<?php
include('connection.php');

// Get student groups
$groups_query = "SELECT sg.*, i.year, i.month, p.name as program_name 
                FROM student_group sg 
                JOIN intake i ON sg.intake_id = i.id 
                JOIN program p ON i.program_id = p.id 
                ORDER BY i.year DESC, i.month DESC";
$groups_result = mysqli_query($connection, $groups_query);
?>

<!-- Groups Modal --><div class="modal fade" id="groupsModal" tabindex="-1" aria-labelledby="groupsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="groupsModalLabel">Select Student Groups</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <!-- Left side: Selected Groups -->
                    <div class="col-md-4 border-end">
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold mb-0">Selected Groups</h6>
                                <button type="button" class="btn btn-sm btn-outline-danger" id="clearAllSelections">
                                    <i class="fas fa-trash"></i> Clear All
                                </button>
                            </div>
                            <div id="selectedGroupsPreview" class="selected-groups-preview">
                                <!-- Selected groups will be shown here -->
                            </div>
                        </div>
                        <div class="mb-4">
                            <h6 class="fw-bold mb-3">Filters</h6>
                            <div class="mb-3">
                                <label for="campus" class="form-label">Campus</label>
                                <select class="form-select" id="campus">
                                    <option value="">Select Campus</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="college" class="form-label">College</label>
                                <select class="form-select" id="college" disabled>
                                    <option value="">Select College</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="school" class="form-label">School</label>
                                <select class="form-select" id="school" disabled>
                                    <option value="">Select School</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="department" class="form-label">Department</label>
                                <select class="form-select" id="department" disabled>
                                    <option value="">Select Department</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="modalProgram" class="form-label">Program</label>
                                <select class="form-select" id="modalProgram" disabled>
                                    <option value="">Select Program</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="intake" class="form-label">Intake</label>
                                <select class="form-select" id="intake" disabled>
                                    <option value="">Select Intake</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-4">
                            <h6 class="fw-bold mb-3">Search</h6>
                            <div class="input-group">
                                <input type="text" class="form-control" id="groupSearch" placeholder="Search groups...">
                                <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right side: Groups List -->
                    <div class="col-md-8">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold mb-0">Available Groups</h6>
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="selectAllGroups">Select All</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAllGroups">Deselect All</button>
                            </div>
                        </div>
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-hover">
                                <thead class="sticky-top bg-light">
                                    <tr>
                                        <th style="width: 50px;">
                                            <input type="checkbox" class="form-check-input" id="selectAllCheckbox">
                                        </th>
                                        <th>Group Name</th>
                                        <th>Size</th>
                                        <th>Program</th>
                                        <th>Intake</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="groupsTableBody">
                                    <!-- Groups will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <div class="d-flex justify-content-between align-items-center w-100">
                    <div>
                        <span class="badge bg-primary" id="selectedGroupsCount">0 groups selected</span>
                    </div>
                    <div>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="confirmGroups">Confirm Selection</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Make selectedGroupIds globally accessible
window.selectedGroupIds = window.selectedGroupIds || new Set();
window.selectedGroupsData = window.selectedGroupsData || new Map();

// Define updateSelectedGroupsCount in the global scope
function updateSelectedGroupsCount() {
    const selectedCount = selectedGroupIds.size;
    const countElement = document.getElementById('selectedGroupsCount');
    if (countElement) {
        countElement.textContent = `${selectedCount} groups selected`;
    }
}

