<?php
session_start();
include('connection.php');

header('Content-Type: application/json');

// Get parameters
$timetable_id = isset($_GET['timetable_id']) ? $_GET['timetable_id'] : null;
$academic_year = isset($_GET['academic_year']) ? $_GET['academic_year'] : null;
$semester = isset($_GET['semester']) ? $_GET['semester'] : null;

if (!$timetable_id) {
    echo json_encode(['success' => false, 'message' => 'Timetable ID is required']);
    exit;
}

// Get timetable details
$timetable_query = "SELECT t.*, m.name as module_name, m.code as module_code 
                   FROM timetable t 
                   JOIN module m ON t.module_id = m.id 
                   WHERE t.id = ?";
$stmt = mysqli_prepare($connection, $timetable_query);
mysqli_stmt_bind_param($stmt, "i", $timetable_id);
mysqli_stmt_execute($stmt);
$timetable_result = mysqli_stmt_get_result($stmt);
$timetable = mysqli_fetch_assoc($timetable_result);

if (!$timetable) {
    echo json_encode(['success' => false, 'message' => 'Timetable not found']);
    exit;
}

// Get academic years
$year_query = "SELECT id, year_label FROM academic_year ORDER BY year_label DESC";
$year_result = mysqli_query($connection, $year_query);
$academic_years = '';
while ($year = mysqli_fetch_assoc($year_result)) {
    $selected = ($year['id'] == $academic_year) ? 'selected' : '';
    $academic_years .= "<option value='{$year['id']}' {$selected}>{$year['year_label']}</option>";
}

// Generate semester options
$semesters = '';
$semester_options = ['1', '2', '3'];
foreach ($semester_options as $sem) {
    $selected = ($sem == $semester) ? 'selected' : '';
    $semesters .= "<option value='{$sem}' {$selected}>Semester {$sem}</option>";
}

// Prepare response
$response = [
    'success' => true,
    'data' => [
        'module_name' => $timetable['module_name'],
        'module_code' => $timetable['module_code'],
        'academic_years' => $academic_years,
        'semesters' => $semesters
    ]
];

echo json_encode($response);
?> 