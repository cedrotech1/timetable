<?php
// Enable error logging but prevent display
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

include('connection.php');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Validate required fields
    $required_fields = ['academic_year_id', 'semester', 'lecturer_id', 'facility_id', 'module_id', 'group_ids'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    $academic_year_id = $_POST['academic_year_id'];
    $semester = $_POST['semester'];
    $lecturer_id = $_POST['lecturer_id'];
    $facility_id = $_POST['facility_id'];
    $module_id = $_POST['module_id'];
    $group_ids = $_POST['group_ids'];

    // Verify academic year exists
    $check_year = "SELECT id FROM academic_year WHERE id = ?";
    $stmt = mysqli_prepare($connection, $check_year);
    if (!$stmt) {
        throw new Exception("Year check preparation failed: " . mysqli_error($connection));
    }
    mysqli_stmt_bind_param($stmt, "i", $academic_year_id);
    mysqli_stmt_execute($stmt);
    $year_result = mysqli_stmt_get_result($stmt);
    if (mysqli_num_rows($year_result) === 0) {
        throw new Exception("Invalid academic year ID");
    }

    // Verify module exists and matches semester
    $check_module = "SELECT id, semester FROM module WHERE id = ?";
    $stmt = mysqli_prepare($connection, $check_module);
    if (!$stmt) {
        throw new Exception("Module check preparation failed: " . mysqli_error($connection));
    }
    mysqli_stmt_bind_param($stmt, "i", $module_id);
    mysqli_stmt_execute($stmt);
    $module_result = mysqli_stmt_get_result($stmt);
    if (mysqli_num_rows($module_result) === 0) {
        throw new Exception("Invalid module ID");
    }
    $module_data = mysqli_fetch_assoc($module_result);
    if ($module_data['semester'] != $semester) {
        throw new Exception("Module semester does not match selected semester");
    }

    // Verify lecturer exists
    $check_lecturer = "SELECT id FROM users WHERE id = ? AND role = 'lecturer'";
    $stmt = mysqli_prepare($connection, $check_lecturer);
    if (!$stmt) {
        throw new Exception("Lecturer check preparation failed: " . mysqli_error($connection));
    }
    mysqli_stmt_bind_param($stmt, "i", $lecturer_id);
    mysqli_stmt_execute($stmt);
    $lecturer_result = mysqli_stmt_get_result($stmt);
    if (mysqli_num_rows($lecturer_result) === 0) {
        throw new Exception("Invalid lecturer ID");
    }

    // Verify facility exists
    $check_facility = "SELECT id FROM facility WHERE id = ?";
    $stmt = mysqli_prepare($connection, $check_facility);
    if (!$stmt) {
        throw new Exception("Facility check preparation failed: " . mysqli_error($connection));
    }
    mysqli_stmt_bind_param($stmt, "i", $facility_id);
    mysqli_stmt_execute($stmt);
    $facility_result = mysqli_stmt_get_result($stmt);
    if (mysqli_num_rows($facility_result) === 0) {
        throw new Exception("Invalid facility ID");
    }

    // Start transaction
    mysqli_begin_transaction($connection);

    try {
        // Insert timetable entry
        $insert_query = "INSERT INTO timetable (academic_year_id, semester, lecturer_id, facility_id, module_id) 
                        VALUES (?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($connection, $insert_query);
        if (!$stmt) {
            throw new Exception("Timetable insert preparation failed: " . mysqli_error($connection));
        }

        mysqli_stmt_bind_param($stmt, "isiii", 
            $academic_year_id, 
            $semester, 
            $lecturer_id, 
            $facility_id,
            $module_id
        );
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Failed to insert timetable: " . mysqli_stmt_error($stmt));
        }
        
        $timetable_id = mysqli_insert_id($connection);
        
        // Insert group associations
        $group_query = "INSERT INTO timetable_groups (timetable_id, group_id) VALUES (?, ?)";
        $stmt = mysqli_prepare($connection, $group_query);
        if (!$stmt) {
            throw new Exception("Group insert preparation failed: " . mysqli_error($connection));
        }
        
        foreach ($group_ids as $group_id) {
            mysqli_stmt_bind_param($stmt, "ii", $timetable_id, $group_id);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to insert group association: " . mysqli_stmt_error($stmt));
            }
        }
        
        // Commit transaction
        mysqli_commit($connection);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'timetable_id' => $timetable_id]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($connection);
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Timetable Error: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 