<?php
header('Content-Type: application/json');
include('connection.php');

$response = ['success' => false, 'data' => []];

try {
    if (!isset($_GET['college_id']) || !is_numeric($_GET['college_id'])) {
        throw new Exception('Invalid college ID');
    }

    $college_id = (int)$_GET['college_id'];
    $query = "SELECT id, name FROM school WHERE college_id = ? ORDER BY name";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $college_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $schools = [];
    while ($row = $result->fetch_assoc()) {
        $schools[] = [
            'id' => $row['id'],
            'name' => $row['name']
        ];
    }
    
    $response = [
        'success' => true,
        'data' => $schools
    ];
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
