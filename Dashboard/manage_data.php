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

// Get active tab from URL parameter or default to facilities
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'facilities';

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

// Get programs for modules form
$programs_query = "SELECT p.*, d.name as department_name 
                  FROM program p 
                  JOIN department d ON p.department_id = d.id 
                  ORDER BY p.name";
$programs_result = mysqli_query($connection, $programs_query);
$programs = [];
while ($program = mysqli_fetch_assoc($programs_result)) {
    $programs[] = $program;
}
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
            <h1>Manage Data</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item">Data</li>
                    <li class="breadcrumb-item active">Manage</li>
                </ol>
            </nav>
        </div>

        <section class="section">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <!-- Tabs -->
                            <ul class="nav nav-tabs" id="dataTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button onclick="window.location.href='?tab=facilities'" 
                                            class="nav-link <?php echo $active_tab === 'facilities' ? 'active' : ''; ?>" 
                                            id="facilities-tab" 
                                            type="button" 
                                            role="tab">
                                        <i class="bi bi-building me-1"></i>Facilities
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button onclick="window.location.href='?tab=modules'" 
                                            class="nav-link <?php echo $active_tab === 'modules' ? 'active' : ''; ?>" 
                                            id="modules-tab" 
                                            type="button" 
                                            role="tab">
                                        <i class="bi bi-book me-1"></i>Modules
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button onclick="window.location.href='?tab=lecturers'" 
                                            class="nav-link <?php echo $active_tab === 'lecturers' ? 'active' : ''; ?>" 
                                            id="lecturers-tab" 
                                            type="button" 
                                            role="tab">
                                        <i class="bi bi-person me-1"></i>Lecturers
                                    </button>
                                </li>
                            </ul>

                            <!-- Tab Content -->
                            <div class="tab-content pt-4" id="dataTabsContent">
                                <!-- Facilities Tab -->
                                <div class="tab-pane fade <?php echo $active_tab === 'facilities' ? 'show active' : ''; ?>" 
                                     id="facilities" 
                                     role="tabpanel">
                                     
                                    <?php include('manage_facilities.php'); ?>
                                </div>

                                <!-- Modules Tab -->
                                <div class="tab-pane fade <?php echo $active_tab === 'modules' ? 'show active' : ''; ?>" 
                                     id="modules" 
                                     role="tabpanel">
                                    <?php include('manage_modules.php'); ?>
                                </div>

                                <!-- Lecturers Tab -->
                                <div class="tab-pane fade <?php echo $active_tab === 'lecturers' ? 'show active' : ''; ?>" 
                                     id="lecturers" 
                                     role="tabpanel">
                                    <?php include('manage_lecturers.php'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Include your existing JS files -->
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
            // Load initial data based on active tab
        const activeTab = '<?php echo $active_tab; ?>';
        if (activeTab === 'modules') {
            loadModules(1);
        } else if (activeTab === 'lecturers') {
            loadLecturers(1);
        }
    });

    // Function to display module pagination
    function displayModulePagination(pagination) {
        const paginationContainer = document.getElementById('modulePagination');
        if (!paginationContainer) return;

        let html = '<ul class="pagination justify-content-center">';
        
        // Previous button
        html += `
            <li class="page-item ${pagination.current_page === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${pagination.current_page - 1}" onclick="loadModules(${pagination.current_page - 1})">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
        `;

        // Page numbers
        for (let i = 1; i <= pagination.last_page; i++) {
            if (
                i === 1 || // First page
                i === pagination.last_page || // Last page
                (i >= pagination.current_page - 2 && i <= pagination.current_page + 2) // Pages around current
            ) {
                html += `
                    <li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                        <a class="page-link" href="#" data-page="${i}" onclick="loadModules(${i})">${i}</a>
                    </li>
                `;
            } else if (
                i === pagination.current_page - 3 || 
                i === pagination.current_page + 3
            ) {
                html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        // Next button
        html += `
            <li class="page-item ${pagination.current_page === pagination.last_page ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${pagination.current_page + 1}" onclick="loadModules(${pagination.current_page + 1})">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
        `;

        html += '</ul>';
        paginationContainer.innerHTML = html;
    }

    // Function to display lecturer pagination
    function displayLecturerPagination(pagination) {
        const paginationContainer = document.getElementById('lecturerPagination');
        if (!paginationContainer) return;

        let html = '<ul class="pagination justify-content-center">';
        
        // Previous button
        html += `
            <li class="page-item ${pagination.current_page === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${pagination.current_page - 1}" onclick="loadLecturers(${pagination.current_page - 1})">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
        `;

        // Page numbers
        for (let i = 1; i <= pagination.last_page; i++) {
            if (
                i === 1 || // First page
                i === pagination.last_page || // Last page
                (i >= pagination.current_page - 2 && i <= pagination.current_page + 2) // Pages around current
            ) {
                html += `
                    <li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                        <a class="page-link" href="#" data-page="${i}" onclick="loadLecturers(${i})">${i}</a>
                    </li>
                `;
            } else if (
                i === pagination.current_page - 3 || 
                i === pagination.current_page + 3
            ) {
                html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        // Next button
        html += `
            <li class="page-item ${pagination.current_page === pagination.last_page ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${pagination.current_page + 1}" onclick="loadLecturers(${pagination.current_page + 1})">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
        `;

        html += '</ul>';
        paginationContainer.innerHTML = html;
    }

    // Function to load modules
    async function loadModules(page = 1) {
        try {
            const search = document.getElementById('moduleSearch')?.value || '';
            const program = document.getElementById('moduleProgram')?.value || '';
            const year = document.getElementById('moduleYear')?.value || '';
            const semester = document.getElementById('moduleSemester')?.value || '';

            const response = await fetch(`get_modules.php?page=${page}&search=${search}&program=${program}&year=${year}&semester=${semester}`);
            const data = await response.json();

            if (data.success) {
                const modulesContainer = document.getElementById('modulesList');
                if (modulesContainer) {
                    let html = '';
                    data.data.modules.forEach(module => {
                        html += `
                            <tr>
                                <td>${module.code}</td>
                                <td>${module.name}</td>
                                <td>${module.credits}</td>
                                <td>Year ${module.year}</td>
                                <td>Semester ${module.semester}</td>
                                <td>${module.program_name}</td>
                                <td>${module.department_name}</td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="editModule(${module.id})">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteModule(${module.id})">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                    modulesContainer.innerHTML = html;
                }
                displayModulePagination(data.data.pagination);
            } else {
                alert(data.message || 'Failed to load modules');
            }
        } catch (error) {
            console.error('Error loading modules:', error);
            alert('Failed to load modules. Please try again.');
        }
    }

    // Function to load lecturers
    async function loadLecturers(page = 1) {
        try {
            const search = document.getElementById('lecturerSearch')?.value || '';
            const status = document.getElementById('lecturerStatus')?.value || '';

            const response = await fetch(`get_lecturers.php?page=${page}&search=${search}&status=${status}`);
            const data = await response.json();

            if (data.success) {
                const lecturersContainer = document.getElementById('lecturersList');
                if (lecturersContainer) {
                    let html = '';
                    data.data.lecturers.forEach(lecturer => {
                        html += `
                            <tr>
                                <td>${lecturer.names}</td>
                                <td>${lecturer.email}</td>
                                <td>${lecturer.phone || 'Not set'}</td>
                                <td>
                                    <span class="badge ${lecturer.active ? 'bg-success' : 'bg-danger'}">
                                        ${lecturer.active ? 'Active' : 'Inactive'}
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="editLecturer(${lecturer.id})">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteLecturer(${lecturer.id})">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                    lecturersContainer.innerHTML = html;
                }
                displayLecturerPagination(data.data.pagination);
            } else {
                alert(data.message || 'Failed to load lecturers');
            }
        } catch (error) {
            console.error('Error loading lecturers:', error);
            alert('Failed to load lecturers. Please try again.');
        }
    }

    // Function to edit module
    function editModule(id) {
        window.location.href = `edit_module.php?id=${id}`;
    }

    // Function to delete module
    async function deleteModule(id) {
        if (confirm('Are you sure you want to delete this module?')) {
            try {
                const response = await fetch('delete_module.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: id })
                });
                const data = await response.json();
                if (data.success) {
                    alert('Module deleted successfully');
                    loadModules(1);
                } else {
                    alert(data.message || 'Failed to delete module');
                }
            } catch (error) {
                console.error('Error deleting module:', error);
                alert('Failed to delete module. Please try again.');
            }
        }
    }

    // Function to edit lecturer
    function editLecturer(id) {
        window.location.href = `edit_lecturer.php?id=${id}`;
    }

    // Function to delete lecturer
    async function deleteLecturer(id) {
        if (confirm('Are you sure you want to delete this lecturer?')) {
            try {
                const response = await fetch('delete_lecturer.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: id })
                });
                const data = await response.json();
                if (data.success) {
                    alert('Lecturer deleted successfully');
                    loadLecturers(1);
                } else {
                    alert(data.message || 'Failed to delete lecturer');
                }
            } catch (error) {
                console.error('Error deleting lecturer:', error);
                alert('Failed to delete lecturer. Please try again.');
            }
        }
    }
    </script>
</body>
</html> 