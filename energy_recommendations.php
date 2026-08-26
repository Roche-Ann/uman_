<?php
// energy_recommendations.php
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
    
    if ($action === 'update_status') {
        $id = intval($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? 'Acknowledged';
        $remarks = trim($_POST['remarks'] ?? '');
        
        if ($id > 0) {
            try {
                // Get recommendation title
                $stmt = $pdo->prepare("SELECT recommendation_title FROM energy_recommendations WHERE id = ?");
                $stmt->execute([$id]);
                $title = $stmt->fetchColumn();

                $stmt = $pdo->prepare("
                    UPDATE energy_recommendations 
                    SET status = ?, remarks = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$status, $remarks, $id]);

                // Create alert notification
                $pdo->prepare("
                    INSERT INTO energy_notifications (message) 
                    VALUES (?)
                ")->execute(["Recommendation status updated: '{$title}' is now {$status}."]);

                $success = "Recommendation status successfully updated to {$status}!";
            } catch (PDOException $e) {
                $error = "Failed to update recommendation: " . $e->getMessage();
            }
        }
    }
}

// ------------------------------------------------------------------------
// Get Search / Filter / Pagination parameters
// ------------------------------------------------------------------------
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority_level'] ?? '';

// Pagination configuration
$limit = 10;
$page = !empty($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(r.recommendation_title LIKE ? OR r.description LIKE ? OR r.target_facility_asset LIKE ?)";
    $searchWildcard = '%' . $search . '%';
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
}

if ($status_filter) {
    $conditions[] = "r.status = ?";
    $params[] = $status_filter;
}

if ($priority_filter) {
    $conditions[] = "r.priority_level = ?";
    $params[] = $priority_filter;
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Retrieve count for pagination
$countQuery = "SELECT COUNT(*) FROM energy_recommendations r $whereClause";
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Retrieve recommendations list
$query = "
    SELECT * FROM energy_recommendations r
    $whereClause
    ORDER BY date_received DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$recsList = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            if (savedTheme === 'dark') {
                document.documentElement.classList.add('dark-theme');
            }
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Energy Efficiency Advisories</title>
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

        .badge-pending { background: #fff4e5; color: #b45309; }
        .badge-acknowledged { background: #e0f2fe; color: #0284c7; }
        .badge-implemented { background: #e2fbe8; color: #1e7e34; }
        .badge-archived { background: #f1f5f9; color: #64748b; }

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
    </style>
</head>
<body>

<?php include 'includes/utilities_sidebar.php'; ?>

<main class="main-content" id="mainContent">
    <div class="card">
        
        <!-- Header -->
        <div class="dashboard-header">
            <div>
                <h1><i class="fas fa-lightbulb"></i> Efficiency Advisory Recommendations</h1>
                <p style="color: #64748b; font-size: 14px; margin-top: 5px;">Review inbound advisory recommendations from the external Energy Efficiency System and track compliance status.</p>
            </div>
            <div>
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
                <input type="text" name="search" class="form-control" placeholder="Search title, description, or target..." value="<?php echo htmlspecialchars($search); ?>">
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="">All Statuses</option>
                    <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Acknowledged" <?php echo $status_filter === 'Acknowledged' ? 'selected' : ''; ?>>Acknowledged</option>
                    <option value="Implemented" <?php echo $status_filter === 'Implemented' ? 'selected' : ''; ?>>Implemented</option>
                    <option value="Archived" <?php echo $status_filter === 'Archived' ? 'selected' : ''; ?>>Archived</option>
                </select>
            </div>

            <div class="form-group">
                <label>Priority Level</label>
                <select name="priority_level" class="form-control">
                    <option value="">All Priorities</option>
                    <option value="Low" <?php echo $priority_filter === 'Low' ? 'selected' : ''; ?>>Low</option>
                    <option value="Medium" <?php echo $priority_filter === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                    <option value="High" <?php echo $priority_filter === 'High' ? 'selected' : ''; ?>>High</option>
                    <option value="Emergency" <?php echo $priority_filter === 'Emergency' ? 'selected' : ''; ?>>Emergency</option>
                </select>
            </div>

            <div style="display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
                <a href="energy_recommendations.php" class="btn btn-outline">Reset</a>
            </div>
        </form>

        <!-- Recommendations Table -->
        <div class="table-section">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Recommendation Title</th>
                            <th>Description</th>
                            <th>Target Asset/Facility</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Remarks / Compliance Notes</th>
                            <th>Date Received</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recsList)): ?>
                            <tr><td colspan="8" style="text-align:center; padding:30px; color:#64748b;">No recommendations found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recsList as $rec): 
                                $statusBadge = strtolower($rec['status']);
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($rec['recommendation_title']); ?></strong></td>
                                <td><?php echo htmlspecialchars(substr($rec['description'], 0, 45)) . (strlen($rec['description']) > 45 ? '...' : ''); ?></td>
                                <td><?php echo htmlspecialchars($rec['target_facility_asset']); ?></td>
                                <td><span class="badge badge-<?php echo strtolower($rec['priority_level']); ?>"><?php echo htmlspecialchars($rec['priority_level']); ?></span></td>
                                <td><span class="badge badge-<?php echo $statusBadge; ?>"><?php echo htmlspecialchars($rec['status']); ?></span></td>
                                <td><?php echo $rec['remarks'] ? htmlspecialchars($rec['remarks']) : '<em style="color:#94a3b8;">None</em>'; ?></td>
                                <td><?php echo date('M d, Y', strtotime($rec['date_received'])); ?></td>
                                <td style="text-align:right; white-space:nowrap;">
                                    <button class="btn btn-outline" style="padding:4px 8px; font-size:12px;" onclick="openReviewModal(<?php echo json_encode($rec); ?>)"><i class="fas fa-check-double"></i> Review Advisory</button>
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
                    Showing <?php echo $offset + 1; ?> to <?php echo min($totalRecords, $offset + $limit); ?> of <?php echo $totalRecords; ?> advisories
                </div>
                <div class="pagination-links">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="energy_recommendations.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&priority_level=<?php echo urlencode($priority_filter); ?>" class="page-link <?php echo $page == $i ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<!-- REVIEW MODAL -->
<div id="reviewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Review Advisory Recommendation</h3>
            <button class="modal-close" onclick="closeModal('reviewModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" id="review-id" name="id">
            <div class="modal-body">
                <div class="form-group">
                    <label>Advisory Status</label>
                    <select id="review-status" name="status" class="form-control" required>
                        <option value="Pending">Pending (Under review queue)</option>
                        <option value="Acknowledged">Acknowledged (Noted by LGU Administration)</option>
                        <option value="Implemented">Implemented (Recommendation solved/applied)</option>
                        <option value="Archived">Archived (Closed)</option>
                    </select>
                </div>

                <div class="form-group" style="margin-top:15px;">
                    <label>Compliance Notes / Action Remarks</label>
                    <textarea id="review-remarks" name="remarks" class="form-control" rows="4" placeholder="Enter remarks regarding retrofit actions, budgets, or logistics..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('reviewModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Evaluation</button>
            </div>
        </form>
    </div>
</div>

<script>
    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
    }

    function openReviewModal(rec) {
        document.getElementById('review-id').value = rec.id;
        document.getElementById('review-status').value = rec.status;
        document.getElementById('review-remarks').value = rec.remarks || '';
        document.getElementById('reviewModal').classList.add('open');
    }
</script>

</body>
</html>
