<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include("./connection.php");

// Use role and campus from session
$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'lecturer';
$campus = isset($_SESSION['campus']) ? $_SESSION['campus'] : null;
$school_id = isset($_SESSION['school_id']) ? $_SESSION['school_id'] : null;

// Role-based queries
function getInsightsByRole($connection, $role, $campus, $school_id) {
    $data = [];
    
    switch(strtolower($role)) {
        case 'admin':
        case 'super_admin':
            // Admin can see everything
            $sql = "
            SELECT 
                COUNT(DISTINCT u.id) as total_users,
                COUNT(DISTINCT CASE WHEN u.role = 'student' THEN u.id END) as total_students,
                COUNT(DISTINCT CASE WHEN u.role = 'lecturer' THEN u.id END) as total_lecturers,
                COUNT(DISTINCT c.id) as total_campuses,
                COUNT(DISTINCT s.id) as total_schools,
                COUNT(DISTINCT d.id) as total_departments,
                COUNT(DISTINCT p.id) as total_programs,
                COUNT(DISTINCT m.id) as total_modules,
                COUNT(DISTINCT sg.id) as total_student_groups,
                COUNT(DISTINCT t.id) as total_timetables,
                COUNT(DISTINCT ts.id) as total_sessions,
                COUNT(DISTINCT f.id) as total_facilities,
                COUNT(DISTINCT i.id) as total_intakes,
                COUNT(DISTINCT ay.id) as total_academic_years
            FROM users u
            LEFT JOIN campus c ON u.campus = c.id
            LEFT JOIN school s ON u.school = s.id
            LEFT JOIN department d ON d.school_id = s.id
            LEFT JOIN program p ON p.school_id = s.id
            LEFT JOIN module m ON m.program_id = p.id
            LEFT JOIN student_group sg ON sg.intake_id IN (SELECT i.id FROM intake i WHERE i.program_id = p.id)
            LEFT JOIN timetable t ON t.module_id = m.id
            LEFT JOIN timetable_sessions ts ON ts.timetable_id = t.id
            LEFT JOIN facility f ON f.campus_id = c.id
            LEFT JOIN intake i ON i.program_id = p.id
            LEFT JOIN academic_year ay ON ay.id = t.academic_year_id
            ";
            break;
            
        case 'lecturer':
            // Lecturer can see their school data
            $whereClause = "WHERE u.role IN ('student', 'lecturer')";
            if($campus) {
                $campus = mysqli_real_escape_string($connection, $campus);
                $whereClause .= " AND u.campus = '$campus'";
            }
            if($school_id) {
                $school_id = mysqli_real_escape_string($connection, $school_id);
                $whereClause .= " AND u.school = '$school_id'";
            }
            
            $sql = "
            SELECT 
                COUNT(DISTINCT u.id) as total_users,
                COUNT(DISTINCT CASE WHEN u.role = 'student' THEN u.id END) as total_students,
                COUNT(DISTINCT CASE WHEN u.role = 'lecturer' THEN u.id END) as total_lecturers,
                COUNT(DISTINCT s.id) as total_schools,
                COUNT(DISTINCT d.id) as total_departments,
                COUNT(DISTINCT p.id) as total_programs,
                COUNT(DISTINCT m.id) as total_modules,
                COUNT(DISTINCT sg.id) as total_student_groups,
                COUNT(DISTINCT t.id) as total_timetables,
                COUNT(DISTINCT ts.id) as total_sessions,
                COUNT(DISTINCT f.id) as total_facilities
            FROM users u
            LEFT JOIN campus c ON u.campus = c.id
            LEFT JOIN school s ON u.school = s.id
            LEFT JOIN department d ON d.school_id = s.id
            LEFT JOIN program p ON p.school_id = s.id
            LEFT JOIN module m ON m.program_id = p.id
            LEFT JOIN student_group sg ON sg.intake_id IN (SELECT i.id FROM intake i WHERE i.program_id = p.id)
            LEFT JOIN timetable t ON t.module_id = m.id
            LEFT JOIN timetable_sessions ts ON ts.timetable_id = t.id
            LEFT JOIN facility f ON f.campus_id = c.id
            $whereClause
            ";
            break;
            
        case 'student':
            // Student can see limited data - their group, program, modules
            $email = mysqli_real_escape_string($connection, $_SESSION['email'] ?? '');
            $whereClause = "WHERE st.email = '$email'";
            
            $sql = "
            SELECT 
                COUNT(DISTINCT st.id) as my_group_size,
                COUNT(DISTINCT m.id) as total_modules,
                COUNT(DISTINCT t.id) as total_timetables,
                COUNT(DISTINCT ts.id) as total_sessions,
                COUNT(DISTINCT f.id) as available_facilities,
                sg.name as group_name,
                p.name as program_name,
                c.name as campus_name
            FROM student st
            LEFT JOIN student_group sg ON st.group_id = sg.id
            LEFT JOIN intake i ON sg.intake_id = i.id
            LEFT JOIN program p ON i.program_id = p.id
            LEFT JOIN module m ON m.program_id = p.id
            LEFT JOIN timetable t ON t.module_id = m.id
            LEFT JOIN timetable_sessions ts ON ts.timetable_id = t.id
            LEFT JOIN school s ON p.school_id = s.id
            LEFT JOIN campus c ON s.campus_id = c.id
            LEFT JOIN facility f ON f.campus_id = c.id
            $whereClause
            ";
            break;
            
        default:
            // Default view for other roles
            $sql = "SELECT 0 as total_users";
    }
    
    $result = mysqli_query($connection, $sql);
    if (!$result) {
        error_log("SQL Error in getInsightsByRole: " . mysqli_error($connection));
        return [];
    }
    
    $row = mysqli_fetch_assoc($result);
    return $row ? $row : [];
}

