<?php
session_start();
include("connection.php");

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header("Location: index.php");
    exit();
}
// only admin can access this page
if ($_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['id'];
$is_admin = $_SESSION['role'] === 'admin';

// Get user's campus if not admin
$campus_id = null;
if (!$is_admin) {
    $stmt = $connection->prepare("SELECT campus FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $campus = $result->fetch_assoc();
    $campus_id = $campus ? $campus['campus'] : null;
    
    if (!$campus_id) {
        die("Your account is not associated with any campus.");
    }
}
$message = '';
$editing_site = null;
$current_site_id = null;

// Get all colleges and schools for the campus (or all colleges if admin)
function getCampusCollegesAndSchools($connection, $campus_id = null) {
    $data = ['colleges' => [], 'schools' => []];
    
    // Get colleges - since college no longer has campus_id, get all colleges
    $college_query = "SELECT c.*, c.name as college_name FROM college c";
    
    $college_result = mysqli_query($connection, $college_query);
    if ($college_result) {
        while ($row = mysqli_fetch_assoc($college_result)) {
            $data['colleges'][$row['id']] = $row;
        }
    }

    // Get schools for these colleges
    if (!empty($data['colleges'])) {
        $college_ids = implode(",", array_keys($data['colleges']));
        $school_query = "SELECT s.*, c.name as college_name 
                        FROM school s 
                        JOIN college c ON s.college_id = c.id 
                        WHERE s.college_id IN ($college_ids) ORDER BY c.name, s.name";
        $school_result = mysqli_query($connection, $school_query);
        if ($school_result) {
            while ($row = mysqli_fetch_assoc($school_result)) {
                $data['schools'][$row['college_id']][] = $row;
            }
        }
    }
    
    return $data;
}

// Get schools by campus for modal
function getSchoolsByCampus($connection, $campus_id) {
    $schools = [];
    
    // Get all colleges first
    $college_query = "SELECT id, name FROM college ORDER BY name";
    $college_result = mysqli_query($connection, $college_query);
    $colleges = [];
    
    while ($college = mysqli_fetch_assoc($college_result)) {
        $colleges[$college['id']] = $college;
        $colleges[$college['id']]['schools'] = [];
    }
    
    if (!empty($colleges)) {
        $college_ids = implode(',', array_keys($colleges));
        $school_query = "SELECT s.*, c.name as college_name 
                        FROM school s 
                        JOIN college c ON s.college_id = c.id 
                        WHERE s.college_id IN ($college_ids) 
                        ORDER BY c.name, s.name";
        
        $school_result = mysqli_query($connection, $school_query);
        if ($school_result) {
            while ($row = mysqli_fetch_assoc($school_result)) {
                if (isset($colleges[$row['college_id']])) {
                    $colleges[$row['college_id']]['schools'][] = $row;
                }
            }
        }
    }
    
    return array_values($colleges);
}

// Get assigned schools for a site
function getAssignedSchools($connection, $site_id) {
    $assigned = [];
    $query = "SELECT school_id FROM site_school WHERE site_id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $site_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $assigned[$row['school_id']] = true;
    }
    return $assigned;
}

// Add new site
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_site'])) {
    $name = mysqli_real_escape_string($connection, $_POST['name']);
    
    // Get campus from form for admin, or use user's campus for non-admin
    $selected_campus_id = $is_admin ? (int)($_POST['campus'] ?? 0) : $campus_id;
    
    if (empty($name)) {
        $message = '<div class="alert alert-warning">Please enter a site name</div>';
    } elseif ($is_admin && empty($selected_campus_id)) {
        $message = '<div class="alert alert-warning">Please select a campus</div>';
    } else {
        mysqli_begin_transaction($connection);
        try {
            $query = "INSERT INTO site (name, campus) VALUES (?, ?)";
            $stmt = $connection->prepare($query);
            $stmt->bind_param("si", $name, $selected_campus_id);
            if ($stmt->execute()) {
                $site_id = mysqli_insert_id($connection);
                
                // Process school assignments if any
                if (isset($_POST['schools']) && is_array($_POST['schools'])) {
                    $school_stmt = $connection->prepare("INSERT INTO site_school (site_id, school_id) VALUES (?, ?)");
                    foreach ($_POST['schools'] as $school_id) {
                        $school_id = (int)$school_id;
                        $school_stmt->bind_param("ii", $site_id, $school_id);
                        $school_stmt->execute();
                    }
                    $school_stmt->close();
                }
                
                mysqli_commit($connection);
                $message = '<div class="alert alert-success">Site added successfully!</div>';
            } else {
                throw new Exception($stmt->error);
            }
        } catch (Exception $e) {
            mysqli_rollback($connection);
            $message = '<div class="alert alert-danger">Error adding site: ' . $e->getMessage() . '</div>';
        }
    }
}

