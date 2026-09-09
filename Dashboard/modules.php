<?php
session_start();
include("connection.php");

// Get current user's campus
$current_user_id = $_SESSION['id'];
$stmt = $connection->prepare("SELECT role, campus, college, school FROM users WHERE id = ?");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();
$current_user = $result->fetch_assoc();
$campus_id = $current_user['campus'];

// Fetch all programs for filter
$programs = [];
$programsQuery = "SELECT id, name, code FROM program ORDER BY name ASC";
$stmt = $connection->prepare($programsQuery);
// $stmt->bind_param("", $campus_id);
$stmt->execute();
$programsResult = $stmt->get_result();
while ($row = $programsResult->fetch_assoc()) {
    $programs[$row['id']] = $row['name'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta content="width=device-width, initial-scale=1.0" name="viewport" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests" />
  <title>UR-TIMETABLE - Modules</title>
  <link href="assets/img/icon1.png" rel="icon" />
  <link href="assets/img/icon1.png" rel="apple-touch-icon" />
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet" />
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet" />
  <link href="assets/css/style.css" rel="stylesheet" />
  <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet" />
</head>
<body>
  <?php include("./includes/header.php"); ?>
  <?php include("./includes/menu.php"); ?>

  <main id="main" class="main">
    <div class="pagetitle">
      <h1>Modules</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <li class="breadcrumb-item active">Modules</li>
        </ol>
      </nav>
    </div>

    <div class="mt-4">
      <div class="card p-3">
        <div class="row mb-3">
          <div class="col-md-4">
            <label for="programFilter" class="form-label">Filter by Program</label>
            <select id="programFilter" class="form-select">
              <option value="">All Programs</option>
              <?php foreach ($programs as $id => $name): ?>
                <option value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label for="yearFilter" class="form-label">Filter by Year</label>
            <select id="yearFilter" class="form-select">
              <option value="">All Years</option>
              <option value="1">Year 1</option>
              <option value="2">Year 2</option>
              <option value="3">Year 3</option>
              <option value="4">Year 4</option>
            </select>
          </div>
          <div class="col-md-3">
            <label for="semesterFilter" class="form-label">Filter by Semester</label>
            <select id="semesterFilter" class="form-select">
              <option value="">All Semesters</option>
              <option value="1">Semester 1</option>
              <option value="2">Semester 2</option>
            </select>
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <button id="resetFilters" class="btn btn-outline-secondary w-100">Reset Filters</button>
          </div>
        </div>

        <!-- Add Module Button -->
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5>Modules List</h5>
          <div>
            <!-- <a href="add_module.php" class="btn btn-primary me-2">
              <i class="bi bi-plus-lg"></i> Add Module
            </a>
            <a href="upload_data.php" class="btn btn-success">
              <i class="bi bi-upload"></i> Upload Excel
            </a> -->
          </div>
        </div>

        <div class="table-responsive">
          <table id="modulesTable" class="table table-striped table-hover" style="width:100%">
            <thead class="table-primary">
              <tr>
                <th>#</th>
                <th>Module Name</th>
                <th>Code</th>
                <th>Credits</th>
                <th>Year</th>
                <th>Semester</th>
                <th>Program</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $query = "SELECT m.*, p.name AS program_name, p.code AS program_code 
                        FROM module m 
                        LEFT JOIN program p ON m.program_id = p.id 
                        ORDER BY m.name ASC";
              $stmt = $connection->prepare($query);
            
              $stmt->execute();
              $result = $stmt->get_result();
              $i = 1;
              while ($row = $result->fetch_assoc()) {
                $programInfo = '';
                if (!empty($row['program_name']) || !empty($row['program_code'])) {
                    $programName = $row['program_name'] ?? 'N/A';
                    $programCode = $row['program_code'] ? '(' . $row['program_code'] . ')' : '';
                    $programInfo = trim("$programName $programCode");
                } else {
                    $programInfo = 'N/A';
                }
                
                echo "<tr>";
                echo "<td>" . $i++ . "</td>";
                echo "<td>" . (!empty($row['name']) ? htmlspecialchars($row['name']) : 'N/A') . "</td>";
                echo "<td>" . (!empty($row['code']) ? htmlspecialchars($row['code']) : 'N/A') . "</td>";
                echo "<td>" . (isset($row['credits']) ? htmlspecialchars($row['credits']) : 'N/A') . "</td>";
                echo "<td>" . (isset($row['year']) ? htmlspecialchars($row['year']) : 'N/A') . "</td>";
                echo "<td>" . (isset($row['semester']) ? htmlspecialchars($row['semester']) : 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($programInfo) . "</td>";
                echo "<td>";
                echo "<div class='btn-group'>";
                echo "<a href='edit_module.php?id=" . $row['id'] . "' class='btn btn-sm btn-outline-primary' title='Edit'><i class='bi bi-pencil'></i></a>";
                echo "<button class='btn btn-sm btn-outline-danger delete-module' data-id='" . $row['id'] . "' title='Delete'><i class='bi bi-trash'></i></button>";
                echo "</div>";
                echo "</td>";
                echo "</tr>";
              }
              ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>

  <!-- Include JavaScript Libraries -->
  <!-- Load jQuery first -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <!-- Then load Bootstrap bundle (includes Popper) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
  <!-- Then DataTables -->
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <script>
    // Use jQuery in noConflict mode if needed
    (function($) {
      $(document).ready(function() {
        // Rest of your JavaScript code here
        const table = $('#modulesTable').DataTable({
          responsive: true,
          pageLength: 10,
          lengthMenu: [5, 10, 25, 50, 100],
          order: [[1, 'asc']],
          dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
               "<'row'<'col-sm-12'tr>>" +
               "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
          language: {
            search: "_INPUT_",
            searchPlaceholder: "Search...",
            lengthMenu: "Show _MENU_ entries per page",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "No entries found",
            infoFiltered: "(filtered from _MAX_ total entries)",
            paginate: {
              first: "First",
              last: "Last",
              next: "Next",
              previous: "Previous"
            }
          },
          columnDefs: [
            { orderable: false, targets: [0, 7] }, // Disable sorting on # and Actions columns
            { className: "text-center", targets: [3, 4, 5, 7] } // Center align these columns
          ]
        });

        // Rest of your event handlers...
        $('#programFilter').on('change', function() {
          table.column(6).search(this.value).draw();
        });

        $('#yearFilter').on('change', function() {
          table.column(4).search(this.value).draw();
        });

        $('#semesterFilter').on('change', function() {
          table.column(5).search(this.value).draw();
        });

        $('#resetFilters').on('click', function() {
          $('#programFilter, #yearFilter, #semesterFilter').val('').trigger('change');
          table.search('').columns().search('').draw();
        });

        $(document).on('click', '.delete-module', function() {
          const moduleId = $(this).data('id');
          const row = $(this).closest('tr');
          
          Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!'
          }).then((result) => {
            if (result.isConfirmed) {
              $.ajax({
                url: 'delete_module.php',
                type: 'POST',
                data: { id: moduleId },
                dataType: 'json',
                success: function(response) {
                  if (response.success) {
                    table.row(row).remove().draw(false);
                    Swal.fire(
                      'Deleted!',
                      response.message || 'Module has been deleted.',
                      'success'
                    );
                  } else {
                    Swal.fire(
                      'Error!',
                      response.message || 'Failed to delete module.',
                      'error'
                    );
                  }
                },
                error: function() {
                  Swal.fire(
                    'Error!',
                    'An error occurred while processing your request.',
                    'error'
                  );
                }
              });
            }
          });
        });

        table.on('order.dt search.dt', function() {
          table.column(0, {search:'applied', order:'applied'}).nodes().each(function(cell, i) {
            cell.innerHTML = i + 1;
          });
        }).draw();
      });
    })(jQuery);
  </script>
</body>
</html>
