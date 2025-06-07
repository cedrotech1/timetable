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

    // Get filter parameters
    $search = isset($_GET['search']) ? mysqli_real_escape_string($connection, $_GET['search']) : '';
    $program = isset($_GET['program']) ? (int)$_GET['program'] : 0;
    $year = isset($_GET['year']) ? (int)$_GET['year'] : 0;
    
    // Pagination parameters
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;

    // Build the base query
    $query = "SELECT m.*, p.name as program_name, p.code as program_code 
              FROM module m 
              LEFT JOIN program p ON m.program_id = p.id 
              WHERE 1=1";
    
    // Add search condition
    if (!empty($search)) {
        $query .= " AND (m.name LIKE '%$search%' OR m.code LIKE '%$search%')";
    }
    
    // Add program filter
    if ($program > 0) {
        $query .= " AND m.program_id = $program";
    }
    
    // Add year filter
    if ($year > 0) {
        $query .= " AND m.year = $year";
    }

    // Get total count for pagination
    $count_query = str_replace("m.*, p.name as program_name, p.code as program_code", "COUNT(*) as total", $query);
    $count_result = mysqli_query($connection, $count_query);
    $total = mysqli_fetch_assoc($count_result)['total'];

    // Add pagination
    $query .= " ORDER BY m.code LIMIT $offset, $limit";

    // Execute the query
    $result = mysqli_query($connection, $query);
    if (!$result) {
        throw new Exception("Query failed: " . mysqli_error($connection));
    }

    // Fetch all modules
    $modules = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $modules[] = $row;
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
            'modules' => $modules,
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