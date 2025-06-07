<?php
// This file contains the schedule display functionality
?>

<div class="timetable-container">
    <h4>Current Schedule</h4>
    <div class="mb-3">
        <div class="row">
            <div class="col-md-6">
                <select class="form-select" id="displayAcademicYear">
                    <option value="">All Academic Years</option>
                    <?php
                    $years_query = "SELECT * FROM academic_year ORDER BY year_label DESC";
                    $years_result = mysqli_query($connection, $years_query);
                    while($year = mysqli_fetch_assoc($years_result)) {
                        echo "<option value='{$year['id']}'>{$year['year_label']}</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-6">
                <select class="form-select" id="displaySemester">
                    <option value="">All Semesters</option>
                    <option value="1">Semester 1</option>
                    <option value="2">Semester 2</option>
                </select>
            </div>
        </div>
    </div>
    <div id="scheduleList"></div>
</div>

<script>
function loadSchedule() {
    const academicYear = document.getElementById('displayAcademicYear').value;
    const semester = document.getElementById('displaySemester').value;

    // Build the URL with optional parameters
    let url = 'get_schedule.php';
    const params = new URLSearchParams();
    if (academicYear) params.append('academic_year', academicYear);
    if (semester) params.append('semester', semester);
    if (params.toString()) url += '?' + params.toString();

    fetch(url)
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => {
                    throw new Error(err.message || 'Failed to load schedule');
                });
            }
            return response.json();
        })
        .then(data => {
            const scheduleList = document.getElementById('scheduleList');
            scheduleList.innerHTML = '';

            // Check if there's an error
            if (data.error) {
                scheduleList.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                return;
            }

            // Get the schedule array
            const schedule = Array.isArray(data) ? data : (data.schedule || []);

            if (schedule.length === 0) {
                scheduleList.innerHTML = '<div class="alert alert-info">No classes scheduled</div>';
                return;
            }

            // Group schedules by academic year and semester
            const groupedSchedules = schedule.reduce((acc, item) => {
                const key = `${item.academic_year_label} - Semester ${item.semester}`;
                if (!acc[key]) acc[key] = [];
                acc[key].push(item);
                return acc;
            }, {});

            // Create sections for each academic year/semester combination
            Object.entries(groupedSchedules).forEach(([period, items]) => {
                const periodHeader = document.createElement('div');
                periodHeader.className = 'period-header mb-3';
                periodHeader.innerHTML = `<h5 class="text-primary">${period}</h5>`;
                scheduleList.appendChild(periodHeader);

                items.forEach(item => {
                    const scheduleItem = document.createElement('div');
                    scheduleItem.className = 'card mb-3';
                    
                    // Format sessions
                    const sessions = item.sessions ? item.sessions.map(session => 
                        `${session.day}: ${session.start_time} - ${session.end_time}`
                    ).join('<br>') : 'No sessions scheduled';

                    scheduleItem.innerHTML = `
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">${item.module_code} - ${item.module_name}</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="card-text">
                                        <strong>Lecturer:</strong> ${item.lecturer_name}<br>
                                        <strong>Facility:</strong> ${item.facility_name} (${item.facility_type})<br>
                                        <strong>Location:</strong> ${item.facility_location || 'N/A'}<br>
                                        <strong>Capacity:</strong> ${item.facility_capacity || 'N/A'}
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p class="card-text">
                                        <strong>Groups:</strong><br>
                                        ${item.groups.length > 0 ? item.groups.join(', ') : 'No Groups'}<br>
                                        <strong>Program:</strong> ${item.program_name || 'N/A'}<br>
                                        <strong>Academic Year:</strong> ${item.academic_year_label}<br>
                                        <strong>Semester:</strong> ${item.semester}
                                    </p>
                                </div>
                            </div>
                            <div class="mt-3">
                                <strong>Schedule:</strong><br>
                                <div class="ms-3">
                                    ${sessions}
                                </div>
                            </div>
                        </div>
                    `;
                    scheduleList.appendChild(scheduleItem);
                });
            });
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('scheduleList').innerHTML = 
                `<div class="alert alert-danger">Error loading schedule: ${error.message}</div>`;
        });
}

// Add event listeners for academic year and semester changes
document.getElementById('displayAcademicYear').addEventListener('change', loadSchedule);
document.getElementById('displaySemester').addEventListener('change', loadSchedule);

// Initial load
document.addEventListener('DOMContentLoaded', loadSchedule);
</script>

<style>
.timetable-container {
    margin: 20px;
    padding: 20px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
}

.card {
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.card-header {
    border-bottom: none;
}

.card-body {
    padding: 1.5rem;
}

.card-text {
    margin-bottom: 0.5rem;
}

.card-text strong {
    color: #495057;
}

.ms-3 {
    margin-left: 1rem;
}

.period-header {
    border-bottom: 2px solid #e9ecef;
    padding-bottom: 0.5rem;
}

.period-header h5 {
    margin: 0;
    font-weight: 600;
}
</style> 