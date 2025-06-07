<?php
include('connection.php');

header('Content-Type: application/json');

try {
    // Start with timetable_sessions and join all related tables
    $query = "
        SELECT 
            ts.id as session_id,
            ts.day,
            ts.start_time,
            ts.end_time,
            
            -- Timetable info
            t.id as timetable_id,
            t.semester,
            ay.year_label as academic_year,
            
            -- Module info
            m.id as module_id,
            m.code as module_code,
            m.name as module_name,
            m.credits,
            m.year as module_year,
            
            -- Lecturer info
            u.id as lecturer_id,
            u.names as lecturer_name,
            
            -- Facility info
            f.id as facility_id,
            f.name as facility_name,
            f.location as facility_location,
            f.type as facility_type,
            
            -- Group info with its complete chain
            sg.id as group_id,
            sg.name as group_name,
            
            -- Group's Intake info
            i.id as intake_id,
            i.year as intake_year,
            i.month as intake_month,
            
            -- Group's Program info
            p.id as program_id,
            p.name as program_name,
            p.code as program_code,
            
            -- Group's Department info
            d.id as department_id,
            d.name as department_name,
            
            -- Group's School info
            s.id as school_id,
            s.name as school_name,
            
            -- Group's College info
            c.id as college_id,
            c.name as college_name,
            
            -- Group's Campus info
            cp.id as campus_id,
            cp.name as campus_name
            
        FROM timetable_sessions ts
        JOIN timetable t ON ts.timetable_id = t.id
        JOIN academic_year ay ON t.academic_year_id = ay.id
        JOIN module m ON t.module_id = m.id
        JOIN users u ON t.lecturer_id = u.id
        JOIN facility f ON t.facility_id = f.id
        JOIN timetable_groups tg ON t.id = tg.timetable_id
        JOIN student_group sg ON tg.group_id = sg.id
        JOIN intake i ON sg.intake_id = i.id
        JOIN program p ON i.program_id = p.id
        JOIN department d ON p.department_id = d.id
        JOIN school s ON d.school_id = s.id
        JOIN college c ON s.college_id = c.id
        JOIN campus cp ON c.campus_id = cp.id
        ORDER BY ts.day, ts.start_time, sg.name";

    $result = mysqli_query($connection, $query);
    
    if (!$result) {
        throw new Exception(mysqli_error($connection));
    }

    $sessions = [];
    $currentSession = null;

    while ($row = mysqli_fetch_assoc($result)) {
        // If this is a new session or we're starting fresh
        if ($currentSession === null || 
            $currentSession['session']['id'] !== $row['session_id']) {
            
            // If we have a previous session, add it to our sessions array
            if ($currentSession !== null) {
                $sessions[] = $currentSession;
            }

            // Create new session structure
            $currentSession = [
                'session' => [
                    'id' => $row['session_id'],
                    'day' => $row['day'],
                    'start_time' => $row['start_time'],
                    'end_time' => $row['end_time']
                ],
                'timetable' => [
                    [
                        'id' => $row['timetable_id'],
                        'semester' => $row['semester'],
                        'academic_year' => $row['academic_year'],
                        'module' => [
                            'id' => $row['module_id'],
                            'code' => $row['module_code'],
                            'name' => $row['module_name'],
                            'credits' => $row['credits'],
                            'year' => $row['module_year']
                        ],
                        'lecturer' => [
                            'id' => $row['lecturer_id'],
                            'name' => $row['lecturer_name']
                        ],
                        'facility' => [ 
                            'id' => $row['facility_id'],
                            'name' => $row['facility_name'],
                            'location' => $row['facility_location'],
                            'type' => $row['facility_type']
                        ],
                        'groups' => []
                    ]
                ]
            ];
        }

        // Add group with its complete chain to the current timetable entry
        $currentSession['timetable'][0]['groups'][] = [
            'id' => $row['group_id'],
            'name' => $row['group_name'],
            'intake' => [
                'id' => $row['intake_id'],
                'year' => $row['intake_year'],
                'month' => $row['intake_month']
            ],
            'program' => [
                'id' => $row['program_id'],
                'name' => $row['program_name'],
                'code' => $row['program_code']
            ],
            'department' => [
                'id' => $row['department_id'],
                'name' => $row['department_name']
            ],
            'school' => [
                'id' => $row['school_id'],
                'name' => $row['school_name']
            ],
            'college' => [
                'id' => $row['college_id'],
                'name' => $row['college_name']
            ],
            'campus' => [
                'id' => $row['campus_id'],
                'name' => $row['campus_name']
            ]
        ];
    }

    // Add the last session
    if ($currentSession !== null) {
        $sessions[] = $currentSession;
    }

    echo json_encode([
        'success' => true,
        'data' => $sessions
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 