<?php
session_start();
header('Content-Type: application/json');
include('connection.php');

// Check if user is logged in and has permission
if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Get timetable ID from URL
$timetableId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$timetableId) {
    echo json_encode(['success' => false, 'error' => 'Invalid timetable ID']);
    exit;
}

// Get module_id from POST data
$input = json_decode(file_get_contents('php://input'), true);
$moduleId = isset($input['module_id']) ? intval($input['module_id']) : 0;

if (!$moduleId) {
    echo json_encode(['success' => false, 'error' => 'Module ID is required']);
    exit;
}

// Verify the module exists
$stmt = $connection->prepare("SELECT id, name, code, credits FROM module WHERE id = ?");
$stmt->bind_param("i", $moduleId);
$stmt->execute();
$module = $stmt->get_result()->fetch_assoc();

if (!$module) {
    echo json_encode(['success' => false, 'error' => 'Module not found']);
    exit;
}

try {
    // Start transaction
    $connection->begin_transaction();
    
    // Update the timetable with the new module
    $updateStmt = $connection->prepare("UPDATE timetable SET module_id = ? WHERE id = ?");
    $updateStmt->bind_param("ii", $moduleId, $timetableId);
    
    if (!$updateStmt->execute()) {
        throw new Exception("Failed to update timetable");
    }
    
    // Note: Removed update for non-existent columns module_name and module_code in timetable_sessions
    
    // Commit transaction
    $connection->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Module updated successfully',
        'module' => [
            'id' => $moduleId,
            'name' => $module['name'],
            'code' => $module['code'],
            'credits' => $module['credits']
        ]
    ]);
    
} catch (Exception $e) {
    $connection->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage(),
        'sql_error' => $connection->error ?? null
    ]);
}
