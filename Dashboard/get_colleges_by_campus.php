<?php
include('connection.php');

header('Content-Type: application/json');

if (!isset($_GET['campus_id']) || empty($_GET['campus_id'])) {
    echo json_encode(['success' => false, 'message' => 'Campus ID is required']);
    exit;
}

$campus_id = (int)$_GET['campus_id'];

try {
    // Get colleges for the specified campus
    $query = "SELECT id, name FROM college WHERE campus_id = ? ORDER BY name";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $campus_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $colleges = [];
    while ($row = $result->fetch_assoc()) {
        $colleges[] = [
            'id' => $row['id'],
            'name' => $row['name']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'colleges' => $colleges
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error loading colleges: ' . $e->getMessage()
    ]);
}
?> 