<?php
// assets_history.php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$limit = 15;
$page = !empty($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Unified search/filter
$search = trim($_GET['search'] ?? '');
$searchParam = '%' . $search . '%';

// Fetch logs with pagination
$statusLogsQuery = "
    SELECT 
        l.id, 
        'status' as log_type, 
        a.asset_id, 
        a.name as asset_name, 
        l.old_status, 
        l.new_status, 
        NULL as old_location,
        NULL as new_location,
        l.changed_at, 
        l.notes, 
        u.full_name as user_name
    FROM asset_status_logs l
    JOIN utility_assets a ON l.utility_asset_id = a.id
    LEFT JOIN users u ON l.changed_by = u.id
    WHERE a.name LIKE ? OR a.asset_id LIKE ?
";

$locationLogsQuery = "
    SELECT 
        loc.id, 
        'location' as log_type, 
        a.asset_id, 
        a.name as asset_name, 
        NULL as old_status, 
        NULL as new_status, 
        loc.old_location,
        loc.new_location,
        loc.changed_at, 
        'Location address or GPS coordinates changed.' as notes, 
        u.full_name as user_name
    FROM asset_locations loc
    JOIN utility_assets a ON loc.utility_asset_id = a.id
    LEFT JOIN users u ON loc.changed_by = u.id
    WHERE a.name LIKE ? OR a.asset_id LIKE ?
";

$unionQuery = "
    ($statusLogsQuery)
    UNION ALL
    ($locationLogsQuery)
    ORDER BY changed_at DESC
    LIMIT $limit OFFSET $offset
";

$countQuery = "
    SELECT COUNT(*) FROM (
        ($statusLogsQuery)
        UNION ALL
        ($locationLogsQuery)
    ) as total_logs
";

// Count total
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute([$searchParam, $searchParam, $searchParam, $searchParam]);
$totalLogs = $countStmt->fetchColumn();
$totalPages = ceil($totalLogs / $limit);

// Fetch logs
$stmt = $pdo->prepare($unionQuery);
$stmt->execute([$searchParam, $searchParam, $searchParam, $searchParam]);
$logs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Audit Logs History</title>
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

        .btn {
            padding: 10px 20px;
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

        .btn-outline { background: transparent; border: 1px solid #cbd5e1; color: #64748b; }
        .btn-outline:hover { background: #f8f9fa; color: #2c3e50; }
        
        .btn-primary { background: #3762c8; color: white; }
        .btn-primary:hover { background: #2851b0; }

        /* Filter Box */
        .search-panel {
            background: white;
            padding: 15px 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            align-items: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .form-control {
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 14px;
            outline: none;
            flex-grow: 1;
        }

        /* Timeline layout */
        .timeline-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #cbd5e1;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 30px;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -25px;
            top: 6px;
            width: 12px;
            height: 12px;
            border-radius: 50px;
            background: #3762c8;
            border: 2px solid white;
            box-shadow: 0 0 0 3px rgba(55, 98, 200, 0.25);
        }

        .timeline-item.location::before {
            background: #27ae60;
            box-shadow: 0 0 0 3px rgba(39, 174, 96, 0.25);
        }

        .timeline-item.damaged::before {
            background: #e74c3c;
            box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.25);
        }

        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }

        .timeline-title {
            font-size: 14px;
            font-weight: 600;
            color: #2c3e50;
        }

        .timeline-meta {
            font-size: 11px;
            color: #94a3b8;
        }

        .timeline-desc {
            font-size: 13px;
            color: #475569;
            margin-top: 5px;
            line-height: 1.5;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 99px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-operational { background: #e2fbe8; color: #1e7e34; }
        .badge-inspection { background: #fef9e7; color: #d39e00; }
        .badge-damaged { background: #fde8e8; color: #bd2130; }
        .badge-maintenance { background: #f3e5f5; color: #7b1fa2; }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 6px;
            margin-top: 30px;
        }

        .page-link {
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            text-decoration: none;
            color: #64748b;
            font-size: 13px;
            font-weight: 500;
        }

        .page-link:hover {
            border-color: #3762c8;
            color: #3762c8;
            background: #f8fafc;
        }

        .page-link.active {
            background: #3762c8;
            color: white;
            border-color: #3762c8;
        }
    </style>
</head>
<body>

<?php include 'includes/utilities_sidebar.php'; ?>

<main class="main-content" id="mainContent">
    <div class="card">
        
        <!-- Header -->
        <div class="dashboard-header">
            <div>
                <h1><i class="fas fa-history"></i> Asset History Logs</h1>
                <p style="color: #64748b; font-size: 14px; margin-top: 5px;">Chronological audit trail of all asset modifications, updates, and reports.</p>
            </div>
            <div>
                <a href="assets_dashboard.php" class="btn btn-outline"><i class="fas fa-chevron-left"></i> Dashboard</a>
            </div>
        </div>

        <!-- Search Bar -->
        <form method="GET" class="search-panel">
            <input type="text" name="search" class="form-control" placeholder="Search by asset ID or asset name..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search Logs</button>
            <a href="assets_history.php" class="btn btn-outline">Clear</a>
        </form>

        <!-- Timeline Section -->
        <div class="timeline-section">
            <?php if (empty($logs)): ?>
                <div style="text-align: center; padding: 40px; color: #64748b;">No log details found in the history database.</div>
            <?php else: ?>
                <div class="timeline">
                    <?php foreach ($logs as $log): 
                        $class = '';
                        if ($log['log_type'] === 'location') $class = 'location';
                        elseif ($log['new_status'] === 'Damaged') $class = 'damaged';
                    ?>
                        <div class="timeline-item <?php echo $class; ?>">
                            <div class="timeline-header">
                                <div class="timeline-title">
                                    Asset <strong><?php echo htmlspecialchars($log['asset_name'] . ' ('. $log['asset_id'] .')'); ?></strong> 
                                    <?php if ($log['log_type'] === 'status'): ?>
                                        condition changed from 
                                        <strong><?php echo htmlspecialchars($log['old_status'] ?: 'Unspecified'); ?></strong> to 
                                        <span class="badge badge-<?php echo strtolower(str_replace(' ', '', $log['new_status'])); ?>">
                                            <?php echo htmlspecialchars($log['new_status']); ?>
                                        </span>
                                    <?php else: ?>
                                        location was modified
                                    <?php endif; ?>
                                </div>
                                <div class="timeline-meta"><?php echo date('M d, Y h:i A', strtotime($log['changed_at'])); ?></div>
                            </div>
                            <div class="timeline-desc">
                                <?php if ($log['log_type'] === 'location'): ?>
                                    <div style="margin-bottom:4px;"><strong>Old Location:</strong> <?php echo htmlspecialchars($log['old_location'] ?: 'None'); ?></div>
                                    <div><strong>New Location:</strong> <?php echo htmlspecialchars($log['new_location']); ?></div>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($log['notes'] ?: 'No status comment recorded.'); ?>
                                <?php endif; ?>
                                <div style="font-size:11px; margin-top:5px; color:#94a3b8; font-style:italic;">
                                    Modified by <?php echo htmlspecialchars($log['user_name'] ?: 'System / Admin'); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="assets_history.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="page-link <?php echo $page == $i ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</main>

</body>
</html>
