<?php
session_start();
header('Content-Type: application/json');
require_once 'connection.php';

$timetableId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$sessionId = $_SESSION['id'];

$sql = "UPDATE timetable SET status='Approved', approvedby=? WHERE id=?";
$stmt = $connection->prepare($sql);
$stmt->bind_param("si", $sessionId, $timetableId);

if($stmt->execute()){
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => $connection->error]);
}
