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
    :root {
      --bulk-navy: #031f50;
      --bulk-navy-2: #012a70;
      --bulk-surface: #f4f6f9;
      --bulk-line: #e2e8f0;
    }
    .page-title {
      background-color: var(--bulk-navy) !important;
      color: #fff !important;
      padding: 12px 16px !important;
      border-radius: 8px !important;
      border: 0;
    }
    .page-title h2 { font-size: 1.05rem; margin: 0; color: #fff; font-weight: 600; }

    .bulk-section {
      border: 1px solid var(--bulk-line);
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 1px 2px rgba(3, 31, 80, 0.04);
    }
    .bulk-section-header {
      background: linear-gradient(90deg, var(--bulk-navy) 0%, #0a3a7a 100%);
      color: #fff;
      padding: 12px 16px;
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
    }
    .bulk-section-header .step-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 26px; height: 26px;
      border-radius: 50%;
      background: rgba(255,255,255,0.18);
      font-weight: 700;
      font-size: 0.85rem;
      margin-right: 8px;
    }
    .bulk-section-body { background: #fff; padding: 16px; }

    .selector-panel {
      background: var(--bulk-surface);
      border: 1px solid var(--bulk-line);
      border-radius: 10px;
      padding: 12px;
      height: 100%;
    }
    .selector-panel .panel-title {
      font-size: 0.78rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      color: var(--bulk-navy);
      margin-bottom: 8px;
    }
    #programSelect {
      font-size: 0.82rem;
      border-radius: 8px;
      border-color: #cbd5e1;
    }
    #groupsCheckList {
      max-height: 180px;
      overflow: auto;
      background: #fff;
      border: 1px solid var(--bulk-line) !important;
      border-radius: 8px !important;
      padding: 8px !important;
    }
    .group-check-item {
      display: flex;
      align-items: flex-start;
      gap: 8px;
      padding: 8px 10px;
      border-radius: 8px;
      border: 1px solid transparent;
      margin-bottom: 4px;
    }
    .group-check-item:hover { background: #eef3fb; border-color: #d6e2f5; }
    .group-check-item .g-name { font-weight: 600; font-size: 0.88rem; color: #0f172a; }
    .group-check-item .g-meta { font-size: 0.75rem; color: #64748b; }

    .selected-summary {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 10px;
      margin-bottom: 14px;
    }
    @media (max-width: 768px) {
      .selected-summary { grid-template-columns: 1fr; }
    }
    .summary-stat {
      background: var(--bulk-surface);
      border: 1px solid var(--bulk-line);
      border-radius: 10px;
      padding: 12px 14px;
    }
    .summary-stat .label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.04em; color: #64748b; font-weight: 600; }
    .summary-stat .value { font-size: 1.35rem; font-weight: 700; color: var(--bulk-navy); line-height: 1.2; }
    .summary-stat .hint { font-size: 0.75rem; color: #64748b; margin-top: 2px; }

    #selectedGroupsEmpty {
      border: 1px dashed #cbd5e1;
      border-radius: 10px;
      padding: 18px;
      text-align: center;
      color: #64748b;
      background: #fafbfc;
    }
    .selected-group-card {
      border: 1px solid var(--bulk-line);
      border-radius: 10px;
      padding: 12px 14px;
      background: #fff;
      display: flex;
      justify-content: space-between;
      gap: 12px;
      align-items: flex-start;
      margin-bottom: 8px;
      box-shadow: 0 1px 0 rgba(15, 23, 42, 0.03);
    }
    .selected-group-card .sg-title {
      font-weight: 700;
      color: var(--bulk-navy);
      font-size: 0.95rem;
    }
    .selected-group-card .sg-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 6px 10px;
      margin-top: 6px;
    }
    .meta-pill {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-size: 0.72rem;
      background: #eef2ff;
      color: #1e3a8a;
      border-radius: 6px;
      padding: 3px 8px;
      font-weight: 550;
    }
    .meta-pill.cap { background: #ecfdf5; color: #047857; }
    .meta-pill.year { background: #fff7ed; color: #c2410c; }
    .btn-remove-group {
      border: 0;
      background: #fee2e2;
      color: #b91c1c;
      width: 28px; height: 28px;
      border-radius: 8px;
      line-height: 1;
      flex-shrink: 0;
    }
    .btn-remove-group:hover { background: #fecaca; }

    #bulkTable {
      border-collapse: separate;
      border-spacing: 0;
      margin: 0;
    }
    #bulkTable thead th {
      white-space: nowrap;
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.03em;
      background: #eef2f7;
      color: #334155;
      border-color: var(--bulk-line);
      padding: 10px 8px;
      position: sticky;
      top: 0;
      z-index: 1;
    }
    #bulkTable td {
      vertical-align: middle;
      border-color: var(--bulk-line);
      padding: 8px;
      background: #fff;
    }
    #bulkTable .col-module, #bulkTable .col-facility { min-width: 200px; }
    #bulkTable .col-lecturers { min-width: 230px; max-width: 300px; }
    #bulkTable tbody tr:hover td { background: #f8fafc; }
    .picker-btn {
      width: 100%;
      text-align: left;
      white-space: normal;
      font-size: 0.82rem;
      min-height: 52px;
      border-radius: 8px;
      border: 1px dashed #94a3b8;
      background: #f8fafc;
      color: #0f172a;
      padding: 8px 10px;
    }
    .picker-btn:hover {
      border-style: solid;
      border-color: var(--bulk-navy-2);
      background: #eef3fb;
    }
    .picker-btn.has-value {
      border-style: solid;
      border-color: #cbd5e1;
      background: #fff;
    }
    .picker-btn .muted { color: #64748b; font-size: 0.72rem; margin-top: 2px; }
    .picker-btn .pick-icon { color: var(--bulk-navy-2); margin-right: 4px; }
    .row-status {
      font-size: 0.72rem;
      font-weight: 600;
      padding: 4px 8px;
      border-radius: 999px;
      display: inline-block;
      background: #f1f5f9;
      color: #475569;
    }
    .row-status.text-success { background: #dcfce7; color: #166534 !important; }
    .row-status.text-danger { background: #fee2e2; color: #b91c1c !important; }
    .row-ok td { background-color: #f0fdf4 !important; }
    .row-fail td { background-color: #fef2f2 !important; }

    .lect-tag {
      display: inline-flex; align-items: center; gap: 4px;
      border-radius: 999px; padding: 3px 8px; margin: 2px; font-size: 0.72rem; font-weight: 600;
    }
    .lect-tag.leader { background: var(--bulk-navy); color: #fff; }
    .lect-tag.other { background: #e8f5e9; color: #198754; border: 1px solid #a5d6a7; }
    .lect-tag .role { opacity: 0.85; font-weight: 500; font-size: 0.65rem; }
    .lect-tag button { border: 0; background: transparent; color: inherit; line-height: 1; padding: 0 0 0 2px; }
    .lecturers-cell .btn-pick-lecturers {
      margin-top: 6px;
      border-radius: 8px;
      font-size: 0.78rem;
    }

    .picker-modal .modal-header {
      background: var(--bulk-navy);
      color: #fff;
    }
    .picker-modal .modal-header .btn-close { filter: invert(1); }
    .picker-modal .modal-title { font-size: 1rem; font-weight: 600; }
    .picker-list { max-height: 420px; overflow: auto; padding-right: 4px; }
    .picker-item {
      cursor: pointer;
      border: 1px solid var(--bulk-line);
      border-radius: 10px;
      padding: 12px 14px;
      margin-bottom: 8px;
      transition: background .15s, border-color .15s, transform .1s;
      background: #fff;
    }
    .picker-item:hover {
      background: #eef3fb;
      border-color: #93c5fd;
      transform: translateY(-1px);
    }
    .picker-item .cap-badge {
      background: var(--bulk-navy); color: #fff; border-radius: 999px;
      padding: 3px 10px; font-size: 0.75rem; font-weight: 600;
      white-space: nowrap;
    }
    .picker-item.too-small { opacity: 0.75; }
    .picker-item.too-small .cap-badge { background: #dc3545; }
    .picker-item.ok-cap .cap-badge { background: #198754; }
    .section-divider-label {
      font-size: 0.72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      color: #64748b;
      margin: 10px 0 8px;
      padding-bottom: 4px;
      border-bottom: 1px solid var(--bulk-line);
    }
    .lect-row {
      display: flex; justify-content: space-between; align-items: center; gap: 8px;
      border: 1px solid var(--bulk-line); border-radius: 10px; padding: 10px 12px; margin-bottom: 8px;
      background: #fff;
    }
    .lect-row .lect-actions { flex-shrink: 0; display: flex; gap: 4px; flex-wrap: wrap; }
    .lect-row .lect-actions .btn { font-size: 0.72rem; padding: 3px 8px; min-width: auto; }
    .lect-row.selected-leader { background: #eef2ff; border-color: #c5d2ff; }
    .lect-row.selected-other { background: #f0fdf4; border-color: #bbf7d0; }
    .table-wrap {
      border: 1px solid var(--bulk-line);
      border-radius: 10px;
      overflow: auto;
      max-height: min(70vh, 720px);
    }
    .hint-bar {
      font-size: 0.8rem;
      color: #475569;
      background: #f8fafc;
      border: 1px solid var(--bulk-line);
      border-radius: 8px;
      padding: 10px 12px;
      margin-top: 12px;
    }
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
      <a href="timetable_import_excel.php" class="btn btn-outline-light btn-sm">Import Excel</a>
      <a href="timetable_set.php" class="btn btn-outline-light btn-sm">Single plan</a>
      <button type="button" id="btnClearBulk" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i> Reset</button>
    </div>
  </div>

  <!-- 1. Groups -->
  <div class="bulk-section mb-3">
    <div class="bulk-section-header">
      <div class="d-flex align-items-center">
        <span class="step-badge">1</span>
        <div>
          <div class="fw-semibold">Select groups</div>
          <div class="small" style="opacity:.85">Shared for all plan rows below</div>
        </div>
      </div>
    </div>
    <div class="bulk-section-body">
      <div class="row g-3">
        <div class="col-lg-4">
          <div class="selector-panel">
            <div class="panel-title"><i class="bi bi-mortarboard me-1"></i> Program</div>
            <input type="search" id="programSearch" class="form-control form-control-sm mb-2" placeholder="Search program, school, college, campus...">
            <select id="programSelect" class="form-select form-select-sm" size="7">
              <option value="">-- Select program --</option>
            </select>
            <div class="small text-muted mt-2"><span id="programMatchCount">0</span> programs shown</div>
          </div>
        </div>
        <div class="col-lg-3">
          <div class="selector-panel">
            <div class="panel-title"><i class="bi bi-calendar3 me-1"></i> Intake</div>
            <select id="intakeSelect" class="form-select form-select-sm" disabled>
              <option value="">-- Select intake --</option>
            </select>
            <div id="intakeHint" class="small text-muted mt-2">Year of study &amp; campus</div>
            <div id="intakeEmptyAlert" class="alert alert-warning py-2 px-3 mt-2 mb-0 d-none small" role="alert"></div>
          </div>
        </div>
        <div class="col-lg-5">
          <div class="selector-panel">
            <div class="panel-title"><i class="bi bi-people me-1"></i> Available groups</div>
            <div id="groupsCheckList">
              <span class="text-muted small">Select a program and intake first</span>
            </div>
          </div>
        </div>
      </div>

      <hr class="my-3">

      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
        <h6 class="mb-0 fw-semibold text-dark"><i class="bi bi-check2-square me-1 text-success"></i> Selected groups overview</h6>
        <button type="button" id="btnClearSelectedGroups" class="btn btn-outline-danger btn-sm" disabled>
          <i class="bi bi-x-circle"></i> Clear selected
        </button>
      </div>

      <div class="selected-summary">
        <div class="summary-stat">
          <div class="label">Groups</div>
          <div class="value" id="selectedGroupCount">0</div>
          <div class="hint">Checked for all rows</div>
        </div>
        <div class="summary-stat">
          <div class="label">Total students</div>
          <div class="value" id="requiredCapacity">0</div>
          <div class="hint">Required facility capacity</div>
        </div>
        <div class="summary-stat">
          <div class="label">Programs</div>
          <div class="value" id="selectedProgramCount">0</div>
          <div class="hint" id="selectedProgramHint">None yet</div>
        </div>
      </div>

      <div id="selectedGroupsEmpty">
        <i class="bi bi-info-circle me-1"></i> No groups selected yet. Search a program, pick an intake, then check groups.
      </div>
      <div id="selectedGroupCards"></div>

      <div class="hint-bar">
        Modules are limited to selected program(s); year/semester matches appear first.
        Facility &amp; group time conflicts are blocked; lecturer overlaps are allowed.
      </div>
    </div>
  </div>

  <!-- 2. Plan rows -->
  <div class="bulk-section mb-3">
    <div class="bulk-section-header">
      <div class="d-flex align-items-center">
        <span class="step-badge">2</span>
        <div>
          <div class="fw-semibold">Add plan rows</div>
          <div class="small" style="opacity:.85">Click Module / Facility / Lecturers to search in modals</div>
        </div>
      </div>
      <div class="d-flex gap-2">
        <button type="button" id="btnAddRow" class="btn btn-sm btn-light"><i class="bi bi-plus-lg"></i> Add row</button>
        <button type="button" id="btnSaveAll" class="btn btn-sm btn-success"><i class="bi bi-save"></i> Save all</button>
      </div>
    </div>
    <div class="bulk-section-body">
      <div class="table-wrap">
        <table class="table table-bordered table-sm align-middle mb-0" id="bulkTable">
          <thead>
            <tr>
              <th style="width:40px">#</th>
              <th class="col-module">Module</th>
              <th>Day</th>
              <th>Start</th>
              <th>End</th>
              <th class="col-facility">Facility</th>
              <th class="col-lecturers">Lecturers</th>
              <th>Status</th>
              <th style="width:90px">Actions</th>
            </tr>
          </thead>
          <tbody id="bulkTableBody"></tbody>
        </table>
      </div>
      <div class="hint-bar mb-0">
        <i class="bi bi-lightning-charge me-1"></i>
        Use searchable modals for module, free facility, and module leader / lecturers.
        Changing day or time clears the facility so you can re-check availability.
        For Excel sync, use <a href="timetable_import_excel.php">Import Timetable Excel</a>.
      </div>
    </div>
  </div>

  <div id="bulkResultAlert" class="alert d-none" role="alert"></div>
  <div id="bulkConflictDetails" class="card d-none mb-3 border-danger">
    <div class="card-header text-danger fw-semibold">Conflict details</div>
    <div class="card-body small" id="bulkConflictBody"></div>
  </div>
</main>

<!-- Preview before save (same idea as timetable_set.php) -->
<div class="modal fade picker-modal" id="bulkPreviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Preview bulk timetable</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="bulkPreviewContent"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="btnConfirmBulkSave">
          <i class="bi bi-check-circle"></i> Confirm &amp; Save
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Shared searchable picker modal (module / facility) -->
<div class="modal fade picker-modal" id="pickerModal" tabindex="-1" aria-hidden="true">
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
            <input type="number" id="pickerMinCap" class="form-control" min="0" placeholder="e.g. 400">
          </div>
          <div class="col-md-3 facility-only d-none">
            <div class="form-check mt-4">
              <input class="form-check-input" type="checkbox" id="pickerFitRequired">
              <label class="form-check-label" for="pickerFitRequired">Fit required (≥ <span id="pickerRequiredCap">0</span>)</label>
            </div>
          </div>
        </div>
        <div id="pickerHint" class="hint-bar mb-3 mt-0"></div>
        <div id="pickerList" class="picker-list"></div>
      </div>
    </div>
  </div>
</div>

<!-- Lecturers modal -->
<div class="modal fade picker-modal" id="lecturerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Select Module Leader &amp; Lecturers</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <div class="panel-title mb-2" style="font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#031f50;">Selected for this row</div>
          <div id="lectModalTags" class="border rounded p-3 bg-light">
            <span class="text-muted small">None yet — use Module Leader / Add Lecturer below</span>
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
  let pickerMode = null; // module | facility
  let facilityCache = [];
  let pickerModal;
  let lecturerModal;
  let draftLecturers = { leader: null, others: [] };

  function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, c => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[c]));
  }

  function lectName(l) {
    return (l && (l.names || l.name)) || (l?.id ? ('User ' + l.id) : '');
  }

  function emptyLecturers() {
    return { leader: null, others: [] };
  }

  function getRowLecturers($tr) {
    try {
      const raw = $tr.attr('data-lecturers');
      if (!raw) return emptyLecturers();
      const parsed = JSON.parse(raw);
      return {
        leader: parsed.leader || null,
        others: Array.isArray(parsed.others) ? parsed.others : []
      };
    } catch (e) {
      return emptyLecturers();
    }
  }

  function setRowLecturers($tr, selection) {
    const clean = {
      leader: selection?.leader || null,
      others: Array.isArray(selection?.others) ? selection.others : []
    };
    $tr.attr('data-lecturers', JSON.stringify(clean));
    renderLecturersCell($tr);
  }

  function renderLecturersCell($tr) {
    const sel = getRowLecturers($tr);
    const $wrap = $tr.find('.lecturers-tags').empty();
    if (!sel.leader && !sel.others.length) {
      $wrap.html('<span class="text-muted small">No lecturers</span>');
    } else {
      if (sel.leader) {
        $wrap.append(`
          <span class="lect-tag leader" data-role="leader" data-id="${sel.leader.id}">
            ${escapeHtml(lectName(sel.leader))}
            <span class="role">Leader</span>
            <button type="button" title="Remove">&times;</button>
          </span>
        `);
      }
      sel.others.forEach(l => {
        $wrap.append(`
          <span class="lect-tag other" data-role="other" data-id="${l.id}">
            ${escapeHtml(lectName(l))}
            <span class="role">Lecturer</span>
            <button type="button" title="Remove">&times;</button>
          </span>
        `);
      });
    }
    const count = (sel.leader ? 1 : 0) + sel.others.length;
    $tr.find('.btn-pick-lecturers').html(
      count
        ? `<i class="bi bi-people"></i> Edit lecturers (${count})`
        : `<i class="bi bi-person-plus"></i> Add lecturers...`
    );
  }

  function requiredCapacity() {
    return selectedGroups.reduce((sum, g) => sum + (parseInt(g.size, 10) || 0), 0);
  }

  function resolveGroupMeta(partial) {
    const prog = programs.find(p => String(p.id) === String(partial.program_id)) || selectedProgram;
    const intake = (prog?.intakes || []).find(i => String(i.year_of_study) === String(partial.year_of_study))
      || selectedIntake
      || null;
    return {
      id: partial.id,
      name: partial.name,
      size: partial.size || 0,
      program_id: partial.program_id || prog?.id || 0,
      program_name: partial.program_name || prog?.name || '',
      program_code: partial.program_code || prog?.code || '',
      school_name: partial.school_name || prog?.school_name || '',
      college_name: partial.college_name || prog?.college_name || '',
      year_of_study: partial.year_of_study || intake?.year_of_study || '',
      campus_name: partial.campus_name || intake?.campus_name || ''
    };
  }

  function renderSelectedGroups() {
    $('#selectedGroupCount').text(selectedGroups.length);
    $('#requiredCapacity').text(requiredCapacity());
    const progNames = [...new Set(selectedGroups.map(g => g.program_name).filter(Boolean))];
    $('#selectedProgramCount').text(progNames.length);
    $('#selectedProgramHint').text(progNames.length ? progNames.join(', ') : 'None yet');
    $('#btnClearSelectedGroups').prop('disabled', !selectedGroups.length);

    const $cards = $('#selectedGroupCards').empty();
    if (!selectedGroups.length) {
      $('#selectedGroupsEmpty').removeClass('d-none');
      return;
    }
    $('#selectedGroupsEmpty').addClass('d-none');

    selectedGroups.forEach(g => {
      const meta = resolveGroupMeta(g);
      Object.assign(g, meta);
      $cards.append(`
        <div class="selected-group-card" data-id="${g.id}">
          <div>
            <div class="sg-title">${escapeHtml(g.name)}</div>
            <div class="sg-meta">
              ${g.program_name ? `<span class="meta-pill"><i class="bi bi-mortarboard"></i> ${escapeHtml(g.program_name)}${g.program_code ? ' [' + escapeHtml(g.program_code) + ']' : ''}</span>` : ''}
              ${g.school_name ? `<span class="meta-pill"><i class="bi bi-building"></i> ${escapeHtml(g.school_name)}</span>` : ''}
              ${g.college_name ? `<span class="meta-pill"><i class="bi bi-bank"></i> ${escapeHtml(g.college_name)}</span>` : ''}
              ${g.campus_name ? `<span class="meta-pill"><i class="bi bi-geo-alt"></i> ${escapeHtml(g.campus_name)}</span>` : ''}
              ${g.year_of_study ? `<span class="meta-pill year"><i class="bi bi-calendar3"></i> Year ${escapeHtml(String(g.year_of_study))}</span>` : ''}
              <span class="meta-pill cap"><i class="bi bi-people"></i> ${g.size || 0} students</span>
            </div>
          </div>
          <button type="button" class="btn-remove-group" title="Remove group" data-id="${g.id}">&times;</button>
        </div>
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
      $btn.data('id', module.id).addClass('has-value');
      $btn.html(`
        <div><i class="bi bi-journal-text pick-icon"></i><strong>${escapeHtml(module.code || '')}</strong> ${escapeHtml(module.name || '')}</div>
        <div class="muted">Year ${module.year ?? '—'} · Sem ${module.semester ?? '—'} · click to change</div>
      `);
    } else {
      $btn.data('id', '').removeClass('has-value');
      $btn.html('<span class="text-muted"><i class="bi bi-search me-1"></i>Search module...</span>');
    }
  }

  function setFacilityBtn($tr, fac) {
    const $btn = $tr.find('.btn-pick-facility');
    if (fac && fac.id) {
      $btn.data('id', fac.id);
      $btn.data('capacity', fac.capacity || 0);
      $btn.addClass('has-value');
      $btn.html(`
        <div><i class="bi bi-building pick-icon"></i><strong>${escapeHtml(fac.name)}</strong>
          <span class="badge bg-primary ms-1">${fac.capacity || 0} seats</span></div>
        <div class="muted">${escapeHtml(fac.site_name || '')} / ${escapeHtml(fac.buildname || '')} · click to change</div>
      `);
    } else {
      $btn.data('id', '');
      $btn.data('capacity', '');
      $btn.removeClass('has-value');
      $btn.html('<span class="text-muted"><i class="bi bi-search me-1"></i>Search free facility...</span>');
    }
  }

  function addRow(preset = {}) {
    rowSeq += 1;
    const id = 'row_' + rowSeq;
    const tr = $(`
      <tr data-row-id="${id}" data-lecturers='{"leader":null,"others":[]}'>
        <td class="row-num"></td>
        <td class="col-module">
          <button type="button" class="btn btn-outline-secondary picker-btn btn-pick-module" data-id="">
            <span class="text-muted"><i class="bi bi-search me-1"></i>Search module...</span>
          </button>
        </td>
        <td><select class="form-select form-select-sm day-select">${dayOptionsHtml(preset.day || '')}</select></td>
        <td><select class="form-select form-select-sm start-select">${timeOptionsHtml(preset.start, '08:00')}</select></td>
        <td><select class="form-select form-select-sm end-select">${timeOptionsHtml(preset.end, '13:00')}</select></td>
        <td class="col-facility">
          <button type="button" class="btn btn-outline-secondary picker-btn btn-pick-facility" data-id="">
            <span class="text-muted"><i class="bi bi-search me-1"></i>Search free facility...</span>
          </button>
        </td>
        <td class="col-lecturers lecturers-cell">
          <div class="lecturers-tags"></div>
          <button type="button" class="btn btn-outline-primary btn-sm w-100 btn-pick-lecturers">
            <i class="bi bi-person-plus"></i> Add lecturers...
          </button>
        </td>
        <td><span class="row-status text-muted">Ready</span></td>
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
    if (preset.facility_id) {
      setFacilityBtn(tr, {
        id: preset.facility_id,
        name: preset.facility_name || ('Facility #' + preset.facility_id),
        capacity: preset.facility_capacity || '',
        site_name: preset.facility_site || '',
        buildname: preset.facility_building || ''
      });
    }

    if (preset.lecturers) {
      setRowLecturers(tr, preset.lecturers);
    } else if (preset.leader_id || (preset.other_lecturer_ids && preset.other_lecturer_ids.length)) {
      const leader = preset.leader_id
        ? (lecturersCache.find(x => String(x.id) === String(preset.leader_id)) || { id: preset.leader_id, names: 'User ' + preset.leader_id })
        : null;
      const others = (preset.other_lecturer_ids || []).map(oid =>
        lecturersCache.find(x => String(x.id) === String(oid)) || { id: oid, names: 'User ' + oid }
      ).filter(l => !leader || String(l.id) !== String(leader.id));
      setRowLecturers(tr, { leader, others });
    } else {
      renderLecturersCell(tr);
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
    const lect = getRowLecturers($tr);
    return {
      module_id: parseInt($tr.find('.btn-pick-module').data('id'), 10) || 0,
      day: $tr.find('.day-select').val() || '',
      start: $tr.find('.start-select').val() || '',
      end: $tr.find('.end-select').val() || '',
      facility_id: parseInt($tr.find('.btn-pick-facility').data('id'), 10) || 0,
      leader_id: lect.leader ? (parseInt(lect.leader.id, 10) || 0) : 0,
      other_lecturer_ids: (lect.others || []).map(o => parseInt(o.id, 10)).filter(Boolean),
      lecturers: lect
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
      if (!selectedGroups.length) {
        alert('Please select at least one group first. Modules are filtered by those groups’ programs.');
        return;
      }
      const list = modulesForSelectedGroups();
      const priorityCount = list.filter(m => m._priority).length;
      $('#pickerModalTitle').text('Search module (program modules)');
      const years = [...new Set(selectedGroups.map(g => g.year_of_study).filter(Boolean))].join(', ');
      const progIds = [...new Set(selectedGroups.map(g => g.program_id).filter(Boolean))];
      $('#pickerHint').text(
        `${list.length} program modules · ${priorityCount} match year ${years || '?'} / sem ${SEM || '?'} (shown first) · program(s) ${progIds.join(', ')}`
      );
      renderPickerList();
      pickerModal.show();
    } else if (mode === 'facility') {
      openFacilityPicker($tr);
    }
  }

  function openLecturerPicker($tr) {
    if (!selectedGroups.length) {
      alert('Please select at least one group first before choosing lecturers.');
      return;
    }
    activeRow = $tr;
    const current = getRowLecturers($tr);
    draftLecturers = {
      leader: current.leader ? { ...current.leader } : null,
      others: (current.others || []).map(o => ({ ...o }))
    };
    $('#lectModalSearch').val('');
    renderLecturerModalTags();
    renderLecturerModalList();
    lecturerModal.show();
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

    $('#lectModalHint').text(`${items.length} shown of ${lecturersCache.length} lecturers`);

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

  function applyDraftLecturersToRow() {
    if (!activeRow) return;
    setRowLecturers(activeRow, draftLecturers);
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
    $('#pickerHint').text('Loading free facilities (excludes approved/pending bookings and rooms set on other rows)...');
    $('#pickerList').html('<div class="text-center py-4 text-muted">Loading...</div>');
    $('#pickerRequiredCap').text(requiredCapacity());
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
        sessions: JSON.stringify([{ day, start, end }]),
        minCapacity: 0
      }
    }).done(function (json) {
      facilityCache = Array.isArray(json?.data) ? json.data : [];
      facilityCache.sort((a, b) => (b.capacity || 0) - (a.capacity || 0));
      const taken = getTakenFacilityIds(activeRow, day, start, end);
      const freeCount = facilityCache.filter(f => !taken.has(String(f.id))).length;
      $('#pickerHint').text(`${freeCount} free facilities shown (saved approved/pending excluded; ${taken.size} already set on other overlapping rows). Required students: ${requiredCapacity()}.`);
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
      const matched = modulesForSelectedGroups();
      const items = matched.filter(m => {
        if (!q) return true;
        return `${m.code || ''} ${m.name || ''} ${m.program_name || ''} ${m.year || ''} ${m.semester || ''}`.toLowerCase().includes(q);
      }).slice(0, 500);

      if (!selectedGroups.length) {
        $list.html('<div class="text-muted">Select groups first to see program modules.</div>');
        return;
      }
      if (!items.length) {
        $list.html('<div class="text-muted">No modules found for the selected program(s).</div>');
        return;
      }

      let shownPriorityHead = false;
      let shownOtherHead = false;
      items.forEach(m => {
        if (m._priority && !shownPriorityHead) {
          $list.append(`<div class="section-divider-label">Recommended — Year / Semester match</div>`);
          shownPriorityHead = true;
        }
        if (!m._priority && !shownOtherHead) {
          $list.append(`<div class="section-divider-label">Other modules in this program</div>`);
          shownOtherHead = true;
        }
        const badge = m._priority
          ? '<span class="badge bg-primary ms-1">Y/S match</span>'
          : '<span class="badge bg-secondary ms-1">Program</span>';
        $list.append(`
          <div class="picker-item" data-type="module" data-id="${m.id}">
            <div class="fw-semibold">${escapeHtml(m.code ? m.code + ' — ' : '')}${escapeHtml(m.name)} ${badge}</div>
            <div class="small text-muted">Year ${m.year ?? 'N/A'} · Sem ${m.semester ?? 'N/A'} · ${escapeHtml(m.program_name || '')}</div>
          </div>
        `);
      });
      return;
    }

    if (pickerMode === 'facility') {
      let minCap = parseInt($('#pickerMinCap').val(), 10);
      if ($('#pickerFitRequired').is(':checked')) minCap = req;
      if (!Number.isFinite(minCap) || minCap < 0) minCap = 0;

      const day = activeRow ? activeRow.find('.day-select').val() : '';
      const start = activeRow ? activeRow.find('.start-select').val() : '';
      const end = activeRow ? activeRow.find('.end-select').val() : '';
      const takenOnRows = (day && start && end) ? getTakenFacilityIds(activeRow, day, start, end) : new Set();

      const items = facilityCache.filter(f => {
        if (takenOnRows.has(String(f.id))) return false;
        if ((f.capacity || 0) < minCap) return false;
        if (!q) return true;
        return `${f.name || ''} ${f.site_name || ''} ${f.buildname || ''} ${f.type || ''} ${f.capacity || ''}`.toLowerCase().includes(q);
      });

      if (!items.length) {
        $list.html('<div class="text-muted">No free facilities match (saved bookings + other rows on this page are excluded).</div>');
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

  function programSearchHaystack(p) {
    return [
      p.name,
      p.code,
      p.school_name,
      p.college_name,
      p.department_name,
      ...(p.campus_names || [])
    ].filter(Boolean).join(' ').toLowerCase();
  }

  function programMatchesSearch(p, q) {
    if (!q) return true;
    const hay = programSearchHaystack(p);
    // All words must match somewhere (e.g. "huye nursing" → campus + program)
    const tokens = q.split(/\s+/).filter(Boolean);
    return tokens.every(t => hay.includes(t));
  }

  function programOptionLabel(p) {
    const bits = [];
    if (p.code) bits.push(`[${p.code}]`);
    if (p.school_name) bits.push(p.school_name);
    if (p.college_name) bits.push(p.college_name);
    if (p.campus_names && p.campus_names.length) bits.push(p.campus_names.join('/'));
    const hasIntakes = Array.isArray(p.intakes) && p.intakes.length > 0;
    if (!hasIntakes) bits.push('No intakes');
    const meta = bits.length ? ` — ${bits.join(' · ')}` : '';
    return `${p.name || 'Program'}${meta}`;
  }

  function renderProgramOptions() {
    const q = ($('#programSearch').val() || '').toLowerCase().trim();
    const current = $('#programSelect').val();
    const $sel = $('#programSelect').empty();
    let shown = 0;
    programs.forEach(p => {
      if (!programMatchesSearch(p, q)) return;
      shown += 1;
      const hasIntakes = Array.isArray(p.intakes) && p.intakes.length > 0;
      $sel.append(
        `<option value="${p.id}" ${hasIntakes ? '' : 'data-no-intake="1"'} title="${escapeHtml(programSearchHaystack(p))}">${escapeHtml(programOptionLabel(p))}</option>`
      );
    });
    if (!shown) {
      $sel.append('<option value="">No programs match search</option>');
    } else {
      $sel.prepend('<option value="">-- Select program --</option>');
    }
    if (current && $sel.find(`option[value="${current}"]`).length) {
      $sel.val(current);
    } else {
      $sel.val('');
    }
    $('#programMatchCount').text(shown);
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
              const intakes = (prog.intakes || []).map(intake => ({
                id: intake.id,
                year_of_study: intake.year_of_study || intake.year || 1,
                campus_name: intake.campus_name || intake.campus?.name || 'Unassigned',
                groups: intake.groups || []
              }));
              const campus_names = [...new Set(intakes.map(i => i.campus_name).filter(n => n && n !== 'Unassigned'))];
              programs.push({
                id: prog.id,
                name: prog.name,
                code: prog.code,
                school_id: school.id,
                school_name: school.name,
                college_id: college.id,
                college_name: college.name,
                department_id: prog.department_id || null,
                campus_names,
                intakes
              });
            });
          });
        });
        // Prefer unique by id (same program shouldn't appear twice)
        const byId = new Map();
        programs.forEach(p => {
          if (!byId.has(String(p.id))) byId.set(String(p.id), p);
        });
        programs = Array.from(byId.values()).sort((a, b) =>
          String(a.name || '').localeCompare(String(b.name || ''))
        );
        renderProgramOptions();
      })
      .fail(() => alert('Failed to load organization structure.'));
  }

  function loadModules() {
    return $.getJSON('api_get_modules.php', { page: 1, perPage: 5000, sort: 'name' })
      .done(function (res) {
        modulesCache = (res && res.success && Array.isArray(res.data)) ? res.data : [];
      });
  }

  function selectedProgramIds() {
    return [...new Set(selectedGroups.map(g => String(g.program_id)).filter(id => id && id !== '0' && id !== 'undefined'))];
  }

  function moduleInSelectedPrograms(m) {
    if (!selectedGroups.length) return false;
    const progIds = selectedProgramIds();
    return progIds.includes(String(m.program_id));
  }

  /** Year + semester match for selected groups (recommended / priority). */
  function moduleYearSemPriority(m) {
    if (!moduleInSelectedPrograms(m)) return false;
    return selectedGroups.some(g => {
      if (String(m.program_id) !== String(g.program_id)) return false;
      const sameYear = String(m.year) === String(g.year_of_study);
      const sameSem = !SEM || String(m.semester) === String(SEM);
      return sameYear && sameSem;
    });
  }

  /** All modules for selected program(s); year/semester matches first. */
  function modulesForSelectedGroups() {
    if (!selectedGroups.length) return [];
    const progIds = selectedProgramIds();
    const list = modulesCache
      .filter(m => progIds.includes(String(m.program_id)))
      .map(m => ({ ...m, _priority: moduleYearSemPriority(m) }));

    list.sort((a, b) => {
      if (a._priority !== b._priority) return a._priority ? -1 : 1;
      const ya = parseInt(a.year, 10) || 0;
      const yb = parseInt(b.year, 10) || 0;
      if (ya !== yb) return ya - yb;
      const sa = parseInt(a.semester, 10) || 0;
      const sb = parseInt(b.semester, 10) || 0;
      if (sa !== sb) return sa - sb;
      return String(a.code || a.name || '').localeCompare(String(b.code || b.name || ''));
    });
    return list;
  }

  function clearInvalidModulesOnRows() {
    $('#bulkTableBody tr').each(function () {
      const $tr = $(this);
      const mid = parseInt($tr.find('.btn-pick-module').data('id'), 10) || 0;
      if (!mid) return;
      const m = modulesCache.find(x => String(x.id) === String(mid));
      if (!m || !moduleInSelectedPrograms(m)) {
        setModuleBtn($tr, null);
        $tr.find('.row-status').text('Re-pick module (groups changed)').removeClass('text-success').addClass('text-muted');
      }
    });
  }

  function getTakenFacilityIds($exceptTr, day, start, end) {
    const taken = new Set();
    $('#bulkTableBody tr').each(function () {
      if ($exceptTr && this === $exceptTr[0]) return;
      const $tr = $(this);
      const fid = parseInt($tr.find('.btn-pick-facility').data('id'), 10) || 0;
      if (!fid) return;
      const d = $tr.find('.day-select').val();
      const s = $tr.find('.start-select').val();
      const e = $tr.find('.end-select').val();
      if (!d || !s || !e || d !== day) return;
      if (timesOverlap(start, end, s, e)) taken.add(String(fid));
    });
    return taken;
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
  let programSearchTimer = null;
  $('#programSearch').on('input', function () {
    clearTimeout(programSearchTimer);
    programSearchTimer = setTimeout(renderProgramOptions, 120);
  });

  $('#programSelect').on('change', function () {
    const id = $(this).val();
    selectedProgram = programs.find(p => String(p.id) === String(id)) || null;
    selectedIntake = null;
    const $intake = $('#intakeSelect').empty().prop('disabled', true);
    const $alert = $('#intakeEmptyAlert').addClass('d-none').empty();
    $('#intakeHint').text('Year of study & campus').removeClass('text-danger fw-semibold');
    $('#groupsCheckList').html('<span class="text-muted small">Select a program and intake first</span>');

    if (!selectedProgram) {
      $intake.append('<option value="">-- Select intake --</option>');
      return;
    }

    const intakes = selectedProgram.intakes || [];
    if (!intakes.length) {
      $intake.append('<option value="">No intakes available</option>');
      $('#intakeHint').text('This program has no intakes / student groups.').addClass('text-danger fw-semibold');
      $alert.removeClass('d-none').html(
        `<i class="bi bi-exclamation-triangle-fill me-1"></i>` +
        `<strong>${escapeHtml(selectedProgram.name || 'This program')}</strong> has no intakes. ` +
        `You cannot select groups for it. Choose another program.`
      );
      $('#groupsCheckList').html(`
        <div class="alert alert-warning mb-0 py-2 px-3 small">
          <i class="bi bi-people me-1"></i>
          No student groups — program has no intakes.
        </div>
      `);
      return;
    }

    $intake.append('<option value="">-- Select intake --</option>');
    intakes.forEach(i => {
      $intake.append(`<option value="${i.id}">Year ${i.year_of_study} — ${escapeHtml(i.campus_name)}</option>`);
    });
    $intake.prop('disabled', false);
    $('#intakeHint').text(`${intakes.length} intake(s) — pick year & campus`);
    $('#groupsCheckList').html('<span class="text-muted small">Select an intake to see groups</span>');
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
        <label class="group-check-item" for="g${g.id}">
          <input class="form-check-input group-check mt-1" type="checkbox" value="${g.id}" id="g${g.id}" ${checked}
                 data-name="${escapeHtml(g.name)}" data-size="${g.size || 0}"
                 data-program-id="${selectedProgram?.id || ''}"
                 data-year="${selectedIntake?.year_of_study || ''}"
                 data-campus="${escapeHtml(selectedIntake?.campus_name || '')}">
          <span>
            <div class="g-name">${escapeHtml(g.name)}</div>
            <div class="g-meta">${g.size || 0} students · Year ${selectedIntake?.year_of_study || '?'} · ${escapeHtml(selectedIntake?.campus_name || '')}</div>
          </span>
        </label>
      `);
    });
  });

  $('#groupsCheckList').on('change', '.group-check', function () {
    const id = $(this).val();
    const name = $(this).data('name');
    const size = parseInt($(this).data('size'), 10) || 0;
    const program_id = parseInt($(this).data('program-id'), 10) || (selectedProgram?.id ? parseInt(selectedProgram.id, 10) : 0);
    const year_of_study = parseInt($(this).data('year'), 10) || (selectedIntake?.year_of_study ? parseInt(selectedIntake.year_of_study, 10) : 0);
    const campus_name = $(this).data('campus') || selectedIntake?.campus_name || '';
    if (this.checked) {
      if (!selectedGroups.some(g => String(g.id) === String(id))) {
        selectedGroups.push(resolveGroupMeta({
          id: parseInt(id, 10),
          name,
          size,
          program_id,
          year_of_study,
          campus_name,
          program_name: selectedProgram?.name || '',
          program_code: selectedProgram?.code || '',
          school_name: selectedProgram?.school_name || '',
          college_name: selectedProgram?.college_name || ''
        }));
      }
    } else {
      selectedGroups = selectedGroups.filter(g => String(g.id) !== String(id));
    }
    renderSelectedGroups();
    clearInvalidModulesOnRows();
  });

  $('#selectedGroupCards').on('click', '.btn-remove-group', function () {
    const id = String($(this).data('id'));
    selectedGroups = selectedGroups.filter(g => String(g.id) !== id);
    renderSelectedGroups();
    syncGroupChecks();
    clearInvalidModulesOnRows();
  });

  $('#btnClearSelectedGroups').on('click', function () {
    if (!selectedGroups.length) return;
    if (!confirm('Clear all selected groups?')) return;
    selectedGroups = [];
    renderSelectedGroups();
    syncGroupChecks();
    clearInvalidModulesOnRows();
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
    data.lecturers = getRowLecturers($tr);
    addRow(data);
  });

  $('#bulkTableBody').on('click', '.btn-pick-module', function () {
    openPicker('module', $(this).closest('tr'));
  });
  $('#bulkTableBody').on('click', '.btn-pick-facility', function () {
    openPicker('facility', $(this).closest('tr'));
  });
  $('#bulkTableBody').on('click', '.btn-pick-lecturers', function () {
    openLecturerPicker($(this).closest('tr'));
  });

  // Remove lecturer tag from row cell
  $('#bulkTableBody').on('click', '.lecturers-tags .lect-tag button', function (e) {
    e.stopPropagation();
    const $tr = $(this).closest('tr');
    const $tag = $(this).closest('.lect-tag');
    const role = $tag.data('role');
    const id = String($tag.data('id'));
    const sel = getRowLecturers($tr);
    if (role === 'leader') sel.leader = null;
    else sel.others = sel.others.filter(o => String(o.id) !== id);
    setRowLecturers($tr, sel);
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
    } else if (type === 'facility') {
      const f = facilityCache.find(x => String(x.id) === String(id));
      setFacilityBtn(activeRow, f);
      activeRow.find('.row-status').text('Facility set').addClass('text-muted');
    }
    pickerModal.hide();
  });

  // Lecturer modal events
  let lectSearchTimer = null;
  $('#lectModalSearch').on('input', function () {
    clearTimeout(lectSearchTimer);
    lectSearchTimer = setTimeout(renderLecturerModalList, 150);
  });

  $('#lectModalList').on('click', '.btn-set-leader', function () {
    const id = $(this).closest('.lect-row').data('id');
    const l = lecturersCache.find(x => String(x.id) === String(id));
    if (!l) return;
    draftLecturers.leader = { id: l.id, names: lectName(l), email: l.email || '', ur_email: l.ur_email || '' };
    draftLecturers.others = draftLecturers.others.filter(o => String(o.id) !== String(l.id));
    renderLecturerModalTags();
    renderLecturerModalList();
    applyDraftLecturersToRow();
  });

  $('#lectModalList').on('click', '.btn-add-lecturer', function () {
    const id = $(this).closest('.lect-row').data('id');
    const l = lecturersCache.find(x => String(x.id) === String(id));
    if (!l) return;
    if (draftLecturers.leader && String(draftLecturers.leader.id) === String(l.id)) return;
    if (draftLecturers.others.some(o => String(o.id) === String(l.id))) return;
    draftLecturers.others.push({ id: l.id, names: lectName(l), email: l.email || '', ur_email: l.ur_email || '' });
    renderLecturerModalTags();
    renderLecturerModalList();
    applyDraftLecturersToRow();
  });

  $('#lectModalTags').on('click', '.lect-tag button', function () {
    const $tag = $(this).closest('.lect-tag');
    const role = $tag.data('role');
    const id = String($tag.data('id'));
    if (role === 'leader') draftLecturers.leader = null;
    else draftLecturers.others = draftLecturers.others.filter(o => String(o.id) !== id);
    renderLecturerModalTags();
    renderLecturerModalList();
    applyDraftLecturersToRow();
  });

  $('#btnClearRowLecturers').on('click', function () {
    draftLecturers = emptyLecturers();
    renderLecturerModalTags();
    renderLecturerModalList();
    applyDraftLecturersToRow();
  });

  $('#lecturerModal').on('hidden.bs.modal', function () {
    applyDraftLecturersToRow();
  });

  $('#btnClearBulk').on('click', function () {
    if (!confirm('Clear selected groups and all plan rows?')) return;
    selectedGroups = [];
    renderSelectedGroups();
    syncGroupChecks();
    $('#bulkTableBody').empty();
    $('#bulkResultAlert').addClass('d-none');
    $('#bulkConflictDetails').addClass('d-none');
  });

  function fmtTime(t) {
    return String(t || '').slice(0, 5);
  }

  function timesOverlap(aStart, aEnd, bStart, bEnd) {
    return aStart < bEnd && aEnd > bStart;
  }

  function validateBulkRows() {
    const issues = [];
    if (!AY || !SEM) {
      issues.push({ global: true, message: 'Academic year / semester not configured.' });
      return { ok: false, issues, rows: [] };
    }
    if (!selectedGroups.length) {
      issues.push({ global: true, message: 'Please select at least one group.' });
      return { ok: false, issues, rows: [] };
    }

    const $trs = $('#bulkTableBody tr');
    if (!$trs.length) {
      issues.push({ global: true, message: 'Please add at least one plan row (session).' });
      return { ok: false, issues, rows: [] };
    }

    const rows = [];
    const reqCap = requiredCapacity();

    $trs.each(function (idx) {
      const $tr = $(this);
      $tr.removeClass('row-ok row-fail');
      $tr.find('.row-status').removeClass('text-danger text-success').addClass('text-muted');

      const row = collectRow($tr);
      const missing = [];
      if (!row.module_id) missing.push('module');
      if (!row.day) missing.push('day');
      if (!row.start) missing.push('start');
      if (!row.end) missing.push('end');
      if (!row.facility_id) missing.push('facility');

      if (missing.length) {
        issues.push({ row: idx, message: 'Missing: ' + missing.join(', ') });
        $tr.addClass('row-fail');
        $tr.find('.row-status').text('Missing: ' + missing.join(', ')).removeClass('text-muted').addClass('text-danger');
      } else if (row.start >= row.end) {
        issues.push({ row: idx, message: `Invalid time range: ${row.start} - ${row.end}` });
        $tr.addClass('row-fail');
        $tr.find('.row-status').text('Invalid time range').removeClass('text-muted').addClass('text-danger');
      } else {
        const m = modulesCache.find(x => String(x.id) === String(row.module_id));
        if (!m || !moduleInSelectedPrograms(m)) {
          issues.push({ row: idx, message: 'Module is not in the selected groups’ program(s).' });
          $tr.addClass('row-fail');
          $tr.find('.row-status').text('Module not in program').removeClass('text-muted').addClass('text-danger');
        } else {
          const facCap = parseInt($tr.find('.btn-pick-facility').data('capacity'), 10) || 0;
          if (reqCap > 0 && facCap > 0 && facCap < reqCap) {
            issues.push({
              row: idx,
              soft: true,
              message: `Facility capacity (${facCap}) is below required students (${reqCap}).`
            });
            $tr.find('.row-status').text(`Capacity low (${facCap}<${reqCap})`).removeClass('text-muted').addClass('text-danger');
          } else {
            $tr.find('.row-status').text('Ready');
          }
        }
      }
      rows.push(row);
    });

    // Within-batch overlap (same shared groups → overlapping times conflict)
    for (let i = 0; i < rows.length; i++) {
      for (let j = i + 1; j < rows.length; j++) {
        const a = rows[i], b = rows[j];
        if (!a.day || !b.day || a.day !== b.day) continue;
        if (!a.start || !a.end || !b.start || !b.end) continue;
        if (!timesOverlap(a.start, a.end, b.start, b.end)) continue;

        issues.push({
          row: j,
          message: `Overlaps row ${i + 1} on ${a.day} (${a.start}-${a.end} / ${b.start}-${b.end}) — same groups.`
        });
        const $trJ = $('#bulkTableBody tr').eq(j);
        $trJ.addClass('row-fail');
        $trJ.find('.row-status').text(`Overlaps row ${i + 1}`).removeClass('text-muted').addClass('text-danger');

        if (a.facility_id && a.facility_id === b.facility_id) {
          issues.push({
            row: j,
            message: `Same facility as row ${i + 1} in overlapping time.`
          });
        }
      }
    }

    const hardIssues = issues.filter(x => !x.soft);
    return { ok: hardIssues.length === 0, issues, rows, softWarnings: issues.filter(x => x.soft) };
  }

  function renderConflictsHtml(conf, rowNum) {
    const groupNameById = Object.fromEntries(selectedGroups.map(g => [String(g.id), g.name]));
    const f = conf.facility || [];
    const g = conf.groups || {};
    const l = conf.lecturers || {};
    const li = (txt) => `<li class="mono">${escapeHtml(txt)}</li>`;
    let html = `<div class="mb-3"><strong>Row ${rowNum}</strong>`;

    if (f.length) {
      html += `<h6 class="mt-2 mb-1"><i class="bi bi-building me-1"></i> Facility conflicts</h6><ul class="mb-1">`;
      f.forEach(r => {
        const src = r.source === 'batch' ? ' (another row in this save)' : ` (timetable #${r.timetable_id})`;
        html += li(`Day ${r.day}: ${fmtTime(r.start_time)} - ${fmtTime(r.end_time)}${src}`);
      });
      html += `</ul>`;
    }

    const gKeys = Object.keys(g);
    if (gKeys.length) {
      html += `<h6 class="mt-2 mb-1"><i class="bi bi-people me-1"></i> Group conflicts</h6>`;
      gKeys.forEach(k => {
        const arr = g[k] || [];
        const name = groupNameById[String(k)] || `Group ${k}`;
        html += `<div class="small fw-bold">${escapeHtml(name)}</div><ul class="mb-1">`;
        arr.forEach(r => {
          const src = r.source === 'batch' ? ' (another row in this save)' : ` (timetable #${r.timetable_id})`;
          html += li(`Day ${r.day}: ${fmtTime(r.start_time)} - ${fmtTime(r.end_time)}${src}`);
        });
        html += `</ul>`;
      });
    }

    const lKeys = Object.keys(l);
    // Lecturer overlaps are informational only — do not treat as blockers
    if (lKeys.length && !f.length && !gKeys.length) {
      html += `<div class="text-muted small mt-1">Lecturer time overlaps exist but are allowed.</div>`;
    } else if (lKeys.length) {
      html += `<h6 class="mt-2 mb-1 text-muted"><i class="bi bi-person-badge me-1"></i> Lecturer overlaps (allowed)</h6>`;
      lKeys.forEach(k => {
        const arr = l[k] || [];
        html += `<div class="small fw-bold text-muted">Lecturer ${escapeHtml(k)}</div><ul class="mb-1 text-muted">`;
        arr.forEach(r => {
          html += li(`Day ${r.day}: ${fmtTime(r.start_time)} - ${fmtTime(r.end_time)} (timetable #${r.timetable_id})`);
        });
        html += `</ul>`;
      });
    }

    html += `</div>`;
    return html;
  }

  function buildPreviewHtml(rows) {
    const reqCap = requiredCapacity();
    let html = `
      <div class="alert alert-info mb-3">
        <div><strong>Academic year:</strong> ${escapeHtml(String(AY))} · <strong>Semester:</strong> ${escapeHtml(String(SEM))}</div>
        <div><strong>Groups (${selectedGroups.length}):</strong> ${selectedGroups.map(g => escapeHtml(g.name)).join(', ')}</div>
        <div><strong>Required capacity:</strong> ${reqCap}</div>
        <div><strong>Plans to save:</strong> ${rows.length}</div>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-bordered">
          <thead class="table-light">
            <tr><th>#</th><th>Module</th><th>Day</th><th>Time</th><th>Facility</th><th>Lecturers</th></tr>
          </thead>
          <tbody>
    `;
    rows.forEach((row, i) => {
      const $tr = $('#bulkTableBody tr').eq(i);
      const modTxt = $tr.find('.btn-pick-module').text().replace(/\s*Click to change\s*/i, '').trim();
      const facTxt = $tr.find('.btn-pick-facility strong').first().text() || ('#' + row.facility_id);
      const facCap = $tr.find('.btn-pick-facility').data('capacity') || '';
      const lect = row.lecturers || { leader: null, others: [] };
      const lectParts = [];
      if (lect.leader) lectParts.push(escapeHtml(lectName(lect.leader)) + ' <span class="badge bg-primary">Leader</span>');
      (lect.others || []).forEach(o => lectParts.push(escapeHtml(lectName(o)) + ' <span class="badge bg-success">Lecturer</span>'));
      html += `
        <tr>
          <td>${i + 1}</td>
          <td>${escapeHtml(modTxt)}</td>
          <td>${escapeHtml(row.day)}</td>
          <td>${escapeHtml(row.start)} – ${escapeHtml(row.end)}</td>
          <td>${escapeHtml(facTxt)}${facCap ? ` (${facCap})` : ''}</td>
          <td>${lectParts.length ? lectParts.join('<br>') : '<span class="text-muted">None</span>'}</td>
        </tr>
      `;
    });
    html += `</tbody></table></div>
      <div class="alert alert-secondary mb-0"><i class="bi bi-info-circle"></i> Review details above before saving.</div>`;
    return html;
  }

  async function doBulkSave(rows) {
    const $alert = $('#bulkResultAlert').removeClass('d-none alert-success alert-danger alert-warning').addClass('alert-info').text('Saving...');
    $('#bulkConflictDetails').addClass('d-none');
    $('#bulkConflictBody').empty();

    const payloadRows = rows.map(r => ({
      module_id: r.module_id,
      day: r.day,
      start: r.start,
      end: r.end,
      facility_id: r.facility_id,
      leader_id: r.leader_id || 0,
      other_lecturer_ids: r.other_lecturer_ids || []
    }));

    try {
      const res = await fetch('save_timetable_bulk.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          academic_year_id: AY,
          semester: SEM,
          selectedGroupIds: selectedGroups.map(g => g.id),
          groupIds: selectedGroups.map(g => g.id),
          rows: payloadRows
        })
      });
      const data = await res.json();
      let conflictHtml = '';

      (data.results || []).forEach(r => {
        const $tr = $('#bulkTableBody tr').eq(r.row_index);
        if (!$tr.length) return;
        if (r.status === 'success') {
          $tr.addClass('row-ok').removeClass('row-fail');
          $tr.find('.row-status').removeClass('text-danger text-muted').addClass('text-success')
            .text(r.approval_status || 'Saved');
        } else if (r.status === 'conflict') {
          $tr.addClass('row-fail').removeClass('row-ok');
          $tr.find('.row-status').removeClass('text-success text-muted').addClass('text-danger').text('Conflict');
          if (r.conflicts) conflictHtml += renderConflictsHtml(r.conflicts, (r.row_index || 0) + 1);
        } else {
          $tr.addClass('row-fail').removeClass('row-ok');
          $tr.find('.row-status').removeClass('text-success text-muted').addClass('text-danger')
            .text(r.message || 'Error');
        }
      });

      if (conflictHtml) {
        $('#bulkConflictBody').html(conflictHtml);
        $('#bulkConflictDetails').removeClass('d-none');
      }

      const cls = data.status === 'success' ? 'alert-success' : (data.status === 'partial' ? 'alert-warning' : 'alert-danger');
      $alert.removeClass('alert-info alert-success alert-danger alert-warning').addClass(cls).text(data.message || 'Done');
    } catch (e) {
      console.error(e);
      $alert.removeClass('alert-info').addClass('alert-danger').text('Network error while saving. Please try again.');
    }
  }

  let pendingSaveRows = null;
  let bulkPreviewModal;

  $('#btnSaveAll').on('click', function () {
    const result = validateBulkRows();
    const $alert = $('#bulkResultAlert').removeClass('d-none alert-success alert-danger alert-warning alert-info');
    $('#bulkConflictDetails').addClass('d-none');

    if (!result.ok) {
      const msg = result.issues.filter(i => !i.soft).map(i =>
        i.global ? i.message : `Row ${(i.row || 0) + 1}: ${i.message}`
      ).join(' ');
      $alert.addClass('alert-danger').text(msg || 'Please fix validation errors.');
      return;
    }

    if (result.softWarnings && result.softWarnings.length) {
      const warn = result.softWarnings.map(w => `Row ${w.row + 1}: ${w.message}`).join('\n');
      if (!confirm(warn + '\n\nContinue to preview anyway?')) {
        $alert.addClass('alert-warning').text('Save cancelled — fix facility capacity or continue after confirm.');
        return;
      }
    }

    pendingSaveRows = result.rows;
    $('#bulkPreviewContent').html(buildPreviewHtml(result.rows));
    bulkPreviewModal.show();
    $alert.addClass('d-none');
  });

  $('#btnConfirmBulkSave').on('click', async function () {
    if (!pendingSaveRows) return;
    bulkPreviewModal.hide();
    const rows = pendingSaveRows;
    pendingSaveRows = null;
    await doBulkSave(rows);
  });


  // Init
  pickerModal = new bootstrap.Modal(document.getElementById('pickerModal'));
  lecturerModal = new bootstrap.Modal(document.getElementById('lecturerModal'));
  bulkPreviewModal = new bootstrap.Modal(document.getElementById('bulkPreviewModal'));
  renderSelectedGroups();
  loadOrganization();
  Promise.all([loadModules(), loadLecturers()]).then(() => addRow());
})();
</script>
</body>
</html>
