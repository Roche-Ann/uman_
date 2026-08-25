<?php
// citizen.php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'] ?? 3;
$userName = $_SESSION['user_name'] ?? $_SESSION['full_name'] ?? 'Resident';

// Fetch resident stats
$totalReports = 0;
$resolvedReports = 0;
$activeReports = 0;

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM utility_incidents WHERE resident_id = ?");
    $stmt->execute([$userId]);
    $totalReports = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM utility_incidents WHERE resident_id = ? AND status IN ('Resolved', 'Closed')");
    $stmt->execute([$userId]);
    $resolvedReports = $stmt->fetchColumn();

    $activeReports = $totalReports - $resolvedReports;
} catch (Exception $e) {
    // Fallback if schema differs slightly
}

// Fetch latest 3 advisories
$advisories = [];
try {
    $advisories = $pdo->query("SELECT * FROM utility_advisories ORDER BY published_date DESC LIMIT 3")->fetchAll();
} catch (Exception $e) {
    // Fallback
}


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
    <title>LGU Citizen Portal</title>
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

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 35px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-left: 5px solid #cbd5e1;
        }

        .stat-card.total { border-left-color: #3762c8; }
        .stat-card.active { border-left-color: #f1c40f; }
        .stat-card.resolved { border-left-color: #2ecc71; }

        .stat-info h3 {
            font-size: 24px;
            font-weight: 700;
            color: #2c3e50;
        }

        .stat-info p {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
            margin-top: 3px;
        }

        .stat-icon { font-size: 26px; color: #cbd5e1; }

        .dashboard-layout {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 30px;
            margin-bottom: 35px;
        }

        @media (max-width: 1000px) {
            .dashboard-layout { grid-template-columns: 1fr; }
        }

        .box {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .box h3 {
            font-size: 16px;
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 2px solid #f1f2f6;
            padding-bottom: 10px;
        }

        .item-card {
            padding: 15px;
            border-radius: 8px;
            background: #f8fafc;
            border: 1px solid #edf2f7;
            margin-bottom: 12px;
        }

        .badge {
            font-size: 9px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 99px;
            text-transform: uppercase;
        }
        .badge-partiallyready { background: #fff4e5; color: #b45309; }
        .badge-notready { background: #fde8e8; color: #bd2130; }

        /* Welcome Modal CSS */
        .welcome-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 10000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(4px);
        }
        .welcome-modal.open {
            display: flex;
        }
        .welcome-modal-content {
            background: #ffffff;
            border-radius: 16px;
            width: 500px;
            max-width: 90%;
            padding: 30px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            animation: welcomeSlideIn 0.3s ease-out;
            position: relative;
        }
        @keyframes welcomeSlideIn {
            from { transform: translateY(-30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .welcome-header {
            text-align: center;
            margin-bottom: 20px;
        }
        .welcome-header i {
            font-size: 50px;
            color: #3762c8;
            margin-bottom: 10px;
        }
        .welcome-header h2 {
            font-size: 24px;
            color: #1e293b;
            font-weight: 600;
        }
        .welcome-body {
            font-size: 14px;
            color: #475569;
            line-height: 1.6;
        }
        .welcome-body h4 {
            color: #1e293b;
            margin-top: 15px;
            margin-bottom: 8px;
            font-weight: 600;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
        }
        .welcome-updates-list {
            list-style: none;
            padding: 0;
        }
        .welcome-updates-list li {
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .welcome-updates-list li:last-child {
            border-bottom: none;
        }
        .welcome-updates-list li i {
            color: #3762c8;
            margin-top: 4px;
            font-size: 16px;
        }
        .welcome-footer {
            margin-top: 25px;
            display: flex;
            justify-content: center;
        }
        .welcome-btn {
            background: #3762c8;
            color: #fff;
            padding: 10px 30px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .welcome-btn:hover {
            background: #2851b0;
            transform: translateY(-1px);
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
                <h1><i class="fas fa-home"></i> Resident Portal</h1>
                <p style="color: #64748b; font-size: 14px; margin-top: 5px;">Welcome back, <?php echo htmlspecialchars($userName); ?>. Submit utility incident reports and track LGU service updates.</p>
            </div>
            <div>
                <a href="citizen_reports.php" class="btn btn-primary"><i class="fas fa-plus"></i> File Incident Report</a>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-info">
                    <h3><?php echo number_format($totalReports); ?></h3>
                    <p>Total Filed Reports</p>
                </div>
                <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
            </div>
            <div class="stat-card active">
                <div class="stat-info">
                    <h3><?php echo number_format($activeReports); ?></h3>
                    <p>Active / In Review</p>
                </div>
                <div class="stat-icon"><i class="fas fa-spinner"></i></div>
            </div>
            <div class="stat-card resolved">
                <div class="stat-info">
                    <h3><?php echo number_format($resolvedReports); ?></h3>
                    <p>Resolved Reports</p>
                </div>
                <div class="stat-icon"><i class="fas fa-check-double"></i></div>
            </div>
        </div>

        <!-- Main Layout Split -->
        <div class="dashboard-layout" style="grid-template-columns: 1fr;">
            
            <!-- Left: Latest Utility Advisories -->
            <div class="box">
                <h3><i class="fas fa-bullhorn"></i> Latest LGU Utility Advisories</h3>
                <?php if (empty($advisories)): ?>
                    <p style="color:#64748b; font-size:13px;">No utility advisories published recently.</p>
                <?php else: ?>
                    <?php foreach ($advisories as $adv): ?>
                        <div class="item-card">
                            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                <h4 style="color:#2c3e50; font-size:14px; font-weight:600;"><?php echo htmlspecialchars($adv['title']); ?></h4>
                                <span style="font-size:10px; color:#94a3b8;"><?php echo date('M d, Y', strtotime($adv['published_date'])); ?></span>
                            </div>
                            <p style="font-size:12px; color:#64748b; margin-top:5px;"><?php echo htmlspecialchars(substr($adv['content'], 0, 150)) . (strlen($adv['content']) > 150 ? '...' : ''); ?></p>
                            <div style="font-size:11px; color:#3762c8; font-weight:600; margin-top:8px;"><i class="fas fa-map-marker-alt"></i> Affected Area: <?php echo htmlspecialchars($adv['area_affected']); ?></div>
                        </div>
                    <?php endforeach; ?>
                    <div style="text-align:right; margin-top:10px;">
                        <a href="citizen_advisories.php" style="font-size:12px; font-weight:600; color:#3762c8; text-decoration:none;">View All Advisories <i class="fas fa-arrow-right"></i></a>
                    </div>
                <?php endif; ?>
            </div>



        </div>

    </div>
</main>

<?php if (isset($_SESSION['show_welcome_modal']) && $_SESSION['show_welcome_modal'] === true): ?>
<!-- WELCOME BACK POPUP MODAL -->
<div id="welcomeBackModal" class="welcome-modal open">
    <div class="welcome-modal-content">
        <div class="welcome-header">
            <i class="fas fa-hand-sparkles"></i>
            <h2>Welcome Back, <?php echo htmlspecialchars($userName); ?>!</h2>
            <p style="color: #64748b; font-size: 14px; margin-top: 5px;">You have successfully logged in to the LGU Citizen Portal.</p>
        </div>
        <div class="welcome-body">
            <h4><i class="fas fa-bullhorn" style="color: #3762c8; margin-right: 6px;"></i> While you were away:</h4>
            <ul class="welcome-updates-list">
                <li>
                    <i class="fas fa-file-invoice"></i>
                    <div>
                        <strong>Your Reports:</strong> You have <?php echo $activeReports; ?> active reports currently being processed by the LGU, and <?php echo $resolvedReports; ?> reports have been resolved.
                    </div>
                </li>
                <li>
                    <i class="fas fa-broadcast-tower"></i>
                    <div>
                        <strong>Latest LGU Advisories:</strong> The LGU has published <?php echo count($advisories); ?> new advisories about utility services.
                    </div>
                </li>
            </ul>
        </div>
        <div class="welcome-footer">
            <button type="button" class="welcome-btn" onclick="closeWelcomeModal()">Dismiss Updates</button>
        </div>
    </div>
</div>
<script>
function closeWelcomeModal() {
    document.getElementById('welcomeBackModal').classList.remove('open');
}
</script>
<?php 
    $_SESSION['show_welcome_modal'] = false;
endif; 
?>

</body>
</html>