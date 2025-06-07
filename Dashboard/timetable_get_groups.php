<?php
include('connection.php');
header('Content-Type: application/json');
$program_id = isset($_GET['program_id']) ? (int)$_GET['program_id'] : 0;
$groups = [];
if ($program_id) {
    $result = mysqli_query($connection, "SELECT id, name FROM student_group WHERE intake_id IN (SELECT id FROM intake WHERE program_id = $program_id) ORDER BY name");
    while ($row = mysqli_fetch_assoc($result)) {
        $groups[] = $row;
    }
}
echo json_encode($groups); 