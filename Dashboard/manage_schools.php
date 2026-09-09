<?php
// Include database connection
include 'connection.php';

// Initialize variables
$uploadMessage = '';
$deleteMessage = '';
$addMessage = '';
$schools = [];

// Handle adding a single school
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_school'])) {
    $schoolName = isset($_POST['school_name']) ? trim($_POST['school_name']) : '';

    if ($schoolName === '') {
        $addMessage = 'Error: School name is required.';
    } else {
        $nameLower = strtolower($schoolName);
        $escapedName = mysqli_real_escape_string($connection, $nameLower);

        $checkQuery = "SELECT id FROM all_schools WHERE LOWER(name) = '$escapedName'";
        $checkResult = mysqli_query($connection, $checkQuery);

        if ($checkResult && mysqli_num_rows($checkResult) > 0) {
            $addMessage = 'Error: That school already exists.';
        } else {
            $insertQuery = "INSERT INTO all_schools (name) VALUES ('$escapedName')";
            if (mysqli_query($connection, $insertQuery)) {
                $addMessage = 'School added successfully.';
            } else {
                $addMessage = 'Error: Failed to add school.';
            }
        }

        if ($checkResult) {
            mysqli_free_result($checkResult);
        }
    }
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csvFile'])) {
    $file = $_FILES['csvFile'];
    
    // Validate file
    if ($file['error'] === UPLOAD_ERR_OK && $file['type'] === 'text/csv') {
        $filePath = $file['tmp_name'];
        
        // Open and read CSV file
        if (($handle = fopen($filePath, 'r')) !== false) {
            // Skip header row
            fgetcsv($handle);
            
            $names = [];
            $hasDuplicate = false;
            
            // Read all names and check for duplicates
            while (($data = fgetcsv($handle)) !== false) {
                if (count($data) >= 2 && !empty($data[1])) {
                    $name = strtolower(trim($data[1]));
                    $names[] = $name;
                    
                    // Check if name already exists (case-insensitive)
                    $checkQuery = "SELECT id FROM all_schools WHERE LOWER(name) = '" . mysqli_real_escape_string($connection, $name) . "'";
                    $checkResult = mysqli_query($connection, $checkQuery);
                    if (mysqli_num_rows($checkResult) > 0) {
                        $hasDuplicate = true;
                    }
                    mysqli_free_result($checkResult);
                }
            }
            
            // If no duplicates, proceed with insertion
            if (!$hasDuplicate) {
                $successCount = 0;
                $errorCount = 0;
                
                // Rewind file to process again for insertion
                rewind($handle);
                fgetcsv($handle); // Skip header again
                
                while (($data = fgetcsv($handle)) !== false) {
                    if (count($data) >= 2 && !empty($data[1])) {
                        $name = mysqli_real_escape_string($connection, strtolower(trim($data[1])));
                        
                        // Insert only name into database (id is auto-incremented)
                        $query = "INSERT INTO all_schools (name) VALUES ('$name')";
                        if (mysqli_query($connection, $query)) {
                            $successCount++;
                        } else {
                            $errorCount++;
                        }
                    } else {
                        $errorCount++;
                    }
                }
                $uploadMessage = "Uploaded: $successCount records inserted successfully. $errorCount records failed.";
            } else {
                $uploadMessage = "Error: One or more school names already exist in the database. No records were inserted.";
            }
            
            fclose($handle);
        } else {
            $uploadMessage = "Error: Unable to open the CSV file.";
        }
    } else {
        $uploadMessage = "Error: Please upload a valid CSV file.";
    }
}

// Handle delete single school
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = mysqli_real_escape_string($connection, $_POST['delete_id']);
    $deleteQuery = "DELETE FROM all_schools WHERE id = '$deleteId'";
    if (mysqli_query($connection, $deleteQuery)) {
        $deleteMessage = "School with ID $deleteId deleted successfully.";
    } else {
        $deleteMessage = "Error: Failed to delete school with ID $deleteId.";
    }
}

// Handle delete all schools
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_all'])) {
    $deleteAllQuery = "DELETE FROM all_schools";
    if (mysqli_query($connection, $deleteAllQuery)) {
        $deleteMessage = "All schools deleted successfully.";
    } else {
        $deleteMessage = "Error: Failed to delete all schools.";
    }
}

// Fetch all schools from database
$query = "SELECT id, name FROM all_schools";
$result = mysqli_query($connection, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $schools[] = $row;
    }
    mysqli_free_result($result);
}

// Close connection
mysqli_close($connection);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Management</title>
    <!-- Bootstrap CSS via CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>
<body style="background-color: #f4f6f8;">
    <div class="mt-5">
        <h1 class="mb-4">School Management</h1>
        
        <!-- Add School Form -->
        <div class="card mb-4">
            <div class="card-header">Add Single School</div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="add_school" value="1">
                    <div class="mb-3">
                        <label for="school_name" class="form-label">School Name</label>
                        <input type="text" class="form-control" id="school_name" name="school_name" placeholder="Enter school name" required>
                        <small class="form-text text-muted">Names are stored in lowercase to avoid duplicates.</small>
                    </div>
                    <button type="submit" class="btn btn-success">Add School</button>
                </form>
                <?php if ($addMessage): ?>
                    <div class="alert <?php echo strpos($addMessage, 'Error') === false ? 'alert-success' : 'alert-danger'; ?> mt-3">
                        <?php echo htmlspecialchars($addMessage); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Upload Form -->
        <div class="card mb-4">
            <div class="card-header">Upload Schools (CSV)</div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="csvFile" class="form-label">Select CSV File</label>
                        <input type="file" class="form-control" id="csvFile" name="csvFile" accept=".csv" required>
                        <small class="form-text text-muted">CSV format: id,name (e.g., "1,School Name"). Only name is used, stored in lowercase. All names must be unique.</small>
                    </div>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </form>
                <?php if ($uploadMessage): ?>
                    <div class="alert <?php echo strpos($uploadMessage, 'Error') === false ? 'alert-success' : 'alert-danger'; ?> mt-3">
                        <?php echo htmlspecialchars($uploadMessage); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Delete All Schools -->
        <div class="card mb-4">
            <div class="card-header">Delete All Schools</div>
            <div class="card-body">
                <form method="post" onsubmit="return confirm('Are you sure you want to delete ALL schools? This cannot be undone.');">
                    <input type="hidden" name="delete_all" value="1">
                    <button type="submit" class="btn btn-danger">Delete All Schools</button>
                </form>
            </div>
        </div>

        <!-- Schools Table -->
        <div class="card">
            <div class="card-header">All Schools</div>
            <div class="card-body">
                <?php if ($deleteMessage): ?>
                    <div class="alert <?php echo strpos($deleteMessage, 'Error') === false ? 'alert-success' : 'alert-danger'; ?> mb-3">
                        <?php echo htmlspecialchars($deleteMessage); ?>
                    </div>
                <?php endif; ?>
                <?php if (empty($schools)): ?>
                    <p>No schools found.</p>
                <?php else: ?>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($schools as $school): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($school['id']); ?></td>
                                    <td><?php echo htmlspecialchars($school['name']); ?></td>
                                    <td>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete <?php echo htmlspecialchars($school['name']); ?>?');">
                                            <input type="hidden" name="delete_id" value="<?php echo htmlspecialchars($school['id']); ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>