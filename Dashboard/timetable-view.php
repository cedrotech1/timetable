<?php
// session_start();
include('connection.php');
$user_id = $_SESSION['id'];
$stmt = $connection->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Timetable with Filters</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background: #f4f6f8; padding: 20px; }
table { background: white; border-radius: 10px; overflow: hidden; }
th, td { vertical-align: middle; }
h2{
    background-color: rgb(3,31,80) !important; 
    color: white !important;
    padding: 10px;
    font-weight: bold;
    font-size: 20px;
    border-radius: 10px;
}
.table-dark{
    background-color: rgb(3,31,80) !important; 
    color: white !important;
}
</style>
</head>
<body>

<div class="container">
    <h2 class="mb-4">📅 Timetable</h2>

    <!-- Filters -->
    <div class="mb-3 row g-2">
        <div class="col-md-3">
            <label class="form-label">Department</label>
            <select id="departmentFilter" class="form-select">
                <option value="">All</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Program</label>
            <select id="programFilter" class="form-select" disabled>
                <option value="">All</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Intake</label>
            <select id="intakeFilter" class="form-select" disabled>
                <option value="">All</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Group</label>
            <select id="groupFilter" class="form-select" disabled>
                <option value="">All</option>
            </select>
        </div>
    </div>

    <!-- Export button -->
    <div class="mb-3">
        <button id="exportBtn" class="btn btn-success">Export to Excel</button>
    </div>

    <!-- Table -->
    <div class="table-responsive">
        <table class="table table-striped table-bordered" id="timetableTable">
            <thead class="table-dark">
                <tr>
                    <th>Day</th>
                    <th>Time</th>
                    <th>Course</th>
                    <th>Code</th>
                    <th>Credits</th>
                    <th>Facility</th>
                    <th>Groups</th>
                    <th>Intake</th>
                    <th>Department</th>
                    <th>Program</th>
                    <th>Lecturers</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<!-- SheetJS CDN for Excel export -->
<script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>

<script>
let apiData = [];
const deptSelect = document.getElementById('departmentFilter');
const progSelect = document.getElementById('programFilter');
const intakeSelect = document.getElementById('intakeFilter');
const groupSelect = document.getElementById('groupFilter');
const tableBody = document.querySelector('#timetableTable tbody');
const exportBtn = document.getElementById('exportBtn');

// Load timetable data from API
async function loadTimetable() {
    try {
        const res = await fetch('./get_timetable.php');
        const json = await res.json();
        apiData = json.data || [];

        buildDepartmentOptions();
        filterAndRender();
    } catch (err) {
        console.error('Failed to load timetable', err);
    }
}

// Build department filter options
function buildDepartmentOptions() {
    const departments = new Set();
    apiData.forEach(t => t.groups?.forEach(g => g.department && departments.add(g.department)));
    deptSelect.innerHTML = '<option value="">All</option>';
    [...departments].sort().forEach(d => deptSelect.innerHTML += `<option value="${d}">${d}</option>`);
}

// Event listeners for filters
deptSelect.addEventListener('change', () => {
    const selectedDept = deptSelect.value;
    progSelect.disabled = !selectedDept;
    intakeSelect.disabled = true;
    groupSelect.disabled = true;

    progSelect.innerHTML = '<option value="">All</option>';
    if (selectedDept) {
        const programs = new Set();
        apiData.forEach(t => t.groups?.forEach(g => {
            if (g.department === selectedDept) programs.add(g.program);
        }));
        [...programs].sort().forEach(p => progSelect.innerHTML += `<option value="${p}">${p}</option>`);
    }
    filterAndRender();
});

progSelect.addEventListener('change', () => {
    const selectedDept = deptSelect.value;
    const selectedProg = progSelect.value;
    intakeSelect.disabled = !selectedProg;
    groupSelect.disabled = true;

    intakeSelect.innerHTML = '<option value="">All</option>';
    if (selectedProg) {
        const intakes = new Set();
        apiData.forEach(t => t.groups?.forEach(g => {
            if (g.department === selectedDept && g.program === selectedProg) intakes.add(g.intake);
        }));
        [...intakes].sort().forEach(i => intakeSelect.innerHTML += `<option value="${i}">${i}</option>`);
    }
    filterAndRender();
});

