<?php
// incidents_list.php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'] ?? 1;

$error = '';
$success = '';

// Handle POST actions (Verify, Forward, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'verify') {
        $id = intval($_POST['id'] ?? 0);
        $category_id = intval($_POST['category_id'] ?? 0);
        $asset_id = intval($_POST['asset_id'] ?? 0);
        $priority = $_POST['priority'] ?? 'Medium';
        $status = $_POST['status'] ?? 'Verified';
        $notes = trim($_POST['verification_notes'] ?? '');

        if ($id > 0 && $category_id > 0) {
            try {
                // Get old status
                $old = $pdo->prepare("SELECT status, category_id, priority, incident_id, resident_id FROM utility_incidents WHERE id = ?");
                $old->execute([$id]);
                $curr = $old->fetch();
                
                if ($curr) {
                    // Update incident details
                    $stmt = $pdo->prepare("
                        UPDATE utility_incidents 
                        SET category_id = ?, priority = ?, status = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$category_id, $priority, $status, $id]);

                    // Update/Insert asset link
                    if ($asset_id > 0) {
                        $pdo->prepare("DELETE FROM incident_asset_links WHERE utility_incident_id = ?")->execute([$id]);
                        $pdo->prepare("INSERT INTO incident_asset_links (utility_incident_id, utility_asset_id) VALUES (?, ?)")->execute([$id, $asset_id]);
                    } else {
                        $pdo->prepare("DELETE FROM incident_asset_links WHERE utility_incident_id = ?")->execute([$id]);
                    }

                    // Log status logs
                    $pdo->prepare("
                        INSERT INTO incident_status_logs (utility_incident_id, old_status, new_status, changed_by, notes) 
                        VALUES (?, ?, ?, ?, ?)
                    ")->execute([$id, $curr['status'], $status, $userId, $notes ?: 'Incident details validated and updated by Administrator.']);

                    // Notify Resident
                    $pdo->prepare("
                        INSERT INTO incident_notifications (user_id, message) 
                        VALUES (?, ?)
                    ")->execute([$curr['resident_id'], "Status Update: Your report {$curr['incident_id']} has been marked as {$status} (Priority: {$priority})."]);

                    // Emergency notification trigger if emergency
                    if ($priority === 'Emergency') {
                        $pdo->prepare("
                            INSERT INTO incident_notifications (user_id, message) 
                            VALUES (1, ?)
                        ")->execute(["EMERGENCY ALERT: Incident {$curr['incident_id']} priority set to Emergency."]);
                    }

                    $success = "Incident {$curr['incident_id']} successfully updated!";
                }
            } catch (PDOException $e) {
                $error = "Failed to update incident: " . $e->getMessage();
            }
        }
    } elseif ($action === 'forward') {
        $id = intval($_POST['id'] ?? 0);
        $target_system = trim($_POST['target_system'] ?? '');

        if ($id > 0 && !empty($target_system)) {
            try {
                $stmt = $pdo->prepare("SELECT incident_id, status, resident_id FROM utility_incidents WHERE id = ?");
                $stmt->execute([$id]);
                $curr = $stmt->fetch();

                if ($curr) {
                    $newStatus = 'Forwarded to Maintenance System';
                    
                    // Update status
                    $pdo->prepare("UPDATE utility_incidents SET status = ? WHERE id = ?")->execute([$newStatus, $id]);

                    // Insert forwarding logs
                    $pdo->prepare("
                        INSERT INTO incident_forwarding_logs (utility_incident_id, target_system, forwarded_by, status) 
                        VALUES (?, ?, ?, 'Sent')
                    ")->execute([$id, $target_system, $userId]);

                    // Insert status log
                    $pdo->prepare("
                        INSERT INTO incident_status_logs (utility_incident_id, old_status, new_status, changed_by, notes) 
                        VALUES (?, ?, ?, ?, ?)
                    ")->execute([$id, $curr['status'], $newStatus, $userId, "Incident forwarded to {$target_system}."]);

                    // Notify Resident
                    $pdo->prepare("
                        INSERT INTO incident_notifications (user_id, message) 
                        VALUES (?, ?)
                    ")->execute([$curr['resident_id'], "Dispatched: Your report {$curr['incident_id']} has been forwarded to {$target_system} for resolution."]);

                    $success = "Incident {$curr['incident_id']} successfully forwarded to {$target_system}!";
                }
            } catch (PDOException $e) {
                $error = "Failed to forward incident: " . $e->getMessage();
            }
        }
    }
}

// ------------------------------------------------------------------------
// Get Search / Filter / Pagination parameters
// ------------------------------------------------------------------------
$search = trim($_GET['search'] ?? '');
$category_filter = !empty($_GET['category_id']) ? intval($_GET['category_id']) : null;
$status_filter = !empty($_GET['status']) ? trim($_GET['status']) : null;
$priority_filter = !empty($_GET['priority']) ? trim($_GET['priority']) : null;

// Pagination configuration
$limit = 10;
$page = !empty($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Build conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(i.description LIKE ? OR i.incident_id LIKE ? OR i.location LIKE ?)";
    $searchWildcard = '%' . $search . '%';
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
}

if ($category_filter) {
    $conditions[] = "i.category_id = ?";
    $params[] = $category_filter;
}

if ($status_filter) {
    $conditions[] = "i.status = ?";
    $params[] = $status_filter;
}

if ($priority_filter) {
    $conditions[] = "i.priority = ?";
    $params[] = $priority_filter;
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Retrieve count for pagination
$countQuery = "SELECT COUNT(*) FROM utility_incidents i $whereClause";
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Retrieve incidents
$query = "
    SELECT i.*, c.name as category_name, link.utility_asset_id, a.asset_id as asset_code, a.name as asset_name
    FROM utility_incidents i 
    JOIN incident_categories c ON i.category_id = c.id 
    LEFT JOIN incident_asset_links link ON i.id = link.utility_incident_id
    LEFT JOIN utility_assets a ON link.utility_asset_id = a.id
    $whereClause
    ORDER BY i.created_at DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$incidentsList = $stmt->fetchAll();

// Retrieve all categories & assets for form modal selectors
$categoriesList = $pdo->query("SELECT * FROM incident_categories ORDER BY name ASC")->fetchAll();
$assetsList = $pdo->query("SELECT id, name, asset_id FROM utility_assets ORDER BY asset_id ASC")->fetchAll();

// API endpoint for fetching logs dynamically via AJAX inside history modal
if (isset($_GET['fetch_logs_id'])) {
    header('Content-Type: application/json');
    $stmt = $pdo->prepare("
        SELECT l.*, u.full_name as user_name 
        FROM incident_status_logs l 
        LEFT JOIN users u ON l.changed_by = u.id 
        WHERE l.utility_incident_id = ? 
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
    <title>Incident Management Center</title>
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
            min-width: 160px;
        }

        .form-group label {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
        }

        .form-control {
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 14px;
            outline: none;
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

        .table-container {
            overflow-x: auto;
            width: 100%;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th {
            background: #f8f9fa;
            color: #475569;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            padding: 12px 16px;
            border-bottom: 2px solid #e2e8f0;
        }

        td {
            padding: 14px 16px;
            border-bottom: 1px solid #edf2f7;
            font-size: 14px;
            color: #2c3e50;
        }

        tr:hover td {
            background: #fcfcfc;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 99px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-submitted { background: #e0f2fe; color: #0284c7; }
        .badge-underreview { background: #fef3c7; color: #d97706; }
        .badge-verified { background: #e0e7ff; color: #4f46e5; }
        .badge-forwarded { background: #fae8ff; color: #c084fc; }
        .badge-resolved { background: #d1fae5; color: #065f46; }

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

        .btn-icon-verify { background: #e0e7ff; color: #4f46e5; }
        .btn-icon-verify:hover { background: #c7d2fe; }

        .btn-icon-forward { background: #fae8ff; color: #c084fc; }
        .btn-icon-forward:hover { background: #f3d8ff; }

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

        .modal.open {
            display: flex;
        }

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

        .modal-header {
            padding: 20px 24px;
            background: #f8f9fa;
            border-bottom: 1px solid #edf2f7;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 { font-size: 18px; color: #2c3e50; }
        .modal-close { background: transparent; border: none; font-size: 18px; cursor: pointer; color: #64748b; }
        .modal-body { padding: 24px; max-height: 75vh; overflow-y: auto; }
        .modal-footer { padding: 16px 24px; background: #f8f9fa; border-top: 1px solid #edf2f7; display: flex; justify-content: flex-end; gap: 12px; }

        .timeline {
            position: relative;
            padding-left: 25px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 6px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #cbd5e1;
        }

        .timeline-log-item {
            position: relative;
            margin-bottom: 20px;
        }

        .timeline-log-item::before {
            content: '';
            position: absolute;
            left: -23px;
            top: 5px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #3762c8;
            border: 2px solid white;
        }

        /* Pagination */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
        }

        .pagination-info { font-size: 13px; color: #64748b; }
        .pagination-links { display: flex; gap: 6px; }
        .page-link {
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            text-decoration: none;
            color: #64748b;
            font-size: 13px;
            font-weight: 500;
        }
        .page-link:hover { border-color: #3762c8; color: #3762c8; background: #f8fafc; }
        .page-link.active { background: #3762c8; color: white; border-color: #3762c8; }
    </style>
</head>
<body>

<?php include 'includes/utilities_sidebar.php'; ?>

<main class="main-content" id="mainContent">
    <div class="card">
        
        <!-- Header -->
        <div class="dashboard-header">
            <div>
                <h1><i class="fas fa-bullhorn"></i> Incident Reports Management</h1>
                <p style="color: #64748b; font-size: 14px; margin-top: 5px;">Validate citizen complaints, link reports to assets, and forward to LGU maintenance systems.</p>
            </div>
            <div>
                <a href="incidents_dashboard.php" class="btn btn-outline"><i class="fas fa-chevron-left"></i> Dashboard</a>
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
                <label>Search Report</label>
                <input type="text" name="search" class="form-control" placeholder="Search description, ID, location..." value="<?php echo htmlspecialchars($search); ?>">
            </div>

            <div class="form-group">
                <label>Category</label>
                <select name="category_id" class="form-control">
                    <option value="">All Categories</option>
                    <?php foreach ($categoriesList as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="">All Statuses</option>
                    <option value="Submitted" <?php echo $status_filter === 'Submitted' ? 'selected' : ''; ?>>Submitted</option>
                    <option value="Under Review" <?php echo $status_filter === 'Under Review' ? 'selected' : ''; ?>>Under Review</option>
                    <option value="Verified" <?php echo $status_filter === 'Verified' ? 'selected' : ''; ?>>Verified</option>
                    <option value="Forwarded to Maintenance System" <?php echo $status_filter === 'Forwarded to Maintenance System' ? 'selected' : ''; ?>>Forwarded</option>
                    <option value="Resolved" <?php echo $status_filter === 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
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
                <a href="incidents_list.php" class="btn btn-outline">Reset</a>
            </div>
        </form>

        <!-- Incidents List Table -->
        <div class="table-section">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Report ID</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Linked Asset</th>
                            <th>Location</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($incidentsList)): ?>
                            <tr><td colspan="8" style="text-align:center; padding:30px; color:#64748b;">No reported incidents match filters.</td></tr>
                        <?php else: ?>
                            <?php foreach ($incidentsList as $inc): 
                                $statusBadge = strtolower(str_replace([' ', 'to', 'System'], '', $inc['status']));
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($inc['incident_id']); ?></strong></td>
                                <td><?php echo htmlspecialchars($inc['category_name']); ?></td>
                                <td><?php echo htmlspecialchars(substr($inc['description'], 0, 45)) . (strlen($inc['description']) > 45 ? '...' : ''); ?></td>
                                <td><span class="badge badge-<?php echo $statusBadge; ?>"><?php echo htmlspecialchars($inc['status']); ?></span></td>
                                <td><span class="badge badge-<?php echo strtolower($inc['priority']); ?>"><?php echo htmlspecialchars($inc['priority']); ?></span></td>
                                <td><?php echo $inc['asset_code'] ? htmlspecialchars($inc['asset_code']) : '<em style="color:#94a3b8;">None</em>'; ?></td>
                                <td><?php echo htmlspecialchars($inc['location']); ?></td>
                                <td style="text-align:right; white-space:nowrap;">
                                    <button class="btn-icon btn-icon-verify" onclick='openVerifyModal(<?php echo json_encode($inc); ?>)' title="Verify & Link Asset"><i class="fas fa-check-circle"></i></button>
                                    <button class="btn-icon btn-icon-forward" onclick="openForwardModal(<?php echo $inc['id']; ?>, '<?php echo htmlspecialchars($inc['incident_id']); ?>')" title="Forward Dispatch"><i class="fas fa-paper-plane"></i></button>
                                    <button class="btn-icon btn-icon-history" onclick="viewTimeline(<?php echo $inc['id']; ?>, '<?php echo htmlspecialchars($inc['incident_id']); ?>')" title="View Timeline Log"><i class="fas fa-history"></i></button>
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
                    Showing <?php echo $offset + 1; ?> to <?php echo min($totalRecords, $offset + $limit); ?> of <?php echo $totalRecords; ?> incident reports
                </div>
                <div class="pagination-links">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="incidents_list.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category_id=<?php echo $category_filter; ?>&status=<?php echo urlencode($status_filter); ?>&priority=<?php echo urlencode($priority_filter); ?>" class="page-link <?php echo $page == $i ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<!-- VERIFY & LINK MODAL -->
<div id="verifyModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Verify Incident Report & Link Asset</h3>
            <button class="modal-close" onclick="closeModal('verifyModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="verify">
            <input type="hidden" id="verify-id" name="id">
            <div class="modal-body">
                <div style="background:#f8f9fa; padding:15px; border-radius:8px; margin-bottom:15px; font-size:13px; color:#475569;">
                    <strong>Resident Complaint Description:</strong>
                    <p id="verify-desc-preview" style="margin-top:5px; font-style:italic;"></p>
                </div>

                <div class="form-group">
                    <label>Incident Category</label>
                    <select id="verify-category" name="category_id" class="form-control" required>
                        <?php foreach ($categoriesList as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Associated Utility Asset</label>
                    <select id="verify-asset" name="asset_id" class="form-control">
                        <option value="0">No Linked Asset (Unidentified Location)</option>
                        <?php foreach ($assetsList as $ast): ?>
                            <option value="<?php echo $ast['id']; ?>">
                                <?php echo htmlspecialchars($ast['name'] . ' ('.$ast['asset_id'].')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Priority Classification</label>
                        <select id="verify-priority" name="priority" class="form-control" required>
                            <option value="Low">Low</option>
                            <option value="Medium">Medium</option>
                            <option value="High">High</option>
                            <option value="Emergency">Emergency</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Verification Status</label>
                        <select id="verify-status" name="status" class="form-control" required>
                            <option value="Submitted">Submitted</option>
                            <option value="Under Review">Under Review</option>
                            <option value="Verified">Verified</option>
                            <option value="Resolved">Resolved (Resolved / Closed)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Validation Notes / Action Comments</label>
                    <textarea name="verification_notes" class="form-control" rows="2" placeholder="e.g. Asset matched correctly. Valid report. Priority updated to Emergency due to safety hazard."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('verifyModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Verification</button>
            </div>
        </form>
    </div>
</div>

<!-- FORWARD MODAL -->
<div id="forwardModal" class="modal">
    <div class="modal-content" style="max-width: 480px;">
        <div class="modal-header">
            <h3>Forward to External LGU System</h3>
            <button class="modal-close" onclick="closeModal('forwardModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="forward">
            <input type="hidden" id="forward-id" name="id">
            <div class="modal-body">
                <p style="font-size:13px; color:#64748b; margin-bottom:15px;">Forward incident <strong id="forward-incident-id-text"></strong> to the appropriate department or system queue. This updates status to 'Forwarded to Maintenance System'.</p>
                
                <div class="form-group">
                    <label>Select LGU Dispatch Target System</label>
                    <select name="target_system" class="form-control" required>
                        <option value="Maintenance Management System">Maintenance Management System (Repair teams)</option>
                        <option value="Road Monitoring System">Road Monitoring System (Street damages affecting utilities)</option>
                        <option value="Infrastructure Management System">Infrastructure Management System (Structural hazards)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('forwardModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Forward Dispatch</button>
            </div>
        </form>
    </div>
</div>

<!-- TIMELINE HISTORY MODAL -->
<div id="historyModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Incident Status Log Timeline</h3>
            <button class="modal-close" onclick="closeModal('historyModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div id="timeline-loading" style="text-align:center; padding:20px; color:#64748b;">Loading timeline logs...</div>
            <div class="timeline" id="timeline-container" style="display:none;">
                <!-- Filled dynamically via JS fetch -->
            </div>
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

    function openVerifyModal(inc) {
        document.getElementById('verify-id').value = inc.id;
        document.getElementById('verify-desc-preview').textContent = inc.description;
        document.getElementById('verify-category').value = inc.category_id;
        document.getElementById('verify-asset').value = inc.utility_asset_id || 0;
        document.getElementById('verify-priority').value = inc.priority;
        document.getElementById('verify-status').value = inc.status;
        document.getElementById('verifyModal').classList.add('open');
    }

    function openForwardModal(id, incidentId) {
        document.getElementById('forward-id').value = id;
        document.getElementById('forward-incident-id-text').textContent = incidentId;
        document.getElementById('forwardModal').classList.add('open');
    }

    function viewTimeline(id, incidentId) {
        const loading = document.getElementById('timeline-loading');
        const container = document.getElementById('timeline-container');
        
        loading.style.display = 'block';
        container.style.display = 'none';
        container.innerHTML = '';
        
        document.getElementById('historyModal').classList.add('open');

        // Fetch logs using AJAX
        fetch(`incidents_list.php?fetch_logs_id=${id}`)
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
