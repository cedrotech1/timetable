<?php
session_start();
include ('connection.php');

// get system settings
$system_data_setting = "SELECT * FROM system LIMIT 1";
$system_data_result = mysqli_query($connection, $system_data_setting);
$accademic_year_id = null;
$semester = null;
if ($system_data_result && mysqli_num_rows($system_data_result)) {
  $system_data = mysqli_fetch_assoc($system_data_result);
  $accademic_year_id = $system_data['accademic_year_id'];
  $semester = $system_data['semester'];
}

// get academic year label
$academic_year_label = '';
if ($accademic_year_id) {
  $academic_years_query = "SELECT * FROM academic_year WHERE id = '$accademic_year_id' ORDER BY year_label DESC LIMIT 1";
  $academic_years_result_label = mysqli_query($connection, $academic_years_query);
  if ($academic_years_result_label && mysqli_num_rows($academic_years_result_label)) {
    $academic_year = mysqli_fetch_assoc($academic_years_result_label);
    $academic_year_label = $academic_year['year_label'];
  }
}

$user_id = $_SESSION['id'];
$user_role = $_SESSION['role'] ?? '';
$canAccessAllSchools = in_array($user_role, ['admin', 'registrar_office'], true);

// Admin and registrar_office can add teaching plans across all schools
if (!$canAccessAllSchools) {
    $stmt = $connection->prepare("SELECT school FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $school = $result->fetch_assoc();
    $school_id = $school ? $school['school'] : null;
    $schoolId = $school_id;
    
    if (!$schoolId) {
        echo json_encode(['success' => false, 'message' => 'No school ID found for your account']);
        exit;
    }
} else {
    $schoolId = null; // All schools
}
// select school name
$school_name = '';
if ($schoolId) {
    $school_name_query = "SELECT * FROM school WHERE id = '$schoolId' ORDER BY name DESC LIMIT 1";
    $school_name_result = mysqli_query($connection, $school_name_query);
    if ($school_name_result && mysqli_num_rows($school_name_result)) {
        $school_name = mysqli_fetch_assoc($school_name_result);
        $school_name = $school_name['name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
  <title>UR-TIMETABLE</title>
  <link href="assets/img/icon1.png" rel="icon">
  <link href="assets/img/icon1.png" rel="apple-touch-icon">

  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <style>
    .bg-primary { background-color: rgb(3,31,80) !important; }
    .btn-primary { background-color: rgb(3,31,80) !important; }

    /* Fire modal header colors */
    .fire-success { background: #198754; color:#fff; }
    .fire-error   { background: #dc3545; color:#fff; }
    .fire-warning { background: #ffc107; color:#212529; }
    .fire-info    { background: #0d6efd; color:#fff; }

    .fire-modal .modal-header { align-items: center; }
    .fire-modal .modal-title i { margin-right: .5rem; }
    .conflict-list h6 { margin-top: .75rem; }
    .conflict-list ul { margin-bottom: .25rem; }
    .small-muted { font-size: .9rem; color:#6c757d; }
    .mono { font-family: ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace; }

    h2 { font-size: 17px !important;
    background-color: rgb(3,31,80) !important;
    color:#fff !important;
padding: 6px !important;
margin-bottom: 10px !important;
border-radius: 5px !important; }
.page-title{
    background-color: rgb(3,31,80) !important;
    font-size: 22px !important;
    color:#fff !important;
padding: 10px !important;
margin-bottom: 10px !important;
border-radius: 5px !important;
}
.page-title h2{
    font-size: 22px !important;
}

.save-btn .btn-success{
    background-color: rgb(3,31,80) !important;
}
.btn-success:hover{
    background-color: rgb(63, 143, 84) !important;
}
.page-title .btn-reset-data {
    white-space: nowrap;
    margin: 6px 10px 6px 0;
}
h4{
    font-size: 17px !important;
    background-color: rgb(3,31,80) !important;
    color:#fff !important;
padding: 6px !important;
margin-bottom: 10px !important;
border-radius: 5px !important;
width: 7cm !important;
}
  </style>
</head>
<body>
<?php
include ('./includes/header.php');
include ('./includes/menu.php');
?>

<main id="main" class="main" style="background-color:rgb(245, 245, 245);">
  <div class="pagetitle">
    <h1>Timetable</h1>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
        <li class="breadcrumb-item active">Timetable</li>
      </ol>
    </nav>
  </div>

  <div class="">
    <div class="card page-title d-flex flex-row flex-wrap align-items-center justify-content-between gap-2">
      <h2 class="text-light mb-0">
        Schedule Timetable for academic year: <?php echo htmlspecialchars($academic_year_label ?: '-'); ?>, 
        Semester <?php echo htmlspecialchars($semester ?: '-'); ?>
        <?php if ($canAccessAllSchools): ?>
          (All Schools)
        <?php else: ?>
          for <?php echo htmlspecialchars($school_name ?: 'Assigned School'); ?>
        <?php endif; ?>
      </h2>
      <div class="d-flex gap-2 align-items-center">
        <a href="timetable_set_bulk.php" class="btn btn-outline-light btn-sm">
          <i class="bi bi-table"></i> Bulk plan
        </a>
        <button type="button" id="btnDeleteDataTop" class="btn btn-danger btn-reset-data">
          <i class="bi bi-trash"></i> Reset Data
        </button>
      </div>
    </div>

   

    <div class="row">
      <!-- Session scheduler -->
        <div class="col-md-8">
        <div class="card" style="border: none;">
          <div class="card-body">
            <?php include ('./group_selector.php'); ?>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <?php include ('./schedure.php'); ?>
      </div>
      

      <!-- Group selector -->
     
      </div>

      <!-- Facility select -->
          <!-- Module select -->
      <div class="col-md-12 mt-5">
        <div class="card" style="border: none;">
          <div class="card-body">
            <?php include ('./module_selector.php'); ?>
          </div>
        </div>
      </div>

      <!-- Lecturer select -->
      <div class="col-md-12">
        <div class="card" style="border: none;">
          <div class="card-body">
            <?php include ('./select_lecturers.php'); ?>
               <!-- SAVE BUTTON -->
  
               
            </div>

          
        </div>  
      </div>
      
        
            <?php include ('./facility_selector.php'); ?>
          
      <br>
      <div class="d-flex m-2">
                <div class="save-btn me-2">
                    <button id="btnSaveData" class="btn btn-success col-12">
                        <i class="bi bi-save"></i> Save Timetable
                    </button>
                </div>
                <div class="delete-btn">
                    <button id="btnDeleteData" class="btn btn-danger col-12 btn-reset-data">
                        <i class="bi bi-trash"></i> Reset Data
                    </button>
                </div>

   
    </div>
  </div>

  <script>
// Safe DataTables initialization
$(document).ready(function() {
    // Only initialize DataTables if the table exists and has the right structure
    if ($.fn.DataTable && $('table.display').length) {
        $('table.display').DataTable();
    }
});

document.querySelectorAll('.btn-reset-data').forEach(function (btn) {
    btn.addEventListener('click', function () {
        if (confirm('Are you sure you want to reset all data?')) {
            // Save the selectedGroups before clearing
            const selectedGroups = localStorage.getItem('selectedGroups');

            // Clear all localStorage
            localStorage.clear();

            // Restore selectedGroups if it exists
            if (selectedGroups) {
                localStorage.setItem('selectedGroups', selectedGroups);
            }

            location.reload();
        }
    });
});
</script>


  <!-- FIRE MODAL (notifications) -->
  <div class="modal fade fire-modal" id="notifyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div id="notifyHeader" class="modal-header">
          <h5 class="modal-title d-flex align-items-center" id="notifyTitle">
            <i class="bi bi-info-circle"></i><span>Notice</span>
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div id="notifyBody" class="modal-body">
          <!-- dynamic -->
        </div>
        <div class="modal-footer">
          <button id="notifyCloseBtn" type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button id="notifyPrimaryBtn" type="button" class="btn btn-primary d-none" data-bs-dismiss="modal">OK</button>
        </div>
      </div>
    </div>
  </div>

</main>

<?php include ('./includes/footer.php'); ?>

<a href="#" class="back-to-top d-flex align-items-center justify-content-center">
  <i class="bi bi-arrow-up-short"></i>
</a>

<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

<script>
(function(){
  const AY = <?php echo json_encode($accademic_year_id); ?>;
  const SEM = <?php echo json_encode($semester); ?>;

  // -------- Fire Modal helpers --------
  function showFireModal(type, title, htmlBody, primaryBtnText = null) {
    const header = document.getElementById('notifyHeader');
    const titleEl = document.getElementById('notifyTitle');
    const bodyEl = document.getElementById('notifyBody');
    const primaryBtn = document.getElementById('notifyPrimaryBtn');

    header.classList.remove('fire-success','fire-error','fire-warning','fire-info');
    let icon = 'bi-info-circle', hdr = 'fire-info';
    if (type === 'success') { icon = 'bi-check-circle'; hdr = 'fire-success'; }
    if (type === 'error')   { icon = 'bi-x-circle';     hdr = 'fire-error';   }
    if (type === 'warning') { icon = 'bi-exclamation-triangle'; hdr = 'fire-warning'; }

    header.classList.add(hdr);
    titleEl.innerHTML = `<i class="bi ${icon}"></i><span>${title}</span>`;
    bodyEl.innerHTML = htmlBody || '';

    if (primaryBtnText) {
      primaryBtn.textContent = primaryBtnText;
      primaryBtn.classList.remove('d-none');
    } else {
      primaryBtn.classList.add('d-none');
    }

    new bootstrap.Modal(document.getElementById('notifyModal')).show();
  }

  function safeParse(key) {
    try {
      const v = localStorage.getItem(key);
      return v ? JSON.parse(v) : null;
    } catch { return null; }
  }

  function collectPayload() {
  const selectedGroups = safeParse('selectedGroups') || [];
  const selectedGroupIds = safeParse('selectedGroupIds') 
      || selectedGroups.map(g => g.id);

  const lecturers = safeParse('selectedLecturers') || { leader: null, others: [] };

  return {
    selectedFacilityId: safeParse('selectedFacility')?.id || null,
    selectedGroupIds: selectedGroupIds,
    selectedModuleId: safeParse('selectedModule')?.id || null,
    moduleLeaderId: lecturers.leader?.id || null,
    otherLecturerIds: (lecturers.others || []).map(o => o.id),
    schedule: safeParse('compactSchedules') || [],
    academic_year_id: AY,
    semester: SEM
  };
}


  // Pretty conflict renderer
  function renderConflicts(conf) {
    const selectedGroups = safeParse('selectedGroups') || [];
    const groupNameById = Object.fromEntries(selectedGroups.map(g => [String(g.id), g.name]));
    const selectedLecturers = safeParse('selectedLecturers') || {leader:null,others:[]};
    const lectNameById = {};
    if (selectedLecturers.leader) lectNameById[String(selectedLecturers.leader.id)] = selectedLecturers.leader.names;
    (selectedLecturers.others||[]).forEach(o => lectNameById[String(o.id)] = o.names);

    const f = conf.facility || [];
    const g = conf.groups || {};
    const l = conf.lecturers || {};

    const li = (txt) => `<li class="mono">${txt}</li>`;
    const fmtTime = (t) => t?.slice(0,5) || t;

    let html = `<div class="conflict-list">`;

    if (f.length) {
      html += `<h6><i class="bi bi-building me-1"></i> Facility conflicts</h6><ul>`;
      f.forEach(r => {
        html += li(`Day ${r.day}: ${fmtTime(r.start_time)} - ${fmtTime(r.end_time)} (timetable #${r.timetable_id})`);
      });
      html += `</ul>`;
    }

    const gKeys = Object.keys(g);
    if (gKeys.length) {
      html += `<h6 class="mt-2"><i class="bi bi-people me-1"></i> Group conflicts</h6>`;
      gKeys.forEach(k => {
        const arr = g[k] || [];
        const name = groupNameById[String(k)] || `Group ${k}`;
        html += `<div class="small fw-bold">${name}</div><ul>`;
        arr.forEach(r => {
          html += li(`Day ${r.day}: ${fmtTime(r.start_time)} - ${fmtTime(r.end_time)} (timetable #${r.timetable_id})`);
        });
        html += `</ul>`;
      });
    }

    const lKeys = Object.keys(l);
    if (lKeys.length) {
      html += `<h6 class="mt-2"><i class="bi bi-person-badge me-1"></i> Lecturer conflicts</h6>`;
      lKeys.forEach(k => {
        const arr = l[k] || [];
        const name = lectNameById[String(k)] || `Lecturer ${k}`;
        html += `<div class="small fw-bold">${name}</div><ul>`;
        arr.forEach(r => {
          html += li(`Day ${r.day}: ${fmtTime(r.start_time)} - ${fmtTime(r.end_time)} (timetable #${r.timetable_id})`);
        });
        html += `</ul>`;
      });
    }

    html += `<div class="small-muted mt-2">No records were saved due to conflicts.</div>`;
    html += `</div>`;
    return html;
  }

  // Format preview HTML
  function formatPreview(payload) {
    const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    
    // Get all data from local storage
    const selectedModule = safeParse('selectedModule') || {};
    const selectedFacility = safeParse('selectedFacility') || {};
    const selectedGroups = safeParse('selectedGroups') || [];
    const selectedLecturers = safeParse('selectedLecturers') || { leader: null, others: [] };
    
    // Create array of all lecturers (leader first, then others)
    const allLecturers = [];
    if (selectedLecturers.leader) allLecturers.push({...selectedLecturers.leader, isLeader: true});
    if (selectedLecturers.others && selectedLecturers.others.length) {
      allLecturers.push(...selectedLecturers.others.map(lec => ({...lec, isLeader: false})));
    }
    
    let html = `
      <style>
        .info-card {
          border-radius: 8px;
          box-shadow: 0 2px 4px rgba(0,0,0,0.05);
          margin-bottom: 1rem;
          overflow: hidden;
        }
        .info-card .card-header {
          font-weight: 600;
          padding: 0.75rem 1rem;
        }
        .info-card .card-body {
          padding: 1rem;
        }
        .lecturer-badge {
          display: flex;
          align-items: center;
          padding: 0.5rem;
          border-radius: 6px;
          background: #f8f9fa;
          margin-bottom: 0.5rem;
        }
        .lecturer-badge.leader {
          border-left: 3px solid #0d6efd;
        }
        .lecturer-badge .role {
          font-size: 0.75rem;
          color: #6c757d;
          margin-left: auto;
        }
        .module-code {
          font-family: monospace;
          background: #f1f1f1;
          padding: 2px 6px;
          border-radius: 4px;
          font-size: 0.9em;
        }
      </style>
      
      <div class="preview-container">
        <!-- Module Card -->
        <div class="info-card card mb-3">
          <div class="card-header bg-light d-flex align-items-center">
            <i class="bi bi-book me-2"></i>
            Module Information
          </div>
          <div class="card-body">
            <h5 class="card-title mb-1">${selectedModule.name || 'N/A'}</h5>
            <p class="text-muted mb-2">
              <span class="module-code">${selectedModule.code || 'N/A'}</span>
              <span class="ms-2">• ${selectedModule.credits || '0'} credits</span>
            </p>
            <div class="small text-muted">
              Year ${selectedModule.year || 'N/A'} • Semester ${selectedModule.semester || 'N/A'}
            </div>
          </div>
        </div>
        
        <!-- Facility Card -->
        <div class="info-card card mb-3">
          <div class="card-header bg-light d-flex align-items-center">
            <i class="bi bi-building me-2"></i>
            Facility
          </div>
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <h5 class="mb-1">${selectedFacility.name || 'N/A'}</h5>
                <p class="text-muted mb-0">${selectedFacility.type || 'N/A'}</p>
              </div>
              <span class="badge bg-secondary">${selectedFacility.capacity || '?'} seats</span>
            </div>
            <div class="small text-muted mt-2">
              <i class="bi bi-geo-alt"></i> ${selectedFacility.site_name || 'Location not specified'}
            </div>
          </div>
        </div>
        
        <!-- Lecturers Card -->
        <div class="info-card card mb-3">
          <div class="card-header bg-light d-flex align-items-center">
            <i class="bi bi-people me-2"></i>
            Lecturers (${allLecturers.length})
          </div>
          <div class="card-body p-0">
            ${allLecturers.length > 0 
              ? allLecturers.map(lect => `
                  <div class="lecturer-badge ${lect.isLeader ? 'leader' : ''} mx-3 mt-3">
                    <i class="bi bi-person-fill me-2"></i>
                    <div>
                      <div>${lect.names || 'N/A'}</div>
                      <div class="small text-muted">${lect.email || ''}</div>
                    </div>
                    ${lect.isLeader ? '<span class="role">Module Leader</span>' : ''}
                  </div>
                `).join('')
              : `<div class="p-3 text-muted">No lecturers selected</div>`
            }
          </div>
        </div>
        
        <!-- Groups Card -->
        <div class="info-card card mb-3">
          <div class="card-header bg-light d-flex align-items-center">
            <i class="bi bi-collection me-2"></i>
            Groups (${selectedGroups.length})
          </div>
          <div class="card-body">
            ${selectedGroups.length > 0 
              ? selectedGroups.map(group => `
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                      <strong>${group.name || 'Unnamed Group'}</strong>
                      <div class="small text-muted">
                        ${group.programName || ''} 
                        ${group.intakeYear ? `• Intake ${group.intakeMonth}/${group.intakeYear}` : ''}
                      </div>
                    </div>
                    <span class="badge bg-light text-dark">${group.size || '?'} students</span>
                  </div>
                `).join('')
              : '<div class="text-muted">No groups selected</div>'
            }
          </div>
        </div>
        <hr>
        <h6>Scheduled Sessions:</h6>
        <ul class="list-group mb-3">
    `;

    // Debug: Log the schedule data
    console.log('Schedule data:', payload.schedule);
    
    if (payload.schedule && payload.schedule.length > 0) {
      payload.schedule.forEach((session, index) => {
        // Get day name from the session data (it's already a string like 'Monday')
        const dayName = session.day || 'N/A';
        // Format time (use 'start' and 'end' properties from the schedule data)
        const startTime = session.start ? session.start.toString().substring(0, 5) : 'N/A';
        const endTime = session.end ? session.end.toString().substring(0, 5) : 'N/A';
        
        html += `
          <li class="list-group-item">
            <div><strong>Session ${index + 1}:</strong></div>
            <div>Day: ${dayName}</div>
            <div>Time: ${startTime} - ${endTime}</div>
          </li>
        `;
      });
    } else {
      html += `
        <li class="list-group-item">
          <div class="text-muted">No scheduled sessions found</div>
        </li>
      `;
    }

    html += `
        </ul>
        <div class="alert alert-info">
          <i class="bi bi-info-circle"></i> Please review the details above before saving.
        </div>
      </div>
    `;
    return html;
  }

  // Save handler with preview
  document.getElementById('btnSaveData').addEventListener('click', async () => {
    const payload = collectPayload();

    // quick front validations
    if (!payload.selectedModuleId) {
      return showFireModal('warning', 'Missing Module', '<p>Please select a module.</p>');
    }
    // if (!payload.moduleLeaderId) {
    //   return showFireModal('warning', 'Missing Module Leader', '<p>Please select a module leader.</p>');
    // }
    if (!payload.selectedFacilityId) {
      return showFireModal('warning', 'Missing Facility', '<p>Please select a facility.</p>');
    }
    if (!payload.selectedGroupIds.length) {
      return showFireModal('warning', 'No Groups Selected', '<p>Please select at least one group.</p>');
    }
    if (!payload.schedule.length) {
      return showFireModal('warning', 'No Session Times', '<p>Please add at least one session time.</p>');
    }

    // Show preview first
    const previewModal = new bootstrap.Modal(document.getElementById('previewModal'));
    document.getElementById('previewContent').innerHTML = formatPreview(payload);
    
    // Store the payload in the confirm button
    document.getElementById('confirmSave').onclick = async function() {
      previewModal.hide();
      
      try {
        const res = await fetch('save_timetable.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.status === 'success') {
          // Save selectedGroups before clearing
          const selectedGroups = localStorage.getItem('selectedGroups');
          
          // Clear all localStorage
          localStorage.clear();
          
          // Restore selectedGroups if it exists
          if (selectedGroups) {
            localStorage.setItem('selectedGroups', selectedGroups);
          }
          
          showFireModal('success', 'Scheduled Successfully', `
            <p>The timetable has been created successfully.</p>
            <div class="mono">Timetable ID: <strong>#${data.timetable_id}</strong></div>
          `, 'Great!');
          
          // refresh after 2 seconds
          setTimeout(() => {
            location.reload();
          }, 2000);
        } else if (data.status === 'conflict') {
          showFireModal('error', 'Conflicts Detected', renderConflicts(data.conflicts));
        } else {
          showFireModal('error', 'Save Failed', `<p>${data.message || 'Unknown error'}</p>`);
        }
      } catch (e) {
        console.error(e);
        showFireModal('error', 'Network Error', '<p>Failed to save timetable. Please try again.</p>');
      }
    };
    
    previewModal.show();
  });
})();
</script>
  <!-- Preview Modal -->
  <div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title" id="previewModalLabel">
            <i class="bi bi-eye me-2"></i>Preview Timetable
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="previewContent">
          <!-- Preview content will be inserted here -->
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="bi bi-x-circle"></i> Cancel
          </button>
          <button type="button" class="btn btn-primary" id="confirmSave">
            <i class="bi bi-check-circle"></i> Confirm & Save
          </button>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
