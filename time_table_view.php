<?php
session_start();
include('connection.php');

// Get all campuses
$campuses = [];
$res = mysqli_query($connection, "SELECT id, name FROM campus ORDER BY name");
while ($row = mysqli_fetch_assoc($res)) $campuses[] = $row;

// Get all colleges
$colleges = [];
$res = mysqli_query($connection, "SELECT id, name, campus_id FROM college ORDER BY name");
while ($row = mysqli_fetch_assoc($res)) $colleges[] = $row;

// Get all schools
$schools = [];
$res = mysqli_query($connection, "SELECT id, name, college_id FROM school ORDER BY name");
while ($row = mysqli_fetch_assoc($res)) $schools[] = $row;

// Get all departments
$departments = [];
$res = mysqli_query($connection, "SELECT id, name, school_id FROM department ORDER BY name");
while ($row = mysqli_fetch_assoc($res)) $departments[] = $row;

// Get all programs
$programs = [];
$res = mysqli_query($connection, "SELECT id, name, department_id FROM program ORDER BY name");
while ($row = mysqli_fetch_assoc($res)) $programs[] = $row;

// Get all intakes
$intakes = [];
$res = mysqli_query($connection, "SELECT id, name, program_id FROM intake ORDER BY name");
while ($row = mysqli_fetch_assoc($res)) $intakes[] = $row;

// Get all student groups
$student_groups = [];
$res = mysqli_query($connection, "SELECT id, name, intake_id FROM student_group ORDER BY name");
while ($row = mysqli_fetch_assoc($res)) $student_groups[] = $row;

// Get academic years
$years = [];
$res = mysqli_query($connection, "SELECT id, year_label FROM academic_year ORDER BY year_label DESC");
while ($row = mysqli_fetch_assoc($res)) $years[] = $row;

$semesters = ['1', '2'];

