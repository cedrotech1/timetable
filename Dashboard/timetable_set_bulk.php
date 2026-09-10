<?php
session_start();
include('connection.php');

if (!isset($_SESSION['id'])) {
    header('Location: ../login.php');
    exit;
}

$system_data_setting = "SELECT * FROM system LIMIT 1";
$system_data_result = mysqli_query($connection, $system_data_setting);
$accademic_year_id = null;
$semester = null;
if ($system_data_result && mysqli_num_rows($system_data_result)) {
    $system_data = mysqli_fetch_assoc($system_data_result);
    $accademic_year_id = $system_data['accademic_year_id'];
    $semester = $system_data['semester'];
}

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
$userSchoolId = null;

if (!$canAccessAllSchools) {
    $stmt = $connection->prepare("SELECT school FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $school = $result->fetch_assoc();
    $userSchoolId = $school ? $school['school'] : null;
    $stmt->close();
    if (!$userSchoolId) {
        die('No school ID found for your account');
    }
}

$school_name = '';
if ($userSchoolId) {
    $school_name_query = "SELECT name FROM school WHERE id = '$userSchoolId' LIMIT 1";
    $school_name_result = mysqli_query($connection, $school_name_query);
    if ($school_name_result && mysqli_num_rows($school_name_result)) {
        $school_name = mysqli_fetch_assoc($school_name_result)['name'];
    }
}

$timeOptions = ['08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00','19:00','20:00','21:00'];
$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Bulk Teaching Plan</title>
  <link href="assets/img/icon1.png" rel="icon">
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <style>
    .page-title {
      background-color: rgb(3,31,80) !important;
      color: #fff !important;
      padding: 10px !important;
      border-radius: 5px !important;
    }
    .page-title h2 { font-size: 1.1rem; margin: 0; color: #fff; }
    #bulkTable th { white-space: nowrap; font-size: 0.8rem; background: #f8f9fa; }
    #bulkTable td { vertical-align: middle; }
    #bulkTable .col-module, #bulkTable .col-facility, #bulkTable .col-leader { min-width: 180px; }
    .picker-btn {
      width: 100%;
      text-align: left;
      white-space: normal;
      font-size: 0.82rem;
      min-height: 38px;
    }
    .picker-btn .muted { color: #6c757d; font-size: 0.75rem; }
    .row-status { font-size: 0.75rem; }
    .row-ok { background-color: #f0fdf4 !important; }
    .row-fail { background-color: #fef2f2 !important; }
    .group-chip {
      display: inline-flex; align-items: center; gap: 4px;
      background: #e9ecef; border-radius: 999px; padding: 4px 10px; margin: 2px; font-size: 0.85rem;
    }
    .group-chip button { border: 0; background: transparent; color: #dc3545; }
    .picker-list { max-height: 420px; overflow: auto; }
    .picker-item {
      cursor: pointer;
      border: 1px solid #e9ecef;
      border-radius: 8px;
      padding: 10px 12px;
      margin-bottom: 8px;
      transition: background .15s;
    }
    .picker-item:hover { background: #f1f5ff; border-color: #c5d2ff; }
    .picker-item .cap-badge {
      background: #012a70; color: #fff; border-radius: 999px;
      padding: 2px 8px; font-size: 0.75rem; font-weight: 600;
    }
    .picker-item.too-small { opacity: 0.7; }
    .picker-item.too-small .cap-badge { background: #dc3545; }
    .picker-item.ok-cap .cap-badge { background: #198754; }
  </style>
</head>
<body>
<?php
include('./includes/header.php');
include('./includes/menu.php');
?>

<main id="main" class="main" style="background-color:rgb(245,245,245);">
  <div class="pagetitle">
    <h1>Bulk Teaching Plan</h1>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
        <li class="breadcrumb-item"><a href="timetable_set.php">Teaching Plan</a></li>
        <li class="breadcrumb-item active">Bulk</li>
      </ol>
    </nav>
  </div>

  <div class="card page-title d-flex flex-row flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h2>
      Bulk schedule — AY <?php echo htmlspecialchars($academic_year_label ?: '-'); ?>,
      Semester <?php echo htmlspecialchars($semester ?: '-'); ?>
      <?php if ($canAccessAllSchools): ?>
        (All Schools)
      <?php else: ?>
        for <?php echo htmlspecialchars($school_name ?: 'Assigned School'); ?>
      <?php endif; ?>
    </h2>
    <div class="d-flex gap-2">
      <a href="timetable_set.php" class="btn btn-outline-light btn-sm">Single plan</a>
      <button type="button" id="btnClearBulk" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i> Reset</button>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header fw-semibold">1. Select groups (shared for all rows)</div>
    <div class="card-body">
      <div class="row g-2 align-items-end">
        <div class="col-md-4">
          <label class="form-label small mb-1">Program</label>
          <select id="programSelect" class="form-select form-select-sm">
            <option value="">-- Select program --</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small mb-1">Intake</label>
          <select id="intakeSelect" class="form-select form-select-sm" disabled>
            <option value="">-- Select intake --</option>
          </select>
        </div>
        <div class="col-md-5">
          <label class="form-label small mb-1">Groups</label>
          <div id="groupsCheckList" class="border rounded p-2" style="max-height:140px;overflow:auto;">
            <span class="text-muted small">Select a program and intake first</span>
          </div>
        </div>
      </div>
      <div class="mt-3">
        <div class="small text-muted mb-1">Selected groups (<span id="selectedGroupCount">0</span>) — capacity: <strong id="requiredCapacity">0</strong></div>
        <div id="selectedGroupChips"></div>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <span class="fw-semibold">2. Add plan rows — click Module / Facility / Leader to search in a modal</span>
      <div class="d-flex gap-2">
        <button type="button" id="btnAddRow" class="btn btn-sm btn-primary"><i class="bi bi-plus"></i> Add row</button>
        <button type="button" id="btnSaveAll" class="btn btn-sm btn-success"><i class="bi bi-save"></i> Save all</button>
      </div>
    </div>
    <div class="card-body p-2">
      <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle mb-0" id="bulkTable">
          <thead>
            <tr>
              <th style="width:40px">#</th>
              <th class="col-module">Module</th>
              <th>Day</th>
              <th>Start</th>
              <th>End</th>
              <th class="col-facility">Facility</th>
              <th class="col-leader">Module leader</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="bulkTableBody"></tbody>
        </table>
      </div>
      <p class="small text-muted mt-2 mb-0">
        Facility modal lists <strong>all free rooms</strong> for that day/time (any capacity). Use search/min capacity to filter. Required group capacity is shown for guidance.
      </p>
    </div>
  </div>

  <div id="bulkResultAlert" class="alert d-none" role="alert"></div>
</main>

<!-- Shared searchable picker modal -->
<div class="modal fade" id="pickerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="pickerModalTitle">Select</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-2 mb-3 align-items-end">
          <div class="col-md-6">
            <label class="form-label small mb-1">Search</label>
            <input type="search" id="pickerSearch" class="form-control" placeholder="Type to filter...">
          </div>
          <div class="col-md-3 facility-only d-none">
            <label class="form-label small mb-1">Min capacity</label>
            <input type="number" id="pickerMinCap" class="form-control" min="0" placeholder="e.g. 400">
          </div>
          <div class="col-md-3 facility-only d-none">
            <div class="form-check mt-4">
              <input class="form-check-input" type="checkbox" id="pickerFitRequired">
              <label class="form-check-label" for="pickerFitRequired">Fit required (≥ <span id="pickerRequiredCap">0</span>)</label>
            </div>
          </div>
        </div>
        <div id="pickerHint" class="small text-muted mb-2"></div>
        <div id="pickerList" class="picker-list"></div>
      </div>
    </div>
  </div>
</div>

<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
<script>
(function () {
  const AY = <?php echo json_encode($accademic_year_id); ?>;
  const SEM = <?php echo json_encode($semester); ?>;
  const userSchoolId = <?php echo json_encode($userSchoolId); ?>;
  const canAccessAllSchools = <?php echo $canAccessAllSchools ? 'true' : 'false'; ?>;
  const DAYS = <?php echo json_encode($days); ?>;
  const TIMES = <?php echo json_encode($timeOptions); ?>;

  let programs = [];
  let selectedProgram = null;
  let selectedIntake = null;
  let selectedGroups = [];
  let rowSeq = 0;
  let modulesCache = [];
  let lecturersCache = [];
  let activeRow = null;
  let pickerMode = null; // module | facility | leader
  let facilityCache = [];
  let pickerModal;

  function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, c => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[c]));
  }

  function requiredCapacity() {
    return selectedGroups.reduce((sum, g) => sum + (parseInt(g.size, 10) || 0), 0);
  }

  function renderSelectedGroups() {
    $('#selectedGroupCount').text(selectedGroups.length);
    $('#requiredCapacity').text(requiredCapacity());
    const $chips = $('#selectedGroupChips').empty();
    selectedGroups.forEach(g => {
      $chips.append(`
        <span class="group-chip" data-id="${g.id}">
          ${escapeHtml(g.name)} (${g.size || 0})
          <button type="button" title="Remove" data-id="${g.id}">&times;</button>
        </span>
      `);
    });
  }

  function syncGroupChecks() {
    $('#groupsCheckList input[type=checkbox]').each(function () {
      const id = String($(this).val());
      $(this).prop('checked', selectedGroups.some(g => String(g.id) === id));
    });
  }

  function dayOptionsHtml(selected) {
    return '<option value="">Day</option>' + DAYS.map(d =>
      `<option value="${d}" ${d === selected ? 'selected' : ''}>${d}</option>`
    ).join('');
  }

  function timeOptionsHtml(selected, fallback) {
    const val = selected || fallback;
    return TIMES.map(t => `<option value="${t}" ${t === val ? 'selected' : ''}>${t}</option>`).join('');
  }

  function setModuleBtn($tr, module) {
    const $btn = $tr.find('.btn-pick-module');
    if (module && module.id) {
      $btn.data('id', module.id);
      $btn.html(`<strong>${escapeHtml(module.code || '')}</strong> ${escapeHtml(module.name || '')}<div class="muted">Click to change</div>`);
    } else {
      $btn.data('id', '');
      $btn.html('<span class="text-muted">Search module...</span>');
    }
  }

  function setLeaderBtn($tr, lect) {
    const $btn = $tr.find('.btn-pick-leader');
    if (lect && lect.id) {
      $btn.data('id', lect.id);
      $btn.html(`${escapeHtml(lect.names || lect.name || ('User ' + lect.id))}<div class="muted">Click to change</div>`);
    } else {
      $btn.data('id', '');
      $btn.html('<span class="text-muted">Search leader (optional)...</span>');
    }
  }

  function setFacilityBtn($tr, fac) {
    const $btn = $tr.find('.btn-pick-facility');
    if (fac && fac.id) {
      $btn.data('id', fac.id);
      $btn.data('capacity', fac.capacity || 0);
      $btn.html(`
        <strong>${escapeHtml(fac.name)}</strong>
        <span class="badge bg-primary ms-1">${fac.capacity || 0} seats</span>
        <div class="muted">${escapeHtml(fac.site_name || '')} / ${escapeHtml(fac.buildname || '')}</div>
      `);
    } else {
      $btn.data('id', '');
      $btn.data('capacity', '');
      $btn.html('<span class="text-muted">Search free facility...</span>');
    }
  }

  function addRow(preset = {}) {
    rowSeq += 1;
    const id = 'row_' + rowSeq;
    const tr = $(`
      <tr data-row-id="${id}">
        <td class="row-num"></td>
        <td class="col-module">
          <button type="button" class="btn btn-outline-secondary picker-btn btn-pick-module" data-id="">
            <span class="text-muted">Search module...</span>
          </button>
        </td>
        <td><select class="form-select form-select-sm day-select">${dayOptionsHtml(preset.day || '')}</select></td>
        <td><select class="form-select form-select-sm start-select">${timeOptionsHtml(preset.start, '08:00')}</select></td>
        <td><select class="form-select form-select-sm end-select">${timeOptionsHtml(preset.end, '13:00')}</select></td>
        <td class="col-facility">
          <button type="button" class="btn btn-outline-secondary picker-btn btn-pick-facility" data-id="">
            <span class="text-muted">Search free facility...</span>
          </button>
        </td>
        <td class="col-leader">
          <button type="button" class="btn btn-outline-secondary picker-btn btn-pick-leader" data-id="">
            <span class="text-muted">Search leader (optional)...</span>
          </button>
        </td>
        <td class="row-status text-muted">Ready</td>
        <td class="text-nowrap">
          <button type="button" class="btn btn-outline-primary btn-sm btn-dup" title="Duplicate"><i class="bi bi-copy"></i></button>
          <button type="button" class="btn btn-outline-danger btn-sm btn-del" title="Delete"><i class="bi bi-trash"></i></button>
        </td>
      </tr>
    `);
    $('#bulkTableBody').append(tr);

    if (preset.module_id) {
      const m = modulesCache.find(x => String(x.id) === String(preset.module_id));
      if (m) setModuleBtn(tr, m);
    }
    if (preset.leader_id) {
      const l = lecturersCache.find(x => String(x.id) === String(preset.leader_id));
      if (l) setLeaderBtn(tr, l);
    }
    if (preset.facility_id) {
      setFacilityBtn(tr, {
        id: preset.facility_id,
        name: preset.facility_name || ('Facility #' + preset.facility_id),
        capacity: preset.facility_capacity || '',
        site_name: preset.facility_site || '',
        buildname: preset.facility_building || ''
      });
    }

    renumberRows();
    return tr;
  }

  function renumberRows() {
    $('#bulkTableBody tr').each(function (i) {
      $(this).find('.row-num').text(i + 1);
    });
  }

  function collectRow($tr) {
    return {
      module_id: parseInt($tr.find('.btn-pick-module').data('id'), 10) || 0,
      day: $tr.find('.day-select').val() || '',
      start: $tr.find('.start-select').val() || '',
      end: $tr.find('.end-select').val() || '',
      facility_id: parseInt($tr.find('.btn-pick-facility').data('id'), 10) || 0,
      leader_id: parseInt($tr.find('.btn-pick-leader').data('id'), 10) || 0,
      other_lecturer_ids: []
    };
  }

  function openPicker(mode, $tr) {
    activeRow = $tr;
    pickerMode = mode;
    $('#pickerSearch').val('');
    $('#pickerMinCap').val('');
    $('#pickerFitRequired').prop('checked', false);
    $('#pickerRequiredCap').text(requiredCapacity());
    $('.facility-only').toggleClass('d-none', mode !== 'facility');

    if (mode === 'module') {
      $('#pickerModalTitle').text('Search module');
      $('#pickerHint').text(`${modulesCache.length} modules loaded`);
      renderPickerList();
      pickerModal.show();
    } else if (mode === 'leader') {
      $('#pickerModalTitle').text('Search module leader');
      $('#pickerHint').text(`${lecturersCache.length} lecturers loaded — optional`);
      renderPickerList();
      pickerModal.show();
    } else if (mode === 'facility') {
      openFacilityPicker($tr);
    }
  }

  function openFacilityPicker($tr) {
    const day = $tr.find('.day-select').val();
    const start = $tr.find('.start-select').val();
    const end = $tr.find('.end-select').val();
    if (!day || !start || !end) {
      alert('Set day, start and end first.');
      return;
    }
    if (start >= end) {
      alert('End time must be after start time.');
      return;
    }
    if (!selectedGroups.length) {
      alert('Select at least one group first.');
      return;
    }
    if (!AY || !SEM) {
      alert('Academic year / semester not configured.');
      return;
    }

    $('#pickerModalTitle').text(`Free facilities — ${day} ${start}-${end}`);
    $('#pickerHint').text('Loading all free facilities for this slot...');
    $('#pickerList').html('<div class="text-center py-4 text-muted">Loading...</div>');
    $('#pickerRequiredCap').text(requiredCapacity());
    pickerModal.show();

    // Load ALL free rooms for slot (no min capacity) so user can see every capacity
    $.ajax({
      url: 'get_facilities_with_site.php',
      type: 'POST',
      dataType: 'json',
      data: {
        draw: 1,
        start: 0,
        length: 1000,
        'search[value]': '',
        academic_year_id: AY,
        semester: SEM,
        sessions: JSON.stringify([{ day, start, end }]),
        minCapacity: 0
      }
    }).done(function (json) {
      facilityCache = Array.isArray(json?.data) ? json.data : [];
      facilityCache.sort((a, b) => (b.capacity || 0) - (a.capacity || 0));
      $('#pickerHint').text(`${facilityCache.length} free facilities (all capacities). Required students: ${requiredCapacity()}.`);
      renderPickerList();
    }).fail(function (xhr) {
      console.error(xhr.responseText);
      facilityCache = [];
      $('#pickerHint').text('Failed to load facilities.');
      $('#pickerList').html('<div class="alert alert-danger">Failed to load facilities.</div>');
    });
  }

  function renderPickerList() {
    const q = ($('#pickerSearch').val() || '').toLowerCase().trim();
    const $list = $('#pickerList').empty();
    const req = requiredCapacity();

    if (pickerMode === 'module') {
      const items = modulesCache.filter(m => {
        if (!q) return true;
        return `${m.code || ''} ${m.name || ''} ${m.program_name || ''}`.toLowerCase().includes(q);
      }).slice(0, 400);

      if (!items.length) {
        $list.html('<div class="text-muted">No modules match.</div>');
        return;
      }
      items.forEach(m => {
        $list.append(`
          <div class="picker-item" data-type="module" data-id="${m.id}">
            <div class="fw-semibold">${escapeHtml(m.code ? m.code + ' — ' : '')}${escapeHtml(m.name)}</div>
            <div class="small text-muted">Year ${m.year ?? 'N/A'} · Sem ${m.semester ?? 'N/A'} · ${escapeHtml(m.program_name || '')}</div>
          </div>
        `);
      });
      return;
    }

    if (pickerMode === 'leader') {
      $list.append(`
        <div class="picker-item" data-type="leader" data-id="">
          <div class="fw-semibold text-muted">No leader (clear)</div>
        </div>
      `);
      const items = lecturersCache.filter(l => {
        if (!q) return true;
        return `${l.names || ''} ${l.name || ''} ${l.email || ''} ${l.ur_email || ''}`.toLowerCase().includes(q);
      }).slice(0, 400);
      items.forEach(l => {
        $list.append(`
          <div class="picker-item" data-type="leader" data-id="${l.id}">
            <div class="fw-semibold">${escapeHtml(l.names || l.name || ('User ' + l.id))}</div>
            <div class="small text-muted">${escapeHtml(l.ur_email || l.email || '')}</div>
          </div>
        `);
      });
      return;
    }

    if (pickerMode === 'facility') {
      let minCap = parseInt($('#pickerMinCap').val(), 10);
      if ($('#pickerFitRequired').is(':checked')) minCap = req;
      if (!Number.isFinite(minCap) || minCap < 0) minCap = 0;

      const items = facilityCache.filter(f => {
        if ((f.capacity || 0) < minCap) return false;
        if (!q) return true;
        return `${f.name || ''} ${f.site_name || ''} ${f.buildname || ''} ${f.type || ''} ${f.capacity || ''}`.toLowerCase().includes(q);
      });

      if (!items.length) {
        $list.html('<div class="text-muted">No free facilities match this search/capacity filter.</div>');
        return;
      }

      items.forEach(f => {
        const cap = f.capacity || 0;
        const ok = !req || cap >= req;
        $list.append(`
          <div class="picker-item ${ok ? 'ok-cap' : 'too-small'}" data-type="facility" data-id="${f.id}">
            <div class="d-flex justify-content-between align-items-start gap-2">
              <div>
                <div class="fw-semibold">${escapeHtml(f.name)}</div>
                <div class="small text-muted">${escapeHtml(f.type || '')} · ${escapeHtml(f.site_name || '')} · ${escapeHtml(f.buildname || 'N/A')}</div>
              </div>
              <span class="cap-badge">${cap} seats ${ok ? '' : '(small)'}</span>
            </div>
          </div>
        `);
      });
    }
  }

  function loadOrganization() {
    $.getJSON('get_organization_structure.php')
      .done(function (response) {
        programs = [];
        if (!response.success || !response.data?.colleges) {
          alert('Failed to load programs.');
          return;
        }
        response.data.colleges.forEach(college => {
          (college.schools || []).forEach(school => {
            if (!canAccessAllSchools && userSchoolId && String(school.id) !== String(userSchoolId)) return;
            (school.all_programs || []).forEach(prog => {
              programs.push({
                id: prog.id,
                name: prog.name,
                code: prog.code,
                school_id: school.id,
                school_name: school.name,
                intakes: (prog.intakes || []).map(intake => ({
                  id: intake.id,
                  year_of_study: intake.year_of_study || intake.year || 1,
                  campus_name: intake.campus_name || intake.campus?.name || 'Unassigned',
                  groups: intake.groups || []
                }))
              });
            });
          });
        });
        const $sel = $('#programSelect').empty().append('<option value="">-- Select program --</option>');
        programs.forEach(p => {
          const label = `${p.name}${p.code ? ' [' + p.code + ']' : ''}${p.school_name ? ' (' + p.school_name + ')' : ''}`;
          $sel.append(`<option value="${p.id}">${escapeHtml(label)}</option>`);
        });
      })
      .fail(() => alert('Failed to load organization structure.'));
  }

  function loadModules() {
    return $.getJSON('api_get_modules.php', { page: 1, perPage: 5000, sort: 'name' })
      .done(function (res) {
        modulesCache = (res && res.success && Array.isArray(res.data)) ? res.data : [];
      });
  }

  function loadLecturers() {
    const params = { page: 1, limit: 5000 };
    if (!canAccessAllSchools && userSchoolId) params.school = userSchoolId;
    return $.getJSON('get_lecturers.php', params)
      .done(function (res) {
        if (Array.isArray(res)) lecturersCache = res;
        else if (Array.isArray(res?.data)) lecturersCache = res.data;
        else if (Array.isArray(res?.lecturers)) lecturersCache = res.lecturers;
        else lecturersCache = [];
      });
  }

  // Events
  $('#programSelect').on('change', function () {
    const id = $(this).val();
    selectedProgram = programs.find(p => String(p.id) === String(id)) || null;
    selectedIntake = null;
    const $intake = $('#intakeSelect').empty().prop('disabled', true);
    $('#groupsCheckList').html('<span class="text-muted small">Select an intake</span>');
    if (!selectedProgram) return;
    $intake.append('<option value="">-- Select intake --</option>');
    (selectedProgram.intakes || []).forEach(i => {
      $intake.append(`<option value="${i.id}">Year ${i.year_of_study} — ${escapeHtml(i.campus_name)}</option>`);
    });
    $intake.prop('disabled', false);
  });

  $('#intakeSelect').on('change', function () {
    const id = $(this).val();
    selectedIntake = selectedProgram?.intakes?.find(i => String(i.id) === String(id)) || null;
    const $list = $('#groupsCheckList').empty();
    if (!selectedIntake?.groups?.length) {
      $list.html('<span class="text-muted small">No groups for this intake</span>');
      return;
    }
    selectedIntake.groups.forEach(g => {
      const checked = selectedGroups.some(x => String(x.id) === String(g.id)) ? 'checked' : '';
      $list.append(`
        <div class="form-check">
          <input class="form-check-input group-check" type="checkbox" value="${g.id}" id="g${g.id}" ${checked}
                 data-name="${escapeHtml(g.name)}" data-size="${g.size || 0}">
          <label class="form-check-label" for="g${g.id}">${escapeHtml(g.name)} (${g.size || 0})</label>
        </div>
      `);
    });
  });

  $('#groupsCheckList').on('change', '.group-check', function () {
    const id = $(this).val();
    const name = $(this).data('name');
    const size = parseInt($(this).data('size'), 10) || 0;
    if (this.checked) {
      if (!selectedGroups.some(g => String(g.id) === String(id))) {
        selectedGroups.push({ id: parseInt(id, 10), name, size });
      }
    } else {
      selectedGroups = selectedGroups.filter(g => String(g.id) !== String(id));
    }
    renderSelectedGroups();
  });

  $('#selectedGroupChips').on('click', 'button', function () {
    const id = String($(this).data('id'));
    selectedGroups = selectedGroups.filter(g => String(g.id) !== id);
    renderSelectedGroups();
    syncGroupChecks();
  });

  $('#btnAddRow').on('click', function () {
    addRow();
  });

  $('#bulkTableBody').on('click', '.btn-del', function () {
    $(this).closest('tr').remove();
    renumberRows();
  });

  $('#bulkTableBody').on('click', '.btn-dup', function () {
    const $tr = $(this).closest('tr');
    const data = collectRow($tr);
    const $facBtn = $tr.find('.btn-pick-facility');
    data.facility_name = $facBtn.find('strong').first().text();
    data.facility_capacity = $facBtn.data('capacity');
    addRow(data);
  });

  $('#bulkTableBody').on('click', '.btn-pick-module', function () {
    openPicker('module', $(this).closest('tr'));
  });
  $('#bulkTableBody').on('click', '.btn-pick-leader', function () {
    openPicker('leader', $(this).closest('tr'));
  });
  $('#bulkTableBody').on('click', '.btn-pick-facility', function () {
    openPicker('facility', $(this).closest('tr'));
  });

  $('#bulkTableBody').on('change', '.day-select, .start-select, .end-select', function () {
    const $tr = $(this).closest('tr');
    setFacilityBtn($tr, null);
    $tr.find('.row-status').text('Re-pick facility').removeClass('text-success').addClass('text-muted');
  });

  let searchTimer = null;
  $('#pickerSearch, #pickerMinCap').on('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(renderPickerList, 150);
  });
  $('#pickerFitRequired').on('change', function () {
    if (this.checked) $('#pickerMinCap').val(requiredCapacity() || '');
    renderPickerList();
  });

  $('#pickerList').on('click', '.picker-item', function () {
    if (!activeRow) return;
    const type = $(this).data('type');
    const id = $(this).data('id');

    if (type === 'module') {
      const m = modulesCache.find(x => String(x.id) === String(id));
      setModuleBtn(activeRow, m);
    } else if (type === 'leader') {
      if (!id) setLeaderBtn(activeRow, null);
      else {
        const l = lecturersCache.find(x => String(x.id) === String(id));
        setLeaderBtn(activeRow, l);
      }
    } else if (type === 'facility') {
      const f = facilityCache.find(x => String(x.id) === String(id));
      setFacilityBtn(activeRow, f);
      activeRow.find('.row-status').text('Facility set').addClass('text-muted');
    }
    pickerModal.hide();
  });

  $('#btnClearBulk').on('click', function () {
    if (!confirm('Clear selected groups and all plan rows?')) return;
    selectedGroups = [];
    renderSelectedGroups();
    syncGroupChecks();
    $('#bulkTableBody').empty();
    $('#bulkResultAlert').addClass('d-none');
  });

  $('#btnSaveAll').on('click', async function () {
    const $alert = $('#bulkResultAlert').removeClass('d-none alert-success alert-danger alert-warning').addClass('alert-info').text('Saving...');
    if (!selectedGroups.length) {
      $alert.removeClass('alert-info').addClass('alert-danger').text('Select at least one group.');
      return;
    }
    const $rows = $('#bulkTableBody tr');
    if (!$rows.length) {
      $alert.removeClass('alert-info').addClass('alert-danger').text('Add at least one plan row.');
      return;
    }

    const rows = [];
    let invalid = false;
    $rows.each(function () {
      const $tr = $(this);
      $tr.removeClass('row-ok row-fail');
      const row = collectRow($tr);
      if (!row.module_id || !row.day || !row.start || !row.end || !row.facility_id) {
        invalid = true;
        $tr.addClass('row-fail');
        $tr.find('.row-status').text('Incomplete').addClass('text-danger');
      }
      rows.push(row);
    });
    if (invalid) {
      $alert.removeClass('alert-info').addClass('alert-danger').text('Fill module, day, times and facility on every row.');
      return;
    }

    try {
      const res = await fetch('save_timetable_bulk.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          academic_year_id: AY,
          semester: SEM,
          groupIds: selectedGroups.map(g => g.id),
          rows
        })
      });
      const data = await res.json();
      (data.results || []).forEach(r => {
        const $tr = $('#bulkTableBody tr').eq(r.row_index);
        if (!$tr.length) return;
        if (r.status === 'success') {
          $tr.addClass('row-ok').removeClass('row-fail');
          $tr.find('.row-status').removeClass('text-danger').addClass('text-success').text(r.approval_status || 'Saved');
        } else {
          $tr.addClass('row-fail').removeClass('row-ok');
          $tr.find('.row-status').removeClass('text-success').addClass('text-danger')
            .text(r.status === 'conflict' ? 'Conflict' : (r.message || 'Error'));
        }
      });
      const cls = data.status === 'success' ? 'alert-success' : (data.status === 'partial' ? 'alert-warning' : 'alert-danger');
      $alert.removeClass('alert-info alert-success alert-danger alert-warning').addClass(cls).text(data.message || 'Done');
    } catch (e) {
      console.error(e);
      $alert.removeClass('alert-info').addClass('alert-danger').text('Network error while saving.');
    }
  });

  // Init
  pickerModal = new bootstrap.Modal(document.getElementById('pickerModal'));
  renderSelectedGroups();
  loadOrganization();
  Promise.all([loadModules(), loadLecturers()]).then(() => addRow());
})();
</script>
</body>
</html>
