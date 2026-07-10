<?php
// utilities_dashboard.php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$userType = $_SESSION['user_type'] ?? 'employee';
$userName = $_SESSION['user_name'] ?? $_SESSION['full_name'] ?? 'LGU Coordinator';

// 1. Fetch Aggregated Metrics from Database Views
$assets = ['total_assets' => 0, 'operational_assets' => 0, 'damaged_assets' => 0, 'inspection_assets' => 0];
$incidents = ['total_incidents' => 0, 'submitted_incidents' => 0, 'review_incidents' => 0, 'forwarded_incidents' => 0, 'resolved_incidents' => 0];
$maintenance = ['total_requests' => 0, 'pending_requests' => 0, 'progress_requests' => 0, 'completed_requests' => 0, 'emergency_requests' => 0];
$energy = ['total_consumption' => 0, 'total_cost' => 0, 'total_records' => 0];
$facilities = ['total_facilities' => 0, 'ready_facilities' => 0, 'partial_facilities' => 0, 'notready_facilities' => 0];

try {
    $assets = $pdo->query("SELECT * FROM aggregated_assets_view")->fetch() ?: $assets;
} catch (Throwable $e) {}

try {
    $incidents = $pdo->query("SELECT * FROM aggregated_incidents_view")->fetch() ?: $incidents;
} catch (Throwable $e) {}

try {
    $maintenance = $pdo->query("SELECT * FROM aggregated_maintenance_view")->fetch() ?: $maintenance;
} catch (Throwable $e) {}

try {
    $energy = $pdo->query("SELECT * FROM aggregated_energy_view")->fetch() ?: $energy;
} catch (Throwable $e) {}

try {
    $facilities = $pdo->query("SELECT * FROM aggregated_facility_view")->fetch() ?: $facilities;
} catch (Throwable $e) {}

// Retrieve planning stats
$totalCoverageAreas = 0;
$activeExpansions = 0;
$pendingProjects = 0;

try {
    $totalCoverageAreas = $pdo->query("SELECT COUNT(*) FROM utility_coverage_records")->fetchColumn();
} catch (Throwable $e) {}

try {
    $activeExpansions = $pdo->query("SELECT COUNT(*) FROM utility_expansion_requests WHERE status IN ('Pending', 'Under Review')")->fetchColumn();
} catch (Throwable $e) {}

try {
    $pendingProjects = $pdo->query("SELECT COUNT(*) FROM development_projects WHERE readiness_status != 'Ready'")->fetchColumn();
} catch (Throwable $e) {}

