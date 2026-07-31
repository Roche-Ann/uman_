<?php

// Reports & Analytics

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helper.php';
require_once __DIR__ . '/../modules/DocumentReportManagement/DocumentController.php';
require_once __DIR__ . '/../config/config.php';

$auth = new Auth();
$auth->requirePermission('generate_reports');
$auth->requireRole(['admin', 'super_admin']);

// Load locale settings for date/time display
$_db = Database::getInstance();
$_localeRows   = $_db->fetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('locale_time_format', 'locale_timezone', 'locale_date_format')");
$_localeMap    = array_column($_localeRows, 'setting_value', 'setting_key');
$dashTimeFormat  = $_localeMap['locale_time_format'] ?? '12h';
$dashTimezone    = $_localeMap['locale_timezone']    ?? 'Asia/Manila';
$dashDateFormat  = $_localeMap['locale_date_format'] ?? 'F j, Y';
$phpDateFormat   = match($dashDateFormat) {
    'M/D/YYYY'   => 'm/d/Y',
    'D/M/YYYY'   => 'd/m/Y',
    'YYYY-MM-DD' => 'Y-m-d',
    default      => 'F j, Y',
};
$_tz = new DateTimeZone($dashTimezone);
$_now = new DateTime('now', $_tz);

$documentController = new DocumentController();
$report = null;
$error = '';

// --- INITIALIZE CHART DATA ---
$chartData = [
    'status' => ['Approved' => 0, 'Rejected' => 0, 'Pending' => 0],
    'months' => ['Jan'=>0, 'Feb'=>0, 'Mar'=>0, 'Apr'=>0, 'May'=>0, 'Jun'=>0, 'Jul'=>0, 'Aug'=>0, 'Sep'=>0, 'Oct'=>0, 'Nov'=>0, 'Dec'=>0],
    'barangays' => [],
    'yoy_comparison' => ['current' => 0, 'previous' => 0]
];

// --- PAGINATION SETTINGS ---
$itemsPerPage = 10;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($currentPage < 1) $currentPage = 1;

if (isset($_REQUEST['report_type'])) {
    $reportType = $_REQUEST['report_type'];
    $selectedYear = !empty($_REQUEST['year']) ? (int)$_REQUEST['year'] : (int)date('Y');
    
    $filters = [];
    if (!empty($_REQUEST['date_from'])) $filters['date_from'] = $_REQUEST['date_from'];
    if (!empty($_REQUEST['date_to'])) $filters['date_to'] = $_REQUEST['date_to'];
    $filters['year'] = $selectedYear;
    
    $report = $documentController->generateReport($reportType, $filters);
    $isValid = (is_array($report) && !empty($report['data']));

    if (!$isValid) {
        $error = "Notice: " . ($report['error'] ?? 'No records found for the selected year.');
        $report = null; 
    } else {
        // 1. Get Previous Year Data for Comparison
        $prevFilters = $filters;
        $prevFilters['year'] = $selectedYear - 1;
        $prevYearReport = $documentController->generateReport($reportType, $prevFilters);
        $chartData['yoy_comparison']['current'] = count($report['data']);
        $chartData['yoy_comparison']['previous'] = (is_array($prevYearReport) && isset($prevYearReport['data'])) ? count($prevYearReport['data']) : 0;

        foreach ($report['data'] as $row) {
            $s = strtolower($row['status'] ?? '');
            if ($s === 'approved') $chartData['status']['Approved']++;
            elseif ($s === 'rejected') $chartData['status']['Rejected']++;
            else $chartData['status']['Pending']++;

            $dateKey = $row['created_at'] ?? $row['date_issued'] ?? '';
            if ($dateKey) {
                $m = date('M', strtotime($dateKey));
                if (isset($chartData['months'][$m])) $chartData['months'][$m]++;
            }

            $brgy = $row['barangay'] ?? '';
            if ($brgy) {
                $chartData['barangays'][$brgy] = ($chartData['barangays'][$brgy] ?? 0) + 1;
            }
        }
        arsort($chartData['barangays']);
        $chartData['barangays'] = array_slice($chartData['barangays'], 0, 5);

        $totalItems = count($report['data']);
        $totalPages = max(1, ceil($totalItems / $itemsPerPage));
        $offset = ($currentPage - 1) * $itemsPerPage;
        $allDataForExport = $report['data']; 
        $report['data'] = array_slice($allDataForExport, $offset, $itemsPerPage); 
    }
}

$pageTitle = 'Reports & Analytics';
$isAuthPage = true;

// --- Inspector Workload snapshot (always shown, all-time — not tied to selected report/year) ---
$inspectorWorkload = $documentController->getInspectorWorkloadSnapshot();

// --- AI-generated narrative insights (uses aggregated $chartData only, no raw records) ---
require_once __DIR__ . '/../core/Geminiinsights.php';
$aiInsights = new Geminiinsights();
$aiNarrative = $aiInsights->generate($chartData, $inspectorWorkload, $selectedYear ?? (int)date('Y'));

