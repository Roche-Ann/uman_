<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../modules/MonitoringAndInspection/MonitoringController.php';

$auth = new Auth();
$auth->requireRole(['admin', 'super_admin', 'zoning_officer', 'building_official', 'inspector']);
$controller = new MonitoringController();

$apps        = $controller->getApplicationsForDropdown('approved');
$staffs      = $controller->getStaffList();
$inspections = $controller->getInspectionLogs();

// --- TAB FILTER (All / For Inspection / Scheduled / Completed) ---
$allowedTabs = ['all', 'inspection', 'scheduled', 'completed'];
$activeTab = isset($_GET['status']) && in_array($_GET['status'], $allowedTabs) ? $_GET['status'] : 'all';

if ($activeTab !== 'all') {
    $inspections = array_values(array_filter($inspections, function ($log) use ($activeTab) {
        return strtolower(trim($log['display_status'])) === $activeTab;
    }));
}

// --- PAGINATION CONFIGURATION (style matches audit-logs.php) ---
$limit = 10;
$page = isset($_GET['p']) && is_numeric($_GET['p']) ? (int)$_GET['p'] : 1;
if ($page < 1) $page = 1;

$totalInspections = count($inspections);
$totalPages = max(1, ceil($totalInspections / $limit));
if ($page > $totalPages) $page = $totalPages;

$offset = ($page - 1) * $limit;
$inspections = array_slice($inspections, $offset, $limit);

$currentRole   = $_SESSION['role'] ?? '';
$isInspector   = $currentRole === 'inspector';
$isZoningStaff = in_array($currentRole, ['zoning_officer', 'admin', 'super_admin', 'building_official']);

