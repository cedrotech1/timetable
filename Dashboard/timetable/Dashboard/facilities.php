<?php
session_start();
include("connection.php");

$current_user_id = $_SESSION['id'];
$stmt = $connection->prepare("SELECT role, campus, college, school FROM users WHERE id = ?");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();
$current_user = $result->fetch_assoc();
$campus_id = $current_user['campus'];


// Fetch all sites to build filter dropdown
$sites = [];
if ($campus_id) { 
    $stmt = $connection->prepare("SELECT id, name FROM site WHERE campus = ? ORDER BY name ASC");
    $stmt->bind_param("i", $campus_id);
    $stmt->execute();
    $sitesResult = $stmt->get_result();
    while ($row = $sitesResult->fetch_assoc()) {
        $sites[$row['id']] = $row['name'];
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta content="width=device-width, initial-scale=1.0" name="viewport" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests" />
  <title>UR-TIMETABLE - Facilities</title>
  <link href="assets/img/icon1.png" rel="icon" />
  <link href="assets/img/icon1.png" rel="apple-touch-icon" />

  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet" />
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet" />
  <link href="assets/css/style.css" rel="stylesheet" />

  <!-- DataTables CSS -->
  <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet" />
  
  <!-- SweetAlert2 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
  
  <!-- SweetAlert2 JS -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
</head>
<body>
<?php
include("./includes/header.php");
include("./includes/menu.php");
?>

<main id="main" class="main">
  <div class="d-flex justify-content-between align-items-center mb-3">
  <h5>Facilities List</h5>
  <div>
    <div class="btn-group" role="group">
      <button type="button" class="btn btn-danger btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" <?= $campus_id ? '' : 'disabled' ?>>
        <i class="bi bi-trash"></i> Delete Facilities
      </button>
      <ul class="dropdown-menu">
        <li><a class="dropdown-item" href="#" id="deleteCampusFacilities">From Entire Campus</a></li>
        <li><a class="dropdown-item" href="#" id="deleteSiteFacilities">From Current Site</a></li>
      </ul>
    </div>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
        <li class="breadcrumb-item active">Facilities</li>
      </ol>
    </nav>
  </div>
  </div>

  <div class="container mt-4">
    <div class="card p-3">
      <div class="row mb-3">
        <div class="col-md-3">
          <label for="siteFilter" class="form-label">Filter by Site</label>
          <select id="siteFilter" class="form-select">
            <option value="">All Sites</option>
            <?php foreach ($sites as $id => $name): ?>
              <option value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

    <!-- Table Container -->
        <div class="table-container">
          <div class="table-responsive">
            <table id="facilitiesTable" class="table table-striped table-hover" style="width:100%">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Type</th>
                  <th>Capacity</th>
                  <th>Building Name</th>
                  <th>Building Code</th>
                  <th>Site</th>
                  <th>Campus</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $query = "SELECT f.id, f.name, f.type, f.buildname, f.build_code, f.capacity, f.site, s.name AS site_name, 
                                 camp.name AS campus_name
                          FROM facility f
                          LEFT JOIN site s ON f.site = s.id
                          LEFT JOIN campus camp ON s.campus = camp.id
                          ORDER BY camp.name, s.name, f.name ASC";
                $result = $connection->query($query);
                while ($row = $result->fetch_assoc()):
                ?>
                <tr>
                  <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                  <td>
                    <span class="badge bg-<?= $row['type'] == 'classroom' ? 'primary' : ($row['type'] == 'laboratory' ? 'success' : 'secondary') ?>">
                      <?= ucfirst(htmlspecialchars($row['type'])) ?>
                    </span>
                  </td>
                  <td><?= htmlspecialchars($row['capacity']) ?></td>
                  <td><?= htmlspecialchars($row['buildname'] ?? 'N/A') ?></td>
                  <td><code><?= htmlspecialchars($row['build_code'] ?? 'N/A') ?></code></td>
                  <td><?= htmlspecialchars($row['site_name'] ?? 'N/A') ?></td>
                  <td><?= htmlspecialchars($row['campus_name'] ?? 'N/A') ?></td>
                  <td>
                    <div class="btn-group" role="group">
                      <button class="btn btn-sm btn-outline-primary btn-custom" onclick='openEditModal(<?= json_encode($row) ?>)' title="Edit">
                        <i class="bi bi-pencil"></i>
                      </button>
                      <a href="facility_view.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-info btn-custom" title="View">
                        <i class="bi bi-eye"></i>
                      </a>
                      <button class="btn btn-sm btn-outline-danger btn-custom delete-facility" 
                              data-id="<?= $row['id'] ?>" 
                              data-name="<?= htmlspecialchars($row['name']) ?>"
                              title="Delete">
                        <i class="bi bi-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>
    </div>
  </div>
</main>

<div class="modal fade" id="facilityModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form id="facilityForm" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="facilityModalLabel">Add Facility</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="facilityId">
        
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label fw-bold">Facility Name *</label>
            <input required type="text" class="form-control" name="name" id="facilityName" placeholder="Enter facility name">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label fw-bold">Type *</label>
            <select required class="form-select" name="type" id="facilityType">
              <option value="">Select Type</option>
              <option value="classroom">Classroom</option>
              <option value="laboratory">Laboratory</option>
              <option value="other">Other</option>
            </select>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label fw-bold">Capacity *</label>
            <input required type="number" min="1" class="form-control" name="capacity" id="facilityCapacity" placeholder="Enter capacity">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label fw-bold">Site *</label>
            <select required class="form-select" name="site" id="facilitySite">
              <option value="">Select Site</option>
              <?php foreach ($sites as $id => $name): ?>
                <option value="<?= $id ?>"><?= htmlspecialchars($name) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label fw-bold">Building Name</label>
            <input type="text" class="form-control" name="buildname" id="facilityBuildname" placeholder="Enter building name">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label fw-bold">Building Code</label>
            <input type="text" class="form-control" name="build_code" id="facilityBuildCode" placeholder="Enter building code">
          </div>
        </div>

        <div id="formAlert" class="alert alert-danger d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary btn-custom">
          <i class="bi bi-check-circle"></i> Save Facility
        </button>
        <button type="button" class="btn btn-secondary btn-custom" data-bs-dismiss="modal">
          <i class="bi bi-x-circle"></i> Cancel
        </button>
      </div>
    </form>
  </div>
</div>

<!-- JS -->
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- jQuery (required for DataTables) -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
// Function to confirm and delete a facility
function deleteFacility(id, name) {
  Swal.fire({
    title: 'Delete Facility',
    text: `Are you sure you want to delete "${name}"? This action cannot be undone.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Yes, delete it!',
    cancelButtonText: 'Cancel'
  }).then((result) => {
    if (result.isConfirmed) {
      $.post('delete_facility.php', {
        action: 'delete_single',
        facility_id: id
      }, function(response) {
        if (response.success) {
          Swal.fire('Deleted!', response.message, 'success').then(() => {
            location.reload();
          });
        } else {
          Swal.fire('Error!', response.message || 'Failed to delete facility', 'error');
        }
      }, 'json');
    }
  });
}

// Function to confirm and handle facility deletion
function confirmDeletion(type, id, locationName) {
  const isCampus = type === 'campus';
  const locationText = isCampus ? 'entire campus' : 'current site';
  
  Swal.fire({
    title: `Delete All Facilities`,
    html: `Are you sure you want to delete <strong>ALL</strong> facilities from the ${locationText}?<br><br>
           <span class="text-danger"><i class="bi bi-exclamation-triangle-fill"></i> This action cannot be undone and will affect all related data!</span>`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#6c757d',
    confirmButtonText: `Yes, delete from ${locationText}`,
    cancelButtonText: 'Cancel',
    input: 'text',
    inputPlaceholder: 'Type "DELETE ALL" to confirm',
    inputValidator: (value) => {
      if (value !== 'DELETE ALL') {
        return 'You need to type "DELETE ALL" to confirm';
      }
    }
  }).then((result) => {
    if (result.isConfirmed) {
      $.ajax({
        url: 'delete_facility.php',
        type: 'POST',
        data: {
          action: 'delete_all',
          type: type,
          id: id
        },
        dataType: 'json',
        success: function(response) {
          if (response.success) {
            Swal.fire({
              title: 'Success!',
              text: response.message,
              icon: 'success',
              confirmButtonText: 'OK'
            }).then(() => {
              location.reload();
            });
          } else {
            Swal.fire('Error!', response.message || 'Failed to delete facilities', 'error');
          }
        },
        error: function() {
          Swal.fire('Error!', 'Failed to connect to server', 'error');
        }
      });
    }
  });
}

$(document).ready(function() {
  const table = $('#facilitiesTable').DataTable({
    order: [[0, 'asc']],
    pageLength: 10,
    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
    responsive: true,
    dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
         '<"row"<"col-sm-12"tr>>' +
         '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
    language: {
      search: "_INPUT_",
      searchPlaceholder: "Search facilities...",
      lengthMenu: "_MENU_ facilities per page",
      info: "Showing _START_ to _END_ of _TOTAL_ facilities",
      infoEmpty: "No facilities found",
      infoFiltered: "(filtered from _MAX_ total facilities)",
      paginate: {
        previous: "Previous",
        next: "Next"
      }
    }
  });

  // Filter by site name (column index 5)
  $('#siteFilter').on('change', function() {
    const val = $.fn.dataTable.util.escapeRegex($(this).val());
    if(val) {
      table.column(5).search('^' + val + '$', true, false).draw();
    } else {
      table.column(5).search('').draw();
    }
  });

  // Filter by type (column index 1)
  $('#typeFilter').on('change', function() {
    const val = $.fn.dataTable.util.escapeRegex($(this).val());
    if(val) {
      table.column(1).search(val, true, false).draw();
    } else {
      table.column(1).search('').draw();
    }
  });
});

function openAddModal() {
  $('#facilityModalLabel').text('Add New Facility');
  $('#facilityForm')[0].reset();
  $('#facilityId').val('');
  $('#formAlert').addClass('d-none').text('');
}

function openEditModal(data) {
  $('#facilityModalLabel').text('Edit Facility');
  $('#facilityId').val(data.id);
  $('#facilityName').val(data.name);
  $('#facilityType').val(data.type);
  $('#facilityCapacity').val(data.capacity);
  $('#facilitySite').val(data.site);
  $('#facilityBuildname').val(data.buildname || '');
  $('#facilityBuildCode').val(data.build_code || '');
  $('#formAlert').addClass('d-none').text('');
  new bootstrap.Modal(document.getElementById('facilityModal')).show();
}

$('#facilityForm').on('submit', function(e) {
  e.preventDefault();
  const submitBtn = $(this).find('button[type="submit"]');
  const originalText = submitBtn.html();
  
  // Show loading state
  submitBtn.html('<i class="bi bi-hourglass-split"></i> Saving...').prop('disabled', true);
  
  const formData = $(this).serialize();
  $.post('save_facility.php', formData, function(res) {
    if (res.success) {
      // Show success message briefly before reload
      $('#formAlert').removeClass('d-none alert-danger').addClass('alert-success').text('Facility saved successfully!');
      setTimeout(() => {
        location.reload();
      }, 1000);
    } else {
      $('#formAlert').removeClass('d-none alert-success').addClass('alert-danger').text(res.message || 'Error saving facility');
      submitBtn.html(originalText).prop('disabled', false);
    }
  }, 'json').fail(() => {
    $('#formAlert').removeClass('d-none alert-success').addClass('alert-danger').text('Server error occurred. Please try again.');
    submitBtn.html(originalText).prop('disabled', false);
  });
});

// Handle delete button click
$(document).on('click', '.delete-facility', function() {
  const id = $(this).data('id');
  const name = $(this).data('name');
  deleteFacility(id, name);
});

// Handle delete buttons click
$('#deleteCampusFacilities').on('click', function(e) {
  e.preventDefault();
  const campusId = <?= $campus_id ?? 0 ?>;
  if (campusId) {
    confirmDeletion('campus', campusId, 'campus');
  } else {
    Swal.fire('Error', 'No campus selected', 'error');
  }
});

$('#deleteSiteFacilities').on('click', function(e) {
  e.preventDefault();
  const siteId = <?= $site_id ?? 0 ?>;
  if (siteId) {
    confirmDeletion('site', siteId, 'site');
  } else {
    Swal.fire('Error', 'Please select a site first', 'error');
  }
});

// Add some interactive feedback
$('.btn-custom').hover(function() {
  $(this).addClass('shadow');
}, function() {
  $(this).removeClass('shadow');
});
</script>

</body>
</html>