<?php
include('connection.php');

// Set JSON content type header
header('Content-Type: application/json');

try {
    if (!isset($_GET['department_id']) || !is_numeric($_GET['department_id'])) {
        throw new Exception('Invalid department ID');
    }

    $department_id = (int)$_GET['department_id'];
    
    // Get department name
    $query = "SELECT name FROM department WHERE id = $department_id";
    $result = mysqli_query($connection, $query);
    
    if (!$result) {
        throw new Exception(mysqli_error($connection));
    }
    
    if ($department = mysqli_fetch_assoc($result)) {
        echo json_encode([
            'success' => true,
            'department_name' => $department['name']
        ]);
    } else {
        throw new Exception('Department not found');
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 