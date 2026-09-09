<aside id="sidebar" class="sidebar">
    <ul class="sidebar-nav" id="sidebar-nav">




        <li class="nav-item">
            <div class="card1 border-0 shadow-sm mb-3 role-card" style="background-color: rgba(255, 255, 255, 0.95); border: none;">
                <div class="card-body p-3">
                    <?php
                    $role = $_SESSION['role'];
                    $user_id = $_SESSION['id'];
                    // get school
                    $sql = "SELECT * FROM users WHERE id = $user_id";
                    $result = mysqli_query($connection, $sql);
                    $row = mysqli_fetch_assoc($result);
                    $school_id_menu = $row['school'];

                    $roleInfo = [
                        'admin' => [
                            'icon' => 'bi-shield-check',
                            'color' => 'primary',
                            'title' => 'System Administrator',
                            'bg' => 'bg-primary bg-opacity-10'
                        ],
                        'campus_admin' => [
                            'icon' => 'bi-building',
                            'color' => 'success',
                            'title' => 'Campus Administrator',
                            'bg' => 'bg-success bg-opacity-10'
                        ],
                        'dean_office' => [
                            'icon' => 'bi-building',
                            'color' => 'rgb(29, 42, 63)',
                            'title' => 'Dean Office',
                            'bg' => 'bg-info bg-opacity-10'
                        ],
                        'registrar_office' => [
                            'icon' => 'bi-person-workspace',
                            'color' => 'rgb(3,31,80)',
                            'title' => 'Registrar Office',
                            'bg' => 'bg-warning bg-opacity-10'
                        ],
                        'dtle' => [
                            'icon' => 'bi-person-workspace',
                            'color' => 'rgb(3,31,80)',
                            'title' => 'DTLE',
                            'bg' => 'bg-warning bg-opacity-10'
                        ],
                        'lecturer' => [
                            'icon' => 'bi-person-workspace',
                            'color' => 'rgb(3,31,80)',
                            'title' => 'Lecturer',
                            'bg' => 'bg-warning bg-opacity-10'
                        ]
                    ];
                    $info = $roleInfo[$role] ?? [
                        'icon' => 'bi-person',
                        'color' => 'secondary',
                        'title' => 'User',
                        'bg' => 'bg-secondary bg-opacity-10'
                    ];
                    ?>
                    <div class="d-flex align-items-center mb-2">
                        <div class="rounded-circle p-2 <?php echo $info['bg']; ?> me-2 role-icon">
                            <i class="bi <?php echo $info['icon']; ?> fs-4 text-<?php echo $info['color']; ?>"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold text-<?php echo $info['color']; ?>"><?php echo ucfirst($role); ?>
                            </h6>
                            <small class="text-muted"><?php echo $info['title']; ?></small>
                        </div>
                    </div>
                    <div class="progress" style="height: 4px;">
                        <div class="progress-bar bg-<?php echo $info['color']; ?>" role="progressbar"
                            style="width: 100%"></div>
                    </div>
                </div>
            </div>
        </li>

        <style>
            .role-card {
                transition: all 0.3s ease;
                backdrop-filter: blur(10px);
                background-color: rgba(16, 63, 133, 0.95) !important;
            }

            .role-card:hover {
                transform: translateY(-3px);
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1) !important;
                background-color: rgba(255, 255, 255, 1) !important;
            }

            .role-icon {
                transition: all 0.3s ease;
            }

            .role-card:hover .role-icon {
                transform: scale(1.1);
            }

            .progress-bar {
                transition: all 0.3s ease;
            }

            .role-card:hover .progress-bar {
                opacity: 0.8;
            }

            .card-body {
                background: linear-gradient(145deg, rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.95));
            }
        </style>

<li class="nav-item">
            <a class="nav-link collapsed" href="index.php">
                <i class="bi bi-house"></i><span>Dashboard</span>
            </a>
        </li>

        <?php
        if ($role == 'admin') {
            ?>

            <li class="nav-item">
            <a class="nav-link collapsed" href="add_user.php">
                <i class="bi bi-people"></i><span>User Management</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link collapsed" href="campus.php">
                <i class="bi bi-building"></i><span>Organizational structure</span>
            </a>
        </li>

        <!-- <li class="nav-item">
            <a class="nav-link collapsed" href="upload_data.php">
                <i class="bi bi-upload"></i><span>Upload Information</span>
            </a>
        </li> -->
        <!-- Manage Sites -->
        <li class="nav-item">
            <a class="nav-link collapsed" href="manage_sites.php">
                <i class="bi bi-building"></i><span>Manage Sites</span>
            </a>
        </li>
        <!-- Facilities -->
        <li class="nav-item">
            <a class="nav-link collapsed" href="facilities.php">
                <i class="bi bi-building"></i><span>Facilities</span>
            </a>
        </li>
        <!-- Modules -->
        <li class="nav-item">
            <a class="nav-link collapsed" href="modules.php">
                <i class="bi bi-book"></i><span>Modules</span>
            </a>
        </li>
        <!-- lecturers -->
        <!-- <li class="nav-item">
            <a class="nav-link collapsed" href="lecturers.php">
                <i class="bi bi-people"></i><span>Lecturers</span>
            </a>
        </li> -->
     
    
        <?php } ?>

        
<?php
if ($role == 'dtle') {
    ?>
   
   <li class="nav-item">
            <a class="nav-link collapsed" href="all_timetable.php">
                <i class="bi bi-calendar-check"></i><span>View Timetable</span>
            </a>
        </li>


    <?php
}
?>

<?php
if ($role == 'lecturer') {
    ?>
   
   <li class="nav-item">
            <a class="nav-link collapsed" href="all_timetable.php">
                <i class="bi bi-calendar-check"></i><span>View Timetable</span>
            </a>
        </li>
    <?php
}
?>


<?php
if ($role == 'dean_office') {
    ?>
    <li class="nav-item">
            <a class="nav-link collapsed" href="timetable.php">
                <i class="bi bi-calendar-check"></i><span> Schedule Timetable</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link collapsed" href="school-timetable.php">
                <i class="bi bi-calendar-check"></i><span>School Timetable</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link collapsed" href="facilities-school.php">
                <i class="bi bi-building"></i><span>Facilities</span>
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link collapsed" href="school_page.php?id=<?php echo $school_id_menu; ?>">
                <i class="bi bi-people"></i><span>Add student groups</span>
            </a>
        </li>

     



    <?php
}
?>

<?php
if ($role == 'registrar_office') {
    ?>
        
        <li class="nav-item">
            <a class="nav-link collapsed" href="all_timetable.php">
                <i class="bi bi-calendar-check"></i><span>View Timetable</span>
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link collapsed" href="system.php">
                <i class="bi bi-gear"></i><span>System Settings</span>
            </a>
        </li>
      


<?php } ?>




        <!-- Dashboard -->
     
        <!-- User Management -->
     

        <!-- Logout -->
        <li class="nav-item">
            <a class="nav-link collapsed" href="../logout.php">
                <i class="bi bi-box-arrow-right"></i><span>Logout</span>
            </a>
        </li>
        <!-- upload information -->
   
        
      


    </ul>
</aside>