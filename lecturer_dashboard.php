<?php
session_start();
include('connection.php');

// Check login and group
if (!isset($_SESSION['group_id'])) {
    die("No group assigned. Please log in again.");
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
    background: #f5f7fa;
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

    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle" id="timetableTable">
            <thead class="table-dark text-center">
                <tr>
                    <th>Day</th>
                    <th>Time</th>
                    <th>Course</th>
                    <th>Code</th>
                    <th>Credits</th>
                    <th>Facility</th>
                    <th>Group</th>
                    <th>Intake</th>
                    <th>Department</th>
                    <th>Program</th>
                    <th>Lecturers</th>
                  
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
        const data = json.data || [];

        if (data.length > 0) {
            renderMeta(data[0]);
        }
        renderTable(data);
    } catch (err) {
        console.error('Failed to load timetable', err);
    }
}

// Render student meta info dynamically (academicYear & semester from PHP)
function renderMeta(firstItem) {
    const group = firstItem.groups?.[0] || {};
    const campus = group.campus || "N/A";
    const college = group.college || "N/A";
    const school = group.school || "N/A";
    const department = group.department || "N/A";
    const program = group.program || "N/A";
    const intake = group.intake || "N/A";
    const groupName = group.name || "N/A";

    metaDiv.innerHTML = `
        <div><strong>Academic Year:</strong> ${academicYear}</div>
        <div><strong>Semester:</strong> ${semester}</div>
        <div><strong>Campus:</strong> ${campus}</div>
        <div><strong>College:</strong> ${college}</div>
        <div><strong>School:</strong> ${school}</div>
        <div><strong>Department:</strong> ${department}</div>
        <div><strong>Program:</strong> ${program}</div>
        <div><strong>Intake:</strong> ${intake}</div>
        <div><strong>Group:</strong> ${groupName}</div>
    `;
}

// Render timetable table
function renderTable(data) {
    tableBody.innerHTML = '';
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
            const groups = t.groups.map(g => g.name).join(', ');
            const intake = t.groups.map(g => g.intake).filter((v,i,a)=>a.indexOf(v)===i).join(', ');
            const department = t.groups.map(g => g.department).filter((v,i,a)=>a.indexOf(v)===i).join(', ');
            const program = t.groups.map(g => g.program).filter((v,i,a)=>a.indexOf(v)===i).join(', ');
            const lecturers = [
                t.leader_lecturer?.names,
                ...t.other_lecturers?.map(l => l.names) || []
            ].filter(Boolean).join(', ');
            const statusBadge = t.status === 'approved' 
                ? `<span class="badge bg-success">Approved</span>` 
                : `<span class="badge bg-warning text-dark">Pending</span>`;

            tableBody.innerHTML += `
                <tr>
                    <td class="text-center">${day}</td>
                    <td class="text-center">${time}</td>
                    <td>${t.course}</td>
                    <td>${t.code}</td>
                    <td class="text-center">${t.credits}</td>
                    <td>${t.facility}</td>
                    <td>${groups}</td>
                    <td>${intake}</td>
                    <td>${department}</td>
                    <td>${program}</td>
                    <td>${lecturers}</td>
                   
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
