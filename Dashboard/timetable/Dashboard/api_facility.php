<?php
session_start();
include('connection.php');

header('Content-Type: application/json');

try {
    // Handle get_campuses request
    if (isset($_GET['get_campuses'])) {
        $query = "SELECT id, name FROM campus ORDER BY name ASC";
        $result = mysqli_query($connection, $query);
        
        $campuses = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $campuses[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $campuses
        ]);
        exit;
    }

    // Get filter parameters
    $academic_year_id = isset($_GET['academic_year_id']) ? (int)$_GET['academic_year_id'] : null;
    $semester = isset($_GET['semester']) ? (int)$_GET['semester'] : null;
    $campus = isset($_GET['campus']) ? (int)$_GET['campus'] : null;
    $type = isset($_GET['type']) ? mysqli_real_escape_string($connection, $_GET['type']) : null;
    $location = isset($_GET['location']) ? mysqli_real_escape_string($connection, $_GET['location']) : null;
    $capacity = isset($_GET['capacity']) ? $_GET['capacity'] : null;
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 9;
    $offset = ($page - 1) * $per_page;

    // Build WHERE clause
    $where_conditions = [];
    if ($campus) {
        $where_conditions[] = "f.campus_id = $campus";
    }
    if ($type) {
        $where_conditions[] = "f.type = '$type'";
    }
    if ($location) {
        $where_conditions[] = "f.location LIKE '%$location%'";
    }
    if ($capacity) {
        list($min, $max) = explode('-', $capacity);
        if ($max === '+') {
            $where_conditions[] = "f.capacity >= $min";
        } else {
            $where_conditions[] = "f.capacity BETWEEN $min AND $max";
        }
    }

    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM facility f $where_clause";
    $count_result = mysqli_query($connection, $count_query);
    $total = mysqli_fetch_assoc($count_result)['total'];

    // Get facilities with pagination
    $query = "SELECT 
                f.id,
                f.name,
                f.location,
                f.type,
                f.capacity,
                f.campus_id,
                c.name as campus_name,
                GROUP_CONCAT(
                    CONCAT(
                        ts.day, '|',
                        TIME_FORMAT(ts.start_time, '%H:%i'), '|',
                        TIME_FORMAT(ts.end_time, '%H:%i'), '|',
                        t.semester, '|',
                        t.academic_year_id
                    )
                ) as taken_slots
              FROM facility f
              LEFT JOIN campus c ON f.campus_id = c.id
              LEFT JOIN timetable t ON f.id = t.facility_id
              LEFT JOIN timetable_sessions ts ON t.id = ts.timetable_id
              $where_clause
              GROUP BY f.id
              ORDER BY f.name ASC
              LIMIT $offset, $per_page";
    
    $result = mysqli_query($connection, $query);
    
    $facilities = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // Process taken slots into array
        $taken_slots = [];
        if ($row['taken_slots']) {
            $slots = explode(',', $row['taken_slots']);
            foreach ($slots as $slot) {
                list($day, $start_time, $end_time, $semester, $year_id) = explode('|', $slot);
                if ($academic_year_id && $semester) {
                    // Only include slots for the selected academic year and semester
                    if ($year_id == $academic_year_id && $semester == $semester) {
                        $taken_slots[] = [
                            'day' => $day,
                            'start_time' => $start_time,
                            'end_time' => $end_time,
                            'semester' => $semester,
                            'academic_year_id' => $year_id
                        ];
                    }
                } else {
                    $taken_slots[] = [
                        'day' => $day,
                        'start_time' => $start_time,
                        'end_time' => $end_time,
                        'semester' => $semester,
                        'academic_year_id' => $year_id
                    ];
                }
            }
        }
            
        $facility = [
            'id' => $row['id'],
            'name' => $row['name'],
            'location' => $row['location'],
            'type' => $row['type'],
            'capacity' => $row['capacity'],
            'campus_id' => $row['campus_id'],
            'campus_name' => $row['campus_name'],
            'taken_slots' => $taken_slots
        ];

        // Filter by status if specified
        if ($status) {
            $isAvailable = empty($taken_slots);
            if (($status === 'available' && !$isAvailable) || 
                ($status === 'occupied' && $isAvailable)) {
                continue;
            }
        }

        $facilities[] = $facility;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $facilities,
        'total' => $total
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

mysqli_close($connection);
?> 