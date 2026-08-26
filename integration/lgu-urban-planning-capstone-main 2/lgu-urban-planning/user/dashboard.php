<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../core/Database.php';

$pageTitle = 'Dashboard';
$isAuthPage = true;
$db = Database::getInstance();

// 1. FRESH DATA FETCH
if (isset($_SESSION['user_id'])) {
    $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    $_SESSION['user_data'] = $user;
}
$user = $_SESSION['user_data'] ?? null;
$userId = $_SESSION['user_id'] ?? 0;

// --- LOAD USER DISPLAY PREFERENCES ---
$userTimeFormat = '12h'; // default
$userDateFormat = 'F d, Y'; // default
if ($userId) {
    $prefRows = $db->fetchAll(
        "SELECT pref_key, pref_value FROM user_preferences WHERE user_id = ?",
        [$userId]
    );
    $prefs = [];
    foreach ($prefRows as $row) {
        $prefs[$row['pref_key']] = $row['pref_value'];
    }
    $userTimeFormat = $prefs['locale_time_format'] ?? '12h';
    $rawDateFormat  = $prefs['locale_date_format'] ?? 'F j, Y';
    // Map settings format tokens to PHP date() tokens
    $dateFormatMap = [
        'M/D/YYYY'   => 'm/d/Y',
        'D/M/YYYY'   => 'd/m/Y',
        'YYYY-MM-DD' => 'Y-m-d',
        'F j, Y'     => 'F j, Y',   // "Month D, YYYY" — matches settings.php option
    ];
    $userDateFormat = $dateFormatMap[$rawDateFormat] ?? 'F j, Y'; // default: Month D, YYYY
}

// --- LANGUAGE ---
$dashLang = $prefs['locale_language'] ?? $_SESSION['locale_language'] ?? 'en_PH';

$dashTranslations = [
    'en_PH' => [
        'title'                => 'My Dashboard',
        'reschedule_alert'     => 'Your reschedule request has been sent to the Admin/Official inbox.',
        'office_status_label'  => 'Office Service Status',
        'status_open'          => 'Open Now (Accepting Applications)',
        'status_closed_hours'  => 'Closed (Outside Office Hours)',
        'status_closed_weekend'=> 'Closed (Weekend)',
        'status_closed_holiday'=> 'Closed (Holiday)',
        'schedule_days'        => 'Mon - Fri',
        'schedule_hours'       => '8:00 AM - 5:00 PM',
        'stat_my_apps'         => 'My Applications',
        'stat_unread_msgs'     => 'Unread Messages',
        'section_inspections'  => 'Site Inspections',
        'inspection_details'   => 'Inspection Details',
        'inspection_hint'      => 'Select a highlighted date to view details.',
        'section_recent_apps'  => 'Recent Applications',
        'col_app_number'       => 'Application #',
        'col_project_name'     => 'Project Name',
        'col_status'           => 'Status',
        'col_action'           => 'Action',
        'no_applications'      => 'No applications found.',
        'btn_view'             => 'View',
        'modal_reschedule'     => 'Request Reschedule',
        'modal_reschedule_for' => 'Requesting reschedule for:',
        'modal_new_date'       => 'Preferred New Date',
        'modal_reason'         => 'Reason for Rescheduling',
        'modal_reason_hint'    => 'State your reason here...',
        'modal_cancel'         => 'Cancel',
        'modal_submit'         => 'Submit Request',
    ],
    'fil' => [
        'title'                => 'Aking Dashboard',
        'reschedule_alert'     => 'Ang iyong kahilingang mag-reschedule ay naipadala na sa Admin/Official inbox.',
        'office_status_label'  => 'Katayuan ng Serbisyo ng Opisina',
        'status_open'          => 'Bukas Ngayon (Tumatanggap ng mga Aplikasyon)',
        'status_closed_hours'  => 'Sarado (Labas ng Oras ng Opisina)',
        'status_closed_weekend'=> 'Sarado (Weekend)',
        'status_closed_holiday'=> 'Sarado (Pista Opisyal)',
        'schedule_days'        => 'Lun - Biy',
        'schedule_hours'       => '8:00 AM - 5:00 PM',
        'stat_my_apps'         => 'Aking mga Aplikasyon',
        'stat_unread_msgs'     => 'Mga Hindi Pa Nababasang Mensahe',
        'section_inspections'  => 'Mga Inspeksyon sa Site',
        'inspection_details'   => 'Detalye ng Inspeksyon',
        'inspection_hint'      => 'Pumili ng naka-highlight na petsa para makita ang mga detalye.',
        'section_recent_apps'  => 'Mga Kamakailang Aplikasyon',
        'col_app_number'       => 'Aplikasyon #',
        'col_project_name'     => 'Pangalan ng Proyekto',
        'col_status'           => 'Katayuan',
        'col_action'           => 'Aksyon',
        'no_applications'      => 'Walang nahanap na aplikasyon.',
        'btn_view'             => 'Tingnan',
        'modal_reschedule'     => 'Humiling ng Muling Iskedyul',
        'modal_reschedule_for' => 'Humihiling ng muling iskedyul para sa:',
        'modal_new_date'       => 'Gustong Bagong Petsa',
        'modal_reason'         => 'Dahilan ng Muling Pag-iskedyul',
        'modal_reason_hint'    => 'Ilagay ang iyong dahilan dito...',
        'modal_cancel'         => 'Kanselahin',
        'modal_submit'         => 'Isumite ang Kahilingan',
    ],
];

