<?php
require_once 'room_list.php';
?>

<!-- Rooms Modal -->
<div class="modal fade" id="roomsModal" tabindex="-1" aria-labelledby="roomsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="roomsModalLabel">
                    <i class="bi bi-building me-2"></i>
                    <span id="modalHostelName">Loading...</span>
                </h5>
                <div class="d-flex align-items-center">
                    <button type="button" class="btn btn-light btn-sm me-2" id="refreshRoomsBtn" title="Refresh Rooms">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body p-0">
                <div id="rooms-container">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>  
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
let currentHostelId = null;
let lastUpdateTime = 0;
const UPDATE_INTERVAL = 500; // 500ms for more frequent updates
let isUpdating = false;
let lastRoomStates = new Map(); // Track last known room states
let pendingUpdates = new Set(); // Track pending updates
let updateQueue = []; // Queue for updates

// Handle modal show event
document.getElementById('roomsModal').addEventListener('show.bs.modal', function(event) {
    const button = event.relatedTarget;
    const hostelId = button.getAttribute('data-hostel-id');
    const hostelName = button.getAttribute('data-hostel-name');
    
    currentHostelId = hostelId;
    lastUpdateTime = Date.now();
    lastRoomStates.clear();
    pendingUpdates.clear();
    updateQueue = [];
    document.getElementById('modalHostelName').textContent = hostelName;
    
    // Load initial data
    loadRooms(hostelId);
    
    // Start periodic updates
    startPeriodicUpdates();
});

function startPeriodicUpdates() {
    if (window.roomUpdateInterval) {
        clearInterval(window.roomUpdateInterval);
    }
    
    window.roomUpdateInterval = setInterval(() => {
        if (currentHostelId && Date.now() - lastUpdateTime >= UPDATE_INTERVAL && !isUpdating) {
            processUpdateQueue();
        }
    }, UPDATE_INTERVAL);
}

function processUpdateQueue() {
    if (updateQueue.length > 0) {
        const update = updateQueue.shift();
        loadRooms(update.hostelId, update.isInitialLoad);
    } else {
        loadRooms(currentHostelId, false);
    }
    lastUpdateTime = Date.now();
}

function queueUpdate(hostelId, isInitialLoad = false) {
    const updateKey = `${hostelId}`;
    if (!pendingUpdates.has(updateKey)) {
        pendingUpdates.add(updateKey);
        updateQueue.push({ hostelId, isInitialLoad });
    }
}

function loadRooms(hostelId, isInitialLoad = false) {
    if (isUpdating) {
        queueUpdate(hostelId, isInitialLoad);
        return;
    }
    
    isUpdating = true;
    
    // Add timestamp to prevent race conditions
    const timestamp = Date.now();
    
    fetch(`get_rooms.php?hostel_id=${hostelId}&_=${timestamp}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const roomsContainer = document.getElementById('rooms-container');
                
                if (!roomsContainer.querySelector('.room-list')) {
                    roomsContainer.innerHTML = '<div class="room-list"></div>';
                }
                
                const roomList = roomsContainer.querySelector('.room-list');
                const existingRooms = roomList.querySelectorAll('.room-card');
                const roomMap = new Map();
                
                existingRooms.forEach(room => {
                    const roomId = room.dataset.roomId;
                    roomMap.set(roomId, room);
                });
                
                // Process rooms with optimistic updates
                data.rooms.forEach(room => {
                    const isAvailable = room.remain > 0;
                    const roomState = `${room.id}-${room.remain}-${room.current_applications}`;
                    const lastState = lastRoomStates.get(room.id);
                    
                    // Skip if state hasn't changed
                    if (lastState === roomState && !isInitialLoad) {
                        roomMap.delete(room.id.toString());
                        return;
                    }
                    
                    lastRoomStates.set(room.id, roomState);
                    
                    const roomHtml = `
                        <div class="room-card" data-room-id="${room.id}" 
                             data-remain="${room.remain}" 
                             data-applications="${room.current_applications}"
                             data-timestamp="${timestamp}">
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
                                    <form action="apply_room.php" method="POST" class="d-inline apply-room-form">
                                        <input type="hidden" name="room_id" value="${room.id}">
                                        <input type="hidden" name="hostel_id" value="${hostelId}">
                                        <input type="hidden" name="timestamp" value="${timestamp}">
                                        <button type="submit" class="btn btn-sm btn-primary apply-btn" 
                                                ${!isAvailable ? 'disabled' : ''}>
                                            <i class="bi bi-check-circle me-1"></i>
                                            Apply
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    const existingRoom = roomMap.get(room.id.toString());
                    if (existingRoom) {
                        const existingTimestamp = parseInt(existingRoom.dataset.timestamp);
                        if (timestamp > existingTimestamp) {
                            const tempDiv = document.createElement('div');
                            tempDiv.innerHTML = roomHtml;
                            const newRoom = tempDiv.firstElementChild;
                            
                            existingRoom.className = newRoom.className;
                            existingRoom.dataset.roomId = newRoom.dataset.roomId;
                            existingRoom.dataset.remain = newRoom.dataset.remain;
                            existingRoom.dataset.applications = newRoom.dataset.applications;
                            existingRoom.dataset.timestamp = newRoom.dataset.timestamp;
                            
                            const existingInfo = existingRoom.querySelector('.room-info');
                            const newInfo = newRoom.querySelector('.room-info');
                            if (existingInfo.innerHTML !== newInfo.innerHTML) {
                                existingInfo.innerHTML = newInfo.innerHTML;
                            }
                        }
                        roomMap.delete(room.id.toString());
                    } else {
                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = roomHtml;
                        const newRoom = tempDiv.firstElementChild;
                        newRoom.style.opacity = '0';
                        roomList.appendChild(newRoom);
                        requestAnimationFrame(() => {
                            newRoom.style.transition = 'opacity 0.3s ease';
                            newRoom.style.opacity = '1';
                        });
                    }
                });
                
                roomMap.forEach(room => {
                    room.style.transition = 'opacity 0.3s ease';
                    room.style.opacity = '0';
                    setTimeout(() => room.remove(), 300);
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (!window.roomUpdateInterval) {
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to load rooms. Please try again.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        })
        .finally(() => {
            isUpdating = false;
            const updateKey = `${hostelId}`;
            pendingUpdates.delete(updateKey);
            
            // Process next update if any
            if (updateQueue.length > 0) {
                processUpdateQueue();
            }
        });
}

