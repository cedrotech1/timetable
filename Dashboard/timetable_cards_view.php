<?php
session_start();
include('connection.php');

// Get current system settings
$system_query = "SELECT s.*, ay.year_label 
                FROM system s 
                LEFT JOIN academic_year ay ON s.accademic_year_id = ay.id 
                LIMIT 1";
$system_result = mysqli_query($connection, $system_query);
$system_data = mysqli_fetch_assoc($system_result);

// Get all campuses
$campuses = [];
$res = mysqli_query($connection, "SELECT id, name FROM campus ORDER BY name");
while ($row = mysqli_fetch_assoc($res)) $campuses[] = $row;

// Get academic years
$years = [];
$res = mysqli_query($connection, "SELECT id, year_label FROM academic_year ORDER BY year_label DESC");
while ($row = mysqli_fetch_assoc($res)) $years[] = $row;

$semesters = ['1', '2', '3'];

// Set default values
$default_year = $system_data['accademic_year_id'] ?? '';
$default_semester = $system_data['semester'] ?? '1';
?>
<head>
<meta charset="utf-8">
<meta content="width=device-width, initial-scale=1.0" name="viewport">

<title>UR-TIMETABLE</title>
<meta content="" name="description">
<meta content="" name="keywords">

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
.timetable-card {
    border: 1px solid #ddd;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: transform 0.2s;
    background: white;
}

.timetable-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.card-header {
    background-color: #f8f9fa;
    color: #333;
    border-bottom: 1px solid #ddd;
    padding: 15px;
    border-radius: 8px 8px 0 0;
}

.card-header h5 {
    margin: 0;
    font-size: 1.2rem;
    color: #333;
}

.card-header small {
    color: #666;
}

.card-body {
    padding: 20px;
}

.info-row {
    margin-bottom: 12px;
    display: flex;
    align-items: center;
}

.info-label {
    font-weight: 600;
    color: #333;
    min-width: 120px;
}

.sessions-list {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.sessions-list h6 {
    color: #333;
    margin-bottom: 15px;
    font-weight: 600;
}

.session-item {
    background-color: #f8f9fa;
    padding: 12px;
    margin-bottom: 10px;
    border-radius: 6px;
    border-left: 4px solid #ddd;
}

.session-item i {
    color: #666;
    margin-right: 8px;
}

.groups-list {
    margin-top: 15px;
    padding-top: 12px;
    border-top: 1px solid #eee;
}

.groups-list h6 {
    color: #333;
    margin-bottom: 10px;
    font-weight: 600;
    font-size: 0.9em;
}

.group-item {
    background-color: #f8f9fa;
    padding: 12px;
    margin-bottom: 8px;
    border-radius: 4px;
    border-left: 2px solid #ddd;
}

.group-item .btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.group-item .btn-danger {
    opacity: 0.7;
    transition: opacity 0.2s;
}

.group-item .btn-danger:hover {
    opacity: 1;
}

.groups-list .btn-primary {
    background: #012970;
    border-color: #012970;
}

.groups-list .btn-primary:hover {
    background: #011f57;
    border-color: #011f57;
}

.group-item strong {
    color: #333;
    display: block;
    margin-bottom: 4px;
    font-size: 0.95em;
}

.group-item small {
    color: #666;
    display: block;
    line-height: 1.4;
}

.group-hierarchy {
    display: flex;
    flex-direction: column;
    gap: 2px;
    font-size: 0.8em;
}

.hierarchy-item {
    background: transparent;
    padding: 2px 4px;
    color: #666;
    display: flex;
    align-items: center;
    border-left: none;
}

.hierarchy-item i {
    color: #666;
    margin-right: 4px;
    font-size: 0.8em;
    width: 14px;
    text-align: center;
}

.filter-section {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
    padding: 20px;
}

.year-semester-filter {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
}

.year-semester-filter .form-group {
    flex: 1;
}

.year-semester-filter label {
    font-weight: 600;
    color: #4154f1;
    margin-bottom: 8px;
}

.year-semester-filter select {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.filter-title {
    color: #333;
    margin-bottom: 20px;
}

.step-indicator {
    display: flex;
    justify-content: space-between;
    margin-bottom: 30px;
    padding: 0 20px;
    position: relative;
}

.step {
    text-align: center;
    flex: 1;
    position: relative;
    z-index: 1;
}

.step:not(:last-child):after {
    content: '';
    position: absolute;
    top: 20px;
    right: -50%;
    width: 100%;
    height: 2px;
    background-color: #ddd;
    z-index: 1;
}

.step.active:not(:last-child):after {
    background-color: #28a745;
}

.step.completed:not(:last-child):after {
    background-color: #28a745;
}


/* Filter Steps */
.filter-steps {
    position: relative;
    min-height: 120px;
}

.filter-step {
    display: none;
    animation: fadeIn 0.3s ease;
}

.filter-step.active {
    display: block;
}

.filter-group {
    background: #f8f9fa;
    padding: 0.75rem;
    border-radius: 6px;
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
    margin-bottom: 0.5rem;
}

.filter-group:hover {
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
    border-color: #012970;
}

.filter-group .form-label {
    color: #012970;
    font-weight: 500;
    margin-bottom: 0.25rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.9rem;
}

.filter-group .form-label i {
    font-size: 1rem;
}

.step-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: #f8f9fa;
    border: 2px solid #ddd;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 10px;
    position: relative;
    z-index: 2;
    transition: all 0.3s ease;
}

.step.active .step-icon {
    background-color: #28a745;
    border-color: #28a745;
    color: white;
}

.step.completed .step-icon {
    background-color: #28a745;
    border-color: #28a745;
    color: white;
}

.step-label {
    font-size: 0.8em;
    color: #666;
    transition: all 0.3s ease;
}

.step.active .step-label {
    color: #28a745;
    font-weight: bold;
}

.step.completed .step-label {
    color: #28a745;
    font-weight: bold;
}

.filter-step {
    display: none;
    animation: fadeIn 0.3s ease;
}

.filter-step.active {
    display: block;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}


/* Navigation Buttons */
.filter-actions {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
    padding-top: 0.5rem;
    border-top: 1px solid #e9ecef;
    margin-top: 0.5rem;
}

.filter-actions .btn {
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
    min-width: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
}

.filter-actions .btn-primary {
    background: #012970;
    border-color: #012970;
}

.filter-actions .btn-primary:hover {
    background: #011f57;
    border-color: #011f57;
}

.filter-actions .btn-secondary {
    background: #6c757d;
    border-color: #6c757d;
}

.filter-actions .btn-secondary:hover {
    background: #5a6268;
    border-color: #5a6268;
}

.filter-actions .btn-success {
    background: #28a745;
    border-color: #28a745;
}

.filter-actions .btn-success:hover {
    background: #218838;
    border-color: #218838;
}

.filter-actions .btn-danger {
    background: #dc3545;
    border-color: #dc3545;
}

.filter-actions .btn-danger:hover {
    background: #c82333;
    border-color: #bd2130;
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
    z-index: 1000;
}

.active-filters {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 4px;
}

.active-filters h6 {
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
}

.active-filters .filter-tag {
    margin-bottom: 0.5rem;
}

.active-filters .filter-tag strong {
    color: #333;
    font-weight: 600;
}

.active-filters .filter-tag span {
    color: #666;
}

.no-data-message {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 4px;
}

.no-data-message h6 {
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
}

.no-data-message .row > div {
    margin-bottom: 0.5rem;
}

.no-data-message strong {
    color: #333;
    font-weight: 600;
}

.no-timetable-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    padding: 40px 20px;
    text-align: center;
    margin: 20px 0;
}

