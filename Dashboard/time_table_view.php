<?php

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

<?php
include("./includes/header.php");
include("./includes/menu.php");
?>



<main id="main" class="main">
<div class="container-fluid py-4">
    <h2 class="mb-4">Time Table</h2>
    
    <!-- Filters Section -->
    <div class="card mb-4">
        <div class="card-body">
            <form id="filterForm" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Campus</label>
                    <select class="form-select" id="campus_id" name="campus_id">
                        <option value="">All Campuses</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">College</label>
                    <select class="form-select" id="college_id" name="college_id">
                        <option value="">All Colleges</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">School</label>
                    <select class="form-select" id="school_id" name="school_id">
                        <option value="">All Schools</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Department</label>
                    <select class="form-select" id="department_id" name="department_id">
                        <option value="">All Departments</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Program</label>
                    <select class="form-select" id="program_id" name="program_id">
                        <option value="">All Programs</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Intake</label>
                    <select class="form-select" id="intake_id" name="intake_id">
                        <option value="">All Intakes</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Academic Year</label>
                    <select class="form-select" id="academic_year_id" name="academic_year_id">
                        <option value="">All Years</option>
                        <?php foreach ($years as $year): ?>
                            <option value="<?php echo $year['id']; ?>"><?php echo $year['year_label']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Semester</label>
                    <select class="form-select" id="semester" name="semester">
    <option value="">All Semesters</option>
    <?php foreach ($semesters as $sem): ?>
        <option value="<?php echo $sem; ?>">Semester <?php echo $sem; ?></option>
    <?php endforeach; ?>
</select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <button type="button" class="btn btn-secondary" onclick="resetFilters()">Reset</button>
                </div>
            </form>
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
        url: 'timetable_get_campus.php',
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
                console.error('Error loading campuses:', response.error);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading campuses:', error);
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
            url: 'timetable_get_colleges.php',
            method: 'GET',
            data: { campus_id: campusId },
            dataType: 'json',
            success: function(response) {
                console.log('Colleges response:', response); // Debug log
                if (response && Array.isArray(response)) {
                    response.forEach(college => {
                        collegeSelect.append(`<option value="${college.id}">${college.name}</option>`);
                    });
                } else if (response && response.success && Array.isArray(response.data)) {
                    response.data.forEach(college => {
                        collegeSelect.append(`<option value="${college.id}">${college.name}</option>`);
                    });
                } else {
                    console.error('Invalid response format:', response);
                    collegeSelect.append('<option value="" disabled>Error loading colleges</option>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading colleges:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
                collegeSelect.append('<option value="" disabled>Error loading colleges</option>');
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
            url: 'timetable_get_schools.php',
            method: 'GET',
            data: { college_id: collegeId },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    response.data.forEach(school => {
                        schoolSelect.append(`<option value="${school.id}">${school.name}</option>`);
                    });
                } else {
                    console.error('Error loading schools:', response.error);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading schools:', error);
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
            url: 'timetable_get_departments.php',
            method: 'GET',
            data: { school_id: schoolId },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    response.data.forEach(department => {
                        departmentSelect.append(`<option value="${department.id}">${department.name}</option>`);
                    });
                } else {
                    console.error('Error loading departments:', response.error);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading departments:', error);
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
            url: 'timetable_get_programs.php',
            method: 'GET',
            data: { department_id: departmentId },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    response.data.forEach(program => {
                        programSelect.append(`<option value="${program.id}">${program.name}</option>`);
                    });
                } else {
                    console.error('Error loading programs:', response.error);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading programs:', error);
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
            url: 'timetable_get_intakes.php',
            method: 'GET',
            data: { program_id: programId },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    response.data.forEach(intake => {
                        intakeSelect.append(`<option value="${intake.id}">${intake.name}</option>`);
                    });
                } else {
                    console.error('Error loading intakes:', response.error);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading intakes:', error);
            }
        });
    }
    loadTimetable();
}

function loadTimetable() {
    showLoading();
    
    $.ajax({
        url: 'get_timetable.php',
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
            if (response.success && response.data && response.data.length > 0) {
                displayTimetable(response.data);
            } else {
                $('#timetableTable tbody').html('<tr><td colspan="12" class="text-center">No timetable data found</td></tr>');
            }
        },
        error: function(xhr, status, error) {
            hideLoading();
            console.error('Error loading timetable:', error);
            $('#timetableTable tbody').html('<tr><td colspan="12" class="text-center text-danger">Error loading timetable data</td></tr>');
        }
    });
}

