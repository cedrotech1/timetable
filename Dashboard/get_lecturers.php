<?php
include('connection.php');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Get filter parameters
    $search = isset($_GET['search']) ? mysqli_real_escape_string($connection, $_GET['search']) : '';
    $campus = isset($_GET['campus']) ? (int)$_GET['campus'] : 0;
    $status = isset($_GET['status']) ? mysqli_real_escape_string($connection, $_GET['status']) : '';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;

    // Build the base query
    $query = "SELECT u.*, c.name as campus_name 
              FROM users u 
              LEFT JOIN campus c ON u.campus = c.id 
              WHERE u.role = 'lecturer'";

    // Add search condition
    if (!empty($search)) {
        $query .= " AND (u.names LIKE '%$search%' OR u.email LIKE '%$search%')";
    }

    // Add campus filter
    if ($campus > 0) {
        $query .= " AND u.campus = $campus";
    }

    // Add status filter
    if (!empty($status)) {
        $query .= " AND u.active = " . ($status === 'active' ? '1' : '0');
    }

    // Add order by
    $query .= " ORDER BY u.names ASC";

    // Get total count for pagination
    $count_query = str_replace("u.*, c.name as campus_name", "COUNT(*) as total", $query);
    $count_result = mysqli_query($connection, $count_query);
    $total = mysqli_fetch_assoc($count_result)['total'];

    // Add pagination
    $query .= " LIMIT $offset, $limit";

    // Execute the main query
    $result = mysqli_query($connection, $query);

    if (!$result) {
        throw new Exception("Query failed: " . mysqli_error($connection));
    }

    // Fetch all lecturers
    $lecturers = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $lecturers[] = $row;
    }

    // Calculate pagination info
    $total_pages = ceil($total / $limit);
    $pagination = [
        'current_page' => $page,
        'last_page' => $total_pages,
        'per_page' => $limit,
        'total' => $total
    ];

    // Return success response
    echo json_encode([
        'success' => true,
        'data' => [
            'lecturers' => $lecturers,
            'pagination' => $pagination
        ]
    ]);

} catch (Exception $e) {
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 