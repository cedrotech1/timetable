<?php
error_reporting(0);
include('connection.php');
header('Content-Type: application/json');

try {
    if (!$connection) {
        throw new Exception("Database connection failed");
    }

    if (!isset($_GET['department_id'])) {
        throw new Exception('Department ID is required');
    }

    $department_id = $_GET['department_id'];
    
    // Updated query to include department information
    $query = "SELECT p.id, p.name, p.code, d.name as department_name 
              FROM program p 
              JOIN department d ON p.department_id = d.id 
              WHERE p.department_id = ? 
              ORDER BY p.name";
              
    $stmt = mysqli_prepare($connection, $query);
    
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . mysqli_error($connection));
    }
    
    mysqli_stmt_bind_param($stmt, 'i', $department_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Query execution failed: " . mysqli_stmt_error($stmt));
    }
    
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result) {
        throw new Exception("Failed to get result: " . mysqli_error($connection));
    }
    
    $programs = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $programs[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'code' => $row['code'],
            'department_name' => $row['department_name']
        ];
    }
    
    echo json_encode(['success' => true, 'data' => $programs]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 