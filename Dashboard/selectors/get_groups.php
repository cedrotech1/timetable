<?php
error_reporting(0);
include('connection.php');
header('Content-Type: application/json');

try {
    if (!$connection) {
        throw new Exception("Database connection failed");
    }

    if (!isset($_GET['intake_id'])) {
        throw new Exception('Intake ID is required');
    }

    $intake_id = $_GET['intake_id'];
    $query = "SELECT id, name, size FROM student_group WHERE intake_id = ? ORDER BY name";
    $stmt = mysqli_prepare($connection, $query);
    
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . mysqli_error($connection));
    }
    
    mysqli_stmt_bind_param($stmt, 'i', $intake_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Query execution failed: " . mysqli_stmt_error($stmt));
    }
    
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result) {
        throw new Exception("Failed to get result: " . mysqli_error($connection));
    }
    
    $groups = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $groups[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $groups]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 