intakeSelect.addEventListener('change', () => {
    const selectedDept = deptSelect.value;
    const selectedProg = progSelect.value;
    const selectedIntake = intakeSelect.value;
    groupSelect.disabled = !selectedIntake;

    groupSelect.innerHTML = '<option value="">All</option>';
    if (selectedIntake) {
        const groups = new Set();
        apiData.forEach(t => t.groups?.forEach(g => {
            if (g.department === selectedDept && g.program === selectedProg && g.intake === selectedIntake) groups.add(g.name);
        }));
        [...groups].sort().forEach(g => groupSelect.innerHTML += `<option value="${g}">${g}</option>`);
    }
    filterAndRender();
});

groupSelect.addEventListener('change', filterAndRender);

// Filter data
function filterAndRender() {
    const dept = deptSelect.value;
    const prog = progSelect.value;
    const intake = intakeSelect.value;
    const group = groupSelect.value;

    const filtered = apiData.filter(t =>
        t.groups?.some(g =>
            (!dept || g.department === dept) &&
            (!prog || g.program === prog) &&
            (!intake || g.intake === intake) &&
            (!group || g.name === group)
        )
    );

    renderTable(filtered);
}

// Render timetable table
function renderTable(data) {
    tableBody.innerHTML = '';

    data.forEach(t => {
        const sessionMap = {};

        // Merge duplicate sessions
        t.sessions?.forEach(session => {
            const key = `${session.day}-${session.start}-${session.end}`;
            if (!sessionMap[key]) sessionMap[key] = [];
            sessionMap[key].push(session);
        });

        Object.keys(sessionMap).forEach(key => {
            const sessions = sessionMap[key];
            const day = sessions[0].day;
            const time = `${sessions[0].start} - ${sessions[0].end}`;

            const groups = t.groups
                .filter(g =>
                    (!deptSelect.value || g.department === deptSelect.value) &&
                    (!progSelect.value || g.program === progSelect.value) &&
                    (!intakeSelect.value || g.intake === intakeSelect.value) &&
                    (!groupSelect.value || g.name === groupSelect.value)
                )
                .map(g => g.name).join(', ');

            const intake = t.groups.map(g => g.intake).filter((v,i,a)=>a.indexOf(v)===i).join(', ');
            const department = t.groups.map(g => g.department).filter((v,i,a)=>a.indexOf(v)===i).join(', ');
            const program = t.groups.map(g => g.program).filter((v,i,a)=>a.indexOf(v)===i).join(', ');

            const lecturers = [
                t.leader_lecturer?.names,
                ...t.other_lecturers?.map(l => l.names) || []
            ].filter(Boolean).join(', ');

            const status = t.status || 'pending';

            tableBody.innerHTML += `
                <tr>
                    <td>${day}</td>
                    <td>${time}</td>
                    <td>${t.course}</td>
                    <td>${t.code}</td>
                    <td>${t.credits}</td>
                    <td>${t.facility}</td>
                    <td>${groups}</td>
                    <td>${intake}</td>
                    <td>${department}</td>
                    <td>${program}</td>
                    <td>${lecturers}</td>
                    <td>${status}</td>
                    <td>
                        <a href="timetable_details.php?id=${t.id}" class="btn btn-primary btn-sm">View</a>
                    </td>
                </tr>
            `;
        });
    });
}

// Export table to Excel
exportBtn.addEventListener('click', () => {
    const table = document.getElementById('timetableTable');
    const wb = XLSX.utils.table_to_book(table, { sheet: "Timetable" });
    XLSX.writeFile(wb, "timetable.xlsx");
});

window.addEventListener('DOMContentLoaded', loadTimetable);
</script>

</body>
</html>
