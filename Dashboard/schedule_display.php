<?php
// This file contains the schedule display functionality
?>

<div class="timetable-container">
    <h4>Current Schedule</h4>
    <div id="scheduleList"></div>
</div>

<script>
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
                        <h5 class="card-title">${item.module_code} - ${item.module_name}</h5>
                        <p class="card-text">
                            <strong>Lecturer:</strong> ${item.lecturer_name}<br>
                            <strong>Facility:</strong> ${item.facility_name} (${item.facility_type})<br>
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

// Load initial schedule when the page loads
document.addEventListener('DOMContentLoaded', function() {
    loadSchedule();
});
</script> 