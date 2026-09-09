<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Organization Structure - UR-TIMETABLE</title>

<link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">

<style>
/* Tree View CSS */
.tree-view {
    margin: 20px 0;
    font-family: 'Poppins', sans-serif;
}

.tree-view ul {
    list-style: none !important;
    padding-left: 30px;
    position: relative;
}

.tree-view ul:before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 1px;
    background:rgb(211, 224, 238);
}

.tree-view li {
    margin: 12px 0;
    position: relative;
    list-style: none !important;
}

.tree-view li:before {
    content: '';
    position: absolute;
    left: -30px;
    top: 50%;
    width: 20px;
    height: 1px;
    background:rgb(173, 188, 202);
}

/* Remove default list markers */
.tree-view li::marker {
    display: none !important;
}

/* Custom styling for different levels */
.tree-view .level-school ul ul {
    padding-left: 25px;
}

.tree-view .level-school ul ul:before {
    left: -5px;
}

/* Toggle icons */
.tree-view .toggle {
    cursor: pointer;
    margin-right: 8px;
    transition: transform 0.2s;
    display: inline-block;
    width: 20px;
    height: 20px;
    text-align: center;
    line-height: 20px;
    border-radius: 50%;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
}

.tree-view .toggle:hover {
    background: rgb(169, 231, 193);
}

/* Content styling */
.tree-view .content {
    display: inline-block;
    padding: 8px 15px;
    border-radius: 6px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    transition: all 0.2s;
    min-width: 200px;
}

.tree-view .content:hover {
    transform: translateX(5px);
}

/* Collapsed state */
.tree-view .collapsed {
    display: none;
}

/* Badge styling */
.tree-view .badge {
    margin-left: 8px;
    font-weight: 500;
    padding: 5px 10px;
}

/* Level-specific colors */
.tree-view .level-campus .content {
    background:rgb(252, 252, 252);
    border-color: #90caf9;
}

.tree-view .level-campus .badge {
    background: #1976d2;
    color: white;
}

.tree-view .level-college .content {
    background: #e8f5e9;
    border-color:rgb(214, 181, 165);
}

.tree-view .level-college .badge {
    background: #2e7d32;
    color: white;
}

.tree-view .level-school .content {
    background: #fff3e0;
    border-color: #ffcc80;
}

.tree-view .level-school .badge {
    background: #f57c00;
    color: white;
}

.tree-view .level-department .content {
    background: #f3e5f5;
    border-color: #ce93d8;
}

.tree-view .level-department .badge {
    background: #7b1fa2;
    color: white;
}

.tree-view .level-program .content {
    background: #e0f7fa;
    border-color: #80deea;
}

.tree-view .level-program .badge {
    background: #0097a7;
    color: white;
}

.tree-view .level-intake .content {
    background: #ffebee;
    border-color: #ef9a9a;
}

.tree-view .level-intake .badge {
    background: #c62828;
    color: white;
}

.tree-view .level-group .content {
    background: #e8eaf6;
    border-color: #9fa8da;
}

.tree-view .level-group .badge {
    background: #3949ab;
    color: white;
}

/* Count badges */
.tree-view .count-badge {
    margin-left: 8px;
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.8em;
    opacity: 0.8;
}

