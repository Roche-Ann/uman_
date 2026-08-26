<?php
// ── Language / translation setup ─────────────────────────────────────────────
$dashLang = $_SESSION['locale_language'] ?? 'en_PH';

$dashTranslations = [
    'en_PH' => [
        'page_title'            => 'Dashboard',
        'subtitle'              => 'Overview of all system activity and operations.',
        // Stat cards
        'total_applications'    => 'Total Applications',
        'pending_review'        => 'Pending Review',
        'approved'              => 'Approved',
        'rejected'              => 'Rejected',
        // Inspector banner
        'inspector_welcome'     => 'Welcome, Inspector! Check your assigned applications below.',
        // Performance metrics
        'avg_processing'        => 'Avg. Processing Time',
        'days'                  => 'Days',
        'top_barangay'          => 'Top Performing Barangay',
        'upcoming_inspections'  => 'Upcoming Inspections',
        // Overdue alert
        'priority_action'       => 'Priority Action Required',
        'overdue_desc'          => 'There are <strong>%d</strong> applications pending for more than 3 days.',
        'review_overdue'        => 'Review Overdue',
        // Recent Applications table
        'recent_apps'           => 'Recent Applications',
        'search_placeholder'    => 'Search ID, Project, or Applicant...',
        'all_status'            => 'All Status',
        'reset'                 => 'Reset',
        'col_app_number'        => 'APPLICATION #',
        'col_project'           => 'PROJECT NAME',
        'col_applicant'         => 'APPLICANT',
        'col_status'            => 'STATUS',
        'col_date'              => 'DATE',
        'col_action'            => 'ACTION',
        'no_applications'       => 'No applications found.',
        'view'                  => 'View',
        // Charts
        'chart_status'          => 'Application Status',
        'chart_categories'      => 'Project Categories',
        'chart_barangay'        => 'Applications per Barangay',
        'chart_trend'           => 'Monthly Growth Trend',
        'chart_total'           => 'Total',
        'chart_applications'    => 'Applications',
    ],
    'fil' => [
        'page_title'            => 'Dashboard',
        'subtitle'              => 'Pangkalahatang-tanaw ng lahat ng aktibidad at operasyon ng sistema.',
        // Stat cards
        'total_applications'    => 'Kabuuang Aplikasyon',
        'pending_review'        => 'Naghihintay ng Pagsusuri',
        'approved'              => 'Naaprubahan',
        'rejected'              => 'Tinanggihan',
        // Inspector banner
        'inspector_welcome'     => 'Maligayang pagdating, Inspektor! Tingnan ang iyong mga itinalagang aplikasyon sa ibaba.',
        // Performance metrics
        'avg_processing'        => 'Avg. Oras ng Pagproseso',
        'days'                  => 'Araw',
        'top_barangay'          => 'Nangungunang Barangay',
        'upcoming_inspections'  => 'Paparating na Inspeksyon',
        // Overdue alert
        'priority_action'       => 'Kailangan ng Agarang Aksyon',
        'overdue_desc'          => 'Mayroong <strong>%d</strong> na aplikasyon na naghihintay nang mahigit 3 araw.',
        'review_overdue'        => 'Suriin ang Nalalaon',
        // Recent Applications table
        'recent_apps'           => 'Mga Kamakailang Aplikasyon',
        'search_placeholder'    => 'Maghanap ng ID, Proyekto, o Aplikante...',
        'all_status'            => 'Lahat ng Status',
        'reset'                 => 'I-reset',
        'col_app_number'        => 'NUMERO NG APLIKASYON',
        'col_project'           => 'PANGALAN NG PROYEKTO',
        'col_applicant'         => 'APLIKANTE',
        'col_status'            => 'STATUS',
        'col_date'              => 'PETSA',
        'col_action'            => 'AKSYON',
        'no_applications'       => 'Walang nahanap na aplikasyon.',
        'view'                  => 'Tingnan',
        // Charts
        'chart_status'          => 'Status ng Aplikasyon',
        'chart_categories'      => 'Mga Kategorya ng Proyekto',
        'chart_barangay'        => 'Mga Aplikasyon bawat Barangay',
        'chart_trend'           => 'Buwanang Trend ng Paglago',
        'chart_total'           => 'Kabuuan',
        'chart_applications'    => 'Mga Aplikasyon',
    ],
];

function dt(string $key, array $translations, string $lang): string {
    return $translations[$lang][$key] ?? $translations['en_PH'][$key] ?? $key;
}

$pageTitle = dt('page_title', $dashTranslations, $dashLang);

// ── Role-based dashboard heading ─────────────────────────────────────────────
$dashRoleLabels = [
    'admin'         => 'Admin Dashboard',
    'inspector'     => 'Inspector Dashboard',
    'zoning'        => 'Zoning Dashboard',
    'zoning_officer'=> 'Zoning Officer Dashboard',
    'staff'         => 'Staff Dashboard',
];
$dashCurrentRole = $_SESSION['role'] ?? '';
$dashRoleHeading = $dashRoleLabels[$dashCurrentRole]
    ?? (ucwords(str_replace('_', ' ', $dashCurrentRole)) . ' Dashboard');
