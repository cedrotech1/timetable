<?php
// remove_lecturer.php
require_once "connection.php"; // should define $connection (mysqli)

$lectId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$timetableId = isset($_GET['timetable_id']) ? intval($_GET['timetable_id']) : 0;

if ($lectId <= 0 || $timetableId <= 0) {
    die("Invalid parameters.");
}

$sql = "DELETE FROM timetable_lecturers WHERE lect_id = ? AND timetable_id = ?";
$stmt = $connection->prepare($sql);
$stmt->bind_param("ii", $lectId, $timetableId);

if ($stmt->execute()) {
    header("Location: timetable_details_all.php?id=" . $timetableId);
    exit;
} else {
    die("Error removing lecturer: " . $stmt->error);
}

$stmt->close();
$connection->close();
?>