.no-timetable-icon {
    font-size: 48px;
    color: #dc3545;
    margin-bottom: 20px;
}

.no-timetable-card h4 {
    color: #333;
    margin-bottom: 10px;
    font-weight: 600;
}

.no-timetable-card p {
    margin-bottom: 20px;
    font-size: 0.95rem;
}

.no-timetable-card .btn {
    padding: 8px 20px;
    font-size: 0.9rem;
}
</style>
</head>
<body>

<?php
include("./includes/header.php");
include("./includes/menu.php");
?>

<main id="main" class="main">
<div class="container-fluid py-4">
    <!-- Year and Semester Filter -->
    <div class="filter-section">
        <div class="year-semester-filter">
            <div class="form-group">
                <label for="academic_year"><i class="bi bi-calendar"></i> Academic Year</label>
                <select class="form-select" id="academic_year" name="academic_year">
                    <?php foreach ($years as $year): ?>
                        <option value="<?php echo $year['id']; ?>" <?php echo ($year['id'] == $default_year) ? 'selected' : ''; ?>>
                            <?php echo $year['year_label']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="semester"><i class="bi bi-book"></i> Semester</label>
                <select class="form-select" id="semester" name="semester">
                    <?php foreach ($semesters as $sem): ?>
                        <option value="<?php echo $sem; ?>" <?php echo ($sem == $default_semester) ? 'selected' : ''; ?>>
                            Semester <?php echo $sem; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

   

    <!-- Filters Section -->
    <div class="card mb-4">
        <div class="card-body">
            <form id="filterForm" class="row g-3">
                <div class="col-12">
                    <h5 class="filter-title"><i class="bi bi-funnel"></i> Filter Timetable</h5>
                </div>
                
                <!-- Step Indicator -->
                <div class="col-12">
                    <div class="step-indicator">
                        <div class="step active" data-step="campus">
                            <div class="step-icon"><i class="bi bi-geo-alt"></i></div>
                            <div class="step-label">Campus</div>
                        </div>
                        <div class="step" data-step="college">
                            <div class="step-icon"><i class="bi bi-building"></i></div>
                            <div class="step-label">College</div>
                        </div>
                        <div class="step" data-step="school">
                            <div class="step-icon"><i class="bi bi-bank"></i></div>
                            <div class="step-label">School</div>
                        </div>
                        <div class="step" data-step="department">
                            <div class="step-icon"><i class="bi bi-diagram-3"></i></div>
                            <div class="step-label">Department</div>
                        </div>
                        <div class="step" data-step="program">
                            <div class="step-icon"><i class="bi bi-mortarboard"></i></div>
                            <div class="step-label">Program</div>
                        </div>
                        <div class="step" data-step="intake">
                            <div class="step-icon"><i class="bi bi-calendar"></i></div>
                            <div class="step-label">Intake</div>
                        </div>
                        <div class="step" data-step="group">
                            <div class="step-icon"><i class="bi bi-people"></i></div>
                            <div class="step-label">Group</div>
                        </div>
                    </div>
                </div>

                <!-- Filter Steps -->
                <div class="col-12">
                    <div class="filter-steps">
                        <!-- Campus Step -->
                        <div class="filter-step active" id="campus-step">
                            <div class="filter-group">
                                <label class="form-label"><i class="bi bi-geo-alt"></i> Select Campus</label>
                                <select class="form-select" id="campus_id" name="campus_id">
                                    <option value="">All Campuses</option>
                                </select>
                            </div>
                        </div>

                        <!-- College Step -->
                        <div class="filter-step" id="college-step">
                            <div class="filter-group">
                                <label class="form-label"><i class="bi bi-building"></i> Select College</label>
                                <select class="form-select" id="college_id" name="college_id">
                                    <option value="">All Colleges</option>
                                </select>
                            </div>
                        </div>

                        <!-- School Step -->
                        <div class="filter-step" id="school-step">
                            <div class="filter-group">
                                <label class="form-label"><i class="bi bi-bank"></i> Select School</label>
                                <select class="form-select" id="school_id" name="school_id">
                                    <option value="">All Schools</option>
                                </select>
                            </div>
                        </div>

                        <!-- Department Step -->
                        <div class="filter-step" id="department-step">
                            <div class="filter-group">
                                <label class="form-label"><i class="bi bi-diagram-3"></i> Select Department</label>
                                <select class="form-select" id="department_id" name="department_id">
                                    <option value="">All Departments</option>
                                </select>
                            </div>
                        </div>

                        <!-- Program Step -->
                        <div class="filter-step" id="program-step">
                            <div class="filter-group">
                                <label class="form-label"><i class="bi bi-mortarboard"></i> Select Program</label>
                                <select class="form-select" id="program_id" name="program_id">
                                    <option value="">All Programs</option>
                                </select>
                            </div>
                        </div>

                        <!-- Intake Step -->
                        <div class="filter-step" id="intake-step">
                            <div class="filter-group">
                                <label class="form-label"><i class="bi bi-calendar"></i> Select Intake</label>
                                <select class="form-select" id="intake_id" name="intake_id">
                                    <option value="">All Intakes</option>
                                </select>
                            </div>
                        </div>

                        <!-- Group Step -->
                        <div class="filter-step" id="group-step">
                            <div class="filter-group">
                                <label class="form-label"><i class="bi bi-people"></i> Select Group</label>
                                <select class="form-select" id="group_id" name="group_id">
                                    <option value="">All Groups</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation Buttons -->
                <div class="col-12" style="margin-top:-0.5cm">
                    <div class="filter-actions">
                        <button type="button" class="btn btn-secondary" id="prevBtn" style="display: none;">
                            <i class="bi bi-arrow-left"></i> Previous
                        </button>
                        <button type="button" class="btn btn-primary" id="nextBtn">
                            Next <i class="bi bi-arrow-right"></i>
                        </button>
                        <button type="submit" class="btn btn-success" id="applyBtn" style="display: none;">
                            <i class="bi bi-search"></i> Apply Filters
                        </button>
                        <button type="button" class="btn btn-danger" onclick="resetFilters()">
                            <i class="bi bi-x-circle"></i> Reset
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

     <!-- Active Filters Display -->
    <div class="active-filters mb-4" id="activeFilters" style="display: none;">
        <div class="row bg-white p-3">
            <div class="col-md-6">
                <h6 class="text-muted mb-2">Organization Structure</h6>
                <div id="orgFilters"></div>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted mb-2">Academic Details</h6>
                <div id="academicFilters"></div>
            </div>
        </div>
    </div>

    <!-- Timetable Cards -->
    <div class="row" id="timetableCards">
        <!-- Cards will be dynamically inserted here -->
    </div>
