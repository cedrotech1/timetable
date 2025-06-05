<?php
session_start();




include("connection.php");

// Query to select system status
$query = "SELECT * FROM system";
$result = mysqli_query($connection, $query);
$row = mysqli_fetch_assoc($result);
$status = $row['status'] ?? null;
$allow_message = $row['allow_message'] ?? null;

if ($status != "live") {
    header("Location: status.php");
    exit(); // Stop further script execution
}

$error = "";
$successMessage = "";
$isSubmitting = false;

// Rate limiting settings
$maxRequestsPerMinute = 60; // Max requests allowed per minute per user
$requestTimeout = 60; // Time window in seconds

if (!isset($_SESSION['request_times'])) {
    $_SESSION['request_times'] = [];
}

// Remove outdated requests from session
$currentTime = time();
$_SESSION['request_times'] = array_filter($_SESSION['request_times'], function ($timestamp) use ($currentTime, $requestTimeout) {
    return ($currentTime - $timestamp) < $requestTimeout;
});

// Check if request limit is exceeded
if (count($_SESSION['request_times']) >= $maxRequestsPerMinute) {
    $error = "Please try again later. There are too many requests at the moment.";
} else {
    if (isset($_POST["submit"])) {
        $_SESSION['request_times'][] = $currentTime; // Track the request time

        $regnumber = mysqli_real_escape_string($connection, $_POST['regnumber']);
        $nid = mysqli_real_escape_string($connection, $_POST['nid']);

        // Query to check student information
        $query = "SELECT * FROM info WHERE regnumber = '$regnumber' AND nid = '$nid'";
        $result = mysqli_query($connection, $query);

        if (mysqli_num_rows($result) > 0) {
            $student = mysqli_fetch_assoc($result);
            
            // Store student info in session
            $_SESSION['student_id'] = $student['id'];
            $_SESSION['student_regnumber'] = $student['regnumber'];
            $_SESSION['student_name'] = $student['names'];
            $_SESSION['student_email'] = $student['email'];
            $_SESSION['student_campus'] = $student['campus'];
            $_SESSION['student_college'] = $student['college'];
            $_SESSION['student_school'] = $student['school'];
            $_SESSION['student_program'] = $student['program'];
            $_SESSION['student_year'] = $student['yearofstudy'];
            $_SESSION['student_gender'] = $student['gender'];
            $_SESSION['student_status'] = $student['status'];

            
                header("Location: Students/index.php");
         
            exit();
        } else {
            $error = "Invalid registration number or national ID. Please try again.";
        }
    }
}

if (!empty($error)) {
    // echo "<script>alert('$error');</script>";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>UR-HUYE</title>
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
  </style>

<style>
        body {
            font-family: Arial, sans-serif;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: white;
            padding: 20px;
            border-radius: 8px;
            width: 300px;
            text-align: center;
        }
        .close-btn {
            float: right;
            cursor: pointer;
        }
        .result {
            margin-top: 10px;
            font-size: 14px;
            color: green;
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
                    <h5 class="card-title text-justify pb-0 fs-4">Authentication...</h5>

                    Demo Credentials for Testing: <br/>
                    1. Reg: 20231007, NID: 1002003007  :1 <br/>
                    2. Reg: 20231016, NID: 1002003016  :1 <br/>
                    3. Reg: 20231031, NID: 1002003031  :3 <br/>
                    4. Reg: 20231039, NID: 1002003039  :3

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
                      <label for="yourRegNumber" class="form-label">REGISTRATION NUMBER.</label>
                      <div class="input-group has-validation">
                        <input type="text" name="regnumber" class="form-control" id="yourRegNumber" value="20231007" required>
                        <div class="invalid-feedback">REGISTRATION NUMBER</div>
                      </div>
                    </div>

                    <div class="col-12">
                      <label for="yourRegNumber" class="form-label">NATIONAL ID NUMBER</label>
                      <div class="input-group has-validation">
                        <input type="text" name="nid" class="form-control" id="yourRegNumber" value="1002003007" required>
                        <div class="invalid-feedback">Please enter your national ID number</div>
                      </div>
                    </div>
                    <div class="col-12">
                      <button class="btn btn-primary w-100" name="submit" type="submit" <?php if ($isSubmitting): ?>
                          disabled <?php endif; ?>>
                        <?php echo $isSubmitting ? "Sending Email..." : "Submit"; ?>
                      </button>
                    </div>
               
                    <div class="col-12">
                    
                    </div>
                  </form>
                </div>
              </div>
              <div class="row">


             
                <div class="col-6">
                <button style="border:2px solid green;background-color:white;width:100%" <?php if($allow_message!='allow'){echo 'disabled';} ?> class="btn btn-default"> 
                  <a href="message.php" > message</a></button>


                </div>

                <div class="col-6">
                  
                <button style="border:2px solid gray;background-color:white;width:100%" class="btn btn-default"> 
                  <a href="rejected.php">  my card</a></button>


                </div>
              </div>
            
   <!-- <p   id="openModal">message us</p> -->
    <br>

  
            </div>
          </div>
        </div>
      </section>
    </div>
  </main>
  
  <script>
        const openModal = document.getElementById('openModal');
        const closeModal = document.getElementById('closeModal');
        const modal = document.getElementById('myModal');
        const form = document.getElementById('checkEmailForm');
        const resultDiv = document.getElementById('emailResult');

        // Open modal
        openModal.addEventListener('click', () => {
            modal.style.display = 'flex';
        });

        // Close modal
        closeModal.addEventListener('click', () => {
            modal.style.display = 'none';
        });

        window.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });

        // Handle form submission with AJAX
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const regNumber = document.getElementById('regNumber').value;

            fetch('check_email.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `regNumber=${regNumber}`,
            })
            .then(response => response.text())
            .then(data => {
                resultDiv.textContent = data;
            })
            .catch(error => {
                resultDiv.textContent = 'Error fetching email.';
                console.error('Error:', error);
            });
        });
    </script>
</body>

</html>