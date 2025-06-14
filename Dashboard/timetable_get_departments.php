<?php
include('connection.php');
header('Content-Type: application/json');

try {
    $school_id = isset($_GET['school_id']) ? (int)$_GET['school_id'] : 0;
    
    if (!$school_id) {
        throw new Exception('School ID is required');
    }

    $result = mysqli_query($connection, "SELECT id, name FROM department WHERE school_id = $school_id ORDER BY name");
    if (!$result) {
        throw new Exception(mysqli_error($connection));
    }

    $departments = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $departments[] = $row;
    }

    echo json_encode([
        'success' => true,
        'data' => $departments
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 