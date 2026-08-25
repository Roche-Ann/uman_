<?php
// maintenance_dashboard.php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$userType = $_SESSION['user_type'] ?? '';

// Core Counts with safe fallbacks (handles different schemas)
function safeCount($pdo, $sql) {
    try {
        return (int) $pdo->query($sql)->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

$totalRequests = safeCount($pdo, "SELECT COUNT(*) FROM maintenance_requests");
$pendingRequests = safeCount($pdo, "SELECT COUNT(*) FROM maintenance_requests WHERE status = 'Created'");
$forwardedRequests = safeCount($pdo, "SELECT COUNT(*) FROM maintenance_requests WHERE status = 'Forwarded'");
$inProgressRequests = safeCount($pdo, "SELECT COUNT(*) FROM maintenance_requests WHERE status = 'In Progress'");
$completedRequests = safeCount($pdo, "SELECT COUNT(*) FROM maintenance_requests WHERE status IN ('Completed', 'Closed')");
$emergencyRequests = safeCount($pdo, "SELECT COUNT(*) FROM maintenance_requests WHERE priority = 'Emergency'") ?: safeCount($pdo, "SELECT COUNT(*) FROM maintenance_requests WHERE urgency = 'Emergency'");

// Data for charts with fallbacks to compatible columns
$priorityCounts = [];
try {
    $priorityCounts = $pdo->query(
        "SELECT priority as label, COUNT(id) as count FROM maintenance_requests GROUP BY priority"
    )->fetchAll(PDO::FETCH_ASSOC);
    if (empty($priorityCounts)) {
        $priorityCounts = $pdo->query(
            "SELECT urgency as label, COUNT(id) as count FROM maintenance_requests GROUP BY urgency"
        )->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    // fallback to empty
    $priorityCounts = [];
}

$priorities = ['Low', 'Medium', 'High', 'Emergency'];
$priorityCountsMap = array_fill_keys($priorities, 0);
foreach ($priorityCounts as $row) {
    $label = $row['label'] ?? $row['priority'] ?? $row['urgency'] ?? null;
    $count = isset($row['count']) ? (int)$row['count'] : 0;
    if ($label && isset($priorityCountsMap[$label])) {
        $priorityCountsMap[$label] = $count;
    }
}
$priorityCountsJson = json_encode(array_values($priorityCountsMap));

$sourceCounts = [];
try {
    $sourceCountsRaw = $pdo->query(
        "SELECT source as label, COUNT(id) as count FROM maintenance_requests GROUP BY source"
    )->fetchAll(PDO::FETCH_ASSOC);
    if (empty($sourceCountsRaw)) {
        $sourceCountsRaw = $pdo->query(
            "SELECT asset_type as label, COUNT(id) as count FROM maintenance_requests GROUP BY asset_type"
        )->fetchAll(PDO::FETCH_ASSOC);
    }
    foreach ($sourceCountsRaw as $r) {
        $sourceCounts[$r['label']] = (int)$r['count'];
    }
} catch (Throwable $e) {
    $sourceCounts = [];
}

$sourcesJson = json_encode(array_keys($sourceCounts));
$sourceCountsJson = json_encode(array_values($sourceCounts));

// Recent activities list with fallback if request fields differ
$recentActivities = [];
try {
    $recentActivities = $pdo->query(
        "SELECT h.*, r.request_id as request_ref, r.description FROM maintenance_history h JOIN maintenance_requests r ON h.maintenance_request_id = r.id ORDER BY h.performed_at DESC LIMIT 6"
    )->fetchAll(PDO::FETCH_ASSOC);
    if (empty($recentActivities)) {
        // Fallback: fetch maintenance_history alone
        $recentActivities = $pdo->query(
            "SELECT id as id, action, details, performed_at FROM maintenance_history ORDER BY performed_at DESC LIMIT 6"
        )->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $recentActivities = [];
}

// Simulated AI Analytics (Coordination summaries logic)
try {
    $allRequests = $pdo->query(
        "SELECT COALESCE(r.request_id, CONCAT('REQ-', r.id)) as request_id, COALESCE(r.source, r.asset_type) as source, r.description, COALESCE(r.priority, r.urgency) as priority, r.status, a.name as asset_name FROM maintenance_requests r LEFT JOIN utility_assets a ON r.utility_asset_id = a.id"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    try {
        $allRequests = $pdo->query(
            "SELECT id as request_id, asset_type as source, description, urgency as priority, status FROM maintenance_requests"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e2) {
        $allRequests = [];
    }
}

function generateAISummary($requests) {
    if (empty($requests)) {
        return "No maintenance requests available in database for AI text analysis.";
    }

    $summary = "<strong>LGU AI Assistant Coordination Summary (" . date('F Y') . ")</strong><br><br>";
    
    // Grouping count
    $groups = [];
    $emergencies = 0;
    foreach ($requests as $req) {
        $groups[$req['source']][] = $req['request_id'];
        if ($req['priority'] === 'Emergency') {
            $emergencies++;
        }
    }
    
    $summary .= "📋 <strong>Workload Distribution:</strong><br>";
    foreach ($groups as $src => $ids) {
        $qty = count($ids);
        $summary .= "• <strong>{$src}:</strong> {$qty} pending coordination request" . ($qty > 1 ? 's' : '') . ".<br>";
    }
    
    $summary .= "<br>⚠️ <strong>Urgency Notification:</strong><br>";
    if ($emergencies > 0) {
        $summary .= "• AI detected <strong>{$emergencies} Emergency request(s)</strong>. These require immediate dispatch forwarding to the external Maintenance System to prevent service interruption.";
    } else {
        $summary .= "• No critical coordination anomalies identified. Queue is processing normally.";
    }
    
    return $summary;
}

$aiAnalysisOutput = generateAISummary($allRequests);

// AI Analytics — Maintenance Intelligence
$completionRate = ($totalRequests > 0) ? round(($completedRequests / $totalRequests) * 100) : 100;

$maintAiRecs = [];
if ($emergencyRequests > 0) {
    $maintAiRecs[] = ['icon' => 'fa-exclamation-triangle', 'color' => '#e74c3c', 'priority' => 'High',
        'title' => 'Emergency Dispatches Active',
        'text' => "{$emergencyRequests} emergency maintenance request(s) flagged. Verify external dispatch systems have been notified."];
}
if ($pendingRequests > 3) {
    $maintAiRecs[] = ['icon' => 'fa-clock', 'color' => '#f39c12', 'priority' => 'Medium',
        'title' => 'Growing Request Backlog',
        'text' => "{$pendingRequests} requests still in Created status. Pipeline may be experiencing delays."];
} elseif ($pendingRequests > 0) {
    $maintAiRecs[] = ['icon' => 'fa-clipboard-list', 'color' => '#f39c12', 'priority' => 'Medium',
        'title' => 'Pending Requests',
        'text' => "{$pendingRequests} maintenance request(s) awaiting forwarding. Process promptly to prevent service disruption."];
}
if ($completionRate < 50 && $totalRequests > 1) {
    $maintAiRecs[] = ['icon' => 'fa-chart-line', 'color' => '#e74c3c', 'priority' => 'High',
        'title' => 'Low Completion Rate',
        'text' => "Only {$completionRate}% of maintenance tasks completed. Review workflow bottlenecks."];
} elseif ($completionRate >= 80) {
    $maintAiRecs[] = ['icon' => 'fa-trophy', 'color' => '#27ae60', 'priority' => 'Info',
        'title' => 'Strong Completion Rate',
        'text' => "Completion rate at {$completionRate}%. Maintenance coordination pipeline is efficient."];
}
if ($forwardedRequests > 0) {
    $maintAiRecs[] = ['icon' => 'fa-paper-plane', 'color' => '#a55eea', 'priority' => 'Info',
        'title' => 'Requests Forwarded',
        'text' => "{$forwardedRequests} request(s) forwarded to external maintenance system for execution."];
}
if (empty($maintAiRecs)) {
    $maintAiRecs[] = ['icon' => 'fa-check-circle', 'color' => '#27ae60', 'priority' => 'Info',
        'title' => 'All Clear', 'text' => 'Maintenance pipeline is operating within normal parameters.'];
}

// Retrieve unread notifications count
$unreadNotifications = $pdo->query("SELECT COUNT(*) FROM maintenance_notifications WHERE read_status = 0")->fetchColumn();
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
    <title>LGU Maintenance Coordination Dashboard</title>
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

        .dashboard-header h1 i {
            color: #3762c8;
        }

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
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-left: 5px solid #cbd5e1;
        }

        .stat-card.total { border-left-color: #3762c8; }
        .stat-card.pending { border-left-color: #f1c40f; }
        .stat-card.forwarded { border-left-color: #a55eea; }
        .stat-card.progress { border-left-color: #45aaf2; }
        .stat-card.completed { border-left-color: #2ecc71; }
        .stat-card.emergency { border-left-color: #e74c3c; }

        .stat-info h3 {
            font-size: 26px;
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

        .stat-icon {
            font-size: 26px;
            color: #cbd5e1;
        }

        .dashboard-layout {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 30px;
            margin-bottom: 35px;
        }

        @media (max-width: 1000px) {
            .dashboard-layout {
                grid-template-columns: 1fr;
            }
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

        .ai-box h3 {
            color: white;
            border-bottom-color: rgba(255,255,255,0.15);
        }

        .ai-box h3 i {
            color: #45aaf2;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.15); }
            100% { transform: scale(1); }
        }

        .ai-content {
            font-size: 13px;
            line-height: 1.6;
            background: rgba(0, 0, 0, 0.2);
            padding: 20px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.1);
        }

        /* Recent Activity Feed */
        .feed-container {
            max-height: 320px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .feed-item {
            padding: 10px 15px;
            border-radius: 8px;
            background: #f8fafc;
            border-left: 3px solid #cbd5e1;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
                <h1><i class="fas fa-tools"></i> Maintenance Coordination</h1>
                <p style="color: #64748b; font-size: 14px; margin-top: 5px;">Dispatch and track utility maintenance requests with the external Maintenance Management System.</p>
            </div>
            <div style="display:flex; gap:10px;">
                <a href="maintenance_list.php" class="btn btn-primary"><i class="fas fa-list"></i> Coordinate Requests</a>
                <a href="maintenance_assets_view.php" class="btn btn-outline"><i class="fas fa-eye"></i> Asset-Based View</a>
            </div>
        </div>

        <!-- Summary Cards Grid -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-info">
                    <h3><?php echo number_format($totalRequests); ?></h3>
                    <p>Total Requests</p>
                </div>
                <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
            </div>
            <div class="stat-card pending">
                <div class="stat-info">
                    <h3><?php echo number_format($pendingRequests); ?></h3>
                    <p>Created</p>
                </div>
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
            </div>
            <div class="stat-card forwarded">
                <div class="stat-info">
                    <h3><?php echo number_format($forwardedRequests); ?></h3>
                    <p>Forwarded</p>
                </div>
                <div class="stat-icon"><i class="fas fa-paper-plane"></i></div>
            </div>
            <div class="stat-card progress">
                <div class="stat-info">
                    <h3><?php echo number_format($inProgressRequests); ?></h3>
                    <p>In Progress</p>
                </div>
                <div class="stat-icon"><i class="fas fa-cogs"></i></div>
            </div>
            <div class="stat-card completed">
                <div class="stat-info">
                    <h3><?php echo number_format($completedRequests); ?></h3>
                    <p>Completed</p>
                </div>
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            </div>
            <div class="stat-card emergency">
                <div class="stat-info">
                    <h3><?php echo number_format($emergencyRequests); ?></h3>
                    <p>Emergencies</p>
                </div>
                <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
            </div>
        </div>

        <!-- Dashboard Analytics Grid -->
        <div class="dashboard-layout">
            <!-- Left Chart Category Frequency -->
            <div class="box">
                <h3><i class="fas fa-chart-bar"></i> Maintenance Priority Distribution</h3>
                <div style="position:relative; height:280px; width:100%; display:flex; justify-content:center; align-items:center;">
                    <canvas id="priorityChart"></canvas>
                </div>
            </div>

            <!-- Right AI Analytics Box -->
            <div class="box ai-box">
                <h3><i class="fas fa-robot"></i> AI Dispatch Workload Summarizer</h3>
                <div class="ai-content">
                    <?php echo $aiAnalysisOutput; ?>
                </div>
            </div>
        </div>

        <!-- Maps and feeds row -->
        <div class="dashboard-layout">
            <!-- Left: Source breakdown -->
            <div class="box">
                <h3><i class="fas fa-chart-pie"></i> Maintenance Request Sources</h3>
                <div style="position:relative; height:280px; width:100%; display:flex; justify-content:center; align-items:center;">
                    <canvas id="sourceChart"></canvas>
                </div>
            </div>

            <!-- Right: Recent Activity Log Feed -->
            <div class="box">
                <h3><i class="fas fa-history"></i> Recent Activity Logs</h3>
                <div class="feed-container">
                    <?php if (empty($recentActivities)): ?>
                        <div style="color: #64748b; font-size: 13px;">No recent maintenance activity.</div>
                    <?php else: ?>
                        <?php foreach ($recentActivities as $act): ?>
                            <div class="feed-item">
                                <div>
                                    <div style="font-weight:600; font-size:13px; color:#2c3e50;"><?php echo htmlspecialchars($act['action']); ?></div>
                                    <div style="font-size:11px; color:#64748b;"><?php echo htmlspecialchars($act['details']); ?></div>
                                </div>
                                <div style="font-size:10px; color:#94a3b8; font-style:italic;"><?php echo date('M d, h:i A', strtotime($act['performed_at'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- AI Recommendations Section -->
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:25px; margin-bottom:25px;">
            <div class="box">
                <h3><i class="fas fa-brain" style="color:#3762c8;"></i> AI Completion Pipeline
                    <span style="font-size:11px;font-weight:400;color:#64748b;margin-left:auto;">Completion Rate: <strong style="color:<?php echo $completionRate >= 70 ? '#27ae60' : ($completionRate >= 40 ? '#f39c12' : '#e74c3c'); ?>;"><?php echo $completionRate; ?>%</strong></span>
                </h3>
                <div style="margin-bottom:14px;">
                    <div style="display:flex;justify-content:space-between;font-size:12px;color:#64748b;margin-bottom:6px;"><span>Completed</span><span><?php echo $completedRequests; ?>/<?php echo $totalRequests; ?></span></div>
                    <div style="height:12px;background:#e2e8f0;border-radius:99px;overflow:hidden;">
                        <div style="height:100%;width:<?php echo $completionRate; ?>%;background:linear-gradient(90deg,#27ae60,#2ecc71);border-radius:99px;transition:width 1s;"></div>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:8px;text-align:center;">
                    <div style="padding:8px;border-radius:8px;background:linear-gradient(135deg,#eff6ff,#dbeafe);">
                        <div style="font-size:18px;font-weight:700;color:#2c3e50;"><?php echo $pendingRequests; ?></div>
                        <div style="font-size:8px;text-transform:uppercase;font-weight:600;color:#64748b;">Created</div>
                    </div>
                    <div style="padding:8px;border-radius:8px;background:linear-gradient(135deg,#f3e8ff,#e9d5ff);">
                        <div style="font-size:18px;font-weight:700;color:#2c3e50;"><?php echo $forwardedRequests; ?></div>
                        <div style="font-size:8px;text-transform:uppercase;font-weight:600;color:#64748b;">Forwarded</div>
                    </div>
                    <div style="padding:8px;border-radius:8px;background:linear-gradient(135deg,#e0f2fe,#bae6fd);">
                        <div style="font-size:18px;font-weight:700;color:#2c3e50;"><?php echo $inProgressRequests; ?></div>
                        <div style="font-size:8px;text-transform:uppercase;font-weight:600;color:#64748b;">In Progress</div>
                    </div>
                    <div style="padding:8px;border-radius:8px;background:linear-gradient(135deg,#dcfce7,#bbf7d0);">
                        <div style="font-size:18px;font-weight:700;color:#2c3e50;"><?php echo $completedRequests; ?></div>
                        <div style="font-size:8px;text-transform:uppercase;font-weight:600;color:#64748b;">Completed</div>
                    </div>
                    <div style="padding:8px;border-radius:8px;background:linear-gradient(135deg,#fee2e2,#fecaca);">
                        <div style="font-size:18px;font-weight:700;color:#e74c3c;"><?php echo $emergencyRequests; ?></div>
                        <div style="font-size:8px;text-transform:uppercase;font-weight:600;color:#64748b;">Emergency</div>
                    </div>
                </div>
            </div>
            <div class="box">
                <h3><i class="fas fa-lightbulb" style="color:#f59e0b;"></i> AI Recommendations <span style="background:#3762c8;color:#fff;font-size:10px;padding:2px 8px;border-radius:99px;margin-left:8px;"><?php echo count($maintAiRecs); ?></span></h3>
                <div style="display:flex; flex-direction:column; gap:10px; max-height:220px; overflow-y:auto;">
                    <?php foreach ($maintAiRecs as $rec): ?>
                    <div style="padding:12px 14px; border-radius:10px; background:#f8fafc; border-left:4px solid <?php echo $rec['color']; ?>;">
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

    </div>
</main>

<script>
    // Priority Chart
    const priorityCtx = document.getElementById('priorityChart').getContext('2d');
    new Chart(priorityCtx, {
        type: 'bar',
        data: {
            labels: ['Low', 'Medium', 'High', 'Emergency'],
            datasets: [{
                label: 'Request Count',
                data: <?php echo $priorityCountsJson; ?>,
                backgroundColor: ['#2ecc71', '#3498db', '#f1c40f', '#e74c3c'],
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1, font: { family: 'Poppins' } } },
                x: { ticks: { font: { family: 'Poppins', size: 11 } } }
            }
        }
    });

    // Source Chart
    const sourceCtx = document.getElementById('sourceChart').getContext('2d');
    new Chart(sourceCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo $sourcesJson; ?>,
            datasets: [{
                data: <?php echo $sourceCountsJson; ?>,
                backgroundColor: ['#a55eea', '#45aaf2', '#fa8231'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { font: { family: 'Poppins', size: 11 } }
                }
            }
        }
    });
</script>

</body>
</html>
