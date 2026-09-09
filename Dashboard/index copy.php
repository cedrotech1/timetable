<?php
session_start();
include('connection.php');

// Get current user info
$user_id = $_SESSION['id'] ?? 0;
$stmt = $connection->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc() ?: [];
$role = $user['role'] ?? '';

// ==================== 1. CAMPUS STATISTICS ====================
// Get all campuses first
$campus_stats = [];
$result = mysqli_query($connection, "SELECT * FROM campus ORDER BY name");
while ($row = mysqli_fetch_assoc($result)) {
    $campus_id = $row['id'];
    $campus_stats[$campus_id] = [
        'id' => $campus_id,
        'campus_name' => $row['name'],
        'college_count' => 0,
        'school_count' => 0,
        'program_count' => 0,
        'intake_count' => 0,
        'group_count' => 0
    ];
}

// 1. Get all intakes with their campus, program, department, and school info
$query = "
    SELECT 
        i.id as intake_id,
        i.campus_id,
        c.name as campus_name,
        p.id as program_id,
        d.id as department_id,
        d.school_id,
        s.college_id,
        (SELECT COUNT(*) FROM student_group sg WHERE sg.intake_id = i.id) as group_count
    FROM intake i
    JOIN campus c ON i.campus_id = c.id
    LEFT JOIN program p ON i.program_id = p.id
    LEFT JOIN department d ON p.department_id = d.id
    LEFT JOIN school s ON d.school_id = s.id
    ORDER BY c.name
";

$result = mysqli_query($connection, $query);
$intake_data = [];

while ($row = mysqli_fetch_assoc($result)) {
    $campus_id = $row['campus_id'];
    
    // Initialize campus data if not exists
    if (!isset($intake_data[$campus_id])) {
        $intake_data[$campus_id] = [
            'colleges' => [],
            'schools' => [],
            'programs' => [],
            'intakes' => [],
            'groups' => 0
        ];
    }
    
    // Add unique colleges, schools, programs, and intakes
    if ($row['college_id'] && !in_array($row['college_id'], $intake_data[$campus_id]['colleges'])) {
        $intake_data[$campus_id]['colleges'][] = $row['college_id'];
    }
    
    if ($row['school_id'] && !in_array($row['school_id'], $intake_data[$campus_id]['schools'])) {
        $intake_data[$campus_id]['schools'][] = $row['school_id'];
    }
    
    if ($row['program_id'] && !in_array($row['program_id'], $intake_data[$campus_id]['programs'])) {
        $intake_data[$campus_id]['programs'][] = $row['program_id'];
    }
    
    if ($row['intake_id'] && !in_array($row['intake_id'], $intake_data[$campus_id]['intakes'])) {
        $intake_data[$campus_id]['intakes'][] = $row['intake_id'];
    }
    
    // Add groups
    $intake_data[$campus_id]['groups'] += (int)$row['group_count'];
}

// Update campus stats with the collected data
foreach ($intake_data as $campus_id => $data) {
    if (isset($campus_stats[$campus_id])) {
        $campus_stats[$campus_id]['college_count'] = count($data['colleges']);
        $campus_stats[$campus_id]['school_count'] = count($data['schools']);
        $campus_stats[$campus_id]['program_count'] = count($data['programs']);
        $campus_stats[$campus_id]['intake_count'] = count($data['intakes']);
        $campus_stats[$campus_id]['group_count'] = $data['groups'];
    }
}

// Get intake distribution
$intake_distribution = [];
$query = "
    SELECT 
        i.campus_id,
        c.name as campus_name,
        CONCAT(i.year, '-', LPAD(i.month, 2, '0')) as intake_period,
        COUNT(DISTINCT i.id) as intake_count,
        COUNT(DISTINCT sg.id) as group_count
    FROM intake i
    JOIN campus c ON i.campus_id = c.id
    LEFT JOIN student_group sg ON sg.intake_id = i.id
    GROUP BY i.campus_id, i.year, i.month, c.name
    ORDER BY i.year DESC, i.month DESC
