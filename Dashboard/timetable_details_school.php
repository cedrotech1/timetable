<?php
session_start();
include('connection.php');

$timetableId = isset($_GET['id']) ? intval($_GET['id']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta content="" name="description">
  <meta content="" name="keywords">

  <!-- Favicons -->
  <link href="assets/img/icon1.png" rel="icon">
  <link href="assets/img/icon1.png" rel="apple-touch-icon">

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

  <!-- Template Main CSS File -->
  <link href="assets/css/style.css" rel="stylesheet">

  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">

<link rel="stylesheet" href="./assets/css/style.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">


<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- DataTables (Bootstrap 5 styling) -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<style>
    body { background-color: #f8f9fa; }
    .card { margin-bottom: 20px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
    .card-header { font-weight: bold; background: rgb(3,31,80); color: white; }
    .lecturer-card, .group-card { background: #f1f3f5; padding: 8px; border-radius: 8px; margin-bottom: 5px; }
    .module-card { background: #f1f3f5; padding: 8px; border-radius: 8px; margin-bottom: 5px; }
    .btn-primary { background-color: rgb(3,31,80) !important; }
    .dt-control { cursor: pointer; }
    .delete-group { 
    cursor: pointer;
    display: block;              /* so text-align works on its own line */
    text-align: end !important;  /* force right alignment */
    margin-left: auto !important; /* push it to the right if in flex */
}

</style>
</head>
<body>

<?php include('./includes/header.php'); ?>
    <?php include('./includes/menu.php'); ?>

    <main id="main" class="main">
    
<div class="container mt-4">
    <div id="alert-container"></div>
    <div id="timetable-container" class="row"></div>
</div>


<!-- Module Picker Modal -->
<div class="modal fade" id="modulePickerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header" style="background: rgb(3,31,80); color: #fff;">
        <h5 class="modal-title">Select Module</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-2 mb-3">
          <div class="col-12 col-md-4">
            <label class="form-label mb-1">Program</label>
            <select id="moduleFilterProgram" class="form-select form-select-sm">
              <option value="">All Programs</option>
            </select>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label mb-1">Year</label>
            <select id="moduleFilterYear" class="form-select form-select-sm">
              <option value="">All Years</option>
              <option value="1">Year 1</option>
              <option value="2">Year 2</option>
              <option value="3">Year 3</option>
              <option value="4">Year 4</option>
              <option value="5">Year 5</option>
            </select>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label mb-1">Semester</label>
            <select id="moduleFilterSemester" class="form-select form-select-sm">
              <option value="">All Semesters</option>
              <option value="1">Semester 1</option>
              <option value="2">Semester 2</option>
            </select>
          </div>
        </div>
        <div class="table-responsive">
          <table id="moduleTable" class="table table-striped table-hover">
            <thead>
              <tr>
                <th>Code</th>
                <th>Name</th>
                <th>Credits</th>
                <th>Year</th>
                <th>Semester</th>
                <th>Program</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Facility Picker Modal -->
<div class="modal fade" id="facilityPickerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header" style="background: rgb(3,31,80); color: #fff;">
        <h5 class="modal-title">Select Facility</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <!-- Optional quick filters -->
        <div class="row g-2 mb-3">
          <div class="col-12 col-md-4">
            <label class="form-label mb-1">Type</label>
            <select id="facilityFilterType" class="form-select form-select-sm">
              <option value="">All</option>
            </select>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label mb-1"></label>
            <select id="facilityFilterCampus" hidden class="form-select form-select-sm">
              <option value="">All</option>
            </select>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label mb-1">Site</label>
            <select id="facilityFilterSite" class="form-select form-select-sm">
              <option value="">All</option>
            </select>
          </div>
        </div>

        <div class="table-responsive">
          <table id="facilityTable" class="table table-striped table-hover w-100">
            <thead>
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Type</th>
                <th>Capacity</th>
                <th>Campus</th>
                <th>Site</th>
                <th style="width: 110px;">Action</th>
              </tr>
            </thead>
            <tbody><!-- filled by JS --></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <small class="text-muted me-auto">Tip: use the search box to quickly find a room.</small>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
// jQuery (required by DataTables)
</script>
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<style>
    .card {
        padding: 1px;
        border: none;
        border-radius: 5px;
        margin-bottom: 10px;
    }
</style>
<script>
const timetableId = <?= $timetableId ?>;

// Show alerts
function showAlert(message, type="success") {
    const alert = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
    document.getElementById('alert-container').innerHTML = alert;
}

// Helper to safely fetch & parse JSON with debug
async function safeFetch(url, options = {}) {
    console.log("[safeFetch] Fetching:", url, "Options:", options);
    try {
        const fetchRes = await fetch(url, options);
        const text = await fetchRes.text();
        console.log("[safeFetch] Raw response text:", text);
        return JSON.parse(text);
    } catch (err) {
        console.error("[safeFetch] ❌ JSON parse error or fetch issue:", err);
        showAlert("⚠ Server returned an invalid response.", "danger");
        return null;
    }
}

// Approve timetable (unchanged)
async function approveTimetable(id) {
    const res = await safeFetch(`./approve_timetable.php?id=${id}`, { method: "POST" });
    if (res?.success) {
        showAlert("✅ Timetable approved successfully!");
    } else {
        showAlert("❌ Failed to approve timetable!", "danger");
    }
}

/* =======================
   FACILITY: picker + update
   ======================= */

let facilitiesCache = null;
let facilityTable = null;
let currentFacilityPickForTimetable = null;
let modulesCache = null;
let moduleTable = null;
let currentModulePickForTimetable = null; // which timetable is picking

async function openFacilityPicker(forTimetableId) {
    currentFacilityPickForTimetable = forTimetableId;

    // lazy load facilities once
    if (!facilitiesCache) {
        const res = await safeFetch('./get_facilities.php');
        if (!res?.success) {
            showAlert("❌ Failed to load facilities.", "danger");
            return;
        }
        facilitiesCache = res.data || [];
    }

    // Fill filter dropdowns
    fillFilterOptions('facilityFilterType', [...new Set(facilitiesCache.map(f => f.type).filter(Boolean))]);
    fillFilterOptions('facilityFilterCampus', [...new Set(facilitiesCache.map(f => f.campus).filter(Boolean))]);
    fillFilterOptions('facilityFilterSite', [...new Set(facilitiesCache.map(f => f.site).filter(Boolean))]);

    // Fill table body
    const tbody = document.querySelector('#facilityTable tbody');
    tbody.innerHTML = facilitiesCache.map(f => `
        <tr>
            <td>${f.id}</td>
            <td>${escapeHtml(f.name)}</td>
            <td>${escapeHtml(f.type || '')}</td>
            <td>${f.capacity ?? ''}</td>
            <td>${escapeHtml(f.campus || '')}</td>
            <td>${escapeHtml(f.site || '')}</td>
            <td>
                <button class="btn btn-sm btn-primary" onclick='pickFacility(${JSON.stringify(f.id) }, ${JSON.stringify(f.name)})'>
                    Select
                </button>
            </td>
        </tr>
    `).join('');

    // Init DataTable once
    if (!facilityTable) {
        facilityTable = new $.fn.dataTable.Api($('#facilityTable').DataTable({
            pageLength: 10,
            lengthMenu: [5,10,25,50,100],
            order: [[1, 'asc']],
            autoWidth: false
        }));

        // Hook up filters to column searches
        // Columns: 0=id,1=name,2=type,3=capacity,4=campus,5=site,6=action
        document.getElementById('facilityFilterType').addEventListener('change', (e) => {
            facilityTable.column(2).search(e.target.value || '', true, false).draw();
        });
        document.getElementById('facilityFilterCampus').addEventListener('change', (e) => {
            facilityTable.column(4).search(e.target.value || '', true, false).draw();
        });
        document.getElementById('facilityFilterSite').addEventListener('change', (e) => {
            facilityTable.column(5).search(e.target.value || '', true, false).draw();
        });
    } else {
        facilityTable.clear();
        facilitiesCache.forEach(f => {
            facilityTable.row.add([
                f.id,
                escapeHtml(f.name),
                escapeHtml(f.type || ''),
                f.capacity ?? '',
                escapeHtml(f.campus || ''),
                escapeHtml(f.site || ''),
                `<button class="btn btn-sm btn-primary" onclick='pickFacility(${JSON.stringify(f.id)}, ${JSON.stringify(f.name)})'>Select</button>`
            ]);
        });
        facilityTable.draw();
    }

    const facilityModal = new bootstrap.Modal(document.getElementById('facilityPickerModal'));
    facilityModal.show();
}

function fillFilterOptions(selectId, items) {
    const sel = document.getElementById(selectId);
    const v = sel.value; // keep selection
    sel.innerHTML = `<option value="">All</option>` + items.map(x => `<option value="${escapeHtml(x)}">${escapeHtml(x)}</option>`).join('');
    if ([...sel.options].some(o => o.value === v)) sel.value = v;
}

// Module picker modal instance
let modulePickerModal = null;

// Function to safely update modal content
function updateModalContent(modalElement, html) {
    if (!modalElement) {
        console.error('Modal element is null');
        return false;
    }
    
    // Ensure modal is shown before trying to access its content
    if (modalElement.classList.contains('show')) {
        const modalBody = modalElement.querySelector('.modal-body');
        if (!modalBody) {
            console.error('Modal body not found');
            return false;
        }
        modalBody.innerHTML = html;
        return true;
    } else {
        // If modal is not shown yet, queue the update for when it's shown
        const showHandler = () => {
            const modalBody = modalElement.querySelector('.modal-body');
            if (modalBody) {
                modalBody.innerHTML = html;
            }
            modalElement.removeEventListener('shown.bs.modal', showHandler);
        };
        modalElement.addEventListener('shown.bs.modal', showHandler);
        return true;
    }
}

// Open module picker in a new window
function openModulePicker(programId, timetableId) {
    if (!programId || !timetableId) {
        showAlert("❌ Could not determine program for this group.", "danger");
        return;
    }
    
    // Open the module picker in a new window
    const width = 1000;
    const height = 700;
    const left = (screen.width - width) / 2;
    const top = (screen.height - height) / 2;
    
    window.open(
        `module_picker.php?program_id=${programId}&timetable_id=${timetableId}`,
        'modulePicker',
        `width=${width},height=${height},top=${top},left=${left},resizable=yes,scrollbars=yes`
    );
    
    // Store the current timetable ID for the callback
    currentModulePickForTimetable = timetableId;
}

// Update the module in the database
async function updateSelectedModule(timetableId, moduleId, moduleName, moduleCode, credits) {
    if (!timetableId || !moduleId) {
        showAlert("❌ Invalid module selection.", "warning");
        return;
    }
    
    try {
        const response = await fetch('update_module.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `timetable_id=${timetableId}&module_id=${moduleId}`
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Update the UI to reflect the new module
            const moduleCell = document.querySelector(`tr[data-timetable-id="${timetableId}"] .module-info`);
            if (moduleCell) {
                moduleCell.innerHTML = `
                    <div class="module-name">${moduleName}</div>
                    <div class="text-muted small">${moduleCode} • ${credits} credits</div>
                `;
            }
            
            // Update the hidden inputs
            document.getElementById('moduleId').value = moduleId;
            document.getElementById('moduleName').textContent = moduleName;
            document.getElementById('moduleCode').textContent = moduleCode;
            document.getElementById('moduleCredits').textContent = credits;
            
            showAlert("✅ Module updated successfully!", "success");
            return true;
        } else {
            throw new Error(result.message || 'Failed to update module');
        }
    } catch (error) {
        console.error('Error updating module:', error);
        showAlert(`❌ Error: ${error.message || 'Failed to update module. Please try again.'}`, "danger");
        return false;
    }
}

// Old changeModule function for backward compatibility
async function changeModule() {
    const timetableId = currentModulePickForTimetable;
    const newModuleId = document.getElementById('moduleId').value;
    
    if (!timetableId || !newModuleId) {
        showAlert("❌ Please select a module first.", "warning");
        return;
    }
    
    const moduleName = document.getElementById('moduleName').textContent;
    const moduleCode = document.getElementById('moduleCode').textContent;
    const credits = document.getElementById('moduleCredits').textContent;
    
    return updateSelectedModule(timetableId, newModuleId, moduleName, moduleCode, credits);

    if (res?.success) {
        showAlert("✅ Module updated successfully!");
        // Reload the page to show updated module info
        setTimeout(() => window.location.reload(), 1000);
    } else {
        showAlert(`❌ Failed to update module! ${res?.error ? '('+res.error+')' : ''}`, "danger");
    }
}

function pickFacility(facilityId, facilityName) {
    if (!currentFacilityPickForTimetable) return;
    document.getElementById(`facilityId-${currentFacilityPickForTimetable}`).value = facilityId;
    document.getElementById(`facilityName-${currentFacilityPickForTimetable}`).textContent = facilityName;

    // close facility picker modal
    const facilityPickerEl = document.getElementById('facilityPickerModal');
    const facilityModal = bootstrap.Modal.getInstance(facilityPickerEl);
    facilityModal?.hide();
}

function escapeHtml(s){
    if (s === null || s === undefined) return '';
    return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

// Update timetable.facility_id (sends facility_id)
async function changeFacility(id) {
    const newFacilityId = document.getElementById(`facilityId-${id}`).value;
    console.log(`[changeFacility] Sending request for timetable ID=${id}, facility_id=${newFacilityId}`);

    const res = await safeFetch(`./update_facility.php?id=${id}`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ facility_id: newFacilityId })
    });

    console.log("[changeFacility] Raw response object:", res);

    if (res?.success) {
        console.log("[changeFacility] ✅ Facility updated successfully!");
        showAlert(`🏫 Facility changed successfully!`);
    } else {
        console.warn("[changeFacility] ❌ Facility update failed:", res?.error);
        showAlert(`❌ Failed to update facility! ${res?.error ? '('+res.error+')' : ''}`, "danger");
    }
}

/* =======================
   Delete timetable (unchanged)
   ======================= */
async function deleteTimetable(id) {
    if (!confirm("Are you sure you want to delete this timetable?")) return;
    const res = await safeFetch(`./delete_timetable.php?id=${id}`, { method: "DELETE" });
    if (res?.success) {
        showAlert("🗑 Timetable deleted successfully!");
        document.getElementById(`timetable-card-${id}`).remove();
    } else {
        showAlert("❌ Failed to delete timetable!", "danger");
    }
}

/* =======================
   Load timetable (facility UI changed)
   ======================= */
(async () => {
    const res = await safeFetch(`./get_timetable.php?id=${timetableId}`);
    if (!res?.success || !res.data.length) {
        document.getElementById('timetable-container').innerHTML = `<div class="alert alert-warning">No timetable found.</div>`;
        return;
    }

    const data = res.data.find(t => parseInt(t.id) === timetableId);
    if (!data) {
        document.getElementById('timetable-container').innerHTML = `<div class="alert alert-warning">Timetable not found.</div>`;
        return;
    }

    const uniqueSessions = Array.from(
    new Set((data.sessions || []).map(s => `${s.id}|${s.day}|${s.start}|${s.end}`))
).map(str => {
    const [id, day, start, end] = str.split('|');
    const showTrash = (data.sessions || []).length > 1; // only show if more than one session
    return `<p class="module-card">
                <strong>${day}</strong> - ${start} to ${end}
                ${showTrash ? `<a href="remove_session.php?timetable_id=${data.id}&session_id=${id}"><i class="bi bi-trash text-danger ml-4"></i></a>` : ''}
            </p>`;
}).join('');

    const html = `
        <div class="d-flex gap-2 m-3">
            <button class="btn btn-danger btn-sm" onclick="deleteTimetable(${data.id})">Delete</button>
            <a href="manage_lecturers.php?timetable_id=${data.id}" class="btn btn-primary btn-sm">Manage Lecturers</a>
        </div>
        <!-- Module Card -->
        <div class="col-md-12"></div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"> <i class="bi bi-book"></i> Module Details</div>
                    <div class="card-body">
                    <div class="module-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Module:</strong> <span id="moduleName">${data.course || 'N/A'}</span><br>
                                <p>
                                    <strong>Code:</strong> <span id="moduleCode">${data.code || 'N/A'}</span><br>
                                    <strong>Credits:</strong> <span id="moduleCredits">${data.credits || '0'}</span>
                                </p>
                                ${data.groups && data.groups.length > 0 ? 
                                    `<div id="program-data" data-program-id="${data.groups[0].program.id || ''}" style="display: none;"></div>` : 
                                    '<div id="program-data" data-program-id="" style="display: none;"></div>'
                                }
                            </div>
                            <a href="change_timetable_module.php?timetable_id=${data.id}" class="btn btn-sm btn-primary">
                                <i class="bi bi-pencil"></i> Change Module
                            </a>
                        </div>
                        <input type="hidden" id="moduleId" value="${data.module_id}">
                    </div>
        </div>
    </div>
</div>

<!-- Facility Card -->
<div class="col-md-6">
    <div class="card">
        <div class="card-header"><i class="bi bi-building"></i> Facility Information</div>
        <div class="card-body">
           
              
            <strong>Current Facility:</strong>
            <hr>
                
                    ${data.facility ? `
                        <div class="lecturer-card">
                           Facility: <strong>${data.facility.name}</strong><br>
                           Type: <strong>${data.facility.type}</strong><br>
                           Capacity: <strong>${data.facility.capacity}</strong><br>
                           Site: <strong>${data.facility.site.name}</strong><br>
                             
                          
                        </div>
                         <p><strong>Status:</strong> ${data.status}</p>
                    ` : 'N/A'}
                <hr>
            





            <p class="d-flex align-items-center gap-2 flex-wrap">
                        <strong class="me-2">Change Facility:</strong>
                        <span id="facilityName-${data.id}">${data.facility_name || ''}</span>
                        <input type="hidden" id="facilityId-${data.id}" value="${data.facility_id || ''}">
                        <button class="btn btn-outline-secondary btn-sm" onclick="openFacilityPicker(${data.id})">Choose</button>
                        <button class="btn btn-primary btn-sm" onclick="changeFacility(${data.id})">Update</button>
                        
                    </p>
        </div>
    </div>
</div>


        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><i class="bi bi-person-workspace"></i> Leader Lecturer</div>
                <div class="card-body">
                    ${data.leader_lecturer ? `
                        <div class="lecturer-card">
                            <strong>${data.leader_lecturer.names}</strong><br>
                            <small>${data.leader_lecturer.email}</small><br>
                            <small>${data.leader_lecturer.phone}</small><br>
                            
                            
                            
                        </div>
                    ` : 'N/A'}
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><i class="bi bi-people"></i> Other Lecturers</div>
                <div class="card-body">
                    ${(data.other_lecturers?.length ? data.other_lecturers.map(l => `
                        <div class="lecturer-card">
                            <strong>${l.names}</strong><br>
                            <small>${l.email}</small><br>
                            <small>${l.phone}</small><br>
                                <a href="remove_lecturer.php?id=${l.id}&timetable_id=${data.id}" class="text-end delete-group">
                            <small><i class="bi bi-trash text-danger text-center"></i></small>
                        </a>
                            
                        </div>
                    `).join('') : 'None')}
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header"><i class="bi bi-pen"></i> Created By</div>
                <div class="card-body">
                    ${data.created_by ? `
                        <div class="lecturer-card">
                            <strong>${data.created_by.names}</strong><br>
                            <small>${data.created_by.email}</small><br>
                            <small>${data.created_by.phone}</small><br>
                            <small>Role: ${data.created_by.role}</small>
                        </div>
                    ` : 'N/A'}
                </div>
            </div>
        </div>

       <div class="col-md-4">
    <div class="card">
        <div class="card-header"><i class="bi bi-check"></i> Approved By</div>
        <div class="card-body">
            ${data.approved_by && Object.keys(data.approved_by).length ? `
                <div class="lecturer-card">
                    <strong>${data.approved_by.names}</strong><br>
                    <small>${data.approved_by.email}</small><br>
                    <small>${data.approved_by.phone}</small><br>
                    <small>Role: ${data.approved_by.role}</small>
                </div>
            ` : 'Not Approved Yet'}
        </div>
    </div>
</div>



        <div class="col-md-4">
            <div class="card">
                <div class="card-header"><i class="bi bi-clock"></i> Sessions</div>
                <div class="card-body">
                    ${uniqueSessions || 'No sessions'}
                </div>
            </div>
        </div>

 
        <div class="col-12">
  <div class="card">
    <div class="card-header py-1" style="font-size: 0.85rem;">
      <i class="bi bi-people small"></i> Groups
    </div>
    <div class="card-body p-2" style="font-size: 0.8rem; line-height: 1.4;">
      ${data.groups?.length 
        ? `<div class="row g-2">
            ${data.groups.map(g => `
              <div class="col-md-4">
                <div class="group-card border p-2 rounded h-100">
                  <strong>${g.name}</strong> <small>(${g.size} students)</small><br>
                  <i class="bi bi-building"></i> <strong>College:</strong> ${g.college?.name || ''}<br>
                  <i class="bi bi-bank"></i> <strong>School:</strong> ${g.school?.name || ''}<br>
                  <i class="bi bi-diagram-3"></i> <strong>Department:</strong> ${g.department?.name || ''}<br>
                  <i class="bi bi-book"></i> <strong>Program:</strong> ${g.program?.name || ''}<br>
                  <i class="bi bi-calendar"></i> <strong>Intake:</strong> ${g.intake ? g.intake.year + '-' + String(g.intake.month).padStart(2,'0') : ''}<br>

                  <a href="remove_group.php?id=${g.id}&timetable_id=${data.id}" class="delete-group float-end">
                    <i class="bi bi-trash text-danger small"></i>
                  </a>
                </div>
              </div>
            `).join('')}
          </div>`
        : '<small>No groups</small>'
      }
    </div>
  </div>
</div>


</div>

    `;

    document.getElementById('timetable-container').innerHTML = html;
})();
</script>

    </main>

    <?php include('./includes/footer.php'); ?> 
    <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i
      class="bi bi-arrow-up-short"></i></a>

  <!-- Vendor JS Files -->
  <script src="assets/vendor/apexcharts/apexcharts.min.js"></script>
  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/vendor/chart.js/chart.umd.js"></script>
  <script src="assets/vendor/echarts/echarts.min.js"></script>
  <script src="assets/vendor/quill/quill.min.js"></script>
  <script src="assets/vendor/simple-datatables/simple-datatables.js"></script>
  <script src="assets/vendor/tinymce/tinymce.min.js"></script>
  <script src="assets/vendor/php-email-form/validate.js"></script>

  <!-- Template Main JS File -->
  <script src="assets/js/main.js"></script>
</body>
</html>
