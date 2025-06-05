<?php
// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

include('connection.php');

$academic_year = isset($_GET['academic_year']) ? $_GET['academic_year'] : null;
$semester = isset($_GET['semester']) ? $_GET['semester'] : null;

if (!$academic_year || !$semester) {
    http_response_code(400);
    echo json_encode(['error' => 'Academic year and semester are required']);
    exit;
}

try {
    // First, verify the academic year exists
    $check_year = "SELECT id FROM academic_year WHERE id = ?";
    $stmt = mysqli_prepare($connection, $check_year);
    if (!$stmt) {
        throw new Exception("Year check preparation failed: " . mysqli_error($connection));
    }
    mysqli_stmt_bind_param($stmt, "i", $academic_year);
    mysqli_stmt_execute($stmt);
    $year_result = mysqli_stmt_get_result($stmt);
    if (mysqli_num_rows($year_result) === 0) {
        throw new Exception("Invalid academic year ID");
    }

    // Get timetable entries with related information
    $query = "SELECT 
                t.id,
                t.module_id,
                t.lecturer_id,
                t.facility_id,
                t.semester,
                t.academic_year_id,
                m.name as module_name,
                u.names as lecturer_name,
                f.name as facility_name,
                GROUP_CONCAT(DISTINCT sg.name) as group_names
              FROM timetable t
              LEFT JOIN module m ON t.module_id = m.id
              LEFT JOIN users u ON t.lecturer_id = u.id
              LEFT JOIN facility f ON t.facility_id = f.id
              LEFT JOIN timetable_groups tg ON t.id = tg.timetable_id
              LEFT JOIN student_group sg ON tg.group_id = sg.id
              WHERE t.academic_year_id = ? 
              AND t.semester = ?
              GROUP BY t.id, t.module_id, t.lecturer_id, t.facility_id, t.semester, t.academic_year_id, m.name, u.names, f.name
              ORDER BY t.id";

    $stmt = mysqli_prepare($connection, $query);
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . mysqli_error($connection));
    }

    mysqli_stmt_bind_param($stmt, "is", $academic_year, $semester);
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Query execution failed: " . mysqli_stmt_error($stmt));
    }

    $result = mysqli_stmt_get_result($stmt);
    if (!$result) {
        throw new Exception("Failed to get result: " . mysqli_error($connection));
    }

    $schedule = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $schedule[] = [
            'id' => $row['id'],
            'module_name' => $row['module_name'] ?? 'No Module',
            'lecturer_name' => $row['lecturer_name'] ?? 'Not Assigned',
            'facility_name' => $row['facility_name'] ?? 'Not Assigned',
            'groups' => $row['group_names'] ? explode(',', $row['group_names']) : []
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($schedule);

} catch (Exception $e) {
    error_log("Timetable Error: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
} 