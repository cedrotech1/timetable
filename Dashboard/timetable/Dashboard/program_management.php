<?php
session_start();
include('connection.php');

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit();
}
$user_id = $_SESSION["id"];
                    // get school
                    $sql = "SELECT * FROM users WHERE id = $user_id";
                    $result = mysqli_query($connection, $sql);
                    $row = mysqli_fetch_assoc($result);
                    $school_id = $row["school"];

$program_id = isset($_GET['program_id']) ? (int)$_GET['program_id'] : 0;
$program_name = isset($_GET['program_name']) ? urldecode($_GET['program_name']) : 'Program';

if ($program_id <= 0) {
    header('Location: dashboard.php');
    exit();
}

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    
    try {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'add_intake':
                $year = (int)($_POST['year'] ?? 0);
                $month = (int)($_POST['month'] ?? 0);
                
                if ($year < 2000 || $year > 2100) {
                    $response['message'] = 'Please enter a valid year between 2000 and 2100';
                    break;
                }
                
                if ($month < 1 || $month > 12) {
                    $response['message'] = 'Please enter a valid month (1-12)';
                    break;
                }
                
                // Check if intake already exists for this program, year, and month
                $check = $connection->prepare("SELECT id FROM intake WHERE program_id = ? AND year = ? AND month = ?");
                $check->bind_param('iii', $program_id, $year, $month);
                $check->execute();
                
                if ($check->get_result()->num_rows > 0) {
                    $response['message'] = 'An intake already exists for this program in the selected year and month';
                    break;
                }
                
                $size = 0; // Initialize size as 0, will be updated when groups are added
                $stmt = $connection->prepare("INSERT INTO intake (year, month, size, program_id) VALUES (?, ?, ?, ?)");
                $stmt->bind_param('iiii', $year, $month, $size, $program_id);
                
                if ($stmt->execute()) {
                    $intake_id = $connection->insert_id;
                    $response['success'] = true;
                    $response['message'] = 'Intake added successfully';
                    $response['intake_id'] = $intake_id;
                } else {
                    $response['message'] = 'Failed to add intake: ' . $connection->error;
                }
                break;
                
            case 'delete_intake':
                $intake_id = (int)($_POST['intake_id'] ?? 0);
                
                // Check if intake has groups
                $check = $connection->prepare("SELECT COUNT(*) as count FROM student_group WHERE intake_id = ?");
                $check->bind_param('i', $intake_id);
                $check->execute();
                $result = $check->get_result()->fetch_assoc();
                
                if ($result['count'] > 0) {
                    $response['message'] = 'Cannot delete intake with existing groups';
                    break;
                }
                
                $stmt = $connection->prepare("DELETE FROM intake WHERE id = ? AND program_id = ?");
                $stmt->bind_param('ii', $intake_id, $program_id);
                
                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Intake deleted successfully';
                } else {
                    $response['message'] = 'Failed to delete intake';
                }
                break;
                
            case 'add_group':
                // Debug: Log the received POST data and raw input
                error_log('Raw POST data: ' . file_get_contents('php://input'));
                error_log('POST array: ' . print_r($_POST, true));
                
                // Debug: Log all server variables related to the request
                error_log('Request method: ' . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'));
                error_log('Content-Type header: ' . ($_SERVER['CONTENT_TYPE'] ?? 'NOT SET'));
                
                // Try to get JSON data if it's a JSON request
                $jsonData = json_decode(file_get_contents('php://input'), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    error_log('JSON data received: ' . print_r($jsonData, true));
                    $_POST = array_merge($_POST, $jsonData);
                }
                
                // Get parameters with extensive debugging
                $name = isset($_POST['name']) ? trim($_POST['name']) : '';
                $size = isset($_POST['size']) ? (int)$_POST['size'] : 0;
                $intake_id = isset($_POST['intake_id']) ? (int)$_POST['intake_id'] : 0;
                $group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;
                $program_id = isset($_POST['program_id']) ? (int)$_POST['program_id'] : 0;
                
                // Debug log all extracted values
                error_log("Extracted values - Name: '$name', Size: $size, Intake ID: $intake_id, Group ID: $group_id, Program ID: $program_id");
                
                // Initialize response array
                $response = ['success' => false, 'message' => ''];
                
                if (empty($name)) {
                    $response['message'] = 'Group name is required';
                    header('Content-Type: application/json');
                    echo json_encode($response);
                    exit;
                }
                
                if ($size <= 0) {
                    $response['message'] = 'Please enter a valid group size';
                    header('Content-Type: application/json');
                    echo json_encode($response);
                    exit;
                }
                
                if ($intake_id <= 0) {
                    $response['message'] = 'Invalid intake';
                    header('Content-Type: application/json');
                    echo json_encode($response);
                    exit;
                }
                
                // Start transaction
                $connection->begin_transaction();
                
                try {
                    if ($group_id > 0) {
                        // Update existing group
                        $check = $connection->prepare("SELECT id, size, name FROM student_group WHERE id = ? AND intake_id = ?");
                        $check->bind_param('ii', $group_id, $intake_id);
                        $check->execute();
                        $existingGroup = $check->get_result()->fetch_assoc();
                        
                        if (!$existingGroup) {
                            throw new Exception('Group not found');
                        }
                        
                        // Check if name is being changed and if the new name already exists
                        if ($name !== $existingGroup['name']) {
                            $nameCheck = $connection->prepare("SELECT id FROM student_group WHERE name = ? AND intake_id = ? AND id != ?");
                            $nameCheck->bind_param('sii', $name, $intake_id, $group_id);
                            $nameCheck->execute();
                            
                            if ($nameCheck->get_result()->num_rows > 0) {
                                throw new Exception('A group with this name already exists in the selected intake');
                            }
                        }
                        
                        // Update the group
                        $stmt = $connection->prepare("UPDATE student_group SET name = ?, size = ? WHERE id = ?");
                        $stmt->bind_param('sii', $name, $size, $group_id);
                        if (!$stmt->execute()) {
                            throw new Exception('Failed to update group: ' . $connection->error);
                        }
                        
                        // Calculate size difference for updating intake
                        $sizeDiff = $size - $existingGroup['size'];
                        $response['message'] = 'Group updated successfully';
                    } else {
                        // Add new group
                        // Check if group name already exists in this intake
                        $check = $connection->prepare("SELECT id FROM student_group WHERE name = ? AND intake_id = ?");
                        $check->bind_param('si', $name, $intake_id);
                        $check->execute();
                        
                        if ($check->get_result()->num_rows > 0) {
                            throw new Exception('A group with this name already exists in the selected intake');
                        }
                        
                        // Insert new group
                        $stmt = $connection->prepare("INSERT INTO student_group (name, size, intake_id) VALUES (?, ?, ?)");
                        $stmt->bind_param('sii', $name, $size, $intake_id);
                        if (!$stmt->execute()) {
                            throw new Exception('Failed to add group: ' . $connection->error);
                        }
                        $group_id = $connection->insert_id;
                        $sizeDiff = $size;
                        $response['message'] = 'Group added successfully';
                    }
                    
                    // Update intake size if there's a change
                    if ($sizeDiff != 0) {
                        $updateIntake = $connection->prepare("UPDATE intake SET size = GREATEST(0, size + ?) WHERE id = ?");
                        $updateIntake->bind_param('ii', $sizeDiff, $intake_id);
                        if (!$updateIntake->execute()) {
                            throw new Exception('Failed to update intake size: ' . $connection->error);
                        }
                    }
                    
                    $connection->commit();
                    $response['success'] = true;
                    $response['group_id'] = $group_id;
                    
                } catch (Exception $e) {
                    $connection->rollback();
                    $response['success'] = false;
                    $response['message'] = $e->getMessage();
                    error_log('Group update error: ' . $e->getMessage());
                }
                
                // Ensure we always return JSON
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
                break;
                
            case 'delete_group':
                $group_id = (int)($_POST['group_id'] ?? 0);
                
                if ($group_id <= 0) {
                    $response['message'] = 'Invalid group';
                    break;
                }
                
                // Start transaction
                $connection->begin_transaction();
                
                try {
                    // First get the group and its size before deleting
                    $getGroup = $connection->prepare("
                        SELECT sg.id, sg.size, sg.intake_id 
                        FROM student_group sg
                        JOIN intake i ON sg.intake_id = i.id
                        WHERE sg.id = ? AND i.program_id = ?
                    ");
                    $getGroup->bind_param('ii', $group_id, $program_id);
                    $getGroup->execute();
                    $group = $getGroup->get_result()->fetch_assoc();
                    
                    if (!$group) {
                        throw new Exception('Group not found or you do not have permission to delete it');
                    }
                    
                    // Delete the group
                    $delete = $connection->prepare("DELETE FROM student_group WHERE id = ?");
                    $delete->bind_param('i', $group_id);
                    $delete->execute();
                    
                    // Update the intake size
                    $updateIntake = $connection->prepare("UPDATE intake SET size = GREATEST(0, size - ?) WHERE id = ?");
                    $updateIntake->bind_param('ii', $group['size'], $group['intake_id']);
                    $updateIntake->execute();
                    
                    $connection->commit();
                    
                    $response['success'] = true;
                    $response['message'] = 'Group deleted successfully';
                    
                } catch (Exception $e) {
                    $connection->rollback();
                    $response['message'] = 'Failed to delete group: ' . $e->getMessage();
                }
                break;
                
            case 'get_intakes':
                $stmt = $connection->prepare("
                    SELECT i.*, 
                           (SELECT COUNT(*) FROM student_group WHERE intake_id = i.id) as group_count
                    FROM intake i 
                    WHERE i.program_id = ? 
                    ORDER BY i.year DESC, i.month DESC
                ");
                $stmt->bind_param('i', $program_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $intakes = [];
                
                while ($row = $result->fetch_assoc()) {
                    $intakes[] = [
                        'id' => $row['id'],
                        'year' => $row['year'],
                        'month' => $row['month'],
                        'size' => $row['size'],
                        'group_count' => $row['group_count']
                    ];
                }
                
                $response['success'] = true;
                $response['data'] = $intakes;
                break;
                
            case 'get_groups':
                $intake_id = (int)($_POST['intake_id'] ?? 0);
                
                $stmt = $connection->prepare("
                    SELECT sg.* 
                    FROM student_group sg
                    JOIN intake i ON sg.intake_id = i.id
                    WHERE i.program_id = ? AND sg.intake_id = ?
                    ORDER BY sg.name
                
                ");
                $stmt->bind_param('ii', $program_id, $intake_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $groups = [];
                
                while ($row = $result->fetch_assoc()) {
                    $groups[] = [
                        'id' => $row['id'],
                        'name' => $row['name'],
                        'size' => $row['size']
                    ];
                }
                
                $response['success'] = true;
                $response['data'] = $groups;
                break;
                
            default:
                $response['message'] = 'Invalid action';
        }
    } catch (Exception $e) {
        $response['message'] = 'Server error: ' . $e->getMessage();
    }
    
    echo json_encode($response);
    exit();
}

// Get program details
$stmt = $connection->prepare("SELECT * FROM program WHERE id = ?");
$stmt->bind_param('i', $program_id);
$stmt->execute();
$program = $stmt->get_result()->fetch_assoc();

if (!$program) {
    header('Location: dashboard.php');
    exit();
}

// Get current year and month for the form
define('CURRENT_YEAR', date('Y'));
define('CURRENT_MONTH', date('n'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Program: <?= htmlspecialchars($program_name) ?> - Timetable System</title>
    
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="assets/img/icon1.png" rel="icon" />
    <meta content="" name="description">
    <meta content="" name="keywords">
    <link href="assets/img/icon1.png" rel="apple-touch-icon">
    <link href="https://fonts.gstatic.com" rel="preconnect">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">

    <!-- CDN UI libs (same stack as your college page) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.min.css" rel="stylesheet" />
    <link href="https://cdn.datatables.net/v/bs5/dt-2.0.8/datatables.min.css" rel="stylesheet" />

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <style>
        .intake-table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 1.5rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.05);
        }
        .intake-table th, .intake-table td {
            border: 1px solid #dee2e6;
            padding: 0.75rem;
            vertical-align: middle;
        }
        .intake-table thead th {
            background-color: #f8f9fa;
            font-weight: 600;
            text-align: left;
        }
        .intake-table tbody tr:hover {
            background-color: rgba(13, 110, 253, 0.05);
        }
        .intake-header {
            background-color: #f1f8ff !important;
            font-weight: 600;
        }
        .intake-month {
            color: #0d6efd;
            font-weight: 600;
        }
        .group-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.35em 0.65em;
            font-size: 0.875rem;
            font-weight: 500;
            line-height: 1;
            color: #fff;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
            background-color: rgb(22, 50, 99);
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .group-actions {
            white-space: nowrap;
        }
        .no-groups {
            color: #6c757d;
            font-style: italic;
        }
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        .action-buttons .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
        .btn-primary,.btn-outline-primary { background-color: rgb(22, 50, 99); color:#fff; }
        .btn-primary:hover,.btn-outline-primary:hover { background-color: rgb(147, 144, 204); }
        .btn-danger,.btn-outline-danger { background-color: rgb(207, 93, 0); color:#fff; }
        .btn-danger:hover,.btn-outline-danger:hover { background-color: rgb(207, 4, 4); }

        h5 { font-size: 17px !important; background-color: rgb(22, 50, 99) !important; color:#fff !important; padding: 6px !important; margin-bottom: 10px !important; border-radius: 5px !important; }
        h4 { font-size: 17px !important; background-color: rgb(22, 50, 99) !important; color:#fff !important; padding: 6px !important; margin-bottom: 10px !important; border-radius: 5px !important; }
        h2 { font-size: 17px !important; background-color: rgb(22, 50, 99) !important; color:#fff !important; padding: 6px !important; margin-bottom: 10px !important; border-radius: 5px !important; }
        th { font-size: 17px !important; background-color: rgb(22, 50, 99) !important; color:#fff !important; padding: 6px !important; margin-bottom: 10px !important; border-radius: 5px !important; }
       /* program defalt link color */
       table a { color: rgb(0, 2, 5) !important; }
       .main { background-color: #f8f9fa; }
       /* edit icon color */
       .card { border: none !important;
        box-shadow: 3px 3px 5px rgba(0, 0, 0, 0.1) !important;
        border-radius: 5px !important; }
        .pagetitle { padding: 10px !important;
        background-color: #fff !important;
        border-radius: 5px !important; }
        .pagetitle h1 { font-size: 19px !important; 
        background-color: rgb(22, 50, 99) !important; 
        color:#fff !important; 
        padding: 6px !important; 
        margin-bottom: 10px !important; 
        border-radius: 5px !important; }
       
    </style>
</head>
<body>
    <?php include('includes/header.php'); ?>
    <?php include('includes/menu.php'); ?>

    <main id="main" class="main">
        <div class="pagetitle">
            <h1>Manage Program: <?= htmlspecialchars($program_name) ?></h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="school_page.php?id=<?= $school_id ?? '' ?>">School</a></li>
                    <li class="breadcrumb-item active"><?= htmlspecialchars($program_name) ?></li>
                </ol>
            </nav>
        </div>

        <section class="section">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Add New Intake</h5>
                            <form id="addIntakeForm" class="row g-3">
                                <div class="col-md-3">
                                    <label for="intakeYear" class="form-label">Year</label>
                                    <select class="form-select" id="intakeYear" required>
                                        <?php for ($y = CURRENT_YEAR - 2; $y <= CURRENT_YEAR + 5; $y++): ?>
                                            <option value="<?= $y ?>" <?= $y === CURRENT_YEAR ? 'selected' : '' ?>><?= $y ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="intakeMonth" class="form-label">Month</label>
                                    <select class="form-select" id="intakeMonth" required>
                                        <?php 
                                        $months = [
                                            1 => 'January', 'February', 'March', 'April', 'May', 'June',
                                            'July', 'August', 'September', 'October', 'November', 'December'
                                        ];
                                        foreach ($months as $num => $name): 
                                        ?>
                                            <option value="<?= $num ?>" <?= $num === CURRENT_MONTH ? 'selected' : '' ?>><?= $name ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <input type="hidden" id="intakeSize" value="0">
                                <div class="col-md-3 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-plus-circle"></i> Add Intake
                                    </button>
                                    <!-- add program modules button link page -->
                                     <a href="program_modules.php?program_id=<?= $program_id ?>" class="btn btn-primary">
                                        <i class="bi bi-plus-circle"></i> Add Modules
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Intakes & Groups</h5>
                            <div id="intakesList">
                                <div class="text-center py-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2">Loading intakes and groups...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Add Group Modal -->
    <div class="modal fade" id="addGroupModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Group</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addGroupForm">
                        <input type="hidden" id="groupIntakeId">
                        <div class="mb-3">
                            <label for="groupName" class="form-label">Group Name</label>
                            <input type="text" class="form-control" id="groupName" required>
                        </div>
                        <div class="mb-3">
                            <label for="groupSize" class="form-label">Group Size</label>
                            <input type="number" class="form-control" id="groupSize" min="1" value="30" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveGroupBtn">Save Group</button>
                </div>
            </div>
        </div>
    </div>

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
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.all.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // Load intakes on page load
        $(document).ready(function() {
            loadIntakes();
            
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
        
        // Add intake form submission
        $('#addIntakeForm').on('submit', function(e) {
            e.preventDefault();
            
            const year = $('#intakeYear').val();
            const month = $('#intakeMonth').val();
            const size = $('#intakeSize').val();
            
            $.ajax({
                url: '?ajax=1&program_id=<?= $program_id ?>',
                method: 'POST',
                data: {
                    action: 'add_intake',
                    year: year,
                    month: month,
                    size: size
                },
                success: function(response) {
                    if (response.success) {
                        showAlert('success', 'Success', response.message || 'Intake added successfully');
                        loadIntakes();
                        $('#addIntakeForm')[0].reset();
                    } else {
                        showAlert('error', 'Error', response.message || 'Failed to add intake');
                    }
                },
                error: function() {
                    showAlert('error', 'Error', 'An error occurred while processing your request');
                }
            });
        });
        
        // Save group button click
        $('#saveGroupBtn').on('click', function() {
            const name = $('#groupName').val().trim();
            const size = $('#groupSize').val();
            const intakeId = $('#groupIntakeId').val();
            
            if (!name) {
                showAlert('error', 'Error', 'Group name is required');
                return;
            }
            
            if (size <= 0) {
                showAlert('error', 'Error', 'Please enter a valid group size');
                return;
            }
            
            $.ajax({
                url: '?ajax=1&program_id=<?= $program_id ?>',
                method: 'POST',
                data: {
                    action: 'add_group',
                    name: name,
                    size: size,
                    intake_id: intakeId
                },
                success: function(response) {
                    if (response.success) {
                        showAlert('success', 'Success', response.message || 'Group added successfully');
                        $('#addGroupModal').modal('hide');
                        loadIntakes();
                    } else {
                        showAlert('error', 'Error', response.message || 'Failed to add group');
                    }
                },
                error: function() {
                    showAlert('error', 'Error', 'An error occurred while processing your request');
                }
            });
        });
        
        // Show add group modal
        $(document).on('click', '.add-group-btn', function() {
            const intakeId = $(this).data('intake-id');
            const intakeMonth = $(this).data('intake-month');
            const intakeYear = $(this).data('intake-year');
            
            $('#groupIntakeId').val(intakeId);
            $('#groupName').val('');
            $('#groupSize').val('30');
            
            $('#addGroupModal .modal-title').text(`Add Group - ${intakeMonth} ${intakeYear}`);
            $('#addGroupModal').modal('show');
        });
        
        // Delete intake
        $(document).on('click', '.delete-intake-btn', function() {
            const intakeId = $(this).data('intake-id');
            const intakeName = $(this).data('intake-name');
            
            Swal.fire({
                title: 'Delete Intake?',
                text: `Are you sure you want to delete the intake ${intakeName}? This action cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '?ajax=1&program_id=<?= $program_id ?>',
                        method: 'POST',
                        data: {
                            action: 'delete_intake',
                            intake_id: intakeId
                        },
                        success: function(response) {
                            if (response.success) {
                                showAlert('success', 'Success', response.message || 'Intake deleted successfully');
                                loadIntakes();
                            } else {
                                showAlert('error', 'Error', response.message || 'Failed to delete intake');
                            }
                        },
                        error: function() {
                            showAlert('error', 'Error', 'An error occurred while processing your request');
                        }
                    });
                }
            });
        });
        
        // Delete group
        $(document).on('click', '.delete-group-btn', function(e) {
            e.stopPropagation();
            
            const groupId = $(this).data('group-id');
            const groupName = $(this).data('group-name');
            
            Swal.fire({
                title: 'Delete Group?',
                text: `Are you sure you want to delete the group ${escapeHtml(groupName)}?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '?ajax=1&program_id=<?= $program_id ?>',
                        method: 'POST',
                        data: {
                            action: 'delete_group',
                            group_id: groupId
                        },
                        success: function(response) {
                            if (response.success) {
                                showAlert('success', 'Success', response.message || 'Group deleted successfully');
                                loadIntakes();
                            } else {
                                showAlert('error', 'Error', response.message || 'Failed to delete group');
                            }
                        },
                        error: function() {
                            showAlert('error', 'Error', 'An error occurred while processing your request');
                        }
                    });
                }
            });
        });
        
        // Edit group button click handler - redirect to edit page
        $(document).on('click', '.edit-group-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const groupId = $(this).data('group-id');
            const intakeId = $(this).data('intake-id');
            
            if (groupId && intakeId) {
                window.location.href = `edit_group_page.php?group_id=${groupId}&intake_id=${intakeId}&program_id=<?= $program_id ?>`;
            }
        });
        
        // Toggle groups visibility
        $(document).on('click', '.intake-header', function() {
            const card = $(this).closest('.intake-card');
            const groupsContainer = card.find('.groups-container');
            const icon = card.find('.toggle-icon');
            
            groupsContainer.slideToggle(200);
            icon.toggleClass('bi-chevron-down bi-chevron-up');
        });
        
        // Load groups for an intake (used when adding/deleting groups)
        function loadGroups(intakeId, container) {
            // This function is kept for backward compatibility
            // The main table now loads all data at once
            if (container) {
                container.html('<span class="badge bg-primary">Refreshing...</span>');
                setTimeout(() => {
                    loadIntakes(); // Refresh the entire table
                }, 300);
            }
        }
        
        // Load all intakes with groups in a table
        function loadIntakes() {
            $('#intakesList').html(`
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading intakes and groups...</p>
                </div>
            `);
            
            $.ajax({
                url: '?ajax=1&program_id=<?= $program_id ?>',
                method: 'POST',
                data: { action: 'get_intakes' },
                success: function(response) {
                    if (response.success) {
                        const intakes = response.data || [];
                        
                        if (intakes.length > 0) {
                            let html = `
                                <div class="table-responsive">
                                    <table class="intake-table">
                                        <thead>
                                            <tr>
                                                <th style="width: 20%;">Intake</th>
                                                <th style="width: 15%;">Size</th>
                                                <th style="width: 45%;">Groups</th>
                                                <th style="width: 20%;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                            `;
                            
                            const months = [
                                'January', 'February', 'March', 'April', 'May', 'June',
                                'July', 'August', 'September', 'October', 'November', 'December'
                            ];
                            
                            // First, collect all intakes with their groups
                            const intakePromises = intakes.map(intake => {
                                return new Promise((resolve) => {
                                    const monthName = months[parseInt(intake.month) - 1] || '';
                                    const intakeKey = `${monthName} ${intake.year}`;
                                    
                                    // Get groups for this intake
                                    $.ajax({
                                        url: '?ajax=1&program_id=<?= $program_id ?>',
                                        method: 'POST',
                                        data: {
                                            action: 'get_groups',
                                            intake_id: intake.id
                                        },
                                        success: function(groupsResponse) {
                                            const groups = groupsResponse.success ? (groupsResponse.data || []) : [];
                                            resolve({
                                                id: intake.id,
                                                name: intakeKey,
                                                month: monthName,
                                                year: intake.year,
                                                size: intake.size,
                                                groupCount: groups.length,
                                                groups: groups
                                            });
                                        },
                                        error: function() {
                                            resolve({
                                                id: intake.id,
                                                name: intakeKey,
                                                month: monthName,
                                                year: intake.year,
                                                size: intake.size,
                                                groupCount: 0,
                                                groups: []
                                            });
                                        }
                                    });
                                });
                            });
                            
                            // When all intakes have been processed with their groups
                            Promise.all(intakePromises).then(intakesWithGroups => {
                                // Sort intakes by year and month (newest first)
                                intakesWithGroups.sort((a, b) => {
                                    if (a.year !== b.year) return b.year - a.year;
                                    return b.month - a.month;
                                });
                                
                                // Generate table rows
                                intakesWithGroups.forEach((intake, index) => {
                                    const rowCount = Math.max(1, intake.groups.length);
                                    
                                    // Intake info cell (with rowspan)
                                    html += `
                                        <tr>
                                            <td class="intake-header" rowspan="${rowCount}">
                                                <div class="intake-month">${intake.month} ${intake.year}</div>
                                                <div class="text-muted small">${intake.groupCount} group(s)</div>
                                            </td>
                                            <td rowspan="${rowCount}">${intake.size} students</td>
                                    `;
                                    
                                    // Groups for this intake
                                    if (intake.groups.length > 0) {
                                        // First group in the first row
                                        const firstGroup = intake.groups[0];
                                        html += `
                                            <td>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="group-badge">
                                                        ${escapeHtml(firstGroup.name)} (${firstGroup.size} students)
                                                    </span>
                                                    <div class="action-buttons">
                                                        <button type="button" class="btn btn-sm btn-outline-danger delete-group-btn" 
                                                                data-group-id="${firstGroup.id}" 
                                                                data-group-name="${firstGroup.name}"
                                                                data-bs-toggle="tooltip" 
                                                                data-bs-placement="top" 
                                                                title="Delete Group">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                        <a href="edit_group_page.php?group_id=${firstGroup.id}&intake_id=${intake.id}&program_id=<?= $program_id ?>" 
                                                           class="btn btn-sm btn-outline-primary"
                                                           data-bs-toggle="tooltip" 
                                                           data-bs-placement="top" 
                                                           title="Edit Group">
                                                            <i class="bi bi-pencil" style="color: rgb(248, 250, 252)"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="action-buttons" rowspan="${rowCount}">
                                                <button type="button" class="btn btn-sm btn-primary add-group-btn" 
                                                        data-intake-id="${intake.id}" 
                                                        data-intake-month="${intake.month}" 
                                                        data-intake-year="${intake.year}"
                                                        data-bs-toggle="tooltip" 
                                                        data-bs-placement="top" 
                                                        title="Add Group">
                                                    <i class="bi bi-plus-circle"></i> Add Group
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger delete-intake-btn" 
                                                        data-intake-id="${intake.id}" 
                                                        data-intake-name="${intake.month} ${intake.year}"
                                                        data-bs-toggle="tooltip" 
                                                        data-bs-placement="top" 
                                                        title="Delete Intake">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        `;
                                        
                                        // Additional groups in separate rows
                                        for (let i = 1; i < intake.groups.length; i++) {
                                            const group = intake.groups[i];
                                            html += `
                                                <tr>
                                                    <td>
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <span class="group-badge">
                                                                ${escapeHtml(group.name)} (${group.size} students)
                                                            </span>
                                                            <div class="action-buttons">
                                                                <button type="button" class="btn btn-sm btn-outline-danger delete-group-btn" 
                                                                        data-group-id="${group.id}" 
                                                                        data-group-name="${group.name}"
                                                                        data-bs-toggle="tooltip" 
                                                                        data-bs-placement="top" 
                                                                        title="Delete Group">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                                <a href="edit_group_page.php?group_id=${group.id}&intake_id=${intake.id}&program_id=<?= $program_id ?>" 
                                                                   class="btn btn-sm btn-outline-primary"
                                                                   data-bs-toggle="tooltip" 
                                                                   data-bs-placement="top" 
                                                                   title="Edit Group">
                                                                    <i class="bi bi-pencil" style="color: rgb(248, 250, 252)"></i>
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            `;
                                        }
                                    } else {
                                        // No groups for this intake
                                        html += `
                                            <td class="no-groups">No groups added yet</td>
                                            <td class="action-buttons">
                                                <button type="button" class="btn btn-sm btn-primary add-group-btn" 
                                                        data-intake-id="${intake.id}" 
                                                        data-intake-month="${intake.month}" 
                                                        data-intake-year="${intake.year}"
                                                        data-bs-toggle="tooltip" 
                                                        data-bs-placement="top" 
                                                        title="Add Group">
                                                    <i class="bi bi-plus-circle"></i> Add Group
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger delete-intake-btn" 
                                                        data-intake-id="${intake.id}" 
                                                        data-intake-name="${intake.month} ${intake.year}"
                                                        data-bs-toggle="tooltip" 
                                                        data-bs-placement="top" 
                                                        title="Delete Intake">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        `;
                                    }
                                });
                                
                                // Close table
                                html += `
                                        </tbody>
                                    </table>
                                </div>
                                `;
                                
                                $('#intakesList').html(html);
                                
                                // Initialize tooltips for the new elements
                                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                                var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                                    return new bootstrap.Tooltip(tooltipTriggerEl);
                                });
                                
                            });
                        } else {
                            $('#intakesList').html(`
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> No intakes found. Add your first intake using the form above.
                                </div>
                            `);
                        }
                    } else {
                        $('#intakesList').html(`
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle"></i> Failed to load intakes. ${response.message || 'Please try again.'}
                            </div>
                        `);
                    }
                },
                error: function() {
                    $('#intakesList').html(`
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i> An error occurred while loading intakes. Please try again later.
                        </div>
                    `);
                }
            });
        }
        
        // Helper function to escape HTML
        function escapeHtml(unsafe) {
            if (typeof unsafe !== 'string') return unsafe;
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
        
        // Function to show group form (add/edit)
        function showGroupForm(intakeId, groupId = 0, groupName = '', groupSize = 0) {
            const isEdit = groupId > 0;
            
            Swal.fire({
                title: isEdit ? 'Edit Group' : 'Add New Group',
                html: `
                    <div class="mb-3">
                        <label for="groupName" class="form-label">Group Name</label>
                        <input type="text" class="form-control" id="groupName" value="${escapeHtml(groupName)}" placeholder="Enter group name" required>
                    </div>
                    <div class="mb-3">
                        <label for="groupSize" class="form-label">Group Size</label>
                        <input type="number" class="form-control" id="groupSize" min="1" value="${groupSize || ''}" required>
                    </div>
                    <input type="hidden" id="groupId" value="${groupId}">
                `,
                showCancelButton: true,
                confirmButtonText: isEdit ? 'Update Group' : 'Add Group',
                cancelButtonText: 'Cancel',
                focusConfirm: false,
                preConfirm: () => {
                    const name = document.getElementById('groupName').value.trim();
                    const size = parseInt(document.getElementById('groupSize').value);
                    
                    if (!name) {
                        Swal.showValidationMessage('Group name is required');
                        return false;
                    }
                    
                    if (isNaN(size) || size <= 0) {
                        Swal.showValidationMessage('Please enter a valid group size');
                        return false;
                    }
                    
                    return { name, size };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const { name, size } = result.value;
                    const groupId = document.getElementById('groupId').value ? parseInt(document.getElementById('groupId').value) : 0;
                    
                    // Create request data
                    const requestData = {
                        action: 'add_group',
                        name: name,
                        size: size,
                        intake_id: intakeId,
                        program_id: <?= $program_id ?>
                    };
                    
                    // Add group_id only if editing
                    if (groupId > 0) {
                        requestData.group_id = groupId;
                    }
                    
                    console.log('Sending data:', requestData);
                    
                    // Show loading state
                    Swal.fire({
                        title: isEdit ? 'Updating...' : 'Adding...',
                        text: 'Please wait',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Show loading state
                    Swal.fire({
                        title: isEdit ? 'Updating Group...' : 'Adding Group...',
                        text: 'Please wait',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading()
                    });

                    // Send request to edit_group.php
                    const formData = new FormData();
                    formData.append('name', name);
                    formData.append('size', size);
                    formData.append('intake_id', intakeId);
                    if (isEdit) {
                        formData.append('group_id', groupId);
                    }

                    fetch('edit_group.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: result.message,
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                // Reload the intakes to show updates
                                loadIntakes();
                            });
                        } else {
                            throw new Error(result.message || 'Operation failed');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: error.message || 'An error occurred. Please try again.'
                        });
                    });
                }
            });
        }
        
        // Helper function to show toast notifications
        function toast(type, message) {
            Swal.fire({
                icon: type,
                title: 'Notification',
                text: message,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        }
        
        // Helper function to show alerts
        function showAlert(icon, title, text) {
            Swal.fire({
                icon: icon,
                title: title,
                text: text,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        }
    });
    </script>
</body>
</html>
