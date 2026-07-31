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
        .btn-export-gradient { font-size: 0.78rem; padding: 7px 12px; }

        /* Filter card: full-width fields */
        .card .row.g-2 .col-md-3,
        .card .row.g-2 .col-md-2,
        .card .row.g-2 .col-md-5 { width: 100%; flex: 0 0 100%; }
        .btn-group.w-100 { width: 100% !important; }

        /* Table: hide IP Address + Reference ID */
        .table-lgu thead th:nth-child(5),
        .table-lgu tbody td:nth-child(5),
        .table-lgu thead th:nth-child(6),
        .table-lgu tbody td:nth-child(6) { display: none; }

        .table-lgu { font-size: 0.78rem; }
        .table-lgu th, .table-lgu td { padding: 0.5rem 0.4rem; }

        /* Pagination */
        .card-footer .row { flex-direction: column; gap: 10px; text-align: center; }
        .card-footer .col-md-6:last-child { text-align: center !important; }
        .pagination { justify-content: center !important; }
    }

    /* ── 480px: Large Mobile ───────────────────────────────────────────────── */
    @media (max-width: 480px) {

        .p-4.page-container { padding: 0.75rem !important; }

        /* Header */
        .row.align-items-center.mb-4 h2 { font-size: 1.1rem; }
        .row.align-items-center.mb-4 p { font-size: 0.75rem; }
        .btn-export-gradient {
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
        .table-lgu th, .table-lgu td { padding: 0.4rem 0.3rem; }
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
    }

    /* ── 320px: Small Mobile ───────────────────────────────────────────────── */
    @media (max-width: 320px) {

        .p-4.page-container { padding: 0.5rem !important; }

        /* Header */
        .row.align-items-center.mb-4 h2 { font-size: 0.95rem; }
        .row.align-items-center.mb-4 p { font-size: 0.68rem; }
        .btn-export-gradient { font-size: 0.68rem; padding: 5px 8px; }

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
        .table-lgu th, .table-lgu td { padding: 0.3rem 0.2rem; }
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
                        <span id="modalAgentRaw" class="text-muted small" style="font-size: 0.72rem;"></span>
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

                    <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:0.5rem 0.75rem;" class="d-flex align-items-center gap-2 small mb-4">
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

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

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

const CURRENT_FILE = window.location.pathname.split('/').pop() || 'audit-logs.php';

// ===== LIVE SEARCH LOGIC (mirrors admin/users.php) =====
(function () {
    const searchInput    = document.getElementById('searchInput');
    const dateFromFilter = document.getElementById('dateFromFilter');
    const dateToFilter   = document.getElementById('dateToFilter');
    const searchSpinner  = document.getElementById('searchSpinner');
    const tbody          = document.getElementById('auditTableBody');
    const paginationInfo = document.getElementById('paginationInfo');
    const paginationNav  = document.getElementById('paginationNav');
    const filterForm     = document.getElementById('auditFilterForm');

    let debounceTimer = null;
    let currentPage   = 1;
    let requestSeq    = 0; // guards against out-of-order responses

    function esc(str) {
        return String(str ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    function buildUrl(page) {
        const params = new URLSearchParams();
        params.set('ajax', 'search_logs');
        params.set('action', searchInput.value.trim());
        params.set('date_from', dateFromFilter.value);
        params.set('date_to', dateToFilter.value);
        params.set('p', page);
        return `${CURRENT_FILE}?${params.toString()}`;
    }

    function severityBadge(severity, labels) {
        const config = {
            critical: { cls: 'bg-danger text-white',  icon: 'bi-exclamation-octagon', label: labels.severity_critical },
            warning:  { cls: 'bg-warning text-dark',   icon: 'bi-exclamation-triangle', label: labels.severity_warning },
            info:     { cls: 'bg-info text-white',     icon: 'bi-info-circle',          label: labels.severity_info }
        };
        const c = config[severity] || config.info;
        return `<span class="badge ${c.cls} border-0 shadow-sm px-2 py-1"><i class="bi ${c.icon} me-1"></i>${esc(c.label)}</span>`;
    }

    function rowHtml(log, labels) {
        const refCell = log.entity_type
            ? `${esc(log.entity_type)} <span class="text-secondary fw-bold">#${esc(log.entity_id)}</span>`
            : `<span class="text-muted opacity-50">-</span>`;

        return `<tr onclick="showLogDetails(this)"
            data-user="${esc(log.user)}"
            data-action="${esc(log.action)}"
            data-time="${esc(log.time)}"
            data-details="${esc(log.details)}"
            data-ip="${esc(log.ip)}"
            data-agent="${esc(log.agent)}">
            <td class="ps-4">${severityBadge(log.severity, labels)}</td>
            <td class="small text-secondary">${esc(log.time)}</td>
            <td><div class="fw-bold text-primary small">${esc(log.user)}</div></td>
            <td><span class="badge bg-light text-dark border fw-normal px-2 py-1">${esc(log.action)}</span></td>
            <td class="small font-monospace text-muted">${esc(log.ip)}</td>
            <td class="small text-muted">${refCell}</td>
        </tr>`;
    }

    function renderPagination(data) {
        const { page, totalPages, labels } = data;
        const items = [];
        const item = (label, targetPage, disabled, active) => `
            <li class="page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}">
                <a class="page-link" href="#" data-page="${targetPage}">${label}</a>
            </li>`;

        items.push(item('<i class="bi bi-chevron-double-left"></i>', 1, page <= 1, false));
        items.push(item(esc(labels.prev), page - 1, page <= 1, false));
        const start = Math.max(1, page - 2);
        const end = Math.min(totalPages, page + 2);
        for (let i = start; i <= end; i++) {
            items.push(item(i, i, false, page === i));
        }
        items.push(item(esc(labels.next), page + 1, page >= totalPages, false));
        items.push(item('<i class="bi bi-chevron-double-right"></i>', totalPages, page >= totalPages, false));

        paginationNav.innerHTML = items.join('');
        paginationNav.querySelectorAll('a.page-link').forEach(a => {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                const p = parseInt(this.dataset.page, 10);
                if (!isNaN(p) && p >= 1) doSearch(p);
            });
        });
    }

    function renderInfo(data) {
        const { totalLogs, offset, limit, labels } = data;
        const from = totalLogs > 0 ? offset + 1 : 0;
        const to = Math.min(offset + limit, totalLogs);
        paginationInfo.innerHTML = `${esc(labels.showing)} <strong>${from}</strong> ${esc(labels.to)}
            <strong>${to}</strong> ${esc(labels.of)}
            <strong>${totalLogs}</strong> ${esc(labels.entries)}`;
    }

    function doSearch(page) {
        currentPage = page || 1;
        const seq = ++requestSeq;
        searchSpinner.style.display = 'inline-block';

        fetch(buildUrl(currentPage))
            .then(res => res.json())
            .then(data => {
                if (seq !== requestSeq) return; // stale response, ignore
                if (!data.success) return;

                if (!data.rows.length) {
                    tbody.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-muted small italic">${esc(data.labels.no_records)}</td></tr>`;
                } else {
                    tbody.innerHTML = data.rows.map(log => rowHtml(log, data.labels)).join('');
                }
                renderInfo(data);
                renderPagination(data);

                // Reflect state in the URL for shareable/bookmarkable links, without reloading
                const qp = new URLSearchParams();
                if (searchInput.value.trim()) qp.set('action', searchInput.value.trim());
                if (dateFromFilter.value) qp.set('date_from', dateFromFilter.value);
                if (dateToFilter.value) qp.set('date_to', dateToFilter.value);
                if (currentPage > 1) qp.set('p', currentPage);
                const newUrl = window.location.pathname + (qp.toString() ? '?' + qp.toString() : '');
                history.replaceState(null, '', newUrl);
            })
            .catch(() => { /* silently ignore network hiccups */ })
            .finally(() => { searchSpinner.style.display = 'none'; });
    }

    searchInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => doSearch(1), 350);
    });

    // Date range picker — single clickable field showing "from - to" (mirrors applications.php)
    const dateRangeInput = document.getElementById('auditDateRangeInput');
    const clearDateBtn   = document.getElementById('auditClearDateRange');

    if (dateRangeInput && window.flatpickr) {
        const initialDates = [];
        if (dateFromFilter.value) initialDates.push(dateFromFilter.value);
        if (dateToFilter.value)   initialDates.push(dateToFilter.value);

        const dateRangePicker = flatpickr(dateRangeInput, {
            mode: 'range',
            dateFormat: 'M j, Y',
            defaultDate: initialDates.length ? initialDates : undefined,
            onClose: function (selectedDates, dateStr, instance) {
                if (selectedDates.length === 2) {
                    dateFromFilter.value = instance.formatDate(selectedDates[0], 'Y-m-d');
                    dateToFilter.value   = instance.formatDate(selectedDates[1], 'Y-m-d');
                    clearDateBtn.classList.remove('d-none');
                    clearTimeout(debounceTimer);
                    doSearch(1);
                }
            }
        });

        clearDateBtn.addEventListener('click', function () {
            dateRangePicker.clear();
            dateFromFilter.value = '';
            dateToFilter.value   = '';
            clearDateBtn.classList.add('d-none');
            clearTimeout(debounceTimer);
            doSearch(1);
        });
    }

    // "Apply Filters" button still works, just without a full page reload
    filterForm.addEventListener('submit', function (e) {
        e.preventDefault();
        clearTimeout(debounceTimer);
        doSearch(1);
    });
})();

