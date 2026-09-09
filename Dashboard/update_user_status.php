<?php
session_start();
include('connection.php');

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

// Handle status update
if (isset($_POST['updateStatus'])) {
    header('Content-Type: application/json');
    
    $userId = $_POST['userId'] ?? '';
    $status = $_POST['status'] ?? '';
    
    if (empty($userId) || !in_array($status, ['0', '1'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }
    
    try {
        // Update user status
        $stmt = $connection->prepare("UPDATE users SET active = ? WHERE id = ?");
        $stmt->bind_param("ii", $status, $userId);
        
        if ($stmt->execute()) {
            $statusText = $status == '1' ? 'activated' : 'deactivated';
            echo json_encode(['success' => true, 'message' => "User has been $statusText successfully!"]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating user status']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// If not a POST request with updateStatus
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Invalid request']);
?> 