<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once 'connection.php';

try {
    // Get filter parameters
    $academic_year = isset($_GET['academic_year']) ? $_GET['academic_year'] : null;
    $semester = isset($_GET['semester']) ? $_GET['semester'] : null;
    $campus_id = isset($_GET['campus_id']) ? $_GET['campus_id'] : null;
    $college_id = isset($_GET['college_id']) ? $_GET['college_id'] : null;
    $school_id = isset($_GET['school_id']) ? $_GET['school_id'] : null;
    $department_id = isset($_GET['department_id']) ? $_GET['department_id'] : null;
    $program_id = isset($_GET['program_id']) ? $_GET['program_id'] : null;
    $intake_id = isset($_GET['intake_id']) ? $_GET['intake_id'] : null;
    $group_id = isset($_GET['group_id']) ? $_GET['group_id'] : null;

    // Build the base query
    $query = "
        SELECT DISTINCT
            t.id,
            t.semester,
            ay.id as academic_year_id,
            ay.year_label as academic_year,
            m.id as module_id,
            m.name as module_name,
            m.code as module_code,
            m.credits as module_credits,
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
            c.name as campus_name
        FROM timetable t
        LEFT JOIN academic_year ay ON t.academic_year_id = ay.id
        LEFT JOIN module m ON t.module_id = m.id
        LEFT JOIN users u ON t.lecturer_id = u.id
        LEFT JOIN facility f ON t.facility_id = f.id
        LEFT JOIN campus c ON f.campus_id = c.id
        LEFT JOIN timetable_groups tg ON t.id = tg.timetable_id
        LEFT JOIN student_group sg ON tg.group_id = sg.id
        LEFT JOIN intake i ON sg.intake_id = i.id
        LEFT JOIN program p ON i.program_id = p.id
        LEFT JOIN department d ON p.department_id = d.id
        LEFT JOIN school s ON d.school_id = s.id
        LEFT JOIN college col ON s.college_id = col.id
        WHERE 1=1
    ";

    // Add filter conditions
    if ($academic_year) {
        $query .= " AND ay.id = " . intval($academic_year);
    }
    if ($semester) {
        $query .= " AND t.semester = " . intval($semester);
    }
    if ($campus_id) {
        $query .= " AND c.id = " . intval($campus_id);
    }
    if ($college_id) {
        $query .= " AND col.id = " . intval($college_id);
    }
    if ($school_id) {
        $query .= " AND s.id = " . intval($school_id);
    }
    if ($department_id) {
        $query .= " AND d.id = " . intval($department_id);
    }
    if ($program_id) {
        $query .= " AND p.id = " . intval($program_id);
    }
    if ($intake_id) {
        $query .= " AND i.id = " . intval($intake_id);
    }
    if ($group_id) {
        $query .= " AND sg.id = " . intval($group_id);
    }

    $query .= " ORDER BY t.id DESC";

    $result = mysqli_query($connection, $query);
    $timetables = [];

    while ($row = mysqli_fetch_assoc($result)) {
        // Get sessions for this timetable
        $sessionsQuery = "
            SELECT id, day, start_time, end_time
            FROM timetable_sessions
            WHERE timetable_id = {$row['id']}
        ";
        $sessionsResult = mysqli_query($connection, $sessionsQuery);
        $sessions = [];
        
        while ($session = mysqli_fetch_assoc($sessionsResult)) {
            $sessions[] = [
                'id' => $session['id'],
                'day' => $session['day'],
                'start_time' => $session['start_time'],
                'end_time' => $session['end_time']
            ];
        }

        // Get groups for this timetable
        $groupsQuery = "
            SELECT 
                sg.id,
                sg.name,
                sg.size,
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
                i.month as intake_month
            FROM timetable_groups tg
            JOIN student_group sg ON tg.group_id = sg.id
            JOIN intake i ON sg.intake_id = i.id
            JOIN program p ON i.program_id = p.id
            JOIN department d ON p.department_id = d.id
            JOIN school s ON d.school_id = s.id
            JOIN college col ON s.college_id = col.id
            JOIN campus c ON col.campus_id = c.id
            WHERE tg.timetable_id = {$row['id']}
        ";

        $groupsResult = mysqli_query($connection, $groupsQuery);
        $groups = [];

        while ($group = mysqli_fetch_assoc($groupsResult)) {
            $groups[] = [
                'id' => $group['id'],
                'name' => $group['name'],
                'size' => $group['size'],
                'campus' => [
                    'id' => $group['campus_id'],
                    'name' => $group['campus_name']
                ],
                'college' => [
                    'id' => $group['college_id'],
                    'name' => $group['college_name']
                ],
                'school' => [
                    'id' => $group['school_id'],
                    'name' => $group['school_name']
                ],
                'department' => [
                    'id' => $group['department_id'],
                    'name' => $group['department_name']
                ],
                'program' => [
                    'id' => $group['program_id'],
                    'name' => $group['program_name'],
                    'code' => $group['program_code']
                ],
                'intake' => [
                    'id' => $group['intake_id'],
                    'year' => $group['intake_year'],
                    'month' => $group['intake_month']
                ]
            ];
        }

        $timetables[] = [
            'id' => $row['id'],
            'semester' => $row['semester'],
            'academic_year' => $row['academic_year'],
            'module' => [
                'id' => $row['module_id'],
                'name' => $row['module_name'],
                'code' => $row['module_code'],
                'credits' => $row['module_credits']
            ],
            'lecturer' => [
                'id' => $row['lecturer_id'],
                'name' => $row['lecturer_name'],
                'phone' => $row['lecturer_phone'],
                'email' => $row['lecturer_email']
            ],
            'facility' => [
                'id' => $row['facility_id'],
                'name' => $row['facility_name'],
                'location' => $row['facility_location'],
                'type' => $row['facility_type'],
                'capacity' => $row['facility_capacity']
            ],
            'sessions' => $sessions,
            'groups' => $groups
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $timetables
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?> 