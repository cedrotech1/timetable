<?php
session_start();
include("connection.php");
header('Content-Type: application/json');

$oldCode = isset($_POST['old_code']) ? trim($_POST['old_code']) : '';
$newCode = isset($_POST['new_code']) ? trim($_POST['new_code']) : '';
$syncBuildName = isset($_POST['sync_buildname']) && $_POST['sync_buildname'] ? true : false;

if ($oldCode === '' || $newCode === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Both old and new building codes are required.'
    ]);
    exit;
}

if ($oldCode === $newCode) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Old and new building codes must be different.'
    ]);
    exit;
}

try {
    $connection->begin_transaction();

    if ($syncBuildName) {
        $sql = "UPDATE facility SET build_code = ?, buildname = ? WHERE build_code = ?";
        $stmt = $connection->prepare($sql);
        $stmt->bind_param('sss', $newCode, $newCode, $oldCode);
    } else {
        $sql = "UPDATE facility SET build_code = ? WHERE build_code = ?";
        $stmt = $connection->prepare($sql);
        $stmt->bind_param('ss', $newCode, $oldCode);
    }

    if (!$stmt->execute()) {
        throw new Exception('Failed to update facility records.');
    }

    $affectedRows = $stmt->affected_rows;
    $connection->commit();

    if ($affectedRows === 0) {
        echo json_encode([
            'success' => true,
            'message' => 'No facilities were updated. Please confirm the current building code is correct.'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => "Updated {$affectedRows} facility record(s)."
        ]);
    }
} catch (Exception $e) {
    $connection->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
