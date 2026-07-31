<?php
/**
 * My Applications List
 */

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helper.php';
require_once __DIR__ . '/../modules/ApplicantSelfService/ApplicantController.php';

$auth = new Auth();
$auth->requireRole('applicant');

$applicantController = new ApplicantController();
$applications = $applicantController->getMyApplications();

// ── i18n — reads language saved by settings.php ──────────────────────────────
$_aLang = $_SESSION['locale_language'] ?? 'en_PH';

$_aT = [
    'en_PH' => [
        'page_title'       => 'My Applications',
        'heading'          => 'My Applications',
        'btn_submit_new'   => 'Submit New Application',
        'col_app_num'      => 'Application #',
        'col_project'      => 'Project Name',
        'col_status'       => 'Status',
        'col_documents'    => 'Documents',
        'col_submitted'    => 'Submitted',
        'col_action'       => 'Action',
        'empty_table'      => 'No applications yet.',
        'empty_link'       => 'Submit your first application',
        'doc_count'        => 'doc(s)',
        'tap_to_view'      => 'Tap to view details',
        'btn_view'         => 'View',
    ],
    'fil' => [
        'page_title'       => 'Aking mga Aplikasyon',
        'heading'          => 'Aking mga Aplikasyon',
        'btn_submit_new'   => 'Magsumite ng Bagong Aplikasyon',
        'col_app_num'      => 'Aplikasyon #',
        'col_project'      => 'Pangalan ng Proyekto',
        'col_status'       => 'Katayuan',
        'col_documents'    => 'Mga Dokumento',
        'col_submitted'    => 'Isinumite',
        'col_action'       => 'Aksyon',
        'empty_table'      => 'Wala pang mga aplikasyon.',
        'empty_link'       => 'Isumite ang iyong unang aplikasyon',
        'doc_count'        => 'dok.',
        'tap_to_view'      => 'I-tap upang makita ang mga detalye',
        'btn_view'         => 'Tingnan',
    ],
];

function _at(string $key): string {
    global $_aT, $_aLang;
    return $_aT[$_aLang][$key] ?? $_aT['en_PH'][$key] ?? $key;
}

$pageTitle = _at('page_title');
$isAuthPage = true;
include __DIR__ . '/../user/header.php';
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
/* =============================================
   MY APPLICATIONS PAGE — MODERN CIVIC THEME
   Fully responsive — 1024px | 768px | 480px | 320px
   ============================================= */

.apps-page {
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
    --ap-focus:       rgba(22,50,79,.16);

    width: 100%;
    box-sizing: border-box;
    overflow-x: hidden;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    color: var(--ap-text);
}

.apps-page *,
.apps-page *::before,
.apps-page *::after {
    box-sizing: border-box;
}

/* --- Dark mode ---
   Every rule below already reads colors from the --ap-* custom
   properties, so redefining them here re-themes the whole page. */
[data-bs-theme="dark"] .apps-page {
    --ap-navy:        #4d8eff;
    --ap-navy-deep:   #cfe2ff;
    --ap-navy-tint:   rgba(77, 142, 255, .15);
    --ap-gold:        #e8c568;
    --ap-gold-tint:   rgba(232, 197, 104, .12);
    --ap-bg:          var(--bs-body-bg);
    --ap-surface:     var(--bs-tertiary-bg);
    --ap-border:      var(--bs-border-color);
    --ap-text:        var(--bs-body-color);
    --ap-text-muted:  var(--bs-secondary-color);
    --ap-focus:       rgba(77, 142, 255, .25);
}

/* --- Page header row (title + button) --- */
.apps-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1.25rem;
    border-bottom: 1px solid var(--ap-border);
}

.apps-header .apps-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.09em;
    text-transform: uppercase;
    color: var(--ap-gold);
    margin-bottom: 0.5rem;
}

.apps-header .apps-eyebrow::before {
    content: "";
    display: inline-block;
    width: 18px;
    height: 2px;
    background: var(--ap-gold);
}

