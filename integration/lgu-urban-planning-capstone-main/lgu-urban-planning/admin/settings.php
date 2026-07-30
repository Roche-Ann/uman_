<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helper.php';
require_once __DIR__ . '/../core/Auth.php';

$auth = new Auth();
$auth->requireLogin();

$_allowedRoles = ['admin', 'super_admin', 'zoning_officer', 'building_official', 'assessor', 'inspector'];
if (!in_array($_SESSION['role'] ?? '', $_allowedRoles)) {
    header('Location: /lgu-urban-planning/admin/index.php');
    exit;
}

// Staff roles: can view settings but have restricted access
$isStaff = !in_array($_SESSION['role'] ?? '', ['admin', 'super_admin']);

$db        = Database::getInstance();
$pdo       = $db->getConnection();
$userId    = $_SESSION['user_id'];
$pageTitle = 'Settings'; // will be overridden below once $lang is ready

// ── Audit log helper ──────────────────────────────────────────────────────────
function logAudit($pdo, $userId, $action, $entityType, $entityId, $details) {
    $ip        = $_SERVER['REMOTE_ADDR']     ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $stmt = $pdo->prepare(
        "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    $stmt->execute([$userId, $action, $entityType, $entityId, $details, $ip, $userAgent]);
}

// ── Settings helper: load all settings into an associative array ──────────────
function loadSettings($db) {
    $rows = $db->fetchAll("SELECT setting_key, setting_value FROM settings");
    $map  = [];
    foreach ($rows as $row) {
        $map[$row['setting_key']] = $row['setting_value'];
    }
    return $map;
}

// ── Settings helper: upsert a single key ─────────────────────────────────────
function saveSetting($pdo, $key, $value) {
    $stmt = $pdo->prepare(
        "INSERT INTO settings (setting_key, setting_value, updated_at)
         VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()"
    );
    $stmt->execute([$key, $value]);
}

// ── Defined permissions list ──────────────────────────────────────────────────
$allPermissions = [
    // ── Admin / System ─────────────────────────────────────────────────────
    'view_all_applications'     => 'View all applications',
    'manage_users'              => 'Manage users',
    'manage_zoning'             => 'Manage zoning',
    'manage_settings'           => 'Manage settings',
    'generate_reports'          => 'Generate reports',

    // ── Audit Logs ──────────────────────────────────────────────────────────
    'view_audit_logs'           => 'View audit logs',
    'export_audit_logs'         => 'Export audit logs',
    'purge_audit_logs'          => 'Purge audit logs',

    // ── Messages ────────────────────────────────────────────────────────────
    'view_messages'             => 'View messages',
    'manage_deletion_requests'  => 'Manage deletion requests',

    // ── Applications ────────────────────────────────────────────────────────
    'review_applications'       => 'Review applications',
    'update_application_status' => 'Update application status',
    'check_zoning_compliance'   => 'Check zoning compliance',
    'view_applications'         => 'View applications',
    'add_remarks'               => 'Add remarks',

    // ── Permits and Inspections ─────────────────────────────────────────────
    'generate_permit'           => 'Generate permit PDF',
    'send_inspection_request'   => 'Send inspection request',
    'submit_inspection_report'  => 'Submit inspection report',

    // ── GIS Map ─────────────────────────────────────────────────────────────
    'view_gis_map'              => 'View GIS map',
    'check_spatial_compliance'  => 'Check spatial compliance',

    // ── Assessor ────────────────────────────────────────────────────────────
    'update_parcel_info'        => 'Update parcel info',

    // ── Applicant ───────────────────────────────────────────────────────────
    'submit_application'        => 'Submit application',
    'view_own_applications'     => 'View own applications',
    'upload_documents'          => 'Upload documents',
];

$allRoles = ['super_admin', 'admin', 'zoning_officer', 'building_official', 'assessor', 'inspector', 'applicant'];

$success = '';
$errors  = [];

// ── Backup export (must run before any output) ────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'backup') {
    // --- Token validation (same one-time token system as audit-logs export) ---
    $submittedToken = $_GET['export_token'] ?? '';
    $sessionToken   = $_SESSION['export_token'] ?? '';
    $tokenExpiry    = $_SESSION['export_token_expires'] ?? '';
    $tokenTable     = $_SESSION['export_token_table'] ?? '';

    $tokenValid = (
        !empty($submittedToken) &&
        !empty($sessionToken) &&
        hash_equals($sessionToken, $submittedToken) &&
        $tokenTable === 'database_backup' &&
        strtotime($tokenExpiry) >= time()
    );

    // Invalidate immediately — one-time use only
    unset($_SESSION['export_token'], $_SESSION['export_token_expires'],
          $_SESSION['export_token_table'], $_SESSION['export_token_type']);

    if (!$tokenValid) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:2rem;text-align:center;">
             <h3>&#128274; Export Denied</h3>
             <p>Invalid or expired export token. Please use the Download button and complete verification.</p>
             <a href="settings.php">Go back</a></div>');
    }

    logAudit($pdo, $userId, 'Settings Update', 'settings', 0,
        'Admin downloaded a full database SQL backup. Reason: ' . htmlspecialchars($_SESSION['export_reason'] ?? 'Not specified'));
    unset($_SESSION['export_reason']);

    $filename = 'lgu_backup_' . date('Y-m-d_His') . '.sql';
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store');
    header('Pragma: no-cache');

    $output = "-- LGU Urban Planning System — Database Backup\n";
    $output .= "-- Generated: " . date('Y-m-d H:i:s') . " (Asia/Manila)\n";
    $output .= "-- Generated by: Admin (User ID {$userId})\n\n";
    $output .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    // Get all tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        $safeTable = "`{$table}`";

        // Table structure
        $createRow = $pdo->query("SHOW CREATE TABLE {$safeTable}")->fetch(PDO::FETCH_NUM);
        $output .= "-- --------------------------------------------------------\n";
        $output .= "-- Table structure for {$safeTable}\n";
        $output .= "-- --------------------------------------------------------\n";
        $output .= "DROP TABLE IF EXISTS {$safeTable};\n";
        $output .= $createRow[1] . ";\n\n";

        // Table data
        $rows = $pdo->query("SELECT * FROM {$safeTable}")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            $output .= "-- Data for table {$safeTable}\n";
            $cols = '`' . implode('`, `', array_keys($rows[0])) . '`';
            foreach ($rows as $row) {
                $vals = array_map(function($v) use ($pdo) {
                    return is_null($v) ? 'NULL' : $pdo->quote($v);
                }, array_values($row));
                $output .= "INSERT INTO {$safeTable} ({$cols}) VALUES (" . implode(', ', $vals) . ");\n";
            }
            $output .= "\n";
        }
    }

    $output .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    echo $output;
    exit;
}

// ── Handle POST actions ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // Staff roles may only POST save_locale
    if ($isStaff && $_POST['action'] !== 'save_locale') {
        header('Location: /lgu-urban-planning/admin/settings.php?error=unauthorized');
        exit;
    }

    // -- Save announcement ----------------------------------------------------
    if ($_POST['action'] === 'save_announcement') {
        $enabled = isset($_POST['announcement_enabled']) ? '1' : '0';
        $type    = in_array($_POST['announcement_type'] ?? '', ['info', 'warning', 'success', 'danger'])
                   ? $_POST['announcement_type'] : 'info';
        $message = trim($_POST['announcement_message'] ?? '');

        if ($enabled === '1' && empty($message)) {
            $errors[] = 'Announcement message cannot be empty when the banner is enabled.';
        }

        if (empty($errors)) {
            // Write to system_settings — the same table header.php reads so the
            // banner appears site-wide (login, register, dashboard, etc.)
            $stmt = $pdo->prepare(
                "INSERT INTO system_settings (setting_key, setting_value, is_active)
                 VALUES ('system_announcement', ?, ?)
                 ON DUPLICATE KEY UPDATE
                     setting_value = VALUES(setting_value),
                     is_active     = VALUES(is_active)"
            );
            $stmt->execute([$message, (int)$enabled]);

            // Store the banner type as its own row so header.php can colour it
            $stmt2 = $pdo->prepare(
                "INSERT INTO system_settings (setting_key, setting_value, is_active)
                 VALUES ('system_announcement_type', ?, 1)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
            );
            $stmt2->execute([$type]);

            logAudit($pdo, $userId, 'Settings Update', 'settings', 0,
                "Announcement banner updated. Enabled: {$enabled}, Type: {$type}.");
            $success = 'Announcement banner settings saved.';
        }
    }

    // -- Save locale ----------------------------------------------------------
    if ($_POST['action'] === 'save_locale') {
        $language    = trim($_POST['locale_language']    ?? 'en_PH');
        $dateFormat  = trim($_POST['locale_date_format'] ?? 'M/D/YYYY');
        $timeFormat  = trim($_POST['locale_time_format'] ?? '12h');
        $timezone    = trim($_POST['locale_timezone']    ?? 'Asia/Manila');

        saveSetting($pdo, 'locale_language',    $language);
        saveSetting($pdo, 'locale_date_format', $dateFormat);
        saveSetting($pdo, 'locale_time_format', $timeFormat);
        saveSetting($pdo, 'locale_timezone',    $timezone);

        logAudit($pdo, $userId, 'Settings Update', 'settings', 0,
            "Locale updated. Language: {$language}, Date: {$dateFormat}, Time: {$timeFormat}, TZ: {$timezone}.");
    $success = 'Language & locale settings saved.' . ($language === 'fil' ? ' / Naitala ang mga setting ng wika at lokal.' : '');
    }

    // -- Save role permissions ------------------------------------------------
    if ($_POST['action'] === 'save_permissions') {
        $submitted = $_POST['permissions'] ?? [];

        foreach ($allRoles as $role) {
            foreach ($allPermissions as $permKey => $permLabel) {
                $allowed = isset($submitted[$role][$permKey]) ? 1 : 0;
                $stmt = $pdo->prepare(
                    "INSERT INTO role_permissions (role, permission, is_allowed)
                     VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed)"
                );
                $stmt->execute([$role, $permKey, $allowed]);
            }
        }

        logAudit($pdo, $userId, 'Settings Update', 'role_permissions', 0,
            'Role permissions matrix updated by administrator.');
        $success = 'Role permissions saved successfully.';
    }
}

