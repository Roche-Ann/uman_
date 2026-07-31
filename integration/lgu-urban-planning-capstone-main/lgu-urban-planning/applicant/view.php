<?php
/**
 * View Application Details (Applicant)
 */

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helper.php';
require_once __DIR__ . '/../modules/ApplicantSelfService/ApplicantController.php';

$auth = new Auth();
$auth->requireRole('applicant');

$db = Database::getInstance();
$applicantController = new ApplicantController();
$applicationId = $_GET['id'] ?? 0;
$application = $applicantController->getApplicationDetails($applicationId);

if (!$application) {
    header('Location: /lgu-urban-planning/applicant/applications.php');
    exit;
}

// Payment record (if this application has ever had a permit fee generated) —
// used to show the "Pay Now" card below when status is 'pending_payment'.
$paymentRecord = $db->fetchOne("SELECT * FROM payments WHERE application_id = ? ORDER BY id DESC LIMIT 1", [$applicationId]);

// ── i18n — reads language saved by settings.php ──────────────────────────────
$_vwLang = $_SESSION['locale_language'] ?? 'en_PH';

$_vwT = [
    'en_PH' => [
        'page_title'        => 'Application Details',
        'heading'           => 'Application Details',
        // Progress tracker
        'card_progress'     => 'Application Progress',
        'step_submitted'    => 'Submitted',
        'step_review'       => 'Under Review',
        'step_inspection'   => 'Site Inspection',
        'step_approved'     => 'Approved',
        'step_payment'      => 'Payment',
        'step_released'     => 'Permit Released',
        'note_rejected'     => 'This application was not approved. See status history below for details.',
        // Project info
        'sec_project'       => 'Project Information',
        'lbl_project_name'  => 'Project Name',
        'lbl_project_type'  => 'Project Type',
        'lbl_description'   => 'Description',
        // Location info
        'sec_location'      => 'Location Information',
        'lbl_lot_number'    => 'Lot Number',
        'lbl_block'         => 'Block Number',
        'lbl_street'        => 'Street',
        'lbl_barangay'      => 'Barangay',
        'lbl_district'      => 'District',
        'lbl_parcel_id'     => 'Parcel ID',
        'lbl_coordinates'   => 'Coordinates',
        // Zoning compliance
        'sec_zoning'        => 'Zoning Compliance',
        'lbl_status'        => 'Status',
        // Documents card
        'card_documents'    => 'Documents',
        'no_documents'      => 'No documents uploaded yet.',
        'col_doc_type'      => 'Document Type',
        'col_file_name'     => 'File Name',
        'col_uploaded'      => 'Uploaded',
        'col_action'        => 'Action',
        'btn_download'      => 'Download',
        'btn_view'          => 'View',
        // Status history card
        'card_history'      => 'Status History',
        'no_remarks'        => 'No remarks',
        'lbl_by'            => 'by',
        // Back button
        'btn_back'          => 'Back to Applications',
        // Payment card
        'card_payment'      => 'Permit Fee Payment',
        'pay_intro'         => 'Your application has passed Final Approval. Please settle the permit fee below to receive your Locational Clearance / Permit.',
        'pay_reference'     => 'Reference No.',
        'pay_amount'        => 'Amount Due',
        'btn_pay_now'        => 'Pay Now',
        'pay_paid_note'     => 'Payment received. Your permit has been emailed to your registered email address.',
    ],
    'fil' => [
        'page_title'        => 'Mga Detalye ng Aplikasyon',
        'heading'           => 'Mga Detalye ng Aplikasyon',
        // Progress tracker
        'card_progress'     => 'Progreso ng Aplikasyon',
        'step_submitted'    => 'Naisumite',
        'step_review'       => 'Sinusuri',
        'step_inspection'   => 'Inspeksyon sa Site',
        'step_approved'     => 'Naaprubahan',
        'step_payment'      => 'Bayad',
        'step_released'     => 'Inilabas ang Permit',
        'note_rejected'     => 'Hindi naaprubahan ang aplikasyong ito. Tingnan ang kasaysayan ng katayuan sa ibaba para sa detalye.',
        // Project info
        'sec_project'       => 'Impormasyon ng Proyekto',
        'lbl_project_name'  => 'Pangalan ng Proyekto',
        'lbl_project_type'  => 'Uri ng Proyekto',
        'lbl_description'   => 'Paglalarawan',
        // Location info
        'sec_location'      => 'Impormasyon ng Lokasyon',
        'lbl_lot_number'    => 'Numero ng Lote',
        'lbl_block'         => 'Numero ng Bloke',
        'lbl_street'        => 'Kalye',
        'lbl_barangay'      => 'Barangay',
        'lbl_district'      => 'Distrito',
        'lbl_parcel_id'     => 'ID ng Parsela',
        'lbl_coordinates'   => 'Koordinada',
        // Zoning compliance
        'sec_zoning'        => 'Pagsunod sa Zoning',
        'lbl_status'        => 'Katayuan',
        // Documents card
        'card_documents'    => 'Mga Dokumento',
        'no_documents'      => 'Wala pang mga dokumentong na-upload.',
        'col_doc_type'      => 'Uri ng Dokumento',
        'col_file_name'     => 'Pangalan ng File',
        'col_uploaded'      => 'Ini-upload',
        'col_action'        => 'Aksyon',
        'btn_download'      => 'I-download',
        'btn_view'          => 'Tingnan',
        // Status history card
        'card_history'      => 'Kasaysayan ng Katayuan',
        'no_remarks'        => 'Walang mga komento',
        'lbl_by'            => 'ni',
        // Back button
        'btn_back'          => 'Bumalik sa mga Aplikasyon',
        // Payment card
        'card_payment'      => 'Bayad sa Permit Fee',
        'pay_intro'         => 'Naaprubahan na ang inyong aplikasyon. Mangyaring bayaran ang permit fee sa ibaba para matanggap ang inyong Locational Clearance / Permit.',
        'pay_reference'     => 'Reference No.',
        'pay_amount'        => 'Halagang Babayaran',
        'btn_pay_now'        => 'Magbayad Ngayon',
        'pay_paid_note'     => 'Natanggap na ang bayad. Ang inyong permit ay naipadala na sa inyong nakarehistrong email.',
    ],
];

