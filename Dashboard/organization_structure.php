<?php
session_start();
include('connection.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Organization Structure - UR-TIMETABLE</title>

    <!-- Favicons -->
    <link href="assets/img/icon1.png" rel="icon">
    <link href="assets/img/icon1.png" rel="apple-touch-icon">

    <!-- Google Fonts -->
    <link href="https://fonts.gstatic.com" rel="preconnect">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">

    <!-- Vendor CSS Files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
    <link href="assets/vendor/quill/quill.snow.css" rel="stylesheet">
    <link href="assets/vendor/quill/quill.bubble.css" rel="stylesheet">
    <link href="assets/vendor/remixicon/remixicon.css" rel="stylesheet">
    <link href="assets/vendor/simple-datatables/style.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <style>
        .tree-view {
            margin: 20px 0;
            font-family: 'Poppins', sans-serif;
        }
        .tree-view ul {
            list-style: none !important;
            padding-left: 30px;
            position: relative;
        }
        .tree-view ul:before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 1px;
            background: #e9ecef;
        }
        .tree-view li {
            margin: 12px 0;
            position: relative;
            list-style: none !important;
        }
        .tree-view li:before {
            content: '';
            position: absolute;
            left: -30px;
            top: 50%;
            width: 20px;
            height: 1px;
            background: #e9ecef;
        }
        /* Remove dots from all levels */
        .tree-view li::marker {
            display: none !important;
        }
        .tree-view li::before {
            display: none !important;
        }
        /* Add custom bullet for non-campus items */
        .tree-view li:not(.level-campus)::before {
            display: block !important;
            content: '';
            position: absolute;
            left: -30px;
            top: 50%;
            width: 20px;
            height: 1px;
            background: #e9ecef;
        }
        .tree-view .toggle {
            cursor: pointer;
            margin-right: 8px;
            transition: transform 0.2s;
            display: inline-block;
            width: 20px;
            height: 20px;
            text-align: center;
            line-height: 20px;
            border-radius: 50%;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
        }
        .tree-view .toggle:hover {
            background:rgb(169, 231, 193);
        }
        .tree-view .content {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 6px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            transition: all 0.2s;
            min-width: 200px;
        }
        .tree-view .content:hover {
            transform: translateX(5px);
        }
        .tree-view .collapsed {
            display: none;
        }
        .tree-view .badge {
            margin-left: 8px;
            font-weight: 500;
            padding: 5px 10px;
        }
        /* Level-specific colors */
        .tree-view .level-campus .content {
            background: #e3f2fd;
            border-color: #90caf9;
        }
        .tree-view .level-campus .badge {
            background: #1976d2;
            color: white;
        }
        .tree-view .level-college .content {
            background: #e8f5e9;
            border-color: #a5d6a7;
        }
        .tree-view .level-college .badge {
            background: #2e7d32;
            color: white;
        }
        .tree-view .level-school .content {
            background: #fff3e0;
            border-color: #ffcc80;
        }
        .tree-view .level-school .badge {
            background: #f57c00;
            color: white;
        }
        .tree-view .level-department .content {
            background: #f3e5f5;
            border-color: #ce93d8;
        }
        .tree-view .level-department .badge {
            background: #7b1fa2;
            color: white;
        }
        .tree-view .level-program .content {
            background: #e0f7fa;
            border-color: #80deea;
        }
        .tree-view .level-program .badge {
            background: #0097a7;
            color: white;
        }
        .tree-view .level-intake .content {
            background: #fce4ec;
            border-color: #f48fb1;
        }
        .tree-view .level-intake .badge {
            background: #c2185b;
            color: white;
        }
        .tree-view .level-group .content {
            background: #f1f8e9;
            border-color: #c5e1a5;
        }
        .tree-view .level-group .badge {
            background: #689f38;
            color: white;
        }
        /* Count badges */
        .tree-view .count-badge {
            margin-left: 8px;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            opacity: 0.8;
        }
        .level-campus .count-badge { background: #1976d2; }
        .level-college .count-badge { background: #2e7d32; }
        .level-school .count-badge { background: #f57c00; }
        .level-department .count-badge { background: #7b1fa2; }
        .level-program .count-badge { background: #0097a7; }
        .level-intake .count-badge { background: #c2185b; }
        .level-group .count-badge { background: #689f38; }

        /* Not Found Message */
        .not-found-message {
            padding: 15px;
            margin: 10px 0;
            background: #fff3cd;
            border: 1px solid #ffeeba;
            border-radius: 6px;
            color: #856404;
            text-align: center;
            font-weight: 500;
        }

        /* Reset Button Styles */
        .reset-btn {
            position: absolute;
            right: 20px;
            top: 20px;
            padding: 8px 15px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }
        .reset-btn:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        .reset-btn i {
            font-size: 1.1em;
        }
        .card {
            position: relative;
        }
    </style>
</head>

<body>
    <?php
    include("./includes/header.php");
    include("./includes/menu.php");
    ?>

    <main id="main" class="main">
        <div class="pagetitle">
            <h1>Organization Structure</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item active">Organization Structure</li>
                </ol>
            </nav>
        </div>

        <section class="section">
            <div class="row">
                <div class="col-lg-12">
                    <!-- Complete Structure View -->
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Complete Organization Structure</h5>
                            <button type="button" class="reset-btn" id="resetTreeView">
                                <i class="bi bi-arrow-counterclockwise"></i>
                                Reset View
                            </button>
                            <div id="treeView" class="tree-view">
                                <!-- Tree structure will be populated here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

    <!-- Vendor JS Files -->
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
    $(document).ready(function() {
        let organizationData = null;

        // Load organization structure
        function loadOrganizationStructure() {
            $.ajax({
                url: 'get_organization_structure.php',
                method: 'GET',
                success: function(response) {
                    if (response.success && response.data && response.data.length > 0) {
                        organizationData = response.data;
                        renderTreeView();
                    } else {
                        showNotFoundMessage();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    showNotFoundMessage();
                }
            });
        }

        // Show not found message
        function showNotFoundMessage() {
            const treeView = $('#treeView');
            treeView.html(`
                <div class="not-found-message">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    No organization structure data found
                </div>
            `);
        }

        // Render tree view
        function renderTreeView() {
            const treeView = $('#treeView');
            treeView.empty();

            organizationData.forEach(campus => {
                const campusHtml = createTreeItem('campus', campus);
                treeView.append(campusHtml);
            });
        }

        // Create tree item
        function createTreeItem(type, item) {
            const hasChildren = item.colleges || item.schools || item.departments || 
                              item.programs || item.intakes || item.groups;
            
            // Count children
            let childCount = 0;
            if (item.colleges) childCount += item.colleges.length;
            if (item.schools) childCount += item.schools.length;
            if (item.departments) childCount += item.departments.length;
            if (item.programs) childCount += item.programs.length;
            if (item.intakes) childCount += item.intakes.length;
            if (item.groups) childCount += item.groups.length;
            
            // Format intake name if it's an intake
            let displayName = item.name;
            if (type === 'intake' && item.year && item.month) {
                const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
                                  'July', 'August', 'September', 'October', 'November', 'December'];
                displayName = `${monthNames[item.month - 1]} ${item.year}`;
            }
            
            const $item = $(`
                <li class="level-${type}">
                    <span class="toggle ${hasChildren ? 'bi bi-chevron-right' : ''}"></span>
                    <span class="content">
                        ${displayName}
                        <span class="badge">${type.charAt(0).toUpperCase() + type.slice(1)}</span>
                        ${childCount > 0 ? `<span class="count-badge">${childCount}</span>` : ''}
                    </span>
                    ${hasChildren ? '<ul class="collapsed"></ul>' : ''}
                </li>
            `);

            if (hasChildren) {
                const $children = $item.find('ul');
                
                if (item.colleges) {
                    item.colleges.forEach(college => {
                        $children.append(createTreeItem('college', college));
                    });
                }
                if (item.schools) {
                    item.schools.forEach(school => {
                        $children.append(createTreeItem('school', school));
                    });
                }
                if (item.departments) {
                    item.departments.forEach(department => {
                        $children.append(createTreeItem('department', department));
                    });
                }
                if (item.programs) {
                    item.programs.forEach(program => {
                        $children.append(createTreeItem('program', program));
                    });
                }
                if (item.intakes) {
                    item.intakes.forEach(intake => {
                        $children.append(createTreeItem('intake', intake));
                    });
                }
                if (item.groups) {
                    item.groups.forEach(group => {
                        $children.append(createTreeItem('group', group));
                    });
                }
            }

            return $item;
        }

        // Handle tree toggle
        $(document).on('click', '.tree-view .toggle', function() {
            const $this = $(this);
            const $children = $this.siblings('ul');
            
            $this.toggleClass('bi-chevron-right bi-chevron-down');
            $children.toggleClass('collapsed');
        });

        // Load initial data
        loadOrganizationStructure();

        // Reset Tree View
        $('#resetTreeView').on('click', function() {
            // Collapse all expanded sections
            $('.tree-view .toggle.bi-chevron-down').click();
            // Scroll to top
            $('html, body').animate({ scrollTop: 0 }, 500);
        });
    });
    </script>

    <!-- Template Main JS File -->
    <script src="assets/js/main.js"></script>
</body>
</html> 