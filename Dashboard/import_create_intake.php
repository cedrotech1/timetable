<?php
/**
 * Create intake (promotion) + student groups during Excel import.
 * POST JSON single:
 *   { program_id, year_of_study, campus_id, group_numbers: [1,2], size_each, size_mode }
 * Or bulk (each group created at most once):
 *   { campus_id, items: [ { program_id, year_of_study, group_numbers, size_each, size_mode }, ... ] }
 */
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

include('connection.php');

function respond($ok, $message = '', $data = []) {
    echo json_encode(array_merge(['success' => $ok, 'message' => $message], $data));
    exit;
}

function create_intake_groups($connection, $program_id, $year_of_study, $campus_id, $group_numbers, $size_each, $size_mode, $intake_year, $intake_month) {
    $program_id = intval($program_id);
    $year_of_study = intval($year_of_study);
    $campus_id = intval($campus_id);
    $group_numbers = array_values(array_unique(array_filter(array_map('intval', $group_numbers), function ($n) {
        return $n > 0;
    })));
    $size_each = intval($size_each);
    $size_mode = ($size_mode === 'total') ? 'total' : 'each';

    if (!$program_id) throw new Exception('Select a program.');
    if ($year_of_study < 1) throw new Exception('Year of study is required.');
    if (!$campus_id) throw new Exception('Select a campus.');
    if (empty($group_numbers)) throw new Exception('Provide at least one group number.');
    if ($size_each < 1) throw new Exception('Group size must be at least 1.');

    $prog = mysqli_query($connection, "SELECT id, name FROM program WHERE id = " . intval($program_id) . " LIMIT 1");
    if (!$prog || !mysqli_num_rows($prog)) throw new Exception('Program not found.');
    $program = mysqli_fetch_assoc($prog);

    $camp = mysqli_query($connection, "SELECT id, name FROM campus WHERE id = " . intval($campus_id) . " LIMIT 1");
    if (!$camp || !mysqli_num_rows($camp)) throw new Exception('Campus not found.');
    $campus = mysqli_fetch_assoc($camp);

    $count = count($group_numbers);
    if ($size_mode === 'total') {
        $base = intdiv($size_each, $count);
        $rem = $size_each % $count;
        $sizes = [];
        foreach ($group_numbers as $i => $n) {
            $sizes[$n] = $base + ($i < $rem ? 1 : 0);
        }
        $total_students = $size_each;
    } else {
        $sizes = [];
        foreach ($group_numbers as $n) $sizes[$n] = $size_each;
        $total_students = $size_each * $count;
    }

    $intake_id = 0;
    $check = mysqli_prepare($connection, "SELECT id FROM intake WHERE program_id = ? AND year_of_study = ? AND campus_id = ? LIMIT 1");
    mysqli_stmt_bind_param($check, 'iii', $program_id, $year_of_study, $campus_id);
    mysqli_stmt_execute($check);
    $cres = mysqli_stmt_get_result($check);
    if ($cres && ($row = mysqli_fetch_assoc($cres))) {
        $intake_id = (int)$row['id'];
        mysqli_query($connection, "UPDATE intake SET size = GREATEST(IFNULL(size,0), " . intval($total_students) . ") WHERE id = " . $intake_id);
    } else {
        $stmt = mysqli_prepare($connection, "INSERT INTO intake (year, month, year_of_study, size, program_id, campus_id) VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'iiiiii', $intake_year, $intake_month, $year_of_study, $total_students, $program_id, $campus_id);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Failed to create intake: ' . mysqli_error($connection));
        }
        $intake_id = (int)mysqli_insert_id($connection);
        mysqli_stmt_close($stmt);
    }
    mysqli_stmt_close($check);

    if (!$intake_id) throw new Exception('Could not resolve intake.');

    $created = [];
    $existing = [];
    foreach ($group_numbers as $num) {
        $name = 'Group ' . $num;
        $gsize = (int)$sizes[$num];

        $gq = mysqli_prepare($connection, "SELECT id, name, size FROM student_group WHERE intake_id = ? AND name = ? LIMIT 1");
        mysqli_stmt_bind_param($gq, 'is', $intake_id, $name);
        mysqli_stmt_execute($gq);
        $gres = mysqli_stmt_get_result($gq);
        if ($gres && ($grow = mysqli_fetch_assoc($gres))) {
            mysqli_query($connection, "UPDATE student_group SET size = " . $gsize . " WHERE id = " . intval($grow['id']));
            $existing[] = [
                'id' => (int)$grow['id'],
                'name' => $name,
                'size' => $gsize,
                'program_id' => $program_id,
                'program_name' => $program['name'],
                'year_of_study' => $year_of_study,
                'campus_name' => $campus['name']
            ];
        } else {
            $ins = mysqli_prepare($connection, "INSERT INTO student_group (name, size, intake_id) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($ins, 'sii', $name, $gsize, $intake_id);
            if (!mysqli_stmt_execute($ins)) {
                throw new Exception('Failed to create group: ' . mysqli_error($connection));
            }
            $gid = (int)mysqli_insert_id($connection);
            mysqli_stmt_close($ins);
            $created[] = [
                'id' => $gid,
                'name' => $name,
                'size' => $gsize,
                'program_id' => $program_id,
                'program_name' => $program['name'],
                'year_of_study' => $year_of_study,
                'campus_name' => $campus['name']
            ];
        }
        mysqli_stmt_close($gq);
    }

    return [
        'intake_id' => $intake_id,
        'program' => ['id' => $program_id, 'name' => $program['name']],
        'campus' => ['id' => $campus_id, 'name' => $campus['name']],
        'year_of_study' => $year_of_study,
        'groups' => array_merge($existing, $created),
        'created_count' => count($created),
        'existing_count' => count($existing)
    ];
}

if (!isset($_SESSION['id'])) {
    respond(false, 'Not logged in.');
}

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['admin', 'registrar_office', 'dean_office'], true)) {
    respond(false, 'Not allowed.');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    respond(false, 'Invalid JSON.');
}

