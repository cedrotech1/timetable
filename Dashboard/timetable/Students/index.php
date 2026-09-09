<?php
session_start();
require_once 'connection.php';

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: logout.php");
    exit();
}

$student_regnumber = $_SESSION['student_regnumber'];
$student_campus = $_SESSION['student_campus'];
$student_gender = $_SESSION['student_gender'];
$student_year = $_SESSION['student_year'];
$student_college = $_SESSION['student_college'];
$student_school = $_SESSION['student_school'];

// Check if student has an active application
$application_query = "SELECT a.*, r.room_code, h.name as hostel_name 
                     FROM applications a
                     JOIN rooms r ON a.room_id = r.id
                     JOIN hostels h ON r.hostel_id = h.id
                     WHERE a.regnumber = ? AND a.status != 'rejected'";
$application_stmt = $connection->prepare($application_query);
$application_stmt->bind_param("s", $student_regnumber);
$application_stmt->execute();
$current_application = $application_stmt->get_result()->fetch_assoc();

// If student has an active application, show only their application details
if ($current_application) {
    include 'hostel_includes/student_info.php';
    include 'hostel_includes/current_application.php';
    exit();
}

// If no active application, show hostel selection
$hostels_query = "SELECT h.* FROM hostels h 
                JOIN campuses c ON h.campus_id = c.id 
                WHERE c.name = ?";
$hostels_stmt = $connection->prepare($hostels_query);
$hostels_stmt->bind_param("s", $student_campus);
$hostels_stmt->execute();
$hostels = $hostels_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Function to check hostel eligibility
function checkHostelEligibility($connection, $hostel_id, $student_gender, $student_year, $student_college, $student_school)
{
    $attributes_query = "SELECT attribute_key, attribute_value FROM hostel_attributes WHERE hostel_id = ?";
    $attributes_stmt = $connection->prepare($attributes_query);
    $attributes_stmt->bind_param("i", $hostel_id);
    $attributes_stmt->execute();
    $attributes_result = $attributes_stmt->get_result();

    $attributes = [];
    while ($attr = $attributes_result->fetch_assoc()) {
        $attributes[$attr['attribute_key']] = $attr['attribute_value'];
    }

    // Check gender eligibility
    if (isset($attributes['gender']) && $attributes['gender'] !== $student_gender) {
        return false;
    }

    // Check year eligibility
    if (isset($attributes['year_of_study']) && $attributes['year_of_study'] != $student_year) {
        return false;
    }

    // Check college eligibility
    if (isset($attributes['college']) && $attributes['college'] !== $student_college) {
        return false;
    }

    // Check school eligibility
    if (isset($attributes['school']) && $attributes['school'] !== $student_school) {
        return false;
    }

    return true;
}

// Filter eligible hostels
$eligible_hostels = array_filter($hostels, function ($hostel) use ($connection, $student_gender, $student_year, $student_college, $student_school) {
    return checkHostelEligibility($connection, $hostel['id'], $student_gender, $student_year, $student_college, $student_school);
});
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UR-HOSTELS</title>
    <link href="../icon1.png" rel="icon">
    <link href="../icon1.png" rel="apple-touch-icon">
    <link href="https://fonts.gstatic.com" rel="preconnect">
    <link
        href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i"
        rel="stylesheet">
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">


    <style>
        .hostel-card {
            transition: transform 0.2s;
            margin-bottom: 20px;
            height: 100%;
            border: none;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .hostel-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .room-list {
            max-height: 400px;
            overflow-y: auto;
            padding: 10px;
        }

        .application-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #006991;
        }

        .roommate-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .receipt-upload {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin-top: 20px;
        }

        .receipt-upload:hover {
            border-color: #006991;
        }
        

        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: 500;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background-color: #d1e7dd;
            color: #0f5132;
        }

        .student-info-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .student-info-card h4 {
            color: #006991;
            margin-bottom: 15px;
        }

        .student-info-item {
            margin-bottom: 10px;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        /* Add skeleton loading styles */
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
            border-radius: 4px;
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
    </style>
</head>

<body>
    <div class="container mt-4">
        <!-- Skeleton Loading Structure -->
        <div class="skeleton-loading">
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="skeleton skeleton-title w-75 mb-4"></div>
                            <div class="skeleton skeleton-text w-50 mb-3"></div>
                            
                            <!-- Student Info Skeleton -->
                            <div class="student-info-card mb-4">
                                <div class="skeleton skeleton-title w-50 mb-3"></div>
                                <div class="skeleton skeleton-text w-75 mb-2"></div>
                                <div class="skeleton skeleton-text w-75 mb-2"></div>
                                <div class="skeleton skeleton-text w-75 mb-2"></div>
                            </div>
                                

                            <!-- Hostel Cards Skeleton -->
                            <div class="row">
                                <?php for($i = 0; $i < 3; $i++): ?>
                                <div class="col-md-4">
                                    <div class="card hostel-card">
                                        <div class="card-body">
                                            <div class="skeleton skeleton-title w-75 mb-3"></div>
                                            <div class="skeleton skeleton-text w-100 mb-2"></div>
                                            <div class="skeleton skeleton-text w-100 mb-2"></div>
                                            <div class="skeleton skeleton-text w-50 mb-3"></div>
                                            <div class="skeleton skeleton-text w-25"></div>
                                        </div>
                                    </div>
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actual Content -->
        <div class="content-loading">
            <div class="row">
                <div class="col-md-12">
                    <div class="card" style="border: 0px solid #000;">
                        <div class="card-body">
                            <h2 class="card-title">Select a Hostel</h2>
                            <p class="text-muted">Click on a hostel card to view available rooms</p>

                            <?php include 'hostel_includes/student_info.php'; ?>
                            <?php
                            // Pass only eligible hostels to the hostel map component
                            $hostels = $eligible_hostels;
                            include 'hostel_includes/hostel_map.php';
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rooms Modal -->
    <div class="modal fade" id="roomsModal" tabindex="-1" aria-labelledby="roomsModalLabel">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="roomsModalLabel">Available Rooms</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="rooms-container"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
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

            const roomsModal = document.getElementById('roomsModal');
            const modal = new bootstrap.Modal(roomsModal);

            // Store the element that had focus before the modal opened
            let previousActiveElement;

            // When modal is about to be shown
            roomsModal.addEventListener('show.bs.modal', function () {
                previousActiveElement = document.activeElement;
            });

            // When modal is hidden
            roomsModal.addEventListener('hidden.bs.modal', function () {
                if (previousActiveElement) {
                    previousActiveElement.focus();
                }
            });

            // Handle room view buttons
            document.querySelectorAll('.view-rooms').forEach(button => {
                button.addEventListener('click', function () {
                    const hostelId = this.dataset.hostelId;
                    const hostelName = this.dataset.hostelName || 'Hostel';

                    // Update modal title
                    const modalTitle = document.querySelector('#roomsModal .modal-title');
                    if (modalTitle) {
                        modalTitle.textContent = `Available Rooms - ${hostelName}`;
                    }

                    // Show loading state
                    const roomsContainer = document.getElementById('rooms-container');
                    if (roomsContainer) {
                        roomsContainer.innerHTML = `
                            <div class="text-center">
                                <div class="skeleton skeleton-text w-75 mx-auto mb-3"></div>
                                <div class="skeleton skeleton-text w-50 mx-auto mb-3"></div>
                                <div class="skeleton skeleton-text w-25 mx-auto"></div>
                            </div>`;
                    }

                    // Load rooms
                    loadRooms(hostelId, 1);

                    // Show modal
                    modal.show();
                });
            });
        });
    </script>
</body>

</html>