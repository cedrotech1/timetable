<?php
require_once 'config.php';
require_once 'auth.php';

// Function to get overall statistics
function getOverallStats($connection) {
    $stats = [
        'total_campuses' => 0,
        'total_hostels' => 0,
        'total_rooms' => 0,
        'total_beds' => 0,
        'occupied_beds' => 0,
        'available_beds' => 0,
        'total_applications' => 0,
        'pending_applications' => 0,
        'approved_applications' => 0,
        'rejected_applications' => 0
    ];
    
    // Get campus counts
    $query = "SELECT COUNT(*) as total FROM campuses";
    $result = $connection->query($query);
    $stats['total_campuses'] = $result->fetch_assoc()['total'];
    
    // Get hostel counts
    $query = "SELECT COUNT(*) as total FROM hostels";
    $result = $connection->query($query);
    $stats['total_hostels'] = $result->fetch_assoc()['total'];
    
    // Get room and bed statistics
    $query = "SELECT 
                COUNT(*) as total_rooms,
                SUM(number_of_beds) as total_beds,
                SUM(number_of_beds - remain) as occupied_beds,
                SUM(remain) as available_beds
              FROM rooms";
    $result = $connection->query($query);
    $room_stats = $result->fetch_assoc();
    $stats['total_rooms'] = $room_stats['total_rooms'];
    $stats['total_beds'] = $room_stats['total_beds'];
    $stats['occupied_beds'] = $room_stats['occupied_beds'];
    $stats['available_beds'] = $room_stats['available_beds'];
    
    // Get application statistics
    $query = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
              FROM applications";
    $result = $connection->query($query);
    $app_stats = $result->fetch_assoc();
    $stats['total_applications'] = $app_stats['total'];
    $stats['pending_applications'] = $app_stats['pending'];
    $stats['approved_applications'] = $app_stats['approved'];
    $stats['rejected_applications'] = $app_stats['rejected'];
    
    return $stats;
}

// Function to get campus-wise statistics
function getCampusStats($connection) {
    $query = "SELECT 
                c.name as campus_name,
                COUNT(DISTINCT h.id) as total_hostels,
                COUNT(DISTINCT r.id) as total_rooms,
                SUM(r.number_of_beds) as total_beds,
                SUM(r.remain) as available_beds,
                COUNT(DISTINCT a.id) as total_applications,
                SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) as pending_applications
              FROM campuses c
              LEFT JOIN hostels h ON c.id = h.campus_id
              LEFT JOIN rooms r ON h.id = r.hostel_id
              LEFT JOIN applications a ON r.id = a.room_id
              GROUP BY c.id, c.name";
    $result = $connection->query($query);
    
    $stats = [];
    while ($row = $result->fetch_assoc()) {
        $stats[] = $row;
    }
    
    return $stats;
}

// Function to get hostel-wise statistics
function getHostelStats($connection) {
    $query = "SELECT 
                h.name as hostel_name,
                c.name as campus_name,
                COUNT(DISTINCT r.id) as total_rooms,
                SUM(r.number_of_beds) as total_beds,
                SUM(r.remain) as available_beds,
                COUNT(DISTINCT a.id) as total_applications,
                SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) as pending_applications,
                SUM(CASE WHEN a.status = 'approved' THEN 1 ELSE 0 END) as approved_applications
              FROM hostels h
              JOIN campuses c ON h.campus_id = c.id
              LEFT JOIN rooms r ON h.id = r.hostel_id
              LEFT JOIN applications a ON r.id = a.room_id
              GROUP BY h.id, h.name, c.name
              ORDER BY c.name, h.name";
    $result = $connection->query($query);
    
    $stats = [];
    while ($row = $result->fetch_assoc()) {
        $stats[] = $row;
    }
    
    return $stats;
}

// Function to get application status distribution
function getApplicationStats($connection) {
    $query = "SELECT 
                status,
                COUNT(*) as count,
                DATE(created_at) as date
              FROM applications
              GROUP BY status, DATE(created_at)
              ORDER BY date DESC, status";
    $result = $connection->query($query);
    
    $stats = [];
    while ($row = $result->fetch_assoc()) {
        $stats[] = $row;
    }
    
    return $stats;
}

// Function to get room status distribution
function getRoomStatusStats($connection) {
    $query = "SELECT 
                status,
                COUNT(*) as count,
                SUM(number_of_beds) as total_beds,
                SUM(remain) as available_beds
              FROM rooms
              GROUP BY status";
    $result = $connection->query($query);
    
    $stats = [];
    while ($row = $result->fetch_assoc()) {
        $stats[] = $row;
    }
    
    return $stats;
}

