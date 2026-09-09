<?php
// Include database connection
session_start();
include 'connection.php';

// Initialize variables
$message = '';
$edit_mode = false;
$edit_id = 0;
$current_abbreviation = '';
$current_full_name = '';

// Handle delete actions
if (isset($_GET['action'])) {
    // Delete single college
    if ($_GET['action'] === 'delete' && isset($_GET['id'])) {
        $delete_id = intval($_GET['id']);   
        $delete_query = "DELETE FROM college WHERE id = $delete_id";
        if (mysqli_query($connection, $delete_query)) {
            $message = "College deleted successfully.";
        } else {
            $message = "Error deleting college: " . mysqli_error($connection);
        }
    }
    // Delete all colleges
    elseif ($_GET['action'] === 'delete_all' && isset($_GET['confirm']) && $_GET['confirm'] === 'true') {
        // Disable foreign key checks temporarily
        mysqli_query($connection, "SET FOREIGN_KEY_CHECKS = 0");
        
        // Delete all colleges
        $delete_all = mysqli_query($connection, "TRUNCATE TABLE college");
        
        // Re-enable foreign key checks
        mysqli_query($connection, "SET FOREIGN_KEY_CHECKS = 1");
        
        if ($delete_all) {
            $message = "All colleges have been deleted successfully.";
            $edit_mode = false;
        } else {
            $message = "Error deleting all colleges: " . mysqli_error($connection);
        }
    }
}

// Handle edit mode
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_id = intval($_GET['id']);
    $edit_query = "SELECT * FROM college WHERE id = $edit_id";
    $edit_result = mysqli_query($connection, $edit_query);
    if ($edit_result && mysqli_num_rows($edit_result) > 0) {
        $edit_mode = true;
        $college = mysqli_fetch_assoc($edit_result);
        $current_abbreviation = $college['name'];
        $current_full_name = $college['full_name'];
    } else {
        $message = "College not found.";
    }
}

// Handle cancel edit
if (isset($_POST['cancel_edit'])) {
    $edit_mode = false;
    $edit_id = 0;
    $current_abbreviation = '';
    $current_full_name = '';
    $message = "Edit cancelled.";
}

// Handle form submission for both add and edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_college']) || isset($_POST['update_college']))) {
    $abbreviation = isset($_POST['abbreviation']) ? mysqli_real_escape_string($connection, trim($_POST['abbreviation'])) : '';
    $full_name = isset($_POST['full_name']) ? mysqli_real_escape_string($connection, trim($_POST['full_name'])) : '';
    $edit_id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;

    // Validate inputs
    if (empty($abbreviation)) {
        $message = "Error: College abbreviation cannot be empty.";
    } elseif (empty($full_name)) {
        $message = "Error: College full name cannot be empty.";
    } else {
        // Check for duplicate abbreviation (case-insensitive), excluding current record if in edit mode
        $abbrCheckQuery = "SELECT id FROM college WHERE LOWER(name) = LOWER('$abbreviation')";
        if ($edit_id > 0) {
            $abbrCheckQuery .= " AND id != $edit_id";
        }
        $abbrCheckResult = mysqli_query($connection, $abbrCheckQuery);
        
        if (mysqli_num_rows($abbrCheckResult) > 0) {
            $message = "Error: College abbreviation '$abbreviation' already exists.";
        } else {
            if ($edit_id > 0) {
                // Update existing college
                $updateQuery = "UPDATE college SET name = '$abbreviation', full_name = '$full_name' WHERE id = $edit_id";
                if (mysqli_query($connection, $updateQuery)) {
                    $message = "College '$abbreviation' updated successfully.";
                    $edit_mode = false;
                } else {
                    $message = "Error updating college: " . mysqli_error($connection);
                }
            } else {
                // Insert new college
                $insertQuery = "INSERT INTO college (name, full_name) VALUES ('$abbreviation', '$full_name')";
                if (mysqli_query($connection, $insertQuery)) {
                    $new_college_id = mysqli_insert_id($connection);
                    $message = "College '$abbreviation' added successfully. <a href='college_page.php?id=$new_college_id'>View College</a>";
                    // Clear form inputs
                    $abbreviation = '';
                    $full_name = '';
                } else {
                    $message = "Error: Failed to add college. " . mysqli_error($connection);
                }
            }
            if (isset($abbrCheckResult)) {
                mysqli_free_result($abbrCheckResult);
            }
        }
    }
}

// Get all colleges to display
$colleges_query = "SELECT * FROM college ORDER BY name ASC";
$colleges_result = mysqli_query($connection, $colleges_query);

// Close connection at the end of the file
// mysqli_close($connection);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Colleges</title>
    <meta content="" name="description">
    <meta content="" name="keywords">
    <link href="assets/img/icon1.png" rel="icon">
    <link href="assets/img/icon1.png" rel="apple-touch-icon">
    <link href="https://fonts.gstatic.com" rel="preconnect">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <!-- Bootstrap CSS via CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <style>
        .college-card {
            border: 2px solid #28a745;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .college-card .card-header {
            background-color: #28a745;
            color: white;
            font-weight: bold;
        }
    </style>
