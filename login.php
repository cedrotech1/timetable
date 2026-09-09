<?php
// Start the session
session_start();

// Include database connection file
include("connection.php");

$system_data_setting = "SELECT * FROM system LIMIT 1";
$system_data_result = mysqli_query($connection, $system_data_setting);
$accademic_year_id = null;
$semester = null;
if ($system_data_result && mysqli_num_rows($system_data_result)) {
  $system_data = mysqli_fetch_assoc($system_data_result);
  $accademic_year_id = $system_data['accademic_year_id'];
  $semester = $system_data['semester'];
}

// get academic year label
$academic_year_label = '';
if ($accademic_year_id) {
  $academic_years_query = "SELECT * FROM academic_year WHERE id = '$accademic_year_id' ORDER BY year_label DESC LIMIT 1";
  $academic_years_result_label = mysqli_query($connection, $academic_years_query);
  if ($academic_years_result_label && mysqli_num_rows($academic_years_result_label)) {
    $academic_year = mysqli_fetch_assoc($academic_years_result_label);
    $academic_year_label = $academic_year['year_label'];
  }
}

$_SESSION['academic_year_id'] = $accademic_year_id;
$_SESSION['semester'] = $semester;
$_SESSION['academic_year_label'] = $academic_year_label;

// Initialize the error variable
$error = "";

// Check if form is submitted
if (isset($_POST["login"])) {
    $identifierInput = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($identifierInput === '') {
        $error = "Please enter your email or UR email.";
    } else {
        $identifier = strtolower($identifierInput);
        $sql = "SELECT id, email, ur_email, role, password, active, names, campus, school, image 
                FROM users 
                WHERE (LOWER(email) = ? OR LOWER(ur_email) = ?) 
                LIMIT 1";

        if ($stmt = $connection->prepare($sql)) {
            $stmt->bind_param('ss', $identifier, $identifier);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $row = $result->fetch_assoc();

                if (password_verify($password, $row['password'])) {
                    if ($row['active'] == '1') {
                        $_SESSION['loggedin'] = true;
                        $_SESSION['email'] = !empty($row['email']) ? $row['email'] : $row['ur_email'];
                        $_SESSION['ur_email'] = $row['ur_email'];
                        $_SESSION['role'] = $row['role'];
                        $_SESSION['id'] = $row['id'];
                        $_SESSION['campus'] = $row['campus'];
                        $_SESSION['school_id'] = $row['school'];
                        $_SESSION['names'] = $row['names'];
                        $_SESSION['user_image'] = $row['image'];

                        echo "<script>window.location.href='Dashboard/index.php'</script>";
                        exit;
                    } else {
                        $error = "Sorry! Your account is deactivated by the admin.";
                    }
                } else {
                    $error = "Invalid password.";
                }
            } else {
                $error = "Account not found.";
            }

            $stmt->close();
        } else {
            $error = "An error occurred while processing your request.";
        }
    }
}
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
  <link href="./Dashboard/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="./Dashboard/assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="./Dashboard/assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
  <link href="./Dashboard/assets/vendor/quill/quill.snow.css" rel="stylesheet">
  <link href="./Dashboard/assets/vendor/quill/quill.bubble.css" rel="stylesheet">
  <link href="./Dashboard/assets/vendor/remixicon/remixicon.css" rel="stylesheet">
  <link href="./Dashboard/assets/vendor/simple-datatables/style.css" rel="stylesheet">

  <!-- Template Main CSS File -->
  <link href="./Dashboard/assets/css/style.css" rel="stylesheet">
  <style>
    .logo1 {
    width: 70%; /* Set your desired width */
    height: auto; /* Maintains aspect ratio */
    margin-bottom:10px
}
</style>
</head>

<body>

  <main>
    <div class="container">

      <section class="section register min-vh-100 d-flex flex-column align-items-center justify-content-center py-4">
        <div class="container">
          <div class="row justify-content-center">
            <div class="col-lg-4 col-md-6 d-flex flex-column align-items-center justify-content-center">

              <div class="d-flex justify-content-center py-4"></div><!-- End Logo -->

              <div class="card mb-3">
                <div class="card-body">

                  <div class="pt-4 pb-2">
                  <div class="row">
                      <img class="logo1" src="./assets/img/ur.png" alt="">
              </div>

              <h3 className="text-xl font-bold">Sign in..... </h3>
<!-- <h2>Empower your campus life, sign in.</h2> -->
                    <!-- Display error message if any -->
                    <?php if (!empty($error)): ?>
                    <div class="alert alert-danger" role="alert">
                      <?php echo $error; ?>
                    </div>
                    <?php endif; ?>

                  </div>

                  <form class="row g-3 needs-validation" novalidate method="post" action="login.php">

                    

                    <div class="col-12">
                      <label for="yourUsername" class="form-label">Email</label>
                      <div class="input-group has-validation">
                        <span class="input-group-text" id="inputGroupPrepend">@</span>
                        <input type="email" name="email" class="form-control" id="yourUsername" required>
                        <div class="invalid-feedback">Please enter your email.</div>
                      </div>
                    </div>

                    <div class="col-12">
                      <label for="yourPassword" class="form-label">Password</label>
                      <input type="password" name="password" class="form-control" id="yourPassword" required>
                      <div class="invalid-feedback">Please enter your password!</div>
                    </div>

                    <div class="col-12">
                      <div class="form-check">
                        
                       <a href="reset.php"><label class="form-check-label" for="reset">reset password</label></a> 
                      </div>
                    </div>
                    <div class="col-12">
                      <button class="btn btn-primary w-100" name='login' type="submit">Login</button>
                    </div>
                  
                  </form>

                </div>
              </div>

          

            </div>
          </div>
        </div>
      </section>

    </div>
  </main><!-- End #main -->



  <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i
      class="bi bi-arrow-up-short"></i></a>

  <!-- Template Main JS File -->
  <script src="assets/js/main.js"></script>
  <!-- Add Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Handle demo account selection
      document.querySelectorAll('.use-demo').forEach(button => {
        button.addEventListener('click', function() {
          const email = this.dataset.email;
          const password = this.dataset.pass;
          
          document.getElementById('yourUsername').value = email;
          document.getElementById('yourPassword').value = password;
          
          // Close the modal
          const modal = bootstrap.Modal.getInstance(document.getElementById('demoModal'));
          modal.hide();
        });
      });

      // Initialize all modals
      var modals = document.querySelectorAll('.modal');
      modals.forEach(function(modal) {
        new bootstrap.Modal(modal);
      });
    });
  </script>

</body>

</html>
