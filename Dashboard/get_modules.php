<?php
// get_modules.php
// Returns JSON list of modules with pagination, search, filtering, & sorting
// Requires: include('connection.php'); which defines $connection (mysqli)

header('Content-Type: application/json');
include('connection.php');

// params
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['perPage']) ? max(1, (int)$_GET['perPage']) : 10000;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$programFilter = isset($_GET['program_id']) ? (int)$_GET['program_id'] : 0;
$yearFilter = isset($_GET['year']) ? trim($_GET['year']) : '';
$semesterFilter = isset($_GET['semester']) ? trim($_GET['semester']) : '';
$sortField = isset($_GET['sortField']) ? $_GET['sortField'] : 'name';
$sortOrder = isset($_GET['sortOrder']) && strtolower($_GET['sortOrder']) === 'desc' ? 'DESC' : 'ASC';

$allowedSort = ['name','credits','code','year','semester'];
if (!in_array($sortField, $allowedSort)) $sortField = 'name';

$offset = ($page - 1) * $perPage;

// Build WHERE clauses
$where = [];
if ($programFilter > 0) $where[] = "program_id = " . intval($programFilter);
if ($yearFilter !== '') $where[] = "year = '" . mysqli_real_escape_string($connection, $yearFilter) . "'";
if ($semesterFilter !== '') $where[] = "semester = '" . mysqli_real_escape_string($connection, $semesterFilter) . "'";
if ($search !== '') {
    $s = mysqli_real_escape_string($connection, $search);
    $where[] = "(name LIKE '%$s%' OR code LIKE '%$s%' OR year LIKE '%$s%' OR semester LIKE '%$s%')";
}
$whereSql = count($where) ? "WHERE " . implode(" AND ", $where) : "";

// total count
$countSql = "SELECT COUNT(*) AS total FROM `module` $whereSql";
$countRes = mysqli_query($connection, $countSql);
if (!$countRes) {
    echo json_encode(['success'=>false, 'message'=>'Count query failed', 'error'=>mysqli_error($connection)]);
    exit;
}
$row = mysqli_fetch_assoc($countRes);
$total = (int)$row['total'];

// fetch data
$sql = "SELECT id, name, credits, code, year, semester, program_id
        FROM `module`
        $whereSql
        ORDER BY $sortField $sortOrder
        LIMIT $offset, $perPage";
$res = mysqli_query($connection, $sql);
if (!$res) {
    echo json_encode(['success'=>false, 'message'=>'Query failed', 'error'=>mysqli_error($connection)]);
    exit;
}

$modules = [];
while ($r = mysqli_fetch_assoc($res)) {
    $modules[] = $r;
}

echo json_encode([
    'success' => true,
    'data' => $modules,
    'total' => $total,
    'page' => $page,
    'perPage' => $perPage,
    'pages' => $perPage ? ceil($total / $perPage) : 1,
]);
