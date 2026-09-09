<?php
include('connection.php');

// Set JSON content type header
header('Content-Type: application/json');

try {
    if (!isset($_GET['college_id']) || !is_numeric($_GET['college_id'])) {
        throw new Exception('Invalid college ID');
    }

    $college_id = (int)$_GET['college_id'];
    
    // Get college name
    $query = "SELECT name FROM college WHERE id = $college_id";
    $result = mysqli_query($connection, $query);
    
    if (!$result) {
        throw new Exception(mysqli_error($connection));
    }
    
    $college = mysqli_fetch_assoc($result);
    
    if (!$college) {
        throw new Exception('College not found');
    }
    
    echo json_encode([
        'success' => true,
        'college_name' => $college['name']
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 