.apps-header h2 {
    font-size: 1.65rem;
    font-weight: 800;
    letter-spacing: -0.015em;
    margin: 0;
    color: var(--ap-navy-deep);
}

.apps-header .btn {
    font-size: 0.88rem;
    white-space: nowrap;
    border-radius: 8px;
    font-weight: 600;
    padding: 0.6rem 1.25rem;
    background: var(--ap-navy);
    border-color: var(--ap-navy);
}

.apps-header .btn:hover,
.apps-header .btn:focus {
    background: var(--ap-navy-deep);
    border-color: var(--ap-navy-deep);
}

/* --- Card --- */
.apps-page .card {
    width: 100%;
    background: var(--ap-surface);
    border: 1px solid var(--ap-border);
    border-radius: 12px;
    box-shadow: 0 1px 2px rgba(16,24,40,.04);
    overflow: hidden;  /* clip table edges on mobile */
}

.apps-page .card-body {
    padding: 0;        /* table fills the card edge-to-edge */
    overflow-x: auto;  /* horizontal scroll if table overflows */
    -webkit-overflow-scrolling: touch;
}

/* --- Table (desktop) --- */
.apps-table {
    width: 100%;
    min-width: 600px;  /* enforce minimum so columns don't crush */
    margin: 0;
    font-size: 0.875rem;
}

.apps-table thead th {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--ap-text-muted);
    padding: 0.85rem 1.25rem;
    white-space: nowrap;
    background: var(--ap-navy-tint);
    border-bottom: 1px solid var(--ap-border);
}

.apps-table tbody td {
    padding: 0.85rem 1.25rem;
    vertical-align: middle;
    border-bottom: 1px solid var(--ap-border);
    color: var(--ap-text);
}

.apps-table tbody tr:last-child td {
    border-bottom: none;
}

.apps-table tbody tr {
    transition: background-color .12s ease;
}

.apps-table tbody tr:hover {
    background: var(--ap-bg);
}

.apps-table tbody td:first-child {
    font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--ap-navy);
    letter-spacing: 0.01em;
}

/* --- Empty state --- */
.apps-empty {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--ap-text-muted);
    font-size: 0.9rem;
}

.apps-empty a,
.apps-empty-card a {
    color: var(--ap-navy);
    font-weight: 600;
    text-decoration: none;
}

.apps-empty a:hover,
.apps-empty-card a:hover {
    text-decoration: underline;
}

/* --- Status badge --- */
.apps-table .badge {
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    padding: 0.35em 0.75em;
    border-radius: 999px;
}

/* --- View button --- */
.apps-table .btn-sm {
    font-size: 0.8rem;
    font-weight: 600;
    padding: 0.35rem 0.9rem;
    white-space: nowrap;
    border-radius: 7px;
    background: var(--ap-navy);
    border-color: var(--ap-navy);
}

.apps-table .btn-sm:hover,
.apps-table .btn-sm:focus {
    background: var(--ap-navy-deep);
    border-color: var(--ap-navy-deep);
}

/* =============================================
   1024px — Laptop
   ============================================= */
@media (max-width: 1024px) {

    .apps-header h2 { font-size: 1.5rem; }

    .apps-table {
        min-width: 560px;
    }

    .apps-table thead th,
    .apps-table tbody td {
        padding: 0.75rem 1rem;
    }
}

/* =============================================
   768px — Tablet
   ============================================= */
@media (max-width: 768px) {

    .apps-header h2 { font-size: 1.35rem; }

    .apps-header {
        margin-bottom: 1.25rem;
        padding-bottom: 1rem;
    }

    .apps-header .btn {
        font-size: 0.84rem;
        padding: 0.5rem 1rem;
    }

    .apps-table {
        font-size: 0.82rem;
        min-width: 520px;
    }

    .apps-table thead th {
        font-size: 0.68rem;
        padding: 0.65rem 0.9rem;
    }

    .apps-table tbody td {
        padding: 0.65rem 0.9rem;
    }
}

