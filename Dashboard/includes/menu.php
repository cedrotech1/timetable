<aside id="sidebar" class="sidebar">
    <ul class="sidebar-nav" id="sidebar-nav">




        <li class="nav-item">
            <div class="card border-0 shadow-sm mb-3 role-card" style="background-color: rgba(255, 255, 255, 0.95);">
                <div class="card-body p-3">
                    <?php
                    $role = $_SESSION["role"];
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
                        'timetable_officer' => [
                            'icon' => 'bi-calendar-check',
                            'color' => 'info',
                            'title' => 'Timetable Officer',
                            'bg' => 'bg-info bg-opacity-10'
                        ],
                        'lecturer' => [
                            'icon' => 'bi-person-workspace',
                            'color' => 'warning',
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
                            <h6 class="mb-0 fw-bold text-<?php echo $info['color']; ?>"><?php echo ucfirst($role); ?></h6>
                            <small class="text-muted"><?php echo $info['title']; ?></small>
                        </div>
                    </div>
                    <div class="progress" style="height: 4px;">
                        <div class="progress-bar bg-<?php echo $info['color']; ?>" role="progressbar" style="width: 100%"></div>
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
                box-shadow: 0 5px 15px rgba(0,0,0,0.1) !important;
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
                background: linear-gradient(145deg, rgba(255,255,255,0.9), rgba(255,255,255,0.95));
            }
        </style>

        <?php if ($_SESSION['role'] == 'timetable_officer') { ?>

            <li class="nav-item">
                <a class="nav-link collapsed" href="index.php">
                    <i class="bi bi-speedometer2"></i><span>Dashboard</span>
                </a>
            </li>

            <!-- User Management -->
            <li class="nav-item">
                <a class="nav-link collapsed" href="add_user.php">
                    <i class="bi bi-people"></i><span>Manage Users</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link collapsed" data-bs-target="#timetable-nav" data-bs-toggle="collapse" href="#">
                    <i class="bi bi-calendar3"></i><span>Timetable</span><i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <ul id="timetable-nav" class="nav-content collapse" data-bs-parent="#sidebar-nav">
                    <li>
                        <a class="nav-link collapsed" href="timetable.php">
                            <i class="bi bi-pencil-square"></i><span>Manage Timetable</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link collapsed" href="timetable_cards_view.php">
                            <i class="bi bi-card-list"></i><span>Timetable Cards</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link collapsed" href="time_table_view.php">
                            <i class="bi bi-calendar-week"></i><span>Time Table View</span>
                        </a>
                    </li>
                </ul>
            </li>


            <!-- Organization -->
            <li class="nav-item">
                <a class="nav-link collapsed" href="organization_structure.php">
                    <i class="bi bi-diagram-3"></i><span>Organization Structure</span>
                </a>
            </li>

            <!-- Facilities -->
            <li class="nav-item">
                <a class="nav-link collapsed" href="facility_view.php">
                    <i class="bi bi-geo-alt"></i><span>Manage Facilities</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link collapsed" data-bs-target="#reset-nav" data-bs-toggle="collapse" href="#">
                    <i class="bi bi-exclamation-triangle text-danger"></i><span class="text-danger">Danger Zone</span><i
                        class="bi bi-chevron-down ms-auto"></i>
                </a>
                <ul id="reset-nav" class="nav-content collapse" data-bs-parent="#sidebar-nav">
                    <li>
                        <a class="nav-link collapsed text-danger" href="Reset_structure_data.php">
                            <i class="bi bi-trash"></i><span>Reset Structure Data</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link collapsed text-danger" href="Reset_docs_data.php">
                            <i class="bi bi-trash"></i><span>Reset Docs Data</span>
                        </a>
                    </li>
                </ul>
            </li>



        <?php } ?>

        <!-- Manage Data (Admin Only) -->
        <?php if ($_SESSION['role'] == 'campus_admin') { ?>

            <!-- User Management -->
            <li class="nav-item">
                <a class="nav-link collapsed" href="add_user.php">
                    <i class="bi bi-people"></i><span>Manage Users</span>
                </a>
            </li>

            <!-- Data Management -->
            <li class="nav-item">
                <a class="nav-link collapsed" data-bs-target="#data-nav" data-bs-toggle="collapse" href="#">
                    <i class="bi bi-database"></i><span>Data Management</span><i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <ul id="data-nav" class="nav-content collapse" data-bs-parent="#sidebar-nav">
                    <li>
                        <a class="nav-link collapsed" href="upload_data.php">
                            <i class="bi bi-upload"></i><span>Upload Data</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link collapsed" href="manage_data.php">
                            <i class="bi bi-pencil-square"></i><span>Manage Campus (Form)</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link collapsed" href="manage_campus.php">
                            <i class="bi bi-building"></i><span>Manage Campus</span>
                        </a>
                    </li>
                </ul>
            </li>


        <?php } ?>


        <?php if ($_SESSION['role'] == 'admin') { ?>
            <!-- Dashboard -->
            <li class="nav-item">
                <a class="nav-link collapsed" href="index.php">
                    <i class="bi bi-speedometer2"></i><span>Dashboard</span>
                </a>
            </li>

            <!-- User Management -->
            <li class="nav-item">
                <a class="nav-link collapsed" href="add_user.php">
                    <i class="bi bi-people"></i><span>Manage Users</span>
                </a>
            </li>

            <!-- Data Management -->
            <li class="nav-item">
                <a class="nav-link collapsed" data-bs-target="#data-nav" data-bs-toggle="collapse" href="#">
                    <i class="bi bi-database"></i><span>Data Management</span><i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <ul id="data-nav" class="nav-content collapse" data-bs-parent="#sidebar-nav">
                    <li>
                        <a class="nav-link collapsed" href="upload_data.php">
                            <i class="bi bi-upload"></i><span>Upload Data</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link collapsed" href="manage_data.php">
                            <i class="bi bi-pencil-square"></i><span>Manage Campus (Form)</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link collapsed" href="manage_campus.php">
                            <i class="bi bi-building"></i><span>Manage Campus</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- Timetable Management -->
            <li class="nav-item">
                <a class="nav-link collapsed" data-bs-target="#timetable-nav" data-bs-toggle="collapse" href="#">
                    <i class="bi bi-calendar3"></i><span>Timetable</span><i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <ul id="timetable-nav" class="nav-content collapse" data-bs-parent="#sidebar-nav">
                    <li>
                        <a class="nav-link collapsed" href="timetable.php">
                            <i class="bi bi-pencil-square"></i><span>Manage Timetable</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link collapsed" href="timetable_cards_view.php">
                            <i class="bi bi-card-list"></i><span>Timetable Cards</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link collapsed" href="time_table_view.php">
                            <i class="bi bi-calendar-week"></i><span>Time Table View</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- Organization -->
            <li class="nav-item">
                <a class="nav-link collapsed" href="organization_structure.php">
                    <i class="bi bi-diagram-3"></i><span>Organization Structure</span>
                </a>
            </li>

            <!-- Facilities -->
            <li class="nav-item">
                <a class="nav-link collapsed" href="facility_view.php">
                    <i class="bi bi-geo-alt"></i><span>Manage Facilities</span>
                </a>
            </li>

            <!-- System Settings -->
            <li class="nav-item">
                <a class="nav-link collapsed" data-bs-target="#settings-nav" data-bs-toggle="collapse" href="#">
                    <i class="bi bi-gear"></i><span>Settings</span><i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <ul id="settings-nav" class="nav-content collapse" data-bs-parent="#sidebar-nav">
                    <li>
                        <a class="nav-link collapsed" href="users-profile.php">
                            <i class="bi bi-person-circle"></i><span>Profile</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link collapsed" href="system.php">
                            <i class="bi bi-gear-fill"></i><span>System Settings</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link collapsed" href="download.php">
                            <i class="bi bi-download"></i><span>Backup Data</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- Data Reset (Danger Zone) -->
            <li class="nav-item">
                <a class="nav-link collapsed" data-bs-target="#reset-nav" data-bs-toggle="collapse" href="#">
                    <i class="bi bi-exclamation-triangle text-danger"></i><span class="text-danger">Danger Zone</span><i
                        class="bi bi-chevron-down ms-auto"></i>
                </a>
                <ul id="reset-nav" class="nav-content collapse" data-bs-parent="#sidebar-nav">
                    <li>
                        <a class="nav-link collapsed text-danger" href="Reset_structure_data.php">
                            <i class="bi bi-trash"></i><span>Reset Structure Data</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link collapsed text-danger" href="Reset_docs_data.php">
                            <i class="bi bi-trash"></i><span>Reset Docs Data</span>
                        </a>
                    </li>
                </ul>
            </li>

        <?php } ?>


        <!-- Logout -->
        <li class="nav-item">
            <a class="nav-link collapsed" href="../logout.php">
                <i class="bi bi-box-arrow-right"></i><span>Logout</span>
            </a>
        </li>



    </ul>
</aside>