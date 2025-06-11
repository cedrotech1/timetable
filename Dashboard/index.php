<?php
session_start();
include("connection.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    <title>UR-TIMETABLE</title>
    <link href="assets/img/icon1.png" rel="icon">
    <link href="assets/img/icon1.png" rel="apple-touch-icon">
    
    <!-- Include your existing CSS files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <!-- Add Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .org-structure-card {
            margin-bottom: 20px;
        }
        .org-structure-card .card-header {
            background-color: #f8f9fa;
            padding: 15px;
        }
        .org-structure-card .card-body {
            padding: 20px;
        }
        .stats-box {
            background: #fff;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stats-box h6 {
            color: #6c757d;
            margin-bottom: 10px;
        }
        .stats-box .number {
            font-size: 24px;
            font-weight: bold;
            color: #012970;
        }
        .hierarchy-item {
            padding: 10px;
            margin: 5px 0;
            border-left: 3px solid #012970;
            background: #f8f9fa;
        }
        .hierarchy-item:hover {
            background: #e9ecef;
        }
        .hierarchy-item .badge {
            float: right;
        }
        #tree-container {
            width: 100%;
            height: 800px;
            overflow: auto;
            background: #fff;
        }
        .node circle {
            fill: #fff;
            stroke: steelblue;
            stroke-width: 2px;
        }
        .node text {
            font: 12px sans-serif;
        }
        .node--internal circle {
            fill: #fff;
        }
        .node--leaf circle {
            fill: #fff;
        }
        .link {
            fill: none;
            stroke: #ccc;
            stroke-width: 1.5px;
        }
        .node {
            cursor: pointer;
        }
        .node:hover circle {
            stroke: #ff7f0e;
            stroke-width: 3px;
        }
        .node--active circle {
            stroke: #ff7f0e;
            stroke-width: 3px;
        }
        .tooltip {
            position: absolute;
            padding: 10px;
            background: rgba(0, 0, 0, 0.8);
            color: #fff;
            border-radius: 4px;
            font-size: 12px;
            pointer-events: none;
        }
        .chart-container {
            position: relative;
            margin: auto;
            height: 800px;
            width: 100%;
        }
        .nav-tabs .nav-link {
            color: #012970;
            font-weight: 500;
            padding: 12px 20px;
        }
        .nav-tabs .nav-link.active {
            color: #4154f1;
            border-color: #dee2e6 #dee2e6 #fff;
            font-weight: 600;
        }
        .tab-content {
            padding: 20px 0;
        }
    </style>
</head>