// 2. Fetch Notifications System Feed (Combined Notifications)
$allNotifications = [];
try {
    $incNotifs = $pdo->query("SELECT message, created_at, 'Incident' as type FROM incident_notifications ORDER BY created_at DESC LIMIT 5")->fetchAll();
    $mntNotifs = $pdo->query("SELECT message, created_at, 'Maintenance' as type FROM maintenance_notifications ORDER BY created_at DESC LIMIT 5")->fetchAll();
    $plnNotifs = $pdo->query("SELECT message, created_at, 'Planning' as type FROM planning_notifications ORDER BY created_at DESC LIMIT 5")->fetchAll();
    $engNotifs = $pdo->query("SELECT message, created_at, 'Energy' as type FROM energy_notifications ORDER BY created_at DESC LIMIT 5")->fetchAll();
    
    $allNotifications = array_merge($incNotifs, $mntNotifs, $plnNotifs, $engNotifs);
    usort($allNotifications, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    $allNotifications = array_slice($allNotifications, 0, 8);
} catch (Exception $e) {
    // Fallback if some table has no notifications seeded
}

// 3. AI Command Center Summarizer (Descriptive statistics summarization only)
function generateAICentralSummary($assets, $incidents, $maintenance, $energy, $facilities, $totalCoverageAreas) {
    $summary = "<strong>LGU Central AI Assistant Coordination Report (" . date('F Y') . ")</strong><br><br>";
    
    $summary .= "🏢 <strong>System-Wide Load Insights:</strong><br>";
    $summary .= "• Assets: " . ($assets['total_assets'] ?? 0) . " monitored, with " . ($assets['damaged_assets'] ?? 0) . " currently marked as Damaged.<br>";
    $summary .= "• Incidents: " . ($incidents['total_incidents'] ?? 0) . " resident reports tracked. " . ($incidents['submitted_incidents'] ?? 0) . " are awaiting initial review.<br>";
    $summary .= "• Maintenance: " . ($maintenance['total_requests'] ?? 0) . " total coordination requests. " . ($maintenance['emergency_requests'] ?? 0) . " emergency dispatches have been flagged.<br>";
    $summary .= "• Energy: Total raw recorded consumption stands at " . number_format($energy['total_consumption'] ?? 0, 1) . " kWh.<br>";
    $summary .= "• Public Facilities: " . ($facilities['total_facilities'] ?? 0) . " venues monitored. " . ($facilities['ready_facilities'] ?? 0) . " are verified as Fully Ready.<br>";
    
    $summary .= "<br>⚠️ <strong>Coordination Advisories:</strong><br>";
    if (($incidents['submitted_incidents'] ?? 0) > 0 || ($maintenance['emergency_requests'] ?? 0) > 0) {
        $summary .= "• Attention: Pending resident reports and emergency maintenance requests are currently active. Prompt forwarding to external dispatch queues is recommended.";
    } else {
        $summary .= "• All active utility monitoring and maintenance pipelines are currently operating within nominal queue limits.";
    }
    
    return $summary;
}

$aiSummaryText = generateAICentralSummary($assets, $incidents, $maintenance, $energy, $facilities, $totalCoverageAreas);

// Chart Arrays
$assetLabels = json_encode(['Operational', 'Damaged', 'Needs Inspection']);
$assetData = json_encode([$assets['operational_assets'] ?? 0, $assets['damaged_assets'] ?? 0, $assets['inspection_assets'] ?? 0]);

$incidentLabels = json_encode(['Submitted', 'Under Review', 'Forwarded', 'Resolved']);
$incidentData = json_encode([$incidents['submitted_incidents'] ?? 0, $incidents['review_incidents'] ?? 0, $incidents['forwarded_incidents'] ?? 0, $incidents['resolved_incidents'] ?? 0]);

$facilityLabels = json_encode(['Fully Ready', 'Partially Ready', 'Not Ready']);
$facilityData = json_encode([$facilities['ready_facilities'] ?? 0, $facilities['partial_facilities'] ?? 0, $facilities['notready_facilities'] ?? 0]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LGU Central Command Center</title>
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
            position: absolute;
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
            margin-left: 90px;
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
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 35px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            border-left: 5px solid #cbd5e1;
        }

        .stat-card.assets { border-left-color: #3762c8; }
        .stat-card.incidents { border-left-color: #f1c40f; }
        .stat-card.maintenance { border-left-color: #e74c3c; }
        .stat-card.planning { border-left-color: #2ecc71; }
        .stat-card.energy { border-left-color: #a55eea; }
        .stat-card.facilities { border-left-color: #45aaf2; }

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

        /* Tab Layout */
        .tab-buttons {
            display: flex;
            gap: 10px;
            border-bottom: 2px solid #edf2f7;
            padding-bottom: 10px;
            margin-bottom: 25px;
            overflow-x: auto;
        }

        .tab-btn {
            background: transparent;
            border: none;
            padding: 10px 18px;
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .tab-btn:hover { background: #f8fafc; color: #2c3e50; }
        .tab-btn.active { background: #3762c8; color: white; }

        .tab-pane { display: none; }
        .tab-pane.active { display: block; }

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

        /* AI Analytics Card styling */
        .ai-box {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            border: none;
        }
        .ai-box h3 { color: white; border-bottom-color: rgba(255,255,255,0.15); }
        .ai-box h3 i { color: #45aaf2; animation: pulse 2s infinite; }
        .ai-content { font-size: 13px; line-height: 1.6; background: rgba(0, 0, 0, 0.2); padding: 20px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.15); }

        .log-item {
            padding: 10px 15px;
            border-radius: 8px;
            background: #f8fafc;
            border-left: 3px solid #cbd5e1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
        }
        .badge {
            font-size: 9px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 99px;
            text-transform: uppercase;
        }

        /* Welcome Modal CSS */
        .welcome-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 10000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(4px);
        }
        .welcome-modal.open {
            display: flex;
        }
        .welcome-modal-content {
            background: #ffffff;
            border-radius: 16px;
            width: 500px;
            max-width: 90%;
            padding: 30px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            animation: welcomeSlideIn 0.3s ease-out;
            position: relative;
        }
        @keyframes welcomeSlideIn {
            from { transform: translateY(-30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .welcome-header {
            text-align: center;
            margin-bottom: 20px;
        }
        .welcome-header i {
            font-size: 50px;
            color: #3762c8;
            margin-bottom: 10px;
        }
        .welcome-header h2 {
            font-size: 24px;
            color: #1e293b;
            font-weight: 600;
        }
        .welcome-body {
            font-size: 14px;
            color: #475569;
            line-height: 1.6;
        }
        .welcome-body h4 {
            color: #1e293b;
            margin-top: 15px;
            margin-bottom: 8px;
            font-weight: 600;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
        }
        .welcome-updates-list {
            list-style: none;
            padding: 0;
        }
        .welcome-updates-list li {
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .welcome-updates-list li:last-child {
            border-bottom: none;
        }
        .welcome-updates-list li i {
            color: #3762c8;
            margin-top: 4px;
            font-size: 16px;
        }
        .welcome-footer {
            margin-top: 25px;
            display: flex;
            justify-content: center;
        }
        .welcome-btn {
            background: #3762c8;
            color: #fff;
            padding: 10px 30px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .welcome-btn:hover {
            background: #2851b0;
            transform: translateY(-1px);
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
                <h1><i class="fas fa-satellite-dish"></i> LGU Central Command Dashboard</h1>
                <p style="color: #64748b; font-size: 14px; margin-top: 5px;">Unified monitoring center for inventory, resident complaints, dispatches, coverage, and energy audits.</p>
            </div>
            <div>
                <button class="btn btn-primary" onclick="generateReport()"><i class="fas fa-file-download"></i> Generate System Report</button>
            </div>
        </div>

        <!-- Central Summary Metrics Cards -->
        <div class="stats-grid">
            <div class="stat-card assets">
                <div class="stat-info">
                    <h3><?php echo number_format($assets['total_assets'] ?? 0); ?></h3>
                    <p>Total Assets</p>
                </div>
            </div>
            <div class="stat-card incidents">
                <div class="stat-info">
                    <h3><?php echo number_format($incidents['total_incidents'] ?? 0); ?></h3>
                    <p>Active Incidents</p>
                </div>
            </div>
            <div class="stat-card maintenance">
                <div class="stat-info">
                    <h3><?php echo number_format($maintenance['pending_requests'] ?? 0); ?></h3>
                    <p>Pending Maintenance</p>
                </div>
            </div>
            <div class="stat-card planning">
                <div class="stat-info">
                    <h3><?php echo number_format($totalCoverageAreas); ?></h3>
                    <p>Coverage Zones</p>
                </div>
            </div>
            <div class="stat-card energy">
                <div class="stat-info">
                    <h3><?php echo number_format($energy['total_consumption'] ?? 0, 1); ?> <span style="font-size:11px;">kWh</span></h3>
                    <p>Energy Consumption</p>
                </div>
            </div>
            <div class="stat-card facilities">
                <div class="stat-info">
                    <h3><?php echo number_format($facilities['ready_facilities'] ?? 0); ?> / <?php echo number_format($facilities['total_facilities'] ?? 0); ?></h3>
                    <p>Facilities Ready</p>
                </div>
            </div>
        </div>

        <!-- AI Digest Row -->
        <div class="dashboard-layout" style="grid-template-columns: 1.5fr 1fr; margin-bottom: 25px;">
            <div class="box ai-box">
                <h3><i class="fas fa-robot"></i> Central AI Analytics Summary</h3>
                <div class="ai-content">
                    <?php echo $aiSummaryText; ?>
                </div>
            </div>
            
            <!-- Global Alert Feed -->
            <div class="box">
                <h3><i class="fas fa-bell"></i> Live Coordination Notification Feed</h3>
                <div style="display:flex; flex-direction:column; gap:10px; max-height: 220px; overflow-y:auto;">
                    <?php if (empty($allNotifications)): ?>
                        <div style="color: #64748b; font-size: 13px;">No recent system notifications.</div>
                    <?php else: ?>
                        <?php foreach ($allNotifications as $n): ?>
                            <div class="log-item">
                                <div>
                                    <div style="font-weight:600; color:#2c3e50;"><?php echo htmlspecialchars($n['message']); ?></div>
                                    <div style="font-size:10px; color:#94a3b8; margin-top:2px;">Module: <?php echo $n['type']; ?> · <?php echo date('M d, h:i A', strtotime($n['created_at'])); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Section Tabs -->
        <div class="tab-buttons">
            <button class="tab-btn active" onclick="switchTab(event, 'assets-pane')"><i class="fas fa-warehouse"></i> Asset Analytics</button>
            <button class="tab-btn" onclick="switchTab(event, 'incidents-pane')"><i class="fas fa-bullhorn"></i> Incidents & Maintenance</button>
            <button class="tab-btn" onclick="switchTab(event, 'planning-pane')"><i class="fas fa-map-marked-alt"></i> Planning & Facilities</button>
            <button class="tab-btn" onclick="switchTab(event, 'energy-pane')"><i class="fas fa-bolt"></i> Energy Sync</button>
        </div>

        <!-- TAB PANES -->
        
        <!-- 1. Assets Pane -->
        <div id="assets-pane" class="tab-pane active">
            <div class="dashboard-layout">
                <div class="box">
                    <h3><i class="fas fa-chart-pie"></i> Asset Operational Status</h3>
                    <div style="position:relative; height:280px; width:100%; display:flex; justify-content:center; align-items:center;">
                        <canvas id="assetChart"></canvas>
                    </div>
                </div>
                <div class="box" style="display:flex; flex-direction:column; justify-content:center;">
                    <h4 style="color:#2c3e50; font-size:15px; margin-bottom:10px;">Asset Monitoring Summary:</h4>
                    <p style="font-size:13px; color:#64748b; line-height:1.6;">LGU assets are categorized into Solar Streetlights, Drainage Gates, and Pipeline Sections. The current active database shows <strong><?php echo $assets['damaged_assets'] ?? 0; ?></strong> assets marked as Damaged, requiring coordination via maintenance requests.</p>
                </div>
            </div>
        </div>

        <!-- 2. Incidents & Maintenance Pane -->
        <div id="incidents-pane" class="tab-pane">
            <div class="dashboard-layout">
                <div class="box">
                    <h3><i class="fas fa-chart-bar"></i> Incident Status Breakdown</h3>
                    <div style="position:relative; height:280px; width:100%; display:flex; justify-content:center; align-items:center;">
                        <canvas id="incidentChart"></canvas>
                    </div>
                </div>
                <div class="box" style="display:flex; flex-direction:column; justify-content:center;">
                    <h4 style="color:#2c3e50; font-size:15px; margin-bottom:10px;">Maintenance Dispatches:</h4>
                    <p style="font-size:13px; color:#64748b; line-height:1.6;">Outbound maintenance requests correspond to resident reports or asset monitoring records. Current queues have <strong><?php echo $maintenance['pending_requests'] ?? 0; ?></strong> pending tasks and <strong><?php echo $maintenance['emergency_requests'] ?? 0; ?></strong> emergency requests routed to external repair systems.</p>
                </div>
            </div>
        </div>

        <!-- 3. Planning & Facilities Pane -->
        <div id="planning-pane" class="tab-pane">
            <div class="dashboard-layout">
                <div class="box">
                    <h3><i class="fas fa-chart-pie"></i> Facility Utility Readiness Levels</h3>
                    <div style="position:relative; height:280px; width:100%; display:flex; justify-content:center; align-items:center;">
                        <canvas id="facilityChart"></canvas>
                    </div>
                </div>
                <div class="box" style="display:flex; flex-direction:column; justify-content:center;">
                    <h4 style="color:#2c3e50; font-size:15px; margin-bottom:10px;">Utility Expansion Planning:</h4>
                    <p style="font-size:13px; color:#64748b; line-height:1.6;">Zoning plans show <strong><?php echo $totalCoverageAreas; ?></strong> coverage zones monitored. There are currently <strong><?php echo $activeExpansions; ?></strong> active service expansion requests under review with the Urban Planning System.</p>
                </div>
            </div>
        </div>

        <!-- 4. Energy Sync Pane -->
        <div id="energy-pane" class="tab-pane">
            <div class="dashboard-layout" style="grid-template-columns: 1fr;">
                <div class="box">
                    <h3><i class="fas fa-plug"></i> Consumption Data & Cost Summary</h3>
                    <p style="font-size:14px; color:#2c3e50; margin-bottom:15px;">Total raw electricity recorded: <strong><?php echo number_format($energy['total_consumption'] ?? 0, 1); ?> kWh</strong> (Estimated Cost: <strong>₱<?php echo number_format($energy['total_cost'] ?? 0, 2); ?></strong>).</p>
                    <p style="font-size:13px; color:#64748b; line-height:1.6;">Synchronization files are exported to the external Energy Efficiency System periodically. Successful export operations: <strong><?php echo $successfulSyncs; ?></strong> sync actions logged in system records.</p>
                </div>
            </div>
        </div>

    </div>
</main>

<script>
    function switchTab(evt, tabId) {
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        
        document.getElementById(tabId).classList.add('active');
        evt.currentTarget.classList.add('active');
    }

    function generateReport() {
        alert("Generating LGU System-Wide Utility Status Report (PDF/Excel) details...\nSimulated file download started in background.");
    }

    // Chart 1: Assets Operational Status
    const assetCtx = document.getElementById('assetChart').getContext('2d');
    new Chart(assetCtx, {
        type: 'pie',
        data: {
            labels: <?php echo $assetLabels; ?>,
            datasets: [{
                data: <?php echo $assetData; ?>,
                backgroundColor: ['#2ecc71', '#e74c3c', '#f1c40f']
            }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });

    // Chart 2: Incident Status Breakdown
    const incidentCtx = document.getElementById('incidentChart').getContext('2d');
    new Chart(incidentCtx, {
        type: 'bar',
        data: {
            labels: <?php echo $incidentLabels; ?>,
            datasets: [{
                label: 'Reports Count',
                data: <?php echo $incidentData; ?>,
                backgroundColor: '#f1c40f',
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } }
        }
    });

    // Chart 3: Facility Readiness Status
    const facilityCtx = document.getElementById('facilityChart').getContext('2d');
    new Chart(facilityCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo $facilityLabels; ?>,
            datasets: [{
                data: <?php echo $facilityData; ?>,
                backgroundColor: ['#2ecc71', '#f1c40f', '#e74c3c']
            }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });
</script>

<?php if (isset($_SESSION['show_welcome_modal']) && $_SESSION['show_welcome_modal'] === true): ?>
<!-- WELCOME BACK POPUP MODAL -->
<div id="welcomeBackModal" class="welcome-modal open">
    <div class="welcome-modal-content">
        <div class="welcome-header">
            <i class="fas fa-hand-sparkles"></i>
            <h2>Welcome Back, <?php echo htmlspecialchars($userName); ?>!</h2>
            <p style="color: #64748b; font-size: 14px; margin-top: 5px;">You have successfully logged in as LGU Employee.</p>
        </div>
        <div class="welcome-body">
            <h4><i class="fas fa-bullhorn" style="color: #3762c8; margin-right: 6px;"></i> While you were away:</h4>
            <ul class="welcome-updates-list">
                <li>
                    <i class="fas fa-boxes"></i>
                    <div>
                        <strong>Monitored Assets:</strong> Currently tracking <?php echo $assets['total_assets'] ?? 0; ?> total assets, with <?php echo $assets['damaged_assets'] ?? 0; ?> currently flagged as Damaged.
                    </div>
                </li>
                <li>
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <strong>Submitted Incidents:</strong> There are <?php echo $incidents['submitted_incidents'] ?? 0; ?> new resident incident reports awaiting initial review.
                    </div>
                </li>
                <li>
                    <i class="fas fa-bolt"></i>
                    <div>
                        <strong>Energy Management:</strong> Total raw recorded grid consumption stands at <?php echo number_format($energy['total_consumption'] ?? 0, 1); ?> kWh.
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