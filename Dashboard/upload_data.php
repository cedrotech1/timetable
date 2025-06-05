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
include('./includes/auth.php');

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    <title>UR-TIMETABLE</title>
    <link href="assets/img/icon1.png" rel="icon">
    <link href="assets/img/icon1.png" rel="apple-touch-icon">
    
    <!-- Include your existing CSS files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>
    <?php
    include("./includes/header.php");
    include("./includes/menu.php");
    ?>

    <main id="main" class="main">
        <div class="pagetitle">
            <h1>Upload Data</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item">Data</li>
                    <li class="breadcrumb-item active">Upload</li>
                    <li class="breadcrumb-item active">Upload Data</li>
                </ol>
            </nav>
        </div>

        <section class="section">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <!-- Tabs -->
                            <ul class="nav nav-tabs" id="uploadTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="facilities-tab" data-bs-toggle="tab" data-bs-target="#facilities" type="button" role="tab">
                                        <i class="bi bi-building me-1"></i>Facilities
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="modules-tab" data-bs-toggle="tab" data-bs-target="#modules" type="button" role="tab">
                                        <i class="bi bi-book me-1"></i>Modules
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="lecturers-tab" data-bs-toggle="tab" data-bs-target="#lecturers" type="button" role="tab">
                                        <i class="bi bi-person me-1"></i>Lecturers
                                    </button>
                                </li>
                            </ul>

                            <!-- Tab Content -->
                            <div class="tab-content pt-4" id="uploadTabsContent">
                                <!-- Facilities Tab -->
                                <div class="tab-pane fade show active" id="facilities" role="tabpanel">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h5 class="card-title">Upload Facilities</h5>
                                            <p class="text-muted">Upload facilities data in Excel or CSV format.</p>
                                            <form id="facilitiesForm" enctype="multipart/form-data">
                                                <div class="mb-3">
                                                    <label class="form-label">Select Campus</label>
                                                    <select class="form-select" name="campus_id" required>
                                                        <option value="">Select Campus</option>
                                                        <?php foreach ($campuses as $campus): ?>
                                                        <option value="<?php echo $campus['id']; ?>"><?php echo htmlspecialchars($campus['name']); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Upload File</label>
                                                    <input type="file" class="form-control" name="file" accept=".xlsx,.xls,.csv" required>
                                                    <div class="form-text">Supported formats: Excel (.xlsx, .xls) or CSV</div>
                                                </div>
                                                <div class="mb-3">
                                                    <a href="templates/facilities_template.xlsx" class="btn btn-outline-primary" download>
                                                        <i class="bi bi-download me-1"></i>Download Template
                                                    </a>
                                                </div>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="bi bi-upload me-1"></i>Upload Facilities
                                                </button>
                                            </form>
                                        </div>
                                        <div class="col-md-6">
                                            <h5 class="card-title">Example Data</h5>
                                            <div class="table-responsive">
                                                <table class="table table-bordered">
                                                    <thead>
                                                        <tr>
                                                            <th>name</th>
                                                            <th>type</th>
                                                            <th>capacity</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td>Lecture Hall 1</td>
                                                            <td>Lecture Hall</td>
                                                            <td>100</td>
                                                        </tr>
                                                        <tr>
                                                            <td>Computer Lab 1</td>
                                                            <td>Laboratory</td>
                                                            <td>30</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Modules Tab -->
                                <div class="tab-pane fade" id="modules" role="tabpanel">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h5 class="card-title">Upload Modules</h5>
                                            <p class="text-muted">Upload modules data in Excel or CSV format. Module codes must match program codes.</p>
                                            <form id="modulesForm" enctype="multipart/form-data">
                                                <div class="mb-3">
                                                    <label class="form-label">Upload File</label>
                                                    <input type="file" class="form-control" name="file" accept=".xlsx,.xls,.csv" required>
                                                    <div class="form-text">Supported formats: Excel (.xlsx, .xls) or CSV</div>
                                                </div>
                                                <div class="mb-3">
                                                    <a href="templates/modules_template.xlsx" class="btn btn-outline-primary" download>
                                                        <i class="bi bi-download me-1"></i>Download Template
                                                    </a>
                                                </div>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="bi bi-upload me-1"></i>Upload Modules
                                                </button>
                                            </form>
                                        </div>
                                        <div class="col-md-6">
                                            <h5 class="card-title">Example Data</h5>
                                            <div class="table-responsive">
                                                <table class="table table-bordered">
                                                    <thead>
                                                        <tr>
                                                            <th>name</th>
                                                            <th>module_code</th>
                                                            <th>qualification_code</th>
                                                            <th>credits</th>
                                                            <th>year</th>
                                                            <th>semester</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td>Introduction to Programming</td>
                                                            <td>CS101</td>
                                                            <td>112</td>
                                                            <td>3</td>
                                                            <td>1</td>
                                                            <td>1</td>
                                                        </tr>
                                                        <tr>
                                                            <td>Database Management Systems</td>
                                                            <td>CS201</td>
                                                            <td>23</td>
                                                            <td>4</td>
                                                            <td>1</td>
                                                            <td>2</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Lecturers Tab -->
                                <div class="tab-pane fade" id="lecturers" role="tabpanel">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h5 class="card-title">Upload Lecturers</h5>
                                            <p class="text-muted">Upload lecturers data in Excel or CSV format. Lecturers will be added as users with lecturer role.</p>
                                            <form id="lecturersForm" enctype="multipart/form-data">
                                                <div class="mb-3">
                                                    <label class="form-label">Upload File</label>
                                                    <input type="file" class="form-control" name="file" accept=".xlsx,.xls,.csv" required>
                                                    <div class="form-text">Supported formats: Excel (.xlsx, .xls) or CSV</div>
                                                </div>
                                                <div class="mb-3">
                                                    <a href="templates/lecturers_template.xlsx" class="btn btn-outline-primary" download>
                                                        <i class="bi bi-download me-1"></i>Download Template
                                                    </a>
                                                </div>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="bi bi-upload me-1"></i>Upload Lecturers
                                                </button>
                                            </form>
                                        </div>
                                        <div class="col-md-6">
                                            <h5 class="card-title">Example Data</h5>
                                            <div class="table-responsive">
                                                <table class="table table-bordered">
                                                    <thead>
                                                        <tr>
                                                            <th>names</th>
                                                            <th>email</th>
                                                            <th>phone</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td>John Doe</td>
                                                            <td>john.doe@example.com</td>
                                                            <td>+1234567890</td>
                                                        </tr>
                                                        <tr>
                                                            <td>Jane Smith</td>
                                                            <td>jane.smith@example.com</td>
                                                            <td>+0987654321</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Upload Results Modal -->
    <div class="modal fade" id="uploadResultsModal" tabindex="-1" aria-labelledby="uploadResultsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadResultsModalLabel">Upload Results</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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

    <!-- Include your existing JS files -->
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <!-- Add SheetJS library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

    <script>
    // Function to show upload results in modal
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
        if (result.data && result.data.success_count > 0) {
            html += `
                <div class="card mb-3">
                    <div class="card-header bg-success text-white">
                        <i class="bi bi-check-circle me-2"></i>
                        Successfully Processed Items
                    </div>
                    <div class="card-body">
                        <p class="mb-0">Successfully uploaded ${result.data.success_count} facilities.</p>
                    </div>
                </div>
            `;
        }

        // Show error messages
        if (result.data && result.data.error_messages && result.data.error_messages.length > 0) {
            html += `
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Issues Found
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            ${result.data.error_messages.map(msg => `
                                <li class="list-group-item">
                                    <i class="bi bi-dot me-2"></i>
                                    ${msg}
                                </li>
                            `).join('')}
                        </ul>
                    </div>
                </div>
            `;
        }

        // Show error details if present
        if (result.data && result.data.error_details) {
            html += `
                <div class="card mt-3">
                    <div class="card-header bg-danger text-white">
                        <i class="bi bi-bug me-2"></i>
                        Technical Details
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-3">Message:</dt>
                            <dd class="col-sm-9">${result.data.error_details.message}</dd>
                            
                            <dt class="col-sm-3">File:</dt>
                            <dd class="col-sm-9">${result.data.error_details.file}</dd>
                            
                            <dt class="col-sm-3">Line:</dt>
                            <dd class="col-sm-9">${result.data.error_details.line}</dd>
                        </dl>
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

    // Handle facilities form submission
    document.getElementById('facilitiesForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const submitButton = this.querySelector('button[type="submit"]');
        const originalText = submitButton.innerHTML;
        const file = formData.get('file');
        
        try {
            submitButton.disabled = true;
            submitButton.innerHTML = `
                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                Uploading...
            `;

            // If it's an Excel file, convert it to CSV
            if (file.name.match(/\.(xlsx|xls)$/i)) {
                const csv = await excelToCSV(file);
                const csvBlob = new Blob([csv], { type: 'text/csv' });
                formData.set('file', new File([csvBlob], file.name.replace(/\.(xlsx|xls)$/i, '.csv'), { type: 'text/csv' }));
            }
            
            const response = await fetch('upload_facilities.php', {
                method: 'POST',
                body: formData
            });
            
            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Server response:', text);
                throw new Error('Server returned non-JSON response. Check server logs for details.');
            }
            
            const result = await response.json();
            showUploadResults(result);
            
            if (result.success) {
                this.reset();
            }
        } catch (error) {
            console.error('Upload error:', error);
            showUploadResults({
                success: false,
                message: error.message || 'Failed to process the upload. Please check the file format and try again.',
                data: {
                    error_details: {
                        message: error.message,
                        file: error.fileName || 'Unknown',
                        line: error.lineNumber || 'Unknown'
                    }
                }
            });
        } finally {
            submitButton.disabled = false;
            submitButton.innerHTML = originalText;
        }
    });

    // Handle modules form submission
    document.getElementById('modulesForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const submitButton = this.querySelector('button[type="submit"]');
        const originalText = submitButton.innerHTML;
        const file = formData.get('file');
        
        try {
            submitButton.disabled = true;
            submitButton.innerHTML = `
                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                Uploading...
            `;

            // If it's an Excel file, convert it to CSV
            if (file.name.match(/\.(xlsx|xls)$/i)) {
                const csv = await excelToCSV(file);
                const csvBlob = new Blob([csv], { type: 'text/csv' });
                formData.set('file', new File([csvBlob], file.name.replace(/\.(xlsx|xls)$/i, '.csv'), { type: 'text/csv' }));
            }
            
            const response = await fetch('upload_modules.php', {
                method: 'POST',
                body: formData
            });
            
            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Server response:', text);
                throw new Error('Server returned non-JSON response. Check server logs for details.');
            }
            
            const result = await response.json();
            showUploadResults(result);
            
            if (result.success) {
                this.reset();
            }
        } catch (error) {
            console.error('Upload error:', error);
            showUploadResults({
                success: false,
                message: error.message || 'Failed to process the upload. Please check the file format and try again.',
                data: {
                    error_details: {
                        message: error.message,
                        file: error.fileName || 'Unknown',
                        line: error.lineNumber || 'Unknown'
                    }
                }
            });
        } finally {
            submitButton.disabled = false;
            submitButton.innerHTML = originalText;
        }
    });

    // Handle lecturers form submission
    document.getElementById('lecturersForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const submitButton = this.querySelector('button[type="submit"]');
        const originalText = submitButton.innerHTML;
        const file = formData.get('file');
        
        try {
            submitButton.disabled = true;
            submitButton.innerHTML = `
                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                Uploading...
            `;

            // If it's an Excel file, convert it to CSV
            if (file.name.match(/\.(xlsx|xls)$/i)) {
                const csv = await excelToCSV(file);
                const csvBlob = new Blob([csv], { type: 'text/csv' });
                formData.set('file', new File([csvBlob], file.name.replace(/\.(xlsx|xls)$/i, '.csv'), { type: 'text/csv' }));
            }
            
            const response = await fetch('upload_lecturers.php', {
                method: 'POST',
                body: formData
            });
            
            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Server response:', text);
                throw new Error('Server returned non-JSON response. Check server logs for details.');
            }
            
            const result = await response.json();
            showUploadResults(result);
            
            if (result.success) {
                this.reset();
            }
        } catch (error) {
            console.error('Upload error:', error);
            showUploadResults({
                success: false,
                message: error.message || 'Failed to process the upload. Please check the file format and try again.',
                data: {
                    error_details: {
                        message: error.message,
                        file: error.fileName || 'Unknown',
                        line: error.lineNumber || 'Unknown'
                    }
                }
            });
        } finally {
            submitButton.disabled = false;
            submitButton.innerHTML = originalText;
        }
    });

    // Handle cascading selects for modules
    document.getElementById('moduleCampus').addEventListener('change', async function() {
        const campusId = this.value;
        const collegeSelect = document.getElementById('moduleCollege');
        const schoolSelect = document.getElementById('moduleSchool');
        const departmentSelect = document.getElementById('moduleDepartment');
        const programSelect = document.getElementById('moduleProgram');
        
        // Reset and disable all dependent selects
        collegeSelect.innerHTML = '<option value="">Select College</option>';
        schoolSelect.innerHTML = '<option value="">Select School</option>';
        departmentSelect.innerHTML = '<option value="">Select Department</option>';
        programSelect.innerHTML = '<option value="">Select Program</option>';
        
        if (!campusId) {
            collegeSelect.disabled = true;
            schoolSelect.disabled = true;
            departmentSelect.disabled = true;
            programSelect.disabled = true;
            return;
        }
        
        try {
            const response = await fetch(`get_colleges.php?campus_id=${campusId}`);
            const colleges = await response.json();
            
            if (colleges.length > 0) {
                colleges.forEach(college => {
                    const option = document.createElement('option');
                    option.value = college.id;
                    option.textContent = college.name;
                    collegeSelect.appendChild(option);
                });
                collegeSelect.disabled = false;
            } else {
                showAlert('Info', 'No colleges found for this campus.', 'info');
            }
        } catch (error) {
            showAlert('Error!', 'Failed to load colleges. Please try again.', 'danger');
        }
    });

    document.getElementById('moduleCollege').addEventListener('change', async function() {
        const collegeId = this.value;
        const schoolSelect = document.getElementById('moduleSchool');
        const departmentSelect = document.getElementById('moduleDepartment');
        const programSelect = document.getElementById('moduleProgram');
        
        // Reset and disable all dependent selects
        schoolSelect.innerHTML = '<option value="">Select School</option>';
        departmentSelect.innerHTML = '<option value="">Select Department</option>';
        programSelect.innerHTML = '<option value="">Select Program</option>';
        
        if (!collegeId) {
            schoolSelect.disabled = true;
            departmentSelect.disabled = true;
            programSelect.disabled = true;
            return;
        }
        
        try {
            const response = await fetch(`get_schools.php?college_id=${collegeId}`);
            const schools = await response.json();
            
            if (schools.length > 0) {
                schools.forEach(school => {
                    const option = document.createElement('option');
                    option.value = school.id;
                    option.textContent = school.name;
                    schoolSelect.appendChild(option);
                });
                schoolSelect.disabled = false;
            } else {
                showAlert('Info', 'No schools found for this college.', 'info');
            }
        } catch (error) {
            showAlert('Error!', 'Failed to load schools. Please try again.', 'danger');
        }
    });

    document.getElementById('moduleSchool').addEventListener('change', async function() {
        const schoolId = this.value;
        const departmentSelect = document.getElementById('moduleDepartment');
        const programSelect = document.getElementById('moduleProgram');
        
        // Reset and disable all dependent selects
        departmentSelect.innerHTML = '<option value="">Select Department</option>';
        programSelect.innerHTML = '<option value="">Select Program</option>';
        
        if (!schoolId) {
            departmentSelect.disabled = true;
            programSelect.disabled = true;
            return;
        }
        
        try {
            const response = await fetch(`get_departments.php?school_id=${schoolId}`);
            const departments = await response.json();
            
            if (departments.length > 0) {
                departments.forEach(department => {
                    const option = document.createElement('option');
                    option.value = department.id;
                    option.textContent = department.name;
                    departmentSelect.appendChild(option);
                });
                departmentSelect.disabled = false;
            } else {
                showAlert('Info', 'No departments found for this school.', 'info');
            }
        } catch (error) {
            showAlert('Error!', 'Failed to load departments. Please try again.', 'danger');
        }
    });

    document.getElementById('moduleDepartment').addEventListener('change', async function() {
        const departmentId = this.value;
        const programSelect = document.getElementById('moduleProgram');
        
        // Reset and disable program select
        programSelect.innerHTML = '<option value="">Select Program</option>';
        
        if (!departmentId) {
            programSelect.disabled = true;
            return;
        }
        
        try {
            const response = await fetch(`get_programs.php?department_id=${departmentId}`);
            const programs = await response.json();
            
            if (programs.length > 0) {
                programs.forEach(program => {
                    const option = document.createElement('option');
                    option.value = program.id;
                    option.textContent = program.name;
                    programSelect.appendChild(option);
                });
                programSelect.disabled = false;
            } else {
                showAlert('Info', 'No programs found for this department.', 'info');
            }
        } catch (error) {
            showAlert('Error!', 'Failed to load programs. Please try again.', 'danger');
        }
    });
    </script>
</body>
</html> 