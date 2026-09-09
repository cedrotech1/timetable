<?php
session_start();
include('connection.php');

$college_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($college_id <= 0) {
    http_response_code(400);
    echo 'Invalid college id';
    exit;
}

// ---- Fetch college + campus (for page header) ----
$college = [];
$stmt = $connection->prepare("SELECT c.*, camp.name AS campus_name FROM college c LEFT JOIN campus camp ON c.campus_id = camp.id WHERE c.id = ?");
$stmt->bind_param("i", $college_id);
$stmt->execute();
$res = $stmt->get_result();
$college = $res->fetch_assoc();
if (!$college) {
    http_response_code(404);
    echo 'College not found';
    exit;
}

// ---- AJAX API (same file) ----
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    // Helper: sanitize and normalize name (trim + collapse spaces)
    $normalize = function ($s) {
        $s = trim($s);
        $s = preg_replace('/\s+/', ' ', $s);
        return $s;
    };

    // Helper: check exists (case-insensitive) for this college
    $existsStmt = $connection->prepare("SELECT id FROM school WHERE college_id = ? AND LOWER(name) = LOWER(?) LIMIT 1");

    try {
        switch ($action) {
            case 'list_schools': {
                $list = [];
                $q = $connection->prepare("SELECT id, name FROM school WHERE college_id = ? ORDER BY name");
                $q->bind_param("i", $college_id);
                $q->execute();
                $rs = $q->get_result();
                while ($row = $rs->fetch_assoc()) { $list[] = $row; }
                echo json_encode(['success' => true, 'data' => $list]);
                break;
            }
            case 'add_school': {
                $name = $normalize($_POST['name'] ?? '');
                if ($name === '') { echo json_encode(['success' => false, 'message' => 'School name is required']); break; }
                $existsStmt->bind_param('is', $college_id, $name);
                $existsStmt->execute();
                $rs = $existsStmt->get_result();
                if ($rs->fetch_row()) { echo json_encode(['success' => false, 'message' => 'School already exists in this college']); break; }
                $ins = $connection->prepare("INSERT INTO school (name, college_id) VALUES (?, ?)");
                $ins->bind_param('si', $name, $college_id);
                $ins->execute();
                echo json_encode(['success' => true, 'message' => 'School added']);
                break;
            }
            case 'edit_school': {
                $id   = (int)($_POST['id'] ?? 0);
                $name = $normalize($_POST['name'] ?? '');
                if ($id <= 0 || $name === '') { echo json_encode(['success' => false, 'message' => 'Invalid payload']); break; }
                // ensure unique name within this college (exclude current id)
                $q = $connection->prepare("SELECT id FROM school WHERE college_id = ? AND LOWER(name) = LOWER(?) AND id <> ? LIMIT 1");
                $q->bind_param('isi', $college_id, $name, $id);
                $q->execute();
                $rs = $q->get_result();
                if ($rs->fetch_row()) { echo json_encode(['success' => false, 'message' => 'Another school with this name exists']); break; }
                $up = $connection->prepare("UPDATE school SET name = ? WHERE id = ? AND college_id = ?");
                $up->bind_param('sii', $name, $id, $college_id);
                $up->execute();
                echo json_encode(['success' => true, 'message' => 'School updated']);
                break;
            }
            case 'delete_school': {
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid id']); break; }
                $del = $connection->prepare("DELETE FROM school WHERE id = ? AND college_id = ?");
                $del->bind_param('ii', $id, $college_id);
                $del->execute();
                echo json_encode(['success' => true, 'message' => 'School deleted']);
                break;
            }
            case 'delete_all': {
                $del = $connection->prepare("DELETE FROM school WHERE college_id = ?");
                $del->bind_param('i', $college_id);
                $del->execute();
                echo json_encode(['success' => true, 'message' => 'All schools deleted']);
                break;
            }
            case 'check_existing': {
                // Input: JSON array of names (POST 'names')
                $raw = $_POST['names'] ?? '[]';
                $names = json_decode($raw, true);
                if (!is_array($names)) $names = [];
                $out = [];
                $check = $connection->prepare("SELECT id FROM school WHERE college_id = ? AND LOWER(name) = LOWER(?) LIMIT 1");
                foreach ($names as $nm) {
                    $nm = $normalize((string)$nm);
                    if ($nm === '') continue;
                    $check->bind_param('is', $college_id, $nm);
                    $check->execute();
                    $r = $check->get_result();
                    $row = $r->fetch_assoc();
                    $out[] = ['name' => $nm, 'exists' => (bool)$row, 'id' => $row['id'] ?? null];
                }
                echo json_encode(['success' => true, 'data' => $out]);
                break;
            }
            case 'bulk_add': {
                $raw = $_POST['names'] ?? '[]';
                $names = json_decode($raw, true);
                if (!is_array($names)) $names = [];
                // normalize + dedupe (case-insensitive)
                $normalized = [];
                foreach ($names as $nm) {
                    $n = $normalize((string)$nm);
                    if ($n !== '') $normalized[strtolower($n)] = $n;
                }
                $toInsert = array_values($normalized);

                $inserted = 0; $skipped = 0; $errors = 0; $skippedNames = [];
                $ins = $connection->prepare("INSERT INTO school (name, college_id) VALUES (?, ?)");
                foreach ($toInsert as $nm) {
                    // skip if exists
                    $existsStmt->bind_param('is', $college_id, $nm);
                    $existsStmt->execute();
                    $rs = $existsStmt->get_result();
                    if ($rs->fetch_row()) { $skipped++; $skippedNames[] = $nm; continue; }
                    $ins->bind_param('si', $nm, $college_id);
                    if ($ins->execute()) $inserted++; else $errors++;
                }
                echo json_encode(['success' => true, 'message' => 'Bulk add completed', 'inserted' => $inserted, 'skipped' => $skipped, 'errors' => $errors, 'skippedNames' => $skippedNames]);
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
    <title><?= htmlspecialchars($college['name']) ?> – College</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="assets/img/icon1.png" rel="icon" />
    <meta content="" name="description">
    <meta content="" name="keywords">
    <link href="assets/img/icon1.png" rel="icon">
    <link href="assets/img/icon1.png" rel="apple-touch-icon">
    <link href="https://fonts.gstatic.com" rel="preconnect">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.min.css" rel="stylesheet" />
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/v/bs5/dt-2.0.8/datatables.min.css" rel="stylesheet" />

    <style>
        body { background:#f7f8fa; }
        .card { box-shadow:0 2px 10px rgba(0,0,0,0.06); border:0; }
        .preview-badge { font-size: .8rem; }
        .file-drop { border:2px dashed #ced4da; border-radius: .5rem; padding:1.25rem; text-align:center; background:#fff; }
        .file-drop.dragover { border-color:#0d6efd; background:#eef4ff; }
        .table td, .table th { vertical-align: middle; }
        h2 { font-size: 1rem;background-color: rgba(14, 26, 78);padding: 6px !important; margin-bottom: 10px !important; border-radius: 5px !important;color:#fff !important; }
        h2:hover { background-color: rgba(14, 26, 78, 0.8); }
        .btn-primary { background-color: rgba(14, 26, 78); color:#fff !important; }
        .btn-primary:hover { background-color: rgba(14, 26, 78, 0.8); }
    </style>
</head>
<body>
<?php include('./includes/header.php'); ?>
<?php include('./includes/menu.php'); ?>

<main id="main" class="main">
    <div class="p-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div>
                <h2 class="mb-0">College: <?= htmlspecialchars($college['name']) ?></h2>
                <small class="text-muted">Campus: <?= htmlspecialchars($college['campus_name'] ?? 'Unknown') ?></small>
            </div>
            <div class="text-end">
                <button id="btnDeleteAll" class="btn btn-outline-danger"><i class="bi bi-trash3"></i> Delete All Schools</button>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card p-3">
                    <h2 class="mb-3">Add New School</h2>
                    <form id="formAdd">
                        <div class="mb-3">
                            <label class="form-label">School name</label>
                            <input type="text" class="form-control" id="addName" placeholder="e.g., School of Business" required />
                        </div>
                        <button class="btn btn-primary" type="submit"><i class="bi bi-plus-lg"></i> Add</button>
                    </form>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card p-3">
                    <h2 class="mb-3">Upload Schools (Excel)</h2>
                    <div id="dropArea" class="file-drop mb-3">
                        <div class="mb-2"><i class="bi bi-file-earmark-spreadsheet" style="font-size:2rem;"></i></div>
                        <div>Drop .xlsx/.xls here or <label class="text-primary text-decoration-underline" style="cursor:pointer"><input type="file" id="excelInput" accept=".xlsx,.xls" hidden />browse</label></div>
                        <div class="small text-muted mt-1">Expect a column named <code>name</code> or <code>School Name</code></div>
                    </div>
                    <div class="d-flex gap-2 mb-2">
                        <button id="btnCheck" class="btn btn-outline-secondary" disabled><i class="bi bi-search"></i> Check against existing</button>
                        <button id="btnImport" class="btn btn-success" disabled><i class="bi bi-cloud-upload"></i> Import new schools</button>
                    </div>
                    <div class="table-responsive">
                        <table id="previewTable" class="table table-sm table-bordered align-middle mb-0">
                            <thead><tr><th>#</th><th>School Name</th><th>Status</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- <hr class="my-4" /> -->
        <div class="card p-3">
          <h2 class="mb-3">Schools in this College</h2>
          <div class="table-responsive">
              <table id="schoolsTable" class="table table-bordered table-striped w-100">
                  <thead>
                      <tr><th style="width:60%">School Name</th><th style="width:40%">Actions</th></tr>
                  </thead>
                  <tbody></tbody>
              </table>
          </div>
        </div>

    </div>
</main>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit School</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="formEdit">
            <input type="hidden" id="editId" />
            <div class="mb-3">
                <label class="form-label">School name</label>
                <input type="text" id="editName" class="form-control" required />
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
  const collegeId = <?= (int)$college_id ?>;
  let dt;
  let previewRows = []; // {name, exists:null|true|false}

  function api(action, data) {
    return $.ajax({
      url: `?id=${collegeId}&ajax=1&action=${encodeURIComponent(action)}`,
      method: 'POST',
      data,
      dataType: 'json'
    });
  }

  function toast(icon, title) {
    Swal.fire({toast:true, icon, title, position:'top-end', timer:2500, showConfirmButton:false});
  }

  function loadSchools() {
    api('list_schools').done(res => {
      if (!res.success) return toast('error', res.message||'Failed');
      const rows = res.data || [];
      if (dt) { dt.clear().destroy(); }
      const tbody = $('#schoolsTable tbody').empty();
      rows.forEach(r => {
        const tr = $(
          `<tr>
            <td>${escapeHtml(r.name)}</td>
            <td>
              <button class="btn btn-sm btn-primary me-2 btn-edit" data-id="${r.id}" data-name="${escapeAttr(r.name)}"><i class="bi bi-pencil-square"></i> Edit</button>
              <button class="btn btn-sm btn-danger btn-del" data-id="${r.id}"><i class="bi bi-trash"></i> Delete</button>
            </td>
          </tr>`
        );
        tbody.append(tr);
      });
      dt = new DataTable('#schoolsTable', { pageLength: 10, order: [], responsive: true });
    });
  }

  // Simple escaping for HTML and attributes
  function escapeHtml(s){ return String(s).replace(/[&<>\"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
  function escapeAttr(s){ return String(s).replace(/["']/g, c => ({'"':'&quot;','\'':'&#39;'}[c])); }

  // Add single school
  $('#formAdd').on('submit', function(e){
    e.preventDefault();
    const name = $('#addName').val().trim().replace(/\s+/g,' ');
    if (!name) return toast('warning','Enter a name');
    api('add_school', {name}).done(res => {
      if (res.success) { $('#addName').val(''); toast('success','Added'); loadSchools(); }
      else toast('error', res.message||'Failed');
    });
  });

  // Edit
  let editModal = new bootstrap.Modal(document.getElementById('editModal'));
  $(document).on('click', '.btn-edit', function(){
    $('#editId').val($(this).data('id'));
    $('#editName').val($(this).data('name'));
    editModal.show();
  });
  $('#btnSaveEdit').on('click', function(){
    const id = $('#editId').val();
    const name = $('#editName').val().trim().replace(/\s+/g,' ');
    if (!name) return toast('warning','Enter a name');
    api('edit_school', {id, name}).done(res => {
      if (res.success) { toast('success','Updated'); editModal.hide(); loadSchools(); }
      else toast('error', res.message||'Failed');
    });
  });

  // Delete single
  $(document).on('click', '.btn-del', function(){
    const id = $(this).data('id');
    Swal.fire({title:'Delete this school?', icon:'warning', showCancelButton:true, confirmButtonText:'Delete'}).then(r=>{
      if (r.isConfirmed) {
        api('delete_school', {id}).done(res=>{
          if (res.success) { toast('success','Deleted'); loadSchools(); }
          else toast('error', res.message||'Failed');
        });
      }
    });
  });

  // Delete all
  $('#btnDeleteAll').on('click', function(){
    Swal.fire({title:'Delete ALL schools in this college?', text:'This cannot be undone', icon:'warning', showCancelButton:true, confirmButtonText:'Delete all'}).then(r=>{
      if (r.isConfirmed) {
        api('delete_all').done(res=>{
          if (res.success) { toast('success','All deleted'); loadSchools(); }
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
      // Extract names from 'name' or 'School Name'
      const names = [];
      json.forEach(row => {
        let nm = row.name || row.Name || row['School Name'] || row['school name'] || '';
        if (typeof nm !== 'string') nm = String(nm);
        nm = nm.trim().replace(/\s+/g,' ');
        if (nm) names.push(nm);
      });
      // Deduplicate (case-insensitive)
      const seen = new Set();
      previewRows = [];
      names.forEach(n => { const key = n.toLowerCase(); if (!seen.has(key)) { seen.add(key); previewRows.push({name:n, exists:null}); } });
      renderPreview();
      $('#btnCheck').prop('disabled', previewRows.length===0);
      $('#btnImport').prop('disabled', true);
    };
    reader.readAsArrayBuffer(file);
  }

  function renderPreview(results){
    const tbody = $('#previewTable tbody').empty();
    (previewRows || []).forEach((r, idx)=>{
      const status = r.exists===null ? '<span class="badge text-bg-secondary preview-badge">unchecked</span>' : (r.exists ? '<span class="badge text-bg-warning preview-badge">exists</span>' : '<span class="badge text-bg-success preview-badge">new</span>');
      tbody.append(`<tr><td>${idx+1}</td><td>${escapeHtml(r.name)}</td><td>${status}</td></tr>`);
    });
  }

  $('#btnCheck').on('click', function(){
    if (!previewRows.length) return;
    const names = previewRows.map(r=>r.name);
    api('check_existing', {names: JSON.stringify(names)}).done(res=>{
      if (!res.success) return toast('error', res.message||'Check failed');
      const map = new Map();
      (res.data||[]).forEach(x=> map.set(x.name.toLowerCase(), !!x.exists));
      previewRows = previewRows.map(r => ({...r, exists: map.get(r.name.toLowerCase()) ?? false}));
      renderPreview();
      const anyNew = previewRows.some(r=>r.exists===false);
      $('#btnImport').prop('disabled', !anyNew);
      toast('success', 'Checked against existing');
    });
  });

  $('#btnImport').on('click', function(){
    const toImport = previewRows.filter(r=>r.exists===false).map(r=>r.name);
    if (!toImport.length) return toast('info','Nothing to import');
    api('bulk_add', {names: JSON.stringify(toImport)}).done(res=>{
      if (res.success) {
        toast('success', `Imported: ${res.inserted}, Skipped: ${res.skipped}`);
        loadSchools();
        // Mark imported as exists now
        const set = new Set(toImport.map(n=>n.toLowerCase()));
        previewRows = previewRows.map(r=> set.has(r.name.toLowerCase()) ? ({...r, exists:true}) : r);
        renderPreview();
        $('#btnImport').prop('disabled', true);
      } else {
        toast('error', res.message||'Import failed');
      }
    });
  });

  // Init
  loadSchools();
})();
</script>
</body>
</html>