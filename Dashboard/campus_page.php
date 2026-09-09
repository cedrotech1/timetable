<?php
session_start();
include('connection.php');

$campus_id = $_GET['id'] ?? 1;

// Fetch campus details
$query = "SELECT * FROM campus WHERE id = ?";
$stmt = $connection->prepare($query);
$stmt->bind_param("i", $campus_id);
$stmt->execute();
$result = $stmt->get_result();
$campus = $result->fetch_assoc();
$campus_name = $campus['name'] ?? "Unknown Campus";

// Handle add new college
if (isset($_POST['add_college'])) {
    $college_name = trim($_POST['college_name']);
    if ($college_name !== "") {
        $insert = $connection->prepare("INSERT INTO college (name, campus_id) VALUES (?, ?)");
        $insert->bind_param("si", $college_name, $campus_id);
        $insert->execute();
    }
    header("Location: campus_page.php?id=$campus_id");  
    exit;
}

// Handle edit college
if (isset($_POST['edit_college'])) {
    $college_id = $_POST['college_id'];
    $college_name = trim($_POST['college_name']);
    if ($college_name !== "") {
        $update = $connection->prepare("UPDATE college SET name=? WHERE id=? AND campus_id=?");
        $update->bind_param("sii", $college_name, $college_id, $campus_id);
        $update->execute();
    }
    header("Location: campus_details.php?id=$campus_id");
    exit;
}

// Fetch colleges
$colleges = [];
$query = "SELECT * FROM college WHERE campus_id = ?";
$stmt = $connection->prepare($query);
$stmt->bind_param("i", $campus_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $colleges[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($campus_name) ?> - Campus Details</title>
    <meta content="" name="description">
    <meta content="" name="keywords">
    <link href="assets/img/icon1.png" rel="icon">
    <link href="assets/img/icon1.png" rel="apple-touch-icon">
    <link href="https://fonts.gstatic.com" rel="preconnect">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card {
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        h4{
            background-color:rgb(23, 48, 73);
            padding: 10px;
            border-radius: 5px;
            color:#fff;
            font-size: 17px;
        }
        .btn-primary{
            background-color:rgb(23, 48, 73);
        }
    </style>
</head>
<body>
    <?php include('./includes/header.php'); ?>
    <?php include('./includes/menu.php'); ?>

    <main id="main" class="main">
        <div class="row mt-4">
            <div class="col-md-7 card">
                <h4><?= htmlspecialchars($campus_name) ?> - Colleges</h2>
                <table class="table table-bordered mt-3">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>College Name</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($colleges) > 0): ?>
                    <?php foreach ($colleges as $index => $college): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($college['name']) ?></td>
                            <td>
                                <!-- Edit Form (inline) -->
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="college_id" value="<?= $college['id'] ?>">
                                    <input type="text" name="college_name" value="<?= htmlspecialchars($college['name']) ?>" class="form-control d-inline" style="width:200px;">
                                    <button type="submit" name="edit_college" class="btn btn-sm btn-primary">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3" class="text-center">No colleges found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

            </div>
            <div class="mx-3 col-md-4 card">
                <a href="campus.php" class="btn btn-primary float-end">Back to Campus</a>
                <h4 class="mt-4">Add New College</h4>
        <form method="post" class="d-flex">
            <input type="text" name="college_name" placeholder="Enter college name" class="form-control me-2" required>
            <button type="submit" name="add_college" class="btn btn-success">Add</button>
        </form>
            </div>
        </div>

        <!-- List Colleges -->
  

        <!-- Add College -->
        

    </main>
</body>
</html>
