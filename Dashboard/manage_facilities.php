<?php
// Include connection and auth files
include('connection.php');


// Get user's role and campus
$id = $_SESSION['id'];
$sql = "SELECT * FROM users WHERE id = $id";
$result = mysqli_query($connection, $sql);
$row = mysqli_fetch_assoc($result);
$mycampus = $row['campus'];
$role = $row['role'];

// Get campuses for filter
if($role === 'warefare'){       
    $campuses_query = mysqli_query($connection, "SELECT * FROM campus WHERE id = $mycampus ORDER BY name");
} else {
    $campuses_query = mysqli_query($connection, "SELECT * FROM campus ORDER BY name");
}
$campuses = [];
while ($campus = mysqli_fetch_assoc($campuses_query)) {
    $campuses[] = $campus;
}

// Get existing facility types
$types_query = "SELECT DISTINCT type FROM facility WHERE type IS NOT NULL ORDER BY type";
$types_result = mysqli_query($connection, $types_query);
$types = [];
while ($type = mysqli_fetch_assoc($types_result)) {
    $types[] = $type['type'];
}

// Get existing locations
$locations_query = "SELECT DISTINCT location FROM facility WHERE location IS NOT NULL ORDER BY location";
$locations_result = mysqli_query($connection, $locations_query);
$locations = [];
while ($location = mysqli_fetch_assoc($locations_result)) {
    $locations[] = $location['location'];
}
?>

