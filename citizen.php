<?php
// citizen.php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'] ?? 3;
$userName = $_SESSION['user_name'] ?? $_SESSION['full_name'] ?? 'Resident';

// Fetch resident stats
$totalReports = 0;
$resolvedReports = 0;
$activeReports = 0;

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM utility_incidents WHERE resident_id = ?");
    $stmt->execute([$userId]);
    $totalReports = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM utility_incidents WHERE resident_id = ? AND status IN ('Resolved', 'Closed')");
    $stmt->execute([$userId]);
    $resolvedReports = $stmt->fetchColumn();

    $activeReports = $totalReports - $resolvedReports;
} catch (Exception $e) {
    // Fallback if schema differs slightly
}

// The Facebook Page Plugin is handled client-side via embedded iframes in the HTML below.
// This avoids server-side blocking from Facebook.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            if (savedTheme === 'dark') {
                document.documentElement.classList.add('dark-theme');
            }
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LGU Citizen Portal</title>
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            background: url("assets/images/cityhall.jpeg") center/cover no-repeat fixed;
            position: relative;
        }

        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            backdrop-filter: blur(6px);
            background: rgba(0, 0, 0, 0.35);
            z-index: 0;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px 40px 120px 40px;
            transition: margin-left 0.25s ease;
            z-index: 1;
            position: relative;
        }

        .main-content.collapsed {
            margin-left: 90px;
        }

        .card {
            width: 100%;
            max-width: 1700px;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(15px);
            border-radius: 18px;
            padding: 40px 40px 50px 40px;
            color: #000;
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,0.25);
            margin-bottom: 60px;
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .dashboard-header h1 {
            color: #2c3e50;
            font-size: 32px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .dashboard-header h1 i { color: #3762c8; }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary { background: #3762c8; color: white; }
        .btn-primary:hover { background: #2851b0; }

        .btn-outline { background: transparent; border: 1px solid #cbd5e1; color: #64748b; }
        .btn-outline:hover { background: #f8f9fa; color: #2c3e50; }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 35px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-left: 5px solid #cbd5e1;
        }

        .stat-card.total { border-left-color: #3762c8; }
        .stat-card.active { border-left-color: #f1c40f; }
        .stat-card.resolved { border-left-color: #2ecc71; }

        .stat-info h3 {
            font-size: 24px;
            font-weight: 700;
            color: #2c3e50;
        }

        .stat-info p {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
            margin-top: 3px;
        }

        .stat-icon { font-size: 26px; color: #cbd5e1; }

        .dashboard-layout {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 30px;
            margin-bottom: 35px;
        }

        @media (max-width: 1000px) {
            .dashboard-layout { grid-template-columns: 1fr; }
        }

        .box {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .box h3 {
            font-size: 16px;
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 2px solid #f1f2f6;
            padding-bottom: 10px;
        }

        /* Advisories Layout */
        .advisories-layout {
            display: grid;
            grid-template-columns: 500px 1fr;
            gap: 28px;
            align-items: start;
        }

        @media (max-width: 1100px) {
            .advisories-layout {
                grid-template-columns: 1fr;
            }
        }

        .advisory-tabs-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 14px;
        }

        .advisory-tabs {
            display: inline-flex;
            background: rgba(226, 232, 240, 0.8);
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: 4px;
            gap: 4px;
        }

        .advisory-tab-btn {
            border: none;
            background: transparent;
            padding: 9px 20px;
            border-radius: 9px;
            font-size: 13.5px;
            font-weight: 600;
            color: #475569;
            cursor: pointer;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .advisory-tab-btn:hover {
            color: #1e293b;
            background: rgba(255, 255, 255, 0.4);
        }

        .advisory-tab-btn.active {
            background: #3762c8;
            color: #ffffff !important;
            box-shadow: 0 4px 12px rgba(55, 98, 200, 0.3);
        }

        .feed-container {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: center;
            min-height: 700px;
            max-width: 500px;
            width: 100%;
            transition: all 0.3s ease;
        }

        .feed-pane {
            display: none;
            width: 100%;
            justify-content: center;
        }

        .feed-pane.active {
            display: flex;
        }

        /* Emergency Sidebar */
        .emergency-sidebar {
            display: flex;
            flex-direction: column;
            gap: 18px;
            width: 100%;
        }

        .emergency-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .emergency-card-title {
            font-size: 15px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .emergency-card-title span {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .hotline-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        @media (max-width: 600px) {
            .hotline-list {
                grid-template-columns: 1fr;
            }
        }

        .hotline-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            background: #f8fafc;
            border-radius: 10px;
            border: 1px solid #edf2f7;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .hotline-item:hover {
            background: #eff6ff;
            border-color: #bfdbfe;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.04);
        }

        .hotline-info {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
        }

        .hotline-number {
            font-size: 13px;
            font-weight: 700;
            color: #3762c8;
            background: rgba(55, 98, 200, 0.08);
            padding: 4px 8px;
            border-radius: 6px;
        }

        /* Dual Feeds + Sidebar Layout */
        .advisories-main-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 340px;
            gap: 24px;
            align-items: start;
        }

        @media (max-width: 1300px) {
            .advisories-main-grid {
                grid-template-columns: 1fr;
            }
        }

        .dual-feeds-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
            gap: 20px;
        }

        .feed-box-card {
            background: #ffffff;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        /* QC Gov Card Theming */
        .feed-box-qcgov {
            border: 1px solid rgba(59, 130, 246, 0.35);
            border-top: 3px solid #3b82f6;
        }

        /* QCDRRMC Card Theming */
        .feed-box-qcdrrmc {
            border: 1px solid rgba(239, 68, 68, 0.4);
            border-top: 3px solid #ef4444;
        }

        .feed-box-header {
            padding: 12px 16px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 13.5px;
            font-weight: 700;
            color: #1e293b;
        }

        .feed-box-header-title {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-open-fb {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            background: rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(0, 0, 0, 0.08);
            padding: 4px 8px;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-open-fb:hover {
            color: #1e293b;
            background: rgba(0, 0, 0, 0.08);
            transform: translateY(-1px);
        }

        .feed-box-body {
            display: flex;
            justify-content: center;
            min-height: 700px;
            width: 100%;
            background: #ffffff;
            border-radius: 0 0 14px 14px;
            overflow: hidden;
        }

        /* Emergency & Weather Sidebar */
        .emergency-sidebar {
            display: flex;
            flex-direction: column;
            gap: 16px;
            width: 100%;
        }

        .emergency-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .emergency-card-title {
            font-size: 14.5px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .emergency-card-title span {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .hotline-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .hotline-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            background: #f8fafc;
            border-radius: 10px;
            border: 1px solid #edf2f7;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .hotline-item:hover {
            background: #eff6ff;
            border-color: #bfdbfe;
            transform: translateX(2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.04);
        }

        .hotline-info {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
        }

        .hotline-number {
            font-size: 13px;
            font-weight: 700;
            color: #3762c8;
            background: rgba(55, 98, 200, 0.08);
            padding: 4px 8px;
            border-radius: 6px;
        }

        /* Slim Compact Weather Strip */
        .weather-compact-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .weather-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 14px;
            font-weight: 700;
            color: #1e293b;
        }

        .weather-main-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 12px;
            background: linear-gradient(135deg, rgba(55, 98, 200, 0.06), rgba(2, 132, 199, 0.08));
            border-radius: 10px;
            margin-bottom: 12px;
        }

        .weather-temp-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .weather-icon-large {
            font-size: 26px;
            line-height: 1;
        }

        .weather-temp-value {
            font-size: 20px;
            font-weight: 700;
            color: #1e293b;
        }

        .weather-desc-text {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
        }

        .weather-stats-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .weather-stat-pill {
            background: #f8fafc;
            border: 1px solid #edf2f7;
            border-radius: 8px;
            padding: 8px 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
        }

        .weather-stat-pill i {
            color: #0284c7;
            font-size: 14px;
        }

        .weather-stat-val {
            font-weight: 700;
            color: #1e293b;
        }

        .tab-alert-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(239, 68, 68, 0.15);
            color: #dc2626;
            border: 1px solid rgba(239, 68, 68, 0.3);
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 99px;
            letter-spacing: 0.3px;
        }

        .pulse-dot-red {
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #ef4444;
            box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.25);
            animation: pulseRed 1.8s infinite;
        }

        @keyframes pulseRed {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.6); }
            70% { transform: scale(1.1); box-shadow: 0 0 0 5px rgba(239, 68, 68, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }

        /* Dark Theme Support */
        .dark-theme .feed-box-qcgov {
            background: #1e293b;
            border-color: rgba(59, 130, 246, 0.45);
            border-top: 3px solid #3b82f6;
            box-shadow: 0 4px 20px -2px rgba(59, 130, 246, 0.18);
        }

        .dark-theme .feed-box-qcdrrmc {
            background: #1e293b;
            border-color: rgba(239, 68, 68, 0.5);
            border-top: 3px solid #ef4444;
            box-shadow: 0 4px 20px -2px rgba(239, 68, 68, 0.22);
        }

        .dark-theme .feed-box-header {
            background: #0f172a;
            border-color: #334155;
            color: #f8fafc;
        }

        .dark-theme .btn-open-fb {
            color: #94a3b8;
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(255, 255, 255, 0.12);
        }

        .dark-theme .btn-open-fb:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.14);
        }

        .dark-theme .emergency-card,
        .dark-theme .weather-compact-card {
            background: #1e293b;
            border-color: #334155;
            color: #f8fafc;
        }

        .dark-theme .emergency-card-title,
        .dark-theme .weather-card-header {
            color: #f8fafc;
        }

        .dark-theme .hotline-item,
        .dark-theme .weather-stat-pill {
            background: #0f172a;
            border-color: #334155;
        }

        .dark-theme .hotline-info {
            color: #e2e8f0;
        }

        .dark-theme .hotline-number {
            color: #60a5fa;
            background: rgba(96, 165, 250, 0.12);
        }

        .dark-theme .weather-main-row {
            background: linear-gradient(135deg, rgba(30, 58, 138, 0.35), rgba(15, 23, 42, 0.5));
        }

        .dark-theme .weather-temp-value,
        .dark-theme .weather-stat-val {
            color: #f8fafc;
        }

        .dark-theme .weather-desc-text {
            color: #94a3b8;
        }

        .dark-theme .tab-alert-badge {
            background: rgba(239, 68, 68, 0.25);
            color: #fca5a5;
            border-color: rgba(239, 68, 68, 0.4);
        }
    </style>
</head>
<body>

<?php include 'includes/utilities_sidebar.php'; ?>

<main class="main-content" id="mainContent">
    <div class="card">
        
        <!-- Header -->
        <div class="dashboard-header">
            <div>
                <h1><i class="fas fa-home"></i> Resident Portal</h1>
                <p style="color: #64748b; font-size: 14px; margin-top: 5px;">Welcome back, <?php echo htmlspecialchars($userName); ?>. Request LGU assets and track utility advisories.</p>
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="citizen_asset_request.php" class="btn btn-primary" style="background-color: #3762c8; border-color: #3762c8; color: #fff;"><i class="fas fa-boxes-stacked"></i> Request LGU Assets</a>
            </div>
        </div>

        <!-- Facebook SDK -->
        <div id="fb-root"></div>
        <script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v18.0" nonce="12345678"></script>

        <!-- Main Layout Split -->
        <div class="box" style="padding: 25px;">
            <div style="margin-bottom: 20px;">
                <h3 style="font-size: 1.25rem; color: #1e293b; display: flex; align-items: center; gap: 8px; font-weight: 700;">
                    <i class="fas fa-bullhorn" style="color: #3762c8;"></i> Latest LGU Advisories
                </h3>
            </div>

            <!-- Dual Feeds + Side Command Layout -->
            <div class="advisories-main-grid">
                
                <!-- Left: Side-by-Side Dual Feeds -->
                <div class="dual-feeds-container">
                    
                    <!-- 1. Quezon City Government Feed Box -->
                    <div class="feed-box-card feed-box-qcgov">
                        <div class="feed-box-header">
                            <div class="feed-box-header-title">
                                <i class="fas fa-landmark" style="color: #3b82f6;"></i>
                                <span>Quezon City Government</span>
                            </div>
                            <a href="https://www.facebook.com/QCGov" target="_blank" rel="noopener noreferrer" class="btn-open-fb">
                                Open Facebook <i class="fas fa-arrow-up-right-from-square"></i>
                            </a>
                        </div>
                        <div class="feed-box-body">
                            <div class="fb-page" 
                                data-href="https://www.facebook.com/QCGov" 
                                data-tabs="timeline" 
                                data-width="500" 
                                data-height="700" 
                                data-small-header="false" 
                                data-adapt-container-width="true" 
                                data-hide-cover="false" 
                                data-show-facepile="false"
                                style="width: 100%;">
                                <blockquote cite="https://www.facebook.com/QCGov" class="fb-xfbml-parse-ignore"><a href="https://www.facebook.com/QCGov">Quezon City Government</a></blockquote>
                            </div>
                        </div>
                    </div>

                    <!-- 2. QCDRRMC Disaster Alerts Feed Box -->
                    <div class="feed-box-card feed-box-qcdrrmc">
                        <div class="feed-box-header">
                            <div class="feed-box-header-title">
                                <i class="fas fa-shield-halved" style="color: #ef4444;"></i>
                                <span>QCDRRMC Disaster Alerts</span>
                                <span id="tabAlertBadge" class="tab-alert-badge" style="display: none;">
                                    <span class="pulse-dot-red"></span> Alert Active
                                </span>
                            </div>
                            <a href="https://www.facebook.com/qcdrrmc" target="_blank" rel="noopener noreferrer" class="btn-open-fb">
                                Open Facebook <i class="fas fa-arrow-up-right-from-square"></i>
                            </a>
                        </div>
                        <div class="feed-box-body">
                            <div class="fb-page" 
                                data-href="https://www.facebook.com/qcdrrmc" 
                                data-tabs="timeline" 
                                data-width="500" 
                                data-height="700" 
                                data-small-header="false" 
                                data-adapt-container-width="true" 
                                data-hide-cover="false" 
                                data-show-facepile="false"
                                style="width: 100%;">
                                <blockquote cite="https://www.facebook.com/qcdrrmc" class="fb-xfbml-parse-ignore"><a href="https://www.facebook.com/qcdrrmc">Quezon City Disaster Risk Reduction and Management Council</a></blockquote>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Right Side: Weather Telemetry & Emergency Command -->
                <div class="emergency-sidebar">
                    
                    <!-- Weather Telemetry Card -->
                    <div class="weather-compact-card">
                        <div class="weather-card-header">
                            <span><i class="fas fa-cloud-sun-rain" style="color: #0284c7;"></i> QC Weather Watch</span>
                            <span id="weatherStatusBadge" style="font-size: 11px; font-weight: 700; padding: 2px 7px; border-radius: 99px; background: #fef3c7; color: #b45309;">
                                Monitoring
                            </span>
                        </div>

                        <div class="weather-main-row">
                            <div class="weather-temp-group">
                                <span class="weather-icon-large" id="weatherIconLarge">⛈️</span>
                                <div>
                                    <div class="weather-temp-value" id="weatherTempValue">--°C</div>
                                    <div class="weather-desc-text" id="weatherDescText">Loading...</div>
                                </div>
                            </div>
                            <div style="text-align: right; font-size: 11px; color: #64748b;">
                                <div>Feels like</div>
                                <div style="font-weight: 700; color: #0284c7; font-size: 13px;" id="weatherFeelsLike">--°C</div>
                            </div>
                        </div>

                        <div class="weather-stats-row">
                            <div class="weather-stat-pill">
                                <i class="fas fa-cloud-showers-heavy"></i>
                                <div>
                                    <div style="font-size: 10px; color: #64748b;">Rain Chance</div>
                                    <div class="weather-stat-val" id="weatherRainProb">--%</div>
                                </div>
                            </div>
                            <div class="weather-stat-pill">
                                <i class="fas fa-wind"></i>
                                <div>
                                    <div style="font-size: 10px; color: #64748b;">Wind Speed</div>
                                    <div class="weather-stat-val" id="weatherWindSpeed">-- km/h</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 24/7 Hotlines Card -->
                    <div class="emergency-card">
                        <div class="emergency-card-title">
                            <span><i class="fas fa-phone-volume" style="color: #dc2626;"></i> 24/7 Emergency Hotlines</span>
                            <span style="font-size: 11px; color: #64748b;">Tap to Call</span>
                        </div>
                        <ul class="hotline-list">
                            <li>
                                <a href="tel:122" class="hotline-item">
                                    <div class="hotline-info">
                                        <i class="fas fa-building-flag" style="color: #3762c8;"></i>
                                        <span>QC Emergency</span>
                                    </div>
                                    <span class="hotline-number">122</span>
                                </a>
                            </li>
                            <li>
                                <a href="tel:89275914" class="hotline-item">
                                    <div class="hotline-info">
                                        <i class="fas fa-kit-medical" style="color: #dc2626;"></i>
                                        <span>QCDRRMC Rescue</span>
                                    </div>
                                    <span class="hotline-number">8927-5914</span>
                                </a>
                            </li>
                            <li>
                                <a href="tel:16211" class="hotline-item">
                                    <div class="hotline-info">
                                        <i class="fas fa-bolt" style="color: #eab308;"></i>
                                        <span>Meralco Outage</span>
                                    </div>
                                    <span class="hotline-number">16211</span>
                                </a>
                            </li>
                            <li>
                                <a href="tel:1626" class="hotline-item">
                                    <div class="hotline-info">
                                        <i class="fas fa-droplet" style="color: #0284c7;"></i>
                                        <span>Maynilad Water</span>
                                    </div>
                                    <span class="hotline-number">1626</span>
                                </a>
                            </li>
                        </ul>
                    </div>

                </div>

            </div>
        </div>

    </div>
</main>

<script>
// Automatically refresh Facebook feeds periodically (every 3 minutes)
setInterval(function() {
    if (typeof FB !== 'undefined' && FB.XFBML) {
        FB.XFBML.parse();
    }
}, 180000);

// Weather Code mapping for Philippine tropical conditions
function getWeatherDetails(code) {
    switch (code) {
        case 0: return { icon: '☀️', text: 'Clear Skies', badge: 'Normal', badgeBg: '#dcfce7', badgeColor: '#15803d' };
        case 1:
        case 2: return { icon: '🌤️', text: 'Partly Cloudy', badge: 'Fair', badgeBg: '#e0f2fe', badgeColor: '#0369a1' };
        case 3: return { icon: '☁️', text: 'Overcast / Cloudy', badge: 'Cloudy', badgeBg: '#f1f5f9', badgeColor: '#475569' };
        case 45:
        case 48: return { icon: '🌫️', text: 'Fog / Low Visibility', badge: 'Caution', badgeBg: '#fef3c7', badgeColor: '#b45309' };
        case 51:
        case 53:
        case 55: return { icon: '🌦️', text: 'Light Drizzle', badge: 'Drizzle', badgeBg: '#e0f2fe', badgeColor: '#0369a1' };
        case 61:
        case 63: return { icon: '🌧️', text: 'Moderate Rain', badge: 'Rainy', badgeBg: '#fef3c7', badgeColor: '#b45309' };
        case 65: return { icon: '🌧️', text: 'Heavy Rainfall', badge: 'Heavy Rain', badgeBg: '#fee2e2', badgeColor: '#b91c1c' };
        case 80:
        case 81: return { icon: '🌦️', text: 'Scattered Showers', badge: 'Showers', badgeBg: '#e0f2fe', badgeColor: '#0369a1' };
        case 82: return { icon: '⛈️', text: 'Violent Rain Showers', badge: 'Severe', badgeBg: '#fee2e2', badgeColor: '#b91c1c' };
        case 95: return { icon: '⛈️', text: 'Thunderstorms', badge: 'Storm Watch', badgeBg: '#fee2e2', badgeColor: '#b91c1c' };
        case 96:
        case 99: return { icon: '⛈️', text: 'Severe Thunderstorms', badge: 'Critical Alert', badgeBg: '#fee2e2', badgeColor: '#b91c1c' };
        default: return { icon: '🌤️', text: 'Normal Conditions', badge: 'Monitoring', badgeBg: '#fef3c7', badgeColor: '#b45309' };
    }
}

// Live Quezon City Weather API fetch (Open-Meteo)
async function fetchQCWeather() {
    try {
        const url = 'https://api.open-meteo.com/v1/forecast?latitude=14.6760&longitude=121.0437&current=temperature_2m,relative_humidity_2m,apparent_temperature,precipitation,weather_code,wind_speed_10m&hourly=precipitation_probability&timezone=Asia%2FManila';
        const response = await fetch(url);
        if (!response.ok) return;
        const data = await response.json();
        
        if (data && data.current) {
            const cur = data.current;
            const weather = getWeatherDetails(cur.weather_code);
            
            // Get current hour rain probability
            let rainProb = 0;
            if (data.hourly && data.hourly.precipitation_probability && data.hourly.precipitation_probability.length > 0) {
                const currentHourIndex = new Date().getHours();
                rainProb = data.hourly.precipitation_probability[currentHourIndex] ?? data.hourly.precipitation_probability[0] ?? 0;
            }

            // Update Current Weather UI
            document.getElementById('weatherIconLarge').textContent = weather.icon;
            document.getElementById('weatherTempValue').textContent = Math.round(cur.temperature_2m) + '°C';
            document.getElementById('weatherDescText').textContent = weather.text;
            document.getElementById('weatherFeelsLike').textContent = Math.round(cur.apparent_temperature) + '°C';
            document.getElementById('weatherRainProb').textContent = rainProb + '%';
            document.getElementById('weatherWindSpeed').textContent = Math.round(cur.wind_speed_10m) + ' km/h';

            const badge = document.getElementById('weatherStatusBadge');
            if (badge) {
                badge.textContent = weather.badge;
                badge.style.background = weather.badgeBg;
                badge.style.color = weather.badgeColor;
            }

            // Severe/Rainy weather detection for QCDRRMC Feed Alert Badge
            const isStormOrRain = [61, 63, 65, 80, 81, 82, 95, 96, 99].includes(cur.weather_code) || rainProb >= 70;
            const tabAlertBadge = document.getElementById('tabAlertBadge');
            if (tabAlertBadge) {
                tabAlertBadge.style.display = isStormOrRain ? 'inline-flex' : 'none';
            }
        }
    } catch (err) {
        console.warn('Weather telemetry unavailable:', err);
    }
}

// Initial fetch and 15-minute periodic auto-update
fetchQCWeather();
setInterval(fetchQCWeather, 900000);
</script>

<?php if (isset($_SESSION['show_welcome_modal']) && $_SESSION['show_welcome_modal'] === true): ?>
<!-- WELCOME BACK POPUP MODAL -->
<div id="welcomeBackModal" class="welcome-modal open">
    <div class="welcome-modal-content">
        <div class="welcome-header">
            <i class="fas fa-hand-sparkles"></i>
            <h2>Welcome Back, <?php echo htmlspecialchars($userName); ?>!</h2>
            <p style="color: #64748b; font-size: 14px; margin-top: 5px;">You have successfully logged in to the LGU Citizen Portal.</p>
        </div>
        <div class="welcome-body">
            <h4><i class="fas fa-bullhorn" style="color: #3762c8; margin-right: 6px;"></i> While you were away:</h4>
            <ul class="welcome-updates-list">
                <li>
                    <i class="fas fa-broadcast-tower"></i>
                    <div>
                        <strong>Latest LGU Advisories:</strong> Official updates and weather advisories are streaming live.
                    </div>
                </li>
            </ul>
        </div>
        <div class="welcome-footer">
            <button type="button" class="welcome-btn" onclick="closeWelcomeModal()">Dismiss Updates</button>
        </div>
    </div>
</div>
<script>
function closeWelcomeModal() {
    document.getElementById('welcomeBackModal').classList.remove('open');
}
</script>
<?php 
    $_SESSION['show_welcome_modal'] = false;
endif; 
?>

</body>
</html>