function _vwt(string $key): string {
    global $_vwT, $_vwLang;
    return $_vwT[$_vwLang][$key] ?? $_vwT['en_PH'][$key] ?? $key;
}

$pageTitle = _vwt('page_title');
$isAuthPage = true;
include __DIR__ . '/../user/header.php';
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<?php
// ── Application progress tracker ──────────────────────────────────────────────
// This view only has the free-text `status` string to work with, so the step
// is inferred by keyword. If a numeric workflow-stage field becomes available
// on $application, swap this block to read it directly instead of guessing.
$_trkStatus = strtolower($application['status'] ?? '');

$_trkSteps = [
    'submitted'  => _vwt('step_submitted'),
    'review'     => _vwt('step_review'),
    'inspection' => _vwt('step_inspection'),
    'approved'   => _vwt('step_approved'),
    'payment'    => _vwt('step_payment'),
    'released'   => _vwt('step_released'),
];
$_trkKeys = array_keys($_trkSteps);

$_trkRejected = (bool) preg_match('/reject|denied|declin|cancel/', $_trkStatus);

if (preg_match('/releas|complet|issued|claimed/', $_trkStatus)) {
    $_trkCurrent = 5;
} elseif ($_trkStatus === 'approved') {
    // In the new payment-gated flow, 'approved' is only ever set *after*
    // payment succeeds and the permit has already been generated/emailed
    // (see issuePermitAndNotifyApplicant()) — so it's equivalent to the
    // final "Permit Released" step, not just "Approved".
    $_trkCurrent = 5;
} elseif ($_trkStatus === 'pending_payment') {
    // Final Approval has already been decided (step 3 done) — the
    // applicant is now actively on the Payment step.
    $_trkCurrent = 4;
} elseif (preg_match('/inspect|site.?visit|compliance/', $_trkStatus)) {
    $_trkCurrent = 2;
} elseif (preg_match('/review|process|evaluat/', $_trkStatus)) {
    $_trkCurrent = 1;
} else {
    $_trkCurrent = 0; // submitted / pending / default
}
?>

<style>
/* =============================================
   APPLICATION DETAILS PAGE — MODERN CIVIC THEME
   Breakpoints: 1024px | 768px | 480px | 320px
   ============================================= */

.app-details-wrapper {
    --ap-navy:        #16324F;
    --ap-navy-deep:   #0F2438;
    --ap-navy-tint:   #EAF0F5;
    --ap-gold:        #A9812F;
    --ap-gold-tint:   #F6EFDE;
    --ap-bg:          #F6F7F9;
    --ap-surface:     #FFFFFF;
    --ap-border:      #E2E6EC;
    --ap-text:        #1C2733;
    --ap-text-muted:  #667085;
    --ap-danger:      #B3261E;
    --ap-danger-tint: #FBEAE9;

    padding: 1.5rem;
    max-width: 960px;
    margin: 0 auto;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    color: var(--ap-text);
    box-sizing: border-box;
}

.app-details-wrapper *,
.app-details-wrapper *::before,
.app-details-wrapper *::after {
    box-sizing: border-box;
}

.app-details-wrapper h2 {
    font-size: 1.55rem;
    font-weight: 800;
    letter-spacing: -0.015em;
    color: var(--ap-navy-deep);
}

/* --- Cards --- */
.app-details-wrapper .card {
    background: var(--ap-surface);
    border: 1px solid var(--ap-border);
    border-radius: 12px;
    box-shadow: 0 1px 2px rgba(16,24,40,.04);
}

.app-details-wrapper .card-header {
    background: var(--ap-surface);
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--ap-border);
}

