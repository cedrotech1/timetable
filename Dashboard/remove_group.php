<?php
// remove_group.php
require_once "connection.php"; // should define $connection (mysqli)

$groupId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$timetableId = isset($_GET['timetable_id']) ? intval($_GET['timetable_id']) : 0;

if ($groupId <= 0 || $timetableId <= 0) {
    die("Invalid parameters.");
}

$sql = "DELETE FROM timetable_groups WHERE group_id = ? AND timetable_id = ?";
$stmt = $connection->prepare($sql);
$stmt->bind_param("ii", $groupId, $timetableId);

if ($stmt->execute()) {
    header("Location: timetable_details_all.php?id=" . $timetableId);
    exit;
} else {
    die("Error removing group: " . $stmt->error);
}

$stmt->close();
$connection->close();
?>
