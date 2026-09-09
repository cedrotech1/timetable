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
<body>
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
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Modules (<?= count($modules) ?>)</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($modules) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Code</th>
                                                <th>Module Name</th>
                                                <th>Credits</th>
                                                <th>Year</th>
                                                <th>Semester</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($modules as $module): ?>
                                                <tr class="module-card">
                                                    <td><strong><?= htmlspecialchars($module['code']) ?></strong></td>
                                                    <td><?= htmlspecialchars($module['name']) ?></td>
                                                    <td><?= $module['credits'] ?></td>
                                                    <td>Year <?= $module['year'] ?></td>
                                                    <td>Semester <?= $module['semester'] ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
