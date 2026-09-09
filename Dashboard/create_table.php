<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'connection.php';

// Check if table already exists
$tableCheck = $connection->query("SHOW TABLES LIKE 'timetable'");

if ($tableCheck->num_rows > 0) {
    die("Timetable table already exists");
}

// SQL to create table
$sql = "CREATE TABLE `timetable` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `module_id` int(11) DEFAULT NULL,
    `leader_lecturer_id` int(11) DEFAULT NULL,
    `facility_id` int(11) DEFAULT NULL,
    `semester` varchar(50) DEFAULT NULL,
    `academic_year_id` int(11) DEFAULT NULL,
    `status` varchar(50) DEFAULT NULL,
    `approvedby` int(11) DEFAULT NULL,
    `createdby` int(11) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `module_id` (`module_id`),
    KEY `leader_lecturer_id` (`leader_lecturer_id`),
    KEY `facility_id` (`facility_id`),
    KEY `academic_year_id` (`academic_year_id`),
    KEY `createdby` (`createdby`),
    KEY `approvedby` (`approvedby`),
    KEY `status` (`status`),
    KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// Execute query
if ($connection->query($sql) === TRUE) {
    echo "Timetable table created successfully<br>";
    
    // Insert sample data
    $sampleData = [
        "INSERT INTO `timetable` (`module_id`, `leader_lecturer_id`, `facility_id`, `semester`, `academic_year_id`, `status`, `approvedby`, `createdby`, `created_at`) 
        VALUES (1, 1, 1, 'Spring', 1, 'approved', 1, 1, NOW() - INTERVAL 30 DAY)",
        
        "INSERT INTO `timetable` (`module_id`, `leader_lecturer_id`, `facility_id`, `semester`, `academic_year_id`, `status`, `approvedby`, `createdby`, `created_at`)
        VALUES (2, 2, 2, 'Spring', 1, 'pending', NULL, 2, NOW() - INTERVAL 15 DAY)",
        
        "INSERT INTO `timetable` (`module_id`, `leader_lecturer_id`, `facility_id`, `semester`, `academic_year_id`, `status`, `approvedby`, `createdby`, `created_at`)
        VALUES (3, 1, 3, 'Fall', 1, 'approved', 1, 1, NOW() - INTERVAL 7 DAY)",
        
        "INSERT INTO `timetable` (`module_id`, `leader_lecturer_id`, `facility_id`, `semester`, `academic_year_id`, `status`, `approvedby`, `createdby`, `created_at`)
        VALUES (4, 3, 1, 'Fall', 1, 'rejected', 2, 3, NOW() - INTERVAL 3 DAY)",
        
        "INSERT INTO `timetable` (`module_id`, `leader_lecturer_id`, `facility_id`, `semester`, `academic_year_id`, `status`, `approvedby`, `createdby`, `created_at`)
        VALUES (5, 2, 4, 'Spring', 1, 'approved', 1, 2, NOW() - INTERVAL 1 DAY)"
    ];
    
    foreach ($sampleData as $sql) {
        if ($connection->query($sql) === TRUE) {
            echo "Inserted sample data: " . htmlspecialchars(substr($sql, 0, 50)) . "...<br>";
        } else {
            echo "Error inserting data: " . $connection->error . "<br>";
        }
    }
    
    echo "<br>Setup complete. <a href='check_db.php'>Check Database</a> | <a href='timetable_analytics.php'>View Analytics</a>";
} else {
    echo "Error creating table: " . $connection->error . "<br>";
}
?>
