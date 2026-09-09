<?php
// session_start();

$user_id = $_SESSION['id'];
$stmt = $connection->prepare("SELECT school FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$school = $result->fetch_assoc();
$userSchoolId = $school['school'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Select Department Programs Filtered by User School</title>
<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<style>
  body {
    background: #f8f9fa;
  }

  /* Selected groups cards styling */
  #selectedGroupsList .card {
    margin-bottom: 6px;
    font-size: 0.85rem;
    position: relative;
    padding-top: 1.6rem;
    max-width: 450px;
  }
  #selectedGroupsList .card-body {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.5rem;
    padding: 0.4rem 0.75rem 0.5rem 0.75rem;
  }
  .remove-btn {
    position: absolute;
    top: 0.2rem;
    right: 0.2rem;
    padding: 0 6px;
    font-size: 1rem;
    line-height: 1;
    border-radius: 50%;
    cursor: pointer;
    height: 1.5rem;
    width: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .group-info {
    flex-grow: 1;
  }
  .group-meta {
    font-size: 0.8rem;
    color: #6c757d;
    margin-bottom: 0.15rem;
  }
  label.form-check-label {
    user-select: none;
  }

  #selectorsContainer > div.mb-3, #groupsSection {
    transition: max-height 0.3s ease, opacity 0.3s ease;
  }

  #addGroupBtn {
    margin-top: 10px;
    display: none;
  }
  
  .selection-path {
    margin-bottom: 15px;
  }
  .selection-path .btn {
    margin-right: 10px;
  }
  .selection-path .btn.active {
    background-color: #0d6efd;
    border-color: #0d6efd;
  }
</style>
</head>
<body>

<div class="container">
  <h2 class="mb-4">Select Groups</h2>

  <div class="selection-path">
    <p class="fw-semibold">Select by:</p>
    <button id="byDepartmentBtn" class="btn btn-outline-primary active">Department</button>
    <button id="byProgramBtn" class="btn btn-outline-primary">Program</button>
  </div>

  <div id="selectorsContainer">
    <div id="departmentSection" class="mb-3">
      <label for="departmentSelect" class="form-label fw-semibold">Department</label>
      <select id="departmentSelect" class="form-select">
        <option value="">-- Select Department --</option>
      </select>
    </div>

    <div id="programSection" class="mb-3 d-none">
      <label for="programSelect" class="form-label fw-semibold">Program</label>
      <select id="programSelect" class="form-select">
        <option value="">-- Select Program --</option>
      </select>
    </div>

    <div id="intakeSection" class="mb-3 d-none">
      <label for="intakeSelect" class="form-label fw-semibold">Intake</label>
      <select id="intakeSelect" class="form-select">
        <option value="">-- Select Intake --</option>
      </select>
    </div>

    <div id="groupsSection" class="mb-4 d-none">
      <h5 class="fw-semibold">Groups</h5>
      <ul id="groupsList" class="list-group"></ul>
    </div>
  </div>

  <button id="addGroupBtn" class="btn btn-sm btn-outline-secondary">Add Another Group</button>

  <h4 class="fw-semibold mb-3 mt-4">Selected Groups</h4>
  <div id="selectedGroupsList"></div>
  <p class="fw-bold mt-3 fs-5" id="totalStudents">Total students in selected groups: 0</p>
</div>

