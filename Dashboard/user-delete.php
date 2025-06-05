<?php
include('connection.php');
$id=$_GET['userId'];
if (!isset($id)) {
    echo "<script>window.location.href='add_user.php'</script>";
  }



            $ok=mysqli_query($connection,"delete from users where id='$id'");
            if($ok){
                echo "<script>alert('deleted')</script>";
                echo "<script>window.location.href='add_user.php'</script>";
            }
       
// }



?>