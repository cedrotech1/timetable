<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include('connection.php');

// Check if user is logged in and has permission
if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit();
}

$timetable_id = isset($_GET['timetable_id']) ? intval($_GET['timetable_id']) : 0;
if (!$timetable_id) {
    die("Invalid timetable ID");
}

// Handle form submission for saving lecturers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_lecturers'])) {
    // Debug: Log the raw POST data
    error_log('POST data: ' . print_r($_POST, true));
    
    if (empty($_POST['selected_lecturers'])) {
        $error_message = 'No lecturer data received';
    } else {
        $selected_lecturers = json_decode($_POST['selected_lecturers'], true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_message = 'Invalid lecturer data format: ' . json_last_error_msg();
            error_log('JSON decode error: ' . json_last_error_msg());
        } else {
            error_log('Parsed lecturer data: ' . print_r($selected_lecturers, true));
            
            $leader_id = 0;
            $other_lecturers = [];
            
            // Extract leader ID
            if (isset($selected_lecturers['leader']) && is_array($selected_lecturers['leader'])) {
                $leader_id = intval($selected_lecturers['leader']['id'] ?? 0);
            } elseif (isset($selected_lecturers['leader'])) {
                $leader_id = intval($selected_lecturers['leader']);
            }
            
            // Extract other lecturers
            if (isset($selected_lecturers['others']) && is_array($selected_lecturers['others'])) {
                foreach ($selected_lecturers['others'] as $lecturer) {
                    if (is_array($lecturer) && isset($lecturer['id'])) {
                        $other_lecturers[] = intval($lecturer['id']);
                    } elseif (is_numeric($lecturer)) {
                        $other_lecturers[] = intval($lecturer);
                    }
                }
            }
            
            error_log("Leader ID: $leader_id");
            error_log('Other lecturers: ' . print_r($other_lecturers, true));
            
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // 1. Update leader in timetable table
                $update_leader = $conn->prepare("UPDATE timetable SET leader_lecturer_id = ? WHERE id = ?");
                $update_leader->bind_param("ii", $leader_id, $timetable_id);
                $update_leader->execute();
                
                // 2. Get current lecturers to preserve any existing assignments
                $current_lecturers = [];
                $current_query = $conn->prepare("SELECT lect_id FROM timetable_lecturers WHERE timetable_id = ?");
                $current_query->bind_param("i", $timetable_id);
                $current_query->execute();
                $current_result = $current_query->get_result();
                while ($row = $current_result->fetch_assoc()) {
                    $current_lecturers[] = $row['lect_id'];
                }
                
                // 3. Add the leader to timetable_lecturers if not already in others list
                if ($leader_id > 0) {
                    $insert_lecturer = $conn->prepare("INSERT INTO timetable_lecturers (timetable_id, lect_id) VALUES (?, ?)");
                    $insert_lecturer->bind_param("ii", $timetable_id, $leader_id);
                    $insert_lecturer->execute();
                }
                
                // 4. Add other lecturers to timetable_lecturers (excluding the leader if they were in the list)
                if (!empty($other_lecturers)) {
                    $insert_lecturer = $conn->prepare("INSERT INTO timetable_lecturers (timetable_id, lect_id) VALUES (?, ?)");
                    foreach ($other_lecturers as $lect_id) {
                        // Skip if this is the leader (already added) or already exists
                        if ($lect_id === $leader_id || in_array($lect_id, $current_lecturers)) continue;
                        
                        $insert_lecturer->bind_param("ii", $timetable_id, $lect_id);
                        $insert_lecturer->execute();
                    }
                }
                
                // Commit the transaction if all queries were successful
                $conn->commit();
                $success_message = "Lecturers updated successfully! Leader and other lecturers have been saved.";
                
                // Refresh the page to show updated data
                $_SESSION['success_message'] = $success_message;
                header("Location: manage_lecturers.php?timetable_id=" . $timetable_id);
                exit();
                
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Error updating lecturers: " . $e->getMessage();
                error_log('Database error: ' . $e->getMessage());
            }
        }
    }
}

