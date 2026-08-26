<?php
// waste_notifications.php
require_once 'includes/auth.php';
require_once 'includes/db.php';
if (!isLoggedIn()) { header('Location: login.php'); exit(); }

// Mark all as read if requested
if (isset($_GET['mark_all_read'])) {
    try { $pdo->exec("UPDATE waste_notifications SET is_read=1"); } catch(Throwable $e){}
    header('Location: waste_notifications.php'); exit();
}
// Mark single as read
if (isset($_GET['read'])) {
    $id = intval($_GET['read']);
    try { $pdo->prepare("UPDATE waste_notifications SET is_read=1 WHERE id=?")->execute([$id]); } catch(Throwable $e){}
    header('Location: waste_notifications.php'); exit();
}
// Delete
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='delete') {
    $id = intval($_POST['id'] ?? 0);
    try { $pdo->prepare("DELETE FROM waste_notifications WHERE id=?")->execute([$id]); } catch(Throwable $e){}
    header('Location: waste_notifications.php'); exit();
}

$fType = $_GET['type'] ?? '';
$fRead = $_GET['read_filter'] ?? '';
$where = ['1=1']; $params = [];
if($fType) { $where[] = 'type=?'; $params[] = $fType; }
if($fRead==='unread') { $where[] = 'is_read=0'; }
if($fRead==='read')   { $where[] = 'is_read=1'; }

