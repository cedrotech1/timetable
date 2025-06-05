<?php
error_reporting(0);
include('connection.php');
// connection 

header('Content-Type: application/json');

try {
    if (!$connection) {
        throw new Exception("Database connection failed");
    }

    $query = "SELECT id, name FROM campus ORDER BY name";
    $result = mysqli_query($connection, $query);
    
    if (!$result) {
        throw new Exception("Query failed: " . mysqli_error($connection));
    }
    
    $campuses = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $campuses[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $campuses]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 