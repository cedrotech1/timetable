<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include('connection.php');

// Get program ID from URL
$program_id = isset($_GET['program_id']) ? intval($_GET['program_id']) : 0;
if (!$program_id) {
    header('Location: program_management.php');
    exit();
}

// Get program details
$program_query = "SELECT * FROM program WHERE id = $program_id";
$program_result = mysqli_query($connection, $program_query);
$program = mysqli_fetch_assoc($program_result);

if (!$program) {
    header('Location: program_management.php');
    exit();
}

// Get existing modules for this program
$modules_query = "SELECT * FROM module WHERE program_id = $program_id ORDER BY year, semester, code";
$modules_result = mysqli_query($connection, $modules_query);
$modules = [];
while ($row = mysqli_fetch_assoc($modules_result)) {
    $modules[] = $row;
}

// Handle form submission
$message = '';
$message_type = '';

// Handle single module form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_single_module'])) {
    $name = mysqli_real_escape_string($connection, $_POST['name']);
    $code = mysqli_real_escape_string($connection, $_POST['code']);
    $credits = intval($_POST['credits']);
    $year = intval($_POST['year']);
    $semester = intval($_POST['semester']);
    
    // Check if module code already exists for this program
    $check_query = "SELECT id FROM module WHERE code = '$code' AND program_id = $program_id";
    $check_result = mysqli_query($connection, $check_query);
    
    if (mysqli_num_rows($check_result) > 0) {
        $message = 'A module with this code already exists in this program';
        $message_type = 'danger';
    } else {
        // Insert new module
        $insert_query = "INSERT INTO module (name, code, credits, year, semester, program_id) 
                        VALUES ('$name', '$code', $credits, $year, $semester, $program_id)";
        
        if (mysqli_query($connection, $insert_query)) {
            $message = 'Module added successfully';
            $message_type = 'success';
            // Refresh the page to show the new module
            header("Location: program_modules.php?program_id=$program_id");
            exit();
        } else {
            $message = 'Error adding module: ' . mysqli_error($connection);
            $message_type = 'danger';
        }
    }
}

// Handle delete selected modules
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_selected']) && !empty($_POST['selected_modules'])) {
    // Sanitize the module IDs
    $selected_modules = array_map('intval', $_POST['selected_modules']);
    $module_ids = implode(',', $selected_modules);
    
    // Delete selected modules
    $delete_query = "DELETE FROM module WHERE id IN ($module_ids) AND program_id = $program_id";
    
    if (mysqli_query($connection, $delete_query)) {
        $message = 'Selected modules have been deleted successfully';
        $message_type = 'success';
        // Refresh the page to show the changes
        header("Location: program_modules.php?program_id=$program_id");
        exit();
    } else {
        $message = 'Error deleting selected modules: ' . mysqli_error($connection);
        $message_type = 'danger';
    }
}

// Handle delete all modules
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_all_modules'])) {
    // Delete all modules for this program
    $delete_query = "DELETE FROM module WHERE program_id = $program_id";
    
    if (mysqli_query($connection, $delete_query)) {
        $message = 'All modules have been deleted successfully';
        $message_type = 'success';
        // Refresh the page to show the changes
        header("Location: program_modules.php?program_id=$program_id");
        exit();
    } else {
        $message = 'Error deleting modules: ' . mysqli_error($connection);
        $message_type = 'danger';
    }
}

