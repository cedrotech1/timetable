<?php

include('connection.php');

// Get academic years
$academic_years_query = "SELECT * FROM academic_year ORDER BY year_label DESC";
$academic_years_result = mysqli_query($connection, $academic_years_query);

// Get student groups
$groups_query = "SELECT sg.*, i.year, i.month, p.name as program_name 
                FROM student_group sg 
                JOIN intake i ON sg.intake_id = i.id 
                JOIN program p ON i.program_id = p.id 
                ORDER BY i.year DESC, i.month DESC";
$groups_result = mysqli_query($connection, $groups_query);

// Get facilities
$facilities_query = "SELECT * FROM facility WHERE type = 'classroom' OR type = 'Lecture Hall' OR type = 'Laboratory'";
$facilities_result = mysqli_query($connection, $facilities_query);

// Get lecturers
$lecturers_query = "SELECT id, names, email FROM users WHERE role = 'lecturer'";
$lecturers_result = mysqli_query($connection, $lecturers_query);

// Get modules
$modules_query = "SELECT m.*, p.name as program_name 
                 FROM module m 
                 JOIN program p ON m.program_id = p.id 
                 ORDER BY m.year, m.semester, m.name";
$modules_result = mysqli_query($connection, $modules_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timetable Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .timetable-container {
            margin: 20px;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .selected-groups {
            margin-top: 10px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .selected-group {
            display: inline-block;
            margin: 5px;
            padding: 5px 10px;
            background: #e9ecef;
            border-radius: 15px;
        }
        .selected-group button {
            margin-left: 5px;
            border: none;
            background: none;
            color: #dc3545;
            cursor: pointer;
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
                        <form id="timetableForm">
                            <div class="mb-3">
                                <label for="academicYear" class="form-label">Academic Year</label>
                                <select class="form-select" id="academicYear" name="academic_year_id" required>
                                    <?php while($year = mysqli_fetch_assoc($academic_years_result)): ?>
                                        <option value="<?php echo $year['id']; ?>"><?php echo $year['year_label']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="semester" class="form-label">Semester</label>
                                <select class="form-select" id="semester" name="semester" required>
                                    <option value="1">Semester 1</option>
                                    <option value="2">Semester 2</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="module" class="form-label">Module</label>
                                <select class="form-select" id="module" name="module_id" required>
                                    <option value="">Select Module</option>
                                    <?php while($module = mysqli_fetch_assoc($modules_result)): ?>
                                        <option value="<?php echo $module['id']; ?>">
                                            <?php echo $module['name'] . ' (' . $module['code'] . ') - ' . 
                                                      $module['program_name'] . ' - Year ' . $module['year'] . 
                                                      ' Semester ' . $module['semester']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="studentGroups" class="form-label">Student Groups</label>
                                <select class="form-select" id="studentGroups" multiple>
                                    <?php while($group = mysqli_fetch_assoc($groups_result)): ?>
                                        <option value="<?php echo $group['id']; ?>">
                                            <?php echo $group['name'] . ' - ' . $group['program_name'] . ' (' . $group['year'] . '/' . $group['month'] . ')'; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <div id="selectedGroups" class="selected-groups"></div>
                            </div>

                            <div class="mb-3">
                                <label for="lecturer" class="form-label">Lecturer</label>
                                <select class="form-select" id="lecturer" name="lecturer_id" required>
                                    <option value="">Select Lecturer</option>
                                    <?php while($lecturer = mysqli_fetch_assoc($lecturers_result)): ?>
                                        <option value="<?php echo $lecturer['id']; ?>">
                                            <?php echo $lecturer['names']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="facility" class="form-label">Facility</label>
                                <select class="form-select" id="facility" name="facility_id" required>
                                    <option value="">Select Facility</option>
                                    <?php while($facility = mysqli_fetch_assoc($facilities_result)): ?>
                                        <option value="<?php echo $facility['id']; ?>">
                                            <?php echo $facility['name'] . ' (' . $facility['type'] . ') - Capacity: ' . $facility['capacity']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="startTime" class="form-label">Start Time</label>
                                <input type="datetime-local" class="form-control" id="startTime" name="start_time" required>
                            </div>

                            <div class="mb-3">
                                <label for="endTime" class="form-label">End Time</label>
                                <input type="datetime-local" class="form-control" id="endTime" name="end_time" required>
                            </div>

                            <button type="submit" class="btn btn-primary">Schedule Class</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="timetable-container">
                    <h4>Current Schedule</h4>
                    <div id="scheduleList"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Make selectedGroupIds globally accessible
        let selectedGroupIds = new Set();

        document.addEventListener('DOMContentLoaded', function() {
            const studentGroups = document.getElementById('studentGroups');
            const selectedGroups = document.getElementById('selectedGroups');

            // Handle group selection
            studentGroups.addEventListener('change', function() {
                Array.from(this.selectedOptions).forEach(option => {
                    if (!selectedGroupIds.has(option.value)) {
                        selectedGroupIds.add(option.value);
                        const groupDiv = document.createElement('div');
                        groupDiv.className = 'selected-group';
                        groupDiv.innerHTML = `
                            ${option.text}
                            <button type="button" onclick="removeGroup('${option.value}')">&times;</button>
                            <input type="hidden" name="group_ids[]" value="${option.value}">
                        `;
                        selectedGroups.appendChild(groupDiv);
                    }
                });
                this.selectedIndex = -1;
            });

            // Handle form submission
            document.getElementById('timetableForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (selectedGroupIds.size === 0) {
                    alert('Please select at least one student group');
                    return;
                }

                const formData = new FormData(this);
                
                fetch('save_timetable.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        alert('Class scheduled successfully');
                        this.reset();
                        selectedGroups.innerHTML = '';
                        selectedGroupIds.clear();
                        loadSchedule();
                    } else {
                        alert(data.message || 'Error scheduling class');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error scheduling class');
                });
            });

            // Load initial schedule
            loadSchedule();
        });

        function removeGroup(groupId) {
            const groupElement = document.querySelector(`input[value="${groupId}"]`).parentElement;
            groupElement.remove();
            selectedGroupIds.delete(groupId);
        }

        function loadSchedule() {
            const academicYear = document.getElementById('academicYear').value;
            const semester = document.getElementById('semester').value;

            fetch(`get_schedule.php?academic_year=${academicYear}&semester=${semester}`)
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(err => {
                            throw new Error(err.message || 'Failed to load schedule');
                        });
                    }
                    return response.json();
                })
                .then(schedule => {
                    const scheduleList = document.getElementById('scheduleList');
                    scheduleList.innerHTML = '';

                    if (schedule.length === 0) {
                        scheduleList.innerHTML = '<div class="alert alert-info">No classes scheduled for this period</div>';
                        return;
                    }

                    schedule.forEach(item => {
                        const scheduleItem = document.createElement('div');
                        scheduleItem.className = 'card mb-3';
                        scheduleItem.innerHTML = `
                            <div class="card-body">
                                <h5 class="card-title">${item.module_name || 'No Module'}</h5>
                                <p class="card-text">
                                    <strong>Lecturer:</strong> ${item.lecturer_name || 'Not Assigned'}<br>
                                    <strong>Facility:</strong> ${item.facility_name || 'Not Assigned'}<br>
                                    <strong>Groups:</strong> ${item.groups.length > 0 ? item.groups.join(', ') : 'No Groups'}
                                </p>
                            </div>
                        `;
                        scheduleList.appendChild(scheduleItem);
                    });
                })
                .catch(error => {
                    console.error('Error loading schedule:', error);
                    const scheduleList = document.getElementById('scheduleList');
                    scheduleList.innerHTML = `<div class="alert alert-danger">Error loading schedule: ${error.message}</div>`;
                });
        }
    </script>
</body>
</html>
