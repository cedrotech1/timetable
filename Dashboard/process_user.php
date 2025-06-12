<?php
session_start();
include('connection.php');

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Get current user's role and campus
$current_user_id = $_SESSION['id'];
$stmt = $connection->prepare("SELECT role, campus FROM users WHERE id = ?");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();
$current_user = $result->fetch_assoc();

// Function to check if current user can create the specified role
function canCreateUser($currentUserRole, $newUserRole) {
    if ($currentUserRole === 'admin') {
        return in_array($newUserRole, ['admin', 'campus_admin']);
    } else if ($currentUserRole === 'campus_admin') {
        return in_array($newUserRole, ['timetable_officer', 'lecturer']);
    }
    return false;
}

header('Content-Type: application/json');

if (isset($_POST['saveuser'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $role = $_POST['role'];
    $campus = ($current_user['role'] === 'campus_admin') ? $current_user['campus'] : (($role !== 'admin') ? $_POST['campus'] : null);
    $password = password_hash('1234', PASSWORD_DEFAULT);
    $default_image = 'assets/img/av.png';

    if ($name != '' && $email != '' && $password != '') {
        // Check if email already exists
        $stmt = $connection->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            exit;
        }

        // Validate role-based permissions
        if (!canCreateUser($current_user['role'], $role)) {
            echo json_encode(['success' => false, 'message' => "You don't have permission to create users with this role"]);
            exit;
        }

        // Validate campus requirement for non-admin roles
        if ($role !== 'admin' && empty($campus)) {
            echo json_encode(['success' => false, 'message' => "Campus is required for " . ucfirst($role) . " role"]);
            exit;
        }

        // If no errors, create the user
        $stmt = $connection->prepare("INSERT INTO users (names, email, phone, role, password, campus, active, image) VALUES (?, ?, ?, ?, ?, ?, 1, ?)");
        $stmt->bind_param("sssssss", $name, $email, $phone, $role, $password, $campus, $default_image);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => "User '$name' has been added successfully!"]);
        } else {
            echo json_encode(['success' => false, 'message' => "Error creating user: " . $connection->error]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => "Please fill all required fields."]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?> 