/* =============================================
   480px — Large Mobile
   Swap the table for stacked cards per row
   ============================================= */
@media (max-width: 480px) {

    .apps-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.6rem;
        margin-bottom: 1.1rem;
        padding-bottom: 0.9rem;
    }

    .apps-header h2 { font-size: 1.18rem; }

    .apps-header .btn {
        width: 100%;
        text-align: center;
        font-size: 0.85rem;
    }

    /* Hide the scrollable table wrapper */
    .apps-page .card {
        border: none;
        background: transparent;
        box-shadow: none !important;
    }

    .apps-page .card-body {
        padding: 0;
        overflow-x: visible;
    }

    /* Hide the real <table> */
    .apps-table { display: none; }

    /* Show card-style rows rendered via data-* attributes */
    .app-card-row {
        display: flex;
        flex-direction: column;
        background: var(--ap-surface);
        border: 1px solid var(--ap-border);
        border-radius: 12px;
        padding: 0.9rem 1rem;
        margin-bottom: 0.7rem;
        box-shadow: 0 1px 2px rgba(16,24,40,.04);
    }

    .app-card-row .app-row-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 0.5rem;
        margin-bottom: 0.45rem;
    }

    .app-card-row .app-num {
        font-size: 0.72rem;
        font-weight: 700;
        color: var(--ap-navy);
        letter-spacing: 0.03em;
        font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
    }

    .app-card-row .app-name {
        font-size: 0.92rem;
        font-weight: 700;
        color: var(--ap-navy-deep);
        margin-bottom: 0.4rem;
        word-break: break-word;
    }

    .app-card-row .app-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem 1rem;
        font-size: 0.75rem;
        color: var(--ap-text-muted);
        margin-bottom: 0.6rem;
    }

    .app-card-row .app-meta span {
        display: flex;
        align-items: center;
        gap: 0.3rem;
    }

    .app-card-row .app-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 0.6rem;
        border-top: 1px solid var(--ap-border);
        gap: 0.5rem;
    }

    .app-card-row .app-footer .badge {
        font-size: 0.68rem;
        font-weight: 600;
        padding: 0.32em 0.75em;
        border-radius: 999px;
    }

    .app-card-row .app-footer .btn {
        font-size: 0.8rem;
        font-weight: 600;
        padding: 0.35rem 0.95rem;
        border-radius: 7px;
        background: var(--ap-navy);
        border-color: var(--ap-navy);
    }

    /* Empty state in card mode */
    .apps-empty-card {
        text-align: center;
        padding: 2.25rem 1rem;
        background: var(--ap-surface);
        border: 1px solid var(--ap-border);
        border-radius: 12px;
        color: var(--ap-text-muted);
        font-size: 0.875rem;
    }
}

/* =============================================
   320px — Small Mobile
   ============================================= */
@media (max-width: 320px) {

    .apps-header h2 { font-size: 1.02rem; }

    .apps-header .btn {
        font-size: 0.78rem;
        padding: 0.45rem 0.6rem;
    }

    .app-card-row {
        padding: 0.7rem 0.75rem;
        margin-bottom: 0.55rem;
        border-radius: 10px;
    }

    .app-card-row .app-num {
        font-size: 0.68rem;
    }

    .app-card-row .app-name {
        font-size: 0.84rem;
    }

    .app-card-row .app-meta {
        font-size: 0.7rem;
        gap: 0.35rem 0.75rem;
    }

    .app-card-row .app-footer .badge {
        font-size: 0.63rem;
    }

    .app-card-row .app-footer .btn {
        font-size: 0.72rem;
        padding: 0.3rem 0.7rem;
    }

    .apps-empty-card {
        font-size: 0.8rem;
        padding: 1.5rem 0.75rem;
    }
}
</style>

