<?php
    session_start();
    include("connection.php");
    
    // Fetch distinct sites with their campus information
    $sites = [];
    $site_query = "SELECT DISTINCT s.id, s.name as site_name, s.campus 
                  FROM site s
                  JOIN facility f ON f.site = s.id 
                  WHERE s.name IS NOT NULL AND s.name != '' 
                  ORDER BY s.name ASC";
    $site_result = $connection->query($site_query);
    if ($site_result && $site_result->num_rows > 0) {
        while ($row = $site_result->fetch_assoc()) {
            $sites[] = [
                'id' => $row['id'],
                'name' => $row['site_name'],
                'campus' => $row['campus']
            ];
        }
    }
    
    // Fetch distinct facility types from the database
    $types = [];
    $type_query = "SELECT DISTINCT type FROM facility WHERE type IS NOT NULL AND type != '' ORDER BY type ASC";
    $type_result = $connection->query($type_query);
    if ($type_result && $type_result->num_rows > 0) {
        while ($row = $type_result->fetch_assoc()) {
            $types[] = $row['type'];
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>UR-TIMETABLE - Room Availability</title>
    <link href="assets/img/icon1.png" rel="icon" />

    <!-- Bootstrap 5 + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="assets/css/style.css" rel="stylesheet" />

    <!-- Toastr -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

    <style>
        body { background: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        .card { border-radius: 12px; transition: all 0.3s ease; border: none; cursor: pointer; margin-bottom: 1rem; }
        .card:hover { transform: translateY(-12px) scale(1.02); box-shadow: 0 25px 50px rgba(0,0,0,0.2) !important; }

        .room-fully-free {
            border-left: 4px solid #28a745 !important;
            background: #f8fff9 !important;
        }
        .room-currently-free {
            border-left: 4px solid #ffc107 !important;
            background: #fffdf0 !important;
        }
        .room-occupied {
            border-left: 4px solid #dc3545 !important;
            background: #fff5f5 !important;
        }

        .badge-free { background: #28a745; color: white; font-weight: bold; font-size: 0.7rem; padding: 0.25rem 0.5rem; }
        .badge-now-free { background: #ffc107; color: #212529; font-size: 0.7rem; padding: 0.25rem 0.5rem; }
        .badge-occupied { background: #dc3545; color: white; font-size: 0.7rem; padding: 0.25rem 0.5rem; }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .session-item {
            background: white; border-radius: 8px; padding: 8px; margin-bottom: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05); transition: 0.2s;
            font-size: 0.85rem;
        }
        .session-item:hover { transform: translateX(8px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }

        #loadingOverlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.7); z-index: 9999; justify-content: center; align-items: center; flex-direction: column; color: white;
        }
        .spinner { width: 60px; height: 60px; border: 6px solid #f3f3f3; border-top: 6px solid #3498db; border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 20px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        .card-link { text-decoration: none; color: inherit; }
        .card-link:hover { color: inherit; }

        /* Modal Styles */
        .modal-lg { max-width: 800px; }
        .modal-header { border-bottom: 2px solid #e9ecef; }
        .modal-title { font-weight: 600; color: #2c3e50; }
        .modal-body { padding: 1.5rem; }
        .detail-row { margin-bottom: 1rem; }
        .detail-label { font-weight: 600; color: #6c757d; }
        .detail-value { color: #2c3e50; }
        .sessions-list { max-height: 300px; overflow-y: auto; }
        .session-time { font-weight: 500; color: #2c3e50; }
        .session-course { color: #6c757d; font-size: 0.9em; }
    </style>
    </head>
    <body>

    <?php
    include("./includes/header.php");
    include("./includes/menu.php");
    ?>

    <main id="main" class="main">

    <div id="loadingOverlay">
        <div class="spinner"></div>
        <h4>Loading Rooms...</h4>
    </div>

    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-12 text-center text-md-start">
                <h1 class="display-5 fw-bold text-primary">
                    Room Availability Dashboard
                </h1>
                <p class="lead text-muted">Real-time status of all classrooms, labs & halls at Huye Campus</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="card shadow-lg mb-5 border-0 pt-3">
            <div class="card-body">
                <div class="row g-3 align-items-center">
                    <div class="col-lg-5">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0">
                                <i class="fas fa-search text-muted"></i>
                            </span>
                            <input type="text" id="searchInput" class="form-control border-start-0" 
                                placeholder="Search room, building, site... (e.g. A121, AUDI, RUHANDE)">
                        </div>
                    </div>

                    <div class="col-lg-3">
                        <select id="sortBy" class="form-select">
                            <option value="booked">Sort by: Booked First</option>
                            <option value="free">Sort by: Free First</option>
                            <option value="name_asc">Sort by: Name (A-Z)</option>
                            <option value="name_desc">Sort by: Name (Z-A)</option>
                            <option value="capacity_asc">Sort by: Capacity (Low-High)</option>
                            <option value="capacity_desc">Sort by: Capacity (High-Low)</option>
                        </select>
                    </div>

                    <div class="col-lg-3">
                        <select id="siteFilter" class="form-select">
                            <option value="">All Sites</option>
                            <?php foreach ($sites as $site): ?>
                                <option value="<?php echo htmlspecialchars($site['name']); ?>">
                                    <?php echo htmlspecialchars($site['name']); ?> 
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-lg-3">
                        <select id="typeFilter" class="form-select">
                            <option value="">All Types</option>
                            <?php foreach ($types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>">
                                    <?php 
                                        // Format the type for better display
                                        $displayType = strtolower($type);
                                        $displayType = str_replace(['_', '-'], ' ', $displayType);
                                        echo ucwords($displayType);
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-lg-2">
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" id="onlyFreeNow">
                            <label class="form-check-label fw-bold text-success" for="onlyFreeNow">
                                Free Now
                            </label>
                        </div>
                    </div>

                    <div class="col-12 text-end d-flex justify-content-end gap-2">
                        <button id="exportExcelBtn" class="btn btn-success btn-lg px-4">
                            <i class="fas fa-file-excel me-2"></i>Export to Excel
                        </button>
                        <button id="refreshBtn" class="btn btn-primary btn-lg px-5">
                            <i class="fas fa-sync-alt me-2"></i>Refresh
                        </button>
                    </div>
                </div>
                <div class="mt-3">
                    <small class="text-muted fw-bold" id="resultsCount">Loading rooms...</small>
                </div>
            </div>
        </div>

        <!-- Results Grid -->
        <div id="facilitiesContainer" class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4"></div>

        <!-- Facility Details Modal -->
        <div class="modal fade" id="facilityModal" tabindex="-1" aria-labelledby="facilityModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-light">
                        <h5 class="modal-title" id="facilityModalLabel">Facility Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0" id="facilityModalBody">
                        <!-- Content will be dynamically inserted here -->
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i> Close
                        </button>
                        <a href="#" id="viewFullDetailsBtn" class="btn btn-primary">
                            <i class="fas fa-external-link-alt me-1"></i> View Full Details
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div id="emptyState" class="text-center py-5 d-none">
            <i class="fas fa-search fa-5x text-muted mb-4"></i>
            <h3 class="text-muted">No rooms found</h3>
            <p class="text-muted">Try adjusting your search or filters</p>
        </div>
    </div>
    </main>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <!-- SheetJS for Excel export -->
    <script src="https://cdn.sheetjs.com/xlsx-0.19.3/package/dist/xlsx.full.min.js"></script>

    <script>
    let allFacilities = [];

    function formatTime(t) {
        if (!t) return 'N/A';
        const [h, m] = t.split(':');
        const hour = parseInt(h);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        const h12 = hour % 12 || 12;
        return `${h12}:${m} ${ampm}`;
    }

    function cleanType(type) {
        if (!type) return "Room";
        const t = type.toLowerCase();
        if (t.includes('classroom')) return 'CLASSROOM';
        if (t.includes('audi')) return 'AUDITORIUM';
        if (t.includes('conference')) return 'CONFERENCE HALL';
        if (t.includes('koica')) return 'KOICA HALL';
        if (t.includes('theater') || t.includes('entertainment')) return 'THEATER HALL';
        if (t.includes('former library')) return 'FORMER LIBRARY';
        return type.toUpperCase();
    }

    function isCurrentlyFree(bookings) {
        const now = new Date();
        const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        const currentDay = days[now.getDay()];
        const currentTime = now.toTimeString().slice(0, 5);

        return !bookings.some(b => 
            b.sessions.some(s => 
                s.day === currentDay &&
                s.start_time <= currentTime &&
                currentTime < s.end_time
            )
        );
    }

    function renderFacilities(facilities) {
        const $container = $('#facilitiesContainer');
        const $empty = $('#emptyState');
        $container.empty();

        if (facilities.length === 0) {
            $empty.removeClass('d-none');
            $('#resultsCount').text('0 rooms found');
            return;
        }

        $empty.addClass('d-none');
        $('#resultsCount').html(`<strong>${facilities.length}</strong> room${facilities.length > 1 ? 's' : ''} found`);

        facilities.forEach(f => {
            const fullyFree = f.bookings.length === 0;
            const currentlyFree = fullyFree || isCurrentlyFree(f.bookings);

            const cardClass = fullyFree ? 'room-fully-free' : currentlyFree ? 'room-currently-free' : 'room-occupied';
            const statusBadge = fullyFree
                ? '<div class="badge badge-free">FULLY FREE</div>'
                : currentlyFree
                    ? '<div class="badge badge-now-free">FREE NOW</div>'
                    : '<div class="badge badge-occupied">IN USE</div>';

            let sessionsHtml = '';
            if (fullyFree) {
                sessionsHtml = `
                    <div class="text-center py-1">
                        <i class="fas fa-check-circle text-success"></i>
                    </div>`;
            } else {
                f.bookings.forEach(b => {
                    b.sessions.forEach(s => {
                        const groups = b.groups?.length > 0
                            ? b.groups.map(g => 
                                `<span class="badge bg-primary me-2 mb-1">
                                    ${g.group_name} – ${g.year_of_study || 'Year ?'} – ${g.program}
                                    ${g.group_size ? `<small class="ms-1">(${g.group_size})</small>` : ''}
                                </span>`
                            ).join('')
                            : '<em class="text-muted">No group assigned</em>';

                        sessionsHtml += `
                            <div class="session-item">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <div class="text-truncate" style="max-width: 70%;">
                                            <div class="text-truncate"><strong class="text-primary">${b.module_code || 'N/A'}</strong></div>
                                            <small class="text-truncate d-block">${b.module_name || 'Module'}</small>
                                        </div>
                                    <span class="badge bg-${b.status === 'approved' ? 'success' : 'warning'}">
                                        ${b.status ? b.status.charAt(0).toUpperCase() + b.status.slice(1) : 'Pending'}
                                    </span>
                                </div>
                                    <div class="text-muted small text-truncate">
                                        <i class="far fa-calendar-alt"></i> ${s.day}
                                        <i class="far fa-clock ms-1"></i> ${formatTime(s.start_time)}-${formatTime(s.end_time)}
                                    </div>
                                    <div class="mt-1 text-truncate">${groups}</div>
                            </div>`;
                    });
                });
            }

            const card = `
                <div class="col-lg-4 col-md-6">
                    <div class="card h-100 shadow-sm ${cardClass} facility-card" 
                        data-facility='${JSON.stringify(f).replace(/'/g, "&#39;")}'>
                        <div class="card-header bg-transparent border-0 pb-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 fw-bold text-dark text-truncate" style="max-width: 70%;">${f.name}</h5>
                                <div class="ms-2">${statusBadge}</div>
                            </div>
                            <div class="mt-1">
                                <small class="text-muted">
                                    <i class="fas fa-home"></i> ${cleanType(f.type).replace('HALL', '')}
                                <i class="fas fa-users ms-2"></i> ${f.capacity || 'N/A'}
                                </small>
                            </div>
                            <small class="text-muted d-block mt-2">
                                <i class="fas fa-map-marker-alt text-danger"></i>
                                <strong>${f.site || 'Unknown Site'}</strong> • ${f.campus}
                            </small>
                        </div>
                        <div class="card-body pt-3">
                            <div style="max-height: 180px; overflow-y: auto; overflow-x: hidden; font-size: 0.8rem;">
                                ${sessionsHtml}
                            </div>
                        </div>
                        <div class="card-footer bg-transparent text-center border-0 py-2">
                            <small class="text-muted">
                                ${fullyFree ? 'Available' : currentlyFree ? 'Free Now' : 'In Use'}
                            </small>
                        </div>
                    </div>
                </div>`;

            $container.append(card);
        });
    }

    function loadFacilities() {
        $('#loadingOverlay').fadeIn(200);

        const params = {
            search: $('#searchInput').val().trim(),
            site: $('#siteFilter').val(),
            type: $('#typeFilter').val(),
            only_free_now: $('#onlyFreeNow').is(':checked') ? 1 : 0
        };

        // Get the current sort option
        const sortBy = $('#sortBy').val();

        $.get('api_facility1.php', params)
            .done(function(res) {
                if (res && res.success && res.data) {
                    const sorted = res.data.sort((a, b) => {
                        const aFree = a.bookings.length === 0 || isCurrentlyFree(a.bookings);
                        const bFree = b.bookings.length === 0 || isCurrentlyFree(b.bookings);
                        if (aFree && !bFree) return -1;
                        if (!aFree && bFree) return 1;
                        return (b.capacity || 0) - (a.capacity || 0);
                    });

                    // Apply client-side sorting
                    const sortedFacilities = sortFacilities(sorted, sortBy);
                    renderFacilities(sortedFacilities);
                } else {
                    toastr.error('Failed to load data');
                    renderFacilities([]);
                }
            })
            .fail(function() {
                toastr.error('Connection failed. Please try again.');
                renderFacilities([]);
            })
            .always(() => $('#loadingOverlay').fadeOut(300));
    }

    // Sort facilities based on selected option
    function sortFacilities(facilities, sortBy) {
        return [...facilities].sort((a, b) => {
            const aHasBookings = a.bookings && a.bookings.length > 0;
            const bHasBookings = b.bookings && b.bookings.length > 0;
            
            switch(sortBy) {
                case 'booked':
                    // Booked first, then by name
                    if (aHasBookings && !bHasBookings) return -1;
                    if (!aHasBookings && bHasBookings) return 1;
                    return a.name.localeCompare(b.name);
                    
                case 'free':
                    // Free first, then by name
                    if (!aHasBookings && bHasBookings) return -1;
                    if (aHasBookings && !bHasBookings) return 1;
                    return a.name.localeCompare(b.name);
                    
                case 'name_asc':
                    return a.name.localeCompare(b.name);
                    
                case 'name_desc':
                    return b.name.localeCompare(a.name);
                    
                case 'capacity_asc':
                    return (a.capacity || 0) - (b.capacity || 0);
                    
                case 'capacity_desc':
                    return (b.capacity || 0) - (a.capacity || 0);
                    
                default:
                    return 0;
            }
        });
    }

    // Export to Excel function
    function exportToExcel() {
        try {
            console.log('Starting Excel export...');
            const facilities = [];
            const $facilityCards = $('.facility-card');
            
            if ($facilityCards.length === 0) {
                toastr.warning('No facilities found to export');
                return;
            }

            $facilityCards.each(function(index) {
                try {
                    const $card = $(this);
                    let facilityData = $card.data('facility');
                    
                    // Ensure we have valid data
                    if (!facilityData) {
                        console.warn('No data found for facility card', index);
                        return;
                    }

                    // Parse if it's a string
                    if (typeof facilityData === 'string') {
                        try {
                            facilityData = JSON.parse(facilityData.replace(/&#39;/g, '"'));
                        } catch (e) {
                            console.error('Error parsing facility data:', e);
                            return;
                        }
                    }

                    const facilityName = facilityData.name || 'Unnamed Facility';
                    console.log(`Processing facility ${index + 1}:`, facilityName);

                    // Add facility info
                    facilities.push({
                        'Room': facilityName,
                        'Type': facilityData.type || 'N/A',
                        'Site': facilityData.site || 'N/A',
                        'Campus': facilityData.campus || 'N/A',
                        'Capacity': facilityData.capacity || 0,
                        'Status': $card.find('.badge').first().text().trim() || 'N/A',
                        'Sessions': facilityData.bookings?.length || 0,
                        'Details': 'Facility Information' // Mark as facility row
                    });

                    // Add session details if any
                    if (facilityData.bookings && facilityData.bookings.length > 0) {
                        facilityData.bookings.forEach((booking, bookingIndex) => {
                            if (booking.sessions && booking.sessions.length > 0) {
                                booking.sessions.forEach((session, sessionIndex) => {
                                    facilities.push({
                                        'Room': '', // Empty to indicate session row
                                        'Session': `Session ${sessionIndex + 1}`,
                                        'Day': session.day || 'N/A',
                                        'Time': `${session.start_time || ''} - ${session.end_time || ''}`.trim(),
                                        'Module': booking.module_code || 'N/A',
                                        'Module Name': booking.module_name || 'N/A',
                                        'Status': booking.status || 'N/A',
                                        'Details': 'Session Information' // Mark as session row
                                    });
                                });
                            }
                        });
                    }

                    // Add an empty row between facilities
                    facilities.push({});

                } catch (cardError) {
                    console.error(`Error processing facility card ${index + 1}:`, cardError);
                    toastr.error(`Error processing facility ${index + 1}, skipping...`);
                }
            });

            if (facilities.length === 0) {
                toastr.warning('No data available to export');
                return;
            }

            // Create worksheet and workbook
            const worksheet = XLSX.utils.json_to_sheet(facilities);
            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, worksheet, 'Room Schedule');

            // Set column widths
            if (!worksheet['!cols']) worksheet['!cols'] = [];
            const columnWidths = [
                { wch: 20 }, // Room
                { wch: 15 }, // Type
                { wch: 15 }, // Site
                { wch: 20 }, // Campus
                { wch: 10 }, // Capacity
                { wch: 15 }, // Status
                { wch: 10 }, // Sessions
                { wch: 25 }  // Details
            ];
            columnWidths.forEach((width, index) => {
                if (!worksheet['!cols'][index]) {
                    worksheet['!cols'][index] = width;
                } else {
                    worksheet['!cols'][index].wch = Math.max(worksheet['!cols'][index].wch || 0, width.wch);
                }
            });

            // Generate Excel file
            const date = new Date().toISOString().slice(0, 10);
            const fileName = `Room_Schedule_${date}.xlsx`;
            XLSX.writeFile(workbook, fileName);
            
            toastr.success(`Exported ${$facilityCards.length} facilities to ${fileName}`);
            console.log('Export completed successfully');

        } catch (error) {
            console.error('Export error:', error);
            toastr.error(`Export failed: ${error.message || 'Unknown error'}`);
        }
    }

    // Event Listeners
    let searchTimer;
    $('#searchInput').on('input', function() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(loadFacilities, 400);
    });
    
    // Export to Excel button click handler
    $('#exportExcelBtn').on('click', exportToExcel);

    $('#siteFilter, #typeFilter, #onlyFreeNow, #sortBy').on('change', loadFacilities);
    $('#refreshBtn').on('click', loadFacilities);

    // Initialize the page
    $(document).ready(function() {
        toastr.options = { positionClass: 'toast-top-right', timeOut: 3000 };
        loadFacilities();
        
        // Auto-refresh every 5 minutes
        setInterval(loadFacilities, 300000);
        
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });

    // Handle facility card click
    $(document).on('click', '.facility-card', function() {
        try {
            // Get the data attribute and parse it
            const facilityData = $(this).data('facility');
            let facility;
            
            if (typeof facilityData === 'string') {
                // If it's a string, parse it (handle HTML entities)
                facility = JSON.parse(facilityData.replace(/&#39;/g, '"'));
            } else {
                // If it's already an object, use it directly
                facility = facilityData;
            }
            
            showFacilityModal(facility);
        } catch (error) {
            console.error('Error parsing facility data:', error);
            toastr.error('Failed to load facility details. Please try again.');
        }
    });

    // Show facility details in modal
    function showFacilityModal(facility) {
        const modal = new bootstrap.Modal(document.getElementById('facilityModal'));
        const modalBody = document.getElementById('facilityModalBody');
        
        // Show loading state
        modalBody.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading facility details...</p>
            </div>
        `;
        
        // Show the modal
        modal.show();
        
        // Format the details
        const fullyFree = facility.bookings.length === 0;
        const currentlyFree = fullyFree || isCurrentlyFree(facility.bookings);
        
        let statusBadge = '';
        if (fullyFree) {
            statusBadge = '<span class="badge bg-success">Fully Free</span>';
        } else if (currentlyFree) {
            statusBadge = '<span class="badge bg-warning text-dark">Free Now</span>';
        } else {
            statusBadge = '<span class="badge bg-danger">Occupied</span>';
        }
        
        // Create bookings list
        let bookingsHtml = '';
        if (facility.bookings.length > 0) {
            facility.bookings.forEach(b => {
                b.sessions.forEach(s => {
                    const groups = b.groups?.length > 0
                        ? b.groups.map(g => 
                            `<span class="badge bg-primary me-2 mb-1">
                                ${g.group_name || 'Group'} – ${g.year_of_study || 'Year ?'} – ${g.program || 'Program'}
                                ${g.group_size ? `<small class="ms-1">(${g.group_size})</small>` : ''}
                            </span>`
                        ).join('')
                        : '<em class="text-muted">No group assigned</em>';

                    bookingsHtml += `
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6 class="card-title text-primary mb-1">${b.module_code || 'N/A'}</h6>
                                        <p class="card-text small text-muted mb-2">${b.module_name || 'Module'}</p>
                                    </div>
                                    <span class="badge bg-${b.status === 'approved' ? 'success' : 'warning'}">
                                        ${b.status ? b.status.charAt(0).toUpperCase() + b.status.slice(1) : 'Pending'}
                                    </span>
                                </div>
                                <div class="d-flex align-items-center text-muted small mb-2">
                                    <i class="far fa-calendar-alt me-2"></i>
                                    <span class="me-3">${s.day || 'Day not specified'}</span>
                                    <i class="far fa-clock me-2"></i>
                                    <span>${formatTime(s.start_time)} - ${formatTime(s.end_time)}</span>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted">Groups:</small>
                                    <div class="mt-1">${groups}</div>
                                </div>
                            </div>
                        </div>`;
                });
            });
        } else {
            bookingsHtml = '<div class="alert alert-info">No scheduled sessions for this facility.</div>';
        }
        
        // Set modal content
        modalBody.innerHTML = `
            <div class="container-fluid">
                <div class="row mb-4">
                    <div class="col-md-8">
                        <h3>${facility.name || 'Facility'}</h3>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            ${statusBadge}
                            <span class="badge bg-secondary">${facility.type || 'Room'}</span>
                        </div>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <h4 class="text-muted">${facility.capacity || 'N/A'} <small class="text-muted">seats</small></h4>
                    </div>
                </div>
                
                <div class="row">
                
                    
                    
                    
                    
                
                </div>
                
                <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
                    <h5 class="mb-0">Schedule</h5>
                    <a href="facility_view.php?id=${facility.id}" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-external-link-alt me-1"></i> View Full Details
                    </a>
                </div>
                <div class="sessions-list">
                    ${bookingsHtml}
                </div>
            </div>
        `;
    }

    </script>

    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>

    <?php include("./includes/footer.php"); ?>
    </body>
    </html>