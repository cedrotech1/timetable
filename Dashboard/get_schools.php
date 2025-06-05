<?php
include('connection.php');

// Set JSON content type header
header('Content-Type: text/html');

try {
    if (!isset($_GET['college_id']) || !is_numeric($_GET['college_id'])) {
        throw new Exception('Invalid college ID');
    }

    $college_id = (int)$_GET['college_id'];
    
    // Get schools for the specified college
    $query = "SELECT * FROM school WHERE college_id = $college_id ORDER BY name";
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
        
        // Output each school
        $counter = 1;
        while ($school = mysqli_fetch_assoc($result)) {
            echo '<tr data-id="' . $school['id'] . '">
                    <td>' . $counter++ . '</td>
                    <td>' . htmlspecialchars($school['name']) . '</td>
                    <td class="text-center">
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-primary" onclick="editSchool(' . $school['id'] . ', \'' . htmlspecialchars($school['name']) . '\')">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteSchool(' . $school['id'] . ')">
                                <i class="bi bi-trash"></i>
                            </button>
                            <button class="btn btn-sm btn-info" onclick="showDepartments(' . $school['id'] . ')">
                                <i class="bi bi-building"></i> View Departments
                            </button>
                        </div>
                    </td>
                </tr>';
        }
        
        // End table
        echo '</tbody></table>';
    } else {
        // No schools found
        echo '<div class="text-center py-5">
                <i class="bi bi-building text-muted" style="font-size: 3rem;"></i>
                <h4 class="mt-3">No Schools Found</h4>
                <p class="text-muted">There are no schools in this college yet.</p>
                <button class="btn btn-primary mt-3" onclick="showAddSchoolModal(' . $college_id . ')">
                    <i class="bi bi-plus-circle me-1"></i>Add First School
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