<?php
include('connection.php');

// Get campuses for selection
$campuses_query = "SELECT * FROM campus ORDER BY name";
$campuses_result = mysqli_query($connection, $campuses_query);
$campuses = [];
while ($campus = mysqli_fetch_assoc($campuses_result)) {
    $campuses[] = $campus;
}
?>

<div class="card">
    <div class="card-body">
        <h5 class="card-title">Manage Lecturers</h5>

        <!-- Search and Filter Form -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <input type="text" class="form-control" id="lecturerSearch" placeholder="Search lecturers...">
            </div>
            <div class="col-md-2">
                <select class="form-select" id="lecturerStatus">
                    <option value="">All Status</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-primary w-100" onclick="loadLecturers(1)">
                    <i class="bi bi-search me-1"></i>Search
                </button>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-success w-100" onclick="window.location.href='add_lecturer.php'">
                    <i class="bi bi-plus-circle me-1"></i>Add Lecturer
                </button>
            </div>
        </div>

        <!-- Lecturers Table -->
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="lecturersList">
                    <!-- Data will be loaded here -->
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div id="lecturerPagination" class="mt-4">
            <!-- Pagination will be loaded here -->
        </div>
    </div>
</div>

<!-- Edit Lecturer Modal -->
<div class="modal fade" id="editLecturerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Lecturer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editLecturerForm">
                    <input type="hidden" id="editLecturerId" name="id">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" id="editLecturerName" name="names" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" id="editLecturerEmail" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="tel" class="form-control" id="editLecturerPhone" name="phone">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="editLecturerStatus" name="active" required>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="updateLecturer()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Lecturer Modal -->
<div class="modal fade" id="deleteLecturerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Lecturer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this lecturer? This action cannot be undone.</p>
                <input type="hidden" id="deleteLecturerId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmDeleteLecturer()">Delete</button>
            </div>
        </div>
    </div>
</div>

<script>
// Function to display lecturer pagination
function displayLecturerPagination(pagination) {
    const paginationContainer = document.getElementById('lecturerPagination');
    if (!paginationContainer) return;

    let html = '<ul class="pagination justify-content-center">';
    
    // Previous button
    html += `
        <li class="page-item ${pagination.current_page === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${pagination.current_page - 1}" onclick="loadLecturers(${pagination.current_page - 1})">
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
                    <a class="page-link" href="#" data-page="${i}" onclick="loadLecturers(${i})">${i}</a>
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
            <a class="page-link" href="#" data-page="${pagination.current_page + 1}" onclick="loadLecturers(${pagination.current_page + 1})">
                <i class="bi bi-chevron-right"></i>
            </a>
        </li>
    `;

    html += '</ul>';
    paginationContainer.innerHTML = html;
}

// Function to load lecturers with pagination
async function loadLecturers(page = 1) {
    try {
        const search = document.getElementById('lecturerSearch').value;
        const status = document.getElementById('lecturerStatus').value;

        const response = await fetch(`get_lecturers.php?search=${search}&status=${status}&page=${page}`);
        const data = await response.json();

        if (data.success) {
            const lecturerTableBody = document.getElementById('lecturersList');
            lecturerTableBody.innerHTML = '';

            data.data.lecturers.forEach(lecturer => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${lecturer.names}</td>
                    <td>${lecturer.email}</td>
                    <td>${lecturer.phone}</td>
                    <td>
                        <span class="badge ${lecturer.status === 'active' ? 'bg-success' : 'bg-danger'}">
                            ${lecturer.status}
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="editLecturer(${lecturer.id})">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="deleteLecturer(${lecturer.id})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                `;
                lecturerTableBody.appendChild(row);
            });

            displayLecturerPagination(data.data.pagination);
        } else {
            throw new Error(data.message || 'Failed to load lecturers');
        }
    } catch (error) {
        console.error('Error loading lecturers:', error);
        alert('Error: ' + error.message);
    }
}

// Function to edit lecturer
async function editLecturer(id) {
    try {
        const response = await fetch(`get_lecturer.php?id=${id}`);
        const data = await response.json();

        if (data.success) {
            const lecturer = data.data;
            document.getElementById('editLecturerId').value = lecturer.id;
            document.getElementById('editLecturerName').value = lecturer.names;
            document.getElementById('editLecturerEmail').value = lecturer.email;
            document.getElementById('editLecturerPhone').value = lecturer.phone;
            document.getElementById('editLecturerStatus').value = lecturer.status;

            const modal = new bootstrap.Modal(document.getElementById('editLecturerModal'));
            modal.show();
        } else {
            throw new Error(data.message || 'Failed to load lecturer details');
        }
    } catch (error) {
        console.error('Error loading lecturer details:', error);
        alert('Error: ' + error.message);
    }
}

// Function to update lecturer
async function updateLecturer() {
    try {
        const form = document.getElementById('editLecturerForm');
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        const response = await fetch('update_lecturer.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('editLecturerModal'));
            modal.hide();
            loadLecturers();
            alert('Lecturer updated successfully');
        } else {
            throw new Error(result.message || 'Failed to update lecturer');
        }
    } catch (error) {
        console.error('Error updating lecturer:', error);
        alert('Error: ' + error.message);
    }
}

// Function to delete lecturer
function deleteLecturer(id) {
    document.getElementById('deleteLecturerId').value = id;
    const modal = new bootstrap.Modal(document.getElementById('deleteLecturerModal'));
    modal.show();
}

// Function to confirm lecturer deletion
async function confirmDeleteLecturer() {
    try {
        const id = document.getElementById('deleteLecturerId').value;
        const response = await fetch('delete_lecturer.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id })
        });

        const result = await response.json();

        if (result.success) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('deleteLecturerModal'));
            modal.hide();
            loadLecturers();
            alert('Lecturer deleted successfully');
        } else {
            throw new Error(result.message || 'Failed to delete lecturer');
        }
    } catch (error) {
        console.error('Error deleting lecturer:', error);
        alert('Error: ' + error.message);
    }
}

// Initialize the page
document.addEventListener('DOMContentLoaded', function() {
    // Load initial data
    loadLecturers();

    // Add event listeners for search and filter inputs
    document.getElementById('lecturerSearch').addEventListener('input', debounce(() => loadLecturers(1), 300));
    document.getElementById('lecturerStatus').addEventListener('change', () => loadLecturers(1));
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