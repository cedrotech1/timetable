<?php
// // Only start session if not already started
// if (session_status() === PHP_SESSION_NONE) {
//     session_start();
// }

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
$dbName = getenv('DB_TIMETABLE-V3');
$dbUser = getenv('DB_USER');
$dbPassword = getenv('DB_PASSWORD');


// Enable error reporting for mysqli
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create connection with error handling
$connection = @mysqli_connect($dbHost, $dbUser, $dbPassword, $dbName, $dbPort);
$conn = @mysqli_connect($dbHost, $dbUser, $dbPassword, $dbName, $dbPort);

// Check connection
if (!$connection) {
    // Log the error
    error_log('Connection failed: ' . mysqli_connect_error());
    
    // Return false to allow API to handle the error
    return false;
}

// Set charset to ensure proper encoding
mysqli_set_charset($connection, 'utf8mb4');


$query = "SELECT * FROM system";
$result1 = mysqli_query($connection, $query);


if (mysqli_num_rows($result1) > 0) {
while ($row1 = mysqli_fetch_assoc($result1)) {
$status= $row1['status'];



}
}

?>

