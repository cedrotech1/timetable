<?php
include('connection.php');
header('Content-Type: application/json');

try {
    $program_id = isset($_GET['program_id']) ? (int)$_GET['program_id'] : 0;
    
    if (!$program_id) {
        throw new Exception('Program ID is required');
    }

    $result = mysqli_query($connection, "SELECT id, CONCAT(year, ' - ', CASE month WHEN 1 THEN 'January' WHEN 2 THEN 'February' WHEN 3 THEN 'March' WHEN 4 THEN 'April' WHEN 5 THEN 'May' WHEN 6 THEN 'June' WHEN 7 THEN 'July' WHEN 8 THEN 'August' WHEN 9 THEN 'September' WHEN 10 THEN 'October' WHEN 11 THEN 'November' WHEN 12 THEN 'December' END) as name FROM intake WHERE program_id = $program_id ORDER BY year DESC, month DESC");
    if (!$result) {
        throw new Exception(mysqli_error($connection));
    }

    $intakes = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $intakes[] = $row;
    }

    echo json_encode([
        'success' => true,
        'data' => $intakes
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 