<?php
// Start output buffering to catch any unwanted output
ob_start();

// Disable error display to prevent HTML in JSON response
ini_set('display_errors', 0);

// Log errors to a file
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/facility_errors.log');

// Set JSON content type
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Function to send JSON error response
function sendError($message, $code = 500) {
    http_response_code($code);
    echo json_encode([
        'draw' => isset($_GET['draw']) ? intval($_GET['draw']) : 0,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => $message
    ]);
    exit;
}

// Start session and include connection
session_start();

try {
    if (!file_exists('connection.php')) {
        throw new Exception('Database connection file not found');
    }
    
    include('connection.php');
    
    if (!isset($connection) || !$connection) {
        throw new Exception('Database connection failed');
    }

    // Check if user is logged in
    if (!isset($_SESSION['id'])) {
        sendError('User not authenticated', 401);
    }

    $user_id = $_SESSION['id'];
    $user_role = $_SESSION['role'] ?? '';

    // Handle role-based access control
    if ($user_role === 'registrar_office') {
        // Registrar office can see all facilities
        $school_id = null;
    } else if ($user_role === 'dean') {
        // For deans, get their school and associated sites
        $stmt = $connection->prepare("SELECT school FROM users WHERE id = ?");
        if (!$stmt) {
            throw new Exception('Failed to prepare statement: ' . $connection->error);
        }
        
        $stmt->bind_param("i", $user_id);
        if (!$stmt->execute()) {
            throw new Exception('Failed to execute query: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        if (!$result) {
            throw new Exception('Failed to get result: ' . $stmt->error);
        }
        
        $school = $result->fetch_assoc();
        $school_id = $school ? $school['school'] : null;
        
        if (!$school_id) {
            sendError('No school assigned to your dean account', 403);
        }
    } else {
        // For other roles, use their school but with site restrictions
        $stmt = $connection->prepare("SELECT school FROM users WHERE id = ?");
        if (!$stmt) {
            throw new Exception('Failed to prepare statement: ' . $connection->error);
        }
        
        $stmt->bind_param("i", $user_id);
        if (!$stmt->execute()) {
            throw new Exception('Failed to execute query: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        if (!$result) {
            throw new Exception('Failed to get result: ' . $stmt->error);
        }
        
        $school = $result->fetch_assoc();
        $school_id = $school ? $school['school'] : null;
        
        if (!$school_id) {
            sendError('No school ID found for your account', 403);
        }
    }
// Get pagination parameters from DataTables
$start = isset($_GET['start']) ? intval($_GET['start']) : 0;
$length = isset($_GET['length']) ? intval($_GET['length']) : 5; // Default to 5 items per page
$search = '';

// Handle search parameter safely
if (isset($_GET['search']['value'])) {
    $search = trim(strval($_GET['search']['value']));
}

$minCapacity = isset($_GET['minCapacity']) ? max(0, intval($_GET['minCapacity'])) : 0;

// Use the start and length parameters directly from DataTables
$offset = $start;
$perPage = $length;

$searchEsc = mysqli_real_escape_string($connection, $search);
$schoolIdEsc = $school_id ? intval($school_id) : 0;
$minCapacityEsc = intval($minCapacity);

// Count total matching facilities
$countSql = "
    SELECT COUNT(DISTINCT f.id) as total 
    FROM facility f
    JOIN site s ON f.site = s.id
";

// Add conditions for the count query
$whereConditions = [];

// Add school filter based on user role
if ($school_id) {
    // For deans and other non-registrar roles, filter by their school's sites
    $whereConditions[] = "EXISTS (
        SELECT 1 FROM site_school ss 
        WHERE ss.site_id = s.id AND ss.school_id = $schoolIdEsc
    )";
    
    // If you have additional site-based restrictions, add them here
    // For example, if deans have specific site access beyond just school:
    if ($user_role === 'dean') {
        // Add any additional dean-specific site restrictions here if needed
        // $whereConditions[] = "additional_condition_for_dean";
    }
}

// Add search condition
if ($search !== '') {
    $whereConditions[] = "(f.name LIKE '%$searchEsc%' OR s.name LIKE '%$searchEsc%')";
}

// Combine all conditions
if (!empty($whereConditions)) {
    $countSql .= " WHERE " . implode(' AND ', $whereConditions);
}

$countResult = mysqli_query($connection, $countSql);
if (!$countResult) {
    echo json_encode(['success' => false, 'message' => 'Count query failed']);
    exit;
}

$row = mysqli_fetch_assoc($countResult);
$totalRecords = (int)$row['total'];

// For filtered records (same as total for now, adjust if needed)
$filteredRecords = $totalRecords;

// First, get the distinct facilities with their site info
$sql = "
    SELECT DISTINCT
        f.id,
        f.name,
        f.type,
        f.capacity,
        s.name as site_name,
        s.id as site_id,
        f.buildname as building_name,
        NULL as building_id,    
        (
            SELECT GROUP_CONCAT(DISTINCT sch.name SEPARATOR ', ')
            FROM site_school ss2
            LEFT JOIN school sch ON ss2.school_id = sch.id
            WHERE ss2.site_id = s.id
        ) as school_names
    FROM facility f
    JOIN site s ON f.site = s.id
";

// Add school filter only if not registrar_office
if ($user_role !== 'registrar_office' && $school_id) {
    $sql .= " WHERE EXISTS (
        SELECT 1 FROM site_school ss 
        WHERE ss.site_id = s.id AND ss.school_id = $schoolIdEsc
    )";
    $whereClause = true;
}

if ($search !== '') {
    $sql .= (strpos($sql, 'WHERE') !== false ? ' AND' : ' WHERE') . " (f.name LIKE '%$searchEsc%' OR s.name LIKE '%$searchEsc%')";
}

$sql .= " ORDER BY f.name ASC LIMIT $offset, $perPage";

$result = mysqli_query($connection, $sql);
if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Facilities query failed']);
    exit;
}

