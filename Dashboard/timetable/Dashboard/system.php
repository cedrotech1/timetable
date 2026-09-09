<?php
session_start();
include('connection.php');
// include ('./includes/auth.php');
// checkUserRole(['information_modifier']);

// Handle Academic Year CRUD operations
if (isset($_POST['add_year'])) {
    $year_label = $connection->real_escape_string($_POST['year_label']);
    
    // Validate if year already exists
    $check_query = "SELECT id FROM academic_year WHERE year_label = '$year_label'";
    $check_result = $connection->query($check_query);
    
    if ($check_result->num_rows > 0) {
        echo "<script>alert('Academic year already exists!'); window.location.href='system.php';</script>";
        exit();
    }
    
    $query = "INSERT INTO academic_year (year_label) VALUES ('$year_label')";
    $connection->query($query);
    echo "<script>window.location.href='system.php'</script>";
}

if (isset($_POST['delete_year'])) {
    $year_id = $connection->real_escape_string($_POST['year_id']);
    
    // Check if year is being used in system settings
    $check_query = "SELECT id FROM system WHERE accademic_year_id = '$year_id'";
    $check_result = $connection->query($check_query);
    
    if ($check_result->num_rows > 0) {
        echo "<script>alert('Cannot delete academic year as it is currently in use!'); window.location.href='system.php';</script>";
        exit();
    }
    
    $query = "DELETE FROM academic_year WHERE id = '$year_id'";
    $connection->query($query);
    echo "<script>window.location.href='system.php'</script>";
}

if (isset($_POST['update'])) {
    $status = $connection->real_escape_string($_POST['status']);
    $academic_year_id = $connection->real_escape_string($_POST['academic_year_id']);
    $semester = $connection->real_escape_string($_POST['semester']);
    $userid = $_SESSION['id'] ?? 1; // Default to 1 if not set

    // Validate if academic year exists
    $check_query = "SELECT id FROM academic_year WHERE id = '$academic_year_id'";
    $check_result = $connection->query($check_query);
    
    if ($check_result->num_rows == 0) {
        echo "<script>alert('Selected academic year does not exist!'); window.location.href='system.php';</script>";
        exit();
    }

    $query = "UPDATE system SET 
              status = '$status',
              accademic_year_id = '$academic_year_id',
              semester = '$semester',
              userid = '$userid'";
    
    $result = $connection->query($query);
    if ($result) {
        echo "<script>window.location.href='system.php'</script>";
    } else {
        echo "Error: " . $connection->error;
    }
}

// Fetch current system settings
$system_query = "SELECT * FROM system LIMIT 1";
$system_result = $connection->query($system_query);
$system_data = $system_result->fetch_assoc();

// Fetch all academic years
$years_query = "SELECT * FROM academic_year ORDER BY year_label DESC";
$years_result = $connection->query($years_query);
?>


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title>UR-TIMETABLE</title>
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
  <style>
    .th {}
  </style>


</head>

