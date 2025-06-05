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

    p {
      font-family: italic;
      color: darkblue;
      text-align: center;

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
                    <center> <img class="logo1" src="./assets/img/ur.png" alt=""></center> 
                    </div>
                    <!-- <h5 class="card-title text-justify pb-0 fs-4">Authentication...</h5> -->

                    <?php
                    include("connection.php");
                    // Query to select users
                    $query = "SELECT * FROM system";
                    $result = mysqli_query($connection, $query);

                    while ($row = mysqli_fetch_assoc($result)) {
                      $status = $row['status'];
                    }


                    if ($status == "mentainance") {
                      ?>
                      <p>
                        <br>
                        hello student, this system is under mentainance ! stay tune ! 👌
                      </p>
                      <?php
                    }

                    if ($status == "development") {
                      ?>
                      <p>
                        <br>
                        hello student, this system is under development ! be ready any time...👍
                      </p>
                      <?php
                    }
                    if ($status == "offline") {
                      ?>
                      <p>
                        <br>
                        Hello student, this system is currently offline as the working hours for today have ended. It will be available again tomorrow. 👍
                      </p>
                      <?php
                    }


                    if ($status == "closed") {
                      ?>
                      <p>
                        <br>
                        hello student, this system is closed thank you for use it !...😍
                      </p>
                      <?php
                    }  if ($status == "live") {
                      header("Location: index.php");
                      exit(); // Stop further script execution
      
                    }
                     else {
                      // echo $status;
                      
                    }



                    ?>


                  </div>

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