<?php
session_start();
include('connection.php');

header('Content-Type: application/json');

// Get POST data
$timetable_id = $_POST['timetable_id'] ?? null;
$group_ids = $_POST['group_ids'] ?? [];

if (!$timetable_id || empty($group_ids)) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
    exit;
}

try {
    // Get timetable details including sessions and facility
    $timetable_query = "SELECT t.*, f.capacity as facility_capacity 
                       FROM timetable t 
                       JOIN facility f ON t.facility_id = f.id 
                       WHERE t.id = ?";
    $stmt = mysqli_prepare($connection, $timetable_query);
    mysqli_stmt_bind_param($stmt, "i", $timetable_id);
    mysqli_stmt_execute($stmt);
    $timetable_result = mysqli_stmt_get_result($stmt);
    $timetable = mysqli_fetch_assoc($timetable_result);

    if (!$timetable) {
        throw new Exception('Timetable not found');
    }

    // Get timetable sessions
    $sessions_query = "SELECT * FROM timetable_sessions WHERE timetable_id = ?";
    $stmt = mysqli_prepare($connection, $sessions_query);
    mysqli_stmt_bind_param($stmt, "i", $timetable_id);
    mysqli_stmt_execute($stmt);
    $sessions_result = mysqli_stmt_get_result($stmt);
    $sessions = [];
    while ($session = mysqli_fetch_assoc($sessions_result)) {
        $sessions[] = $session;
    }

    // Get total students in existing groups
    $existing_groups_query = "SELECT SUM(sg.size) as total_students 
                            FROM timetable_groups tg 
                            JOIN student_group sg ON tg.group_id = sg.id 
                            WHERE tg.timetable_id = ?";
    $stmt = mysqli_prepare($connection, $existing_groups_query);
    mysqli_stmt_bind_param($stmt, "i", $timetable_id);
    mysqli_stmt_execute($stmt);
    $existing_result = mysqli_stmt_get_result($stmt);
    $existing_data = mysqli_fetch_assoc($existing_result);
    $existing_students = $existing_data['total_students'] ?? 0;

    // Get total students in new groups
    $new_groups_query = "SELECT SUM(size) as total_students 
                        FROM student_group 
                        WHERE id IN (" . implode(',', array_fill(0, count($group_ids), '?')) . ")";
    $stmt = mysqli_prepare($connection, $new_groups_query);
    mysqli_stmt_bind_param($stmt, str_repeat('i', count($group_ids)), ...$group_ids);
    mysqli_stmt_execute($stmt);
    $new_result = mysqli_stmt_get_result($stmt);
    $new_data = mysqli_fetch_assoc($new_result);
    $new_students = $new_data['total_students'] ?? 0;

    // Check facility capacity
    if (($existing_students + $new_students) > $timetable['facility_capacity']) {
        echo json_encode([
            'success' => false,
            'message' => 'Facility capacity exceeded. Current: ' . $existing_students . 
                        ', New: ' . $new_students . 
                        ', Capacity: ' . $timetable['facility_capacity']
        ]);
        exit;
    }

    // Check for session conflicts
    $conflicts = [];
    foreach ($sessions as $session) {
        $conflict_query = "SELECT DISTINCT t.id, m.name as module_name, ts.day, ts.start_time, ts.end_time
                          FROM timetable t
                          JOIN timetable_sessions ts ON t.id = ts.timetable_id
                          JOIN timetable_groups tg ON t.id = tg.timetable_id
                          JOIN student_group sg ON tg.group_id = sg.id
                          JOIN module m ON t.module_id = m.id
                          WHERE sg.id IN (" . implode(',', array_fill(0, count($group_ids), '?')) . ")
                          AND ts.day = ?
                          AND ((ts.start_time <= ? AND ts.end_time > ?) 
                               OR (ts.start_time < ? AND ts.end_time >= ?)
                               OR (ts.start_time >= ? AND ts.end_time <= ?))";
        
        $params = array_merge($group_ids, [
            $session['day'],
            $session['start_time'], $session['start_time'],
            $session['end_time'], $session['end_time'],
            $session['start_time'], $session['end_time']
        ]);
        
        $stmt = mysqli_prepare($connection, $conflict_query);
        mysqli_stmt_bind_param($stmt, str_repeat('i', count($params)), ...$params);
        mysqli_stmt_execute($stmt);
        $conflict_result = mysqli_stmt_get_result($stmt);
        
        while ($conflict = mysqli_fetch_assoc($conflict_result)) {
            if ($conflict['id'] != $timetable_id) {
                $conflicts[] = [
                    'module' => $conflict['module_name'],
                    'day' => $conflict['day'],
                    'time' => $conflict['start_time'] . ' - ' . $conflict['end_time']
                ];
            }
        }
    }

    if (!empty($conflicts)) {
        echo json_encode([
            'success' => false,
            'message' => 'Session conflicts found',
            'conflicts' => $conflicts
        ]);
        exit;
    }

    // All validations passed
    echo json_encode([
        'success' => true,
        'message' => 'Groups can be added',
        'details' => [
            'existing_students' => $existing_students,
            'new_students' => $new_students,
            'total_students' => $existing_students + $new_students,
            'facility_capacity' => $timetable['facility_capacity']
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Validation error: ' . $e->getMessage()
    ]);
}
?> 