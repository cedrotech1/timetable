<?php
// remove_lecturer.php
require_once "connection.php"; // should define $connection (mysqli)

$sessionId = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;
$timetableId = isset($_GET['timetable_id']) ? intval($_GET['timetable_id']) : 0;

if ($sessionId <= 0 || $timetableId <= 0) {
    die("Invalid parameters.");
}

$sql = "DELETE FROM timetable_sessions WHERE id = ? AND timetable_id = ?";
$stmt = $connection->prepare($sql);
$stmt->bind_param("ii", $sessionId, $timetableId);

if ($stmt->execute()) {
    header("Location: timetable_details_all.php?id=" . $timetableId);
    exit;
} else {
    die("Error removing session: " . $stmt->error);
}

$stmt->close();
$connection->close();
?>
