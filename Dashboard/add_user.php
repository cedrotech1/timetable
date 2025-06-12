<?php
session_start();  
include('connection.php');
// include('./includes/auth.php');
// checkUserRole(['information_modifier']);

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

// Get current user's role and campus
$current_user_id = $_SESSION['id'];
$stmt = $connection->prepare("SELECT role, campus FROM users WHERE id = ?");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();
$current_user = $result->fetch_assoc();

// Function to check if current user can create the specified role
function canCreateUser($currentUserRole, $newUserRole) {
    if ($currentUserRole === 'admin') {
        return in_array($newUserRole, ['admin', 'campus_admin']);
    } else if ($currentUserRole === 'campus_admin') {
        return in_array($newUserRole, ['timetable_officer', 'lecturer']);
    }
    return false;
}

require_once '../../loadEnv.php';

// Load the .env file
$filePath = __DIR__ . '/../../.env'; // Corrected path
loadEnv($filePath);
include("../email_functions.php");

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title>Add User</title>
  <meta content="" name="description">
  <meta content="" name="keywords">

  <!-- Favicons -->
  <link href="assets/img/icon1.png" rel="icon">
  <link href="assets/img/icon1.png" rel="apple-touch-icon">

  <!-- Google Fonts -->
  <link href="https://fonts.gstatic.com" rel="preconnect">
  <link
    href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i"
    rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
  <link href="assets/vendor/quill/quill.snow.css" rel="stylesheet">
  <link href="assets/vendor/quill/quill.bubble.css" rel="stylesheet">
  <link href="assets/vendor/remixicon/remixicon.css" rel="stylesheet">
  <link href="assets/vendor/simple-datatables/style.css" rel="stylesheet">

  <!-- Template Main CSS File -->
  <link href="assets/css/style.css" rel="stylesheet">

  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">

  <script>
    // Function to load campuses based on role
    function loadCampuses() {
        const roleSelect = document.getElementById('floatingRole');
        const campusSelect = document.getElementById('floatingCampus');
        
        // Clear existing options
        campusSelect.innerHTML = '<option value="" disabled selected>Select Campus</option>';
        
        if (roleSelect.value === 'admin') {
            return; // Don't load campuses for admin role
        }
        
        // Show loading state
        campusSelect.disabled = true;
        
        // Fetch campuses from server
        fetch('get_campuses.php')
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                console.log('Received data:', data);
                if (data.success && Array.isArray(data.campuses)) {
                    if (data.campuses.length === 0) {
                        const noOption = document.createElement('option');
                        noOption.textContent = 'No campuses available';
                        noOption.disabled = true;
                        campusSelect.appendChild(noOption);
                    } else {
                        data.campuses.forEach(campus => {
                            const option = document.createElement('option');
                            option.value = campus.id;
                            option.textContent = campus.name;
                            campusSelect.appendChild(option);
                        });
                    }
                } else {
                    throw new Error(data.message || 'Error loading campuses');
                }
            })
            .catch(error => {
                console.error('Error loading campuses:', error);
                const errorOption = document.createElement('option');
                errorOption.textContent = 'Error loading campuses';
                errorOption.disabled = true;
                campusSelect.appendChild(errorOption);
            })
            .finally(() => {
                campusSelect.disabled = false;
            });
    }

    // Function to toggle campus field visibility
    function toggleCampusField() {
        const roleSelect = document.getElementById('floatingRole');
        const campusField = document.getElementById('campusField');
        const campusSelect = document.getElementById('floatingCampus');
        
        if (roleSelect.value === 'admin') {
            campusField.style.display = 'none';
            campusSelect.required = false;
        } else {
            // Only show campus field if current user is admin
            if ('<?php echo $current_user['role']; ?>' === 'admin') {
                campusField.style.display = 'block';
                campusSelect.required = true;
                loadCampuses();
            } else {
                campusField.style.display = 'none';
                campusSelect.required = false;
            }
        }
    }

    // Set default password to 1234
    function setDefaultPassword() {
        const passwordField = document.getElementById('password');
        passwordField.value = '1234';
    }

    window.onload = function() {
        setDefaultPassword();
        toggleCampusField();
        initializeFilters();
    };

    // Initialize filters
    function initializeFilters() {
        const searchInput = document.getElementById('searchInput');
        const roleFilter = document.getElementById('roleFilter');
        const statusFilter = document.getElementById('statusFilter');
        const campusFilter = document.getElementById('campusFilter');

        if (searchInput) searchInput.addEventListener('keyup', filterTable);
        if (roleFilter) roleFilter.addEventListener('change', filterTable);
        if (statusFilter) statusFilter.addEventListener('change', filterTable);
        if (campusFilter) campusFilter.addEventListener('change', filterTable);
    }

    // Filter table function
    function filterTable() {
        const searchInput = document.getElementById('searchInput');
        const roleFilter = document.getElementById('roleFilter');
        const statusFilter = document.getElementById('statusFilter');
        const campusFilter = document.getElementById('campusFilter');
        const tableBody = document.getElementById('usersTableBody');

        if (!tableBody) return;

        const searchText = searchInput ? searchInput.value.toLowerCase() : '';
        const roleValue = roleFilter ? roleFilter.value : '';
        const statusValue = statusFilter ? statusFilter.value : '';
        const campusValue = campusFilter ? campusFilter.value : '';

        const rows = tableBody.getElementsByTagName('tr');

        for (let row of rows) {
            const name = row.cells[1].textContent.toLowerCase();
            const email = row.cells[2].textContent.toLowerCase();
            const role = row.cells[4].textContent;
            const status = row.cells[6].textContent;
            const campusCell = row.cells[5];
            const campusId = campusCell.getAttribute('data-campus-id');

            const matchesSearch = name.includes(searchText) || email.includes(searchText);
            const matchesRole = !roleValue || role === roleValue;
            const matchesStatus = !statusValue || (statusValue === '1' && status === 'Active') || (statusValue === '0' && status === 'Inactive');
            const matchesCampus = !campusValue || campusId === campusValue;

            row.style.display = matchesSearch && matchesRole && matchesStatus && matchesCampus ? '' : 'none';
        }
    }

    // Excel Export Function
    function exportToExcel() {
        const table = document.getElementById('usersTableBody');
        if (!table) return;

        const rows = table.getElementsByTagName('tr');
        const wb = XLSX.utils.book_new();
        const ws_data = [];
        
        // Add headers
        ws_data.push([
            'Name',
            'Email',
            'Phone',
            'Role',
            'Campus',
            'Status'
        ]);
        
        // Add data rows
        for (let row of rows) {
            if (row.style.display !== 'none') {
                const cells = row.getElementsByTagName('td');
                ws_data.push([
                    cells[1].textContent,
                    cells[2].textContent,
                    cells[3].textContent,
                    cells[4].textContent,
                    cells[5].textContent,
                    cells[6].textContent
                ]);
            }
        }
        
        const ws = XLSX.utils.aoa_to_sheet(ws_data);
        XLSX.utils.book_append_sheet(wb, ws, "Users");
        const wbout = XLSX.write(wb, { bookType: 'xlsx', type: 'binary' });
        
        const blob = new Blob([s2ab(wbout)], { type: 'application/octet-stream' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'users_list.xlsx';
        a.click();
        window.URL.revokeObjectURL(url);
    }

    // Helper function for Excel export
    function s2ab(s) {
        const buf = new ArrayBuffer(s.length);
        const view = new Uint8Array(buf);
        for (let i = 0; i < s.length; i++) {
            view[i] = s.charCodeAt(i) & 0xFF;
        }
        return buf;
    }
  </script>



</head>

<body>

  <?php
  include("./includes/header.php");
  include("./includes/menu.php");
  ?>


  <main id="main" class="main">

    <section class="section dashboard">
      <div class="row">
        <!-- <div class="col-lg-1"></div> -->
        <!-- Left side columns -->
    

      </div>
    </section>

    <?php
    // Query to select users
    $query = "SELECT * FROM users WHERE role != 'admin'";
    $result = mysqli_query($connection, $query);

    // Check if any users were found
    if (mysqli_num_rows($result) > 0) {
      ?>
      <section class="section dashboard">
        <div class="row">
          <!-- <div class="col-lg-1"></div> -->
          <!-- Left side columns -->
          <div class="col-lg-12">
            <div class="row">

              <div class="card">
                <div class="card-body p-2">
                  <center>
                    <h5 class="card-title"> LIST OF ALL USERS</h5>
                  </center>
                </div>
              </div>


            </div>
          </div><!-- End Left side columns -->


        </div>
      </section>

      <?php

    }

    ?>

    <!-- Users Table Section -->
    <section class="section dashboard">
      <div class="row">
        <div class="col-lg-12">
          <div class="card">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title">Users List</h5>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                  <i class="fas fa-plus"></i> Add User
                </button>
              </div>

              <!-- Search and Filter Section -->
              <div class="row mb-3">
                <div class="col-md-3">
                  <input type="text" id="searchInput" class="form-control" placeholder="Search users...">
                </div>
                <div class="col-md-2">
                  <select id="roleFilter" class="form-select">
                    <option value="">All Roles</option>
                    <?php if ($current_user['role'] === 'admin'): ?>
                      <option value="admin">Admin</option>
                      <option value="campus_admin">Campus Admin</option>
                    <?php endif; ?>
                    <option value="timetable_officer">Timetable Officer</option>
                    <option value="lecturer">Lecturer</option>
                  </select>
                </div>
                <div class="col-md-2">
                  <select id="statusFilter" class="form-select">
                    <option value="">All Status</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                  </select>
                </div>
                <?php if ($current_user['role'] === 'admin'): ?>
                <div class="col-md-2">
                  <select id="campusFilter" class="form-select">
                    <option value="">All Campuses</option>
                    <?php
                    $campusQuery = "SELECT * FROM campus ORDER BY name";
                    $campusResult = mysqli_query($connection, $campusQuery);
                    while ($campus = mysqli_fetch_assoc($campusResult)) {
                      echo "<option value='" . $campus['id'] . "'>" . htmlspecialchars($campus['name']) . "</option>";
                    }
                    ?>
                  </select>
                </div>
                <?php endif; ?>
                <div class="col-md-3">
                  <button class="btn btn-success" onclick="exportToExcel()">
                    <i class="fas fa-file-excel"></i> Export to Excel
                  </button>
                </div>
              </div>

              <!-- Users Table -->
              <div class="table-responsive">
                <table class="table table-striped table-hover">
                  <thead>
                    <tr>
                      <th>Image</th>
                      <th>Name</th>
                      <th>Email</th>
                      <th>Phone</th>
                      <th>Role</th>
                      <th>Campus</th>
                      <th>Status</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody id="usersTableBody">
                    <?php
                    // Modify query based on user role
                    if ($current_user['role'] === 'admin') {
                        $query = "SELECT u.*, c.name as campus_name, c.id as campus_id 
                                 FROM users u 
                                 LEFT JOIN campus c ON u.campus = c.id 
                                 WHERE u.role != 'admin'";
                    } else if ($current_user['role'] === 'campus_admin') {
                        $query = "SELECT u.*, c.name as campus_name, c.id as campus_id 
                                 FROM users u 
                                 LEFT JOIN campus c ON u.campus = c.id 
                                 WHERE u.campus = ? AND u.role IN ('timetable_officer', 'lecturer')";
                        $stmt = $connection->prepare($query);
                        $stmt->bind_param("i", $current_user['campus']);
                        $stmt->execute();
                        $result = $stmt->get_result();
                    } else {
                        $result = mysqli_query($connection, "SELECT * FROM users WHERE id = " . $current_user['id']);
                    }

                    if ($current_user['role'] === 'admin') {
                        $result = mysqli_query($connection, $query);
                    }

                    while ($row = mysqli_fetch_assoc($result)) {
                      echo "<tr>";
                      echo "<td><img src='./" . $row['image'] . "' class='rounded-circle' width='40' height='40'></td>";
                      echo "<td>" . $row['names'] . "</td>";
                      echo "<td>" . $row['email'] . "</td>";
                      echo "<td>" . $row['phone'] . "</td>";
                      echo "<td>" . $row['role'] . "</td>";
                      echo "<td data-campus-id='" . ($row['campus_id'] ?? '') . "'>" . ($row['campus_name'] ?? 'N/A') . "</td>";
                      echo "<td>" . ($row['active'] ? 'Active' : 'Inactive') . "</td>";
                      echo "<td>
                              <a href='user-delete.php?userId=" . $row['id'] . "' class='btn btn-sm btn-danger'><i class='fas fa-trash'></i></a>
                              <button class='btn btn-sm " . ($row['active'] ? 'btn-warning' : 'btn-success') . "' 
                                      onclick='" . ($row['active'] ? 'confirmDeactivation' : 'confirmActivation') . "(" . $row['id'] . ", \"" . $row['names'] . "\")'>
                                <i class='fas " . ($row['active'] ? 'fa-toggle-on' : 'fa-toggle-off') . "'></i>
                              </button>
                            </td>";
                      echo "</tr>";
                    }
                    ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <?php if (isset($error) && !empty($error)): ?>
              <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>
            <?php endif; ?>
            
            <?php if (isset($success) && !empty($success)): ?>
              <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>
            <?php endif; ?>

            <form id="addUserForm" class="row g-3" action="add_user.php" method="post">
              <div class="col-md-12">
                <div class="form-floating">
                  <input type="text" class="form-control" id="floatingName" placeholder="Name" name="name" required>
                  <label for="floatingName">Name</label>
                </div>
              </div>

              <div class="col-md-12">
                <div class="form-floating">
                  <input type="email" class="form-control" id="floatingEmail" placeholder="Email" name="email" required>
                  <label for="floatingEmail">Email</label>
                </div>
              </div>

              <div class="col-md-12">
                <div class="form-floating">
                  <input type="tel" class="form-control" id="floatingPhone" placeholder="Phone" name="phone" required>
                  <label for="floatingPhone">Phone</label>
                </div>
              </div>

              <!-- Role Selection Dropdown -->
              <div class="col-md-12">
                <div class="form-floating">
                  <select class="form-select" id="floatingRole" name="role" required onchange="toggleCampusField()">
                    <option value="" disabled selected>Select Role</option>
                    <?php if ($current_user['role'] === 'admin'): ?>
                      <option value="admin">Admin</option>
                      <option value="campus_admin">Campus Admin</option>
                    <?php elseif ($current_user['role'] === 'campus_admin'): ?>
                      <option value="timetable_officer">Timetable Officer</option>
                      <option value="lecturer">Lecturer</option>
                    <?php endif; ?>
                  </select>
                  <label for="floatingRole">Role</label>
                </div>
              </div>

              <!-- Campus Selection Dropdown (initially hidden) -->
              <div class="col-md-12" id="campusField" style="display: none;">
                <div class="form-floating">
                  <select class="form-select" id="floatingCampus" name="campus">
                    <option value="" disabled selected>Select Campus</option>
                  </select>
                  <label for="floatingCampus">Campus</label>
                </div>
              </div>

              <input type="hidden" id="password" name="password">
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" name="saveuser" class="btn btn-primary" form="addUserForm">Save User</button>
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

  </main><!-- End #main -->





  <?php
  include("./includes/footer.php");
  ?>

  <!-- End Footer -->

  <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i
      class="bi bi-arrow-up-short"></i></a>

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

  <script>
    // Add this to your existing JavaScript
    document.addEventListener('DOMContentLoaded', function() {
        // Reset form when modal is closed
        const addUserModal = document.getElementById('addUserModal');
        addUserModal.addEventListener('hidden.bs.modal', function () {
            document.getElementById('addUserForm').reset();
            setDefaultPassword();
            toggleCampusField();
        });

        // Handle form submission
        const addUserForm = document.getElementById('addUserForm');
        addUserForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('saveuser', '1');
            
            // Log form data for debugging
            console.log('Form data being sent:');
            for (let pair of formData.entries()) {
                console.log(pair[0] + ': ' + pair[1]);
            }
            
            fetch('process_user.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    // Show success message
                    const successAlert = document.createElement('div');
                    successAlert.className = 'alert alert-success alert-dismissible fade show';
                    successAlert.innerHTML = `
                        ${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    `;
                    document.querySelector('.modal-body').insertBefore(successAlert, addUserForm);
                    
                    // Close modal after 2 seconds
                    setTimeout(() => {
                        const modal = bootstrap.Modal.getInstance(addUserModal);
                        modal.hide();
                        // Reload the page to show new user
                        window.location.reload();
                    }, 2000);
                } else {
                    // Show error message
                    const errorAlert = document.createElement('div');
                    errorAlert.className = 'alert alert-danger alert-dismissible fade show';
                    errorAlert.innerHTML = `
                        ${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    `;
                    document.querySelector('.modal-body').insertBefore(errorAlert, addUserForm);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                const errorAlert = document.createElement('div');
                errorAlert.className = 'alert alert-danger alert-dismissible fade show';
                errorAlert.innerHTML = `
                    An error occurred while processing your request.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;
                document.querySelector('.modal-body').insertBefore(errorAlert, addUserForm);
            });
        });
    });
  </script>

</body>

</html>

<?php
if (isset($_POST['saveuser'])) {
    header('Content-Type: application/json');
    
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $role = $_POST['role'];
    $campus = ($current_user['role'] === 'campus_admin') ? $current_user['campus'] : (($role !== 'admin') ? $_POST['campus'] : null);
    $password = password_hash('1234', PASSWORD_DEFAULT);
    $default_image = 'assets/img/av.png';

    if ($name != '' && $email != '' && $password != '') {
        // Check if email already exists
        $stmt = $connection->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            exit;
        }

        // Validate role-based permissions
        if (!canCreateUser($current_user['role'], $role)) {
            echo json_encode(['success' => false, 'message' => "You don't have permission to create users with this role"]);
            exit;
        }

        // Validate campus requirement for non-admin roles
        if ($role !== 'admin' && empty($campus)) {
            echo json_encode(['success' => false, 'message' => "Campus is required for " . ucfirst($role) . " role"]);
            exit;
        }

        // If no errors, create the user
        $stmt = $connection->prepare("INSERT INTO users (names, email, phone, role, password, campus, active, image) VALUES (?, ?, ?, ?, ?, ?, 1, ?)");
        $stmt->bind_param("sssssss", $name, $email, $phone, $role, $password, $campus, $default_image);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => "User '$name' has been added successfully!"]);
        } else {
            echo json_encode(['success' => false, 'message' => "Error creating user: " . $connection->error]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => "Please fill all required fields."]);
    }
    exit;
}

// Get success message from session if exists
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
?>