<?php
include('connection.php');

function deleteTimetable($timetable_id) {
    global $connection;
    
    // Start transaction
    $connection->begin_transaction();
    
    try {
        // Delete from timetable_groups
        $sql1 = "DELETE FROM timetable_groups WHERE timetable_id = ?";
        $stmt1 = $connection->prepare($sql1);
        $stmt1->bind_param("i", $timetable_id);
        $stmt1->execute();
        
        // Delete from timetable_sessions
        $sql2 = "DELETE FROM timetable_sessions WHERE timetable_id = ?";
        $stmt2 = $connection->prepare($sql2);
        $stmt2->bind_param("i", $timetable_id);
        $stmt2->execute();
        
        // Finally delete from timetable
        $sql3 = "DELETE FROM timetable WHERE id = ?";
        $stmt3 = $connection->prepare($sql3);
        $stmt3->bind_param("i", $timetable_id);
        $stmt3->execute();
        
        // If everything is successful, commit the transaction
        $connection->commit();
        return true;
    } catch (Exception $e) {
        // If there's an error, rollback the transaction
        $connection->rollback();
        return false;
    }
}

// Handle the AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = array('success' => false, 'message' => '');
    
    if (isset($_POST['timetable_id'])) {
        $timetable_id = $_POST['timetable_id'];
        
        if (deleteTimetable($timetable_id)) {
            $response['success'] = true;
            $response['message'] = 'Timetable deleted successfully';
        } else {
            $response['message'] = 'Error deleting timetable';
        }
    } else {
        $response['message'] = 'Timetable ID is required';
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?> 