</div>

<div class="loading" id="loadingIndicator" style="display: none;">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
</div>

<!-- Add Group Modal -->
<div class="modal fade" id="addGroupModal" tabindex="-1" aria-labelledby="addGroupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addGroupModalLabel">Add Groups</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addGroupForm" class="row g-1 align-items-end">
                    <input type="hidden" id="timetableId" name="timetable_id">
                    <div class="col-md-12">
                        <label for="campus" class="form-label small">Campus</label>
                        <select class="form-select form-select-sm select2" id="campus" name="campus" required>
                            <option value="">Select Campus</option>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label for="college" class="form-label small">College</label>
                        <select class="form-select form-select-sm select2" id="college" name="college" disabled required>
                            <option value="">Select College</option>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label for="school" class="form-label small">School</label>
                        <select class="form-select form-select-sm select2" id="school" name="school" disabled required>
                            <option value="">Select School</option>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label for="department" class="form-label small">Department</label>
                        <select class="form-select form-select-sm select2" id="department" name="department" disabled required>
                            <option value="">Select Department</option>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label for="program" class="form-label small">Program</label>
                        <select class="form-select form-select-sm select2" id="program" name="program" disabled required>
                            <option value="">Select Program</option>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label for="intake" class="form-label small">Intake</label>
                        <select class="form-select form-select-sm select2" id="intake" name="intake" disabled required>
                            <option value="">Select Intake</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label for="groups" class="form-label small">Groups</label>
                        <select class="form-select form-select-sm select2" id="groups" name="groups[]" multiple disabled required>
                            <option value="">Select Groups</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="submitAddGroup()">Add Selected Groups</button>
            </div>
        </div>
    </div>
</div>

<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
// Define modal-related functions globally
function loadModalCampus() {
    $.ajax({
        url: 'get_organization_structure.php',
        method: 'GET',
        success: function(response) {
            if (response.success) {
                const campusSelect = $('#campus');
                campusSelect.empty().append('<option value="">Select Campus</option>');
                
                response.data.forEach(campus => {
                    campusSelect.append(`<option value="${campus.id}">${campus.name}</option>`);
                });
                
                campusSelect.prop('disabled', false);
            }
        }
    });
}

function loadModalCollege(campusId) {
    $.ajax({
        url: 'get_organization_structure.php',
        method: 'GET',
        data: { campus_id: campusId },
        success: function(response) {
            if (response.success) {
                const collegeSelect = $('#college');
                collegeSelect.empty().append('<option value="">Select College</option>');
                
                response.data.forEach(campus => {
                    if (campus.id === campusId && campus.colleges) {
                        campus.colleges.forEach(college => {
                            collegeSelect.append(`<option value="${college.id}">${college.name}</option>`);
                        });
                    }
                });
                
                collegeSelect.prop('disabled', false);
            }
        }
    });
}

