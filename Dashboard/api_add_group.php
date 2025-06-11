<?php
session_start();
include('connection.php');
// require_once 'auth_check.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get POST data
$timetable_id = $_POST['timetable_id'] ?? null;
$group_id = $_POST['group_id'] ?? null;

// Validate input
if (!$timetable_id || !$group_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
    exit;
}

try {
    // Check if the group is already assigned to this timetable
    $check_query = "SELECT id FROM timetable_groups WHERE timetable_id = ? AND group_id = ?";
    $check_stmt = mysqli_prepare($connection, $check_query);
    mysqli_stmt_bind_param($check_stmt, "ii", $timetable_id, $group_id);
    mysqli_stmt_execute($check_stmt);
    $result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($result) > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'This group is already assigned to this timetable'
        ]);
        exit;
    }

    // Insert the new group assignment
    $insert_query = "INSERT INTO timetable_groups (timetable_id, group_id) VALUES (?, ?)";
    $insert_stmt = mysqli_prepare($connection, $insert_query);
    mysqli_stmt_bind_param($insert_stmt, "ii", $timetable_id, $group_id);
    
    if (mysqli_stmt_execute($insert_stmt)) {
        echo json_encode([
            'success' => true,
            'message' => 'Group added successfully'
        ]);
    } else {
        throw new Exception(mysqli_error($connection));
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?> 