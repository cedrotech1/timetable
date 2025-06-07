<?php
// Prevent any output before headers
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set header to return JSON
header('Content-Type: application/json');

// Function to send JSON response
function sendJsonResponse($success, $message = '', $data = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'facilities' => $data
    ]);
    exit;
}

try {
    include('connection.php');

    // Check if request is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJsonResponse(false, 'Invalid request method');
    }

    // Get JSON input
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data) {
        sendJsonResponse(false, 'Invalid JSON data');
    }

    // Get and validate input
    $day = isset($data['day']) ? $data['day'] : null;
    $start_time = isset($data['start_time']) ? $data['start_time'] : null;
    $end_time = isset($data['end_time']) ? $data['end_time'] : null;

    // Validate required fields
    if (!$day || !$start_time || !$end_time) {
        sendJsonResponse(false, 'Missing required parameters');
    }

    // Get all facilities
    $facilities_query = "SELECT f.*, c.name as campus_name 
                        FROM facility f 
                        LEFT JOIN campus c ON f.campus_id = c.id 
                        WHERE f.type = 'classroom' OR f.type = 'Lecture Hall' OR f.type = 'Laboratory'";
    $facilities_result = mysqli_query($connection, $facilities_query);
    
    if (!$facilities_result) {
        throw new Exception("Error fetching facilities: " . mysqli_error($connection));
    }

    $available_facilities = [];

    while ($facility = mysqli_fetch_assoc($facilities_result)) {
        // Check if facility is already booked for this time slot
        $check_query = "SELECT ts.* FROM timetable_sessions ts 
                       JOIN timetable t ON ts.timetable_id = t.id 
                       WHERE t.facility_id = ? AND ts.day = ? 
                       AND ((ts.start_time <= ? AND ts.end_time > ?) 
                           OR (ts.start_time < ? AND ts.end_time >= ?) 
                           OR (ts.start_time >= ? AND ts.end_time <= ?))";
        
        $check_stmt = mysqli_prepare($connection, $check_query);
        if (!$check_stmt) {
            throw new Exception("Error preparing check query: " . mysqli_error($connection));
        }
        
        mysqli_stmt_bind_param($check_stmt, "isssssss", 
            $facility['id'],
            $day,
            $end_time, $start_time,
            $end_time, $start_time,
            $start_time, $end_time
        );
        
        if (!mysqli_stmt_execute($check_stmt)) {
            throw new Exception("Error executing check query: " . mysqli_stmt_error($check_stmt));
        }
        
        $result = mysqli_stmt_get_result($check_stmt);
        
        // If no overlapping schedules found, facility is available
        if (mysqli_num_rows($result) === 0) {
            $available_facilities[] = $facility;
        }
    }

    sendJsonResponse(true, 'Available facilities retrieved successfully', $available_facilities);

} catch (Exception $e) {
    sendJsonResponse(false, $e->getMessage());
} catch (Error $e) {
    sendJsonResponse(false, 'PHP Error: ' . $e->getMessage());
}
?> 