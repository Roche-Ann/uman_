<?php
// assets_dashboard.php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$userType = $_SESSION['user_type'] ?? '';
$userName = $_SESSION['user_name'] ?? $_SESSION['full_name'] ?? 'User';

// 1. Core Summary Stats
$totalAssets = $pdo->query("SELECT COUNT(*) FROM utility_assets")->fetchColumn();
$operationalAssets = $pdo->query("SELECT COUNT(*) FROM utility_assets WHERE condition_status = 'Operational'")->fetchColumn();
$needsInspection = $pdo->query("SELECT COUNT(*) FROM utility_assets WHERE condition_status = 'Needs Inspection'")->fetchColumn();
$damagedAssets = $pdo->query("SELECT COUNT(*) FROM utility_assets WHERE condition_status = 'Damaged'")->fetchColumn();
$underMaintenance = $pdo->query("SELECT COUNT(*) FROM utility_assets WHERE condition_status = 'Under Maintenance'")->fetchColumn();

// 2. Data for Category Chart
$categoryData = $pdo->query("
    SELECT t.name, COUNT(a.id) as count 
    FROM asset_types t 
    LEFT JOIN utility_assets a ON t.id = a.asset_type_id 
    GROUP BY t.id
")->fetchAll();

$categoriesJson = json_encode(array_column($categoryData, 'name'));
$categoryCountsJson = json_encode(array_column($categoryData, 'count'));

// 3. Recently Added Assets
$recentAssets = $pdo->query("
    SELECT a.*, t.name as type_name 
    FROM utility_assets a 
    JOIN asset_types t ON a.asset_type_id = t.id 
    ORDER BY a.created_at DESC 
    LIMIT 5
")->fetchAll();

// 4. Assets with Issues (Damaged / Needs Inspection)
$issuesCount = $pdo->query("
    SELECT COUNT(*) 
    FROM utility_assets 
    WHERE condition_status IN ('Damaged', 'Needs Inspection')
")->fetchColumn();

$show_all_reported = isset($_GET['show_all_reported']) && $_GET['show_all_reported'] == '1';
$limitClause = $show_all_reported ? "" : "LIMIT 5";

$issuesAssets = $pdo->query("
    SELECT a.*, t.name as type_name 
    FROM utility_assets a 
    JOIN asset_types t ON a.asset_type_id = t.id 
    WHERE a.condition_status IN ('Damaged', 'Needs Inspection')
    ORDER BY a.updated_at DESC
    $limitClause
")->fetchAll();

// 5. Recent Activity Logs (Status logs)
$recentLogs = $pdo->query("
    SELECT l.*, a.asset_id, a.name as asset_name, u.full_name as user_name
    FROM asset_status_logs l 
    JOIN utility_assets a ON l.utility_asset_id = a.id 
    LEFT JOIN users u ON l.changed_by = u.id
    ORDER BY l.changed_at DESC 
    LIMIT 5
")->fetchAll();

// 6. Notifications count
$unreadNotifications = $pdo->query("SELECT COUNT(*) FROM asset_notifications WHERE read_status = 0")->fetchColumn();

// 7. AI Analytics — Asset Intelligence
$assetHealthScore = ($totalAssets > 0) ? round(($operationalAssets / $totalAssets) * 100) : 100;
$damagedNoMaint = 0;
try {
    $damagedNoMaint = (int)$pdo->query("
        SELECT COUNT(*) FROM utility_assets a 
        WHERE a.condition_status = 'Damaged' 
        AND a.id NOT IN (
            SELECT DISTINCT COALESCE(asset_id, 0) FROM maintenance_requests WHERE asset_id IS NOT NULL
        )
    ")->fetchColumn();
} catch (Throwable $e) {}

// AI Recommendations for assets
$assetAiRecs = [];
if ($damagedAssets > 0) {
    $assetAiRecs[] = ['icon' => 'fa-exclamation-triangle', 'color' => '#e74c3c', 'priority' => 'High',
        'title' => 'Damaged Assets Detected',
        'text' => "{$damagedAssets} asset(s) are currently marked as Damaged. Coordinate with the Maintenance module to schedule repairs."];
}
if ($damagedNoMaint > 0) {
    $assetAiRecs[] = ['icon' => 'fa-unlink', 'color' => '#e74c3c', 'priority' => 'High',
        'title' => 'No Maintenance Dispatched',
        'text' => "{$damagedNoMaint} damaged asset(s) have no linked maintenance request. Consider creating dispatch tickets immediately."];
}
if ($needsInspection > 0) {
    $assetAiRecs[] = ['icon' => 'fa-search', 'color' => '#f39c12', 'priority' => 'Medium',
        'title' => 'Inspections Pending',
        'text' => "{$needsInspection} asset(s) require field inspection. Early inspections can prevent costly damage."];
}
if ($assetHealthScore >= 90) {
    $assetAiRecs[] = ['icon' => 'fa-check-circle', 'color' => '#27ae60', 'priority' => 'Info',
        'title' => 'Excellent Asset Health',
        'text' => "Asset health score is at {$assetHealthScore}%. Infrastructure is in strong condition."];
} elseif ($assetHealthScore < 60 && $totalAssets > 0) {
    $assetAiRecs[] = ['icon' => 'fa-chart-line', 'color' => '#e74c3c', 'priority' => 'High',
        'title' => 'Low Asset Health Score',
        'text' => "Only {$assetHealthScore}% of assets are operational. A comprehensive maintenance audit is recommended."];
}
if (empty($assetAiRecs)) {
    $assetAiRecs[] = ['icon' => 'fa-thumbs-up', 'color' => '#27ae60', 'priority' => 'Info',
        'title' => 'All Clear', 'text' => 'No critical asset issues detected. All systems operating normally.'];
}

// AI Narrative for assets
$assetAiNarrative = "<strong>AI Asset Intelligence Report — " . date('F d, Y') . "</strong><br><br>";
$assetAiNarrative .= "📊 <strong>Health Score:</strong> <span style='font-size:18px;font-weight:700;'>{$assetHealthScore}%</span> of assets operational.<br><br>";
if ($damagedNoMaint > 0) {
    $assetAiNarrative .= "⚠️ <strong>Alert:</strong> {$damagedNoMaint} damaged asset(s) have no maintenance request linked. Immediate attention recommended.";
} else {
    $assetAiNarrative .= "✅ <strong>Status:</strong> All damaged assets have corresponding maintenance requests. Pipeline is functioning properly.";
}
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
    <title>Utility Asset Management - Dashboard</title>
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            padding: 30px 40px;
            transition: margin-left 0.25s ease;
            z-index: 1;
            position: relative;
        }

        .main-content.collapsed {
            margin-left: 78px;
        }

        .card {
            width: 100%;
            max-width: 1700px;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(15px);
            border-radius: 18px;
            padding: 40px;
            color: #000;
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,0.25);
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

        .dashboard-header h1 i {
            color: #3762c8;
        }

        .header-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .btn-action {
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: #3762c8;
            color: white;
            box-shadow: 0 4px 12px rgba(55, 98, 200, 0.3);
        }

        .btn-primary:hover {
            background: #2851b0;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(55, 98, 200, 0.4);
        }

        .btn-secondary {
            background: rgba(0, 0, 0, 0.05);
            color: #2c3e50;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .btn-secondary:hover {
            background: rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .notification-badge {
            position: relative;
            background: white;
            color: #2c3e50;
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
            cursor: pointer;
            transition: all 0.3s;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .notification-badge:hover {
            background: rgba(255, 255, 255, 0.95);
            transform: scale(1.05);
        }

        .badge-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #e74c3c;
            color: white;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 99px;
            border: 2px solid white;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 18px;
            margin-bottom: 35px;
        }

        .stat-card {
            border-radius: 16px;
            padding: 22px 18px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.18);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            color: #fff;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #1a3e7a, #2a5fc2);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: -20px; right: -20px;
            width: 90px; height: 90px;
            border-radius: 50%;
            background: rgba(255,255,255,0.08);
            pointer-events: none;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 14px 32px rgba(0,0,0,0.25);
        }

        .stat-card.operational      { background: linear-gradient(135deg, #1a6b38, #25a259); }
        .stat-card.needs-inspection { background: linear-gradient(135deg, #7a5c0d, #c4920e); }
        .stat-card.damaged          { background: linear-gradient(135deg, #7a1a1a, #c22a2a); }
        .stat-card.maintenance      { background: linear-gradient(135deg, #4c1d7a, #7c3dbf); }

        .stat-card-icon {
            width: 52px; height: 52px;
            border-radius: 14px;
            background: rgba(255,255,255,0.18);
            display: grid;
            place-items: center;
            font-size: 22px;
            flex-shrink: 0;
        }

        .stat-info h3 {
            font-size: 30px;
            font-weight: 700;
            line-height: 1;
            color: #fff;
        }

        .stat-footer {
            display: none;
        }

        /* Charts Section */
        .dashboard-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 35px;
        }

        @media (max-width: 1100px) {
            .dashboard-layout {
                grid-template-columns: 1fr;
            }
        }

        .chart-box {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .chart-box h3 {
            font-size: 16px;
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 2px solid #f1f2f6;
            padding-bottom: 10px;
        }

        .chart-container {
            position: relative;
            height: 280px;
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        /* Tables & Lists */
        .list-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .list-section h3 {
            font-size: 17px;
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #f1f2f6;
            padding-bottom: 10px;
        }

        .table-container {
            overflow-x: auto;
            width: 100%;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th {
            background: #f8f9fa;
            color: #475569;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            padding: 12px 16px;
            border-bottom: 2px solid #e2e8f0;
        }

        td {
            padding: 14px 16px;
            border-bottom: 1px solid #edf2f7;
            font-size: 14px;
            color: #2c3e50;
        }

        tr:hover td {
            background: #fdfdfd;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 99px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-operational { background: #e2fbe8; color: #1e7e34; }
        .badge-inspection { background: #fef9e7; color: #d39e00; }
        .badge-damaged { background: #fde8e8; color: #bd2130; }
        .badge-maintenance { background: #f3e5f5; color: #7b1fa2; }

        /* Timeline Styles */
        .timeline {
            position: relative;
            padding-left: 30px;
            margin-top: 15px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e2e8f0;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 25px;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -25px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50px;
            background: #3762c8;
            border: 2px solid white;
            box-shadow: 0 0 0 3px rgba(55, 98, 200, 0.25);
        }

        .timeline-item.damaged::before {
            background: #e74c3c;
            box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.25);
        }

        .timeline-item.inspection::before {
            background: #f1c40f;
            box-shadow: 0 0 0 3px rgba(241, 196, 15, 0.25);
        }

        .timeline-info {
            display: flex;
            flex-direction: column;
        }

        .timeline-title {
            font-size: 13px;
            font-weight: 600;
            color: #2c3e50;
        }

        .timeline-desc {
            font-size: 12px;
            color: #64748b;
            margin-top: 3px;
        }

        .timeline-time {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 5px;
        }
    </style>
</head>
<body>

<?php include 'includes/utilities_sidebar.php'; ?>

<main class="main-content" id="mainContent">
    <div class="card">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div>
                <h1><i class="fas fa-chart-line"></i> Utility Asset Dashboard</h1>
                <p style="color: #64748b; font-size: 14px; margin-top: 5px;">Manage records and track conditions of all utility assets owned or monitored by the LGU.</p>
            </div>
            
            <div class="header-actions">
                <a href="assets_notifications.php" class="notification-badge" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <?php if ($unreadNotifications > 0): ?>
                        <span class="badge-count"><?php echo $unreadNotifications; ?></span>
                    <?php endif; ?>
                </a>
                <a href="assets_crud.php" class="btn-action btn-primary"><i class="fas fa-boxes"></i> Asset Inventory</a>
            </div>
        </div>

        <!-- Stats Overview Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-card-icon"><i class="fas fa-boxes"></i></div>
                <div class="stat-info"><h3><?php echo number_format($totalAssets); ?></h3><p style="color:rgba(255,255,255,0.8);font-size:11px;text-transform:uppercase;font-weight:600;margin-top:4px;letter-spacing:.6px;">Total Assets</p></div>
            </div>
            <div class="stat-card operational">
                <div class="stat-card-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info"><h3><?php echo number_format($operationalAssets); ?></h3><p style="color:rgba(255,255,255,0.8);font-size:11px;text-transform:uppercase;font-weight:600;margin-top:4px;letter-spacing:.6px;">Operational</p></div>
            </div>
            <div class="stat-card needs-inspection">
                <div class="stat-card-icon"><i class="fas fa-search"></i></div>
                <div class="stat-info"><h3><?php echo number_format($needsInspection); ?></h3><p style="color:rgba(255,255,255,0.8);font-size:11px;text-transform:uppercase;font-weight:600;margin-top:4px;letter-spacing:.6px;">Needs Inspection</p></div>
            </div>
            <div class="stat-card damaged">
                <div class="stat-card-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-info"><h3><?php echo number_format($damagedAssets); ?></h3><p style="color:rgba(255,255,255,0.8);font-size:11px;text-transform:uppercase;font-weight:600;margin-top:4px;letter-spacing:.6px;">Damaged</p></div>
            </div>
            <div class="stat-card maintenance">
                <div class="stat-card-icon"><i class="fas fa-tools"></i></div>
                <div class="stat-info"><h3><?php echo number_format($underMaintenance); ?></h3><p style="color:rgba(255,255,255,0.8);font-size:11px;text-transform:uppercase;font-weight:600;margin-top:4px;letter-spacing:.6px;">Under Maintenance</p></div>
            </div>
        </div>

        <!-- AI Analytics Section -->
        <div style="display:grid; grid-template-columns: 1.5fr 1fr; gap:25px; margin-bottom:35px;">
            <div class="chart-box" style="background:linear-gradient(135deg,#1e3c72,#2a5298); border:none; color:white;">
                <h3 style="color:white; border-bottom-color:rgba(255,255,255,0.15);">
                    <i class="fas fa-brain" style="color:#45aaf2; animation:pulse 2s infinite;"></i> AI Asset Intelligence
                </h3>
                <div style="font-size:13px; line-height:1.7; background:rgba(0,0,0,0.2); padding:20px; border-radius:8px; border:1px solid rgba(255,255,255,0.15);">
                    <?php echo $assetAiNarrative; ?>
                </div>
            </div>
            <div class="chart-box">
                <h3><i class="fas fa-lightbulb" style="color:#f59e0b;"></i> AI Recommendations <span style="background:#3762c8;color:#fff;font-size:10px;padding:2px 8px;border-radius:99px;margin-left:8px;"><?php echo count($assetAiRecs); ?></span></h3>
                <div style="display:flex; flex-direction:column; gap:10px; max-height:250px; overflow-y:auto;">
                    <?php foreach ($assetAiRecs as $rec): ?>
                    <div style="padding:12px 14px; border-radius:10px; background:#f8fafc; border-left:4px solid <?php echo $rec['color']; ?>; transition:all 0.3s;">
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                            <div style="width:28px;height:28px;border-radius:7px;background:<?php echo $rec['color']; ?>;display:flex;align-items:center;justify-content:center;color:white;font-size:12px;flex-shrink:0;">
                                <i class="fas <?php echo $rec['icon']; ?>"></i>
                            </div>
                            <span style="font-weight:600;font-size:13px;color:#2c3e50;"><?php echo $rec['title']; ?></span>
                            <span style="font-size:9px;font-weight:700;padding:2px 8px;border-radius:99px;text-transform:uppercase;margin-left:auto;background:<?php echo $rec['color']; ?>20;color:<?php echo $rec['color']; ?>;"><?php echo $rec['priority']; ?></span>
                        </div>
                        <div style="font-size:12px;color:#64748b;line-height:1.5;padding-left:36px;"><?php echo $rec['text']; ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Charts Layout -->
        <div class="dashboard-layout">
            <!-- Left Chart Box (Categories Distribution) -->
            <div class="chart-box">
                <h3><i class="fas fa-chart-pie"></i> Assets by Category</h3>
                <div class="chart-container">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
            
            <!-- Right Chart Box (Condition Health Meter) -->
            <div class="chart-box">
                <h3><i class="fas fa-heartbeat"></i> Asset Health Status</h3>
                <div class="chart-container">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Lists Section (Recent Assets and Reported Issues) -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 30px;">
            <!-- Recently Added Assets -->
            <div class="list-section">
                <h3>
                    <span><i class="fas fa-clock"></i> Recently Added</span>
                    <a href="assets_crud.php" style="font-size: 13px; color: #3762c8; text-decoration: none;">View All</a>
                </h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Asset ID</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Installed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentAssets)): ?>
                                <tr><td colspan="4" style="text-align: center; color: #94a3b8;">No assets found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentAssets as $asset): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($asset['asset_id']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($asset['name']); ?></td>
                                    <td><?php echo htmlspecialchars($asset['type_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($asset['date_installed'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Reported Issues -->
            <div class="list-section">
                <h3>
                    <div style="display: flex; align-items: center; gap: 10px; width: 100%;">
                        <span><i class="fas fa-exclamation-circle" style="color: #e74c3c;"></i> Reported Issues / Damages</span>
                        <span class="badge badge-damaged" style="font-size: 12px;"><?php echo htmlspecialchars($issuesCount); ?> Active</span>
                        <?php if ($issuesCount > 5): ?>
                            <?php if ($show_all_reported): ?>
                                <a href="assets_dashboard.php" class="btn-outline-small" style="margin-left: auto; font-size: 12px; padding: 4px 10px; border-radius: 4px; text-decoration: none; border: 1px solid #cbd5e1; color: #475569;">Show Less</a>
                            <?php else: ?>
                                <a href="assets_dashboard.php?show_all_reported=1" class="btn-outline-small" style="margin-left: auto; font-size: 12px; padding: 4px 10px; border-radius: 4px; text-decoration: none; border: 1px solid #cbd5e1; color: #3762c8;">Show All</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Asset ID</th>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Location</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($issuesAssets)): ?>
                                <tr><td colspan="4" style="text-align: center; color: #27ae60;">No current issues reported!</td></tr>
                            <?php else: ?>
                                <?php foreach ($issuesAssets as $asset): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($asset['asset_id']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($asset['name']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo ($asset['condition_status'] === 'Damaged') ? 'damaged' : 'inspection'; ?>">
                                            <?php echo htmlspecialchars($asset['condition_status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($asset['location']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- History Log Section -->
        <div class="list-section" style="margin-top: 30px; margin-bottom: 0;">
            <h3>
                <span><i class="fas fa-history"></i> Recent Status Updates Log</span>
                <a href="assets_history.php" style="font-size: 13px; color: #3762c8; text-decoration: none;">Full History</a>
            </h3>
            <div class="timeline">
                <?php if (empty($recentLogs)): ?>
                    <div style="color: #94a3b8; font-size: 13px;">No history logs recorded yet.</div>
                <?php else: ?>
                    <?php foreach ($recentLogs as $log): 
                        $class = '';
                        if ($log['new_status'] === 'Damaged') $class = 'damaged';
                        elseif ($log['new_status'] === 'Needs Inspection') $class = 'inspection';
                    ?>
                        <div class="timeline-item <?php echo $class; ?>">
                            <div class="timeline-info">
                                <div class="timeline-title">
                                    Asset <strong><?php echo htmlspecialchars($log['asset_name'] . ' ('. $log['asset_id'] .')'); ?></strong> 
                                    status changed to 
                                    <span class="badge badge-<?php echo strtolower(str_replace(' ', '', $log['new_status'])); ?>" style="font-size: 10px; padding: 2px 8px;">
                                        <?php echo htmlspecialchars($log['new_status']); ?>
                                    </span>
                                </div>
                                <div class="timeline-desc">
                                    <?php echo htmlspecialchars($log['notes'] ?: 'No notes provided.'); ?>
                                    <?php if ($log['user_name']): ?>
                                        · <em>Updated by <?php echo htmlspecialchars($log['user_name']); ?></em>
                                    <?php endif; ?>
                                </div>
                                <div class="timeline-time"><?php echo date('M d, Y h:i A', strtotime($log['changed_at'])); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
</main>

<script>
    // Category Doughnut Chart
    const categoryCtx = document.getElementById('categoryChart').getContext('2d');
    new Chart(categoryCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo $categoriesJson; ?>,
            datasets: [{
                data: <?php echo $categoryCountsJson; ?>,
                backgroundColor: [
                    '#4b7bec', // Streetlight
                    '#26de81', // Drainage
                    '#fa8231', // Water
                    '#a55eea', // Electrical Pole
                    '#45aaf2'  // Public Infra
                ],
                borderWidth: 1,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        font: { size: 11, family: 'Poppins' }
                    }
                }
            }
        }
    });

    // Health Status Bar Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'bar',
        data: {
            labels: ['Operational', 'Needs Inspection', 'Damaged', 'Maintenance'],
            datasets: [{
                label: 'Asset Count',
                data: [
                    <?php echo $operationalAssets; ?>, 
                    <?php echo $needsInspection; ?>, 
                    <?php echo $damagedAssets; ?>, 
                    <?php echo $underMaintenance; ?>
                ],
                backgroundColor: [
                    '#2ecc71', // Operational
                    '#f1c40f', // Needs Inspection
                    '#e74c3c', // Damaged
                    '#9b59b6'  // Maintenance
                ],
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        font: { family: 'Poppins' }
                    }
                },
                x: {
                    ticks: {
                        font: { family: 'Poppins', size: 11 }
                    }
                }
            }
        }
    });
</script>

</body>
</html>
