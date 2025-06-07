<?php


include('connection.php');
// include('./includes/auth.php');

// Get user's role and campus
$id = $_SESSION['id'];
$sql = "SELECT * FROM users WHERE id = $id";
$result = mysqli_query($connection, $sql);
$row = mysqli_fetch_assoc($result);
$mycampus = $row['campus'];
$role = $row['role'];

// Get all campuses for selection
if($role === 'warefare'){       
    $campuses_query = mysqli_query($connection, "SELECT * FROM campus WHERE id = $mycampus ORDER BY name");
} else {
    $campuses_query = mysqli_query($connection, "SELECT * FROM campus ORDER BY name");
}
$campuses = [];
while ($campus = mysqli_fetch_assoc($campuses_query)) {
    $campuses[] = $campus;
}

// Get programs for selection with their departments
$programs_query = "SELECT p.*, d.name as department_name 
                  FROM program p 
                  JOIN department d ON p.department_id = d.id 
                  ORDER BY p.name";
$programs_result = mysqli_query($connection, $programs_query);
$programs = [];
while ($program = mysqli_fetch_assoc($programs_result)) {
    $programs[] = $program;
}

// Pagination settings
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Search and filter parameters
$search = isset($_GET['search']) ? mysqli_real_escape_string($connection, $_GET['search']) : '';
$program_filter = isset($_GET['program']) ? (int)$_GET['program'] : 0;
$year_filter = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$semester_filter = isset($_GET['semester']) ? (int)$_GET['semester'] : 0;

