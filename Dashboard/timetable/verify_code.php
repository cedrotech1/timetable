<?php
session_start();
include("connection.php");

$error = "";
$successMessage = "";

// Check if user has started the verification process
if (!isset($_SESSION['temp_student_regnumber'])) {
    header("Location: index.php");
    exit();
}

// Get expiry time from database
$regnumber = $_SESSION['temp_student_regnumber'];
$query = "SELECT token FROM info WHERE regnumber = '$regnumber'";
$result = mysqli_query($connection, $query);
$row = mysqli_fetch_assoc($result);
$expiry_time = $row['token'] ?? 0;

if (isset($_POST["verify"])) {
    $code = mysqli_real_escape_string($connection, $_POST['code']);
    
    // Query to check code and expiry time
    $query = "SELECT * FROM info WHERE regnumber = '$regnumber' AND code = '$code'";
    $result = mysqli_query($connection, $query);
    
    if (mysqli_num_rows($result) > 0) {
        $student = mysqli_fetch_assoc($result);
        
        // Check if code has expired (1 minute)
        if (time() > $student['token']) {
            $error = "Verification code has expired. Please request a new one.";
            // Clear expired code
            $update_query = "UPDATE info SET code = NULL, token = NULL WHERE regnumber = '$regnumber'";
            mysqli_query($connection, $update_query);
        } else {
            // Code is valid, set permanent session variables
            $_SESSION['student_id'] = $_SESSION['temp_student_id'];
            $_SESSION['student_regnumber'] = $_SESSION['temp_student_regnumber'];
            $_SESSION['student_name'] = $_SESSION['temp_student_name'];
            $_SESSION['student_email'] = $_SESSION['temp_student_email'];
            $_SESSION['student_campus'] = $_SESSION['temp_student_campus'];
            $_SESSION['student_college'] = $_SESSION['temp_student_college'];
            $_SESSION['student_school'] = $_SESSION['temp_student_school'];
            $_SESSION['student_program'] = $_SESSION['temp_student_program'];
            $_SESSION['student_year'] = $_SESSION['temp_student_year'];
            $_SESSION['student_gender'] = $_SESSION['temp_student_gender'];
            $_SESSION['student_status'] = $_SESSION['temp_student_status'];
            
            // Clear temporary session variables
            unset($_SESSION['temp_student_id']);
            unset($_SESSION['temp_student_regnumber']);
            unset($_SESSION['temp_student_name']);
            unset($_SESSION['temp_student_email']);
            unset($_SESSION['temp_student_campus']);
            unset($_SESSION['temp_student_college']);
            unset($_SESSION['temp_student_school']);
            unset($_SESSION['temp_student_program']);
            unset($_SESSION['temp_student_year']);
            unset($_SESSION['temp_student_gender']);
            unset($_SESSION['temp_student_status']);
            
            // Clear the verification code
            $update_query = "UPDATE info SET code = NULL, token = NULL WHERE regnumber = '$regnumber'";
            mysqli_query($connection, $update_query);
            
            header("Location: Students/index.php");
            exit();
        }
    } else {
        $error = "Invalid verification code. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Verify Code - UR-HUYE</title>
    <link href="./icon1.png" rel="icon" type="image/x-icon">
    <link href="https://fonts.gstatic.com" rel="preconnect">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans|Nunito|Poppins" rel="stylesheet">
    <link href="./Dashboard/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="./Dashboard/assets/css/style.css" rel="stylesheet">
    <style>
        .logo1 {
            width: 70%;
            height: auto;
            margin-bottom: 10px;
        }
        .countdown {
            font-size: 1.2em;
            color: #dc3545;
            font-weight: bold;
            margin: 10px 0;
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
                            <div class="card mb-3">
                                <div class="card-body">
                                    <div class="pt-4 pb-2">
                                        <div class="row">
                                            <img class="logo1" src="./assets/img/ur.png" alt="">
                                        </div>
                                        <h5 class="card-title text-justify pb-0 fs-4">Verify Code</h5>
                                        <p class="text-center">Please enter the verification code sent to your phone sms ! </p>
                                        <div class="countdown text-center" id="countdown"></div>

                                        <?php if (!empty($error)): ?>
                                            <div class="alert alert-danger" role="alert">
                                                <?php echo $error; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($successMessage)): ?>
                                            <div class="alert alert-success" role="alert">
                                                <?php echo $successMessage; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <form class="row g-3 needs-validation" novalidate method="post" action="">
                                        <div class="col-12">
                                            <label for="verificationCode" class="form-label">Verification Code</label>
                                            <div class="input-group has-validation">
                                                <input type="text" name="code" class="form-control" id="verificationCode" required>
                                                <div class="invalid-feedback">Please enter the verification code</div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <button class="btn btn-primary w-100" name="verify" type="submit">
                                                Verify Code
                                            </button>
                                        </div>
                                        <div class="col-12 text-center">
                                            <a href="index.php" class="text-decoration-none">Request new code</a>
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

    <script>
        // Set the expiry time from PHP
        const expiryTime = <?php echo $expiry_time; ?>;
        
        function updateCountdown() {
            const now = Math.floor(Date.now() / 1000);
            const timeLeft = expiryTime - now;
            
            if (timeLeft <= 0) {
                document.getElementById('countdown').innerHTML = "Code expired!";
                // Refresh the page after 2 seconds
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, 2000);
                return;
            }
            
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            document.getElementById('countdown').innerHTML = 
                `Time remaining: ${minutes}:${seconds.toString().padStart(2, '0')}`;
        }

        // Update countdown every second
        updateCountdown();
        setInterval(updateCountdown, 1000);
    </script>
</body>
</html> 