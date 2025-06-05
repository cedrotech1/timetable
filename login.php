<?php
// Start the session
session_start();

// Include database connection file
include("connection.php");

// Initialize the error variable
$error = "";

// Check if form is submitted
if (isset($_POST["login"])) {
    // Define email and password variables and prevent SQL injection
    $email = mysqli_real_escape_string($connection, $_POST['email']);
    $password = $_POST['password']; // No need to escape this as it's not directly used in the query

    // Fetch user from database based on email
    $sql = "SELECT id, email, role, password, active, names,campus image FROM users WHERE email='$email'";
    $result = mysqli_query($connection, $sql);

    if ($result && mysqli_num_rows($result) === 1) {
        $row = mysqli_fetch_assoc($result);

        // Verify hashed password retrieved from the database
        if (password_verify($password, $row['password'])) {
            if ($row['active'] == '1') {
                // Password is correct, start a new session
                $_SESSION['loggedin'] = true;
                $_SESSION['email'] = $row['email'];
                $_SESSION['role'] = $row['role'];
                $_SESSION['id'] = $row['id'];
                $_SESSION['campus'] = $row['campus'];



                echo "<script>window.location.href='Dashboard/index.php'</script>";
                exit; // Exit script after redirection
            } else {
                $error = "Sorry! Your account is deactivated by the admin.";
            }
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "Email not found.";
    }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title>UR-HOSTELS</title>
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

                    <button type="button" class="btn btn-info btn-block mb-4" data-bs-toggle="modal" data-bs-target="#demoModal">
                      <i class="bi bi-people"></i> Demo Accounts
                    </button>

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

  <!-- Demo Accounts Modal -->
  <div class="modal fade" id="demoModal" tabindex="-1" aria-labelledby="demoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="demoModalLabel">Demo Accounts</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="demo-accounts">
            <div class="demo-account mb-3">
              <h6><i class="bi bi-person"></i> Admin Account</h6>
              <p class="mb-1"><strong>Email:</strong> cedrickhakuzimana@gmail.com</p>
              <p class="mb-1"><strong>Password:</strong> 1234</p>
              <button class="btn btn-sm btn-primary use-demo" data-email="cedrickhakuzimana@gmail.com" data-pass="1234">Use This Account</button>
            </div>
            <hr>
            <div class="demo-account mb-3">
              <h6><i class="bi bi-person"></i> Huye welfare  Account</h6>
              <p class="mb-1"><strong>Email:</strong> cedrickhakuzimana75@gmail.com</p>
                <p class="mb-1"><strong>Password:</strong> 1234</p>
                <button class="btn btn-sm btn-primary use-demo" data-email="cedrickhakuzimana75@gmail.com" data-pass="1234">Use This Account</button>
            </div>
            <hr>
            <div class="demo-account mb-3">
              <h6><i class="bi bi-person"></i> remera welfare  Account</h6>
              <p class="mb-1"><strong>Email:</strong> akimana@gmail.com</p>
                <p class="mb-1"><strong>Password:</strong> 1234</p>
                <button class="btn btn-sm btn-primary use-demo" data-email="akimana@gmail.com" data-pass="1234">Use This Account</button>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

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
