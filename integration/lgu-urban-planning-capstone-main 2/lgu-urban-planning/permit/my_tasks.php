<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
$auth = new Auth();
$auth->requireRole('inspector'); 

$db = Database::getInstance();
$inspector_id = $_SESSION['user_id'];

$tasks = $db->fetchAll("
    SELECT i.*, a.application_number, a.project_name, a.barangay, a.district, u.last_name AS owner_last_name
    FROM inspections i 
    JOIN applications a ON i.application_id = a.id
    LEFT JOIN users u ON a.applicant_id = u.id
    WHERE i.inspector_id = ? AND i.status = 'scheduled'
    ORDER BY i.scheduled_at ASC", [$inspector_id]);

include __DIR__ . '/../admin/header.php';
?>

<div class="p-4">
    <h2 class="fw-bold"><i class="bi bi-clipboard-check me-2"></i>My Inspection Tasks</h2>
    <p class="text-muted">List of projects assigned to you for field verification.</p>

    <div class="row mt-4">
        <?php if ($tasks): foreach ($tasks as $t): ?>
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card shadow-sm border-0 border-start border-4 border-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <span class="badge bg-primary mb-2">Scheduled</span>
                            <small class="text-muted"><?= date('M d, Y h:i A', strtotime($t['scheduled_at'])) ?></small>
                        </div>
                        <h6 class="fw-bold mb-1">App #<?= $t['application_number'] ?></h6>
                        <p class="small text-secondary mb-1"><?= $t['project_name'] ?> (<?= $t['owner_last_name'] ?>)</p>
                        <?php if (!empty($t['barangay']) || !empty($t['district'])): ?>
                            <p class="small text-muted mb-3">
                                <i class="bi bi-geo-alt me-1"></i>
                                <?= htmlspecialchars(implode(', ', array_filter([$t['barangay'] ?? '', $t['district'] ?? '']))) ?>
                            </p>
                        <?php else: ?>
                            <p class="small text-secondary mb-3"></p>
                        <?php endif; ?>
                        <div class="d-flex gap-2">
                            <a href="/lgu-urban-planning/permit/view.php?id=<?= $t['application_id'] ?>" class="btn btn-outline-primary btn-sm w-50">
                                <i class="bi bi-eye me-1"></i> View Application
                            </a>
                            <a href="/lgu-urban-planning/monitoring/index.php" class="btn btn-primary btn-sm w-50">
                                <i class="bi bi-clipboard2-pulse me-1"></i> Submit Report
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; else: ?>
            <div class="text-center p-5 bg-light rounded">
                <i class="bi bi-emoji-smile fs-1 text-muted"></i>
                <p class="mt-2 text-muted">No pending inspections. Good job!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../admin/footer.php'; ?>