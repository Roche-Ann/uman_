<?php
/**
 * Access Denied Page
 */

require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/Helper.php';

$auth = new Auth();

include __DIR__ . '/views/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body text-center p-5">
                    <i class="bi bi-shield-x" style="font-size: 4rem; color: #dc3545;"></i>
                    <h2 class="mt-3">Access Denied</h2>
                    <p class="text-muted">You don't have permission to access this page.</p>
                    <?php
                    if (isset($_SESSION['role'])) {
                        $role = $_SESSION['role'];
                        $dashboardUrl = ($role === 'applicant') ? '/lgu-urban-planning/user/index.php' : '/lgu-urban-planning/admin/index.php';
                    } else {
                        $dashboardUrl = '/lgu-urban-planning/login.php';
                    }
                    ?>
                    <a href="<?php echo $dashboardUrl; ?>" class="btn btn-primary">Go to Dashboard</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/views/footer.php'; ?>

