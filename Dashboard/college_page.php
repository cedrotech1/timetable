<?php
session_start();
// Include database connection
include 'connection.php';

// Initialize variables
$college = null;
$allSchools = [];
$collegeSchools = [];
$message = '';

// Get college_id from URL
$college_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch college details
if ($college_id > 0) {
    $collegeQuery = "SELECT id, name FROM college WHERE id = '$college_id'";
    $collegeResult = mysqli_query($connection, $collegeQuery);
    if ($collegeResult && mysqli_num_rows($collegeResult) > 0) {
        $college = mysqli_fetch_assoc($collegeResult);
    }
    mysqli_free_result($collegeResult);
}

// Fetch all schools from all_schools
$allSchoolsQuery = "SELECT id, name FROM all_schools ORDER BY name";
$allSchoolsResult = mysqli_query($connection, $allSchoolsQuery);
if ($allSchoolsResult) {
    while ($row = mysqli_fetch_assoc($allSchoolsResult)) {
        $allSchools[] = $row;
    }
    mysqli_free_result($allSchoolsResult);
}

// Fetch schools associated with this college
$collegeSchoolsQuery = "SELECT id, name FROM school WHERE college_id = '$college_id' ORDER BY name";
$collegeSchoolsResult = mysqli_query($connection, $collegeSchoolsQuery);
if ($collegeSchoolsResult) {
    while ($row = mysqli_fetch_assoc($collegeSchoolsResult)) {
        $collegeSchools[] = $row;
    }
    mysqli_free_result($collegeSchoolsResult);
}

// Get names of schools already in this college for highlighting
$collegeSchoolNames = array_map('strtolower', array_column($collegeSchools, 'name'));

// Handle adding schools to the college
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['school_names']) && is_array($_POST['school_names'])) {
    $successCount = 0;
    $errorCount = 0;
    $duplicateCount = 0;

    foreach ($_POST['school_names'] as $school_name) {
        $school_name = mysqli_real_escape_string($connection, strtolower(trim($school_name)));
        
        // Check for duplicate
        $checkQuery = "SELECT id FROM school WHERE college_id = '$college_id' AND LOWER(name) = '$school_name'";
        $checkResult = mysqli_query($connection, $checkQuery);
        if (mysqli_num_rows($checkResult) == 0) {
            $insertQuery = "INSERT INTO school (name, college_id) VALUES ('$school_name', '$college_id')";
            if (mysqli_query($connection, $insertQuery)) {
                $successCount++;
            } else {
                $errorCount++;
            }
        } else {
            $duplicateCount++;
        }
        mysqli_free_result($checkResult);
    }

    if ($successCount > 0) {
        $message = "Added $successCount school(s) to college successfully.";
        if ($duplicateCount > 0) {
            $message .= " $duplicateCount school(s) were already assigned.";
        }
        if ($errorCount > 0) {
            $message .= " $errorCount school(s) failed to add.";
        }
        // Refresh college schools list
        $collegeSchools = [];
        $collegeSchoolsResult = mysqli_query($connection, $collegeSchoolsQuery);
        if ($collegeSchoolsResult) {
            while ($row = mysqli_fetch_assoc($collegeSchoolsResult)) {
                $collegeSchools[] = $row;
            }
            mysqli_free_result($collegeSchoolsResult);
        }
        $collegeSchoolNames = array_map('strtolower', array_column($collegeSchools, 'name'));
    } else {
        $message = "Error: No schools added. ";
        if ($duplicateCount > 0) {
            $message .= "$duplicateCount school(s) were already assigned.";
        }
        if ($errorCount > 0) {
            $message .= " $errorCount school(s) failed to add.";
        }
    }
}

// Handle deleting a school from the college
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = mysqli_real_escape_string($connection, $_POST['delete_id']);
    $deleteQuery = "DELETE FROM school WHERE id = '$delete_id' AND college_id = '$college_id'";
    if (mysqli_query($connection, $deleteQuery)) {
        $message = "School removed from college successfully.";
        // Refresh college schools list
        $collegeSchools = [];
        $collegeSchoolsResult = mysqli_query($connection, $collegeSchoolsQuery);
        if ($collegeSchoolsResult) {
            while ($row = mysqli_fetch_assoc($collegeSchoolsResult)) {
                $collegeSchools[] = $row;
            }
            mysqli_free_result($collegeSchoolsResult);
        }
        $collegeSchoolNames = array_map('strtolower', array_column($collegeSchools, 'name'));
    } else {
        $message = "Error: Failed to remove school from college.";
    }
}

