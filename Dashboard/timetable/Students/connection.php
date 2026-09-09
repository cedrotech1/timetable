<?php

// session_start();

// if(!isset($_SESSION['loggedin'])){
//     echo"<script>window.location.href='../login.php'</script>";

// }


require_once '../../loadEnv.php';

// Load the .env file
$filePath = __DIR__ . '/../../.env'; // Corrected path
loadEnv($filePath);

// Access environment variables
$dbHost = getenv('DB_HOST');
$dbPort = getenv('DB_PORT');
$dbName = getenv('DB_HOSTEL');
$dbUser = getenv('DB_USER');
$dbPassword = getenv('DB_PASSWORD');

$connection=mysqli_connect($dbHost,$dbUser, $dbPassword,$dbName,$dbPort);
if($connection){

}


$query = "SELECT * FROM system";
$result1 = mysqli_query($connection, $query);


if (mysqli_num_rows($result1) > 0) {
while ($row1 = mysqli_fetch_assoc($result1)) {
$status= $row1['status'];
$exp= $row1['exp_date'];
$exam_validity= $row1['exam_validity'];
$accademic_year= $row1['accademic_year'];
$semester= $row1['semester'];
$allow_message= $row1['allow_message'];


}
}

?>

