<?php
// assets_reports.php
require_once 'includes/auth.php';
require_once 'includes/db.php';

// ✅ Security: Only employees can access reports
if (!isLoggedIn() || !isEmployee()) {
    header('Location: login.php');
    exit();
}

$type_filter = !empty($_GET['type_id']) ? intval($_GET['type_id']) : null;
$status_filter = !empty($_GET['status']) ? trim($_GET['status']) : null;
$search = trim($_GET['search'] ?? '');

// Build query
$conditions = [];
$params = [];

if ($type_filter) {
    $conditions[] = "a.asset_type_id = ?";
    $params[] = $type_filter;
}
if ($status_filter) {
    $conditions[] = "a.condition_status = ?";
    $params[] = $status_filter;
}
if ($search) {
    $conditions[] = "(a.name LIKE ? OR a.location LIKE ? OR a.asset_id LIKE ?)";
    $searchWildcard = '%' . $search . '%';
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

$query = "
    SELECT a.*, t.name as type_name 
    FROM utility_assets a 
    JOIN asset_types t ON a.asset_type_id = t.id 
    $whereClause
    ORDER BY a.asset_id ASC
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$assets = $stmt->fetchAll();

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=utility_assets_report_' . date('Ymd') . '.csv');
    $output = fopen('php://output', 'w');
    
    // Header Row
    fputcsv($output, ['Asset ID', 'Name', 'Category', 'Location', 'Latitude', 'Longitude', 'Date Installed', 'Status', 'Responsible Office']);
    
    foreach ($assets as $asset) {
        fputcsv($output, [
            $asset['asset_id'],
            $asset['name'],
            $asset['type_name'],
            $asset['location'],
            $asset['latitude'] ?? 'N/A',
            $asset['longitude'] ?? 'N/A',
            $asset['date_installed'],
            $asset['condition_status'],
            $asset['responsible_office'] ?? 'N/A'
        ]);
    }
    fclose($output);
    exit();
}

// Stats for overview inside report
$total = count($assets);
$stats = [
    'Operational' => 0,
    'Needs Inspection' => 0,
    'Damaged' => 0,
    'Under Maintenance' => 0
];
foreach ($assets as $asset) {
    if (isset($stats[$asset['condition_status']])) {
        $stats[$asset['condition_status']]++;
    }
}

$assetTypes = $pdo->query("SELECT * FROM asset_types ORDER BY name ASC")->fetchAll();

