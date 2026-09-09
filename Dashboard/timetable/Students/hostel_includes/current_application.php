<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<style>
.application-details .card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: none;
    border-radius: 0.5rem;
}

.application-details .card-header {
    border-radius: 0.5rem 0.5rem 0 0 !important;
    padding: 1rem 1.5rem;
}

.application-details .card-body {
    padding: 1.5rem;
}

.application-info-item {
    margin-bottom: 1.25rem;
    padding: 0.75rem;
    background-color: #f8f9fa;
    border-radius: 0.375rem;
    transition: all 0.2s ease;
}

.application-info-item:hover {
    background-color: #e9ecef;
}

.application-info-item label {
    font-size: 0.875rem;
    font-weight: 500;
    color: #6c757d;
    margin-bottom: 0.25rem;
}

.application-info-item p {
    font-size: 1rem;
    color: #212529;
    margin-bottom: 0;
}

.receipt-upload .card {
    border: 1px solid #dee2e6;
}

.receipt-upload .card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.current-receipt .card {
    border: 1px solid #dee2e6;
    background-color: #fff;
}

.roommate-card {
    padding: 1rem;
    margin-bottom: 1rem;
    background-color: #f8f9fa;
    border-radius: 0.375rem;
    border: 1px solid #dee2e6;
    transition: all 0.2s ease;
}

.roommate-card:hover {
    background-color: #e9ecef;
}

.roommate-card:last-child {
    margin-bottom: 0;
}

.badge {
    padding: 0.5em 0.75em;
    font-weight: 500;
}

.btn {
    padding: 0.5rem 1rem;
    font-weight: 500;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
}

.form-control:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}
</style>

<?php
if (!isset($current_application)) {
    return;
}
?>
<div class="container">
<div class="application-details mb-4">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0"><i class="bi bi-house-door me-2"></i> Current Application</h4>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="application-info-item">
                        <label><i class="bi bi-building me-2"></i>Hostel</label>
                        <p><?php echo htmlspecialchars($current_application['hostel_name']); ?></p>
                    </div>
                    <div class="application-info-item">
                        <label><i class="bi bi-door-open me-2"></i>Room</label>
                        <p><?php echo htmlspecialchars($current_application['room_code']); ?></p>
                    </div>
                    <div class="application-info-item">
                        <label><i class="bi bi-info-circle me-2"></i>Application Status</label>
                        <p>
                            <span class="badge bg-<?php 
                                echo $current_application['status'] === 'approved' ? 'success' : 
                                    ($current_application['status'] === 'rejected' ? 'danger' : 'warning'); 
                            ?>">
                                <?php echo ucfirst(htmlspecialchars($current_application['status'])); ?>
                            </span>
                        </p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="application-info-item">
                        <label><i class="bi bi-currency-dollar me-2"></i>Hostel Fees</label>
                        <p>RWF 40,000</p>
                    </div>
                    <div class="application-info-item">
                        <label><i class="bi bi-calendar-check me-2"></i>Application Date</label>
                        <p><?php echo date('F j, Y', strtotime($current_application['created_at'])); ?></p>
                    </div>
                </div>
            </div>

            <!-- Receipt Upload Section -->
            <div class="receipt-upload mt-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-receipt me-2"></i>Payment Receipt</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-4">Please upload your bank payment receipt for RWF 40,000</p>
                        
                        <?php if ($current_application['slep']): ?>
                            <div class="current-receipt mb-4">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="mb-3"><i class="bi bi-file-earmark-text me-2"></i>Current Receipt</h6>
                                        <div class="row align-items-center">
                                            <div class="col-md-8">
                                                <img src="./uploads/receipts/<?php echo htmlspecialchars($current_application['slep']); ?>" 
                                                     class="img-thumbnail" style="max-height: 150px;" 
                                                     alt="Payment Receipt">
                                            </div>
                                            <div class="col-md-4 text-end">
                                                <a href="./uploads/receipts/<?php echo htmlspecialchars($current_application['slep']); ?>" 
                                                   class="btn btn-sm btn-info me-2" target="_blank">
                                                    <i class="bi bi-eye me-1"></i> View Full
                                                </a>
                                                <form action="delete_receipt.php" method="POST" class="d-inline">
                                                    <input type="hidden" name="application_id" value="<?php echo $current_application['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" 
                                                            onclick="return confirm('Are you sure you want to delete this receipt?')">
                                                        <i class="bi bi-trash me-1"></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <form action="upload_receipt.php" method="POST" enctype="multipart/form-data" class="mt-3">
                            <input type="hidden" name="application_id" value="<?php echo $current_application['id']; ?>">
                            <div class="mb-3">
                                <label for="receipt" class="form-label">
                                    <i class="bi bi-upload me-2"></i>Upload New Receipt
                                </label>
                                <input type="file" class="form-control" id="receipt" name="receipt" 
                                       accept="image/*,.pdf" required>
                                <small class="text-muted">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Accepted formats: JPG, PNG, PDF (Max size: 2MB)
                                </small>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-cloud-upload me-2"></i>
                                <?php echo $current_application['slep'] ? 'Update Receipt' : 'Upload Receipt'; ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Roommates Section -->
            <div class="roommates-section mt-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-people me-2"></i>Your Roommates</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $roommates_query = "SELECT s.*, a.status 
                                          FROM applications a
                                          JOIN info s ON a.regnumber = s.regnumber
                                          WHERE a.room_id = ? AND a.regnumber != ?";
                        $roommates_stmt = $connection->prepare($roommates_query);
                        $roommates_stmt->bind_param("is", $current_application['room_id'], $_SESSION['student_regnumber']);
                        $roommates_stmt->execute();
                        $roommates = $roommates_stmt->get_result();
                        
                        if ($roommates->num_rows > 0):
                            while ($roommate = $roommates->fetch_assoc()):
                        ?>
                            <div class="roommate-card">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h6 class="mb-1">
                                            <i class="bi bi-person me-2"></i>
                                            <?php echo htmlspecialchars($roommate['names']); ?>
                                        </h6>
                                        <p class="text-muted mb-0">
                                            <i class="bi bi-card-text me-2"></i>
                                            <?php echo htmlspecialchars($roommate['regnumber']); ?> | 
                                            <i class="bi bi-building me-2"></i>
                                            <?php echo htmlspecialchars($roommate['college']); ?> | 
                                            <i class="bi bi-mortarboard me-2"></i>
                                            Year <?php echo htmlspecialchars($roommate['yearofstudy']); ?>
                                            <i class="bi bi-phone me-2"></i>
                                            <?php echo htmlspecialchars($roommate['phone']); ?>
                                        </p>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <span class="badge bg-<?php 
                                            echo $roommate['status'] === 'approved' ? 'success' : 
                                                ($roommate['status'] === 'rejected' ? 'danger' : 'warning'); 
                                        ?>">
                                            <?php echo ucfirst(htmlspecialchars($roommate['status'])); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php 
                            endwhile;
                        else:
                        ?>
                            <div class="text-center py-4">
                                <i class="bi bi-people text-muted" style="font-size: 2rem;"></i>
                                <p class="text-muted mt-2 mb-0">No roommates assigned yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div> 
</div>