.app-details-wrapper .card-header h5 {
    font-size: 1rem;
    font-weight: 700;
    margin: 0;
    color: var(--ap-navy-deep);
    letter-spacing: -0.01em;
}

.app-details-wrapper .card-body {
    padding: 1.5rem;
}

/* Card header: keep badge inline with title */
.app-details-wrapper .card-header.d-flex {
    flex-wrap: wrap;
    gap: 0.5rem;
}

.app-details-wrapper .card-body h6 {
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--ap-gold);
    margin-top: 0;
    margin-bottom: 0.9rem;
}

.app-details-wrapper .card-body hr {
    border-color: var(--ap-border);
    opacity: 1;
    margin: 1.25rem 0;
}

.app-details-wrapper .card-body p {
    margin-bottom: 0.65rem;
    font-size: 0.92rem;
}

.app-details-wrapper .card-body p strong {
    color: var(--ap-text-muted);
    font-weight: 600;
    margin-right: 0.3rem;
}

.app-details-wrapper .card-body pre {
    background: var(--ap-bg) !important;
    border: 1px solid var(--ap-border);
    border-radius: 8px;
    color: var(--ap-text);
    font-size: 0.85rem;
}

/* --- Badges --- */
.app-details-wrapper .badge {
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    padding: 0.4em 0.85em;
    border-radius: 999px;
}

/* --- Documents table --- */
.app-details-wrapper .table-responsive-wrapper {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.app-details-wrapper .table-responsive-wrapper .table {
    min-width: 480px;
}

.app-details-wrapper .table {
    font-size: 0.88rem;
    margin: 0;
}

.app-details-wrapper .table thead th {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--ap-text-muted);
    background: var(--ap-navy-tint);
    border-bottom: 1px solid var(--ap-border);
    padding: 0.75rem 1rem;
}

.app-details-wrapper .table tbody td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--ap-border);
    vertical-align: middle;
}

.app-details-wrapper .table tbody tr:last-child td {
    border-bottom: none;
}

.app-details-wrapper .btn {
    border-radius: 7px;
    font-weight: 600;
}

.app-details-wrapper .btn-primary,
.app-details-wrapper .btn-secondary {
    background: var(--ap-navy);
    border-color: var(--ap-navy);
}

.app-details-wrapper .btn-primary:hover,
.app-details-wrapper .btn-primary:focus,
.app-details-wrapper .btn-secondary:hover,
.app-details-wrapper .btn-secondary:focus {
    background: var(--ap-navy-deep);
    border-color: var(--ap-navy-deep);
}

.app-details-wrapper .btn-outline-primary {
    color: var(--ap-navy);
    border-color: var(--ap-border);
}

.app-details-wrapper .btn-outline-primary:hover,
.app-details-wrapper .btn-outline-primary:focus {
    background: var(--ap-navy-tint);
    border-color: var(--ap-navy);
    color: var(--ap-navy-deep);
}

.app-details-wrapper > .btn-secondary {
    margin-top: 0.25rem;
}

/* --- Timeline (status history) --- */
.app-details-wrapper .timeline .history-item {
    border-color: var(--ap-border) !important;
    border-left-width: 3px !important;
}

.app-details-wrapper .timeline strong {
    color: var(--ap-navy-deep);
    font-size: 0.95rem;
}

.app-details-wrapper .timeline .border-start {
    word-break: break-word;
}

.app-details-wrapper .timeline p {
    color: var(--ap-text);
    font-size: 0.88rem;
}

.app-details-wrapper .timeline small {
    color: var(--ap-text-muted);
}

/* ── Status History Pagination ── */
#historyPagination {
    border-top: 1px solid var(--ap-border);
    padding-top: 0.75rem;
}

/* =============================================
   PROGRESS TRACKER
   ============================================= */
.tracker-card .card-body {
    padding: 1.5rem 1.75rem 1.25rem;
}

.tracker-title {
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--ap-gold);
    margin-bottom: 1.25rem;
}

.status-tracker {
    display: flex;
    align-items: flex-start;
    width: 100%;
}

.st-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 0 0 auto;
    width: 96px;
    text-align: center;
}

.st-circle {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.85rem;
    border: 2px solid var(--ap-border);
    background: var(--ap-surface);
    color: var(--ap-text-muted);
    flex: none;
    transition: background-color .15s ease, border-color .15s ease, color .15s ease;
}

.st-label {
    margin-top: 0.55rem;
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--ap-text-muted);
    line-height: 1.25;
}

.st-connector {
    flex: 1 1 auto;
    height: 2px;
    min-width: 12px;
    background: var(--ap-border);
    margin-top: 17px;
}

.st-connector.st-done {
    background: var(--ap-navy);
}

.st-step.st-done .st-circle {
    background: var(--ap-navy);
    border-color: var(--ap-navy);
    color: #fff;
}

.st-step.st-done .st-label {
    color: var(--ap-navy-deep);
}

