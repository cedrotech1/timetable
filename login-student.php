<?php
// Start the session
session_start();

// Include database connection
include("connection.php");

// Initialize error variable
$error = "";

// Check if form is submitted
if (isset($_POST["login"])) {
    // Get registration number and password
    $regnumber = mysqli_real_escape_string($connection, $_POST['regnumber']);
    $password = $_POST['password']; // no need to escape, used in password_verify

    // Fetch student from database based on regnumber
    $sql = "SELECT id, regnumber, email, password, group_id FROM student WHERE regnumber='$regnumber'";
    $result = mysqli_query($connection, $sql);

    if ($result && mysqli_num_rows($result) === 1) {
        $row = mysqli_fetch_assoc($result);

        // Verify password
        if (password_verify($password, $row['password'])) {
            // Successful login
            $_SESSION['loggedin'] = true;
            $_SESSION['regnumber'] = $row['regnumber'];
            $_SESSION['student_id'] = $row['id'];
            $_SESSION['email'] = $row['email'];
            $_SESSION['group_id'] = $row['group_id'];
            $_SESSION['role'] = 'student';

            echo "<script>window.location.href='timetable-view.php'</script>";
            exit;
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "Registration number not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="utf-8">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<title>UR-TIMETABLE - Student Login</title>
<link href="./Dashboard/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="./Dashboard/assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
<link href="./Dashboard/assets/css/style.css" rel="stylesheet">
<style>
.logo1 { width: 70%; height: auto; margin-bottom: 10px; }
</style>
</head>

<body>
<main>
<div class="container">
  <section class="section register min-vh-100 d-flex flex-column align-items-center justify-content-center py-4">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-lg-4 col-md-6 d-flex flex-column align-items-center justify-content-center">
          <div class="d-flex justify-content-center py-4"></div>
          <div class="card mb-3">
            <div class="card-body">
              <div class="pt-4 pb-2 text-center">
                <img class="logo1" src="./assets/img/ur.png" alt="">
                <h3 class="text-xl font-bold">Student Sign in</h3>
                <?php if (!empty($error)): ?>
                <div class="alert alert-danger mt-2"><?php echo $error; ?></div>
                <?php endif; ?>
              </div>

              <form class="row g-3 needs-validation" novalidate method="post" action="login-student.php">

                <div class="col-12">
                  <label for="yourRegnumber" class="form-label">Registration Number</label>
                  <input type="text" name="regnumber" class="form-control" id="yourRegnumber" required>
                  <div class="invalid-feedback">Please enter your registration number.</div>
                </div>

                <div class="col-12">
                  <label for="yourPassword" class="form-label">Password</label>
                  <input type="password" name="password" class="form-control" id="yourPassword" required>
                  <div class="invalid-feedback">Please enter your password!</div>
                </div>

                <div class="col-12">
                  <div class="form-check">
                    <a href="reset.php"><label class="form-check-label">Reset password</label></a>
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
</main>

<a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
