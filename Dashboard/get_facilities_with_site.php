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
    if (in_array($user_role, ['admin', 'registrar_office'], true)) {
        // Admin and registrar office can see all facilities
        $school_id = null;
    // Also treat dean_office like school-scoped users
    } else if ($user_role === 'dean' || $user_role === 'dean_office') {
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
// Read from POST or GET (DataTables may use either)
$req = array_merge($_GET, $_POST);

$start = isset($req['start']) ? intval($req['start']) : 0;
$length = isset($req['length']) ? intval($req['length']) : 5;
$search = '';

if (isset($req['search']['value'])) {
    $search = trim(strval($req['search']['value']));
} elseif (isset($req['search[value]'])) {
    $search = trim(strval($req['search[value]']));
}

$academicYearId = isset($req['academic_year_id']) ? intval($req['academic_year_id']) : 0;
$semester = isset($req['semester']) ? trim(strval($req['semester'])) : '';

// Sessions for availability check: [{day, start, end}, ...]
$sessions = [];
if (!empty($req['sessions'])) {
    $decoded = json_decode($req['sessions'], true);
    if (is_array($decoded)) {
        foreach ($decoded as $s) {
            $day = trim($s['day'] ?? '');
            $startTime = trim($s['start'] ?? '');
            $endTime = trim($s['end'] ?? '');
            if ($day === '' || $startTime === '' || $endTime === '') {
                continue;
            }
            if (preg_match('/^\d{2}:\d{2}$/', $startTime)) $startTime .= ':00';
            if (preg_match('/^\d{2}:\d{2}$/', $endTime)) $endTime .= ':00';
            if ($startTime >= $endTime) {
                continue;
            }
            $sessions[] = [
                'day' => $day,
                'start' => $startTime,
                'end' => $endTime,
            ];
        }
    }
}

// Use the start and length parameters directly from DataTables
$offset = $start;
$perPage = max(1, $length);

$searchEsc = mysqli_real_escape_string($connection, $search);
$schoolIdEsc = $school_id ? intval($school_id) : 0;
$academicYearEsc = intval($academicYearId);
$semesterEsc = mysqli_real_escape_string($connection, $semester);
$minCapacity = isset($req['minCapacity']) ? max(0, intval($req['minCapacity'])) : 0;
$minCapacityEsc = intval($minCapacity);

/**
 * Facilities already booked for overlapping sessions in this academic year + semester.
 */
function buildAvailabilityCondition($connection, $sessions, $academicYearEsc, $semesterEsc) {
    if (empty($sessions) || !$academicYearEsc || $semesterEsc === '') {
        return '';
    }

    $overlapParts = [];
    foreach ($sessions as $s) {
        $day = mysqli_real_escape_string($connection, $s['day']);
        $start = mysqli_real_escape_string($connection, $s['start']);
        $end = mysqli_real_escape_string($connection, $s['end']);
        // Overlap: existing.start < requested.end AND existing.end > requested.start
        $overlapParts[] = "(ts.day = '$day' AND ts.start_time < '$end' AND ts.end_time > '$start')";
    }

    if (empty($overlapParts)) {
        return '';
    }

    $overlapSql = implode(' OR ', $overlapParts);
    return " AND NOT EXISTS (
        SELECT 1
        FROM timetable t
        INNER JOIN timetable_sessions ts ON ts.timetable_id = t.id
        WHERE t.facility_id = f.id
          AND t.academic_year_id = $academicYearEsc
          AND t.semester = '$semesterEsc'
          AND LOWER(t.status) = 'approved'
          AND ($overlapSql)
    )";
}

$availabilityCondition = buildAvailabilityCondition($connection, $sessions, $academicYearEsc, $semesterEsc);

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
}

// Add search condition (name/site/building, and capacity if search is numeric)
if ($search !== '') {
    if (ctype_digit($search)) {
        $capacitySearch = intval($search);
        $whereConditions[] = "(f.name LIKE '%$searchEsc%' OR s.name LIKE '%$searchEsc%' OR f.buildname LIKE '%$searchEsc%' OR f.capacity = $capacitySearch OR f.capacity >= $capacitySearch)";
    } else {
        $whereConditions[] = "(f.name LIKE '%$searchEsc%' OR s.name LIKE '%$searchEsc%' OR f.buildname LIKE '%$searchEsc%')";
    }
}

if ($minCapacityEsc > 0) {
    $whereConditions[] = "f.capacity >= $minCapacityEsc";
}

// Combine all conditions
if (!empty($whereConditions)) {
    $countSql .= " WHERE " . implode(' AND ', $whereConditions);
}

// Append availability filter (starts with AND ...)
if ($availabilityCondition !== '') {
    $countSql .= (stripos($countSql, ' WHERE ') !== false ? '' : ' WHERE 1=1') . $availabilityCondition;
}

$countResult = mysqli_query($connection, $countSql);
if (!$countResult) {
    echo json_encode(['success' => false, 'message' => 'Count query failed', 'error' => mysqli_error($connection)]);
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

$dataWhere = [];
if (!in_array($user_role, ['admin', 'registrar_office'], true) && $school_id) {
    $dataWhere[] = "EXISTS (
        SELECT 1 FROM site_school ss 
        WHERE ss.site_id = s.id AND ss.school_id = $schoolIdEsc
    )";
}

if ($search !== '') {
    if (ctype_digit($search)) {
        $capacitySearch = intval($search);
        $dataWhere[] = "(f.name LIKE '%$searchEsc%' OR s.name LIKE '%$searchEsc%' OR f.buildname LIKE '%$searchEsc%' OR f.capacity = $capacitySearch OR f.capacity >= $capacitySearch)";
    } else {
        $dataWhere[] = "(f.name LIKE '%$searchEsc%' OR s.name LIKE '%$searchEsc%' OR f.buildname LIKE '%$searchEsc%')";
    }
}

if ($minCapacityEsc > 0) {
    $dataWhere[] = "f.capacity >= $minCapacityEsc";
}

if (!empty($dataWhere)) {
    $sql .= " WHERE " . implode(' AND ', $dataWhere);
}

if ($availabilityCondition !== '') {
    $sql .= (stripos($sql, ' WHERE ') !== false ? '' : ' WHERE 1=1') . $availabilityCondition;
}

$sql .= " ORDER BY f.capacity DESC, f.name ASC LIMIT $offset, $perPage";

$result = mysqli_query($connection, $sql);
if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Facilities query failed', 'error' => mysqli_error($connection)]);
    exit;
}

$facilities = [];
while ($facility = mysqli_fetch_assoc($result)) {
    $facilities[] = $facility;
}

// Prepare the response in DataTables expected format
$draw = isset($req['draw']) ? intval($req['draw']) : 1;

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
    'recordsTotal' => $totalRecords,
    'recordsFiltered' => $filteredRecords,
    'data' => $formattedData
];

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

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
