<?php
include 'connection.php';

// Get default academic year and semester from system table
$system_query = "SELECT s.accademic_year_id, s.semester, ay.year_label 
                 FROM system s 
                 LEFT JOIN academic_year ay ON s.accademic_year_id = ay.id 
                  LIMIT 1";
$system_result = $connection->query($system_query);

$current_academic_year = '';
$current_semester = '';
$academic_year_label = '';

if ($system_result->num_rows > 0) {
    $system_data = $system_result->fetch_assoc();
    $current_academic_year = $system_data['accademic_year_id'];
    $current_semester = $system_data['semester'];
    $academic_year_label = $system_data['year_label'];
}

// Use the system-set values
$semester = $current_semester;
$academic_year = $current_academic_year;

// Initialize conflicts arrays
$lecturer_conflicts = [];
$facility_conflicts = [];
$group_conflicts = [];

// Function to check time overlap
function checkTimeOverlap($start1, $end1, $start2, $end2, $day1, $day2) {
    if ($day1 !== $day2) return false;
    
    $start1 = strtotime($start1);
    $end1 = strtotime($end1);
    $start2 = strtotime($start2);
    $end2 = strtotime($end2);
    
    return ($start1 < $end2 && $end1 > $start2);
}

// Build WHERE clause using system-set values
$where_clause = "WHERE t.semester = '$semester' AND t.academic_year_id = '$academic_year'";

// Query to get all timetable sessions with related data including student group details
$query = "
    SELECT 
        ts.id as session_id,
        ts.timetable_id,
        ts.day,
        ts.start_time,
        ts.end_time,
        t.module_id,
        t.leader_lecturer_id,
        t.facility_id,
        t.semester,
        t.academic_year_id,
        m.name as module_name,
        m.code as module_code,
        u.names as lecturer_name,
        f.name as facility_name,
        f.capacity as facility_capacity,
        sg.id as group_id,
        sg.name as group_name,
        sg.size as group_size,
        i.year as intake_year,
        i.month as intake_month,
        i.year_of_study,
        p.name as program_name,
        p.code as program_code,
        s.name as school_name,
        c.name as college_name,
        GROUP_CONCAT(DISTINCT tg.group_id) as groups,
        GROUP_CONCAT(DISTINCT tl.lect_id) as additional_lecturers
    FROM timetable_sessions ts
    INNER JOIN timetable t ON ts.timetable_id = t.id
    LEFT JOIN module m ON t.module_id = m.id
    LEFT JOIN users u ON t.leader_lecturer_id = u.id
    LEFT JOIN facility f ON t.facility_id = f.id
    LEFT JOIN timetable_groups tg ON t.id = tg.timetable_id
    LEFT JOIN student_group sg ON tg.group_id = sg.id
    LEFT JOIN intake i ON sg.intake_id = i.id
    LEFT JOIN program p ON i.program_id = p.id
    LEFT JOIN school s ON p.school_id = s.id
    LEFT JOIN college c ON s.college_id = c.id
    LEFT JOIN timetable_lecturers tl ON t.id = tl.timetable_id
    $where_clause
    GROUP BY ts.id, sg.id
    ORDER BY ts.day, ts.start_time
";

$result = $connection->query($query);
$sessions = [];

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $sessions[] = $row;
    }
}

