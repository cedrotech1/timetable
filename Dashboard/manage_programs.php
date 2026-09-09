<?php
// Include database connection
include 'connection.php';

// Initialize variables
$uploadMessage = '';
$deleteMessage = '';
$addMessage = '';
$programs = [];

// Handle manual add single program
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_program'])) {
    $programName = trim($_POST['program_name']);
    
    if (!empty($programName)) {
        // Check if program name already exists (case-insensitive)
        $checkQuery = "SELECT id FROM all_programs WHERE LOWER(name) = LOWER('" . mysqli_real_escape_string($connection, $programName) . "')";
        $checkResult = mysqli_query($connection, $checkQuery);
        
        if (mysqli_num_rows($checkResult) > 0) {
            $addMessage = "Error: Program name already exists.";
        } else {
            // Insert program
            $insertQuery = "INSERT INTO all_programs (name) VALUES ('" . mysqli_real_escape_string($connection, $programName) . "')";
            if (mysqli_query($connection, $insertQuery)) {
                $addMessage = "Program added successfully.";
            } else {
                $addMessage = "Error: Failed to add program.";
            }
        }
        mysqli_free_result($checkResult);
    } else {
        $addMessage = "Error: Program name cannot be empty.";
    }
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csvFile'])) {
    $file = $_FILES['csvFile'];
    
    // Validate file
    if ($file['error'] === UPLOAD_ERR_OK && $file['type'] === 'text/csv') {
        $filePath = $file['tmp_name'];
        
        // Open and read CSV file
        if (($handle = fopen($filePath, 'r')) !== false) {
            // Skip header row
            fgetcsv($handle);
            
            $names = [];
            $hasDuplicate = false;
            
            // Read all names and check for duplicates
            while (($data = fgetcsv($handle)) !== false) {
                if (count($data) >= 2 && !empty($data[1])) {
                    $name = strtolower(trim($data[1]));
                    $names[] = $name;
                    
                    // Check if name already exists (case-insensitive)
                    $checkQuery = "SELECT id FROM all_programs WHERE LOWER(name) = '" . mysqli_real_escape_string($connection, $name) . "'";
                    $checkResult = mysqli_query($connection, $checkQuery);
                    if (mysqli_num_rows($checkResult) > 0) {
                        $hasDuplicate = true;
                    }
                    mysqli_free_result($checkResult);
                }
            }
            
            // If no duplicates, proceed with insertion
            if (!$hasDuplicate) {
                $successCount = 0;
                $errorCount = 0;
                
                // Rewind file to process again for insertion
                rewind($handle);
                fgetcsv($handle); // Skip header again
                
                while (($data = fgetcsv($handle)) !== false) {
                    if (count($data) >= 2 && !empty($data[1])) {
                        $name = mysqli_real_escape_string($connection, strtolower(trim($data[1])));
                        
                        // Insert only name into database (id is auto-incremented)
                        $query = "INSERT INTO all_programs (name) VALUES ('$name')";
                        if (mysqli_query($connection, $query)) {
                            $successCount++;
                        } else {
                            $errorCount++;
                        }
                    } else {
                        $errorCount++;
                    }
                }
                $uploadMessage = "Uploaded: $successCount records inserted successfully. $errorCount records failed.";
            } else {
                $uploadMessage = "Error: One or more program names already exist in the database. No records were inserted.";
            }
            
            fclose($handle);
        } else {
            $uploadMessage = "Error: Unable to open the CSV file.";
        }
    } else {
        $uploadMessage = "Error: Please upload a valid CSV file.";
    }
}

// Handle delete single program
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = mysqli_real_escape_string($connection, $_POST['delete_id']);
    $deleteQuery = "DELETE FROM all_programs WHERE id = '$deleteId'";
    if (mysqli_query($connection, $deleteQuery)) {
        $deleteMessage = "Program with ID $deleteId deleted successfully.";
    } else {
        $deleteMessage = "Error: Failed to delete program with ID $deleteId.";
    }
}

// Handle delete all programs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_all'])) {
    $deleteAllQuery = "DELETE FROM all_programs";
    if (mysqli_query($connection, $deleteAllQuery)) {
        $deleteMessage = "All programs deleted successfully.";
    } else {
        $deleteMessage = "Error: Failed to delete all programs.";
    }
}

// Fetch all programs from database
$query = "SELECT id, name FROM all_programs ORDER BY name";
$result = mysqli_query($connection, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $programs[] = $row;
    }
    mysqli_free_result($result);
}

