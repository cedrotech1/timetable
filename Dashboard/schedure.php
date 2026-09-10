<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Compact Session Time Scheduler</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<!-- FontAwesome for icons -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
<style>
  .schedule-card {
    position: relative;
    margin-bottom: 0.5rem;
    padding: 0.5rem 1rem;
    border: 1px solid #ddd;
    border-radius: 0.375rem;
    background:rgb(255, 255, 255) !important;
  }

  .schedule-remove-btn {
    position: absolute;
    top: 6px;
    right: 8px;
    border: none;
    background: transparent;
    color: #dc3545;
    font-size: 1.2rem;
    cursor: pointer;
  }
</style>
</head>
<body>




      <div class="card compact-time-card" style="border: none;">
        <div class="card-header page-title text-white py-2">
          <h6 class="mb-0"><i class="fas fa-clock me-1"></i>Session Time</h6>
        </div>
        <div class="card-body p-3 " style="border: none;">
          <form id="compactSessionForm" class="needs-validation" novalidate>
            <div class="mb-2">
              <label for="compactDay" class="form-label small fw-bold">Day</label>
              <select class="form-select form-select-sm" id="compactDay" required>
                <option value="" selected disabled>Select day</option>
                <option>Monday</option>
                <option>Tuesday</option>
                <option>Wednesday</option>
                <option>Thursday</option>
                <option>Friday</option>
                <option>Saturday</option>
                <option>Sunday</option>
              </select>
              <div class="invalid-feedback small">Please select a day</div>
            </div>

            <div id="predefinedSessions" class="mb-4">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <label class="form-label small fw-bold mb-0">Predefined Sessions</label>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addCustomSession">
                  <i class="fas fa-plus me-1"></i> Custom Time
                </button>
              </div>
              <div class="row g-2">
                <div class="col-12">
                  <button type="button" class="btn btn-outline-primary w-100 text-start mb-2 session-option" data-start="08:00" data-end="13:00">
                    <i class="far fa-sun me-2"></i> Morning Session
                    <small class="text-muted float-end">8:00 AM - 1:00 PM</small>
                  </button>
                </div>
                <div class="col-12">
                  <button type="button" class="btn btn-outline-primary w-100 text-start mb-2 session-option" data-start="14:00" data-end="17:00">
                    <i class="fas fa-sun me-2"></i> Afternoon Session
                    <small class="text-muted float-end">2:00 PM - 5:00 PM</small>
                  </button>
                </div>
                <div class="col-12">
                  <button type="button" class="btn btn-outline-primary w-100 text-start session-option" data-start="17:00" data-end="21:00">
                    <i class="far fa-moon me-2"></i> Evening Session
                    <small class="text-muted float-end">5:00 PM - 9:00 PM</small>
                  </button>
                </div>
              </div>
            </div>

            <div id="customTimeSection" class="d-none">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <label class="form-label small fw-bold mb-0">Custom Time</label>
                <button type="button" class="btn-close" id="closeCustomTime"></button>
              </div>
              <div class="row g-2">
                <div class="col-6">
                  <label for="customStart" class="form-label small fw-bold">Start Time</label>
                  <select class="form-select form-select-sm" id="customStart">
                    <option value="08:00">8:00 AM</option>
                    <option value="09:00">9:00 AM</option>
                    <option value="10:00">10:00 AM</option>
                    <option value="11:00">11:00 AM</option>
                    <option value="12:00">12:00 PM</option>
                    <option value="13:00">1:00 PM</option>
                    <option value="14:00">2:00 PM</option>
                    <option value="15:00">3:00 PM</option>
                    <option value="16:00">4:00 PM</option>
                    <option value="17:00">5:00 PM</option>
                    <option value="18:00">6:00 PM</option>
                    <option value="19:00">7:00 PM</option>
                    <option value="20:00">8:00 PM</option>
                  </select>
                </div>
                <div class="col-6">
                  <label for="customEnd" class="form-label small fw-bold">End Time</label>
                  <select class="form-select form-select-sm" id="customEnd">
                    <option value="09:00">9:00 AM</option>
                    <option value="10:00">10:00 AM</option>
                    <option value="11:00">11:00 AM</option>
                    <option value="12:00">12:00 PM</option>
                    <option value="13:00">1:00 PM</option>
                    <option value="14:00">2:00 PM</option>
                    <option value="15:00">3:00 PM</option>
                    <option value="16:00">4:00 PM</option>
                    <option value="17:00">5:00 PM</option>
                    <option value="18:00">6:00 PM</option>
                    <option value="19:00">7:00 PM</option>
                    <option value="20:00">8:00 PM</option>
                    <option value="21:00">9:00 PM</option>
                  </select>
                </div>
                <div class="col-12 mt-2">
                  <button type="button" class="btn btn-primary btn-sm w-100" id="addCustomTime">
                    <i class="fas fa-plus me-1"></i> Add Custom Session
                  </button>
                </div>
              </div>
            </div>

            <div class="d-grid mt-3">
              <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-check me-1"></i>save session/s
              </button>
            </div>
          </form>
        </div>
      </div>

      <h2 class="mt-4">Current Session</h2>
      <div id="scheduleList"></div>

      <!-- Toast Notification -->
      <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
        <div id="scheduleToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
          <div class="toast-header">
            <strong class="me-auto">Success</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
          </div>
          <div class="toast-body"></div>
        </div>
      </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // LocalStorage key for compact schedules
  const STORAGE_KEY = 'compactSchedules';
  
  // Load schedules from localStorage or empty array
  let currentSchedules = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
  // We'll only work with the first schedule in the array
  let currentSchedule = currentSchedules[0] || null;
  
  // Function declarations (hoisted)
  function formatTime(time24) {
    if (!time24) return '';
    const [h, m] = time24.split(':').map(Number);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const hour12 = h % 12 === 0 ? 12 : h % 12;
    return `${hour12}:${m.toString().padStart(2,'0')} ${ampm}`;
  }

  // Toggle form visibility
  function toggleFormVisibility(show) {
    const formSection = document.querySelector('.compact-time-card');
    const changeBtn = document.getElementById('changeSessionBtn');
    
    if (show) {
      formSection.classList.remove('d-none');
      if (changeBtn) changeBtn.classList.add('d-none');
    } else {
      formSection.classList.add('d-none');
      if (!changeBtn) {
        // Create change button if it doesn't exist
        const container = document.getElementById('scheduleList');
        const changeBtn = document.createElement('button');
        changeBtn.id = 'changeSessionBtn';
        changeBtn.className = 'btn btn-outline-primary btn-sm mt-3';
        changeBtn.innerHTML = '<i class="fas fa-edit me-1"></i> Change Session';
        changeBtn.addEventListener('click', () => toggleFormVisibility(true));
        container.after(changeBtn);
      } else {
        changeBtn.classList.remove('d-none');
      }
    }
  }

  // Render current schedule
  function renderSchedule() {
    const container = document.getElementById('scheduleList');
    container.innerHTML = '';

    if (!currentSchedule) {
      container.innerHTML = '<p class="text-muted">No schedule set yet. Add one using the form above.</p>';
      toggleFormVisibility(true);
      return;
    }

    const div = document.createElement('div');
    div.className = 'schedule-card';
    div.innerHTML = `
      <strong>${currentSchedule.day}</strong>: ${formatTime(currentSchedule.start)} - ${formatTime(currentSchedule.end)}
      <button class="schedule-remove-btn" title="Clear schedule" aria-label="Clear schedule">&times;</button>
    `;
    div.querySelector('button').addEventListener('click', clearSchedule);
    container.appendChild(div);
    
    // Hide form after setting a schedule
    toggleFormVisibility(false);
  }
  
  // Clear the current schedule
  function clearSchedule() {
    currentSchedule = null;
    localStorage.setItem(STORAGE_KEY, '[]');
    localStorage.removeItem('selectedFacility');
    window.dispatchEvent(new CustomEvent('scheduleUpdated', {
      detail: { schedule: null }
    }));
    renderSchedule();
    showToast('Schedule cleared');
    toggleFormVisibility(true);
  }

  // Save current schedule to localStorage
  function saveSchedule() {
    if (currentSchedule) {
      // Always save as an array with the current schedule
      localStorage.setItem(STORAGE_KEY, JSON.stringify([currentSchedule]));
    } else {
      // If no schedule, save empty array
      localStorage.setItem(STORAGE_KEY, '[]');
    }

    // Session changed → previous facility choice may no longer be free
    localStorage.removeItem('selectedFacility');
    
    // Notify facility selector to reload available rooms
    window.dispatchEvent(new CustomEvent('scheduleUpdated', {
      detail: { schedule: currentSchedule }
    }));
  }
  
  // Show toast notification
  function showToast(message, type = 'success') {
    const toastEl = document.getElementById('scheduleToast');
    const toastBody = toastEl.querySelector('.toast-body');
    const toastHeader = toastEl.querySelector('.toast-header');
    
    // Update toast style based on type
    if (type === 'error') {
      toastHeader.className = 'toast-header bg-danger text-white';
    } else {
      toastHeader.className = 'toast-header bg-success text-white';
    }
    
    toastBody.textContent = message;
    const toast = new bootstrap.Toast(toastEl);
    toast.show();
  }

  // Set the current schedule
  function setSchedule(day, start, end) {
    currentSchedule = { day, start, end };
    saveSchedule();
    renderSchedule();
    showToast('Schedule updated!');
  }

  // Validate start < end time (HH:mm)
  function isValidTimeRange(start, end) {
    return start < end;
  }

  // Form submission handler
  document.getElementById('compactSessionForm').addEventListener('submit', e => {
    e.preventDefault();

    const form = e.target;
    if (!form.checkValidity()) {
      form.classList.add('was-validated');
      return;
    }

    const day = document.getElementById('compactDay').value;
    const start = document.getElementById('customStart').value;
    const end = document.getElementById('customEnd').value;

    if (!isValidTimeRange(start, end)) {
      showToast('End time must be after start time', 'error');
      return;
    }
    
    setSchedule(day, start, end);
    
    // Reset form and validation state
    form.reset();
    form.classList.remove('was-validated');
    
    // Go back to predefined sessions view
    document.getElementById('predefinedSessions').classList.remove('d-none');
    document.getElementById('customTimeSection').classList.add('d-none');
  });

  // Toggle between predefined and custom time sections
  document.getElementById('addCustomSession').addEventListener('click', function() {
    document.getElementById('predefinedSessions').classList.add('d-none');
    document.getElementById('customTimeSection').classList.remove('d-none');
  });

  document.getElementById('closeCustomTime').addEventListener('click', function() {
    document.getElementById('predefinedSessions').classList.remove('d-none');
    document.getElementById('customTimeSection').classList.add('d-none');
  });

  // Handle predefined session selection
  document.querySelectorAll('.session-option').forEach(button => {
    button.addEventListener('click', function() {
      const day = document.getElementById('compactDay').value;
      const start = this.dataset.start;
      const end = this.dataset.end;
      
      if (!day) {
        alert('Please select a day first');
        document.getElementById('compactDay').focus();
        return;
      }
      
      setSchedule(day, start, end);
    });
  });

  // Handle custom time addition
  document.getElementById('addCustomTime').addEventListener('click', function() {
    const day = document.getElementById('compactDay').value;
    const start = document.getElementById('customStart').value;
    const end = document.getElementById('customEnd').value;
    
    if (!day) {
      alert('Please select a day first');
      document.getElementById('compactDay').focus();
      return;
    }
    
    if (start >= end) {
      alert('End time must be after start time');
      return;
    }
    
    setSchedule(day, start, end);
    
    // Reset and go back to predefined view
    document.getElementById('predefinedSessions').classList.remove('d-none');
    document.getElementById('customTimeSection').classList.add('d-none');
  });
  
  // Initial render on page load
  document.addEventListener('DOMContentLoaded', function() {
    renderSchedule();
    
    // Close form when clicking outside
    document.addEventListener('click', function(e) {
      const formSection = document.querySelector('.compact-time-card');
      const changeBtn = document.getElementById('changeSessionBtn');
      
      if (currentSchedule && !formSection.contains(e.target) && !changeBtn?.contains(e.target)) {
        toggleFormVisibility(false);
      }
    });
  });
  
  renderSchedule();
</script>

</body>
</html>
