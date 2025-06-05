<?php
include('connection.php');

// Get facilities
$facilities_query = "SELECT f.*, c.name as campus_name 
                    FROM facility f 
                    LEFT JOIN campus c ON f.campus_id = c.id 
                    WHERE f.type = 'classroom' OR f.type = 'Lecture Hall' OR f.type = 'Laboratory'";
$facilities_result = mysqli_query($connection, $facilities_query);
?>

<!-- Facility Modal -->
<div class="modal fade" id="facilityModal" tabindex="-1" aria-labelledby="facilityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="facilityModalLabel">Select Facility</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>Total Students:</strong> <span id="totalStudents">0</span>
                            <br>
                            <strong>Required Capacity:</strong> <span id="requiredCapacity">0</span>
                        </div>
                        <div>
                            <span id="availableFacilitiesCount" class="badge bg-primary">0 facilities available</span>
                        </div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control" id="facilitySearch" placeholder="Search facilities...">
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" id="facilityTypeFilter">
                            <option value="">All Types</option>
                            <option value="classroom">Classroom</option>
                            <option value="Lecture Hall">Lecture Hall</option>
                            <option value="Laboratory">Laboratory</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" id="facilityLocationFilter">
                            <option value="">All Locations</option>
                            <?php 
                            mysqli_data_seek($facilities_result, 0);
                            $locations = [];
                            while($facility = mysqli_fetch_assoc($facilities_result)) {
                                if (!empty($facility['location']) && !in_array($facility['location'], $locations)) {
                                    $locations[] = $facility['location'];
                                    echo "<option value='" . htmlspecialchars($facility['location']) . "'>" . 
                                         htmlspecialchars($facility['location']) . "</option>";
                                }
                            }
                            ?>
                        </select>
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
                                <th>Capacity</th>
                            </tr>
                        </thead>
                        <tbody id="facilityTableBody">
                            <?php 
                            mysqli_data_seek($facilities_result, 0);
                            while($facility = mysqli_fetch_assoc($facilities_result)): 
                            ?>
                            <tr class="facility-row">
                                <td>
                                    <div class="form-check">
                                        <input class="form-check-input facility-radio" type="radio" 
                                               name="facility" value="<?php echo $facility['id']; ?>" 
                                               data-capacity="<?php echo $facility['capacity']; ?>"
                                               id="facility<?php echo $facility['id']; ?>">
                                        <label class="form-check-label" for="facility<?php echo $facility['id']; ?>">
                                            <?php echo htmlspecialchars($facility['name']); ?>
                                        </label>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($facility['name']); ?></td>
                                <td><?php echo htmlspecialchars($facility['type']); ?></td>
                                <td><?php echo htmlspecialchars($facility['location']); ?></td>
                                <td><?php echo $facility['capacity']; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div>
                            <span class="text-muted">Showing <span id="facilityStartRecord">1</span> to <span id="facilityEndRecord">10</span> of <span id="facilityTotalRecords">0</span> facilities</span>
                        </div>
                        <nav aria-label="Facility pagination">
                            <ul class="pagination mb-0">
                                <li class="page-item" id="facilityPrevPage">
                                    <a class="page-link" href="#" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                <li class="page-item" id="facilityNextPage">
                                    <a class="page-link" href="#" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <div class="d-flex justify-content-between align-items-center w-100">
                    <div>
                        <span class="badge bg-info" id="availableFacilitiesCount">0 facilities available</span>
                    </div>
                    <div>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="confirmFacility">Confirm Selection</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Add pagination variables
let currentFacilityPage = 1;
const facilitiesPerPage = 10;
let totalFacilities = 0;

// Function to update facility pagination
function updateFacilityPagination() {
    const rows = document.querySelectorAll('#facilityTableBody tr');
    totalFacilities = rows.length;
    const totalPages = Math.ceil(totalFacilities / facilitiesPerPage);
    
    // Update pagination info
    document.getElementById('facilityTotalRecords').textContent = totalFacilities;
    document.getElementById('facilityStartRecord').textContent = ((currentFacilityPage - 1) * facilitiesPerPage) + 1;
    document.getElementById('facilityEndRecord').textContent = Math.min(currentFacilityPage * facilitiesPerPage, totalFacilities);
    
    // Update pagination buttons
    document.getElementById('facilityPrevPage').classList.toggle('disabled', currentFacilityPage === 1);
    document.getElementById('facilityNextPage').classList.toggle('disabled', currentFacilityPage === totalPages);
    
    // Show/hide rows based on current page
    rows.forEach((row, index) => {
        const start = (currentFacilityPage - 1) * facilitiesPerPage;
        const end = start + facilitiesPerPage;
        row.style.display = (index >= start && index < end) ? '' : 'none';
    });
}

