<?php
require_once 'components/hostel_card.php';

header('Content-Type: application/json');

try {
    if (!isset($_GET['hostel_id'])) {
        throw new Exception('Hostel ID is required');
    }

    $hostel_id = intval($_GET['hostel_id']);
    
    // Get hostel statistics
    $stats = getHostelStats($connection, $hostel_id);
    
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 