// Detect conflicts
for ($i = 0; $i < count($sessions); $i++) {
    for ($j = $i + 1; $j < count($sessions); $j++) {
        $session1 = $sessions[$i];
        $session2 = $sessions[$j];
        
        // Skip if it's the same timetable session (different groups in same session)
        if ($session1['timetable_id'] == $session2['timetable_id'] && 
            $session1['day'] == $session2['day'] &&
            $session1['start_time'] == $session2['start_time'] &&
            $session1['end_time'] == $session2['end_time']) {
            continue;
        }
        
        // Check if sessions overlap in time
        if (checkTimeOverlap(
            $session1['start_time'], 
            $session1['end_time'], 
            $session2['start_time'], 
            $session2['end_time'],
            $session1['day'],
            $session2['day']
        )) {
            
            // Get all lecturers for both sessions (leader + additional)
            $session1_lecturers = [];
            $session2_lecturers = [];
            
            // Add leader lecturers if they exist
            if (!empty($session1['leader_lecturer_id'])) {
                $session1_lecturers[] = [
                    'id' => $session1['leader_lecturer_id'],
                    'name' => $session1['lecturer_name'],
                    'type' => 'Leader Lecturer'
                ];
            }
            
            if (!empty($session2['leader_lecturer_id'])) {
                $session2_lecturers[] = [
                    'id' => $session2['leader_lecturer_id'],
                    'name' => $session2['lecturer_name'],
                    'type' => 'Leader Lecturer'
                ];
            }
            
            // Add additional lecturers
            $lect1 = array_filter(explode(',', $session1['additional_lecturers'] ?? ''));
            $lect2 = array_filter(explode(',', $session2['additional_lecturers'] ?? ''));
            
            foreach ($lect1 as $lect_id) {
                if (!empty($lect_id)) {
                    $lect_name_query = $connection->query("SELECT names FROM users WHERE id = '$lect_id'");
                    $lect_name = $lect_name_query->num_rows > 0 ? $lect_name_query->fetch_assoc()['names'] : 'Unknown';
                    $session1_lecturers[] = [
                        'id' => $lect_id,
                        'name' => $lect_name,
                        'type' => 'Additional Lecturer'
                    ];
                }
            }
            
            foreach ($lect2 as $lect_id) {
                if (!empty($lect_id)) {
                    $lect_name_query = $connection->query("SELECT names FROM users WHERE id = '$lect_id'");
                    $lect_name = $lect_name_query->num_rows > 0 ? $lect_name_query->fetch_assoc()['names'] : 'Unknown';
                    $session2_lecturers[] = [
                        'id' => $lect_id,
                        'name' => $lect_name,
                        'type' => 'Additional Lecturer'
                    ];
                }
            }
            
            // Check for lecturer conflicts - only if there's only one lecturer in common
            $session1_lecturer_ids = array_column($session1_lecturers, 'id');
            $session2_lecturer_ids = array_column($session2_lecturers, 'id');
            $common_lecturer_ids = array_intersect($session1_lecturer_ids, $session2_lecturer_ids);
            
            foreach ($common_lecturer_ids as $lect_id) {
                if (!empty($lect_id)) {
                    // Get lecturer details from session1 (where they might be leader or additional)
                    $lecturer = current(array_filter($session1_lecturers, function($l) use ($lect_id) {
                        return $l['id'] == $lect_id;
                    }));
                    
                    // Only create conflict if this is the only lecturer in both sessions
                    if ((count($session1_lecturers) === 1 && count($session2_lecturers) === 1) || 
                        (count($session1_lecturers) === 1 && count($common_lecturer_ids) === 1) ||
                        (count($session2_lecturers) === 1 && count($common_lecturer_ids) === 1)) {
                        
                        $conflict_key = $lect_id . '_' . $session1['session_id'] . '_' . $session2['session_id'];
                        $lecturer_conflicts[$conflict_key] = [
                            'lecturer_id' => $lect_id,
                            'lecturer_name' => $lecturer['name'],
                            'session1' => array_merge($session1, ['all_lecturers' => $session1_lecturers]),
                            'session2' => array_merge($session2, ['all_lecturers' => $session2_lecturers]),
                            'conflict_type' => $lecturer['type'],
                            'is_conflict' => true
                        ];
                    } else {
                        // Still show the conflict but mark it as non-critical
                        $conflict_key = $lect_id . '_' . $session1['session_id'] . '_' . $session2['session_id'] . '_info';
                        $lecturer_conflicts[$conflict_key] = [
                            'lecturer_id' => $lect_id,
                            'lecturer_name' => $lecturer['name'],
                            'session1' => array_merge($session1, ['all_lecturers' => $session1_lecturers]),
                            'session2' => array_merge($session2, ['all_lecturers' => $session2_lecturers]),
                            'conflict_type' => $lecturer['type'] . ' (Non-critical)',
                            'is_conflict' => false
                        ];
                    }
                }
            }
            
            // Check facility conflicts - only flag if total group size exceeds facility capacity
            if ($session1['facility_id'] == $session2['facility_id'] && 
                !empty($session1['facility_id']) && 
                $session1['facility_capacity'] < ($session1['group_size'] + $session2['group_size'])) {
                
                $conflict_key = $session1['facility_id'] . '_' . $session1['session_id'] . '_' . $session2['session_id'];
                $facility_conflicts[$conflict_key] = [
                    'facility_id' => $session1['facility_id'],
                    'facility_name' => $session1['facility_name'],
                    'facility_capacity' => $session1['facility_capacity'],
                    'session1' => $session1,
                    'session2' => $session2,
                    'conflict_type' => 'Facility',
                    'total_students' => $session1['group_size'] + $session2['group_size']
                ];
            }
            
            // Check group conflicts - only flag if it's the same group in different modules at the same time
            if ($session1['group_id'] == $session2['group_id'] && 
                !empty($session1['group_id']) && 
                $session1['module_id'] != $session2['module_id']) {
                
                $conflict_key = $session1['group_id'] . '_' . $session1['session_id'] . '_' . $session2['session_id'];
                $group_conflicts[$conflict_key] = [
                    'group_id' => $session1['group_id'],
                    'group_name' => $session1['group_name'],
                    'group_size' => $session1['group_size'],
                    'session1' => $session1,
                    'session2' => $session2,
                    'conflict_type' => 'Group'
                ];
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timetable Conflicts Detection</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Smooth scrolling for the entire page */
        html {
            scroll-behavior: smooth;
        }
        
        /* Fixed header */
        .fixed-header {
            position: sticky;
            top: 0;
            background: white;
            z-index: 1000;
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin: -20px -20px 20px -20px;
            padding: 20px;
        }
        
        /* Navigation buttons */
        .nav-buttons {
            display: flex;
            gap: 10px;
            margin: 15px 0;
            flex-wrap: wrap;
        }
        
        .nav-btn {
            padding: 8px 15px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }
        
        .nav-btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        .nav-btn i {
            font-size: 14px;
        }
        
        /* Back to top button */
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #2c3e50;
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
            z-index: 999;
        }
        
        .back-to-top.visible {
            opacity: 1;
            visibility: visible;
        }
        
        .back-to-top:hover {
            background: #1a252f;
            transform: translateY(-3px);
        }
        
        /* Conflict sections */
        .conflict-section {
            margin-bottom: 30px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .conflict-header {
            background: #f8f9fa;
            padding: 15px 20px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s;
        }
        
        .conflict-header:hover {
            background: #e9ecef;
        }
        
        .conflict-header i {
            transition: transform 0.3s;
        }
        
        .conflict-header.collapsed i {
            transform: rotate(-90deg);
        }
        
        .conflict-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-in-out;
            padding: 0 20px;
        }
        
        .conflict-content.expanded {
            max-height: 5000px; /* Adjust based on your content */
            padding: 20px;
            overflow-y: auto;
        }
        
        /* Scrollbar styling */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #bdc3c7;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #95a5a6;
        }
        
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --danger: #e74c3c;
            --warning: #f39c12;
            --success: #27ae60;
            --light: #ecf0f1;
            --dark: #34495e;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: var(--primary);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        .current-session-info {
            background: var(--secondary);
            color: white;
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid #bdc3c7;
        }
        
        .session-badge {
            display: inline-flex;
            align-items: center;
            gap: 15px;
            background: rgba(255,255,255,0.2);
            padding: 12px 25px;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .session-badge i {
            font-size: 1.3rem;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            padding: 25px;
            background: white;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-left: 5px solid var(--secondary);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.lecturer { border-left-color: var(--danger); }
        .stat-card.facility { border-left-color: var(--warning); }
        .stat-card.group { border-left-color: var(--success); }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .stat-card.lecturer .stat-number { color: var(--danger); }
        .stat-card.facility .stat-number { color: var(--warning); }
        .stat-card.group .stat-number { color: var(--success); }
        
        .stat-label {
            color: var(--dark);
            font-size: 1rem;
            font-weight: 600;
        }
        
        .conflict-section {
            margin: 0;
            border-bottom: 1px solid #eee;
        }
        
        .conflict-header {
            background: var(--dark);
            color: white;
            padding: 20px 25px;
            font-weight: 600;
            font-size: 1.2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        
        .conflict-header:hover {
            background: #2c3e50;
        }
        
        .conflict-content {
            padding: 0;
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .conflict-content.expanded {
            padding: 25px;
            max-height: 2000px;
        }
        
        .conflict-item {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        
        .conflict-item:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .conflict-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px dashed #dee2e6;
        }
        
        .conflict-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--danger);
        }
        
        .conflict-type {
            background: var(--danger);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .sessions-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .session-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .session-card:hover {
            border-color: var(--secondary);
        }
        
        .session-header {
            background: var(--primary);
            color: white;
            padding: 12px 15px;
            border-radius: 6px;
            margin: -20px -20px 15px -20px;
            font-weight: 600;
        }
        
        .session-details {
            display: grid;
            gap: 10px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .detail-label {
            font-weight: 600;
            color: var(--dark);
        }
        
        .detail-value {
            color: #555;
            text-align: right;
        }
        
        .student-group-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        
        .group-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
        }
        
        .no-conflicts {
            background: var(--success);
            color: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            font-size: 1.1rem;
        }
        
        .toggle-icon {
            transition: transform 0.3s ease;
        }
        
        .expanded .toggle-icon {
            transform: rotate(180deg);
        }
        
        .toggle-all-btn {
            background: var(--warning);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 20px auto;
            transition: all 0.3s ease;
        }
        
        .toggle-all-btn:hover {
            background: #e67e22;
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .sessions-container {
                grid-template-columns: 1fr;
            }
            
            .conflict-meta {
                flex-direction: column;
                gap: 10px;
                align-items: start;
            }
            
            .session-badge {
                flex-direction: column;
                gap: 8px;
            }
        }
        
        .conflict-item {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            border-left: 4px solid #e74c3c;
        }
        
        .non-critical-conflict {
            border-left-color: #f39c12;
            opacity: 0.9;
        }
        
        .non-critical-badge {
            background: #f39c12;
            color: white;
            font-size: 0.8em;
            padding: 2px 8px;
            border-radius: 10px;
            margin-left: 10px;
            font-weight: normal;
        }
        
        .lecturers-info {
            display: flex;
            padding: 10px 15px;
            background: #f9f9f9;
            border-bottom: 1px solid #eee;
            flex-wrap: wrap;
        }
        
        .lecturers-session {
            flex: 1;
            min-width: 200px;
            margin: 5px 0;
            padding: 0 10px;
        }
        
        .lecturer-tag {
            display: inline-block;
            background: #e8f4fc;
            padding: 2px 8px;
            border-radius: 12px;
            margin: 3px 3px 3px 0;
            font-size: 0.85em;
            border: 1px solid #d0e3f1;
        }
        
        .conflict-lecturer {
            background: #ffebee;
            border-color: #ffcdd2;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <!-- Back to top button -->
    <div class="back-to-top" id="backToTop">
        <i class="fas fa-arrow-up"></i>
    </div>
    
    <div class="container">
        <!-- Fixed Header -->
        <div class="fixed-header">
            <div class="header">
                <h1><i class="fas fa-exclamation-triangle"></i> Timetable Conflicts Detection</h1>
                <p>Identify and resolve scheduling conflicts in your academic timetable</p>
            </div>
            
            <!-- Navigation Buttons -->
            <div class="nav-buttons">
                <button class="nav-btn" onclick="scrollToSection('lecturer')">
                    <i class="fas fa-chalkboard-teacher"></i> Lecturer Conflicts (<?= count($lecturer_conflicts) ?>)</button>
                <button class="nav-btn" onclick="scrollToSection('facility')">
                    <i class="fas fa-building"></i> Facility Conflicts (<?= count($facility_conflicts) ?>)</button>
                <button class="nav-btn" onclick="scrollToSection('group')">
                    <i class="fas fa-users"></i> Group Conflicts (<?= count($group_conflicts) ?>)</button>
            </div>
        </div>
        
        <!-- Current Session Info -->
        <div class="current-session-info">
            <div class="session-badge">
                <div>
                    <i class="fas fa-calendar-alt"></i> Academic Year: 
                    <strong><?= !empty($academic_year_label) ? htmlspecialchars($academic_year_label) : $current_academic_year ?></strong>
                </div>
                <div>
                    <i class="fas fa-clock"></i> Semester: 
                    <strong><?= $current_semester ?></strong>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats">
            <div class="stat-card lecturer">
                <div class="stat-number"><?= count($lecturer_conflicts) ?></div>
                <div class="stat-label"><i class="fas fa-chalkboard-teacher"></i> Lecturer Conflicts</div>
            </div>
            <div class="stat-card facility">
                <div class="stat-number"><?= count($facility_conflicts) ?></div>
                <div class="stat-label"><i class="fas fa-building"></i> Facility Conflicts</div>
            </div>
            <div class="stat-card group">
                <div class="stat-number"><?= count($group_conflicts) ?></div>
                <div class="stat-label"><i class="fas fa-users"></i> Group Conflicts</div>
            </div>
        </div>

        <!-- Toggle All Button -->
        <button class="toggle-all-btn" onclick="toggleAllSections()">
            <i class="fas fa-expand-arrows-alt"></i> Toggle All Sections
        </button>

        <!-- Lecturer Conflicts -->
        <div class="conflict-section">
            <div class="conflict-header" onclick="toggleSection('lecturer')">
                <span><i class="fas fa-chalkboard-teacher"></i> Lecturer Conflicts (<?= count($lecturer_conflicts) ?>)</span>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="conflict-content" id="lecturer-content">
                <?php if (empty($lecturer_conflicts)): ?>
                    <div class="no-conflicts">
                        <i class="fas fa-check-circle"></i> No lecturer conflicts found for <?= !empty($academic_year_label) ? htmlspecialchars($academic_year_label) : $current_academic_year ?> - Semester <?= $current_semester ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($lecturer_conflicts as $conflict): ?>
                        <div class="conflict-item <?= $conflict['is_conflict'] ? 'critical-conflict' : 'non-critical-conflict' ?>">
                            <div class="conflict-meta">
                                <div class="conflict-title">
                                    <i class="fas fa-user-tie"></i> 
                                    <?= htmlspecialchars($conflict['lecturer_name']) ?>
                                    <?php if (!$conflict['is_conflict']): ?>
                                        <span class="non-critical-badge">Non-Critical</span>
                                    <?php endif; ?>
                                </div>
                                <div class="conflict-type"><?= htmlspecialchars($conflict['conflict_type']) ?></div>
                            </div>
                            <div class="lecturers-info">
                                <div class="lecturers-session">
                                    <strong>Session 1 Lecturers:</strong><br>
                                    <?php foreach ($conflict['session1']['all_lecturers'] as $lect): ?>
                                        <span class="lecturer-tag <?= $lect['id'] == $conflict['lecturer_id'] ? 'conflict-lecturer' : '' ?>">
                                            <?= htmlspecialchars($lect['name']) ?> (<?= $lect['type'] ?>)
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                                <div class="lecturers-session">
                                    <strong>Session 2 Lecturers:</strong><br>
                                    <?php foreach ($conflict['session2']['all_lecturers'] as $lect): ?>
                                        <span class="lecturer-tag <?= $lect['id'] == $conflict['lecturer_id'] ? 'conflict-lecturer' : '' ?>">
                                            <?= htmlspecialchars($lect['name']) ?> (<?= $lect['type'] ?>)
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="sessions-container">
                                <div class="session-card">
                                    <div class="session-header">Session 1 Details</div>
                                    <div class="session-details">
                                        <div class="detail-row">
                                            <span class="detail-label">Timetable ID:</span>
                                            <span class="detail-value"><?= $conflict['session1']['timetable_id'] ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Module:</span>
                                            <span class="detail-value"><?= htmlspecialchars($conflict['session1']['module_name']) ?> (ID: <?= $conflict['session1']['module_id'] ?>)</span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Facility:</span>
                                            <span class="detail-value"><?= htmlspecialchars($conflict['session1']['facility_name']) ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Day & Time:</span>
                                            <span class="detail-value"><?= $conflict['session1']['day'] ?>, <?= substr($conflict['session1']['start_time'], 0, 5) ?> - <?= substr($conflict['session1']['end_time'], 0, 5) ?></span>
                                        </div>
                                        <?php if (!empty($conflict['session1']['group_name'])): ?>
                                        <div class="student-group-info">
                                            <div class="group-details">
                                                <div><strong>Group:</strong> <?= $conflict['session1']['group_name'] ?> (<?= $conflict['session1']['group_size'] ?> students)</div>
                                                <div><strong>Intake:</strong> <?= $conflict['session1']['intake_month'] ?> <?= $conflict['session1']['intake_year'] ?></div>
                                                <div><strong>Program:</strong> <?= $conflict['session1']['program_name'] ?></div>
                                                <div><strong>School:</strong> <?= $conflict['session1']['school_name'] ?></div>
                                                <div><strong>College:</strong> <?= $conflict['session1']['college_name'] ?></div>
                                                <div><strong>Year of Study:</strong> <?= $conflict['session1']['year_of_study'] ?></div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="session-card">
                                    <div class="session-header">Session 2 Details</div>
                                    <div class="session-details">
                                        <div class="detail-row">
                                            <span class="detail-label">Timetable ID:</span>
                                            <span class="detail-value"><?= $conflict['session2']['timetable_id'] ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Module:</span>
                                            <span class="detail-value"><?= htmlspecialchars($conflict['session2']['module_name']) ?> (ID: <?= $conflict['session2']['module_id'] ?>)</span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Facility:</span>
                                            <span class="detail-value"><?= htmlspecialchars($conflict['session2']['facility_name']) ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Day & Time:</span>
                                            <span class="detail-value"><?= $conflict['session2']['day'] ?>, <?= substr($conflict['session2']['start_time'], 0, 5) ?> - <?= substr($conflict['session2']['end_time'], 0, 5) ?></span>
                                        </div>
                                        <?php if (!empty($conflict['session2']['group_name'])): ?>
                                        <div class="student-group-info">
                                            <div class="group-details">
                                                <div><strong>Group:</strong> <?= $conflict['session2']['group_name'] ?> (<?= $conflict['session2']['group_size'] ?> students)</div>
                                                <div><strong>Intake:</strong> <?= $conflict['session2']['intake_month'] ?> <?= $conflict['session2']['intake_year'] ?></div>
                                                <div><strong>Program:</strong> <?= $conflict['session2']['program_name'] ?></div>
                                                <div><strong>School:</strong> <?= $conflict['session2']['school_name'] ?></div>
                                                <div><strong>College:</strong> <?= $conflict['session2']['college_name'] ?></div>
                                                <div><strong>Year of Study:</strong> <?= $conflict['session2']['year_of_study'] ?></div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Facility Conflicts -->
        <div class="conflict-section">
            <div class="conflict-header" onclick="toggleSection('facility')">
                <span><i class="fas fa-building"></i> Facility Conflicts (<?= count($facility_conflicts) ?>)</span>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="conflict-content" id="facility-content">
                <?php if (empty($facility_conflicts)): ?>
                    <div class="no-conflicts">
                        <i class="fas fa-check-circle"></i> No facility conflicts found for <?= !empty($academic_year_label) ? htmlspecialchars($academic_year_label) : $current_academic_year ?> - Semester <?= $current_semester ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($facility_conflicts as $conflict): ?>
                        <div class="conflict-item">
                            <div class="conflict-meta">
                                <div class="conflict-title">
                                    <i class="fas fa-door-open"></i> <?= htmlspecialchars($conflict['facility_name']) ?> (Capacity: <?= $conflict['facility_capacity'] ?>)
                                </div>
                                <div class="conflict-type"><?= $conflict['conflict_type'] ?></div>
                            </div>
                            <div class="sessions-container">
                                <div class="session-card">
                                    <div class="session-header">Session 1 Details</div>
                                    <div class="session-details">
                                        <div class="detail-row">
                                            <span class="detail-label">Timetable ID:</span>
                                            <span class="detail-value"><?= $conflict['session1']['timetable_id'] ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Module:</span>
                                            <span class="detail-value"><?= htmlspecialchars($conflict['session1']['module_name']) ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Lecturer:</span>
                                            <span class="detai  l-value">
                                                <?= htmlspecialchars($conflict['session1']['lecturer_name']) ?>
                                                <?php if (!empty($conflict['session1']['additional_lecturers'])): ?>
                                                    <div class="additional-lecturers" style="margin-top: 5px; padding-left: 10px; border-left: 2px solid #ddd;">
                                                        <strong>Additional Lecturers:</strong><br>
                                                        <?php 
                                                        $additional_lecturers = explode(',', $conflict['session1']['additional_lecturers']);
                                                        $lecturer_names = [];
                                                        foreach ($additional_lecturers as $lect_id) {
                                                            if (!empty($lect_id)) {
                                                                $lect_name_query = $connection->query("SELECT names FROM users WHERE id = '$lect_id'");
                                                                if ($lect_name_query && $lect_name_query->num_rows > 0) {
                                                                    $lecturer_names[] = htmlspecialchars($lect_name_query->fetch_assoc()['names']);
                                                                }
                                                            }
                                                        }
                                                        echo implode("<br>", $lecturer_names);
                                                        ?>
                                                    </div>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Day & Time:</span>
                                            <span class="detail-value"><?= $conflict['session1']['day'] ?>, <?= substr($conflict['session1']['start_time'], 0, 5) ?> - <?= substr($conflict['session1']['end_time'], 0, 5) ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="session-card">
                                    <div class="session-header">Session 2 Details</div>
                                    <div class="session-details">
                                        <div class="detail-row">
                                            <span class="detail-label">Timetable ID:</span>
                                            <span class="detail-value"><?= $conflict['session2']['timetable_id'] ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Module:</span>
                                            <span class="detail-value"><?= htmlspecialchars($conflict['session2']['module_name']) ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Lecturer:</span>
                                            <span class="detail-value">
                                                <?= htmlspecialchars($conflict['session2']['lecturer_name']) ?>
                                                <?php if (!empty($conflict['session2']['additional_lecturers'])): ?>
                                                    <div class="additional-lecturers" style="margin-top: 5px; padding-left: 10px; border-left: 2px solid #ddd;">
                                                        <strong>Additional Lecturers:</strong><br>
                                                        <?php 
                                                        $additional_lecturers = explode(',', $conflict['session2']['additional_lecturers']);
                                                        $lecturer_names = [];
                                                        foreach ($additional_lecturers as $lect_id) {
                                                            if (!empty($lect_id)) {
                                                                $lect_name_query = $connection->query("SELECT names FROM users WHERE id = '$lect_id'");
                                                                if ($lect_name_query && $lect_name_query->num_rows > 0) {
                                                                    $lecturer_names[] = htmlspecialchars($lect_name_query->fetch_assoc()['names']);
                                                                }
                                                            }
                                                        }
                                                        echo implode("<br>", $lecturer_names);
                                                        ?>
                                                    </div>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Day & Time:</span>
                                            <span class="detail-value"><?= $conflict['session2']['day'] ?>, <?= substr($conflict['session2']['start_time'], 0, 5) ?> - <?= substr($conflict['session2']['end_time'], 0, 5) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Group Conflicts -->
        <div class="conflict-section">
            <div class="conflict-header" onclick="toggleSection('group')">
                <span><i class="fas fa-users"></i> Group Conflicts (<?= count($group_conflicts) ?>)</span>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="conflict-content" id="group-content">
                <?php if (empty($group_conflicts)): ?>
                    <div class="no-conflicts">
                        <i class="fas fa-check-circle"></i> No group conflicts found for <?= !empty($academic_year_label) ? htmlspecialchars($academic_year_label) : $current_academic_year ?> - Semester <?= $current_semester ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($group_conflicts as $conflict): ?>
                        <div class="conflict-item">
                            <div class="conflict-meta">
                                <div class="conflict-title">
                                    <i class="fas fa-user-friends"></i> <?= htmlspecialchars($conflict['group_name']) ?> (<?= $conflict['group_size'] ?> students)
                                </div>
                                <div class="conflict-type"><?= $conflict['conflict_type'] ?></div>
                            </div>
                            <div class="student-group-info">
                                <div class="group-details">
                                    <div><strong>Year of Study:</strong> Year <?= $conflict['session1']['year_of_study'] ?></div>
                                    <div><strong>Campus:</strong> <?= $conflict['session1']['campus_name'] ?? 'N/A' ?></div>
                                </div>
                            </div>  
                            <div class="sessions-container">
                                <div class="session-card">
                                    <div class="session-header">Session 1 Details</div>
                                    <div class="session-details">
                                        <div class="detail-row">
                                            <span class="detail-label">Timetable ID:</span>
                                            <span class="detail-value"><?= $conflict['session1']['timetable_id'] ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Module:</span>
                                            <span class="detail-value"><?= htmlspecialchars($conflict['session1']['module_name']) ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Lecturer:</span>
                                            <span class="detail-value">
                                                <?= htmlspecialchars($conflict['session1']['lecturer_name']) ?>
                                                <?php if (!empty($conflict['session1']['additional_lecturers'])): ?>
                                                    <div class="additional-lecturers" style="margin-top: 5px; padding-left: 10px; border-left: 2px solid #ddd;">
                                                        <strong>Additional Lecturers:</strong><br>
                                                        <?php 
                                                        $additional_lecturers = explode(',', $conflict['session1']['additional_lecturers']);
                                                        $lecturer_names = [];
                                                        foreach ($additional_lecturers as $lect_id) {
                                                            if (!empty($lect_id)) {
                                                                $lect_name_query = $connection->query("SELECT names FROM users WHERE id = '$lect_id'");
                                                                if ($lect_name_query && $lect_name_query->num_rows > 0) {
                                                                    $lecturer_names[] = htmlspecialchars($lect_name_query->fetch_assoc()['names']);
                                                                }
                                                            }
                                                        }
                                                        echo implode("<br>", $lecturer_names);
                                                        ?>
                                                    </div>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Facility:</span>
                                            <span class="detail-value"><?= htmlspecialchars($conflict['session1']['facility_name']) ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Day & Time:</span>
                                            <span class="detail-value"><?= $conflict['session1']['day'] ?>, <?= substr($conflict['session1']['start_time'], 0, 5) ?> - <?= substr($conflict['session1']['end_time'], 0, 5) ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="session-card">
                                    <div class="session-header">Session 2 Details</div>
                                    <div class="session-details">
                                        <div class="detail-row">
                                            <span class="detail-label">Timetable ID:</span>
                                            <span class="detail-value"><?= $conflict['session2']['timetable_id'] ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Module:</span>
                                            <span class="detail-value"><?= htmlspecialchars($conflict['session2']['module_name']) ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Lecturer:</span>
                                            <span class="detail-value">
                                                <?= htmlspecialchars($conflict['session2']['lecturer_name']) ?>
                                                <?php if (!empty($conflict['session2']['additional_lecturers'])): ?>
                                                    <div class="additional-lecturers" style="margin-top: 5px; padding-left: 10px; border-left: 2px solid #ddd;">
                                                        <strong>Additional Lecturers:</strong><br>
                                                        <?php 
                                                        $additional_lecturers = explode(',', $conflict['session2']['additional_lecturers']);
                                                        $lecturer_names = [];
                                                        foreach ($additional_lecturers as $lect_id) {
                                                            if (!empty($lect_id)) {
                                                                $lect_name_query = $connection->query("SELECT names FROM users WHERE id = '$lect_id'");
                                                                if ($lect_name_query && $lect_name_query->num_rows > 0) {
                                                                    $lecturer_names[] = htmlspecialchars($lect_name_query->fetch_assoc()['names']);
                                                                }
                                                            }
                                                        }
                                                        echo implode("<br>", $lecturer_names);
                                                        ?>
                                                    </div>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Facility:</span>
                                            <span class="detail-value"><?= htmlspecialchars($conflict['session2']['facility_name']) ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Day & Time:</span>
                                            <span class="detail-value"><?= $conflict['session2']['day'] ?>, <?= substr($conflict['session2']['start_time'], 0, 5) ?> - <?= substr($conflict['session2']['end_time'], 0, 5) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Move the script to the head and wrap in DOMContentLoaded -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle section visibility
        function toggleSection(section) {
            const content = document.getElementById(section + '-content');
            const header = content?.previousElementSibling;
            if (!content || !header) return;
            
            // Toggle expanded/collapsed state
            const isExpanding = !content.classList.contains('expanded');
            
            // Update classes
            content.classList.toggle('expanded', isExpanding);
            header.classList.toggle('expanded', isExpanding);
            
            // Update icon rotation
            const icon = header.querySelector('i');
            if (icon) {
                icon.style.transform = isExpanding ? 'rotate(0deg)' : 'rotate(-90deg)';
            }
            
            // Smooth scroll to the section when expanding
            if (isExpanding) {
                setTimeout(() => {
                    content.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 100);
            }
        }
        
        // Scroll to specific section
        function scrollToSection(section) {
            const element = document.getElementById(section + '-content');
            if (element) {
                // Expand if collapsed
                if (!element.classList.contains('expanded')) {
                    toggleSection(section);
                }
                // Scroll to the section
                setTimeout(() => {
                    element.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 100);
            }
        }
        
        // Initialize all sections
        function initializeSections() {
            const sections = ['lecturer', 'facility', 'group'];
            
            sections.forEach(section => {
                const content = document.getElementById(section + '-content');
                const header = content?.previousElementSibling;
                if (!content || !header) return;
                
                // Check if section has conflicts
                const hasConflicts = content.querySelector('.conflict-item') !== null;
                
                // Set initial state - expand if there are conflicts
                if (hasConflicts) {
                    content.classList.add('expanded');
                    header.classList.add('expanded');
                } else {
                    content.classList.remove('expanded');
                    header.classList.remove('expanded');
                    // Rotate icon for collapsed sections
                    const icon = header.querySelector('i');
                    if (icon) {
                        icon.style.transform = 'rotate(-90deg)';
                    }
                }
                
                // Add click handler
                header.onclick = (e) => {
                    // Don't trigger if clicking on a link inside the header
                    if (e.target.tagName === 'A') return;
                    toggleSection(section);
                };
            });
        }
        
        // Back to top button
        const backToTop = document.getElementById('backToTop');
        if (backToTop) {
            window.addEventListener('scroll', () => {
                backToTop.classList.toggle('visible', window.pageYOffset > 300);
            });
            
            backToTop.addEventListener('click', () => {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
        }
        
        // Initialize everything
        initializeSections();
        
        // Make functions available globally
        window.toggleSection = toggleSection;
        window.scrollToSection = scrollToSection;
    });
    </script>
</body>
</html>