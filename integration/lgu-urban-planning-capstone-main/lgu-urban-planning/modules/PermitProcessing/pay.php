<?php
/**
 * pay.php — Mock payment / checkout page (applicant-facing)
 *
 * Built for a capstone demo: NOT a real payment gateway. "Pay Now" simply
 * simulates a short processing delay, then marks the payment as paid and
 * triggers permit issuance (PDF + email) via issuePermitAndNotifyApplicant().
 *
 * NOTE: adjust the role check below (and $_SESSION key names) to match
 * however the applicant portal actually authenticates — this was written
 * without visibility into that file. Everything else (DB/table names,
 * PERMIT_FEE_AMOUNT, issuePermitAndNotifyApplicant) matches what already
 * exists in permit/view.php and config/config.php.
 */

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/payments.php';
require_once __DIR__ . '/issue_permit.php';

$auth = new Auth();
$auth->requireRole(['applicant']); // adjust to match the applicant portal's actual role name

$db     = Database::getInstance();
$dbConn = $db->getConnection();

$applicationId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$applicantId   = (int) ($_SESSION['user_id'] ?? 0);
$error         = '';
$paidJustNow   = false;

// ── Load application, scoped to the logged-in applicant only ───────────────
$application = $db->fetchOne(
    "SELECT a.*, u.email AS applicant_email, u.first_name AS applicant_first_name, u.last_name AS applicant_last_name
     FROM applications a
     JOIN users u ON u.id = a.applicant_id
     WHERE a.id = ? AND a.applicant_id = ?",
    [$applicationId, $applicantId]
);

if (!$application) {
    die('Application not found.');
}

if ($application['status'] !== 'pending_payment') {
    die('This application does not currently have a pending payment.');
}

// ── Load (or create, as a safety net) the unpaid payment row ───────────────
$payment = createPendingPayment($dbConn, $applicationId, (float) PERMIT_FEE_AMOUNT);

// ── Handle "Pay Now" submission ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mock_pay') {

    if ($payment['status'] === 'paid') {
        // Already paid (e.g. double submit) — nothing to do.
        $paidJustNow = false;
    } else {
        // Simulate gateway processing time so the demo feels real.
        sleep(2);

        $transactionId = generateMockTransactionId();

        $dbConn->prepare(
            "UPDATE payments SET status = 'paid', transaction_id = ?, paid_at = NOW() WHERE id = ?"
        )->execute([$transactionId, $payment['id']]);

        // Attribute the resulting status-history/message rows to a system
        // account rather than the applicant. Adjust this lookup if your
        // system already has a fixed "system" user id.
        $systemUser = $db->fetchOne("SELECT id FROM users WHERE role IN ('admin','super_admin') ORDER BY id ASC LIMIT 1");
        $officerId  = $systemUser ? (int) $systemUser['id'] : $applicantId;

        $result = issuePermitAndNotifyApplicant(
            $dbConn,
            $db,
            $application,
            'Payment received (Ref: ' . $payment['reference_number'] . ', Txn: ' . $transactionId . '). Permit released.',
            $officerId
        );

        if (!$result['success']) {
            $error = $result['message'];
        } else {
            $paidJustNow = true;
            $payment['status']         = 'paid';
            $payment['transaction_id'] = $transactionId;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Permit Fee Payment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width: 560px;">

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($payment['status'] === 'paid'): ?>
        <div class="card border-success shadow-sm">
            <div class="card-body text-center p-4">
                <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                <h4 class="fw-bold mt-3">Payment Successful</h4>
                <p class="text-muted mb-1">Reference No: <?php echo htmlspecialchars($payment['reference_number']); ?></p>
                <p class="text-muted">Transaction ID: <?php echo htmlspecialchars($payment['transaction_id'] ?? ''); ?></p>
                <p class="mt-3">Your permit has been generated and emailed to your registered email address.</p>
                <a href="/lgu-urban-planning/applicant/view.php?id=<?php echo $applicationId; ?>" class="btn btn-success mt-2">
                    Back to My Application
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="fw-bold mb-0"><i class="bi bi-credit-card me-2"></i>Permit Fee Payment</h5>
            </div>
            <div class="card-body p-4">
                <table class="table table-borderless mb-4">
                    <tr>
                        <td class="text-muted">Application No.</td>
                        <td class="fw-bold text-end"><?php echo htmlspecialchars($application['application_number']); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Reference No.</td>
                        <td class="fw-bold text-end"><?php echo htmlspecialchars($payment['reference_number']); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Amount Due</td>
                        <td class="fw-bold text-end fs-5">₱<?php echo number_format((float) $payment['amount'], 2); ?></td>
                    </tr>
                </table>

                <form method="POST" id="payForm">
                    <input type="hidden" name="action" value="mock_pay">
                    <input type="hidden" name="id" value="<?php echo $applicationId; ?>">
                    <button type="submit" id="payBtn" class="btn btn-primary w-100 py-2 fw-bold">
                        <i class="bi bi-lock-fill me-1"></i> Pay Now
                    </button>
                </form>
                <p class="text-muted small mt-3 mb-0 text-center">
                    This is a demo checkout — no real payment is processed.
                </p>
            </div>
        </div>
    <?php endif; ?>

</div>

<script>
    // Simple loading state so the simulated server-side delay feels intentional.
    var form = document.getElementById('payForm');
    if (form) {
        form.addEventListener('submit', function () {
            var btn = document.getElementById('payBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Processing payment...';
        });
    }
</script>
</body>
</html>