// ── Load current values ───────────────────────────────────────────────────────
$settings = loadSettings($db);

// Announcement: read from system_settings (same table header.php uses) so the
// form always shows what is actually live on the site.
$annRows = $pdo->query(
    "SELECT setting_key, setting_value, is_active
     FROM system_settings
     WHERE setting_key IN ('system_announcement', 'system_announcement_type')"
)->fetchAll(PDO::FETCH_ASSOC);
$annMap = [];
foreach ($annRows as $row) { $annMap[$row['setting_key']] = $row; }

$announcementEnabled = ($annMap['system_announcement']['is_active'] ?? 0) ? '1' : '0';
$announcementMessage = $annMap['system_announcement']['setting_value'] ?? '';
$rawType             = $annMap['system_announcement_type']['setting_value'] ?? 'info';
$announcementType    = in_array($rawType, ['info', 'warning', 'success', 'danger'], true) ? $rawType : 'info';

// Locale defaults
$localeLanguage   = $settings['locale_language']    ?? 'en_PH';
$localeDateFormat = $settings['locale_date_format'] ?? 'M/D/YYYY';
$localeTimeFormat = $settings['locale_time_format'] ?? '12h';
$localeTimezone   = $settings['locale_timezone']    ?? 'Asia/Manila';

// ── Store language in session so all pages can access it ─────────────────────
$_SESSION['locale_language'] = $localeLanguage;

// ── Translation strings ───────────────────────────────────────────────────────
$translations = [
    'en_PH' => [
        // Page & breadcrumb
        'page_title'            => 'Settings',
        'breadcrumb_dashboard'  => 'Dashboard',
        'breadcrumb_settings'   => 'Settings',
        // Sidebar
        'sidebar_title'         => 'System Settings',
        'sidebar_sub'           => 'Admin configuration',
        'nav_announcement'      => 'Announcement Banner',
        'nav_locale'            => 'Language & Locale',
        'nav_permissions'       => 'Role Permissions',
        'nav_login_activity'    => 'Login Activity',
        'nav_backup'            => 'Backup & Restore',
        // Announcement tab
        'ann_card_title'        => 'Announcement Banner',
        'ann_card_subtitle'     => 'Show a site-wide notice on the dashboard for all users.',
        'ann_enable_label'      => 'Enable banner',
        'ann_enable_hint'       => 'Display the announcement on the dashboard',
        'ann_type_label'        => 'Banner type',
        'ann_type_hint'         => 'Sets the color and icon style',
        'ann_type_info'         => 'Info (Blue)',
        'ann_type_warning'      => 'Warning (Yellow)',
        'ann_type_success'      => 'Success (Green)',
        'ann_type_danger'       => 'Danger (Red)',
        'ann_preview_label'     => 'Live preview',
        'ann_message_label'     => 'Message',
        'ann_message_hint'      => '/300 characters',
        'ann_fallback'          => 'We are currently performing system updates. You may encounter issues with registration or submissions; please save your drafts and try again later.',
        'save_changes'          => 'Save Changes',
        // Locale tab
        'locale_card_title'     => 'Language & Locale',
        'locale_card_subtitle'  => 'Date, time, and language display preferences for the portal.',
        'locale_lang_label'     => 'Language',
        'locale_lang_hint'      => 'System display language',
        'locale_date_label'     => 'Date format',
        'locale_date_hint'      => 'How dates are displayed across the portal',
        'locale_time_label'     => 'Time format',
        'locale_tz_label'       => 'Timezone',
        'locale_tz_hint'        => 'All timestamps will use this timezone',
        'time_12h'              => '12-hour (AM/PM)',
        'time_24h'              => '24-hour',
        // Permissions tab
        'perm_card_title'       => 'Role Permissions',
        'perm_card_subtitle'    => 'Define which portal features each role can access.',
        'perm_col_label'        => 'Permission',
        'perm_locked_note' => 'Admin and Super Admin permissions are locked and cannot be removed.',
        // Login activity tab
        'login_card_title'      => 'Login Activity',
        'login_card_subtitle'   => 'Recent login sessions for your account.',
        'login_col_status'      => 'Status',
        'login_col_browser'     => 'Browser / Device',
        'login_col_ip'          => 'IP Address',
        'login_col_datetime'    => 'Date & Time',
        'login_empty'           => 'No login records found yet.',
        'login_success'         => 'Success',
        'login_failed'          => 'Failed',
        // Alerts
        'alert_fix'             => 'Please fix the following:',
        // Backup tab
        'backup_card_title'     => 'Backup & Restore',
        'backup_card_subtitle'  => 'Download a full SQL backup of the database for safekeeping.',
        'backup_download_label' => 'Download Database Backup',
        'backup_download_hint'  => 'Generates and downloads a complete .sql file of all tables and data.',
        'backup_btn'            => 'Download SQL Backup',
        'backup_warning'        => 'The backup file contains sensitive data. Store it securely and do not share it.',
    ],
    'fil' => [
        // Page & breadcrumb
        'page_title'            => 'Mga Setting',
        'breadcrumb_dashboard'  => 'Dashboard',
        'breadcrumb_settings'   => 'Mga Setting',
        // Sidebar
        'sidebar_title'         => 'Mga Setting ng Sistema',
        'sidebar_sub'           => 'Konfigurasyon ng Admin',
        'nav_announcement'      => 'Anunsyo',
        'nav_locale'            => 'Wika at Lokal',
        'nav_permissions'       => 'Mga Pahintulot sa Papel',
        'nav_login_activity'    => 'Aktibidad sa Pag-login',
        'nav_backup'            => 'Backup at Ibalik',
        // Announcement tab
        'ann_card_title'        => 'Anunsyo',
        'ann_card_subtitle'     => 'Magpakita ng abiso sa buong site sa dashboard para sa lahat ng mga gumagamit.',
        'ann_enable_label'      => 'I-aktibo ang banner',
        'ann_enable_hint'       => 'Ipakita ang anunsyo sa dashboard',
        'ann_type_label'        => 'Uri ng banner',
        'ann_type_hint'         => 'Nagtatakda ng kulay at estilo ng icon',
        'ann_type_info'         => 'Impormasyon (Asul)',
        'ann_type_warning'      => 'Babala (Dilaw)',
        'ann_type_success'      => 'Tagumpay (Berde)',
        'ann_type_danger'       => 'Panganib (Pula)',
        'ann_preview_label'     => 'Live na preview',
        'ann_message_label'     => 'Mensahe',
        'ann_message_hint'      => '/300 na karakter',
        'ann_fallback'          => 'Kasalukuyan kaming nagsasagawa ng mga update sa sistema. Maaari kang makaranas ng mga isyu sa pagpaparehistro o pagsusumite; mangyaring i-save ang iyong mga draft at subukang muli mamaya.',
        'save_changes'          => 'I-save ang mga Pagbabago',
        // Locale tab
        'locale_card_title'     => 'Wika at Lokal',
        'locale_card_subtitle'  => 'Mga kagustuhan sa pagpapakita ng petsa, oras, at wika para sa portal.',
        'locale_lang_label'     => 'Wika',
        'locale_lang_hint'      => 'Wika ng sistema',
        'locale_date_label'     => 'Format ng petsa',
        'locale_date_hint'      => 'Paano ipinapakita ang mga petsa sa buong portal',
        'locale_time_label'     => 'Format ng oras',
        'locale_tz_label'       => 'Time zone',
        'locale_tz_hint'        => 'Lahat ng mga timestamp ay gagamit ng time zone na ito',
        'time_12h'              => '12-oras (AM/PM)',
        'time_24h'              => '24-oras',
        // Permissions tab
        'perm_card_title'       => 'Mga Pahintulot sa Papel',
        'perm_card_subtitle'    => 'Tukuyin kung aling mga feature ng portal ang maa-access ng bawat papel.',
        'perm_col_label'        => 'Pahintulot',
        'perm_locked_note'      => 'Ang mga pahintulot ng Admin at Super Admin ay naka-lock at hindi maaaring alisin.',
        // Login activity tab
        'login_card_title'      => 'Aktibidad sa Pag-login',
        'login_card_subtitle'   => 'Mga kamakailang session sa pag-login para sa iyong account.',
        'login_col_status'      => 'Katayuan',
        'login_col_browser'     => 'Browser / Device',
        'login_col_ip'          => 'IP Address',
        'login_col_datetime'    => 'Petsa at Oras',
        'login_empty'           => 'Walang mga talaan ng pag-login na nahanap.',
        'login_success'         => 'Matagumpay',
        'login_failed'          => 'Nabigo',
        // Alerts
        'alert_fix'             => 'Pakiayos ang mga sumusunod:',
        // Backup tab
        'backup_card_title'     => 'Backup at Ibalik',
        'backup_card_subtitle'  => 'I-download ang kumpletong SQL backup ng database para sa kaligtasan ng datos.',
        'backup_download_label' => 'I-download ang Backup ng Database',
        'backup_download_hint'  => 'Gumagawa at nagda-download ng kumpletong .sql file ng lahat ng talahanayan at datos.',
        'backup_btn'            => 'I-download ang SQL Backup',
        'backup_warning'        => 'Ang backup file ay naglalaman ng sensitibong datos. Itago ito nang ligtas at huwag ibahagi.',
    ],
];

