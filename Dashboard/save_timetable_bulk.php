<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

include('connection.php');

function sendBulkResponse($status, $message = '', $data = []) {
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $data));
    exit;
}

function time_to_sql_bulk($t) {
    if (preg_match('/^\d{2}:\d{2}$/', $t)) return $t . ':00';
    return $t;
}

function fetch_all_stmt_bulk($stmt) {
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $rows[] = $row;
        }
    }
    return $rows;
}

/**
 * Save one teaching-plan row. Returns ['status'=>..., 'message'=>..., ...]
 */
function saveOneTimetableRow($connection, $user_id, $user_role, $academic_year_id, $semester, $groups, $row, $ignore_conflicts = false) {
    $module_id = intval($row['module_id'] ?? 0);
    $leader_id = intval($row['leader_id'] ?? 0);
    $facility_id = intval($row['facility_id'] ?? 0);
    $day = trim($row['day'] ?? '');
    $start = trim($row['start'] ?? '');
    $end = trim($row['end'] ?? '');
    $other_lecturers = $row['other_lecturer_ids'] ?? [];

    $missing = [];
    if (!$module_id) $missing[] = 'module';
    if (!$facility_id) $missing[] = 'facility';
    if ($day === '') $missing[] = 'day';
    if ($start === '') $missing[] = 'start';
    if ($end === '') $missing[] = 'end';
    if (!$semester) $missing[] = 'semester';
    if (!$academic_year_id) $missing[] = 'academic year';
    if (empty($groups)) $missing[] = 'groups';

    if (!empty($missing)) {
        return ['status' => 'error', 'message' => 'Missing: ' . implode(', ', $missing)];
    }

    $startSql = time_to_sql_bulk($start);
    $endSql = time_to_sql_bulk($end);
    if ($startSql >= $endSql) {
        return ['status' => 'error', 'message' => "Invalid time range: {$start} - {$end}"];
    }

    $norm_sessions = [['day' => $day, 'start' => $startSql, 'end' => $endSql]];

    $conflicts = ['facility' => [], 'groups' => [], 'lecturers' => []];

    // Facility conflicts (Approved only)
    $facility_q = mysqli_prepare($connection, "
        SELECT t.id AS timetable_id, s.day, s.start_time, s.end_time
        FROM timetable t
        JOIN timetable_sessions s ON s.timetable_id = t.id
        WHERE t.facility_id = ?
          AND t.academic_year_id = ?
          AND t.semester = ?
          AND LOWER(t.status) = 'approved'
          AND s.day = ?
          AND s.start_time < ?
          AND s.end_time > ?
    ");
    foreach ($norm_sessions as $s) {
        mysqli_stmt_bind_param($facility_q, "iiisss",
            $facility_id, $academic_year_id, $semester, $s['day'], $s['end'], $s['start']
        );
        mysqli_stmt_execute($facility_q);
        $rows = fetch_all_stmt_bulk($facility_q);
        foreach ($rows as $r) {
            $conflicts['facility'][] = $r;
        }
    }
    mysqli_stmt_close($facility_q);

    // Group conflicts
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
            $rows = fetch_all_stmt_bulk($group_q);
            if (!empty($rows)) {
                if (!isset($conflicts['groups'][$gid])) {
                    $conflicts['groups'][$gid] = [];
                }
                foreach ($rows as $r) {
                    $conflicts['groups'][$gid][] = $r;
                }
            }
        }
    }
    mysqli_stmt_close($group_q);

    $has_conflict = !empty($conflicts['facility']) || !empty($conflicts['groups']);
    if ($has_conflict && !$ignore_conflicts) {
        return [
            'status' => 'conflict',
            'message' => 'Conflicts detected.',
            'conflicts' => $conflicts
        ];
    }

    $autoApprove = in_array($user_role, ['admin', 'registrar_office'], true);
    $status = $autoApprove ? 'Approved' : 'pending';

    mysqli_begin_transaction($connection);
    try {
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
            throw new Exception('Failed to create timetable.');
        }

        $stmt = mysqli_prepare($connection, "INSERT INTO timetable_groups (timetable_id, group_id) VALUES (?, ?)");
        foreach ($groups as $gid) {
            $gid = intval($gid);
            mysqli_stmt_bind_param($stmt, "ii", $timetable_id, $gid);
            mysqli_stmt_execute($stmt);
        }
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($connection, "INSERT INTO timetable_sessions (timetable_id, day, start_time, end_time) VALUES (?, ?, ?, ?)");
        foreach ($norm_sessions as $s) {
            mysqli_stmt_bind_param($stmt, "isss", $timetable_id, $s['day'], $s['start'], $s['end']);
            mysqli_stmt_execute($stmt);
        }
        mysqli_stmt_close($stmt);

        if (!empty($other_lecturers)) {
            $stmt = mysqli_prepare($connection, "INSERT INTO timetable_lecturers (timetable_id, lect_id) VALUES (?, ?)");
            foreach ($other_lecturers as $lid) {
                $lid = intval($lid);
                if ($lid <= 0) continue;
                mysqli_stmt_bind_param($stmt, "ii", $timetable_id, $lid);
                mysqli_stmt_execute($stmt);
            }
            mysqli_stmt_close($stmt);
        }

        mysqli_commit($connection);

        return [
            'status' => 'success',
            'message' => $autoApprove ? 'Approved' : 'Pending',
            'timetable_id' => $timetable_id,
            'approval_status' => $status
        ];
    } catch (Exception $ex) {
        mysqli_rollback($connection);
        return ['status' => 'error', 'message' => $ex->getMessage()];
    }
}

if (!isset($_SESSION['id'])) {
    sendBulkResponse('error', 'Not logged in.');
}

$user_id = intval($_SESSION['id']);
$user_role = $_SESSION['role'] ?? '';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    sendBulkResponse('error', 'No valid JSON data received.');
}

$academic_year_id = intval($input['academic_year_id'] ?? 0);
$semester = intval($input['semester'] ?? 0);
$groups = $input['groupIds'] ?? $input['selectedGroupIds'] ?? [];
$rows = $input['rows'] ?? [];
$ignore_conflicts = !empty($input['ignoreConflicts']);

if (!$academic_year_id || !$semester) {
    sendBulkResponse('error', 'Academic year and semester are required.');
}
if (empty($groups)) {
    sendBulkResponse('error', 'Please select at least one group.');
}
if (empty($rows) || !is_array($rows)) {
    sendBulkResponse('error', 'Please add at least one plan row.');
}

$groups = array_values(array_unique(array_map('intval', $groups)));
$results = [];
$successCount = 0;
$failCount = 0;

foreach ($rows as $index => $row) {
    $result = saveOneTimetableRow(
        $connection,
        $user_id,
        $user_role,
        $academic_year_id,
        $semester,
        $groups,
        $row,
        $ignore_conflicts
    );
    $result['row_index'] = $index;
    $results[] = $result;
    if (($result['status'] ?? '') === 'success') {
        $successCount++;
    } else {
        $failCount++;
    }
}

$overall = $failCount === 0 ? 'success' : ($successCount > 0 ? 'partial' : 'error');
sendBulkResponse($overall, "Saved {$successCount} of " . count($rows) . " plan(s).", [
    'results' => $results,
    'success_count' => $successCount,
    'fail_count' => $failCount
]);
