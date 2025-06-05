<?php
// Force 401 Unauthorized error
header("HTTP/1.1 401 Unauthorized");
include '401.php';
exit();
?> 