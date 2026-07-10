<?php
// planning_coverage.php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $area_name = trim($_POST['area_name'] ?? '');
        $latitude = floatval($_POST['latitude'] ?? 0);
        $longitude = floatval($_POST['longitude'] ?? 0);
        $radius = intval($_POST['radius_meters'] ?? 500);
        $type = $_POST['coverage_type'] ?? 'Water Supply';
        $status = $_POST['coverage_status'] ?? 'Fully Covered';
        $remarks = trim($_POST['remarks'] ?? '');

        if (empty($area_name) || $latitude === 0.0 || $longitude === 0.0) {
            $error = 'Please fill in all required fields (Area Name, Latitude, Longitude).';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO utility_coverage_records (area_name, latitude, longitude, radius_meters, coverage_type, coverage_status, remarks) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$area_name, $latitude, $longitude, $radius, $type, $status, $remarks]);

                // Log outbound coordination action (informs the Urban Planning sync)
                $pdo->prepare("
                    INSERT INTO planning_coordination_logs (direction, log_type, details) 
                    VALUES ('Outbound', 'Coverage Update', ?)
                ")->execute(["Updated coverage records for area: {$area_name} ({$type} - {$status})."]);

                $success = "Coverage area record '{$area_name}' successfully created!";
            } catch (PDOException $e) {
                $error = "Failed to save record: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $pdo->prepare("DELETE FROM utility_coverage_records WHERE id = ?")->execute([$id]);
                $success = "Coverage record successfully deleted!";
            } catch (PDOException $e) {
                $error = "Failed to delete record: " . $e->getMessage();
            }
        }
    }
}

// Retrieve search / filters
$search = trim($_GET['search'] ?? '');
$type_filter = $_GET['coverage_type'] ?? '';
$status_filter = $_GET['coverage_status'] ?? '';

$conditions = [];
$params = [];

if ($search) {
    $conditions[] = "(area_name LIKE ? OR remarks LIKE ?)";
    $searchWildcard = '%' . $search . '%';
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
}

if ($type_filter) {
    $conditions[] = "coverage_type = ?";
    $params[] = $type_filter;
}

