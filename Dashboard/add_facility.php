<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header
header('Content-Type: application/json');

try {
    require_once('connection.php');

    // Validate input
    $required_fields = ['name', 'type', 'capacity', 'location', 'campus_id'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("Field '$field' is required");
        }
    }

    // Sanitize input
    $name = mysqli_real_escape_string($connection, $_POST['name']);
    $type = mysqli_real_escape_string($connection, $_POST['type']);
    $capacity = (int)$_POST['capacity'];
    $location = mysqli_real_escape_string($connection, $_POST['location']);
    $campus_id = (int)$_POST['campus_id'];

    // Validate capacity
    if ($capacity < 1) {
        throw new Exception('Capacity must be greater than 0');
    }

    // Get campus name for error message
    $campus_query = "SELECT name FROM campus WHERE id = $campus_id";
    $campus_result = mysqli_query($connection, $campus_query);
    $campus_name = mysqli_fetch_assoc($campus_result)['name'];

    // Check if facility name already exists in the same campus and location
    $check_query = "SELECT f.*, c.name as campus_name 
                   FROM facility f 
                   JOIN campus c ON f.campus_id = c.id 
                   WHERE f.name = '$name' 
                   AND f.campus_id = $campus_id 
                   AND f.location = '$location'";
    $check_result = mysqli_query($connection, $check_query);
    if (!$check_result) {
        throw new Exception("Check query failed: " . mysqli_error($connection));
    }

    if (mysqli_num_rows($check_result) > 0) {
        $existing = mysqli_fetch_assoc($check_result);
        throw new Exception("A facility named '$name' already exists in $campus_name campus at location '$location'. Please use a different name or location.");
    }

    // Insert new facility
    $query = "INSERT INTO facility (name, type, capacity, location, campus_id) 
              VALUES ('$name', '$type', $capacity, '$location', $campus_id)";

    if (!mysqli_query($connection, $query)) {
        throw new Exception("Insert failed: " . mysqli_error($connection));
    }

    echo json_encode([
        'success' => true,
        'message' => 'Facility added successfully'
    ]);

} catch (Exception $e) {
    error_log("Add facility error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 