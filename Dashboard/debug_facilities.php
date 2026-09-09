<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering
ob_start();

// Set JSON content type
header('Content-Type: application/json');

// Simple test response
$response = [
    'draw' => isset($_GET['draw']) ? intval($_GET['draw']) : 1,
    'recordsTotal' => 2,
    'recordsFiltered' => 2,
    'data' => [
        [
            'id' => 1,
            'buildname' => 'Building A',
            'name' => 'Test Room 1',
            'type' => 'Classroom',
            'capacity' => 30,
            'site_name' => 'Main Campus',
            'status' => 'Adequate',
            'action' => '<button class="select-btn" data-id="1">Select</button>'
        ],
        [
            'id' => 2,
            'buildname' => 'Building B',
            'name' => 'Test Lab 1',
            'type' => 'Lab',
            'capacity' => 20,
            'site_name' => 'Main Campus',
            'status' => 'Adequate',
            'action' => '<button class="select-btn" data-id="2">Select</button>'
        ]
    ]
];

// Clear any previous output
ob_clean();

// Output the JSON
echo json_encode($response);

// End output buffering and flush
ob_end_flush();
exit;