// Update selected groups preview
function updateSelectedGroupsPreview() {
    const preview = document.getElementById('selectedGroupsPreview');
    if (!preview) return;
    
    preview.innerHTML = '';
    
    if (!window.selectedGroupsData || window.selectedGroupsData.size === 0) {
        preview.innerHTML = '<div class="text-muted text-center">No groups selected</div>';
        return;
    }
    
    // Calculate total size
    let totalSize = 0;
    window.selectedGroupsData.forEach((data, id) => {
        console.log('Processing group data for preview:', { id, data });
        if (data.size) {
            totalSize += parseInt(data.size);
        }
    });
    
    console.log('Total size for preview:', totalSize);
    
    // Add summary at the top
    const summaryDiv = document.createElement('div');
    summaryDiv.className = 'selected-groups-summary mb-3 p-2 bg-light rounded';
    summaryDiv.innerHTML = `
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <strong>${window.selectedGroupsData.size} Groups Selected</strong>
            </div>
            <div>
                <strong>Total Size: ${totalSize} Students</strong>
            </div>
        </div>
    `;
    preview.appendChild(summaryDiv);
    
    // Add individual groups
    window.selectedGroupsData.forEach((data, id) => {
        const groupDiv = document.createElement('div');
        groupDiv.className = 'selected-group';
        
        // Format group info
        const infoParts = [];
        if (data.size) infoParts.push(`Size: ${data.size} students`);
        if (data.program_name) infoParts.push(data.program_name);
        if (data.intake_year && data.intake_month) {
            infoParts.push(`${data.intake_year}/${data.intake_month}`);
        }
        
        groupDiv.innerHTML = `
            <div>
                <div class="group-name">${data.name}</div>
                <div class="group-info">
                    ${infoParts.join(' | ')}
                </div>
            </div>
            <button type="button" class="remove-group" data-group-id="${id}" title="Remove group">
                <i class="fas fa-times"></i>
            </button>
        `;
        preview.appendChild(groupDiv);
    });
}

// Handle group selection
function handleGroupSelection(groupId, isChecked, groupData) {
    console.log('Handling group selection:', { groupId, isChecked, groupData });
    
    if (isChecked) {
        window.selectedGroupIds.add(groupId);
        // Ensure size is properly stored as a number
        const size = parseInt(groupData.size) || 0;
        window.selectedGroupsData.set(groupId, {
            name: groupData.name,
            size: size.toString(),
            program_name: groupData.program_name,
            intake_year: groupData.intake_year,
            intake_month: groupData.intake_month
        });
    } else {
        window.selectedGroupIds.delete(groupId);
        window.selectedGroupsData.delete(groupId);
    }
    
    updateSelectedGroupsPreview();
    updateSelectedGroupsCount();
    
    // Update facility button state
    if (typeof window.updateFacilityButton === 'function') {
        console.log('Triggering facility button update from group selection');
        window.updateFacilityButton();
    }
}

// Add event listener for group checkboxes
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('group-checkbox')) {
        const groupId = e.target.value;
        const groupData = {
            name: e.target.dataset.name,
            size: e.target.dataset.size || '0',
            program_name: e.target.dataset.program,
            intake_year: e.target.dataset.intakeYear,
            intake_month: e.target.dataset.intakeMonth
        };
        console.log('Group checkbox changed:', { groupId, groupData });
        handleGroupSelection(groupId, e.target.checked, groupData);
    }
});

// Add event listener for group removal
document.addEventListener('click', function(e) {
    if (e.target.closest('.remove-group')) {
        const groupId = e.target.closest('.remove-group').dataset.groupId;
        removeGroupFromPreview(groupId);
    }
});

// Remove group from preview
function removeGroupFromPreview(groupId) {
    console.log('Removing group from preview:', groupId);
    window.selectedGroupIds.delete(groupId);
    window.selectedGroupsData.delete(groupId);
    
    // Update checkbox in table if visible
    const checkbox = document.querySelector(`.group-checkbox[value="${groupId}"]`);
    if (checkbox) {
        checkbox.checked = false;
    }
    
    updateSelectedGroupsPreview();
    updateSelectedGroupsCount();
}

// Handle confirm selection
document.getElementById('confirmGroups').addEventListener('click', function() {
    console.log('Confirming group selection:', {
        selectedGroups: Array.from(window.selectedGroupIds),
        groupData: Object.fromEntries(window.selectedGroupsData)
    });
    
    // Update the main display
    if (typeof window.updateSelectedGroupsDisplay === 'function') {
        window.updateSelectedGroupsDisplay();
    }
    
    // Update facility button state
    if (typeof window.updateFacilityButton === 'function') {
        console.log('Triggering facility button update from confirm selection');
        window.updateFacilityButton();
    }
    
    // Close the modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('groupsModal'));
    if (modal) {
        modal.hide();
    }
});

// Add event listener for modal show
document.getElementById('groupsModal').addEventListener('show.bs.modal', function() {
    console.log('Modal showing, current selection:', {
        selectedGroups: Array.from(window.selectedGroupIds),
        groupData: Object.fromEntries(window.selectedGroupsData)
    });
    
    // Update checkboxes to match current selection state
    document.querySelectorAll('.group-checkbox').forEach(checkbox => {
        checkbox.checked = window.selectedGroupIds.has(checkbox.value);
    });
    
    // Update the preview
    updateSelectedGroupsPreview();
    updateSelectedGroupsCount();
});