<!-- Facilities Tab -->
<div class="tab-pane fade show active" id="facilities" role="tabpanel">
    <div class="row mb-3">
        <div class="col-md-6">
            <h5 class="card-title">Facilities</h5>
        </div>
        <div class="col-md-6 text-end">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFacilityModal">
                <i class="bi bi-plus-circle me-1"></i>Add Facility
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <input type="text" class="form-control" id="facilitySearch" placeholder="Search facilities...">
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="facilityCampus">
                                <option value="">All Campuses</option>
                                <?php foreach ($campuses as $campus): ?>
                                <option value="<?php echo $campus['id']; ?>"><?php echo htmlspecialchars($campus['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="facilityType">
                                <option value="">All Types</option>
                                <?php foreach ($types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Facilities Grid -->
    <div class="row" id="facilitiesGrid">
        <!-- Facilities will be loaded here -->
    </div>

    <!-- Pagination -->
    <div class="row mt-3">
        <div class="col-md-12">
            <nav aria-label="Facilities pagination">
                <ul class="pagination justify-content-center" id="facilitiesPagination">
                    <!-- Pagination will be loaded here -->
                </ul>
            </nav>
        </div>
    </div>
</div>

<!-- Modules Tab -->
<div class="tab-pane fade" id="modules" role="tabpanel">
    <div class="row mb-3">
        <div class="col-md-6">
            <h5 class="card-title">Modules</h5>
        </div>
        <div class="col-md-6 text-end">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModuleModal">
                <i class="bi bi-plus-circle me-1"></i>Add Module
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <input type="text" class="form-control" id="moduleSearch" placeholder="Search modules...">
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="moduleProgram">
                                <option value="">All Programs</option>
                                <?php foreach ($programs as $program): ?>
                                <option value="<?php echo $program['id']; ?>"><?php echo htmlspecialchars($program['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="moduleYear">
                                <option value="">All Years</option>
                                <option value="1">Year 1</option>
                                <option value="2">Year 2</option>
                                <option value="3">Year 3</option>
                                <option value="4">Year 4</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modules Table -->
    <div class="table-responsive">
        <table class="table table-hover" id="modulesTable">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Program</th>
                    <th>Year</th>
                    <th>Semester</th>
                    <th>Credits</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <!-- Modules will be loaded here -->
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="row mt-3">
        <div class="col-md-12">
            <nav aria-label="Modules pagination">
                <ul class="pagination justify-content-center" id="modulesPagination">
                    <!-- Pagination will be loaded here -->
                </ul>
            </nav>
        </div>
    </div>
</div>

<!-- Lecturers Tab -->
<div class="tab-pane fade" id="lecturers" role="tabpanel">
    <div class="row mb-3">
        <div class="col-md-6">
            <h5 class="card-title">Lecturers</h5>
        </div>
        <div class="col-md-6 text-end">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLecturerModal">
                <i class="bi bi-plus-circle me-1"></i>Add Lecturer
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <input type="text" class="form-control" id="lecturerSearch" placeholder="Search lecturers...">
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="lecturerDepartment">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $department): ?>
                                <option value="<?php echo $department['id']; ?>"><?php echo htmlspecialchars($department['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="lecturerCampus">
                                <option value="">All Campuses</option>
                                <?php foreach ($campuses as $campus): ?>
                                <option value="<?php echo $campus['id']; ?>"><?php echo htmlspecialchars($campus['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lecturers Table -->
    <div class="table-responsive">
        <table class="table table-hover" id="lecturersTable">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Department</th>
                    <th>Campus</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <!-- Lecturers will be loaded here -->
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="row mt-3">
        <div class="col-md-12">
            <nav aria-label="Lecturers pagination">
                <ul class="pagination justify-content-center" id="lecturersPagination">
                    <!-- Pagination will be loaded here -->
                </ul>
            </nav>
        </div>
    </div>
</div>

<!-- Add Facility Modal -->
<div class="modal fade" id="addFacilityModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Facility</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="facilityErrorAlert" class="alert alert-danger d-none"></div>
                <form id="addFacilityForm" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" name="name" required 
                               pattern="[A-Za-z0-9\s-]+" 
                               title="Only letters, numbers, spaces and hyphens are allowed">
                        <div class="invalid-feedback">Please enter a valid facility name</div>
                        <div class="form-text">Facility names must be unique within the same campus and location</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select class="form-select" name="type" required>
                            <option value="">Select Type</option>
                            <?php foreach ($types as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                            <?php endforeach; ?>
                            <option value="other">Other (specify)</option>
                        </select>
                        <div class="invalid-feedback">Please select facility type</div>
                        <input type="text" class="form-control mt-2 d-none" id="otherType" placeholder="Enter facility type">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Capacity</label>
                        <input type="number" class="form-control" name="capacity" min="1" required>
                        <div class="invalid-feedback">Please enter a valid capacity (minimum 1)</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <select class="form-select" name="location" required>
                            <option value="">Select Location</option>
                            <?php foreach ($locations as $location): ?>
                            <option value="<?php echo htmlspecialchars($location); ?>"><?php echo htmlspecialchars($location); ?></option>
                            <?php endforeach; ?>
                            <option value="other">Other (specify)</option>
                        </select>
                        <div class="invalid-feedback">Please enter facility location</div>
                        <input type="text" class="form-control mt-2 d-none" id="otherLocation" placeholder="Enter location">
                        <div class="form-text">Location must be unique for each facility name within a campus</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Campus</label>
                        <select class="form-select" name="campus_id" required>
                            <option value="">Select Campus</option>
                            <?php foreach ($campuses as $campus): ?>
                            <option value="<?php echo $campus['id']; ?>"><?php echo htmlspecialchars($campus['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Please select a campus</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="saveFacility">Save Facility</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Edit Facility Modal -->
<div class="modal fade" id="editFacilityModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Facility</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="editFacilityErrorAlert" class="alert alert-danger d-none"></div>
                <form id="editFacilityForm" class="needs-validation" novalidate>
                    <input type="hidden" name="id" id="editFacilityId">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" name="name" id="editFacilityName" required 
                               pattern="[A-Za-z0-9\s-]+" 
                               title="Only letters, numbers, spaces and hyphens are allowed">
                        <div class="invalid-feedback">Please enter a valid facility name</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select class="form-select" name="type" id="editFacilityType" required>
                            <option value="">Select Type</option>
                            <?php foreach ($types as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                            <?php endforeach; ?>
                            <option value="other">Other (specify)</option>
                        </select>
                        <div class="invalid-feedback">Please select facility type</div>
                        <input type="text" class="form-control mt-2 d-none" id="editOtherType" placeholder="Enter facility type">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Capacity</label>
                        <input type="number" class="form-control" name="capacity" id="editFacilityCapacity" min="1" required>
                        <div class="invalid-feedback">Please enter a valid capacity (minimum 1)</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <select class="form-select" name="location" id="editFacilityLocation" required>
                            <option value="">Select Location</option>
                            <?php foreach ($locations as $location): ?>
                            <option value="<?php echo htmlspecialchars($location); ?>"><?php echo htmlspecialchars($location); ?></option>
                            <?php endforeach; ?>
                            <option value="other">Other (specify)</option>
                        </select>
                        <div class="invalid-feedback">Please enter facility location</div>
                        <input type="text" class="form-control mt-2 d-none" id="editOtherLocation" placeholder="Enter location">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Campus</label>
                        <select class="form-select" name="campus_id" id="editFacilityCampus" required>
                            <option value="">Select Campus</option>
                            <?php foreach ($campuses as $campus): ?>
                            <option value="<?php echo $campus['id']; ?>"><?php echo htmlspecialchars($campus['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Please select a campus</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="updateFacility">Update Facility</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteFacilityModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this facility? This action cannot be undone.</p>
                <input type="hidden" id="deleteFacilityId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/utils.js"></script>
<script>
let currentPage = 1;
const itemsPerPage = 12;

function loadFacilities(page = 1) {
    const search = document.getElementById('facilitySearch').value;
    const campus = document.getElementById('facilityCampus').value;
    const type = document.getElementById('facilityType').value;

    fetch(`get_facilities.php?page=${page}&limit=${itemsPerPage}&search=${search}&campus=${campus}&type=${type}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Response text:', text);
                    throw new Error('Invalid JSON response from server');
                }
            });
        })
        .then(data => {
            if (data.success) {
                displayFacilities(data.data.facilities);
                displayPagination(data.data.pagination);
            } else {
                showAlert('Error', data.message || 'Failed to load facilities', 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error', 'Failed to load facilities. Please try again.', 'danger');
        });
}

function displayFacilities(facilities) {
    const grid = document.getElementById('facilitiesGrid');
    grid.innerHTML = '';

    facilities.forEach(facility => {    
        const card = `
            <div class="col-md-4 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">${facility.name}</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <i class="bi bi-building me-2 text-primary"></i>
                            <span class="badge bg-info">${facility.type}</span>
                        </div>
                        <div class="mb-2">
                            <i class="bi bi-people-fill me-2 text-primary"></i>
                            <strong>Capacity:</strong> ${facility.capacity}
                        </div>
                        <div class="mb-2">
                            <i class="bi bi-geo-alt-fill me-2 text-primary"></i>
                            <strong>Location:</strong> ${facility.location}
                        </div>
                        <div class="mb-3">
                            <i class="bi bi-geo me-2 text-primary"></i>
                            <strong>Campus:</strong> ${facility.campus_name}
                        </div>
                        <div class="btn-group w-100">
                            <button class="btn btn-outline-primary" onclick="editFacility(${facility.id})">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            <button class="btn btn-outline-danger" onclick="deleteFacility(${facility.id})">
                                <i class="bi bi-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        grid.innerHTML += card;
    });
}

function displayPagination(pagination) {
    const paginationEl = document.getElementById('facilitiesPagination');
    paginationEl.innerHTML = '';

    // Previous button
    paginationEl.innerHTML += `
        <li class="page-item ${pagination.current_page === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="loadFacilities(${pagination.current_page - 1})">Previous</a>
        </li>
    `;

    // Page numbers
    for (let i = 1; i <= pagination.last_page; i++) {
        paginationEl.innerHTML += `
            <li class="page-item ${pagination.current_page === i ? 'active' : ''}">
                <a class="page-link" href="#" onclick="loadFacilities(${i})">${i}</a>
            </li>
        `;
    }

    // Next button
    paginationEl.innerHTML += `
        <li class="page-item ${pagination.current_page === pagination.last_page ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="loadFacilities(${pagination.current_page + 1})">Next</a>
        </li>
    `;
}

// Real-time filtering for facilities
document.getElementById('facilitySearch').addEventListener('input', debounce(() => loadFacilities(1), 300));
document.getElementById('facilityCampus').addEventListener('change', () => loadFacilities(1));
document.getElementById('facilityType').addEventListener('change', () => loadFacilities(1));

// Handle type selection in add form
document.querySelector('select[name="type"]').addEventListener('change', function() {
    const otherTypeInput = document.getElementById('otherType');
    if (this.value === 'other') {
        otherTypeInput.classList.remove('d-none');
        otherTypeInput.required = true;
    } else {
        otherTypeInput.classList.add('d-none');
        otherTypeInput.required = false;
    }
});

// Handle location selection in add form
document.querySelector('select[name="location"]').addEventListener('change', function() {
    const otherLocationInput = document.getElementById('otherLocation');
    if (this.value === 'other') {
        otherLocationInput.classList.remove('d-none');
        otherLocationInput.required = true;
    } else {
        otherLocationInput.classList.add('d-none');
        otherLocationInput.required = false;
    }
});

// Handle type selection in edit form
document.querySelector('#editFacilityType').addEventListener('change', function() {
    const otherTypeInput = document.getElementById('editOtherType');
    if (this.value === 'other') {
        otherTypeInput.classList.remove('d-none');
        otherTypeInput.required = true;
    } else {
        otherTypeInput.classList.add('d-none');
        otherTypeInput.required = false;
    }
});

// Handle location selection in edit form
document.querySelector('#editFacilityLocation').addEventListener('change', function() {
    const otherLocationInput = document.getElementById('editOtherLocation');
    if (this.value === 'other') {
        otherLocationInput.classList.remove('d-none');
        otherLocationInput.required = true;
    } else {
        otherLocationInput.classList.add('d-none');
        otherLocationInput.required = false;
    }
});

// Update the saveFacility event listener to handle custom type/location
document.getElementById('saveFacility').addEventListener('click', function() {
    const form = document.getElementById('addFacilityForm');
    const errorAlert = document.getElementById('facilityErrorAlert');
    
    // Reset previous validation state
    form.classList.remove('was-validated');
    errorAlert.classList.add('d-none');
    errorAlert.innerHTML = '';
    
    // Check form validation
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
    }
    
    const formData = new FormData(form);
    
    // Handle custom type
    if (formData.get('type') === 'other') {
        const otherType = document.getElementById('otherType').value;
        if (!otherType) {
            document.getElementById('otherType').classList.add('is-invalid');
            return;
        }
        formData.set('type', otherType);
    }
    
    // Handle custom location
    if (formData.get('location') === 'other') {
        const otherLocation = document.getElementById('otherLocation').value;
        if (!otherLocation) {
            document.getElementById('otherLocation').classList.add('is-invalid');
            return;
        }
        formData.set('location', otherLocation);
    }
    
    const submitButton = this;
    const originalText = submitButton.innerHTML;
    
    // Disable submit button and show loading state
    submitButton.disabled = true;
    submitButton.innerHTML = `
        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
        Saving...
    `;

    fetch('add_facility.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Response text:', text);
                throw new Error('Invalid JSON response from server');
            }
        });
    })
    .then(data => {
        if (data.success) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('addFacilityModal'));
            modal.hide();
            form.reset();
            loadFacilities(currentPage);
            showAlert('Success', 'Facility added successfully', 'success');
        } else {
            errorAlert.classList.remove('d-none');
            errorAlert.innerHTML = `
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>Error:</strong> ${data.message}
            `;
            errorAlert.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        errorAlert.classList.remove('d-none');
        errorAlert.innerHTML = `
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Error:</strong> Failed to add facility. Please try again.
        `;
    })
    .finally(() => {
        submitButton.disabled = false;
        submitButton.innerHTML = originalText;
    });
});

// Update the editFacility function to handle custom type/location
function editFacility(id) {
    fetch(`get_facility.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const facility = data.data;
                
                // Populate form
                document.getElementById('editFacilityId').value = facility.id;
                document.getElementById('editFacilityName').value = facility.name;
                
                // Handle type
                const typeSelect = document.getElementById('editFacilityType');
                const otherTypeInput = document.getElementById('editOtherType');
                if (typeSelect.querySelector(`option[value="${facility.type}"]`)) {
                    typeSelect.value = facility.type;
                    otherTypeInput.classList.add('d-none');
                } else {
                    typeSelect.value = 'other';
                    otherTypeInput.value = facility.type;
                    otherTypeInput.classList.remove('d-none');
                }
                
                document.getElementById('editFacilityCapacity').value = facility.capacity;
                
                // Handle location
                const locationSelect = document.getElementById('editFacilityLocation');
                const otherLocationInput = document.getElementById('editOtherLocation');
                if (locationSelect.querySelector(`option[value="${facility.location}"]`)) {
                    locationSelect.value = facility.location;
                    otherLocationInput.classList.add('d-none');
                } else {
                    locationSelect.value = 'other';
                    otherLocationInput.value = facility.location;
                    otherLocationInput.classList.remove('d-none');
                }
                
                document.getElementById('editFacilityCampus').value = facility.campus_id;
                
                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('editFacilityModal'));
                modal.show();
            } else {
                showAlert('Error', data.message || 'Failed to load facility details', 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error', 'Failed to load facility details', 'danger');
        });
}

// Update the updateFacility event listener to handle custom type/location
document.getElementById('updateFacility').addEventListener('click', function() {
    const form = document.getElementById('editFacilityForm');
    const errorAlert = document.getElementById('editFacilityErrorAlert');
    
    // Reset previous validation state
    form.classList.remove('was-validated');
    errorAlert.classList.add('d-none');
    errorAlert.innerHTML = '';
    
    // Check form validation
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
    }
    
    const formData = new FormData(form);
    
    // Handle custom type
    if (formData.get('type') === 'other') {
        const otherType = document.getElementById('editOtherType').value;
        if (!otherType) {
            document.getElementById('editOtherType').classList.add('is-invalid');
            return;
        }
        formData.set('type', otherType);
    }
    
    // Handle custom location
    if (formData.get('location') === 'other') {
        const otherLocation = document.getElementById('editOtherLocation').value;
        if (!otherLocation) {
            document.getElementById('editOtherLocation').classList.add('is-invalid');
            return;
        }
        formData.set('location', otherLocation);
    }
    
    const submitButton = this;
    const originalText = submitButton.innerHTML;
    
    // Disable submit button and show loading state
    submitButton.disabled = true;
    submitButton.innerHTML = `
        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
        Updating...
    `;

    fetch('update_facility.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('editFacilityModal'));
            modal.hide();
            loadFacilities(currentPage);
            showAlert('Success', 'Facility updated successfully', 'success');
        } else {
            errorAlert.classList.remove('d-none');
            errorAlert.innerHTML = data.message || 'Failed to update facility';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        errorAlert.classList.remove('d-none');
        errorAlert.innerHTML = 'Failed to update facility. Please try again.';
    })
    .finally(() => {
        submitButton.disabled = false;
        submitButton.innerHTML = originalText;
    });
});

function deleteFacility(id) {
    // Set the facility ID in the delete modal
    document.getElementById('deleteFacilityId').value = id;
    
    // Show the delete confirmation modal
    const modal = new bootstrap.Modal(document.getElementById('deleteFacilityModal'));
    modal.show();
}

// Handle facility deletion
document.getElementById('confirmDelete').addEventListener('click', function() {
    console.log('Delete button clicked');
    const id = document.getElementById('deleteFacilityId').value;
    console.log('Facility ID to delete:', id);
    
    const submitButton = this;
    const originalText = submitButton.innerHTML;
    
    // Disable submit button and show loading state
    submitButton.disabled = true;
    submitButton.innerHTML = `
        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
        Deleting...
    `;
    console.log('Button state updated to loading');

    // Create form data
    const formData = new FormData();
    formData.append('id', id);
    console.log('FormData created with ID:', id);

    console.log('Sending delete request to delete_facility.php');
    fetch('delete_facility.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response received:', response);
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        
        if (!response.ok) {
            console.error('Response not OK:', response.status);
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return response.text().then(text => {
            console.log('Response text:', text);
            try {
                const data = JSON.parse(text);
                console.log('Parsed JSON data:', data);
                return data;
            } catch (e) {
                console.error('JSON parse error:', e);
                console.error('Raw response text:', text);
                throw new Error('Invalid JSON response from server');
            }
        });
    })
    .then(data => {
        console.log('Processing response data:', data);
        if (data.success) {
            console.log('Delete successful, closing modal and refreshing list');
            const modal = bootstrap.Modal.getInstance(document.getElementById('deleteFacilityModal'));
            modal.hide();
            loadFacilities(currentPage);
            showAlert('Success', 'Facility deleted successfully', 'success');
        } else {
            console.error('Delete failed:', data.message);
            showAlert('Error', data.message || 'Failed to delete facility', 'danger');
        }
    })
    .catch(error => {
        console.error('Error in delete process:', error);
        console.error('Error stack:', error.stack);
        showAlert('Error', 'Failed to delete facility. Please try again.', 'danger');
    })
    .finally(() => {
        console.log('Resetting button state');
        submitButton.disabled = false;
        submitButton.innerHTML = originalText;
    });
});

// Reset edit form when modal is closed
document.getElementById('editFacilityModal').addEventListener('hidden.bs.modal', function() {
    const form = document.getElementById('editFacilityForm');
    const errorAlert = document.getElementById('editFacilityErrorAlert');
    form.reset();
    form.classList.remove('was-validated');
    errorAlert.classList.add('d-none');
    errorAlert.innerHTML = '';
    document.querySelectorAll('#editFacilityForm input, #editFacilityForm select').forEach(input => {
        input.classList.remove('is-valid', 'is-invalid');
    });
});

// Initial load
document.addEventListener('DOMContentLoaded', function() {
    loadFacilities();
});

// Debounce function to prevent too many API calls
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

// Load modules function
function loadModules(page = 1) {
    const search = document.getElementById('moduleSearch').value;
    const program = document.getElementById('moduleProgram').value;
    const year = document.getElementById('moduleYear').value;

    fetch(`get_modules.php?page=${page}&limit=10&search=${search}&program=${program}&year=${year}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayModules(data.data.modules);
                displayModulePagination(data.data.pagination);
            } else {
                showAlert('Error', data.message || 'Failed to load modules', 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error', 'Failed to load modules. Please try again.', 'danger');
        });
}

// Display modules function
function displayModules(modules) {
    const tbody = document.querySelector('#modulesTable tbody');
    tbody.innerHTML = '';

    modules.forEach(module => {
        const row = `
            <tr>
                <td>${module.code}</td>
                <td>${module.name}</td>
                <td>${module.program_name}</td>
                <td>${module.year}</td>
                <td>${module.semester}</td>
                <td>${module.credits}</td>
                <td>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-outline-primary" onclick="editModule(${module.id})">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteModule(${module.id})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
        tbody.innerHTML += row;
    });
}

// Load lecturers function
function loadLecturers(page = 1) {
    const search = document.getElementById('lecturerSearch').value;
    const department = document.getElementById('lecturerDepartment').value;
    const campus = document.getElementById('lecturerCampus').value;

    fetch(`get_lecturers.php?page=${page}&limit=10&search=${search}&department=${department}&campus=${campus}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayLecturers(data.data.lecturers);
                displayLecturerPagination(data.data.pagination);
            } else {
                showAlert('Error', data.message || 'Failed to load lecturers', 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error', 'Failed to load lecturers. Please try again.', 'danger');
        });
}

// Display lecturers function
function displayLecturers(lecturers) {
    const tbody = document.querySelector('#lecturersTable tbody');
    tbody.innerHTML = '';

    lecturers.forEach(lecturer => {
        const row = `
            <tr>
                <td>${lecturer.name}</td>
                <td>${lecturer.email}</td>
                <td>${lecturer.phone}</td>
                <td>${lecturer.department_name}</td>
                <td>${lecturer.campus_name}</td>
                <td>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-outline-primary" onclick="editLecturer(${lecturer.id})">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteLecturer(${lecturer.id})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
        tbody.innerHTML += row;
    });
}

// Initial load for all tabs
document.addEventListener('DOMContentLoaded', function() {
    loadFacilities();
    loadModules();
    loadLecturers();
});

// Handle tab changes
document.querySelectorAll('a[data-bs-toggle="tab"]').forEach(tab => {
    tab.addEventListener('shown.bs.tab', function(e) {
        const target = e.target.getAttribute('data-bs-target');
        if (target === '#facilities') {
            loadFacilities();
        } else if (target === '#modules') {
            loadModules();
        } else if (target === '#lecturers') {
            loadLecturers();
        }
    });
});
</script> 