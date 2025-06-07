<?php
include('connection.php');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Get JSON data from request body
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data) {
        throw new Exception("Invalid JSON data");
    }

    // Validate required fields
    $required_fields = ['id', 'names', 'email', 'campus', 'status'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Sanitize input data
    $id = (int)$data['id'];
    $names = mysqli_real_escape_string($connection, $data['names']);
    $email = mysqli_real_escape_string($connection, $data['email']);
    $campus = (int)$data['campus'];
    $status = mysqli_real_escape_string($connection, $data['status']);

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email format");
    }

    // Check if email is already used by another user
    $email_check_query = "SELECT id FROM users WHERE email = '$email' AND id != $id";
    $email_check_result = mysqli_query($connection, $email_check_query);
    if (mysqli_num_rows($email_check_result) > 0) {
        throw new Exception("Email is already in use by another user");
    }

    // Build the update query
    $query = "UPDATE users SET 
              names = '$names',
              email = '$email',
              campus = $campus,
              active = " . ($status === 'active' ? '1' : '0') . "
              WHERE id = $id AND role = 'lecturer'";

    // Execute the query
    $result = mysqli_query($connection, $query);

    if (!$result) {
        throw new Exception("Failed to update lecturer: " . mysqli_error($connection));
    }

    if (mysqli_affected_rows($connection) === 0) {
        throw new Exception("No changes were made to the lecturer");
    }

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Lecturer updated successfully'
    ]);

} catch (Exception $e) {
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 