function loadModalSchool(collegeId) {
    $.ajax({
        url: 'get_organization_structure.php',
        method: 'GET',
        data: { college_id: collegeId },
        success: function(response) {
            if (response.success) {
                const schoolSelect = $('#school');
                schoolSelect.empty().append('<option value="">Select School</option>');
                
                response.data.forEach(campus => {
                    campus.colleges.forEach(college => {
                        if (college.id === collegeId && college.schools) {
                            college.schools.forEach(school => {
                                schoolSelect.append(`<option value="${school.id}">${school.name}</option>`);
                            });
                        }
                    });
                });
                
                schoolSelect.prop('disabled', false);
            }
        }
    });
}

function loadModalDepartment(schoolId) {
    $.ajax({
        url: 'get_organization_structure.php',
        method: 'GET',
        data: { school_id: schoolId },
        success: function(response) {
            if (response.success) {
                const departmentSelect = $('#department');
                departmentSelect.empty().append('<option value="">Select Department</option>');
                
                response.data.forEach(campus => {
                    campus.colleges.forEach(college => {
                        college.schools.forEach(school => {
                            if (school.id === schoolId && school.departments) {
                                school.departments.forEach(department => {
                                    departmentSelect.append(`<option value="${department.id}">${department.name}</option>`);
                                });
                            }
                        });
                    });
                });
                
                departmentSelect.prop('disabled', false);
            }
        }
    });
}

function loadModalProgram(departmentId) {
    $.ajax({
        url: 'get_organization_structure.php',
        method: 'GET',
        data: { department_id: departmentId },
        success: function(response) {
            if (response.success) {
                const programSelect = $('#program');
                programSelect.empty().append('<option value="">Select Program</option>');
                
                response.data.forEach(campus => {
                    campus.colleges.forEach(college => {
                        college.schools.forEach(school => {
                            school.departments.forEach(department => {
                                if (department.id === departmentId && department.programs) {
                                    department.programs.forEach(program => {
                                        programSelect.append(`<option value="${program.id}">${program.name}</option>`);
                                    });
                                }
                            });
                        });
                    });
                });
                
                programSelect.prop('disabled', false);
            }
        }
    });
}

function loadModalIntake(programId) {
    $.ajax({
        url: 'get_organization_structure.php',
        method: 'GET',
        data: { program_id: programId },
        success: function(response) {
            if (response.success) {
                const intakeSelect = $('#intake');
                intakeSelect.empty().append('<option value="">Select Intake</option>');
                
                response.data.forEach(campus => {
                    campus.colleges.forEach(college => {
                        college.schools.forEach(school => {
                            school.departments.forEach(department => {
                                department.programs.forEach(program => {
                                    if (program.id === programId && program.intakes) {
                                        program.intakes.forEach(intake => {
                                            intakeSelect.append(`<option value="${intake.id}">${intake.year}/${intake.month}</option>`);
                                        });
                                    }
                                });
                            });
                        });
                    });
                });
                
                intakeSelect.prop('disabled', false);
            }
        }
    });
}

function loadModalGroup(intakeId) {
    $.ajax({
        url: 'get_organization_structure.php',
        method: 'GET',
        data: { intake_id: intakeId },
        success: function(response) {
            if (response.success) {
                const groupSelect = $('#groups');
                groupSelect.empty().append('<option value="">Select Groups</option>');
                
                response.data.forEach(campus => {
                    campus.colleges.forEach(college => {
                        college.schools.forEach(school => {
                            school.departments.forEach(department => {
                                department.programs.forEach(program => {
                                    program.intakes.forEach(intake => {
                                        if (intake.id === parseInt(intakeId) && intake.groups) {
                                            intake.groups.forEach(group => {
                                                groupSelect.append(`<option value="${group.id}">${group.name}</option>`);
                                            });
                                        }
                                    });
                                });
                            });
                        });
                    });
                });
                
                groupSelect.prop('disabled', false);
            }
        }
    });
}

// Define addGroup function
function addGroup(timetableId) {
    $('#timetableId').val(timetableId);
    $('#addGroupModal').modal('show');
    
    // Initialize Select2 for all dropdowns in the modal
    $('#addGroupModal .form-select').select2({
        width: '100%',
        dropdownParent: $('#addGroupModal')
    });

    // Load initial campus data
    loadModalCampus();
}

// Campus change handler
$('#campus').change(function() {
    const campusId = $(this).val();
    if (campusId) {
        $.ajax({
            url: 'get_organization_structure.php',
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    const collegeSelect = $('#college');
                    collegeSelect.empty().append('<option value="">Select College</option>');
                    
                    response.data.forEach(campus => {
                        if (campus.id === campusId && campus.colleges) {
                            campus.colleges.forEach(college => {
                                collegeSelect.append(`<option value="${college.id}">${college.name}</option>`);
                            });
                        }
                    });
                    
                    collegeSelect.prop('disabled', false);
                }
            }
        });
    }
    $('#school, #department, #program, #intake, #group').prop('disabled', true).empty();
});

// College change handler
$('#college').change(function() {
    const collegeId = $(this).val();
    if (collegeId) {
        $.ajax({
            url: 'get_organization_structure.php',
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    const schoolSelect = $('#school');
                    schoolSelect.empty().append('<option value="">Select School</option>');
                    
                    response.data.forEach(campus => {
                        campus.colleges.forEach(college => {
                            if (college.id === parseInt(collegeId) && college.schools) {
                                college.schools.forEach(school => {
                                    schoolSelect.append(`<option value="${school.id}">${school.name}</option>`);
                                });
                            }
                        });
                    });
                    
                    schoolSelect.prop('disabled', false);
                }
            }
        });
    }
    $('#department, #program, #intake, #group').prop('disabled', true).empty();
});

