<?php
include('connection.php');

// Get the schedule from POST data
$schedule = isset($_POST['schedule']) ? json_decode($_POST['schedule'], true) : [];
$academic_year_id = isset($_POST['academic_year_id']) ? intval($_POST['academic_year_id']) : null;
$semester = isset($_POST['semester']) ? intval($_POST['semester']) : null;

// Base query to get all facilities
$facilities_query = "SELECT DISTINCT f.*, c.name as campus_name 
                    FROM facility f 
                    LEFT JOIN campus c ON f.campus_id = c.id";

// If we have schedule data, check for conflicts
if (!empty($schedule) && $academic_year_id && $semester) {
    $facilities_query .= " WHERE f.id NOT IN (
        SELECT DISTINCT t.facility_id
        FROM timetable t
        JOIN timetable_sessions ts ON t.id = ts.timetable_id
        WHERE t.academic_year_id = ? 
        AND t.semester = ?
        AND (";
    
    $conditions = [];
    $params = [];
    $types = "ii"; // academic_year_id and semester are integers
    
    foreach ($schedule as $session) {
        $conditions[] = "(ts.day = ? AND (
            (ts.start_time <= ? AND ts.end_time > ?) OR
            (ts.start_time < ? AND ts.end_time >= ?) OR
            (ts.start_time >= ? AND ts.end_time <= ?)
        ))";
        $params[] = $session['day'];
        $params[] = $session['end_time'];
        $params[] = $session['start_time'];
        $params[] = $session['end_time'];
        $params[] = $session['start_time'];
        $params[] = $session['start_time'];
        $params[] = $session['end_time'];
        $types .= "sssssss"; // 7 string parameters for each session
    }
    
    $facilities_query .= implode(" OR ", $conditions) . "))";
    
    // Prepare and execute the query with parameters
    $stmt = mysqli_prepare($connection, $facilities_query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, $types, $academic_year_id, $semester, ...$params);
        mysqli_stmt_execute($stmt);
        $facilities_result = mysqli_stmt_get_result($stmt);
    } else {
        $facilities_result = mysqli_query($connection, $facilities_query);
    }
} else {
    $facilities_result = mysqli_query($connection, $facilities_query);
}
?>