// Handle CSV file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Check if file is a CSV file
    if ($file_ext !== 'csv') {
        $message = 'Only CSV files are allowed. Please use the template provided.';
        $message_type = 'danger';
    } elseif ($file['error'] === UPLOAD_ERR_OK) {
        $handle = fopen($file['tmp_name'], 'r');
        
        if ($handle !== FALSE) {
            // Skip header row
            fgetcsv($handle);
            
            $success_count = 0;
            $error_messages = [];
            $row_num = 1; // Start from 1 since we skipped header
            
            while (($data = fgetcsv($handle)) !== FALSE) {
                $row_num++;
                
                // Skip empty rows
                if (empty(array_filter($data))) continue;
                
                $code = mysqli_real_escape_string($connection, $data[0] ?? '');
                $name = mysqli_real_escape_string($connection, $data[1] ?? '');
                $credits = intval($data[2] ?? 0);
                $year = intval($data[3] ?? 1);
                $semester = intval($data[4] ?? 1);
                
                if (empty($code) || empty($name)) {
                    $error_messages[] = "Row $row_num: Missing required fields (Code and Name are required)";
                    continue;
                }
                
                // Check if module code already exists for this program
                $check_query = "SELECT id FROM module WHERE code = '$code' AND program_id = $program_id";
                $check_result = mysqli_query($connection, $check_query);
                
                if (mysqli_num_rows($check_result) > 0) {
                    $error_messages[] = "Row $row_num: Module with code '$code' already exists";
                    continue;
                }
                
                // Insert new module
                $insert_query = "INSERT INTO module (name, code, credits, year, semester, program_id) 
                                VALUES ('$name', '$code', $credits, $year, $semester, $program_id)";
                
                if (mysqli_query($connection, $insert_query)) {
                    $success_count++;
                } else {
                    $error_messages[] = "Row $row_num: Error adding module '$code': " . mysqli_error($connection);
                }
            }
            
            fclose($handle);
            
            // Prepare success/error message
            $message = "Successfully imported $success_count modules.";
            $message_type = 'success';
            
            if (!empty($error_messages)) {
                $message .= ' ' . count($error_messages) . ' errors occurred.';
                $_SESSION['import_errors'] = $error_messages;
            }
            
            // Refresh the page to show the new modules
            header("Location: program_modules.php?program_id=$program_id");
            exit();
        } else {
            $message = 'Error reading the uploaded file.';
            $message_type = 'danger';
        }
    } else {
        $message = 'Error uploading file. Error code: ' . $file['error'];
        $message_type = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title><?= htmlspecialchars($program['name']) ?> - Modules</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="assets/img/icon1.png" rel="icon" />

    <link href="assets/img/icon1.png" rel="apple-touch-icon">
    <link href="https://fonts.gstatic.com" rel="preconnect">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet" />
    <style>
        .module-card {
            transition: transform 0.2s;
        }
        .module-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
        }
    </style>
