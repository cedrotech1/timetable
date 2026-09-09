<?php
session_start();
require_once 'connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tables = [
        'timetable_sessions',
        'timetable_lecturers',
        'timetable_groups',
        'timetable'
    ];

    $connection->begin_transaction();

    try {
        foreach ($tables as $table) {
            if (!$connection->query("TRUNCATE TABLE {$table}")) {
                throw new Exception($connection->error ?: "Failed to truncate {$table}");
            }
        }

        $connection->commit();
        $message = ['type' => 'success', 'text' => 'All timetable data has been reset.'];
    } catch (Exception $e) {
        $connection->rollback();
        $message = ['type' => 'danger', 'text' => 'Reset failed: ' . $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Timetables</title>
    <link href="assets/img/icon1.png" rel="icon">
    <link href="assets/img/icon1.png" rel="apple-touch-icon">
    
    <!-- Bootstrap & Styles -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
    <?php include './includes/header.php'; ?>
    <?php include './includes/menu.php'; ?>
    <!-- main content -->
    <main id="main" class="main">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-danger text-white">
                    <h4 class="mb-0">Reset All Timetables</h4>
                </div>
                <div class="card-body">
                    <p class="text-muted">This action will permanently delete every timetable and all related associations (groups, lecturers, sessions). Proceed with caution.</p>

                    <?php if (!empty($message)): ?>
                        <div class="alert alert-<?= htmlspecialchars($message['type']) ?>">
                            <?= htmlspecialchars($message['text']) ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" onsubmit="return confirm('This will erase all timetable data. Are you sure?');">
                        <button type="submit" class="btn btn-danger w-100">Reset Timetables</button>
                    </form>
                </div>
                <div class="card-footer text-muted small">
                    Action recorded at <?= date('Y-m-d H:i:s') ?>
                </div>
            </div>
        </div>
    </div>
</div>
</main>
    <?php include './includes/footer.php'; ?>
    <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i
      class="bi bi-arrow-up-short"></i></a>

  <!-- Vendor JS Files -->
  <script src="assets/vendor/apexcharts/apexcharts.min.js"></script>
  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/vendor/chart.js/chart.umd.js"></script>
  <script src="assets/vendor/echarts/echarts.min.js"></script>
  <script src="assets/vendor/quill/quill.min.js"></script>
  <script src="assets/vendor/simple-datatables/simple-datatables.js"></script>
  <script src="assets/vendor/tinymce/tinymce.min.js"></script>
  <script src="assets/vendor/php-email-form/validate.js"></script>

  <!-- Template Main JS File -->
  <script src="assets/js/main.js"></script>
</body>
</html>
