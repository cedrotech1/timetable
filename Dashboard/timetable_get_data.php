<?php
include('connection.php');
header('Content-Type: application/json');

$campus_id = isset($_GET['campus']) ? (int)$_GET['campus'] : 0;
$college_id = isset($_GET['college']) ? (int)$_GET['college'] : 0;
$school_id = isset($_GET['school']) ? (int)$_GET['school'] : 0;
$department_id = isset($_GET['department']) ? (int)$_GET['department'] : 0;
$program_id = isset($_GET['program']) ? (int)$_GET['program'] : 0;
$intake_id = isset($_GET['intake']) ? (int)$_GET['intake'] : 0;
$group_id = isset($_GET['group']) ? (int)$_GET['group'] : 0;
$year_id = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$semester = isset($_GET['semester']) ? $_GET['semester'] : '';

$query = "
    SELECT 
        ts.day,
        TIME_FORMAT(ts.start_time, '%H:%i') as start_time,
        TIME_FORMAT(ts.end_time, '%H:%i') as end_time,
        m.code as module_code,
        m.name as module_name,
        u.names as lecturer_name,
        f.name as facility_name,
        d.name as department_name,
        p.name as program_name,
        i.year as intake_year,
        i.month as intake_month,
        sg.name as group_name,
        c.name as campus_name,
        cl.name as college_name,
        s.name as school_name,
        ay.year_label as academic_year,
        t.semester
    FROM timetable_sessions ts
    JOIN timetable t ON ts.timetable_id = t.id
    JOIN module m ON t.module_id = m.id
    JOIN users u ON t.lecturer_id = u.id
    JOIN facility f ON t.facility_id = f.id
    JOIN program p ON m.program_id = p.id
    JOIN department d ON p.department_id = d.id
    JOIN school s ON d.school_id = s.id
    JOIN college cl ON s.college_id = cl.id
    JOIN campus c ON cl.campus_id = c.id
    JOIN timetable_groups tg ON t.id = tg.timetable_id
    JOIN student_group sg ON tg.group_id = sg.id
    JOIN intake i ON sg.intake_id = i.id
    JOIN academic_year ay ON t.academic_year_id = ay.id
    WHERE 1=1
";

if ($campus_id) $query .= " AND c.id = $campus_id";
if ($college_id) $query .= " AND cl.id = $college_id";
if ($school_id) $query .= " AND s.id = $school_id";
if ($department_id) $query .= " AND d.id = $department_id";
if ($program_id) $query .= " AND p.id = $program_id";
if ($intake_id) $query .= " AND i.id = $intake_id";
if ($group_id) $query .= " AND sg.id = $group_id";
if ($year_id) $query .= " AND t.academic_year_id = $year_id";
if ($semester) $query .= " AND t.semester = '" . mysqli_real_escape_string($connection, $semester) . "'";

$query .= " ORDER BY ts.day, ts.start_time";

$result = mysqli_query($connection, $query);
$sessions = [];
while ($row = mysqli_fetch_assoc($result)) {
    $sessions[] = $row;
}
echo json_encode(['success' => true, 'data' => $sessions]); 