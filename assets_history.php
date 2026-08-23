<?php
// assets_history.php — Asset Audit Activity Timeline
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$search      = trim($_GET['search']      ?? '');
$filterDate  = trim($_GET['date']        ?? '');
$filterType  = trim($_GET['action_type'] ?? '');
$page        = max(1, intval($_GET['page'] ?? 1));
$limit       = 40;
$offset      = ($page - 1) * $limit;
$searchParam = '%' . $search . '%';

$where  = [];
$params = [];
if ($search !== '') { $where[] = '(a.asset_id LIKE ? OR a.name LIKE ?)'; $params[] = $searchParam; $params[] = $searchParam; }
if ($filterDate !== '') { $where[] = 'DATE(l.changed_at) = ?'; $params[] = $filterDate; }
if ($filterType !== '') { $where[] = 'l.action_type = ?'; $params[] = $filterType; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM asset_status_logs l LEFT JOIN utility_assets a ON l.utility_asset_id = a.id $whereSql");
    $countStmt->execute($params);
    $totalLogs  = (int)$countStmt->fetchColumn();
    $totalPages = max(1, ceil($totalLogs / $limit));
} catch (Throwable $e) { $totalLogs = 0; $totalPages = 1; }

$logs = [];
try {
    $stmt = $pdo->prepare("
        SELECT l.id, l.action_type, l.old_status, l.new_status, l.notes, l.changed_fields, l.changed_at,
               a.asset_id, a.name AS asset_name, a.parent_asset_id,
               u.full_name AS user_name
        FROM asset_status_logs l
        LEFT JOIN utility_assets a ON l.utility_asset_id = a.id
        LEFT JOIN users u ON l.changed_by = u.id
        $whereSql
        ORDER BY l.changed_at DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $logs = []; }

$grouped = [];
foreach ($logs as $log) {
    $day = date('Y-m-d', strtotime($log['changed_at']));
    $grouped[$day][] = $log;
}

function actionMeta(string $type): array {
    return match($type) {
        'asset_created'  => ['icon' => 'fa-plus-circle',  'color' => '#10b981', 'bg' => '#ecfdf5', 'label' => 'Asset Registered'],
        'asset_edited'   => ['icon' => 'fa-pen',          'color' => '#3762c8', 'bg' => '#eff6ff', 'label' => 'Details Edited'],
        'status_changed' => ['icon' => 'fa-exchange-alt', 'color' => '#f59e0b', 'bg' => '#fffbeb', 'label' => 'Status Changed'],
        'split_created'  => ['icon' => 'fa-scissors',     'color' => '#8b5cf6', 'bg' => '#f5f3ff', 'label' => 'Quantity Split'],
        'split_merged'   => ['icon' => 'fa-link',         'color' => '#0891b2', 'bg' => '#ecfeff', 'label' => 'Split Merged'],
        default          => ['icon' => 'fa-circle',       'color' => '#94a3b8', 'bg' => '#f8fafc', 'label' => ucwords(str_replace('_',' ',$type))],
    };
}

$actionTypes = [
    '' => 'All Actions',
    'asset_created'  => 'Asset Registered',
    'asset_edited'   => 'Details Edited',
    'status_changed' => 'Status Changed',
    'split_created'  => 'Quantity Split',
    'split_merged'   => 'Split Merged',
];

function pageUrl(int $p, string $search, string $date, string $type): string {
    $q = http_build_query(array_filter(['page'=>$p>1?$p:null,'search'=>$search?:null,'date'=>$date?:null,'action_type'=>$type?:null],fn($v)=>$v!==null&&$v!==''));
    return 'assets_history.php' . ($q ? "?$q" : '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Asset Activity Log — Utilities Management</title>
    <meta name="description" content="Chronological audit timeline of all asset actions.">
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;font-family:'Poppins',sans-serif;}
        body{min-height:100vh;display:flex;background:url("assets/images/cityhall.jpeg") center/cover no-repeat fixed;position:relative;}
        body::before{content:"";position:absolute;inset:0;backdrop-filter:blur(6px);background:rgba(0,0,0,.35);z-index:0;}
        .main-content{flex:1;margin-left:280px;padding:30px 36px;transition:margin-left .25s ease;z-index:1;position:relative;}
        .main-content.collapsed{margin-left:90px;}
        .card{background:rgba(255,255,255,.88);backdrop-filter:blur(18px);border-radius:20px;padding:36px 40px;box-shadow:0 8px 32px rgba(0,0,0,.18);border:1px solid rgba(255,255,255,.3);}
        .page-header{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px;margin-bottom:28px;}
        .page-header h1{font-size:28px;font-weight:700;color:#1e293b;display:flex;align-items:center;gap:12px;}
        .page-header h1 i{color:#3762c8;font-size:24px;}
        .page-header .subtitle{font-size:13px;color:#64748b;margin-top:4px;}
        .btn{padding:9px 18px;border-radius:8px;font-weight:600;font-size:13px;border:none;cursor:pointer;transition:all .25s ease;display:inline-flex;align-items:center;gap:7px;text-decoration:none;}
        .btn-primary{background:#3762c8;color:#fff;} .btn-primary:hover{background:#2851b0;box-shadow:0 4px 12px rgba(55,98,200,.35);}
        .btn-outline{background:transparent;border:1px solid #cbd5e1;color:#64748b;} .btn-outline:hover{background:#f1f5f9;color:#1e293b;}
        .filter-bar{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:18px 22px;margin-bottom:28px;display:flex;flex-wrap:wrap;gap:14px;align-items:flex-end;box-shadow:0 2px 8px rgba(0,0,0,.05);}
        .filter-group{display:flex;flex-direction:column;gap:5px;flex:1;min-width:160px;}
        .filter-group label{font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.6px;}
        .form-control{padding:9px 13px;border-radius:8px;border:1px solid #cbd5e1;font-size:13px;color:#1e293b;background:#f8fafc;outline:none;transition:border-color .2s;width:100%;}
        .form-control:focus{border-color:#3762c8;background:#fff;box-shadow:0 0 0 3px rgba(55,98,200,.12);}
        .stats-row{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:28px;}
        .stat-chip{display:flex;align-items:center;gap:10px;padding:12px 18px;border-radius:12px;border:1px solid transparent;font-size:13px;font-weight:600;}
        .stat-chip i{font-size:18px;} .stat-chip .stat-num{font-size:22px;font-weight:700;}
        .day-group{margin-bottom:36px;}
        .day-label{display:flex;align-items:center;gap:12px;margin-bottom:16px;padding-bottom:10px;border-bottom:2px solid #e2e8f0;}
        .day-label .day-date{font-size:15px;font-weight:700;color:#1e293b;}
        .day-label .day-count{font-size:11px;background:#e2e8f0;color:#475569;padding:2px 9px;border-radius:99px;font-weight:600;}
        .day-label .day-today{font-size:11px;background:#dcfce7;color:#166534;padding:2px 9px;border-radius:99px;font-weight:700;}
        .timeline{position:relative;padding-left:36px;}
        .timeline::before{content:'';position:absolute;left:13px;top:0;bottom:0;width:2px;background:#e2e8f0;}
        .timeline-item{position:relative;margin-bottom:18px;animation:fadeIn .3s ease both;}
        @keyframes fadeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
        .tl-dot{position:absolute;left:-29px;top:14px;width:14px;height:14px;border-radius:50%;border:2px solid #fff;box-shadow:0 0 0 3px rgba(0,0,0,.08);}
        .tl-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px 18px;transition:box-shadow .2s,transform .2s;}
        .tl-card:hover{box-shadow:0 4px 16px rgba(0,0,0,.09);transform:translateX(3px);}
        .tl-card-header{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;flex-wrap:wrap;}
        .tl-action-badge{display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;flex-shrink:0;}
        .tl-asset-name{font-size:14px;font-weight:600;color:#1e293b;margin-top:4px;}
        .tl-asset-id{font-size:12px;color:#94a3b8;font-family:monospace;margin-top:1px;}
        .tl-meta{font-size:11px;color:#94a3b8;flex-shrink:0;text-align:right;}
        .tl-meta .tl-user{font-weight:600;color:#64748b;}
        .status-flow{display:flex;align-items:center;gap:8px;margin-top:10px;flex-wrap:wrap;}
        .status-pill{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:700;}
        .sp-operational{background:#e2fbe8;color:#1e7e34;} .sp-damaged{background:#fde8e8;color:#bd2130;}
        .sp-needsinspection{background:#fef9e7;color:#d39e00;} .sp-undermaintenance{background:#f3e5f5;color:#7b1fa2;}
        .sp-retired{background:#f1f5f9;color:#475569;} .sp-null,.sp-{background:#f1f5f9;color:#94a3b8;}
        .diff-table{width:100%;border-collapse:collapse;margin-top:10px;font-size:12px;}
        .diff-table th{background:#f8fafc;color:#64748b;font-weight:600;padding:5px 10px;text-align:left;border-bottom:1px solid #e2e8f0;}
        .diff-table td{padding:5px 10px;border-bottom:1px solid #f1f5f9;color:#475569;vertical-align:top;}
        .diff-table tr:last-child td{border-bottom:none;}
        .diff-old{color:#be123c;background:#fff1f2;padding:1px 5px;border-radius:4px;font-family:monospace;}
        .diff-new{color:#15803d;background:#f0fdf4;padding:1px 5px;border-radius:4px;font-family:monospace;}
        .diff-field{font-weight:600;color:#334155;}
        .tl-notes{margin-top:9px;font-size:12px;color:#64748b;background:#f8fafc;border-left:3px solid #cbd5e1;padding:6px 10px;border-radius:0 6px 6px 0;font-style:italic;}
        .empty-state{text-align:center;padding:60px 20px;color:#94a3b8;}
        .empty-state i{font-size:48px;margin-bottom:16px;color:#cbd5e1;display:block;}
        .empty-state h3{font-size:18px;color:#64748b;margin-bottom:8px;}
        .pagination-row{display:flex;justify-content:space-between;align-items:center;margin-top:32px;flex-wrap:wrap;gap:12px;}
        .pagination-info{font-size:13px;color:#64748b;}
        .pagination-links{display:flex;gap:6px;flex-wrap:wrap;}
        .page-link{padding:6px 13px;border-radius:7px;border:1px solid #e2e8f0;text-decoration:none;color:#64748b;font-size:13px;font-weight:500;transition:all .2s;}
        .page-link:hover{border-color:#3762c8;color:#3762c8;background:#eff6ff;}
        .page-link.active{background:#3762c8;color:#fff;border-color:#3762c8;}
        .page-link.disabled{opacity:.4;pointer-events:none;}
        /* Dark mode */
        .dark-theme .card{background:rgba(15,23,42,.95)!important;border-color:rgba(255,255,255,.08)!important;}
        .dark-theme .page-header h1{color:#f8fafc!important;} .dark-theme .page-header .subtitle{color:#94a3b8!important;}
        .dark-theme .filter-bar{background:#1e293b!important;border-color:#334155!important;}
        .dark-theme .filter-group label{color:#94a3b8!important;}
        .dark-theme .form-control{background:#151f32!important;border-color:#475569!important;color:#f8fafc!important;}
        .dark-theme .day-label{border-bottom-color:#334155!important;} .dark-theme .day-label .day-date{color:#f8fafc!important;}
        .dark-theme .day-label .day-count{background:#334155!important;color:#94a3b8!important;}
        .dark-theme .timeline::before{background:#334155!important;}
        .dark-theme .tl-card{background:#1e293b!important;border-color:#334155!important;}
        .dark-theme .tl-asset-name{color:#f8fafc!important;} .dark-theme .tl-asset-id{color:#64748b!important;}
        .dark-theme .tl-meta .tl-user{color:#94a3b8!important;}
        .dark-theme .diff-table th{background:#151f32!important;color:#64748b!important;border-bottom-color:#334155!important;}
        .dark-theme .diff-table td{color:#94a3b8!important;border-bottom-color:#1e293b!important;}
        .dark-theme .diff-field{color:#cbd5e1!important;}
        .dark-theme .tl-notes{background:#151f32!important;border-left-color:#475569!important;color:#94a3b8!important;}
        .dark-theme .stat-chip{border-color:#334155!important;}
        .dark-theme .pagination-info{color:#64748b!important;}
        .dark-theme .page-link{background:#1e293b!important;border-color:#334155!important;color:#94a3b8!important;}
        .dark-theme .page-link:hover{border-color:#3762c8!important;color:#93c5fd!important;}
        .dark-theme .page-link.active{background:#3762c8!important;color:#fff!important;border-color:#3762c8!important;}
    </style>
</head>
<body>
<?php include 'includes/utilities_sidebar.php'; ?>
<main class="main-content" id="mainContent">
<div class="card">

    <div class="page-header">
        <div>
            <h1><i class="fas fa-scroll"></i> Asset Activity Log</h1>
            <p class="subtitle">Chronological audit of every action taken on utility assets — registrations, edits, status changes, splits &amp; merges.</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="assets_crud.php" class="btn btn-outline"><i class="fas fa-boxes"></i> Inventory</a>
            <a href="assets_dashboard.php" class="btn btn-outline"><i class="fas fa-chart-pie"></i> Dashboard</a>
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" class="filter-bar">
        <div class="filter-group" style="flex:2;min-width:200px;">
            <label><i class="fas fa-search" style="margin-right:4px;"></i> Search Asset</label>
            <input type="text" name="search" class="form-control" placeholder="Asset ID or name…" value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="filter-group">
            <label><i class="fas fa-calendar-day" style="margin-right:4px;"></i> Date</label>
            <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($filterDate); ?>">
        </div>
        <div class="filter-group">
            <label><i class="fas fa-tag" style="margin-right:4px;"></i> Action Type</label>
            <select name="action_type" class="form-control">
                <?php foreach ($actionTypes as $val => $lbl): ?>
                    <option value="<?php echo $val; ?>" <?php echo $filterType === $val ? 'selected' : ''; ?>><?php echo htmlspecialchars($lbl); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:flex;gap:8px;align-self:flex-end;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
            <a href="assets_history.php" class="btn btn-outline"><i class="fas fa-times"></i> Clear</a>
        </div>
    </form>

    <!-- Stats chips -->
    <?php
    try { $statsRows = $pdo->query("SELECT action_type, COUNT(*) as cnt FROM asset_status_logs GROUP BY action_type")->fetchAll(PDO::FETCH_KEY_PAIR); }
    catch (Throwable $e) { $statsRows = []; }
    $chipDefs = [
        ['asset_created','#10b981','#ecfdf5','fa-plus-circle'],
        ['status_changed','#f59e0b','#fffbeb','fa-exchange-alt'],
        ['asset_edited','#3762c8','#eff6ff','fa-pen'],
        ['split_created','#8b5cf6','#f5f3ff','fa-scissors'],
        ['split_merged','#0891b2','#ecfeff','fa-link'],
    ];
    ?>
    <div class="stats-row">
        <?php foreach ($chipDefs as [$type,$color,$bg,$icon]): ?>
        <div class="stat-chip" style="background:<?php echo $bg;?>;border-color:<?php echo $color;?>22;color:<?php echo $color;?>;">
            <i class="fas <?php echo $icon;?>"></i>
            <div><div class="stat-num"><?php echo number_format($statsRows[$type]??0);?></div><div style="font-size:10px;font-weight:600;opacity:.75;"><?php echo $actionTypes[$type]??$type;?></div></div>
        </div>
        <?php endforeach;?>
        <div class="stat-chip" style="background:#f8fafc;border-color:#e2e8f0;color:#475569;margin-left:auto;">
            <i class="fas fa-list-ol"></i>
            <div><div class="stat-num"><?php echo number_format($totalLogs);?></div><div style="font-size:10px;font-weight:600;opacity:.75;">Filtered Results</div></div>
        </div>
    </div>

    <!-- Timeline -->
    <?php if (empty($grouped)): ?>
        <div class="empty-state">
            <i class="fas fa-scroll"></i>
            <h3>No activity logs found</h3>
            <p>Try adjusting your filters or perform some asset actions to generate logs.</p>
        </div>
    <?php else: ?>
        <?php foreach ($grouped as $day => $entries):
            $ts = strtotime($day);
            $today = date('Y-m-d');
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            if ($day === $today)         $dayLabel = 'Today — ' . date('F j, Y', $ts);
            elseif ($day === $yesterday) $dayLabel = 'Yesterday — ' . date('F j, Y', $ts);
            else                         $dayLabel = date('l, F j, Y', $ts);
        ?>
        <div class="day-group">
            <div class="day-label">
                <i class="fas fa-calendar-alt" style="color:#3762c8;font-size:15px;"></i>
                <span class="day-date"><?php echo $dayLabel;?></span>
                <?php if ($day === $today): ?><span class="day-today"><i class="fas fa-circle" style="font-size:7px;"></i> Today</span><?php endif;?>
                <span class="day-count"><?php echo count($entries);?> action<?php echo count($entries)!==1?'s':'';?></span>
            </div>
            <div class="timeline">
            <?php foreach ($entries as $log):
                $meta = actionMeta($log['action_type'] ?? 'status_changed');
                $diff = [];
                if (!empty($log['changed_fields'])) {
                    $decoded = json_decode($log['changed_fields'], true);
                    if (is_array($decoded)) $diff = $decoded;
                }
                $spClass = fn(string $s) => 'sp-' . strtolower(preg_replace('/[^a-zA-Z]/','',$s));
            ?>
            <div class="timeline-item">
                <div class="tl-dot" style="background:<?php echo $meta['color'];?>;"></div>
                <div class="tl-card" style="border-left:3px solid <?php echo $meta['color'];?>;">
                    <div class="tl-card-header">
                        <div>
                            <div><span class="tl-action-badge" style="background:<?php echo $meta['bg'];?>;color:<?php echo $meta['color'];?>;"><i class="fas <?php echo $meta['icon'];?>"></i><?php echo $meta['label'];?></span></div>
                            <div class="tl-asset-name">
                                <?php echo htmlspecialchars($log['asset_name'] ?? '(Deleted Asset)');?>
                                <?php if (!empty($log['parent_asset_id'])): ?><span style="font-size:10px;background:#fef3c7;color:#92400e;padding:1px 6px;border-radius:4px;margin-left:6px;font-weight:700;">OFFSHOOT</span><?php endif;?>
                            </div>
                            <div class="tl-asset-id"><?php echo htmlspecialchars($log['asset_id'] ?? '—');?></div>
                        </div>
                        <div class="tl-meta">
                            <div><?php echo date('h:i A', strtotime($log['changed_at']));?></div>
                            <div class="tl-user"><?php echo htmlspecialchars($log['user_name'] ?? 'System');?></div>
                        </div>
                    </div>

                    <?php if (($log['old_status'] ?? '') !== '' || ($log['new_status'] ?? '') !== ''): ?>
                    <div class="status-flow">
                        <span class="status-pill <?php echo $spClass($log['old_status'] ?? '');?>"><?php echo htmlspecialchars($log['old_status'] ?: 'None');?></span>
                        <i class="fas fa-arrow-right" style="color:#94a3b8;font-size:11px;"></i>
                        <span class="status-pill <?php echo $spClass($log['new_status'] ?? '');?>"><?php echo htmlspecialchars($log['new_status'] ?: 'N/A');?></span>
                    </div>
                    <?php endif;?>

                    <?php if (!empty($diff)): ?>
                    <table class="diff-table">
                        <thead><tr><th style="width:140px;">Field</th><th>Before</th><th>After</th></tr></thead>
                        <tbody>
                            <?php foreach ($diff as $field => $change): ?>
                            <tr>
                                <td class="diff-field"><?php echo htmlspecialchars($field);?></td>
                                <td><span class="diff-old"><?php echo htmlspecialchars($change['old'] ?: '—');?></span></td>
                                <td><span class="diff-new"><?php echo htmlspecialchars($change['new'] ?: '—');?></span></td>
                            </tr>
                            <?php endforeach;?>
                        </tbody>
                    </table>
                    <?php endif;?>

                    <?php if (!empty($log['notes'])): ?>
                    <div class="tl-notes"><i class="fas fa-comment-alt" style="margin-right:5px;"></i><?php echo htmlspecialchars($log['notes']);?></div>
                    <?php endif;?>
                </div>
            </div>
            <?php endforeach;?>
            </div>
        </div>
        <?php endforeach;?>

        <?php if ($totalPages > 1): ?>
        <div class="pagination-row">
            <div class="pagination-info">Showing <?php echo number_format($offset+1);?>–<?php echo number_format(min($totalLogs,$offset+$limit));?> of <?php echo number_format($totalLogs);?> entries</div>
            <div class="pagination-links">
                <a href="<?php echo pageUrl($page-1,$search,$filterDate,$filterType);?>" class="page-link <?php echo $page<=1?'disabled':'';?>"><i class="fas fa-chevron-left"></i></a>
                <?php $s=max(1,$page-2);$e=min($totalPages,$page+2);
                if($s>1){echo '<a href="'.pageUrl(1,$search,$filterDate,$filterType).'" class="page-link">1</a>';if($s>2)echo '<span class="page-link disabled">…</span>';}
                for($i=$s;$i<=$e;$i++){echo '<a href="'.pageUrl($i,$search,$filterDate,$filterType).'" class="page-link '.($i===$page?'active':'').'">'.$i.'</a>';}
                if($e<$totalPages){if($e<$totalPages-1)echo '<span class="page-link disabled">…</span>';echo '<a href="'.pageUrl($totalPages,$search,$filterDate,$filterType).'" class="page-link">'.$totalPages.'</a>';}?>
                <a href="<?php echo pageUrl($page+1,$search,$filterDate,$filterType);?>" class="page-link <?php echo $page>=$totalPages?'disabled':'';?>"><i class="fas fa-chevron-right"></i></a>
            </div>
        </div>
        <?php endif;?>
    <?php endif;?>

</div>
</main>
<script>
    const sidebar = document.querySelector('.sidebar-nav');
    const main    = document.getElementById('mainContent');
    if (sidebar && main && localStorage.getItem('sidebarCollapsed') === 'true') {
        sidebar.classList.add('collapsed');
        main.classList.add('collapsed');
    }
</script>
</body>
</html>
