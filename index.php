<?php
// Start output buffering at the very beginning
ob_start();
session_start();

// Error handling for database connection
function handleDbError($connection) {
    if (!$connection) {
        ob_clean(); // Clear any previous output
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Database connection failed: ' . mysqli_connect_error()
        ]);
        exit();
    }
}

// Set JSON headers for all AJAX responses
if(isset($_POST['ajax_login']) || isset($_POST['ajax_signup']) || isset($_POST['get_user_details'])) {
    ob_clean(); // Clear any previous output
    header('Content-Type: application/json');
}

// Error handler for JSON responses
function sendJsonResponse($status, $message, $data = []) {
    ob_clean(); // Clear any previous output
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

// Include database connection
try {
    ob_clean(); // Clear any previous output
    include("connection.php");
    handleDbError($connection);
} catch (Exception $e) {
    ob_clean(); // Clear any previous output
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection error: ' . $e->getMessage()
    ]);
    exit();
}

// AJAX endpoint to get user details
if(isset($_POST['get_user_details'])) {
    try {
        if (!isset($_POST['regnumber']) || empty($_POST['regnumber'])) {
            sendJsonResponse('error', 'Registration number is required');
        }

        $regnumber = mysqli_real_escape_string($connection, $_POST['regnumber']);
        $query = "SELECT * FROM info WHERE regnumber = '$regnumber'";
        $result = mysqli_query($connection, $query);

        if (!$result) {
            sendJsonResponse('error', 'Database query error: ' . mysqli_error($connection));
        }
        
        if(mysqli_num_rows($result) > 0) {
            sendJsonResponse('success', 'Registration number found');
        } else {
            sendJsonResponse('error', 'Registration number not found');
        }
    } catch (Exception $e) {
        sendJsonResponse('error', 'Server error: ' . $e->getMessage());
    }
}

// AJAX endpoint for login
if(isset($_POST['ajax_login'])) {
    try {
        ob_clean(); // Clear any previous output
        header('Content-Type: application/json');
        
        if (!isset($_POST['login_regnumber']) || !isset($_POST['login_password'])) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Registration number and password are required'
            ]);
            exit();
        }

        $regnumber = mysqli_real_escape_string($connection, $_POST['login_regnumber']);
        $password = mysqli_real_escape_string($connection, $_POST['login_password']);

        $query = "SELECT * FROM info WHERE regnumber = '$regnumber'";
        $result = mysqli_query($connection, $query);

        if (!$result) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Database query error: ' . mysqli_error($connection)
            ]);
            exit();
        }

        if (mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_assoc($result);
            if (password_verify($password, $user['password'])) {
                // Store user info in session
                $_SESSION['student_id'] = $user['id'];
                $_SESSION['student_regnumber'] = $user['regnumber'];
                $_SESSION['student_name'] = $user['names'];
                $_SESSION['student_email'] = $user['email'];
                $_SESSION['student_campus'] = $user['campus'];
                $_SESSION['student_college'] = $user['college'];
                $_SESSION['student_school'] = $user['school'];
                $_SESSION['student_program'] = $user['program'];
                $_SESSION['student_year'] = $user['yearofstudy'];
                $_SESSION['student_gender'] = $user['gender'];
                $_SESSION['student_status'] = $user['status'];

                echo json_encode([
                    'status' => 'success',
                    'message' => 'Login successful!',
                    'redirect' => true
                ]);
                exit();
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid password!'
                ]);
                exit();
            }
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Registration number not found!'
            ]);
            exit();
        }
    } catch (Exception $e) {
        ob_clean(); // Clear any previous output
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Server error: ' . $e->getMessage()
        ]);
        exit();
    }
}

