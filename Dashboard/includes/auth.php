<?php
// session_start();

/**
 * Function to protect pages based on user roles.
 *
 * @param array $allowedRoles Array of roles allowed to access the page.
 */
function checkUserRole(array $allowedRoles)
{
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header("HTTP/1.1 401 Unauthorized");
        include '../401.php';
        exit();
    }

    // Check if the user's role exists in the session
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowedRoles)) {
        header("HTTP/1.1 403 Forbidden");
        include '../403.php';
        exit();
    }
}

/**
 * Function to check if user is logged in
 */
function checkLogin()
{
    if (!isset($_SESSION['user_id'])) {
        header("HTTP/1.1 401 Unauthorized");
        include '../401.php';
        exit();
    }
}

/**
 * Function to check if user is admin
 */
function checkAdmin()
{
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        header("HTTP/1.1 403 Forbidden");
        include '../403.php';
        exit();
    }
}

/**
 * Function to check if user is student
 */
function checkStudent()
{
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
        header("HTTP/1.1 403 Forbidden");
        include '../403.php';
        exit();
    }
}
?>
