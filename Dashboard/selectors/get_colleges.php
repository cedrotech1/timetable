<?php
error_reporting(0);
include('connection.php');
header('Content-Type: application/json');

try {
    if (!$connection) {
        throw new Exception("Database connection failed");
    }

    if (!isset($_GET['campus_id'])) {
        throw new Exception('Campus ID is required');
    }

    $campus_id = $_GET['campus_id'];
    $query = "SELECT id, name FROM college WHERE campus_id = ? ORDER BY name";
    $stmt = mysqli_prepare($connection, $query);
    
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . mysqli_error($connection));
    }
    
    mysqli_stmt_bind_param($stmt, 'i', $campus_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Query execution failed: " . mysqli_stmt_error($stmt));
    }
    
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result) {
        throw new Exception("Failed to get result: " . mysqli_error($connection));
    }
    
    $colleges = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $colleges[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $colleges]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 