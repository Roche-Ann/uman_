<?php
// facility_dashboard.php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$userType = $_SESSION['user_type'] ?? '';

// Core Counts
$totalFacilities = $pdo->query("SELECT COUNT(*) FROM public_facilities")->fetchColumn();
$fullyReady = $pdo->query("SELECT COUNT(*) FROM public_facilities WHERE utility_status = 'Fully Ready'")->fetchColumn();
$partiallyReady = $pdo->query("SELECT COUNT(*) FROM public_facilities WHERE utility_status = 'Partially Ready'")->fetchColumn();
$notReady = $pdo->query("SELECT COUNT(*) FROM public_facilities WHERE utility_status = 'Not Ready'")->fetchColumn();
$activeIncidents = $pdo->query("SELECT COUNT(*) FROM facility_incidents WHERE status != 'Resolved'")->fetchColumn();
$upcomingBookings = $pdo->query("SELECT COUNT(*) FROM facility_bookings WHERE booking_date >= CURDATE()")->fetchColumn();

// Chart Data: Facility Type breakdown
$typeCounts = $pdo->query("
    SELECT facility_type, COUNT(id) as count 
    FROM public_facilities 
    GROUP BY facility_type
")->fetchAll(PDO::FETCH_KEY_PAIR);
$typesJson = json_encode(array_keys($typeCounts));
$typeCountsJson = json_encode(array_values($typeCounts));

// Retrieve geolocations for Leaflet
$mapFacilities = $pdo->query("SELECT * FROM public_facilities WHERE latitude IS NOT NULL")->fetchAll();

// Recent incidents list
$recentIncidents = $pdo->query("
    SELECT i.*, f.name as facility_name 
    FROM facility_incidents i 
    JOIN public_facilities f ON i.public_facility_id = f.id 
    ORDER BY i.created_at DESC 
    LIMIT 5
")->fetchAll();

// Upcoming bookings list
$recentBookings = $pdo->query("
    SELECT b.*, f.name as facility_name, f.utility_status 
    FROM facility_bookings b 
    JOIN public_facilities f ON b.public_facility_id = f.id 
    WHERE b.booking_date >= CURDATE() 
    ORDER BY b.booking_date ASC 
    LIMIT 4
")->fetchAll();

// AI Facility Advisor text
function generateAIFacilitySummary($facilities, $incidents, $bookings) {
    if (empty($facilities)) {
        return "No facility records logged in database for AI text evaluation.";
    }

    $summary = "<strong>LGU AI Assistant Facilities Advisor (" . date('F Y') . ")</strong><br><br>";
    
    // Grouping by utility condition
    $groups = ['Fully' => 0, 'Partially' => 0, 'Not' => 0];
    foreach ($facilities as $f) {
        if ($f['utility_status'] === 'Fully Ready') $groups['Fully']++;
        elseif ($f['utility_status'] === 'Partially Ready') $groups['Partially']++;
        else $groups['Not']++;
    }
    
    $summary .= "🏢 <strong>Readiness Distribution:</strong><br>";
    $summary .= "• Fully Ready: {$groups['Fully']}. Partially Ready: {$groups['Partially']}. Not Ready: {$groups['Not']}.<br>";
    
    // Active incidents
    $active = [];
    foreach ($incidents as $inc) {
        if ($inc['status'] !== 'Resolved') {
            $active[] = "{$inc['facility_name']} ({$inc['incident_type']})";
        }
    }
    
    $summary .= "<br>⚠️ <strong>Priority Maintenance Alerts:</strong><br>";
    if (!empty($active)) {
        $summary .= "• Warning: Active utility outages at: " . implode(', ', $active) . ". Recommended coordination for quick technician dispatches.";
    } else {
        $summary .= "• No active utility incidents logged. Grids are operating under normal conditions.";
    }
    
    // Booking impact warnings
    $bookingWarnings = [];
    foreach ($bookings as $bk) {
        if ($bk['utility_status'] !== 'Fully Ready' && $bk['expected_attendance'] >= 100) {
            $bookingWarnings[] = "<strong>{$bk['event_name']}</strong> at {$bk['facility_name']} (Expected: {$bk['expected_attendance']} guests, Readiness: {$bk['utility_status']})";
        }
    }
    
    if (!empty($bookingWarnings)) {
        $summary .= "<br>📅 <strong>Reservation Utility Checks:</strong><br>";
        $summary .= "• Caution: The following upcoming events are scheduled at venues with partial/no utility readiness:<br>";
        foreach ($bookingWarnings as $warn) {
            $summary .= "  - {$warn}.<br>";
        }
    }
    
    return $summary;
}

$aiDigest = generateAIFacilitySummary($mapFacilities, $recentIncidents, $recentBookings);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Facility Utility Control Panel</title>
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
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-left: 5px solid #cbd5e1;
        }

        .stat-card.total { border-left-color: #3762c8; }
        .stat-card.ready { border-left-color: #2ecc71; }
        .stat-card.partial { border-left-color: #f1c40f; }
        .stat-card.notready { border-left-color: #e74c3c; }
        .stat-card.incident { border-left-color: #e74c3c; }
        .stat-card.booking { border-left-color: #a55eea; }

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
        .badge-fullyready { background: #e2fbe8; color: #1e7e34; }
        .badge-partiallyready { background: #fff4e5; color: #b45309; }
        .badge-notready { background: #fde8e8; color: #bd2130; }
    </style>
</head>
<body>

<?php include 'includes/utilities_sidebar.php'; ?>

<main class="main-content" id="mainContent">
    <div class="card">
        
        <!-- Header -->
        <div class="dashboard-header">
            <div>
                <h1><i class="fas fa-warehouse"></i> Public Facility Utilities</h1>
                <p style="color: #64748b; font-size: 14px; margin-top: 5px;">Monitor electricity and water availability status logs of parks, gyms, halls, and evacuation centers.</p>
            </div>
            <div style="display:flex; gap:10px;">
                <a href="facility_list.php" class="btn btn-primary"><i class="fas fa-satellite"></i> Manage Facilities</a>
                <a href="facility_incidents.php" class="btn btn-outline"><i class="fas fa-exclamation-triangle"></i> Facility Incidents</a>
                <a href="facility_highusage.php" class="btn btn-outline"><i class="fas fa-calendar-alt"></i> Booking Overlay</a>
            </div>
        </div>

        <!-- Summary Cards Grid -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-info">
                    <h3><?php echo number_format($totalFacilities); ?></h3>
                    <p>Monitored Facilities</p>
                </div>
                <div class="stat-icon"><i class="fas fa-city"></i></div>
            </div>
            <div class="stat-card ready">
                <div class="stat-info">
                    <h3><?php echo number_format($fullyReady); ?></h3>
                    <p>Fully Ready</p>
                </div>
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            </div>
            <div class="stat-card partial">
                <div class="stat-info">
                    <h3><?php echo number_format($partiallyReady); ?></h3>
                    <p>Partially Ready</p>
                </div>
                <div class="stat-icon"><i class="fas fa-adjust"></i></div>
            </div>
            <div class="stat-card notready">
                <div class="stat-info">
                    <h3><?php echo number_format($notReady); ?></h3>
                    <p>Not Ready</p>
                </div>
                <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            </div>
            <div class="stat-card incident">
                <div class="stat-info">
                    <h3><?php echo number_format($activeIncidents); ?></h3>
                    <p>Active Incidents</p>
                </div>
                <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
            </div>
            <div class="stat-card booking">
                <div class="stat-info">
                    <h3><?php echo number_format($upcomingBookings); ?></h3>
                    <p>Upcoming Bookings</p>
                </div>
                <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
            </div>
        </div>

        <!-- Maps and AI Advisor -->
        <div class="dashboard-layout" style="grid-template-columns: 1.5fr 1fr;">
            <!-- Left: Map view -->
            <div class="box">
                <h3><i class="fas fa-map-marked-alt"></i> Public Facilities Map Pinpoint Layers</h3>
                <div id="map"></div>
            </div>

            <!-- Right: AI Advisor -->
            <div class="box ai-box">
                <h3><i class="fas fa-robot"></i> LGU AI Facility Advisor</h3>
                <div class="ai-content">
                    <?php echo $aiDigest; ?>
                </div>
            </div>
        </div>

        <!-- Type distributions and Recent Activities -->
        <div class="dashboard-layout">
            <!-- Left: Facility type breakdown -->
            <div class="box">
                <h3><i class="fas fa-chart-pie"></i> Facilities Monitored by Type</h3>
                <div style="position:relative; height:280px; width:100%; display:flex; justify-content:center; align-items:center;">
                    <canvas id="typeChart"></canvas>
                </div>
            </div>

            <!-- Right: Recent incident list feed -->
            <div class="box">
                <h3><i class="fas fa-history"></i> Recent Facility Incidents</h3>
                <div style="display:flex; flex-direction:column; gap:10px; max-height:280px; overflow-y:auto;">
                    <?php if (empty($recentIncidents)): ?>
                        <div style="color: #64748b; font-size: 13px;">No recent facility incidents.</div>
                    <?php else: ?>
                        <?php foreach ($recentIncidents as $inc): ?>
                            <div class="log-item">
                                <div>
                                    <div style="font-weight:600; font-size:13px; color:#2c3e50;"><?php echo htmlspecialchars($inc['facility_name']); ?></div>
                                    <div style="font-size:11px; color:#64748b; margin-top:2px;">Type: <?php echo htmlspecialchars($inc['incident_type']); ?> · Description: <?php echo htmlspecialchars($inc['description']); ?></div>
                                </div>
                                <span style="font-size:10px; color:#94a3b8; font-style:italic; white-space:nowrap;"><?php echo date('M d', strtotime($inc['created_at'])); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</main>

<script>
    // Facility Type Chart.js
    const typeCtx = document.getElementById('typeChart').getContext('2d');
    new Chart(typeCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo $typesJson; ?>,
            datasets: [{
                data: <?php echo $typeCountsJson; ?>,
                backgroundColor: ['#3498db', '#9b59b6', '#f1c40f', '#e74c3c', '#2ecc71', '#95a5a6'],
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
    const map = L.map('map').setView([14.5995, 120.9842], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    const facilities = <?php echo json_encode($mapFacilities); ?>;
    facilities.forEach(f => {
        let pinColor = '#2ecc71';
        if (f.utility_status === 'Partially Ready') pinColor = '#f1c40f';
        else if (f.utility_status === 'Not Ready') pinColor = '#e74c3c';

        const customIcon = L.divIcon({
            html: `<div style="background-color: ${pinColor}; width: 14px; height: 14px; border-radius: 50%; border: 2px solid white; box-shadow: 0 0 5px rgba(0,0,0,0.5);"></div>`,
            className: 'custom-div-icon',
            iconSize: [14, 14]
        });

        L.marker([parseFloat(f.latitude), parseFloat(f.longitude)], { icon: customIcon })
            .bindPopup(`
                <div style="font-family:'Poppins'; width:180px;">
                    <div style="font-weight:700; font-size:12px; color:#2c3e50;">${f.name}</div>
                    <div style="font-size:10px; color:#64748b; font-style:italic;">${f.location}</div>
                    <p style="font-size:11px; margin-top:5px;">${f.description || ''}</p>
                    <div style="font-size:10px; font-weight:600; margin-top:5px;">Status: ${f.utility_status}</div>
                </div>
            `).addTo(map);
    });
</script>

</body>
</html>
