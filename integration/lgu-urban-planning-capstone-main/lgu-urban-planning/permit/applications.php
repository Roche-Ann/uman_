<?php
/**
 * Applications List (Staff View) - Integrated Manual Add & Overdue Filter
 */

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helper.php';
require_once __DIR__ . '/../modules/PermitProcessing/PermitController.php';

$auth = new Auth();
$auth->requireRole(['admin', 'super_admin', 'zoning_officer', 'building_official', 'assessor', 'inspector']);

$db = Database::getInstance();
$permitController = new PermitController();

// --- START: MANUAL ADD LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'manual_add') {
    try {
        $dbConn = $db->getConnection();
        $dbConn->beginTransaction();

        $appNumber = Helper::generateApplicationNumber();
        
        // Dito natin kukunin ang buong string (e.g. 114-05-002-01-001)
        $fullParcelId = $_POST['parcel_id'] ?? ''; 

        $sql = "INSERT INTO applications 
                (application_number, applicant_id, parcel_id, project_name, project_type, project_description, 
                 lot_number, barangay, district, street, block, latitude, longitude, status, record_type, submitted_at, created_at) 
                VALUES 
                (:application_number, :applicant_id, :parcel_id, :project_name, :project_type, :project_description, 
                 :lot_number, :barangay, :district, :street, :block, :latitude, :longitude, :status, :record_type, :submitted_at, :created_at)";
        
        $params = [
            ':application_number'  => $appNumber,
            ':applicant_id'        => $_POST['applicant_id'],
            ':parcel_id'           => $fullParcelId, 
            ':project_name'        => $_POST['project_name'],
            ':project_type'        => $_POST['project_type'],
            ':project_description' => $_POST['project_description'],
            ':lot_number'          => $_POST['lot_number'],
            ':barangay'            => $_POST['barangay'],
            ':district'            => $_POST['district'] ?? null,
            ':street'              => $_POST['street'],
            ':block'               => $_POST['block'],
            ':latitude'            => $_POST['latitude'],
            ':longitude'           => $_POST['longitude'],
            ':status'              => 'submitted',
            ':record_type'         => 'walk-in',
            ':submitted_at'        => date('Y-m-d H:i:s'),
            ':created_at'          => date('Y-m-d H:i:s')
        ];

        $stmt = $dbConn->prepare($sql);
        $stmt->execute($params);

        $dbConn->commit();
        header("Location: applications.php?success=1");
        exit();

    } catch (Exception $e) {
        if (isset($dbConn)) $dbConn->rollBack();
        $error_msg = "Error creating application: " . $e->getMessage();
    }
}
// --- END: MANUAL ADD LOGIC ---

$allApplicants = $db->fetchAll("SELECT id, first_name, last_name FROM users WHERE role = 'applicant' ORDER BY last_name ASC");

