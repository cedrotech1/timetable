<?php
require_once 'room_list.php';

// Function to get hostel statistics
function getHostelStats($connection, $hostel_id) {
    $stats = [
        'total_rooms' => 0,
        'available_rooms' => 0,
        'total_beds' => 0,
        'available_beds' => 0
    ];
    
    // Get room statistics
    $query = "SELECT 
                COUNT(*) as total_rooms,
                SUM(CASE WHEN remain > 0 THEN 1 ELSE 0 END) as available_rooms,
                SUM(number_of_beds) as total_beds,
                SUM(remain) as available_beds
              FROM rooms 
              WHERE hostel_id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $hostel_id);
    $stmt->execute();
    $room_stats = $stmt->get_result()->fetch_assoc();
    
    $stats['total_rooms'] = $room_stats['total_rooms'];
    $stats['available_rooms'] = $room_stats['available_rooms'];
    $stats['total_beds'] = $room_stats['total_beds'];
    $stats['available_beds'] = $room_stats['available_beds'];
    
    return $stats;
}

// Function to get hostel attributes
function getHostelAttributes($connection, $hostel_id) {
    $query = "SELECT attribute_key, attribute_value 
              FROM hostel_attributes 
              WHERE hostel_id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $hostel_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $attributes = [];
    while ($row = $result->fetch_assoc()) {
        $attributes[$row['attribute_key']] = $row['attribute_value'];
    }
    
    return $attributes;
}

