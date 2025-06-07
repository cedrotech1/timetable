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
    if (!isset($data['id']) || empty($data['id'])) {
        throw new Exception("Missing lecturer ID");
    }

    // Sanitize input data
    $id = (int)$data['id'];

    // Check if lecturer exists
    $check_query = "SELECT id FROM users WHERE id = $id AND role = 'lecturer'";
    $check_result = mysqli_query($connection, $check_query);
    if (mysqli_num_rows($check_result) === 0) {
        throw new Exception("Lecturer not found");
    }

    // Check if lecturer is assigned to any timetable sessions
    $usage_check_query = "SELECT ts.id 
                         FROM timetable_sessions ts 
                         JOIN timetable t ON ts.timetable_id = t.id 
                         WHERE t.lecturer_id = $id 
                         LIMIT 1";
    $usage_check_result = mysqli_query($connection, $usage_check_query);
    if (mysqli_num_rows($usage_check_result) > 0) {
        throw new Exception("Cannot delete lecturer: They are assigned to timetable sessions");
    }

    // Build the delete query
    $query = "DELETE FROM users WHERE id = $id AND role = 'lecturer'";

    // Execute the query
    $result = mysqli_query($connection, $query);

    if (!$result) {
        throw new Exception("Failed to delete lecturer: " . mysqli_error($connection));
    }

    if (mysqli_affected_rows($connection) === 0) {
        throw new Exception("No lecturer was deleted");
    }

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Lecturer deleted successfully'
    ]);

} catch (Exception $e) {
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 