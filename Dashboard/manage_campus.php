<?php
// Set secure session cookie parameters BEFORE starting the session
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Lax');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set error handling to return JSON for AJAX requests
function handleError($errno, $errstr, $errfile, $errline) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $errstr]);
        exit;
    }
    return false;
}

// Set error handler
set_error_handler('handleError');

// Handle fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Fatal error: ' . $error['message']]);
            exit;
        }
    }
});

include('connection.php');
$id = $_SESSION['id'];
$sql = "SELECT * FROM users WHERE id = $id";
$result = mysqli_query($connection, $sql);
$row = mysqli_fetch_assoc($result);
$mycampus = $row['campus'];
$role = $row['role'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Set JSON content type header
        header('Content-Type: application/json');
        
        $response = ['success' => false, 'message' => ''];
        
        try {
            switch ($_POST['action']) {
                case 'add_campus':
                case 'edit_campus':
                    if (!isset($_POST['campus_name']) || empty($_POST['campus_name'])) {
                        throw new Exception('Campus name is required');
                    }
                    $name = mysqli_real_escape_string($connection, $_POST['campus_name']);
                    $id = isset($_POST['campus_id']) ? (int)$_POST['campus_id'] : 0;
                    
                    // Check for duplicate campus name
                    $check_query = "SELECT id FROM campus WHERE LOWER(name) = LOWER('$name')";
                    if ($id > 0) {
                        $check_query .= " AND id != $id";
                    }
                    $check_result = mysqli_query($connection, $check_query);
                    if (mysqli_num_rows($check_result) > 0) {
                        throw new Exception('A campus with this name already exists');
                    }
                    
                    if ($_POST['action'] === 'add_campus') {
                        if (!mysqli_query($connection, "INSERT INTO campus (name) VALUES ('$name')")) {
                            throw new Exception("Failed to add campus: " . mysqli_error($connection));
                        }
                        $response = ['success' => true, 'message' => 'Campus added successfully.'];
                    } else {
                        if (!mysqli_query($connection, "UPDATE campus SET name = '$name' WHERE id = $id")) {
                            throw new Exception("Failed to update campus: " . mysqli_error($connection));
                        }
                        $response = ['success' => true, 'message' => 'Campus updated successfully.'];
                    }
                    break;

                case 'delete_campus':
                    if (!isset($_POST['campus_id']) || empty($_POST['campus_id'])) {
                        throw new Exception('Campus ID is required');
                    }
                    $id = (int)$_POST['campus_id'];
                    if (!mysqli_query($connection, "DELETE FROM campus WHERE id = $id")) {
                        throw new Exception("Failed to delete campus: " . mysqli_error($connection));
                    }
                    $response = ['success' => true, 'message' => 'Campus deleted successfully.'];
                    break;
                
                case 'add_college':
                case 'edit_college':
                    if (!isset($_POST['college_name']) || empty($_POST['college_name'])) {
                        throw new Exception('College name is required');
                    }
                    if (!isset($_POST['campus_id']) || empty($_POST['campus_id'])) {
                        throw new Exception('Campus ID is required');
                    }
                    $name = mysqli_real_escape_string($connection, $_POST['college_name']);
                    $campus_id = (int)$_POST['campus_id'];
                    $id = isset($_POST['college_id']) ? (int)$_POST['college_id'] : 0;
                    
                    // Check for duplicate college name in the same campus
                    $check_query = "SELECT id FROM college WHERE LOWER(name) = LOWER('$name') AND campus_id = $campus_id";
                    if ($id > 0) {
                        $check_query .= " AND id != $id";
                    }
                    $check_result = mysqli_query($connection, $check_query);
                    if (mysqli_num_rows($check_result) > 0) {
                        throw new Exception('A college with this name already exists in this campus');
                    }
                    
                    if ($_POST['action'] === 'add_college') {
                        if (!mysqli_query($connection, "INSERT INTO college (name, campus_id) VALUES ('$name', $campus_id)")) {
                            throw new Exception("Failed to add college: " . mysqli_error($connection));
                        }
                        $response = ['success' => true, 'message' => 'College added successfully.'];
                    } else {
                        if (!mysqli_query($connection, "UPDATE college SET name = '$name', campus_id = $campus_id WHERE id = $id")) {
                            throw new Exception("Failed to update college: " . mysqli_error($connection));
                        }
                        $response = ['success' => true, 'message' => 'College updated successfully.'];
                    }
                    break;

                case 'delete_college':
                    if (!isset($_POST['college_id']) || empty($_POST['college_id'])) {
                        throw new Exception('College ID is required');
                    }
                    $id = (int)$_POST['college_id'];
                    if (!mysqli_query($connection, "DELETE FROM college WHERE id = $id")) {
                        throw new Exception("Failed to delete college: " . mysqli_error($connection));
                    }
                    $response = ['success' => true, 'message' => 'College deleted successfully.'];
                    break;
                
                case 'add_school':
                case 'edit_school':
                    if (!isset($_POST['school_name']) || empty($_POST['school_name'])) {
                        throw new Exception('School name is required');
                    }
                    if (!isset($_POST['college_id']) || empty($_POST['college_id'])) {
                        throw new Exception('College ID is required');
                    }
                    $name = mysqli_real_escape_string($connection, $_POST['school_name']);
                    $college_id = (int)$_POST['college_id'];
                    $id = isset($_POST['school_id']) ? (int)$_POST['school_id'] : 0;
                    
                    // Check for duplicate school name in the same college
                    $check_query = "SELECT id FROM school WHERE LOWER(name) = LOWER('$name') AND college_id = $college_id";
                    if ($id > 0) {
                        $check_query .= " AND id != $id";
                    }
                    $check_result = mysqli_query($connection, $check_query);
                    if (mysqli_num_rows($check_result) > 0) {
                        throw new Exception('A school with this name already exists in this college');
                    }
                    
                    if ($_POST['action'] === 'add_school') {
                        if (!mysqli_query($connection, "INSERT INTO school (name, college_id) VALUES ('$name', $college_id)")) {
                            throw new Exception("Failed to add school: " . mysqli_error($connection));
                        }
                        $response = ['success' => true, 'message' => 'School added successfully.'];
                    } else {
                        if (!mysqli_query($connection, "UPDATE school SET name = '$name', college_id = $college_id WHERE id = $id")) {
                            throw new Exception("Failed to update school: " . mysqli_error($connection));
                        }
                        $response = ['success' => true, 'message' => 'School updated successfully.'];
                    }
                    break;

                case 'delete_school':
                    if (!isset($_POST['school_id']) || empty($_POST['school_id'])) {
                        throw new Exception('School ID is required');
                    }
                    $id = (int)$_POST['school_id'];
                    if (!mysqli_query($connection, "DELETE FROM school WHERE id = $id")) {
                        throw new Exception("Failed to delete school: " . mysqli_error($connection));
                    }
                    $response = ['success' => true, 'message' => 'School deleted successfully.'];
                    break;

                case 'add_department':
                case 'edit_department':
                    if (!isset($_POST['department_name']) || empty($_POST['department_name'])) {
                        throw new Exception('Department name is required');
                    }
                    if (!isset($_POST['school_id']) || empty($_POST['school_id'])) {
                        throw new Exception('School ID is required');
                    }
                    $name = mysqli_real_escape_string($connection, $_POST['department_name']);
                    $school_id = (int)$_POST['school_id'];
                    $id = isset($_POST['department_id']) ? (int)$_POST['department_id'] : 0;
                    
                    // Check for duplicate department name in the same school
                    $check_query = "SELECT id FROM department WHERE LOWER(name) = LOWER('$name') AND school_id = $school_id";
                    if ($id > 0) {
                        $check_query .= " AND id != $id";
                    }
                    $check_result = mysqli_query($connection, $check_query);
                    if (mysqli_num_rows($check_result) > 0) {
                        throw new Exception('A department with this name already exists in this school');
                    }
                    
                    if ($_POST['action'] === 'add_department') {
                        if (!mysqli_query($connection, "INSERT INTO department (name, school_id) VALUES ('$name', $school_id)")) {
                            throw new Exception("Failed to add department: " . mysqli_error($connection));
                        }
                        $response = ['success' => true, 'message' => 'Department added successfully.'];
                    } else {
                        if (!mysqli_query($connection, "UPDATE department SET name = '$name', school_id = $school_id WHERE id = $id")) {
                            throw new Exception("Failed to update department: " . mysqli_error($connection));
                        }
                        $response = ['success' => true, 'message' => 'Department updated successfully.'];
                    }
                    break;

                case 'delete_department':
                    if (!isset($_POST['department_id']) || empty($_POST['department_id'])) {
                        throw new Exception('Department ID is required');
                    }
                    $id = (int)$_POST['department_id'];
                    if (!mysqli_query($connection, "DELETE FROM department WHERE id = $id")) {
                        throw new Exception("Failed to delete department: " . mysqli_error($connection));
                    }
                    $response = ['success' => true, 'message' => 'Department deleted successfully.'];
                    break;

                case 'add_program':
                case 'edit_program':
                    if (!isset($_POST['program_name']) || empty($_POST['program_name'])) {
                        throw new Exception('Program name is required');
                    }
                    if (!isset($_POST['program_code']) || empty($_POST['program_code'])) {
                        throw new Exception('Program code is required');
                    }
                    if (!isset($_POST['department_id']) || empty($_POST['department_id'])) {
                        throw new Exception('Department ID is required');
                    }
                    $name = mysqli_real_escape_string($connection, $_POST['program_name']);
                    $code = mysqli_real_escape_string($connection, $_POST['program_code']);
                    $department_id = (int)$_POST['department_id'];
                    $id = isset($_POST['program_id']) ? (int)$_POST['program_id'] : 0;
                    
                    // Check for duplicate program name in the same department
                    $check_query = "SELECT id FROM program WHERE LOWER(name) = LOWER('$name') AND department_id = $department_id";
                    if ($id > 0) {
                        $check_query .= " AND id != $id";
                    }
                    $check_result = mysqli_query($connection, $check_query);
                    if (mysqli_num_rows($check_result) > 0) {
                        throw new Exception('A program with this name already exists in this department');
                    }
                    
                    // Check for duplicate program code in the same department
                    $check_query = "SELECT id FROM program WHERE LOWER(code) = LOWER('$code') AND department_id = $department_id";
                    if ($id > 0) {
                        $check_query .= " AND id != $id";
                    }
                    $check_result = mysqli_query($connection, $check_query);
                    if (mysqli_num_rows($check_result) > 0) {
                        throw new Exception('A program with this code already exists in this department');
                    }
                    
                    if ($_POST['action'] === 'add_program') {
                        if (!mysqli_query($connection, "INSERT INTO program (name, code, department_id) VALUES ('$name', '$code', $department_id)")) {
                            throw new Exception("Failed to add program: " . mysqli_error($connection));
                        }
                        $response = ['success' => true, 'message' => 'Program added successfully.'];
                    } else {
                        if (!mysqli_query($connection, "UPDATE program SET name = '$name', code = '$code', department_id = $department_id WHERE id = $id")) {
                            throw new Exception("Failed to update program: " . mysqli_error($connection));
                        }
                        $response = ['success' => true, 'message' => 'Program updated successfully.'];
                    }
                    break;

                case 'delete_program':
                    if (!isset($_POST['program_id']) || empty($_POST['program_id'])) {
                        throw new Exception('Program ID is required');
                    }
                    $id = (int)$_POST['program_id'];
                    if (!mysqli_query($connection, "DELETE FROM program WHERE id = $id")) {
                        throw new Exception("Failed to delete program: " . mysqli_error($connection));
                    }
                    $response = ['success' => true, 'message' => 'Program deleted successfully.'];
                    break;

                case 'add_intake':
                case 'edit_intake':
                    if (!isset($_POST['year']) || empty($_POST['year'])) {
                        throw new Exception('Year is required');
                    }
                    if (!isset($_POST['month']) || empty($_POST['month'])) {
                        throw new Exception('Month is required');
                    }
                    if (!isset($_POST['program_id']) || empty($_POST['program_id'])) {
                        throw new Exception('Program ID is required');
                    }
                    $year = (int)$_POST['year'];
                    $month = (int)$_POST['month'];
                    $program_id = (int)$_POST['program_id'];
                    $id = isset($_POST['intake_id']) ? (int)$_POST['intake_id'] : 0;
                    
                    // Check for duplicate intake (same year and month) in the same program
                    $check_query = "SELECT id FROM intake WHERE year = $year AND month = $month AND program_id = $program_id";
                    if ($id > 0) {
                        $check_query .= " AND id != $id";
                    }
                    $check_result = mysqli_query($connection, $check_query);
                    if (mysqli_num_rows($check_result) > 0) {
                        throw new Exception('An intake for this month and year already exists in this program');
                    }
                    
                    if ($_POST['action'] === 'add_intake') {
                        // Initialize size to 0 for new intake
                        if (!mysqli_query($connection, "INSERT INTO intake (year, month, program_id, size) VALUES ($year, $month, $program_id, 0)")) {
                            throw new Exception("Failed to add intake: " . mysqli_error($connection));
                        }
                        $response = ['success' => true, 'message' => 'Intake added successfully.'];
                    } else {
                        // When editing intake, we don't modify the size as it's managed by groups
                        if (!mysqli_query($connection, "UPDATE intake SET year = $year, month = $month, program_id = $program_id WHERE id = $id")) {
                            throw new Exception("Failed to update intake: " . mysqli_error($connection));
                        }
                        $response = ['success' => true, 'message' => 'Intake updated successfully.'];
                    }
                    break;

                case 'delete_intake':
                    if (!isset($_POST['intake_id']) || empty($_POST['intake_id'])) {
                        throw new Exception('Intake ID is required');
                    }
                    $id = (int)$_POST['intake_id'];
                    
                    // Start transaction
                    mysqli_begin_transaction($connection);
                    try {
                        // Delete all associated student groups first
                        if (!mysqli_query($connection, "DELETE FROM student_group WHERE intake_id = $id")) {
                            throw new Exception("Failed to delete associated student groups: " . mysqli_error($connection));
                        }
                        
                        // Then delete the intake
                        if (!mysqli_query($connection, "DELETE FROM intake WHERE id = $id")) {
                            throw new Exception("Failed to delete intake: " . mysqli_error($connection));
                        }
                        
                        // Commit transaction
                        mysqli_commit($connection);
                        $response = ['success' => true, 'message' => 'Intake deleted successfully.'];
                    } catch (Exception $e) {
                        // Rollback transaction on error
                        mysqli_rollback($connection);
                        throw $e;
                    }
                    break;

                case 'add_student_group':
                case 'edit_student_group':
                    if (!isset($_POST['group_name']) || empty($_POST['group_name'])) {
                        throw new Exception('Group name is required');
                    }
                    if (!isset($_POST['group_size']) || empty($_POST['group_size'])) {
                        throw new Exception('Group size is required');
                    }
                    if (!isset($_POST['intake_id']) || empty($_POST['intake_id'])) {
                        throw new Exception('Intake ID is required');
                    }
                    $name = mysqli_real_escape_string($connection, $_POST['group_name']);
                    $size = (int)$_POST['group_size'];
                    $intake_id = (int)$_POST['intake_id'];
                    $id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;
                    
                    // Check for duplicate group name in the same intake
                    $check_query = "SELECT id FROM student_group WHERE LOWER(name) = LOWER('$name') AND intake_id = $intake_id";
                    if ($id > 0) {
                        $check_query .= " AND id != $id";
                    }
                    $check_result = mysqli_query($connection, $check_query);
                    if (mysqli_num_rows($check_result) > 0) {
                        throw new Exception('A student group with this name already exists in this intake');
                    }
                    
                    // Start transaction
                    mysqli_begin_transaction($connection);
                    try {
                        if ($_POST['action'] === 'add_student_group') {
                            // Add the new group
                            if (!mysqli_query($connection, "INSERT INTO student_group (name, size, intake_id) VALUES ('$name', $size, $intake_id)")) {
                                throw new Exception("Failed to add student group: " . mysqli_error($connection));
                            }
                            
                            // Update intake size
                            if (!mysqli_query($connection, "UPDATE intake SET size = COALESCE(size, 0) + $size WHERE id = $intake_id")) {
                                throw new Exception("Failed to update intake size: " . mysqli_error($connection));
                            }
                            
                            $response = ['success' => true, 'message' => 'Student group added successfully.'];
                        } else {
                            // Get the old group size
                            $old_size_query = "SELECT size FROM student_group WHERE id = $id";
                            $old_size_result = mysqli_query($connection, $old_size_query);
                            if (!$old_size_result || !($old_size = mysqli_fetch_assoc($old_size_result))) {
                                throw new Exception("Failed to get old group size");
                            }
                            $old_size = (int)$old_size['size'];
                            
                            // Update the group
                            if (!mysqli_query($connection, "UPDATE student_group SET name = '$name', size = $size, intake_id = $intake_id WHERE id = $id")) {
                                throw new Exception("Failed to update student group: " . mysqli_error($connection));
                            }
                            
                            // Update intake size (subtract old size and add new size)
                            if (!mysqli_query($connection, "UPDATE intake SET size = COALESCE(size, 0) - $old_size + $size WHERE id = $intake_id")) {
                                throw new Exception("Failed to update intake size: " . mysqli_error($connection));
                            }
                            
                            $response = ['success' => true, 'message' => 'Student group updated successfully.'];
                        }
                        
                        // Commit transaction
                        mysqli_commit($connection);
                    } catch (Exception $e) {
                        // Rollback transaction on error
                        mysqli_rollback($connection);
                        throw $e;
                    }
                    break;

                case 'delete_student_group':
                    if (!isset($_POST['group_id']) || empty($_POST['group_id'])) {
                        throw new Exception('Group ID is required');
                    }
                    $id = (int)$_POST['group_id'];
                    
                    // Start transaction
                    mysqli_begin_transaction($connection);
                    try {
                        // Get the group size and intake_id before deleting
                        $group_query = "SELECT size, intake_id FROM student_group WHERE id = $id";
                        $group_result = mysqli_query($connection, $group_query);
                        if (!$group_result || !($group = mysqli_fetch_assoc($group_result))) {
                            throw new Exception("Failed to get group information");
                        }
                        $size = (int)$group['size'];
                        $intake_id = (int)$group['intake_id'];
                        
                        // Delete the group
                        if (!mysqli_query($connection, "DELETE FROM student_group WHERE id = $id")) {
                            throw new Exception("Failed to delete student group: " . mysqli_error($connection));
                        }
                        
                        // Update intake size
                        if (!mysqli_query($connection, "UPDATE intake SET size = COALESCE(size, 0) - $size WHERE id = $intake_id")) {
                            throw new Exception("Failed to update intake size: " . mysqli_error($connection));
                        }
                        
                        // Commit transaction
                        mysqli_commit($connection);
                        $response = ['success' => true, 'message' => 'Student group deleted successfully.'];
                    } catch (Exception $e) {
                        // Rollback transaction on error
                        mysqli_rollback($connection);
                        throw $e;
                    }
                    break;

                default:
                    throw new Exception("Invalid action specified: " . $_POST['action']);
            }
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }
        
        echo json_encode($response);
        exit;
    }
}

