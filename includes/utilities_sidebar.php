<?php
// includes/utilities_sidebar.php
include_once __DIR__ . '/emergency_banner.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$userName  = htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['full_name'] ?? 'Resident', ENT_QUOTES, 'UTF-8');
$userType  = $_SESSION['user_type'] ?? '';
$currentPage = basename($_SERVER['PHP_SELF']);
// Allow subdirectory pages (e.g. admin/) to set a base path prefix.
// Root-level pages don't need to set this; it defaults to ''.
if (!isset($sidebarBase)) {
    $sidebarBase = '';
}

// Helper to mark active link
function sidebarActive(string $page, string $current): string {
    return $page === $current ? ' active' : '';
}

// Helper to render Day/Night Toggle Switch (skeuomorphic animated mode toggle)
if (!function_exists('renderDayNightToggle')) {
    function renderDayNightToggle(string $extraClass = '', string $id = ''): string {
        $idAttr = $id !== '' ? ' id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '"' : '';
        return '<div class="day-night-toggle ' . htmlspecialchars($extraClass, ENT_QUOTES, 'UTF-8') . '"' . $idAttr . ' role="switch" aria-checked="false" aria-label="Toggle Dark and Light Mode">
            <div class="dnt-halos">
                <span class="dnt-halo dnt-halo-1"></span>
                <span class="dnt-halo dnt-halo-2"></span>
                <span class="dnt-halo dnt-halo-3"></span>
            </div>
            <div class="dnt-stars">
                <svg class="dnt-star dnt-star-1" viewBox="0 0 24 24"><path d="M12 0 C12 6.6 6.6 12 0 12 C6.6 12 12 17.4 12 24 C12 17.4 17.4 12 24 12 C17.4 12 12 6.6 12 0 Z"/></svg>
                <svg class="dnt-star dnt-star-2" viewBox="0 0 24 24"><path d="M12 0 C12 6.6 6.6 12 0 12 C6.6 12 12 17.4 12 24 C12 17.4 17.4 12 24 12 C17.4 12 12 6.6 12 0 Z"/></svg>
                <svg class="dnt-star dnt-star-3" viewBox="0 0 24 24"><path d="M12 0 C12 6.6 6.6 12 0 12 C6.6 12 12 17.4 12 24 C12 17.4 17.4 12 24 12 C17.4 12 12 6.6 12 0 Z"/></svg>
                <span class="dnt-dot dnt-dot-1"></span>
                <span class="dnt-dot dnt-dot-2"></span>
                <span class="dnt-dot dnt-dot-3"></span>
            </div>
            <div class="dnt-clouds">
                <div class="dnt-cloud-back">
                    <span class="dnt-puff puff-b1"></span>
                    <span class="dnt-puff puff-b2"></span>
                    <span class="dnt-puff puff-b3"></span>
                </div>
                <div class="dnt-cloud-front">
                    <span class="dnt-puff puff-f1"></span>
                    <span class="dnt-puff puff-f2"></span>
                    <span class="dnt-puff puff-f3"></span>
                    <span class="dnt-puff puff-f4"></span>
                </div>
            </div>
            <div class="dnt-knob">
                <div class="dnt-craters">
                    <span class="dnt-crater crater-1"></span>
                    <span class="dnt-crater crater-2"></span>
                    <span class="dnt-crater crater-3"></span>
                </div>
            </div>
        </div>';
    }
}

// Fetch badge counts for citizen navigation
$activeReportCount = 0;
$unreadNotifCount = 0;
if ($userType !== 'employee' && isset($pdo) && isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM utility_incidents WHERE resident_id = ? AND status NOT IN ('Resolved', 'Closed')");
        $stmt->execute([$_SESSION['user_id']]);
        $activeReportCount = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {}

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM incident_notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $unreadNotifCount = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {}
}
?>
<link rel="stylesheet" href="<?php echo $sidebarBase; ?>assets/css/responsive.css">
<script>
    // Immediate script to apply theme before document rendering to prevent flash of light theme
    (function() {
        const savedTheme = localStorage.getItem('theme') || 'light';
        if (savedTheme === 'dark') {
            document.documentElement.classList.add('dark-theme');
        }
    })();
