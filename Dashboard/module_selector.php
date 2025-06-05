<?php
include('connection.php');

// Get modules
$modules_query = "SELECT m.*, p.name as program_name, p.id as program_id 
                 FROM module m 
                 JOIN program p ON m.program_id = p.id 
                 ORDER BY m.code";
$modules_result = mysqli_query($connection, $modules_query);
?>

<!-- Module Modal -->
<div class="modal fade" id="moduleModal" tabindex="-1" aria-labelledby="moduleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="moduleModalLabel">Select Module</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <input type="text" class="form-control" id="moduleSearch" placeholder="Search modules...">
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Select</th>
                                        <th>Code</th>
                                        <th>Name</th>
                                        <th>Program</th>
                                        <th>Credits</th>
                                    </tr>
                                </thead>
                                <tbody id="moduleTableBody">
                                    <?php 
                                    while($module = mysqli_fetch_assoc($modules_result)): 
                                    ?>
                                    <tr>
                                        <td>
                                            <input type="radio" class="form-check-input module-radio" 
                                                   name="module_radio" 
                                                   value="<?php echo $module['id']; ?>"
                                                   data-code="<?php echo $module['code']; ?>"
                                                   data-name="<?php echo $module['name']; ?>"
                                                   data-program="<?php echo $module['program_id']; ?>"
                                                   data-credits="<?php echo $module['credits']; ?>"
                                                   data-semester="<?php echo $module['semester']; ?>">
                                        </td>
                                        <td><?php echo $module['code']; ?></td>
                                        <td><?php echo $module['name']; ?></td>
                                        <td><?php echo $module['program_name']; ?></td>
                                        <td><?php echo $module['credits']; ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <div>
                                    <span class="text-muted">Showing <span id="moduleStartRecord">1</span> to <span id="moduleEndRecord">10</span> of <span id="moduleTotalRecords">0</span> modules</span>
                                </div>
                                <nav aria-label="Module pagination">
                                    <ul class="pagination mb-0">
                                        <li class="page-item" id="modulePrevPage">
                                            <a class="page-link" href="#" aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                        <li class="page-item" id="moduleNextPage">
                                            <a class="page-link" href="#" aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="confirmModule">Confirm Selection</button>
            </div>
        </div>
    </div>
</div>

<script>
// Add pagination variables
let currentModulePage = 1;
const modulesPerPage = 10;
let totalModules = 0;

// Function to update module pagination
function updateModulePagination() {
    const rows = document.querySelectorAll('#moduleTableBody tr');
    totalModules = rows.length;
    const totalPages = Math.ceil(totalModules / modulesPerPage);
    
    // Update pagination info
    document.getElementById('moduleTotalRecords').textContent = totalModules;
    document.getElementById('moduleStartRecord').textContent = ((currentModulePage - 1) * modulesPerPage) + 1;
    document.getElementById('moduleEndRecord').textContent = Math.min(currentModulePage * modulesPerPage, totalModules);
    
    // Update pagination buttons
    document.getElementById('modulePrevPage').classList.toggle('disabled', currentModulePage === 1);
    document.getElementById('moduleNextPage').classList.toggle('disabled', currentModulePage === totalPages);
    
    // Show/hide rows based on current page
    rows.forEach((row, index) => {
        const start = (currentModulePage - 1) * modulesPerPage;
        const end = start + modulesPerPage;
        row.style.display = (index >= start && index < end) ? '' : 'none';
    });
}

// Add pagination event listeners
document.getElementById('modulePrevPage').addEventListener('click', function(e) {
    e.preventDefault();
    if (currentModulePage > 1) {
        currentModulePage--;
        updateModulePagination();
    }
});

document.getElementById('moduleNextPage').addEventListener('click', function(e) {
    e.preventDefault();
    const totalPages = Math.ceil(totalModules / modulesPerPage);
    if (currentModulePage < totalPages) {
        currentModulePage++;
        updateModulePagination();
    }
});

// Update pagination when modal is shown
document.getElementById('moduleModal').addEventListener('show.bs.modal', function() {
    currentModulePage = 1;
    updateModulePagination();
});

// Update pagination when search changes
document.getElementById('moduleSearch').addEventListener('input', function() {
    currentModulePage = 1;
    updateModulePagination();
});

// Module search functionality
document.getElementById('moduleSearch').addEventListener('input', function(e) {
    const searchText = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('#moduleTableBody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchText) ? '' : 'none';
    });
});

// Confirm module selection
document.getElementById('confirmModule').addEventListener('click', function() {
    const selectedModule = document.querySelector('.module-radio:checked');
    if (!selectedModule) {
        alert('Please select a module');
        return;
    }

    document.getElementById('module').value = selectedModule.value;
    document.getElementById('selectedModuleDisplay').value = 
        `${selectedModule.dataset.code} - ${selectedModule.dataset.name} (${selectedModule.dataset.credits} credits)`;

    // Close modal
    bootstrap.Modal.getInstance(document.getElementById('moduleModal')).hide();
    
    // Enable lecturer selection
    if (typeof window.updateLecturerButton === 'function') {
        window.updateLecturerButton();
    }
});
</script> 