<?php
session_start();
include('connection.php');

$user_id = $_SESSION['id'] ?? 0;

$stmt = $connection->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc() ?: [];

$role      = $user['role'] ?? '';
$campusId  = $user['campus'] ?? null;
$collegeId = $user['college'] ?? null;
$schoolId  = $user['school'] ?? null;

// Fetch all campuses
$campuses = [];
$query = "SELECT * FROM campus ORDER BY name";
$result = $connection->query($query);
while ($row = $result->fetch_assoc()) {
    $campuses[$row['id']] = $row;
}

// Fetch all colleges with their campus
$colleges = [];
$query = "SELECT c.*, camp.name as campus_name FROM college c 
          LEFT JOIN campus camp ON c.campus_id = camp.id 
          ORDER BY camp.name, c.name";
$result = $connection->query($query);
while ($row = $result->fetch_assoc()) {
    $colleges[$row['id']] = $row;
    $campuses[$row['campus_id']]['colleges'][] = $row['id'];
}

// Fetch all schools with their college
$schools = [];
$query = "SELECT s.*, col.name as college_name, col.campus_id 
          FROM school s 
          LEFT JOIN college col ON s.college_id = col.id 
          ORDER BY col.name, s.name";
$result = $connection->query($query);
while ($row = $result->fetch_assoc()) {
    $schools[$row['id']] = $row;
    $colleges[$row['college_id']]['schools'][] = $row['id'];
}

// Fetch all programs with their school (and department if exists)
$programs = [];
$query = "SELECT p.*, s.name as school_name, 
                 d.name as department_name,
                 d.id as department_id
          FROM program p 
          LEFT JOIN school s ON p.school_id = s.id 
          LEFT JOIN department d ON p.department_id = d.id 
          ORDER BY s.name, p.name";
$result = $connection->query($query);
while ($row = $result->fetch_assoc()) {
    $programs[$row['id']] = $row;
    $schools[$row['school_id']]['programs'][] = $row['id'];
}

// Fetch all intakes with their program
$intakes = [];
$query = "SELECT i.*, p.name as program_name 
          FROM intake i
          LEFT JOIN program p ON i.program_id = p.id
          ORDER BY i.year DESC, i.month";
$result = $connection->query($query);
while ($row = $result->fetch_assoc()) {
    $intakes[$row['id']] = $row;
    $programs[$row['program_id']]['intakes'][] = $row['id'];
}

// Fetch all student groups with their intake
$studentGroups = [];
$query = "SELECT g.*, CONCAT('Intake ', i.year, '-', LPAD(i.month, 2, '0'), ' (', p.name, ')') as intake_name
          FROM student_group g
          LEFT JOIN intake i ON g.intake_id = i.id
          LEFT JOIN program p ON i.program_id = p.id
          ORDER BY g.name";
