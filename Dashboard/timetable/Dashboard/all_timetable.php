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
<title>Timetable</title>
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

<div class="container table-card">

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
      <label for="collegeFilter" class="form-label fw-semibold">College</label>
      <select id="collegeFilter" class="form-select form-select-sm">
        <option value="">All</option>
      </select>
    </div>
    <div class="col-md-2 col-sm-4">
      <label for="schoolFilter" class="form-label fw-semibold">School</label>
      <select id="schoolFilter" class="form-select form-select-sm">
        <option value="">All</option>
      </select>
    </div>
    <div class="col-md-2 col-sm-4">
      <label for="departmentFilter" class="form-label fw-semibold">Department</label>
      <select id="departmentFilter" class="form-select form-select-sm">
        <option value="">All</option>
      </select>
    </div>
    <div class="col-md-2 col-sm-4">
      <label for="programFilter" class="form-label fw-semibold">Program</label>
      <select id="programFilter" class="form-select form-select-sm">
        <option value="">All</option>
      </select>
    </div>
    <div class="col-md-2 col-sm-4">
      <label for="intakeFilter" class="form-label fw-semibold">Intake</label>
      <select id="intakeFilter" class="form-select form-select-sm">
        <option value="">All</option>
      </select>
    </div>
    <div class="col-md-2 col-sm-4">
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
<h2 class="fw-semibold timetable-title mt-2">Timetable</h2>
<div id="currentView" style="font-size:13px; line-height:1.4; margin-bottom:10px; padding:0;">
  Loading timetable view...
</div>


<div class="table-responsive">
<table class="table table-bordered" id="timetableTable">
<thead class="table-dark">
<tr>
  <th rowspan="2">Day</th>
  <th rowspan="2">Time</th>
  <th rowspan="2">Course</th>
  <th rowspan="2">Code</th>
  <th rowspan="2">Credits</th>
  <th rowspan="2">Facility</th>
  <th colspan="6">Group Details</th>
  <th rowspan="2">Lecturers</th>
  <th rowspan="2">Actions</th>
</tr>
<tr>
  <th>Group</th>
  <th>Intake</th>
  <th>Program</th>
  <th>Department</th>
  <th>School</th>
  <th>College</th>
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

const filters = {
  college: document.getElementById('collegeFilter'),
  school: document.getElementById('schoolFilter'),
  department: document.getElementById('departmentFilter'),
  program: document.getElementById('programFilter'),
  intake: document.getElementById('intakeFilter'),
  group: document.getElementById('groupFilter')
};

const searchFields = {
  lecturer: document.getElementById('lecturerSearch'),
  facility: document.getElementById('facilitySearch'),
  module: document.getElementById('moduleSearch')
};

const currentViewEl = document.getElementById('currentView');

// Helper to populate select options
function setOptions(select, items, getId=id=>id) {
  const val = select.value;
  select.innerHTML = '<option value="">All</option>';
  [...new Map(items.map(i=>[getId(i),i]))].forEach(([id,obj])=>{
    select.innerHTML += `<option value="${id}">${obj.name || obj.year+'-'+(obj.month||'')}</option>`;
  });
  if ([...select.options].some(o=>o.value===val)) select.value = val;
}

// Update "Currently Viewing" info
function updateCurrentView() {
  let html = '';

  let academicYear = "<?php echo $academic_year_label ?? ''; ?>";
  let semester = "<?php echo $semester ?? ''; ?>";

  if (academicYear) html += `<strong>Academic Year:</strong> ${academicYear}<br>`;
  if (semester) html += `<strong>Semester:</strong> ${semester}<br>`;
  let campusName = "<?php echo $campus['name'] ?? ''; ?>";
  if (campusName) html += `<strong>Campus:</strong> ${campusName}<br>`;

  if (searchFields.lecturer.value) html += `<strong>Lecturer Search:</strong> "${searchFields.lecturer.value}"<br>`;
  if (searchFields.facility.value) html += `<strong>Facility Search:</strong> "${searchFields.facility.value}"<br>`;
  if (searchFields.module.value) html += `<strong>Module Search:</strong> "${searchFields.module.value}"<br>`;
  if (filters.college.value) html += `<strong>College:</strong> ${filters.college.options[filters.college.selectedIndex].text}<br>`;
  if (filters.school.value) html += `<strong>School:</strong> ${filters.school.options[filters.school.selectedIndex].text}<br>`;
  if (filters.department.value) html += `<strong>Department:</strong> ${filters.department.options[filters.department.selectedIndex].text}<br>`;
  if (filters.program.value) html += `<strong>Program:</strong> ${filters.program.options[filters.program.selectedIndex].text}<br>`;
  if (filters.intake.value) html += `<strong>Intake:</strong> ${filters.intake.options[filters.intake.selectedIndex].text}<br>`;
  if (filters.group.value) html += `<strong>Group:</strong> ${filters.group.options[filters.group.selectedIndex].text}<br>`;

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
    apiData = (json.data || []).map(t=>{
      t.groups = (t.groups || []).filter(g=>g.campus?.id == <?php echo $campusId ?? 0; ?>);
      return t;
    }).filter(t=>t.groups.length>0);

    updateFilters();
    renderTable(filterData());
  } catch(e){console.error(e);}
}