.level-campus .count-badge { background: #1976d2; }
.level-college .count-badge { background: #2e7d32; }
.level-school .count-badge { background: #f57c00; }
.level-department .count-badge { background: #7b1fa2; }
.level-program .count-badge { background: #0097a7; }
.level-intake .count-badge { background: #c62828; }
.level-group .count-badge { background: #3949ab; }

/* Reset Button Styles */
.reset-btn {
    position: absolute;
    right: 20px;
    top: 20px;
    padding: 8px 15px;
    background: #dc3545;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
    transition: all 0.3s;
}

.reset-btn:hover {
    background: #c82333;
    transform: translateY(-2px);
}

.reset-btn i {
    font-size: 1.1em;
}

.card {
    position: relative;
}

/* Modal Form CSS */
#groupRegistrationModal .modal-content {
    border-radius: 12px;
    padding: 20px;
    background: #f8f9fa;
}
#groupRegistrationModal .modal-header {
    background: rgb(3,31,80);
    color: white;
    border-top-left-radius: 12px;
    border-top-right-radius: 12px;
}
#groupRegistrationModal .btn-primary {
    background: rgb(3,31,80);
    border: none;
}
#groupRegistrationModal .btn-primary:hover {
    background: rgb(3,31,80);
}
#registrationMessage {
    font-weight: bold;
    margin-bottom: 10px;
}

/* Loading spinner */
.loading-spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid rgba(0,0,0,.1);
    border-radius: 50%;
    border-top-color: #1976d2;
    animation: spin 1s ease-in-out infinite;
    margin-right: 10px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.loading-text {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
</style>
</head>
<body>

<?php
// session_start();
include ('connection.php');

$registrationMessage = '';
$registrationSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['group_register'])) {
    $regnumber = trim($_POST['regnumber']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $group_id = (int)$_POST['group_id'];

    if ($password !== $confirm_password) {
        $registrationMessage = 'Passwords do not match!';
    } else {
        // Start transaction
        $connection->begin_transaction();
        
        try {
            // First, delete any existing registration for this student
            $deleteStmt = $connection->prepare('DELETE FROM student WHERE regnumber = ?');
            $deleteStmt->bind_param('s', $regnumber);
            $deleteStmt->execute();
            
            // Then insert the new registration
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insertStmt = $connection->prepare('INSERT INTO student (regnumber, email, password, group_id) VALUES (?, ?, ?, ?)');
            $insertStmt->bind_param('sssi', $regnumber, $email, $hashed_password, $group_id);
            
            if ($insertStmt->execute()) {
                $connection->commit();
                $registrationMessage = 'Registration updated successfully!';
                $registrationSuccess = true;
                
                // Store registration in session
                $_SESSION['student_registered'] = true;
                $_SESSION['student_group'] = $group_id;
            } else {
                throw new Exception('Failed to register. Please try again.');
            }
        } catch (Exception $e) {
            $connection->rollback();
            $registrationMessage = $e->getMessage();
        }
    }
}
?>

<main class="main">
<div class="container mt-4">
<section class="section">
<div class="row">
<div class="col-lg-12">
<div class="card">
    <!-- staff login -->
    <a href="login.php"> <button type="button" class="btn btn-primary mb-4 m-2">Staff Login</button></a>
<div class="card-body" style="background-color: rgb(227, 246, 255);">
<h5 class="card-title">Campus Tree Structure</h5>
<p>Hello student! Please open campus, college, up to group, then click on group to register in your group!<br> 
If you are already registered in a group, you can login and view your timetable! 
<a href="login-student.php">Click here to login</a>
</p>
<button type="button" class="reset-btn mb-2" id="resetTreeView">
<i class="bi bi-arrow-counterclockwise"></i> Reset View
</button>   
<div id="treeView" class="tree-view">
    <div class="loading-text">
        <div class="loading-spinner"></div>
        <span>Loading organization structure...</span>
    </div>
</div>
</div>
</div>
</div>
</div>
</section>
</div>
</main>

<!-- Registration Modal -->
<div class="modal fade" id="groupRegistrationModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog">
<div class="modal-content">
<form id="groupRegistrationForm" method="POST">
<input type="hidden" name="group_id" id="groupId">
<input type="hidden" name="group_register" value="1">
<div class="modal-header">
<h5 class="modal-title">Register for Group</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">

<?php if ($registrationMessage): ?>
<div id="registrationMessage" class="<?= $registrationSuccess ? 'text-success' : 'text-danger' ?>">
<?= $registrationMessage ?>
</div>
<?php endif; ?>

<div class="mb-3">
<label for="regNumber" class="form-label">Registration Number</label>
<input type="text" class="form-control" id="regNumber" name="regnumber" required>
</div>

<div class="mb-3">
<label for="email" class="form-label">Email</label>
<input type="email" class="form-control" id="email" name="email" required>
</div>

<div class="mb-3">
<label for="password" class="form-label">Password</label>
<input type="password" class="form-control" id="password" name="password" required>
</div>

<div class="mb-3">
<label for="confirm_password" class="form-label">Confirm Password</label>
<input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
</div>

<div class="form-check mb-3">
<input type="checkbox" class="form-check-input" id="confirmCheck" required>
<label class="form-check-label" for="confirmCheck">I confirm registration</label>
</div>

<div class="modal-footer">
<button type="submit" class="btn btn-primary">Register</button>
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
</div>

</div>
</form>
</div>
</div>
</div>

<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
$(document).ready(function() {
    let organizationData = null;

    function loadOrganizationStructure() {
        // Fetch data from the API
        $.ajax({
            url: 'Dashboard/get_organization_structure.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    organizationData = response.data;
                    renderTreeView();
                } else {
                    $('#treeView').html('<div class="alert alert-danger">Failed to load organization structure: ' + (response.error || 'Unknown error') + '</div>');
                }
            },
            error: function(xhr, status, error) {
                $('#treeView').html('<div class="alert alert-danger">Failed to load organization structure: ' + error + '</div>');
            }
        });
    }

    function renderTreeView() {
        const treeView = $('#treeView').empty();
        
        if (!organizationData || !organizationData.colleges || organizationData.colleges.length === 0) {
            treeView.html('<div class="alert alert-info">No organization data available</div>');
            return;
        }
        
        // Start with colleges instead of campuses
        organizationData.colleges.forEach(college => {
            treeView.append(createTreeItem('college', college));
        });
    }

    function createTreeItem(type, item) {
        // Determine if this item has children
        let hasChildren = false;
        let childCount = 0;
        
        // Count all possible children based on type
        switch (type) {
            case 'college':
                hasChildren = item.schools && item.schools.length > 0;
                childCount = hasChildren ? item.schools.length : 0;
                break;
                
            case 'school':
                // Count departments + direct programs
                const deptCount = item.departments ? item.departments.length : 0;
                const directPrograms = item.all_programs ? item.all_programs.filter(p => !p.department_id).length : 0;
                hasChildren = (deptCount + directPrograms) > 0;
                childCount = deptCount + directPrograms;
                break;
                
            case 'department':
                hasChildren = item.programs && item.programs.length > 0;
                childCount = hasChildren ? item.programs.length : 0;
                break;
                
            case 'program':
                hasChildren = item.intakes && item.intakes.length > 0;
                childCount = hasChildren ? item.intakes.length : 0;
                break;
                
            case 'intake':
                hasChildren = item.groups && item.groups.length > 0;
                childCount = hasChildren ? item.groups.length : 0;
                break;
                
            case 'group':
                // Add any group-specific children here if needed
                break;
        }

        // Format display name - handle undefined intake names
        let displayName = item.name || '';
        if (type === 'intake') {
            if (item.year && item.month) {
                const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
                                  'July', 'August', 'September', 'October', 'November', 'December'];
                // If name is empty or undefined, just show the date
                displayName = displayName.trim() ? 
                    `${displayName} (${monthNames[item.month - 1]} ${item.year})` : 
                    `${monthNames[item.month - 1]} ${item.year}`;
                
                // Add year of study if available
                if (item.year_of_study) {
                    displayName += ` - Year ${item.year_of_study}`;
                }
            }
            // Add campus info if available
            if (item.campus && item.campus.name) {
                displayName += ` - ${item.campus.name}`;
            }
        }

        // Create the list item
        const $item = $(`
            <li class="level-${type}">
                <span class="toggle ${hasChildren ? 'bi bi-chevron-right' : ''}"></span>
                <span class="content">
                    ${displayName} <span class="badge">${type.charAt(0).toUpperCase() + type.slice(1)}</span>
                    ${childCount > 0 ? `<span class="count-badge">${childCount}</span>` : ''}
                </span>
                ${hasChildren ? '<ul class="collapsed"></ul>' : ''}
            </li>
        `);

        // Add children if they exist
        if (hasChildren) {
            const $children = $item.find('ul');
            
            // Handle different types of items
            switch (type) {
                case 'college':
                    if (item.schools) {
                        item.schools.forEach(s => $children.append(createTreeItem('school', s)));
                    }
                    break;
                    
                case 'school':
                    // First show departments and their programs
                    if (item.departments) {
                        item.departments.forEach(d => {
                            $children.append(createTreeItem('department', d));
                        });
                    }
                    
                    // Then show direct programs (without departments)
                    if (item.all_programs) {
                        const directPrograms = item.all_programs.filter(p => !p.department_id);
                        if (directPrograms.length > 0) {
                            directPrograms.forEach(p => {
                                $children.append(createTreeItem('program', p));
                            });
                        }
                    }
                    break;
                    
                case 'department':
                    if (item.programs) {
                        item.programs.forEach(p => $children.append(createTreeItem('program', p)));
                    }
                    break;
                    
                case 'program':
                    if (item.intakes) {
                        item.intakes.forEach(i => $children.append(createTreeItem('intake', i)));
                    }
                    break;
                    
                case 'intake':
                    if (item.groups) {
                        item.groups.forEach(g => $children.append(createTreeItem('group', g)));
                    }
                    break;
                    
                case 'group':
                    // Add any group-specific children here if needed
                    break;
            }
        }

        // Add click handler for groups
        if (type === 'group') {
            $item.find('.content').css('cursor', 'pointer').on('click', function() {
                $('#groupId').val(item.id);
                $('#groupRegistrationModal').modal('show');
            });
        }

        return $item;
    }

    $(document).on('click','.tree-view .toggle', function(){
        const $this = $(this);
        $this.toggleClass('bi-chevron-right bi-chevron-down');
        $this.siblings('ul').toggleClass('collapsed');
    });

    $('#resetTreeView').on('click', function(){
        $('.tree-view .toggle.bi-chevron-down').click();
        $('html, body').animate({scrollTop:0},500);
    });

    // Load the organization structure
    loadOrganizationStructure();
});
</script>

</body>
</html>