.st-step.st-current .st-circle {
    background: var(--ap-surface);
    border-color: var(--ap-gold);
    color: var(--ap-gold);
    box-shadow: 0 0 0 4px var(--ap-gold-tint);
}

.st-step.st-current .st-label {
    color: var(--ap-navy-deep);
    font-weight: 700;
}

.st-step.st-rejected .st-circle {
    background: var(--ap-danger);
    border-color: var(--ap-danger);
    color: #fff;
}

.st-step.st-rejected .st-label {
    color: var(--ap-danger);
    font-weight: 700;
}

.tracker-rejected-note {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin: 1.1rem 0 0;
    padding: 0.6rem 0.85rem;
    background: var(--ap-danger-tint);
    border: 1px solid rgba(179,38,30,.25);
    border-radius: 8px;
    color: var(--ap-danger);
    font-size: 0.82rem;
    font-weight: 500;
}

/* ── 768px – Tablets ── */
/* ── 1024px – Laptop ── */
@media (max-width: 1024px) {
    .app-details-wrapper {
        padding: 1.25rem;
    }

    .app-details-wrapper h2 {
        font-size: 1.4rem;
    }

    .app-details-wrapper .card-body {
        padding: 1.25rem;
    }

    .app-details-wrapper .table thead th,
    .app-details-wrapper .table tbody td {
        padding: 0.65rem 0.85rem;
    }
}

@media (max-width: 768px) {
    .app-details-wrapper {
        padding: 1rem;
    }

    .app-details-wrapper h2 {
        font-size: 1.3rem;
        margin-bottom: 1rem !important;
    }

    .app-details-wrapper .card-header h5 {
        font-size: 0.95rem;
    }

    .app-details-wrapper .card-body {
        padding: 1.1rem;
    }

    .app-details-wrapper .card-body h6 {
        font-size: 0.72rem;
    }

    .app-details-wrapper .card-body p,
    .app-details-wrapper .card-body pre,
    .app-details-wrapper .card-body small {
        font-size: 0.88rem;
    }

    /* Stack badge below title if needed */
    .app-details-wrapper .card-header.d-flex {
        flex-direction: column;
        align-items: flex-start !important;
    }

    .app-details-wrapper .badge {
        align-self: flex-start;
    }

    /* Tighten table cells */
    .app-details-wrapper .table th,
    .app-details-wrapper .table td {
        font-size: 0.83rem;
        padding: 0.55rem 0.7rem;
    }

    .app-details-wrapper .btn-sm {
        font-size: 0.78rem;
        padding: 0.3rem 0.6rem;
    }

    .app-details-wrapper .btn-secondary {
        width: 100%;
        text-align: center;
    }

    .st-step { width: 78px; }
    .st-label { font-size: 0.68rem; }
}

/* ── 480px – Large Mobile ── */
@media (max-width: 480px) {
    .app-details-wrapper {
        padding: 0.75rem;
    }

    .app-details-wrapper h2 {
        font-size: 1.15rem;
    }

    .app-details-wrapper .card {
        border-radius: 10px;
    }

    .app-details-wrapper .card-header {
        padding: 0.7rem 0.9rem;
    }

    .app-details-wrapper .card-header h5 {
        font-size: 0.9rem;
        margin-bottom: 0;
    }

    .app-details-wrapper .card-body {
        padding: 0.9rem;
    }

    .app-details-wrapper .card-body h6 {
        font-size: 0.7rem;
        margin-top: 0.4rem;
    }

    .app-details-wrapper .card-body p {
        font-size: 0.85rem;
        margin-bottom: 0.5rem;
    }

    .app-details-wrapper .card-body pre {
        font-size: 0.78rem;
        white-space: pre-wrap;
        word-break: break-word;
    }

    .app-details-wrapper .table th,
    .app-details-wrapper .table td {
        font-size: 0.78rem;
        padding: 0.45rem 0.5rem;
    }

    /* Timeline */
    .app-details-wrapper .timeline .border-start {
        padding-left: 0.6rem !important;
        border-left-width: 2px !important;
    }

    .app-details-wrapper .timeline strong {
        font-size: 0.85rem;
    }

    .app-details-wrapper .timeline p {
        font-size: 0.82rem;
    }

    .app-details-wrapper .timeline small {
        font-size: 0.75rem;
    }

    .app-details-wrapper .btn-secondary {
        font-size: 0.85rem;
        padding: 0.55rem 1rem;
    }

    /* Progress tracker → vertical list */
    .tracker-card .card-body {
        padding: 1.1rem 1.1rem 0.9rem;
    }

    .tracker-title {
        margin-bottom: 1rem;
    }

    .status-tracker {
        flex-direction: column;
        align-items: flex-start;
    }

    .st-step {
        flex-direction: row;
        align-items: center;
        width: 100%;
        text-align: left;
        gap: 0.75rem;
    }

    .st-label {
        margin-top: 0;
    }

    .st-connector {
        width: 2px;
        height: 18px;
        min-width: 2px;
        margin: 0 0 0 16px;
    }
}

