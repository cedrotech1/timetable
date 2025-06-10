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

?>



<main id="main" class="main">
<div class="container-fluid py-4">
    <!-- <h2 class="mb-4">Time Table</h2> -->
    
    <!-- Filters Section -->
    <div class="card mb-4">
        <div class="card-body">
            <form id="filterForm" class="row g-3">
                <div class="col-12">
                    <h5 class="filter-title"><i class="bi bi-funnel"></i> Filter Timetable</h5>
                </div>

                <!-- Academic Period Filters -->
                <div class="col-12">
                    <div class="academic-period-filters">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="filter-group">
                                    <label class="form-label"><i class="bi bi-calendar2-week"></i> Academic Year</label>
                                    <select class="form-select" id="academic_year_id" name="academic_year_id">
                                        <option value="">All Academic Years</option>
                                        <?php foreach ($years as $year): ?>
                                            <option value="<?php echo $year['id']; ?>">
                                                <?php echo $year['year_label']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="filter-group">
                                    <label class="form-label"><i class="bi bi-calendar3"></i> Semester</label>
                                    <select class="form-select" id="semester" name="semester">
                                        <option value="">All Semesters</option>
                                        <?php foreach ($semesters as $sem): ?>
                                            <option value="<?php echo $sem; ?>">
                                                Semester <?php echo $sem; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
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
                <div class="col-12" style="margin-top:'-2cm'">
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
    let currentStep = 0;
    const steps = ['campus', 'college', 'school', 'department', 'program', 'intake', 'group'];
    
    // Initialize Select2 for all select elements
    $('.form-select').select2({
        width: '100%'
    });
    
    // Initialize Select2 for group filter
    $('#group_id').select2({
        placeholder: "Select groups",
        allowClear: true,
        width: '100%'
    });
    
    // Load initial data and timetable
    loadCampuses();
    loadTimetable(); // Load all timetable data initially
    
    // Add change event listeners for academic period filters
    $('#academic_year_id').on('change', function() {
        loadTimetable();
    });
    
    $('#semester').on('change', function() {
        loadTimetable();
    });
    
    // Handle next button click
    $('#nextBtn').click(function() {
        if (currentStep < steps.length - 1) {
            const currentField = steps[currentStep];
            const nextField = steps[currentStep + 1];
            
            // Load dependent data before moving to next step
            if (currentField === 'campus') {
                handleCampusChange();
            } else if (currentField === 'college') {
                handleCollegeChange();
            } else if (currentField === 'school') {
                handleSchoolChange();
            } else if (currentField === 'department') {
                handleDepartmentChange();
            } else if (currentField === 'program') {
                handleProgramChange();
            } else if (currentField === 'intake') {
                handleIntakeChange();
            }
            
            // Move to next step
            $('.filter-step').removeClass('active');
            $(`#${nextField}-step`).addClass('active');
            
            // Update step indicator
            $(`.step[data-step="${currentField}"]`).addClass('completed');
            $(`.step[data-step="${nextField}"]`).addClass('active');
            
            // Show/hide navigation buttons
            $('#prevBtn').show();
            if (currentStep + 1 === steps.length - 1) {
                $('#nextBtn').hide();
                $('#applyBtn').show();
            }
            
            currentStep++;
            
            // Load timetable with current filters
            loadTimetable();
        }
    });
    
    // Handle previous button click
    $('#prevBtn').click(function() {
        if (currentStep > 0) {
            const currentField = steps[currentStep];
            const prevField = steps[currentStep - 1];
            
            // Move to previous step
            $('.filter-step').removeClass('active');
            $(`#${prevField}-step`).addClass('active');
            
            // Update step indicator
            $(`.step[data-step="${currentField}"]`).removeClass('active completed');
            $(`.step[data-step="${prevField}"]`).addClass('active');
            
            // Show/hide navigation buttons
            $('#nextBtn').show();
            $('#applyBtn').hide();
            if (currentStep - 1 === 0) {
                $('#prevBtn').hide();
            }
            
            currentStep--;
            
            // Load timetable with current filters
            loadTimetable();
        }
    });
    
    // Handle form submission
    $('#filterForm').on('submit', function(e) {
        e.preventDefault();
        loadTimetable();
    });
    
    // Handle reset
    function resetFilters() {
        currentStep = 0;
        $('.filter-step').removeClass('active');
        $('#campus-step').addClass('active');
        $('.step').removeClass('active completed');
        $('.step[data-step="campus"]').addClass('active');
        $('#prevBtn').hide();
        $('#nextBtn').show();
        $('#applyBtn').hide();
        $('.form-select').val('').trigger('change');
        $('#group_id').val(null).trigger('change');
        
        // Reset academic year and semester to empty values
        $('#academic_year_id').val('').trigger('change');
        $('#semester').val('').trigger('change');
        
        loadTimetable();
    }
    
    // Make resetFilters available globally
    window.resetFilters = resetFilters;
    
    // Add change event listeners for all select elements
    $('#campus_id').on('change', function() {
        handleCampusChange();
        loadTimetable();
    });
    
    $('#college_id').on('change', function() {
        handleCollegeChange();
        loadTimetable();
    });
    
    $('#school_id').on('change', function() {
        handleSchoolChange();
        loadTimetable();
    });
    
    $('#department_id').on('change', function() {
        handleDepartmentChange();
        loadTimetable();
    });
    
    $('#program_id').on('change', function() {
        handleProgramChange();
        loadTimetable();
    });
    
    $('#intake_id').on('change', function() {
        handleIntakeChange();
        loadTimetable();
    });

    // Add group change handler
    $('#group_id').on('change', function() {
        loadTimetable();
    });
});