// Match all_programs names to program rows that already have modules (same idea as campus.php)
$programsWithModules = []; // lower(name) => program.id
$moduleQuery = "SELECT DISTINCT p.id, LOWER(TRIM(p.name)) AS lname
                FROM program p
                INNER JOIN module m ON m.program_id = p.id
                WHERE p.name IS NOT NULL AND TRIM(p.name) <> ''";
$moduleResult = mysqli_query($connection, $moduleQuery);
if ($moduleResult) {
    while ($row = mysqli_fetch_assoc($moduleResult)) {
        $programsWithModules[$row['lname']] = (int)$row['id'];
    }
    mysqli_free_result($moduleResult);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Program Management</title>
    <!-- Bootstrap CSS via CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body style="background-color: #f4f6f8;">
    <div class="mt-5">
        <h1 class="mb-4">Program Management</h1>
        
        <!-- Add Single Program Form -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">Add Single Program</div>
            <div class="card-body">
                <form method="post" class="row g-3">
                    <div class="col-md-8">
                        <label for="programName" class="form-label">Program Name</label>
                        <input type="text" class="form-control" id="programName" name="program_name" placeholder="Enter program name" required>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" name="add_program" class="btn btn-success w-100">
                            <i class="bi bi-plus-circle"></i> Add Program
                        </button>
                    </div>
                </form>
                <?php if ($addMessage): ?>
                    <div class="alert <?php echo strpos($addMessage, 'Error') === false ? 'alert-success' : 'alert-danger'; ?> mt-3">
                        <?php echo htmlspecialchars($addMessage); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Upload Form -->
        <div class="card mb-4">
            <div class="card-header">Upload Programs (CSV)</div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="csvFile" class="form-label">Select CSV File</label>
                        <input type="file" class="form-control" id="csvFile" name="csvFile" accept=".csv" required>
                        <small class="form-text text-muted">CSV format: id,name (e.g., "1,Program Name"). Only name is used, stored in lowercase. All names must be unique.</small>
                    </div>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </form>
                <?php if ($uploadMessage): ?>
                    <div class="alert <?php echo strpos($uploadMessage, 'Error') === false ? 'alert-success' : 'alert-danger'; ?> mt-3">
                        <?php echo htmlspecialchars($uploadMessage); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Delete All Programs -->
        <div class="card mb-4">
            <div class="card-header">Delete All Programs</div>
            <div class="card-body">
                <form method="post" onsubmit="return confirm('Are you sure you want to delete ALL programs? This cannot be undone.');">
                    <input type="hidden" name="delete_all" value="1">
                    <button type="submit" class="btn btn-danger">Delete All Programs</button>
                </form>
            </div>
        </div>

        <!-- Programs Table -->
        <div class="card">
            <div class="card-header">All Programs</div>
            <div class="card-body">
                <?php if ($deleteMessage): ?>
                    <div class="alert <?php echo strpos($deleteMessage, 'Error') === false ? 'alert-success' : 'alert-danger'; ?> mb-3">
                        <?php echo htmlspecialchars($deleteMessage); ?>
                    </div>
                <?php endif; ?>
                <?php if (empty($programs)): ?>
                    <p>No programs found.</p>
                <?php else: ?>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Modules</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($programs as $program): ?>
                                <?php
                                $lname = strtolower(trim($program['name']));
                                $hasModules = isset($programsWithModules[$lname]);
                                $linkedProgramId = $hasModules ? $programsWithModules[$lname] : 0;
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($program['id']); ?></td>
                                    <td><?php echo htmlspecialchars($program['name']); ?></td>
                                    <td>
                                        <?php if ($hasModules): ?>
                                            <button type="button"
                                                    class="btn btn-link p-0 view-modules"
                                                    data-program-id="<?php echo (int)$linkedProgramId; ?>"
                                                    data-program-name="<?php echo htmlspecialchars($program['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    title="View all modules">
                                                <i class="bi bi-check-circle-fill text-success"></i>
                                                <span class="text-success ms-1">Has modules</span>
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted" title="No modules assigned">
                                                <i class="bi bi-x-circle text-danger"></i>
                                                <span class="ms-1">No modules</span>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete <?php echo htmlspecialchars($program['name']); ?>?');">
                                            <input type="hidden" name="delete_id" value="<?php echo htmlspecialchars($program['id']); ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modulesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modulesModalLabel">Program Modules</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="modulesModalLoading" class="text-center py-4 d-none">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2 mb-0">Loading modules...</p>
                    </div>
                    <div id="modulesModalError" class="alert alert-danger d-none"></div>
                    <div id="modulesModalList" class="d-none"></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const modalElement = document.getElementById('modulesModal');
        if (!modalElement) {
            return;
        }

        const modulesModal = new bootstrap.Modal(modalElement);
        const modulesModalLabel = document.getElementById('modulesModalLabel');
        const modulesModalLoading = document.getElementById('modulesModalLoading');
        const modulesModalError = document.getElementById('modulesModalError');
        const modulesModalList = document.getElementById('modulesModalList');

        function setModalState({ loading = false, error = '', items = [] }) {
            modulesModalLoading.classList.toggle('d-none', !loading);

            if (error) {
                modulesModalError.classList.remove('d-none');
                modulesModalError.textContent = error;
            } else {
                modulesModalError.classList.add('d-none');
                modulesModalError.textContent = '';
            }

            if (items.length) {
                const groups = new Map();
                items.forEach((module) => {
                    const rawYear = module.year ?? 'N/A';
                    const yearKey = rawYear && rawYear !== 'N/A' ? String(rawYear) : 'Unspecified';
                    if (!groups.has(yearKey)) {
                        groups.set(yearKey, []);
                    }
                    groups.get(yearKey).push(module);
                });

                const sortedKeys = Array.from(groups.keys()).sort((a, b) => {
                    const numA = Number(a);
                    const numB = Number(b);
                    const isNumA = !Number.isNaN(numA);
                    const isNumB = !Number.isNaN(numB);
                    if (isNumA && isNumB) {
                        return numB - numA;
                    }
                    if (isNumA) return -1;
                    if (isNumB) return 1;
                    return a.localeCompare(b);
                });

                const fragment = document.createDocumentFragment();
                sortedKeys.forEach((year) => {
                    const section = document.createElement('div');
                    section.className = 'mb-3';

                    const header = document.createElement('div');
                    header.className = 'fw-bold text-primary mb-2';
                    header.textContent = year === 'Unspecified' ? 'Year not set' : `Year ${year}`;
                    section.appendChild(header);

                    const list = document.createElement('ul');
                    list.className = 'list-group';

                    groups.get(year).forEach((module) => {
                        const li = document.createElement('li');
                        li.className = 'list-group-item';
                        li.innerHTML = `
                            <div class="fw-semibold">${module.code ? module.code + ' — ' : ''}${module.name}</div>
                            <div class="small text-muted">Semester ${module.semester ?? 'N/A'} · ${module.credits ?? '0'} credits</div>
                        `;
                        list.appendChild(li);
                    });

                    section.appendChild(list);
                    fragment.appendChild(section);
                });

                modulesModalList.innerHTML = '';
                modulesModalList.appendChild(fragment);
                modulesModalList.classList.remove('d-none');
            } else {
                modulesModalList.classList.add('d-none');
                modulesModalList.innerHTML = '';
            }
        }

        document.addEventListener('click', function(event) {
            const trigger = event.target.closest('.view-modules');
            if (!trigger) {
                return;
            }

            const programId = trigger.getAttribute('data-program-id');
            const programName = trigger.getAttribute('data-program-name') || 'Program Modules';
            if (!programId) {
                return;
            }

            modulesModalLabel.textContent = `Modules for ${programName}`;
            setModalState({ loading: true });
            modulesModal.show();

            const params = new URLSearchParams({ program_id: programId, perPage: 500, page: 1, sort: 'name' });

            fetch(`api_get_modules.php?${params.toString()}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && Array.isArray(data.data)) {
                        if (data.data.length) {
                            const modules = data.data.map(module => ({
                                name: module.name || 'Untitled module',
                                code: module.code || '',
                                credits: module.credits || 0,
                                year: module.year || 'N/A',
                                semester: module.semester || 'N/A'
                            }));
                            setModalState({ items: modules });
                        } else {
                            setModalState({ error: 'No modules found for this program.' });
                        }
                    } else {
                        setModalState({ error: data.message || 'Failed to load modules.' });
                    }
                })
                .catch(() => {
                    setModalState({ error: 'A network error occurred while loading modules.' });
                });
        });
    });
    </script>
</body>
</html>