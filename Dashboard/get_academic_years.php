<?php
session_start();
include("connection.php");

// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set headers
header('Content-Type: application/json');

try {
    // Check if connection exists
    if (!isset($connection) || !$connection) {
        throw new Exception('Database connection not available');
    }

    // Test the connection
    if (!mysqli_ping($connection)) {
        throw new Exception('Database connection lost');
    }

    // Prepare and execute query
    $query = "SELECT id, year_label FROM academic_year ORDER BY id DESC";
    $result = mysqli_query($connection, $query);
    
    if (!$result) {
        throw new Exception('Database query failed: ' . mysqli_error($connection));
    }

    // Fetch and format data
    $academic_years = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $academic_years[] = [
            'id' => (int)$row['id'],
            'year_label' => $row['year_label']
        ];
    }

    // Return success response
    echo json_encode([
        'success' => true,
        'data' => $academic_years
    ]);

} catch (Exception $e) {
    // Return error response
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// Close connection if it exists
if (isset($connection) && $connection) {
    mysqli_close($connection);
}
?> 