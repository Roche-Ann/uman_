<?php
// waste_schedules.php
require_once 'includes/auth.php';
require_once 'includes/db.php';
if (!isLoggedIn()) { header('Location: login.php'); exit(); }

$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $zone    = trim($_POST['zone_name'] ?? '');
        $brgy    = trim($_POST['barangay'] ?? '');
        $day     = $_POST['day_of_week'] ?? '';
        $time    = $_POST['time_slot'] ?? '6:00 AM';
        $truckId = !empty($_POST['truck_id']) ? intval($_POST['truck_id']) : null;
        $routeId = !empty($_POST['route_id']) ? intval($_POST['route_id']) : null;
        $type    = $_POST['waste_type'] ?? 'Mixed';

        if (empty($zone) || empty($day)) { $error = 'Zone name and day are required.'; }
        else {
            try {
                $pdo->prepare("INSERT INTO waste_schedules(zone_name,barangay,day_of_week,time_slot,truck_id,route_id,waste_type) VALUES(?,?,?,?,?,?,?)")
                    ->execute([$zone,$brgy,$day,$time,$truckId,$routeId,$type]);
                $success = "Schedule for {$zone} added!";
            } catch(PDOException $e){ $error = $e->getMessage(); }
        }
    } elseif ($action === 'update_status') {
        $id     = intval($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        try {
            $pdo->prepare("UPDATE waste_schedules SET status=? WHERE id=?")->execute([$status,$id]);
            if ($status === 'Missed') {
                $row = $pdo->prepare("SELECT zone_name FROM waste_schedules WHERE id=?");
                $row->execute([$id]); $r = $row->fetch();
                $pdo->prepare("INSERT INTO waste_notifications(message,type) VALUES(?,?)")
                    ->execute(["Missed collection schedule: {$r['zone_name']}.",'Missed Collection']);
            }
            $success = "Status updated.";
        } catch(PDOException $e){ $error = $e->getMessage(); }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        try { $pdo->prepare("DELETE FROM waste_schedules WHERE id=?")->execute([$id]); $success = "Schedule deleted."; }
        catch(PDOException $e){ $error = $e->getMessage(); }
    }
}

