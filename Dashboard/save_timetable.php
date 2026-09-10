<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log the request
file_put_contents('save_timetable_debug.log', "[" . date('Y-m-d H:i:s') . "] New request: " . json_encode($_SERVER['REQUEST_METHOD']) . "\n", FILE_APPEND);

include('connection.php');

// Function to send JSON response
function sendResponse($status, $message = '', $data = []) {
    $response = ['status' => $status];
    if ($message) $response['message'] = $message;
    if (!empty($data)) $response = array_merge($response, $data);
    
    // Log the response
    file_put_contents('save_timetable_debug.log', "[" . date('Y-m-d H:i:s') . "] Response: " . json_encode($response) . "\n", FILE_APPEND);
    
    echo json_encode($response);
    exit;
}

// Ensure logged in
if (!isset($_SESSION['id'])) {
    sendResponse('error', 'Not logged in.');
}
$user_id = intval($_SESSION['id']);

// Helpers
function time_to_sql($t) {
    // "09:00" -> "09:00:00"
    if (preg_match('/^\d{2}:\d{2}$/', $t)) return $t . ':00';
    // already 09:00:00 or other
    return $t;
}
function fetch_all_stmt($stmt) {
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    if ($res) while ($row = mysqli_fetch_assoc($res)) $rows[] = $row;
    return $rows;
}

// Read payload
$input = json_decode(file_get_contents("php://input"), true);
if (!$input) {
    $rawInput = file_get_contents("php://input");
    file_put_contents('save_timetable_debug.log', "[" . date('Y-m-d H:i:s') . "] Invalid input. Raw input: " . $rawInput . "\n", FILE_APPEND);
    sendResponse('error', 'No valid JSON data received. Please check your input.');
}

// Log the received input (without sensitive data)
$loggableInput = $input;
if (isset($loggableInput['selectedGroupIds'])) {
    $loggableInput['selectedGroupIds'] = count($loggableInput['selectedGroupIds']) . ' groups';
}
if (isset($loggableInput['otherLecturerIds'])) {
    $loggableInput['otherLecturerIds'] = count($loggableInput['otherLecturerIds']) . ' lecturers';
}
file_put_contents('save_timetable_debug.log', "[" . date('Y-m-d H:i:s') . "] Input data: " . json_encode($loggableInput) . "\n", FILE_APPEND);

$module_id = intval($input['selectedModuleId'] ?? 0);
$leader_id = intval($input['moduleLeaderId'] ?? 0);
$facility_id = intval($input['selectedFacilityId'] ?? 0);
$semester = intval($input['semester'] ?? 0);
$academic_year_id = intval($input['academic_year_id'] ?? 0);
$groups = $input['selectedGroupIds'] ?? [];
$sessions = $input['schedule'] ?? [];
$other_lecturers = $input['otherLecturerIds'] ?? [];

// Basic validation
$missingFields = [];
if (!$module_id) $missingFields[] = 'module';
// if (!$leader_id) $missingFields[] = 'module leader';
if (!$facility_id) $missingFields[] = 'facility';
if (!$semester) $missingFields[] = 'semester';
if (!$academic_year_id) $missingFields[] = 'academic year';

if (!empty($missingFields)) {
    sendResponse('error', 'Missing required fields: ' . implode(', ', $missingFields), [
        'missing_fields' => $missingFields,
        'received_data' => [
            'module_id' => $module_id,
            'leader_id' => $leader_id,
            'facility_id' => $facility_id,
            'semester' => $semester,
            'academic_year_id' => $academic_year_id
        ]
    ]);
}

if (empty($groups)) {
    sendResponse('error', 'Please select at least one group.');
}

if (empty($sessions)) {
    sendResponse('error', 'Please add at least one session time.');
}

// Normalize sessions times
$norm_sessions = [];
foreach ($sessions as $s) {
    if (empty($s['day']) || empty($s['start']) || empty($s['end'])) continue;
    $day = $s['day'];
    $start = time_to_sql($s['start']);
    $end = time_to_sql($s['end']);
    // Ensure start < end
    if ($start >= $end) {
        echo json_encode(["status" => "error", "message" => "Invalid time range for {$day}: {$s['start']} - {$s['end']}"]);
        exit;
    }
    $norm_sessions[] = ['day' => $day, 'start' => $start, 'end' => $end];
}
if (empty($norm_sessions)) {
    echo json_encode(["status" => "error", "message" => "No valid sessions provided."]);
    exit;
}