function showLoading() {
    $('#loadingIndicator').show();
}

function hideLoading() {
    $('#loadingIndicator').hide();
}

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
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading campuses:', error);
        }
    });
}

function handleCampusChange() {
    const campusId = $('#campus_id').val();
    const collegeSelect = $('#college_id');
    
    // Reset dependent dropdowns
    collegeSelect.empty().append('<option value="">All Colleges</option>');
    $('#school_id').empty().append('<option value="">All Schools</option>');
    $('#department_id').empty().append('<option value="">All Departments</option>');
    $('#program_id').empty().append('<option value="">All Programs</option>');
    $('#intake_id').empty().append('<option value="">All Intakes</option>');
    
    if (campusId) {
        $.ajax({
            url: 'Dashboard/get_organization_structure.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    const campus = response.data.find(c => c.id === campusId);
                    if (campus && campus.colleges) {
                        campus.colleges.forEach(college => {
                            collegeSelect.append(`<option value="${college.id}">${college.name}</option>`);
                        });
                    }
                }
            }
        });
    }
}

function handleCollegeChange() {
    const campusId = $('#campus_id').val();
    const collegeId = $('#college_id').val();
    const schoolSelect = $('#school_id');
    
    // Reset dependent dropdowns
    schoolSelect.empty().append('<option value="">All Schools</option>');
    $('#department_id').empty().append('<option value="">All Departments</option>');
    $('#program_id').empty().append('<option value="">All Programs</option>');
    $('#intake_id').empty().append('<option value="">All Intakes</option>');
    
    if (campusId && collegeId) {
        $.ajax({
            url: 'Dashboard/get_organization_structure.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    const campus = response.data.find(c => c.id === campusId);
                    if (campus) {
                        const college = campus.colleges.find(c => c.id === parseInt(collegeId));
                        if (college && college.schools) {
                            college.schools.forEach(school => {
                                schoolSelect.append(`<option value="${school.id}">${school.name}</option>`);
                            });
                        }
                    }
                }
            }
        });
    }
}

function handleSchoolChange() {
    const campusId = $('#campus_id').val();
    const collegeId = $('#college_id').val();
    const schoolId = $('#school_id').val();
    const departmentSelect = $('#department_id');
    
    // Reset dependent dropdowns
    departmentSelect.empty().append('<option value="">All Departments</option>');
    $('#program_id').empty().append('<option value="">All Programs</option>');
    $('#intake_id').empty().append('<option value="">All Intakes</option>');
    
    if (campusId && collegeId && schoolId) {
        $.ajax({
            url: 'Dashboard/get_organization_structure.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    const campus = response.data.find(c => c.id === campusId);
                    if (campus) {
                        const college = campus.colleges.find(c => c.id === parseInt(collegeId));
                        if (college) {
                            const school = college.schools.find(s => s.id === parseInt(schoolId));
                            if (school && school.departments) {
                                school.departments.forEach(department => {
                                    departmentSelect.append(`<option value="${department.id}">${department.name}</option>`);
                                });
                            }
                        }
                    }
                }
            }
        });
    }
}

