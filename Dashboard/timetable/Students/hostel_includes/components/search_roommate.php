<?php
// Prevent any output before JSON
ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable error display, we'll handle it ourselves

// Fix the connection path to point to the correct location
require_once '../connection.php';

// Clear any previous output
ob_clean();

// Set JSON header
header('Content-Type: application/json');

try {
    // Check database connection
    if (!isset($connection) || $connection->connect_error) {
        throw new Exception('Database connection failed: ' . ($connection->connect_error ?? 'Unknown error'));
    }

    // Check if reg_number is provided
    if (!isset($_GET['reg_number']) || empty($_GET['reg_number'])) {
        throw new Exception('Registration number is required');
    }

    $reg_number = trim($_GET['reg_number']);
    error_log("Searching for roommate with reg number: " . $reg_number);

    // First check if the student exists and is eligible
    $query = "SELECT regnumber, name, email 
              FROM students 
              WHERE regnumber = ? 
              AND status = 'active'";
    
    error_log("Executing query: " . $query);
    
    $stmt = $connection->prepare($query);
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $connection->error);
    }
    
    $stmt->bind_param("s", $reg_number);
    if (!$stmt->execute()) {
        throw new Exception('Database execute error: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception('Database result error: ' . $stmt->error);
    }
    
    error_log("Found " . $result->num_rows . " matching students");
    
    if ($result->num_rows === 0) {
        throw new Exception('Student not found or not eligible for hostel application');
    }
    
    $student = $result->fetch_assoc();
    error_log("Found student: " . json_encode($student));
    
    // Check if student already has an active application
    $query = "SELECT id, status 
              FROM applications 
              WHERE regnumber = ? 
              AND status IN ('pending', 'approved')";
    
    error_log("Executing applications query: " . $query);
    
    $stmt = $connection->prepare($query);
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $connection->error);
    }
    
    $stmt->bind_param("s", $reg_number);
    if (!$stmt->execute()) {
        throw new Exception('Database execute error: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception('Database result error: ' . $stmt->error);
    }
    
    error_log("Found " . $result->num_rows . " existing applications");
    
    if ($result->num_rows > 0) {
        $application = $result->fetch_assoc();
        if ($application['status'] === 'approved') {
            throw new Exception('This student already has an approved room');
        } else {
            throw new Exception('This student already has a pending application');
        }
    }
    
    // Return success response with student details
    $response = [
        'success' => true,
        'student' => [
            'reg_number' => $student['regnumber'],
            'name' => $student['name'],
            'email' => $student['email']
        ]
    ];
    
    error_log("Sending success response: " . json_encode($response));
    echo json_encode($response);

} catch (Exception $e) {
    error_log('Roommate search error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    http_response_code(400);
    $error_response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
    error_log('Sending error response: ' . json_encode($error_response));
    echo json_encode($error_response);
}

// End output buffering and send
ob_end_flush(); 