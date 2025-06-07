<?php
include('connection.php');
header('Content-Type: application/json');
$department_id = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$programs = [];
if ($department_id) {
    $result = mysqli_query($connection, "SELECT id, name FROM program WHERE department_id = $department_id ORDER BY name");
    while ($row = mysqli_fetch_assoc($result)) {
        $programs[] = $row;
    }
}
echo json_encode($programs); 