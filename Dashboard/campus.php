<?php
session_start();
include('connection.php');
// only admin can access this page
if ($_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit();
}

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

// Fetch all colleges
$colleges = [];
$query = "SELECT * FROM college ORDER BY name";
$result = $connection->query($query);
while ($row = $result->fetch_assoc()) {
    $colleges[$row['id']] = array_merge($row, ['schools' => []]);
}

// Fetch all schools with their college
$schools = [];
$query = "SELECT s.*, col.name as college_name
          FROM school s 
          LEFT JOIN college col ON s.college_id = col.id 
          ORDER BY col.name, s.name";
$result = $connection->query($query);
while ($row = $result->fetch_assoc()) {
    $schools[$row['id']] = array_merge($row, ['programs' => []]);
    if (!isset($colleges[$row['college_id']])) {
        $colleges[$row['college_id']] = ['schools' => [], 'name' => $row['college_name']];
    }
    $colleges[$row['college_id']]['schools'][] = $row['id'];
}

// Fetch all programs with their school and department
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
    $programs[$row['id']] = array_merge($row, ['intakes' => []]);
    if (!isset($schools[$row['school_id']])) {
        $schools[$row['school_id']] = ['programs' => [], 'name' => $row['school_name']];
    }
    $schools[$row['school_id']]['programs'][] = $row['id'];
}

// Check which programs have modules
$programsWithModules = [];
$query = "SELECT DISTINCT program_id FROM module WHERE program_id IS NOT NULL";
$result = $connection->query($query);
while ($row = $result->fetch_assoc()) {
    $programsWithModules[$row['program_id']] = true;
}

// Fetch all intakes with their program and campus
$intakes = [];
$query = "SELECT i.*, p.name as program_name, c.name as campus_name, c.id as campus_id
          FROM intake i
          LEFT JOIN program p ON i.program_id = p.id
          LEFT JOIN campus c ON i.campus_id = c.id
          ORDER BY i.year DESC, i.month";
$result = $connection->query($query);
while ($row = $result->fetch_assoc()) {
    $intakes[$row['id']] = array_merge($row, ['groups' => []]);
    if (!isset($programs[$row['program_id']])) {
        $programs[$row['program_id']] = ['intakes' => [], 'name' => $row['program_name']];
    }
    $programs[$row['program_id']]['intakes'][] = $row['id'];
    
    if ($row['campus_id']) {
        if (!isset($campuses[$row['campus_id']])) {
            $campuses[$row['campus_id']] = ['intakes' => [], 'name' => $row['campus_name']];
    }
        $campuses[$row['campus_id']]['intakes'][] = $row['id'];
    }
}