if ($status_filter) {
    $conditions[] = "coverage_status = ?";
    $params[] = $status_filter;
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

$records = $pdo->prepare("
    SELECT * FROM utility_coverage_records 
    $whereClause 
    ORDER BY area_name ASC
");
$records->execute($params);
$coverageRecords = $records->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Utility Coverage Zone Records</title>
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

        .btn-danger { background: #e74c3c; color: white; }
        .btn-danger:hover { background: #c0392b; }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-error { background-color: #fde8e8; color: #c0392b; border: 1px solid #f8b4b4; }
        .alert-success { background-color: #e2fbe8; color: #1e7e34; border: 1px solid #b8f0c5; }

        .layout-grid {
            display: grid;
            grid-template-columns: 1fr 1.3fr;
            gap: 35px;
        }

        @media (max-width: 1000px) {
            .layout-grid { grid-template-columns: 1fr; }
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
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 15px;
        }

        .form-group label { font-size: 13px; font-weight: 600; color: #64748b; }
        .form-control { padding: 10px 14px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 14px; outline: none; }

        #map {
            width: 100%;
            height: 200px;
            border-radius: 8px;
            margin-top: 5px;
            border: 1px solid #cbd5e1;
        }

        /* Coverage List */
        .list-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
            max-height: 700px;
            overflow-y: auto;
            padding-right: 5px;
        }

        .list-item {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 15px 20px;
            background: #f8fafc;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 99px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-fullycovered { background: #e2fbe8; color: #1e7e34; }
        .badge-partiallycovered { background: #fff4e5; color: #b45309; }
        .badge-notcovered { background: #fde8e8; color: #bd2130; }
    </style>
</head>
<body>

<?php include 'includes/utilities_sidebar.php'; ?>

<main class="main-content" id="mainContent">
    <div class="card">
        
        <!-- Header -->
        <div class="dashboard-header">
            <div>
                <h1><i class="fas fa-satellite"></i> Utility Coverage Records</h1>
                <p style="color: #64748b; font-size: 14px; margin-top: 5px;">Manage service availability records and boundaries for municipality zoning coordination.</p>
            </div>
            <div>
                <a href="planning_dashboard.php" class="btn btn-outline"><i class="fas fa-chevron-left"></i> Dashboard</a>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="layout-grid">
            <!-- Left: Add coverage area form -->
            <div class="section-box">
                <h3><i class="fas fa-plus"></i> Define Coverage Area</h3>
                
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="form-group">
                        <label>Area Zone Name *</label>
                        <input type="text" name="area_name" class="form-control" placeholder="e.g. Sampaloc District Zone A" required>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <div class="form-group">
                            <label>GPS Latitude *</label>
                            <input type="number" step="any" name="latitude" id="latInput" class="form-control" required readonly>
                        </div>
                        <div class="form-group">
                            <label>GPS Longitude *</label>
                            <input type="number" step="any" name="longitude" id="lngInput" class="form-control" required readonly>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Click Map to Select Center Point Coordinates</label>
                        <div id="map"></div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <div class="form-group">
                            <label>Coverage Type *</label>
                            <select name="coverage_type" class="form-control" required>
                                <option value="Water Supply">Water Supply</option>
                                <option value="Streetlight">Streetlight</option>
                                <option value="Drainage">Drainage</option>
                                <option value="Electrical">Electrical</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Coverage Status *</label>
                            <select name="coverage_status" class="form-control" required>
                                <option value="Fully Covered">Fully Covered</option>
                                <option value="Partially Covered">Partially Covered</option>
                                <option value="Not Covered">Not Covered</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Service Area Radius (Meters)</label>
                        <input type="number" name="radius_meters" class="form-control" value="500">
                    </div>

                    <div class="form-group">
                        <label>Coordination Remarks / Limitations</label>
                        <textarea name="remarks" class="form-control" rows="2" placeholder="e.g. Redundant supply mains installed. No pressure drops."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Coverage Zone</button>
                </form>
            </div>

            <!-- Right: Interactive Coverage List -->
            <div class="section-box">
                <h3><i class="fas fa-clipboard-list"></i> Managed Coverage Zones</h3>
                
                <div class="list-container">
                    <?php if (empty($coverageRecords)): ?>
                        <div style="text-align: center; color:#94a3b8; padding:40px;">No coverage records logged yet.</div>
                    <?php else: ?>
                        <?php foreach ($coverageRecords as $rec): 
                            $badgeClass = strtolower(str_replace(' ', '', $rec['coverage_status']));
                        ?>
                            <div class="list-item">
                                <div>
                                    <div style="font-weight:700; font-size:14px; color:#2c3e50;"><?php echo htmlspecialchars($rec['area_name']); ?></div>
                                    <div style="font-size:11px; font-weight:600; color:#3762c8; margin-top:2px;">
                                        <?php echo htmlspecialchars($rec['coverage_type']); ?> · Radius: <?php echo htmlspecialchars($rec['radius_meters']); ?>m
                                    </div>
                                    <p style="font-size:12px; color:#64748b; margin-top:5px;"><?php echo htmlspecialchars($rec['remarks']); ?></p>
                                </div>
                                <div style="display:flex; flex-direction:column; align-items:flex-end; gap:10px;">
                                    <span class="badge badge-<?php echo $badgeClass; ?>"><?php echo htmlspecialchars($rec['coverage_status']); ?></span>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this coverage zone?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $rec['id']; ?>">
                                        <button type="submit" class="btn-outline" style="border:none; cursor:pointer; color:#e74c3c; font-size:12px;" title="Delete Record"><i class="fas fa-trash"></i> Delete</button>
                                    </form>
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
    // Map configuration
    const map = L.map('map').setView([14.5995, 120.9842], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    let marker = null;

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
