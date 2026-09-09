<?php
session_start();
include("connection.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    <title>UR-TIMETABLE</title>
    <link href="assets/img/icon1.png" rel="icon">
    <link href="assets/img/icon1.png" rel="apple-touch-icon">
    
    <!-- Include your existing CSS files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <!-- Add Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
   
</head>

<body>
    <?php
    include("./includes/header.php");
    include("./includes/menu.php");
    ?>

    <main id="main" class="main">
        <div class="pagetitle">
            <h1>Add New User</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="add_user.php">Users</a></li>
                    <li class="breadcrumb-item active">Add New User</li>
                </ol>
            </nav>
        </div>

        <section class="section">
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">User Information</h5>
                            
                            <?php
                            // Handle form submission
                            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                                $names = $_POST['names'] ?? '';
                                $email = $_POST['email'] ?? '';
                                $ur_email = $_POST['ur_email'] ?? '';
                                $phone = $_POST['phone'] ?? '';
                                $staff_number = $_POST['staff_number'] ?? '';
                                $role = $_POST['role'] ?? '';
                                $campus = !empty($_POST['campus']) ? (int)$_POST['campus'] : null;
                                $college = !empty($_POST['college']) ? (int)$_POST['college'] : null;
                                $school = !empty($_POST['school']) ? (int)$_POST['school'] : null;
                                $default_password = '23122312';
                                $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);
                                $is_active = 1;

                                // Insert user into database
                                $stmt = $connection->prepare("INSERT INTO users (names, email, ur_email, password, active) 
                                    VALUES (?, ?, ?, ?, ?)");
                                
                                $stmt->bind_param("ssssi", $names, $email, $ur_email, $hashed_password, $is_active);
                                
                                if ($stmt->execute()) {
                                    echo '<div class="alert alert-success">User added successfully! Default password is: ' . htmlspecialchars($default_password) . '</div>';
                                } else {
                                    echo '<div class="alert alert-danger">Error adding user: ' . $connection->error . '</div>';
                                }
                                $stmt->close();
                            }
                            ?>

                            <form method="POST" class="row g-3">
                                <div class="col-md-12">
                                    <label for="names" class="form-label">Full Names *</label>
                                    <input type="text" class="form-control" id="names" name="names" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Personal Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="ur_email" class="form-label">UR Email</label>
                                    <input type="email" class="form-control" id="ur_email" name="ur_email">
                                </div>
                                
                                <!-- Hidden fields with default values -->
                                <input type="hidden" name="staff_number" value="">
                                <input type="hidden" name="phone" value="">
                                <input type="hidden" name="role" value="user">
                                <!-- Campus Selection -->
                                <div class="col-md-6" id="campusField">
                                    <label for="campus" class="form-label">Campus</label>
                                    <select class="form-select" id="campus" name="campus" required>
                                        <option value="">Select Campus</option>
                                        <?php
                                        $campuses = $connection->query("SELECT * FROM campus ORDER BY name");
                                        while($campus = $campuses->fetch_assoc()):
                                        ?>
                                        <option value="<?php echo $campus['id']; ?>"><?php echo htmlspecialchars($campus['name']); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <!-- College Selection -->
                                <div class="col-md-6" id="collegeField">
                                    <label for="college" class="form-label">College</label>
                                    <select class="form-select" id="college" name="college" required>
                                        <option value="">Select College</option>
                                    </select>
                                </div>
                                
                                <!-- School Selection -->
                                <div class="col-md-12" id="schoolField">
                                    <label for="school" class="form-label">School</label>
                                    <select class="form-select" id="school" name="school" required>
                                        <option value="">Select School</option>
                                    </select>
                                </div>
                                <input type="hidden" name="is_active" value="1">
                                
                                <div class="text-center">
                                    <button type="submit" class="btn btn-primary">Add User</button>
                                    <button type="reset" class="btn btn-secondary">Reset</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
    
    <script>
    // Function to update form fields based on role selection
    function updateRoleFields() {
        const role = document.getElementById('role').value;
        const campusField = document.getElementById('campusField');
        const collegeField = document.getElementById('collegeField');
        const schoolField = document.getElementById('schoolField');
        
        // For admin, hide campus, college, and school fields
        if (role === 'admin') {
            campusField.style.display = 'none';
            collegeField.style.display = 'none';
            schoolField.style.display = 'none';
        } else {
            campusField.style.display = 'block';
            collegeField.style.display = 'block';
            schoolField.style.display = 'block';
        }
    }
    
    // Load colleges when campus is selected
    document.getElementById('campus').addEventListener('change', function() {
        const campusId = this.value;
        const collegeSelect = document.getElementById('college');
        const schoolSelect = document.getElementById('school');
        
        // Clear previous options
        collegeSelect.innerHTML = '<option value="">Select College</option>';
        schoolSelect.innerHTML = '<option value="">Select School</option>';
        
        if (campusId) {
            // Show loading state
            collegeSelect.disabled = true;
            
            // Fetch colleges for the selected campus
            fetch('get_colleges.php?campus_id=' + campusId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    data.forEach(college => {
                        const option = document.createElement('option');
                        option.value = college.id;
                        option.textContent = college.name;
                        collegeSelect.appendChild(option);
                    });
                    collegeSelect.disabled = false;
                })
                .catch(error => {
                    console.error('Error loading colleges:', error);
                    alert('Error loading colleges. Please try again.');
                    collegeSelect.disabled = false;
                });
        } else {
            collegeSelect.disabled = false;
        }
    });
    
    // Load schools when college is selected
    document.getElementById('college').addEventListener('change', function() {
        const collegeId = this.value;
        const schoolSelect = document.getElementById('school');
        
        // Clear previous options
        schoolSelect.innerHTML = '<option value="">Select School</option>';
        
        if (collegeId) {
            // Show loading state
            schoolSelect.disabled = true;
            
            // Fetch schools for the selected college
            fetch('get_schools.php?college_id=' + collegeId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data && data.length > 0) {
                        data.forEach(school => {
                            const option = document.createElement('option');
                            option.value = school.id;
                            option.textContent = school.name;
                            schoolSelect.appendChild(option);
                        });
                    } else {
                        const option = document.createElement('option');
                        option.value = '';
                        option.textContent = 'No schools found for this college';
                        schoolSelect.appendChild(option);
                    }
                    schoolSelect.disabled = false;
                })
                .catch(error => {
                    console.error('Error loading schools:', error);
                    const option = document.createElement('option');
                    option.value = '';
                    option.textContent = 'Error loading schools';
                    schoolSelect.appendChild(option);
                    schoolSelect.disabled = false;
                });
        } else {
            schoolSelect.disabled = false;
        }
    });
    
    // Initialize the form fields based on default role
    document.addEventListener('DOMContentLoaded', function() {
        updateRoleFields();
    });
    </script>

    <!-- Include your existing JS files -->
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>

  
</body>
</html> 