<body>

  <?php
  include("./includes/header.php");
  include("./includes/menu.php");
  ?>



  <main id="main" class="main">



    <section class="section dashboard">
      <div class="row">



        <!-- Left side columns -->
        <div class="col-lg-12">
          <div class="row">

            <div class="card">
              <div class="card-body">
                <br>
                <ul class="nav nav-tabs d-flex" id="myTabjustified" role="tablist">
                  <li class="nav-item flex-fill" role="presentation">
                    <button class="nav-link w-100 active" id="system-tab" data-bs-toggle="tab"
                      data-bs-target="#system-justified" type="button" role="tab" aria-controls="system"
                      aria-selected="true">System Settings</button>
                  </li>
                  <li class="nav-item flex-fill" role="presentation">
                    <button class="nav-link w-100" id="academic-tab" data-bs-toggle="tab"
                      data-bs-target="#academic-justified" type="button" role="tab" aria-controls="academic"
                      aria-selected="false">Academic Years</button>
                  </li>
                </ul>

                <div class="tab-content pt-2" id="myTabjustifiedContent">
                  <!-- System Settings Tab -->
                  <div class="tab-pane fade show active" id="system-justified" role="tabpanel" aria-labelledby="system-tab">
                    <div class="row">
                      <div class="col-md-6"></div>
                      <div class="col-md-6">
                        <form class="mt-3" action="" method="POST">
                          <div class="col-md-12">
                            <div class="form-floating">
                              <input type="text" class="form-control" id="floatingStatus" placeholder="status" 
                                value='<?php echo $system_data['status'] ?? '' ?>' disabled>
                              <label for="floatingStatus">Current System Status</label>
                            </div>
                          </div>
                          <br>
                          <div class="col-md-12">
                            <div class="form-floating">
                              <select class="form-control" id="floatingStatus" name="status" required>
                                <option value="live" <?php echo ($system_data['status'] == 'live') ? 'selected' : ''; ?>>Live</option>
                                <option value="maintenance" <?php echo ($system_data['status'] == 'maintenance') ? 'selected' : ''; ?>>Maintenance</option>
                                <option value="offline" <?php echo ($system_data['status'] == 'offline') ? 'selected' : ''; ?>>Offline</option>
                                <option value="development" <?php echo ($system_data['status'] == 'development') ? 'selected' : ''; ?>>Development</option>
                              </select>
                              <label for="floatingStatus">Update System Status</label>
                            </div>
                          </div>
                          <br>
                          <div class="col-md-12">
                            <div class="form-floating">
                              <select class="form-control" id="floatingYear" name="academic_year_id" required>
                                <?php 
                                $years_result->data_seek(0);
                                while($year = $years_result->fetch_assoc()): 
                                ?>
                                  <option value="<?php echo $year['id']; ?>" 
                                    <?php echo ($system_data['accademic_year_id'] == $year['id']) ? 'selected' : ''; ?>>
                                    <?php echo $year['year_label']; ?>
                                  </option>
                                <?php endwhile; ?>
                              </select>
                              <label for="floatingYear">Academic Year</label>
                            </div>
                          </div>
                          <br>
                          <div class="col-md-12">
                            <div class="form-floating">
                              <select class="form-control" id="floatingSemester" name="semester" required>
                                <option value="1" <?php echo ($system_data['semester'] == '1') ? 'selected' : ''; ?>>Semester 1</option>
                                <option value="2" <?php echo ($system_data['semester'] == '2') ? 'selected' : ''; ?>>Semester 2</option>
                                <option value="3" <?php echo ($system_data['semester'] == '3') ? 'selected' : ''; ?>>Semester 3</option>
                              </select>
                              <label for="floatingSemester">Semester</label>
                            </div>
                          </div>
                          <br>
                          <button type="submit" name='update' class="btn btn-primary col-12">Update System Settings</button>
                        </form>
                      </div>
                    </div>
                  </div>

                  <!-- Academic Years Tab -->
                  <div class="tab-pane fade" id="academic-justified" role="tabpanel" aria-labelledby="academic-tab">
                    <div class="row">
                      <div class="col-md-6">
                        <div class="card">
                          <div class="card-body">
                            <h5 class="card-title">Add New Academic Year</h5>
                            <form action="" method="POST">
                              <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="year_label" name="year_label" required>
                                <label for="year_label">Academic Year (e.g., 2023-2024)</label>
                              </div>
                              <button type="submit" name="add_year" class="btn btn-primary">Add Year</button>
                            </form>
                          </div>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="card">
                          <div class="card-body">
                            <h5 class="card-title">Academic Years List</h5>
                            <div class="table-responsive">
                              <table class="table table-striped">
                                <thead>
                                  <tr>
                                    <th>Year Label</th>
                                    <th>Action</th>
                                  </tr>
                                </thead>
                                <tbody>
                                  <?php 
                                  $years_result->data_seek(0);
                                  while($year = $years_result->fetch_assoc()): 
                                  ?>
                                  <tr>
                                    <td><?php echo $year['year_label']; ?></td>
                                    <td>
                                      <form action="" method="POST" style="display: inline;">
                                        <input type="hidden" name="year_id" value="<?php echo $year['id']; ?>">
                                        <button type="submit" name="delete_year" class="btn btn-danger btn-sm" 
                                          onclick="return confirm('Are you sure you want to delete this academic year?')">
                                          Delete
                                        </button>
                                      </form>
                                    </td>
                                  </tr>
                                  <?php endwhile; ?>
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




          </div>
        </div><!-- End Left side columns -->


      </div>
    </section>

  </main><!-- End #main -->

  <!-- ======= Footer ======= -->

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

</body>

</html>
