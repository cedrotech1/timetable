<?php
session_start();
include("connection.php");

if (!isset($_SESSION['id']) || !isset($_GET['id'])) {
    header("Location: lecturers.php");
    exit();
}

$lecturer_id = intval($_GET['id']);
$error = '';
$success = '';


// Fetch lecturer data
$stmt = $connection->prepare("SELECT * FROM users WHERE id = ? AND role = 'lecturer'");
$stmt->bind_param("i", $lecturer_id);
$stmt->execute();
$lecturer = $stmt->get_result()->fetch_assoc();

if (!$lecturer) {
    header("Location: lecturers.php");
    exit();
}

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
        // Check if email already exists for another user
        $stmt = $connection->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $lecturer_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = 'Email already exists';
        } else {
            // Handle file upload if new image is provided
            $imagePath = $lecturer['image'];
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
                        // Delete old image if it's not the default one
                        if ($imagePath !== 'assets/img/default-avatar.png' && file_exists($imagePath)) {
                            unlink($imagePath);
                        }
                        $imagePath = $targetPath;
                    }
                }
            }
            
            // Update lecturer
            $stmt = $connection->prepare("UPDATE users SET names = ?, email = ?, phone = ?, image = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $names, $email, $phone, $imagePath, $lecturer_id);
            
            if ($stmt->execute()) {
                $success = 'Lecturer updated successfully';
                // Refresh lecturer data
                $stmt = $connection->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->bind_param("i", $lecturer_id);
                $stmt->execute();
                $lecturer = $stmt->get_result()->fetch_assoc();
            } else {
                $error = 'Failed to update lecturer: ' . $connection->error;
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
  <title>Edit Lecturer - UR-TIMETABLE</title>
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
      <h1>Edit Lecturer</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <li class="breadcrumb-item"><a href="lecturers.php">Lecturers</a></li>
          <li class="breadcrumb-item active">Edit</li>
        </ol>
      </nav>
    </div>

    <div class="container">
      <div class="row justify-content-center">
        <div class="col-lg-8">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Edit Lecturer Information</h5>
              
              <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
              <?php endif; ?>
              
              <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
              <?php endif; ?>

              <form action="edit_lecturer.php?id=<?php echo $lecturer_id; ?>" method="POST" enctype="multipart/form-data" class="row g-3">
                <div class="col-12 text-center mb-4">
                  <label for="profile_image" class="d-block">
                    <img id="imagePreview" src="<?php echo htmlspecialchars($lecturer['image']); ?>" class="profile-image-preview" alt="Profile Image">
                    <input type="file" id="profile_image" name="profile_image" class="profile-image-upload" accept="image/*">
                  </label>
                  <div class="text-muted">Click to change profile picture</div>
                </div>

                <div class="col-md-6">
                  <label for="names" class="form-label">Full Name <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="names" name="names" required 
                         value="<?php echo htmlspecialchars($lecturer['names']); ?>">
                </div>

                <div class="col-md-6">
                  <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                  <input type="email" class="form-control" id="email" name="email" required
                         value="<?php echo htmlspecialchars($lecturer['email']); ?>">
                </div>

                <div class="col-md-6">
                  <label for="phone" class="form-label">Phone Number</label>
                  <input type="tel" class="form-control" id="phone" name="phone"
                         value="<?php echo htmlspecialchars($lecturer['phone'] ?? ''); ?>">
                </div>

                <div class="col-12">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="status" name="status" value="1" 
                           <?php echo ($lecturer['active'] ? 'checked' : ''); ?>>
                    <label class="form-check-label" for="status">
                      Active Account
                    </label>
                  </div>
                </div>

                <div class="text-center">
                  <button type="submit" class="btn btn-primary">Update Lecturer</button>
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
