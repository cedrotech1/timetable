<?php
error_reporting(0);
include('connection.php');
header('Content-Type: application/json');

try {
    if (!$connection) {
        throw new Exception("Database connection failed");
    }

    if (!isset($_GET['program_id'])) {
        throw new Exception('Program ID is required');
    }

    $program_id = $_GET['program_id'];
    $query = "SELECT id, year, month, size FROM intake WHERE program_id = ? ORDER BY year DESC, month DESC";
    $stmt = mysqli_prepare($connection, $query);
    
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . mysqli_error($connection));
    }
    
    mysqli_stmt_bind_param($stmt, 'i', $program_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Query execution failed: " . mysqli_stmt_error($stmt));
    }
    
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result) {
        throw new Exception("Failed to get result: " . mysqli_error($connection));
    }
    
    $intakes = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $intakes[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $intakes]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 