if($role === 'warefare'){       
    // Get campuses for warefare role - only their assigned campus
    $campuses_query = mysqli_query($connection, "SELECT * FROM campus WHERE id = $mycampus ORDER BY name");
} else {
    // Get all campuses for other roles
    $campuses_query = mysqli_query($connection, "SELECT * FROM campus ORDER BY name");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    <title>UR-TIMETABLE</title>
    <link href="assets/img/icon1.png" rel="icon">
    <link href="assets/img/icon1.png" rel="apple-touch-icon">
    
    <!-- Include your existing CSS files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>
    <?php
    include("./includes/header.php");
    include("./includes/menu.php");
    ?>

    <main id="main" class="main">
        <div class="pagetitle">
            <h1>Manage Structure</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item active">Structure Management</li>
                </ol>
            </nav>
        </div>

        <section class="section">
            <div class="row">
                <div class="col-lg-12">
                    <!-- Campus Section -->
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3 p-2">
                                <h5 class="card-title">
                                    <i class="bi bi-building me-2"></i>Campuses
                                </h5>
                                <?php if($role !== 'warefare'): ?>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCampusModal">
                                    <i class="bi bi-plus-circle me-1"></i>Add Campus
                                </button>
                                <?php endif; ?>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-hover table-borderless">
                                    <thead class="">
                                      
                                        <tr style="border-bottom:0.3px solid #012970;">    
                                            <th>No.</th>
                                            <th>Name</th>
                                            <th class="text-center"></th>
                                        </tr>
                                    </thead>
                                  
                                    <tbody>
                                 
                                        <?php 
                                        $hasCampuses = false;
                                        $counter = 1;
                                        while ($campus = mysqli_fetch_assoc($campuses_query)): 
                                            $hasCampuses = true;
                                        ?>
                                        
                                        <tr>
                                            <td><?php echo $counter++; ?></td>
                                            <td><?php echo htmlspecialchars($campus['name']); ?></td>
                                            <td class="text-center">
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-primary" onclick="editCampus(<?php echo $campus['id']; ?>, '<?php echo htmlspecialchars($campus['name']); ?>')">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                    <?php if($role !== 'warefare'): ?>
                                                    <button class="btn btn-sm btn-danger" onclick="deleteCampus(<?php echo $campus['id']; ?>)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-sm btn-info" onclick="showColleges(<?php echo $campus['id']; ?>)">
                                                        <i class="bi bi-building"></i> View Colleges
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; 
                                        if (!$hasCampuses): ?>
                                        <tr>
                                            <td colspan="3" class="text-center py-5">
                                                <i class="bi bi-building text-muted" style="font-size: 3rem;"></i>
                                                <h4 class="mt-3">No Campuses Found</h4>
                                                <p class="text-muted">There are no campuses in the system yet.</p>
                                                <?php if($role !== 'warefare'): ?>
                                                <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#addCampusModal">
                                                    <i class="bi bi-plus-circle me-1"></i>Add First Campus
                                                </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Colleges Section (Initially Hidden) -->
                    <div id="collegesSection" class="card mt-4" style="display: none;">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3 p-2">
                                <h5 class="card-title">
                                    <i class="bi bi-building me-2"></i>Colleges
                                </h5>
                                <button type="button" class="btn btn-primary" onclick="showAddCollegeModal(currentCampusId)">
                                    <i class="bi bi-plus-circle me-1"></i>Add College
                                </button>
                            </div>
                            
                            <div id="collegesTable" class="table-responsive">
                                <!-- Colleges will be loaded here dynamically -->
                            </div>
                        </div>
                    </div>

                    <!-- Schools Section -->
                    <div id="schoolsSection" class="card mt-4" style="display: none;">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3 p-2">
                                <h5 class="card-title">
                                    <i class="bi bi-building me-2"></i>Schools
                                </h5>
                                <button type="button" class="btn btn-primary" onclick="showAddSchoolModal(currentCollegeId)">
                                    <i class="bi bi-plus-circle me-1"></i>Add School
                                </button>
                            </div>
                            
                            <div id="schoolsTable" class="table-responsive">
                                <!-- Schools will be loaded here dynamically -->
                            </div>
                        </div>
                    </div>

                    <!-- Departments Section -->
                    <div id="departmentsSection" class="card mt-4" style="display: none;">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3 p-2">
                                <h5 class="card-title">
                                    <i class="bi bi-building me-2"></i>Departments
                                </h5>
                                <button type="button" class="btn btn-primary" onclick="showAddDepartmentModal(currentSchoolId)">
                                    <i class="bi bi-plus-circle me-1"></i>Add Department
                                </button>
                            </div>
                            
                            <div id="departmentsTable" class="table-responsive">
                                <!-- Departments will be loaded here dynamically -->
                            </div>
                        </div>
                    </div>

                    <!-- Programs Section -->
                    <div id="programsSection" class="card mt-4" style="display: none;">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3 p-2">
                                <h5 class="card-title">
                                    <i class="bi bi-book me-2"></i>Programs
                                </h5>
                                <button type="button" class="btn btn-primary" onclick="showAddProgramModal(currentDepartmentId)">
                                    <i class="bi bi-plus-circle me-1"></i>Add Program
                                </button>
                            </div>
                            
                            <div id="programsTable" class="table-responsive">
                                <!-- Programs will be loaded here dynamically -->
                            </div>
                        </div>
                    </div>

                    <!-- Intakes Section -->
                    <div id="intakesSection" class="card mt-4" style="display: none;">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3 p-2">
                                <h5 class="card-title">
                                    <i class="bi bi-calendar me-2"></i>Intakes
                                </h5>
                                <button type="button" class="btn btn-primary" onclick="showAddIntakeModal(currentProgramId)">
                                    <i class="bi bi-plus-circle me-1"></i>Add Intake
                                </button>
                            </div>
                            
                            <div id="intakesTable" class="table-responsive">
                                <!-- Intakes will be loaded here dynamically -->
                            </div>
                        </div>
                    </div>

                    <!-- Student Groups Section -->
                    <div id="studentGroupsSection" class="card mt-4" style="display: none;">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3 p-2">
                                <h5 class="card-title">
                                    <i class="bi bi-people me-2"></i>Student Groups
                                </h5>
                                <button type="button" class="btn btn-primary" onclick="showAddStudentGroupModal(currentIntakeId)">
                                    <i class="bi bi-plus-circle me-1"></i>Add Student Group
                                </button>
                            </div>
                            
                            <div id="studentGroupsTable" class="table-responsive">
                                <!-- Student groups will be loaded here dynamically -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Add Campus Modal -->
        <div class="modal fade" id="addCampusModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">
                            <i class="bi bi-building me-2"></i>Add Campus
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST" id="addCampusForm">
                        <div class="modal-body">
                            <input type="hidden" name="action" value="add_campus">
                            <input type="hidden" name="campus_id" value="">
                            <div class="mb-3">
                                <label class="form-label">Campus Name</label>
                                <input type="text" class="form-control" name="campus_name" required 
                                    minlength="2" maxlength="100" 
                                    pattern="[A-Za-z0-9\s\-]+" 
                                    title="Only letters, numbers, spaces and hyphens are allowed">
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle me-1"></i>Close
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-1"></i>Save Campus
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <style>
        .btn-group .btn {
            margin: 0 2px;
        }
        .table th {
            font-weight: 600;
        }
        .modal-header {
            border-radius: 0.3rem 0.3rem 0 0;
        }
        .card {
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            border: none;
            border-radius: 0.5rem;
        }
        .card-title {
            color: #012970;
            font-weight: 600;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(0,0,0,0.02);
        }
        .btn-primary {
            background-color: #4154f1;
            border-color: #4154f1;
        }
        .btn-primary:hover {
            background-color: #3647d4;
            border-color: #3647d4;
        }
        .btn-info {
            background-color: #0dcaf0;
            border-color: #0dcaf0;
            color: #fff;
        }
        .btn-info:hover {
            background-color: #0bb6d9;
            border-color: #0bb6d9;
            color: #fff;
        }
        /* Add smooth transitions */
        .table-responsive {
            transition: opacity 0.3s ease-in-out;
        }
        .table tbody tr {
            transition: background-color 0.3s ease-in-out;
        }
        .table tbody tr:hover {
            background-color: rgba(0,0,0,0.02);
        }
        /* Add fade animation for table updates */
        .table-update {
            animation: fadeIn 0.3s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>

    <!-- Include your existing JS files -->
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>

    <script>
    // Define global variables at the top of the script
    let currentCampusId = null;
    let currentCollegeId = null;
    let currentSchoolId = null;
    let currentDepartmentId = null;
    let currentProgramId = null;
    let currentIntakeId = null;
    let currentGroupId = null;

    // Add form submit handler for add campus form
    document.addEventListener('DOMContentLoaded', function() {
        const addCampusForm = document.getElementById('addCampusForm');
        if (addCampusForm) {
            // Add input validation for campus name
            const campusNameInput = addCampusForm.querySelector('input[name="campus_name"]');
            if (campusNameInput) {
                campusNameInput.addEventListener('input', function() {
                    validateCampusName(this);
                });
            }

            addCampusForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const campusNameInput = this.querySelector('input[name="campus_name"]');
                if (!validateCampusName(campusNameInput)) {
                    return;
                }
                handleFormSubmit(this, 'addCampusModal');
            });
        }
    });

    // Function to validate campus name
    function validateCampusName(input) {
        const value = input.value.trim();
        const errorElement = input.nextElementSibling;
        
        // Clear previous validation state
        input.classList.remove('is-invalid', 'is-valid');
        if (errorElement && errorElement.classList.contains('invalid-feedback')) {
            errorElement.textContent = '';
        }
        
        // Check if empty
        if (!value) {
            input.classList.add('is-invalid');
            if (errorElement && errorElement.classList.contains('invalid-feedback')) {
                errorElement.textContent = 'Campus name is required';
            }
            return false;
        }
        
        // Check minimum length
        if (value.length < 2) {
            input.classList.add('is-invalid');
            if (errorElement && errorElement.classList.contains('invalid-feedback')) {
                errorElement.textContent = 'Campus name must be at least 2 characters long';
            }
            return false;
        }
        
        // Check maximum length
        if (value.length > 100) {
            input.classList.add('is-invalid');
            if (errorElement && errorElement.classList.contains('invalid-feedback')) {
                errorElement.textContent = 'Campus name must not exceed 100 characters';
            }
            return false;
        }
        
        // If all validations pass
        input.classList.add('is-valid');
        return true;
    }

    // Function to handle form submissions with real-time updates
    async function handleFormSubmit(form, modalId) {
        const submitButton = form.querySelector('button[type="submit"]');
        if (!submitButton) {
            showAlert('Error!', 'Submit button not found in form', 'danger');
            return;
        }

        const originalText = submitButton.innerHTML;
        
        try {
            const formData = new FormData(form);
            const action = formData.get('action');
            
            // Special handling for campus operations
            if (action === 'add_campus' || action === 'edit_campus') {
                submitButton.disabled = true;
                submitButton.innerHTML = `
                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                    Processing...
                `;
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('Success!', result.message, 'success');
                    // Close the modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById(modalId));
                    if (modal) {
                        modal.hide();
                    }
                    // Refresh the page
                    location.reload();
                } else {
                    showAlert('Error!', result.message, 'danger');
                }
                return;
            }
            
            if (action === 'add_college' && currentCampusId) {
                formData.set('campus_id', currentCampusId);
            } else if (action === 'add_school' && currentCollegeId) {
                formData.set('college_id', currentCollegeId);
            } else if (action === 'add_department' && currentSchoolId) {
                formData.set('school_id', currentSchoolId);
            } else if (action === 'add_program' && currentDepartmentId) {
                formData.set('department_id', currentDepartmentId);
            } else if (action === 'add_intake' && currentProgramId) {
                formData.set('program_id', currentProgramId);
            } else if (action === 'add_student_group' && currentIntakeId) {
                formData.set('intake_id', currentIntakeId);
            }
            
            submitButton.disabled = true;
            submitButton.innerHTML = `
                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                Processing...
            `;
            
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Server response:', text);
                try {
                    const result = JSON.parse(text);
                    result.action = action;
                    await handleResponse(result, modalId);
                } catch (e) {
                    throw new Error('Server returned invalid response format. Please try again.');
                }
            } else {
                const result = await response.json();
                result.action = action;
                await handleResponse(result, modalId);
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('Error!', 'An error occurred while processing your request. Please try again.', 'danger');
        } finally {
            submitButton.disabled = false;
            submitButton.innerHTML = originalText;
        }
    }

    // Function to handle the response and update tables in real-time
    async function handleResponse(result, modalId) {
        if (result.success) {
            showAlert('Success!', result.message, 'success');
            
            const modal = bootstrap.Modal.getInstance(document.getElementById(modalId));
            if (modal) {
                modal.hide();
            }
            
            const action = result.action || '';
            
            // Update the appropriate table based on the action
            if (action.includes('campus')) {
                // Refresh the page for campus operations
                location.reload();
            } else if (action.includes('college')) {
                await showColleges(currentCampusId);
            } else if (action.includes('school')) {
                await showSchools(currentCollegeId);
            } else if (action.includes('department')) {
                await showDepartments(currentSchoolId);
            } else if (action.includes('program')) {
                await showPrograms(currentDepartmentId);
            } else if (action.includes('intake')) {
                await showIntakes(currentProgramId);
            } else if (action.includes('student_group')) {
                await showStudentGroups(currentIntakeId);
            }
        } else {
            showAlert('Error!', result.message || 'An error occurred', 'danger');
        }
    }

    // Function to refresh the campus table with real-time updates
    async function refreshCampusTable() {
        try {
            const response = await fetch('get_campuses.php');
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            const html = await response.text();
            
            // Update the table with animation
            const tableContainer = document.querySelector('.table-responsive');
            if (tableContainer) {
                tableContainer.style.opacity = '0';
                setTimeout(() => {
                    tableContainer.innerHTML = html;
                    tableContainer.style.opacity = '1';
                }, 300);
            }
        } catch (error) {
            console.error('Error refreshing campus table:', error);
            showAlert('Error!', 'Failed to refresh campus list. Please try again.', 'danger');
        }
    }

    // Function to show alert messages with better styling
    function showAlert(title, message, type) {
        try {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
            alertDiv.style.zIndex = '9999';
            alertDiv.style.minWidth = '300px';
            alertDiv.style.maxWidth = '500px';
            alertDiv.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
            
            const icon = type === 'success' ? 'check-circle' : 'exclamation-triangle';
            
            alertDiv.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="bi bi-${icon} me-2" style="font-size: 1.5rem;"></i>
                    <div>
                        <strong>${title}</strong>
                        <p class="mb-0">${message}</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(alertDiv);
            
            // Auto dismiss after 5 seconds
            setTimeout(() => {
                alertDiv.classList.remove('show');
                setTimeout(() => alertDiv.remove(), 150);
            }, 5000);
        } catch (error) {
            console.error('Error showing alert:', error);
            // Fallback to basic alert if the styled alert fails
            alert(`${title}: ${message}`);
        }
    }

    // Function to handle image load errors
    function handleImageLoad(img) {
        if (img) {
            img.onerror = function() {
                this.src = 'assets/img/default-image.png';
            };
        }
    }

    // Function to clear sections below a specific section
    function clearSectionsBelow(sectionId) {
        const sections = [
            'collegesSection',
            'schoolsSection',
            'departmentsSection',
            'programsSection',
            'intakesSection',
            'studentGroupsSection'
        ];
        
        const startIndex = sections.indexOf(sectionId);
        if (startIndex === -1) return;
        
        // Clear all sections after the current one
        for (let i = startIndex + 1; i < sections.length; i++) {
            const element = document.getElementById(sections[i]);
            if (element) {
                element.style.display = 'none';
                // Clear the content of the section
                const tableElement = element.querySelector('.table-responsive');
                if (tableElement) {
                    tableElement.innerHTML = '';
                }
            }
        }
    }

    // Function to show colleges for a campus
    function showColleges(campusId) {
        currentCampusId = campusId;
        currentCollegeId = null; // Reset college ID when showing colleges
        clearSectionsBelow('collegesSection');
        
        // Get campus name for the title
        fetch(`get_campus_name.php?campus_id=${campusId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Invalid JSON response:', text);
                        return { success: false, message: 'Invalid response format' };
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    document.querySelector('#collegesSection .card-title').innerHTML = 
                        `<i class="bi bi-building me-2"></i>Colleges in ${data.campus_name}`;
                } else {
                    document.querySelector('#collegesSection .card-title').innerHTML = 
                        `<i class="bi bi-building me-2"></i>Colleges`;
                    console.error('Error getting campus name:', data.message);
                }
            })
            .catch(error => {
                console.error('Error fetching campus name:', error);
                document.querySelector('#collegesSection .card-title').innerHTML = 
                    `<i class="bi bi-building me-2"></i>Colleges`;
            });
        
        // Show loading state
        document.getElementById('collegesTable').innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading colleges...</p>
            </div>
        `;
        document.getElementById('collegesSection').style.display = 'block';
        
        fetch(`get_colleges.php?campus_id=${campusId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(html => {
                document.getElementById('collegesTable').innerHTML = html;
            })
            .catch(error => {
                console.error('Error fetching colleges:', error);
                document.getElementById('collegesTable').innerHTML = `
                    <div class="alert alert-danger m-3">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Error loading colleges. Please try again.
                        <button class="btn btn-sm btn-outline-danger ms-3" onclick="showColleges(${campusId})">
                            <i class="bi bi-arrow-clockwise me-1"></i>Retry
                        </button>
                    </div>
                `;
            });
    }

    // Function to show add college modal
    function showAddCollegeModal(campusId) {
        currentCampusId = campusId;
        
        // Create and show the modal
        const modalHtml = `
            <div class="modal fade" id="addCollegeModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title">
                                <i class="bi bi-building me-2"></i>Add College
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="add_college">
                                <input type="hidden" name="campus_id" value="${campusId}">
                                <div class="mb-3">
                                    <label class="form-label">College Name</label>
                                    <input type="text" class="form-control" name="college_name" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="bi bi-x-circle me-1"></i>Close
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i>Save College
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        const existingModal = document.getElementById('addCollegeModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Add new modal to body
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Show the modal
        const modal = new bootstrap.Modal(document.getElementById('addCollegeModal'));
            modal.show();
        
        // Add form submit handler
        document.querySelector('#addCollegeModal form').addEventListener('submit', function(e) {
            e.preventDefault();
            handleFormSubmit(this, 'addCollegeModal');
        });
    }

    // Function to edit college
    function editCollege(id, name) {
        currentCollegeId = id;
        
        // Create and show the modal
        const modalHtml = `
            <div class="modal fade" id="editCollegeModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title">
                                <i class="bi bi-building me-2"></i>Edit College
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="edit_college">
                                <input type="hidden" name="college_id" value="${id}">
                                <input type="hidden" name="campus_id" value="${currentCampusId}">
                                <div class="mb-3">
                                    <label class="form-label">College Name</label>
                                    <input type="text" class="form-control" name="college_name" value="${name}" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="bi bi-x-circle me-1"></i>Close
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i>Update College
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        const existingModal = document.getElementById('editCollegeModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Add new modal to body
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Show the modal
        const modal = new bootstrap.Modal(document.getElementById('editCollegeModal'));
        modal.show();
        
        // Add form submit handler
        document.querySelector('#editCollegeModal form').addEventListener('submit', function(e) {
            e.preventDefault();
            handleFormSubmit(this, 'editCollegeModal');
        });
    }

    // Function to delete college
    async function deleteCollege(id) {
            if (confirm('Are you sure you want to delete this campus? This will also delete all associated colleges, schools, departments, and programs.')) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'delete_campus');
                    formData.append('campus_id', id);
                    
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        location.reload();
                        showAlert('Success!', result.message, 'success');
                    } else {
                        showAlert('Error!', result.message, 'danger');
                    }
                } catch (error) {
                    showAlert('Error!', 'Failed to delete campus.', 'danger');
                }
            }
        }

    // Function to show schools for a college
    function showSchools(collegeId) {
        currentCollegeId = collegeId;
        currentSchoolId = null; // Reset school ID when showing schools
        clearSectionsBelow('schoolsSection');
        
        // Get college name for the title
        fetch(`get_college_name.php?college_id=${collegeId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Invalid JSON response:', text);
                        return { success: false, message: 'Invalid response format' };
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    document.querySelector('#schoolsSection .card-title').innerHTML = 
                        `<i class="bi bi-building me-2"></i>Schools in ${data.college_name}`;
                } else {
                    document.querySelector('#schoolsSection .card-title').innerHTML = 
                        `<i class="bi bi-building me-2"></i>Schools`;
                    console.error('Error getting college name:', data.message);
                }
            })
            .catch(error => {
                console.error('Error fetching college name:', error);
                document.querySelector('#schoolsSection .card-title').innerHTML = 
                    `<i class="bi bi-building me-2"></i>Schools`;
            });
        
        // Show loading state
        document.getElementById('schoolsTable').innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading schools...</p>
            </div>
        `;
        document.getElementById('schoolsSection').style.display = 'block';
        
        fetch(`get_schools.php?college_id=${collegeId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(html => {
                document.getElementById('schoolsTable').innerHTML = html;
            })
            .catch(error => {
                console.error('Error fetching schools:', error);
                document.getElementById('schoolsTable').innerHTML = `
                    <div class="alert alert-danger m-3">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Error loading schools. Please try again.
                        <button class="btn btn-sm btn-outline-danger ms-3" onclick="showSchools(${collegeId})">
                            <i class="bi bi-arrow-clockwise me-1"></i>Retry
                        </button>
                    </div>
                `;
            });
    }

    // Function to show add school modal
    function showAddSchoolModal(collegeId) {
        currentCollegeId = collegeId;
        
        // Create and show the modal
        const modalHtml = `
            <div class="modal fade" id="addSchoolModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title">
                                <i class="bi bi-building me-2"></i>Add School
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="add_school">
                                <input type="hidden" name="college_id" value="${collegeId}">
                                <div class="mb-3">
                                    <label class="form-label">School Name</label>
                                    <input type="text" class="form-control" name="school_name" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="bi bi-x-circle me-1"></i>Close
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i>Save School
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        const existingModal = document.getElementById('addSchoolModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Add new modal to body
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Show the modal
        const modal = new bootstrap.Modal(document.getElementById('addSchoolModal'));
        modal.show();
        
        // Add form submit handler
        document.querySelector('#addSchoolModal form').addEventListener('submit', function(e) {
                e.preventDefault();
            handleFormSubmit(this, 'addSchoolModal');
        });
    }

    // Function to edit school
    function editSchool(id, name) {
        currentSchoolId = id;
        
        // Create and show the modal
        const modalHtml = `
            <div class="modal fade" id="editSchoolModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title">
                                <i class="bi bi-building me-2"></i>Edit School
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="edit_school">
                                <input type="hidden" name="school_id" value="${id}">
                                <input type="hidden" name="college_id" value="${currentCollegeId}">
                                <div class="mb-3">
                                    <label class="form-label">School Name</label>
                                    <input type="text" class="form-control" name="school_name" value="${name}" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="bi bi-x-circle me-1"></i>Close
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i>Update School
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        const existingModal = document.getElementById('editSchoolModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Add new modal to body
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Show the modal
        const modal = new bootstrap.Modal(document.getElementById('editSchoolModal'));
        modal.show();
        
        // Add form submit handler
        document.querySelector('#editSchoolModal form').addEventListener('submit', function(e) {
            e.preventDefault();
            handleFormSubmit(this, 'editSchoolModal');
        });
    }

    // Function to delete school
    async function deleteSchool(id) {
        if (confirm('Are you sure you want to delete this school? This will also delete all associated departments, programs, and student groups.')) {
            try {
                const formData = new FormData();
                formData.append('action', 'delete_school');
                formData.append('school_id', id);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showSchools(currentCollegeId);
                    showAlert('Success!', result.message, 'success');
                } else {
                    showAlert('Error!', result.message, 'danger');
                }
            } catch (error) {
                showAlert('Error!', 'Failed to delete school.', 'danger');
            }
        }
    }

    // Function to show departments for a school
    function showDepartments(schoolId) {
        currentSchoolId = schoolId;
        currentDepartmentId = null; // Reset department ID when showing departments
        clearSectionsBelow('departmentsSection');
        
        // Get school name for the title
        fetch(`get_school_name.php?school_id=${schoolId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Invalid JSON response:', text);
                        return { success: false, message: 'Invalid response format' };
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    document.querySelector('#departmentsSection .card-title').innerHTML = 
                        `<i class="bi bi-building me-2"></i>Departments in ${data.school_name}`;
                } else {
                    document.querySelector('#departmentsSection .card-title').innerHTML = 
                        `<i class="bi bi-building me-2"></i>Departments`;
                    console.error('Error getting school name:', data.message);
                }
            })
            .catch(error => {
                console.error('Error fetching school name:', error);
                document.querySelector('#departmentsSection .card-title').innerHTML = 
                    `<i class="bi bi-building me-2"></i>Departments`;
            });
        
        // Show loading state
        document.getElementById('departmentsTable').innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading departments...</p>
            </div>
        `;
        document.getElementById('departmentsSection').style.display = 'block';
        
        fetch(`get_departments.php?school_id=${schoolId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(html => {
                document.getElementById('departmentsTable').innerHTML = html;
            })
            .catch(error => {
                console.error('Error fetching departments:', error);
                document.getElementById('departmentsTable').innerHTML = `
                    <div class="alert alert-danger m-3">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Error loading departments. Please try again.
                        <button class="btn btn-sm btn-outline-danger ms-3" onclick="showDepartments(${schoolId})">
                            <i class="bi bi-arrow-clockwise me-1"></i>Retry
                        </button>
                    </div>
                `;
            });
    }

    // Function to show add department modal
    function showAddDepartmentModal(schoolId) {
        currentSchoolId = schoolId;
        
        // Create and show the modal
        const modalHtml = `
            <div class="modal fade" id="addDepartmentModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title">
                                <i class="bi bi-building me-2"></i>Add Department
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST" id="addDepartmentForm">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="add_department">
                                <input type="hidden" name="school_id" value="${schoolId}">
                                <div class="mb-3">
                                    <label class="form-label">Department Name</label>
                                    <input type="text" class="form-control" name="department_name" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="bi bi-x-circle me-1"></i>Close
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i>Save Department
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        const existingModal = document.getElementById('addDepartmentModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Add new modal to body
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Show the modal
        const modal = new bootstrap.Modal(document.getElementById('addDepartmentModal'));
        modal.show();
        
        // Add form submit handler
        const form = document.getElementById('addDepartmentForm');
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            try {
                const formData = new FormData(this);
                const submitButton = this.querySelector('button[type="submit"]');
                const originalText = submitButton.innerHTML;
                
                // Show loading state
                submitButton.disabled = true;
                submitButton.innerHTML = `
                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                    Processing...
                `;
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                
                if (result.success) {
                    // Close the modal
                    modal.hide();
                    
                    // Refresh the departments list
                    await showDepartments(schoolId);
                    
                    // Show success message
                    showAlert('Success!', result.message, 'success');
                } else {
                    showAlert('Error!', result.message || 'An error occurred', 'danger');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('Error!', 'An error occurred while processing your request. Please try again.', 'danger');
            } finally {
                // Reset button state
                const submitButton = form.querySelector('button[type="submit"]');
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalText;
                }
            }
        });
    }

    // Function to edit department
    function editDepartment(id, name) {
        currentDepartmentId = id;
        
        // Create and show the modal
        const modalHtml = `
            <div class="modal fade" id="editDepartmentModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title">
                                <i class="bi bi-building me-2"></i>Edit Department
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="edit_department">
                                <input type="hidden" name="department_id" value="${id}">
                                <input type="hidden" name="school_id" value="${currentSchoolId}">
                                <div class="mb-3">
                                    <label class="form-label">Department Name</label>
                                    <input type="text" class="form-control" name="department_name" value="${name}" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="bi bi-x-circle me-1"></i>Close
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i>Update Department
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        const existingModal = document.getElementById('editDepartmentModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Add new modal to body
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Show the modal
        const modal = new bootstrap.Modal(document.getElementById('editDepartmentModal'));
        modal.show();
        
        // Add form submit handler
        document.querySelector('#editDepartmentModal form').addEventListener('submit', function(e) {
            e.preventDefault();
            handleFormSubmit(this, 'editDepartmentModal');
        });
    }

    // Function to delete department
    async function deleteDepartment(id) {
        if (confirm('Are you sure you want to delete this department? This will also delete all associated programs, intakes, and student groups.')) {
            try {
                const formData = new FormData();
                formData.append('action', 'delete_department');
                formData.append('department_id', id);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showDepartments(currentSchoolId);
                    showAlert('Success!', result.message, 'success');
                } else {
                    showAlert('Error!', result.message, 'danger');
                }
            } catch (error) {
                showAlert('Error!', 'Failed to delete department.', 'danger');
            }
        }
    }

    // Function to show programs for a department
    function showPrograms(departmentId) {
        if (currentDepartmentId !== departmentId) {
            currentDepartmentId = departmentId;
            clearSectionsBelow('programsSection');
        }
        
        // Get department name for the title
        fetch(`get_department_name.php?department_id=${departmentId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Invalid JSON response:', text);
                        return { success: false, message: 'Invalid response format' };
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    document.querySelector('#programsSection .card-title').innerHTML = 
                        `<i class="bi bi-book me-2"></i>Programs in ${data.department_name}`;
                } else {
                    document.querySelector('#programsSection .card-title').innerHTML = 
                        `<i class="bi bi-book me-2"></i>Programs`;
                    console.error('Error getting department name:', data.message);
                }
            })
            .catch(error => {
                console.error('Error fetching department name:', error);
                document.querySelector('#programsSection .card-title').innerHTML = 
                    `<i class="bi bi-book me-2"></i>Programs`;
            });
        
        // Show loading state
        document.getElementById('programsTable').innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading programs...</p>
            </div>
        `;
        document.getElementById('programsSection').style.display = 'block';
        
        fetch(`get_programs.php?department_id=${departmentId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(html => {
                document.getElementById('programsTable').innerHTML = html;
            })
            .catch(error => {
                console.error('Error fetching programs:', error);
                document.getElementById('programsTable').innerHTML = `
                    <div class="alert alert-danger m-3">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Error loading programs. Please try again.
                        <button class="btn btn-sm btn-outline-danger ms-3" onclick="showPrograms(${departmentId})">
                            <i class="bi bi-arrow-clockwise me-1"></i>Retry
                        </button>
                    </div>
                `;
            });
    }

    // Function to show add program modal
    function showAddProgramModal(departmentId) {
        currentDepartmentId = departmentId;
        
        // Create and show the modal
        const modalHtml = `
            <div class="modal fade" id="addProgramModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title">
                                <i class="bi bi-book me-2"></i>Add Program
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="add_program">
                                <input type="hidden" name="department_id" value="${departmentId}">
                                <div class="mb-3">
                                    <label class="form-label">Program Name</label>
                                    <input type="text" class="form-control" name="program_name" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Program Code</label>
                                    <input type="text" class="form-control" name="program_code" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="bi bi-x-circle me-1"></i>Close
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i>Save Program
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        const existingModal = document.getElementById('addProgramModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Add new modal to body
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Show the modal
        const modal = new bootstrap.Modal(document.getElementById('addProgramModal'));
        modal.show();
        
        // Add form submit handler
        document.querySelector('#addProgramModal form').addEventListener('submit', function(e) {
            e.preventDefault();
            handleFormSubmit(this, 'addProgramModal');
        });
    }

    // Function to edit program
    function editProgram(id, name, code) {
        currentProgramId = id;
        
        // Create and show the modal
        const modalHtml = `
            <div class="modal fade" id="editProgramModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title">
                                <i class="bi bi-book me-2"></i>Edit Program
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="edit_program">
                                <input type="hidden" name="program_id" value="${id}">
                                <input type="hidden" name="department_id" value="${currentDepartmentId}">
                                <div class="mb-3">
                                    <label class="form-label">Program Name</label>
                                    <input type="text" class="form-control" name="program_name" value="${name}" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Program Code</label>
                                    <input type="text" class="form-control" name="program_code" value="${code}" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="bi bi-x-circle me-1"></i>Close
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i>Update Program
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        const existingModal = document.getElementById('editProgramModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Add new modal to body
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Show the modal
        const modal = new bootstrap.Modal(document.getElementById('editProgramModal'));
        modal.show();
        
        // Add form submit handler
        document.querySelector('#editProgramModal form').addEventListener('submit', function(e) {
            e.preventDefault();
            handleFormSubmit(this, 'editProgramModal');
        });
    }

    // Function to delete program
    async function deleteProgram(id) {
        if (confirm('Are you sure you want to delete this program? This will also delete all associated intakes and student groups.')) {
            try {
                const formData = new FormData();
                formData.append('action', 'delete_program');
                formData.append('program_id', id);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showPrograms(currentDepartmentId);
                    showAlert('Success!', result.message, 'success');
                } else {
                    showAlert('Error!', result.message, 'danger');
                }
            } catch (error) {
                showAlert('Error!', 'Failed to delete program.', 'danger');
            }
        }
    }

    // Function to show intakes for a program
    function showIntakes(programId) {
        if (currentProgramId !== programId) {
            currentProgramId = programId;
            clearSectionsBelow('intakesSection');
        }
        
        // Get program name for the title
        fetch(`get_program_name.php?program_id=${programId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Invalid JSON response:', text);
                        return { success: false, message: 'Invalid response format' };
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    document.querySelector('#intakesSection .card-title').innerHTML = 
                        `<i class="bi bi-calendar me-2"></i>Intakes for ${data.program_name}`;
                } else {
                    document.querySelector('#intakesSection .card-title').innerHTML = 
                        `<i class="bi bi-calendar me-2"></i>Intakes`;
                    console.error('Error getting program name:', data.message);
                }
            })
            .catch(error => {
                console.error('Error fetching program name:', error);
                document.querySelector('#intakesSection .card-title').innerHTML = 
                    `<i class="bi bi-calendar me-2"></i>Intakes`;
            });
        
        // Show loading state
        document.getElementById('intakesTable').innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading intakes...</p>
            </div>
        `;
        document.getElementById('intakesSection').style.display = 'block';
        
        fetch(`get_intakes.php?program_id=${programId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(html => {
                document.getElementById('intakesTable').innerHTML = html;
            })
            .catch(error => {
                console.error('Error fetching intakes:', error);
                document.getElementById('intakesTable').innerHTML = `
                    <div class="alert alert-danger m-3">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Error loading intakes. Please try again.
                        <button class="btn btn-sm btn-outline-danger ms-3" onclick="showIntakes(${programId})">
                            <i class="bi bi-arrow-clockwise me-1"></i>Retry
                        </button>
                    </div>
                `;
            });
    }

    // Function to show add intake modal
    function showAddIntakeModal(programId) {
        currentProgramId = programId;
        
        // Create and show the modal
        const modalHtml = `
            <div class="modal fade" id="addIntakeModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title">
                                <i class="bi bi-calendar me-2"></i>Add Intake
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="add_intake">
                                <input type="hidden" name="program_id" value="${programId}">
                                <div class="mb-3">
                                    <label class="form-label">Year</label>
                                    <input type="number" class="form-control" name="year" min="2000" max="2100" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Month</label>
                                    <select class="form-select" name="month" required>
                                        <option value="">Select Month</option>
                                        <option value="1">January</option>
                                        <option value="2">February</option>
                                        <option value="3">March</option>
                                        <option value="4">April</option>
                                        <option value="5">May</option>
                                        <option value="6">June</option>
                                        <option value="7">July</option>
                                        <option value="8">August</option>
                                        <option value="9">September</option>
                                        <option value="10">October</option>
                                        <option value="11">November</option>
                                        <option value="12">December</option>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="bi bi-x-circle me-1"></i>Close
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i>Save Intake
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        const existingModal = document.getElementById('addIntakeModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Add new modal to body
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Show the modal
        const modal = new bootstrap.Modal(document.getElementById('addIntakeModal'));
        modal.show();
        
        // Add form submit handler
        document.querySelector('#addIntakeModal form').addEventListener('submit', function(e) {
            e.preventDefault();
            handleFormSubmit(this, 'addIntakeModal');
        });
    }

    // Function to edit intake
    function editIntake(id, year, month) {
        currentIntakeId = id;
        
        // Create and show the modal
        const modalHtml = `
            <div class="modal fade" id="editIntakeModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title">
                                <i class="bi bi-calendar me-2"></i>Edit Intake
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="edit_intake">
                                <input type="hidden" name="intake_id" value="${id}">
                                <input type="hidden" name="program_id" value="${currentProgramId}">
                                <div class="mb-3">
                                    <label class="form-label">Year</label>
                                    <input type="number" class="form-control" name="year" min="2000" max="2100" value="${year}" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Month</label>
                                    <select class="form-select" name="month" required>
                                        <option value="">Select Month</option>
                                        <option value="1" ${month === 1 ? 'selected' : ''}>January</option>
                                        <option value="2" ${month === 2 ? 'selected' : ''}>February</option>
                                        <option value="3" ${month === 3 ? 'selected' : ''}>March</option>
                                        <option value="4" ${month === 4 ? 'selected' : ''}>April</option>
                                        <option value="5" ${month === 5 ? 'selected' : ''}>May</option>
                                        <option value="6" ${month === 6 ? 'selected' : ''}>June</option>
                                        <option value="7" ${month === 7 ? 'selected' : ''}>July</option>
                                        <option value="8" ${month === 8 ? 'selected' : ''}>August</option>
                                        <option value="9" ${month === 9 ? 'selected' : ''}>September</option>
                                        <option value="10" ${month === 10 ? 'selected' : ''}>October</option>
                                        <option value="11" ${month === 11 ? 'selected' : ''}>November</option>
                                        <option value="12" ${month === 12 ? 'selected' : ''}>December</option>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="bi bi-x-circle me-1"></i>Close
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i>Update Intake
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        const existingModal = document.getElementById('editIntakeModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Add new modal to body
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Show the modal
        const modal = new bootstrap.Modal(document.getElementById('editIntakeModal'));
        modal.show();
        
        // Add form submit handler
        document.querySelector('#editIntakeModal form').addEventListener('submit', function(e) {
            e.preventDefault();
            handleFormSubmit(this, 'editIntakeModal');
        });
    }

    // Function to delete intake
    async function deleteIntake(id) {
        if (confirm('Are you sure you want to delete this intake? This will also delete all associated student groups.')) {
            try {
                const formData = new FormData();
                formData.append('action', 'delete_intake');
                formData.append('intake_id', id);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showIntakes(currentProgramId);
                    showAlert('Success!', result.message, 'success');
                } else {
                    showAlert('Error!', result.message, 'danger');
                }
            } catch (error) {
                showAlert('Error!', 'Failed to delete intake.', 'danger');
            }
        }
    }

    // Function to show student groups for an intake
    async function showStudentGroups(intakeId) {
        if (currentIntakeId !== intakeId) {
            currentIntakeId = intakeId;
            clearSectionsBelow('studentGroupsSection');
        }
        
        // Get intake name for the title
        try {
            const response = await fetch(`get_intake_name.php?intake_id=${intakeId}`);
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            const data = await response.json();
            
            if (data.success) {
                document.querySelector('#studentGroupsSection .card-title').innerHTML = 
                    `<i class="bi bi-people me-2"></i>Student Groups for ${data.program_name} (${data.intake_name})`;
            } else {
                document.querySelector('#studentGroupsSection .card-title').innerHTML = 
                    `<i class="bi bi-people me-2"></i>Student Groups`;
                console.error('Error getting intake name:', data.message);
            }
        } catch (error) {
            console.error('Error fetching intake name:', error);
            document.querySelector('#studentGroupsSection .card-title').innerHTML = 
                `<i class="bi bi-people me-2"></i>Student Groups`;
        }
        
        // Show loading state
        document.getElementById('studentGroupsTable').innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading student groups...</p>
            </div>
        `;
        document.getElementById('studentGroupsSection').style.display = 'block';
        
        try {
            const response = await fetch(`get_student_groups.php?intake_id=${intakeId}`);
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            const html = await response.text();
            document.getElementById('studentGroupsTable').innerHTML = html;
        } catch (error) {
            console.error('Error fetching student groups:', error);
            document.getElementById('studentGroupsTable').innerHTML = `
                <div class="alert alert-danger m-3">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Error loading student groups. Please try again.
                    <button class="btn btn-sm btn-outline-danger ms-3" onclick="showStudentGroups(${intakeId})">
                        <i class="bi bi-arrow-clockwise me-1"></i>Retry
                    </button>
                </div>
            `;
        }
    }

    // Function to hide all sections
    function hideAllSections() {
        const sections = [
            'collegesSection',
            'schoolsSection',
            'departmentsSection',
            'programsSection',
            'intakesSection',
            'studentGroupsSection'
        ];
        
        sections.forEach(section => {
            const element = document.getElementById(section);
            if (element) {
                element.style.display = 'none';
            }
        });
    }

    // Function to show add student group modal
    function showAddStudentGroupModal(intakeId) {
        currentIntakeId = intakeId;
        
        // Create and show the modal
        const modalHtml = `
            <div class="modal fade" id="addStudentGroupModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title">
                                <i class="bi bi-people me-2"></i>Add Student Group
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="add_student_group">
                                <input type="hidden" name="intake_id" value="${intakeId}">
                                <div class="mb-3">
                                    <label class="form-label">Group Name</label>
                                    <input type="text" class="form-control" name="group_name" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Group Capacity</label>
                                    <input type="number" class="form-control" name="group_size" min="1" required>
                                    <div class="form-text">Maximum number of students allowed in this group</div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="bi bi-x-circle me-1"></i>Close
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i>Save Student Group
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        const existingModal = document.getElementById('addStudentGroupModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Add new modal to body
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Show the modal
        const modal = new bootstrap.Modal(document.getElementById('addStudentGroupModal'));
        modal.show();
        
        // Add form submit handler
        document.querySelector('#addStudentGroupModal form').addEventListener('submit', function(e) {
            e.preventDefault();
            handleFormSubmit(this, 'addStudentGroupModal');
        });
    }

    // Function to edit student group
    function editStudentGroup(id, name, size) {
        currentGroupId = id;
        
        // Create and show the modal
        const modalHtml = `
            <div class="modal fade" id="editStudentGroupModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title">
                                <i class="bi bi-people me-2"></i>Edit Student Group
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="edit_student_group">
                                <input type="hidden" name="group_id" value="${id}">
                                <input type="hidden" name="intake_id" value="${currentIntakeId}">
                                <div class="mb-3">
                                    <label class="form-label">Group Name</label>
                                    <input type="text" class="form-control" name="group_name" value="${name}" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Group Capacity</label>
                                    <input type="number" class="form-control" name="group_size" min="1" value="${size}" required>
                                    <div class="form-text">Maximum number of students allowed in this group</div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="bi bi-x-circle me-1"></i>Close
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i>Update Student Group
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        const existingModal = document.getElementById('editStudentGroupModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Add new modal to body
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Show the modal
        const modal = new bootstrap.Modal(document.getElementById('editStudentGroupModal'));
        modal.show();
        
        // Add form submit handler
        document.querySelector('#editStudentGroupModal form').addEventListener('submit', function(e) {
            e.preventDefault();
            handleFormSubmit(this, 'editStudentGroupModal');
        });
    }

    // Function to delete student group
    async function deleteStudentGroup(id) {
        if (confirm('Are you sure you want to delete this student group?')) {
            try {
                const formData = new FormData();
                formData.append('action', 'delete_student_group');
                formData.append('group_id', id);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showStudentGroups(currentIntakeId);
                    showAlert('Success!', result.message, 'success');
                } else {
                    showAlert('Error!', result.message, 'danger');
                }
            } catch (error) {
                showAlert('Error!', 'Failed to delete student group.', 'danger');
            }
        }
    }

    // Function to edit campus
    function editCampus(id, name) {
        // Create and show the modal
        const modalHtml = `
            <div class="modal fade" id="editCampusModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title">
                                <i class="bi bi-building me-2"></i>Edit Campus
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="edit_campus">
                                <input type="hidden" name="campus_id" value="${id}">
                                <div class="mb-3">
                                    <label class="form-label">Campus Name</label>
                                    <input type="text" class="form-control" name="campus_name" value="${name}" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="bi bi-x-circle me-1"></i>Close
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i>Update Campus
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        const existingModal = document.getElementById('editCampusModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Add new modal to body
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Show the modal
        const modal = new bootstrap.Modal(document.getElementById('editCampusModal'));
        modal.show();
        
        // Add form submit handler
        document.querySelector('#editCampusModal form').addEventListener('submit', function(e) {
            e.preventDefault();
            handleFormSubmit(this, 'editCampusModal');
        });
    }

    // Function to delete campus
    async function deleteCampus(id) {
        if (confirm('Are you sure you want to delete this campus? This will also delete all associated colleges, schools, departments, programs, intakes, and student groups.')) {
            try {
                const formData = new FormData();
                formData.append('action', 'delete_campus');
                formData.append('campus_id', id);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    await refreshCampusTable();
                    showAlert('Success!', result.message, 'success');
                } else {
                    showAlert('Error!', result.message, 'danger');
                }
            } catch (error) {
                showAlert('Error!', 'Failed to delete campus.', 'danger');
            }
        }
    }

    // Add input validation and feedback
    function validateInput(input, rules) {
        const value = input.value.trim();
        let isValid = true;
        let errorMessage = '';
        
        if (rules.required && !value) {
            isValid = false;
            errorMessage = 'This field is required';
        } else if (rules.minLength && value.length < rules.minLength) {
            isValid = false;
            errorMessage = `Minimum length is ${rules.minLength} characters`;
        } else if (rules.maxLength && value.length > rules.maxLength) {
            isValid = false;
            errorMessage = `Maximum length is ${rules.maxLength} characters`;
        } else if (rules.pattern && !rules.pattern.test(value)) {
            isValid = false;
            errorMessage = rules.message || 'Invalid format';
        }
        
        // Update input styling
        input.classList.toggle('is-invalid', !isValid);
        input.classList.toggle('is-valid', isValid && value !== '');
        
        // Update feedback message
        const feedback = input.nextElementSibling;
        if (feedback && feedback.classList.contains('invalid-feedback')) {
            feedback.textContent = errorMessage;
        }
        
        return isValid;
        }
    </script>
</body>
</html> 