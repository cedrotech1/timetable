<?php
session_start();
include('connection.php');

// Check login and group
if (!isset($_SESSION['group_id'])) {
    // die("No group assigned. Please log in again.");
    header("Location: index.php");
    exit();
}
$student_group_id = $_SESSION['group_id'];

// Get semester & academic year from system table
$system_data_setting = "SELECT * FROM system LIMIT 1";
$system_data_result = mysqli_query($connection, $system_data_setting);
$accademic_year_id = null;
$semester = null;

if ($system_data_result && mysqli_num_rows($system_data_result)) {
    $system_data = mysqli_fetch_assoc($system_data_result);
    $accademic_year_id = $system_data['accademic_year_id'];
    $semester = $system_data['semester'];
}

// Get academic year label
$academic_year_label = '';
if ($accademic_year_id) {
    $academic_years_query = "SELECT * FROM academic_year WHERE id = '$accademic_year_id' ORDER BY year_label DESC LIMIT 1";
    $academic_years_result_label = mysqli_query($connection, $academic_years_query);
    if ($academic_years_result_label && mysqli_num_rows($academic_years_result_label)) {
        $academic_year = mysqli_fetch_assoc($academic_years_result_label);
        $academic_year_label = $academic_year['year_label'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Timetable</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    background:rgb(255, 255, 255);
    padding: 20px;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}
.card {
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}
h2 {
    background: linear-gradient(90deg, rgb(3,31,80), rgb(0,98,204));
    color: white;
    padding: 12px 20px;
    border-radius: 12px;
    font-size: 1.4rem;
}
.table {
    border-radius: 10px;
    overflow: hidden;
}
.table-dark {
    background-color: rgb(3,31,80) !important;
}
.badge {
    font-size: 0.9rem;
}
.header-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 8px;
    margin-bottom: 15px;
}
.header-info div {
    background: #fff;
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 0.9rem;
    box-shadow: 0 1px 4px rgba(0,0,0,0.05);
}
</style>
</head>
<body>
<br>
<div class="container">
    <!-- logout btn   -->
 <a href="logout_student.php">   <button class="btn btn-danger mb-4">Logout</button></a>
    <div class="card p-3 mb-4">
        <div class="d-flex align-items-center mb-3">
            <img src="./assets/img/ur.png" alt="UR Logo" style="width: 4cm; height: 2cm; object-fit: contain; margin-right: 20px;">
            <h2 class="mb-0">📅 My Timetable</h2>
        </div>
        <div id="studentMeta" class="header-info">
            <!-- Dynamic academic info will be loaded here -->
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="text-secondary mb-0">Semester Timetable</h5>
        <button id="exportBtn" class="btn btn-success btn-sm">📤 Export to Excel</button>
    </div>

    <div class="table-responsive" style="background-color: #fff;padding: 10px;">
        <table class="table  table-bordered align-middle" id="timetableTable" style="background-color: #fff;">
            <thead class="text-center" style="background-color: rgb(3,31,80);color: #fff;">
                <tr>
                    <th style="background-color: rgb(3,31,80);color: #fff;">Day</th>
                    <th style="background-color: rgb(3,31,80);color: #fff;">Time</th>
                    <th style="background-color: rgb(3,31,80);color: #fff;">Module title</th>
                    <th style="background-color: rgb(3,31,80);color: #fff;">Module code</th>
                    <th style="background-color: rgb(3,31,80);color: #fff;">Credits</th>
                    <th style="background-color: rgb(3,31,80);color: #fff;">Facility</th>
                    <th style="background-color: rgb(3,31,80);color: #fff;">Group</th>
                    <th style="background-color: rgb(3,31,80);color: #fff;">Intake</th>
                    <th style="background-color: rgb(3,31,80);color: #fff;">Department</th>
                    <th style="background-color: rgb(3,31,80);color: #fff;">Program</th>
                    <th style="background-color: rgb(3,31,80);color: #fff;">Lecturers</th>
                    <th style="background-color: rgb(3,31,80);color: #fff;">Status</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
<script>
// Pass academic year & semester from PHP to JS
const academicYear = "<?php echo $academic_year_label; ?>";
const semester = "<?php echo $semester; ?>";

const tableBody = document.querySelector('#timetableTable tbody');
const exportBtn = document.getElementById('exportBtn');
const metaDiv = document.getElementById('studentMeta');

async function loadTimetable() {
    try {
        const res = await fetch('./Dashboard/get_timetable.php?group_id=<?php echo $student_group_id; ?>');
        const json = await res.json();
        let data = json.data || [];

        // Filter data to only include entries that have the student's group
        data = data.filter(entry => {
            return entry.groups?.some(group => group.id == <?php echo $student_group_id; ?>);
        });

        if (data.length > 0) {
            renderMeta(data[0]);
        }
        renderTable(data);
    } catch (err) {
        console.error('Failed to load timetable', err);
        tableBody.innerHTML = `
            <tr>
                <td colspan="12" class="text-center text-danger">Failed to load timetable data</td>
            </tr>
        `;
    }
}

// Helper function to safely get nested properties
function getProperty(obj, path, defaultValue = 'N/A') {
    return path.split('.').reduce((o, p) => (o && o[p] !== undefined ? o[p] : defaultValue), obj);
}

// Render student meta info dynamically
function renderMeta(firstItem) {
    // Get the student's group info (first group in the array)
    const group = firstItem.groups?.[0] || {};
    
    // Get intake month name if available
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
                       'July', 'August', 'September', 'October', 'November', 'December'];
    const intakeMonth = group.intake?.month ? monthNames[group.intake.month - 1] : '';
    const intakeYear = group.intake?.year || '';
    const intakeDisplay = intakeMonth && intakeYear ? `${intakeMonth} ${intakeYear}` : 'N/A';

    metaDiv.innerHTML = `
        <div><strong>Academic Year:</strong> ${academicYear}</div>
        <div><strong>Semester:</strong> ${semester}</div>
        <div><strong>Campus:</strong> ${getProperty(group, 'campus.name')}</div>
        <div><strong>College:</strong> ${getProperty(group, 'college.name')}</div>
        <div><strong>School:</strong> ${getProperty(group, 'school.name')}</div>
        <div><strong>Department:</strong> ${getProperty(group, 'department.name')}</div>
        <div><strong>Program:</strong> ${getProperty(group, 'program.name')}</div>
        <div><strong>Intake:</strong> ${intakeDisplay}</div>
        <div><strong>Group:</strong> ${group.name || 'N/A'}</div>
    `;
}

