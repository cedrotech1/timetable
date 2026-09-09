
<?php
session_start();
include("connection.php");

// Get all facilities without pagination
$sql = "SELECT * FROM facility ORDER BY name ASC";
$result = $connection->query($sql);

$facilities = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $facilities[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta content="width=device-width, initial-scale=1.0" name="viewport" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <title>UR-TIMETABLE - Facilities</title>
  <link href="assets/img/icon1.png" rel="icon" />
  <link href="assets/img/icon1.png" rel="apple-touch-icon" />

  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet" />
<link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet" />
<link href="assets/css/style.css" rel="stylesheet" />
</head>
<body>
<?php
 include("./includes/header.php");
include("./includes/menu.php");
?>

<main id="main" class="main">
  <div class="pagetitle">
    <h1>Facilities List</h1>
  </div>
  
  <section class="section">
    <div class="row">
      <div class="col-lg-12">
        <div class="card">
          <div class="card-body">
        </button>
        <div id="bulkDeleteAlert" class="alert alert-warning py-1 px-2 mb-0 d-none"></div>
      </form>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <div class="table-responsive w-100">
              <table id="facilitiesTable" class="table table-striped table-hover table-bordered mb-0 w-100">
                <thead class="table-dark">
                  <tr>
                    <th class="text-center">#</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th class="text-center">Capacity</th>
                    <th>Building</th>
                    <th>Building Code</th>
                    <th class="text-center">Actions</th>
                  </tr>
                </thead>
                <tbody class="table-group-divider">
                  <?php foreach ($facilities as $facility): ?>
                    <tr>
                      <td class="text-center fw-bold">
                        <span class="badge bg-secondary"><?= htmlspecialchars($facility['id']) ?></span>
                      </td>
                      <td class="align-middle">
                        <div class="d-flex align-items-center">
                          <i class="bi bi-building me-2 text-primary"></i>
                          <span><?= htmlspecialchars($facility['name']) ?></span>
                        </div>
                      </td>
                      <td class="align-middle">
                        <?php 
                        $type = htmlspecialchars($facility['type']);
                        $badgeClass = match(strtolower($type)) {
                          'classroom' => 'bg-primary',
                          'laboratory' => 'bg-success',
                          'auditorium' => 'bg-warning text-dark',
                          default => 'bg-secondary'
                        };
                        ?>
                        <span class="badge <?= $badgeClass ?>"><?= $type ?></span>
                      </td>
                      <td class="text-center align-middle">
                        <span class="badge bg-info text-dark"><?= number_format($facility['capacity']) ?></span>
                      </td>
                      <td class="align-middle">
                        <i class="bi bi-building me-1"></i>
                        <?= htmlspecialchars($facility['buildname'] ?? 'N/A') ?>
                      </td>
                      <td class="align-middle">
                        <span class="badge bg-light text-dark border">
                          <?= htmlspecialchars($facility['build_code'] ?? 'N/A') ?>
                        </span>
                      </td>
                      <td class="text-center align-middle">
                        <a href="facility_view.php?id=<?= $facility['id'] ?>" 
                                data-bs-toggle="tooltip" >
                           View
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
          <!-- Pagination removed to show all facilities -->
        </div>
      </div>
    </div>
  </div>
</main>

<!-- View Facility Modal -->
<div class="modal fade" id="viewFacilityModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Facility Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <table class="table table-bordered">
          <tr>
            <th>ID:</th>
            <td id="view-id"></td>
          </tr>
          <tr>
            <th>Name:</th>
            <td id="view-name"></td>
          </tr>
          <tr>
            <th>Type:</th>
            <td id="view-type"></td>
          </tr>
          <tr>
            <th>Capacity:</th>
            <td id="view-capacity"></td>
          </tr>
          <tr>
            <th>Building:</th>
            <td id="view-building"></td>
          </tr>
          <tr>
            <th>Building Code:</th>
            <td id="view-building-code"></td>
          </tr>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- JS -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>



<script>
// Initialize DataTable
document.addEventListener('DOMContentLoaded', function() {
  // Initialize Bootstrap tooltips
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });

  // Initialize DataTable
  const table = $('#facilitiesTable').DataTable({
    dom: "<'row'<'col-12'B>>" +
         "<'row'<'col-12'f>>" +
         "<'row'<'col-12'tr>>" +
         "<'row'<'col-12 col-md-5'i><'col-12 col-md-7'p>>",
    scrollX: true,
    buttons: [
      {
        extend: 'excel',
        className: 'btn btn-sm btn-outline-secondary',
        text: '<i class="bi bi-file-earmark-excel me-1"></i> Excel',
        exportOptions: {
          columns: [0, 1, 2, 3, 4, 5]
        }
      },
      {
        extend: 'pdf',
        className: 'btn btn-sm btn-outline-secondary',
        text: '<i class="bi bi-file-earmark-pdf me-1"></i> PDF',
        exportOptions: {
          columns: [0, 1, 2, 3, 4, 5]
        }
      },
      {
        extend: 'print',
        className: 'btn btn-sm btn-outline-secondary',
        text: '<i class="bi bi-printer me-1"></i> Print',
        exportOptions: {
          columns: [0, 1, 2, 3, 4, 5]
        }
      }
    ],
    pageLength: -1, // Show all records by default
    scrollCollapse: true,
    fixedHeader: true,
    lengthMenu: [[-1, 10, 25, 50, 100], ["All", 10, 25, 50, 100]],
    order: [[0, 'asc']],
    language: {
      search: "_INPUT_",
      searchPlaceholder: "Search facilities...",
      lengthMenu: "Show _MENU_ entries",
      info: "Showing _START_ to _END_ of _TOTAL_ facilities",
      infoEmpty: "No facilities found",
      infoFiltered: "(filtered from _MAX_ total facilities)",
      paginate: {
        first: "First",
        last: "Last",
        next: "<i class='bi bi-chevron-right'></i>",
        previous: "<i class='bi bi-chevron-left'></i>"
      }
    },
    initComplete: function() {
      // Add custom info
      this.api().on('draw.dt', function() {
        const pageInfo = table.page.info();
        $('#tableInfo').html(`Showing ${pageInfo.recordsDisplay} of ${pageInfo.recordsTotal} facilities`);
      });
    }
  });

  // Type filter
  $('#typeFilter').on('change', function() {
    table.column(2).search(this.value).draw();
  });

  // Capacity filter
  $('#capacityFilter').on('change', function() {
    const val = this.value;
    if (val) {
      const [min, max] = val.split('-').map(Number);
      $.fn.dataTable.ext.search.push(
        function(settings, data, dataIndex) {
          const capacity = parseFloat(data[3]) || 0; // capacity is in 4th column (0-based index 3)
          return (capacity >= min && capacity <= max);
        }
      );
    } else {
      $.fn.dataTable.ext.search.pop();
    }
    table.draw();
  });

  // Reset filters
  $('#resetFilters').on('click', function() {
    $('#typeFilter, #capacityFilter').val('').trigger('change');
    table.search('').columns().search('').draw();
  });
});

// Initialize view modal
const viewFacilityModal = new bootstrap.Modal(document.getElementById('viewFacilityModal'));

// Handle view button click
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.view-facility').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const type = this.getAttribute('data-type');
            const capacity = this.getAttribute('data-capacity');
            const building = this.getAttribute('data-building') || 'N/A';
            const buildingCode = this.getAttribute('data-building-code') || 'N/A';
            
            document.getElementById('view-id').textContent = id;
            document.getElementById('view-name').textContent = name;
            document.getElementById('view-type').textContent = type;
            document.getElementById('view-capacity').textContent = capacity;
            document.getElementById('view-building').textContent = building;
            document.getElementById('view-building-code').textContent = buildingCode;
            
            viewFacilityModal.show();
        });
    });
});
</script>

</body>
</html>
  