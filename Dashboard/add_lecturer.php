<?php

include('connection.php');

// Get user's role and campus
$id = $_SESSION['id'];
$sql = "SELECT * FROM users WHERE id = $id";
$result = mysqli_query($connection, $sql);
$row = mysqli_fetch_assoc($result);
$mycampus = $row['campus'];
$role = $row['role'];

// Get all campuses for selection
if($role === 'warefare'){       
    $campuses_query = mysqli_query($connection, "SELECT * FROM campus WHERE id = $mycampus ORDER BY name");
} else {
    $campuses_query = mysqli_query($connection, "SELECT * FROM campus ORDER BY name");
}
$campuses = [];
while ($campus = mysqli_fetch_assoc($campuses_query)) {
    $campuses[] = $campus;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => '', 'data' => null];
    
    try {
        // Validate required fields
        $required_fields = ['names', 'email', 'phone', 'campus_id'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                throw new Exception("$field is required");
            }
        }

        $names = mysqli_real_escape_string($connection, $_POST['names']);
        $email = mysqli_real_escape_string($connection, $_POST['email']);
        $phone = mysqli_real_escape_string($connection, $_POST['phone']);
        $campus_id = intval($_POST['campus_id']);

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format");
        }

        // Check for duplicate email
        $check_query = "SELECT id FROM users WHERE email = ?";
        $check_stmt = mysqli_prepare($connection, $check_query);
        mysqli_stmt_bind_param($check_stmt, "s", $email);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);

        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            throw new Exception("Email '$email' already exists");
        }

        // Generate a random password
        $password = bin2hex(random_bytes(8)); // 16 characters
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert lecturer
        $insert_query = "INSERT INTO users (names, email, phone, campus, role, password, active, image) VALUES (?, ?, ?, ?, 'lecturer', ?, 1, 'upload/icon1.png')";
        $insert_stmt = mysqli_prepare($connection, $insert_query);
        mysqli_stmt_bind_param($insert_stmt, "sssss", $names, $email, $phone, $campus_id, $hashed_password);

        if (mysqli_stmt_execute($insert_stmt)) {
            $response['success'] = true;
            $response['message'] = "Lecturer added successfully";
            $response['data'] = [
                'password' => $password // Send the generated password back to display to admin
            ];
        } else {
            throw new Exception("Error inserting lecturer: " . mysqli_error($connection));
        }

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }

    // Send JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    <title>Add Lecturer - UR-TIMETABLE</title>
    <link href="assets/img/icon1.png" rel="icon">
    <link href="assets/img/icon1.png" rel="apple-touch-icon">
    
    <!-- Include your existing CSS files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>
    <?php
    include("./includes/header.php");
    include("./includes/menu.php");
    ?>

    <main id="main" class="main">
        <div class="pagetitle">
            <h1>Add Lecturer</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item">Lecturers</li>
                    <li class="breadcrumb-item active">Add Lecturer</li>
                </ol>
            </nav>
        </div>

        <section class="section">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Add New Lecturer</h5>
                            
                            <form id="lecturerForm" class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Campus</label>
                                    <select class="form-select" name="campus_id" required>
                                        <option value="">Select Campus</option>
                                        <?php foreach ($campuses as $campus): ?>
                                        <option value="<?php echo $campus['id']; ?>"><?php echo htmlspecialchars($campus['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Full Names</label>
                                    <input type="text" class="form-control" name="names" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Phone</label>
                                    <input type="tel" class="form-control" name="phone" required>
                                </div>

                                <div class="text-center">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-plus-circle me-1"></i>Add Lecturer
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Password Modal -->
    <div class="modal fade" id="passwordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Lecturer Credentials</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Please save these credentials for the new lecturer:</p>
                    <div class="alert alert-info">
                        <strong>Email:</strong> <span id="lecturerEmail"></span><br>
                        <strong>Password:</strong> <span id="lecturerPassword"></span>
                    </div>
                    <p class="text-danger">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        This password will not be shown again. Please make sure to save it.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Include your existing JS files -->
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>

    <script>
    document.getElementById('lecturerForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const form = this;
        const submitButton = form.querySelector('button[type="submit"]');
        const originalText = submitButton.innerHTML;

        try {
            submitButton.disabled = true;
            submitButton.innerHTML = `
                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                Adding...
            `;

            const formData = new FormData(form);
            const response = await fetch('add_lecturer.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                // Show password modal
                document.getElementById('lecturerEmail').textContent = formData.get('email');
                document.getElementById('lecturerPassword').textContent = result.data.password;
                const passwordModal = new bootstrap.Modal(document.getElementById('passwordModal'));
                passwordModal.show();
                
                // Reset form
                form.reset();
            } else {
                // Show error message
                alert(result.message || 'Failed to add lecturer');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred while adding the lecturer');
        } finally {
            submitButton.disabled = false;
            submitButton.innerHTML = originalText;
        }
    });
    </script>
</body>
</html> 