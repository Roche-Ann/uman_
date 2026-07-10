<?php
// citizen_facilities.php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$search = trim($_GET['search'] ?? '');
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(f.name LIKE ? OR f.location LIKE ?)";
    $searchWildcard = '%' . $search . '%';
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Retrieve facilities overlaid with indicator checks
$facilitiesList = [];
try {
    $query = "
        SELECT f.*, s.water_available, s.electricity_available, s.drainage_ok, s.lighting_ok
        FROM public_facilities f 
        JOIN facility_utility_status s ON f.id = s.public_facility_id 
        $whereClause
        ORDER BY f.name ASC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $facilitiesList = $stmt->fetchAll();
} catch (Exception $e) {
    // Fallback
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Facility Utility Index</title>
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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
            padding: 11px 22px;
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

        /* Search Panel */
        .search-panel {
            background: white;
            padding: 15px 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            align-items: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .form-control { padding: 10px 14px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 14px; outline: none; flex-grow: 1; }

        /* Layout Grid */
        .split-layout {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 30px;
        }

        @media (max-width: 1000px) {
            .split-layout { grid-template-columns: 1fr; }
        }

        .box {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }

        #map {
            width: 100%;
            height: 480px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
        }

        /* Facility Cards */
        .facility-item {
            padding: 15px;
            border-radius: 8px;
            background: #f8fafc;
            border: 1px solid #edf2f7;
            margin-bottom: 15px;
        }

        .facility-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .facility-title { font-size: 15px; font-weight: 700; color: #2c3e50; }
        .facility-location { font-size: 11px; color: #64748b; margin-top: 2px; }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 99px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-fullyready { background: #e2fbe8; color: #1e7e34; }
        .badge-partiallyready { background: #fff4e5; color: #b45309; }
        .badge-notready { background: #fde8e8; color: #bd2130; }

        /* Indicators Checklist */
        .indicators-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-top: 12px;
            background: white;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #edf2f7;
        }
        .indicator-item { font-size: 11px; font-weight: 500; display: flex; align-items: center; gap: 6px; }
        .indicator-item.ok { color: #1e7e34; }
        .indicator-item.fail { color: #bd2130; }
    </style>
</head>
<body>

<?php include 'includes/utilities_sidebar.php'; ?>

<main class="main-content" id="mainContent">
    <div class="card">
        
        <!-- Header -->
        <div class="dashboard-header">
            <div>
                <h1><i class="fas fa-warehouse"></i> Public Venue Utility Status</h1>
                <p style="color: #64748b; font-size: 14px; margin-top: 5px;">Read-only index of LGU facility utility availability alerts and readiness ratings.</p>
            </div>
            <div>
                <a href="citizen.php" class="btn btn-outline"><i class="fas fa-chevron-left"></i> Home</a>
            </div>
        </div>

        <!-- Search Bar -->
        <form method="GET" class="search-panel">
            <input type="text" name="search" class="form-control" placeholder="Search by venue name or location..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
            <a href="citizen_facilities.php" class="btn btn-outline">Clear</a>
        </form>

        <div class="split-layout">
            
            <!-- Left: Facility List -->
            <div class="box">
                <div style="max-height: 480px; overflow-y: auto; padding-right: 5px;">
                    <?php if (empty($facilitiesList)): ?>
                        <p style="color:#64748b; font-size:13px;">No public facilities found.</p>
                    <?php else: ?>
                        <?php foreach ($facilitiesList as $fac): 
                            $badgeClass = strtolower(str_replace(' ', '', $fac['utility_status']));
                        ?>
                            <div class="facility-item">
                                <div class="facility-header">
                                    <div>
                                        <div class="facility-title"><?php echo htmlspecialchars($fac['name']); ?></div>
                                        <div class="facility-location"><?php echo htmlspecialchars($fac['facility_type'] . ' · '.$fac['location']); ?></div>
                                    </div>
                                    <span class="badge badge-<?php echo $badgeClass; ?>"><?php echo htmlspecialchars($fac['utility_status']); ?></span>
                                </div>

                                <p style="font-size:12px; color:#64748b; margin-top:5px;"><?php echo htmlspecialchars($fac['description']); ?></p>

                                <div class="indicators-grid">
                                    <div class="indicator-item <?php echo $fac['water_available'] ? 'ok' : 'fail'; ?>">
                                        <i class="fas <?php echo $fac['water_available'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i> Water Supply
                                    </div>
                                    <div class="indicator-item <?php echo $fac['electricity_available'] ? 'ok' : 'fail'; ?>">
                                        <i class="fas <?php echo $fac['electricity_available'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i> Electricity
                                    </div>
                                    <div class="indicator-item <?php echo $fac['drainage_ok'] ? 'ok' : 'fail'; ?>">
                                        <i class="fas <?php echo $fac['drainage_ok'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i> Drainage Status
                                    </div>
                                    <div class="indicator-item <?php echo $fac['lighting_ok'] ? 'ok' : 'fail'; ?>">
                                        <i class="fas <?php echo $fac['lighting_ok'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i> Lighting System
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right: Pinpoint Map -->
            <div class="box">
                <div id="map"></div>
            </div>

        </div>

    </div>
</main>

<script>
    // Initialize map
    const map = L.map('map').setView([14.5995, 120.9842], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    const facilities = <?php echo json_encode($facilitiesList); ?>;
    facilities.forEach(f => {
        if (f.latitude && f.longitude) {
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
                    <div style="font-family:'Poppins'; width:160px;">
                        <div style="font-weight:700; font-size:11px; color:#2c3e50;">${f.name}</div>
                        <div style="font-size:9px; color:#64748b; font-style:italic;">${f.location}</div>
                        <div style="font-size:10px; font-weight:600; margin-top:5px;">Readiness: ${f.utility_status}</div>
                    </div>
                `).addTo(map);
        }
    });
</script>

</body>
</html>
