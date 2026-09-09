<?php
session_start();
// Include database connection
include 'connection.php';

// Include header
include 'includes/header.php';

// Add custom CSS for this page
echo '<style>
:root {
    --bs-primary: #4e73df;
    --bs-success: #1cc88a;
    --bs-info: #36b9cc;
    --bs-warning: #f6c23e;
    --bs-danger: #e74a3b;
    --bs-light: #f8f9fc;
    --bs-dark: #5a5c69;
}

body {
    background-color: #f8f9fc;
    color: #4a4b65;
}

.card {
    border: none;
    border-radius: 0.5rem;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    margin-bottom: 1.5rem;
    overflow: hidden;
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1) !important;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.15) !important;
}

.card-header {
    font-weight: 600;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    background-color: #f8f9fc;
    padding: 1rem 1.25rem;
}

.btn {
    border-radius: 0.35rem;
    font-weight: 500;
    padding: 0.5rem 1rem;
    transition: all 0.2s;
}

.table {
    margin-bottom: 0;
}

.table thead th {
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.7rem;
    letter-spacing: 0.5px;
    color: #6e707e;
    background-color: #f8f9fc;
    border-bottom: 1px solid #e3e6f0;
}

.table > :not(:first-child) {
    border-top: 0;
}

.badge {
    font-weight: 500;
    padding: 0.35em 0.65em;
    font-size: 0.75em;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .card {
        border-radius: 0;
        margin-left: -1rem;
        margin-right: -1rem;
        width: calc(100% + 2rem);
    }
}
</style>';

// Add Font Awesome for icons
echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';

// Initialize variables
$school = null;
$college_name = '';
$allPrograms = [];
$schoolPrograms = [];
$schoolDepartments = [];
$message = '';

// Get school_id from URL
$school_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch school details and associated college name
if ($school_id > 0) {
    $schoolQuery = "SELECT s.id, s.name, s.college_id, c.name AS college_name 
                    FROM school s 
                    JOIN college c ON s.college_id = c.id 
                    WHERE s.id = '$school_id'";
    $schoolResult = mysqli_query($connection, $schoolQuery);
    if ($schoolResult && mysqli_num_rows($schoolResult) > 0) {
        $school = mysqli_fetch_assoc($schoolResult);
        $college_name = $school['college_name'];
    }
    mysqli_free_result($schoolResult);
}

// Fetch all programs from all_programs
$allProgramsQuery = "SELECT id, name FROM all_programs ORDER BY name";
$allProgramsResult = mysqli_query($connection, $allProgramsQuery);
if ($allProgramsResult) {
    while ($row = mysqli_fetch_assoc($allProgramsResult)) {
        $allPrograms[] = $row;
    }
    mysqli_free_result($allProgramsResult);
}

// Fetch programs associated with this school
$schoolProgramsQuery = "SELECT p.id, p.name, p.department_id, d.name AS department_name 
                       FROM program p 
                       LEFT JOIN department d ON p.department_id = d.id 
                       WHERE p.school_id = '$school_id' ORDER BY p.name";
$schoolProgramsResult = mysqli_query($connection, $schoolProgramsQuery);
if ($schoolProgramsResult) {
    while ($row = mysqli_fetch_assoc($schoolProgramsResult)) {
        $schoolPrograms[] = $row;
    }
    mysqli_free_result($schoolProgramsResult);
}

// Fetch departments for this school
$schoolDepartmentsQuery = "SELECT id, name FROM department WHERE school_id = '$school_id' ORDER BY name";
$schoolDepartmentsResult = mysqli_query($connection, $schoolDepartmentsQuery);
if ($schoolDepartmentsResult) {
    while ($row = mysqli_fetch_assoc($schoolDepartmentsResult)) {
        $schoolDepartments[] = $row;
    }
    mysqli_free_result($schoolDepartmentsResult);
}

// Get names of programs already in this school for highlighting
$schoolProgramNames = array_map('strtolower', array_column($schoolPrograms, 'name'));