/* ── 320px – Small Mobile ── */
@media (max-width: 320px) {
    .app-details-wrapper {
        padding: 0.5rem;
    }

    .app-details-wrapper h2 {
        font-size: 1rem;
    }

    .app-details-wrapper .card-header {
        padding: 0.55rem 0.7rem;
    }

    .app-details-wrapper .card-header h5 {
        font-size: 0.82rem;
    }

    .app-details-wrapper .card-body {
        padding: 0.7rem;
    }

    .app-details-wrapper .card-body h6 {
        font-size: 0.68rem;
    }

    .app-details-wrapper .card-body p,
    .app-details-wrapper .card-body p strong {
        font-size: 0.78rem;
    }

    .app-details-wrapper .card-body pre {
        font-size: 0.72rem;
        padding: 0.5rem !important;
    }

    .app-details-wrapper .badge {
        font-size: 0.68rem;
        padding: 0.3em 0.6em;
    }

    /* Fully wrap table text */
    .app-details-wrapper .table-responsive-wrapper .table {
        min-width: 300px;
    }

    .app-details-wrapper .table th,
    .app-details-wrapper .table td {
        font-size: 0.72rem;
        padding: 0.35rem 0.4rem;
        white-space: normal;
        word-break: break-word;
    }

    .app-details-wrapper .btn-sm {
        font-size: 0.7rem;
        padding: 0.25rem 0.5rem;
    }

    .app-details-wrapper .btn-secondary {
        font-size: 0.8rem;
        padding: 0.5rem 0.75rem;
    }

    .app-details-wrapper .timeline .border-start {
        padding-left: 0.5rem !important;
    }

    .app-details-wrapper .timeline strong {
        font-size: 0.8rem;
    }

    .app-details-wrapper .timeline p,
    .app-details-wrapper .timeline small {
        font-size: 0.72rem;
    }

    .st-circle {
        width: 28px;
        height: 28px;
        font-size: 0.75rem;
    }

    .st-label {
        font-size: 0.72rem;
    }

    .st-connector {
        margin-left: 13px;
    }
}
/* =============================================
   DARK MODE
   ============================================= */
[data-bs-theme="dark"] .app-details-wrapper {
    --ap-bg:          #0f172a;
    --ap-surface:     #1e293b;
    --ap-border:      #334155;
    --ap-text:        #f1f5f9;
    --ap-text-muted:  #94a3b8;
    --ap-navy:        #5b8dc4;
    --ap-navy-tint:   rgba(91, 141, 196, 0.16);
    --ap-gold:        #d9ae55;
    --ap-gold-tint:   rgba(217, 174, 85, 0.18);
    --ap-danger-tint: rgba(239, 68, 68, 0.16);
}

/* --ap-navy-deep and --ap-danger stay dark on purpose — they still back the
   btn-primary/secondary hover states and the rejected-step circle, which read
   fine as a dark fill under white text in either theme. But the same vars are
   also used as plain text color in a few spots, and dark navy/red text is
   unreadable on the dark card surface above, so those get an explicit
   lighter override instead of touching the shared variable. */
[data-bs-theme="dark"] .app-details-wrapper h2,
[data-bs-theme="dark"] .app-details-wrapper .card-header h5,
[data-bs-theme="dark"] .app-details-wrapper .timeline strong,
[data-bs-theme="dark"] .app-details-wrapper .st-step.st-done .st-label,
[data-bs-theme="dark"] .app-details-wrapper .st-step.st-current .st-label,
[data-bs-theme="dark"] .app-details-wrapper .btn-outline-primary:hover,
[data-bs-theme="dark"] .app-details-wrapper .btn-outline-primary:focus {
    color: #f1f5f9;
}

[data-bs-theme="dark"] .app-details-wrapper .st-step.st-rejected .st-label,
[data-bs-theme="dark"] .app-details-wrapper .tracker-rejected-note {
    color: #f87171;
}
</style>

