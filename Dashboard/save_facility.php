<?php
session_start();
include("connection.php");
header('Content-Type: application/json');

// Support both JSON and form submissions
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input) || empty($input)) {
    $input = $_POST;
}

// Extract fields from request
$id = isset($input['id']) ? intval($input['id']) : 0;
$name = trim($input['name'] ?? '');
$name2 = trim($input['name2'] ?? '');
$type = trim($input['type'] ?? '');
$new_type = trim($input['new_type'] ?? '');
$capacity = isset($input['capacity']) ? intval($input['capacity']) : 0;
$site_id = isset($input['site_id']) ? intval($input['site_id']) : 0;
if ($site_id <= 0) {
    $site_id = isset($input['site']) ? intval($input['site']) : 0;
}
$buildname = trim($input['buildname'] ?? '');
$build_code = trim($input['build_code'] ?? '');

if ($type === '__custom__') {
    $type = $new_type;
}

// Basic validation
if (empty($name) || empty($type) || $capacity <= 0 || $site_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
    exit;
}

try {
    // Start transaction
    $connection->begin_transaction();

    // Check if facility with the same name exists in the same site
    $check_sql = "SELECT id FROM facility WHERE name = ? AND site = ?";
    $params = [$name, $site_id];
    $types = "si";
    
    if ($id > 0) {
        $check_sql .= " AND id != ?";
        $params[] = $id;
        $types .= "i";
    }
    
    $stmt = $connection->prepare($check_sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        throw new Exception('A facility with this code already exists in the selected site');
    }
    
    // Get campus_id from site
    $campus_stmt = $connection->prepare("SELECT campus FROM site WHERE id = ?");
    $campus_stmt->bind_param("i", $site_id);
    $campus_stmt->execute();
    $campus_result = $campus_stmt->get_result();
    
    if ($campus_result->num_rows === 0) {
        throw new Exception('Invalid site selected');
    }
    
    $campus_id = $campus_result->fetch_assoc()['campus'];
    
    // Prepare the SQL query
    if (isset($id) && $id > 0) {
        // Update existing facility
        $sql = "UPDATE facility SET 
                name = ?, name2 = ?, type = ?, capacity = ?, 
                site = ?, campus_id = ?, buildname = ?, build_code = ?
                WHERE id = ?";
        $stmt = $connection->prepare($sql);
        $stmt->bind_param("sssiiissi", 
            $name, $name2, $type, $capacity, 
            $site_id, $campus_id, $buildname, $build_code, $id
        );
    } else {
        // Insert new facility
        $sql = "INSERT INTO facility 
                (name, name2, type, capacity, site, campus_id, buildname, build_code) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $connection->prepare($sql);
        $stmt->bind_param("sssiiiss", 
            $name, $name2, $type, $capacity, 
            $site_id, $campus_id, $buildname, $build_code
        );
    }
    
    // Execute the query
    if (!$stmt->execute()) {
        throw new Exception('Failed to save facility: ' . $connection->error);
    }
    
    // Get the ID of the inserted/updated record
    $facility_id = (isset($id) && $id > 0) ? $id : $connection->insert_id;
    
    // Commit the transaction
    $connection->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Facility saved successfully',
        'id' => $facility_id
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