// Get chart data based on role
function getChartData($connection, $role, $campus, $school_id) {
    $chartData = ['labels' => [], 'values' => []];
    
    switch(strtolower($role)) {
        case 'admin':
        case 'super_admin':
            // Users by campus
            $sql = "
            SELECT c.name as label, COUNT(DISTINCT u.id) as value 
            FROM campus c 
            LEFT JOIN users u ON u.campus = c.id 
            GROUP BY c.id, c.name 
            ORDER BY value DESC LIMIT 10";
            break;
            
        case 'lecturer':
            // Students by program in their school
            $whereClause = "";
            if($school_id) {
                $school_id = mysqli_real_escape_string($connection, $school_id);
                $whereClause = "WHERE p.school_id = '$school_id'";
            }
            $sql = "
            SELECT p.name as label, COUNT(DISTINCT s.id) as value 
            FROM program p 
            LEFT JOIN intake i ON i.program_id = p.id
            LEFT JOIN student_group sg ON sg.intake_id = i.id
            LEFT JOIN student s ON s.group_id = sg.id
            $whereClause
            GROUP BY p.id, p.name 
            HAVING value > 0
            ORDER BY value DESC LIMIT 8";
            break;
            
        case 'student':
            // Modules by semester for their program
            $email = mysqli_real_escape_string($connection, $_SESSION['email'] ?? '');
            $sql = "
            SELECT m.semester as label, COUNT(m.id) as value 
            FROM student st
            JOIN student_group sg ON st.group_id = sg.id
            JOIN intake i ON sg.intake_id = i.id
            JOIN program p ON i.program_id = p.id
            JOIN module m ON m.program_id = p.id
            WHERE st.email = '$email'
            GROUP BY m.semester 
            ORDER BY value DESC";
            break;
            
        default:
            $sql = "SELECT 'No Data' as label, 0 as value";
    }
    
    $result = mysqli_query($connection, $sql);
    if (!$result) {
        error_log("SQL Error in getChartData: " . mysqli_error($connection));
        return $chartData;
    }
    
    while($row = mysqli_fetch_assoc($result)) {
        $chartData['labels'][] = $row['label'] ?? 'Unknown';
        $chartData['values'][] = (int)($row['value'] ?? 0);
    }
    
    return $chartData;
}

