<?php
session_start();
include('connection.php');

// Get timetable ID from URL
$timetableId = isset($_GET['timetable_id']) ? intval($_GET['timetable_id']) : 0;

// Get timetable details
$timetable = [];
$programId = 0;

if ($timetableId > 0) {
    $stmt = $connection->prepare("
        SELECT t.*, 
               p.name as program_name, 
               p.id as program_id,
               m.code as module_code,
               m.name as module_name,
               m.id as module_id
        FROM timetable t 
        LEFT JOIN module m ON t.module_id = m.id
        LEFT JOIN program p ON m.program_id = p.id 
        WHERE t.id = ?
    ");
    $stmt->bind_param("i", $timetableId);
    $stmt->execute();
    $result = $stmt->get_result();
    $timetable = $result->fetch_assoc();
    $programId = $timetable['program_id'] ?? 0;
}

// Handle module update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['module_id'])) {
    $moduleId = intval($_POST['module_id']);
    $response = ['success' => false, 'message' => ''];
    
    try {
        // Start transaction
        $connection->begin_transaction();
        
        // First, verify the timetable exists
        $checkStmt = $connection->prepare("SELECT * FROM timetable WHERE id = ?");
        $checkStmt->bind_param("i", $timetableId);
        $checkStmt->execute();
        $timetableData = $checkStmt->get_result()->fetch_assoc();
        
        if (!$timetableData) {
            throw new Exception("Timetable entry not found with ID: $timetableId");
        }
        
        // Update module_id in timetable using all fields to ensure update works
        $updateStmt = $connection->prepare("
            UPDATE timetable SET 
                module_id = ?,
                leader_lecturer_id = ?,
                facility_id = ?,
                semester = ?,
                academic_year_id = ?,
                status = ?,
                approvedby = ?,
                createdby = ?
            WHERE id = ?
        ");
        
        $updateStmt->bind_param(
            "iiiiisssi",
            $moduleId,
            $timetableData['leader_lecturer_id'],
            $timetableData['facility_id'],
            $timetableData['semester'],
            $timetableData['academic_year_id'],
            $timetableData['status'],
            $timetableData['approvedby'],
            $timetableData['createdby'],
            $timetableId
        );
        
        $success = $updateStmt->execute();
        $affectedRows = $connection->affected_rows;
        
        if (!$success || $affectedRows === 0) {
            // Try alternative approach with direct query
            $directUpdate = $connection->query("UPDATE timetable SET module_id = $moduleId WHERE id = $timetableId");
            $affectedRows = $connection->affected_rows;
            
            if ($affectedRows === 0) {
                throw new Exception("Failed to update timetable record. No rows affected.");
            }
        }
        
        $connection->commit();
        
        // Get updated module details
        $moduleStmt = $connection->prepare("
            SELECT m.code as module_code, m.name as module_name 
            FROM module m 
            WHERE m.id = ?
        ");
        $moduleStmt->bind_param("i", $moduleId);
        $moduleStmt->execute();
        $moduleResult = $moduleStmt->get_result()->fetch_assoc();
        
        $response = [
            'success' => true,
            'message' => 'Module updated successfully!',
            'module_id' => $moduleId,
            'module_code' => $moduleResult['module_code'] ?? '',
            'module_name' => $moduleResult['module_name'] ?? ''
        ];
        
    } catch (Exception $e) {
        $connection->rollback();
        $response['message'] = 'Error: ' . $e->getMessage();
        error_log("Module update failed: " . $e->getMessage());
    }
    
    // Return JSON response for AJAX requests
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta content="" name="description">
  <meta content="" name="keywords">

  <!-- Favicons -->
  <link href="assets/img/icon1.png" rel="icon">
  <link href="assets/img/icon1.png" rel="apple-touch-icon">

  <!-- Google Fonts -->
  <link href="https://fonts.gstatic.com" rel="preconnect">
  <link
    href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i"
    rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
  <link href="assets/vendor/quill/quill.snow.css" rel="stylesheet">
  <link href="assets/vendor/quill/quill.bubble.css" rel="stylesheet">
  <link href="assets/vendor/remixicon/remixicon.css" rel="stylesheet">
  <link href="assets/vendor/simple-datatables/style.css" rel="stylesheet">

  <!-- Template Main CSS File -->
  <link href="assets/css/style.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">

  <style>
    body { background-color: #f8f9fa; }
    .card { margin-bottom: 20px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
    .card-header { font-weight: bold; background: rgb(3,31,80); color: white; }
    .module-selected { background-color: #e8f4ff !important; }
  </style>
</head>

<body>
  <!-- ======= Header ======= -->
  <?php include('includes/header.php'); ?>
  <?php include('includes/menu.php'); ?>

  <main id="main" class="main">
    <div class="pagetitle">
      <h1>Change Module for Timetable</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <li class="breadcrumb-item">Timetable</li>
          <li class="breadcrumb-item active">Change Module</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section">
      <div class="row">
        <div class="col-lg-12">
          <div class="card">
            <div class="card-body">
              <?php if (!empty($timetable)): ?>
                <h5 class="card-title current-module-display">Current Module: <?= htmlspecialchars($timetable['module_code'] ?? 'N/A') ?> - <?= htmlspecialchars($timetable['module_name'] ?? 'N/A') ?></h5>
                <p>Program: <?= htmlspecialchars($timetable['program_name'] ?? 'N/A') ?></p>
                
                <div class="table-responsive">
                  <table id="modulesTable" class="table table-striped table-hover">
                    <thead>
                      <tr>
                        <th>Code</th>
                        <th>Module Name</th>
                        <th>Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php
                      // Get all modules for the current program
                      if ($programId > 0) {
                        $stmt = $connection->prepare("
                          SELECT m.* FROM module m 
                          WHERE m.program_id = ? 
                          ORDER BY m.code
                        ");
                        $stmt->bind_param("i", $programId);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        while ($module = $result->fetch_assoc()) {
                          $isCurrent = ($module['id'] == ($timetable['module_id'] ?? 0));
                          echo "<tr>";
                          echo "<td>" . htmlspecialchars($module['code']) . "</td>";
                          echo "<td>" . htmlspecialchars($module['name']) . "</td>";
                          if ($isCurrent) {
                            echo '<td><span class="badge bg-secondary">Current Module</span></td>';
                          } else {
                            echo '<td><button class="btn btn-sm btn-primary select-module" data-module-id="' . $module['id'] . '" data-module-name="' . htmlspecialchars($module['code'] . ' - ' . $module['name']) . '">Select</button></td>';
                          }
                          echo "</tr>";
                        }
                      }
                      ?>
                    </tbody>
                  </table>
                </div>
                
                <!-- Confirmation Modal -->
                <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
                  <div class="modal-dialog">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title" id="confirmModalLabel">Confirm Module Change</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body">
                        Are you sure you want to change the module to <span id="moduleName"></span>?
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="confirmChange">Change Module</button>
                      </div>
                    </div>
                  </div>
                </div>
                
              <?php else: ?>
                <div class="alert alert-danger">Timetable entry not found or invalid ID provided.</div>
                <a href="timetable_set.php" class="btn btn-secondary">Back to Timetable</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </section>
  </main>

  <!-- jQuery and Bootstrap JS -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  
  <script>
  // Initialize DataTable
  $(document).ready(function() {
      $('#modulesTable').DataTable({
          responsive: true,
          initComplete: function() {
              $('.dataTables_filter input').addClass('form-control');
              $('.dataTables_length select').addClass('form-select');
          }
      });

      // Module selection
      let selectedModuleId = null;
      
      $('.select-module').on('click', function() {
          selectedModuleId = $(this).data('module-id');
          $('#moduleName').text($(this).data('module-name'));
          
          // Initialize modal if not already done
          const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
          confirmModal.show();
      });

      // Confirm module change
      $('#confirmChange').on('click', function() {
          if (!selectedModuleId) return;
          
          // Show loading state
          const $btn = $(this);
          const originalText = $btn.html();
          $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Updating...');
          
          // Send AJAX request
          $.ajax({
              url: window.location.href,
              type: 'POST',
              data: {
                  module_id: selectedModuleId,
                  timetable_id: <?= $timetableId ?>
              },
              beforeSend: function() {
                  console.log('Sending AJAX request to update module:', {
                      module_id: selectedModuleId,
                      timetable_id: <?= $timetableId ?>
                  });
              },
              dataType: 'json',
              success: function(response) {
                  console.log('AJAX Success:', response);
                  
                  if (response.success) {
                      // Show success toast
                      const successToastEl = document.getElementById('successToast');
                      const successToastMessage = document.getElementById('successToastMessage');
                      if (successToastEl && successToastMessage) {
                          successToastMessage.textContent = response.message;
                          const toast = new bootstrap.Toast(successToastEl);
                          toast.show();
                      }
                      
                      // Update the current module display
                      const currentModuleElement = document.querySelector('.current-module-display');
                      if (currentModuleElement && response.module_code) {
                          currentModuleElement.textContent = 'Current Module: ' + response.module_code + ' - ' + (response.module_name || '');
                      }
                      
                      // Update table buttons
                      $('.select-module').each(function() {
                          const btn = $(this);
                          const moduleId = btn.data('module-id');
                          if (moduleId == selectedModuleId) {
                              btn.replaceWith('<span class="badge bg-secondary">Current Module</span>');
                          }
                      });
                      
                      // Redirect after delay if needed
                      setTimeout(function() {
                          if (response.redirect) {
                              window.location.href = response.redirect;
                          }
                      }, 1500);
                      
                  } else {
                      // Show error message
                      const errorToastEl = document.getElementById('errorToast');
                      const errorToastMessage = document.getElementById('errorToastMessage');
                      if (errorToastEl && errorToastMessage) {
                          errorToastMessage.textContent = response.message || 'An error occurred';
                          const toast = new bootstrap.Toast(errorToastEl);
                          toast.show();
                      }
                  }
              },
              error: function(xhr, status, error) {
                  console.error('AJAX Error:', {
                      status: status,
                      error: error,
                      response: xhr.responseText
                  });
                  
                  // Show error message
                  const errorToastEl = document.getElementById('errorToast');
                  const errorToastMessage = document.getElementById('errorToastMessage');
                  if (errorToastEl && errorToastMessage) {
                      let errorMsg = 'Error: ' + (error || 'Unknown error occurred');
                      try {
                          const response = JSON.parse(xhr.responseText);
                          errorMsg = response.message || errorMsg;
                      } catch (e) {
                          if (xhr.responseText) {
                              errorMsg = 'Server error: ' + xhr.responseText.substring(0, 100);
                          }
                      }
                      
                      errorToastMessage.textContent = errorMsg;
                      const toast = new bootstrap.Toast(errorToastEl);
                      toast.show();
                  }
              },
              complete: function() {
                  $btn.prop('disabled', false).html(originalText);
                  // Hide the modal
                  const modal = bootstrap.Modal.getInstance(document.getElementById('confirmModal'));
                  if (modal) modal.hide();
              }
          });
      });
  });
  </script>

  <!-- Success Toast -->
  <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
      <div id="successToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
          <div class="toast-header bg-success text-white">
              <strong class="me-auto">Success</strong>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
          </div>
          <div class="toast-body" id="successToastMessage">
              Module updated successfully!
          </div>
      </div>
  </div>

  <!-- Error Toast -->
  <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
      <div id="errorToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
          <div class="toast-header bg-danger text-white">
              <strong class="me-auto">Error</strong>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
          </div>
          <div class="toast-body" id="errorToastMessage">
              An error occurred while updating the module.
          </div>
      </div>
  </div>

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

  <a href="#" class="back-to-top d-flex align-items-center justify-content-center">
      <i class="bi bi-arrow-up-short"></i>
  </a>

</body>
</html>