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
        'groups' => $data
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

    // Validate required fields
    if (!$schedule || !$academic_year_id || !$semester) {
        sendJsonResponse(false, 'Missing required parameters');
    }

    // Get all groups
    $groups_query = "SELECT sg.*, i.year, i.month, p.name as program_name 
                    FROM student_group sg 
                    JOIN intake i ON sg.intake_id = i.id 
                    JOIN program p ON i.program_id = p.id 
                    ORDER BY i.year DESC, i.month DESC";
    $groups_result = mysqli_query($connection, $groups_query);
    
    if (!$groups_result) {
        throw new Exception("Error fetching groups: " . mysqli_error($connection));
    }
    
    // Get all existing schedules for the academic year and semester
    $existing_schedules_query = "SELECT t.id, tg.group_id, m.code as module_code, 
                               ts.day, ts.start_time, ts.end_time
                               FROM timetable t 
                               JOIN timetable_sessions ts ON t.id = ts.timetable_id
                               JOIN module m ON t.module_id = m.id 
                               JOIN timetable_groups tg ON t.id = tg.timetable_id
                               WHERE t.academic_year_id = ? AND t.semester = ?";
    
    $stmt = mysqli_prepare($connection, $existing_schedules_query);
    if (!$stmt) {
        throw new Exception("Error preparing existing schedules query: " . mysqli_error($connection));
    }
    
    mysqli_stmt_bind_param($stmt, "ii", $academic_year_id, $semester);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Error executing existing schedules query: " . mysqli_stmt_error($stmt));
    }
    
    $existing_schedules_result = mysqli_stmt_get_result($stmt);
    $existing_schedules = [];
    
    while ($row = mysqli_fetch_assoc($existing_schedules_result)) {
        $existing_schedules[] = $row;
    }
    
    // Process groups and check for conflicts
    $groups = [];
    while ($group = mysqli_fetch_assoc($groups_result)) {
        $has_conflict = false;
        
        // Check each session in the new schedule
        foreach ($schedule as $new_session) {
            // Check against existing schedules
            foreach ($existing_schedules as $existing) {
                if ($existing['group_id'] == $group['id'] && 
                    $existing['day'] == $new_session['day']) {
                    
                    // Convert times to comparable format
                    $new_start = strtotime($new_session['start_time']);
                    $new_end = strtotime($new_session['end_time']);
                    $existing_start = strtotime($existing['start_time']);
                    $existing_end = strtotime($existing['end_time']);
                    
                    // Check for time overlap
                    if (($new_start >= $existing_start && $new_start < $existing_end) ||
                        ($new_end > $existing_start && $new_end <= $existing_end) ||
                        ($new_start <= $existing_start && $new_end >= $existing_end)) {
                        $has_conflict = true;
                        break 2; // Break both loops if conflict found
                    }
                }
            }
        }
        
        if (!$has_conflict) {
            $groups[] = [
                'id' => $group['id'],
                'name' => $group['name'],
                'size' => $group['size'],
                'program_name' => $group['program_name'],
                'year' => $group['year'],
                'month' => $group['month']
            ];
        }
    }
    
    sendJsonResponse(true, 'Groups loaded successfully', $groups);
    
} catch (Exception $e) {
    sendJsonResponse(false, $e->getMessage());
}
?> 