<?php
// maintenance_assets_view.php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$search = trim($_GET['search'] ?? '');

$conditions = [];
$params = [];

if ($search) {
    $conditions[] = "(a.name LIKE ? OR a.asset_id LIKE ? OR a.location LIKE ?)";
    $searchWildcard = '%' . $search . '%';
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Retrieve assets with requests aggregates
$query = "
    SELECT 
        a.id,
        a.asset_id,
        a.name as asset_name,
        t.name as type_name,
        a.location,
        a.condition_status,
        COUNT(CASE WHEN r.status NOT IN ('Completed', 'Closed') THEN r.id END) as active_requests,
        MAX(r.updated_at) as last_activity
    FROM utility_assets a
    JOIN asset_types t ON a.asset_type_id = t.id
    LEFT JOIN maintenance_requests r ON a.id = r.utility_asset_id
    $whereClause
    GROUP BY a.id
    ORDER BY active_requests DESC, a.asset_id ASC
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$assetsList = $stmt->fetchAll();
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
    <title>Asset-Based Maintenance Summary</title>
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
            margin-left: 78px;
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

        /* Search Panel */
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

        /* Asset Grid */
        .assets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 25px;
        }

        .asset-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
            border: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.2s, border-color 0.2s;
        }

        .asset-card:hover {
            transform: translateY(-3px);
            border-color: #3762c8;
        }

        .asset-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .asset-title {
            font-size: 16px;
            font-weight: 700;
            color: #2c3e50;
        }

        .asset-type {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: #64748b;
            margin-top: 2px;
        }

        .badge-count {
            background: #fde8e8;
            color: #e74c3c;
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 99px;
        }

        .badge-count.zero {
            background: #e2fbe8;
            color: #1e7e34;
        }

        .detail-row {
            font-size: 13px;
            color: #475569;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .detail-row i {
            color: #94a3b8;
            width: 16px;
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
                <h1><i class="fas fa-eye"></i> Asset-Based Maintenance Overview</h1>
                <p style="color: #64748b; font-size: 14px; margin-top: 5px;">Track unresolved dispatch records grouped by specific LGU utility assets.</p>
            </div>
            <div>
                <a href="maintenance_dashboard.php" class="btn btn-outline"><i class="fas fa-chevron-left"></i> Dashboard</a>
            </div>
        </div>

        <!-- Search Bar -->
        <form method="GET" class="search-panel">
            <input type="text" name="search" class="form-control" placeholder="Filter by asset name, asset ID, or location..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter Assets</button>
            <a href="maintenance_assets_view.php" class="btn btn-outline">Clear</a>
        </form>

        <!-- Assets Grid -->
        <div class="assets-grid">
            <?php if (empty($assetsList)): ?>
                <div style="grid-column: 1/-1; text-align: center; color: #64748b; padding: 40px;">No assets match filters.</div>
            <?php else: ?>
                <?php foreach ($assetsList as $asset): 
                    $activeCount = intval($asset['active_requests']);
                ?>
                    <div class="asset-card">
                        <div>
                            <div class="asset-header">
                                <div>
                                    <div class="asset-title"><?php echo htmlspecialchars($asset['asset_name']); ?></div>
                                    <div class="asset-type"><?php echo htmlspecialchars($asset['type_name'] . ' ('.$asset['asset_id'].')'); ?></div>
                                </div>
                                <span class="badge-count <?php echo $activeCount === 0 ? 'zero' : ''; ?>">
                                    <?php echo $activeCount; ?> Active
                                </span>
                            </div>
                            
                            <div class="detail-row">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?php echo htmlspecialchars($asset['location']); ?></span>
                            </div>
                            <div class="detail-row">
                                <i class="fas fa-heartbeat"></i>
                                <span>Asset Condition: <strong><?php echo htmlspecialchars($asset['condition_status']); ?></strong></span>
                            </div>
                        </div>
                        
                        <div style="border-top:1px solid #edf2f7; margin-top:15px; padding-top:15px; font-size:11px; color:#94a3b8; display:flex; justify-content:space-between; align-items:center;">
                            <span>Last Coordination Action:</span>
                            <strong><?php echo $asset['last_activity'] ? date('M d, Y', strtotime($asset['last_activity'])) : 'None'; ?></strong>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</main>

</body>
</html>