// Helper: get translated string, fallback to English
function t(string $key, array $translations, string $lang): string {
    return $translations[$lang][$key] ?? $translations['en_PH'][$key] ?? $key;
}

$lang = $localeLanguage;
$pageTitle = t('page_title', $translations, $lang);
$isAuthPage = true;

// Role permissions: build a matrix [role][permission] = 0|1
$permMatrix = [];
foreach ($allRoles as $role) {
    foreach ($allPermissions as $permKey => $permLabel) {
        $permMatrix[$role][$permKey] = 0; // default off
    }
}
$savedPerms = $db->fetchAll("SELECT role, permission, is_allowed FROM role_permissions");
foreach ($savedPerms as $row) {
    if (isset($permMatrix[$row['role']][$row['permission']])) {
        $permMatrix[$row['role']][$row['permission']] = (int)$row['is_allowed'];
    }
}

// Login activity: last 10 login events for the current admin
$loginLogs = $db->fetchAll(
    "SELECT action, details, ip_address, user_agent, created_at
     FROM audit_logs
     WHERE user_id = ? AND action IN ('Login', 'Failed Login', 'Login Success', 'Login Failed')
     ORDER BY created_at DESC
     LIMIT 10",
    [$userId]
);

// Determine which tab to re-open after a POST error
// Staff default to locale tab; admins default to announcement tab
$activeTab = $isStaff ? 'tab-locale' : 'tab-announcement';
if (!empty($errors) && isset($_POST['action'])) {
    $tabMap = [
        'save_announcement' => 'tab-announcement',
        'save_locale'       => 'tab-locale',
        'save_permissions'  => 'tab-permissions',
    ];
    $activeTab = $tabMap[$_POST['action']] ?? ($isStaff ? 'tab-locale' : 'tab-announcement');
}

include __DIR__ . '/header.php';
?>