$intake_year = intval($input['intake_year'] ?? date('Y'));
$intake_month = intval($input['intake_month'] ?? date('n'));

// Bulk: create all missing groups once (deduped by program + year + campus)
if (!empty($input['items']) && is_array($input['items'])) {
    $defaultCampus = intval($input['campus_id'] ?? 0);
    // Merge duplicate keys so each group number is requested once
    $merged = [];
    foreach ($input['items'] as $item) {
        $pid = intval($item['program_id'] ?? 0);
        $yos = intval($item['year_of_study'] ?? 0);
        $cid = intval($item['campus_id'] ?? $defaultCampus);
        if (!$pid || !$yos || !$cid) continue;
        $key = $pid . ':' . $yos . ':' . $cid;
        if (!isset($merged[$key])) {
            $merged[$key] = [
                'program_id' => $pid,
                'year_of_study' => $yos,
                'campus_id' => $cid,
                'group_numbers' => [],
                'size_each' => intval($item['size_each'] ?? 0),
                'size_mode' => ($item['size_mode'] ?? 'each') === 'total' ? 'total' : 'each'
            ];
        }
        $nums = $item['group_numbers'] ?? [];
        if (!is_array($nums)) $nums = [];
        foreach ($nums as $n) {
            $n = intval($n);
            if ($n > 0) $merged[$key]['group_numbers'][] = $n;
        }
        $sz = intval($item['size_each'] ?? 0);
        if ($sz > $merged[$key]['size_each']) $merged[$key]['size_each'] = $sz;
    }

    mysqli_begin_transaction($connection);
    try {
        $results = [];
        $totalCreated = 0;
        $totalExisting = 0;
        foreach ($merged as $item) {
            $item['group_numbers'] = array_values(array_unique($item['group_numbers']));
            if (empty($item['group_numbers'])) continue;
            if ($item['size_each'] < 1) $item['size_each'] = 40;
            $r = create_intake_groups(
                $connection,
                $item['program_id'],
                $item['year_of_study'],
                $item['campus_id'],
                $item['group_numbers'],
                $item['size_each'],
                $item['size_mode'],
                $intake_year,
                $intake_month
            );
            $totalCreated += $r['created_count'];
            $totalExisting += $r['existing_count'];
            $results[] = $r;
        }
        mysqli_commit($connection);
        respond(true, "Groups ready: {$totalCreated} created, {$totalExisting} already existed (no duplicates).", [
            'created_count' => $totalCreated,
            'existing_count' => $totalExisting,
            'results' => $results
        ]);
    } catch (Exception $e) {
        mysqli_rollback($connection);
        respond(false, $e->getMessage());
    }
}

// Single section create
$program_id = intval($input['program_id'] ?? 0);
$year_of_study = intval($input['year_of_study'] ?? 0);
$campus_id = intval($input['campus_id'] ?? 0);
$group_numbers = $input['group_numbers'] ?? [];
if (!is_array($group_numbers)) $group_numbers = [];
$size_each = intval($input['size_each'] ?? 0);
$size_mode = ($input['size_mode'] ?? 'each') === 'total' ? 'total' : 'each';

mysqli_begin_transaction($connection);
try {
    $r = create_intake_groups(
        $connection,
        $program_id,
        $year_of_study,
        $campus_id,
        $group_numbers,
        $size_each,
        $size_mode,
        $intake_year,
        $intake_month
    );
    mysqli_commit($connection);
    respond(true, 'Intake/groups ready (' . $r['created_count'] . ' created, ' . $r['existing_count'] . ' existing).', $r);
} catch (Exception $e) {
    mysqli_rollback($connection);
    respond(false, $e->getMessage());
}
