<?php
header('Content-Type: application/json');
include('connection.php');

$response = ['success' => false, 'data' => []];

try {
    if (!isset($_GET['campus_id']) || !is_numeric($_GET['campus_id'])) {
        throw new Exception('Invalid campus ID');
    }

    $campus_id = (int)$_GET['campus_id'];
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
    
    $response = [
        'success' => true,
        'data' => $colleges
    ];
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