// ===== EXPORT VERIFICATION LOGIC =====
const _exportModalEl = document.getElementById('exportVerifyModal');
let _exportType = '', _exportTable = '', _exportUrl = '';

/* ---- shared helper ---- */
function _elmt(id) { return document.getElementById(id); }

/* ---- shared toggle password visibility ---- */
function togglePasswordVisibility(inputId, eyeId) {
    var input = document.getElementById(inputId);
    var eye   = document.getElementById(eyeId);
    if (input.type === 'password') {
        input.type = 'text';
        eye.classList.replace('bi-eye-slash', 'bi-eye');
    } else {
        input.type = 'password';
        eye.classList.replace('bi-eye', 'bi-eye-slash');
    }
}

/* ---- shared toast ---- */
function _showToast(msg, type) {
    var toastEl   = _elmt('auditToast');
    var toastMsg  = _elmt('auditToastMsg');
    var toastIcon = _elmt('auditToastIcon');
    var config = {
        warning: { bg: 'bg-warning', text: 'text-dark',  icon: 'bi-exclamation-triangle-fill' },
        danger:  { bg: 'bg-danger',  text: 'text-white', icon: 'bi-x-circle-fill'             },
        success: { bg: 'bg-success', text: 'text-white', icon: 'bi-check-circle-fill'         },
        info:    { bg: 'bg-info',    text: 'text-dark',  icon: 'bi-info-circle-fill'          }
    };
    var c = config[type] || config['info'];
    toastEl.className   = 'toast align-items-center border-0 shadow ' + c.bg + ' ' + c.text;
    toastIcon.className = 'bi ' + c.icon;
    toastMsg.innerText  = msg;
    bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 3500 }).show();
}

