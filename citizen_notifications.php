<?php
// citizen_notifications.php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'] ?? 3;

$error = '';
$success = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'mark_read') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE incident_notifications SET read_status = 1 WHERE id = ? AND user_id = ?");
                $stmt->execute([$id, $userId]);
                $success = "Notification marked as read.";
            } catch (PDOException $e) {
                $error = "Operation failed: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM incident_notifications WHERE id = ? AND user_id = ?");
                $stmt->execute([$id, $userId]);
                $success = "Notification deleted successfully.";
            } catch (PDOException $e) {
                $error = "Operation failed: " . $e->getMessage();
            }
        }
    }
}

// Retrieve notifications list
$notifications = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM incident_notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll();
} catch (Exception $e) {
    // Fallback
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Notifications Center</title>
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

        /* Notification List */
        .notif-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .notif-item {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.03);
            border-left: 5px solid #cbd5e1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: transform 0.2s;
        }

        .notif-item.unread { border-left-color: #3762c8; background: #f8fafc; }

        .notif-content h4 { font-size: 14px; color: #2c3e50; font-weight: 600; }
        .notif-content span { font-size: 11px; color: #94a3b8; display: block; margin-top: 4px; }
    </style>
</head>
<body>

<?php include 'includes/utilities_sidebar.php'; ?>

<main class="main-content" id="mainContent">
    <div class="card">
        
        <!-- Header -->
        <div class="dashboard-header">
            <div>
                <h1><i class="fas fa-bell"></i> Notifications Center</h1>
                <p style="color: #64748b; font-size: 14px; margin-top: 5px;">Manage confirmations, warnings, and maintenance progress updates.</p>
            </div>
            <div>
                <a href="citizen.php" class="btn btn-outline"><i class="fas fa-chevron-left"></i> Home</a>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Notifications Box -->
        <div class="notif-list">
            <?php if (empty($notifications)): ?>
                <div style="text-align:center; padding:40px; color:#64748b; background:white; border-radius:10px; border:1px solid #edf2f7;">Your notification inbox is empty.</div>
            <?php else: ?>
                <?php foreach ($notifications as $n): ?>
                    <div class="notif-item <?php echo $n['read_status'] == 0 ? 'unread' : ''; ?>">
                        <div class="notif-content">
                            <h4><?php echo htmlspecialchars($n['message']); ?></h4>
                            <span>Filed: <?php echo date('M d, Y h:i A', strtotime($n['created_at'])); ?></span>
                        </div>

                        <div style="display:flex; gap:10px;">
                            <?php if ($n['read_status'] == 0): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="id" value="<?php echo $n['id']; ?>">
                                    <button type="submit" class="btn btn-outline" style="padding:6px 12px; font-size:11px;"><i class="fas fa-check"></i> Mark Read</button>
                                </form>
                            <?php endif; ?>
                            
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this notification?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $n['id']; ?>">
                                <button type="submit" class="btn-outline" style="border:none; cursor:pointer; color:#e74c3c; font-size:12px;"><i class="fas fa-trash"></i> Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</main>

</body>
</html>