// Add event listener for modal hidden
document.getElementById('groupsModal').addEventListener('hidden.bs.modal', function() {
    console.log('Modal hidden, final selection:', {
        selectedGroups: Array.from(window.selectedGroupIds),
        groupData: Object.fromEntries(window.selectedGroupsData)
    });
});

// Handle clear all selections
document.getElementById('clearAllSelections').addEventListener('click', function() {
    console.log('Clearing all selections');
    window.selectedGroupIds.clear();
    window.selectedGroupsData.clear();
    
    // Uncheck all checkboxes
    document.querySelectorAll('.group-checkbox').forEach(checkbox => {
        checkbox.checked = false;
    });
    
    updateSelectedGroupsPreview();
    updateSelectedGroupsCount();
    
    // Update the main display if the function exists
    if (typeof window.updateSelectedGroupsDisplay === 'function') {
        window.updateSelectedGroupsDisplay();
    }
    
    // Update facility button state
    if (typeof window.updateFacilityButton === 'function') {
        console.log('Triggering facility button update from clear all');
        window.updateFacilityButton();
    }
});

// Update selected groups display
function updateSelectedGroupsDisplay() {
    const selectedGroupsDiv = document.getElementById('selectedGroups');
    if (!selectedGroupsDiv) return;
    
    selectedGroupsDiv.innerHTML = '';

    if (window.selectedGroupsData.size === 0) {
        selectedGroupsDiv.innerHTML = '<div class="text-muted text-center">No groups selected</div>';
        return;
    }

    // Calculate total size
    let totalSize = 0;
    window.selectedGroupsData.forEach((data, id) => {
        console.log('Processing group data for main display:', { id, data });
        if (data.size) {
            totalSize += parseInt(data.size);
        }
    });

    console.log('Total size for main display:', totalSize);

    // Add summary at the top
    const summaryDiv = document.createElement('div');
    summaryDiv.className = 'selected-groups-summary mb-3 p-2 bg-light rounded';
    summaryDiv.innerHTML = `
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <strong>${window.selectedGroupsData.size} Groups Selected</strong>
            </div>
            <div>
                <strong>Total Size: ${totalSize} Students</strong>
            </div>
        </div>
    `;
    selectedGroupsDiv.appendChild(summaryDiv);

    // Add individual groups
    window.selectedGroupsData.forEach((data, id) => {
        const groupDiv = document.createElement('div');
        groupDiv.className = 'selected-group';
        
        // Format group info
        const infoParts = [];
        if (data.size) infoParts.push(`Size: ${data.size} students`);
        if (data.program_name) infoParts.push(data.program_name);
        if (data.intake_year && data.intake_month) {
            infoParts.push(`${data.intake_year}/${data.intake_month}`);
        }
        
        groupDiv.innerHTML = `
            <div>
                <div class="group-name">${data.name}</div>
                <div class="group-info">
                    ${infoParts.join(' | ')}
                </div>
            </div>
            <button type="button" class="remove-group" data-group-id="${id}" title="Remove group">
                <i class="fas fa-times"></i>
            </button>
            <input type="hidden" name="group_ids[]" value="${id}">
        `;
        selectedGroupsDiv.appendChild(groupDiv);
    });

    // Update display text
    const displayInput = document.getElementById('selectedGroupsDisplay');
    if (displayInput) {
        displayInput.value = window.selectedGroupsData.size > 0 ? 
            `${window.selectedGroupsData.size} group(s) selected - Total: ${totalSize} students` : '';
    }

    // Update facility button state
    updateFacilityButton();
}

// Update facility button state
function updateFacilityButton() {
    const facilityButton = document.getElementById('facilityButton');
    if (facilityButton) {
        const hasGroups = window.selectedGroupIds.size > 0;
        const hasValidSchedule = document.querySelectorAll('.session-entry').length > 0 && 
            Array.from(document.querySelectorAll('.session-entry')).every(entry => {
                const day = entry.querySelector('.session-day').value;
                const startTime = entry.querySelector('.session-start').value;
                const endTime = entry.querySelector('.session-end').value;
                return day && startTime && endTime;
            });
        
        facilityButton.disabled = !(hasValidSchedule && hasGroups);
        
        // Add tooltip to explain why button is disabled
        if (facilityButton.disabled) {
            const reason = !hasValidSchedule ? 'Please complete schedule first' : 
                         !hasGroups ? 'Please select groups first' : 
                         'Please complete all required fields';
            facilityButton.title = reason;
        } else {
            facilityButton.title = 'Select a facility';
        }
    }
}