<div class="page-container settings-page">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="/lgu-urban-planning/admin/index.php" style="color: inherit; text-decoration: none;"><?php echo t('breadcrumb_dashboard', $translations, $lang); ?></a>
            </li>
            <li class="breadcrumb-item active"><?php echo t('breadcrumb_settings', $translations, $lang); ?></li>
        </ol>
    </nav>

    <!-- Alerts -->
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert">
            <i class="bi bi-check-circle-fill"></i>
            <span><?php echo htmlspecialchars($success); ?></span>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <strong><?php echo t('alert_fix', $translations, $lang); ?></strong>
            </div>
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $e): ?>
                    <li><?php echo htmlspecialchars($e); ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="settings-grid">

        <!-- ── LEFT PANEL: Settings nav ─────────────────────────────────── -->
        <aside class="settings-sidebar-card">
            <div class="settings-sidebar-header">
                <div class="settings-sidebar-icon">
                    <i class="bi bi-gear-fill"></i>
                </div>
                <div>
                    <h6 class="settings-sidebar-title"><?php echo t('sidebar_title', $translations, $lang); ?></h6>
                    <p class="settings-sidebar-sub"><?php echo t('sidebar_sub', $translations, $lang); ?></p>
                </div>
            </div>

            <nav class="settings-nav" id="settingsTabs" role="tablist">
                <?php if (!$isStaff): ?>
                <button class="settings-nav-item <?php echo $activeTab === 'tab-announcement' ? 'active' : ''; ?>"
                        data-bs-toggle="tab" data-bs-target="#tab-announcement" type="button" role="tab">
                    <i class="bi bi-megaphone"></i>
                    <span><?php echo t('nav_announcement', $translations, $lang); ?></span>
                </button>
                <?php endif; ?>
                <button class="settings-nav-item <?php echo $activeTab === 'tab-locale' ? 'active' : ''; ?>"
                        data-bs-toggle="tab" data-bs-target="#tab-locale" type="button" role="tab">
                    <i class="bi bi-translate"></i>
                    <span><?php echo t('nav_locale', $translations, $lang); ?></span>
                </button>
                <button class="settings-nav-item <?php echo $activeTab === 'tab-permissions' ? 'active' : ''; ?>"
                        data-bs-toggle="tab" data-bs-target="#tab-permissions" type="button" role="tab">
                    <i class="bi bi-shield-check"></i>
                    <span><?php echo t('nav_permissions', $translations, $lang); ?></span>
                </button>
                <button class="settings-nav-item"
                        data-bs-toggle="tab" data-bs-target="#tab-login-activity" type="button" role="tab">
                    <i class="bi bi-clock-history"></i>
                    <span><?php echo t('nav_login_activity', $translations, $lang); ?></span>
                </button>
                <?php if (!$isStaff): ?>
                <button class="settings-nav-item"
                        data-bs-toggle="tab" data-bs-target="#tab-backup" type="button" role="tab">
                    <i class="bi bi-database-down"></i>
                    <span><?php echo t('nav_backup', $translations, $lang); ?></span>
                </button>
                <?php endif; ?>
            </nav>
        </aside>

        <!-- ── RIGHT PANEL: Tab content ─────────────────────────────────── -->
        <div class="settings-main">
            <div class="tab-content">

                <!-- ── TAB: Announcement Banner ──────────────────────────── -->
                <div class="tab-pane fade <?php echo $activeTab === 'tab-announcement' ? 'show active' : ''; ?>"
                     id="tab-announcement" role="tabpanel">

                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div>
                                <h6 class="settings-card-title"><?php echo t('ann_card_title', $translations, $lang); ?></h6>
                                <p class="settings-card-subtitle">
                                    <?php echo t('ann_card_subtitle', $translations, $lang); ?>
                                </p>
                            </div>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="action" value="save_announcement">

                            <div class="settings-fields">

                                <!-- Enable toggle -->
                                <div class="settings-field-row">
                                    <div class="settings-field-info">
                                        <label class="settings-field-label" for="announcementEnabled">
                                            <?php echo t('ann_enable_label', $translations, $lang); ?>
                                        </label>
                                        <p class="settings-field-hint"><?php echo t('ann_enable_hint', $translations, $lang); ?></p>
                                    </div>
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input settings-toggle" type="checkbox"
                                               id="announcementEnabled" name="announcement_enabled"
                                               <?php echo $announcementEnabled === '1' ? 'checked' : ''; ?>>
                                    </div>
                                </div>

                                <!-- Banner type -->
                                <div class="settings-field-row">
                                    <div class="settings-field-info">
                                        <label class="settings-field-label" for="announcementType">
                                            <?php echo t('ann_type_label', $translations, $lang); ?>
                                        </label>
                                        <p class="settings-field-hint"><?php echo t('ann_type_hint', $translations, $lang); ?></p>
                                    </div>
                                    <select class="form-select settings-select" id="announcementType"
                                            name="announcement_type">
                                        <option value="info"    <?php echo $announcementType === 'info'    ? 'selected' : ''; ?>><?php echo t('ann_type_info', $translations, $lang); ?></option>
                                        <option value="warning" <?php echo $announcementType === 'warning' ? 'selected' : ''; ?>><?php echo t('ann_type_warning', $translations, $lang); ?></option>
                                        <option value="success" <?php echo $announcementType === 'success' ? 'selected' : ''; ?>><?php echo t('ann_type_success', $translations, $lang); ?></option>
                                        <option value="danger"  <?php echo $announcementType === 'danger'  ? 'selected' : ''; ?>><?php echo t('ann_type_danger', $translations, $lang); ?></option>
                                    </select>
                                </div>

                                <div class="settings-field-row settings-field-stack settings-preview-sticky">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <label class="settings-field-label mb-0"><?php echo t('ann_preview_label', $translations, $lang); ?></label>
                                    </div>
                                    <div class="announcement-preview alert alert-<?php echo htmlspecialchars($announcementType); ?> d-flex align-items-center gap-2 mb-0"
                                         id="announcementPreview" role="status" aria-live="polite">
                                        <i class="bi bi-megaphone-fill flex-shrink-0"></i>
                                        <span id="previewText"><?php echo $announcementMessage
                                            ? htmlspecialchars($announcementMessage)
                                            : t('ann_fallback', $translations, $lang); ?></span>
                                    </div>
                                </div>

                                <!-- Message -->
                                <div class="settings-field-row settings-field-stack">
                                    <label class="settings-field-label" for="announcementMessage"><?php echo t('ann_message_label', $translations, $lang); ?></label>
                                    <textarea class="form-control settings-textarea" id="announcementMessage"
                                              name="announcement_message" rows="3"
                                              placeholder="e.g. The UPAD office will be closed on December 25 in observance of Christmas Day."
                                              oninput="updatePreview(this.value)"><?php echo htmlspecialchars($announcementMessage); ?></textarea>
                                    <small class="text-muted">
                                        <span id="charCount"><?php echo strlen($announcementMessage); ?></span><?php echo t('ann_message_hint', $translations, $lang); ?>
                                    </small>
                                </div>
                            </div>

                            <div class="settings-card-footer">
                                <button type="submit" class="btn btn-save">
                                    <i class="bi bi-check-lg"></i> <?php echo t('save_changes', $translations, $lang); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div><!-- /#tab-announcement -->

                <!-- ── TAB: Language & Locale ────────────────────────────── -->
                <div class="tab-pane fade <?php echo $activeTab === 'tab-locale' ? 'show active' : ''; ?>"
                     id="tab-locale" role="tabpanel">

                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div>
                                <h6 class="settings-card-title"><?php echo t('locale_card_title', $translations, $lang); ?></h6>
                                <p class="settings-card-subtitle">
                                    <?php echo t('locale_card_subtitle', $translations, $lang); ?>
                                </p>
                            </div>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="action" value="save_locale">

                            <div class="settings-fields">

                                <div class="settings-field-row">
                                    <div class="settings-field-info">
                                        <label class="settings-field-label" for="localeLanguage"><?php echo t('locale_lang_label', $translations, $lang); ?></label>
                                        <p class="settings-field-hint"><?php echo t('locale_lang_hint', $translations, $lang); ?></p>
                                    </div>
                                    <select class="form-select settings-select" id="localeLanguage"
                                            name="locale_language">
                                        <option value="en_PH" <?php echo $localeLanguage === 'en_PH' ? 'selected' : ''; ?>>English (Philippines)</option>
                                        <option value="fil"   <?php echo $localeLanguage === 'fil'   ? 'selected' : ''; ?>>Filipino</option>
                                    </select>
                                </div>

                                <div class="settings-field-row">
                                    <div class="settings-field-info">
                                        <label class="settings-field-label" for="localeDateFormat"><?php echo t('locale_date_label', $translations, $lang); ?></label>
                                        <p class="settings-field-hint"><?php echo t('locale_date_hint', $translations, $lang); ?></p>
                                    </div>
                                    <select class="form-select settings-select" id="localeDateFormat"
                                            name="locale_date_format">
                                        <option value="M/D/YYYY"  <?php echo $localeDateFormat === 'M/D/YYYY'  ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                                        <option value="D/M/YYYY"  <?php echo $localeDateFormat === 'D/M/YYYY'  ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                                        <option value="YYYY-MM-DD" <?php echo $localeDateFormat === 'YYYY-MM-DD' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                                        <option value="F j, Y"    <?php echo $localeDateFormat === 'F j, Y'    ? 'selected' : ''; ?>>Month D, YYYY (e.g. December 25, 2025)</option>
                                    </select>
                                </div>

                                <div class="settings-field-row">
                                    <div class="settings-field-info">
                                        <label class="settings-field-label" for="localeTimeFormat"><?php echo t('locale_time_label', $translations, $lang); ?></label>
                                    </div>
                                    <select class="form-select settings-select" id="localeTimeFormat"
                                            name="locale_time_format">
                                        <option value="12h" <?php echo $localeTimeFormat === '12h' ? 'selected' : ''; ?>><?php echo t('time_12h', $translations, $lang); ?></option>
                                        <option value="24h" <?php echo $localeTimeFormat === '24h' ? 'selected' : ''; ?>><?php echo t('time_24h', $translations, $lang); ?></option>
                                    </select>
                                </div>

                                <div class="settings-field-row">
                                    <div class="settings-field-info">
                                        <label class="settings-field-label" for="localeTimezone"><?php echo t('locale_tz_label', $translations, $lang); ?></label>
                                        <p class="settings-field-hint"><?php echo t('locale_tz_hint', $translations, $lang); ?></p>
                                    </div>
                                    <select class="form-select settings-select" id="localeTimezone"
                                            name="locale_timezone">
                                        <option value="Asia/Manila" <?php echo $localeTimezone === 'Asia/Manila' ? 'selected' : ''; ?>>Asia/Manila (UTC+8)</option>
                                    </select>
                                </div>

                            </div>

                            <div class="settings-card-footer">
                                <button type="submit" class="btn btn-save">
                                    <i class="bi bi-check-lg"></i> <?php echo t('save_changes', $translations, $lang); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div><!-- /#tab-locale -->

                <!-- ── TAB: Role Permissions ─────────────────────────────── -->
                <div class="tab-pane fade <?php echo $activeTab === 'tab-permissions' ? 'show active' : ''; ?>"
                     id="tab-permissions" role="tabpanel">

                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div>
                                <h6 class="settings-card-title"><?php echo t('perm_card_title', $translations, $lang); ?></h6>
                                <p class="settings-card-subtitle">
                                    <?php echo t('perm_card_subtitle', $translations, $lang); ?>
                                </p>
                            </div>
                        </div>

                        <?php if ($isStaff): ?>
                        <div class="d-flex align-items-center gap-2 mx-4 mb-3 px-3 py-2 rounded border border-secondary-subtle bg-secondary-subtle small text-secondary">
                            <i class="bi bi-eye flex-shrink-0"></i>
                            <span>You are viewing role permissions in read-only mode. Only administrators can make changes.</span>
                        </div>
                        <?php endif; ?>

                        <form method="POST">
                            <input type="hidden" name="action" value="save_permissions">

                            <div class="table-responsive">
                                <table class="table settings-perm-table align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th class="perm-col-label"><?php echo t('perm_col_label', $translations, $lang); ?></th>
                                            <?php
                                            $roleLabels = [
                                                'super_admin'       => 'Super Admin',
                                                'admin'             => 'Admin',
                                                'zoning_officer'    => 'Zoning Officer',
                                                'building_official' => 'Building Official',
                                                'assessor'          => 'Assessor',
                                                'inspector'         => 'Inspector',
                                                'applicant'         => 'Applicant',
                                            ];
                                            foreach ($allRoles as $role): ?>
                                            <th class="perm-col-role text-center">
                                                <span class="role-badge role-<?php echo $role; ?>">
                                                    <?php echo $roleLabels[$role] ?? ucfirst($role); ?>
                                                </span>
                                            </th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($allPermissions as $permKey => $permLabel): ?>
                                        <tr>
                                            <td class="perm-label-cell"><?php echo htmlspecialchars($permLabel); ?></td>
                                            <?php foreach ($allRoles as $role): ?>
                                            <td class="text-center">
                                                <?php
                                                // Admin always has all permissions — lock it
                                                $isAdmin   = ($role === 'admin' || $role === 'super_admin');
                                                $isChecked = $isAdmin ? true : (bool)($permMatrix[$role][$permKey] ?? 0);
                                                // Staff: all checkboxes are view-only (disabled)
                                                $isDisabled = $isAdmin || $isStaff;
                                                ?>
                                                <input type="checkbox"
                                                       class="form-check-input settings-perm-check"
                                                       name="permissions[<?php echo $role; ?>][<?php echo $permKey; ?>]"
                                                       <?php echo $isChecked  ? 'checked'  : ''; ?>
                                                       <?php echo $isDisabled ? 'disabled' : ''; ?>>
                                                <?php if ($isAdmin): ?>
                                                    <!-- Submit admin perms as always-on even though input is disabled -->
                                                    <input type="hidden"
                                                           name="permissions[admin][<?php echo $permKey; ?>]"
                                                           value="1">
                                                <?php endif; ?>
                                            </td>
                                            <?php endforeach; ?>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="settings-card-footer">
                                <small class="text-muted me-auto">
                                    <i class="bi bi-lock-fill text-secondary"></i>
                                    <?php echo t('perm_locked_note', $translations, $lang); ?>
                                </small>
                                <?php if (!$isStaff): ?>
                                <button type="submit" class="btn btn-save">
                                    <i class="bi bi-check-lg"></i> <?php echo t('save_changes', $translations, $lang); ?>
                                </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div><!-- /#tab-permissions -->

                <!-- ── TAB: Login Activity ───────────────────────────────── -->
                <div class="tab-pane fade" id="tab-login-activity" role="tabpanel">

                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div>
                                <h6 class="settings-card-title"><?php echo t('login_card_title', $translations, $lang); ?></h6>
                                <p class="settings-card-subtitle">
                                    <?php echo t('login_card_subtitle', $translations, $lang); ?>
                                </p>
                            </div>
                        </div>

                        <?php if (empty($loginLogs)): ?>
                            <div class="settings-empty-state">
                                <i class="bi bi-clock-history"></i>
                                <p><?php echo t('login_empty', $translations, $lang); ?></p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table settings-activity-table align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th><?php echo t('login_col_status', $translations, $lang); ?></th>
                                            <th><?php echo t('login_col_browser', $translations, $lang); ?></th>
                                            <th><?php echo t('login_col_ip', $translations, $lang); ?></th>
                                            <th><?php echo t('login_col_datetime', $translations, $lang); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($loginLogs as $log):
                                            $isFailed  = stripos($log['action'], 'fail') !== false
                                                      || stripos($log['action'], 'failed') !== false;
                                            $badgeClass = $isFailed ? 'badge-login-fail' : 'badge-login-success';
                                            $badgeText  = $isFailed ? t('login_failed', $translations, $lang) : t('login_success', $translations, $lang);

                                            // Parse browser from user-agent string
                                            $ua = $log['user_agent'] ?? '';
                                            if (stripos($ua, 'Firefox') !== false)       $browser = 'Firefox';
                                            elseif (stripos($ua, 'Edg') !== false)       $browser = 'Edge';
                                            elseif (stripos($ua, 'Chrome') !== false)    $browser = 'Chrome';
                                            elseif (stripos($ua, 'Safari') !== false)    $browser = 'Safari';
                                            else $browser = 'Unknown browser';

                                            if (stripos($ua, 'Windows') !== false)       $os = 'Windows';
                                            elseif (stripos($ua, 'Mac') !== false)       $os = 'macOS';
                                            elseif (stripos($ua, 'Linux') !== false)     $os = 'Linux';
                                            elseif (stripos($ua, 'Android') !== false)   $os = 'Android';
                                            elseif (stripos($ua, 'iPhone') !== false)    $os = 'iOS';
                                            else $os = 'Unknown OS';
                                        ?>
                                        <tr>
                                            <td>
                                                <span class="badge <?php echo $badgeClass; ?>">
                                                    <?php echo $badgeText; ?>
                                                </span>
                                            </td>
                                            <td class="activity-browser">
                                                <i class="bi bi-browser-<?php echo strtolower($browser); ?> me-1"></i>
                                                <?php echo htmlspecialchars("{$browser} · {$os}"); ?>
                                            </td>
                                            <td>
                                                <code class="activity-ip">
                                                    <?php echo htmlspecialchars($log['ip_address']); ?>
                                                </code>
                                            </td>
                                            <td class="activity-time text-muted">
                                                <?php echo date('M j, Y · g:i A', strtotime($log['created_at'])); ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div><!-- /#tab-login-activity -->

                <!-- ── TAB: Backup & Restore ─────────────────────────────── -->
                <div class="tab-pane fade" id="tab-backup" role="tabpanel">

                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div>
                                <h6 class="settings-card-title"><?php echo t('backup_card_title', $translations, $lang); ?></h6>
                                <p class="settings-card-subtitle"><?php echo t('backup_card_subtitle', $translations, $lang); ?></p>
                            </div>
                        </div>

                        <div class="settings-fields">
                            <div class="settings-field-row">
                                <div class="settings-field-info">
                                    <label class="settings-field-label"><?php echo t('backup_download_label', $translations, $lang); ?></label>
                                    <p class="settings-field-hint"><?php echo t('backup_download_hint', $translations, $lang); ?></p>
                                </div>
                                <form method="POST" style="display:none;">
                                    <input type="hidden" name="action" value="export_backup">
                                </form>
                                <button type="button" class="btn btn-save" onclick="openBackupExportModal()">
                                    <i class="bi bi-download"></i> <?php echo t('backup_btn', $translations, $lang); ?>
                                </button>
                            </div>

                            <div class="settings-field-row settings-field-stack">
                                <div class="alert alert-warning d-flex align-items-center gap-2 mb-0 py-2" style="font-size:0.85rem;">
                                    <i class="bi bi-shield-exclamation flex-shrink-0"></i>
                                    <span><?php echo t('backup_warning', $translations, $lang); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div><!-- /#tab-backup -->

            </div><!-- /.tab-content -->
        </div><!-- /.settings-main -->

    </div><!-- /.settings-grid -->
