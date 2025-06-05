<?php
// Database connectionection
include("connection.php");
$query = "SELECT * FROM system";
$result = mysqli_query($connection, $query);
$row = mysqli_fetch_assoc($result);
$status = $row['status'] ?? null;
$allow_message = $row['allow_message'] ?? null;

// if ($allow_message != "allow") {
//     header("Location: index.php");
//     exit(); // Stop further script execution
// }

if ($status != "live") {
    header("Location: status.php");
    exit(); // Stop further script execution
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])) {
    $regNumber = $_POST['regnumber'];
    $names = $_POST['names'];
    $phone = $_POST['phone'];
    $message = $_POST['message'];

    if ($allow_message != "allow") {
        $errorMessage = "Messages is closed at moment";
    }else{
        $sql = "INSERT INTO messages (reg_number, names, phone, message) VALUES (?, ?, ?, ?)";
        $stmt = $connection->prepare($sql);
        $stmt->bind_param("ssss", $regNumber, $names, $phone, $message);
    
        if ($stmt->execute()) {
            $successMessage = "Message sent successfully!";
        } else {
            $errorMessage = "Error: " . $connection->error;
        }

    }
    // Insert message into the database
 
}

// Retrieve messages
$sql = "SELECT * FROM messages ORDER BY created_at DESC";
$result = $connection->query($sql);
$messages = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>UR-HUYE</title>
    <link href="./icon1.png" rel="icon">
    <link href="https://fonts.gstatic.com" rel="preconnect">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans|Nunito|Poppins" rel="stylesheet">
    <link href="./Dashboard/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="./Dashboard/assets/css/style.css" rel="stylesheet">
    <style>
        .logo1 {
            width: 70%;
            height: auto;
            margin-bottom: 10px;
        }

        body {
            font-family: Arial, sans-serif;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            padding: 20px;
            border-radius: 8px;
            width: 300px;
            text-align: center;
        }

        .close-btn {
            float: right;
            cursor: pointer;
        }

        .result {
            margin-top: 10px;
            font-size: 14px;
            color: green;
        }
    </style>
</head>

<body>
  

    <main>
        <div class="container">
            <section class="section register min-vh-100 d-flex flex-column align-items-center justify-content-center py-4">
                <div class="container">
                    <div class="row justify-content-center">
                        <div class="col-lg-4 col-md-6 d-flex flex-column align-items-center justify-content-center">
                            <div class="card mb-3">
                                <div class="card-body">
                                    <div class="pt-4 pb-2">
                                        <div class="row">
                                            <img class="logo1" src="./assets/img/ur.png" alt="">
                                        </div>
                                        <h5 class="card-title text-center pb-0 fs-4">Message us</h5>

                                                                <?php if (isset($successMessage)) { ?>
                                <div class="alert alert-success"><?php echo $successMessage; ?></div>
                            <?php } elseif (isset($errorMessage)) { ?>
                                <div class="alert alert-danger"><?php echo $errorMessage; ?></div>
                            <?php } ?>
                                    </div>
                                    <form class="row g-3 needs-validation" method="post" action="">
                                        <div class="col-12">
                                            <label for="yourRegNumber" class="form-label">Registration Number</label>
                                            <input type="text" name="regnumber" class="form-control" required>
                                        </div>
                                        <div class="col-12">
                                            <label for="names" class="form-label">Names</label>
                                            <input type="text" name="names" class="form-control" required>
                                        </div>
                                        <div class="col-12">
                                            <label for="phone" class="form-label">Phone Number</label>
                                            <input type="text" name="phone" class="form-control" required>
                                        </div>
                                        <div class="col-12">
                                            <label for="message" class="form-label">Message</label>
                                            <textarea class="form-control" name="message" required></textarea>
                                        </div>
                                        <div class="col-12">
                                            
                                                 <button class="btn btn-primary w-100" name="submit" type="submit">Send Message</button>

                                          
                                           
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            
            </section>
        </div>
    </main>
</body>

</html>
