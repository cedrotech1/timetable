<?php
include('connection.php');

// Set JSON content type header
header('Content-Type: text/html');

try {
    if (!isset($_GET['program_id']) || !is_numeric($_GET['program_id'])) {
        throw new Exception('Invalid program ID');
    }

    $program_id = (int)$_GET['program_id'];
    
    // Get intakes with their total group sizes
    $query = "SELECT i.*, 
              COUNT(sg.id) as total_groups,
              COALESCE(SUM(sg.size), 0) as total_capacity
              FROM intake i 
              LEFT JOIN student_group sg ON i.id = sg.intake_id 
              WHERE i.program_id = $program_id 
              GROUP BY i.id 
              ORDER BY i.year DESC, i.month DESC";
    
    $result = mysqli_query($connection, $query);
    
    if (!$result) {
        throw new Exception("Failed to fetch intakes: " . mysqli_error($connection));
    }
    
    if (mysqli_num_rows($result) > 0) {
        // Start table
        echo '<table class="table table-hover table-borderless">
                <thead class="">
                    <tr style="border-bottom:0.3px solid #012970;">
                        <th>No.</th>
                        <th>Name</th>
                       
                        <th>Total Groups</th>
                        <th>Total Capacity</th>
                        <th class="text-center"></th>
                    </tr>
                </thead>
                <tbody>';
        
        // Output each intake
        $counter = 1;
        while ($intake = mysqli_fetch_assoc($result)) {
            echo '<tr data-id="' . $intake['id'] . '">
                    <td>' . $counter++ . '</td>
                   <td>' . date('F', mktime(0, 0, 0, $intake['month'], 1)) . '-' . $intake['year'] . '</td>

                    <td>' . $intake['total_groups'] . '</td>
                    <td>' . $intake['total_capacity'] . '</td>
                    <td class="text-center">
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-primary" onclick="editIntake(' . $intake['id'] . ', ' . $intake['year'] . ', ' . $intake['month'] . ')">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteIntake(' . $intake['id'] . ')">
                                <i class="bi bi-trash"></i>
                            </button>
                            <button class="btn btn-sm btn-info" onclick="showStudentGroups(' . $intake['id'] . ')">
                                <i class="bi bi-people"></i> View Student Groups
                            </button>
                        </div>
                    </td>
                </tr>';
        }
        
        
        // End table
        echo '</tbody></table>';
    } else {
        // No intakes found
        echo '<div class="text-center py-5">
                <i class="bi bi-calendar text-muted" style="font-size: 3rem;"></i>
                <h4 class="mt-3">No Intakes Found</h4>
                <p class="text-muted">There are no intakes for this program yet.</p>
                <button class="btn btn-primary mt-3" onclick="showAddIntakeModal(' . $program_id . ')">
                    <i class="bi bi-plus-circle me-1"></i>Add First Intake
                </button>
              </div>';
    }

} catch (Exception $e) {
    echo '<div class="alert alert-danger m-3">
            <i class="bi bi-exclamation-triangle me-2"></i>
            ' . $e->getMessage() . '
        </div>';
}
?> 