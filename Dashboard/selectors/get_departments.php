<?php
error_reporting(0);
include('connection.php');
header('Content-Type: application/json');

try {
    if (!$connection) {
        throw new Exception("Database connection failed");
    }

    if (!isset($_GET['school_id'])) {
        throw new Exception('School ID is required');
    }

    $school_id = $_GET['school_id'];
    
    // Updated query to include school information
    $query = "SELECT d.id, d.name, s.name as school_name 
              FROM department d 
              JOIN school s ON d.school_id = s.id 
              WHERE d.school_id = ? 
              ORDER BY d.name";
              
    $stmt = mysqli_prepare($connection, $query);
    
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . mysqli_error($connection));
    }
    
    mysqli_stmt_bind_param($stmt, 'i', $school_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Query execution failed: " . mysqli_stmt_error($stmt));
    }
    
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result) {
        throw new Exception("Failed to get result: " . mysqli_error($connection));
    }
    
    $departments = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $departments[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'school_name' => $row['school_name']
        ];
    }
    
    echo json_encode(['success' => true, 'data' => $departments]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 