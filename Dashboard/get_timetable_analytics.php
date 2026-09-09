<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set content type to JSON
header('Content-Type: application/json');

// Include database connection
require_once '../connection.php';

// Function to send JSON response and exit
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Check database connection
if (!isset($connection) || $connection->connect_error) {
    sendJsonResponse(['success' => false, 'message' => 'Database connection failed: ' . ($connection->connect_error ?? 'No connection')], 500);
}

try {
    // Get request parameters with defaults
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-6 months'));
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
    $groupBy = isset($_GET['group_by']) ? $_GET['group_by'] : 'month';

    // Validate and sanitize inputs
    $startDate = filter_var($startDate, FILTER_SANITIZE_STRING);
    $endDate = filter_var($endDate, FILTER_SANITIZE_STRING);
    $groupBy = in_array($groupBy, ['day', 'week', 'month', 'year']) ? $groupBy : 'month';

    // Prepare response
    $response = [
        'success' => true,
        'data' => [
            'creationOverTime' => getCreationOverTime($conn, $startDate, $endDate, $groupBy),
            'byDayOfWeek' => getByDayOfWeek($conn, $startDate, $endDate),
            'byTimeOfDay' => getByTimeOfDay($conn, $startDate, $endDate)
        ]
    ];
    
    sendJsonResponse($response);
    
} catch (Exception $e) {
    sendJsonResponse([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ], 500);
}

// Close database connection
if (isset($connection)) {
    $connection->close();
}

exit;

/**
 * Get timetable creation data over time
 */
function getCreationOverTime($connection, $startDate, $endDate, $groupBy) {
    global $connection;
    
    $dateFormat = '';
    $interval = '';
    
    switch ($groupBy) {
        case 'day':
            $dateFormat = '%Y-%m-%d';
            $interval = '1 DAY';
            break;
        case 'week':
            $dateFormat = '%x-W%v';
            $interval = '1 WEEK';
            break;
        case 'year':
            $dateFormat = '%Y';
            $interval = '1 YEAR';
            break;
        case 'month':
        default:
            $dateFormat = '%Y-%m';
            $interval = '1 MONTH';
            break;
    }
    
    $query = "
        SELECT 
            DATE_FORMAT(created_at, ?) as period,
            COUNT(*) as count
        FROM 
            timetable
        WHERE 
            created_at BETWEEN ? AND ? + INTERVAL 1 DAY
        GROUP BY 
            period
        ORDER BY 
            period
    ";
    
    $stmt = $connection->prepare($query);
    if ($stmt === false) {
        throw new Exception('Prepare failed: ' . $connection->error);
    }
    
    $stmt->bind_param('sss', $dateFormat, $startDate, $endDate);
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if ($result === false) {
        throw new Exception('Get result failed: ' . $connection->error);
    }
    
    $labels = [];
    $values = [];
    
    while ($row = $result->fetch_assoc()) {
        $labels[] = $row['period'];
        $values[] = (int)$row['count'];
    }
    
    // Fill in missing dates with zero counts
    $allPeriods = generateDateRange($startDate, $endDate, $groupBy);
    $filledValues = [];
    
    foreach ($allPeriods as $period) {
        $index = array_search($period, $labels);
        $filledValues[] = $index !== false ? $values[$index] : 0;
    }
    
    return [
        'labels' => $allPeriods,
        'values' => $filledValues
    ];
}

/**
 * Get timetable by day of week (0=Sunday, 6=Saturday)
 */
function getByDayOfWeek($connection, $startDate, $endDate) {
    global $connection;
    
    $query = "
        SELECT 
            DAYOFWEEK(created_at) - 1 as day_of_week,
            COUNT(*) as count
        FROM 
            timetable
        WHERE 
            created_at BETWEEN ? AND ? + INTERVAL 1 DAY
        GROUP BY 
            day_of_week
        ORDER BY 
            day_of_week
    ";
    
    $stmt = $connection->prepare($query);
    if ($stmt === false) {
        throw new Exception('Prepare failed: ' . $connection->error);
    }
    
    $stmt->bind_param('ss', $startDate, $endDate);
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if ($result === false) {
        throw new Exception('Get result failed: ' . $connection->error);
    }
    
    // Initialize array with 7 days (0-6)
    $data = array_fill(0, 7, 0);
    
    while ($row = $result->fetch_assoc()) {
        $data[$row['day_of_week']] = (int)$row['count'];
    }
    
    return $data;
}

/**
 * Get timetable by time of day (hour)
 */
function getByTimeOfDay($connection, $startDate, $endDate) {
    global $connection;
    
    $query = "
        SELECT 
            HOUR(created_at) as hour,
            COUNT(*) as count
        FROM 
            timetable
        WHERE 
            created_at BETWEEN ? AND ? + INTERVAL 1 DAY
        GROUP BY 
            hour
        ORDER BY 
            hour
    ";
    
    $stmt = $connection->prepare($query);
    if ($stmt === false) {
        throw new Exception('Prepare failed: ' . $connection->error);
    }
    
    $stmt->bind_param('ss', $startDate, $endDate);
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if ($result === false) {
        throw new Exception('Get result failed: ' . $connection->error);
    }
    
    // Initialize array with 24 hours (0-23)
    $hours = array_fill(0, 24, 0);
    $labels = [];
    
    // Create labels (12-hour format with AM/PM)
    for ($i = 0; $i < 24; $i++) {
        $labels[] = date('g A', strtotime("$i:00:00"));
    }
    
    while ($row = $result->fetch_assoc()) {
        $hours[$row['hour']] = (int)$row['count'];
    }
    
    return [
        'labels' => $labels,
        'values' => $hours
    ];
}

/**
 * Generate an array of date periods between two dates
 */
function generateDateRange($startDate, $endDate, $groupBy) {
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $end->modify('+1 day'); // Include end date
    
    $interval = null;
    $format = '';
    
    switch ($groupBy) {
        case 'day':
            $interval = new DateInterval('P1D');
            $format = 'Y-m-d';
            break;
        case 'week':
            $interval = new DateInterval('P1W');
            $format = 'o-\WW';
            break;
        case 'year':
            $interval = new DateInterval('P1Y');
            $format = 'Y';
            break;
        case 'month':
        default:
            $interval = new DateInterval('P1M');
            $format = 'Y-m';
            break;
    }
    
    $period = new DatePeriod($start, $interval, $end);
    $dates = [];
    
    foreach ($period as $date) {
        $dates[] = $date->format($format);
    }
    
    return $dates;
}
?>