// Build query
$where_conditions = [];
if ($search) {
    $where_conditions[] = "(m.name LIKE '%$search%' OR m.code LIKE '%$search%')";
}
if ($program_filter) {
    $where_conditions[] = "m.program_id = $program_filter";
}
if ($year_filter) {
    $where_conditions[] = "m.year = $year_filter";
}
if ($semester_filter) {
    $where_conditions[] = "m.semester = $semester_filter";
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total records for pagination
$count_query = "SELECT COUNT(*) as total FROM module m $where_clause";
$count_result = mysqli_query($connection, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get modules with pagination
$query = "SELECT m.*, p.name as program_name, d.name as department_name 
          FROM module m 
          LEFT JOIN program p ON m.program_id = p.id 
          LEFT JOIN department d ON p.department_id = d.id 
          $where_clause 
          ORDER BY m.name 
          LIMIT $offset, $records_per_page";
$result = mysqli_query($connection, $query);
?>

<div class="card">
    <div class="card-body">
        <h5 class="card-title">Manage Modules</h5>

        <!-- Search and Filter Form -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <input type="text" class="form-control" id="moduleSearch" placeholder="Search modules...">
            </div>
            <div class="col-md-3">
                <select class="form-select" id="moduleProgram">
                    <option value="">All Programs</option>
                    <?php foreach ($programs as $program): ?>
                    <option value="<?php echo $program['id']; ?>">
                        <?php echo htmlspecialchars($program['name'] . ' (' . $program['department_name'] . ')'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="moduleYear">
                    <option value="">All Years</option>
                    <option value="1">Year 1</option>
                    <option value="2">Year 2</option>
                    <option value="3">Year 3</option>
                    <option value="4">Year 4</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="moduleSemester">
                    <option value="">All Semesters</option>
                    <option value="1">Semester 1</option>
                    <option value="2">Semester 2</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-primary w-100" onclick="loadModules(1)">
                    <i class="bi bi-search me-1"></i>Search
                </button>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-success w-100" onclick="window.location.href='add_module.php'">
                    <i class="bi bi-plus-circle me-1"></i>Add Module
                </button>
            </div>
        </div>

        <!-- Modules Table -->
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Credits</th>
                        <th>Year</th>
                        <th>Semester</th>
                        <th>Program</th>
                        <th>Department</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="modulesList">
                    <!-- Data will be loaded here -->
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div id="modulePagination" class="mt-4">
            <!-- Pagination will be loaded here -->
        </div>
    </div>
</div>

<!-- Edit Module Modal -->
<div class="modal fade" id="editModuleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Module</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editModuleForm">
                    <input type="hidden" id="editModuleId" name="id">
                    <div class="mb-3">
                        <label class="form-label">Module Name</label>
                        <input type="text" class="form-control" id="editModuleName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Module Code</label>
                        <input type="text" class="form-control" id="editModuleCode" name="code" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Program</label>
                        <select class="form-select" id="editModuleProgram" name="program_id" required>
                            <?php foreach ($programs as $program): ?>
                            <option value="<?php echo $program['id']; ?>">
                                <?php echo htmlspecialchars($program['name'] . ' (' . $program['department_name'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Credits</label>
                        <input type="number" class="form-control" id="editModuleCredits" name="credits" min="1" max="30" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Year</label>
                        <select class="form-select" id="editModuleYear" name="year" required>
                            <option value="1">Year 1</option>
                            <option value="2">Year 2</option>
                            <option value="3">Year 3</option>
                            <option value="4">Year 4</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Semester</label>
                        <select class="form-select" id="editModuleSemester" name="semester" required>
                            <option value="1">Semester 1</option>
                            <option value="2">Semester 2</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="updateModule()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Module Modal -->
<div class="modal fade" id="deleteModuleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Module</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this module? This action cannot be undone.</p>
                <input type="hidden" id="deleteModuleId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmDeleteModule()">Delete</button>
            </div>
        </div>
    </div>
</div>

<script>
// Function to display module pagination
function displayModulePagination(pagination) {
    const paginationContainer = document.getElementById('modulePagination');
    if (!paginationContainer) return;

    let html = '<ul class="pagination justify-content-center">';
    
    // Previous button
    html += `
        <li class="page-item ${pagination.current_page === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${pagination.current_page - 1}" onclick="loadModules(${pagination.current_page - 1})">
                <i class="bi bi-chevron-left"></i>
            </a>
        </li>
    `;

    // Page numbers
    for (let i = 1; i <= pagination.last_page; i++) {
        if (
            i === 1 || // First page
            i === pagination.last_page || // Last page
            (i >= pagination.current_page - 2 && i <= pagination.current_page + 2) // Pages around current
        ) {
            html += `
                <li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}" onclick="loadModules(${i})">${i}</a>
                </li>
            `;
        } else if (
            i === pagination.current_page - 3 || 
            i === pagination.current_page + 3
        ) {
            html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }

    // Next button
    html += `
        <li class="page-item ${pagination.current_page === pagination.last_page ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${pagination.current_page + 1}" onclick="loadModules(${pagination.current_page + 1})">
                <i class="bi bi-chevron-right"></i>
            </a>
        </li>
    `;

    html += '</ul>';
    paginationContainer.innerHTML = html;
}

// Function to load modules with pagination
async function loadModules(page = 1) {
    try {
        const search = document.getElementById('moduleSearch').value;
        const program = document.getElementById('moduleProgram').value;
        const year = document.getElementById('moduleYear').value;
        const semester = document.getElementById('moduleSemester').value;

        const response = await fetch(`get_modules.php?search=${search}&program=${program}&year=${year}&semester=${semester}&page=${page}`);
        const data = await response.json();

        if (data.success) {
            const moduleTableBody = document.getElementById('modulesList');
            moduleTableBody.innerHTML = '';

            data.data.modules.forEach(module => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${module.code}</td>
                    <td>${module.name}</td>
                    <td>${module.credits}</td>
                    <td>Year ${module.year}</td>
                    <td>Semester ${module.semester}</td>
                    <td>${module.program_name}</td>
                    <td>${module.department_name}</td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="editModule(${module.id})">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="deleteModule(${module.id})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                `;
                moduleTableBody.appendChild(row);
            });

            displayModulePagination(data.data.pagination);
        } else {
            throw new Error(data.message || 'Failed to load modules');
        }
    } catch (error) {
        console.error('Error loading modules:', error);
        alert('Error: ' + error.message);
    }
}

// Function to edit module
async function editModule(id) {
    try {
        const response = await fetch(`get_module.php?id=${id}`);
        const data = await response.json();

        if (data.success) {
            const module = data.data;
            document.getElementById('editModuleId').value = module.id;
            document.getElementById('editModuleName').value = module.name;
            document.getElementById('editModuleCode').value = module.code;
            document.getElementById('editModuleProgram').value = module.program_id;
            document.getElementById('editModuleCredits').value = module.credits;
            document.getElementById('editModuleYear').value = module.year;
            document.getElementById('editModuleSemester').value = module.semester;

            const modal = new bootstrap.Modal(document.getElementById('editModuleModal'));
            modal.show();
        } else {
            throw new Error(data.message || 'Failed to load module details');
        }
    } catch (error) {
        console.error('Error loading module details:', error);
        alert('Error: ' + error.message);
    }
}

// Function to update module
async function updateModule() {
    try {
        const form = document.getElementById('editModuleForm');
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        const response = await fetch('update_module.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('editModuleModal'));
            modal.hide();
            loadModules();
            alert('Module updated successfully');
        } else {
            throw new Error(result.message || 'Failed to update module');
        }
    } catch (error) {
        console.error('Error updating module:', error);
        alert('Error: ' + error.message);
    }
}

// Function to delete module
function deleteModule(id) {
    document.getElementById('deleteModuleId').value = id;
    const modal = new bootstrap.Modal(document.getElementById('deleteModuleModal'));
    modal.show();
}

// Function to confirm module deletion
async function confirmDeleteModule() {
    try {
        const id = document.getElementById('deleteModuleId').value;
        const response = await fetch('delete_module.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id })
        });

        const result = await response.json();

        if (result.success) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('deleteModuleModal'));
            modal.hide();
            loadModules();
            alert('Module deleted successfully');
        } else {
            throw new Error(result.message || 'Failed to delete module');
        }
    } catch (error) {
        console.error('Error deleting module:', error);
        alert('Error: ' + error.message);
    }
}

// Initialize the page
document.addEventListener('DOMContentLoaded', function() {
    // Load initial data
    loadModules();

    // Add event listeners for search and filter inputs
    document.getElementById('moduleSearch').addEventListener('input', debounce(() => loadModules(1), 300));
    document.getElementById('moduleProgram').addEventListener('change', () => loadModules(1));
    document.getElementById('moduleYear').addEventListener('change', () => loadModules(1));
    document.getElementById('moduleSemester').addEventListener('change', () => loadModules(1));
});

// Debounce function to limit API calls
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}
</script> 