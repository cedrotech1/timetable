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

$user_role = $_SESSION['role'] ?? '';
$canImport = in_array($user_role, ['admin', 'registrar_office', 'dean_office'], true);
if (!$canImport) {
    die('You do not have permission to import timetables.');
}

$userSchoolId = null;
$canAccessAllSchools = in_array($user_role, ['admin', 'registrar_office'], true);
if (!$canAccessAllSchools) {
    $uq = mysqli_query($connection, "SELECT school FROM users WHERE id = " . intval($_SESSION['id']) . " LIMIT 1");
    if ($uq && ($ur = mysqli_fetch_assoc($uq))) {
        $userSchoolId = $ur['school'] ?? null;
    }
}

$campuses = [];
$cq = mysqli_query($connection, "SELECT id, name FROM campus ORDER BY name");
if ($cq) {
    while ($r = mysqli_fetch_assoc($cq)) $campuses[] = $r;
}
$allPrograms = [];
$pq = mysqli_query($connection, "SELECT id, name, code FROM program ORDER BY name");
if ($pq) {
    while ($r = mysqli_fetch_assoc($pq)) $allPrograms[] = $r;
}

$timeOptions = ['08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00','19:00','20:00','21:00'];
$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Import Timetable Excel</title>
  <link href="assets/img/icon1.png" rel="icon">
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
  <style>
    :root {
      --imp-navy: #031f50;
      --imp-surface: #f4f6f9;
      --imp-line: #e2e8f0;
    }
    .page-title {
      background: var(--imp-navy) !important;
      color: #fff !important;
      padding: 12px 16px !important;
      border-radius: 8px !important;
      border: 0;
    }
    .page-title h2 { font-size: 1.05rem; margin: 0; color: #fff; font-weight: 600; }
    .imp-section {
      border: 1px solid var(--imp-line);
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 1px 2px rgba(3,31,80,.04);
      background: #fff;
    }
    .imp-header {
      background: linear-gradient(90deg, var(--imp-navy), #0a3a7a);
      color: #fff;
      padding: 12px 16px;
    }
    .imp-header .step {
      display: inline-flex; align-items: center; justify-content: center;
      width: 26px; height: 26px; border-radius: 50%;
      background: rgba(255,255,255,.18); font-weight: 700; margin-right: 8px;
    }
    .imp-body { padding: 16px; }
    .panel {
      background: var(--imp-surface);
      border: 1px solid var(--imp-line);
      border-radius: 10px;
      padding: 14px;
      height: 100%;
    }
    .panel-title {
      font-size: .78rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: .04em; color: var(--imp-navy); margin-bottom: 8px;
    }
    .sec-card {
      border: 1px solid var(--imp-line);
      border-radius: 10px;
      padding: 12px 14px;
      margin-bottom: 8px;
      background: #fff;
      cursor: pointer;
      display: flex;
      justify-content: space-between;
      gap: 10px;
      align-items: flex-start;
    }
    .sec-card.active { border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59,130,246,.15); }
    .sec-card .title { font-weight: 700; color: var(--imp-navy); font-size: .92rem; }
    .meta { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
    .pill {
      font-size: .72rem; font-weight: 600; border-radius: 6px;
      padding: 3px 8px; background: #eef2ff; color: #1e3a8a;
    }
    .pill.ok { background: #dcfce7; color: #166534; }
    .pill.warn { background: #fef3c7; color: #92400e; }
    .pill.err { background: #fee2e2; color: #b91c1c; }
    .table-wrap {
      border: 1px solid var(--imp-line);
      border-radius: 10px;
      overflow: auto;
      max-height: min(60vh, 640px);
    }
    #matchTable { min-width: 1100px; }
    #matchTable th { white-space: nowrap; font-size: .78rem; }
    #matchTable td { vertical-align: top; }
    .picker-btn {
      width: 100%;
      text-align: left;
      white-space: normal;
      font-size: .78rem;
      padding: 6px 8px;
    }
    .picker-btn .pick-icon { color: #012a70; margin-right: 4px; }
    .lect-tag {
      display: inline-flex; align-items: center; gap: 4px;
      border-radius: 999px; padding: 2px 8px; font-size: .72rem; margin: 2px;
    }
    .lect-tag.leader { background: var(--imp-navy); color: #fff; }
    .lect-tag.other { background: #e8f5e9; color: #198754; border: 1px solid #a5d6a7; }
    .lect-tag .role { opacity: .85; font-weight: 500; font-size: .65rem; }
    .lect-tag button { border: 0; background: transparent; color: inherit; line-height: 1; padding: 0 0 0 2px; }
    .hint {
      font-size: .8rem; color: #475569; background: #f8fafc;
      border: 1px solid var(--imp-line); border-radius: 8px;
      padding: 10px 12px; margin-top: 12px;
    }
    .picker-list { max-height: 420px; overflow: auto; padding-right: 4px; }
    .picker-item {
      border: 1px solid var(--imp-line);
      border-radius: 8px;
      padding: 10px 12px;
      margin-bottom: 6px;
      cursor: pointer;
      background: #fff;
    }
    .picker-item:hover { border-color: #3b82f6; background: #f8fbff; }
    .picker-item .cap-badge {
      font-size: .72rem; font-weight: 700; color: #fff;
      border-radius: 6px; padding: 2px 8px; white-space: nowrap;
    }
    .picker-item.too-small { opacity: .75; }
    .picker-item.too-small .cap-badge { background: #dc3545; }
    .picker-item.ok-cap .cap-badge { background: #198754; }
    .section-divider-label {
      font-size: .72rem; font-weight: 700; text-transform: uppercase;
      color: #64748b; margin: 10px 0 6px;
    }
    .lect-row {
      display: flex; justify-content: space-between; gap: 10px; align-items: center;
      border: 1px solid var(--imp-line); border-radius: 8px; padding: 8px 10px; margin-bottom: 6px;
    }
    .lect-row .lect-actions { flex-shrink: 0; display: flex; gap: 4px; flex-wrap: wrap; }
    .lect-row .lect-actions .btn { font-size: .72rem; padding: 3px 8px; }
    .lect-row.selected-leader { background: #eef2ff; border-color: #c5d2ff; }
    .lect-row.selected-other { background: #f0fdf4; border-color: #bbf7d0; }
    .excel-sub { font-size: .72rem; color: #64748b; }
  </style>
</head>
<body>
<?php
include('./includes/header.php');
include('./includes/menu.php');
?>

<main id="main" class="main" style="background-color:rgb(245,245,245);">
  <div class="pagetitle">
    <h1>Import Timetable Excel</h1>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
        <li class="breadcrumb-item"><a href="timetable_set_bulk.php">Bulk Teaching Plan</a></li>
        <li class="breadcrumb-item active">Import Excel</li>
      </ol>
    </nav>
  </div>

  <div class="card page-title d-flex flex-row flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h2>
      Excel sync &amp; match — AY <?php echo htmlspecialchars($academic_year_label ?: '-'); ?>,
      Semester <?php echo htmlspecialchars($semester ?: '-'); ?>
    </h2>
    <div class="d-flex gap-2">
      <a href="timetable_set_bulk.php" class="btn btn-outline-light btn-sm">Bulk plan</a>
      <a href="timetable_set.php" class="btn btn-outline-light btn-sm">Single plan</a>
    </div>
  </div>

  <div class="imp-section mb-3">
    <div class="imp-header d-flex align-items-center">
      <span class="step">1</span>
      <div>
        <div class="fw-semibold">Upload Excel</div>
        <div class="small" style="opacity:.85">Auto-match first, then fix anything with the pickers — same as Teaching Plan</div>
      </div>
    </div>
    <div class="imp-body">
      <div class="row g-3">
        <div class="col-lg-5">
          <div class="panel">
            <div class="panel-title"><i class="bi bi-file-earmark-excel me-1"></i> File</div>
            <input type="file" id="excelFile" class="form-control form-control-sm" accept=".xlsx,.xls,.csv">
            <div class="small text-muted mt-2">
              Detects section headers like <em>Year 2 : PROGRAM… GROUP 1 &amp; 2</em>, then matches modules, rooms, lecturers and groups.
            </div>
            <div class="d-flex flex-wrap gap-2 mt-3">
              <button type="button" id="btnParse" class="btn btn-primary btn-sm" disabled>
                <i class="bi bi-search"></i> Parse &amp; match
              </button>
              <button type="button" id="btnClear" class="btn btn-outline-secondary btn-sm" disabled>Clear</button>
            </div>
            <div id="statusMsg" class="small text-muted mt-2"></div>
          </div>
        </div>
        <div class="col-lg-7">
          <div class="panel">
            <div class="panel-title"><i class="bi bi-diagram-3 me-1"></i> Matched sections</div>
            <div id="sectionsEmpty" class="text-muted small">Upload a file, then Parse &amp; match.</div>
            <div id="sectionsList"></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="imp-section mb-3 d-none" id="detailSection">
    <div class="imp-header d-flex flex-wrap align-items-center justify-content-between gap-2">
      <div class="d-flex align-items-center">
        <span class="step">2</span>
        <div>
          <div class="fw-semibold" id="detailTitle">Section details</div>
          <div class="small" style="opacity:.85">Change day, time, module, facility or lecturers anytime — then save</div>
        </div>
      </div>
      <button type="button" id="btnSaveSection" class="btn btn-success btn-sm">
        <i class="bi bi-save"></i> Save this section
      </button>
    </div>
    <div class="imp-body">
      <div id="sectionMeta" class="meta mb-3"></div>

      <div id="createGroupsPanel" class="panel mb-3 d-none" style="background:#fff8e6;border-color:#f0d78c;">
        <div class="panel-title text-warning-emphasis"><i class="bi bi-plus-circle me-1"></i> No groups matched — create promotion / intake &amp; groups</div>
        <p class="small text-muted mb-2">
          Create the missing year intake and groups from this Excel section, then rematch automatically.
        </p>
        <div class="row g-2 align-items-end">
          <div class="col-md-4">
            <label class="form-label small mb-1">Program</label>
            <select id="createProgramId" class="form-select form-select-sm"></select>
          </div>
          <div class="col-md-2">
            <label class="form-label small mb-1">Year of study</label>
            <input type="number" id="createYear" class="form-control form-control-sm" min="1" max="6" value="1">
          </div>
          <div class="col-md-3">
            <label class="form-label small mb-1">Campus</label>
            <select id="createCampusId" class="form-select form-select-sm">
              <?php foreach ($campuses as $c): ?>
                <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label small mb-1">Group numbers</label>
            <input type="text" id="createGroupNums" class="form-control form-control-sm" placeholder="e.g. 1,2">
          </div>
          <div class="col-md-3">
            <label class="form-label small mb-1">Size</label>
            <input type="number" id="createSize" class="form-control form-control-sm" min="1" placeholder="109">
          </div>
          <div class="col-md-3">
            <label class="form-label small mb-1">Size means</label>
            <select id="createSizeMode" class="form-select form-select-sm">
              <option value="each">Per group (EACH GROUP)</option>
              <option value="total">Total for all groups</option>
            </select>
          </div>
          <div class="col-md-6">
            <button type="button" id="btnCreateIntakeGroups" class="btn btn-warning btn-sm">
              <i class="bi bi-people"></i> Create intake &amp; groups, then rematch
            </button>
            <span id="createStatus" class="small ms-2 text-muted"></span>
          </div>
        </div>
      </div>

      <div class="table-wrap">
        <table class="table table-sm table-bordered mb-0" id="matchTable">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Day / Time</th>
              <th>Excel</th>
              <th style="min-width:180px">Module</th>
              <th style="min-width:160px">Facility</th>
              <th style="min-width:180px">Lecturers</th>
              <th>Groups</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody id="matchBody"></tbody>
        </table>
      </div>
      <div class="hint mb-0">
        Unmatched Excel values become warnings — fix them with <strong>Pick / Change</strong> (module, facility, lecturers) or edit day/time.
        Save needs system <strong>module</strong>, <strong>facility</strong>, <strong>day</strong> and <strong>time</strong>. Lecturer is optional. Facility &amp; group conflicts are blocked on save.
      </div>
      <div id="saveAlert" class="alert d-none mt-3 mb-0" role="alert"></div>
      <div id="conflictBox" class="card border-danger d-none mt-3">
        <div class="card-header text-danger fw-semibold">Conflict details</div>
        <div class="card-body small" id="conflictBody"></div>
      </div>
    </div>
  </div>
</main>

<!-- Shared searchable picker (module / facility) -->
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
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-search"></i></span>
              <input type="search" id="pickerSearch" class="form-control" placeholder="Type to filter...">
            </div>
          </div>
          <div class="col-md-3 facility-only d-none">
            <label class="form-label small mb-1">Min capacity</label>
            <input type="number" id="pickerMinCap" class="form-control" min="0" placeholder="e.g. 100">
          </div>
          <div class="col-md-3 facility-only d-none">
            <div class="form-check mt-4">
              <input class="form-check-input" type="checkbox" id="pickerFitRequired">
              <label class="form-check-label" for="pickerFitRequired">Fit required (≥ <span id="pickerRequiredCap">0</span>)</label>
            </div>
          </div>
        </div>
        <div id="pickerHint" class="hint mt-0 mb-3"></div>
        <div id="pickerList" class="picker-list"></div>
      </div>
    </div>
  </div>
</div>

<!-- Lecturers modal -->
<div class="modal fade" id="lecturerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Module Leader &amp; Lecturers</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <div class="panel-title mb-2">Selected for this row</div>
          <div id="lectModalTags" class="border rounded p-3 bg-light">
            <span class="text-muted small">None yet</span>
          </div>
        </div>
        <label class="form-label small mb-1">Search lecturers</label>
        <div class="input-group mb-2">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input type="search" id="lectModalSearch" class="form-control" placeholder="Name or email...">
        </div>
        <div id="lectModalHint" class="small text-muted mb-2"></div>
        <div id="lectModalList" class="picker-list"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnClearRowLecturers">Clear all</button>
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
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
  const ALL_PROGRAMS = <?php echo json_encode($allPrograms); ?>;
  const DAYS = <?php echo json_encode($days); ?>;
  const TIMES = <?php echo json_encode($timeOptions); ?>;
  const userSchoolId = <?php echo json_encode($userSchoolId); ?>;
  const canAccessAllSchools = <?php echo $canAccessAllSchools ? 'true' : 'false'; ?>;

  let fileBuffer = null;
  let parsedSections = [];
  let importSections = [];
  let activeIdx = null;
  let editRowIndex = null;
  let modulesCache = [];
  let lecturersCache = [];
  let facilityCache = [];
  let pickerMode = null;
  let pickerModal = null;
  let lecturerModal = null;
  let draftLecturers = { leader: null, others: [] };

  function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, c => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[c]));
  }

  function lectName(l) {
    return (l && (l.names || l.name)) || (l?.id ? ('User ' + l.id) : '');
  }

  function setStatus(msg, cls) {
    $('#statusMsg').removeClass('text-muted text-danger text-success text-warning').addClass(cls || 'text-muted').text(msg || '');
  }

  function dayOptionsHtml(selected) {
    return DAYS.map(d => `<option value="${d}" ${d === selected ? 'selected' : ''}>${d}</option>`).join('');
  }

  function timeOptionsHtml(selected, fallback) {
    const sel = selected || fallback || '';
    let html = TIMES.map(t => `<option value="${t}" ${t === sel ? 'selected' : ''}>${t}</option>`).join('');
    if (sel && !TIMES.includes(sel)) {
      html = `<option value="${escapeHtml(sel)}" selected>${escapeHtml(sel)}</option>` + html;
    }
    return html;
  }

  function normalizeDayName(d) {
    const s = String(d || '').trim().toLowerCase();
    const map = {
      mon: 'Monday', monday: 'Monday',
      tue: 'Tuesday', tues: 'Tuesday', tuesday: 'Tuesday',
      wed: 'Wednesday', weds: 'Wednesday', wedsday: 'Wednesday', wednesday: 'Wednesday',
      thu: 'Thursday', thur: 'Thursday', thurs: 'Thursday', thursday: 'Thursday',
      fri: 'Friday', friday: 'Friday',
      sat: 'Saturday', saturday: 'Saturday',
      sun: 'Sunday', sunday: 'Sunday'
    };
    if (map[s]) return map[s];
    for (const [k, v] of Object.entries(map)) {
      if (s.startsWith(k)) return v;
    }
    return d ? String(d).trim() : '';
  }

  function parseExcelTime(raw) {
    const text = String(raw || '').replace(/\s+/g, ' ').trim();
    const timeGroupNums = [];
    const gp = text.match(/\(?\s*(?:GP|G|Group)\s*([0-9]+(?:\s*[&,]\s*[0-9]+)*)\s*\)?/i);
    if (gp) (gp[1].match(/\d+/g) || []).forEach(n => timeGroupNums.push(parseInt(n, 10)));

    const m = text.match(/(\d{1,2}):(\d{2})\s*(AM|PM)?\s*[-–—to]+\s*(\d{1,2}):(\d{2})\s*(AM|PM)?/i);
    if (!m) return { start: '', end: '', time_raw: text, time_group_nums: timeGroupNums };

    function to24(h, min, ampm) {
      let hh = parseInt(h, 10);
      const ap = (ampm || '').toUpperCase();
      if (ap === 'PM' && hh < 12) hh += 12;
      if (ap === 'AM' && hh === 12) hh = 0;
      return String(hh).padStart(2, '0') + ':' + min;
    }

    let start = to24(m[1], m[2], m[3] || '');
    let end = to24(m[4], m[5], m[6] || '');
    if (parseInt(m[1], 10) >= 13) start = String(parseInt(m[1], 10)).padStart(2, '0') + ':' + m[2];
    if (parseInt(m[4], 10) >= 13) end = String(parseInt(m[4], 10)).padStart(2, '0') + ':' + m[5];
    return { start, end, time_raw: text, time_group_nums: timeGroupNums };
  }

  function isHeaderRow(cells) {
    const joined = cells.map(c => String(c || '').toLowerCase()).join(' | ');
    return joined.includes('day') && (joined.includes('module code') || joined.includes('module/course') || joined.includes('module'));
  }

  function findColMap(cells) {
    const map = {};
    cells.forEach((c, i) => {
      const t = String(c || '').toLowerCase().replace(/\s+/g, ' ').trim();
      if (t === 'day') map.day = i;
      else if (t === 'time') map.time = i;
      else if (t.includes('module code')) map.module_code = i;
      else if (t.includes('module/course') || t.includes('course name') || (t.includes('module') && t.includes('name'))) map.module_name = i;
      else if (t.includes('lecturer')) map.lecturers = i;
      else if (t.includes('room capacity') || t === 'capacity') map.room_capacity = i;
      else if (t.includes('class room') || t.includes('classroom') || t === 'room') map.classroom = i;
      else if (t.includes('number of students') || t.includes('no of students')) map.students = i;
    });
    return map;
  }

  function parseSectionTitle(text) {
    const t = String(text || '').replace(/\s+/g, ' ').trim();
    if (!/^year\s*\d+/i.test(t) && !/group\s*\d+/i.test(t)) return null;
    if (/^day\b/i.test(t)) return null;
    const yearM = t.match(/year\s*(\d+)/i);
    const year = yearM ? parseInt(yearM[1], 10) : 0;
    const afterYear = t.replace(/^year\s*\d+\s*[:\-]?\s*/i, '');
    const program_hint = afterYear.split(/,?\s*GROUP/i)[0].trim();
    return { title: t, year, program_hint, group_hint: t };
  }

  function parseSheetToSections(aoa) {
    const sections = [];
    let current = null;
    let colMap = null;
    let lastDay = '';

    for (let r = 0; r < aoa.length; r++) {
      const row = aoa[r] || [];
      const cells = row.map(c => (c == null ? '' : String(c).replace(/\r?\n/g, ' ').trim()));
      const first = cells[0] || '';
      const line = cells.filter(Boolean).join(' ').trim();
      if (!line) continue;

      const sec = parseSectionTitle(line) || parseSectionTitle(first);
      if (sec && !isHeaderRow(cells)) {
        current = { ...sec, rows: [] };
        sections.push(current);
        colMap = null;
        lastDay = '';
        continue;
      }

      if (isHeaderRow(cells)) {
        colMap = findColMap(cells);
        continue;
      }
      if (!current || !colMap) continue;

      const get = (key) => {
        const idx = colMap[key];
        return idx == null ? '' : (cells[idx] || '');
      };

      let day = get('day');
      if (day) lastDay = normalizeDayName(day);
      else day = lastDay;
      day = normalizeDayName(day);

      const timeRaw = get('time');
      if (!timeRaw && !get('module_code') && !get('module_name')) continue;
      const tm = parseExcelTime(timeRaw);
      if (!tm.start && !get('module_code')) continue;

      current.rows.push({
        day,
        start: tm.start,
        end: tm.end,
        time_raw: tm.time_raw,
        time_group_nums: tm.time_group_nums,
        module_code: get('module_code'),
        module_name: get('module_name'),
        lecturers: get('lecturers'),
        classroom: get('classroom'),
        room_capacity: parseInt(get('room_capacity'), 10) || null,
        students: parseInt(get('students'), 10) || null
      });
    }
    return sections.filter(s => s.rows.length > 0);
  }

  function refreshRowStatus(row) {
    const errors = [];
    const warnings = [];
    if (!row.day) errors.push('Missing day');
    if (!row.start || !row.end) errors.push('Missing time');
    if (row.start && row.end && row.start >= row.end) errors.push('End must be after start');
    if (!row.module) warnings.push('Pick a system module');
    if (!row.facility) warnings.push('Pick a facility');
    if (!(row.groups || []).length) warnings.push('No groups matched');
    // Keep excel-origin notes that aren't already fixed
    (row.warnings || []).forEach(w => {
      if (/module code not in system/i.test(w) && row.module) return;
      if (/facility not matched|no classroom/i.test(w) && row.facility) return;
      if (/pick a system module|pick a facility|no groups/i.test(w)) return;
      if (!warnings.includes(w) && !errors.includes(w)) warnings.push(w);
    });
    row.errors = errors;
    row.warnings = warnings;
    if (errors.length) row.status = 'error';
    else if (warnings.length) row.status = 'warning';
    else row.status = 'ok';
    return row;
  }

  function refreshSectionStats(sec) {
    let ok = 0, warnings = 0, errors = 0;
    (sec.rows || []).forEach(r => {
      refreshRowStatus(r);
      if (r.status === 'ok') ok++;
      else if (r.status === 'warning') { ok++; warnings++; }
      else errors++;
    });
    sec.stats = { rows: (sec.rows || []).length, ok, warnings, errors };
  }

  function requiredCapacity(sec) {
    const groups = sec?.groups || [];
    if (!groups.length) return 0;
    return groups.reduce((s, g) => s + (parseInt(g.size, 10) || 0), 0);
  }

  function modulesForSection(sec) {
    const pid = sec?.program?.id;
    const year = sec?.year;
    let list = modulesCache.slice();
    if (pid) {
      list = list.filter(m => String(m.program_id) === String(pid));
    }
    list = list.map(m => {
      const sameYear = year && String(m.year) === String(year);
      const sameSem = !SEM || String(m.semester) === String(SEM);
      return { ...m, _priority: !!(sameYear && sameSem) };
    });
    list.sort((a, b) => {
      if (a._priority !== b._priority) return a._priority ? -1 : 1;
      return String(a.code || a.name || '').localeCompare(String(b.code || b.name || ''));
    });
    return list;
  }

  function renderSections() {
    const $list = $('#sectionsList').empty();
    if (!importSections.length) {
      $('#sectionsEmpty').removeClass('d-none');
      $('#detailSection').addClass('d-none');
      return;
    }
    $('#sectionsEmpty').addClass('d-none');
    importSections.forEach((sec, idx) => {
      refreshSectionStats(sec);
      const st = sec.stats || {};
      const prog = sec.program ? escapeHtml(sec.program.name) : '<span class="text-danger">Program not matched</span>';
      $list.append(`
        <div class="sec-card ${activeIdx === idx ? 'active' : ''}" data-sec="${idx}">
          <div>
            <div class="title">Section ${idx + 1}: ${escapeHtml(sec.title || 'Untitled')}</div>
            <div class="meta">
              <span class="pill">${prog}</span>
              <span class="pill">Year ${sec.year || '?'}</span>
              <span class="pill">${(sec.groups || []).length} groups</span>
              <span class="pill">${st.rows || 0} rows</span>
              <span class="pill ok">${st.ok || 0} ready</span>
              <span class="pill warn">${st.warnings || 0} warn</span>
              <span class="pill err">${st.errors || 0} err</span>
            </div>
          </div>
          <button type="button" class="btn btn-sm btn-outline-primary" data-sec="${idx}">Open</button>
        </div>
      `);
    });
  }

  function lectTagsHtml(row) {
    const parts = [];
    if (row.lecturers?.leader) {
      parts.push(`<span class="lect-tag leader">${escapeHtml(lectName(row.lecturers.leader))} <span class="role">ML</span></span>`);
    }
    (row.lecturers?.others || []).forEach(o => {
      parts.push(`<span class="lect-tag other">${escapeHtml(lectName(o))}</span>`);
    });
    return parts.join('') || '<span class="text-muted small">None</span>';
  }

  function showSection(idx) {
    activeIdx = idx;
    const sec = importSections[idx];
    if (!sec) return;
    refreshSectionStats(sec);
    renderSections();
    $('#detailSection').removeClass('d-none');
    $('#detailTitle').text(`Section ${idx + 1}: ${sec.title || ''}`);
    $('#saveAlert').addClass('d-none');
    $('#conflictBox').addClass('d-none');

    const $meta = $('#sectionMeta').empty();
    if (sec.program) $meta.append(`<span class="pill"><i class="bi bi-mortarboard"></i> ${escapeHtml(sec.program.name)} <span class="text-muted">(${sec.program.match_score || '?'}%)</span></span>`);
    else $meta.append(`<span class="pill err">Program not matched</span>`);
    $meta.append(`<span class="pill">Year ${sec.year || '?'}</span>`);
    if ((sec.group_numbers || []).length) {
      $meta.append(`<span class="pill">Excel groups: ${(sec.group_numbers || []).join(', ')}</span>`);
    }
    (sec.groups || []).forEach(g => {
      $meta.append(`<span class="pill ok">${escapeHtml(g.name)} (${g.size || 0})</span>`);
    });

    const needs = !!sec.needs_groups || !(sec.groups || []).length;
    const $panel = $('#createGroupsPanel');
    if (needs) {
      $panel.removeClass('d-none');
      const $prog = $('#createProgramId').empty();
      const cands = sec.program_candidates || [];
      if (cands.length) {
        cands.forEach(c => $prog.append(`<option value="${c.id}">${escapeHtml(c.name)} (${c.score})</option>`));
      } else {
        ALL_PROGRAMS.forEach(p => {
          $prog.append(`<option value="${p.id}">${escapeHtml(p.name)}${p.code ? ' [' + escapeHtml(p.code) + ']' : ''}</option>`);
        });
      }
      if (sec.program?.id) $prog.val(String(sec.program.id));
      const tlm = (cands.length ? cands : ALL_PROGRAMS).find(p => /transport|logistics/i.test(p.name || ''));
      if (tlm && /transport|logistics/i.test(sec.title || '')) $prog.val(String(tlm.id));
      $('#createYear').val(sec.year || 1);
      $('#createGroupNums').val((sec.group_numbers || []).join(',') || '1');
      const sh = sec.size_hint || {};
      if (sh.size) $('#createSize').val(sh.size);
      if (sh.mode) $('#createSizeMode').val(sh.mode);
      $('#createStatus').text('');
    } else {
      $panel.addClass('d-none');
    }

    const $body = $('#matchBody').empty();
    (sec.rows || []).forEach((row, i) => {
      refreshRowStatus(row);
      const stCls = row.status === 'ok' ? 'text-success' : (row.status === 'warning' ? 'text-warning' : 'text-danger');
      const modLabel = row.module
        ? `<div><i class="bi bi-journal-text pick-icon"></i><strong>${escapeHtml(row.module.code || '')}</strong> ${escapeHtml(row.module.name || '')}</div>`
        : `<span class="text-muted"><i class="bi bi-search me-1"></i>Pick system module…</span>`;
      const facLabel = row.facility
        ? `<div><i class="bi bi-building pick-icon"></i><strong>${escapeHtml(row.facility.name)}</strong> <span class="text-muted">(${row.facility.capacity || '?'})</span></div>`
        : `<span class="text-muted"><i class="bi bi-search me-1"></i>Pick facility…</span>`;
      const grps = (row.groups || []).map(g => escapeHtml(g.name)).join(', ') || '—';
      const notes = [...(row.errors || []), ...(row.warnings || [])].map(escapeHtml).join('; ');
      $body.append(`
        <tr data-row="${i}">
          <td>${i + 1}</td>
          <td style="min-width:140px">
            <select class="form-select form-select-sm day-select mb-1">${dayOptionsHtml(row.day)}</select>
            <div class="d-flex gap-1">
              <select class="form-select form-select-sm start-select">${timeOptionsHtml(row.start, '08:00')}</select>
              <select class="form-select form-select-sm end-select">${timeOptionsHtml(row.end, '10:00')}</select>
            </div>
          </td>
          <td class="small">
            <div class="excel-sub">${escapeHtml(row.excel?.module_code || '')}</div>
            <div>${escapeHtml(row.excel?.module_name || '')}</div>
            <div class="excel-sub">${escapeHtml(row.excel?.classroom || '')}</div>
            <div class="excel-sub">${escapeHtml(row.excel?.lecturers || '')}</div>
          </td>
          <td>
            <button type="button" class="btn btn-outline-secondary picker-btn btn-pick-module">${modLabel}</button>
          </td>
          <td>
            <button type="button" class="btn btn-outline-secondary picker-btn btn-pick-facility">${facLabel}</button>
          </td>
          <td>
            <div class="mb-1 lect-preview">${lectTagsHtml(row)}</div>
            <button type="button" class="btn btn-outline-primary btn-sm w-100 btn-pick-lecturers">
              <i class="bi bi-people"></i> Edit lecturers
            </button>
          </td>
          <td class="small">${grps}</td>
          <td class="row-status ${stCls} small">${escapeHtml(row.status)}${notes ? '<div class="text-muted">' + notes + '</div>' : ''}</td>
        </tr>
      `);
    });
  }

  function activeRowData() {
    if (activeIdx == null || editRowIndex == null) return null;
    return importSections[activeIdx]?.rows?.[editRowIndex] || null;
  }

  function syncRowFromDom($tr) {
    const i = parseInt($tr.data('row'), 10);
    const row = importSections[activeIdx]?.rows?.[i];
    if (!row) return null;
    row.day = $tr.find('.day-select').val() || '';
    row.start = $tr.find('.start-select').val() || '';
    row.end = $tr.find('.end-select').val() || '';
    refreshRowStatus(row);
    const stCls = row.status === 'ok' ? 'text-success' : (row.status === 'warning' ? 'text-warning' : 'text-danger');
    const notes = [...(row.errors || []), ...(row.warnings || [])].map(escapeHtml).join('; ');
    $tr.find('.row-status').attr('class', `row-status ${stCls} small`)
      .html(`${escapeHtml(row.status)}${notes ? '<div class="text-muted">' + notes + '</div>' : ''}`);
    return row;
  }

  function openModulePicker(rowIndex) {
    editRowIndex = rowIndex;
    pickerMode = 'module';
    const sec = importSections[activeIdx];
    $('#pickerSearch').val('');
    $('.facility-only').addClass('d-none');
    $('#pickerModalTitle').text('Pick system module');
    const list = modulesForSection(sec);
    const excel = sec.rows[rowIndex]?.excel || {};
    $('#pickerHint').text(
      `${list.length} modules for program · Excel: ${excel.module_code || '—'} ${excel.module_name || ''}`
    );
    renderPickerList();
    pickerModal.show();
  }

  function openFacilityPicker(rowIndex) {
    editRowIndex = rowIndex;
    pickerMode = 'facility';
    const $tr = $(`#matchBody tr[data-row="${rowIndex}"]`);
    const row = syncRowFromDom($tr);
    if (!row?.day || !row?.start || !row?.end) {
      alert('Set day, start and end first.');
      return;
    }
    if (row.start >= row.end) {
      alert('End time must be after start time.');
      return;
    }
    const sec = importSections[activeIdx];
    const req = requiredCapacity(sec);
    $('#pickerSearch').val('');
    $('#pickerMinCap').val('');
    $('#pickerFitRequired').prop('checked', false);
    $('#pickerRequiredCap').text(req);
    $('.facility-only').removeClass('d-none');
    $('#pickerModalTitle').text(`Free facilities — ${row.day} ${row.start}-${row.end}`);
    $('#pickerHint').text('Loading free facilities…');
    $('#pickerList').html('<div class="text-center py-4 text-muted">Loading…</div>');
    pickerModal.show();

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
        sessions: JSON.stringify([{ day: row.day, start: row.start, end: row.end }]),
        minCapacity: 0
      }
    }).done(function (json) {
      facilityCache = Array.isArray(json?.data) ? json.data : [];
      facilityCache.sort((a, b) => (b.capacity || 0) - (a.capacity || 0));
      $('#pickerHint').text(`${facilityCache.length} free facilities (approved/pending bookings excluded). Required students: ${req}.`);
      renderPickerList();
    }).fail(function () {
      facilityCache = [];
      $('#pickerHint').text('Failed to load facilities.');
      $('#pickerList').html('<div class="alert alert-danger mb-0">Failed to load facilities.</div>');
    });
  }

  function openLecturerPicker(rowIndex) {
    editRowIndex = rowIndex;
    const row = importSections[activeIdx]?.rows?.[rowIndex];
    if (!row) return;
    draftLecturers = {
      leader: row.lecturers?.leader ? { ...row.lecturers.leader } : null,
      others: (row.lecturers?.others || []).map(o => ({ ...o }))
    };
    $('#lectModalSearch').val('');
    renderLecturerModalTags();
    renderLecturerModalList();
    lecturerModal.show();
  }

  function renderPickerList() {
    const q = ($('#pickerSearch').val() || '').toLowerCase().trim();
    const $list = $('#pickerList').empty();
    const sec = importSections[activeIdx];
    const req = requiredCapacity(sec);

    if (pickerMode === 'module') {
      const items = modulesForSection(sec).filter(m => {
        if (!q) return true;
        return `${m.code || ''} ${m.name || ''} ${m.program_name || ''}`.toLowerCase().includes(q);
      }).slice(0, 500);
      if (!items.length) {
        $list.html('<div class="text-muted">No modules found. Check program match / module catalog.</div>');
        return;
      }
      let shownP = false, shownO = false;
      items.forEach(m => {
        if (m._priority && !shownP) { $list.append('<div class="section-divider-label">Recommended — Year / Semester</div>'); shownP = true; }
        if (!m._priority && !shownO) { $list.append('<div class="section-divider-label">Other program modules</div>'); shownO = true; }
        $list.append(`
          <div class="picker-item" data-type="module" data-id="${m.id}">
            <div class="fw-semibold">${escapeHtml(m.code ? m.code + ' — ' : '')}${escapeHtml(m.name)}</div>
            <div class="small text-muted">Year ${m.year ?? 'N/A'} · Sem ${m.semester ?? 'N/A'}</div>
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
        return `${f.name || ''} ${f.site_name || ''} ${f.buildname || ''}`.toLowerCase().includes(q);
      });
      if (!items.length) {
        $list.html('<div class="text-muted">No free facilities match.</div>');
        return;
      }
      items.forEach(f => {
        const cap = f.capacity || 0;
        const ok = !req || cap >= req;
        $list.append(`
          <div class="picker-item ${ok ? 'ok-cap' : 'too-small'}" data-type="facility" data-id="${f.id}">
            <div class="d-flex justify-content-between gap-2">
              <div>
                <div class="fw-semibold">${escapeHtml(f.name)}</div>
                <div class="small text-muted">${escapeHtml(f.site_name || '')} · ${escapeHtml(f.buildname || '')}</div>
              </div>
              <span class="cap-badge">${cap} seats</span>
            </div>
          </div>
        `);
      });
    }
  }

  function renderLecturerModalTags() {
    const $tags = $('#lectModalTags').empty();
    if (!draftLecturers.leader && !draftLecturers.others.length) {
      $tags.html('<span class="text-muted small">None yet — use Module Leader / Add Lecturer below</span>');
      return;
    }
    if (draftLecturers.leader) {
      $tags.append(`
        <span class="lect-tag leader" data-role="leader" data-id="${draftLecturers.leader.id}">
          ${escapeHtml(lectName(draftLecturers.leader))}
          <span class="role">Module Leader</span>
          <button type="button" title="Remove">&times;</button>
        </span>
      `);
    }
    draftLecturers.others.forEach(l => {
      $tags.append(`
        <span class="lect-tag other" data-role="other" data-id="${l.id}">
          ${escapeHtml(lectName(l))}
          <span class="role">Lecturer</span>
          <button type="button" title="Remove">&times;</button>
        </span>
      `);
    });
  }

  function renderLecturerModalList() {
    const q = ($('#lectModalSearch').val() || '').toLowerCase().trim();
    const $list = $('#lectModalList').empty();
    const items = lecturersCache.filter(l => {
      if (!q) return true;
      return `${l.names || ''} ${l.name || ''} ${l.email || ''} ${l.ur_email || ''}`.toLowerCase().includes(q);
    }).slice(0, 400);
    $('#lectModalHint').text(`${items.length} shown of ${lecturersCache.length}`);
    if (!items.length) {
      $list.html('<div class="text-muted">No lecturers match.</div>');
      return;
    }
    items.forEach(l => {
      const isLeader = draftLecturers.leader && String(draftLecturers.leader.id) === String(l.id);
      const isOther = draftLecturers.others.some(o => String(o.id) === String(l.id));
      const rowClass = isLeader ? 'selected-leader' : (isOther ? 'selected-other' : '');
      $list.append(`
        <div class="lect-row ${rowClass}" data-id="${l.id}">
          <div>
            <div class="fw-semibold">${escapeHtml(lectName(l))}</div>
            <div class="small text-muted">${escapeHtml(l.ur_email || l.email || '')}</div>
          </div>
          <div class="lect-actions">
            <button type="button" class="btn btn-primary btn-sm btn-set-leader" ${isLeader ? 'disabled' : ''}>
              ${isLeader ? 'Module Leader ✓' : 'Module Leader'}
            </button>
            <button type="button" class="btn btn-success btn-sm btn-add-lecturer" ${isLeader || isOther ? 'disabled' : ''}>
              ${isOther ? 'Lecturer ✓' : 'Add Lecturer'}
            </button>
          </div>
        </div>
      `);
    });
  }

  function applyDraftLecturers() {
    const row = activeRowData();
    if (!row) return;
    row.lecturers = {
      leader: draftLecturers.leader,
      others: draftLecturers.others.slice()
    };
    if (parsedSections[activeIdx]?.rows?.[editRowIndex]) {
      parsedSections[activeIdx].rows[editRowIndex].forced_leader_id = draftLecturers.leader?.id || 0;
      parsedSections[activeIdx].rows[editRowIndex].forced_other_lecturer_ids =
        (draftLecturers.others || []).map(o => o.id).filter(Boolean);
    }
    const $tr = $(`#matchBody tr[data-row="${editRowIndex}"]`);
    $tr.find('.lect-preview').html(lectTagsHtml(row));
    syncRowFromDom($tr);
  }

  async function rematchSections(sectionsPayload) {
    const res = await fetch('match_bulk_import.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ sections: sectionsPayload, semester: SEM })
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Match failed');
    importSections = data.sections || [];
    importSections.forEach(refreshSectionStats);
    renderSections();
    if (activeIdx != null && importSections[activeIdx]) showSection(activeIdx);
    else if (importSections.length) showSection(0);
    return data;
  }

  function fmtTime(t) { return String(t || '').slice(0, 5); }

  function renderConflicts(conf, rowNum) {
    const f = conf.facility || [];
    const g = conf.groups || {};
    let html = `<div class="mb-2"><strong>Row ${rowNum}</strong>`;
    if (f.length) {
      html += `<div class="fw-semibold mt-1">Facility</div><ul>`;
      f.forEach(r => html += `<li>${escapeHtml(r.day)} ${fmtTime(r.start_time)}–${fmtTime(r.end_time)} (#${r.timetable_id || 'batch'})</li>`);
      html += `</ul>`;
    }
    Object.keys(g).forEach(k => {
      html += `<div class="fw-semibold">Group ${escapeHtml(k)}</div><ul>`;
      (g[k] || []).forEach(r => html += `<li>${escapeHtml(r.day)} ${fmtTime(r.start_time)}–${fmtTime(r.end_time)}</li>`);
      html += `</ul>`;
    });
    html += `</div>`;
    return html;
  }

  async function saveActiveSection() {
    const sec = importSections[activeIdx];
    if (!sec) return;
    if (!AY || !SEM) {
      alert('Academic year / semester not configured.');
      return;
    }

    // Sync all day/time from DOM
    $('#matchBody tr').each(function () { syncRowFromDom($(this)); });

    let groups = sec.groups || [];
    if (!groups.length) {
      const map = new Map();
      (sec.rows || []).forEach(r => (r.groups || []).forEach(g => map.set(String(g.id), g)));
      groups = Array.from(map.values());
    }
    if (!groups.length) {
      alert('No groups for this section. Create intake/groups first.');
      return;
    }

    const rows = (sec.rows || [])
      .filter(r => r.module && r.day && r.start && r.end && r.facility)
      .map(r => ({
        module_id: r.module.id,
        day: r.day,
        start: r.start,
        end: r.end,
        facility_id: r.facility.id,
        leader_id: r.lecturers?.leader?.id || 0,
        other_lecturer_ids: (r.lecturers?.others || []).map(o => o.id).filter(Boolean)
      }));

    const skipped = (sec.rows || []).length - rows.length;
    if (!rows.length) {
      alert('No complete rows to save. Each row needs module + facility + day/time (use Pick buttons).');
      return;
    }

    let msg = `Save ${rows.length} plan(s) for ${groups.length} group(s)?`;
    if (skipped) msg += `\n(${skipped} incomplete row(s) will be skipped)`;
    if (!confirm(msg)) return;

    const $alert = $('#saveAlert').removeClass('d-none alert-success alert-danger alert-warning').addClass('alert-info').text('Saving…');
    $('#conflictBox').addClass('d-none');
    $('#conflictBody').empty();

    try {
      const res = await fetch('save_timetable_bulk.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          academic_year_id: AY,
          semester: SEM,
          groupIds: groups.map(g => g.id),
          selectedGroupIds: groups.map(g => g.id),
          rows
        })
      });
      const data = await res.json();
      let conflictHtml = '';
      (data.results || []).forEach(r => {
        if (r.status === 'conflict' && r.conflicts) {
          conflictHtml += renderConflicts(r.conflicts, (r.row_index || 0) + 1);
        }
      });
      if (conflictHtml) {
        $('#conflictBody').html(conflictHtml);
        $('#conflictBox').removeClass('d-none');
      }
      const cls = data.status === 'success' ? 'alert-success' : (data.status === 'partial' ? 'alert-warning' : 'alert-danger');
      $alert.removeClass('alert-info').addClass(cls).text(data.message || 'Done');
    } catch (e) {
      console.error(e);
      $alert.removeClass('alert-info').addClass('alert-danger').text('Network error while saving.');
    }
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
  $('#excelFile').on('change', function () {
    const f = this.files && this.files[0];
    fileBuffer = null;
    importSections = [];
    parsedSections = [];
    activeIdx = null;
    renderSections();
    $('#btnClear').prop('disabled', !f);
    $('#btnParse').prop('disabled', true);
    if (!f) { setStatus(''); return; }
    setStatus('Reading file…');
    const reader = new FileReader();
    reader.onload = function (e) {
      fileBuffer = e.target.result;
      $('#btnParse').prop('disabled', false);
      setStatus(`Ready: ${f.name}. Click Parse & match.`);
    };
    reader.onerror = () => setStatus('Failed to read file.', 'text-danger');
    reader.readAsArrayBuffer(f);
  });

  $('#btnClear').on('click', function () {
    $('#excelFile').val('');
    fileBuffer = null;
    importSections = [];
    parsedSections = [];
    activeIdx = null;
    renderSections();
    $('#btnParse').prop('disabled', true);
    $('#btnClear').prop('disabled', true);
    setStatus('');
  });

  $('#btnParse').on('click', async function () {
    if (!fileBuffer) return;
    if (typeof XLSX === 'undefined') {
      alert('Excel library failed to load. Refresh the page.');
      return;
    }
    $('#btnParse').prop('disabled', true);
    setStatus('Parsing Excel…');
    try {
      const wb = XLSX.read(fileBuffer, { type: 'array' });
      const aoa = XLSX.utils.sheet_to_json(wb.Sheets[wb.SheetNames[0]], { header: 1, defval: '', raw: false });
      const parsed = parseSheetToSections(aoa);
      if (!parsed.length) {
        setStatus('No timetable sections found. Check Year/Group headers and Day/Time/Module columns.', 'text-danger');
        $('#btnParse').prop('disabled', false);
        return;
      }
      parsedSections = parsed;
      setStatus(`Parsed ${parsed.length} section(s). Matching…`);
      await rematchSections(parsedSections);
      setStatus(`Matched ${importSections.length} section(s). Fix any warnings with Pick buttons, then Save.`, 'text-success');
    } catch (err) {
      console.error(err);
      setStatus('Parse/match error: ' + (err.message || err), 'text-danger');
    }
    $('#btnParse').prop('disabled', false);
  });

  $('#btnCreateIntakeGroups').on('click', async function () {
    const sec = importSections[activeIdx];
    if (!sec) return;
    const program_id = parseInt($('#createProgramId').val(), 10);
    const year_of_study = parseInt($('#createYear').val(), 10);
    const campus_id = parseInt($('#createCampusId').val(), 10);
    const group_numbers = String($('#createGroupNums').val() || '')
      .split(/[,&\s]+/)
      .map(x => parseInt(x, 10))
      .filter(n => n > 0);
    const size_each = parseInt($('#createSize').val(), 10);
    const size_mode = $('#createSizeMode').val();

    if (!program_id || !year_of_study || !campus_id || !group_numbers.length || !size_each) {
      alert('Fill program, year, campus, group numbers and size.');
      return;
    }
    if (!confirm(`Create Group ${group_numbers.join(' & ')} for year ${year_of_study}?`)) return;

    // Capture current edits into parsed payload before rematch
    $('#matchBody tr').each(function () {
      const $tr = $(this);
      const ri = parseInt($tr.data('row'), 10);
      const row = syncRowFromDom($tr);
      if (!parsedSections[activeIdx]) return;
      if (!parsedSections[activeIdx].rows) parsedSections[activeIdx].rows = [];
      if (!parsedSections[activeIdx].rows[ri]) {
        parsedSections[activeIdx].rows[ri] = {
          day: row.day, start: row.start, end: row.end,
          module_code: row.excel?.module_code || '',
          module_name: row.excel?.module_name || '',
          lecturers: row.excel?.lecturers || '',
          classroom: row.excel?.classroom || '',
          time_group_nums: []
        };
      }
      const pr = parsedSections[activeIdx].rows[ri];
      pr.day = row.day; pr.start = row.start; pr.end = row.end;
      if (row.module?.id) pr.forced_module_id = row.module.id;
      if (row.facility?.id) pr.forced_facility_id = row.facility.id;
      if (row.lecturers?.leader?.id) pr.forced_leader_id = row.lecturers.leader.id;
      pr.forced_other_lecturer_ids = (row.lecturers?.others || []).map(o => o.id).filter(Boolean);
    });

    $('#createStatus').text('Creating…');
    $('#btnCreateIntakeGroups').prop('disabled', true);
    try {
      const res = await fetch('import_create_intake.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ program_id, year_of_study, campus_id, group_numbers, size_each, size_mode })
      });
      const data = await res.json();
      if (!data.success) throw new Error(data.message || 'Create failed');

      const payload = parsedSections.map((s, i) => {
        const copy = { ...s, rows: (s.rows || []).map(r => ({ ...r })) };
        if (i === activeIdx) copy.forced_program_id = program_id;
        return copy;
      });
      await rematchSections(payload);
      $('#createStatus').text((data.message || 'Created') + ' Rematched.');
      setStatus('Groups created. Review picks, then Save.', 'text-success');
    } catch (err) {
      console.error(err);
      $('#createStatus').text(err.message || 'Failed');
      alert(err.message || 'Failed to create intake/groups');
    }
    $('#btnCreateIntakeGroups').prop('disabled', false);
  });

  $('#sectionsList').on('click', '[data-sec]', function () {
    const idx = parseInt($(this).data('sec'), 10);
    if (Number.isFinite(idx)) showSection(idx);
  });

  $('#matchBody').on('change', '.day-select, .start-select, .end-select', function () {
    const $tr = $(this).closest('tr');
    const row = syncRowFromDom($tr);
    // Changing schedule clears facility (availability changes)
    if ($(this).is('.day-select, .start-select, .end-select') && row) {
      row.facility = null;
      $tr.find('.btn-pick-facility').html('<span class="text-muted"><i class="bi bi-search me-1"></i>Pick facility…</span>');
      syncRowFromDom($tr);
    }
  });

  $('#matchBody').on('click', '.btn-pick-module', function () {
    openModulePicker(parseInt($(this).closest('tr').data('row'), 10));
  });
  $('#matchBody').on('click', '.btn-pick-facility', function () {
    openFacilityPicker(parseInt($(this).closest('tr').data('row'), 10));
  });
  $('#matchBody').on('click', '.btn-pick-lecturers', function () {
    openLecturerPicker(parseInt($(this).closest('tr').data('row'), 10));
  });

  $('#pickerSearch, #pickerMinCap, #pickerFitRequired').on('input change', renderPickerList);

  $('#pickerList').on('click', '.picker-item', function () {
    const type = $(this).data('type');
    const id = $(this).data('id');
    const row = activeRowData();
    if (!row) return;
    if (type === 'module') {
      const m = modulesCache.find(x => String(x.id) === String(id));
      if (!m) return;
      row.module = {
        id: m.id, code: m.code, name: m.name,
        year: m.year, semester: m.semester, program_id: m.program_id, credits: m.credits
      };
      if (parsedSections[activeIdx]?.rows?.[editRowIndex]) {
        parsedSections[activeIdx].rows[editRowIndex].forced_module_id = m.id;
      }
      pickerModal.hide();
      showSection(activeIdx);
    } else if (type === 'facility') {
      const f = facilityCache.find(x => String(x.id) === String(id));
      if (!f) return;
      row.facility = {
        id: f.id, name: f.name, capacity: f.capacity,
        site_name: f.site_name || '', buildname: f.buildname || ''
      };
      if (parsedSections[activeIdx]?.rows?.[editRowIndex]) {
        parsedSections[activeIdx].rows[editRowIndex].forced_facility_id = f.id;
      }
      pickerModal.hide();
      showSection(activeIdx);
    }
  });

  $('#lectModalSearch').on('input', renderLecturerModalList);

  $('#lectModalList').on('click', '.btn-set-leader', function () {
    const id = $(this).closest('.lect-row').data('id');
    const l = lecturersCache.find(x => String(x.id) === String(id));
    if (!l) return;
    draftLecturers.others = draftLecturers.others.filter(o => String(o.id) !== String(id));
    draftLecturers.leader = { id: l.id, names: l.names || l.name, email: l.email, ur_email: l.ur_email };
    renderLecturerModalTags();
    renderLecturerModalList();
    applyDraftLecturers();
  });

  $('#lectModalList').on('click', '.btn-add-lecturer', function () {
    const id = $(this).closest('.lect-row').data('id');
    const l = lecturersCache.find(x => String(x.id) === String(id));
    if (!l) return;
    if (draftLecturers.leader && String(draftLecturers.leader.id) === String(id)) return;
    if (!draftLecturers.others.some(o => String(o.id) === String(id))) {
      draftLecturers.others.push({ id: l.id, names: l.names || l.name, email: l.email, ur_email: l.ur_email });
    }
    renderLecturerModalTags();
    renderLecturerModalList();
    applyDraftLecturers();
  });

  $('#lectModalTags').on('click', '.lect-tag button', function () {
    const $tag = $(this).closest('.lect-tag');
    const role = $tag.data('role');
    const id = $tag.data('id');
    if (role === 'leader') draftLecturers.leader = null;
    else draftLecturers.others = draftLecturers.others.filter(o => String(o.id) !== String(id));
    renderLecturerModalTags();
    renderLecturerModalList();
    applyDraftLecturers();
  });

  $('#btnClearRowLecturers').on('click', function () {
    draftLecturers = { leader: null, others: [] };
    renderLecturerModalTags();
    renderLecturerModalList();
    applyDraftLecturers();
  });

  $('#btnSaveSection').on('click', saveActiveSection);

  pickerModal = new bootstrap.Modal(document.getElementById('pickerModal'));
  lecturerModal = new bootstrap.Modal(document.getElementById('lecturerModal'));

  Promise.all([loadModules(), loadLecturers()]).then(() => {
    setStatus('Catalogs loaded. Upload an Excel file to begin.');
  });
})();
</script>
</body>
</html>
