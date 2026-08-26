<?php

// Audit Logs

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helper.php';
require_once __DIR__ . '/../modules/UserAccessManagement/UserController.php';

$auth = new Auth();
$auth->requirePermission('view_audit_logs');
$auth->requireRole(['admin', 'super_admin']);

$userController = new UserController();
$db     = Database::getInstance();
$dbConn = $db->getConnection();

// ── Audit log helper (mirrors users.php / modules/PermitProcessing/view.php) ─
function logAudit(PDO $pdo, int $userId, string $action, string $entityType, int $entityId, string $details): void {
    try {
        $pdo->prepare(
            "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
        )->execute([
            $userId,
            $action,
            $entityType,
            $entityId,
            $details,
            $_SERVER['REMOTE_ADDR']  ?? '0.0.0.0',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        ]);
    } catch (Exception $e) {
        // Silent fail — auditing should never block the main action
    }
}

// ── Load language from session (written by settings.php on every save) ────────
$lang = $_SESSION['locale_language'] ?? 'en_PH';

// ── Translation strings for audit-logs.php ───────────────────────────────────
$translations = [
    'en_PH' => [
        // Page
        'page_title'            => 'Audit Logs | LGU Urban Planning',
        'page_heading'          => 'Audit Logs',
        'page_subtitle'         => 'Official activity logs for transparency and administrative monitoring.',
        // Header buttons
        'btn_generate_report'   => 'EXPORT CSV',
        // Filter labels & buttons
        'filter_action_label'   => 'Action Type',
        'filter_action_ph'      => 'Search action...',
        'filter_date_from'      => 'Date From',
        'filter_date_to'        => 'Date To',
        'btn_apply_filters'     => 'APPLY FILTERS',
        'btn_reset'             => 'RESET',
        // Table headers
        'th_severity'           => 'Severity',
        'th_timestamp'          => 'Timestamp',
        'th_user'               => 'User',
        'th_action'             => 'Action',
        'th_ip'                 => 'IP Address',
        'th_ref_id'             => 'Reference ID',
        // Table body
        'no_records'            => 'No audit records found matching your filters.',
        // Severity badges
        'severity_critical'     => 'CRITICAL',
        'severity_warning'      => 'WARNING',
        'severity_info'         => 'INFO',
        // Pagination
        'pg_showing'            => 'Showing',
        'pg_to'                 => 'to',
        'pg_of'                 => 'of',
        'pg_entries'            => 'entries',
        'pg_prev'               => 'Prev',
        'pg_next'               => 'Next',
        // Log detail modal
        'modal_log_performed'   => 'Performed By',
        'modal_log_ip'          => 'IP Address',
        'modal_log_timestamp'   => 'Timestamp',
        'modal_log_device'      => 'Device / Browser Info',
        'modal_log_changes'     => 'Changes Made',
        'modal_log_no_changes'  => 'No specific data changes recorded.',
        'btn_close'             => 'Close',
        // Export modal
        'export_modal_title'    => 'Secure Export Verification',
        'export_warning'        => 'You are about to export official audit records. Please confirm your identity to proceed.',
        'export_purpose_label'  => 'Purpose of Export',
        'export_purpose_ph'     => '— Select a reason —',
        'export_password_label' => 'Admin Password',
        'export_password_ph'    => 'Re-enter your account password',
        'btn_cancel'            => 'Cancel',
        'btn_verify_download'   => 'Verify & Download',
        'btn_print'             => 'Print',
        'btn_verify_print'      => 'Verify & Print',
        // JS toast / alert strings (passed to JS via json_encode)
        'js_export_select_reason'   => 'Please select a purpose for this export.',
        'js_export_enter_password'  => 'Please enter your password to continue.',
        'js_export_success'         => 'Verification successful. Starting download...',
        'js_export_network_error'   => 'Network error. Please try again.',
        'js_print_subtitle'         => 'Verify your identity to print these records',
        'js_print_warning'          => 'You are about to print official audit records. Please confirm your identity to proceed.',
        'js_print_success'          => 'Verification successful. Opening print dialog...',
    ],
    'fil' => [
        // Page
        'page_title'            => 'Mga Audit Log | LGU Urban Planning',
        'page_heading'          => 'Mga Audit Log',
        'page_subtitle'         => 'Mga opisyal na log ng aktibidad para sa transparency at administratibong pagmamatyag.',
        // Header buttons
        'btn_generate_report'   => 'I-EXPORT ANG CSV',
        // Filter labels & buttons
        'filter_action_label'   => 'Uri ng Aksyon',
        'filter_action_ph'      => 'Maghanap ng aksyon...',
        'filter_date_from'      => 'Petsa Mula',
        'filter_date_to'        => 'Petsa Hanggang',
        'btn_apply_filters'     => 'ILAPAT ANG MGA FILTER',
        'btn_reset'             => 'I-RESET',
        // Table headers
        'th_severity'           => 'Antas',
        'th_timestamp'          => 'Timestamp',
        'th_user'               => 'Gumagamit',
        'th_action'             => 'Aksyon',
        'th_ip'                 => 'IP Address',
        'th_ref_id'             => 'Reference ID',
        // Table body
        'no_records'            => 'Walang mga rekord ng audit na natuklasan na akma sa iyong mga filter.',
        // Severity badges
        'severity_critical'     => 'KRITIKAL',
        'severity_warning'      => 'BABALA',
        'severity_info'         => 'IMPORMASYON',
        // Pagination
        'pg_showing'            => 'Ipinapakita',
        'pg_to'                 => 'hanggang',
        'pg_of'                 => 'sa',
        'pg_entries'            => 'mga entry',
        'pg_prev'               => 'Nakaraan',
        'pg_next'               => 'Susunod',
        // Log detail modal
        'modal_log_performed'   => 'Ginawa Ni',
        'modal_log_ip'          => 'IP Address',
        'modal_log_timestamp'   => 'Timestamp',
        'modal_log_device'      => 'Device / Browser Info',
        'modal_log_changes'     => 'Mga Pagbabagong Ginawa',
        'modal_log_no_changes'  => 'Walang partikular na pagbabago ng datos na naitala.',
        'btn_close'             => 'Isara',
        // Export modal
        'export_modal_title'    => 'Secure na Pag-verify ng Export',
        'export_warning'        => 'Mag-e-export ka ng mga opisyal na audit record. Mangyaring kumpirmahin ang iyong pagkakakilanlan upang magpatuloy.',
        'export_purpose_label'  => 'Layunin ng Export',
        'export_purpose_ph'     => '— Pumili ng dahilan —',
        'export_password_label' => 'Password ng Admin',
        'export_password_ph'    => 'Muling ilagay ang iyong password',
        'btn_cancel'            => 'Kanselahin',
        'btn_verify_download'   => 'I-verify at I-download',
        'btn_print'             => 'I-print',
        'btn_verify_print'      => 'I-verify at I-print',
        // JS toast / alert strings
        'js_export_select_reason'   => 'Mangyaring pumili ng layunin para sa export na ito.',
        'js_export_enter_password'  => 'Mangyaring ilagay ang iyong password upang magpatuloy.',
        'js_export_success'         => 'Matagumpay na na-verify. Nagsisimula na ang pag-download...',
        'js_export_network_error'   => 'Error sa network. Mangyaring subukan ulit.',
        'js_print_subtitle'         => 'I-verify ang iyong pagkakakilanlan upang i-print ang mga rekord na ito',
        'js_print_warning'          => 'Mag-i-print ka ng mga opisyal na audit record. Mangyaring kumpirmahin ang iyong pagkakakilanlan upang magpatuloy.',
        'js_print_success'          => 'Matagumpay na na-verify. Bubuksan na ang print dialog...',
    ],
];

