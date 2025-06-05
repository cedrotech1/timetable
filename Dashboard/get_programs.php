<?php
include('connection.php');

// Set JSON content type header
header('Content-Type: text/html');

try {
    if (!isset($_GET['department_id']) || !is_numeric($_GET['department_id'])) {
        throw new Exception('Invalid department ID');
    }

    $department_id = (int)$_GET['department_id'];
    
    // Get programs for the specified department
    $query = "SELECT * FROM program WHERE department_id = $department_id ORDER BY name";
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
                        <th>Code</th>
                        <th class="text-center"></th>
                    </tr>
                </thead>
                <tbody>';
        
        // Output each program
        $counter = 1;
        while ($program = mysqli_fetch_assoc($result)) {
            echo '<tr data-id="' . $program['id'] . '">
                    <td>' . $counter++ . '</td>
                    <td>' . htmlspecialchars($program['name']) . '</td>
                    <td>' . htmlspecialchars($program['code']) . '</td>
                    <td class="text-center">
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-primary" onclick="editProgram(' . $program['id'] . ', \'' . htmlspecialchars($program['name']) . '\', \'' . htmlspecialchars($program['code']) . '\')">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteProgram(' . $program['id'] . ')">
                                <i class="bi bi-trash"></i>
                            </button>
                            <button class="btn btn-sm btn-info" onclick="showIntakes(' . $program['id'] . ')">
                                <i class="bi bi-calendar"></i> View Intakes
                            </button>
                        </div>
                    </td>
                </tr>';
        }
        
        // End table
        echo '</tbody></table>';
    } else {
        // No programs found
        echo '<div class="text-center py-5">
                <i class="bi bi-book text-muted" style="font-size: 3rem;"></i>
                <h4 class="mt-3">No Programs Found</h4>
                <p class="text-muted">There are no programs in this department yet.</p>
                <button class="btn btn-primary mt-3" onclick="showAddProgramModal(' . $department_id . ')">
                    <i class="bi bi-plus-circle me-1"></i>Add First Program
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