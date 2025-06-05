<?php

include('connection.php');

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
    <style>
        .timetable-container {
            margin: 20px;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .selected-groups {x 
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
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-4">
                <div class="card mt-3">
                    <div class="card-header">
                        <h5>Schedule New Class</h5>
                    </div>
                    <div class="card-body">
                        <form id="timetableForm" method="POST" action="save_timetable.php" onsubmit="return false;">
                            <div class="mb-3">
                                <label for="academicYear" class="form-label">Academic Year</label>
                                <select class="form-select" id="academicYear" name="academic_year_id" required>
                                    <option value="">Select Academic Year</option>
                                    <?php while($year = mysqli_fetch_assoc($academic_years_result)): ?>
                                        <option value="<?php echo $year['id']; ?>"><?php echo $year['year_label']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="semester" class="form-label">Semester</label>
                                <select class="form-select" id="semester" name="semester" required disabled>
                                    <option value="">Select Semester</option>
                                    <option value="1">Semester 1</option>
                                    <option value="2">Semester 2</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="studentGroups" class="form-label">Student Groups</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="selectedGroupsDisplay" readonly placeholder="Select groups">
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#groupsModal" disabled>
                                        Select Groups
                                    </button>
                                </div>
                                <div id="selectedGroups" class="selected-groups mt-2"></div>
                            </div>

                            <div class="mb-3">
                                <label for="facility" class="form-label">Facility</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="selectedFacilityDisplay" readonly placeholder="Select facility">
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#facilityModal" disabled>
                                        Select Facility
                                    </button>
                                </div>
                                <input type="hidden" id="facility" name="facility_id" required>
                            </div>

                            <div class="mb-3">
                                <label for="module" class="form-label">Module</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="selectedModuleDisplay" readonly placeholder="Select module">
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#moduleModal" disabled>
                                        Select Module
                                    </button>
                                </div>
                                <input type="hidden" id="module" name="module_id" required>
                            </div>

                            <div class="mb-3">
                                <label for="lecturer" class="form-label">Lecturer</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="selectedLecturerDisplay" readonly placeholder="Select lecturer">
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#lecturerModal" disabled>
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

            <div class="col-md-8">
                <?php include('schedule_display.php'); ?>
            </div>
        </div>
    </div>

    <?php 
    include('module_selector.php');
    include('lecturer_selector.php');
    include('facility_selector.php');
    include('group_selector.php');
    ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize modals using Bootstrap's getOrCreateInstance
        const modalElements = document.querySelectorAll('.modal');
        modalElements.forEach(modalElement => {
            try {
                bootstrap.Modal.getOrCreateInstance(modalElement);
            } catch (error) {
                console.error('Error initializing modal:', error);
            }
        });

        // Add click handlers for modal triggers
        document.querySelectorAll('[data-bs-toggle="modal"]').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const targetModal = this.getAttribute('data-bs-target');
                const modal = bootstrap.Modal.getOrCreateInstance(document.querySelector(targetModal));
                modal.show();
            });
        });

        // Form field dependency logic
        const academicYearSelect = document.getElementById('academicYear');
        const semesterSelect = document.getElementById('semester');
        const groupsButton = document.querySelector('[data-bs-target="#groupsModal"]');
        const facilityButton = document.querySelector('[data-bs-target="#facilityModal"]');
        const moduleButton = document.querySelector('[data-bs-target="#moduleModal"]');
        const lecturerButton = document.querySelector('[data-bs-target="#lecturerModal"]');
        const submitButton = document.querySelector('button[type="submit"]');

        // Enable semester when academic year is selected
        academicYearSelect.addEventListener('change', function() {
            semesterSelect.disabled = !this.value;
            if (!this.value) {
                semesterSelect.value = '';
                groupsButton.disabled = true;
            }
        });

        // Enable groups selection when semester is selected
        semesterSelect.addEventListener('change', function() {
            groupsButton.disabled = !this.value;
            if (!this.value) {
                document.getElementById('selectedGroupsDisplay').value = '';
                document.getElementById('selectedGroups').innerHTML = '';
                facilityButton.disabled = true;
            }
        });

        // Enable facility selection when groups are selected
        function updateFacilityButton() {
            facilityButton.disabled = selectedGroupIds.size === 0;
            if (selectedGroupIds.size === 0) {
                document.getElementById('selectedFacilityDisplay').value = '';
                document.getElementById('facility').value = '';
                moduleButton.disabled = true;
            }
        }

        // Enable module selection when facility is selected
        function updateModuleButton() {
            moduleButton.disabled = !document.getElementById('facility').value;
            if (!document.getElementById('facility').value) {
                document.getElementById('selectedModuleDisplay').value = '';
                document.getElementById('module').value = '';
                lecturerButton.disabled = true;
            }
        }

        // Enable lecturer selection when module is selected
        function updateLecturerButton() {
            lecturerButton.disabled = !document.getElementById('module').value;
            if (!document.getElementById('module').value) {
                document.getElementById('selectedLecturerDisplay').value = '';
                document.getElementById('lecturer').value = '';
                submitButton.disabled = false;
            }
        }

        // Enable submit button when all fields are filled
        function updateSubmitButton() {
            const academicYear = document.getElementById('academicYear').value;
            const semester = document.getElementById('semester').value;
            const moduleId = document.getElementById('module').value;
            const lecturerId = document.getElementById('lecturer').value;
            const facilityId = document.getElementById('facility').value;
            
            // Enable submit button only if all fields are filled and at least one group is selected
            // submitButton.disabled = !(academicYear && semester && moduleId && lecturerId && facilityId && selectedGroupIds.size > 0);
        }

        // Add event listeners for all field changes
        academicYearSelect.addEventListener('change', updateSubmitButton);
        semesterSelect.addEventListener('change', updateSubmitButton);
        document.getElementById('facility').addEventListener('change', updateSubmitButton);
        document.getElementById('module').addEventListener('change', updateSubmitButton);
        document.getElementById('lecturer').addEventListener('change', updateSubmitButton);

        // Make update functions globally accessible
        window.updateFacilityButton = updateFacilityButton;
        window.updateModuleButton = updateModuleButton;
        window.updateLecturerButton = updateLecturerButton;
        window.updateSubmitButton = updateSubmitButton;

        // Handle form submission
        document.getElementById('timetableForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validate required fields
            const academicYear = document.getElementById('academicYear').value;
            const semester = document.getElementById('semester').value;
            const moduleId = document.getElementById('module').value;
            const lecturerId = document.getElementById('lecturer').value;
            const facilityId = document.getElementById('facility').value;

            // Check if any required field is empty
            if (!academicYear) {
                alert('Please select an Academic Year');
                return false;
            }
            if (!semester) {
                alert('Please select a Semester');
                return false;
            }
            if (selectedGroupIds.size === 0) {
                alert('Please select at least one student group');
                return false;
            }
            if (!facilityId) {
                alert('Please select a Facility');
                return false;
            }
            if (!moduleId) {
                alert('Please select a Module');
                return false;
            }
            if (!lecturerId) {
                alert('Please select a Lecturer');
                return false;
            }

            // Create FormData object
            const formData = new FormData(this);

            // Add group IDs to formData
            selectedGroupIds.forEach(groupId => {
                formData.append('group_ids[]', groupId);
            });
            
            // Send POST request
            fetch('save_timetable.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => {
                        throw new Error(err.message || 'Failed to save timetable');
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert('Class scheduled successfully');
                    // Reset form
                    this.reset();
                    // Clear selected groups
                    selectedGroups.innerHTML = '';
                    selectedGroupIds.clear();
                    selectedGroupsData.clear();
                    // Clear display fields
                    document.getElementById('selectedGroupsDisplay').value = '';
                    document.getElementById('selectedFacilityDisplay').value = '';
                    document.getElementById('selectedModuleDisplay').value = '';
                    document.getElementById('selectedLecturerDisplay').value = '';
                    // Reload schedule
                    loadSchedule();
                } else {
                    alert(data.message || 'Error scheduling class');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error scheduling class: ' + error.message);
            });

            return false;
        });
    });
    </script>
</body>
</html>