// Render timetable table
function renderTable(data) {
    tableBody.innerHTML = '';
    
    if (data.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="12" class="text-center">No timetable sessions found for your group</td>
            </tr>
        `;
        return;
    }

    data.forEach(t => {
        const sessionMap = {};
        t.sessions?.forEach(session => {
            const key = `${session.day}-${session.start}-${session.end}`;
            if (!sessionMap[key]) sessionMap[key] = [];
            sessionMap[key].push(session);
        });

        Object.keys(sessionMap).forEach(key => {
            const sessions = sessionMap[key];
            const day = sessions[0].day;
            const time = `${sessions[0].start} - ${sessions[0].end}`;
            
            // Process groups data
            const groups = t.groups.map(g => ({
                name: g.name || 'N/A',
                intake: g.intake ? `${g.intake.year}-${String(g.intake.month).padStart(2, '0')}` : 'N/A',
                department: g.department?.name || 'N/A',
                program: g.program?.name || 'N/A'
            }));
            
            const groupNames = groups.map(g => g.name).join(', ');
            const intakes = [...new Set(groups.map(g => g.intake))].join(', ');
            const departments = [...new Set(groups.map(g => g.department))].join(', ');
            const programs = [...new Set(groups.map(g => g.program))].join(', ');
            
            const lecturers = [
                t.leader_lecturer?.names,
                ...t.other_lecturers?.map(l => l.names) || []
            ].filter(Boolean).join(', ');
            
            const statusBadge = t.status === 'Approved' 
                ? `<span class="badge bg-success">Approved</span>` 
                : `<span class="badge bg-warning text-dark">Pending</span>`;
            
            const facilityName = t.facility?.name || 'N/A';
            const siteName = t.facility?.site?.name ? ` (${t.facility.site.name})` : '';

            tableBody.innerHTML += `
                <tr>
                    <td class="text-center">${day}</td>
                    <td class="text-center">${time}</td>
                    <td>${t.course || 'N/A'}</td>
                    <td>${t.code || 'N/A'}</td>
                    <td class="text-center">${t.credits || '0'}</td>
                    <td>${facilityName}${siteName}</td>
                    <td>${groupNames}</td>
                    <td>${intakes}</td>
                    <td>${departments}</td>
                    <td>${programs}</td>
                    <td>${lecturers || 'N/A'}</td>
                    <td class="text-center">${statusBadge}</td>
                </tr>
            `;
        });
    });
}

// Export table to Excel
exportBtn.addEventListener('click', () => {
    const table = document.getElementById('timetableTable');
    const wb = XLSX.utils.table_to_book(table, { sheet: "My Timetable" });
    XLSX.writeFile(wb, "my_timetable.xlsx");
});

window.addEventListener('DOMContentLoaded', loadTimetable);
</script>

</body>
</html>