if ($dashCurrentRole === '') {
    $dashRoleHeading = 'Dashboard';
}

// Load locale settings for date/time display
$localeSettings   = $db->fetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('locale_time_format', 'locale_timezone', 'locale_date_format')");
$localeMap        = array_column($localeSettings, 'setting_value', 'setting_key');
$dashTimeFormat   = $localeMap['locale_time_format'] ?? '12h';
$dashTimezone     = $localeMap['locale_timezone']    ?? 'Asia/Manila';
$dashDateFormat   = $localeMap['locale_date_format'] ?? 'F j, Y';

// Normalise date format to a PHP date() token
$phpDateFormat = match($dashDateFormat) {
    'M/D/YYYY'  => 'm/d/Y',
    'D/M/YYYY'  => 'd/m/Y',
    'YYYY-MM-DD'=> 'Y-m-d',
    default     => 'F j, Y',  // "Month D, YYYY"
};

// 1. PERFORMANCE METRICS (NEW)
// Average Processing Time (Days from creation to decision)
$avgTimeResult = $db->fetchOne("SELECT ROUND(AVG(DATEDIFF(updated_at, created_at)), 1) as avg_days 
                                FROM applications 
                                WHERE status IN ('approved', 'rejected')");
$avgProcessingTime = $avgTimeResult['avg_days'] ?? '0';

// Top Performing Barangay (Most applications in total)
$topBrgyResult = $db->fetchOne("SELECT barangay, COUNT(*) as count 
                                FROM applications 
                                GROUP BY barangay 
                                ORDER BY count DESC LIMIT 1");
$topBarangay = $topBrgyResult['barangay'] ?? 'No Data';

// Upcoming Inspections (Next 7 days)
$upcomingInspections = $db->fetchOne("SELECT COUNT(*) as total FROM inspections 
                                      WHERE scheduled_at >= CURDATE() 
                                      AND scheduled_at <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                                      AND status = 'scheduled'");
$inspectionCount = $upcomingInspections['total'] ?? 0;

// 2. STATUS CARDS DATA
$overdueResult = $db->fetchOne("SELECT COUNT(*) as total FROM applications 
                                WHERE (status = 'submitted' OR status = 'Pending') 
                                AND created_at <= DATE_SUB(NOW(), INTERVAL 3 DAY)");
$overdueCount = $overdueResult['total'] ?? 0;

$stats = $db->fetchOne("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'submitted' OR status = 'Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review,
    SUM(CASE WHEN status = 'for_revision' THEN 1 ELSE 0 END) as for_revision
    FROM applications");

$dashboardData['stats'] = [
    'total_applications' => $stats['total'] ?? 0,
    'pending_review' => $stats['pending'] ?? 0,
    'approved' => $stats['approved'] ?? 0,
    'rejected' => $stats['rejected'] ?? 0
];

// 3. CHART DATA FETCHING
$statusLabels = ['Approved', 'Pending', 'Under Review', 'Revision', 'Rejected'];
$statusCounts = [
    $stats['approved'] ?? 0, 
    $stats['pending'] ?? 0, 
    $stats['under_review'] ?? 0, 
    $stats['for_revision'] ?? 0, 
    $stats['rejected'] ?? 0
];

$landUseData = $db->fetchAll("SELECT project_type, COUNT(*) as total FROM applications WHERE project_type IS NOT NULL GROUP BY project_type");
$landLabels = array_column($landUseData, 'project_type');
$landCounts = array_column($landUseData, 'total');

$barangayData = $db->fetchAll("SELECT barangay, COUNT(*) as total FROM applications GROUP BY barangay ORDER BY total DESC LIMIT 10");
$brgyLabels = array_column($barangayData, 'barangay');
$brgyCounts = array_column($barangayData, 'total');

$monthlyData = $db->fetchAll("SELECT MONTHNAME(created_at) as month, COUNT(*) as total FROM applications WHERE YEAR(created_at) = YEAR(CURDATE()) GROUP BY MONTH(created_at) ORDER BY MONTH(created_at)");
$monthLabels = array_column($monthlyData, 'month');
$monthCounts = array_column($monthlyData, 'total');

// 4. RECENT APPLICATIONS & FILTERS
$filters = ['status' => $_GET['status'] ?? '', 'search' => $_GET['search'] ?? ''];

if ($_SESSION['role'] === 'inspector') {
    // Para kay Inspector: Kunin lang ang mga applications na naka-assign sa kanya sa 'inspections' table
    $sql = "SELECT a.*, u.first_name, u.last_name 
            FROM applications a 
            LEFT JOIN users u ON a.applicant_id = u.id 
            JOIN inspections i ON a.id = i.application_id 
            WHERE i.inspector_id = ? 
            ORDER BY a.created_at DESC LIMIT 10";
    $recentApps = $db->fetchAll($sql, [$_SESSION['user_id']]);

    // Recent inspection schedules
    $recentInspections = $db->fetchAll("
        SELECT i.*, a.application_number, a.project_name, a.barangay
        FROM inspections i
        JOIN applications a ON i.application_id = a.id
        WHERE i.inspector_id = ?
        ORDER BY i.scheduled_at DESC LIMIT 10", [$_SESSION['user_id']]);
} else {
    // Para sa Admin/Zoning/Staff: Ito ang original logic mo
    $sql = "SELECT a.*, u.first_name, u.last_name FROM applications a LEFT JOIN users u ON a.applicant_id = u.id WHERE 1=1";
    $params = [];
    if (!empty($filters['status'])) { $sql .= " AND a.status = ?"; $params[] = $filters['status']; }
    if (!empty($filters['search'])) {
        $sql .= " AND (a.project_name LIKE ? OR a.application_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
        $val = "%".$filters['search']."%"; $params = array_merge($params, [$val, $val, $val, $val]);
    }
    $sql .= " ORDER BY a.created_at DESC LIMIT 10";
    $recentApps = $db->fetchAll($sql, $params);
}

?>

<style>
    /* ── BASE STYLES ── */
    .overdue-alert { background: linear-gradient(135deg, #fff5f5 0%, #ffffff 100%); }
    .chart-card { border-radius: 15px; border: none; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); }
    .chart-container { position: relative; height: 280px; width: 100%; }
    .metric-card { border-radius: 15px; transition: transform 0.2s, box-shadow 0.2s; }
    .metric-card:hover { transform: translateY(-5px); box-shadow: 0 8px 24px rgba(0,0,0,0.18) !important; }

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

    /* Red gradient variant (e.g. Review Overdue) */
    .btn-view-gradient-red {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        border: none;
        color: #fff;
        border-radius: 8px;
        font-weight: 600;
        box-shadow: 0 3px 8px rgba(220, 38, 38, 0.3);
        transition: transform 0.12s ease, box-shadow 0.12s ease, color 0.12s ease;
    }
    .btn-view-gradient-red:hover,
    .btn-view-gradient-red:focus,
    .btn-view-gradient-red:active {
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 5px 12px rgba(220, 38, 38, 0.4);
    }

    /* ================================================
       MOBILE RESPONSIVE
       1024px (Laptop) | 768px (Tablet) | 480px (Large Mobile) | 320px (Small Mobile)
       ================================================ */

    /* --- 1024px: Laptop --- */
    @media (max-width: 1024px) {
        .p-4 { padding: 1.25rem !important; }
        .metric-card .card-body h2 { font-size: 1.65rem; }

        /* Total Applications / Pending Review / Approved / Rejected cards:
           at this width the sidebar can be either expanded (~250px) or
           collapsed to icons (~80px), so each card's actual width swings
           by ~40px depending purely on sidebar state — with the default
           label size, that's enough to flip "Total Applications" between
           1 and 2 lines, making the whole card row (and everything below
           it) jump every time the sidebar is toggled. Shrink the label
           enough that it reliably fits on one line either way, and
           reserve height for 2 lines regardless so a translation that
           still wraps doesn't reintroduce the jump. */
        .metric-card .card-body h6 {
            font-size: 0.78rem;
            min-height: 2.1em;
            display: flex;
            align-items: center;
        }
        .metric-card .card-body .bi { font-size: 1.2rem !important; }

        /* Avg. Processing Time / Top Performing Barangay / Upcoming
           Inspections cards: at this width (minus the docked sidebar)
           each of the 3 columns is only ~200px, so labels like "Top
           Performing Barangay" wrap to 3 lines. align-items-center then
           centers the icon against that whole 3-line block, making it
           look like it's floating disconnected from the text. Anchor
           the icon to the top instead, and shrink it + the label so two
           lines is the worst case rather than three. */
        .stat-icon-card-body {
            align-items: flex-start !important;
        }
        .stat-icon-card-body .rounded-circle {
            padding: 0.6rem !important;
            flex-shrink: 0;
        }
        .stat-icon-card-body .bi { font-size: 1.1rem !important; }
        .stat-icon-card-body h6 { font-size: 0.72rem; margin-bottom: 0.25rem; }
        .stat-icon-card-body h4 { font-size: 1.05rem; }

        .table { font-size: 0.85rem; }
        .table th, .table td { padding: 0.65rem 0.55rem; }

        /* Priority Action Required alert: with the sidebar expanded, the
           icon+text block and the "Review Overdue" button come up just
           short of fitting on one row, so flex-wrap silently drops the
           button onto its own line only in that state — toggling the
           sidebar flips the alert between a clean 1-row layout and a
           2-row one with the button stranded on the left. Trim the icon,
           text, and button just enough that the row reliably fits with
           room to spare either way, so it always looks like the 1-row
           layout regardless of sidebar state. */
        .overdue-alert .rounded-circle {
            padding: 0.65rem !important;
        }
        .overdue-alert .bi.fs-3 {
            font-size: 1.3rem !important;
        }
        .overdue-alert h5 {
            font-size: 0.95rem;
        }
        .overdue-alert p {
            font-size: 0.82rem;
        }
        .overdue-alert .btn {
            font-size: 0.82rem;
            padding: 8px 16px;
        }
    }

    /* --- 768px: Tablet --- */
    @media (max-width: 768px) {

        .p-4 { padding: 1rem !important; }

        /* Page header: stack title block + date badge */
        .d-flex.justify-content-between.align-items-center.mb-4 {
            flex-direction: column;
            align-items: flex-start !important;
            gap: 10px;
        }

        /* Icon + title */
        .d-flex.justify-content-between.align-items-center.mb-4 h2 { font-size: 1.3rem; }
        .d-flex.justify-content-between.align-items-center.mb-4 h2 span.rounded-circle {
            width: 36px !important; height: 36px !important;
        }
        .d-flex.justify-content-between.align-items-center.mb-4 h2 i { font-size: 1.1rem !important; }
        .d-flex.justify-content-between.align-items-center.mb-4 p {
            font-size: 0.8rem;
        }

        /* Date/clock badge */
        .d-flex.justify-content-between.align-items-center.mb-4 .badge {
            font-size: 0.72rem;
            padding: 6px 10px !important;
        }

        /* Stat cards: 2×2 grid */
        .col-md-3 { width: 50%; flex: 0 0 50%; }
        .row.g-4 { --bs-gutter-x: 0.6rem; --bs-gutter-y: 0.6rem; }

        /* Metric card text */
        .metric-card .card-body h2 { font-size: 1.5rem; }
        .metric-card .card-body h6 { font-size: 0.78rem; }
        .metric-card .card-body .bi { font-size: 1.2rem !important; }

        /* Performance metric cards: 1 per row */
        .col-md-4 { width: 100%; flex: 0 0 100%; }

        /* Performance row cards */
        .stat-icon-card-body h4 { font-size: 1rem; }
        .stat-icon-card-body h6 { font-size: 0.75rem; }
        .stat-icon-card-body .rounded-circle { padding: 0.6rem !important; }
        .stat-icon-card-body .bi { font-size: 1.1rem !important; }

        /* Overdue alert */
        .overdue-alert .card-body { padding: 1rem !important; }
        .overdue-alert h5 { font-size: 0.95rem; }
        .overdue-alert p { font-size: 0.8rem; }
        .overdue-alert .btn { font-size: 0.8rem; padding: 6px 12px; }

        /* Recent Applications card: stack search controls */
        .card-header .row.g-2 { flex-wrap: wrap; }
        .card-header .col-md-4,
        .card-header .col-md-3 { width: 100%; flex: 0 0 100%; }
        .card-header h5 { font-size: 0.95rem; margin-bottom: 8px; }
        .card-header .input-group-sm .form-control { font-size: 0.8rem; }
        .card-header .form-select-sm { font-size: 0.8rem; }

        /* Bootstrap's .input-group wraps by default, which can push a
           trailing element onto its own line once its column narrows —
           keep the search icon + input on one row. */
        .input-group { flex-wrap: nowrap; }

        /* Table */
        .table { font-size: 0.8rem; }
        .table th, .table td { padding: 0.5rem 0.4rem; }
        .table thead th:nth-child(3),
        .table tbody td:nth-child(3) { display: none; } /* hide Applicant col */
        .table .btn-sm { font-size: 0.75rem; padding: 4px 10px; }

        /* Guard header/cell text (e.g. "SCHEDULED DATE") from breaking
           mid-word once columns get tight — let table-responsive's
           horizontal scroll take over instead. */
        .table th, .table td { white-space: nowrap; }
        .table-responsive .table { min-width: 560px; }

        /* Chart cards: 1 per row */
        .col-md-6 { width: 100%; flex: 0 0 100%; }
        .chart-container { height: 220px; }
        .chart-card h5 { font-size: 0.95rem; margin-bottom: 0.75rem !important; }
        .chart-card.p-4 { padding: 1rem !important; }
    }

    /* --- 480px: Large Mobile --- */
    @media (max-width: 480px) {

        .p-4 { padding: 0.75rem !important; }

        /* Header icon + title */
        .d-flex.justify-content-between.align-items-center.mb-4 h2 { font-size: 1.05rem; }
        .d-flex.justify-content-between.align-items-center.mb-4 h2 span.rounded-circle {
            width: 32px !important; height: 32px !important;
        }
        .d-flex.justify-content-between.align-items-center.mb-4 h2 i { font-size: 0.95rem !important; }
        .d-flex.justify-content-between.align-items-center.mb-4 p {
            font-size: 0.75rem;
        }

        /* Date badge: stretch full width */
        .d-flex.justify-content-between.align-items-center.mb-4 .badge {
            font-size: 0.68rem;
            padding: 5px 8px !important;
            width: 100%;
            justify-content: center;
        }

        /* Stat cards: 2×2, tighter gap */
        .col-md-3 { width: 50%; flex: 0 0 50%; }
        .row.g-4 { --bs-gutter-x: 0.4rem; --bs-gutter-y: 0.4rem; }
        .metric-card .card-body { padding: 0.75rem !important; }
        .metric-card .card-body h2 { font-size: 1.25rem; }
        .metric-card .card-body h6 { font-size: 0.72rem; }
        .metric-card .card-body .bi { font-size: 1rem !important; }

        /* Performance cards */
        .stat-icon-card-body { padding: 0.75rem !important; }
        .stat-icon-card-body h4 { font-size: 0.95rem; }
        .stat-icon-card-body h6 { font-size: 0.7rem; }
        .stat-icon-card-body .rounded-circle {
            padding: 0.5rem !important;
            margin-right: 0.6rem !important;
        }

        /* Overdue alert: stack button full-width below text */
        .overdue-alert .card-body.p-4 {
            flex-direction: column !important;
            align-items: flex-start !important;
            padding: 0.75rem !important;
        }
        .overdue-alert h5 { font-size: 0.88rem; }
        .overdue-alert p { font-size: 0.75rem; }
        .overdue-alert .btn { width: 100%; text-align: center; font-size: 0.78rem; }

        /* Recent Applications card header */
        .card-header { padding: 0.75rem !important; }
        .card-header h5 { font-size: 0.88rem; }
        .card-header .input-group-sm .form-control { font-size: 0.75rem; }
        .card-header .form-select-sm { font-size: 0.75rem; }

        /* Table */
        .table { font-size: 0.72rem; }
        .table th, .table td { padding: 0.4rem 0.3rem; white-space: nowrap; }
        .table-responsive .table { min-width: 400px; }
        .table thead th:nth-child(3),
        .table tbody td:nth-child(3),
        .table thead th:nth-child(5),
        .table tbody td:nth-child(5) { display: none; } /* hide Applicant + Date */
        .table .btn-sm { font-size: 0.68rem; padding: 3px 8px; }
        .table .badge { font-size: 0.62rem; padding: 3px 6px; }

        /* Charts */
        .chart-container { height: 190px; }
        .chart-card.p-4 { padding: 0.75rem !important; }
        .chart-card h5 { font-size: 0.88rem; margin-bottom: 0.6rem !important; }

        .mb-4 { margin-bottom: 0.75rem !important; }
    }

    /* --- 320px: Small Mobile --- */
    @media (max-width: 320px) {

        .p-4 { padding: 0.5rem !important; }

        /* Header icon + title */
        .d-flex.justify-content-between.align-items-center.mb-4 h2 { font-size: 0.92rem; }
        .d-flex.justify-content-between.align-items-center.mb-4 h2 span.rounded-circle {
            width: 28px !important; height: 28px !important;
        }
        .d-flex.justify-content-between.align-items-center.mb-4 h2 i { font-size: 0.82rem !important; }
        .d-flex.justify-content-between.align-items-center.mb-4 p {
            font-size: 0.68rem;
        }

        /* Date badge */
        .d-flex.justify-content-between.align-items-center.mb-4 .badge {
            font-size: 0.6rem;
            padding: 4px 6px !important;
        }

        /* Stat cards: 2×2, very tight gap */
        .col-md-3 { width: 50%; flex: 0 0 50%; }
        .row.g-4 { --bs-gutter-x: 0.3rem; --bs-gutter-y: 0.3rem; }
        .metric-card .card-body { padding: 0.6rem !important; }
        .metric-card .card-body h2 { font-size: 1.1rem; }
        .metric-card .card-body h6 { font-size: 0.66rem; }
        .metric-card .card-body .bi { font-size: 0.9rem !important; }

        /* Performance cards */
        .stat-icon-card-body { padding: 0.6rem !important; }
        .stat-icon-card-body h4 { font-size: 0.85rem; }
        .stat-icon-card-body h6 { font-size: 0.65rem; }
        .stat-icon-card-body .rounded-circle {
            padding: 0.4rem !important;
            margin-right: 0.5rem !important;
        }
        .stat-icon-card-body .bi { font-size: 0.9rem !important; }

        /* Overdue alert */
        .overdue-alert .card-body.p-4 {
            flex-direction: column !important;
            align-items: flex-start !important;
            padding: 0.6rem !important;
            gap: 8px !important;
        }
        .overdue-alert h5 { font-size: 0.8rem; }
        .overdue-alert p { font-size: 0.68rem; }
        .overdue-alert .btn { width: 100%; text-align: center; font-size: 0.72rem; padding: 5px 10px; }
        .overdue-alert .rounded-circle { padding: 0.4rem !important; }
        .overdue-alert .bi.fs-3 { font-size: 1rem !important; }

        /* Recent Applications card header */
        .card-header { padding: 0.5rem !important; }
        .card-header h5 { font-size: 0.8rem; }
        .card-header .input-group-sm .form-control { font-size: 0.68rem; padding: 3px 6px; }
        .card-header .form-select-sm { font-size: 0.68rem; padding: 3px 6px; }

        /* Table: keep App# + Status + Action only */
        .table { font-size: 0.65rem; }
        .table th, .table td { padding: 0.3rem 0.2rem; white-space: nowrap; }
        .table-responsive .table { min-width: 300px; }
        .table thead th:nth-child(2),
        .table tbody td:nth-child(2),
        .table thead th:nth-child(3),
        .table tbody td:nth-child(3),
        .table thead th:nth-child(5),
        .table tbody td:nth-child(5) { display: none; }
        .table .btn-sm { font-size: 0.6rem; padding: 2px 6px; }
        .table .badge { font-size: 0.58rem; padding: 2px 5px; }

        /* Charts */
        .chart-container { height: 160px; }
        .chart-card.p-4 { padding: 0.6rem !important; }
        .chart-card h5 { font-size: 0.8rem; margin-bottom: 0.5rem !important; }

        .mb-4 { margin-bottom: 0.6rem !important; }
    }
</style>

<div class="p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0 d-flex align-items-center gap-2" style="color: #1e293b;">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle">
                    <i class="bi bi-speedometer2" style="color:#10b981;font-size:1.9rem;"></i>
                </span>
                <?php echo htmlspecialchars($dashRoleHeading); ?>
            </h2>
            <p class="text-muted mb-0"><?php echo dt('subtitle', $dashTranslations, $dashLang); ?></p>
        </div>
        <div class="badge bg-primary p-2 px-3 d-flex align-items-center gap-2">
            <i class="bi bi-calendar3"></i>
            <span id="dashDate"><?php
                $tz = new DateTimeZone($dashTimezone);
                $now = new DateTime('now', $tz);
                echo $now->format($phpDateFormat);
            ?></span>
            <span class="opacity-50">|</span>
            <i class="bi bi-clock"></i>
            <span id="dashTime"></span>
        </div>
        <script>
        window.DASH_CLOCK_CONFIG = {
            use12h:   <?php echo $dashTimeFormat === '12h' ? 'true' : 'false'; ?>,
            timezone: <?php echo json_encode($dashTimezone); ?>
        };
        </script>
    </div>

    <div class="row mb-4 g-4">
        <?php 
        $cards = [
            [dt('total_applications', $dashTranslations, $dashLang), $dashboardData['stats']['total_applications'], 'bi-file-earmark-text', 'linear-gradient(135deg, #3b82f6 0%, #2563eb 100%)', '/lgu-urban-planning/permit/applications.php'],
            [dt('pending_review',     $dashTranslations, $dashLang), $dashboardData['stats']['pending_review'],     'bi-clock-history',     'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)', '/lgu-urban-planning/permit/applications.php?status=submitted'],
            [dt('approved',           $dashTranslations, $dashLang), $dashboardData['stats']['approved'],           'bi-check-circle',      'linear-gradient(135deg, #10b981 0%, #059669 100%)', '/lgu-urban-planning/permit/applications.php?status=approved'],
            [dt('rejected',           $dashTranslations, $dashLang), $dashboardData['stats']['rejected'],           'bi-x-circle',          'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)', '/lgu-urban-planning/permit/applications.php?status=rejected'],
        ];
        $isInspector = ($_SESSION['role'] === 'inspector');
        foreach($cards as $card): ?>
        <div class="col-md-3">
            <?php if (!$isInspector): ?>
            <a href="<?php echo $card[4]; ?>" class="text-decoration-none">
            <?php endif; ?>
                <div class="card text-white shadow-sm border-0 metric-card" style="background: <?php echo $card[3]; ?>; <?php echo !$isInspector ? 'cursor: pointer;' : ''; ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="text-white-50 mb-2" style="font-weight: 500;"><?php echo $card[0]; ?></h6>
                                <h2 class="text-black mb-0" style="font-weight: 700;"><?php echo $card[1]; ?></h2>
                            </div>
                            <i class="bi <?php echo $card[2]; ?>" style="font-size: 1.5rem;"></i>
                        </div>
                    </div>
                </div>
            <?php if (!$isInspector): ?>
            </a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row mb-4 g-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 bg-white" style="border-radius: 15px; border-left: 5px solid #6366f1;">
                <div class="card-body d-flex align-items-center stat-icon-card-body">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3"><i class="bi bi-speedometer2 text-primary fs-4"></i></div>
                    <div><h6 class="text-muted mb-1"><?php echo dt('avg_processing', $dashTranslations, $dashLang); ?></h6><h4 class="fw-bold mb-0"><?php echo $avgProcessingTime; ?> <?php echo dt('days', $dashTranslations, $dashLang); ?></h4></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 bg-white" style="border-radius: 15px; border-left: 5px solid #8b5cf6;">
                <div class="card-body d-flex align-items-center stat-icon-card-body">
                    <div class="rounded-circle bg-purple bg-opacity-10 p-3 me-3" style="background: rgba(139,92,246,0.1);"><i class="bi bi-trophy text-purple fs-4" style="color:#8b5cf6;"></i></div>
                    <div><h6 class="text-muted mb-1"><?php echo dt('top_barangay', $dashTranslations, $dashLang); ?></h6><h4 class="fw-bold mb-0"><?php echo htmlspecialchars($topBarangay); ?></h4></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 bg-white" style="border-radius: 15px; border-left: 5px solid #ec4899;">
                <div class="card-body d-flex align-items-center stat-icon-card-body">
                    <div class="rounded-circle bg-pink bg-opacity-10 p-3 me-3" style="background: rgba(236,72,153,0.1);"><i class="bi bi-calendar-check text-pink fs-4" style="color:#ec4899;"></i></div>
                    <div><h6 class="text-muted mb-1"><?php echo dt('upcoming_inspections', $dashTranslations, $dashLang); ?></h6><h4 class="fw-bold mb-0"><?php echo $inspectionCount; ?></h4></div>
                </div>
            </div>
        </div>
    </div>

        <?php if ($overdueCount > 0 && $_SESSION['role'] !== 'inspector'): ?>
    <div class="card mb-4 border-0 shadow-sm overdue-alert" style="border-radius: 15px; border-left: 5px solid #ef4444 !important;">
        <div class="card-body p-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center">
                <div class="rounded-circle bg-danger bg-opacity-10 p-3 me-3">
                    <i class="bi bi-exclamation-octagon-fill text-danger fs-3"></i>
                </div>
                <div>
                    <h5 class="mb-1 fw-bold text-danger"><?php echo dt('priority_action', $dashTranslations, $dashLang); ?></h5>
                    <p class="mb-0 alert-text">
                        <?php echo sprintf(dt('overdue_desc', $dashTranslations, $dashLang), $overdueCount); ?>
                    </p>
                </div>
            </div>

            <a href="/lgu-urban-planning/permit/applications.php?status=submitted&filter=overdue" class="btn btn-view-gradient-red px-4">
                <?php echo dt('review_overdue', $dashTranslations, $dashLang); ?> <i class="bi bi-arrow-right ms-2"></i>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($_SESSION['role'] === 'inspector'): ?>
    <!-- ── INSPECTOR: Recent Inspection Schedules ── -->
    <div class="card border-0 shadow-sm mb-4" style="border-radius: 15px;">
        <div class="card-header py-3 border-0 custom-card-header">
            <div class="row g-2 align-items-center">
                <div class="col">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-clipboard2-pulse me-2 text-primary"></i>My Inspection Schedules</h5>
                </div>
                <div class="col-auto">
                    <a href="/lgu-urban-planning/monitoring/index.php" class="btn btn-sm btn-outline-primary px-3">View All</a>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-muted">
                    <tr style="font-size: 0.8rem;">
                        <th class="ps-4">APPLICATION #</th>
                        <th>PROJECT NAME</th>
                        <th>BARANGAY</th>
                        <th>STATUS</th>
                        <th>SCHEDULED DATE</th>
                        <th class="text-end pe-4">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentInspections)): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">No inspection schedules assigned yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentInspections as $ins):
                            $insStatus = strtolower($ins['status']);
                            if ($insStatus === 'scheduled')       { $badgeClass = 'bg-warning text-dark'; }
                            elseif ($insStatus === 'completed')   { $badgeClass = 'bg-success'; }
                            else                                  { $badgeClass = 'bg-secondary'; }
                        ?>
                        <tr>
                            <td class="ps-4 fw-bold text-primary">#<?php echo htmlspecialchars($ins['application_number']); ?></td>
                            <td><?php echo htmlspecialchars($ins['project_name']); ?></td>
                            <td class="text-muted"><?php echo htmlspecialchars($ins['barangay'] ?? '—'); ?></td>
                            <td><span class="badge rounded-pill <?php echo $badgeClass; ?>"><?php echo ucfirst($ins['status']); ?></span></td>
                            <td class="text-muted">
                                <?php echo (!empty($ins['scheduled_at']) && $ins['scheduled_at'] !== '0000-00-00 00:00:00')
                                    ? date('M d, Y h:i A', strtotime($ins['scheduled_at']))
                                    : '<span class="text-danger small">TBD</span>'; ?>
                            </td>
                            <td class="text-end pe-4">
                                <a href="/lgu-urban-planning/monitoring/index.php" class="btn btn-sm btn-outline-primary px-3">Go to Report</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php else: ?>
    <!-- ── STAFF: Recent Applications ── -->
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 15px;">
        <div class="card-header py-3 border-0 custom-card-header">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col">
                    <h5 class="mb-0 fw-bold"><?php echo dt('recent_apps', $dashTranslations, $dashLang); ?></h5>
                </div>
                
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text border-0 custom-input-accent"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control form-control-sm bg-light border-0" 
                            placeholder="<?php echo dt('search_placeholder', $dashTranslations, $dashLang); ?>" 
                            value="<?php echo htmlspecialchars($filters['search']); ?>">
                    </div>
                </div>

                <div class="col-md-3">
                    <select class="form-select form-select-sm bg-light border-0" name="status" onchange="this.form.submit()">
                        <option value=""><?php echo dt('all_status', $dashTranslations, $dashLang); ?></option>
                        <?php 
                        $opts = ['submitted', 'under_review', 'for_revision', 'approved', 'rejected'];
                        foreach($opts as $opt): ?>
                            <option value="<?php echo $opt; ?>" <?php echo $filters['status'] === $opt ? 'selected' : ''; ?>>
                                <?php echo ucfirst(str_replace('_', ' ', $opt)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if($filters['status'] || $filters['search']): ?>
                <div class="col-auto">
                    <a href="index.php" class="btn btn-sm btn-link text-muted text-decoration-none"><?php echo dt('reset', $dashTranslations, $dashLang); ?></a>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-muted">
                    <tr style="font-size: 0.8rem;">
                        <th class="ps-4"><?php echo dt('col_app_number', $dashTranslations, $dashLang); ?></th>
                        <th><?php echo dt('col_project', $dashTranslations, $dashLang); ?></th>
                        <th><?php echo dt('col_applicant', $dashTranslations, $dashLang); ?></th>
                        <th><?php echo dt('col_status', $dashTranslations, $dashLang); ?></th>
                        <th><?php echo dt('col_date', $dashTranslations, $dashLang); ?></th>
                        <th class="text-end pe-4"><?php echo dt('col_action', $dashTranslations, $dashLang); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentApps)): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted"><?php echo dt('no_applications', $dashTranslations, $dashLang); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($recentApps as $app): ?>
                        <tr>
                            <td class="ps-4 fw-bold text-dark">#<?php echo htmlspecialchars($app['application_number']); ?></td>
                            <td><?php echo htmlspecialchars($app['project_name']); ?></td>
                            <td><?php echo htmlspecialchars(($app['first_name'] ?? '') . ' ' . ($app['last_name'] ?? '')); ?></td>
                            <td>
                                <span class="badge rounded-pill bg-<?php echo Helper::getStatusBadge($app['status']); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $app['status'])); ?>
                                </span>
                            </td>
                            <td class="text-muted"><?php echo Helper::formatDate($app['created_at']); ?></td>
                            <td class="text-end pe-4">
                                <a href="/lgu-urban-planning/permit/view.php?id=<?php echo $app['id']; ?>" class="btn btn-sm btn-view-gradient px-3"><?php echo dt('view', $dashTranslations, $dashLang); ?></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="row mb-4 g-4">
        <div class="col-md-6">
            <div class="card chart-card p-4">
                <h5 class="fw-bold mb-4"><?php echo dt('chart_status', $dashTranslations, $dashLang); ?></h5>
                <div class="chart-container"><canvas id="statusPieChart"></canvas></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card chart-card p-4">
                <h5 class="fw-bold mb-4"><?php echo dt('chart_categories', $dashTranslations, $dashLang); ?></h5>
                <div class="chart-container"><canvas id="landUsePieChart"></canvas></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card chart-card p-4">
                <h5 class="fw-bold mb-4"><?php echo dt('chart_barangay', $dashTranslations, $dashLang); ?></h5>
                <div class="chart-container"><canvas id="barangayChart"></canvas></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card chart-card p-4">
                <h5 class="fw-bold mb-4"><?php echo dt('chart_trend', $dashTranslations, $dashLang); ?></h5>
                <div class="chart-container"><canvas id="trendChart"></canvas></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
window.DASH_CHART_DATA = {
    statusLabels:  <?php echo json_encode($statusLabels); ?>,
    statusCounts:  <?php echo json_encode($statusCounts); ?>,
    landLabels:    <?php echo json_encode(!empty($landLabels) ? $landLabels : ['No Data']); ?>,
    landCounts:    <?php echo json_encode(!empty($landCounts) ? $landCounts : [1]); ?>,
    brgyLabels:    <?php echo json_encode($brgyLabels); ?>,
    brgyCounts:    <?php echo json_encode($brgyCounts); ?>,
    brgyLabel:     <?php echo json_encode(dt('chart_total', $dashTranslations, $dashLang)); ?>,
    monthLabels:   <?php echo json_encode($monthLabels); ?>,
    monthCounts:   <?php echo json_encode($monthCounts); ?>,
    monthLabel:    <?php echo json_encode(dt('chart_applications', $dashTranslations, $dashLang)); ?>
};
</script>
<script src="../assets/js/admin-dashboard.js"></script>

</script>