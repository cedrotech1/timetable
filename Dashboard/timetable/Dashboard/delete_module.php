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
    echo json_encode(['success' => false, 'message' => 'Module ID is required']);
    exit;
}

$moduleId = (int)$_POST['id'];

try {
    // Start transaction
    $connection->begin_transaction();

    // Check if module exists
    $checkStmt = $connection->prepare("SELECT id FROM module WHERE id = ?");
    $checkStmt->bind_param("i", $moduleId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        throw new Exception('Module not found');
    }

    // Delete related records first (e.g., timetable entries, etc.)
    // Add any related table deletions here if needed
    // Example:
    // $deleteRelatedStmt = $connection->prepare("DELETE FROM timetable WHERE module_id = ?");
    // $deleteRelatedStmt->bind_param("i", $moduleId);
    // $deleteRelatedStmt->execute();

    // Delete the module
    $deleteStmt = $connection->prepare("DELETE FROM module WHERE id = ?");
    $deleteStmt->bind_param("i", $moduleId);
    
    if (!$deleteStmt->execute()) {
        throw new Exception('Failed to delete module');
    }

    // Commit transaction
    $connection->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Module deleted successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $connection->rollback();
    
    error_log('Error deleting module: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while deleting the module: ' . $e->getMessage()
    ]);
}

$connection->close();
?>
