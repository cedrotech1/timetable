<?php
session_start();
include('connection.php');

// Get default academic year and semester from system settings
$system_query = "SELECT s.*, ay.year_label 
                FROM system s 
                LEFT JOIN academic_year ay ON s.accademic_year_id = ay.id 
                LIMIT 1";
$system_result = mysqli_query($connection, $system_query);
$system_data = mysqli_fetch_assoc($system_result);

// Get all academic years
$years_query = "SELECT id, year_label FROM academic_year ORDER BY year_label DESC";
$years_result = mysqli_query($connection, $years_query);
$academic_years = [];
while ($row = mysqli_fetch_assoc($years_result)) {
    $academic_years[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Facility Management - UR-TIMETABLE</title>
    
     <!-- Favicons -->
  <link href="assets/img/icon1.png" rel="icon">
  <link href="assets/img/icon1.png" rel="apple-touch-icon">

  <!-- Google Fonts -->
  <link href="https://fonts.gstatic.com" rel="preconnect">
  <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
  <link href="assets/vendor/quill/quill.snow.css" rel="stylesheet">
  <link href="assets/vendor/quill/quill.bubble.css" rel="stylesheet">
  <link href="assets/vendor/remixicon/remixicon.css" rel="stylesheet">
  <link href="assets/vendor/simple-datatables/style.css" rel="stylesheet">

  <!-- Template Main CSS File -->
  <link href="assets/css/style.css" rel="stylesheet">

  <!-- XLSX and PapaParse libraries -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.3.0/papaparse.min.js"></script>

</head>
<body>
    <?php
    include("./includes/header.php");
    include("./includes/menu.php");
    ?>

    <main id="main" class="main">
        <div class="container-fluid py-4">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="bi bi-building"></i> Facility Management
                            </h5>
                            
                            <!-- Filter Section -->
                            <div class="row mb-4">
                                <!-- Primary Filters -->
                                <div class="col-md-3">
                                    <label class="form-label">Academic Year</label>
                                    <select class="form-select" id="academic_year">
                                        <?php foreach ($academic_years as $year): ?>
                                            <option value="<?php echo $year['id']; ?>" 
                                                    <?php echo ($year['id'] == $system_data['accademic_year_id']) ? 'selected' : ''; ?>>
                                                <?php echo $year['year_label']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Semester</label>
                                    <select class="form-select" id="semester">
                                        <option value="1" <?php echo ($system_data['semester'] == '1') ? 'selected' : ''; ?>>Semester 1</option>
                                        <option value="2" <?php echo ($system_data['semester'] == '2') ? 'selected' : ''; ?>>Semester 2</option>
                                        <option value="3" <?php echo ($system_data['semester'] == '3') ? 'selected' : ''; ?>>Semester 3</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" id="status">
                                        <option value="">All</option>
                                        <option value="available">Available</option>
                                        <option value="occupied">Occupied</option>
                                    </select>
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <button class="btn btn-primary w-100" id="applyPrimaryFilter">
                                        <i class="bi bi-funnel"></i> Apply Primary Filter
                                    </button>
                                </div>
                            </div>

                            <!-- Secondary Filters -->
                            <div class="row mb-4" id="secondaryFilters" style="display: none;">
                                <div class="col-md-3">
                                    <label class="form-label">Facility Type</label>
                                    <select class="form-select" id="facility_type">
                                        <option value="">All Types</option>
                                        <option value="Classroom">Classroom</option>
                                        <option value="computer lab">Computer Lab</option>
                                        <option value="medicine lab">Medicine Lab</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Capacity Range</label>
                                    <select class="form-select" id="capacity_range">
                                        <option value="">All Capacities</option>
                                        <option value="0-20">0 - 20</option>
                                        <option value="21-50">21 - 50</option>
                                        <option value="51-100">51 - 100</option>
                                        <option value="101-200">101 - 200</option>
                                        <option value="201-+">201+</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Location</label>
                                    <input type="text" class="form-control" id="location" placeholder="Search location...">
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <button class="btn btn-secondary w-100" id="applySecondaryFilter">
                                        <i class="bi bi-funnel-fill"></i> Apply Secondary Filter
                                    </button>
                                </div>
                            </div>

                            <!-- Facilities Cards -->
                            <div class="row" id="facilitiesContainer">
                                <!-- Data will be loaded here -->
                            </div>

                            <!-- Pagination Controls -->
                            <div class="row mt-4">
                                <div class="col-12">
                                    <nav aria-label="Facility pagination">
                                        <ul class="pagination justify-content-center" id="paginationControls">
                                            <!-- Pagination will be loaded here -->
                                        </ul>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal for Slots -->
    <div class="modal fade" id="slotsModal" tabindex="-1" aria-labelledby="slotsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="slotsModalLabel">Taken Slots</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="slotsModalBody">
                    <!-- Slots will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="loading" id="loadingIndicator" style="display: none;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <!-- Vendor JS Files -->
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
    $(document).ready(function() {
        let filteredData = [];
        let currentPage = 1;
        const itemsPerPage = 9;
        
        // Initial load
        loadFacilities();

        // Primary filter handler
        $('#applyPrimaryFilter').click(function() {
            currentPage = 1; // Reset to first page when filter changes
            const selectedYear = $('#academic_year option:selected').text().trim();
            console.log('Selected Academic Year:', selectedYear);
            loadFacilities();
            $('#secondaryFilters').show();
        });

        // Secondary filter handler
        $('#applySecondaryFilter').click(function() {
            currentPage = 1; // Reset to first page when filter changes
            applySecondaryFilters();
        });

        function showLoading() {
            $('#loadingIndicator').show();
        }

        function hideLoading() {
            $('#loadingIndicator').hide();
        }

        function loadFacilities() {
            showLoading();
            const academicYearId = $('#academic_year').val();
            const semester = $('#semester').val();
            const status = $('#status').val();

            console.log('Loading facilities with:', {
                academicYearId,
                semester,
                status,
                page: currentPage
            });

            $.ajax({
                url: 'api_facility.php',
                method: 'GET',
                data: {
                    academic_year_id: academicYearId,
                    semester: semester,
                    status: status,
                    page: currentPage,
                    per_page: itemsPerPage
                },
                success: function(response) {
                    console.log('API Response:', response);
                    if (response.success) {
                        filteredData = response.data;
                        displayFacilities(response.data);
                        updatePagination(response.total);
                    } else {
                        console.error('Error loading facilities:', response.message);
                        $('#facilitiesContainer').html(`
                            <div class="col-12">
                                <div class="alert alert-danger mb-0">
                                    Error loading facilities: ${response.message}
                                </div>
                            </div>
                        `);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', {xhr, status, error});
                    $('#facilitiesContainer').html(`
                        <div class="col-12">
                            <div class="alert alert-danger mb-0">
                                Error loading facilities. Please try again.
                            </div>
                        </div>
                    `);
                },
                complete: function() {
                    hideLoading();
                }
            });
        }

        function updatePagination(totalItems) {
            const totalPages = Math.ceil(totalItems / itemsPerPage);
            const pagination = $('#paginationControls');
            pagination.empty();

            if (totalPages <= 1) {
                pagination.hide();
                return;
            }

            pagination.show();

            // Previous button
            pagination.append(`
                <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${currentPage - 1}" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
            `);

            // Page numbers
            for (let i = 1; i <= totalPages; i++) {
                if (
                    i === 1 || // First page
                    i === totalPages || // Last page
                    (i >= currentPage - 2 && i <= currentPage + 2) // Pages around current
                ) {
                    pagination.append(`
                        <li class="page-item ${i === currentPage ? 'active' : ''}">
                            <a class="page-link" href="#" data-page="${i}">${i}</a>
                        </li>
                    `);
                } else if (
                    i === currentPage - 3 || // Before current range
                    i === currentPage + 3 // After current range
                ) {
                    pagination.append(`
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                    `);
                }
            }

            // Next button
            pagination.append(`
                <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${currentPage + 1}" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            `);

            // Add click handlers
            pagination.find('.page-link').click(function(e) {
                e.preventDefault();
                const newPage = $(this).data('page');
                if (newPage && newPage !== currentPage) {
                    currentPage = newPage;
                    displayFacilities(filteredData);
                    // Scroll to top of facilities container
                    $('html, body').animate({
                        scrollTop: $('#facilitiesContainer').offset().top - 100
                    }, 500);
                }
            });
        }

        function applySecondaryFilters() {
            const type = $('#facility_type').val();
            const capacityRange = $('#capacity_range').val();
            const location = $('#location').val().toLowerCase();

            let filtered = [...filteredData];

            // Apply type filter
            if (type) {
                filtered = filtered.filter(f => f.type.toLowerCase() === type.toLowerCase());
            }

            // Apply capacity range filter
            if (capacityRange) {
                const [min, max] = capacityRange.split('-');
                filtered = filtered.filter(f => {
                    const capacity = parseInt(f.capacity);
                    if (max === '+') {
                        return capacity >= parseInt(min);
                    }
                    return capacity >= parseInt(min) && capacity <= parseInt(max);
                });
            }

            // Apply location filter
            if (location) {
                filtered = filtered.filter(f => 
                    f.location.toLowerCase().includes(location)
                );
            }

            displayFacilities(filtered);
            updatePagination(filtered.length);
        }

        function displayFacilities(facilities) {
            const container = $('#facilitiesContainer');
            container.empty();

            if (facilities.length === 0) {
                container.append(`
                    <div class="col-12">
                        <div class="alert alert-info mb-0">
                            No facilities found matching the selected criteria.
                        </div>
                    </div>
                `);
                $('#paginationControls').hide();
                return;
            }

            const selectedYearId = $('#academic_year').val();
            const selectedSemester = $('#semester').val();
            const selectedYearLabel = $('#academic_year option:selected').text().trim();

            // Calculate pagination
            const totalPages = Math.ceil(facilities.length / itemsPerPage);
            const startIndex = (currentPage - 1) * itemsPerPage;
            const endIndex = Math.min(startIndex + itemsPerPage, facilities.length);
            const currentPageFacilities = facilities.slice(startIndex, endIndex);

            // Display current page facilities
            currentPageFacilities.forEach(facility => {
                // Filter slots for current academic year and semester
                const relevantSlots = facility.taken_slots.filter(slot => {
                    const slotSemester = slot.semester.toString();
                    const slotYearId = slot.academic_year_id;
                    
                    // Compare both year ID and semester
                    const yearMatch = slotYearId === selectedYearId;
                    const semesterMatch = slotSemester === selectedSemester;
                    
                    return yearMatch && semesterMatch;
                });

                // Calculate availability status
                const isAvailable = relevantSlots.length === 0;
                const statusClass = isAvailable ? 'success' : 'warning';
                const statusText = isAvailable ? 'Available' : 'Partially Occupied';

                const card = `
                    <div class="col-md-4 mb-4">
                        <div class="card h-100 facility-card">
                            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-building"></i> ${facility.name}
                                </h5>
                                <span class="badge bg-${statusClass}">${statusText}</span>
                            </div>
                            <div class="card-body">
                                <div class="facility-info">
                                    <div class="info-item">
                                        <i class="bi bi-geo-alt"></i>
                                        <span><strong>Location:</strong> ${facility.location}</span>
                                    </div>
                                    <div class="info-item">
                                        <i class="bi bi-tag"></i>
                                        <span><strong>Type:</strong> ${facility.type}</span>
                                    </div>
                                    <div class="info-item">
                                        <i class="bi bi-people"></i>
                                        <span><strong>Capacity:</strong> ${facility.capacity}</span>
                                    </div>
                                    <div class="info-item">
                                        <i class="bi bi-building-add"></i>
                                        <span><strong>Campus:</strong> ${facility.campus_name}</span>
                                    </div>
                                </div>
                                ${relevantSlots.length > 0 ? `
                                    <div class="mt-3">
                                        <button type="button" class="btn btn-primary w-100 view-slots-btn" 
                                                data-facility-name="${facility.name}"
                                                data-slots='${JSON.stringify(relevantSlots)}'>
                                            <i class="bi bi-calendar-check"></i> View Schedule (${relevantSlots.length} slots)
                                        </button>
                                    </div>
                                ` : `
                                    <div class="mt-3">
                                        <div class="alert alert-success mb-0">
                                            <i class="bi bi-check-circle"></i> Available for ${selectedYearLabel} Semester ${selectedSemester}
                                        </div>
                                    </div>
                                `}
                            </div>
                        </div>
                    </div>
                `;
                container.append(card);
            });

            // Update pagination controls
            updatePagination(facilities.length);

            // Add click handler for view slots buttons
            $('.view-slots-btn').click(function() {
                const facilityName = $(this).data('facility-name');
                const slots = $(this).data('slots');
                showSlots(facilityName, slots);
            });
        }

        function showError(message) {
            const container = $('#facilitiesContainer');
            container.html(`
                <div class="col-12">
                    <div class="alert alert-danger mb-0">
                        <i class="bi bi-exclamation-triangle"></i> ${message}
                    </div>
                </div>
            `);
        }

        function showSlots(facilityName, slots) {
            const modalBody = $('#slotsModalBody');
            modalBody.empty();

            if (!slots || slots.length === 0) {
                modalBody.append('<p class="text-center">No slots taken for this facility.</p>');
            } else {
                // Group slots by day
                const slotsByDay = {};
                const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                
                days.forEach(day => {
                    slotsByDay[day] = slots.filter(slot => slot.day === day)
                        .sort((a, b) => a.start_time.localeCompare(b.start_time));
                });

                // Create schedule display
                const scheduleContainer = $('<div class="schedule-container"></div>');
                
                days.forEach(day => {
                    if (slotsByDay[day].length > 0) {
                        const daySection = $(`
                            <div class="day-section mb-3">
                                <h6 class="day-title">${day}</h6>
                                <div class="time-slots">
                                    ${slotsByDay[day].map(slot => `
                                        <div class="time-slot">
                                            <i class="bi bi-clock"></i>
                                            ${slot.start_time} - ${slot.end_time}
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        `);
                        scheduleContainer.append(daySection);
                    }
                });

                modalBody.append(scheduleContainer);
            }

            $('#slotsModalLabel').text(`Schedule for ${facilityName}`);
            $('#slotsModal').modal('show');
        }
    });
    </script>

    <style>
    .loading {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.8);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 9999;
    }

    .facility-card {
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        border: none;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .facility-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }

    .facility-card .card-header {
        border-bottom: none;
        padding: 1rem;
    }

    .facility-card .card-title {
        color: #ffffff;
        font-size: 1.2rem;
        font-weight: 600;
        margin: 0;
    }

    .facility-card .card-title i {
        margin-right: 0.5rem;
    }

    .facility-info {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .info-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem;
        background-color: #f8f9fa;
        border-radius: 0.25rem;
    }

    .info-item i {
        color: #012970;
        font-size: 1.1rem;
    }

    .info-item span {
        color: #444444;
    }

    .view-slots-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.75rem;
        font-weight: 500;
        transition: all 0.2s ease-in-out;
    }

    .view-slots-btn:hover {
        transform: translateY(-2px);
    }

    .schedule-container {
        padding: 1rem;
    }

    .day-section {
        background-color: #f8f9fa;
        padding: 1rem;
        border-radius: 0.5rem;
    }

    .day-title {
        color: #012970;
        font-weight: 600;
        margin-bottom: 0.75rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid #dee2e6;
    }

    .time-slots {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .time-slot {
        background-color: #e9ecef;
        padding: 0.5rem 1rem;
        border-radius: 0.25rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.9rem;
    }

    .time-slot i {
        color: #012970;
    }

    .badge {
        font-size: 0.8rem;
        padding: 0.5em 0.75em;
    }

    .modal-body {
        padding: 1.5rem;
    }

    .modal-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
        padding: 1rem 1.5rem;
    }

    .modal-footer {
        background-color: #f8f9fa;
        border-top: 1px solid #dee2e6;
        padding: 1rem 1.5rem;
    }

    /* Pagination Styles */
    .pagination {
        margin-bottom: 0;
    }

    .pagination .page-link {
        color: #012970;
        border: 1px solid #dee2e6;
        padding: 0.5rem 1rem;
        margin: 0 2px;
        border-radius: 4px;
        transition: all 0.2s ease-in-out;
    }

    .pagination .page-link:hover {
        background-color: #e9ecef;
        border-color: #dee2e6;
        color: #012970;
    }

    .pagination .page-item.active .page-link {
        background-color: #012970;
        border-color: #012970;
        color: white;
    }

    .pagination .page-item.disabled .page-link {
        color: #6c757d;
        pointer-events: none;
        background-color: #fff;
        border-color: #dee2e6;
    }

    .pagination .page-item:not(.disabled):not(.active) .page-link:hover {
        transform: translateY(-2px);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    </style>
    <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

<!-- Vendor JS Files -->
<script src="assets/vendor/apexcharts/apexcharts.min.js"></script>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/vendor/chart.js/chart.umd.js"></script>
<script src="assets/vendor/echarts/echarts.min.js"></script>
<script src="assets/vendor/quill/quill.min.js"></script>
<script src="assets/vendor/simple-datatables/simple-datatables.js"></script>
<script src="assets/vendor/tinymce/tinymce.min.js"></script>
<script src="assets/vendor/php-email-form/validate.js"></script>

<!-- Template Main JS File -->
<script src="assets/js/main.js"></script>
</body>
</html> 