<?php
session_start();
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

try {
    // Include files
    if (!file_exists('connection.php')) {
        throw new Exception('Database connection file not found');
    }
    // if (!file_exists('./includes/auth.php')) {
    //     throw new Exception('Authentication file not found');
    // }

    include('connection.php');
    // include('./includes/auth.php');

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
            $required_columns = ['name', 'module_code', 'qualification_code', 'credits', 'year', 'semester'];
            $header = array_map('strtolower', $header);
            $missing_columns = array_diff($required_columns, $header);
            
            if (!empty($missing_columns)) {
                fclose($handle);
                sendJsonResponse(false, 'Missing required columns: ' . implode(', ', $missing_columns));
            }

            // Get column indices
            $name_index = array_search('name', $header);
            $module_code_index = array_search('module_code', $header);
            $qualification_code_index = array_search('qualification_code', $header);
            $credits_index = array_search('credits', $header);
            $year_index = array_search('year', $header);
            $semester_index = array_search('semester', $header);

            // Read data rows
            $row_number = 1;
            while (($row = fgetcsv($handle)) !== FALSE) {
                $row_number++;
                
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                // Validate row data
                if (!isset($row[$name_index]) || !isset($row[$module_code_index]) || 
                    !isset($row[$qualification_code_index]) || !isset($row[$credits_index]) ||
                    !isset($row[$year_index]) || !isset($row[$semester_index])) {
                    $error_messages[] = "Row $row_number: Missing required data";
                    continue;
                }

                $name = trim($row[$name_index]);
                $module_code = trim($row[$module_code_index]);
                $qualification_code = trim($row[$qualification_code_index]);
                $credits = trim($row[$credits_index]);
                $year = trim($row[$year_index]);
                $semester = trim($row[$semester_index]);

                // Validate data types
                if (empty($name) || empty($module_code) || empty($qualification_code) || 
                    empty($year) || empty($semester)) {
                    $error_messages[] = "Row $row_number: All fields are required";
                    continue;
                }

                if (!is_numeric($credits) || intval($credits) <= 0) {
                    $error_messages[] = "Row $row_number: Credits must be a positive number";
                    continue;
                }

                if (!is_numeric($year) || intval($year) < 1 || intval($year) > 4) {
                    $error_messages[] = "Row $row_number: Year must be between 1 and 4";
                    continue;
                }

                if (!is_numeric($semester) || intval($semester) < 1 || intval($semester) > 2) {
                    $error_messages[] = "Row $row_number: Semester must be 1 or 2";
                    continue;
                }

                $data[] = [
                    'name' => $name,
                    'module_code' => $module_code,
                    'qualification_code' => $qualification_code,
                    'credits' => intval($credits),
                    'year' => intval($year),
                    'semester' => intval($semester)
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

    // Get all program codes for quick lookup
    $programs_query = "SELECT id, code FROM program";
    $programs_result = mysqli_query($connection, $programs_query);
    $program_codes = [];
    while ($program = mysqli_fetch_assoc($programs_result)) {
        $program_codes[$program['code']] = $program['id'];
    }

    // Validate that we have program codes
    if (empty($program_codes)) {
        sendJsonResponse(false, 'No program codes found in the database. Please add programs first.');
    }

    foreach ($data as $row) {
        $name = mysqli_real_escape_string($connection, $row['name']);
        $module_code = mysqli_real_escape_string($connection, $row['module_code']);
        $qualification_code = mysqli_real_escape_string($connection, $row['qualification_code']);
        $credits = intval($row['credits']);
        $year = intval($row['year']);
        $semester = intval($row['semester']);

        // Check if program exists for this qualification code
        if (!isset($program_codes[$qualification_code])) {
            $error_messages[] = "Module '$name': Qualification code '$qualification_code' does not match any existing program code. Available program codes are: " . implode(', ', array_keys($program_codes));
            continue;
        }

        $program_id = $program_codes[$qualification_code];

        // Check if module already exists for this program, year, and semester
        $check_sql = "SELECT id FROM module WHERE code = '$module_code' AND program_id = $program_id AND year = $year AND semester = $semester";
        $check_result = mysqli_query($connection, $check_sql);

        if (mysqli_num_rows($check_result) > 0) {
            $error_messages[] = "Module with code '$module_code' already exists in this program (qualification code: $qualification_code) for year $year semester $semester";
            continue;
        }

        // Validate credits
        if ($credits <= 0 || $credits > 30) {
            $error_messages[] = "Module '$name': Credits must be between 1 and 30";
            continue;
        }

        // Insert new module
        $sql = "INSERT INTO module (name, code, credits, program_id, year, semester) VALUES ('$name', '$module_code', $credits, $program_id, $year, $semester)";
        
        if (mysqli_query($connection, $sql)) {
            $success_count++;
        } else {
            $error_messages[] = "Error inserting module '$name': " . mysqli_error($connection);
        }
    }

    if ($success_count > 0) {
        mysqli_commit($connection);
        sendJsonResponse(true, "Successfully uploaded $success_count modules.", [
            'success_count' => $success_count,
            'error_messages' => $error_messages
        ]);
    } else {
        mysqli_rollback($connection);
        sendJsonResponse(false, 'No modules were uploaded.', [
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