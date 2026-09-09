<?php
session_start();
include("connection.php");

header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$campus_id = isset($_GET['campus_id']) ? (int)$_GET['campus_id'] : 0;

if ($campus_id <= 0) {
    echo json_encode(['error' => 'Invalid campus ID']);
    exit();
}

// Get user's campus if not admin
$user_id = $_SESSION['id'];
$is_admin = $_SESSION['role'] === 'admin';
$allowed_campus = $campus_id;

if (!$is_admin) {
    $stmt = $connection->prepare("SELECT campus FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_campus = $result->fetch_assoc();
    
    if ($user_campus['campus'] != $campus_id) {
        echo json_encode(['error' => 'Access denied']);
        exit();
    }
}

// Get schools for this campus
$schools = [];
$colleges = [];

// Get all colleges first
$college_query = "SELECT id, name FROM college ORDER BY name";
$college_result = mysqli_query($connection, $college_query);

while ($college = mysqli_fetch_assoc($college_result)) {
    $colleges[$college['id']] = $college;
}

// Get schools
$school_query = "SELECT s.*, c.name as college_name 
                FROM school s 
                JOIN college c ON s.college_id = c.id 
                ORDER BY c.name, s.name";

$school_result = mysqli_query($connection, $school_query);
while ($school = mysqli_fetch_assoc($school_result)) {
    $school['college_id'] = $school['college_id']; // Ensure college_id is available
    $schools[] = $school;
}

echo json_encode($schools);
?>