$filters = [
    'status'    => $_GET['status'] ?? '',
    'search'    => $_GET['search'] ?? '',
    'filter'    => $_GET['filter'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to'   => $_GET['date_to'] ?? '',
    'sort'      => $_GET['sort'] ?? 'newest'
];

if ($filters['status'] === 'overdue') {
    $filters['filter'] = 'overdue';
}

$applications = $permitController->getApplications($filters);

// --- DATE RANGE FILTER ---
if (!empty($filters['date_from'])) {
    $dateFromTs = strtotime($filters['date_from'] . ' 00:00:00');
    $applications = array_filter($applications, function ($app) use ($dateFromTs) {
        return strtotime($app['created_at']) >= $dateFromTs;
    });
}
if (!empty($filters['date_to'])) {
    $dateToTs = strtotime($filters['date_to'] . ' 23:59:59');
    $applications = array_filter($applications, function ($app) use ($dateToTs) {
        return strtotime($app['created_at']) <= $dateToTs;
    });
}
$applications = array_values($applications);

// --- SORTING ---
switch ($filters['sort']) {
    case 'oldest':
        usort($applications, function ($a, $b) {
            return strtotime($a['created_at']) <=> strtotime($b['created_at']);
        });
        break;
    case 'name_asc':
        usort($applications, function ($a, $b) {
            return strcasecmp($a['project_name'], $b['project_name']);
        });
        break;
    case 'status':
        usort($applications, function ($a, $b) {
            return strcasecmp($a['status'], $b['status']);
        });
        break;
    case 'newest':
    default:
        usort($applications, function ($a, $b) {
            return strtotime($b['created_at']) <=> strtotime($a['created_at']);
        });
        break;
}

// --- PAGINATION CONFIGURATION (style matches audit-logs.php) ---
$limit = 15;
$page = isset($_GET['p']) && is_numeric($_GET['p']) ? (int)$_GET['p'] : 1;
if ($page < 1) $page = 1;

$totalApplications = count($applications);
$totalPages = max(1, ceil($totalApplications / $limit));
if ($page > $totalPages) $page = $totalPages;

$offset = ($page - 1) * $limit;
$applications = array_slice($applications, $offset, $limit);

$query_string = http_build_query(array_filter($filters));

$pageTitle = 'Applications';
$isAuthPage = true;
include __DIR__ . '/../admin/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<style>
    #map-container { height: 300px; width: 100%; border-radius: 8px; margin-top: 10px; border: 1px solid #ddd; }
    .select2-container--bootstrap-5 .select2-selection { border-radius: 0.375rem; }
    .table-danger:hover td { background-color: #f8d7da !important; }

    .table thead th,
    .table tbody td {
        white-space: nowrap;
        vertical-align: middle;
    }

    .project-name-cell {
        white-space: normal !important;
        min-width: 200px;
    }

    /* Pagination — same style as audit-logs.php */
    .pagination .page-link { color: #2c3e50; border: 1px solid #dee2e6; margin: 0 2px; border-radius: 4px; }
    .pagination .page-item.active .page-link { background-color: #0d6efd; border-color: #0d6efd; color: white; }
    .pagination .page-link:hover { background-color: #e7f1ff; border-color: #b6d4fe; color: #0d6efd; }
    .info-text { font-size: 0.875rem; color: #6c757d; }

    /* ================================================
       MANUAL ADD MODAL — modern / professional redesign
       ================================================ */
    #manualAddModal .modal-content {
        border-radius: 16px;
        overflow: hidden;
    }

    #manualAddModal .modal-header {
        background: linear-gradient(135deg, #1c4e9e 0%, #0d6efd 100%);
        border-bottom: none;
        padding: 1.25rem 1.5rem;
    }
    #manualAddModal .modal-header-icon {
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
    #manualAddModal .modal-title {
        font-size: 1.15rem;
        font-weight: 700;
        letter-spacing: -0.01em;
    }
    #manualAddModal .modal-header-subtitle {
        font-size: 0.8rem;
        color: rgba(255, 255, 255, 0.75);
        margin-top: 1px;
    }

    #manualAddModal .modal-body {
        background: #f6f8fb;
        padding: 1.75rem;
    }

    #manualAddModal .form-section {
        background: #ffffff;
        border: 1px solid #eaeef3;
        border-radius: 12px;
        padding: 1.25rem 1.5rem 1.5rem;
        margin-bottom: 1.25rem;
    }
    #manualAddModal .form-section:last-child { margin-bottom: 0; }

    #manualAddModal .form-section-title {
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
    #manualAddModal .form-section-title i {
        font-size: 0.95rem;
        color: #0d6efd;
    }

    #manualAddModal .modal-body .form-label {
        font-weight: 600;
        font-size: 0.76rem;
        text-transform: uppercase;
        letter-spacing: 0.02em;
        color: #5a6474;
        margin-bottom: 0.4rem;
    }

    #manualAddModal .modal-body .form-control,
    #manualAddModal .modal-body .form-select {
        border: 1.5px solid #e2e6ec;
        border-radius: 9px;
        padding: 0.55rem 0.85rem;
        font-size: 0.9rem;
        background-color: #fcfdfe;
        transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
    }
    #manualAddModal .modal-body .form-control:focus,
    #manualAddModal .modal-body .form-select:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.12);
        background-color: #ffffff;
    }
    #manualAddModal .modal-body .form-control::placeholder { color: #a7b0bd; }
    #manualAddModal .modal-body textarea.form-control { resize: vertical; }

    /* Select2 (live search) — match the modernized input style */
    #manualAddModal .select2-container--bootstrap-5 .select2-selection {
        border: 1.5px solid #e2e6ec;
        border-radius: 9px;
        min-height: calc(1.5em + 1.1rem + 3px);
        padding: 0.55rem 0.85rem;
        background-color: #fcfdfe;
    }
    #manualAddModal .select2-container--bootstrap-5.select2-container--focus .select2-selection,
    #manualAddModal .select2-container--bootstrap-5.select2-container--open .select2-selection {
        border-color: #0d6efd;
        box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.12);
        background-color: #ffffff;
    }

    #manualAddModal #btn-select-map {
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.78rem;
        border-width: 1.5px;
    }

    #manualAddModal #map-container {
        border-radius: 10px;
        overflow: hidden;
    }

    #manualAddModal .modal-footer {
        background: #ffffff;
        border-top: 1px solid #eef0f3;
        padding: 1.1rem 1.5rem;
        gap: 0.6rem;
    }
    #manualAddModal .modal-footer .btn {
        border-radius: 9px;
        font-weight: 600;
        font-size: 0.88rem;
        padding: 0.55rem 1.4rem;
        transition: transform 0.12s ease, box-shadow 0.12s ease;
    }
    #manualAddModal .modal-footer .btn-primary {
        background: linear-gradient(135deg, #1c4e9e 0%, #0d6efd 100%);
        border: none;
        box-shadow: 0 4px 12px rgba(13, 110, 253, 0.28);
    }
    #manualAddModal .modal-footer .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(13, 110, 253, 0.35);
    }
    #manualAddModal .modal-footer .btn-outline-secondary {
        border: 1.5px solid #dde1e7;
        color: #5a6474;
        background: #fff;
    }
    #manualAddModal .modal-footer .btn-outline-secondary:hover {
        background: #f6f8fb;
        border-color: #c7cdd6;
    }

    /* Manual Add Application — gradient button (green variant) */
    .btn-manual-add {
        background: linear-gradient(135deg, #0f7a4e 0%, #17a566 100%);
        border: none;
        color: #fff;
        border-radius: 9px;
        font-weight: 600;
        padding: 0.55rem 1.4rem;
        box-shadow: 0 4px 12px rgba(23, 165, 102, 0.32);
        transition: transform 0.12s ease, box-shadow 0.12s ease, color 0.12s ease;
    }
    .btn-manual-add:hover {
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(23, 165, 102, 0.4);
    }
    .btn-manual-add:active,
    .btn-manual-add:focus {
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
       MOBILE RESPONSIVE
       768px (Tablet) | 480px (Large Mobile) | 320px (Small Mobile)
       ================================================ */

    /* --- 768px: Tablet --- */
    @media (max-width: 768px) {

        /* Page header */
        .p-4 { padding: 1rem !important; }

        .d-flex.justify-content-between.align-items-center.mb-4 {
            flex-direction: column;
            align-items: flex-start !important;
            gap: 10px;
        }
        .d-flex.justify-content-between.align-items-center.mb-4 h2 {
            font-size: 1.25rem;
            margin-bottom: 0;
        }
        .d-flex.justify-content-between.align-items-center.mb-4 .btn {
            width: 100%;
            font-size: 0.875rem;
        }

        /* Filter form: stack inputs */
        .card-body .row.g-3 .col-md-4,
        .card-body .row.g-3 .col-md-3,
        .card-body .row.g-3 .col-md-2,
        .card-body .row.g-3 .col-md-1 {
            width: 100%;
            flex: 0 0 100%;
        }

        /* Overdue alert: stack button below text */
        .alert.d-flex.justify-content-between {
            flex-direction: column;
            gap: 10px;
            align-items: flex-start !important;
        }
        .alert.d-flex.justify-content-between .btn {
            width: 100%;
            text-align: center;
        }

        /* Table: shrink font, hide lower-priority columns */
        .table { font-size: 0.8rem; }
        .table th, .table td { padding: 0.5rem 0.4rem; }

        /* Hide: Assigned To, Date */
        .table thead th:nth-child(5),
        .table tbody td:nth-child(5),
        .table thead th:nth-child(6),
        .table tbody td:nth-child(6) { display: none; }

        .project-name-cell { min-width: 130px; }

        /* Modal — override Bootstrap modal-lg max-width so it fits the viewport */
        .modal-dialog.modal-lg {
            max-width: calc(100% - 1rem) !important;
            width: calc(100% - 1rem) !important;
            margin: 0.5rem auto;
        }
        .modal-body { padding: 1rem !important; }

        /* Modal form: restore 2-column layout for larger fields at 768px */
        .modal-body .col-md-8 { width: 66.66%; flex: 0 0 66.66%; }
        .modal-body .col-md-4 { width: 33.33%; flex: 0 0 33.33%; }
        .modal-body .col-md-6 { width: 50%;    flex: 0 0 50%; }

        #manualAddModal .modal-footer { padding: 0.75rem 1rem; }
        #manualAddModal .modal-footer .btn { padding: 8px 20px; font-size: 0.875rem; }
        #map-container { height: 240px; }

        /* Pagination */
        .card-footer .row { flex-direction: column; gap: 10px; text-align: center; }
        .card-footer .col-md-6:last-child { text-align: center !important; }
        .pagination { justify-content: center !important; }
    }

    /* --- 480px: Large Mobile --- */
    @media (max-width: 480px) {

        .p-4 { padding: 0.75rem !important; }

        /* Page header */
        .d-flex.justify-content-between.align-items-center.mb-4 h2 { font-size: 1.1rem; }
        .d-flex.justify-content-between.align-items-center.mb-4 .btn {
            font-size: 0.82rem;
            padding: 7px 12px;
        }

        /* Filter card */
        .card-body { padding: 0.75rem !important; }
        .card-body .row.g-3 { --bs-gutter-y: 0.5rem; }
        .form-control, .form-select { font-size: 0.82rem; padding: 6px 10px; }

        /* Table: also hide Applicant col */
        .table { font-size: 0.74rem; }
        .table th, .table td { padding: 0.4rem 0.3rem; }

        .table thead th:nth-child(3),
        .table tbody td:nth-child(3),
        .table thead th:nth-child(5),
        .table tbody td:nth-child(5),
        .table thead th:nth-child(6),
        .table tbody td:nth-child(6) { display: none; }

        /* Keep App# shorter */
        .table thead th:first-child,
        .table tbody td:first-child { padding-left: 0.5rem !important; }
        .table thead th:last-child,
        .table tbody td:last-child { padding-right: 0.5rem !important; }

        .table .btn-sm { font-size: 0.7rem; padding: 3px 10px; }
        .table .badge { font-size: 0.65rem; padding: 3px 6px; }
        .project-name-cell { min-width: 100px; font-size: 0.74rem; }

        /* Modal */
        #manualAddModal .modal-header { padding: 0.75rem 1rem; }
        #manualAddModal .modal-title { font-size: 0.95rem; }
        .modal-body { padding: 0.75rem !important; }
        .modal-body .row.g-3 { --bs-gutter-y: 0.5rem; }
        #manualAddModal .modal-body .form-label { font-size: 0.72rem; margin-bottom: 2px; }
        #manualAddModal .modal-body .form-control,
        #manualAddModal .modal-body .form-select { font-size: 0.82rem; padding: 6px 9px; }
        #manualAddModal .modal-footer .btn { padding: 7px 16px; font-size: 0.82rem; }
        #map-container { height: 200px; }

        /* Coord row: stack lat/lng */
        .modal-body .row.g-2 .col-md-6 {
            width: 100%;
            flex: 0 0 100%;
        }

        /* Pagination */
        .pagination .page-link { font-size: 0.72rem; padding: 4px 8px; }
        .card-footer { padding: 0.6rem 0.75rem; }
        .info-text { font-size: 0.72rem; }
    }

    /* --- 320px: Small Mobile --- */
    @media (max-width: 320px) {

        .p-4 { padding: 0.5rem !important; }

        /* Page header */
        .d-flex.justify-content-between.align-items-center.mb-4 h2 { font-size: 1rem; }
        .d-flex.justify-content-between.align-items-center.mb-4 .btn {
            font-size: 0.78rem;
            padding: 6px 10px;
        }

        /* Filter */
        .card-body { padding: 0.6rem !important; }
        .form-control, .form-select { font-size: 0.78rem; padding: 5px 8px; }
        .card-body .row.g-3 { --bs-gutter-y: 0.4rem; }

        /* Table: keep only App#, Status, Action */
        .table { font-size: 0.68rem; }
        .table th, .table td { padding: 0.35rem 0.25rem; }

        .table thead th:nth-child(2),
        .table tbody td:nth-child(2),
        .table thead th:nth-child(3),
        .table tbody td:nth-child(3),
        .table thead th:nth-child(5),
        .table tbody td:nth-child(5),
        .table thead th:nth-child(6),
        .table tbody td:nth-child(6) { display: none; }

        .table .btn-sm { font-size: 0.62rem; padding: 2px 7px; }
        .table .badge { font-size: 0.6rem; padding: 2px 5px; }

        /* Alerts */
        .alert { font-size: 0.78rem; padding: 0.6rem 0.75rem; }

        /* Modal */
        .modal-dialog { margin: 0.25rem; }
        #manualAddModal .modal-header { padding: 0.6rem 0.75rem; }
        #manualAddModal .modal-title { font-size: 0.88rem; }
        .modal-body { padding: 0.6rem !important; }
        #manualAddModal .modal-body .form-label { font-size: 0.68rem; }
        #manualAddModal .modal-body .form-control,
        #manualAddModal .modal-body .form-select { font-size: 0.78rem; padding: 5px 8px; }
        .modal-body .row.g-3 { --bs-gutter-y: 0.35rem; }
        #manualAddModal .modal-footer { padding: 0.5rem 0.75rem; gap: 6px; }
        #manualAddModal .modal-footer .btn { padding: 6px 12px; font-size: 0.78rem; }
        /* Side-by-side buttons at 320px */
        #manualAddModal .modal-footer {
            display: flex !important;
            flex-direction: row !important;
            justify-content: stretch;
        }
        #manualAddModal .modal-footer .btn {
            flex: 1;
            text-align: center;
        }
        #map-container { height: 180px; }

        /* Pick on Map button — full width */
        #btn-select-map { width: 100%; margin-top: 4px; font-size: 0.75rem; }
        .d-flex.justify-content-between.align-items-center.mb-2 {
            flex-direction: column;
            align-items: flex-start !important;
            gap: 4px;
        }

        /* Pagination: show only prev/next + current */
        .pagination .page-item:not(:first-child):not(:nth-child(2)):not(:last-child):not(:nth-last-child(2)) { display: none; }
        .pagination .page-link { font-size: 0.65rem; padding: 3px 7px; }
        .card-footer { padding: 0.5rem; }
        .info-text { font-size: 0.65rem; }
    }