// Get names of programs assigned to any school to prevent duplicates
$assignedProgramNames = [];
$assignedProgramsQuery = "SELECT DISTINCT LOWER(name) AS name FROM program WHERE name IS NOT NULL";
$assignedProgramsResult = mysqli_query($connection, $assignedProgramsQuery);
if ($assignedProgramsResult) {
    while ($row = mysqli_fetch_assoc($assignedProgramsResult)) {
        if (!empty($row['name'])) {
            $assignedProgramNames[] = $row['name'];
        }
    }
    mysqli_free_result($assignedProgramsResult);
}

// Handle manually adding a new program
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_new_program'])) {
    $program_name = mysqli_real_escape_string($connection, trim($_POST['new_program_name']));
    $department_id = isset($_POST['new_program_department_id']) && $_POST['new_program_department_id'] !== '' ? intval($_POST['new_program_department_id']) : null;
    
    if (!empty($program_name)) {
        // Check if program already exists in this school
        $checkQuery = "SELECT id FROM program WHERE school_id = '$school_id' AND LOWER(name) = LOWER('$program_name')";
        $checkResult = mysqli_query($connection, $checkQuery);
        
        if (mysqli_num_rows($checkResult) == 0) {
            $departmentValue = $department_id ? "'$department_id'" : 'NULL';
            $insertQuery = "INSERT INTO program (name, code, department_id, school_id) VALUES ('$program_name', NULL, $departmentValue, '$school_id')";
            
            if (mysqli_query($connection, $insertQuery)) {
                $message = "Program '$program_name' added successfully.";
                // Refresh school programs list
                $schoolPrograms = [];
                $schoolProgramsResult = mysqli_query($connection, $schoolProgramsQuery);
                if ($schoolProgramsResult) {
                    while ($row = mysqli_fetch_assoc($schoolProgramsResult)) {
                        $schoolPrograms[] = $row;
                    }
                    mysqli_free_result($schoolProgramsResult);
                }
                $schoolProgramNames = array_map('strtolower', array_column($schoolPrograms, 'name'));
            } else {
                $message = "Error: Failed to add program.";
            }
        } else {
            $message = "Error: Program '$program_name' already exists in this school.";
        }
        mysqli_free_result($checkResult);
    } else {
        $message = "Error: Program name cannot be empty.";
    }
}

// Handle adding programs to the school
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['program_names']) && is_array($_POST['program_names'])) {
    $successCount = 0;
    $errorCount = 0;
    $duplicateCount = 0;
    $department_id = isset($_POST['department_id']) && $_POST['department_id'] !== '' ? intval($_POST['department_id']) : null;

    foreach ($_POST['program_names'] as $program_name) {
        $program_name = mysqli_real_escape_string($connection, strtolower(trim($program_name)));
        
        // Check for duplicate
        $checkQuery = "SELECT id FROM program WHERE school_id = '$school_id' AND LOWER(name) = '$program_name'";
        $checkResult = mysqli_query($connection, $checkQuery);
        if (mysqli_num_rows($checkResult) == 0) {
            $departmentValue = $department_id ? "'$department_id'" : 'NULL';
            $insertQuery = "INSERT INTO program (name, code, department_id, school_id) VALUES ('$program_name', NULL, $departmentValue, '$school_id')";
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
        $message = "Added $successCount program(s) to school successfully.";
        if ($duplicateCount > 0) {
            $message .= " $duplicateCount program(s) were already assigned.";
        }
        if ($errorCount > 0) {
            $message .= " $errorCount program(s) failed to add.";
        }
        // Refresh school programs list
        $schoolPrograms = [];
        $schoolProgramsResult = mysqli_query($connection, $schoolProgramsQuery);
        if ($schoolProgramsResult) {
            while ($row = mysqli_fetch_assoc($schoolProgramsResult)) {
                $schoolPrograms[] = $row;
            }
            mysqli_free_result($schoolProgramsResult);
        }
        $schoolProgramNames = array_map('strtolower', array_column($schoolPrograms, 'name'));
    } else {
        $message = "Error: No programs added. ";
        if ($duplicateCount > 0) {
            $message .= "$duplicateCount program(s) were already assigned.";
        }
        if ($errorCount > 0) {
            $message .= " $errorCount program(s) failed to add.";
        }
    }
}

// Handle editing program department assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_program_department'])) {
    $program_id = intval($_POST['program_id']);
    $department_id = isset($_POST['program_department_id']) && $_POST['program_department_id'] !== '' ? intval($_POST['program_department_id']) : null;
    $departmentValue = $department_id ? "'$department_id'" : 'NULL';
    $updateQuery = "UPDATE program SET department_id = $departmentValue WHERE id = '$program_id' AND school_id = '$school_id'";
    if (mysqli_query($connection, $updateQuery)) {
        $message = "Program department updated successfully.";
        // Refresh school programs list
        $schoolPrograms = [];
        $schoolProgramsResult = mysqli_query($connection, $schoolProgramsQuery);
        if ($schoolProgramsResult) {
            while ($row = mysqli_fetch_assoc($schoolProgramsResult)) {
                $schoolPrograms[] = $row;
            }
            mysqli_free_result($schoolProgramsResult);
        }
        $schoolProgramNames = array_map('strtolower', array_column($schoolPrograms, 'name'));
    } else {
        $message = "Error: Failed to update program department.";
    }
}