$facilities = [];
while ($facility = mysqli_fetch_assoc($result)) {
    $facilities[] = $facility;
}

// Prepare the response in DataTables expected format
$draw = isset($_GET['draw']) ? intval($_GET['draw']) : 1;

// Format the facilities data to match the expected structure
$formattedData = [];
$processedIds = [];

foreach ($facilities as $facility) {
    // Skip if we've already processed this facility
    if (in_array($facility['id'], $processedIds)) {
        continue;
    }
    
    $formattedData[] = [
        'DT_RowId' => 'row_' . ($facility['id'] ?? ''),
        'id' => $facility['id'] ?? 0,
        'buildname' => !empty($facility['building_name']) ? $facility['building_name'] : 'N/A',
        'name' => $facility['name'] ?? 'Unknown',
        'type' => $facility['type'] ?? 'N/A',
        'capacity' => (int)($facility['capacity'] ?? 0),
        'site_name' => $facility['site_name'] ?? 'N/A',
        'school_names' => $facility['school_names'] ?? 'Not assigned',
        'status' => 'Adequate', // Default status
        'action' => '<button class="select-facility-btn" data-id="' . ($facility['id'] ?? '') . '">Select</button>'
    ];
    
    $processedIds[] = $facility['id'];
}

$response = [
    'draw' => $draw,
    'recordsTotal' => $totalRecords,    // Total records in the database
    'recordsFiltered' => $filteredRecords, // Total records after filtering (same as total for now)
    'data' => $formattedData
];

// Log the response for debugging
error_log('Sending response: ' . print_r($response, true));

// Set content type and prevent caching
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Clear any previous output
ob_clean();

// Output the JSON
$json = json_encode($response);

// Check for JSON encoding errors
if ($json === false) {
    $response = [
        'draw' => $draw,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'JSON Encode Error: ' . json_last_error_msg()
    ];
    $json = json_encode($response);
}

// Output the response
ob_clean();
echo json_encode($response);
exit;

} catch (Exception $e) {
    // Log the error
    error_log('Facility Selector Error: ' . $e->getMessage());
    
    // Send JSON error response
    ob_clean();
    sendError('An error occurred while processing your request: ' . $e->getMessage());
}

// Ensure no output after this point
exit;