</div><!-- /.settings-page -->

<!-- ===== TOAST ===== -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="settingsToast" class="toast align-items-center border-0 shadow" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body d-flex align-items-center gap-2">
                <i class="bi" id="settingsToastIcon" style="font-size:1.1rem;flex-shrink:0;"></i>
                <span id="settingsToastMsg"></span>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<!-- ===== BACKUP EXPORT VERIFICATION MODAL ===== -->
<div class="modal fade" id="backupVerifyModal" tabindex="-1" aria-labelledby="backupVerifyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header text-white" style="background-color:#1e3a6e;">
                <h5 class="modal-title" id="backupVerifyModalLabel">
                    <i class="bi bi-shield-lock-fill me-2"></i>Secure Backup Verification
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:0.5rem 0.75rem;"
                     class="d-flex align-items-center gap-2 small mb-4">
                    <i class="bi bi-exclamation-triangle-fill fs-5 text-warning flex-shrink-0"></i>
                    <span>You are about to download the <strong>full database</strong>, including all sensitive records. Please confirm your identity to proceed.</span>
                </div>

                <div id="backupVerifyAlert" class="alert small py-2 mb-3" style="display:none;"></div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">Purpose of Download <span class="text-danger">*</span></label>
                    <select id="backupReason" class="form-select">
                        <option value="">— Select a reason —</option>
                        <option value="Routine Backup">Routine Backup</option>
                        <option value="Before System Update">Before System Update</option>
                        <option value="Disaster Recovery">Disaster Recovery</option>
                        <option value="Data Migration">Data Migration</option>
                        <option value="Compliance / Audit">Compliance / Audit</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="mb-1">
                    <label class="form-label small fw-bold">Admin Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" id="backupPassword" class="form-control"
                               placeholder="Re-enter your account password">
                        <span class="input-group-text bg-white" style="cursor:pointer;"
                              onclick="toggleBackupPasswordVisibility()">
                            <i class="bi bi-eye-slash" id="backupEyeIcon"></i>
                        </span>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn text-white px-4" id="backupVerifyBtn"
                        style="background-color:#1e3a6e;">
                    <span id="backupBtnSpinner" class="spinner-border spinner-border-sm me-1 d-none"></span>
                    <i class="bi bi-download me-1" id="backupBtnIcon"></i> Verify & Download
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── Styles ──────────────────────────────────────────────────────────────── -->
<style>
/* ── Layout ── */
.settings-page { padding: 1.5rem 2rem; }
.settings-grid {
    display: grid;
    grid-template-columns: 240px 1fr;
    gap: 1.5rem;
    align-items: start;
}
@media (max-width: 820px) {
    .settings-grid { grid-template-columns: 1fr; }

    /* Remove overflow:hidden — it clips the horizontal scroll nav */
    .settings-sidebar-card { overflow: visible; position: static; }

    /* Convert sidebar nav to horizontal scrollable pill row */
    .settings-nav {
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        overflow-x: auto !important;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        padding: 0.4rem 0.5rem;
        gap: 4px;
    }
    .settings-nav::-webkit-scrollbar { display: none; }
    .settings-nav-item {
        flex-shrink: 0;
        width: auto;
        padding: 0.45rem 0.75rem;
        font-size: 0.78rem;
        white-space: nowrap;
        border-radius: 20px;
    }
}