<div class="app-details-wrapper">
    <h2 class="mb-4"><?php echo _vwt('heading'); ?></h2>

    <!-- ── Application Progress Tracker ── -->
    <div class="card mb-3 tracker-card">
        <div class="card-body">
            <div class="tracker-title"><?php echo _vwt('card_progress'); ?></div>
            <div class="status-tracker">
                <?php
                $_trkLastIdx = count($_trkKeys) - 1;
                foreach ($_trkKeys as $_idx => $_key):
                    if ($_trkRejected && $_idx === $_trkCurrent) {
                        $_state = 'rejected';
                    } elseif ($_idx < $_trkCurrent) {
                        $_state = 'done';
                    } elseif ($_idx === $_trkCurrent && $_idx === $_trkLastIdx) {
                        // The final step (Permit Released) is a terminal state —
                        // once reached, show it as completed rather than "in
                        // progress", since there's no further step after it.
                        $_state = 'done';
                    } elseif ($_idx === $_trkCurrent) {
                        $_state = 'current';
                    } else {
                        $_state = 'upcoming';
                    }
                ?>
                    <?php if ($_idx > 0): ?>
                        <div class="st-connector<?php echo ($_idx - 1) < $_trkCurrent ? ' st-done' : ''; ?>"></div>
                    <?php endif; ?>
                    <div class="st-step st-<?php echo $_state; ?>">
                        <div class="st-circle">
                            <?php if ($_state === 'done'): ?>
                                <i class="bi bi-check-lg"></i>
                            <?php elseif ($_state === 'rejected'): ?>
                                <i class="bi bi-x-lg"></i>
                            <?php else: ?>
                                <?php echo $_idx + 1; ?>
                            <?php endif; ?>
                        </div>
                        <div class="st-label"><?php echo htmlspecialchars($_trkSteps[$_key]); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($_trkRejected): ?>
                <p class="tracker-rejected-note">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo _vwt('note_rejected'); ?>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5>Application #<?php echo htmlspecialchars($application['application_number']); ?></h5>
            <span class="badge bg-<?php echo Helper::getStatusBadge($application['status']); ?>">
                <?php echo ucfirst(str_replace('_', ' ', $application['status'])); ?>
            </span>
        </div>
        <div class="card-body">
            <h6><?php echo _vwt('sec_project'); ?></h6>
            <p><strong><?php echo _vwt('lbl_project_name'); ?>:</strong> <?php echo htmlspecialchars($application['project_name']); ?></p>
            <p><strong><?php echo _vwt('lbl_project_type'); ?>:</strong> <?php echo htmlspecialchars($application['project_type'] ?? 'N/A'); ?></p>
            <p><strong><?php echo _vwt('lbl_description'); ?>:</strong> <?php echo htmlspecialchars($application['project_description'] ?? 'N/A'); ?></p>
            
            <hr>
            
            <h6><?php echo _vwt('sec_location'); ?></h6>
            <p><strong><?php echo _vwt('lbl_lot_number'); ?>:</strong> <?php echo htmlspecialchars($application['lot_number'] ?? 'N/A'); ?></p>
            <p><strong><?php echo _vwt('lbl_block'); ?>:</strong> <?php echo htmlspecialchars($application['block'] ?? 'N/A'); ?></p>
            <p><strong><?php echo _vwt('lbl_street'); ?>:</strong> <?php echo htmlspecialchars($application['street'] ?? 'N/A'); ?></p>
            <p><strong><?php echo _vwt('lbl_barangay'); ?>:</strong> <?php echo htmlspecialchars($application['barangay'] ?? 'N/A'); ?></p>
            <p><strong><?php echo _vwt('lbl_district'); ?>:</strong> <?php echo htmlspecialchars($application['district'] ?? 'N/A'); ?></p>
            <p><strong><?php echo _vwt('lbl_parcel_id'); ?>:</strong> <?php echo htmlspecialchars($application['parcel_id'] ?? 'N/A'); ?></p>
            <?php if (!empty($application['latitude']) && !empty($application['longitude'])): ?>
                <p><strong><?php echo _vwt('lbl_coordinates'); ?>:</strong> <?php echo htmlspecialchars($application['latitude']); ?>, <?php echo htmlspecialchars($application['longitude']); ?></p>
            <?php endif; ?>
            
            <?php 
            // Fix: Added null coalescing to prevent "Undefined array key" warning
            $zoningStatus = $application['zoning_compliance_status'] ?? 'pending';
            if ($zoningStatus !== 'pending'): ?>
                <hr>
                <h6><?php echo _vwt('sec_zoning'); ?></h6>
                <p><strong><?php echo _vwt('lbl_status'); ?>:</strong> 
                    <span class="badge bg-<?php echo $zoningStatus === 'compliant' ? 'success' : 'danger'; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $zoningStatus)); ?>
                    </span>
                </p>
                <?php 
                // Fix: Added safety check for the report content
                $report = $application['zoning_compliance_report'] ?? null;
                if ($report): ?>
                    <pre class="bg-light p-3"><?php echo htmlspecialchars($report); ?></pre>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($application['status'] === 'pending_payment' && $paymentRecord): ?>
    <div class="card mb-3 border-warning">
        <div class="card-header bg-warning-subtle d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-cash-coin me-2"></i><?php echo _vwt('card_payment'); ?></h5>
            <?php if ($paymentRecord['status'] === 'paid'): ?>
                <span class="badge bg-success"><?php echo _vwt('step_released'); ?></span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($paymentRecord['status'] === 'paid'): ?>
                <p class="mb-0 text-success"><i class="bi bi-check-circle-fill me-1"></i> <?php echo _vwt('pay_paid_note'); ?></p>
            <?php else: ?>
                <p><?php echo _vwt('pay_intro'); ?></p>
                <p class="mb-1"><strong><?php echo _vwt('pay_reference'); ?>:</strong> <?php echo htmlspecialchars($paymentRecord['reference_number']); ?></p>
                <p class="mb-3"><strong><?php echo _vwt('pay_amount'); ?>:</strong> ₱<?php echo number_format((float) $paymentRecord['amount'], 2); ?></p>
                <a href="/lgu-urban-planning/modules/PermitProcessing/pay.php?id=<?php echo $applicationId; ?>" class="btn btn-warning fw-bold">
                    <i class="bi bi-credit-card me-1"></i> <?php echo _vwt('btn_pay_now'); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-header">
            <h5><?php echo _vwt('card_documents'); ?></h5>
        </div>
        <div class="card-body">
            <?php if (empty($application['documents'])): ?>
                <p class="text-muted"><?php echo _vwt('no_documents'); ?></p>
            <?php else: ?>
                <div class="table-responsive-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?php echo _vwt('col_doc_type'); ?></th>
                            <th><?php echo _vwt('col_file_name'); ?></th>
                            <th><?php echo _vwt('col_uploaded'); ?></th>
                            <th><?php echo _vwt('col_action'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($application['documents'] as $doc): ?>
                        <tr>
                            <td><?php echo ucfirst(str_replace('_', ' ', $doc['document_type'])); ?></td>
                            <td><?php echo htmlspecialchars($doc['file_name']); ?></td>
                            <td><?php echo Helper::formatDateTime($doc['created_at']); ?></td>
                            <td>
                                <?php
                                    $docId   = (int)$doc['id'];
                                    $docName = htmlspecialchars(addslashes($doc['file_name']));
                                    $docExt  = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                                    $encodedName = urlencode($doc['file_name']);
                                    $encodedPath = urlencode($doc['file_path'] ?? $doc['file_name']);
                                    $viewUrl = '/lgu-urban-planning/documents/user_download.php?file=' . $encodedPath . '&amp;name=' . $encodedName . '&amp;view=1';
                                    $dlUrl   = '/lgu-urban-planning/documents/user_download.php?file=' . $encodedPath . '&name=' . $encodedName;
                                ?>
                                <button type="button"
                                    class="btn btn-sm btn-outline-primary me-1"
                                    onclick="openDocModal('<?php echo '/lgu-urban-planning/documents/user_download.php?file=' . $encodedPath . '&view=1&name=' . $encodedName; ?>','<?php echo $docName; ?>','<?php echo $docExt; ?>')">
                                    <i class="bi bi-eye"></i> <?php echo _vwt('btn_view'); ?>
                                </button>
                                <a href="<?php echo $dlUrl; ?>"
                                   class="btn btn-sm btn-primary"
                                   download="<?php echo htmlspecialchars($doc['file_name']); ?>">
                                    <i class="bi bi-download"></i> <?php echo _vwt('btn_download'); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div><!-- /.table-responsive-wrapper -->
            <?php endif; ?>
        </div>
    </div>
    
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5><?php echo _vwt('card_history'); ?></h5>
            <small class="text-muted" id="historyPageInfo"></small>
        </div>
        <div class="card-body">
            <div class="timeline" id="historyTimeline">
                <?php foreach ($application['status_history'] as $history): ?>
                <div class="mb-3 border-start border-3 ps-3 history-item">
                    <strong>
                        <?php if ($history['status'] === 'pending_payment'): ?>
                            <i class="bi bi-cash-coin text-warning me-1"></i>
                        <?php endif; ?>
                        <?php echo ucfirst(str_replace('_', ' ', $history['status'])); ?>
                    </strong>
                    <p class="mb-1"><?php echo nl2br(htmlspecialchars($history['remarks'] ?? _vwt('no_remarks'))); ?></p>
                    <small class="text-muted">
                        <?php echo Helper::formatDateTime($history['created_at']); ?>
                        <?php if (!empty($history['first_name'])): ?>
                            <?php echo _vwt('lbl_by'); ?> <?php echo htmlspecialchars($history['first_name'] . ' ' . $history['last_name']); ?>
                        <?php endif; ?>
                    </small>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination Controls -->
            <div class="d-flex justify-content-center align-items-center gap-2 mt-3" id="historyPagination" style="display:none!important;">
                <button class="btn btn-sm btn-outline-secondary" id="historyPrevBtn" onclick="changeHistoryPage(-1)" disabled>
                    <i class="bi bi-chevron-left"></i> Prev
                </button>
                <span class="text-muted small px-1" id="historyPaginationLabel"></span>
                <button class="btn btn-sm btn-outline-secondary" id="historyNextBtn" onclick="changeHistoryPage(1)">
                    Next <i class="bi bi-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>
    
    <a href="/lgu-urban-planning/applicant/applications.php" class="btn btn-secondary"><?php echo _vwt('btn_back'); ?></a>
</div><!-- /.app-details-wrapper -->

<!-- ── Document Viewer Modal ─────────────────────────────────────────────── -->
<div class="modal fade" id="docViewerModal" tabindex="-1" aria-labelledby="docViewerLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content" style="height: 90vh;">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-semibold" id="docViewerLabel">
                    <i class="bi bi-file-earmark me-2"></i><span id="docViewerTitle">Document</span>
                </h6>
                <div class="ms-auto d-flex gap-2 align-items-center">
                    <a id="docViewerDownload" href="#" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-download"></i> Download
                    </a>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body p-0 d-flex flex-column align-items-center justify-content-center bg-secondary bg-opacity-10" style="overflow:hidden; flex:1;">
                <!-- PDF -->
                <iframe id="docViewerFrame"
                        src=""
                        style="width:100%; height:100%; border:none; display:none;"
                        title="Document Viewer">
                </iframe>
                <!-- Image -->
                <img id="docViewerImg"
                     src=""
                     alt="Document"
                     style="max-width:100%; max-height:100%; object-fit:contain; display:none; padding:1rem;" />
                <!-- Unsupported -->
                <div id="docViewerUnsupported" style="display:none;" class="text-center p-4">
                    <i class="bi bi-file-earmark-x fs-1 text-muted"></i>
                    <p class="mt-2 text-muted">This file type cannot be previewed.<br>Please download it to view.</p>
                    <a id="docViewerUnsupportedLink" href="#" class="btn btn-primary mt-2">
                        <i class="bi bi-download me-1"></i> Download File
                    </a>
                </div>
                <!-- Loading spinner -->
                <div id="docViewerSpinner" class="text-center p-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 text-muted small">Loading document...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openDocModal(viewUrl, fileName, ext) {
    // Reset
    document.getElementById('docViewerFrame').style.display    = 'none';
    document.getElementById('docViewerImg').style.display      = 'none';
    document.getElementById('docViewerUnsupported').style.display = 'none';
    document.getElementById('docViewerSpinner').style.display  = 'block';
    document.getElementById('docViewerFrame').src              = '';
    document.getElementById('docViewerImg').src                = '';

    document.getElementById('docViewerTitle').textContent = fileName;

    // Download link (no &view=1)
    const downloadUrl = viewUrl.replace('&view=1', '');
    document.getElementById('docViewerDownload').href = downloadUrl;
    document.getElementById('docViewerUnsupportedLink').href = downloadUrl;

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('docViewerModal'));
    modal.show();

    const imageExts = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

    if (ext === 'pdf') {
        const frame = document.getElementById('docViewerFrame');
        frame.onload = () => {
            document.getElementById('docViewerSpinner').style.display = 'none';
            frame.style.display = 'block';
        };
        frame.src = viewUrl;

    } else if (imageExts.includes(ext)) {
        const img = document.getElementById('docViewerImg');
        img.onload = () => {
            document.getElementById('docViewerSpinner').style.display = 'none';
            img.style.display = 'block';
        };
        img.src = viewUrl;

    } else {
        // Unsupported type — show download prompt
        document.getElementById('docViewerSpinner').style.display    = 'none';
        document.getElementById('docViewerUnsupported').style.display = 'block';
    }
}

