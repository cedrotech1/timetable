<?php
include('connection.php');

// Set JSON content type header
header('Content-Type: application/json');

try {
    if (!isset($_GET['intake_id']) || !is_numeric($_GET['intake_id'])) {
        throw new Exception('Invalid intake ID');
    }

    $intake_id = (int)$_GET['intake_id'];
    
    // Get intake information with program name
    $query = "SELECT i.*, p.name as program_name 
              FROM intake i 
              JOIN program p ON i.program_id = p.id 
              WHERE i.id = $intake_id";
    $result = mysqli_query($connection, $query);
    
    if (!$result) {
        throw new Exception(mysqli_error($connection));
    }
    
    if ($intake = mysqli_fetch_assoc($result)) {
        $month_name = date('F', mktime(0, 0, 0, $intake['month'], 1));
        echo json_encode([
            'success' => true,
            'intake_name' => $month_name . ' ' . $intake['year'],
            'program_name' => $intake['program_name']
        ]);
    } else {
        throw new Exception('Intake not found');
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 