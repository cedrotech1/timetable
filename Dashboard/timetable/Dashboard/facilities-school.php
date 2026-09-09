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
$sitesResult = $connection->query("SELECT id, name FROM site WHERE campus = $campus_id ORDER BY name ASC");
$sites = [];
while ($row = $sitesResult->fetch_assoc()) {
    $sites[$row['id']] = $row['name'];
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
</head>
<body>
<?php
include("./includes/header.php");
include("./includes/menu.php");
?>

<main id="main" class="main">
  <div class="pagetitle">
    <h1>Facilities</h1>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
        <li class="breadcrumb-item active">Facilities</li>
      </ol>
    </nav>
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

    <!-- Add Facility Button -->
<div class="d-flex justify-content-between align-items-center mb-3">
  <h5>Facilities List</h5>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#facilityModal" onclick="openAddModal()">Add Facility</button>
</div>

<table id="facilitiesTable" class="table table-striped table-borderless" style="width:100%">
  <thead>
    <tr>
      <th>Name</th>
      <th>Type</th>
      <th>Capacity</th>
      <th>Site</th>
      <th>Actions</th>
    
    </tr>
  </thead>
  <tbody>
    <?php
    $query = "SELECT f.id, f.name, f.type, f.capacity, f.site, s.name AS site_name
              FROM facility f
              LEFT JOIN site s ON f.site = s.id
              ORDER BY f.name ASC";
    $result = $connection->query($query);
    while ($row = $result->fetch_assoc()):
    ?>
    <tr>
      <td><?= htmlspecialchars($row['name']) ?></td>
      <td><?= htmlspecialchars($row['type']) ?></td>
      <td><?= htmlspecialchars($row['capacity']) ?></td>
      <td><?= htmlspecialchars($row['site_name']) ?></td>
      <td>
        <!-- view -->
        <a href="facility_view.php?id=<?= $row['id'] ?>" class="btn btn-primary btn-sm">View</a>
      </td>
    </tr>
    <?php endwhile; ?>
  </tbody>
</table>
<!-- Modal for Add/Edit -->
<div class="modal fade" id="facilityModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form id="facilityForm" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="facilityModalLabel">Add Facility</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="facilityId">
        <div class="mb-3">
          <label class="form-label">Name</label>
          <input required type="text" class="form-control" name="name" id="facilityName">
        </div>
        <div class="mb-3">
          <label class="form-label">Type</label>
          <!-- <input required type="text" class="form-control" name="type" id="facilityType"> -->
           <!-- select -->
           <select required class="form-select" name="type" id="facilityType">
            <option value="">Select Type</option>
            <option value="classroom">Classroom</option>
            <option value="laboratory">Laboratory</option>
            <option value="other">Other</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Capacity</label>
          <input required type="number" min="1" class="form-control" name="capacity" id="facilityCapacity">
        </div>
        <div class="mb-3">
          <label class="form-label">Site</label>
          <select required class="form-select" name="site" id="facilitySite">
            <option value="">Select Site</option>
            <?php foreach ($sites as $id => $name): ?>
              <option value="<?= $id ?>"><?= htmlspecialchars($name) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div id="formAlert" class="alert alert-danger d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Save</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>


    </div>
  </div>
</main>

<!-- JS -->
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- jQuery (required for DataTables) -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
  const table = $('#facilitiesTable').DataTable({
    order: [[0, 'asc']],
    pageLength: 10,
  });

  // Filter by site name column (index 3)
  $('#siteFilter').on('change', function() {
    const val = $.fn.dataTable.util.escapeRegex($(this).val());
    if(val) {
      table.column(3).search('^' + val + '$', true, false).draw();  // exact match
    } else {
      table.column(3).search('').draw();
    }
  });
});
</script>

<script>
function openAddModal() {
  $('#facilityModalLabel').text('Add Facility');
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
  $('#formAlert').addClass('d-none').text('');
  new bootstrap.Modal(document.getElementById('facilityModal')).show();
}

$('#facilityForm').on('submit', function(e) {
  e.preventDefault();
  const formData = $(this).serialize();
  $.post('save_facility.php', formData, function(res) {
    if (res.success) {
      location.reload();
    } else {
      $('#formAlert').removeClass('d-none').text(res.message);
    }
  }, 'json').fail(() => {
    $('#formAlert').removeClass('d-none').text('Server error.');
  });
});
</script>


</body>
</html>
