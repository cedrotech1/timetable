<?php
session_start();
include 'connection.php';
// include 'includes/header.php';
// include 'includes/sidebar.php';

// Get all users who are assigned to timetables (either as leader or additional lecturer)
$lecturers_query = "
    SELECT DISTINCT u.id, u.names, u.email 
    FROM users u
    WHERE u.id IN (
        SELECT leader_lecturer_id FROM timetable WHERE leader_lecturer_id IS NOT NULL
        UNION
        SELECT lect_id FROM timetable_lecturers WHERE lect_id IS NOT NULL
    )
    ORDER BY u.names
";
$lecturers_result = $connection->query($lecturers_query);

// Get current academic year and semester
$system_query = "SELECT s.accademic_year_id, s.semester, ay.year_label 
                 FROM system s 
                 LEFT JOIN academic_year ay ON s.accademic_year_id = ay.id 
                 LIMIT 1";
$system_result = $connection->query($system_query);
$system_data = $system_result->fetch_assoc();
$current_academic_year = $system_data['accademic_year_id'];
$current_semester = $system_data['semester'];
$academic_year_label = $system_data['year_label'];

// Debug output
echo "<!-- Debug: Current Academic Year ID: $current_academic_year -->\n";
echo "<!-- Debug: Current Semester: $current_semester -->\n";
echo "<!-- Debug: Academic Year Label: $academic_year_label -->\n";

// Debug: Check if we have any timetable sessions for this semester/year
$debug_query = "SELECT COUNT(*) as session_count FROM timetable_sessions ts 
                JOIN timetable t ON ts.timetable_id = t.id 
                WHERE t.semester = '$current_semester' 
                AND t.academic_year_id = '$current_academic_year'";
$debug_result = $connection->query($debug_query);
$debug_data = $debug_result->fetch_assoc();
echo "<!-- Debug: Found {$debug_data['session_count']} timetable sessions for semester $current_semester, year $current_academic_year -->\n";

// Debug: Check if we have any lecturers
$lecturer_count = $connection->query("SELECT COUNT(*) as cnt FROM users WHERE role = 'lecturer'")->fetch_assoc()['cnt'];
echo "<!-- Debug: Found $lecturer_count lecturers in the system -->\n";

