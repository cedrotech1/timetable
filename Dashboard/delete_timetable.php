<?php
session_start();
header('Content-Type: application/json');
require_once 'connection.php';

$timetableId = isset($_GET['id']) ? intval($_GET['id']) : 0;

$connection->begin_transaction();

try {
    $tables = [
        'timetable_sessions',
        'timetable_lecturers',
        'timetable_groups'
    ];

    foreach ($tables as $table) {
        $sql = "DELETE FROM {$table} WHERE timetable_id = ?";
        $stmt = $connection->prepare($sql);
        $stmt->bind_param("i", $timetableId);
        if (!$stmt->execute()) {
            throw new Exception($stmt->error ?: 'Failed to delete related records');
        }
        $stmt->close();
    }

    $sql = "DELETE FROM timetable WHERE id = ?";
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("i", $timetableId);
    if (!$stmt->execute()) {
        throw new Exception($stmt->error ?: 'Failed to delete timetable');
    }
    $stmt->close();

    $connection->commit();
    echo json_encode(["success" => true]);
} catch (Exception $e) {
    $connection->rollback();
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
