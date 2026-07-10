<?php
// facility_incidents.php
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
        $facility_id = intval($_POST['public_facility_id'] ?? 0);
        $asset_id = !empty($_POST['utility_asset_id']) ? intval($_POST['utility_asset_id']) : null;
        $type = $_POST['incident_type'] ?? 'Power Outage';
        $description = trim($_POST['description'] ?? '');

        if ($facility_id <= 0 || empty($description)) {
            $error = 'Please fill in all required fields (Facility and Description).';
        } else {
            try {
                // Insert incident
                $stmt = $pdo->prepare("
                    INSERT INTO facility_incidents (public_facility_id, utility_asset_id, incident_type, description, status) 
                    VALUES (?, ?, ?, ?, 'Active')
                ");
                $stmt->execute([$facility_id, $asset_id, $type, $description]);

                // Downgrade specific indicator automatically in checklist
                $water = 1; $elec = 1; $drainage = 1; $lighting = 1;
                if ($type === 'Water Interruption') $water = 0;
                elseif ($type === 'Power Outage') $elec = 0;
                elseif ($type === 'Drainage Blockage') $drainage = 0;
                elseif ($type === 'Lighting Failure') $lighting = 0;

                // Update checklist status
                $stmt = $pdo->prepare("
                    UPDATE facility_utility_status 
                    SET 
                        water_available = CASE WHEN ? = 0 THEN 0 ELSE water_available END,
                        electricity_available = CASE WHEN ? = 0 THEN 0 ELSE electricity_available END,
                        drainage_ok = CASE WHEN ? = 0 THEN 0 ELSE drainage_ok END,
                        lighting_ok = CASE WHEN ? = 0 THEN 0 ELSE lighting_ok END
                    WHERE public_facility_id = ?
                ");
                $stmt->execute([$water, $elec, $drainage, $lighting, $facility_id]);

                // Fetch new indicators count to recalculate status
                $stmt = $pdo->prepare("SELECT water_available, electricity_available, drainage_ok, lighting_ok FROM facility_utility_status WHERE public_facility_id = ?");
                $stmt->execute([$facility_id]);
                $chk = $stmt->fetch();

                $totalScore = $chk['water_available'] + $chk['electricity_available'] + $chk['drainage_ok'] + $chk['lighting_ok'];
                $status = 'Fully Ready';
                if ($totalScore === 0) {
                    $status = 'Not Ready';
                } elseif ($totalScore < 4) {
                    $status = 'Partially Ready';
                }

                $pdo->prepare("UPDATE public_facilities SET utility_status = ? WHERE id = ?")->execute([$status, $facility_id]);

                // Log outbound coordination
                $stmt = $pdo->prepare("SELECT name FROM public_facilities WHERE id = ?");
                $stmt->execute([$facility_id]);
                $fName = $stmt->fetchColumn();

                $pdo->prepare("
                    INSERT INTO planning_coordination_logs (direction, log_type, details) 
                    VALUES ('Outbound', 'Facility Incident', ?)
                ")->execute(["New facility incident logged at '{$fName}': {$type}. Status degraded to {$status}."]);

                $success = "Facility Incident successfully registered and readiness updated!";
            } catch (PDOException $e) {
                $error = "Failed to log incident: " . $e->getMessage();
            }
        }
    } elseif ($action === 'resolve') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                // Get incident details
                $stmt = $pdo->prepare("SELECT public_facility_id, incident_type FROM facility_incidents WHERE id = ?");
                $stmt->execute([$id]);
                $inc = $stmt->fetch();

                if ($inc) {
                    // Mark incident resolved
                    $pdo->prepare("UPDATE facility_incidents SET status = 'Resolved' WHERE id = ?")->execute([$id]);

                    // Restore check indicator
                    $type = $inc['incident_type'];
                    $facility_id = $inc['public_facility_id'];

                    $water = ($type === 'Water Interruption') ? 1 : null;
                    $elec = ($type === 'Power Outage') ? 1 : null;
                    $drainage = ($type === 'Drainage Blockage') ? 1 : null;
                    $lighting = ($type === 'Lighting Failure') ? 1 : null;

                    $stmt = $pdo->prepare("
                        UPDATE facility_utility_status 
                        SET 
                            water_available = CASE WHEN ? = 1 THEN 1 ELSE water_available END,
                            electricity_available = CASE WHEN ? = 1 THEN 1 ELSE electricity_available END,
                            drainage_ok = CASE WHEN ? = 1 THEN 1 ELSE drainage_ok END,
                            lighting_ok = CASE WHEN ? = 1 THEN 1 ELSE lighting_ok END
                        WHERE public_facility_id = ?
                    ");
                    $stmt->execute([$water, $elec, $drainage, $lighting, $facility_id]);

                    // Recalculate status
                    $stmt = $pdo->prepare("SELECT water_available, electricity_available, drainage_ok, lighting_ok FROM facility_utility_status WHERE public_facility_id = ?");
                    $stmt->execute([$facility_id]);
                    $chk = $stmt->fetch();

                    $totalScore = $chk['water_available'] + $chk['electricity_available'] + $chk['drainage_ok'] + $chk['lighting_ok'];
                    $status = 'Fully Ready';
                    if ($totalScore === 0) {
                        $status = 'Not Ready';
                    } elseif ($totalScore < 4) {
                        $status = 'Partially Ready';
                    }

                    $pdo->prepare("UPDATE public_facilities SET utility_status = ? WHERE id = ?")->execute([$status, $facility_id]);

                    // Log outbound coordination
                    $stmt = $pdo->prepare("SELECT name FROM public_facilities WHERE id = ?");
                    $stmt->execute([$facility_id]);
                    $fName = $stmt->fetchColumn();

                    $pdo->prepare("
                        INSERT INTO planning_coordination_logs (direction, log_type, details) 
                        VALUES ('Outbound', 'Incident Resolution', ?)
                    ")->execute(["Facility incident {$type} resolved at '{$fName}'. Status updated to {$status}."]);

                    $success = "Incident successfully resolved and readiness updated!";
                }
            } catch (PDOException $e) {
                $error = "Failed to resolve incident: " . $e->getMessage();
            }
        }
    }
}