function dt(string $key, array $translations, string $lang): string {
    return $translations[$lang][$key] ?? $translations['en_PH'][$key] ?? $key;
}

// --- SERVICE MONITORING LOGIC ---
date_default_timezone_set('Asia/Manila');
$currentDay = date('N'); // 1 (Mon) to 7 (Sun)
$currentDate = date('Y-m-d');
$currentTime = date('H:i');

// Listahan ng Regular Holidays sa Pilipinas (Y-m-d)
$holidays = [
    date('Y') . "-01-01", // New Year's Day
    date('Y') . "-04-09", // Araw ng Kagitingan
    date('Y') . "-05-01", // Labor Day
    date('Y') . "-06-12", // Independence Day
    date('Y') . "-08-25", // National Heroes Day
    date('Y') . "-11-01", // All Saints Day
    date('Y') . "-12-25", // Christmas Day
    date('Y') . "-12-30", // Rizal Day
];

$isWeekend = ($currentDay >= 6); 
$isHoliday = in_array($currentDate, $holidays);
$isOfficeHours = ($currentTime >= '08:00' && $currentTime <= '17:00');

$isOpen = (!$isWeekend && !$isHoliday && $isOfficeHours);

if ($isHoliday) {
    $statusMsg   = dt('status_closed_holiday', $dashTranslations, $dashLang);
    $statusColor = "text-danger";
} elseif ($isWeekend) {
    $statusMsg   = dt('status_closed_weekend', $dashTranslations, $dashLang);
    $statusColor = "text-danger";
} elseif (!$isOfficeHours) {
    $statusMsg   = dt('status_closed_hours', $dashTranslations, $dashLang);
    $statusColor = "text-warning";
} else {
    $statusMsg   = dt('status_open', $dashTranslations, $dashLang);
    $statusColor = "text-success";
}

