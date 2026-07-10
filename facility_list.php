<?php
// facility_list.php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $facility_id = trim($_POST['facility_id'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $type = $_POST['facility_type'] ?? 'Other LGU facility';
        $location = trim($_POST['location'] ?? '');
        $lat = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
        $lng = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;
        $description = trim($_POST['description'] ?? '');

        if (empty($facility_id) || empty($name) || empty($location)) {
            $error = 'Please fill in all required fields (Facility ID, Name, and Location).';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO public_facilities (facility_id, name, facility_type, location, latitude, longitude, utility_status, description) 
                    VALUES (?, ?, ?, ?, ?, ?, 'Fully Ready', ?)
                ");
                $stmt->execute([$facility_id, $name, $type, $location, $lat, $lng, $description]);
                $fid = $pdo->lastInsertId();

                // Setup default checklist entry
                $pdo->prepare("
                    INSERT INTO facility_utility_status (public_facility_id, water_available, electricity_available, drainage_ok, lighting_ok) 
                    VALUES (?, 1, 1, 1, 1)
                ")->execute([$fid]);

                $success = "Public Facility '{$name}' successfully registered!";
            } catch (PDOException $e) {
                $error = "Failed to register facility: " . $e->getMessage();
            }
        }
    } elseif ($action === 'update_checklist') {
        $id = intval($_POST['id'] ?? 0);
        $water = isset($_POST['water_available']) ? 1 : 0;
        $elec = isset($_POST['electricity_available']) ? 1 : 0;
        $drainage = isset($_POST['drainage_ok']) ? 1 : 0;
        $lighting = isset($_POST['lighting_ok']) ? 1 : 0;

        if ($id > 0) {
            try {
                // Determine Utility Status
                $totalScore = $water + $elec + $drainage + $lighting;
                $status = 'Fully Ready';
                if ($totalScore === 0) {
                    $status = 'Not Ready';
                } elseif ($totalScore < 4) {
                    $status = 'Partially Ready';
                }

                // Update checklist values
                $stmt = $pdo->prepare("
                    UPDATE facility_utility_status 
                    SET water_available = ?, electricity_available = ?, drainage_ok = ?, lighting_ok = ? 
                    WHERE public_facility_id = ?
                ");
                $stmt->execute([$water, $elec, $drainage, $lighting, $id]);

                // Update parent status
                $pdo->prepare("UPDATE public_facilities SET utility_status = ? WHERE id = ?")->execute([$status, $id]);

                // Log outbound coordination action
                $stmt = $pdo->prepare("SELECT name FROM public_facilities WHERE id = ?");
                $stmt->execute([$id]);
                $fName = $stmt->fetchColumn();

                $pdo->prepare("
                    INSERT INTO planning_coordination_logs (direction, log_type, details) 
                    VALUES ('Outbound', 'Facility Update', ?)
                ")->execute(["Updated utility availability indicators for public venue '{$fName}' to: {$status}."]);

                $success = "Utility checklist for '{$fName}' updated successfully to {$status}!";
            } catch (PDOException $e) {
                $error = "Failed to update checklist: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $pdo->prepare("DELETE FROM public_facilities WHERE id = ?")->execute([$id]);
                $success = "Facility record deleted successfully!";
            } catch (PDOException $e) {
                $error = "Failed to delete record: " . $e->getMessage();
            }
        }
    }
}

// ------------------------------------------------------------------------
// Get Search / Filter / Pagination parameters
// ------------------------------------------------------------------------
$search = trim($_GET['search'] ?? '');
$type_filter = $_GET['facility_type'] ?? '';
$status_filter = $_GET['utility_status'] ?? '';

// Pagination configuration
$limit = 9;
$page = !empty($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(f.name LIKE ? OR f.location LIKE ? OR f.facility_id LIKE ?)";
    $searchWildcard = '%' . $search . '%';
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
}

if ($type_filter) {
    $conditions[] = "f.facility_type = ?";
    $params[] = $type_filter;
}

if ($status_filter) {
    $conditions[] = "f.utility_status = ?";
    $params[] = $status_filter;
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Retrieve count for pagination
$countQuery = "SELECT COUNT(*) FROM public_facilities f $whereClause";
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Retrieve facilities list
$query = "
    SELECT f.*, s.water_available, s.electricity_available, s.drainage_ok, s.lighting_ok
    FROM public_facilities f 
    JOIN facility_utility_status s ON f.id = s.public_facility_id 
    $whereClause
    ORDER BY f.name ASC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$facilitiesList = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Facility Utilities Manager</title>
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

        /* Filter Panel */
        .filter-panel {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex: 1;
            min-width: 160px;
        }

        .form-group label { font-size: 12px; font-weight: 600; color: #64748b; }
        .form-control { padding: 10px 14px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 14px; outline: none; }

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

        .btn-outline { background: transparent; border: 1px solid #cbd5e1; color: #64748b; }
        .btn-outline:hover { background: #f8f9fa; color: #2c3e50; }

        /* Facility Grid Layout */
        .facility-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
            gap: 25px;
        }

        .facility-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.2s;
        }

        .facility-card:hover {
            transform: translateY(-2px);
            border-color: #3762c8;
        }

        .facility-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .facility-title { font-size: 17px; font-weight: 700; color: #2c3e50; }
        .facility-location { font-size: 12px; color: #64748b; margin-top: 2px; }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 99px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-fullyready { background: #e2fbe8; color: #1e7e34; }
        .badge-partiallyready { background: #fff4e5; color: #b45309; }
        .badge-notready { background: #fde8e8; color: #bd2130; }

        /* Checklist Indicator items */
        .checklist-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 15px;
            background: #f8fafc;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #edf2f7;
        }

        .indicator-item {
            font-size: 12px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .indicator-item.ok { color: #1e7e34; }
        .indicator-item.fail { color: #bd2130; }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(4px);
        }
        .modal.open { display: flex; }
        .modal-content {
            background: white;
            width: 90%;
            max-width: 600px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            animation: modalFadeIn 0.3s ease;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .modal-header { padding: 20px 24px; background: #f8f9fa; border-bottom: 1px solid #edf2f7; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { font-size: 18px; color: #2c3e50; }
        .modal-close { background: transparent; border: none; font-size: 18px; cursor: pointer; color: #64748b; }
        .modal-body { padding: 24px; max-height: 75vh; overflow-y: auto; }
        .modal-footer { padding: 16px 24px; background: #f8f9fa; border-top: 1px solid #edf2f7; display: flex; justify-content: flex-end; gap: 12px; }

        #map { width: 100%; height: 180px; border-radius: 8px; margin-top: 5px; border: 1px solid #cbd5e1; }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
            font-size: 14px;
            font-weight: 500;
        }
        .checkbox-group input { width: 18px; height: 18px; }

        /* Pagination */
        .pagination-container { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; }
        .pagination-info { font-size: 13px; color: #64748b; }
        .pagination-links { display: flex; gap: 6px; }
        .page-link { padding: 6px 12px; border-radius: 6px; border: 1px solid #cbd5e1; text-decoration: none; color: #64748b; font-size: 13px; font-weight: 500; }
        .page-link:hover { border-color: #3762c8; color: #3762c8; background: #f8fafc; }
        .page-link.active { background: #3762c8; color: white; border-color: #3762c8; }
        
        .form-row { display: flex; gap: 15px; margin-bottom: 15px; }
        .form-row .form-group { flex: 1; }
        @media (max-width: 600px) { .form-row { flex-direction: column; gap: 0; } }
    </style>
</head>
<body>

<?php include 'includes/utilities_sidebar.php'; ?>

<main class="main-content" id="mainContent">
    <div class="card">
        
        <!-- Header -->
        <div class="dashboard-header">
            <div>
                <h1><i class="fas fa-warehouse"></i> Public Facilities Records</h1>
                <p style="color: #64748b; font-size: 14px; margin-top: 5px;">Configure municipal facilities, review GPS pins, and track core utility availability toggles.</p>
            </div>
            <div>
                <button class="btn btn-primary" onclick="openCreateModal()"><i class="fas fa-plus"></i> Add Facility</button>
                <a href="facility_dashboard.php" class="btn btn-outline"><i class="fas fa-chevron-left"></i> Dashboard</a>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Filters Form -->
        <form method="GET" class="filter-panel">
            <div class="form-group" style="flex:2;">
                <label>Keyword Search</label>
                <input type="text" name="search" class="form-control" placeholder="Search facility ID, name, location..." value="<?php echo htmlspecialchars($search); ?>">
            </div>

            <div class="form-group">
                <label>Facility Type</label>
                <select name="facility_type" class="form-control">
                    <option value="">All Types</option>
                    <option value="Park" <?php echo $type_filter === 'Park' ? 'selected' : ''; ?>>Park</option>
                    <option value="Gymnasium" <?php echo $type_filter === 'Gymnasium' ? 'selected' : ''; ?>>Gymnasium</option>
                    <option value="Barangay Hall" <?php echo $type_filter === 'Barangay Hall' ? 'selected' : ''; ?>>Barangay Hall</option>
                    <option value="Evacuation Center" <?php echo $type_filter === 'Evacuation Center' ? 'selected' : ''; ?>>Evacuation Center</option>
                    <option value="Community Center" <?php echo $type_filter === 'Community Center' ? 'selected' : ''; ?>>Community Center</option>
                </select>
            </div>

            <div class="form-group">
                <label>Utility Readiness</label>
                <select name="utility_status" class="form-control">
                    <option value="">All Readiness</option>
                    <option value="Fully Ready" <?php echo $status_filter === 'Fully Ready' ? 'selected' : ''; ?>>Fully Ready</option>
                    <option value="Partially Ready" <?php echo $status_filter === 'Partially Ready' ? 'selected' : ''; ?>>Partially Ready</option>
                    <option value="Not Ready" <?php echo $status_filter === 'Not Ready' ? 'selected' : ''; ?>>Not Ready</option>
                </select>
            </div>

            <div style="display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
                <a href="facility_list.php" class="btn btn-outline">Reset</a>
            </div>
        </form>

        <!-- Facilities Cards Grid -->
        <div class="facility-grid">
            <?php if (empty($facilitiesList)): ?>
                <div style="grid-column:1/-1; text-align:center; color:#64748b; padding:40px;">No public facilities match filters.</div>
            <?php else: ?>
                <?php foreach ($facilitiesList as $fac): 
                    $badgeClass = strtolower(str_replace(' ', '', $fac['utility_status']));
                ?>
                    <div class="facility-card">
                        <div>
                            <div class="facility-header">
                                <div>
                                    <div class="facility-title"><?php echo htmlspecialchars($fac['name']); ?></div>
                                    <div class="facility-location"><?php echo htmlspecialchars($fac['facility_type'] . ' · '.$fac['facility_id']); ?></div>
                                </div>
                                <span class="badge badge-<?php echo $badgeClass; ?>">
                                    <?php echo htmlspecialchars($fac['utility_status']); ?>
                                </span>
                            </div>

                            <p style="font-size:12px; color:#64748b; margin-bottom:15px;"><?php echo htmlspecialchars($fac['description']); ?></p>

                            <div style="font-size:12px; color:#2c3e50; font-weight:600;"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($fac['location']); ?></div>

                            <!-- Indicators block -->
                            <div class="checklist-grid">
                                <div class="indicator-item <?php echo $fac['water_available'] ? 'ok' : 'fail'; ?>">
                                    <i class="fas <?php echo $fac['water_available'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i> Water Supply
                                </div>
                                <div class="indicator-item <?php echo $fac['electricity_available'] ? 'ok' : 'fail'; ?>">
                                    <i class="fas <?php echo $fac['electricity_available'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i> Electricity
                                </div>
                                <div class="indicator-item <?php echo $fac['drainage_ok'] ? 'ok' : 'fail'; ?>">
                                    <i class="fas <?php echo $fac['drainage_ok'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i> Drainage
                                </div>
                                <div class="indicator-item <?php echo $fac['lighting_ok'] ? 'ok' : 'fail'; ?>">
                                    <i class="fas <?php echo $fac['lighting_ok'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i> Lighting System
                                </div>
                            </div>
                        </div>

                        <div style="border-top:1px solid #edf2f7; margin-top:20px; padding-top:15px; display:flex; justify-content:space-between; align-items:center;">
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this public facility?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $fac['id']; ?>">
                                <button type="submit" class="btn-outline" style="border:none; cursor:pointer; color:#e74c3c; font-size:12px;"><i class="fas fa-trash"></i> Delete</button>
                            </form>
                            
                            <button class="btn btn-outline" style="padding: 6px 12px; font-size:12px;" onclick='openChecklistModal(<?php echo json_encode($fac); ?>)'><i class="fas fa-check-double"></i> Update Checklist</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination Container -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination-container">
            <div class="pagination-info">
                Showing <?php echo $offset + 1; ?> to <?php echo min($totalRecords, $offset + $limit); ?> of <?php echo $totalRecords; ?> facilities
            </div>
            <div class="pagination-links">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="facility_list.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&facility_type=<?php echo urlencode($type_filter); ?>&utility_status=<?php echo urlencode($status_filter); ?>" class="page-link <?php echo $page == $i ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</main>

<!-- CREATE FACILITY MODAL -->
<div id="createModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add LGU Public Facility</h3>
            <button class="modal-close" onclick="closeModal('createModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Facility Name *</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Barangay 386 Gym">
                    </div>
                    <div class="form-group">
                        <label>Facility Type *</label>
                        <select name="facility_type" class="form-control" required>
                            <option value="Park">Park</option>
                            <option value="Gymnasium">Gymnasium</option>
                            <option value="Barangay Hall">Barangay Hall</option>
                            <option value="Evacuation Center">Evacuation Center</option>
                            <option value="Community Center">Community Center</option>
                            <option value="Other LGU facility">Other LGU facility</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Unique Facility ID Code *</label>
                        <input type="text" name="facility_id" class="form-control" required placeholder="e.g. FAC-GYM-009">
                    </div>
                    <div class="form-group">
                        <label>Location Address *</label>
                        <input type="text" name="location" class="form-control" required placeholder="e.g. San Rafael St, Manila">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                    <div class="form-group">
                        <label>GPS Latitude (Optional)</label>
                        <input type="number" step="any" name="latitude" id="latInput" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label>GPS Longitude (Optional)</label>
                        <input type="number" step="any" name="longitude" id="lngInput" class="form-control" readonly>
                    </div>
                </div>

                <div class="form-group">
                    <label>Click Map to Set Pin Coordinate Location</label>
                    <div id="map"></div>
                </div>

                <div class="form-group">
                    <label>Facility Description</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Describe the facility usage scope..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Facility</button>
            </div>
        </form>
    </div>
</div>

<!-- UPDATE CHECKLIST MODAL -->
<div id="checklistModal" class="modal">
    <div class="modal-content" style="max-width:450px;">
        <div class="modal-header">
            <h3>Update Utility Checklist Status</h3>
            <button class="modal-close" onclick="closeModal('checklistModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update_checklist">
            <input type="hidden" id="checklist-id" name="id">
            <div class="modal-body">
                <p style="font-size:13px; color:#64748b; margin-bottom:20px;">Toggle core utility indicators to automatically compute overall facility readiness.</p>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="water_available" id="check-water" value="1">
                    <label for="check-water">Water Supply Functional</label>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" name="electricity_available" id="check-elec" value="1">
                    <label for="check-elec">Electricity Grid Connected</label>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" name="drainage_ok" id="check-drainage" value="1">
                    <label for="check-drainage">Sanitation & Drainage Clear</label>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" name="lighting_ok" id="check-lighting" value="1">
                    <label for="check-lighting">Lighting System Operational</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('checklistModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Indicators</button>
            </div>
        </form>
    </div>
</div>

<script>
    let map = null;
    let marker = null;

    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
    }

    function openCreateModal() {
        document.getElementById('createModal').classList.add('open');
        
        // Initialize map asynchronously when opening modal
        if (!map) {
            setTimeout(() => {
                map = L.map('map').setView([14.5995, 120.9842], 13);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);

                map.on('click', function(e) {
                    document.getElementById('latInput').value = e.latlng.lat.toFixed(6);
                    document.getElementById('lngInput').value = e.latlng.lng.toFixed(6);

                    if (marker) {
                        marker.setLatLng(e.latlng);
                    } else {
                        marker = L.marker(e.latlng).addTo(map);
                    }
                });
            }, 200);
        }
    }

    function openChecklistModal(fac) {
        document.getElementById('checklist-id').value = fac.id;
        document.getElementById('check-water').checked = fac.water_available == 1;
        document.getElementById('check-elec').checked = fac.electricity_available == 1;
        document.getElementById('check-drainage').checked = fac.drainage_ok == 1;
        document.getElementById('check-lighting').checked = fac.lighting_ok == 1;
        document.getElementById('checklistModal').classList.add('open');
    }
</script>

</body>
</html>