// Add pagination event listeners
document.getElementById('facilityPrevPage').addEventListener('click', function(e) {
    e.preventDefault();
    if (currentFacilityPage > 1) {
        currentFacilityPage--;
        updateFacilityPagination();
    }
});

document.getElementById('facilityNextPage').addEventListener('click', function(e) {
    e.preventDefault();
    const totalPages = Math.ceil(totalFacilities / facilitiesPerPage);
    if (currentFacilityPage < totalPages) {
        currentFacilityPage++;
        updateFacilityPagination();
    }
});

// Update pagination when modal is shown
document.getElementById('facilityModal').addEventListener('show.bs.modal', function() {
    currentFacilityPage = 1;
    updateFacilityPagination();
});

// Update pagination when search or filters change
document.getElementById('facilitySearch').addEventListener('input', function() {
    currentFacilityPage = 1;
    updateFacilityPagination();
});

// Add filter event listeners
document.getElementById('facilityTypeFilter').addEventListener('change', function() {
    currentFacilityPage = 1;
    updateFacilityPagination();
});

document.getElementById('facilityLocationFilter').addEventListener('change', function() {
    currentFacilityPage = 1;
    updateFacilityPagination();
});

// Update facility search functionality to include filters
document.getElementById('facilitySearch').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const totalStudents = parseInt(document.getElementById('totalStudents').textContent);
    const selectedType = document.getElementById('facilityTypeFilter').value.toLowerCase();
    const selectedLocation = document.getElementById('facilityLocationFilter').value.toLowerCase();
    const rows = document.querySelectorAll('#facilityTableBody tr');
    let availableCount = 0;

    rows.forEach(row => {
        const capacity = parseInt(row.querySelector('.facility-radio').dataset.capacity) || 0;
        const name = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
        const type = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
        const location = row.querySelector('td:nth-child(4)').textContent.toLowerCase();
        
        const matchesSearch = name.includes(searchTerm) || 
                            type.includes(searchTerm) || 
                            location.includes(searchTerm);
        const matchesType = !selectedType || type === selectedType;
        const matchesLocation = !selectedLocation || location === selectedLocation;
        
        if (capacity >= totalStudents && matchesSearch && matchesType && matchesLocation) {
            row.style.display = '';
            availableCount++;
        } else {
            row.style.display = 'none';
        }
    });

    document.getElementById('availableFacilitiesCount').textContent = 
        `${availableCount} facilities available`;
});

// Update facility type filter
document.getElementById('facilityTypeFilter').addEventListener('change', function() {
    const searchTerm = document.getElementById('facilitySearch').value.toLowerCase();
    const totalStudents = parseInt(document.getElementById('totalStudents').textContent);
    const selectedType = this.value.toLowerCase();
    const selectedLocation = document.getElementById('facilityLocationFilter').value.toLowerCase();
    const rows = document.querySelectorAll('#facilityTableBody tr');
    let availableCount = 0;

    rows.forEach(row => {
        const capacity = parseInt(row.querySelector('.facility-radio').dataset.capacity) || 0;
        const name = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
        const type = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
        const location = row.querySelector('td:nth-child(4)').textContent.toLowerCase();
        
        const matchesSearch = name.includes(searchTerm) || 
                            type.includes(searchTerm) || 
                            location.includes(searchTerm);
        const matchesType = !selectedType || type === selectedType;
        const matchesLocation = !selectedLocation || location === selectedLocation;
        
        if (capacity >= totalStudents && matchesSearch && matchesType && matchesLocation) {
            row.style.display = '';
            availableCount++;
        } else {
            row.style.display = 'none';
        }
    });

    document.getElementById('availableFacilitiesCount').textContent = 
        `${availableCount} facilities available`;
});

