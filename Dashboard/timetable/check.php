<?php
include('connection.php');

// Initialize variables
$regnumber = $fullnames = $studentemail = $campus = $college = $school = $program = "";
$nid = $phone = "";
$message = $messageType = "";
$isViewing = false; // Flag to check if data is being viewed

// Fetch student data
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search'])) {
    $regnumber = $_POST['regnumber'];

    // Fetch the student data from the `info` table
    $sql = "SELECT * FROM info WHERE regnumber = ?";
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("s", $regnumber);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Fetch the row data
        $row = $result->fetch_assoc();
        $fullnames = $row['names'];
        $studentemail = $row['email'];
        $campus = $row['campus'];
        $college = $row['college'];
        $school = $row['school'];
        $program = $row['program'];
        $picture = $row['picture'];
        $status = $row['status'];
        $isViewing = true; 

        // Check for corresponding entry in the `student_ids` table
        $sql1 = "SELECT * FROM student_ids WHERE regnumber = ?";
        $stmt1 = $connection->prepare($sql1);
        $stmt1->bind_param("s", $regnumber);
        $stmt1->execute();
        $result1 = $stmt1->get_result();

        if ($result1->num_rows > 0) {
            // Student exists in both tables
            $row1 = $result1->fetch_assoc();
            $nid = $row1['nid'];
            $phone1 = $row1['phone'];
        } else {
            // Student exists only in the `info` table
            $nid = ""; // Set empty value for National ID
            $phone1 = ""; // Set empty value for Phone
            $message = "Student found, but no ID or phone information available.";
            $messageType = "warning";
        }
    } else {
        // No student found in the `info` table
        $message = "No student found with this registration number.";
        $messageType = "danger";
        $isViewing = false;
    }
}



?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>UR-HUYE-CARDS</title>
    <link href="./icon1.png" rel="icon">
    <link href="./icon1.png" rel="apple-touch-icon">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>


    
    <main id="main" class="main">
        <section class="section dashboard" style="padding:0.5cm">
            
            <div class="row">

                <!-- Display message card if there's a message -->
                <?php if ($message): ?>
                    <div class="col-lg-12">
                        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- First Form: Input Regnumber to fetch student data -->
                <div class="col-lg-6">
                <div class="row" style="padding:0.5cm">
                        <div class="col-6">

                        </div>
                     

                    </div>
                    <div class="card p-2">
                    <div class="col-md-3">




<a href="./index.php"><button class="btn btn-success btn-sm" id="exportButton">
        back to form</button></a>

