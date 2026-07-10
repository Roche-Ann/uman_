<?php
// maintenance_list.php
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
        $source = $_POST['source'] ?? 'Asset Monitoring';
        $description = trim($_POST['description'] ?? '');
        $priority = $_POST['priority'] ?? 'Medium';
        $location = trim($_POST['location'] ?? '');
        $status = 'Created';

        if (empty($description) || empty($location)) {
            $error = 'Please fill in all required fields (Description and Location).';
        } else {
            try {
                // Generate Unique ID
                $prefix = 'MNT-' . date('Ym') . '-';
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM maintenance_requests WHERE request_id LIKE ?");
                $stmt->execute([$prefix . '%']);
                $count = $stmt->fetchColumn() + 1;
                $request_id = $prefix . str_pad($count, 4, '0', STR_PAD_LEFT);

                // Insert request
                $stmt = $pdo->prepare("
                    INSERT INTO maintenance_requests (request_id, utility_asset_id, source, description, priority, location, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$request_id, $asset_id, $source, $description, $priority, $location, $status]);
                $mrid = $pdo->lastInsertId();

                // Log status
                $pdo->prepare("
                    INSERT INTO maintenance_status_logs (maintenance_request_id, old_status, new_status, changed_by, notes) 
                    VALUES (?, NULL, ?, ?, 'Request created manually by Administrator.')
                ")->execute([$mrid, $status, $userId]);

                // Asset link
                if ($asset_id) {
                    $pdo->prepare("INSERT INTO maintenance_asset_links (maintenance_request_id, utility_asset_id) VALUES (?, ?)")->execute([$mrid, $asset_id]);
                }

                // Log history
                $pdo->prepare("
                    INSERT INTO maintenance_history (maintenance_request_id, action, performed_by, details) 
                    VALUES (?, 'Request Created', ?, ?)
                ")->execute([$mrid, $userId, "Coordination request manually created from {$source} source."]);

                // Notify admin
                $pdo->prepare("
                    INSERT INTO maintenance_notifications (user_id, message) 
                    VALUES (1, ?)
                ")->execute(["New maintenance request {$request_id} created."]);

                $success = "Maintenance Request {$request_id} successfully created!";
            } catch (PDOException $e) {
                $error = "Failed to create request: " . $e->getMessage();
            }
        }
    } elseif ($action === 'forward') {
        $id = intval($_POST['id'] ?? 0);
        $target = $_POST['target_system'] ?? 'Maintenance System';
        $simulate_response = $_POST['simulate_response'] ?? 'Accepted';

        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("SELECT request_id, status, utility_asset_id FROM maintenance_requests WHERE id = ?");
                $stmt->execute([$id]);
                $req = $stmt->fetch();

                if ($req) {
                    $extRef = ($simulate_response === 'Accepted') ? 'EXT-WO-' . rand(1000, 9999) : null;
                    $newStatus = ($simulate_response === 'Accepted') ? 'Accepted by Maintenance System' : 'Created';
                    $forwardStatus = ($simulate_response === 'Accepted') ? 'Accepted' : 'Rejected';

                    // Update request status
                    $pdo->prepare("UPDATE maintenance_requests SET status = ? WHERE id = ?")->execute([$newStatus, $id]);

                    // Insert forwarding logs
                    $pdo->prepare("
                        INSERT INTO maintenance_forwarding_logs (maintenance_request_id, target_system, external_ref_id, status) 
                        VALUES (?, ?, ?, ?)
                    ")->execute([$id, $target, $extRef, $forwardStatus]);

                    // Log status change
                    $pdo->prepare("
                        INSERT INTO maintenance_status_logs (maintenance_request_id, old_status, new_status, changed_by, notes) 
                        VALUES (?, ?, ?, ?, ?)
                    ")->execute([$id, $req['status'], $newStatus, $userId, "Dispatched to external system. Response received: {$forwardStatus}. " . ($extRef ? "External Ref: {$extRef}." : "")]);

                    // Log history
                    $pdo->prepare("
                        INSERT INTO maintenance_history (maintenance_request_id, action, performed_by, details) 
                        VALUES (?, 'Request Forwarded', ?, ?)
                    ")->execute([$id, $userId, "Forwarded to {$target}. System returned {$forwardStatus}."]);

                    // Notify resident
                    $pdo->prepare("
                        INSERT INTO maintenance_notifications (user_id, message) 
                        VALUES (3, ?)
                    ")->execute(["Status Update: Maintenance request {$req['request_id']} has been forwarded and {$forwardStatus}."]);

                    $success = "Request {$req['request_id']} forwarded. Response: {$forwardStatus}.";
                }
            } catch (PDOException $e) {
                $error = "Failed to forward request: " . $e->getMessage();
            }
        }
    } elseif ($action === 'simulate_update') {
        $id = intval($_POST['id'] ?? 0);
        $new_status = $_POST['status'] ?? 'In Progress';
        $remarks = trim($_POST['remarks'] ?? '');

        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("SELECT request_id, status FROM maintenance_requests WHERE id = ?");
                $stmt->execute([$id]);
                $req = $stmt->fetch();

                if ($req) {
                    // Update status
                    $pdo->prepare("UPDATE maintenance_requests SET status = ? WHERE id = ?")->execute([$new_status, $id]);

                    // Log status change
                    $pdo->prepare("
                        INSERT INTO maintenance_status_logs (maintenance_request_id, old_status, new_status, changed_by, notes) 
                        VALUES (?, ?, ?, ?, ?)
                    ")->execute([$id, $req['status'], $new_status, $userId, "Inbound update from external Maintenance System: " . ($remarks ?: "No remarks.")]);

                    // Log history
                    $pdo->prepare("
                        INSERT INTO maintenance_history (maintenance_request_id, action, performed_by, details) 
                        VALUES (?, 'Inbound Update', ?, ?)
                    ")->execute([$id, $userId, "Status changed to {$new_status} by Maintenance System. Remarks: {$remarks}"]);

                    // Notify resident
                    $notifMsg = "Status Update: Maintenance request {$req['request_id']} is now {$new_status}.";
                    if ($new_status === 'Completed') {
                        $notifMsg = "Success: Maintenance request {$req['request_id']} has been Completed by the technicians.";
                    }
                    $pdo->prepare("
                        INSERT INTO maintenance_notifications (user_id, message) 
                        VALUES (3, ?)")->execute([$notifMsg]);

                    $success = "Inbound status update simulated successfully!";
                }
            } catch (PDOException $e) {
                $error = "Failed to update request: " . $e->getMessage();
            }
        }
    }
}

// ------------------------------------------------------------------------
// Get Search / Filter / Pagination parameters
// ------------------------------------------------------------------------
$search = trim($_GET['search'] ?? '');
$source_filter = !empty($_GET['source']) ? trim($_GET['source']) : null;
$status_filter = !empty($_GET['status']) ? trim($_GET['status']) : null;
$priority_filter = !empty($_GET['priority']) ? trim($_GET['priority']) : null;

// Pagination configuration
$limit = 10;
$page = !empty($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(r.description LIKE ? OR r.request_id LIKE ? OR r.location LIKE ?)";
    $searchWildcard = '%' . $search . '%';
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
}

if ($source_filter) {
    $conditions[] = "r.source = ?";
    $params[] = $source_filter;
}

if ($status_filter) {
    $conditions[] = "r.status = ?";
    $params[] = $status_filter;
}

if ($priority_filter) {
    $conditions[] = "r.priority = ?";
    $params[] = $priority_filter;
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Retrieve count for pagination
$countQuery = "SELECT COUNT(*) FROM maintenance_requests r $whereClause";
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Retrieve requests list
$query = "
    SELECT r.*, a.asset_id as asset_code, a.name as asset_name
    FROM maintenance_requests r 
    LEFT JOIN utility_assets a ON r.utility_asset_id = a.id 
    $whereClause
    ORDER BY r.created_at DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$requestsList = $stmt->fetchAll();

// Retrieve all assets for form dropdown
$assetsList = $pdo->query("SELECT id, name, asset_id FROM utility_assets ORDER BY asset_id ASC")->fetchAll();

// API endpoint for fetching logs dynamically
if (isset($_GET['fetch_logs_id'])) {
    header('Content-Type: application/json');
    $stmt = $pdo->prepare("
        SELECT l.*, u.full_name as user_name 
        FROM maintenance_status_logs l 
        LEFT JOIN users u ON l.changed_by = u.id 
        WHERE l.maintenance_request_id = ? 
        ORDER BY l.changed_at ASC
    ");
    $stmt->execute([intval($_GET['fetch_logs_id'])]);
    echo json_encode($stmt->fetchAll());
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Requests Coordination</title>
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

        .dashboard-header h1 i {
            color: #3762c8;
        }

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
            min-width: 150px;
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

        .btn-warning { background: #f1c40f; color: white; }
        .btn-warning:hover { background: #d39e00; }

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

        .badge-created { background: #f1f5f9; color: #64748b; }
        .badge-forwarded { background: #fae8ff; color: #c084fc; }
        .badge-accepted { background: #e0e7ff; color: #4f46e5; }
        .badge-progress { background: #e0f2fe; color: #0284c7; }
        .badge-completed { background: #e2fbe8; color: #1e7e34; }

        .badge-low { background: #e2fbe8; color: #1e7e34; }
        .badge-medium { background: #e0f2fe; color: #0284c7; }
        .badge-high { background: #fff4e5; color: #b45309; }
        .badge-emergency { background: #fde8e8; color: #bd2130; }

        .btn-icon {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.2s;
        }
        .btn-icon-forward { background: #fae8ff; color: #c084fc; }
        .btn-icon-forward:hover { background: #f3d8ff; }
        .btn-icon-update { background: #e0f2fe; color: #0284c7; }
        .btn-icon-update:hover { background: #bae6fd; }
        .btn-icon-history { background: #f1f5f9; color: #64748b; }
        .btn-icon-history:hover { background: #cbd5e1; }

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

        .timeline { position: relative; padding-left: 25px; }
        .timeline::before { content: ''; position: absolute; left: 6px; top: 0; bottom: 0; width: 2px; background: #cbd5e1; }
        .timeline-log-item { position: relative; margin-bottom: 20px; }
        .timeline-log-item::before { content: ''; position: absolute; left: -23px; top: 5px; width: 10px; height: 10px; border-radius: 50%; background: #3762c8; border: 2px solid white; }

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
                <h1><i class="fas fa-tools"></i> Coordinate Maintenance Requests</h1>
                <p style="color: #64748b; font-size: 14px; margin-top: 5px;">Manage outbound dispatches, link asset databases, and monitor repair status milestones.</p>
            </div>
            <div>
                <button class="btn btn-primary" onclick="openCreateModal()"><i class="fas fa-plus"></i> Log Request</button>
                <a href="maintenance_dashboard.php" class="btn btn-outline"><i class="fas fa-chevron-left"></i> Dashboard</a>
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
                <input type="text" name="search" class="form-control" placeholder="Search description, ID, location..." value="<?php echo htmlspecialchars($search); ?>">
            </div>

            <div class="form-group">
                <label>Source</label>
                <select name="source" class="form-control">
                    <option value="">All Sources</option>
                    <option value="Resident Report" <?php echo $source_filter === 'Resident Report' ? 'selected' : ''; ?>>Resident Report</option>
                    <option value="Asset Monitoring" <?php echo $source_filter === 'Asset Monitoring' ? 'selected' : ''; ?>>Asset Monitoring</option>
                    <option value="Emergency Alert" <?php echo $source_filter === 'Emergency Alert' ? 'selected' : ''; ?>>Emergency Alert</option>
                </select>
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="">All Statuses</option>
                    <option value="Created" <?php echo $status_filter === 'Created' ? 'selected' : ''; ?>>Created</option>
                    <option value="Forwarded" <?php echo $status_filter === 'Forwarded' ? 'selected' : ''; ?>>Forwarded</option>
                    <option value="Accepted by Maintenance System" <?php echo $status_filter === 'Accepted by Maintenance System' ? 'selected' : ''; ?>>Accepted</option>
                    <option value="In Progress" <?php echo $status_filter === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                    <option value="Completed" <?php echo $status_filter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                </select>
            </div>

            <div class="form-group">
                <label>Priority</label>
                <select name="priority" class="form-control">
                    <option value="">All Priorities</option>
                    <option value="Low" <?php echo $priority_filter === 'Low' ? 'selected' : ''; ?>>Low</option>
                    <option value="Medium" <?php echo $priority_filter === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                    <option value="High" <?php echo $priority_filter === 'High' ? 'selected' : ''; ?>>High</option>
                    <option value="Emergency" <?php echo $priority_filter === 'Emergency' ? 'selected' : ''; ?>>Emergency</option>
                </select>
            </div>

            <div style="display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
                <a href="maintenance_list.php" class="btn btn-outline">Reset</a>
            </div>
        </form>

        <!-- Requests List Table -->
        <div class="table-section">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>Source</th>
                            <th>Description</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Linked Asset</th>
                            <th>Location</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($requestsList)): ?>
                            <tr><td colspan="8" style="text-align:center; padding:30px; color:#64748b;">No maintenance requests match filters.</td></tr>
                        <?php else: ?>
                            <?php foreach ($requestsList as $req): 
                                $statusBadge = strtolower(str_replace([' ', 'by', 'Maintenance', 'System'], '', $req['status']));
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($req['request_id']); ?></strong></td>
                                <td><?php echo htmlspecialchars($req['source']); ?></td>
                                <td><?php echo htmlspecialchars(substr($req['description'], 0, 40)) . (strlen($req['description']) > 40 ? '...' : ''); ?></td>
                                <td><span class="badge badge-<?php echo strtolower($req['priority']); ?>"><?php echo htmlspecialchars($req['priority']); ?></span></td>
                                <td><span class="badge badge-<?php echo $statusBadge; ?>"><?php echo htmlspecialchars($req['status']); ?></span></td>
                                <td><?php echo $req['asset_code'] ? htmlspecialchars($req['asset_code']) : '<em style="color:#94a3b8;">None</em>'; ?></td>
                                <td><?php echo htmlspecialchars($req['location']); ?></td>
                                <td style="text-align:right; white-space:nowrap;">
                                    <button class="btn-icon btn-icon-forward" onclick="openForwardModal(<?php echo $req['id']; ?>, '<?php echo htmlspecialchars($req['request_id']); ?>')" title="Dispatch Outbound"><i class="fas fa-paper-plane"></i></button>
                                    <button class="btn-icon btn-icon-update" onclick="openSimulateModal(<?php echo $req['id']; ?>, '<?php echo htmlspecialchars($req['request_id']); ?>')" title="Simulate Inbound Status Update"><i class="fas fa-cogs"></i></button>
                                    <button class="btn-icon btn-icon-history" onclick="viewTimeline(<?php echo $req['id']; ?>, '<?php echo htmlspecialchars($req['request_id']); ?>')" title="View Log Timeline"><i class="fas fa-history"></i></button>
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
                    Showing <?php echo $offset + 1; ?> to <?php echo min($totalRecords, $offset + $limit); ?> of <?php echo $totalRecords; ?> requests
                </div>
                <div class="pagination-links">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="maintenance_list.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&source=<?php echo urlencode($source_filter); ?>&status=<?php echo urlencode($status_filter); ?>&priority=<?php echo urlencode($priority_filter); ?>" class="page-link <?php echo $page == $i ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<!-- CREATE REQUEST MODAL -->
<div id="createModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Log Utility Maintenance Request</h3>
            <button class="modal-close" onclick="closeModal('createModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group" style="margin-bottom:15px;">
                    <label>Issue Description *</label>
                    <textarea name="description" class="form-control" rows="3" required placeholder="Details about repair issue..."></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Linked Utility Asset *</label>
                        <select name="utility_asset_id" class="form-control" required>
                            <option value="">Select Asset</option>
                            <?php foreach ($assetsList as $ast): ?>
                                <option value="<?php echo $ast['id']; ?>">
                                    <?php echo htmlspecialchars($ast['name'] . ' ('.$ast['asset_id'].')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Source Identification *</label>
                        <select name="source" class="form-control" required>
                            <option value="Asset Monitoring">Asset Monitoring</option>
                            <option value="Resident Report">Resident Report</option>
                            <option value="Emergency Alert">Emergency Alert</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Priority Level *</label>
                        <select name="priority" class="form-control" required>
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                            <option value="Emergency">Emergency</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Location Landmark Address *</label>
                        <input type="text" name="location" class="form-control" required placeholder="e.g. Rizal Ave near Recto">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Request</button>
            </div>
        </form>
    </div>
</div>

<!-- FORWARD MODAL -->
<div id="forwardModal" class="modal">
    <div class="modal-content" style="max-width: 480px;">
        <div class="modal-header">
            <h3>Forward Dispatch to External Maintenance Queue</h3>
            <button class="modal-close" onclick="closeModal('forwardModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="forward">
            <input type="hidden" id="forward-id" name="id">
            <div class="modal-body">
                <p style="font-size:13px; color:#64748b; margin-bottom:15px;">Dispatch request <strong id="forward-request-id-text"></strong> to the external Maintenance System API queue.</p>
                
                <div class="form-group">
                    <label>Target System</label>
                    <input type="text" name="target_system" class="form-control" value="Maintenance System" readonly>
                </div>
                
                <div class="form-group" style="margin-top:15px;">
                    <label>Simulate External API Response</label>
                    <select name="simulate_response" class="form-control">
                        <option value="Accepted">Simulate API 'Accepted' (Generates WO reference ID)</option>
                        <option value="Rejected">Simulate API 'Rejected' (Return error / queue full)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('forwardModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Dispatch Forward</button>
            </div>
        </form>
    </div>
</div>

<!-- SIMULATE INBOUND UPDATE MODAL -->
<div id="simulateModal" class="modal">
    <div class="modal-content" style="max-width: 480px;">
        <div class="modal-header">
            <h3>Simulate Inbound Status Update</h3>
            <button class="modal-close" onclick="closeModal('simulateModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="simulate_update">
            <input type="hidden" id="simulate-id" name="id">
            <div class="modal-body">
                <p style="font-size:13px; color:#64748b; margin-bottom:15px;">Simulate an inbound Webhook update status payload from the external Maintenance Management System for request <strong id="simulate-request-id-text"></strong>.</p>
                
                <div class="form-group">
                    <label>Select Status Update</label>
                    <select name="status" class="form-control" required>
                        <option value="Accepted by Maintenance System">Accepted by Maintenance System</option>
                        <option value="In Progress">In Progress (Technician dispatched / working)</option>
                        <option value="Completed">Completed (Maintenance solved)</option>
                        <option value="Closed">Closed (Archived)</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-top:15px;">
                    <label>Technician Remarks / Status Notes (Read-Only)</label>
                    <input type="text" name="remarks" class="form-control" placeholder="e.g. Swapped LED bulb / Pipe leak sealed and tested.">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('simulateModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Simulate Webhook</button>
            </div>
        </form>
    </div>
</div>

<!-- TIMELINE HISTORY MODAL -->
<div id="historyModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Request Coordination Timeline</h3>
            <button class="modal-close" onclick="closeModal('historyModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div id="timeline-loading" style="text-align:center; padding:20px; color:#64748b;">Loading timeline logs...</div>
            <div class="timeline" id="timeline-container" style="display:none;"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" onclick="closeModal('historyModal')">Close</button>
        </div>
    </div>
</div>

<script>
    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
    }

    function openCreateModal() {
        document.getElementById('createModal').classList.add('open');
    }

    function openForwardModal(id, requestId) {
        document.getElementById('forward-id').value = id;
        document.getElementById('forward-request-id-text').textContent = requestId;
        document.getElementById('forwardModal').classList.add('open');
    }

    function openSimulateModal(id, requestId) {
        document.getElementById('simulate-id').value = id;
        document.getElementById('simulate-request-id-text').textContent = requestId;
        document.getElementById('simulateModal').classList.add('open');
    }

    function viewTimeline(id, requestId) {
        const loading = document.getElementById('timeline-loading');
        const container = document.getElementById('timeline-container');
        
        loading.style.display = 'block';
        container.style.display = 'none';
        container.innerHTML = '';
        
        document.getElementById('historyModal').classList.add('open');

        // Fetch logs using AJAX
        fetch(`maintenance_list.php?fetch_logs_id=${id}`)
            .then(res => res.json())
            .then(data => {
                loading.style.display = 'none';
                container.style.display = 'block';

                if (data.length === 0) {
                    container.innerHTML = '<p style="color:#64748b; font-size:13px;">No history logs found.</p>';
                    return;
                }

                data.forEach(log => {
                    const date = new Date(log.changed_at).toLocaleString('en-US', {
                        month: 'short', day: 'numeric', year: 'numeric',
                        hour: '2-digit', minute: '2-digit', hour12: true
                    });
                    
                    const item = document.createElement('div');
                    item.className = 'timeline-log-item';
                    item.innerHTML = `
                        <div style="font-weight:600; font-size:13px; color:#2c3e50;">
                            Status updated to <span class="badge" style="font-size: 8px; padding: 2px 6px; background:#e0f2fe; color:#0284c7;">${log.new_status}</span>
                        </div>
                        <div style="font-size:12px; color:#475569; margin-top:2px;">${log.notes || 'No comments.'}</div>
                        <div style="font-size:10px; color:#94a3b8; margin-top:3px;">
                            ${date} · Updated by ${log.user_name || 'System'}
                        </div>
                    `;
                    container.appendChild(item);
                });
            });
    }
</script>

</body>
</html>
