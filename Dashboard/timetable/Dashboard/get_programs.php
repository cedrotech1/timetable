<?php
// get_programs.php
header('Content-Type: application/json');
include('connection.php');

$sql = "SELECT id, name FROM program ORDER BY name ASC";
$res = mysqli_query($connection, $sql);
if (!$res) {
    echo json_encode(['success'=>false, 'message'=>'Query failed', 'error'=>mysqli_error($connection)]);
    exit;
}
$list = [];
while ($r = mysqli_fetch_assoc($res)) $list[] = $r;

echo json_encode(['success'=>true, 'data'=>$list]);