function handleDepartmentChange() {
    const campusId = $('#campus_id').val();
    const collegeId = $('#college_id').val();
    const schoolId = $('#school_id').val();
    const departmentId = $('#department_id').val();
    const programSelect = $('#program_id');
    
    // Reset dependent dropdowns
    programSelect.empty().append('<option value="">All Programs</option>');
    $('#intake_id').empty().append('<option value="">All Intakes</option>');
    
    if (campusId && collegeId && schoolId && departmentId) {
        $.ajax({
            url: 'Dashboard/get_organization_structure.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    const campus = response.data.find(c => c.id === campusId);
                    if (campus) {
                        const college = campus.colleges.find(c => c.id === parseInt(collegeId));
                        if (college) {
                            const school = college.schools.find(s => s.id === parseInt(schoolId));
                            if (school) {
                                const department = school.departments.find(d => d.id === parseInt(departmentId));
                                if (department && department.programs) {
                                    department.programs.forEach(program => {
                                        programSelect.append(`<option value="${program.id}">${program.name}</option>`);
                                    });
                                }
                            }
                        }
                    }
                }
            }
        });
    }
}

function handleProgramChange() {
    const campusId = $('#campus_id').val();
    const collegeId = $('#college_id').val();
    const schoolId = $('#school_id').val();
    const departmentId = $('#department_id').val();
    const programId = $('#program_id').val();
    const intakeSelect = $('#intake_id');
    const groupSelect = $('#group_id');
    
    // Reset dropdowns
    intakeSelect.empty().append('<option value="">All Intakes</option>');
    groupSelect.empty().append('<option value="">All Groups</option>');
    
    if (campusId && collegeId && schoolId && departmentId && programId) {
        $.ajax({
            url: 'Dashboard/get_organization_structure.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    const campus = response.data.find(c => c.id === campusId);
                    if (campus) {
                        const college = campus.colleges.find(c => c.id === parseInt(collegeId));
                        if (college) {
                            const school = college.schools.find(s => s.id === parseInt(schoolId));
                            if (school) {
                                const department = school.departments.find(d => d.id === parseInt(departmentId));
                                if (department) {
                                    const program = department.programs.find(p => p.id === parseInt(programId));
                                    if (program) {
                                        // Load intakes
                                        if (program.intakes) {
                                            program.intakes.forEach(intake => {
                                                intakeSelect.append(`<option value="${intake.id}">${intake.year}/${intake.month}</option>`);
                                            });
                                        }
                                        
                                        // Load groups
                                        if (program.groups) {
                                            program.groups.forEach(group => {
                                                groupSelect.append(`<option value="${group.id}">${group.name} (${group.size})</option>`);
                                            });
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        });
    }
}

function handleIntakeChange() {
    const campusId = $('#campus_id').val();
    const collegeId = $('#college_id').val();
    const schoolId = $('#school_id').val();
    const departmentId = $('#department_id').val();
    const programId = $('#program_id').val();
    const intakeId = $('#intake_id').val();
    const groupSelect = $('#group_id');
    
    // Reset group dropdown
    groupSelect.empty().append('<option value="">All Groups</option>');
    
    if (campusId && collegeId && schoolId && departmentId && programId && intakeId) {
        $.ajax({
            url: 'Dashboard/get_organization_structure.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    const campus = response.data.find(c => c.id === campusId);
                    if (campus) {
                        const college = campus.colleges.find(c => c.id === parseInt(collegeId));
                        if (college) {
                            const school = college.schools.find(s => s.id === parseInt(schoolId));
                            if (school) {
                                const department = school.departments.find(d => d.id === parseInt(departmentId));
                                if (department) {
                                    const program = department.programs.find(p => p.id === parseInt(programId));
                                    if (program) {
                                        const intake = program.intakes.find(i => i.id === parseInt(intakeId));
                                        if (intake && intake.groups) {
                                            intake.groups.forEach(group => {
                                                groupSelect.append(`<option value="${group.id}">${group.name}</option>`);
                                            });
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        });
    }
}

function loadTimetable() {
    showLoading();
    
    // Get all current filter values
    const filters = {
        campus_id: $('#campus_id').val(),
        college_id: $('#college_id').val(),
        school_id: $('#school_id').val(),
        department_id: $('#department_id').val(),
        program_id: $('#program_id').val(),
        intake_id: $('#intake_id').val(),
        group_id: $('#group_id').val(),
        academic_year_id: $('#academic_year_id').val(),
        semester: $('#semester').val()
    };
    
    $.ajax({
        url: 'Dashboard/get_timetable.php',
        method: 'GET',
        data: filters,
        dataType: 'json',
        success: function(response) {
            hideLoading();
            const container = $('.timetable-container');
            container.empty();

            if (response.success) {
                if (response.data && response.data.length > 0) {
                    displayTimetable(response.data);
                } else {
                    // Show no data message with current filters
                    const activeFilters = Object.entries(filters)
                        .filter(([key, value]) => value)
                        .map(([key, value]) => {
                            const select = $(`#${key}`);
                            const text = select.find('option:selected').text();
                            return `<div><strong>${key.replace('_id', '').replace(/\b\w/g, l => l.toUpperCase())}:</strong> ${text}</div>`;
                        })
                        .join('');

                    container.html(`
                        <div class="no-data-message">
                            <div class="alert alert-info">
                                <h4><i class="bi bi-info-circle"></i> No Timetable Found</h4>
                                <p>Current filters:</p>
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-muted mb-2">Organization Structure</h6>
                                        ${activeFilters}
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
                    <div class="alert alert-danger">
                        <h4><i class="bi bi-exclamation-triangle"></i> Error</h4>
                        <p>${response.error || 'An error occurred while loading the timetable.'}</p>
                    </div>
                `);
            }
        },
        error: function(xhr, status, error) {
            hideLoading();
            const container = $('.timetable-container');
            container.html(`
                <div class="alert alert-danger">
                    <h4><i class="bi bi-exclamation-triangle"></i> Error</h4>
                    <p>Failed to load timetable data. Please try again later.</p>
                    <small class="text-muted">Error details: ${error}</small>
                </div>
            `);
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

    // Add organization structure and academic details header
    container.append(`
        <div class="timetable-header p-3">
            <div class="row">
                <div class="col-md-6">
                    <div class="header-section">
                        <h4><i class="bi bi-building"></i> Organization Structure</h4>
                        <div class="header-content">
                            ${campus !== 'All Campuses' ? `<div><strong>Campus:</strong> ${campus}</div>` : ''}
                            ${college !== 'All Colleges' ? `<div><strong>College:</strong> ${college}</div>` : ''}
                            ${school !== 'All Schools' ? `<div><strong>School:</strong> ${school}</div>` : ''}
                            ${department !== 'All Departments' ? `<div><strong>Department:</strong> ${department}</div>` : ''}
                            ${program !== 'All Programs' ? `<div><strong>Program:</strong> ${program}</div>` : ''}
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="header-section">
                        <h4><i class="bi bi-calendar-check"></i> Academic Details</h4>
                        <div class="header-content">
                            ${intake !== 'All Intakes' ? `<div><strong>Intake:</strong> ${intake}</div>` : ''}
                            ${academicYear !== 'All Academic Years' ? `<div><strong>Academic Year:</strong> ${academicYear}</div>` : ''}
                            ${semester !== 'All Semesters' ? `<div><strong>Semester:</strong> ${semester}</div>` : ''}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `);

    // Group sessions by their unique combination of day, time, module, and lecturer
    const groupedSessions = {};
    data.forEach(session => {
        const key = `${session.session.day}_${session.session.start_time}_${session.session.end_time}_${session.timetable.module.id}_${session.timetable.lecturer.id}`;
        if (!groupedSessions[key]) {
            groupedSessions[key] = {
                session: session.session,
                timetable: session.timetable,
                groups: []
            };
        }
        groupedSessions[key].groups = groupedSessions[key].groups.concat(session.timetable.groups);
    });

    // Create cards container
    const cardsContainer = $('<div class="timetable-cards"></div>');

    // Add sessions as cards
    Object.values(groupedSessions).forEach(groupedSession => {
        const { session, timetable, groups } = groupedSession;
        
        // Create card for each session
        const card = $(`
            <div class="timetable-card">
                <div class="card-header">
                    <div class="session-time">
                        <i class="bi bi-clock"></i> ${session.start_time} - ${session.end_time}
                    </div>
                    <div class="session-day">
                        <i class="bi bi-calendar"></i> ${session.day}
                    </div>
                </div>
                <div class="card-body">
                    <div class="module-info">
                        <h5 class="module-code">${timetable.module.code}</h5>
                        <p class="module-name">${timetable.module.name}</p>
                    </div>
                    <div class="lecturer-info">
                        <i class="bi bi-person"></i> ${timetable.lecturer.name}
                    </div>
                    <div class="facility-info">
                        <i class="bi bi-building"></i> ${timetable.facility.name}
                        <small class="text-muted">${timetable.facility.location} (${timetable.facility.type}, Capacity: ${timetable.facility.capacity})</small>
                    </div>
                    <div class="groups-info">
                        <h6><i class="bi bi-people"></i> Groups</h6>
                        <div class="groups-list">
                            ${groups.map(group => `
                                <div class="group-item">
                                    <div class="group-header">
                                        <div class="group-name">${group.name}</div>
                                        <div class="group-size">Size: ${group.size}</div>
                                    </div>
                                    <div class="group-details">
                                        <div class="detail-row">
                                            <i class="bi bi-geo-alt"></i>
                                            <span>Campus: ${group.campus.name}</span>
                                        </div>
                                        <div class="detail-row">
                                            <i class="bi bi-building"></i>
                                            <span>College: ${group.college.name}</span>
                                        </div>
                                        <div class="detail-row">
                                            <i class="bi bi-bank"></i>
                                            <span>School: ${group.school.name}</span>
                                        </div>
                                        <div class="detail-row">
                                            <i class="bi bi-diagram-3"></i>
                                            <span>Department: ${group.department.name}</span>
                                        </div>
                                        <div class="detail-row">
                                            <i class="bi bi-mortarboard"></i>
                                            <span>Program: ${group.program.name} (${group.program.code})</span>
                                        </div>
                                        <div class="detail-row">
                                            <i class="bi bi-calendar-date"></i>
                                            <span>Intake: ${group.intake.year}/${group.intake.month}</span>
                                        </div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
            </div>
        `);
        
        cardsContainer.append(card);
    });

    container.append(cardsContainer);
}
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
/* Main Container */
.main {
    padding: 15px;
    background: #f8f9fa;
}

/* Card Styling */
.card {
    margin-bottom: 15px;
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.card-body {
    padding: 15px;
}

/* Filter Title */
.filter-title {
    color: #012970;
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #e9ecef;
}

.filter-title i {
    margin-right: 0.5rem;
    color: #012970;
}

/* Step Indicator */
.step-indicator {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    position: relative;
}

.step-indicator::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 2px;
    background: #e9ecef;
    z-index: 1;
}

.step {
    position: relative;
    z-index: 2;
    text-align: center;
    width: 80px;
}

.step-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #fff;
    border: 2px solid #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 0.25rem;
    transition: all 0.3s ease;
}

.step.active .step-icon {
    background: #012970;
    border-color: #012970;
    color: #fff;
}

.step.completed .step-icon {
    background: #28a745;
    border-color: #28a745;
    color: #fff;
}

.step-label {
    font-size: 0.75rem;
    color: #6c757d;
    font-weight: 500;
}

.step.active .step-label {
    color: #012970;
    font-weight: 600;
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

/* Select2 Custom Styling */
.select2-container--default .select2-selection--single {
    height: 32px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    margin-bottom: 0;
}

.select2-container {
    margin-bottom: 0;
}

.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 32px;
    padding-left: 10px;
    font-size: 0.875rem;
    color: #495057;
}

.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 30px;
}

.select2-container--default .select2-results__option--highlighted[aria-selected] {
    background-color: #012970;
}

.select2-dropdown {
    border: 1px solid #dee2e6;
    border-radius: 4px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

/* Timetable Container */
.timetable-container {
    margin-top: 15px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

/* Table Styling */
.timetable-table {
    font-size: 0.875rem;
    width: 100%;
    margin-bottom: 0;
}

.timetable-table th {
    background-color: #012970;
    color: #fff;
    font-weight: 500;
    padding: 8px;
    border: 1px solid #dee2e6;
}

.timetable-table td {
    padding: 8px;
    border: 1px solid #dee2e6;
    vertical-align: middle;
}

.timetable-table tbody tr:hover {
    background-color: #f8f9fa;
}

.timetable-table .session-header {
    background-color: #f8f9fa;
}

.timetable-table strong {
    color: #012970;
    font-weight: 600;
}

/* Organization Structure and Academic Details */
.header-section {
    padding: 10px;
    background: #f8f9fa;
    border-radius: 6px;
    margin-bottom: 10px;
}

.header-section h4 {
    color: #012970;
    font-size: 1rem;
    margin-bottom: 10px;
    padding-bottom: 5px;
    border-bottom: 1px solid #dee2e6;
}

.header-content {
    font-size: 0.875rem;
    color: #495057;
}

.header-content div {
    margin-bottom: 5px;
}

.header-content strong {
    color: #012970;
    font-weight: 500;
}

/* No Data Message */
.no-data-message {
    padding: 15px;
}

.no-data-message .alert {
    margin-bottom: 0;
}

.no-data-message h4 {
    color: #012970;
    font-size: 1.1rem;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.no-data-message h4 i {
    color: #012970;
}

.no-data-message p {
    margin-bottom: 10px;
    color: #495057;
}

.no-data-message h6 {
    color: #6c757d;
    font-size: 0.875rem;
    margin-bottom: 5px;
}

.no-data-message div {
    font-size: 0.875rem;
    margin-bottom: 3px;
    color: #495057;
}

.no-data-message strong {
    color: #012970;
    font-weight: 500;
}

/* Loading Indicator */
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

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .step-indicator {
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    
    .step {
        width: calc(33.333% - 0.5rem);
    }
    
    .step-indicator::before {
        display: none;
    }
    
    .filter-actions {
        flex-wrap: wrap;
    }
    
    .filter-actions .btn {
        width: 100%;
    }
}

/* Add these styles to your existing CSS */
.timetable-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
    padding: 1.5rem;
}

.timetable-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    overflow: hidden;
}

.timetable-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.timetable-card .card-header {
    background: #012970;
    color: #fff;
    padding: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.timetable-card .session-time,
.timetable-card .session-day {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
}

.timetable-card .card-body {
    padding: 1.5rem;
}

.timetable-card .module-info {
    margin-bottom: 1.25rem;
}

.timetable-card .module-code {
    color: #012970;
    font-size: 1.2rem;
    margin-bottom: 0.25rem;
}

.timetable-card .module-name {
    color: #6c757d;
    font-size: 1rem;
    margin-bottom: 0;
}

.timetable-card .lecturer-info,
.timetable-card .facility-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
    color: #495057;
    font-size: 0.95rem;
}

.timetable-card .groups-info {
    border-top: 1px solid #e9ecef;
    padding-top: 1rem;
    margin-top: 1rem;
}

.timetable-card .groups-info h6 {
    color: #012970;
    font-size: 1rem;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.timetable-card .groups-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.timetable-card .group-item {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.timetable-card .group-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #e9ecef;
}

.timetable-card .group-name {
    font-weight: 600;
    color: #012970;
    font-size: 1rem;
}

.timetable-card .group-size {
    background: #e9ecef;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.85rem;
    color: #495057;
}

.timetable-card .group-details {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.timetable-card .detail-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    color: #495057;
}

.timetable-card .detail-row i {
    color: #012970;
    font-size: 1rem;
}

@media (max-width: 768px) {
    .timetable-cards {
        grid-template-columns: 1fr;
        padding: 1rem;
    }
    
    .timetable-card .card-body {
        padding: 1rem;
    }
    
    .timetable-card .group-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
}

/* Add these styles to your existing CSS */
.academic-period-filters {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    border: 1px solid #e9ecef;
}

.academic-period-filters .filter-group {
    background: #fff;
    padding: 1rem;
    border-radius: 6px;
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
}

.academic-period-filters .filter-group:hover {
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
    border-color: #012970;
}

.academic-period-filters .form-label {
    color: #012970;
    font-weight: 500;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.academic-period-filters .form-label i {
    font-size: 1.1rem;
}

.academic-period-filters .form-select {
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 0.5rem;
    font-size: 0.95rem;
    color: #495057;
    transition: border-color 0.3s ease;
}

.academic-period-filters .form-select:focus {
    border-color: #012970;
    box-shadow: 0 0 0 0.2rem rgba(1, 41, 112, 0.1);
}

@media (max-width: 768px) {
    .academic-period-filters {
        padding: 0.75rem;
    }
    
    .academic-period-filters .filter-group {
        padding: 0.75rem;
    }
}
</style>
</body>
</html> 