$result = $connection->query($query);
while ($row = $result->fetch_assoc()) {
    $studentGroups[$row['id']] = $row;
    if (isset($intakes[$row['intake_id']])) {
        $intakes[$row['intake_id']]['groups'][] = $row['id'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Institutional Structure</title>
    <meta content="" name="description">
    <meta content="" name="keywords">
    <link href="assets/img/icon1.png" rel="icon">
    <link href="assets/img/icon1.png" rel="apple-touch-icon">
    <link href="https://fonts.gstatic.com" rel="preconnect">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body { font-size: 14px; }
        .structure-table { border-collapse: collapse; width: 100%; }
        .structure-table th, .structure-table td { 
            border: 1px solid #e0e0e0; 
            padding: 10px 15px; 
            vertical-align: top;
        }
        .structure-table th { 
            background-color: #031f50; 
            color: white; 
            font-weight: 600;
        }
        .structure-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .no-data {
            color: #6c757d;
            font-style: italic;
        }
        .main-title {
            color: #031f50;
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 2px solid #031f50;
        }
        .program-item,
        .intake-section,
        .group-item {
            margin-bottom: 5px;
            padding: 3px;
            border-radius: 2px;
        }
        
        .program-section {
            margin-bottom: 15px;
            padding: 5px;
            background-color: #f8f9fa;
            border-left: 3px solid #031f50;
        }
        
        .program-name {
            font-weight: 600;
            margin-bottom: 5px;
            color: #031f50;
        }
        
        .intake-section {
            margin-left: 15px;
            margin-bottom: 8px;
            padding: 5px;
            background-color: #f0f4f8;
        }
        
        .intake-name {
            font-weight: 500;
            margin-bottom: 3px;
        }
        
        .groups-list {
            margin-left: 15px;
        }
        
        .group-item {
            background-color: #e9ecef;
            padding: 2px 5px;
            margin: 2px 0;
            border-radius: 3px;
        }
        .department-programs {
            background-color: #e5ebf0;
        }
        .no-department-programs {
            background-color: #f0e5e5;
        }
        .department-name {
            font-weight: 500;
            color: #495057;
            margin-top: 5px;
            border-bottom: 1px solid #ccc;
        }
        .no-department-title {
            font-weight: 500;
            color: #495057;
            margin-top: 5px;
            border-bottom: 1px solid #ccc;
        }
        .clickable {
            color: #031f50;
            cursor: pointer;
            text-decoration: none;
            transition: color 0.2s;
        }
        .clickable:hover {
            color: #0056b3;
            text-decoration: underline;
        }
        .clickable-campus {
            font-weight: 600;
        }
        .clickable-college {
            font-weight: 500;
        }
        .clickable-school {
            font-weight: 500;
        }
    </style>
</head>
<body>
    <?php include('./includes/header.php'); ?>
    <?php include('./includes/menu.php'); ?>
    
    <main id="main" class="main">
        <div class="card container mt-4 p-4">
            <h2 class="main-title">Institutional Structure</h2>
            <div class="table-responsive">
                <table class="structure-table">
                    <thead>
                        <tr>
                            <th width="20%">Campus</th>
                            <th width="20%">College</th>
                            <th width="20%">School</th>
                            <th width="20%">Programs</th>
                            <th width="20%">Intakes & Groups</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($campuses as $campus): ?>
                            <?php if (!empty($campus['colleges'])): ?>
                                <?php 
                                // Calculate total rows for this campus
                                $campusRows = 0;
                                foreach ($campus['colleges'] as $collegeId) {
                                    $college = $colleges[$collegeId];
                                    if (!empty($college['schools'])) {
                                        $campusRows += count($college['schools']);
                                    } else {
                                        $campusRows += 1; // College with no schools
                                    }
                                }
                                ?>
                                
                                <?php $campusDisplayed = false; ?>
                                <?php foreach ($campus['colleges'] as $collegeId): ?>
                                    <?php $college = $colleges[$collegeId]; ?>
                                    <?php if (!empty($college['schools'])): ?>
                                        <?php 
                                        // Calculate total rows for this college
                                        $collegeRows = count($college['schools']);
                                        ?>
                                        
                                        <?php $collegeDisplayed = false; ?>
                                        <?php foreach ($college['schools'] as $schoolId): ?>
                                            <?php $school = $schools[$schoolId]; ?>
                                            <tr>
                                                <?php if (!$campusDisplayed): ?>
                                                    <td rowspan="<?= $campusRows ?>">
                                                        <a href="campus_page.php?id=<?= $campus['id'] ?>" class="clickable clickable-campus">
                                                            <?= htmlspecialchars($campus['name']) ?>
                                                        </a>
                                                    </td>
                                                    <?php $campusDisplayed = true; ?>
                                                <?php endif; ?>
                                                
                                                <?php if (!$collegeDisplayed): ?>
                                                    <td rowspan="<?= $collegeRows ?>">
                                                        <a href="college_page.php?id=<?= $college['id'] ?>" class="clickable clickable-college">
                                                            <?= htmlspecialchars($college['name']) ?>
                                                        </a>
                                                    </td>
                                                    <?php $collegeDisplayed = true; ?>
                                                <?php endif; ?>
                                                
                                                <td>
                                                    <a href="school_page.php?id=<?= $school['id'] ?>" class="clickable clickable-school">
                                                        <?= htmlspecialchars($school['name']) ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <?php if (!empty($school['programs'])): ?>
                                                        <div class="programs-list">
                                                            <?php 
                                                            // Separate programs with departments and without
                                                            $departmentPrograms = [];
                                                            $noDepartmentPrograms = [];
                                                            
                                                            foreach ($school['programs'] as $programId) {
                                                                $program = $programs[$programId];
                                                                if (!empty($program['department_id'])) {
                                                                    if (!isset($departmentPrograms[$program['department_id']])) {
                                                                        $departmentPrograms[$program['department_id']] = [
                                                                            'name' => $program['department_name'],
                                                                            'programs' => []
                                                                        ];
                                                                    }
                                                                    $departmentPrograms[$program['department_id']]['programs'][] = $program;
                                                                } else {
                                                                    $noDepartmentPrograms[] = $program;
                                                                }
                                                            }
                                                            
                                                            // Display programs with departments first
                                                            foreach ($departmentPrograms as $deptId => $deptData): ?>
                                                                <div class="department-programs">
                                                                    <div class="department-name">
                                                                        <a href="department_page.php?id=<?= $deptId ?>" class="clickable">
                                                                            <?= htmlspecialchars($deptData['name']) ?>
                                                                        </a>
                                                                    </div>
                                                                    <?php foreach ($deptData['programs'] as $program): ?>
                                                                        <div class="program-item">
                                                                            <a href="./program_management.php?program_id=<?= $program['id'] ?>&program_name=<?= urlencode($program['name']) ?>" class="clickable">
                                                                                <?= htmlspecialchars($program['name']) ?>
                                                                            </a>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            <?php endforeach; 
                                                            
                                                            // Then display programs without departments
                                                            if (!empty($noDepartmentPrograms)): ?>
                                                                <div class="no-department-programs">
                                                                    <div class="no-department-title">No Department</div>
                                                                    <?php foreach ($noDepartmentPrograms as $program): ?>
                                                                        <div class="program-item">
                                                                            <a href="./program_management.php?program_id=<?= $program['id'] ?>&program_name=<?= urlencode($program['name']) ?>" class="clickable">
                                                                                <?= htmlspecialchars($program['name']) ?>
                                                                            </a>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="no-data">No programs found</div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $programsWithIntakes = [];
                                                    if (!empty($school['programs'])) {
                                                        foreach ($school['programs'] as $programId) {
                                                            $program = $programs[$programId];
                                                            if (!empty($program['intakes'])) {
                                                                $programsWithIntakes[] = $program;
                                                            }
                                                        }
                                                    }
                                                    
                                                    if (!empty($programsWithIntakes)): ?>
                                                        <div class="intakes-groups-list">
                                                            <?php 
                                                            foreach ($programsWithIntakes as $program): 
                                                                $programHasIntakes = false;
                                                                
                                                                // First check if any intakes have groups
                                                                foreach ($program['intakes'] as $intakeId) {
                                                                    $intake = $intakes[$intakeId];
                                                                    if (!empty($intake['groups'])) {
                                                                        $programHasIntakes = true;
                                                                        break;
                                                                    }
                                                                }
                                                                
                                                                if ($programHasIntakes): ?>
                                                                    <div class="program-section">
                                                                        <div class="program-name">
                                                                            <strong><?= htmlspecialchars($program['name']) ?></strong>
                                                                        </div>
                                                                        
                                                                        <?php foreach ($program['intakes'] as $intakeId): 
                                                                            $intake = $intakes[$intakeId];
                                                                            if (!empty($intake['groups'])): ?>
                                                                                <div class="intake-section">
                                                                                    <div class="intake-name">
                                                                                        <a href="./program_management.php?program_id=<?= $program['id'] ?>&program_name=<?= urlencode($program['name']) ?>" class="clickable">
                                                                                            <?= htmlspecialchars($intake['year'] . '-' . str_pad($intake['month'], 2, '0', STR_PAD_LEFT)) ?>
                                                                                            (<?= $intake['size'] ?> students)
                                                                                        </a>
                                                                                    </div>
                                                                                    
                                                                                    <div class="groups-list">
                                                                                        <?php foreach ($intake['groups'] as $groupId): 
                                                                                            $group = $studentGroups[$groupId]; ?>
                                                                                            <div class="group-item">
                                                                                                <a href="./program_management.php?program_id=<?= $program['id'] ?>&program_name=<?= urlencode($program['name']) ?>" class="clickable">
                                                                                                    <?= htmlspecialchars($group['name']) ?>
                                                                                                    (<?= $group['size'] ?> students)
                                                                                                </a>
                                                                                            </div>
                                                                                        <?php endforeach; ?>
                                                                                    </div>
                                                                                </div>
                                                                            <?php endif; 
                                                                        endforeach; ?>
                                                                    </div>
                                                                <?php endif;
                                                            endforeach; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="no-data">No intakes or groups found</div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: // College with no schools ?>
                                        <tr>
                                            <?php if (!$campusDisplayed): ?>
                                                <td rowspan="<?= $campusRows ?>">
                                                    <a href="campus_page.php?id=<?= $campus['id'] ?>" class="clickable clickable-campus">
                                                        <?= htmlspecialchars($campus['name']) ?>
                                                    </a>
                                                </td>
                                                <?php $campusDisplayed = true; ?>
                                            <?php endif; ?>
                                            <td>
                                                <a href="college_page.php?id=<?= $college['id'] ?>" class="clickable clickable-college">
                                                    <?= htmlspecialchars($college['name']) ?>
                                                </a>
                                            </td>
                                            <td colspan="2" class="no-data">No schools available</td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php else: // Campus with no colleges ?>
                                <tr>
                                    <td>
                                        <a href="campus_page.php?id=<?= $campus['id'] ?>" class="clickable clickable-campus">
                                            <?= htmlspecialchars($campus['name']) ?>
                                        </a>
                                    </td>
                                    <td colspan="3" class="no-data">No colleges available</td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <a href="#" class="back-to-top d-flex align-items-center justify-content-center">
        <i class="bi bi-arrow-up-short"></i>
    </a>

    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>