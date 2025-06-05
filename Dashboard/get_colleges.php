<?php
include('connection.php');

// Set JSON content type header
header('Content-Type: text/html');

try {
    if (!isset($_GET['campus_id']) || !is_numeric($_GET['campus_id'])) {
        throw new Exception('Invalid campus ID');
    }

    $campus_id = (int)$_GET['campus_id'];
    
    // Get colleges for the specified campus
    $query = "SELECT * FROM college WHERE campus_id = $campus_id ORDER BY name";
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
        
        // Output each college
        $counter = 1;
        while ($college = mysqli_fetch_assoc($result)) {
            echo '<tr>
                    <td>' . $counter++ . '</td>
                    <td>' . htmlspecialchars($college['name']) . '</td>
                    <td class="text-center">
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-primary" onclick="editCollege(' . $college['id'] . ', \'' . htmlspecialchars($college['name']) . '\')">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteCollege(' . $college['id'] . ')">
                                <i class="bi bi-trash"></i>
                            </button>
                            <button class="btn btn-sm btn-info" onclick="showSchools(' . $college['id'] . ')">
                                <i class="bi bi-building"></i> View Schools
                            </button>
                        </div>
                    </td>
                </tr>';
        }
        
        // End table
        echo '</tbody></table>';
    } else {
        // No colleges found
        echo '<div class="text-center py-5">
                <i class="bi bi-building text-muted" style="font-size: 3rem;"></i>
                <h4 class="mt-3">No Colleges Found</h4>
                <p class="text-muted">There are no colleges in this campus yet.</p>
                <button class="btn btn-primary mt-3" onclick="showAddCollegeModal(' . $campus_id . ')">
                    <i class="bi bi-plus-circle me-1"></i>Add First College
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