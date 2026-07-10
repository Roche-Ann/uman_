<?php
// includes/utilities_sidebar.php
require_once 'auth.php';
require_once __DIR__ . '/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$userName  = htmlspecialchars($_SESSION['user_name'] ?? '', ENT_QUOTES, 'UTF-8');
$userType  = $_SESSION['user_type'] ?? '';
$currentPage = basename($_SERVER['PHP_SELF']);

// Helper to mark active link
function sidebarActive(string $page, string $current): string {
    return $page === $current ? ' active' : '';
}
?>
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
        overflow-y: auto;
        font-family: 'Poppins', sans-serif;
    }

    .sidebar-top {
        display: flex;
        flex-direction: column;
        flex-grow: 1;
        padding: 20px 0;
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
    .sidebar-nav.collapsed .user-info,
    .sidebar-nav.collapsed .back-link { display: none; }
    .sidebar-nav.collapsed .nav-link {
        justify-content: center;
        padding: 12px 0;
    }
    .sidebar-nav.collapsed .nav-link i { margin-right: 0; width: auto; }

    /* Sync main-content margin with sidebar */
    .main-content { margin-left: 280px; transition: margin-left 0.25s ease; }
    .main-content.collapsed { margin-left: 90px; }

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
    .dark-theme [style*="color: rgb(44, 62, 80)"] {
        color: #f8fafc !important;
    }
    .dark-theme [style*="color:#64748b"],
    .dark-theme [style*="color: #64748b"] {
        color: #cbd5e1 !important;
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
</style>

<!-- ===== SIDEBAR HTML ===== -->
<nav class="sidebar-nav" id="sidebar-nav" role="navigation" aria-label="Utilities Navigation">
    <div class="sidebar-top">
        <button class="collapse-btn" id="collapse-btn" aria-label="Toggle sidebar" aria-pressed="false">&#8249;</button>

        <div class="site-logo">
            <img src="assets/images/logocityhall.png" alt="LGU Logo">
            <div class="logo-text">
                <h3>Utilities Management</h3>
                <p>Welcome, <?php echo $userName; ?></p>
            </div>
        </div>

        <div class="sidebar-divider"></div>

        <ul class="nav-list">
            <li class="nav-section-header">MAIN NAVIGATION</li>
            <?php if ($userType === 'employee'): ?>
            <li>
                <a href="utilities_dashboard.php" class="nav-link<?php echo sidebarActive('utilities_dashboard.php', $currentPage); ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="link-label">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="assets_dashboard.php" class="nav-link<?php echo $currentPage === 'assets_dashboard.php' ? ' active' : ''; ?>">
                    <i class="fas fa-chart-line"></i>
                    <span class="link-label">Asset Dashboard</span>
                </a>
            </li>
            <li>
                <a href="assets_crud.php" class="nav-link<?php echo ($currentPage === 'assets_crud.php' || (strpos($currentPage, 'assets_') === 0 && $currentPage !== 'assets_dashboard.php')) ? ' active' : ''; ?>">
                    <i class="fas fa-warehouse"></i>
                    <span class="link-label">Asset Inventory</span>
                </a>
            </li>
            <li>
                <a href="incidents_dashboard.php" class="nav-link<?php echo (strpos($currentPage, 'incidents_') === 0) ? ' active' : ''; ?>">
                    <i class="fas fa-bullhorn"></i>
                    <span class="link-label">Incident Reports</span>
                </a>
            </li>
            <li>
                <a href="maintenance_dashboard.php" class="nav-link<?php echo (strpos($currentPage, 'maintenance_') === 0) ? ' active' : ''; ?>">
                    <i class="fas fa-tools"></i>
                    <span class="link-label">Maintenance Coordination</span>
                </a>
            </li>
            <li>
                <a href="planning_dashboard.php" class="nav-link<?php echo (strpos($currentPage, 'planning_') === 0) ? ' active' : ''; ?>">
                    <i class="fas fa-map-marked-alt"></i>
                    <span class="link-label">Utility Planning</span>
                </a>
            </li>
            <li>
                <a href="energy_dashboard.php" class="nav-link<?php echo (strpos($currentPage, 'energy_') === 0) ? ' active' : ''; ?>">
                    <i class="fas fa-bolt"></i>
                    <span class="link-label">Energy Management</span>
                </a>
            </li>
            <li>
                <a href="facility_dashboard.php" class="nav-link<?php echo (strpos($currentPage, 'facility_') === 0) ? ' active' : ''; ?>">
                    <i class="fas fa-warehouse"></i>
                    <span class="link-label">Public Facilities</span>
                </a>
            </li>
            <?php else: ?>
            <li>
                <a href="citizen.php" class="nav-link<?php echo sidebarActive('citizen.php', $currentPage); ?>">
                    <i class="fas fa-home"></i>
                    <span class="link-label">Home Dashboard</span>
                </a>
            </li>
            <li>
                <a href="citizen_reports.php" class="nav-link<?php echo sidebarActive('citizen_reports.php', $currentPage); ?>">
                    <i class="fas fa-file-invoice"></i>
                    <span class="link-label">Track Reports</span>
                </a>
            </li>
            <li>
                <a href="citizen_advisories.php" class="nav-link<?php echo sidebarActive('citizen_advisories.php', $currentPage); ?>">
                    <i class="fas fa-bullhorn"></i>
                    <span class="link-label">LGU Advisories</span>
                </a>
            </li>
            <li>
                <a href="citizen_facilities.php" class="nav-link<?php echo sidebarActive('citizen_facilities.php', $currentPage); ?>">
                    <i class="fas fa-warehouse"></i>
                    <span class="link-label">Public Venues</span>
                </a>
            </li>
            <li>
                <a href="citizen_notifications.php" class="nav-link<?php echo sidebarActive('citizen_notifications.php', $currentPage); ?>">
                    <i class="fas fa-bell"></i>
                    <span class="link-label">Notifications</span>
                </a>
            </li>
            <li>
                <a href="citizen_profile.php" class="nav-link<?php echo sidebarActive('citizen_profile.php', $currentPage); ?>">
                    <i class="fas fa-user-cog"></i>
                    <span class="link-label">Profile Settings</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </div>

    <div>
        <div class="sidebar-divider"></div>
        <div class="user-info">
            <div class="user-welcome"><i class="fas fa-user-circle" style="margin-right:6px; color:#6384d2;"></i><?php echo $userName; ?></div>
            <button class="theme-toggle-btn" onclick="toggleTheme()" style="margin-bottom: 8px;">
                <i class="fas fa-moon" id="theme-toggle-icon"></i> <span id="theme-toggle-text">Dark Mode</span>
            </button>
            <button class="logout-btn" onclick="confirmLogout()">
                <i class="fas fa-sign-out-alt"></i> Logout
            </button>
        </div>
    </div>
</nav>

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
            <a href="logout.php" class="logout-modal-btn confirm-btn">Logout</a>
        </div>
    </div>
</div>

<script>
function applyTheme(theme) {
    const icon = document.getElementById('theme-toggle-icon');
    const text = document.getElementById('theme-toggle-text');
    if (theme === 'dark') {
        document.body.classList.add('dark-theme');
        document.documentElement.classList.add('dark-theme');
        if (icon) icon.className = 'fas fa-sun';
        if (text) text.textContent = 'Light Mode';
    } else {
        document.body.classList.remove('dark-theme');
        document.documentElement.classList.remove('dark-theme');
        if (icon) icon.className = 'fas fa-moon';
        if (text) text.textContent = 'Dark Mode';
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
    const sidebar  = document.getElementById('sidebar-nav');
    const collapseBtn = document.getElementById('collapse-btn');
    const mainContent = document.querySelector('.main-content');
    if (!collapseBtn || !sidebar) return;

    collapseBtn.addEventListener('click', () => {
        const isCollapsed = sidebar.classList.toggle('collapsed');
        if (mainContent) mainContent.classList.toggle('collapsed', isCollapsed);
        collapseBtn.innerHTML = isCollapsed ? '&#8250;' : '&#8249;';
        collapseBtn.setAttribute('aria-pressed', isCollapsed);
    });
})();
</script>