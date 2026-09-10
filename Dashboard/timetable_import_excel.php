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
    .meta {
      display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px;
    }
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
      max-height: min(55vh, 560px);
    }
    .hint {
      font-size: .8rem; color: #475569; background: #f8fafc;
      border: 1px solid var(--imp-line); border-radius: 8px;
      padding: 10px 12px; margin-top: 12px;
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
        <div class="small" style="opacity:.85">Teaching timetable sheet (Day, Time, Module code, Lecturer, Classroom…)</div>
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
          <div class="small" style="opacity:.85">Review matches, then save this section into the timetable</div>
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
        <table class="table table-sm table-bordered mb-0">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Day / Time</th>
              <th>Excel module</th>
              <th>Matched module</th>
              <th>Facility</th>
              <th>Lecturers</th>
              <th>Groups</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody id="matchBody"></tbody>
        </table>
      </div>
      <div class="hint mb-0">
        Only rows with a matched <strong>module</strong>, <strong>day</strong> and <strong>time</strong> are saved.
        Facility &amp; group conflicts are blocked; lecturer overlaps are allowed.
        Save one Excel section at a time, then open the next.
      </div>
      <div id="saveAlert" class="alert d-none mt-3 mb-0" role="alert"></div>
      <div id="conflictBox" class="card border-danger d-none mt-3">
        <div class="card-header text-danger fw-semibold">Conflict details</div>
        <div class="card-body small" id="conflictBody"></div>
      </div>
    </div>
  </div>
</main>

<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
<script>
(function () {
  const AY = <?php echo json_encode($accademic_year_id); ?>;
  const SEM = <?php echo json_encode($semester); ?>;
  const ALL_PROGRAMS = <?php echo json_encode($allPrograms); ?>;

  let fileBuffer = null;
  let parsedSections = []; // raw parse before DB match
  let importSections = [];
  let activeIdx = null;

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

  function renderSections() {
    const $list = $('#sectionsList').empty();
    if (!importSections.length) {
      $('#sectionsEmpty').removeClass('d-none');
      $('#detailSection').addClass('d-none');
      return;
    }
    $('#sectionsEmpty').addClass('d-none');
    importSections.forEach((sec, idx) => {
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
              <span class="pill ok">${st.ok || 0} ok</span>
              <span class="pill warn">${st.warnings || 0} warn</span>
              <span class="pill err">${st.errors || 0} err</span>
            </div>
          </div>
          <button type="button" class="btn btn-sm btn-outline-primary" data-sec="${idx}">Open</button>
        </div>
      `);
    });
  }

  function showSection(idx) {
    activeIdx = idx;
    const sec = importSections[idx];
    if (!sec) return;
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

    // Create panel when no groups
    const needs = !!sec.needs_groups || !(sec.groups || []).length;
    const $panel = $('#createGroupsPanel');
    if (needs) {
      $panel.removeClass('d-none');
      const $prog = $('#createProgramId').empty();
      const cands = sec.program_candidates || [];
      if (cands.length) {
        cands.forEach(c => {
          $prog.append(`<option value="${c.id}">${escapeHtml(c.name)} (${c.score})</option>`);
        });
      } else {
        ALL_PROGRAMS.forEach(p => {
          $prog.append(`<option value="${p.id}">${escapeHtml(p.name)}${p.code ? ' [' + escapeHtml(p.code) + ']' : ''}</option>`);
        });
      }
      if (sec.program?.id) $prog.val(String(sec.program.id));
      // Prefer candidate with transport/logistics if present
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
      const stCls = row.status === 'ok' ? 'text-success' : (row.status === 'warning' ? 'text-warning' : 'text-danger');
      const mod = row.module ? `${escapeHtml(row.module.code || '')} — ${escapeHtml(row.module.name || '')}` : '<span class="text-danger">—</span>';
      const fac = row.facility ? `${escapeHtml(row.facility.name)} (${row.facility.capacity || '?'})` : '<span class="text-muted">—</span>';
      const lect = [];
      if (row.lecturers?.leader) lect.push(escapeHtml(lectName(row.lecturers.leader)) + ' <span class="badge bg-primary">ML</span>');
      (row.lecturers?.others || []).forEach(o => lect.push(escapeHtml(lectName(o))));
      const grps = (row.groups || []).map(g => escapeHtml(g.name)).join(', ') || '—';
      const notes = [...(row.errors || []), ...(row.warnings || [])].map(escapeHtml).join('; ');
      $body.append(`
        <tr>
          <td>${i + 1}</td>
          <td>${escapeHtml(row.day)}<br><span class="small text-muted">${escapeHtml(row.start)}–${escapeHtml(row.end)}</span></td>
          <td class="small">${escapeHtml(row.excel?.module_code || '')}<br>${escapeHtml(row.excel?.module_name || '')}</td>
          <td class="small">${mod}</td>
          <td class="small">${fac}</td>
          <td class="small">${lect.join('<br>') || '—'}</td>
          <td class="small">${grps}</td>
          <td class="${stCls} small">${escapeHtml(row.status)}${notes ? '<div class="text-muted">' + notes + '</div>' : ''}</td>
        </tr>
      `);
    });
  }

  async function rematchSections(sectionsPayload) {
    const res = await fetch('match_bulk_import.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ sections: sectionsPayload })
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Match failed');
    importSections = data.sections || [];
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

    let groups = sec.groups || [];
    if (!groups.length) {
      const map = new Map();
      (sec.rows || []).forEach(r => (r.groups || []).forEach(g => map.set(String(g.id), g)));
      groups = Array.from(map.values());
    }
    if (!groups.length) {
      alert('No groups matched for this section. Fix group names in Excel / system, then rematch.');
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

    if (!rows.length) {
      alert('No complete rows to save (need matched module + facility + day/time).');
      return;
    }

    if (!confirm(`Save ${rows.length} plan(s) for ${groups.length} group(s) in this section?`)) return;

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

  $('#excelFile').on('change', function () {
    const f = this.files && this.files[0];
    fileBuffer = null;
    importSections = [];
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
      const totalErr = importSections.reduce((s, x) => s + (x.stats?.errors || 0), 0);
      setStatus(`Matched ${importSections.length} section(s). Open a section — create groups if needed, then Save.`, totalErr ? 'text-warning' : 'text-success');
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

      $('#createStatus').text(data.message || 'Created. Rematching…');

      // Rematch this section with forced program
      const payload = parsedSections.map((s, i) => {
        const copy = { ...s };
        if (i === activeIdx) copy.forced_program_id = program_id;
        return copy;
      });
      await rematchSections(payload);
      $('#createStatus').text(data.message + ' Rematched.');
      setStatus('Groups created and section rematched. Review then Save.', 'text-success');
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

  $('#btnSaveSection').on('click', saveActiveSection);

  $('#btnClear').on('click', function () {
    $('#excelFile').val('');
    fileBuffer = null;
    parsedSections = [];
    importSections = [];
    activeIdx = null;
    renderSections();
    $('#btnParse').prop('disabled', true);
    $('#btnClear').prop('disabled', true);
    setStatus('');
    $('#saveAlert').addClass('d-none');
    $('#conflictBox').addClass('d-none');
    $('#createGroupsPanel').addClass('d-none');
  });
})();
</script>
</body>
</html>
