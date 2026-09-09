<?php
// api_get_modules.php
// Returns JSON list of modules with pagination, search, filtering, & sorting
// Supports multiple program IDs

// Set headers first to ensure proper JSON response
header('Content-Type: application/json');

// Include connection and handle errors
include('connection.php');

// Check if connection was successful
if (!isset($connection) || !$connection) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed',
        'error' => mysqli_connect_error()
    ]);
    exit;
}

// Initialize variables
$types = '';
$params = [];

// Get and validate parameters
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['perPage']) ? max(1, (int)$_GET['perPage']) : 10;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$programFilter = isset($_GET['program_id']) ? $_GET['program_id'] : '0';
$yearFilter = isset($_GET['year']) ? trim($_GET['year']) : '';
$semesterFilter = isset($_GET['semester']) ? trim($_GET['semester']) : '';
$sortField = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$sortOrder = isset($_GET['order']) && strtolower($_GET['order']) === 'desc' ? 'DESC' : 'ASC';

$allowedSort = ['name','credits','code','year','semester'];
if (!in_array($sortField, $allowedSort)) $sortField = 'name';

$offset = ($page - 1) * $perPage;

// Build WHERE clauses
$where = [];

// Debug: Log the received program filter
// error_log('Received program filter: ' . print_r($programFilter, true));

// Handle program filter (can be single ID or comma-separated list)
if (!empty($programFilter)) {
    $programIds = array_map('intval', explode(',', $programFilter));
    $programIds = array_filter($programIds, function($id) { 
        // Debug: Log each program ID being processed
        // error_log('Processing program ID: ' . $id);
        return $id > 0; 
    });
    
    if (!empty($programIds)) {
        // Debug: Log the final program IDs being used for filtering
        // error_log('Filtering by program IDs: ' . implode(',', $programIds));
        $placeholders = implode(',', array_fill(0, count($programIds), '?'));
        $where[] = "m.program_id IN ($placeholders)";
        $types .= str_repeat('i', count($programIds));
        $params = array_merge($params, $programIds);
    }
}

// Other filters
if ($yearFilter !== '') {
    $where[] = "m.year = ?";
    $types .= 's';
    $params[] = $yearFilter;
}

if ($semesterFilter !== '') {
    $where[] = "m.semester = ?";
    $types .= 's';
    $params[] = $semesterFilter;
}

if ($search !== '') {
    $searchTerm = "%$search%";
    $where[] = "(m.name LIKE ? OR m.code LIKE ? OR m.year LIKE ? OR m.semester LIKE ?)";
    $types .= 'ssss';
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

$whereSql = count($where) ? "WHERE " . implode(" AND ", $where) : "";

// total count with the same JOIN and WHERE conditions
$countSql = "SELECT COUNT(*) AS total 
             FROM `module` m
             LEFT JOIN `program` p ON m.program_id = p.id
             $whereSql";

// Prepare and execute count query
$countStmt = mysqli_prepare($connection, $countSql);
if ($countStmt === false) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to prepare count query',
        'error' => mysqli_error($connection)
    ]);
    exit;
}

// Bind parameters if any
if (!empty($params)) {
    mysqli_stmt_bind_param($countStmt, $types, ...$params);
}

mysqli_stmt_execute($countStmt);
$countRes = mysqli_stmt_get_result($countStmt);
if (!$countRes) {
    echo json_encode(['success'=>false, 'message'=>'Count query failed', 'error'=>mysqli_error($connection)]);
    exit;
}
$row = mysqli_fetch_assoc($countRes);
$total = (int)$row['total'];

// fetch data with program name
$sql = "SELECT m.id, m.name, m.credits, m.code, m.year, m.semester, 
               m.program_id, p.name as program_name
        FROM `module` m
        LEFT JOIN `program` p ON m.program_id = p.id
        $whereSql
        ORDER BY $sortField $sortOrder
        LIMIT ?, ?";

// Add limit and offset to params
$types .= 'ii';
$params[] = $offset;
$params[] = $perPage;

// Prepare and execute main query
$stmt = mysqli_prepare($connection, $sql);
if ($stmt === false) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to prepare query',
        'error' => mysqli_error($connection)
    ]);
    exit;
}

// Bind parameters if any
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Query failed',
        'error' => mysqli_error($connection)
    ]);
    exit;
}

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $data[] = $row;
}

$pages = ceil($total / $perPage);

// Prepare the response with debug info
$response = [
    'success' => true,
    'data' => $data,
    'total' => $total,
    'pages' => $pages,
    'page' => $page,
    'perPage' => $perPage,
    'debug' => [
        'programFilter' => $programFilter,
        'programIds' => $programIds ?? [],
        'whereClause' => $whereSql,
        'query' => $sql,
        'params' => $params ?? []
    ]
];

// Output the response with pretty print for better readability in browser
echo json_encode($response, JSON_PRETTY_PRINT);
