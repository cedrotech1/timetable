<?php
session_start();
header('Content-Type: application/json');
include('connection.php');

// Get timetable_id from GET/POST
$timetable_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get current academic year & semester
$system_result = mysqli_query($connection, "SELECT * FROM system LIMIT 1");
if (!$system_result || mysqli_num_rows($system_result) == 0) {
    echo json_encode(['success' => false, 'message' => 'Academic year or semester not found']);
    exit;
}
$system_data = mysqli_fetch_assoc($system_result);
$academic_year_id = $system_data['accademic_year_id'];
$semester = $system_data['semester'];

$query = "
SELECT 
    t.id AS timetable_id,
    t.status,
    m.name AS course,
    m.code,
    m.credits,

    -- Leader lecturer
    l.id AS leader_lecturer_id,
    l.names AS leader_names,
    l.email AS leader_email,
    l.phone AS leader_phone,
    l.role AS leader_role,

    -- Created by
    c.id AS created_by_id,
    c.names AS created_by_names,
    c.email AS created_by_email,
    c.phone AS created_by_phone,
    c.role AS created_by_role,

    -- Approved by
    a.id AS approved_by_id,
    a.names AS approved_by_names,
    a.email AS approved_by_email,
    a.phone AS approved_by_phone,
    a.role AS approved_by_role,

    -- Other lecturers
    tl.lect_id AS other_lect_id,
    ol.names AS other_lecturer_name,
    ol.email AS other_lecturer_email,
    ol.phone AS other_lecturer_phone,
    ol.role AS other_lecturer_role,

    -- Facility
    f.id AS facility_id,
    f.name AS facility_name,
    f.type AS facility_type,
    f.capacity AS facility_capacity,
    st.id AS site_id,
    st.name AS site_name,
    camp.id AS campus_id,
    camp.name AS campus_name,

    -- Sessions
    ts.id AS session_id,
    ts.day,
    ts.start_time,
    ts.end_time,

    -- Groups with full hierarchy
    sg.id AS group_id,
    sg.name AS group_name,
    sg.size AS group_size,

    i.id AS intake_id,
    i.year AS intake_year,
    i.month AS intake_month,
    i.year_of_study AS intake_year_of_study,

    p.id AS program_id,
    p.name AS program_name,
    p.code AS program_code,

    -- Department (can be null if program comes directly from school)
    d.id AS department_id,
    d.name AS department_name,

    -- School (can come from department or directly from program)
    COALESCE(d.school_id, p.school_id) AS school_id,
    COALESCE(s_department.name, s_program.name) AS school_name,

    -- Campus from intake (direct relationship)
    camp_intake.id AS intake_campus_id,
    camp_intake.name AS intake_campus_name,

    -- College (can come from school hierarchy)
    COALESCE(s_department.college_id, s_program.college_id) AS college_id,
    COALESCE(col_department.name, col_program.name) AS college_name

FROM timetable t
LEFT JOIN module m ON t.module_id = m.id

LEFT JOIN users l ON t.leader_lecturer_id = l.id
LEFT JOIN users c ON t.createdby = c.id
LEFT JOIN users a ON t.approvedby = a.id

LEFT JOIN timetable_lecturers tl ON t.id = tl.timetable_id
LEFT JOIN users ol ON tl.lect_id = ol.id

LEFT JOIN facility f ON t.facility_id = f.id
LEFT JOIN site st ON f.site = st.id
LEFT JOIN campus camp ON st.campus = camp.id

LEFT JOIN timetable_sessions ts ON t.id = ts.timetable_id
LEFT JOIN timetable_groups tg ON t.id = tg.timetable_id
LEFT JOIN student_group sg ON tg.group_id = sg.id
LEFT JOIN intake i ON sg.intake_id = i.id
LEFT JOIN program p ON i.program_id = p.id

-- Left join for department path (if program comes from department)
LEFT JOIN department d ON p.department_id = d.id
LEFT JOIN school s_department ON d.school_id = s_department.id
LEFT JOIN college col_department ON s_department.college_id = col_department.id

-- Left join for school path (if program comes directly from school)
LEFT JOIN school s_program ON p.school_id = s_program.id
LEFT JOIN college col_program ON s_program.college_id = col_program.id

-- Join with campus through intake
LEFT JOIN campus camp_intake ON i.campus_id = camp_intake.id

WHERE t.semester = '$semester' 
  AND t.academic_year_id = '$academic_year_id'
  " . ($timetable_id > 0 ? " AND t.id = '$timetable_id'" : "") . "
