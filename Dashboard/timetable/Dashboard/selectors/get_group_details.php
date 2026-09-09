<?php
// Prevent any output before headers
ob_start();

// Disable error reporting to prevent PHP errors from being output
error_reporting(0);
ini_set('display_errors', 0);

// Include database connection
include('connection.php');

// Set JSON header
header('Content-Type: application/json');

try {
    // Check connection
    if (!$connection) {
        throw new Exception("Database connection failed");
    }

    // Validate input
    if (!isset($_GET['group_id'])) {
        throw new Exception('Group ID is required');
    }

    $group_id = $_GET['group_id'];
    
    // Query to get group details with full hierarchy information
    $query = "SELECT 
                sg.id, sg.name as group_name, sg.size as group_size,
                i.year, i.month, i.size as intake_size,
                p.name as program_name, p.code as program_code,
                d.name as department_name,
                s.name as school_name,
                c.name as college_name,
                ca.name as campus_name
              FROM student_group sg
              JOIN intake i ON sg.intake_id = i.id
              JOIN program p ON i.program_id = p.id
              JOIN department d ON p.department_id = d.id
              JOIN school s ON d.school_id = s.id
              JOIN college c ON s.college_id = c.id
              JOIN campus ca ON c.campus_id = ca.id
              WHERE sg.id = ?";
              
    $stmt = mysqli_prepare($connection, $query);
    
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . mysqli_error($connection));
    }
    
    mysqli_stmt_bind_param($stmt, 'i', $group_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Query execution failed: " . mysqli_stmt_error($stmt));
    }
    
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result) {
        throw new Exception("Failed to get result: " . mysqli_error($connection));
    }
    
    $group = mysqli_fetch_assoc($result);
    
    if (!$group) {
        throw new Exception("Group not found");
    }
    
    // Format the response
    $response = [
        'id' => $group['id'],
        'name' => $group['group_name'],
        'size' => $group['group_size'],
        'intake' => [
            'year' => $group['year'],
            'month' => $group['month'],
            'size' => $group['intake_size']
        ],
        'program' => [
            'name' => $group['program_name'],
            'code' => $group['program_code']
        ],
        'department' => $group['department_name'],
        'school' => $group['school_name'],
        'college' => $group['college_name'],
        'campus' => $group['campus_name']
    ];
    
    // Clear any output buffer
    ob_clean();
    
    // Send JSON response
    echo json_encode(['success' => true, 'data' => $response]);
    
} catch (Exception $e) {
    // Clear any output buffer
    ob_clean();
    
    // Send error response
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// End output buffering and flush
ob_end_flush();
?> 