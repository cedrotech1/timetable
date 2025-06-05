<?php
include('connection.php');

if (!isset($_GET['intake_id'])) {
    echo '<div class="alert alert-danger">Intake ID is required</div>';
    exit;
}

$intake_id = (int)$_GET['intake_id'];

// Get student groups for the intake
$query = "SELECT * FROM student_group WHERE intake_id = $intake_id ORDER BY name";
$result = mysqli_query($connection, $query);

if (!$result) {
    echo '<div class="alert alert-danger">Error fetching student groups: ' . mysqli_error($connection) . '</div>';
    exit;
}

if (mysqli_num_rows($result) === 0) {
    echo '<div class="text-center py-5">
        <i class="bi bi-people text-muted" style="font-size: 3rem;"></i>
        <h4 class="mt-3">No Student Groups Found</h4>
        <p class="text-muted">There are no student groups for this intake yet.</p>
        <button class="btn btn-primary mt-3" onclick="showAddStudentGroupModal(' . $intake_id . ')">
            <i class="bi bi-plus-circle me-1"></i>Add First Student Group
        </button>
    </div>';
    exit;
}

// Display the table
echo '<table class="table table-hover table-borderless">
    <thead class="">
        <tr style="border-bottom:0.3px solid #012970;">
            <th>No.</th>
            <th>Name</th>
            <th>Capacity</th>
            <th class="text-center"></th>
        </tr>
    </thead>
    <tbody>';

// Output each student group
$counter = 1;
while ($group = mysqli_fetch_assoc($result)) {
    echo '<tr data-id="' . $group['id'] . '">
            <td>' . $counter++ . '</td>
            <td>' . htmlspecialchars($group['name']) . '</td>
            <td>' . $group['size'] . '</td>
            <td class="text-center">
                <div class="btn-group" role="group">
                    <button class="btn btn-sm btn-primary" onclick="editStudentGroup(' . $group['id'] . ', \'' . htmlspecialchars($group['name']) . '\', ' . $group['size'] . ')">
                        <i class="bi bi-pencil-square"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteStudentGroup(' . $group['id'] . ')">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </td>
        </tr>';
}

echo '</tbody></table>';
?> 