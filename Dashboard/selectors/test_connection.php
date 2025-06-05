<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('connection.php');

echo "<h2>Testing Database Connection</h2>";

if ($connection) {
    echo "Database connection successful<br>";
    
    // Test programs table
    echo "<h3>Testing Programs Table</h3>";
    $query = "SELECT * FROM program";
    $result = mysqli_query($connection, $query);
    
    if ($result) {
        echo "Found " . mysqli_num_rows($result) . " programs:<br>";
        while ($row = mysqli_fetch_assoc($result)) {
            echo "ID: " . $row['id'] . ", Name: " . $row['name'] . ", Code: " . $row['code'] . ", Department ID: " . $row['department_id'] . "<br>";
        }
    } else {
        echo "Error querying programs: " . mysqli_error($connection) . "<br>";
    }
    
    // Test departments table
    echo "<h3>Testing Departments Table</h3>";
    $query = "SELECT * FROM department";
    $result = mysqli_query($connection, $query);
    
    if ($result) {
        echo "Found " . mysqli_num_rows($result) . " departments:<br>";
        while ($row = mysqli_fetch_assoc($result)) {
            echo "ID: " . $row['id'] . ", Name: " . $row['name'] . ", School ID: " . $row['school_id'] . "<br>";
        }
    } else {
        echo "Error querying departments: " . mysqli_error($connection) . "<br>";
    }
    
    // Test schools table
    echo "<h3>Testing Schools Table</h3>";
    $query = "SELECT * FROM school";
    $result = mysqli_query($connection, $query);
    
    if ($result) {
        echo "Found " . mysqli_num_rows($result) . " schools:<br>";
        while ($row = mysqli_fetch_assoc($result)) {
            echo "ID: " . $row['id'] . ", Name: " . $row['name'] . ", College ID: " . $row['college_id'] . "<br>";
        }
    } else {
        echo "Error querying schools: " . mysqli_error($connection) . "<br>";
    }
    
    // Test colleges table
    echo "<h3>Testing Colleges Table</h3>";
    $query = "SELECT * FROM college";
    $result = mysqli_query($connection, $query);
    
    if ($result) {
        echo "Found " . mysqli_num_rows($result) . " colleges:<br>";
        while ($row = mysqli_fetch_assoc($result)) {
            echo "ID: " . $row['id'] . ", Name: " . $row['name'] . ", Campus ID: " . $row['campus_id'] . "<br>";
        }
    } else {
        echo "Error querying colleges: " . mysqli_error($connection) . "<br>";
    }
    
    // Test campuses table
    echo "<h3>Testing Campuses Table</h3>";
    $query = "SELECT * FROM campus";
    $result = mysqli_query($connection, $query);
    
    if ($result) {
        echo "Found " . mysqli_num_rows($result) . " campuses:<br>";
        while ($row = mysqli_fetch_assoc($result)) {
            echo "ID: " . $row['id'] . ", Name: " . $row['name'] . "<br>";
        }
    } else {
        echo "Error querying campuses: " . mysqli_error($connection) . "<br>";
    }
    
} else {
    echo "Database connection failed: " . mysqli_connect_error() . "<br>";
}
?> 