// Load initial campuses
loadCampuses();

// Add event listeners for all select elements
document.getElementById('campus').addEventListener('change', function() {
    if (this.value) {
        loadColleges(this.value);
    } else {
        resetSelects(['college', 'school', 'department', 'modalProgram', 'intake', 'group']);
    }
});

document.getElementById('college').addEventListener('change', function() {
    if (this.value) {
        loadSchools(this.value);
    } else {
        resetSelects(['school', 'department', 'modalProgram', 'intake', 'group']);
    }
});

document.getElementById('school').addEventListener('change', function() {
    if (this.value) {
        loadDepartments(this.value);
    } else {
        resetSelects(['department', 'modalProgram', 'intake', 'group']);
    }
});

document.getElementById('department').addEventListener('change', function() {
    if (this.value) {
        loadPrograms(this.value);
    } else {
        resetSelects(['modalProgram', 'intake', 'group']);
    }
});

document.getElementById('modalProgram').addEventListener('change', function() {
    if (this.value) {
        loadIntakes(this.value);
    } else {
        resetSelects(['intake', 'group']);
    }
});

document.getElementById('intake').addEventListener('change', function() {
    if (this.value) {
        loadGroups(this.value);
    } else {
        resetSelects(['group']);
    }
});

// Add event listener for search
document.getElementById('groupSearch').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#groupsTableBody tr');
    
    rows.forEach(row => {
        const groupName = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
        const programName = row.querySelector('td:nth-child(4)').textContent.toLowerCase();
        const intake = row.querySelector('td:nth-child(5)').textContent.toLowerCase();
        
        if (groupName.includes(searchTerm) || programName.includes(searchTerm) || intake.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

// Add event listener for clear search
document.getElementById('clearSearch').addEventListener('click', function() {
    document.getElementById('groupSearch').value = '';
    document.querySelectorAll('#groupsTableBody tr').forEach(row => {
        row.style.display = '';
    });
});

// Add event listeners for select all/deselect all
document.getElementById('selectAllGroups').addEventListener('click', function() {
    const visibleCheckboxes = Array.from(document.querySelectorAll('#groupsTableBody .group-checkbox'))
        .filter(checkbox => checkbox.closest('tr').style.display !== 'none');
    
    visibleCheckboxes.forEach(checkbox => {
        if (!checkbox.checked) {
            checkbox.checked = true;
            const groupId = checkbox.value;
            const groupData = {
                name: checkbox.dataset.name,
                size: checkbox.dataset.size,
                program_name: checkbox.dataset.program,
                intake_year: checkbox.dataset.intakeYear,
                intake_month: checkbox.dataset.intakeMonth
            };
            handleGroupSelection(groupId, true, groupData);
        }
    });
});

document.getElementById('deselectAllGroups').addEventListener('click', function() {
    const visibleCheckboxes = Array.from(document.querySelectorAll('#groupsTableBody .group-checkbox'))
        .filter(checkbox => checkbox.closest('tr').style.display !== 'none');
    
    visibleCheckboxes.forEach(checkbox => {
        if (checkbox.checked) {
            checkbox.checked = false;
            const groupId = checkbox.value;
            handleGroupSelection(groupId, false);
        }
    });
});

// Add event listener for select all checkbox
document.getElementById('selectAllCheckbox').addEventListener('change', function() {
    const visibleCheckboxes = Array.from(document.querySelectorAll('#groupsTableBody .group-checkbox'))
        .filter(checkbox => checkbox.closest('tr').style.display !== 'none');
    
    visibleCheckboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
        const groupId = checkbox.value;
        const groupData = {
            name: checkbox.dataset.name,
            size: checkbox.dataset.size,
            program_name: checkbox.dataset.program,
            intake_year: checkbox.dataset.intakeYear,
            intake_month: checkbox.dataset.intakeMonth
        };
        handleGroupSelection(groupId, this.checked, groupData);
    });
});

// Add event listener for modal show
document.getElementById('groupsModal').addEventListener('show.bs.modal', function() {
    // Update checkboxes to match current selection state
    document.querySelectorAll('.group-checkbox').forEach(checkbox => {
        checkbox.checked = window.selectedGroupIds.has(checkbox.value);
    });
});

// Update loadGroups function
function loadGroups(intakeId) {
    console.log('Loading groups for intake:', intakeId);
    fetch(`selectors/get_groups.php?intake_id=${intakeId}`)
        .then(response => response.json())
        .then(data => {
            console.log('Groups data:', data);
            if (data.success) {
                const tbody = document.getElementById('groupsTableBody');
                tbody.innerHTML = '';
                
                if (!data.data || data.data.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="6" class="text-center text-muted">
                                No groups available for this selection
                            </td>
                        </tr>
                    `;
                    return;
                }
                
                data.data.forEach(group => {
                    const row = document.createElement('tr');
                    row.className = 'group-row';
                    const isSelected = window.selectedGroupIds.has(group.id);
                    
                    // Format group info for data attributes
                    const programName = group.program_name || '';
                    const intakeYear = group.intake_year || '';
                    const intakeMonth = group.intake_month || '';
                    const groupSize = group.size || '0';
                    
                    row.innerHTML = `
                        <td>
                            <input type="checkbox" class="form-check-input group-checkbox" 
                                   value="${group.id}" 
                                   data-name="${group.name}"
                                   data-size="${groupSize}"
                                   data-program="${programName}"
                                   data-intake-year="${intakeYear}"
                                   data-intake-month="${intakeMonth}"
                                   ${isSelected ? 'checked' : ''}>
                        </td>
                        <td>${group.name}</td>
                        <td>${groupSize} students</td>
                        <td>${programName}</td>
                        <td>${intakeYear && intakeMonth ? `${intakeYear}/${intakeMonth}` : ''}</td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-primary view-group" 
                                    data-group-id="${group.id}">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
                updateSelectedGroupsCount();
            } else {
                console.error('Error loading groups:', data.message);
                alert('Error loading groups: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error in loadGroups:', error);
            alert('Error loading groups: ' + error.message);
        });
}

function resetSelects(selectIds) {
    selectIds.forEach(id => {
        const select = document.getElementById(id);
        select.innerHTML = `<option value="">Select ${id.charAt(0).toUpperCase() + id.slice(1)}</option>`;
        select.disabled = true;
    });
}

function loadCampuses() {
    console.log('Loading campuses...');
    fetch('selectors/get_campuses.php')
        .then(response => {
            console.log('Campuses response status:', response.status);
            return response.text().then(text => {
                console.log('Raw response:', text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', e);
                    throw new Error('Invalid JSON response: ' + e.message);
                }
            });
        })
        .then(data => {
            console.log('Campuses data:', data);
            if (data.success) {
                const select = document.getElementById('campus');
                select.innerHTML = '<option value="">Select Campus</option>';
                data.data.forEach(campus => {
                    select.innerHTML += `<option value="${campus.id}">${campus.name}</option>`;
                });
                select.disabled = false;
            } else {
                console.error('Error loading campuses:', data.message);
                alert('Error loading campuses: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error in loadCampuses:', error);
            alert('Error loading campuses: ' + error.message);
        });
}

function loadColleges(campusId) {
    console.log('Loading colleges for campus:', campusId);
    fetch(`selectors/get_colleges.php?campus_id=${campusId}`)
        .then(response => {
            console.log('Colleges response status:', response.status);
            return response.text().then(text => {
                console.log('Raw response:', text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', e);
                    throw new Error('Invalid JSON response: ' + e.message);
                }
            });
        })  
        .then(data => {
            console.log('Colleges data:', data);
            if (data.success) {
                const select = document.getElementById('college');
                select.innerHTML = '<option value="">Select College</option>';
                data.data.forEach(college => {
                    select.innerHTML += `<option value="${college.id}">${college.name}</option>`;
                });
                select.disabled = false;
            } else {
                console.error('Error loading colleges:', data.message);
                alert('Error loading colleges: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error in loadColleges:', error);
            alert('Error loading colleges: ' + error.message);
        });
}

function loadSchools(collegeId) {
    console.log('Loading schools for college:', collegeId);
    fetch(`selectors/get_schools.php?college_id=${collegeId}`)
        .then(response => {
            console.log('Schools response status:', response.status);
            return response.text().then(text => {
                console.log('Raw response:', text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', e);
                    throw new Error('Invalid JSON response: ' + e.message);
                }
            });
        })
        .then(data => {
            console.log('Schools data:', data);
            if (data.success) {
                const select = document.getElementById('school');
                select.innerHTML = '<option value="">Select School</option>';
                data.data.forEach(school => {
                    select.innerHTML += `<option value="${school.id}">${school.name} (${school.college_name})</option>`;
                });
                select.disabled = false;
            } else {
                console.error('Error loading schools:', data.message);
                alert('Error loading schools: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error in loadSchools:', error);
            alert('Error loading schools: ' + error.message);
        });
}

function loadDepartments(schoolId) {
    console.log('Loading departments for school:', schoolId);
    fetch(`selectors/get_departments.php?school_id=${schoolId}`)
        .then(response => {
            console.log('Departments response status:', response.status);
            return response.text().then(text => {
                console.log('Raw response:', text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', e);
                    throw new Error('Invalid JSON response: ' + e.message);
                }
            });
        })
        .then(data => {
            console.log('Departments data:', data);
            if (data.success) {
                const select = document.getElementById('department');
                select.innerHTML = '<option value="">Select Department</option>';
                data.data.forEach(department => {
                    select.innerHTML += `<option value="${department.id}">${department.name} (${department.school_name})</option>`;
                });
                select.disabled = false;
            } else {
                console.error('Error loading departments:', data.message);
                alert('Error loading departments: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error in loadDepartments:', error);
            alert('Error loading departments: ' + error.message);
        });
}

function loadPrograms(departmentId) {
    console.log('Loading programs for department:', departmentId);
    fetch(`selectors/get_programs.php?department_id=${departmentId}`)
        .then(response => {
            console.log('Programs response status:', response.status);
            return response.text().then(text => {
                console.log('Raw response:', text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', e);
                    throw new Error('Invalid JSON response: ' + e.message);
                }
            });
        })
        .then(data => {
            console.log('Programs data:', data);
            if (data.success) {
                const select = document.getElementById('modalProgram');
                select.innerHTML = '<option value="">Select Program</option>';
                data.data.forEach(program => {
                    select.innerHTML += `<option value="${program.id}">${program.name} (${program.code}) - ${program.department_name}</option>`;
                });
                select.disabled = false;
            } else {
                console.error('Error loading programs:', data.message);
                alert('Error loading programs: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error in loadPrograms:', error);
            alert('Error loading programs: ' + error.message);
        });
}

function loadIntakes(programId) {
    console.log('Loading intakes for program:', programId);
    fetch(`selectors/get_intakes.php?program_id=${programId}`)
        .then(response => {
            console.log('Intakes response status:', response.status);
            return response.text().then(text => {
                console.log('Raw response:', text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', e);
                    throw new Error('Invalid JSON response: ' + e.message);
                }
            });
        })
        .then(data => {
            console.log('Intakes data:', data);
            if (data.success) {
                const select = document.getElementById('intake');
                select.innerHTML = '<option value="">Select Intake</option>';
                data.data.forEach(intake => {
                    select.innerHTML += `<option value="${intake.id}">${intake.year}/${intake.month} (Size: ${intake.size})</option>`;
                });
                select.disabled = false;
            } else {
                console.error('Error loading intakes:', data.message);
                alert('Error loading intakes: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error in loadIntakes:', error);
            alert('Error loading intakes: ' + error.message);
        });
}

// Add event listener for schedule changes
document.addEventListener('change', function(e) {
    if (e.target.matches('.session-day, .session-start, .session-end')) {
        const sessionEntry = e.target.closest('.session-entry');
        const day = sessionEntry.querySelector('.session-day').value;
        const startTime = sessionEntry.querySelector('.session-start').value;
        const endTime = sessionEntry.querySelector('.session-end').value;
        
        // Update facility button state based on schedule completion
        updateFacilityButton();
    }
});

// Add styles for the summary
const style = document.createElement('style');
style.textContent = `
    .selected-groups-summary {
        border: 1px solid #dee2e6;
        background-color: #f8f9fa;
        margin-bottom: 1rem;
    }
    .selected-groups-summary strong {
        color: #0d6efd;
    }
    .selected-group {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.5rem;
        margin-bottom: 0.5rem;
        background-color: #fff;
        border: 1px solid #dee2e6;
        border-radius: 0.25rem;
    }
    .selected-group .group-info {
        font-size: 0.875rem;
        color: #6c757d;
    }
    .selected-group button {
        padding: 0.25rem 0.5rem;
        margin-left: 0.5rem;
    }
`;
document.head.appendChild(style);
</script> 