// AJAX endpoint for signup
if(isset($_POST['ajax_signup'])) {
    try {
        // Validate required fields
        $required_fields = ['regnumber', 'phone', 'national_id', 'password', 'confirm_password'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                sendJsonResponse('error', ucfirst($field) . ' is required');
            }
        }

        $regnumber = mysqli_real_escape_string($connection, $_POST['regnumber']);
        $phone = mysqli_real_escape_string($connection, $_POST['phone']);
        $national_id = mysqli_real_escape_string($connection, $_POST['national_id']);
        $password = mysqli_real_escape_string($connection, $_POST['password']);
        $confirm_password = mysqli_real_escape_string($connection, $_POST['confirm_password']);

        // Validate password match
        if ($password !== $confirm_password) {
            sendJsonResponse('error', 'Passwords do not match!');
        }

        // Check if user exists with given credentials
        $query = "SELECT * FROM info WHERE regnumber = '$regnumber'";
        $result = mysqli_query($connection, $query);

        if (!$result) {
            sendJsonResponse('error', 'Database query error: ' . mysqli_error($connection));
        }

        if (mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_assoc($result);
            $errors = [];
            
            // Check phone number
            if($user['phone'] !== $phone) {
                $errors[] = "Phone number does not match";
            }
            
            // Check national ID
            if($user['national_id'] !== $national_id) {
                $errors[] = "National ID does not match";
            }
            
            if(empty($errors)) {
                // Hash the password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Update the password
                $update_query = "UPDATE info SET password = '$hashed_password' WHERE regnumber = '$regnumber'";
                if (mysqli_query($connection, $update_query)) {
                    sendJsonResponse('success', 'Password updated successfully! Redirecting to login...', ['redirect' => 'login.php']);
                } else {
                    sendJsonResponse('error', 'Error updating password: ' . mysqli_error($connection));
                }
            } else {
                sendJsonResponse('error', 'Validation failed: ' . implode(", ", $errors));
            }
        } else {
            sendJsonResponse('error', 'Registration number not found!');
        }
    } catch (Exception $e) {
        sendJsonResponse('error', 'Server error: ' . $e->getMessage());
    }
}

