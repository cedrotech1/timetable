<?php
session_start();
include("connection.php");

if (!isset($_SESSION['id'])) {
    header("Location: index.php");
    exit();
}

// Get current user's campus
$current_user_id = $_SESSION['id'];
$stmt = $connection->prepare("SELECT role, campus FROM users WHERE id = ?");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();
$current_user = $result->fetch_assoc();
$campus_id = $current_user['campus'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta content="width=device-width, initial-scale=1.0" name="viewport" />
  <title>UR-TIMETABLE - Lecturers</title>
  <link href="assets/img/icon1.png" rel="icon" />
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
      <h1>Lecturers</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <li class="breadcrumb-item active">Lecturers</li>
        </ol>
      </nav>
    </div>

    <div class="container mt-4">
      <div class="card p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5>Lecturers List</h5>
          <a href="add_lecturer.php" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Add Lecturer
          </a>
        </div>

        <div class="table-responsive">
          <table id="lecturersTable" class="table table-striped table-hover" style="width:100%">
            <thead class="table-primary">
              <tr>
                <th>#</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <!-- <th>Campus</th> -->
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $query = "SELECT u.*, c.name AS campus_name 
                        FROM users u 
                        LEFT JOIN campus c ON u.campus = c.id 
                        WHERE u.role = 'lecturer'
                        ORDER BY u.names ASC";
              $result = $connection->query($query);
              $i = 1;
              
              while ($row = $result->fetch_assoc()) {
                $statusClass = $row['active'] ? 'bg-success' : 'bg-secondary';
                $statusText = $row['active'] ? 'Active' : 'Inactive';
                
                echo "<tr>";
                echo "<td>" . $i++ . "</td>";
                echo "<td>" . htmlspecialchars($row['names']) . "</td>";
                echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                echo "<td>" . htmlspecialchars($row['phone'] ?? 'N/A') . "</td>";
                // echo "<td>" . htmlspecialchars($row['campus_name'] ?? 'N/A') . "</td>";
                echo "<td><span class='badge " . $statusClass . "'>" . $statusText . "</span></td>";
                echo "<td>";
                echo "<div class='btn-group'>";
                echo "<a href='edit_lecturer.php?id=" . $row['id'] . "' class='btn btn-sm btn-outline-primary' title='Edit'><i class='bi bi-pencil'></i></a>";
                echo "<button class='btn btn-sm btn-outline-danger delete-lecturer' data-id='" . $row['id'] . "' title='Delete'><i class='bi bi-trash'></i></button>";
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

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <script>
    (function($) {
      $(document).ready(function() {
        const table = $('#lecturersTable').DataTable({
          responsive: true,
          pageLength: 10,
          lengthMenu: [5, 10, 25, 50, 100],
          order: [[1, 'asc']],
          dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
               "<'row'<'col-sm-12'tr>>" +
               "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>"
        });

        // Delete lecturer
        $(document).on('click', '.delete-lecturer', function() {
          const lecturerId = $(this).data('id');
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
                url: 'delete_lecturer.php',
                type: 'POST',
                data: { id: lecturerId },
                dataType: 'json',
                success: function(response) {
                  if (response.success) {
                    table.row(row).remove().draw(false);
                    Swal.fire('Deleted!', 'Lecturer has been deleted.', 'success');
                  } else {
                    Swal.fire('Error!', response.message || 'Failed to delete lecturer.', 'error');
                  }
                },
                error: function() {
                  Swal.fire('Error!', 'An error occurred while processing your request.', 'error');
                }
              });
            }
          });
        });
      });
    })(jQuery);
  </script>
</body>
</html>
