<?php
include('connection.php');
header('Content-Type: application/json');

try {
    $department_id = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
    
    if (!$department_id) {
        throw new Exception('Department ID is required');
    }

    $result = mysqli_query($connection, "SELECT id, name FROM program WHERE department_id = $department_id ORDER BY name");
    if (!$result) {
        throw new Exception(mysqli_error($connection));
    }

    $programs = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $programs[] = $row;
    }

    echo json_encode([
        'success' => true,
        'data' => $programs
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 