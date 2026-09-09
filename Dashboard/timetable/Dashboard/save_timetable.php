<?php
session_start();
header('Content-Type: application/json');
include('connection.php');

// Ensure logged in
if (!isset($_SESSION['id'])) {
    echo json_encode(["status" => "error", "message" => "Not logged in."]);
    exit;
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
    echo json_encode(["status" => "error", "message" => "No data received."]);
    exit;
}

$module_id = intval($input['selectedModuleId'] ?? 0);
$leader_id = intval($input['moduleLeaderId'] ?? 0);
$facility_id = intval($input['selectedFacilityId'] ?? 0);
$semester = intval($input['semester'] ?? 0);
$academic_year_id = intval($input['academic_year_id'] ?? 0);
$groups = $input['selectedGroupIds'] ?? [];
$sessions = $input['schedule'] ?? [];
$other_lecturers = $input['otherLecturerIds'] ?? [];

// Basic validation
if (!$module_id || !$leader_id || !$facility_id || !$semester || !$academic_year_id) {
    echo json_encode(["status" => "error", "message" => "Missing required fields."]);
    exit;
}
if (empty($groups)) {
    echo json_encode(["status" => "error", "message" => "Please select at least one group."]);
    exit;
}
if (empty($sessions)) {
    echo json_encode(["status" => "error", "message" => "Please add at least one session time."]);
    exit;
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

// 1) Facility conflicts
$facility_q = mysqli_prepare($connection, "
    SELECT t.id AS timetable_id, s.day, s.start_time, s.end_time
    FROM timetable t
    JOIN timetable_sessions s ON s.timetable_id = t.id
    WHERE t.facility_id = ?
      AND t.academic_year_id = ?
      AND t.semester = ?
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

// If any conflicts -> return without saving
$has_conflict =
    !empty($conflicts['facility']) ||
    !empty($conflicts['groups']) ||
    !empty($conflicts['lecturers']);

if ($has_conflict) {
    echo json_encode([
        "status" => "conflict",
        "message" => "Conflicts detected. Please review.",
        "conflicts" => $conflicts
    ]);
    exit;
}

// No conflicts -> save everything
mysqli_begin_transaction($connection);

try {
    // Insert timetable
    $status = 'pending';
    $stmt = mysqli_prepare($connection, "
        INSERT INTO timetable (module_id, leader_lecturer_id, facility_id, semester, academic_year_id, status, createdby)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    mysqli_stmt_bind_param($stmt, "iiiiisi",
        $module_id, $leader_id, $facility_id, $semester, $academic_year_id, $status, $user_id
    );
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
        "message" => "Timetable scheduled successfully!",
        "timetable_id" => $timetable_id
    ]);
} catch (Exception $ex) {
    mysqli_rollback($connection);
    echo json_encode(["status" => "error", "message" => $ex->getMessage()]);
}
