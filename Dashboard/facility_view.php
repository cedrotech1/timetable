<?php
session_start();
include('connection.php');

// Get facility ID
$facility_id = $_GET['id'] ?? null;
if (!$facility_id) {
    header("Location: facilities.php");
    exit;
}

// Get facility
$stmt = $connection->prepare("
    SELECT f.*, c.name AS campus_name, s.name AS site_name
    FROM facility f
    LEFT JOIN campus c ON f.campus_id = c.id
    LEFT JOIN site s ON f.site = s.id
    WHERE f.id = ?
");
$stmt->bind_param("i", $facility_id);
$stmt->execute();
$result = $stmt->get_result();
$facility = $result->fetch_assoc() ?: [];
if (!$facility) {
    header("Location: facilities.php");
    exit;
}

// Get facility timetables with groups and sessions
$stmt2 = $connection->prepare("
    SELECT t.id AS timetable_id, t.semester, t.academic_year_id, 
           s.day, s.start_time, s.end_time,
           g.name AS group_name, p.name AS program_name, d.name AS department_name,
           m.name AS module_name, m.code AS module_code
    FROM timetable t
    LEFT JOIN timetable_sessions s ON s.timetable_id = t.id
    LEFT JOIN timetable_groups tg ON tg.timetable_id = t.id
    LEFT JOIN student_group g ON g.id = tg.group_id
    LEFT JOIN intake i ON i.id = g.intake_id
    LEFT JOIN program p ON p.id = i.program_id
    LEFT JOIN department d ON d.id = p.department_id
    LEFT JOIN module m ON m.id = t.module_id
    WHERE t.facility_id = ? AND t.semester = ? AND t.academic_year_id = ?
    ORDER BY t.id, s.day, s.start_time
");

$semester = $_SESSION['semester'] ?? 1;
$academic_year_id = $_SESSION['academic_year_id'] ?? 1;

// accademic year label
$academic_year_query = "SELECT * FROM academic_year WHERE id = ?";
$academic_year_stmt = $connection->prepare($academic_year_query);
$academic_year_stmt->bind_param("i", $academic_year_id);
$academic_year_stmt->execute();
$academic_year_result = $academic_year_stmt->get_result();
$academic_year = $academic_year_result->fetch_assoc();

$stmt2->bind_param("iii", $facility_id, $semester, $academic_year_id);
$stmt2->execute();
$result2 = $stmt2->get_result();

// Organize data for calendar
$calendar_data = [];
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$time_slots = [];

while ($row = $result2->fetch_assoc()) {
    if ($row['day'] && $row['start_time'] && $row['end_time']) {
        $day = $row['day'];
        $start_time = $row['start_time'];
        $end_time = $row['end_time'];
        
        // Create time slots
        if (!in_array($start_time, $time_slots)) {
            $time_slots[] = $start_time;
        }
        
        // Store session data
        $calendar_data[$day][$start_time][] = [
            'timetable_id' => $row['timetable_id'],
            'start_time' => $start_time,
            'end_time' => $end_time,
            'group_name' => $row['group_name'],
            'program_name' => $row['program_name'],
            'department_name' => $row['department_name'],
            'module_name' => $row['module_name'],
            'module_code' => $row['module_code'],
            'semester' => $row['semester']
        ];
    }
}

// Sort time slots
sort($time_slots);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    <title>UR-TIMETABLE - Facility Calendar</title>
    <link href="assets/img/icon1.png" rel="icon">
    <link href="assets/img/icon1.png" rel="apple-touch-icon">

    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">

    <style>
        .facility-header {
            background: #012a70;
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .facility-info {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: center;
        }

        .info-item {
            background: rgba(255, 255, 255, 0.1);
            padding: 8px 15px;
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }

        .info-item_accademic{
            background: rgb(10, 129, 123);
            padding: 8px 15px;
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }

        .calendar-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .calendar-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 2px solid #e9ecef;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: 100px repeat(7, 1fr);
            gap: 1px;
            background: #e9ecef;
        }

        .time-slot {
            background: #f8f9fa;
            padding: 15px 10px;
            text-align: center;
            font-weight: 600;
            font-size: 0.9rem;
            color: #495057;
            border-right: 2px solid #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 80px;
        }

        .day-header {
            background: #012a70;
            color: white;
            padding: 15px 10px;
            text-align: center;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .calendar-cell {
            background: white;
            padding: 8px;
            min-height: 80px;
            position: relative;
            transition: all 0.3s ease;
        }

        .calendar-cell:hover {
            background: #f8f9fa;
        }

        .session-block {
            background: #012a70;
            color: white;
            padding: 8px;
            border-radius: 8px;
            margin-bottom: 5px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .session-block:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .session-time {
            font-weight: 600;
            font-size: 0.75rem;
            opacity: 0.9;
        }

        .session-module {
            font-weight: 600;
            margin-bottom: 2px;
        }

        .session-group {
            font-size: 0.75rem;
            opacity: 0.9;
        }

        .session-program {
            font-size: 0.7rem;
            opacity: 0.8;
            margin-top: 2px;
        }

        .empty-cell {
            color: #adb5bd;
            font-style: italic;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
        }

        .legend {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .legend-item {
            display: inline-flex;
            align-items: center;
            margin-right: 20px;
            margin-bottom: 10px;
        }

        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            margin-right: 8px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #007bff;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #012a70;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .calendar-grid {
                grid-template-columns: 80px repeat(7, 1fr);
            }
        }

        @media (max-width: 992px) {
            .calendar-grid {
                grid-template-columns: 70px repeat(7, 1fr);
            }
            
            .day-header, .time-slot {
                font-size: 0.8rem;
                padding: 10px 5px;
            }
            
            .session-block {
                font-size: 0.75rem;
                padding: 6px;
            }
        }

        @media (max-width: 768px) {
            .calendar-grid {
                grid-template-columns: 1fr;
                gap: 0;
            }
            
            .day-header {
                display: none;
            }
            
            .time-slot {
                display: none;
            }
            
            .mobile-day-section {
                display: block;
                background: white;
                margin-bottom: 20px;
                border-radius: 10px;
                overflow: hidden;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            }
            
            .mobile-day-header {
                background: #007bff;
                color: white;
                padding: 15px;
                font-weight: 600;
                text-align: center;
            }
            
            .mobile-sessions {
                padding: 15px;
            }
            
            .mobile-session {
                background: #f8f9fa;
                border-left: 4px solid #007bff;
                padding: 15px;
                margin-bottom: 10px;
                border-radius: 8px;
            }
        }

        .desktop-calendar {
            display: block;
        }

        .mobile-calendar {
            display: none;
        }

        @media (max-width: 768px) {
            .desktop-calendar {
                display: none;
            }
            
            .mobile-calendar {
                display: block;
            }
        }

        /* Session Modal */
        .session-details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e9ecef;
        }

        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .detail-label {
            font-weight: 600;
            color: #495057;
        }

        .detail-value {
            color: #007bff;
        }
    </style>
</head>

<body>
    <?php include("./includes/header.php"); ?>
    <?php include("./includes/menu.php"); ?>

    <main id="main" class="main">
        <!-- Facility Header -->
        <div class="facility-header">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                <h2 class="mb-0">
                    <i class="bi bi-building"></i>
                    <?= htmlspecialchars($facility['name'] ?? 'Unknown Facility') ?>
                </h2>
                <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#editFacilityModal">
                    <i class="bi bi-pencil-square"></i> Edit facility
                </button>
            </div>
            <div class="facility-info">
            <div class="info-item">
                    <i class="bi bi-tag me-2"></i>
                    <strong>Facility Name:</strong> <?= htmlspecialchars($facility['name'] ?? 'N/A') ?>
                </div>
                <div class="info-item">
                    <i class="bi bi-tag me-2"></i>
                    <strong>Facility Name2:</strong> <?= htmlspecialchars($facility['name2'] ?? 'N/A') ?>
                </div>
                <div class="info-item">
                    <i class="bi bi-tag me-2"></i>
                    <strong>Building Name:</strong> <?= htmlspecialchars($facility['buildname'] ?? 'N/A') ?>
                </div>
                <div class="info-item">
                    <i class="bi bi-tag me-2"></i>
                    <strong>Building Code:</strong> <?= htmlspecialchars($facility['build_code'] ?? 'N/A') ?>
                </div>
                <div class="info-item">
                    <i class="bi bi-tag me-2"></i>
                    <strong>Type:</strong> <?= htmlspecialchars($facility['type'] ?? 'N/A') ?>
                </div>
                <div class="info-item">
                    <i class="bi bi-people me-2"></i>
                    <strong>Capacity / Size:</strong> <span id="displayCapacity"><?= htmlspecialchars((string)($facility['capacity'] ?? 'N/A')) ?></span>
                </div>
                <div class="info-item">
                    <i class="bi bi-geo-alt me-2"></i>
                    <strong>Campus:</strong> <?= htmlspecialchars($facility['campus_name'] ?? 'N/A') ?>
                </div>
                <div class="info-item">
                    <i class="bi bi-geo me-2"></i>
                    <strong>Site:</strong> <?= htmlspecialchars($facility['site_name'] ?? 'N/A') ?>
                </div>
                <hr>
                <br>
                  <!--accademic year -->
                  <div class="info-item_accademic">
                    <i class="bi bi-calendar me-2"></i>
                    <strong>Academic Year:</strong> <?= $academic_year['year_label'] ?? 'N/A' ?>
                </div>
                <hr>
                <div class="info-item_accademic">
                    <i class="bi bi-calendar me-2"></i>
                    <strong>Semester:</strong> <?= $semester ?>
                </div>
                <hr>             
            </div>
        </div>

        <!-- Edit Facility Modal -->
        <div class="modal fade" id="editFacilityModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Facility</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="editFacilityForm">
                        <div class="modal-body">
                            <div id="editFacilityAlert" class="alert d-none" role="alert"></div>
                            <input type="hidden" name="id" value="<?= (int)$facility['id'] ?>">
                            <input type="hidden" name="site_id" value="<?= (int)($facility['site'] ?? 0) ?>">
                            <input type="hidden" name="campus_id" value="<?= (int)($facility['campus_id'] ?? 0) ?>">

                            <div class="mb-3">
                                <label class="form-label">Name</label>
                                <input type="text" class="form-control" name="name" required
                                       value="<?= htmlspecialchars($facility['name'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Name 2</label>
                                <input type="text" class="form-control" name="name2"
                                       value="<?= htmlspecialchars($facility['name2'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Type</label>
                                <input type="text" class="form-control" name="type" required
                                       value="<?= htmlspecialchars($facility['type'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Capacity / Size <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="capacity" min="1" required
                                       value="<?= (int)($facility['capacity'] ?? 0) ?>">
                                <div class="form-text">Number of seats / students this facility can hold.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Building name</label>
                                <input type="text" class="form-control" name="buildname"
                                       value="<?= htmlspecialchars($facility['buildname'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Building code</label>
                                <input type="text" class="form-control" name="build_code"
                                       value="<?= htmlspecialchars($facility['build_code'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" id="btnSaveFacility">
                                <i class="bi bi-save"></i> Save changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <?php
        $total_sessions = 0;
        $unique_groups = [];
        $unique_programs = [];
        $time_coverage = [];

        foreach ($calendar_data as $day => $day_sessions) {
            foreach ($day_sessions as $time => $sessions) {
                foreach ($sessions as $session) {
                    $total_sessions++;
                    if ($session['group_name']) {
                        $unique_groups[$session['group_name']] = true;
                    }
                    if ($session['program_name']) {
                        $unique_programs[$session['program_name']] = true;
                    }
                    $time_coverage[$time] = true;
                }
            }
        }
        ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $total_sessions ?></div>
                <div class="stat-label">Total Sessions</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count($unique_groups) ?></div>
                <div class="stat-label">Student Groups</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count($unique_programs) ?></div>
                <div class="stat-label">Programs</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count($time_coverage) ?></div>
                <div class="stat-label">Time Slots Used</div>
            </div>
        </div>

        <!-- Desktop Calendar -->
        <div class="calendar-container desktop-calendar">
            <div class="calendar-header">
                <h4 class="mb-0">
                    <i class="bi bi-calendar-week me-2"></i>
                    Weekly Timetable
                </h4>
            </div>

            <?php if (empty($time_slots)): ?>
                <div class="text-center p-5">
                    <i class="bi bi-calendar-x display-1 text-muted mb-3"></i>
                    <h5 class="text-muted">No sessions scheduled for this facility</h5>
                    <p class="text-muted">There are currently no timetabled sessions for the selected semester and academic year.</p>
                </div>
            <?php else: ?>
                <div class="calendar-grid">
                    <!-- Header row -->
                    <div class="time-slot">Time</div>
                    <?php foreach ($days as $day): ?>
                        <div class="day-header"><?= $day ?></div>
                    <?php endforeach; ?>

                    <!-- Time slots and sessions -->
                    <?php foreach ($time_slots as $time): ?>
                        <div class="time-slot">
                            <?= date('H:i', strtotime($time)) ?>
                        </div>
                        <?php foreach ($days as $day): ?>
                            <div class="calendar-cell">
                                <?php if (isset($calendar_data[$day][$time])): ?>
                                    <?php foreach ($calendar_data[$day][$time] as $session): ?>
                                        <div class="session-block" 
                                             data-bs-toggle="modal" 
                                             data-bs-target="#sessionModal"
                                             data-session='<?= json_encode($session) ?>'>
                                            <div class="session-time">
                                                <?= date('H:i', strtotime($session['start_time'])) ?> - <?= date('H:i', strtotime($session['end_time'])) ?>
                                            </div>
                                            <div class="session-module">
                                                <?= htmlspecialchars($session['module_code'] ?? 'N/A') ?>
                                            </div>
                                            <div class="session-group">
                                                <?= htmlspecialchars($session['group_name'] ?? 'N/A') ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-cell">-</div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Mobile Calendar -->
        <div class="mobile-calendar">
            <?php if (empty($calendar_data)): ?>
                <div class="text-center p-5 bg-white rounded">
                    <i class="bi bi-calendar-x display-1 text-muted mb-3"></i>
                    <h5 class="text-muted">No sessions scheduled</h5>
                    <p class="text-muted">There are currently no timetabled sessions for this facility.</p>
                </div>
            <?php else: ?>
                <?php foreach ($days as $day): ?>
                    <?php if (isset($calendar_data[$day])): ?>
                        <div class="mobile-day-section">
                            <div class="mobile-day-header">
                                <i class="bi bi-calendar-day me-2"></i>
                                <?= $day ?>
                            </div>
                            <div class="mobile-sessions">
                                <?php foreach ($calendar_data[$day] as $time => $sessions): ?>
                                    <?php foreach ($sessions as $session): ?>
                                        <div class="mobile-session" 
                                             data-bs-toggle="modal" 
                                             data-bs-target="#sessionModal"
                                             data-session='<?= json_encode($session) ?>'>
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <strong><?= htmlspecialchars($session['module_code'] ?? 'N/A') ?></strong>
                                                <span class="badge bg-primary">
                                                    <?= date('H:i', strtotime($session['start_time'])) ?> - <?= date('H:i', strtotime($session['end_time'])) ?>
                                                </span>
                                            </div>
                                            <div class="text-muted">
                                                <?= htmlspecialchars($session['group_name'] ?? 'N/A') ?><br>
                                                <small><?= htmlspecialchars($session['program_name'] ?? 'N/A') ?></small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Back Button -->
        <div class="text-center mt-4">
            <a href="facilities.php" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left me-2"></i>
                Back to Facilities
            </a>
        </div>
    </main>

    <!-- Session Details Modal -->
    <div class="modal fade" id="sessionModal" tabindex="-1" aria-labelledby="sessionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="sessionModalLabel">
                        <i class="bi bi-info-circle me-2"></i>
                        Session Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="sessionModalBody">
                    <!-- Session details will be populated here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const editForm = document.getElementById('editFacilityForm');
            if (editForm) {
                editForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const alertEl = document.getElementById('editFacilityAlert');
                    const btn = document.getElementById('btnSaveFacility');
                    const formData = new FormData(editForm);
                    const payload = {
                        id: parseInt(formData.get('id'), 10),
                        site_id: parseInt(formData.get('site_id'), 10),
                        campus_id: parseInt(formData.get('campus_id'), 10) || 1,
                        name: (formData.get('name') || '').trim(),
                        name2: (formData.get('name2') || '').trim(),
                        type: (formData.get('type') || '').trim(),
                        capacity: parseInt(formData.get('capacity'), 10),
                        buildname: (formData.get('buildname') || '').trim(),
                        build_code: (formData.get('build_code') || '').trim()
                    };

                    if (!payload.name || !payload.type || !payload.capacity || payload.capacity < 1) {
                        alertEl.className = 'alert alert-danger';
                        alertEl.textContent = 'Name, type and capacity (size) are required.';
                        alertEl.classList.remove('d-none');
                        return;
                    }

                    btn.disabled = true;
                    btn.innerHTML = 'Saving...';
                    alertEl.classList.add('d-none');

                    try {
                        const res = await fetch('update_facility.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(payload)
                        });
                        const data = await res.json();
                        if (data.success || (data.message && data.message.toLowerCase().includes('no changes'))) {
                            alertEl.className = 'alert alert-success';
                            alertEl.textContent = data.success ? 'Facility updated successfully.' : 'Saved (no field changes detected).';
                            alertEl.classList.remove('d-none');
                            setTimeout(() => window.location.reload(), 700);
                        } else {
                            alertEl.className = 'alert alert-danger';
                            alertEl.textContent = data.message || 'Failed to update facility.';
                            alertEl.classList.remove('d-none');
                        }
                    } catch (err) {
                        alertEl.className = 'alert alert-danger';
                        alertEl.textContent = 'Network error while saving.';
                        alertEl.classList.remove('d-none');
                    } finally {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="bi bi-save"></i> Save changes';
                    }
                });
            }

            // Session modal population
            const sessionModal = document.getElementById('sessionModal');
            const modalBody = document.getElementById('sessionModalBody');
            
            // Add click event to all session blocks
            document.querySelectorAll('[data-session]').forEach(function(element) {
                element.addEventListener('click', function() {
                    const sessionData = JSON.parse(this.getAttribute('data-session'));
                    
                    modalBody.innerHTML = `
                        <div class="session-details">
                            <div class="detail-row">
                                <span class="detail-label"><i class="bi bi-book me-2"></i>Module:</span>
                                <span class="detail-value">${sessionData.module_name || 'N/A'} (${sessionData.module_code || 'N/A'})</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label"><i class="bi bi-clock me-2"></i>Time:</span>
                                <span class="detail-value">${formatTime(sessionData.start_time)} - ${formatTime(sessionData.end_time)}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label"><i class="bi bi-people me-2"></i>Group:</span>
                                <span class="detail-value">${sessionData.group_name || 'N/A'}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label"><i class="bi bi-mortarboard me-2"></i>Program:</span>
                                <span class="detail-value">${sessionData.program_name || 'N/A'}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label"><i class="bi bi-building me-2"></i>Department:</span>
                                <span class="detail-value">${sessionData.department_name || 'N/A'}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label"><i class="bi bi-calendar me-2"></i>Semester:</span>
                                <span class="detail-value">${sessionData.semester || 'N/A'}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label"><i class="bi bi-hash me-2"></i>Timetable ID:</span>
                                <span class="detail-value">${sessionData.timetable_id || 'N/A'}</span>
                            </div>
                        </div>
                    `;
                });
            });
            
            function formatTime(timeString) {
                if (!timeString) return 'N/A';
                const date = new Date('1970-01-01T' + timeString + 'Z');
                return date.toLocaleTimeString('en-US', {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                });
            }
        });

        // Add some smooth scrolling for better UX
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>