// 2. FETCH DASHBOARD STATS & INSPECTIONS
try {
    $myApps = $db->fetchAll("SELECT * FROM applications WHERE applicant_id = ? ORDER BY created_at DESC", [$userId]);
    
    $inspections = $db->fetchAll("SELECT i.*, ap.project_name, ap.application_number 
                                   FROM inspections i 
                                   JOIN applications ap ON i.application_id = ap.id 
                                   WHERE ap.applicant_id = ? AND i.status != 'cancelled'
                                   ORDER BY i.scheduled_at ASC", [$userId]);

    $apptDates = [];
    foreach ($inspections as $ins) {
        $dateKey = date('Y-m-d', strtotime($ins['scheduled_at']));
        $apptDates[$dateKey][] = [
            'id' => $ins['id'],
            'project_name' => $ins['project_name'],
            'application_number' => $ins['application_number'],
            'scheduled_at' => $ins['scheduled_at']
        ];
    }
} catch (Exception $e) {
    $myApps = $myApps ?? [];
    $inspections = [];
    $apptDates = [];
}

$unreadMsgs = $db->fetchOne("SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0", [$userId]);

$dashboardData = [
    'my_applications' => $myApps,
    'unread_messages' => $unreadMsgs['count'] ?? 0
];

// 3. CALENDAR CALCULATIONS
$month = date('m');
$year = date('Y');
$firstDayOfMonth = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = date('t', $firstDayOfMonth);
$firstDayOfWeek = date('w', $firstDayOfMonth);
?>

<style>
    /* =============================================
       BASE STYLES
    ============================================= */
    .calendar-table { table-layout: fixed; width: 100%; }
    .calendar-table th { font-size: 0.7rem; color: #adb5bd; text-transform: uppercase; text-align: center; }
    .calendar-day {
        height: 40px; text-align: center; vertical-align: middle;
        cursor: pointer; font-size: 0.85rem; border-radius: 8px; transition: all 0.2s;
    }
    .calendar-day:hover { background-color: #f8f9fa; color: #0d6efd; }
    .calendar-day.has-appt {
        background-color: #0d6efd !important; color: white !important;
        font-weight: bold; border: 2px solid #0056b3;
    }
    .calendar-day.today { border-bottom: 3px solid #dc3545; }

    /* Status Card Animation */
    .status-dot { animation: blink 2s infinite; }
    @keyframes blink { 0% { opacity: 1; } 50% { opacity: 0.4; } 100% { opacity: 1; } }

    /* Dashboard outer padding */
    .dashboard-wrapper { padding: 1.5rem; }

    /* Stat cards icon */
    .stat-card-icon { font-size: 2rem; }

    /* Applications table — prevent text wrapping */
    .table-app thead th,
    .table-app tbody td { white-space: nowrap; }

    /* Scrollable table wrapper (base) */
    .apps-table-wrapper {
        display: block;
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    /* Clickable stat cards */
    .stat-card-clickable {
        transition: transform 0.18s ease, box-shadow 0.18s ease;
        cursor: pointer;
    }
    .stat-card-clickable:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.18) !important;
    }
    .stat-card-clickable:active {
        transform: translateY(-1px);
    }

    /* Alert spacing */
    .alert { border-radius: 0.5rem; }

    /* Appointment detail item card (JS-generated in showApptDetail) */
    .appt-item-card { background-color: #fff; }

    /* =============================================
       DARK MODE
    ============================================= */
    [data-bs-theme="dark"] .bg-light {
        background-color: var(--bs-tertiary-bg) !important;
    }
    [data-bs-theme="dark"] .badge.bg-light.text-dark {
        color: var(--bs-body-color) !important;
        border-color: var(--bs-border-color) !important;
    }
    [data-bs-theme="dark"] .appt-item-card {
        background-color: var(--bs-tertiary-bg);
    }
    [data-bs-theme="dark"] .calendar-table th {
        color: var(--bs-secondary-color);
    }
    [data-bs-theme="dark"] .calendar-day:hover {
        background-color: rgba(255, 255, 255, 0.08);
        color: #6ea8fe;
    }
    [data-bs-theme="dark"] .calendar-day.has-appt {
        border-color: #0a58ca;
    }
    [data-bs-theme="dark"] .calendar-day.today {
        border-bottom-color: #ea868f;
    }
    [data-bs-theme="dark"] .stat-card-clickable:hover {
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5) !important;
    }

    /* =============================================
       1024px — LAPTOP
    ============================================= */
    @media (max-width: 1024px) {
        .dashboard-wrapper { padding: 1.25rem; }

        /* Header */
        .dashboard-header h2 { font-size: 1.55rem; }
        .dashboard-header .badge { font-size: 0.85rem !important; padding: 8px 14px !important; }

        /* Status card */
        .status-card-inner .status-icon-box { padding: 1.1rem !important; }
        .status-card-inner .status-icon-box i { font-size: 1.5rem !important; }
        .status-main-text h6 { font-size: 0.72rem; }
        .status-main-text h5 { font-size: 1.05rem; }

        /* Stat cards */
        .stat-card .card-body { padding: 1.25rem !important; }
        .stat-card h2 { font-size: 1.75rem; }
        .stat-card-icon { font-size: 1.8rem; }

        /* Calendar */
        .calendar-day { height: 37px; font-size: 0.8rem; }

        /* Applications table */
        .table-app th, .table-app td { padding: 0.65rem 0.75rem; font-size: 0.88rem; }

        /* Section headers */
        .section-card-header h5 { font-size: 1.1rem; }
        .section-card-header small { font-size: 0.8rem; }
    }

    /* =============================================
       768px — TABLETS
    ============================================= */
    @media (max-width: 768px) {
        .dashboard-wrapper { padding: 1rem; }

        /* Header: stack title and date badge */
        .dashboard-header {
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 0.5rem;
        }
        .dashboard-header h2 { font-size: 1.4rem; margin-bottom: 0 !important; }
        .dashboard-header .badge {
            font-size: 0.8rem !important;
            padding: 8px 14px !important;
            align-self: flex-start;
        }

        /* Alert */
        .alert { font-size: 0.88rem; padding: 0.65rem 1rem; }

        /* Status card */
        .status-card-inner .status-icon-box { padding: 1rem !important; }
        .status-card-inner .status-icon-box i { font-size: 1.4rem !important; }
        .status-card-body { padding: 0.75rem !important; }
        .status-main-text h6 { font-size: 0.68rem; }
        .status-main-text h5 { font-size: 1rem; }
        .status-schedule-badges { margin-top: 0.5rem; width: 100%; }
        .status-schedule-badges .badge { font-size: 0.75rem; }

        /* Stat cards: side-by-side (Bootstrap col-md-6 already handles this) */
        .stat-card .card-body { padding: 1rem !important; }
        .stat-card h2 { font-size: 1.6rem; }
        .stat-card h6 { font-size: 0.8rem; }
        .stat-card-icon { font-size: 1.5rem; }

        /* Calendar */
        .calendar-day { height: 34px; font-size: 0.78rem; }
        .calendar-table th { font-size: 0.62rem; }
        #appt-detail-panel h6 { font-size: 0.78rem; }

        /* Applications table */
        .apps-table-wrapper {
            display: block !important;
            overflow-x: auto !important;
        }
        .table-app { min-width: 500px; }
        .table-app th, .table-app td { font-size: 0.82rem; padding: 0.5rem 0.6rem; }

        /* Section card headers */
        .section-card-header { padding: 0.65rem 1rem !important; }
        .section-card-header h5 { font-size: 1.05rem; }
        .section-card-header small { font-size: 0.75rem; }

        /* Modal */
        .modal-dialog { margin: 0.5rem auto; max-width: calc(100% - 1rem); }
        .modal-header h5 { font-size: 1rem; }
        .modal-body { padding: 1.25rem !important; }
        .modal-footer .btn { font-size: 0.85rem; }

        /* Row gaps */
        .row.g-4 { --bs-gutter-y: 1rem; }
    }

    /* =============================================
       480px — LARGE MOBILE
    ============================================= */
    @media (max-width: 480px) {
        .dashboard-wrapper { padding: 0.75rem; }

        /* Header */
        .dashboard-header h2 { font-size: 1.2rem; }
        .dashboard-header .badge { font-size: 0.72rem !important; padding: 6px 10px !important; }

        /* Alert */
        .alert { font-size: 0.82rem; padding: 0.6rem 0.85rem; }
        .alert .btn-close { padding: 0.5rem; }

        /* Status card — stack icon above content */
        .status-card-inner {
            flex-direction: column !important;
            align-items: stretch !important;
        }
        .status-card-inner .status-icon-box {
            width: 100% !important;
            padding: 0.6rem 1rem !important;
            display: flex !important;
            align-items: center !important;
            gap: 0.6rem;
            min-height: 48px;
        }
        .status-card-inner .status-icon-box i { font-size: 1.2rem !important; }
        .status-card-body { padding: 0.75rem !important; width: 100%; }
        .status-main-text h6 { font-size: 0.62rem; }
        .status-main-text h5 { font-size: 0.9rem; }
        .status-dot { font-size: 0.6rem !important; }
        .status-schedule-badges { margin-top: 0.4rem; display: flex; flex-wrap: wrap; gap: 0.25rem; }
        .status-schedule-badges .badge { font-size: 0.68rem; }

        /* Stat cards — full-width stacked */
        .stat-cards-row > .col-md-6 {
            flex: 0 0 100% !important;
            max-width: 100% !important;
            width: 100% !important;
        }
        .stat-card .card-body { padding: 1rem !important; }
        .stat-card h2 { font-size: 1.4rem; }
        .stat-card h6 { font-size: 0.75rem; }
        .stat-card-icon { font-size: 1.4rem; }

        /* Calendar card body — prevent column clipping */
        .card-body.p-3 { padding: 0.5rem !important; overflow: hidden; }
        .calendar-table { table-layout: fixed; }
        .calendar-table th {
            font-size: 0.52rem;
            padding: 2px 0;
            letter-spacing: -0.02em;
        }
        .calendar-day { height: 28px; font-size: 0.68rem; border-radius: 5px; padding: 0; }
        #appt-detail-panel { padding: 0.6rem !important; }
        #appt-detail-panel h6 { font-size: 0.72rem; }
        #appt-detail-panel .btn { font-size: 0.72rem; }

        /* Section headers */
        .section-card-header { padding: 0.6rem 0.85rem !important; }
        .section-card-header h5 { font-size: 1rem; }
        .section-card-header small { font-size: 0.72rem; }

        /* Applications table */
        .apps-table-wrapper {
            display: block !important;
            width: 100% !important;
            overflow-x: auto !important;
            -webkit-overflow-scrolling: touch;
        }
        .table-app { min-width: 440px; }
        .table-app th, .table-app td { font-size: 0.78rem; padding: 0.4rem 0.5rem; }
        .table-app .ps-4 { padding-left: 0.6rem !important; }
        .table-app .btn-sm { font-size: 0.72rem; padding: 0.22rem 0.5rem; }

        /* Modal */
        .modal-dialog { margin: 0.25rem auto; max-width: calc(100% - 0.5rem); }
        .modal-header { padding: 0.85rem 1rem !important; }
        .modal-header h5 { font-size: 0.95rem; }
        .modal-body { padding: 1rem !important; }
        .modal-body p { font-size: 0.85rem; }
        .modal-body .form-label { font-size: 0.78rem; }
        .modal-body .form-control { font-size: 0.85rem; }
        .modal-footer { padding: 0.6rem 1rem !important; }
        .modal-footer .btn { font-size: 0.82rem; padding: 0.3rem 0.75rem; }

        /* Row gaps */
        .row.g-4 { --bs-gutter-y: 0.75rem; }
        .mb-4 { margin-bottom: 0.85rem !important; }
    }

    /* =============================================
       320px — SMALL MOBILE
    ============================================= */
    @media (max-width: 320px) {
        .dashboard-wrapper { padding: 0.4rem; }

        /* Header */
        .dashboard-header h2 { font-size: 1rem; }
        .dashboard-header .badge { font-size: 0.62rem !important; padding: 5px 8px !important; }

        /* Alert */
        .alert { font-size: 0.76rem; padding: 0.5rem 0.7rem; }

        /* Status card — inherits column layout from 480px, tighten further */
        .status-card-inner .status-icon-box {
            padding: 0.5rem 0.75rem !important;
            min-height: 40px;
        }
        .status-card-inner .status-icon-box i { font-size: 1rem !important; }
        .status-main-text h6 { font-size: 0.58rem; letter-spacing: 0.01em; }
        .status-main-text h5 { font-size: 0.8rem; }
        .status-schedule-badges .badge { font-size: 0.6rem; margin-bottom: 2px; padding: 3px 6px; }

        /* Stat cards */
        .stat-card .card-body { padding: 0.75rem !important; }
        .stat-card h2 { font-size: 1.2rem; }
        .stat-card h6 { font-size: 0.68rem; }
        .stat-card-icon { font-size: 1.1rem; }

        /* Calendar — all 7 columns must fit at 320px */
        .card-body.p-3 { padding: 0.35rem !important; }
        .calendar-table th {
            font-size: 0.44rem;
            letter-spacing: -0.04em;
            padding: 1px 0;
        }
        .calendar-day {
            height: 24px;
            font-size: 0.56rem;
            border-radius: 3px;
        }
        #appt-detail-panel { padding: 0.5rem !important; }
        #appt-detail-panel h6 { font-size: 0.65rem; }
        #appt-detail-panel .text-primary { font-size: 0.75rem !important; }
        #appt-detail-panel .small { font-size: 0.65rem !important; }
        #appt-detail-panel .btn { font-size: 0.65rem; padding: 0.18rem 0.4rem; }

        /* Section headings */
        .section-card-header { padding: 0.5rem 0.6rem !important; }
        .section-card-header h5 { font-size: 0.85rem; }
        .section-card-header small { font-size: 0.62rem; }

        /* Applications table */
        .apps-table-wrapper {
            display: block !important;
            width: 100% !important;
            overflow-x: auto !important;
            -webkit-overflow-scrolling: touch;
        }
        .table-app { min-width: 360px; }
        .table-app thead th { font-size: 0.62rem; padding: 0.3rem 0.35rem; }
        .table-app tbody td { font-size: 0.62rem; padding: 0.28rem 0.35rem; }
        .table-app .ps-4 { padding-left: 0.4rem !important; }
        .table-app .btn-sm { font-size: 0.58rem; padding: 0.15rem 0.3rem; }
        .table-app .badge { font-size: 0.58rem; padding: 3px 6px; }

        /* Modal */
        .modal-dialog { margin: 0.15rem auto; max-width: calc(100% - 0.3rem); }
        .modal-header { padding: 0.6rem 0.75rem !important; }
        .modal-header h5 { font-size: 0.88rem; }
        .modal-body { padding: 0.75rem !important; }
        .modal-body p { font-size: 0.78rem; }
        .modal-body .form-label { font-size: 0.72rem; }
        .modal-body .form-control { font-size: 0.78rem; padding: 0.3rem 0.5rem; }
        .modal-footer { padding: 0.4rem 0.6rem !important; }
        .modal-footer .btn { font-size: 0.72rem; padding: 0.25rem 0.55rem; }

        /* Tighter gaps */
        .row.g-4 { --bs-gutter-y: 0.5rem; --bs-gutter-x: 0.5rem; }
        .mb-4 { margin-bottom: 0.65rem !important; }
        .g-4 { --bs-gutter-x: 0.5rem; }
    }
</style>

<div class="dashboard-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-4 dashboard-header">
        <h2 class="mb-1" style="font-weight: 700;"><?php echo dt('title', $dashTranslations, $dashLang); ?></h2>
        <div class="badge bg-primary p-2 px-3 d-flex align-items-center gap-2 shadow-sm">
            <i class="bi bi-calendar3"></i>
            <span id="dashDate"><?php
                $tz = new DateTimeZone('Asia/Manila');
                $now = new DateTime('now', $tz);
                echo $now->format($userDateFormat);
            ?></span>
            <span class="opacity-50">|</span>
            <i class="bi bi-clock"></i>
            <span id="live-clock" data-use12h="<?php echo $userTimeFormat === '12h' ? 'true' : 'false'; ?>" data-timezone="Asia/Manila"></span>
        </div>
    </div>

    <?php if (isset($_GET['success']) && $_GET['success'] == 'rescheduled'): ?>
    <div class="alert alert-info border-0 shadow-sm mb-4 alert-dismissible fade show">
        <i class="bi bi-info-circle-fill me-2"></i>
        <?php echo dt('reschedule_alert', $dashTranslations, $dashLang); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

        <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm overflow-hidden">
                <div class="card-body p-0">
                    <div class="d-flex align-items-center status-card-inner">
                        <div class="p-4 <?php echo $isOpen ? 'bg-success' : 'bg-secondary'; ?> text-white status-icon-box">
                            <i class="bi <?php echo $isOpen ? 'bi-door-open-fill' : 'bi-door-closed-fill'; ?>" style="font-size: 1.8rem;"></i>
                        </div>
                        <div class="p-3 flex-grow-1 status-card-body">
                            <div class="d-flex justify-content-between align-items-center flex-wrap">
                                <div class="status-main-text">
                                    <h6 class="mb-1 fw-bold text-muted small text-uppercase"><?php echo dt('office_status_label', $dashTranslations, $dashLang); ?></h6>
                                    <h5 class="mb-0 fw-bold <?php echo $statusColor; ?>">
                                        <i class="bi bi-circle-fill me-2 status-dot" style="font-size: 0.7rem;"></i>
                                        <?php echo $statusMsg; ?>
                                    </h5>
                                </div>
                                <div class="text-md-end mt-2 mt-md-0 status-schedule-badges">
                                    <span class="badge bg-light text-dark border"><?php echo dt('schedule_days', $dashTranslations, $dashLang); ?></span>
                                    <span class="badge bg-light text-dark border"><?php echo dt('schedule_hours', $dashTranslations, $dashLang); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4 g-4 stat-cards-row">
        <div class="col-md-6">
            <a href="/lgu-urban-planning/applicant/applications.php" class="text-decoration-none">
                <div class="card text-white border-0 shadow-sm stat-card stat-card-clickable" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-white-50 mb-2"><?php echo dt('stat_my_apps', $dashTranslations, $dashLang); ?></h6>
                                <h2 class="mb-0 fw-bold"><?php echo count($dashboardData['my_applications']); ?></h2>
                            </div>
                            <i class="bi bi-file-earmark-text opacity-50 stat-card-icon"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-6">
            <a href="/lgu-urban-planning/applicant/messages.php" class="text-decoration-none">
                <div class="card text-white border-0 shadow-sm stat-card stat-card-clickable" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-white-50 mb-2"><?php echo dt('stat_unread_msgs', $dashTranslations, $dashLang); ?></h6>
                                <h2 class="mb-0 fw-bold"><?php echo $dashboardData['unread_messages']; ?></h2>
                            </div>
                            <i class="bi bi-envelope opacity-50 stat-card-icon"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header py-3 border-bottom d-flex justify-content-between align-items-center section-card-header">
                    <h5 class="mb-0 fw-bold"><?php echo dt('section_inspections', $dashTranslations, $dashLang); ?></h5>
                    <small class="fw-bold text-muted"><?php echo date('F Y'); ?></small>
                </div>
                <div class="card-body p-3">
                    <table class="table table-sm table-borderless calendar-table mb-3" id="apptCalendarTable" data-appt-dates="<?php echo htmlspecialchars(json_encode($apptDates ?? [])); ?>">
                        <thead>
                            <tr>
                                <th>Su</th><th>Mo</th><th>Tu</th><th>We</th><th>Th</th><th>Fr</th><th>Sa</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            echo "<tr>";
                            for ($i = 0; $i < $firstDayOfWeek; $i++) echo "<td></td>";

                            for ($day = 1; $day <= $daysInMonth; $day++) {
                                if (($i + $day - 1) % 7 == 0 && $day != 1) echo "</tr><tr>";
                                
                                $dateStr = "$year-$month-" . str_pad($day, 2, "0", STR_PAD_LEFT);
                                $hasAppt = isset($apptDates[$dateStr]) ? 'has-appt' : '';
                                $isToday = ($dateStr == date('Y-m-d')) ? 'today' : '';
                                
                                echo "<td class='calendar-day $hasAppt $isToday' onclick='showApptDetail(\"$dateStr\")'>$day</td>";
                            }
                            echo "</tr>";
                            ?>
                        </tbody>
                    </table>

                    <div id="appt-detail-panel" class="p-3 border rounded-3 bg-light shadow-sm" style="display:none;">
                        <h6 class="fw-bold small mb-2 text-uppercase text-muted border-bottom pb-1"><?php echo dt('inspection_details', $dashTranslations, $dashLang); ?></h6>
                        <div id="appt-info-content"></div>
                    </div>

                    <div id="no-appt-msg" class="text-center py-4">
                        <p class="text-muted small mb-0"><?php echo dt('inspection_hint', $dashTranslations, $dashLang); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header py-3 border-bottom section-card-header">
                    <h5 class="mb-0 fw-bold"><?php echo dt('section_recent_apps', $dashTranslations, $dashLang); ?></h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive apps-table-wrapper">
                        <table class="table table-hover align-middle mb-0 table-app">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4"><?php echo dt('col_app_number', $dashTranslations, $dashLang); ?></th>
                                    <th><?php echo dt('col_project_name', $dashTranslations, $dashLang); ?></th>
                                    <th><?php echo dt('col_status', $dashTranslations, $dashLang); ?></th>
                                    <th class="text-center"><?php echo dt('col_action', $dashTranslations, $dashLang); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($dashboardData['my_applications'])): ?>
                                    <tr><td colspan="4" class="text-center py-4"><?php echo dt('no_applications', $dashTranslations, $dashLang); ?></td></tr>
                                <?php else: ?>
                                    <?php foreach ($dashboardData['my_applications'] as $app): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-primary"><?php echo htmlspecialchars($app['application_number']); ?></td>
                                        <td><?php echo htmlspecialchars($app['project_name']); ?></td>
                                        <td>
                                            <?php 
                                                $statusClass = ($app['status'] == 'approved') ? 'bg-success' : (($app['status'] == 'pending') ? 'bg-warning' : 'bg-primary');
                                            ?>
                                            <span class="badge rounded-pill <?php echo $statusClass; ?>">
                                                <?php echo ucfirst($app['status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <a href="/lgu-urban-planning/applicant/view.php?id=<?php echo $app['id']; ?>" class="btn btn-sm btn-outline-primary px-3"><?php echo dt('btn_view', $dashTranslations, $dashLang); ?></a>                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="rescheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="process_reschedule.php" method="POST" class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold"><?php echo dt('modal_reschedule', $dashTranslations, $dashLang); ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="appointment_id" id="modal_appt_id">
                <p class="mb-3"><?php echo dt('modal_reschedule_for', $dashTranslations, $dashLang); ?> <b id="modal_app_num" class="text-danger"></b></p>
                <div class="mb-3">
                    <label class="form-label fw-bold small"><?php echo dt('modal_new_date', $dashTranslations, $dashLang); ?></label>
                    <input type="date" name="new_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="mb-0">
                    <label class="form-label fw-bold small"><?php echo dt('modal_reason', $dashTranslations, $dashLang); ?></label>
                    <textarea name="reason" class="form-control" rows="3" placeholder="<?php echo dt('modal_reason_hint', $dashTranslations, $dashLang); ?>" required></textarea>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?php echo dt('modal_cancel', $dashTranslations, $dashLang); ?></button>
                <button type="submit" class="btn btn-danger px-4 fw-bold"><?php echo dt('modal_submit', $dashTranslations, $dashLang); ?></button>
            </div>
        </form>
    </div>
</div>

<script src="assets/js/user-dashboard.js"></script>