<?php
// assets_notifications.php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Mark all as read
if (isset($_GET['read_all'])) {
    $pdo->exec("UPDATE asset_notifications SET read_status = 1 WHERE read_status = 0");
    header('Location: assets_notifications.php');
    exit();
}

// Fetch all notifications
$notifications = $pdo->query("SELECT * FROM asset_notifications ORDER BY created_at DESC LIMIT 50")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Notifications Alerts</title>
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

        .btn-primary { background: #3762c8; color: white; }
        .btn-primary:hover { background: #2851b0; }

        .btn-outline { background: transparent; border: 1px solid #cbd5e1; color: #64748b; }
        .btn-outline:hover { background: #f8f9fa; color: #2c3e50; }

        /* Notifications list layout */
        .notif-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .notif-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .notif-item {
            display: flex;
            gap: 15px;
            padding: 18px 20px;
            border-radius: 10px;
            background: #f8fafc;
            border-left: 4px solid #cbd5e1;
            transition: all 0.2s;
        }

        .notif-item:hover {
            transform: translateX(3px);
            background: #f1f5f9;
        }

        .notif-item.unread {
            background: #f0f7ff;
            border-left-color: #3762c8;
        }

        .notif-item.unread:hover {
            background: #e0f2fe;
        }

        .notif-icon {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: #e2e8f0;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        .notif-item.unread .notif-icon {
            background: #dbeafe;
            color: #3762c8;
        }

        .notif-icon.damaged {
            background: #fde8e8 !important;
            color: #e74c3c !important;
        }

        .notif-content {
            flex-grow: 1;
        }

        .notif-message {
            font-size: 14px;
            color: #1e293b;
            font-weight: 500;
        }

        .notif-item.unread .notif-message {
            font-weight: 600;
        }

        .notif-time {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 6px;
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
                <h1><i class="fas fa-bell"></i> Asset Alerts & Notifications</h1>
                <p style="color: #64748b; font-size: 14px; margin-top: 5px;">Monitor system triggers for new assets and reported damages.</p>
            </div>
            <div style="display:flex; gap:10px;">
                <a href="assets_notifications.php?read_all=1" class="btn btn-primary"><i class="fas fa-check-double"></i> Mark All Read</a>
                <a href="assets_dashboard.php" class="btn btn-outline"><i class="fas fa-chevron-left"></i> Dashboard</a>
            </div>
        </div>

        <!-- Notifications timeline list -->
        <div class="notif-section">
            <?php if (empty($notifications)): ?>
                <div style="text-align: center; padding: 40px; color: #64748b;">No alerts or notifications recorded.</div>
            <?php else: ?>
                <div class="notif-list">
                    <?php foreach ($notifications as $n): 
                        $iconClass = 'fa-info-circle';
                        $classSuffix = '';
                        if ($n['type'] === 'reported_damaged') {
                            $iconClass = 'fa-exclamation-triangle';
                            $classSuffix = ' damaged';
                        } elseif ($n['type'] === 'asset_created') {
                            $iconClass = 'fa-plus-circle';
                        }
                    ?>
                        <div class="notif-item <?php echo $n['read_status'] == 0 ? 'unread' : ''; ?>">
                            <div class="notif-icon <?php echo $classSuffix; ?>">
                                <i class="fas <?php echo $iconClass; ?>"></i>
                            </div>
                            <div class="notif-content">
                                <div class="notif-message"><?php echo htmlspecialchars($n['message']); ?></div>
                                <div class="notif-time"><i class="fas fa-clock"></i> <?php echo date('M d, Y h:i A', strtotime($n['created_at'])); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</main>

</body>
</html>
