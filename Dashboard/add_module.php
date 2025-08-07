<?php
session_start();

include('connection.php');

// Get programs for selection
$programs_query = "SELECT p.*, d.name as department_name 
                  FROM program p 
                  JOIN department d ON p.department_id = d.id 
                  ORDER BY p.name";
$programs_result = mysqli_query($connection, $programs_query);
$programs = [];
while ($program = mysqli_fetch_assoc($programs_result)) {
    $programs[] = $program;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => '', 'data' => null];
    
    try {
        // Validate required fields
        $required_fields = ['name', 'code', 'program_id', 'credits', 'year', 'semester'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                throw new Exception("$field is required");
            }
        }

        $name = mysqli_real_escape_string($connection, $_POST['name']);
        $code = mysqli_real_escape_string($connection, $_POST['code']);
        $program_id = intval($_POST['program_id']);
        $credits = intval($_POST['credits']);
        $year = intval($_POST['year']);
        $semester = intval($_POST['semester']);

        // Validate credits
        if ($credits <= 0 || $credits > 30) {
            throw new Exception("Credits must be between 1 and 30");
        }

        // Validate year
        if ($year < 1 || $year > 4) {
            throw new Exception("Year must be between 1 and 4");
        }

        // Validate semester
        if ($semester < 1 || $semester > 2) {
            throw new Exception("Semester must be 1 or 2");
        }

        // Check if module already exists for this program, year, and semester
        $check_sql = "SELECT id FROM module WHERE code = '$code' AND program_id = $program_id AND year = $year AND semester = $semester";
        $check_result = mysqli_query($connection, $check_sql);

        if (mysqli_num_rows($check_result) > 0) {
            throw new Exception("Module with code '$code' already exists in this program for year $year semester $semester");
        }

        // Insert new module
        $sql = "INSERT INTO module (name, code, credits, program_id, year, semester) VALUES ('$name', '$code', $credits, $program_id, $year, $semester)";
        
        if (mysqli_query($connection, $sql)) {
            $response['success'] = true;
            $response['message'] = "Module added successfully";
        } else {
            throw new Exception("Error inserting module: " . mysqli_error($connection));
        }

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }

    // Send JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    <title>Add Module - UR-TIMETABLE</title>
    <link href="assets/img/icon1.png" rel="icon">
    <link href="assets/img/icon1.png" rel="apple-touch-icon">
    
    <!-- Include your existing CSS files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>
    <?php
    include("./includes/header.php");
    include("./includes/menu.php");
    ?>

    <main id="main" class="main">
        <div class="pagetitle">
            <h1>Add Module</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item">Modules</li>
                    <li class="breadcrumb-item active">Add Module</li>
                </ol>
            </nav>
        </div>

        <section class="section">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Add New Module</h5>
                            
                            <form id="moduleForm" class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Program</label>
                                    <select class="form-select" name="program_id" required>
                                        <option value="">Select Program</option>
                                        <?php foreach ($programs as $program): ?>
                                        <option value="<?php echo $program['id']; ?>">
                                            <?php echo htmlspecialchars($program['name'] . ' (' . $program['department_name'] . ')'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Module Name</label>
                                    <input type="text" class="form-control" name="name" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Module Code</label>
                                    <input type="text" class="form-control" name="code" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Credits</label>
                                    <input type="number" class="form-control" name="credits" min="1" max="30" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Year</label>
                                    <select class="form-select" name="year" required>
                                        <option value="">Select Year</option>
                                        <option value="1">Year 1</option>
                                        <option value="2">Year 2</option>
                                        <option value="3">Year 3</option>
                                        <option value="4">Year 4</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Semester</label>
                                    <select class="form-select" name="semester" required>
                                        <option value="">Select Semester</option>
                                        <option value="1">Semester 1</option>
                                        <option value="2">Semester 2</option>
                                    </select>
                                </div>

                                <div class="text-center">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-plus-circle me-1"></i>Add Module
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Include your existing JS files -->
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>

    <script>
    document.getElementById('moduleForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const form = this;
        const submitButton = form.querySelector('button[type="submit"]');
        const originalText = submitButton.innerHTML;

        try {
            submitButton.disabled = true;
            submitButton.innerHTML = `
                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                Adding...
            `;

            const formData = new FormData(form);
            const response = await fetch('add_module.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                // Show success message
                alert(result.message);
                // Reset form
                form.reset();
            } else {
                // Show error message
                alert(result.message || 'Failed to add module');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred while adding the module');
        } finally {
            submitButton.disabled = false;
            submitButton.innerHTML = originalText;
        }
    });
    </script>
</body>
</html> 