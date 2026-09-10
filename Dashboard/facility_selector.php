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
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .fade-in {
                animation: fadeIn 0.3s ease-out;
            }
            .hover-scale {
                transition: transform 0.2s ease;
            }
            .hover-scale:hover {
                transform: scale(1.05);
            }
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
            .capacity-ok {
                background-color: #f0fdf4 !important;
            }
            .capacity-warning {
                background-color: #fffbeb !important;
            }
            .capacity-over {
                background-color: #fef2f2 !important;
            }
        </style>
    </head>
    <body class="bg-gray-100 font-sans antialiased">
        <div class="">
            <h2 class="text-3xl font-bold text-gray-800 mb-6 fade-in">Select Facility</h2>
            <p id="availabilityHint" class="text-sm text-gray-600 mb-4 hidden">
                Showing facilities free for the selected session time in the current academic year &amp; semester.
            </p>

            <!-- Selected Facility Card -->
            <div id="selectedFacilityCard" class="bg-white rounded-xl shadow-lg p-6 mb-6 hidden fade-in">
                <div class="flex justify-between items-center">
                    <div>
                        <h5 class="text-xl font-semibold text-gray-800" id="selectedFacilityName"></h5>
                        <p class="text-gray-600 mt-2"><i class="bi bi-building mr-2"></i><strong>Type:</strong> <span id="selectedFacilityType"></span></p>
                        <p class="text-gray-600"><i class="bi bi-people mr-2"></i><strong>Capacity:</strong> <span id="selectedFacilityCapacity"></span> students</p>
                        <p class="text-gray-600"><i class="bi bi-geo-alt mr-2"></i><strong>Site:</strong> <span id="selectedFacilitySiteName"></span></p>
                        <p class="text-gray-600"><i class="bi bi-building mr-2"></i><strong>Building:</strong> <span id="selectedFacilityBuilding"></span></p>
                    </div>
                    <button id="changeFacilityBtn" class="bg-gray-100 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-200 hover-scale flex items-center">
                        <i class="bi bi-arrow-left-right mr-2"></i> Change Facility
                    </button>
                </div>
            </div>

            <!-- Facility Selector Table -->
            <div id="facilitySelectorTable" class="bg-white rounded-xl shadow-lg p-6 fade-in">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                
                    <div class="flex items-center">
                        <span class="text-gray-600"><i class="bi bi-people mr-2"></i><strong>Required Capacity:</strong> <span id="requiredCapacity" class="font-semibold">0</span> students</span>
                    </div>
                    <div class="flex items-center" id="sessionFilterInfo">
                        <span class="text-gray-600 text-sm"><i class="bi bi-clock mr-2"></i><span id="sessionFilterLabel">No session set</span></span>
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
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Building</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Capacity</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Site</th>

                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
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
            const ACADEMIC_YEAR_ID = <?= json_encode(isset($accademic_year_id) ? $accademic_year_id : null) ?>;
            const SEMESTER = <?= json_encode(isset($semester) ? $semester : null) ?>;

            function getSelectedGroups() {
                return JSON.parse(localStorage.getItem('selectedGroups')) || [];
            }

            function getScheduleSessions() {
                const sessions = JSON.parse(localStorage.getItem('compactSchedules') || '[]');
                return Array.isArray(sessions) ? sessions.filter(s => s && s.day && s.start && s.end) : [];
            }

            function formatSessionLabel(sessions) {
                if (!sessions.length) return 'No session set';
                return sessions.map(s => `${s.day} ${s.start}-${s.end}`).join(', ');
            }

            function getRequiredCapacity() {
                const selectedGroups = getSelectedGroups();
                if (selectedGroups.length === 0) return 0;
                return selectedGroups.reduce((total, group) => total + (parseInt(group.size) || 0), 0);
            }

            function getCapacityStatus(capacity, required) {
                if (required === 0) return 'ok';
                if (capacity >= required * 1.2) return 'ok';
                if (capacity >= required * 0.8) return 'warning';
                return 'over';
            }

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
                $('#selectedFacilitySiteName').text(selectedFacility.site_name || 'N/A');
                $('#selectedFacilityBuilding').text(selectedFacility.buildname || 'N/A');

                $('#selectedFacilityCard').removeClass('hidden');
                $('#facilitySelectorTable').addClass('hidden');
                return true;
            }

            function updateSessionFilterLabel() {
                const sessions = getScheduleSessions();
                $('#sessionFilterLabel').text(formatSessionLabel(sessions));
                if (sessions.length) {
                    $('#availabilityHint').removeClass('hidden');
                } else {
                    $('#availabilityHint').addClass('hidden');
                }
            }

            function showPrerequisiteMessage(message) {
                $('#facilitySelectorTable').html(`
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="bi bi-exclamation-triangle text-yellow-400 text-xl"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700">${message}</p>
                            </div>
                        </div>
                    </div>
                `);
            }

            const selectedGroups = getSelectedGroups();
            const sessions = getScheduleSessions();

            // Always listen so setting/changing session refreshes this section
            window.addEventListener('scheduleUpdated', function() {
                window.location.reload();
            });

            if (selectedGroups.length === 0) {
                showPrerequisiteMessage('No groups selected. Please select groups first before choosing a facility.');
                return;
            }

            if (!sessions.length) {
                showPrerequisiteMessage('No session time set. Please set day and time first so only free facilities are shown.');
                return;
            }

            if (!ACADEMIC_YEAR_ID || !SEMESTER) {
                showPrerequisiteMessage('Academic year or semester is not configured in system settings.');
                return;
            }

            updateSessionFilterLabel();

            let table = $('#facilitiesTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: 'get_facilities_with_site.php',
                    type: 'GET',
                    data: function(d) {
                        const currentSessions = getScheduleSessions();
                        return {
                            draw: d.draw || 1,
                            start: d.start || 0,
                            length: d.length || 5,
                            search: d.search,
                            order: d.order,
                            columns: d.columns,
                            academic_year_id: ACADEMIC_YEAR_ID,
                            semester: SEMESTER,
                            sessions: JSON.stringify(currentSessions),
                            minCapacity: getRequiredCapacity()
                        };
                    },
                    dataSrc: function(json) {
                        if (!json || !Array.isArray(json.data)) {
                            console.error('Invalid data format from server:', json);
                            return [];
                        }
                        return json.data;
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', status, error);
                        console.error('Response:', xhr.responseText);
                    }
                },
                createdRow: function(row, data) {
                    const requiredCapacity = getRequiredCapacity();
                    const status = getCapacityStatus(data.capacity, requiredCapacity);
                    $(row).addClass(`capacity-${status}`);
                },
                columns: [
                    { 
                        data: 'buildname',
                        render: function(data) {
                            return data || 'N/A';
                        }
                    },
                    { 
                        data: 'name',
                        render: function(data, type, row) {
                            const requiredCapacity = getRequiredCapacity();
                            const status = getCapacityStatus(row.capacity, requiredCapacity);
                            const icons = {
                                'ok': '<i class="bi bi-check-circle-fill text-green-500 mr-1"></i>',
                                'warning': '<i class="bi bi-exclamation-triangle-fill text-yellow-500 mr-1"></i>',
                                'over': '<i class="bi bi-x-circle-fill text-red-500 mr-1"></i>'
                            };
                            return `${icons[status] || ''} ${data}`;
                        }
                    },
                    { 
                        data: 'type',
                        render: function(data) {
                            return data || 'N/A';
                        }
                    },
                    { 
                        data: 'capacity',
                        render: function(data, type, row) {
                            const requiredCapacity = getRequiredCapacity();
                            const status = getCapacityStatus(data, requiredCapacity);
                            const textColors = {
                                'ok': 'text-green-600',
                                'warning': 'text-yellow-600',
                                'over': 'text-red-600'
                            };
                            return `<span class="font-semibold ${textColors[status] || ''}">${data} <span class="text-gray-500">/ ${requiredCapacity}</span></span>`;
                        }
                    },
                    { 
                        data: 'site_name',
                        render: function(data) {
                            return data || 'N/A';
                        }
                    },
                    {
                        data: null,
                        render: function(data, type, row) {
                            const requiredCapacity = getRequiredCapacity();
                            const status = getCapacityStatus(row.capacity, requiredCapacity);
                            const statusText = {
                                'ok': 'Available',
                                'warning': 'Limited space',
                                'over': 'Too small'
                            };
                            const statusColors = {
                                'ok': 'bg-green-100 text-green-800',
                                'warning': 'bg-yellow-100 text-yellow-800',
                                'over': 'bg-red-100 text-red-800'
                            };
                            return `<span class="px-2 py-1 text-xs font-medium rounded-full ${statusColors[status] || ''}">
                                ${statusText[status] || 'Unknown'}
                            </span>`;
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        render: function(data, type, row) {
                            const requiredCapacity = getRequiredCapacity();
                            const status = getCapacityStatus(row.capacity, requiredCapacity);
                            const buttonClass = status === 'over' ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700';
                            return `<button class="${buttonClass} text-white px-3 py-1 rounded-md hover-scale selectFacilityBtn" 
                                    data-fac='${JSON.stringify(row).replace(/'/g, "&apos;")}'
                                    title="Select this available facility">
                                Select
                            </button>`;
                        }
                    }
                ],
                paging: true,
                pageLength: 5,
                lengthMenu: [5, 10, 25, 50],
                lengthChange: true,
                searching: true,
                ordering: true,
                deferRender: true,
                responsive: true,
                autoWidth: false,
                language: {
                    emptyTable: 'No available facilities for this session time',
                    zeroRecords: 'No available facilities match your search',
                    processing: '<div class="flex justify-center"><svg class="animate-spin h-5 w-5 text-blue-600" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg></div>'
                }
            });

            function reloadFacilitiesForSchedule() {
                // Full reload keeps availability filter in sync with the latest session
                window.location.reload();
            }

            $('#refreshBtn').on('click', function() {
                if (getSelectedGroups().length === 0 || getScheduleSessions().length === 0) {
                    window.location.reload();
                } else {
                    $('#requiredCapacity').text(getRequiredCapacity());
                    table.ajax.reload();
                }
            });

            $('#facilitiesTable tbody').on('click', '.selectFacilityBtn', function() {
                const facData = $(this).data('fac');
                localStorage.setItem('selectedFacility', JSON.stringify(facData));
                showSelectedFacility();
            });

            $('#changeFacilityBtn').on('click', function() {
                localStorage.removeItem('selectedFacility');
                showSelectedFacility();
                $('#requiredCapacity').text(getRequiredCapacity());
                table.ajax.reload();
            });

            $('#requiredCapacity').text(getRequiredCapacity());
            showSelectedFacility();
        });
        </script>
    </body>
    </html>