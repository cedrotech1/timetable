<?php
session_start();
include("../connection.php");

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['student_id'];
$regnumber = $_SESSION['student_regnumber'];

// Get student's application status with more detailed information
$query = "SELECT 
            a.*,
            i.current_application,
            i.names,
            i.campus,
            i.college,
            i.school,
            i.program,
            i.yearofstudy,
            i.email,
            i.phone,
            r.room_code,
            h.name as hostel_name,
            TIMESTAMPDIFF(HOUR, a.created_at, NOW()) as hours_pending
          FROM info i
          LEFT JOIN applications a ON i.regnumber = a.regnumber
          LEFT JOIN rooms r ON a.room_id = r.id
          LEFT JOIN hostels h ON r.hostel_id = h.id
          WHERE i.regnumber = '$regnumber'";

$result = mysqli_query($connection, $query);
$application = mysqli_fetch_assoc($result);

// Calculate remaining time for pending applications
$remaining_hours = 48; // 2 days in hours
if ($application && $application['status'] == 'pending') {
    $remaining_hours = max(0, 48 - $application['hours_pending']);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Application Status - UR-HUYE</title>

    <!-- Favicons -->
    <link href="../icon1.png" rel="icon">
    <link href="../icon1.png" rel="apple-touch-icon">

    <!-- Google Fonts -->
    <link href="https://fonts.gstatic.com" rel="preconnect">
    <link
        href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i"
        rel="stylesheet">

    <!-- Vendor CSS Files -->
    <link href="../Dashboard/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../Dashboard/assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="../Dashboard/assets/css/style.css" rel="stylesheet">

    <style>
        .status-card {
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .status-card:hover {
            transform: translateY(-5px);
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
        }

        .status-pending {
            background-color: #ffc107;
            color: #000;
        }

        .status-paid {
            background-color: #17a2b8;
            color: #fff;
        }

        .status-approved {
            background-color: #28a745;
            color: #fff;
        }

        .status-rejected {
            background-color: #dc3545;
            color: #fff;
        }

        .countdown {
            font-size: 1.2rem;
            font-weight: 600;
            color: #dc3545;
        }

        .warning-message {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .student-info {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .student-info p {
            margin-bottom: 8px;
        }

        .student-info strong {
            color: #495057;
        }

        /* Enhanced skeleton loading styles */
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
            border-radius: 4px;
            position: relative;
            overflow: hidden;
        }

        .skeleton::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            transform: translateX(-100%);
            background-image: linear-gradient(90deg,
                    rgba(255, 255, 255, 0) 0,
                    rgba(255, 255, 255, 0.2) 20%,
                    rgba(255, 255, 255, 0.5) 60%,
                    rgba(255, 255, 255, 0));
            animation: shimmer 2s infinite;
        }

        .skeleton-text {
            height: 1em;
            margin-bottom: 0.5em;
        }

        .skeleton-title {
            height: 2em;
            margin-bottom: 1em;
        }

        .skeleton-card {
            height: 200px;
            margin-bottom: 1rem;
        }

        .skeleton-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
        }

        .skeleton-badge {
            height: 2em;
            width: 100px;
            border-radius: 8px;
        }

        .skeleton-countdown {
            height: 3em;
            width: 200px;
            border-radius: 8px;
        }

        @keyframes shimmer {
            100% {
                transform: translateX(100%);
            }
        }

        @keyframes loading {
            0% {
                background-position: 200% 0;
            }

            100% {
                background-position: -200% 0;
            }
        }

        /* Hide content while loading */
        .content-loading {
            display: none;
        }

        .skeleton-loading {
            display: block;
        }

        .clock-container {
            width: 200px;
            height: 200px;
            margin: 0 auto;
        }

        .clock-face {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: #f8f9fa;
            border: 8px solid #e9ecef;
            position: relative;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }

        .clock-center {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 12px;
            height: 12px;
            background: #dc3545;
            border-radius: 50%;
            transform: translate(-50%, -50%);
            z-index: 4;
        }

        .clock-hand {
            position: absolute;
            bottom: 50%;
            left: 50%;
            transform-origin: bottom;
            background: #495057;
            border-radius: 4px;
            z-index: 3;
        }

        .hour-hand {
            width: 4px;
            height: 60px;
            margin-left: -2px;
        }

        .minute-hand {
            width: 3px;
            height: 80px;
            margin-left: -1.5px;
        }

        .second-hand {
            width: 2px;
            height: 90px;
            margin-left: -1px;
            background: #dc3545;
        }

        .clock-marks {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
        }

        .mark {
            position: absolute;
            width: 2px;
            height: 10px;
            background: #495057;
            left: 50%;
            transform-origin: 50% 100px;
        }

        <?php for($i = 1; $i <= 12; $i++): ?>
        .mark-<?php echo $i; ?> {
            transform: rotate(<?php echo ($i * 30); ?>deg);
        }
        <?php endfor; ?>

        .progress-container {
            background: #f8f9fa;
            border-radius: 4px;
            padding: 2px;
        }

        .progress {
            background-color: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-bar {
            transition: width 1s linear;
        }
    </style>
</head>

<body>
    <!-- Loading Skeleton -->
    <div class="skeleton-loading">
        <?php
        $isLoading = true;
        include 'hostel_includes/student_info.php';
        ?>
        <main id="main" class="main">
            <div class="container">
                <div class="row">
                    <div class="col-lg-9">
                        <div class="card status-card">
                            <div class="card-body">
                                <div class="skeleton skeleton-title w-50 mx-auto mb-4"></div>

                                <!-- Student Information Skeleton -->
                                <div class="student-info mb-4">
                                    <div class="skeleton skeleton-title w-25 mb-3"></div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="skeleton skeleton-text w-75 mb-2"></div>
                                            <div class="skeleton skeleton-text w-75 mb-2"></div>
                                            <div class="skeleton skeleton-text w-75 mb-2"></div>
                                            <div class="skeleton skeleton-text w-75 mb-2"></div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="skeleton skeleton-text w-75 mb-2"></div>
                                            <div class="skeleton skeleton-text w-75 mb-2"></div>
                                            <div class="skeleton skeleton-text w-75 mb-2"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Application Status Skeleton -->
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="skeleton skeleton-text w-25 mb-2"></div>
                                        <div class="skeleton skeleton-badge mb-4"></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="skeleton skeleton-text w-75 mb-2"></div>
                                        <div class="skeleton skeleton-text w-75 mb-2"></div>
                                        <div class="skeleton skeleton-text w-75 mb-2"></div>
                                    </div>
                                </div>

                                <!-- Pending Status Skeleton -->
                                <div class="row mt-4">
                                    <div class="col-md-6">
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-body text-center p-4">
                                                <div class="skeleton skeleton-title w-50 mx-auto mb-3"></div>
                                                <div class="skeleton skeleton-countdown mx-auto"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-body text-center p-4">
                                                <div class="skeleton skeleton-title w-50 mx-auto mb-3"></div>
                                                <div class="skeleton skeleton-countdown mx-auto"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Actual Content -->
    <div class="content-loading">
        <?php
        $isLoading = false;
        include 'hostel_includes/student_info.php';
        ?>
        <main id="main" class="main">
            <div class="container">
                <div class="row">
                    <div class="col-lg-9">
                        <div class="card status-card">
                            <div class="card-body">
                                <h4 class="card-title text-center mb-4">Application Status</h4>

                                <?php if (!$application || !$application['status']): ?>
                                    <div class="text-center">
                                        <?php if ($application['current_application'] === 'rejected'): ?>
                                            <div class="alert alert-danger">
                                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                                <strong>Application Rejected</strong>
                                                <p class="mb-0 mt-2">Your application has been rejected due to uploading
                                                    inappropriate receipt. You can apply again.</p>
                                            </div>
                                        <?php elseif ($application['current_application'] === 'auto-rejected'): ?>
                                            <div class="alert alert-danger">
                                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                                <strong>Application Auto-Rejected</strong>
                                                <p class="mb-0 mt-2">Your application has been automatically rejected by the
                                                    system due to delay in uploading receipt, exceeding the 48 hours (two days)
                                                    limit. You can apply again.</p>
                                            </div>
                                        <?php else: ?>
                                            <p class="mb-4">You haven't submitted any application yet.</p>
                                        <?php endif; ?>
                                        <a href="index.php" class="btn btn-primary">Apply Now</a>
                                    </div>
                                <?php else: ?>
                                    <!-- Student Information -->
                                    <div class="student-info mb-4">
                                        <h5 class="mb-3">Student Information</h5>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <p><strong>Name:</strong>
                                                    <?php echo htmlspecialchars($application['names']); ?>
                                                </p>
                                                <p><strong>Registration:</strong>
                                                    <?php echo htmlspecialchars($regnumber); ?>
                                                </p>
                                                <p><strong>Campus:</strong>
                                                    <?php echo htmlspecialchars($application['campus']); ?></p>
                                                <p><strong>College:</strong>
                                                    <?php echo htmlspecialchars($application['college']); ?></p>
                                            </div>
                                            <div class="col-md-6">
                                                <p><strong>School:</strong>
                                                    <?php echo htmlspecialchars($application['school']); ?></p>
                                                <p><strong>Program:</strong>
                                                    <?php echo htmlspecialchars($application['program']); ?></p>
                                                <p><strong>Year:</strong>
                                                    <?php echo htmlspecialchars($application['yearofstudy']); ?></p>
                                                <p><strong>Email:</strong>
                                                    <?php echo htmlspecialchars($application['email']); ?></p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Application Status -->
                                    <div class="row">
                                        <div class="col-md-6">
                                            <!-- <p class="text-muted mb-0">
                                                Now your room is reserved for you total,
                                            </p> -->
                                            <p><strong>Status:</strong></p>
                                            <span
                                                class="status-badge mb-4 status-<?php echo strtolower($application['status']); ?>">
                                                <?php echo ucfirst($application['status']); ?>
                                            </span>
                                        </div>
                                        <div class="col-md-6 mt-2">
                                            <p><strong>Room:</strong>
                                                <?php echo htmlspecialchars($application['room_code']); ?>
                                            </p>
                                            <p><strong>Hostel:</strong>
                                                <?php echo htmlspecialchars($application['hostel_name']); ?></p>
                                            <p><strong>Application Date:</strong>
                                                <?php echo date('M d, Y H:i', strtotime($application['created_at'])); ?></p>
                                        </div>
                                    </div>

                                    <?php if ($application['status'] == 'approved'): ?>
                                        <div class="roommates-section mt-4">
                                            <div class="card">
                                                <div class="card-header">
                                                    <h5 class="mb-0"><i class="bi bi-people me-2"></i>Your Roommates</h5>
                                                </div>
                                                <div class="card-body">
                                                    <?php
                                                    $roommates_query = "SELECT s.*, a.status 
                                                                    FROM applications a
                                                                    JOIN info s ON a.regnumber = s.regnumber
                                                                    WHERE a.room_id = ? AND a.regnumber != ? AND a.status = 'approved'";
                                                    $roommates_stmt = $connection->prepare($roommates_query);
                                                    $roommates_stmt->bind_param("is", $application['room_id'], $regnumber);
                                                    $roommates_stmt->execute();
                                                    $roommates = $roommates_stmt->get_result();

                                                    if ($roommates->num_rows > 0):
                                                        while ($roommate = $roommates->fetch_assoc()):
                                                            ?>
                                                            <div class="roommate-card mb-3 p-3 border rounded">
                                                                <div class="row align-items-center">
                                                                    <div class="col-md-8">
                                                                        <h6 class="mb-1">
                                                                            <i class="bi bi-person me-2"></i>
                                                                            <?php echo htmlspecialchars($roommate['names']); ?>
                                                                        </h6>
                                                                        <p class="text-muted mb-0">
                                                                            <i class="bi bi-card-text me-2"></i>
                                                                            <?php echo htmlspecialchars($roommate['regnumber']); ?> |
                                                                            <i class="bi bi-building me-2"></i>
                                                                            <?php echo htmlspecialchars($roommate['college']); ?> |
                                                                            <i class="bi bi-mortarboard me-2"></i>
                                                                            Year
                                                                            <?php echo htmlspecialchars($roommate['yearofstudy']); ?>
                                                                        </p>
                                                                    </div>
                                                                    <div class="col-md-4 text-end">
                                                                        <!-- description -->

                                                                        <span class="badge bg-success">
                                                                            Approved
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <?php
                                                        endwhile;
                                                    else:
                                                        ?>
                                                        <div class="text-center py-4">
                                                            <i class="bi bi-people text-muted" style="font-size: 2rem;"></i>
                                                            <p class="text-muted mt-2 mb-0">No roommates assigned yet.</p>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($application['status'] == 'pending'): ?>
                                        <div class="container my-5">
                                            <div class="row justify-content-center">
                                                <!-- Main Countdown Card -->
                                                <div class="col-lg-12">
                                                    <div class="card    border-0 rounded-4 overflow-hidden">
                                                        <div class="card-body p-5">
                                                            <!-- Clock Design -->
                                                            <div class="text-center mb-4">
                                                                <div class="clock-container position-relative d-inline-block">
                                                                    <div class="clock-face">
                                                                        <div class="clock-center"></div>
                                                                        <div class="clock-hand hour-hand"></div>
                                                                        <div class="clock-hand minute-hand"></div>
                                                                        <div class="clock-hand second-hand"></div>
                                                                        <div class="clock-marks">
                                                                            <?php for($i = 1; $i <= 12; $i++): ?>
                                                                                <div class="mark mark-<?php echo $i; ?>"></div>
                                                                            <?php endfor; ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- Digital Display -->
                                                            <div class="text-center mb-4">
                                                                <h3 class="text-muted mb-2">Time Remaining</h3>
                                                                <div id="countdown-timer" class="display-3 fw-bold text-primary mb-2"></div>
                                                                <div id="readable-timer" class="h4 text-muted"></div>
                                                            </div>

                                                            <!-- Progress Bar -->
                                                            <div class="progress-container mb-4">
                                                                <div class="progress" style="height: 8px;">
                                                                    <div id="time-progress" class="progress-bar bg-danger" role="progressbar" style="width: 100%"></div>
                                                                </div>
                                                            </div>

                                                            <!-- Warning Message -->
                                                            <div class="alert alert-warning border-0 rounded-3">
                                                                <div class="d-flex align-items-center">
                                                                    <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                                                                    <div>
                                                                        <h5 class="mb-2">Important Notice</h5>
                                                                        <p class="mb-1">Your application will be automatically deleted if you don't upload a valid payment receipt within 48 hours.</p>
                                                                        <p class="mb-0">Uploading fake or invalid receipts will result in immediate application deletion.</p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <script>
                                            function updateClock() {
                                                const now = new Date();
                                                const hours = now.getHours();
                                                const minutes = now.getMinutes();
                                                const seconds = now.getSeconds();

                                                const hourDeg = (hours % 12) * 30 + minutes * 0.5;
                                                const minuteDeg = minutes * 6;
                                                const secondDeg = seconds * 6;

                                                document.querySelector('.hour-hand').style.transform = `rotate(${hourDeg}deg)`;
                                                document.querySelector('.minute-hand').style.transform = `rotate(${minuteDeg}deg)`;
                                                document.querySelector('.second-hand').style.transform = `rotate(${secondDeg}deg)`;
                                            }

                                            // Update clock every second
                                            setInterval(updateClock, 1000);
                                            updateClock(); // Initial call

                                            // Update progress bar
                                            function updateProgress() {
                                                const createdAt = new Date('<?php echo $application['created_at']; ?>');
                                                const endTime = new Date(createdAt.getTime() + (48 * 60 * 60 * 1000));
                                                const now = new Date();
                                                const total = endTime - createdAt;
                                                const elapsed = now - createdAt;
                                                const progress = Math.max(0, Math.min(100, (elapsed / total) * 100));
                                                
                                                document.getElementById('time-progress').style.width = `${100 - progress}%`;
                                            }

                                            setInterval(updateProgress, 1000);
                                            updateProgress(); // Initial call
                                        </script>
                                    <?php endif; ?>

                                    <!-- if it payed -->
                                    <?php if ($application['status'] == 'paid'): ?>
                                        <div class="alert alert-success mt-4">
                                            <h5><i class="bi bi-check-circle-fill"></i> Application is in under review!</h5>
                                            <p>your receipt has been uploaded successfully, please wait for the review, we will
                                                notify you once it's approved thruth your phone number sms and here in the
                                                application status page</p>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($application['status'] == 'rejected'): ?>
                                        <div class="alert alert-danger mt-4">
                                            <h5><i class="bi bi-x-circle-fill"></i> Application Rejected</h5>
                                            <p>Your application has been rejected. You can submit a new application.</p>
                                            <a href="index.php" class="btn btn-primary mt-3">Submit New Application</a>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Vendor JS Files -->
    <script src="../Dashboard/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Show skeleton loading initially
            document.querySelector('.skeleton-loading').style.display = 'block';
            document.querySelector('.content-loading').style.display = 'none';

            // Simulate loading time (remove this in production and use actual loading events)
            setTimeout(() => {
                document.querySelector('.skeleton-loading').style.display = 'none';
                document.querySelector('.content-loading').style.display = 'block';
            }, 1500);
        });
    </script>

    <?php if ($application && $application['status'] == 'pending'): ?>
        <script>
            // Set the countdown timer
            function updateCountdown() {
                // Get the creation time from PHP
                const createdAt = new Date('<?php echo $application['created_at']; ?>');
                const endTime = new Date(createdAt.getTime() + (48 * 60 * 60 * 1000)); // 48 hours from creation

                function update() {
                    const currentTime = new Date();
                    const timeLeft = endTime - currentTime;

                    if (timeLeft <= 0) {
                        document.getElementById('countdown-timer').innerHTML = "00:00:00";
                        document.getElementById('readable-timer').innerHTML = "Time's up!";
                        return;
                    }

                    const hours = Math.floor(timeLeft / (1000 * 60 * 60));
                    const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);

                    // Digital format
                    document.getElementById('countdown-timer').innerHTML =
                        `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

                    // Readable format (without seconds)
                    let timeString = '';
                    if (hours > 0) {
                        timeString += hours + ' hour' + (hours !== 1 ? 's' : '');
                    }
                    if (minutes > 0) {
                        if (timeString) timeString += ' and ';
                        timeString += minutes + ' minute' + (minutes !== 1 ? 's' : '');
                    }

                    document.getElementById('readable-timer').innerHTML =
                        `You have ${timeString} remaining`;
                }

                update();
                setInterval(update, 1000);
            }

            updateCountdown();
        </script>
    <?php endif; ?>
</body>

</html>