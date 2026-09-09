<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include("connection.php");

// Log the request
file_put_contents('schools_debug.log', date('Y-m-d H:i:s') . " - Request: " . print_r($_GET, true) . "\n", FILE_APPEND);

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get campus ID from request
$campus_id = isset($_GET['campus_id']) ? (int)$_GET['campus_id'] : 0;

if (!$campus_id) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid campus ID']);
    exit();
}

// Verify the campus exists and user has access to it
$campus_check = "SELECT id FROM campus WHERE id = ?";
$stmt = $connection->prepare($campus_check);
$stmt->bind_param("i", $campus_id);
$stmt->execute();
$campus_result = $stmt->get_result();

if ($campus_result->num_rows === 0) {
    $error = 'Campus not found or access denied';
    file_put_contents('schools_debug.log', date('Y-m-d H:i:s') . " - Error: $error\n", FILE_APPEND);
    header('Content-Type: application/json');
    echo json_encode(['error' => $error]);
    exit();
}

// Get schools for the specified campus
$query = "SELECT s.id, s.name, c.name as college_name, c.id as college_id
          FROM school s 
          JOIN college c ON s.college_id = c.id 
          WHERE c.campus_id = ? 
          ORDER BY c.name, s.name";

$stmt = $connection->prepare($query);
$stmt->bind_param("i", $campus_id);
if (!$stmt->execute()) {
    $error = 'Database query failed: ' . $stmt->error;
    file_put_contents('schools_debug.log', date('Y-m-d H:i:s') . " - Error: $error\n", FILE_APPEND);
    header('Content-Type: application/json');
    echo json_encode(['error' => $error]);
    exit();
}

$result = $stmt->get_result();
if (!$result) {
    $error = 'Failed to get result set: ' . $connection->error;
    file_put_contents('schools_debug.log', date('Y-m-d H:i:s') . " - Error: $error\n", FILE_APPEND);
    header('Content-Type: application/json');
    echo json_encode(['error' => $error]);
    exit();
}

$schools = [];
while ($row = $result->fetch_assoc()) {
    $schools[] = [
        'id' => (int)$row['id'],
        'name' => htmlspecialchars($row['name']),
        'college_name' => htmlspecialchars($row['college_name'])
    ];
}

// Log the response
file_put_contents('schools_debug.log', date('Y-m-d H:i:s') . " - Found " . count($schools) . " schools\n", FILE_APPEND);

header('Content-Type: application/json');
echo json_encode($schools);
?>
