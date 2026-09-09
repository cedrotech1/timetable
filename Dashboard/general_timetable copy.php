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
  $campusId  = $_GET['campus'] ?? ($user['campus'] ?? null); // Get campus from URL or user's default
  $collegeId = $user['college'] ?? null;
  $schoolId  = $user['school'] ?? null;

  // Fetch all campuses for the filter
  $campuses = [];
  $campusResult = $connection->query("SELECT * FROM campus ORDER BY name");
  if ($campusResult) {
      while($row = $campusResult->fetch_assoc()) {
          $campuses[] = $row;
      }
  }

  // fetch selected campus info
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
  <title>Timetable - <?php echo htmlspecialchars($campus['name'] ?? 'All Campuses'); ?></title>
  <meta content="" name="description">
  <style>
      .campus-filter {
          margin: 15px 0;
          padding: 10px;
          background: #f8f9fa;
          border-radius: 5px;
      }
      .campus-filter select {
          padding: 5px 10px;
          border-radius: 4px;
          border: 1px solid #ced4da;
      }
  </style>
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
  <body style="background-color: #f4f6f8;">
      <!-- Campus Filter -->
    
      <?php include('./includes/header.php'); ?>
      <?php include('./includes/menu.php'); ?>

      <main id="main" class="main">

  <div class="table-card">
  <div class=" mt-3">
          <form method="get" action="" class="campus-filter">
              <div class="row align-items-center">
                  <div class="col-md-4">
                      <label for="campus" class="form-label">Filter by Campus:</label>
                      <select name="campus" id="campus" class="form-select" onchange="this.form.submit()">
                          <option value="">All Campuses</option>
                          <?php foreach ($campuses as $c): ?>
                              <option value="<?php echo $c['id']; ?>" <?php echo ($c['id'] == $campusId) ? 'selected' : ''; ?>>
                                  <?php echo htmlspecialchars($c['name']); ?>
                              </option>
                          <?php endforeach; ?>
                      </select>
                  </div>
              </div>
          </form>
      </div>    

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
    <th rowspan="2" style="background-color:rgb(99, 124, 167);color: white;">Day</th>
    <th rowspan="2" style="background-color:rgb(99, 124, 167);color: white;">Time</th>
    <th rowspan="2" style="background-color:rgb(99, 124, 167);color: white;">Course</th>
    <th rowspan="2" style="background-color:rgb(99, 124, 167);color: white;">Code</th>
    <th rowspan="2" style="background-color:rgb(99, 124, 167);color: white;">Credits</th>
    <th rowspan="2" style="background-color:rgb(99, 124, 167);color: white;">Facility</th>
    <th colspan="7" style="background-color:rgb(99, 124, 167);color: white;">Group Details</th>
    <th rowspan="2" style="background-color:rgb(99, 124, 167);color: white;">Lecturers</th>  
    <th rowspan="2" style="background-color:rgb(99, 124, 167);color: white;">Actions</th>
  </tr>
  <tr>
    <th style="background-color:rgb(99, 124, 167);color: white;">Group</th>
    <th style="background-color:rgb(99, 124, 167);color: white;">Year of Study</th>
    <th style="background-color:rgb(99, 124, 167);color: white;">Program</th>
    <th style="background-color:rgb(99, 124, 167);color: white;">Department</th>
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

  const filters = {
    college: document.getElementById('collegeFilter'),
    school: document.getElementById('schoolFilter'),
    department: document.getElementById('departmentFilter'),
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

    // Always show academic year and semester
    let academicYear = "<?php echo $academic_year_label ?? 'Not set'; ?>";
    let semester = "<?php echo $semester ?? 'Not set'; ?>";
    html += `<strong>Academic Year:</strong> ${academicYear}<br>`;
    html += `<strong>Trimester:</strong> ${semester}<br>`;
    
    // Show selected campus or 'All Campuses' by default
    const campusSelect = document.getElementById('campus');
    const selectedOption = campusSelect?.options[campusSelect.selectedIndex];
    const campusText = selectedOption?.value ? selectedOption.text : 'All Campuses';
    html += `<strong>Campus:</strong> ${campusText}<br>`;

    if (searchFields.lecturer.value) html += `<strong>Lecturer Search:</strong> "${searchFields.lecturer.value}"<br>`;
    if (searchFields.facility.value) html += `<strong>Facility Search:</strong> "${searchFields.facility.value}"<br>`;
    if (searchFields.module.value) html += `<strong>Module Search:</strong> "${searchFields.module.value}"<br>`;
    if (filters.college.value) html += `<strong>College:</strong> ${filters.college.options[filters.college.selectedIndex].text}<br>`;
    if (filters.school.value) html += `<strong>School:</strong> ${filters.school.options[filters.school.selectedIndex].text}<br>`;
    if (filters.department.value) html += `<strong>Department:</strong> ${filters.department.options[filters.department.selectedIndex].text}<br>`;
    if (filters.program.value) html += `<strong>Program:</strong> ${filters.program.options[filters.program.selectedIndex].text}<br>`;
    if (filters.yearOfStudy.value) html += `<strong>Year of Study:</strong> ${filters.yearOfStudy.options[filters.yearOfStudy.selectedIndex].text}<br>`;
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
      
      // Get all timetable entries
      let timetableData = json.data || [];
      
      // Apply campus filter if selected
      const selectedCampusId = document.getElementById('campus')?.value;
      if (selectedCampusId) {
        timetableData = timetableData.filter(entry => {
          // Check if any group in this entry belongs to the selected campus through intake
          return entry.groups?.some(group => group.intake?.campus?.id == selectedCampusId) ||
                // Or if the lecturer is from the selected campus
                (entry.leader_lecturer_id && entry.leader_lecturer?.campus?.id == selectedCampusId) ||
                // Or if any other lecturer is from the selected campus
                entry.other_lecturers?.some(lect => lect.campus?.id == selectedCampusId);
        });
      }
      
      apiData = timetableData;
      updateFilters();
      renderTable(filterData());
    } catch(e) {
      console.error('Error loading timetable:', e);
    }
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

    // Year of Study filter - depends on program selection
    let selectedProg = filters.program.value;
    let years = [];
    
    if (selectedProg) {
      years = groups
        .filter(g => g.program?.id == selectedProg && g.intake?.year_of_study)
        .map(g => ({
          id: g.intake.year_of_study,
          name: `Year ${g.intake.year_of_study}`
        }));
    } else {
      years = groups
        .filter(g => g.intake?.year_of_study)
        .map(g => ({
          id: g.intake.year_of_study,
          name: `Year ${g.intake.year_of_study}`
        }));
    }
    
    // Get unique years and sort them
    const uniqueYears = [...new Map(years.map(item => [item.id, item])).values()]
      .sort((a, b) => a.id - b.id);
      
    setOptions(filters.yearOfStudy, uniqueYears, y => y.id, y => y.name);

    // Group filter - depends on year of study selection
    let selectedYear = filters.yearOfStudy.value;
    let groupList = selectedYear ? 
      groups.filter(g => g.intake?.year_of_study == selectedYear) : 
      groups;

    setOptions(filters.group, groupList.filter(Boolean), g=>g.id);
  }

  // Filter data by current selection
  // Helper function to get day order (Sunday = 0, Monday = 1, etc.)
  function getDayOrder(day) {
    const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday','Sunday'];
    return days.indexOf(day);
  }

  // Helper function to convert time string to minutes since midnight for sorting
  function timeToMinutes(timeStr) {
    if (!timeStr) return 0;
    const [hours, minutes] = timeStr.split(':').map(Number);
    return hours * 60 + minutes;
  }

  function filterData() {
    const college = filters.college.value;
    const school = filters.school.value;
    const dept = filters.department.value;
    const prog = filters.program.value;
    const yearOfStudy = filters.yearOfStudy.value;
    const group = filters.group.value;

    const lecturerSearch = searchFields.lecturer.value.trim().toLowerCase();
    const facilitySearch = searchFields.facility.value.trim().toLowerCase();
    const moduleSearch = searchFields.module.value.trim().toLowerCase();

    // First, filter the data
    const filteredData = apiData
      .filter(t => matchesLecturerSearch(t, lecturerSearch))
      .filter(t => matchesFacilitySearch(t, facilitySearch))
      .filter(t => matchesModuleSearch(t, moduleSearch))
      .map(t => {
        const g = (t.groups||[]).filter(gr => {
          return (!college || gr.college?.id==college)
              && (!school || gr.school?.id==school)
              && (!dept || (gr.department ? gr.department.id==dept : dept===''))
              && (!prog || gr.program?.id==prog)
              && (!yearOfStudy || gr.intake?.year_of_study == yearOfStudy)
              && (!group || gr.id==group);
        });
        return {...t, groups: g};
      })
      .filter(t => t.groups.length > 0);

    // Sort the data by day and time
    return filteredData.sort((a, b) => {
      // If either timetable entry has no sessions, put it at the end
      if (!a.sessions || a.sessions.length === 0) return 1;
      if (!b.sessions || b.sessions.length === 0) return -1;
      
      // Get the first session for each timetable entry (assuming each entry has at least one session)
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
            <td>${t.course || 'N/A'}</td>
            <td>${t.code || 'N/A'}</td>
            <td>${t.credits || 'N/A'}</td>
            <td>${facility || 'N/A'}${siteName ? ' (' + siteName + ')' : ''} <span class="badge bg-${t.status=='Approved'?'success':'danger'}">${t.status || 'Pending'}</span></td>
            <td>${g.name}</td>
            <td>${g.intake?.year_of_study ? 'Year ' + g.intake.year_of_study : ''}</td>
            <td>${g.program?.name || ''}</td>
            <td>${g.department?.name || ''}</td>
            <td>${g.school?.name || ''}</td>
            <td>${g.intake?.campus?.name || ''}</td>
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
  // Add event listener for campus change
  document.getElementById('campus')?.addEventListener('change', () => {
    loadTimetable();
    updateCurrentView();
  });

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
  </body>
  </html>