<?php
// Include database connection
require_once 'connection.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid college ID']);
    exit;
}

$collegeId = (int)$_GET['id'];

try {
    // Prepare and execute the query
    $stmt = $connection->prepare("SELECT id, name, campus_id FROM college WHERE id = ?");
    $stmt->bind_param('i', $collegeId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $college = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $college['id'],
                'name' => $college['name'],
                'campus_id' => $college['campus_id']
            ]
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'College not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$connection->close();
?>
