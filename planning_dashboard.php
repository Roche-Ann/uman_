<?php
// planning_dashboard.php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$userType = $_SESSION['user_type'] ?? '';

// Core Counts
$totalAreas = $pdo->query("SELECT COUNT(*) FROM utility_coverage_records")->fetchColumn();
$activeExpansions = $pdo->query("SELECT COUNT(*) FROM utility_expansion_requests WHERE status IN ('Pending', 'Under Review')")->fetchColumn();
$importedProjects = $pdo->query("SELECT COUNT(*) FROM development_projects")->fetchColumn();
$overloadedZones = $pdo->query("SELECT COUNT(*) FROM utility_capacity_records WHERE status IN ('Near Capacity', 'Overloaded')")->fetchColumn();

// Chart Data: Coverage Status counts
$coverageCounts = $pdo->query("
    SELECT coverage_status, COUNT(id) as count 
    FROM utility_coverage_records 
    GROUP BY coverage_status
")->fetchAll(PDO::FETCH_KEY_PAIR);

$statuses = ['Fully Covered', 'Partially Covered', 'Not Covered'];
$coverageCountsMap = array_fill_keys($statuses, 0);
foreach ($coverageCounts as $status => $qty) {
    if (isset($coverageCountsMap[$status])) {
        $coverageCountsMap[$status] = $qty;
    }
}
$coverageLabelsJson = json_encode($statuses);
$coverageCountsJson = json_encode(array_values($coverageCountsMap));

// Map coordinates data retrieval
$mapCoverage = $pdo->query("SELECT * FROM utility_coverage_records")->fetchAll();
$mapProjects = $pdo->query("SELECT * FROM development_projects WHERE latitude IS NOT NULL")->fetchAll();

// Capacity records for AI/Table
$capacityRecords = $pdo->query("SELECT * FROM utility_capacity_records")->fetchAll();

// Recent logs
$recentLogs = $pdo->query("SELECT * FROM planning_coordination_logs ORDER BY logged_at DESC LIMIT 5")->fetchAll();

// AI Planning text generator
function generateAIPlanningSummary($coverage, $projects, $capacities) {
    $summary = "<strong>LGU AI Assistant Planning Digest (" . date('F Y') . ")</strong><br><br>";
    
    // Grouping by coverage
    $groups = ['Fully' => 0, 'Partially' => 0, 'Not' => 0];
    foreach ($coverage as $c) {
        if ($c['coverage_status'] === 'Fully Covered') $groups['Fully']++;
        elseif ($c['coverage_status'] === 'Partially Covered') $groups['Partially']++;
        else $groups['Not']++;
    }
    
    $summary .= "🛰️ <strong>Municipal Utility Coverage:</strong><br>";
    $summary .= "• Fully serviced zones: {$groups['Fully']}. Partially serviced: {$groups['Partially']}. Lack of coverage zones: {$groups['Not']}.<br>";
    
    // Capacity Overload warnings
    $warnings = [];
    foreach ($capacities as $cap) {
        if ($cap['status'] !== 'Normal') {
            $warnings[] = "{$cap['location_zone']} ({$cap['capacity_type']} is {$cap['status']})";
        }
    }
    
    $summary .= "<br>⚡ <strong>Capacity Alerts:</strong><br>";
    if (!empty($warnings)) {
        $summary .= "• Warning: " . implode(', ', $warnings) . " require grid/infrastructure upgrade reviews prior to endorsing new building permits.";
    } else {
        $summary .= "• All monitored utility volumes and loads are operating within safe baseline parameters.";
    }
    
    // Development project impacts
    $summary .= "<br><br>🏗️ <strong>Imported Project Impact Reviews:</strong><br>";
    foreach ($projects as $p) {
        $summary .= "• <strong>{$p['project_name']}</strong> requires: {$p['utility_requirements']} (Readiness: {$p['readiness_status']}).<br>";
    }
    
    return $summary;
}

$aiDigest = generateAIPlanningSummary($mapCoverage, $mapProjects, $capacityRecords);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Utility Planning & GIS Dashboard</title>
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Leaflet CSS & JS -->
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
        .stat-card.expansion { border-left-color: #f1c40f; }
        .stat-card.projects { border-left-color: #a55eea; }
        .stat-card.capacity { border-left-color: #e74c3c; }

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

        /* Map styling */
        #map {
            width: 100%;
            height: 380px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
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
        }
        .badge {
            font-size: 9px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 99px;
            text-transform: uppercase;
        }
        .badge-inbound { background: #e0f2fe; color: #0284c7; }
        .badge-outbound { background: #e2fbe8; color: #1e7e34; }
    </style>
</head>
<body>

<?php include 'includes/utilities_sidebar.php'; ?>

<main class="main-content" id="mainContent">
    <div class="card">
        
        <!-- Header -->
        <div class="dashboard-header">
            <div>
                <h1><i class="fas fa-map-marked-alt"></i> Utility Planning Hub</h1>
                <p style="color: #64748b; font-size: 14px; margin-top: 5px;">Track utility coverage maps, submit service expansions, and review imported LGU urban projects.</p>
            </div>
            <div style="display:flex; gap:10px;">
                <a href="planning_coverage.php" class="btn btn-primary"><i class="fas fa-satellite"></i> Coverage Records</a>
                <a href="planning_expansions.php" class="btn btn-outline"><i class="fas fa-plus"></i> Expansion Requests</a>
                <a href="planning_projects.php" class="btn btn-outline"><i class="fas fa-building"></i> Urban Projects</a>
            </div>
        </div>

        <!-- Summary Cards Grid -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-info">
                    <h3><?php echo number_format($totalAreas); ?></h3>
                    <p>Coverage Zones</p>
                </div>
                <div class="stat-icon"><i class="fas fa-globe-asia"></i></div>
            </div>
            <div class="stat-card expansion">
                <div class="stat-info">
                    <h3><?php echo number_format($activeExpansions); ?></h3>
                    <p>Active Expansions</p>
                </div>
                <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
            </div>
            <div class="stat-card projects">
                <div class="stat-info">
                    <h3><?php echo number_format($importedProjects); ?></h3>
                    <p>Urban Plans</p>
                </div>
                <div class="stat-icon"><i class="fas fa-city"></i></div>
            </div>
            <div class="stat-card capacity">
                <div class="stat-info">
                    <h3><?php echo number_format($overloadedZones); ?></h3>
                    <p>Capacities Near Limit</p>
                </div>
                <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
            </div>
        </div>

        <!-- Interactive Map row -->
        <div class="dashboard-layout" style="grid-template-columns: 1.5fr 1fr;">
            <!-- Left: GIS Coverage Circle Layers Map -->
            <div class="box">
                <h3><i class="fas fa-layer-group"></i> GIS Utility Coverage & Project Map</h3>
                <div id="map"></div>
            </div>

            <!-- Right: AI Analytics Summarizer -->
            <div class="box ai-box">
                <h3><i class="fas fa-robot"></i> LGU AI Utility Planning Assistant</h3>
                <div class="ai-content">
                    <?php echo $aiDigest; ?>
                </div>
            </div>
        </div>

        <!-- Charts and logs row -->
        <div class="dashboard-layout">
            <!-- Left: Coverage status breakdown chart -->
            <div class="box">
                <h3><i class="fas fa-chart-pie"></i> Utility Coverage Level Breakdown</h3>
                <div style="position:relative; height:280px; width:100%; display:flex; justify-content:center; align-items:center;">
                    <canvas id="coverageChart"></canvas>
                </div>
            </div>

            <!-- Right: Coordination logs feed -->
            <div class="box">
                <h3><i class="fas fa-exchange-alt"></i> External Planning Sync Logs</h3>
                <div style="display:flex; flex-direction:column; gap:10px; max-height:280px; overflow-y:auto;">
                    <?php if (empty($recentLogs)): ?>
                        <div style="color: #64748b; font-size: 13px;">No external logs available.</div>
                    <?php else: ?>
                        <?php foreach ($recentLogs as $log): ?>
                            <div class="log-item">
                                <div>
                                    <div style="font-weight:600; font-size:13px; color:#2c3e50; display:flex; align-items:center; gap:8px;">
                                        <span class="badge badge-<?php echo strtolower($log['direction']); ?>"><?php echo htmlspecialchars($log['direction']); ?></span>
                                        <?php echo htmlspecialchars($log['log_type']); ?>
                                    </div>
                                    <div style="font-size:11px; color:#64748b; margin-top:3px;"><?php echo htmlspecialchars($log['details']); ?></div>
                                </div>
                                <span style="font-size:10px; color:#94a3b8; font-style:italic;"><?php echo date('M d, h:i A', strtotime($log['logged_at'])); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</main>

<script>
    // Coverage Chart.js
    const coverageCtx = document.getElementById('coverageChart').getContext('2d');
    new Chart(coverageCtx, {
        type: 'pie',
        data: {
            labels: <?php echo $coverageLabelsJson; ?>,
            datasets: [{
                data: <?php echo $coverageCountsJson; ?>,
                backgroundColor: ['#2ecc71', '#f1c40f', '#e74c3c'],
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

    // Map initialization
    const map = L.map('map').setView([14.5995, 120.9842], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    // Plot coverage circles
    const coverageRecords = <?php echo json_encode($mapCoverage); ?>;
    coverageRecords.forEach(c => {
        let color = '#2ecc71';
        if (c.coverage_status === 'Partially Covered') color = '#f1c40f';
        else if (c.coverage_status === 'Not Covered') color = '#e74c3c';

        L.circle([parseFloat(c.latitude), parseFloat(c.longitude)], {
            color: color,
            fillColor: color,
            fillOpacity: 0.25,
            radius: parseInt(c.radius_meters)
        }).bindPopup(`
            <div style="font-family:'Poppins';">
                <strong>${c.area_name}</strong><br>
                Type: ${c.coverage_type}<br>
                Status: <strong>${c.coverage_status}</strong><br>
                <span style="font-size:10px; color:#64748b;">${c.remarks || ''}</span>
            </div>
        `).addTo(map);
    });

    // Plot Development plans
    const projects = <?php echo json_encode($mapProjects); ?>;
    projects.forEach(p => {
        const customIcon = L.divIcon({
            html: `<div style="background-color: #3762c8; width: 14px; height: 14px; border-radius: 50%; border: 2px solid white; box-shadow: 0 0 5px rgba(0,0,0,0.5);"></div>`,
            className: 'custom-div-icon',
            iconSize: [14, 14]
        });

        L.marker([parseFloat(p.latitude), parseFloat(p.longitude)], { icon: customIcon })
            .bindPopup(`
                <div style="font-family:'Poppins'; width:180px;">
                    <div style="font-weight:700; font-size:12px; color:#2c3e50;">${p.project_name}</div>
                    <div style="font-size:10px; color:#64748b; font-style:italic;">${p.location}</div>
                    <p style="font-size:10px; margin-top:5px;">Requirements: ${p.utility_requirements}</p>
                    <div style="font-size:10px; font-weight:600; margin-top:5px;">Readiness: ${p.readiness_status}</div>
                </div>
            `).addTo(map);
    });
</script>

</body>
</html>
