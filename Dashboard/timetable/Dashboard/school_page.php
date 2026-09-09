<?php
session_start();
include('connection.php');

$school_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($school_id <= 0) {
    http_response_code(400);
    echo 'Invalid school id';
    exit;
}

/* ---- Fetch school (+ optional college/campus for header) ---- */
// First, update the school query to include college and campus IDs
$school = [];
$stmt = $connection->prepare("
    SELECT s.*, c.name AS college_name, camp.name AS campus_name, 
           c.id as college_id, camp.id as campus_id
    FROM school s
    LEFT JOIN college c ON s.college_id = c.id
    LEFT JOIN campus camp ON c.campus_id = camp.id
    WHERE s.id = ?
");
if ($stmt) {
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $school = $res->fetch_assoc();
}

// Fetch all departments for this school
$departments = [];
$stmt = $connection->prepare("SELECT * FROM department WHERE school_id = ? ORDER BY name");
if ($stmt) {
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $departments[$row['id']] = $row;
    }
}

// Prepare department options for selects
$departmentOptions = ['' => '-- No Department --'];
foreach ($departments as $dept) {
    $departmentOptions[$dept['id']] = $dept['name'];
}

/* ---- AJAX API (same file) ---- */
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    // helper: normalize strings (trim + collapse spaces)
    $normalize = function ($s) {
        $s = trim((string)$s);
        $s = preg_replace('/\s+/', ' ', $s);
        return $s;
    };

    // existence checks (within this school) — by name or code (case-insensitive)
    $existsByName = $connection->prepare("SELECT id FROM program WHERE school_id = ? AND LOWER(name) = LOWER(?) LIMIT 1");
    $existsByCode = $connection->prepare("SELECT id FROM program WHERE school_id = ? AND code IS NOT NULL AND LOWER(code) = LOWER(?) LIMIT 1");

    try {
        switch ($action) {
            case 'list_programs': {
                $list = [];
                $q = $connection->prepare("SELECT p.id, p.name, p.code, d.name as department_name, d.id as department_id 
                                       FROM program p 
                                       LEFT JOIN department d ON p.department_id = d.id 
                                       WHERE p.school_id = ? 
                                       ORDER BY p.name");
                $q->bind_param("i", $school_id);
                $q->execute();
                $rs = $q->get_result();
                while ($row = $rs->fetch_assoc()) { 
                    $list[] = [
                        'id' => $row['id'],
                        'name' => $row['name'],
                        'code' => $row['code'],
                        'department' => $row['department_name'] ? [
                            'id' => $row['department_id'],
                            'name' => $row['department_name']
                        ] : null
                    ];
                }
                echo json_encode(['success' => true, 'data' => $list]);
                break;
            }

            case 'add_program': {
                $name = $normalize($_POST['name'] ?? '');
                $code = $normalize($_POST['code'] ?? '');
                if ($name === '') {
                    echo json_encode(['success' => false, 'message' => 'Program name is required']);
                    break;
                }
                // uniqueness: name or (if provided) code
                $existsByName->bind_param('is', $school_id, $name);
                $existsByName->execute();
                if ($existsByName->get_result()->fetch_row()) {
                    echo json_encode(['success' => false, 'message' => 'A program with this name already exists in this school']);
                    break;
                }
                if ($code !== '') {
                    $existsByCode->bind_param('is', $school_id, $code);
                    $existsByCode->execute();
                    if ($existsByCode->get_result()->fetch_row()) {
                        echo json_encode(['success' => false, 'message' => 'A program with this code already exists in this school']);
                        break;
                    }
                }
                $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
                if ($department_id === 0) $department_id = null;
                
                $ins = $connection->prepare("INSERT INTO program (name, code, school_id, department_id) VALUES (?, ?, ?, ?)");
                $codeParam = ($code === '' ? null : $code);
                $ins->bind_param('ssii', $name, $codeParam, $school_id, $department_id);
                $ins->execute();
                echo json_encode(['success' => true, 'message' => 'Program added']);
                break;
            }

            case 'edit_program': {
                $id   = (int)($_POST['id'] ?? 0);
                $name = $normalize($_POST['name'] ?? '');
                $code = $normalize($_POST['code'] ?? '');
                if ($id <= 0 || $name === '') {
                    echo json_encode(['success' => false, 'message' => 'Invalid payload']);
                    break;
                }
                // unique name within school excluding self
                $q = $connection->prepare("SELECT id FROM program WHERE school_id = ? AND LOWER(name) = LOWER(?) AND id <> ? LIMIT 1");
                $q->bind_param('isi', $school_id, $name, $id);
                $q->execute();
                if ($q->get_result()->fetch_row()) {
                    echo json_encode(['success' => false, 'message' => 'Another program with this name exists']);
                    break;
                }
                if ($code !== '') {
                    $q2 = $connection->prepare("SELECT id FROM program WHERE school_id = ? AND LOWER(code) = LOWER(?) AND id <> ? LIMIT 1");
                    $q2->bind_param('isi', $school_id, $code, $id);
                    $q2->execute();
                    if ($q2->get_result()->fetch_row()) {
                        echo json_encode(['success' => false, 'message' => 'Another program with this code exists']);
                        break;
                    }
                }
                $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
                if ($department_id === 0) $department_id = null;
                
                $up = $connection->prepare("UPDATE program SET name = ?, code = ?, department_id = ? WHERE id = ? AND school_id = ?");
                $codeParam = ($code === '' ? null : $code);
                $up->bind_param('ssiii', $name, $codeParam, $department_id, $id, $school_id);
                $up->execute();
                echo json_encode(['success' => true, 'message' => 'Program updated']);
                break;
            }

            case 'delete_program': {
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid id']);
                    break;
                }
                $del = $connection->prepare("DELETE FROM program WHERE id = ? AND school_id = ?");
                $del->bind_param('ii', $id, $school_id);
                $del->execute();
                echo json_encode(['success' => true, 'message' => 'Program deleted']);
                break;
            }

            case 'delete_all': {
                $del = $connection->prepare("DELETE FROM program WHERE school_id = ?");
                $del->bind_param('i', $school_id);
                $del->execute();
                echo json_encode(['success' => true, 'message' => 'All programs deleted']);
                break;
            }

            case 'check_existing': {
                // Input: JSON array of items [{name, code?}]
                $raw = $_POST['items'] ?? '[]';
                $items = json_decode($raw, true);
                if (!is_array($items)) $items = [];
                $out = [];
                $checkName = $connection->prepare("SELECT id FROM program WHERE school_id = ? AND LOWER(name) = LOWER(?) LIMIT 1");
                $checkCode = $connection->prepare("SELECT id FROM program WHERE school_id = ? AND code IS NOT NULL AND LOWER(code) = LOWER(?) LIMIT 1");
                foreach ($items as $it) {
                    $nm = $normalize($it['name'] ?? '');
                    $cd = $normalize($it['code'] ?? '');
                    if ($nm === '' && $cd === '') continue;
                    $exists = false; $by = null; $id = null;

                    if ($nm !== '') {
                        $checkName->bind_param('is', $school_id, $nm);
                        $checkName->execute();
                        $r = $checkName->get_result()->fetch_assoc();
                        if ($r) { $exists = true; $by = 'name'; $id = $r['id']; }
                    }
                    if (!$exists && $cd !== '') {
                        $checkCode->bind_param('is', $school_id, $cd);
                        $checkCode->execute();
                        $r2 = $checkCode->get_result()->fetch_assoc();
                        if ($r2) { $exists = true; $by = 'code'; $id = $r2['id']; }
                    }

                    $out[] = [
                        'name' => $nm, 'code' => ($cd === '' ? null : $cd),
                        'exists' => $exists, 'by' => $by, 'id' => $id
                    ];
                }
                echo json_encode(['success' => true, 'data' => $out]);
                break;
            }

            case 'bulk_add': {
                $raw = $_POST['items'] ?? '[]';
                $items = json_decode($raw, true);
                if (!is_array($items)) $items = [];

                // Normalize + dedupe by (name lower) then by (code lower if present)
                $normalized = [];
                foreach ($items as $it) {
                    $nm = $normalize($it['name'] ?? '');
                    $cd = $normalize($it['code'] ?? '');
                    if ($nm === '' && $cd === '') continue;
                    $key = strtolower($nm) . '|' . strtolower($cd);
                    $normalized[$key] = ['name'=>$nm, 'code'=>$cd];
                }
                $toInsert = array_values($normalized);

                $inserted = 0; $skipped = 0; $errors = 0; $skippedItems = [];
                $ins = $connection->prepare("INSERT INTO program (name, code, school_id, department_id) VALUES (?, ?, ?, ?)");
                foreach ($toInsert as $it) {
                    $nm = $it['name'];
                    $cd = $it['code'];

                    // skip if exists by name or (if provided) code
                    $existsByName->bind_param('is', $school_id, $nm);
                    $existsByName->execute();
                    if ($existsByName->get_result()->fetch_row()) {
                        $skipped++; $skippedItems[] = $it; continue;
                    }
                    if ($cd !== '') {
                        $existsByCode->bind_param('is', $school_id, $cd);
                        $existsByCode->execute();
                        if ($existsByCode->get_result()->fetch_row()) {
                            $skipped++; $skippedItems[] = $it; continue;
                        }
                    }
                    $codeParam = ($cd === '' ? null : $cd);
                    $department_id = null; // No department in bulk import for now
                    $ins->bind_param('ssii', $nm, $codeParam, $school_id, $department_id);
                    if ($ins->execute()) {
                        $inserted++;
                    } else {
                        $errors++;
                        error_log("Error inserting program: " . $connection->error);
                    }
                }
                echo json_encode([
                    'success' => true,
                    'message' => 'Bulk add completed',
                    'inserted' => $inserted, 'skipped' => $skipped, 'errors' => $errors,
                    'skippedItems' => $skippedItems
                ]);
                break;
            }

            case 'list_departments': {
                $list = [];
                $q = $connection->prepare("SELECT * FROM department WHERE school_id = ? ORDER BY name");
                $q->bind_param("i", $school_id);
                $q->execute();
                $rs = $q->get_result();
                while ($row = $rs->fetch_assoc()) { 
                    $list[] = $row;
                }
                echo json_encode(['success' => true, 'data' => $list]);
                break;
            }
            
            case 'add_department': {
                $name = $normalize($_POST['name'] ?? '');
                if ($name === '') {
                    echo json_encode(['success' => false, 'message' => 'Department name is required']);
                    break;
                }
                
                // Check if department with same name exists in this school
                $check = $connection->prepare("SELECT id FROM department WHERE school_id = ? AND LOWER(name) = LOWER(?)");
                $check->bind_param('is', $school_id, $name);
                $check->execute();
                if ($check->get_result()->num_rows > 0) {
                    echo json_encode(['success' => false, 'message' => 'A department with this name already exists in this school']);
                    break;
                }
                
                $ins = $connection->prepare("INSERT INTO department (name, school_id) VALUES (?, ?)");
                $ins->bind_param('si', $name, $school_id);
                $ins->execute();
                echo json_encode(['success' => true, 'message' => 'Department added', 'id' => $connection->insert_id]);
                break;
            }
            
            case 'edit_department': {
                $id = (int)($_POST['id'] ?? 0);
                $name = $normalize($_POST['name'] ?? '');
                
                if ($id <= 0 || $name === '') {
                    echo json_encode(['success' => false, 'message' => 'Invalid payload']);
                    break;
                }
                
                // Check if another department with same name exists in this school
                $check = $connection->prepare("SELECT id FROM department WHERE school_id = ? AND LOWER(name) = LOWER(?) AND id != ?");
                $check->bind_param('isi', $school_id, $name, $id);
                $check->execute();
                if ($check->get_result()->num_rows > 0) {
                    echo json_encode(['success' => false, 'message' => 'Another department with this name already exists']);
                    break;
                }
                
                $up = $connection->prepare("UPDATE department SET name = ? WHERE id = ? AND school_id = ?");
                $up->bind_param('sii', $name, $id, $school_id);
                $up->execute();
                echo json_encode(['success' => true, 'message' => 'Department updated']);
                break;
            }
            
            case 'delete_department': {
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid id']);
                    break;
                }
                
                // Check if department has programs
                $check = $connection->prepare("SELECT COUNT(*) as count FROM program WHERE department_id = ?");
                $check->bind_param('i', $id);
                $check->execute();
                $count = $check->get_result()->fetch_assoc()['count'];
                
                if ($count > 0) {
                    echo json_encode(['success' => false, 'message' => 'Cannot delete department with assigned programs']);
                    break;
                }
                
                $del = $connection->prepare("DELETE FROM department WHERE id = ? AND school_id = ?");
                $del->bind_param('ii', $id, $school_id);
                $del->execute();
                echo json_encode(['success' => true, 'message' => 'Department deleted']);
                break;
            }
            
            default:
                echo json_encode(['success' => false, 'message' => 'Unknown action']);
        }
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title><?= htmlspecialchars($school['name']) ?> – Programs</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="assets/img/icon1.png" rel="icon" />
    <meta content="" name="description">
    <meta content="" name="keywords">
    <link href="assets/img/icon1.png" rel="apple-touch-icon">
    <link href="https://fonts.gstatic.com" rel="preconnect">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">

    <!-- CDN UI libs (same stack as your college page) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.min.css" rel="stylesheet" />
    <link href="https://cdn.datatables.net/v/bs5/dt-2.0.8/datatables.min.css" rel="stylesheet" />

    <style>
        body { background:#f7f8fa; }
        .card { box-shadow:0 2px 10px rgba(0,0,0,0.06); border:0; }
        .preview-badge { font-size: .8rem; }
        .file-drop { border:2px dashed #ced4da; border-radius: .5rem; padding:1.25rem; text-align:center; background:#fff; }
        .file-drop.dragover { border-color:#0d6efd; background:#eef4ff; }
        .table td, .table th { vertical-align: middle; }
        .w-90 { width:90%; }
        .btn-primary,.btn-outline-primary { background-color: rgb(22, 50, 99); color:#fff; }
        .btn-primary:hover,.btn-outline-primary:hover { background-color: rgb(147, 144, 204); }
        .btn-danger,.btn-outline-danger { background-color: rgb(207, 93, 0); color:#fff; }
        .btn-danger:hover,.btn-outline-danger:hover { background-color: rgb(207, 4, 4); }

        h5 { font-size: 17px !important; background-color: rgb(22, 50, 99) !important; color:#fff !important; padding: 6px !important; margin-bottom: 10px !important; border-radius: 5px !important; }
        h4 { font-size: 17px !important; background-color: rgb(22, 50, 99) !important; color:#fff !important; padding: 6px !important; margin-bottom: 10px !important; border-radius: 5px !important; }
        h2 { font-size: 17px !important; background-color: rgb(22, 50, 99) !important; color:#fff !important; padding: 6px !important; margin-bottom: 10px !important; border-radius: 5px !important; }
        th { font-size: 17px !important; background-color: rgb(22, 50, 99) !important; color:#fff !important; padding: 6px !important; margin-bottom: 10px !important; border-radius: 5px !important; }
       /* program defalt link color */
       table a { color: rgb(0, 2, 5) !important; }
        
    
        
    </style>
</head>
<body>
<?php include('./includes/header.php'); ?>
<?php include('./includes/menu.php'); ?>

<main id="main" class="main">
    <div class="p-4">
        <div class="card school_details p-2 gap-2 mb-3">
            <div class="description">
                <h4 class="mb-0">School: <?= htmlspecialchars($school['name']) ?></h4>
                <small class="">
                    <?php if(!empty($school['college_name'])): ?>
                        College: <?= htmlspecialchars($school['college_name']) ?><?= !empty($school['campus_name']) ? ' – Campus: '.htmlspecialchars($school['campus_name']) : '' ?>
                    <?php endif; ?>
                </small>
            </div>
            <div class="text-end">
                <button class="btn btn-outline-primary me-2 btn-sm" data-bs-toggle="modal" data-bs-target="#departmentModal">
                    <i class="bi bi-building"></i> Manage Departments
                </button>
                <button id="btnDeleteAll" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash3"></i> Delete All Programs</button>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card p-3">
                    <h5 class="mb-3">Add New Program</h5>
                    <form id="formAdd">
                        <div class="mb-3">
                            <label class="form-label">Program name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="addName" placeholder="e.g., BSc Computer Science" required />
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Program code (optional)</label>
                            <input type="text" class="form-control" id="addCode" placeholder="e.g., CS101" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Department (optional)</label>
                            <select class="form-select" id="addDepartment">
                                <option value="">-- No Department --</option>
                                <?php foreach($departments as $dept): ?>
                                    <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="btn btn-primary" type="submit"><i class="bi bi-plus-lg"></i> Add</button>
                    </form>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card p-3">
                    <h5 class="mb-3">Upload Programs (Excel)</h5>
                    <div id="dropArea" class="file-drop mb-3">
                        <div class="mb-2"><i class="bi bi-file-earmark-spreadsheet" style="font-size:2rem;"></i></div>
                        <div>Drop .xlsx/.xls here or
                            <label class="text-primary text-decoration-underline" style="cursor:pointer">
                                <input type="file" id="excelInput" accept=".xlsx,.xls" hidden />browse
                            </label>
                        </div>
                        <div class="small text-muted mt-1">
                            Expected columns: <code>name</code> and optional <code>code</code> (headers can be <i>Name</i>, <i>Program Name</i>, <i>Code</i>, etc.)
                        </div>
                    </div>

                    <div class="d-flex gap-2 mb-2">
                        <button id="btnCheck" class="btn btn-outline-secondary" disabled>
                            <i class="bi bi-search"></i> Check against existing
                        </button>
                        <button id="btnImport" class="btn btn-success" disabled>
                            <i class="bi bi-cloud-upload"></i> Import new programs
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table id="previewTable" class="table table-sm table-bordered align-middle mb-0">
                            <thead>
                                <tr><th>#</th><th>Program Name</th><th>Code</th><th>Status</th></tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>

        <hr class="my-4" />

        <h5 class="mb-3">Programs in this School</h5>
        <div class="card table-responsive p-3">
            <table id="programsTable" class="table table-bordered table-striped w-100">
                <thead>
                    <tr>
                        <th style="width:35%">Program Name</th>
                        <th style="width:20%">Code</th>
                        <th style="width:25%">Department</th>
                        <th style="width:20%">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

    </div>
</main>

<!-- Department Management Modal -->
<div class="modal fade" id="departmentModal" tabindex="-1" aria-labelledby="departmentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="departmentModalLabel">Manage Departments</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="card mb-4">
          <div class="card-header">
            <h6 class="mb-0">Add New Department</h6>
          </div>
          <div class="card-body">
            <form id="addDepartmentForm">
              <div class="input-group">
                <input type="text" class="form-control" id="newDepartmentName" placeholder="Department name" required>
                <button class="btn btn-primary" type="submit">Add</button>
              </div>
            </form>
          </div>
        </div>
        
        <div class="table-responsive">
          <table class="table table-bordered" id="departmentsTable">
            <thead>
              <tr>
                <th>Department Name</th>
                <th style="width: 120px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <!-- Departments will be loaded here -->
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Program</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="formEdit">
            <input type="hidden" id="editId" />
            <div class="mb-3">
                <label class="form-label">Program name <span class="text-danger">*</span></label>
                <input type="text" id="editName" class="form-control" required />
            </div>
            <div class="mb-3">
                <label class="form-label">Program code (optional)</label>
                <input type="text" id="editCode" class="form-control" />
            </div>
            <div class="mb-3">
                <label class="form-label">Department (optional)</label>
                <select class="form-select" id="editDepartment">
                    <option value="">-- No Department --</option>
                    <?php foreach($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" id="btnSaveEdit" class="btn btn-primary">Save changes</button>
      </div>
    </div>
  </div>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.all.min.js"></script>
<!-- DataTables -->
<script src="https://cdn.datatables.net/v/bs5/dt-2.0.8/datatables.min.js"></script>
<!-- SheetJS -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

<script>
(function(){
  const schoolId = <?= (int)$school_id ?>;
  let dt;
  let previewRows = []; // {name, code, exists:null|true|false, by:'name'|'code'|null}

  function api(action, data) {
    return $.ajax({
      url: `?id=${schoolId}&ajax=1&action=${encodeURIComponent(action)}`,
      method: 'POST',
      data,
      dataType: 'json'
    });
  }

  function toast(icon, title) {
    Swal.fire({toast:true, icon, title, position:'top-end', timer:2500, showConfirmButton:false});
  }

  function escapeHtml(s){ return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
  function escapeAttr(s){ return String(s).replace(/["']/g, c => ({'"':'&quot;','\'':'&#39;'}[c])); }

  function loadPrograms() {
    api('list_programs').done(res => {
      if (!res.success) return toast('error', res.message||'Failed');
      const rows = res.data || [];
      if (dt) { dt.clear().destroy(); }
      const tbody = $('#programsTable tbody').empty();
      rows.forEach(r => {
        const tr = $(`
          <tr>
            <td>
              <a href="program_management.php?program_id=${r.id}&program_name=${encodeURIComponent(r.name || '')}" class="text-primary text-decoration-none">
                ${escapeHtml(r.name ?? '')}
              </a>
            </td>
            <td>${escapeHtml(r.code ?? '')}</td>
            <td>${r.department ? escapeHtml(r.department.name) : '<span class="text-muted">None</span>'}</td>
            <td>
              <button class="btn btn-sm btn-primary me-2 btn-edit"
                data-id="${r.id}" 
                data-name="${escapeAttr(r.name ?? '')}" 
                data-code="${escapeAttr(r.code ?? '')}"
                data-department-id="${r.department ? r.department.id : ''}">
                <i class="bi bi-pencil-square"></i> Edit
              </button>
              <button class="btn btn-sm btn-danger btn-del" data-id="${r.id}">
                <i class="bi bi-trash"></i> Delete
              </button>
            </td>
          </tr>
        `);
        tbody.append(tr);
      });
      dt = new DataTable('#programsTable', { pageLength: 10, order: [], responsive: true });
    });
  }

  // Add single program
  $('#formAdd').on('submit', function(e){
    e.preventDefault();
    const name = $('#addName').val().trim().replace(/\s+/g,' ');
    const code = $('#addCode').val().trim().replace(/\s+/g,' ');
    const department_id = $('#addDepartment').val() || null;
    if (!name) return toast('warning','Enter a program name');
    api('add_program', {name, code, department_id}).done(res => {
      if (res.success) {
        $('#addName').val(''); $('#addCode').val('');
        toast('success','Program added'); 
        loadPrograms();
        loadDepartments(); // Refresh department list in case it was updated
      } else toast('error', res.message||'Failed');
    });
  });

  // Edit
  let editModal = new bootstrap.Modal(document.getElementById('editModal'));
  $(document).on('click', '.btn-edit', function(){
    const deptId = $(this).data('department-id') || '';
    $('#editId').val($(this).data('id'));
    $('#editName').val($(this).data('name'));
    $('#editCode').val($(this).data('code') || '');
    $('#editDepartment').val(deptId);
    editModal.show();
  });
  $('#btnSaveEdit').on('click', function(){
    const id   = $('#editId').val();
    const name = $('#editName').val().trim().replace(/\s+/g,' ');
    const code = $('#editCode').val().trim().replace(/\s+/g,' ');
    const department_id = $('#editDepartment').val() || null;
    if (!name) return toast('warning','Enter a program name');
    api('edit_program', {id, name, code, department_id}).done(res => {
      if (res.success) { toast('success','Program updated'); editModal.hide(); loadPrograms(); }
      else toast('error', res.message||'Failed');
    });
  });

  // Delete single
  $(document).on('click', '.btn-del', function(){
    const id = $(this).data('id');
    Swal.fire({title:'Delete this program?', icon:'warning', showCancelButton:true, confirmButtonText:'Delete'}).then(r=>{
      if (r.isConfirmed) {
        api('delete_program', {id}).done(res=>{
          if (res.success) { toast('success','Deleted'); loadPrograms(); }
          else toast('error', res.message||'Failed');
        });
      }
    });
  });

  // Delete all
  $('#btnDeleteAll').on('click', function(){
    Swal.fire({
      title:'Delete ALL programs in this school?',
      text:'This cannot be undone',
      icon:'warning', showCancelButton:true, confirmButtonText:'Delete all'
    }).then(r=>{
      if (r.isConfirmed) {
        api('delete_all').done(res=>{
          if (res.success) { toast('success','All deleted'); loadPrograms(); }
          else toast('error', res.message||'Failed');
        });
      }
    });
  });

  // ---- Excel upload + preview ----
  const drop = $('#dropArea');
  const fileInput = $('#excelInput');

  drop.on('dragover', function(e){ e.preventDefault(); e.originalEvent.dataTransfer.dropEffect = 'copy'; $(this).addClass('dragover'); });
  drop.on('dragleave dragend', function(){ $(this).removeClass('dragover'); });
  drop.on('drop', function(e){ e.preventDefault(); $(this).removeClass('dragover'); handleFile(e.originalEvent.dataTransfer.files[0]); });
  fileInput.on('change', function(){ if (this.files[0]) handleFile(this.files[0]); });

  function handleFile(file){
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(e){
      const data = new Uint8Array(e.target.result);
      const wb = XLSX.read(data, {type:'array'});
      const ws = wb.Sheets[wb.SheetNames[0]];
      const json = XLSX.utils.sheet_to_json(ws, {defval:'', raw:true});

      // Accept headers like: name / Name / Program Name ; code / Code / Program Code
      const items = [];
      json.forEach(row => {
        let nm = row.name || row.Name || row['Program Name'] || row['program name'] || '';
        let cd = row.code || row.Code || row['Program Code'] || row['program code'] || '';
        if (typeof nm !== 'string') nm = String(nm);
        if (typeof cd !== 'string') cd = String(cd);
        nm = nm.trim().replace(/\s+/g,' ');
        cd = cd.trim().replace(/\s+/g,' ');
        if (nm || cd) items.push({name:nm, code:cd});
      });

      // Deduplicate client-side by (name lower, code lower)
      const seen = new Set();
      previewRows = [];
      items.forEach(it => {
        const key = (it.name||'').toLowerCase() + '|' + (it.code||'').toLowerCase();
        if (!seen.has(key)) { seen.add(key); previewRows.push({name:it.name, code:it.code, exists:null, by:null}); }
      });

      renderPreview();
      $('#btnCheck').prop('disabled', previewRows.length===0);
      $('#btnImport').prop('disabled', true);
    };
    reader.readAsArrayBuffer(file);
  }

  function renderPreview(){
    const tbody = $('#previewTable tbody').empty();
    (previewRows || []).forEach((r, idx)=>{
      let badge = '<span class="badge text-bg-secondary preview-badge">unchecked</span>';
      if (r.exists === true) {
        const by = r.by === 'code' ? 'code' : 'name';
        badge = `<span class="badge text-bg-warning preview-badge">exists (${by})</span>`;
      } else if (r.exists === false) {
        badge = '<span class="badge text-bg-success preview-badge">new</span>';
      }
      tbody.append(`<tr>
        <td>${idx+1}</td>
        <td>${escapeHtml(r.name||'')}</td>
        <td>${escapeHtml(r.code||'')}</td>
        <td>${badge}</td>
      </tr>`);
    });
  }

  $('#btnCheck').on('click', function(){
    if (!previewRows.length) return;
    const items = previewRows.map(r=>({name:r.name, code:r.code}));
    api('check_existing', {items: JSON.stringify(items)}).done(res=>{
      if (!res.success) return toast('error', res.message||'Check failed');
      const map = new Map();
      (res.data||[]).forEach(x=>{
        const key = (x.name||'').toLowerCase() + '|' + (x.code||'').toLowerCase();
        map.set(key, {exists: !!x.exists, by: x.by||null});
      });
      previewRows = previewRows.map(r=>{
        const key = (r.name||'').toLowerCase() + '|' + (r.code||'').toLowerCase();
        const found = map.get(key);
        return {...r, exists: found ? found.exists : false, by: found ? found.by : null};
      });
      renderPreview();
      const anyNew = previewRows.some(r=>r.exists===false);
      $('#btnImport').prop('disabled', !anyNew);
      toast('success', 'Checked against existing');
    });
  });

  $('#btnImport').on('click', function(){
    const toImport = previewRows.filter(r=>r.exists===false).map(r=>({name:r.name, code:r.code}));
    if (!toImport.length) return toast('info','Nothing to import');
    api('bulk_add', {items: JSON.stringify(toImport)}).done(res=>{
      if (res.success) {
        toast('success', `Imported: ${res.inserted}, Skipped: ${res.skipped}`);
        loadPrograms();
        // Mark imported as exists now
        const set = new Set(toImport.map(it => (it.name||'').toLowerCase()+'|'+(it.code||'').toLowerCase()));
        previewRows = previewRows.map(r=>{
          const key = (r.name||'').toLowerCase()+'|'+(r.code||'').toLowerCase();
          return set.has(key) ? ({...r, exists:true, by:null}) : r;
        });
        renderPreview();
        $('#btnImport').prop('disabled', true);
      } else {
        toast('error', res.message||'Import failed');
      }
    });
  });

  // Department Management
  const departmentModal = new bootstrap.Modal(document.getElementById('departmentModal'));
  
  function loadDepartments() {
    api('list_departments').done(res => {
      if (!res.success) return;
      
      // Update department dropdowns
      const deptSelects = $('select[id$="Department"]').not('#departmentSelect');
      const currentValues = {};
      deptSelects.each(function() {
        currentValues[this.id] = $(this).val();
      });
      
      const defaultOption = '<option value="">-- No Department --</option>';
      const options = res.data.map(d => `<option value="${d.id}">${escapeHtml(d.name)}</option>`).join('');
      
      deptSelects.each(function() {
        const currentVal = currentValues[this.id];
        $(this).html(defaultOption + options);
        if (currentVal) $(this).val(currentVal);
      });
      
      // Update departments table
      const tbody = $('#departmentsTable tbody').empty();
      res.data.forEach(dept => {
        tbody.append(`
          <tr>
            <td>${escapeHtml(dept.name)}</td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-primary me-2 btn-edit-dept" 
                      data-id="${dept.id}" data-name="${escapeAttr(dept.name)}">
                <i class="bi bi-pencil"></i>
              </button>
              <button class="btn btn-sm btn-outline-danger btn-delete-dept" 
                      data-id="${dept.id}" data-name="${escapeAttr(dept.name)}">
                <i class="bi bi-trash"></i>
              </button>
            </td>
          </tr>
        `);
      });
    });
  }
  
  // Add department
  $('#addDepartmentForm').on('submit', function(e) {
    e.preventDefault();
    const name = $('#newDepartmentName').val().trim();
    if (!name) return toast('warning', 'Department name is required');
    
    api('add_department', {name}).done(res => {
      if (res.success) {
        $('#newDepartmentName').val('');
        loadDepartments();
        toast('success', 'Department added');
      } else {
        toast('error', res.message || 'Failed to add department');
      }
    });
  });
  
  // Edit department
  $(document).on('click', '.btn-edit-dept', function() {
    const id = $(this).data('id');
    const name = $(this).data('name');
    
    Swal.fire({
      title: 'Edit Department',
      input: 'text',
      inputValue: name,
      showCancelButton: true,
      confirmButtonText: 'Update',
      inputValidator: (value) => !value && 'Department name is required'
    }).then((result) => {
      if (result.isConfirmed) {
        api('edit_department', {id, name: result.value}).done(res => {
          if (res && res.success) {
            loadDepartments();
            toast('success', 'Department updated successfully');
          } else {
            const errorMsg = res && res.message ? res.message : 'Failed to update department';
            toast('error', errorMsg);
          }
        });
      }
    });
  });
  
  // Delete department
  $(document).on('click', '.btn-delete-dept', function() {
    const id = $(this).data('id');
    const name = $(this).data('name');
    
    Swal.fire({
      title: 'Delete Department?',
      text: `Are you sure you want to delete "${name}"?`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete it!',
      cancelButtonText: 'Cancel',
      confirmButtonColor: '#dc3545'
    }).then((result) => {
      if (result.isConfirmed) {
        api('delete_department', {id}).done(res => {
          if (res.success) {
            loadDepartments();
            toast('success', 'Department deleted');
          } else {
            toast('error', res.message || 'Failed to delete department');
          }
        });
      }
    });
  });

  // Init
  loadPrograms();
  loadDepartments();
})();
</script>
</body>
</html>
