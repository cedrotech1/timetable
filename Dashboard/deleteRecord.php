<?php
include('connection.php');


$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = intval($_POST["id"]);
    
    if ($id > 0) {
        $sql = "DELETE FROM info WHERE regnumber = ?";
        $stmt = $connection->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $message = "Record deleted successfully!";

            $sql = "DELETE FROM student_ids WHERE regnumber = ?";
            $stmt = $connection->prepare($sql);
            $stmt->bind_param("i", $id);
        } else {
            $message = "Error deleting record: " . $connection->error;
        }

        $stmt->close();
    } else {
        $message = "Invalid ID!";
    }
}

$connection->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Record</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            text-align: center;
            margin: 50px;
        }
        .container {
            background: white;
            padding: 20px;
            width: 300px;
            margin: auto;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
        }
        h2 {
            color: #333;
        }
        input {
            width: 80%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        button {
            background: red;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        button:hover {
            background: darkred;
        }
        .message {
            margin-top: 10px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Delete Record</h2>
        <form method="POST" action="">
            <label for="id">Enter Reg number to Delete:</label>
            <input type="number" name="id" id="id" required>
            <button type="submit">Delete</button>
        </form>
        <p class="message"><?= htmlspecialchars($message) ?></p>
    </div>
</body>
</html>