$stmt = $pdo->prepare("SELECT * FROM waste_notifications WHERE ".implode(' AND ',$where)." ORDER BY created_at DESC LIMIT 100");
$stmt->execute($params);
$notifs = $stmt->fetchAll();
$unreadCount = $pdo->query("SELECT COUNT(*) FROM waste_notifications WHERE is_read=0")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>(function(){const t=localStorage.getItem('theme')||'light';if(t==='dark')document.documentElement.classList.add('dark-theme')})();</script>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Waste Notifications — UMAN_</title>
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Poppins',sans-serif}
        body{min-height:100vh;display:flex;background:url("assets/images/cityhall.jpeg") center/cover no-repeat fixed}
        body::before{content:"";position:fixed;inset:0;backdrop-filter:blur(6px);background:rgba(0,0,0,0.35);z-index:0}
        .main-content{flex:1;margin-left:280px;padding:28px 36px;z-index:1;position:relative}
        .main-content.collapsed{margin-left:90px}
        .card{background:rgba(255,255,255,0.92);backdrop-filter:blur(18px);border-radius:20px;padding:32px;color:#1e293b;box-shadow:0 8px 32px rgba(0,0,0,0.18);border:1px solid rgba(255,255,255,0.3)}
        .page-header{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px;margin-bottom:20px}
        .page-header h1{font-size:24px;font-weight:700;color:#15803d;display:flex;align-items:center;gap:12px}
        .btn{padding:9px 18px;border-radius:9px;font-weight:600;font-size:13px;border:none;cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;gap:7px;text-decoration:none}
        .btn-green{background:linear-gradient(135deg,#16a34a,#15803d);color:#fff}
        .btn-outline{background:#fff;border:1px solid #e2e8f0;color:#64748b}
        .btn-outline:hover{background:#f8fafc}
        .btn-sm{padding:6px 14px;font-size:12px}
        .filter-bar{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;align-items:center}
        .filter-bar select{padding:9px 12px;border-radius:9px;border:1px solid #e2e8f0;font-size:13px;font-family:'Poppins',sans-serif;outline:none;background:#fff}
        /* Notification items */
        .notif-list{display:flex;flex-direction:column;gap:10px}
        .notif-item{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:16px 20px;display:flex;gap:14px;align-items:flex-start;transition:all .2s;position:relative}
        .notif-item:hover{box-shadow:0 4px 14px rgba(0,0,0,.08);transform:translateY(-1px)}
        .notif-item.unread{border-left:4px solid #16a34a;background:linear-gradient(135deg,#f0fdf4,#ffffff)}
        .notif-item.unread.complaint{border-left-color:#dc2626;background:linear-gradient(135deg,#fef2f2,#ffffff)}
        .notif-item.unread.missed{border-left-color:#ea580c;background:linear-gradient(135deg,#fff7ed,#ffffff)}
        .notif-icon{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0}
        .notif-icon.general{background:linear-gradient(135deg,#f0fdf4,#dcfce7);color:#16a34a}
        .notif-icon.missed{background:linear-gradient(135deg,#fff7ed,#ffedd5);color:#ea580c}
        .notif-icon.complaint{background:linear-gradient(135deg,#fef2f2,#fee2e2);color:#dc2626}
        .notif-icon.facility{background:linear-gradient(135deg,#eff6ff,#dbeafe);color:#2563eb}
        .notif-icon.compliance{background:linear-gradient(135deg,#faf5ff,#ede9fe);color:#7c3aed}
        .notif-content{flex:1}
        .notif-msg{font-size:13px;color:#1e293b;font-weight:500;line-height:1.5}
        .notif-meta{font-size:11px;color:#94a3b8;margin-top:4px;display:flex;align-items:center;gap:8px}
        .notif-type-badge{display:inline-block;padding:1px 8px;border-radius:10px;font-size:10px;font-weight:700;background:#f0fdf4;color:#16a34a}
        .notif-actions{display:flex;gap:6px;flex-shrink:0}
        .notif-dot{width:8px;height:8px;border-radius:50%;background:#16a34a;flex-shrink:0;margin-top:6px}
        .notif-dot.read{background:#e2e8f0}
        .empty-state{text-align:center;padding:40px;color:#94a3b8}
        .empty-state i{font-size:40px;display:block;margin-bottom:12px;opacity:.3}
        .unread-badge{display:inline-flex;align-items:center;gap:6px;background:#fef2f2;border:1px solid #fecaca;color:#dc2626;padding:5px 12px;border-radius:20px;font-size:12px;font-weight:600;margin-left:8px}
        .dark-theme .card{background:rgba(15,23,42,0.92);color:#e2e8f0}
        .dark-theme .notif-item{background:#1e293b;border-color:#334155}
        .dark-theme .notif-item.unread{background:linear-gradient(135deg,#14532d20,#1e293b)}
        .dark-theme .notif-msg{color:#f1f5f9}
        .dark-theme .filter-bar select{background:#1e293b;border-color:#475569;color:#f8fafc}
        @media(max-width:768px){.main-content{margin-left:0;padding:16px}}
    </style>
</head>
<body>
<?php include 'includes/utilities_sidebar.php'; ?>
<main class="main-content" id="mainContent">
<div class="card">

    <div class="page-header">
        <div>
            <h1>
                <i class="fas fa-bell"></i> Waste Notifications
                <?php if($unreadCount > 0): ?>
                <span class="unread-badge"><i class="fas fa-circle" style="font-size:8px"></i><?= $unreadCount ?> unread</span>
                <?php endif; ?>
            </h1>
            <p>System alerts for missed collections, complaints, and facility status.</p>
        </div>
        <div style="display:flex;gap:10px">
            <a href="waste_dashboard.php" class="btn btn-outline btn-sm"><i class="fas fa-chevron-left"></i> Dashboard</a>
            <?php if($unreadCount > 0): ?>
            <a href="?mark_all_read=1" class="btn btn-green btn-sm"><i class="fas fa-check-double"></i> Mark All Read</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" class="filter-bar">
        <select name="type" onchange="this.form.submit()">
            <option value="">All Types</option>
            <?php foreach(['General','Missed Collection','New Complaint','Facility Alert','Compliance Alert'] as $t): ?>
            <option value="<?= $t ?>" <?= $fType===$t?'selected':'' ?>><?= $t ?></option>
            <?php endforeach; ?>
        </select>
        <select name="read_filter" onchange="this.form.submit()">
            <option value="">All</option>
            <option value="unread" <?= $fRead==='unread'?'selected':'' ?>>Unread Only</option>
            <option value="read" <?= $fRead==='read'?'selected':'' ?>>Read Only</option>
        </select>
        <a href="waste_notifications.php" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Clear</a>
    </form>

    <!-- Notification List -->
    <?php if(empty($notifs)): ?>
    <div class="empty-state">
        <i class="fas fa-bell-slash"></i>
        <strong>No notifications</strong><br>
        <small>System alerts will appear here when events occur.</small>
    </div>
    <?php else: ?>
    <div class="notif-list">
        <?php foreach($notifs as $n):
            $isUnread = !$n['is_read'];
            $iconClass = match($n['type']){
                'Missed Collection'=>'missed','New Complaint'=>'complaint',
                'Facility Alert'=>'facility','Compliance Alert'=>'compliance',default=>'general'
            };
            $icon = match($n['type']){
                'Missed Collection'=>'fa-truck','New Complaint'=>'fa-map-pin',
                'Facility Alert'=>'fa-building','Compliance Alert'=>'fa-clipboard-check',default=>'fa-bell'
            };
            $itemClass = $isUnread ? ('unread '.$iconClass) : '';
        ?>
        <div class="notif-item <?= $itemClass ?>">
            <div class="notif-icon <?= $iconClass ?>"><i class="fas <?= $icon ?>"></i></div>
            <div class="notif-content">
                <div class="notif-msg"><?= htmlspecialchars($n['message']) ?></div>
                <div class="notif-meta">
                    <span class="notif-type-badge"><?= htmlspecialchars($n['type']) ?></span>
                    <span><i class="fas fa-clock"></i> <?= date('M j, Y · g:i A', strtotime($n['created_at'])) ?></span>
                    <?php if($isUnread): ?><span style="color:#16a34a;font-weight:600">● Unread</span><?php endif; ?>
                </div>
            </div>
            <div class="notif-actions">
                <?php if($isUnread): ?>
                <a href="?read=<?= $n['id'] ?>" class="btn btn-sm btn-outline" title="Mark as read"><i class="fas fa-check"></i></a>
                <?php endif; ?>
                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this notification?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $n['id'] ?>">
                    <button type="submit" class="btn btn-sm" style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626"><i class="fas fa-trash"></i></button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>
</main>
<script>
const sb=document.getElementById('sidebar-nav'),mc=document.getElementById('mainContent');
if(sb){new MutationObserver(()=>mc.classList.toggle('collapsed',sb.classList.contains('collapsed'))).observe(sb,{attributes:true,attributeFilter:['class']})}
</script>
</body></html>
