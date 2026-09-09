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
<title>Select Program Groups</title>
<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<style>
  body {
    background: #f8f9fa;
  }

  /* Selected groups cards styling */
  #selectedGroupsList {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1rem;
    width: 100%;
  }
  
  #selectedGroupsList .card {
    margin: 0;
    border: none;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    overflow: hidden;
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
  }
  
  #selectedGroupsList .card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
  }
  
  #selectedGroupsList .card-header {
    background-color: #4a6cf7;
    color: white;
    padding: 0.75rem 1.25rem;
    font-size: 1.1rem;
    font-weight: 600;
    border-bottom: none;
  }
  
  #selectedGroupsList .card-body {
    padding: 1.25rem;
  }
  
  .group-info {
    width: 100%;
  }
  
  .group-meta {
    margin-bottom: 1rem;
  }
  
  .group-meta-row {
    display: flex;
    margin-bottom: 0.5rem;
    align-items: flex-start;
  }
  
  .group-meta-label {
    flex: 0 0 100px;
    color: #6c757d;
    font-weight: 500;
  }
  
  .group-meta-value {
    flex: 1;
    color: #212529;
    font-weight: 500;
  }
  
  .group-size {
    background-color: #f1f8ff;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    text-align: center;
    font-weight: 600;
    color: #0d6efd;
    margin-top: 1rem;
    border: 1px solid #d0e3ff;
  }
  
  .remove-btn {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    background-color: rgba(255, 255, 255, 0.9);
    color: #dc3545;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
  }
  
  .remove-btn:hover {
    background-color: #dc3545;
    color: white;
    transform: scale(1.1);
  }
  label.form-check-label {
    user-select: none;
  }

  #selectorsContainer > div.mb-3, #groupsSection {
    transition: max-height 0.3s ease, opacity 0.3s ease;
  }

  #addGroupBtn {
    margin-top: 10px;
  }
  
  .click-counter {
    background-color: #e9ecef;
    border-radius: 6px;
    padding: 8px 15px;
    font-weight: 600;
    font-size: 1.1rem;
    margin-bottom: 20px;
    display: inline-block;
    color: #495057;
  }
  
  .empty-state {
    text-align: center;
    padding: 30px;
    color: #6c757d;
  }
  
  .empty-state .btn {
    margin-top: 15px;
  }
</style>
</head>
<body>

<div class="container">
  <h2 class="mb-4">Select Groups</h2>
  
  <!-- <div class="click-counter">Groups Selected: <span id="clickCount">0</span></div> -->

  <!-- Setup Form (Hidden by default) -->
  <div id="setupForm" class="card p-3 mb-4 d-none">
    <h5 class="fw-semibold mb-3">Setup Group Selection</h5>
    
    <div id="selectorsContainer">
      <div id="programSection" class="mb-3">
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

    <div class="d-flex justify-content-between">
      <button id="cancelSetupBtn" class="btn btn-outline-secondary">Cancel</button>
      <button id="setupCompleteBtn" class="btn btn-primary d-none">Complete Setup</button>
    </div>
  </div>

  <!-- Group Information Section -->
  <div id="groupInfoSection">
   
    <!-- Empty state when no groups selected -->
    <div id="emptyState" class="empty-state">
      <h5>No groups selected yet</h5>
      <p>Click the button below to start selecting groups</p>
      <button id="startSelectionBtn" class="btn btn-primary">Select Groups</button>
    </div>
    
    <!-- Groups list when groups are selected -->
    <div id="selectedGroupsContainer" class="d-none">
      <div id="selectedGroupsList"></div>
      <p class="fw-bold mt-3 fs-5" id="totalStudents">Total students in selected groups: 0</p>
      <button id="addGroupBtn" class="btn btn-outline-primary">Add More Groups</button>
    </div>
  </div>
</div>

