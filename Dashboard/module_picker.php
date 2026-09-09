<?php
session_start();
include('connection.php');
include('includes/header.php');

$programId = isset($_GET['program_id']) ? intval($_GET['program_id']) : 0;
$timetableId = isset($_GET['timetable_id']) ? intval($_GET['timetable_id']) : 0;

if (!$programId || !$timetableId) {
    die("<div class='alert alert-danger'>Missing required parameters.</div>");
}
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4>Select Module</h4>
                    <p class="mb-0">Timetable ID: <?= htmlspecialchars($timetableId) ?></p>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Program</label>
                            <select id="moduleFilterProgram" class="form-select form-select-sm">
                                <option value="">All Programs</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Year</label>
                            <select id="moduleFilterYear" class="form-select form-select-sm">
                                <option value="">All Years</option>
                                <option value="1">Year 1</option>
                                <option value="2">Year 2</option>
                                <option value="3">Year 3</option>
                                <option value="4">Year 4</option>
                                <option value="5">Year 5</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Semester</label>
                            <select id="moduleFilterSemester" class="form-select form-select-sm">
                                <option value="">All Semesters</option>
                                <option value="1">Semester 1</option>
                                <option value="2">Semester 2</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="table-responsive
                        <table id="moduleTable" class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Credits</th>
                                    <th>Year</th>
                                    <th>Semester</th>
                                    <th>Program</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    const programId = <?= $programId ?>;
    const timetableId = <?= $timetableId ?>;
    let moduleTable;

    // Initialize DataTable
    function initDataTable() {
        return $('#moduleTable').DataTable({
            processing: true,
            serverSide: false,
            paging: true,
            pageLength: 10,
            lengthChange: false,
            searching: true,
            ordering: true,
            info: true,
            autoWidth: false,
            responsive: true,
            order: [[0, 'asc']],
            columns: [
                { data: 'code' },
                { data: 'name' },
                { data: 'credits' },
                { data: 'year' },
                { data: 'semester' },
                { data: 'program' },
                { 
                    data: null,
                    render: function(data, type, row) {
                        return `
                            <button class="btn btn-sm btn-primary select-module" 
                                data-id="${row.id}" 
                                data-name="${row.name.replace(/"/g, '&quot;')}" 
                                data-code="${row.code}" 
                                data-credits="${row.credits}">
                                Select
                            </button>
                        `;
                    },
                    orderable: false
                }
            ],
            language: {
                emptyTable: 'No modules found',
                info: 'Showing _START_ to _END_ of _TOTAL_ modules',
                infoEmpty: 'No modules to show',
                infoFiltered: '(filtered from _MAX_ total modules)'
            }
        });
    }

    // Load modules for the program
    function loadModules() {
        $.ajax({
            url: `./get_modules_for_specific_program.php?program_id=${programId}`,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data && response.data.length > 0) {
                    // Process and add data to the table
                    const modules = response.data.map(module => ({
                        id: module.id,
                        code: module.code || '',
                        name: module.name || '',
                        credits: module.credits || 0,
                        year: module.year || '',
                        semester: module.semester || '',
                        program: module.program_name || ''
                    }));
                    
                    // Clear and redraw the table
                    moduleTable.clear().rows.add(modules).draw();
                    
                    // Update program filter
                    const programs = [...new Set(modules.map(m => m.program).filter(Boolean))];
                    const programSelect = $('#moduleFilterProgram');
                    programSelect.empty().append('<option value="">All Programs</option>');
                    programs.forEach(program => {
                        programSelect.append(`<option value="${program}">${program}</option>`);
                    });
                } else {
                    showAlert('No modules found for this program.', 'warning');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading modules:', error);
                showAlert('Error loading modules. Please try again.', 'danger');
            }
        });
    }

    // Handle module selection
    function handleModuleSelection() {
        $('#moduleTable').on('click', '.select-module', function() {
            const moduleId = $(this).data('id');
            const moduleName = $(this).data('name');
            const moduleCode = $(this).data('code');
            const credits = $(this).data('credits');
            
            // Update the parent window and close this one
            if (window.opener && !window.opener.closed) {
                window.opener.updateSelectedModule(timetableId, moduleId, moduleName, moduleCode, credits);
                window.close();
            } else {
                showAlert('Could not communicate with the parent window. Please close this window and try again.', 'danger');
            }
        });
    }

    // Apply filters
    function applyFilters() {
        $('#moduleFilterProgram, #moduleFilterYear, #moduleFilterSemester').on('change', function() {
            const program = $('#moduleFilterProgram').val();
            const year = $('#moduleFilterYear').val();
            const semester = $('#moduleFilterSemester').val();
            
            moduleTable.column(5).search(program, true, false);
            moduleTable.column(3).search(year ? '^' + year + '$' : '', true, false);
            moduleTable.column(4).search(semester, true, false);
            
            moduleTable.draw();
        });
    }

    // Initialize everything
    function init() {
        moduleTable = initDataTable();
        loadModules();
        handleModuleSelection();
        applyFilters();
    }

    // Show alert message
    function showAlert(message, type = 'info') {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        
        $('.card-body').prepend(alertHtml);
    }

    // Start the application
    init();
});
</script>

<?php include('includes/footer.php'); ?>
