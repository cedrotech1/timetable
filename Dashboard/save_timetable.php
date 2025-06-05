<?php
include('connection.php');

header('Content-Type: application/json');

try {
    // Start transaction
    mysqli_begin_transaction($connection);

    // Validate required fields
    $required_fields = ['academic_year_id', 'semester', 'module_id', 'lecturer_id', 'facility_id', 'group_ids'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Convert semester to proper format
    $semester = $_POST['semester'] == '1' ? 'Semester 1' : 'Semester 2';

    // Insert into timetable
    $timetable_query = "INSERT INTO timetable (module_id, lecturer_id, facility_id, semester, academic_year_id) 
                       VALUES (?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($connection, $timetable_query);
    mysqli_stmt_bind_param($stmt, 'iiisi', 
        $_POST['module_id'],
        $_POST['lecturer_id'],
        $_POST['facility_id'],
        $semester,
        $_POST['academic_year_id']
    );
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Error creating timetable entry: " . mysqli_error($connection));
    }
    
    $timetable_id = mysqli_insert_id($connection);

    // Insert group assignments
    $group_query = "INSERT INTO timetable_groups (timetable_id, group_id) VALUES (?, ?)";
    $stmt = mysqli_prepare($connection, $group_query);
    
    foreach ($_POST['group_ids'] as $group_id) {
        mysqli_stmt_bind_param($stmt, 'ii', $timetable_id, $group_id);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error assigning group: " . mysqli_error($connection));
        }
    }

    // Commit transaction
    mysqli_commit($connection);
    
    echo json_encode(['success' => true, 'message' => 'Class scheduled successfully']);

} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($connection);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

mysqli_close($connection);
?> 