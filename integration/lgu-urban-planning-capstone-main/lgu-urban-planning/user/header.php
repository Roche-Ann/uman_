<?php
// ── Header i18n ───────────────────────────────────────────────────────────────
// Picks up the language saved by settings.php via $_SESSION['locale_language'].
$_hLang = $_SESSION['locale_language'] ?? 'en_PH';

$_hT = [
    'en_PH' => [
        'nav_title'        => 'Urban Planning and Development',
        'notif_header'     => 'Notifications',
        'notif_empty'      => 'No notifications yet.',
        'notif_caught_up'  => "You're all caught up!",
        'notif_view_all'   => 'View All Messages',
        'menu_profile'     => 'My Profile',
        'menu_settings'    => 'Settings',
        'sidebar_dashboard'=> 'Dashboard',
        'sidebar_apply'    => 'Submit Application',
        'sidebar_apps'     => 'My Applications',
        'sidebar_messages' => 'Messages',
        'sidebar_welcome'  => 'Welcome',
        'sidebar_logout'   => 'Logout',
    ],
    'fil' => [
        'nav_title'        => 'Pagpaplano at Pagpapaunlad ng Lungsod',
        'notif_header'     => 'Mga Abiso',
        'notif_empty'      => 'Wala pang mga abiso.',
        'notif_caught_up'  => 'Wala kang nalalagpasang bagay!',
        'notif_view_all'   => 'Tingnan ang Lahat ng Mensahe',
        'menu_profile'     => 'Aking Profile',
        'menu_settings'    => 'Mga Setting',
        'sidebar_dashboard'=> 'Dashboard',
        'sidebar_apply'    => 'Magsumite ng Aplikasyon',
        'sidebar_apps'     => 'Aking mga Aplikasyon',
        'sidebar_messages' => 'Mga Mensahe',
        'sidebar_welcome'  => 'Maligayang pagdating',
        'sidebar_logout'   => 'Mag-logout',
    ],
];

/**
 * Translate a header string, falling back to en_PH.
 */
function _ht(string $key): string {
    global $_hT, $_hLang;
    return $_hT[$_hLang][$key] ?? $_hT['en_PH'][$key] ?? $key;
}

