<?php
session_start();
include("connection.php");

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['room_id']) && isset($_POST['hostel_id'])) {
    $room_id = (int)$_POST['room_id'];
    $hostel_id = (int)$_POST['hostel_id'];
    $student_id = $_SESSION['student_id'];
    $student_regnumber = $_SESSION['student_regnumber'];
    $student_gender = $_SESSION['student_gender'];
    $student_year = $_SESSION['student_year'];
    $timestamp = isset($_POST['timestamp']) ? (int)$_POST['timestamp'] : 0;

    // Start transaction
    $connection->begin_transaction();

    try {
        // Check if student already has an application
        $check_query = "SELECT * FROM applications WHERE regnumber = ? AND status != 'rejected' FOR UPDATE";
        $check_stmt = $connection->prepare($check_query);
        $check_stmt->bind_param("s", $student_regnumber);
        $check_stmt->execute();
        $existing_application = $check_stmt->get_result();

        if ($existing_application->num_rows > 0) {
            throw new Exception("You already have a pending or approved application.");
        }

        // Check room availability with timestamp validation and lock the room
        $room_query = "SELECT r.*, h.id as hostel_id, 
                      (SELECT COUNT(*) FROM applications a WHERE a.room_id = r.id AND a.status != 'rejected') as current_applications,
                      (SELECT COUNT(*) FROM applications a WHERE a.room_id = r.id AND a.status = 'pending' AND a.created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)) as recent_applications
                      FROM rooms r 
                      JOIN hostels h ON r.hostel_id = h.id 
                      WHERE r.id = ? AND r.remain > 0 
                      FOR UPDATE";
        $room_stmt = $connection->prepare($room_query);
        $room_stmt->bind_param("i", $room_id);
        $room_stmt->execute();
        $room = $room_stmt->get_result()->fetch_assoc();

        if (!$room) {
            throw new Exception("Room is no longer available.");
        }

        // Validate timestamp (if provided) - ensure data isn't too old
        if ($timestamp > 0) {
            $current_time = time();
            if ($current_time - $timestamp > 300) { // 5 minutes threshold
                throw new Exception("Room information is too old. Please refresh the page and try again.");
            }
        }

        // Check if room is still available considering pending applications
        if ($room['remain'] == 0) {
            throw new Exception("Room is no longer available due to pending applications.");
        }

        // Check if room has too many recent applications
        if ($room['recent_applications'] >= $room['remain']) {
            throw new Exception("This room has too many pending applications. Please try another room.");
        }

        // Check if total applications (including pending) exceed room capacity
        if ($room['current_applications'] >= $room['number_of_beds']) {
            throw new Exception("Room has reached maximum capacity. Please try another room.");
        }

        // Check hostel attributes against student attributes
        $attributes_query = "SELECT * FROM hostel_attributes WHERE hostel_id = ?";
        $attributes_stmt = $connection->prepare($attributes_query);
        $attributes_stmt->bind_param("i", $hostel_id);
        $attributes_stmt->execute();
        $attributes = $attributes_stmt->get_result();

        $is_eligible = true;
        while ($attr = $attributes->fetch_assoc()) {
            if ($attr['attribute_key'] === 'gender' && $attr['attribute_value'] !== $student_gender) {
                $is_eligible = false;
                break;
            }
            if ($attr['attribute_key'] === 'year_of_study' && $attr['attribute_value'] != $student_year) {
                $is_eligible = false;
                break;
            }
        }

        if (!$is_eligible) {
            throw new Exception("You are not eligible for this hostel based on the requirements.");
        }
        // currect time in Rwanda
        $current_time = date('Y-m-d H:i:s');
        // Insert application with timestamp
        $insert_query = "INSERT INTO applications (regnumber, room_id, status, created_at, updated_at) 
                        VALUES (?, ?, 'pending', '$current_time', '$current_time')";
        $insert_stmt = $connection->prepare($insert_query);
        $insert_stmt->bind_param("si", $student_regnumber, $room_id);
        $insert_stmt->execute();

        // Update room availability
        $update_query = "UPDATE rooms SET remain = remain - 1 WHERE id = ? AND remain > 0";
        $update_stmt = $connection->prepare($update_query);
        $update_stmt->bind_param("i", $room_id);
        $update_stmt->execute();

        if ($update_stmt->affected_rows === 0) {
            throw new Exception("Room is no longer available. Please try another room.");
        }

        // Commit transaction
        $connection->commit();
        
        // Get student phone number and name
        $student_info_query = "SELECT phone, names FROM info WHERE regnumber = ?";
        $student_info_stmt = $connection->prepare($student_info_query);
        $student_info_stmt->bind_param("s", $student_regnumber);
        $student_info_stmt->execute();
        $student_info = $student_info_stmt->get_result()->fetch_assoc();

        // Get room and hostel details
        $room_details_query = "SELECT r.room_code, h.name as hostel_name 
                             FROM rooms r 
                             JOIN hostels h ON h.id = r.hostel_id 
                             WHERE r.id = ?";
        $room_details_stmt = $connection->prepare($room_details_query);
        $room_details_stmt->bind_param("i", $room_id);
        $room_details_stmt->execute();
        $room_details = $room_details_stmt->get_result()->fetch_assoc();

        // Prepare phone number
        $phone = $student_info['phone'];
        if (!str_starts_with($phone, '+')) {
            if (str_starts_with($phone, '0')) {
                $phone = '+250' . substr($phone, 1);
            }
        }

        // Prepare message
        $message = "Dear {$student_info['names']}, your hostel application for room {$room_details['room_code']} in {$room_details['hostel_name']} has been submitted successfully. Please upload your bank receipt within 48 hours, otherwise your application will be automatically rejected.";

        // Send SMS
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

        if ($httpCode !== 200) {
            error_log("SMS sending failed for phone {$phone}. Response: " . $response);
        }
        
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            // AJAX request
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true, 
                'message' => 'Your application has been submitted successfully! Please upload your bank receipt within 48 hours.',
                'room_id' => $room_id,
                'timestamp' => time()
            ]);
        } else {
            // Regular form submission
            $_SESSION['success_message'] = "Your application has been submitted successfully!\n\n" .
                "Room Details:\n" .
                "• Room Code: " . $room['room_code'] . "\n" .
                "• Hostel: " . $room['hostel_name'] . "\n" .
                "• Number of Beds: " . $room['number_of_beds'] . "\n" .
                "Important: Please upload your bank receipt within 48 hours to complete your application.";
            header("Location: index.php");
        }
        exit();

    } catch (Exception $e) {
        // Rollback transaction on error
        $connection->rollback();
        
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            // AJAX request
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false, 
                'message' => $e->getMessage(),
                'timestamp' => time()
            ]);
        } else {
            // Regular form submission
            $_SESSION['error_message'] = $e->getMessage();
            header("Location: index.php");
        }
        exit();
    }
} else {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        // AJAX request
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Invalid request method',
            'timestamp' => time()
        ]);
    } else {
        header("Location: index.php");
    }
    exit();
}

// Add SweetAlert script at the end of the file
if (isset($_SESSION['show_success_alert'])) {
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                title: 'Success!',
                text: 'Your application has been submitted successfully!',
                icon: 'success',
                confirmButtonText: 'OK'
            });
        });
    </script>";
    unset($_SESSION['show_success_alert']);
}
?> 