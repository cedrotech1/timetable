
<?php
// Include database connection
include('connection.php');

// Check if userId is provided in the URL
if (isset($_GET['userId'])) {
    // Sanitize the userId input to prevent SQL injection
    $userId = mysqli_real_escape_string($connection, $_GET['userId']);

    // Update the user's active status to 1 (active)
    $query = "UPDATE users SET active = 1 WHERE id = $userId";

    // Execute the update query
    if (mysqli_query($connection, $query)) {
        // User activated successfully, redirect back to the page where the activation was triggered
        $save_query = "INSERT INTO privilages (uid, title) VALUES ($userId, 'active')";
        mysqli_query($connection, $save_query);

        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    } else {
        // Error occurred while activating user
        echo 'Error activating user: ' . mysqli_error($connection);
    }
} else {
    // userId is not provided, redirect back to the page where the activation was triggered
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}
?>
