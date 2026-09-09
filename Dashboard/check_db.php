<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'connection.php';

// Check connection
if (!isset($connection) || $connection->connect_error) {
    die("<h2>Database Connection Error</h2>" . ($connection->connect_error ?? 'Connection not established'));
}

// Function to check if table exists
function tableExists($connection, $table) {
    $result = $connection->query("SHOW TABLES LIKE '$table'");
    return $result->num_rows > 0;
}

// Check if timetable table exists
$tableExists = tableExists($connection, 'timetable');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Check</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        table { border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>Database Connection Test</h1>
    
    <h2>Connection Status</h2>
    <p class="success">✓ Successfully connected to database: <?= $connection->host_info ?></p>
    
    <h2>Table Check</h2>
    <?php if ($tableExists): ?>
        <p class="success">✓ Timetable table exists</p>
        
        <h3>Table Structure</h3>
        <?php
        $result = $connection->query("DESCRIBE timetable");
        if ($result): ?>
            <table>
                <tr>
                    <th>Field</th>
                    <th>Type</th>
                    <th>Null</th>
                    <th>Key</th>
                    <th>Default</th>
                    <th>Extra</th>
                </tr>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['Field']) ?></td>
                        <td><?= htmlspecialchars($row['Type']) ?></td>
                        <td><?= htmlspecialchars($row['Null']) ?></td>
                        <td><?= htmlspecialchars($row['Key']) ?></td>
                        <td><?= htmlspecialchars($row['Default'] ?? 'NULL') ?></td>
                        <td><?= htmlspecialchars($row['Extra']) ?></td>
                    </tr>
                <?php endwhile; ?>
            </table>
            
            <h3>Sample Data (First 5 Rows)</h3>
            <?php
            $result = $connection->query("SELECT * FROM timetable ORDER BY created_at DESC LIMIT 5");
            if ($result && $result->num_rows > 0): ?>
                <table>
                    <tr>
                        <?php while ($field = $result->fetch_field()): ?>
                            <th><?= htmlspecialchars($field->name) ?></th>
                        <?php endwhile; ?>
                    </tr>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <?php foreach ($row as $value): ?>
                                <td><?= htmlspecialchars($value) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endwhile; ?>
                </table>
            <?php else: ?>
                <p>No data found in timetable table.</p>
            <?php endif; ?>
            
        <?php else: ?>
            <p class="error">Error describing table: <?= $connection->error ?></p>
        <?php endif; ?>
        
    <?php else: ?>
        <p class="error">✗ Timetable table does not exist</p>
        
        <h3>Create Table</h3>
        <form method="post" action="create_table.php">
            <button type="submit">Create Timetable Table</button>
        </form>
    <?php endif; ?>
    
    <h2>Test API Endpoint</h2>
    <p><a href="test_api.php" target="_blank">Test API Response</a></p>
    
    <h2>Check Timetable Analytics Page</h2>
    <p><a href="timetable_analytics.php" target="_blank">Open Timetable Analytics</a></p>
</body>
</html>