$isPrint = isset($_GET['export']) && $_GET['export'] === 'print';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Inventory Report</title>
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
            background: <?php echo $isPrint ? 'white' : 'url("assets/images/cityhall.jpeg") center/cover no-repeat fixed'; ?>;
            position: relative;
        }

        <?php if (!$isPrint): ?>
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
        <?php endif; ?>

        .main-content {
            flex: 1;
            margin-left: <?php echo $isPrint ? '0' : '280px'; ?>;
            padding: <?php echo $isPrint ? '0' : '30px 40px'; ?>;
            transition: margin-left 0.25s ease;
            z-index: 1;
            position: relative;
        }

        .card {
            width: 100%;
            max-width: 1700px;
            background: <?php echo $isPrint ? 'white' : 'rgba(255, 255, 255, 0.85)'; ?>;
            backdrop-filter: <?php echo $isPrint ? 'none' : 'blur(15px)'; ?>;
            border-radius: <?php echo $isPrint ? '0' : '18px'; ?>;
            padding: <?php echo $isPrint ? '20px' : '40px'; ?>;
            color: #000;
            box-shadow: <?php echo $isPrint ? 'none' : '0 6px 20px rgba(0,0,0,0.2)'; ?>;
            border: <?php echo $isPrint ? 'none' : '1px solid rgba(255,255,255,0.25)'; ?>;
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #edf2f7;
            padding-bottom: 20px;
        }

        .dashboard-header h1 {
            color: #2c3e50;
            font-size: 26px;
            font-weight: 700;
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

        .btn-primary { background: #3762c8; color: white; }
        .btn-primary:hover { background: #2851b0; }

        .btn-outline { background: transparent; border: 1px solid #cbd5e1; color: #64748b; }
        .btn-outline:hover { background: #f8f9fa; color: #2c3e50; }

        .filter-panel {
            background: white;
            padding: 15px 25px;
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
            min-width: 150px;
        }

        .form-group label {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
        }

        .form-control {
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 13px;
            outline: none;
        }

        /* Summary boxes */
        .summary-row {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .summary-box {
            flex: 1;
            min-width: 140px;
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            border-left: 4px solid #cbd5e1;
            box-shadow: 0 4px 10px rgba(0,0,0,0.04);
        }

        .summary-box.operational { border-left-color: #2ecc71; }
        .summary-box.inspection { border-left-color: #f1c40f; }
        .summary-box.damaged { border-left-color: #e74c3c; }
        .summary-box.maintenance { border-left-color: #9b59b6; }

        .summary-box h4 {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
        }

        .summary-box p {
            font-size: 22px;
            font-weight: 700;
            color: #2c3e50;
            margin-top: 5px;
        }

        /* Table */
        .report-table-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #edf2f7;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        th {
            background: #f8f9fa;
            color: #475569;
            font-weight: 600;
            padding: 10px 12px;
            border-bottom: 2px solid #cbd5e1;
            text-transform: uppercase;
            text-align: left;
        }

        td {
            padding: 12px 12px;
            border-bottom: 1px solid #edf2f7;
            color: #2c3e50;
        }

        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 99px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-operational { background: #e2fbe8; color: #1e7e34; }
        .badge-inspection { background: #fef9e7; color: #d39e00; }
        .badge-damaged { background: #fde8e8; color: #bd2130; }
        .badge-maintenance { background: #f3e5f5; color: #7b1fa2; }

        /* Print styling rules */
        @media print {
            .sidebar-nav, .filter-panel, .header-actions {
                display: none !important;
            }
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            body {
                background: white !important;
            }
            .card {
                padding: 0 !important;
                box-shadow: none !important;
                border: none !important;
            }
        }
    </style>
</head>
<body>

<?php if (!$isPrint) { include 'includes/utilities_sidebar.php'; } ?>

<main class="main-content" id="mainContent">
    <div class="card">
        
        <!-- Header -->
        <div class="dashboard-header">
            <div>
                <h1><i class="fas fa-file-invoice"></i> LGU Utility Assets Inventory Report</h1>
                <p style="color:#64748b; font-size:12px; margin-top:5px;">Report Generated on <?php echo date('F d, Y h:i A'); ?></p>
            </div>
            
            <?php if (!$isPrint): ?>
            <div class="header-actions">
                <a href="assets_reports.php?export=print&type_id=<?php echo $type_filter; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" target="_blank" class="btn btn-primary"><i class="fas fa-print"></i> Print PDF</a>
                <a href="assets_reports.php?export=csv&type_id=<?php echo $type_filter; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-outline"><i class="fas fa-file-excel"></i> Export CSV</a>
                <a href="assets_dashboard.php" class="btn btn-outline"><i class="fas fa-chevron-left"></i> Dashboard</a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Filter Panel -->
        <?php if (!$isPrint): ?>
        <form method="GET" class="filter-panel">
            <div class="form-group" style="flex:2;">
                <label>Keyword Search</label>
                <input type="text" name="search" class="form-control" placeholder="Search ID, name, location..." value="<?php echo htmlspecialchars($search); ?>">
            </div>

            <div class="form-group">
                <label>Asset Category</label>
                <select name="type_id" class="filter-select">
                    <option value="">All Categories</option>
                    <?php foreach ($assetTypes as $type): ?>
                        <option value="<?php echo $type['id']; ?>" <?php echo $type_filter == $type['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($type['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Status Filter</label>
                <select name="status" class="filter-select">
                    <option value="">All Statuses</option>
                    <option value="Operational" <?php echo $status_filter === 'Operational' ? 'selected' : ''; ?>>Operational</option>
                    <option value="Needs Inspection" <?php echo $status_filter === 'Needs Inspection' ? 'selected' : ''; ?>>Needs Inspection</option>
                    <option value="Damaged" <?php echo $status_filter === 'Damaged' ? 'selected' : ''; ?>>Damaged</option>
                    <option value="Under Maintenance" <?php echo $status_filter === 'Under Maintenance' ? 'selected' : ''; ?>>Under Maintenance</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
            <a href="assets_reports.php" class="btn btn-outline">Clear</a>
        </form>
        <?php endif; ?>

        <!-- Summary Row -->
        <div class="summary-row">
            <div class="summary-box">
                <h4>Total Selected</h4>
                <p><?php echo $total; ?></p>
            </div>
            <div class="summary-box operational">
                <h4>Operational</h4>
                <p><?php echo $stats['Operational']; ?></p>
            </div>
            <div class="summary-box inspection">
                <h4>Needs Inspection</h4>
                <p><?php echo $stats['Needs Inspection']; ?></p>
            </div>
            <div class="summary-box damaged">
                <h4>Damaged</h4>
                <p><?php echo $stats['Damaged']; ?></p>
            </div>
            <div class="summary-box maintenance">
                <h4>In Maintenance</h4>
                <p><?php echo $stats['Under Maintenance']; ?></p>
            </div>
        </div>

        <!-- Table -->
        <div class="report-table-container">
            <table>
                <thead>
                    <tr>
                        <th>Asset ID</th>
                        <th>Asset Name</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th>Installed</th>
                        <th>Status</th>
                        <th>Office</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($assets)): ?>
                        <tr><td colspan="7" style="text-align: center; color: #64748b;">No assets found matching filters.</td></tr>
                    <?php else: ?>
                        <?php foreach ($assets as $asset): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($asset['asset_id']); ?></strong></td>
                            <td><?php echo htmlspecialchars($asset['name']); ?></td>
                            <td><?php echo htmlspecialchars($asset['type_name']); ?></td>
                            <td><?php echo htmlspecialchars($asset['location']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($asset['date_installed'])); ?></td>
                            <td>
                                <span class="badge badge-<?php echo strtolower(str_replace(' ', '', $asset['condition_status'])); ?>">
                                    <?php echo htmlspecialchars($asset['condition_status']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($asset['responsible_office'] ?: 'Unassigned'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</main>

<?php if ($isPrint): ?>
<script>
    window.addEventListener('DOMContentLoaded', () => {
        window.print();
    });
</script>
<?php endif; ?>

</body>
</html>