<?php
// waste_citizen_map.php — Citizen: GPS-filtered route view + complaint submission
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'] ?? null;

// Fetch citizen's barangay from profile (fallback if GPS denied)
$citizenBarangay = '';
try {
    $row = $pdo->prepare("SELECT barangay FROM users WHERE id = ?");
    $row->execute([$userId]);
    $citizenBarangay = $row->fetchColumn() ?: '';
} catch (Throwable $e) {}

// Fetch all routes + stops (we'll filter client-side by GPS)
$routes = [];
try {
    $routes = $pdo->query("SELECT * FROM waste_routes WHERE is_active = 1 ORDER BY id")->fetchAll();
} catch (Throwable $e) {}

$routeStops = [];
try {
    $rows = $pdo->query("
        SELECT s.*, r.route_name, r.color_hex, r.start_time, r.district, r.coverage
        FROM waste_route_stops s
        JOIN waste_routes r ON s.route_id = r.id
        ORDER BY s.route_id, s.stop_order
    ")->fetchAll();
    foreach ($rows as $row) {
        $routeStops[$row['route_id']][] = $row;
    }
} catch (Throwable $e) {}

// Fetch citizen's own complaints
$myComplaints = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM waste_complaints
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$userId]);
    $myComplaints = $stmt->fetchAll();
} catch (Throwable $e) {}

