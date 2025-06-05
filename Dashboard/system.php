<?php
include('connection.php');
include ('./includes/auth.php');
checkUserRole(['information_modifier']);







if (isset($_POST['update'])) {
  // Retrieve form data and sanitize inputs   
  $status = $connection->real_escape_string($_POST['status']);



  // Check if the email already exists
  $Query = "update system set status='$status'";

  $resultx = $connection->query($Query);

      if ($resultx) {
        echo "<script>window.location.href='system.php'</script>";
      } else {
          echo "Error: " . $sql . "<br>" . $connection->error;

}
}
// $connection->close();
?>


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title>UR-HOSTELS</title>
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
    .th {}
  </style>


</head>

<body>

  <?php
  include("./includes/header.php");
  include("./includes/menu.php");
  ?>



  <main id="main" class="main">



    <section class="section dashboard">
      <div class="row">



        <!-- Left side columns -->
        <div class="col-lg-12">
          <div class="row">

            <div class="card">
              <div class="card-body">
                <br>
                <ul class="nav nav-tabs d-flex" id="myTabjustified" role="tablist">
                
                  <li class="nav-item flex-fill" role="presentation">
                    <button class="nav-link w-100" id="profile-tab" data-bs-toggle="tab"
                      data-bs-target="#profile-justified" type="button" role="tab" aria-controls="profile"
                      aria-selected="false">System setting</button>
                  </li>
                </ul>

                <div class="tab-content pt-2" id="myTabjustifiedContent">
                  <div class="tab-pane fade show active" id="home-justified" role="tabpanel" aria-labelledby="home-tab">

                  <div class="row">
                      <div class="col-md-6"></div>
                      <div class="col-md-6">
                        <form class="mt-3" action="" method="POST">

                        <?php
                              

                                ?>

                
                          <div class="col-md-12">
                            <div class="form-floating">
                              <input type="text" class="form-control" id="floatingName" placeholder="app_password" value='<?php echo $status ?>'
                             disabled>
                              <label for="floatingName">current system status</label>
                            </div>
                          </div>
                          <br>
                          <!-- <input type="text" name='pid' value='<?php //echo $id ?>'> -->
                          <div class="col-md-12">
                            <div class="form-floating">
                              <select class="form-control" id="floatingSchool" name="status" aria-label="Select status">
                                 <option value="live">live</option>
                                 <option value="mentainance">mentainance</option>
                                   <option value="offline">offline</option>
                                  <option value="live">closed</option>
                                  <option value="development">development</option>

                           
                              </select>
                              <label for="floatingSchool">update system status</label>
                            </div>
                          </div>
                          <br>
                      



                    
                          <br>
                          <button type="submit" name='update' class="btn btn-danger col-12">update</button>
                        </form>
                      
                      </div>
                    </div>
                  </div>

             

                  <div class="tab-pane fade" id="profile-justified" role="tabpanel" aria-labelledby="profile-tab">
                    
                  </div>
                </div>
              </div>
            </div>




          </div>
        </div><!-- End Left side columns -->


      </div>
    </section>

  </main><!-- End #main -->

  <!-- ======= Footer ======= -->

  <?php
  include("./includes/footer.php");
  ?>

  <!-- End Footer -->

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