</div>
                        <form class="mt-3" method="POST" action="">
                            <div class="col-sm-12">
                            <div class="row">
                            

                           

                        </div>
                        <br>
                                <div class="input-group has-validation">
                                    <span class="input-group-text">REG NUMBER</span>
                                    <input type="text" name="regnumber" class="form-control" required>
                                    <div class="invalid-feedback">Please enter regnumber.</div>
                                </div>
                            </div>
                            <br>
                            <div class="row">
                                <div class="col-6">
                                    <button type="submit" name="search" class="btn btn-outline-primary my-3 col-12">View information</button>
                                </div>
                                <div class="col-6">
                                    <button type="reset" class="btn btn-outline-primary my-3 col-12">Reset</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Second Form: Display and update fetched student data -->

                <?php if($isViewing){
                  ?>
                   <div class="col-lg-6">
                    <div class="card p-2">
                        <form class="mt-3" method="POST" action="">
                            <h5 class="card-title">STUDENT INFORMATION</h5>
                            <div class="col-sm-12 my-3">
                                <div class="input-group has-validation">
                                    <span class="input-group-text">REG NUMBER</span>
                                    <input type="text" name="regnumber" class="form-control" disabled value="<?php echo $regnumber; ?>" readonly>
                                    <input type="text" name="regnumber1" class="form-control" hidden  value="<?php echo $regnumber; ?>" readonly>
                                </div>
                            </div>
                            <div class="col-sm-12">
                                <div class="input-group has-validation">
                                    <span class="input-group-text">NAMES</span>
                                    <input type="text" name="names" class="form-control" disabled value="<?php echo $fullnames; ?>" required>
                                </div>
                            </div>
                            <div class="col-sm-12 my-3">
                                <div class="input-group has-validation">
                                    <span class="input-group-text">EMAIL</span>
                                    <input type="email" name="email" class="form-control"  disabled value="<?php echo $studentemail; ?>" required>
                                </div>
                            </div>

                            <div class="col-sm-12 my-3">
                                <div class="input-group has-validation">
                                    <span class="input-group-text">Phone</span>
                                    <input type="text" name="phone" class="form-control" disabled value="<?php echo $phone1; ?>" required>
                                </div>
                            </div>

                            <div class="col-sm-12 my-3">
                                <div class="input-group has-validation">
                                    <span class="input-group-text">National id number</span>
                                    <input type="number" name="nid" class="form-control" disabled value="<?php echo $nid; ?>" required>
                                </div>
                            </div>

                            <div class="col-sm-12">
                                <div class="input-group has-validation">
                                    <span class="input-group-text">CAMPUS</span>
                                    <input type="text" name="campus" class="form-control" disabled value="<?php echo $campus; ?>" required>
                                </div>
                            </div>
                            <div class="col-sm-12 my-3">
                                <div class="input-group has-validation">
                                    <span class="input-group-text">COLLEGE</span>
                                    <input type="text" name="college" class="form-control" disabled value="<?php echo $college; ?>" required>
                                </div>
                            </div>
                            <div class="col-sm-12">
                                <div class="input-group has-validation">
                                    <span class="input-group-text">SCHOOL</span>
                                    <input type="text" name="school" class="form-control" disabled value="<?php echo $school; ?>" required>
                                </div>
                            </div>
                            <div class="col-sm-12 my-3">
                                <div class="input-group has-validation">
                                    <span class="input-group-text">PROGRAM</span>
                                    <input type="text" name="program" class="form-control"  disabled value="<?php echo $program; ?>" required>
                                </div>
                            </div>

                            <div class="col-sm-12 my-3">
                            
                            <?php
                                                                if (!$picture == null) {
                                                                    ?><br>
                                                                    <img src="./Students/<?php echo $picture; ?>" alt=""
                                                                        style="width:3.7cm;margin-top:0cm">

                                                                        <?php
                                                                        if($status=='rejected')
                                                                        {
                                                                            ?>
                                                                            <div alt=""
                                                                            style="padding:0.4cm;margin-top:0.3cm;border:1px solid red;color:red">
                                                                            picture rejected
                                                                        </div>
                                                                       
                                                                        <?php

                                                                        }
                                                                       else if($status=='accepted')
                                                                        {
                                                                            ?>
                                                                            <div alt=""
                                                                            style="padding:0.4cm;margin-top:0.3cm;border:1px solid green;color:green">
                                                                            picture re-acepted after being rejected !
                                                                        </div>
                                                                       
                                                                        <?php

                                                                        }


                                                                         ?>


                                                               
                                                                    <?php
                                                                } else {
                                                                    ?>
                                                                    
                                                                    <div alt=""
                                                                        style="padding:0.4cm;margin-top:0.3cm;border:1px solid red;color:red">
                                                                        student has not yet upload picture
                                                                    </div>
                                                                   
                                                                    <?php
                                                                }
                                                                ?>
                            </div>
                            <br>
                           
                        </form>
                    </div>
                </div>

                  <?php

                }else{

                }?>
                 
               

            </div>
        </section>
    </main>

    <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i
            class="bi bi-arrow-up-short"></i></a>

    <!-- Vendor JS Files -->
    <script src="assets/vendor/apexcharts/apexcharts.min.js"></script>
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/chart.js/chart.umd.js"></script>
    <script src="assets/vendor/echarts/echarts.min.js"></script>
    <script src="assets/vendor/quill/quill.min.js"></script>
    <script src="assets/vendor/simple-datatables/simple-datatables.js"></script>
    <script src="assets/vendor/tinymce/tinymce.min.js"></script>
    <script src="assets/vendor/php-email-form/validate.js"></script>

    <!-- Template Main JS File -->
    <script src="assets/js/main.js"></script>
</body>

</html>
