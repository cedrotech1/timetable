<?php
// get_programs_by_name.php
// Returns program IDs for given program names

header('Content-Type: application/json');
include('connection.php');

// Check if connection was successful
if (!isset($connection) || !$connection) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed',
        'error' => mysqli_connect_error()
    ]);
    exit;
}

// Get program names from query parameter
$programNames = [];
if (isset($_GET['names'])) {
    $names = json_decode($_GET['names'], true);
    if (is_array($names)) {
        $programNames = array_filter(array_map('trim', $names));
    }
}

// If no valid program names provided, return empty array
if (empty($programNames)) {
    echo json_encode([
        'success' => true,
        'data' => []
    ]);
    exit;
}

// Prepare the query with placeholders
$placeholders = str_repeat('?,', count($programNames) - 1) . '?';
$query = "SELECT id, name FROM program WHERE name IN ($placeholders)";

// Prepare and execute the query
$stmt = mysqli_prepare($connection, $query);
if ($stmt === false) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to prepare query',
        'error' => mysqli_error($connection)
    ]);
    exit;
}

// Bind parameters
$types = str_repeat('s', count($programNames));
mysqli_stmt_bind_param($stmt, $types, ...$programNames);

// Execute query
if (!mysqli_stmt_execute($stmt)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Query execution failed',
        'error' => mysqli_stmt_error($stmt)
    ]);
    exit;
}

// Get results
$result = mysqli_stmt_get_result($stmt);
$programs = [];
while ($row = mysqli_fetch_assoc($result)) {
    $programs[] = [
        'id' => (int)$row['id'],
        'name' => $row['name']
    ];
}

// Return the results
echo json_encode([
    'success' => true,
    'data' => $programs
]);
