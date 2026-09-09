<?php
// Set error handling before any other code
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/upload_errors.log');

session_start();
include("connection.php");

if (!isset($_SESSION['id'])) {
    header("Location: index.php");
    exit();
}
// only admin can access this page
if ($_SESSION['role'] != 'admin') {
  header("Location: index.php");
  exit();
}

$user_id = $_SESSION['id'];

$site_id = isset($_GET['site_id']) ? intval($_GET['site_id']) : 0;

// Get site information including campus
$site_info = [];
if ($site_id > 0) {
    $site_query = "SELECT s.*, c.name as campus_name 
                  FROM site s 
                  JOIN campus c ON s.campus = c.id 
                  WHERE s.id = ?";
    $stmt = $connection->prepare($site_query);
    $stmt->bind_param("i", $site_id);
    $stmt->execute();
    $site_result = $stmt->get_result();
    $site_info = $site_result->fetch_assoc();
}

// Get campus ID from site information
$campus_id = $site_info['campus'] ?? null;

// Redirect if site not found
if ($site_id > 0 && empty($site_info)) {
    $_SESSION['error'] = "Site not found or access denied";
    header("Location: manage_sites.php");
    exit();
}

// Handle AJAX upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') === 0) {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$campus_id) {
        echo json_encode(['success' => false, 'message' => 'Campus not found']);
        exit();
    }

    if ($site_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid site ID']);
        exit();
    }

    if (!isset($input['facilities']) || !is_array($input['facilities'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid facility data']);
        exit();
    }

    try {
        $connection->begin_transaction();

        $checkStmt = $connection->prepare("SELECT COUNT(*) FROM facility WHERE name = ? AND campus_id = ? AND site = ?");
        $insertStmt = $connection->prepare("INSERT INTO facility (name, name2, type, capacity, campus_id, site, buildname, build_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        $duplicates = [];

        foreach ($input['facilities'] as $fac) {
            $name = trim($fac['name'] ?? '');
            $name2 = trim($fac['name2'] ?? '');
            $type = trim($fac['type'] ?? '');
            $capacity = (int)($fac['capacity'] ?? 0);
            $buildname = trim($fac['buildname'] ?? '');
            $build_code = trim($fac['build_code'] ?? '');

            if ($name && $type && $capacity > 0) {
                $checkStmt->bind_param("sii", $name, $campus_id, $site_id);
                $checkStmt->execute();
                $checkStmt->bind_result($count);
                $checkStmt->fetch();
                $checkStmt->free_result();

                if ($count > 0) {
                    $duplicates[] = $name;
                    continue;
                }

                $insertStmt->bind_param("ssssiiss", $name, $name2, $type, $capacity, $campus_id, $site_id, $buildname, $build_code);
                $insertStmt->execute();
            }
        }

        $checkStmt->close();
        $insertStmt->close();

        $connection->commit();

        if (!empty($duplicates)) {
            echo json_encode(['success' => true, 'message' => 'Some facilities were skipped due to duplication: ' . implode(', ', $duplicates)]);
        } else {
            echo json_encode(['success' => true, 'message' => 'Facilities uploaded successfully!']);
        }
    } catch (Exception $e) {
        if (isset($checkStmt)) $checkStmt->close();
        if (isset($insertStmt)) $insertStmt->close();
        $connection->rollback();
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }

    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Upload Facilities - <?php echo htmlspecialchars($site_info['name'] ?? 'Site'); ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet" />
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet" />
  <link href="assets/css/style.css" rel="stylesheet" />
  <script src="https://cdn.sheetjs.com/xlsx-0.20.0/package/dist/xlsx.full.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<?php include("./includes/header.php"); ?>
<?php include("./includes/menu.php"); ?>

<main id="main" class="main">
  <div class="pagetitle">
    <h1>Upload Facilities</h1>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
        <li class="breadcrumb-item"><a href="manage_sites.php">Sites</a></li>
        <li class="breadcrumb-item active">Upload Facilities</li>
      </ol>
    </nav>
  </div>

  <?php if (!empty($site_info)): 
    // Fetch associated schools for this site
    $schools_query = "SELECT s.id, s.name 
                     FROM school s
                     JOIN site_school ss ON s.id = ss.school_id
                     WHERE ss.site_id = ?
                     ORDER BY s.name ASC";
    $stmt = $connection->prepare($schools_query);
    $stmt->bind_param("i", $site_id);
    $stmt->execute();
    $schools_result = $stmt->get_result();
    $schools = [];
    while ($school = $schools_result->fetch_assoc()) {
        $schools[] = $school;
    }
    $stmt->close();
  ?>
  <div class="row">
    <div class="col-12">
      <div class="card">
        <div class="card-body">
          <div class="row">
            <div class="col-md-6">
              <h5 class="card-title">Site Information</h5>
              <p class="mb-2"><strong>Site Name:</strong> <?php echo htmlspecialchars($site_info['name']); ?></p>
              <p class="mb-2"><strong>Campus:</strong> <?php echo htmlspecialchars($site_info['campus_name'] ?? 'N/A'); ?></p>
              
              <h6 class="mt-4 mb-2">Associated Schools:</h6>
              <?php if (!empty($schools)): ?>
                <div class="d-flex flex-wrap gap-2">
                  <?php foreach ($schools as $school): ?>
                    <span class="badge bg-primary"><?php echo htmlspecialchars($school['name']); ?></span>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <div class="alert alert-warning py-1 px-2 mb-0 small">
                  No schools assigned to this site.
                </div>
              <?php endif; ?>
            </div>
            <div class="col-md-6">
              <!-- Additional site information can go here -->
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="container mt-4">
    <div class="row">
      <div class="col-md-7">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Excel Upload</strong>
            <div>
              <button type="button" onclick="downloadSampleExcel()" class="btn btn-sm btn-success me-2">
                <i class="bi bi-download"></i> Download Template
              </button>
              <a href="#" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#instructionsModal">
                <i class="bi bi-info-circle"></i> Instructions
              </a>
            </div>
          </div>
          <div class="card-body">
            <form id="uploadForm">
              <input type="hidden" id="site_id" value="<?= htmlspecialchars($site_id) ?>" />
              <div class="mb-3">
                <label class="form-label">Select Excel File</label>
                <input type="file" class="form-control" id="excelFile" accept=".xlsx,.xls" required />
              </div>
              <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> Upload</button>
            </form>
          </div>
        </div>
      </div>

      <!-- Reference Table -->
      <div class="col-md-5">
        <div class="card">
          <div class="card-header">
            <strong>Reference Format</strong>
          </div>
          <div class="card-body">
            <table class="table table-sm table-bordered mb-0">
              <thead class="table-light">
                <tr>
                  <th>Column</th>
                  <th>Description</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td><code>Name</code></td>
                  <td>Facility name (e.g., Room 101)</td>
                </tr>
                <tr>
                  <td><code>Type</code></td>
                  <td>Facility type (e.g., Lab, Lecture Hall)</td>
                </tr>
                <tr>
                  <td><code>Capacity</code></td>
                  <td>Integer (must be > 0)</td>
                </tr>
                <tr>
                  <td><code>Building Name</code></td>
                  <td>Name of the building (e.g., Science Building)</td>
                </tr>
                <tr>
                  <td><code>Building Code</code></td>
                  <td>Building code/identifier (e.g., SCI)</td>
                </tr>
              </tbody>
            </table>
            <div class="mt-2 text-muted small">* Make sure there are no duplicates within the same site and campus.</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Existing Facilities Section -->
  <div class="container mt-4">
    <?php if (isset($_SESSION['success'])): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Existing Facilities</h5>
        <div>
          <button type="button" class="btn btn-danger btn-sm me-2" id="deleteSelectedBtn" disabled>
            <i class="bi bi-trash"></i> Delete Selected
          </button>
          <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addFacilityModal">
            <i class="bi bi-plus-lg"></i> Add Facility
          </button>
        </div>
      </div>
      <div class="card-body">
        <?php
        if ($site_id > 0) {
          $facilities_query = "SELECT f.* 
                             FROM facility f 
                             WHERE f.site = ? 
                             ORDER BY f.name ASC";
          $stmt = $connection->prepare($facilities_query);
          $stmt->bind_param("i", $site_id);
          $stmt->execute();
          $facilities_result = $stmt->get_result();
          
          if ($facilities_result->num_rows > 0): ?>
            <div class="table-responsive">
              <form id="deleteFacilitiesForm" method="post" action="delete_facilities.php">
                <input type="hidden" name="site_id" value="<?php echo $site_id; ?>">
                <table class="table table-striped table-hover">
                  <thead>
                    <tr>
                      <th width="40">
                        <input type="checkbox" id="selectAll" class="form-check-input">
                      </th>
                      <th>Name</th>
                    <th>Name2</th>
                    <th>Type</th>
                    <th>Capacity</th>
                    <th>Building</th>
                    <th>Building Code</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while ($facility = $facilities_result->fetch_assoc()): ?>
                    <tr>
                      <td>
                        <input type="checkbox" name="facility_ids[]" value="<?php echo $facility['id']; ?>" class="form-check-input facility-checkbox">
                      </td>
                      <td><?php echo htmlspecialchars($facility['name']); ?></td>
                      <td><?php echo htmlspecialchars($facility['name2'] ?? '-'); ?></td>
                      <td><?php echo htmlspecialchars($facility['type']); ?></td>
                      <td><?php echo htmlspecialchars($facility['capacity']); ?></td>
                      <td><?php echo htmlspecialchars($facility['buildname'] ?? '-'); ?></td>
                      <td><?php echo htmlspecialchars($facility['build_code'] ?? '-'); ?></td>
                      <td>
                        <button type="button" class="btn btn-sm btn-warning edit-facility" 
                                data-id="<?php echo $facility['id']; ?>"
                                data-name="<?php echo htmlspecialchars($facility['name']); ?>"
                                data-name2="<?php echo htmlspecialchars($facility['name2'] ?? ''); ?>"
                                data-type="<?php echo htmlspecialchars($facility['type']); ?>"
                                data-capacity="<?php echo htmlspecialchars($facility['capacity']); ?>"
                                data-buildname="<?php echo htmlspecialchars($facility['buildname'] ?? ''); ?>"
                                data-buildcode="<?php echo htmlspecialchars($facility['build_code'] ?? ''); ?>">
                          <i class="bi bi-pencil"></i> Edit
                        </button>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
              </form>
            </div>
          <?php else: ?>
            <div class="alert alert-info mb-0">
              No facilities found for this site. Upload facilities using the form above.
              <?php if ($site_id > 0): ?>
                <div class="mt-2">
                  <button type="button" id="deleteAllFacilities" class="btn btn-sm btn-outline-danger" disabled>
                    <i class="bi bi-trash"></i> Delete All Facilities
                  </button>
                </div>
              <?php endif; ?>
            </div>
          <?php endif;
          
          $stmt->close();
        } else { ?>
          <div class="alert alert-warning mb-0">
            Please select a site to view its facilities.
          </div>
        <?php } ?>
      </div>
    </div>
  </div>
</main>

<!-- Add Facility Modal -->
<div class="modal fade" id="addFacilityModal" tabindex="-1" aria-labelledby="addFacilityModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addFacilityModalLabel">Add New Facility</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="addFacilityForm">
        <div class="modal-body">
          <input type="hidden" id="facilitySiteId" value="<?php echo $site_id; ?>">
          <div class="mb-3">
            <label for="facilityCode" class="form-label">Code *</label>
            <input type="text" class="form-control" id="facilityCode" required>
            <div class="form-text">Unique identifier (e.g., C001, L101)</div>
          </div>
          <div class="mb-3">
            <label for="facilityName" class="form-label">Name</label>
            <input type="text" class="form-control" id="facilityName">
            <div class="form-text">Optional: Full name or description</div>
          </div>
          <div class="mb-3">
            <label for="facilityType" class="form-label">Type *</label>
            <div class="input-group">
              <select class="form-select" id="facilityType" required>
                <option value="">Select Type</option>
                <?php
                // Get all unique facility types from the database
                $type_query = "SELECT DISTINCT type FROM facility WHERE type != '' ORDER BY type";
                $type_result = $connection->query($type_query);
                while ($type = $type_result->fetch_assoc()) {
                  echo "<option value=\"" . htmlspecialchars($type['type']) . "\">" . htmlspecialchars($type['type']) . "</option>";
                }
                ?>
                <option value="__custom__">+ Add New Type</option>
              </select>
              <input type="text" class="form-control d-none" id="newFacilityType" placeholder="Enter new type">
            </div>
          </div>
          <div class="mb-3">
            <label for="facilityCapacity" class="form-label">Capacity *</label>
            <input type="number" class="form-control" id="facilityCapacity" min="1" required>
          </div>
          <div class="mb-3">
            <label for="facilityBuilding" class="form-label">Building</label>
            <input type="text" class="form-control" id="facilityBuilding">
          </div>
          <div class="mb-3">
            <label for="facilityBuildingCode" class="form-label">Building Code</label>
            <input type="text" class="form-control" id="facilityBuildingCode">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Facility</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Update Facility Modal -->
<div class="modal fade" id="updateFacilityModal" tabindex="-1" aria-labelledby="updateFacilityModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="updateFacilityModalLabel">Update Facility</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="updateFacilityForm">
        <input type="hidden" id="updateFacilityId">
        <input type="hidden" id="updateFacilitySiteId" value="<?php echo $site_id; ?>">
        <div class="modal-body">
          <div class="mb-3">
            <label for="updateFacilityCode" class="form-label">Code *</label>
            <input type="text" class="form-control" id="updateFacilityCode" required>
          </div>
          <div class="mb-3">
            <label for="updateFacilityName" class="form-label">Name</label>
            <input type="text" class="form-control" id="updateFacilityName">
          </div>
          <div class="mb-3">
            <label for="updateFacilityType" class="form-label">Type *</label>
            <div class="input-group">
              <select class="form-select" id="updateFacilityType" required>
                <option value="">Select Type</option>
                <?php
                $type_result = $connection->query("SELECT DISTINCT type FROM facility WHERE type != '' ORDER BY type");
                while ($type = $type_result->fetch_assoc()) {
                  echo "<option value=\"" . htmlspecialchars($type['type']) . "\">" . htmlspecialchars($type['type']) . "</option>";
                }
                ?>
                <option value="__custom__">+ Add New Type</option>
              </select>
              <input type="text" class="form-control d-none" id="updateNewFacilityType" placeholder="Enter new type">
            </div>
          </div>
          <div class="mb-3">
            <label for="updateFacilityCapacity" class="form-label">Capacity *</label>
            <input type="number" class="form-control" id="updateFacilityCapacity" min="1" required>
          </div>
          <div class="mb-3">
            <label for="updateFacilityBuilding" class="form-label">Building</label>
            <input type="text" class="form-control" id="updateFacilityBuilding">
          </div>
          <div class="mb-3">
            <label for="updateFacilityBuildingCode" class="form-label">Building Code</label>
            <input type="text" class="form-control" id="updateFacilityBuildingCode">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update Facility</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Instructions Modal -->
<div class="modal fade" id="instructionsModal" tabindex="-1" aria-labelledby="instructionsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="instructionsModalLabel">Excel Template Instructions</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <h6>Required Columns:</h6>
        <ul>
          <li><strong>name</strong> - Unique identifier for the facility (e.g., C001, L101)</li>
          <li><strong>type</strong> - Type of facility (e.g., Classroom, Laboratory, Auditorium)</li>
          <li><strong>capacity</strong> - Number of people the facility can accommodate (must be a number > 0)</li>
        </ul>
        
        <h6>Optional Columns:</h6>
        <ul>
          <li><strong>name2</strong> - Additional name or description</li>
          <li><strong>buildname</strong> - Name of the building</li>
          <li><strong>build_code</strong> - Building code</li>
        </ul>
        
        <div class="alert alert-info mt-3">
          <strong>Note:</strong> The first row must contain the column headers exactly as shown above.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" onclick="downloadSampleExcel()">
          <i class="bi bi-download"></i> Download Template
        </button>
      </div>
    </div>
  </div>
</div>

<script>
// Handle Add Facility Form Submission
document.getElementById('addFacilityForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const siteId = document.getElementById('facilitySiteId').value;
    const code = document.getElementById('facilityCode').value.trim();
    const name = document.getElementById('facilityName').value.trim();
    const type = getSelectedFacilityType();
    const capacity = document.getElementById('facilityCapacity').value;
    const building = document.getElementById('facilityBuilding').value.trim();
    const buildingCode = document.getElementById('facilityBuildingCode').value.trim();

    // Basic validation
    if (!code || !type || !capacity || (facilityTypeSelect.value === '__custom__' && !newFacilityTypeInput.value.trim())) {
        Swal.fire('Error', 'Please fill in all required fields', 'error');
        return;
    }

    try {
        const response = await fetch('save_facility.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                site_id: siteId,
                name: code,
                name2: name,
                type: type,
                capacity: parseInt(capacity),
                buildname: building,
                build_code: buildingCode
            })
        });

        const result = await response.json();
        
        if (result.success) {
            Swal.fire('Success', 'Facility added successfully!', 'success').then(() => {
                location.reload();
            });
        } else {
            Swal.fire('Error', result.message || 'Failed to add facility', 'error');
        }
    } catch (error) {
      Swal.fire('Success', 'Facility added successfully!', 'success').then(() => {
                location.reload();
            });
    }
});