// School change handler
$('#school').change(function() {
    const schoolId = $(this).val();
    if (schoolId) {
        $.ajax({
            url: 'get_organization_structure.php',
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    const departmentSelect = $('#department');
                    departmentSelect.empty().append('<option value="">Select Department</option>');
                    
                    response.data.forEach(campus => {
                        campus.colleges.forEach(college => {
                            college.schools.forEach(school => {
                                if (school.id === parseInt(schoolId) && school.departments) {
                                    school.departments.forEach(department => {
                                        departmentSelect.append(`<option value="${department.id}">${department.name}</option>`);
                                    });
                                }
                            });
                        });
                    });
                    
                    departmentSelect.prop('disabled', false);
                }
            }
        });
    }
    $('#program, #intake, #group').prop('disabled', true).empty();
});

// Department change handler
$('#department').change(function() {
    const departmentId = $(this).val();
    if (departmentId) {
        $.ajax({
            url: 'get_organization_structure.php',
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    const programSelect = $('#program');
                    programSelect.empty().append('<option value="">Select Program</option>');
                    
                    response.data.forEach(campus => {
                        campus.colleges.forEach(college => {
                            college.schools.forEach(school => {
                                school.departments.forEach(department => {
                                    if (department.id === parseInt(departmentId) && department.programs) {
                                        department.programs.forEach(program => {
                                            programSelect.append(`<option value="${program.id}">${program.name}</option>`);
                                        });
                                    }
                                });
                            });
                        });
                    });
                    
                    programSelect.prop('disabled', false);
                }
            }
        });
    }
    $('#intake, #group').prop('disabled', true).empty();
});

// Program change handler
$('#program').change(function() {
    const programId = $(this).val();
    if (programId) {
        $.ajax({
            url: 'get_organization_structure.php',
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    const intakeSelect = $('#intake');
                    intakeSelect.empty().append('<option value="">Select Intake</option>');
                    
                    response.data.forEach(campus => {
                        campus.colleges.forEach(college => {
                            college.schools.forEach(school => {
                                school.departments.forEach(department => {
                                    department.programs.forEach(program => {
                                        if (program.id === parseInt(programId) && program.intakes) {
                                            program.intakes.forEach(intake => {
                                                intakeSelect.append(`<option value="${intake.id}">${intake.year}/${intake.month}</option>`);
                                            });
                                        }
                                    });
                                });
                            });
                        });
                    });
                    
                    intakeSelect.prop('disabled', false);
                }
            }
        });
    }
    $('#group').prop('disabled', true).empty();
});

// Intake change handler
$('#intake').change(function() {
    const intakeId = $(this).val();
    if (intakeId) {
        loadModalGroup(intakeId);
    } else {
        $('#groups').prop('disabled', true).empty().append('<option value="">Select Groups</option>');
    }
});

function submitAddGroup() {
    const timetableId = document.getElementById('timetableId').value;
    const groupSelect = document.getElementById('groups');
    const selectedGroups = Array.from(groupSelect.selectedOptions).map(option => option.value);
    
    if (selectedGroups.length === 0) {
        alert('Please select at least one group');
        return;
    }

    // Show loading state
    const submitButton = document.querySelector('#addGroupModal .btn-primary');
    const originalText = submitButton.innerHTML;
    submitButton.disabled = true;
    submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Validating...';

    // First validate the groups
    $.ajax({
        url: 'validate_groups.php',
        type: 'POST',
        dataType: 'json',
        data: {
            timetable_id: timetableId,
            group_ids: selectedGroups
        },
        success: function(response) {
            if (response.success) {
                // If validation passes, proceed with adding groups
                const promises = selectedGroups.map(groupId => {
                    return $.ajax({
                        url: 'api_add_group.php',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            timetable_id: timetableId,
                            group_id: groupId
                        }
                    });
                });

                Promise.all(promises)
                    .then(responses => {
                        const allSuccessful = responses.every(response => response.success);
                        if (allSuccessful) {
                            alert('Groups added successfully');
                            $('#addGroupModal').modal('hide');
                            location.reload();
                        } else {
                            const errors = responses
                                .filter(response => !response.success)
                                .map(response => response.message)
                                .join('\n');
                            alert('Error adding some groups:\n' + errors);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error adding groups. Please try again.');
                    })
                    .finally(() => {
                        // Reset button state
                        submitButton.disabled = false;
                        submitButton.innerHTML = originalText;
                    });
            } else {
                // Show validation errors
                let errorMessage = response.message;
                if (response.conflicts) {
                    errorMessage += '\n\nConflicts found:\n';
                    response.conflicts.forEach(conflict => {
                        errorMessage += `- ${conflict.module} on ${conflict.day} at ${conflict.time}\n`;
                    });
                }
                alert(errorMessage);
                submitButton.disabled = false;
                submitButton.innerHTML = originalText;
            }
        },
        error: function(xhr, status, error) {
            console.error('Validation error:', error);
            alert('Error validating groups. Please try again.');
            submitButton.disabled = false;
            submitButton.innerHTML = originalText;
        }
    });
}

// Add state management for the modal
let modalState = {
    selectedGroups: new Set(),
    validationInProgress: false
};

// Update the groups change handler
$('#groups').on('change', function() {
    const selectedOptions = Array.from(this.selectedOptions);
    const selectedValues = selectedOptions.map(option => option.value);
    
    // Update state
    modalState.selectedGroups = new Set(selectedValues);
    
    // Update UI
    const submitButton = document.querySelector('#addGroupModal .btn-primary');
    submitButton.disabled = selectedValues.length === 0;
});