// Get all statistics
$overall_stats = getOverallStats($connection);
$campus_stats = getCampusStats($connection);
$hostel_stats = getHostelStats($connection);
$application_stats = getApplicationStats($connection);
$room_status_stats = getRoomStatusStats($connection);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hostel System Statistics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stat-card {
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <h1 class="mb-4">Hostel System Statistics</h1>
        
        <!-- Overall Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Campuses</h5>
                        <h2><?php echo $overall_stats['total_campuses']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Hostels</h5>
                        <h2><?php echo $overall_stats['total_hostels']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Applications</h5>
                        <h2><?php echo $overall_stats['total_applications']; ?></h2>
                        <small>Pending: <?php echo $overall_stats['pending_applications']; ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-warning text-white">
                    <div class="card-body">
                        <h5 class="card-title">Available Beds</h5>
                        <h2><?php echo $overall_stats['available_beds']; ?></h2>
                        <small>Total: <?php echo $overall_stats['total_beds']; ?></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <!-- Campus Distribution -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Campus Distribution</h5>
                        <div class="chart-container">
                            <canvas id="campusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Application Status -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Application Status</h5>
                        <div class="chart-container">
                            <canvas id="applicationChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Room Status Distribution -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Room Status Distribution</h5>
                        <div class="chart-container">
                            <canvas id="roomStatusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Statistics Tables -->
        <div class="row">
            <!-- Campus-wise Statistics -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Campus-wise Statistics</h5>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Campus</th>
                                        <th>Hostels</th>
                                        <th>Rooms</th>
                                        <th>Beds</th>
                                        <th>Available</th>
                                        <th>Applications</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($campus_stats as $stat): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($stat['campus_name']); ?></td>
                                        <td><?php echo $stat['total_hostels']; ?></td>
                                        <td><?php echo $stat['total_rooms']; ?></td>
                                        <td><?php echo $stat['total_beds']; ?></td>
                                        <td><?php echo $stat['available_beds']; ?></td>
                                        <td><?php echo $stat['total_applications']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hostel-wise Statistics -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Hostel-wise Statistics</h5>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Hostel</th>
                                        <th>Campus</th>
                                        <th>Rooms</th>
                                        <th>Beds</th>
                                        <th>Available</th>
                                        <th>Applications</th>
                                        <th>Pending</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($hostel_stats as $stat): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($stat['hostel_name']); ?></td>
                                        <td><?php echo htmlspecialchars($stat['campus_name']); ?></td>
                                        <td><?php echo $stat['total_rooms']; ?></td>
                                        <td><?php echo $stat['total_beds']; ?></td>
                                        <td><?php echo $stat['available_beds']; ?></td>
                                        <td><?php echo $stat['total_applications']; ?></td>
                                        <td><?php echo $stat['pending_applications']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Campus Distribution Chart
    new Chart(document.getElementById('campusChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($campus_stats, 'campus_name')); ?>,
            datasets: [{
                label: 'Total Beds',
                data: <?php echo json_encode(array_column($campus_stats, 'total_beds')); ?>,
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }, {
                label: 'Available Beds',
                data: <?php echo json_encode(array_column($campus_stats, 'available_beds')); ?>,
                backgroundColor: 'rgba(75, 192, 192, 0.5)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Application Status Chart
    new Chart(document.getElementById('applicationChart'), {
        type: 'pie',
        data: {
            labels: ['Pending', 'Approved', 'Rejected'],
            datasets: [{
                data: [
                    <?php echo $overall_stats['pending_applications']; ?>,
                    <?php echo $overall_stats['approved_applications']; ?>,
                    <?php echo $overall_stats['rejected_applications']; ?>
                ],
                backgroundColor: [
                    'rgba(255, 206, 86, 0.5)',
                    'rgba(75, 192, 192, 0.5)',
                    'rgba(255, 99, 132, 0.5)'
                ],
                borderColor: [
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(255, 99, 132, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });

    // Room Status Chart
    new Chart(document.getElementById('roomStatusChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($room_status_stats, 'status')); ?>,
            datasets: [{
                label: 'Total Beds',
                data: <?php echo json_encode(array_column($room_status_stats, 'total_beds')); ?>,
                backgroundColor: 'rgba(153, 102, 255, 0.5)',
                borderColor: 'rgba(153, 102, 255, 1)',
                borderWidth: 1
            }, {
                label: 'Available Beds',
                data: <?php echo json_encode(array_column($room_status_stats, 'available_beds')); ?>,
                backgroundColor: 'rgba(255, 159, 64, 0.5)',
                borderColor: 'rgba(255, 159, 64, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    </script>
</body>
</html> 