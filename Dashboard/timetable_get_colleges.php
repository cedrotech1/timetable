<?php
include('connection.php');
header('Content-Type: application/json');
$campus_id = isset($_GET['campus_id']) ? (int)$_GET['campus_id'] : 0;
$colleges = [];
if ($campus_id) {
    $result = mysqli_query($connection, "SELECT id, name FROM college WHERE campus_id = $campus_id ORDER BY name");
    while ($row = mysqli_fetch_assoc($result)) {
        $colleges[] = $row;
    }
}
echo json_encode($colleges); 