<?php
// energy_dashboard.php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$userType = $_SESSION['user_type'] ?? '';

// Core Counts
$totalConsumption = $pdo->query("SELECT SUM(consumption_kwh) FROM energy_consumption_records")->fetchColumn() ?: 0;
$totalCost = $pdo->query("SELECT SUM(cost) FROM energy_consumption_records")->fetchColumn() ?: 0;
$pendingRecommendations = $pdo->query("SELECT COUNT(*) FROM energy_recommendations WHERE status = 'Pending'")->fetchColumn();
$successfulSyncs = $pdo->query("SELECT COUNT(*) FROM energy_sync_logs WHERE status = 'Successful'")->fetchColumn();

// Chart Data: Monthly consumption trend, broken down per facility/asset —
// COALESCE(facility_name, asset_type) so real CPRF facilities each get their
// own series (previously every CPRF row shared the literal 'CPRF Facility'
// asset_type and collapsed into one bucket).
$monthlyTrendRows = $pdo->query("
    SELECT COALESCE(facility_name, asset_type) as label, month_year, SUM(consumption_kwh) as kwh
    FROM energy_consumption_records
    GROUP BY label, month_year
    ORDER BY month_year ASC
")->fetchAll(PDO::FETCH_ASSOC);

$monthsSet = [];
$byFacility = [];
foreach ($monthlyTrendRows as $row) {
    $monthsSet[$row['month_year']] = true;
    $byFacility[$row['label']][$row['month_year']] = (float)$row['kwh'];
}
$monthsList = array_keys($monthsSet);
sort($monthsList);

$facilityColors = ['#3762c8', '#2ecc71', '#f1c40f', '#e74c3c', '#9b59b6', '#1abc9c', '#e67e22', '#34495e'];
$trendDatasets = [];
$colorIndex = 0;
foreach ($byFacility as $label => $monthMap) {
    $data = [];
    foreach ($monthsList as $m) {
        $data[] = $monthMap[$m] ?? 0;
    }
    $trendDatasets[] = [
        'label' => $label,
        'data' => $data,
        'borderColor' => $facilityColors[$colorIndex % count($facilityColors)],
        'backgroundColor' => $facilityColors[$colorIndex % count($facilityColors)],
        'fill' => false,
        'tension' => 0.3,
    ];
    $colorIndex++;
}
$monthsJson = json_encode($monthsList);
$trendDatasetsJson = json_encode($trendDatasets);

// Chart Data: Top consuming sectors/facilities — same COALESCE, so each CPRF
// facility gets its own bar instead of one shared "CPRF Facility" bucket.
$sectorCounts = $pdo->query("
    SELECT COALESCE(facility_name, asset_type) as label, SUM(consumption_kwh) as kwh
    FROM energy_consumption_records
    GROUP BY label
")->fetchAll(PDO::FETCH_KEY_PAIR);
$sectorsJson = json_encode(array_keys($sectorCounts));
$sectorKwhJson = json_encode(array_values($sectorCounts));

// Recent Recommendations
$recentRecs = $pdo->query("SELECT * FROM energy_recommendations ORDER BY date_received DESC LIMIT 5")->fetchAll();

// Retrieve all records for AI Summary
$allRecords = $pdo->query("
    SELECT COALESCE(facility_name, 'Streetlight Pole / Asset') as name, asset_type, location, month_year, consumption_kwh 
    FROM energy_consumption_records
")->fetchAll();

function generateAIEnergySummary($records, $pendingRecs) {
    if (empty($records)) {
        return "No energy consumption records available in database for AI text summary.";
    }

    $summary = "<strong>LGU AI Assistant Energy Data Summary (" . date('F Y') . ")</strong><br><br>";
    
    // Grouping by facility (falls back to asset_type for non-CPRF UMAN
    // assets like streetlights, which have no facility_name of their own)
    $groups = [];
    foreach ($records as $r) {
        if (!isset($groups[$r['name']])) {
            $groups[$r['name']] = 0;
        }
        $groups[$r['name']] += $r['consumption_kwh'];
    }
    
    $summary .= "📊 <strong>Sectored Consumption Aggregation:</strong><br>";
    foreach ($groups as $type => $kwh) {
        $summary .= "• <strong>{$type}:</strong> " . number_format($kwh, 2) . " kWh consumed in total recorded periods.<br>";
    }
    
    $summary .= "<br>💡 <strong>Efficiency Advisories Status:</strong><br>";
    if ($pendingRecs > 0) {
        $summary .= "• There are currently <strong>{$pendingRecs} unacknowledged advisory recommendation(s)</strong> received from the external Energy Efficiency System.";
    } else {
        $summary .= "• All efficiency advisory recommendations have been acknowledged or implemented.";
    }
    
    return $summary;
}

$aiDigest = generateAIEnergySummary($allRecords, $pendingRecommendations);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Energy Consumption Management</title>
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

        .stat-card.consumption { border-left-color: #3762c8; }
        .stat-card.cost { border-left-color: #2ecc71; }
        .stat-card.recs { border-left-color: #f1c40f; }
        .stat-card.sync { border-left-color: #a55eea; }

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
        .badge-pending { background: #fef3c7; color: #d97706; }
        .badge-acknowledged { background: #e0f2fe; color: #0284c7; }
    </style>
</head>
<body>

<?php include 'includes/utilities_sidebar.php'; ?>

<main class="main-content" id="mainContent">
    <div class="card">
        
        <!-- Header -->
        <div class="dashboard-header">
            <div>
                <h1><i class="fas fa-bolt"></i> Energy Consumption Control</h1>
                <p style="color: #64748b; font-size: 14px; margin-top: 5px;">Monitor facility electricity consumption logs and coordinate recommendations with the external efficiency system.</p>
            </div>
            <div style="display:flex; gap:10px;">
                <a href="energy_records.php" class="btn btn-primary"><i class="fas fa-calculator"></i> Consumption Records</a>
                <a href="energy_sync.php" class="btn btn-outline"><i class="fas fa-sync"></i> Transmission Logs</a>
                <a href="energy_recommendations.php" class="btn btn-outline"><i class="fas fa-lightbulb"></i> Advisories</a>
            </div>
        </div>

        <!-- Summary Cards Grid -->
        <div class="stats-grid">
            <div class="stat-card consumption">
                <div class="stat-info">
                    <h3><?php echo number_format($totalConsumption, 2); ?> <span style="font-size:12px;">kWh</span></h3>
                    <p>Total Consumption</p>
                </div>
                <div class="stat-icon"><i class="fas fa-bolt"></i></div>
            </div>
            <div class="stat-card cost">
                <div class="stat-info">
                    <h3>₱<?php echo number_format($totalCost, 2); ?></h3>
                    <p>Total Estimated Cost</p>
                </div>
                <div class="stat-icon"><i class="fas fa-coins"></i></div>
            </div>
            <div class="stat-card recs">
                <div class="stat-info">
                    <h3><?php echo number_format($pendingRecommendations); ?></h3>
                    <p>Pending Advisories</p>
                </div>
                <div class="stat-icon"><i class="fas fa-lightbulb"></i></div>
            </div>
            <div class="stat-card sync">
                <div class="stat-info">
                    <h3><?php echo number_format($successfulSyncs); ?></h3>
                    <p>Successful Syncs</p>
                </div>
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>

        <!-- Consumption trends chart and AI summarizer -->
        <div class="dashboard-layout">
            <!-- Left Chart Category Frequency -->
            <div class="box">
                <h3><i class="fas fa-chart-line"></i> Monthly Electricity Consumption Trends</h3>
                <div style="position:relative; height:280px; width:100%; display:flex; justify-content:center; align-items:center;">
                    <canvas id="consumptionChart"></canvas>
                </div>
            </div>

            <!-- Right AI Analytics Box -->
            <div class="box ai-box">
                <h3><i class="fas fa-robot"></i> LGU AI Energy Consumption Digest</h3>
                <div class="ai-content">
                    <?php echo $aiDigest; ?>
                </div>
            </div>
        </div>

        <!-- Sector breakdown and Advisories -->
        <div class="dashboard-layout">
            <!-- Left: Sector distribution chart -->
            <div class="box">
                <h3><i class="fas fa-chart-bar"></i> Consumption by Sector Type</h3>
                <div style="position:relative; height:280px; width:100%; display:flex; justify-content:center; align-items:center;">
                    <canvas id="sectorChart"></canvas>
                </div>
            </div>

            <!-- Right: Advisories list feed -->
            <div class="box">
                <h3><i class="fas fa-bell"></i> Received Efficiency Advisories</h3>
                <div style="display:flex; flex-direction:column; gap:10px; max-height:280px; overflow-y:auto;">
                    <?php if (empty($recentRecs)): ?>
                        <div style="color: #64748b; font-size: 13px;">No advisories received.</div>
                    <?php else: ?>
                        <?php foreach ($recentRecs as $rec): ?>
                            <div class="log-item">
                                <div>
                                    <div style="font-weight:600; font-size:13px; color:#2c3e50; display:flex; align-items:center; gap:8px;">
                                        <span class="badge badge-<?php echo strtolower($rec['status']); ?>"><?php echo htmlspecialchars($rec['status']); ?></span>
                                        <?php echo htmlspecialchars($rec['recommendation_title']); ?>
                                    </div>
                                    <div style="font-size:11px; color:#64748b; margin-top:3px;"><?php echo htmlspecialchars($rec['description']); ?></div>
                                </div>
                                <span style="font-size:10px; color:#94a3b8; font-style:italic; white-space:nowrap;"><?php echo date('M d', strtotime($rec['date_received'])); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</main>

<script>
    // Monthly Consumption Chart — one line per facility/asset
    const consumptionCtx = document.getElementById('consumptionChart').getContext('2d');
    new Chart(consumptionCtx, {
        type: 'line',
        data: {
            labels: <?php echo $monthsJson; ?>,
            datasets: <?php echo $trendDatasetsJson; ?>
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: true, position: 'bottom', labels: { font: { family: 'Poppins', size: 10 } } } },
            scales: {
                y: { beginAtZero: true, ticks: { font: { family: 'Poppins' } } },
                x: { ticks: { font: { family: 'Poppins', size: 11 } } }
            }
        }
    });

    // Sector Distribution Chart
    const sectorCtx = document.getElementById('sectorChart').getContext('2d');
    new Chart(sectorCtx, {
        type: 'bar',
        data: {
            labels: <?php echo $sectorsJson; ?>,
            datasets: [{
                label: 'Consumption (kWh)',
                data: <?php echo $sectorKwhJson; ?>,
                backgroundColor: '#2ecc71',
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { font: { family: 'Poppins' } } },
                x: { ticks: { font: { family: 'Poppins', size: 10 } } }
            }
        }
    });
</script>

</body>
</html>
