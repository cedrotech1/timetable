<?php
include('connection.php');

// Set JSON content type header
header('Content-Type: application/json');

try {
    if (!isset($_GET['program_id']) || !is_numeric($_GET['program_id'])) {
        throw new Exception('Invalid program ID');
    }

    $program_id = (int)$_GET['program_id'];
    
    // Get program name
    $query = "SELECT name FROM program WHERE id = $program_id";
    $result = mysqli_query($connection, $query);
    
    if (!$result) {
        throw new Exception(mysqli_error($connection));
    }
    
    if ($program = mysqli_fetch_assoc($result)) {
        echo json_encode([
            'success' => true,
            'program_name' => $program['name']
        ]);
    } else {
        throw new Exception('Program not found');
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 