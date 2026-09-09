<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $regNumber = $_POST['regNumber'];

    // Database connection
    $conn = new mysqli('localhost', 'root', '', 'ur-student-card', 3307);
    if ($conn->connect_error) {
        die('Database connection failed: ' . $conn->connect_error);
    }

    $stmt = $conn->prepare('SELECT email FROM info WHERE regnumber = ?');
    $stmt->bind_param('s', $regNumber);
    $stmt->execute();
    $stmt->bind_result($email);

    if ($stmt->fetch()) {
        echo htmlspecialchars($email);
    } else {
        echo 'No email found for this registration number.';
    }

    $stmt->close();
    $conn->close();
}
?>