// Update site
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_site'])) {
    $id = (int)$_POST['id'];
    $name = mysqli_real_escape_string($connection, $_POST['name']);
    $selected_campus_id = $is_admin ? (int)$_POST['campus'] : $campus_id;
    
    if (empty($name)) {
        $message = '<div class="alert alert-warning">Please enter a site name</div>';
    } elseif ($is_admin && empty($selected_campus_id)) {
        $message = '<div class="alert alert-warning">Please select a campus</div>';
    } elseif ($id > 0) {
        $query = "UPDATE site SET name = ?, campus = ? WHERE id = ?";
        $stmt = $connection->prepare($query);
        $stmt->bind_param("sii", $name, $selected_campus_id, $id);
        if ($stmt->execute()) {
            $message = '<div class="alert alert-success">Site updated successfully!</div>';
        } else {
            $message = '<div class="alert alert-danger">Error updating site: ' . $stmt->error . '</div>';
        }
    }
}

// Update school assignments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_schools'])) {
    $site_id = (int)$_POST['site_id'];
    $schools = isset($_POST['schools']) ? $_POST['schools'] : [];
    
    if ($site_id > 0) {
        // First, get the campus_id for this site to verify school assignments
        $site_query = "SELECT campus FROM site WHERE id = ?";
        $stmt = $connection->prepare($site_query);
        $stmt->bind_param("i", $site_id);
        $stmt->execute();
        $site = $stmt->get_result()->fetch_assoc();
        
        if (!$site) {
            $message = '<div class="alert alert-danger">Error: Site not found</div>';
        } else {
            $campus_id = $site['campus'];
            
            mysqli_begin_transaction($connection);
            try {
                // Remove all current assignments
                $delete_stmt = $connection->prepare("DELETE FROM site_school WHERE site_id = ?");
                $delete_stmt->bind_param("i", $site_id);
                $delete_stmt->execute();
                
                if (!empty($schools)) {
                    // Allow any school assignment since college doesn't have campus_id
                    $placeholders = str_repeat('?,', count($schools));
                    $placeholders = rtrim($placeholders, ',');
                    
                    $valid_schools_query = "SELECT id FROM school WHERE id IN ($placeholders)";
                    $stmt = $connection->prepare($valid_schools_query);
                    $types = str_repeat('i', count($schools));
                    $school_ids = array_map('intval', $schools);
                    $stmt->bind_param($types, ...$school_ids);
                    $stmt->execute();
                    $valid_schools = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    
                    // Insert valid school assignments
                    if (!empty($valid_schools)) {
                        $insert_query = "INSERT INTO site_school (site_id, school_id) VALUES (?, ?)";
                        $insert_stmt = $connection->prepare($insert_query);
                        
                        foreach ($valid_schools as $school) {
                            $insert_stmt->bind_param("ii", $site_id, $school['id']);
                            $insert_stmt->execute();
                        }
                        $insert_stmt->close();
                    }
                }
                
                mysqli_commit($connection);
                $message = '<div class="alert alert-success">School assignments updated successfully!</div>';
            } catch (Exception $e) {
                mysqli_rollback($connection);
                $message = '<div class="alert alert-danger">Error updating school assignments: ' . $e->getMessage() . '</div>';
            }
        }
    }
}

