<?php
// energy_records.php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'] ?? 1;

$error = '';
$success = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $asset_id = !empty($_POST['utility_asset_id']) ? intval($_POST['utility_asset_id']) : null;
        $facility_name = trim($_POST['facility_name'] ?? '');
        $asset_type = $_POST['asset_type'] ?? 'Streetlight';
        $location = trim($_POST['location'] ?? '');
        $month_year = trim($_POST['month_year'] ?? '');
        $kwh = floatval($_POST['consumption_kwh'] ?? 0);
        $cost = !empty($_POST['cost']) ? floatval($_POST['cost']) : null;
        $source = $_POST['data_source'] ?? 'Manual Input';
        $notes = trim($_POST['notes'] ?? '');

        if (empty($location) || empty($month_year) || $kwh <= 0) {
            $error = 'Please fill in all required fields (Location, Month-Year, and positive kWh consumption).';
        } else {
            try {
                // Generate Unique ID
                $prefix = 'ENG-' . date('Ym') . '-';
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM energy_consumption_records WHERE record_id LIKE ?");
                $stmt->execute([$prefix . '%']);
                $count = $stmt->fetchColumn() + 1;
                $record_id = $prefix . str_pad($count, 4, '0', STR_PAD_LEFT);

                $stmt = $pdo->prepare("
                    INSERT INTO energy_consumption_records (record_id, utility_asset_id, facility_name, asset_type, location, month_year, consumption_kwh, cost, data_source, notes) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$record_id, $asset_id, $facility_name ?: null, $asset_type, $location, $month_year, $kwh, $cost, $source, $notes]);

                // Create alert notification
                $pdo->prepare("
                    INSERT INTO energy_notifications (message) 
                    VALUES (?)
                ")->execute(["New energy consumption record {$record_id} registered (Consumption: {$kwh} kWh)."]);

                $success = "Energy Record {$record_id} successfully created!";
            } catch (PDOException $e) {
                $error = "Failed to save record: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $pdo->prepare("DELETE FROM energy_consumption_records WHERE id = ?")->execute([$id]);
                $success = "Energy record successfully deleted!";
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
$type_filter = $_GET['asset_type'] ?? '';
$source_filter = $_GET['data_source'] ?? '';

// Pagination configuration
$limit = 10;
$page = !empty($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(r.location LIKE ? OR r.facility_name LIKE ? OR r.record_id LIKE ?)";
    $searchWildcard = '%' . $search . '%';
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
}

if ($type_filter) {
    $conditions[] = "r.asset_type = ?";
    $params[] = $type_filter;
}

if ($source_filter) {
    $conditions[] = "r.data_source = ?";
    $params[] = $source_filter;
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Retrieve count for pagination
$countQuery = "SELECT COUNT(*) FROM energy_consumption_records r $whereClause";
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Retrieve records list
$query = "
    SELECT r.*, a.asset_id as asset_code, a.name as asset_name
    FROM energy_consumption_records r 
    LEFT JOIN utility_assets a ON r.utility_asset_id = a.id 
    $whereClause
    ORDER BY r.month_year DESC, r.date_recorded DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$recordsList = $stmt->fetchAll();

// Retrieve all assets for form dropdown
$assetsList = $pdo->query("SELECT id, name, asset_id FROM utility_assets ORDER BY asset_id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Electricity Consumption Logs</title>
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

        .badge-manual { background: #e0f2fe; color: #0284c7; }
        .badge-imported { background: #e2fbe8; color: #1e7e34; }

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
                <h1><i class="fas fa-bolt"></i> Electricity Consumption Records</h1>
                <p style="color: #64748b; font-size: 14px; margin-top: 5px;">Manage raw kWh records and utility costs across assets and facilities.</p>
            </div>
            <div>
                <button class="btn btn-primary" onclick="openCreateModal()"><i class="fas fa-plus"></i> Add Log</button>
                <a href="energy_dashboard.php" class="btn btn-outline"><i class="fas fa-chevron-left"></i> Dashboard</a>
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
                <input type="text" name="search" class="form-control" placeholder="Search location, record ID, or facility..." value="<?php echo htmlspecialchars($search); ?>">
            </div>

            <div class="form-group">
                <label>Asset/Facility Type</label>
                <select name="asset_type" class="form-control">
                    <option value="">All Sectors</option>
                    <option value="Streetlight" <?php echo $type_filter === 'Streetlight' ? 'selected' : ''; ?>>Streetlight</option>
                    <option value="Public Facility" <?php echo $type_filter === 'Public Facility' ? 'selected' : ''; ?>>Public Facility</option>
                    <option value="Water Infrastructure" <?php echo $type_filter === 'Water Infrastructure' ? 'selected' : ''; ?>>Water Infrastructure</option>
                </select>
            </div>

            <div class="form-group">
                <label>Data Source</label>
                <select name="data_source" class="form-control">
                    <option value="">All Sources</option>
                    <option value="Manual Input" <?php echo $source_filter === 'Manual Input' ? 'selected' : ''; ?>>Manual Input</option>
                    <option value="Imported" <?php echo $source_filter === 'Imported' ? 'selected' : ''; ?>>Imported</option>
                </select>
            </div>

            <div style="display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
                <a href="energy_records.php" class="btn btn-outline">Reset</a>
            </div>
        </form>

        <!-- Records List Table -->
        <div class="table-section">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Record ID</th>
                            <th>Sector</th>
                            <th>Facility / Linked Asset</th>
                            <th>Period</th>
                            <th>Consumption (kWh)</th>
                            <th>Cost (PHP)</th>
                            <th>Data Source</th>
                            <th>Location</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recordsList)): ?>
                            <tr><td colspan="9" style="text-align:center; padding:30px; color:#64748b;">No energy consumption logs logged.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recordsList as $rec): 
                                $sourceBadge = ($rec['data_source'] === 'Imported') ? 'imported' : 'manual';
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($rec['record_id']); ?></strong></td>
                                <td><?php echo htmlspecialchars($rec['asset_type']); ?></td>
                                <td>
                                    <?php 
                                        if ($rec['facility_name']) {
                                            echo htmlspecialchars($rec['facility_name']);
                                        } elseif ($rec['asset_name']) {
                                            echo htmlspecialchars($rec['asset_name'] . ' ('.$rec['asset_code'].')');
                                        } else {
                                            echo '<em style="color:#94a3b8;">Unlinked Asset</em>';
                                        }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($rec['month_year']); ?></td>
                                <td><?php echo number_format($rec['consumption_kwh'], 2); ?> kWh</td>
                                <td><?php echo $rec['cost'] ? '₱' . number_format($rec['cost'], 2) : '—'; ?></td>
                                <td><span class="badge badge-<?php echo $sourceBadge; ?>"><?php echo htmlspecialchars($rec['data_source']); ?></span></td>
                                <td><?php echo htmlspecialchars($rec['location']); ?></td>
                                <td style="text-align:right;">
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this record?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $rec['id']; ?>">
                                        <button type="submit" class="btn-outline" style="border:none; cursor:pointer; color:#e74c3c;" title="Delete Record"><i class="fas fa-trash"></i> Delete</button>
                                    </form>
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
                        <a href="energy_records.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&asset_type=<?php echo urlencode($type_filter); ?>&data_source=<?php echo urlencode($source_filter); ?>" class="page-link <?php echo $page == $i ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<!-- CREATE RECORD MODAL -->
<div id="createModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Log Electricity Consumption Record</h3>
            <button class="modal-close" onclick="closeModal('createModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Asset Category Type *</label>
                        <select name="asset_type" class="form-control" required>
                            <option value="Streetlight">Streetlight</option>
                            <option value="Public Facility">Public Facility</option>
                            <option value="Water Infrastructure">Water Infrastructure</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Month and Year *</label>
                        <input type="month" name="month_year" class="form-control" required value="<?php echo date('Y-m'); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Linked Utility Asset (If applicable)</label>
                        <select name="utility_asset_id" class="form-control">
                            <option value="">None / Facility Entry</option>
                            <?php foreach ($assetsList as $ast): ?>
                                <option value="<?php echo $ast['id']; ?>">
                                    <?php echo htmlspecialchars($ast['name'] . ' ('.$ast['asset_id'].')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Facility Name (If unlinked asset)</label>
                        <input type="text" name="facility_name" class="form-control" placeholder="e.g. Quiapo Municipal Annex">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Consumption (kWh) *</label>
                        <input type="number" step="any" name="consumption_kwh" class="form-control" required placeholder="e.g. 1500.50">
                    </div>
                    <div class="form-group">
                        <label>Estimated Cost (PHP)</label>
                        <input type="number" step="any" name="cost" class="form-control" placeholder="e.g. 18000">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Location Landmark Address *</label>
                        <input type="text" name="location" class="form-control" required placeholder="e.g. Roxas Blvd, Manila">
                    </div>
                    <div class="form-group">
                        <label>Data Acquisition Source *</label>
                        <select name="data_source" class="form-control" required>
                            <option value="Manual Input">Manual Input</option>
                            <option value="Imported">Imported</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Record Notes</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Calibration details or weather notes..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Record</button>
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
