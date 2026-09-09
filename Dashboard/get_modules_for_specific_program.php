<?php
// get_modules_for_specific_program.php
// Returns JSON list of modules for a specific program
// Requires: include('connection.php'); which defines $connection (mysqli)

header('Content-Type: application/json');
include('connection.php');

// Get program ID from query parameters
$programId = isset($_GET['program_id']) ? (int)$_GET['program_id'] : 0;

if (!$programId) {
    echo json_encode(['success' => false, 'error' => 'Program ID is required']);
    exit;
}

// Prepare and execute the query
$query = "SELECT m.id, m.name, m.credits, m.code, m.year, m.semester, 
                 p.name as program_name, p.id as program_id
          FROM `module` m
          JOIN `program` p ON m.program_id = p.id
          WHERE m.program_id = ?
          ORDER BY m.year, m.semester, m.name";

$stmt = $connection->prepare($query);
$stmt->bind_param("i", $programId);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    echo json_encode(['success' => false, 'error' => 'Query failed: ' . $connection->error]);
    exit;
}

$modules = [];
while ($row = $result->fetch_assoc()) {
    $modules[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'code' => $row['code'],
        'credits' => $row['credits'],
        'year' => $row['year'],
        'semester' => $row['semester'],
        'program_id' => $row['program_id'],
        'program_name' => $row['program_name']
    ];
}

echo json_encode([
    'success' => true,
    'data' => $modules
]);

$stmt->close();
$connection->close();
?>