// ── Status History Pagination ────────────────────────────────────────────────
(function () {
    const ITEMS_PER_PAGE = 5;
    let currentPage = 1;

    const items      = Array.from(document.querySelectorAll('.history-item'));
    const pagination = document.getElementById('historyPagination');
    const prevBtn    = document.getElementById('historyPrevBtn');
    const nextBtn    = document.getElementById('historyNextBtn');
    const pageLabel  = document.getElementById('historyPaginationLabel');
    const pageInfo   = document.getElementById('historyPageInfo');

    const totalPages = Math.ceil(items.length / ITEMS_PER_PAGE);

    function renderPage(page) {
        const start = (page - 1) * ITEMS_PER_PAGE;
        const end   = start + ITEMS_PER_PAGE;

        items.forEach(function (item, idx) {
            item.style.display = (idx >= start && idx < end) ? '' : 'none';
        });

        prevBtn.disabled = (page <= 1);
        nextBtn.disabled = (page >= totalPages);

        const label = 'Page ' + page + ' of ' + totalPages;
        pageLabel.textContent = label;
        pageInfo.textContent  = items.length + ' entr' + (items.length === 1 ? 'y' : 'ies');
    }

    if (items.length > ITEMS_PER_PAGE) {
        pagination.style.removeProperty('display'); // show the nav
        renderPage(currentPage);
    } else {
        // Still show info label even when no pagination needed
        pageInfo.textContent = items.length + ' entr' + (items.length === 1 ? 'y' : 'ies');
    }

    window.changeHistoryPage = function (direction) {
        const next = currentPage + direction;
        if (next < 1 || next > totalPages) return;
        currentPage = next;
        renderPage(currentPage);

        // Scroll to top of the history card smoothly
        document.getElementById('historyTimeline')
                .closest('.card')
                .scrollIntoView({ behavior: 'smooth', block: 'start' });
    };
})();

// Clear iframe/img src when modal closes to stop loading
document.getElementById('docViewerModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('docViewerFrame').src = '';
    document.getElementById('docViewerImg').src   = '';
});
</script>

<?php include __DIR__ . '/../user/footer.php'; ?>