<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header
header('Content-Type: application/json');

try {
    require_once('connection.php');

    // Get pagination parameters
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;

    // Get filter parameters
    $search = isset($_GET['search']) ? mysqli_real_escape_string($connection, $_GET['search']) : '';
    $campus = isset($_GET['campus']) ? (int)$_GET['campus'] : 0;
    $type = isset($_GET['type']) ? mysqli_real_escape_string($connection, $_GET['type']) : '';

    // Build query
    $where = [];
    if ($search) {
        $where[] = "(f.name LIKE '%$search%' OR f.location LIKE '%$search%')";
    }
    if ($campus) {
        $where[] = "f.campus_id = $campus";
    }
    if ($type) {
        $where[] = "f.type = '$type'";
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM facility f $whereClause";
    $countResult = mysqli_query($connection, $countQuery);
    if (!$countResult) {
        throw new Exception("Count query failed: " . mysqli_error($connection));
    }
    $total = mysqli_fetch_assoc($countResult)['total'];

    // Get facilities
    $query = "SELECT f.*, c.name as campus_name 
              FROM facility f 
              LEFT JOIN campus c ON f.campus_id = c.id 
              $whereClause 
              ORDER BY f.name 
              LIMIT $offset, $limit";

    $result = mysqli_query($connection, $query);
    if (!$result) {
        throw new Exception("Main query failed: " . mysqli_error($connection));
    }

    $facilities = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $facilities[] = $row;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'facilities' => $facilities,
            'pagination' => [
                'total' => (int)$total,
                'per_page' => $limit,
                'current_page' => $page,
                'last_page' => ceil($total / $limit)
            ]
        ]
    ]);

} catch (Exception $e) {
    error_log("Facilities error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} 