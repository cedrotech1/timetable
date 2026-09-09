<?php
// import_staff_users.php


// connection.php

// Database credentials (replace with your actual values)
$dbHost = 'localhost';
$dbUser = 'root';
$dbPassword = 'Ur@2312';
$dbName = 'timetable-v3';
$dbPort = 3306; // Default MySQL port

$connection = mysqli_connect($dbHost, $dbUser, $dbPassword, $dbName, $dbPort);

if (!$connection) {
    die('Database connection failed: ' . mysqli_connect_error());
}


// Staff API endpoint
$apiUrl = "http://172.20.250.25/staff/Dashboard/users_api.php";

// Fetch data from API
$response = file_get_contents($apiUrl);
if ($response === FALSE) {
    die("Error fetching API");
}

$data = json_decode($response, true);

if (!$data || $data["status"] !== "success") {
    die("Invalid API response");
}

$users = $data["data"];
$imported = 0;
$skipped = 0;

foreach ($users as $index => $user) {
    // Check for required fields
    $missingFields = [];
    if (empty($user['names'])) $missingFields[] = 'names';
    if (empty($user['email'])) $missingFields[] = 'email';
    if (empty($user['campus'])) $missingFields[] = 'campus';
    
    if (!empty($missingFields)) {
        error_log(sprintf(
            "Skipping user #%d: Missing required fields: %s", 
            $index + 1, 
            implode(', ', $missingFields)
        ));
        $skipped++;
        continue;
    }

    // Make staff_number optional and handle NULL values
    $staff_number = !empty($user['staff_number']) ? "'" . mysqli_real_escape_string($connection, $user['staff_number']) . "'" : 'NULL';
    $names = mysqli_real_escape_string($connection, $user['names']);
    $email = mysqli_real_escape_string($connection, $user['email']);
    $ur_email = isset($user['ur_email']) ? mysqli_real_escape_string($connection, $user['ur_email']) : '';
    $phone = isset($user['phone']) ? mysqli_real_escape_string($connection, $user['phone']) : '';
    $password = isset($user['password']) ? mysqli_real_escape_string($connection, $user['password']) : password_hash('default_password', PASSWORD_DEFAULT);
    $active = isset($user['active']) ? intval($user['active']) : 1;
    $campus_name = mysqli_real_escape_string($connection, $user['campus']);
    $college_name = isset($user['college']) ? mysqli_real_escape_string($connection, $user['college']) : null;

    // Get campus id from campus name
    $campusQuery = "SELECT id FROM campus WHERE name = '$campus_name' LIMIT 1";
    $campusResult = mysqli_query($connection, $campusQuery);
    $campusId = null;
    if ($campusResult && mysqli_num_rows($campusResult) > 0) {
        $campusRow = mysqli_fetch_assoc($campusResult);
        $campusId = $campusRow['id'];
    } else {
        $campusId = null; // if campus not found
    }

    // Skip if no staff number or campus id
    if (empty($staff_number) || !$campusId) {
        $skipped++;
        continue;
    }

    // Get college ID if college name is provided
    $collegeId = 'NULL';
    if (!empty($college_name)) {
        $collegeQuery = "SELECT id FROM college WHERE campus_id = '$campusId' AND LOWER(name) = LOWER('$college_name') LIMIT 1";
        $collegeResult = mysqli_query($connection, $collegeQuery);
        if ($collegeResult && mysqli_num_rows($collegeResult) > 0) {
            $collegeRow = mysqli_fetch_assoc($collegeResult);
            $collegeId = $collegeRow['id'];
        }
    }

    // Check if user already exists by email (since email is our unique identifier)
    $checkQuery = "SELECT id FROM users WHERE email = '$email' LIMIT 1";
    $checkResult = mysqli_query($connection, $checkQuery);
    $existingUserId = null;
    
    if ($checkResult && mysqli_num_rows($checkResult) > 0) {
        $row = mysqli_fetch_assoc($checkResult);
        $existingUserId = $row['id'];
    }

    if ($existingUserId) {
        // Update existing user
        $updateQuery = "
            UPDATE users SET 
                staff_number = $staff_number,
                names = '$names',
                email = '$email',
                ur_email = '$ur_email',
                phone = '$phone',
                campus = '$campusId',
                active = '$active',
                college = $collegeId
            WHERE id = $existingUserId
        ";
        
        if (mysqli_query($connection, $updateQuery)) {
            $imported++;
        } else {
            $error = mysqli_error($connection);
            error_log(sprintf(
                "Failed to update user ID %s (Email: %s): %s - Query: %s", 
                $existingUserId,
                $email,
                $error,
                $updateQuery
            ));
            $skipped++;
        }
    } else {
        // Insert new user with NULL handling for staff_number
        $insertQuery = "
            INSERT INTO users (staff_number, names, email, ur_email, phone, password, campus, active, college) 
            VALUES ($staff_number, '$names', '$email', '$ur_email', '$phone', '$password', '$campusId', '$active', $collegeId)
        ";

        if (mysqli_query($connection, $insertQuery)) {
            $imported++;
        } else {
            $error = mysqli_error($connection);
            error_log(sprintf(
                "Failed to insert user (Email: %s): %s - Query: %s", 
                $email,
                $error,
                $insertQuery
            ));
            $skipped++;
        }
    }
}

echo json_encode([
    "status" => "completed",
    "imported" => $imported,
    "skipped" => $skipped
]);

mysqli_close($connection);
?>