// Reset modal state when it's closed
$('#addGroupModal').on('hidden.bs.modal', function() {
    modalState.selectedGroups.clear();
    modalState.validationInProgress = false;
    
    // Reset form
    $('#addGroupForm')[0].reset();
    $('#groups').empty().append('<option value="">Select Groups</option>').prop('disabled', true);
    
    // Reset button state
    const submitButton = document.querySelector('#addGroupModal .btn-primary');
    submitButton.disabled = true;
    submitButton.innerHTML = 'Add Selected Groups';
});

// Define updateActiveFilters function globally
function updateActiveFilters() {
    const orgFilters = $('#orgFilters');
    const academicFilters = $('#academicFilters');
    orgFilters.empty();
    academicFilters.empty();
    
    let hasActiveFilters = false;

    // Organization Structure Filters
    if ($('#campus_id').val()) {
        hasActiveFilters = true;
        orgFilters.append(`
            <div class="filter-tag">
                <strong>Campus:</strong> <span>${$('#campus_id option:selected').text()}</span>
            </div>
        `);
    }
    if ($('#college_id').val()) {
        hasActiveFilters = true;
        orgFilters.append(`
            <div class="filter-tag">
                <strong>College:</strong> <span>${$('#college_id option:selected').text()}</span>
            </div>
        `);
    }
    if ($('#school_id').val()) {
        hasActiveFilters = true;
        orgFilters.append(`
            <div class="filter-tag">
                <strong>School:</strong> <span>${$('#school_id option:selected').text()}</span>
            </div>
        `);
    }
    if ($('#department_id').val()) {
        hasActiveFilters = true;
        orgFilters.append(`
            <div class="filter-tag">
                <strong>Department:</strong> <span>${$('#department_id option:selected').text()}</span>
            </div>
        `);
    }
    if ($('#program_id').val()) {
        hasActiveFilters = true;
        orgFilters.append(`
            <div class="filter-tag">
                <strong>Program:</strong> <span>${$('#program_id option:selected').text()}</span>
            </div>
        `);
    }
    if ($('#intake_id').val()) {
        hasActiveFilters = true;
        orgFilters.append(`
            <div class="filter-tag">
                <strong>Intake:</strong> <span>${$('#intake_id option:selected').text()}</span>
            </div>
        `);
    }
    if ($('#group_id').val()) {
        hasActiveFilters = true;
        orgFilters.append(`
            <div class="filter-tag">
                <strong>Group:</strong> <span>${$('#group_id option:selected').text()}</span>
            </div>
        `);
    }

    // Academic Filters
    if ($('#academic_year').val()) {
        hasActiveFilters = true;
        academicFilters.append(`
            <div class="filter-tag">
                <strong>Academic Year:</strong> <span>${$('#academic_year option:selected').text()}</span>
            </div>
        `);
    }
    if ($('#semester').val()) {
        hasActiveFilters = true;
        academicFilters.append(`
            <div class="filter-tag">
                <strong>Semester:</strong> <span>${$('#semester option:selected').text()}</span>
            </div>
        `);
    }

    $('#activeFilters').toggle(hasActiveFilters);
}

