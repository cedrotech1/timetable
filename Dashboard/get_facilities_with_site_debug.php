<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering
ob_start();

// Set headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Simple test response
$response = [
    'draw' => isset($_GET['draw']) ? intval($_GET['draw']) : 1,
    'recordsTotal' => 2,
    'recordsFiltered' => 2,
    'data' => [
        ['id' => 1, 'name' => 'Test Facility 1', 'type' => 'Classroom', 'capacity' => 30, 'site_name' => 'Main Campus'],
        ['id' => 2, 'name' => 'Test Facility 2', 'type' => 'Lab', 'capacity' => 20, 'site_name' => 'Main Campus']
    ]
];

// Clear any previous output
ob_clean();

// Output the response
echo json_encode($response);

// End output buffering and flush
ob_end_flush();
exit;