// Prepare conflict collectors
$conflicts = [
    'facility'  => [],         // array of rows {timetable_id, day, start_time, end_time}
    'groups'    => [],         // map group_id => array of rows
    'lecturers' => []          // map lect_id => array of rows
];

// 1) Facility conflicts (only against Approved bookings)
$facility_q = mysqli_prepare($connection, "
    SELECT t.id AS timetable_id, s.day, s.start_time, s.end_time
    FROM timetable t
    JOIN timetable_sessions s ON s.timetable_id = t.id
    WHERE t.facility_id = ?
      AND t.academic_year_id = ?
      AND t.semester = ?
      AND LOWER(t.status) IN ('approved', 'pending')
      AND s.day = ?
      AND s.start_time < ?
      AND s.end_time > ?
");
foreach ($norm_sessions as $s) {
    mysqli_stmt_bind_param($facility_q, "iiisss",
        $facility_id, $academic_year_id, $semester, $s['day'], $s['end'], $s['start']
    );
    mysqli_stmt_execute($facility_q);
    $rows = fetch_all_stmt($facility_q);
    if (!empty($rows)) {
        foreach ($rows as $r) $conflicts['facility'][] = $r;
    }
}
mysqli_stmt_close($facility_q);

// 2) Group conflicts
$group_q = mysqli_prepare($connection, "
    SELECT t.id AS timetable_id, tg.group_id, s.day, s.start_time, s.end_time
    FROM timetable t
    JOIN timetable_groups tg ON tg.timetable_id = t.id
    JOIN timetable_sessions s ON s.timetable_id = t.id
    WHERE tg.group_id = ?
      AND t.academic_year_id = ?
      AND t.semester = ?
      AND s.day = ?
      AND s.start_time < ?
      AND s.end_time > ?
");
foreach ($groups as $gid) {
    $gid = intval($gid);
    foreach ($norm_sessions as $s) {
        mysqli_stmt_bind_param($group_q, "iiisss",
            $gid, $academic_year_id, $semester, $s['day'], $s['end'], $s['start']
        );
        mysqli_stmt_execute($group_q);
        $rows = fetch_all_stmt($group_q);
        if (!empty($rows)) {
            if (!isset($conflicts['groups'][$gid])) $conflicts['groups'][$gid] = [];
            foreach ($rows as $r) $conflicts['groups'][$gid][] = $r;
        }
    }
}
mysqli_stmt_close($group_q);

// 3) Lecturer conflicts (leader + others)
$lect_q = mysqli_prepare($connection, "
    SELECT DISTINCT t.id AS timetable_id, s.day, s.start_time, s.end_time
    FROM timetable t
    JOIN timetable_sessions s ON s.timetable_id = t.id
    LEFT JOIN timetable_lecturers tl ON tl.timetable_id = t.id
    WHERE (t.leader_lecturer_id = ? OR tl.lect_id = ?)
      AND t.academic_year_id = ?
      AND t.semester = ?
      AND s.day = ?
      AND s.start_time < ?
      AND s.end_time > ?
");
$all_lect_ids = array_unique(array_merge([$leader_id], array_map('intval', $other_lecturers)));
foreach ($all_lect_ids as $lid) {
    foreach ($norm_sessions as $s) {
        mysqli_stmt_bind_param($lect_q, "iiiisss",
            $lid, $lid, $academic_year_id, $semester, $s['day'], $s['end'], $s['start']
        );
        mysqli_stmt_execute($lect_q);
        $rows = fetch_all_stmt($lect_q);
        if (!empty($rows)) {
            if (!isset($conflicts['lecturers'][$lid])) $conflicts['lecturers'][$lid] = [];
            foreach ($rows as $r) $conflicts['lecturers'][$lid][] = $r;
        }
    }
}
mysqli_stmt_close($lect_q);

// Check if we should ignore conflicts
$ignore_conflicts = !empty($input['ignoreConflicts']);
// Only check for facility and group conflicts, ignore lecturer conflicts
$has_conflict = !empty($conflicts['facility']) || 
                !empty($conflicts['groups']);
                // !empty($conflicts['lecturers'])

// If there are conflicts and we're not ignoring them, return the conflicts
if ($has_conflict && !$ignore_conflicts) {
    echo json_encode([
        "status" => "conflict",
        "message" => "Conflicts detected. Please review.",
        "conflicts" => $conflicts
    ]);
    exit;
}


// If we're ignoring conflicts, log them
if ($has_conflict && $ignore_conflicts) {
    error_log("Saving timetable with conflicts (ignored): " . json_encode($conflicts));
}

// No conflicts -> save everything
mysqli_begin_transaction($connection);

try {
    // Admin / registrar teaching plans are approved immediately (no pending)
    $user_role = $_SESSION['role'] ?? '';
    $autoApprove = in_array($user_role, ['admin', 'registrar_office'], true);
    $status = $autoApprove ? 'Approved' : 'pending';

    if ($autoApprove) {
        $stmt = mysqli_prepare($connection, "
            INSERT INTO timetable (module_id, leader_lecturer_id, facility_id, semester, academic_year_id, status, approvedby, createdby)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        mysqli_stmt_bind_param($stmt, "iiiiisii",
            $module_id, $leader_id, $facility_id, $semester, $academic_year_id, $status, $user_id, $user_id
        );
    } else {
        $stmt = mysqli_prepare($connection, "
            INSERT INTO timetable (module_id, leader_lecturer_id, facility_id, semester, academic_year_id, status, createdby)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        mysqli_stmt_bind_param($stmt, "iiiiisi",
            $module_id, $leader_id, $facility_id, $semester, $academic_year_id, $status, $user_id
        );
    }
    mysqli_stmt_execute($stmt);
    $timetable_id = mysqli_insert_id($connection);
    mysqli_stmt_close($stmt);

    if (!$timetable_id) {
        throw new Exception("Failed to create timetable.");
    }

    // Insert groups
    if (!empty($groups)) {
        $stmt = mysqli_prepare($connection, "INSERT INTO timetable_groups (timetable_id, group_id) VALUES (?, ?)");
        foreach ($groups as $gid) {
            $gid = intval($gid);
            mysqli_stmt_bind_param($stmt, "ii", $timetable_id, $gid);
            mysqli_stmt_execute($stmt);
        }
        mysqli_stmt_close($stmt);
    }

    // Insert sessions
    if (!empty($norm_sessions)) {
        $stmt = mysqli_prepare($connection, "INSERT INTO timetable_sessions (timetable_id, day, start_time, end_time) VALUES (?, ?, ?, ?)");
        foreach ($norm_sessions as $s) {
            mysqli_stmt_bind_param($stmt, "isss", $timetable_id, $s['day'], $s['start'], $s['end']);
            mysqli_stmt_execute($stmt);
        }
        mysqli_stmt_close($stmt);
    }

    // Insert other lecturers
    if (!empty($other_lecturers)) {
        $stmt = mysqli_prepare($connection, "INSERT INTO timetable_lecturers (timetable_id, lect_id) VALUES (?, ?)");
        foreach ($other_lecturers as $lid) {
            $lid = intval($lid);
            mysqli_stmt_bind_param($stmt, "ii", $timetable_id, $lid);
            mysqli_stmt_execute($stmt);
        }
        mysqli_stmt_close($stmt);
    }

    mysqli_commit($connection);

    echo json_encode([
        "status" => "success",
        "message" => $autoApprove
            ? "Timetable scheduled and approved successfully!"
            : "Timetable scheduled successfully!",
        "timetable_id" => $timetable_id,
        "approval_status" => $status
    ]);
} catch (Exception $ex) {
    mysqli_rollback($connection);
    error_log('Timetable save error: ' . $ex->getMessage() . '\n' . $ex->getTraceAsString());
    file_put_contents('save_timetable_error.log', "[" . date('Y-m-d H:i:s') . "] Error: " . $ex->getMessage() . "\n" . $ex->getTraceAsString() . "\n\n", FILE_APPEND);
    
    $errorMessage = 'An error occurred while saving the timetable. Please try again.';
    if (strpos($ex->getMessage(), 'Duplicate entry') !== false) {
        $errorMessage = 'This schedule conflicts with an existing entry. Please check for overlapping times.';
    }
    
    sendResponse('error', $errorMessage, [
        'error' => $ex->getMessage(),
        'error_type' => get_class($ex)
    ]);
}
