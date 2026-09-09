<?php
// Set secure session cookie parameters BEFORE starting the session
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Lax');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include('connection.php');
// include('./includes/auth.php');

// Get user's role and campus
$id = $_SESSION['id'];
$sql = "SELECT * FROM users WHERE id = $id";
$result = mysqli_query($connection, $sql);
$row = mysqli_fetch_assoc($result);
$mycampus = $row['campus'];
$role = $row['role'];

// Get all campuses for selection
if($role === 'warefare'){       
    $campuses_query = mysqli_query($connection, "SELECT * FROM campus WHERE id = $mycampus ORDER BY name");
} else {
    $campuses_query = mysqli_query($connection, "SELECT * FROM campus ORDER BY name");
}
$campuses = [];
while ($campus = mysqli_fetch_assoc($campuses_query)) {
    $campuses[] = $campus;
}

// Get programs for the modules form
$programs_query = "SELECT id, name FROM program ORDER BY name";
$programs_result = mysqli_query($connection, $programs_query);
$programs = mysqli_fetch_all($programs_result, MYSQLI_ASSOC);

/* ---- AJAX API (same file) ---- */
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    // helper: normalize strings (trim + collapse spaces)
    $normalize = function ($s) {
        $s = trim((string)$s);
        $s = preg_replace('/\s+/', ' ', $s);
        return $s;
    };

    try {
        switch ($action) {
            case 'upload_modules': {
                if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                    echo json_encode(['success' => false, 'message' => 'File upload failed']);
                    break;
                }

                $file = $_FILES['file'];
                $fileName = $file['name'];
                $fileTmpName = $file['tmp_name'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                // Process file based on extension
                $data = [];
                if ($fileExtension === 'csv') {
                    if (($handle = fopen($fileTmpName, 'r')) !== FALSE) {
                        $headers = fgetcsv($handle);
                        while (($row = fgetcsv($handle)) !== FALSE) {
                            $data[] = array_combine($headers, $row);
                        }
                        fclose($handle);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Unsupported file format. Please use CSV format.']);
                    break;
                }

                $inserted = 0;
                $errors = [];
                $success_items = [];

                foreach ($data as $row) {
                    $name = $normalize($row['Name'] ?? $row['name'] ?? '');
                    $module_code = $normalize($row['Module Code'] ?? $row['module_code'] ?? '');
                    $qualification_code = $normalize($row['Qualification Code'] ?? $row['qualification_code'] ?? '');
                    $credits = (int)($row['Credits'] ?? $row['credits'] ?? 0);
                    $year = (int)($row['Year'] ?? $row['year'] ?? 1);
                    $semester = (int)($row['Semester'] ?? $row['semester'] ?? 1);

                    if (empty($name) || empty($module_code) || empty($qualification_code)) {
                        $errors[] = "Skipped row: Missing required fields (Name, Module Code, or Qualification Code)";
                        continue;
                    }

                    // Skip program code validation and use a default program ID
                    $program_id = 1; // Default program ID, adjust if needed

                    try {
                        // First check if module with same code already exists
                        $check_stmt = $connection->prepare("SELECT id FROM module WHERE code = ?");
                        $check_stmt->bind_param('s', $module_code);
                        $check_stmt->execute();
                        $exists = $check_stmt->get_result()->num_rows > 0;
                        
                        if ($exists) {
                            // Update existing module
                            $insert_stmt = $connection->prepare("UPDATE module SET name = ?, program_id = ?, credits = ?, year = ?, semester = ? WHERE code = ?");
                            $insert_stmt->bind_param('siiiis', $name, $program_id, $credits, $year, $semester, $module_code);
                            $action = 'updated';
                        } else {
                            // Insert new module
                            $insert_stmt = $connection->prepare("INSERT INTO module (name, code, program_id, credits, year, semester) VALUES (?, ?, ?, ?, ?, ?)");
                            $insert_stmt->bind_param('ssiiii', $name, $module_code, $program_id, $credits, $year, $semester);
                            $action = 'added';
                        }
                        
                        if ($insert_stmt->execute()) {
                            $inserted++;
                            $success_items[] = "$name ($action)";
                        } else {
                            throw new Exception($connection->error);
                        }
                    } catch (Exception $e) {
                        $errors[] = "Failed to insert module '$name': " . $e->getMessage();
                    }
                }

                echo json_encode([
                    'success' => $inserted > 0,
                    'message' => "Upload completed: $inserted modules added",
                    'inserted' => $inserted,
                    'errors' => count($errors),
                    'error_messages' => $errors,
                    'success_items' => $success_items
                ]);
                break;
            }

            case 'upload_lecturers': {
                if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                    echo json_encode(['success' => false, 'message' => 'File upload failed']);
                    break;
                }

                $file = $_FILES['file'];
                $fileName = $file['name'];
                $fileTmpName = $file['tmp_name'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                // Process file based on extension
                $data = [];
                if ($fileExtension === 'csv') {
                    if (($handle = fopen($fileTmpName, 'r')) !== FALSE) {
                        $headers = fgetcsv($handle);
                        while (($row = fgetcsv($handle)) !== FALSE) {
                            $data[] = array_combine($headers, $row);
                        }
                        fclose($handle);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Unsupported file format. Please use CSV format.']);
                    break;
                }

                $inserted = 0;
                $errors = [];
                $success_items = [];

                foreach ($data as $row) {
                    $names = $normalize($row['Names'] ?? $row['names'] ?? '');
                    $email = $normalize($row['Email'] ?? $row['email'] ?? '');
                    $phone = $normalize($row['Phone'] ?? $row['phone'] ?? '');

                    if (empty($names) || empty($email)) {
                        $errors[] = "Skipped row: Missing required fields (Names or Email)";
                        continue;
                    }

                    // Check if email already exists
                    $email_check = $connection->prepare("SELECT id FROM users WHERE email = ?");
                    $email_check->bind_param('s', $email);
                    $email_check->execute();
                    
                    if ($email_check->get_result()->num_rows > 0) {
                        $errors[] = "User with email '$email' already exists";
                        continue;
                    }

                    // Generate default password (you can customize this)
                    $default_password = 'lecturer123';
                    $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);

                    // Insert lecturer as user
                    $insert_stmt = $connection->prepare("INSERT INTO users (names, email, phone, password, role, campus) VALUES (?, ?, ?, ?, 'lecturer', ?)");
                    $insert_stmt->bind_param('ssssi', $names, $email, $phone, $hashed_password, $mycampus);
                    
                    if ($insert_stmt->execute()) {
                        $inserted++;
                        $success_items[] = $names;
                    } else {
                        $errors[] = "Failed to insert lecturer '$names': " . $connection->error;
                    }
                }

                echo json_encode([
                    'success' => $inserted > 0,
                    'message' => "Upload completed: $inserted lecturers added",
                    'inserted' => $inserted,
                    'errors' => count($errors),
                    'error_messages' => $errors,
                    'success_items' => $success_items
                ]);
                break;
            }

            case 'preview_file': {
                if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                    echo json_encode(['success' => false, 'message' => 'File upload failed']);
                    break;
                }

                $file = $_FILES['file'];
                $fileName = $file['name'];
                $fileTmpName = $file['tmp_name'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                // Process file based on extension
                $data = [];
                if ($fileExtension === 'csv') {
                    if (($handle = fopen($fileTmpName, 'r')) !== FALSE) {
                        $headers = fgetcsv($handle);
                        $rowCount = 0;
                        while (($row = fgetcsv($handle)) !== FALSE && $rowCount < 10) { // Preview first 10 rows
                            $data[] = array_combine($headers, $row);
                            $rowCount++;
                        }
                        fclose($handle);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Unsupported file format. Please use CSV format.']);
                    break;
                }

                echo json_encode([
                    'success' => true,
                    'data' => $data,
                    'headers' => $headers ?? [],
                    'total_rows' => count($data)
                ]);
                break;
            }

            default:
                echo json_encode(['success' => false, 'message' => 'Unknown action']);
        }
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Upload Data – UR-TIMETABLE</title>
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

    <!-- CDN UI libs (same stack as school page) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.min.css" rel="stylesheet" />
    <link href="https://cdn.datatables.net/v/bs5/dt-2.0.8/datatables.min.css" rel="stylesheet" />

    <style>
        body { background:#f7f8fa; }
        .card { box-shadow:0 2px 10px rgba(0,0,0,0.06); border:0; }
        .preview-badge { font-size: .8rem; }
        .file-drop { border:2px dashed #ced4da; border-radius: .5rem; padding:1.25rem; text-align:center; background:#fff; }
        .file-drop.dragover { border-color:#0d6efd; background:#eef4ff; }
        .table td, .table th { vertical-align: middle; }
        .w-90 { width:90%; }
        .btn-primary,.btn-outline-primary { background-color: rgb(22, 50, 99); color:#fff; }
        .btn-primary:hover,.btn-outline-primary:hover { background-color: rgb(147, 144, 204); }
        .btn-danger,.btn-outline-danger { background-color: rgb(207, 93, 0); color:#fff; }
        .btn-danger:hover,.btn-outline-danger:hover { background-color: rgb(207, 4, 4); }
        .btn-success,.btn-outline-success { background-color: rgb(25, 135, 84); color:#fff; }
        .btn-success:hover,.btn-outline-success:hover { background-color: rgb(20, 108, 67); }

        h5 { font-size: 17px !important; background-color: rgb(22, 50, 99) !important; color:#fff !important; padding: 6px !important; margin-bottom: 10px !important; border-radius: 5px !important; }
        h4 { font-size: 17px !important; background-color: rgb(22, 50, 99) !important; color:#fff !important; padding: 6px !important; margin-bottom: 10px !important; border-radius: 5px !important; }
        h2 { font-size: 17px !important; background-color: rgb(22, 50, 99) !important; color:#fff !important; padding: 6px !important; margin-bottom: 10px !important; border-radius: 5px !important; }
        th { font-size: 17px !important; background-color: rgb(22, 50, 99) !important; color:#fff !important; padding: 6px !important; margin-bottom: 10px !important; border-radius: 5px !important; }
        
        /* Nav tabs styling to match design */
        .nav-tabs .nav-link {
            background-color: #f8f9fa;
            border-color: #dee2e6;
            color: rgb(22, 50, 99);
        }
        .nav-tabs .nav-link.active {
            background-color: rgb(22, 50, 99);
            color: #fff;
            border-color: rgb(22, 50, 99);
        }
        .nav-tabs .nav-link:hover {
            background-color: rgb(147, 144, 204);
            color: #fff;
        }

        /* Upload area styling - same as school page */
        .upload-section {
            background: #fff;
            border: 2px dashed #ced4da;
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
        }
        .upload-section:hover {
            border-color: rgb(22, 50, 99);
            background: #f8f9fa;
        }
        .upload-section.dragover {
            border-color: rgb(22, 50, 99);
            background: #eef4ff;
        }

        .example-table {
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .pagetitle h1 {
            font-size: 24px !important;
            color: rgb(22, 50, 99) !important;
            background: none !important;
            padding: 0 !important;
            margin-bottom: 5px !important;
            border-radius: 0 !important;
        }

        /* Status badges */
        .status-new { background-color: #198754 !important; }
        .status-exists { background-color: #ffc107 !important; color: #000 !important; }
        .status-error { background-color: #dc3545 !important; }
        .status-unchecked { background-color: #6c757d !important; }
    </style>
</head>

<body>
    <?php include('./includes/header.php'); ?>
    <?php include('./includes/menu.php'); ?>

    <main id="main" class="main">
        <div class="p-4">
            <div class="pagetitle mb-4">
                <h1>Upload Data</h1>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item">Data</li>
                        <li class="breadcrumb-item active">Upload</li>
                    </ol>
                </nav>
            </div>

            <div class="card">
                <div class="card-body p-4">
                    <!-- Tabs -->
                    <ul class="nav nav-tabs mb-4" id="uploadTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="modules-tab" data-bs-toggle="tab" data-bs-target="#modules" type="button" role="tab">
                                <i class="bi bi-book me-2"></i> Upload Modules
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="lecturers-tab" data-bs-toggle="tab" data-bs-target="#lecturers" type="button" role="tab">
                                <i class="bi bi-person me-2"></i> Upload Lecturers
                            </button>
                        </li>
                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content" id="uploadTabsContent">
                        <!-- Modules Tab -->
                        <div class="tab-pane fade show active" id="modules" role="tabpanel">
                            <div class="row g-4">
                                <!-- Upload Section -->
                                <div class="col-lg-0">
                                    <div class="card p-3">
                                        <h5 class="mb-3">Upload Modules File</h5>
                                        <div id="modulesDropArea" class="file-drop mb-3">
                                            <div class="mb-2">
                                                <i class="bi bi-file-earmark-spreadsheet" style="font-size:2rem; color: rgb(22, 50, 99);"></i>
                                            </div>
                                            <div>Drop .xlsx/.xls/.csv here or
                                                <label class="text-primary text-decoration-underline" style="cursor:pointer">
                                                    <input type="file" id="modulesInput" accept=".xlsx,.xls,.csv" hidden />browse
                                                </label>
                                            </div>
                                            <div class="small text-muted mt-1">
                                                Expected columns: <code>Name</code>, <code>Module Code</code>, <code>Qualification Code</code>, <code>Credits</code>, <code>Year</code>, <code>Semester</code>
                                            </div>
                                        </div>

                                        <div class="d-flex gap-2 mb-2">
                                            <button id="btnPreviewModules" class="btn btn-outline-secondary" disabled>
                                                <i class="bi bi-search"></i> Preview
                                            </button>
                                            <button id="btnUploadModules" class="btn btn-success" disabled>
                                                <i class="bi bi-cloud-upload"></i> Upload
                                            </button>
                                        </div>
                                        
                                        <div class="text-center">
                                            <a href="templates/modules_template.xlsx" class="btn btn-outline-secondary btn-sm" download>
                                                <i class="bi bi-download me-1"></i> Download Template
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <!-- Preview Section -->
                                <div class="col-lg-0">
                                    <div class="card p-3">
                                        <h5 class="mb-3">File Preview</h5>
                                        <div class="table-responsive">
                                            <table id="modulesPreviewTable" class="table table-sm table-bordered align-middle mb-0">
                                                <thead>
                                                    <tr><th>#</th><th>Name</th><th>Module Code</th><th>Qualification Code</th><th>Credits</th><th>Year</th><th>Semester</th><th>Status</th></tr>
                                                </thead>
                                                <tbody></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Lecturers Tab -->
                        <div class="tab-pane fade" id="lecturers" role="tabpanel">
                            <div class="row g-4">
                                <!-- Upload Section -->
                                <div class="col-lg-0">
                                    <div class="card p-3">
                                        <h5 class="mb-3">Upload Lecturers File</h5>
                                        <div id="lecturersDropArea" class="file-drop mb-3">
                                            <div class="mb-2">
                                                <i class="bi bi-people" style="font-size:2rem; color: rgb(22, 50, 99);"></i>
                                            </div>
                                            <div>Drop .xlsx/.xls/.csv here or
                                                <label class="text-primary text-decoration-underline" style="cursor:pointer">
                                                    <input type="file" id="lecturersInput" accept=".xlsx,.xls,.csv" hidden />browse
                                                </label>
                                            </div>
                                            <div class="small text-muted mt-1">
                                                Expected columns: <code>Names</code>, <code>Email</code>, <code>Phone</code>
                                            </div>
                                        </div>

                                        <div class="d-flex gap-2 mb-2">
                                            <button id="btnPreviewLecturers" class="btn btn-outline-secondary" disabled>
                                                <i class="bi bi-search"></i> Preview
                                            </button>
                                            <button id="btnUploadLecturers" class="btn btn-success" disabled>
                                                <i class="bi bi-cloud-upload"></i> Upload
                                            </button>
                                        </div>
                                        
                                        <div class="text-center">
                                            <a href="templates/lecturers_template.xlsx" class="btn btn-outline-secondary btn-sm" download>
                                                <i class="bi bi-download me-1"></i> Download Template
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <!-- Preview Section -->
                                <div class="col-lg-0">
                                    <div class="card p-3">
                                        <h5 class="mb-3">File Preview</h5>
                                        <div class="table-responsive">
                                            <table id="lecturersPreviewTable" class="table table-sm table-bordered align-middle mb-0">
                                                <thead>
                                                    <tr><th>#</th><th>Names</th><th>Email</th><th>Phone</th><th>Status</th></tr>
                                                </thead>
                                                <tbody></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- End Lecturers Tab -->
                    </div>
                    <!-- End Tab Content -->
                </div>
            </div>
        </div>
    </main>

    <!-- Upload Results Modal -->
    <div class="modal fade" id="uploadResultsModal" tabindex="-1" aria-labelledby="uploadResultsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background-color: rgb(22, 50, 99); color: white;">
                    <h5 class="modal-title" id="uploadResultsModalLabel" style="background: none !important; color: white !important; font-size: 18px !important; padding: 0 !important; margin: 0 !important;">Upload Results</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="uploadResultsContent"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.all.min.js"></script>
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/v/bs5/dt-2.0.8/datatables.min.js"></script>
    <!-- SheetJS -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

    <script>
    (function(){
        let modulesPreviewData = [];
        let lecturersPreviewData = [];

        function api(action, data) {
            return $.ajax({
                url: `?ajax=1&action=${encodeURIComponent(action)}`,
                method: 'POST',
                data,
                dataType: 'json'
            });
        }

        function toast(icon, title) {
            Swal.fire({toast:true, icon, title, position:'top-end', timer:2500, showConfirmButton:false});
        }

        function escapeHtml(s) { 
            return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); 
        }

        function showUploadResults(result) {
            const modal = new bootstrap.Modal(document.getElementById('uploadResultsModal'));
            const contentDiv = document.getElementById('uploadResultsContent');
            
            let html = '';
            
            if (result.success) {
                html += `
                    <div class="alert alert-success mb-3">
                        <i class="bi bi-check-circle me-2"></i>
                        <strong>Success!</strong> ${result.message}
                    </div>
                `;
            } else {
                html += `
                    <div class="alert alert-danger mb-3">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Error!</strong> ${result.message}
                    </div>
                `;
            }

            // Show success details
            if (result.inserted > 0) {
                html += `
                    <div class="card mb-3">
                        <div class="card-header" style="background-color: rgb(25, 135, 84); color: white;">
                            <i class="bi bi-check-circle me-2"></i>
                            Successfully Processed Items (${result.inserted})
                        </div>
                        <div class="card-body">
                            ${result.success_items ? result.success_items.map(item => `<span class="badge bg-success me-1 mb-1">${escapeHtml(item)}</span>`).join('') : ''}
                        </div>
                    </div>
                `;
            }

            // Show error messages
            if (result.error_messages && result.error_messages.length > 0) {
                html += `
                    <div class="card">
                        <div class="card-header" style="background-color: rgb(207, 93, 0); color: white;">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Issues Found (${result.error_messages.length})
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                ${result.error_messages.map(msg => `
                                    <li class="list-group-item">
                                        <i class="bi bi-dot me-2"></i>
                                        ${escapeHtml(msg)}
                                    </li>
                                `).join('')}
                            </ul>
                        </div>
                    </div>
                `;
            }

            contentDiv.innerHTML = html;
            modal.show();
        }

        // Function to convert Excel file to CSV
        function excelToCSV(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    try {
                        const data = new Uint8Array(e.target.result);
                        const workbook = XLSX.read(data, { type: 'array' });
                        const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
                        const csv = XLSX.utils.sheet_to_csv(firstSheet);
                        resolve(csv);
                    } catch (error) {
                        reject(error);
                    }
                };
                reader.onerror = reject;
                reader.readAsArrayBuffer(file);
            });
        }

        // Modules Upload Functionality
        const modulesDropArea = $('#modulesDropArea');
        const modulesInput = $('#modulesInput');

        modulesDropArea.on('dragover', function(e) { 
            e.preventDefault(); 
            e.originalEvent.dataTransfer.dropEffect = 'copy'; 
            $(this).addClass('dragover'); 
        });
        modulesDropArea.on('dragleave dragend', function() { 
            $(this).removeClass('dragover'); 
        });
        modulesDropArea.on('drop', function(e) { 
            e.preventDefault(); 
            $(this).removeClass('dragover'); 
            handleModulesFile(e.originalEvent.dataTransfer.files[0]); 
        });
        modulesInput.on('change', function() { 
            if (this.files[0]) handleModulesFile(this.files[0]); 
        });

        function handleModulesFile(file) {
            if (!file) return;
            
            $('#btnPreviewModules').prop('disabled', false);
            $('#btnUploadModules').prop('disabled', true);
            
            window.currentModulesFile = file;
            toast('info', 'File selected. Click Preview to view contents.');
        }

        $('#btnPreviewModules').on('click', async function() {
            if (!window.currentModulesFile) return;
            
            const btn = $(this);
            const originalText = btn.html();
            
            try {
                btn.prop('disabled', true);
                btn.html('<span class="spinner-border spinner-border-sm me-2"></span>Loading...');
                
                let csvData;
                if (window.currentModulesFile.name.match(/\.(xlsx|xls)$/i)) {
                    csvData = await excelToCSV(window.currentModulesFile);
                } else {
                    csvData = await new Promise((resolve, reject) => {
                        const reader = new FileReader();
                        reader.onload = e => resolve(e.target.result);
                        reader.onerror = reject;
                        reader.readAsText(window.currentModulesFile);
                    });
                }
                
                // Parse CSV data
                const lines = csvData.trim().split('\n');
                const headers = lines[0].split(',').map(h => h.trim().replace(/"/g, ''));
                
                modulesPreviewData = [];
                for (let i = 1; i < lines.length && i <= 10; i++) { // Preview first 10 rows
                    const values = lines[i].split(',').map(v => v.trim().replace(/"/g, ''));
                    const row = {};
                    headers.forEach((header, index) => {
                        row[header] = values[index] || '';
                    });
                    modulesPreviewData.push(row);
                }
                
                renderModulesPreview();
                $('#btnUploadModules').prop('disabled', modulesPreviewData.length === 0);
                toast('success', `Preview loaded: ${modulesPreviewData.length} rows`);
                
            } catch (error) {
                console.error('Preview error:', error);
                toast('error', 'Failed to preview file');
            } finally {
                btn.prop('disabled', false);
                btn.html(originalText);
            }
        });

        function renderModulesPreview() {
            const tbody = $('#modulesPreviewTable tbody').empty();
            
            modulesPreviewData.forEach((row, idx) => {
                const name = row['Name'] || row['name'] || '';
                const moduleCode = row['Module Code'] || row['module_code'] || '';
                const qualificationCode = row['Qualification Code'] || row['qualification_code'] || '';
                const credits = row['Credits'] || row['credits'] || '';
                const year = row['Year'] || row['year'] || '';
                const semester = row['Semester'] || row['semester'] || '';
                
                let status = '<span class="badge status-unchecked preview-badge">Ready</span>';
                if (!name || !moduleCode || !qualificationCode) {
                    status = '<span class="badge status-error preview-badge">Missing Data</span>';
                }
                
                tbody.append(`
                    <tr>
                        <td>${idx + 1}</td>
                        <td>${escapeHtml(name)}</td>
                        <td>${escapeHtml(moduleCode)}</td>
                        <td>${escapeHtml(qualificationCode)}</td>
                        <td>${escapeHtml(credits)}</td>
                        <td>${escapeHtml(year)}</td>
                        <td>${escapeHtml(semester)}</td>
                        <td>${status}</td>
                    </tr>
                `);
            });
        }

        $('#btnUploadModules').on('click', async function() {
            if (!window.currentModulesFile) return;
            
            const btn = $(this);
            const originalText = btn.html();
            
            try {
                btn.prop('disabled', true);
                btn.html('<span class="spinner-border spinner-border-sm me-2"></span>Uploading...');
                
                const formData = new FormData();
                
                // Convert Excel to CSV if needed
                if (window.currentModulesFile.name.match(/\.(xlsx|xls)$/i)) {
                    const csv = await excelToCSV(window.currentModulesFile);
                    const csvBlob = new Blob([csv], { type: 'text/csv' });
                    formData.append('file', new File([csvBlob], window.currentModulesFile.name.replace(/\.(xlsx|xls)$/i, '.csv'), { type: 'text/csv' }));
                } else {
                    formData.append('file', window.currentModulesFile);
                }
                
                formData.append('action', 'upload_modules');
                
                const response = await fetch('?ajax=1', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                showUploadResults(result);
                
                if (result.success) {
                    // Reset form
                    modulesInput.val('');
                    window.currentModulesFile = null;
                    $('#modulesPreviewTable tbody').empty();
                    $('#btnPreviewModules').prop('disabled', true);
                    $('#btnUploadModules').prop('disabled', true);
                }
                
            } catch (error) {
                console.error('Upload error:', error);
                showUploadResults({
                    success: false,
                    message: 'Upload failed: ' + error.message
                });
            } finally {
                btn.prop('disabled', false);
                btn.html(originalText);
            }
        });

        // Lecturers Upload Functionality
        const lecturersDropArea = $('#lecturersDropArea');
        const lecturersInput = $('#lecturersInput');

        lecturersDropArea.on('dragover', function(e) { 
            e.preventDefault(); 
            e.originalEvent.dataTransfer.dropEffect = 'copy'; 
            $(this).addClass('dragover'); 
        });
        lecturersDropArea.on('dragleave dragend', function() { 
            $(this).removeClass('dragover'); 
        });
        lecturersDropArea.on('drop', function(e) { 
            e.preventDefault(); 
            $(this).removeClass('dragover'); 
            handleLecturersFile(e.originalEvent.dataTransfer.files[0]); 
        });
        lecturersInput.on('change', function() { 
            if (this.files[0]) handleLecturersFile(this.files[0]); 
        });

        function handleLecturersFile(file) {
            if (!file) return;
            
            $('#btnPreviewLecturers').prop('disabled', false);
            $('#btnUploadLecturers').prop('disabled', true);
            
            window.currentLecturersFile = file;
            toast('info', 'File selected. Click Preview to view contents.');
        }

        $('#btnPreviewLecturers').on('click', async function() {
            if (!window.currentLecturersFile) return;
            
            const btn = $(this);
            const originalText = btn.html();
            
            try {
                btn.prop('disabled', true);
                btn.html('<span class="spinner-border spinner-border-sm me-2"></span>Loading...');
                
                let csvData;
                if (window.currentLecturersFile.name.match(/\.(xlsx|xls)$/i)) {
                    csvData = await excelToCSV(window.currentLecturersFile);
                } else {
                    csvData = await new Promise((resolve, reject) => {
                        const reader = new FileReader();
                        reader.onload = e => resolve(e.target.result);
                        reader.onerror = reject;
                        reader.readAsText(window.currentLecturersFile);
                    });
                }
                
                // Parse CSV data
                const lines = csvData.trim().split('\n');
                const headers = lines[0].split(',').map(h => h.trim().replace(/"/g, ''));
                
                lecturersPreviewData = [];
                for (let i = 1; i < lines.length && i <= 10; i++) { // Preview first 10 rows
                    const values = lines[i].split(',').map(v => v.trim().replace(/"/g, ''));
                    const row = {};
                    headers.forEach((header, index) => {
                        row[header] = values[index] || '';
                    });
                    lecturersPreviewData.push(row);
                }
                
                renderLecturersPreview();
                $('#btnUploadLecturers').prop('disabled', lecturersPreviewData.length === 0);
                toast('success', `Preview loaded: ${lecturersPreviewData.length} rows`);
                
            } catch (error) {
                console.error('Preview error:', error);
                toast('error', 'Failed to preview file');
            } finally {
                btn.prop('disabled', false);
                btn.html(originalText);
            }
        });

        function renderLecturersPreview() {
            const tbody = $('#lecturersPreviewTable tbody').empty();
            
            lecturersPreviewData.forEach((row, idx) => {
                const names = row['Names'] || row['names'] || '';
                const email = row['Email'] || row['email'] || '';
                const phone = row['Phone'] || row['phone'] || '';
                
                let status = '<span class="badge status-unchecked preview-badge">Ready</span>';
                if (!names || !email) {
                    status = '<span class="badge status-error preview-badge">Missing Data</span>';
                }
                
                tbody.append(`
                    <tr>
                        <td>${idx + 1}</td>
                        <td>${escapeHtml(names)}</td>
                        <td>${escapeHtml(email)}</td>
                        <td>${escapeHtml(phone)}</td>
                        <td>${status}</td>
                    </tr>
                `);
            });
        }

        $('#btnUploadLecturers').on('click', async function() {
            if (!window.currentLecturersFile) return;
            
            const btn = $(this);
            const originalText = btn.html();
            
            try {
                btn.prop('disabled', true);
                btn.html('<span class="spinner-border spinner-border-sm me-2"></span>Uploading...');
                
                const formData = new FormData();
                
                // Convert Excel to CSV if needed
                if (window.currentLecturersFile.name.match(/\.(xlsx|xls)$/i)) {
                    const csv = await excelToCSV(window.currentLecturersFile);
                    const csvBlob = new Blob([csv], { type: 'text/csv' });
                    formData.append('file', new File([csvBlob], window.currentLecturersFile.name.replace(/\.(xlsx|xls)$/i, '.csv'), { type: 'text/csv' }));
                } else {
                    formData.append('file', window.currentLecturersFile);
                }
                
                formData.append('action', 'upload_lecturers');
                
                const response = await fetch('?ajax=1', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                showUploadResults(result);
                
                if (result.success) {
                    // Reset form
                    lecturersInput.val('');
                    window.currentLecturersFile = null;
                    $('#lecturersPreviewTable tbody').empty();
                    $('#btnPreviewLecturers').prop('disabled', true);
                    $('#btnUploadLecturers').prop('disabled', true);
                }
                
            } catch (error) {
                console.error('Upload error:', error);
                showUploadResults({
                    success: false,
                    message: 'Upload failed: ' + error.message
                });
            } finally {
                btn.prop('disabled', false);
                btn.html(originalText);     
            }
        });

    })();
    </script>
</body>
</html>