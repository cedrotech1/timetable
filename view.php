<?php 
    session_start();
    include('connection.php');

    // Get academic year from session or use defaults
    $academic_year_id = $_SESSION['academic_year_id'] ?? null;
    $semester = $_SESSION['semester'] ?? 1; // Default to first semester if not set
    $academic_year_label = $_SESSION['academic_year_label'] ?? date('Y') . '/' . (date('Y') + 1);

    // Get campus from URL or use default (first campus)
    $campusId = $_GET['campus'] ?? null;
    $role = 'public';
    
    // If no campus is selected, get the first available campus
    if (!$campusId) {
        $result = $connection->query("SELECT id FROM campus ORDER BY id LIMIT 1");
        if ($result && $row = $result->fetch_assoc()) {
            $campusId = $row['id'];
        }
    }

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
    <title>University Timetable - <?php echo htmlspecialchars($campus['name'] ?? 'All Campuses'); ?></title>
    <meta content="" name="description">
    <meta content="" name="keywords">

    <!-- Favicons -->
    <link href="assets/img/icon1.png" rel="icon">
    <link href="assets/img/icon1.png" rel="apple-touch-icon">

    <!-- Google Fonts -->
    <link href="https://fonts.gstatic.com" rel="preconnect">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">

    <!-- Vendor CSS Files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
    <link href="assets/vendor/quill/quill.snow.css" rel="stylesheet">
    <link href="assets/vendor/quill/quill.bubble.css" rel="stylesheet">
    <link href="assets/vendor/remixicon/remixicon.css" rel="stylesheet">
    <link href="assets/vendor/simple-datatables/style.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #f4f6f8;
            padding-top: 100px; /* Space for fixed navbar */
        }

        /* Fixed Navbar */
        .fixed-top-navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            background-color: #031f50;
            padding: 1.2rem 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .staff-login-btn {
            font-size: 1.25rem !important;
            padding: 0.75rem 2rem !important;
        }

        main { font-size: 12px; }
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
        .filter-container label { font-size: 0.85rem; margin-bottom: 4px; }
        .filter-container select { font-size: 0.85rem; }
        .table-card { background: #fff !important; padding: 12px 15px; border-radius: 6px; }
        .timetable-title {
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
        .btn-primary {
            background-color: #031f50 !important;
            color: white !important;
            font-size: 12px !important;
            padding: 2px 5px !important;
        }

        /* Help Tip Card */
        .card-header h5 { font-size: 1.1rem; }
        .card-body ol { padding-left: 1.2rem; }
        .card-body li { margin-bottom: 0.5rem; }
        .alert { font-size: 0.9rem; }

        /* Loading Spinner */
        .loading-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: flex; flex-direction: column;
            justify-content: center; align-items: center;
            z-index: 9999;
        }
        .spinner-border { width: 3rem; height: 3rem; color: #031f50; }
        .loading-text { margin-top: 1rem; font-size: 1.2rem; color: #031f50; font-weight: 500; }

        #timetableTable { opacity: 0; transition: opacity 0.3s ease-in-out; }
        #timetableTable.loaded { opacity: 1; }
    </style>
</head>
<body>

    <!-- FIXED NAVBAR -->
    <nav class="navbar navbar-dark fixed-top-navbar">
        <div class="container-fluid d-flex justify-content-between align-items-center">
            <!-- Logo + University Name -->
            <a class="navbar-brand d-flex align-items-center text-white text-decoration-none" href="#">
                <img src="icon1.png" alt="Logo" width="50" height="50" class="me-3">
                <span class="fw-bold fs-4">University of Rwanda</span>
            </a>

            <!-- Big Staff Login Button -->
            <a href="login.php" class="btn btn-primary btn-lg staff-login-btn">
                Staff Login
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container-fluid py-3">

        <div class="table-card">
            <!-- Help Tip Card -->
            <div class="card mb-4 border-primary">
                <div class="card-header text-white d-flex justify-content-between align-items-center" style="background-color: #031f50;">
                    <h5 class="card-title mb-0">How to find your timetable</h5>
                    <button type="button" class="btn-close btn-close-white" onclick="this.closest('.card').style.display='none'"></button>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h6 class="text-primary">Follow these steps to see your specific timetable:</h6>
                            <ol class="mb-0">
                                <li>Select your <strong>Campus</strong> from the dropdown</li>
                                <li>Choose your <strong>College</strong> from the list</li>
                                <li>Select your <strong>School</strong></li>
                                <li>Pick your <strong>Program</strong> of study</li>
                                <li>Select your <strong>Year of Study</strong></li>
                                <li>Choose your specific <strong>Group</strong> (if applicable)</li>
                            </ol>
                        </div>
                        <div class="col-md-4 d-flex align-items-center">
                            <div class="alert alert-info mb-0 w-100">
                                For the most accurate results, start by selecting your campus and work your way through the filters from left to right.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Nested Filters -->
            <div class="filter-container mb-4">
                <div class="row g-3 align-items-end">
                    <div class="col-md-2 col-sm-4">
                        <form method="get" action="" class="campus-filter">
                            <label for="campus" class="form-label">Filter by Campus:</label>
                            <select name="campus" id="campus" class="form-select" onchange="this.form.submit()">
                                <option value="">All Campuses</option>
                                <?php foreach ($campuses as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo ($c['id'] == $campusId) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
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

            <button id="resetFilters" class="btn btn-primary">Reset Filters</button>
            <button id="exportBtn" class="btn btn-success">Export to Excel</button>

            <br><br>

            <!-- Loading Overlay -->
            <div id="loadingOverlay" class="loading-overlay">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <div class="loading-text">Loading timetable data...</div>
            </div>

            <!-- Dynamic view info -->
            <h2 class="fw-semibold timetable-title mt-2">Timetable</h2>
            <div id="currentView" style="font-size:13px; line-height:1.4; margin-bottom:10px; padding:0;">
                Preparing your timetable view...
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="timetableTable">
                    <thead class="table-dark">
                        <tr>
                            <th rowspan="2" style="background-color:rgb(99, 124, 167);color: white;">Day</th>
                            <th rowspan="2" style="background-color:rgb(99, 124, 167);color: white;">Time</th>
                            <th rowspan="2" style="background-color:rgb(99, 124, 167);color: white;">Course</th>
                            <th rowspan="2" style="background-color:rgb(99, 124, 167);color: white;">Credits</th>
                            <th rowspan="2" style="background-color:rgb(99, 124, 167);color: white;">Facility</th>
                            <th colspan="6" style="background-color:rgb(99, 124, 167);color: white;">Group Details</th>
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
    </div>

    <script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
    <script>
        let apiData = [];
        const tableBody = document.querySelector('#timetableTable tbody');

        const filters = {
            college: document.getElementById('collegeFilter'),
            school: document.getElementById('schoolFilter'),
            program: document.getElementById('programFilter'),
            yearOfStudy: document.getElementById('yearOfStudyFilter'),
            group: document.getElementById('groupFilter')
        };

        const currentViewEl = document.getElementById('currentView');

        function setOptions(select, items, getId = id => id, getText = obj => obj.name) {
            const val = select.value;
            select.innerHTML = '<option value="">All</option>';
            [...new Map(items.map(i => [getId(i), i]))].forEach(([id, obj]) => {
                select.innerHTML += `<option value="${id}">${getText(obj)}</option>`;
            });
            if ([...select.options].some(o => o.value === val)) select.value = val;
        }

        function updateCurrentView() {
            let html = '';
            const academicYear = "<?php echo $academic_year_label; ?>";
            const semester = "<?php echo $semester; ?>";
            html += `<strong>Academic Year:</strong> ${academicYear}<br>`;
            html += `<strong>Trimester:</strong> ${semester}<br>`;

            const campusSelect = document.getElementById('campus');
            const campusText = campusSelect?.value ? campusSelect.selectedOptions[0].text : 'All Campuses';
            html += `<strong>Campus:</strong> ${campusText}<br>`;

            if (filters.college.value) html += `<strong>College:</strong> ${filters.college.selectedOptions[0].text}<br>`;
            if (filters.school.value) html += `<strong>School:</strong> ${filters.school.selectedOptions[0].text}<br>`;
            if (filters.program.value) html += `<strong>Program:</strong> ${filters.program.selectedOptions[0].text}<br>`;
            if (filters.yearOfStudy.value) html += `<strong>Year of Study:</strong> ${filters.yearOfStudy.selectedOptions[0].text}<br>`;
            if (filters.group.value) html += `<strong>Group:</strong> ${filters.group.selectedOptions[0].text}<br>`;

            currentViewEl.innerHTML = html || '<em>Currently viewing full timetable (no filters applied)</em>';
        }

        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
            document.getElementById('timetableTable').classList.remove('loaded');
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
            document.getElementById('timetableTable').classList.add('loaded');
        }

        async function loadTimetable() {
            showLoading();
            try {
                const res = await fetch('./Dashboard/get_timetable.php');
                const json = await res.json();
                let timetableData = json.data || [];

                const selectedCampusId = document.getElementById('campus')?.value;
                if (selectedCampusId) {
                    timetableData = timetableData.filter(entry =>
                        entry.groups?.some(g => g.intake?.campus?.id == selectedCampusId) ||
                        entry.leader_lecturer?.campus?.id == selectedCampusId ||
                        entry.other_lecturers?.some(l => l.campus?.id == selectedCampusId)
                    );
                }

                apiData = timetableData;
                updateFilters();
                renderTable(filterData());
                hideLoading();
            } catch (e) {
                console.error('Error:', e);
                hideLoading();
                document.getElementById('loadingOverlay').innerHTML = `
                    <div class="text-danger text-center">
                        <div class="loading-text">Failed to load timetable.</div>
                        <button class="btn btn-primary mt-3" onclick="loadTimetable()">Retry</button>
                    </div>`;
            }
        }

        function updateFilters() {
            const groups = apiData.flatMap(t => t.groups || []);
            const colleges = groups.map(g => g.college).filter(Boolean);
            setOptions(filters.college, colleges, g => g.id, g => g.name);

            const selectedCollege = filters.college.value;
            const schools = selectedCollege
                ? groups.filter(g => g.college?.id == selectedCollege).map(g => g.school)
                : groups.map(g => g.school);
            setOptions(filters.school, schools.filter(Boolean), g => g.id, g => g.name);

            const selectedSchool = filters.school.value;
            const programs = selectedSchool
                ? groups.filter(g => g.school?.id == selectedSchool).map(g => g.program)
                : groups.map(g => g.program);
            setOptions(filters.program, programs.filter(Boolean), g => g.id, g => g.name);

            const selectedProg = filters.program.value;
            const years = selectedProg
                ? groups.filter(g => g.program?.id == selectedProg && g.intake?.year_of_study)
                    .map(g => ({ id: g.intake.year_of_study, name: `Year ${g.intake.year_of_study}` }))
                : groups.filter(g => g.intake?.year_of_study)
                    .map(g => ({ id: g.intake.year_of_study, name: `Year ${g.intake.year_of_study}` }));
            const uniqueYears = [...new Map(years.map(y => [y.id, y])).values()].sort((a, b) => a.id - b.id);
            setOptions(filters.yearOfStudy, uniqueYears, y => y.id, y => y.name);

            const currentCollege = filters.college.value;
            const currentSchool = filters.school.value;
            const currentProgram = filters.program.value;
            const currentYear = filters.yearOfStudy.value;

            let filteredGroups = groups.filter(g => {
                if (!currentCollege && !currentSchool && !currentProgram && !currentYear) return true;
                return (!currentCollege || g.college?.id == currentCollege) &&
                       (!currentSchool || g.school?.id == currentSchool) &&
                       (!currentProgram || g.program?.id == currentProgram) &&
                       (!currentYear || g.intake?.year_of_study == currentYear);
            });

            const uniqueGroups = [];
            const seen = new Set();
            filteredGroups.forEach(g => {
                const key = `${g.id}_${g.program?.id}_${g.intake?.year_of_study}`;
                if (!seen.has(key)) {
                    seen.add(key);
                    uniqueGroups.push(g);
                }
            });

            uniqueGroups.sort((a, b) => {
                const prog = (a.program?.name || '').localeCompare(b.program?.name || '');
                if (prog !== 0) return prog;
                const year = (a.intake?.year_of_study || 0) - (b.intake?.year_of_study || 0);
                if (year !== 0) return year;
                return (a.name || '').localeCompare(b.name || '');
            });

            window.groupData = {};
            uniqueGroups.forEach(g => {
                const prog = g.program?.name ? ` (${g.program.name})` : '';
                const year = g.intake?.year_of_study ? ` - Y${g.intake.year_of_study}` : '';
                window.groupData[g.id] = g;
                g.displayName = `${g.name}${prog}${year}`;
            });

            setOptions(filters.group, uniqueGroups, g => g.id, g => g.displayName);
        }

        function getDayOrder(day) {
            return ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'].indexOf(day);
        }

        function timeToMinutes(t) {
            if (!t) return 0;
            const [h, m] = t.split(':').map(Number);
            return h * 60 + m;
        }

        function filterData() {
            const college = filters.college.value;
            const school = filters.school.value;
            const prog = filters.program.value;
            const year = filters.yearOfStudy.value;
            const groupId = filters.group.value;

            const filtered = apiData
                .map(t => ({
                    ...t,
                    groups: (t.groups || []).filter(g =>
                        (!college || g.college?.id == college) &&
                        (!school || g.school?.id == school) &&
                        (!prog || g.program?.id == prog) &&
                        (!year || g.intake?.year_of_study == year) &&
                        (!groupId || g.id == groupId)
                    )
                }))
                .filter(t => t.groups.length > 0);

            return filtered.sort((a, b) => {
                if (!a.sessions?.length) return 1;
                if (!b.sessions?.length) return -1;
                const sa = a.sessions[0], sb = b.sessions[0];
                const dayDiff = getDayOrder(sa.day) - getDayOrder(sb.day);
                if (dayDiff !== 0) return dayDiff;
                return timeToMinutes(sa.start) - timeToMinutes(sb.start);
            });
        }

        function renderTable(data) {
            tableBody.innerHTML = '';
            data.forEach(t => {
                const sessions = t.sessions || [];
                const facility = t.facility?.name || '';
                const site = t.facility?.site?.name ? ` (${t.facility.site.name})` : '';

                const sessionMap = {};
                sessions.forEach(s => {
                    const key = `${s.day}-${s.start}-${s.end}`;
                    sessionMap[key] = sessionMap[key] || [];
                    sessionMap[key].push(s);
                });

                Object.entries(sessionMap).forEach(([key, sess], idx) => {
                    const [day, start, end] = key.split('-');
                    const groups = t.groups || [];
                    const rowspan = groups.length;

                    groups.forEach((g, i) => {
                        const isFirst = idx === 0 && i === 0;
                        tableBody.innerHTML += `<tr>
                            ${i === 0 ? `<td rowspan="${rowspan}">${day}</td><td rowspan="${rowspan}">${start}-${end}</td>` : ''}
                            <td>${t.course || 'N/A'}</td>
                            <td>${t.credits || 'N/A'}</td>
                            <td>${facility}${site}</td>
                            <td>${g.name}</td>
                            <td>${g.intake?.year_of_study ? 'Year ' + g.intake.year_of_study : ''}</td>
                            <td>${g.program?.name || ''}</td>
                            <td>${g.school?.name || ''}</td>
                            <td>${g.intake?.campus?.name || ''}</td>
                            <td>${g.college?.name || ''}</td>
                        </tr>`;
                    });
                });
            });
            updateCurrentView();
        }

        document.getElementById('resetFilters').onclick = () => {
            Object.values(filters).forEach(f => f.value = '');
            updateFilters();
            renderTable(filterData());
        };

        Object.values(filters).forEach(f => f.onchange = () => {
            updateFilters();
            renderTable(filterData());
        });

        document.getElementById('campus')?.addEventListener('change', () => {
            loadTimetable();
        });

        document.getElementById('exportBtn').onclick = () => {
            const wb = XLSX.utils.table_to_book(document.getElementById('timetableTable'), {sheet: "Timetable"});
            XLSX.writeFile(wb, "timetable.xlsx");
        };

        window.addEventListener('DOMContentLoaded', loadTimetable);
    </script>

    <!-- Back to Top -->
    <a href="#" class="back-to-top d-flex align-items-center justify-content-center">
        Back to Top
    </a>

    <!-- Vendor JS -->
    <script src="./assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="./assets/js/main.js"></script>
</body>
</html>