// Close connection
// mysqli_close($connection); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>College Management</title>
      <!-- Bootstrap CSS via CDN -->
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/img/icon1.png" rel="icon">
    <link href="assets/img/icon1.png" rel="apple-touch-icon">
    
    <!-- Bootstrap & Styles -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <!-- Chart.js -->
    <!-- Bootstrap CSS via CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <style>
        .disabled-row {
            background-color: #f8f9fa;
            opacity: 0.6;
        }
        .disabled-row .form-check-input,
        .disabled-row .btn {
            cursor: not-allowed;
        }
        .college-card {
            border: 2px solid #007bff;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .college-card .card-header {
            background-color: #007bff;
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
        <h1 class="mb-4">College Management</h1>
        
        <!-- Message Display -->
        <?php if ($message): ?>
            <div class="alert <?php echo strpos($message, 'Error') === false && strpos($message, 'failed') === false ? 'alert-success' : 'alert-danger'; ?> mb-4">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- College Details and Schools -->
        <div class="card mb-4 college-card">
            <div class="card-header">College Details</div>
            <div class="card-body">
                <?php if ($college): ?>
                    <h5 class="mb-3">College: <?php echo htmlspecialchars($college['name']); ?></h5>
                    <h6 class="mb-3">Schools in this College</h6>
                    <?php if (empty($collegeSchools)): ?>
                        <p>No schools assigned to this college.</p>
                    <?php else: ?>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>School Name</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($collegeSchools as $school): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($school['id']); ?></td>
                                        <td><?php echo htmlspecialchars($school['name']); ?></td>
                                        <td>
                                            <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to remove <?php echo htmlspecialchars($school['name']); ?> from this college?');">
                                                <input type="hidden" name="delete_id" value="<?php echo htmlspecialchars($school['id']); ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-danger">College not found.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($college): ?>
            <!-- Add Schools to College -->
            <div class="card mb-4">
                <div class="card-header">Add Schools to College</div>
                <div class="card-body">
                    <form method="post">
                        <?php if (empty($allSchools)): ?>
                            <p>No schools available to add.</p>
                        <?php else: ?>
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Select</th>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allSchools as $school): ?>
                                        <?php $isAssigned = in_array(strtolower($school['name']), $collegeSchoolNames); ?>
                                        <tr class="<?php echo $isAssigned ? 'disabled-row' : ''; ?>">
                                            <td>
                                                <input type="checkbox" 
                                                       class="form-check-input" 
                                                       name="school_names[]" 
                                                       value="<?php echo htmlspecialchars($school['name']); ?>" 
                                                       <?php echo $isAssigned ? 'disabled' : ''; ?>>
                                            </td>
                                            <td><?php echo htmlspecialchars($school['id']); ?></td>
                                            <td><?php echo htmlspecialchars($school['name']); ?></td>
                                            <td>
                                                <button type="submit" 
                                                        class="btn btn-sm btn-primary" 
                                                        name="school_names[]" 
                                                        value="<?php echo htmlspecialchars($school['name']); ?>"
                                                        <?php echo $isAssigned ? 'disabled' : ''; ?>>
                                                    Add to College
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <button type="submit" class="btn btn-success mt-3">Add Selected Schools</button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    </main>
    
    <!-- Vendor JS Files -->
<script src="assets/vendor/apexcharts/apexcharts.min.js"></script>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/vendor/chart.js/chart.umd.js"></script>
<script src="assets/vendor/echarts/echarts.min.js"></script>
<script src="assets/vendor/quill/quill.min.js"></script>
<script src="assets/vendor/simple-datatables/simple-datatables.js"></script>
<script src="assets/vendor/tinymce/tinymce.min.js"></script>
<script src="assets/vendor/php-email-form/validate.js"></script>

    <!-- Bootstrap JS via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>