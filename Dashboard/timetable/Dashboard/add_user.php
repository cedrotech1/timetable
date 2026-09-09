<?php
require_once '../connection.php';
session_start();


// Handle AJAX requests for colleges, schools, and user updates
if (isset($_POST['action'])) {
    if ($_POST['action'] == 'get_colleges' && isset($_POST['campus_id'])) {
        $campus_id = intval($_POST['campus_id']);
        $query = "SELECT * FROM college WHERE campus_id = ? ORDER BY name";
        $stmt = $connection->prepare($query);
        $stmt->bind_param('i', $campus_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $options = '';
        while ($college = $result->fetch_assoc()) {
            $options .= '<option value="' . $college['id'] . '">' . htmlspecialchars($college['name']) . '</option>';
        }
        echo $options;
        $stmt->close(); 
        exit;
    } elseif ($_POST['action'] == 'get_schools' && isset($_POST['college_id'])) {
        $college_id = intval($_POST['college_id']);
        $query = "SELECT * FROM school WHERE college_id = ? ORDER BY name";
        $stmt = $connection->prepare($query);
        $stmt->bind_param('i', $college_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $options = '';
        while ($school = $result->fetch_assoc()) {
            $options .= '<option value="' . $school['id'] . '">' . htmlspecialchars($school['name']) . '</option>';
        }
        echo $options;
        $stmt->close();
        exit;
    } elseif ($_POST['action'] == 'update_user') {
        $id = intval($_POST['id']);
        $role = $_POST['role'];
        $campus = $_POST['campus'] ? intval($_POST['campus']) : null;
        $college = $_POST['college'] ? intval($_POST['college']) : null;
        $school = $_POST['school'] ? intval($_POST['school']) : null;

        $query = "UPDATE users SET role = ?, campus = ?, college = ?, school = ? WHERE id = ?";
        $stmt = $connection->prepare($query);
        $stmt->bind_param('siiii', $role, $campus, $college, $school, $id);
        $stmt->execute();
        $stmt->close();
        echo 'success';
        exit;
    }
}

function getRoleDisplayName($role) {
    switch ($role) {
        case 'registrar_office': return 'Registrar Office';
        case 'dean_office': return 'Dean Office';
        case 'dtle': return 'DTLE';
        default: return $role;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/img/icon1.png" rel="icon">
    <link href="assets/img/icon1.png" rel="apple-touch-icon">
    
    <!-- Bootstrap & Styles -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <?php include './includes/header.php'; ?>
  <?php include './includes/menu.php'; ?>
 
  <main id="main" class="main">

    <div class="container mt-4">
        <h2>Users List</h2>
        
        <!-- Search Form -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <input type="text" name="search" class="form-control" placeholder="Search by name, email, or staff number" 
                               value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="phone" class="form-control" placeholder="Phone number"
                               value="<?php echo isset($_GET['phone']) ? htmlspecialchars($_GET['phone']) : ''; ?>">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <?php if(isset($_GET['search']) || isset($_GET['phone'])): ?>
                            <a href="add_user.php" class="btn btn-secondary">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Staff Number</th>
                    <th>Names</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Campus</th>
                    <th>College</th>
                    <th>School</th>
                    <th>Active</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Build the base query
                $query = "
                    SELECT u.*, c.name as campus_name, col.name as college_name, s.name as school_name
                    FROM users u
                    LEFT JOIN campus c ON u.campus = c.id
                    LEFT JOIN college col ON u.college = col.id
                    LEFT JOIN school s ON u.school = s.id
                    WHERE 1=1
                ";
                
                $params = [];
                $types = '';
                
                // Add search conditions
                if (!empty($_GET['search'])) {
                    $searchTerm = "%" . $_GET['search'] . "%";
                    $query .= " AND (u.names LIKE ? OR u.email LIKE ? OR u.ur_email LIKE ? OR u.staff_number LIKE ?)";
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                    $types .= 'ssss';
                }
                
                if (!empty($_GET['phone'])) {
                    $phoneTerm = "%" . $_GET['phone'] . "%";
                    $query .= " AND u.phone LIKE ?";
                    $params[] = $phoneTerm;
                    $types .= 's';
                }
                
                $query .= " ORDER BY u.id";
                
                // Prepare and execute the query
                $stmt = $connection->prepare($query);
                if (!empty($params)) {
                    $stmt->bind_param($types, ...$params);
                }
                $stmt->execute();
                $result = $stmt->get_result();
                while ($user = $result->fetch_assoc()) {
                    ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['staff_number']); ?></td>
                        <td><?php echo htmlspecialchars($user['names']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo getRoleDisplayName($user['role']); ?></td>
                        <td><?php echo htmlspecialchars($user['campus_name'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($user['college_name'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($user['school_name'] ?? '-'); ?></td>
                        <td><?php echo $user['active'] ? 'Yes' : 'No'; ?></td>
                        <td>
                            <button class="btn btn-primary btn-sm edit-user-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editUserModal"
                                    data-id="<?php echo $user['id']; ?>"
                                    data-role="<?php echo $user['role']; ?>"
                                    data-campus="<?php echo $user['campus']; ?>"
                                    data-college="<?php echo $user['college']; ?>"
                                    data-school="<?php echo $user['school']; ?>"
                                    >
                                Edit
                            </button>
                        </td>
                    </tr>
                    <?php
                }
                ?>
            </tbody>
        </table>

        <!-- Edit User Modal -->
        <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="editUserId">
                        
                        <div class="mb-3">
                            <label for="editRole" class="form-label">Role</label>
                            <select class="form-select" id="editRole" required>
                                <option value="">Select Role</option>
                                <option value="registrar_office">Registrar Office</option>
                                <option value="dean_office">Dean Office</option>
                                <option value="dtle">DTLE</option>
                            </select>
                        </div>
                        
                        <div class="mb-3" id="campusField">
                            <label for="editCampus" class="form-label">Campus</label>
                            <select class="form-select" id="editCampus">
                                <option value="">Select Campus</option>
                                <?php
                                $campuses = $connection->query("SELECT * FROM campus ORDER BY name");
                                while ($campus = $campuses->fetch_assoc()) {
                                    echo '<option value="' . $campus['id'] . '">' . htmlspecialchars($campus['name']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="mb-3 d-none" id="collegeField">
                            <label for="editCollege" class="form-label">College</label>
                            <select class="form-select" id="editCollege">
                                <option value="">Select College</option>
                            </select>
                        </div>
                        
                        <div class="mb-3 d-none" id="schoolField">
                            <label for="editSchool" class="form-label">School</label>
                            <select class="form-select" id="editSchool">
                                <option value="">Select School</option>
                            </select>
                        </div>
                        
                       
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="saveUserBtn">
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            Save Changes
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Handle edit button click
            $('.edit-user-btn').click(function() {
                const id = $(this).data('id');
                const role = $(this).data('role');
                const campus = $(this).data('campus') || '';
                const college = $(this).data('college') || '';
                const school = $(this).data('school') || '';
               

                $('#editUserId').val(id);
                $('#editRole').val(role);
                $('#editCampus').val(campus);
                $('#editCollege').val(college);
                $('#editSchool').val(school);

                // Show/hide fields based on role
                toggleFields(role, campus, college, school);
            });

            // Handle role change
            $('#editRole').change(function() {
                const role = $(this).val();
                const campus = $('#editCampus').val();
                const college = $('#editCollege').val();
                toggleFields(role, campus, college);
            });

            // Handle campus change
            $('#editCampus').change(function() {
                const role = $('#editRole').val();
                const campusId = $(this).val();
                toggleFields(role, campusId);
            });

            // Handle college change
            $('#editCollege').change(function() {
                const collegeId = $(this).val();
                loadSchools(collegeId);
            });

            // Function to toggle fields based on role
            function toggleFields(role, campusId = '', collegeId = '', schoolId = '') {
                $('#collegeField').addClass('d-none');
                $('#schoolField').addClass('d-none');
                $('#editCollege').html('<option value="">Select College</option>');
                $('#editSchool').html('<option value="">Select School</option>');

                if (role === 'dean_office') {
                    $('#campusField').removeClass('d-none');
                    if (campusId) {
                        loadColleges(campusId, collegeId).then(() => {
                            if (collegeId) {
                                loadSchools(collegeId, schoolId);
                            }
                        });
                    }
                } else {
                    $('#campusField').removeClass('d-none');
                }
            }

            // Function to load colleges based on campus
            function loadColleges(campusId, selectedCollege = '') {
                if (!campusId) {
                    $('#editCollege').html('<option value="">Select College</option>');
                    $('#editSchool').html('<option value="">Select School</option>');
                    return Promise.resolve();
                }

                return $.ajax({
                    url: 'add_user.php',
                    method: 'POST',
                    data: { action: 'get_colleges', campus_id: campusId },
                    success: function(data) {
                        const collegeSelect = $('#editCollege');
                        collegeSelect.html('<option value="">Select College</option>' + data);
                        if (selectedCollege) {
                            collegeSelect.val(selectedCollege);
                            loadSchools(selectedCollege);
                        }
                        $('#collegeField').removeClass('d-none');
                    },
                    error: function() {
                        $('#editCollege').html('<option value="">Error loading colleges</option>');
                        $('#editSchool').html('<option value="">Select School</option>');
                    }
                });
            }

            // Function to load schools based on college
            function loadSchools(collegeId, selectedSchool = '') {
                if (!collegeId) {
                    $('#editSchool').html('<option value="">Select School</option>');
                    return Promise.resolve();
                }

                return $.ajax({
                    url: 'add_user.php',
                    method: 'POST',
                    data: { action: 'get_schools', college_id: collegeId },
                    success: function(data) {
                        const schoolSelect = $('#editSchool');
                        schoolSelect.html('<option value="">Select School</option>' + data);
                        if (selectedSchool) {
                            schoolSelect.val(selectedSchool);
                        }
                        $('#schoolField').removeClass('d-none');
                    },
                    error: function() {
                        $('#editSchool').html('<option value="">Error loading schools</option>');
                    }
                });
            }

            // Handle save changes
            $('#saveUserBtn').click(function() {
                const $btn = $(this);
                const spinner = $btn.find('.spinner-border');
                spinner.removeClass('d-none');
                $btn.prop('disabled', true);

                const userId = $('#editUserId').val();
                const role = $('#editRole').val();
                const campus = $('#editCampus').val();
                const college = role === 'dean_office' ? $('#editCollege').val() : '';
                const school = role === 'dean_office' ? $('#editSchool').val() : '';
           
                
                // Validate required fields based on role
                if ((role === 'dean_office' && (!campus || !college || !school)) || 
                    (role === 'dtle' && !campus)) {
                    alert('Please fill in all required fields');
                    spinner.addClass('d-none');
                    $btn.prop('disabled', false);
                    return;
                }

                // Send the update request
                $.ajax({
                    url: 'add_user.php',
                    method: 'POST',
                    data: {
                        action: 'update_user',
                        id: userId,
                        role: role,
                        campus: campus,
                        college: college,
                        school: school,
                      
                    },
                    success: function(response) {
                        if (response === 'success') {
                            location.reload();
                        } else {
                            alert('Error updating user: ' + response);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Error updating user: ' + error);
                        console.error('Error:', error);
                    },
                    complete: function() {
                        spinner.addClass('d-none');
                        $btn.prop('disabled', false);
                    }
                });
            });
        });
    </script>
    <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

<!-- Vendor JS Files -->
<script src="assets/vendor/apexcharts/apexcharts.min.js"></script>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/vendor/chart.js/chart.umd.js"></script>
<script src="assets/vendor/echarts/echarts.min.js"></script>
<script src="assets/vendor/quill/quill.min.js"></script>
<script src="assets/vendor/simple-datatables/simple-datatables.js"></script>
<script src="assets/vendor/tinymce/tinymce.min.js"></script>
<script src="assets/vendor/php-email-form/validate.js"></script>

<!-- Template Main JS File -->
<script src="assets/js/main.js"></script>
</body>
</html>

<?php
$connection->close();
?>