<!-- Facility Selection Modal -->
<div class="modal fade" id="facilityModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Select Facility</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="filter-controls mb-3">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <input type="text" class="form-control" id="facilitySearch" placeholder="Search by name...">
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="facilityType">
                                <option value="">All Types</option>
                                <?php 
                                $types = [];
                                while($facility = mysqli_fetch_assoc($facilities_result)) {
                                    if (!in_array($facility['type'], $types)) {
                                        $types[] = $facility['type'];
                                        echo "<option value='" . htmlspecialchars($facility['type']) . "'>" . htmlspecialchars($facility['type']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="facilityLocation">
                                <option value="">All Locations</option>
                                <?php 
                                $locations = [];
                                mysqli_data_seek($facilities_result, 0);
                                while($facility = mysqli_fetch_assoc($facilities_result)) {
                                    if (!in_array($facility['location'], $locations)) {
                                        $locations[] = $facility['location'];
                                        echo "<option value='" . htmlspecialchars($facility['location']) . "'>" . htmlspecialchars($facility['location']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" id="facilityCampus">
                                <option value="">All Campuses</option>
                                <?php 
                                $campuses = [];
                                mysqli_data_seek($facilities_result, 0);
                                while($facility = mysqli_fetch_assoc($facilities_result)) {
                                    if (!in_array($facility['campus_name'], $campuses)) {
                                        $campuses[] = $facility['campus_name'];
                                        echo "<option value='" . htmlspecialchars($facility['campus_name']) . "'>" . htmlspecialchars($facility['campus_name']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Select</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Location</th>
                                <th>Campus</th>
                                <th>Capacity</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            mysqli_data_seek($facilities_result, 0);
                            while($facility = mysqli_fetch_assoc($facilities_result)): 
                            ?>
                            <tr class="facility-row">
                                <td>
                                    <input type="radio" name="facility" value="<?php echo $facility['id']; ?>" class="facility-radio">
                                </td>
                                <td><?php echo htmlspecialchars($facility['name']); ?></td>
                                <td><?php echo htmlspecialchars($facility['type']); ?></td>
                                <td><?php echo htmlspecialchars($facility['location']); ?></td>
                                <td><?php echo htmlspecialchars($facility['campus_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="badge bg-success">
                                        <?php echo htmlspecialchars($facility['capacity']); ?> students
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <div class="pagination-container d-flex justify-content-center mt-3"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="selectFacility">Select</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const facilityModal = document.getElementById('facilityModal');
    const selectFacilityBtn = document.getElementById('selectFacility');
    const selectedFacilityDisplay = document.getElementById('selectedFacilityDisplay');
    const facilityInput = document.getElementById('facility');
    const facilityTable = document.querySelector('#facilityModal .table tbody');
    const itemsPerPage = 10;

    // Function to update facility display based on filters
    function updateFacilityDisplay() {
        const searchTerm = document.getElementById('facilitySearch')?.value.toLowerCase() || '';
        const typeFilter = document.getElementById('facilityType')?.value || '';
        const locationFilter = document.getElementById('facilityLocation')?.value || '';
        const campusFilter = document.getElementById('facilityCampus')?.value || '';

        const rows = facilityTable.querySelectorAll('tr');
        let visibleCount = 0;

        rows.forEach(row => {
            const name = row.cells[1].textContent.toLowerCase();
            const type = row.cells[2].textContent;
            const location = row.cells[3].textContent;
            const campus = row.cells[4].textContent;

            const matchesSearch = name.includes(searchTerm);
            const matchesType = !typeFilter || type === typeFilter;
            const matchesLocation = !locationFilter || location === locationFilter;
            const matchesCampus = !campusFilter || campus === campusFilter;

            if (matchesSearch && matchesType && matchesLocation && matchesCampus) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        updatePagination(visibleCount);
    }

    // Function to update pagination
    function updatePagination(totalItems) {
        const paginationContainer = document.querySelector('.pagination-container');
        const totalPages = Math.ceil(totalItems / itemsPerPage);

        if (totalPages <= 1) {
            paginationContainer.innerHTML = '';
            return;
        }

        let paginationHTML = `
            <nav aria-label="Facility pagination">
                <ul class="pagination">
                    <li class="page-item">
                        <a class="page-link" href="#" data-page="prev">&laquo;</a>
                    </li>
        `;

        for (let i = 1; i <= totalPages; i++) {
            paginationHTML += `
                <li class="page-item">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>
            `;
        }

        paginationHTML += `
                    <li class="page-item">
                        <a class="page-link" href="#" data-page="next">&raquo;</a>
                    </li>
                </ul>
            </nav>
        `;

        paginationContainer.innerHTML = paginationHTML;

        // Add click handlers for pagination
        paginationContainer.querySelectorAll('.page-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const page = this.dataset.page;
                const currentPage = parseInt(document.querySelector('.pagination .active')?.textContent || '1');
                
                let newPage;
                if (page === 'prev') {
                    newPage = Math.max(1, currentPage - 1);
                } else if (page === 'next') {
                    newPage = Math.min(totalPages, currentPage + 1);
                } else {
                    newPage = parseInt(page);
                }

                // Update active page
                document.querySelectorAll('.pagination .page-item').forEach(item => {
                    item.classList.remove('active');
                });
                this.parentElement.classList.add('active');

                // Show the appropriate page of facilities
                const startIndex = (newPage - 1) * itemsPerPage;
                const endIndex = startIndex + itemsPerPage;
                const rows = facilityTable.querySelectorAll('tr:not(:first-child)');
                
                rows.forEach((row, index) => {
                    if (row.style.display !== 'none') {
                        row.style.display = (index >= startIndex && index < endIndex) ? '' : 'none';
                    }
                });
            });
        });

        // Activate first page
        const firstPageLink = paginationContainer.querySelector('.page-link[data-page="1"]');
        if (firstPageLink) {
            firstPageLink.click();
        }
    }

    // Add event listeners for filters
    document.getElementById('facilitySearch')?.addEventListener('input', updateFacilityDisplay);
    document.getElementById('facilityType')?.addEventListener('change', updateFacilityDisplay);
    document.getElementById('facilityLocation')?.addEventListener('change', updateFacilityDisplay);
    document.getElementById('facilityCampus')?.addEventListener('change', updateFacilityDisplay);

    // Handle facility selection
    selectFacilityBtn.addEventListener('click', function() {
        const selectedFacility = document.querySelector('input[name="facility"]:checked');
        if (selectedFacility) {
            const row = selectedFacility.closest('tr');
            const facilityName = row.cells[1].textContent;
            const facilityType = row.cells[2].textContent;
            const facilityCapacity = row.cells[5].textContent.trim();
            const facilityCampus = row.cells[4].textContent;
            
            selectedFacilityDisplay.value = `${facilityName} (${facilityType}, ${facilityCapacity}, ${facilityCampus})`;
            facilityInput.value = selectedFacility.value;
            
            const modal = bootstrap.Modal.getInstance(facilityModal);
            modal.hide();
        } else {
            alert('Please select a facility');
        }
    });

    // Clear facility selection when modal is closed
    facilityModal.addEventListener('hidden.bs.modal', function() {
        const selectedFacility = document.querySelector('input[name="facility"]:checked');
        if (selectedFacility) {
            selectedFacility.checked = false;
        }
    });

    // Initial display
    updateFacilityDisplay();
});
</script> 