/* ---- export: reset modal ---- */
function _resetExportModal() {
    _elmt('exportPassword').value    = '';
    _elmt('exportPassword').type     = 'password';
    _elmt('exportReason').value      = '';
    _elmt('exportPassword').classList.remove('is-invalid');
    _elmt('exportReason').classList.remove('is-invalid');
    _elmt('exportEyeIcon').className = 'bi bi-eye-slash';
    _elmt('exportVerifyBtn').disabled = false;
    _elmt('exportBtnSpinner').classList.add('d-none');
    _elmt('exportBtnIcon').classList.remove('d-none');
}

_elmt('exportReason').addEventListener('change', function () {
    if (this.value) this.classList.remove('is-invalid');
});
_elmt('exportPassword').addEventListener('input', function () {
    if (this.value.trim()) this.classList.remove('is-invalid');
});

/* ---- export: open modal ---- */
function openExportModal(type, table, downloadUrl) {
    _exportType  = type.toUpperCase();
    _exportTable = table;
    _exportUrl   = downloadUrl ? new URL(downloadUrl, window.location.href).href : null;

    _resetExportModal();

    var isPrint = _exportType === 'PRINT';
    _elmt('exportVerifySubtitle').textContent = isPrint
        ? AUDIT_T.print_subtitle
        : 'Verify your identity to download this export';
    _elmt('exportVerifySectionTitle').innerHTML =
        '<i class="bi ' + (isPrint ? 'bi-printer' : 'bi-file-earmark-arrow-down') + '" id="exportVerifySectionIcon"></i> ' +
        (isPrint ? 'Print Details' : 'Export Details');
    _elmt('exportWarningText').textContent = isPrint
        ? AUDIT_T.print_warning
        : AUDIT_T.export_warning;
    _elmt('exportBtnLabel').textContent = isPrint
        ? AUDIT_T.btn_verify_print
        : AUDIT_T.btn_verify_download;
    _elmt('exportBtnIcon').className = isPrint ? 'bi bi-printer me-1' : 'bi bi-download me-1';

    bootstrap.Modal.getOrCreateInstance(_exportModalEl).show();
}

/* ---- clean up on close ---- */
_exportModalEl.addEventListener('hide.bs.modal', function () {
    var f = _exportModalEl.querySelector(':focus'); if (f) f.blur();
});

