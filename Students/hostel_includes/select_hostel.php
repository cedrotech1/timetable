<?php
require_once 'components/hostel_card.php';
require_once 'components/rooms_modal.php';

// Get all hostels
$query = "SELECT * FROM hostels ORDER BY name";
$result = $connection->query($query);
$hostels = $result->fetch_all(MYSQLI_ASSOC);
?>
 
<div class="container-fluid py-4">
  
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-building me-2"></i>
                        Available Hostels
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($hostels)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            No hostels are currently available.
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($hostels as $hostel): ?>
                                <?php displayHostelCard($hostel, $connection); ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.container-fluid {
    max-width: 1400px;
    margin: 0 auto;
}

.card {
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.card-header {
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.alert {
    border: none;
    border-radius: 0.5rem;
}

.alert-info {
    background-color: #e3f2fd;
    color: #0d47a1;
}

.alert i {
    font-size: 1.25rem;
}
</style> 