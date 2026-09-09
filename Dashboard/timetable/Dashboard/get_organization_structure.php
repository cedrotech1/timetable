<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('connection.php');

header('Content-Type: application/json');

function getOrganizationStructure($connection) {
    $response = [
        'success' => false,
        'data' => null,
        'intakes' => null,
        'groups' => null,
        'error' => null
    ];

    try {
        $c = $_SESSION['campus'] ?? null;
        $program_id = $_GET['program_id'] ?? null;
        $intake_id = $_GET['intake_id'] ?? null;
        $school_id = $_GET['school_id'] ?? null;
        $department_id = $_GET['department_id'] ?? null;

        // If specific program_id is requested, return its intakes with groups if they exist
        if ($program_id) {
            $intakes = [];
            $intakeQuery = "SELECT id, year, month FROM intake WHERE program_id = ? ORDER BY year DESC, month DESC";
            $intakeStmt = mysqli_prepare($connection, $intakeQuery);
            mysqli_stmt_bind_param($intakeStmt, "i", $program_id);
            mysqli_stmt_execute($intakeStmt);
            $intakeResult = mysqli_stmt_get_result($intakeStmt);

            while ($intake = mysqli_fetch_assoc($intakeResult)) {
                $intakeData = [
                    'id' => $intake['id'],
                    'year' => $intake['year'],
                    'month' => $intake['month']
                ];

                // Get groups for this intake if they exist
                $groups = [];
                $groupQuery = "SELECT id, name, size FROM student_group WHERE intake_id = ? ORDER BY name";
                $groupStmt = mysqli_prepare($connection, $groupQuery);
                mysqli_stmt_bind_param($groupStmt, "i", $intake['id']);
                mysqli_stmt_execute($groupStmt);
                $groupResult = mysqli_stmt_get_result($groupStmt);

                while ($group = mysqli_fetch_assoc($groupResult)) {
                    $groups[] = [
                        'id' => $group['id'],
                        'name' => $group['name'],
                        'size' => $group['size']
                    ];
                }

                if (!empty($groups)) {
                    $intakeData['groups'] = $groups;
                }

                $intakes[] = $intakeData;
            }

            $response['success'] = true;
            $response['intakes'] = $intakes;
            echo json_encode($response);
            exit;
        }

        // If specific intake_id is requested, return its groups
        if ($intake_id) {
            $groups = [];
            $groupQuery = "SELECT id, name, size FROM student_group WHERE intake_id = ? ORDER BY name";
            $groupStmt = mysqli_prepare($connection, $groupQuery);
            mysqli_stmt_bind_param($groupStmt, "i", $intake_id);
            mysqli_stmt_execute($groupStmt);
            $groupResult = mysqli_stmt_get_result($groupStmt);

            while ($group = mysqli_fetch_assoc($groupResult)) {
                $groups[] = [
                    'id' => $group['id'],
                    'name' => $group['name'],
                    'size' => $group['size']
                ];
            }

            $response['success'] = true;
            $response['groups'] = $groups;
            echo json_encode($response);
            exit;
        }

        // If school_id is requested, return its departments and programs with intakes if they exist
        if ($school_id) {
            $schoolData = [];
            
            // Get departments for this school
            $departments = [];
            $departmentQuery = "SELECT id, name FROM department WHERE school_id = ? ORDER BY name";
            $departmentStmt = mysqli_prepare($connection, $departmentQuery);
            mysqli_stmt_bind_param($departmentStmt, "i", $school_id);
            mysqli_stmt_execute($departmentStmt);
            $departmentResult = mysqli_stmt_get_result($departmentStmt);

            while ($department = mysqli_fetch_assoc($departmentResult)) {
                $departments[] = [
                    'id' => $department['id'],
                    'name' => $department['name']
                ];
            }

            // Get all programs for this school with intakes if they exist
            $programs = [];
            $programQuery = "SELECT id, name, code, department_id FROM program WHERE school_id = ? ORDER BY name";
            $programStmt = mysqli_prepare($connection, $programQuery);
            mysqli_stmt_bind_param($programStmt, "i", $school_id);
            mysqli_stmt_execute($programStmt);
            $programResult = mysqli_stmt_get_result($programStmt);

            while ($program = mysqli_fetch_assoc($programResult)) {
                $programData = [
                    'id' => $program['id'],
                    'name' => $program['name'],
                    'code' => $program['code'],
                    'department_id' => $program['department_id']
                ];

                // Get intakes for this program if they exist
                $intakes = [];
                $intakeQuery = "SELECT id, year, month FROM intake WHERE program_id = ? ORDER BY year DESC, month DESC";
                $intakeStmt = mysqli_prepare($connection, $intakeQuery);
                mysqli_stmt_bind_param($intakeStmt, "i", $program['id']);
                mysqli_stmt_execute($intakeStmt);
                $intakeResult = mysqli_stmt_get_result($intakeStmt);

                while ($intake = mysqli_fetch_assoc($intakeResult)) {
                    $intakeData = [
                        'id' => $intake['id'],
                        'year' => $intake['year'],
                        'month' => $intake['month']
                    ];

                    // Get groups for this intake if they exist
                    $groups = [];
                    $groupQuery = "SELECT id, name, size FROM student_group WHERE intake_id = ? ORDER BY name";
                    $groupStmt = mysqli_prepare($connection, $groupQuery);
                    mysqli_stmt_bind_param($groupStmt, "i", $intake['id']);
                    mysqli_stmt_execute($groupStmt);
                    $groupResult = mysqli_stmt_get_result($groupStmt);

                    while ($group = mysqli_fetch_assoc($groupResult)) {
                        $groups[] = [
                            'id' => $group['id'],
                            'name' => $group['name'],
                            'size' => $group['size']
                        ];
                    }

                    if (!empty($groups)) {
                        $intakeData['groups'] = $groups;
                    }

                    $intakes[] = $intakeData;
                }

                if (!empty($intakes)) {
                    $programData['intakes'] = $intakes;
                }

                $programs[] = $programData;
            }

            $schoolData['departments'] = $departments;
            $schoolData['programs'] = $programs;
            
            $response['success'] = true;
            $response['data'] = $schoolData;
            echo json_encode($response);
            exit;
        }

        // Full organization structure with intakes and groups if they exist
        $campuses = [];
       
        // Modify campus query based on user role
        if ($c) {
            $campusQuery = "SELECT id, name FROM campus WHERE id = ? ORDER BY name";
            $campusStmt = mysqli_prepare($connection, $campusQuery);
            mysqli_stmt_bind_param($campusStmt, "i", $c);
            mysqli_stmt_execute($campusStmt);
            $campusResult = mysqli_stmt_get_result($campusStmt);
        } else {
            $campusQuery = "SELECT id, name FROM campus ORDER BY name";
            $campusResult = mysqli_query($connection, $campusQuery);
        }

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
                        
                        // Get department-specific programs with intakes if they exist
                        $departmentPrograms = [];
                        $programQuery = "SELECT id, name, code FROM program WHERE department_id = ? ORDER BY name";
                        $programStmt = mysqli_prepare($connection, $programQuery);
                        mysqli_stmt_bind_param($programStmt, "i", $departmentId);
                        mysqli_stmt_execute($programStmt);
                        $programResult = mysqli_stmt_get_result($programStmt);

                        while ($program = mysqli_fetch_assoc($programResult)) {
                            $programData = [
                                'id' => $program['id'],
                                'name' => $program['name'],
                                'code' => $program['code']
                            ];

                            // Get intakes for this program if they exist
                            $intakes = [];
                            $intakeQuery = "SELECT id, year, month FROM intake WHERE program_id = ? ORDER BY year DESC, month DESC";
                            $intakeStmt = mysqli_prepare($connection, $intakeQuery);
                            mysqli_stmt_bind_param($intakeStmt, "i", $program['id']);
                            mysqli_stmt_execute($intakeStmt);
                            $intakeResult = mysqli_stmt_get_result($intakeStmt);

                            while ($intake = mysqli_fetch_assoc($intakeResult)) {
                                $intakeData = [
                                    'id' => $intake['id'],
                                    'year' => $intake['year'],
                                    'month' => $intake['month']
                                ];

                                // Get groups for this intake if they exist
                                $groups = [];
                                $groupQuery = "SELECT id, name, size FROM student_group WHERE intake_id = ? ORDER BY name";
                                $groupStmt = mysqli_prepare($connection, $groupQuery);
                                mysqli_stmt_bind_param($groupStmt, "i", $intake['id']);
                                mysqli_stmt_execute($groupStmt);
                                $groupResult = mysqli_stmt_get_result($groupStmt);

                                while ($group = mysqli_fetch_assoc($groupResult)) {
                                    $groups[] = [
                                        'id' => $group['id'],
                                        'name' => $group['name'],
                                        'size' => $group['size']
                                    ];
                                }

                                if (!empty($groups)) {
                                    $intakeData['groups'] = $groups;
                                }

                                $intakes[] = $intakeData;
                            }

                            if (!empty($intakes)) {
                                $programData['intakes'] = $intakes;
                            }

                            $departmentPrograms[] = $programData;
                        }

                        $departments[] = [
                            'id' => $department['id'],
                            'name' => $department['name'],
                            'programs' => $departmentPrograms
                        ];
                    }

                    // Get all programs for this school with intakes if they exist
                    $allPrograms = [];
                    $programQuery = "SELECT id, name, code, department_id FROM program WHERE school_id = ? ORDER BY name";
                    $programStmt = mysqli_prepare($connection, $programQuery);
                    mysqli_stmt_bind_param($programStmt, "i", $schoolId);
                    mysqli_stmt_execute($programStmt);
                    $programResult = mysqli_stmt_get_result($programStmt);

                    while ($program = mysqli_fetch_assoc($programResult)) {
                        $programData = [
                            'id' => $program['id'],
                            'name' => $program['name'],
                            'code' => $program['code'],
                            'department_id' => $program['department_id']
                        ];

                        // Get intakes for this program if they exist
                        $intakes = [];
                        $intakeQuery = "SELECT id, year, month FROM intake WHERE program_id = ? ORDER BY year DESC, month DESC";
                        $intakeStmt = mysqli_prepare($connection, $intakeQuery);
                        mysqli_stmt_bind_param($intakeStmt, "i", $program['id']);
                        mysqli_stmt_execute($intakeStmt);
                        $intakeResult = mysqli_stmt_get_result($intakeStmt);

                        while ($intake = mysqli_fetch_assoc($intakeResult)) {
                            $intakeData = [
                                'id' => $intake['id'],
                                'year' => $intake['year'],
                                'month' => $intake['month']
                            ];

                            // Get groups for this intake if they exist
                            $groups = [];
                            $groupQuery = "SELECT id, name, size FROM student_group WHERE intake_id = ? ORDER BY name";
                            $groupStmt = mysqli_prepare($connection, $groupQuery);
                            mysqli_stmt_bind_param($groupStmt, "i", $intake['id']);
                            mysqli_stmt_execute($groupStmt);
                            $groupResult = mysqli_stmt_get_result($groupStmt);

                            while ($group = mysqli_fetch_assoc($groupResult)) {
                                $groups[] = [
                                    'id' => $group['id'],
                                    'name' => $group['name'],
                                    'size' => $group['size']
                                ];
                            }

                            if (!empty($groups)) {
                                $intakeData['groups'] = $groups;
                            }

                            $intakes[] = $intakeData;
                        }

                        if (!empty($intakes)) {
                            $programData['intakes'] = $intakes;
                        }

                        $allPrograms[] = $programData;
                    }

                    $schools[] = [
                        'id' => $school['id'],
                        'name' => $school['name'],
                        'departments' => $departments,
                        'all_programs' => $allPrograms  // All programs in this school with intakes if they exist
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