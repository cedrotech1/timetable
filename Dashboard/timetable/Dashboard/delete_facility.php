<?php
session_start();
include("connection.php");
header('Content-Type: application/json');

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

// Get the action type (delete_single or delete_all)
$action = $_POST['action'] ?? '';
$facility_id = intval($_POST['facility_id'] ?? 0);
$delete_type = $_POST['type'] ?? ''; // 'campus' or 'site'
$delete_id = intval($_POST['id'] ?? 0);

// Validate action and parameters
if (!in_array($action, ['delete_single', 'delete_all']) || 
    ($action === 'delete_all' && !in_array($delete_type, ['campus', 'site']))) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

// Verify delete_id is provided for delete_all action
if ($action === 'delete_all' && $delete_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID provided']);
    exit;
}

try {
    $connection->begin_transaction();
    
    if ($action === 'delete_single' && $facility_id > 0) {
        // Delete a single facility
        $stmt = $connection->prepare("DELETE FROM facility WHERE id = ?");
        $stmt->bind_param("i", $facility_id);
        $stmt->execute();
        
        if ($stmt->affected_rows === 0) {
            throw new Exception('Facility not found or already deleted');
        }
        
        $message = 'Facility deleted successfully';
    } 
    elseif ($action === 'delete_all') {
        if ($delete_type === 'campus') {
            // First, get all site IDs for this campus to clean up related data
            $siteStmt = $connection->prepare("SELECT id FROM site WHERE campus = ?");
            $siteStmt->bind_param("i", $delete_id);
            $siteStmt->execute();
            $siteResult = $siteStmt->get_result();
            $siteIds = [];
            while ($row = $siteResult->fetch_assoc()) {
                $siteIds[] = $row['id'];
            }
            
            if (!empty($siteIds)) {
                // Delete facilities for all sites in this campus
                $placeholders = implode(',', array_fill(0, count($siteIds), '?'));
                $types = str_repeat('i', count($siteIds));
                $stmt = $connection->prepare("DELETE FROM facility WHERE site IN ($placeholders)");
                $stmt->bind_param($types, ...$siteIds);
                $stmt->execute();
                $deleted_count = $stmt->affected_rows;
            } else {
                $deleted_count = 0;
            }
            
            $message = "All facilities from the campus have been deleted";
        } 
        elseif ($delete_type === 'site') {
            // Delete all facilities from a specific site
            $stmt = $connection->prepare("DELETE FROM facility WHERE site = ?");
            $stmt->bind_param("i", $delete_id);
            $stmt->execute();
            $deleted_count = $stmt->affected_rows;
            
            $message = 'All facilities from the selected site have been deleted';
        }
        
        $message .= ". Deleted: $deleted_count facilities";
    }
    
    $connection->commit();
    echo json_encode([
        'success' => true, 
        'message' => $message,
        'deleted_count' => $deleted_count ?? 0
    ]);
    
} catch (Exception $e) {
    if (isset($connection)) {
        $connection->rollback();
    }
    error_log('Delete facility error: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}
?>
