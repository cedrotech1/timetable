<?php
require_once 'connection.php';
// require_once 'auth_check.php';

header('Content-Type: application/json');

try {
    // Enable error logging
    error_log("Remove group request received");
    
    // Get parameters
    $timetable_id = isset($_POST['timetable_id']) ? intval($_POST['timetable_id']) : 0;
    $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;

    error_log("Parameters received - timetable_id: $timetable_id, group_id: $group_id");

    if (!$timetable_id || !$group_id) {
        throw new Exception('Invalid parameters: timetable_id and group_id are required');
    }

    // Check if the timetable exists
    $stmt = $connection->prepare("SELECT id FROM timetable WHERE id = ?");
    $stmt->bind_param("i", $timetable_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Timetable not found');
    }

    // Check if the group exists
    $stmt = $connection->prepare("SELECT id FROM student_group WHERE id = ?");
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Group not found');
    }

    // Check if the group is assigned to the timetable
    $stmt = $connection->prepare("SELECT id FROM timetable_groups WHERE timetable_id = ? AND group_id = ?");
    $stmt->bind_param("ii", $timetable_id, $group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Group is not assigned to this timetable');
    }

    // Delete the group from timetable_groups
    $stmt = $connection->prepare("DELETE FROM timetable_groups WHERE timetable_id = ? AND group_id = ?");
    $stmt->bind_param("ii", $timetable_id, $group_id);
    
    if ($stmt->execute()) {
        error_log("Group removed successfully");
        echo json_encode([
            'success' => true,
            'message' => 'Group removed successfully'
        ]);
    } else {
        error_log("Database error: " . $stmt->error);
        throw new Exception('Database error: ' . $stmt->error);
    }

} catch (Exception $e) {
    error_log("Error removing group: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 