/* ================================================
   MOBILE RESPONSIVE
   768px (Tablet) | 480px (Large Mobile) | 320px (Small Mobile)
   ================================================ */

/* --- 768px: Tablet --- */
@media (max-width: 768px) {

    .settings-page { padding: 1rem; }
    .settings-grid { gap: 1rem; }

    /* Sidebar header */
    .settings-sidebar-header { padding: 0.75rem 1rem; }
    .settings-sidebar-icon { width: 34px; height: 34px; font-size: 0.95rem; }
    .settings-sidebar-title { font-size: 0.82rem; }
    .settings-sidebar-sub { font-size: 0.68rem; }

    /* Content cards */
    .settings-card-header { padding: 0.85rem 1.1rem; flex-wrap: wrap; gap: 6px; }
    .settings-card-title { font-size: 0.88rem; }
    .settings-card-subtitle { font-size: 0.75rem; }
    .settings-card-footer { padding: 0.75rem 1.1rem; flex-wrap: wrap; gap: 6px; }

    /* Fields */
    .settings-fields { padding: 0.25rem 1.1rem; }
    .settings-field-row { flex-wrap: wrap; gap: 0.5rem; padding: 0.75rem 0; }
    .settings-field-label { font-size: 0.82rem; }
    .settings-field-hint { font-size: 0.72rem; }
    .settings-select { width: 100%; }

    /* Permissions table */
    .settings-perm-table { font-size: 0.78rem; }
    .settings-perm-table thead th { padding: 0.6rem 0.5rem; font-size: 0.7rem; }
    .settings-perm-table tbody td { padding: 0.5rem 0.5rem; }
    .perm-col-label { padding-left: 0.75rem !important; }
    .perm-label-cell { padding-left: 0.75rem !important; }
    #tab-permissions .settings-card-header { flex-direction: column; align-items: flex-start !important; }
    #tab-permissions .settings-card-header .btn { width: 100%; justify-content: center; }

    /* Activity table */
    .settings-activity-table { font-size: 0.78rem; }
    .settings-activity-table thead th { padding: 0.6rem 0.75rem; font-size: 0.7rem; }
    .settings-activity-table tbody td { padding: 0.6rem 0.75rem; }

    /* Backup buttons */
    #tab-backup .d-flex.gap-3 { flex-wrap: wrap; gap: 8px !important; }
    #tab-backup .d-flex.gap-3 .btn { flex: 1; min-width: 140px; }

    /* Modals */
    .modal-body { padding: 1rem !important; }
}

/* --- 480px: Large Mobile --- */
@media (max-width: 480px) {

    .settings-page { padding: 0.75rem; }

    /* Sidebar */
    .settings-sidebar-header { padding: 0.65rem 0.85rem; gap: 8px; }
    .settings-sidebar-icon { width: 30px; height: 30px; font-size: 0.85rem; }
    .settings-sidebar-title { font-size: 0.78rem; }
    .settings-sidebar-sub { display: none; }
    .settings-nav { padding: 0.3rem 0.4rem; gap: 3px; }
    .settings-nav-item { font-size: 0.72rem; padding: 0.4rem 0.65rem; }

    /* Content cards */
    .settings-card-header { padding: 0.75rem 0.9rem; }
    .settings-card-title { font-size: 0.82rem; }
    .settings-card-subtitle { font-size: 0.7rem; }
    .settings-card-footer { padding: 0.6rem 0.9rem; }
    .settings-fields { padding: 0.25rem 0.9rem; }
    .settings-field-row { padding: 0.65rem 0; gap: 0.4rem; }
    .settings-field-label { font-size: 0.78rem; }
    .settings-field-hint { font-size: 0.68rem; }

    /* Form controls */
    .form-control, .form-select, .settings-textarea { font-size: 0.82rem; padding: 6px 9px; }
    .settings-select { font-size: 0.82rem; }
    .settings-toggle { width: 2.2em; height: 1.2em; }

    /* Announcement preview */
    .announcement-preview { font-size: 0.78rem; }
    .settings-preview-sticky { padding-bottom: 0.65rem; }

    /* Permissions table — make scrollable */
    .settings-perm-table { font-size: 0.72rem; }
    .settings-perm-table thead th { padding: 0.5rem 0.35rem; font-size: 0.65rem; letter-spacing: 0; }
    .settings-perm-table tbody td { padding: 0.45rem 0.35rem; }
    .settings-perm-check { width: 0.95em; height: 0.95em; }
    #tab-permissions .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }

    /* Role badges */
    .role-badge { font-size: 0.62rem; padding: 2px 7px; }

    /* Activity table */
    .settings-activity-table { font-size: 0.72rem; }
    .settings-activity-table thead th { padding: 0.5rem 0.6rem; font-size: 0.65rem; }
    .settings-activity-table tbody td { padding: 0.5rem 0.6rem; }
    .activity-ip { font-size: 0.7rem; padding: 1px 6px; }
    .activity-time { font-size: 0.72rem; }

    /* Backup */
    #tab-backup .d-flex.gap-3 .btn { font-size: 0.8rem; padding: 7px 12px; }

    /* Save button */
    .btn-save { font-size: 0.8rem; padding: 0.4rem 1rem; }

    /* Modals */
    .modal-header { padding: 0.7rem 0.9rem !important; }
    .modal-title { font-size: 0.9rem; }
    .modal-body { padding: 0.75rem !important; }
    .modal-footer { padding: 0.6rem 0.75rem; }
    .modal-footer .btn { font-size: 0.8rem; padding: 6px 12px; }
}

