<?php
session_start();
include('connection.php');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Get current user's role and campus
$current_user_id = $_SESSION['id'];
$stmt = $connection->prepare("SELECT role, campus FROM users WHERE id = ?");
if (!$stmt) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database prepare error: ' . $connection->error]);
    exit();
}

$stmt->bind_param("i", $current_user_id);
if (!$stmt->execute()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database execute error: ' . $stmt->error]);
    exit();
}

$result = $stmt->get_result();
$current_user = $result->fetch_assoc();

if (!$current_user) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit();
}

header('Content-Type: application/json');

try {
    // Prepare SQL query based on user role
    if ($current_user['role'] === 'admin') {
        $query = "SELECT id, name FROM campus ORDER BY name";
        $result = mysqli_query($connection, $query);
        if (!$result) {
            throw new Exception('Error fetching campuses: ' . mysqli_error($connection));
        }
    } else if ($current_user['role'] === 'campus_admin') {
        $query = "SELECT id, name FROM campus WHERE id = ?";
        $stmt = $connection->prepare($query);
        if (!$stmt) {
            throw new Exception('Error preparing campus query: ' . $connection->error);
        }
        $stmt->bind_param("i", $current_user['campus']);
        if (!$stmt->execute()) {
            throw new Exception('Error executing campus query: ' . $stmt->error);
        }
        $result = $stmt->get_result();
    } else {
        throw new Exception('Unauthorized access');
    }

    $campuses = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $campuses[] = [
            'id' => (int)$row['id'],
            'name' => $row['name']
        ];
    }

    // Always return data in the expected format
    echo json_encode([
        'success' => true,
        'campuses' => $campuses
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 