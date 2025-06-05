<?php
session_start();
include('connection.php');

// Get program ID
$program_id = isset($_GET['program_id']) ? (int)$_GET['program_id'] : 0;

if (!$program_id) {
    http_response_code(400);
    exit('Program ID is required');
}

// Get modules for the program
$query = "SELECT id, name, code FROM module WHERE program_id = ?";
$stmt = mysqli_prepare($connection, $query);
mysqli_stmt_bind_param($stmt, "i", $program_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$modules = [];
while ($row = mysqli_fetch_assoc($result)) {
    $modules[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'code' => $row['code']
    ];
}

header('Content-Type: application/json');
echo json_encode($modules); 