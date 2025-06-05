<?php
// Include database connection
include('connection.php');

// Check if userId is provided in the URL
if (isset($_GET['userId'])) {
    // Sanitize the userId input to prevent SQL injection
    $userId = mysqli_real_escape_string($connection, $_GET['userId']);

    // Update the user's active status to 0 (inactive)
    $query = "UPDATE users SET active = 0 WHERE id = $userId";

    // Execute the update query
    if (mysqli_query($connection, $query)) {
        $save_query = "delete from privilages where uid=$userId and title='active'";
        mysqli_query($connection, $save_query);
        // User deactivated successfully, redirect back to the page where the deactivation was triggered
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    } else {
        // Error occurred while deactivating user
        echo 'Error deactivating user: ' . mysqli_error($connection);
    }
} else {
    // userId is not provided, redirect back to the page where the deactivation was triggered
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}
?>
