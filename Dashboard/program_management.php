<?php
session_start();
include('connection.php');

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit();
}
$user_id = $_SESSION["id"];
                    // get school
                    $sql = "SELECT * FROM users WHERE id = $user_id";
                    $result = mysqli_query($connection, $sql);
                    $row = mysqli_fetch_assoc($result);
                    $school_id = $row["school"];

$program_id = isset($_GET['program_id']) ? (int)$_GET['program_id'] : 0;
$program_name = isset($_GET['program_name']) ? urldecode($_GET['program_name']) : 'Program';

if ($program_id <= 0) {
    header('Location: dashboard.php');
    exit();
}

// Handle program deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_program'])) {
    // Start transaction
    $connection->begin_transaction();
    
    try {
        // First, get all intakes for this program to delete related groups
        $getIntakesStmt = $connection->prepare("SELECT id FROM intake WHERE program_id = ?");
        $getIntakesStmt->bind_param('i', $program_id);
        $getIntakesStmt->execute();
        $intakesResult = $getIntakesStmt->get_result();
        
        // Delete all groups for each intake
        $deleteGroupsStmt = $connection->prepare("DELETE FROM student_group WHERE intake_id = ?");
        while ($intake = $intakesResult->fetch_assoc()) {
            $deleteGroupsStmt->bind_param('i', $intake['id']);
            $deleteGroupsStmt->execute();
        }
        
        // Delete intakes for this program
        $deleteIntakesStmt = $connection->prepare("DELETE FROM intake WHERE program_id = ?");
        $deleteIntakesStmt->bind_param('i', $program_id);
        $deleteIntakesStmt->execute();
        
        // Delete all modules for this program
        $deleteModulesStmt = $connection->prepare("DELETE FROM module WHERE program_id = ?");
        $deleteModulesStmt->bind_param('i', $program_id);
        $deleteModulesStmt->execute();
        
        // Delete the program itself
        $deleteProgramStmt = $connection->prepare("DELETE FROM program WHERE id = ?");
        $deleteProgramStmt->bind_param('i', $program_id);
        $deleteProgramStmt->execute();
        
        // Commit transaction
        $connection->commit();
        
        $_SESSION['success_message'] = "Program deleted successfully";
        header("Location: campus.php");
        exit();
        
    } catch (Exception $e) {
        // Rollback on error
        $connection->rollback();
        $_SESSION['error_message'] = "Error: Failed to delete program. " . $e->getMessage();
        header("Location: program_management.php?program_id=$program_id&program_name=" . urlencode($program_name));
        exit();
    }
}