// Debug: Check what academic years and semesters exist in the timetable table
$years_semesters = $connection->query("
    SELECT 
        t.academic_year_id, 
        ay.year_label, 
        t.semester, 
        COUNT(*) as session_count
    FROM timetable t
    LEFT JOIN academic_year ay ON t.academic_year_id = ay.id
    GROUP BY t.academic_year_id, t.semester
    ORDER BY t.academic_year_id, t.semester
");

echo "<!-- Debug: Available academic years and semesters in timetable table -->\n";
while ($row = $years_semesters->fetch_assoc()) {
    echo sprintf(
        '<!-- Year: %s (ID: %d), Semester: %d, Sessions: %d -->%s',
        htmlspecialchars($row['year_label'] ?? 'N/A'),
        $row['academic_year_id'],
        $row['semester'],
        $row['session_count'],
        "\n"
    );
}

// Debug: Check if there are any timetable sessions at all
$any_sessions = $connection->query("SELECT COUNT(*) as cnt FROM timetable_sessions")->fetch_assoc()['cnt'];
echo "<!-- Debug: Total timetable sessions in the system: $any_sessions -->\n";

// Initialize workload data array
$workload_data = [];

// Process each lecturer's workload
if ($lecturers_result->num_rows > 0) {
    while ($lecturer = $lecturers_result->fetch_assoc()) {
        $lecturer_id = $lecturer['id'];
        
        // Get all sessions for this lecturer (as leader or additional)
        $workload_query = "
            SELECT 
                ts.id as session_id,
                ts.day,
                ts.start_time,
                ts.end_time,
                m.name as module_name,
                m.code as module_code,
                m.credits,
                f.name as facility_name,
                GROUP_CONCAT(DISTINCT sg.name ORDER BY sg.name SEPARATOR ', ') as group_names,
                COUNT(DISTINCT ts.id) as session_count,
                SUM(TIMESTAMPDIFF(HOUR, CONCAT('2000-01-01 ', ts.start_time), CONCAT('2000-01-01 ', ts.end_time))) as total_hours
            FROM timetable t
            INNER JOIN timetable_sessions ts ON t.id = ts.timetable_id
            LEFT JOIN module m ON t.module_id = m.id
            LEFT JOIN facility f ON t.facility_id = f.id
            LEFT JOIN timetable_groups tg ON t.id = tg.timetable_id
            LEFT JOIN student_group sg ON tg.group_id = sg.id
            WHERE (t.leader_lecturer_id = ? OR t.id IN (
                SELECT timetable_id FROM timetable_lecturers WHERE lect_id = ?
            ))
            AND t.semester = ?
            AND t.academic_year_id = ?
            GROUP BY ts.id, ts.day, ts.start_time, ts.end_time, m.name, m.code, m.credits, f.name
            ORDER BY ts.day, ts.start_time
        ";
        
        // Debug: Print the query for this lecturer
        $debug_workload_query = str_replace('?', "'$lecturer_id'", str_replace('?', "'$lecturer_id'", str_replace('?', "'$current_semester'", str_replace('?', "'$current_academic_year'", $workload_query))));
        echo "<!-- Debug: Workload query for lecturer $lecturer_id: " . htmlspecialchars($debug_workload_query) . " -->\n";
        
        $stmt = $connection->prepare($workload_query);
        $stmt->bind_param('iiii', $lecturer_id, $lecturer_id, $current_semester, $current_academic_year);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Debug: Check how many sessions were found for this lecturer
        $session_count = $result->num_rows;
        echo "<!-- Debug: Found $session_count sessions for lecturer $lecturer_id -->\n";
        
        $sessions = [];
        $total_hours = 0;
        $module_count = 0;
        $unique_modules = [];
        $days = [
            'Monday' => 0,
            'Tuesday' => 0,
            'Wednesday' => 0,
            'Thursday' => 0,
            'Friday' => 0,
            'Saturday' => 0,
            'Sunday' => 0
        ];
        
        while ($row = $result->fetch_assoc()) {
            $sessions[] = $row;
            $total_hours += $row['total_hours'];
            
            // Track unique modules
            if (!in_array($row['module_code'], $unique_modules)) {
                $unique_modules[] = $row['module_code'];
                $module_count++;
            }
            
            // Track hours per day
            if (isset($days[$row['day']])) {
                $days[$row['day']] += $row['total_hours'];
            }
        }
        
        // Calculate workload status
        $workload_status = 'light';
        if ($total_hours > 20) {
            $workload_status = 'danger';
        } elseif ($total_hours > 15) {
            $workload_status = 'warning';
        } elseif ($total_hours > 0) {
            $workload_status = 'success';
        }
        
        // Add to workload data
        $workload_data[] = [
            'id' => $lecturer_id,
            'name' => $lecturer['names'],
            'email' => $lecturer['email'],
            'total_hours' => $total_hours,
            'module_count' => $module_count,
            'session_count' => count($sessions),
            'status' => $workload_status,
            'days' => $days,
            'sessions' => $sessions
        ];
    }
}

// Sort by total hours (descending)
usort($workload_data, function($a, $b) {
    return $b['total_hours'] - $a['total_hours'];
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturers Workload - Timetable System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        .workload-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border-left: 4px solid #0d6efd;
        }
        .workload-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .workload-status {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        .status-danger { background-color: #dc3545; }
        .status-warning { background-color: #ffc107; }
        .status-success { background-color: #198754; }
        .status-light { background-color: #6c757d; }
        .day-chart {
            height: 100px;
        }
        .module-badge {
            font-size: 0.8rem;
            margin-right: 0.3rem;
            margin-bottom: 0.3rem;
        }
        .workload-summary {
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-people-fill me-2"></i>Lecturers Workload</h2>
            <div>
                <span class="badge bg-primary me-2">Semester <?= $current_semester ?></span>
                <span class="badge bg-secondary"><?= $academic_year_label ?></span>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Total Lecturers</h5>
                        <h2 class="mb-0"><?= count($workload_data) ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Average Hours/Week</h5>
                        <h2 class="mb-0">
                            <?= count($workload_data) > 0 ? 
                                round(array_sum(array_column($workload_data, 'total_hours')) / count($workload_data), 1) : 0 ?>
                        </h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark mb-3">
                    <div class="card-body">
                        <h5 class="card-title">High Workload</h5>
                        <h2 class="mb-0">
                            <?= count(array_filter($workload_data, function($w) { return $w['status'] === 'danger'; })) ?>
                        </h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Total Teaching Hours</h5>
                        <h2 class="mb-0">
                            <?= array_sum(array_column($workload_data, 'total_hours')) ?>
                        </h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Lecturers List</h5>
                    <div class="d-flex
                        <div class="input-group input-group-sm" style="width: 250px;">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" id="searchInput" class="form-control" placeholder="Search lecturers...">
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="workloadTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>Lecturer</th>
                                <th>Total Hours</th>
                                <th>Modules</th>
                                <th>Sessions</th>
                                <th>Daily Average</th>
                                <th>Workload</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($workload_data as $workload): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="me-2">
                                                <div class="avatar-sm bg-light rounded-circle d-flex align-items-center justify-content-center">
                                                    <i class="bi bi-person-fill text-primary"></i>
                                                </div>
                                            </div>
                                            <div>
                                                <h6 class="mb-0"><?= htmlspecialchars($workload['name']) ?></h6>
                                                <small class="text-muted"><?= $workload['email'] ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="fw-bold"><?= $workload['total_hours'] ?></span> hrs
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?= $workload['module_count'] ?> modules</span>
                                    </td>
                                    <td><?= $workload['session_count'] ?> sessions</td>
                                    <td>
                                        <?php 
                                            $working_days = count(array_filter($workload['days'], function($h) { return $h > 0; }));
                                            $daily_avg = $working_days > 0 ? $workload['total_hours'] / $working_days : 0;
                                            echo round($daily_avg, 1) . ' hrs/day';
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                            $progress = min(100, ($workload['total_hours'] / 20) * 100);
                                            $status_class = $workload['status'] === 'danger' ? 'bg-danger' : 
                                                          ($workload['status'] === 'warning' ? 'bg-warning' : 'bg-success');
                                        ?>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar progress-bar-striped progress-bar-animated <?= $status_class ?>" 
                                                 role="progressbar" 
                                                 style="width: <?= $progress ?>%" 
                                                 aria-valuenow="<?= $progress ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                                <?= $workload['total_hours'] ?> hrs
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary view-details" 
                                                data-lecturer-id="<?= $workload['id'] ?>"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#detailsModal">
                                            <i class="bi bi-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailsModalLabel">Lecturer Workload Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="lecturerDetails">
                    <!-- Content will be loaded via AJAX -->
                    <div class="text-center my-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary">
                        <i class="bi bi-printer"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        // Global chart instance
        let workloadChart = null;
        
        $(document).ready(function() {
            // Initialize DataTable
            var table = $('#workloadTable').DataTable({
                pageLength: 25,
                order: [[1, 'desc']], // Sort by total hours by default
                dom: '<"top"f>rt<"bottom"lip><"clear">',
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search lecturers...",
                }
            });

            // Handle view details button click
            $(document).on('click', '.view-details', function() {
                var lecturerId = $(this).data('lecturer-id');
                loadLecturerDetails(lecturerId);
            });

            // Function to load lecturer details via AJAX
            function loadLecturerDetails(lecturerId) {
                $('#lecturerDetails').html(`
                    <div class="text-center my-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                `);

                // Show the modal first
                var detailsModal = new bootstrap.Modal(document.getElementById('detailsModal'));
                detailsModal.show();

                $.ajax({
                    url: 'get_lecturer_workload.php',
                    type: 'GET',
                    data: { lecturer_id: lecturerId },
                    success: function(response) {
                        $('#lecturerDetails').html(response);
                        // Initialize chart after modal is shown
                        $('#detailsModal').on('shown.bs.modal', function() {
                            renderWorkloadChart();
                        });
                    },
                    error: function() {
                        $('#lecturerDetails').html('<div class="alert alert-danger">Error loading lecturer details.</div>');
                    }
                });
            }

            // Function to render the workload chart
            function renderWorkloadChart() {
                const chartElement = document.getElementById('workloadChart');
                if (!chartElement) {
                    console.error('Chart element not found');
                    return;
                }

                // Get chart data from data attribute
                const chartDataStr = chartElement.getAttribute('data-chart');
                if (!chartDataStr) {
                    console.error('No chart data found');
                    return;
                }

                let chartData;
                try {
                    // Parse the JSON data
                    chartData = JSON.parse(chartDataStr);
                    
                    // Ensure we have valid data
                    if (!chartData || typeof chartData !== 'object') {
                        throw new Error('Invalid chart data format');
                    }
                } catch (e) {
                    console.error('Error parsing chart data:', e);
                    return;
                }

                // Get the canvas context
                const ctx = chartElement.getContext('2d');
                if (!ctx) {
                    console.error('Could not get 2D context');
                    return;
                }

                // Destroy previous chart instance if it exists
                if (workloadChart) {
                    workloadChart.destroy();
                }

                // Create days array in order
                const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                const labels = [];
                const data = [];
                
                // Ensure all days are in the chart, even if they have 0 hours
                days.forEach(day => {
                    labels.push(day);
                    data.push(chartData[day] || 0);
                });

                // Create new chart instance
                workloadChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Teaching Hours',
                            data: data,
                            backgroundColor: 'rgba(13, 110, 253, 0.5)',
                            borderColor: 'rgba(13, 110, 253, 1)',
                            borderWidth: 1,
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Hours',
                                    font: {
                                        weight: 'bold'
                                    }
                                },
                                ticks: {
                                    stepSize: 1
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Day of Week',
                                    font: {
                                        weight: 'bold'
                                    }
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.parsed.y + ' hour' + (context.parsed.y !== 1 ? 's' : '');
                                    }
                                }
                            }
                        },
                        animation: {
                            duration: 1000,
                            easing: 'easeInOutQuart'
                        }
                    }
                });
            }

            // Handle modal shown event to render chart
            var detailsModal = document.getElementById('detailsModal');
            if (detailsModal) {
                detailsModal.addEventListener('shown.bs.modal', function() {
                    renderWorkloadChart();
                });
            }
        });
    </script>
</body>
</html>