/* --- 320px: Small Mobile --- */
@media (max-width: 320px) {

    .settings-page { padding: 0.5rem; }

    /* Sidebar */
    .settings-sidebar-header { padding: 0.5rem 0.7rem; gap: 6px; }
    .settings-sidebar-icon { width: 26px; height: 26px; font-size: 0.78rem; }
    .settings-sidebar-title { font-size: 0.72rem; }
    .settings-nav { padding: 0.25rem; gap: 2px; }
    .settings-nav-item { font-size: 0.65rem; padding: 0.35rem 0.55rem; }
    .settings-nav-item i { font-size: 0.78rem; }

    /* Content cards */
    .settings-card-header { padding: 0.6rem 0.75rem; }
    .settings-card-title { font-size: 0.78rem; }
    .settings-card-subtitle { font-size: 0.65rem; }
    .settings-card-footer {
        padding: 0.5rem 0.75rem;
        flex-direction: row;
        justify-content: stretch;
        gap: 6px;
    }
    .settings-card-footer .btn-save,
    .settings-card-footer .btn { flex: 1; text-align: center; font-size: 0.72rem; padding: 6px 8px; }
    .settings-fields { padding: 0.2rem 0.75rem; }
    .settings-field-row { padding: 0.55rem 0; gap: 0.35rem; }
    .settings-field-label { font-size: 0.72rem; }
    .settings-field-hint { font-size: 0.62rem; }

    /* Form controls */
    .form-control, .form-select, .settings-textarea { font-size: 0.78rem; padding: 5px 8px; }
    .settings-select { font-size: 0.78rem; }

    /* Field rows: stack label above control, full width */
    .settings-field-row {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 0.35rem;
        padding: 0.55rem 0;
    }
    .settings-field-info { width: 100%; }
    .settings-select,
    .settings-toggle ~ *,
    .settings-field-row .form-select,
    .settings-field-row .form-control {
        width: 100% !important;
        font-size: 0.78rem;
    }
    /* Toggle stays inline beside label */
    .settings-field-row .settings-toggle { align-self: flex-start; }

    /* Announcement preview */
    .announcement-preview { font-size: 0.72rem; }

    /* Permissions table */
    .settings-perm-table { font-size: 0.65rem; }
    .settings-perm-table thead th { padding: 0.4rem 0.25rem; font-size: 0.58rem; }
    .settings-perm-table tbody td { padding: 0.4rem 0.25rem; }
    .perm-col-label { padding-left: 0.5rem !important; min-width: 110px; }
    .perm-label-cell { padding-left: 0.5rem !important; font-size: 0.65rem; }
    .settings-perm-check { width: 0.88em; height: 0.88em; }
    .role-badge { font-size: 0.58rem; padding: 2px 5px; }

    /* Activity table */
    .settings-activity-table { font-size: 0.65rem; }
    .settings-activity-table thead th { padding: 0.4rem 0.5rem; font-size: 0.58rem; }
    .settings-activity-table tbody td { padding: 0.4rem 0.5rem; }
    .activity-ip { font-size: 0.62rem; padding: 1px 5px; }
    .activity-time { font-size: 0.65rem; }

    /* Backup: stack buttons full width */
    #tab-backup .d-flex.gap-3 { flex-direction: column !important; gap: 6px !important; }
    #tab-backup .d-flex.gap-3 .btn { width: 100%; font-size: 0.75rem; padding: 7px 10px; }

    /* Save button */
    .btn-save { font-size: 0.72rem; padding: 0.35rem 0.85rem; }

    /* Empty state */
    .settings-empty-state { padding: 1.5rem 0.5rem; }
    .settings-empty-state i { font-size: 1.75rem; }
    .settings-empty-state p { font-size: 0.75rem; }

    /* Modals */
    .modal-dialog { margin: 0.25rem; }
    .modal-header { padding: 0.55rem 0.7rem !important; }
    .modal-title { font-size: 0.82rem; }
    .modal-body { padding: 0.6rem !important; }
    .modal-footer {
        padding: 0.5rem 0.6rem;
        display: flex !important;
        flex-direction: row !important;
        justify-content: stretch;
        gap: 6px;
    }
    .modal-footer .btn { flex: 1; text-align: center; font-size: 0.72rem; padding: 5px 8px; }
}

/* ── Left sidebar ── */
.settings-sidebar-card {
    background: #fff;
    border: 1px solid #e5e9f0;
    border-radius: 14px;
    overflow: hidden;
    position: sticky;
    top: 1rem;
}
.settings-sidebar-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 1.1rem 1.25rem;
    border-bottom: 1px solid #f0f3f8;
}
.settings-sidebar-icon {
    width: 40px;
    height: 40px;
    background: #e8edf7;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #1e3a6e;
    font-size: 1.1rem;
    flex-shrink: 0;
}
.settings-sidebar-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: #1a1a2e;
    margin: 0;
}
.settings-sidebar-sub {
    font-size: 0.75rem;
    color: #6b7280;
    margin: 0;
}
.settings-nav {
    padding: 0.5rem;
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.settings-nav-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0.6rem 0.85rem;
    border-radius: 8px;
    border: none;
    background: transparent;
    color: #4b5563;
    font-size: 0.85rem;
    font-weight: 500;
    text-align: left;
    cursor: pointer;
    width: 100%;
    transition: background 0.15s, color 0.15s;
}
.settings-nav-item i { font-size: 0.95rem; flex-shrink: 0; }
.settings-nav-item:hover { background: #f3f6fb; color: #1e3a6e; }
.settings-nav-item.active { background: #e8edf7; color: #1e3a6e; font-weight: 600; }

/* ── Right content cards ── */
.settings-main { min-width: 0; }
.settings-card {
    background: #fff;
    border: 1px solid #e5e9f0;
    border-radius: 14px;
    overflow: hidden;
}
.settings-card-header {
    padding: 1.1rem 1.5rem;
    border-bottom: 1px solid #f0f3f8;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
}
.settings-card-title {
    font-size: 0.95rem;
    font-weight: 600;
    color: #1a1a2e;
    margin: 0;
}
.settings-card-subtitle {
    font-size: 0.8rem;
    color: #6b7280;
    margin: 2px 0 0;
}
.settings-card-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid #f0f3f8;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.75rem;
}

/* ── Settings fields ── */
.settings-fields { padding: 0.5rem 1.5rem; }
.settings-field-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.85rem 0;
    border-bottom: 1px solid #f5f7fb;
}
.settings-field-row:last-child { border-bottom: none; }
.settings-field-stack {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
}
.settings-field-info { flex: 1; min-width: 0; }
.settings-field-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #374151;
    margin: 0;
    display: block;
}
.settings-field-hint {
    font-size: 0.78rem;
    color: #9ca3af;
    margin: 2px 0 0;
}
.settings-select {
    width: 200px;
    font-size: 0.85rem;
    flex-shrink: 0;
}
.settings-textarea {
    font-size: 0.85rem;
    width: 100%;
    resize: vertical;
}
.settings-toggle { width: 2.5em; height: 1.3em; cursor: pointer; }

/* ── Announcement preview ── */
.announcement-preview { font-size: 0.85rem; width: 100%; }

