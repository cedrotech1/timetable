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

// Log the start of the script
error_log("Delete facility script started");

try {
    require_once('connection.php');
    error_log("Database connection established");

    // Log POST data
    error_log("POST data received: " . print_r($_POST, true));

    // Validate input
    if (!isset($_POST['id']) || empty($_POST['id'])) {
        error_log("Error: Facility ID is missing or empty");
        throw new Exception('Facility ID is required');
    }

    $id = (int)$_POST['id'];
    error_log("Processing delete request for facility ID: " . $id);

    // Check if facility exists
    $check_query = "SELECT id FROM facility WHERE id = $id";
    error_log("Executing existence check query: " . $check_query);
    
    $check_result = mysqli_query($connection, $check_query);
    if (!$check_result) {
        error_log("Existence check query failed: " . mysqli_error($connection));
        throw new Exception("Check query failed: " . mysqli_error($connection));
    }

    if (mysqli_num_rows($check_result) === 0) {
        error_log("Error: Facility with ID $id not found");
        throw new Exception('Facility not found');
    }
    error_log("Facility exists, proceeding with usage check");

    // Check if facility is used in any timetable
    $check_usage = "SELECT ts.id 
                   FROM timetable_sessions ts 
                   JOIN timetable t ON ts.timetable_id = t.id 
                   WHERE t.facility_id = $id 
                   LIMIT 1";
    error_log("Executing usage check query: " . $check_usage);
    
    $usage_result = mysqli_query($connection, $check_usage);
    if (!$usage_result) {
        error_log("Usage check query failed: " . mysqli_error($connection));
        throw new Exception("Usage check failed: " . mysqli_error($connection));
    }

    if (mysqli_num_rows($usage_result) > 0) {
        error_log("Error: Facility $id is being used in timetable sessions");
        throw new Exception('Cannot delete facility because it is being used in timetable sessions');
    }
    error_log("Facility is not in use, proceeding with deletion");

    // Delete facility
    $query = "DELETE FROM facility WHERE id = $id";
    error_log("Executing delete query: " . $query);
    
    if (!mysqli_query($connection, $query)) {
        error_log("Delete query failed: " . mysqli_error($connection));
        throw new Exception("Delete failed: " . mysqli_error($connection));
    }

    error_log("Facility deleted successfully");
    echo json_encode([
        'success' => true,
        'message' => 'Facility deleted successfully'
    ]);

} catch (Exception $e) {
    error_log("Delete facility error: " . $e->getMessage());
    error_log("Error trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 