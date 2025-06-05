<?php
include('connection.php');

// Set JSON content type header
header('Content-Type: application/json');

try {
    if (!isset($_GET['campus_id']) || !is_numeric($_GET['campus_id'])) {
        throw new Exception('Invalid campus ID');
    }

    $campus_id = (int)$_GET['campus_id'];
    
    // Use the correct table name 'campus' instead of 'campuses'
    $query = "SELECT name FROM campus WHERE id = $campus_id";
    $result = mysqli_query($connection, $query);
    
    if (!$result) {
        throw new Exception(mysqli_error($connection));
    }
    
    $campus = mysqli_fetch_assoc($result);
    
    if (!$campus) {
        throw new Exception('Campus not found');
    }
    
    echo json_encode([
        'success' => true,
        'campus_name' => $campus['name']
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 