</style>

<div class="p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0 d-flex align-items-center gap-2" style="color: #1e293b;">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle">
                    <i class="bi bi-file-earmark-text" style="color:#10b981;font-size:1.9rem;"></i>
                </span>
                Development Permit Applications
            </h2>
            <p class="text-muted mb-0">Manage and monitor all development permit applications.</p>
        </div>
        <button type="button" class="btn btn-manual-add" data-bs-toggle="modal" data-bs-target="#manualAddModal">
            <i class="bi bi-plus-lg me-1"></i> Manual Add Application
        </button>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle me-2"></i> Application successfully created and linked!
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_msg)): ?>
        <div class="alert alert-danger shadow-sm">
            <i class="bi bi-exclamation-octagon me-2"></i> <?php echo htmlspecialchars($error_msg); ?>
        </div>
    <?php endif; ?>

    <?php if ($filters['filter'] === 'overdue'): ?>
        <div class="alert alert-danger d-flex justify-content-between align-items-center mb-4 shadow-sm" style="border-radius: 10px; border-left: 5px solid #dc3545;">
            <div>
                <i class="bi bi-clock-history me-2"></i>
                <strong>Filtered View:</strong> Showing applications pending for more than 3 days.
            </div>
            <a href="applications.php" class="btn btn-sm btn-outline-danger">Clear Filter</a>
        </div>
    <?php endif; ?>
    
    <div class="card mb-3 shadow-sm border-0">
        <div class="card-body">
            <form method="GET" class="row g-3" id="filterForm">
                <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted" id="appSearchIcon"></i></span>
                        <input type="text" class="form-control border-start-0 border-end-0" name="search" id="appSearchInput"
                               placeholder="Search project or applicant..."
                               value="<?php echo htmlspecialchars($filters['search']); ?>"
                               autocomplete="off">
                        <button type="button" id="appClearSearch" class="btn btn-outline-secondary <?php echo $filters['search'] ? '' : 'd-none'; ?>" title="Clear">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="status" id="appStatusSelect">
                        <option value="">All Status</option>
                        <option value="submitted" <?php echo $filters['status'] === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                        <option value="under_review" <?php echo $filters['status'] === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                        <option value="for_revision" <?php echo $filters['status'] === 'for_revision' ? 'selected' : ''; ?>>For Revision</option>
                        <option value="approved" <?php echo $filters['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $filters['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="overdue" <?php echo ($filters['status'] === 'overdue') ? 'selected' : ''; ?> style="color: #dc3545; font-weight: bold;">Overdue (3+ Days)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-calendar3 text-muted"></i></span>
                        <input type="text" class="form-control border-start-0 border-end-0" id="appDateRangeInput"
                               placeholder="Date range" autocomplete="off" readonly>
                        <button type="button" id="appClearDateRange"
                                class="btn btn-outline-secondary <?php echo (empty($filters['date_from']) && empty($filters['date_to'])) ? 'd-none' : ''; ?>"
                                title="Clear">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                    <input type="hidden" name="date_from" id="appDateFrom" value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                    <input type="hidden" name="date_to" id="appDateTo" value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="sort" id="appSortSelect">
                        <option value="newest" <?php echo ($filters['sort'] === 'newest' || $filters['sort'] === '') ? 'selected' : ''; ?>>Newest</option>
                        <option value="oldest" <?php echo $filters['sort'] === 'oldest' ? 'selected' : ''; ?>>Oldest</option>
                        <option value="name_asc" <?php echo $filters['sort'] === 'name_asc' ? 'selected' : ''; ?>>Name A-Z</option>
                        <option value="status" <?php echo $filters['sort'] === 'status' ? 'selected' : ''; ?>>Status</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <a href="applications.php" class="btn btn-outline-secondary w-100 shadow-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</a>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Application #</th>
                            <th>Project Name</th>
                            <th>Applicant</th>
                            <th>Status</th>
                            <th>Assigned To</th>
                            <th>Date</th>
                            <th class="text-center pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($applications)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">No applications found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($applications as $app): 
                                $isOverdue = false;
                                $createdDate = strtotime($app['created_at']);
                                $threeDaysAgo = strtotime('-3 days');
                                if ($createdDate <= $threeDaysAgo && !in_array($app['status'], ['approved', 'rejected', 'cancelled'])) {
                                    $isOverdue = true;
                                }
                            ?>
                            <tr class="<?php echo $isOverdue ? 'table-danger' : ''; ?>">
                                <td class="ps-4 fw-bold">
                                    <span class="d-inline-flex align-items-center">
                                        <?php echo htmlspecialchars($app['application_number']); ?>
                                        <?php if($isOverdue): ?>
                                            <i class="bi bi-exclamation-triangle-fill text-danger ms-2" title="Overdue"></i>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td class="project-name-cell"><?php echo htmlspecialchars($app['project_name']); ?></td>
                                <td><?php echo htmlspecialchars($app['applicant_first_name'] . ' ' . $app['applicant_last_name']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo Helper::getStatusBadge($app['status']); ?> px-2 py-1">
                                        <?php echo strtoupper(str_replace('_', ' ', $app['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($app['officer_first_name'])): ?>
                                        <div class="small fw-bold"><?php echo htmlspecialchars($app['officer_first_name'] . ' ' . $app['officer_last_name']); ?></div>
                                    <?php else: ?>
                                        <span class="text-muted small italic">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo Helper::formatDate($app['created_at']); ?></td>
                                <td class="text-center pe-4">
                                    <a href="view.php?id=<?php echo $app['id']; ?>" class="btn btn-sm btn-view-gradient px-3">View</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-footer py-3 border-0">
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                    <span class="info-text text-muted">
                        Showing <strong><?php echo $totalApplications > 0 ? ($offset + 1) : 0; ?></strong> to
                        <strong><?php echo min($offset + $limit, $totalApplications); ?></strong> of
                        <strong><?php echo $totalApplications; ?></strong> entries
                    </span>
                </div>
                <div class="col-md-6 text-md-end">
                    <nav aria-label="Page navigation">
                        <ul class="pagination pagination-sm justify-content-center justify-content-md-end mb-0">
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?p=1&<?php echo $query_string; ?>"><i class="bi bi-chevron-double-left"></i></a>
                            </li>
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?p=<?php echo ($page - 1); ?>&<?php echo $query_string; ?>">Prev</a>
                            </li>
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($totalPages, $page + 2);
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?p=<?php echo $i; ?>&<?php echo $query_string; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?p=<?php echo ($page + 1); ?>&<?php echo $query_string; ?>">Next</a>
                            </li>
                            <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?p=<?php echo $totalPages; ?>&<?php echo $query_string; ?>"><i class="bi bi-chevron-double-right"></i></a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="manualAddModal" aria-hidden="true" aria-labelledby="manualAddModalLabel">
    <div class="modal-dialog modal-lg">
        <form action="applications.php" method="POST" class="modal-content border-0 shadow-lg">
            <input type="hidden" name="action" value="manual_add">
            <div class="modal-header text-white">
                <div class="d-flex align-items-center">
                    <span class="modal-header-icon"><i class="bi bi-pencil-square"></i></span>
                    <div>
                        <h5 class="modal-title mb-0" id="manualAddModalLabel">Manual Application Entry</h5>
                        <div class="modal-header-subtitle">Record a walk-in or manually submitted application</div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">

                <!-- Applicant -->
                <div class="form-section">
                    <div class="form-section-title"><i class="bi bi-person-badge"></i> Applicant</div>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Select Registered Applicant</label>
                            <select name="applicant_id" class="form-select select2-search" data-placeholder="Search applicant name..." required>
                                <option value=""></option>
                                <?php foreach($allApplicants as $applicant): ?>
                                    <option value="<?php echo $applicant['id']; ?>"><?php echo htmlspecialchars($applicant['last_name'] . ', ' . $applicant['first_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Project Details -->
                <div class="form-section">
                    <div class="form-section-title"><i class="bi bi-building"></i> Project Details</div>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Project Name</label>
                            <input type="text" name="project_name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Project Type</label>
                            <select name="project_type" class="form-select" required>
                                <option value="Residential">Residential</option>
                                <option value="Commercial">Commercial</option>
                                <option value="Industrial">Industrial</option>
                                <option value="Institutional">Institutional</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Project Description</label>
                            <textarea name="project_description" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Location -->
                <div class="form-section">
                    <div class="form-section-title"><i class="bi bi-geo-alt"></i> Location &amp; Parcel</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Barangay</label>
                            <input type="text" name="barangay" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">District</label>
                            <input type="text" name="district" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Street</label>
                            <input type="text" name="street" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Lot Number</label>
                            <input type="text" name="lot_number" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Block Number</label>
                            <input type="text" name="block" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Parcel ID (PIN)</label>
                            <input type="text" name="parcel_id" class="form-control" placeholder="e.g. 123-45-678">
                        </div>

                        <div class="col-md-12 mt-2">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label mb-0">Project Location (Coordinates)</label>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="btn-select-map">
                                    <i class="bi bi-geo-alt me-1"></i> Pick On Map
                                </button>
                            </div>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <input type="number" step="any" name="latitude" id="inp-lat" class="form-control coord-input" placeholder="Latitude" required>
                                </div>
                                <div class="col-md-6">
                                    <input type="number" step="any" name="longitude" id="inp-lng" class="form-control coord-input" placeholder="Longitude" required>
                                </div>
                            </div>
                            <div id="map-container" style="display:none; margin-top:10px;"></div>
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer border-top-0">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-check-lg me-1"></i> Create Application
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    let map;
    let marker;
    const defaultLat = 14.7566;
    const defaultLng = 121.0450;

    /**
     * Function para i-update ang marker at ang inputs
     * @param {number} lat 
     * @param {number} lng 
     * @param {boolean} moveMap - kung ise-center ang mapa (true para sa manual type)
     */
    function updateMarker(lat, lng, moveMap = false) {
        if (!lat || !lng || isNaN(lat) || isNaN(lng)) return;
        
        const pos = [parseFloat(lat), parseFloat(lng)];
        
        if (marker) {
            marker.setLatLng(pos);
        } else if (map) {
            // Gagawa ng draggable marker kung wala pa
            marker = L.marker(pos, {draggable: true}).addTo(map);
            
            // Sync: Kapag d-in-rag ang pin, update ang text inputs
            marker.on('dragend', function() {
                const newPos = marker.getLatLng();
                $('#inp-lat').val(newPos.lat.toFixed(6));
                $('#inp-lng').val(newPos.lng.toFixed(6));
            });
        }

        // Kung galing sa manual typing, dalhin ang view ng mapa sa location
        if (moveMap && map) {
            map.setView(pos, 16);
        }
    }

    $(document).ready(function() {
        // 1. EVENT: Kapag nag-type manual sa Latitude/Longitude fields
        $('#inp-lat, #inp-lng').on('input change', function() {
            const lat = $('#inp-lat').val();
            const lng = $('#inp-lng').val();
            
            // I-update ang marker at i-center ang map
            if(lat && lng) {
                updateMarker(lat, lng, true);
            }
        });

        // 2. EVENT: Toggle Map Container
        $('#btn-select-map').on('click', function() {
            const container = $('#map-container');
            const btn = $(this);
            
            container.slideToggle(400, function() {
                if (container.is(':visible')) {
                    btn.html('<i class="bi bi-map-fill"></i> Hide Map');
                    
                    // Initialize Map kung first time bubuksan
                    if (!map) {
                        map = L.map('map-container').setView([defaultLat, defaultLng], 13);
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '© OpenStreetMap contributors'
                        }).addTo(map);

                        // Sync: Kapag clinick ang MAPA, update inputs at marker
                        map.on('click', function(e) {
                            const lat = e.latlng.lat.toFixed(6);
                            const lng = e.latlng.lng.toFixed(6);
                            
                            $('#inp-lat').val(lat);
                            $('#inp-lng').val(lng);
                            updateMarker(lat, lng);
                        });
                    }
                    
                    // Importante: I-refresh ang size ng Leaflet para hindi putol ang tiles
                    setTimeout(() => { 
                        map.invalidateSize(); 
                        // Kung may laman na ang inputs, ipakita na agad ang pin
                        const existingLat = $('#inp-lat').val();
                        const existingLng = $('#inp-lng').val();
                        if(existingLat && existingLng) updateMarker(existingLat, existingLng, true);
                    }, 200);

                } else {
                    btn.html('<i class="bi bi-map"></i> Select on Map');
                }
            });
        });

        // 3. Auto-format Parcel ID (Optional helper)
        $('#parcel_id').on('input', function() {
            let val = $(this).val().replace(/[^0-9]/g, '');
            // Dito mo pwedeng dagdagan ng auto-dash logic kung gusto mo
        });

        // 4. Live search for "Select Registered Applicant"
        $('.select2-search').select2({
            theme: 'bootstrap-5',
            width: '100%',
            dropdownParent: $('#manualAddModal'),
            placeholder: function () { return $(this).data('placeholder'); },
            allowClear: true
        });

        // FIX: aria-hidden focus warning — use inert attribute to properly manage focus
        const manualAddModal = document.getElementById('manualAddModal');
        if (manualAddModal) {
            // Before Bootstrap sets aria-hidden, blur focused descendants and set inert
            manualAddModal.addEventListener('hide.bs.modal', function () {
                const focused = manualAddModal.querySelector(':focus');
                if (focused) focused.blur();
                manualAddModal.inert = true;
            });

            // Once fully hidden, clean up — Bootstrap will handle aria-hidden
            manualAddModal.addEventListener('hidden.bs.modal', function () {
                manualAddModal.inert = false;
                // Return focus to the trigger button
                const trigger = document.querySelector('[data-bs-target="#manualAddModal"]');
                if (trigger) trigger.focus();
            });

            // Remove inert when opening so the form is fully interactive
            manualAddModal.addEventListener('show.bs.modal', function () {
                manualAddModal.inert = false;
            });
        }
    });

// ── Live Search & Filter ──────────────────────────────────────────────────────
(function () {
    const searchInput  = document.getElementById('appSearchInput');
    const clearBtn     = document.getElementById('appClearSearch');
    const searchIcon   = document.getElementById('appSearchIcon');
    const statusSelect = document.getElementById('appStatusSelect');
    const dateRangeInput = document.getElementById('appDateRangeInput');
    const dateFromHidden = document.getElementById('appDateFrom');
    const dateToHidden   = document.getElementById('appDateTo');
    const clearDateBtn   = document.getElementById('appClearDateRange');
    const sortSelect    = document.getElementById('appSortSelect');
    const form          = document.getElementById('filterForm');
    if (!searchInput || !form) return;

    let debounceTimer = null;

    function submitForm() {
        form.submit();
    }

    // Live search with 500ms debounce
    searchInput.addEventListener('input', function () {
        clearBtn.classList.toggle('d-none', this.value === '');
        searchIcon.className = 'bi bi-arrow-clockwise text-muted';
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () {
            searchIcon.className = 'bi bi-search text-muted';
            submitForm();
        }, 500);
    });

    // Enter key submits immediately
    searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(debounceTimer);
            searchIcon.className = 'bi bi-search text-muted';
            submitForm();
        }
        if (e.key === 'Escape') {
            searchInput.value = '';
            clearBtn.classList.add('d-none');
            clearTimeout(debounceTimer);
            submitForm();
        }
    });

    // Clear button
    clearBtn.addEventListener('click', function () {
        searchInput.value = '';
        clearBtn.classList.add('d-none');
        clearTimeout(debounceTimer);
        submitForm();
    });

    // Status dropdown — submit immediately on change
    statusSelect.addEventListener('change', function () {
        clearTimeout(debounceTimer);
        submitForm();
    });

    // Date range picker — single clickable field showing "from - to"
    if (dateRangeInput && window.flatpickr) {
        const initialDates = [];
        if (dateFromHidden.value) initialDates.push(dateFromHidden.value);
        if (dateToHidden.value)   initialDates.push(dateToHidden.value);

        const dateRangePicker = flatpickr(dateRangeInput, {
            mode: 'range',
            dateFormat: 'M j, Y',
            defaultDate: initialDates.length ? initialDates : undefined,
            onClose: function (selectedDates, dateStr, instance) {
                if (selectedDates.length === 2) {
                    dateFromHidden.value = instance.formatDate(selectedDates[0], 'Y-m-d');
                    dateToHidden.value   = instance.formatDate(selectedDates[1], 'Y-m-d');
                    clearDateBtn.classList.remove('d-none');
                    clearTimeout(debounceTimer);
                    submitForm();
                }
            }
        });

        clearDateBtn.addEventListener('click', function () {
            dateRangePicker.clear();
            dateFromHidden.value = '';
            dateToHidden.value = '';
            clearDateBtn.classList.add('d-none');
            clearTimeout(debounceTimer);
            submitForm();
        });
    }

    // Sort — submit immediately on change
    if (sortSelect) {
        sortSelect.addEventListener('change', function () {
            clearTimeout(debounceTimer);
            submitForm();
        });
    }
})();
</script>

<?php include __DIR__ . '/../admin/footer.php'; ?>