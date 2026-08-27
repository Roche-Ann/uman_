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
        padding-top: 80px !important;
        padding-bottom: 95px !important;
        width: 100% !important;
        max-width: 100vw !important;
        box-sizing: border-box !important;
    }
    .main-content.collapsed {
        margin-left: 0 !important;
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
        bottom: 0;
        left: 0;
        right: 0;
        height: 66px;
        background: rgba(18, 24, 38, 0.95);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-top: 1px solid rgba(255, 255, 255, 0.12);
        display: flex;
        align-items: center;
        justify-content: space-around;
        padding: 0 10px;
        z-index: 10000;
        box-shadow: 0 -5px 25px rgba(0, 0, 0, 0.35);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        font-family: 'Poppins', sans-serif;
    }

    body:not(.dark-theme) .citizen-bottom-nav {
        background: rgba(255, 255, 255, 0.96);
        border-top: 1px solid rgba(0, 0, 0, 0.08);
        box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.08);
    }

    .dark-theme .citizen-bottom-nav {
        background: #111827;
        border-top: 1px solid #1f2937;
        box-shadow: 0 -5px 25px rgba(0, 0, 0, 0.5);
    }

    @media (min-width: 769px) {
        .citizen-bottom-nav {
            left: 50%;
            transform: translateX(-50%);
            right: auto;
            width: 90%;
            max-width: 640px;
            bottom: 16px;
            border-radius: 32px;
            border: 1px solid rgba(255, 255, 255, 0.18);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.4);
        }
        body:not(.dark-theme) .citizen-bottom-nav {
            border: 1px solid rgba(0, 0, 0, 0.1);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.12);
        }
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

    /* Slide-Up Citizen Action Menu Sheet */
    .citizen-menu-sheet {
        position: fixed;
        bottom: -100%;
        left: 50%;
        transform: translateX(-50%);
        width: 100%;
        max-width: 500px;
        background: #ffffff;
        border-radius: 24px 24px 0 0;
        padding: 20px 20px 85px 20px;
        box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.25);
        z-index: 10001;
        transition: bottom 0.35s cubic-bezier(0.32, 0.72, 0, 1);
        font-family: 'Poppins', sans-serif;
    }

    .dark-theme .citizen-menu-sheet {
        background: #1e293b;
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-bottom: none;
        box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.6);
    }

    .citizen-menu-sheet.open {
        bottom: 0;
    }

    .citizen-sheet-handle {
        width: 42px;
        height: 5px;
        background: #cbd5e1;
        border-radius: 99px;
        margin: 0 auto 16px auto;
    }

    .dark-theme .citizen-sheet-handle {
        background: #475569;
    }

    .citizen-sheet-header {
        display: flex;
        align-items: center;
        gap: 14px;
        padding-bottom: 14px;
        border-bottom: 1px solid #f1f5f9;
        margin-bottom: 14px;
    }

    .dark-theme .citizen-sheet-header {
        border-bottom-color: #334155;
    }

    .citizen-sheet-avatar {
        width: 46px;
        height: 46px;
        border-radius: 50%;
        background: linear-gradient(135deg, #3762c8, #6384d2);
        display: grid;
        place-items: center;
        color: #fff;
        font-size: 18px;
        box-shadow: 0 4px 12px rgba(55, 98, 200, 0.3);
    }

    .citizen-sheet-user-name {
        font-size: 15px;
        font-weight: 700;
        color: #1e293b;
    }

    .dark-theme .citizen-sheet-user-name {
        color: #f8fafc;
    }

    .citizen-sheet-user-role {
        font-size: 12px;
        color: #64748b;
        font-weight: 500;
    }

    .dark-theme .citizen-sheet-user-role {
        color: #94a3b8;
    }

    .citizen-sheet-menu {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .citizen-sheet-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 16px;
        border-radius: 12px;
        text-decoration: none;
        color: #1e293b;
        font-size: 13.5px;
        font-weight: 600;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        transition: all 0.2s ease;
        cursor: pointer;
    }

    .dark-theme .citizen-sheet-item {
        background: #0f172a;
        border-color: #334155;
        color: #f8fafc;
    }

    .citizen-sheet-item:hover {
        background: #edf2f7;
        transform: translateX(4px);
    }

    .dark-theme .citizen-sheet-item:hover {
        background: #1e293b;
    }

    .citizen-sheet-item-left {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .citizen-sheet-item-left i {
        width: 22px;
        font-size: 16px;
        color: #3762c8;
        text-align: center;
    }

    .dark-theme .citizen-sheet-item-left i {
        color: #6384d2;
    }

    .citizen-sheet-backdrop {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        z-index: 10000;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .citizen-sheet-backdrop.active {
        display: block;
        opacity: 1;
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
    .sidebar-nav.collapsed .logout-btn .logout-text,
    .sidebar-nav.collapsed .back-link { display: none; }
    .sidebar-nav.collapsed .nav-link {
        justify-content: center;
        padding: 12px 0;
    }
    .sidebar-nav.collapsed .nav-link i { margin-right: 0; width: auto; }
    .sidebar-nav.collapsed .user-info {
        padding: 10px 0 16px;
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

    /* Sync main-content margin with sidebar */
    .main-content { margin-left: 280px; transition: margin-left 0.25s ease; min-height: 100vh; display: flex; flex-direction: column; }
    .main-content > .card { flex: 1; }
    .main-content.collapsed { margin-left: 90px; }

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

    /* Theme Toggle Button Style */
    .theme-toggle-btn {
        background: rgba(0, 0, 0, 0.05);
        color: #1e293b;
        padding: 9px 14px;
        border-radius: 7px;
        cursor: pointer;
        transition: all 0.3s ease;
        width: 88%;
        border: 1px solid rgba(0, 0, 0, 0.1);
        font-weight: 600;
        font-size: 13px;
        font-family: 'Poppins', sans-serif;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    .theme-toggle-btn:hover {
        background: rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
    }
    .sidebar-nav.collapsed .theme-toggle-btn span {
        display: none;
    }
    .sidebar-nav.collapsed .theme-toggle-btn {
        width: auto;
        padding: 9px;
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

    /* Cards and Grids (Stats Cards) */
    .dark-theme .stat-card {
        background: #1e293b !important;
        color: #f8fafc !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25) !important;
        border-left-width: 5px !important;
    }
    .dark-theme .stat-info h3 {
        color: #f8fafc !important;
    }
    .dark-theme .stat-info p {
        color: #94a3b8 !important;
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
    .dark-theme .box {
        background: #1e293b !important;
        border-color: #334155 !important;
        color: #f8fafc !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25) !important;
    }
    .dark-theme .box h3,
    .dark-theme .box h4 {
        color: #f8fafc !important;
        border-bottom-color: #334155 !important;
    }

    /* Override hardcoded inline text colors in dark theme */
    .dark-theme [style*="color:#2c3e50"],
    .dark-theme [style*="color: #2c3e50"],
    .dark-theme [style*="color:rgb(44, 62, 80)"],
    .dark-theme [style*="color: rgb(44, 62, 80)"],
    .dark-theme [style*="color:#1e293b"],
    .dark-theme [style*="color: #1e293b"],
    .dark-theme [style*="color:#000"],
    .dark-theme [style*="color: #000"] {
        color: #f8fafc !important;
    }
    .dark-theme [style*="color:#64748b"],
    .dark-theme [style*="color: #64748b"],
    .dark-theme [style*="color:#475569"],
    .dark-theme [style*="color: #475569"] {
        color: #94a3b8 !important;
    }
    .dark-theme [style*="background:#f8fafc"],
    .dark-theme [style*="background: #f8fafc"],
    .dark-theme [style*="background:#f1f5f9"],
    .dark-theme [style*="background: #f1f5f9"],
    .dark-theme [style*="background:#f8f9fa"],
    .dark-theme [style*="background: #f8f9fa"] {
        background: #0f172a !important;
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

    /* ── Stat cards (CPRF Hub & other pages) ── */
    .dark-theme .stat-card {
        background: #1e293b !important;
        border-color: #334155 !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3) !important;
    }
    .dark-theme .stat-card .stat-info h3 {
        color: #f8fafc !important;
    }
    .dark-theme .stat-card h3 {
        color: #f8fafc !important;
    }
    .dark-theme .stat-card p {
        color: #94a3b8 !important;
    }
    /* Preserve the colored left-border accent — just intensify it slightly */
    .dark-theme .stat-card                    { border-left-color: #3762c8 !important; }
    .dark-theme .stat-card.operational        { border-left-color: #27ae60 !important; }
    .dark-theme .stat-card.needs-inspection   { border-left-color: #f39c12 !important; }
    .dark-theme .stat-card.damaged            { border-left-color: #e74c3c !important; }
    .dark-theme .stat-card.maintenance        { border-left-color: #9b59b6 !important; }
    /* stat-footer labels keep their colors in dark mode */
    .dark-theme .stat-card .stat-footer .stat-icon,
    .dark-theme .stat-card .stat-footer .stat-label { opacity: 0.9; }


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
        <button type="button" class="mobile-theme-toggle" onclick="toggleTheme()" aria-label="Toggle Dark Mode">
            <i class="fas fa-moon" id="mobile-theme-icon"></i>
        </button>
    </div>
</div>

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
            $isOpsActive = (strpos($currentPage, 'incidents_') === 0) || (strpos($currentPage, 'maintenance_') === 0) || $currentPage === 'upad_integration.php';
            ?>
            <li class="sidebar-dropdown-wrapper">
                <button type="button" class="sidebar-dropdown-toggle<?php echo $isOpsActive ? ' active' : ''; ?>" onclick="toggleSidebarDropdown(this)">
                    <i class="fas fa-tasks icon-main"></i>
                    <span class="link-label">Operations</span>
                    <i class="fas fa-chevron-right chevron-icon<?php echo $isOpsActive ? ' rotate' : ''; ?>"></i>
                </button>
                <ul class="sidebar-dropdown-menu<?php echo $isOpsActive ? ' open' : ''; ?>">
                    <li>
                        <a href="<?php echo $sidebarBase; ?>incidents_dashboard.php" class="dropdown-link<?php echo (strpos($currentPage, 'incidents_') === 0) ? ' active' : ''; ?>">
                            <i class="fas fa-bullhorn"></i>
                            <span>Incident Reports</span>
                        </a>
                    </li>
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
                <a href="<?php echo $sidebarBase; ?>citizen_reports.php" class="nav-link<?php echo sidebarActive('citizen_reports.php', $currentPage); ?>">
                    <i class="fas fa-file-invoice"></i>
                    <span class="link-label">Track Reports</span>
                </a>
            </li>
            <li>
                <a href="<?php echo $sidebarBase; ?>citizen_asset_request.php" class="nav-link<?php echo sidebarActive('citizen_asset_request.php', $currentPage); ?>">
                    <i class="fas fa-boxes-stacked"></i>
                    <span class="link-label">Asset Requests</span>
                </a>
            </li>
            <li>
                <a href="<?php echo $sidebarBase; ?>citizen_advisories.php" class="nav-link<?php echo sidebarActive('citizen_advisories.php', $currentPage); ?>">
                    <i class="fas fa-bullhorn"></i>
                    <span class="link-label">LGU Advisories</span>
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
            <div class="user-welcome"><i class="fas fa-user-circle" style="margin-right:6px; color:#6384d2;"></i><?php echo $userName; ?></div>
            <button class="theme-toggle-btn" onclick="toggleTheme()" style="margin-bottom: 8px;" title="Toggle Dark/Light Mode">
                <i class="fas fa-moon" id="theme-toggle-icon"></i> <span id="theme-toggle-text">Dark Mode</span>
            </button>
            <button class="logout-btn" onclick="confirmLogout()" title="Logout">
                <i class="fas fa-sign-out-alt"></i> <span class="logout-text">Logout</span>
            </button>
        </div>
    </div>
</nav>

<?php if ($userType !== 'employee'): ?>
<!-- ===== CITIZEN BOTTOM NAVIGATION BAR (Messenger Style - Photo 3) ===== -->
<div class="citizen-bottom-nav" id="citizenBottomNav" role="navigation" aria-label="Citizen Bottom Navigation">
    <a href="<?php echo $sidebarBase; ?>citizen.php" class="citizen-bottom-item<?php echo $currentPage === 'citizen.php' ? ' active' : ''; ?>">
        <i class="fas fa-home"></i>
        <span>Home</span>
    </a>
    <a href="<?php echo $sidebarBase; ?>citizen_reports.php" class="citizen-bottom-item<?php echo ($currentPage === 'citizen_reports.php' || $currentPage === 'citizen_submit_report.php') ? ' active' : ''; ?>">
        <i class="fas fa-file-invoice"></i>
        <span>Reports</span>
        <?php if ($activeReportCount > 0): ?>
            <span class="citizen-bottom-badge"><?php echo $activeReportCount; ?></span>
        <?php endif; ?>
    </a>
    <a href="<?php echo $sidebarBase; ?>citizen_asset_request.php" class="citizen-bottom-item<?php echo $currentPage === 'citizen_asset_request.php' ? ' active' : ''; ?>">
        <i class="fas fa-boxes-stacked"></i>
        <span>Requests</span>
    </a>
    <a href="<?php echo $sidebarBase; ?>citizen_advisories.php" class="citizen-bottom-item<?php echo $currentPage === 'citizen_advisories.php' ? ' active' : ''; ?>">
        <i class="fas fa-bullhorn"></i>
        <span>Advisories</span>
    </a>
    <button type="button" class="citizen-bottom-item<?php echo ($currentPage === 'citizen_notifications.php' || $currentPage === 'citizen_profile.php') ? ' active' : ''; ?>" onclick="toggleCitizenMenuSheet()" aria-label="Open Menu">
        <i class="fas fa-bars"></i>
        <span>Menu</span>
        <?php if ($unreadNotifCount > 0): ?>
            <span class="citizen-bottom-badge"><?php echo $unreadNotifCount; ?></span>
        <?php endif; ?>
    </button>
</div>

<!-- Backdrop Overlay for Citizen Action Sheet -->
<div class="citizen-sheet-backdrop" id="citizenSheetBackdrop" onclick="closeCitizenMenuSheet()"></div>

<!-- ===== CITIZEN SLIDE-UP MENU SHEET ===== -->
<div class="citizen-menu-sheet" id="citizenMenuSheet" role="dialog" aria-modal="true" aria-label="Citizen Menu">
    <div class="citizen-sheet-handle"></div>
    <div class="citizen-sheet-header">
        <div class="citizen-sheet-avatar">
            <i class="fas fa-user"></i>
        </div>
        <div>
            <div class="citizen-sheet-user-name"><?php echo $userName; ?></div>
            <div class="citizen-sheet-user-role"><i class="fas fa-shield-alt" style="margin-right:4px; color:#3762c8;"></i>Resident Portal</div>
        </div>
    </div>
    <div class="citizen-sheet-menu">
        <a href="<?php echo $sidebarBase; ?>citizen_notifications.php" class="citizen-sheet-item">
            <div class="citizen-sheet-item-left">
                <i class="fas fa-bell"></i>
                <span>Notifications</span>
            </div>
            <?php if ($unreadNotifCount > 0): ?>
                <span style="background:#ef4444; color:#fff; font-size:11px; padding:2px 8px; border-radius:99px; font-weight:700;"><?php echo $unreadNotifCount; ?> new</span>
            <?php else: ?>
                <i class="fas fa-chevron-right" style="color:#94a3b8; font-size:12px;"></i>
            <?php endif; ?>
        </a>
        <a href="<?php echo $sidebarBase; ?>citizen_profile.php" class="citizen-sheet-item">
            <div class="citizen-sheet-item-left">
                <i class="fas fa-user-cog"></i>
                <span>Profile Settings</span>
            </div>
            <i class="fas fa-chevron-right" style="color:#94a3b8; font-size:12px;"></i>
        </a>
        <button type="button" class="citizen-sheet-item" onclick="toggleTheme()" style="width:100%; text-align:left;">
            <div class="citizen-sheet-item-left">
                <i class="fas fa-moon" id="citizen-sheet-theme-icon"></i>
                <span id="citizen-sheet-theme-text">Dark Mode</span>
            </div>
            <span style="font-size:12px; color:#64748b; font-weight:500;">Toggle</span>
        </button>
        <button type="button" class="citizen-sheet-item" onclick="confirmLogout()" style="width:100%; border-color:#fee2e2; background:#fef2f2; color:#dc2626;">
            <div class="citizen-sheet-item-left">
                <i class="fas fa-sign-out-alt" style="color:#dc2626;"></i>
                <span style="color:#dc2626;">Logout</span>
            </div>
            <i class="fas fa-chevron-right" style="color:#dc2626; font-size:12px;"></i>
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
    const sheet = document.getElementById('citizenMenuSheet');
    const backdrop = document.getElementById('citizenSheetBackdrop');
    if (sheet) sheet.classList.add('open');
    if (backdrop) backdrop.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeCitizenMenuSheet() {
    const sheet = document.getElementById('citizenMenuSheet');
    const backdrop = document.getElementById('citizenSheetBackdrop');
    if (sheet) sheet.classList.remove('open');
    if (backdrop) backdrop.classList.remove('active');
    document.body.style.overflow = '';
}

function toggleCitizenMenuSheet() {
    const sheet = document.getElementById('citizenMenuSheet');
    if (sheet && sheet.classList.contains('open')) {
        closeCitizenMenuSheet();
    } else {
        openCitizenMenuSheet();
    }
}

function applyTheme(theme) {
    const icon = document.getElementById('theme-toggle-icon');
    const mobIcon = document.getElementById('mobile-theme-icon');
    const text = document.getElementById('theme-toggle-text');
    const sheetIcon = document.getElementById('citizen-sheet-theme-icon');
    const sheetText = document.getElementById('citizen-sheet-theme-text');
    if (theme === 'dark') {
        document.body.classList.add('dark-theme');
        document.documentElement.classList.add('dark-theme');
        if (icon) icon.className = 'fas fa-sun';
        if (mobIcon) mobIcon.className = 'fas fa-sun';
        if (text) text.textContent = 'Light Mode';
        if (sheetIcon) sheetIcon.className = 'fas fa-sun';
        if (sheetText) sheetText.textContent = 'Light Mode';
    } else {
        document.body.classList.remove('dark-theme');
        document.documentElement.classList.remove('dark-theme');
        if (icon) icon.className = 'fas fa-moon';
        if (mobIcon) mobIcon.className = 'fas fa-moon';
        if (text) text.textContent = 'Dark Mode';
        if (sheetIcon) sheetIcon.className = 'fas fa-moon';
        if (sheetText) sheetText.textContent = 'Dark Mode';
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
    if (collapseBtn && sidebar) {
        collapseBtn.addEventListener('click', () => {
            const isCollapsed = sidebar.classList.toggle('collapsed');
            if (mainContent) mainContent.classList.toggle('collapsed', isCollapsed);
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