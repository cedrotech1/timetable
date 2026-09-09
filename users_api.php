<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *"); // Allow external apps to consume this API

include('connection.php');

// SQL query to fetch all users
$sql = "SELECT `id`, `staff_number`, `names`, `college`, `campus`, `school`, 
               `staff_type`, `department`, `accademic_rank`, `role`, `title`, 
               `email`, `ur_email`, `phone`, `gender`, `image`, `active`, 
               `created_at`, `updated_at` 
        FROM `users`";

$result = mysqli_query($connection, $sql);

$response = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $response[] = $row;
    }
    echo json_encode([
        "status" => "success",
        "count" => count($response),
        "data" => $response
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Query failed: " . mysqli_error($connection)
    ]);
}

mysqli_close($connection);
?>
