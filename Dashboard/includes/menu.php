<aside id="sidebar" class="sidebar">
    <ul class="sidebar-nav" id="sidebar-nav">

        <!-- Manage Data (Admin Only) -->
        <?php if ($_SESSION['role'] == 'warefare') { ?>

            <li class="nav-item">
                <a class="nav-link collapsed" href="index.php">
                    <i class="bi bi-house-door"></i><span>Dashboard</span>
                </a>
            </li>
            <!-- upload hostels -->
            <li class="nav-item">
                <a class="nav-link collapsed" data-bs-target="#icons-nav" data-bs-toggle="collapse" href="#">
                    <i class="bi bi-database"></i><span>Manage Data</span><i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <ul id="icons-nav" class="nav-content collapse" data-bs-parent="#sidebar-nav">
                    <li>
                        <a class="nav-link collapsed" href="welfare_add_data.php">
                            <i class="bi bi-upload"></i><span>Upload student information</span>
                        </a>
                    </li>

                    <!-- <li>
                        <a class="nav-link collapsed" href="allstudents.php">
                            <i class="bi bi-card-heading"></i><span>All students</span>
                        </a>
                    </li> -->
                    <li>
                        <a class="nav-link collapsed" href="updateinfo.php">
                            <i class="bi bi-pencil-square"></i><span>Update Info</span>
                        </a>
                    </li>





                </ul>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed" href="search_warefare.php">
                    <i class="bi bi-file-earmark-bar-graph"></i><span>manage reports</span>
                </a>
            </li>


            <li>
                <a class="nav-link collapsed" href="manage_hostels.php">
                    <i class="bi bi-building"></i><span>manage hostels</span>
                </a>
            </li>
            <!-- manage application -->
            <li>
                <a class="nav-link collapsed" href="manage_applications.php">
                    <i class="bi bi-clock-history"></i><span>manage application</span>
                </a>
            </li>
            <!-- logout -->
            <li>
                <a class="nav-link collapsed" href="../logout.php">
                    <i class="bi bi-box-arrow-right"></i><span>logout</span>
                </a>
            </li>



        <?php } ?>


        <?php if ($_SESSION['role'] == 'information_modifier') { ?>
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
                    <i class="bi bi-exclamation-triangle text-danger"></i><span class="text-danger">Danger Zone</span><i class="bi bi-chevron-down ms-auto"></i>
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

            <!-- Logout -->
            <li class="nav-item">
                <a class="nav-link collapsed" href="../logout.php">
                    <i class="bi bi-box-arrow-right"></i><span>Logout</span>
                </a>
            </li>
        <?php } ?>



    </ul>
</aside>