// Define displayTimetables function globally
function displayTimetables(timetables) {
    const container = $('#timetableCards');
    container.empty();

    if (timetables.length === 0) {
        container.html(`
            <div class="col-12">
                <div class="no-timetable-card">
                    <div class="no-timetable-icon">
                        <i class="bi bi-calendar-x"></i>
                    </div>
                    <h4>No Timetable Found</h4>
                    <p class="text-muted">No timetables match the selected filters</p>
                    <button type="button" class="btn btn-outline-secondary" onclick="resetFilters()">
                        <i class="bi bi-arrow-counterclockwise"></i> Reset Filters
                    </button>
                </div>
            </div>
        `);
        return;
    }

    timetables.forEach(timetable => {
        const card = `
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header" style="background-color: #05204a; color: white; border-bottom-0 pt-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="card-title text-white mb-0">${timetable.module.name}</h5>
                            <span class="badge" style="background-color: white; color: #05204a">${timetable.module.credits} Credits</span>
                        </div>
                        <p class="text-white-50 mb-0">${timetable.module.code}</p>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-person-circle" style="color: #05204a; font-size: 1.5rem;"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-0" style="color: #05204a; font-size: 0.875rem;">Lecturer</h6>
                                    <p class="mb-0">${timetable.lecturer.name}</p>
                                </div>
                            </div>
                            
                            <div class="d-flex align-items-center mb-3">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-building" style="color: #05204a; font-size: 1.5rem;"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-0" style="color: #05204a; font-size: 0.875rem;">Facility</h6>
                                    <p class="mb-0">${timetable.facility.name} (${timetable.facility.location})</p>
                                </div>
                            </div>
                        </div>

                      

                        <div class="mb-4">
                            <h6 class="mb-3" style="color: #05204a;">
                                <i class="bi bi-calendar-event me-2"></i>Sessions
                            </h6>
                            ${timetable.sessions.map(session => `
                                <div class="d-flex align-items-center mb-2 p-2" style="background-color: rgba(5, 32, 74, 0.1); border-radius: 0.375rem;">
                                    <i class="bi bi-calendar-check me-2" style="color: #05204a;"></i>
                                    <div>
                                        <span class="d-block">${session.day}</span>
                                        <small class="text-muted">${session.start_time} - ${session.end_time}</small>
                                    </div>
                                </div>
                            `).join('')}
                        </div>

                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0" style="color: #05204a;">
                                    <i class="bi bi-people me-2"></i>Groups
                                </h6>
                                <button type="button" class="btn btn-sm" style="background-color: #05204a; color: white;" onclick="addGroup(${timetable.id})">
                                    <i class="bi bi-plus-circle me-1"></i>Add Group
                                </button>
                            </div>
                            ${timetable.groups.map(group => `
                                <div class="card mb-2" style="background-color: rgba(5, 32, 74, 0.1);">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-2" style="color: #05204a;">${group.name}</h6>
                                                <div class="small" style="color: #666; font-size: 0.8rem;">
                                                    <div class="mb-1">
                                                        <i class="bi bi-geo-alt me-1" style="color: #05204a;"></i><strong style="font-size: 0.8rem;">Campus:</strong> <span style="font-size: 0.8rem;">${group.campus.name}</span>
                                                    </div>
                                                    <div class="mb-1">
                                                        <i class="bi bi-building me-1" style="color: #05204a;"></i><strong style="font-size: 0.8rem;">College:</strong> <span style="font-size: 0.8rem;">${group.college.name}</span>
                                                    </div>
                                                    <div class="mb-1">
                                                        <i class="bi bi-bank me-1" style="color: #05204a;"></i><strong style="font-size: 0.8rem;">School:</strong> <span style="font-size: 0.8rem;">${group.school.name}</span>
                                                    </div>
                                                    <div class="mb-1">
                                                        <i class="bi bi-diagram-3 me-1" style="color: #05204a;"></i><strong style="font-size: 0.8rem;">Department:</strong> <span style="font-size: 0.8rem;">${group.department.name}</span>
                                                    </div>
                                                    <div class="mb-1">
                                                        <i class="bi bi-mortarboard me-1" style="color: #05204a;"></i><strong style="font-size: 0.8rem;">Program:</strong> <span style="font-size: 0.8rem;">${group.program.name}</span>
                                                    </div>
                                                    <div>
                                                        <i class="bi bi-calendar me-1" style="color: #05204a;"></i><strong style="font-size: 0.8rem;">Intake:</strong> <span style="font-size: 0.8rem;">${group.intake.year}/${group.intake.month}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-danger" onclick="removeGroup(${timetable.id}, ${group.id})">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
            </div>
        `;
        container.append(card);
    });
}

// Define loadTimetables function globally
function loadTimetables() {
    console.log('Loading timetables...');
    $('#loadingIndicator').show();
    
    const filters = {
        academic_year: $('#academic_year').val(),
        semester: $('#semester').val(),
        campus_id: $('#campus_id').val(),
        college_id: $('#college_id').val(),
        school_id: $('#school_id').val(),
        department_id: $('#department_id').val(),
        program_id: $('#program_id').val(),
        intake_id: $('#intake_id').val(),
        group_id: $('#group_id').val()
    };
    
    console.log('Filters:', filters);

    $.ajax({
        url: 'api_get_timetables.php',
        method: 'GET',
        data: filters,
        success: function(response) {
            console.log('Timetables response:', response);
            
            if (response.success) {
                displayTimetables(response.data);
                updateActiveFilters();
            } else {
                console.error('Error in response:', response);
                alert('Error loading timetables: ' + (response.message || 'Unknown error'));
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error:', {
                status: status,
                error: error,
                response: xhr.responseText
            });
            alert('Error loading timetables: ' + error);
        },
        complete: function() {
            $('#loadingIndicator').hide();
        }
    });
}

// Define removeGroup function globally
function removeGroup(timetableId, groupId) {
    console.log('Attempting to remove group:', { timetableId, groupId });
    
    if (!timetableId || !groupId) {
        console.error('Invalid parameters:', { timetableId, groupId });
        alert('Invalid parameters for group removal');
        return;
    }

    if (confirm('Are you sure you want to remove this group from the timetable?')) {
        console.log('Sending remove group request...');
        
        $.ajax({
            url: 'api_remove_group.php',
            method: 'POST',
            data: {
                timetable_id: timetableId,
                group_id: groupId
            },
            success: function(response) {
                console.log('Remove group response:', response);
                
                if (response.success) {
                    console.log('Group removed successfully');
                    // Reload timetables to show updated data
                    loadTimetables();
                } else {
                    console.error('Error in response:', response);
                    alert('Error removing group: ' + (response.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
                alert('Error removing group: ' + error);
            }
        });
    }
}

$(document).ready(function() {
    let currentStep = 0;
    const steps = ['campus', 'college', 'school', 'department', 'program', 'intake', 'group'];
    let organizationStructure = null;
    
    // Initialize Select2 for all select elements
    $('.form-select').select2({
        width: '100%'
    });

    // Load organization structure
    function loadOrganizationStructure() {
        $.ajax({
            url: 'get_organization_structure.php',
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    organizationStructure = response.data;
                    populateCampusDropdown();
                } else {
                    alert('Error loading organization structure: ' + response.message);
                }
            },
            error: function() {
                alert('Error loading organization structure');
            }
        });
    }

    // Populate campus dropdown
    function populateCampusDropdown() {
        const select = $('#campus_id');
        select.empty().append('<option value="">All Campuses</option>');
        organizationStructure.forEach(campus => {
            select.append(`<option value="${campus.id}">${campus.name}</option>`);
        });
    }

    // Load initial data
    loadOrganizationStructure();
    loadTimetables();

    // Handle filter form submission
    $('#filterForm').on('submit', function(e) {
        e.preventDefault();
        loadTimetables();
    });

    // Handle next button click
    $('#nextBtn').click(function() {
        if (currentStep < steps.length - 1) {
            currentStep++;
            updateSteps();
        }
    });

    // Handle previous button click
    $('#prevBtn').click(function() {
        if (currentStep > 0) {
            currentStep--;
            updateSteps();
        }
    });

    // Update steps visibility
    function updateSteps() {
        $('.filter-step').removeClass('active');
        $(`#${steps[currentStep]}-step`).addClass('active');
        
        $('.step').removeClass('active completed');
        
        // Mark previous steps as completed
        for (let i = 0; i < currentStep; i++) {
            $(`.step[data-step="${steps[i]}"]`).addClass('completed');
        }
        
        // Mark current step as active
        $(`.step[data-step="${steps[currentStep]}"]`).addClass('active');
        
        $('#prevBtn').toggle(currentStep > 0);
        $('#nextBtn').toggle(currentStep < steps.length - 1);
        $('#applyBtn').toggle(currentStep === steps.length - 1);
    }

    // Load dependent data when parent selection changes
    $('#campus_id').change(function() {
        const campusId = $(this).val();
        if (campusId) {
            const campus = organizationStructure.find(c => c.id === campusId);
            const select = $('#college_id');
            select.empty().append('<option value="">All Colleges</option>');
            campus.colleges.forEach(college => {
                select.append(`<option value="${college.id}">${college.name}</option>`);
            });
        } else {
            $('#college_id').empty().append('<option value="">All Colleges</option>');
        }
        // Clear dependent dropdowns
        $('#school_id, #department_id, #program_id, #intake_id, #group_id').empty()
            .append('<option value="">All ' + $(this).attr('id').replace('_id', 's') + '</option>');
        loadTimetables();
    });

    $('#college_id').change(function() {
        const collegeId = $(this).val();
        if (collegeId) {
            const campus = organizationStructure.find(c => c.id === $('#campus_id').val());
            const college = campus.colleges.find(c => c.id.toString() === collegeId);
            const select = $('#school_id');
            select.empty().append('<option value="">All Schools</option>');
            college.schools.forEach(school => {
                select.append(`<option value="${school.id}">${school.name}</option>`);
            });
        } else {
            $('#school_id').empty().append('<option value="">All Schools</option>');
        }
        // Clear dependent dropdowns
        $('#department_id, #program_id, #intake_id, #group_id').empty()
            .append('<option value="">All ' + $(this).attr('id').replace('_id', 's') + '</option>');
        loadTimetables();
    });

    $('#school_id').change(function() {
        const schoolId = $(this).val();
        if (schoolId) {
            const campus = organizationStructure.find(c => c.id === $('#campus_id').val());
            const college = campus.colleges.find(c => c.id.toString() === $('#college_id').val());
            const school = college.schools.find(s => s.id.toString() === schoolId);
            const select = $('#department_id');
            select.empty().append('<option value="">All Departments</option>');
            school.departments.forEach(department => {
                select.append(`<option value="${department.id}">${department.name}</option>`);
            });
        } else {
            $('#department_id').empty().append('<option value="">All Departments</option>');
        }
        // Clear dependent dropdowns
        $('#program_id, #intake_id, #group_id').empty()
            .append('<option value="">All ' + $(this).attr('id').replace('_id', 's') + '</option>');
        loadTimetables();
    });

    $('#department_id').change(function() {
        const departmentId = $(this).val();
        if (departmentId) {
            const campus = organizationStructure.find(c => c.id === $('#campus_id').val());
            const college = campus.colleges.find(c => c.id.toString() === $('#college_id').val());
            const school = college.schools.find(s => s.id.toString() === $('#school_id').val());
            const department = school.departments.find(d => d.id.toString() === departmentId);
            const select = $('#program_id');
            select.empty().append('<option value="">All Programs</option>');
            department.programs.forEach(program => {
                select.append(`<option value="${program.id}">${program.name}</option>`);
            });
        } else {
            $('#program_id').empty().append('<option value="">All Programs</option>');
        }
        // Clear dependent dropdowns
        $('#intake_id, #group_id').empty()
            .append('<option value="">All ' + $(this).attr('id').replace('_id', 's') + '</option>');
        loadTimetables();
    });

    $('#program_id').change(function() {
        const programId = $(this).val();
        if (programId) {
            const campus = organizationStructure.find(c => c.id === $('#campus_id').val());
            const college = campus.colleges.find(c => c.id.toString() === $('#college_id').val());
            const school = college.schools.find(s => s.id.toString() === $('#school_id').val());
            const department = school.departments.find(d => d.id.toString() === $('#department_id').val());
            const program = department.programs.find(p => p.id.toString() === programId);
            const select = $('#intake_id');
            select.empty().append('<option value="">All Intakes</option>');
            program.intakes.forEach(intake => {
                select.append(`<option value="${intake.id}">${intake.year}/${intake.month}</option>`);
            });
        } else {
            $('#intake_id').empty().append('<option value="">All Intakes</option>');
        }
        // Clear dependent dropdowns
        $('#group_id').empty().append('<option value="">All Groups</option>');
        loadTimetables();
    });

    $('#intake_id').change(function() {
        const intakeId = $(this).val();
        if (intakeId) {
            loadModalGroup(intakeId);
        } else {
            $('#groups').prop('disabled', true).empty().append('<option value="">Select Groups</option>');
        }
    });

    $('#group_id').change(function() {
        loadTimetables();
    });

    // Add change handlers for year and semester
    $('#academic_year, #semester').change(function() {
        loadTimetables();
    });

    // Handle filter tag removal
    $(document).on('click', '.btn-close', function() {
        const filterKey = $(this).data('filter');
        if (filterKey === 'academic_year' || filterKey === 'semester') {
            $(`#${filterKey}`).val('').trigger('change');
        } else {
            $(`#${filterKey}_id`).val('').trigger('change');
        }
        loadTimetables();
    });

    // Reset all filters
    window.resetFilters = function() {
        $('#filterForm select').val('').trigger('change');
        currentStep = 0;
        updateSteps();
        loadTimetables();
    };
});
</script>
</body>
</html> 