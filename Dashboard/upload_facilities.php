<?php
// Prevent any output before JSON response
ob_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Increase memory limit and execution time for large files
ini_set('memory_limit', '512M');
set_time_limit(300); // 5 minutes

// Set JSON header
header('Content-Type: application/json');

// Function to send JSON response
function sendJsonResponse($success, $message, $data = null) {
    $response = [
        'success' => $success,
        'message' => $message
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit;
}

// Function to convert Excel file to CSV
function convertExcelToCsv($inputFile) {
    $outputFile = tempnam(sys_get_temp_dir(), 'csv_');
    $command = "xlsx2csv -d tab \"$inputFile\" \"$outputFile\"";
    exec($command, $output, $returnVar);
    
    if ($returnVar !== 0) {
        throw new Exception('Failed to convert Excel file to CSV');
    }
    
    return $outputFile;
}

try {
    // Include files
    if (!file_exists('connection.php')) {
        throw new Exception('Database connection file not found');
    }
    if (!file_exists('./includes/auth.php')) {
        throw new Exception('Authentication file not found');
    }

    include('connection.php');
    include('./includes/auth.php');

    // Check user role
    if (!isset($_SESSION['id'])) {
        sendJsonResponse(false, 'User not authenticated');
    }

    // Check if file was uploaded
    if (!isset($_FILES['file'])) {
        sendJsonResponse(false, 'No file was uploaded.');
    }

    if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $error_message = match($_FILES['file']['error']) {
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
            default => 'Unknown upload error'
        };
        sendJsonResponse(false, 'Upload error: ' . $error_message);
    }

    // Check if campus_id is provided
    if (!isset($_POST['campus_id']) || empty($_POST['campus_id'])) {
        sendJsonResponse(false, 'Please select a campus.');
    }

    $campus_id = intval($_POST['campus_id']);

    // Get file details
    $file = $_FILES['file'];
    $file_name = $file['name'];
    $file_tmp = $file['tmp_name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    // Check file extension
    if ($file_ext !== 'csv') {
        sendJsonResponse(false, 'Invalid file format. Please upload a CSV file.');
    }

    // Read file content
    $data = [];
    $error_messages = [];

    try {
        if (($handle = fopen($file_tmp, "r")) !== FALSE) {
            // Read header row
            $header = fgetcsv($handle);
            if ($header === FALSE) {
                fclose($handle);
                sendJsonResponse(false, 'Failed to read the file header.');
            }

            // Validate header
            $required_columns = ['name', 'type', 'capacity', 'location'];
            $header = array_map('strtolower', $header);
            $missing_columns = array_diff($required_columns, $header);
            
            if (!empty($missing_columns)) {
                fclose($handle);
                sendJsonResponse(false, 'Missing required columns: ' . implode(', ', $missing_columns));
            }

            // Get column indices
            $name_index = array_search('name', $header);
            $type_index = array_search('type', $header);
            $capacity_index = array_search('capacity', $header);
            $location_index = array_search('location', $header);

            // Read data rows
            $row_number = 1;
            while (($row = fgetcsv($handle)) !== FALSE) {
                $row_number++;
                
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                // Validate row data
                if (!isset($row[$name_index]) || !isset($row[$type_index]) || !isset($row[$capacity_index]) || !isset($row[$location_index])) {
                    $error_messages[] = "Row $row_number: Missing required data";
                    continue;
                }

                $name = trim($row[$name_index]);
                $type = trim($row[$type_index]);
                $capacity = trim($row[$capacity_index]);
                $location = trim($row[$location_index]);

                // Validate data types
                if (empty($name) || empty($type) || empty($location)) {
                    $error_messages[] = "Row $row_number: Name, type, and location cannot be empty";
                    continue;
                }

                if (!is_numeric($capacity) || intval($capacity) <= 0) {
                    $error_messages[] = "Row $row_number: Capacity must be a positive number";
                    continue;
                }

                $data[] = [
                    'name' => $name,
                    'type' => $type,
                    'capacity' => intval($capacity),
                    'location' => $location
                ];
            }
            fclose($handle);
        }
    } catch (Exception $e) {
        sendJsonResponse(false, 'Error reading file: ' . $e->getMessage());
    }

    if (empty($data)) {
        sendJsonResponse(false, 'No valid data found in the file.');
    }

    // Start transaction
    mysqli_begin_transaction($connection);

    $success_count = 0;
    $error_messages = [];

    foreach ($data as $row) {
        $name = mysqli_real_escape_string($connection, $row['name']);
        $type = mysqli_real_escape_string($connection, $row['type']);
        $capacity = intval($row['capacity']);
        $location = mysqli_real_escape_string($connection, $row['location']);

        // Check if facility already exists in the same campus and location
        $check_sql = "SELECT id FROM facility WHERE name = '$name' AND campus_id = $campus_id AND location = '$location'";
        $check_result = mysqli_query($connection, $check_sql);

        if (mysqli_num_rows($check_result) > 0) {
            $error_messages[] = "Facility '$name' already exists in this campus and location";
            continue;
        }

        // Insert new facility
        $sql = "INSERT INTO facility (name, type, capacity, campus_id, location) VALUES ('$name', '$type', $capacity, $campus_id, '$location')";
        
        if (mysqli_query($connection, $sql)) {
            $success_count++;
        } else {
            $error_messages[] = "Error inserting facility '$name': " . mysqli_error($connection);
        }
    }

    if ($success_count > 0) {
        mysqli_commit($connection);
        sendJsonResponse(true, "Successfully uploaded $success_count facilities.", [
            'success_count' => $success_count,
            'error_messages' => $error_messages
        ]);
    } else {
        mysqli_rollback($connection);
        sendJsonResponse(false, 'No facilities were uploaded.', [
            'error_messages' => $error_messages
        ]);
    }

} catch (Exception $e) {
    if (isset($connection)) {
        mysqli_rollback($connection);
    }
    sendJsonResponse(false, $e->getMessage(), [
        'error_details' => [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
} 