// Get additional stats based on role
function getAdditionalStats($connection, $role, $campus, $school_id) {
    switch(strtolower($role)) {
        case 'admin':
        case 'super_admin':
            $sql = "
            SELECT 
                COUNT(DISTINCT CASE WHEN t.status = 'pending' THEN t.id END) as pending_timetables,
                COUNT(DISTINCT CASE WHEN t.status = 'approved' THEN t.id END) as approved_timetables,
                COUNT(DISTINCT CASE WHEN u.active = 1 THEN u.id END) as active_users,
                COUNT(DISTINCT ar.id) as total_resources
            FROM timetable t
            CROSS JOIN users u
            LEFT JOIN all_resources ar ON 1=1";
            break;
            
        case 'lecturer':
            $whereClause = "";
            if($school_id) {
                $school_id = mysqli_real_escape_string($connection, $school_id);
                $whereClause = "WHERE u.school = '$school_id'";
            }
            $sql = "
            SELECT 
                COUNT(DISTINCT CASE WHEN t.status = 'pending' THEN t.id END) as pending_timetables,
                COUNT(DISTINCT CASE WHEN t.status = 'approved' THEN t.id END) as approved_timetables,
                COUNT(DISTINCT CASE WHEN u.active = 1 AND u.role = 'student' THEN u.id END) as active_students
            FROM timetable t
            CROSS JOIN users u
            $whereClause";
            break;
            
        case 'student':
            $email = mysqli_real_escape_string($connection, $_SESSION['email'] ?? '');
            $sql = "
            SELECT 
                COUNT(DISTINCT CASE WHEN t.status = 'approved' THEN t.id END) as my_timetables,
                COUNT(DISTINCT ts.id) as my_sessions,
                SUM(DISTINCT m.credits) as total_credits
            FROM student st
            JOIN student_group sg ON st.group_id = sg.id
            JOIN intake i ON sg.intake_id = i.id
            JOIN program p ON i.program_id = p.id
            JOIN module m ON m.program_id = p.id
            JOIN timetable t ON t.module_id = m.id
            JOIN timetable_sessions ts ON ts.timetable_id = t.id
            WHERE st.email = '$email'";
            break;
            
        default:
            return [];
    }
    
    $result = mysqli_query($connection, $sql);
    if (!$result) {
        error_log("SQL Error in getAdditionalStats: " . mysqli_error($connection));
        return [];
    }
    
    $row = mysqli_fetch_assoc($result);
    return $row ? $row : [];
}

// Check database connection
if (!$connection) {
    die("Database connection failed: " . mysqli_connect_error());
}

