<?php
error_reporting(0);
include('connection.php');
header('Content-Type: application/json');

try {
    if (!$connection) {
        throw new Exception("Database connection failed");
    }

    if (!isset($_GET['college_id'])) {
        throw new Exception('College ID is required');
    }

    $college_id = $_GET['college_id'];
    
    // Updated query to include college information
    $query = "SELECT s.id, s.name, c.name as college_name 
              FROM school s 
              JOIN college c ON s.college_id = c.id 
              WHERE s.college_id = ? 
              ORDER BY s.name";
              
    $stmt = mysqli_prepare($connection, $query);
    
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . mysqli_error($connection));
    }
    
    mysqli_stmt_bind_param($stmt, 'i', $college_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Query execution failed: " . mysqli_stmt_error($stmt));
    }
    
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result) {
        throw new Exception("Failed to get result: " . mysqli_error($connection));
    }
    
    $schools = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $schools[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'college_name' => $row['college_name']
        ];
    }
    
    echo json_encode(['success' => true, 'data' => $schools]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 