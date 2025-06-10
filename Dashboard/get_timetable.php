<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../connection.php');

// Get filter parameters
$campus_id = isset($_GET['campus_id']) ? intval($_GET['campus_id']) : null;
$college_id = isset($_GET['college_id']) ? intval($_GET['college_id']) : null;
$school_id = isset($_GET['school_id']) ? intval($_GET['school_id']) : null;
$department_id = isset($_GET['department_id']) ? intval($_GET['department_id']) : null;
$program_id = isset($_GET['program_id']) ? intval($_GET['program_id']) : null;
$intake_id = isset($_GET['intake_id']) ? intval($_GET['intake_id']) : null;
$group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : null;
$module_id = isset($_GET['module_id']) ? intval($_GET['module_id']) : null;
$lecturer_id = isset($_GET['lecturer_id']) ? intval($_GET['lecturer_id']) : null;
$facility_id = isset($_GET['facility_id']) ? intval($_GET['facility_id']) : null;
$academic_year_id = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : null;
$semester = isset($_GET['semester']) ? $_GET['semester'] : null;

// Build the base query
$query = "
    SELECT 
        ts.id as session_id,
        ts.day,
        ts.start_time,
        ts.end_time,
        t.id as timetable_id,
        t.semester,
        t.academic_year_id,
        m.id as module_id,
        m.name as module_name,
        m.code as module_code,
        m.credits as module_credit,
        u.id as lecturer_id,
        u.names as lecturer_name,
        u.phone as lecturer_phone,
        u.email as lecturer_email,
        f.id as facility_id,
        f.name as facility_name,
        f.location as facility_location,
        f.type as facility_type,
        f.capacity as facility_capacity,
        c.id as campus_id,
        c.name as campus_name,
        col.id as college_id,
        col.name as college_name,
        s.id as school_id,
        s.name as school_name,
        d.id as department_id,
        d.name as department_name,
        p.id as program_id,
        p.name as program_name,
        p.code as program_code,
        i.id as intake_id,
        i.year as intake_year,
        i.month as intake_month,
        sg.id as group_id,
        sg.name as group_name,
        sg.size as group_size,
        ay.year_label as academic_year
    FROM timetable_sessions ts
    JOIN timetable t ON ts.timetable_id = t.id
    JOIN module m ON t.module_id = m.id
    JOIN users u ON t.lecturer_id = u.id AND u.role = 'lecturer'
    JOIN facility f ON t.facility_id = f.id
    JOIN campus c ON f.campus_id = c.id
    JOIN timetable_groups tg ON t.id = tg.timetable_id
    JOIN student_group sg ON tg.group_id = sg.id
    JOIN intake i ON sg.intake_id = i.id
    JOIN program p ON i.program_id = p.id
    JOIN department d ON p.department_id = d.id
    JOIN school s ON d.school_id = s.id
    JOIN college col ON s.college_id = col.id
    JOIN academic_year ay ON t.academic_year_id = ay.id
    WHERE 1=1
";

// Add filters
$params = [];
if ($campus_id) {
    $query .= " AND c.id = ?";
    $params[] = $campus_id;
}
if ($college_id) {
    $query .= " AND col.id = ?";
    $params[] = $college_id;
}
if ($school_id) {
    $query .= " AND s.id = ?";
    $params[] = $school_id;
}
if ($department_id) {
    $query .= " AND d.id = ?";
    $params[] = $department_id;
}
if ($program_id) {
    $query .= " AND p.id = ?";
    $params[] = $program_id;
}
if ($intake_id) {
    $query .= " AND i.id = ?";
    $params[] = $intake_id;
}
if ($group_id) {
    $query .= " AND sg.id = ?";
    $params[] = $group_id;
}
if ($module_id) {
    $query .= " AND m.id = ?";
    $params[] = $module_id;
}
if ($lecturer_id) {
    $query .= " AND u.id = ?";
    $params[] = $lecturer_id;
}
if ($facility_id) {
    $query .= " AND f.id = ?";
    $params[] = $facility_id;
}
if ($academic_year_id) {
    $query .= " AND t.academic_year_id = ?";
    $params[] = $academic_year_id;
}
if ($semester) {
    $query .= " AND t.semester = ?";
    $params[] = $semester;
}

$query .= " ORDER BY ts.day, ts.start_time, ts.end_time";

// Prepare and execute the query
$stmt = mysqli_prepare($connection, $query);
if (!empty($params)) {
    $types = str_repeat('i', count($params));
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Organize the data
$timetable = [];
while ($row = mysqli_fetch_assoc($result)) {
    $session_key = $row['session_id'];
    
    if (!isset($timetable[$session_key])) {
        $timetable[$session_key] = [
            'session' => [
                'id' => $row['session_id'],
                'day' => $row['day'],
                'start_time' => $row['start_time'],
                'end_time' => $row['end_time']
            ],
            'timetable' => [
                'id' => $row['timetable_id'],
                'semester' => $row['semester'],
                'academic_year' => $row['academic_year'],
                'module' => [
                    'id' => $row['module_id'],
                    'name' => $row['module_name'],
                    'code' => $row['module_code'],
                    'credits'=> $row['module_credit'],
                ],
                'lecturer' => [
                    'id' => $row['lecturer_id'],
                    'name' => $row['lecturer_name'],
                    'phone'=>$row['lecturer_phone'],
                    'email'=> $row['lecturer_email'],
                ],
                'facility' => [
                    'id' => $row['facility_id'],
                    'name' => $row['facility_name'],
                    'location' => $row['facility_location'],
                    'type' => $row['facility_type'],
                    'capacity' => $row['facility_capacity']
                ],
                'groups' => []
            ]
        ];
    }
    
    // Add group information
    $timetable[$session_key]['timetable']['groups'][] = [
        'id' => $row['group_id'],
        'name' => $row['group_name'],
        'size' => $row['group_size'],
        'campus' => [
            'id' => $row['campus_id'],
            'name' => $row['campus_name']
        ],
        'college' => [
            'id' => $row['college_id'],
            'name' => $row['college_name']
        ],
        'school' => [
            'id' => $row['school_id'],
            'name' => $row['school_name']
        ],
        'department' => [
            'id' => $row['department_id'],
            'name' => $row['department_name']
        ],
        'program' => [
            'id' => $row['program_id'],
            'name' => $row['program_name'],
            'code' => $row['program_code']
        ],
        'intake' => [
            'id' => $row['intake_id'],
            'year' => $row['intake_year'],
            'month' => $row['intake_month']
        ]
    ];
}

// Return the response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'data' => array_values($timetable)
]);
?> 