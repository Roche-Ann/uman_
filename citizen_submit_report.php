<?php
// citizen_submit_report.php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'] ?? 3;
$userName = $_SESSION['user_name'] ?? $_SESSION['full_name'] ?? 'Citizen';

$error = '';
$success = '';

// Handle Submit Report
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = intval($_POST['category_id'] ?? 0);
    $asset_id = intval($_POST['asset_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;

    if (empty($description) || empty($location) || $category_id <= 0) {
        $error = 'Please fill in all required fields (Category, Location, and Description).';
    } else {
        try {
            // Generate unique Incident ID
            $prefix = 'INC-' . date('Ym') . '-';
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM utility_incidents WHERE incident_id LIKE ?");
            $stmt->execute([$prefix . '%']);
            $count = $stmt->fetchColumn() + 1;
            $incident_id = $prefix . str_pad($count, 4, '0', STR_PAD_LEFT);

            // Insert Incident
            $stmt = $pdo->prepare("
                INSERT INTO utility_incidents (incident_id, category_id, description, location, latitude, longitude, status, priority, resident_id)
                VALUES (?, ?, ?, ?, ?, ?, 'Submitted', 'Medium', ?)
            ");
            $stmt->execute([$incident_id, $category_id, $description, $location, $latitude, $longitude, $userId]);
            $uiid = $pdo->lastInsertId();

            // Link to asset if selected
            if ($asset_id > 0) {
                $pdo->prepare("INSERT INTO incident_asset_links (utility_incident_id, utility_asset_id) VALUES (?, ?)")->execute([$uiid, $asset_id]);
            }

            // Image Upload Handling
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $targetDir = 'uploads/incidents/';
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                $fileExtension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                if (in_array($fileExtension, $allowedExtensions)) {
                    $fileName = uniqid('inc_', true) . '.' . $fileExtension;
                    $targetFilePath = $targetDir . $fileName;
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFilePath)) {
                        $pdo->prepare("INSERT INTO incident_images (utility_incident_id, image_path) VALUES (?, ?)")->execute([$uiid, $targetFilePath]);
                    }
                }
            }

            // Log Initial status
            $pdo->prepare("
                INSERT INTO incident_status_logs (utility_incident_id, old_status, new_status, changed_by, notes) 
                VALUES (?, NULL, 'Submitted', ?, 'Report submitted by resident.')
            ")->execute([$uiid, $userId]);

            // Create admin notification
            $pdo->prepare("
                INSERT INTO incident_notifications (user_id, message) 
                VALUES (1, ?)
            ")->execute(["New incident {$incident_id} reported: " . substr($description, 0, 50) . "..."]);

            $success = "Incident Report {$incident_id} successfully submitted!";
        } catch (PDOException $e) {
            $error = "Failed to submit report: " . $e->getMessage();
        }
    }
}

// Fetch categories for form
$categories = $pdo->query("SELECT * FROM incident_categories ORDER BY name ASC")->fetchAll();

// Fetch assets for selection
$assets = $pdo->query("SELECT id, name, asset_id FROM utility_assets ORDER BY asset_id ASC")->fetchAll();

// Fetch citizen's reports
$myReports = $pdo->prepare("
    SELECT i.*, c.name as category_name, link.utility_asset_id, a.asset_id as asset_code
    FROM utility_incidents i 
    JOIN incident_categories c ON i.category_id = c.id
    LEFT JOIN incident_asset_links link ON i.id = link.utility_incident_id
    LEFT JOIN utility_assets a ON link.utility_asset_id = a.id
    WHERE i.resident_id = ?
    ORDER BY i.created_at DESC
");
$myReports->execute([$userId]);
$reports = $myReports->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Incident Report</title>
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Leaflet CSS & JS for map coords selection -->
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
        }

        .dashboard-header h1 {
            color: #2c3e50;
            font-size: 30px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .dashboard-header h1 i {
            color: #3762c8;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 14px;
            font-weight: 500;
        }

        .alert-error {
            background-color: #fde8e8;
            color: #c0392b;
            border: 1px solid #f8b4b4;
        }

        .alert-success {
            background-color: #e2fbe8;
            color: #1e7e34;
            border: 1px solid #b8f0c5;
        }

        .layout-grid {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 35px;
        }

        @media (max-width: 992px) {
            .layout-grid {
                grid-template-columns: 1fr;
            }
        }

        .section-box {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .section-box h3 {
            font-size: 16px;
            color: #2c3e50;
            margin-bottom: 20px;
            border-bottom: 2px solid #f1f2f6;
            padding-bottom: 10px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 15px;
        }

        .form-group label {
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
        }

        .form-control {
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 14px;
            outline: none;
            transition: border 0.3s;
        }

        .form-control:focus {
            border-color: #3762c8;
        }

        #map {
            width: 100%;
            height: 200px;
            border-radius: 8px;
            margin-top: 5px;
            border: 1px solid #cbd5e1;
        }

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
        }

        .btn-primary { background: #3762c8; color: white; }
        .btn-primary:hover { background: #2851b0; }

        /* Report Feed List */
        .report-feed {
            display: flex;
            flex-direction: column;
            gap: 15px;
            max-height: 700px;
            overflow-y: auto;
            padding-right: 5px;
        }

        .report-item {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 15px 20px;
            background: #f8fafc;
            transition: transform 0.2s;
        }

        .report-item:hover {
            transform: translateX(3px);
            background: #f1f5f9;
        }

        .report-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .report-id {
            font-size: 13px;
            font-weight: 700;
            color: #2c3e50;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 99px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-submitted { background: #e0f2fe; color: #0284c7; }
        .badge-underreview { background: #fef3c7; color: #d97706; }
        .badge-verified { background: #e0e7ff; color: #4f46e5; }
        .badge-forwarded { background: #fae8ff; color: #c084fc; }
        .badge-inprogress { background: #ecfdf5; color: #10b981; }
        .badge-resolved { background: #d1fae5; color: #065f46; }
        .badge-closed { background: #f1f5f9; color: #64748b; }
    </style>
</head>
<body>

<?php include 'includes/utilities_sidebar.php'; ?>

<main class="main-content" id="mainContent">
    <div class="card">
        
        <div class="dashboard-header">
            <div>
                <h1><i class="fas fa-bullhorn"></i> Submit Incident Report</h1>
                <p style="color: #64748b; font-size: 14px; margin-top: 5px;">Report broken streetlights, water pipeline leaks, drainage blockage, or utility issues directly to the LGU.</p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="layout-grid">
            <!-- Submit Report Box -->
            <div class="section-box">
                <h3><i class="fas fa-edit"></i> Incident Report Form</h3>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Incident Category *</label>
                        <select name="category_id" class="form-control" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Associate to Utility Asset (Optional but Recommended)</label>
                        <select name="asset_id" class="form-control">
                            <option value="">No Linked Asset (Unidentified Location)</option>
                            <?php foreach ($assets as $ast): ?>
                                <option value="<?php echo $ast['id']; ?>">
                                    <?php echo htmlspecialchars($ast['name'] . ' ('.$ast['asset_id'].')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Report Description *</label>
                        <textarea name="description" class="form-control" rows="4" placeholder="Detail the issue (e.g. Streetlight bulb out / Water pipe leaking near building corner)" required></textarea>
                    </div>

                    <div class="form-group">
                        <label>Incident Address / Location *</label>
                        <input type="text" name="location" id="locationInput" class="form-control" placeholder="Street landmark, Barangay..." required>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:15px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label>GPS Latitude (Auto-Filled)</label>
                            <input type="number" step="any" name="latitude" id="latInput" class="form-control" readonly>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label>GPS Longitude (Auto-Filled)</label>
                            <input type="number" step="any" name="longitude" id="lngInput" class="form-control" readonly>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Click Map to Pin Location Coordinates</label>
                        <div id="map"></div>
                    </div>

                    <div class="form-group">
                        <label>Upload Supporting Photo / Image</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Report</button>
                </form>
            </div>

            <!-- Past Reports Feed -->
            <div class="section-box">
                <h3><i class="fas fa-history"></i> My Incident Submissions</h3>
                
                <div class="report-feed">
                    <?php if (empty($reports)): ?>
                        <div style="text-align: center; color: #94a3b8; padding: 40px;">You haven't submitted any reports yet.</div>
                    <?php else: ?>
                        <?php foreach ($reports as $report): 
                            $badgeClass = strtolower(str_replace([' ', 'to', 'System'], '', $report['status']));
                        ?>
                            <div class="report-item">
                                <div class="report-meta">
                                    <span class="report-id"><?php echo htmlspecialchars($report['incident_id']); ?></span>
                                    <span class="badge badge-<?php echo $badgeClass; ?>"><?php echo htmlspecialchars($report['status']); ?></span>
                                </div>
                                <div style="font-weight: 600; font-size: 14px; color:#2c3e50; margin-bottom:4px;"><?php echo htmlspecialchars($report['category_name']); ?></div>
                                <p style="font-size: 13px; color: #475569; margin-bottom: 8px;"><?php echo htmlspecialchars($report['description']); ?></p>
                                
                                <div style="display:flex; justify-content:space-between; align-items:center; font-size:11px; color:#94a3b8;">
                                    <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($report['location']); ?></span>
                                    <span><?php echo date('M d, Y', strtotime($report['created_at'])); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</main>

<script>
    // Initialize map centering around Manila area
    const map = L.map('map').setView([14.5995, 120.9842], 13);

    // OSM Tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    let marker = null;

    // Click handler to set coordinates
    map.on('click', function(e) {
        const lat = e.latlng.lat;
        const lng = e.latlng.lng;

        document.getElementById('latInput').value = lat.toFixed(6);
        document.getElementById('lngInput').value = lng.toFixed(6);

        if (marker) {
            marker.setLatLng(e.latlng);
        } else {
            marker = L.marker(e.latlng).addTo(map);
        }
    });
</script>

</body>
</html>