// Handle adding a department
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_department'])) {
    $department_name = mysqli_real_escape_string($connection, strtolower(trim($_POST['department_name'])));
    if (!empty($department_name)) {
        $checkQuery = "SELECT id FROM department WHERE school_id = '$school_id' AND LOWER(name) = '$department_name'";
        $checkResult = mysqli_query($connection, $checkQuery);
        if (mysqli_num_rows($checkResult) == 0) {
            $insertQuery = "INSERT INTO department (name, school_id) VALUES ('$department_name', '$school_id')";
            if (mysqli_query($connection, $insertQuery)) {
                $message = "Department '$department_name' added successfully.";
                // Refresh departments list
                $schoolDepartments = [];
                $schoolDepartmentsResult = mysqli_query($connection, $schoolDepartmentsQuery);
                if ($schoolDepartmentsResult) {
                    while ($row = mysqli_fetch_assoc($schoolDepartmentsResult)) {
                        $schoolDepartments[] = $row;
                    }
                    mysqli_free_result($schoolDepartmentsResult);
                }
            } else {
                $message = "Error: Failed to add department.";
            }
        } else {
            $message = "Error: Department '$department_name' already exists.";
        }
        mysqli_free_result($checkResult);
    } else {
        $message = "Error: Department name cannot be empty.";
    }
}

// Handle editing a department
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_department'])) {
    $department_id = intval($_POST['department_id']);
    $department_name = mysqli_real_escape_string($connection, strtolower(trim($_POST['department_name'])));
    if (!empty($department_name)) {
        $checkQuery = "SELECT id FROM department WHERE school_id = '$school_id' AND LOWER(name) = '$department_name' AND id != '$department_id'";
        $checkResult = mysqli_query($connection, $checkQuery);
        if (mysqli_num_rows($checkResult) == 0) {
            $updateQuery = "UPDATE department SET name = '$department_name' WHERE id = '$department_id' AND school_id = '$school_id'";
            if (mysqli_query($connection, $updateQuery)) {
                $message = "Department updated successfully.";
                // Refresh departments and programs lists
                $schoolDepartments = [];
                $schoolDepartmentsResult = mysqli_query($connection, $schoolDepartmentsQuery);
                if ($schoolDepartmentsResult) {
                    while ($row = mysqli_fetch_assoc($schoolDepartmentsResult)) {
                        $schoolDepartments[] = $row;
                    }
                    mysqli_free_result($schoolDepartmentsResult);
                }
                $schoolPrograms = [];
                $schoolProgramsResult = mysqli_query($connection, $schoolProgramsQuery);
                if ($schoolProgramsResult) {
                    while ($row = mysqli_fetch_assoc($schoolProgramsResult)) {
                        $schoolPrograms[] = $row;
                    }
                    mysqli_free_result($schoolProgramsResult);
                }
                $schoolProgramNames = array_map('strtolower', array_column($schoolPrograms, 'name'));
            } else {
                $message = "Error: Failed to update department.";
            }
        } else {
            $message = "Error: Department '$department_name' already exists.";
        }
        mysqli_free_result($checkResult);
    } else {
        $message = "Error: Department name cannot be empty.";
    }
}

