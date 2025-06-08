<?php
include('connection.php');

header('Content-Type: application/json');

function getOrganizationStructure($connection) {
    $response = [
        'success' => false,
        'data' => null,
        'error' => null
    ];

    try {
        // Get all campuses with their colleges
        $campuses = [];
        $campusQuery = "SELECT id, name FROM campus ORDER BY name";
        $campusResult = mysqli_query($connection, $campusQuery);

        while ($campus = mysqli_fetch_assoc($campusResult)) {
            $campusId = $campus['id'];
            
            // Get colleges for this campus
            $colleges = [];
            $collegeQuery = "SELECT id, name FROM college WHERE campus_id = ? ORDER BY name";
            $collegeStmt = mysqli_prepare($connection, $collegeQuery);
            mysqli_stmt_bind_param($collegeStmt, "i", $campusId);
            mysqli_stmt_execute($collegeStmt);
            $collegeResult = mysqli_stmt_get_result($collegeStmt);

            while ($college = mysqli_fetch_assoc($collegeResult)) {
                $collegeId = $college['id'];
                
                // Get schools for this college
                $schools = [];
                $schoolQuery = "SELECT id, name FROM school WHERE college_id = ? ORDER BY name";
                $schoolStmt = mysqli_prepare($connection, $schoolQuery);
                mysqli_stmt_bind_param($schoolStmt, "i", $collegeId);
                mysqli_stmt_execute($schoolStmt);
                $schoolResult = mysqli_stmt_get_result($schoolStmt);

                while ($school = mysqli_fetch_assoc($schoolResult)) {
                    $schoolId = $school['id'];
                    
                    // Get departments for this school
                    $departments = [];
                    $departmentQuery = "SELECT id, name FROM department WHERE school_id = ? ORDER BY name";
                    $departmentStmt = mysqli_prepare($connection, $departmentQuery);
                    mysqli_stmt_bind_param($departmentStmt, "i", $schoolId);
                    mysqli_stmt_execute($departmentStmt);
                    $departmentResult = mysqli_stmt_get_result($departmentStmt);

                    while ($department = mysqli_fetch_assoc($departmentResult)) {
                        $departmentId = $department['id'];
                        
                        // Get programs for this department
                        $programs = [];
                        $programQuery = "SELECT id, name FROM program WHERE department_id = ? ORDER BY name";
                        $programStmt = mysqli_prepare($connection, $programQuery);
                        mysqli_stmt_bind_param($programStmt, "i", $departmentId);
                        mysqli_stmt_execute($programStmt);
                        $programResult = mysqli_stmt_get_result($programStmt);

                        while ($program = mysqli_fetch_assoc($programResult)) {
                            $programId = $program['id'];
                            
                            // Get intakes for this program
                            $intakes = [];
                            $intakeQuery = "SELECT id, year, month FROM intake WHERE program_id = ? ORDER BY year DESC, month DESC";
                            $intakeStmt = mysqli_prepare($connection, $intakeQuery);
                            mysqli_stmt_bind_param($intakeStmt, "i", $programId);
                            mysqli_stmt_execute($intakeStmt);
                            $intakeResult = mysqli_stmt_get_result($intakeStmt);

                            while ($intake = mysqli_fetch_assoc($intakeResult)) {
                                $intakeId = $intake['id'];
                                // Get groups for this intake
                                $groups = [];
                                $groupQuery = "SELECT id, name FROM student_group WHERE intake_id = ? ORDER BY name";
                                $groupStmt = mysqli_prepare($connection, $groupQuery);
                                mysqli_stmt_bind_param($groupStmt, "i", $intakeId);
                                mysqli_stmt_execute($groupStmt);
                                $groupResult = mysqli_stmt_get_result($groupStmt);

                                while ($group = mysqli_fetch_assoc($groupResult)) {
                                    $groups[] = [
                                        'id' => $group['id'],
                                        'name' => $group['name']
                                    ];
                                }

                                $intakes[] = [
                                    'id' => $intake['id'],
                                    'year' => $intake['year'],
                                    'month' => $intake['month'],
                                    'groups' => $groups
                                ];
                            }

                            $programs[] = [
                                'id' => $program['id'],
                                'name' => $program['name'],
                                'intakes' => $intakes
                            ];
                        }

                        $departments[] = [
                            'id' => $department['id'],
                            'name' => $department['name'],
                            'programs' => $programs
                        ];
                    }

                    $schools[] = [
                        'id' => $school['id'],
                        'name' => $school['name'],
                        'departments' => $departments
                    ];
                }

                $colleges[] = [
                    'id' => $college['id'],
                    'name' => $college['name'],
                    'schools' => $schools
                ];
            }

            $campuses[] = [
                'id' => $campus['id'],
                'name' => $campus['name'],
                'colleges' => $colleges
            ];
        }

        $response['success'] = true;
        $response['data'] = $campuses;

    } catch (Exception $e) {
        $response['error'] = $e->getMessage();
    }

    return $response;
}

// Handle the request
$result = getOrganizationStructure($connection);
echo json_encode($result);
?> 