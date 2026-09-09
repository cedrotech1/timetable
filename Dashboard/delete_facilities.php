<?php
session_start();
include("connection.php");

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    $_SESSION['error'] = 'You must be logged in to perform this action.';
    header('Location: index.php');
    exit();
}

// Check if site_id is provided
if (!isset($_POST['site_id']) || !is_numeric($_POST['site_id'])) {
    $_SESSION['error'] = 'Invalid site ID';
    header('Location: manage_sites.php');
    exit();
}

$site_id = (int)$_POST['site_id'];
$redirect_url = 'upload_facilities.php?site_id=' . $site_id;

try {
    // Check if user has permission to manage this site
    $check_query = "SELECT id FROM site WHERE id = ?";
    $stmt = $connection->prepare($check_query);
    $stmt->bind_param("i", $site_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Site not found or access denied');
    }
    $stmt->close();

    // Handle delete all facilities for site
    if (isset($_POST['delete_all']) && $_POST['delete_all'] == '1') {
        $delete_query = "DELETE FROM facility WHERE site = ?";
        $stmt = $connection->prepare($delete_query);
        $stmt->bind_param("i", $site_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'All facilities have been deleted successfully.';
        } else {
            throw new Exception('Failed to delete all facilities');
        }
        $stmt->close();
    } 
    // Handle delete selected facilities
    elseif (isset($_POST['facility_ids']) && is_array($_POST['facility_ids']) && !empty($_POST['facility_ids'])) {
        // Sanitize facility IDs
        $facility_ids = array_map('intval', $_POST['facility_ids']);
        $placeholders = rtrim(str_repeat('?,', count($facility_ids)), ',');
        
        // First verify all facilities belong to this site
        $verify_query = "SELECT COUNT(*) as count FROM facility WHERE id IN ($placeholders) AND site = ?";
        $stmt = $connection->prepare($verify_query);
        
        // Create types and params for bind_param
        $types = str_repeat('i', count($facility_ids)) . 'i';
        $params = array_merge($facility_ids, [$site_id]);
        
        // Bind parameters
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] != count($facility_ids)) {
            throw new Exception('One or more selected facilities do not belong to this site');
        }
        $stmt->close();
        
        // Delete the facilities
        $delete_query = "DELETE FROM facility WHERE id IN ($placeholders)";
        $stmt = $connection->prepare($delete_query);
        
        // Bind parameters for delete
        $types = str_repeat('i', count($facility_ids));
        $stmt->bind_param($types, ...$facility_ids);
        
        if ($stmt->execute()) {
            $deleted = $stmt->affected_rows;
            $_SESSION['success'] = "Successfully deleted $deleted facility(ies).";
        } else {
            throw new Exception('Failed to delete selected facilities');
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = 'No facilities selected for deletion';
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
}

// Redirect back to the facilities page
header("Location: $redirect_url");
exit();
