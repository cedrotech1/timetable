<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

include('connection.php');



// Pagination parameters
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

// Search
$search = isset($_GET['search']) ? trim($_GET['search']) : "";

// Filters
$campus = isset($_GET['campus']) ? trim($_GET['campus']) : "";
$college = isset($_GET['college']) ? trim($_GET['college']) : "";
$school = isset($_GET['school']) ? trim($_GET['school']) : "";

// Build query
$sql = "SELECT id, names, email, phone, image, about, role, campus, college, school 
        FROM users 
        WHERE role != 'admin'";

// Search filter
if (!empty($search)) {
    $sql .= " AND (names LIKE ? OR email LIKE ? OR phone LIKE ?)";
}

// Campus filter
if (!empty($campus)) {
    $sql .= " AND campus = ?";
}

// College filter
if (!empty($college)) {
    $sql .= " AND college = ?";
}

// School filter
if (!empty($school)) {
    $sql .= " AND school = ?";
}

$sql .= " ORDER BY names ASC LIMIT ? OFFSET ?";

// Prepare statement
$stmt = $connection->prepare($sql);

// Dynamic bind parameters
$bindTypes = "";
$bindValues = [];

if (!empty($search)) {
    $bindTypes .= "sss";
    $searchTerm = "%$search%";
    $bindValues[] = &$searchTerm;
    $bindValues[] = &$searchTerm;
    $bindValues[] = &$searchTerm;
}

if (!empty($campus)) {
    $bindTypes .= "s";
    $bindValues[] = &$campus;
}

if (!empty($college)) {
    $bindTypes .= "s";
    $bindValues[] = &$college;
}

if (!empty($school)) {
    $bindTypes .= "s";
    $bindValues[] = &$school;
}

$bindTypes .= "ii";
$bindValues[] = &$limit;
$bindValues[] = &$offset;

// Bind dynamically
$stmt->bind_param($bindTypes, ...$bindValues);

// Execute query
$stmt->execute();
$result = $stmt->get_result();

// Fetch lecturers
$lecturers = [];
while ($row = $result->fetch_assoc()) {
    $lecturers[] = $row;
}

// Count total for pagination
$countSql = "SELECT COUNT(*) AS total FROM users WHERE role = 'lecturer'";
if (!empty($search)) {
    $countSql .= " AND (names LIKE '%$search%' OR email LIKE '%$search%' OR phone LIKE '%$search%')";
}
if (!empty($campus)) {
    $countSql .= " AND campus = '$campus'";
}
if (!empty($college)) {
    $countSql .= " AND college = '$college'";
}
if (!empty($school)) {
    $countSql .= " AND school = '$school'";
}

$countResult = $connection->query($countSql);
$total = $countResult->fetch_assoc()['total'];

echo json_encode([
    "success" => true,
    "data" => $lecturers,
    "pagination" => [
        "total" => (int)$total,
        "page" => $page,
        "limit" => $limit,
        "pages" => ceil($total / $limit)
    ]
]);

$stmt->close();
$connection->close();
?>
