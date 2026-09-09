<?php
// Set error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include the connection file to ensure it's working
require_once 'connection.php';

// Test database connection
if (!isset($connection) || $connection->connect_error) {
    die("Database connection failed: " . ($connection->connect_error ?? 'Unknown error'));
}

// Set headers for JSON response
header('Content-Type: application/json');

// Function to test the API endpoint
function testEndpoint($endpoint, $params = []) {
    $url = 'http://' . $_SERVER['HTTP_HOST'] . '/timetable/Dashboard/api/' . $endpoint;
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    $response = @file_get_contents($url);
    if ($response === false) {
        return [
            'success' => false,
            'error' => 'Failed to fetch data',
            'url' => $url,
            'headers' => get_headers($url, 1)
        ];
    }
    
    return [
        'success' => true,
        'url' => $url,
        'response' => $response,
        'json_decoded' => json_decode($response, true),
        'json_last_error' => json_last_error(),
        'json_last_error_msg' => json_last_error_msg()
    ];
}

// Test the endpoint
$testResults = [
    'basic_test' => testEndpoint('get_timetable_analytics.php'),
    'with_params' => testEndpoint('get_timetable_analytics.php', [
        'start_date' => date('Y-m-d', strtotime('-1 month')),
        'end_date' => date('Y-m-d'),
        'group_by' => 'day'
    ])
];

// Output the results
echo json_encode($testResults, JSON_PRETTY_PRINT);

// Also output the raw response for debugging
if (isset($_GET['debug'])) {
    echo "\n\n--- RAW RESPONSE ---\n";
    $response = file_get_contents('http://' . $_SERVER['HTTP_HOST'] . '/timetable/Dashboard/api/get_timetable_analytics.php');
    echo htmlspecialchars($response);
}
?>
