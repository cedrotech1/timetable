<?php
include('connection.php');
include('./includes/auth.php');
// checkUserRole(['information_modifier']);

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
    // Function to generate a random password
    function generateRandomPassword(length = 12) {
      const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+";
      let password = "";
      for (let i = 0; i < length; i++) {
        password += charset.charAt(Math.floor(Math.random() * charset.length));
      }
      return password;
    }

    // Automatically set a random password in the hidden password input
    function setRandomPassword() {
      const passwordField = document.getElementById('password');
      const generatedPassword = generateRandomPassword();
      passwordField.value = generatedPassword;
    }

    // Call the function to set the password when the page loads
    window.onload = setRandomPassword;
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
        <div class="col-lg-5">
          <div class="row">

            <div class="card">
              <div class="card-body">
                <h5 class="card-title">ADD USER FORM</h5>


                <form class="row g-3" action="add_user.php" method="post">
                  <div class="col-md-12">
                    <div class="form-floating">
                      <input type="text" class="form-control" id="floatingName" placeholder="Name" name="name" required>
                      <label for="floatingName">Name</label>
                    </div>
                  </div>

                  <div class="col-md-12">
                    <div class="form-floating">
                      <input type="email" class="form-control" id="floatingEmail" placeholder="Email" name="email"
                        required>
                      <label for="floatingEmail">Email</label>
                    </div>
                  </div>

                  <div class="col-md-12">
                    <div class="form-floating">
                      <input type="tel" class="form-control" id="floatingPhone" placeholder="Phone" name="phone"
                        required>
                      <label for="floatingPhone">Phone</label>
                    </div>
                  </div>

                  <!-- Role Selection Dropdown -->
                  <div class="col-md-12">
                    <div class="form-floating">
                      <select class="form-select" id="floatingRole" name="role" required onchange="toggleCampusField()">
                        <option value="" disabled selected>Select Role</option>
                       
                        <option value="information_modifier">Information Modifier</option>
                        <option value="warefare">warefare</option>
                        <option value="admin">Admin</option>
                      </select>
                      <label for="floatingRole">Role</label>
                    </div>
                  </div>

                  <!-- Campus Selection Dropdown (initially hidden) -->
                  <div class="col-md-12" id="campusField" style="display: none;">
                    <div class="form-floating">
                      <select class="form-select" id="floatingCampus" name="campus">
                        <option value="" disabled selected>Select Campus</option>
                        <?php
                        $campusQuery = "SELECT * FROM campuses";
                        $campusResult = mysqli_query($connection, $campusQuery);
                        while ($campus = mysqli_fetch_assoc($campusResult)) {
                          echo "<option value='" . $campus['id'] . "'>" . $campus['name'] . "</option>";
                        }
                        ?>
                      </select>
                      <label for="floatingCampus">Campus</label>
                    </div>
                  </div>

                  <input type="hidden" id="password" name="password">

                  <div class="text-center">
                    <button type="submit" name="saveuser" class="btn btn-primary">Save User</button>
                    <button type="reset" class="btn btn-secondary">Reset</button>
                  </div>
                </form>




              </div>
            </div>


          </div>
        </div><!-- End Left side columns -->


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
              <h5 class="card-title">Users List</h5>
              
              <!-- Search and Filter Section -->
              <div class="row mb-3">
                <div class="col-md-3">
                  <input type="text" id="searchInput" class="form-control" placeholder="Search users...">
                </div>
                <div class="col-md-2">
                  <select id="roleFilter" class="form-select">
                    <option value="">All Roles</option>
                    <option value="information_modifier">Information Modifier</option>
                    <option value="warefare">Warefare</option>
                  </select>
                </div>
                <div class="col-md-2">
                  <select id="statusFilter" class="form-select">
                    <option value="">All Status</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                  </select>
                </div>
                <div class="col-md-2">
                  <select id="campusFilter" class="form-select">
                    <option value="">All Campuses</option>
                    <?php
                    $campusQuery = "SELECT * FROM campuses ORDER BY name";
                    $campusResult = mysqli_query($connection, $campusQuery);
                    while ($campus = mysqli_fetch_assoc($campusResult)) {
                      echo "<option value='" . $campus['id'] . "'>" . $campus['name'] . "</option>";
                    }
                    ?>
                  </select>
                </div>
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
                    $query = "SELECT u.*, c.name as campus_name, c.id as campus_id 
                             FROM users u 
                             LEFT JOIN campuses c ON u.campus = c.id 
                             WHERE u.role != 'admin'";
                    $result = mysqli_query($connection, $query);

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

    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
      // Existing filter functionality
      document.getElementById('searchInput').addEventListener('keyup', filterTable);
      document.getElementById('roleFilter').addEventListener('change', filterTable);
      document.getElementById('statusFilter').addEventListener('change', filterTable);
      document.getElementById('campusFilter').addEventListener('change', filterTable);

      function filterTable() {
        const searchText = document.getElementById('searchInput').value.toLowerCase();
        const roleFilter = document.getElementById('roleFilter').value;
        const statusFilter = document.getElementById('statusFilter').value;
        const campusFilter = document.getElementById('campusFilter').value;
        const rows = document.getElementById('usersTableBody').getElementsByTagName('tr');

        for (let row of rows) {
          const name = row.cells[1].textContent.toLowerCase();
          const email = row.cells[2].textContent.toLowerCase();
          const role = row.cells[4].textContent;
          const status = row.cells[6].textContent;
          const campusCell = row.cells[5];
          const campusId = campusCell.getAttribute('data-campus-id');

          const matchesSearch = name.includes(searchText) || email.includes(searchText);
          const matchesRole = !roleFilter || role === roleFilter;
          const matchesStatus = !statusFilter || (statusFilter === '1' && status === 'Active') || (statusFilter === '0' && status === 'Inactive');
          const matchesCampus = !campusFilter || campusId === campusFilter;

          row.style.display = matchesSearch && matchesRole && matchesStatus && matchesCampus ? '' : 'none';
        }
      }

      // Excel Export Function
      function exportToExcel() {
        // Get the table
        const table = document.getElementById('usersTableBody');
        const rows = table.getElementsByTagName('tr');
        
        // Create workbook and worksheet
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
          if (row.style.display !== 'none') { // Only export visible rows
            const cells = row.getElementsByTagName('td');
            ws_data.push([
              cells[1].textContent, // Name
              cells[2].textContent, // Email
              cells[3].textContent, // Phone
              cells[4].textContent, // Role
              cells[5].textContent, // Campus
              cells[6].textContent  // Status
            ]);
          }
        }
        
        // Create worksheet
        const ws = XLSX.utils.aoa_to_sheet(ws_data);
        
        // Add worksheet to workbook
        XLSX.utils.book_append_sheet(wb, ws, "Users");
        
        // Generate Excel file
        const wbout = XLSX.write(wb, { bookType: 'xlsx', type: 'binary' });
        
        // Convert to blob and download
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
    // Function to toggle campus field visibility
    function toggleCampusField() {
      const roleSelect = document.getElementById('floatingRole');
      const campusField = document.getElementById('campusField');
      const campusSelect = document.getElementById('floatingCampus');
      
      if (roleSelect.value === 'warefare') {
        campusField.style.display = 'block';
        campusSelect.required = true;
      } else {
        campusField.style.display = 'none';
        campusSelect.required = false;
      }
    }
  </script>

</body>

</html>

<?php
if (isset($_POST['saveuser'])) {
  $name = $_POST['name'];
  $email = $_POST['email'];
  $phone = $_POST['phone'];
  $role = $_POST['role'];
  $password = 1234;
  $campus = ($role === 'warefare') ? $_POST['campus'] : null;

  if ($name != '' && $email != '' && $password != '') {
    // Hash the password for security using bcrypt algorithm
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    // Insert the user into the database with hashed password
    $query = "INSERT INTO users (names, email, phone, role, password, image, active, campus) 
              VALUES ('$name', '$email', '$phone', '$role', '$hashed_password', 'assets/img/av.png', '1', " . 
              ($campus ? "'$campus'" : "NULL") . ")";
    $result = mysqli_query($connection, $query);

    if ($result) {
      sendWelcomeEmail($email, $name, $password);
      echo "<script>alert('User added successfully.')</script>";
      echo "<script>window.location.href='add_user.php'</script>";
    } else {
      echo "<script>alert('Error occurred while adding user.')</script>";
    }
  } else {
    echo "<script>alert('Please fill all required fields.')</script>";
  }
}
?>