<div class="apps-page">

    <!-- Page header -->
    <div class="apps-header">
        <div>
            <div class="apps-eyebrow"><?php echo _at('page_title'); ?></div>
            <h2><?php echo _at('heading'); ?></h2>
        </div>
        <a href="/lgu-urban-planning/applicant/apply.php" class="btn btn-primary">
            <i class="bi bi-plus me-1"></i> <?php echo _at('btn_submit_new'); ?>
        </a>
    </div>

    <!-- ── Desktop / Tablet: scrollable table ── -->
    <div class="card">
        <div class="card-body">
            <table class="table apps-table mb-0">
                <thead>
                    <tr>
                        <th><?php echo _at('col_app_num'); ?></th>
                        <th><?php echo _at('col_project'); ?></th>
                        <th><?php echo _at('col_status'); ?></th>
                        <th><?php echo _at('col_documents'); ?></th>
                        <th><?php echo _at('col_submitted'); ?></th>
                        <th><?php echo _at('col_action'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($applications)): ?>
                        <tr>
                            <td colspan="6" class="apps-empty">
                                <?php echo _at('empty_table'); ?>
                                <a href="/lgu-urban-planning/applicant/apply.php"><?php echo _at('empty_link'); ?></a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($applications as $app): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($app['application_number']); ?></td>
                            <td><?php echo htmlspecialchars($app['project_name']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo Helper::getStatusBadge($app['status']); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $app['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo $app['document_count']; ?> <?php echo _at('doc_count'); ?></td>
                            <td><?php echo Helper::formatDate($app['submitted_at'] ?? $app['created_at']); ?></td>
                            <td>
                                <a href="/lgu-urban-planning/applicant/view.php?id=<?php echo $app['id']; ?>"
                                   class="btn btn-sm btn-primary"><?php echo _at('btn_view'); ?></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ── Mobile (≤480px): stacked card rows ── -->
    <div class="d-none" id="app-cards-mobile">
        <?php if (empty($applications)): ?>
            <div class="apps-empty-card">
                <?php echo _at('empty_table'); ?>
                <a href="/lgu-urban-planning/applicant/apply.php"><?php echo _at('empty_link'); ?></a>
            </div>
        <?php else: ?>
            <?php foreach ($applications as $app): ?>
            <div class="app-card-row">
                <div class="app-row-top">
                    <span class="app-num"><?php echo htmlspecialchars($app['application_number']); ?></span>
                    <span class="badge bg-<?php echo Helper::getStatusBadge($app['status']); ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $app['status'])); ?>
                    </span>
                </div>
                <div class="app-name"><?php echo htmlspecialchars($app['project_name']); ?></div>
                <div class="app-meta">
                    <span><i class="bi bi-file-earmark"></i> <?php echo $app['document_count']; ?> <?php echo _at('doc_count'); ?></span>
                    <span><i class="bi bi-calendar3"></i> <?php echo Helper::formatDate($app['submitted_at'] ?? $app['created_at']); ?></span>
                </div>
                <div class="app-footer">
                    <span class="text-muted" style="font-size:0.72rem;"><?php echo _at('tap_to_view'); ?></span>
                    <a href="/lgu-urban-planning/applicant/view.php?id=<?php echo $app['id']; ?>"
                       class="btn btn-sm btn-primary">
                        <i class="bi bi-eye me-1"></i><?php echo _at('btn_view'); ?>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<script>
/* Switch between table and card layout based on viewport width */
(function () {
    const tableCard   = document.querySelector('.apps-page > .card');
    const mobileCards = document.getElementById('app-cards-mobile');

    function applyLayout() {
        if (window.innerWidth <= 480) {
            if (tableCard)   tableCard.classList.add('d-none');
            if (mobileCards) mobileCards.classList.remove('d-none');
        } else {
            if (tableCard)   tableCard.classList.remove('d-none');
            if (mobileCards) mobileCards.classList.add('d-none');
        }
    }

    applyLayout();
    window.addEventListener('resize', applyLayout);
})();
</script>

<?php include __DIR__ . '/../user/footer.php'; ?>