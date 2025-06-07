<?php
include('connection.php');
header('Content-Type: application/json');

try {
    $result = mysqli_query($connection, "SELECT id, name FROM campus ORDER BY name");
    if (!$result) {
        throw new Exception(mysqli_error($connection));
    }

    $campuses = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $campuses[] = $row;
    }

    echo json_encode([
        'success' => true,
        'data' => $campuses
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 