// Fetch all student groups with their intake and campus
$studentGroups = [];
$query = "SELECT g.*, 
                 CONCAT('Intake ', i.year, '-', LPAD(i.month, 2, '0'), ' (', p.name, ') - ', c.name) as intake_name,
                 i.campus_id
          FROM student_group g
          LEFT JOIN intake i ON g.intake_id = i.id
          LEFT JOIN program p ON i.program_id = p.id
          LEFT JOIN campus c ON i.campus_id = c.id
          ORDER BY c.name, p.name, i.year DESC, i.month, g.name";
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
        .clickable-college {
            font-weight: 600;
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
        <div class="card p-4">
            <h2 class="main-title">Institutional Structure</h2>
            <div class="table-responsive">
                <table class="structure-table">
                    <thead>
                        <tr>
                         <th width="20%">   <a href="colleges.php">College</a></th>
                            <th width="20%"> <a href="all_schools.php">School/center</a></th>
                            <th width="30%"> <a href="all_programs.php">Programs</a></th>
                            <th width="30%">Promotions & Groups</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $hasData = false;

                        // Iterate through all colleges
                        foreach ($colleges as $collegeId => $college):
                            if (!isset($college['schools']) || !is_array($college['schools'])) {
                                $college['schools'] = [];
                            }

                            // Calculate total rows for this college
                            $collegeRowspan = 0;
                            if (!empty($college['schools'])) {
                                foreach ($college['schools'] as $schoolId) {
                                    if (!isset($schools[$schoolId])) continue;
                                    $school = $schools[$schoolId];
                                    if (!isset($school['programs']) || !is_array($school['programs'])) {
                                        $school['programs'] = [];
                                    }
                                    $programCount = count($school['programs']);
                                    $collegeRowspan += $programCount > 0 ? $programCount : 1;
                                }
                            } else {
                                $collegeRowspan = 1;
                            }

                            $isCollegeFirst = true;

                            if (!empty($college['schools'])) {
                                foreach ($college['schools'] as $schoolId):
                                    if (!isset($schools[$schoolId])) continue;
                                    $school = $schools[$schoolId];
                                    if (!isset($school['programs']) || !is_array($school['programs'])) {
                                        $school['programs'] = [];
                                    }

                                    // Calculate rows for this school
                                    $schoolRowspan = count($school['programs']) > 0 ? count($school['programs']) : 1;

                                    $isSchoolFirst = true;

                                    // Organize programs by department
                                    $departments = [];
                                    if (!empty($school['programs'])) {
                                        foreach ($school['programs'] as $programId) {
                                            if (!isset($programs[$programId])) continue;
                                            $program = $programs[$programId];
                                            $departmentId = $program['department_id'] ?? 0;
                                            $departmentName = $program['department_name'] ?? 'No Department';
                                            if (!isset($departments[$departmentId])) {
                                                $departments[$departmentId] = [
                                                    'name' => $departmentName,
                                                    'programs' => []
                                                ];
                                            }
                                            $departments[$departmentId]['programs'][$programId] = $program;
                                        }
                                    }

                                    if (!empty($departments)) {
                                        foreach ($departments as $deptId => $dept):
                                            $deptShown = false;

                                            foreach ($dept['programs'] as $programId => $program):
                                                $hasData = true;
                                                ?>
                                                <tr>
                                                    <?php if ($isCollegeFirst): ?>
                                                        <td rowspan="<?= $collegeRowspan ?>">
                                                            <a href="college_page.php?id=<?= $collegeId ?>" class="clickable clickable-college">
                                                                <?= htmlspecialchars($college['name']) ?>
                                                            </a>
                                                        </td>
                                                    <?php endif; ?>
                                                    <?php if ($isSchoolFirst): ?>
                                                        <td rowspan="<?= $schoolRowspan ?>">
                                                            <a href="school_page.php?id=<?= $schoolId ?>" class="clickable clickable-school">
                                                                <?= htmlspecialchars($school['name']) ?>
                                                            </a>
                                                        </td>
                                                    <?php endif; ?>
                                                    <td>
                                                        <?php if (!$deptShown && !empty($dept['name']) && $dept['name'] !== 'No Department'): ?>
                                                            <div class="department-name fw-bold mb-1">
                                                                <?= htmlspecialchars($dept['name']) ?>
                                                            </div>
                                                            <?php $deptShown = true; ?>
                                                        <?php endif; ?>
                                                        <div class="program-item ms-2">
                                                            <a href="program_management.php?program_id=<?= $program['id'] ?>&program_name=<?= urlencode($program['name']) ?>" class="clickable clickable-program">
                                                                <?= htmlspecialchars($program['name']) ?>
                                                            </a>
                                                            <?php if (isset($programsWithModules[$program['id']])): ?>
                                                                <button type="button"
                                                                        class="btn btn-link p-0 ms-2 view-modules"
                                                                        data-program-id="<?= (int) $program['id'] ?>"
                                                                        data-program-name="<?= htmlspecialchars($program['name'], ENT_QUOTES, 'UTF-8') ?>"
                                                                        title="View all modules">
                                                                    <i class="bi bi-check-circle-fill text-success"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        if (!isset($program['intakes']) || !is_array($program['intakes'])) {
                                                            $program['intakes'] = [];
                                                        }
                                                        $programIntakes = !empty($program['intakes']) ? array_map(function($intakeId) use ($intakes) {
                                                            return $intakes[$intakeId];
                                                        }, $program['intakes']) : [];

                                                        // Sort intakes by year of study (descending) and then by campus name
                                                        usort($programIntakes, function($a, $b) {
                                                            if ($a['year_of_study'] == $b['year_of_study']) {
                                                                return strcmp($a['campus_name'] ?? '', $b['campus_name'] ?? '');
                                                            }
                                                            return $b['year_of_study'] - $a['year_of_study'];
                                                        });

                                                        if (!empty($programIntakes)) {
                                                            // Group intakes by year_of_study and campus
                                                            $groupedIntakes = [];
                                                            foreach ($programIntakes as $intake) {
                                                                $key = $intake['year_of_study'] . '_' . $intake['campus_id'];
                                                                if (!isset($groupedIntakes[$key])) {
                                                                    $groupedIntakes[$key] = [
                                                                        'year_of_study' => $intake['year_of_study'],
                                                                        'campus_name' => $intake['campus_name'] ?? 'N/A',
                                                                        'groups' => []
                                                                    ];
                                                                }
                                                                // Merge groups from all intakes with same year and campus
                                                                if (!empty($intake['groups'])) {
                                                                    $groupedIntakes[$key]['groups'] = array_merge(
                                                                        $groupedIntakes[$key]['groups'] ?? [],
                                                                        $intake['groups']
                                                                    );
                                                                }
                                                            }
                                                            
                                                            // Sort by year of study (descending)
                                                            usort($groupedIntakes, function($a, $b) {
                                                                return $b['year_of_study'] - $a['year_of_study'];
                                                            });
                                                            
                                                            foreach ($groupedIntakes as $intake) {
                                                                echo '<div class="intake-item mb-2">';
                                                                echo '  <div class="d-flex align-items-center mb-1">';
                                                                echo '    <strong>Year ' . htmlspecialchars($intake['year_of_study']) . '</strong>';
                                                                echo '    <span class="badge bg-info ms-2">' . htmlspecialchars($intake['campus_name']) . '</span>';
                                                                echo '  </div>';
                                                                echo '  <div class="groups-list">';
                                                                
                                                                if (!empty($intake['groups'])) {
                                                                    foreach ($intake['groups'] as $groupId) {
                                                                        if (!isset($studentGroups[$groupId])) continue;
                                                                        $group = $studentGroups[$groupId];
                                                                        echo '<a href="group_page.php?id=' . $group['id'] . '" class="btn btn-sm btn-outline-secondary me-1 mb-1">';
                                                                        echo htmlspecialchars($group['name']);
                                                                        echo '</a>';
                                                                    }
                                                                } else {
                                                                    echo '<span class="text-muted small">No groups</span>';
                                                                }
                                                                
                                                                echo '  </div>';
                                                                echo '</div>';
                                                            }
                                                        } else {
                                                            echo '<span class="text-muted">No data available</span>';
                                                        }
                                                        ?>
                                                    </td>
                                                </tr>
                                                <?php
                                                $isCollegeFirst = false;
                                                $isSchoolFirst = false;
                                            endforeach;
                                        endforeach;
                                    } else {
                                        // School has no programs
                                        $hasData = true;
                                        ?>
                                        <tr>
                                            <?php if ($isCollegeFirst): ?>
                                                <td rowspan="<?= $collegeRowspan ?>">
                                                    <a href="college_page.php?id=<?= $collegeId ?>" class="clickable clickable-college">
                                                        <?= htmlspecialchars($college['name']) ?>
                                                    </a>
                                                </td>
                                            <?php endif; ?>
                                            <?php if ($isSchoolFirst): ?>
                                                <td rowspan="<?= $schoolRowspan ?>">
                                                    <a href="school_page.php?id=<?= $schoolId ?>" class="clickable clickable-school">
                                                        <?= htmlspecialchars($school['name']) ?>
                                                    </a>
                                                </td>
                                            <?php endif; ?>
                                            <td>
                                                <span class="text-muted">No programs</span>
                                            </td>
                                            <td>
                                                <span class="text-muted">No Promotions</span>
                                            </td>
                                        </tr>
                                        <?php
                                        $isCollegeFirst = false;
                                        $isSchoolFirst = false;
                                    }
                                endforeach;
                            } else {
                                // College has no schools
                                $hasData = true;
                                ?>
                                <tr>
                                    <td rowspan="1">
                                        <a href="college_page.php?id=<?= $collegeId ?>" class="clickable clickable-college">
                                            <?= htmlspecialchars($college['name']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="text-muted">No schools</span>
                                    </td>
                                    <td>
                                        <span class="text-muted">No programs</span>
                                    </td>
                                    <td>
                                        <span class="text-muted">No Promotions</span>
                                    </td>
                                </tr>
                                <?php
                            }
                        endforeach;

                        if (!$hasData): ?>
                            <tr>
                                <td colspan="4" class="text-center">No colleges found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <a href="#" class="back-to-top d-flex align-items-center justify-content-center">
        <i class="bi bi-arrow-up-short"></i>
    </a>

    <div class="modal fade" id="modulesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modulesModalLabel">Program Modules</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="modulesModalLoading" class="text-center py-4 d-none">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2 mb-0">Loading modules...</p>
                    </div>
                    <div id="modulesModalError" class="alert alert-danger d-none"></div>
                    <div id="modulesModalList" class="d-none"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const modalElement = document.getElementById('modulesModal');
        if (!modalElement) {
            return;
        }

        const modulesModal = new bootstrap.Modal(modalElement);
        const modulesModalLabel = document.getElementById('modulesModalLabel');
        const modulesModalLoading = document.getElementById('modulesModalLoading');
        const modulesModalError = document.getElementById('modulesModalError');
        const modulesModalList = document.getElementById('modulesModalList');

        function setModalState({ loading = false, error = '', items = [] }) {
            modulesModalLoading.classList.toggle('d-none', !loading);

            if (error) {
                modulesModalError.classList.remove('d-none');
                modulesModalError.textContent = error;
            } else {
                modulesModalError.classList.add('d-none');
                modulesModalError.textContent = '';
            }

            if (items.length) {
                const groups = new Map();
                items.forEach((module) => {
                    const rawYear = module.year ?? 'N/A';
                    const yearKey = rawYear && rawYear !== 'N/A' ? String(rawYear) : 'Unspecified';
                    if (!groups.has(yearKey)) {
                        groups.set(yearKey, []);
                    }
                    groups.get(yearKey).push(module);
                });

                const sortedKeys = Array.from(groups.keys()).sort((a, b) => {
                    const numA = Number(a);
                    const numB = Number(b);
                    const isNumA = !Number.isNaN(numA);
                    const isNumB = !Number.isNaN(numB);
                    if (isNumA && isNumB) {
                        return numB - numA; // descending
                    }
                    if (isNumA) return -1;
                    if (isNumB) return 1;
                    return a.localeCompare(b);
                });

                const fragment = document.createDocumentFragment();
                sortedKeys.forEach((year) => {
                    const section = document.createElement('div');
                    section.className = 'mb-3';

                    const header = document.createElement('div');
                    header.className = 'fw-bold text-primary mb-2';
                    header.textContent = year === 'Unspecified' ? 'Year not set' : `Year ${year}`;
                    section.appendChild(header);

                    const list = document.createElement('ul');
                    list.className = 'list-group';

                    groups.get(year).forEach((module) => {
                        const li = document.createElement('li');
                        li.className = 'list-group-item';
                        li.innerHTML = `
                            <div class="fw-semibold">${module.code ? module.code + ' — ' : ''}${module.name}</div>
                            <div class="small text-muted">Semester ${module.semester ?? 'N/A'} · ${module.credits ?? '0'} credits</div>
                        `;
                        list.appendChild(li);
                    });

                    section.appendChild(list);
                    fragment.appendChild(section);
                });

                modulesModalList.innerHTML = '';
                modulesModalList.appendChild(fragment);
                modulesModalList.classList.remove('d-none');
            } else {
                modulesModalList.classList.add('d-none');
                modulesModalList.innerHTML = '';
            }
        }

        document.addEventListener('click', function(event) {
            const trigger = event.target.closest('.view-modules');
            if (!trigger) {
                return;
            }

            const programId = trigger.getAttribute('data-program-id');
            const programName = trigger.getAttribute('data-program-name') || 'Program Modules';
            if (!programId) {
                return;
            }

            modulesModalLabel.textContent = `Modules for ${programName}`;
            setModalState({ loading: true });
            modulesModal.show();

            const params = new URLSearchParams({ program_id: programId, perPage: 500, page: 1, sort: 'name' });

            fetch(`api_get_modules.php?${params.toString()}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && Array.isArray(data.data)) {
                        if (data.data.length) {
                            const modules = data.data.map(module => ({
                                name: module.name || 'Untitled module',
                                code: module.code || '',
                                credits: module.credits || 0,
                                year: module.year || 'N/A',
                                semester: module.semester || 'N/A'
                            }));
                            setModalState({ items: modules });
                        } else {
                            setModalState({ error: 'No modules found for this program.' });
                        }
                    } else {
                        setModalState({ error: data.message || 'Failed to load modules.' });
                    }
                })
                .catch(() => {
                    setModalState({ error: 'A network error occurred while loading modules.' });
                });
        });
    });
    </script>
</body>
</html>