<?php
if (!isset($_SESSION['student_id'])) {
    return;
}

// Get loading state from parent
$isLoading = isset($isLoading) ? $isLoading : false;
?>
<div class="container mt-4">
<!-- Bootstrap CSS -->
<!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"> -->

<?php include dirname(__DIR__) . '/includes/studentMenu.php'; ?>
<br>

<!-- Bootstrap JS -->
<!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script> -->

<style>
    /* Skeleton Loading Styles */
    .skeleton {
        background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
        background-size: 200% 100%;
        animation: loading 1.5s infinite;
        border-radius: 4px;
        position: relative;
        overflow: hidden;
    }

    .skeleton::after {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        bottom: 0;
        left: 0;
        transform: translateX(-100%);
        background-image: linear-gradient(
            90deg,
            rgba(255, 255, 255, 0) 0,
            rgba(255, 255, 255, 0.2) 20%,
            rgba(255, 255, 255, 0.5) 60%,
            rgba(255, 255, 255, 0)
        );
        animation: shimmer 2s infinite;
    }

    .skeleton-text {
        height: 1em;
        margin-bottom: 0.5em;
    }

    .skeleton-title {
        height: 2em;
        margin-bottom: 1em;
    }

    .skeleton-label {
        height: 0.8em;
        width: 120px;
        margin-bottom: 0.3em;
    }

    .skeleton-value {
        height: 1.2em;
        width: 180px;
    }

    @keyframes shimmer {
        100% {
            transform: translateX(100%);
        }
    }

    @keyframes loading {
        0% {
            background-position: 200% 0;
        }
        100% {
            background-position: -200% 0;
        }
    }
</style>

<?php if ($isLoading): ?>
    <!-- Skeleton Loading Structure -->
    <div class="student-info-card mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <div class="skeleton skeleton-title w-50"></div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <?php for($i = 0; $i < 3; $i++): ?>
                        <div class="student-info-item mb-3">
                            <div class="skeleton skeleton-label"></div>
                            <div class="skeleton skeleton-value"></div>
                        </div>
                        <?php endfor; ?>
                    </div>
                    <div class="col-md-6">
                        <?php for($i = 0; $i < 4; $i++): ?>
                        <div class="student-info-item mb-3">
                            <div class="skeleton skeleton-label"></div>
                            <div class="skeleton skeleton-value"></div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- Actual Content -->
    <div class="student-info-card mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0 text-white"><i class="bi bi-person-circle text-white"></i> Student Information</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="student-info-item">
                            <label class="text-muted">Full Name:</label>
                            <p class="mb-0"><?php echo htmlspecialchars($_SESSION['student_name']); ?></p>
                        </div>
                        <div class="student-info-item">
                            <label class="text-muted">Registration Number:</label>
                            <p class="mb-0"><?php echo htmlspecialchars($_SESSION['student_regnumber']); ?></p>
                        </div>
                        <div class="student-info-item">
                            <label class="text-muted">Campus:</label>
                            <p class="mb-0"><?php echo htmlspecialchars($_SESSION['student_campus']); ?></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="student-info-item">
                            <label class="text-muted">College:</label>
                            <p class="mb-0"><?php echo htmlspecialchars($_SESSION['student_college']); ?></p>
                        </div>
                        <div class="student-info-item">
                            <label class="text-muted">Program:</label>
                            <p class="mb-0"><?php echo htmlspecialchars($_SESSION['student_program']); ?></p>
                        </div>
                        <div class="student-info-item">
                            <label class="text-muted">Year of Study:</label>
                            <p class="mb-0"><?php echo htmlspecialchars($_SESSION['student_year']); ?></p>
                        </div>
                        <div class="student-info-item">
                            <label class="text-muted">Gender:</label>
                            <p class="mb-0"><?php echo htmlspecialchars($_SESSION['student_gender']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
</div>