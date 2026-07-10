<?php
// assets_map.php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$userType = $_SESSION['user_type'] ?? '';

// Fetch all geolocated assets
$query = "
    SELECT a.*, t.name as type_name, img.image_path
    FROM utility_assets a 
    JOIN asset_types t ON a.asset_type_id = t.id 
    LEFT JOIN (
        SELECT utility_asset_id, MAX(image_path) as image_path 
        FROM asset_images 
        GROUP BY utility_asset_id
    ) img ON a.id = img.utility_asset_id
    WHERE a.latitude IS NOT NULL AND a.longitude IS NOT NULL
";
$assets = $pdo->query($query)->fetchAll();
$assetsJson = json_encode($assets);

// Retrieve all types and statuses for filters
$assetTypes = $pdo->query("SELECT * FROM asset_types ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Location Map</title>
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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
            display: flex;
            flex-direction: column;
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
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
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

        .btn-outline { background: transparent; border: 1px solid #cbd5e1; color: #64748b; }
        .btn-outline:hover { background: #f8f9fa; color: #2c3e50; }

        /* Map controls panel */
        .map-panel {
            background: white;
            padding: 15px 25px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-group label {
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
        }

        .filter-select {
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 13px;
            outline: none;
        }

        /* Map Container */
        #map {
            width: 100%;
            height: 550px;
            border-radius: 12px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0,0,0,0.05);
            z-index: 10;
        }

        /* Leaflet popup styling */
        .leaflet-popup-content-wrapper {
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            padding: 5px;
        }

        .popup-container {
            font-family: 'Poppins', sans-serif;
            width: 220px;
        }

        .popup-title {
            font-size: 14px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .popup-type {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            color: #3762c8;
            margin-bottom: 8px;
        }

        .popup-desc {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 8px;
            line-height: 1.4;
        }

        .popup-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 99px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .badge-operational { background: #e2fbe8; color: #1e7e34; }
        .badge-inspection { background: #fef9e7; color: #d39e00; }
        .badge-damaged { background: #fde8e8; color: #bd2130; }
        .badge-maintenance { background: #f3e5f5; color: #7b1fa2; }

        .popup-img {
            width: 100%;
            height: 90px;
            object-fit: cover;
            border-radius: 6px;
            margin-top: 5px;
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
                <h1><i class="fas fa-map-marked-alt"></i> Asset Location Map</h1>
                <p style="color: #64748b; font-size: 14px; margin-top: 5px;">Interactive geolocations of all utility assets monitored by the LGU.</p>
            </div>
            <div>
                <a href="assets_dashboard.php" class="btn btn-outline"><i class="fas fa-chevron-left"></i> Dashboard</a>
            </div>
        </div>

        <!-- Filter Panel -->
        <div class="map-panel">
            <div class="filter-group">
                <label>Filter Category</label>
                <select id="typeFilter" class="filter-select" onchange="filterMap()">
                    <option value="all">All Categories</option>
                    <?php foreach ($assetTypes as $type): ?>
                        <option value="<?php echo htmlspecialchars($type['name']); ?>"><?php echo htmlspecialchars($type['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label>Filter Status</label>
                <select id="statusFilter" class="filter-select" onchange="filterMap()">
                    <option value="all">All Statuses</option>
                    <option value="Operational">Operational</option>
                    <option value="Needs Inspection">Needs Inspection</option>
                    <option value="Damaged">Damaged</option>
                    <option value="Under Maintenance">Under Maintenance</option>
                </select>
            </div>

            <div style="font-size:12px; color:#64748b; margin-left:auto;">
                Showing <strong id="markerCount">0</strong> geolocated assets on the map.
            </div>
        </div>

        <!-- Interactive Map -->
        <div id="map"></div>

    </div>
</main>

<script>
    // Initial geolocated assets data from PHP
    const assets = <?php echo $assetsJson; ?>;
    
    // Initialize map centering around Manila area (default LGU scope)
    const map = L.map('map').setView([14.5995, 120.9842], 13);

    // Load OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    // Layer group to manage markers dynamically
    const markerLayer = L.layerGroup().addTo(map);

    // Marker styling templates by condition status
    function getMarkerColor(status) {
        switch(status) {
            case 'Operational': return '#2ecc71';
            case 'Needs Inspection': return '#f1c40f';
            case 'Damaged': return '#e74c3c';
            case 'Under Maintenance': return '#9b59b6';
            default: return '#3498db';
        }
    }

    // Function to render markers based on filters
    function filterMap() {
        const selectedType = document.getElementById('typeFilter').value;
        const selectedStatus = document.getElementById('statusFilter').value;

        // Clear existing markers
        markerLayer.clearLayers();
        let count = 0;

        assets.forEach(asset => {
            const matchType = (selectedType === 'all' || asset.type_name === selectedType);
            const matchStatus = (selectedStatus === 'all' || asset.condition_status === selectedStatus);

            if (matchType && matchStatus) {
                count++;
                
                // Create SVG icon marker
                const color = getMarkerColor(asset.condition_status);
                const customIcon = L.divIcon({
                    html: `<div style="background-color: ${color}; width: 14px; height: 14px; border-radius: 50%; border: 2px solid white; box-shadow: 0 0 5px rgba(0,0,0,0.5);"></div>`,
                    className: 'custom-div-icon',
                    iconSize: [14, 14]
                });

                // Create popup HTML
                const badgeClass = asset.condition_status.toLowerCase().replace(' ', '');
                let popupHtml = `
                    <div class="popup-container">
                        <div class="popup-title">${asset.name}</div>
                        <div class="popup-type">${asset.type_name} (${asset.asset_id})</div>
                        <span class="popup-badge badge-${badgeClass}">${asset.condition_status}</span>
                        <div class="popup-desc">${asset.description || 'No additional notes.'}</div>
                        <div class="popup-desc" style="font-weight: 500;"><i class="fas fa-map-marker-alt"></i> ${asset.location}</div>
                `;

                if (asset.image_path) {
                    popupHtml += `<img class="popup-img" src="${asset.image_path}" alt="Asset Image">`;
                }

                popupHtml += `</div>`;

                // Add marker
                L.marker([parseFloat(asset.latitude), parseFloat(asset.longitude)], { icon: customIcon })
                    .bindPopup(popupHtml)
                    .addTo(markerLayer);
            }
        });

        document.getElementById('markerCount').textContent = count;
    }

    // Initial render
    filterMap();
</script>

</body>
</html>