// Handle program update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_program_name'])) {
    $new_name = trim($_POST['program_name']);
    
    if (!empty($new_name)) {
        // Check if name already exists for a different program
        $checkStmt = $connection->prepare("SELECT id FROM program WHERE LOWER(name) = LOWER(?) AND id != ?");
        $checkStmt->bind_param('si', $new_name, $program_id);
        $checkStmt->execute();
        
        if ($checkStmt->get_result()->num_rows > 0) {
            $_SESSION['error_message'] = "Error: Program name already exists.";
        } else {
            $updateStmt = $connection->prepare("UPDATE program SET name = ? WHERE id = ?");
            $updateStmt->bind_param('si', $new_name, $program_id);
            
            if ($updateStmt->execute()) {
                $_SESSION['success_message'] = "Program name updated successfully.";
                $program_name = $new_name;
            } else {
                $_SESSION['error_message'] = "Error: Failed to update program name.";
            }
        }
    } else {
        $_SESSION['error_message'] = "Error: Program name cannot be empty.";
    }
    
    header("Location: program_management.php?program_id=$program_id&program_name=" . urlencode($new_name));
    exit();
}

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    
    try {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'add_intake':
                $connection->begin_transaction();
                try {
                    // Set year and month to 0 as they are no longer needed
                    $year = 0;
                    $month = 0;
                    
                    $campus_id = (int)($_POST['campus_id'] ?? 0);
                    $year_of_study = (int)($_POST['year_of_study'] ?? 1);
                    $total_students = (int)($_POST['total_students'] ?? 0);
                    $number_of_groups = (int)($_POST['number_of_groups'] ?? 1);
                    
                    // Validate inputs
                    if ($total_students <= 0 || $number_of_groups <= 0 || $number_of_groups > $total_students) {
                        throw new Exception('Invalid number of students or groups');
                    }
                    
                    // Calculate students per group
                    $base_group_size = floor($total_students / $number_of_groups);
                    $remainder = $total_students % $number_of_groups;
                    
                    // Check if promotion already exists for this program, year of study, and campus
                    $check = $connection->prepare("SELECT id FROM intake WHERE program_id = ? AND year_of_study = ? AND campus_id = ?");
                    $check->bind_param('iii', $program_id, $year_of_study, $campus_id);
                    $check->execute();
                    
                    if ($check->get_result()->num_rows > 0) {
                        throw new Exception('A promotion already exists for this program, year of study, and campus');
                    }
                    
                    // Insert the intake
                    $stmt = $connection->prepare("INSERT INTO intake (year, month, year_of_study, size, program_id, campus_id) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param('iiiiii', $year, $month, $year_of_study, $total_students, $program_id, $campus_id);
                    
                    if (!$stmt->execute()) {
                        throw new Exception('Failed to add promotion: ' . $connection->error);
                    }
                    
                    $intake_id = $connection->insert_id;
                    
                    // Create groups
                    $groupStmt = $connection->prepare("INSERT INTO student_group (name, size, intake_id) VALUES (?, ?, ?)");
                    
                    for ($i = 1; $i <= $number_of_groups; $i++) {
                        // Calculate group size (distribute remainder students among first few groups)
                        $group_size = ($i <= $remainder) ? $base_group_size + 1 : $base_group_size;
                        $group_name = 'Group ' . $i;
                        
                        $groupStmt->bind_param('sii', $group_name, $group_size, $intake_id);
                        if (!$groupStmt->execute()) {
                            throw new Exception('Failed to create group: ' . $groupStmt->error);
                        }
                    }
                    
                    $connection->commit();
                    $response['success'] = true;
                    $response['message'] = 'Promotion and ' . $number_of_groups . ' groups created successfully';
                    $response['intake_id'] = $intake_id;
                    
                } catch (Exception $e) {
                    $connection->rollback();
                    $response['message'] = $e->getMessage();
                }
                break;
                
            case 'delete_intake':
                $intake_id = (int)($_POST['intake_id'] ?? 0);
                
                // Start transaction
                $connection->begin_transaction();
                
                try {
                    // 1. First, get all groups for this intake
                    $getGroups = $connection->prepare("SELECT id FROM student_group WHERE intake_id = ?");
                    $getGroups->bind_param('i', $intake_id);
                    $getGroups->execute();
                    $groups = $getGroups->get_result()->fetch_all(MYSQLI_ASSOC);
                    $groupIds = array_column($groups, 'id');
                    
                    if (!empty($groupIds)) {
                        // Convert group IDs to comma-separated string for IN clause
                        $groupIdsStr = implode(',', array_fill(0, count($groupIds), '?'));
                        $types = str_repeat('i', count($groupIds));
                        
                        // 2. Get all timetable IDs that have these groups
                        $getTimetables = $connection->prepare("
                            SELECT DISTINCT timetable_id 
                            FROM timetable_groups 
                            WHERE group_id IN ($groupIdsStr)
                        ");
                        $getTimetables->bind_param($types, ...$groupIds);
                        $getTimetables->execute();
                        $timetables = $getTimetables->get_result()->fetch_all(MYSQLI_ASSOC);
                        $timetableIds = array_column($timetables, 'timetable_id');
                        
                        if (!empty($timetableIds)) {
                            $timetableIdsStr = implode(',', array_fill(0, count($timetableIds), '?'));
                            $timetableTypes = str_repeat('i', count($timetableIds));
                            
                            // 3. Delete from timetable_sessions
                            $deleteSessions = $connection->prepare("
                                DELETE FROM timetable_sessions 
                                WHERE timetable_id IN ($timetableIdsStr)
                            ");
                            $deleteSessions->bind_param($timetableTypes, ...$timetableIds);
                            if (!$deleteSessions->execute()) {
                                throw new Exception('Failed to delete timetable sessions');
                            }
                            
                            // 4. Delete from timetable_lecturers
                            $deleteLecturers = $connection->prepare("
                                DELETE FROM timetable_lecturers 
                                WHERE timetable_id IN ($timetableIdsStr)
                            ");
                            $deleteLecturers->bind_param($timetableTypes, ...$timetableIds);
                            if (!$deleteLecturers->execute()) {
                                throw new Exception('Failed to delete timetable lecturers');
                            }
                            
                            // 5. Delete from timetable_groups
                            $deleteTimetableGroups = $connection->prepare("
                                DELETE FROM timetable_groups 
                                WHERE group_id IN ($groupIdsStr)
                            
                            ");
                            $deleteTimetableGroups->bind_param($types, ...$groupIds);
                            if (!$deleteTimetableGroups->execute()) {
                                throw new Exception('Failed to delete timetable groups');
                            }
                            
                            // 6. Finally, delete the timetables
                            $deleteTimetables = $connection->prepare("
                                DELETE FROM timetable 
                                WHERE id IN ($timetableIdsStr)
                            ");
                            $deleteTimetables->bind_param($timetableTypes, ...$timetableIds);
                            if (!$deleteTimetables->execute()) {
                                throw new Exception('Failed to delete timetables');
                            }
                        }
                    }
                    
                    // 7. Now delete all groups associated with this intake
                    $deleteGroups = $connection->prepare("DELETE FROM student_group WHERE intake_id = ?");
                    $deleteGroups->bind_param('i', $intake_id);
                    
                    if (!$deleteGroups->execute()) {
                        throw new Exception('Failed to delete groups for this promotion');
                    }
                    
                    // 8. Finally, delete the intake itself
                    $deleteIntake = $connection->prepare("DELETE FROM intake WHERE id = ? AND program_id = ?");
                    $deleteIntake->bind_param('ii', $intake_id, $program_id);
                    
                    if (!$deleteIntake->execute()) {
                        throw new Exception('Failed to delete promotion');
                    }
                    
                    // If we got here, everything was successful
                    $connection->commit();
                    $response['success'] = true;
                    $response['message'] = 'Promotion and all associated data deleted successfully';
                    
                } catch (Exception $e) {
                    // Something went wrong, rollback the transaction
                    $connection->rollback();
                    $response['message'] = $e->getMessage();
                    error_log('Error deleting intake: ' . $e->getMessage());
                }
                break;
                
            case 'add_group':
                // Debug: Log the received POST data and raw input
                error_log('Raw POST data: ' . file_get_contents('php://input'));
                error_log('POST array: ' . print_r($_POST, true));
                
                // Debug: Log all server variables related to the request
                error_log('Request method: ' . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'));
                error_log('Content-Type header: ' . ($_SERVER['CONTENT_TYPE'] ?? 'NOT SET'));
                
                // Try to get JSON data if it's a JSON request
                $jsonData = json_decode(file_get_contents('php://input'), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    error_log('JSON data received: ' . print_r($jsonData, true));
                    $_POST = array_merge($_POST, $jsonData);
                }
                
                // Get parameters with extensive debugging
                $name = isset($_POST['name']) ? trim($_POST['name']) : '';
                $size = isset($_POST['size']) ? (int)$_POST['size'] : 0;
                $intake_id = isset($_POST['intake_id']) ? (int)$_POST['intake_id'] : 0;
                $group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;
                $program_id = isset($_POST['program_id']) ? (int)$_POST['program_id'] : 0;
                
                // Debug log all extracted values
                error_log("Extracted values - Name: '$name', Size: $size, Intake ID: $intake_id, Group ID: $group_id, Program ID: $program_id");
                
                // Initialize response array
                $response = ['success' => false, 'message' => ''];
                
                if (empty($name)) {
                    $response['message'] = 'Group name is required';
                    header('Content-Type: application/json');
                    echo json_encode($response);
                    exit;
                }
                
                if ($size <= 0) {
                    $response['message'] = 'Please enter a valid group size';
                    header('Content-Type: application/json');
                    echo json_encode($response);
                    exit;
                }
                
                if ($intake_id <= 0) {
                    $response['message'] = 'Invalid intake';
                    header('Content-Type: application/json');
                    echo json_encode($response);
                    exit;
                }
                
                // Start transaction
                $connection->begin_transaction();
                
                try {
                    if ($group_id > 0) {
                        // Update existing group
                        $check = $connection->prepare("SELECT id, size, name FROM student_group WHERE id = ? AND intake_id = ?");
                        $check->bind_param('ii', $group_id, $intake_id);
                        $check->execute();
                        $existingGroup = $check->get_result()->fetch_assoc();
                        
                        if (!$existingGroup) {
                            throw new Exception('Group not found');
                        }
                        
                        // Check if name is being changed and if the new name already exists
                        if ($name !== $existingGroup['name']) {
                            $nameCheck = $connection->prepare("SELECT id FROM student_group WHERE name = ? AND intake_id = ? AND id != ?");
                            $nameCheck->bind_param('sii', $name, $intake_id, $group_id);
                            $nameCheck->execute();
                            
                            if ($nameCheck->get_result()->num_rows > 0) {
                                throw new Exception('A group with this name already exists in the selected intake');
                            }
                        }
                        
                        // Update the group
                        $stmt = $connection->prepare("UPDATE student_group SET name = ?, size = ? WHERE id = ?");
                        $stmt->bind_param('sii', $name, $size, $group_id);
                        if (!$stmt->execute()) {
                            throw new Exception('Failed to update group: ' . $connection->error);
                        }
                        
                        // Calculate size difference for updating intake
                        $sizeDiff = $size - $existingGroup['size'];
                        $response['message'] = 'Group updated successfully';
                    } else {
                        // Add new group
                        // Check if group name already exists in this intake
                        $check = $connection->prepare("SELECT id FROM student_group WHERE name = ? AND intake_id = ?");
                        $check->bind_param('si', $name, $intake_id);
                        $check->execute();
                        
                        if ($check->get_result()->num_rows > 0) {
                            throw new Exception('A group with this name already exists in the selected intake');
                        }
                        
                        // Insert new group
                        $stmt = $connection->prepare("INSERT INTO student_group (name, size, intake_id) VALUES (?, ?, ?)");
                        $stmt->bind_param('sii', $name, $size, $intake_id);
                        if (!$stmt->execute()) {
                            throw new Exception('Failed to add group: ' . $connection->error);
                        }
                        $group_id = $connection->insert_id;
                        $sizeDiff = $size;
                        $response['message'] = 'Group added successfully';
                    }
                    
                    // Update intake size if there's a change
                    if ($sizeDiff != 0) {
                        $updateIntake = $connection->prepare("UPDATE intake SET size = GREATEST(0, size + ?) WHERE id = ?");
                        $updateIntake->bind_param('ii', $sizeDiff, $intake_id);
                        if (!$updateIntake->execute()) {
                            throw new Exception('Failed to update intake size: ' . $connection->error);
                        }
                    }
                    
                    $connection->commit();
                    $response['success'] = true;
                    $response['group_id'] = $group_id;
                    
                } catch (Exception $e) {
                    $connection->rollback();
                    $response['success'] = false;
                    $response['message'] = $e->getMessage();
                    error_log('Group update error: ' . $e->getMessage());
                }
                
                // Ensure we always return JSON
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
                break;
                
            case 'delete_group':
                $group_id = (int)($_POST['group_id'] ?? 0);
                
                if ($group_id <= 0) {
                    $response['message'] = 'Invalid group';
                    break;
                }
                
                // Start transaction
                $connection->begin_transaction();
                
                try {
                    // First get the group and its size before deleting
                    $getGroup = $connection->prepare("
                        SELECT sg.id, sg.size, sg.intake_id 
                        FROM student_group sg
                        JOIN intake i ON sg.intake_id = i.id
                        WHERE sg.id = ? AND i.program_id = ?
                    ");
                    $getGroup->bind_param('ii', $group_id, $program_id);
                    $getGroup->execute();
                    $group = $getGroup->get_result()->fetch_assoc();
                    
                    if (!$group) {
                        throw new Exception('Group not found or you do not have permission to delete it');
                    }
                    
                    // Delete the group
                    $delete = $connection->prepare("DELETE FROM student_group WHERE id = ?");
                    $delete->bind_param('i', $group_id);
                    $delete->execute();
                    
                    // Update the intake size
                    $updateIntake = $connection->prepare("UPDATE intake SET size = GREATEST(0, size - ?) WHERE id = ?");
                    $updateIntake->bind_param('ii', $group['size'], $group['intake_id']);
                    $updateIntake->execute();
                    
                    $connection->commit();
                    
                    $response['success'] = true;
                    $response['message'] = 'Group deleted successfully';
                    
                } catch (Exception $e) {
                    $connection->rollback();
                    $response['message'] = 'Failed to delete group: ' . $e->getMessage();
                }
                break;
                
            case 'get_intakes':
                $stmt = $connection->prepare("
                    SELECT i.*, 
                           (SELECT COUNT(*) FROM student_group WHERE intake_id = i.id) as group_count,
                           c.name as campus_name,
                           i.year_of_study
                    FROM intake i
                    LEFT JOIN campus c ON i.campus_id = c.id
                    WHERE i.program_id = ?
                    ORDER BY i.year_of_study DESC, c.name ASC
                ");
                $stmt->bind_param('i', $program_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $intakes = [];
                
                while ($row = $result->fetch_assoc()) {
                    $intakes[] = [
                        'id' => $row['id'],
                        'year' => $row['year'],
                        'month' => $row['month'],
                        'size' => $row['size'],
                        'group_count' => $row['group_count'],
                        'campus_name' => $row['campus_name'],
                        'year_of_study' => $row['year_of_study']
                    ];
                }
                
                $response['success'] = true;
                $response['data'] = $intakes;
                break;
                
            case 'get_groups':
                $intake_id = (int)($_POST['intake_id'] ?? 0);
                
                $stmt = $connection->prepare("
                    SELECT sg.* 
                    FROM student_group sg
                    JOIN intake i ON sg.intake_id = i.id
                    WHERE i.program_id = ? AND sg.intake_id = ?
                    ORDER BY sg.name
                
                ");
                $stmt->bind_param('ii', $program_id, $intake_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $groups = [];
                
                while ($row = $result->fetch_assoc()) {
                    $groups[] = [
                        'id' => $row['id'],
                        'name' => $row['name'],
                        'size' => $row['size']
                    ];
                }
                
                $response['success'] = true;
                $response['data'] = $groups;
                break;
                
            default:
                $response['message'] = 'Invalid action';
        }
    } catch (Exception $e) {
        $response['message'] = 'Server error: ' . $e->getMessage();
    }
    
    echo json_encode($response);
    exit();
}

// Get program details
$stmt = $connection->prepare("SELECT * FROM program WHERE id = ?");
$stmt->bind_param('i', $program_id);
$stmt->execute();
$program = $stmt->get_result()->fetch_assoc();

if (!$program) {
    header('Location: dashboard.php');
    exit();
}

// Get all modules for this program
$modulesStmt = $connection->prepare("SELECT * FROM module WHERE program_id = ? ORDER BY year ASC, semester ASC, name ASC");
$modulesStmt->bind_param('i', $program_id);
$modulesStmt->execute();
$modulesResult = $modulesStmt->get_result();
$modules = [];
while ($row = $modulesResult->fetch_assoc()) {
    $modules[] = $row;
}

// Get current year and month for the form
define('CURRENT_YEAR', date('Y'));
define('CURRENT_MONTH', date('n'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Program: <?= htmlspecialchars($program_name) ?> - Timetable System</title>
    
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="assets/img/icon1.png" rel="icon" />
    <meta content="" name="description">
    <meta content="" name="keywords">
    <link href="assets/img/icon1.png" rel="apple-touch-icon">
    <link href="https://fonts.gstatic.com" rel="preconnect">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">

    <!-- CDN UI libs (same stack as your college page) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.min.css" rel="stylesheet" />
    <link href="https://cdn.datatables.net/v/bs5/dt-2.0.8/datatables.min.css" rel="stylesheet" />

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <style>
        .intake-table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 1.5rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.05);
        }
        .intake-table th, .intake-table td {
            border: 1px solid #dee2e6;
            padding: 0.75rem;
            vertical-align: middle;
        }
        .intake-table thead th {
            background-color: #f8f9fa;
            font-weight: 600;
            text-align: left;
        }
        .intake-table tbody tr:hover {
            background-color: rgba(13, 110, 253, 0.05);
        }
        .intake-header {
            background-color: #f1f8ff !important;
            font-weight: 600;
        }
        .intake-month {
            color: #0d6efd;
            font-weight: 600;
        }
        .group-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.35em 0.65em;
            font-size: 0.875rem;
            font-weight: 500;
            line-height: 1;
            color: #fff;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
            background-color: rgb(22, 50, 99);
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .group-actions {
            white-space: nowrap;
        }
        .no-groups {
            color: #6c757d;
            font-style: italic;
        }
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        .action-buttons .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
        .btn-primary,.btn-outline-primary { background-color: rgb(22, 50, 99); color:#fff; }
        .btn-primary:hover,.btn-outline-primary:hover { background-color: rgb(147, 144, 204); }
        .btn-danger,.btn-outline-danger { background-color: rgb(207, 93, 0); color:#fff; }
        .btn-danger:hover,.btn-outline-danger:hover { background-color: rgb(207, 4, 4); }

        /* h5 { font-size: 17px !important; background-color: rgb(22, 50, 99) !important; color:#fff !important; padding: 6px !important; margin-bottom: 10px !important; border-radius: 5px !important; } */
        /* h4 { font-size: 17px !important; background-color: rgb(22, 50, 99) !important; color:#fff !important; padding: 6px !important; margin-bottom: 10px !important; border-radius: 5px !important; } */
        /* h2 { font-size: 17px !important; background-color: rgb(22, 50, 99) !important; color:#fff !important; padding: 6px !important; margin-bottom: 10px !important; border-radius: 5px !important; } */
        /* th { font-size: 17px !important; background-color: rgb(22, 50, 99) !important; color:#fff !important; padding: 6px !important; margin-bottom: 10px !important; border-radius: 5px !important; } */
       /* program defalt link color */
       table a { color: rgb(0, 2, 5) !important; }
       .main { background-color: #f8f9fa; }
       /* edit icon color */
       .card { border: none !important;
        box-shadow: 3px 3px 5px rgba(0, 0, 0, 0.1) !important;
        border-radius: 5px !important; }
        .pagetitle { padding: 10px !important;
        background-color: #fff !important;
        border-radius: 5px !important; }
        .pagetitle h1 { font-size: 19px !important; 
        background-color: rgb(22, 50, 99) !important; 
        color:#fff !important; 
        padding: 6px !important; 
        margin-bottom: 10px !important; 
        border-radius: 5px !important; }
       
    </style>
</head>
<body>
    <?php include('includes/header.php'); ?>
    <?php include('includes/menu.php'); ?>

    <main id="main" class="main">
        <div class="pagetitle">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1>
                        <?php
                        if($_SESSION['role']=="dean_office"){

                        }else{
                            ?>
                             <span id="program-name-display">Manage Program: <?= htmlspecialchars($program_name) ?></span>
                        <button type="button" class="btn btn-sm btn-outline-primary ms-2" onclick="editProgramName()">
                            <i class="bi bi-pencil"></i> Edit
                        </button>
                        <a href="campus.php" class="btn btn-sm btn-outline-secondary ms-2">
                            <i class="bi bi-arrow-left"></i> Back to Programs
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-danger ms-2" onclick="confirmDeleteProgram()">
                            <i class="bi bi-trash"></i> Delete Program
                        </button>

                            <?php
                        }
                        
                        ?>
                       
                    </h1>
                    <form id="edit-program-form" method="post" style="display:none;" class="mt-2">
                        <div class="input-group" style="max-width: 500px;">
                            <input type="text" name="program_name" class="form-control" value="<?= htmlspecialchars($program_name) ?>" required>
                            <button type="submit" name="update_program_name" class="btn btn-success">
                                <i class="bi bi-check"></i> Save
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="cancelEditProgramName()">
                                <i class="bi bi-x"></i> Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="school_page.php?id=<?= $school_id ?? '' ?>">School</a></li>
                    <li class="breadcrumb-item active"><?= htmlspecialchars($program_name) ?></li>
                </ol>
            </nav>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['success_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['error_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <section class="section">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Add New Promotion</h5>
                            <form id="addIntakeForm" class="row g-3">
                                <input type="hidden" name="program_id" value="<?= $program_id ?>">
                                <input type="hidden" name="year" value="0">
                                <input type="hidden" name="month" value="0">
                                <div class="col-md-3">
                                    <label for="intakeYearOfStudy" class="form-label">Year of Study</label>
                                    <select class="form-select" id="intakeYearOfStudy" required>
                                        <option value="1">Year 1</option>
                                        <option value="2">Year 2</option>
                                        <option value="3">Year 3</option>
                                        <option value="4">Year 4</option>
                                        <option value="5">Year 5</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="intakeCampus" class="form-label">Campus</label>
                                    <select class="form-select" id="intakeCampus" required>
                                        <option value="">Select Campus</option>
                                        <?php 
                                        $campusQuery = $connection->query("SELECT id, name FROM campus ORDER BY name");
                                        while ($campus = $campusQuery->fetch_assoc()) {
                                            echo '<option value="' . $campus['id'] . '">' . htmlspecialchars($campus['name']) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="totalStudents" class="form-label">Total Students</label>
                                    <input type="number" class="form-control" id="totalStudents" min="1" value="0" required>
                                </div>
                                <div class="col-md-2">
                                    <label for="numberOfGroups" class="form-label">Number of Groups <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="numberOfGroups" min="1" required
                                           placeholder="Enter number of groups"
                                           title="Please enter the number of groups to create">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-plus-circle"></i> Add Promotion
                                    </button>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <a href="program_modules.php?program_id=<?= $program_id ?>" class="btn btn-outline-primary w-100">
                                        <i class="bi bi-plus-circle"></i> Add Modules
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Promotions & Groups</h5>
                            <div id="intakesList">
                                <div class="text-center py-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2">Loading promotions and groups...</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Program Modules Section -->    
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="bi bi-book"></i> Program Modules 
                                <span class="badge bg-info ms-2"><?= count($modules) ?> modules</span>
                            </h5>
                            
                            <?php if (empty($modules)): ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> No modules found for this program. 
                                    <a href="program_modules.php?program_id=<?= $program_id ?>" class="alert-link">Add modules now</a>
                                </div>
                            <?php else: ?>
                                <div class="accordion" id="modulesAccordion">
                                    <?php 
                                    // Group modules by year only
                                    $groupedModules = [];
                                    foreach ($modules as $module) {
                                        $year = $module['year'] ?? 'N/A';
                                        $key = "Year $year";
                                        if (!isset($groupedModules[$key])) {
                                            $groupedModules[$key] = [];
                                        }
                                        $groupedModules[$key][] = $module;
                                    }
                                    
                                    $index = 0;
                                    foreach ($groupedModules as $groupName => $groupModules): 
                                        $index++;
                                        $collapseId = "collapse" . $index;
                                    ?>
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="heading<?= $index ?>">
                                                <button class="accordion-button <?= $index > 1 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>" aria-expanded="<?= $index === 1 ? 'true' : 'false' ?>" aria-controls="<?= $collapseId ?>">
                                                    <strong><?= htmlspecialchars($groupName) ?></strong>
                                                    <span class="badge bg-secondary ms-2"><?= count($groupModules) ?> modules</span>
                                                </button>
                                            </h2>
                                            <div id="<?= $collapseId ?>" class="accordion-collapse collapse <?= $index === 1 ? 'show' : '' ?>" aria-labelledby="heading<?= $index ?>" data-bs-parent="#modulesAccordion">
                                                <div class="accordion-body">
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-hover">
                                                            <thead>
                                                                <tr>
                                                                    <th>Code</th>
                                                                    <th>Module Name</th>
                                                                    <th>Semester</th>
                                                                    <th>Credits</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($groupModules as $module): ?>
                                                                    <tr>
                                                                        <td><code><?= htmlspecialchars($module['code']) ?></code></td>
                                                                        <td><?= htmlspecialchars($module['name']) ?></td>
                                                                        <td><span class="badge bg-info"><?= htmlspecialchars($module['semester']) ?></span></td>
                                                                        <td><span class="badge bg-primary"><?= htmlspecialchars($module['credits']) ?></span></td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="mt-3">
                                    <a href="program_modules.php?program_id=<?= $program_id ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-plus-circle"></i> Add More Modules
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Hidden form for program deletion -->
    <form id="delete-program-form" method="post" style="display:none;">
        <input type="hidden" name="delete_program" value="1">
    </form>

    <!-- Add Group Modal -->
    <div class="modal fade" id="addGroupModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Group</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addGroupForm">
                        <input type="hidden" id="groupIntakeId">
                        <div class="mb-3">
                            <label for="groupName" class="form-label">Group Name</label>
                            <input type="text" class="form-control" id="groupName" required>
                        </div>
                        <div class="mb-3">
                            <label for="groupSize" class="form-label">Group Size</label>
                            <input type="number" class="form-control" id="groupSize" min="1" value="30" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveGroupBtn">Save Group</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Vendor JS Files -->
    <script src="assets/vendor/apexcharts/apexcharts.min.js"></script>
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/chart.js/chart.umd.js"></script>
    <script src="assets/vendor/echarts/echarts.min.js"></script>
    <script src="assets/vendor/quill/quill.min.js"></script>
    <script src="assets/vendor/simple-datatables/simple-datatables.js"></script>
    <script src="assets/vendor/tinymce/tinymce.min.js"></script>
    <script src="assets/vendor/php-email-form/validate.js"></script>

    <!-- Template Main JS File -->
    <script src="assets/js/main.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.all.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // Load intakes on page load
        $(document).ready(function() {
            loadIntakes();
            
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
        
        // Add intake form submission
        // Clear number of groups when total students changes
        $('#totalStudents').on('input', function() {
            const totalStudents = parseInt($(this).val()) || 0;
            if (totalStudents > 0) {
                // Just clear the number of groups field
                $('#numberOfGroups').val('');
            }
        });

        $('#addIntakeForm').on('submit', function(e) {
            e.preventDefault();
            
            const yearOfStudy = $('#intakeYearOfStudy').val();
            const campusId = $('#intakeCampus').val();
            const totalStudents = parseInt($('#totalStudents').val()) || 0;
            const numberOfGroups = parseInt($('#numberOfGroups').val()) || 1;
            
            if (!yearOfStudy || !campusId || totalStudents <= 0 || numberOfGroups <= 0) {
                showAlert('error', 'Error', 'Please fill in all fields with valid values');
                return;
            }
            
            if (numberOfGroups > totalStudents) {
                showAlert('error', 'Error', 'Number of groups cannot be greater than total students');
                return; 
            }
            
            // Submit the form
            $.ajax({
                url: '?ajax=1&program_id=<?= $program_id ?>',
                method: 'POST',
                data: {
                    action: 'add_intake',
                    year_of_study: yearOfStudy,
                    campus_id: campusId,
                    total_students: totalStudents,
                    number_of_groups: numberOfGroups
                },
                success: function(response) {
                    if (response.success) {
                        showAlert('success', 'Success', response.message || 'Intake added successfully');
                        loadIntakes();
                        $('#addIntakeForm')[0].reset();
                    } else {
                        showAlert('error', 'Error', response.message || 'Failed to add intake');
                    }
                },
                error: function() {
                    showAlert('error', 'Error', 'An error occurred while processing your request');
                },
            });
        });
        
        // Save group button click
        $('#saveGroupBtn').on('click', function() {
            const name = $('#groupName').val().trim();
            const size = $('#groupSize').val();
            const intakeId = $('#groupIntakeId').val();
            
            if (!name) {
                showAlert('error', 'Error', 'Group name is required');
                return;
            }
            
            if (size <= 0) {
                showAlert('error', 'Error', 'Please enter a valid group size');
                return;
            }
            
            if (intakeId <= 0) {
                showAlert('error', 'Error', 'Invalid intake');
                return;
            }
            
            $.ajax({
                url: '?ajax=1&program_id=<?= $program_id ?>',
                method: 'POST',
                data: {
                    action: 'add_group',
                    name: name,
                    size: size,
                    intake_id: intakeId
                },
                success: function(response) {
                    if (response.success) {
                        showAlert('success', 'Success', response.message || 'Group added successfully');
                        $('#addGroupModal').modal('hide');
                        loadIntakes();
                    } else {
                        showAlert('error', 'Error', response.message || 'Failed to add group');
                    }
                },
                error: function() {
                    showAlert('error', 'Error', 'An error occurred while processing your request');
                }
            });
        });
        
        // Show add group modal
        $(document).on('click', '.add-group-btn', function() {
            const intakeId = $(this).data('intake-id');
            const intakeMonth = $(this).data('intake-month');
            const intakeYear = $(this).data('intake-year');
            
            $('#groupIntakeId').val(intakeId);
            $('#groupName').val('');
            $('#groupSize').val('30');
            
            $('#addGroupModal .modal-title').text(`Add Group - ${intakeMonth} ${intakeYear}`);
            $('#addGroupModal').modal('show');
        });
        
        // Delete intake
        $(document).on('click', '.delete-intake-btn', function() {
            const intakeId = $(this).data('intake-id');
            const intakeName = $(this).data('intake-name');
            
            Swal.fire({
                title: 'Delete Intake?',
                text: `Are you sure you want to delete the intake ${intakeName}? This action cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '?ajax=1&program_id=<?= $program_id ?>',
                        method: 'POST',
                        data: {
                            action: 'delete_intake',
                            intake_id: intakeId
                        },
                        success: function(response) {
                            if (response.success) {
                                showAlert('success', 'Success', response.message || 'Intake deleted successfully');
                                loadIntakes();
                            } else {
                                showAlert('error', 'Error', response.message || 'Failed to delete intake');
                            }
                        },
                        error: function() {
                            showAlert('error', 'Error', 'An error occurred while processing your request');
                        }
                    });
                }
            });
        });
        
        // Delete group
        $(document).on('click', '.delete-group-btn', function(e) {
            e.stopPropagation();
            
            const groupId = $(this).data('group-id');
            const groupName = $(this).data('group-name');
            
            Swal.fire({
                title: 'Delete Group?',
                text: `Are you sure you want to delete the group ${escapeHtml(groupName)}?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '?ajax=1&program_id=<?= $program_id ?>',
                        method: 'POST',
                        data: {
                            action: 'delete_group',
                            group_id: groupId
                        },
                        success: function(response) {
                            if (response.success) {
                                showAlert('success', 'Success', response.message || 'Group deleted successfully');
                                loadIntakes();
                            } else {
                                showAlert('error', 'Error', response.message || 'Failed to delete group');
                            }
                        },
                        error: function() {
                            showAlert('error', 'Error', 'An error occurred while processing your request');
                        }
                    });
                }
            });
        });
        
        // Edit group button click handler - redirect to edit page
        $(document).on('click', '.edit-group-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const groupId = $(this).data('group-id');
            const intakeId = $(this).data('intake-id');
            
            if (groupId && intakeId) {
                window.location.href = `edit_group_page.php?group_id=${groupId}&intake_id=${intakeId}&program_id=<?= $program_id ?>`;
            }
        });
        
        // Toggle groups visibility
        $(document).on('click', '.intake-header', function() {
            const card = $(this).closest('.intake-card');
            const groupsContainer = card.find('.groups-container');
            const icon = card.find('.toggle-icon');
            
            groupsContainer.slideToggle(200);
            icon.toggleClass('bi-chevron-down bi-chevron-up');
        });
        
        // Load groups for an intake (used when adding/deleting groups)
        function loadGroups(intakeId, container) {
            // This function is kept for backward compatibility
            // The main table now loads all data at once
            if (container) {
                container.html('<span class="badge bg-primary">Refreshing...</span>');
                setTimeout(() => {
                    loadIntakes(); // Refresh the entire table
                }, 300);
            }
        }
        
        // Load all intakes with groups in a table
        function loadIntakes() {
            $('#intakesList').html(`
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading promotions and groups...</p>
                </div>
            `);
            
            $.ajax({
                url: '?ajax=1&program_id=<?= $program_id ?>',
                method: 'POST',
                data: { action: 'get_intakes' },
                success: function(response) {
                    if (response.success) {
                        const intakes = response.data || [];
                        
                        if (intakes.length > 0) {
                            let html = `
                                <div class="table-responsive">
                                    <table class="intake-table">
                                        <thead>
                                            <tr>
                                                <th style="width: 25%;">Year of Study</th>
                                                <th style="width: 25%;">Campus</th>
                                                <th style="width: 15%;">Size</th>
                                                <th style="width: 20%;">Groups</th>
                                                <th style="width: 15%;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                            `;
                            
                            // First, collect all intakes with their groups
                            const intakePromises = intakes.map(intake => {
                                return new Promise((resolve) => {
                                    const intakeKey = `Year ${intake.year_of_study || 'N/A'}`;
                                    
                                    // Get groups for this intake
                                    $.ajax({
                                        url: '?ajax=1&program_id=<?= $program_id ?>',
                                        method: 'POST',
                                        data: {
                                            action: 'get_groups',
                                            intake_id: intake.id
                                        },
                                        success: function(groupsResponse) {
                                            const groups = groupsResponse.success ? (groupsResponse.data || []) : [];
                                            resolve({
                                                id: intake.id,
                                                name: intakeKey,
                                                campus_name: intake.campus_name || 'N/A',
                                                size: intake.size,
                                                year_of_study: intake.year_of_study,
                                                groupCount: groups.length,
                                                groups: groups
                                            });
                                        },
                                        error: function() {
                                            resolve({
                                                id: intake.id,
                                                name: intakeKey,
                                                campus_name: intake.campus_name || 'N/A',
                                                size: intake.size,
                                                year_of_study: intake.year_of_study,
                                                groupCount: 0,
                                                groups: []
                                            });
                                        }
                                    });
                                });
                            });
                            
                            // When all intakes have been processed with their groups
                            Promise.all(intakePromises).then(intakesWithGroups => {
                                // Sort intakes by year of study (newest first)
                                intakesWithGroups.sort((a, b) => b.year_of_study - a.year_of_study);
                                
                                // Generate table rows
                                intakesWithGroups.forEach((intake, index) => {
                                    const rowCount = Math.max(1, intake.groups.length);
                                    
                                    // Intake info cell (with rowspan)
                                    html += `
                                        <tr>
                                            <td rowspan="${rowCount}">Year ${intake.year_of_study || 'N/A'}</td>
                                            <td rowspan="${rowCount}">${intake.campus_name || 'N/A'}</td>
                                            <td rowspan="${rowCount}">${intake.size} students</td>
                                    `;
                                    
                                    // Groups for this intake
                                    if (intake.groups.length > 0) {
                                        // First group in the first row
                                        const firstGroup = intake.groups[0];
                                        html += `
                                            <td>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="group-badge">
                                                        ${escapeHtml(firstGroup.name)} (${firstGroup.size} students)
                                                    </span>
                                                    <div class="action-buttons">
                                                        <button type="button" class="btn btn-sm btn-outline-danger delete-group-btn" 
                                                                data-group-id="${firstGroup.id}" 
                                                                data-group-name="${firstGroup.name}"
                                                                data-bs-toggle="tooltip" 
                                                                data-bs-placement="top" 
                                                                title="Delete Group">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                        <a href="edit_group_page.php?group_id=${firstGroup.id}&intake_id=${intake.id}&program_id=<?= $program_id ?>" 
                                                           class="btn btn-sm btn-outline-primary"
                                                           data-bs-toggle="tooltip" 
                                                           data-bs-placement="top" 
                                                           title="Edit Group">
                                                            <i class="bi bi-pencil" style="color: rgb(248, 250, 252)"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="action-buttons" rowspan="${rowCount}">
                                                <div class="d-flex flex-column gap-2">
                                                    <button type="button" class="btn btn-sm btn-primary add-group-btn" 
                                                            data-intake-id="${intake.id}" 
                                                            data-intake-month="${intake.month}" 
                                                            data-intake-year="${intake.year}"
                                                            data-bs-toggle="tooltip" 
                                                            data-bs-placement="top" 
                                                            title="Add Group">
                                                        <i class="bi bi-plus-circle"></i> Add Group
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger delete-intake-btn" 
                                                            data-intake-id="${intake.id}" 
                                                            data-intake-name="Year ${intake.year_of_study || 'N/A'} (${intake.campus_name || 'N/A'})"
                                                            data-bs-toggle="tooltip" 
                                                            data-bs-placement="bottom" 
                                                            title="Delete Intake">
                                                        <i class="bi bi-trash"></i> Delete Intake
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        `;
                                        
                                        // Additional groups in separate rows
                                        for (let i = 1; i < intake.groups.length; i++) {
                                            const group = intake.groups[i];
                                            html += `
                                                <tr>
                                                    <td>
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <span class="group-badge">
                                                                ${escapeHtml(group.name)} (${group.size} students)
                                                            </span>
                                                            <div class="action-buttons">
                                                                <button type="button" class="btn btn-sm btn-outline-danger delete-group-btn" 
                                                                        data-group-id="${group.id}" 
                                                                        data-group-name="${group.name}"
                                                                        data-bs-toggle="tooltip" 
                                                                        data-bs-placement="top" 
                                                                        title="Delete Group">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                                <a href="edit_group_page.php?group_id=${group.id}&intake_id=${intake.id}&program_id=<?= $program_id ?>" 
                                                                   class="btn btn-sm btn-outline-primary"
                                                                   data-bs-toggle="tooltip" 
                                                                   data-bs-placement="top" 
                                                                   title="Edit Group">
                                                                    <i class="bi bi-pencil" style="color: rgb(248, 250, 252)"></i>
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            `;
                                        }
                                    } else {
                                        // No groups for this intake
                                        html += `
                                            <td class="no-groups">No groups added yet</td>
                                            <td class="action-buttons">
                                                <div class="d-flex flex-column gap-2">
                                                    <button type="button" class="btn btn-sm btn-primary add-group-btn" 
                                                            data-intake-id="${intake.id}" 
                                                            data-intake-month="${intake.month}" 
                                                            data-intake-year="${intake.year}"
                                                            data-bs-toggle="tooltip" 
                                                            data-bs-placement="top" 
                                                            title="Add Group">
                                                        <i class="bi bi-plus-circle"></i> Add Group
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger delete-intake-btn" 
                                                            data-intake-id="${intake.id}" 
                                                            data-intake-name="Year ${intake.year_of_study || 'N/A'} (${intake.campus_name || 'N/A'})"
                                                            data-bs-toggle="tooltip" 
                                                            data-bs-placement="bottom" 
                                                            title="Delete Intake">
                                                        <i class="bi bi-trash"></i> Delete Intake
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        `;
                                    }
                                });
                                
                                // Close table
                                html += `
                                        </tbody>
                                    </table>
                                </div>
                                `;
                                
                                $('#intakesList').html(html);
                                
                                // Initialize tooltips for the new elements
                                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                                var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                                    return new bootstrap.Tooltip(tooltipTriggerEl);
                                });
                                
                            });
                        } else {
                            $('#intakesList').html(`
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> No promotions found. Add your first promotion using the form above.
                                </div>
                            `);
                        }
                    } else {
                        $('#intakesList').html(`
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle"></i> Failed to load promotions. ${response.message || 'Please try again.'}
                            </div>
                        `);
                    }
                },
                error: function() {
                    $('#intakesList').html(`
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i> An error occurred while loading promotions. Please try again later.
                        </div>
                    `);
                }
            });
        }
        
        // Helper function to escape HTML
        function escapeHtml(unsafe) {
            if (typeof unsafe !== 'string') return unsafe;
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
        
        // Function to show group form (add/edit)
        function showGroupForm(intakeId, groupId = 0, groupName = '', groupSize = 0) {
            const isEdit = groupId > 0;
            
            Swal.fire({
                title: isEdit ? 'Edit Group' : 'Add New Group',
                html: `
                    <div class="mb-3">
                        <label for="groupName" class="form-label">Group Name</label>
                        <input type="text" class="form-control" id="groupName" value="${escapeHtml(groupName)}" placeholder="Enter group name" required>
                    </div>
                    <div class="mb-3">
                        <label for="groupSize" class="form-label">Group Size</label>
                        <input type="number" class="form-control" id="groupSize" min="1" value="${groupSize || ''}" required>
                    </div>
                    <input type="hidden" id="groupId" value="${groupId}">
                `,
                showCancelButton: true,
                confirmButtonText: isEdit ? 'Update Group' : 'Add Group',
                cancelButtonText: 'Cancel',
                focusConfirm: false,
                preConfirm: () => {
                    const name = document.getElementById('groupName').value.trim();
                    const size = parseInt(document.getElementById('groupSize').value);
                    
                    if (!name) {
                        Swal.showValidationMessage('Group name is required');
                        return false;
                    }
                    
                    if (isNaN(size) || size <= 0) {
                        Swal.showValidationMessage('Please enter a valid group size');
                        return false;
                    }
                    
                    return { name, size };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const { name, size } = result.value;
                    const groupId = document.getElementById('groupId').value ? parseInt(document.getElementById('groupId').value) : 0;
                    
                    // Create request data
                    const requestData = {
                        action: 'add_group',
                        name: name,
                        size: size,
                        intake_id: intakeId,
                        program_id: <?= $program_id ?>
                    };
                    
                    // Add group_id only if editing
                    if (groupId > 0) {
                        requestData.group_id = groupId;
                    }
                    
                    console.log('Sending data:', requestData);
                    
                    // Show loading state
                    Swal.fire({
                        title: isEdit ? 'Updating...' : 'Adding...',
                        text: 'Please wait',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Show loading state
                    Swal.fire({
                        title: isEdit ? 'Updating Group...' : 'Adding Group...',
                        text: 'Please wait',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading()
                    });

                    // Send request to edit_group.php
                    const formData = new FormData();
                    formData.append('name', name);
                    formData.append('size', size);
                    formData.append('intake_id', intakeId);
                    if (isEdit) {
                        formData.append('group_id', groupId);
                    }

                    fetch('edit_group.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: result.message,
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                // Reload the intakes to show updates
                                loadIntakes();
                            });
                        } else {
                            throw new Error(result.message || 'Operation failed');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: error.message || 'An error occurred. Please try again.'
                        });
                    });
                }
            });
        }   
        
        // Helper function to show toast notifications
        function toast(type, message) {
            Swal.fire({
                icon: type,
                title: 'Notification',
                text: message,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        }
        
        // Helper function to show alerts
        function showAlert(icon, title, text) {
            Swal.fire({
                icon: icon,
                title: title,
                text: text,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        }
    });
    
    // Program name edit functions
    function editProgramName() {
        document.getElementById('program-name-display').style.display = 'none';
        document.getElementById('edit-program-form').style.display = 'inline-block';
    }
    
    function cancelEditProgramName() {
        document.getElementById('program-name-display').style.display = 'inline';
        document.getElementById('edit-program-form').style.display = 'none';
    }
    
    // Program deletion function
    function confirmDeleteProgram() {
        Swal.fire({
            title: 'Delete Program?',
            html: '<strong>Warning:</strong> This will permanently delete the program along with:<br>' +
                  '• All modules<br>' +
                  '• All promotions<br>' +
                  '• All student groups<br><br>' +
                  '<strong>This action cannot be undone!</strong>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return new Promise((resolve) => {
                    // Submit the delete form
                    document.getElementById('delete-program-form').submit();
                    resolve();
                });
            },
            allowOutsideClick: () => !Swal.isLoading()
        });
    }
    
    // Program name edit functions
    function editProgramName() {
        document.getElementById('program-name-display').style.display = 'none';
        document.getElementById('edit-program-form').style.display = 'block';
    }
    
    function cancelEditProgramName() {
        document.getElementById('program-name-display').style.display = 'inline';
        document.getElementById('edit-program-form').style.display = 'none';
    }
    
    </script>
    
</body>
</html>
