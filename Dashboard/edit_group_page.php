<?php
session_start();
require_once 'connection.php';

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit();
}

// Get group and intake IDs from URL
$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
$intake_id = isset($_GET['intake_id']) ? (int)$_GET['intake_id'] : 0;
$program_id = isset($_GET['program_id']) ? (int)$_GET['program_id'] : 0;
$is_edit = $group_id > 0;

// Initialize variables
$name = '';
$size = 0;
$error = '';
$success = '';

// If form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = trim($_POST['name'] ?? '');
    $size = isset($_POST['size']) ? (int)$_POST['size'] : 0;
    
    // Validate input
    if (empty($name)) {
        $error = 'Group name is required';
    } elseif ($size <= 0) {
        $error = 'Please enter a valid group size';
    } else {
        // Start transaction
        $connection->begin_transaction();
        
        try {
            if ($is_edit) {
                // Update existing group
                $stmt = $connection->prepare("
                    UPDATE student_group 
                    SET name = ?, size = ?
                    WHERE id = ? AND intake_id = ?
                
                
                ");
                $stmt->bind_param('siii', $name, $size, $group_id, $intake_id);
            } else {
                // Add new group
                // Check for duplicate name
                $check = $connection->prepare("
                    SELECT id FROM student_group 
                    WHERE name = ? AND intake_id = ?
                
                
                ");
                $check->bind_param('si', $name, $intake_id);
                $check->execute();
                
                if ($check->get_result()->num_rows > 0) {
                    throw new Exception('A group with this name already exists in the selected intake');
                }
                
                $stmt = $connection->prepare("
                    INSERT INTO student_group (name, size, intake_id)
                    VALUES (?, ?, ?)
                
                
                ");
                $stmt->bind_param('sii', $name, $size, $intake_id);
            }
            
            if (!$stmt->execute()) {
                throw new Exception($is_edit ? 'Failed to update group' : 'Failed to add group');
            }
            
            // Update intake size
            $updateIntake = $connection->prepare("
                UPDATE intake 
                SET size = (
                    SELECT COALESCE(SUM(size), 0)
                    FROM student_group
                    WHERE intake_id = ?
                )
                WHERE id = ?
            
            
            ");
            $updateIntake->bind_param('ii', $intake_id, $intake_id);
            
            if (!$updateIntake->execute()) {
                throw new Exception('Failed to update intake size');
            }
            
            $connection->commit();
            
            // Redirect back to program management page with success message
            $_SESSION['success'] = $is_edit ? 'Group updated successfully' : 'Group added successfully';
            header("Location: program_management.php?program_id=$program_id");
            exit();
            
        } catch (Exception $e) {
            $connection->rollback();
            $error = $e->getMessage();
        }
    }
} elseif ($is_edit) {
    // Load existing group data
    $stmt = $connection->prepare("
        SELECT name, size 
        FROM student_group 
        WHERE id = ? AND intake_id = ?
    
    
    ");
    $stmt->bind_param('ii', $group_id, $intake_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($group = $result->fetch_assoc()) {
        $name = $group['name'];
        $size = $group['size'];
    } else {
        $error = 'Group not found';
    }
}

// Get program name for the breadcrumb
$program_name = '';
if ($program_id > 0) {
    $stmt = $connection->prepare("SELECT name FROM program WHERE id = ?");
    $stmt->bind_param('i', $program_id);
    $stmt->execute();
    $program_name = $stmt->get_result()->fetch_assoc()['name'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit ? 'Edit' : 'Add' ?> Group - School Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="assets/img/icon1.png" rel="icon" />
    <meta content="" name="description">
    <meta content="" name="keywords">
    <link href="assets/img/icon1.png" rel="apple-touch-icon">
    <link href="https://fonts.gstatic.com" rel="preconnect">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">

   <style>
        body {
            background-color: #f8f9fa;
        }
        .form-container {
            max-width: 600px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/menu.php'; ?>
    <main id="main" class="main">
    
    <div class="container">
        <nav aria-label="breadcrumb" class="my-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="program_management.php?program_id=<?= $program_id ?>"><?= htmlspecialchars($program_name) ?></a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= $is_edit ? 'Edit' : 'Add' ?> Group</li>
            </ol>
        </nav>
        
        <div class="form-container">
            <h2 class="mb-4"><?= $is_edit ? 'Edit' : 'Add New' ?> Group</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="post" action="">
                <input type="hidden" name="program_id" value="<?= $program_id ?>">
                
                <div class="mb-3">
                    <label for="name" class="form-label">Group Name</label>
                    <input type="text" class="form-control" id="name" name="name" 
                           value="<?= htmlspecialchars($name) ?>" required>
                </div>
                
                <div class="mb-4">
                    <label for="size" class="form-label">Group Size</label>
                    <input type="number" class="form-control" id="size" name="size" 
                           value="<?= $size > 0 ? $size : '' ?>" min="1" required>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="program_management.php?program_id=<?= $program_id ?>" class="btn btn-secondary me-md-2">
                        <i class="bi bi-arrow-left"></i> Back
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> <?= $is_edit ? 'Update' : 'Save' ?> Group
                    </button>
                </div>
            </form>
        </div>
    </div>
    </main>    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
