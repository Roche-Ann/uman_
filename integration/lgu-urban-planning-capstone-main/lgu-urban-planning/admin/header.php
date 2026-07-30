<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helper.php';
require_once __DIR__ . '/../core/Auth.php';
$dbHeader = Database::getInstance();
$unreadMessages = 0;
if (isset($_SESSION['user_id'])) {
    $row = $dbHeader->fetchOne("SELECT COUNT(*) AS cnt FROM messages WHERE receiver_id = ? AND is_read = 0", [$_SESSION['user_id']]);
    $unreadMessages = $row['cnt'] ?? 0;
}

$current_path = $_SERVER['PHP_SELF'];

// Show the preloader once, right after a successful login
$showLoginPreloader = !empty($_SESSION['show_preloader']);
unset($_SESSION['show_preloader']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>LGU Urban Planning System - Admin Portal</title>
    <link rel="icon" type="image/x-icon" href="/lgu-urban-planning/assets/upad-logo.png" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css" />
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

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

        /* ---------- LOGOUT OVERLAY (branded card) ---------- */
        #logout-overlay {
            position: fixed;
            inset: 0;
            z-index: 999999;
            background: rgba(15, 23, 42, 0.45);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            display: none;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        #logout-overlay.show {
            display: flex;
        }
        #logout-overlay.visible {
            opacity: 1;
        }

        .logout-card {
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
            padding: 36px 40px 32px;
            width: 300px;
            max-width: 88vw;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            transform: translateY(10px) scale(0.97);
            opacity: 0;
            transition: transform 0.35s cubic-bezier(.34,1.56,.64,1), opacity 0.35s ease;
        }
        #logout-overlay.visible .logout-card {
            transform: translateY(0) scale(1);
            opacity: 1;
        }

        .logout-card-logo {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            object-fit: contain;
            margin-bottom: 16px;
            box-shadow: 0 4px 14px rgba(26, 58, 110, 0.18);
        }

        .logout-card-title {
            font-size: 15px;
            font-weight: 700;
            color: #1e293b;
            letter-spacing: 0.2px;
            margin-bottom: 4px;
        }

        .logout-card-subtext {
            font-size: 12.5px;
            color: #64748b;
            margin-bottom: 22px;
            line-height: 1.4;
        }

        .logout-progress-track {
            width: 100%;
            height: 5px;
            border-radius: 999px;
            background: #e9edf5;
            overflow: hidden;
        }
        .logout-progress-fill {
            height: 100%;
            width: 40%;
            border-radius: 999px;
            background: linear-gradient(90deg, #1e3a8a, #4d76d6);
            animation: logout-progress-slide 1.1s ease-in-out infinite;
        }
        @keyframes logout-progress-slide {
            0%   { transform: translateX(-100%); }
            100% { transform: translateX(250%); }
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

        @keyframes infra-rise-in {
            from { opacity: 0; transform: translateY(6px); }
            to   { opacity: 1; transform: translateY(0);   }
        }


        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            background-attachment: fixed;
            min-height: 100vh;
        }
        
        .top-navbar {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            backdrop-filter: blur(10px);
            padding: 15px 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            position: sticky;
            top: 0;
            z-index: 1000;
            margin-bottom: 0;
        }
        
        .top-navbar h5 {
            color: white !important;
        }
        
        .top-navbar .user-info {
            color: white;
        }
        
        .top-navbar .user-info .text-muted {
            color: rgba(255, 255, 255, 0.8) !important;
        }
        
        .top-navbar .user-info div[style*="color: #1e293b"] {
            color: white !important;
        }
        
        /* --- SIDEBAR UPDATED --- */
        .sidebar {
            height: calc(100vh - 70px);
            background: linear-gradient(180deg, #f8f9fb 0%, #eef0f5 100%);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            box-shadow: 4px 0 25px rgba(0, 0, 0, 0.15);
            border-right: 1px solid rgba(255, 255, 255, 0.6);
            position: sticky;
            top: 70px;
            overflow: hidden;
            transition: all 0.3s ease;
            width: 250px;
            display: flex !important;
            flex-direction: column;
        }
        
        .sidebar.collapsed {
            width: 80px;
        }
        
        .sidebar.collapsed .sidebar-text {
            display: none;
        }
        
        .sidebar.collapsed .nav-link {
            justify-content: center;
            padding: 14px 10px;
        }
        
        .sidebar.collapsed h4 {
            font-size: 1rem;
            text-align: center;
        }
        
        /* --- SCROLLBAR HIDER LOGIC --- */
        .sidebar-content {
            flex: 1;
            padding: 20px 0;
            overflow-y: auto; 
            scrollbar-width: none; 
            -ms-overflow-style: none; 
        }

        .sidebar-content::-webkit-scrollbar {
            display: none; 
        }

        .sidebar-footer {
            padding: 10px 0 20px 0;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-toggle {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .sidebar-toggle:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .top-navbar .sidebar-toggle {
            margin-bottom: 0;
            background: rgba(255, 255, 255, 0.2);
        }
        
        .top-navbar .sidebar-toggle:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .sidebar > * {
            position: relative;
            z-index: 1;
        }

        .sidebar h4 {
            font-weight: 700;
            font-size: 1.5rem;
            letter-spacing: -0.5px;
            color: #000000;
            text-shadow: none;
            padding: 0 20px;
        }

        .sidebar-logo {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 16px 20px 8px;
        }

        .sidebar-logo img {
            width: 100px;
            height: 100px;
            object-fit: contain;
            transition: all 0.3s ease;
        }

        .sidebar.collapsed .sidebar-logo img {
            width: 40px;
            height: 40px;
        }
        
        .sidebar .nav-link {
            color: #000000;
            padding: 14px 20px;
            margin: 4px 12px;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .sidebar .nav-link i {
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }
        
        .sidebar .nav-link:hover {
            background: #97a4c2;
            color: #000;
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .sidebar .nav-link.active {
            background: #3762c8;
            color: #fff;
            box-shadow: 0 4px 16px rgba(55, 98, 200, 0.3);
            font-weight: 600;
        }

        .sidebar .nav-link.logout-btn {
            color: #dc3545;
        }

        .sidebar .nav-link.logout-btn:hover {
            background: rgba(220, 53, 69, 0.12);
            color: #dc3545;
        }
        
        .main-content {
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
            min-height: 100vh;
            padding: 30px;
            transition: all 0.3s ease;
            flex: 1;
        }
        
        /* ---- RIGHT-SIDE NAVBAR ---- */
        .user-info {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        /* Group dark mode + bell tightly together */
        .navbar-icon-group {
            display: flex;
            align-items: center;
            gap: 2px;
        }

        .navbar-icon-btn {
            text-decoration: none;
            color: white !important;
            font-size: 1rem;
            line-height: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 7px;
            transition: background 0.2s ease;
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

        /* Avatar circle */
        .user-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1rem;
            flex-shrink: 0;
            border: 2px solid rgba(255, 255, 255, 0.25);
        }
        
        .nav-link-toggle {
            cursor: pointer;
        }
        .submenu .nav-link {
            padding-left: 30px;
        }
        .toggle-caret {
            transition: transform 0.2s ease;
        }
        .collapse.show + .toggle-caret,
        .nav-link-toggle[aria-expanded="true"] .toggle-caret {
            transform: rotate(180deg);
        }

        /* --- NOTIFICATION DROPDOWN STYLES --- */
        .notif-dropdown .dropdown-menu {
            width: 320px;
            border: none;
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
            border-radius: 12px;
            padding: 0;
            margin-top: 10px !important;
            overflow: hidden;
        }
        .notif-header {
            background: #f8f9fa;
            padding: 12px 16px;
            border-bottom: 1px solid #eee;
            font-weight: 600;
            font-size: 0.85rem;
            color: #374151;
            letter-spacing: 0.3px;
        }
        .notif-item {
            padding: 12px 16px;
            border-bottom: 1px solid #f3f4f6;
            display: block;
            text-decoration: none;
            color: #333;
            transition: background 0.15s ease;
        }
        .notif-item:hover { background: #f0f4ff; }
        .notif-item.unread {
            background: #eef2ff;
            border-left: 3px solid #3b82f6;
        }
        .notif-msg-truncate {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 270px;
        }
        .notif-footer {
            padding: 10px 16px;
            text-align: center;
            border-top: 1px solid #eee;
            background: #fafafa;
        }

        /* --- DARK MODE UI --- */
        [data-bs-theme="dark"] body { color: #ced4da !important; }
        [data-bs-theme="dark"] h1, [data-bs-theme="dark"] h2, [data-bs-theme="dark"] h3, 
        [data-bs-theme="dark"] h4, [data-bs-theme="dark"] h5, [data-bs-theme="dark"] h6,
        [data-bs-theme="dark"] p, [data-bs-theme="dark"] label, [data-bs-theme="dark"] strong { color: #ffffff !important; }
        [data-bs-theme="dark"] .sidebar { background: rgba(30, 30, 30, 0.9) !important; border-right: 1px solid rgba(255, 255, 255, 0.1); }
        [data-bs-theme="dark"] .sidebar .nav-link, [data-bs-theme="dark"] .sidebar h4 { color: #ffffff !important; }
        [data-bs-theme="dark"] .sidebar .nav-link:hover { background: rgba(255,255,255,0.12); color: #fff; }
        [data-bs-theme="dark"] .sidebar .nav-link.logout-btn { color: #ffbaba !important; }
        [data-bs-theme="dark"] .main-content { background: #0f172a !important; }
        [data-bs-theme="dark"] .top-navbar { background: #1e293b !important; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        [data-bs-theme="dark"] .card { background-color: #1e293b !important; border: 1px solid rgba(255, 255, 255, 0.1) !important; }
        [data-bs-theme="dark"] .form-control, [data-bs-theme="dark"] .form-select { background-color: #0f172a !important; border-color: #334155 !important; color: #ffffff !important; }
        [data-bs-theme="dark"] .overdue-alert { background: linear-gradient(135deg, #2d1616 0%, #1e293b 100%) !important; border: 1px solid rgba(239, 68, 68, 0.2) !important; border-left: 5px solid #ef4444 !important; }
        [data-bs-theme="dark"] .overdue-alert .alert-text { color: #e2e8f0 !important; }
        [data-bs-theme="dark"] .overdue-alert strong { color: #ff8080 !important; }
        [data-bs-theme="dark"] .empty-placeholder { background-color: rgba(255, 255, 255, 0.05); color: #adb5bd !important; border: 1px solid rgba(255, 255, 255, 0.1); }
        [data-bs-theme="dark"] .fc {
        --fc-border-color: #444;
        --fc-page-bg-color: #2b3035;
        --fc-neutral-bg-color: #343a40;
        --fc-list-event-hover-bg-color: #3d4246;
        color: #dee2e6; }
        [data-bs-theme="dark"] .fc-theme-bootstrap5 a {
        color: #fff; }
        [data-bs-theme="dark"] .status-active { background-color: #0a2e1f; color: #75b798; }
        [data-bs-theme="dark"] .status-inactive { background-color: #2c0b0e; color: #ea868f; }
        [data-bs-theme="dark"] .card { border: 1px solid rgba(255,255,255,0.1); }
        [data-bs-theme="dark"] .table { color: #dee2e6; }
        [data-bs-theme="dark"] .modal-content { border: 1px solid rgba(255,255,255,0.15); }
        [data-bs-theme="dark"] .pagination .page-link { background-color: #1a1d20; border-color: #373b3e; color: #dee2e6; }
        [data-bs-theme="dark"] .pagination .page-item.active .page-link { background-color: #3d444b; border-color: #495057; }
        [data-bs-theme="dark"] .page-container { background-color: #0f172a !important; }
        [data-bs-theme="dark"] .card { background-color: #1e293b !important; border-color: rgba(255, 255, 255, 0.1) !important; }
        [data-bs-theme="dark"] .table-lgu thead { background-color: #334155 !important; border-top: 2px solid #22c55e !important; }
        [data-bs-theme="dark"] .table-lgu { color: #e2e8f0 !important; }
        [data-bs-theme="dark"] .table-hover tbody tr:hover { background-color: rgba(255, 255, 255, 0.05) !important; }
        [data-bs-theme="dark"] .modal-content
        [data-bs-theme="dark"] .modal-header
        [data-bs-theme="dark"] .modal-footer { background-color: #1e293b !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.1) !important; }
        [data-bs-theme="dark"] .bg-light { background-color: #334155 !important; color: #ffffff !important; }
        [data-bs-theme="dark"] .breadcrumb-item a { color: #4ade80 !important; }
        [data-bs-theme="dark"] .text-muted { color: #94a3b8 !important; }
        [data-bs-theme="dark"] .report-main-grid, 
        [data-bs-theme="dark"] .empty-report-state,
        [data-bs-theme="dark"] .chart-card-container,
        [data-bs-theme="dark"] .table-container-fixed,
        [data-bs-theme="dark"] .card {
            background-color: #1e293b !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
            color: #f1f5f9 !important;
        }

        [data-bs-theme="dark"] .card-header, 
        [data-bs-theme="dark"] .card-footer {
            background-color: #334155 !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
        }

        [data-bs-theme="dark"] .permits-table thead {
            background-color: #0f172a !important;
        }

        [data-bs-theme="dark"] .permits-table td {
            color: #cbd5e1 !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        [data-bs-theme="dark"] .empty-report-state {
            background: #1e293b !important;
            border: 2px dashed #475569 !important;
        }

        [data-bs-theme="dark"] .form-label, 
        [data-bs-theme="dark"] h2, 
        [data-bs-theme="dark"] h4, 
        [data-bs-theme="dark"] h5 {
            color: #ffffff !important;
        }

        /* ---- AVATAR DROPDOWN ---- */
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

        .avatar-menu {
            width: 240px;
            border: none;
            border-radius: 14px;
            padding: 6px 0;
            margin-top: 10px !important;
            overflow: hidden;
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
        .avatar-menu-item:hover {
            background: #f3f4f6;
            color: #111827;
        }
        .avatar-menu-item:hover i {
            color: #3b82f6;
        }

        /* Unread badge inside menu */
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

        .avatar-menu-logout {
            color: #dc2626 !important;
        }
        .avatar-menu-logout i {
            color: #dc2626 !important;
        }
        .avatar-menu-logout:hover {
            background: #fef2f2 !important;
        }

        /* Dark mode overrides */
        [data-bs-theme="dark"] .avatar-menu {
            background-color: #1e293b;
            border: 1px solid rgba(255,255,255,0.08);
        }
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

        /* ================================================
           MOBILE RESPONSIVE
           768px (Tablet) | 480px (Large Mobile) | 320px (Small Mobile)
           ================================================ */

        /* --- Sidebar overlay base (shared across all mobile breakpoints) --- */
        @media (max-width: 768px) {

            /* Navbar */
            .top-navbar {
                padding: 10px 16px;
            }
            .top-navbar h5 {
                font-size: 0.9rem;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                max-width: 180px;
            }

            /* Sidebar: off-canvas overlay on mobile */
            .sidebar {
                position: fixed !important;
                top: 0;
                left: -260px;
                height: 100vh !important;
                width: 250px !important;
                z-index: 1050;
                transition: left 0.3s ease;
                box-shadow: 4px 0 24px rgba(0,0,0,0.25);
            }
            .sidebar.mobile-open {
                left: 0 !important;
            }
            /* Backdrop overlay when sidebar is open */
            .sidebar-backdrop {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,0.45);
                z-index: 1040;
            }
            .sidebar-backdrop.show {
                display: block;
            }

            /* Main content fills full width since sidebar is off-canvas */
            .main-content {
                padding: 16px !important;
                min-height: 100vh;
                width: 100% !important;
            }

            /* Collapsed state does NOT apply on mobile */
            .sidebar.collapsed {
                width: 250px !important;
                left: -260px;
            }
            .sidebar.collapsed .sidebar-text { display: inline !important; }
            .sidebar.collapsed .nav-link { justify-content: flex-start !important; padding: 14px 20px !important; }
            .sidebar.collapsed h4 { font-size: 1.5rem; text-align: left; }

            /* User info adjustments */
            .user-info { gap: 8px; }
            .navbar-icon-btn { width: 28px; height: 28px; }

            /* Notification dropdown — keep usable width */
            .notif-dropdown .dropdown-menu { width: 290px; }
            .notif-msg-truncate { max-width: 230px; }
        }

        /* --- 480px: Large Mobile --- */
        @media (max-width: 480px) {

            .top-navbar {
                padding: 8px 12px;
            }
            .top-navbar h5 {
                font-size: 0.82rem;
                max-width: 140px;
            }

            .sidebar { width: 230px !important; left: -240px; }
            .sidebar.collapsed { width: 230px !important; left: -240px; }

            .main-content { padding: 12px !important; }

            .user-info { gap: 6px; }
            .user-avatar { width: 34px; height: 34px; font-size: 0.88rem; }
            .navbar-icon-btn { width: 26px; height: 26px; font-size: 0.9rem; }

            /* Notification dropdown */
            .notif-dropdown .dropdown-menu { width: 260px; right: 0; left: auto; }
            .notif-msg-truncate { max-width: 200px; }

            /* Avatar dropdown */
            .avatar-menu { width: 210px; }
        }

        /* --- 320px: Small Mobile --- */
        @media (max-width: 320px) {

            .top-navbar {
                padding: 7px 10px;
                gap: 6px;
            }
            .top-navbar h5 {
                font-size: 0.75rem;
                max-width: 100px;
            }

            .sidebar { width: 210px !important; left: -220px; }
            .sidebar.collapsed { width: 210px !important; left: -220px; }
            .sidebar .nav-link { padding: 11px 14px; font-size: 0.85rem; }
            .sidebar h4 { font-size: 1.1rem; padding: 0 14px; }

            .main-content { padding: 8px !important; }

            .user-info { gap: 4px; }
            .user-avatar { width: 30px; height: 30px; font-size: 0.8rem; }
            .navbar-icon-btn { width: 24px; height: 24px; font-size: 0.82rem; }
            .user-text-info { display: none !important; } /* too cramped */

            /* Notification dropdown — full-width-ish on tiny screen */
            .notif-dropdown .dropdown-menu { width: 240px; }
            .notif-msg-truncate { max-width: 175px; }
            .notif-header { font-size: 0.78rem; }
            .notif-item { padding: 9px 12px; }

            /* Avatar dropdown */
            .avatar-menu { width: 190px; }
            .avatar-menu-item { font-size: 0.8rem; padding: 8px 12px; }
            .avatar-menu-name { font-size: 0.8rem; }
            .avatar-menu-role { font-size: 0.68rem; }
        }
    </style>
    <script src="/lgu-urban-planning/assets/js/admin.js"></script>
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

    <!-- Mobile sidebar backdrop -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- Logging out overlay -->
    <div id="logout-overlay">
        <div class="logout-card">
            <img src="/lgu-urban-planning/assets/upad-logo.png" alt="Logo" class="logout-card-logo">
            <div class="logout-card-title">Logging out</div>
            <div class="logout-card-subtext">Please wait while we securely sign you out.</div>
            <div class="logout-progress-track">
                <div class="logout-progress-fill"></div>
            </div>
        </div>
    </div>
    <div class="container-fluid p-0">
        <nav class="top-navbar">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <button class="sidebar-toggle" id="sidebarToggle">
                        <i class="bi bi-list"></i>
                    </button>
                    <h5 class="mb-0" style="font-weight: 600;">Urban Planning and Development</h5>
                </div>
                <div class="user-info">
                    <!-- Icon Group: Dark Mode + Bell -->
                    <div class="navbar-icon-group">
                        <button class="btn btn-link text-white p-0 navbar-icon-btn" onclick="toggleDarkMode()" title="Toggle Dark/Light Mode">
                            <i id="themeIcon" class="bi bi-moon-stars"></i>
                        </button>

                    <!-- Notification Bell -->
                    <?php
                    $uId = $_SESSION['user_id'] ?? 0;
                    $latestNotifs = $dbHeader->fetchAll("SELECT * FROM messages WHERE receiver_id = ? ORDER BY created_at DESC LIMIT 5", [$uId]);
                    ?>
                    <div class="dropdown notif-dropdown">
                        <button class="btn btn-link text-white p-0 position-relative navbar-icon-btn" id="notifBell" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-bell"></i>
                            <?php if ($unreadMessages > 0): ?>
                                <span class="notif-badge"><?php echo $unreadMessages; ?></span>
                            <?php endif; ?>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="notifBell">
                            <div class="notif-header">Notifications</div>
                            <div style="max-height: 300px; overflow-y: auto;">
                                <?php if (empty($latestNotifs)): ?>
                                    <div class="p-4 text-center">
                                        <i class="bi bi-chat-dots text-muted" style="font-size: 2rem; opacity: 0.4;"></i>
                                        <div class="fw-bold mt-2 small text-muted">No notifications yet.</div>
                                        <small class="text-muted">You're all caught up!</small>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($latestNotifs as $n): 
                                        $preview = mb_strimwidth(strip_tags($n['message']), 0, 80, '…');
                                    ?>
                                        <a href="/lgu-urban-planning/admin/messages.php" class="notif-item <?php echo $n['is_read'] == 0 ? 'unread' : ''; ?>">
                                            <div class="fw-bold small text-dark"><?php echo htmlspecialchars($n['subject']); ?></div>
                                            <div class="text-muted small notif-msg-truncate"><?php echo htmlspecialchars($preview); ?></div>
                                            <small class="text-primary" style="font-size: 0.7rem; font-weight: 500;">
                                                <?php echo date('M d, h:i A', strtotime($n['created_at'])); ?>
                                            </small>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="notif-footer">
                                <a href="/lgu-urban-planning/admin/messages.php" class="small text-decoration-none fw-bold text-primary">View All Messages</a>
                            </div>
                        </div>
                    </div>
                    </div><!-- /.navbar-icon-group -->

                    <!-- User Info: Name + Role -->
                    <div class="user-text-info d-none d-md-block">
                        <div class="user-fullname"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                        <div class="user-role"><?php echo Helper::getRoleName($_SESSION['role']); ?></div>
                    </div>

                    <!-- Avatar Dropdown -->
                    <div class="dropdown avatar-dropdown">
                        <button class="user-avatar" id="avatarDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Account options">
                            <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end avatar-menu shadow" aria-labelledby="avatarDropdown">
                            <!-- Header -->
                            <li class="avatar-menu-header">
                                <div class="avatar-menu-avatar">
                                    <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="avatar-menu-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                                    <div class="avatar-menu-role"><?php echo Helper::getRoleName($_SESSION['role']); ?></div>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider my-1"></li>

                            <!-- Account options -->
                            <li>
                                <a class="dropdown-item avatar-menu-item" href="/lgu-urban-planning/admin/profile.php">
                                    <i class="bi bi-person-circle"></i> My Profile
                                </a>
                            </li>
                            <li>
                                <?php if (in_array($_SESSION['role'], ['admin', 'super_admin', 'zoning_officer', 'building_official', 'assessor', 'inspector'])): ?>
                                <a class="dropdown-item avatar-menu-item" href="/lgu-urban-planning/admin/settings.php">
                                    <i class="bi bi-gear"></i> Settings
                                </a>
                                <?php endif; ?>
                            </li>
                            <li>
                                <a class="dropdown-item avatar-menu-item" href="/lgu-urban-planning/admin/messages.php">
                                    <i class="bi bi-envelope"></i> Messages
                                    <?php if ($unreadMessages > 0): ?>
                                        <span class="avatar-menu-badge"><?php echo $unreadMessages; ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li>
                                <a class="dropdown-item avatar-menu-item avatar-menu-logout" href="/lgu-urban-planning/logout.php">
                                    <i class="bi bi-box-arrow-right"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

        </nav>
        
        <div class="d-flex">
            <nav class="sidebar" id="sidebar">
                <div class="sidebar-content">
                    <div class="sidebar-logo mb-3">
                        <img src="/lgu-urban-planning/assets/upad-logo.png" alt="Logo">
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos($current_path, '/admin/index.php') !== false) ? 'active' : ''; ?>" href="/lgu-urban-planning/admin/index.php" title="Dashboard">
                                <i class="bi bi-house-door"></i> <span class="sidebar-text">Dashboard</span>
                            </a>
                        </li>
                        
                        <?php if ($_SESSION['role'] === 'inspector'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos($current_path, 'my_tasks.php') !== false) ? 'active' : ''; ?>" href="/lgu-urban-planning/permit/my_tasks.php">
                                <i class="bi bi-clipboard-check"></i> <span class="sidebar-text">My Inspections</span>
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php 
                        $isMonitoring = (strpos($current_path, '/monitoring/') !== false);
                        $isApps = (basename($current_path) == 'applications.php');
                        $appsOpen = $isApps || $isMonitoring; 
                        ?>
                        
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center justify-content-between nav-link-toggle <?php echo $appsOpen ? '' : 'collapsed'; ?>" data-bs-toggle="collapse" data-bs-target="#sidebarApps" aria-expanded="<?php echo $appsOpen ? 'true' : 'false'; ?>" aria-controls="sidebarApps">
                                <div><i class="bi bi-file-earmark-text"></i> <span class="sidebar-text">Applications</span></div>
                                <i class="bi bi-caret-down-fill sidebar-text toggle-caret" style="font-size: 0.8rem;"></i>
                            </a>
                            <div class="collapse <?php echo $appsOpen ? 'show' : ''; ?>" id="sidebarApps">
                                <ul class="nav flex-column submenu ms-3 mb-2">
                                    <?php if ($_SESSION['role'] !== 'inspector'): ?>
                                    <li class="nav-item">
                                        <a class="nav-link <?php echo $isApps ? 'active' : ''; ?>" href="/lgu-urban-planning/permit/applications.php">
                                            <span class="sidebar-text">Development Permits</span>
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                    <?php if (!in_array($_SESSION['role'], ['assessor'])): ?>
                                    <li class="nav-item">
                                        <a class="nav-link <?php echo $isMonitoring ? 'active' : ''; ?>" href="/lgu-urban-planning/monitoring/index.php">
                                            <span class="sidebar-text">Monitoring &amp; Inspections</span>
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </li>

                        <?php if ($_SESSION['role'] !== 'inspector'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos($current_path, '/gis/') !== false) ? 'active' : ''; ?>" href="/lgu-urban-planning/gis/map.php" title="GIS Map">
                                <i class="bi bi-map"></i> <span class="sidebar-text">GIS Map</span>
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if (in_array($_SESSION['role'], ['admin', 'super_admin'])): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo (basename($current_path) == 'users.php') ? 'active' : ''; ?>" href="/lgu-urban-planning/admin/users.php" title="User Management">
                                    <i class="bi bi-people"></i> <span class="sidebar-text">User Management</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo (basename($current_path) == 'audit-logs.php') ? 'active' : ''; ?>" href="/lgu-urban-planning/admin/audit-logs.php" title="Audit Logs">
                                    <i class="bi bi-journal-text"></i> <span class="sidebar-text">Audit Logs</span>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php if (in_array($_SESSION['role'], ['admin', 'super_admin'])): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo (strpos($current_path, '/reports/') !== false) ? 'active' : ''; ?>" href="/lgu-urban-planning/reports/index.php" title="Reports">
                                    <i class="bi bi-graph-up"></i> <span class="sidebar-text">Reports</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="sidebar-footer">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link logout-btn text-black" href="/lgu-urban-planning/logout.php" title="Logout">
                                <i class="bi bi-box-arrow-right text-black"></i> <span class="sidebar-text">Logout</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            <main class="main-content" style="flex: 1;">
<script>
(function () {
    function isMobile() { return window.innerWidth <= 768; }

    function closeMobileSidebar() {
        var s = document.getElementById('sidebar');
        var b = document.getElementById('sidebarBackdrop');
        if (s) s.classList.remove('mobile-open');
        if (b) b.classList.remove('show');
    }

    // Override toggleSidebar — runs after admin.js has already set its version
    window.toggleSidebar = function () {
        var sidebar  = document.getElementById('sidebar');
        var backdrop = document.getElementById('sidebarBackdrop');
        if (!sidebar) return;
        if (isMobile()) {
            sidebar.classList.toggle('mobile-open');
            if (backdrop) backdrop.classList.toggle('show');
        } else {
            // Desktop: original collapse behaviour
            sidebar.classList.toggle('collapsed');
        }
    };

    window.closeMobileSidebar = closeMobileSidebar;

    // Also re-wire the toggle button directly so onclick attr can't be stale
    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('sidebarToggle');
        if (btn) {
            btn.onclick = null; // clear any existing onclick
            btn.addEventListener('click', window.toggleSidebar);
        }

        var backdrop = document.getElementById('sidebarBackdrop');
        if (backdrop) {
            backdrop.addEventListener('click', closeMobileSidebar);
        }

        // Close on nav-link click (mobile only)
        document.querySelectorAll('.sidebar .nav-link:not(.nav-link-toggle)').forEach(function (l) {
            l.addEventListener('click', function () {
                if (isMobile()) closeMobileSidebar();
            });
        });

        // Clean up on resize to desktop
        window.addEventListener('resize', function () {
            if (!isMobile()) closeMobileSidebar();
        });

        // Logout confirmation — intercept both logout links (sidebar + avatar menu)
        var logoutLinks = document.querySelectorAll('a[href$="logout.php"]');
        var logoutOverlay = document.getElementById('logout-overlay');

        logoutLinks.forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                var href = link.getAttribute('href');

                if (logoutOverlay) {
                    logoutOverlay.classList.add('show');
                    // Force a reflow so the opacity transition actually plays
                    void logoutOverlay.offsetWidth;
                    logoutOverlay.classList.add('visible');
                }

                setTimeout(function () {
                    window.location.href = href;
                }, 1200);
            });
        });
    });
})();
</script>