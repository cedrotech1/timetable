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
              </select>
              <div class="invalid-feedback small">Please select a day</div>
            </div>

            <div class="row g-2">
              <div class="col-6">
                <div class="time-selector">
                  <label for="compactStart" class="form-label small fw-bold">Start</label>
                  <select class="form-select form-select-sm" id="compactStart" required>
                    <option value="" selected disabled>From</option>
                    <optgroup label="Morning">
                      <option value="08:00">8:00 AM</option>
                      <option value="09:00">9:00 AM</option>
                      <option value="10:00">10:00 AM</option>
                      <option value="11:00">11:00 AM</option>
                    </optgroup>
                    <optgroup label="Afternoon">
                      <option value="12:00">12:00 PM</option>
                      <option value="13:00">1:00 PM</option>
                      <option value="14:00">2:00 PM</option>
                      <option value="15:00">3:00 PM</option>
                    </optgroup>
                    <optgroup label="Evening">
                      <option value="16:00">4:00 PM</option>
                      <option value="17:00">5:00 PM</option>
                      <option value="18:00">6:00 PM</option>
                      <option value="19:00">7:00 PM</option>
                    </optgroup>
                  </select>
                </div>
              </div>

              <div class="col-6">
                <div class="time-selector">
                  <label for="compactEnd" class="form-label small fw-bold">End</label>
                  <select class="form-select form-select-sm" id="compactEnd" required>
                    <option value="" selected disabled>To</option>
                    <optgroup label="Morning">
                      <option value="09:00">9:00 AM</option>
                      <option value="10:00">10:00 AM</option>
                      <option value="11:00">11:00 AM</option>
                      <option value="12:00">12:00 PM</option>
                    </optgroup>
                    <optgroup label="Afternoon">
                      <option value="13:00">1:00 PM</option>
                      <option value="14:00">2:00 PM</option>
                      <option value="15:00">3:00 PM</option>
                      <option value="16:00">4:00 PM</option>
                    </optgroup>
                    <optgroup label="Evening">
                      <option value="17:00">5:00 PM</option>
                      <option value="18:00">6:00 PM</option>
                      <option value="19:00">7:00 PM</option>
                      <option value="20:00">8:00 PM</option>
                      <option value="21:00">9:00 PM</option>
               
                    </optgroup>
                  </select>
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

      <h2 class="mt-4">Selected sessions</h2>
      <div id="scheduleList"></div>




<script>
  // LocalStorage key
  const STORAGE_KEY = 'compactSchedules';

  // Load schedules from localStorage or empty array
  let schedules = JSON.parse(localStorage.getItem(STORAGE_KEY)) || [];

  // Format time string (HH:mm) to 12h with AM/PM
  function formatTime(time24) {
    const [h, m] = time24.split(':').map(Number);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const hour12 = h % 12 === 0 ? 12 : h % 12;
    return `${hour12}:${m.toString().padStart(2,'0')} ${ampm}`;
  }

  // Render schedules list below the form
  function renderSchedules() {
    const container = document.getElementById('scheduleList');
    container.innerHTML = '';

    if (schedules.length === 0) {
      container.innerHTML = '<p class="text-muted">No schedules added yet.</p>';
      return;
    }

    schedules.forEach((sch, i) => {
      const div = document.createElement('div');
      div.className = 'schedule-card';
      div.innerHTML = `
        <strong>${sch.day}</strong>: ${formatTime(sch.start)} - ${formatTime(sch.end)}
        <button class="schedule-remove-btn" title="Remove schedule" aria-label="Remove schedule">&times;</button>
      `;
      div.querySelector('button').addEventListener('click', () => {
        schedules.splice(i, 1);
        saveSchedules();
        renderSchedules();
      });
      container.appendChild(div);
    });
  }

  // Save schedules to localStorage
  function saveSchedules() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(schedules));
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

    const day = form.compactDay.value;
    const start = form.compactStart.value;
    const end = form.compactEnd.value;

    if (!isValidTimeRange(start, end)) {
      alert('End time must be after start time.');
      return;
    }

    // Check if exact schedule already exists
    const exists = schedules.some(sch =>
      sch.day === day && sch.start === start && sch.end === end
    );
    if (exists) {
      alert('This schedule is already added.');
      return;
    }

    schedules.push({ day, start, end });
    saveSchedules();
    renderSchedules();

    // Reset form and validation state
    form.reset();
    form.classList.remove('was-validated');
  });

  // Initial render on page load
  renderSchedules();
</script>

</body>
</html>
