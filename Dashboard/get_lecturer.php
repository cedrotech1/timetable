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
    // Get lecturer ID
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id <= 0) {
        throw new Exception("Invalid lecturer ID");
    }

    // Build the query
    $query = "SELECT u.*, c.name as campus_name 
              FROM users u 
              LEFT JOIN campus c ON u.campus = c.id 
              WHERE u.id = $id AND u.role = 'lecturer'";

    // Execute the query
    $result = mysqli_query($connection, $query);

    if (!$result) {
        throw new Exception("Query failed: " . mysqli_error($connection));
    }

    // Fetch lecturer data
    $lecturer = mysqli_fetch_assoc($result);

    if (!$lecturer) {
        throw new Exception("Lecturer not found");
    }

    // Return success response
    echo json_encode([
        'success' => true,
        'data' => $lecturer
    ]);

} catch (Exception $e) {
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 