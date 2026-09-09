<?php
// Set Content Security Policy header
// header("Content-Security-Policy: upgrade-insecure-requests");

// session_start();
// include('connection.php');

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit();
}
$userid = $_SESSION['id'];
$campus = $_SESSION['campus'];
$ok1 = mysqli_query($connection, "select * from users where id=$userid");
while ($row = mysqli_fetch_array($ok1)) {
    $id = $row["id"];
    $names = $row["names"];
    $image = $row["image"];
    $phone = $row["phone"];
    $email = $row["email"];

    $role = $row["role"];
    $campus = $row["campus"];
}
         
?>
<!-- ======= Header ======= -->

<header id="header" class="header fixed-top d-flex align-items-center">

  <div class="d-flex align-items-center justify-content-between">
    <a href="index.php" class="logo d-flex align-items-center" style="text-decoration: none;">
  <!-- remove link line -->
      <span class="d-none d-lg-block">TIME TABLE</span>
    </a>
    <i class="bi bi-list toggle-sidebar-btn"></i>
  </div><!-- End Logo -->


  <nav class="header-nav ms-auto">
    <ul class="d-flex align-items-center">

      <li class="nav-item d-block d-lg-none">
        <a class="nav-link nav-icon search-bar-toggle " href="#">
          <i class="bi bi-search"></i>
        </a>
      </li><!-- End Search Icon-->

      <li class="nav-item dropdown">



      </li><!-- End Notification Nav -->

      <li class="nav-item dropdown">

     
      
      </li><!-- End Messages Nav -->

      <li class="nav-item dropdown pe-3">

        <a class="nav-link nav-profile d-flex align-items-center pe-0" href="#" data-bs-toggle="dropdown">
          <img src="<?php echo $image ?>" alt="Profile" class="rounded-circle" style="height:1cm;width:1cm">
          <span class="d-none d-md-block dropdown-toggle ps-2"><?php echo $names; ?></span>
        </a><!-- End Profile Iamge Icon -->

        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow profile">
          <li class="dropdown-header">
            <h6><?php echo $names;?></h6>
            <span><?php echo $role; ?></span>
          </li>
          <li>
            <hr class="dropdown-divider">
          </li>

          <li>
            <a class="dropdown-item d-flex align-items-center" href="users-profile.php">
              <i class="bi bi-person"></i>
              <span>My Profile</span>
            </a>
          </li>
          <li>
            <hr class="dropdown-divider">
          </li>

          <li>
            <a class="dropdown-item d-flex align-items-center" href="users-profile.php">
              <i class="bi bi-gear"></i>
              <span>Account Settings</span>
            </a>
          </li>
          <li>
            <hr class="dropdown-divider">
          </li>

          <li>
            <a class="dropdown-item d-flex align-items-center" href="../index.php">
              <i class="bi bi-question-circle"></i>
              <span>go user page</span>
            </a>
          </li>
          <li>
            <hr class="dropdown-divider">
          </li>

          <li>
            <a class="dropdown-item d-flex align-items-center" href="../logout.php">
              <i class="bi bi-box-arrow-right"></i>
              <span>Sign Out</span>
            </a>
          </li>

        </ul><!-- End Profile Dropdown Items -->
      </li><!-- End Profile Nav -->

    </ul>
  </nav><!-- End Icons Navigation -->

</header><!-- End Header -->