$routesJson    = json_encode(array_values($routes));
$stopsJson     = json_encode($routeStops);
$complaintsJson = json_encode($myComplaints);
$fallbackBarangay = json_encode($citizenBarangay);
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
    <title>My Waste Collection Route — UMAN_</title>
    <meta name="description" content="View your barangay's garbage collection route and estimated arrival times. Report missed collections and illegal dumping.">
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Poppins',sans-serif}
        body{min-height:100vh;display:flex;background:url("assets/images/cityhall.jpeg") center/cover no-repeat fixed;position:relative}
        body::before{content:"";position:fixed;inset:0;backdrop-filter:blur(6px);background:rgba(0,0,0,0.35);z-index:0}
        .main-content{flex:1;margin-left:280px;padding:28px 36px;transition:margin-left .25s;z-index:1;position:relative;display:flex;flex-direction:column}
        .main-content.collapsed{margin-left:90px}
        .card{background:rgba(255,255,255,0.92);backdrop-filter:blur(18px);border-radius:20px;padding:32px;color:#1e293b;box-shadow:0 8px 32px rgba(0,0,0,0.18);border:1px solid rgba(255,255,255,0.3);flex:1;display:flex;flex-direction:column;gap:20px}

        /* Header */
        .page-header{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px}
        .page-header h1{font-size:24px;font-weight:700;color:#15803d;display:flex;align-items:center;gap:12px}
        .page-header p{color:#64748b;font-size:13px;margin-top:4px}

        /* Route info card */
        .route-info-card{background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1px solid #bbf7d0;border-radius:16px;padding:18px 22px;display:flex;align-items:center;gap:16px}
        .route-info-card.loading{background:linear-gradient(135deg,#f8fafc,#f1f5f9);border-color:#e2e8f0}
        .route-dot{width:16px;height:16px;border-radius:50%;border:3px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,0.2);flex-shrink:0}
        .route-info-text h3{font-size:15px;font-weight:700;color:#1e293b}
        .route-info-text p{font-size:12px;color:#64748b;margin-top:2px}

        /* Buttons */
        .btn{padding:10px 20px;border-radius:10px;font-weight:600;font-size:13px;border:none;cursor:pointer;transition:all .25s;display:inline-flex;align-items:center;gap:8px;text-decoration:none}
        .btn-green{background:linear-gradient(135deg,#16a34a,#15803d);color:#fff;box-shadow:0 3px 10px rgba(22,163,74,.3)}
        .btn-green:hover{transform:translateY(-1px);box-shadow:0 5px 15px rgba(22,163,74,.4)}
        .btn-red{background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;box-shadow:0 3px 10px rgba(220,38,38,.3)}
        .btn-orange{background:linear-gradient(135deg,#ea580c,#c2410c);color:#fff;box-shadow:0 3px 10px rgba(234,88,12,.3)}
        .btn-outline{background:#fff;border:1px solid #e2e8f0;color:#64748b}
        .btn-outline:hover{background:#f8fafc}
        .btn-sm{padding:7px 14px;font-size:12px}

        /* Complaint buttons row */
        .report-actions{display:flex;gap:10px;flex-wrap:wrap}

        /* Map */
        #map{width:100%;height:500px;border-radius:16px;box-shadow:0 6px 24px rgba(0,0,0,0.12);border:1px solid rgba(0,0,0,0.06);z-index:10}

        /* GPS prompt banner */
        .gps-banner{background:linear-gradient(135deg,#eff6ff,#dbeafe);border:1px solid #bfdbfe;border-radius:12px;padding:14px 18px;display:flex;align-items:center;gap:12px;font-size:13px;color:#1e40af}
        .gps-banner i{font-size:18px;color:#2563eb}

        /* Complaint Modal */
        .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:2000;align-items:center;justify-content:center;padding:20px}
        .modal-overlay.show{display:flex}
        .modal{background:#fff;border-radius:20px;width:100%;max-width:520px;box-shadow:0 20px 60px rgba(0,0,0,0.25);overflow:hidden;animation:modalIn .25s ease}
        @keyframes modalIn{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
        .modal-header{padding:20px 24px 16px;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center}
        .modal-header h2{font-size:17px;font-weight:700;color:#1e293b;display:flex;align-items:center;gap:10px}
        .modal-close{background:none;border:none;font-size:20px;color:#94a3b8;cursor:pointer;line-height:1;padding:4px}
        .modal-close:hover{color:#374151}
        .modal-body{padding:20px 24px}
        .form-group{margin-bottom:16px}
        .form-group label{display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:6px}
        .form-control{width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:10px;font-size:13px;font-family:'Poppins',sans-serif;outline:none;transition:border-color .2s;background:#fff}
        .form-control:focus{border-color:#16a34a;box-shadow:0 0 0 3px rgba(22,163,74,.1)}
        .form-control.error{border-color:#dc2626}
        textarea.form-control{resize:vertical;min-height:80px}

        /* Type selector */
        .type-selector{display:grid;grid-template-columns:1fr 1fr;gap:10px}
        .type-card{border:2px solid #e2e8f0;border-radius:12px;padding:14px;cursor:pointer;text-align:center;transition:all .2s}
        .type-card:hover{border-color:#16a34a;background:#f0fdf4}
        .type-card.active.missed{border-color:#dc2626;background:#fef2f2}
        .type-card.active.illegal{border-color:#ea580c;background:#fff7ed}
        .type-card .type-icon{font-size:24px;margin-bottom:6px}
        .type-card .type-name{font-size:12px;font-weight:700;color:#374151}
        .type-card.active.missed .type-name{color:#dc2626}
        .type-card.active.illegal .type-name{color:#ea580c}

        /* Pin map (mini) */
        #pinMap{width:100%;height:200px;border-radius:12px;border:1px solid #e2e8f0;margin-top:6px}

        /* Photo upload */
        .photo-upload-area{border:2px dashed #e2e8f0;border-radius:12px;padding:20px;text-align:center;cursor:pointer;transition:all .2s;position:relative}
        .photo-upload-area:hover,.photo-upload-area.drag{border-color:#16a34a;background:#f0fdf4}
        .photo-upload-area input{position:absolute;inset:0;opacity:0;cursor:pointer}
        .photo-preview{display:none;margin-top:10px;position:relative}
        .photo-preview img{width:100%;height:120px;object-fit:cover;border-radius:8px}
        .photo-remove{position:absolute;top:6px;right:6px;background:#dc2626;color:#fff;border:none;border-radius:50%;width:24px;height:24px;cursor:pointer;font-size:12px;display:flex;align-items:center;justify-content:center}

        /* Modal footer */
        .modal-footer{padding:16px 24px;background:#f8fafc;border-top:1px solid #f1f5f9;display:flex;gap:10px;justify-content:flex-end}

        /* My complaints list */
        .my-complaints{display:flex;flex-direction:column;gap:10px}
        .complaint-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px 16px;display:flex;gap:12px;align-items:flex-start}
        .cc-icon{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:14px}
        .cc-icon.missed{background:#fef2f2;color:#dc2626}
        .cc-icon.illegal{background:#fff7ed;color:#ea580c}
        .cc-title{font-size:13px;font-weight:600;color:#1e293b}
        .cc-meta{font-size:11px;color:#94a3b8;margin-top:2px}
        .cc-status{display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700}
        .cc-status.pending{background:#fef9e7;color:#d97706}
        .cc-status.review{background:#eff6ff;color:#2563eb}
        .cc-status.resolved{background:#f0fdf4;color:#16a34a}

        /* Leaflet popup */
        .leaflet-popup-content-wrapper{border-radius:12px;box-shadow:0 6px 20px rgba(0,0,0,.15)}
        .leaflet-popup-content{margin:12px 16px;font-family:'Poppins',sans-serif;font-size:12px}

        /* Lightbox */
        .lightbox{display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:9999;align-items:center;justify-content:center;cursor:zoom-out}
        .lightbox.show{display:flex}
        .lightbox img{max-width:90vw;max-height:85vh;border-radius:12px}
        .lightbox-close{position:absolute;top:20px;right:24px;color:#fff;font-size:28px;cursor:pointer}

        @media(max-width:900px){.main-content{margin-left:0;padding:16px}#map{height:380px}}
    </style>
</head>
<body>

<?php include 'includes/citizen_sidebar.php'; ?>

<main class="main-content" id="mainContent">
<div class="card">

    <!-- Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-recycle"></i> My Waste Collection Route</h1>
            <p>See your garbage collection schedule, estimated arrival times, and report issues in your area.</p>
        </div>
        <a href="citizen.php" class="btn btn-outline btn-sm"><i class="fas fa-chevron-left"></i> Back</a>
    </div>

    <!-- GPS Banner -->
    <div class="gps-banner" id="gpsBanner">
        <i class="fas fa-location-arrow"></i>
        <span id="gpsStatus">Detecting your location to show your local collection route...</span>
    </div>

    <!-- Active Route Info -->
    <div class="route-info-card loading" id="routeInfoCard">
        <div class="route-dot" id="routeDot" style="background:#94a3b8"></div>
        <div class="route-info-text">
            <h3 id="routeName">Detecting route...</h3>
            <p id="routeCoverage">Please allow location access or update your profile barangay.</p>
        </div>
    </div>

    <!-- Report Buttons -->
    <div class="report-actions">
        <button class="btn btn-red" onclick="openComplaintModal('Missed Collection')">
            <i class="fas fa-truck"></i> Report Missed Collection
        </button>
        <button class="btn btn-orange" onclick="openComplaintModal('Illegal Dumping')">
            <i class="fas fa-dumpster-fire"></i> Report Illegal Dumping
        </button>
    </div>

    <!-- Map -->
    <div id="map"></div>

    <!-- My Complaints -->
    <?php if (!empty($myComplaints)): ?>
    <div>
        <h3 style="font-size:15px;font-weight:700;color:#1e293b;margin-bottom:12px">
            <i class="fas fa-history" style="color:#16a34a"></i> My Recent Reports
        </h3>
        <div class="my-complaints">
            <?php foreach (array_slice($myComplaints, 0, 5) as $c): ?>
            <?php
                $isMissed = $c['complaint_type'] === 'Missed Collection';
                $sClass   = $c['status'] === 'Resolved' ? 'resolved' : ($c['status'] === 'Under Review' ? 'review' : 'pending');
            ?>
            <div class="complaint-card">
                <div class="cc-icon <?= $isMissed ? 'missed' : 'illegal' ?>">
                    <i class="fas <?= $isMissed ? 'fa-truck' : 'fa-dumpster-fire' ?>"></i>
                </div>
                <div style="flex:1">
                    <div class="cc-title"><?= htmlspecialchars($c['complaint_type']) ?> — <?= htmlspecialchars($c['complaint_id']) ?></div>
                    <div class="cc-meta">
                        <?= htmlspecialchars($c['barangay'] ?: 'Location on map') ?> &bull;
                        <?= date('M j, Y', strtotime($c['created_at'])) ?>
                    </div>
                    <?php if ($c['description']): ?>
                    <div class="cc-meta" style="margin-top:4px;font-style:italic">"<?= htmlspecialchars(substr($c['description'],0,80)) ?>..."</div>
                    <?php endif; ?>
                </div>
                <span class="cc-status <?= $sClass ?>"><?= $c['status'] ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>
</main>

<!-- Complaint Modal -->
<div class="modal-overlay" id="complaintModal">
    <div class="modal">
        <div class="modal-header">
            <h2 id="modalTitle"><i class="fas fa-exclamation-triangle" style="color:#dc2626"></i> File a Complaint</h2>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="complaintForm" enctype="multipart/form-data">
                <input type="hidden" id="complaintType" name="complaint_type" value="">
                <input type="hidden" id="pinLat" name="latitude" value="">
                <input type="hidden" id="pinLng" name="longitude" value="">

                <!-- Type selector -->
                <div class="form-group">
                    <label>Complaint Type</label>
                    <div class="type-selector">
                        <div class="type-card" id="cardMissed" onclick="selectType('Missed Collection')">
                            <div class="type-icon">🚛</div>
                            <div class="type-name">Missed Collection</div>
                        </div>
                        <div class="type-card" id="cardIllegal" onclick="selectType('Illegal Dumping')">
                            <div class="type-icon">🗑️</div>
                            <div class="type-name">Illegal Dumping</div>
                        </div>
                    </div>
                </div>

                <!-- Location -->
                <div class="form-group">
                    <label><i class="fas fa-map-pin"></i> Location — Drag the pin to the exact spot</label>
                    <div id="pinMap"></div>
                    <div style="font-size:11px;color:#94a3b8;margin-top:5px">
                        <i class="fas fa-info-circle"></i> Pin placed at your GPS location. Drag it to adjust.
                    </div>
                </div>

                <!-- Address detail -->
                <div class="form-group">
                    <label>Specific Address / Landmark (optional)</label>
                    <input type="text" class="form-control" name="address_detail" id="addressDetail"
                           placeholder="e.g., Near the talipapa on Regalado Ave">
                </div>

                <!-- Description -->
                <div class="form-group">
                    <label>Description</label>
                    <textarea class="form-control" name="description" id="description"
                              placeholder="Describe the issue briefly..."></textarea>
                </div>

                <!-- Photo upload -->
                <div class="form-group">
                    <label>Photo (optional, max 5MB)</label>
                    <div class="photo-upload-area" id="uploadArea">
                        <input type="file" name="photo" id="photoInput" accept="image/*" onchange="previewPhoto(this)">
                        <i class="fas fa-camera" style="font-size:24px;color:#94a3b8;margin-bottom:8px"></i>
                        <div style="font-size:13px;color:#64748b;font-weight:500">Tap to take a photo or choose from gallery</div>
                        <div style="font-size:11px;color:#94a3b8;margin-top:4px">JPG, PNG, WEBP supported</div>
                    </div>
                    <div class="photo-preview" id="photoPreview">
                        <img id="photoThumb" src="" alt="Preview">
                        <button type="button" class="photo-remove" onclick="removePhoto()"><i class="fas fa-times"></i></button>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline btn-sm" onclick="closeModal()">Cancel</button>
            <button class="btn btn-green btn-sm" id="submitBtn" onclick="submitComplaint()">
                <i class="fas fa-paper-plane"></i> Submit Report
            </button>
        </div>
    </div>
</div>

<!-- Lightbox -->
<div class="lightbox" id="lightbox" onclick="closeLightbox()">
    <span class="lightbox-close">&times;</span>
    <img id="lightboxImg" src="" alt="Photo">
</div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
// ─── DATA ────────────────────────────────────────────────────
const STATIC_ROUTES = [
    { id:1, route_name:'Commonwealth–Batasan Corridor', color_hex:'#16a34a', district:'Batasan Hills District', coverage:'Commonwealth Ave, UP Diliman, Batasan Rd, Holy Spirit', start_time:'06:00:00' },
    { id:2, route_name:'Quezon Ave–Timog Circuit',      color_hex:'#2563eb', district:'Apolonio Samson District', coverage:'Quezon Ave, Timog Ave, Scout Area, EDSA', start_time:'06:30:00' },
    { id:3, route_name:'Balintawak–Fairview North',     color_hex:'#ea580c', district:'Novaliches District', coverage:'Mindanao Ave, Quirino Hwy, Fairview, Regalado Ave', start_time:'06:00:00' },
    { id:4, route_name:'Cubao–Aurora Boulevard East',   color_hex:'#dc2626', district:'Matandang Balara District', coverage:'EDSA Cubao, Aurora Blvd, Eastwood, Libis', start_time:'06:30:00' },
    { id:5, route_name:'Novaliches–Sauyo Loop',         color_hex:'#7c3aed', district:'San Bartolome District', coverage:'Novaliches Proper, Sauyo Rd, Lagro, Nova Market', start_time:'06:00:00' },
    { id:6, route_name:'Kamuning–Project 6 Circuit',    color_hex:'#ca8a04', district:'Commonwealth District', coverage:'EDSA Kamuning, Tomas Morato, Project 4, Project 6', start_time:'07:00:00' }
];

const STATIC_STOPS = {
    1: [
        { route_id:1, stop_order:1, barangay_name:'Batasan Hills',  latitude:14.6957, longitude:121.1050, travel_min:0,  service_min:10, waste_types:'Biodegradable, Non-biodegradable' },
        { route_id:1, stop_order:2, barangay_name:'Holy Spirit',    latitude:14.6889, longitude:121.0894, travel_min:18, service_min:12, waste_types:'Biodegradable, Non-biodegradable' },
        { route_id:1, stop_order:3, barangay_name:'Commonwealth',   latitude:14.6803, longitude:121.0756, travel_min:15, service_min:10, waste_types:'Mixed' },
        { route_id:1, stop_order:4, barangay_name:'UP Campus Area', latitude:14.6547, longitude:121.0644, travel_min:20, service_min:10, waste_types:'Recyclable, Mixed' },
        { route_id:1, stop_order:5, barangay_name:'Diliman',        latitude:14.6507, longitude:121.0695, travel_min:10, service_min:8,  waste_types:'Mixed' },
        { route_id:1, stop_order:6, barangay_name:'Loyola Heights', latitude:14.6432, longitude:121.0784, travel_min:12, service_min:8,  waste_types:'Mixed' }
    ],
    2: [
        { route_id:2, stop_order:1, barangay_name:'Quezon Avenue', latitude:14.6411, longitude:121.0153, travel_min:0,  service_min:10, waste_types:'Mixed' },
        { route_id:2, stop_order:2, barangay_name:'Sacred Heart',  latitude:14.6387, longitude:121.0203, travel_min:10, service_min:8,  waste_types:'Biodegradable' },
        { route_id:2, stop_order:3, barangay_name:'Timog Avenue',  latitude:14.6363, longitude:121.0308, travel_min:12, service_min:8,  waste_types:'Mixed' },
        { route_id:2, stop_order:4, barangay_name:'South Triangle',latitude:14.6339, longitude:121.0358, travel_min:8,  service_min:8,  waste_types:'Mixed' },
        { route_id:2, stop_order:5, barangay_name:'Scout Area',    latitude:14.6306, longitude:121.0408, travel_min:10, service_min:10, waste_types:'Recyclable' },
        { route_id:2, stop_order:6, barangay_name:'EDSA-Quezon',   latitude:14.6284, longitude:121.0506, travel_min:15, service_min:8,  waste_types:'Mixed' }
    ],
    3: [
        { route_id:3, stop_order:1, barangay_name:'Balintawak',     latitude:14.6567, longitude:120.9831, travel_min:0,  service_min:10, waste_types:'Mixed' },
        { route_id:3, stop_order:2, barangay_name:'Tandang Sora',   latitude:14.6695, longitude:121.0305, travel_min:20, service_min:12, waste_types:'Biodegradable' },
        { route_id:3, stop_order:3, barangay_name:'Fairview',       latitude:14.7211, longitude:121.0578, travel_min:25, service_min:15, waste_types:'Mixed' },
        { route_id:3, stop_order:4, barangay_name:'Greater Lagro',  latitude:14.7358, longitude:121.0506, travel_min:12, service_min:10, waste_types:'Mixed' },
        { route_id:3, stop_order:5, barangay_name:'Regalado',       latitude:14.7472, longitude:121.0428, travel_min:12, service_min:8,  waste_types:'Recyclable' },
        { route_id:3, stop_order:6, barangay_name:'North Fairview', latitude:14.7556, longitude:121.0436, travel_min:8,  service_min:8,  waste_types:'Mixed' }
    ],
    4: [
        { route_id:4, stop_order:1, barangay_name:'Cubao',      latitude:14.6195, longitude:121.0528, travel_min:0,  service_min:10, waste_types:'Mixed' },
        { route_id:4, stop_order:2, barangay_name:'New Manila', latitude:14.6211, longitude:121.0389, travel_min:10, service_min:8,  waste_types:'Biodegradable' },
        { route_id:4, stop_order:3, barangay_name:'Aurora Blvd',latitude:14.6183, longitude:121.0567, travel_min:8,  service_min:10, waste_types:'Mixed' },
        { route_id:4, stop_order:4, barangay_name:'Anonas',     latitude:14.6142, longitude:121.0628, travel_min:10, service_min:8,  waste_types:'Mixed' },
        { route_id:4, stop_order:5, barangay_name:'Libis',      latitude:14.5989, longitude:121.0700, travel_min:15, service_min:10, waste_types:'Recyclable' },
        { route_id:4, stop_order:6, barangay_name:'Eastwood',   latitude:14.6092, longitude:121.0789, travel_min:12, service_min:8,  waste_types:'Mixed' }
    ],
    5: [
        { route_id:5, stop_order:1, barangay_name:'Novaliches Proper', latitude:14.7272, longitude:121.0167, travel_min:0,  service_min:12, waste_types:'Mixed' },
        { route_id:5, stop_order:2, barangay_name:'Sauyo',             latitude:14.7028, longitude:121.0122, travel_min:18, service_min:10, waste_types:'Biodegradable' },
        { route_id:5, stop_order:3, barangay_name:'Lagro',             latitude:14.7172, longitude:121.0344, travel_min:15, service_min:10, waste_types:'Mixed' },
        { route_id:5, stop_order:4, barangay_name:'San Agustin',       latitude:14.7089, longitude:121.0278, travel_min:10, service_min:8,  waste_types:'Recyclable' },
        { route_id:5, stop_order:5, barangay_name:'Sta. Lucia',        latitude:14.6983, longitude:121.0211, travel_min:12, service_min:8,  waste_types:'Mixed' },
        { route_id:5, stop_order:6, barangay_name:'Novaliches Market', latitude:14.7233, longitude:121.0128, travel_min:15, service_min:10, waste_types:'Mixed' }
    ],
    6: [
        { route_id:6, stop_order:1, barangay_name:'Kamuning',     latitude:14.6331, longitude:121.0286, travel_min:0,  service_min:10, waste_types:'Mixed' },
        { route_id:6, stop_order:2, barangay_name:'Tomas Morato', latitude:14.6378, longitude:121.0347, travel_min:8,  service_min:8,  waste_types:'Biodegradable' },
        { route_id:6, stop_order:3, barangay_name:'Paligsahan',   latitude:14.6353, longitude:121.0197, travel_min:10, service_min:8,  waste_types:'Mixed' },
        { route_id:6, stop_order:4, barangay_name:'Project 4',    latitude:14.6283, longitude:121.0614, travel_min:20, service_min:10, waste_types:'Recyclable' },
        { route_id:6, stop_order:5, barangay_name:'Project 6',    latitude:14.6453, longitude:121.0131, travel_min:18, service_min:10, waste_types:'Mixed' },
        { route_id:6, stop_order:6, barangay_name:'Sto. Domingo', latitude:14.6406, longitude:121.0214, travel_min:10, service_min:8,  waste_types:'Mixed' }
    ]
};

const DB_ROUTES = <?= $routesJson ?>;
const DB_STOPS  = <?= $stopsJson ?>;

const ROUTES         = (DB_ROUTES && DB_ROUTES.length > 0) ? DB_ROUTES : STATIC_ROUTES;
const STOPS          = (DB_STOPS && Object.keys(DB_STOPS).length > 0) ? DB_STOPS : STATIC_STOPS;
const MY_COMPLAINTS  = <?= $complaintsJson ?>;
const FALLBACK_BRGAY = <?= $fallbackBarangay ?>;

// ─── STATE ───────────────────────────────────────────────────
let userLat = null, userLng = null;
let activeRouteId = null;
let pinMarker = null;
let pinMap = null;

// ─── MAIN MAP ────────────────────────────────────────────────
const map = L.map('map').setView([14.676, 121.044], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors', maxZoom: 19
}).addTo(map);

// ─── ETA CALC ────────────────────────────────────────────────
function calcETAs(routeId) {
    const route = ROUTES.find(r => r.id == routeId);
    if (!route || !STOPS[routeId]) return [];
    const [sh,sm] = (route.start_time || '06:00:00').split(':').map(Number);
    let totalMin = sh*60+sm;
    return STOPS[routeId].map((stop, i) => {
        if (i > 0) totalMin += parseInt(stop.travel_min) + parseInt(STOPS[routeId][i-1].service_min);
        const h=Math.floor(totalMin/60)%24, m=totalMin%60;
        const ampm=h>=12?'PM':'AM', h12=(h%12)||12;
        return {...stop, etaStr:`${h12}:${String(m).padStart(2,'0')} ${ampm}`};
    });
}

// ─── FIND NEAREST ROUTE ──────────────────────────────────────
function haversine(lat1,lng1,lat2,lng2) {
    const R=6371,dLat=(lat2-lat1)*Math.PI/180,dLng=(lng2-lng1)*Math.PI/180;
    const a=Math.sin(dLat/2)**2+Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLng/2)**2;
    return R*2*Math.atan2(Math.sqrt(a),Math.sqrt(1-a));
}

function findNearestRoute(lat, lng) {
    let nearest = null, minDist = Infinity;
    ROUTES.forEach(route => {
        const stops = STOPS[route.id];
        if (!stops) return;
        stops.forEach(stop => {
            const d = haversine(lat, lng, parseFloat(stop.latitude), parseFloat(stop.longitude));
            if (d < minDist) { minDist = d; nearest = route; }
        });
    });
    return nearest;
}

// ─── RENDER ACTIVE ROUTE (SNAPPED TO ROADS) ──────────────────
async function fetchRoadGeometry(stops) {
    try {
        const coordString = stops.map(s => `${parseFloat(s.longitude)},${parseFloat(s.latitude)}`).join(';');
        const url = `https://router.project-osrm.org/route/v1/driving/${coordString}?overview=full&geometries=geojson`;
        const res = await fetch(url);
        if (!res.ok) throw new Error('OSRM error');
        const data = await res.json();
        if (data.code === 'Ok' && data.routes && data.routes[0]) {
            return data.routes[0].geometry.coordinates.map(c => [c[1], c[0]]);
        }
    } catch (e) {
        console.warn('Fallback routing used:', e);
    }
    return stops.map(s => [parseFloat(s.latitude), parseFloat(s.longitude)]);
}

function renderActiveRoute(routeId) {
    activeRouteId = routeId;
    const route = ROUTES.find(r => r.id == routeId);
    if (!route || !STOPS[routeId]) return;

    const etaStops = calcETAs(routeId);
    const fallbackLatLngs  = etaStops.map(s => [parseFloat(s.latitude), parseFloat(s.longitude)]);

    // Draw initial polyline
    const poly = L.polyline(fallbackLatLngs, {
        color: route.color_hex,
        weight: 5,
        opacity: 0.85,
        lineJoin: 'round',
        lineCap: 'round'
    }).addTo(map);

    // Snap to road network
    fetchRoadGeometry(etaStops).then(roadLatLngs => {
        poly.setLatLngs(roadLatLngs);
        map.fitBounds(L.polyline(roadLatLngs).getBounds(), {padding:[40,40]});
    });

    // Draw stops
    etaStops.forEach((stop, i) => {
        const icon = L.divIcon({
            html: `<div style="
                background:${route.color_hex};color:#fff;
                width:${i===0?30:20}px;height:${i===0?30:20}px;
                border-radius:50%;border:3px solid #fff;
                box-shadow:0 2px 8px rgba(0,0,0,.3);
                display:flex;align-items:center;justify-content:center;
                font-size:${i===0?12:9}px;font-weight:700
            ">${i===0?'<i class="fas fa-truck" style="font-size:11px"></i>':stop.stop_order}</div>`,
            className:'',iconSize:i===0?[30,30]:[20,20],iconAnchor:i===0?[15,15]:[10,10]
        });
        L.marker([parseFloat(stop.latitude),parseFloat(stop.longitude)],{icon})
            .addTo(map)
            .bindPopup(`
                <strong>📍 ${stop.barangay_name}</strong><br>
                <span style="color:${route.color_hex};font-weight:600">⏰ ${stop.etaStr}</span><br>
                <small style="color:#64748b">${stop.waste_types||'Mixed Waste'}</small>
            `);

        // ETA label
        L.marker([parseFloat(stop.latitude),parseFloat(stop.longitude)],{
            icon: L.divIcon({
                html:`<div style="background:rgba(255,255,255,.95);border:1px solid ${route.color_hex};color:${route.color_hex};padding:2px 7px;border-radius:10px;font-size:10px;font-weight:600;white-space:nowrap;box-shadow:0 2px 6px rgba(0,0,0,.15);margin-top:-28px;margin-left:8px">${stop.etaStr}</div>`,
                className:'',iconSize:[65,20],iconAnchor:[0,10]
            }),interactive:false,zIndexOffset:-100
        }).addTo(map);
    });

    // Update route info card
    const card = document.getElementById('routeInfoCard');
    card.classList.remove('loading');
    card.style.background = `linear-gradient(135deg,${route.color_hex}15,${route.color_hex}25)`;
    card.style.borderColor = route.color_hex + '80';
    document.getElementById('routeDot').style.background = route.color_hex;
    document.getElementById('routeName').textContent = route.route_name;
    document.getElementById('routeCoverage').textContent = `${route.district} · ${route.coverage} · Starts at ${route.start_time?.substring(0,5)} AM`;

    // Update banner
    document.getElementById('gpsBanner').style.background = 'linear-gradient(135deg,#f0fdf4,#dcfce7)';
    document.getElementById('gpsBanner').style.borderColor = '#bbf7d0';
    document.getElementById('gpsBanner').style.color = '#15803d';
    document.getElementById('gpsBanner').querySelector('i').style.color = '#16a34a';

    map.fitBounds(L.polyline(fallbackLatLngs).getBounds(), {padding:[40,40]});
}


// ─── RENDER MY COMPLAINT PINS ────────────────────────────────
function renderMyComplaints() {
    MY_COMPLAINTS.forEach(c => {
        if (!c.latitude || !c.longitude) return;
        const isMissed = c.complaint_type === 'Missed Collection';
        const isResolved = c.status === 'Resolved';
        const color = isResolved ? '#16a34a' : (isMissed ? '#dc2626' : '#ea580c');
        const icon = L.divIcon({
            html:`<div style="background:${color};width:18px;height:18px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.3)"></div>`,
            className:'',iconSize:[18,24],iconAnchor:[9,24]
        });
        L.marker([parseFloat(c.latitude),parseFloat(c.longitude)],{icon})
            .addTo(map)
            .bindPopup(`
                <div style="font-family:'Poppins',sans-serif">
                    <strong style="color:${color}">${c.complaint_type}</strong><br>
                    <small style="color:#64748b">${new Date(c.created_at).toLocaleDateString('en-PH',{month:'short',day:'numeric'})}</small><br>
                    <span style="font-size:11px;font-weight:600">${c.status}</span>
                    ${c.photo_path?`<br><img src="${c.photo_path}" style="width:100%;height:80px;object-fit:cover;border-radius:6px;margin-top:6px;cursor:pointer" onclick="openLightbox('${c.photo_path}')">` : ''}
                </div>
            `);
    });
}

// ─── GPS DETECTION ───────────────────────────────────────────
function detectLocation() {
    if (!navigator.geolocation) {
        useFallback();
        return;
    }
    document.getElementById('gpsStatus').textContent = 'Requesting location access...';
    navigator.geolocation.getCurrentPosition(
        pos => {
            userLat = pos.coords.latitude;
            userLng = pos.coords.longitude;
            document.getElementById('gpsStatus').textContent = `Location detected! Showing your local collection route.`;

            // User location marker
            L.circleMarker([userLat,userLng],{radius:10,color:'#2563eb',fillColor:'#3b82f6',fillOpacity:.8,weight:3})
                .addTo(map)
                .bindPopup('<strong>📍 Your Location</strong>');

            const nearest = findNearestRoute(userLat, userLng);
            if (nearest) renderActiveRoute(nearest.id);
            else useFallback();
        },
        () => {
            document.getElementById('gpsStatus').textContent = 'Location access denied. Using your profile barangay as fallback.';
            useFallback();
        },
        { timeout: 10000, enableHighAccuracy: true }
    );
}

function useFallback() {
    if (!FALLBACK_BRGAY) {
        document.getElementById('gpsStatus').textContent = 'Could not detect location. Showing all routes. Please update your profile barangay.';
        ROUTES.forEach(r => renderActiveRoute(r.id));
        return;
    }
    // Match barangay to route
    const brgy = FALLBACK_BRGAY.toLowerCase();
    let matched = null;
    ROUTES.forEach(r => {
        if ((r.route_name+' '+r.district+' '+r.coverage).toLowerCase().includes(brgy.split(',')[0])) {
            matched = r;
        }
    });
    if (matched) renderActiveRoute(matched.id);
    else {
        document.getElementById('gpsStatus').textContent = 'Could not match your barangay to a route. Showing all routes.';
        ROUTES.forEach(r => renderActiveRoute(r.id));
    }
}

// ─── COMPLAINT MODAL ─────────────────────────────────────────
function openComplaintModal(type) {
    selectType(type);
    document.getElementById('complaintModal').classList.add('show');

    // Init pin map after modal opens
    setTimeout(() => {
        if (!pinMap) {
            const centerLat = userLat || 14.676;
            const centerLng = userLng || 121.044;
            pinMap = L.map('pinMap').setView([centerLat, centerLng], 16);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19}).addTo(pinMap);
            pinMarker = L.marker([centerLat, centerLng], { draggable: true }).addTo(pinMap);
            pinMarker.on('dragend', updatePinCoords);
            updatePinCoords();
        } else {
            if (userLat) {
                pinMarker.setLatLng([userLat, userLng]);
                pinMap.setView([userLat, userLng], 16);
                updatePinCoords();
            }
        }
        pinMap.invalidateSize();
    }, 200);
}

function updatePinCoords() {
    const ll = pinMarker.getLatLng();
    document.getElementById('pinLat').value = ll.lat.toFixed(7);
    document.getElementById('pinLng').value = ll.lng.toFixed(7);
}

function closeModal() {
    document.getElementById('complaintModal').classList.remove('show');
    document.getElementById('complaintForm').reset();
    document.getElementById('photoPreview').style.display = 'none';
}

function selectType(type) {
    document.getElementById('complaintType').value = type;
    const isMissed = type === 'Missed Collection';
    document.getElementById('cardMissed').className = 'type-card' + (isMissed ? ' active missed' : '');
    document.getElementById('cardIllegal').className = 'type-card' + (!isMissed ? ' active illegal' : '');
    const icon = isMissed ? 'fa-truck' : 'fa-dumpster-fire';
    const color = isMissed ? '#dc2626' : '#ea580c';
    document.getElementById('modalTitle').innerHTML = `<i class="fas ${icon}" style="color:${color}"></i> ${type}`;
}

// ─── PHOTO PREVIEW ───────────────────────────────────────────
function previewPhoto(input) {
    if (!input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('photoThumb').src = e.target.result;
        document.getElementById('photoPreview').style.display = 'block';
    };
    reader.readAsDataURL(input.files[0]);
}

function removePhoto() {
    document.getElementById('photoInput').value = '';
    document.getElementById('photoPreview').style.display = 'none';
}

// ─── SUBMIT COMPLAINT ────────────────────────────────────────
function submitComplaint() {
    const type = document.getElementById('complaintType').value;
    if (!type) { alert('Please select a complaint type.'); return; }
    if (!document.getElementById('pinLat').value) { alert('Please allow location access or move the pin.'); return; }

    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

    const formData = new FormData(document.getElementById('complaintForm'));
    formData.append('action', 'submit');

    fetch('api/waste-complaints.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closeModal();
            // Refresh page to show new complaint
            setTimeout(() => window.location.reload(), 500);
        } else {
            alert(data.message || 'Submission failed. Please try again.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Report';
        }
    })
    .catch(() => {
        alert('Network error. Please try again.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Report';
    });
}

// ─── LIGHTBOX ────────────────────────────────────────────────
function openLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightbox').classList.add('show');
}
function closeLightbox() { document.getElementById('lightbox').classList.remove('show'); }

// ─── INIT ────────────────────────────────────────────────────
detectLocation();
renderMyComplaints();
</script>
</body>
</html>