/* ---- export: loading state ---- */
function _setExportBtnLoading(on) {
    _elmt('exportVerifyBtn').disabled = on;
    _elmt('exportBtnSpinner').classList.toggle('d-none', !on);
    _elmt('exportBtnIcon').classList.toggle('d-none', on);
}

/* ---- export: submit ---- */
function submitExportVerification() {
    var password    = _elmt('exportPassword').value.trim();
    var reason      = _elmt('exportReason').value;
    var reasonEl    = _elmt('exportReason');
    var passwordEl  = _elmt('exportPassword');
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
            _showToast(AUDIT_T.export_select_reason, 'warning');
        } else {
            _showToast(AUDIT_T.export_enter_password, 'warning');
        }
        return;
    }

    _setExportBtnLoading(true);

    var basePath   = window.location.pathname.replace(/\/[^/]+$/, '/');
    var verifyPath = basePath + 'verify_action.php';
    var fd = new FormData();
    fd.append('password',    password);
    fd.append('reason',      reason);
    fd.append('export_type', _exportType);
    fd.append('table_name',  _exportTable);

    fetch(verifyPath, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function(res) { if (!res.ok) throw new Error('Server error: ' + res.status); return res.json(); })
        .then(function(data) {
            if (!data.success) {
                _setExportBtnLoading(false);
                _showToast(data.message || AUDIT_T.export_select_reason, 'danger');
                return;
            }

            if (_exportType === 'PRINT') {
                _showToast(AUDIT_T.print_success, 'success');
                setTimeout(function() {
                    _setExportBtnLoading(false);
                    bootstrap.Modal.getOrCreateInstance(_exportModalEl).hide();
                    setTimeout(function() { window.print(); }, 300);
                }, 800);
                return;
            }

            _showToast(AUDIT_T.export_success, 'success');
            var sep         = _exportUrl.includes('?') ? '&' : '?';
            var downloadUrl = _exportUrl + sep + 'export_token=' + encodeURIComponent(data.token);
            var iframe = document.createElement('iframe');
            iframe.style.display = 'none';
            iframe.src = downloadUrl;
            document.body.appendChild(iframe);
            setTimeout(function() {
                document.body.removeChild(iframe);
                _setExportBtnLoading(false);
                bootstrap.Modal.getOrCreateInstance(_exportModalEl).hide();
            }, 3000);
        })
        .catch(function() {
            _setExportBtnLoading(false);
            _showToast(AUDIT_T.export_network_error, 'danger');
        });
}

_elmt('exportVerifyBtn').onclick = submitExportVerification;
// ===== END VERIFICATION LOGIC =====


// ===== LOG DETAIL MODAL =====
function showLogDetails(row) {
    var user    = row.getAttribute('data-user');
    var action  = row.getAttribute('data-action');
    var time    = row.getAttribute('data-time');
    var details = row.getAttribute('data-details');
    var ip      = row.getAttribute('data-ip');
    var agent   = row.getAttribute('data-agent');

    var displayDevice  = 'Unknown Device';
    var displayBrowser = 'Unknown Browser';

    if      (agent.includes('Windows NT 10.0')) displayDevice = 'Windows 10/11 Desktop';
    else if (agent.includes('Android'))          displayDevice = 'Android Mobile';
    else if (agent.includes('iPhone'))           displayDevice = 'iPhone/iOS';
    else if (agent.includes('Macintosh'))        displayDevice = 'Mac Desktop';

    if      (agent.includes('Chrome') && !agent.includes('Edg'))    displayBrowser = 'Google Chrome';
    else if (agent.includes('Edg'))                                  displayBrowser = 'Microsoft Edge';
    else if (agent.includes('Firefox'))                              displayBrowser = 'Mozilla Firefox';
    else if (agent.includes('Safari') && !agent.includes('Chrome')) displayBrowser = 'Apple Safari';

    document.getElementById('modalTitle').innerText        = action;
    document.getElementById('modalUser').innerText         = user;
    document.getElementById('modalTime').innerText         = time;
    document.getElementById('modalIP').innerText           = ip;
    document.getElementById('modalAgentDisplay').innerText = displayDevice + ' (' + displayBrowser + ')';
    document.getElementById('modalAgentRaw').innerText     = agent;
    document.getElementById('modalDetails').innerText      = details ? details : AUDIT_T.no_changes;

    bootstrap.Modal.getOrCreateInstance(document.getElementById('logModal')).show();
}
</script>

<?php include __DIR__ . '/footer.php'; ?>