$data = getInsightsByRole($connection, $role, $campus, $school_id);
$chartData = getChartData($connection, $role, $campus, $school_id);
$additionalStats = getAdditionalStats($connection, $role, $campus, $school_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    <title>UR-TIMETABLE | Dashboard</title>
    <link href="assets/img/icon1.png" rel="icon">
    <link href="assets/img/icon1.png" rel="apple-touch-icon">
    
    <!-- Bootstrap & Styles -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        /* Simple Card Styles */
        .info-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #007bff;
            margin-bottom: 20px;
        }

        .info-card h5 {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }

        .info-card h3 {
            color: #007bff;
            font-size: 2rem;
            font-weight: bold;
            margin: 0;
        }

        /* Chart Container Styles */
        .chart-container {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .chart-container h4 {
            color: #007bff;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .chart-wrapper {
            position: relative;
            height: 400px;
        }

        /* Additional Stats */
        .additional-stats {
            margin-top: 20px;
        }

        .mini-stat {
            background: white;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 15px;
        }

        .mini-stat h6 {
            color: #6c757d;
            font-size: 0.8rem;
            margin-bottom: 8px;
        }

        .mini-stat h4 {
            color: #007bff;
            font-weight: bold;
            margin: 0;
        }

        .role-badge {
            background: #007bff;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            text-transform: uppercase;
            font-weight: 500;
        }

        .alert-info {
            border-left: 4px solid #007bff;
            background: rgba(0, 123, 255, 0.1);
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
        <h1>Dashboard Insights 
            <span class="role-badge"><?php echo htmlspecialchars(ucfirst($role)); ?></span>
        </h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Insights</li>
            </ol>
        </nav>
    </div>

    <?php if(strtolower($role) == 'admin' || strtolower($role) == 'super_admin'): ?>
    <!-- Admin View -->
    <div class="row">
        <div class="col-md-3">
            <div class="card info-card">
                <div class="card-body">
                    <h5><i class="bi bi-people"></i> Total Users</h5>
                    <h3><?php echo number_format($data['total_users'] ?? 0); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card info-card">
                <div class="card-body">
                    <h5><i class="bi bi-mortarboard"></i> Students</h5>
                    <h3><?php echo number_format($data['total_students'] ?? 0); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card info-card">
                <div class="card-body">
                    <h5><i class="bi bi-person-badge"></i> Lecturers</h5>
                    <h3><?php echo number_format($data['total_lecturers'] ?? 0); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card info-card">
                <div class="card-body">
                    <h5><i class="bi bi-building"></i> Campuses</h5>
                    <h3><?php echo number_format($data['total_campuses'] ?? 0); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Admin Stats -->
    <div class="row additional-stats">
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="mini-stat">
                <h6>Schools</h6>
                <h4><?php echo number_format($data['total_schools'] ?? 0); ?></h4>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="mini-stat">
                <h6>Departments</h6>
                <h4><?php echo number_format($data['total_departments'] ?? 0); ?></h4>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="mini-stat">
                <h6>Programs</h6>
                <h4><?php echo number_format($data['total_programs'] ?? 0); ?></h4>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="mini-stat">
                <h6>Modules</h6>
                <h4><?php echo number_format($data['total_modules'] ?? 0); ?></h4>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="mini-stat">
                <h6>Timetables</h6>
                <h4><?php echo number_format($data['total_timetables'] ?? 0); ?></h4>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="mini-stat">
                <h6>Facilities</h6>
                <h4><?php echo number_format($data['total_facilities'] ?? 0); ?></h4>
            </div>
        </div>
    </div>

    <?php elseif(strtolower($role) == 'lecturer'): ?>
    <!-- Lecturer View -->
    <div class="row">
        <div class="col-md-3">
            <div class="card info-card">
                <div class="card-body">
                    <h5><i class="bi bi-mortarboard"></i> Students</h5>
                    <h3><?php echo number_format($data['total_students'] ?? 0); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card info-card">
                <div class="card-body">
                    <h5><i class="bi bi-journal-bookmark"></i> Programs</h5>
                    <h3><?php echo number_format($data['total_programs'] ?? 0); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card info-card">
                <div class="card-body">
                    <h5><i class="bi bi-book"></i> Modules</h5>
                    <h3><?php echo number_format($data['total_modules'] ?? 0); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card info-card">
                <div class="card-body">
                    <h5><i class="bi bi-calendar-check"></i> Timetables</h5>
                    <h3><?php echo number_format($data['total_timetables'] ?? 0); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Lecturer Stats -->
    <div class="row additional-stats">
        <div class="col-lg-3 col-md-6">
            <div class="mini-stat">
                <h6>Student Groups</h6>
                <h4><?php echo number_format($data['total_student_groups'] ?? 0); ?></h4>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="mini-stat">
                <h6>Sessions</h6>
                <h4><?php echo number_format($data['total_sessions'] ?? 0); ?></h4>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="mini-stat">
                <h6>Facilities</h6>
                <h4><?php echo number_format($data['total_facilities'] ?? 0); ?></h4>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="mini-stat">
                <h6>Departments</h6>
                <h4><?php echo number_format($data['total_departments'] ?? 0); ?></h4>
            </div>
        </div>
    </div>

    <?php elseif(strtolower($role) == 'student'): ?>
    <!-- Student View -->
    <div class="alert alert-info">
        <h5><i class="bi bi-info-circle"></i> Your Academic Overview</h5>
        <p>Welcome! Here's your personal academic dashboard with your program and timetable information.</p>
    </div>

    <div class="row">
        <div class="col-md-3">
            <div class="card info-card">
                <div class="card-body">
                    <h5><i class="bi bi-book"></i> My Modules</h5>
                    <h3><?php echo number_format($data['total_modules'] ?? 0); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card info-card">
                <div class="card-body">
                    <h5><i class="bi bi-calendar-check"></i> My Timetables</h5>
                    <h3><?php echo number_format($data['total_timetables'] ?? 0); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card info-card">
                <div class="card-body">
                    <h5><i class="bi bi-clock"></i> Total Sessions</h5>
                    <h3><?php echo number_format($data['total_sessions'] ?? 0); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card info-card">
                <div class="card-body">
                    <h5><i class="bi bi-geo-alt"></i> Facilities</h5>
                    <h3><?php echo number_format($data['available_facilities'] ?? 0); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Student Info -->
    <div class="row additional-stats">
        <div class="col-lg-4 col-md-12">
            <div class="mini-stat">
                <h6>My Group</h6>
                <h4><?php echo htmlspecialchars($data['group_name'] ?? 'Not Assigned'); ?></h4>
            </div>
        </div>
        <div class="col-lg-4 col-md-12">
            <div class="mini-stat">
                <h6>Program</h6>
                <h4><?php echo htmlspecialchars(substr($data['program_name'] ?? 'Not Assigned', 0, 20)); ?></h4>
            </div>
        </div>
        <div class="col-lg-4 col-md-12">
            <div class="mini-stat">
                <h6>Campus</h6>
                <h4><?php echo htmlspecialchars($data['campus_name'] ?? 'Not Assigned'); ?></h4>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- Default/Other Roles View -->
    <div class="alert alert-warning">
        <h5><i class="bi bi-exclamation-triangle"></i> Limited Access</h5>
        <p>Your current role has limited dashboard access. Please contact administrator for more information.</p>
    </div>
    <?php endif; ?>

    <!-- Charts Section (Role-based) -->
    <?php if(!empty($chartData['labels'])): ?>
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="chart-container">
                <h4>
                    <?php 
                    switch(strtolower($role)) {
                        case 'admin':
                        case 'super_admin':
                            echo '<i class="bi bi-bar-chart"></i> Users by Campus';
                            break;
                        case 'lecturer':
                            echo '<i class="bi bi-bar-chart"></i> Students by Program';
                            break;
                        case 'student':
                            echo '<i class="bi bi-bar-chart"></i> Modules by Semester';
                            break;
                        default:
                            echo '<i class="bi bi-bar-chart"></i> Overview';
                    }
                    ?>
                </h4>
                <div class="chart-wrapper">
                    <canvas id="barChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="chart-container">
                <h4><i class="bi bi-pie-chart"></i> Distribution</h4>
                <div class="chart-wrapper">
                    <canvas id="pieChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Additional Stats for Admin/Lecturer -->
    <?php if(!empty($additionalStats) && (strtolower($role) == 'admin' || strtolower($role) == 'super_admin' || strtolower($role) == 'lecturer')): ?>
    <div class="row mt-4">
        <?php if(isset($additionalStats['pending_timetables'])): ?>
        <div class="col-md-4">
            <div class="card info-card">
                <div class="card-body">
                    <h5><i class="bi bi-clock-history"></i> Pending Timetables</h5>
                    <h3><?php echo number_format($additionalStats['pending_timetables']); ?></h3>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if(isset($additionalStats['approved_timetables'])): ?>
        <div class="col-md-4">
            <div class="card info-card">
                <div class="card-body">
                    <h5><i class="bi bi-check-circle"></i> Approved Timetables</h5>
                    <h3><?php echo number_format($additionalStats['approved_timetables']); ?></h3>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if(isset($additionalStats['active_users'])): ?>
        <div class="col-md-4">
            <div class="card info-card">
                <div class="card-body">
                    <h5><i class="bi bi-person-check"></i> Active Users</h5>
                    <h3><?php echo number_format($additionalStats['active_users']); ?></h3>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if(isset($additionalStats['active_students'])): ?>
        <div class="col-md-4">
            <div class="card info-card">
                <div class="card-body">
                    <h5><i class="bi bi-person-check"></i> Active Students</h5>
                    <h3><?php echo number_format($additionalStats['active_students']); ?></h3>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if(isset($additionalStats['total_resources'])): ?>
        <div class="col-md-4">
            <div class="card info-card">
                <div class="card-body">
                    <h5><i class="bi bi-box"></i> Resources</h5>
                    <h3><?php echo number_format($additionalStats['total_resources']); ?></h3>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</main>

<!-- Bootstrap JS -->
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>

<!-- Chart.js Script -->
<script>
<?php if(!empty($chartData['labels'])): ?>
const labels = <?php echo json_encode($chartData['labels']); ?>;
const values = <?php echo json_encode($chartData['values']); ?>;

// Check if chart elements exist before initializing
if (document.getElementById('barChart')) {
    // Bar Chart
    new Chart(document.getElementById('barChart'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Count',
                data: values,
                backgroundColor: 'rgba(0, 123, 255, 0.6)',
                borderColor: '#007bff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

if (document.getElementById('pieChart')) {
    // Pie Chart
    new Chart(document.getElementById('pieChart'), {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                label: 'Distribution',
                data: values,
                backgroundColor: [
                    'rgba(0, 123, 255, 0.8)',
                    'rgba(0, 123, 255, 0.6)',
                    'rgba(0, 123, 255, 0.4)',
                    'rgba(0, 123, 255, 0.3)',
                    'rgba(0, 123, 255, 0.2)',
                    'rgba(40, 167, 69, 0.8)',
                    'rgba(40, 167, 69, 0.6)',
                    'rgba(255, 193, 7, 0.8)'
                ],
                borderColor: '#007bff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
}
<?php endif; ?>
</script>

</body>
</html>