// Delete site
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id > 0) {
        mysqli_begin_transaction($connection);
        try {
            // First delete school assignments
            mysqli_query($connection, "DELETE FROM site_school WHERE site_id = $id");
            
            // Then delete the site
            $query = "DELETE FROM site WHERE id = $id";
            if (!$is_admin) {
                $query .= " AND campus = '$campus_id'";
            }
            if (mysqli_query($connection, $query)) {
                mysqli_commit($connection);
                $message = '<div class="alert alert-success">Site deleted successfully!</div>';
            } else {
                throw new Exception(mysqli_error($connection));
            }
        } catch (Exception $e) {
            mysqli_rollback($connection);
            $message = '<div class="alert alert-danger">Error deleting site: ' . $e->getMessage() . '</div>';
        }
    }
}

// Set editing mode
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    if ($edit_id > 0) {
        $query = "SELECT * FROM site WHERE id = ?";
        if (!$is_admin) {
            $query .= " AND campus = ?";
        }
        $stmt = $connection->prepare($query);
        
        if ($is_admin) {
            $stmt->bind_param("i", $edit_id);
        } else {
            $stmt->bind_param("ii", $edit_id, $campus_id);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $editing_site = $result->fetch_assoc()) {
            // Site found and belongs to user's campus (or user is admin)
        } else {
            $message = '<div class="alert alert-warning">Site not found or you don\'t have permission to edit it.</div>';
            $editing_site = null;
        }
    }
}

// Get campus data for school management
$campus_data = getCampusCollegesAndSchools($connection, $campus_id);
$colleges = $campus_data['colleges'];
$schools = $campus_data['schools'];

// Get sites (all for admin, or filtered by campus for non-admin)
$query = "SELECT s.*, 
          camp.name as campus_name,
          (SELECT GROUP_CONCAT(DISTINCT sch.name SEPARATOR ', ') 
           FROM site_school ss 
           JOIN school sch ON ss.school_id = sch.id 
           WHERE ss.site_id = s.id) as assigned_schools,
          (SELECT COUNT(*) FROM site_school WHERE site_id = s.id) as school_count
          FROM site s
          JOIN campus camp ON s.campus = camp.id";
          
if (!$is_admin && $campus_id) {
    $query .= " WHERE s.campus = " . (int)$campus_id;
}

