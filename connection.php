<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Default database configuration
$dbHost = 'localhost';
$dbPort = '3306';
$dbName = 'timetable-v3';
$dbUser = 'root';
$dbPassword = 'Ur@2312';

// Try to load environment variables if loadEnv.php exists (app dir first, then parent)
$loadEnvCandidates = [__DIR__ . '/loadEnv.php', __DIR__ . '/../loadEnv.php'];
$envCandidates = [__DIR__ . '/.env', __DIR__ . '/../.env'];
foreach ($loadEnvCandidates as $loadEnvPath) {
    if (file_exists($loadEnvPath)) {
        require_once $loadEnvPath;
        break;
    }
}
if (function_exists('loadEnv')) {
    foreach ($envCandidates as $filePath) {
        if (file_exists($filePath)) {
            loadEnv($filePath);
            $dbHost = getenv('DB_HOST') ?: $dbHost;
            $dbPort = getenv('DB_PORT') ?: $dbPort;
            $dbName = getenv('DB_TIMETABLE-v3') ?: $dbName;
            $dbUser = getenv('DB_USER') ?: $dbUser;
            $dbPassword = getenv('DB_PASSWORD') ?: $dbPassword;
            break;
        }
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