$isAuthPage = true;
include __DIR__ . '/../admin/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style> 
    /* ── BASE ── */
    #inspectionCalendar { min-height: 500px; background: #fff; border-radius: 8px; padding: 10px; } 
    .log-table-container { max-height: 500px; overflow-y: auto; }
    .status-badge { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
    .table thead th { background-color: #f8f9fa; position: sticky; top: 0; z-index: 5; }
    .fc-event { cursor: pointer; padding: 2px 4px; font-size: 0.85em; }
    .fc-toolbar-title { font-size: 1.1rem !important; font-weight: bold; }
    .fc-event-title { font-weight: 600 !important; font-size: 0.75rem !important; padding: 1px 3px; }
    .fc-daygrid-event { border-radius: 4px !important; margin: 1px 2px !important; white-space: nowrap !important; }

    /* ── CALENDAR CARD (modern / professional) ── */
    .calendar-card-header {
        background: #fff;
        padding: 1rem 1.25rem;
        border-bottom: 1px solid #eef0f3;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .calendar-card-header i { color: #0d6efd; font-size: 1.05rem; }
    .calendar-card-header h6 { margin: 0; font-weight: 700; color: #1e293b; }

    .fc .fc-toolbar-title { color: #1c4e9e; font-weight: 700; }
    .fc .fc-button {
        background: #ffffff;
        border: 1.5px solid #e2e6ec;
        color: #5a6474;
        border-radius: 8px !important;
        font-weight: 600;
        box-shadow: none !important;
        text-transform: capitalize;
        transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
    }
    .fc .fc-button:hover {
        background: #f0f6ff;
        border-color: #0d6efd;
        color: #0d6efd;
    }
    .fc .fc-button-primary:not(:disabled).fc-button-active,
    .fc .fc-button-primary:not(:disabled):active {
        background: linear-gradient(135deg, #1c4e9e 0%, #0d6efd 100%) !important;
        border-color: transparent !important;
        color: #fff !important;
    }
    .fc .fc-today-button {
        background: linear-gradient(135deg, #1c4e9e 0%, #0d6efd 100%) !important;
        border: none !important;
        color: #fff !important;
        opacity: 1 !important;
    }
    .fc .fc-today-button:disabled {
        background: #eef0f3 !important;
        color: #adb5bd !important;
    }
    .fc .fc-col-header-cell-cushion {
        color: #1c4e9e;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.03em;
        text-decoration: none;
        padding: 8px 4px;
    }
    .fc .fc-daygrid-day-number {
        color: #334155;
        font-weight: 600;
        text-decoration: none;
    }
    .fc .fc-day-today { background: rgba(13, 110, 253, 0.06) !important; }
    .fc .fc-day-today .fc-daygrid-day-number { color: #0d6efd; font-weight: 700; }
    .fc-theme-standard td, .fc-theme-standard th { border-color: #eef0f3; }
    .fc-theme-standard .fc-scrollgrid { border-color: #eef0f3; }


    /* ================================================
       MOBILE RESPONSIVE
       768px (Tablet) | 480px (Large Mobile) | 320px (Small Mobile)
       ================================================ */

    /* --- 768px: Tablet --- */
    @media (max-width: 768px) {

        /* Page padding */
        .p-4 { padding: 1rem !important; }

        /* Page header: stack title + button */
        .d-flex.justify-content-between.mb-4.align-items-center {
            flex-direction: column;
            align-items: flex-start !important;
            gap: 10px;
        }
        .d-flex.justify-content-between.mb-4.align-items-center .btn {
            width: 100%;
            font-size: 0.875rem;
        }

        /* Main 2-column layout: stack log above calendar */
        .row.g-4 > .col-xl-7,
        .row.g-4 > .col-xl-5 {
            width: 100%;
            flex: 0 0 100%;
        }

        /* Card header: stack title + filter tabs */
        .card-header.d-flex.justify-content-between {
            flex-direction: column;
            align-items: flex-start !important;
            gap: 8px;
        }
        .calendar-card-header { padding: 0.85rem 1rem; }
        /* Filter tab pills: scrollable row */
        #monitoringTabs {
            width: 100%;
            flex-wrap: nowrap !important;
            overflow-x: auto;
            overflow-y: hidden;
            scrollbar-width: none;
            -webkit-overflow-scrolling: touch;
        }
        #monitoringTabs::-webkit-scrollbar { display: none; }
        #monitoringTabs .nav-item { flex-shrink: 0; }

        /* Table */
        .table { font-size: 0.8rem; }
        .table th, .table td { padding: 0.5rem 0.4rem; }

        /* Calendar: reduce min-height */
        #inspectionCalendar { min-height: 380px; padding: 8px; }
        .fc-toolbar-title { font-size: 0.95rem !important; }

        /* Schedule modal */
        .modal-dialog.modal-dialog-centered { margin: 0.75rem; }
        .modal-body.p-4 { padding: 1rem !important; }
        #scheduleModal .modal-body { padding: 1.1rem !important; }
        #scheduleModal .form-section { padding: 1rem 1.1rem 1.2rem; margin-bottom: 1rem; }
        #scheduleModal .modal-footer { padding: 0.85rem 1.1rem; }
        #scheduleModal .modal-footer .btn { padding: 0.5rem 1.2rem; font-size: 0.85rem; }

        /* View modal (xl) */
        .modal-dialog.modal-xl {
            max-width: calc(100% - 1rem) !important;
            width: calc(100% - 1rem) !important;
            margin: 0.5rem auto;
        }
        .modal-dialog.modal-xl .modal-body.p-4 { padding: 1rem !important; }

        /* Checklist + Violation: stack */
        #viewModal .col-md-6 {
            width: 100%;
            flex: 0 0 100%;
        }

        /* Pagination */
        .card-footer .row { flex-direction: column; gap: 10px; text-align: center; }
        .card-footer .col-md-6:last-child { text-align: center !important; }
        .pagination { justify-content: center !important; }
    }

    /* --- 480px: Large Mobile --- */
    @media (max-width: 480px) {

        .p-4 { padding: 0.75rem !important; }

        /* Page header */
        .d-flex.justify-content-between.mb-4.align-items-center h2 { font-size: 1.1rem; }
        .d-flex.justify-content-between.mb-4.align-items-center p { font-size: 0.78rem; margin-bottom: 0; }
        .d-flex.justify-content-between.mb-4.align-items-center .btn { font-size: 0.82rem; padding: 7px 12px; }

        /* Card header */
        .card-header { padding: 0.65rem 0.75rem !important; }
        .card-header h6 { font-size: 0.82rem; }
        #monitoringTabs { font-size: 0.68rem !important; }
        #monitoringTabs .nav-link { padding: 2px 8px !important; font-size: 0.68rem; }
        .calendar-card-header { padding: 0.65rem 0.75rem; }
        .calendar-card-header h6 { font-size: 0.82rem; }
        .calendar-card-header i { font-size: 0.95rem; }

        /* Table: hide Inspector col */
        .table { font-size: 0.74rem; }
        .table th, .table td { padding: 0.4rem 0.3rem; }
        .table thead th:nth-child(2),
        .table tbody td:nth-child(2) { display: none; }
        .table .btn-sm {
            font-size: 0.72rem !important;
            padding: 4px 10px !important;
            min-width: 52px;
            line-height: 1.3;
        }
        .status-badge { font-size: 0.62rem; }

        /* Calendar — compact month grid */
        #inspectionCalendar {
            min-height: unset !important;
            height: auto !important;
            padding: 6px;
        }
        .fc .fc-toolbar.fc-header-toolbar { margin-bottom: 8px !important; flex-wrap: nowrap; gap: 4px; }
        .fc-toolbar-title { font-size: 0.82rem !important; }
        .fc .fc-button { font-size: 0.72rem !important; padding: 3px 7px !important; line-height: 1.2 !important; }
        .fc .fc-daygrid-day { min-height: 34px !important; }
        .fc .fc-daygrid-day-number { font-size: 0.7rem; padding: 2px 4px; }
        .fc .fc-col-header-cell-cushion { font-size: 0.68rem; padding: 3px 2px; }
        .fc-event-title { font-size: 0.6rem !important; padding: 0 2px; }
        .fc-daygrid-event { margin: 1px 1px !important; }

        /* Schedule modal */
        .modal-body.p-4 { padding: 0.75rem !important; }
        .modal-body .form-label { font-size: 0.75rem; margin-bottom: 2px; }
        .modal-body .form-control,
        .modal-body .form-select { font-size: 0.82rem; padding: 6px 9px; }
        .modal-body .mb-3 { margin-bottom: 0.6rem !important; }
        .modal-footer { padding: 0.6rem 0.75rem; }
        #scheduleModal .modal-header { padding: 0.85rem 1rem; }
        #scheduleModal .modal-title { font-size: 1rem; }
        #scheduleModal .modal-header-subtitle { font-size: 0.72rem; }
        #scheduleModal .modal-body { padding: 0.85rem !important; }
        #scheduleModal .form-section { padding: 0.85rem 0.9rem 1rem; margin-bottom: 0.85rem; }
        #scheduleModal .form-section-title { font-size: 0.72rem; margin-bottom: 0.8rem; }
        #scheduleModal .modal-body .form-label { font-size: 0.7rem; }
        #scheduleModal .modal-body .form-control,
        #scheduleModal .modal-body .form-select,
        #scheduleModal .sd-input-wrap { font-size: 0.82rem; padding: 6px 9px; }
        #scheduleModal .modal-footer { padding: 0.7rem 0.9rem; }
        #scheduleModal .modal-footer .btn { padding: 7px 16px; font-size: 0.82rem; }

        /* View modal */
        #viewModal .modal-body { padding: 0.75rem !important; }
        #viewModal .modal-title { font-size: 0.85rem; }
        #viewModal .modal-header { padding: 0.6rem 0.85rem !important; }
        #viewModal h4 { font-size: 1rem; }
        #viewModal .row.g-3 .col-6 { font-size: 0.78rem; }
        #viewModal .row.g-3 .p-2 { padding: 0.4rem !important; }
        #viewModal h6.fw-bold { font-size: 0.78rem; }
        #viewModal .form-check-label { font-size: 0.78rem; }
        #viewModal .card-header { font-size: 0.75rem; padding: 6px 10px !important; }
        #viewModal .card-body { padding: 0.65rem !important; }
        #viewModal .btn-sm { font-size: 0.75rem; }
        #viewModal .badge.bg-white { font-size: 0.6rem; }

        /* Pagination */
        .pagination .page-link { font-size: 0.72rem; padding: 4px 8px; }
        .card-footer { padding: 0.6rem 0.75rem; }
        .info-text { font-size: 0.72rem; }
    }

    /* ── SEARCHABLE DROPDOWN ── */
    .searchable-dropdown { position: relative; }
    .sd-input-wrap {
        display: flex; align-items: center; gap: 6px;
        border: 1px solid #dee2e6; border-radius: 6px;
        background: #fff; padding: 6px 10px; cursor: text;
        transition: border-color .15s;
    }
    .sd-input-wrap:focus-within { border-color: #86b7fe; box-shadow: 0 0 0 3px rgba(13,110,253,.15); }
    .sd-icon { color: #6c757d; font-size: 0.8rem; flex-shrink: 0; }
    .sd-input {
        border: none; outline: none; flex: 1;
        font-size: 0.875rem; background: transparent; min-width: 0;
    }
    .sd-clear {
        color: #adb5bd; cursor: pointer; font-size: 1rem; line-height: 1;
        display: none; flex-shrink: 0;
    }
    .sd-clear:hover { color: #495057; }
    .sd-list {
        display: none; position: absolute; z-index: 1055;
        left: 0; right: 0; top: calc(100% + 4px);
        background: #fff; border: 1px solid #dee2e6;
        border-radius: 6px; box-shadow: 0 4px 16px rgba(0,0,0,.12);
        max-height: 210px; overflow-y: auto;
    }
    .sd-list.open { display: block; }
    .sd-item {
        padding: 8px 12px; font-size: 0.825rem; cursor: pointer;
        border-bottom: 1px solid #f1f3f5; transition: background .1s;
    }
    .sd-item:last-child { border-bottom: none; }
    .sd-item:hover, .sd-item.active { background: #e8f4fd; }
    .sd-item.selected { background: #d1ecf1; font-weight: 600; }
    .sd-no-results { padding: 10px 12px; color: #6c757d; font-size: 0.8rem; font-style: italic; }

    /* Schedule Inspection — gradient trigger button (green variant, mirrors .btn-manual-add) */
    .btn-schedule-inspection {
        background: linear-gradient(135deg, #0f7a4e 0%, #17a566 100%);
        border: none;
        color: #fff;
        border-radius: 9px;
        font-weight: 600;
        padding: 0.55rem 1.4rem;
        box-shadow: 0 4px 12px rgba(23, 165, 102, 0.32);
        transition: transform 0.12s ease, box-shadow 0.12s ease, color 0.12s ease;
    }
    .btn-schedule-inspection:hover {
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(23, 165, 102, 0.4);
    }
    .btn-schedule-inspection:active,
    .btn-schedule-inspection:focus {
        color: #fff;
    }

    /* Table "View" button — gradient blue */
    .btn-view-gradient {
        background: linear-gradient(135deg, #1c4e9e 0%, #4a7dfc 100%);
        border: none;
        color: #fff;
        border-radius: 8px;
        font-weight: 600;
        box-shadow: 0 3px 8px rgba(28, 78, 158, 0.3);
        transition: transform 0.12s ease, box-shadow 0.12s ease, color 0.12s ease;
    }
    .btn-view-gradient:hover,
    .btn-view-gradient:focus,
    .btn-view-gradient:active {
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 5px 12px rgba(28, 78, 158, 0.4);
    }

    /* ================================================
       SCHEDULE INSPECTION MODAL — modern / professional redesign
       (mirrors #manualAddModal in applications.php)
       ================================================ */
    #scheduleModal .modal-content {
        border-radius: 16px;
        overflow: hidden;
    }

    #scheduleModal .modal-header {
        background: linear-gradient(135deg, #1c4e9e 0%, #0d6efd 100%);
        border-bottom: none;
        padding: 1.25rem 1.5rem;
    }
    #scheduleModal .modal-header-icon {
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
    #scheduleModal .modal-title {
        font-size: 1.15rem;
        font-weight: 700;
        letter-spacing: -0.01em;
    }
    #scheduleModal .modal-header-subtitle {
        font-size: 0.8rem;
        color: rgba(255, 255, 255, 0.75);
        margin-top: 1px;
    }

    #scheduleModal .modal-body {
        background: #f6f8fb;
        padding: 1.75rem;
    }

    #scheduleModal .form-section {
        background: #ffffff;
        border: 1px solid #eaeef3;
        border-radius: 12px;
        padding: 1.25rem 1.5rem 1.5rem;
        margin-bottom: 1.25rem;
    }
    #scheduleModal .form-section:last-child { margin-bottom: 0; }

    #scheduleModal .form-section-title {
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
    #scheduleModal .form-section-title i {
        font-size: 0.95rem;
        color: #0d6efd;
    }

    #scheduleModal .modal-body .form-label {
        font-weight: 600;
        font-size: 0.76rem;
        text-transform: uppercase;
        letter-spacing: 0.02em;
        color: #5a6474;
        margin-bottom: 0.4rem;
    }

    #scheduleModal .modal-body .form-control,
    #scheduleModal .modal-body .form-select {
        border: 1.5px solid #e2e6ec;
        border-radius: 9px;
        padding: 0.55rem 0.85rem;
        font-size: 0.9rem;
        background-color: #fcfdfe;
        transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
    }
    #scheduleModal .modal-body .form-control:focus,
    #scheduleModal .modal-body .form-select:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.12);
        background-color: #ffffff;
    }
    #scheduleModal .modal-body .form-control::placeholder { color: #a7b0bd; }
    #scheduleModal .modal-body textarea.form-control { resize: vertical; }

    /* Searchable dropdown — match the modernized input style */
    #scheduleModal .sd-input-wrap {
        border: 1.5px solid #e2e6ec;
        border-radius: 9px;
        padding: 0.55rem 0.85rem;
        background-color: #fcfdfe;
    }
    #scheduleModal .sd-input-wrap:focus-within {
        border-color: #0d6efd;
        box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.12);
        background-color: #ffffff;
    }
    #scheduleModal .sd-input { font-size: 0.9rem; }

    #scheduleModal .modal-footer {
        background: #ffffff;
        border-top: 1px solid #eef0f3;
        padding: 1.1rem 1.5rem;
        gap: 0.6rem;
    }
    #scheduleModal .modal-footer .btn {
        border-radius: 9px;
        font-weight: 600;
        font-size: 0.88rem;
        padding: 0.55rem 1.4rem;
        transition: transform 0.12s ease, box-shadow 0.12s ease;
    }
    #scheduleModal .modal-footer .btn-primary {
        background: linear-gradient(135deg, #1c4e9e 0%, #0d6efd 100%);
        border: none;
        box-shadow: 0 4px 12px rgba(13, 110, 253, 0.28);
    }
    #scheduleModal .modal-footer .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(13, 110, 253, 0.35);
    }
    #scheduleModal .modal-footer .btn-outline-secondary,
    #scheduleModal .modal-footer .btn-link {
        border: 1.5px solid #dde1e7;
        color: #5a6474;
        background: #fff;
        text-decoration: none;
    }
    #scheduleModal .modal-footer .btn-outline-secondary:hover,
    #scheduleModal .modal-footer .btn-link:hover {
        background: #f6f8fb;
        border-color: #c7cdd6;
        color: #5a6474;
    }

    /* ================================================
       INSPECTION DETAILS (VIEW) MODAL — modern / professional redesign
       (mirrors #scheduleModal / #manualAddModal)
       ================================================ */
    #viewModal .modal-content {
        border-radius: 16px;
        overflow: hidden;
    }

    #viewModal .modal-header {
        background: linear-gradient(135deg, #1c4e9e 0%, #0d6efd 100%);
        border-bottom: none;
        padding: 1.25rem 1.5rem;
    }
    #viewModal .modal-header-icon {
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
    #viewModal .modal-title {
        font-size: 1.15rem;
        font-weight: 700;
        letter-spacing: -0.01em;
    }
    #viewModal .modal-header-subtitle {
        font-size: 0.8rem;
        color: rgba(255, 255, 255, 0.75);
        margin-top: 1px;
    }

    #viewModal .modal-body {
        background: #f6f8fb;
        padding: 1.75rem;
    }

    /* Project summary card */
    #viewModal .form-section {
        background: #ffffff;
        border: 1px solid #eaeef3;
        border-radius: 12px;
        padding: 1.25rem 1.5rem 1.5rem;
        margin-bottom: 1.25rem;
    }
    #viewModal .form-section .text-muted.small.fw-bold {
        font-size: 0.76rem;
        text-transform: uppercase;
        letter-spacing: 0.02em;
        color: #5a6474 !important;
    }
    #viewModal .form-section label.form-label {
        font-weight: 600;
        font-size: 0.76rem;
        text-transform: uppercase;
        letter-spacing: 0.02em;
        color: #5a6474;
        margin-bottom: 0.4rem;
    }
    #viewModal #view_project_name { color: #1c4e9e !important; }

    #viewModal .view-info-box {
        border: 1.5px solid #e2e6ec;
        border-radius: 9px;
        background-color: #fcfdfe;
        padding: 0.6rem 0.9rem;
        font-size: 0.9rem;
        font-weight: 600;
        color: #1e293b;
    }
    #viewModal .view-info-box.notes-box {
        font-weight: 400;
        font-style: italic;
        color: #495057;
    }

    /* Checklist / Violation panels */
    #viewModal .view-panel {
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid #eaeef3;
    }
    #viewModal .view-panel-header {
        padding: 0.85rem 1.1rem;
        font-size: 0.78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #fff;
        border-bottom: none;
    }
    #viewModal .checklist-header {
        background: linear-gradient(135deg, #0f7a4e 0%, #17a566 100%);
    }
    #viewModal .violation-header {
        background: linear-gradient(135deg, #b02a37 0%, #dc3545 100%);
    }
    #viewModal .view-panel .card-body {
        background: #ffffff;
        padding: 1.25rem 1.4rem;
    }
    #viewModal .view-panel h6.fw-bold.text-primary {
        color: #1c4e9e !important;
    }

    /* Pagination — same style as audit-logs.php */
    .pagination .page-link { color: #2c3e50; border: 1px solid #dee2e6; margin: 0 2px; border-radius: 4px; }
    .pagination .page-item.active .page-link { background-color: #0d6efd; border-color: #0d6efd; color: white; }
    .pagination .page-link:hover { background-color: #e7f1ff; border-color: #b6d4fe; color: #0d6efd; }
    .info-text { font-size: 0.875rem; color: #6c757d; }

    /* --- 320px: Small Mobile --- */
    @media (max-width: 320px) {

        .p-4 { padding: 0.5rem !important; }

        /* Page header */
        .d-flex.justify-content-between.mb-4.align-items-center h2 { font-size: 0.95rem; }
        .d-flex.justify-content-between.mb-4.align-items-center p { font-size: 0.72rem; }
        .d-flex.justify-content-between.mb-4.align-items-center .btn { font-size: 0.78rem; padding: 6px 10px; }

        /* Card header */
        .card-header { padding: 0.5rem 0.6rem !important; }
        .card-header h6 { font-size: 0.75rem; }
        #monitoringTabs { font-size: 0.62rem !important; gap: 2px; }
        #monitoringTabs .nav-link { padding: 2px 6px !important; font-size: 0.62rem; }
        .calendar-card-header { padding: 0.5rem 0.6rem; }
        .calendar-card-header h6 { font-size: 0.75rem; }

        /* Table: hide Inspector + Date, keep APP ID, Status, Action */
        .table { font-size: 0.65rem; }
        .table th, .table td { padding: 0.3rem 0.2rem; }
        .table thead th:nth-child(2),
        .table tbody td:nth-child(2),
        .table thead th:nth-child(3),
        .table tbody td:nth-child(3) { display: none; }
        .table .btn-sm { font-size: 0.6rem; padding: 2px 6px; }
        .status-badge { font-size: 0.58rem; padding: 2px 4px; }

        /* Calendar — ultra-compact month grid */
        #inspectionCalendar {
            min-height: unset !important;
            height: auto !important;
            padding: 3px;
        }
        .fc .fc-toolbar.fc-header-toolbar { margin-bottom: 5px !important; flex-wrap: nowrap; gap: 2px; }
        .fc-toolbar-title { font-size: 0.68rem !important; }
        .fc .fc-button { font-size: 0.6rem !important; padding: 2px 5px !important; line-height: 1.1 !important; }
        .fc .fc-daygrid-day { min-height: 24px !important; }
        .fc .fc-daygrid-day-number { font-size: 0.58rem; padding: 1px 2px; }
        .fc .fc-col-header-cell-cushion { font-size: 0.55rem; padding: 2px 1px; }
        .fc-event-title { font-size: 0.52rem !important; padding: 0 1px; }
        .fc-daygrid-event { margin: 0 !important; }

        /* Schedule modal */
        .modal-body.p-4 { padding: 0.6rem !important; }
        .modal-body .form-label { font-size: 0.68rem; margin-bottom: 1px; }
        .modal-body .form-control,
        .modal-body .form-select { font-size: 0.78rem; padding: 5px 8px; }
        .modal-body .mb-3 { margin-bottom: 0.5rem !important; }
        .modal-footer { padding: 0.5rem 0.6rem; }
        /* Side-by-side footer buttons */
        .modal-footer {
            display: flex !important;
            flex-direction: row !important;
            justify-content: stretch;
            gap: 6px;
        }
        .modal-footer .btn { flex: 1; text-align: center; font-size: 0.78rem; padding: 6px 8px; }
        #scheduleModal .modal-header { padding: 0.65rem 0.8rem; }
        #scheduleModal .modal-title { font-size: 0.9rem; }
        #scheduleModal .modal-header-subtitle { font-size: 0.66rem; }
        #scheduleModal .modal-body { padding: 0.65rem !important; }
        #scheduleModal .form-section { padding: 0.7rem 0.75rem 0.85rem; margin-bottom: 0.7rem; }
        #scheduleModal .form-section-title { font-size: 0.66rem; margin-bottom: 0.65rem; }
        #scheduleModal .modal-body .form-label { font-size: 0.64rem; }
        #scheduleModal .modal-body .form-control,
        #scheduleModal .modal-body .form-select,
        #scheduleModal .sd-input-wrap { font-size: 0.78rem; padding: 5px 8px; }
        #scheduleModal .modal-footer { padding: 0.55rem 0.75rem; gap: 6px; }
        #scheduleModal .modal-footer .btn { padding: 6px 10px; font-size: 0.76rem; }

        /* View modal */
        #viewModal .modal-body { padding: 0.6rem !important; }
        #viewModal .modal-title { font-size: 0.75rem; }
        #viewModal .modal-header { padding: 0.45rem 0.7rem !important; }
        #viewModal .mb-4.text-center h4 { font-size: 0.9rem; }
        #viewModal .mb-4.text-center h6 { font-size: 0.65rem; }
        #viewModal .row.g-3 .col-6 { font-size: 0.7rem; }
        #viewModal .row.g-3 .p-2 { padding: 0.35rem !important; }
        #viewModal h6.fw-bold { font-size: 0.72rem; }
        #viewModal .form-check-label { font-size: 0.72rem; }
        #viewModal .text-muted[style] { font-size: 0.65rem !important; }
        #viewModal .card-header { font-size: 0.68rem; padding: 5px 8px !important; }
        #viewModal .card-header .badge { font-size: 0.55rem; padding: 2px 5px; }
        #viewModal .card-body { padding: 0.5rem !important; }
        #viewModal .btn-sm { font-size: 0.68rem; padding: 5px 8px; }
        #viewModal textarea.form-control-sm { font-size: 0.72rem; }

        /* Pagination: show only prev/next + current */
        .pagination .page-item:not(:first-child):not(:nth-child(2)):not(:last-child):not(:nth-last-child(2)) { display: none; }
        .pagination .page-link { font-size: 0.65rem; padding: 3px 7px; }
        .card-footer { padding: 0.5rem; }
        .info-text { font-size: 0.65rem; }
    }
</style>

<div class="p-4">
    <div class="d-flex justify-content-between mb-4 align-items-center">
        <div>
            <h2 class="fw-bold mb-0 d-flex align-items-center gap-2" style="color: #1e293b;">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle">
                    <i class="bi bi-clipboard2-pulse" style="color:#10b981;font-size:1.9rem;"></i>
                </span>
                Monitoring &amp; Inspections
            </h2>
            <p class="text-muted mb-0">Real-time inspection tracking and scheduling.</p>
        </div>
        <?php if ($_SESSION['role'] !== 'inspector'): ?>
        <button class="btn btn-schedule-inspection px-4" onclick="openScheduleModal()">
            <i class="bi bi-calendar-plus me-2"></i>Schedule Inspection
        </button>
        <?php endif; ?>
    </div>

    <div class="row g-4">
        <div class="col-xl-7">
            <div class="card shadow-sm border-0 h-100">
<div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
    <h6 class="mb-0 fw-bold">
        <i class="bi bi-list-check me-2 text-primary"></i>Inspection Record Log
    </h6>

    <ul class="nav nav-pills border rounded-pill p-1 bg-light shadow-sm" id="monitoringTabs" style="font-size: 0.75rem;">
    <li class="nav-item">
        <a href="?status=all" class="nav-link rounded-pill py-0 px-2 fw-bold <?= $activeTab === 'all' ? 'active bg-primary text-white' : 'text-secondary' ?>">
            All
        </a>
    </li>
    <li class="nav-item">
        <a href="?status=inspection" class="nav-link rounded-pill py-0 px-2 fw-bold <?= $activeTab === 'inspection' ? 'active bg-primary text-white' : 'text-secondary' ?>">
            For Inspection
        </a>
    </li>
    <li class="nav-item">
        <a href="?status=scheduled" class="nav-link rounded-pill py-0 px-2 fw-bold <?= $activeTab === 'scheduled' ? 'active bg-primary text-white' : 'text-secondary' ?>">
            Scheduled
        </a>
    </li>
    <li class="nav-item">
        <a href="?status=completed" class="nav-link rounded-pill py-0 px-2 fw-bold <?= $activeTab === 'completed' ? 'active bg-primary text-white' : 'text-secondary' ?>">
            Completed
        </a>
    </li>
</ul>
</div>
                <div class="card-body p-0 log-table-container">
                    <div class="table-responsive">
<table class="table table-hover align-middle mb-0">
    <thead>
        <tr class="text-secondary small">
            <th class="ps-3">APP ID</th>
            <th>INSPECTOR</th>
            <th>DATE</th>
            <th class="text-center">STATUS</th>
            <th class="text-center">ACTION</th> </tr>
    </thead>
<tbody class="small" id="inspectionTableBody">
    <?php if (!empty($inspections)): foreach($inspections as $log): ?>
    <tr data-status="<?= htmlspecialchars($log['display_status']) ?>"> 
        <td class="ps-3 fw-bold text-primary">#<?= htmlspecialchars($log['application_number']) ?></td>
        <td><?= htmlspecialchars($log['inspector_name'] ?? 'Unassigned') ?></td>
        <td class="text-muted">
            <?php 
            if (!empty($log['scheduled_at']) && $log['scheduled_at'] !== '0000-00-00 00:00:00') {
                echo date('M d, Y', strtotime($log['scheduled_at']));
            } else {
                echo '<span class="badge bg-light text-danger border italic">TBD / No Schedule</span>';
            }
            ?>
        </td>
        <td class="text-center">
            <?php 
                // Visual Badge Logic
                $currentStatus = strtolower($log['display_status']);
                if($currentStatus == 'inspection') {
                    $statusClass = 'bg-secondary-subtle text-secondary border-secondary-subtle';
                    $label = 'For Inspection';
                } elseif($log['status'] == 'completed') {
                    $statusClass = 'bg-success-subtle text-success border-success-subtle';
                    $label = 'Passed';
                } else {
                    $statusClass = 'bg-warning-subtle text-dark border-warning-subtle';
                    $label = 'Scheduled';
                }
            ?>
            <span class="badge border <?= $statusClass ?> px-2 py-1 status-badge">
                <?= $label ?>
            </span>
        </td>
            <td class="text-center">
                <button class="btn btn-sm btn-view-gradient fw-bold px-3" 
                        onclick='viewInspectionDetails(<?= json_encode($log) ?>)'>
                    <i class="bi bi-eye me-1"></i> View
                </button>
            </td>
        </tr>
    <?php endforeach; else: ?>
        <tr class="no-records"><td colspan="5" class="text-center py-5 text-muted italic"><?= $activeTab === 'all' ? 'No records found.' : 'No records found for this category.' ?></td></tr>
    <?php endif; ?>
</tbody>
</table>
                    </div>
                </div>

                <div class="card-footer py-3 border-0">
                    <div class="row align-items-center">
                        <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                            <span class="info-text text-muted">
                                Showing <strong><?= $totalInspections > 0 ? ($offset + 1) : 0 ?></strong> to
                                <strong><?= min($offset + $limit, $totalInspections) ?></strong> of
                                <strong><?= $totalInspections ?></strong> entries
                            </span>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <nav aria-label="Page navigation">
                                <ul class="pagination pagination-sm justify-content-center justify-content-md-end mb-0">
                                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?status=<?= $activeTab ?>&p=1"><i class="bi bi-chevron-double-left"></i></a>
                                    </li>
                                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?status=<?= $activeTab ?>&p=<?= ($page - 1) ?>">Prev</a>
                                    </li>
                                    <?php
                                    $start = max(1, $page - 2);
                                    $end = min($totalPages, $page + 2);
                                    for ($i = $start; $i <= $end; $i++):
                                    ?>
                                        <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                            <a class="page-link" href="?status=<?= $activeTab ?>&p=<?= $i ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?status=<?= $activeTab ?>&p=<?= ($page + 1) ?>">Next</a>
                                    </li>
                                    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?status=<?= $activeTab ?>&p=<?= $totalPages ?>"><i class="bi bi-chevron-double-right"></i></a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card shadow-sm border-0 h-100">
                <div class="calendar-card-header">
                    <i class="bi bi-calendar3"></i>
                    <h6>Inspection Calendar</h6>
                </div>
                <div class="card-body">
                    <div id="inspectionCalendar"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="scheduleModal" tabindex="-1" aria-hidden="true" aria-labelledby="scheduleModalLabel">
    <div class="modal-dialog modal-dialog-centered">
        <form id="inspectionForm" class="modal-content border-0 shadow-lg">
            <div class="modal-header text-white">
                <div class="d-flex align-items-center">
                    <span class="modal-header-icon"><i class="bi bi-calendar-plus"></i></span>
                    <div>
                        <h5 class="modal-title mb-0" id="scheduleModalLabel">New Inspection Schedule</h5>
                        <div class="modal-header-subtitle">Assign an inspector and set a site visit schedule</div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">

                <!-- Application & Inspector -->
                <div class="form-section">
                    <div class="form-section-title"><i class="bi bi-person-badge"></i> Application &amp; Inspector</div>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Select Application</label>
                            <input type="hidden" name="application_id" id="application_id_val" required>
                            <div class="searchable-dropdown" id="appDropdown">
                                <div class="sd-input-wrap">
                                    <i class="bi bi-search sd-icon"></i>
                                    <input type="text" class="sd-input" id="appSearch" placeholder="Search project..." autocomplete="off">
                                    <span class="sd-clear" onclick="clearSD('appDropdown','application_id_val','appSearch')">&times;</span>
                                </div>
                                <div class="sd-list" id="appList">
                                    <div class="sd-item" data-value="" data-label="-- Choose Project --"><em class="text-muted">-- Choose Project --</em></div>
                                    <?php foreach($apps as $app): ?>
                                        <div class="sd-item" data-value="<?= $app['id'] ?>" data-label="#<?= htmlspecialchars($app['application_number']) ?> - <?= htmlspecialchars($app['project_name']) ?>">
                                            #<?= htmlspecialchars($app['application_number']) ?> &mdash; <?= htmlspecialchars($app['project_name']) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Assign Inspector</label>
                            <input type="hidden" name="inspector_id" id="inspector_id_val" required>
                            <div class="searchable-dropdown" id="inspDropdown">
                                <div class="sd-input-wrap">
                                    <i class="bi bi-search sd-icon"></i>
                                    <input type="text" class="sd-input" id="inspSearch" placeholder="Search inspector..." autocomplete="off">
                                    <span class="sd-clear" onclick="clearSD('inspDropdown','inspector_id_val','inspSearch')">&times;</span>
                                </div>
                                <div class="sd-list" id="inspList">
                                    <div class="sd-item" data-value="" data-label="-- Choose Inspector --"><em class="text-muted">-- Choose Inspector --</em></div>
                                    <?php foreach($staffs as $s):
                                        $userRole = strtolower($s['role']);
                                        if($userRole === 'inspector'): ?>
                                            <div class="sd-item" data-value="<?= $s['id'] ?>" data-label="<?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?>">
                                                <?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle ms-1" style="font-size:0.65rem;">Inspector</span>
                                            </div>
                                    <?php endif; endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Schedule Details -->
                <div class="form-section">
                    <div class="form-section-title"><i class="bi bi-calendar-event"></i> Schedule Details</div>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Inspection Date &amp; Time</label>
                            <input type="datetime-local" name="scheduled_at" class="form-control" required min="<?= date('Y-m-d\TH:i') ?>">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Remarks / Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Specific items to monitor..."></textarea>
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer border-top-0">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-4" id="btnSaveSchedule">
                    <i class="bi bi-check-lg me-1"></i> Save Schedule
                </button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true" aria-labelledby="viewModalLabel">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header text-white">
                <div class="d-flex align-items-center">
                    <span class="modal-header-icon"><i class="bi bi-info-circle"></i></span>
                    <div>
                        <h5 class="modal-title mb-0" id="viewModalLabel">Inspection Details &amp; Checklist</h5>
                        <div class="modal-header-subtitle">Review project info, checklist, and file a violation if needed</div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="form-section">
                    <div class="mb-4 text-center">
                        <h6 class="text-muted small fw-bold mb-1">PROJECT NAME</h6>
                        <h4 id="view_project_name" class="fw-bold text-primary mb-0"></h4>
                        <span id="view_app_number" class="badge bg-light text-secondary border mt-2"></span>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-6">
                            <label class="form-label mb-1">INSPECTOR</label>
                            <div class="view-info-box"><span id="view_inspector"></span></div>
                        </div>
                        <div class="col-6">
                            <label class="form-label mb-1">DATE &amp; TIME</label>
                            <div class="view-info-box"><span id="view_date"></span></div>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label mb-1">REMARKS / NOTES FROM SCHEDULER</label>
                        <div id="view_notes" class="view-info-box notes-box" style="min-height: 60px;"></div>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100 view-panel">
                            <div class="card-header view-panel-header checklist-header">
                                <i class="bi bi-card-checklist me-2"></i>INSPECTION CHECKLIST
                            </div>
                            <div class="card-body">
    <form id="checklistForm">
        <input type="hidden" name="inspection_id" id="checklist_ins_id">

        <div class="mb-3">
            <h6 class="fw-bold text-primary border-bottom pb-1 small text-uppercase">
                <i class="bi bi-map-fill me-1"></i> I. Zoning & Land Use Compliance
            </h6>
            <div class="form-check mb-2">
                <input class="form-check-input border-secondary" type="checkbox" name="land_use_check" id="check_land" <?= $isZoningStaff ? 'disabled' : 'required' ?>>
                <label class="form-check-label small fw-bold" for="check_land">
                    ACTUAL LAND USE VERIFICATION
                </label>
                <div class="text-muted" style="font-size: 0.75rem;">
                    The actual use of the building/lot is consistent with the Zoning Classification (e.g., R-1, C-2) and the approved Development Permit.
                </div>
            </div>
        </div>

        <div class="mb-3">
            <h6 class="fw-bold text-primary border-bottom pb-1 small text-uppercase">
                <i class="bi bi-rulers me-1"></i> II. Development Standards
            </h6>
            <div class="form-check mb-2">
                <input class="form-check-input border-secondary" type="checkbox" name="plan_consistency" id="check_plan" <?= $isZoningStaff ? 'disabled' : 'required' ?>>
                <label class="form-check-label small fw-bold" for="check_plan">
                    PLAN & SETBACK CONSISTENCY
                </label>
                <div class="text-muted" style="font-size: 0.75rem;">
                    As-built structure conforms to the required setbacks, building footprint, and dimensions indicated in the automated zoning plan.
                </div>
            </div>
        </div>

        <div class="mb-3">
            <h6 class="fw-bold text-primary border-bottom pb-1 small text-uppercase">
                <i class="bi bi-search me-1"></i> III. Monitoring & Expansion Control
            </h6>
            <div class="form-check mb-2">
                <input class="form-check-input border-secondary" type="checkbox" name="expansion_check" id="check_expansion" <?= $isZoningStaff ? 'disabled' : 'required' ?>>
                <label class="form-check-label small fw-bold" for="check_expansion">
                    NON-VIOLATION OF EXPANSION
                </label>
                <div class="text-muted" style="font-size: 0.75rem;">
                    No unauthorized horizontal or vertical expansions beyond the Floor Area Ratio (FAR) allowed in the Zoning Ordinance.
                </div>
            </div>
        </div>

        <?php if ($isInspector): ?>
        <div class="mb-3 pt-2 border-top">
            <label class="form-label small fw-bold text-muted text-uppercase">Inspector's Remarks</label>
            <textarea name="inspection_notes" class="form-control form-control-sm border-primary" rows="2" placeholder="Input specific zoning findings here..."></textarea>
        </div>
        <button type="button" class="btn btn-primary btn-sm w-100 fw-bold shadow-sm py-2" onclick="saveChecklist()">
            <i class="bi bi-shield-check me-1"></i> VALIDATE ZONING COMPLIANCE
        </button>
        <?php else: ?>
        <div class="mb-3 pt-2 border-top">
            <div class="alert alert-secondary py-2 small mb-0 text-muted">
                <i class="bi bi-lock-fill me-1"></i> Inspector's Remarks and compliance validation are restricted to the assigned Inspector.
            </div>
        </div>
        <?php endif; ?>
    </form>
</div>
                        </div>
                    </div>

<div class="col-md-6">
    <div class="card border-0 shadow-sm h-100 view-panel" id="violationSection">
        <div class="card-header view-panel-header violation-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-exclamation-triangle-fill me-2"></i>OFFICIAL VIOLATION REPORT</span>
            <span class="badge bg-white text-danger">LEGAL ACTION</span>
        </div>
        <div class="card-body">
            <form id="violationForm" enctype="multipart/form-data">
                <input type="hidden" name="inspection_id" id="viol_ins_id">
                <input type="hidden" name="application_id" id="viol_app_id">
                
                <div class="mb-2">
                    <label class="small fw-bold mb-1 text-danger">NATURE OF VIOLATION</label>
                    <select name="violation_type" class="form-select form-select-sm shadow-sm border-danger" <?= $isZoningStaff ? 'disabled' : 'required' ?>>
                        <option value="">-- Select Critical Violation --</option>
                        <option value="Deviation from Approved Plan">Deviation from Approved Plan (Blueprint Mismatch)</option>
                        <option value="Encroachment/Illegal Expansion">Encroachment/Illegal Expansion (Boundary/Setback Violation)</option>
                        <option value="Unauthorized Change of Use">Change of Use (e.g. Residential to Industrial)</option>
                        <option value="Safety & Structural Hazard">Structural/Safety Hazard</option>
                        <option value="No Valid Permits/Documentation">Lack of Proper Documentation</option>
                    </select>
                </div>

                <div class="mb-2">
                    <label class="small fw-bold mb-1 text-danger">PHOTO EVIDENCE (Violation Proof)</label>
                    <input type="file" name="violation_photo" class="form-control form-control-sm shadow-sm border-danger" accept="image/*" <?= $isZoningStaff ? 'disabled' : 'required' ?>>
                </div>

                <div class="mb-3">
                    <label class="small fw-bold mb-1 text-danger">REMARKS / FINDINGS</label>
                    <textarea name="notes" class="form-control form-control-sm shadow-sm border-danger" rows="3" placeholder="State exact details (e.g., 'Extra floor added without permit')" <?= $isZoningStaff ? 'disabled' : 'required' ?>></textarea>
                </div>

                <div class="mt-3 mb-2 px-1 text-danger" style="font-size: 0.7rem; line-height: 1.2;">
                    <i class="bi bi-info-circle-fill me-1"></i> 
                    <strong>SYSTEM PROTOCOL:</strong> Submission will trigger a <u>VIOLATION DETECTED</u> status. 
                    This action automatically suspends Certificate issuance and flags the application for mandatory resolution.
                </div>

                <?php if ($isInspector): ?>
                <button type="submit" class="btn btn-danger btn-sm w-100 fw-bold shadow-sm mt-1">
                    <i class="bi bi-file-earmark-pdf-fill me-1"></i> ISSUE NOTICE OF VIOLATION
                </button>
                <?php else: ?>
                <div class="alert alert-warning py-2 px-3 small mt-1 mb-0">
                    <i class="bi bi-lock me-1"></i> Only the assigned Inspector can file a violation report.
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>
                </div> </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
const ACTION_PATH = '../modules/MonitoringAndInspection/monitoring_action.php';

function openScheduleModal() {
    const myModal = new bootstrap.Modal(document.getElementById('scheduleModal'));
    myModal.show();
}

document.addEventListener('DOMContentLoaded', function() {
    // === 1. CALENDAR INITIALIZATION ===
    const calendarEl = document.getElementById('inspectionCalendar');
    if (calendarEl) {
        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            themeSystem: 'bootstrap5',
            eventDisplay: 'block', 
            displayEventTime: false, 
            headerToolbar: { 
                left: 'prev,next', 
                center: 'title', 
                right: 'today' 
            },
            events: ACTION_PATH + '?action=fetch_events',
            height: 'auto',
            eventMaxStack: 2, 
            dayMaxEvents: true,
            eventClick: function(info) {
                Swal.fire({
                    title: 'Cancel Inspection?',
                    text: "Delete schedule for " + info.event.title + "?",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    confirmButtonText: 'Yes, delete it!',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        const fd = new FormData();
                        fd.append('id', info.event.id);
                        fetch(ACTION_PATH + '?action=delete_event', { method: 'POST', body: fd })
                        .then(res => res.json())
                        .then(data => {
                            if(data.success) {
                                info.event.remove();
                                Swal.fire('Deleted!', 'Schedule removed.', 'success');
                            }
                        });
                    }
                });
            },
            eventDataTransform: function(item) {
                const shortID = item.application_number.split('-').pop();
                return {
                    id: item.id,
                    title: '#' + shortID, 
                    start: item.scheduled_at,
                    allDay: true, 
                    backgroundColor: item.status === 'completed' ? '#198754' : '#ffc107',
                    borderColor: 'transparent',
                    textColor: item.status === 'completed' ? '#ffffff' : '#000000'
                };
            }
        });
        calendar.render();
    }

    // === 2. FORM SUBMISSIONS ===
    const insForm = document.getElementById('inspectionForm');
    if(insForm) {
        insForm.onsubmit = function(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSaveSchedule');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';

            fetch(ACTION_PATH + '?action=save_schedule', { method: 'POST', body: new FormData(this) })
            .then(res => res.json())
            .then(data => {
                if(data.success) { 
                    Swal.fire({ icon: 'success', title: 'Schedule Saved!', showConfirmButton: false, timer: 1500 })
                    .then(() => location.reload());
                } else { 
                    Swal.fire('Error', data.message || "Error saving.", 'error');
                    btn.disabled = false;
                    btn.innerText = 'Save Schedule';
                }
            });
        };
    }

// I-update ang onsubmit handler para sa Violation Form
const violForm = document.getElementById('violationForm');
if(violForm) {
    violForm.onsubmit = function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';

        // Gagamit ng FormData para masalo ang File Upload
        const fd = new FormData(this);

        fetch(ACTION_PATH + '?action=report_violation', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Violation Reported',
                    text: 'The application has been flagged and the Notice of Violation is now active.',
                    confirmButtonText: 'Print Notice'
                }).then(() => {
                    // Dito pwede mo i-redirect sa isang printable page (Optional sa defense)
                    // window.open('print_notice.php?id=' + fd.get('application_id'), '_blank');
                    location.reload();
                });
            } else {
                Swal.fire('Error', data.message, 'error');
                btn.disabled = false;
            }
        })
        .catch(err => {
            console.error(err);
            Swal.fire('Server Error', 'Check your file upload size or directory permissions.', 'error');
            btn.disabled = false;
        });
    };
}
});

