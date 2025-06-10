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

// Get academic years
$academic_years_query = "SELECT * FROM academic_year ORDER BY year_label DESC";
$academic_years_result = mysqli_query($connection, $academic_years_query);

// Get programs for module filtering
$programs_query = "SELECT p.*, d.name as department_name 
                  FROM program p 
                  JOIN department d ON p.department_id = d.id 
                  ORDER BY p.name";
$programs_result = mysqli_query($connection, $programs_query);

// Get student groups
$groups_query = "SELECT sg.*, i.year, i.month, p.name as program_name 
                FROM student_group sg 
                JOIN intake i ON sg.intake_id = i.id 
                JOIN program p ON i.program_id = p.id 
                ORDER BY i.year DESC, i.month DESC";
$groups_result = mysqli_query($connection, $groups_query);

// Get facilities
$facilities_query = "SELECT f.*, c.name as campus_name 
                    FROM facility f 
                    LEFT JOIN campus c ON f.campus_id = c.id 
                    WHERE f.type = 'classroom' OR f.type = 'Lecture Hall' OR f.type = 'Laboratory'";
$facilities_result = mysqli_query($connection, $facilities_query);

// Get lecturers
$lecturers_query = "SELECT id, names, email FROM users WHERE role = 'lecturer'";
$lecturers_result = mysqli_query($connection, $lecturers_query);

// Get modules
$modules_query = "SELECT m.*, p.name as program_name, p.id as program_id 
                 FROM module m 
                 JOIN program p ON m.program_id = p.id 
                 ORDER BY m.code";