";
$result = mysqli_query($connection, $query);
while ($row = mysqli_fetch_assoc($result)) {
    $campus_id = $row['campus_id'];
    if (!isset($intake_distribution[$campus_id])) {
        $intake_distribution[$campus_id] = [
            'name' => $row['campus_name'],
            'intakes' => []
        ];
    }
    $intake_distribution[$campus_id]['intakes'][] = $row;
}

// ==================== 2. BUILD HIERARCHY ====================
$colleges = $schools = $programs = $intakes = $studentGroups = [];

// Get current academic year and semester
$system_result = mysqli_query($connection, "SELECT * FROM system LIMIT 1");
if ($system_result && mysqli_num_rows($system_result) > 0) {
    $system_data = mysqli_fetch_assoc($system_result);
    $academic_year_id = $system_data['accademic_year_id'];
    $semester = $system_data['semester'];
    $academic_year = $system_data['academic_year'] ?? date('Y');
} else {
    $academic_year_id = 1;
    $semester = 1;
    $academic_year = date('Y');
}

// Fetch colleges
$res = mysqli_query($connection, "SELECT id, name FROM college ORDER BY name");
while ($row = mysqli_fetch_assoc($res)) {
    $colleges[$row['id']] = array_merge($row, ['schools' => []]);
}

