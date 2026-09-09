<?php
session_start();
include("connection.php");

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get site ID from query string
$site_id = isset($_GET['site_id']) ? (int)$_GET['site_id'] : 0;

if ($site_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid site ID']);
    exit();
}

// Get assigned schools for this site
$assigned_schools = [];
$query = "SELECT school_id FROM site_school WHERE site_id = ?";
$stmt = $connection->prepare($query);
$stmt->bind_param("i", $site_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $assigned_schools[] = (int)$row['school_id'];
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($assigned_schools);

// Close connection
$stmt->close();
$connection->close();
?>