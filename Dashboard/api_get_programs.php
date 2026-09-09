<?php
// api_get_programs.php
// Returns JSON list of programs with optional filtering by IDs
// Requires: include('connection.php'); which defines $connection (mysqli)

header('Content-Type: application/json');
include('connection.php');

// Check if specific program IDs are requested
$programIds = [];
if (isset($_GET['ids'])) {
    $ids = explode(',', $_GET['ids']);
    $programIds = array_filter(array_map('intval', $ids), function($id) {
        return $id > 0;
    });
}

// Build the SQL query
$sql = "SELECT id, name, code FROM program";

// Add WHERE clause if specific IDs are requested
if (!empty($programIds)) {
    $idList = implode(',', $programIds);
    $sql .= " WHERE id IN ($idList)";
}

$sql .= " ORDER BY name ASC";

// Execute the query
$res = mysqli_query($connection, $sql);
if (!$res) {
    echo json_encode([
        'success' => false, 
        'message' => 'Query failed', 
        'error' => mysqli_error($connection)
    ]);
    exit;
}

// Fetch and return results
$list = [];
while ($row = mysqli_fetch_assoc($res)) {
    $list[] = $row;
}

echo json_encode([
    'success' => true, 
    'data' => $list,
    'count' => count($list)
]);