// Show the preloader once, right after a successful login
$showLoginPreloader = !empty($_SESSION['show_preloader']);
unset($_SESSION['show_preloader']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>LGU Urban Planning System - User Portal</title>
    <link rel="icon" type="image/x-icon" href="/lgu-urban-planning/assets/upad-logo.png" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600&display=swap');

        /* ---------- INFRA PRELOADER ---------- */
        #infra-preloader {
            position: fixed;
            inset: 0;
            z-index: 99999;
            background: rgba(255, 255, 255, 0.30);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: opacity 0.5s ease, visibility 0.5s ease;
        }

        #infra-preloader.is-hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        .preloader-logo-wrap {
            position: relative;
            width: 150px;
            height: 150px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .preloader-glow {
            position: absolute;
            inset: -18px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(245, 166, 35, 0.22) 0%, rgba(26, 58, 110, 0.10) 55%, transparent 75%);
            animation: preloader-glow-pulse 2.4s ease-in-out infinite;
        }

        @keyframes preloader-glow-pulse {
            0%, 100% { transform: scale(0.9); opacity: 0.55; }
            50%      { transform: scale(1.12); opacity: 1; }
        }

        .preloader-ring {
            position: absolute;
            inset: -12px;
            border-radius: 50%;
            border: 3px solid rgba(26, 58, 110, 0.10);
            border-top-color: #f5a623;
            border-right-color: #f5a623;
            animation: preloader-ring-spin 1.3s linear infinite;
        }

        @keyframes preloader-ring-spin {
            to { transform: rotate(360deg); }
        }

        .preloader-logo {
            width: 96px;
            height: auto;
            position: relative;
            z-index: 2;
            opacity: 0;
            filter: drop-shadow(0 6px 14px rgba(26, 58, 110, 0.18));
            transform: scale(0.8);
            animation: preloader-logo-in 0.7s cubic-bezier(.34,1.56,.64,1) forwards;
        }

        @keyframes preloader-logo-in {
            to { opacity: 1; transform: scale(1); }
        }

        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        
        body, .sidebar, .main-content, .top-navbar {
        transition: background-color 0.3s ease, color 0.3s ease; }

        body {
            background: rgba(0, 0, 0, 0.35);
            background-attachment: fixed;
            min-height: 100vh;
            margin: 0;
            display: flex;
            flex-direction: column;
        }
        
        .top-navbar {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            backdrop-filter: blur(10px);
            padding: 15px 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            position: sticky;
            top: 0;
            z-index: 1050;
        }
        
        .top-navbar h5 {
            color: white !important;
            margin: 0;
            font-weight: 600;
        }
        
        .top-navbar .user-info {
            color: white;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        /* --- SIDEBAR STYLE */
        .sidebar {
            position: fixed;
            top: 70px; 
            left: 0;
            width: 250px;
            height: calc(100vh - 70px);
            background: rgba(255, 255, 255, 0.795); 
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            box-shadow: 4px 0 25px rgba(0,0,0,0.15);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.3s ease;
            z-index: 1000;
            border-right: 1px solid rgba(255, 255, 255, 0.25);
        }
        
        .sidebar.collapsed {
            width: 80px;
        }
        
        .sidebar.collapsed .sidebar-text, 
        .sidebar.collapsed .badge,
        .sidebar.collapsed .sidebar-logo-container,
        .sidebar.collapsed .welcome-text {
            display: none;
        }
        
        /* Logo and Divider Styling */
        .sidebar-logo-container {
            padding: 20px;
            text-align: center;
        }

        .sidebar-logo-container img {
            max-width: 100px;
            height: auto;
            margin-bottom: 15px;
        }

        .sidebar-divider {
            height: 2px;
            background: rgba(0, 0, 0, 0.1);
            margin: 0 20px 20px 20px;
        }
        
        .sidebar-top {
            flex-grow: 1;
            overflow-y: auto;
            scrollbar-width: none;
        }
        
        .sidebar-top::-webkit-scrollbar {
            display: none;
        }

        .sidebar h4 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 1.25rem;
            color: #000000;
            padding: 0 20px;
            margin-bottom: 20px;
        }
        
        .sidebar .nav-list {
            list-style: none;
            padding: 0 15px;
            margin: 0;
        }

        .sidebar .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #000000;
            text-decoration: none;
            padding: 12px 15px;
            margin-bottom: 5px;
            transition: all 0.3s ease;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .sidebar .nav-link i {
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }
        
        .sidebar .nav-link:hover {
            background: #97a4c2;
            transform: translateX(8px);
            color: #000;
        }
        
        .sidebar .nav-link.active {
            background: #3762c8;
            color: #fff;
            box-shadow: 0 4px 12px rgba(55, 98, 200, 0.3);
        }

        /* --- LOGOUT BUTTON AND WELCOME SA IBABA --- */
        .sidebar-footer {
            padding: 20px 15px;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }

        .welcome-text {
            display: block;
            text-align: center;
            font-weight: 600;
            font-size: 0.9rem;
            color: #000000;
            margin-bottom: 10px;
        }

        .logout-btn-sidebar {
            background: #3762c8;
            color: #fff !important;
            padding: 10px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            transition: 0.3s;
            width: 100%;
            font-weight: 500;
        }

        .logout-btn-sidebar:hover {
            background: #285ccd;
            transform: translateY(-2px);
            color: #fff;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .sidebar-toggle {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .sidebar-toggle:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .main-content {
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
            min-height: 100vh;
            margin-left: 250px;
            width: calc(100% - 250px);
            padding: 20px;
            transition: all 0.3s ease;
            flex: 1;
            min-width: 0;
            overflow-x: hidden;
            box-sizing: border-box;
        }

        .sidebar.collapsed + .main-content {
            margin-left: 80px;
            width: calc(100% - 80px);
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        /* --- NOTIFICATION BELL STYLES --- */
        .notif-dropdown .dropdown-menu {
            width: 320px;
            border: none;
            box-shadow: 0 5px 25px rgba(0,0,0,0.2);
            border-radius: 12px;
            padding: 0;
            margin-top: 10px !important;
        }
        .notif-header {
            background: #f8f9fa;
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            border-radius: 12px 12px 0 0;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .notif-item {
            padding: 12px 15px;
            border-bottom: 1px solid #f8f8f8;
            display: block;
            text-decoration: none;
            color: #333;
            transition: 0.2s;
            overflow: hidden;
            max-width: 320px;
        }
        .notif-item:hover { background: #f0f4ff; }
        .notif-item.unread { background: #edf2ff; border-left: 3px solid #3762c8; }
        .notif-message-preview {
            width: 100%;
            display: block;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            font-size: 0.82rem;
            color: #666;
        }
        .notif-footer {
            padding: 10px;
            text-align: center;
            border-top: 1px solid #eee;
        }

        /* =============================================
           SIDEBAR OVERLAY (mobile drawer backdrop)
        ============================================= */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            z-index: 999;
            backdrop-filter: blur(2px);
            -webkit-backdrop-filter: blur(2px);
        }
        .sidebar-overlay.active { display: block; }

        /* =============================================
           1024px — LAPTOP
        ============================================= */
        @media (max-width: 1024px) {

            /* --- Navbar --- */
            .top-navbar {
                padding: 12px 20px;
            }
            .top-navbar h5 {
                font-size: 1rem;
                max-width: 260px;
            }

            /* --- Sidebar: stay docked, just narrower --- */
            .sidebar {
                width: 220px;
            }
            .main-content {
                margin-left: 220px;
                width: calc(100% - 220px);
                padding: 18px;
            }
            .sidebar .nav-link {
                padding: 11px 13px;
                font-size: 0.95rem;
            }
            .sidebar-logo-container img {
                max-width: 90px;
            }

            /* --- Notification / avatar dropdowns --- */
            .notif-dropdown .dropdown-menu {
                width: 300px;
            }
            .avatar-menu {
                width: 230px;
            }
        }

        /* =============================================
           768px — TABLETS
        ============================================= */
        @media (max-width: 768px) {

            /* --- Navbar --- */
            .top-navbar {
                padding: 10px 16px;
            }
            .top-navbar h5 {
                font-size: 0.95rem;
                max-width: 200px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .user-info { gap: 6px; }

            /* --- Sidebar: off-canvas drawer --- */
            .sidebar {
                transform: translateX(-100%);
                width: 250px;
                top: 0;
                height: 100vh;
                z-index: 1040;
                transition: transform 0.3s ease;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            /* Reset collapsed behaviour on mobile — always full width when shown */
            .sidebar.collapsed {
                width: 250px;
                transform: translateX(-100%);
            }
            .sidebar.collapsed.show {
                transform: translateX(0);
            }
            .sidebar.collapsed .sidebar-text,
            .sidebar.collapsed .badge,
            .sidebar.collapsed .sidebar-logo-container,
            .sidebar.collapsed .welcome-text {
                display: unset;
            }

            /* --- Main content: full width --- */
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
                padding: 14px 12px;
                box-sizing: border-box;
            }

            /* --- Notification dropdown --- */
            .notif-dropdown .dropdown-menu {
                width: 290px;
            }

            /* --- Avatar menu --- */
            .avatar-menu { width: 220px; }
        }

        /* =============================================
           480px — LARGE MOBILE
        ============================================= */
        @media (max-width: 480px) {

            /* --- Navbar --- */
            .top-navbar {
                padding: 8px 12px;
            }
            .top-navbar h5 {
                font-size: 0.82rem;
                max-width: 130px;
            }
            .user-info { gap: 4px; }

            /* Shrink toggle button slightly */
            .sidebar-toggle {
                padding: 6px 10px;
                font-size: 0.9rem;
            }

            /* Hide the theme-toggle icon on very small bars to save room */
            .navbar-icon-btn[onclick="toggleDarkMode()"] {
                display: none;
            }

            /* --- Notification dropdown: viewport-anchored to beat Popper.js --- */
            .notif-dropdown .dropdown-menu {
                position: fixed !important;
                top: 56px !important;
                left: 12px !important;
                right: 12px !important;
                width: auto !important;
                max-width: calc(100vw - 24px) !important;
                transform: none !important;
                margin-top: 0 !important;
            }

            /* Notification item text clamp */
            .notif-message-preview {
                max-width: 100% !important;
                white-space: nowrap;
            }

            /* --- Avatar menu --- */
            .avatar-menu {
                width: 200px;
                border-radius: 10px;
            }
            .avatar-menu-header { padding: 10px 12px 8px; gap: 8px; }
            .avatar-menu-avatar { width: 34px; height: 34px; font-size: 0.85rem; }
            .avatar-menu-name { font-size: 0.82rem; }
            .avatar-menu-role { font-size: 0.7rem; }
            .avatar-menu-item { padding: 8px 12px; font-size: 0.82rem; }

            /* --- Main content --- */
            .main-content { padding: 10px 8px; width: 100% !important; margin-left: 0 !important; }

            /* --- Sidebar (inherit 768 rules, just ensure sizing is correct) --- */
            .sidebar { width: 240px; }
            .sidebar-logo-container img { max-width: 80px; }
            .sidebar h4 { font-size: 1.05rem; }
            .sidebar .nav-link { padding: 10px 12px; font-size: 0.9rem; gap: 10px; }
            .sidebar .nav-link i { font-size: 1.05rem; }
            .welcome-text { font-size: 0.82rem; }
            .logout-btn-sidebar { padding: 8px; font-size: 0.85rem; }

            /* --- User avatar button --- */
            .user-avatar {
                width: 34px;
                height: 34px;
                font-size: 0.85rem;
            }
        }

        /* =============================================
           320px — SMALL MOBILE
        ============================================= */
        @media (max-width: 320px) {

            /* --- Navbar --- */
            .top-navbar { padding: 7px 8px; }
            .top-navbar h5 {
                font-size: 0.72rem;
                max-width: 90px;
            }
            .user-info { gap: 2px; }

            .sidebar-toggle { padding: 5px 8px; font-size: 0.8rem; }

            /* Navbar icon buttons: tighter */
            .navbar-icon-btn { width: 28px; height: 28px; font-size: 0.85rem; }

            /* Notification badge: prevent overflow */
            .notif-badge { font-size: 0.52rem; min-width: 13px; height: 13px; }

            /* --- Notification dropdown: full-width, viewport-anchored --- */
            .notif-dropdown .dropdown-menu {
                position: fixed !important;
                top: 50px !important;
                left: 8px !important;
                right: 8px !important;
                width: auto !important;
                max-width: calc(100vw - 16px) !important;
                transform: none !important;
                margin-top: 0 !important;
            }
            .notif-header { font-size: 0.8rem; padding: 9px 12px; }
            .notif-item { padding: 9px 12px; }
            .notif-footer { padding: 7px 10px; font-size: 0.75rem; }

            /* --- Avatar menu --- */
            .avatar-menu { width: 180px; border-radius: 8px; }
            .avatar-menu-header { padding: 8px 10px 6px; gap: 6px; }
            .avatar-menu-avatar { width: 28px; height: 28px; font-size: 0.75rem; }
            .avatar-menu-name { font-size: 0.75rem; }
            .avatar-menu-role { font-size: 0.65rem; }
            .avatar-menu-item { padding: 7px 10px; font-size: 0.77rem; gap: 7px; }
            .avatar-menu-item i { font-size: 0.85rem; width: 15px; }

            /* --- User avatar button --- */
            .user-avatar { width: 28px; height: 28px; font-size: 0.72rem; }

            /* --- Main content --- */
            .main-content { padding: 8px 6px; width: 100% !important; margin-left: 0 !important; }

            /* --- Sidebar --- */
            .sidebar { width: 220px; }
            .sidebar-logo-container { padding: 14px; }
            .sidebar-logo-container img { max-width: 64px; margin-bottom: 10px; }
            .sidebar-divider { margin: 0 14px 14px; }
            .sidebar h4 { font-size: 0.95rem; padding: 0 14px; margin-bottom: 14px; }
            .sidebar .nav-list { padding: 0 10px; }
            .sidebar .nav-link { padding: 9px 10px; font-size: 0.82rem; gap: 8px; }
            .sidebar .nav-link i { font-size: 0.95rem; width: 20px; }
            .sidebar-footer { padding: 14px 10px; }
            .welcome-text { font-size: 0.75rem; margin-bottom: 7px; }
            .logout-btn-sidebar { padding: 7px; font-size: 0.78rem; gap: 7px; border-radius: 6px; }
        }

        /* ---- RIGHT-SIDE NAVBAR ---- */
        .navbar-icon-group {
            display: flex;
            align-items: center;
            gap: 2px;
        }

        .navbar-icon-btn {
            color: white !important;
            font-size: 1rem;
            line-height: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 7px;
            transition: background 0.2s ease;
            background: none;
            border: none;
            text-decoration: none;
            padding: 0;
        }
        .navbar-icon-btn:hover {
            background: rgba(255, 255, 255, 0.18);
        }

        /* Notification badge */
        .notif-badge {
            position: absolute;
            top: -4px;
            right: -6px;
            background: #ef4444;
            color: white;
            font-size: 0.6rem;
            font-weight: 700;
            min-width: 16px;
            height: 16px;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 3px;
            line-height: 1;
            pointer-events: none;
        }

        /* User text (name + role) */
        .user-text-info {
            text-align: right;
            line-height: 1.3;
        }
        .user-fullname {
            color: #ffffff;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .user-role {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.75rem;
        }

        /* Avatar circle as dropdown trigger */
        .avatar-dropdown .user-avatar {
            cursor: pointer;
            border: none;
            transition: opacity 0.2s ease, transform 0.15s ease;
        }
        .avatar-dropdown .user-avatar:hover {
            opacity: 0.88;
            transform: scale(1.06);
        }
        .avatar-dropdown .user-avatar:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(99, 160, 255, 0.45);
        }

        /* Avatar dropdown menu */
        .avatar-menu {
            width: 240px;
            border: none;
            border-radius: 14px;
            padding: 6px 0;
            margin-top: 10px !important;
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }
        .avatar-menu-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px 10px;
        }
        .avatar-menu-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            font-weight: 700;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .avatar-menu-name {
            font-weight: 600;
            font-size: 0.88rem;
            color: #111827;
            line-height: 1.3;
        }
        .avatar-menu-role {
            font-size: 0.75rem;
            color: #6b7280;
        }
        .avatar-menu-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 16px;
            font-size: 0.875rem;
            color: #374151;
            transition: background 0.15s ease;
        }
        .avatar-menu-item i {
            font-size: 1rem;
            color: #6b7280;
            width: 18px;
            text-align: center;
            flex-shrink: 0;
        }
        .avatar-menu-item:hover { background: #f3f4f6; color: #111827; }
        .avatar-menu-item:hover i { color: #3b82f6; }
        .avatar-menu-badge {
            margin-left: auto;
            background: #ef4444;
            color: white;
            font-size: 0.65rem;
            font-weight: 700;
            min-width: 18px;
            height: 18px;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 4px;
        }
        .avatar-menu-logout { color: #dc2626 !important; }
        .avatar-menu-logout i { color: #dc2626 !important; }
        .avatar-menu-logout:hover { background: #fef2f2 !important; }

        /* Dark Mode Overrides */
    [data-bs-theme="dark"] body { background: #121212 !important; }
    [data-bs-theme="dark"] .top-navbar { background: #1e293b !important; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
    [data-bs-theme="dark"] .sidebar { background: rgba(30, 30, 30, 0.9) !important; border-right: 1px solid rgba(255, 255, 255, 0.1); }
    [data-bs-theme="dark"] .main-content { background: #1a1a1a !important; color: #e0e0e0 !important; }
    [data-bs-theme="dark"] .sidebar .nav-link, [data-bs-theme="dark"] .sidebar h4, [data-bs-theme="dark"] .welcome-text { color: #ffffff !important; }
    [data-bs-theme="dark"] .sidebar-divider { background: rgba(255, 255, 255, 0.1); }
    [data-bs-theme="dark"] .notif-dropdown .dropdown-menu { background: #2d3748; color: white; }
    [data-bs-theme="dark"] .notif-header { background: #1a202c; color: white; border-bottom: 1px solid #4a5568; }
    [data-bs-theme="dark"] .notif-item { color: #cbd5e0; border-bottom: 1px solid #4a5568; }
    [data-bs-theme="dark"] .notif-item:hover { background: #2c5282; }
    [data-bs-theme="dark"] .avatar-menu { background-color: #1e293b; border: 1px solid rgba(255,255,255,0.08); }
    [data-bs-theme="dark"] .avatar-menu-name { color: #f1f5f9; }
    [data-bs-theme="dark"] .avatar-menu-role { color: #94a3b8; }
    [data-bs-theme="dark"] .avatar-menu-item { color: #cbd5e1; }
    [data-bs-theme="dark"] .avatar-menu-item i { color: #94a3b8; }
    [data-bs-theme="dark"] .avatar-menu-item:hover { background: #334155; color: #f1f5f9; }
    [data-bs-theme="dark"] .avatar-menu-item:hover i { color: #60a5fa; }
    [data-bs-theme="dark"] .avatar-menu-logout { color: #f87171 !important; }
    [data-bs-theme="dark"] .avatar-menu-logout i { color: #f87171 !important; }
    [data-bs-theme="dark"] .avatar-menu-logout:hover { background: rgba(239,68,68,0.1) !important; }
    [data-bs-theme="dark"] .dropdown-divider { border-color: rgba(255,255,255,0.08); }
    [data-bs-theme="dark"] .sidebar .nav-link:hover { background: #334155; color: #fff !important; }
    [data-bs-theme="dark"] .sidebar-footer { border-top: 1px solid rgba(255, 255, 255, 0.1); }
    [data-bs-theme="dark"] .notif-item.unread { background: rgba(55, 98, 200, 0.18); }
    [data-bs-theme="dark"] .notif-footer { border-top: 1px solid #4a5568; }
    [data-bs-theme="dark"] .notif-dropdown .text-dark { color: #f1f5f9 !important; }
    [data-bs-theme="dark"] .notif-message-preview { color: #94a3b8; }

    </style>
    <script src="/lgu-urban-planning/assets/js/user.js?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/lgu-urban-planning/assets/js/user.js'); ?>"></script>
    <script>
        /* Mobile sidebar overlay toggle — patches whatever toggleSidebar user.js defines */
        (function () {
            const _original = window.toggleSidebar;
            window.toggleSidebar = function () {
                if (_original) _original();
                const sidebar  = document.getElementById('sidebar');
                const overlay  = document.getElementById('sidebarOverlay');
                if (!sidebar || !overlay) return;
                /* On mobile (<= 768px) use .show; on desktop keep original collapse logic */
                if (window.innerWidth <= 768) {
                    sidebar.classList.toggle('show');
                    overlay.classList.toggle('active');
                }
            };
        })();
    </script>
</head>
<body <?php if (!empty($isAuthPage)) echo 'data-auth-page="true"'; ?>>

    <!-- INFRA Preloader (only shown once, right after login) -->
    <?php if ($showLoginPreloader): ?>
    <div id="infra-preloader" class="infra-preloader">
        <div class="preloader-logo-wrap">
            <div class="preloader-glow"></div>
            <div class="preloader-ring"></div>
            <img src="/lgu-urban-planning/assets/upad-logo.png" alt="UPAD Logo" class="preloader-logo">
        </div>
    </div>
    <script>
        (function () {
            var MIN_DISPLAY_MS = 2800;
            var start = Date.now();
            window.addEventListener('load', function () {
                var elapsed = Date.now() - start;
                var wait = Math.max(0, MIN_DISPLAY_MS - elapsed);
                setTimeout(function () {
                    var el = document.getElementById('infra-preloader');
                    if (el) el.classList.add('is-hidden');
                }, wait);
            });
        })();
    </script>
    <?php endif; ?>

    <nav class="top-navbar">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <button class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()">
                    <i class="bi bi-list"></i>
                </button>
                <h5 class="mb-0"><?php echo _ht('nav_title'); ?></h5>
            </div>
            <div class="user-info">

                <?php
                    $db = Database::getInstance();
                    $userId = $_SESSION['user_id'] ?? 0;
                    // Keep session avatar in sync with DB
                    $headerUser = $db->fetchOne("SELECT avatar FROM users WHERE id = ?", [$userId]);
                    if ($headerUser && !empty($headerUser['avatar'])) {
                        $_SESSION['avatar'] = $headerUser['avatar'];
                    }
                    $notifCount = $db->fetchOne("SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0", [$userId]);
                    $latestNotifs = $db->fetchAll("SELECT * FROM messages WHERE receiver_id = ? ORDER BY created_at DESC LIMIT 5", [$userId]);
                    $unreadCount = $notifCount['count'] ?? 0;
                ?>

                <!-- Icon Group: Dark Mode + Bell -->
                <div class="navbar-icon-group">
                    <button class="navbar-icon-btn" onclick="toggleDarkMode()" title="Toggle Dark/Light Mode">
                        <i id="themeIcon" class="bi bi-moon-stars"></i>
                    </button>

                    <!-- Notification Bell -->
                    <div class="dropdown notif-dropdown">
                        <button class="navbar-icon-btn position-relative" id="notifBell" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-bell"></i>
                            <?php if ($unreadCount > 0): ?>
                                <span class="notif-badge"><?php echo $unreadCount; ?></span>
                            <?php endif; ?>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="notifBell">
                            <div class="notif-header"><?php echo _ht('notif_header'); ?></div>
                            <div style="max-height: 300px; overflow-y: auto;">
                                <?php if (empty($latestNotifs)): ?>
                                    <div class="p-4 text-center">
                                        <i class="bi bi-chat-dots text-muted" style="font-size: 2rem; opacity: 0.4;"></i>
                                        <div class="fw-bold mt-2 small text-muted"><?php echo _ht('notif_empty'); ?></div>
                                        <small class="text-muted"><?php echo _ht('notif_caught_up'); ?></small>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($latestNotifs as $n): ?>
                                        <a href="/lgu-urban-planning/applicant/messages.php" class="notif-item <?php echo $n['is_read'] == 0 ? 'unread' : ''; ?>">
                                            <div class="fw-bold small text-dark"><?php echo htmlspecialchars($n['subject']); ?></div>
                                            <div class="notif-message-preview">
                                                <?php
                                                    $msg = preg_replace('/\s+/', ' ', trim(strip_tags($n['message'])));
                                                    echo htmlspecialchars(mb_substr($msg, 0, 55) . (mb_strlen($msg) > 55 ? '…' : ''));
                                                ?>
                                            </div>
                                            <small class="text-primary" style="font-size: 0.7rem; font-weight: 500;">
                                                <?php echo date('M d, h:i A', strtotime($n['created_at'])); ?>
                                            </small>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="notif-footer">
                                <a href="/lgu-urban-planning/applicant/messages.php" class="small text-decoration-none fw-bold text-primary"><?php echo _ht('notif_view_all'); ?></a>
                            </div>
                        </div>
                    </div>
                </div><!-- /.navbar-icon-group -->

                <!-- User Name + Role -->
                <div class="user-text-info d-none d-md-block">
                    <div class="user-fullname"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                    <div class="user-role"><?php echo Helper::getRoleName($_SESSION['role']); ?></div>
                </div>

                <!-- Avatar Dropdown -->
                <div class="dropdown avatar-dropdown">
                    <button class="user-avatar <?php echo !empty($_SESSION['avatar']) ? 'user-avatar-img' : ''; ?>" id="avatarDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Account options">
                        <?php if (!empty($_SESSION['avatar'])): ?>
                            <img src="<?php echo htmlspecialchars($_SESSION['avatar']); ?>" alt="Avatar" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                        <?php else: ?>
                            <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                        <?php endif; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end avatar-menu shadow" aria-labelledby="avatarDropdown">
                        <!-- Header -->
                        <li class="avatar-menu-header">
                            <div class="avatar-menu-avatar" <?php if (!empty($_SESSION['avatar'])) echo 'style="background:none;padding:0;"'; ?>>
                                <?php if (!empty($_SESSION['avatar'])): ?>
                                    <img src="<?php echo htmlspecialchars($_SESSION['avatar']); ?>" alt="Avatar" style="width:40px;height:40px;object-fit:cover;border-radius:50%;">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="avatar-menu-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                                <div class="avatar-menu-role"><?php echo Helper::getRoleName($_SESSION['role']); ?></div>
                            </div>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <a class="dropdown-item avatar-menu-item" href="/lgu-urban-planning/applicant/profile.php">
                                <i class="bi bi-person-circle"></i> <?php echo _ht('menu_profile'); ?>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item avatar-menu-item" href="/lgu-urban-planning/applicant/settings.php">
                                <i class="bi bi-gear"></i> <?php echo _ht('menu_settings'); ?>
                            </a>
                        </li>

                    </ul>
                </div>

            </div>
        </div>
    </nav>
    
    <div class="d-flex">
        <!-- Mobile sidebar backdrop overlay -->
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-top">
                <div class="sidebar-logo-container">
                    <img src="../assets/upad-logo.png" alt="UPAD Logo">
                </div>
                <div class="sidebar-divider"></div>

                <ul class="nav-list">
                    <li>
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="/lgu-urban-planning/user/index.php">
                            <i class="bi bi-house-door"></i> <span class="sidebar-text"><?php echo _ht('sidebar_dashboard'); ?></span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link" href="/lgu-urban-planning/applicant/apply.php">
                            <i class="bi bi-file-earmark-plus"></i> <span class="sidebar-text"><?php echo _ht('sidebar_apply'); ?></span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link" href="/lgu-urban-planning/applicant/applications.php">
                            <i class="bi bi-list-ul"></i> <span class="sidebar-text"><?php echo _ht('sidebar_apps'); ?></span>
                        </a>
                    </li>
                    <li>
    <a class="nav-link" href="/lgu-urban-planning/applicant/messages.php">
        <i class="bi bi-envelope"></i> <span class="sidebar-text"><?php echo _ht('sidebar_messages'); ?></span>
        <?php
            require_once __DIR__ . '/../modules/ApplicantSelfService/ApplicantController.php';
            $appController = new ApplicantController();
            $unread = $appController->getUnreadMessageCount();
        ?>
        <span id="sidebarNotifBadge" class="badge bg-danger ms-auto sidebar-text" 
              style="<?php echo ($unread > 0) ? '' : 'display: none;'; ?>">
            <?php echo ($unread > 0) ? $unread : ''; ?>
        </span>
    </a>
</li>
                </ul>
            </div>

            <div class="sidebar-footer">
                <span class="welcome-text"><?php echo _ht('sidebar_welcome'); ?>, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                <a href="/lgu-urban-planning/logout.php" class="logout-btn-sidebar">
                    <i class="bi bi-box-arrow-right"></i> <span class="sidebar-text"><?php echo _ht('sidebar_logout'); ?></span>
                </a>
            </div>
        </nav>

        <main class="main-content">