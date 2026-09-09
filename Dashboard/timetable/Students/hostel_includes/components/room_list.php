<?php
require_once 'hostel_card.php';

function hasAvailableRooms($connection, $hostel_id) {
    $query = "SELECT COUNT(*) as count FROM rooms WHERE hostel_id = ? AND remain > 0";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $hostel_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'] > 0;
}

function getRooms($connection, $hostel_id, $page = 1, $per_page = 10) {
    $offset = ($page - 1) * $per_page;
    
    // Get total count for pagination
    $count_query = "SELECT COUNT(*) as total FROM rooms WHERE hostel_id = ? AND remain > 0";
    $count_stmt = $connection->prepare($count_query);
    $count_stmt->bind_param("i", $hostel_id);
    $count_stmt->execute();
    $total = $count_stmt->get_result()->fetch_assoc()['total'];
    
    // Get rooms for current page
    $query = "SELECT * FROM rooms WHERE hostel_id = ? AND remain > 0 ORDER BY room_code LIMIT ? OFFSET ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("iii", $hostel_id, $per_page, $offset);
    $stmt->execute();
    $rooms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    return [
        'rooms' => $rooms,
        'total' => $total,
        'total_pages' => ceil($total / $per_page)
    ];
}

function getRoomsJson($connection, $hostel_id, $page = 1, $per_page = 10) {
    $offset = ($page - 1) * $per_page;
    
    // Get total count for pagination
    $count_query = "SELECT COUNT(*) as total FROM rooms WHERE hostel_id = ? AND remain > 0";
    $count_stmt = $connection->prepare($count_query);
    $count_stmt->bind_param("i", $hostel_id);
    $count_stmt->execute();
    $total = $count_stmt->get_result()->fetch_assoc()['total'];
    
    // Get rooms for current page with application counts
    $query = "SELECT r.*, 
              (SELECT COUNT(*) FROM applications a WHERE a.room_id = r.id AND a.status != 'rejected') as current_applications
              FROM rooms r 
              WHERE r.hostel_id = ? AND r.remain > 0 
              ORDER BY r.room_code 
              LIMIT ? OFFSET ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("iii", $hostel_id, $per_page, $offset);
    $stmt->execute();
    $rooms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    return [
        'success' => true,
        'rooms' => $rooms,
        'total' => $total,
        'total_pages' => ceil($total / $per_page),
        'current_page' => $page
    ];
}

function displayRoomList($connection, $hostel_id, $page = 1) {
    $result = getRooms($connection, $hostel_id, $page);
    $rooms = $result['rooms'];
    $total_pages = $result['total_pages'];
    
    if (empty($rooms)) {
        echo '<div class="alert alert-info">No rooms available in this hostel.</div>';
        return;
    }
    ?>
    
    <div class="room-list">
        <?php foreach ($rooms as $room): ?>
            <div class="room-card">
                <div class="room-header">
                    <h6 class="room-code">
                        <i class="bi bi-door-open me-2"></i>
                        <?php echo htmlspecialchars($room['room_code']); ?>
                    </h6>
                    <span class="badge bg-success">
                        <i class="bi bi-check-circle me-1"></i>
                        Availables
                    </span>
                </div>
                <div class="room-body">
                    <div class="room-info">
                        <div class="info-item">
                            <i class="bi bi-people"></i>
                            <span><?php echo $room['remain']; ?>/<?php echo $room['number_of_beds']; ?> Beds Available</span>
                        </div>
                        <div class="info-item">
                            <i class="bi bi-building"></i>
                            <span>Block <?php echo htmlspecialchars($room['block']); ?></span>
                        </div>
                    </div>
                    <button type="button" 
                            class="btn btn-primary btn-sm apply-btn"
                            data-room-id="<?php echo $room['id']; ?>"
                            data-room-code="<?php echo htmlspecialchars($room['room_code']); ?>">
                        <i class="bi bi-check-circle me-1"></i>
                        Apply
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <?php if ($total_pages > 1): ?>
        <div class="pagination-container mt-3">
            <nav aria-label="Room list pagination">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="#" data-page="<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
    <?php
}
?>

<style>
.room-list {
    max-height: 400px;
    overflow-y: auto;
    padding: 1rem;
}

.room-card {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 0.5rem;
    padding: 1rem;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
}

.room-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.room-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.room-code {
    margin: 0;
    color: #0d6efd;
    font-weight: 500;
}

.room-body {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.room-info {
    display: flex;
    gap: 1rem;
}

.info-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #6c757d;
    font-size: 0.875rem;
}

.info-item i {
    color: #0d6efd;
}

.apply-btn {
    white-space: nowrap;
}

.pagination-container {
    margin-top: 1rem;
}

.page-link {
    color: #0d6efd;
    border: 1px solid #dee2e6;
    padding: 0.375rem 0.75rem;
}

.page-item.active .page-link {
    background-color: #0d6efd;
    border-color: #0d6efd;
}
</style>

<script>
function loadRooms(hostelId, page) {
    fetch(`get_rooms.php?hostel_id=${hostelId}&page=${page}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const roomsContainer = document.getElementById('rooms-container');
                const currentContent = roomsContainer.innerHTML;
                
                let html = '<div class="room-list">';
                data.rooms.forEach(room => {
                    html += `
                        <div class="card room-card">
                            <div class="card-body">
                                <div class="room-info">
                                    <div class="room-code">
                                        <i class="bi bi-door-open me-2"></i>
                                        ${room.room_code}
                                    </div>
                                    <div class="room-stats">
                                        <div class="room-stat">
                                            <i class="bi bi-people text-primary"></i>
                                            ${room.number_of_beds} Beds
                                        </div>
                                        <div class="room-stat">
                                            <i class="bi bi-check-circle text-success"></i>
                                            ${room.remain} Available
                                        </div>
                                        ${room.current_applications > 0 ? `
                                            <div class="room-stat">
                                                <i class="bi bi-hourglass-split text-warning"></i>
                                                ${room.current_applications} Pending
                                            </div>
                                        ` : ''}
                                    </div>
                                    <div class="room-actions">
                                        <form action="apply_room.php" method="POST" class="d-inline">
                                            <input type="hidden" name="room_id" value="${room.id}">
                                            <input type="hidden" name="hostel_id" value="${hostelId}">
                                            <button type="submit" class="btn btn-sm btn-primary" 
                                                    ${room.remain <= 0 ? 'disabled' : ''}
                                                    onclick="return confirm('Are you sure you want to apply for this room?')">
                                                <i class="bi bi-check-circle me-1"></i>
                                                Apply
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                
                // Add pagination if needed
                if (data.total_pages > 1) {
                    html += `
                        <div class="pagination-container mt-3">
                            <nav aria-label="Room pagination">
                                <ul class="pagination justify-content-center">
                    `;
                    
                    for (let i = 1; i <= data.total_pages; i++) {
                        html += `
                            <li class="page-item ${i === page ? 'active' : ''}">
                                <a class="page-link" href="#" data-page="${i}">${i}</a>
                            </li>
                        `;
                    }
                    
                    html += `
                                </ul>
                            </nav>
                        </div>
                    `;
                }
                
                // Only update if content has changed
                if (currentContent !== html) {
                    roomsContainer.innerHTML = html;
                    
                    // Add pagination event listeners
                    document.querySelectorAll('.pagination .page-link').forEach(link => {
                        link.addEventListener('click', function(e) {
                            e.preventDefault();
                            const newPage = this.dataset.page;
                            loadRooms(hostelId, newPage);
                        });
                    });
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
}
</script> 