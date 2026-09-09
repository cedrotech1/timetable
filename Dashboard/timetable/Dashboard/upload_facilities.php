<?php
session_start();
include("connection.php");

if (!isset($_SESSION['id'])) {
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
        $insertStmt = $connection->prepare("INSERT INTO facility (name, type, capacity, campus_id, site, buildname, build_code) VALUES (?, ?, ?, ?, ?, ?, ?)");

        $duplicates = [];

        foreach ($input['facilities'] as $fac) {
            $name = trim($fac['name'] ?? '');
            $type = trim($fac['type'] ?? '');
            $capacity = (int)($fac['capacity'] ?? 0);
            $buildName = trim($fac['buildname'] ?? '');
            $buildCode = trim($fac['build_code'] ?? '');

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

                $insertStmt->bind_param("ssiisss", $name, $type, $capacity, $campus_id, $site_id, $buildName, $buildCode);
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
            <a href=" .xlsx" class="btn btn-sm btn-success" download>
              <i class="bi bi-download"></i> Download Template
            </a>
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
    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">Existing Facilities</h5>
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
              <table class="table table-striped table-hover">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Capacity</th>
                    <th>Building</th>
                    <th>Building Code</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while ($facility = $facilities_result->fetch_assoc()): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($facility['name']); ?></td>
                      <td><?php echo htmlspecialchars($facility['type']); ?></td>
                      <td><?php echo htmlspecialchars($facility['capacity']); ?></td>
                      <td><?php echo htmlspecialchars($facility['buildname'] ?? 'N/A'); ?></td>
                      <td><?php echo htmlspecialchars($facility['build_code'] ?? 'N/A'); ?></td>
                    </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="alert alert-info mb-0">
              No facilities found for this site. Upload facilities using the form above.
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

<script>
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
                Swal.fire('Error', 'Excel file is empty or has no data.', 'error');
                return;
            }

            rows.shift();

            const facilities = rows
                .filter(row => row.length >= 3)
                .map(row => ({
                    name: row[0],
                    type: row[1],
                    capacity: parseInt(row[2]),
                    buildname: row[3] || '',
                    build_code: row[4] || ''
                }))
                .filter(f => f.name && f.type && !isNaN(f.capacity) && f.capacity > 0);

            console.log("Parsed Facilities:", facilities);

            if (facilities.length === 0) {
                console.log("No valid facility rows found");
                Swal.fire('Error', 'No valid data found in the Excel file.', 'error');
                return;
            }

            console.log("Sending data to server...");
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ facilities })
            });

            const text = await response.text();
            console.log("Raw server response text:", text);

            try {
                const result = JSON.parse(text);
                console.log("Server Response:", result);

                if (result.success) {
                    Swal.fire('Success', result.message, 'success').then(() => {
                        window.location.href = 'manage_sites.php';
                    });
                } else {
                    Swal.fire('Error', result.message, 'error');
                }
            } catch (err) {
                console.error("Failed to parse JSON from server response:", err);
                Swal.fire('Error', 'Server returned invalid response.', 'error');
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

    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    </body>
</html>
