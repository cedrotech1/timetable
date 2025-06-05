<?php
include('connection.php');

// Get lecturers
$lecturers_query = "SELECT id, names, email FROM users WHERE role = 'lecturer'";
$lecturers_result = mysqli_query($connection, $lecturers_query);
?>

<!-- Lecturer Selection Modal -->
<div class="modal fade" id="lecturerModal" tabindex="-1" aria-labelledby="lecturerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="lecturerModalLabel">Select Lecturer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <input type="text" class="form-control" id="lecturerSearch" placeholder="Search lecturers...">
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Select</th>
                                <th>Name</th>
                                <th>Email</th>
                            </tr>
                        </thead>
                        <tbody id="lecturerTableBody">
                            <?php while($lecturer = mysqli_fetch_assoc($lecturers_result)): ?>
                            <tr>
                                <td>
                                    <input type="radio" name="lecturer" class="lecturer-radio" 
                                           data-id="<?php echo $lecturer['id']; ?>"
                                           data-name="<?php echo htmlspecialchars($lecturer['names']); ?>"
                                           data-email="<?php echo htmlspecialchars($lecturer['email']); ?>">
                                </td>
                                <td><?php echo htmlspecialchars($lecturer['names']); ?></td>
                                <td><?php echo htmlspecialchars($lecturer['email']); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div>
                            <span class="text-muted">Showing <span id="lecturerStartRecord">1</span> to <span id="lecturerEndRecord">10</span> of <span id="lecturerTotalRecords">0</span> lecturers</span>
                        </div>
                        <nav aria-label="Lecturer pagination">
                            <ul class="pagination mb-0">
                                <li class="page-item" id="lecturerPrevPage">
                                    <a class="page-link" href="#" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                <li class="page-item" id="lecturerNextPage">
                                    <a class="page-link" href="#" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="confirmLecturer">Confirm Selection</button>
            </div>
        </div>
    </div>
</div>

<script>
// Add pagination variables
let currentLecturerPage = 1;
const lecturersPerPage = 10;
let totalLecturers = 0;

// Function to update lecturer pagination
function updateLecturerPagination() {
    const rows = document.querySelectorAll('#lecturerTableBody tr');
    totalLecturers = rows.length;
    const totalPages = Math.ceil(totalLecturers / lecturersPerPage);
    
    // Update pagination info
    document.getElementById('lecturerTotalRecords').textContent = totalLecturers;
    document.getElementById('lecturerStartRecord').textContent = ((currentLecturerPage - 1) * lecturersPerPage) + 1;
    document.getElementById('lecturerEndRecord').textContent = Math.min(currentLecturerPage * lecturersPerPage, totalLecturers);
    
    // Update pagination buttons
    document.getElementById('lecturerPrevPage').classList.toggle('disabled', currentLecturerPage === 1);
    document.getElementById('lecturerNextPage').classList.toggle('disabled', currentLecturerPage === totalPages);
    
    // Show/hide rows based on current page
    rows.forEach((row, index) => {
        const start = (currentLecturerPage - 1) * lecturersPerPage;
        const end = start + lecturersPerPage;
        row.style.display = (index >= start && index < end) ? '' : 'none';
    });
}

// Add pagination event listeners
document.getElementById('lecturerPrevPage').addEventListener('click', function(e) {
    e.preventDefault();
    if (currentLecturerPage > 1) {
        currentLecturerPage--;
        updateLecturerPagination();
    }
});

document.getElementById('lecturerNextPage').addEventListener('click', function(e) {
    e.preventDefault();
    const totalPages = Math.ceil(totalLecturers / lecturersPerPage);
    if (currentLecturerPage < totalPages) {
        currentLecturerPage++;
        updateLecturerPagination();
    }
});

// Update pagination when modal is shown
document.getElementById('lecturerModal').addEventListener('show.bs.modal', function() {
    currentLecturerPage = 1;
    updateLecturerPagination();
});

// Update pagination when search changes
document.getElementById('lecturerSearch').addEventListener('input', function() {
    currentLecturerPage = 1;
    updateLecturerPagination();
});

// Search functionality
document.getElementById('lecturerSearch').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#lecturerTableBody tr');
    
    rows.forEach(row => {
        const name = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
        const email = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
        row.style.display = (name.includes(searchTerm) || email.includes(searchTerm)) ? '' : 'none';
    });
});

// Handle lecturer selection
document.getElementById('confirmLecturer').addEventListener('click', function() {
    const selectedRadio = document.querySelector('input[name="lecturer"]:checked');
    if (selectedRadio) {
        const lecturerId = selectedRadio.dataset.id;
        const lecturerName = selectedRadio.dataset.name;
        const lecturerEmail = selectedRadio.dataset.email;

        // Update hidden input
        document.getElementById('lecturer').value = lecturerId;
        
        // Update display
        document.getElementById('selectedLecturerDisplay').value = `${lecturerName} (${lecturerEmail})`;
        
        // Close modal
        bootstrap.Modal.getInstance(document.getElementById('lecturerModal')).hide();
    } else {
        alert('Please select a lecturer');
    }
});
</script>