// ------------------------------------------------------------------------
// Get Search / Filter / Pagination parameters
// ------------------------------------------------------------------------
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';

// Pagination configuration
$limit = 10;
$page = !empty($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(i.description LIKE ? OR i.incident_type LIKE ? OR f.name LIKE ?)";
    $searchWildcard = '%' . $search . '%';
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
}

if ($status_filter) {
    $conditions[] = "i.status = ?";
    $params[] = $status_filter;
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Retrieve count for pagination
$countQuery = "
    SELECT COUNT(*) 
    FROM facility_incidents i 
    JOIN public_facilities f ON i.public_facility_id = f.id
    $whereClause
";
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Retrieve incidents list
$query = "
    SELECT i.*, f.name as facility_name, a.asset_id as asset_code
    FROM facility_incidents i 
    JOIN public_facilities f ON i.public_facility_id = f.id 
    LEFT JOIN utility_assets a ON i.utility_asset_id = a.id 
    $whereClause
    ORDER BY i.created_at DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$incidentsList = $stmt->fetchAll();

// Retrieve all facilities and assets for dropdown selector
$facilitiesList = $pdo->query("SELECT id, name FROM public_facilities ORDER BY name ASC")->fetchAll();
$assetsList = $pdo->query("SELECT id, name, asset_id FROM utility_assets ORDER BY asset_id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facility Utility Incidents</title>
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        /* Table Section */
        .table-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .table-container { overflow-x: auto; width: 100%; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th {
            background: #f8f9fa;
            color: #475569;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            padding: 12px 16px;
            border-bottom: 2px solid #e2e8f0;
        }
        td { padding: 14px 16px; border-bottom: 1px solid #edf2f7; font-size: 14px; color: #2c3e50; }
        tr:hover td { background: #fcfcfc; }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 99px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-active { background: #fde8e8; color: #bd2130; }
        .badge-resolved { background: #e2fbe8; color: #1e7e34; }

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
            max-width: 550px;
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
        .modal-body { padding: 24px; }
        .modal-footer { padding: 16px 24px; background: #f8f9fa; border-top: 1px solid #edf2f7; display: flex; justify-content: flex-end; gap: 12px; }

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
                <h1><i class="fas fa-exclamation-triangle"></i> Facility Utility Incidents</h1>
                <p style="color: #64748b; font-size: 14px; margin-top: 5px;">Log, monitor, and resolve utility failures affecting public LGU facilities.</p>
            </div>
            <div>
                <button class="btn btn-primary" onclick="openCreateModal()"><i class="fas fa-plus"></i> Log Incident</button>
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
                <input type="text" name="search" class="form-control" placeholder="Search description, incident type, facility name..." value="<?php echo htmlspecialchars($search); ?>">
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="">All Statuses</option>
                    <option value="Active" <?php echo $status_filter === 'Active' ? 'selected' : ''; ?>>Active</option>
                    <option value="Resolved" <?php echo $status_filter === 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                </select>
            </div>

            <div style="display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
                <a href="facility_incidents.php" class="btn btn-outline">Reset</a>
            </div>
        </form>

        <!-- Incidents Table -->
        <div class="table-section">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Facility Name</th>
                            <th>Incident Type</th>
                            <th>Description</th>
                            <th>Linked Asset</th>
                            <th>Status</th>
                            <th>Date Logged</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($incidentsList)): ?>
                            <tr><td colspan="7" style="text-align:center; padding:30px; color:#64748b;">No incidents recorded.</td></tr>
                        <?php else: ?>
                            <?php foreach ($incidentsList as $inc): 
                                $statusBadge = strtolower($inc['status']);
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($inc['facility_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($inc['incident_type']); ?></td>
                                <td><?php echo htmlspecialchars($inc['description']); ?></td>
                                <td><?php echo $inc['asset_code'] ? htmlspecialchars($inc['asset_code']) : '<em style="color:#94a3b8;">None</em>'; ?></td>
                                <td><span class="badge badge-<?php echo $statusBadge; ?>"><?php echo htmlspecialchars($inc['status']); ?></span></td>
                                <td><?php echo date('M d, Y h:i A', strtotime($inc['created_at'])); ?></td>
                                <td style="text-align:right; white-space:nowrap;">
                                    <?php if ($inc['status'] === 'Active'): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Resolve this facility incident and restore readiness?');">
                                            <input type="hidden" name="action" value="resolve">
                                            <input type="hidden" name="id" value="<?php echo $inc['id']; ?>">
                                            <button type="submit" class="btn btn-primary" style="padding: 6px 12px; font-size:12px;"><i class="fas fa-check-circle"></i> Resolve Incident</button>
                                        </form>
                                    <?php else: ?>
                                        <span style="font-size:12px; color:#94a3b8; font-style:italic;">Resolved</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Container -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination-container">
                <div class="pagination-info">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($totalRecords, $offset + $limit); ?> of <?php echo $totalRecords; ?> records
                </div>
                <div class="pagination-links">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="facility_incidents.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" class="page-link <?php echo $page == $i ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<!-- CREATE INCIDENT MODAL -->
<div id="createModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Log Facility Utility Incident</h3>
            <button class="modal-close" onclick="closeModal('createModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Public LGU Facility *</label>
                        <select name="public_facility_id" class="form-control" required>
                            <option value="">Select Facility</option>
                            <?php foreach ($facilitiesList as $fac): ?>
                                <option value="<?php echo $fac['id']; ?>"><?php echo htmlspecialchars($fac['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Incident Type *</label>
                        <select name="incident_type" class="form-control" required>
                            <option value="Power Outage">Power Outage</option>
                            <option value="Water Interruption">Water Interruption</option>
                            <option value="Drainage Blockage">Drainage Blockage</option>
                            <option value="Lighting Failure">Lighting Failure</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Linked Utility Asset (Optional)</label>
                    <select name="utility_asset_id" class="form-control">
                        <option value="">None / Unlinked Asset</option>
                        <?php foreach ($assetsList as $ast): ?>
                            <option value="<?php echo $ast['id']; ?>">
                                <?php echo htmlspecialchars($ast['name'] . ' ('.$ast['asset_id'].')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-top:15px;">
                    <label>Incident Description *</label>
                    <textarea name="description" class="form-control" rows="3" required placeholder="Describe the failure, e.g. Water pump failed causing low pressure at toilets."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Log Incident</button>
            </div>
        </form>
    </div>
</div>

<script>
    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
    }

    function openCreateModal() {
        document.getElementById('createModal').classList.add('open');
    }
</script>

</body>
</html>