<script>
$(function() {
  const STUDENTS_PER_GROUP = 20;
  const userSchoolId = <?= json_encode($userSchoolId) ?>;
  let departments = [];
  let programs = [];
  let selectedDepartment = null;
  let selectedProgram = null;
  let selectedIntake = null;
  let currentSelectionPath = 'department'; // 'department' or 'program'

  // Load persisted groups or empty array
  let selectedGroups = JSON.parse(localStorage.getItem('selectedGroups')) || [];

  // Save selectedGroups to localStorage
  function saveSelectedGroups() {
    localStorage.setItem('selectedGroups', JSON.stringify(selectedGroups));
  }

  // Update total students count
  function updateStudentCount() {
    const total = selectedGroups.reduce((sum, group) => sum + (group.size || STUDENTS_PER_GROUP), 0);
    $('#totalStudents').text(`Total students in selected groups: ${total}`);
  }

  // Show/hide selects & groups based on selectedGroups
  function updateSelectorsVisibility() {
    if(selectedGroups.length > 0) {
      $('#selectorsContainer').hide();
      $('#addGroupBtn').show();
    } else {
      $('#selectorsContainer').show();
      $('#addGroupBtn').hide();
    }
  }

  // Reset all selectors
  function resetSelectors() {
    if (currentSelectionPath === 'department') {
      $('#departmentSection').removeClass('d-none');
      $('#programSection, #intakeSection, #groupsSection').addClass('d-none');
      $('#programSelect, #intakeSelect').val('');
      $('#groupsList').empty();
    } else {
      $('#departmentSection').addClass('d-none');
      $('#programSection').removeClass('d-none');
      $('#intakeSection, #groupsSection').addClass('d-none');
      $('#intakeSelect').val('');
      $('#groupsList').empty();
    }
    selectedDepartment = null;
    selectedProgram = null;
    selectedIntake = null;
  }

  // Render departments dropdown
  function renderDepartments() {
    const $sel = $('#departmentSelect').empty().append('<option value="">-- Select Department --</option>');
    departments.forEach(d => {
      $sel.append(`<option value="${d.id}">${d.name}</option>`);
    });
  }

  // Render programs dropdown
  function renderPrograms() {
    const $sel = $('#programSelect').empty().append('<option value="">-- Select Program --</option>');
    
    let filteredPrograms = programs;
    if (currentSelectionPath === 'department' && selectedDepartment) {
      // Show only programs for selected department
      filteredPrograms = programs.filter(p => p.department_id == selectedDepartment.id);
    } else if (currentSelectionPath === 'program') {
      // Show all programs for the user's school
      filteredPrograms = programs.filter(p => p.school_id == userSchoolId);
    }
    
    filteredPrograms.forEach(p => {
      $sel.append(`<option value="${p.id}">${p.name}${p.code ? ' (' + p.code + ')' : ''}</option>`);
    });
    $('#programSection').removeClass('d-none');
  }

  // Render intakes dropdown
  function renderIntakes() {
    if (!selectedProgram || !selectedProgram.intakes?.length) return $('#intakeSection').addClass('d-none');
    const $sel = $('#intakeSelect').empty().append('<option value="">-- Select Intake --</option>');
    selectedProgram.intakes.forEach(i => {
      $sel.append(`<option value="${i.id}">${i.year} - Month: ${i.month}</option>`);
    });
    $('#intakeSection').removeClass('d-none');
  }

  // Render groups list with checkboxes
  function renderGroups() {
    if (!selectedIntake || !selectedIntake.groups?.length) return $('#groupsSection').addClass('d-none');

    const $list = $('#groupsList').empty();
    selectedIntake.groups.forEach(group => {
      const isChecked = selectedGroups.some(g => g.id === group.id);
      const li = $(`
        <li class="list-group-item d-flex align-items-center">
          <input class="form-check-input me-2" type="checkbox" id="grp${group.id}" value="${group.id}" ${isChecked ? 'checked' : ''}>
          <label class="form-check-label flex-grow-1" for="grp${group.id}">${group.name} (${group.size || STUDENTS_PER_GROUP} students)</label>
        </li>
      `);
      $list.append(li);
    });
    $('#groupsSection').removeClass('d-none');
  }

  // Render selected groups cards with full info
  function renderSelectedGroups() {
    const $container = $('#selectedGroupsList').empty();
    selectedGroups.forEach(group => {
      const card = $(`
        <div class="card shadow-sm">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
              <div class="group-info">
                <div class="fw-semibold">${group.name}</div>
                <div class="group-meta">
                  ${group.departmentName ? 'Dept: ' + group.departmentName + ' | ' : ''}
                  Program: ${group.programName}${group.programCode ? ' (' + group.programCode + ')' : ''} | 
                  Intake: ${group.intakeYear} / ${group.intakeMonth}
                </div>
                <div class="text-muted">Size: ${group.size || STUDENTS_PER_GROUP} students</div>
              </div>
              <button type="button" class="btn btn-sm btn-danger remove-btn" data-id="${group.id}" title="Remove group">&times;</button>
            </div>
          </div>
        </div>
      `);
      $container.append(card);
    });
    updateStudentCount();
    updateSelectorsVisibility();
  }

  // Add group to selectedGroups with all metadata
  function addGroup(group, program, intake, department) {
    if (!selectedGroups.some(g => g.id === group.id)) {
      selectedGroups.push({
        id: group.id,
        name: group.name,
        size: group.size,
        programName: program.name,
        programCode: program.code,
        intakeYear: intake.year,
        intakeMonth: intake.month,
        departmentName: department ? department.name : null,
      });
      saveSelectedGroups();
    }
  }

  // Remove group by id
  function removeGroup(id) {
    selectedGroups = selectedGroups.filter(g => g.id !== id);
    saveSelectedGroups();
  }

  // Find department by id
  function findDepartmentById(id) {
    return departments.find(d => d.id == id);
  }

  // Find program by id
  function findProgramById(progId) {
    return programs.find(p => p.id == progId);
  }

  // Find intake by id inside program
  function findIntakeById(prog, intakeId) {
    return prog?.intakes?.find(i => i.id == intakeId);
  }

  // Load organization structure
  function loadOrganizationStructure() {
    $.ajax({
      url: 'get_organization_structure.php',
      method: 'GET',
      dataType: 'json',
      success: function(response) {
        if (response.success && response.data?.length) {
          departments = [];
          programs = [];
          
          response.data.forEach(campus => {
            (campus.colleges || []).forEach(college => {
              (college.schools || []).forEach(school => {
                // Filter by user's school
                if(userSchoolId && school.id != userSchoolId) return;
                
                // Collect departments for this school
                (school.departments || []).forEach(dep => {
                  departments.push({
                    id: dep.id,
                    name: dep.name,
                    schoolId: school.id
                  });
                });

                // Collect all programs for this school (both department and non-department)
                (school.all_programs || []).forEach(prog => {
                  programs.push({
                    id: prog.id,
                    name: prog.name,
                    code: prog.code,
                    department_id: prog.department_id,
                    school_id: school.id
                  });
                });
              });
            });
          });

          renderDepartments();
          renderSelectedGroups();
        } else {
          alert('No data found for your school.');
        }
      },
      error: function() {
        alert('Failed to load organization structure.');
      }
    });
  }

  // Load intakes for a program
  function loadIntakes(programId) {
    $.ajax({
      url: 'get_organization_structure.php',
      method: 'GET',
      data: { program_id: programId },
      dataType: 'json',
      success: function(response) {
        if (response.success && response.intakes?.length) {
          if (!selectedProgram) selectedProgram = {};
          selectedProgram.intakes = response.intakes;
          renderIntakes();
        } else {
          alert('No intakes found for this program.');
          $('#intakeSection').addClass('d-none');
        }
      },
      error: function() {
        alert('Failed to load intakes.');
      }
    });
  }

  // Load groups for an intake
  function loadGroups(intakeId) {
    $.ajax({
      url: 'get_organization_structure.php',
      method: 'GET',
      data: { intake_id: intakeId },
      dataType: 'json',
      success: function(response) {
        if (response.success && response.groups?.length) {
          if (!selectedIntake) selectedIntake = {};
          selectedIntake.groups = response.groups;
          renderGroups();
        } else {
          alert('No groups found for this intake.');
          $('#groupsSection').addClass('d-none');
        }
      },
      error: function() {
        alert('Failed to load groups.');
      }
    });
  }

  // Event handlers

  // Department path selection
  $('#byDepartmentBtn').on('click', function() {
    currentSelectionPath = 'department';
    $(this).addClass('active').removeClass('btn-outline-primary');
    $('#byProgramBtn').addClass('btn-outline-primary').removeClass('active');
    resetSelectors();
    $('#departmentSection').removeClass('d-none');
  });

  // Program path selection
  $('#byProgramBtn').on('click', function() {
    currentSelectionPath = 'program';
    $(this).addClass('active').removeClass('btn-outline-primary');
    $('#byDepartmentBtn').addClass('btn-outline-primary').removeClass('active');
    resetSelectors();
    $('#programSection').removeClass('d-none');
    renderPrograms(); // Refresh programs list
  });

  $('#departmentSelect').on('change', function() {
    const depId = $(this).val();
    selectedDepartment = findDepartmentById(depId);
    selectedProgram = null;
    selectedIntake = null;

    $('#programSelect').val('');
    $('#intakeSelect').val('');
    $('#groupsList').empty();
    $('#intakeSection, #groupsSection').addClass('d-none');

    if (selectedDepartment) {
      renderPrograms();
    }
  });

  $('#programSelect').on('change', function() {
    const progId = $(this).val();
    selectedProgram = findProgramById(progId);
    selectedIntake = null;

    $('#intakeSelect').val('');
    $('#groupsList').empty();
    $('#groupsSection').addClass('d-none');

    if (selectedProgram) {
      loadIntakes(selectedProgram.id);
    }
  });

  $('#intakeSelect').on('change', function() {
    const intakeId = $(this).val();
    if (!selectedProgram || !intakeId) return;
    
    selectedIntake = findIntakeById(selectedProgram, intakeId);
    if (selectedIntake) {
      loadGroups(selectedIntake.id);
    }
  });

  // Group checkbox change
  $('#groupsList').on('change', 'input[type=checkbox]', function() {
    const groupId = $(this).val();
    const group = selectedIntake.groups.find(g => g.id == groupId);
    if (!group) return;

    if (this.checked) {
      addGroup(group, selectedProgram, selectedIntake, selectedDepartment);
    } else {
      removeGroup(group.id);
    }
    renderSelectedGroups();
  });

  // Remove button on selected groups card
  $('#selectedGroupsList').on('click', '.remove-btn', function() {
    const id = $(this).data('id');
    removeGroup(id);
    renderSelectedGroups();

    // Uncheck if visible in groups list
    $(`#groupsList input[type=checkbox][value="${id}"]`).prop('checked', false);
  });

  // Add Another Group button click
  $('#addGroupBtn').on('click', function() {
    $('#selectorsContainer').show();
    $(this).hide();
    resetSelectors();
  });
  
  // Initial load
  loadOrganizationStructure();
});
</script>

<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>