// Get current timetable details
$timetable_query = $conn->prepare("SELECT t.*, m.name as module_name, m.code as module_code 
                                 FROM timetable t 
                                 LEFT JOIN module m ON t.module_id = m.id 
                                 WHERE t.id = ?");
$timetable_query->bind_param("i", $timetable_id);
$timetable_query->execute();
$timetable = $timetable_query->get_result()->fetch_assoc();

if (!$timetable) {
    die("Timetable not found");
}

// Get currently assigned lecturers
$assigned_lecturers = [];
$leader_lecturers = [];
$other_lecturers = [];

// Get the leader lecturer if exists
if (!empty($timetable['leader_lecturer_id'])) {
    $leader_query = $conn->prepare("
        SELECT u.id, u.names, u.email, u.phone, u.staff_number, 1 as is_leader
        FROM users u
        WHERE u.id = ?
    ");
    $leader_query->bind_param("i", $timetable['leader_lecturer_id']);
    $leader_query->execute();
    $leader_result = $leader_query->get_result();
    
    if ($leader_row = $leader_result->fetch_assoc()) {
        $leader_lecturers[] = $leader_row;
        $assigned_lecturers[$leader_row['id']] = $leader_row;
    }
}

// Get other assigned lecturers (excluding the leader)
$others_query = $conn->prepare("
    SELECT u.id, u.names, u.email, u.phone, u.staff_number, 0 as is_leader
    FROM timetable_lecturers tl
    JOIN users u ON tl.lect_id = u.id
    WHERE tl.timetable_id = ? AND (tl.lect_id != ? OR ? IS NULL)
");
$leader_id_param = $timetable['leader_lecturer_id'] ?? null;
$others_query->bind_param("iii", $timetable_id, $leader_id_param, $leader_id_param);
$others_query->execute();
$others_result = $others_query->get_result();

while ($row = $others_result->fetch_assoc()) {
    $other_lecturers[] = $row;
    $assigned_lecturers[$row['id']] = $row;
}

// Combine all current lecturers for the selection form
$current_lecturers = array_merge($leader_lecturers, $other_lecturers);

// Get leader details if exists
$leader_details = null;
if ($timetable['leader_lecturer_id']) {
    $leader_query = $conn->prepare("SELECT id, names, email, phone FROM users WHERE id = ?");
    $leader_query->bind_param("i", $timetable['leader_lecturer_id']);
    $leader_query->execute();
    $leader_details = $leader_query->get_result()->fetch_assoc();
}

// Initialize variables
$success_message = '';
$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add_lecturer' && !empty($_POST['lecturer_id'])) {
        $lecturer_id = intval($_POST['lecturer_id']);
        
        // Check if lecturer is already assigned
        $check = $conn->prepare("SELECT id FROM timetable_lecturers WHERE timetable_id = ? AND lect_id = ?");
        $check->bind_param("ii", $timetable_id, $lecturer_id);
        $check->execute();
        
        if ($check->get_result()->num_rows === 0) {
            $insert = $conn->prepare("INSERT INTO timetable_lecturers (timetable_id, lect_id) VALUES (?, ?)");
            $insert->bind_param("ii", $timetable_id, $lecturer_id);
            $insert->execute();
            
            if ($insert->affected_rows > 0) {
                $success_message = "Lecturer added successfully!";
            } else {
                $error_message = "Failed to add lecturer.";
            }
        } else {
            $error_message = "This lecturer is already assigned to this timetable.";
        }
    }
    
    // Handle lecturer removal
    if (isset($_POST['action']) && $_POST['action'] === 'remove_lecturer' && !empty($_POST['lecturer_id'])) {
        $lecturer_id = intval($_POST['lecturer_id']);
        $timetable_id = intval($_POST['timetable_id']);
        $success = false;
        
        // If this is the module leader, remove them as leader
        if (!empty($timetable['leader_lecturer_id']) && $timetable['leader_lecturer_id'] == $lecturer_id) {
            $update = $conn->prepare("UPDATE timetable SET leader_lecturer_id = NULL WHERE id = ?");
            $update->bind_param("i", $timetable_id);
            $success = $update->execute();
            $message = "Module leader removed successfully!";
        } 
        // Otherwise, remove as regular lecturer
        else {
            $delete = $conn->prepare("DELETE FROM timetable_lecturers WHERE lect_id = ? AND timetable_id = ?");
            $delete->bind_param("ii", $lecturer_id, $timetable_id);
            $success = $delete->execute();
            $message = "Lecturer removed successfully!";
        }
        
        if ($success) {
            $_SESSION['success'] = $message;
        } else {
            $_SESSION['error'] = "Failed to remove lecturer.";
        }
        
        header("Location: manage_lecturers.php?timetable_id=" . $timetable_id);
        exit();
    }
    
    // Handle setting leader lecturer
    if (isset($_POST['action']) && $_POST['action'] === 'set_leader' && !empty($_POST['lecturer_id'])) {
        $new_leader_id = intval($_POST['lecturer_id']);
        
        // First check if this lecturer is assigned to the timetable
        $check = $conn->prepare("SELECT id FROM timetable_lecturers WHERE timetable_id = ? AND lect_id = ?");
        $check->bind_param("ii", $timetable_id, $new_leader_id);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            // Update the timetable with the new leader
            $update = $conn->prepare("UPDATE timetable SET leader_lecturer_id = ? WHERE id = ?");
            $update->bind_param("ii", $new_leader_id, $timetable_id);
            $update->execute();
            
            if ($update->affected_rows > 0) {
                $success_message = "Leader lecturer updated successfully!";
                // Refresh timetable data
                $timetable['leader_lecturer_id'] = $new_leader_id;
            } else {
                $error_message = "Failed to update leader lecturer.";
            }
        } else {
            $error_message = "This lecturer is not assigned to this timetable.";
        }
    }
}

