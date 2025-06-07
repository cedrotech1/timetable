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
    $schedule = isset($data['schedule']) ? $data['schedule'] : null;
    $academic_year_id = isset($data['academic_year_id']) ? intval($data['academic_year_id']) : null;
    $semester = isset($data['semester']) ? intval($data['semester']) : null;
    $group_ids = isset($data['group_ids']) ? $data['group_ids'] : [];

    // Validate required fields
    if (!$schedule || !$academic_year_id || !$semester || empty($group_ids)) {
        sendJsonResponse(false, 'Missing required parameters');
    }

    // Calculate total group size
    $total_students = 0;
    $group_query = "SELECT size FROM student_group WHERE id IN (" . implode(',', array_fill(0, count($group_ids), '?')) . ")";
    $stmt = mysqli_prepare($connection, $group_query);
    mysqli_stmt_bind_param($stmt, str_repeat('i', count($group_ids)), ...$group_ids);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Error calculating group sizes: " . mysqli_stmt_error($stmt));
    }
    
    $group_result = mysqli_stmt_get_result($stmt);
    while ($group = mysqli_fetch_assoc($group_result)) {
        $total_students += $group['size'];
    }

    // Get all facilities with sufficient capacity and check availability
    $facilities_query = "SELECT DISTINCT f.*, c.name as campus_name 
                        FROM facility f 
                        LEFT JOIN campus c ON f.campus_id = c.id
                        WHERE f.capacity >= ?
                        AND f.id NOT IN (
                            SELECT DISTINCT t.facility_id
                            FROM timetable t
                            JOIN timetable_sessions ts ON t.id = ts.timetable_id
                            WHERE t.academic_year_id = ? 
                            AND t.semester = ?
                            AND (
                                (ts.day = ? AND (
                                    (ts.start_time <= ? AND ts.end_time > ?) OR
                                    (ts.start_time < ? AND ts.end_time >= ?) OR
                                    (ts.start_time >= ? AND ts.end_time <= ?)
                                ))
                            )
                        )";
    
    $stmt = mysqli_prepare($connection, $facilities_query);
    if (!$stmt) {
        throw new Exception("Error preparing facilities query: " . mysqli_error($connection));
    }
    
    // Bind parameters for each session in the schedule
    $facilities = [];
    foreach ($schedule as $session) {
        mysqli_stmt_bind_param($stmt, "iiisssssss", 
            $total_students,
            $academic_year_id,
            $semester,
            $session['day'],
            $session['end_time'], $session['start_time'],
            $session['end_time'], $session['start_time'],
            $session['start_time'], $session['end_time']
        );
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error executing facilities query: " . mysqli_stmt_error($stmt));
        }
        
        $result = mysqli_stmt_get_result($stmt);
        while ($facility = mysqli_fetch_assoc($result)) {
            // Only add facility if it's not already in the array
            if (!isset($facilities[$facility['id']])) {
                $facility['total_students'] = $total_students;
                $facility['available_capacity'] = $facility['capacity'] - $total_students;
                $facilities[$facility['id']] = $facility;
            }
        }
    }
    
    // Convert associative array to indexed array
    $facilities = array_values($facilities);
    
    sendJsonResponse(true, 'Available facilities retrieved successfully', $facilities);
    
} catch (Exception $e) {
    sendJsonResponse(false, $e->getMessage());
} catch (Error $e) {
    sendJsonResponse(false, 'PHP Error: ' . $e->getMessage());
}
?> 