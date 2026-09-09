<?php
include('connection.php');

header('Content-Type: application/json');

if (!isset($_GET['college_id']) || empty($_GET['college_id'])) {
    echo json_encode(['success' => false, 'message' => 'College ID is required']);
    exit;
}

$college_id = (int)$_GET['college_id'];

try {
    // Get schools for the specified college
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
    
    echo json_encode([
        'success' => true,
        'schools' => $schools
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error loading schools: ' . $e->getMessage()
    ]);
}
?> 