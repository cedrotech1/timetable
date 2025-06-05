<?php
require_once '../connection.php';

// Prevent session blocking
session_write_close();

// Set headers for SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');
header('Access-Control-Allow-Origin: *');

// Get hostel ID from request
$hostel_id = isset($_GET['hostel_id']) ? (int)$_GET['hostel_id'] : 0;

if (!$hostel_id) {
    echo "data: " . json_encode(['success' => false, 'message' => 'Invalid hostel ID']) . "\n\n";
    flush();
    exit;
}

// Function to get room data
function getRoomData($connection, $hostel_id) {
    $query = "SELECT r.*, 
              (SELECT COUNT(*) FROM applications a WHERE a.room_id = r.id AND a.status = 'pending') as current_applications
              FROM rooms r 
              WHERE r.hostel_id = ? AND r.remain > 0
              ORDER BY r.room_code";
    
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $hostel_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $rooms = [];
    while ($row = $result->fetch_assoc()) {
        $rooms[] = $row;
    }
    
    return [
        'success' => true,
        'hostel_id' => $hostel_id,
        'rooms' => $rooms,
        'current_page' => 1,
        'total_pages' => 1,
        'timestamp' => time()
    ];
}

// Function to send SSE message
function sendSSEMessage($data) {
    echo "data: " . json_encode($data) . "\n\n";
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
    flush();
}

// Initial data
$lastData = getRoomData($connection, $hostel_id);
sendSSEMessage($lastData);

// Keep connection alive and check for updates
while (true) {
    // Check for updates every 100ms
    usleep(100000); // 100ms delay
    
    $currentData = getRoomData($connection, $hostel_id);
    
    // Only send update if data has changed
    if (json_encode($currentData) !== json_encode($lastData)) {
        sendSSEMessage($currentData);
        $lastData = $currentData;
    }
    
    // Check if client is still connected
    if (connection_aborted()) {
        break;
    }
    
    // Prevent PHP timeout
    set_time_limit(30);
} 