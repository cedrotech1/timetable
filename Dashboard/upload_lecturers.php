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
    // Check if file was uploaded
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        sendJsonResponse('error', 'No file uploaded or upload error occurred.');
    }

    // Get file details
    $file = $_FILES['file'];
    $file_name = $file['name'];
    $file_tmp = $file['tmp_name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    // Check file extension
    if (!in_array($file_ext, ['xlsx', 'xls', 'csv'])) {
        sendJsonResponse('error', 'Invalid file format. Please upload an Excel or CSV file.');
    }

    // Read file content
    $data = [];
    if (($handle = fopen($file_tmp, "r")) !== FALSE) {
        // Skip header row
        fgetcsv($handle);
        
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (!empty(array_filter($row))) { // Skip empty rows
                $data[] = [
                    'names' => trim($row[0]),
                    'email' => trim($row[1]),
                    'phone' => trim($row[2]),
                    'campus' => trim($row[3])
                ];
            }
        }
        fclose($handle);
    }

    if (empty($data)) {
        sendJsonResponse('error', 'No valid data found in the file.');
    }

    // Start transaction
    mysqli_begin_transaction($connection);

    $success_count = 0;
    $error_messages = [];

    foreach ($data as $row) {
        // Validate required fields
        if (empty($row['names']) || empty($row['email']) || empty($row['phone']) || empty($row['campus'])) {
            $error_messages[] = "Invalid data for lecturer: {$row['names']}";
            continue;
        }

        // Validate email format
        if (!filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $error_messages[] = "Invalid email format for lecturer: {$row['names']}";
            continue;
        }

        // Check for duplicate email
        $check_query = "SELECT id FROM users WHERE email = ?";
        $check_stmt = mysqli_prepare($connection, $check_query);
        mysqli_stmt_bind_param($check_stmt, "s", $row['email']);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);

        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            $error_messages[] = "Email '{$row['email']}' already exists";
            continue;
        }

        // Generate a random password
        $password = bin2hex(random_bytes(8)); // 16 characters
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert lecturer
        $insert_query = "INSERT INTO users (names, email, phone, campus, role, password, active, image) VALUES (?, ?, ?, ?, 'lecturer', ?, 1, 'assets/img/av.png')";
        $insert_stmt = mysqli_prepare($connection, $insert_query);
        mysqli_stmt_bind_param($insert_stmt, "sssss", $row['names'], $row['email'], $row['phone'], $row['campus'], $hashed_password);

        if (mysqli_stmt_execute($insert_stmt)) {
            $success_count++;
            // TODO: Send email with credentials to the lecturer
        } else {
            $error_messages[] = "Failed to insert lecturer: {$row['names']}";
        }
    }

    if ($success_count > 0) {
        mysqli_commit($connection);
        sendJsonResponse('success', "Successfully uploaded $success_count lecturers", [
            'success_count' => $success_count,
            'error_messages' => $error_messages
        ]);
    } else {
        mysqli_rollback($connection);
        sendJsonResponse('error', 'No lecturers were uploaded successfully', [
            'error_messages' => $error_messages
        ]);
    }

} catch (Exception $e) {
    if (isset($connection)) {
        mysqli_rollback($connection);
    }
    sendJsonResponse('error', 'An error occurred: ' . $e->getMessage());
} 