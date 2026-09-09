<?php
session_start();
header('Content-Type: application/json');
require_once 'connection.php';

$timetableId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$input = json_decode(file_get_contents('php://input'), true);
$facilityId = isset($input['facility_id']) ? intval($input['facility_id']) : 0;

if ($timetableId <= 0 || $facilityId <= 0) {
    echo json_encode(["success" => false, "error" => "Missing parameters"]);
    exit;
}

$sql = "UPDATE timetable SET facility_id=? WHERE id=?";
$stmt = $connection->prepare($sql);
$stmt->bind_param("ii", $facilityId, $timetableId);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => $connection->error]);
}
?>