</script>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap');

    /* ===== CITIZEN MODE BOTTOM NAV BAR OVERRIDES (Messenger Style - Photo 3) ===== */
    <?php if ($userType !== 'employee'): ?>
    .sidebar-nav {
        display: none !important;
    }
    .sidebar-backdrop {
        display: none !important;
    }
    .main-content {
        margin-left: 0 !important;
        margin-top: 0 !important;
        padding-top: 80px !important;
        padding-bottom: 95px !important;
        width: 100% !important;
        max-width: 100vw !important;
        box-sizing: border-box !important;
    }
    .main-content.collapsed {
        margin-left: 0 !important;
        margin-top: 0 !important;
    }
    .mobile-topbar {
        display: flex !important;
    }
    .mobile-nav-toggle {
        display: none !important;
    }
    .card, .container, .dashboard-container, .main-card {
        margin-left: auto !important;
        margin-right: auto !important;
    }
    <?php endif; ?>

    .citizen-bottom-nav {
        position: fixed;
        bottom: 16px;
        left: 50%;
        transform: translateX(-50%);
        right: auto;
        width: 92%;
        max-width: 640px;
        height: 66px;
        background: rgba(18, 24, 38, 0.95);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 32px;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        overflow: hidden;
        padding: 0;
        z-index: 10000;
        box-shadow: 0 12px 35px rgba(0, 0, 0, 0.4);
        transition: height 0.38s cubic-bezier(0.32, 0.72, 0, 1),
                    transform 0.35s cubic-bezier(0.32, 0.72, 0, 1),
                    border-radius 0.38s cubic-bezier(0.32, 0.72, 0, 1),
                    box-shadow 0.38s ease;
        font-family: 'Poppins', sans-serif;
    }

    body:not(.dark-theme) .citizen-bottom-nav {
        background: rgba(255, 255, 255, 0.96);
        border: 1px solid rgba(0, 0, 0, 0.1);
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.12);
    }

    .dark-theme .citizen-bottom-nav {
        background: #111827;
        border: 1px solid #1f2937;
        box-shadow: 0 12px 35px rgba(0, 0, 0, 0.5);
    }

    /* Expanded state - bar grows upward to show menu */
    .citizen-bottom-nav.menu-expanded {
        height: auto;
        border-radius: 24px;
        box-shadow: 0 -4px 40px rgba(0, 0, 0, 0.35), 0 12px 35px rgba(0, 0, 0, 0.4);
    }

    /* The row of nav items pinned to the bottom */
    .citizen-bottom-nav-items {
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 66px;
        display: flex;
        align-items: center;
        justify-content: space-around;
        padding: 0 10px;
        box-sizing: border-box;
        transition: opacity 0.15s ease, visibility 0.15s ease;
        opacity: 1;
        visibility: visible;
        z-index: 1;
    }

    .citizen-bottom-nav.menu-expanded .citizen-bottom-nav-items {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
    }

    /* Expanded menu panel that sits above the nav items */
    .citizen-bottom-menu-panel {
        width: 100%;
        max-height: 0;
        overflow: hidden;
        opacity: 0;
        transition: max-height 0.38s cubic-bezier(0.32, 0.72, 0, 1),
                    opacity 0.28s ease,
                    padding 0.38s cubic-bezier(0.32, 0.72, 0, 1);
        padding: 0 14px;
        box-sizing: border-box;
    }

    .citizen-bottom-nav.menu-expanded .citizen-bottom-menu-panel {
        max-height: 420px;
        opacity: 1;
        padding: 20px 14px;
    }

    .citizen-sheet-handle {
        width: 40px;
        height: 5px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 99px;
        margin: -8px auto 16px auto;
        cursor: grab;
        user-select: none;
        transition: background 0.2s ease;
    }

    body:not(.dark-theme) .citizen-sheet-handle {
        background: rgba(0, 0, 0, 0.15);
    }

    .citizen-sheet-handle:active {
        cursor: grabbing;
    }

    body:not(.dark-theme) .citizen-bottom-menu-divider {
        background: rgba(0, 0, 0, 0.08);
    }

    /* Menu panel user header */
    .citizen-panel-user {
        display: flex;
        align-items: center;
        gap: 12px;
        padding-bottom: 14px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        margin-bottom: 12px;
    }

    body:not(.dark-theme) .citizen-panel-user {
        border-bottom-color: rgba(0, 0, 0, 0.08);
    }

    .citizen-panel-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #3762c8, #6384d2);
        display: grid;
        place-items: center;
        color: #fff;
        font-size: 16px;
        flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(55, 98, 200, 0.3);
    }

    .citizen-panel-name {
        font-size: 14px;
        font-weight: 700;
        color: #f8fafc;
        line-height: 1.2;
    }

    body:not(.dark-theme) .citizen-panel-name {
        color: #1e293b;
    }

    .citizen-panel-role {
        font-size: 11px;
        color: #94a3b8;
        font-weight: 500;
    }

    /* Menu panel items */
    .citizen-panel-items {
        display: flex;
        flex-direction: column;
        gap: 7px;
        padding-bottom: 14px;
    }

    .citizen-panel-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 14px;
        border-radius: 12px;
        text-decoration: none;
        color: #f8fafc;
        font-size: 13px;
        font-weight: 600;
        background: rgba(255, 255, 255, 0.07);
        border: 1px solid rgba(255, 255, 255, 0.1);
        transition: background 0.2s ease, transform 0.15s ease;
        cursor: pointer;
        width: 100%;
        text-align: left;
        box-sizing: border-box;
    }

    body:not(.dark-theme) .citizen-panel-item {
        color: #1e293b;
        background: rgba(0, 0, 0, 0.04);
        border-color: rgba(0, 0, 0, 0.08);
    }

    .citizen-panel-item:hover {
        background: rgba(55, 98, 200, 0.18);
        transform: translateX(3px);
    }

    body:not(.dark-theme) .citizen-panel-item:hover {
        background: rgba(55, 98, 200, 0.08);
    }

    .citizen-panel-item.logout-item {
        color: #f87171;
        background: rgba(239, 68, 68, 0.08);
        border-color: rgba(239, 68, 68, 0.2);
    }

    body:not(.dark-theme) .citizen-panel-item.logout-item {
        color: #dc2626;
        background: #fef2f2;
        border-color: #fee2e2;
    }

    .citizen-panel-item.logout-item:hover {
        background: rgba(239, 68, 68, 0.18);
    }

    .citizen-panel-item-left {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .citizen-panel-item-left i {
        width: 20px;
        text-align: center;
        font-size: 14px;
        color: #6384d2;
    }

    body:not(.dark-theme) .citizen-panel-item-left i {
        color: #3762c8;
    }

    .citizen-panel-item.logout-item .citizen-panel-item-left i {
        color: #f87171;
    }

    body:not(.dark-theme) .citizen-panel-item.logout-item .citizen-panel-item-left i {
        color: #dc2626;
    }

    .citizen-bottom-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        color: #94a3b8;
        font-size: 11px;
        font-weight: 500;
        padding: 6px 14px;
        border-radius: 20px;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        background: transparent;
        border: none;
        cursor: pointer;
        line-height: 1.2;
    }

    .citizen-bottom-item i {
        font-size: 18px;
        margin-bottom: 3px;
        transition: transform 0.2s ease, color 0.2s ease;
    }

    /* Active Capsule Pill (Photo 3 Highlighted Blue Style) */
    .citizen-bottom-item.active {
        color: #ffffff !important;
        background: #3762c8 !important;
        box-shadow: 0 4px 14px rgba(55, 98, 200, 0.4);
        transform: translateY(-2px);
        padding: 6px 18px;
    }

    .citizen-bottom-item.active i {
        color: #ffffff !important;
        transform: scale(1.1);
    }

    .citizen-bottom-item:hover:not(.active) {
        color: #3762c8;
        background: rgba(55, 98, 200, 0.1);
    }

    .dark-theme .citizen-bottom-item:hover:not(.active) {
        color: #6384d2;
        background: rgba(255, 255, 255, 0.08);
    }

    /* Notification / Counter Badge (Photo 3 Red Badge style) */
    .citizen-bottom-badge {
        position: absolute;
        top: 2px;
        right: 6px;
        background: #ef4444;
        color: #ffffff;
        font-size: 10px;
        font-weight: 700;
        padding: 1px 5px;
        min-width: 16px;
        height: 16px;
        border-radius: 99px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 2px solid rgba(18, 24, 38, 0.95);
        line-height: 1;
        box-shadow: 0 2px 6px rgba(239, 68, 68, 0.4);
    }

    body:not(.dark-theme) .citizen-bottom-badge {
        border-color: #ffffff;
    }



    /* ===== GLASSMORPHISM SIDEBAR — matches utilities_dashboard.php exactly ===== */
    .sidebar-nav {
        position: fixed;
        top: 0;
        left: 0;
        width: 280px;
        height: 100vh;
        background: rgba(255, 255, 255, 0.795);
        backdrop-filter: blur(18px);
        -webkit-backdrop-filter: blur(18px);
        border-right: 1px solid rgba(255, 255, 255, 0.25);
        box-shadow: 4px 0 25px rgba(0, 0, 0, 0.25);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        z-index: 1000;
        transition: transform 0.3s ease, width 0.25s ease;
        overflow: hidden; /* Main container stays fixed */
        font-family: 'Poppins', sans-serif;
    }

    /* Fixed top header */
    .sidebar-header {
        display: flex;
        flex-direction: column;
        flex-shrink: 0;
        padding-top: 16px;
    }

    /* Scrollable module navigation */
    .sidebar-menu-scrollable {
        flex: 1 1 auto;
        overflow-y: auto;
        overflow-x: hidden;
        min-height: 0;
        padding: 4px 0 10px 0;
        overscroll-behavior: contain;
    }

    /* Fixed bottom user info & logout */
    .sidebar-bottom {
        flex-shrink: 0;
        margin-top: auto;
        background: inherit;
        z-index: 2;
    }

    /* ===== MODERN SLEEK SCROLLBAR STYLES ===== */
    /* Webkit (Chrome, Edge, Safari, Opera) */
    ::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }
    ::-webkit-scrollbar-track {
        background: rgba(0, 0, 0, 0.15);
    }
    ::-webkit-scrollbar-thumb {
        background: rgba(148, 163, 184, 0.5);
        border-radius: 99px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        transition: background 0.2s ease;
    }
    ::-webkit-scrollbar-thumb:hover {
        background: #3762c8;
    }

    /* Firefox */
    * {
        scrollbar-width: thin;
        scrollbar-color: rgba(148, 163, 184, 0.5) rgba(0, 0, 0, 0.15);
    }

    /* Dark Theme Scrollbars */
    .dark-theme ::-webkit-scrollbar-track {
        background: rgba(15, 23, 42, 0.5);
    }
    .dark-theme ::-webkit-scrollbar-thumb {
        background: rgba(148, 163, 184, 0.35);
        border: 1px solid rgba(255, 255, 255, 0.05);
    }
    .dark-theme ::-webkit-scrollbar-thumb:hover {
        background: #6384d2;
    }
    .dark-theme * {
        scrollbar-color: rgba(148, 163, 184, 0.35) rgba(15, 23, 42, 0.5);
    }

    /* Sidebar specific scrollbar */
    .sidebar-menu-scrollable::-webkit-scrollbar {
        width: 5px;
    }
    .sidebar-menu-scrollable::-webkit-scrollbar-track {
        background: transparent;
    }
    .sidebar-menu-scrollable::-webkit-scrollbar-thumb {
        background: rgba(99, 132, 210, 0.3);
        border-radius: 99px;
    }
    .sidebar-menu-scrollable::-webkit-scrollbar-thumb:hover {
        background: #3762c8;
    }
    .dark-theme .sidebar-menu-scrollable::-webkit-scrollbar-thumb {
        background: rgba(148, 163, 184, 0.25);
    }
    .dark-theme .sidebar-menu-scrollable::-webkit-scrollbar-thumb:hover {
        background: #6384d2;
    }

    /* Collapse/toggle button */
    .collapse-btn {
        align-self: flex-end;
        margin: 4px 16px 8px 0;
        width: 56px;
        height: 56px;
        border-radius: 14px;
        border: 1px solid rgba(0, 0, 0, 0.15);
        background: linear-gradient(135deg, #97a4c2, #6384d2);
        color: #fff;
        font-weight: 700;
        font-size: 20px;
        display: grid;
        place-items: center;
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
    }
    .collapse-btn:hover {
        transform: translateY(-2px) scale(1.02);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
        background: linear-gradient(135deg, #4d76d6, #1651d0);
    }

    /* Logo area */
    .site-logo {
        margin-top: 5px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding-bottom: 5px;
        width: calc(100% - 50px);
        margin-left: 25px;
        margin-right: 25px;
        margin-bottom: 10px;
    }
    .site-logo img {
        width: 45px;
        height: 45px;
        border-radius: 10px;
        object-fit: cover;
        box-shadow: 0 4px 10px rgba(0,0,0,0.15);
    }
    .logo-text { color: #000; font-weight: 600; }
    .logo-text h3 { font-size: 16px; margin: 0; font-family: 'Poppins', sans-serif; }
    .logo-text p  { font-size: 12px; opacity: 0.75; margin: 0; font-family: 'Poppins', sans-serif; }

    /* Divider */
    .sidebar-divider {
        border: none;
        border-bottom: 2px solid rgba(0, 0, 0, 0.12);
        width: calc(100% - 50px);
        margin: 10px 25px;
    }

    /* Nav list */
    .nav-list {
        list-style: none;
        font-size: 14px;
        padding: 0 20px;
        margin: 0;
        display: flex;
        flex-direction: column;
    }
    .nav-list li { width: 100%; margin: 2px 0; }

    /* Section headers */
    .nav-section-header {
        padding: 12px 20px 4px 20px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1.2px;
        color: #475569;
        opacity: 0.8;
        font-family: 'Poppins', sans-serif;
    }

    /* Nav links */
    .nav-link {
        display: flex;
        align-items: center;
        gap: 12px;
        color: #1e293b;
        text-decoration: none;
        padding: 10px 20px;
        transition: all 0.3s ease;
        border-radius: 8px;
        font-family: 'Poppins', sans-serif;
        font-weight: 500;
    }
    .nav-link i {
        width: 20px;
        font-size: 15px;
        color: #6384d2;
        flex-shrink: 0;
        text-align: center;
    }
    .nav-link .link-label {
        display: inline-block;
        line-height: 1.4;
    }

    /* Active state — solid blue pill like the dashboard */
    .nav-link.active,
    .nav-link.active:hover {
        background: #3762c8;
        color: #fff;
        transform: translateX(2px);
        box-shadow: 0 4px 12px rgba(55, 98, 200, 0.35);
    }
    .nav-link.active i { color: #fff; }

    /* Hover state */
    .nav-link:hover {
        background: rgba(151, 164, 194, 0.35);
        transform: translateX(8px) scale(1.02);
    }
    .nav-link:hover i { color: #3762c8; }

    /* User info bottom section */
    .user-info {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 16px 0 20px;
        border-top: 1px solid rgba(0, 0, 0, 0.09);
    }
    .user-welcome {
        text-align: center;
        color: #1e293b;
        font-weight: 600;
        font-size: 0.9rem;
        margin-bottom: 10px;
        font-family: 'Poppins', sans-serif;
    }
    .back-link {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 9px 14px;
        margin-bottom: 8px;
        border-radius: 7px;
        background: rgba(41, 128, 185, 0.15);
        border: 1px solid rgba(41, 128, 185, 0.35);
        color: #1a3a5c;
        text-decoration: none;
        transition: all 0.3s ease;
        width: 88%;
        font-family: 'Poppins', sans-serif;
        font-size: 13px;
        font-weight: 500;
    }
    .back-link i { color: #2980b9; }
    .back-link:hover {
        background: rgba(41, 128, 185, 0.3);
        transform: translateX(-4px);
        box-shadow: 0 4px 12px rgba(41, 128, 185, 0.2);
    }
    .logout-btn {
        background: #3762c8;
        color: #fff;
        padding: 9px 14px;
        border-radius: 7px;
        cursor: pointer;
        transition: all 0.3s ease;
        width: 88%;
        border: none;
        font-weight: 600;
        font-size: 13px;
        font-family: 'Poppins', sans-serif;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    .logout-btn:hover {
        background: #2851b0;
        transform: translateY(-2px);
        box-shadow: 0 5px 14px rgba(55, 98, 200, 0.4);
    }

    /* ===== COLLAPSED STATE ===== */
    .sidebar-nav.collapsed { width: 78px; }
    .sidebar-nav.collapsed .site-logo .logo-text,
    .sidebar-nav.collapsed .nav-section-header,
    .sidebar-nav.collapsed .link-label,
    .sidebar-nav.collapsed .user-welcome,
    .sidebar-nav.collapsed .theme-toggle-btn span,
    .sidebar-nav.collapsed .theme-toggle-meta,
    .sidebar-nav.collapsed .logout-btn .logout-text,
    .sidebar-nav.collapsed .back-link { display: none !important; }
    .sidebar-nav.collapsed .nav-link {
        justify-content: center;
        padding: 12px 0;
    }
    .sidebar-nav.collapsed .nav-link i { margin-right: 0; width: auto; }
    .sidebar-nav.collapsed .user-info {
        padding: 10px 0 16px;
    }
    .sidebar-nav.collapsed .theme-toggle-container {
        width: 52px;
        height: 44px;
        padding: 0;
        justify-content: center;
        border-radius: 10px;
        margin: 0 auto 8px auto;
    }
    .sidebar-nav.collapsed .day-night-toggle {
        transform: scale(0.66);
        transform-origin: center center;
    }
    .sidebar-nav.collapsed .theme-toggle-btn,
    .sidebar-nav.collapsed .logout-btn {
        width: 44px;
        height: 44px;
        padding: 0;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 8px auto;
    }

    /* ===== TOP HEADER BAR (Employee Desktop) ===== */
    :root {
        --topbar-height: 62px;
    }

    .app-topbar {
        position: fixed;
        top: 0;
        left: 280px;
        right: 0;
        height: var(--topbar-height);
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(18px);
        -webkit-backdrop-filter: blur(18px);
        border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        box-shadow: 0 2px 16px rgba(0, 0, 0, 0.06);
        z-index: 900;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 24px;
        transition: left 0.25s ease;
        font-family: 'Poppins', sans-serif;
    }

    .app-topbar.collapsed {
        left: 90px;
    }

    .dark-theme .app-topbar {
        background: rgba(13, 19, 33, 0.95);
        border-bottom-color: rgba(255, 255, 255, 0.08);
        box-shadow: 0 2px 16px rgba(0, 0, 0, 0.35);
    }

    /* Left side: page breadcrumb / greeting */
    .topbar-left {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .topbar-greeting {
        font-size: 13px;
        color: #64748b;
        font-weight: 400;
    }

    .topbar-user-name {
        font-size: 15px;
        font-weight: 700;
        color: #0f172a;
        letter-spacing: -0.2px;
    }

    .dark-theme .topbar-greeting {
        color: #94a3b8;
    }

    .dark-theme .topbar-user-name {
        color: #f8fafc;
    }

    .topbar-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: linear-gradient(135deg, #3762c8, #6384d2);
        display: grid;
        place-items: center;
        color: #fff;
        font-size: 14px;
        flex-shrink: 0;
        box-shadow: 0 3px 10px rgba(55, 98, 200, 0.3);
    }

    /* Right side: action buttons */
    .topbar-actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .topbar-btn {
        position: relative;
        width: 38px;
        height: 38px;
        border-radius: 10px;
        border: 1px solid rgba(0, 0, 0, 0.1);
        background: rgba(248, 250, 252, 0.8);
        color: #475569;
        font-size: 16px;
        display: grid;
        place-items: center;
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        outline: none;
        flex-shrink: 0;
    }

    .topbar-btn:hover {
        background: #3762c8;
        color: #fff;
        border-color: #3762c8;
        box-shadow: 0 4px 12px rgba(55, 98, 200, 0.35);
        transform: translateY(-1px);
    }

    .dark-theme .topbar-btn {
        background: rgba(30, 41, 59, 0.8);
        border-color: rgba(255, 255, 255, 0.1);
        color: #94a3b8;
    }

    .dark-theme .topbar-btn:hover {
        background: #3762c8;
        color: #fff;
        border-color: #3762c8;
    }

    /* Notification badge on bell */
    .topbar-notif-badge {
        position: absolute;
        top: 3px;
        right: 3px;
        width: 8px;
        height: 8px;
        background: #ef4444;
        border-radius: 50%;
        border: 2px solid rgba(255, 255, 255, 0.9);
        box-shadow: 0 0 0 1px rgba(239, 68, 68, 0.4);
    }

    .dark-theme .topbar-notif-badge {
        border-color: rgba(13, 19, 33, 0.9);
    }

    /* Day/night toggle in topbar */
    .topbar-theme-wrap {
        display: flex;
        align-items: center;
        gap: 0;
        cursor: pointer;
        padding: 0;
        border: none;
        background: transparent;
        outline: none;
    }

    .topbar-theme-wrap:hover .day-night-toggle {
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.3);
    }

    /* Topbar divider */
    .topbar-divider {
        width: 1px;
        height: 24px;
        background: rgba(0, 0, 0, 0.1);
        border-radius: 1px;
        margin: 0 4px;
    }

    .dark-theme .topbar-divider {
        background: rgba(255, 255, 255, 0.1);
    }

    /* Sync main-content margin with sidebar */
    .main-content { margin-left: 280px; margin-top: var(--topbar-height); transition: margin-left 0.25s ease; min-height: calc(100vh - var(--topbar-height)); display: flex; flex-direction: column; }
    .main-content > .card { flex: 1; }
    .main-content.collapsed { margin-left: 90px; }

    /* Hide topbar on mobile (uses mobile-topbar instead) */
    @media (max-width: 992px) {
        .app-topbar {
            display: none;
        }
        .main-content {
            margin-top: 0 !important;
            min-height: 100vh;
        }
    }

    /* ===== TOPBAR CLOCK ===== */
    .topbar-clock-block {
        display: flex;
        flex-direction: column;
        gap: 1px;
    }

    .topbar-clock-time {
        font-size: 20px;
        font-weight: 700;
        color: #0f172a;
        letter-spacing: 0.5px;
        line-height: 1.2;
        font-variant-numeric: tabular-nums;
        font-family: 'Poppins', sans-serif;
    }

    .dark-theme .topbar-clock-time {
        color: #f8fafc;
    }

    .topbar-clock-date {
        font-size: 11px;
        color: #64748b;
        font-weight: 500;
        letter-spacing: 0.3px;
        font-family: 'Poppins', sans-serif;
    }

    .dark-theme .topbar-clock-date {
        color: #94a3b8;
    }

    /* ===== ACCOUNT AVATAR & POPOVER ===== */
    .topbar-account-wrap {
        position: relative;
    }

    .topbar-account-btn {
        display: flex;
        align-items: center;
        gap: 7px;
        background: rgba(55, 98, 200, 0.1);
        border: 1px solid rgba(55, 98, 200, 0.2);
        border-radius: 99px;
        padding: 4px 10px 4px 4px;
        cursor: pointer;
        outline: none;
        transition: all 0.2s ease;
    }

    .topbar-account-btn:hover {
        background: rgba(55, 98, 200, 0.18);
        border-color: rgba(55, 98, 200, 0.4);
        box-shadow: 0 3px 12px rgba(55, 98, 200, 0.2);
    }

    .dark-theme .topbar-account-btn {
        background: rgba(99, 132, 210, 0.12);
        border-color: rgba(99, 132, 210, 0.25);
    }

    .dark-theme .topbar-account-btn:hover {
        background: rgba(99, 132, 210, 0.22);
    }

    .topbar-acct-avatar {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: linear-gradient(135deg, #3762c8, #6384d2);
        display: grid;
        place-items: center;
        color: #fff;
        font-size: 13px;
        flex-shrink: 0;
        box-shadow: 0 2px 8px rgba(55, 98, 200, 0.3);
    }

    .topbar-acct-caret {
        font-size: 10px;
        color: #3762c8;
        transition: transform 0.25s ease;
    }

    .dark-theme .topbar-acct-caret {
        color: #6384d2;
    }

    .topbar-acct-caret.open {
        transform: rotate(180deg);
    }

    /* Popover panel */
    .topbar-account-popover {
        position: absolute;
        top: calc(100% + 10px);
        right: 0;
        width: 260px;
        background: #ffffff;
        border-radius: 16px;
        border: 1px solid rgba(0, 0, 0, 0.08);
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.14), 0 4px 12px rgba(0, 0, 0, 0.06);
        opacity: 0;
        visibility: hidden;
        transform: translateY(-8px) scale(0.97);
        transition: opacity 0.22s ease, visibility 0.22s ease, transform 0.22s cubic-bezier(0.34, 1.56, 0.64, 1);
        z-index: 9999;
        overflow: hidden;
        font-family: 'Poppins', sans-serif;
    }

    .topbar-account-popover.open {
        opacity: 1;
        visibility: visible;
        transform: translateY(0) scale(1);
    }

    .dark-theme .topbar-account-popover {
        background: #1e293b;
        border-color: rgba(255, 255, 255, 0.1);
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.5), 0 4px 12px rgba(0, 0, 0, 0.3);
    }

    /* Popover header */
    .tpop-header {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 18px 18px 14px;
    }

    .tpop-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: linear-gradient(135deg, #3762c8, #6384d2);
        display: grid;
        place-items: center;
        color: #fff;
        font-size: 20px;
        flex-shrink: 0;
        box-shadow: 0 4px 14px rgba(55, 98, 200, 0.35);
    }

    .tpop-name {
        font-size: 15px;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.3;
    }

    .dark-theme .tpop-name {
        color: #f8fafc;
    }

    .tpop-role {
        font-size: 11px;
        color: #3762c8;
        font-weight: 600;
        margin-top: 2px;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .dark-theme .tpop-role {
        color: #6384d2;
    }

    /* Popover divider */
    .tpop-divider {
        height: 1px;
        background: rgba(0, 0, 0, 0.07);
        margin: 0 18px;
    }

    .dark-theme .tpop-divider {
        background: rgba(255, 255, 255, 0.08);
    }

    /* Popover detail rows */
    .tpop-details {
        padding: 12px 18px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .tpop-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }

    .tpop-label {
        font-size: 12px;
        color: #64748b;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .dark-theme .tpop-label {
        color: #94a3b8;
    }

    .tpop-label i {
        width: 14px;
        text-align: center;
        color: #6384d2;
        font-size: 11px;
    }

    .tpop-value {
        font-size: 12px;
        color: #1e293b;
        font-weight: 600;
        text-align: right;
    }

    .dark-theme .tpop-value {
        color: #e2e8f0;
    }

    .tpop-online {
        color: #10b981 !important;
        font-size: 8px !important;
    }

    .tpop-status-active {
        color: #10b981 !important;
    }

    /* Popover logout button */
    .tpop-logout-btn {
        width: 100%;
        padding: 12px 18px;
        background: rgba(239, 68, 68, 0.06);
        border: none;
        border-top: 1px solid rgba(239, 68, 68, 0.1);
        color: #ef4444;
        font-size: 13px;
        font-weight: 600;
        font-family: 'Poppins', sans-serif;
        cursor: pointer;
        text-align: left;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: background 0.2s ease, color 0.2s ease;
        margin-top: 4px;
    }

    .tpop-logout-btn:hover {
        background: rgba(239, 68, 68, 0.12);
        color: #dc2626;
    }

    .dark-theme .tpop-logout-btn {
        background: rgba(239, 68, 68, 0.08);
        border-top-color: rgba(239, 68, 68, 0.15);
        color: #f87171;
    }

    .dark-theme .tpop-logout-btn:hover {
        background: rgba(239, 68, 68, 0.16);
        color: #fca5a5;
    }

    /* ===== SMOOTH VIEW TRANSITIONS & MODULE SWITCHING ===== */
    @view-transition {
        navigation: auto;
    }

    ::view-transition-group(root) {
        animation-duration: 0.22s;
    }

    .sidebar-nav {
        view-transition-name: sidebar-navigation;
    }

    body::before {
        view-transition-name: background-overlay;
    }

    .main-content {
        view-transition-name: page-main-content;
    }

    /* Content Entrance Animation for all module pages */
    @keyframes moduleFadeEnter {
        0% {
            opacity: 0;
            transform: translateY(8px);
        }
        100% {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .main-content > .card,
    .main-content > .dashboard-container,
    .main-content > .container,
    .main-content > .container-fluid {
        animation: moduleFadeEnter 0.24s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        will-change: opacity, transform;
    }

    /* Smooth page exit transition */
    .page-nav-exiting .main-content > .card,
    .page-nav-exiting .main-content > .dashboard-container,
    .page-nav-exiting .main-content > .container,
    .page-nav-exiting .main-content > .container-fluid {
        opacity: 0 !important;
        transform: translateY(-6px) !important;
        transition: opacity 0.14s ease-out, transform 0.14s ease-out !important;
    }

    @media (max-width: 992px) {
        .sidebar-nav {
            transform: translateX(-100%);
        }
        .sidebar-nav.open {
            transform: translateX(0);
        }
        .main-content { margin-left: 0 !important; }
    }

    /* Logout Confirmation Modal Styles */
    .logout-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(15, 23, 42, 0.6);
        z-index: 99999;
        justify-content: center;
        align-items: center;
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        animation: logoutFadeIn 0.25s ease-out;
    }
    .logout-modal.show {
        display: flex;
    }
    .logout-modal-content {
        background: #ffffff;
        border-radius: 16px;
        width: 400px;
        max-width: 90%;
        padding: 30px;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 10px 10px -5px rgba(0, 0, 0, 0.05);
        text-align: center;
        animation: logoutScaleUp 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .logout-modal-header i {
        font-size: 48px;
        color: #ef4444;
        margin-bottom: 15px;
    }
    .logout-modal-header h2 {
        font-size: 22px;
        color: #0f172a;
        font-weight: 600;
        margin-bottom: 10px;
        margin-top: 0;
    }
    .logout-modal-text {
        font-size: 14px;
        color: #64748b;
        margin-bottom: 25px;
        line-height: 1.5;
    }
    .logout-modal-actions {
        display: flex;
        gap: 12px;
        justify-content: center;
    }
    .logout-modal-btn {
        padding: 10px 24px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        border: none;
        display: inline-block;
    }
    .logout-modal-btn.cancel-btn {
        background: #f1f5f9;
        color: #475569;
    }
    .logout-modal-btn.cancel-btn:hover {
        background: #e2e8f0;
        color: #334155;
    }
    .logout-modal-btn.confirm-btn {
        background: #ef4444;
        color: #ffffff;
    }
    .logout-modal-btn.confirm-btn:hover {
        background: #dc2626;
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
    }
    @keyframes logoutFadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    @keyframes logoutScaleUp {
        from { transform: scale(0.95); opacity: 0; }
        to { transform: scale(1); opacity: 1; }
    }

    /* ===== THEME TOGGLE CONTAINER & SKEUOMORPHIC DAY/NIGHT SWITCH ===== */
    .theme-toggle-container {
        width: 88%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 6px 12px;
        border-radius: 12px;
        background: rgba(0, 0, 0, 0.04);
        border: 1px solid rgba(0, 0, 0, 0.08);
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        margin: 0 auto 10px auto;
        user-select: none;
        outline: none;
    }
    .theme-toggle-container:hover {
        background: rgba(0, 0, 0, 0.08);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
    }
    .theme-toggle-container:focus-visible {
        box-shadow: 0 0 0 3px rgba(55, 98, 200, 0.3);
    }
    .theme-toggle-meta {
        display: flex;
        flex-direction: column;
        text-align: left;
    }
    .theme-toggle-title {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.8px;
        text-transform: uppercase;
        color: #64748b;
        line-height: 1.2;
    }
    .theme-toggle-status {
        font-size: 13px;
        font-weight: 600;
        color: #1e293b;
        line-height: 1.3;
    }

    /* Standard Day/Night Switch Variables */
    :root {
        --dnt-w: 86px;
        --dnt-h: 36px;
        --dnt-knob: 28px;
        --dnt-pad: 4px;
    }

    .day-night-toggle {
        position: relative;
        width: var(--dnt-w);
        height: var(--dnt-h);
        border-radius: 999px;
        cursor: pointer;
        overflow: hidden;
        border: 2px solid rgba(255, 255, 255, 0.9);
        background: linear-gradient(180deg, #2b9dff 0%, #4facfe 50%, #6ec2ff 100%);
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.18),
                    inset 0 3px 8px rgba(0, 0, 0, 0.38),
                    inset 0 1px 2px rgba(0, 0, 0, 0.4),
                    inset 0 -1px 3px rgba(255, 255, 255, 0.6);
        transition: background 0.45s cubic-bezier(0.4, 0, 0.2, 1),
                    border-color 0.45s ease,
                    box-shadow 0.45s ease;
        user-select: none;
        -webkit-tap-highlight-color: transparent;
        padding: 0;
        display: inline-block;
        flex-shrink: 0;
        outline: none;
        vertical-align: middle;
    }

    .dark-theme .day-night-toggle {
        background: linear-gradient(180deg, #18202c 0%, #202836 50%, #151c27 100%);
        border-color: rgba(255, 255, 255, 0.18);
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.5),
                    inset 0 4px 9px rgba(0, 0, 0, 0.8),
                    inset 0 1px 3px rgba(0, 0, 0, 0.95),
                    inset 0 -1px 2px rgba(255, 255, 255, 0.1);
    }

    /* Concentric Halos / Ripples */
    .dnt-halos {
        position: absolute;
        top: 50%;
        left: calc(var(--dnt-pad) + var(--dnt-knob) / 2);
        transform: translate(-50%, -50%);
        pointer-events: none;
        transition: left 0.5s cubic-bezier(0.68, -0.15, 0.265, 1.2);
        z-index: 1;
    }

    .dark-theme .dnt-halos {
        left: calc(var(--dnt-w) - var(--dnt-pad) - var(--dnt-knob) / 2);
    }

    .dnt-halo {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        border-radius: 50%;
        pointer-events: none;
        transition: background 0.45s ease;
    }

    .dnt-halo-1 {
        width: 52px;
        height: 52px;
        background: rgba(255, 255, 255, 0.22);
    }
    .dnt-halo-2 {
        width: 82px;
        height: 82px;
        background: rgba(255, 255, 255, 0.13);
    }
    .dnt-halo-3 {
        width: 116px;
        height: 116px;
        background: rgba(255, 255, 255, 0.07);
    }

    .dark-theme .dnt-halo-1 {
        background: rgba(255, 255, 255, 0.08);
    }
    .dark-theme .dnt-halo-2 {
        background: rgba(255, 255, 255, 0.045);
    }
    .dark-theme .dnt-halo-3 {
        background: rgba(255, 255, 255, 0.02);
    }

    /* Night Sky Stars */
    .dnt-stars {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        z-index: 2;
        opacity: 0;
        transform: scale(0.3) rotate(-25deg);
        transition: opacity 0.3s ease, transform 0.45s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .dark-theme .dnt-stars {
        opacity: 1;
        transform: scale(1) rotate(0deg);
        transition: opacity 0.4s ease 0.06s, transform 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) 0.06s;
    }

    .dnt-star {
        position: absolute;
        fill: #ffffff;
        filter: drop-shadow(0 0 3px rgba(255, 255, 255, 0.9));
        animation: dntTwinkle 3s infinite ease-in-out;
    }

    .dnt-star-1 {
        width: 13px;
        height: 13px;
        top: 4px;
        left: 28px;
        animation-delay: 0.1s;
    }

    .dnt-star-2 {
        width: 9px;
        height: 9px;
        top: 15px;
        left: 10px;
        animation-delay: 0.8s;
    }

    .dnt-star-3 {
        width: 10px;
        height: 10px;
        bottom: 4px;
        left: 24px;
        animation-delay: 1.6s;
    }

    .dnt-dot {
        position: absolute;
        background: #ffffff;
        border-radius: 50%;
        box-shadow: 0 0 2.5px rgba(255, 255, 255, 0.95);
        animation: dntTwinkle 2.4s infinite ease-in-out;
    }

    .dnt-dot-1 {
        width: 2.5px;
        height: 2.5px;
        top: 8px;
        left: 17px;
        animation-delay: 0.4s;
    }

    .dnt-dot-2 {
        width: 3px;
        height: 3px;
        bottom: 10px;
        left: 12px;
        animation-delay: 1.3s;
    }

    .dnt-dot-3 {
        width: 2px;
        height: 2px;
        top: 16px;
        left: 42px;
        animation-delay: 2s;
    }

    @keyframes dntTwinkle {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.4; transform: scale(0.72); }
    }

    /* Day Sky Clouds */
    .dnt-clouds {
        position: absolute;
        right: 0;
        bottom: 0;
        width: 58px;
        height: 34px;
        pointer-events: none;
        z-index: 2;
        transform: translateY(0);
        opacity: 1;
        transition: transform 0.45s cubic-bezier(0.34, 1.35, 0.64, 1), opacity 0.35s ease;
    }

    .dark-theme .dnt-clouds {
        transform: translateY(125%);
        opacity: 0;
        transition: transform 0.4s cubic-bezier(0.55, 0, 0.1, 1), opacity 0.25s ease;
    }

    .dnt-cloud-back {
        position: absolute;
        right: -2px;
        bottom: -3px;
        width: 100%;
        height: 100%;
    }

    .dnt-cloud-back .dnt-puff {
        background: rgba(200, 230, 255, 0.72);
    }

    .dnt-cloud-front {
        position: absolute;
        right: 0;
        bottom: -3px;
        width: 100%;
        height: 100%;
    }

    .dnt-cloud-front .dnt-puff {
        background: #ffffff;
        box-shadow: 0 -1px 3px rgba(0, 0, 0, 0.08);
    }

    .dnt-puff {
        position: absolute;
        border-radius: 50%;
    }

    .puff-b1 { width: 28px; height: 28px; right: 0px; bottom: 8px; }
    .puff-b2 { width: 22px; height: 22px; right: 18px; bottom: 10px; }
    .puff-b3 { width: 18px; height: 18px; right: 33px; bottom: 3px; }

    .puff-f1 { width: 32px; height: 32px; right: -5px; bottom: 2px; }
    .puff-f2 { width: 25px; height: 25px; right: 14px; bottom: 4px; }
    .puff-f3 { width: 20px; height: 20px; right: 29px; bottom: 0px; }
    .puff-f4 { width: 16px; height: 16px; right: 41px; bottom: -2px; }

    /* The Sliding Knob (Sun / Moon) */
    .dnt-knob {
        position: absolute;
        top: var(--dnt-pad);
        left: var(--dnt-pad);
        width: var(--dnt-knob);
        height: var(--dnt-knob);
        border-radius: 50%;
        background: linear-gradient(135deg, #ffd738 0%, #f5a623 100%);
        box-shadow: 0 3px 8px rgba(0, 0, 0, 0.35),
                    inset 0 1.5px 2px rgba(255, 255, 255, 0.85),
                    inset 0 -2px 3px rgba(180, 100, 0, 0.3),
                    0 0 12px rgba(245, 166, 35, 0.5);
        transition: transform 0.5s cubic-bezier(0.68, -0.15, 0.265, 1.2),
                    background 0.4s ease,
                    box-shadow 0.4s ease;
        z-index: 5;
        transform: translateX(0);
    }

    .dark-theme .dnt-knob {
        transform: translateX(calc(var(--dnt-w) - var(--dnt-knob) - (var(--dnt-pad) * 2)));
        background: linear-gradient(135deg, #eaeff5 0%, #cfd8e3 100%);
        box-shadow: 0 3px 9px rgba(0, 0, 0, 0.55),
                    inset 0 1.5px 2px rgba(255, 255, 255, 0.95),
                    inset 0 -2px 3px rgba(80, 95, 115, 0.4),
                    0 0 8px rgba(234, 239, 245, 0.25);
    }

    /* Moon Craters */
    .dnt-craters {
        position: relative;
        width: 100%;
        height: 100%;
        border-radius: 50%;
        overflow: hidden;
        pointer-events: none;
    }

    .dnt-crater {
        position: absolute;
        border-radius: 50%;
        background: #90a1b3;
        box-shadow: inset 0 1.5px 2px rgba(0, 0, 0, 0.45),
                    0 0.5px 1px rgba(255, 255, 255, 0.4);
        opacity: 0;
        transform: scale(0.2);
        transition: opacity 0.25s ease, transform 0.3s ease;
    }

    .dark-theme .dnt-crater {
        opacity: 1;
        transform: scale(1);
        transition: opacity 0.35s ease 0.08s, transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) 0.12s;
    }

    .crater-1 {
        width: 6.5px;
        height: 6.5px;
        top: 5px;
        right: 7px;
    }

    .crater-2 {
        width: 10px;
        height: 10px;
        bottom: 4px;
        left: 5px;
    }

    .crater-3 {
        width: 7px;
        height: 7px;
        bottom: 6px;
        right: 4px;
    }

    /* Compact Switch Variant (For Mobile & Citizen Drawer) */
    .day-night-toggle.compact {
        --dnt-w: 62px;
        --dnt-h: 28px;
        --dnt-knob: 22px;
        --dnt-pad: 3px;
    }
    .day-night-toggle.compact .crater-1 { width: 5px; height: 5px; top: 4px; right: 5px; }
    .day-night-toggle.compact .crater-2 { width: 7.5px; height: 7.5px; bottom: 3px; left: 4px; }
    .day-night-toggle.compact .crater-3 { width: 5.5px; height: 5.5px; bottom: 4px; right: 3px; }
    .day-night-toggle.compact .dnt-star-1 { width: 10px; height: 10px; top: 3px; left: 19px; }
    .day-night-toggle.compact .dnt-star-2 { width: 7px; height: 7px; top: 11px; left: 8px; }
    .day-night-toggle.compact .dnt-star-3 { width: 8px; height: 8px; bottom: 3px; left: 17px; }
    .day-night-toggle.compact .dnt-clouds { width: 44px; height: 26px; }

    /* Legacy theme toggle fallback button */
    .theme-toggle-btn {
        display: none;
    }

    /* ===== DARK THEME SYSTEM ===== */
    .dark-theme body::before {
        background: rgba(15, 23, 42, 0.85) !important;
    }
    .dark-theme .sidebar-nav {
        background: rgba(15, 23, 42, 0.95);
        border-right: 1px solid rgba(255, 255, 255, 0.1);
        box-shadow: 4px 0 25px rgba(0, 0, 0, 0.5);
    }
    .dark-theme .logo-text h3 {
        color: #f8fafc;
    }
    .dark-theme .logo-text p {
        color: #94a3b8;
    }
    .dark-theme .nav-section-header {
        color: #94a3b8;
    }
    .dark-theme .nav-link {
        color: #cbd5e1;
    }
    .dark-theme .nav-link i {
        color: #6384d2;
    }
    .dark-theme .nav-link:hover {
        background: rgba(255, 255, 255, 0.08);
        color: #ffffff;
    }
    .dark-theme .nav-link.active {
        background: #3762c8;
        color: #ffffff;
        box-shadow: 0 4px 12px rgba(55, 98, 200, 0.4);
    }
    .dark-theme .user-welcome {
        color: #f8fafc;
    }
    .dark-theme .sidebar-divider {
        border-bottom: 2px solid rgba(255, 255, 255, 0.1);
    }
    .dark-theme .theme-toggle-container {
        background: rgba(255, 255, 255, 0.06);
        border-color: rgba(255, 255, 255, 0.12);
    }
    .dark-theme .theme-toggle-container:hover {
        background: rgba(255, 255, 255, 0.1);
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.4);
    }
    .dark-theme .theme-toggle-title {
        color: #94a3b8;
    }
    .dark-theme .theme-toggle-status {
        color: #f8fafc;
    }
    .dark-theme .theme-toggle-btn {
        background: rgba(255, 255, 255, 0.1);
        color: #f8fafc;
        border-color: rgba(255, 255, 255, 0.15);
    }
    .dark-theme .theme-toggle-btn:hover {
        background: rgba(255, 255, 255, 0.18);
    }

    /* Page-level container/card dark modes */
    .dark-theme .card {
        background: rgba(30, 41, 59, 0.9) !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        color: #f8fafc !important;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5) !important;
    }
    .dark-theme .card h1, .dark-theme .card h2, .dark-theme .card h3, .dark-theme .card h4, .dark-theme .card h5, .dark-theme .card h6,
    .dark-theme .dashboard-header h1, .dark-theme .dashboard-header h2, .dark-theme .main-content h1, .dark-theme .main-content h2,
    .dark-theme .main-content h3, .dark-theme .main-content h4, .dark-theme .main-content h5 {
        color: #f8fafc !important;
    }
    .dark-theme p, .dark-theme .card p, .dark-theme .text-muted, .dark-theme label, .dark-theme td, .dark-theme .form-label {
        color: #cbd5e1 !important;
    }

    /* Cards and Grids (Stats Cards - Keep colorful gradients in dark mode) */
    .dark-theme .stat-card {
        color: #ffffff !important;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4) !important;
    }
    .dark-theme .stat-card.assets,
    .dark-theme .stat-card.completed,
    .dark-theme .stat-card.fulfilled,
    .dark-theme .stat-card.green         { background: linear-gradient(135deg, #1a6b38, #25a259) !important; }

    .dark-theme .stat-card.incidents     { background: linear-gradient(135deg, #7a2f0d, #c0440f) !important; }

    .dark-theme .stat-card.maintenance,
    .dark-theme .stat-card.total,
    .dark-theme .stat-card.approved,
    .dark-theme .stat-card.blue          { background: linear-gradient(135deg, #1a3e7a, #2a5fc2) !important; }

    .dark-theme .stat-card.energy,
    .dark-theme .stat-card.forwarded,
    .dark-theme .stat-card.purple        { background: linear-gradient(135deg, #4c1d7a, #7c3dbf) !important; }

    .dark-theme .stat-card.pending,
    .dark-theme .stat-card.amber         { background: linear-gradient(135deg, #7a5c0d, #c4920e) !important; }

    .dark-theme .stat-card.progress,
    .dark-theme .stat-card.teal          { background: linear-gradient(135deg, #0d4a7a, #1580cc) !important; }

    .dark-theme .stat-card.emergency,
    .dark-theme .stat-card.failed,
    .dark-theme .stat-card.rejected,
    .dark-theme .stat-card.damaged       { background: linear-gradient(135deg, #7a1a1a, #c22a2a) !important; }

    .dark-theme .stat-card-icon,
    .dark-theme .stat-card .stat-icon,
    .dark-theme .stat-card .stat-card-icon,
    .dark-theme .stat-card .stat-icon-wrap {
        background: rgba(255, 255, 255, 0.18) !important;
        color: #ffffff !important;
    }

    .dark-theme .stat-card h3,
    .dark-theme .stat-card .stat-info h3 {
        color: #ffffff !important;
    }

    .dark-theme .stat-card p,
    .dark-theme .stat-card .stat-info p {
        color: rgba(255, 255, 255, 0.85) !important;
    }

    /* Tables */
    .dark-theme table {
        border-color: #334155 !important;
    }
    .dark-theme th {
        background-color: #1e293b !important;
        color: #f8fafc !important;
        border-bottom: 2px solid #334155 !important;
    }
    .dark-theme td {
        background-color: transparent !important;
        color: #cbd5e1 !important;
        border-bottom: 1px solid #334155 !important;
    }
    .dark-theme tr:hover td {
        background-color: rgba(255, 255, 255, 0.04) !important;
    }

    /* Inputs, Selects, and Textareas */
    .dark-theme input[type="text"], 
    .dark-theme input[type="email"], 
    .dark-theme input[type="password"], 
    .dark-theme input[type="number"], 
    .dark-theme input[type="date"], 
    .dark-theme select, 
    .dark-theme textarea {
        background: #1e293b !important;
        border: 1px solid #475569 !important;
        color: #f8fafc !important;
    }
    .dark-theme input::placeholder, .dark-theme textarea::placeholder {
        color: #64748b !important;
    }

    /* Lists and items */
    .dark-theme .feed-item, 
    .dark-theme .notification-item, 
    .dark-theme .advisory-card, 
    .dark-theme .project-card, 
    .dark-theme .list-group-item,
    .dark-theme .activity-item,
    .dark-theme .log-item {
        background: #1e293b !important;
        border-color: #334155 !important;
        color: #f8fafc !important;
    }

    /* Welcome back welcome-modal overrides */
    .dark-theme .welcome-modal-content {
        background: #1e293b !important;
        color: #f8fafc !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
    }
    .dark-theme .welcome-header h2 {
        color: #f8fafc !important;
    }
    .dark-theme .welcome-body h4 {
        border-bottom-color: #334155 !important;
        color: #f8fafc !important;
    }
    .dark-theme .welcome-updates-list li {
        border-bottom-color: #334155 !important;
    }
    .dark-theme .welcome-updates-list li strong {
        color: #f8fafc !important;
    }

    /* Confirm logout modal dark theme */
    .dark-theme .logout-modal-content {
        background: #1e293b !important;
        color: #f8fafc !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
    }
    .dark-theme .logout-modal-header h2 {
        color: #f8fafc !important;
    }
    .dark-theme .logout-modal-text {
        color: #cbd5e1 !important;
    }
    .dark-theme .logout-modal-btn.cancel-btn {
        background: #334155;
        color: #cbd5e1;
    }
    .dark-theme .logout-modal-btn.cancel-btn:hover {
        background: #475569;
        color: #f8fafc;
    }

    /* Box panels styling in dark theme */
    .dark-theme .box,
    .dark-theme .form-section,
    .dark-theme .table-section,
    .dark-theme .section-box,
    .dark-theme .filter-panel {
        background: #1e293b !important;
        border-color: #334155 !important;
        color: #f8fafc !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25) !important;
    }
    .dark-theme .box h3,
    .dark-theme .box h4,
    .dark-theme .form-section h3,
    .dark-theme .table-section h3,
    .dark-theme .section-box h3,
    .dark-theme .filter-panel h3 {
        color: #f8fafc !important;
        border-bottom-color: #334155 !important;
    }

    /* Item cards, feeds & notifications in dark mode */
    .dark-theme .item-card,
    .dark-theme .report-item,
    .dark-theme .notif-item {
        background: #0f172a !important;
        border: 1px solid #334155 !important;
        color: #f8fafc !important;
    }
    .dark-theme .notif-item.unread {
        background: #151f32 !important;
        border-left: 5px solid #3762c8 !important;
    }
    .dark-theme .item-card:hover,
    .dark-theme .report-item:hover {
        background: #151f32 !important;
    }
    .dark-theme .item-card h4,
    .dark-theme .advisory-title,
    .dark-theme .notif-content h4,
    .dark-theme .report-id,
    .dark-theme .timeline-title {
        color: #f8fafc !important;
    }
    .dark-theme .item-card p,
    .dark-theme .advisory-card p,
    .dark-theme .report-item p,
    .dark-theme .timeline-desc,
    .dark-theme #track-desc {
        color: #cbd5e1 !important;
    }
    .dark-theme .advisory-date,
    .dark-theme .notif-content span,
    .dark-theme .empty-state,
    .dark-theme .empty-state p {
        color: #94a3b8 !important;
    }
    .dark-theme .empty-state i {
        color: #475569 !important;
    }

    /* Timelines & Rating Stars */
    .dark-theme .timeline::before {
        background: #334155 !important;
    }
    .dark-theme .timeline-step::before {
        background: #475569 !important;
        border-color: #1e293b !important;
    }
    .dark-theme .timeline-step.active::before {
        background: #3762c8 !important;
    }
    .dark-theme #feedback-section {
        border-top-color: #334155 !important;
    }
    .dark-theme #feedback-section h4 {
        color: #f8fafc !important;
    }
    .dark-theme .star-btn {
        color: #475569 !important;
    }
    .dark-theme .star-btn.selected {
        color: #f1c40f !important;
    }

    /* Override hardcoded inline text & background colors in dark theme */
    .dark-theme [style*="color:#2c3e50"],
    .dark-theme [style*="color: #2c3e50"],
    .dark-theme [style*="color:rgb(44, 62, 80)"],
    .dark-theme [style*="color: rgb(44, 62, 80)"],
    .dark-theme [style*="color:#1e293b"],
    .dark-theme [style*="color: #1e293b"],
    .dark-theme [style*="color:#000000"],
    .dark-theme [style*="color:#000"],
    .dark-theme [style*="color: #000"] {
        color: #f8fafc !important;
    }
    .dark-theme [style*="color:#64748b"],
    .dark-theme [style*="color: #64748b"],
    .dark-theme [style*="color:#475569"],
    .dark-theme [style*="color: #475569"],
    .dark-theme [style*="color:#334155"],
    .dark-theme [style*="color: #334155"] {
        color: #cbd5e1 !important;
    }
    .dark-theme [style*="background:#f8fafc"],
    .dark-theme [style*="background: #f8fafc"],
    .dark-theme [style*="background:#f1f5f9"],
    .dark-theme [style*="background: #f1f5f9"],
    .dark-theme [style*="background:#f8f9fa"],
    .dark-theme [style*="background: #f8f9fa"] {
        background: #0f172a !important;
    }
    .dark-theme [style*="background:white"],
    .dark-theme [style*="background: white"],
    .dark-theme [style*="background:#ffffff"],
    .dark-theme [style*="background: #ffffff"] {
        background: #1e293b !important;
    }
    .dark-theme [style*="border-top:1px solid #edf2f7"],
    .dark-theme [style*="border-top: 1px solid #edf2f7"] {
        border-top-color: #334155 !important;
    }

    /* Badges in dark mode */
    .dark-theme .badge-citizen {
        background: rgba(14, 165, 233, 0.2) !important;
        color: #38bdf8 !important;
        border: 1px solid rgba(14, 165, 233, 0.4) !important;
    }
    .dark-theme .badge-employee {
        background: rgba(99, 102, 241, 0.2) !important;
        color: #a5b4fc !important;
        border: 1px solid rgba(99, 102, 241, 0.4) !important;
    }
    .dark-theme .badge-active {
        background: rgba(16, 185, 129, 0.2) !important;
        color: #34d399 !important;
        border: 1px solid rgba(16, 185, 129, 0.4) !important;
    }
    .dark-theme .badge-inactive {
        background: rgba(239, 68, 68, 0.2) !important;
        color: #f87171 !important;
        border: 1px solid rgba(239, 68, 68, 0.4) !important;
    }
    .dark-theme .badge-low {
        background: rgba(16, 185, 129, 0.2) !important;
        color: #34d399 !important;
        border: 1px solid rgba(16, 185, 129, 0.4) !important;
    }
    .dark-theme .badge-medium {
        background: rgba(14, 165, 233, 0.2) !important;
        color: #38bdf8 !important;
        border: 1px solid rgba(14, 165, 233, 0.4) !important;
    }
    .dark-theme .badge-high {
        background: rgba(245, 158, 11, 0.2) !important;
        color: #fbbf24 !important;
        border: 1px solid rgba(245, 158, 11, 0.4) !important;
    }
    .dark-theme .badge-emergency {
        background: rgba(239, 68, 68, 0.2) !important;
        color: #f87171 !important;
        border: 1px solid rgba(239, 68, 68, 0.4) !important;
    }

    /* Tab button overrides in dark theme */
    .dark-theme .tab-buttons {
        border-bottom-color: #334155 !important;
    }
    .dark-theme .tab-btn {
        color: #94a3b8 !important;
    }
    .dark-theme .tab-btn:hover {
        background: #334155 !important;
        color: #f8fafc !important;
    }
    .dark-theme .tab-btn.active {
        background: #3762c8 !important;
        color: #ffffff !important;
    }

    /* CRUD and Asset Dashboard specific components */
    .dark-theme .filter-container,
    .dark-theme .filter-panel,
    .dark-theme .table-section,
    .dark-theme .chart-box,
    .dark-theme .list-section {
        background: #1e293b !important;
        border-color: #334155 !important;
        color: #f8fafc !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25) !important;
    }
    .dark-theme .chart-box h3,
    .dark-theme .list-section h3,
    .dark-theme .table-section h3 {
        color: #f8fafc !important;
        border-bottom-color: #334155 !important;
    }
    
    /* General label styling in dark mode */
    .dark-theme label,
    .dark-theme .form-group label {
        color: #94a3b8 !important;
    }

    /* Notification Badge overrides */
    .dark-theme .notification-badge {
        background: #1e293b !important;
        border-color: #334155 !important;
        color: #cbd5e1 !important;
    }
    .dark-theme .notification-badge:hover {
        background: #334155 !important;
        color: #ffffff !important;
    }
    .dark-theme .badge-count {
        border-color: #1e293b !important;
    }

    /* Review comparisons table in dark theme */
    .dark-theme .review-field-name {
        color: #cbd5e1 !important;
    }
    .dark-theme .review-table th,
    .dark-theme .review-table td {
        border-bottom-color: #334155 !important;
    }

    /* Custom searchable dropdowns in dark theme */
    .dark-theme .custom-select-options {
        background: #1e293b !important;
        border-color: #334155 !important;
    }
    .dark-theme .custom-option {
        color: #cbd5e1 !important;
        border-bottom-color: #334155 !important;
    }
    .dark-theme .custom-option:hover {
        background-color: #334155 !important;
        color: #ffffff !important;
    }

    /* Dialog Modals in dark theme */
    .dark-theme .modal-content {
        background: #1e293b !important;
        color: #f8fafc !important;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5) !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
    }
    .dark-theme .modal-header,
    .dark-theme .modal-footer {
        background: #151f32 !important;
        border-color: #334155 !important;
    }
    .dark-theme .modal-header h3 {
        color: #f8fafc !important;
    }
    .dark-theme .modal-close {
        color: #cbd5e1 !important;
    }
    
    /* Support for file input */
    .dark-theme input[type="file"] {
        background: #1e293b !important;
        border-color: #475569 !important;
        color: #cbd5e1 !important;
    }




    /* ── Page header / subtitle text ── */
    .dark-theme .dashboard-header h1,
    .dark-theme .dashboard-header h2 {
        color: #f8fafc !important;
    }
    .dark-theme .subtitle,
    .dark-theme .dashboard-header .subtitle {
        color: #94a3b8 !important;
    }

    /* ── Filter bar (CPRF & CRUD) ── */
    .dark-theme .filter-bar label {
        color: #cbd5e1 !important;
    }
    .dark-theme .filter-bar select {
        background: #1e293b !important;
        border-color: #475569 !important;
        color: #f8fafc !important;
    }

    /* ── Buttons default (white background) ── */
    .dark-theme .btn:not(.btn-primary):not(.btn-danger):not(.btn-success):not(.btn-warning) {
        background: #1e293b !important;
        border-color: #475569 !important;
        color: #cbd5e1 !important;
    }
    .dark-theme .btn-outline {
        background: transparent !important;
        border-color: #475569 !important;
        color: #94a3b8 !important;
    }
    .dark-theme .btn-outline:hover {
        background: #334155 !important;
        color: #f8fafc !important;
    }

    /* ── Pagination ── */
    .dark-theme .page-link {
        background: #1e293b !important;
        border-color: #475569 !important;
        color: #94a3b8 !important;
    }
    .dark-theme .page-link:hover {
        background: #334155 !important;
        color: #f8fafc !important;
        border-color: #3762c8 !important;
    }
    .dark-theme .page-link.active {
        background: #3762c8 !important;
        color: #fff !important;
        border-color: #3762c8 !important;
    }
    .dark-theme .pagination-info {
        color: #94a3b8 !important;
    }

    /* ── Hub-level tabs (CPRF Integration Hub) ── */
    .dark-theme .hub-tabs {
        border-bottom-color: #334155 !important;
    }
    .dark-theme .hub-tab {
        color: #94a3b8 !important;
    }
    .dark-theme .hub-tab:hover {
        background: #1e293b !important;
        color: #f8fafc !important;
    }
    .dark-theme .hub-tab.active {
        background: #1e293b !important;
        border-color: #334155 !important;
        border-bottom-color: #1e293b !important;
        color: #34d399 !important;
    }
    .dark-theme .hub-tab .count-chip {
        background: #312e81 !important;
        color: #a5b4fc !important;
    }

    /* ── Asset request table ── */
    .dark-theme .req-table th {
        background: #151f32 !important;
        color: #94a3b8 !important;
        border-bottom-color: #334155 !important;
    }
    .dark-theme .req-table td {
        color: #cbd5e1 !important;
        border-bottom-color: #334155 !important;
    }
    .dark-theme .req-table td strong {
        color: #f8fafc !important;
    }
    .dark-theme .req-table td small {
        color: #64748b !important;
    }
    .dark-theme .req-table td em {
        color: #64748b !important;
    }
    .dark-theme .req-table tr:hover td {
        background: rgba(255,255,255,0.04) !important;
    }

    /* ── Action forms (CPRF approve/reject forms) ── */
    .dark-theme .action-form {
        background: #151f32 !important;
        border-color: #334155 !important;
    }
    .dark-theme .action-form textarea {
        background: #1e293b !important;
        border-color: #475569 !important;
        color: #f8fafc !important;
    }
    .dark-theme .action-form select {
        background: #1e293b !important;
        border-color: #475569 !important;
        color: #f8fafc !important;
    }

    /* ── Facility list panel (CPRF Assignments tab) ── */
    .dark-theme .facility-list {
        background: #1e293b !important;
        border-color: #334155 !important;
    }
    .dark-theme .facility-list .search {
        background: #1e293b !important;
        border-bottom-color: #334155 !important;
    }
    .dark-theme .facility-list input[type=search],
    .dark-theme .panel-toolbar input[type=search] {
        background: #151f32 !important;
        border-color: #475569 !important;
        color: #f8fafc !important;
    }
    .dark-theme .facility-list input[type=search]::placeholder,
    .dark-theme .panel-toolbar input[type=search]::placeholder {
        color: #64748b !important;
    }
    .dark-theme .facility-item {
        background: #151f32 !important;
        border-color: #334155 !important;
        color: #cbd5e1 !important;
    }
    .dark-theme .facility-item:hover {
        background: #0f3d2e !important;
        border-color: #10b981 !important;
    }
    .dark-theme .facility-item.active {
        background: #14532d !important;
        border-color: #059669 !important;
    }
    .dark-theme .facility-item .name {
        color: #f8fafc !important;
    }
    .dark-theme .facility-item .meta {
        color: #64748b !important;
    }

    /* ── Sub-tabs inside Facility Assignments ── */
    .dark-theme .tabs {
        border-bottom-color: #334155 !important;
    }
    .dark-theme .tab {
        color: #94a3b8 !important;
    }
    .dark-theme .tab:hover {
        background: #1e293b !important;
        color: #f8fafc !important;
    }
    .dark-theme .tab.active {
        background: #1e293b !important;
        border-color: #334155 !important;
        border-bottom-color: #1e293b !important;
        color: #34d399 !important;
    }
    .dark-theme .tab .count-chip {
        background: #0c4a6e !important;
        color: #7dd3fc !important;
    }

    /* ── Generic table with .table class (Facility Assignments tab) ── */
    .dark-theme .table {
        background: #1e293b !important;
        border-color: #334155 !important;
    }
    .dark-theme .table th {
        background: #151f32 !important;
        color: #94a3b8 !important;
        border-bottom-color: #334155 !important;
    }
    .dark-theme .table td {
        color: #cbd5e1 !important;
        border-bottom-color: #334155 !important;
    }
    .dark-theme .table tr:hover td {
        background: rgba(255,255,255,0.04) !important;
    }
    .dark-theme .code {
        color: #93c5fd !important;
    }
    .dark-theme .muted {
        color: #64748b !important;
    }

    /* ── Event cards (activity log) ── */
    .dark-theme .event-card {
        background: #1e293b !important;
        border-color: #334155 !important;
    }
    .dark-theme .event-card .ref {
        color: #f8fafc !important;
    }

    /* ── Flash messages in dark theme ── */
    .dark-theme .flash.success {
        background: #064e3b !important;
        color: #6ee7b7 !important;
        border-color: #065f46 !important;
    }
    .dark-theme .flash.error {
        background: #450a0a !important;
        color: #fca5a5 !important;
        border-color: #7f1d1d !important;
    }
    .dark-theme .flash.warning {
        background: #451a03 !important;
        color: #fcd34d !important;
        border-color: #78350f !important;
    }

    /* ── Accordion table rows (Assets CRUD) ── */
    .dark-theme .group-header-row {
        background: #1a2540 !important;
    }
    .dark-theme .group-header-row:hover,
    .dark-theme .group-header-row.expanded {
        background: #1e2d52 !important;
        border-left-color: #3b82f6 !important;
    }
    .dark-theme .category-label {
        color: #f1f5f9 !important;
    }
    .dark-theme .accordion-icon {
        background: #1e3a6e !important;
        color: #93c5fd !important;
        border-color: #2d5099 !important;
    }
    .dark-theme .group-header-row.expanded .accordion-icon {
        background: #3762c8 !important;
        color: #ffffff !important;
        border-color: #3762c8 !important;
    }
    .dark-theme .asset-count-badge {
        background: #1e3a6e !important;
        color: #93c5fd !important;
        border-color: #2d5099 !important;
    }
    .dark-theme .expand-hint {
        color: #64748b !important;
    }
    .dark-theme .group-header-row:hover .expand-hint,
    .dark-theme .group-header-row.expanded .expand-hint {
        color: #93c5fd !important;
    }
    .dark-theme .child-table-wrapper {
        background: #151f32 !important;
        border-top-color: #334155 !important;
        border-bottom-color: #2d5099 !important;
    }
    .dark-theme .child-table thead tr {
        background: #1a2540 !important;
    }
    .dark-theme .child-table thead th {
        color: #94a3b8 !important;
        border-bottom-color: #334155 !important;
    }
    .dark-theme .child-table tbody tr {
        border-bottom-color: #334155 !important;
    }
    .dark-theme .child-table tbody tr:hover {
        background: #1e2d52 !important;
    }
    .dark-theme .child-table td {
        color: #cbd5e1 !important;
    }
    .dark-theme .child-asset-row.search-highlight {
        background: #3b2f00 !important;
        border-left-color: #ca8a04 !important;
    }
    /* ── Offshoot / split rows (Assets CRUD dark mode) ── */
    .dark-theme .child-asset-row.is-offshoot {
        background: #2c1f06 !important;
        border-left-color: #b45309 !important;
    }
    .dark-theme .child-asset-row.is-offshoot:hover {
        background: #3b2a08 !important;
    }
    .dark-theme .offshoot-badge {
        background: #3b2a08 !important;
        border-color: #854d0e !important;
        color: #fbbf24 !important;
    }
    .dark-theme .offshoot-merge-notice {
        background: #1c1007 !important;
        border-color: #78350f !important;
        color: #fcd34d !important;
    }
    .dark-theme .offshoot-merge-notice strong {
        color: #fbbf24 !important;
    }
    .dark-theme .split-panel {
        background: #052e16 !important;
        border-color: #166534 !important;
    }
    .dark-theme .split-panel label,
    .dark-theme .split-panel label.panel-title {
        color: #4ade80 !important;
    }
    .dark-theme .split-panel p.panel-hint {
        color: #86efac !important;
    }
    .dark-theme .split-panel [style*="color:#166534"],
    .dark-theme .split-panel div[style] {
        color: #86efac !important;
    }

    /* ── Form control inputs in pages (CRUD filter bar) ── */
    .dark-theme .form-control {
        background: #1e293b !important;
        border-color: #475569 !important;
        color: #f8fafc !important;
    }
    .dark-theme .form-control::placeholder {
        color: #64748b !important;
    }
    .dark-theme .form-control:focus {
        border-color: #3762c8 !important;
        box-shadow: 0 0 0 3px rgba(55, 98, 200, 0.25) !important;
    }

    /* ── Form grid in CPRF tab 2 ── */
    .dark-theme .form-grid label {
        color: #94a3b8 !important;
    }
    .dark-theme .form-grid input,
    .dark-theme .form-grid select,
    .dark-theme .form-grid textarea {
        background: #1e293b !important;
        border-color: #475569 !important;
        color: #f8fafc !important;
    }

    /* ── Icon action buttons (view/edit/delete) ── */
    .dark-theme .btn-icon-view {
        background: #0c3554 !important;
        color: #38bdf8 !important;
    }
    .dark-theme .btn-icon-view:hover {
        background: #0e4a75 !important;
    }
    .dark-theme .btn-icon-edit {
        background: #3b2800 !important;
        color: #fbbf24 !important;
    }
    .dark-theme .btn-icon-edit:hover {
        background: #4d3400 !important;
    }
    .dark-theme .btn-icon-delete {
        background: #450a0a !important;
        color: #f87171 !important;
    }
    .dark-theme .btn-icon-delete:hover {
        background: #5c0f0f !important;
    }

    /* ── No-action text ── */
    .dark-theme .no-action {
        color: #475569 !important;
    }

    /* ── Empty state ── */
    .dark-theme .empty-state {
        color: #64748b !important;
    }

    /* ── Alert messages (CRUD page) ── */
    .dark-theme .alert-success {
        background: #064e3b !important;
        color: #6ee7b7 !important;
        border-color: #065f46 !important;
    }
    .dark-theme .alert-error {
        background: #450a0a !important;
        color: #fca5a5 !important;
        border-color: #7f1d1d !important;
    }

    /* ===== SIDEBAR DROPDOWN SYSTEM ===== */
    .sidebar-dropdown-wrapper {
        list-style: none;
        width: 100%;
        margin: 2px 0;
    }
    .sidebar-dropdown-toggle {
        display: flex;
        align-items: center;
        width: 100%;
        background: transparent;
        border: none;
        outline: none;
        text-align: left;
        gap: 12px;
        color: #1e293b;
        padding: 10px 20px;
        font-family: 'Poppins', sans-serif;
        font-weight: 500;
        font-size: 14px;
        cursor: pointer;
        border-radius: 8px;
        transition: all 0.3s ease;
    }
    .sidebar-dropdown-toggle i.icon-main {
        width: 20px;
        font-size: 15px;
        color: #6384d2;
        flex-shrink: 0;
        text-align: center;
    }
    .sidebar-dropdown-toggle .chevron-icon {
        margin-left: auto;
        font-size: 11px;
        color: #94a3b8;
        transition: transform 0.3s ease;
    }
    .sidebar-dropdown-toggle .chevron-icon.rotate {
        transform: rotate(90deg);
    }
    .sidebar-dropdown-toggle:hover {
        background: rgba(151, 164, 194, 0.25);
        color: #3762c8;
    }
    .sidebar-dropdown-toggle.active {
        color: #3762c8;
        font-weight: 600;
    }
    .sidebar-dropdown-menu {
        list-style: none;
        padding: 0 0 0 16px;
        margin: 0;
        max-height: 0;
        overflow: hidden;
        opacity: 0;
        transition: max-height 0.3s ease-out, opacity 0.2s ease-out;
    }
    .sidebar-dropdown-menu.open {
        max-height: 200px;
        opacity: 1;
        transition: max-height 0.35s ease-in-out, opacity 0.4s ease-in-out;
        padding-bottom: 6px;
    }
    .sidebar-dropdown-menu li {
        width: 100%;
        margin: 2px 0;
    }
    .dropdown-link {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #475569;
        text-decoration: none;
        padding: 8px 16px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 500;
        transition: all 0.25s ease;
    }
    .dropdown-link i {
        font-size: 12px;
        color: #94a3b8;
        width: 14px;
        text-align: center;
    }
    .dropdown-link:hover {
        background: rgba(151, 164, 194, 0.15);
        color: #3762c8;
    }
    .dropdown-link.active {
        background: rgba(55, 98, 200, 0.1);
        color: #3762c8;
        font-weight: 600;
    }
    .dropdown-link.active i {
        color: #3762c8;
    }

    /* Dark Theme Support */
    .dark-theme .sidebar-dropdown-toggle {
        color: #cbd5e1;
    }
    .dark-theme .sidebar-dropdown-toggle:hover {
        background: rgba(255, 255, 255, 0.08);
        color: #ffffff;
    }
    .dark-theme .sidebar-dropdown-toggle.active {
        color: #6384d2;
    }
    .dark-theme .dropdown-link {
        color: #94a3b8;
    }
    .dark-theme .dropdown-link:hover {
        background: rgba(255, 255, 255, 0.05);
        color: #ffffff;
    }
    .dark-theme .dropdown-link.active {
        background: rgba(99, 132, 210, 0.15);
        color: #6384d2;
    }
    .dark-theme .dropdown-link.active i {
        color: #6384d2;
    }

    /* Collapsed Sidebar overrides for dropdowns */
    .sidebar-nav.collapsed .sidebar-dropdown-toggle span,
    .sidebar-nav.collapsed .sidebar-dropdown-toggle .chevron-icon,
    .sidebar-nav.collapsed .sidebar-dropdown-menu {
        display: none !important;
    }
    .sidebar-nav.collapsed .sidebar-dropdown-toggle {
        justify-content: center;
        padding: 12px 0;
    }
    .sidebar-nav.collapsed .sidebar-dropdown-toggle i.icon-main {
        margin-right: 0;
        width: auto;
    }

    /* ── body overlay in dark mode ── */
    .dark-theme body::before {
        background: rgba(5, 10, 22, 0.75) !important;
    }

</style>

<!-- ===== MOBILE TOPBAR ===== -->
<div class="mobile-topbar" id="mobile-topbar">
    <div class="mobile-topbar-left">
        <button type="button" class="mobile-nav-toggle" id="mobile-nav-toggle" aria-label="Open Navigation Menu">
            <i class="fas fa-bars"></i>
        </button>
        <a href="<?php echo $sidebarBase; ?><?php echo $userType === 'employee' ? 'utilities_dashboard.php' : 'citizen.php'; ?>" class="mobile-topbar-brand">
            <img src="<?php echo $sidebarBase; ?>assets/images/logocityhall.png" alt="Logo" onerror="this.style.display='none';">
            <span>UMAN</span>
        </a>
    </div>
    <div class="mobile-topbar-right">
        <button type="button" class="mobile-theme-toggle" onclick="toggleTheme()" aria-label="Toggle Dark/Light Mode" title="Toggle Dark/Light Mode">
            <?php echo renderDayNightToggle('compact', 'mobile-theme-switch'); ?>
        </button>
    </div>
</div>

<?php if ($userType === 'employee'): ?>
<!-- ===== DESKTOP TOP HEADER BAR (Employee Only) ===== -->
<header class="app-topbar" id="app-topbar" role="banner">
    <!-- Left: Greeting + Live Clock -->
    <div class="topbar-left">
        <div class="topbar-clock-block">
            <div class="topbar-clock-time" id="topbar-clock">00:00:00</div>
            <div class="topbar-clock-date" id="topbar-date">Loading...</div>
        </div>
    </div>

    <!-- Right: Action buttons + Account Avatar -->
    <div class="topbar-actions">
        <!-- Dark Mode Toggle -->
        <button type="button" class="topbar-theme-wrap" onclick="toggleTheme()" title="Toggle Dark/Light Mode" aria-label="Toggle Dark/Light Mode">
            <?php echo renderDayNightToggle('compact', 'topbar-theme-switch'); ?>
        </button>

        <div class="topbar-divider"></div>

        <!-- Notification Bell -->
        <button type="button" class="topbar-btn" id="topbar-notif-btn" aria-label="Notifications" title="Notifications">
            <i class="fas fa-bell"></i>
            <span class="topbar-notif-badge" id="topbar-notif-indicator" style="display:none;"></span>
        </button>

        <!-- Settings -->
        <button type="button" class="topbar-btn" id="topbar-settings-btn" aria-label="Settings" title="Settings (coming soon)" disabled style="opacity:0.5; cursor:not-allowed;">
            <i class="fas fa-cog"></i>
        </button>

        <div class="topbar-divider"></div>

        <!-- Account Avatar (clickable → popover) -->
        <div class="topbar-account-wrap" id="topbar-account-wrap">
            <button type="button" class="topbar-account-btn" id="topbar-account-btn"
                    aria-label="Account Info" title="Account Info"
                    onclick="toggleAccountPopover(event)" aria-haspopup="true" aria-expanded="false">
                <div class="topbar-acct-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <i class="fas fa-chevron-down topbar-acct-caret" id="topbar-acct-caret"></i>
            </button>

            <!-- Account Popover -->
            <div class="topbar-account-popover" id="topbar-account-popover" role="dialog" aria-label="Account Information">
                <div class="tpop-header">
                    <div class="tpop-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="tpop-info">
                        <div class="tpop-name"><?php echo $userName; ?></div>
                        <div class="tpop-role"><i class="fas fa-shield-alt"></i> Employee</div>
                    </div>
                </div>
                <div class="tpop-divider"></div>
                <div class="tpop-details">
                    <div class="tpop-row">
                        <span class="tpop-label"><i class="fas fa-id-badge"></i> Account</span>
                        <span class="tpop-value"><?php echo $userName; ?></span>
                    </div>
                    <div class="tpop-row">
                        <span class="tpop-label"><i class="fas fa-user-tag"></i> Role</span>
                        <span class="tpop-value">Employee</span>
                    </div>
                    <div class="tpop-row">
                        <span class="tpop-label"><i class="fas fa-circle tpop-online"></i> Status</span>
                        <span class="tpop-value tpop-status-active">Active</span>
                    </div>
                </div>
                <div class="tpop-divider"></div>
                <button type="button" class="tpop-logout-btn" onclick="confirmLogout()">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </div>
        </div>
    </div>
</header>
<?php endif; ?>

<!-- Backdrop Overlay for Mobile Drawer -->
<div class="sidebar-backdrop" id="sidebar-backdrop"></div>

<!-- ===== SIDEBAR HTML ===== -->
<nav class="sidebar-nav" id="sidebar-nav" role="navigation" aria-label="Utilities Navigation">
    <div class="sidebar-header">
        <button class="collapse-btn" id="collapse-btn" aria-label="Toggle sidebar" aria-pressed="false">&#8249;</button>

        <div class="site-logo">
            <img src="<?php echo $sidebarBase; ?>assets/images/logocityhall.png" alt="LGU Logo"
                 id="sidebar-logo-img"
                 onerror="this.style.display='none'; document.getElementById('sidebar-logo-fallback').style.display='grid';">
            <span id="sidebar-logo-fallback" style="display:none; width:45px; height:45px; border-radius:10px; background:linear-gradient(135deg,#3762c8,#6384d2); place-items:center; color:#fff; font-size:20px; box-shadow:0 4px 10px rgba(0,0,0,0.15);">
                <i class="fas fa-city"></i>
            </span>
            <div class="logo-text">
                <h3>Utilities Management</h3>
                <p>Welcome, <?php echo $userName; ?></p>
            </div>
        </div>

        <div class="sidebar-divider"></div>
    </div>

    <div class="sidebar-menu-scrollable">
        <ul class="nav-list">
            <li class="nav-section-header">MAIN NAVIGATION</li>
            <?php if ($userType === 'employee'): ?>
            <li>
                <a href="<?php echo $sidebarBase; ?>utilities_dashboard.php" class="nav-link<?php echo sidebarActive('utilities_dashboard.php', $currentPage); ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="link-label">Dashboard</span>
                </a>
            </li>

            <!-- Asset Management Dropdown -->
            <?php 
            $isAssetActive = in_array($currentPage, ['assets_dashboard.php', 'assets_crud.php', 'cprf_integration.php']);
            ?>
            <li class="sidebar-dropdown-wrapper">
                <button type="button" class="sidebar-dropdown-toggle<?php echo $isAssetActive ? ' active' : ''; ?>" onclick="toggleSidebarDropdown(this)">
                    <i class="fas fa-boxes icon-main"></i>
                    <span class="link-label">Asset Management</span>
                    <i class="fas fa-chevron-right chevron-icon<?php echo $isAssetActive ? ' rotate' : ''; ?>"></i>
                </button>
                <ul class="sidebar-dropdown-menu<?php echo $isAssetActive ? ' open' : ''; ?>">
                    <li>
                        <a href="<?php echo $sidebarBase; ?>assets_dashboard.php" class="dropdown-link<?php echo $currentPage === 'assets_dashboard.php' ? ' active' : ''; ?>">
                            <i class="fas fa-chart-line"></i>
                            <span>Asset Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $sidebarBase; ?>assets_crud.php" class="dropdown-link<?php echo ($currentPage === 'assets_crud.php') ? ' active' : ''; ?>">
                            <i class="fas fa-warehouse"></i>
                            <span>Asset Inventory</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $sidebarBase; ?>cprf_integration.php" class="dropdown-link<?php echo sidebarActive('cprf_integration.php', $currentPage); ?>">
                            <i class="fas fa-exchange-alt"></i>
                            <span>Asset Requests</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- Operations Dropdown -->
            <?php 
            $isOpsActive = (strpos($currentPage, 'maintenance_') === 0) || $currentPage === 'upad_integration.php';
            ?>
            <li class="sidebar-dropdown-wrapper">
                <button type="button" class="sidebar-dropdown-toggle<?php echo $isOpsActive ? ' active' : ''; ?>" onclick="toggleSidebarDropdown(this)">
                    <i class="fas fa-tasks icon-main"></i>
                    <span class="link-label">Operations</span>
                    <i class="fas fa-chevron-right chevron-icon<?php echo $isOpsActive ? ' rotate' : ''; ?>"></i>
                </button>
                <ul class="sidebar-dropdown-menu<?php echo $isOpsActive ? ' open' : ''; ?>">
                    <li>
                        <a href="<?php echo $sidebarBase; ?>maintenance_dashboard.php" class="dropdown-link<?php echo (strpos($currentPage, 'maintenance_') === 0) ? ' active' : ''; ?>">
                            <i class="fas fa-tools"></i>
                            <span>Maintenance</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $sidebarBase; ?>upad_integration.php" class="dropdown-link<?php echo sidebarActive('upad_integration.php', $currentPage); ?>">
                            <i class="fas fa-city"></i>
                            <span>Inspection Requests</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- Utilities Dropdown -->
            <?php 
            $isUtilsActive = (strpos($currentPage, 'energy_') === 0) || (strpos($currentPage, 'water_') === 0);
            ?>
            <li class="sidebar-dropdown-wrapper">
                <button type="button" class="sidebar-dropdown-toggle<?php echo $isUtilsActive ? ' active' : ''; ?>" onclick="toggleSidebarDropdown(this)">
                    <i class="fas fa-tint icon-main"></i>
                    <span class="link-label">Utilities</span>
                    <i class="fas fa-chevron-right chevron-icon<?php echo $isUtilsActive ? ' rotate' : ''; ?>"></i>
                </button>
                <ul class="sidebar-dropdown-menu<?php echo $isUtilsActive ? ' open' : ''; ?>">
                    <li>
                        <a href="<?php echo $sidebarBase; ?>energy_dashboard.php" class="dropdown-link<?php echo (strpos($currentPage, 'energy_') === 0) ? ' active' : ''; ?>">
                            <i class="fas fa-bolt"></i>
                            <span>Energy Management</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $sidebarBase; ?>water_dashboard.php" class="dropdown-link<?php echo (strpos($currentPage, 'water_') === 0) ? ' active' : ''; ?>">
                            <i class="fas fa-tint"></i>
                            <span>Water Management</span>
                        </a>
                    </li>
                </ul>
            </li>

            <li>
                <a href="<?php echo $sidebarBase; ?>ai_analytics.php" class="nav-link<?php echo sidebarActive('ai_analytics.php', $currentPage); ?>">
                    <i class="fas fa-brain"></i>
                    <span class="link-label">AI Analytics</span>
                </a>
            </li>

<!-- ===== REPORTS & EXPORTS SECTION ===== -->
<li class="nav-section-header">REPORTS & EXPORTS</li>
<li>
    <a href="<?php echo $sidebarBase; ?>export_dashboard.php" class="nav-link<?php echo $currentPage === 'export_dashboard.php' ? ' active' : ''; ?>">
        <i class="fas fa-file-export"></i>
        <span class="link-label">Export Data</span>
    </a>
</li>
<li>
    <a href="<?php echo $sidebarBase; ?>admin/users.php" class="nav-link<?php echo $currentPage === 'users.php' ? ' active' : ''; ?>">
        <i class="fas fa-users-cog"></i>
        <span class="link-label">User Management</span>
    </a>
</li>

            <?php else: ?>
            <li>
                <a href="<?php echo $sidebarBase; ?>citizen.php" class="nav-link<?php echo sidebarActive('citizen.php', $currentPage); ?>">
                    <i class="fas fa-home"></i>
                    <span class="link-label">Home Dashboard</span>
                </a>
            </li>
            <li>
                <a href="<?php echo $sidebarBase; ?>citizen_asset_request.php" class="nav-link<?php echo sidebarActive('citizen_asset_request.php', $currentPage); ?>">
                    <i class="fas fa-boxes-stacked"></i>
                    <span class="link-label">Asset Requests</span>
                </a>
            </li>

            <li>
                <a href="<?php echo $sidebarBase; ?>citizen_notifications.php" class="nav-link<?php echo sidebarActive('citizen_notifications.php', $currentPage); ?>">
                    <i class="fas fa-bell"></i>
                    <span class="link-label">Notifications</span>
                </a>
            </li>
            <li>
                <a href="<?php echo $sidebarBase; ?>citizen_profile.php" class="nav-link<?php echo sidebarActive('citizen_profile.php', $currentPage); ?>">
                    <i class="fas fa-user-cog"></i>
                    <span class="link-label">Profile Settings</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="sidebar-bottom">
        <div class="sidebar-divider"></div>
        <div class="user-info">
            <?php if ($userType !== 'employee'): ?>
            <div class="user-welcome"><i class="fas fa-user-circle" style="margin-right:6px; color:#6384d2;"></i><?php echo $userName; ?></div>
            <div class="theme-toggle-container" onclick="toggleTheme()" title="Toggle Dark/Light Mode" role="button" tabindex="0" onkeydown="if(event.key==='Enter'||event.key===' ')toggleTheme()">
                <div class="theme-toggle-meta">
                    <span class="theme-toggle-title">Theme</span>
                    <span class="theme-toggle-status" id="theme-toggle-text">Light Mode</span>
                </div>
                <?php echo renderDayNightToggle('', 'sidebar-theme-switch'); ?>
            </div>
            <?php endif; ?>
            <button class="logout-btn" onclick="confirmLogout()" title="Logout">
                <i class="fas fa-sign-out-alt"></i> <span class="logout-text">Logout</span>
            </button>
        </div>
    </div>
</nav>

<?php if ($userType !== 'employee'): ?>
<!-- ===== CITIZEN BOTTOM NAVIGATION BAR (Expandable Island) ===== -->
<div class="citizen-bottom-nav" id="citizenBottomNav" role="navigation" aria-label="Citizen Bottom Navigation">

    <!-- Expanded menu panel (hidden until Menu is clicked) -->
    <div class="citizen-bottom-menu-panel" id="citizenMenuPanel">
        <!-- Drag handle for sliding down -->
        <div class="citizen-sheet-handle"></div>
        <!-- User info header -->
        <div class="citizen-panel-user">
            <div class="citizen-panel-avatar"><i class="fas fa-user"></i></div>
            <div>
                <div class="citizen-panel-name"><?php echo $userName; ?></div>
                <div class="citizen-panel-role"><i class="fas fa-shield-alt" style="margin-right:4px; color:#3762c8;"></i>Resident Portal</div>
            </div>
        </div>
        <!-- Menu items -->
        <div class="citizen-panel-items">
            <a href="<?php echo $sidebarBase; ?>citizen_notifications.php" class="citizen-panel-item">
                <div class="citizen-panel-item-left">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                </div>
                <?php if ($unreadNotifCount > 0): ?>
                    <span style="background:#ef4444; color:#fff; font-size:11px; padding:2px 8px; border-radius:99px; font-weight:700;"><?php echo $unreadNotifCount; ?> new</span>
                <?php else: ?>
                    <i class="fas fa-chevron-right" style="color:#94a3b8; font-size:12px;"></i>
                <?php endif; ?>
            </a>
            <a href="<?php echo $sidebarBase; ?>citizen_profile.php" class="citizen-panel-item">
                <div class="citizen-panel-item-left">
                    <i class="fas fa-user-cog"></i>
                    <span>Profile Settings</span>
                </div>
                <i class="fas fa-chevron-right" style="color:#94a3b8; font-size:12px;"></i>
            </a>
            <button type="button" class="citizen-panel-item" onclick="toggleTheme()">
                <div class="citizen-panel-item-left">
                    <?php echo renderDayNightToggle('compact', 'citizen-sheet-theme-switch'); ?>
                    <span id="citizen-sheet-theme-text" style="margin-left: 10px;">Dark Mode</span>
                </div>
                <span style="font-size:12px; color:#94a3b8; font-weight:500;">Toggle</span>
            </button>
            <button type="button" class="citizen-panel-item logout-item" onclick="confirmLogout()">
                <div class="citizen-panel-item-left">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </div>
                <i class="fas fa-chevron-right" style="font-size:12px;"></i>
            </button>
        </div>
    </div>

    <div class="citizen-bottom-menu-divider"></div>

    <!-- Nav items row (always visible) -->
    <div class="citizen-bottom-nav-items">
        <a href="<?php echo $sidebarBase; ?>citizen.php" class="citizen-bottom-item<?php echo $currentPage === 'citizen.php' ? ' active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Home</span>
        </a>
        <a href="<?php echo $sidebarBase; ?>citizen_asset_request.php" class="citizen-bottom-item<?php echo $currentPage === 'citizen_asset_request.php' ? ' active' : ''; ?>">
            <i class="fas fa-boxes-stacked"></i>
            <span>Requests</span>
        </a>
        <button type="button" class="citizen-bottom-item<?php echo ($currentPage === 'citizen_notifications.php' || $currentPage === 'citizen_profile.php') ? ' active' : ''; ?>" id="citizenMenuBtn" onclick="toggleCitizenMenuSheet()" aria-label="Open Menu" aria-expanded="false">
            <i class="fas fa-bars" id="citizenMenuIcon"></i>
            <span>Menu</span>
            <?php if ($unreadNotifCount > 0): ?>
                <span class="citizen-bottom-badge"><?php echo $unreadNotifCount; ?></span>
            <?php endif; ?>
        </button>
    </div>
</div>
<?php endif; ?>


<!-- Logout Confirmation Modal -->
<div id="logoutConfirmModal" class="logout-modal">
    <div class="logout-modal-content">
        <div class="logout-modal-header">
            <i class="fas fa-sign-out-alt"></i>
            <h2>Confirm Logout</h2>
        </div>
        <p class="logout-modal-text">Are you sure you want to log out of the LGU Utilities System?</p>
        <div class="logout-modal-actions">
            <button type="button" class="logout-modal-btn cancel-btn" onclick="closeLogoutModal()">Cancel</button>
            <a href="<?php echo $sidebarBase; ?>logout.php" class="logout-modal-btn confirm-btn">Logout</a>
        </div>
    </div>
</div>

<script>
function toggleSidebarDropdown(button) {
    const menu = button.nextElementSibling;
    const chevron = button.querySelector('.chevron-icon');
    if (!menu) return;
    
    const isOpen = menu.classList.toggle('open');
    if (chevron) {
        chevron.classList.toggle('rotate', isOpen);
    }
}

function openCitizenMenuSheet() {
    const nav = document.getElementById('citizenBottomNav');
    const btn = document.getElementById('citizenMenuBtn');
    const icon = document.getElementById('citizenMenuIcon');
    if (nav) {
        nav.classList.add('menu-expanded');
        nav.style.transform = 'translate3d(-50%, 0, 0)';
    }
    if (btn) btn.setAttribute('aria-expanded', 'true');
    if (icon) {
        icon.classList.remove('fa-bars');
        icon.classList.add('fa-xmark');
    }
}

function closeCitizenMenuSheet() {
    const nav = document.getElementById('citizenBottomNav');
    const btn = document.getElementById('citizenMenuBtn');
    const icon = document.getElementById('citizenMenuIcon');
    if (nav) {
        nav.classList.remove('menu-expanded');
        nav.style.transform = 'translateX(-50%)';
    }
    if (btn) btn.setAttribute('aria-expanded', 'false');
    if (icon) {
        icon.classList.remove('fa-xmark');
        icon.classList.add('fa-bars');
    }
}

function toggleCitizenMenuSheet() {
    const nav = document.getElementById('citizenBottomNav');
    if (nav && nav.classList.contains('menu-expanded')) {
        closeCitizenMenuSheet();
    } else {
        openCitizenMenuSheet();
    }
}

// Close when clicking outside the bar
document.addEventListener('click', function(e) {
    const nav = document.getElementById('citizenBottomNav');
    if (nav && nav.classList.contains('menu-expanded') && !nav.contains(e.target)) {
        closeCitizenMenuSheet();
    }
});

function applyTheme(theme) {
    const icon = document.getElementById('theme-toggle-icon');
    const mobIcon = document.getElementById('mobile-theme-icon');
    const text = document.getElementById('theme-toggle-text');
    const sheetIcon = document.getElementById('citizen-sheet-theme-icon');
    const sheetText = document.getElementById('citizen-sheet-theme-text');
    const dntToggles = document.querySelectorAll('.day-night-toggle');
    const isDark = (theme === 'dark');

    if (isDark) {
        document.body.classList.add('dark-theme');
        document.documentElement.classList.add('dark-theme');
        if (icon) icon.className = 'fas fa-sun';
        if (mobIcon) mobIcon.className = 'fas fa-sun';
        if (text) text.textContent = 'Dark Mode';
        if (sheetIcon) sheetIcon.className = 'fas fa-sun';
        if (sheetText) sheetText.textContent = 'Dark Mode';
        dntToggles.forEach(t => t.setAttribute('aria-checked', 'true'));
    } else {
        document.body.classList.remove('dark-theme');
        document.documentElement.classList.remove('dark-theme');
        if (icon) icon.className = 'fas fa-moon';
        if (mobIcon) mobIcon.className = 'fas fa-moon';
        if (text) text.textContent = 'Light Mode';
        if (sheetIcon) sheetIcon.className = 'fas fa-moon';
        if (sheetText) sheetText.textContent = 'Light Mode';
        dntToggles.forEach(t => t.setAttribute('aria-checked', 'false'));
    }
}

function toggleTheme() {
    const currentTheme = localStorage.getItem('theme') || 'light';
    const newTheme = currentTheme === 'light' ? 'dark' : 'light';
    localStorage.setItem('theme', newTheme);
    applyTheme(newTheme);
}

// Update the theme icon and text once DOM elements are ready
document.addEventListener('DOMContentLoaded', () => {
    const savedTheme = localStorage.getItem('theme') || 'light';
    applyTheme(savedTheme);

    // ── Live Clock ──────────────────────────────────────────────
    const clockEl = document.getElementById('topbar-clock');
    const dateEl  = document.getElementById('topbar-date');
    if (clockEl && dateEl) {
        function updateClock() {
            const now  = new Date();
            const h    = String(now.getHours()).padStart(2, '0');
            const m    = String(now.getMinutes()).padStart(2, '0');
            const s    = String(now.getSeconds()).padStart(2, '0');
            clockEl.textContent = `${h}:${m}:${s}`;

            const days   = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
            const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            dateEl.textContent = `${days[now.getDay()]}, ${months[now.getMonth()]} ${now.getDate()}, ${now.getFullYear()}`;
        }
        updateClock();
        setInterval(updateClock, 1000);
    }
});

// ── Account Popover ──────────────────────────────────────────
function toggleAccountPopover(e) {
    e.stopPropagation();
    const popover = document.getElementById('topbar-account-popover');
    const btn     = document.getElementById('topbar-account-btn');
    const caret   = document.getElementById('topbar-acct-caret');
    if (!popover) return;
    const isOpen = popover.classList.toggle('open');
    if (btn)   btn.setAttribute('aria-expanded', isOpen);
    if (caret) caret.classList.toggle('open', isOpen);
}

// Close popover when clicking anywhere outside
document.addEventListener('click', function(e) {
    const wrap  = document.getElementById('topbar-account-wrap');
    const pop   = document.getElementById('topbar-account-popover');
    const btn   = document.getElementById('topbar-account-btn');
    const caret = document.getElementById('topbar-acct-caret');
    if (pop && pop.classList.contains('open') && wrap && !wrap.contains(e.target)) {
        pop.classList.remove('open');
        if (btn)   btn.setAttribute('aria-expanded', 'false');
        if (caret) caret.classList.remove('open');
    }
});

function confirmLogout() {
    document.getElementById('logoutConfirmModal').classList.add('show');
}

function closeLogoutModal() {
    document.getElementById('logoutConfirmModal').classList.remove('show');
}

// Close modal when clicking outside of it
window.addEventListener('click', function(event) {
    const modal = document.getElementById('logoutConfirmModal');
    if (event.target === modal) {
        closeLogoutModal();
    }
});

(function () {
    const sidebar     = document.getElementById('sidebar-nav');
    const collapseBtn = document.getElementById('collapse-btn');
    const mobileToggle = document.getElementById('mobile-nav-toggle');
    const backdrop    = document.getElementById('sidebar-backdrop');
    const mainContent = document.querySelector('.main-content');
    const isCitizen   = <?php echo ($userType !== 'employee') ? 'true' : 'false'; ?>;

    // Desktop collapse toggle
    const appTopbar = document.getElementById('app-topbar');
    if (collapseBtn && sidebar) {
        collapseBtn.addEventListener('click', () => {
            const isCollapsed = sidebar.classList.toggle('collapsed');
            if (mainContent) mainContent.classList.toggle('collapsed', isCollapsed);
            if (appTopbar) appTopbar.classList.toggle('collapsed', isCollapsed);
            collapseBtn.innerHTML = isCollapsed ? '&#8250;' : '&#8249;';
            collapseBtn.setAttribute('aria-pressed', isCollapsed);
        });
    }

    // Mobile drawer open/close
    function openMobileSidebar() {
        if (sidebar) sidebar.classList.add('mobile-open');
        if (backdrop) backdrop.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeMobileSidebar() {
        if (sidebar) sidebar.classList.remove('mobile-open');
        if (backdrop) backdrop.classList.remove('active');
        document.body.style.overflow = '';
    }

    if (mobileToggle) {
        mobileToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            if (isCitizen) {
                toggleCitizenMenuSheet();
                return;
            }
            if (sidebar && sidebar.classList.contains('mobile-open')) {
                closeMobileSidebar();
            } else {
                openMobileSidebar();
            }
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', closeMobileSidebar);
    }

    // Auto-close drawer on link click on small screens
    const navLinks = document.querySelectorAll('.sidebar-nav .nav-link, .citizen-bottom-item');
    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 992) {
                closeMobileSidebar();
            }
        });
    });

    // Close on ESC key
    window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (sidebar && sidebar.classList.contains('mobile-open')) {
                closeMobileSidebar();
            }
            const sheet = document.getElementById('citizenMenuSheet');
            if (sheet && sheet.classList.contains('open')) {
                closeCitizenMenuSheet();
            }
        }
    });

    // Restore and persist sidebar menu scroll position so it never jumps on page transitions
    const menuScroll = document.querySelector('.sidebar-menu-scrollable');
    if (menuScroll) {
        const savedScroll = sessionStorage.getItem('sidebar_menu_scroll');
        if (savedScroll !== null) {
            menuScroll.scrollTop = parseInt(savedScroll, 10);
        }
        menuScroll.addEventListener('scroll', () => {
            sessionStorage.setItem('sidebar_menu_scroll', menuScroll.scrollTop);
        }, { passive: true });
    }

    // Seamless instant prefetching and smooth exit transitions on module link clicks
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.includes('logout.php')) return;

        // Hover prefetch for instant loading
        link.addEventListener('mouseenter', () => {
            if (!link._prefetched) {
                const prefetch = document.createElement('link');
                prefetch.rel = 'prefetch';
                prefetch.href = href;
                document.head.appendChild(prefetch);
                link._prefetched = true;
            }
        }, { once: true });

        // Smooth exit transition before navigating
        link.addEventListener('click', (e) => {
            if (e.metaKey || e.ctrlKey || e.shiftKey || link.getAttribute('target') === '_blank') return;
            if (link.classList.contains('active')) return;

            // If browser natively supports View Transitions, allow native cross-fade
            if ('startViewTransition' in document) {
                return;
            }

            e.preventDefault();
            document.body.classList.add('page-nav-exiting');
            setTimeout(() => {
                window.location.href = href;
            }, 120);
        });
    });

    // Drag-to-dismiss for Expanded Citizen Bottom Nav Bar
    (function() {
        const nav = document.getElementById('citizenBottomNav');
        const handle = document.querySelector('.citizen-sheet-handle');
        if (!nav || !handle) return;

        let startY = 0;
        let currentY = 0;
        let isDragging = false;

        // Start drag on handle
        handle.addEventListener('touchstart', onDragStart, { passive: true });
        handle.addEventListener('mousedown', onDragStart);

        function onDragStart(e) {
            if (!nav.classList.contains('menu-expanded')) return;

            startY = e.touches ? e.touches[0].clientY : e.clientY;
            isDragging = true;
            nav.style.transition = 'none';

            document.addEventListener('touchmove', onDragMove, { passive: false });
            document.addEventListener('mousemove', onDragMove);
            document.addEventListener('touchend', onDragEnd);
            document.addEventListener('mouseup', onDragEnd);
        }

        function onDragMove(e) {
            if (!isDragging) return;
            
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;
            const deltaY = clientY - startY;

            if (deltaY > 0) {
                if (e.cancelable) e.preventDefault();
                currentY = deltaY;
                nav.style.transform = `translate3d(-50%, ${deltaY}px, 0)`;
            }
        }

        function onDragEnd() {
            if (!isDragging) return;
            isDragging = false;

            document.removeEventListener('touchmove', onDragMove);
            document.removeEventListener('mousemove', onDragMove);
            document.removeEventListener('touchend', onDragEnd);
            document.removeEventListener('mouseup', onDragEnd);

            nav.style.transition = '';

            if (currentY > 85) {
                closeCitizenMenuSheet();
            } else {
                nav.style.transform = 'translate3d(-50%, 0, 0)';
            }
            currentY = 0;
        }
    })();
})();
</script>

<!-- GLOBAL SPINNER -->
<div id="global-spinner" class="global-spinner-overlay">
    <div class="spinner"></div>
</div>
<style>
.global-spinner-overlay {
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    z-index: 999999;
    display: flex;
    justify-content: center;
    align-items: center;
    opacity: 1;
    visibility: visible;
    transition: opacity 0.25s ease, visibility 0.25s ease;
}
.dark-theme .global-spinner-overlay {
    background: rgba(15, 23, 42, 0.8);
}
.global-spinner-overlay.hidden {
    opacity: 0;
    visibility: hidden;
}
.global-spinner-overlay .spinner {
    width: 48px;
    height: 48px;
    border: 4px solid rgba(55, 98, 200, 0.2);
    border-top-color: #3762c8;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}
.dark-theme .global-spinner-overlay .spinner {
    border: 4px solid rgba(99, 132, 210, 0.2);
    border-top-color: #6384d2;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>
<script>
window.addEventListener('load', function() {
    const spinner = document.getElementById('global-spinner');
    if (spinner) {
        // Small delay to ensure smooth transition
        setTimeout(() => spinner.classList.add('hidden'), 100);
    }
});
window.addEventListener('beforeunload', function() {
    const spinner = document.getElementById('global-spinner');
    if (spinner) spinner.classList.remove('hidden');
});
</script>

<?php
$triggerSplash = false;
if (isset($_SESSION['just_logged_in']) && $_SESSION['just_logged_in'] === true) {
    $triggerSplash = true;
    unset($_SESSION['just_logged_in']);
}
?>
<!-- SYSTEM LOGO INTRO ANIMATION ON LOGIN -->
<div id="login-logo-splash" class="login-logo-splash-overlay<?php echo $triggerSplash ? ' active' : ''; ?>" role="dialog" aria-modal="true" aria-label="System Login Intro">
    <div class="splash-content">
        <div class="splash-logo-wrapper">
            <div class="splash-glow-ring"></div>
            <div class="splash-glow-ring-inner"></div>
            <img src="<?php echo $sidebarBase; ?>assets/images/logocityhall.png" alt="System Logo" class="splash-logo-img">
        </div>
        <div class="splash-brand-details">
            <h1 class="splash-title">UMAN</h1>
            <p class="splash-subtitle">Utilities Management System</p>
            <div class="splash-welcome-pill"><i class="fas fa-user-check"></i> Welcome back, <?php echo $userName; ?></div>
        </div>
        <div class="splash-progress-track">
            <div class="splash-progress-bar"></div>
        </div>
    </div>
</div>

<style>
.login-logo-splash-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: radial-gradient(circle at center, rgba(15, 23, 42, 0.6) 0%, rgba(7, 11, 20, 0.8) 100%);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    z-index: 9999999;
    display: none;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    opacity: 0;
    transition: opacity 0.55s cubic-bezier(0.4, 0, 0.2, 1), transform 0.55s cubic-bezier(0.4, 0, 0.2, 1);
}

.login-logo-splash-overlay.active {
    display: flex !important;
    opacity: 1 !important;
}

.login-logo-splash-overlay.splash-exit {
    opacity: 0 !important;
    transform: scale(1.08) !important;
    pointer-events: none !important;
}

.splash-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 30px;
    position: relative;
    z-index: 2;
    font-family: 'Poppins', sans-serif;
}

.splash-logo-wrapper {
    position: relative;
    width: 150px;
    height: 150px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 24px;
}

.splash-glow-ring {
    position: absolute;
    width: 170px;
    height: 170px;
    border-radius: 50%;
    background: conic-gradient(from 0deg, #3762c8, #00A896, #6384d2, #3762c8);
    animation: splashSpinGlow 3s linear infinite;
    filter: blur(14px);
    opacity: 0.8;
}

.splash-glow-ring-inner {
    position: absolute;
    width: 150px;
    height: 150px;
    border-radius: 50%;
    border: 2px solid rgba(255, 255, 255, 0.25);
    box-shadow: 0 0 35px rgba(55, 98, 200, 0.6), inset 0 0 20px rgba(0, 168, 150, 0.35);
    animation: splashPulseRing 2s ease-in-out infinite alternate;
}

.splash-logo-img {
    width: 105px;
    height: 105px;
    object-fit: contain;
    position: relative;
    z-index: 3;
    filter: drop-shadow(0 10px 25px rgba(0, 0, 0, 0.6));
    animation: splashLogoEntrance 0.85s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
}

.splash-brand-details {
    display: none !important;
}

.splash-title {
    font-family: 'Poppins', sans-serif;
    font-size: 32px;
    font-weight: 800;
    letter-spacing: 3px;
    background: linear-gradient(135deg, #ffffff 0%, #93c5fd 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 4px;
    line-height: 1.1;
}

.splash-subtitle {
    font-size: 13px;
    color: #94a3b8;
    font-weight: 500;
    letter-spacing: 0.8px;
    margin-bottom: 14px;
}

.splash-welcome-pill {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 6px 16px;
    background: rgba(55, 98, 200, 0.25);
    border: 1px solid rgba(99, 132, 210, 0.35);
    border-radius: 99px;
    color: #93c5fd;
    font-size: 12px;
    font-weight: 600;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.2);
}

.splash-progress-track {
    display: none !important;
}

.splash-progress-bar {
    width: 0%;
    height: 100%;
    background: linear-gradient(90deg, #3762c8, #00A896, #6384d2);
    border-radius: 99px;
    animation: splashFillProgress 1.65s cubic-bezier(0.4, 0, 0.2, 1) 0.2s forwards;
}

@keyframes splashSpinGlow {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@keyframes splashPulseRing {
    0% { transform: scale(0.94); opacity: 0.5; }
    100% { transform: scale(1.06); opacity: 1; }
}

@keyframes splashLogoEntrance {
    0% {
        opacity: 0;
        transform: scale(0.2) rotate(-18deg);
    }
    70% {
        transform: scale(1.12) rotate(3deg);
    }
    100% {
        opacity: 1;
        transform: scale(1) rotate(0deg);
    }
}

@keyframes splashTextRise {
    0% {
        opacity: 0;
        transform: translateY(18px);
    }
    100% {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes splashFillProgress {
    0% { width: 0%; }
    100% { width: 100%; }
}
</style>

<script>
(function() {
    const splash = document.getElementById('login-logo-splash');
    if (!splash) return;
    
    // Check if triggered via session OR client sessionStorage flag
    const clientTrigger = sessionStorage.getItem('just_logged_in') === 'true';
    const serverTrigger = splash.classList.contains('active');
    
    if (serverTrigger || clientTrigger) {
        splash.classList.add('active');
        sessionStorage.removeItem('just_logged_in');
        
        // Hide standard spinner while splash plays
        const globalSpinner = document.getElementById('global-spinner');
        if (globalSpinner) globalSpinner.classList.add('hidden');

        document.body.style.overflow = 'hidden';
        
        setTimeout(function() {
            splash.classList.add('splash-exit');
            setTimeout(function() {
                splash.classList.remove('active');
                splash.style.display = 'none';
                document.body.style.overflow = '';
            }, 550);
        }, 1950);
    }
})();
</script>