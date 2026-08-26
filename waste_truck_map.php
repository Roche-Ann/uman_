<?php
// waste_truck_map.php — Admin: Full QC Route Map + Citizen Complaint Pins
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$userType = $_SESSION['user_type'] ?? 'employee';

// Fetch routes + stops
$routes = [];
try {
    $routes = $pdo->query("SELECT * FROM waste_routes WHERE is_active = 1 ORDER BY id")->fetchAll();
} catch (Throwable $e) {}

$routeStops = [];
try {
    $rows = $pdo->query("
        SELECT s.*, r.route_name, r.color_hex
        FROM waste_route_stops s
        JOIN waste_routes r ON s.route_id = r.id
        ORDER BY s.route_id, s.stop_order
    ")->fetchAll();
    foreach ($rows as $row) {
        $routeStops[$row['route_id']][] = $row;
    }
} catch (Throwable $e) {}

// Fetch complaints (with photo)
$complaints = [];
try {
    $complaints = $pdo->query("
        SELECT c.*, u.full_name as reporter_name
        FROM waste_complaints c
        LEFT JOIN users u ON c.user_id = u.id
        WHERE c.latitude IS NOT NULL AND c.longitude IS NOT NULL
        ORDER BY c.created_at DESC
    ")->fetchAll();
} catch (Throwable $e) {}

// Stats
$stats = ['open_complaints' => 0, 'active_routes' => 0, 'active_trucks' => 0, 'monthly_collections' => 0];
try {
    $stats = $pdo->query("SELECT * FROM aggregated_waste_view")->fetch() ?: $stats;
} catch (Throwable $e) {}

$routesJson    = json_encode(array_values($routes));
$stopsJson     = json_encode($routeStops);
$complaintsJson = json_encode($complaints);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>
        (function() {
            const t = localStorage.getItem('theme') || 'light';
            if (t === 'dark') document.documentElement.classList.add('dark-theme');
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Waste Route Map — UMAN_</title>
    <meta name="description" content="Interactive Quezon City garbage collection route map with ETA stops and citizen complaint tracking.">
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Poppins',sans-serif}
        body{min-height:100vh;display:flex;background:url("assets/images/cityhall.jpeg") center/cover no-repeat fixed;position:relative}
        body::before{content:"";position:fixed;inset:0;backdrop-filter:blur(6px);background:rgba(0,0,0,0.35);z-index:0}
        .main-content{flex:1;margin-left:280px;padding:28px 36px;transition:margin-left .25s;z-index:1;position:relative;display:flex;flex-direction:column}
        .main-content.collapsed{margin-left:90px}

        /* Card */
        .card{background:rgba(255,255,255,0.92);backdrop-filter:blur(18px);border-radius:20px;padding:32px;color:#1e293b;box-shadow:0 8px 32px rgba(0,0,0,0.18);border:1px solid rgba(255,255,255,0.3);flex:1;display:flex;flex-direction:column;gap:20px}

        /* Header */
        .page-header{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px}
        .page-header h1{font-size:26px;font-weight:700;color:#15803d;display:flex;align-items:center;gap:12px}
        .page-header h1 i{background:linear-gradient(135deg,#16a34a,#15803d);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
        .page-header p{color:#64748b;font-size:13px;margin-top:4px}
        .header-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}

        /* Stats Bar */
        .stats-bar{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
        .stat-card{background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1px solid #bbf7d0;border-radius:14px;padding:16px 20px;display:flex;align-items:center;gap:14px}
        .stat-card.orange{background:linear-gradient(135deg,#fff7ed,#ffedd5);border-color:#fed7aa}
        .stat-card.blue{background:linear-gradient(135deg,#eff6ff,#dbeafe);border-color:#bfdbfe}
        .stat-card.purple{background:linear-gradient(135deg,#faf5ff,#ede9fe);border-color:#ddd6fe}
        .stat-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;background:#16a34a;color:#fff;flex-shrink:0}
        .stat-card.orange .stat-icon{background:#ea580c}
        .stat-card.blue .stat-icon{background:#2563eb}
        .stat-card.purple .stat-icon{background:#7c3aed}
        .stat-value{font-size:22px;font-weight:700;color:#1e293b;line-height:1}
        .stat-label{font-size:11px;color:#64748b;font-weight:500;margin-top:3px}

        /* Controls Panel */
        .controls-panel{display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap}
        .legend-box{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:16px 20px;min-width:220px}
        .legend-box h3{font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px}
        .legend-item{display:flex;align-items:center;gap:10px;margin-bottom:8px;cursor:pointer;user-select:none;transition:opacity .2s}
        .legend-item:hover{opacity:.8}
        .legend-swatch{width:28px;height:6px;border-radius:3px;flex-shrink:0}
        .legend-label{font-size:12px;font-weight:500;color:#374151;flex:1}
        .legend-toggle{width:18px;height:18px;border:2px solid #cbd5e1;border-radius:4px;display:flex;align-items:center;justify-content:center;transition:all .2s;flex-shrink:0}
        .legend-toggle.active{background:#16a34a;border-color:#16a34a;color:#fff}
        .legend-divider{border:none;border-top:1px solid #f1f5f9;margin:10px 0}

        /* Complaint pins legend */
        .pin-legend{display:flex;flex-direction:column;gap:6px}
        .pin-item{display:flex;align-items:center;gap:8px;font-size:12px;color:#374151}
        .pin-dot{width:12px;height:12px;border-radius:50%;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,0.3)}

        /* Filter controls */
        .filter-controls{display:flex;gap:10px;flex:1;align-items:center;flex-wrap:wrap}
        .filter-group{display:flex;align-items:center;gap:8px}
        .filter-group label{font-size:12px;font-weight:600;color:#64748b;white-space:nowrap}
        .filter-select,.filter-input{padding:8px 12px;border-radius:9px;border:1px solid #e2e8f0;font-size:12px;font-family:'Poppins',sans-serif;background:#fff;outline:none;transition:border-color .2s}
        .filter-select:focus,.filter-input:focus{border-color:#16a34a}

        /* Complaint count badge */
        .complaint-badge{display:inline-flex;align-items:center;gap:6px;background:#fef2f2;border:1px solid #fecaca;color:#dc2626;padding:6px 12px;border-radius:20px;font-size:12px;font-weight:600}
        .complaint-badge .pulse-dot{width:8px;height:8px;border-radius:50%;background:#dc2626;animation:pulse 1.5s infinite}
        @keyframes pulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.4);opacity:.7}}

        /* Buttons */
        .btn{padding:9px 18px;border-radius:9px;font-weight:600;font-size:13px;border:none;cursor:pointer;transition:all .25s;display:inline-flex;align-items:center;gap:7px;text-decoration:none}
        .btn-green{background:linear-gradient(135deg,#16a34a,#15803d);color:#fff;box-shadow:0 3px 10px rgba(22,163,74,.3)}
        .btn-green:hover{transform:translateY(-1px);box-shadow:0 5px 15px rgba(22,163,74,.4)}
        .btn-outline{background:#fff;border:1px solid #e2e8f0;color:#64748b}
        .btn-outline:hover{background:#f8fafc;color:#374151}
        .btn-sm{padding:6px 14px;font-size:12px}

        /* Map */
        #map{width:100%;height:580px;border-radius:16px;box-shadow:0 6px 24px rgba(0,0,0,0.12);border:1px solid rgba(0,0,0,0.06);z-index:10;flex-shrink:0}

        /* Leaflet customizations */
        .leaflet-popup-content-wrapper{border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,0.15);padding:0;overflow:hidden}
        .leaflet-popup-content{margin:0;width:260px!important}
        .leaflet-popup-tip-container{margin-top:-1px}

        /* ETA Popup */
        .stop-popup{padding:14px 16px}
        .stop-popup .route-badge{display:inline-block;padding:2px 10px;border-radius:20px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;color:#fff}
        .stop-popup .stop-name{font-size:15px;font-weight:700;color:#1e293b;margin-bottom:4px}
        .stop-popup .eta-row{display:flex;align-items:center;gap:6px;font-size:13px;color:#16a34a;font-weight:600;margin-bottom:8px}
        .stop-popup .meta-row{font-size:11px;color:#64748b;display:flex;align-items:center;gap:5px;margin-top:3px}

        /* Complaint Popup */
        .complaint-popup{font-family:'Poppins',sans-serif}
        .complaint-popup .cp-header{padding:12px 16px;display:flex;align-items:center;gap:10px}
        .complaint-popup .cp-header.missed{background:linear-gradient(135deg,#fef2f2,#fee2e2)}
        .complaint-popup .cp-header.illegal{background:linear-gradient(135deg,#fff7ed,#ffedd5)}
        .complaint-popup .cp-type{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px}
        .complaint-popup .cp-type.missed{color:#dc2626}
        .complaint-popup .cp-type.illegal{color:#ea580c}
        .complaint-popup .cp-body{padding:12px 16px}
        .complaint-popup .cp-row{display:flex;gap:8px;margin-bottom:8px;font-size:12px;color:#374151;align-items:flex-start}
        .complaint-popup .cp-row i{color:#94a3b8;margin-top:2px;width:14px;flex-shrink:0}
        .complaint-popup .cp-status{display:inline-block;padding:2px 10px;border-radius:20px;font-size:10px;font-weight:700}
        .complaint-popup .cp-status.pending{background:#fef9e7;color:#d97706}
        .complaint-popup .cp-status.review{background:#eff6ff;color:#2563eb}
        .complaint-popup .cp-status.resolved{background:#f0fdf4;color:#16a34a}
        .complaint-popup .cp-photo{width:100%;height:120px;object-fit:cover;display:block;cursor:pointer;transition:opacity .2s}
        .complaint-popup .cp-photo:hover{opacity:.88}
        .complaint-popup .cp-actions{display:flex;gap:8px;padding:10px 16px;background:#f8fafc;border-top:1px solid #f1f5f9}
        .complaint-popup .cp-btn{flex:1;padding:7px;border-radius:8px;font-size:11px;font-weight:600;border:none;cursor:pointer;transition:all .2s;font-family:'Poppins',sans-serif}
        .complaint-popup .cp-btn.resolve{background:#16a34a;color:#fff}
        .complaint-popup .cp-btn.resolve:hover{background:#15803d}
        .complaint-popup .cp-btn.dismiss{background:#f1f5f9;color:#64748b}
        .complaint-popup .cp-btn.dismiss:hover{background:#e2e8f0}

        /* Photo Lightbox */
        .lightbox{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:9999;align-items:center;justify-content:center;cursor:zoom-out}
        .lightbox.show{display:flex}
        .lightbox img{max-width:90vw;max-height:85vh;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,0.5)}
        .lightbox-close{position:absolute;top:20px;right:24px;color:#fff;font-size:28px;cursor:pointer;line-height:1;opacity:.8;transition:opacity .2s}
        .lightbox-close:hover{opacity:1}

        /* Dark theme */
        .dark-theme .card{background:rgba(15,23,42,0.92);color:#e2e8f0}
        .dark-theme .page-header h1{color:#4ade80}
        .dark-theme .legend-box,.dark-theme .filter-select,.dark-theme .filter-input{background:#1e293b;border-color:#334155;color:#e2e8f0}
        .dark-theme .stat-card{background:linear-gradient(135deg,#14532d,#166534);border-color:#16a34a}
        .dark-theme .stat-card.orange{background:linear-gradient(135deg,#431407,#7c2d12);border-color:#c2410c}
        .dark-theme .stat-card.blue{background:linear-gradient(135deg,#172554,#1e3a8a);border-color:#1d4ed8}
        .dark-theme .stat-card.purple{background:linear-gradient(135deg,#2e1065,#4c1d95);border-color:#6d28d9}
        .dark-theme .stat-value,.dark-theme .legend-label{color:#f1f5f9}
        .dark-theme .btn-outline{background:#1e293b;border-color:#334155;color:#94a3b8}

        @media(max-width:900px){
            .main-content{margin-left:0;padding:16px}
            .stats-bar{grid-template-columns:repeat(2,1fr)}
            .controls-panel{flex-direction:column}
            #map{height:420px}
        }
    </style>
</head>
<body>

<?php include 'includes/utilities_sidebar.php'; ?>

<main class="main-content" id="mainContent">
<div class="card">

    <!-- Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-map-marked-alt"></i> Waste Collection Route Map</h1>
            <p>Quezon City garbage truck routes with estimated arrival times and citizen complaint tracking.</p>
        </div>
        <div class="header-actions">
            <span class="complaint-badge" id="complaintBadge">
                <span class="pulse-dot"></span>
                <span id="openComplaintCount"><?= $stats['open_complaints'] ?></span> Open Complaints
            </span>
            <a href="waste_dashboard.php" class="btn btn-outline btn-sm"><i class="fas fa-th-large"></i> Dashboard</a>
            <a href="waste_records.php" class="btn btn-green btn-sm"><i class="fas fa-clipboard-list"></i> Records</a>
        </div>
    </div>

    <!-- Stats Bar -->
    <div class="stats-bar">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-route"></i></div>
            <div>
                <div class="stat-value"><?= $stats['active_routes'] ?></div>
                <div class="stat-label">Active Routes</div>
            </div>
        </div>
        <div class="stat-card blue">
            <div class="stat-icon"><i class="fas fa-truck"></i></div>
            <div>
                <div class="stat-value"><?= $stats['active_trucks'] ?></div>
                <div class="stat-label">Active Trucks</div>
            </div>
        </div>
        <div class="stat-card orange">
            <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div>
                <div class="stat-value"><?= $stats['open_complaints'] ?></div>
                <div class="stat-label">Open Complaints</div>
            </div>
        </div>
        <div class="stat-card purple">
            <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
            <div>
                <div class="stat-value"><?= $stats['monthly_collections'] ?></div>
                <div class="stat-label">Collections This Month</div>
            </div>
        </div>
    </div>

    <!-- Controls Panel -->
    <div class="controls-panel">
        <!-- Route Legend -->
        <div class="legend-box">
            <h3><i class="fas fa-layer-group"></i> Routes</h3>
            <?php
            $staticRoutes = [
                ['id'=>1,'route_name'=>'Commonwealth–Batasan','color_hex'=>'#16a34a'],
                ['id'=>2,'route_name'=>'Quezon Ave–Timog',     'color_hex'=>'#2563eb'],
                ['id'=>3,'route_name'=>'Balintawak–Fairview',  'color_hex'=>'#ea580c'],
                ['id'=>4,'route_name'=>'Cubao–Aurora East',    'color_hex'=>'#dc2626'],
                ['id'=>5,'route_name'=>'Novaliches–Sauyo',     'color_hex'=>'#7c3aed'],
                ['id'=>6,'route_name'=>'Kamuning–Project 6',   'color_hex'=>'#ca8a04'],
            ];
            // Merge DB routes if present, otherwise use static list
            $legendRoutes = !empty($routes) ? $routes : $staticRoutes;
            foreach ($legendRoutes as $route): ?>
            <div class="legend-item" onclick="toggleRoute(<?= $route['id'] ?>, this)" data-route="<?= $route['id'] ?>">
                <div class="legend-swatch" style="background:<?= htmlspecialchars($route['color_hex']) ?>"></div>
                <span class="legend-label" style="font-size:11px"><?= htmlspecialchars($route['route_name']) ?></span>
                <div class="legend-toggle active" id="toggle-<?= $route['id'] ?>"><i class="fas fa-check" style="font-size:9px"></i></div>
            </div>
            <?php endforeach; ?>

            <hr class="legend-divider">
            <h3 style="margin-bottom:10px"><i class="fas fa-map-pin"></i> Complaints</h3>
            <div class="pin-legend">
                <div class="pin-item"><div class="pin-dot" style="background:#dc2626"></div> Missed Collection</div>
                <div class="pin-item"><div class="pin-dot" style="background:#ea580c"></div> Illegal Dumping</div>
                <div class="pin-item"><div class="pin-dot" style="background:#16a34a"></div> Resolved</div>
            </div>
        </div>


        <!-- Filters -->
        <div style="display:flex;flex-direction:column;gap:10px;flex:1">
            <div class="filter-controls">
                <div class="filter-group">
                    <label>Complaints</label>
                    <select id="complaintFilter" class="filter-select" onchange="renderComplaints()">
                        <option value="all">All Complaints</option>
                        <option value="open">Open Only</option>
                        <option value="resolved">Resolved Only</option>
                        <option value="missed">Missed Collection</option>
                        <option value="illegal">Illegal Dumping</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>ETA Labels</label>
                    <select id="etaFilter" class="filter-select" onchange="toggleETALabels()">
                        <option value="show">Show ETAs</option>
                        <option value="hide">Hide ETAs</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Date</label>
                    <input type="date" id="complaintDate" class="filter-input" onchange="renderComplaints()" value="">
                </div>
            </div>

            <!-- Map -->
            <div id="map"></div>
        </div>
    </div>

</div>
</main>

<!-- Photo Lightbox -->
<div class="lightbox" id="lightbox" onclick="closeLightbox()">
    <span class="lightbox-close" onclick="closeLightbox()">&times;</span>
    <img id="lightboxImg" src="" alt="Complaint Photo">
</div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
// ─── HARDCODED FALLBACK: 6 QC Garbage Collection Routes ──────
// Used when DB is empty; DB data overlays this when available.
const STATIC_ROUTES = [
    { id:1, route_name:'Commonwealth–Batasan Corridor', color_hex:'#16a34a', district:'Batasan Hills District', coverage:'Commonwealth Ave, UP Diliman, Batasan Rd, Holy Spirit', start_time:'06:00:00' },
    { id:2, route_name:'Quezon Ave–Timog Circuit',      color_hex:'#2563eb', district:'Apolonio Samson District', coverage:'Quezon Ave, Timog Ave, Scout Area, EDSA', start_time:'06:30:00' },
    { id:3, route_name:'Balintawak–Fairview North',     color_hex:'#ea580c', district:'Novaliches District', coverage:'Mindanao Ave, Quirino Hwy, Fairview, Regalado Ave', start_time:'06:00:00' },
    { id:4, route_name:'Cubao–Aurora Boulevard East',   color_hex:'#dc2626', district:'Matandang Balara District', coverage:'EDSA Cubao, Aurora Blvd, Eastwood, Libis', start_time:'06:30:00' },
    { id:5, route_name:'Novaliches–Sauyo Loop',         color_hex:'#7c3aed', district:'San Bartolome District', coverage:'Novaliches Proper, Sauyo Rd, Lagro, Nova Market', start_time:'06:00:00' },
    { id:6, route_name:'Kamuning–Project 6 Circuit',    color_hex:'#ca8a04', district:'Commonwealth District', coverage:'EDSA Kamuning, Tomas Morato, Project 4, Project 6', start_time:'07:00:00' }
];

const STATIC_STOPS = {
    1: [ // Commonwealth–Batasan
        { route_id:1, stop_order:1, barangay_name:'Batasan Hills',  latitude:14.6957, longitude:121.1050, travel_min:0,  service_min:10, waste_types:'Biodegradable, Non-biodegradable' },
        { route_id:1, stop_order:2, barangay_name:'Holy Spirit',    latitude:14.6889, longitude:121.0894, travel_min:18, service_min:12, waste_types:'Biodegradable, Non-biodegradable' },
        { route_id:1, stop_order:3, barangay_name:'Commonwealth',   latitude:14.6803, longitude:121.0756, travel_min:15, service_min:10, waste_types:'Mixed' },
        { route_id:1, stop_order:4, barangay_name:'UP Campus Area', latitude:14.6547, longitude:121.0644, travel_min:20, service_min:10, waste_types:'Recyclable, Mixed' },
        { route_id:1, stop_order:5, barangay_name:'Diliman',        latitude:14.6507, longitude:121.0695, travel_min:10, service_min:8,  waste_types:'Mixed' },
        { route_id:1, stop_order:6, barangay_name:'Loyola Heights', latitude:14.6432, longitude:121.0784, travel_min:12, service_min:8,  waste_types:'Mixed' }
    ],
    2: [ // Quezon Ave–Timog
        { route_id:2, stop_order:1, barangay_name:'Quezon Avenue', latitude:14.6411, longitude:121.0153, travel_min:0,  service_min:10, waste_types:'Mixed' },
        { route_id:2, stop_order:2, barangay_name:'Sacred Heart',  latitude:14.6387, longitude:121.0203, travel_min:10, service_min:8,  waste_types:'Biodegradable' },
        { route_id:2, stop_order:3, barangay_name:'Timog Avenue',  latitude:14.6363, longitude:121.0308, travel_min:12, service_min:8,  waste_types:'Mixed' },
        { route_id:2, stop_order:4, barangay_name:'South Triangle',latitude:14.6339, longitude:121.0358, travel_min:8,  service_min:8,  waste_types:'Mixed' },
        { route_id:2, stop_order:5, barangay_name:'Scout Area',    latitude:14.6306, longitude:121.0408, travel_min:10, service_min:10, waste_types:'Recyclable' },
        { route_id:2, stop_order:6, barangay_name:'EDSA-Quezon',   latitude:14.6284, longitude:121.0506, travel_min:15, service_min:8,  waste_types:'Mixed' }
    ],
    3: [ // Balintawak–Fairview
        { route_id:3, stop_order:1, barangay_name:'Balintawak',     latitude:14.6567, longitude:120.9831, travel_min:0,  service_min:10, waste_types:'Mixed' },
        { route_id:3, stop_order:2, barangay_name:'Tandang Sora',   latitude:14.6695, longitude:121.0305, travel_min:20, service_min:12, waste_types:'Biodegradable' },
        { route_id:3, stop_order:3, barangay_name:'Fairview',       latitude:14.7211, longitude:121.0578, travel_min:25, service_min:15, waste_types:'Mixed' },
        { route_id:3, stop_order:4, barangay_name:'Greater Lagro',  latitude:14.7358, longitude:121.0506, travel_min:12, service_min:10, waste_types:'Mixed' },
        { route_id:3, stop_order:5, barangay_name:'Regalado',       latitude:14.7472, longitude:121.0428, travel_min:12, service_min:8,  waste_types:'Recyclable' },
        { route_id:3, stop_order:6, barangay_name:'North Fairview', latitude:14.7556, longitude:121.0436, travel_min:8,  service_min:8,  waste_types:'Mixed' }
    ],
    4: [ // Cubao–Aurora East
        { route_id:4, stop_order:1, barangay_name:'Cubao',      latitude:14.6195, longitude:121.0528, travel_min:0,  service_min:10, waste_types:'Mixed' },
        { route_id:4, stop_order:2, barangay_name:'New Manila', latitude:14.6211, longitude:121.0389, travel_min:10, service_min:8,  waste_types:'Biodegradable' },
        { route_id:4, stop_order:3, barangay_name:'Aurora Blvd',latitude:14.6183, longitude:121.0567, travel_min:8,  service_min:10, waste_types:'Mixed' },
        { route_id:4, stop_order:4, barangay_name:'Anonas',     latitude:14.6142, longitude:121.0628, travel_min:10, service_min:8,  waste_types:'Mixed' },
        { route_id:4, stop_order:5, barangay_name:'Libis',      latitude:14.5989, longitude:121.0700, travel_min:15, service_min:10, waste_types:'Recyclable' },
        { route_id:4, stop_order:6, barangay_name:'Eastwood',   latitude:14.6092, longitude:121.0789, travel_min:12, service_min:8,  waste_types:'Mixed' }
    ],
    5: [ // Novaliches–Sauyo
        { route_id:5, stop_order:1, barangay_name:'Novaliches Proper', latitude:14.7272, longitude:121.0167, travel_min:0,  service_min:12, waste_types:'Mixed' },
        { route_id:5, stop_order:2, barangay_name:'Sauyo',             latitude:14.7028, longitude:121.0122, travel_min:18, service_min:10, waste_types:'Biodegradable' },
        { route_id:5, stop_order:3, barangay_name:'Lagro',             latitude:14.7172, longitude:121.0344, travel_min:15, service_min:10, waste_types:'Mixed' },
        { route_id:5, stop_order:4, barangay_name:'San Agustin',       latitude:14.7089, longitude:121.0278, travel_min:10, service_min:8,  waste_types:'Recyclable' },
        { route_id:5, stop_order:5, barangay_name:'Sta. Lucia',        latitude:14.6983, longitude:121.0211, travel_min:12, service_min:8,  waste_types:'Mixed' },
        { route_id:5, stop_order:6, barangay_name:'Novaliches Market', latitude:14.7233, longitude:121.0128, travel_min:15, service_min:10, waste_types:'Mixed' }
    ],
    6: [ // Kamuning–Project 6
        { route_id:6, stop_order:1, barangay_name:'Kamuning',     latitude:14.6331, longitude:121.0286, travel_min:0,  service_min:10, waste_types:'Mixed' },
        { route_id:6, stop_order:2, barangay_name:'Tomas Morato', latitude:14.6378, longitude:121.0347, travel_min:8,  service_min:8,  waste_types:'Biodegradable' },
        { route_id:6, stop_order:3, barangay_name:'Paligsahan',   latitude:14.6353, longitude:121.0197, travel_min:10, service_min:8,  waste_types:'Mixed' },
        { route_id:6, stop_order:4, barangay_name:'Project 4',    latitude:14.6283, longitude:121.0614, travel_min:20, service_min:10, waste_types:'Recyclable' },
        { route_id:6, stop_order:5, barangay_name:'Project 6',    latitude:14.6453, longitude:121.0131, travel_min:18, service_min:10, waste_types:'Mixed' },
        { route_id:6, stop_order:6, barangay_name:'Sto. Domingo', latitude:14.6406, longitude:121.0214, travel_min:10, service_min:8,  waste_types:'Mixed' }
    ]
};

// ─── MERGE DB DATA OVER STATIC FALLBACK ─────────────────────
// If DB returned routes, use those; otherwise fall back to static
const DB_ROUTES = <?= $routesJson ?>;
const DB_STOPS  = <?= $stopsJson ?>;

const ROUTES = (DB_ROUTES && DB_ROUTES.length > 0) ? DB_ROUTES : STATIC_ROUTES;
const STOPS  = (DB_STOPS  && Object.keys(DB_STOPS).length > 0) ? DB_STOPS : STATIC_STOPS;
const COMPLAINTS = <?= $complaintsJson ?>;

// ─── MAP INIT ────────────────────────────────────────────────
const map = L.map('map', { zoomControl: true }).setView([14.676, 121.044], 13);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://openstreetmap.org">OpenStreetMap</a> contributors',
    maxZoom: 19
}).addTo(map);

// ─── STATE ───────────────────────────────────────────────────
const routeLayers    = {};  // route_id → { polyline, stops[] }
const complaintLayer = L.layerGroup().addTo(map);
const etaLabels      = {};  // stop id → label marker
let showETAs         = true;


// ─── ETA CALCULATOR ──────────────────────────────────────────
function calcETAs(routeId) {
    const route = ROUTES.find(r => r.id == routeId);
    if (!route || !STOPS[routeId]) return [];
    const stops = STOPS[routeId];
    let totalMin = 0;
    const [sh, sm] = route.start_time.split(':').map(Number);
    const startMin = sh * 60 + sm;

    return stops.map((stop, i) => {
        if (i > 0) totalMin += (parseInt(stop.travel_min) + parseInt(stops[i-1].service_min));
        const eta = startMin + totalMin;
        const h = Math.floor(eta / 60) % 24;
        const m = eta % 60;
        const ampm = h >= 12 ? 'PM' : 'AM';
        const h12 = ((h % 12) || 12);
        return {
            ...stop,
            etaStr: `${h12}:${String(m).padStart(2,'0')} ${ampm}`
        };
    });
}

// ─── STOP POPUP HTML ─────────────────────────────────────────
function stopPopupHTML(stop, route) {
    return `
    <div class="stop-popup">
        <span class="route-badge" style="background:${route.color_hex}">${route.route_name}</span>
        <div class="stop-name">📍 ${stop.barangay_name}</div>
        <div class="eta-row"><i class="fas fa-clock"></i> Est. Arrival: ${stop.etaStr}</div>
        <div class="meta-row"><i class="fas fa-recycle"></i> ${stop.waste_types || 'Mixed Waste'}</div>
        <div class="meta-row"><i class="fas fa-list-ol"></i> Stop ${stop.stop_order} of ${STOPS[stop.route_id]?.length}</div>
    </div>`;
}

// ─── RENDER ROUTES (SNAPPED TO REAL ROADS) ───────────────────
const roadGeometryCache = {};

async function fetchRoadGeometry(stops) {
    const cacheKey = stops.map(s => `${s.latitude},${s.longitude}`).join(';');
    if (roadGeometryCache[cacheKey]) {
        return roadGeometryCache[cacheKey];
    }
    
    try {
        const coordString = stops.map(s => `${parseFloat(s.longitude)},${parseFloat(s.latitude)}`).join(';');
        const url = `https://router.project-osrm.org/route/v1/driving/${coordString}?overview=full&geometries=geojson`;
        const res = await fetch(url);
        if (!res.ok) throw new Error('OSRM network error');
        const data = await res.json();
        
        if (data.code === 'Ok' && data.routes && data.routes[0]) {
            const coords = data.routes[0].geometry.coordinates; // [lng, lat]
            const roadLatLngs = coords.map(c => [c[1], c[0]]); // convert to [lat, lng]
            roadGeometryCache[cacheKey] = roadLatLngs;
            return roadLatLngs;
        }
    } catch (e) {
        console.warn('Road snapping fallback to straight line:', e);
    }
    return stops.map(s => [parseFloat(s.latitude), parseFloat(s.longitude)]);
}

function renderRoutes() {
    ROUTES.forEach(async (route) => {
        const stops = STOPS[route.id];
        if (!stops || stops.length === 0) return;

        const etaStops = calcETAs(route.id);
        const fallbackLatLngs = etaStops.map(s => [parseFloat(s.latitude), parseFloat(s.longitude)]);

        // Polyline with smooth road styling
        const poly = L.polyline(fallbackLatLngs, {
            color: route.color_hex,
            weight: 5,
            opacity: 0.85,
            lineJoin: 'round',
            lineCap: 'round',
            smoothFactor: 1
        }).addTo(map);

        // Fetch actual road network coordinates and update polyline
        fetchRoadGeometry(etaStops).then(roadLatLngs => {
            poly.setLatLngs(roadLatLngs);
        });

        // Stop markers
        const stopMarkers = [];
        etaStops.forEach((stop, i) => {
            const isFirst = i === 0;
            const icon = L.divIcon({
                html: `<div style="
                    background:${route.color_hex};
                    color:#fff;
                    width:${isFirst ? 32 : 22}px;
                    height:${isFirst ? 32 : 22}px;
                    border-radius:50%;
                    border:3px solid #fff;
                    box-shadow:0 2px 8px rgba(0,0,0,0.3);
                    display:flex;align-items:center;justify-content:center;
                    font-size:${isFirst ? 13 : 9}px;
                    font-weight:700;
                    font-family:'Poppins',sans-serif;
                ">${isFirst ? '<i class="fas fa-truck" style="font-size:12px"></i>' : stop.stop_order}</div>`,
                className: '',
                iconSize: isFirst ? [32,32] : [22,22],
                iconAnchor: isFirst ? [16,16] : [11,11]
            });

            const marker = L.marker([parseFloat(stop.latitude), parseFloat(stop.longitude)], { icon })
                .addTo(map)
                .bindPopup(stopPopupHTML(stop, route), { maxWidth: 260, className: '' });

            stopMarkers.push(marker);

            // ETA floating label
            if (showETAs) {
                const label = L.marker([parseFloat(stop.latitude), parseFloat(stop.longitude)], {
                    icon: L.divIcon({
                        html: `<div style="
                            background:rgba(255,255,255,0.95);
                            border:1px solid ${route.color_hex};
                            color:${route.color_hex};
                            padding:2px 7px;
                            border-radius:10px;
                            font-size:10px;
                            font-weight:600;
                            font-family:'Poppins',sans-serif;
                            white-space:nowrap;
                            box-shadow:0 2px 6px rgba(0,0,0,0.15);
                            margin-top:${isFirst ? -36 : -28}px;
                            margin-left:8px;
                        ">${stop.etaStr}</div>`,
                        className: '',
                        iconSize: [70, 20],
                        iconAnchor: [0, 10]
                    }),
                    interactive: false,
                    zIndexOffset: -100
                }).addTo(map);
                etaLabels[`${route.id}-${stop.stop_order}`] = label;
            }
        });

        routeLayers[route.id] = { poly, stopMarkers };
    });
}


// ─── TOGGLE ROUTE ────────────────────────────────────────────
function toggleRoute(routeId, el) {
    const layer = routeLayers[routeId];
    if (!layer) return;
    const toggle = document.getElementById(`toggle-${routeId}`);
    const isActive = toggle.classList.contains('active');
    if (isActive) {
        map.removeLayer(layer.poly);
        layer.stopMarkers.forEach(m => map.removeLayer(m));
        Object.keys(etaLabels).filter(k => k.startsWith(`${routeId}-`)).forEach(k => map.removeLayer(etaLabels[k]));
        toggle.classList.remove('active');
        toggle.innerHTML = '';
    } else {
        layer.poly.addTo(map);
        layer.stopMarkers.forEach(m => m.addTo(map));
        if (showETAs) Object.keys(etaLabels).filter(k => k.startsWith(`${routeId}-`)).forEach(k => etaLabels[k].addTo(map));
        toggle.classList.add('active');
        toggle.innerHTML = '<i class="fas fa-check" style="font-size:9px"></i>';
    }
}

// ─── TOGGLE ETA LABELS ───────────────────────────────────────
function toggleETALabels() {
    showETAs = document.getElementById('etaFilter').value === 'show';
    Object.values(etaLabels).forEach(lbl => {
        if (showETAs) lbl.addTo(map); else map.removeLayer(lbl);
    });
}

// ─── COMPLAINT PINS ──────────────────────────────────────────
function getComplaintIcon(complaint) {
    const isResolved = complaint.status === 'Resolved';
    const isMissed   = complaint.complaint_type === 'Missed Collection';
    const color      = isResolved ? '#16a34a' : (isMissed ? '#dc2626' : '#ea580c');
    return L.divIcon({
        html: `<div style="position:relative">
            <div style="
                background:${color};
                width:20px;height:20px;
                border-radius:50% 50% 50% 0;
                transform:rotate(-45deg);
                border:2px solid #fff;
                box-shadow:0 2px 8px rgba(0,0,0,0.3);
                ${!isResolved ? 'animation:pinPulse 2s infinite;' : ''}
            "></div>
        </div>`,
        className: '',
        iconSize: [20, 26],
        iconAnchor: [10, 26]
    });
}

function complaintPopupHTML(c) {
    const isMissed   = c.complaint_type === 'Missed Collection';
    const isResolved = c.status === 'Resolved';
    const typeClass  = isMissed ? 'missed' : 'illegal';
    const statusClass = c.status === 'Pending' ? 'pending' : (c.status === 'Resolved' ? 'resolved' : 'review');
    const icon       = isMissed ? 'fa-truck' : 'fa-dumpster-fire';
    const d          = new Date(c.created_at);
    const dateStr    = d.toLocaleDateString('en-PH', {month:'short',day:'numeric',year:'numeric'}) + ' · ' +
                       d.toLocaleTimeString('en-PH', {hour:'numeric',minute:'2-digit',hour12:true});
    return `
    <div class="complaint-popup">
        <div class="cp-header ${typeClass}">
            <div style="width:34px;height:34px;border-radius:10px;background:${isMissed?'#fecaca':'#fed7aa'};display:flex;align-items:center;justify-content:center">
                <i class="fas ${icon}" style="color:${isMissed?'#dc2626':'#ea580c'};font-size:14px"></i>
            </div>
            <div>
                <div class="cp-type ${typeClass}">${c.complaint_type}</div>
                <span class="cp-status ${statusClass}">${c.status}</span>
            </div>
        </div>
        ${c.photo_path ? `<img src="${c.photo_path}" class="cp-photo" alt="Complaint Photo" onclick="openLightbox('${c.photo_path}')">` : ''}
        <div class="cp-body">
            <div class="cp-row"><i class="fas fa-user"></i><span>${c.reporter_name || 'Anonymous'}</span></div>
            <div class="cp-row"><i class="fas fa-map-marker-alt"></i><span>${c.address_detail || c.barangay || 'Location on map'}</span></div>
            <div class="cp-row"><i class="fas fa-clock"></i><span>${dateStr}</span></div>
            ${c.description ? `<div class="cp-row"><i class="fas fa-comment"></i><span style="font-style:italic">"${c.description}"</span></div>` : ''}
        </div>
        ${!isResolved ? `
        <div class="cp-actions">
            <button class="cp-btn resolve" onclick="resolveComplaint(${c.id}, this)"><i class="fas fa-check"></i> Mark Resolved</button>
            <button class="cp-btn dismiss" onclick="dismissComplaint(${c.id}, this)"><i class="fas fa-times"></i> Dismiss</button>
        </div>` : `<div class="cp-actions" style="justify-content:center"><span style="font-size:11px;color:#16a34a;font-weight:600"><i class="fas fa-check-circle"></i> Resolved</span></div>`}
    </div>`;
}

function renderComplaints() {
    complaintLayer.clearLayers();
    const filter    = document.getElementById('complaintFilter').value;
    const dateInput = document.getElementById('complaintDate').value;

    COMPLAINTS.forEach(c => {
        if (!c.latitude || !c.longitude) return;
        const isOpen     = ['Pending','Under Review'].includes(c.status);
        const isResolved = c.status === 'Resolved';
        const isMissed   = c.complaint_type === 'Missed Collection';

        if (filter === 'open'     && !isOpen)     return;
        if (filter === 'resolved' && !isResolved)  return;
        if (filter === 'missed'   && !isMissed)    return;
        if (filter === 'illegal'  &&  isMissed)    return;
        if (dateInput) {
            const cd = c.created_at.substring(0,10);
            if (cd !== dateInput) return;
        }

        L.marker([parseFloat(c.latitude), parseFloat(c.longitude)], { icon: getComplaintIcon(c) })
            .bindPopup(complaintPopupHTML(c), { maxWidth: 280, className: '' })
            .addTo(complaintLayer);
    });
}

// ─── RESOLVE / DISMISS ───────────────────────────────────────
function resolveComplaint(id, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Resolving...';
    fetch('api/waste-complaints.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ action:'resolve', id })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const c = COMPLAINTS.find(x => x.id == id);
            if (c) c.status = 'Resolved';
            renderComplaints();
            map.closePopup();
            updateComplaintBadge();
        }
    })
    .catch(() => { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check"></i> Mark Resolved'; });
}

function dismissComplaint(id, btn) {
    if (!confirm('Dismiss this complaint?')) return;
    fetch('api/waste-complaints.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ action:'dismiss', id })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const idx = COMPLAINTS.findIndex(x => x.id == id);
            if (idx > -1) COMPLAINTS.splice(idx, 1);
            renderComplaints();
            map.closePopup();
            updateComplaintBadge();
        }
    });
}

function updateComplaintBadge() {
    const open = COMPLAINTS.filter(c => ['Pending','Under Review'].includes(c.status)).length;
    document.getElementById('openComplaintCount').textContent = open;
}

// ─── LIGHTBOX ────────────────────────────────────────────────
function openLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightbox').classList.add('show');
}
function closeLightbox() {
    document.getElementById('lightbox').classList.remove('show');
}

// ─── CSS ANIMATION ───────────────────────────────────────────
const style = document.createElement('style');
style.textContent = `
@keyframes pinPulse {
    0%,100%{filter:drop-shadow(0 0 0 rgba(220,38,38,0))}
    50%{filter:drop-shadow(0 0 6px rgba(220,38,38,0.7))}
}`;
document.head.appendChild(style);

// ─── INIT ────────────────────────────────────────────────────
renderRoutes();
renderComplaints();

// Sidebar collapse sync
const sidebar = document.getElementById('sidebar');
const mainContent = document.getElementById('mainContent');
if (sidebar) {
    const observer = new MutationObserver(() => {
        mainContent.classList.toggle('collapsed', sidebar.classList.contains('collapsed'));
    });
    observer.observe(sidebar, { attributes: true, attributeFilter: ['class'] });
}
</script>

</body>
</html>
