<?php
// planning_expansions.php
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
        $location = trim($_POST['area_location'] ?? '');
        $type = $_POST['utility_type'] ?? 'Water Supply';
        $reason = trim($_POST['reason'] ?? '');
        $priority = $_POST['priority'] ?? 'Medium';
        $scope = trim($_POST['estimated_scope'] ?? '');

        if (empty($location) || empty($reason)) {
            $error = 'Please fill in all required fields (Location and Reason).';
        } else {
            try {
                // Generate Unique ID
                $prefix = 'PLN-EXP-' . date('Ym') . '-';
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM utility_expansion_requests WHERE request_id LIKE ?");
                $stmt->execute([$prefix . '%']);
                $count = $stmt->fetchColumn() + 1;
                $request_id = $prefix . str_pad($count, 4, '0', STR_PAD_LEFT);

                $stmt = $pdo->prepare("
                    INSERT INTO utility_expansion_requests (request_id, area_location, utility_type, reason, priority, estimated_scope, status) 
                    VALUES (?, ?, ?, ?, ?, ?, 'Pending')
                ");
                $stmt->execute([$request_id, $location, $type, $reason, $priority, $scope]);

                // Log outbound coordination logs
                $pdo->prepare("
                    INSERT INTO planning_coordination_logs (direction, log_type, details) 
                    VALUES ('Outbound', 'Expansion Request', ?)
                ")->execute(["Dispatched service expansion proposal: {$request_id} for zone: {$location} ({$type})."]);

                $success = "Expansion Request {$request_id} successfully created!";
            } catch (PDOException $e) {
                $error = "Failed to save request: " . $e->getMessage();
            }
        }
    } elseif ($action === 'update_status') {
        $id = intval($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? 'Under Review';
        
        if ($id > 0) {
            try {
                $pdo->prepare("UPDATE utility_expansion_requests SET status = ? WHERE id = ?")->execute([$status, $id]);
                
                // Get request ID
                $stmt = $pdo->prepare("SELECT request_id FROM utility_expansion_requests WHERE id = ?");
                $stmt->execute([$id]);
                $reqId = $stmt->fetchColumn();

                $pdo->prepare("
                    INSERT INTO planning_coordination_logs (direction, log_type, details) 
                    VALUES ('Outbound', 'Expansion Update', ?)
                ")->execute(["Expansion request {$reqId} status updated to: {$status}."]);

                $success = "Request {$reqId} status successfully updated to {$status}!";
            } catch (PDOException $e) {
                $error = "Failed to update status: " . $e->getMessage();
            }
        }
    }
}

// ------------------------------------------------------------------------
// Get Search / Filter / Pagination parameters
// ------------------------------------------------------------------------
$search = trim($_GET['search'] ?? '');
$type_filter = $_GET['utility_type'] ?? '';
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';

// Pagination configuration
$limit = 10;
$page = !empty($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(area_location LIKE ? OR reason LIKE ? OR request_id LIKE ?)";
    $searchWildcard = '%' . $search . '%';
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
}

if ($type_filter) {
    $conditions[] = "utility_type = ?";
    $params[] = $type_filter;
}

if ($status_filter) {
    $conditions[] = "status = ?";
    $params[] = $status_filter;
}

if ($priority_filter) {
    $conditions[] = "priority = ?";
    $params[] = $priority_filter;
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Retrieve count for pagination
$countQuery = "SELECT COUNT(*) FROM utility_expansion_requests r $whereClause";
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Retrieve requests list
$query = "
    SELECT * FROM utility_expansion_requests r
    $whereClause
    ORDER BY created_at DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$requestsList = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Utility Expansion Requests</title>
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

        .badge-pending { background: #f1f5f9; color: #64748b; }
        .badge-underreview { background: #fff4e5; color: #b45309; }
        .badge-approved { background: #e2fbe8; color: #1e7e34; }
        .badge-deferred { background: #e0f2fe; color: #0284c7; }
        .badge-rejected { background: #fde8e8; color: #bd2130; }

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
        .btn-icon-edit { background: #e0e7ff; color: #4f46e5; }
        .btn-icon-edit:hover { background: #c7d2fe; }

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
                <h1><i class="fas fa-chart-line"></i> Utility Expansion Requests</h1>
                <p style="color: #64748b; font-size: 14px; margin-top: 5px;">Manage pipeline extensions, pole additions, and utility capacity grid expansions.</p>
            </div>
            <div>
                <button class="btn btn-primary" onclick="openCreateModal()"><i class="fas fa-plus"></i> Submit Request</button>
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

        <!-- Filters Form -->
        <form method="GET" class="filter-panel">
            <div class="form-group" style="flex:2;">
                <label>Keyword Search</label>
                <input type="text" name="search" class="form-control" placeholder="Search location, ID, reason..." value="<?php echo htmlspecialchars($search); ?>">
            </div>

            <div class="form-group">
                <label>Utility Type</label>
                <select name="utility_type" class="form-control">
                    <option value="">All Types</option>
                    <option value="Water Supply" <?php echo $type_filter === 'Water Supply' ? 'selected' : ''; ?>>Water Supply</option>
                    <option value="Streetlight" <?php echo $type_filter === 'Streetlight' ? 'selected' : ''; ?>>Streetlight</option>
                    <option value="Drainage" <?php echo $type_filter === 'Drainage' ? 'selected' : ''; ?>>Drainage</option>
                    <option value="Electrical" <?php echo $type_filter === 'Electrical' ? 'selected' : ''; ?>>Electrical</option>
                </select>
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="">All Statuses</option>
                    <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Under Review" <?php echo $status_filter === 'Under Review' ? 'selected' : ''; ?>>Under Review</option>
                    <option value="Approved" <?php echo $status_filter === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="Deferred" <?php echo $status_filter === 'Deferred' ? 'selected' : ''; ?>>Deferred</option>
                    <option value="Rejected" <?php echo $status_filter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
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
                <a href="planning_expansions.php" class="btn btn-outline">Reset</a>
            </div>
        </form>

        <!-- Requests List Table -->
        <div class="table-section">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>Location</th>
                            <th>Utility Type</th>
                            <th>Reason for expansion</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Scope Estimate</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($requestsList)): ?>
                            <tr><td colspan="8" style="text-align:center; padding:30px; color:#64748b;">No expansion requests match filters.</td></tr>
                        <?php else: ?>
                            <?php foreach ($requestsList as $req): 
                                $statusBadge = strtolower(str_replace(' ', '', $req['status']));
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($req['request_id']); ?></strong></td>
                                <td><?php echo htmlspecialchars($req['area_location']); ?></td>
                                <td><?php echo htmlspecialchars($req['utility_type']); ?></td>
                                <td><?php echo htmlspecialchars(substr($req['reason'], 0, 45)) . (strlen($req['reason']) > 45 ? '...' : ''); ?></td>
                                <td><span class="badge badge-<?php echo strtolower($req['priority']); ?>"><?php echo htmlspecialchars($req['priority']); ?></span></td>
                                <td><span class="badge badge-<?php echo $statusBadge; ?>"><?php echo htmlspecialchars($req['status']); ?></span></td>
                                <td><?php echo htmlspecialchars($req['estimated_scope']); ?></td>
                                <td style="text-align:right; white-space:nowrap;">
                                    <button class="btn-icon btn-icon-edit" onclick="openStatusModal(<?php echo $req['id']; ?>, '<?php echo htmlspecialchars($req['request_id']); ?>', '<?php echo $req['status']; ?>')" title="Review Status"><i class="fas fa-edit"></i></button>
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
                        <a href="planning_expansions.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&utility_type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>&priority=<?php echo urlencode($priority_filter); ?>" class="page-link <?php echo $page == $i ? 'active' : ''; ?>">
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
            <h3>Log Utility Expansion Request</h3>
            <button class="modal-close" onclick="closeModal('createModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group" style="margin-bottom:15px;">
                    <label>Reason / Planning Justification *</label>
                    <textarea name="reason" class="form-control" rows="3" required placeholder="Why is this expansion required..."></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Target Location / Area Name *</label>
                        <input type="text" name="area_location" class="form-control" required placeholder="e.g. Taft Avenue Zone C">
                    </div>
                    <div class="form-group">
                        <label>Utility Infrastructure Type *</label>
                        <select name="utility_type" class="form-control" required>
                            <option value="Water Supply">Water Supply</option>
                            <option value="Streetlight">Streetlight</option>
                            <option value="Drainage">Drainage</option>
                            <option value="Electrical">Electrical</option>
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
                        <label>Estimated Construction Scope Details</label>
                        <input type="text" name="estimated_scope" class="form-control" placeholder="e.g. Lay 200m PVC main pipes.">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<!-- UPDATE STATUS MODAL -->
<div id="statusModal" class="modal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header">
            <h3>Update Request Status</h3>
            <button class="modal-close" onclick="closeModal('statusModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" id="status-id" name="id">
            <div class="modal-body">
                <p style="font-size:13px; color:#64748b; margin-bottom:15px;">Change planning status for expansion request <strong id="status-request-id-text"></strong>.</p>
                
                <div class="form-group">
                    <label>Select Status</label>
                    <select id="status-select" name="status" class="form-control" required>
                        <option value="Pending">Pending</option>
                        <option value="Under Review">Under Review</option>
                        <option value="Approved">Approved</option>
                        <option value="Deferred">Deferred</option>
                        <option value="Rejected">Rejected</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('statusModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
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

    function openStatusModal(id, reqId, status) {
        document.getElementById('status-id').value = id;
        document.getElementById('status-request-id-text').textContent = reqId;
        document.getElementById('status-select').value = status;
        document.getElementById('statusModal').classList.add('open');
    }
</script>

</body>
</html>
