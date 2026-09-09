<?php
session_start();
include("connection.php");

header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$site_id = isset($_GET['site_id']) ? (int)$_GET['site_id'] : 0;

if ($site_id <= 0) {
    echo json_encode(['error' => 'Invalid site ID']);
    exit();
}

// Verify site exists and user has access
$user_id = $_SESSION['id'];
$is_admin = $_SESSION['role'] === 'admin';

$site_query = "SELECT campus FROM site WHERE id = ?";
$stmt = $connection->prepare($site_query);
$stmt->bind_param("i", $site_id);
$stmt->execute();
$site = $stmt->get_result()->fetch_assoc();

if (!$site) {
    echo json_encode(['error' => 'Site not found']);
    exit();
}

if (!$is_admin) {
    $user_campus_query = "SELECT campus FROM users WHERE id = ?";
    $user_stmt = $connection->prepare($user_campus_query);
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_campus = $user_stmt->get_result()->fetch_assoc();
    
    if ($user_campus['campus'] != $site['campus']) {
        echo json_encode(['error' => 'Access denied']);
        exit();
    }
}

// Get assigned schools
$assigned = [];
$query = "SELECT school_id FROM site_school WHERE site_id = ?";
$stmt = $connection->prepare($query);
$stmt->bind_param("i", $site_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $assigned[] = $row['school_id'];
}

echo json_encode(['assigned' => $assigned]);
?>