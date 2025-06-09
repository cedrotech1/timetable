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
<link
    href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i"
    rel="stylesheet">

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
</head>
<body>





<main id="main" class="main">
<div class="container-fluid py-4">
    <h2 class="mb-4">Time Table</h2>
    
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

    <!-- Timetable Grid -->
    <div class="timetable-container">
        <div class="table-responsive">
            <table class="table table-striped" id="timetableTable">
                <thead>
                    <tr>
                        <th>Day</th>
                        <th>Time</th>
                        <th>Module</th>
                        <th>Lecturer</th>
                        <th>Campus</th>
                        <th>College</th>
                        <th>School</th>
                        <th>Department</th>
                        <th>Program</th>
                        <th>Group</th>
                        <th>Intake</th>
                        <th>Facility</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="loading" id="loadingIndicator" style="display: none;">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
</div>

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
            const container = $('.timetable-container');
            container.empty();

            if (response.success) {
                if (response.data && response.data.length > 0) {
                    displayTimetable(response.data);
                } else {
                    // Show no data message with selected filters
                    const campus = $('#campus_id option:selected').text();
                    const college = $('#college_id option:selected').text();
                    const school = $('#school_id option:selected').text();
                    const department = $('#department_id option:selected').text();
                    const program = $('#program_id option:selected').text();
                    const intake = $('#intake_id option:selected').text();
                    const academicYear = $('#academic_year_id option:selected').text();
                    const semester = $('#semester option:selected').text();

                    container.html(`
                        <div class="timetable-card">
                            <div class="card-header">
                                <h5 class="mb-0">No Timetable Found</h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> No timetable data found for the selected filters:
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-muted mb-2">Organization Structure</h6>
                                        ${campus !== 'All Campuses' ? `<div><strong>Campus:</strong> ${campus}</div>` : ''}
                                        ${college !== 'All Colleges' ? `<div><strong>College:</strong> ${college}</div>` : ''}
                                        ${school !== 'All Schools' ? `<div><strong>School:</strong> ${school}</div>` : ''}
                                        ${department !== 'All Departments' ? `<div><strong>Department:</strong> ${department}</div>` : ''}
                                        ${program !== 'All Programs' ? `<div><strong>Program:</strong> ${program}</div>` : ''}
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-muted mb-2">Academic Details</h6>
                                        ${intake !== 'All Intakes' ? `<div><strong>Intake:</strong> ${intake}</div>` : ''}
                                        ${academicYear !== 'All Years' ? `<div><strong>Academic Year:</strong> ${academicYear}</div>` : ''}
                                        ${semester !== 'All Semesters' ? `<div><strong>Semester:</strong> ${semester}</div>` : ''}
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <button type="button" class="btn btn-primary" onclick="resetFilters()">
                                        <i class="bi bi-arrow-counterclockwise"></i> Reset Filters
                                    </button>
                                </div>
                            </div>
                        </div>
                    `);
                }
            } else {
                container.html(`
                    <div class="timetable-card">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0">Error</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle"></i> ${response.error || 'An error occurred while loading the timetable.'}
                            </div>
                        </div>
                    </div>
                `);
            }
        },
        error: function(xhr, status, error) {
            hideLoading();
            const container = $('.timetable-container');
            container.html(`
                <div class="timetable-card">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">Error</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i> Failed to load timetable data. Please try again later.
                        </div>
                        <div class="text-muted">
                            <small>Error details: ${error}</small>
                        </div>
                    </div>
                </div>
            `);
            console.error('Error loading timetable:', {
                status: status,
                error: error,
                response: xhr.responseText
            });
        }
    });
}