include __DIR__ . '/../admin/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    /* ── Gradient action button (copied from users.php / audit-logs.php style) ── */
    .btn-simulate-gradient {
        background: linear-gradient(135deg, #1c4e9e 0%, #4a7dfc 100%);
        border: none;
        color: #fff;
        border-radius: 8px;
        font-weight: 600;
        box-shadow: 0 3px 8px rgba(28, 78, 158, 0.3);
        transition: transform 0.12s ease, box-shadow 0.12s ease, color 0.12s ease;
    }
    .btn-simulate-gradient:hover,
    .btn-simulate-gradient:focus,
    .btn-simulate-gradient:active {
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 5px 12px rgba(28, 78, 158, 0.4);
    }

    /* ================================================
       EXPORT / PRINT VERIFICATION MODAL
       (mirrors #exportVerifyModal in admin/users.php)
       ================================================ */
    #exportVerifyModal .modal-content {
        border-radius: 16px;
        overflow: hidden;
    }

    #exportVerifyModal .modal-header {
        background: linear-gradient(135deg, #1c4e9e 0%, #0d6efd 100%);
        border-bottom: none;
        padding: 1.25rem 1.5rem;
    }
    #exportVerifyModal .modal-header-icon {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.16);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-right: 0.75rem;
        flex-shrink: 0;
    }
    #exportVerifyModal .modal-title {
        font-size: 1.15rem;
        font-weight: 700;
        letter-spacing: -0.01em;
    }
    #exportVerifyModal .modal-header-subtitle {
        font-size: 0.8rem;
        color: rgba(255, 255, 255, 0.75);
        margin-top: 1px;
    }

    #exportVerifyModal .modal-body {
        background: #f6f8fb;
        padding: 1.75rem;
    }

    #exportVerifyModal .form-section {
        background: #ffffff;
        border: 1px solid #eaeef3;
        border-radius: 12px;
        padding: 1.25rem 1.5rem 1.5rem;
        margin-bottom: 1.25rem;
    }
    #exportVerifyModal .form-section:last-child { margin-bottom: 0; }

    #exportVerifyModal .form-section-title {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        font-size: 0.78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #1c4e9e;
        margin-bottom: 1.1rem;
        padding-bottom: 0.65rem;
        border-bottom: 1px solid #f0f2f5;
    }
    #exportVerifyModal .form-section-title i {
        font-size: 0.95rem;
        color: #0d6efd;
    }

    #exportVerifyModal .modal-body .form-label {
        font-weight: 600;
        font-size: 0.76rem;
        text-transform: uppercase;
        letter-spacing: 0.02em;
        color: #5a6474;
        margin-bottom: 0.4rem;
    }

    #exportVerifyModal .modal-body .form-control,
    #exportVerifyModal .modal-body .form-select {
        border: 1.5px solid #e2e6ec;
        border-radius: 9px;
        padding: 0.55rem 0.85rem;
        font-size: 0.9rem;
        background-color: #fcfdfe;
        transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
    }
    #exportVerifyModal .modal-body .form-control:focus,
    #exportVerifyModal .modal-body .form-select:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.12);
        background-color: #ffffff;
    }
    #exportVerifyModal .modal-body .form-control::placeholder { color: #a7b0bd; }

    #exportVerifyModal .modal-footer {
        background: #ffffff;
        border-top: 1px solid #eef0f3;
        padding: 1.1rem 1.5rem;
        gap: 0.6rem;
    }
    #exportVerifyModal .modal-footer .btn {
        border-radius: 9px;
        font-weight: 600;
        font-size: 0.88rem;
        padding: 0.55rem 1.4rem;
        transition: transform 0.12s ease, box-shadow 0.12s ease;
    }
    #exportVerifyModal .modal-footer .btn-primary {
        background: linear-gradient(135deg, #1c4e9e 0%, #0d6efd 100%);
        border: none;
        box-shadow: 0 4px 12px rgba(13, 110, 253, 0.28);
    }
    #exportVerifyModal .modal-footer .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(13, 110, 253, 0.35);
    }
    #exportVerifyModal .modal-footer .btn-light {
        border: 1.5px solid #dde1e7;
        color: #5a6474;
        background: #fff;
    }
    #exportVerifyModal .modal-footer .btn-light:hover {
        background: #f6f8fb;
        border-color: #c7cdd6;
        color: #5a6474;
    }

    #exportVerifyModal .modal-body .form-control.is-invalid,
    #exportVerifyModal .modal-body .form-select.is-invalid {
        border-color: #dc3545;
        background-color: #fff5f5;
    }
    #exportVerifyModal .modal-body .form-control.is-invalid:focus,
    #exportVerifyModal .modal-body .form-select.is-invalid:focus {
        border-color: #dc3545;
        box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.12);
    }
    #exportVerifyModal .modal-body .input-group:has(.form-control.is-invalid) .input-group-text {
        border-color: #dc3545;
    }

    /* ── Print styles (Reports "Print" button) ── */
    .print-only-table { display: none; }
    @media print {
        .d-print-none { display: none !important; }
        .card, .shadow-sm { box-shadow: none !important; border: 1px solid #dee2e6 !important; }
        body { background: #fff !important; }

        /* The on-screen table only holds the current page (10 rows).
           Swap it out for the full, unpaginated table when printing so
           "Print" always reflects the same complete data set as "Export All CSV". */
        .paginated-table-onscreen { display: none !important; }
        .print-only-table { display: table !important; }
    }

    .report-main-grid { display: grid; grid-template-columns: 350px 1fr; gap: 1.5rem; align-items: start; }
    .report-display-area { min-width: 0; }
    .table-container-fixed { width: 100%; overflow-x: auto; border: 1px solid #e3e6f0; border-radius: 8px; background: white; }
    .permits-table { table-layout: fixed; width: 100%; min-width: 1000px; margin-bottom: 0; }
    .permits-table th, .permits-table td { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding: 12px; }
    .empty-report-state { background: #fff; border: 2px dashed #e3e6f0; border-radius: 15px; padding: 100px 20px; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #858796; }
    .analytics-section { margin-top: 2rem; }
    .chart-card-container { background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #e3e6f0; height: 100%; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
    .pagination .page-link { color: #2c3e50; border: 1px solid #dee2e6; margin: 0 2px; border-radius: 4px; }
    .pagination .page-item.active .page-link { background-color: #0d6efd; border-color: #0d6efd; color: white; }
    .pagination .page-link:hover { background-color: #e7f1ff; border-color: #b6d4fe; color: #0d6efd; }
    .pagination .page-item.disabled .page-link { color: #6c757d; background-color: #f8f9fa; }
    .info-text { font-size: 0.875rem; color: #6c757d; }
    @media (max-width: 992px) { .report-main-grid { grid-template-columns: 1fr; } }
    .table-dark-header { background-color: #f8f9fa; }
    [data-bs-theme="dark"] .table-dark-header { background-color: #0f172a !important; }

    /* ── 768px: Tablet ─────────────────────────────────────────────────────── */
    @media (max-width: 768px) {

        .p-4 { padding: 1rem !important; }

        /* Header: stack title and date badge */
        .d-flex.justify-content-between.align-items-center.mb-4 {
            flex-direction: column;
            align-items: flex-start !important;
            gap: 10px;
        }
        .d-flex.justify-content-between.align-items-center.mb-4 h2 { font-size: 1.3rem; }
        .d-flex.justify-content-between.align-items-center.mb-4 p { font-size: 0.8rem; }
        .d-flex.justify-content-between.align-items-center.mb-4 .badge {
            font-size: 0.72rem;
            padding: 6px 10px !important;
        }

        /* Grid: single column */
        .report-main-grid { grid-template-columns: 1fr; gap: 1rem; }

        /* Filter sidebar: compact */
        .filter-sidebar .card-body { padding: 1rem !important; }
        .filter-sidebar .form-select,
        .filter-sidebar .form-control { font-size: 0.85rem; }
        .filter-sidebar .form-label { font-size: 0.72rem !important; }

        /* Report table header */
        .card-header.d-flex.justify-content-between { flex-wrap: wrap; gap: 8px; }
        .card-header h5 { font-size: 0.95rem; }
        .card-header .btn-sm { font-size: 0.75rem; padding: 5px 10px; }

        /* Table */
        .permits-table { font-size: 0.78rem; min-width: 600px; }
        .permits-table th, .permits-table td { padding: 8px; }

        /* Empty state */
        .empty-report-state { padding: 60px 20px; }
        .empty-report-state h4 { font-size: 1rem; }
        .empty-report-state p { font-size: 0.8rem; }

        /* Pagination */
        .card-footer .row { flex-direction: column; gap: 10px; text-align: center; }
        .card-footer .col-md-6:last-child { text-align: center !important; }
        .pagination { justify-content: center !important; }
        .pagination .page-link { font-size: 0.78rem; padding: 5px 9px; }

        /* Charts */
        .chart-card-container { padding: 14px; }
        .analytics-section .row { --bs-gutter-y: 1rem; }
    }

    /* ── 480px: Large Mobile ───────────────────────────────────────────────── */
    @media (max-width: 480px) {

        .p-4 { padding: 0.75rem !important; }

        /* Header */
        .d-flex.justify-content-between.align-items-center.mb-4 h2 { font-size: 1.1rem; }
        .d-flex.justify-content-between.align-items-center.mb-4 p { font-size: 0.75rem; }
        .d-flex.justify-content-between.align-items-center.mb-4 .badge {
            font-size: 0.65rem;
            padding: 5px 8px !important;
            width: 100%;
            justify-content: center;
        }

        /* Grid gap */
        .report-main-grid { gap: 0.75rem; }

        /* Filter sidebar */
        .filter-sidebar .card-body { padding: 0.75rem !important; }
        .filter-sidebar .card-header { padding: 0.6rem 0.75rem; }
        .filter-sidebar .card-header h5 { font-size: 0.88rem; }
        .filter-sidebar .form-select,
        .filter-sidebar .form-control { font-size: 0.78rem; padding: 6px 10px; }
        .filter-sidebar .form-label { font-size: 0.65rem !important; margin-bottom: 3px; }
        .filter-sidebar .mb-3 { margin-bottom: 0.6rem !important; }
        .filter-sidebar .mb-4 { margin-bottom: 0.75rem !important; }
        .filter-sidebar .btn { font-size: 0.8rem; padding: 8px; }

        /* Date inputs: stack vertically */
        .filter-sidebar .row.mb-3 .col-6 { width: 100%; flex: 0 0 100%; }
        .filter-sidebar .row.mb-3 { --bs-gutter-y: 0.4rem; }

        /* Report card */
        .card-header.d-flex.justify-content-between {
            flex-direction: column;
            align-items: flex-start !important;
            gap: 8px;
        }
        .card-header h5 { font-size: 0.85rem; }
        .card-header .btn-sm { width: 100%; text-align: center; font-size: 0.72rem; }

        /* Table */
        .permits-table { font-size: 0.7rem; min-width: 500px; }
        .permits-table th, .permits-table td { padding: 6px 8px; }
        .permits-table th { font-size: 0.62rem; }

        /* Empty state */
        .empty-report-state { padding: 40px 16px; }
        .empty-report-state .fs-1 { font-size: 2rem !important; }
        .empty-report-state h4 { font-size: 0.9rem; }
        .empty-report-state p { font-size: 0.75rem; }

        /* Pagination: smaller, centered */
        .d-flex.justify-content-between.align-items-center nav { width: 100%; }
        .pagination { justify-content: center; flex-wrap: wrap; gap: 2px; }
        .pagination .page-link { font-size: 0.68rem; padding: 4px 8px; margin: 0 1px; }

        /* Charts */
        .chart-card-container { padding: 10px; }
        .chart-card-container canvas { max-height: 200px; }
        .analytics-section { margin-top: 1rem; }
        .analytics-section .col-md-6 { width: 100%; flex: 0 0 100%; }

        /* mb spacing */
        .mb-4 { margin-bottom: 0.75rem !important; }
    }

    /* ── 320px: Small Mobile ───────────────────────────────────────────────── */
    @media (max-width: 320px) {

        .p-4 { padding: 0.5rem !important; }

        /* Header */
        .d-flex.justify-content-between.align-items-center.mb-4 h2 { font-size: 0.95rem; }
        .d-flex.justify-content-between.align-items-center.mb-4 p { font-size: 0.68rem; }
        .d-flex.justify-content-between.align-items-center.mb-4 .badge { font-size: 0.6rem; padding: 4px 6px !important; }

        /* Grid */
        .report-main-grid { gap: 0.5rem; }

        /* Filter sidebar */
        .filter-sidebar .card-body { padding: 0.5rem !important; }
        .filter-sidebar .card-header { padding: 0.5rem; }
        .filter-sidebar .card-header h5 { font-size: 0.8rem; }
        .filter-sidebar .form-select,
        .filter-sidebar .form-control { font-size: 0.72rem; padding: 5px 8px; }
        .filter-sidebar .form-label { font-size: 0.6rem !important; }
        .filter-sidebar .btn { font-size: 0.72rem; padding: 6px; }

        /* Report card */
        .card-header h5 { font-size: 0.78rem; }
        .card-header .btn-sm { font-size: 0.65rem; padding: 4px 8px; }

        /* Table */
        .permits-table { font-size: 0.62rem; min-width: 420px; }
        .permits-table th, .permits-table td { padding: 4px 6px; }
        .permits-table th { font-size: 0.55rem; }

        /* Empty state */
        .empty-report-state { padding: 30px 12px; }
        .empty-report-state .fs-1 { font-size: 1.5rem !important; }
        .empty-report-state h4 { font-size: 0.82rem; }
        .empty-report-state p { font-size: 0.68rem; }

        /* Pagination: prev/next only */
        .pagination .page-item:not(:first-child):not(:nth-child(2)):not(:last-child):not(:nth-last-child(2)) { display: none; }
        .pagination .page-link { font-size: 0.62rem; padding: 3px 7px; }

        /* Charts */
        .chart-card-container { padding: 8px; }
        .chart-card-container canvas { max-height: 160px; }
        .analytics-section { margin-top: 0.75rem; }

        .mb-4 { margin-bottom: 0.6rem !important; }
    }
</style>


<div class="p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0 d-flex align-items-center gap-2" style="color: #1e293b;">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle">
                    <i class="bi bi-bar-chart-line" style="color:#10b981;font-size:1.9rem;"></i>
                </span>
                Analytics Reports
            </h2>
            <p class="text-muted mb-0">Generate and export system reports for data-driven decisions.</p>
        </div>
        <span class="badge bg-primary px-3 py-2 rounded-pill d-inline-flex align-items-center gap-2">
            <i class="bi bi-calendar3"></i>
            <span><?php echo $_now->format($phpDateFormat); ?></span>
            <span class="opacity-50">|</span>
            <i class="bi bi-clock"></i>
            <span id="reportTime"></span>
        </span>
        <script>
        (function () {
            const use12h   = <?php echo $dashTimeFormat === '12h' ? 'true' : 'false'; ?>;
            const timezone = <?php echo json_encode($dashTimezone); ?>;
            function tick() {
                document.getElementById('reportTime').textContent =
                    new Intl.DateTimeFormat('en-PH', {
                        timeZone: timezone,
                        hour: '2-digit', minute: '2-digit', second: '2-digit',
                        hour12: use12h
                    }).format(new Date());
            }
            tick();
            setInterval(tick, 1000);
        })();
        </script>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-info alert-dismissible fade show border-0 shadow-sm rounded-3">
            <i class="bi bi-info-circle-fill me-2"></i>
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="report-main-grid">
        <div class="filter-sidebar d-print-none">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-filter-left me-2 text-primary"></i>Generate Report</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted text-uppercase">Report Type</label>
                            <select class="form-select shadow-sm" name="report_type" required>
                                <option value="applications_summary" <?php echo (isset($_REQUEST['report_type']) && $_REQUEST['report_type'] == 'applications_summary') ? 'selected' : ''; ?>>Applications Summary</option>
                                <option value="permits_issued" <?php echo (isset($_REQUEST['report_type']) && $_REQUEST['report_type'] == 'permits_issued') ? 'selected' : ''; ?>>Permits Issued</option>
                                <option value="zoning_compliance" <?php echo (isset($_REQUEST['report_type']) && $_REQUEST['report_type'] == 'zoning_compliance') ? 'selected' : ''; ?>>Zoning Compliance Report</option>
                                <option value="inspector_performance" <?php echo (isset($_REQUEST['report_type']) && $_REQUEST['report_type'] == 'inspector_performance') ? 'selected' : ''; ?>>Inspector Performance</option>
                                <option value="audit_summary" <?php echo (isset($_REQUEST['report_type']) && $_REQUEST['report_type'] == 'audit_summary') ? 'selected' : ''; ?>>Audit Summary</option>
                                <option value="user_growth" <?php echo (isset($_REQUEST['report_type']) && $_REQUEST['report_type'] == 'user_growth') ? 'selected' : ''; ?>>User Growth Report</option>
                                <option value="monthly_analytics" <?php echo (isset($_REQUEST['report_type']) && $_REQUEST['report_type'] == 'monthly_analytics') ? 'selected' : ''; ?>>Monthly Analytics</option>
                            </select>
                        </div>
                        <div class="row mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted text-uppercase">Date From</label>
                                <input type="date" class="form-control" name="date_from" value="<?php echo $_REQUEST['date_from'] ?? ''; ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted text-uppercase">Date To</label>
                                <input type="date" class="form-control" name="date_to" value="<?php echo $_REQUEST['date_to'] ?? ''; ?>">
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-muted text-uppercase">Year</label>
                            <input type="number" class="form-control" name="year" value="<?php echo $_REQUEST['year'] ?? date('Y'); ?>">
                        </div>
                        <button type="submit" class="btn btn-simulate-gradient w-100 fw-bold py-2 shadow-sm rounded-3">
                            <i class="bi bi-gear-fill me-2"></i>Generate Report
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="report-display-area">
            <?php if ($report && !empty($report['data'])): ?>
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 border-bottom">
                        <h5 class="mb-0 fw-bold text-success"><?php echo htmlspecialchars($report['name']); ?></h5>
                        <div class="d-flex gap-2 d-print-none">
                            <form method="POST" action="/lgu-urban-planning/reports/export.php" id="csvExportForm">
                                <input type="hidden" name="report_data" value="<?php echo htmlspecialchars(json_encode($allDataForExport)); ?>">
                                <input type="hidden" name="export_format" value="csv">
                            </form>
                            <button type="button" class="btn btn-simulate-gradient btn-sm rounded-pill px-3"
                                onclick="openExportModal('csv', 'reports', null)">
                                <i class="bi bi-download"></i> Export All CSV
                            </button>
                            <button type="button" class="btn btn-simulate-gradient btn-sm rounded-pill px-3"
                                onclick="openExportModal('print', 'reports', null)">
                                <i class="bi bi-printer"></i> Print
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-container-fixed paginated-table-onscreen">
                            <table class="table table-hover align-middle mb-0 permits-table">
                                <thead class="table-dark-header">
                                    <tr>
                                        <?php 
                                        $headers = array_keys($report['data'][0]);
                                        foreach ($headers as $header): ?>
                                            <th class="text-uppercase small fw-bold text-secondary"><?php echo str_replace('_', ' ', $header); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report['data'] as $row): ?>
                                        <tr>
                                            <?php foreach ($row as $value): ?>
                                                <td><?php echo htmlspecialchars($value); ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Print-only: full, unpaginated data set (same rows as "Export All CSV")
                             so window.print() never silently drops rows outside the current page. -->
                        <table class="table table-hover align-middle mb-0 permits-table print-only-table">
                            <thead class="table-dark-header">
                                <tr>
                                    <?php foreach ($headers as $header): ?>
                                        <th class="text-uppercase small fw-bold text-secondary"><?php echo str_replace('_', ' ', $header); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allDataForExport as $row): ?>
                                    <tr>
                                        <?php foreach ($row as $value): ?>
                                            <td><?php echo htmlspecialchars($value); ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($report && !empty($report['data'])): ?>
                    <div class="card-footer bg-white py-3 border-top d-print-none">
                        <div class="row align-items-center">
                            <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                                <span class="info-text text-muted">
                                    Showing <strong><?php echo $totalItems > 0 ? ($offset + 1) : 0; ?></strong> to
                                    <strong><?php echo min($offset + $itemsPerPage, $totalItems); ?></strong> of
                                    <strong><?php echo $totalItems; ?></strong> entries
                                </span>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <nav aria-label="Page navigation">
                                    <ul class="pagination pagination-sm justify-content-center justify-content-md-end mb-0">
                                        <?php
                                        $linkParams = $_GET;
                                        unset($linkParams['page']);
                                        $query_string = http_build_query(array_filter($linkParams));
                                        ?>
                                        <li class="page-item <?php echo ($currentPage <= 1) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=1&<?php echo $query_string; ?>"><i class="bi bi-chevron-double-left"></i></a>
                                        </li>
                                        <li class="page-item <?php echo ($currentPage <= 1) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo ($currentPage - 1); ?>&<?php echo $query_string; ?>">Prev</a>
                                        </li>
                                        <?php
                                        $start = max(1, $currentPage - 2);
                                        $end = min($totalPages, $currentPage + 2);
                                        for ($i = $start; $i <= $end; $i++):
                                        ?>
                                            <li class="page-item <?php echo ($currentPage == $i) ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo $query_string; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?php echo ($currentPage >= $totalPages) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo ($currentPage + 1); ?>&<?php echo $query_string; ?>">Next</a>
                                        </li>
                                        <li class="page-item <?php echo ($currentPage >= $totalPages) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $totalPages; ?>&<?php echo $query_string; ?>"><i class="bi bi-chevron-double-right"></i></a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="empty-report-state shadow-sm mb-4">
                    <i class="bi bi-file-earmark-bar-graph fs-1 opacity-25 mb-3 text-primary"></i>
                    <h4 class="fw-bold text-dark mb-2">Ready to Generate</h4>
                    <p class="text-muted text-center mb-0">Select a report type and filters to view data and analytics.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($aiNarrative)): ?>
    <div class="card shadow-sm mb-4 mt-4" style="border-left: 4px solid #6366f1;">
        <div class="card-body">
            <h6 class="fw-bold mb-2 text-uppercase small text-muted">
                <i class="bi bi-stars"></i> AI Insights
            </h6>
            <div style="white-space: pre-line; font-size: 0.92rem; line-height: 1.6;">
                <?php echo htmlspecialchars($aiNarrative); ?>
            </div>
            <p style="font-size: 0.75rem; color: #9ca3af; margin: 10px 0 0;">
                <i class="bi bi-info-circle"></i> AI-generated summary — please verify figures against the table and charts below.
            </p>
        </div>
    </div>
    <?php endif; ?>

    <div class="analytics-section">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="chart-card-container">
                    <h6 class="fw-bold mb-3 text-uppercase small text-muted">Year-on-Year Growth (Total)</h6>
                    <div style="height: 250px;"><canvas id="yoyGrowthChart"></canvas></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="chart-card-container">
                    <h6 class="fw-bold mb-3 text-uppercase small text-muted">Application Status Rate</h6>
                    <div style="height: 250px;"><canvas id="permitDoughnutChart"></canvas></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="chart-card-container">
                    <h6 class="fw-bold mb-3 text-uppercase small text-muted">Monthly Trend (<?php echo $selectedYear ?? date('Y'); ?>)</h6>
                    <div style="height: 250px;"><canvas id="revenueBarChart"></canvas></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="chart-card-container">
                    <h6 class="fw-bold mb-3 text-uppercase small text-muted">Top 5 Barangays by Projects</h6>
                    <div style="height: 300px;"><canvas id="barangayHorizontalChart"></canvas></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="chart-card-container">
                    <h6 class="fw-bold mb-3 text-uppercase small text-muted">Inspector Workload Distribution</h6>
                    <div style="height: 300px;"><canvas id="inspectorWorkloadChart"></canvas></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== TOAST NOTIFICATION CONTAINER ===== -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 9999;">
    <div id="exportToast" class="toast align-items-center border-0 shadow" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body d-flex align-items-center gap-2" id="exportToastBody">
                <i class="bi" id="exportToastIcon" style="font-size:1.1rem;flex-shrink:0;"></i>
                <span id="exportToastMsg"></span>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<!-- ===== SECURE EXPORT / PRINT VERIFICATION MODAL ===== -->
<div class="modal fade" id="exportVerifyModal" tabindex="-1" aria-labelledby="exportVerifyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header text-white">
                <div class="d-flex align-items-center">
                    <span class="modal-header-icon"><i class="bi bi-shield-lock-fill"></i></span>
                    <div>
                        <h5 class="modal-title mb-0" id="exportVerifyModalLabel">Secure Export Verification</h5>
                        <div class="modal-header-subtitle" id="exportVerifySubtitle">Verify your identity to download this export</div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">

                <div class="form-section">
                    <div class="form-section-title" id="exportVerifySectionTitle"><i class="bi bi-file-earmark-arrow-down" id="exportVerifySectionIcon"></i> Export Details</div>

                    <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:0.5rem 0.75rem;" class="d-flex align-items-center gap-2 small mb-4">
                        <i class="bi bi-exclamation-triangle-fill fs-5 text-warning flex-shrink-0"></i>
                        <span id="exportWarningText">You are about to export official report records. Please confirm your identity to proceed.</span>
                    </div>

                    <div id="exportVerifyAlert" class="alert small py-2 mb-3" style="display:none;"></div>

                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Purpose of Export <span class="text-danger">*</span></label>
                            <select id="exportReason" class="form-select">
                                <option value="">— Select a reason —</option>
                                <option value="Reporting">Reporting</option>
                                <option value="Auditing">Auditing</option>
                                <option value="Archiving">Archiving</option>
                                <option value="Compliance Review">Compliance Review</option>
                                <option value="Data Backup">Data Backup</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Admin Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" id="exportPassword" class="form-control"
                                       placeholder="Re-enter your account password">
                                <span class="input-group-text bg-white" style="cursor:pointer;"
                                      onclick="togglePasswordVisibility('exportPassword', 'exportEyeIcon')">
                                    <i class="bi bi-eye-slash" id="exportEyeIcon"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer border-top-0">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary px-4" id="exportVerifyBtn">
                    <span id="exportBtnSpinner" class="spinner-border spinner-border-sm me-1 d-none"></span>
                    <i class="bi bi-download me-1" id="exportBtnIcon"></i> <span id="exportBtnLabel">Verify &amp; Download</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// ===== EXPORT / PRINT VERIFICATION LOGIC (mirrors admin/users.php) =====
const _exportModalEl = document.getElementById('exportVerifyModal');
let _exportType = '', _exportTable = '', _exportUrl = '';

function _elmt(id) { return document.getElementById(id); }

function togglePasswordVisibility(inputId, eyeId) {
    const input = document.getElementById(inputId);
    const eye = document.getElementById(eyeId);
    if (!input || !eye) return;
    if (input.type === "password") {
        input.type = "text";
        eye.classList.replace("bi-eye-slash", "bi-eye");
    } else {
        input.type = "password";
        eye.classList.replace("bi-eye", "bi-eye-slash");
    }
}

function _resetExportModal() {
    var pwd = _elmt('exportPassword');
    if (pwd) {
        pwd.value = '';
        pwd.type  = 'password';
        pwd.classList.remove('is-invalid');
    }
    var reason = _elmt('exportReason');
    if (reason) reason.classList.remove('is-invalid');
    // Reason select's value can't be cleared via classList; reset separately.
    if (reason) reason.value = '';

    var eyeIcon = _elmt('exportEyeIcon');
    if (eyeIcon) eyeIcon.className = 'bi bi-eye-slash';

    var verifyBtn = _elmt('exportVerifyBtn');
    if (verifyBtn) verifyBtn.disabled = false;

    var spinner = _elmt('exportBtnSpinner');
    if (spinner) spinner.classList.add('d-none');

    var btnIcon = _elmt('exportBtnIcon');
    if (btnIcon) btnIcon.classList.remove('d-none');

    var alertBox = _elmt('exportVerifyAlert');
    if (alertBox) alertBox.style.display = 'none';
}

(function () {
    var reasonEl = _elmt('exportReason');
    if (reasonEl) {
        reasonEl.addEventListener('change', function () {
            if (this.value) this.classList.remove('is-invalid');
        });
    }
    var passwordEl = _elmt('exportPassword');
    if (passwordEl) {
        passwordEl.addEventListener('input', function () {
            if (this.value.trim()) this.classList.remove('is-invalid');
        });
    }
})();

function _setBtnLoading(on) {
    var verifyBtn = _elmt('exportVerifyBtn');
    if (verifyBtn) verifyBtn.disabled = on;
    var spinner = _elmt('exportBtnSpinner');
    if (spinner) spinner.classList.toggle('d-none', !on);
    var btnIcon = _elmt('exportBtnIcon');
    if (btnIcon) btnIcon.classList.toggle('d-none', on);
}

function _showToast(msg, type) {
    var toastEl   = _elmt('exportToast');
    var toastMsg  = _elmt('exportToastMsg');
    var toastIcon = _elmt('exportToastIcon');
    if (!toastEl || !toastMsg || !toastIcon) return;

    var config = {
        warning: { bg: 'bg-warning',  text: 'text-dark',  icon: 'bi-exclamation-triangle-fill' },
        danger:  { bg: 'bg-danger',   text: 'text-white', icon: 'bi-x-circle-fill'              },
        success: { bg: 'bg-success',  text: 'text-white', icon: 'bi-check-circle-fill'          },
        info:    { bg: 'bg-info',     text: 'text-dark',  icon: 'bi-info-circle-fill'           }
    };
    var c = config[type] || config['info'];

    toastEl.className = 'toast align-items-center border-0 shadow ' + c.bg + ' ' + c.text;
    toastIcon.className = 'bi ' + c.icon;
    toastMsg.innerText = msg;

    var bsToast = bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 3500 });
    bsToast.show();
}

function openExportModal(type, table, downloadUrl) {
    // Defensive cleanup FIRST, unconditionally, every time this is called.
    // Never gate opening on a flag/event that might not fire (that's what
    // caused the "2 clicks then nothing works" regression) — instead just
    // make sure no stray backdrop or body-lock state from a previous
    // cycle is left over before showing a fresh modal. Idempotent/safe
    // even if nothing needs cleaning up.
    document.querySelectorAll('.modal-backdrop').forEach(function (el) { el.remove(); });
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('padding-right');

    _exportType  = type.toUpperCase();
    _exportTable = table;
    _exportUrl   = downloadUrl ? new URL(downloadUrl, window.location.href).href : null;

    _resetExportModal();

    var isPrint = _exportType === 'PRINT';
    var subtitleEl = _elmt('exportVerifySubtitle');
    if (subtitleEl) {
        subtitleEl.textContent = isPrint
            ? 'Verify your identity to print this report'
            : 'Verify your identity to download this export';
    }
    var sectionTitleEl = _elmt('exportVerifySectionTitle');
    if (sectionTitleEl) {
        sectionTitleEl.innerHTML =
            '<i class="bi ' + (isPrint ? 'bi-printer' : 'bi-file-earmark-arrow-down') + '" id="exportVerifySectionIcon"></i> ' +
            (isPrint ? 'Print Details' : 'Export Details');
    }
    var warningEl = _elmt('exportWarningText');
    if (warningEl) {
        warningEl.textContent = isPrint
            ? 'You are about to print official report records. Please confirm your identity to proceed.'
            : 'You are about to export official report records. Please confirm your identity to proceed.';
    }
    var btnLabelEl = _elmt('exportBtnLabel');
    if (btnLabelEl) btnLabelEl.textContent = isPrint ? 'Verify & Print' : 'Verify & Download';
    var btnIconEl = _elmt('exportBtnIcon');
    if (btnIconEl) btnIconEl.className = isPrint ? 'bi bi-printer me-1' : 'bi bi-download me-1';

    bootstrap.Modal.getOrCreateInstance(_exportModalEl).show();
}

_exportModalEl.addEventListener('hide.bs.modal', function () {
    var focused = _exportModalEl.querySelector(':focus');
    if (focused) focused.blur();
});

_exportModalEl.addEventListener('hidden.bs.modal', function () {
    // Same cleanup on the way out too, as a second safety net — but nothing
    // else depends on this event actually firing anymore.
    document.querySelectorAll('.modal-backdrop').forEach(function (el) { el.remove(); });
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('padding-right');
});

function submitExportVerification() {
    var reasonEl    = _elmt('exportReason');
    var passwordEl  = _elmt('exportPassword');
    if (!reasonEl || !passwordEl) {
        _showToast('The export form is not ready. Please refresh the page and try again.', 'danger');
        return;
    }

    var password    = passwordEl.value.trim();
    var reason      = reasonEl.value;
    var missing     = false;

    reasonEl.classList.remove('is-invalid');
    passwordEl.classList.remove('is-invalid');

    if (!reason) {
        reasonEl.classList.add('is-invalid');
        missing = true;
    }
    if (!password) {
        passwordEl.classList.add('is-invalid');
        missing = true;
    }

    if (missing) {
        if (!reason && !password) {
            _showToast('Please select a purpose and enter your password to continue.', 'warning');
        } else if (!reason) {
            _showToast('Please select a purpose for this export.', 'warning');
        } else {
            _showToast('Please enter your password to continue.', 'warning');
        }
        return;
    }

    _setBtnLoading(true);

    var fd = new FormData();
    fd.append('password',    password);
    fd.append('reason',      reason);
    fd.append('export_type', _exportType);
    fd.append('table_name',  _exportTable);

    fetch('../admin/verify_action.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function(res) {
            if (!res.ok) throw new Error('Server error: ' + res.status);
            return res.json();
        })
        .then(function(data) {
            if (!data.success) {
                _setBtnLoading(false);
                _showToast(data.message || 'Incorrect password. Export denied.', 'danger');
                return;
            }

            if (_exportType === 'PRINT') {
                _showToast('Verification successful. Opening print dialog...', 'success');
                setTimeout(function() {
                    _setBtnLoading(false);
                    bootstrap.Modal.getOrCreateInstance(_exportModalEl).hide();
                    setTimeout(function() { window.print(); }, 300);
                }, 800);
                return;
            }

            // CSV export — password verified, submit the hidden POST form carrying the report data
            var form = document.getElementById('csvExportForm');
            if (!form) {
                _setBtnLoading(false);
                _showToast('Export form not found on this page. Please refresh and try again.', 'danger');
                return;
            }
            _showToast('Verification successful. Starting download...', 'success');
            var tokenInput = form.querySelector('input[name="export_token"]');
            if (!tokenInput) {
                tokenInput = document.createElement('input');
                tokenInput.type = 'hidden';
                tokenInput.name = 'export_token';
                form.appendChild(tokenInput);
            }
            tokenInput.value = data.token;

            setTimeout(function() {
                _setBtnLoading(false);
                bootstrap.Modal.getOrCreateInstance(_exportModalEl).hide();
                form.submit();
            }, 800);
        })
        .catch(function() {
            _setBtnLoading(false);
            _showToast('Network error. Please try again.', 'danger');
        });
}

(function () {
    var verifyBtn = _elmt('exportVerifyBtn');
    if (verifyBtn) verifyBtn.onclick = submitExportVerification;
})();
// ===== END EXPORT / PRINT VERIFICATION LOGIC =====
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const statusValues = <?php echo json_encode(array_values($chartData['status'])); ?>;
    const monthLabels = <?php echo json_encode(array_keys($chartData['months'])); ?>;
    const monthValues = <?php echo json_encode(array_values($chartData['months'])); ?>;
    const brgyLabels = <?php echo json_encode(array_keys($chartData['barangays'])); ?>;
    const brgyValues = <?php echo json_encode(array_values($chartData['barangays'])); ?>;
    const inspectorLabels = <?php echo json_encode(array_column($inspectorWorkload, 'inspector_name')); ?>;
    const inspectorValues = <?php echo json_encode(array_map('intval', array_column($inspectorWorkload, 'total_inspections'))); ?>;
    const yoyCurrent = <?php echo (int)$chartData['yoy_comparison']['current']; ?>;
    const yoyPrev = <?php echo (int)$chartData['yoy_comparison']['previous']; ?>;
    const currentYearStr = '<?php echo $selectedYear; ?>';
    const prevYearStr = '<?php echo $selectedYear - 1; ?>';
    const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';

    if (isDark) {
        Chart.defaults.color = '#94a3b8'; 
        Chart.defaults.scale.grid.color = 'rgba(255, 255, 255, 0.1)'; 
    }

    // New YoY Comparison Chart
    new Chart(document.getElementById('yoyGrowthChart'), {
        type: 'bar',
        data: {
            labels: [prevYearStr, currentYearStr],
            datasets: [{
                label: 'Total Applications',
                data: [yoyPrev, yoyCurrent],
                backgroundColor: isDark ? ['#475569', '#10b981'] : ['#94a3b8', '#10b981'],
                borderRadius: 8
            }]
        },
        options: { 
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });

    new Chart(document.getElementById('permitDoughnutChart'), {
        type: 'doughnut',
        data: {
            labels: ['Approved', 'Rejected', 'Pending'],
            datasets: [{
                data: statusValues,
                backgroundColor: ['#10b981', '#ef4444', '#f59e0b'],
                hoverOffset: 10
            }]
        },
        options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });

    new Chart(document.getElementById('revenueBarChart'), {
        type: 'bar',
        data: {
            labels: monthLabels,
            datasets: [{
                label: 'Applications',
                data: monthValues,
                backgroundColor: '#3b82f6',
                borderRadius: 5
            }]
        },
        options: { 
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } 
        }
    });

    new Chart(document.getElementById('barangayHorizontalChart'), {
        type: 'bar',
        data: {
            labels: brgyLabels.length ? brgyLabels : ['No Data'],
            datasets: [{
                label: 'Project Count',
                data: brgyValues.length ? brgyValues : [0],
                backgroundColor: '#6366f1',
                borderRadius: 5
            }]
        },
        options: { 
            indexAxis: 'y', 
            maintainAspectRatio: false,
            plugins: { legend: { display: false } }
        }
    });

    new Chart(document.getElementById('inspectorWorkloadChart'), {
        type: 'bar',
        data: {
            labels: inspectorLabels.length ? inspectorLabels : ['No Data'],
            datasets: [{
                label: 'Inspections Assigned',
                data: inspectorValues.length ? inspectorValues : [0],
                backgroundColor: '#f59e0b',
                borderRadius: 5
            }]
        },
        options: {
            indexAxis: 'y',
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });
});
</script>

<?php include __DIR__ . '/../admin/footer.php'; ?>