// Handle facility type selection
const facilityTypeSelect = document.getElementById('facilityType');
const newFacilityTypeInput = document.getElementById('newFacilityType');

facilityTypeSelect.addEventListener('change', function() {
  if (this.value === '__custom__') {
    this.classList.add('d-none');
    newFacilityTypeInput.classList.remove('d-none');
    newFacilityTypeInput.focus();
    newFacilityTypeInput.required = true;
  } else {
    newFacilityTypeInput.classList.add('d-none');
    newFacilityTypeInput.required = false;
  }
});

// Function to get the selected facility type
function getSelectedFacilityType() {
  return facilityTypeSelect.value === '__custom__' 
    ? newFacilityTypeInput.value.trim() 
    : facilityTypeSelect.value;
}

// Function to download sample Excel template
function downloadSampleExcel() {
    // Sample data
    const data = [
        { name: 'C001', name2: 'Room 101', type: 'Classroom', capacity: 75, buildname: 'Main Building', build_code: 'MB101' },
        { name: 'C002', name2: 'Room 102', type: 'Classroom', capacity: 75, buildname: 'Main Building', build_code: 'MB102' },
        { name: 'C003', name2: 'Auditorium', type: 'Auditorium', capacity: 200, buildname: 'Convention Center', build_code: 'CC101' },
        { name: 'L001', name2: 'Chemistry Lab', type: 'Laboratory', capacity: 30, buildname: 'Science Building', build_code: 'SB201' },
        { name: 'L002', name2: 'Physics Lab', type: 'Laboratory', capacity: 25, buildname: 'Science Building', build_code: 'SB202' }
    ];

    // Create workbook and worksheet
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.json_to_sheet(data);
    
    // Add worksheet to workbook
    XLSX.utils.book_append_sheet(wb, ws, "Facilities");
    
    // Generate Excel file and trigger download
    XLSX.writeFile(wb, "facilities_template.xlsx");
}