// Get all groups
$groups = [];
$res = mysqli_query($connection, "SELECT g.id, g.name, g.intake_id, i.program_id, p.department_id, d.school_id, s.college_id, c.campus_id 
                                FROM student_group g 
                                JOIN intake i ON g.intake_id = i.id 
                                JOIN program p ON i.program_id = p.id 
                                JOIN department d ON p.department_id = d.id 
                                JOIN school s ON d.school_id = s.id 
                                JOIN college c ON s.college_id = c.id 
                                ORDER BY g.name");
while ($row = mysqli_fetch_assoc($res)) $groups[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>UR-TIMETABLE</title>
    
    <!-- Favicons -->
    <link href="assets/img/icon1.png" rel="icon">
    <link href="assets/img/icon1.png" rel="apple-touch-icon">
    
    <!-- Google Fonts -->
    <link href="https://fonts.gstatic.com" rel="preconnect">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">
    
    <!-- Vendor CSS Files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
    <link href="assets/vendor/quill/quill.snow.css" rel="stylesheet">
    <link href="assets/vendor/quill/quill.bubble.css" rel="stylesheet">
    <link href="assets/vendor/remixicon/remixicon.css" rel="stylesheet">
    <link href="assets/vendor/simple-datatables/style.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <style>
        .filters-section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .filter-step {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            background: #f8f9fa;
        }
        
        .filter-step.active {
            border-color: #012970;
            background: #fff;
        }
        
        .filter-step .step-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .filter-step .step-title {
            font-weight: 600;
            color: #012970;
            margin: 0;
        }
        
        .filter-step .step-number {
            background: #012970;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }
        
        .filter-group {
            margin-bottom: 15px;
        }
        
        .filter-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: #012970;
            margin-bottom: 8px;
        }
        
        .filter-group label i {
            font-size: 1.1rem;
        }
        
        .group-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }
        
        .group-tag {
            background: #e8f4ff;
            color: #012970;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .group-tag .remove-tag {
            cursor: pointer;
            color: #dc3545;
        }
        
        .group-tag .remove-tag:hover {
            color: #bd2130;
        }
        
        .timetable-table {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .timetable-table thead th {
            background: #012970;
            color: white;
            font-weight: 600;
            padding: 15px;
            border: none;
            text-align: center;
        }
        
        .timetable-table tbody td {
            padding: 12px 15px;
            vertical-align: middle;
            border-bottom: 1px solid #e9ecef;
        }
        
        .timetable-table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .timetable-table .badge {
            padding: 6px 10px;
            font-weight: 500;
            border-radius: 6px;
        }
        
        .timetable-table .badge-primary {
            background: #012970;
        }
        
        .timetable-table .badge-secondary {
            background: #6c757d;
        }
        
        .timetable-table .badge-success {
            background: #28a745;
        }
        
        .timetable-table .badge-info {
            background: #17a2b8;
        }
        
        .timetable-table .badge-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .timetable-table .badge-danger {
            background: #dc3545;
        }
        
        .loading {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        
        .select2-container--default .select2-selection--single {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            height: 42px;
            background-color: #f8f9fa;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 42px;
            padding-left: 15px;
            color: #495057;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px;
        }
        
        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #012970;
        }
        
        .select2-dropdown {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
        }
        
        .timetable-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }
        
        .timetable-card:hover {
            transform: translateY(-5px);
        }
        
        .timetable-card .card-header {
            background: #012970;
            color: white;
            padding: 15px 20px;
            border-radius: 10px 10px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .timetable-card .card-body {
            padding: 20px;
        }
        
        .timetable-card .module-info {
            margin-bottom: 15px;
        }
        
        .timetable-card .module-code {
            font-size: 0.9em;
            color: #666;
        }
        
        .timetable-card .time-info {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        
        .timetable-card .group-info {
            margin-top: 20px;
        }
        
        .group-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            border: 1px solid #e9ecef;
        }
        
        .group-card .group-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .group-card .group-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
        }
        
        .group-card .group-details div {
            font-size: 0.9em;
            color: #495057;
        }
        
        .group-card .group-details i {
            color: #012970;
            margin-right: 5px;
        }
        
        .timetable-card .facility-info {
            background: #e8f4ff;
            padding: 10px;
            border-radius: 5px;
            margin-top: 15px;
        }
        
        .timetable-card .lecturer-info {
            color: #666;
            font-size: 0.9em;
            margin-top: 10px;
        }
        
        .day-header {
            background: #012970;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            margin: 20px 0 10px 0;
        }
        
        @media (max-width: 768px) {
            .filters-section {
                padding: 15px;
            }
            
            .filters-section .col-md-2 {
                width: 100%;
            }
            
            .filters-section .btn-group {
                display: flex;
                flex-direction: column;
            }
            
            .filters-section .btn-group .btn {
                margin-right: 0;
                margin-bottom: 10px;
            }
            
            .timetable-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <main id="main" class="main">
        <div class="container-fluid py-4">
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="mb-0">Time Table</h2>
                </div>
            </div>
            
            <!-- Filters Section -->
            <div class="filters-section">
                <div class="card">
                    <div class="card-header">
                        <h5>Time Table Filters</h5>
                    </div>
                    <div class="card-body">
                        <form id="filterForm" method="GET">
                            <!-- Step 1: Academic Year and Semester -->
                            <div class="filter-step active" id="academic-step">
                                <div class="step-header">
                                    <h6 class="step-title">Step 1: Academic Information</h6>
                                    <span class="step-number">1</span>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="filter-group">
                                            <label><i class="bi bi-calendar"></i> Academic Year</label>
                                            <select class="form-select" id="year" name="year" required>
                                                <option value="">Select Year</option>
                                                <?php foreach ($years as $year): ?>
                                                    <option value="<?php echo $year['id']; ?>"><?php echo htmlspecialchars($year['year_label']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="filter-group">
                                            <label><i class="bi bi-book"></i> Semester</label>
                                            <select class="form-select" id="semester" name="semester" required>
                                                <option value="">Select Semester</option>
                                                <?php foreach ($semesters as $semester): ?>
                                                    <option value="<?php echo $semester; ?>"><?php echo htmlspecialchars($semester); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 2: Group Selection -->
                            <div class="filter-step" id="group-step">
                                <div class="step-header">
                                    <h6 class="step-title">Step 2: Group Selection</h6>
                                    <span class="step-number">2</span>
                                </div>
                                <div class="filter-group">
                                    <label><i class="bi bi-people"></i> Select Groups</label>
                                    <select class="form-select" id="group_id" name="group_id[]" multiple>
                                        <option value="">All Groups</option>
                                    </select>
                                    <div class="group-tags" id="selectedGroups"></div>
                                </div>
                            </div>

                            <div class="text-end mt-4">
                                <button type="submit" class="btn btn-primary">View Time Table</button>
                                <button type="reset" class="btn btn-secondary">Reset</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Timetable Table -->
            <div class="timetable-table">
                <div class="table-responsive">
                    <table class="table" id="timetableTable">
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>Time</th>
                                <th>Module</th>
                                <th>Lecturer</th>
                                <th>Facility</th>
                                <th>Groups</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
    
    <div class="loading" id="loadingIndicator" style="display: none;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
    
    <!-- Vendor JS Files -->
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Select2 for group selection
            $('#group_id').select2({
                placeholder: "Select groups",
                allowClear: true,
                width: '100%'
            });

            // Store all groups data
            const groups = <?php echo json_encode($groups); ?>;

            // Function to update group tags
            function updateGroupTags() {
                const selectedGroups = $('#group_id').val() || [];
                const tagsContainer = $('#selectedGroups');
                tagsContainer.empty();

                selectedGroups.forEach(groupId => {
                    const group = groups.find(g => g.id === groupId);
                    if (group) {
                        const tag = $(`
                            <div class="group-tag">
                                <span>${group.name}</span>
                                <i class="bi bi-x-circle remove-tag" data-group-id="${group.id}"></i>
                            </div>
                        `);
                        tagsContainer.append(tag);
                    }
                });
            }

            // Handle group selection change
            $('#group_id').on('change', function() {
                updateGroupTags();
            });

            // Handle tag removal
            $(document).on('click', '.remove-tag', function() {
                const groupId = $(this).data('group-id');
                const selectedGroups = $('#group_id').val() || [];
                const newSelection = selectedGroups.filter(id => id !== groupId);
                $('#group_id').val(newSelection).trigger('change');
            });

            // Form submission
            $('#filterForm').on('submit', function(e) {
                e.preventDefault();
                const year = $('#year').val();
                const semester = $('#semester').val();
                const selectedGroups = $('#group_id').val() || [];

                if (!year || !semester) {
                    alert('Please select both Academic Year and Semester');
                    return;
                }

                // Show loading indicator
                $('#loadingIndicator').show();

                // Make AJAX call to fetch timetable
                $.ajax({
                    url: 'get_timetable.php',
                    method: 'POST',
                    data: {
                        year: year,
                        semester: semester,
                        groups: selectedGroups
                    },
                    success: function(response) {
                        // Update timetable table
                        updateTimetable(response);
                    },
                    error: function() {
                        alert('Error fetching timetable data');
                    },
                    complete: function() {
                        $('#loadingIndicator').hide();
                    }
                });
            });

            // Function to update timetable
            function updateTimetable(data) {
                const tbody = $('#timetableTable tbody');
                tbody.empty();

                data.forEach(row => {
                    const tr = $(`
                        <tr>
                            <td>${row.day}</td>
                            <td>${row.time}</td>
                            <td>${row.module}</td>
                            <td>${row.lecturer}</td>
                            <td>${row.facility}</td>
                            <td>${row.groups}</td>
                        </tr>
                    `);
                    tbody.append(tr);
                });
            }

            // Form reset
            $('button[type="reset"]').on('click', function() {
                setTimeout(() => {
                    $('#group_id').val(null).trigger('change');
                    updateGroupTags();
                }, 0);
            });
        });
    </script>
</body>
</html> 