// Fetch schools with their college
$res = mysqli_query($connection, "SELECT s.*, c.name as college_name 
    FROM school s 
    LEFT JOIN college c ON s.college_id = c.id 
    ORDER BY c.name, s.name");
while ($row = mysqli_fetch_assoc($res)) {
    $schools[$row['id']] = array_merge($row, ['programs' => []]);
    if (isset($colleges[$row['college_id']])) {
        $colleges[$row['college_id']]['schools'][] = $row['id'];
    }
}

// Fetch programs with their school
$res = mysqli_query($connection, "SELECT p.id, p.name, p.code, 
    COALESCE(p.school_id, d.school_id) as school_id
    FROM program p 
    LEFT JOIN department d ON p.department_id = d.id");
while ($row = mysqli_fetch_assoc($res)) {
    $programs[$row['id']] = $row;
    if (isset($schools[$row['school_id']])) {
        if (!isset($schools[$row['school_id']]['programs'])) {
            $schools[$row['school_id']]['programs'] = [];
        }
        $schools[$row['school_id']]['programs'][] = $row['id'];
    }
}

// Fetch intakes with campus
$res = mysqli_query($connection, "SELECT i.id, i.year, i.month, i.program_id, 
    c.name as campus, c.id as campus_id
    FROM intake i 
    LEFT JOIN campus c ON i.campus_id = c.id 
    ORDER BY i.year DESC, i.month");
while ($row = mysqli_fetch_assoc($res)) {
    $intakes[$row['id']] = array_merge($row, ['groups' => []]);
    if (isset($programs[$row['program_id']])) {
        if (!isset($programs[$row['program_id']]['intakes'])) {
            $programs[$row['program_id']]['intakes'] = [];
        }
        $programs[$row['program_id']]['intakes'][] = $row['id'];
    }
}

// Fetch student groups
$res = mysqli_query($connection, "SELECT id, name, intake_id FROM student_group ORDER BY name");
while ($row = mysqli_fetch_assoc($res)) {
    $studentGroups[$row['id']] = $row;
    if (isset($intakes[$row['intake_id']])) {
        $intakes[$row['intake_id']]['groups'][] = $row['id'];
    }
}

// ==================== 2. COUNT TIMETABLE RECORDS ====================
$recordCounts = []; // group_id => number of timetable rows
$query = "
    SELECT 
        tg.group_id,
        COUNT(DISTINCT t.id) as record_count
    FROM timetable t
    JOIN timetable_groups tg ON t.id = tg.timetable_id
    WHERE t.academic_year_id = ? AND t.semester = ?
    GROUP BY tg.group_id";

$stmt = mysqli_prepare($connection, $query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "is", $academic_year_id, $semester);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $recordCounts[$row['group_id']] = (int)$row['record_count'];
    }
    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Timetable Records | UR-TIMETABLE</title>
    <meta content="" name="description">
    <meta content="" name="keywords">
    <link href="assets/img/icon1.png" rel="icon">
    <link href="assets/img/icon1.png" rel="apple-touch-icon">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
    /* Highlight search matches */
    .bg-warning {
        background-color: #ffc107 !important;
        padding: 0 2px;
        border-radius: 3px;
    }
    
    /* Filter form styles */
    .filter-card {
        margin-bottom: 1.5rem;
        border-left: 4px solid #0d6efd;
    }
    
    .filter-card .card-body {
        padding: 1rem 1.5rem;
    }
    
    .filter-card .form-control,
    .filter-card .form-select {
        border-radius: 0.25rem;
    }
    
    /* Responsive table */
    @media (max-width: 768px) {
        .table-responsive {
            font-size: 0.9rem;
        }
        
        .table th, 
        .table td {
            padding: 0.5rem;
        }
    }
        .structure-table {
            width: 100%;
            border-collapse: collapse;
        }
        .structure-table th, .structure-table td {
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            vertical-align: middle;
        }
        .structure-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        .level1 { background-color: #f8f9fa; font-weight: 600; }
        .level2 { background-color: #f1f8ff; }
        .level3 { background-color: #e8f5e9; }
        .level4 { background-color: #fff3e0; }
        .level5 { background-color: #f3e5f5; }
        .record-count {
            display: inline-block;
            min-width: 30px;
            text-align: center;
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.85em;
        }
        .has-records { background-color: #28a745; color: white; }
        .no-records { background-color: #dc3545; color: white; }
        .clickable { cursor: pointer; color: #0d6efd; text-decoration: none; }
        .clickable:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <?php include('./includes/header.php'); ?>
    <?php include('./includes/menu.php'); ?>
    
    <main id="main" class="main">
        <div class="pagetitle">
            <h1>Timetable Records</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item active">Timetable Records</li>
                </ol>
            </nav>
        </div>

        <!-- Campus Statistics Section -->
      

        <section class="section">
            <div class="row">
                <div class="col-lg-12">

                <?php include('timetable_analytics.php'); ?>
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="bi bi-calendar3"></i> Academic Year: <?= $academic_year ?>
                                <span class="badge" style="background-color: #031f50;color: white;">Semester <?= $semester ?></span>
                            </h5>
                            <p>Showing timetable records for all programs and student groups.</p>
                            
                            <!-- Search and Filter Form -->
                            <div class="card mb-4">
                                <div class="card-body">
                                    <form method="GET" class="row g-3">
                                        <div class="col-md-4">
                                            <input type="text" name="search" class="form-control" placeholder="Search by program or group name" 
                                                   value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <select name="campus" class="form-select" id="campusFilter">
                                                <option value="">All Campuses</option>
                                                <?php 
                                                $campuses = $connection->query("SELECT * FROM campus ORDER BY name");
                                                while($c = $campuses->fetch_assoc()) {
                                                    $selected = (isset($_GET['campus']) && $_GET['campus'] == $c['id']) ? 'selected' : '';
                                                    echo "<option value='{$c['id']}' $selected>{$c['name']}</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <select name="college" class="form-select" id="collegeFilter">
                                                <option value="">All Colleges</option>
                                                <?php 
                                                $collegesList = $connection->query("SELECT * FROM college ORDER BY name");
                                                while($col = $collegesList->fetch_assoc()) {
                                                    $selected = (isset($_GET['college']) && $_GET['college'] == $col['id']) ? 'selected' : '';
                                                    echo "<option value='{$col['id']}' $selected>{$col['name']}</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="bi bi-search"></i> Filter
                                            </button>
                                        </div>
                                        <?php if(isset($_GET['search']) || isset($_GET['campus']) || isset($_GET['college'])): ?>
                                            <div class="col-12 text-end">
                                                <span class="text-dark">
                                                    <i class="bi bi-calendar3"></i> <?= $intake['month'] ?>/<?= $intake['year'] ?> (<?= $intake['campus'] ?>)
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-primary">
                                        <tr>
                                            <th>Structure</th>
                                            <th width="120" class="text-center">Records</th>
                                            <th width="200">Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $grandTotal = 0;
                                        // Apply filters
                                        $filteredColleges = $colleges;
                                        
                                        // Filter by college if selected
                                        if (!empty($_GET['college'])) {
                                            $selectedCollege = intval($_GET['college']);
                                            if (isset($filteredColleges[$selectedCollege])) {
                                                $filteredColleges = [$selectedCollege => $filteredColleges[$selectedCollege]];
                                            }
                                        }
                                        
                                        // Filter by search term if provided
                                        $searchTerm = isset($_GET['search']) ? strtolower(trim($_GET['search'])) : '';
                                        
                                        // Filter by campus if selected
                                        $selectedCampus = !empty($_GET['campus']) ? intval($_GET['campus']) : null;
                                        
                                        foreach ($filteredColleges as $cid => $college):
                                            $collegeTotal = 0;
                                            $schoolCount = 0;
                                            
                                            // Count only schools that match filters
                                            foreach (($college['schools'] ?? []) as $sid) {
                                                $school = $schools[$sid] ?? null;
                                                if (!$school) continue;
                                                
                                                // Check if any program in this school matches the filters
                                                $hasMatchingProgram = false;
                                                foreach (($schools[$sid]['programs'] ?? []) as $pid) {
                                                    $program = $programs[$pid] ?? null;
                                                    if (!$program) continue;
                                                    
                                                    // Check if any intake matches campus filter
                                                    $hasMatchingIntake = true;
                                                    if (isset($programs[$pid]['intakes'])) {
                                                        $hasMatchingIntake = false;
                                                        foreach (($programs[$pid]['intakes'] ?? []) as $iid) {
                                                            $intake = $intakes[$iid] ?? null;
                                                            if ($intake && $intake['campus_id'] == $selectedCampus) {
                                                                $hasMatchingIntake = true;
                                                                break;
                                                            }
                                                        }
                                                    }
                                                    
                                                    // Check search term
                                                    $matchesSearch = empty($searchTerm) || 
                                                        stripos($program['name'], $searchTerm) !== false ||
                                                        stripos($program['code'] ?? '', $searchTerm) !== false;
                                                    
                                                    if ($hasMatchingIntake && $matchesSearch) {
                                                        $hasMatchingProgram = true;
                                                        break;
                                                    }
                                                }
                                                
                                                if ($hasMatchingProgram) {
                                                    $schoolCount++;
                                                }
                                            }
                                        ?>
                                        <?php if ($schoolCount > 0): // Only show college if it has matching schools ?>
                                        <tr class="level1">
                                            <td>
                                                <span class="text-dark">
                                                    <i class="bi bi-building"></i> <?= htmlspecialchars($college['name']) ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="record-count" id="col-<?= $cid ?>">0</span>
                                            </td>
                                            <td><?= $schoolCount ?> school(s)</td>
                                        </tr>
                                        <?php endif; ?>

                                        <?php 
                                        // Filter schools to only those with matching programs
                                        $filteredSchools = [];
                                        $schoolTotals = [];
                                        $programCounts = [];
                                        
                                        foreach (($college['schools'] ?? []) as $sid) {
                                            $school = $schools[$sid] ?? null;
                                            if (!$school) continue;
                                            
                                            $schoolTotal = 0;
                                            $progCount = 0;
                                            $hasMatchingProgram = false;
                                            
                                            foreach (($schools[$sid]['programs'] ?? []) as $pid) {
                                                $program = $programs[$pid] ?? null;
                                                if (!$program) continue;
                                                
                                                // Check campus filter
                                                $matchesCampus = true;
                                                if ($selectedCampus !== null) {
                                                    $matchesCampus = false;
                                                    foreach (($programs[$pid]['intakes'] ?? []) as $iid) {
                                                        $intake = $intakes[$iid] ?? null;
                                                        if ($intake && $intake['campus_id'] == $selectedCampus) {
                                                            $matchesCampus = true;
                                                            break;
                                                        }
                                                    }
                                                }
                                                
                                                // Check search term
                                                $matchesSearch = empty($searchTerm) || 
                                                    stripos($program['name'], $searchTerm) !== false ||
                                                    stripos($program['code'] ?? '', $searchTerm) !== false;
                                                
                                                if ($matchesCampus && $matchesSearch) {
                                                    $progCount++;
                                                    $hasMatchingProgram = true;
                                                    
                                                    // Calculate records for this program
                                                    $progTotal = 0;
                                                    foreach (($programs[$pid]['intakes'] ?? []) as $iid) {
                                                        $intake = $intakes[$iid] ?? null;
                                                        if (!$intake) continue;
                                                        
                                                        // Skip if campus filter doesn't match
                                                        if ($selectedCampus !== null && $intake['campus_id'] != $selectedCampus) {
                                                            continue;
                                                        }
                                                        
                                                        foreach (($intake['groups'] ?? []) as $gid) {
                                                            $progTotal += $recordCounts[$gid] ?? 0;
                                                        }
                                                    }
                                                    $schoolTotal += $progTotal;
                                                }
                                            }
                                            
                                            if ($hasMatchingProgram) {
                                                $filteredSchools[] = $sid;
                                                $schoolTotals[$sid] = $schoolTotal;
                                                $programCounts[$sid] = $progCount;
                                            }
                                        }
                                        
                                        foreach ($filteredSchools as $sid):
                                            $school = $schools[$sid] ?? null;
                                            if (!$school) continue;
                                            $schoolTotal = $schoolTotals[$sid] ?? 0;
                                            $progCount = $programCounts[$sid] ?? 0;
                                        ?>
                                        <tr class="level2">
                                            <td>└─ 
                                                <span class="text-dark">
                                                    <i class="bi bi-house-door"></i> <?= htmlspecialchars($school['name']) ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="record-count" id="sch-<?= $sid ?>">0</span>
                                            </td>
                                            <td><?= $progCount ?> program(s)</td>
                                        </tr>

                                        <?php 
                                        // Filter programs based on search and filters
                                        $filteredPrograms = [];
                                        $programTotals = [];
                                        $intakeCounts = [];
                                        
                                        foreach (($schools[$sid]['programs'] ?? []) as $pid) {
                                            $program = $programs[$pid] ?? null;
                                            if (!$program) continue;
                                            
                                            // Check search term
                                            $matchesSearch = empty($searchTerm) || 
                                                stripos($program['name'], $searchTerm) !== false ||
                                                stripos($program['code'] ?? '', $searchTerm) !== false;
                                            
                                            if (!$matchesSearch) continue;
                                            
                                            // Check intakes for campus filter
                                            $matchingIntakes = [];
                                            $progTotal = 0;
                                            $intakeCount = 0;
                                            
                                            foreach (($programs[$pid]['intakes'] ?? []) as $iid) {
                                                $intake = $intakes[$iid] ?? null;
                                                if (!$intake) continue;
                                                
                                                // Skip if campus filter doesn't match
                                                if ($selectedCampus !== null && $intake['campus_id'] != $selectedCampus) {
                                                    continue;
                                                }
                                                
                                                $intakeCount++;
                                                $matchingIntakes[] = $iid;
                                                
                                                // Calculate records for this intake
                                                foreach (($intake['groups'] ?? []) as $gid) {
                                                    $progTotal += $recordCounts[$gid] ?? 0;
                                                }
                                            }
                                            
                                            // Always include the program, even if it has no intakes
                                            $filteredPrograms[] = $pid;
                                            $programTotals[$pid] = $progTotal;
                                            $intakeCounts[$pid] = $intakeCount;
                                        }
                                        
                                        foreach ($filteredPrograms as $pid):
                                            $program = $programs[$pid] ?? null;
                                            if (!$program) continue;
                                            $progTotal = $programTotals[$pid] ?? 0;
                                            $intakeCount = $intakeCounts[$pid] ?? 0;
                                        ?>
                                        <tr class="level3">
                                            <td>  └─ 
                                                <span class="text-dark">
                                                    <i class="bi bi-journal-text"></i> <?= htmlspecialchars($program['name']) ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="record-count" id="prog-<?= $pid ?>">0</span>
                                            </td>
                                            <td><?= $intakeCount ?> intake(s)</td>
                                        </tr>

                                        <?php 
                                        // Filter intakes based on campus
                                        $filteredIntakes = [];
                                        $intakeTotals = [];
                                        
                                        foreach (($programs[$pid]['intakes'] ?? []) as $iid) {
                                            $intake = $intakes[$iid] ?? null;
                                            if (!$intake) continue;
                                            
                                            // Skip if campus filter doesn't match
                                            if ($selectedCampus !== null && $intake['campus_id'] != $selectedCampus) {
                                                continue;
                                            }
                                            
                                            // Calculate records for this intake
                                            $intakeTotal = 0;
                                            foreach (($intake['groups'] ?? []) as $gid) {
                                                $intakeTotal += $recordCounts[$gid] ?? 0;
                                            }
                                            
                                            $filteredIntakes[] = $iid;
                                            $intakeTotals[$iid] = $intakeTotal;
                                        }
                                        
                                        // Group intakes by year_of_study and campus
                                        $groupedIntakes = [];
                                        foreach ($filteredIntakes as $iid) {
                                            $intake = $intakes[$iid] ?? null;
                                            if (!$intake) continue;
                                            
                                            // Get year_of_study for this intake
                                            $yearQuery = "SELECT DISTINCT year_of_study FROM intake 
                                                         WHERE campus_id = ? AND year = ? AND month = ?";
                                            $stmt = $connection->prepare($yearQuery);
                                            $stmt->bind_param("iii", $intake['campus_id'], $intake['year'], $intake['month']);
                                            $stmt->execute();
                                            $yearResult = $stmt->get_result();
                                            
                                            while ($yearRow = $yearResult->fetch_assoc()) {
                                                $yearOfStudy = $yearRow['year_of_study'];
                                                $key = $yearOfStudy . '-' . $intake['campus_id'];
                                                
                                                if (!isset($groupedIntakes[$key])) {
                                                    $groupedIntakes[$key] = [
                                                        'year_of_study' => $yearOfStudy,
                                                        'campus' => $intake['campus'],
                                                        'intake_ids' => [],
                                                        'group_count' => 0,
                                                        'record_count' => 0
                                                    ];
                                                }
                                                
                                                $groupedIntakes[$key]['intake_ids'][] = $iid;
                                                $groupedIntakes[$key]['group_count'] += count($intake['groups'] ?? []);
                                                $groupedIntakes[$key]['record_count'] += $intakeTotals[$iid] ?? 0;
                                            }
                                        }
                                        
                                        // Display grouped intakes
                                        foreach ($groupedIntakes as $key => $grouped):
                                            $intakeTotal = $grouped['record_count'];
                                            $yearOfStudy = $grouped['year_of_study'];
                                            $campusName = $grouped['campus'];
                                            $groupCount = $grouped['group_count'];
                                            $intakeIds = implode('-', $grouped['intake_ids']);
                                        ?>
                                        <tr class="level4">
                                            <td>    └─ 
                                                <i class="bi bi-people"></i> Year <?= $yearOfStudy ?> 
                                                <small class="text-muted">(<?= $campusName ?>)</small>
                                            </td>
                                            <td class="text-center">
                                                <span class="record-count" id="int-<?= $intakeIds ?>">0</span>
                                            </td>
                                            <td><?= $groupCount ?> group(s)</td>
                                        </tr>

                                        <?php 
                                        foreach (($intake['groups'] ?? []) as $gid):
                                            $group = $studentGroups[$gid] ?? null;
                                            if (!$group) continue;
                                            $records = $recordCounts[$gid] ?? 0;
                                            $intakeTotal += $records;
                                            $progTotal += $records;
                                            $schoolTotal += $records;
                                            $collegeTotal += $records;
                                            $grandTotal += $records;
                                        ?>
                                        <tr class="level5">
                                            <td>      └─ 
                                                <i class="bi bi-people"></i> 
                                                <span class="text-dark">
                                                    <?= htmlspecialchars($group['name']) ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="record-count <?= $records > 0 ? 'has-records' : 'no-records' ?>">
                                                    <?= $records ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($records > 0): ?>
                                                    <span class="text-muted">Timetable records</span>
                                                <?php else: ?>
                                                    <span class="text-muted">No timetable records</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>

                                        <script>
                                            document.getElementById('int-<?= $intakeIds ?>').textContent = <?= $intakeTotal ?>;
                                            document.getElementById('int-<?= $intakeIds ?>').className = 'record-count ' + (<?= $intakeTotal ?> > 0 ? 'has-records' : 'no-records');
                                            
                                            document.getElementById('prog-<?= $pid ?>').textContent = <?= $progTotal ?>;
                                            document.getElementById('prog-<?= $pid ?>').className = 'record-count ' + (<?= $progTotal ?> > 0 ? 'has-records' : 'no-records');
                                            
                                            document.getElementById('sch-<?= $sid ?>').textContent = <?= $schoolTotal ?>;
                                            document.getElementById('sch-<?= $sid ?>').className = 'record-count ' + (<?= $schoolTotal ?> > 0 ? 'has-records' : 'no-records');
                                            
                                            document.getElementById('col-<?= $cid ?>').textContent = <?= $collegeTotal ?>;
                                            document.getElementById('col-<?= $cid ?>').className = 'record-count ' + (<?= $collegeTotal ?> > 0 ? 'has-records' : 'no-records');
                                        </script>
                                        <?php endforeach; ?>
                                        <?php endforeach; ?>
                                        <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-secondary">
                                        <tr>
                                            <th>GRAND TOTAL</th>
                                            <th class="text-center">
                                                <span class="record-count <?= $grandTotal > 0 ? 'has-records' : 'no-records' ?>">
                                                    <?= $grandTotal ?>
                                                </span>
                                            </th>
                                            <th>Timetable Records</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <section class="section">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="bi bi-building"></i> Campus Statistics
                            </h5>
                            
                            <div class="row">
                                <?php foreach ($campus_stats as $campus_id => $campus): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card h-100">
                                        <div class="card-header bg-primary text-white">
                                            <h5 class="mb-0"><?= htmlspecialchars($campus['campus_name']) ?></h5>
                                        </div>
                                        <br>
                                        <div class="card-body">
                                            <div class="row g-3">
                                                <div class="col-6">
                                                    <div class="d-flex align-items-center">
                                                        <div class="bg-primary bg-opacity-10 p-3 rounded-3 me-3">
                                                            <i class="bi bi-building text-primary"></i>
                                                        </div>
                                                        <div>
                                                            <h6 class="mb-0"><?= $campus['college_count'] ?></h6>
                                                            <small class="text-muted">Colleges</small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="d-flex align-items-center">
                                                        <div class="bg-secondary bg-opacity-10 p-3 rounded-3 me-3">
                                                            <i class="bi bi-house-door text-secondary"></i>
                                                        </div>
                                                        <div>
                                                            <h6 class="mb-0"><?= $campus['school_count'] ?></h6>
                                                            <small class="text-muted">Schools</small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                     <div class="d-flex align-items-center">
                                                        <div class="bg-success bg-opacity-10 p-3 rounded-3 me-3">
                                                            <i class="bi bi-book text-success"></i>
                                                        </div>
                                                        <div>
                                                            <h6 class="mb-0"><?= $campus['program_count'] ?></h6>
                                                            <small class="text-muted">Programs</small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="d-flex align-items-center">
                                                        <div class="bg-info bg-opacity-10 p-3 rounded-3 me-3">
                                                            <i class="bi bi-people text-info"></i>
                                                        </div>
                                                        <div>
                                                            <h6 class="mb-0"><?= $campus['intake_count'] ?></h6>
                                                            <small class="text-muted">Intakes</small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="d-flex align-items-center">
                                                        <div class="bg-warning bg-opacity-10 p-3 rounded-3 me-3">
                                                            <i class="bi bi-collection text-warning"></i>
                                                        </div>
                                                        <div>
                                                            <h6 class="mb-0"><?= $campus['group_count'] ?></h6>
                                                            <small class="text-muted">Groups</small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <?php if (isset($intake_distribution[$campus_id])): ?>
                                            <div class="mt-3">
                                                <h6 class="small text-muted mb-2">Promotions</h6>
                                                <div class="progress" style="height: 10px;">
                                                    <?php 
                                                    // Group by year_of_study
                                                    $promotion_groups = [];
                                                    foreach ($intake_distribution[$campus_id]['intakes'] as $intake) {
                                                        // Get the year_of_study from the database
                                                        $year_query = "SELECT DISTINCT year_of_study FROM intake WHERE campus_id = ? AND CONCAT(year, '-', LPAD(month, 2, '0')) = ?";
                                                        $stmt = $connection->prepare($year_query);
                                                        $stmt->bind_param("is", $campus_id, $intake['intake_period']);
                                                        $stmt->execute();
                                                        $year_result = $stmt->get_result();
                                                        
                                                        while ($year_row = $year_result->fetch_assoc()) {
                                                            $year_of_study = $year_row['year_of_study'];
                                                            if (!isset($promotion_groups[$year_of_study])) {
                                                                $promotion_groups[$year_of_study] = 0;
                                                            }
                                                            $promotion_groups[$year_of_study] += $intake['group_count'];
                                                        }
                                                    }
                                                    
                                                    $total_groups = array_sum($promotion_groups);
                                                    
                                                    foreach ($promotion_groups as $year_of_study => $count): 
                                                        $width = $total_groups > 0 ? ($count / $total_groups) * 100 : 0;
                                                        $random_color = '#' . substr(md5($year_of_study), 0, 6);
                                                    ?>
                                                    <div class="progress-bar" 
                                                         role="progressbar" 
                                                         style="width: <?= $width ?>%; background-color: <?= $random_color ?>;" 
                                                         title="Year <?= $year_of_study ?>: <?= $count ?> groups">
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <div class="d-flex flex-wrap mt-2">
                                                    <?php foreach ($promotion_groups as $year_of_study => $count): 
                                                        $random_color = '#' . substr(md5($year_of_study), 0, 6);
                                                    ?>
                                                    <div class="me-3 mb-1">
                                                        <span class="d-inline-block me-1" style="width: 10px; height: 10px; background-color: <?= $random_color ?>;"></span>
                                                        <small>Year <?= $year_of_study ?> (<?= $count ?>)</small>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <div class="back-to-top d-flex align-items-center justify-content-center">
        <i class="bi bi-arrow-up-short"></i>
    </div>

    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/simple-datatables/simple-datatables.js"></script>
    <script src="assets/vendor/tinymce/tinymce.min.js"></script>
    <script src="assets/vendor/php-email-form/validate.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
</body>
</html>