<script>
$(function() {
  const STUDENTS_PER_GROUP = 20;
  const userSchoolId = <?= json_encode($userSchoolId) ?>;
  let programs = [];
  let selectedProgram = null;
  let selectedIntake = null;
  let clickCount = 0; // Track number of group selections

  // Load persisted groups or empty array
  let selectedGroups = JSON.parse(localStorage.getItem('selectedGroups')) || [];
  clickCount = selectedGroups.length; // Initialize click count with existing groups

  // Save selectedGroups to localStorage
  function saveSelectedGroups() {
    localStorage.setItem('selectedGroups', JSON.stringify(selectedGroups));
  }

  // Update click counter
  function updateClickCounter() {
    $('#clickCount').text(clickCount);
  }

  // Update total students count
  function updateStudentCount() {
    const total = selectedGroups.reduce((sum, group) => sum + (group.size || STUDENTS_PER_GROUP), 0);
    $('#totalStudents').text(`Total students in selected groups: ${total}`);
  }

  // Show/hide setup form and group info
  function updateUIState() {
    if(selectedGroups.length > 0) {
      // We have groups - show the groups list
      $('#setupForm').addClass('d-none');
      $('#emptyState').addClass('d-none');
      $('#selectedGroupsContainer').removeClass('d-none');
    } else {
      // No groups - show empty state
      $('#setupForm').addClass('d-none');
      $('#emptyState').removeClass('d-none');
      $('#selectedGroupsContainer').addClass('d-none');
    }
  }

  // Reset all selectors
  function resetSelectors() {
    $('#programSection').removeClass('d-none');
    $('#intakeSection, #groupsSection').addClass('d-none');
    $('#programSelect, #intakeSelect').val('');
    $('#groupsList').empty();
    $('#setupCompleteBtn').addClass('d-none');
    selectedProgram = null;
    selectedIntake = null;
  }

  // Show setup form
  function showSetupForm() {
    $('#setupForm').removeClass('d-none');
    resetSelectors();
  }

  // Hide setup form
  function hideSetupForm() {
    $('#setupForm').addClass('d-none');
  }

  // Render programs dropdown
  function renderPrograms() {
    const $sel = $('#programSelect').empty().append('<option value="">-- Select Program --</option>');
    
    // Show all programs for the user's school
    const filteredPrograms = programs.filter(p => p.school_id == userSchoolId);
    
    filteredPrograms.forEach(p => {
      $sel.append(`<option value="${p.id}">${p.name}${p.code ? ' (' + p.code + ')' : ''}</option>`);
    }); 
    $('#programSection').removeClass('d-none');
  }

  // Render intakes dropdown in 'Year X - Campus' format
  function renderIntakes() {
    if (!selectedProgram || !selectedProgram.intakes?.length) return $('#intakeSection').addClass('d-none');
    const $sel = $('#intakeSelect').empty().append('<option value="">-- Select Intake --</option>');
    
    // Sort intakes by year of study (ascending) and then by campus name (A-Z)
    const sortedIntakes = [...selectedProgram.intakes].sort((a, b) => {
      if (a.year_of_study !== b.year_of_study) return a.year_of_study - b.year_of_study;
      const campusA = (a.campus_name || (a.campus?.name || 'Unassigned')).toUpperCase();
      const campusB = (b.campus_name || (b.campus?.name || 'Unassigned')).toUpperCase();
      return campusA.localeCompare(campusB);
    });
    
    // Add intakes to dropdown in 'Year X - Campus' format
    sortedIntakes.forEach(intake => {
      const year = intake.year_of_study || 1;
      const campus = intake.campus_name || (intake.campus?.name || 'Unassigned');
      $sel.append(`
        <option value="${intake.id}">
          Year ${year} - ${campus}
        </option>
      `);
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
    $('#setupCompleteBtn').removeClass('d-none');
  }

  // Render selected groups cards with full info
  function renderSelectedGroups() {
    const $container = $('#selectedGroupsList').empty();
    selectedGroups.forEach(group => {
      const campusInitials = group.campusName.split(' ').map(word => word[0]).join('').toUpperCase();
      const card = $(`
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span>${group.name}</span>
            <span class="badge bg-light text-dark">${campusInitials}</span>
          </div>
          <div class="card-body">
            <div class="group-info">
              <div class="group-meta">
                <div class="group-meta-row">
                  <div class="group-meta-label">Program:</div>
                  <div class="group-meta-value">${group.programName}</div>
                </div>
                <div class="group-meta-row">
                  <div class="group-meta-label">Year of Study:</div>
                  <div class="group-meta-value">
                    <span class="badge bg-primary">Year ${group.yearOfStudy}</span>
                  </div>
                </div>
                <div class="group-meta-row">
                  <div class="group-meta-label">Campus:</div>
                  <div class="group-meta-value">
                    <i class="bi bi-geo-alt-fill me-1"></i> ${group.campusName}
                  </div>
                </div>
              </div>
              <div class="group-size">
                <i class="bi bi-people-fill me-2"></i>${group.size || STUDENTS_PER_GROUP} Students
              </div>
            </div>
          </div>
          <button type="button" class="remove-btn" data-id="${group.id}" title="Remove group">
            &times;
          </button>
        </div>
      `);
      $container.append(card);
    });
    updateStudentCount();
    updateUIState();
  }

  // Add group to selectedGroups with only essential information
  function addGroup(group, program, intake) {
    if (!selectedGroups.some(g => g.id === group.id)) {
      selectedGroups.push({
        id: group.id,
        name: group.name,
        size: group.size,
        programName: program.name,
        yearOfStudy: intake.year_of_study || 1,
        campusName: intake.campus_name || (intake.campus?.name || 'Unassigned')
      });
      clickCount++; // Increment click counter
      updateClickCounter();
      saveSelectedGroups();
    }
  }

  // Remove group by id
  function removeGroup(id) {
    selectedGroups = selectedGroups.filter(g => g.id !== id);
    clickCount--; // Decrement click counter
    updateClickCounter();
    saveSelectedGroups();
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
        if (response.success && response.data?.colleges?.length) {
          programs = [];
          
          // Process each college
          response.data.colleges.forEach(college => {
            // Process each school in the college
            (college.schools || []).forEach(school => {
              // Filter by user's school if specified
              if(userSchoolId && school.id != userSchoolId) return;
              
              // Process all programs for this school
              (school.all_programs || []).forEach(prog => {
                // Process intakes for each program and add campus info
                const programWithIntakes = {
                  id: prog.id,
                  name: prog.name,
                  code: prog.code,
                  department_id: prog.department_id,
                  school_id: school.id,
                  intakes: []
                };

                // Add intakes with campus information
                (prog.intakes || []).forEach(intake => {
                  // Handle both nested campus object and direct properties
                  const campusId = intake.campus_id || (intake.campus?.id || null);
                  const campusName = intake.campus_name || (intake.campus?.name || 'Unassigned');
                  
                  programWithIntakes.intakes.push({
                    id: intake.id,
                    year: intake.year,
                    month: intake.month,
                    campus_id: campusId,
                    campus_name: campusName,
                    groups: intake.groups || []
                  });
                });

                programs.push(programWithIntakes);
              });
            });
          });

          renderPrograms();
          renderSelectedGroups();
          updateClickCounter(); // Initialize click counter
        } else {
          alert('No data found for your school.');
        }
      },
      error: function(xhr, status, error) {
        console.error('Error loading organization structure:', error);
        alert('Failed to load organization structure. Please check console for details.');
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

  $('#programSelect').on('change', function() {
    const progId = $(this).val();
    selectedProgram = findProgramById(progId);
    selectedIntake = null;

    $('#intakeSelect').val('');
    $('#groupsList').empty();
    $('#groupsSection').addClass('d-none');
    $('#setupCompleteBtn').addClass('d-none');

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
      addGroup(group, selectedProgram, selectedIntake);
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

  // Setup Complete button click
  $('#setupCompleteBtn').on('click', function() {
    hideSetupForm();
    updateUIState();
  });

  // Cancel Setup button click
  $('#cancelSetupBtn').on('click', function() {
    hideSetupForm();
    updateUIState();
  });

  // Start Selection button click (from empty state)
  $('#startSelectionBtn').on('click', function() {
    showSetupForm();
  });

  // Add More Groups button click
  $('#addGroupBtn').on('click', function() {
    showSetupForm();
  });
  
  // Initial load
  loadOrganizationStructure();
});
</script>

<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>