// === 3. GLOBAL FUNCTIONS ===

function viewInspectionDetails(data) {
    document.getElementById('view_project_name').innerText = data.project_name || 'Project Name N/A';
    document.getElementById('view_app_number').innerText = 'App #' + data.application_number;
    document.getElementById('view_inspector').innerText = data.inspector_name;
    const scheduledAt = data.scheduled_at;
    const parsedDate = scheduledAt ? new Date(scheduledAt) : null;
    const isValidDate = parsedDate && !isNaN(parsedDate.getTime()) && parsedDate.getFullYear() > 1970;
    document.getElementById('view_date').innerText = isValidDate
        ? parsedDate.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' })
        : 'TBD / No Schedule';
    document.getElementById('view_notes').innerText = data.notes || 'No notes recorded for this schedule.';
    
    if(document.getElementById('checklist_ins_id')) {
        document.getElementById('checklist_ins_id').value = data.id;
    }
    document.getElementById('viol_ins_id').value = data.id;
    document.getElementById('viol_app_id').value = data.application_id;

    const myModal = new bootstrap.Modal(document.getElementById('viewModal'));
    myModal.show();
}

function saveChecklist() {
    const form = document.getElementById('checklistForm');
    if(!form) return;

    const checkboxes = form.querySelectorAll('input[type="checkbox"]');
    let allChecked = true;
    checkboxes.forEach(cb => { if(!cb.checked) allChecked = false; });

    if(!allChecked) {
        Swal.fire({
            icon: 'error',
            title: 'Zoning Non-Compliance',
            text: 'Cannot complete inspection. One or more requirements are NOT compliant.',
            confirmButtonColor: '#d33'
        });
        return; 
    }

    Swal.fire({
        title: 'Submit Zoning Report?',
        text: "Confirming this will mark the project as COMPLIANT and notify the applicant.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0d6efd',
        confirmButtonText: 'Yes, Submit Result'
    }).then((result) => {
        if (result.isConfirmed) {
            const fd = new FormData(form);
            const insID = document.getElementById('checklist_ins_id').value;

            // 1. I-save ang Checklist status
            fetch(ACTION_PATH + '?action=save_checklist', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    // 2. Ipakita ang Success Alert muna
                    Swal.fire({
                        icon: 'success',
                        title: 'Zoning Validated',
                        text: 'Compliance report has been filed and official notice is being sent.',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        // 3. I-trigger ang pag-send ng Professional LGU Message
                        const msgData = new FormData();
                        msgData.append('inspection_id', insID);
                        
                        fetch(ACTION_PATH + '?action=send_approval_message', { 
                            method: 'POST', 
                            body: msgData 
                        })
                        .then(() => {
                            location.reload(); // Reload pagkatapos ma-send ang message
                        });
                    });
                } else {
                    Swal.fire('Error', 'Failed to update record.', 'error');
                }
            });
        }
    });
}

