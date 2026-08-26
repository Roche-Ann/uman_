<?php
// water_recommendations.php — Water Efficiency Recommendations
require_once 'includes/auth.php';
require_once 'includes/db.php';
ensureWaterSchema();

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
                $stmt = $pdo->prepare("SELECT recommendation_title FROM water_recommendations WHERE id = ?");
                $stmt->execute([$id]);
                $title = $stmt->fetchColumn();

                $stmt = $pdo->prepare("
                    UPDATE water_recommendations 
                    SET status = ?, remarks = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$status, $remarks, $id]);

                // Create alert notification
                $pdo->prepare("
                    INSERT INTO water_notifications (message) 
                    VALUES (?)
                ")->execute(["Recommendation status updated: '{$title}' is now {$status}."]);

                $success = "Recommendation status successfully updated to {$status}!";
            } catch (PDOException $e) {
                $error = "Failed to update recommendation: " . $e->getMessage();
            }
        }
    }
}

// Fetch Search / Filter / Pagination parameters
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

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM water_recommendations r $whereClause");
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Fetch list
$recordsStmt = $pdo->prepare("
    SELECT * FROM water_recommendations r
    $whereClause
    ORDER BY date_received DESC
    LIMIT $limit OFFSET $offset
");
$recordsStmt->execute($params);
$recommendations = $recordsStmt->fetchAll();
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
    <title>Water recommendations | LGU Utilities</title>
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        *::before, *::after { box-sizing: border-box; }

        body {
            min-height: 100vh;
            display: flex;
            background: url("assets/images/cityhall.jpeg") center/cover no-repeat fixed;
            position: relative;
        }
        body::before {
            content: "";
            position: fixed; inset: 0;
            backdrop-filter: blur(6px);
            background: rgba(0, 0, 0, 0.35);
            z-index: 0;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px 40px 60px;
            transition: margin-left 0.25s ease;
            z-index: 1;
            position: relative;
        }
        .main-content.collapsed { margin-left: 90px; }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 20px; } }

        .card {
            width: 100%;
            max-width: 1700px;
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(18px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.18);
            border: 1px solid rgba(255,255,255,0.3);
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 32px;
            flex-wrap: wrap;
            gap: 20px;
        }
        .dashboard-header h1 {
            color: #1e293b;
            font-size: 30px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .dashboard-header h1 i { color: #0284c7; }
        .dashboard-header p { color: #64748b; font-size: 13px; margin-top: 6px; }

        .btn {
            padding: 10px 18px;
            border-radius: 9px;
            font-weight: 600;
            font-size: 13px;
            border: none;
            cursor: pointer;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-primary   { background: #0284c7; color: #fff; }
        .btn-primary:hover { background: #0369a1; transform: translateY(-1px); }
        .btn-outline   { background: transparent; border: 1.5px solid #cbd5e1; color: #475569; }
        .btn-outline:hover { background: #f8fafc; color: #1e293b; }
        .btn-amber     { background: #f59e0b; color: #fff; }
        .btn-amber:hover { background: #d97706; }

        /* ── Filter Bar ── */
        .filter-bar {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 18px 24px;
            margin-bottom: 30px;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            align-items: end;
        }
        .filter-group { display: flex; flex-direction: column; gap: 6px; flex: 1; min-width: 150px; }
        .filter-group label { font-size: 12px; font-weight: 600; color: #64748b; }
        .filter-control {
            padding: 9px 12px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 13px;
            background: #fff;
            color: #1e293b;
            outline: none;
        }
        .filter-control:focus { border-color: #0284c7; }

        /* ── Alert banners ── */
        .flash { padding: 12px 18px; border-radius: 8px; font-size: 13.5px; font-weight: 500; margin-bottom: 24px; border-left: 4px solid; }
        .flash.success { background: #ecfdf5; color: #065f46; border-left-color: #10b981; }
        .flash.error { background: #fef2f2; color: #991b1b; border-left-color: #ef4444; }

        /* ── Recommendation Cards ── */
        .recs-list { display: flex; flex-direction: column; gap: 20px; margin-bottom: 24px; }
        .rec-item {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            display: grid;
            grid-template-columns: 1fr 280px;
            gap: 24px;
        }
        @media (max-width: 900px) { .rec-item { grid-template-columns: 1fr; } }
        
        .rec-content h3 { font-size: 16px; font-weight: 600; color: #1e293b; margin-bottom: 8px; }
        .rec-desc { font-size: 13px; color: #475569; line-height: 1.5; margin-bottom: 16px; }
        .rec-meta { display: flex; gap: 16px; flex-wrap: wrap; font-size: 12px; color: #94a3b8; }
        .rec-meta span { display: flex; align-items: center; gap: 6px; }
        .rec-meta i { color: #64748b; }

        .priority-badge {
            font-size: 10px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 6px;
            text-transform: uppercase;
        }
        .priority-badge.High      { background: #fee2e2; color: #ef4444; }
        .priority-badge.Medium    { background: #fef3c7; color: #d97706; }
        .priority-badge.Low       { background: #f0fdf4; color: #15803d; }
        .priority-badge.Emergency { background: #fef2f2; color: #dc2626; border: 1px solid #fca5a5; }

        .status-badge {
            font-size: 10.5px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .status-badge.Pending      { background: #f1f5f9; color: #475569; }
        .status-badge.Acknowledged { background: #e0f2fe; color: #0284c7; }
        .status-badge.Implemented  { background: #dcfce7; color: #15803d; }
        .status-badge.Archived     { background: #f3e8ff; color: #7c3aed; }

        /* ── Update Action Panel ── */
        .rec-actions {
            border-left: 1px solid #f1f5f9;
            padding-left: 24px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        @media (max-width: 900px) { .rec-actions { border-left: none; padding-left: 0; border-top: 1px solid #f1f5f9; padding-top: 20px; } }
        
        .action-form { display: flex; flex-direction: column; gap: 12px; }
        .action-label { font-size: 11.5px; font-weight: 600; color: #64748b; }
        
        .remarks-input {
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            font-size: 12.5px;
            resize: none;
            width: 100%;
        }
        .remarks-input:focus { border-color: #0284c7; outline: none; }

        .pagination-container { display: flex; justify-content: space-between; align-items: center; margin-top: 14px; flex-wrap: wrap; gap: 12px; }
        .pagination-info { font-size: 12px; color: #64748b; }
        .pagination-links { display: flex; gap: 5px; }
        .page-link {
            padding: 6px 11px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            text-decoration: none;
            color: #475569;
            font-size: 12px;
            transition: all 0.2s;
        }
        .page-link:hover { background: #f1f5f9; color: #1e293b; }
        .page-link.active { background: #0284c7; color: #fff; border-color: #0284c7; font-weight: 600; }

        /* ── Dark Mode ── */
        .dark-theme .card { background: rgba(15,23,42,0.92); border-color: rgba(255,255,255,0.08); }
        .dark-theme .dashboard-header h1 { color: #f8fafc; }
        .dark-theme .dashboard-header p { color: #94a3b8; }
        .dark-theme .filter-bar { background: #1e293b; border-color: #334155; }
        .dark-theme .filter-group label { color: #94a3b8; }
        .dark-theme .filter-control { background: #0f172a; border-color: #334155; color: #cbd5e1; }
        .dark-theme .rec-item { background: #1e293b; border-color: #334155; }
        .dark-theme .rec-content h3 { color: #f8fafc; }
        .dark-theme .rec-desc { color: #cbd5e1; }
        .dark-theme .rec-meta i { color: #94a3b8; }
        .dark-theme .rec-actions { border-left-color: #334155; }
        .dark-theme .remarks-input { background: #0f172a; border-color: #334155; color: #cbd5e1; }
        .dark-theme .remarks-input:focus { border-color: #0284c7; }
        .dark-theme .page-link { border-color: #334155; color: #94a3b8; }
        .dark-theme .page-link:hover { background: #1e293b; }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<?php include_once 'includes/utilities_sidebar.php'; ?>

<main class="main-content <?php echo (isset($_COOKIE['sidebar_collapsed']) && $_COOKIE['sidebar_collapsed'] === 'true') ? 'collapsed' : ''; ?>">
    <div class="card">

        <!-- HEADER -->
        <header class="dashboard-header">
            <div>
                <h1><i class="fas fa-lightbulb"></i> Water Efficiency Recommendations</h1>
                <p>Track, evaluate, and update leak interventions and resource saving advisories.</p>
            </div>
            <div>
                <a href="water_dashboard.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            </div>
        </header>

        <!-- NOTIFICATIONS -->
        <?php if ($success): ?>
            <div class="flash success"><?php echo htmlspecialchars($success); ?></div>
        <?php elseif ($error): ?>
            <div class="flash error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- FILTER BAR -->
        <section class="filter-bar">
            <form action="" method="GET" style="display:contents;">
                <div class="filter-group" style="flex:2;">
                    <label for="search">Keyword Search</label>
                    <input type="text" name="search" id="search" class="filter-control" placeholder="Search title, description, or target..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group">
                    <label for="status">Status</label>
                    <select name="status" id="status" class="filter-control">
                        <option value="">All Statuses</option>
                        <option value="Pending" <?php echo ($status_filter === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="Acknowledged" <?php echo ($status_filter === 'Acknowledged') ? 'selected' : ''; ?>>Acknowledged</option>
                        <option value="Implemented" <?php echo ($status_filter === 'Implemented') ? 'selected' : ''; ?>>Implemented</option>
                        <option value="Archived" <?php echo ($status_filter === 'Archived') ? 'selected' : ''; ?>>Archived</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="priority_level">Priority</label>
                    <select name="priority_level" id="priority_level" class="filter-control">
                        <option value="">All Priorities</option>
                        <option value="Low" <?php echo ($priority_filter === 'Low') ? 'selected' : ''; ?>>Low</option>
                        <option value="Medium" <?php echo ($priority_filter === 'Medium') ? 'selected' : ''; ?>>Medium</option>
                        <option value="High" <?php echo ($priority_filter === 'High') ? 'selected' : ''; ?>>High</option>
                        <option value="Emergency" <?php echo ($priority_filter === 'Emergency') ? 'selected' : ''; ?>>Emergency</option>
                    </select>
                </div>
                <div style="display:flex; gap:8px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                    <a href="water_recommendations.php" class="btn btn-outline"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>
        </section>

        <!-- RECOMMENDATIONS LIST -->
        <section class="recs-list">
            <?php if (empty($recommendations)): ?>
                <div class="rec-item" style="display:block; text-align:center; padding:50px; color:#64748b;">
                    <i class="fas fa-circle-info" style="font-size:30px; margin-bottom:12px;"></i>
                    <p>No water efficiency recommendations found matching the selected filters.</p>
                </div>
            <?php else: ?>
                <?php foreach ($recommendations as $row): ?>
                    <div class="rec-item">
                        <div class="rec-content">
                            <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                                <span class="priority-badge <?php echo $row['priority_level']; ?>"><?php echo $row['priority_level']; ?> Priority</span>
                                <span class="status-badge <?php echo $row['status']; ?>"><i class="fas fa-circle" style="font-size:6px;"></i> <?php echo $row['status']; ?></span>
                            </div>
                            <h3><?php echo htmlspecialchars($row['recommendation_title']); ?></h3>
                            <p class="rec-desc"><?php echo htmlspecialchars($row['description']); ?></p>
                            
                            <?php if ($row['remarks']): ?>
                                <div style="background:#f8fafc; border-left:3px solid #cbd5e1; padding:10px 14px; margin-bottom:14px; border-radius: 0 6px 6px 0; font-size:12px; color:#475569;" class="dark-theme-remarks">
                                    <strong>Latest Remarks:</strong> <?php echo htmlspecialchars($row['remarks']); ?>
                                </div>
                            <?php endif; ?>

                            <div class="rec-meta">
                                <span><i class="fas fa-location-dot"></i> <?php echo htmlspecialchars($row['target_facility_asset']); ?></span>
                                <span><i class="fas fa-calendar-alt"></i> Received: <?php echo date('M d, Y', strtotime($row['date_received'])); ?></span>
                            </div>
                        </div>

                        <div class="rec-actions">
                            <form action="" method="POST" class="action-form">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                
                                <div style="display:flex; flex-direction:column; gap:4px;">
                                    <span class="action-label">Update Status</span>
                                    <select name="status" class="filter-control" style="padding:6px 10px; font-size:12px;" required>
                                        <option value="Pending" <?php echo ($row['status'] === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                        <option value="Acknowledged" <?php echo ($row['status'] === 'Acknowledged') ? 'selected' : ''; ?>>Acknowledged</option>
                                        <option value="Implemented" <?php echo ($row['status'] === 'Implemented') ? 'selected' : ''; ?>>Implemented</option>
                                        <option value="Archived" <?php echo ($row['status'] === 'Archived') ? 'selected' : ''; ?>>Archived</option>
                                    </select>
                                </div>

                                <div style="display:flex; flex-direction:column; gap:4px;">
                                    <span class="action-label">Remarks / Follow-up Notes</span>
                                    <textarea name="remarks" class="remarks-input" rows="2" placeholder="e.g. Dispatched maintenance team..."><?php echo htmlspecialchars($row['remarks'] ?? ''); ?></textarea>
                                </div>

                                <button type="submit" class="btn btn-primary btn-sm" style="justify-content:center;"><i class="fas fa-save"></i> Save Changes</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <!-- PAGINATION -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination-container">
                <div class="pagination-info">
                    Showing <?php echo ($offset + 1); ?> – <?php echo min($totalRecords, $offset + $limit); ?> of <?php echo $totalRecords; ?> recommendations
                </div>
                <div class="pagination-links">
                    <?php
                    $baseQ = http_build_query([
                        'search'         => $search,
                        'status'         => $status_filter,
                        'priority_level' => $priority_filter,
                    ]);
                    for ($pg = 1; $pg <= $totalPages; $pg++):
                    ?>
                        <a href="water_recommendations.php?<?php echo $baseQ; ?>&page=<?php echo $pg; ?>"
                           class="page-link <?php echo ($pg === $page) ? 'active' : ''; ?>">
                            <?php echo $pg; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
</main>

</body>
</html>
