<?php
session_start();
include("connection.php");

if (!isset($_SESSION['id'])) {
    header("Location: index.php");
    exit();
}
// fetch campus
$current_user_id = $_SESSION['id'];
$stmt = $connection->prepare("SELECT role, campus, college, school FROM users WHERE id = ?");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();
$current_user = $result->fetch_assoc();
$campus_id = $current_user['campus'];



$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $names = trim($_POST['names'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    
    // Basic validation
    if (empty($names) || empty($email)) {
        $error = 'Please fill in all required fields';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        // Check if email already exists
        $stmt = $connection->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = 'Email already exists';
        } else {
            // Handle file upload
            $imagePath = 'assets/img/default-avatar.png';
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/lecturers/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $fileExt = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
                $allowedExt = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array($fileExt, $allowedExt)) {
                    $fileName = uniqid('lecturer_') . '.' . $fileExt;
                    $targetPath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetPath)) {
                        $imagePath = $targetPath;
                    }
                }
            }
            
            // Generate a random password
            $password = password_hash('23122312', PASSWORD_DEFAULT);
            $resetCode = '';
            $status = 1;

            
            // Insert new lecturer
            $stmt = $connection->prepare("INSERT INTO users (names, email, phone, image, role, password, active, resetcode, campus) 
                                       VALUES (?, ?, ?, ?, 'lecturer', ?, ?, ?, ?)");
            $stmt->bind_param("ssssssii", $names, $email, $phone, $imagePath, $password, $resetCode, $status, $campus_id);
            
            if ($stmt->execute()) {
                $success = 'Lecturer added successfully';
                // Clear form
                $_POST = [];
            } else {
                $error = 'Failed to add lecturer: ' . $connection->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta content="width=device-width, initial-scale=1.0" name="viewport" />
  <title>Add New Lecturer - UR-TIMETABLE</title>
  <link href="assets/img/icon1.png" rel="icon" />
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet" />
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet" />
  <link href="assets/css/style.css" rel="stylesheet" />
  <style>
    .profile-image-preview {
      width: 150px;
      height: 150px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid #dee2e6;
      cursor: pointer;
    }
    .profile-image-upload {
      display: none;
    }
  </style>
</head>
<body>
  <?php include("./includes/header.php"); ?>
  <?php include("./includes/menu.php"); ?>

  <main id="main" class="main">
    <div class="pagetitle">
      <h1>Add New Lecturer</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <li class="breadcrumb-item"><a href="lecturers.php">Lecturers</a></li>
          <li class="breadcrumb-item active">Add New</li>
        </ol>
      </nav>
    </div>

    <div class="container">
      <div class="row justify-content-center">
        <div class="col-lg-8">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Lecturer Information</h5>
              
              <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
              <?php endif; ?>
              
              <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
              <?php endif; ?>

              <form action="add_lecturer.php" method="POST" enctype="multipart/form-data" class="row g-3">
                

                <div class="col-md-6">
                  <label for="names" class="form-label">Full Name <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="names" name="names" required 
                         value="<?php echo htmlspecialchars($_POST['names'] ?? ''); ?>">
                </div>

                <div class="col-md-6">
                  <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                  <input type="email" class="form-control" id="email" name="email" required
                         value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>

                <div class="col-md-6">
                  <label for="phone" class="form-label">Phone Number</label>
                  <input type="tel" class="form-control" id="phone" name="phone"
                         value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                </div>

              

           

                <div class="text-center">
                  <button type="submit" class="btn btn-primary">Add Lecturer</button>
                  <a href="lecturers.php" class="btn btn-secondary">Cancel</a>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
    $(document).ready(function() {
      // Image preview
      $('#profile_image').change(function(e) {
        const file = e.target.files[0];
        if (file) {
          const reader = new FileReader();
          reader.onload = function(e) {
            $('#imagePreview').attr('src', e.target.result);
          }
          reader.readAsDataURL(file);
        }
      });
    });
  </script>
</body>
</html>
