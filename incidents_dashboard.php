<?php
// incidents_dashboard.php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$userType = $_SESSION['user_type'] ?? '';

// Core Counts
$totalIncidents = $pdo->query("SELECT COUNT(*) FROM utility_incidents")->fetchColumn();
$pendingIncidents = $pdo->query("SELECT COUNT(*) FROM utility_incidents WHERE status = 'Submitted'")->fetchColumn();
$reviewingIncidents = $pdo->query("SELECT COUNT(*) FROM utility_incidents WHERE status = 'Under Review'")->fetchColumn();
$forwardedIncidents = $pdo->query("SELECT COUNT(*) FROM utility_incidents WHERE status = 'Forwarded to Maintenance System'")->fetchColumn();
$resolvedIncidents = $pdo->query("SELECT COUNT(*) FROM utility_incidents WHERE status IN ('Resolved', 'Closed')")->fetchColumn();

// Category frequency counts for charts
$categoryCounts = $pdo->query("
    SELECT c.name, COUNT(i.id) as count 
    FROM incident_categories c 
    LEFT JOIN utility_incidents i ON c.id = i.category_id 
    GROUP BY c.id
")->fetchAll();

$categoriesJson = json_encode(array_column($categoryCounts, 'name'));
$categoryCountsJson = json_encode(array_column($categoryCounts, 'count'));

// Recent incidents list
$recentIncidents = $pdo->query("
    SELECT i.*, c.name as category_name 
    FROM utility_incidents i 
    JOIN incident_categories c ON i.category_id = c.id 
    ORDER BY i.created_at DESC 
    LIMIT 6
")->fetchAll();

// Simulated AI Analytics (Complaints summarization logic)
$allDescriptions = $pdo->query("
    SELECT i.incident_id, c.name as category_name, i.description, i.location, i.priority 
    FROM utility_incidents i 
    JOIN incident_categories c ON i.category_id = c.id
")->fetchAll();

function generateAISummary($allIncidents) {
    if (empty($allIncidents)) {
        return "No incident descriptions available in database for AI text analysis.";
    }

    $summary = "<strong>LGU AI Assistant Insights (Generated " . date('F Y') . ")</strong><br><br>";
    
    // Grouping count
    $groups = [];
    $emergencies = 0;
    foreach ($allIncidents as $inc) {
        $groups[$inc['category_name']][] = $inc['location'];
        if ($inc['priority'] === 'Emergency') {
            $emergencies++;
        }
    }
    
    $summary .= "📊 <strong>Issue Clustering Summary:</strong><br>";
    foreach ($groups as $cat => $locs) {
        $locCounts = array_count_values($locs);
        $locStr = [];
        foreach ($locCounts as $loc => $qty) {
            $locStr[] = "{$loc} ({$qty} report" . ($qty > 1 ? 's' : '') . ")";
        }
        $summary .= "• <strong>{$cat}</strong> clusters detected at: " . implode(', ', $locStr) . ".<br>";
    }
    
    $summary .= "<br>⚠️ <strong>Urgency Notification:</strong><br>";
    if ($emergencies > 0) {
        $summary .= "• AI detected <strong>{$emergencies} critical emergency incident(s)</strong> requiring immediate administrative validation and dispatch to the Maintenance Management System.";
    } else {
        $summary .= "• No critical safety or structural emergency anomalies identified. General maintenance queues remain normal.";
    }
    
    return $summary;
}

$aiAnalysisOutput = generateAISummary($allDescriptions);

// AI Analytics — Incident Intelligence
$resolutionRate = ($totalIncidents > 0) ? round(($resolvedIncidents / $totalIncidents) * 100) : 100;
$emergencyIncidentCount = 0;
try {
    $emergencyIncidentCount = (int)$pdo->query("SELECT COUNT(*) FROM utility_incidents WHERE priority = 'Emergency'")->fetchColumn();
} catch (Throwable $e) {}

$incAiRecs = [];
if ($emergencyIncidentCount > 0) {
    $incAiRecs[] = ['icon' => 'fa-exclamation-circle', 'color' => '#e74c3c', 'priority' => 'High',
        'title' => 'Emergency Incidents Active',
        'text' => "{$emergencyIncidentCount} emergency incident(s) require immediate escalation to department heads."];
}
if ($pendingIncidents > 3) {
    $incAiRecs[] = ['icon' => 'fa-clock', 'color' => '#f39c12', 'priority' => 'Medium',
        'title' => 'High Pending Backlog',
        'text' => "{$pendingIncidents} reports are awaiting initial review. Consider allocating more staff for faster processing."];
} elseif ($pendingIncidents > 0) {
    $incAiRecs[] = ['icon' => 'fa-clipboard-list', 'color' => '#f39c12', 'priority' => 'Medium',
        'title' => 'Pending Reviews',
        'text' => "{$pendingIncidents} incident report(s) need initial review. Timely processing improves citizen satisfaction."];
}
if ($resolutionRate < 50 && $totalIncidents > 1) {
    $incAiRecs[] = ['icon' => 'fa-chart-line', 'color' => '#e74c3c', 'priority' => 'High',
        'title' => 'Low Resolution Rate',
        'text' => "Only {$resolutionRate}% of incidents resolved. Review bottlenecks in the resolution pipeline."];
} elseif ($resolutionRate >= 80) {
    $incAiRecs[] = ['icon' => 'fa-trophy', 'color' => '#27ae60', 'priority' => 'Info',
        'title' => 'Strong Resolution Performance',
        'text' => "Resolution rate is at {$resolutionRate}%. The incident management pipeline is performing well."];
}
if ($forwardedIncidents > 0) {
    $incAiRecs[] = ['icon' => 'fa-share', 'color' => '#a55eea', 'priority' => 'Info',
        'title' => 'Forwarded to Maintenance',
        'text' => "{$forwardedIncidents} incident(s) forwarded to the Maintenance System for coordinated repair."];
}
if (empty($incAiRecs)) {
    $incAiRecs[] = ['icon' => 'fa-check-circle', 'color' => '#27ae60', 'priority' => 'Info',
        'title' => 'All Clear', 'text' => 'No critical incident issues detected. Pipeline is operating normally.'];
}

// Retrieve all incidents for geolocations
$mapIncidents = $pdo->query("
    SELECT i.*, c.name as category_name 
    FROM utility_incidents i 
    JOIN incident_categories c ON i.category_id = c.id 
    WHERE i.latitude IS NOT NULL AND i.longitude IS NOT NULL
")->fetchAll();
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
    <title>LGU Incident Management Dashboard</title>
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Leaflet CSS and JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 35px;
        }

        .stat-card {
            border-radius: 16px;
            padding: 20px 18px;
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

        .stat-card.total      { background: linear-gradient(135deg, #1a3e7a, #2a5fc2); }
        .stat-card.pending    { background: linear-gradient(135deg, #7a5c0d, #c4920e); }
        .stat-card.reviewing  { background: linear-gradient(135deg, #7a2f0d, #c0440f); }
        .stat-card.forwarded  { background: linear-gradient(135deg, #4c1d7a, #7c3dbf); }
        .stat-card.resolved   { background: linear-gradient(135deg, #1a6b38, #25a259); }

        .stat-card-icon, .stat-icon {
            width: 52px; height: 52px;
            border-radius: 14px;
            background: rgba(255,255,255,0.18) !important;
            display: grid;
            place-items: center;
            font-size: 22px;
            flex-shrink: 0;
            color: #fff !important;
        }

        .stat-info {
            display: flex;
            flex-direction: column;
        }

        .stat-info h3 {
            font-size: 28px;
            font-weight: 700;
            color: #fff !important;
            line-height: 1;
            margin: 0;
        }

        .stat-info p {
            font-size: 11px;
            color: rgba(255,255,255,0.85) !important;
            text-transform: uppercase;
            font-weight: 600;
            margin-top: 4px;
            letter-spacing: 0.6px;
            margin-bottom: 0;
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

        /* Map styling */
        #map {
            width: 100%;
            height: 300px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
        }

        /* Recent Incident Feed */
        .feed-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 20px;
        }

        .feed-item {
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            border-radius: 10px;
            padding: 18px;
        }

        .feed-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .feed-id { font-size: 13px; font-weight: 700; color: #2c3e50; }
        
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 99px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-low { background: #e2fbe8; color: #1e7e34; }
        .badge-medium { background: #e0f2fe; color: #0284c7; }
        .badge-high { background: #fff4e5; color: #b45309; }
        .badge-emergency { background: #fde8e8; color: #bd2130; }

        /* Pipeline Boxes */
        .pipeline-box {
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            transition: all 0.2s ease;
        }
        .pipeline-box.submitted { background: linear-gradient(135deg, #eff6ff, #dbeafe); }
        .pipeline-box.reviewing { background: linear-gradient(135deg, #fef9c3, #fef3c7); }
        .pipeline-box.forwarded { background: linear-gradient(135deg, #fce7f3, #fecdd3); }
        .pipeline-box.resolved  { background: linear-gradient(135deg, #dcfce7, #bbf7d0); }
        .pipeline-box .num { font-size: 20px; font-weight: 700; color: #2c3e50; }
        .pipeline-box .label { font-size: 9px; text-transform: uppercase; font-weight: 600; color: #64748b; }

        .rec-card {
            padding: 12px 14px;
            border-radius: 10px;
            background: #f8fafc;
            transition: all 0.2s ease;
        }
        .rec-card .rec-title { font-weight: 600; font-size: 13px; color: #2c3e50; }
        .rec-card .rec-desc  { font-size: 12px; color: #64748b; line-height: 1.5; padding-left: 36px; }

        .recent-report-card {
            padding: 10px 15px;
            border-radius: 8px;
            background: #f8fafc;
            border-left: 3px solid #cbd5e1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s ease;
        }
        .recent-report-card .rep-cat { font-weight: 600; font-size: 13px; color: #2c3e50; }
        .recent-report-card .rep-loc { font-size: 11px; color: #64748b; }

        /* ===== DARK THEME OVERRIDES ===== */
        .dark-theme .card {
            background: rgba(30, 41, 59, 0.9) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: #f8fafc !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5) !important;
        }
        .dark-theme .dashboard-header h1 {
            color: #f8fafc !important;
        }

        .dark-theme .box {
            background: #1e293b !important;
            border: 1px solid #334155 !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3) !important;
            color: #f8fafc !important;
        }
        .dark-theme .box h3 {
            color: #f8fafc !important;
            border-bottom-color: #334155 !important;
        }
        .dark-theme .pipeline-box.submitted {
            background: rgba(59, 130, 246, 0.15) !important;
            border: 1px solid rgba(59, 130, 246, 0.3) !important;
        }
        .dark-theme .pipeline-box.reviewing {
            background: rgba(245, 158, 11, 0.15) !important;
            border: 1px solid rgba(245, 158, 11, 0.3) !important;
        }
        .dark-theme .pipeline-box.forwarded {
            background: rgba(168, 85, 247, 0.15) !important;
            border: 1px solid rgba(168, 85, 247, 0.3) !important;
        }
        .dark-theme .pipeline-box.resolved {
            background: rgba(16, 185, 129, 0.15) !important;
            border: 1px solid rgba(16, 185, 129, 0.3) !important;
        }
        .dark-theme .pipeline-box .num {
            color: #f8fafc !important;
        }
        .dark-theme .pipeline-box .label {
            color: #94a3b8 !important;
        }
        .dark-theme .rec-card {
            background: #0f172a !important;
            border: 1px solid #334155 !important;
        }
        .dark-theme .rec-card .rec-title {
            color: #f8fafc !important;
        }
        .dark-theme .rec-card .rec-desc {
            color: #cbd5e1 !important;
        }
        .dark-theme .recent-report-card {
            background: #0f172a !important;
            border-color: #334155 !important;
        }
        .dark-theme .recent-report-card .rep-cat {
            color: #f8fafc !important;
        }
        .dark-theme .recent-report-card .rep-loc {
            color: #94a3b8 !important;
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
    </style>
</head>
<body>

<?php include 'includes/utilities_sidebar.php'; ?>

<main class="main-content" id="mainContent">
    <div class="card">
        
        <!-- Header -->
        <div class="dashboard-header">
            <div>
                <h1><i class="fas fa-bullhorn"></i> Incident Reports Control</h1>
                <p style="color: #64748b; font-size: 14px; margin-top: 5px;">Monitor resident feedback, categorize issues, and coordinate workflows with LGU systems.</p>
            </div>
            <div>
                <a href="incidents_list.php" class="btn btn-primary"><i class="fas fa-list"></i> Manage Reports</a>
            </div>
        </div>

        <!-- Summary Cards Grid -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-info">
                    <h3><?php echo number_format($totalIncidents); ?></h3>
                    <p>Total Incidents</p>
                </div>
                <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
            </div>
            <div class="stat-card pending">
                <div class="stat-info">
                    <h3><?php echo number_format($pendingIncidents); ?></h3>
                    <p>Submitted</p>
                </div>
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
            </div>
            <div class="stat-card reviewing">
                <div class="stat-info">
                    <h3><?php echo number_format($reviewingIncidents); ?></h3>
                    <p>Under Review</p>
                </div>
                <div class="stat-icon"><i class="fas fa-search"></i></div>
            </div>
            <div class="stat-card forwarded">
                <div class="stat-info">
                    <h3><?php echo number_format($forwardedIncidents); ?></h3>
                    <p>Forwarded</p>
                </div>
                <div class="stat-icon"><i class="fas fa-paper-plane"></i></div>
            </div>
            <div class="stat-card resolved">
                <div class="stat-info">
                    <h3><?php echo number_format($resolvedIncidents); ?></h3>
                    <p>Resolved</p>
                </div>
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>

        <!-- Dashboard Analytics Grid -->
        <div class="dashboard-layout">
            <!-- Left Chart Category Frequency -->
            <div class="box">
                <h3><i class="fas fa-chart-bar"></i> Incident Frequency by Category</h3>
                <div style="position:relative; height:280px; width:100%; display:flex; justify-content:center; align-items:center;">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>

            <!-- Right AI Analytics Box -->
            <div class="box ai-box">
                <h3><i class="fas fa-robot"></i> LGU AI Text Complaint Summarizer</h3>
                <div class="ai-content">
                    <?php echo $aiAnalysisOutput; ?>
                </div>
            </div>
        </div>

        <!-- AI Recommendations Row -->
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:25px; margin-bottom:35px;">
            <div class="box">
                <h3><i class="fas fa-brain" style="color:#3762c8;"></i> AI Resolution Pipeline
                    <span style="font-size:11px;font-weight:400;color:#64748b;margin-left:auto;">Resolution Rate: <strong style="color:<?php echo $resolutionRate >= 70 ? '#27ae60' : ($resolutionRate >= 40 ? '#f39c12' : '#e74c3c'); ?>;"><?php echo $resolutionRate; ?>%</strong></span>
                </h3>
                <div style="margin-bottom:14px;">
                    <div style="display:flex;justify-content:space-between;font-size:12px;color:#64748b;margin-bottom:6px;"><span>Resolved</span><span><?php echo $resolvedIncidents; ?>/<?php echo $totalIncidents; ?></span></div>
                    <div style="height:12px;background:#e2e8f0;border-radius:99px;overflow:hidden;">
                        <div style="height:100%;width:<?php echo $resolutionRate; ?>%;background:linear-gradient(90deg,#3762c8,#6384d2);border-radius:99px;transition:width 1s;"></div>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;text-align:center;">
                    <div class="pipeline-box submitted">
                        <div class="num"><?php echo $pendingIncidents; ?></div>
                        <div class="label">Submitted</div>
                    </div>
                    <div class="pipeline-box reviewing">
                        <div class="num"><?php echo $reviewingIncidents; ?></div>
                        <div class="label">Reviewing</div>
                    </div>
                    <div class="pipeline-box forwarded">
                        <div class="num"><?php echo $forwardedIncidents; ?></div>
                        <div class="label">Forwarded</div>
                    </div>
                    <div class="pipeline-box resolved">
                        <div class="num"><?php echo $resolvedIncidents; ?></div>
                        <div class="label">Resolved</div>
                    </div>
                </div>
            </div>
            <div class="box">
                <h3><i class="fas fa-lightbulb" style="color:#f59e0b;"></i> AI Recommendations <span style="background:#3762c8;color:#fff;font-size:10px;padding:2px 8px;border-radius:99px;margin-left:8px;"><?php echo count($incAiRecs); ?></span></h3>
                <div style="display:flex; flex-direction:column; gap:10px; max-height:220px; overflow-y:auto;">
                    <?php foreach ($incAiRecs as $rec): ?>
                    <div class="rec-card" style="border-left:4px solid <?php echo $rec['color']; ?>;">
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                            <div style="width:28px;height:28px;border-radius:7px;background:<?php echo $rec['color']; ?>;display:flex;align-items:center;justify-content:center;color:white;font-size:12px;flex-shrink:0;">
                                <i class="fas <?php echo $rec['icon']; ?>"></i>
                            </div>
                            <span class="rec-title"><?php echo $rec['title']; ?></span>
                            <span style="font-size:9px;font-weight:700;padding:2px 8px;border-radius:99px;text-transform:uppercase;margin-left:auto;background:<?php echo $rec['color']; ?>20;color:<?php echo $rec['color']; ?>;"><?php echo $rec['priority']; ?></span>
                        </div>
                        <div class="rec-desc"><?php echo $rec['text']; ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Map & Recent incidents row -->
        <div class="dashboard-layout">
            <!-- Left: GIS Incident Maps -->
            <div class="box">
                <h3><i class="fas fa-map-marked-alt"></i> Geolocated Incident Pins</h3>
                <div id="map"></div>
            </div>

            <!-- Right: Recent Reports Feed -->
            <div class="box">
                <h3><i class="fas fa-bell"></i> Recently Submitted Reports</h3>
                <div style="max-height: 300px; overflow-y: auto; display: flex; flex-direction: column; gap:10px;">
                    <?php if (empty($recentIncidents)): ?>
                        <div style="color: #64748b; font-size: 13px;">No recent incident reports.</div>
                    <?php else: ?>
                        <?php foreach ($recentIncidents as $inc): ?>
                            <div class="recent-report-card">
                                <div>
                                    <div class="rep-cat"><?php echo htmlspecialchars($inc['category_name']); ?></div>
                                    <div class="rep-loc"><?php echo htmlspecialchars($inc['location']); ?></div>
                                </div>
                                <span class="badge badge-<?php echo strtolower($inc['priority']); ?>"><?php echo htmlspecialchars($inc['priority']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</main>

<script>
    // Category Chart
    const categoryCtx = document.getElementById('categoryChart').getContext('2d');
    new Chart(categoryCtx, {
        type: 'bar',
        data: {
            labels: <?php echo $categoriesJson; ?>,
            datasets: [{
                label: 'Report Count',
                data: <?php echo $categoryCountsJson; ?>,
                backgroundColor: '#3762c8',
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1, font: { family: 'Poppins' } } },
                x: { ticks: { font: { family: 'Poppins', size: 10 } } }
            }
        }
    });

    // Map configuration
    const map = L.map('map').setView([14.5995, 120.9842], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    const incidents = <?php echo json_encode($mapIncidents); ?>;
    
    incidents.forEach(inc => {
        const customIcon = L.divIcon({
            html: `<div style="background-color: #e74c3c; width: 12px; height: 12px; border-radius: 50%; border: 2px solid white; box-shadow: 0 0 5px rgba(0,0,0,0.5);"></div>`,
            className: 'custom-div-icon',
            iconSize: [12, 12]
        });

        const popupHtml = `
            <div style="font-family: 'Poppins', sans-serif; width: 180px;">
                <div style="font-weight: 700; font-size: 13px; color: #2c3e50;">${inc.category_name}</div>
                <div style="font-size: 10px; color: #3762c8; font-weight: 600; margin-bottom: 5px;">${inc.incident_id}</div>
                <p style="font-size: 11px; color: #64748b; line-height: 1.3;">${inc.description}</p>
                <div style="font-size: 10px; font-weight: 600; margin-top: 5px;"><i class="fas fa-map-marker-alt"></i> ${inc.location}</div>
            </div>
        `;

        L.marker([parseFloat(inc.latitude), parseFloat(inc.longitude)], { icon: customIcon })
            .bindPopup(popupHtml)
            .addTo(map);
    });
</script>

</body>
</html>