// Helper: get translated string, fallback to English
function t_audit(string $key, array $translations, string $lang): string {
    return $translations[$lang][$key] ?? $translations['en_PH'][$key] ?? $key;
}

// Helper Function

// Same classification as getSeverityTag(), but returns a plain key so the
// live-search JS can build the badge markup client-side without re-sending HTML.
function getSeverityKey($action) {
    $action = strtolower($action);
    if (strpos($action, 'delete') !== false || strpos($action, 'remove') !== false || strpos($action, 'config') !== false || strpos($action, 'setting') !== false) {
        return 'critical';
    }
    if (strpos($action, 'update') !== false || strpos($action, 'edit') !== false || strpos($action, 'password') !== false || strpos($action, 'profile') !== false || strpos($action, 'change') !== false) {
        return 'warning';
    }
    return 'info';
}

function getSeverityTag($action, $translations, $lang) {
    $action = strtolower($action);
    
    // CRITICAL: Deletion or System Changes
    if (strpos($action, 'delete') !== false || strpos($action, 'remove') !== false || strpos($action, 'config') !== false || strpos($action, 'setting') !== false) {
        $label = $translations[$lang]['severity_critical'] ?? $translations['en_PH']['severity_critical'];
        return '<span class="badge bg-danger text-white border-0 shadow-sm px-2 py-1"><i class="bi bi-exclamation-octagon me-1"></i>' . $label . '</span>';
    }
    
    // WARNING: Profile updates or Password changes
    if (strpos($action, 'update') !== false || strpos($action, 'edit') !== false || strpos($action, 'password') !== false || strpos($action, 'profile') !== false || strpos($action, 'change') !== false) {
        $label = $translations[$lang]['severity_warning'] ?? $translations['en_PH']['severity_warning'];
        return '<span class="badge bg-warning text-dark border-0 shadow-sm px-2 py-1"><i class="bi bi-exclamation-triangle me-1"></i>' . $label . '</span>';
    }
    
    // INFO: Login, Logout, View (Default)
    $label = $translations[$lang]['severity_info'] ?? $translations['en_PH']['severity_info'];
    return '<span class="badge bg-info text-white border-0 shadow-sm px-2 py-1"><i class="bi bi-info-circle me-1"></i>' . $label . '</span>';
}

