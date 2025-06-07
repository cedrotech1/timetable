<?php

include('connection.php');

// Get all campuses
$campuses = [];
$res = mysqli_query($connection, "SELECT id, name FROM campus ORDER BY name");
while ($row = mysqli_fetch_assoc($res)) $campuses[] = $row;

// Get academic years
$years = [];
$res = mysqli_query($connection, "SELECT id, year_label FROM academic_year ORDER BY year_label DESC");
while ($row = mysqli_fetch_assoc($res)) $years[] = $row;

$semesters = ['Semester 1', 'Semester 2'];
?>
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title>UR-TIMETABLE</title>
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

    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

  <?php
  include("./includes/header.php");
  include("./includes/menu.php");
  ?>



  <main id="main" class="main">
<div class="container-fluid py-4">
    <h2 class="mb-4">Time Table</h2>
    
    <!-- Timetable Grid -->
    <div class="timetable-container">
        <div class="table-responsive">
            <table class="table table-striped" id="timetableTable">
                <thead>
                    <tr>
                        <th>Day</th>
                        <th>Time</th>
                        <th>Module</th>
                        <th>Lecturer</th>
                        <th>Campus</th>
                        <th>College</th>
                        <th>School</th>
                        <th>Department</th>
                        <th>Program</th>
                        <th>Group</th>
                        <th>Intake</th>
                        <th>Facility</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="loading" id="loadingIndicator" style="display: none;">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
</div>

<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
function showLoading() {
    document.getElementById('loadingIndicator').style.display = 'flex';
}

function hideLoading() {
    document.getElementById('loadingIndicator').style.display = 'none';
}

function loadTimetable() {
    showLoading();
    fetch('get_timetable.php')
        .then(r => r.json())
        .then(result => {
            console.log('Received data:', result);
            const tbody = document.querySelector('#timetableTable tbody');
            tbody.innerHTML = '';
            
            if (result.success) {
                result.data.forEach(session => {
                    session.timetable.forEach(timetable => {
                        // Calculate total number of groups for this session
                        const totalGroups = timetable.groups.length;
                        
                        // Create rows for each group
                        timetable.groups.forEach((group, index) => {
                            const row = document.createElement('tr');
                            
                            // For the first group, include session info with rowspan
                            if (index === 0) {
                                row.innerHTML = `
                                    <td rowspan="${totalGroups}" class="session-info">${session.session.day}</td>
                                    <td rowspan="${totalGroups}" class="session-info">${session.session.start_time} - ${session.session.end_time}</td>
                                    <td rowspan="${totalGroups}" class="session-info">
                                        <strong>${timetable.module.code}</strong><br>
                                        ${timetable.module.name}
                                    </td>
                                    <td rowspan="${totalGroups}" class="session-info">${timetable.lecturer.name}</td>
                                    <td>${group.campus.name}</td>
                                    <td>${group.college.name}</td>
                                    <td>${group.school.name}</td>
                                    <td>${group.department.name}</td>
                                    <td>${group.program.name}</td>
                                    <td>${group.name}</td>
                                    <td>${group.intake.year}/${group.intake.month}</td>
                                    <td rowspan="${totalGroups}" class="session-info">
                                        <strong>${timetable.facility.name}</strong><br>
                                        <small class="text-muted">${timetable.facility.location}</small>
                                    </td>
                                `;
                            } else {
                                // For subsequent groups, only show group-specific info
                                row.innerHTML = `
                                    <td>${group.campus.name}</td>
                                    <td>${group.college.name}</td>
                                    <td>${group.school.name}</td>
                                    <td>${group.department.name}</td>
                                    <td>${group.program.name}</td>
                                    <td>${group.name}</td>
                                    <td>${group.intake.year}/${group.intake.month}</td>
                                `;
                            }
                            
                            tbody.appendChild(row);
                        });
                    });
                });
            }
            hideLoading();
        })
        .catch(error => {
            console.error('Error:', error);
            hideLoading();
        });
}

// Add some CSS for better display
const style = document.createElement('style');
style.textContent = `
    .table td {
        vertical-align: middle;
        white-space: nowrap;
    }
    .table thead th {
        background-color: #f8f9fa;
        position: sticky;
        top: 0;
        z-index: 1;
        white-space: nowrap;
    }
    .table-responsive {
        overflow-x: auto;
    }
`;
document.head.appendChild(style);

// Load timetable when page loads
document.addEventListener('DOMContentLoaded', loadTimetable);
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