// Handle deleting a department
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_department'])) {
    $department_id = intval($_POST['delete_department']);
    
    // Explicitly set department_id to NULL for associated programs
    $nullifyQuery = "UPDATE program SET department_id = NULL WHERE department_id = '$department_id' AND school_id = '$school_id'";
    if (mysqli_query($connection, $nullifyQuery)) {
        $deleteQuery = "DELETE FROM department WHERE id = '$department_id' AND school_id = '$school_id'";
        if (mysqli_query($connection, $deleteQuery)) {
            $message = "Department deleted successfully. Associated programs have been unassigned.";
            // Refresh departments and programs lists
            $schoolDepartments = [];
            $schoolDepartmentsResult = mysqli_query($connection, $schoolDepartmentsQuery);
            if ($schoolDepartmentsResult) {
                while ($row = mysqli_fetch_assoc($schoolDepartmentsResult)) {
                    $schoolDepartments[] = $row;
                }
                mysqli_free_result($schoolDepartmentsResult);
            }
            $schoolPrograms = [];
            $schoolProgramsResult = mysqli_query($connection, $schoolProgramsQuery);
            if ($schoolProgramsResult) {
                while ($row = mysqli_fetch_assoc($schoolProgramsResult)) {
                    $schoolPrograms[] = $row;
                }
                mysqli_free_result($schoolProgramsResult);
            }
            $schoolProgramNames = array_map('strtolower', array_column($schoolPrograms, 'name'));
        } else {
            $message = "Error: Failed to delete department.";
        }
    } else {
        $message = "Error: Failed to unassign programs from department.";
    }
}

