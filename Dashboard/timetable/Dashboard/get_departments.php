<?php
include('connection.php');

// Set JSON content type header
header('Content-Type: text/html');

try {
    if (!isset($_GET['school_id']) || !is_numeric($_GET['school_id'])) {
        throw new Exception('Invalid school ID');
    }

    $school_id = (int)$_GET['school_id'];
    
    // Get departments for the specified school
    $query = "SELECT * FROM department WHERE school_id = $school_id ORDER BY name";
    $result = mysqli_query($connection, $query);
    
    if (!$result) {
        throw new Exception(mysqli_error($connection));
    }
    
    if (mysqli_num_rows($result) > 0) {
        // Start table
        echo '<table class="table table-hover table-borderless">
                <thead class="">
                    <tr style="border-bottom:0.3px solid #012970;">
                        <th>No.</th>
                        <th>Name</th>
                        <th class="text-center"></th>
                    </tr>
                </thead>
                <tbody>';
        
        // Output each department
        $counter = 1;
        while ($department = mysqli_fetch_assoc($result)) {
            echo '<tr data-id="' . $department['id'] . '">
                    <td>' . $counter++ . '</td>
                    <td>' . htmlspecialchars($department['name']) . '</td>
                    <td class="text-center">
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-primary" onclick="editDepartment(' . $department['id'] . ', \'' . htmlspecialchars($department['name']) . '\')">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteDepartment(' . $department['id'] . ')">
                                <i class="bi bi-trash"></i>
                            </button>
                            <button class="btn btn-sm btn-info" onclick="showPrograms(' . $department['id'] . ')">
                                <i class="bi bi-book"></i> View Programs
                            </button>
                        </div>
                    </td>
                </tr>';
        }
        
        // End table
        echo '</tbody></table>';
    } else {
        // No departments found
        echo '<div class="text-center py-5">
                <i class="bi bi-building text-muted" style="font-size: 3rem;"></i>
                <h4 class="mt-3">No Departments Found</h4>
                <p class="text-muted">There are no departments in this school yet.</p>
                <button class="btn btn-primary mt-3" onclick="showAddDepartmentModal(' . $school_id . ')">
                    <i class="bi bi-plus-circle me-1"></i>Add First Department
                </button>
              </div>';
    }

} catch (Exception $e) {
    echo '<div class="alert alert-danger m-3">
            <i class="bi bi-exclamation-triangle me-2"></i>
            Error: ' . htmlspecialchars($e->getMessage()) . '
          </div>';
}
?> 