// Nested filter updates
function updateFilters() {
  const groups = apiData.flatMap(t=>t.groups||[]);

  // Colleges - from all groups
  let colleges = groups.map(g=>g.college).filter(Boolean);
  setOptions(filters.college, colleges, g=>g.id);

  // Schools - filtered by selected college or all
  let selectedCollege = filters.college.value;
  let schools = selectedCollege 
    ? groups.filter(g=>g.college?.id==selectedCollege).map(g=>g.school) 
    : groups.map(g=>g.school);
  setOptions(filters.school, schools.filter(Boolean), g=>g.id);

  // Departments - filtered by selected school or all
  let selectedSchool = filters.school.value;
  let departments = selectedSchool 
    ? groups.filter(g=>g.school?.id==selectedSchool).map(g=>g.department) 
    : groups.map(g=>g.department);
  setOptions(filters.department, departments.filter(Boolean), g=>g.id);

  // Programs - can come from department or directly from school
  let selectedDept = filters.department.value;
  let programs = [];
  
  if (selectedDept) {
    // Programs from selected department
    programs = groups.filter(g=>g.department?.id==selectedDept).map(g=>g.program);
  } else if (selectedSchool) {
    // Programs from selected school (department is null)
    programs = groups.filter(g=>
      g.school?.id==selectedSchool && 
      (!g.department || g.department.id==null)
    ).map(g=>g.program);
  } else {
    // All programs
    programs = groups.map(g=>g.program);
  }
  setOptions(filters.program, programs.filter(Boolean), g=>g.id);

  // Intakes - filtered by selected program or all
  let selectedProg = filters.program.value;
  let intakes = selectedProg 
    ? groups.filter(g=>g.program?.id==selectedProg).map(g=>g.intake) 
    : groups.map(g=>g.intake);
  setOptions(filters.intake, intakes.filter(Boolean), g=>g.id);

  // Groups - filtered by selected intake or all
  let selectedIntake = filters.intake.value;
  let groupList = selectedIntake 
    ? groups.filter(g=>g.intake?.id==selectedIntake) 
    : groups;
  setOptions(filters.group, groupList.filter(Boolean), g=>g.id);
}

// Filter data by current selection
function filterData() {
  const college = filters.college.value;
  const school = filters.school.value;
  const dept = filters.department.value;
  const prog = filters.program.value;
  const intake = filters.intake.value;
  const group = filters.group.value;

  const lecturerSearch = searchFields.lecturer.value.trim().toLowerCase();
  const facilitySearch = searchFields.facility.value.trim().toLowerCase();
  const moduleSearch = searchFields.module.value.trim().toLowerCase();

  return apiData
    .filter(t => matchesLecturerSearch(t, lecturerSearch))
    .filter(t => matchesFacilitySearch(t, facilitySearch))
    .filter(t => matchesModuleSearch(t, moduleSearch))
    .map(t => {
      const g = (t.groups||[]).filter(gr => {
        return (!college || gr.college?.id==college)
            && (!school || gr.school?.id==school)
            && (!dept || (gr.department ? gr.department.id==dept : dept===''))
            && (!prog || gr.program?.id==prog)
            && (!intake || gr.intake?.id==intake)
            && (!group || gr.id==group);
      });
      return {...t, groups: g};
    })
    .filter(t => t.groups.length > 0);
}

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
        tableBody.innerHTML += `<tr>
          ${gIdx === 0 ? `<td rowspan="${rowspan}">${day}</td><td rowspan="${rowspan}">${start}-${end}</td>` : ''}
          ${firstRow ? `<td rowspan="${rowspan * sessionKeys.length}">${t.course}</td>
          <td rowspan="${rowspan * sessionKeys.length}">${t.code}</td>
          <td rowspan="${rowspan * sessionKeys.length}">${t.credits}</td>
          <td rowspan="${rowspan * sessionKeys.length}">${facility}${siteName ? ' (' + siteName + ')' : ''} <span class="badge bg-${t.status=='Approved'?'success':'danger'}">${t.status}</span></td>` : ''}
          <td>${g.name}</td>
          <td>${g.intake ? `${g.intake.year}-${String(g.intake.month).padStart(2,'0')}` : ''}</td>
          <td>${g.program?.name || ''}</td>
          <td>${g.department?.name || ''}</td>
          <td>${g.school?.name || ''}</td>
          <td>${g.college?.name || ''}</td>
          ${firstRow ? `<td rowspan="${rowspan * sessionKeys.length}">${lecturers}</td>
          <td rowspan="${rowspan * sessionKeys.length}"><a href="timetable_details_all.php?id=${t.id}" class="btn btn-primary btn-sm">View</a></td>` : ''}
        </tr>`;
      });
    });
  });

  updateCurrentView();
}

// Reset filters
document.getElementById('resetFilters').addEventListener('click',()=>{
  Object.values(filters).forEach(f=>f.value='');
  Object.values(searchFields).forEach(f=>f.value='');
  updateFilters();
  renderTable(filterData());
});

// Filter change
Object.values(filters).forEach(f=>f.addEventListener('change',()=>{
  updateFilters();
  renderTable(filterData());
}));

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
document.getElementById('exportBtn').addEventListener('click',()=>{
  const table = document.getElementById('timetableTable');
  const wb = XLSX.utils.table_to_book(table,{sheet:"Timetable"});
  XLSX.writeFile(wb,"timetable.xlsx");
});

window.addEventListener('DOMContentLoaded',loadTimetable);
</script>

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
</main>
</body>
</html>