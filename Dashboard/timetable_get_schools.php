<?php
include('connection.php');
header('Content-Type: application/json');
$college_id = isset($_GET['college_id']) ? (int)$_GET['college_id'] : 0;
$schools = [];
if ($college_id) {
    $result = mysqli_query($connection, "SELECT id, name FROM school WHERE college_id = $college_id ORDER BY name");
    while ($row = mysqli_fetch_assoc($result)) {
        $schools[] = $row;
    }
}
echo json_encode($schools); 