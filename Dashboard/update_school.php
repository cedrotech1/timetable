<?php
require_once 'connection.php';
session_start();

header('Content-Type: application/json');

// Check if user is logged in and has admin privileges
// if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
//     echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
//     exit;
// }

// Check if this is a school name update request
if (isset($_POST['update_school_name'])) {
    $school_id = isset($_POST['school_id']) ? intval($_POST['school_id']) : 0;
    $new_name = trim($_POST['new_name'] ?? '');
    
    // Validate input
    if (empty($new_name)) {
        echo json_encode(['success' => false, 'message' => 'School name cannot be empty']);
        exit;
    }
    
    if ($school_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid school ID']);
        exit;
    }
    
    // Check if school exists
    $check_sql = "SELECT id FROM school WHERE id = ?";
    $check_stmt = $connection->prepare($check_sql);
    $check_stmt->bind_param('i', $school_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'School not found']);
        exit;
    }
    
    // Check if the new name already exists
    $duplicate_sql = "SELECT id FROM school WHERE LOWER(name) = LOWER(?) AND id != ?";
    $duplicate_stmt = $connection->prepare($duplicate_sql);
    $duplicate_stmt->bind_param('si', $new_name, $school_id);
    $duplicate_stmt->execute();
    $duplicate_result = $duplicate_stmt->get_result();
    
    if ($duplicate_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'A school with this name already exists']);
        exit;
    }
    
    // Update the school name
    $update_sql = "UPDATE school SET name = ? WHERE id = ?";
    $update_stmt = $connection->prepare($update_sql);
    $update_stmt->bind_param('si', $new_name, $school_id);
    
    if ($update_stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'School name updated successfully',
            'new_name' => $new_name
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to update school name: ' . $connection->error
        ]);
    }
    
    $update_stmt->close();
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
?>
