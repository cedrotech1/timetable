<?php
// Prevent any output before JSON response
ob_start();

// Increase memory limit and execution time for large files
ini_set('memory_limit', '512M');
set_time_limit(300); // 5 minutes

// Include files
include('connection.php');
include('./includes/auth.php');

// Check user role
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'User not authenticated',
        'data' => ['errors' => ['Authentication required']]
    ]);
    exit;
}

// Check user role
$userId = $_SESSION['id'];
$roleQuery = "SELECT role FROM users WHERE id = '$userId'";
$roleResult = $connection->query($roleQuery);

if (!$roleResult || $roleResult->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'User role not found',
        'data' => ['errors' => ['Invalid user role']]
    ]);
    exit;
}

$userRole = $roleResult->fetch_assoc()['role'];
if ($userRole !== 'warefare') {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access',
        'data' => ['errors' => ['Insufficient permissions']]
    ]);
    exit;
}

// Clear any output buffers
ob_end_clean();

// Set JSON header
header('Content-Type: application/json');

// Function to send JSON response
function sendJsonResponse($status, $message, $data = null) {
    $response = [
        'status' => $status,
        'message' => $message
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit;
}

try {
    // Get JSON data
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);

    if (!isset($data['data']) || !is_array($data['data']) || empty($data['data'])) {
        sendJsonResponse('error', 'Invalid or empty data received');
    }

    // Get the header row (first row) to determine column indexes
    $headers = array_map('strtolower', $data['data'][0]);

    // Required columns
    $required_columns = [
        'regnumber',
        'campus',
        'college',
        'sirname',
        'lastname',
        'school',
        'program',
        'yearofstudy',
        'email',
        'gender',
        'nid',
        'phone'
    ];

    // Validate all required columns exist
    $missing_columns = [];
    foreach ($required_columns as $column) {
        if (!in_array($column, $headers)) {
            $missing_columns[] = $column;
        }
    }

    if (!empty($missing_columns)) {
        sendJsonResponse('error', 'Missing required columns: ' . implode(', ', $missing_columns));
    }

    // Find indexes based on column names
    $regnumberIndex   = array_search('regnumber', $headers);
    $campusIndex      = array_search('campus', $headers);
    $collegeIndex     = array_search('college', $headers);
    $sirnameIndex     = array_search('sirname', $headers);
    $lastnameIndex    = array_search('lastname', $headers);
    $schoolIndex      = array_search('school', $headers);
    $programIndex     = array_search('program', $headers);
    $yearofstudyIndex = array_search('yearofstudy', $headers);
    $emailIndex       = array_search('email', $headers);
    $genderIndex      = array_search('gender', $headers);
    $nidIndex         = array_search('nid', $headers);
    $phoneIndex       = array_search('phone', $headers);

    // Get user's assigned campus
    $campusQuery = "SELECT c.name FROM campuses c 
                   INNER JOIN users u ON u.campus = c.id 
                   WHERE u.id = '$userId'";
    $campusResult = $connection->query($campusQuery);
    
    if (!$campusResult || $campusResult->num_rows === 0) {
        sendJsonResponse('error', 'User is not associated with any campus');
    }
    
    $userCampus = strtolower(trim($campusResult->fetch_assoc()['name']));

    // Skip the header row and process data rows
    $dataRows = array_slice($data['data'], 1);
    $results = [
        'success' => [],
        'errors' => []
    ];

    // Prepare batch insert
    $batchSize = 100; // Process 100 rows at a time
    $totalRows = count($dataRows);
    $processedRows = 0;
    $batchValues = [];
    $batchErrors = [];

    foreach ($dataRows as $rowIndex => $row) {
        $rowNumber = $rowIndex + 2; // +2 because we skipped header and array is 0-based
        
        // Check if row is empty or contains only whitespace
        $isEmptyRow = true;
        foreach ($row as $cell) {
            if (trim($cell) !== '') {
                $isEmptyRow = false;
                break;
            }
        }
        
        if ($isEmptyRow) {
            continue; // Skip empty rows silently
        }

        // Validate required fields
        if (empty($row[$regnumberIndex]) || empty($row[$sirnameIndex])) {
            $batchErrors[] = "Row $rowNumber: Missing required fields (regnumber or sirname)";
            continue;
        }

        $regnumberRaw = $row[$regnumberIndex];
        $regnumber = strtolower(trim($connection->real_escape_string($regnumberRaw)));

        // Check if regnumber already exists
        $check = $connection->query("SELECT id FROM info WHERE LOWER(TRIM(regnumber)) = '$regnumber'");
        if ($check && $check->num_rows > 0) {
            $batchErrors[] = "Row $rowNumber: Registration number '$regnumber' already exists";
            continue;
        }

        $campusInput = strtolower(trim($connection->real_escape_string($row[$campusIndex])));

        // Validate campus matches user's assigned campus
        if ($campusInput !== $userCampus) {
            $batchErrors[] = "Row $rowNumber: Campus '$campusInput' does not match your assigned campus '$userCampus'";
            continue;
        }

        // Check if campus exists
        $campusResult = $connection->query("SELECT id FROM campuses WHERE LOWER(TRIM(name)) = '$campusInput'");
        if (!$campusResult || $campusResult->num_rows === 0) {
            $batchErrors[] = "Row $rowNumber: Campus '$campusInput' does not exist";
            continue;
        }

        // Prepare data
        $college     = $connection->real_escape_string($row[$collegeIndex]);
        $sirname     = $connection->real_escape_string($row[$sirnameIndex]);
        $lastname    = $connection->real_escape_string($row[$lastnameIndex]);
        $names       = "$sirname $lastname";
        $school      = $connection->real_escape_string($row[$schoolIndex]);
        $program     = $connection->real_escape_string($row[$programIndex]);
        $yearofstudy = $connection->real_escape_string($row[$yearofstudyIndex]);
        $email       = $connection->real_escape_string($row[$emailIndex]);
        $genderRaw   = strtolower(trim($connection->real_escape_string($row[$genderIndex])));

        // Validate and normalize gender
        $gender = ($genderRaw === 'm' || $genderRaw === 'male') ? 'male' :
                  (($genderRaw === 'f' || $genderRaw === 'female') ? 'female' : '');
        
        if (empty($gender)) {
            $batchErrors[] = "Row $rowNumber: Invalid gender value '$genderRaw'";
            continue;
        }

        $nid         = $connection->real_escape_string($row[$nidIndex]);
        $phone       = preg_replace('/\D/', '', $row[$phoneIndex]); // keep digits only
        if (isset($phone[0]) && $phone[0] !== '0') {
            $phone = '0' . $phone;
        }

        // Validate phone number
        if (strlen($phone) < 10) {
            $batchErrors[] = "Row $rowNumber: Invalid phone number '$phone'";
            continue;
        }

        // Optional fields
        $token  = '';
        $status = 'active';

        // Add to batch
        $batchValues[] = "('$regnumber', '$campusInput', '$college', '$names', '$school',
                         '$program', '$yearofstudy', '$email', '$gender', '$nid', '$phone', '$token', '$status')";
        
        $processedRows++;

        // Process batch when it reaches the batch size
        if (count($batchValues) >= $batchSize || $processedRows >= $totalRows) {
            if (!empty($batchValues)) {
                $sql = "INSERT INTO info (
                            regnumber, campus, college, names, school, program,
                            yearofstudy, email, gender, nid, phone, token, status
                        ) VALUES " . implode(',', $batchValues);

                if (!$connection->query($sql)) {
                    $batchErrors[] = "Batch error: " . $connection->error;
                } else {
                    $results['success'][] = "Successfully inserted " . count($batchValues) . " records";
                }
            }
            $batchValues = []; // Clear batch
        }
    }

    // Add any remaining errors to results
    if (!empty($batchErrors)) {
        $results['errors'] = array_merge($results['errors'], $batchErrors);
    }

    $connection->close();

    // Send final response
    if (empty($results['errors'])) {
        sendJsonResponse('success', "Successfully processed $processedRows records", $results);
    } else {
        sendJsonResponse('partial', "Processed $processedRows records with some errors", $results);
    }

} catch (Exception $e) {
    sendJsonResponse('error', 'An error occurred: ' . $e->getMessage());
}
?>