// --- FILTERS ---
$filters = [
    'action'    => $_GET['action'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to'   => $_GET['date_to'] ?? ''
];

// --- AJAX LIVE SEARCH HANDLER (mirrors admin/users.php's ?action=search_users) ---
if (isset($_GET['ajax']) && $_GET['ajax'] === 'search_logs') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json');

    try {
        $searchPage   = isset($_GET['p']) && is_numeric($_GET['p']) ? (int)$_GET['p'] : 1;
        if ($searchPage < 1) $searchPage = 1;
        $searchLimit  = 15;
        $searchOffset = ($searchPage - 1) * $searchLimit;

        $searchFilters = [
            'action'    => $_GET['action'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to'   => $_GET['date_to'] ?? ''
        ];

        $searchTotal      = $userController->getTotalAuditLogsCount($searchFilters);
        $searchTotalPages = max(1, ceil($searchTotal / $searchLimit));
        $searchLogs       = $userController->getAuditLogs($searchFilters, $searchLimit, $searchOffset);

        $rows = [];
        foreach ($searchLogs as $log) {
            $rows[] = [
                'user'        => $log['username'] ?? 'SYSTEM',
                'action'      => $log['action'],
                'time'        => Helper::formatDateTime($log['created_at']),
                'details'     => $log['details'],
                'ip'          => $log['ip_address'],
                'agent'       => $log['user_agent'] ?? 'Unknown Device',
                'entity_type' => $log['entity_type'] ?? '',
                'entity_id'   => $log['entity_id'] ?? '',
                'severity'    => getSeverityKey($log['action']),
            ];
        }

        echo json_encode([
            'success'    => true,
            'rows'       => $rows,
            'totalLogs'  => (int)$searchTotal,
            'totalPages' => (int)$searchTotalPages,
            'page'       => $searchPage,
            'limit'      => $searchLimit,
            'offset'     => $searchOffset,
            'labels'     => [
                'no_records'         => t_audit('no_records', $translations, $lang),
                'showing'            => t_audit('pg_showing', $translations, $lang),
                'to'                 => t_audit('pg_to', $translations, $lang),
                'of'                 => t_audit('pg_of', $translations, $lang),
                'entries'            => t_audit('pg_entries', $translations, $lang),
                'prev'               => t_audit('pg_prev', $translations, $lang),
                'next'               => t_audit('pg_next', $translations, $lang),
                'severity_critical'  => t_audit('severity_critical', $translations, $lang),
                'severity_warning'   => t_audit('severity_warning', $translations, $lang),
                'severity_info'      => t_audit('severity_info', $translations, $lang),
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// --- 1. EXPORT HANDLER (token-gated) ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Validate the one-time export token issued by verify_action.php
    $submittedToken = $_GET['export_token'] ?? '';
    $sessionToken   = $_SESSION['export_token'] ?? '';
    $tokenExpiry    = $_SESSION['export_token_expires'] ?? '';
    $tokenTable     = $_SESSION['export_token_table'] ?? '';

    $tokenValid = (
        !empty($submittedToken) &&
        !empty($sessionToken) &&
        hash_equals($sessionToken, $submittedToken) &&
        $tokenTable === 'audit_logs' &&
        strtotime($tokenExpiry) >= time()
    );

    // Invalidate token immediately (one-time use)
    unset($_SESSION['export_token'], $_SESSION['export_token_expires'],
          $_SESSION['export_token_table'], $_SESSION['export_token_type']);

    if (!$tokenValid) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:2rem;text-align:center;">
             <h3>&#128274; Export Denied</h3>
             <p>Invalid or expired export token. Please use the Export button and complete verification.</p>
             <a href="audit-logs.php">Go back</a></div>');
    }

    $allLogs = $userController->getAuditLogs($filters, 999999, 0);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Audit_Logs_Report_' . date('Y-m-d_His') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    fputcsv($output, ['Timestamp', 'Officer/User', 'Action', 'Entity Type', 'Entity ID', 'Details', 'IP Address', 'User Agent']);

    foreach ($allLogs as $log) {
        fputcsv($output, [
            $log['created_at'],
            $log['username'] ?? 'SYSTEM',
            $log['action'],
            $log['entity_type'] ?? 'N/A',
            $log['entity_id'] ?? 'N/A',
            $log['details'],
            $log['ip_address'],
            $log['user_agent'] ?? 'N/A'
        ]);
    }
    fclose($output);

    logAudit($dbConn, (int)($_SESSION['user_id'] ?? 0), 'export_audit_logs', 'audit_log', 0,
        'Exported Audit Logs CSV.');
    exit;
}

// --- 2. PAGINATION CONFIGURATION ---
$limit = 15; 
$page = isset($_GET['p']) && is_numeric($_GET['p']) ? (int)$_GET['p'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// --- 3. DATA FETCHING ---
$totalLogs  = $userController->getTotalAuditLogsCount($filters);
$totalPages = max(1, ceil($totalLogs / $limit));
$logs       = $userController->getAuditLogs($filters, $limit, $offset);

$query_string = http_build_query(array_filter($filters));

$pageTitle = t_audit('page_title', $translations, $lang);
$isAuthPage = true;
include __DIR__ . '/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<style>
    /* ── Gradient action button (copied from users.php style) ── */
    .btn-export-gradient {
        background: linear-gradient(135deg, #0f7a4e 0%, #17a566 100%);
        border: none;
        color: #fff;
        border-radius: 9px;
        font-weight: 600;
        box-shadow: 0 4px 12px rgba(23, 165, 102, 0.32);
        transition: transform 0.12s ease, box-shadow 0.12s ease, color 0.12s ease;
    }
    .btn-export-gradient:hover,
    .btn-export-gradient:focus,
    .btn-export-gradient:active {
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(23, 165, 102, 0.4);
    }
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
    .pagination .page-link { color: #2c3e50; border: 1px solid #dee2e6; margin: 0 2px; border-radius: 4px; }
    .pagination .page-item.active .page-link { background-color: #0d6efd; border-color: #0d6efd; color: white; }
    .pagination .page-link:hover { background-color: #e7f1ff; border-color: #b6d4fe; color: #0d6efd; }
    .info-text { font-size: 0.875rem; color: #6c757d; }
    .table-lgu thead { background-color: #f8f9fa; border-top: 2px solid #1a5c2b; }
    .breadcrumb-item a { color: #1a5c2b; text-decoration: none; }
    .table-hover tbody tr { cursor: pointer; transition: background 0.2s; }
    .table-hover tbody tr:hover { background-color: rgba(26, 92, 43, 0.05) !important; }
    .text-device { font-size: 0.75rem; color: #95a5a6; }
    .badge { font-size: 0.65rem; letter-spacing: 0.5px; font-weight: 700; }

    /* ── 1024px: Laptop ────────────────────────────────────────────────────── */
    @media (max-width: 1024px) {

        .p-4.page-container { padding: 1.5rem !important; }

        .row.align-items-center.mb-4 h2 { font-size: 1.5rem; }
        .btn-export-gradient { font-size: 0.85rem; }

        .card-body.p-3 { padding: 0.85rem !important; }

        .table-lgu { font-size: 0.85rem; }
        .table-lgu th, .table-lgu td { padding: 0.6rem 0.5rem; }
    }

    /* ── 768px: Tablet ─────────────────────────────────────────────────────── */
    @media (max-width: 768px) {

        .p-4.page-container { padding: 1rem !important; }

        /* Header: stack title and action buttons */
        .row.align-items-center.mb-4 { flex-direction: column; align-items: flex-start !important; gap: 12px; }
        .row.align-items-center.mb-4 .col-md-6 { width: 100%; flex: 0 0 100%; }
        .row.align-items-center.mb-4 .col-md-6.text-md-end {
            text-align: left !important;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .row.align-items-center.mb-4 h2 { font-size: 1.3rem; }
        .row.align-items-center.mb-4 .btn-export-gradient,
        .row.align-items-center.mb-4 .btn-simulate-gradient { font-size: 0.78rem; padding: 7px 12px; }

        /* Filter card: full-width fields
           (col-md-4 — the Apply Filters/Reset button group — was missing
           here, so at exactly 768px it still sat at Bootstrap's md-default
           33.33% column width. The .btn-group.w-100 rule below only makes
           the button group fill *that* narrow column, which is why "APPLY
           FILTERS" was wrapping onto two lines while "RESET" stayed short.) */
        .card .row.g-2 .col-md-3,
        .card .row.g-2 .col-md-2,
        .card .row.g-2 .col-md-4,
        .card .row.g-2 .col-md-5 { width: 100%; flex: 0 0 100%; }
        .btn-group.w-100 { width: 100% !important; }
        .btn-group.w-100 .btn { white-space: nowrap; }

        /* Table: hide IP Address + Reference ID */
        .table-lgu thead th:nth-child(5),
        .table-lgu tbody td:nth-child(5),
        .table-lgu thead th:nth-child(6),
        .table-lgu tbody td:nth-child(6) { display: none; }

        .table-lgu { font-size: 0.78rem; }
        .table-lgu th, .table-lgu td { padding: 0.5rem 0.4rem; }

        /* Guard against header/cell text (e.g. "Timestamp") breaking
           mid-word once the row gets tight — let the table-responsive
           wrapper's horizontal scroll take over instead. */
        .table-lgu th, .table-lgu td { white-space: nowrap; }
        .table-responsive .table-lgu { min-width: 480px; }

        /* Bootstrap's .input-group wraps by default, which can push a
           trailing icon/button (e.g. the export password eye toggle)
           onto its own line once its column gets narrow. */
        .input-group { flex-wrap: nowrap; }

        /* Pagination */
        .card-footer .row { flex-direction: column; gap: 10px; text-align: center; }
        .card-footer .col-md-6:last-child { text-align: center !important; }
        .pagination { justify-content: center !important; }
    }

    /* ── Flatpickr (Date range picker) — no responsive rules existed at all,
       so the calendar rendered at its library-default fixed width (~307px)
       regardless of viewport. On narrower phones that either overflows the
       screen edge or leaves the popup oddly narrower/wider than the input
       it belongs to. Make it fluid on tablet and below. ── */
    @media (max-width: 768px) {
        .flatpickr-calendar {
            width: min(307px, calc(100vw - 1.5rem)) !important;
            max-width: calc(100vw - 1.5rem);
        }
        .flatpickr-calendar.arrowTop,
        .flatpickr-calendar.arrowBottom { left: 0.75rem !important; }
        .flatpickr-days, .dayContainer { width: 100% !important; max-width: 100%; }
        .flatpickr-day { max-width: none; }
    }
    @media (max-width: 480px) {
        .flatpickr-calendar { width: calc(100vw - 1rem) !important; max-width: calc(100vw - 1rem); font-size: 90%; }
        .flatpickr-calendar.arrowTop,
        .flatpickr-calendar.arrowBottom { left: 0.5rem !important; }
        .flatpickr-current-month { font-size: 0.95rem; }
        .flatpickr-day { line-height: 32px; height: 32px; }
    }
    @media (max-width: 320px) {
        .flatpickr-calendar { width: calc(100vw - 0.6rem) !important; max-width: calc(100vw - 0.6rem); font-size: 82%; }
        .flatpickr-day { line-height: 28px; height: 28px; }
    }

    /* ── 480px: Large Mobile ───────────────────────────────────────────────── */
    @media (max-width: 480px) {

        .p-4.page-container { padding: 0.75rem !important; }

        /* Header */
        .row.align-items-center.mb-4 h2 { font-size: 1.1rem; }
        .row.align-items-center.mb-4 p { font-size: 0.75rem; }
        .row.align-items-center.mb-4 .btn-export-gradient,
        .row.align-items-center.mb-4 .btn-simulate-gradient {
            font-size: 0.72rem;
            padding: 6px 10px;
            width: 100%;
            justify-content: center;
        }

        /* Filter card */
        .card.border-0.shadow-sm.mb-4 .card-body { padding: 0.75rem !important; }
        .card .row.g-2 { --bs-gutter-y: 0.4rem; }
        .form-control-sm { font-size: 0.78rem; }
        .form-label { font-size: 0.65rem !important; }
        .btn-group .btn-sm { font-size: 0.75rem; padding: 6px 10px; }

        /* Table: hide User + IP Address + Reference ID */
        .table-lgu thead th:nth-child(3),
        .table-lgu tbody td:nth-child(3),
        .table-lgu thead th:nth-child(5),
        .table-lgu tbody td:nth-child(5),
        .table-lgu thead th:nth-child(6),
        .table-lgu tbody td:nth-child(6) { display: none; }

        .table-lgu { font-size: 0.72rem; }
        .table-lgu th, .table-lgu td { padding: 0.4rem 0.3rem; white-space: nowrap; }
        .table-responsive .table-lgu { min-width: 360px; }
        .table-lgu td .badge { font-size: 0.6rem; padding: 3px 6px; }
        .table-lgu td span.badge.bg-light {
            font-size: 0.65rem;
            max-width: 110px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: inline-block;
            vertical-align: middle;
        }

        /* Pagination */
        .pagination .page-link { font-size: 0.72rem; padding: 4px 8px; }
        .card-footer { padding: 0.6rem 0.75rem; }
        .info-text { font-size: 0.72rem; }

        /* Modals */
        .modal-body { padding: 1rem; font-size: 0.82rem; }
        .modal-header { padding: 0.75rem 1rem; }
        .modal-footer .btn { font-size: 0.78rem; padding: 6px 12px; }

        /* Export/print verification modal footer — Bootstrap's default
           modal-footer just does flex-wrap: wrap, so once "Cancel" and
           "Verify & Print" no longer both fit on one line, the first
           button (Cancel) drops to its own short row and the second
           (Verify & Print) wraps to a nearly-full-width row underneath —
           an accidental, lopsided stack rather than a designed one. Make
           both buttons stack as full-width, equal-width buttons instead,
           with the primary action on top. */
        #exportVerifyModal .modal-footer {
            flex-direction: column-reverse;
            align-items: stretch;
        }
        #exportVerifyModal .modal-footer .btn {
            width: 100%;
            margin: 0;
        }
    }

    /* ── 320px: Small Mobile ───────────────────────────────────────────────── */
    @media (max-width: 320px) {

        .p-4.page-container { padding: 0.5rem !important; }

        /* Header */
        .row.align-items-center.mb-4 h2 { font-size: 0.95rem; }
        .row.align-items-center.mb-4 p { font-size: 0.68rem; }
        .row.align-items-center.mb-4 .btn-export-gradient,
        .row.align-items-center.mb-4 .btn-simulate-gradient { font-size: 0.68rem; padding: 5px 8px; }

        /* Filter */
        .card.border-0.shadow-sm.mb-4 .card-body { padding: 0.5rem !important; }
        .form-control-sm { font-size: 0.72rem; padding: 3px 6px; }
        .btn-group .btn-sm { font-size: 0.68rem; padding: 5px 8px; }

        /* Table: keep only Severity + Timestamp + Action */
        .table-lgu thead th:nth-child(3),
        .table-lgu tbody td:nth-child(3),
        .table-lgu thead th:nth-child(5),
        .table-lgu tbody td:nth-child(5),
        .table-lgu thead th:nth-child(6),
        .table-lgu tbody td:nth-child(6) { display: none; }

        .table-lgu { font-size: 0.65rem; }
        .table-lgu th, .table-lgu td { padding: 0.3rem 0.2rem; white-space: nowrap; }
        .table-responsive .table-lgu { min-width: 300px; }
        .table-lgu td .badge { font-size: 0.55rem; padding: 2px 5px; }
        .table-lgu td span.badge.bg-light {
            max-width: 80px;
            font-size: 0.58rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: inline-block;
            vertical-align: middle;
        }

        /* Pagination: show only prev/next + current */
        .pagination .page-item:not(:first-child):not(:nth-child(2)):not(:last-child):not(:nth-last-child(2)) { display: none; }
        .pagination .page-link { font-size: 0.65rem; padding: 3px 7px; }
        .card-footer { padding: 0.5rem; }
        .info-text { font-size: 0.65rem; }

        /* Modals */
        .modal-dialog { margin: 0.4rem; }
        .modal-body { padding: 0.6rem; font-size: 0.75rem; }
        .modal-header { padding: 0.5rem 0.6rem; }
        .modal-footer { padding: 0.4rem 0.6rem; }
        .modal-footer .btn { font-size: 0.68rem; padding: 4px 8px; }
    }

    /* ================================================
       EXPORT VERIFY MODAL — style copied from users.php
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
        margin-bottom: 0;
    }
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
    #exportVerifyModal .export-warning-box {
        background: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 6px;
        padding: 0.5rem 0.75rem;
        color: #664d03;
    }
    #exportVerifyModal .modal-body .form-label {
        font-weight: 600;
        font-size: 0.76rem;
        text-transform: uppercase;
        letter-spacing: 0.02em;
        color: #5a6474;
        margin-bottom: 0.4rem;
        display: block;
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
    /* The password placeholder ("Re-enter your account password") is longer
       than the field is wide once the input-group's eye-toggle button takes
       its share of a full-width mobile field. Without this, the browser just
       hard-clips the text mid-word ("...your accou|"). Truncate with an
       ellipsis instead, and shrink the font a bit on narrow screens so more
       of the label is actually readable. */
    #exportVerifyModal .modal-body .input-group .form-control::placeholder {
        text-overflow: ellipsis;
    }
    #exportVerifyModal .modal-body .input-group .form-control {
        text-overflow: ellipsis;
        overflow: hidden;
        white-space: nowrap;
    }
    @media (max-width: 480px) {
        #exportVerifyModal .modal-body .input-group .form-control {
            font-size: 0.82rem;
            padding-left: 0.65rem;
        }
    }
    @media (max-width: 320px) {
        #exportVerifyModal .modal-body .input-group .form-control {
            font-size: 0.74rem;
            padding-left: 0.55rem;
        }
    }
    #exportVerifyModal .modal-body .form-control:focus,
    #exportVerifyModal .modal-body .form-select:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.12);
        background-color: #ffffff;
    }
    #exportVerifyModal .modal-body .form-control::placeholder { color: #a7b0bd; }
    /* The generic form-control/form-select rule above (id-scoped) outranks
       Bootstrap's own ":not(:first-child)/:not(:last-child)" input-group
       selectors, so it was re-rounding every corner of the password field
       and eye-toggle button, making them look like two separate, disconnected
       pills instead of one joined control (most noticeable on narrow/mobile
       widths where the input-group stretches full width). Restore the
       joined look. */
    #exportVerifyModal .modal-body .input-group .form-control:not(:last-child) {
        border-top-right-radius: 0;
        border-bottom-right-radius: 0;
        border-right: none;
    }
    #exportVerifyModal .modal-body .input-group .input-group-text {
        border: 1.5px solid #e2e6ec;
        border-left: none;
        border-top-right-radius: 9px;
        border-bottom-right-radius: 9px;
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
        background-color: #fcfdfe;
    }
    #exportVerifyModal .modal-body .input-group:has(.form-control:focus) .input-group-text {
        border-color: #0d6efd;
    }
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
        color: #fff;
        box-shadow: 0 4px 12px rgba(13, 110, 253, 0.28);
    }
    #exportVerifyModal .modal-footer .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(13, 110, 253, 0.35);
        color: #fff;
    }
    #exportVerifyModal .modal-footer .btn-light {
        border: 1.5px solid #dde1e7;
        color: #5a6474;
        background: #fff;
    }
    #exportVerifyModal .modal-footer .btn-light:hover {
        background: #f6f8fb;
        border-color: #c7cdd6;
    }
    #exportVerifyModal .modal-body .form-control.is-invalid,
    #exportVerifyModal .modal-body .form-select.is-invalid {
        border-color: #dc3545;
        background-color: #fff;
    }
    #exportVerifyModal .modal-body .form-control.is-invalid:focus,
    #exportVerifyModal .modal-body .form-select.is-invalid:focus {
        border-color: #dc3545;
        box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.12);
    }
    #exportVerifyModal .modal-body .input-group:has(.form-control.is-invalid) .input-group-text {
        border-color: #dc3545;
    }

    /* ================================================
       LOG DETAIL MODAL — restyled to match #createUserModal
       in admin/users.php
       ================================================ */
    #logModal .modal-content {
        border-radius: 16px;
        overflow: hidden;
    }
    #logModal .modal-header {
        background: linear-gradient(135deg, #1c4e9e 0%, #0d6efd 100%);
        border-bottom: none;
        padding: 1.25rem 1.5rem;
    }
    #logModal .modal-header-icon {
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
    #logModal .modal-title {
        font-size: 1.15rem;
        font-weight: 700;
        letter-spacing: -0.01em;
    }
    #logModal .header-subtitle {
        font-size: 0.8rem;
        color: rgba(255, 255, 255, 0.75);
        margin-top: 1px;
    }

    #logModal .modal-body {
        background: #f6f8fb;
        padding: 1.75rem;
    }

    #logModal .form-section {
        background: #ffffff;
        border: 1px solid #eaeef3;
        border-radius: 12px;
        padding: 1.25rem 1.5rem 1.5rem;
        margin-bottom: 1.25rem;
    }
    #logModal .form-section:last-child { margin-bottom: 0; }

    #logModal .form-section-label {
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
    #logModal .form-section-label i {
        font-size: 0.95rem;
        color: #0d6efd;
    }

    #logModal label.field-label {
        font-weight: 600;
        font-size: 0.76rem;
        text-transform: uppercase;
        letter-spacing: 0.02em;
        color: #5a6474;
        margin-bottom: 0.4rem;
        display: block;
    }

    #logModal .field-value {
        font-size: 0.9rem;
        color: #1e2530;
    }

    #logModal .device-box {
        background: #fcfdfe;
        border: 1.5px solid #e2e6ec;
        border-radius: 9px;
        padding: 0.7rem 0.9rem;
        overflow-wrap: break-word;
        word-break: break-word;
    }
    /* modalAgentRaw previously had a hardcoded inline font-size: 0.72rem,
       which (being inline) beat the .modal-body mobile font-size overrides
       below and left the raw user-agent string too small/hard to read on
       phones, with no wrapping guard for long unbroken tokens. */
    #logModal .device-box-raw {
        font-size: 0.78rem;
    }
    @media (max-width: 480px) {
        #logModal .device-box-raw { font-size: 0.74rem; }
    }

    #logModal .changes-box {
        background: #1e2530;
        color: #e7eaf0;
        border-radius: 9px;
        padding: 0.9rem 1rem;
        font-family: monospace;
        font-size: 0.82rem;
        white-space: pre-wrap;
    }

    #logModal .modal-footer {
        background: #ffffff;
        border-top: 1px solid #eef0f3;
        padding: 1.1rem 1.5rem;
        gap: 0.6rem;
    }
    #logModal .modal-footer .btn {
        border-radius: 9px;
        font-weight: 600;
        font-size: 0.88rem;
        padding: 0.55rem 1.4rem;
        transition: transform 0.12s ease, box-shadow 0.12s ease;
    }
    #logModal .modal-footer .btn-outline-secondary {
        border: 1.5px solid #dde1e7;
        color: #5a6474;
        background: #fff;
    }
    #logModal .modal-footer .btn-outline-secondary:hover {
        background: #f6f8fb;
        border-color: #c7cdd6;
    }

    /* ── Print styles (Audit Logs "Print" button) ── */
    @media print {
        .d-print-none { display: none !important; }
        .card, .shadow-sm { box-shadow: none !important; border: 1px solid #dee2e6 !important; }
        body { background: #fff !important; }
    }

    /* ================================================
       DARK MODE — respects [data-bs-theme="dark"] set on <html>
       by header.php. Overrides the hardcoded light-mode colors
       above (this page predates the site-wide theme toggle).
       ================================================ */
    [data-bs-theme="dark"] h2.text-dark { color: #eef1f6 !important; }
    [data-bs-theme="dark"] .info-text { color: #9aa4b5; }
    [data-bs-theme="dark"] .text-device { color: #7d8798; }
    [data-bs-theme="dark"] .breadcrumb-item a { color: #4caf7d; }

    [data-bs-theme="dark"] .table-lgu thead { background-color: #1a2130; border-top-color: #2e8b52; }
    [data-bs-theme="dark"] .table-hover tbody tr:hover { background-color: rgba(76, 175, 125, 0.08) !important; }
    [data-bs-theme="dark"] .badge.bg-light.text-dark {
        background-color: #2a3242 !important;
        color: #d6dbe4 !important;
        border-color: #3a4356 !important;
    }

    [data-bs-theme="dark"] .pagination .page-link { color: #cdd5e0; background-color: #1e2530; border-color: #333d4d; }
    [data-bs-theme="dark"] .pagination .page-item.active .page-link { background-color: #0d6efd; border-color: #0d6efd; color: #fff; }
    [data-bs-theme="dark"] .pagination .page-link:hover { background-color: #26314a; border-color: #3a5b9c; color: #7fb2ff; }

    /* Filter row's date-range calendar icon uses Bootstrap's .bg-white
       utility (!important), which otherwise stays white in dark mode. */
    [data-bs-theme="dark"] #auditDateRangeInput.form-control,
    [data-bs-theme="dark"] .input-group .input-group-text.bg-white {
        background-color: #232b3a !important;
        border-color: #333d4d !important;
        color: #9aa4b5 !important;
    }
    [data-bs-theme="dark"] .input-group .input-group-text.bg-white i { color: #9aa4b5 !important; }

    /* Export verify modal */
    [data-bs-theme="dark"] #exportVerifyModal .modal-body { background: #151a24; }
    [data-bs-theme="dark"] #exportVerifyModal .form-section { background: #1e2530; border-color: #2c3547; }
    [data-bs-theme="dark"] #exportVerifyModal .form-section-title { color: #7fb2ff; border-bottom-color: #2c3547; }
    [data-bs-theme="dark"] #exportVerifyModal .export-warning-box {
        background: #4d3c0a;
        border-color: #a17f0a;
        color: #ffe69c;
    }
    [data-bs-theme="dark"] #exportVerifyModal .export-warning-box .text-warning { color: #ffd452 !important; }
    [data-bs-theme="dark"] #exportVerifyModal .modal-body .form-label { color: #a9b2c3; }
    [data-bs-theme="dark"] #exportVerifyModal .modal-body .form-control,
    [data-bs-theme="dark"] #exportVerifyModal .modal-body .form-select {
        background-color: #232b3a;
        border-color: #333d4d;
        color: #e7eaf0;
    }
    [data-bs-theme="dark"] #exportVerifyModal .modal-body .form-control:focus,
    [data-bs-theme="dark"] #exportVerifyModal .modal-body .form-select:focus {
        background-color: #232b3a;
        border-color: #0d6efd;
    }
    [data-bs-theme="dark"] #exportVerifyModal .modal-body .form-control::placeholder { color: #6b7787; }
    [data-bs-theme="dark"] #exportVerifyModal .modal-body .input-group-text {
        background-color: #232b3a;
        border-color: #333d4d;
        color: #a9b2c3;
    }
    [data-bs-theme="dark"] #exportVerifyModal .modal-body .form-control.is-invalid,
    [data-bs-theme="dark"] #exportVerifyModal .modal-body .form-select.is-invalid { background-color: #232b3a; }
    [data-bs-theme="dark"] #exportVerifyModal .modal-footer { background: #1e2530; border-top-color: #2c3547; }
    [data-bs-theme="dark"] #exportVerifyModal .modal-footer .btn-light {
        background: #232b3a;
        border-color: #333d4d;
        color: #cdd5e0;
    }
    [data-bs-theme="dark"] #exportVerifyModal .modal-footer .btn-light:hover {
        background: #2a3242;
        border-color: #3a4356;
    }

    /* Log detail modal */
    [data-bs-theme="dark"] #logModal .modal-body { background: #151a24; }
    [data-bs-theme="dark"] #logModal .form-section { background: #1e2530; border-color: #2c3547; }
    [data-bs-theme="dark"] #logModal .form-section-label { color: #7fb2ff; border-bottom-color: #2c3547; }
    [data-bs-theme="dark"] #logModal label.field-label { color: #a9b2c3; }
    [data-bs-theme="dark"] #logModal .field-value { color: #e7eaf0; }
    [data-bs-theme="dark"] #logModal .device-box { background: #1a2130; border-color: #2c3547; }
    [data-bs-theme="dark"] #logModal .modal-footer { background: #1e2530; border-top-color: #2c3547; }
    [data-bs-theme="dark"] #logModal .modal-footer .btn-outline-secondary {
        background: #232b3a;
        border-color: #333d4d;
        color: #cdd5e0;
    }
    [data-bs-theme="dark"] #logModal .modal-footer .btn-outline-secondary:hover {
        background: #2a3242;
        border-color: #3a4356;
    }
</style>

<div class="p-4 page-container">

    <div class="row align-items-center mb-4">
        <div class="col-md-6">
            <h2 class="fw-bold text-dark mb-1">
                <i class="bi bi-shield-check text-success me-2"></i><?= t_audit('page_heading', $translations, $lang) ?>
            </h2>
            <p class="text-muted small mb-0"><?= t_audit('page_subtitle', $translations, $lang) ?></p>
        </div>
        <div class="col-md-6 text-md-end d-print-none">
            <button type="button" class="btn btn-export-gradient shadow-sm"
                onclick="openExportModal('csv', 'audit_logs', '?export=csv&<?= $query_string ?>')">
                <i class="bi bi-download"></i> <?= t_audit('btn_generate_report', $translations, $lang) ?>
            </button>
            <button type="button" class="btn btn-simulate-gradient shadow-sm"
                onclick="openExportModal('print', 'audit_logs', null)">
                <i class="bi bi-printer"></i> <?= t_audit('btn_print', $translations, $lang) ?>
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4 d-print-none">
        <div class="card-body p-3">
            <form method="GET" class="row g-2 align-items-end" id="auditFilterForm" onsubmit="return false;">
                <div class="col-md-3">
                    <div class="position-relative">
                        <input type="text" class="form-control form-control-sm" id="searchInput" name="action" placeholder="<?= t_audit('filter_action_ph', $translations, $lang) ?>" value="<?= htmlspecialchars($filters['action']) ?>">
                        <span id="searchSpinner" class="spinner-border spinner-border-sm text-primary" style="display:none; position:absolute; right:10px; top:50%; transform:translateY(-50%);"></span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-calendar3 text-muted"></i></span>
                        <input type="text" class="form-control border-start-0 border-end-0" id="auditDateRangeInput"
                               placeholder="Date range" autocomplete="off" readonly>
                        <button type="button" id="auditClearDateRange"
                                class="btn btn-outline-secondary <?= (empty($filters['date_from']) && empty($filters['date_to'])) ? 'd-none' : '' ?>"
                                title="Clear">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                    <input type="hidden" name="date_from" id="dateFromFilter" value="<?= htmlspecialchars($filters['date_from']) ?>">
                    <input type="hidden" name="date_to" id="dateToFilter" value="<?= htmlspecialchars($filters['date_to']) ?>">
                </div>
                <div class="col-md-4">
                    <div class="btn-group w-100 shadow-sm">
                        <button type="submit" class="btn btn-simulate-gradient btn-sm px-4 fw-bold"><?= t_audit('btn_apply_filters', $translations, $lang) ?></button>
                        <a href="audit-logs.php" class="btn btn-outline-secondary btn-sm fw-bold"><i class="bi bi-arrow-counterclockwise me-1"></i><?= t_audit('btn_reset', $translations, $lang) ?></a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-lgu table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4 py-3 text-muted small text-uppercase"><?= t_audit('th_severity', $translations, $lang) ?></th>
                            <th class="text-muted small text-uppercase"><?= t_audit('th_timestamp', $translations, $lang) ?></th>
                            <th class="text-muted small text-uppercase"><?= t_audit('th_user', $translations, $lang) ?></th>
                            <th class="text-muted small text-uppercase"><?= t_audit('th_action', $translations, $lang) ?></th>
                            <th class="text-muted small text-uppercase"><?= t_audit('th_ip', $translations, $lang) ?></th>
                            <th class="text-muted small text-uppercase"><?= t_audit('th_ref_id', $translations, $lang) ?></th>
                        </tr>
                    </thead>
                    <tbody id="auditTableBody">
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted small italic"><?= t_audit('no_records', $translations, $lang) ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                            <tr onclick="showLogDetails(this)" 
                                data-user="<?= htmlspecialchars($log['username'] ?? 'SYSTEM') ?>"
                                data-action="<?= htmlspecialchars($log['action']) ?>"
                                data-time="<?= Helper::formatDateTime($log['created_at']) ?>"
                                data-details="<?= htmlspecialchars($log['details']) ?>"
                                data-ip="<?= htmlspecialchars($log['ip_address']) ?>"
                                data-agent="<?= htmlspecialchars($log['user_agent'] ?? 'Unknown Device') ?>">
                                <td class="ps-4"><?= getSeverityTag($log['action'], $translations, $lang) ?></td>
                                <td class="small text-secondary"><?= Helper::formatDateTime($log['created_at']) ?></td>
                                <td>
                                    <div class="fw-bold text-primary small"><?= htmlspecialchars($log['username'] ?? 'SYSTEM') ?></div>
                                </td>
                                <td><span class="badge bg-light text-dark border fw-normal px-2 py-1"><?= htmlspecialchars($log['action']) ?></span></td>
                                <td class="small font-monospace text-muted"><?= htmlspecialchars($log['ip_address']) ?></td>
                                <td class="small text-muted">
                                    <?= $log['entity_type'] ? (htmlspecialchars($log['entity_type']) . " <span class='text-secondary fw-bold'>#" . $log['entity_id'] . "</span>") : '<span class="text-muted opacity-50">-</span>' ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-footer py-3 border-0 d-print-none">
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                    <span class="info-text text-muted" id="paginationInfo">
                        <?= t_audit('pg_showing', $translations, $lang) ?> <strong><?= ($offset + 1) ?></strong> <?= t_audit('pg_to', $translations, $lang) ?> 
                        <strong><?= min($offset + $limit, $totalLogs) ?></strong> <?= t_audit('pg_of', $translations, $lang) ?> 
                        <strong><?= $totalLogs ?></strong> <?= t_audit('pg_entries', $translations, $lang) ?>
                    </span>
                </div>
                <div class="col-md-6 text-md-end">
                    <nav aria-label="Page navigation">
                        <ul class="pagination pagination-sm justify-content-center justify-content-md-end mb-0" id="paginationNav">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?p=1&<?= $query_string ?>"><i class="bi bi-chevron-double-left"></i></a>
                            </li>
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?p=<?= ($page - 1) ?>&<?= $query_string ?>"><?= t_audit('pg_prev', $translations, $lang) ?></a>
                            </li>
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($totalPages, $page + 2);
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                    <a class="page-link" href="?p=<?= $i ?>&<?= $query_string ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?p=<?= ($page + 1) ?>&<?= $query_string ?>"><?= t_audit('pg_next', $translations, $lang) ?></a>
                            </li>
                            <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?p=<?= $totalPages ?>&<?= $query_string ?>"><i class="bi bi-chevron-double-right"></i></a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="logModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header text-white">
                <div class="d-flex align-items-center">
                    <span class="modal-header-icon"><i class="bi bi-clock-history"></i></span>
                    <div>
                        <h5 class="modal-title mb-0" id="modalTitle">Activity Details</h5>
                        <div class="header-subtitle">Full record of this audit log entry</div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">

                <div class="form-section">
                    <div class="form-section-label"><i class="bi bi-person-vcard"></i> Activity Overview</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="field-label"><?= t_audit('modal_log_performed', $translations, $lang) ?></label>
                            <span id="modalUser" class="field-value fw-bold text-primary d-block"></span>
                        </div>
                        <div class="col-md-6">
                            <label class="field-label"><?= t_audit('modal_log_ip', $translations, $lang) ?></label>
                            <span id="modalIP" class="field-value font-monospace d-block"></span>
                        </div>
                        <div class="col-12">
                            <label class="field-label"><?= t_audit('modal_log_timestamp', $translations, $lang) ?></label>
                            <span id="modalTime" class="field-value d-block"></span>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-label"><i class="bi bi-display"></i> <?= t_audit('modal_log_device', $translations, $lang) ?></div>
                    <div class="device-box">
                        <span id="modalAgentDisplay" class="fw-bold d-block mb-1"></span>
                        <span id="modalAgentRaw" class="text-muted small device-box-raw"></span>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-label"><i class="bi bi-file-diff"></i> <?= t_audit('modal_log_changes', $translations, $lang) ?></div>
                    <div id="modalDetails" class="changes-box"></div>
                </div>

            </div>
            <div class="modal-footer border-top-0">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal"><?= t_audit('btn_close', $translations, $lang) ?></button>
            </div>
        </div>
    </div>
</div>

<!-- ===== TOAST NOTIFICATION CONTAINER ===== -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 9999;">
    <div id="auditToast" class="toast align-items-center border-0 shadow" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body d-flex align-items-center gap-2">
                <i class="bi" id="auditToastIcon" style="font-size:1.1rem;flex-shrink:0;"></i>
                <span id="auditToastMsg"></span>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<!-- ===== SECURE EXPORT VERIFICATION MODAL ===== -->
<div class="modal fade" id="exportVerifyModal" tabindex="-1" aria-labelledby="exportVerifyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header text-white">
                <div class="d-flex align-items-center">
                    <span class="modal-header-icon"><i class="bi bi-shield-lock-fill"></i></span>
                    <div>
                        <h5 class="modal-title mb-0" id="exportVerifyModalLabel"><?= t_audit('export_modal_title', $translations, $lang) ?></h5>
                        <div class="modal-header-subtitle" id="exportVerifySubtitle">Verify your identity to download this export</div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">

                <div class="form-section">
                    <div class="form-section-title" id="exportVerifySectionTitle"><i class="bi bi-file-earmark-arrow-down" id="exportVerifySectionIcon"></i> Export Details</div>

                    <div class="export-warning-box d-flex align-items-center gap-2 small mb-4">
                        <i class="bi bi-exclamation-triangle-fill fs-5 text-warning flex-shrink-0"></i>
                        <span id="exportWarningText"><?= t_audit('export_warning', $translations, $lang) ?></span>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label"><?= t_audit('export_purpose_label', $translations, $lang) ?> <span class="text-danger">*</span></label>
                            <select id="exportReason" class="form-select">
                                <option value=""><?= t_audit('export_purpose_ph', $translations, $lang) ?></option>
                                <option value="Reporting">Reporting</option>
                                <option value="Auditing">Auditing</option>
                                <option value="Archiving">Archiving</option>
                                <option value="Compliance Review">Compliance Review</option>
                                <option value="Data Backup">Data Backup</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label"><?= t_audit('export_password_label', $translations, $lang) ?> <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" id="exportPassword" class="form-control"
                                       placeholder="<?= t_audit('export_password_ph', $translations, $lang) ?>">
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
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal"><?= t_audit('btn_cancel', $translations, $lang) ?></button>
                <button type="button" class="btn btn-primary px-4" id="exportVerifyBtn">
                    <span id="exportBtnSpinner" class="spinner-border spinner-border-sm me-1 d-none"></span>
                    <i class="bi bi-download me-1" id="exportBtnIcon"></i> <span id="exportBtnLabel"><?= t_audit('btn_verify_download', $translations, $lang) ?></span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// ── Translations passed from PHP ──────────────────────────────────────────────
const AUDIT_T = <?php echo json_encode([
    'export_select_reason'  => t_audit('js_export_select_reason',  $translations, $lang),
    'export_enter_password' => t_audit('js_export_enter_password', $translations, $lang),
    'export_success'        => t_audit('js_export_success',        $translations, $lang),
    'export_network_error'  => t_audit('js_export_network_error',  $translations, $lang),
    'no_changes'            => t_audit('modal_log_no_changes',     $translations, $lang),
    'export_warning'        => t_audit('export_warning',           $translations, $lang),
    'btn_verify_download'   => t_audit('btn_verify_download',      $translations, $lang),
    'btn_verify_print'      => t_audit('btn_verify_print',         $translations, $lang),
    'print_subtitle'        => t_audit('js_print_subtitle',        $translations, $lang),
    'print_warning'         => t_audit('js_print_warning',         $translations, $lang),
    'print_success'         => t_audit('js_print_success',         $translations, $lang),
]); ?>;
</script>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="../assets/js/admin-audit-logs.js"></script>

<?php include __DIR__ . '/footer.php'; ?>