// Update facility location filter
document.getElementById('facilityLocationFilter').addEventListener('change', function() {
    const searchTerm = document.getElementById('facilitySearch').value.toLowerCase();
    const totalStudents = parseInt(document.getElementById('totalStudents').textContent);
    const selectedType = document.getElementById('facilityTypeFilter').value.toLowerCase();
    const selectedLocation = this.value.toLowerCase();
    const rows = document.querySelectorAll('#facilityTableBody tr');
    let availableCount = 0;

    rows.forEach(row => {
        const capacity = parseInt(row.querySelector('.facility-radio').dataset.capacity) || 0;
        const name = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
        const type = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
        const location = row.querySelector('td:nth-child(4)').textContent.toLowerCase();
        
        const matchesSearch = name.includes(searchTerm) || 
                            type.includes(searchTerm) || 
                            location.includes(searchTerm);
        const matchesType = !selectedType || type === selectedType;
        const matchesLocation = !selectedLocation || location === selectedLocation;
        
        if (capacity >= totalStudents && matchesSearch && matchesType && matchesLocation) {
            row.style.display = '';
            availableCount++;
        } else {
            row.style.display = 'none';
        }
    });

    document.getElementById('availableFacilitiesCount').textContent = 
        `${availableCount} facilities available`;
});

// Update facility display function to include filters
function updateFacilityDisplay() {
    let totalStudents = 0;
    if (typeof selectedGroupsData !== 'undefined') {
        selectedGroupsData.forEach(group => {
            totalStudents += parseInt(group.size) || 0;
        });
    }
    
    document.getElementById('totalStudents').textContent = totalStudents;
    document.getElementById('requiredCapacity').textContent = totalStudents;

    const searchTerm = document.getElementById('facilitySearch').value.toLowerCase();
    const selectedType = document.getElementById('facilityTypeFilter').value.toLowerCase();
    const selectedLocation = document.getElementById('facilityLocationFilter').value.toLowerCase();
    const rows = document.querySelectorAll('#facilityTableBody tr');
    let availableCount = 0;

    rows.forEach(row => {
        const capacity = parseInt(row.querySelector('.facility-radio').dataset.capacity) || 0;
        const name = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
        const type = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
        const location = row.querySelector('td:nth-child(4)').textContent.toLowerCase();
        
        const matchesSearch = name.includes(searchTerm) || 
                            type.includes(searchTerm) || 
                            location.includes(searchTerm);
        const matchesType = !selectedType || type === selectedType;
        const matchesLocation = !selectedLocation || location === selectedLocation;
        
        if (capacity >= totalStudents && matchesSearch && matchesType && matchesLocation) {
            row.style.display = '';
            availableCount++;
        } else {
            row.style.display = 'none';
        }
    });

    document.getElementById('availableFacilitiesCount').textContent = 
        `${availableCount} facilities available`;
}

// Confirm facility selection
document.getElementById('confirmFacility').addEventListener('click', function() {
    const selectedFacility = document.querySelector('.facility-radio:checked');
    if (!selectedFacility) {
        alert('Please select a facility');
        return;
    }

    const facilityId = selectedFacility.value;
    const facilityName = selectedFacility.dataset.name;
    const facilityType = selectedFacility.dataset.type;
    const facilityCapacity = selectedFacility.dataset.capacity;
    const facilityLocation = selectedFacility.dataset.location;
    const facilityCampus = selectedFacility.dataset.campus;
    const totalStudents = parseInt(document.getElementById('totalStudents').textContent);

    if (parseInt(facilityCapacity) < totalStudents) {
        alert('Selected facility does not have sufficient capacity for the selected groups');
        return;
    }

    document.getElementById('facility').value = facilityId;
    document.getElementById('selectedFacilityDisplay').value = 
        `${facilityName} (${facilityType}) - ${facilityLocation}, ${facilityCampus} - Capacity: ${facilityCapacity}`;

    // Close modal
    bootstrap.Modal.getInstance(document.getElementById('facilityModal')).hide();
    
    // Enable module selection
    if (typeof window.updateModuleButton === 'function') {
        window.updateModuleButton();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('facilityModal').addEventListener('show.bs.modal', function() {
        document.getElementById('facilitySearch').value = '';
        updateFacilityDisplay();
    });

    if (typeof window.updateFacilityButton === 'function') {
        const originalUpdateFacilityButton = window.updateFacilityButton;
        window.updateFacilityButton = function() {
            originalUpdateFacilityButton();
            updateFacilityDisplay();
        };
    }
});
</script> 