<?php
include('connection.php');
header('Content-Type: application/json');
$college_id = isset($_GET['college_id']) ? (int)$_GET['college_id'] : 0;
$departments = [];
if ($college_id) {
    $result = mysqli_query($connection, "SELECT id, name FROM department WHERE school_id IN (SELECT id FROM school WHERE college_id = $college_id) ORDER BY name");
    while ($row = mysqli_fetch_assoc($result)) {
        $departments[] = $row;
    }
}
echo json_encode($departments); 