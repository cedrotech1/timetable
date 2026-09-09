<?php
session_start();
include('connection.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Selector</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    
    <style>
        .selector-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 1.5rem;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .selector-title {
            color: #012970;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e9ecef;
        }
        
        .selector-group {
            margin-bottom: 1.5rem;
        }
        
        .selector-label {
            color: #012970;
            font-weight: 500;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .selector-label i {
            color: #012970;
        }
        
        .select2-container--bootstrap-5 .select2-selection {
            border: 1px solid #dee2e6;
            border-radius: 4px;
            min-height: 38px;
        }
        
        .select2-container--bootstrap-5 .select2-selection--single {
            padding: 0.375rem 0.75rem;
        }
        
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            color: #495057;
            padding: 0;
        }
        
        .select2-container--bootstrap-5 .select2-results__option--highlighted[aria-selected] {
            background-color: #012970;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 1rem;
        }
        
        .loading-spinner {
            width: 2rem;
            height: 2rem;
        }
    </style>
</head>
<body class="bg-light">
    <div class="selector-container">
        <h4 class="selector-title">
            <i class="bi bi-diagram-3"></i> Organization Structure Selector
        </h4>
        
        <form id="groupSelectorForm">
            <div class="selector-group">
                <label class="selector-label">
                    <i class="bi bi-geo-alt"></i> Campus
                </label>
                <select class="form-select" id="campus_id" name="campus_id">
                    <option value="">Select Campus</option>
                </select>
            </div>
            
            <div class="selector-group">
                <label class="selector-label">
                    <i class="bi bi-building"></i> College
                </label>
                <select class="form-select" id="college_id" name="college_id" disabled>
                    <option value="">Select College</option>
                </select>
            </div>
            
            <div class="selector-group">
                <label class="selector-label">
                    <i class="bi bi-bank"></i> School
                </label>
                <select class="form-select" id="school_id" name="school_id" disabled>
                    <option value="">Select School</option>
                </select>
            </div>
            
            <div class="selector-group">
                <label class="selector-label">
                    <i class="bi bi-diagram-3"></i> Department
                </label>
                <select class="form-select" id="department_id" name="department_id" disabled>
                    <option value="">Select Department</option>
                </select>
            </div>
            
            <div class="selector-group">
                <label class="selector-label">
                    <i class="bi bi-mortarboard"></i> Program
                </label>
                <select class="form-select" id="program_id" name="program_id" disabled>
                    <option value="">Select Program</option>
                </select>
            </div>
            
            <div class="selector-group">
                <label class="selector-label">
                    <i class="bi bi-calendar"></i> Intake
                </label>
                <select class="form-select" id="intake_id" name="intake_id" disabled>
                    <option value="">Select Intake</option>
                </select>
            </div>
            
            <div class="selector-group">
                <label class="selector-label">
                    <i class="bi bi-people"></i> Group
                </label>
                <select class="form-select" id="group_id" name="group_id" disabled>
                    <option value="">Select Group</option>
                </select>
            </div>
            
            <div class="loading">
                <div class="spinner-border loading-spinner text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        </form>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Select2 -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // Initialize Select2 for all select elements
        $('.form-select').select2({
            theme: 'bootstrap-5',
            width: '100%'
        });
        
        // Load initial campuses
        loadCampuses();
        
        // Add change event listeners
        $('#campus_id').on('change', function() {
            handleCampusChange();
        });
        
        $('#college_id').on('change', function() {
            handleCollegeChange();
        });
        
        $('#school_id').on('change', function() {
            handleSchoolChange();
        });
        
        $('#department_id').on('change', function() {
            handleDepartmentChange();
        });
        
        $('#program_id').on('change', function() {
            handleProgramChange();
        });
        
        $('#intake_id').on('change', function() {
            handleIntakeChange();
        });
    });
    
    function showLoading() {
        $('.loading').show();
    }
    
    function hideLoading() {
        $('.loading').hide();
    }
    
    function loadCampuses() {
        showLoading();
        $.ajax({
            url: 'Dashboard/get_organization_structure.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                hideLoading();
                if (response.success && response.data) {
                    const campusSelect = $('#campus_id');
                    campusSelect.empty().append('<option value="">Select Campus</option>');
                    response.data.forEach(campus => {
                        campusSelect.append(`<option value="${campus.id}">${campus.name}</option>`);
                    });
                    campusSelect.prop('disabled', false).trigger('change');
                }
            },
            error: function(xhr, status, error) {
                hideLoading();
                console.error('Error loading campuses:', error);
            }
        });
    }
    
    function handleCampusChange() {
        const campusId = $('#campus_id').val();
        const collegeSelect = $('#college_id');
        
        // Reset dependent dropdowns
        collegeSelect.empty().append('<option value="">Select College</option>').prop('disabled', true).trigger('change');
        $('#school_id').empty().append('<option value="">Select School</option>').prop('disabled', true).trigger('change');
        $('#department_id').empty().append('<option value="">Select Department</option>').prop('disabled', true).trigger('change');
        $('#program_id').empty().append('<option value="">Select Program</option>').prop('disabled', true).trigger('change');
        $('#intake_id').empty().append('<option value="">Select Intake</option>').prop('disabled', true).trigger('change');
        $('#group_id').empty().append('<option value="">Select Group</option>').prop('disabled', true).trigger('change');
        
        if (campusId) {
            showLoading();
            $.ajax({
                url: 'Dashboard/get_organization_structure.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    hideLoading();
                    if (response.success && response.data) {
                        const campus = response.data.find(c => c.id === campusId);
                        if (campus && campus.colleges) {
                            campus.colleges.forEach(college => {
                                collegeSelect.append(`<option value="${college.id}">${college.name}</option>`);
                            });
                            collegeSelect.prop('disabled', false).trigger('change');
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
        schoolSelect.empty().append('<option value="">Select School</option>').prop('disabled', true).trigger('change');
        $('#department_id').empty().append('<option value="">Select Department</option>').prop('disabled', true).trigger('change');
        $('#program_id').empty().append('<option value="">Select Program</option>').prop('disabled', true).trigger('change');
        $('#intake_id').empty().append('<option value="">Select Intake</option>').prop('disabled', true).trigger('change');
        $('#group_id').empty().append('<option value="">Select Group</option>').prop('disabled', true).trigger('change');
        
        if (campusId && collegeId) {
            showLoading();
            $.ajax({
                url: 'Dashboard/get_organization_structure.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    hideLoading();
                    if (response.success && response.data) {
                        const campus = response.data.find(c => c.id === campusId);
                        if (campus) {
                            const college = campus.colleges.find(c => c.id === parseInt(collegeId));
                            if (college && college.schools) {
                                college.schools.forEach(school => {
                                    schoolSelect.append(`<option value="${school.id}">${school.name}</option>`);
                                });
                                schoolSelect.prop('disabled', false).trigger('change');
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
        departmentSelect.empty().append('<option value="">Select Department</option>').prop('disabled', true).trigger('change');
        $('#program_id').empty().append('<option value="">Select Program</option>').prop('disabled', true).trigger('change');
        $('#intake_id').empty().append('<option value="">Select Intake</option>').prop('disabled', true).trigger('change');
        $('#group_id').empty().append('<option value="">Select Group</option>').prop('disabled', true).trigger('change');
        
        if (campusId && collegeId && schoolId) {
            showLoading();
            $.ajax({
                url: 'Dashboard/get_organization_structure.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    hideLoading();
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
                                    departmentSelect.prop('disabled', false).trigger('change');
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
        programSelect.empty().append('<option value="">Select Program</option>').prop('disabled', true).trigger('change');
        $('#intake_id').empty().append('<option value="">Select Intake</option>').prop('disabled', true).trigger('change');
        $('#group_id').empty().append('<option value="">Select Group</option>').prop('disabled', true).trigger('change');
        
        if (campusId && collegeId && schoolId && departmentId) {
            showLoading();
            $.ajax({
                url: 'Dashboard/get_organization_structure.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    hideLoading();
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
                                        programSelect.prop('disabled', false).trigger('change');
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
        
        // Reset dependent dropdowns
        intakeSelect.empty().append('<option value="">Select Intake</option>').prop('disabled', true).trigger('change');
        $('#group_id').empty().append('<option value="">Select Group</option>').prop('disabled', true).trigger('change');
        
        if (campusId && collegeId && schoolId && departmentId && programId) {
            showLoading();
            $.ajax({
                url: 'Dashboard/get_organization_structure.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    hideLoading();
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
                                        if (program && program.intakes) {
                                            program.intakes.forEach(intake => {
                                                intakeSelect.append(`<option value="${intake.id}">${intake.year}/${intake.month}</option>`);
                                            });
                                            intakeSelect.prop('disabled', false).trigger('change');
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
        groupSelect.empty().append('<option value="">Select Group</option>').prop('disabled', true).trigger('change');
        
        if (campusId && collegeId && schoolId && departmentId && programId && intakeId) {
            showLoading();
            $.ajax({
                url: 'Dashboard/get_organization_structure.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    hideLoading();
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
                                                groupSelect.prop('disabled', false).trigger('change');
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
    </script>
</body>
</html> 