<?php
// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
$timeLimit=1;
// Set up logging
$log_file = __DIR__ . '/cron_log.txt';
function writeLog($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

writeLog("Script started");
writeLog("Time limit set to: " . $timeLimit . " minutes");

// Check if connection file exists
$connection_file = __DIR__ . '/../connection.php';
if (!file_exists($connection_file)) {
    writeLog("ERROR: connection.php file not found at: " . $connection_file);
    die("Connection file not found");
}

include($connection_file);
writeLog("Connection file loaded successfully");

// Check database connection
if (!$connection) {
    writeLog("ERROR: Database connection failed: " . mysqli_connect_error());
    die("Database connection failed");
}

writeLog("Database connection successful");

// Function to send SMS using Pindo API
function sendSMS($phone, $message) {
    if (!str_starts_with($phone, '+')) {
        if (str_starts_with($phone, '0')) {
            $phone = '+250' . substr($phone, 1);
        }
    }
    
    $sms_data = [
        'to' => $phone,
        'text' => $message,
        'sender' => 'PindoTest'
    ];
    
    $ch = curl_init('https://api.pindo.io/v1/sms/');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($sms_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer eyJhbGciOiJIUzUxMiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE4MzcxNzUzMTIsImlhdCI6MTc0MjQ4MDkxMiwiaWQiOiJ1c2VyXzAxSlBTWjlDMTZCTUtZQzZLSkdWRkhQOTBNIiwicmV2b2tlZF90b2tlbl9jb3VudCI6MH0.KjgMZ0ht_NhUbil_3kIgHHByJSokufd2IZdC9-PYeXdkJkan4Rv8DMi0jlHXfZnyh_52bOizk9nTR3QOEBU5ZA',
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    writeLog("SMS sent to $phone with status code: $httpCode");
    return $httpCode === 200;
}

try {
    // Get all pending applications that are older than timeLimit minutes
    $query = "SELECT a.*, i.phone, i.names, r.room_code, h.name as hostel_name 
              FROM applications a 
              JOIN info i ON i.regnumber = a.regnumber 
              JOIN rooms r ON r.id = a.room_id 
              JOIN hostels h ON h.id = r.hostel_id 
              WHERE a.status = 'pending' 
              AND TIMESTAMPDIFF(MINUTE, a.created_at, NOW()) >= $timeLimit";

    writeLog("Executing query: " . $query);
    
    $result = mysqli_query($connection, $query);
    
    if (!$result) {
        throw new Exception("Database query failed: " . mysqli_error($connection));
    }

    $count = mysqli_num_rows($result);
    writeLog("Found $count applications to process");

    if ($count > 0) {
        while ($application = mysqli_fetch_assoc($result)) {
            writeLog("Processing application ID: {$application['id']} for student: {$application['regnumber']}");
            writeLog("Application created at: {$application['created_at']}");
            writeLog("Room ID: {$application['room_id']}");
            
            // Start transaction
            mysqli_begin_transaction($connection);
            
            try {
                // Send SMS notification
                $message = "Dear {$application['names']}, your hostel application for room {$application['room_code']} in {$application['hostel_name']} has been automatically deleted because you didn't upload a payment receipt within $timeLimit minutes. You can apply again.";
                $sms_sent = sendSMS($application['phone'], $message);
                writeLog("SMS sending status: " . ($sms_sent ? "Success" : "Failed"));
                
                // Update student info to set current_application as rejected
                $update_info = "UPDATE info SET current_application = 'auto-rejected' WHERE regnumber = '{$application['regnumber']}'";
                writeLog("Executing query: " . $update_info);
                if (!mysqli_query($connection, $update_info)) {
                    throw new Exception("Failed to update student info: " . mysqli_error($connection));
                }
                writeLog("Updated student info for {$application['regnumber']}");
                
                // Increment room remain
                $update_room = "UPDATE rooms SET remain = remain + 1 WHERE id = {$application['room_id']}";
                writeLog("Executing query: " . $update_room);
                if (!mysqli_query($connection, $update_room)) {
                    throw new Exception("Failed to update room remain: " . mysqli_error($connection));
                }
                writeLog("Updated room remain for room ID: {$application['room_id']}");
                
                // Delete the application
                $delete_app = "DELETE FROM applications WHERE id = {$application['id']}";
                writeLog("Executing query: " . $delete_app);
                if (!mysqli_query($connection, $delete_app)) {
                    throw new Exception("Failed to delete application: " . mysqli_error($connection));
                }
                writeLog("Deleted application ID: {$application['id']}");
                
                // Commit transaction
                mysqli_commit($connection);
                writeLog("Successfully processed application ID: {$application['id']}");
                
            } catch (Exception $e) {
                // Rollback transaction on error
                mysqli_rollback($connection);
                writeLog("Error processing application {$application['id']}: " . $e->getMessage());
            }
        }
    } else {
        writeLog("No applications found that are older than $timeLimit minutes");
    }
} catch (Exception $e) {
    writeLog("Fatal error: " . $e->getMessage());
}

mysqli_close($connection);
writeLog("Script completed");
?> 