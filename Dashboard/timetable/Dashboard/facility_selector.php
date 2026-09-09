<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Facility Selector with Sorting & Filtering</title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Bootstrap Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" rel="stylesheet">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <!-- Bootstrap JS for modals (if needed) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        /* Custom animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in {
            animation: fadeIn 0.3s ease-out;
        }
        /* Hover effects */
        .hover-scale {
            transition: transform 0.2s ease;
        }
        .hover-scale:hover {
            transform: scale(1.05);
        }
        /* DataTable custom styles */
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            @apply px-3 py-1 mx-1 rounded-md bg-gray-100 text-gray-700 hover:bg-blue-100 hover:text-blue-700;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            @apply bg-blue-600 text-white;
        }
        .dataTables_wrapper .dataTables_info {
            @apply text-sm text-gray-600 mt-2;
        }
        .dataTables_wrapper .dataTables_length {
            @apply text-sm text-gray-600 mb-2;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased">
    <div class="container">
        <h2 class="text-3xl font-bold text-gray-800 mb-6 fade-in">Select Facility</h2>

        <!-- Selected Facility Card -->
        <div id="selectedFacilityCard" class="bg-white rounded-xl shadow-lg p-6 mb-6 hidden fade-in">
            <div class="flex justify-between items-center">
                <div>
                    <h5 class="text-xl font-semibold text-gray-800" id="selectedFacilityName"></h5>
                    <p class="text-gray-600 mt-2"><i class="bi bi-building mr-2"></i><strong>Type:</strong> <span id="selectedFacilityType"></span></p>
                    <p class="text-gray-600"><i class="bi bi-people mr-2"></i><strong>Capacity:</strong> <span id="selectedFacilityCapacity"></span> students</p>
                    <p class="text-gray-600"><i class="bi bi-geo-alt mr-2"></i><strong>Site:</strong> <span id="selectedFacilitySiteName"></span></p>
                </div>
                <button id="changeFacilityBtn" class="bg-gray-100 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-200 hover-scale flex items-center">
                    <i class="bi bi-arrow-left-right mr-2"></i> Change Facility
                </button>
            </div>
        </div>

        <!-- Facility Selector Table -->
        <div id="facilitySelectorTable" class="bg-white rounded-xl shadow-lg p-6 fade-in">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <input id="searchInput" type="search" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search by facility name...">
                </div>
                <div class="flex items-center">
                    <span class="text-gray-600"><i class="bi bi-people mr-2"></i><strong>Required Capacity:</strong> <span id="requiredCapacity">0</span> students</span>
                </div>
                <div class="text-end">
                    <button id="refreshBtn" class="bg-gray-100 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-200 hover-scale flex items-center">
                        <i class="bi bi-arrow-clockwise mr-2"></i> Refresh
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table id="facilitiesTable" class="min-w-full bg-white border border-gray-200 rounded-md">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Capacity</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Site Name</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody id="facilitiesTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    $(function() {
        // Function to calculate the exact required capacity from selected groups
        function getRequiredCapacity() {
            const selectedGroups = JSON.parse(localStorage.getItem('selectedGroups')) || [];
            // Sum up all the group sizes
            return selectedGroups.reduce((total, group) => total + (group.size || 0), 0);
        }

        // Function to show/hide selected facility card
        function showSelectedFacility() {
            const selectedFacility = JSON.parse(localStorage.getItem('selectedFacility'));
            if (!selectedFacility) {
                $('#selectedFacilityCard').addClass('hidden');
                $('#facilitySelectorTable').removeClass('hidden');
                return false;
            }

            $('#selectedFacilityName').text(selectedFacility.name);
            $('#selectedFacilityType').text(selectedFacility.type);
            $('#selectedFacilityCapacity').text(selectedFacility.capacity);
            $('#selectedFacilitySiteName').text(selectedFacility.site_name);

            $('#selectedFacilityCard').removeClass('hidden');
            $('#facilitySelectorTable').addClass('hidden');
            return true;
        }

        // Update capacity display
        function updateRequiredCapacity() {
            $('#requiredCapacity').text(getRequiredCapacity());
        }

        // Initialize DataTable with pagination
        let table = $('#facilitiesTable').DataTable({
            ajax: {
                url: 'get_facilities_with_site.php',
                dataSrc: 'data',
                data: function(d) {
                    d.minCapacity = getRequiredCapacity();
                }
            },
            columns: [
                { data: 'name' },
                { data: 'type' },
                { data: 'capacity' },
                { data: 'site_name' },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        return `<button class="bg-blue-600 text-white px-3 py-1 rounded-md hover:bg-blue-700 hover-scale selectFacilityBtn" data-fac='${JSON.stringify(row).replace(/'/g, "&apos;")}'>Select</button>`;
                    }
                }
            ],
            paging: true, // Enable pagination
            pageLength: 10, // Default rows per page
            lengthMenu: [5, 10, 25, 50], // Options for rows per page
            lengthChange: true, // Allow user to change page length
            searching: false, // Handle search manually
            ordering: true,
            processing: true,
            serverSide: false,
            language: {
                processing: '<div class="flex justify-center"><svg class="animate-spin h-5 w-5 text-blue-600" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg></div>'
            }
        });

        // Custom search input to filter the DataTable
        $('#searchInput').on('input', function() {
            const val = $(this).val();
            table.search(val).draw();
        });

        // Refresh button reloads the table data and updates capacity display
        $('#refreshBtn').on('click', function() {
            updateRequiredCapacity();
            table.ajax.reload(null, false); // Reload without resetting paging
        });

        // Select facility button click
        $('#facilitiesTable tbody').on('click', '.selectFacilityBtn', function() {
            const facData = $(this).data('fac');
            localStorage.setItem('selectedFacility', JSON.stringify(facData));
            showSelectedFacility();
        });

        // Change facility button click
        $('#changeFacilityBtn').on('click', function() {
            localStorage.removeItem('selectedFacility');
            showSelectedFacility();
            updateRequiredCapacity();
            table.ajax.reload(null, false); // Reload without resetting paging
        });

        // Initial setup
        updateRequiredCapacity();
        if (!showSelectedFacility()) {
            table.ajax.reload();
        }
    });
    </script>
</body>
</html>