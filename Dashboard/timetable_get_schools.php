<?php
include('connection.php');
header('Content-Type: application/json');

try {
    $college_id = isset($_GET['college_id']) ? (int)$_GET['college_id'] : 0;
    
    if (!$college_id) {
        throw new Exception('College ID is required');
    }

    $result = mysqli_query($connection, "SELECT id, name FROM school WHERE college_id = $college_id ORDER BY name");
    if (!$result) {
        throw new Exception(mysqli_error($connection));
    }

    $schools = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $schools[] = $row;
    }

    echo json_encode([
        'success' => true,
        'data' => $schools
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 