// Clean up when modal is closed
document.getElementById('roomsModal').addEventListener('hidden.bs.modal', function () {
    if (window.roomUpdateInterval) {
        clearInterval(window.roomUpdateInterval);
        window.roomUpdateInterval = null;
    }
    currentHostelId = null;
    lastUpdateTime = 0;
    isUpdating = false;
});

// Handle room application form submission
document.addEventListener('submit', function(e) {
    if (e.target.classList.contains('apply-room-form')) {
        e.preventDefault();
        
        const form = e.target;
        const submitBtn = form.querySelector('.apply-btn');
        const roomCard = form.closest('.room-card');
        const roomId = roomCard.dataset.roomId;
        const remain = parseInt(roomCard.dataset.remain);
        const timestamp = parseInt(roomCard.dataset.timestamp);
        
        // Validate timestamp
        if (Date.now() - timestamp > 5000) { // 5 seconds threshold
            Swal.fire({
                title: 'Error!',
                text: 'Room information is too old. Please refresh and try again.',
                icon: 'error',
                showCancelButton: true,
                confirmButtonText: 'Refresh Now',
                cancelButtonText: 'Cancel',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Refresh the rooms data
                    const hostelId = form.querySelector('input[name="hostel_id"]').value;
                    loadRooms(hostelId, true);
                }
            });
            return;
        }
        
        // Double check room availability
        if (remain <= 0) {
            Swal.fire({
                title: 'Error!',
                text: 'This room is no longer available. Please try another room.',
                icon: 'error',
                showCancelButton: true,
                confirmButtonText: 'Refresh Now',
                cancelButtonText: 'Cancel',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Refresh the rooms data
                    const hostelId = form.querySelector('input[name="hostel_id"]').value;
                    loadRooms(hostelId, true);
                }
            });
            return;
        }
        
        // Disable all apply buttons
        const allApplyButtons = document.querySelectorAll('.apply-btn');
        allApplyButtons.forEach(btn => {
            btn.disabled = true;
            if (btn !== submitBtn) {
                btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Processing...';
            }
        });
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
        
        const formData = new FormData(form);
        formData.append('timestamp', timestamp);
        
        fetch('apply_room.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    title: 'Success!',
                    text: data.message,
                    icon: 'success',
                    confirmButtonText: 'OK',
                    timer: 3000,
                    timerProgressBar: true
                }).then(() => {
                    window.location.href = 'index.php';
                });
            } else {
                Swal.fire({
                    title: 'Error!',
                    text: data.message,
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                
                const hostelId = formData.get('hostel_id');
                queueUpdate(hostelId, true);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                title: 'Error!',
                text: 'An error occurred while submitting your application. Please try again.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        })
        .finally(() => {
            allApplyButtons.forEach(btn => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Apply';
            });
        });
    }
});

// Add refresh button functionality
document.getElementById('refreshRoomsBtn').addEventListener('click', function() {
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
    
    if (currentHostelId) {
        loadRooms(currentHostelId, true);
    }
    
    setTimeout(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-arrow-clockwise"></i>';
    }, 1000);
});
</script>

<style>
.room-list {
    max-height: 70vh;
    overflow-y: auto;
    padding: 1rem;
}

.room-card {
    background: whitesmoke;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    margin-bottom: 1rem;
    padding: 1rem;
    transition: all 0.3s ease;
}

.room-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    background: white;
}

.room-info {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
}

.room-code {
    font-weight: 600;
    color: #0d6efd;
    font-size: 1.1rem;
}

.room-stats {
    display: flex;
    gap: 1.5rem;
    align-items: center;
    flex-wrap: wrap;
}

.room-stat {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    color: #6c757d;
}

.room-stat i {
    font-size: 1.1rem;
}

.room-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-sm {
    padding: 0.4rem 0.8rem;
    font-size: 0.9rem;
    border-radius: 6px;
}

/* Responsive styles */
@media (max-width: 768px) {
    .room-info {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .room-stats {
        width: 100%;
        justify-content: space-between;
    }
    
    .room-actions {
        width: 100%;
        justify-content: flex-end;
    }
    
    .room-card {
        padding: 0.75rem;
    }
}

@media (max-width: 576px) {
    .room-stats {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .room-stat {
        width: 100%;
    }
}

#refreshRoomsBtn {
    transition: transform 0.3s ease;
}

#refreshRoomsBtn:hover {
    transform: rotate(180deg);
}

#refreshRoomsBtn:disabled {
    transform: none;
    opacity: 0.7;
}
</style>