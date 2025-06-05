<?php
// Database credentials
$dbHost = 'localhost';
$dbUser = 'root';
$dbPassword = '';
$dbName = 'timetable';
$dbPort = '3306';

// Create connection
$connection = mysqli_connect($dbHost, $dbUser, $dbPassword, $dbName, $dbPort);

// Check connection
if (!$connection) {
    die("Connection failed: " . mysqli_connect_error());
}
?> 