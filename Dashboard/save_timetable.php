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
        'data' => $data
    ]);
    exit;
}

try {
    include('connection.php');

    // Check if request is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJsonResponse(false, 'Invalid request method');
    }

    // Get and validate required fields
    $facility_id = isset($_POST['facility_id']) ? intval($_POST['facility_id']) : null;
    $module_id = isset($_POST['module_id']) ? intval($_POST['module_id']) : null;
    $lecturer_id = isset($_POST['lecturer_id']) ? intval($_POST['lecturer_id']) : null;
    $academic_year_id = isset($_POST['academic_year_id']) ? intval($_POST['academic_year_id']) : null;
    $semester = isset($_POST['semester']) ? intval($_POST['semester']) : null;
    $group_ids = isset($_POST['group_ids']) ? $_POST['group_ids'] : [];
    $schedule = isset($_POST['schedule']) ? json_decode($_POST['schedule'], true) : null;

    // Validate required fields
    if (!$facility_id || !$module_id || !$lecturer_id || !$academic_year_id || !$semester || empty($group_ids) || !$schedule) {
        sendJsonResponse(false, 'Missing required fields');
    }

    // Get facility capacity
    $facility_query = "SELECT capacity FROM facility WHERE id = ?";
    $stmt = mysqli_prepare($connection, $facility_query);
    mysqli_stmt_bind_param($stmt, "i", $facility_id);
    mysqli_stmt_execute($stmt);
    $facility_result = mysqli_stmt_get_result($stmt);
    $facility = mysqli_fetch_assoc($facility_result);

    if (!$facility) {
        sendJsonResponse(false, 'Selected facility not found');
    }

    // Calculate total group size
    $total_students = 0;
    $group_query = "SELECT size FROM student_group WHERE id IN (" . implode(',', array_fill(0, count($group_ids), '?')) . ")";
    $stmt = mysqli_prepare($connection, $group_query);
    mysqli_stmt_bind_param($stmt, str_repeat('i', count($group_ids)), ...$group_ids);
    mysqli_stmt_execute($stmt);
    $group_result = mysqli_stmt_get_result($stmt);

    while ($group = mysqli_fetch_assoc($group_result)) {
        $total_students += $group['size'];
    }

    // Check if facility has sufficient capacity
    if ($total_students > $facility['capacity']) {
        sendJsonResponse(false, "Selected facility capacity ({$facility['capacity']}) is insufficient for total group size ($total_students)");
    }

    // Get existing schedules for the academic year and semester
    $existing_schedules_query = "SELECT t.id, t.facility_id, tg.group_id, m.code as module_code, 
                               ts.day, ts.start_time, ts.end_time
                               FROM timetable t 
                               JOIN timetable_sessions ts ON t.id = ts.timetable_id
                               JOIN module m ON t.module_id = m.id 
                               LEFT JOIN timetable_groups tg ON t.id = tg.timetable_id
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

    // Check for facility conflicts
    foreach ($existing_schedules as $existing) {
        if ($existing['facility_id'] == $facility_id) {
            foreach ($schedule as $new_session) {
                if ($existing['day'] == $new_session['day']) {
                    // Check for time overlap
                    if (
                        ($existing['start_time'] <= $new_session['end_time'] && $existing['end_time'] > $new_session['start_time']) ||
                        ($existing['start_time'] < $new_session['end_time'] && $existing['end_time'] >= $new_session['start_time']) ||
                        ($existing['start_time'] >= $new_session['start_time'] && $existing['end_time'] <= $new_session['end_time'])
                    ) {
                        throw new Exception("Facility is already booked on {$existing['day']} from {$existing['start_time']} to {$existing['end_time']} for module {$existing['module_code']}");
                    }
                }
            }
        }
    }

    // Check for group conflicts
    foreach ($group_ids as $group_id) {
        foreach ($existing_schedules as $existing) {
            if ($existing['group_id'] == $group_id) {
                foreach ($schedule as $new_session) {
                    if ($existing['day'] == $new_session['day']) {
                        // Check for time overlap
                        if (
                            ($existing['start_time'] <= $new_session['end_time'] && $existing['end_time'] > $new_session['start_time']) ||
                            ($existing['start_time'] < $new_session['end_time'] && $existing['end_time'] >= $new_session['start_time']) ||
                            ($existing['start_time'] >= $new_session['start_time'] && $existing['end_time'] <= $new_session['end_time'])
                        ) {
                            throw new Exception("Group is already scheduled on {$existing['day']} from {$existing['start_time']} to {$existing['end_time']} for module {$existing['module_code']}");
                        }
                    }
                }
            }
        }
    }

    // Start transaction
    mysqli_begin_transaction($connection);

    try {
        // Insert into timetable
        $timetable_query = "INSERT INTO timetable (facility_id, module_id, lecturer_id, academic_year_id, semester) 
                           VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($connection, $timetable_query);
        mysqli_stmt_bind_param($stmt, "iiiii", $facility_id, $module_id, $lecturer_id, $academic_year_id, $semester);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error saving timetable: " . mysqli_stmt_error($stmt));
        }
        
        $timetable_id = mysqli_insert_id($connection);

        // Insert sessions
        $session_query = "INSERT INTO timetable_sessions (timetable_id, day, start_time, end_time) VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($connection, $session_query);
        
        foreach ($schedule as $session) {
            mysqli_stmt_bind_param($stmt, "isss", $timetable_id, $session['day'], $session['start_time'], $session['end_time']);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error saving session: " . mysqli_stmt_error($stmt));
            }
        }

        // Insert group assignments
        $group_query = "INSERT INTO timetable_groups (timetable_id, group_id) VALUES (?, ?)";
        $stmt = mysqli_prepare($connection, $group_query);
        
        foreach ($group_ids as $group_id) {
            mysqli_stmt_bind_param($stmt, "ii", $timetable_id, $group_id);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error assigning group: " . mysqli_stmt_error($stmt));
            }
        }

        // Commit transaction
        mysqli_commit($connection);
        sendJsonResponse(true, 'Timetable saved successfully');

    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($connection);
        throw $e;
    }

} catch (Exception $e) {
    sendJsonResponse(false, $e->getMessage());
} catch (Error $e) {
    sendJsonResponse(false, 'PHP Error: ' . $e->getMessage());
}
?> 