function displayHostelCard($hostel, $connection) {
    // Get hostel statistics
    $stats = getHostelStats($connection, $hostel['id']);
    
    // Get hostel attributes
    $attributes = getHostelAttributes($connection, $hostel['id']);
    
    // Only display hostels with available rooms
    if (!hasAvailableRooms($connection, $hostel['id'])) {
        return;
    }
    ?>
    
    <div class="col-md-6 col-lg-4 mb-4" data-hostel-id="<?php echo $hostel['id']; ?>">
        <div class="card h-100 shadow-sm hostel-card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bi bi-building me-2"></i>
                    <?php echo htmlspecialchars($hostel['name']); ?>
                </h5>
                <span class="badge bg-light text-primary">
                    <i class="bi bi-door-open me-1"></i>
                    <span class="available-rooms"><?php echo $stats['available_rooms']; ?></span> Rooms Available
                </span>
            </div>
            
            <div class="card-body">
                <!-- Statistics -->
                <div class="hostel-stats">
                    <div class="stat-item">
                        <i class="bi bi-door-open text-primary"></i>
                        <div class="stat-value">
                            <span class="available-rooms"><?php echo $stats['available_rooms']; ?></span>/<span class="total-rooms"><?php echo $stats['total_rooms']; ?></span>
                        </div>
                        <div class="stat-label">Rooms</div>
                    </div>
                    <div class="stat-item">
                        <i class="bi bi-people text-success"></i>
                        <div class="stat-value">
                            <span class="available-beds"><?php echo $stats['available_beds']; ?></span>/<span class="total-beds"><?php echo $stats['total_beds']; ?></span>
                        </div>
                        <div class="stat-label">Beds</div>
                    </div>
                </div>
                
                <!-- Allowed Students Section -->
                <div class="allowed-students-section mb-3">
                    <h6 class="text-primary mb-2">
                        <i class="bi bi-people-fill me-2"></i>
                        Allowed Students
                    </h6>
                    <div class="border rounded p-3 bg-light">
                        <?php
                        $hasRestrictions = false;
                        if (isset($attributes['gender']) || isset($attributes['yearofstudy']) || 
                            isset($attributes['school']) || isset($attributes['college'])) {
                            $hasRestrictions = true;
                        }
                        
                        if (!$hasRestrictions): ?>
                            <div class="text-center text-success">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                All students are allowed for this hostel
                            </div>
                        <?php else: ?>
                            <div class="row g-2">
                                <?php if (isset($attributes['gender'])): ?>
                                <div class="col-12">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-gender-ambiguous text-primary me-2"></i>
                                        <span class="small"><?php echo htmlspecialchars($attributes['gender']); ?></span>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (isset($attributes['yearofstudy'])): ?>
                                <div class="col-12">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-mortarboard text-primary me-2"></i>
                                        <span class="small">Year <?php echo htmlspecialchars($attributes['yearofstudy']); ?></span>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (isset($attributes['school'])): ?>
                                <div class="col-12">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-building text-primary me-2"></i>
                                        <span class="small"><?php echo htmlspecialchars($attributes['school']); ?></span>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (isset($attributes['college'])): ?>
                                <div class="col-12">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-mortarboard-fill text-primary me-2"></i>
                                        <span class="small"><?php echo htmlspecialchars($attributes['college']); ?></span>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="card-footer bg-light">
                <button type="button" 
                        class="btn btn-primary w-100" 
                        data-bs-toggle="modal" 
                        data-bs-target="#roomsModal"
                        data-hostel-id="<?php echo $hostel['id']; ?>"
                        data-hostel-name="<?php echo htmlspecialchars($hostel['name']); ?>">
                    <i class="bi bi-door-open me-2"></i>
                    View Available Rooms
                </button>
            </div>
        </div>
    </div>

    <script>
    let isRefreshing = false;
    const hostelId = <?php echo $hostel['id']; ?>;

    function updateHostelStats() {
        if (isRefreshing) return;
        isRefreshing = true;

        fetch(`get_hostel_stats.php?hostel_id=${hostelId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const hostelCard = document.querySelector(`[data-hostel-id="${hostelId}"]`);
                    if (hostelCard) {
                        const stats = data.stats;
                        const currentContent = hostelCard.innerHTML;
                        
                        // Update stats without visual disruption
                        hostelCard.querySelector('.available-rooms').textContent = stats.available_rooms;
                        hostelCard.querySelector('.total-rooms').textContent = stats.total_rooms;
                        hostelCard.querySelector('.available-beds').textContent = stats.available_beds;
                        hostelCard.querySelector('.total-beds').textContent = stats.total_beds;
                    }
                }
            })
            .catch(error => console.error('Error:', error))
            .finally(() => {
                isRefreshing = false;
            });
    }

    // Start refresh interval - using 1 second interval
    const refreshInterval = setInterval(() => {
        if (!isRefreshing) {
            updateHostelStats();
        }
    }, 5000); // Refresh every 1 second

    // Clean up interval when page is unloaded
    window.addEventListener('beforeunload', () => {
        clearInterval(refreshInterval);
    });
    </script>
    <?php
}
?>

<style>
.hostel-card {
    transition: all 0.3s ease;
    border: 1px solid #dee2e6;
    margin-bottom: 1rem;
}

.hostel-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.hostel-card .card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.hostel-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.stat-item {
    text-align: center;
    padding: 0.5rem;
    background-color: #f8f9fa;
    border-radius: 0.25rem;
}

.stat-item i {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}

.stat-item .stat-value {
    font-size: 1.25rem;
    font-weight: 500;
    color: #0d6efd;
}

.stat-item .stat-label {
    font-size: 0.875rem;
    color: #6c757d;
}

.hostel-attributes {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.attribute-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.attribute-item i {
    color: #0d6efd;
}

.btn-view-rooms {
    width: 100%;
    margin-top: 1rem;
}
</style>

<?php
function renderHostelCard($hostel, $connection) {
    $stats = getHostelStats($connection, $hostel['id']);
    $attributes = getHostelAttributes($connection, $hostel['id']);
    ?>
    <div class="card hostel-card">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="bi bi-building me-2"></i>
                <?php echo htmlspecialchars($hostel['hostel_name']); ?>
            </h5>
        </div>
        <div class="card-body">
            <!-- Hostel Statistics -->
            <div class="hostel-stats row g-3">
    <div class="col-6 stat-item text-center">
        <i class="bi bi-door-open text-primary fs-3"></i>
        <div class="stat-value fw-bold"><?php echo $stats['available_rooms']; ?>/<?php echo $stats['total_rooms']; ?></div>
        <div class="stat-label text-muted">Rooms</div>
    </div>

    <div class="col-6 stat-item text-center">
        <i class="bi bi-people text-success fs-3"></i>
        <div class="stat-value fw-bold"><?php echo $stats['available_beds']; ?>/<?php echo $stats['total_beds']; ?></div>
        <div class="stat-label text-muted">Beds</div>
    </div>

    <!-- Add more stat items here if needed -->
    <!-- Example:
    <div class="col-6 stat-item text-center">
        <i class="bi bi-journal-check text-info fs-3"></i>
        <div class="stat-value fw-bold">2</div>
        <div class="stat-label text-muted">Applications</div>
    </div>
    -->
</div>


            <!-- Hostel Attributes -->
            <div class="hostel-attributes">
                <?php if (isset($attributes['location'])): ?>
                <div class="attribute-item">
                    <i class="bi bi-geo-alt"></i>
                    <span><?php echo htmlspecialchars($attributes['location']); ?></span>
                </div>
                <?php endif; ?>

                <?php if (isset($attributes['price'])): ?>
                <div class="attribute-item">
                    <i class="bi bi-currency-dollar"></i>
                    <span><?php echo htmlspecialchars($attributes['price']); ?> per semester</span>
                </div>
                <?php endif; ?>

                <?php if (isset($attributes['gender'])): ?>
                <div class="attribute-item">
                    <i class="bi bi-gender-ambiguous"></i>
                    <span><?php echo htmlspecialchars($attributes['gender']); ?></span>
                </div>
                <?php endif; ?>

                <?php if (isset($attributes['year_of_study'])): ?>
                <div class="attribute-item">
                    <i class="bi bi-mortarboard"></i>
                    <span>Year <?php echo htmlspecialchars($attributes['year_of_study']); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- View Rooms Button -->
            <button type="button" 
                    class="btn btn-primary btn-view-rooms" 
                    data-bs-toggle="modal" 
                    data-bs-target="#roomsModal"
                    data-hostel-id="<?php echo $hostel['id']; ?>"
                    data-hostel-name="<?php echo htmlspecialchars($hostel['hostel_name']); ?>">
                <i class="bi bi-door-open me-2"></i>
                View Available Rooms
            </button>
        </div>
    </div>
    <?php
}
?> 