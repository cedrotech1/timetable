<?php
session_start();
include('connection.php');

// Get filter parameters
$academic_year = isset($_GET['academic_year']) ? (int)$_GET['academic_year'] : null;
$program = isset($_GET['program']) ? (int)$_GET['program'] : null;

// Build the query
$query = "SELECT t.*, m.name as module_name, m.code as module_code, f.name as facility_name 
          FROM timetable t 
          JOIN module m ON t.module_id = m.id 
          JOIN facility f ON t.facility_id = f.id 
          WHERE 1=1";

if ($academic_year) {
    $query .= " AND t.academic_year_id = " . $academic_year;
}

if ($program) {
    $query .= " AND m.program_id = " . $program;
}

$result = mysqli_query($connection, $query);
$events = [];

while ($row = mysqli_fetch_assoc($result)) {
    $events[] = [
        'id' => $row['id'],
        'title' => $row['module_name'] . ' (' . $row['module_code'] . ') - ' . $row['facility_name'],
        'start' => $row['start_time'],
        'end' => $row['end_time'],
        'module_id' => $row['module_id'],
        'facility_id' => $row['facility_id']
    ];
}

header('Content-Type: application/json');
echo json_encode($events); 