function displayTimetable(data) {
    const tbody = $('#timetableTable tbody');
    tbody.empty();
    
    // Get selected filter values
    const campus = $('#campus_id option:selected').text();
    const college = $('#college_id option:selected').text();
    const school = $('#school_id option:selected').text();
    const department = $('#department_id option:selected').text();
    const program = $('#program_id option:selected').text();
    const intake = $('#intake_id option:selected').text();
    const academicYear = $('#academic_year_id option:selected').text();
    const semester = $('#semester option:selected').text();

    // Only create title HTML if it doesn't exist
    if (!$('#timetableTitle').length) {
        let titleHtml = `
            <div id="timetableTitle" class="card mb-4">
                <div class="card-body">
                    <h2 class="card-title mb-3">Timetable</h2>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <h6 class="text-muted mb-2">Organization Structure</h6>
                                ${campus !== 'All Campuses' ? `<div><strong>Campus:</strong> ${campus}</div>` : ''}
                                ${college !== 'All Colleges' ? `<div><strong>College:</strong> ${college}</div>` : ''}
                                ${school !== 'All Schools' ? `<div><strong>School:</strong> ${school}</div>` : ''}
                                ${department !== 'All Departments' ? `<div><strong>Department:</strong> ${department}</div>` : ''}
                                ${program !== 'All Programs' ? `<div><strong>Program:</strong> ${program}</div>` : ''}
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <h6 class="text-muted mb-2">Academic Details</h6>
                                ${intake !== 'All Intakes' ? `<div><strong>Intake:</strong> ${intake}</div>` : ''}
                                ${academicYear !== 'All Years' ? `<div><strong>Academic Year:</strong> ${academicYear}</div>` : ''}
                                ${semester !== 'All Semesters' ? `<div><strong>Semester:</strong> ${semester}</div>` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Add CSS for the title
        if (!$('#timetable-title-style').length) {
            $('head').append(`
                <style id="timetable-title-style">
                    .card-title {
                        color: #012970;
                        font-weight: 600;
                        border-bottom: 2px solid #eee;
                        padding-bottom: 10px;
                    }
                    .card-body h6 {
                        font-size: 0.9rem;
                        font-weight: 600;
                    }
                    .card-body div {
                        font-size: 0.95rem;
                        margin-bottom: 5px;
                    }
                    .card-body strong {
                        color: #012970;
                        margin-right: 5px;
                    }
                </style>
            `);
        }

        // Insert the title before the table
        $('.timetable-container').prepend(titleHtml);
    }
    
    // Update the title content without recreating it
    $('#timetableTitle .card-body').html(`
        <h2 class="card-title mb-3">Timetable</h2>
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <h6 class="text-muted mb-2">Organization Structure</h6>
                    ${campus !== 'All Campuses' ? `<div><strong>Campus:</strong> ${campus}</div>` : ''}
                    ${college !== 'All Colleges' ? `<div><strong>College:</strong> ${college}</div>` : ''}
                    ${school !== 'All Schools' ? `<div><strong>School:</strong> ${school}</div>` : ''}
                    ${department !== 'All Departments' ? `<div><strong>Department:</strong> ${department}</div>` : ''}
                    ${program !== 'All Programs' ? `<div><strong>Program:</strong> ${program}</div>` : ''}
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <h6 class="text-muted mb-2">Academic Details</h6>
                    ${intake !== 'All Intakes' ? `<div><strong>Intake:</strong> ${intake}</div>` : ''}
                    ${academicYear !== 'All Years' ? `<div><strong>Academic Year:</strong> ${academicYear}</div>` : ''}
                    ${semester !== 'All Semesters' ? `<div><strong>Semester:</strong> ${semester}</div>` : ''}
                </div>
            </div>
        </div>
    `);

    // Rest of the function remains the same
    data.forEach(session => {
        session.timetable.groups.forEach((group, index) => {
            const row = $('<tr>');
            
            if (index === 0) {
                row.html(`
                    <td rowspan="${session.timetable.groups.length}" class="session-info">${session.session.day}</td>
                    <td rowspan="${session.timetable.groups.length}" class="session-info">${session.session.start_time} - ${session.session.end_time}</td>
                    <td rowspan="${session.timetable.groups.length}" class="session-info">
                        <strong>${session.timetable.module.code}</strong><br>
                        ${session.timetable.module.name}
                    </td>
                    <td rowspan="${session.timetable.groups.length}" class="session-info">${session.timetable.lecturer.name}</td>
                    <td>${group.campus.name}</td>
                    <td>${group.college.name}</td>
                    <td>${group.school.name}</td>
                    <td>${group.department.name}</td>
                    <td>${group.program.name}</td>
                    <td>${group.name}</td>
                    <td>${group.intake.year}/${group.intake.month}</td>
                    <td rowspan="${session.timetable.groups.length}" class="session-info">
                        <strong>${session.timetable.facility.name}</strong><br>
                        <small class="text-muted">${session.timetable.facility.location}</small>
                    </td>
                `);
            } else {
                row.html(`
                    <td>${group.campus.name}</td>
                    <td>${group.college.name}</td>
                    <td>${group.school.name}</td>
                    <td>${group.department.name}</td>
                    <td>${group.program.name}</td>
                    <td>${group.name}</td>
                    <td>${group.intake.year}/${group.intake.month}</td>
                `);
            }
            
            tbody.append(row);
        });
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
</body>
</html> 