// Fetch current lecturers for this timetable
$current_lecturers_query = $conn->prepare("
    SELECT tl.id as timetable_lecturer_id, u.id, u.names, u.email, u.phone, u.staff_number,
           (SELECT COUNT(*) FROM timetable t WHERE t.leader_lecturer_id = u.id AND t.id = ?) as is_leader
    FROM timetable_lecturers tl
    JOIN users u ON tl.lect_id = u.id
    WHERE tl.timetable_id = ?
    ORDER BY is_leader DESC, u.names ASC
");
$current_lecturers_query->bind_param("ii", $timetable_id, $timetable_id);
$current_lecturers_query->execute();
$current_lecturers = $current_lecturers_query->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch all potential lecturers (staff with lecturer role)
$potential_lecturers_query = $conn->query("
    SELECT id, names, email, staff_number 
    FROM users 
    WHERE role = 'Lecturer' OR role = 'lecturer'
    ORDER BY names ASC
");
$potential_lecturers = $potential_lecturers_query->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Lecturers - Timetable #<?php echo $timetable_id; ?></title>
    
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

    <!-- Template Main CSS File -->
    <link href="assets/css/style.css" rel="stylesheet">
    
    <style>
        .lecturer-card {
            background: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid #007bff;
        }
        .leader-card {
            background: #e7f5ff;
            border-left-color: #0d6efd;
        }
        .action-buttons {
            margin-top: 10px;
        }
        .module-header {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include('./includes/header.php'); ?>
    <?php include('./includes/menu.php'); ?>
    
    <!-- Add Bootstrap Icons CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Add custom styles for lecturer selection -->
    <style>
        .lecturer-card {
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            padding: 1rem;
            margin-bottom: 1rem;
            position: relative;
        }
        .lecturer-card h5 {
            margin-top: 0;
        }
        .lecturer-card .badge {
            font-size: 0.8rem;
            margin-top: 0.5rem;
        }
        .lecturer-actions {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
        }
        .lecturer-actions .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }
        #lecturerSelectionContainer {
            margin-top: 1.5rem;
        }
    </style>

    <main id="main" class="main">
        <div class="pagetitle">
            <h1>Manage Lecturers</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item">Timetable</li>
                    <li class="breadcrumb-item active">Manage Lecturers</li>
                </ol>
            </nav>
        </div><!-- End Page Title -->

        <section class="section">
            <div class="row">
                <div class="col-lg-12">
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($success_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($error_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="card">
                        <div class="card-body">
                            <div class="module-header">
                                <h5 class="card-title">Module: <?php echo htmlspecialchars($timetable['module_name'] . ' (' . $timetable['module_code'] . ')'); ?></h5>
                                <p class="mb-0">Timetable ID: <?php echo $timetable_id; ?></p>
                            </div>
                            
                            <?php 
                            // Display success message from session
                            if (isset($_SESSION['success_message'])) {
                                echo '<div class="alert alert-success alert-dismissible fade show" role="alert">';
                                echo htmlspecialchars($_SESSION['success_message']);
                                echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                                echo '</div>';
                                unset($_SESSION['success_message']); // Clear the message after displaying
                            }
                            ?>
                            
                            <form id="lecturerSelectionForm" method="post" onsubmit="return prepareFormData()">
                                <input type="hidden" name="timetable_id" value="<?php echo $timetable_id; ?>">
                                <input type="hidden" name="selected_lecturers" id="selectedLecturers" value='<?php echo htmlspecialchars(json_encode($selected_lecturers), ENT_QUOTES, 'UTF-8'); ?>'>
                                <input type="hidden" name="save_lecturers" value="1">
                                
                                <div id="lecturerSelectionContainer">
                                    <?php include('select_lecturers.php'); ?>
                                </div>
                                
                                <div class="mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Save Changes
                                    </button>
                                    <a href="timetable_set.php" class="btn btn-secondary">
                                        <i class="bi bi-x"></i> Cancel
                                    </a>
                                </div>
                            </form>

                            <?php if (isset($success_message)): ?>
                                <div class="alert alert-success"><?php echo $success_message; ?></div>
                            <?php endif; ?>
                            
                            <!-- Current Lecturers -->
                            <div class="mb-4">
                                <!-- Leader Section -->
                                <div class="mb-4">
                                    <h5>Module Leader</h5>
                                    <?php if (!empty($leader_lecturers)): ?>
                                        <div class="row">
                                            <?php foreach ($leader_lecturers as $lecturer): ?>
                                                <div class="col-md-6">
                                                    <div class="lecturer-card leader-card">
                                                        <div class="d-flex justify-content-between">
                                                            <div>
                                                                <h6 class="mb-1">
                                                                    <?php echo htmlspecialchars((string)($lecturer['names'] ?? '')); ?>
                                                                    <span class="badge bg-primary">Leader</span>
                                                                </h6>
                                                                <?php if (!empty($lecturer['staff_number'])): ?>
                                                                <p class="mb-1 text-muted small">
                                                                    <?php echo htmlspecialchars((string)$lecturer['staff_number']); ?>
                                                                </p>
                                                                <?php endif; ?>
                                                                <?php if (!empty($lecturer['email'])): ?>
                                                                <p class="mb-1 small">
                                                                    <i class="bi bi-envelope"></i> 
                                                                    <?php echo htmlspecialchars((string)$lecturer['email']); ?>
                                                                </p>
                                                                <?php endif; ?>
                                                                <?php if (!empty($lecturer['phone'])): ?>
                                                                    <p class="mb-0 small">
                                                                        <i class="bi bi-telephone"></i> 
                                                                        <?php echo htmlspecialchars((string)$lecturer['phone']); ?>
                                                                    </p>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="action-buttons">
                                                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to remove this lecturer from leader position?');">
                                                                    <input type="hidden" name="action" value="remove_lecturer">
                                                                    <input type="hidden" name="timetable_id" value="<?php echo $timetable_id; ?>">
                                                                    <input type="hidden" name="lecturer_id" value="<?php echo $timetable['leader_lecturer_id']; ?>">
                                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                        <i class="bi bi-trash"></i> Remove
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-warning">No module leader assigned yet.</div>
                                    <?php endif; ?>
                                </div>

                                <!-- Other Lecturers Section -->
                                <div class="mt-4">
                                    <h5>Other Lecturers</h5>
                                    <?php if (count($other_lecturers) > 0): ?>
                                        <div class="row">
                                            <?php foreach ($other_lecturers as $lecturer): ?>
                                                <div class="col-md-6">
                                                    <div class="lecturer-card">
                                                    <div class="d-flex justify-content-between">
                                                        <div>
                                                            <h6 class="mb-1">
                                                                <?php echo htmlspecialchars((string)($lecturer['names'] ?? '')); ?>
                                                                <?php if (isset($lecturer['is_leader']) && $lecturer['is_leader']): ?>
                                                                    <span class="badge bg-primary">Leader</span>
                                                                <?php endif; ?>
                                                            </h6>
                                                            <?php if (!empty($lecturer['staff_number'])): ?>
                                                            <p class="mb-1 text-muted small">
                                                                <?php echo htmlspecialchars((string)$lecturer['staff_number']); ?>
                                                            </p>
                                                            <?php endif; ?>
                                                            <?php if (!empty($lecturer['email'])): ?>
                                                            <p class="mb-1 small">
                                                                <i class="bi bi-envelope"></i> 
                                                                <?php echo htmlspecialchars((string)$lecturer['email']); ?>
                                                            </p>
                                                            <?php endif; ?>
                                                            <?php if (!empty($lecturer['phone'])): ?>
                                                                <p class="mb-0 small">
                                                                    <i class="bi bi-telephone"></i> 
                                                                    <?php echo htmlspecialchars((string)$lecturer['phone']); ?>
                                                                </p>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="action-buttons">
                                                            <?php if (!(isset($lecturer['is_leader']) && $lecturer['is_leader'])): ?>
                                                                <form method="POST" class="d-inline">
                                                                    <input type="hidden" name="action" value="set_leader">
                                                                    <input type="hidden" name="timetable_id" value="<?php echo $timetable_id; ?>">
                                                                    <input type="hidden" name="lecturer_id" value="<?php echo $lecturer['id']; ?>">
                                                                    <button type="submit" class="btn btn-sm btn-outline-primary mb-1" title="Set as Module Leader">
                                                                        <i class="bi bi-star"></i> Set as Leader
                                                                    </button>
                                                                </form>
                                                                <br>
                                                            <?php endif; ?>
                                                            
                                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to remove this lecturer?');">
                                                                <input type="hidden" name="action" value="remove_lecturer">
                                                                <input type="hidden" name="timetable_id" value="<?php echo $timetable_id; ?>">
                                                                <input type="hidden" name="lecturer_id" value="<?php echo $lecturer['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove Lecturer">
                                                                    <i class="bi bi-trash"></i> Remove
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info">No other lecturers assigned to this timetable yet.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include('./includes/footer.php'); ?>

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
    
    <script>
        // Function to prepare form data before submission
        function prepareFormData() {
            try {
                // Get the selected lecturers from the select_lecturers.php interface
                const selectedLecturers = {
                    leader: selected?.leader || null,
                    others: selected?.others || []
                };
                
                // Update the hidden input with the current selection
                document.getElementById('selectedLecturers').value = JSON.stringify(selectedLecturers);
                console.log('Prepared form data:', selectedLecturers);
                
                // Show loading state
                const submitBtn = document.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
                }
                
                return true; // Allow form submission
                
            } catch (error) {
                console.error('Error preparing form data:', error);
                alert('Error preparing form data. Please check the console for details.');
                return false; // Prevent form submission
            }
        }
        
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                var alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    var fadeEffect = setInterval(function() {
                        if (!alert.style.opacity) {
                            alert.style.opacity = 1;
                        }
                        if (alert.style.opacity > 0) {
                            alert.style.opacity -= 0.1;
                        } else {
                            clearInterval(fadeEffect);
                            alert.style.display = 'none';
                        }
                    }, 200);
                });
            }, 5000);
            
            // Log the initial state for debugging
            console.log('Initial selected lecturers:', <?php echo json_encode($selected_lecturers); ?>);
        });
    </script>
</body>
</html>