$schedules = $pdo->query("
    SELECT s.*,t.plate_number,r.route_name,r.color_hex
    FROM waste_schedules s
    LEFT JOIN waste_trucks t ON s.truck_id=t.id
    LEFT JOIN waste_routes r ON s.route_id=r.id
    ORDER BY FIELD(s.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), s.time_slot
")->fetchAll();

$trucks = $pdo->query("SELECT * FROM waste_trucks ORDER BY truck_id")->fetchAll();
$routes = $pdo->query("SELECT * FROM waste_routes WHERE is_active=1 ORDER BY id")->fetchAll();
$days   = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>(function(){const t=localStorage.getItem('theme')||'light';if(t==='dark')document.documentElement.classList.add('dark-theme')})();</script>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Collection Schedules — UMAN_</title>
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
        .alert{padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:13px;font-weight:500}
        .alert-success{background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d}
        .alert-error{background:#fef2f2;border:1px solid #fecaca;color:#dc2626}
        .btn{padding:9px 18px;border-radius:9px;font-weight:600;font-size:13px;border:none;cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;gap:7px;text-decoration:none}
        .btn-green{background:linear-gradient(135deg,#16a34a,#15803d);color:#fff;box-shadow:0 3px 10px rgba(22,163,74,.3)}
        .btn-green:hover{transform:translateY(-1px)}
        .btn-outline{background:#fff;border:1px solid #e2e8f0;color:#64748b}
        .btn-outline:hover{background:#f8fafc}
        .btn-sm{padding:6px 14px;font-size:12px}
        /* Day accordion */
        .day-section{margin-bottom:16px;border-radius:14px;overflow:hidden;border:1px solid #e2e8f0}
        .day-header{background:linear-gradient(135deg,#f0fdf4,#dcfce7);padding:14px 20px;font-weight:700;font-size:14px;color:#15803d;display:flex;align-items:center;gap:10px;cursor:pointer;user-select:none}
        .day-header .day-count{margin-left:auto;background:#16a34a;color:#fff;padding:2px 10px;border-radius:20px;font-size:11px}
        .day-body{background:#fff;padding:0}
        table{width:100%;border-collapse:collapse;font-size:13px}
        th{background:#f8fafc;padding:11px 14px;text-align:left;font-weight:600;color:#475569;font-size:12px;border-bottom:1px solid #e2e8f0}
        td{padding:11px 14px;border-bottom:1px solid #f8fafc;color:#374151;vertical-align:middle}
        tr:last-child td{border-bottom:none}
        tr:hover td{background:#f0fdf4}
        .badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600}
        .badge-active{background:#f0fdf4;color:#16a34a}
        .badge-missed{background:#fef2f2;color:#dc2626}
        .badge-completed{background:#eff6ff;color:#2563eb}
        .badge-suspended{background:#f8fafc;color:#94a3b8}
        .route-pill{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;color:#fff}
        .status-select{padding:5px 10px;border-radius:8px;border:1px solid #e2e8f0;font-size:12px;font-family:'Poppins',sans-serif;outline:none;background:#fff}
        .empty-state{text-align:center;padding:30px;color:#94a3b8;font-size:13px}
        .empty-state i{font-size:28px;display:block;margin-bottom:8px;opacity:.4}
        /* Modal */
        .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:2000;align-items:center;justify-content:center;padding:20px}
        .modal-overlay.show{display:flex}
        .modal{background:#fff;border-radius:20px;width:100%;max-width:520px;box-shadow:0 20px 60px rgba(0,0,0,.25);overflow:hidden;animation:mIn .25s ease}
        @keyframes mIn{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
        .modal-header{padding:20px 24px;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center}
        .modal-header h2{font-size:17px;font-weight:700;color:#1e293b;display:flex;align-items:center;gap:10px}
        .modal-close{background:none;border:none;font-size:20px;color:#94a3b8;cursor:pointer}
        .modal-body{padding:20px 24px}
        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
        .form-group{margin-bottom:0}
        .form-group.full{grid-column:1/-1}
        .form-group label{display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px}
        .form-control{width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:9px;font-size:13px;font-family:'Poppins',sans-serif;outline:none;transition:border-color .2s}
        .form-control:focus{border-color:#16a34a;box-shadow:0 0 0 3px rgba(22,163,74,.1)}
        .modal-footer{padding:16px 24px;background:#f8fafc;border-top:1px solid #f1f5f9;display:flex;gap:10px;justify-content:flex-end}
        .dark-theme .card,.dark-theme .modal{background:rgba(15,23,42,0.92);color:#e2e8f0}
        .dark-theme .day-section{border-color:#334155}
        .dark-theme .day-header{background:linear-gradient(135deg,#14532d,#166534)}
        .dark-theme .day-body,.dark-theme table{background:#1e293b}
        .dark-theme th{background:#0f172a;color:#94a3b8}
        .dark-theme td{color:#cbd5e1;border-color:#334155}
        .dark-theme .status-select,.dark-theme .form-control{background:#1e293b;border-color:#475569;color:#f8fafc}
        @media(max-width:768px){.main-content{margin-left:0;padding:16px}.form-grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
<?php include 'includes/utilities_sidebar.php'; ?>
<main class="main-content" id="mainContent">
<div class="card">

    <div class="page-header">
        <div>
            <h1><i class="fas fa-calendar-alt"></i> Collection Schedules</h1>
            <p>Manage garbage collection schedules per zone and day of the week.</p>
        </div>
        <div style="display:flex;gap:10px">
            <a href="waste_dashboard.php" class="btn btn-outline btn-sm"><i class="fas fa-chevron-left"></i> Dashboard</a>
            <button class="btn btn-green btn-sm" onclick="document.getElementById('addModal').classList.add('show')">
                <i class="fas fa-plus"></i> Add Schedule
            </button>
        </div>
    </div>

    <?php if($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>

    <!-- Grouped by day -->
    <?php foreach($days as $day):
        $dayRows = array_filter($schedules, fn($s) => $s['day_of_week'] === $day);
        if(empty($dayRows) && empty($schedules)) continue;
    ?>
    <div class="day-section">
        <div class="day-header" onclick="toggleDay('day-<?= $day ?>')">
            <i class="fas fa-calendar-day"></i> <?= $day ?>
            <span class="day-count"><?= count($dayRows) ?> zone<?= count($dayRows)!=1?'s':'' ?></span>
        </div>
        <div class="day-body" id="day-<?= $day ?>">
            <?php if(empty($dayRows)): ?>
            <div class="empty-state"><i class="fas fa-calendar-times"></i>No schedules for <?= $day ?></div>
            <?php else: ?>
            <table>
                <thead><tr><th>Zone</th><th>Barangay</th><th>Time</th><th>Route</th><th>Truck</th><th>Waste Type</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach($dayRows as $s):
                    $badgeClass = match($s['status']){
                        'Active'=>'badge-active','Missed'=>'badge-missed',
                        'Completed'=>'badge-completed','Suspended'=>'badge-suspended',default=>''
                    };
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($s['zone_name']) ?></strong></td>
                    <td><?= htmlspecialchars($s['barangay']??'—') ?></td>
                    <td><i class="fas fa-clock" style="color:#16a34a"></i> <?= htmlspecialchars($s['time_slot']) ?></td>
                    <td>
                        <?php if($s['route_name']): ?>
                        <span class="route-pill" style="background:<?= htmlspecialchars($s['color_hex']??'#16a34a') ?>"><?= htmlspecialchars($s['route_name']) ?></span>
                        <?php else: ?><span style="color:#94a3b8">—</span><?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($s['plate_number']??'—') ?></td>
                    <td><?= htmlspecialchars($s['waste_type']) ?></td>
                    <td>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <select name="status" class="status-select" onchange="this.form.submit()">
                                <?php foreach(['Active','Completed','Missed','Rescheduled','Suspended'] as $st): ?>
                                <option <?= $s['status']===$st?'selected':'' ?>><?= $st ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </td>
                    <td>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this schedule?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button type="submit" class="btn btn-sm" style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626;padding:5px 10px"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if(empty($schedules)): ?>
    <div class="empty-state"><i class="fas fa-calendar-plus"></i>No schedules yet. Add the first collection schedule!</div>
    <?php endif; ?>

</div>
</main>

<!-- Add Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header">
            <h2><i class="fas fa-calendar-plus" style="color:#16a34a"></i> New Collection Schedule</h2>
            <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('show')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Zone Name *</label>
                        <input type="text" name="zone_name" class="form-control" placeholder="e.g., Zone 1 – North" required>
                    </div>
                    <div class="form-group">
                        <label>Barangay</label>
                        <input type="text" name="barangay" class="form-control" placeholder="e.g., Holy Spirit">
                    </div>
                    <div class="form-group">
                        <label>Day of Week *</label>
                        <select name="day_of_week" class="form-control" required>
                            <?php foreach($days as $d): ?><option><?= $d ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Time Slot</label>
                        <input type="text" name="time_slot" class="form-control" value="6:00 AM" placeholder="6:00 AM">
                    </div>
                    <div class="form-group">
                        <label>Assigned Truck</label>
                        <select name="truck_id" class="form-control">
                            <option value="">— None —</option>
                            <?php foreach($trucks as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['truck_id'].' - '.$t['plate_number']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Route</label>
                        <select name="route_id" class="form-control">
                            <option value="">— None —</option>
                            <?php foreach($routes as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['route_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group full">
                        <label>Waste Type</label>
                        <select name="waste_type" class="form-control">
                            <option>Mixed</option><option>Biodegradable</option><option>Non-biodegradable</option><option>Recyclable</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('addModal').classList.remove('show')">Cancel</button>
                <button type="submit" class="btn btn-green btn-sm"><i class="fas fa-save"></i> Save Schedule</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleDay(id){ const el=document.getElementById(id); el.style.display=el.style.display==='none'?'block':'none'; }
const sb=document.getElementById('sidebar-nav'),mc=document.getElementById('mainContent');
if(sb){new MutationObserver(()=>mc.classList.toggle('collapsed',sb.classList.contains('collapsed'))).observe(sb,{attributes:true,attributeFilter:['class']})}
</script>
</body></html>
