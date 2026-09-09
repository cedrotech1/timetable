<?php 
session_start();
include('connection.php');

$user_id = $_SESSION['id'] ?? 0;

// get academic year
$academic_year_id = $_SESSION['academic_year_id'] ?? null;
$semester = $_SESSION['semester'] ?? null;
$academic_year_label = $_SESSION['academic_year_label'] ?? null;

// get user
$stmt = $connection->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc() ?: [];

$role      = $user['role'] ?? '';
$campusId  = $user['campus'] ?? null;
$collegeId = $user['college'] ?? null;
$schoolId  = $user['school'] ?? null;

// fetch school info
$school = null;
if ($schoolId) {
  $stmt = $connection->prepare("SELECT s.*, c.name as college_name FROM school s 
                              LEFT JOIN college c ON s.college_id = c.id 
                              WHERE s.id = ?");
  $stmt->bind_param("i", $schoolId);
  $stmt->execute();
  $school = $stmt->get_result()->fetch_assoc();
  $schoolName = $school['name'] ?? 'Unknown';
  $collegeName = $school['college_name'] ?? 'Unknown';
}

// fetch campus info
$campus = null;
if ($campusId) {
  $stmt = $connection->prepare("SELECT * FROM campus WHERE id = ?");
  $stmt->bind_param("i", $campusId);
  $stmt->execute();
  $campus = $stmt->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>School Timetable</title>
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

<style>
main { background: #f4f6f8;  font-size: 12px; }
table { background: white; border-collapse: collapse; width: 100%; font-size: 12px; }
th, td { padding: 2px 4px; border: 1px solid #ddd; vertical-align: middle; white-space: nowrap; text-align: center; }
h2 { font-size: 16px; margin-bottom: 10px; padding: 5px; }
.table-dark { background-color: #031f50 !important; color: white; font-size: 12px; }
.badge { font-size: 10px; padding: 2px 4px; }
.btn-sm { padding: 2px 5px; font-size: 11px; }
.card { margin-bottom: 5px; padding: 5px; font-size: 12px; }
.card-body { padding: 5px; }
select.form-select { font-size: 12px; padding: 2px 4px; }
.btn { font-size: 12px; padding: 2px 5px; }
#currentView { font-size: 13px; }
.filter-container {
  background: #f8f9fa;
  border: 0px solid #dee2e6;
  padding: 12px 15px;
  border-radius: 6px;
}

.filter-container label {
  font-size: 0.85rem;
  margin-bottom: 4px;
}

.filter-container select {
  font-size: 0.85rem;
}
.search-container {
  background: #f8f9fa;
  border: 0px solid #dee2e6;
  padding: 12px 15px;
  border-radius: 6px;
  margin-bottom: 15px;
}
.search-container label {
  font-size: 0.85rem;
  margin-bottom: 4px;
}
.search-container input {
  border: 1px solid #ced4da !important;
}
.table-card{
    background: #fff !important;
    padding: 12px 15px;
    border-radius: 6px;
}

.timetable-title{
    font-size: 20px;
    text-align: justify;
    margin-bottom: 10px;
    padding: 5px;
    font-weight: bold;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    color: #031f50;
    border-bottom: 1px solid #ddd;
    width: 100%;
    
}
.btn-primary{
    background-color: #031f50 !important;
    color: white !important;
    font-size: 12px !important;
    padding: 2px 5px !important;
}
.search-btn {
    margin-left: 5px;
}
</style>
</head>
<body>
    <?php include('./includes/header.php'); ?>
    <?php include('./includes/menu.php'); ?>

    <main id="main" class="main">

<div class="table-card">

<!-- Search Section -->
<div class="search-container mb-3">
    <div class="row g-3 align-items-end">
        <div class="col-md-4">
            <label for="lecturerSearch" class="form-label fw-semibold">Search Lecturer (Name/Email/Phone)</label>
            <input type="text" id="lecturerSearch" class="form-control form-control-sm" placeholder="Enter lecturer details">
        </div>
        <div class="col-md-4">
            <label for="facilitySearch" class="form-label fw-semibold">Search Facility</label>
            <input type="text" id="facilitySearch" class="form-control form-control-sm" placeholder="Enter facility name">
        </div>
        <div class="col-md-4">
            <label for="moduleSearch" class="form-label fw-semibold">Search Module (Name/Code)</label>
            <input type="text" id="moduleSearch" class="form-control form-control-sm" placeholder="Enter module name or code">
        </div>
    </div>
    <div class="row mt-2">
        <div class="col-md-12">
            <button id="searchBtn" class="btn btn-primary search-btn">Search</button>
            <button id="clearSearch" class="btn btn-secondary search-btn">Clear Search</button>
        </div>
    </div>
</div>

<!-- Nested Filters -->
<div class="filter-container mb-4">
  <div class="row g-3 align-items-end">
    <div class="col-md-2 col-sm-4">
      <label for="idFilter" class="form-label fw-semibold">Timetable ID</label>
      <input type="number" id="idFilter" class="form-control form-control-sm" placeholder="Enter ID">
    </div>
    <div class="col-md-2 col-sm-4">
      <label for="programFilter" class="form-label fw-semibold">Program</label>
      <select id="programFilter" class="form-select form-select-sm">
        <option value="">All</option>
      </select>
    </div>
    <div class="col-md-3 col-sm-4">
      <label for="yearOfStudyFilter" class="form-label fw-semibold">Year of Study</label>
      <select id="yearOfStudyFilter" class="form-select form-select-sm">
        <option value="">All</option>
        <option value="1">Year 1</option>
        <option value="2">Year 2</option>
        <option value="3">Year 3</option>
        <option value="4">Year 4</option>
        <option value="5">Year 5</option>
      </select>
    </div>
    <div class="col-md-3 col-sm-4">
      <label for="groupFilter" class="form-label fw-semibold">Group</label>
      <select id="groupFilter" class="form-select form-select-sm">
        <option value="">All</option>
      </select>
    </div>
  </div>
</div>

<button id="resetFilters" class="btn btn-primary ">Reset Filters</button>

<button id="exportBtn" class="btn btn-success">Export to Excel</button>

<br>
<!-- Dynamic view info -->
<h2 class="fw-semibold timetable-title mt-2">Timetable for <?php echo htmlspecialchars($schoolName) ?></h2>
<div id="currentView" style="font-size:13px; line-height:1.4; margin-bottom:10px; padding:0;">
  Loading timetable view...
</div>

<div class="table-responsive">
<table class="table table-bordered table-striped" id="timetableTable">
<thead class="">
<tr>
  <th rowspan="2" style="background-color:rgb(99, 124, 167);color: white;">ID</th>
  <th rowspan="2" style="background-color:rgb(99, 124, 167);color: white;">Day</th>
  <th rowspan="2" style="background-color:rgb(99, 124, 167);color: white;">Time</th>
  <th rowspan="2" style="background-color:rgb(99, 124, 167);color: white;">Course</th>
  <th rowspan="2" style="background-color:rgb(99, 124, 167);color: white;">Code</th>
  <th rowspan="2" style="background-color:rgb(99, 124, 167);color: white;">Credits</th>
  <th rowspan="2" style="background-color:rgb(99, 124, 167);color: white;">Facility</th>
  <th colspan="6" style="background-color:rgb(99, 124, 167);color: white;">Group Details</th>
  <th rowspan="2" style="background-color:rgb(99, 124, 167);color: white;">Lecturers</th>
</tr>
<tr>
  <th style="background-color:rgb(99, 124, 167);color: white;">Group</th>
  <th style="background-color:rgb(99, 124, 167);color: white;">Year of Study</th>
  <th style="background-color:rgb(99, 124, 167);color: white;">Program</th>
  <th style="background-color:rgb(99, 124, 167);color: white;">School</th>
  <th style="background-color:rgb(99, 124, 167);color: white;">Campus</th>
  <th style="background-color:rgb(99, 124, 167);color: white;">College</th>
</tr>
</thead>
<tbody></tbody>
</table>
</div>
</div>

<script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
<script>
let apiData = [];
const tableBody = document.querySelector('#timetableTable tbody');
const userSchoolId = <?php echo $schoolId ?? 'null'; ?>;

const filters = {
  id: document.getElementById('idFilter'),
  program: document.getElementById('programFilter'),
  yearOfStudy: document.getElementById('yearOfStudyFilter'),
  group: document.getElementById('groupFilter')
};

const searchFields = {
  lecturer: document.getElementById('lecturerSearch'),
  facility: document.getElementById('facilitySearch'),
  module: document.getElementById('moduleSearch')
};

const currentViewEl = document.getElementById('currentView');

// Helper to populate select options
function setOptions(select, items, getId=id=>id, getText=obj=>obj.name || obj.year || '') {
  const val = select.value;
  select.innerHTML = '<option value="">All</option>';
  [...new Map(items.map(i=>[getId(i),i]))].forEach(([id,obj])=>{
    const opt = document.createElement('option');
    opt.value = id;
    opt.textContent = getText(obj) || id;
    if (id == val) opt.selected = true;
    select.appendChild(opt);
  });
}

// Update "Currently Viewing" info
function updateCurrentView() {
  let html = '';

  let academicYear = "<?php echo $academic_year_label ?? ''; ?>";
  let semester = "<?php echo $semester ?? ''; ?>";
  let campusName = "<?php echo $campus['name'] ?? ''; ?>";
  let schoolName = "<?php echo htmlspecialchars($schoolName ?? '') ?>";
  let collegeName = "<?php echo htmlspecialchars($collegeName ?? '') ?>";

  if (academicYear) html += `<strong>Academic Year:</strong> ${academicYear}<br>`;
  if (semester) html += `<strong>Semester:</strong> ${semester}<br>`;
  if (campusName) html += `<strong>Campus:</strong> ${campusName}<br>`;
  html += `<strong>School:</strong> ${schoolName}<br>`;
  html += `<strong>College:</strong> ${collegeName}<br>`;
  
  if (filters.program.value) html += `<strong>Program:</strong> ${filters.program.options[filters.program.selectedIndex].text}<br>`;
  if (filters.yearOfStudy.value) html += `<strong>Year of Study:</strong> ${filters.yearOfStudy.options[filters.yearOfStudy.selectedIndex].text}<br>`;
  if (filters.group.value) html += `<strong>Group:</strong> ${filters.group.options[filters.group.selectedIndex].text}<br>`;

  if (filters.id.value) html += `<strong>ID:</strong> ${filters.id.value}<br>`;
  if (searchFields.lecturer.value) html += `<strong>Lecturer Search:</strong> "${searchFields.lecturer.value}"<br>`;
  if (searchFields.facility.value) html += `<strong>Facility Search:</strong> "${searchFields.facility.value}"<br>`;
  if (searchFields.module.value) html += `<strong>Module Search:</strong> "${searchFields.module.value}"<br>`;

  currentViewEl.innerHTML = html || '<em>Currently viewing full timetable (no filters applied)</em>';
}

// Check if lecturer matches search term
function lecturerMatches(lecturer, searchTerm) {
  if (!lecturer || !searchTerm) return false;
  const term = searchTerm.toLowerCase();
  return (lecturer.names && lecturer.names.toLowerCase().includes(term)) ||
         (lecturer.email && lecturer.email.toLowerCase().includes(term)) ||
         (lecturer.phone && lecturer.phone.toLowerCase().includes(term));
}

// Check if timetable entry matches lecturer search
function matchesLecturerSearch(timetableEntry, searchTerm) {
  if (!searchTerm) return true;
  
  // Check module leader
  if (lecturerMatches(timetableEntry.leader_lecturer, searchTerm)) {
    return true;
  }
  
  // Check other lecturers
  if (timetableEntry.other_lecturers && timetableEntry.other_lecturers.some(lecturer => 
    lecturerMatches(lecturer, searchTerm)
  )) {
    return true;
  }
  
  return false;
}

// Check if facility matches search term
function matchesFacilitySearch(timetableEntry, searchTerm) {
  if (!searchTerm) return true;
  if (!timetableEntry.facility) return false;
  
  const term = searchTerm.toLowerCase();
  const facilityName = timetableEntry.facility.name ? timetableEntry.facility.name.toLowerCase() : '';
  const siteName = timetableEntry.facility.site?.name ? timetableEntry.facility.site.name.toLowerCase() : '';
  
  return facilityName.includes(term) || siteName.includes(term);
}

// Check if module matches search term
function matchesModuleSearch(timetableEntry, searchTerm) {
  if (!searchTerm) return true;
  
  const term = searchTerm.toLowerCase();
  const courseName = timetableEntry.course ? timetableEntry.course.toLowerCase() : '';
  const courseCode = timetableEntry.code ? timetableEntry.code.toLowerCase() : '';
  
  return courseName.includes(term) || courseCode.includes(term);
}

// Load timetable
async function loadTimetable() {
  try {
    const res = await fetch('./get_timetable.php');
    const json = await res.json();
    
    // Filter data based on user's school ID and campus
    apiData = (json.data || []).map(t => {
      t.groups = (t.groups || []).filter(g => {
        // Filter by school and campus through intake
        const schoolMatch = g.school?.id == userSchoolId;
        const campusMatch = !<?php echo $campusId ? 'true' : 'false' ?> || 
                          (g.intake?.campus?.id == <?php echo $campusId ?? 0; ?>);
        return schoolMatch && campusMatch;
      });
      return t;
    }).filter(t => t.groups.length > 0);

    updateFilters();
    renderTable(filterData());
  } catch(e) {
    console.error(e);
  }
}

// Nested filter updates
function updateFilters() {
  const groups = apiData.flatMap(t => t.groups || []);

  // Get unique programs
  const programs = [...new Map(groups.map(g => [g.program?.id, g.program]))].map(([_, p]) => p).filter(Boolean);
  setOptions(filters.program, programs, p => p.id);

  // Year of Study filter - depends on program selection
  const selectedProg = filters.program.value;
  let years = selectedProg
    ? groups.filter(g => g.program?.id == selectedProg && g.intake?.year_of_study)
        .map(g => ({ id: g.intake.year_of_study, name: `Year ${g.intake.year_of_study}` }))
    : groups.filter(g => g.intake?.year_of_study)
        .map(g => ({ id: g.intake.year_of_study, name: `Year ${g.intake.year_of_study}` }));
  
  const uniqueYears = [...new Map(years.map(y => [y.id, y])).values()]
    .sort((a, b) => a.id - b.id);
  
  setOptions(filters.yearOfStudy, uniqueYears, y => y.id, y => y.name);

  // Get current filter values
  const currentProgram = filters.program.value;
  const currentYear = filters.yearOfStudy.value;

  // Filter groups based on current selections
  let filteredGroups = groups.filter(g => {
    if (!currentProgram && !currentYear) return true;
    return (!currentProgram || g.program?.id == currentProgram) &&
           (!currentYear || g.intake?.year_of_study == currentYear);
  });

  // Remove duplicate groups (same id, program, and year)
  const uniqueGroups = [];
  const seen = new Set();
  
  filteredGroups.forEach(g => {
    const key = `${g.id}_${g.program?.id}_${g.intake?.year_of_study}`;
    if (!seen.has(key)) {
      seen.add(key);
      uniqueGroups.push(g);
    }
  });

  // Sort groups by program name, then year, then group name
  uniqueGroups.sort((a, b) => {
    const prog = (a.program?.name || '').localeCompare(b.program?.name || '');
    if (prog !== 0) return prog;
    const year = (a.intake?.year_of_study || 0) - (b.intake?.year_of_study || 0);
    if (year !== 0) return year;
    return (a.name || '').localeCompare(b.name || '');
  });

  // Store group data for display with program and year information
  window.groupData = {};
  uniqueGroups.forEach(g => {
    const prog = g.program?.name ? ` (${g.program.name})` : '';
    const year = g.intake?.year_of_study ? ` - Y${g.intake.year_of_study}` : '';
    window.groupData[g.id] = g;
    g.displayName = `${g.name}${prog}${year}`;
  });

  // Update group dropdown with the filtered and sorted groups
  setOptions(filters.group, uniqueGroups, g => g.id, g => g.displayName);
}

// Helper function to get day order (Monday = 0, Tuesday = 1, etc.)
function getDayOrder(day) {
  const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
  return days.indexOf(day);
}

// Helper function to convert time string to minutes since midnight for sorting
function timeToMinutes(timeStr) {
  if (!timeStr) return 0;
  const [hours, minutes] = timeStr.split(':').map(Number);
  return hours * 60 + minutes;
}

// Filter data by current selection
function filterData() {
  const id = filters.id.value;
  const prog = filters.program.value;
  const yearOfStudy = filters.yearOfStudy.value;
  const group = filters.group.value;
  const campusId = <?php echo $campusId ?? 0; ?>;

  const lecturerSearch = searchFields.lecturer.value.trim().toLowerCase();
  const facilitySearch = searchFields.facility.value.trim().toLowerCase();
  const moduleSearch = searchFields.module.value.trim().toLowerCase();

  // First, filter the data
  const filteredData = apiData
    .filter(t => !id || t.id == id)  // Filter by ID if specified
    .filter(t => matchesLecturerSearch(t, lecturerSearch))
    .filter(t => matchesFacilitySearch(t, facilitySearch))
    .filter(t => matchesModuleSearch(t, moduleSearch))
    .map(t => ({
      ...t,
      groups: (t.groups || []).filter(gr => {
        const campusMatch = !campusId || (gr.intake?.campus?.id == campusId);
        const yearMatch = !yearOfStudy || gr.intake?.year_of_study == yearOfStudy;
        const programMatch = !prog || gr.program?.id == prog;
        const groupMatch = !group || gr.id == group;
        
        return campusMatch && yearMatch && programMatch && groupMatch;
      })
    }))
    .filter(t => t.groups.length > 0);

  // Sort the data by day and time
  return filteredData.sort((a, b) => {
    // If either timetable entry has no sessions, put it at the end
    if (!a.sessions || a.sessions.length === 0) return 1;
    if (!b.sessions || b.sessions.length === 0) return -1;
    
    // Get the first session for each timetable entry
    const sessionA = a.sessions[0];
    const sessionB = b.sessions[0];
    
    // Compare days first
    const dayOrderA = getDayOrder(sessionA.day);
    const dayOrderB = getDayOrder(sessionB.day);
    
    if (dayOrderA !== dayOrderB) {
      return dayOrderA - dayOrderB;
    }
    
    // If same day, compare start times
    const startTimeA = timeToMinutes(sessionA.start);
    const startTimeB = timeToMinutes(sessionB.start);
    
    return startTimeA - startTimeB;
  });
}

// Add CSS for clickable rows
const style = document.createElement('style');
style.textContent = `
  .clickable-row { cursor: pointer; }
  .clickable-row:hover { background-color: #f1f1f1; }
  .clickable-row a { color: inherit; text-decoration: none; }
`;
document.head.appendChild(style);

// Render table
function renderTable(data) {
  tableBody.innerHTML = '';
  data.forEach(t => {
    const sessions = t.sessions || [];
    const facility = t.facility?.name || '';
    const siteName = t.facility?.site?.name || '';
    const leaderName = t.leader_lecturer?.names ? `${t.leader_lecturer.names} (Module Leader)` : '';
    const otherLecturers = (t.other_lecturers?.map(l => l.names) || []).join(', ');
    const lecturers = [leaderName, otherLecturers].filter(Boolean).join(', ');
    const viewUrl = `timetable_details_school.php?id=${t.id}`;

    const sessionMap = {};
    sessions.forEach(s => {
      const key = `${s.day}-${s.start}-${s.end}`;
      if (!sessionMap[key]) sessionMap[key] = [];
      sessionMap[key].push(s);
    });

    const sessionKeys = Object.keys(sessionMap);
    sessionKeys.forEach((key, idx) => {
      const [day, start, end] = key.split('-');
      const groups = t.groups || [];
      const rowspan = groups.length;
      groups.forEach((g, gIdx) => {
        const firstRow = idx === 0 && gIdx === 0;
        const row = document.createElement('tr');
        row.className = 'clickable-row';
        row.onclick = () => window.location.href = viewUrl;
        
        row.innerHTML = `
          ${firstRow ? `<td rowspan="${rowspan * sessionKeys.length}">${t.id}</td>` : ''}
          ${gIdx === 0 ? `<td rowspan="${rowspan}">${day}</td><td rowspan="${rowspan}">${start}-${end}</td>` : ''}
          ${firstRow ? `<td rowspan="${rowspan * sessionKeys.length}">${t.course}</td>
          <td rowspan="${rowspan * sessionKeys.length}">${t.code}</td>
          <td rowspan="${rowspan * sessionKeys.length}">${t.credits}</td>
          <td rowspan="${rowspan * sessionKeys.length}">${facility}${siteName ? ' (' + siteName + ')' : ''} <span class="badge bg-${t.status=='Approved'?'success':'danger'}">${t.status}</span></td>` : ''}
          <td>${g.name}</td>
          <td>${g.intake?.year_of_study ? 'Year ' + g.intake.year_of_study : ''}</td>
          <td>${g.program?.name || ''}</td>
          <td>${g.school?.name || ''}</td>
          <td>${g.intake?.campus?.name || ''}</td>
          <td>${g.college?.name || ''}</td>
          ${firstRow ? `<td rowspan="${rowspan * sessionKeys.length}">${lecturers}</td>` : ''}`;
        
        tableBody.appendChild(row);
      });
    });
  });

  updateCurrentView();
}

// Reset filters
document.getElementById('resetFilters').addEventListener('click', () => {
  Object.values(filters).forEach(f => {
    if (f) f.value = '';
  });
  updateFilters();
  renderTable(filterData());
});

// Filter change
Object.values(filters).forEach(f => {
  if (f) f.addEventListener('change', () => {
    updateFilters();
    renderTable(filterData());
  });
});

// Search button click
document.getElementById('searchBtn').addEventListener('click', () => {
  renderTable(filterData());
});

// Clear search button click
document.getElementById('clearSearch').addEventListener('click', () => {
  Object.values(searchFields).forEach(f => f.value = '');
  renderTable(filterData());
});

// Allow pressing Enter in search fields
Object.values(searchFields).forEach(f => {
  f.addEventListener('keyup', (e) => {
    if (e.key === 'Enter') {
      renderTable(filterData());
    }
  });
});

// Export
document.getElementById('exportBtn').addEventListener('click', () => {
  const table = document.getElementById('timetableTable');
  const wb = XLSX.utils.table_to_book(table, {sheet: "Timetable"});
  XLSX.writeFile(wb, "timetable.xlsx");
});

window.addEventListener('DOMContentLoaded', loadTimetable);
</script>


  <!-- Template Main JS File -->
  <script src="assets/js/main.js"></script>
</main>
</body>

</main>
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