ORDER BY t.id, ts.day, ts.start_time
";

$result = mysqli_query($connection, $query);
$data = [];

while ($row = mysqli_fetch_assoc($result)) {
    $tid = $row['timetable_id'];

    if (!isset($data[$tid])) {
        $data[$tid] = [
            'id' => $row['timetable_id'],
            'course' => $row['course'],
            'code' => $row['code'],
            'credits' => $row['credits'],
            'status' => $row['status'],
            'leader_lecturer' => $row['leader_lecturer_id'] ? [
                'id' => $row['leader_lecturer_id'],
                'names' => $row['leader_names'],
                'email' => $row['leader_email'],
                'phone' => $row['leader_phone'],
                'role' => $row['leader_role']
            ] : [],
            'created_by' => $row['created_by_id'] ? [
                'id' => $row['created_by_id'],
                'names' => $row['created_by_names'],
                'email' => $row['created_by_email'],
                'phone' => $row['created_by_phone'],
                'role' => $row['created_by_role']
            ] : [],
            'approved_by' => $row['approved_by_id'] ? [
                'id' => $row['approved_by_id'],
                'names' => $row['approved_by_names'],
                'email' => $row['approved_by_email'],
                'phone' => $row['approved_by_phone'],
                'role' => $row['approved_by_role']
            ] : [],
            'other_lecturers' => [],
            'facility' => [
                'id' => $row['facility_id'],
                'name' => $row['facility_name'],
                'type' => $row['facility_type'],
                'capacity' => $row['facility_capacity'],
                'site' => [
                    'id' => $row['site_id'],
                    'name' => $row['site_name'],
                    'campus' => [
                        'id' => $row['campus_id'],
                        'name' => $row['campus_name']
                    ]
                ]
            ],
            'sessions' => [],
            'groups' => []
        ];
    }

    // Sessions
    if ($row['day']) {
        $session_key = $row['day'] . '_' . $row['start_time'] . '_' . $row['end_time'];
        $existing_session_keys = array_map(function ($s) {
            return $s['day'] . '_' . $s['start'] . '_' . $s['end'];
        }, $data[$tid]['sessions']);

        if (!in_array($session_key, $existing_session_keys)) {
            $data[$tid]['sessions'][] = [
                'id' => $row['session_id'],
                'day' => $row['day'],
                'start' => $row['start_time'],
                'end' => $row['end_time']
            ];
        }
    }

    // Other lecturers
    if ($row['other_lect_id'] && !in_array($row['other_lect_id'], array_column($data[$tid]['other_lecturers'], 'id'))) {
        $data[$tid]['other_lecturers'][] = [
            'id' => $row['other_lect_id'],
            'names' => $row['other_lecturer_name'] ?? '',
            'email' => $row['other_lecturer_email'] ?? '',
            'phone' => $row['other_lecturer_phone'] ?? '',
            'role' => $row['other_lecturer_role'] ?? ''
        ];
    }

    // Groups
    $group_key = $row['group_id'];
    if ($row['group_id'] && !isset($data[$tid]['groups'][$group_key])) {
        $group_data = [
            'id' => $row['group_id'],
            'name' => $row['group_name'],
            'size' => $row['group_size'],
            'intake' => [
                'id' => $row['intake_id'],
                'year' => $row['intake_year'],
                'month' => $row['intake_month'],
                'year_of_study' => $row['intake_year_of_study'] ?? 1, // Default to 1 if not set
                'campus' => [
                    'id' => $row['intake_campus_id'],
                    'name' => $row['intake_campus_name']
                ]
            ],
            'program' => [
                'id' => $row['program_id'],
                'name' => $row['program_name'],
                'code' => $row['program_code']
            ]
        ];

        // Add department only if it exists
        if ($row['department_id']) {
            $group_data['department'] = [
                'id' => $row['department_id'],
                'name' => $row['department_name']
            ];
        }

        // Add school, college
        $group_data['school'] = [
            'id' => $row['school_id'],
            'name' => $row['school_name']
        ];
        $group_data['college'] = [
            'id' => $row['college_id'],
            'name' => $row['college_name']
        ];

        $data[$tid]['groups'][$group_key] = $group_data;
    }
}

// Re-index groups
foreach ($data as &$dt) {
    $dt['groups'] = array_values($dt['groups']);
}

echo json_encode(['success' => true, 'data' => array_values($data)]);
?>