<?php
/**
 * pay.php — Mock payment / checkout page (applicant-facing)
 */

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/payments.php';
require_once __DIR__ . '/issue_permit.php';
require_once __DIR__ . '/receipt_helper.php';

$auth = new Auth();
$auth->requireRole(['applicant']);

$db     = Database::getInstance();
$dbConn = $db->getConnection();

$applicationId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$applicantId   = (int) ($_SESSION['user_id'] ?? 0);
$error         = '';
$paidJustNow   = false;

// ── Available mock payment methods, grouped by category ────────────────────
// Server-side source of truth: whatever the client posts, we look the
// label up in here rather than trusting the posted label text directly.
$paymentMethods = [
    'ewallet' => [
        'label' => 'E-Wallet',
        'icon'  => 'bi-wallet2',
        'blurb' => 'Pay instantly using your e-wallet app.',
        'providers' => [
            'gcash'   => ['label' => 'GCash',   'icon' => 'bi-phone'],
            'maya'    => ['label' => 'Maya',    'icon' => 'bi-phone'],
            'grabpay' => ['label' => 'GrabPay', 'icon' => 'bi-phone'],
        ],
    ],
    'bank' => [
        'label' => 'Online Banking',
        'icon'  => 'bi-bank',
        'blurb' => "You'll be redirected to your bank's online portal.",
        'providers' => [
            'bdo'       => ['label' => 'BDO Online',        'icon' => 'bi-building'],
            'bpi'       => ['label' => 'BPI Online',        'icon' => 'bi-building'],
            'metrobank' => ['label' => 'Metrobank Online',  'icon' => 'bi-building'],
            'unionbank' => ['label' => 'UnionBank Online',  'icon' => 'bi-building'],
            'landbank'  => ['label' => 'Landbank iAccess',  'icon' => 'bi-building'],
        ],
    ],
    'card' => [
        'label' => 'Credit / Debit Card',
        'icon'  => 'bi-credit-card',
        'blurb' => 'Visa, Mastercard, and JCB accepted.',
        'providers' => [
            'visa_mc' => ['label' => 'Visa / Mastercard', 'icon' => 'bi-credit-card-2-front'],
        ],
    ],
    'otc' => [
        'label' => 'Over-the-Counter',
        'icon'  => 'bi-shop',
        'blurb' => 'Pay in cash at a partner outlet using a reference number.',
        'providers' => [
            '7eleven' => ['label' => '7-Eleven (CLiQQ)',   'icon' => 'bi-shop'],
            'bayad'   => ['label' => 'Bayad Center',        'icon' => 'bi-shop'],
            'cebuana' => ['label' => 'Cebuana Lhuillier',   'icon' => 'bi-shop'],
        ],
    ],
];

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

$selectedMethodLabel = '';
$selectedCategoryLabel = '';