// Only show HTML if not an AJAX request
if(!isset($_POST['ajax_login']) && !isset($_POST['ajax_signup']) && !isset($_POST['get_user_details'])) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hostel Management System - Login & Signup</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@100;300;400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
        }
        .auth-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
        }
        .auth-box {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        .auth-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 25px rgba(0,0,0,0.15);
        }
        .form-group {
            margin-bottom: 25px;
        }
        .form-control {
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            padding: 12px;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
        }
        .nav-tabs {
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 30px;
        }
        .nav-tabs .nav-link {
            border: none;
            color: #666;
            font-weight: 500;
            padding: 10px 20px;
            transition: all 0.3s ease;
        }
        .nav-tabs .nav-link.active {
            color: #007bff;
            border-bottom: 2px solid #007bff;
            background: none;
        }
        .nav-tabs .nav-link:hover {
            border: none;
            color: #007bff;
        }
        .btn-primary {
            background: linear-gradient(45deg, #007bff, #0056b3);
            border: none;
            padding: 12px 30px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background: linear-gradient(45deg, #0056b3, #003d82);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,123,255,0.3);
        }
        .validation-error {
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: none;
        }
        .alert {
            display: none;
            margin-bottom: 20px;
            border-radius: 5px;
            padding: 15px;
            transition: all 0.3s ease;
        }
        .alert-danger {
            background-color: #fff5f5;
            border: 1px solid #feb2b2;
            color: #c53030;
        }
        .alert-success {
            background-color: #f0fff4;
            border: 1px solid #9ae6b4;
            color: #2f855a;
        }
        h2 {
            color: #333;
            font-weight: 600;
            margin-bottom: 30px;
        }
        label {
            color: #555;
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        /* Loading Spinner Styles */
        .spinner-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        .spinner-container {
            text-align: center;
        }
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }
        .spinner-text {
            color: #007bff;
            font-weight: 500;
            font-size: 16px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .btn-loading {
            position: relative;
            color: transparent !important;
        }
        .btn-loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin: -10px 0 0 -10px;
            border: 2px solid #fff;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
    </style>
</head>
<body>
    <!-- Loading Spinner -->
    <div class="spinner-overlay">
        <div class="spinner-container">
            <div class="spinner"></div>
            <div class="spinner-text">Processing...</div>
        </div>
    </div>

    <div class="container">
        <div class="auth-container">
            <ul class="nav nav-tabs" id="authTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="login-tab" data-toggle="tab" href="#login" role="tab">Login</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="signup-tab" data-toggle="tab" href="#signup" role="tab">Sign Up</a>
                </li>
            </ul>

            <div class="alert alert-danger" id="error-alert"></div>
            <div class="alert alert-success" id="success-alert"></div>

            <div class="tab-content" id="authTabsContent">
                <!-- Login Form -->
                <div class="tab-pane fade show active" id="login" role="tabpanel">
                    <div class="auth-box">
                        <h2 class="text-center mb-4">Login</h2>
                        <button type="button" class="btn btn-info btn-block mb-4" data-toggle="modal" data-target="#demoModal">
                            <i class="fas fa-users"></i> Demo Accounts
                        </button>
                        <form id="loginForm">
                            <div class="form-group">
                                <label for="login_regnumber">Registration Number</label>
                                <input type="text" class="form-control" id="login_regnumber" name="login_regnumber" required>
                            </div>

                            <div class="form-group">
                                <label for="login_password">Password</label>
                                <input type="password" class="form-control" id="login_password" name="login_password" required>
                            </div>

                            <button type="submit" class="btn btn-primary btn-block">Login</button>
                        </form>
                    </div>
                </div>

                <!-- Signup Form -->
                <div class="tab-pane fade" id="signup" role="tabpanel">
                    <div class="auth-box">
                        <h2 class="text-center mb-4">Sign Up</h2>
                        <button type="button" class="btn btn-info btn-block mb-4" data-toggle="modal" data-target="#signupDemoModal">
                            <i class="fas fa-users"></i> Demo Accounts
                        </button>
                        <div class="alert alert-info mb-4">
                            <strong>Demo Data:</strong><br>
                            Registration Number: REG001<br>
                            Phone: 0712345678<br>
                            National ID: 12345678
                        </div>
                        <form id="signupForm">
                            <div class="form-group">
                                <label for="regnumber">Registration Number</label>
                                <input type="text" class="form-control" id="regnumber" name="regnumber" required>
                                <div class="validation-error" id="regnumber-error"></div>
                            </div>

                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" required>
                                <div class="validation-error" id="phone-error"></div>
                            </div>

                            <div class="form-group">
                                <label for="national_id">National ID</label>
                                <input type="text" class="form-control" id="national_id" name="national_id" required>
                                <div class="validation-error" id="national_id-error"></div>
                            </div>

                            <div class="form-group">
                                <label for="password">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <div class="validation-error" id="password-error"></div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-block">Sign Up</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Demo Accounts Modal -->
    <div class="modal fade" id="demoModal" tabindex="-1" role="dialog" aria-labelledby="demoModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="demoModalLabel">Demo Accounts</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="demo-accounts">
                        <div class="demo-account mb-3">
                            <h6><i class="fas fa-user-graduate"></i> Huye Student / year 3</h6>
                            <p class="mb-1"><strong>Reg Number:</strong> 20231008</p>
                            <p class="mb-1"><strong>Phone:</strong> 0788609666</p>
                            <p class="mb-1"><strong>National ID:</strong> 1002003008</p>
                            <p class="mb-1"><strong>Password:</strong> 1234</p>
                            <button class="btn btn-sm btn-primary use-demo" data-reg="20231008" data-pass="1234">Use This Account</button>
                        </div>
                        <hr>
                        <div class="demo-account mb-3">
                            <h6><i class="fas fa-user-graduate"></i> Huye Student / year 3</h6>
                            <p class="mb-1"><strong>Reg Number:</strong> 20231007</p>
                            <p class="mb-1"><strong>Phone:</strong> 0721686167</p>
                            <p class="mb-1"><strong>National ID:</strong> 1002003007</p>
                            <p class="mb-1"><strong>Password:</strong> 1234</p>
                            <button class="btn btn-sm btn-primary use-demo" data-reg="20231007" data-pass="1234">Use This Account</button>
                        </div>
                     
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Signup Demo Accounts Modal -->
    <div class="modal fade" id="signupDemoModal" tabindex="-1" role="dialog" aria-labelledby="signupDemoModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="signupDemoModalLabel">Demo Accounts for Sign Up</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="demo-accounts">
                        <div class="demo-account mb-3">
                            <h6><i class="fas fa-user-graduate"></i> Huye Student / year 3</h6>
                            <p class="mb-1"><strong>Reg Number:</strong> 20231008</p>
                            <p class="mb-1"><strong>Phone:</strong> 0788609666</p>
                            <p class="mb-1"><strong>National ID:</strong> 1002003008</p>
                            <button class="btn btn-sm btn-primary use-signup-demo" 
                                data-reg="20231008" 
                                data-phone="0788609666" 
                                data-national-id="1002003008">Use This Account</button>
                        </div>
                        <hr>
                        <div class="demo-account mb-3">
                            <h6><i class="fas fa-user-graduate"></i> Huye Student / year 3</h6>
                            <p class="mb-1"><strong>Reg Number:</strong> 20231007</p>
                            <p class="mb-1"><strong>Phone:</strong> 0721686167</p>
                            <p class="mb-1"><strong>National ID:</strong> 1002003007</p>
                            <button class="btn btn-sm btn-primary use-signup-demo" 
                                data-reg="20231007" 
                                data-phone="0721686167" 
                                data-national-id="1002003007">Use This Account</button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
    $(document).ready(function() {
        function showSpinner() {
            $('.spinner-overlay').fadeIn();
        }

        function hideSpinner() {
            $('.spinner-overlay').fadeOut();
        }

        function showAlert(message, type) {
            $('#error-alert, #success-alert').hide();
            $(`#${type}-alert`).text(message).fadeIn();
            setTimeout(() => {
                $(`#${type}-alert`).fadeOut();
            }, 5000);
        }

        // Handle registration number input
        $('#regnumber').on('blur', function() {
            const regnumber = $(this).val().trim();
            if(regnumber) {
                $.ajax({
                    url: 'signup.php',
                    type: 'POST',
                    data: {
                        get_user_details: true,
                        regnumber: regnumber
                    },
                    dataType: 'json',
                    success: function(response) {
                        if(response.status === 'success') {
                            $('#regnumber-error').hide();
                        } else {
                            $('#regnumber-error').text(response.message).show();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', {xhr, status, error});
                        showAlert('An error occurred. Please try again.', 'error');
                    }
                });
            }
        });

        // Handle password confirmation
        $('#confirm_password').on('input', function() {
            const password = $('#password').val();
            const confirmPassword = $(this).val();
            if(password !== confirmPassword) {
                $('#password-error').text('Passwords do not match').show();
            } else {
                $('#password-error').hide();
            }
        });

        // Handle login form submission
        $('#loginForm').on('submit', function(e) {
            e.preventDefault();
            const $btn = $(this).find('button[type="submit"]');
            $btn.addClass('btn-loading').prop('disabled', true);
            showSpinner();

            $.ajax({
                url: 'signup.php',
                type: 'POST',
                data: {
                    ajax_login: true,
                    login_regnumber: $('#login_regnumber').val().trim(),
                    login_password: $('#login_password').val()
                },
                dataType: 'json',
                success: function(response) {
                    hideSpinner();
                    $btn.removeClass('btn-loading').prop('disabled', false);
                    if(response.status === 'success') {
                        showAlert(response.message, 'success');
                        if(response.redirect) {
                            setTimeout(() => {
                                window.location.href = 'Students/index.php';
                            }, 1000);
                        }
                    } else {
                        showAlert(response.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    hideSpinner();
                    $btn.removeClass('btn-loading').prop('disabled', false);
                    console.error('Login AJAX Error:', {xhr, status, error});
                    showAlert('An error occurred. Please try again.', 'error');
                }
            });
        });

        // Handle signup form submission
        $('#signupForm').on('submit', function(e) {
            e.preventDefault();
            const $btn = $(this).find('button[type="submit"]');
            $btn.addClass('btn-loading').prop('disabled', true);
            showSpinner();

            $.ajax({
                url: 'signup.php',
                type: 'POST',
                data: {
                    ajax_signup: true,
                    regnumber: $('#regnumber').val().trim(),
                    phone: $('#phone').val().trim(),
                    national_id: $('#national_id').val().trim(),
                    password: $('#password').val(),
                    confirm_password: $('#confirm_password').val()
                },
                dataType: 'json',
                success: function(response) {
                    hideSpinner();
                    $btn.removeClass('btn-loading').prop('disabled', false);
                    if(response.status === 'success') {
                        showAlert('Password updated successfully! Redirecting to login...', 'success');
                        // Clear form
                        $('#signupForm')[0].reset();
                        // Switch to login tab after 4 seconds
                        setTimeout(() => {
                            $('#login-tab').tab('show');
                            // Clear success message
                            $('#success-alert').hide();
                        }, 4000);
                    } else {
                        showAlert(response.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    hideSpinner();
                    $btn.removeClass('btn-loading').prop('disabled', false);
                    console.error('Signup AJAX Error:', {xhr, status, error});
                    showAlert('An error occurred. Please try again.', 'error');
                }
            });
        });

        // Handle tab switching
        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            $('.validation-error').hide();
            $('#error-alert, #success-alert').hide();
        });

        // Handle demo account selection
        $('.use-demo').click(function() {
            const regNumber = $(this).data('reg');
            const password = $(this).data('pass');
            
            $('#login_regnumber').val(regNumber);
            $('#login_password').val(password);
            
            $('#demoModal').modal('hide');
        });

        // Handle demo account selection for signup
        $('.use-signup-demo').click(function() {
            const regNumber = $(this).data('reg');
            const phone = $(this).data('phone');
            const nationalId = $(this).data('national-id');
            
            $('#regnumber').val(regNumber);
            $('#phone').val(phone);
            $('#national_id').val(nationalId);
            
            $('#signupDemoModal').modal('hide');
        });
    });
    </script>
</body>
</html>
<?php
}
?>