</head> 
<body style="background-color: #f5f5f5;">
    <?php include('./includes/header.php'); ?>
    <?php include('./includes/menu.php'); ?>

    <main id="main" class="main">
        <div class="p-4">
            <div class="pagetitle">
                <h1><?= htmlspecialchars($program['name']) ?> - Modules</h1>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="program_management.php">Programs</a></li>
                        <li class="breadcrumb-item active"><?= htmlspecialchars($program['name']) ?></li>
                    </ol>
                </nav>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
                    <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Add Module Forms -->
                <div class="col-md-4">
                    <!-- Single Module Form -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Add Single Module</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="program_id" value="<?= $program_id ?>">
                                <input type="hidden" name="add_single_module" value="1">
                                
                                <div class="mb-3">
                                    <label class="form-label">Module Name</label>
                                    <input type="text" name="name" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Module Code</label>
                                    <input type="text" name="code" class="form-control" required>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Credits</label>
                                        <input type="number" name="credits" class="form-control" min="1" required>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Year</label>
                                        <select name="year" class="form-select" required>
                                            <option value="1">Year 1</option>
                                            <option value="2">Year 2</option>
                                            <option value="3">Year 3</option>
                                            <option value="4">Year 4</option>
                                            <option value="5">Year 5</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Semester</label>
                                    <select name="semester" class="form-select" required>
                                        <option value="1">Semester 1</option>
                                        <option value="2">Semester 2</option>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-plus-lg me-1"></i> Add Module
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Excel Upload Form -->
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">Import from Excel</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label class="form-label">CSV File</label>
                                    <input type="file" name="excel_file" class="form-control" accept=".csv" required>
                                    <div class="form-text">
                                        <small>File format: .csv (comma-separated values)<br>
                                        Required columns: Code, Name, Credits, Year, Semester</small>
                                    </div>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-upload me-1"></i> Import Modules
                                    </button>
                                </div>
                            </form>
                            
                            <?php if (isset($_SESSION['import_errors']) && !empty($_SESSION['import_errors'])): ?>
                                <div class="mt-3">
                                    <h6>Import Errors:</h6>
                                    <div class="alert alert-danger p-2" style="max-height: 200px; overflow-y: auto;">
                                        <ul class="mb-0">
                                            <?php foreach ($_SESSION['import_errors'] as $error): ?>
                                                <li><small><?= htmlspecialchars($error) ?></small></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                                <?php unset($_SESSION['import_errors']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mt-3 text-center">
                        <a href="#" class="btn btn-outline-secondary btn-sm" id="downloadTemplate">
                            <i class="bi bi-download me-1"></i> Download Template
                        </a>
                    </div>
                </div>
                
                <!-- Modules List -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Modules List</h5>
                            <!-- <div>
                                <button type="button" class="btn btn-danger btn-sm me-2" data-bs-toggle="modal" data-bs-target="#deleteAllModal" <?= empty($modules) ? 'disabled' : '' ?> >
                                    <i class="bi bi-trash"></i> Delete All
                                </button>
                            </div> -->
                        </div>
                        <!-- Search and Filter Controls -->
                        <div class="card-body border-bottom">
                            <div class="row g-3 mb-3">
                                <div class="col-md-5">
                                    <input type="text" id="searchInput" class="form-control" placeholder="Search modules...">
                                </div>
                                <div class="col-md-3">
                                    <select id="yearFilter" class="form-select">
                                        <option value="">All Years</option>
                                        <option value="1">Year 1</option>
                                        <option value="2">Year 2</option>
                                        <option value="3">Year 3</option>
                                        <option value="4">Year 4</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select id="semesterFilter" class="form-select">
                                        <option value="">All Semesters</option>
                                        <option value="1">Semester 1</option>
                                        <option value="2">Semester 2</option>
                                    </select>
                                </div>
                                <div class="col-md-1">
                                    <button id="resetFilters" class="btn btn-outline-secondary w-100">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </button>
                                </div>
                            </div>
                        <div class="card-body">
                            <?php if (count($modules) > 0): ?>
                                <form id="deleteSelectedForm" method="post" action="">
                                    <div class="mb-3">
                                        <button type="button" id="deleteSelectedBtn" class="btn btn-danger btn-sm" disabled>
                                            <i class="bi bi-trash"></i> Delete Selected
                                        </button>
                                    </div>
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th width="50">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="selectAll">
                                                </div>
                                            </th>
                                            <th class="sortable" data-sort="code">Code <i class="bi bi-arrow-down-up"></i></th>
                                            <th class="sortable" data-sort="name">Name <i class="bi bi-arrow-down-up"></i></th>
                                            <th class="sortable" data-sort="credits">Credits <i class="bi bi-arrow-down-up"></i></th>
                                            <th class="sortable" data-sort="year">Year <i class="bi bi-arrow-down-up"></i></th>
                                            <th class="sortable" data-sort="semester">Semester <i class="bi bi-arrow-down-up"></i></th>
                                            <!-- <th>Actions</th> -->
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($modules as $module): ?>
                                        <tr data-year="<?= $module['year'] ?>" data-semester="<?= $module['semester'] ?>">
                                            <td>
                                                <div class="form-check">
                                                    <input class="form-check-input module-checkbox" type="checkbox" name="selected_modules[]" value="<?= $module['id'] ?>">
                                                </div>
                                            </td>
                                            <td class="module-code"><?= htmlspecialchars($module['code']) ?></td>
                                            <td class="module-name"><?= htmlspecialchars($module['name']) ?></td>
                                            <td class="module-credits"><?= $module['credits'] ?></td>
                                            <td class="module-year">Year <?= $module['year'] ?></td>
                                            <td class="module-semester">Semester <?= $module['semester'] ?></td>
                                          
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-journal-text display-4 text-muted mb-3"></i>
                                    <p class="text-muted">No modules found for this program.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Delete All Confirmation Modal -->
    <div class="modal fade" id="deleteAllModal" tabindex="-1" aria-labelledby="deleteAllModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteAllModalLabel">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Deletion
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="bi bi-exclamation-triangle text-danger" style="font-size: 4rem;"></i>
                    </div>
                    <h5 class="text-center mb-3">Are you absolutely sure?</h5>
                    <div class="alert alert-danger">
                        <p class="mb-1"><i class="bi bi-exclamation-circle-fill me-2"></i>This action will permanently delete:</p>
                        <ul class="mb-0 ps-4">
                            <li>All modules for <strong><?= htmlspecialchars($program['name']) ?></strong></li>
                            <li>This action cannot be undone</li>
                            <li>Any associated data will be lost</li>
                        </ul>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="confirmDeleteAll" name="confirm_delete" required>
                        <label class="form-check-label" for="confirmDeleteAll">
                            I understand that this action is irreversible
                        </label>
                        <div class="invalid-feedback">
                            You must confirm before deleting
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i> Cancel
                    </button>
                    <form id="deleteAllForm" method="post" class="d-inline needs-validation" novalidate>
                        <input type="hidden" name="delete_all_modules" value="1">
                        <button type="submit" id="confirmDeleteBtn" class="btn btn-danger" disabled>
                            <i class="bi bi-trash3-fill me-1"></i> Delete All Modules
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Edit Module Modal -->
    <div class="modal fade" id="editModuleModal" tabindex="-1" aria-labelledby="editModuleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModuleModalLabel">Edit Module</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editModuleForm" method="POST">
                    <input type="hidden" name="module_id" id="editModuleId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="editCode" class="form-label">Code</label>
                            <input type="text" class="form-control" id="editCode" name="code" required>
                        </div>
                        <div class="mb-3">
                            <label for="editName" class="form-label">Name</label>
                            <input type="text" class="form-control" id="editName" name="name" required>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <label for="editCredits" class="form-label">Credits</label>
                                <input type="number" class="form-control" id="editCredits" name="credits" min="1" required>
                            </div>
                            <div class="col-md-4">
                                <label for="editYear" class="form-label">Year</label>
                                <select class="form-select" id="editYear" name="year" required>
                                    <option value="1">Year 1</option>
                                    <option value="2">Year 2</option>
                                    <option value="3">Year 3</option>
                                    <option value="4">Year 4</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="editSemester" class="form-label">Semester</label>
                                <select class="form-select" id="editSemester" name="semester" required>
                                    <option value="1">Semester 1</option>
                                    <option value="2">Semester 2</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Handle delete all confirmation
        const confirmDeleteAll = document.getElementById('confirmDeleteAll');
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        const deleteAllForm = document.getElementById('deleteAllForm');
        
        if (confirmDeleteAll && confirmDeleteBtn) {
            confirmDeleteAll.addEventListener('change', function() {
                confirmDeleteBtn.disabled = !this.checked;
            });
        }
        
        // Handle delete all form submission
        if (deleteAllForm) {
            deleteAllForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Check form validity
                if (!this.checkValidity()) {
                    e.stopPropagation();
                    this.classList.add('was-validated');
                    return;
                }
                
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn ? submitBtn.innerHTML : '';
                
                // Show loading state
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Deleting...';
                }
                
                // Submit the form
                const formData = new FormData(this);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (response.redirected) {
                        window.location.href = response.url;
                    } else {
                        return response.text().then(text => {
                            throw new Error('Unexpected response');
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    
                    // Show error message in toast
                    const toastEl = document.querySelector('.toast');
                    const toastBody = toastEl ? toastEl.querySelector('.toast-body') : null;
                    
                    if (toastEl && toastBody) {
                        const toast = new bootstrap.Toast(toastEl);
                        toastEl.classList.remove('bg-success');
                        toastEl.classList.add('bg-danger');
                        toastBody.textContent = 'Failed to delete modules. Please try again.';
                        toast.show();
                    } else {
                        alert('Failed to delete modules. Please try again.');
                    }
                    
                    // Reset button state
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnText;
                    }
                });
            });
        }
        
        // Handle select all checkbox
        const selectAllCheckbox = document.getElementById('selectAll');
        const moduleCheckboxes = document.querySelectorAll('.module-checkbox');
        const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
        
        // Search and filter functionality
        const searchInput = document.getElementById('searchInput');
        const yearFilter = document.getElementById('yearFilter');
        const semesterFilter = document.getElementById('semesterFilter');
        const resetFiltersBtn = document.getElementById('resetFilters');
        const moduleRows = document.querySelectorAll('tbody tr[data-year]');
        let currentSort = { field: 'code', order: 'asc' };

        // Filter modules based on search and filters
        function filterModules() {
            const searchTerm = searchInput.value.toLowerCase();
            const selectedYear = yearFilter.value;
            const selectedSemester = semesterFilter.value;
            
            moduleRows.forEach(row => {
                const code = row.querySelector('.module-code').textContent.toLowerCase();
                const name = row.querySelector('.module-name').textContent.toLowerCase();
                const year = row.getAttribute('data-year');
                const semester = row.getAttribute('data-semester');
                
                const matchesSearch = code.includes(searchTerm) || name.includes(searchTerm);
                const matchesYear = !selectedYear || year === selectedYear;
                const matchesSemester = !selectedSemester || semester === selectedSemester;
                
                row.style.display = (matchesSearch && matchesYear && matchesSemester) ? '' : 'none';
            });
        }
        
        // Sort table
        function sortTable(field) {
            const tbody = document.querySelector('tbody');
            const rows = Array.from(moduleRows).filter(row => row.style.display !== 'none');
            
            rows.sort((a, b) => {
                let aValue, bValue;
                
                switch(field) {
                    case 'code':
                        aValue = a.querySelector('.module-code').textContent;
                        bValue = b.querySelector('.module-code').textContent;
                        break;
                    case 'name':
                        aValue = a.querySelector('.module-name').textContent;
                        bValue = b.querySelector('.module-name').textContent;
                        break;
                    case 'credits':
                        aValue = parseInt(a.querySelector('.module-credits').textContent);
                        bValue = parseInt(b.querySelector('.module-credits').textContent);
                        break;
                    case 'year':
                        aValue = parseInt(a.getAttribute('data-year'));
                        bValue = parseInt(b.getAttribute('data-year'));
                        break;
                    case 'semester':
                        aValue = parseInt(a.getAttribute('data-semester'));
                        bValue = parseInt(b.getAttribute('data-semester'));
                        break;
                }
                
                if (currentSort.order === 'asc') {
                    return aValue > bValue ? 1 : -1;
                } else {
                    return aValue < bValue ? 1 : -1;
                }
            });
            
            // Remove existing rows
            rows.forEach(row => tbody.removeChild(row));
            
            // Add sorted rows
            rows.forEach(row => tbody.appendChild(row));
        }
        
        // Event listeners for search and filters
        searchInput.addEventListener('input', filterModules);
        yearFilter.addEventListener('change', filterModules);
        semesterFilter.addEventListener('change', filterModules);
        
        // Reset filters
        resetFiltersBtn.addEventListener('click', () => {
            searchInput.value = '';
            yearFilter.value = '';
            semesterFilter.value = '';
            filterModules();
        });
        
        // Sort table when clicking on column headers
        document.querySelectorAll('.sortable').forEach(header => {
            header.addEventListener('click', () => {
                const field = header.getAttribute('data-sort');
                if (currentSort.field === field) {
                    currentSort.order = currentSort.order === 'asc' ? 'desc' : 'asc';
                } else {
                    currentSort.field = field;
                    currentSort.order = 'asc';
                }
                sortTable(field);
                
                // Update sort indicators
                document.querySelectorAll('.sortable i').forEach(icon => {
                    icon.className = 'bi bi-arrow-down-up';
                });
                const sortIcon = header.querySelector('i');
                if (sortIcon) {
                    sortIcon.className = currentSort.order === 'asc' ? 'bi bi-arrow-up' : 'bi bi-arrow-down';
                }
            });
        });
        
        // Edit module functionality
        const editModuleModal = new bootstrap.Modal(document.getElementById('editModuleModal'));
        const editModuleForm = document.getElementById('editModuleForm');
        
        document.querySelectorAll('.edit-module').forEach(button => {
            button.addEventListener('click', function() {
                const moduleId = this.getAttribute('data-id');
                const code = this.getAttribute('data-code');
                const name = this.getAttribute('data-name');
                const credits = this.getAttribute('data-credits');
                const year = this.getAttribute('data-year');
                const semester = this.getAttribute('data-semester');
                
                document.getElementById('editModuleId').value = moduleId;
                document.getElementById('editCode').value = code;
                document.getElementById('editName').value = name;
                document.getElementById('editCredits').value = credits;
                document.getElementById('editYear').value = year;
                document.getElementById('editSemester').value = semester;
                
                editModuleModal.show();
            });
        });
        
        // Add toast container if it doesn't exist
        if (!document.querySelector('.toast-container')) {
            const toastContainer = document.createElement('div');
            toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            toastContainer.style.zIndex = '11';
            document.body.appendChild(toastContainer);
        }

        // Create toast element if it doesn't exist
        if (!document.querySelector('.toast')) {
            const toastContainer = document.querySelector('.toast-container') || document.body;
            const toastElement = document.createElement('div');
            toastElement.className = 'toast align-items-center text-white bg-success border-0';
            toastElement.role = 'alert';
            toastElement.setAttribute('aria-live', 'assertive');
            toastElement.setAttribute('aria-atomic', 'true');
            toastElement.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body"></div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            `;
            toastContainer.appendChild(toastElement);
        }

        // Handle edit form submission
        editModuleForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving...';
            
            fetch('update_module.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    const toastEl = document.querySelector('.toast');
                    const toastBody = toastEl.querySelector('.toast-body');
                    const toast = new bootstrap.Toast(toastEl);
                    
                    toastEl.classList.remove('bg-danger');
                    toastEl.classList.add('bg-success');
                    toastBody.textContent = data.message || 'Module updated successfully';
                    toast.show();
                    
                    // Close modal and reload page after a short delay
                    editModuleModal.hide();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    throw new Error(data.error || 'Failed to update module');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                
                // Show error message in toast
                const toastEl = document.querySelector('.toast');
                const toastBody = toastEl.querySelector('.toast-body');
                const toast = new bootstrap.Toast(toastEl);
                
                toastEl.classList.remove('bg-success');
                toastEl.classList.add('bg-danger');
                toastBody.textContent = error.message || 'An error occurred while updating the module';
                toast.show();
                
                // Reset button state
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            });
        });
        const deleteSelectedForm = document.getElementById('deleteSelectedForm');

        // Toggle all checkboxes when select all is clicked
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                const isChecked = this.checked;
                moduleCheckboxes.forEach(checkbox => {
                    checkbox.checked = isChecked;
                });
                updateDeleteButtonState();
            });
        }

        // Update select all checkbox when individual checkboxes are clicked
        moduleCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateSelectAllCheckbox();
                updateDeleteButtonState();
            });
        });

        // Handle delete selected button click
        if (deleteSelectedBtn && deleteSelectedForm) {
            deleteSelectedBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (confirm('Are you sure you want to delete the selected modules?')) {
                    // Create a hidden input for the delete_selected flag
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'delete_selected';
                    input.value = '1';
                    deleteSelectedForm.appendChild(input);
                    
                    // Submit the form
                    deleteSelectedForm.submit();
                }
            });
        }

        // Function to update the select all checkbox state
        function updateSelectAllCheckbox() {
            if (!selectAllCheckbox) return;
            
            const allChecked = Array.from(moduleCheckboxes).every(checkbox => checkbox.checked);
            selectAllCheckbox.checked = allChecked;
        }

        // Function to update delete button state based on selected checkboxes
        function updateDeleteButtonState() {
            if (!deleteSelectedBtn) return;
            
            const anyChecked = Array.from(moduleCheckboxes).some(checkbox => checkbox.checked);
            deleteSelectedBtn.disabled = !anyChecked;
        }

        document.getElementById('downloadTemplate').addEventListener('click', function(e) {
            e.preventDefault();
            
            // Create a simple CSV content
            const csvContent = 'Code,Name,Credits,Year,Semester\n' +
                             'MATH101,Mathematics,12,1,1\n' +
                             'PHYS101,Physics,10,1,1\n' +
                             'CHEM101,Chemistry,8,1,2';
            
            // Create a Blob with the CSV content
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            
            // Create a download link and trigger it
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'module_template.csv');
            link.style.visibility = 'hidden';
            
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
    </script>
</body>
</html>
