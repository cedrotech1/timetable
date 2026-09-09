<?php
session_start();
header('Content-Type: application/json');
require_once 'connection.php';

// Get current user info
$current_user_id = $_SESSION['id'];
$stmt = $connection->prepare("SELECT role, campus, college, school FROM users WHERE id = ?");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();
$current_user = $result->fetch_assoc();
$campus = $current_user['campus'];

// Corrected SQL: WHERE before ORDER BY
$sql = "
    SELECT 
        f.id,
        f.name,
        f.type,
        f.capacity,
        f.campus_id,
        s.name AS site,
        s.campus
    FROM facility f
    LEFT JOIN site s ON s.id = f.site
    WHERE f.campus_id = $campus
    ORDER BY f.name ASC
";

$res = $connection->query($sql);
if (!$res) {
    echo json_encode(["success" => false, "error" => $connection->error]);
    exit;
}

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = [
        "id"       => (int)$row["id"],
        "name"     => $row["name"],
        "type"     => $row["type"],
        "capacity" => isset($row["capacity"]) ? (int)$row["capacity"] : null,
        "campus_id"=> isset($row["campus_id"]) ? (int)$row["campus_id"] : null,
        "site"     => $row["site"],
        "campus"   => $row["campus"]
    ];
}

echo json_encode(["success" => true, "data" => $data]); 