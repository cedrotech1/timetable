<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Default database configuration
$dbHost = 'localhost';
$dbPort = '3306';
$dbName = 'hostel';
$dbUser = 'root';
$dbPassword = '';

// Try to load environment variables if loadEnv.php exists
if (file_exists(__DIR__ . '/../loadEnv.php')) {
    require_once __DIR__ . '/../loadEnv.php';
    $filePath = __DIR__ . '/../.env';
    if (file_exists($filePath)) {
        loadEnv($filePath);
        // Override defaults with environment variables if they exist
        $dbHost = getenv('DB_HOST') ?: $dbHost;
        $dbPort = getenv('DB_PORT') ?: $dbPort;
        $dbName = getenv('DB_HOSTEL') ?: $dbName; // Use DB_HOSTEL
        $dbUser = getenv('DB_USER') ?: $dbUser;
        $dbPassword = getenv('DB_PASSWORD') ?: $dbPassword;
        // $timeLimit = getenv('TIME') ?: 1; // Load TIME variable
    }
}

// Create connection with error handling
$connection = new mysqli($dbHost, $dbUser, $dbPassword, $dbName, $dbPort);

// Check connection
if ($connection->connect_error) {
    error_log("Connection failed: " . $connection->connect_error);
    throw new Exception("Database connection failed: " . $connection->connect_error);
}

// Set charset to ensure proper encoding
$connection->set_charset("utf8mb4");
?>