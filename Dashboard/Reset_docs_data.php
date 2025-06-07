<?php
include('connection.php');
include ('./includes/auth.php');
// checkUserRole(['information_modifier']);

$userid=$_SESSION['id'];

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve the entered password
    $password = $_POST["password"];

    // Fetch the hashed password for the admin (id = 1)
    $sql = "SELECT password FROM users WHERE id = '$userid'";
    $result = mysqli_query($connection, $sql);

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $hashedPassword = $row["password"];

        // Verify the entered password against the hashed password
        if (password_verify($password, $hashedPassword)) {
            // Password is correct, proceed with clearing data
            $truncate = "TRUNCATE TABLE module";
            mysqli_query($connection, $truncate);

            $truncate = "TRUNCATE TABLE facility";
            mysqli_query($connection, $truncate);

            $truncate = "TRUNCATE TABLE timetable";
            mysqli_query($connection, $truncate);

            $truncate = "TRUNCATE TABLE student_group";
            mysqli_query($connection, $truncate);

            $truncate = "TRUNCATE TABLE timetable_sessions";
            mysqli_query($connection, $truncate);


            $truncate = "delete from users where role='lecturer'";
            mysqli_query($connection, $truncate);



           

            echo "<script>alert('Done')</script>";
        } else {
            // Password is incorrect
            echo "<script>alert('Incorrect admin password')</script>";
        }
    } else {
        echo "<script>alert('Failed to fetch admin details')</script>";
    }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title> CLEAR DOCUMENTS</title>
  <meta content="" name="description">
  <meta content="" name="keywords">

  <!-- Favicons -->
  <link href="assets/img/icon1.png" rel="icon">
  <link href="assets/img/icon1.png" rel="apple-touch-icon">

  <!-- Google Fonts -->
  <link href="https://fonts.gstatic.com" rel="preconnect">
  <link
    href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i"
    rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
  <link href="assets/vendor/quill/quill.snow.css" rel="stylesheet">
  <link href="assets/vendor/quill/quill.bubble.css" rel="stylesheet">
  <link href="assets/vendor/remixicon/remixicon.css" rel="stylesheet">
  <link href="assets/vendor/simple-datatables/style.css" rel="stylesheet">

  <!-- Template Main CSS File -->
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    ul li{
      list-style: none;
    }
  </style>


</head>

<body>

  <?php
  include ("./includes/header.php");
  include ("./includes/menu.php");
  ?>



  <main id="main" class="main">



    <section class="section dashboard">
      <div class="row">
       
        <div class="col-lg-4">
          <div class="row">

          <div class="card">
    <div class="card-body">
        <h5 class="card-title">CLEAR DOCUMENTS</h5>
        <form id="clearDataForm" action="Reset_docs_data.php" method="post">
  <input type="password" class="form-control" name="password" placeholder="ENTER PASSWORD" required><br>
  <input type="submit" value="Clear Data" class="btn btn-danger col-12">
</form>

<script>
document.getElementById("clearDataForm").addEventListener("submit", function(event) {
  // Ask for confirmation before submitting the form
  var confirmClear = confirm("Are you sure you want to delete all system data?");
  if (!confirmClear) {
    // Prevent form submission if user cancels
    event.preventDefault();
  }
});
</script>


     
       
    </div>
</div>



          </div>
        </div><!-- End Left side columns -->


      </div>
    </section>

  </main><!-- End #main -->

  <!-- ======= Footer ======= -->

  <?php
  include ("./includes/footer.php");
  ?>

  <!-- End Footer -->

  <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i
      class="bi bi-arrow-up-short"></i></a>


  <!-- Template Main JS File -->
  <script src="assets/js/main.js"></script>

</body>

</html>