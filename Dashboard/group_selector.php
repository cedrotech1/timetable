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

<!-- Groups Modal -->
<div class="modal fade" id="groupsModal" tabindex="-1" aria-labelledby="groupsModalLabel" aria-hidden="true">
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
let selectedGroupIds = new Set();
let selectedGroupsData = new Map(); // Store group data for preview

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
    preview.innerHTML = '';
    
    selectedGroupsData.forEach((data, id) => {
        const groupDiv = document.createElement('div');
        groupDiv.className = 'selected-group';
        
        // Format group info
        const infoParts = [];
        if (data.size) infoParts.push(`Size: ${data.size}`);
        if (data.program_name) infoParts.push(data.program_name);
        if (data.intake_year && data.intake_month) {
            infoParts.push(`${data.intake_year}/${data.intake_month}`);
        }
        
        groupDiv.innerHTML = `
            <div>
                <div>${data.name}</div>
                <div class="group-info">
                    ${infoParts.join(' | ')}
                </div>
            </div>
            <button type="button" onclick="removeGroupFromPreview('${id}')" title="Remove group">
                <i class="fas fa-times"></i>
            </button>
        `;
        preview.appendChild(groupDiv);
    });
}

// Remove group from preview
function removeGroupFromPreview(groupId) {
    selectedGroupIds.delete(groupId);
    selectedGroupsData.delete(groupId);
    updateSelectedGroupsPreview();
    updateSelectedGroupsCount();
    
    // Update checkbox in table if visible
    const checkbox = document.querySelector(`.group-checkbox[value="${groupId}"]`);
    if (checkbox) {
        checkbox.checked = false;
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

// Add event listener for group checkboxes
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('group-checkbox')) {
        const groupId = e.target.value;
        const groupName = e.target.dataset.name;
        const groupSize = e.target.dataset.size || '';
        const programName = e.target.dataset.program || '';
        const intakeYear = e.target.dataset.intakeYear || '';
        const intakeMonth = e.target.dataset.intakeMonth || '';

        if (e.target.checked) {
            selectedGroupIds.add(groupId);
            selectedGroupsData.set(groupId, {
                name: groupName,
                size: groupSize,
                program_name: programName,
                intake_year: intakeYear,
                intake_month: intakeMonth
            });
        } else {
            selectedGroupIds.delete(groupId);
            selectedGroupsData.delete(groupId);
        }
        updateSelectedGroupsCount();
        updateSelectedGroupsPreview();
    }
});

// Select All checkbox      
document.getElementById('selectAllCheckbox').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.group-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
    updateSelectedGroupsCount();
});

// Select All button
document.getElementById('selectAllGroups').addEventListener('click', function() {
    const checkboxes = document.querySelectorAll('.group-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
    document.getElementById('selectAllCheckbox').checked = true;
    updateSelectedGroupsCount();
});

// Deselect All button
document.getElementById('deselectAllGroups').addEventListener('click', function() {
    const checkboxes = document.querySelectorAll('.group-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    document.getElementById('selectAllCheckbox').checked = false;
    updateSelectedGroupsCount();
});

// Group search
document.getElementById('groupSearch').addEventListener('input', function(e) {
    const searchText = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('#groupsTableBody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchText) ? '' : 'none';
    });
});

// Clear search
document.getElementById('clearSearch').addEventListener('click', function() {
    document.getElementById('groupSearch').value = '';
    const rows = document.querySelectorAll('#groupsTableBody tr');
    rows.forEach(row => {
        row.style.display = '';
    });
});

// Clear all selections button
document.getElementById('clearAllSelections').addEventListener('click', function() {
    selectedGroupIds.clear();
    selectedGroupsData.clear();
    updateSelectedGroupsPreview();
    updateSelectedGroupsCount();
    
    // Update all checkboxes
    document.querySelectorAll('.group-checkbox').forEach(checkbox => {
        checkbox.checked = false;
    });
    document.getElementById('selectAllCheckbox').checked = false;
});

// Update loadGroups function
function loadGroups(intakeId) {
    console.log('Loading groups for intake:', intakeId);
    fetch(`selectors/get_groups.php?intake_id=${intakeId}`)
        .then(response => {
            console.log('Groups response status:', response.status);
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
            console.log('Groups data:', data);
            if (data.success) {
                const tbody = document.getElementById('groupsTableBody');
                tbody.innerHTML = '';
                data.data.forEach(group => {
                    const row = document.createElement('tr');
                    row.className = 'group-row';
                    const isSelected = selectedGroupIds.has(group.id);
                    
                    // Format group info for data attributes
                    const programName = group.program_name || '';
                    const intakeYear = group.intake_year || '';
                    const intakeMonth = group.intake_month || '';
                    
                    row.innerHTML = `
                        <td>
                            <input type="checkbox" class="form-check-input group-checkbox" 
                                   value="${group.id}" 
                                   data-name="${group.name}"
                                   data-size="${group.size || ''}"
                                   data-program="${programName}"
                                   data-intake-year="${intakeYear}"
                                   data-intake-month="${intakeMonth}"
                                   ${isSelected ? 'checked' : ''}>
                        </td>
                        <td>${group.name}</td>
                        <td>${group.size || ''}</td>
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

// Update confirm groups button
document.getElementById('confirmGroups').addEventListener('click', function() {
    if (selectedGroupIds.size === 0) {
        alert('Please select at least one group');
        return;
    }

    const selectedGroupsDiv = document.getElementById('selectedGroups');
    selectedGroupsDiv.innerHTML = '';

    selectedGroupsData.forEach((data, id) => {
        const groupDiv = document.createElement('div');
        groupDiv.className = 'selected-group';
        
        // Format group info
        const infoParts = [];
        if (data.size) infoParts.push(`Size: ${data.size}`);
        if (data.program_name) infoParts.push(data.program_name);
        if (data.intake_year && data.intake_month) {
            infoParts.push(`${data.intake_year}/${data.intake_month}`);
        }
        
        groupDiv.innerHTML = `
            <div>
                <div>${data.name}</div>
                <div class="group-info">
                    ${infoParts.join(' | ')}
                </div>
            </div>
            <button type="button" onclick="removeGroup('${id}')" title="Remove group">
                <i class="fas fa-times"></i>
            </button>
            <input type="hidden" name="group_ids[]" value="${id}">
        `;
        selectedGroupsDiv.appendChild(groupDiv);
    });

    // Update display
    document.getElementById('selectedGroupsDisplay').value = 
        selectedGroupIds.size > 0 ? `${selectedGroupIds.size} group(s) selected` : '';

    // Close modal
    bootstrap.Modal.getInstance(document.getElementById('groupsModal')).hide();
    
    // Update facility button state
    if (typeof window.updateFacilityButton === 'function') {
        window.updateFacilityButton();
    }

    // Update submit button state
    if (typeof window.updateSubmitButton === 'function') {
        window.updateSubmitButton();
    }
});

function removeGroup(groupId) {
    const groupElement = document.querySelector(`input[value="${groupId}"]`).parentElement;
    groupElement.remove();
    selectedGroupIds.delete(groupId);
    
    // Update display
    const selectedGroupsDisplay = document.getElementById('selectedGroupsDisplay');
    selectedGroupsDisplay.value = selectedGroupIds.size > 0 
        ? `${selectedGroupIds.size} group(s) selected` 
        : '';
        
    // Call updateFacilityButton to update facility button state
    if (typeof window.updateFacilityButton === 'function') {
        window.updateFacilityButton();
    }
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
</script> 