$modules_result = mysqli_query($connection, $modules_query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timetable Management</title>
    <!-- Load jQuery first -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Load Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Load Bootstrap Bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Load Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
   <style>
        .timetable-container {
            margin: 20px;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .selected-groups {
            margin-top: 10px;
            padding: 10px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            background-color: #f8f9fa;
        }

        .selected-group {
            display: inline-flex;
            align-items: center;
            margin: 5px;
            padding: 5px 10px;
            background: #e9ecef;
            border-radius: 15px;
            font-size: 0.9rem;
        }

        .selected-group button {
            margin-left: 8px;
            border: none;
            background: none;
            color: #dc3545;
            cursor: pointer;
            padding: 0;
            font-size: 1.1rem;
            line-height: 1;
        }

        .selected-group button:hover {
            color: #bd2130;
        }

        .table th {
            position: sticky;
            top: 0;
            background: white;
            z-index: 1;
        }

        .group-row {
            cursor: pointer;
        }

        .group-row:hover {
            background-color: #f8f9fa;
        }

        .group-row.selected {
            background-color: #e9ecef;
        }

        .selected-groups-preview {
            max-height: 200px;
            overflow-y: auto;
            padding: 10px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            background-color: #f8f9fa;
        }

        .filter-badge {
            display: inline-block;
            margin: 2px;
            padding: 2px 8px;
            background-color: #e9ecef;
            border-radius: 12px;
            font-size: 0.8rem;
        }

        .group-info {
            font-size: 0.8rem;
            color: #6c757d;
        }

        .facility-row {
            cursor: pointer;
        }

        .facility-row:hover {
            background-color: #f8f9fa;
        }

        .facility-row.selected {
            background-color: #e9ecef;
        }

        .facility-radio {
            cursor: pointer;
        }

        .table-danger {
            background-color: #fff3f3;
        }

        .sticky-top {
            z-index: 1;
        }
    </style>
      <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>

<?php
  include ("./includes/header.php");
  include ("./includes/menu.php");
  ?>
    <main id="main" class="main">


    <div class="container-fluid">
        <div class="row">
            <div class="col-md-8">
                <div class="card mt-3">
                    <div class="card-header">
                        <h5>Schedule New Class</h5>
                    </div>
                    <div class="card-body">
                        <form id="timetableForm" method="POST" action="save_timetable.php" onsubmit="return false;">
                            <div class="mb-3">
                                <label for="academicYear" class="form-label">Academic Year</label>
                                <select class="form-select" id="academicYear" name="academic_year_id" required disabled>
                                    <?php while ($year = mysqli_fetch_assoc($academic_years_result)): ?>
                                        <option value="<?php echo $year['id']; ?>" 
                                            <?php echo ($year['id'] == $system_data['accademic_year_id']) ? 'selected' : ''; ?>>
                                            <?php echo $year['year_label']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <input type="hidden" name="academic_year_id" value="<?php echo $system_data['accademic_year_id']; ?>">
                            </div>

                            <div class="mb-3">
                                <label for="semester" class="form-label">Semester</label>
                                <select class="form-select" id="semester" name="semester" required disabled>
                                    <option value="1" <?php echo ($system_data['semester'] == '1') ? 'selected' : ''; ?>>Semester 1</option>
                                    <option value="2" <?php echo ($system_data['semester'] == '2') ? 'selected' : ''; ?>>Semester 2</option>
                                    <option value="3" <?php echo ($system_data['semester'] == '3') ? 'selected' : ''; ?>>Semester 3</option>
                                </select>
                                <input type="hidden" name="semester" value="<?php echo $system_data['semester']; ?>">
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">Schedule</label>
                                <div id="scheduleContainer" class="border rounded p-3 bg-light">
                                    <div class="session-entry mb-3">
                                        <div class="row g-3 align-items-center">
                                            <div class="col-md-4">
                                                <select class="form-select session-day" name="sessions[0][day]"
                                                    required>
                                                    <option value="">Select Day</option>
                                                    <option value="Monday">Monday</option>
                                                    <option value="Tuesday">Tuesday</option>
                                                    <option value="Wednesday">Wednesday</option>
                                                    <option value="Thursday">Thursday</option>
                                                    <option value="Friday">Friday</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <input type="time" class="form-control session-start"
                                                    name="sessions[0][start_time]" required>
                                            </div>
                                            <div class="col-md-3">
                                                <input type="time" class="form-control session-end"
                                                    name="sessions[0][end_time]" required>
                                            </div>
                                            <div class="col-md-2">
                                                <button type="button" class="btn btn-success add-session w-100">
                                                    <i class="fas fa-plus"></i> Add
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="studentGroups" class="form-label">Student Groups</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="selectedGroupsDisplay" readonly
                                        placeholder="Select groups">
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                        data-bs-target="#groupsModal">
                                        Select Groups
                                    </button>
                                </div>
                                <div id="selectedGroups" class="selected-groups mt-2"></div>
                            </div>

                            <div class="mb-3">
                                <label for="facility" class="form-label">Facility</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="selectedFacilityDisplay" readonly
                                        placeholder="Select facility">
                                    <button type="button" class="btn btn-primary" id="facilityButton" data-bs-toggle="modal"
                                        data-bs-target="#facilityModal" disabled>
                                        Select Facility
                                    </button>
                                </div>
                                <input type="hidden" id="facility" name="facility_id" required>
                            </div>

                            <div class="mb-3">
                                <label for="module" class="form-label">Module</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="selectedModuleDisplay" readonly
                                        placeholder="Select module">
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                        data-bs-target="#moduleModal">
                                        Select Module
                                    </button>
                                </div>
                                <input type="hidden" id="module" name="module_id" required>
                            </div>

                            <div class="mb-3">
                                <label for="lecturer" class="form-label">Lecturer</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="selectedLecturerDisplay" readonly
                                        placeholder="Select lecturer">
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                        data-bs-target="#lecturerModal">
                                        Select Lecturer
                                    </button>
                                </div>
                                <input type="hidden" id="lecturer" name="lecturer_id" required>
                            </div>

                            <button type="submit" class="btn btn-primary">Schedule Class</button>
                        </form>
                    </div>
                </div>
            </div>

         
        </div>
    </div>

    <?php
    include('module_selector.php');
    include('lecturer_selector.php');
    include('facility_selector.php');
    include('group_selector.php');
    include('schedule_selector.php');
    ?>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Session management
            let sessionCount = 1;
            const facilityDisplay = document.getElementById('selectedFacilityDisplay');
            const facilityInput = document.getElementById('facility');
            const selectedGroupsDisplay = document.getElementById('selectedGroupsDisplay');
            const selectedGroups = document.getElementById('selectedGroups');
            let selectedGroupIds = new Set();
            let selectedGroupsData = new Map(); // Store group data for display

            // Function to format time for display (12-hour format)
            function formatTimeForDisplay(time) {
                if (!time) return '';
                const [hours, minutes] = time.split(':');
                const hour = parseInt(hours);
                const ampm = hour >= 12 ? 'PM' : 'AM';
                const hour12 = hour % 12 || 12;
                return `${hour12}:${minutes} ${ampm}`;
            }

            // Function to format time for database (24-hour format)
            function formatTimeForDatabase(time) {
                if (!time) return '';
                console.log('Formatting time:', time);
                
                // If time is already in 24-hour format (HH:mm), return it
                if (/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/.test(time)) {
                    const formatted = time + ':00';
                    console.log('Time already in 24h format:', formatted);
                    return formatted;
                }
                
                // Otherwise, convert from 12-hour format
                const [time12, period] = time.split(' ');
                const [hours, minutes] = time12.split(':');
                let hour = parseInt(hours);
                if (period === 'PM' && hour !== 12) hour += 12;
                if (period === 'AM' && hour === 12) hour = 0;
                const formatted = `${hour.toString().padStart(2, '0')}:${minutes}:00`;
                console.log('Converted time:', formatted);
                return formatted;
            }

            // Function to validate time range
            function validateTimeRange(startTime, endTime) {
                if (!startTime || !endTime) {
                    console.log('Missing time values:', { startTime, endTime });
                    return false;
                }
                
                console.log('Validating time range:', { startTime, endTime });
                
                // Convert times to comparable format
                const start = new Date(`2000-01-01T${startTime}`);
                const end = new Date(`2000-01-01T${endTime}`);
                
                // Check if times are valid
                if (isNaN(start.getTime()) || isNaN(end.getTime())) {
                    console.log('Invalid time format');
                    return false;
                }
                
                // Check if end time is after start time
                const isValid = end > start;
                console.log('Time range validation result:', isValid);
                return isValid;
            }

            // Function to get all valid sessions
            function getValidSessions() {
                const sessions = [];
                const sessionEntries = document.querySelectorAll('.session-entry');
                console.log('=== SESSION VALIDATION START ===');
                console.log('Number of session entries found:', sessionEntries.length);

                sessionEntries.forEach((entry, index) => {
                    const day = entry.querySelector('.session-day').value;
                    const startTime = entry.querySelector('.session-start').value;
                    const endTime = entry.querySelector('.session-end').value;

                    console.log(`\nSession ${index + 1} Details:`, {
                        day: day,
                        startTime: startTime,
                        endTime: endTime,
                        hasDay: !!day,
                        hasStartTime: !!startTime,
                        hasEndTime: !!endTime
                    });

                    if (day && startTime && endTime) {
                        // Validate time range
                        const isValidTimeRange = validateTimeRange(startTime, endTime);
                        console.log(`Time range validation for session ${index + 1}:`, isValidTimeRange);

                        if (!isValidTimeRange) {
                            console.log(`Invalid time range for session ${index + 1}`);
                            return;
                        }

                        // Format times for database
                        const formattedStartTime = formatTimeForDatabase(startTime);
                        const formattedEndTime = formatTimeForDatabase(endTime);

                        console.log(`Formatted times for session ${index + 1}:`, {
                            original: { start: startTime, end: endTime },
                            formatted: { start: formattedStartTime, end: formattedEndTime }
                        });

                        sessions.push({
                            day: day,
                            start_time: formattedStartTime,
                            end_time: formattedEndTime
                        });
                    } else {
                        console.log(`Session ${index + 1} is missing required values`);
                    }
                });

                console.log('\nFinal Valid Sessions:', sessions);
                console.log('=== SESSION VALIDATION END ===\n');
                return sessions;
            }

            // Function to create session entry HTML
            function createSessionEntryHTML(index) {
                return `
                <div class="session-entry mb-3">
                    <div class="row g-3 align-items-center">
                        <div class="col-md-4">
                            <select class="form-select session-day" name="sessions[${index}][day]" required>
                                <option value="">Select Day</option>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="time" class="form-control session-start" name="sessions[${index}][start_time]" required>
                        </div>
                        <div class="col-md-3">
                            <input type="time" class="form-control session-end" name="sessions[${index}][end_time]" required>
                        </div>
                        <div class="col-md-2">
                            ${index === 0 ? `
                                <button type="button" class="btn btn-success add-session w-100">
                                    <i class="fas fa-plus"></i> Add
                                </button>
                            ` : `
                            <button type="button" class="btn btn-danger remove-session w-100">
                                <i class="fas fa-trash"></i> Remove
                            </button>
                            `}
                        </div>
                    </div>
                </div>
            `;
            }

            // Update selected groups display
            window.updateSelectedGroupsDisplay = function() {
                const selectedGroupsDiv = document.getElementById('selectedGroups');
                if (!selectedGroupsDiv) return;
                
                selectedGroupsDiv.innerHTML = '';

                if (!window.selectedGroupsData || window.selectedGroupsData.size === 0) {
                    selectedGroupsDiv.innerHTML = '<div class="text-muted text-center">No groups selected</div>';
                    return;
                }

                // Calculate total size
                let totalSize = 0;
                window.selectedGroupsData.forEach((data, id) => {
                    console.log('Processing group data for main display:', { id, data });
                    if (data.size) {
                        totalSize += parseInt(data.size);
                    }
                });

                console.log('Total size for main display:', totalSize);

                // Add summary at the top
                const summaryDiv = document.createElement('div');
                summaryDiv.className = 'selected-groups-summary mb-3 p-2 bg-light rounded';
                summaryDiv.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${window.selectedGroupsData.size} Groups Selected</strong>
                        </div>
                        <div>
                            <strong>Total Size: ${totalSize} Students</strong>
                        </div>
                    </div>
                `;
                selectedGroupsDiv.appendChild(summaryDiv);

                // Add individual groups
                window.selectedGroupsData.forEach((data, id) => {
                    const groupDiv = document.createElement('div');
                    groupDiv.className = 'selected-group';
                    
                    // Format group info
                    const infoParts = [];
                    if (data.size) infoParts.push(`Size: ${data.size} students`);
                    if (data.program_name) infoParts.push(data.program_name);
                    if (data.intake_year && data.intake_month) {
                        infoParts.push(`${data.intake_year}/${data.intake_month}`);
                    }
                    
                    groupDiv.innerHTML = `
                        <div>
                            <div class="group-name">${data.name}</div>
                            <div class="group-info">
                                ${infoParts.join(' | ')}
                            </div>
                        </div>
                        <button type="button" class="remove-group" data-group-id="${id}" title="Remove group">
                            <i class="fas fa-times"></i>
                        </button>
                        <input type="hidden" name="group_ids[]" value="${id}">
                    `;
                    selectedGroupsDiv.appendChild(groupDiv);
                });

                // Update display text
                const displayInput = document.getElementById('selectedGroupsDisplay');
                if (displayInput) {
                    displayInput.value = window.selectedGroupsData.size > 0 ? 
                        `${window.selectedGroupsData.size} group(s) selected - Total: ${totalSize} students` : '';
                }

                // Update facility button state
                updateFacilityButton();
            };

            // Add event listener for group removal in main display
            document.addEventListener('click', function(e) {
                if (e.target.closest('.remove-group')) {
                    const groupId = e.target.closest('.remove-group').dataset.groupId;
                    if (window.selectedGroupIds) {
                        window.selectedGroupIds.delete(groupId);
                    }
                    if (window.selectedGroupsData) {
                        window.selectedGroupsData.delete(groupId);
                    }
                    window.updateSelectedGroupsDisplay();
                }
            });

            // Make updateFacilityButton globally accessible
            window.updateFacilityButton = function() {
                console.log('\n=== UPDATING FACILITY BUTTON STATE ===');
                const facilityButton = document.getElementById('facilityButton');
                if (facilityButton) {
                    // Check if groups are selected
                    const hasGroups = window.selectedGroupIds && window.selectedGroupIds.size > 0;
                    
                    // Check if schedule is valid - only check the first session entry
                    const firstSessionEntry = document.querySelector('.session-entry');
                    const hasValidSchedule = firstSessionEntry && 
                        firstSessionEntry.querySelector('.session-day').value &&
                        firstSessionEntry.querySelector('.session-start').value &&
                        firstSessionEntry.querySelector('.session-end').value;
                    
                    console.log('Button state conditions:', {
                        hasGroups,
                        hasValidSchedule,
                        selectedGroups: window.selectedGroupIds ? Array.from(window.selectedGroupIds) : [],
                        firstSession: firstSessionEntry ? {
                            day: firstSessionEntry.querySelector('.session-day').value,
                            startTime: firstSessionEntry.querySelector('.session-start').value,
                            endTime: firstSessionEntry.querySelector('.session-end').value
                        } : null
                    });
                    
                    // Enable button if both conditions are met
                    facilityButton.disabled = !(hasValidSchedule && hasGroups);
                    
                    // Add tooltip to explain why button is disabled
                    if (facilityButton.disabled) {
                        const reason = !hasValidSchedule ? 'Please complete schedule first' : 
                                     !hasGroups ? 'Please select groups first' : 
                                     'Please complete all required fields';
                        facilityButton.title = reason;
                    } else {
                        facilityButton.title = 'Select a facility';
                    }
                    
                    console.log('Button state updated:', {
                        disabled: facilityButton.disabled,
                        title: facilityButton.title
                    });
                }
                console.log('=== FACILITY BUTTON STATE UPDATED ===\n');
            };

            // Add event listeners for schedule changes
            document.addEventListener('change', function(e) {
                if (e.target.matches('.session-day, .session-start, .session-end')) {
                    console.log('Schedule changed:', {
                        element: e.target.name,
                        value: e.target.value
                    });
                    window.updateFacilityButton();
                }
            });

            // Add event listener for group selection changes
            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('group-checkbox')) {
                    console.log('Group selection changed');
                    window.updateFacilityButton();
                }
            });

            // Initialize button states
            document.addEventListener('DOMContentLoaded', function() {
                window.updateFacilityButton();
            });

            // Function to handle group selection
            function handleGroupSelection(groupId, isChecked) {
                console.log('\n=== HANDLING GROUP SELECTION ===');
                console.log('Group selection:', {
                    groupId,
                    isChecked,
                    currentSelectedGroups: Array.from(selectedGroupIds)
                });

                if (isChecked) {
                    // Get the group info from the row
                    const row = document.querySelector(`#groupsModal .group-checkbox[value="${groupId}"]`).closest('tr');
                    if (row) {
                        const groupName = row.querySelector('td:nth-child(2)').textContent;
                        const groupInfo = row.querySelector('td:nth-child(3)').textContent;
                        
                        // Store the group data
                        selectedGroupsData.set(groupId, {
                            name: groupName,
                            info: groupInfo
                        });
                        selectedGroupIds.add(groupId);
                    }
                } else {
                    // Remove the group data
                    selectedGroupsData.delete(groupId);
                    selectedGroupIds.delete(groupId);
                }

                // Update the display
                updateSelectedGroupsDisplay();
                console.log('=== GROUP SELECTION HANDLED ===\n');
            }

            // Function to remove a group
            function removeGroup(groupId) {
                console.log('\n=== REMOVING GROUP ===');
                console.log('Removing group:', groupId);
    
                // Remove from our data structures
                selectedGroupsData.delete(groupId);
                selectedGroupIds.delete(groupId);
    
                // Update the display
                updateSelectedGroupsDisplay();
    
                    // Clear facility selection if no groups are selected
                if (selectedGroupIds.size === 0) {
                    facilityDisplay.value = '';
                    facilityInput.value = '';
                }
    
                console.log('=== GROUP REMOVED ===\n');
            }

            // Add event listener for group selection in modal
            document.addEventListener('change', function (e) {
                if (e.target.matches('.group-checkbox')) {
                    const groupId = e.target.value;
                    const isChecked = e.target.checked;
                    handleGroupSelection(groupId, isChecked);
                }
            });

            // Add event listener for modal close
            document.addEventListener('hidden.bs.modal', function (e) {
                if (e.target.id === 'groupsModal') {
                    console.log('\n=== GROUPS MODAL CLOSED ===');
                    // Update the display after modal is closed
                    updateSelectedGroupsDisplay();
                    console.log('=== GROUPS MODAL CLOSED HANDLED ===\n');
                }
            });

            // Function to clear all selections
            function clearSelections() {
                console.log('\n=== CLEARING ALL SELECTIONS ===');
                
                // Clear facility selection
                facilityDisplay.value = '';
                facilityInput.value = '';

                // Clear group selections
                selectedGroupsDisplay.value = '';
                selectedGroups.innerHTML = '';
                selectedGroupIds.clear();
                selectedGroupsData.clear();

                // Clear module selection
                document.getElementById('selectedModuleDisplay').value = '';
                document.getElementById('module').value = '';

                // Clear lecturer selection
                document.getElementById('selectedLecturerDisplay').value = '';
                document.getElementById('lecturer').value = '';

                // Clear schedule
                const scheduleContainer = document.getElementById('scheduleContainer');
                scheduleContainer.innerHTML = createSessionEntryHTML(0);
                sessionCount = 1;

                // Update facility button state
                updateFacilityButton();
                
                console.log('=== ALL SELECTIONS CLEARED ===\n');
            }

            // Add event listeners for academic year and semester changes
            document.getElementById('academicYear').addEventListener('change', function () {
                clearSelections();
                checkAvailability();
            });

            document.getElementById('semester').addEventListener('change', function () {
                clearSelections();
                checkAvailability();
            });

            // Add event listener for schedule changes
            document.addEventListener('change', function (e) {
                if (e.target.matches('.session-day, .session-start, .session-end')) {
                    console.log('\n=== SCHEDULE CHANGE DETECTED ===');
                    console.log('Changed field:', {
                        name: e.target.name,
                        value: e.target.value,
                        type: e.target.type
                    });
                    
                    const sessionEntry = e.target.closest('.session-entry');
                    const startTime = sessionEntry.querySelector('.session-start').value;
                    const endTime = sessionEntry.querySelector('.session-end').value;
                    const day = sessionEntry.querySelector('.session-day').value;
                    
                    console.log('Current session state:', { day, startTime, endTime });
                    
                    if (startTime && endTime) {
                        const isValid = validateTimeRange(startTime, endTime);
                        console.log('Time range validation result:', isValid);
                        
                        if (!isValid) {
                            alert('End time must be after start time');
                            e.target.value = '';
                        }
                    }
                    
                    updateFacilityButton();
                    checkAvailability();
                    console.log('=== SCHEDULE CHANGE HANDLED ===\n');
                }
            });

            // Add event listener for facility radio buttons
            document.addEventListener('change', function (e) {
                if (e.target.matches('.facility-radio')) {
                    const row = e.target.closest('tr');
                    const facilityName = row.cells[1].textContent;
                    const facilityId = e.target.value;
                    facilityDisplay.value = facilityName;
                    facilityInput.value = facilityId;
                    const facilityModal = bootstrap.Modal.getInstance(document.getElementById('facilityModal'));
                    if (facilityModal) {
                        facilityModal.hide();
                    }
                }
            });

            // Update add session handler
            document.addEventListener('click', function (e) {
                if (e.target.closest('.add-session')) {
                    const container = document.getElementById('scheduleContainer');
                    const newSession = document.createElement('div');
                    newSession.className = 'session-entry mb-3';
                    newSession.innerHTML = createSessionEntryHTML(sessionCount);
                    container.appendChild(newSession);
                    sessionCount++;
                }
            });

            // Add time validation on change
            document.addEventListener('change', function (e) {
                if (e.target.matches('.session-start, .session-end')) {
                    const sessionEntry = e.target.closest('.session-entry');
                    const startTime = sessionEntry.querySelector('.session-start').value;
                    const endTime = sessionEntry.querySelector('.session-end').value;

                    if (startTime && endTime) {
                        if (!validateTimeRange(startTime, endTime)) {
                            alert('End time must be after start time');
                            e.target.value = '';
                        }
                    }
                }
            });

            // Function to validate sessions
            function validateSessions() {
                const sessions = getValidSessions();
                console.log('Validating sessions:', sessions);

                if (sessions.length === 0) {
                    const sessionEntries = document.querySelectorAll('.session-entry');
                    let emptyFields = [];

                    sessionEntries.forEach((entry, index) => {
                        const day = entry.querySelector('.session-day').value;
                        const startTime = entry.querySelector('.session-start').value;
                        const endTime = entry.querySelector('.session-end').value;

                        if (!day) emptyFields.push(`Day for session ${index + 1}`);
                        if (!startTime) emptyFields.push(`Start time for session ${index + 1}`);
                        if (!endTime) emptyFields.push(`End time for session ${index + 1}`);
                    });

                    alert(`Please complete all session fields:\n${emptyFields.join('\n')}`);
                    return false;
                }
                return true;
            }

            // Handle form submission
            document.getElementById('timetableForm').addEventListener('submit', async function (e) {
                e.preventDefault();

                if (!validateSessions()) {
                    return;
                }

                // Create FormData object
                const formData = new FormData(this);

                // Add group IDs to formData (ensuring no duplicates)
                const uniqueGroupIds = Array.from(selectedGroupIds);
                uniqueGroupIds.forEach(groupId => {
                    formData.append('group_ids[]', groupId);
                });

                // Add schedule data to formData
                formData.append('schedule', JSON.stringify(getValidSessions()));

                // Send POST request
                try {
                    const response = await fetch('save_timetable.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();

                    if (data.success) {
                        alert('Class scheduled successfully');
                        // Reset form
                        this.reset();
                        clearSelections();
                        // Reload schedule
                        loadSchedule();
                    } else {
                        alert(data.message || 'Error scheduling class');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Error scheduling class: ' + error.message);
                }
            });

            // Function to load schedule
            async function loadSchedule() {
                const academicYearId = document.getElementById('academicYear').value;
                const semester = document.getElementById('semester').value;

                if (!academicYearId || !semester) {
                    return;
                }

                try {
                    const response = await fetch(`get_schedule.php?academic_year=${academicYearId}&semester=${semester}`);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    const schedule = await response.json();

                    // Update schedule display
                    const scheduleContainer = document.querySelector('#scheduleDisplay');
                    if (scheduleContainer) {
                        // Clear existing content
                        scheduleContainer.innerHTML = '';

                        if (schedule.length === 0) {
                            scheduleContainer.innerHTML = '<div class="alert alert-info">No classes scheduled for this period</div>';
                            return;
                        }

                        // Create schedule table
                        const table = document.createElement('table');
                        table.className = 'table table-bordered table-hover';
                        table.innerHTML = `
                        <thead>
                            <tr>
                                <th>Module</th>
                                <th>Groups</th>
                                <th>Lecturer</th>
                                <th>Facility</th>
                                <th>Schedule</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    `;

                        const tbody = table.querySelector('tbody');
                        schedule.forEach(item => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                            <td>
                                <strong>${item.module_code}</strong><br>
                                <small class="text-muted">${item.module_name}</small>
                            </td>
                            <td>${item.groups.join(', ')}</td>
                            <td>${item.lecturer_name}</td>
                            <td>
                                ${item.facility_name}<br>
                                <small class="text-muted">${item.facility_type} (${item.facility_capacity} seats)</small>
                            </td>
                            <td>
                                ${item.sessions.map(session =>
                                `${session.day}<br>${formatTimeForDisplay(session.start_time)} - ${formatTimeForDisplay(session.end_time)}`
                            ).join('<br>')}
                            </td>
                        `;
                            tbody.appendChild(row);
                        });

                        scheduleContainer.appendChild(table);
                    }
                } catch (error) {
                    console.error('Error loading schedule:', error);
                    const scheduleContainer = document.querySelector('#scheduleDisplay');
                    if (scheduleContainer) {
                        scheduleContainer.innerHTML = `
                        <div class="alert alert-danger">
                            Error loading schedule. Please try again.
                        </div>
                    `;
                    }
                }
            }

            // Function to check facility and group availability
            async function checkAvailability() {
                const sessions = getValidSessions();
                if (sessions.length === 0) {
                    console.log('No valid sessions to check');
                    return;
                }

                const academicYearId = document.getElementById('academicYear').value;
                const semester = document.getElementById('semester').value;

                if (!academicYearId || !semester) {
                    console.log('Academic year or semester not selected');
                    return;
                }

                // Use the selectedGroupIds Set
                if (selectedGroupIds.size === 0) {
                    // Clear facility selection if no groups are selected
                    facilityDisplay.value = '';
                    facilityInput.value = '';
                    const facilityTable = document.querySelector('#facilityModal .table tbody');
                    if (facilityTable) {
                        facilityTable.innerHTML = `
                        <tr>
                            <td colspan="5" class="text-center text-muted">
                                Please select student groups first
                            </td>
                        </tr>
                    `;
                    }
                    return;
                }

                // Check facility availability
                try {
                    console.log('Checking facility availability with:', {
                        schedule: sessions,
                        academic_year_id: academicYearId,
                        semester: semester,
                        group_ids: Array.from(selectedGroupIds)
                    });

                    const response = await fetch('check_facility_availability.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            schedule: sessions,
                            academic_year_id: academicYearId,
                            semester: semester,
                            group_ids: Array.from(selectedGroupIds)
                        })
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const facilityData = await response.json();
                    console.log('Facility availability response:', facilityData);

                    if (facilityData.success) {
                        // Update facility modal with available facilities
                        const facilityTable = document.querySelector('#facilityModal .table tbody');
                        if (facilityTable) {
                            facilityTable.innerHTML = '';

                            if (facilityData.facilities.length === 0) {
                                facilityTable.innerHTML = `
                                <tr>
                                    <td colspan="5" class="text-center text-muted">
                                        No available facilities for the selected schedule
                                    </td>
                                </tr>
                            `;
                            } else {
                                facilityData.facilities.forEach(facility => {
                                    const row = document.createElement('tr');
                                    row.className = 'facility-row';
                                    row.innerHTML = `
                                    <td>
                                        <input type="radio" name="facility" value="${facility.id}" class="facility-radio">
                                    </td>
                                    <td>${facility.name}</td>
                                    <td>${facility.type}</td>
                                    <td>${facility.location}</td>
                                    <td>${facility.campus_name || 'N/A'}</td>
                                    <td>
                                        <span class="badge bg-success">
                                            Capacity: ${facility.capacity} students
                                        </span>
                                    </td>
                                `;
                                    facilityTable.appendChild(row);
                                });
                            }
                        }
                    }
                } catch (error) {
                    console.error('Error checking facility availability:', error);
                    const facilityTable = document.querySelector('#facilityModal .table tbody');
                    if (facilityTable) {
                        facilityTable.innerHTML = `
                        <tr>
                            <td colspan="5" class="text-center text-danger">
                                Error checking facility availability. Please try again.
                            </td>
                        </tr>
                    `;
                    }
                }

                // Check group availability
                try {
                    console.log('Checking group availability with:', {
                        schedule: sessions,
                        academic_year_id: academicYearId,
                        semester: semester
                    });

                    const response = await fetch('check_group_availability.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            schedule: sessions,
                            academic_year_id: academicYearId,
                            semester: semester
                        })
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const groupData = await response.json();
                    console.log('Group availability response:', groupData);

                    if (groupData.success) {
                        const groupTable = document.querySelector('#groupsModal .table tbody');
                        if (groupTable) {
                            groupTable.innerHTML = '';

                            if (groupData.groups.length === 0) {
                                groupTable.innerHTML = `
                                <tr>
                                    <td colspan="4" class="text-center text-muted">
                                        No groups available for the selected schedule
                                    </td>
                                </tr>
                            `;
                            } else {
                                groupData.groups.forEach(group => {
                                    const row = document.createElement('tr');
                                    row.className = 'group-row';
                                    row.innerHTML = `
                                    <td>
                                        <input type="checkbox" name="group" value="${group.id}" class="group-checkbox">
                                    </td>
                                    <td>${group.name}</td>
                                    <td>${group.program_name}</td>
                                    <td>${group.year}/${group.month}</td>
                                `;
                                    groupTable.appendChild(row);
                                });
                            }
                        }
                    }
                } catch (error) {
                    console.error('Error checking group availability:', error);
                    const groupTable = document.querySelector('#groupsModal .table tbody');
                    if (groupTable) {
                        groupTable.innerHTML = `
                        <tr>
                            <td colspan="4" class="text-center text-danger">
                                Error checking group availability. Please try again.
                            </td>
                        </tr>
                    `;
                    }
                }
            }

            // Add styles for the group display
            const style = document.createElement('style');
            style.textContent = `
                .selected-groups-summary {
                    border: 1px solid #dee2e6;
                    background-color: #f8f9fa;
                    margin-bottom: 1rem;
                }
                .selected-groups-summary strong {
                    color: #0d6efd;
                }
                .selected-group {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 0.75rem;
                    margin-bottom: 0.5rem;
                    background-color: #fff;
                    border: 1px solid #dee2e6;
                    border-radius: 0.25rem;
                    transition: all 0.2s ease;
                }
                .selected-group:hover {
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
                .selected-group .group-name {
                    font-weight: 500;
                    color: #212529;
                    margin-bottom: 0.25rem;
                }
                .selected-group .group-info {
                    font-size: 0.875rem;
                    color: #6c757d;
                }
                .selected-group button {
                    padding: 0.25rem 0.5rem;
                    margin-left: 0.5rem;
                    color: #dc3545;
                    border: none;
                    background: none;
                    transition: color 0.2s ease;
                }
                .selected-group button:hover {
                    color: #bd2130;
                }
                #selectedGroupsDisplay {
                    font-weight: 500;
                    color: #0d6efd;
                }
            `;
            document.head.appendChild(style);
        });
    </script>
      <script src="assets/js/main.js"></script>
  </main><!-- End #main -->
</body>

</html>