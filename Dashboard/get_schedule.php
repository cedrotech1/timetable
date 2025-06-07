<?php
// Enable error logging but prevent display
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

include('connection.php');

// Get academic year and semester from request
$academic_year = isset($_GET['academic_year']) ? $_GET['academic_year'] : null;
$semester = isset($_GET['semester']) ? $_GET['semester'] : null;

// Base query
$query = "SELECT 
    t.id,
    t.academic_year_id,
    t.semester,
    m.code as module_code,
    m.name as module_name,
    m.program_id,
    p.name as program_name,
    u.names as lecturer_name,
    f.name as facility_name,
    f.type as facility_type,
    f.location as facility_location,
    f.capacity as facility_capacity,
    ay.year_label as academic_year_label,
    GROUP_CONCAT(DISTINCT sg.name) as group_names,
    GROUP_CONCAT(DISTINCT CONCAT(ts.day, ':', ts.start_time, ':', ts.end_time)) as session_times
FROM timetable t
JOIN module m ON t.module_id = m.id
JOIN program p ON m.program_id = p.id
JOIN users u ON t.lecturer_id = u.id
JOIN facility f ON t.facility_id = f.id
JOIN academic_year ay ON t.academic_year_id = ay.id
JOIN timetable_groups tg ON t.id = tg.timetable_id
JOIN student_group sg ON tg.group_id = sg.id
JOIN timetable_sessions ts ON t.id = ts.timetable_id";

// Add WHERE clause if filters are provided
$params = [];
$types = "";
if ($academic_year) {
    $query .= " WHERE t.academic_year_id = ?";
    $params[] = $academic_year;
    $types .= "i";
}
if ($semester) {
    $query .= ($academic_year ? " AND" : " WHERE") . " t.semester = ?";
    $params[] = $semester;
    $types .= "i";
}

$query .= " GROUP BY t.id, t.academic_year_id, t.semester, m.code, m.name, m.program_id, p.name, u.names, f.name, f.type, f.location, f.capacity, ay.year_label ORDER BY ay.year_label DESC, t.semester, m.code, ts.day, ts.start_time";

try {
    $stmt = mysqli_prepare($connection, $query);
    if (!$stmt) {
        throw new Exception("Error preparing statement: " . mysqli_error($connection));
    }

    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Error executing statement: " . mysqli_stmt_error($stmt));
    }

    $result = mysqli_stmt_get_result($stmt);
    if (!$result) {
        throw new Exception("Error getting result: " . mysqli_error($connection));
    }

    $schedule = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // Parse session times
        $sessions = [];
        if ($row['session_times']) {
            $session_times = explode(',', $row['session_times']);
            foreach ($session_times as $session) {
                list($day, $start_time, $end_time) = explode(':', $session);
                $sessions[] = [
                    'day' => $day,
                    'start_time' => $start_time,
                    'end_time' => $end_time
                ];
            }
        }

        // Parse group names
        $groups = $row['group_names'] ? explode(',', $row['group_names']) : [];

        $schedule[] = [
            'id' => $row['id'],
            'module_code' => $row['module_code'],
            'module_name' => $row['module_name'],
            'program_name' => $row['program_name'],
            'lecturer_name' => $row['lecturer_name'],
            'facility_name' => $row['facility_name'],
            'facility_type' => $row['facility_type'],
            'facility_location' => $row['facility_location'],
            'facility_capacity' => $row['facility_capacity'],
            'academic_year_label' => $row['academic_year_label'],
            'semester' => $row['semester'],
            'groups' => $groups,
            'sessions' => $sessions
        ];
    }

    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($schedule);

} catch (Exception $e) {
    // Log the error
    error_log("Schedule Error: " . $e->getMessage());
    
    // Return empty array with error message
    header('Content-Type: application/json');
    echo json_encode([
        'error' => $e->getMessage(),
        'schedule' => []
    ]);
}

mysqli_close($connection);
?> 