<?php
// Prevent PHP errors from being displayed
error_reporting(0);
ini_set('display_errors', 0);

// Set proper JSON content type
header('Content-Type: application/json');

session_start();

// Include connection file with error handling
try {
    require_once("connection.php");
    
    if (!$connection) {
        throw new Exception("Database connection failed");
    }   
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection error: ' . $e->getMessage()]);
    exit();
}

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    // Get parameters
    $hostel_id = isset($_GET['hostel_id']) ? (int)$_GET['hostel_id'] : 0;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 5;

    if ($hostel_id <= 0) {
        throw new Exception('Invalid hostel ID');
    }

    // Get hostel name
    $hostel_query = "SELECT name FROM hostels WHERE id = ?";
    $hostel_stmt = $connection->prepare($hostel_query);
    $hostel_stmt->bind_param("i", $hostel_id);
    $hostel_stmt->execute();
    $hostel_result = $hostel_stmt->get_result();
    $hostel = $hostel_result->fetch_assoc();

    if (!$hostel) {
        throw new Exception('Hostel not found');
    }

    // Calculate offset
    $offset = ($page - 1) * $per_page;

    // Get total number of rooms
    $total_query = "SELECT COUNT(*) as total FROM rooms WHERE hostel_id = ? AND remain > 0";
    $total_stmt = $connection->prepare($total_query);
    $total_stmt->bind_param("i", $hostel_id);
    $total_stmt->execute();
    $total_result = $total_stmt->get_result();
    $total_rooms = $total_result->fetch_assoc()['total'];

    // Get rooms for current page
    $rooms_query = "SELECT r.*, 
                    (SELECT COUNT(*) FROM applications a WHERE a.room_id = r.id) as current_applications
                    FROM rooms r 
                    WHERE r.hostel_id = ? AND r.remain > 0
                    ORDER BY r.room_code
                    LIMIT ? OFFSET ?";
                    
    $rooms_stmt = $connection->prepare($rooms_query);
    $rooms_stmt->bind_param("iii", $hostel_id, $per_page, $offset);
    $rooms_stmt->execute();
    $rooms_result = $rooms_stmt->get_result();

    $rooms = [];
    while ($room = $rooms_result->fetch_assoc()) {
        $rooms[] = [
            'id' => $room['id'],
            'room_code' => $room['room_code'],
            'number_of_beds' => $room['number_of_beds'],
            'remain' => $room['remain'],
            'status' => $room['remain'] > 1 ? 'Available' : 'Limited',
            'current_applications' => $room['current_applications']
        ];
    }

    // Return JSON response
    echo json_encode([
        'success' => true,
        'hostel_name' => $hostel['name'],
        'rooms' => $rooms,
        'total' => $total_rooms,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total_rooms / $per_page)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    // Close statements if they exist
    if (isset($hostel_stmt)) {
        $hostel_stmt->close();
    }
    if (isset($total_stmt)) {
        $total_stmt->close();
    }
    if (isset($rooms_stmt)) {
        $rooms_stmt->close();
    }
} 