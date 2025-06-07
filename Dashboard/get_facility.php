<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header
header('Content-Type: application/json');

try {
    require_once('connection.php');

    // Validate input
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('Facility ID is required');
    }

    $id = (int)$_GET['id'];

    // Get facility details
    $query = "SELECT f.*, c.name as campus_name 
              FROM facility f 
              JOIN campus c ON f.campus_id = c.id 
              WHERE f.id = $id";
    
    $result = mysqli_query($connection, $query);
    if (!$result) {
        throw new Exception("Query failed: " . mysqli_error($connection));
    }

    $facility = mysqli_fetch_assoc($result);
    if (!$facility) {
        throw new Exception('Facility not found');
    }

    echo json_encode([
        'success' => true,
        'data' => $facility
    ]);

} catch (Exception $e) {
    error_log("Get facility error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 