<body>
    <?php
    include("./includes/header.php");
    include("./includes/menu.php");
    ?>

    <main id="main" class="main">
        <div class="pagetitle">
            <h1>Dashboard Insights</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item active">Insights</li>
                </ol>
            </nav>
        </div>

        <section class="section dashboard">
            <!-- Tabs -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <ul class="nav nav-tabs" id="insightsTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="organization-tab" data-bs-toggle="tab" data-bs-target="#organization" type="button" role="tab" aria-controls="organization" aria-selected="true">
                                        <i class="bi bi-building"></i> Organization Structure
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="facilities-tab" data-bs-toggle="tab" data-bs-target="#facilities" type="button" role="tab" aria-controls="facilities" aria-selected="false">
                                        <i class="bi bi-house-door"></i> Facilities
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="timetable-tab" data-bs-toggle="tab" data-bs-target="#timetable" type="button" role="tab" aria-controls="timetable" aria-selected="false">
                                        <i class="bi bi-calendar3"></i> Timetable
                                    </button>
                                </li>
                            </ul>

                            <div class="tab-content" id="insightsTabsContent">
                                <!-- Organization Structure Tab -->
                                <div class="tab-pane fade show active" id="organization" role="tabpanel" aria-labelledby="organization-tab">
                                    <!-- Overview Statistics -->
                                    <div class="row">
                                        <div class="col-lg-3 col-md-6">
                                            <div class="stats-box">
                                                <h6>Total Campuses</h6>
                                                <div class="number" id="totalCampuses">-</div>
                                            </div>
                                        </div>
                                        <div class="col-lg-3 col-md-6">
                                            <div class="stats-box">
                                                <h6>Total Colleges</h6>
                                                <div class="number" id="totalColleges">-</div>
                                            </div>
                                        </div>
                                        <div class="col-lg-3 col-md-6">
                                            <div class="stats-box">
                                                <h6>Total Schools</h6>
                                                <div class="number" id="totalSchools">-</div>
                                            </div>
                                        </div>
                                        <div class="col-lg-3 col-md-6">
                                            <div class="stats-box">
                                                <h6>Total Programs</h6>
                                                <div class="number" id="totalPrograms">-</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Organization Structure Charts -->
                                    <div class="row mt-4">
                                        <!-- Main Structure Chart -->
                                        <div class="col-lg-8">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h5 class="card-title">Complete Organization Structure by Campus</h5>
                                                    <div class="chart-container">
                                                        <canvas id="organizationChart"></canvas>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Program Distribution Chart -->
                                        <div class="col-lg-4">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h5 class="card-title">Program Distribution</h5>
                                                    <div class="chart-container" style="height: 400px;">
                                                        <canvas id="programDistributionChart"></canvas>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Additional Organization Charts -->
                                    <div class="row mt-4">
                                        <!-- College Distribution -->
                                        <div class="col-lg-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h5 class="card-title">College Distribution by Campus</h5>
                                                    <div class="chart-container" style="height: 400px;">
                                                        <canvas id="collegeDistributionChart"></canvas>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- School Distribution -->
                                        <div class="col-lg-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h5 class="card-title">School Distribution by College</h5>
                                                    <div class="chart-container" style="height: 400px;">
                                                        <canvas id="schoolDistributionChart"></canvas>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Facilities Tab -->
                                <div class="tab-pane fade" id="facilities" role="tabpanel" aria-labelledby="facilities-tab">
                                    <!-- Facilities Statistics -->
                                    <div class="row">
                                        <div class="col-lg-3 col-md-6">
                                            <div class="stats-box">
                                                <h6>Total Facilities</h6>
                                                <div class="number" id="totalFacilities">-</div>
                                            </div>
                                        </div>
                                        <div class="col-lg-3 col-md-6">
                                            <div class="stats-box">
                                                <h6>Total Rooms</h6>
                                                <div class="number" id="totalRooms">-</div>
                                            </div>
                                        </div>
                                        <div class="col-lg-3 col-md-6">
                                            <div class="stats-box">
                                                <h6>Total Capacity</h6>
                                                <div class="number" id="totalCapacity">-</div>
                                            </div>
                                        </div>
                                        <div class="col-lg-3 col-md-6">
                                            <div class="stats-box">
                                                <h6>Utilization Rate</h6>
                                                <div class="number" id="utilizationRate">-</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Facilities Charts -->
                                    <div class="row mt-4">
                                        <!-- Facility Type Distribution -->
                                        <div class="col-lg-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h5 class="card-title">Facility Type Distribution</h5>
                                                    <div class="chart-container" style="height: 400px;">
                                                        <canvas id="facilityTypeChart"></canvas>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Capacity Distribution -->
                                        <div class="col-lg-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h5 class="card-title">Capacity Distribution by Type</h5>
                                                    <div class="chart-container" style="height: 400px;">
                                                        <canvas id="capacityDistributionChart"></canvas>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Facility Usage Analysis -->
                                    <div class="row mt-4">
                                        <!-- Usage by Day -->
                                        <div class="col-lg-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h5 class="card-title">Facility Usage by Day</h5>
                                                    <div class="chart-container" style="height: 400px;">
                                                        <canvas id="facilityUsageByDayChart"></canvas>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Usage by Time Slot -->
                                        <div class="col-lg-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h5 class="card-title">Facility Usage by Time Slot</h5>
                                                    <div class="chart-container" style="height: 400px;">
                                                        <canvas id="facilityUsageByTimeChart"></canvas>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Semester and Academic Year Analysis -->
                                    <div class="row mt-4">
                                        <!-- Usage by Semester -->
                                        <div class="col-lg-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h5 class="card-title">Facility Usage by Semester</h5>
                                                    <div class="chart-container" style="height: 400px;">
                                                        <canvas id="facilityUsageBySemesterChart"></canvas>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Usage by Academic Year -->
                                        <div class="col-lg-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h5 class="card-title">Facility Usage by Academic Year</h5>
                                                    <div class="chart-container" style="height: 400px;">
                                                        <canvas id="facilityUsageByYearChart"></canvas>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Timetable Tab -->
                                <div class="tab-pane fade" id="timetable" role="tabpanel" aria-labelledby="timetable-tab">
                                    <!-- Timetable Statistics -->
                                    <div class="row">
                                        <div class="col-lg-3 col-md-6">
                                            <div class="stats-box">
                                                <h6>Total Sessions</h6>
                                                <div class="number" id="totalSessions">-</div>
                                            </div>
                                        </div>
                                        <div class="col-lg-3 col-md-6">
                                            <div class="stats-box">
                                                <h6>Total Hours</h6>
                                                <div class="number" id="totalHours">-</div>
                                            </div>
                                        </div>
                                        <div class="col-lg-3 col-md-6">
                                            <div class="stats-box">
                                                <h6>Average Daily Load</h6>
                                                <div class="number" id="avgDailyLoad">-</div>
                                            </div>
                                        </div>
                                        <div class="col-lg-3 col-md-6">
                                            <div class="stats-box">
                                                <h6>Conflict Rate</h6>
                                                <div class="number" id="conflictRate">-</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Timetable Charts -->
                                    <div class="row mt-4">
                                        <div class="col-lg-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h5 class="card-title">Session Distribution by Day</h5>
                                                    <div class="chart-container" style="height: 400px;">
                                                        <canvas id="sessionDistributionChart"></canvas>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h5 class="card-title">Time Slot Utilization</h5>
                                                    <div class="chart-container" style="height: 400px;">
                                                        <canvas id="timeSlotChart"></canvas>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Include your existing JS files -->
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>

    <script>
        async function fetchOrganizationData() {
            try {
                const response = await fetch('get_organization_structure.php');
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const data = await response.json();
                if (!data.success) {
                    throw new Error(data.error || 'Failed to fetch organization data');
                }
                return data;
            } catch (error) {
                console.error('Error fetching organization data:', error);
                throw error;
            }
        }

        async function fetchFacilityData() {
            try {
                const response = await fetch('api_facility.php');
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const data = await response.json();
                if (!data.success) {
                    throw new Error(data.error || 'Failed to fetch facility data');
                }
                return data;
            } catch (error) {
                console.error('Error fetching facility data:', error);
                throw error;
            }
        }

        async function fetchAcademicYears() {
            try {
                const response = await fetch('get_academic_years.php', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('Server did not return JSON');
                }

                const data = await response.json();
                if (!data.success) {
                    throw new Error(data.error || 'Failed to fetch academic years');
                }

                if (!Array.isArray(data.data)) {
                    throw new Error('Invalid data format received');
                }

                return data.data;
            } catch (error) {
                console.error('Error fetching academic years:', error);
                // Return empty array as fallback
                return [];
            }
        }

        async function fetchTimetableData() {
            try {
                const response = await fetch('get_timetable.php');
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const data = await response.json();
                if (!data.success) {
                    throw new Error(data.error || 'Failed to fetch timetable data');
                }
                return data;
            } catch (error) {
                console.error('Error fetching timetable data:', error);
                throw error;
            }
        }

        function updateOrganizationStats(data) {
            let totalColleges = 0;
            let totalSchools = 0;
            let totalDepartments = 0;
            let totalPrograms = 0;
            let totalIntakes = 0;
            let totalGroups = 0;

            data.data.forEach(campus => {
                totalColleges += campus.colleges.length;
                campus.colleges.forEach(college => {
                    totalSchools += college.schools.length;
                    college.schools.forEach(school => {
                        totalDepartments += school.departments.length;
                        school.departments.forEach(dept => {
                            totalPrograms += dept.programs.length;
                            dept.programs.forEach(program => {
                                totalIntakes += program.intakes.length;
                                program.intakes.forEach(intake => {
                                    totalGroups += intake.groups.length;
                                });
                            });
                        });
                    });
                });
            });

            document.getElementById('totalCampuses').textContent = data.data.length;
            document.getElementById('totalColleges').textContent = totalColleges;
            document.getElementById('totalSchools').textContent = totalSchools;
            document.getElementById('totalPrograms').textContent = totalPrograms;
        }

        function updateFacilityStats(data) {
            // Update facility statistics
            document.getElementById('totalFacilities').textContent = data.data.length;
            
            // Calculate total capacity
            const totalCapacity = data.data.reduce((sum, facility) => sum + parseInt(facility.capacity), 0);
            document.getElementById('totalCapacity').textContent = totalCapacity;
            
            // Calculate total rooms (same as facilities in this case)
            document.getElementById('totalRooms').textContent = data.data.length;
            
            // Calculate utilization rate
            const totalSlots = data.data.reduce((sum, facility) => sum + facility.taken_slots.length, 0);
            const maxPossibleSlots = data.data.length * 5 * 8; // Assuming 5 days and 8 time slots per day
            const utilizationRate = ((totalSlots / maxPossibleSlots) * 100).toFixed(1);
            document.getElementById('utilizationRate').textContent = utilizationRate + '%';
        }

        function updateTimetableStats(data) {
            try {
                // Initialize with default values
                let totalSessions = 0;
                let totalHours = 0;
                let avgDailyLoad = 0;
                let conflictRate = 0;

                if (data && data.data) {
                    // Calculate total sessions
                    totalSessions = data.data.length || 0;

                    // Calculate total hours
                    data.data.forEach(session => {
                        if (session.start_time && session.end_time) {
                            const start = new Date(`2000-01-01 ${session.start_time}`);
                            const end = new Date(`2000-01-01 ${session.end_time}`);
                            const hours = (end - start) / (1000 * 60 * 60);
                            totalHours += hours;
                        }
                    });

                    // Calculate average daily load
                    const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
                    const sessionsPerDay = {};
                    days.forEach(day => sessionsPerDay[day] = 0);

                    data.data.forEach(session => {
                        if (session.day && sessionsPerDay[session.day] !== undefined) {
                            sessionsPerDay[session.day]++;
                        }
                    });

                    avgDailyLoad = Object.values(sessionsPerDay).reduce((a, b) => a + b, 0) / days.length;

                    // Calculate conflict rate (simplified version)
                    const timeSlots = {};
                    data.data.forEach(session => {
                        const key = `${session.day}-${session.start_time}-${session.end_time}`;
                        timeSlots[key] = (timeSlots[key] || 0) + 1;
                    });

                    const conflicts = Object.values(timeSlots).filter(count => count > 1).length;
                    conflictRate = totalSessions > 0 ? (conflicts / totalSessions * 100).toFixed(1) : 0;
                }

                // Update the UI
                document.getElementById('totalSessions').textContent = totalSessions;
                document.getElementById('totalHours').textContent = totalHours.toFixed(1);
                document.getElementById('avgDailyLoad').textContent = avgDailyLoad.toFixed(1);
                document.getElementById('conflictRate').textContent = conflictRate + '%';

            } catch (error) {
                console.error('Error updating timetable stats:', error);
                // Set default values on error
                document.getElementById('totalSessions').textContent = '0';
                document.getElementById('totalHours').textContent = '0';
                document.getElementById('avgDailyLoad').textContent = '0';
                document.getElementById('conflictRate').textContent = '0%';
            }
        }

        // Store chart instances
        let organizationChart = null;
        let facilityTypeChart = null;
        let capacityDistributionChart = null;
        let facilityUsageByDayChart = null;
        let facilityUsageByTimeChart = null;
        let facilityUsageBySemesterChart = null;
        let facilityUsageByYearChart = null;
        let sessionDistributionChart = null;
        let timeSlotChart = null;

        function createOrganizationChart(data) {
            const ctx = document.getElementById('organizationChart').getContext('2d');
            
            // Destroy existing chart if it exists
            if (organizationChart) {
                organizationChart.destroy();
            }

            // Process data to count all levels
            const campusData = data.data.map(campus => {
                let totalColleges = campus.colleges.length;
                let totalSchools = 0;
                let totalDepartments = 0;
                let totalPrograms = 0;
                let totalIntakes = 0;
                let totalGroups = 0;

                campus.colleges.forEach(college => {
                    totalSchools += college.schools.length;
                    college.schools.forEach(school => {
                        totalDepartments += school.departments.length;
                        school.departments.forEach(dept => {
                            totalPrograms += dept.programs.length;
                            dept.programs.forEach(program => {
                                totalIntakes += program.intakes.length;
                                program.intakes.forEach(intake => {
                                    totalGroups += intake.groups.length;
                                });
                            });
                        });
                    });
                });

                return {
                    name: campus.name,
                    colleges: totalColleges,
                    schools: totalSchools,
                    departments: totalDepartments,
                    programs: totalPrograms,
                    intakes: totalIntakes,
                    groups: totalGroups
                };
            });

            organizationChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: campusData.map(campus => campus.name),
                    datasets: [
                        {
                            label: 'Colleges',
                            data: campusData.map(campus => campus.colleges),
                            backgroundColor: 'rgba(54, 162, 235, 0.7)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Schools',
                            data: campusData.map(campus => campus.schools),
                            backgroundColor: 'rgba(255, 99, 132, 0.7)',
                            borderColor: 'rgba(255, 99, 132, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Departments',
                            data: campusData.map(campus => campus.departments),
                            backgroundColor: 'rgba(75, 192, 192, 0.7)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Programs',
                            data: campusData.map(campus => campus.programs),
                            backgroundColor: 'rgba(255, 206, 86, 0.7)',
                            borderColor: 'rgba(255, 206, 86, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Intakes',
                            data: campusData.map(campus => campus.intakes),
                            backgroundColor: 'rgba(153, 102, 255, 0.7)',
                            borderColor: 'rgba(153, 102, 255, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Groups',
                            data: campusData.map(campus => campus.groups),
                            backgroundColor: 'rgba(255, 159, 64, 0.7)',
                            borderColor: 'rgba(255, 159, 64, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Complete Organization Structure by Campus',
                            font: {
                                size: 16
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    label += context.parsed.y;
                                    return label;
                                }
                            }
                        },
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 12
                            }
                        }
                    },
                    scales: {
                        x: {
                            stacked: false,
                            title: {
                                display: true,
                                text: 'Campus'
                            }
                        },
                        y: {
                            stacked: false,
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Count'
                            },
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        function createFacilityCharts(data, academicYears) {
            // If no academic years data, show message
            if (!academicYears || academicYears.length === 0) {
                const chartContainer = document.getElementById('facilityUsageBySemesterChart').parentElement;
                chartContainer.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        No academic year data available. Please check your database configuration.
                    </div>
                `;
                return;
            }

            // Process facility data
            const facilityTypes = {};
            const capacityByType = {};
            const usageByDay = {
                'Monday': 0, 'Tuesday': 0, 'Wednesday': 0, 'Thursday': 0, 'Friday': 0
            };
            const usageBySemesterAndYear = {};
            const timeSlots = {};

            // Initialize usage by semester and year
            academicYears.forEach(year => {
                usageBySemesterAndYear[year.id] = {
                    '1': 0, // Semester 1
                    '2': 0  // Semester 2
                };
            });

            data.data.forEach(facility => {
                // Count facility types
                const type = facility.type.toLowerCase();
                facilityTypes[type] = (facilityTypes[type] || 0) + 1;
                
                // Sum capacity by type
                capacityByType[type] = (capacityByType[type] || 0) + parseInt(facility.capacity);

                // Process taken slots
                facility.taken_slots.forEach(slot => {
                    // Count usage by day
                    usageByDay[slot.day]++;

                    // Count usage by semester and year
                    if (usageBySemesterAndYear[slot.academic_year_id]) {
                        usageBySemesterAndYear[slot.academic_year_id][slot.semester]++;
                    }

                    // Count usage by time slot
                    const timeKey = `${slot.start_time}-${slot.end_time}`;
                    timeSlots[timeKey] = (timeSlots[timeKey] || 0) + 1;
                });
            });

            // Create Facility Type Distribution Chart
            const typeCtx = document.getElementById('facilityTypeChart').getContext('2d');
            if (facilityTypeChart) {
                facilityTypeChart.destroy();
            }
            facilityTypeChart = new Chart(typeCtx, {
                type: 'pie',
                data: {
                    labels: Object.keys(facilityTypes),
                    datasets: [{
                        data: Object.values(facilityTypes),
                        backgroundColor: [
                            'rgba(54, 162, 235, 0.7)',
                            'rgba(255, 99, 132, 0.7)',
                            'rgba(75, 192, 192, 0.7)',
                            'rgba(255, 206, 86, 0.7)',
                            'rgba(153, 102, 255, 0.7)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Facility Type Distribution'
                        }
                    }
                }
            });

            // Create Capacity Distribution Chart
            const capacityCtx = document.getElementById('capacityDistributionChart').getContext('2d');
            if (capacityDistributionChart) {
                capacityDistributionChart.destroy();
            }
            capacityDistributionChart = new Chart(capacityCtx, {
                type: 'bar',
                data: {
                    labels: Object.keys(capacityByType),
                    datasets: [{
                        label: 'Total Capacity',
                        data: Object.values(capacityByType),
                        backgroundColor: 'rgba(75, 192, 192, 0.7)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Capacity Distribution by Type'
                        }
                    }
                }
            });

            // Create Usage by Day Chart
            const dayCtx = document.getElementById('facilityUsageByDayChart').getContext('2d');
            if (facilityUsageByDayChart) {
                facilityUsageByDayChart.destroy();
            }
            facilityUsageByDayChart = new Chart(dayCtx, {
                type: 'bar',
                data: {
                    labels: Object.keys(usageByDay),
                    datasets: [{
                        label: 'Number of Slots',
                        data: Object.values(usageByDay),
                        backgroundColor: 'rgba(54, 162, 235, 0.7)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Facility Usage by Day'
                        }
                    }
                }
            });

            // Create Usage by Time Slot Chart
            const timeCtx = document.getElementById('facilityUsageByTimeChart').getContext('2d');
            if (facilityUsageByTimeChart) {
                facilityUsageByTimeChart.destroy();
            }
            facilityUsageByTimeChart = new Chart(timeCtx, {
                type: 'line',
                data: {
                    labels: Object.keys(timeSlots),
                    datasets: [{
                        label: 'Number of Slots',
                        data: Object.values(timeSlots),
                        borderColor: 'rgba(255, 99, 132, 0.7)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Facility Usage by Time Slot'
                        }
                    }
                }
            });

            // Create Combined Semester and Academic Year Chart
            const semesterYearCtx = document.getElementById('facilityUsageBySemesterChart').getContext('2d');
            if (facilityUsageBySemesterChart) {
                facilityUsageBySemesterChart.destroy();
            }

            // Prepare data for the combined chart
            const labels = [];
            const semester1Data = [];
            const semester2Data = [];

            academicYears.forEach(year => {
                labels.push(year.year_label);
                semester1Data.push(usageBySemesterAndYear[year.id]['1']);
                semester2Data.push(usageBySemesterAndYear[year.id]['2']);
            });

            facilityUsageBySemesterChart = new Chart(semesterYearCtx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Semester 1',
                            data: semester1Data,
                            backgroundColor: 'rgba(54, 162, 235, 0.7)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Semester 2',
                            data: semester2Data,
                            backgroundColor: 'rgba(255, 99, 132, 0.7)',
                            borderColor: 'rgba(255, 99, 132, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Facility Usage by Semester and Academic Year'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    label += context.parsed.y + ' slots';
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Academic Year'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Slots'
                            },
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        function createTimetableCharts(data) {
            try {
                if (!data || !data.data) {
                    console.warn('No timetable data available');
                    return;
                }

                // Process data for session distribution by day
                const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
                const sessionsByDay = {};
                days.forEach(day => sessionsByDay[day] = 0);

                // Process data for time slot utilization
                const timeSlots = {};
                const timeSlotOrder = [
                    '08:00-09:00', '09:00-10:00', '10:00-11:00', '11:00-12:00',
                    '12:00-13:00', '13:00-14:00', '14:00-15:00', '15:00-16:00'
                ];

                data.data.forEach(session => {
                    // Count sessions by day
                    if (session.day && sessionsByDay[session.day] !== undefined) {
                        sessionsByDay[session.day]++;
                    }

                    // Count sessions by time slot
                    if (session.start_time && session.end_time) {
                        const timeKey = `${session.start_time}-${session.end_time}`;
                        timeSlots[timeKey] = (timeSlots[timeKey] || 0) + 1;
                    }
                });

                // Create Session Distribution Chart
                const sessionCtx = document.getElementById('sessionDistributionChart').getContext('2d');
                if (sessionDistributionChart) {
                    sessionDistributionChart.destroy();
                }
                sessionDistributionChart = new Chart(sessionCtx, {
                    type: 'bar',
                    data: {
                        labels: days,
                        datasets: [{
                            label: 'Number of Sessions',
                            data: days.map(day => sessionsByDay[day]),
                            backgroundColor: 'rgba(54, 162, 235, 0.7)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Session Distribution by Day'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });

                // Create Time Slot Utilization Chart
                const timeCtx = document.getElementById('timeSlotChart').getContext('2d');
                if (timeSlotChart) {
                    timeSlotChart.destroy();
                }
                timeSlotChart = new Chart(timeCtx, {
                    type: 'line',
                    data: {
                        labels: timeSlotOrder,
                        datasets: [{
                            label: 'Number of Sessions',
                            data: timeSlotOrder.map(slot => timeSlots[slot] || 0),
                            borderColor: 'rgba(255, 99, 132, 0.7)',
                            backgroundColor: 'rgba(255, 99, 132, 0.2)',
                            tension: 0.1,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Time Slot Utilization'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
            } catch (error) {
                console.error('Error creating timetable charts:', error);
            }
        }

        async function initializeInsights() {
            try {
                // Show loading state
                const dashboardSection = document.querySelector('.section.dashboard');
                const loadingMessage = document.createElement('div');
                loadingMessage.className = 'alert alert-info';
                loadingMessage.innerHTML = `
                    <i class="bi bi-arrow-repeat spin"></i>
                    Loading dashboard data...
                `;
                dashboardSection.prepend(loadingMessage);

                // Initialize Organization Structure
                const orgData = await fetchOrganizationData();
                if (orgData && orgData.success) {
                    updateOrganizationStats(orgData);
                    createOrganizationChart(orgData);
                }

                // Initialize Facilities
                const facilityData = await fetchFacilityData();
                const academicYears = await fetchAcademicYears();
                if (facilityData && facilityData.success) {
                    updateFacilityStats(facilityData);
                    createFacilityCharts(facilityData, academicYears);
                }

                // Initialize Timetable
                const timetableData = await fetchTimetableData();
                if (timetableData && timetableData.success) {
                    updateTimetableStats(timetableData);
                    createTimetableCharts(timetableData);
                }

                // Remove loading message
                loadingMessage.remove();
            } catch (error) {
                console.error('Error initializing insights:', error);
                // Show error message to user
                const errorMessage = document.createElement('div');
                errorMessage.className = 'alert alert-danger';
                errorMessage.style.margin = '20px';
                errorMessage.innerHTML = `
                    <strong>Error loading dashboard data:</strong><br>
                    ${error.message}<br>
                    Please try refreshing the page or contact support if the problem persists.
                `;
                document.querySelector('.section.dashboard').prepend(errorMessage);
            }
        }

        // Initialize when the page loads
        document.addEventListener('DOMContentLoaded', initializeInsights);

        // Handle window resize
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(async () => {
                try {
                    const orgData = await fetchOrganizationData();
                    const facilityData = await fetchFacilityData();
                    const academicYears = await fetchAcademicYears();
                    const timetableData = await fetchTimetableData();
                    
                    createOrganizationChart(orgData);
                    createFacilityCharts(facilityData, academicYears);
                    createTimetableCharts(timetableData);
                } catch (error) {
                    console.error('Error updating charts on resize:', error);
                    // Don't show error message on resize to avoid spamming the user
                }
            }, 250); // Debounce resize events
        });
    </script>
</body>
</html> 