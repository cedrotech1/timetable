<?php
include('connection.php');
?>

<!-- Schedule Selection Modal -->
<div class="modal fade" id="scheduleModal" tabindex="-1" aria-labelledby="scheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scheduleModalLabel">Set Class Schedule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="sessionContainer">
                    <div class="session-entry mb-3 p-3 border rounded">
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label">Day</label>
                                <select class="form-select session-day" name="sessions[0][day]" required>
                                    <option value="">Select Day</option>
                                    <option value="Monday">Monday</option>
                                    <option value="Tuesday">Tuesday</option>
                                    <option value="Wednesday">Wednesday</option>
                                    <option value="Thursday">Thursday</option>
                                    <option value="Friday">Friday</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Start Time</label>
                                <input type="time" class="form-control session-start" name="sessions[0][start_time]" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">End Time</label>
                                <input type="time" class="form-control session-end" name="sessions[0][end_time]" required>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="button" class="btn btn-danger remove-session" style="display: none;">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn btn-secondary mt-2" id="addSession">
                    <i class="fas fa-plus"></i> Add Another Session
                </button>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="confirmSchedule">Confirm Schedule</button>
            </div>
        </div>
    </div>
</div>

<script>
// Session management
let sessionCount = 1;
let selectedSessions = [];

document.getElementById('addSession').addEventListener('click', function() {
    const container = document.getElementById('sessionContainer');
    const newSession = document.createElement('div');
    newSession.className = 'session-entry mb-3 p-3 border rounded';
    newSession.innerHTML = `
        <div class="row">
            <div class="col-md-4">
                <label class="form-label">Day</label>
                <select class="form-select session-day" name="sessions[${sessionCount}][day]" required>
                    <option value="">Select Day</option>
                    <option value="Monday">Monday</option>
                    <option value="Tuesday">Tuesday</option>
                    <option value="Wednesday">Wednesday</option>
                    <option value="Thursday">Thursday</option>
                    <option value="Friday">Friday</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Start Time</label>
                <input type="time" class="form-control session-start" name="sessions[${sessionCount}][start_time]" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">End Time</label>
                <input type="time" class="form-control session-end" name="sessions[${sessionCount}][end_time]" required>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="button" class="btn btn-danger remove-session">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `;
    container.appendChild(newSession);
    sessionCount++;

    // Show remove button on first session if there's more than one
    if (sessionCount > 1) {
        document.querySelector('.remove-session').style.display = 'block';
    }
});

// Handle session removal
document.addEventListener('click', function(e) {
    if (e.target.closest('.remove-session')) {
        const sessionEntry = e.target.closest('.session-entry');
        sessionEntry.remove();
        sessionCount--;

        // Hide remove button on first session if it's the only one
        if (sessionCount === 1) {
            document.querySelector('.remove-session').style.display = 'none';
        }

        // Update session indices
        document.querySelectorAll('.session-entry').forEach((entry, index) => {
            entry.querySelector('.session-day').name = `sessions[${index}][day]`;
            entry.querySelector('.session-start').name = `sessions[${index}][start_time]`;
            entry.querySelector('.session-end').name = `sessions[${index}][end_time]`;
        });
    }
});

// Validate session times
function validateSessionTimes() {
    const sessions = document.querySelectorAll('.session-entry');
    for (let session of sessions) {
        const startTime = session.querySelector('.session-start').value;
        const endTime = session.querySelector('.session-end').value;
        
        if (startTime && endTime && startTime >= endTime) {
            alert('End time must be after start time');
            return false;
        }
    }
    return true;
}

// Handle schedule confirmation
document.getElementById('confirmSchedule').addEventListener('click', function() {
    if (!validateSessionTimes()) {
        return;
    }

    // Collect all sessions
    selectedSessions = [];
    document.querySelectorAll('.session-entry').forEach(entry => {
        const day = entry.querySelector('.session-day').value;
        const startTime = entry.querySelector('.session-start').value;
        const endTime = entry.querySelector('.session-end').value;

        if (day && startTime && endTime) {
            selectedSessions.push({
                day: day,
                start_time: startTime,
                end_time: endTime
            });
        }
    });

    if (selectedSessions.length === 0) {
        alert('Please add at least one session');
        return;
    }

    // Update display
    const displayText = selectedSessions.map(session => 
        `${session.day} (${session.start_time} - ${session.end_time})`
    ).join(', ');
    document.getElementById('selectedScheduleDisplay').value = displayText;

    // Close modal
    bootstrap.Modal.getInstance(document.getElementById('scheduleModal')).hide();
});

// Reset sessions when modal is shown
document.getElementById('scheduleModal').addEventListener('show.bs.modal', function() {
    // Clear existing sessions except the first one
    const container = document.getElementById('sessionContainer');
    const firstSession = container.querySelector('.session-entry');
    container.innerHTML = '';
    container.appendChild(firstSession.cloneNode(true));
    sessionCount = 1;

    // Reset form fields
    const daySelect = container.querySelector('.session-day');
    const startTime = container.querySelector('.session-start');
    const endTime = container.querySelector('.session-end');
    daySelect.value = '';
    startTime.value = '';
    endTime.value = '';
});
</script> 