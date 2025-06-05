<?php
session_start();
include("connection.php");

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['application_id'])) {
    $application_id = (int)$_POST['application_id'];
    $student_regnumber = $_SESSION['student_regnumber'];
    
    // Verify that this application belongs to the student
    $verify_query = "SELECT slep FROM applications WHERE id = ? AND regnumber = ?";
    $verify_stmt = $connection->prepare($verify_query);
    $verify_stmt->bind_param("is", $application_id, $student_regnumber);
    $verify_stmt->execute();
    $application = $verify_stmt->get_result()->fetch_assoc();
    
    if (!$application || !$application['slep']) {
        $_SESSION['error_message'] = "Invalid application or no receipt found.";
        header("Location: index.php");
        exit();
    }
    
    // Delete the file
    $filepath = "../uploads/" . $application['slep'];
    if (file_exists($filepath)) {
        unlink($filepath);
    }
    
    // Update application to remove receipt
    $update_query = "UPDATE applications SET 
                    slep = NULL,
                    status = 'pending'
                    WHERE id = ?";
    $update_stmt = $connection->prepare($update_query);
    $update_stmt->bind_param("i", $application_id);
    
    if ($update_stmt->execute()) {
        $_SESSION['success_message'] = "Receipt deleted successfully. You can now upload a new receipt.";
    } else {
        $_SESSION['error_message'] = "Error deleting receipt. Please try again.";
    }
    
    header("Location: index.php");
    exit();
} else {
    header("Location: index.php");
    exit();
}
?> 