</head>
<body>

<?php include './includes/header.php'; ?>
  <?php include './includes/menu.php'; ?>

  <main id="main" class="main" style="background-color:rgb(245, 245, 245);">


    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Colleges</h1>
            <div class="btn-group">
                <a href="#addCollegeForm" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Add New College
                </a>
                <button type="button" class="btn btn-danger ms-2" data-bs-toggle="modal" data-bs-target="#deleteAllModal">
                    <i class="bi bi-trash"></i> Delete All
                </button>
            </div>
        </div>

        <!-- Message Display -->
        <?php if ($message): ?>
            <div class="alert <?php echo strpos($message, 'Error') === false && strpos($message, 'failed') === false ? 'alert-success' : 'alert-danger'; ?> mb-4">
                <?php echo html_entity_decode($message); ?>
            </div>
        <?php endif; ?>

        <!-- List of Colleges -->
        <?php if (isset($colleges_result) && mysqli_num_rows($colleges_result) > 0): ?>
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Existing Colleges</h5>
                </div>
                <div class="list-group list-group-flush">
                    <?php while ($college = mysqli_fetch_assoc($colleges_result)): ?>
                        <a href="college_page.php?id=<?php echo $college['id']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div class="d-flex justify-content-between w-100">
                                <div>
                                    <span class="fw-bold"><?php echo htmlspecialchars($college['name']); ?></span>
                                    <small class="text-muted d-block"><?php echo htmlspecialchars($college['full_name']); ?></small>
                                </div>
                                <div class="btn-group">
                                    <a href="?action=edit&id=<?php echo $college['id']; ?>#addCollegeForm" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                    <a href="#" class="btn btn-sm btn-outline-danger" 
                                       onclick="return confirmDelete(<?php echo $college['id']; ?>, '<?php echo addslashes($college['name']); ?>')">
                                        <i class="bi bi-trash"></i> Delete
                                    </a>
                                </div>
                            </div>
                        </a>
                    <?php endwhile; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Add/Edit College Form -->
        <div class="card college-card mb-4" id="addCollegeForm">
            <div class="card-header"><?php echo $edit_mode ? 'Edit College' : 'Add New College'; ?></div>
            <div class="card-body">
                <form method="post">
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="edit_id" value="<?php echo $edit_id; ?>">
                    <?php endif; ?>
                    <div class="mb-3">
                        <label for="abbreviation" class="form-label">Abbreviation</label>
                        <input type="text" class="form-control" id="abbreviation" name="abbreviation" 
                               value="<?php echo $edit_mode ? htmlspecialchars($current_abbreviation) : (isset($_POST['abbreviation']) ? htmlspecialchars($_POST['abbreviation']) : ''); ?>" 
                               required 
                               placeholder="e.g., CBE"
                               <?php echo $edit_mode ? '' : ''; ?>>
                    </div>
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full College Name</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" 
                               value="<?php echo $edit_mode ? htmlspecialchars($current_full_name) : (isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''); ?>" 
                               required 
                               placeholder="e.g., College of Business and Economics">
                    </div>
                    <div class="d-flex gap-2">
                        <?php if ($edit_mode): ?>
                            <button type="submit" class="btn btn-primary" name="update_college">
                                <i class="bi bi-pencil-square"></i> Update College
                            </button>
                            <button type="submit" class="btn btn-secondary" name="cancel_edit">
                                <i class="bi bi-x-circle"></i> Cancel
                            </button>
                        <?php else: ?>
                            <button type="submit" class="btn btn-success" name="add_college">
                                <i class="bi bi-plus-circle"></i> Add College
                            </button>
                        <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    
    </main>
    
    <!-- Delete All Confirmation Modal -->
    <div class="modal fade" id="deleteAllModal" tabindex="-1" aria-labelledby="deleteAllModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteAllModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <strong>Warning!</strong> This action cannot be undone. All colleges will be permanently deleted.
                    </div>
                    <p>Are you sure you want to delete all colleges? This action will remove all college data from the system.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancel
                    </button>
                    <a href="?action=delete_all&confirm=true" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Yes, Delete All
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <?php include './includes/footer.php'; ?>
    <script>
        function confirmDelete(id, name) {
            if (confirm('Are you sure you want to delete ' + name + '? This action cannot be undone.')) {
                window.location.href = '?action=delete&id=' + id;
            }
            return false;
        }
        
        // Initialize Bootstrap tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
        
        // Auto-hide success message after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alert = document.querySelector('.alert-success');
            if (alert) {
                setTimeout(() => {
                    alert.style.transition = 'opacity 1s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 1000);
                }, 5000);
            }
        });
    </script>

<script src="assets/vendor/apexcharts/apexcharts.min.js"></script>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/vendor/chart.js/chart.umd.js"></script>
<script src="assets/vendor/echarts/echarts.min.js"></script>
<script src="assets/vendor/quill/quill.min.js"></script>
<script src="assets/vendor/simple-datatables/simple-datatables.js"></script>
<script src="assets/vendor/tinymce/tinymce.min.js"></script>
<script src="assets/vendor/php-email-form/validate.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>