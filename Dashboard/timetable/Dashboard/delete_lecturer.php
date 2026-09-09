<?php
session_start();
require_once 'connection.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if ID is provided
if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'Lecturer ID is required']);
    exit;
}

$lecturerId = (int)$_POST['id'];

try {
    // Start transaction
    $connection->begin_transaction();

    // Check if lecturer exists and is actually a lecturer
    $checkStmt = $connection->prepare("SELECT id, role FROM users WHERE id = ?");
    $checkStmt->bind_param("i", $lecturerId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        throw new Exception('Lecturer not found');
    }
    
    $lecturer = $checkResult->fetch_assoc();
    if ($lecturer['role'] !== 'lecturer') {
        throw new Exception('Specified user is not a lecturer');
    }

    // Delete related records first (e.g., timetable entries, etc.)
    // Add any related table deletions here if needed
    // Example:
    // $deleteRelatedStmt = $connection->prepare("DELETE FROM timetable WHERE lecturer_id = ?");
    // $deleteRelatedStmt->bind_param("i", $lecturerId);
    // $deleteRelatedStmt->execute();

    // Delete the lecturer
    $deleteStmt = $connection->prepare("DELETE FROM users WHERE id = ? AND role = 'lecturer'");
    $deleteStmt->bind_param("i", $lecturerId);
    
    if (!$deleteStmt->execute()) {
        throw new Exception('Failed to delete lecturer');
    }

    // Commit transaction
    $connection->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Lecturer deleted successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $connection->rollback();
    
    error_log('Error deleting lecturer: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while deleting the lecturer: ' . $e->getMessage()
    ]);
}

$connection->close();
?>