/* Keeps the live-preview row pinned while the user scrolls / types */
.settings-preview-sticky {
    position: sticky;
    top: 0;
    z-index: 20;
    background: #fff;
    border-radius: 6px;
    /* override the row's bottom border so it stays clean when stuck */
    border-bottom: none !important;
    padding-bottom: 0.85rem;
    margin-bottom: -1px;         /* close the gap left by removing border */
}
[data-bs-theme="dark"] .settings-preview-sticky { background: #1e293b; }

/* ── Permissions table ── */
.settings-perm-table { font-size: 0.85rem; }
.settings-perm-table thead th {
    font-size: 0.78rem;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    background: #f9fafb;
    border-bottom: 1px solid #e5e9f0;
    padding: 0.75rem 1rem;
}
.perm-col-label { width: 50%; padding-left: 1.5rem !important; }
.perm-col-role  { width: 16.6%; }
.perm-label-cell { padding-left: 1.5rem !important; color: #374151; font-weight: 500; }
.settings-perm-table tbody tr:hover td { background: #f9fafb; }
.settings-perm-table tbody td { padding: 0.65rem 1rem; border-color: #f0f3f8; }
.settings-perm-check { width: 1.05em; height: 1.05em; cursor: pointer; }
.settings-perm-check:checked { background-color: #1e3a6e; border-color: #1e3a6e; }
.settings-perm-check:disabled { opacity: 0.6; cursor: not-allowed; }

/* ── Role badges ── */
.role-badge {
    font-size: 0.72rem;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 20px;
    letter-spacing: 0.3px;
}
.role-admin             { background: #dbeafe; color: #1d4ed8; }
.role-zoning_officer    { background: #d1fae5; color: #065f46; }
.role-building_official { background: #fef3c7; color: #92400e; }
.role-assessor          { background: #fee2e2; color: #991b1b; }
.role-applicant         { background: #ede9fe; color: #6d28d9; }

/* ── Login activity table ── */
.settings-activity-table { font-size: 0.85rem; }
.settings-activity-table thead th {
    font-size: 0.78rem;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    background: #f9fafb;
    border-bottom: 1px solid #e5e9f0;
    padding: 0.75rem 1.5rem;
}
.settings-activity-table tbody td { padding: 0.75rem 1.5rem; border-color: #f0f3f8; }
.settings-activity-table tbody tr:hover td { background: #f9fafb; }
.badge-login-success { background: #d1fae5; color: #065f46; font-size: 0.75rem; font-weight: 600; }
.badge-login-fail    { background: #fee2e2; color: #991b1b; font-size: 0.75rem; font-weight: 600; }
.activity-ip   { background: #f3f4f6; color: #374151; padding: 2px 8px; border-radius: 5px; font-size: 0.8rem; }
.activity-time { font-size: 0.82rem; }

/* ── Empty state ── */
.settings-empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: #9ca3af;
}
.settings-empty-state i { font-size: 2.5rem; display: block; margin-bottom: 0.75rem; }
.settings-empty-state p { font-size: 0.9rem; margin: 0; }

/* ── Reuse profile.php button styles ── */
.btn-save {
    background: #1e3a6e;
    color: #fff;
    border: none;
    padding: 0.45rem 1.2rem;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: background 0.15s;
}
.btn-save:hover { background: #16305c; color: #fff; }

/* ── Dark mode ── */
[data-bs-theme="dark"] .settings-sidebar-card,
[data-bs-theme="dark"] .settings-card { background: #1e293b; border-color: #334155; }
[data-bs-theme="dark"] .settings-sidebar-header,
[data-bs-theme="dark"] .settings-card-header,
[data-bs-theme="dark"] .settings-card-footer { border-color: #334155; }
[data-bs-theme="dark"] .settings-sidebar-title,
[data-bs-theme="dark"] .settings-card-title { color: #f1f5f9; }
[data-bs-theme="dark"] .settings-sidebar-sub,
[data-bs-theme="dark"] .settings-card-subtitle { color: #94a3b8; }
[data-bs-theme="dark"] .settings-sidebar-icon { background: #1e3a5f; }
[data-bs-theme="dark"] .settings-nav-item { color: #94a3b8; }
[data-bs-theme="dark"] .settings-nav-item:hover { background: #1e3a5f; color: #93c5fd; }
[data-bs-theme="dark"] .settings-nav-item.active { background: #1e3a5f; color: #93c5fd; }
[data-bs-theme="dark"] .settings-field-label { color: #e2e8f0; }
[data-bs-theme="dark"] .settings-field-row { border-color: #334155; }
[data-bs-theme="dark"] .settings-perm-table thead th,
[data-bs-theme="dark"] .settings-activity-table thead th { background: #0f172a; color: #94a3b8; border-color: #334155; }
[data-bs-theme="dark"] .settings-perm-table tbody td,
[data-bs-theme="dark"] .settings-activity-table tbody td { border-color: #334155; color: #cbd5e1; }
[data-bs-theme="dark"] .settings-perm-table tbody tr:hover td,
[data-bs-theme="dark"] .settings-activity-table tbody tr:hover td { background: #0f172a; }
[data-bs-theme="dark"] .perm-label-cell { color: #e2e8f0; }
[data-bs-theme="dark"] .activity-ip { background: #334155; color: #e2e8f0; }
[data-bs-theme="dark"] .form-select,
[data-bs-theme="dark"] .form-control { background: #0f172a; border-color: #334155; color: #f1f5f9; }
</style>

<!-- ── Scripts ──────────────────────────────────────────────────────────────── -->
<script>
// Live announcement preview — always visible, falls back to example when empty
const PREVIEW_FALLBACK = <?php echo json_encode(t('ann_fallback', $translations, $lang)); ?>;

function updatePreview(text) {
    const previewText = document.getElementById('previewText');
    const charCount   = document.getElementById('charCount');
    previewText.textContent = text.trim() ? text.trim() : PREVIEW_FALLBACK;
    charCount.textContent = text.length;
}

// Update preview banner color when type changes
// Use a precise regex so only the colour token (e.g. "alert-info") is swapped,
// never the bare "alert" class or any other class that starts with "alert-".
document.getElementById('announcementType').addEventListener('change', function () {
    const preview  = document.getElementById('announcementPreview');
    const newType  = this.value;
    // Remove any existing alert-{color} class then add the new one
    const stripped = preview.className.replace(/\balert-(info|warning|success|danger)\b/g, '').trim();
    preview.className = stripped + ' alert-' + newType;
});

// Prevent Bootstrap's Alert component from ever closing the preview element
(function () {
    var preview = document.getElementById('announcementPreview');
    if (preview) {
        preview.addEventListener('close.bs.alert', function (e) { e.preventDefault(); });
    }
})();

// Re-open correct tab after POST error
<?php if (!empty($errors) && isset($_POST['action'])): ?>
document.addEventListener('DOMContentLoaded', function () {
    const tabMap = {
        'save_announcement': 'tab-announcement',
        'save_locale':       'tab-locale',
        'save_permissions':  'tab-permissions',
    };
    const targetId = tabMap['<?php echo htmlspecialchars($_POST['action']); ?>'];
    if (targetId) {
        const tabBtn = document.querySelector('[data-bs-target="#' + targetId + '"]');
        if (tabBtn) tabBtn.click();
    }
});
<?php endif; ?>

// ===== BACKUP EXPORT VERIFICATION =====
(function () {
    var _backupModalEl = document.getElementById('backupVerifyModal');
    if (!_backupModalEl) return; // guard: element must exist

    function _showSettingsToast(msg, type) {
        var toastEl   = document.getElementById('settingsToast');
        var toastMsg  = document.getElementById('settingsToastMsg');
        var toastIcon = document.getElementById('settingsToastIcon');
        if (!toastEl) return;
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

    function _showBackupAlert(msg, type) {
        var el = document.getElementById('backupVerifyAlert');
        if (!el) return;
        el.style.display = 'none';
        el.innerHTML = '';
        void el.offsetHeight;
        el.className = 'alert alert-' + type + ' small py-2 mb-3';
        el.innerText = msg;
        el.style.display = 'block';
    }

    function _hideBackupAlert() {
        var el = document.getElementById('backupVerifyAlert');
        if (!el) return;
        el.style.display = 'none';
        el.className = 'alert small py-2 mb-3';
        el.innerText = '';
    }

    function _setBackupBtnLoading(on) {
        var btn     = document.getElementById('backupVerifyBtn');
        var spinner = document.getElementById('backupBtnSpinner');
        var icon    = document.getElementById('backupBtnIcon');
        if (btn)     btn.disabled = on;
        if (spinner) spinner.classList.toggle('d-none', !on);
        if (icon)    icon.classList.toggle('d-none', on);
    }

    function _resetBackupModal() {
        var pw     = document.getElementById('backupPassword');
        var reason = document.getElementById('backupReason');
        var eye    = document.getElementById('backupEyeIcon');
        if (pw)     { pw.value = ''; pw.type = 'password'; }
        if (reason) reason.value = '';
        if (eye)    eye.className = 'bi bi-eye-slash';
        _setBackupBtnLoading(false);
        _hideBackupAlert();
    }

    window.toggleBackupPasswordVisibility = function () {
        var input = document.getElementById('backupPassword');
        var eye   = document.getElementById('backupEyeIcon');
        if (!input || !eye) return;
        if (input.type === 'password') {
            input.type = 'text';
            eye.classList.replace('bi-eye-slash', 'bi-eye');
        } else {
            input.type = 'password';
            eye.classList.replace('bi-eye', 'bi-eye-slash');
        }
    };

    window.openBackupExportModal = function () {
        _resetBackupModal();
        bootstrap.Modal.getOrCreateInstance(_backupModalEl).show();
    };

    function submitBackupVerification() {
        var password = (document.getElementById('backupPassword').value || '').trim();
        var reason   = document.getElementById('backupReason').value;

        if (!reason) { _showSettingsToast('Please select a purpose for this download.', 'warning'); return; }
        if (!password) { _showSettingsToast('Please enter your admin password to continue.', 'warning'); return; }

        _setBackupBtnLoading(true);
        _hideBackupAlert();

        var basePath   = window.location.pathname.replace(/\/[^/]+$/, '/');
        var verifyPath = basePath + 'verify_action.php';
        var fd = new FormData();
        fd.append('password',    password);
        fd.append('reason',      reason);
        fd.append('export_type', 'SQL_BACKUP');
        fd.append('table_name',  'database_backup');

        fetch(verifyPath, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (res) { if (!res.ok) throw new Error('Server error ' + res.status); return res.json(); })
            .then(function (data) {
                if (!data.success) {
                    _setBackupBtnLoading(false);
                    _showBackupAlert(data.message || 'Incorrect password. Download denied.', 'danger');
                    return;
                }
                _showBackupAlert('Verification successful. Starting download...', 'success');
                var downloadUrl = window.location.pathname + '?export=backup&export_token=' + encodeURIComponent(data.token);
                var iframe = document.createElement('iframe');
                iframe.style.display = 'none';
                iframe.src = downloadUrl;
                document.body.appendChild(iframe);
                setTimeout(function () {
                    document.body.removeChild(iframe);
                    _setBackupBtnLoading(false);
                    bootstrap.Modal.getOrCreateInstance(_backupModalEl).hide();
                }, 3000);
            })
            .catch(function () {
                _setBackupBtnLoading(false);
                _showBackupAlert('Network error. Please try again.', 'danger');
            });
    }

    document.getElementById('backupVerifyBtn').addEventListener('click', submitBackupVerification);

    _backupModalEl.addEventListener('hide.bs.modal', function () {
        var f = _backupModalEl.querySelector(':focus'); if (f) f.blur();
    });
    _backupModalEl.addEventListener('hidden.bs.modal', _hideBackupAlert);
})();
// ===== END BACKUP VERIFICATION =====
</script>

<?php include __DIR__ . '/footer.php'; ?>