// ── Handle "Pay Now" submission ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mock_pay') {

    $postedCategory = (string) ($_POST['payment_category'] ?? '');
    $postedProvider = (string) ($_POST['payment_provider'] ?? '');

    // Validate against the server-side list rather than trusting posted labels.
    if (isset($paymentMethods[$postedCategory]['providers'][$postedProvider])) {
        $selectedCategoryLabel = $paymentMethods[$postedCategory]['label'];
        $selectedMethodLabel   = $paymentMethods[$postedCategory]['providers'][$postedProvider]['label'];
    } else {
        $selectedCategoryLabel = 'Unknown';
        $selectedMethodLabel   = 'Unspecified method';
    }

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

        // Best-effort: record which mock method was used, if the column
        // exists. Safe to ignore if it doesn't — non-critical for the demo.
        try {
            $dbConn->prepare(
                "UPDATE payments SET payment_method = ? WHERE id = ?"
            )->execute([$selectedMethodLabel, $payment['id']]);
        } catch (\PDOException $e) {
            // payments.payment_method column not present — skip silently.
        }

        // Attribute the resulting status-history/message rows to a system
        // account rather than the applicant. Adjust this lookup if your
        // system already has a fixed "system" user id.
        $systemUser = $db->fetchOne("SELECT id FROM users WHERE role IN ('admin','super_admin') ORDER BY id ASC LIMIT 1");
        $officerId  = $systemUser ? (int) $systemUser['id'] : $applicantId;

        $result = issuePermitAndNotifyApplicant(
            $dbConn,
            $db,
            $application,
            'Payment received via ' . $selectedMethodLabel . ' (Ref: ' . $payment['reference_number'] . ', Txn: ' . $transactionId . '). Permit released.',
            $officerId
        );

        if (!$result['success']) {
            $error = $result['message'];
        } else {
            $paidJustNow = true;
            $payment['status']         = 'paid';
            $payment['transaction_id'] = $transactionId;
            $payment['paid_at']        = date('Y-m-d H:i:s');
            $payment['payment_method'] = $selectedMethodLabel;

            // ── Generate + email the official receipt ──
            // A hiccup here shouldn't undo the payment/permit that already
            // succeeded above, so failures are swallowed rather than surfaced
            // as $error. The applicant can still view/download the receipt
            // later from view.php / receipt.php, which will (re)build it on
            // demand if this attempt failed to produce a file.
            try {
                $receiptInfo = buildReceiptPdf($payment, $application);
                sendPaymentReceiptEmail($application, $payment, $receiptInfo['path'], $receiptInfo['filename']);
            } catch (\Throwable $e) {
                // Non-critical for the demo — ignore.
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>UPAD - Permit Fee Payment</title>
    <link rel="icon" type="image/x-icon" href="/lgu-urban-planning/assets/upad-logo.png" />
    <script>
        (function () {
            var stored = localStorage.getItem('theme');
            var theme  = stored || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-bs-theme', theme);
        })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        [data-bs-theme="dark"] body.bg-light { background-color: #0f172a !important; }
        [data-bs-theme="dark"] .card-header.bg-white { background-color: #1e293b !important; }
        [data-bs-theme="dark"] .method-card { background-color: #1e293b !important; border-color: #334155 !important; }
        [data-bs-theme="dark"] .provider-chip { background-color: #1e293b !important; border-color: #334155 !important; color: #e2e8f0 !important; }
        [data-bs-theme="dark"] .detail-panel { background-color: #16213a !important; border-color: #334155 !important; }

        /* =============================================
           PERMIT FEE PAYMENT PAGE
           Fully responsive — 1024px | 768px | 480px | 320px
           ============================================= */

        .pay-page-container {
            max-width: 640px;
        }

        .payment-icon {
            font-size: 3rem;
        }

        .step { display: none; }
        .step.active { display: block; }

        .method-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }

        .method-card {
            border: 1px solid #dee2e6;
            border-radius: 0.6rem;
            padding: 1rem 0.75rem;
            text-align: center;
            cursor: pointer;
            background: #fff;
            transition: border-color .15s ease, box-shadow .15s ease, transform .1s ease;
        }
        .method-card:hover { border-color: #86b7fe; transform: translateY(-1px); }
        .method-card.selected {
            border-color: #0d6efd;
            box-shadow: 0 0 0 2px rgba(13,110,253,.25);
        }
        .method-card i { font-size: 1.6rem; display: block; margin-bottom: 0.4rem; }
        .method-card .method-name { font-weight: 600; font-size: 0.92rem; }
        .method-card .method-blurb { font-size: 0.74rem; color: #6c757d; margin-top: 0.15rem; }

        .provider-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .provider-chip {
            border: 1px solid #dee2e6;
            border-radius: 2rem;
            padding: 0.4rem 0.9rem;
            font-size: 0.85rem;
            cursor: pointer;
            background: #fff;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        .provider-chip.selected {
            border-color: #0d6efd;
            background: #0d6efd;
            color: #fff;
        }

        .detail-panel {
            background: #f8f9fa;
            border: 1px solid #eee;
            border-radius: 0.6rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .back-link { font-size: 0.85rem; cursor: pointer; }

        /* ── 1024px – Laptop ── */
        @media (max-width: 1024px) {
            .pay-page-container {
                padding-top: 3rem !important;
                padding-bottom: 3rem !important;
            }

            .method-card { padding: 0.9rem 0.7rem; }
            .detail-panel { padding: 0.95rem; }
        }

        /* ── 768px – Tablet ── */
        @media (max-width: 768px) {
            .pay-page-container {
                padding-top: 2.5rem !important;
                padding-bottom: 2.5rem !important;
                padding-left: 1.25rem;
                padding-right: 1.25rem;
            }

            .payment-icon { font-size: 2.6rem; }
            .card-body.p-4 { padding: 1.5rem !important; }
            .card-header h5 { font-size: 1.05rem; }
            .pay-table td { font-size: 0.92rem; }
            .pay-amount { font-size: 1.1rem !important; }

            .method-grid { gap: 0.6rem; }
            .method-card { padding: 0.85rem 0.6rem; }
            .method-card i { font-size: 1.45rem; }
            .method-card .method-blurb { font-size: 0.7rem; }
            .provider-chip { font-size: 0.82rem; padding: 0.38rem 0.8rem; }
            .detail-panel { padding: 0.9rem; }
            .back-link { font-size: 0.8rem; }
        }

        /* ── 480px – Large Mobile ── */
        @media (max-width: 480px) {
            .pay-page-container {
                padding-top: 1.75rem !important;
                padding-bottom: 1.75rem !important;
                padding-left: 1rem;
                padding-right: 1rem;
            }

            .payment-icon { font-size: 2.2rem; }
            .card-body.text-center.p-4 { padding: 1.5rem 1.1rem !important; }
            .card-body.p-4 { padding: 1.25rem !important; }
            .card-header { padding: 0.85rem 1.1rem; }
            .card-header h5 { font-size: 0.98rem; }
            .card-header h5 i { margin-right: 0.4rem !important; }
            h4.fw-bold { font-size: 1.15rem; }
            .pay-table { margin-bottom: 1.25rem !important; }
            .pay-table td { font-size: 0.86rem; padding: 0.5rem 0; }
            .pay-amount { font-size: 1rem !important; }
            #payBtn { font-size: 0.92rem; padding: 0.65rem !important; }
            .method-card i { font-size: 1.35rem; }
            .method-card .method-name { font-size: 0.85rem; }
            .method-card .method-blurb { display: none; }

            .method-grid { gap: 0.5rem; }
            .method-card { padding: 0.75rem 0.5rem; border-radius: 0.5rem; }
            .method-card i { margin-bottom: 0.25rem; }

            .provider-row { gap: 0.4rem; margin-bottom: 0.75rem; }
            .provider-chip { font-size: 0.78rem; padding: 0.35rem 0.7rem; }
            .provider-chip i { font-size: 0.85rem; }

            .detail-panel { padding: 0.85rem; border-radius: 0.5rem; }
            .detail-panel .form-label { font-size: 0.78rem; }
            .detail-panel .form-text { font-size: 0.72rem; }
            .detail-panel p, .detail-panel p.small { font-size: 0.82rem; }
            .detail-panel input.form-control-sm { font-size: 0.85rem; padding: 0.4rem 0.6rem; }
            .back-link { font-size: 0.78rem; }

            .success-actions { flex-direction: column; }
            .success-actions .btn { width: 100%; font-size: 0.85rem; padding: 0.6rem !important; }
        }

        /* ── 320px – Small Mobile ── */
        @media (max-width: 320px) {
            .pay-page-container {
                padding-top: 1.25rem !important;
                padding-bottom: 1.25rem !important;
                padding-left: 0.75rem;
                padding-right: 0.75rem;
            }

            .payment-icon { font-size: 1.9rem; }
            .card-body.text-center.p-4 { padding: 1.25rem 0.85rem !important; }
            .card-body.p-4 { padding: 1rem !important; }
            .card-header { padding: 0.7rem 0.9rem; }
            .card-header h5 { font-size: 0.86rem; }
            h4.fw-bold { font-size: 1.02rem; }
            p.text-muted, p.mt-3 { font-size: 0.8rem; }
            .pay-table td { font-size: 0.78rem; padding: 0.4rem 0; }
            .pay-amount { font-size: 0.92rem !important; }
            #payBtn, .btn-success.mt-2 { font-size: 0.85rem; padding: 0.6rem !important; }
            .method-grid { grid-template-columns: 1fr; gap: 0.5rem; }
            .method-card {
                display: flex;
                align-items: center;
                gap: 0.6rem;
                text-align: left;
                padding: 0.6rem 0.75rem;
            }
            .method-card i { font-size: 1.2rem; margin-bottom: 0; }
            .method-card .method-name { font-size: 0.82rem; }

            .provider-chip { font-size: 0.72rem; padding: 0.3rem 0.6rem; }
            .provider-chip i { font-size: 0.78rem; }

            .detail-panel { padding: 0.7rem; }
            .detail-panel .form-label { font-size: 0.74rem; }
            .detail-panel .form-text { font-size: 0.68rem; }
            .detail-panel p, .detail-panel p.small { font-size: 0.78rem; }
            .detail-panel input.form-control-sm { font-size: 0.8rem; padding: 0.35rem 0.55rem; }

            /* Stack Expiry / CVV under Card Number instead of side-by-side */
            .detail-panel .row.g-2 .col-6 { flex: 0 0 100%; max-width: 100%; }

            .back-link { font-size: 0.74rem; }
            .success-actions .btn { font-size: 0.8rem; padding: 0.55rem !important; }
        }
    </style>
</head>
<body class="bg-light">
<div class="container py-5 pay-page-container">

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($payment['status'] === 'paid'): ?>
        <div class="card border-success shadow-sm">
            <div class="card-body text-center p-4">
                <i class="bi bi-check-circle-fill text-success payment-icon"></i>
                <h4 class="fw-bold mt-3">Payment Successful</h4>
                <p class="text-muted mb-1">Reference No: <?php echo htmlspecialchars($payment['reference_number']); ?></p>
                <p class="text-muted mb-1">Transaction ID: <?php echo htmlspecialchars($payment['transaction_id'] ?? ''); ?></p>
                <?php if ($selectedMethodLabel): ?>
                    <p class="text-muted">Paid via: <?php echo htmlspecialchars($selectedMethodLabel); ?></p>
                <?php endif; ?>
                <p class="mt-3">Your permit has been generated and emailed to your registered email address.</p>
                <p class="text-muted mb-3" style="font-size:0.85rem;">A copy of your official receipt has also been emailed to you.</p>
                <div class="success-actions d-flex flex-wrap justify-content-center gap-2">
                    <a href="/lgu-urban-planning/modules/PermitProcessing/receipt.php?id=<?php echo $applicationId; ?>" target="_blank" class="btn btn-outline-primary">
                        <i class="bi bi-receipt me-1"></i> View Receipt
                    </a>
                    <a href="/lgu-urban-planning/modules/PermitProcessing/receipt.php?id=<?php echo $applicationId; ?>&download=1" class="btn btn-outline-secondary">
                        <i class="bi bi-download me-1"></i> Download Receipt
                    </a>
                    <a href="/lgu-urban-planning/applicant/view.php?id=<?php echo $applicationId; ?>" class="btn btn-success">
                        Back to My Application
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="fw-bold mb-0"><i class="bi bi-credit-card me-2"></i>Permit Fee Payment</h5>
            </div>
            <div class="card-body p-4">
                <table class="table table-borderless mb-4 pay-table">
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
                        <td class="fw-bold text-end fs-5 pay-amount">₱<?php echo number_format((float) $payment['amount'], 2); ?></td>
                    </tr>
                </table>

                <form method="POST" id="payForm">
                    <input type="hidden" name="action" value="mock_pay">
                    <input type="hidden" name="id" value="<?php echo $applicationId; ?>">
                    <input type="hidden" name="payment_category" id="payment_category" value="">
                    <input type="hidden" name="payment_provider" id="payment_provider" value="">

                    <!-- STEP 1: choose a payment category -->
                    <div class="step active" id="step1">
                        <p class="text-muted mb-2" style="font-size:0.88rem;">Choose how you'd like to pay</p>
                        <div class="method-grid mb-2">
                            <?php foreach ($paymentMethods as $catKey => $cat): ?>
                                <div class="method-card" data-category="<?php echo htmlspecialchars($catKey); ?>" onclick="chooseCategory('<?php echo htmlspecialchars($catKey); ?>')">
                                    <i class="bi <?php echo htmlspecialchars($cat['icon']); ?>"></i>
                                    <div class="method-name"><?php echo htmlspecialchars($cat['label']); ?></div>
                                    <div class="method-blurb"><?php echo htmlspecialchars($cat['blurb']); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- STEP 2: one panel per category, JS shows the matching one -->
                    <?php foreach ($paymentMethods as $catKey => $cat): ?>
                        <div class="step" id="step2-<?php echo htmlspecialchars($catKey); ?>">
                            <span class="text-primary back-link" onclick="backToStep1()"><i class="bi bi-arrow-left"></i> Change payment method</span>

                            <p class="mt-3 mb-2 fw-semibold"><i class="bi <?php echo htmlspecialchars($cat['icon']); ?> me-1"></i><?php echo htmlspecialchars($cat['label']); ?></p>

                            <div class="provider-row">
                                <?php foreach ($cat['providers'] as $provKey => $prov): ?>
                                    <span class="provider-chip"
                                          data-category="<?php echo htmlspecialchars($catKey); ?>"
                                          data-provider="<?php echo htmlspecialchars($provKey); ?>"
                                          onclick="chooseProvider('<?php echo htmlspecialchars($catKey); ?>','<?php echo htmlspecialchars($provKey); ?>')">
                                        <i class="bi <?php echo htmlspecialchars($prov['icon']); ?>"></i> <?php echo htmlspecialchars($prov['label']); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>

                            <div class="detail-panel">
                                <?php if ($catKey === 'ewallet'): ?>
                                    <label class="form-label small text-muted mb-1">Mobile Number</label>
                                    <input type="text" class="form-control form-control-sm" placeholder="09XX XXX XXXX" maxlength="11" pattern="09[0-9]{9}">
                                    <div class="form-text">A payment request will be sent to this number's e-wallet app. (Demo only — nothing is actually sent.)</div>
                                <?php elseif ($catKey === 'bank'): ?>
                                    <p class="mb-1 small">You'll be redirected to your bank's online banking portal to authorize a transfer of <strong>₱<?php echo number_format((float) $payment['amount'], 2); ?></strong>.</p>
                                    <div class="form-text">Demo only — no real bank connection is made.</div>
                                <?php elseif ($catKey === 'card'): ?>
                                    <div class="row g-2">
                                        <div class="col-12">
                                            <label class="form-label small text-muted mb-1">Card Number</label>
                                            <input type="text" class="form-control form-control-sm" placeholder="4000 1234 5678 9010" maxlength="19">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small text-muted mb-1">Expiry</label>
                                            <input type="text" class="form-control form-control-sm" placeholder="MM/YY" maxlength="5">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small text-muted mb-1">CVV</label>
                                            <input type="text" class="form-control form-control-sm" placeholder="123" maxlength="4">
                                        </div>
                                    </div>
                                    <div class="form-text">Demo only — do not enter a real card number. Nothing is stored or transmitted.</div>
                                <?php elseif ($catKey === 'otc'): ?>
                                    <p class="mb-1 small">Present this reference number at the counter and pay <strong>₱<?php echo number_format((float) $payment['amount'], 2); ?></strong> in cash:</p>
                                    <p class="mb-1 fw-bold"><?php echo htmlspecialchars($payment['reference_number']); ?></p>
                                    <div class="form-text">Demo only — instantly marked as paid for this capstone; a real OTC integration would wait for the outlet's confirmation.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <button type="submit" id="payBtn" class="btn btn-primary w-100 py-2 fw-bold" style="display:none;">
                        <i class="bi bi-lock-fill me-1"></i> Pay Now
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>

</div>

<script src="/lgu-urban-planning/assets/js/user-pay.js"></script>
</body>
</html>