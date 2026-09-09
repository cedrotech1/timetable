<?php
include('connection.php');

// Set JSON content type header
header('Content-Type: application/json');

try {
    if (!isset($_GET['school_id']) || !is_numeric($_GET['school_id'])) {
        throw new Exception('Invalid school ID');
    }

    $school_id = (int)$_GET['school_id'];
    
    // Get school name
    $query = "SELECT name FROM school WHERE id = $school_id";
    $result = mysqli_query($connection, $query);
    
    if (!$result) {
        throw new Exception(mysqli_error($connection));
    }
    
    if ($school = mysqli_fetch_assoc($result)) {
        echo json_encode([
            'success' => true,
            'school_name' => $school['name']
        ]);
    } else {
        throw new Exception('School not found');
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 