<?php
session_start(); 

include('connection.php');
header('Content-Type: application/json');
$user_id = $_SESSION['id'];
$stmt = $connection->prepare("SELECT school FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$school = $result->fetch_assoc();
$school_id = $school['school'];

$schoolId = $school_id ?? null;
if (!$schoolId) {
    echo json_encode(['success' => false, 'message' => 'No school ID in session']);
    exit;
}
// Pagination params
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = isset($_GET['perPage']) ? max(1, intval($_GET['perPage'])) : 10;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$minCapacity = isset($_GET['minCapacity']) ? max(0, intval($_GET['minCapacity'])) : 0;

$offset = ($page - 1) * $perPage;

$searchEsc = mysqli_real_escape_string($connection, $search);
$schoolIdEsc = intval($schoolId);
$minCapacityEsc = intval($minCapacity);

// Count total matching facilities
$countSql = "
    SELECT COUNT(*) as total FROM facility f
    JOIN site_school ss ON ss.site_id = f.site
    WHERE ss.school_id = $schoolIdEsc
      AND f.capacity >= $minCapacityEsc
";

if ($search !== '') {
    $countSql .= " AND f.name LIKE '%$searchEsc%' ";
}

$countResult = mysqli_query($connection, $countSql);
if (!$countResult) {
    echo json_encode(['success' => false, 'message' => 'Count query failed']);
    exit;
}

$row = mysqli_fetch_assoc($countResult);
$total = (int)$row['total'];

// Fetch paginated facilities with site name
$sql = "
    SELECT f.id, f.name, f.type, f.capacity, s.name AS site_name
    FROM facility f
    JOIN site_school ss ON ss.site_id = f.site
    JOIN site s ON s.id = f.site
    WHERE ss.school_id = $schoolIdEsc
      AND f.capacity >= $minCapacityEsc
";

if ($search !== '') {
    $sql .= " AND f.name LIKE '%$searchEsc%' ";
}

$sql .= " ORDER BY f.name ASC LIMIT $offset, $perPage";

$result = mysqli_query($connection, $sql);
if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Facilities query failed']);
    exit;
}

$facilities = [];
while ($facility = mysqli_fetch_assoc($result)) {
    $facilities[] = $facility;
}

echo json_encode([
    'success' => true,
    'data' => $facilities,
    'total' => $total,
    'page' => $page,
    'perPage' => $perPage,
    'pages' => ceil($total / $perPage),
]);
