<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to log errors
function logError($message) {
    $logMessage = date('[Y-m-d H:i:s] ') . $message . "\n";
    $logFile = __DIR__ . '/update_facility_errors.log';
    error_log($logMessage, 3, $logFile);
    return $logMessage;
}

// Start output buffering to catch any unexpected output
ob_start();

// Set content type to JSON
header('Content-Type: application/json');

// Initialize response array
$response = ['success' => false, 'message' => ''];

try {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Get the raw POST data
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }

    // Log the request
    logError("REQUEST: " . print_r($input, true));

    // Check if connection.php exists and is readable
    if (!file_exists('connection.php') || !is_readable('connection.php')) {
        throw new Exception('connection.php is missing or not readable');
    }

    // Include connection file
    require_once 'connection.php';

    // Check if connection was established
    if (!isset($connection) || !($connection instanceof mysqli)) {
        throw new Exception('Database connection failed or is invalid');
    }

    // Check if connection is still alive
    if (!$connection->ping()) {
        throw new Exception('Database connection lost');
    }

    // Validate required fields
    $requiredFields = ['id', 'site_id', 'name', 'type', 'capacity'];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || (empty($input[$field]) && $input[$field] !== '0')) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Prepare the SQL statement for updating facility
    $sql = "UPDATE facility SET 
            name = ?, 
            name2 = ?, 
            type = ?, 
            capacity = ?, 
            buildname = ?, 
            build_code = ?,
            site = ?,
            campus_id = ?
            WHERE id = ?";

    $stmt = $connection->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $connection->error);
    }

    // Bind parameters
    $name = $input['name'];
    $name2 = $input['name2'] ?? '';
    $type = $input['type'];
    $capacity = $input['capacity'];
    $buildname = $input['buildname'] ?? '';
    $build_code = $input['build_code'] ?? '';
    $site_id = $input['site_id'];
    $campus_id = $input['campus_id'] ?? 1; // Default to 1 if not provided
    $id = $input['id'];

    $stmt->bind_param(
        "sssisssii",
        $name,
        $name2,
        $type,
        $capacity,
        $buildname,
        $build_code,
        $site_id,
        $campus_id,
        $id
    );

    // Execute the statement
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute statement: ' . $stmt->error);
    }

    if ($stmt->affected_rows > 0) {
        $response = [
            'success' => true, 
            'message' => 'Facility updated successfully',
            'affected_rows' => $stmt->affected_rows
        ];
    } else {
        // No rows affected, facility might not exist or no changes made
        $response = [
            'success' => false, 
            'message' => 'No changes made or facility not found',
            'affected_rows' => 0
        ];
    }

} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    logError("ERROR: " . $errorMessage . "\n" . $e->getTraceAsString());
    $response = [
        'success' => false,
        'message' => 'An error occurred: ' . $errorMessage,
        'error_details' => $e->getTraceAsString()
    ];
    http_response_code(500);
}

// Clean any output buffers
while (ob_get_level() > 0) {
    ob_end_clean();
}

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response, JSON_PRETTY_PRINT);

// Close database connection if it exists
if (isset($stmt) && $stmt instanceof mysqli_stmt) {
    $stmt->close();
}
if (isset($connection) && $connection instanceof mysqli) {
    $connection->close();
}

exit;
