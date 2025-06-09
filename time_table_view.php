<?php
session_start();
include('connection.php');

// Get all campuses
$campuses = [];
$res = mysqli_query($connection, "SELECT id, name FROM campus ORDER BY name");
while ($row = mysqli_fetch_assoc($res)) $campuses[] = $row;

// Get academic years
$years = [];
$res = mysqli_query($connection, "SELECT id, year_label FROM academic_year ORDER BY year_label DESC");
while ($row = mysqli_fetch_assoc($res)) $years[] = $row;

$semesters = ['1', '2'];
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
        
        .filters-section .card {
            border: none;
            box-shadow: none;
        }
        
        .filters-section .card-header {
            background: #012970;
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .filters-section .card-header h5 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .filters-section .form-label {
            font-weight: 600;
            color: #012970;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        
        .filters-section .form-select {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
        }
        
        .filters-section .form-select:hover {
            border-color: #012970;
            background-color: white;
        }
        
        .filters-section .form-select:focus {
            border-color: #012970;
            box-shadow: 0 0 0 0.2rem rgba(1, 41, 112, 0.25);
            background-color: white;
        }
        
        .filters-section .btn {
            padding: 10px 25px;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .filters-section .btn-primary {
            background: #012970;
            border-color: #012970;
        }
        
        .filters-section .btn-primary:hover {
            background: #001f5c;
            border-color: #001f5c;
            transform: translateY(-2px);
        }
        
        .filters-section .btn-secondary {
            background: #6c757d;
            border-color: #6c757d;
        }
        
        .filters-section .btn-secondary:hover {
            background: #5a6268;
            border-color: #5a6268;
            transform: translateY(-2px);
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
                        <h5><i class="bi bi-funnel"></i> Filter Timetable</h5>
                    </div>
                    <div class="card-body">
                        <form id="filterForm" class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label"><i class="bi bi-geo-alt"></i> Campus</label>
                                <select class="form-select" id="campus_id" name="campus_id">
                                    <option value="">All Campuses</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label"><i class="bi bi-building"></i> College</label>
                                <select class="form-select" id="college_id" name="college_id">
                                    <option value="">All Colleges</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label"><i class="bi bi-bank"></i> School</label>
                                <select class="form-select" id="school_id" name="school_id">
                                    <option value="">All Schools</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label"><i class="bi bi-diagram-3"></i> Department</label>
                                <select class="form-select" id="department_id" name="department_id">
                                    <option value="">All Departments</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label"><i class="bi bi-mortarboard"></i> Program</label>
                                <select class="form-select" id="program_id" name="program_id">
                                    <option value="">All Programs</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label"><i class="bi bi-people"></i> Intake</label>
                                <select class="form-select" id="intake_id" name="intake_id">
                                    <option value="">All Intakes</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label"><i class="bi bi-calendar"></i> Academic Year</label>
                                <select class="form-select" id="academic_year_id" name="academic_year_id">
                                    <option value="">All Years</option>
                                    <?php foreach ($years as $year): ?>
                                        <option value="<?php echo $year['id']; ?>"><?php echo $year['year_label']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label"><i class="bi bi-book"></i> Semester</label>
                                <select class="form-select" id="semester" name="semester">
                                    <option value="">All Semesters</option>
                                    <?php foreach ($semesters as $sem): ?>
                                        <option value="<?php echo $sem; ?>">Semester <?php echo $sem; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <div class="btn-group">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search"></i> Apply Filters
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="resetFilters()">
                                        <i class="bi bi-x-circle"></i> Reset
                                    </button>
                                </div>
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
        $(document).ready(function() {
            // Initialize Select2 for all select elements
            $('.form-select').select2({
                width: '100%'
            });
            
            // Load initial data for dropdowns
            loadCampuses();
            loadAcademicYears();
            
            // Add event listeners using jQuery
            $('#campus_id').on('change', handleCampusChange);
            $('#college_id').on('change', handleCollegeChange);
            $('#school_id').on('change', handleSchoolChange);
            $('#department_id').on('change', handleDepartmentChange);
            $('#program_id').on('change', handleProgramChange);
            $('#intake_id').on('change', loadTimetable);
            $('#academic_year_id').on('change', loadTimetable);
            $('#semester').on('change', loadTimetable);
            
            // Load timetable when page loads
            loadTimetable();
        });
        
        function showLoading() {
            $('#loadingIndicator').show();
        }
        
        function hideLoading() {
            $('#loadingIndicator').hide();
        }
        
        function resetFilters() {
            $('#filterForm')[0].reset();
            $('.form-select').val('').trigger('change');
            loadTimetable();
        }
        
        // Load academic years
        function loadAcademicYears() {
            const yearSelect = $('#academic_year_id');
            yearSelect.empty().append('<option value="">All Years</option>');
            <?php foreach ($years as $year): ?>
            yearSelect.append(`<option value="<?php echo $year['id']; ?>"><?php echo $year['year_label']; ?></option>`);
            <?php endforeach; ?>
        }
        
        // Load campuses
        function loadCampuses() {
            $.ajax({
                url: 'Dashboard/get_organization_structure.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data) {
                        const campusSelect = $('#campus_id');
                        campusSelect.empty().append('<option value="">All Campuses</option>');
                        response.data.forEach(campus => {
                            campusSelect.append(`<option value="${campus.id}">${campus.name}</option>`);
                        });
                    } else {
                        console.error('Error loading campuses:', response.error || 'Unknown error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading campuses:', {
                        status: status,
                        error: error,
                        response: xhr.responseText
                    });
                }
            });
        }
        
        function handleCampusChange() {
            const campusId = $(this).val();
            const collegeSelect = $('#college_id');
            const schoolSelect = $('#school_id');
            const departmentSelect = $('#department_id');
            const programSelect = $('#program_id');
            
            // Reset dependent dropdowns
            collegeSelect.empty().append('<option value="">All Colleges</option>');
            schoolSelect.empty().append('<option value="">All Schools</option>');
            departmentSelect.empty().append('<option value="">All Departments</option>');
            programSelect.empty().append('<option value="">All Programs</option>');
            
            if (campusId) {
                $.ajax({
                    url: 'Dashboard/get_organization_structure.php',
                    method: 'GET',
                    data: { campus_id: campusId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.data) {
                            response.data.forEach(campus => {
                                if (campus.id == campusId && campus.colleges) {
                                    campus.colleges.forEach(college => {
                                        collegeSelect.append(`<option value="${college.id}">${college.name}</option>`);
                                    });
                                }
                            });
                        } else {
                            console.error('Error loading colleges:', response.error || 'Unknown error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error loading colleges:', {
                            status: status,
                            error: error,
                            response: xhr.responseText
                        });
                    }
                });
            }
            loadTimetable();
        }
        
        function handleCollegeChange() {
            const collegeId = $(this).val();
            const schoolSelect = $('#school_id');
            const departmentSelect = $('#department_id');
            const programSelect = $('#program_id');
            
            // Reset dependent dropdowns
            schoolSelect.empty().append('<option value="">All Schools</option>');
            departmentSelect.empty().append('<option value="">All Departments</option>');
            programSelect.empty().append('<option value="">All Programs</option>');
            
            if (collegeId) {
                $.ajax({
                    url: 'Dashboard/get_organization_structure.php',
                    method: 'GET',
                    data: { college_id: collegeId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.data) {
                            response.data.forEach(campus => {
                                campus.colleges.forEach(college => {
                                    if (college.id == collegeId && college.schools) {
                                        college.schools.forEach(school => {
                                            schoolSelect.append(`<option value="${school.id}">${school.name}</option>`);
                                        });
                                    }
                                });
                            });
                        } else {
                            console.error('Error loading schools:', response.error || 'Unknown error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error loading schools:', {
                            status: status,
                            error: error,
                            response: xhr.responseText
                        });
                    }
                });
            }
            loadTimetable();
        }
        
        function handleSchoolChange() {
            const schoolId = $(this).val();
            const departmentSelect = $('#department_id');
            const programSelect = $('#program_id');
            
            // Reset dependent dropdowns
            departmentSelect.empty().append('<option value="">All Departments</option>');
            programSelect.empty().append('<option value="">All Programs</option>');
            
            if (schoolId) {
                $.ajax({
                    url: 'Dashboard/get_organization_structure.php',
                    method: 'GET',
                    data: { school_id: schoolId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.data) {
                            response.data.forEach(campus => {
                                campus.colleges.forEach(college => {
                                    college.schools.forEach(school => {
                                        if (school.id == schoolId && school.departments) {
                                            school.departments.forEach(department => {
                                                departmentSelect.append(`<option value="${department.id}">${department.name}</option>`);
                                            });
                                        }
                                    });
                                });
                            });
                        } else {
                            console.error('Error loading departments:', response.error || 'Unknown error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error loading departments:', {
                            status: status,
                            error: error,
                            response: xhr.responseText
                        });
                    }
                });
            }
            loadTimetable();
        }
        
        function handleDepartmentChange() {
            const departmentId = $(this).val();
            const programSelect = $('#program_id');
            
            // Reset dependent dropdown
            programSelect.empty().append('<option value="">All Programs</option>');
            
            if (departmentId) {
                $.ajax({
                    url: 'Dashboard/get_organization_structure.php',
                    method: 'GET',
                    data: { department_id: departmentId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.data) {
                            response.data.forEach(campus => {
                                campus.colleges.forEach(college => {
                                    college.schools.forEach(school => {
                                        school.departments.forEach(department => {
                                            if (department.id == departmentId && department.programs) {
                                                department.programs.forEach(program => {
                                                    programSelect.append(`<option value="${program.id}">${program.name}</option>`);
                                                });
                                            }
                                        });
                                    });
                                });
                            });
                        } else {
                            console.error('Error loading programs:', response.error || 'Unknown error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error loading programs:', {
                            status: status,
                            error: error,
                            response: xhr.responseText
                        });
                    }
                });
            }
            loadTimetable();
        }
        
        function handleProgramChange() {
            const programId = $(this).val();
            const intakeSelect = $('#intake_id');
            
            // Reset intake dropdown
            intakeSelect.empty().append('<option value="">All Intakes</option>');
            
            if (programId) {
                $.ajax({
                    url: 'Dashboard/get_organization_structure.php',
                    method: 'GET',
                    data: { program_id: programId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.data) {
                            response.data.forEach(campus => {
                                campus.colleges.forEach(college => {
                                    college.schools.forEach(school => {
                                        school.departments.forEach(department => {
                                            department.programs.forEach(program => {
                                                if (program.id == programId && program.intakes) {
                                                    program.intakes.forEach(intake => {
                                                        intakeSelect.append(`<option value="${intake.id}">${intake.year}/${intake.month}</option>`);
                                                    });
                                                }
                                            });
                                        });
                                    });
                                });
                            });
                        } else {
                            console.error('Error loading intakes:', response.error || 'Unknown error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error loading intakes:', {
                            status: status,
                            error: error,
                            response: xhr.responseText
                        });
                    }
                });
            }
            loadTimetable();
        }
        
        function loadTimetable() {
            showLoading();
            
            $.ajax({
                url: 'Dashboard/get_timetable.php',
                method: 'GET',
                data: {
                    campus_id: $('#campus_id').val(),
                    college_id: $('#college_id').val(),
                    school_id: $('#school_id').val(),
                    department_id: $('#department_id').val(),
                    program_id: $('#program_id').val(),
                    intake_id: $('#intake_id').val(),
                    academic_year_id: $('#academic_year_id').val(),
                    semester: $('#semester').val()
                },
                dataType: 'json',
                success: function(response) {
                    hideLoading();
                    const tbody = $('#timetableTable tbody');
                    tbody.empty();
                    
                    if (response.success) {
                        if (response.data && response.data.length > 0) {
                            // Group sessions by day
                            const sessionsByDay = {};
                            response.data.forEach(session => {
                                const day = session.session.day;
                                if (!sessionsByDay[day]) {
                                    sessionsByDay[day] = [];
                                }
                                sessionsByDay[day].push(session);
                            });

                            // Sort days
                            const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                            
                            // Display sessions by day
                            days.forEach(day => {
                                if (sessionsByDay[day] && sessionsByDay[day].length > 0) {
                                    // Add day header
                                    tbody.append(`
                                        <tr class="day-header-row">
                                            <td colspan="6" class="text-center py-3">
                                                <h4 class="mb-0 text-white">${day}</h4>
                                            </td>
                                        </tr>
                                    `);

                                    // Add sessions for this day
                                    sessionsByDay[day].forEach(session => {
                                        const row = $(`
                                            <tr>
                                                <td class="text-center">
                                                    <span class="badge bg-primary">${session.session.day}</span>
                                                </td>
                                                <td class="text-center">
                                                    <i class="bi bi-clock"></i> ${session.session.start_time} - ${session.session.end_time}
                                                </td>
                                                <td>
                                                    <strong>${session.timetable.module.name}</strong>
                                                    <br>
                                                    <small class="text-muted">${session.timetable.module.code}</small>
                                                </td>
                                                <td>
                                                    <i class="bi bi-person"></i> ${session.timetable.lecturer.name}
                                                </td>
                                                <td>
                                                    <i class="bi bi-building"></i> ${session.timetable.facility.name}
                                                    <br>
                                                    <small class="text-muted">${session.timetable.facility.location}</small>
                                                </td>
                                                <td>
                                                    ${session.timetable.groups.map(group => `
                                                        <span class="badge bg-info mb-1">
                                                            ${group.name}
                                                            <small class="ms-1">(${group.size})</small>
                                                        </span>
                                                    `).join('')}
                                                </td>
                                            </tr>
                                        `);
                                        tbody.append(row);
                                    });
                                }
                            });
                        } else {
                            tbody.append(`
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <div class="alert alert-info mb-0">
                                            <i class="bi bi-info-circle"></i> No timetable data found for the selected filters.
                                        </div>
                                    </td>
                                </tr>
                            `);
                        }
                    } else {
                        tbody.append(`
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <div class="alert alert-danger mb-0">
                                        <i class="bi bi-exclamation-triangle"></i> ${response.error || 'An error occurred while loading the timetable.'}
                                    </div>
                                </td>
                            </tr>
                        `);
                    }
                },
                error: function(xhr, status, error) {
                    hideLoading();
                    const tbody = $('#timetableTable tbody');
                    tbody.html(`
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                <div class="alert alert-danger mb-0">
                                    <i class="bi bi-exclamation-triangle"></i> Failed to load timetable data. Please try again later.
                                </div>
                                <div class="text-muted mt-2">
                                    <small>Error details: ${error}</small>
                                </div>
                            </td>
                        </tr>
                    `);
                    console.error('Error loading timetable:', {
                        status: status,
                        error: error,
                        response: xhr.responseText
                    });
                }
            });
        }
        
        // Handle form submission
        $('#filterForm').on('submit', function(e) {
            e.preventDefault();
            loadTimetable();
        });
    </script>
</body>
</html> 