document.getElementById('uploadForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    console.log("Form submitted");

    const fileInput = document.getElementById('excelFile');
    const siteId = document.getElementById('site_id').value;

    if (fileInput.files.length === 0) {
        console.log("No file selected");
        Swal.fire('Error', 'Please select an Excel file.', 'error');
        return;
    }

    const file = fileInput.files[0];
    const reader = new FileReader();

    reader.onload = async function (e) {
        try {
            console.log("File loaded, parsing Excel data...");
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, { type: 'array' });
            const sheet = workbook.Sheets[workbook.SheetNames[0]];
            const rows = XLSX.utils.sheet_to_json(sheet, { header: 1 });

            console.log("Raw Excel Rows:", rows);

            if (rows.length < 2) {
                console.log("Excel only has header or no data");
                Swal.fire('Error', 'Excel file is empty or has no data rows (only header found).', 'error');
                return;
            }

            // Get header row and remove it
            const headers = rows[0].map(h => String(h).toLowerCase().trim());
            console.log("Headers found:", headers);
            rows.shift();

            // Find column indices
            const nameIndex = headers.findIndex(h => h.includes('name') && !h.includes('name2'));
            const name2Index = headers.findIndex(h => h.includes('name2'));
            const typeIndex = headers.findIndex(h => h.includes('type'));
            const capacityIndex = headers.findIndex(h => h.includes('capacity'));
            const buildNameIndex = headers.findIndex(h => h.includes('buildname') || h.includes('building name'));
            const buildCodeIndex = headers.findIndex(h => h.includes('build_code') || h.includes('building code'));

            console.log("Column indices - Name:", nameIndex, "Name2:", name2Index, "Type:", typeIndex, 
                        "Capacity:", capacityIndex, "BuildName:", buildNameIndex, "BuildCode:", buildCodeIndex);

            if (nameIndex === -1 || typeIndex === -1 || capacityIndex === -1) {
                const missingColumns = [];
                if (nameIndex === -1) missingColumns.push('Name');
                if (typeIndex === -1) missingColumns.push('Type');
                if (capacityIndex === -1) missingColumns.push('Capacity');
                
                Swal.fire('Error', `Missing required columns: ${missingColumns.join(', ')}. Please ensure your Excel file has columns for Name, Type, and Capacity.`, 'error');
                return;
            }

            const facilities = rows
                .filter(row => row && row.length > Math.max(nameIndex, typeIndex, capacityIndex, name2Index, buildNameIndex, buildCodeIndex))
                .map(row => {
                    const name = String(row[nameIndex] || '').trim();
                    const name2 = name2Index >= 0 ? String(row[name2Index] || '').trim() : '';
                    const type = String(row[typeIndex] || '').trim();
                    const capacity = parseInt(String(row[capacityIndex] || '0').replace(/[^0-9]/g, '')) || 0;
                    const buildname = buildNameIndex >= 0 ? String(row[buildNameIndex] || '').trim() : '';
                    const build_code = buildCodeIndex >= 0 ? String(row[buildCodeIndex] || '').trim() : '';
                    
                    return {
                        name: name,
                        name2: name2,
                        type: type,
                        capacity: capacity,
                        buildname: buildname,
                        build_code: build_code
                    };
                })
                .filter(f => f.name && f.type && !isNaN(f.capacity) && f.capacity > 0);

            console.log("Parsed Facilities:", facilities);

            if (facilities.length === 0) {
                console.log("No valid facility rows found");
                Swal.fire('Error', 'No valid data found in the Excel file. Please ensure your data has valid values for Name, Type, and Capacity columns, and that Capacity is a number greater than 0.', 'error');
                return;
            }

            console.log("Sending data to server...");
            
            // Get the site ID from the URL or form
            const urlParams = new URLSearchParams(window.location.search);
            const siteIdFromUrl = urlParams.get('site_id');
            const siteId = siteIdFromUrl || document.getElementById('site_id').value;
            
            if (!siteId) {
                throw new Error('Site ID is required');
            }
            
            console.log("Sending facilities:", facilities);
            console.log("Site ID:", siteId);
            
            try {
                const response = await fetch(`upload_facilities.php?site_id=${siteId}`, {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ facilities })
                });
                
                let result;
                const contentType = response.headers.get('content-type');
                
                if (contentType && contentType.includes('application/json')) {
                    result = await response.json();
                } else {
                    const text = await response.text();
                    console.error('Non-JSON response:', text);
                    throw new Error('Server returned an invalid response. Please check the server logs.');
                }
                
                console.log("Server Response:", result);

                if (result.success) {
                    Swal.fire({
                        title: 'Success',
                        html: result.message,
                        icon: 'success'
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        html: result.message || 'An error occurred while saving facilities',
                        icon: 'error'
                    });
                }
            } catch (err) {
                console.error("Error:", err);
                Swal.fire({
                    title: 'Error',
                    text: 'Failed to process the request. Please check the console for details.',
                    icon: 'error'
                });
            }
        } catch (err) {
            console.error("Excel Parsing Error:", err);
            Swal.fire('Error', 'Failed to process the Excel file.', 'error');
        }
    };

    reader.onerror = function (err) {
        console.error("File read error:", err);
        Swal.fire('Error', 'Unable to read the file.', 'error');
    };

    reader.readAsArrayBuffer(file);
});
</script>

    <script>
    // Handle select all checkbox
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
      selectAllCheckbox.addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.facility-checkbox');
        checkboxes.forEach(checkbox => {
          checkbox.checked = this.checked;
        });
        updateDeleteButtonState();
      });
    }

    // Handle individual checkbox changes
    document.addEventListener('change', function(e) {
      if (e.target && e.target.matches('.facility-checkbox')) {
        const checkboxes = document.querySelectorAll('.facility-checkbox');
        const allChecked = checkboxes.length === document.querySelectorAll('.facility-checkbox:checked').length;
        const selectAll = document.getElementById('selectAll');
        if (selectAll) {
          selectAll.checked = allChecked;
        }
        updateDeleteButtonState();
      }
    });

    // Update delete button state
    function updateDeleteButtonState() {
      const deleteBtn = document.getElementById('deleteSelectedBtn');
      if (deleteBtn) {
        const anyChecked = document.querySelectorAll('.facility-checkbox:checked').length > 0;
        deleteBtn.disabled = !anyChecked;
      }
    }

    // Handle delete selected button click
    document.getElementById('deleteSelectedBtn')?.addEventListener('click', function() {
      const form = document.getElementById('deleteFacilitiesForm');
      const selectedCount = document.querySelectorAll('.facility-checkbox:checked').length;
      
      if (selectedCount === 0) return;

      Swal.fire({
        title: 'Are you sure?',
        text: `You are about to delete ${selectedCount} facility(ies). This action cannot be undone!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete them!'
      }).then((result) => {
        if (result.isConfirmed) {
          // Add CSRF token if needed
          const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
          if (csrfToken) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'csrf_token';
            input.value = csrfToken;
            form.appendChild(input);
          }
          
          form.submit();
        }
      });
    });

    // Handle delete all facilities for site
    document.getElementById('deleteAllFacilities')?.addEventListener('click', function(e) {
      e.preventDefault();
      
      Swal.fire({
        title: 'Delete All Facilities',
        text: 'Are you sure you want to delete ALL facilities for this site? This action cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete all!'
      }).then((result) => {
        if (result.isConfirmed) {
          const form = document.createElement('form');
          form.method = 'POST';
          form.action = 'delete_facilities.php';
          
          // Add site_id
          const siteIdInput = document.createElement('input');
          siteIdInput.type = 'hidden';
          siteIdInput.name = 'site_id';
          siteIdInput.value = '<?php echo isset($site_id) ? $site_id : 0; ?>';
          form.appendChild(siteIdInput);
          
          // Add delete_all flag
          const deleteAllInput = document.createElement('input');
          deleteAllInput.type = 'hidden';
          deleteAllInput.name = 'delete_all';
          deleteAllInput.value = '1';
          form.appendChild(deleteAllInput);
          
          // Add CSRF token if needed
          const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
          if (csrfToken) {
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = csrfToken;
            form.appendChild(csrfInput);
          }
          
          document.body.appendChild(form);
          form.submit();
        }
      });
    });
    </script>

    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    
    <script>
    // Handle edit facility button click
    document.addEventListener('click', function(e) {
        if (e.target && e.target.closest('.edit-facility')) {
            const button = e.target.closest('.edit-facility');
            const modal = new bootstrap.Modal(document.getElementById('updateFacilityModal'));
            
            // Set facility data to the form
            document.getElementById('updateFacilityId').value = button.dataset.id;
            document.getElementById('updateFacilityCode').value = button.dataset.name;
            document.getElementById('updateFacilityName').value = button.dataset.name2 || '';
            document.getElementById('updateFacilityType').value = button.dataset.type || '';
            document.getElementById('updateFacilityCapacity').value = button.dataset.capacity || '';
            document.getElementById('updateFacilityBuilding').value = button.dataset.buildname || '';
            document.getElementById('updateFacilityBuildingCode').value = button.dataset.buildcode || '';
            
            // Show the modal
            modal.show();
        }
    });

    // Handle update facility type selection
    const updateFacilityTypeSelect = document.getElementById('updateFacilityType');
    const updateNewFacilityTypeInput = document.getElementById('updateNewFacilityType');

    updateFacilityTypeSelect?.addEventListener('change', function() {
        if (this.value === '__custom__') {
            this.classList.add('d-none');
            updateNewFacilityTypeInput.classList.remove('d-none');
            updateNewFacilityTypeInput.focus();
            updateNewFacilityTypeInput.required = true;
        } else {
            updateNewFacilityTypeInput.classList.add('d-none');
            updateNewFacilityTypeInput.required = false;
        }
    });

    // Handle update facility form submission
    document.getElementById('updateFacilityForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const facilityId = document.getElementById('updateFacilityId').value;
        const siteId = document.getElementById('updateFacilitySiteId').value;
        const code = document.getElementById('updateFacilityCode').value.trim();
        const name = document.getElementById('updateFacilityName').value.trim();
        let type = updateFacilityTypeSelect.value;
        const capacity = document.getElementById('updateFacilityCapacity').value;
        const building = document.getElementById('updateFacilityBuilding').value.trim();
        const buildingCode = document.getElementById('updateFacilityBuildingCode').value.trim();

        // Handle custom type
        if (type === '__custom__') {
            type = updateNewFacilityTypeInput.value.trim();
            if (!type) {
                Swal.fire('Error', 'Please enter a facility type', 'error');
                return;
            }
        }

        if (!code || !type || !capacity) {
            Swal.fire('Error', 'Please fill in all required fields', 'error');
            return;
        }

        try {
            const response = await fetch('update_facility.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: facilityId,
                    site_id: siteId,
                    name: code,
                    name2: name,
                    type: type,
                    capacity: parseInt(capacity),
                    buildname: building,
                    build_code: buildingCode,
                    campus_id: 1 // Add default campus_id
                })
            });

            // First check if the response is ok (status 200-299)
            if (!response.ok) {
                const errorText = await response.text();
                console.error('Server responded with status:', response.status, errorText);
                throw new Error(`Server error: ${response.status} ${response.statusText}\n${errorText}`);
            }

            // Try to parse as JSON, but handle case where response is not JSON
            let result;
            const responseText = await response.text();
            try {
                result = responseText ? JSON.parse(responseText) : {};
            } catch (e) {
                console.error('Failed to parse JSON response:', responseText);
                throw new Error('Invalid response from server');
            }
            
            if (result.success) {
                await Swal.fire({
                    title: 'Success',
                    text: 'Facility updated successfully!',
                    icon: 'success'
                });
                location.reload();
            } else {
                throw new Error(result.message || 'Failed to update facility');
            }
        } catch (error) {
            console.error('Error updating facility:', error);
            await Swal.fire({
                title: 'Error',
                html: `An error occurred while updating the facility:<br><br>${error.message || 'Unknown error'}`,
                icon: 'error'
            });
        }
    });
    </script>
    </body>
</html>
