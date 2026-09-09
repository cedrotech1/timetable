<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include("connection.php");

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get site ID from request
$site_id = isset($_GET['site_id']) ? (int)$_GET['site_id'] : 0;

if (!$site_id) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid site ID']);
    exit();
}

try {
    // Get assigned schools for this site
    $query = "SELECT school_id FROM site_school WHERE site_id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $site_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Database query failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception("Failed to get result set: " . $connection->error);
    }
    
    $assigned = [];
    while ($row = $result->fetch_assoc()) {
        $assigned[] = (int)$row['school_id'];
    }
    
    header('Content-Type: application/json');
    echo json_encode(['assigned' => $assigned]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