function displayTimetable(data) {
    const container = $('.timetable-container');
    container.empty();
    
    // Get selected filter values
    const campus = $('#campus_id option:selected').text();
    const college = $('#college_id option:selected').text();
    const school = $('#school_id option:selected').text();
    const department = $('#department_id option:selected').text();
    const program = $('#program_id option:selected').text();
    const intake = $('#intake_id option:selected').text();
    const academicYear = $('#academic_year_id option:selected').text();
    const semester = $('#semester option:selected').text();

    // Add title card with filters
    container.append(`
        <div class="row mb-4">
            <div class="col-12">
                <div class="timetable-card">
                    <div class="card-header">
                        <h5 class="mb-0">Timetable</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-muted mb-2">Organization Structure</h6>
                                ${campus !== 'All Campuses' ? `<div><strong>Campus:</strong> ${campus}</div>` : ''}
                                ${college !== 'All Colleges' ? `<div><strong>College:</strong> ${college}</div>` : ''}
                                ${school !== 'All Schools' ? `<div><strong>School:</strong> ${school}</div>` : ''}
                                ${department !== 'All Departments' ? `<div><strong>Department:</strong> ${department}</div>` : ''}
                                ${program !== 'All Programs' ? `<div><strong>Program:</strong> ${program}</div>` : ''}
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted mb-2">Academic Details</h6>
                                ${intake !== 'All Intakes' ? `<div><strong>Intake:</strong> ${intake}</div>` : ''}
                                ${academicYear !== 'All Years' ? `<div><strong>Academic Year:</strong> ${academicYear}</div>` : ''}
                                ${semester !== 'All Semesters' ? `<div><strong>Semester:</strong> ${semester}</div>` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `);
    
    // Group sessions by day
    const sessionsByDay = {};
    data.forEach(session => {
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
            container.append(`
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="day-header">
                            <h3 class="mb-0">${day}</h3>
                        </div>
                    </div>
                </div>
            `);

            // Create a row for the day's sessions
            const dayRow = $('<div class="row mb-4"></div>');
            
            // Create cards for each session
            sessionsByDay[day].forEach(session => {
                const card = $(`
                    <div class="col-md-6 mb-4">
                        <div class="timetable-card h-100">
                            <div class="card-header">
                                <h5 class="mb-0">${session.timetable.module.name}</h5>
                                <span class="badge bg-light text-dark">
                                    ${session.session.start_time} - ${session.session.end_time}
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="module-info">
                                    <div class="module-code">${session.timetable.module.code}</div>
                                </div>
                                
                                <div class="time-info">
                                    <i class="bi bi-clock"></i> ${session.session.start_time} - ${session.session.end_time}
                                </div>
                                
                                <div class="facility-info">
                                    <i class="bi bi-building"></i> ${session.timetable.facility.name}
                                    <small class="d-block text-muted">${session.timetable.facility.location}</small>
                                </div>
                                
                                <div class="lecturer-info">
                                    <i class="bi bi-person"></i> ${session.timetable.lecturer.name}
                                </div>
                                
                                <div class="group-info">
                                    <h6>Groups:</h6>
                                    <div class="row">
                                        ${session.timetable.groups.map(group => `
                                            <div class="col-md-6 mb-2">
                                                <div class="group-card">
                                                    <div class="group-header">
                                                        <strong>${group.name}</strong>
                                                        <small class="text-muted">Size: ${group.size}</small>
                                                    </div>
                                                    <div class="group-details">
                                                        <div><i class="bi bi-geo-alt"></i> Campus: ${group.campus.name}</div>
                                                        <div><i class="bi bi-building"></i> College: ${group.college.name}</div>
                                                        <div><i class="bi bi-bank"></i> School: ${group.school.name}</div>
                                                        <div><i class="bi bi-diagram-3"></i> Department: ${group.department.name}</div>
                                                        <div><i class="bi bi-mortarboard"></i> Program: ${group.program.name} (${group.program.code})</div>
                                                        <div><i class="bi bi-calendar"></i> Intake: ${group.intake.year}/${group.intake.month}</div>
                                                    </div>
                                                </div>
                                            </div>
                                        `).join('')}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `);
                dayRow.append(card);
            });
            
            container.append(dayRow);
        }
    });
}

// Handle form submission
$('#filterForm').on('submit', function(e) {
    e.preventDefault();
    loadTimetable();
});
</script>


<a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i
    class="bi bi-arrow-up-short"></i></a>

<!-- Vendor JS Files -->
<script src="assets/vendor/apexcharts/apexcharts.min.js"></script>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/vendor/chart.js/chart.umd.js"></script>
<script src="assets/vendor/echarts/echarts.min.js"></script>
<script src="assets/vendor/quill/quill.min.js"></script>
<script src="assets/vendor/simple-datatables/simple-datatables.js"></script>
<script src="assets/vendor/tinymce/tinymce.min.js"></script>
<script src="assets/vendor/php-email-form/validate.js"></script>

<!-- Template Main JS File -->
<script src="assets/js/main.js"></script>

<style>
.timetable-container {
    padding: 20px;
}

.timetable-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease;
    height: 100%;
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
    height: 100%;
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
    grid-template-columns: 1fr;
    gap: 8px;
}

.group-card .group-details div {
    font-size: 0.85em;
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
}

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

.filters-section .select2-container--default .select2-selection--single {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    height: 42px;
    background-color: #f8f9fa;
}

.filters-section .select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 42px;
    padding-left: 15px;
    color: #495057;
}

.filters-section .select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 40px;
}

.filters-section .select2-container--default .select2-results__option--highlighted[aria-selected] {
    background-color: #012970;
}

.filters-section .select2-dropdown {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
}

.filters-section .row {
    margin-bottom: 15px;
}

.filters-section .col-md-2 {
    margin-bottom: 15px;
}

.filters-section .btn-group {
    margin-top: 10px;
}

.filters-section .btn-group .btn {
    margin-right: 10px;
}

/* Responsive adjustments */
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
}

.alert {
    border-radius: 8px;
    padding: 15px 20px;
    margin-bottom: 20px;
}

.alert-info {
    background-color: #e8f4ff;
    border-color: #b8daff;
    color: #004085;
}

.alert-danger {
    background-color: #f8d7da;
    border-color: #f5c6cb;
    color: #721c24;
}

.alert i {
    margin-right: 8px;
}

.text-muted small {
    font-size: 0.85em;
}
</style>
</body>
</html> 