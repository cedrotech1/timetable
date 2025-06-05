<?php
// Enable error logging but prevent display
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

include('connection.php');

header('Content-Type: application/json');

try {
    $query = "SELECT t.*, 
                     m.code as module_code, m.name as module_name,
                     u.names as lecturer_name,
                     f.name as facility_name, f.type as facility_type,
                     GROUP_CONCAT(sg.name) as groups
              FROM timetable t
              JOIN module m ON t.module_id = m.id
              JOIN users u ON t.lecturer_id = u.id
              JOIN facility f ON t.facility_id = f.id
              LEFT JOIN timetable_groups tg ON t.id = tg.timetable_id
              LEFT JOIN student_group sg ON tg.group_id = sg.id
              GROUP BY t.id
              ORDER BY m.code";

    $stmt = mysqli_prepare($connection, $query);
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . mysqli_error($connection));
    }
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Error fetching schedule: " . mysqli_error($connection));
    }

    $result = mysqli_stmt_get_result($stmt);
    if (!$result) {
        throw new Exception("Failed to get result: " . mysqli_error($connection));
    }

    $schedule = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $row['groups'] = $row['groups'] ? explode(',', $row['groups']) : [];
        $schedule[] = $row;
    }

    echo json_encode($schedule);

} catch (Exception $e) {
    error_log("Schedule Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

mysqli_close($connection);
?> 