<?php
// water_records.php — Water Consumption Readings & Logs
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
    
    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $pdo->prepare("DELETE FROM water_consumption_records WHERE id = ?")->execute([$id]);
                $success = "Water record successfully deleted!";
            } catch (PDOException $e) {
                $error = "Failed to delete record: " . $e->getMessage();
            }
        }
    }
}

// Fetch Search / Filter / Pagination parameters
$search = trim($_GET['search'] ?? '');
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
if ($source_filter) {
    $conditions[] = "r.data_source = ?";
    $params[] = $source_filter;
}

$whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM water_consumption_records r $whereClause");
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Fetch records
$recordsStmt = $pdo->prepare("
    SELECT r.*, a.name as asset_name, a.asset_id as asset_code
    FROM water_consumption_records r
    LEFT JOIN utility_assets a ON r.utility_asset_id = a.id
    $whereClause
    ORDER BY r.month_year DESC, r.date_recorded DESC
    LIMIT $limit OFFSET $offset
");
$recordsStmt->execute($params);
$records = $recordsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Water consumption Readings | LGU Utilities</title>
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
            position: absolute; inset: 0;
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

        /* ── Grid Layouts ── */
        .layout-grid { display: block; }

        /* ── Alert banners ── */
        .flash { padding: 12px 18px; border-radius: 8px; font-size: 13.5px; font-weight: 500; margin-bottom: 24px; border-left: 4px solid; }
        .flash.success { background: #ecfdf5; color: #065f46; border-left-color: #10b981; }
        .flash.error { background: #fef2f2; color: #991b1b; border-left-color: #ef4444; }

        /* ── Records Table ── */
        .panel {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        .table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 12px; }
        .table-search { position: relative; }
        .table-search input {
            padding: 8px 12px 8px 34px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 12.5px;
            outline: none;
            width: 250px;
            transition: all 0.2s;
        }
        .table-search input:focus { border-color: #0284c7; width: 280px; }
        .table-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 13px; }

        .table-wrapper { width: 100%; overflow-x: auto; margin-bottom: 16px; }
        .table { width: 100%; border-collapse: collapse; text-align: left; font-size: 12.5px; }
        .table th { padding: 12px 16px; border-bottom: 2px solid #e2e8f0; color: #475569; font-weight: 600; background: #f8fafc; }
        .table td { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; }
        .table tbody tr:hover td { background: #f8fafc; }

        .btn-delete {
            background: #fee2e2;
            color: #ef4444;
            border: none;
            padding: 6px 10px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: background 0.2s;
        }
        .btn-delete:hover { background: #fca5a5; }

        .source-badge {
            font-size: 10.5px;
            font-weight: 500;
            padding: 2px 6px;
            border-radius: 6px;
            display: inline-block;
        }
        .source-badge.Manual      { background: #eff6ff; color: #2563eb; }
        .source-badge.Imported    { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }
        .source-badge.CPRF        { background: #f0fdf4; color: #16a34a; }

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
        .dark-theme .form-card { background: #1e293b; border-color: #334155; }
        .dark-theme .form-card-title { color: #f8fafc; border-bottom-color: #334155; }
        .dark-theme .form-group label { color: #94a3b8; }
        .dark-theme .form-control { background: #0f172a; border-color: #334155; color: #cbd5e1; }
        .dark-theme .form-control:focus { border-color: #0284c7; }
        .dark-theme .form-control:disabled, .dark-theme .form-control[readonly] { background: #0f172a; color: #64748b; border-color: #1e293b; }
        .dark-theme .panel { background: #1e293b; border-color: #334155; }
        .dark-theme .table th { background: #0f172a; border-bottom-color: #334155; color: #94a3b8; }
        .dark-theme .table td { border-bottom-color: #1e293b; color: #cbd5e1; }
        .dark-theme .table tbody tr:hover td { background: #0f172a; }
        .dark-theme .table-search input { background: #0f172a; border-color: #334155; color: #cbd5e1; }
        .dark-theme .table-search input:focus { border-color: #0284c7; }
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
                <h1><i class="fas fa-list"></i> Water Readings</h1>
                <p>Manage water meter readings, tariffs, and monthly sector usage records.</p>
            </div>
            <div>
                <a href="water_dashboard.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            </div>
        </header>

        <!-- FLASH NOTIFICATION BANNERS -->
        <?php if ($success): ?>
            <div class="flash success"><?php echo htmlspecialchars($success); ?></div>
        <?php elseif ($error): ?>
            <div class="flash error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="layout-grid">
            
            <!-- LEFT PANEL: RECORD LIST TABLE -->
            <div class="panel">
                <div class="table-header">
                    <h3 style="font-size:16px; font-weight:600; color:#1e293b;" class="dashboard-header"><i class="fas fa-list"></i> Consumption logs</h3>
                    <div class="table-search">
                        <form action="" method="GET">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" placeholder="Search record ID or location..." value="<?php echo htmlspecialchars($search); ?>">
                        </form>
                    </div>
                </div>

                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Asset / Facility</th>
                                <th>Location</th>
                                <th>Month-Year</th>
                                <th>Prev (m³)</th>
                                <th>Curr (m³)</th>
                                <th>Usage (m³)</th>
                                <th>Cost</th>
                                <th>Source</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($records)): ?>
                                <tr>
                                    <td colspan="10" style="text-align:center; color:#64748b; padding:30px;">No water reading logs found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($records as $row): ?>
                                    <?php 
                                    $displayName = $row['facility_name'] ?: ($row['asset_name'] ?: 'N/A');
                                    $sourceClass = 'Manual';
                                    if ($row['data_source'] === 'Imported') $sourceClass = 'Imported';
                                    if ($row['data_source'] === 'CPRF Integration') $sourceClass = 'CPRF';
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($row['record_id']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($displayName); ?></td>
                                        <td><?php echo htmlspecialchars($row['location']); ?></td>
                                        <td><span class="code"><?php echo htmlspecialchars($row['month_year']); ?></span></td>
                                        <td><?php echo number_format((float)$row['previous_reading'], 2); ?></td>
                                        <td><?php echo number_format((float)$row['current_reading'], 2); ?></td>
                                        <td><strong><?php echo number_format((float)$row['consumption_m3'], 2); ?></strong></td>
                                        <td><strong>₱<?php echo number_format((float)$row['cost'], 2); ?></strong></td>
                                        <td><span class="source-badge <?php echo $sourceClass; ?>"><?php echo htmlspecialchars($row['data_source']); ?></span></td>
                                        <td>
                                            <form action="" method="POST" onsubmit="return confirm('Are you sure you want to delete this record?');" style="display:inline;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                <button type="submit" class="btn-delete"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- PAGINATION -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination-container">
                    <div class="pagination-info">
                        Showing <?php echo ($offset + 1); ?> – <?php echo min($totalRecords, $offset + $limit); ?> of <?php echo $totalRecords; ?> records
                    </div>
                    <div class="pagination-links">
                        <?php for ($pg = 1; $pg <= $totalPages; $pg++): ?>
                            <a href="water_records.php?search=<?php echo urlencode($search); ?>&page=<?php echo $pg; ?>"
                               class="page-link <?php echo ($pg === $page) ? 'active' : ''; ?>">
                                <?php echo $pg; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

        </div>

    </div>
</main>

</body>
</html>
