<?php
session_start();
header('Content-Type: application/json');
require_once 'connection.php';

if (!isset($_SESSION['id'])) {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

$current_user_id = $_SESSION['id'];

// === GET USER ROLE & CAMPUS ===
$stmt = $connection->prepare("SELECT role, campus FROM users WHERE id = ?");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    echo json_encode(["success" => false, "error" => "User not found"]);
    exit;
}

$is_admin = in_array(strtolower($user['role']), ['admin', 'administrator', 'superadmin', 'timetable_admin']);
$user_campus = $is_admin ? null : (int)$user['campus']; // Admin = no campus filter

// === FILTERS ===
$search        = $_GET['search'] ?? '';
$site_filter   = $_GET['site'] ?? '';
$type_filter   = $_GET['type'] ?? '';
$only_free_now = !empty($_GET['only_free_now']);

$sql = "
    SELECT 
        f.id                    AS facility_id,
        f.name                  AS facility_name,
        f.type                  AS facility_type,
        f.capacity,
        s.name                  AS site_name,
        c.name                  AS campus_name,

        t.id                    AS timetable_id,
        t.status                AS timetable_status,
        ay.year_label           AS academic_year,

        m.code                  AS module_code,
        m.name                  AS module_name,

        ts.day,
        ts.start_time,
        ts.end_time,

        sg.name                 AS group_name,
        sg.size                 AS group_size,
        i.year_of_study         AS year_of_study,
        p.name                  AS program_name
    FROM facility f
    LEFT JOIN site s ON s.id = f.site
    LEFT JOIN campus c ON c.id = f.campus_id
    LEFT JOIN timetable t ON t.facility_id = f.id
    LEFT JOIN timetable_sessions ts ON ts.timetable_id = t.id
    LEFT JOIN module m ON m.id = t.module_id
    LEFT JOIN academic_year ay ON ay.id = t.academic_year_id
    LEFT JOIN timetable_groups tg ON tg.timetable_id = t.id
    LEFT JOIN student_group sg ON sg.id = tg.group_id
    LEFT JOIN intake i ON i.id = sg.intake_id
    LEFT JOIN program p ON p.id = i.program_id
    WHERE 1=1
";

$params = [];
$types = "";

// === CAMPUS FILTER (ONLY FOR NON-ADMINS) ===
if (!$is_admin && $user_campus > 0) {
    $sql .= " AND f.campus_id = ?";
    $params[] = $user_campus;
    $types .= "i";
}

// === OTHER FILTERS ===
if ($search !== '') {
    $sql .= " AND (f.name LIKE ? OR s.name LIKE ? OR f.type LIKE ? OR c.name LIKE ?)";
    $like = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= "ssss";
}
if ($site_filter !== '') {
    $sql .= " AND s.name = ?";
    $params[] = $site_filter;
    $types .= "s";
}
if ($type_filter !== '') {
    $sql .= " AND f.type LIKE ?";
    $params[] = "%$type_filter%";
    $types .= "s";
}

$sql .= " ORDER BY f.name ASC";

$stmt = $connection->prepare($sql);
if ($types !== "") {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$facilities = [];
$now = new DateTime('now', new DateTimeZone('Africa/Kigali'));
$current_day = $now->format('l');
$current_time = $now->format('H:i');

while ($row = $result->fetch_assoc()) {
    $fid = $row['facility_id'];

    if (!isset($facilities[$fid])) {
        $facilities[$fid] = [
            "id"        => (int)$fid,
            "name"      => $row['facility_name'],
            "type"      => $row['facility_type'] ?? 'CLASSROOM',
            "capacity"  => $row['capacity'] ? (int)$row['capacity'] : null,
            "site"      => $row['site_name'] ?? 'Unknown',
            "campus"    => $row['campus_name'] ?? 'Unknown Campus',
            "bookings"  => []
        ];
    }

    if ($row['timetable_id']) {
        $tid = $row['timetable_id'];
        if (!isset($facilities[$fid]['bookings'][$tid])) {
            $facilities[$fid]['bookings'][$tid] = [
                "timetable_id"   => (int)$tid,
                "module_code"    => $row['module_code'] ?? 'N/A',
                "module_name"    => $row['module_name'] ?? 'Unknown Module',
                "academic_year"  => $row['academic_year'] ?? '',
                "status"         => $row['timetable_status'] ?? 'pending',
                "sessions"       => [],
                "groups"         => []
            ];
        }

        // Add session
        if ($row['day']) {
            $session = [
                "day"        => $row['day'],
                "start_time" => substr($row['start_time'] ?? '', 0, 5),
                "end_time"   => substr($row['end_time'] ?? '', 0, 5)
            ];
            if (!in_array($session, $facilities[$fid]['bookings'][$tid]['sessions'])) {
                $facilities[$fid]['bookings'][$tid]['sessions'][] = $session;
            }
        }

        // Add group with Year of Study
        if ($row['group_name']) {
            $yearText = $row['year_of_study'] ? "Year " . $row['year_of_study'] : "Unknown Year";

            $group = [
                "group_name"    => $row['group_name'],
                "group_size"    => $row['group_size'] ? (int)$row['group_size'] : null,
                "year_of_study" => $yearText,
                "program"       => $row['program_name'] ?? 'N/A'
            ];

            $exists = false;
            foreach ($facilities[$fid]['bookings'][$tid]['groups'] as $g) {
                if ($g['group_name'] === $group['group_name'] && $g['year_of_study'] === $group['year_of_study']) {
                    $exists = true; break;
                }
            }
            if (!$exists) {
                $facilities[$fid]['bookings'][$tid]['groups'][] = $group;
            }
        }
    }
}

// Finalize + real-time status
foreach ($facilities as &$f) {
    $f['bookings'] = array_values($f['bookings']);
    $f['is_free'] = count($f['bookings']) === 0;

    $currently_free = true;
    foreach ($f['bookings'] as $b) {
        foreach ($b['sessions'] as $s) {
            if ($s['day'] === $current_day && $s['start_time'] <= $current_time && $s['end_time'] > $current_time) {
                $currently_free = false;
                break 2;
            }
        }
    }
    $f['is_currently_free'] = $currently_free;
}
unset($f);

// Apply "Free Now" filter if needed
if ($only_free_now) {
    $facilities = array_filter($facilities, fn($f) => $f['is_currently_free']);
}

// Convert to array and sort by booking status (booked first, then free)
$facilities = array_values($facilities);
usort($facilities, function($a, $b) {
    // If one is booked and the other isn't, sort booked first
    if (count($a['bookings']) > 0 && count($b['bookings']) === 0) return -1;
    if (count($a['bookings']) === 0 && count($b['bookings']) > 0) return 1;
    
    // If both are booked or both are free, maintain current order (by name)
    return strcmp($a['name'], $b['name']);
});

echo json_encode([
    "success" => true,
    "data"    => $facilities,
    "total"   => count($facilities),
    "user"    => [
        "id" => $current_user_id,
        "role" => $user['role'],
        "campus" => $user['campus'],
        "is_admin" => $is_admin
    ],
    "now"     => "$current_time on $current_day (Kigali Time)"
], JSON_PRETTY_PRINT);