// Handle deleting a program from the school
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_program'])) {
    $delete_id = intval($_POST['delete_program']);
    $deleteQuery = "DELETE FROM program WHERE id = '$delete_id' AND school_id = '$school_id'";
    if (mysqli_query($connection, $deleteQuery)) {
        $message = "Program removed from school successfully.";
        // Refresh school programs list
        $schoolPrograms = [];
        $schoolProgramsResult = mysqli_query($connection, $schoolProgramsQuery);
        if ($schoolProgramsResult) {
            while ($row = mysqli_fetch_assoc($schoolProgramsResult)) {
                $schoolPrograms[] = $row;
            }
            mysqli_free_result($schoolProgramsResult);
        }
        $schoolProgramNames = array_map('strtolower', array_column($schoolPrograms, 'name'));
    } else {
        $message = "Error: Failed to remove program from school.";
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
    <title>School Management</title>
    <!-- Bootstrap CSS via CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/img/icon1.png" rel="icon">
    <link href="assets/img/icon1.png" rel="apple-touch-icon">
    
    <!-- Bootstrap & Styles -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .school-card {
            border: 2px solid #28a745;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .school-card .card-header {
            background-color: #28a745;
            color: white;
            font-weight: bold;
        }
        .program-link {
            color: #007bff;
            text-decoration: none;
        }
        .program-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body style="background-color:rgb(245, 245, 245);">

<?php include './includes/header.php'; ?>
<?php include './includes/menu.php'; ?>
<main id="main" class="main" style="background-color:rgb(245, 245, 245);">
<!-- main content -->
<div class="">
        <h1 class="mb-4">School Management</h1>
        
        <!-- Message Display -->
        <?php if ($message): ?>
            <div class="alert <?php echo strpos($message, 'Error') === false && strpos($message, 'failed') === false ? 'alert-success' : 'alert-danger'; ?> mb-4">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- School Details and Programs -->
        <div class="card mb-4 school-card">
            <div class="card-header">School Details</div>
            <div class="card-body">
                <?php if ($school): ?>
                    <div class="d-flex align-items-center mb-3">
                        <h5 class="mb-0 me-2">
                            <span id="schoolNameDisplay"><?php echo htmlspecialchars($school['name']); ?></span>
                            <span class="text-muted">(ID: <?php echo htmlspecialchars($school['id']); ?>)</span>
                            <button type="button" class="btn btn-sm btn-link p-0 ms-2" id="editSchoolNameBtn" title="Edit school name">
                                <i class="fas fa-edit"></i>
                            </button>
                        </h5>
                        <div class="input-group input-group-sm ms-2" id="schoolNameEdit" style="display: none; width: auto;">
                            <input type="text" class="form-control form-control-sm" id="schoolNameInput" value="<?php echo htmlspecialchars($school['name']); ?>">
                            <button class="btn btn-success btn-sm" type="button" id="saveSchoolNameBtn">
                                <i class="fas fa-check"></i>
                            </button>
                            <button class="btn btn-danger btn-sm" type="button" id="cancelSchoolNameBtn">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <small>College: <?php echo htmlspecialchars($college_name); ?></small></h5>
                    <h6 class="mb-3">Programs in this School</h6>
                    <?php if (empty($schoolPrograms)): ?>
                        <p>No programs assigned to this school.</p>
                    <?php else: ?>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Program Name</th>
                                    <th>Department</th>
                                    <?php
                        if($_SESSION['role']=="dean_office"){

                        }else{
                            ?>
                             <th>action</th>
                            <?php
                        }
                        ?>
                                    
                                   
                                </tr>
                            </thead>
                            <tbody>
                                <?php $counter = 1; ?>
                                <?php foreach ($schoolPrograms as $program): ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td>
                                            <a href="program_management.php?program_id=<?php echo htmlspecialchars($program['id']); ?>&program_name=<?php echo urlencode($program['name']); ?>" class="program-link">
                                                <?php echo htmlspecialchars($program['name']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo $program['department_name'] ? htmlspecialchars($program['department_name']) : 'None'; ?></td>
                                        <?php
                        if($_SESSION['role']=="dean_office"){

                        }else{
                            ?>
                            
                            <td>
                                            <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editProgramModal<?php echo $program['id']; ?>">
                                                Edit Department
                                            </button>
                                            <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to remove <?php echo htmlspecialchars($program['name']); ?> from this school?');">
                                                <input type="hidden" name="delete_program" value="<?php echo htmlspecialchars($program['id']); ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                                            </form>
                                        </td>

                            <?php


                        }
                        ?>
                                    </tr>

                                    <!-- Edit Program Department Modal -->
                                    <div class="modal fade" id="editProgramModal<?php echo $program['id']; ?>" tabindex="-1" aria-labelledby="editProgramModalLabel<?php echo $program['id']; ?>" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="editProgramModalLabel<?php echo $program['id']; ?>">Edit Department for <?php echo htmlspecialchars($program['name']); ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form method="post">
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label for="program_department_id_<?php echo $program['id']; ?>" class="form-label">Assign to Department</label>
                                                            <select class="form-select" id="program_department_id_<?php echo $program['id']; ?>" name="program_department_id">
                                                                <option value="">-- None --</option>
                                                                <?php foreach ($schoolDepartments as $department): ?>
                                                                    <option value="<?php echo htmlspecialchars($department['id']); ?>" <?php echo $program['department_id'] == $department['id'] ? 'selected' : ''; ?>>
                                                                        <?php echo htmlspecialchars($department['name']); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <input type="hidden" name="program_id" value="<?php echo htmlspecialchars($program['id']); ?>">
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <button type="submit" class="btn btn-primary" name="edit_program_department">Save Changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-danger">School not found.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($school): ?>
            <!-- Add New Program Manually -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">Add New Program Manually</div>
                <div class="card-body">
                    <form method="post" class="row g-3">
                        <div class="col-md-6">
                            <label for="new_program_name" class="form-label">Program Name</label>
                            <input type="text" class="form-control" id="new_program_name" name="new_program_name" placeholder="Enter program name" required>
                        </div>
                        <div class="col-md-4">
                            <label for="new_program_department_id" class="form-label">Department (Optional)</label>
                            <select class="form-select" id="new_program_department_id" name="new_program_department_id">
                                <option value="">-- None --</option>
                                <?php foreach ($schoolDepartments as $department): ?>
                                    <option value="<?php echo htmlspecialchars($department['id']); ?>">
                                        <?php echo htmlspecialchars($department['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" name="add_new_program" class="btn btn-success w-100">
                                <i class="bi bi-plus-circle"></i> Add Program
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Add Programs to School -->
            <div class="card mb-4">
                <div class="card-header">Add Programs from Existing List</div>
                <div class="card-body">
                    <form method="post">
                        <div class="mb-3">
                            <label for="department_id" class="form-label">Assign to Department (Optional)</label>
                            <select class="form-select" id="department_id" name="department_id">
                                <option value="">-- None --</option>
                                <?php foreach ($schoolDepartments as $department): ?>
                                    <option value="<?php echo htmlspecialchars($department['id']); ?>">
                                        <?php echo htmlspecialchars($department['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if (empty($allPrograms)): ?>
                            <p>No programs available to add.</p>
                        <?php else: ?>
                            <!-- Search Box -->
                            <div class="mb-3">
                                <input type="text" id="programSearch" class="form-control" placeholder="Search programs..." onkeyup="filterPrograms()">
                            </div>
                            
                            <table class="table table-striped" id="programsTable">
                                <thead>
                                    <tr>
                                        <th>Select</th>
                                        <th>No</th>
                                        <th>Name</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $counter = 1; ?>
                                    <?php foreach ($allPrograms as $program): ?>
                                        <?php 
                                        $programNameLower = strtolower($program['name']);
                                        $isAssignedToSchool = in_array($programNameLower, $schoolProgramNames);
                                        $isAssignedAnywhere = in_array($programNameLower, $assignedProgramNames);
                                    ?>
                                        <tr class="<?php echo $isAssignedAnywhere ? 'disabled-row' : ''; ?>">
                                            <td>
                                                <input type="checkbox" 
                                                       class="form-check-input" 
                                                       name="program_names[]" 
                                                       value="<?php echo htmlspecialchars($program['name']); ?>" 
                                                       <?php echo $isAssignedAnywhere ? 'disabled' : ''; ?>>
                                            </td>
                                            <td><?php echo $counter++; ?></td>
                                            <td><?php echo htmlspecialchars($program['name']); ?></td>
                                            <td>
                                                <button type="submit" 
                                                        class="btn btn-sm btn-primary" 
                                                        name="program_names[]" 
                                                        value="<?php echo htmlspecialchars($program['name']); ?>"
                                                        <?php echo $isAssignedAnywhere ? 'disabled' : ''; ?>>
                                                    Add to School
                                                </button>
                                                <?php if ($isAssignedAnywhere): ?>
                                                    <small class="text-muted ms-2">
                                                        <?php echo $isAssignedToSchool ? 'Already assigned to this school' : 'Assigned to another school'; ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <button type="submit" class="btn btn-success mt-3">Add Selected Programs</button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Manage Departments --> 
            <div class="card mb-4">
                <div class="card-header">Manage Departments</div>
                <div class="card-body">
                    <!-- Add Department Form -->
                    <h6 class="mb-3">Add New Department</h6>
                    <form method="post" class="mb-4">
                        <div class="input-group">
                            <input type="text" class="form-control" name="department_name" placeholder="Enter department name" required>
                            <button type="submit" class="btn btn-primary" name="add_department">Add Department</button>
                        </div>
                    </form>

                    <!-- Departments Table -->
                    <?php if (empty($schoolDepartments)): ?>
                        <p>No departments in this school.</p>
                    <?php else: ?>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Name</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $counter = 1; ?>
                                <?php foreach ($schoolDepartments as $department): ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td><?php echo htmlspecialchars($department['name']); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editDepartmentModal<?php echo $department['id']; ?>">
                                                Edit
                                            </button>
                                            <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete <?php echo htmlspecialchars($department['name']); ?>? This will unassign associated programs.');">
                                                <input type="hidden" name="delete_department" value="<?php echo htmlspecialchars($department['id']); ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                            </form>
                                        </td>
                                    </tr>

                                    <!-- Edit Department Modal -->
                                    <div class="modal fade" id="editDepartmentModal<?php echo $department['id']; ?>" tabindex="-1" aria-labelledby="editDepartmentModalLabel<?php echo $department['id']; ?>" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="editDepartmentModalLabel<?php echo $department['id']; ?>">Edit Department</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form method="post">
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label for="department_name_<?php echo $department['id']; ?>" class="form-label">Department Name</label>
                                                            <input type="text" class="form-control" id="department_name_<?php echo $department['id']; ?>" name="department_name" value="<?php echo htmlspecialchars($department['name']); ?>" required>
                                                        </div>
                                                        <input type="hidden" name="department_id" value="<?php echo htmlspecialchars($department['id']); ?>">
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <button type="submit" class="btn btn-primary" name="edit_department">Save Changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    </main>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const editBtn = document.getElementById('editSchoolNameBtn');
        const saveBtn = document.getElementById('saveSchoolNameBtn');
        const cancelBtn = document.getElementById('cancelSchoolNameBtn');
        const displayElement = document.getElementById('schoolNameDisplay');
        const editElement = document.getElementById('schoolNameEdit');
        const nameInput = document.getElementById('schoolNameInput');
        const schoolId = <?php echo $school_id; ?>;

        editBtn.addEventListener('click', function() {
            displayElement.style.display = 'none';
            editElement.style.display = 'flex';
            editBtn.style.display = 'none';
            nameInput.focus();
        });

        function hideEdit() {
            displayElement.style.display = 'inline';
            editElement.style.display = 'none';
            editBtn.style.display = 'inline';
        }

        cancelBtn.addEventListener('click', function() {
            nameInput.value = displayElement.textContent.trim();
            hideEdit();
        });

        saveBtn.addEventListener('click', function() {
            const newName = nameInput.value.trim();
            if (newName === '') {
                alert('School name cannot be empty');
                return;
            }

            // Send AJAX request to update school name
            const formData = new FormData();
            formData.append('update_school_name', '1');
            formData.append('school_id', schoolId);
            formData.append('new_name', newName);

            fetch('update_school.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayElement.textContent = newName;
                    hideEdit();
                    // Show success message
                    const messageDiv = document.createElement('div');
                    messageDiv.className = 'alert alert-success alert-dismissible fade show';
                    messageDiv.innerHTML = `
                        ${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    `;
                    const container = document.querySelector('.container.mt-5');
                    container.insertBefore(messageDiv, container.firstChild);
                    
                    // Auto-hide message after 3 seconds
                    setTimeout(() => {
                        messageDiv.classList.remove('show');
                        setTimeout(() => messageDiv.remove(), 150);
                    }, 3000);
                } else {
                    alert('Error: ' + (data.message || 'Failed to update school name'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the school name');
            });
        });

        // Handle Enter/Esc keys in the input field
        nameInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                saveBtn.click();
            } else if (e.key === 'Escape') {
                cancelBtn.click();
            }
        });
    });
    </script>

    <!-- Bootstrap JS via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    
    <script>
        // Search/filter programs in the table
        function filterPrograms() {
            const input = document.getElementById('programSearch');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('programsTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const nameCell = rows[i].getElementsByTagName('td')[2]; // Name column
                if (nameCell) {
                    const textValue = nameCell.textContent || nameCell.innerText;
                    if (textValue.toLowerCase().indexOf(filter) > -1) {
                        rows[i].style.display = '';
                    } else {
                        rows[i].style.display = 'none';
                    }
                }
            }
        }
    </script>
    <script src="assets/vendor/apexcharts/apexcharts.min.js"></script>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/vendor/chart.js/chart.umd.js"></script>
<script src="assets/vendor/echarts/echarts.min.js"></script>
<script src="assets/vendor/quill/quill.min.js"></script>
<script src="assets/vendor/simple-datatables/simple-datatables.js"></script>
<script src="assets/vendor/tinymce/tinymce.min.js"></script>
<script src="assets/vendor/php-email-form/validate.js"></script>
  </body>

</html> 