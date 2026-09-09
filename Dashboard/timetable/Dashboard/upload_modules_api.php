<?php
include("connection.php");

$data = json_decode(file_get_contents("php://input"), true);
$response = ["success" => false];

if (!$data || !is_array($data)) {
  $response["message"] = "Invalid data format";
  echo json_encode($response);
  exit;
}

$connection->begin_transaction();

try {
  $stmt = $connection->prepare("INSERT INTO module (name, credits, code, year, semester, program_id) VALUES (?, ?, ?, ?, ?, ?)");

  foreach ($data as $module) {
    $stmt->bind_param("sisiii", $module['name'], $module['credits'], $module['code'], $module['year'], $module['semester'], $module['program_id']);
    $stmt->execute();
  }

  $connection->commit();
  $response["success"] = true;
} catch (Exception $e) {
  $connection->rollback();
  $response["message"] = "Database error: " . $e->getMessage();
}

echo json_encode($response);
?>