$query .= " ORDER BY camp.name, s.name";
$result = mysqli_query($connection, $query);
$sites = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $sites[] = $row;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Manage Sites - UR-TIMETABLE</title>
    <link href="assets/img/icon1.png" rel="icon">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .school-badge {
            font-size: 0.8em;
            margin: 2px;
            cursor: pointer;
        }
        .action-buttons .btn {
            margin: 0 2px;
        }
        .form-container {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .school-checkbox {
            margin-right: 10px;
        }
        .college-section {
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
        }
        .college-name {
            font-weight: bold;
            margin-bottom: 10px;
            color: #0d6efd;
        }
        .modal-body {
            max-height: 60vh;
            overflow-y: auto;
        }
    </style>
</head>

<body>
    <?php include("./includes/header.php"); ?>
    <?php include("./includes/menu.php"); ?>

    <main id="main" class="main">
        <div class="pagetitle">
            <h1>Manage Sites</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item active">Manage Sites</li>
                </ol>
            </nav>
        </div>

        <section class="section">
            <div class="row">
                <div class="col-lg-12">
                    <?php echo $message; ?>
                    
                    <!-- Add/Edit Site Form -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo $editing_site ? 'Edit Site' : 'Add New Site'; ?></h5>
                            <form method="POST" action="">
                                <?php if ($editing_site): ?>
                                    <input type="hidden" name="id" value="<?php echo $editing_site['id']; ?>">
                                <?php endif; ?>
                                <div class="row">
                                    <div class="col-md-8">
                                        <input type="text" class="form-control" name="name" 
                                               value="<?php echo $editing_site ? htmlspecialchars($editing_site['name']) : ''; ?>" 
                                               placeholder="Enter site name" required>
                                    </div>
                                    <div class="col-md-4">
                                        <?php if ($is_admin): ?>
                                        <select name="campus" id="campus" class="form-select mb-3" required>
                                            <option value="">Select Campus</option>
                                            <?php 
                                            $campuses_query = "SELECT * FROM campus ORDER BY name";
                                            $campuses = mysqli_query($connection, $campuses_query);
                                            while($c = mysqli_fetch_assoc($campuses)) {
                                                $selected = (isset($editing_site) && $editing_site['campus'] == $c['id']) ? 'selected' : '';
                                                echo "<option value='{$c['id']}' $selected>{$c['name']}</option>";
                                            }
                                            ?>
                                        </select>
                                        <?php else: ?>
                                        <input type="hidden" name="campus" value="<?php echo $campus_id; ?>">
                                        <?php endif; ?>
                                        <?php if ($editing_site): ?>
                                            <button type="submit" name="update_site" class="btn btn-primary">
                                                <i class="bi bi-check-circle"></i> Update Site
                                            </button>
                                            <a href="manage_sites.php" class="btn btn-secondary">
                                                <i class="bi bi-x-circle"></i> Cancel
                                            </a>
                                        <?php else: ?>
                                            <button type="submit" name="add_site" class="btn btn-primary">
                                                <i class="bi bi-plus-circle"></i> Add Site
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Sites Table -->
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">All Sites</h5>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Name</th>
                                            <?php if ($is_admin): ?>
                                            <th>Campus</th>
                                            <?php endif; ?>
                                            <th>Assigned Schools</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $no = 1;
                                        foreach ($sites as $site):    
                                            $assigned_schools = getAssignedSchools($connection, $site['id']);

                                        ?>
                                        <tr>
                                            <td><?php echo $no++; ?></td>
                                            <td><?php echo htmlspecialchars($site['name']); ?></td>
                                            <?php if ($is_admin): ?>
                                            <td><?php echo htmlspecialchars($site['campus_name']); ?></td>
                                            <?php endif; ?>
                                            <td>
                                                <?php if (!empty($assigned_schools)): 
                                                    $school_names = [];
                                                    $school_ids = array_keys($assigned_schools);
                                                    $school_ids_str = implode(',', $school_ids);
                                                    $school_query = "SELECT name FROM school WHERE id IN ($school_ids_str)";
                                                    $school_result = mysqli_query($connection, $school_query);
                                                    while ($school = mysqli_fetch_assoc($school_result)) {
                                                        $school_names[] = $school['name'];
                                                    }
                                                    
                                                    foreach (array_slice($school_names, 0, 3) as $name): ?>
                                                        <span class="badge bg-primary school-badge" data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($name); ?>">
                                                            <?php echo htmlspecialchars(strlen($name) > 15 ? substr($name, 0, 12) . '...' : $name); ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                    <?php if (count($school_names) > 3): ?>
                                                        <span class="badge bg-secondary">+<?php echo count($school_names) - 3; ?> more</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">No schools assigned</span>
                                                <?php endif; ?>

                                            </td>
                                            <td class="action-buttons">
                                                <a href="?edit=<?php echo $site['id']; ?>" class="btn btn-sm btn-outline-primary" 
                                                >
                                                    <i class="bi bi-pencil"></i> Edit
                                                </a>
                                                <a href="#" class="btn btn-sm btn-info manage-schools" 
                                                   data-bs-toggle="modal" data-bs-target="#manageSchoolsModal"
                                                   data-site-id="<?php echo $site['id']; ?>"
                                                   data-site-name="<?php echo htmlspecialchars($site['name']); ?>"
                                                   data-campus-id="<?php echo $site['campus']; ?>">
                                                    <i class="bi bi-building"></i> Manage Schools
                                                </a>
                                                <a href="?delete=<?php echo $site['id']; ?>" class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('Are you sure you want to delete this site?')">
                                                    <i class="bi bi-trash"></i> Delete
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-info">
                                                       <a href="upload_facilities.php?site_id=<?php echo $site['id']; ?>">
                                                    <i class="bi bi-building"></i> upload facilities
                                                    </a>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($sites)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center">No sites found for your campus.</td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Manage Schools Modal -->
    <div class="modal fade" id="manageSchoolsModal" tabindex="-1" aria-labelledby="manageSchoolsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title" id="manageSchoolsModalLabel">Manage School Assignments</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="schoolAssignmentForm" method="POST" action="">
                    <input type="hidden" name="site_id" id="modalSiteId">
                    <div class="modal-body">
                        <div class="alert alert-info mb-3">
                            <i class="bi bi-info-circle"></i> Check/uncheck schools to update assignments for: 
                            <strong id="siteNameDisplay"></strong>
                        </div>
                        
                        <!-- Tabs for Assigned/All Schools -->
                        <ul class="nav nav-tabs mb-3" id="schoolTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="assigned-tab" data-bs-toggle="tab" 
                                        data-bs-target="#assigned-schools" type="button" role="tab">
                                    Assigned Schools <span id="assignedCount" class="badge bg-primary ms-1">0</span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="all-tab" data-bs-toggle="tab" 
                                        data-bs-target="#all-schools" type="button" role="tab">
                                    All Schools <span id="totalCount" class="badge bg-secondary ms-1">0</span>
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="schoolTabsContent">
                            <!-- Assigned Schools Tab -->
                            <div class="tab-pane fade show active" id="assigned-schools" role="tabpanel">
                                <div id="assignedSchoolsList" class="mb-3">
                                    <!-- Will be populated by JavaScript -->
                                    <div class="text-muted text-center py-3">No schools assigned yet.</div>
                                </div>
                                <div id="noAssignedSchools" class="text-center text-muted">Select schools from the "All Schools" tab</div>
                            </div>
                            
                            <!-- All Schools Tab -->
                            <div class="tab-pane fade" id="all-schools" role="tabpanel">
                                <div class="mb-3">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                        <input type="text" id="schoolSearch" class="form-control" placeholder="Search by school or college name...">
                                    </div>
                                </div>
                                <div id="allSchoolsList" class="schools-container">
                                    <!-- Will be populated by JavaScript -->
                                    <div class="text-center text-muted py-3">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p class="mt-2">Loading schools...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg"></i> Close
                        </button>
                        <button type="submit" name="update_schools" class="btn btn-primary">
                            <i class="bi bi-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        .college-card {
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            margin-bottom: 1rem;
            overflow: hidden;
        }
        .college-header {
            background-color: #f8f9fa;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #dee2e6;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .college-header h6 {
            margin: 0;
            font-weight: 600;
        }
        .schools-list {
            padding: 0.5rem 1rem;
            background-color: #fff;
        }
        .school-item {
            padding: 0.5rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .school-item:last-child {
            border-bottom: none;
        }
        .assigned-school {
            background-color: #e8f4fd;
            border-left: 3px solid #0d6efd;
            padding-left: 0.5rem;
        }
        .search-highlight {
            background-color: #fff3cd;
            padding: 0 2px;
            border-radius: 3px;
        }
        .schools-container {
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
    <!-- JavaScript for School Management -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('manageSchoolsModal');
            const schoolCheckboxes = document.querySelectorAll('.school-checkbox');
            const assignedSchoolsList = document.getElementById('assignedSchoolsList');
            const noAssignedSchools = document.getElementById('noAssignedSchools');
            const assignedCount = document.getElementById('assignedCount');
            const totalCount = document.getElementById('totalCount');
            
            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Update total count
            totalCount.textContent = schoolCheckboxes.length;

            // Function to update assigned schools list
            function updateAssignedSchoolsList() {
                const checkedBoxes = document.querySelectorAll('.school-checkbox:checked');
                assignedCount.textContent = checkedBoxes.length;
                
                // Clear current list
                assignedSchoolsList.innerHTML = '';
                
                if (checkedBoxes.length === 0) {
                    noAssignedSchools.style.display = 'block';
                    return;
                }
                
                noAssignedSchools.style.display = 'none';
                
                // Group by college
                const schoolsByCollege = {};
                checkedBoxes.forEach(checkbox => {
                    const college = checkbox.dataset.college;
                    const schoolName = checkbox.dataset.schoolName;
                    const schoolId = checkbox.value;
                    
                    if (!schoolsByCollege[college]) {
                        schoolsByCollege[college] = [];
                    }
                    schoolsByCollege[college].push({id: schoolId, name: schoolName});
                });
                
                // Create list items
                for (const [college, schools] of Object.entries(schoolsByCollege)) {
                    const collegeHeader = document.createElement('div');
                    collegeHeader.className = 'fw-bold text-primary mb-1';
                    collegeHeader.innerHTML = `<i class="bi bi-building"></i> ${college}`;
                    assignedSchoolsList.appendChild(collegeHeader);
                    
                    const schoolList = document.createElement('div');
                    schoolList.className = 'ms-3 mb-2';
                    
                    schools.forEach(school => {
                        const schoolItem = document.createElement('div');
                        schoolItem.className = 'd-flex justify-content-between align-items-center py-1';
                        schoolItem.innerHTML = `
                            <span>${school.name}</span>
                            <button type="button" class="btn btn-sm btn-outline-danger btn-remove" 
                                    data-school-id="${school.id}" title="Remove">
                                <i class="bi bi-trash"></i>
                            </button>
                        `;
                        schoolList.appendChild(schoolItem);
                    });
                    
                    assignedSchoolsList.appendChild(schoolList);
                }
                
                // Add event listeners to remove buttons
                document.querySelectorAll('.btn-remove').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const schoolId = this.dataset.schoolId;
                        const checkbox = document.querySelector(`.school-checkbox[value="${schoolId}"]`);
                        if (checkbox) {
                            checkbox.checked = false;
                            updateAssignedSchoolsList();
                        }
                    });
                });
            }

            // Function to highlight search terms in text
            function highlightText(text, searchTerm) {
                if (!searchTerm) return text;
                const regex = new RegExp(`(${searchTerm})`, 'gi');
                return text.replace(regex, '<span class="search-highlight">$1</span>');
            }

            // Function to render schools list
            function renderSchools(schools, assignedSchoolIds, searchTerm = '') {
                const container = document.getElementById('allSchoolsList');
                const assignedContainer = document.getElementById('assignedSchoolsList');
                
                if (!schools || schools.length === 0) {
                    container.innerHTML = '<div class="text-center text-muted py-3">No schools found for this campus.</div>';
                    return;
                }

                // Group schools by college
                const colleges = {};
                schools.forEach(school => {
                    if (!colleges[school.college_id]) {
                        colleges[school.college_id] = {
                            id: school.college_id,
                            name: school.college_name,
                            schools: []
                        };
                    }
                    colleges[school.college_id].schools.push(school);
                });

                // Render colleges and schools
                let html = '';
                let assignedHtml = '';
                
                Object.values(colleges).forEach(college => {
                    let collegeVisible = false;
                    let collegeSchoolsHtml = '';
                    
                    college.schools.forEach(school => {
                        const isAssigned = assignedSchoolIds.has(parseInt(school.id));
                        const displayName = searchTerm 
                            ? highlightText(school.name, searchTerm) 
                            : school.name;
                        
                        if (searchTerm && 
                            !school.name.toLowerCase().includes(searchTerm) && 
                            !college.name.toLowerCase().includes(searchTerm)) {
                            return; // Skip if no match
                        }
                        
                        collegeVisible = true;
                        
                        const schoolItem = `
                            <div class="school-item ${isAssigned ? 'assigned-school' : ''}">
                                <div class="form-check">
                                    <input class="form-check-input school-checkbox" 
                                           type="checkbox" 
                                           value="${school.id}" 
                                           id="school_${school.id}"
                                           ${isAssigned ? 'checked' : ''}>
                                    <label class="form-check-label" for="school_${school.id}">
                                        ${displayName}
                                    </label>
                                </div>
                            </div>
                        `;
                        
                        collegeSchoolsHtml += schoolItem;
                        
                        if (isAssigned) {
                            assignedHtml += `
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>${college.name} - ${school.name}</span>
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-school" 
                                            data-school-id="${school.id}">
                                        <i class="bi bi-x"></i>
                                    </button>
                                </div>
                            `;
                        }
                    });
                    
                    if (collegeVisible) {
                        const displayCollegeName = searchTerm 
                            ? highlightText(college.name, searchTerm)
                            : college.name;
                            
                        html += `
                            <div class="college-card">
                                <div class="college-header" data-bs-toggle="collapse" 
                                     data-bs-target="#college-${college.id}" aria-expanded="true">
                                    <h6>${displayCollegeName}</h6>
                                    <i class="bi bi-chevron-down"></i>
                                </div>
                                <div id="college-${college.id}" class="collapse show">
                                    <div class="schools-list">
                                        ${collegeSchoolsHtml}
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                });
                
                container.innerHTML = html || '<div class="text-center text-muted py-3">No matching schools found.</div>';
                
                if (assignedHtml) {
                    assignedContainer.innerHTML = `
                        <div class="list-group">${assignedHtml}</div>
                    `;
                    document.getElementById('noAssignedSchools').classList.add('d-none');
                } else {
                    assignedContainer.innerHTML = '<div class="text-muted text-center py-3">No schools assigned yet.</div>';
                    document.getElementById('noAssignedSchools').classList.remove('d-none');
                }
                
                // Add event listeners to new checkboxes
                document.querySelectorAll('.school-checkbox').forEach(checkbox => {
                    checkbox.addEventListener('change', updateAssignedSchoolsList);
                });
                
                // Add event listeners to remove buttons
                document.querySelectorAll('.btn-remove-school').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const schoolId = this.getAttribute('data-school-id');
                        const checkbox = document.querySelector(`.school-checkbox[value="${schoolId}"]`);
                        if (checkbox) {
                            checkbox.checked = false;
                            updateAssignedSchoolsList();
                        }
                    });
                });
                
                // Initialize collapse toggles
                document.querySelectorAll('.college-header').forEach(header => {
                    header.addEventListener('click', function() {
                        const icon = this.querySelector('i');
                        icon.classList.toggle('bi-chevron-down');
                        icon.classList.toggle('bi-chevron-up');
                    });
                });
            }

            // Handle modal show event
            modal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const siteId = button.getAttribute('data-site-id');
                const siteName = button.getAttribute('data-site-name');
                const campusId = button.getAttribute('data-campus-id');
                
                // Update the modal title and hidden field
                document.getElementById('modalSiteId').value = siteId;
                document.getElementById('siteNameDisplay').textContent = siteName;

                // Show loading state
                const allSchoolsContainer = document.getElementById('allSchoolsList');
                allSchoolsContainer.innerHTML = `
                    <div class="text-center text-muted py-3">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading schools...</p>
                    </div>
                `;

                // Show loading state
                allSchoolsContainer.innerHTML = `
                    <div class="text-center py-3">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading school data...</p>
                    </div>
                `;

                // Function to show error message
                const showError = (message) => {
                    console.error('School Loading Error:', message);
                    allSchoolsContainer.innerHTML = `
                        <div class="alert alert-danger">
                            <h5><i class="bi bi-exclamation-triangle"></i> Error Loading Schools</h5>
                            <p>${message}</p>
                            <p class="mb-0">Please check the console for more details.</p>
                        </div>
                    `;
                };

                // Fetch schools for this site's campus
                const fetchSchools = fetch(`get_schools_by_campus.php?campus_id=${campusId}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .catch(error => {
                        showError(`Failed to load schools: ${error.message}`);
                        throw error; // Re-throw to be caught by Promise.all
                    });

                // Fetch assigned schools
                const fetchAssignedSchools = fetch(`get_assigned_schools.php?site_id=${siteId}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .catch(error => {
                        showError(`Failed to load assigned schools: ${error.message}`);
                        throw error; // Re-throw to be caught by Promise.all
                    });

                Promise.all([fetchSchools, fetchAssignedSchools])
                    .then(([schools, assignedSchools]) => {
                        if (schools.error) {
                            throw new Error(schools.error);
                        }
                        if (assignedSchools.error) {
                            throw new Error(assignedSchools.error);
                        }

                        const assignedSchoolIds = new Set(assignedSchools.assigned.map(id => parseInt(id)));
                        renderSchools(schools, assignedSchoolIds);
                        
                        // Initialize search functionality
                        const searchInput = document.getElementById('schoolSearch');
                        searchInput.addEventListener('input', function() {
                            const searchTerm = this.value.toLowerCase().trim();
                            renderSchools(schools, assignedSchoolIds, searchTerm);
                        });
                    })
                    .catch(error => {
                        if (!error.message.includes('Failed to load')) {
                            showError(`Error: ${error.message || 'Unknown error occurred'}`);
                        }
                    });
            });
            
            // Update assigned schools list when checkboxes change
            schoolCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateAssignedSchoolsList);
            });
            
            // Handle form submission
            const form = document.getElementById('schoolAssignmentForm');
            form.addEventListener('submit', function(e) {
                e.preventDefault(); // Prevent default form submission
                
                const formData = new FormData(this);
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn.innerHTML;
                
                // Get all checked school checkboxes
                const checkedBoxes = document.querySelectorAll('.school-checkbox:checked');
                checkedBoxes.forEach(checkbox => {
                    formData.append('schools[]', checkbox.value);
                });
                
                // Add the update_schools flag
                formData.append('update_schools', '1');
                
                // Show loading state
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
                
                // Submit form data using fetch
                fetch('manage_sites.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(html => {
                    // Show success message
                    const successAlert = document.createElement('div');
                    successAlert.className = 'alert alert-success';
                    successAlert.innerHTML = '<i class="bi bi-check-circle"></i> School assignments updated successfully!';
                    
                    // Insert success message at the top of the page
                    const mainContent = document.querySelector('main');
                    if (mainContent) {
                        mainContent.insertBefore(successAlert, mainContent.firstChild);
                        
                        // Scroll to top to show the message
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        
                        // Remove the alert after 5 seconds
                        setTimeout(() => {
                            successAlert.remove();
                        }, 5000);
                    }
                    
                    // Close the modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('manageSchoolsModal'));
                    if (modal) {
                        modal.hide();
                    }
                    
                    // Reload the page to reflect changes
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Show error message
                    const errorAlert = document.createElement('div');
                    errorAlert.className = 'alert alert-danger';
                    errorAlert.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Error saving school assignments. Please try again.';
                    
                    // Insert error message at the top of the form
                    const modalBody = document.querySelector('.modal-body');
                    if (modalBody) {
                        // Remove any existing error messages first
                        const existingAlerts = modalBody.querySelectorAll('.alert');
                        existingAlerts.forEach(alert => alert.remove());
                        
                        modalBody.insertBefore(errorAlert, modalBody.firstChild);
                        
                        // Remove the alert after 5 seconds
                        setTimeout(() => {
                            errorAlert.remove();
                        }, 5000);
                    }
                })
                .finally(() => {
                    // Reset button state
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                });
            });
        });
    </script>
    <!-- Load Bootstrap JS first -->
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>