// === SEARCHABLE DROPDOWNS ===
function initSearchableDropdown(dropdownId, hiddenId, searchId, listId) {
    const wrap     = document.getElementById(dropdownId);
    const hidden   = document.getElementById(hiddenId);
    const search   = document.getElementById(searchId);
    const list     = document.getElementById(listId);
    const clearBtn = wrap ? wrap.querySelector('.sd-clear') : null;
    if (!wrap || !hidden || !search || !list) return;

    const items = list.querySelectorAll('.sd-item');

    function openList() { list.classList.add('open'); }
    function closeList() { list.classList.remove('open'); }

    search.addEventListener('focus', openList);
    search.addEventListener('input', function() {
        const q = this.value.toLowerCase();
        let hasMatch = false;
        list.querySelectorAll('.sd-no-results').forEach(el => el.remove());
        items.forEach(item => {
            const label = (item.dataset.label || item.textContent).toLowerCase();
            const show  = label.includes(q);
            item.style.display = show ? '' : 'none';
            if (show) hasMatch = true;
        });
        if (!hasMatch) {
            const noRes = document.createElement('div');
            noRes.className = 'sd-no-results';
            noRes.textContent = 'No results found.';
            list.appendChild(noRes);
        }
        if (clearBtn) clearBtn.style.display = this.value ? 'block' : 'none';
        openList();
    });

    list.addEventListener('mousedown', function(e) {
        const item = e.target.closest('.sd-item');
        if (!item) return;
        e.preventDefault();
        const val   = item.dataset.value || '';
        const label = item.dataset.label || item.textContent.trim();
        hidden.value  = val;
        search.value  = val ? label : '';
        if (clearBtn) clearBtn.style.display = val ? 'block' : 'none';
        items.forEach(i => i.classList.remove('selected'));
        item.classList.add('selected');
        closeList();
    });

    document.addEventListener('mousedown', function(e) {
        if (!wrap.contains(e.target)) closeList();
    });
}

function clearSD(dropdownId, hiddenId, searchId) {
    document.getElementById(hiddenId).value = '';
    const s = document.getElementById(searchId);
    s.value = '';
    const wrap = document.getElementById(dropdownId);
    if (wrap) {
        wrap.querySelectorAll('.sd-item').forEach(i => { i.style.display = ''; i.classList.remove('selected'); });
        wrap.querySelectorAll('.sd-no-results').forEach(el => el.remove());
        const clr = wrap.querySelector('.sd-clear');
        if (clr) clr.style.display = 'none';
    }
    s.focus();
}

// Reset dropdowns when modal opens
document.addEventListener('DOMContentLoaded', function() {
    initSearchableDropdown('appDropdown',  'application_id_val', 'appSearch',  'appList');
    initSearchableDropdown('inspDropdown', 'inspector_id_val',   'inspSearch', 'inspList');

    const schedModal = document.getElementById('scheduleModal');
    if (schedModal) {
        schedModal.addEventListener('show.bs.modal', function() {
            clearSD('appDropdown',  'application_id_val', 'appSearch');
            clearSD('inspDropdown', 'inspector_id_val',   'inspSearch');
        });
    }
});

</script>
<?php include __DIR__ . '/../admin/footer.php'; ?>