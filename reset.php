<?php
// Include database connection file
include("connection.php");
require_once '../loadEnv.php';
session_start();

// Load the .env file
$filePath = __DIR__ . '/../.env'; // Corrected path
loadEnv($filePath);
include("./email_functions.php");

// Initialize error and success messages
$error = "";
$success = "";

// Step can now be passed via the URL (default is 1)
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$email = isset($_GET['email']) ? mysqli_real_escape_string($connection, $_GET['email']) : '';

// Check if form is submitted
if (isset($_POST["reset"])) {
    // Step 1: Requesting reset code (email provided)
    if ($step === 1) {
        $email = mysqli_real_escape_string($connection, $_POST['email']);
        
        // Fetch user from database based on email
        $sql = "SELECT id, names, active FROM users WHERE email='$email'";
        $result = mysqli_query($connection, $sql);
        
        if ($result && mysqli_num_rows($result) === 1) {
            $row = mysqli_fetch_assoc($result);
            if ($row['active'] == '1') {
                $names = $row['names'];
                
                // Generate a reset code
                $resetCode = rand(100000, 999999); // Generate a random 6-digit reset code
                
                // Update the user's reset code in the database
                $sqlUpdate = "UPDATE users SET resetcode='$resetCode' WHERE email='$email'";
                mysqli_query($connection, $sqlUpdate);
                
                // Send the reset code to user's email
                sendResetPasswordEmail($email, $names, $resetCode);

                // Redirect to step 2 with the email in the URL
                header("Location: reset.php?step=2&email=" . urlencode($email));
                exit;
            } else {
                $error = "This account is deactivated.";
            }
        } else {
            $error = "Email not found.";
        }
    }

    // Step 2: Verifying the reset code
    if ($step === 2) {
        $resetCode = mysqli_real_escape_string($connection, $_POST['reset_code']);
        
        // Fetch user based on email and reset code
        $sql = "SELECT id FROM users WHERE email='$email' AND resetcode='$resetCode' AND resetcode!=0";
        $result = mysqli_query($connection, $sql);
        
        if ($result && mysqli_num_rows($result) === 1) {
            // Code matches, proceed to reset password
            $_SESSION['code'] = $resetCode;
            
            header("Location: reset.php?step=3&email=" . urlencode($email));
            exit;
        } else {
            $error = "Invalid reset code.";
        }
    }

    // Step 3: Resetting the password
    if ($step === 3) {
        $newPassword = mysqli_real_escape_string($connection, $_POST['new_password']);
        $confirmPassword = mysqli_real_escape_string($connection, $_POST['confirm_password']);
        
        // Check if the passwords match
        if ($newPassword === $confirmPassword) {
            // Hash the new password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Update the password in the database
            $sqlUpdate = "UPDATE users SET password='$hashedPassword', resetcode=NULL WHERE email='$email'";
            if (mysqli_query($connection, $sqlUpdate)) {
                $success = "Your password has been successfully reset.";
                // Redirect after successful reset
                session_destroy();
                header("Location: login.php?reset=success");
                exit;
            } else {
                $error = "Failed to reset password. Please try again.";
            }
        } else {
            $error = "Passwords do not match.";
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Reset Password</title>
  <link href="./Dashboard/assets/img/icon1.png" rel="icon">
  <link href="./Dashboard/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
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
              <div class="card mb-3">
                <div class="card-body">
                  <div class="pt-4 pb-2">
                    <div class="row">
                      <img class="logo1" src="./assets/img/ur.png" alt="">
                    </div>

                    <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                      <?php echo $error; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                    <div class="alert alert-success" role="alert">
                      <?php echo $success; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($step === 1): ?>
                    <form method="post" action="reset.php?step=1">
                      <div class="col-12">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                      </div>
                      <br>
                      <div class="col-12">
                        <button class="btn btn-primary w-100" name="reset" type="submit">Send Reset Code</button>
                      </div>
                    </form>
                    <?php elseif ($step === 2): ?>
                    <form method="post" action="reset.php?step=2&email=<?php echo urlencode($email); ?>">
                      <div class="col-12">
                        <label for="reset_code" class="form-label">Enter Reset Code</label>
                        <input type="text" name="reset_code" class="form-control" required>
                      </div>
                      <br>
                      <div class="col-12">
                        <button class="btn btn-primary w-100" name="reset" type="submit">Verify Code</button>
                      </div>
                    </form>
                    <?php elseif ($step === 3 && isset($_SESSION['code'])): ?>
                    <form method="post" action="reset.php?step=3&email=<?php echo urlencode($email); ?>">
                      <div class="col-12">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required>
                      </div>
                      <div class="col-12">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                      </div>
                      <br>
                      <div class="col-12">
                        <button class="btn btn-primary w-100" name="reset" type="submit">Reset Password</button>
                      </div>
                    </form>
                    <?php else: ?>
                    <div class="alert alert-danger">
                        <p>Invalid step. Please try again.</p>
                    </div>
                    <button class="btn btn-outline-primary"><a href="reset.php">Try again </a></button>
                <?php endif; ?>
                    
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </main>

  <script src="assets/js/main.js"></script>
</body>
</html>
