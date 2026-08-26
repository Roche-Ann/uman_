<?php
// waste_records.php
require_once 'includes/auth.php';
require_once 'includes/db.php';
if (!isLoggedIn()) { header('Location: login.php'); exit(); }

$error = ''; $success = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $truckId  = !empty($_POST['truck_id']) ? intval($_POST['truck_id']) : null;
        $routeId  = !empty($_POST['route_id']) ? intval($_POST['route_id']) : null;
        $date     = $_POST['date_collected'] ?? '';
        $volume   = floatval($_POST['volume_kg'] ?? 0);
        $type     = $_POST['waste_type'] ?? 'Mixed';
        $crew     = intval($_POST['crew_count'] ?? 3);
        $status   = $_POST['collection_status'] ?? 'Completed';
        $notes    = trim($_POST['notes'] ?? '');

        if (empty($date) || $volume < 0) {
            $error = 'Date and volume are required.';
        } else {
            try {
                $prefix = 'WST-'.date('Ym').'-';
                $count  = $pdo->query("SELECT COUNT(*) FROM waste_collection_records WHERE record_id LIKE '{$prefix}%'")->fetchColumn() + 1;
                $recId  = $prefix . str_pad($count, 4, '0', STR_PAD_LEFT);
                $pdo->prepare("INSERT INTO waste_collection_records (record_id,truck_id,route_id,date_collected,volume_kg,waste_type,crew_count,collection_status,notes) VALUES(?,?,?,?,?,?,?,?,?)")
                    ->execute([$recId,$truckId,$routeId,$date,$volume,$type,$crew,$status,$notes]);
                $pdo->prepare("INSERT INTO waste_notifications(message,type) VALUES(?,?)")
                    ->execute(["Collection record {$recId} logged — {$volume} kg ({$type}).",'General']);
                $success = "Record {$recId} created successfully!";
            } catch(PDOException $e){ $error = $e->getMessage(); }
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        try { $pdo->prepare("DELETE FROM waste_collection_records WHERE id=?")->execute([$id]); $success = "Record deleted."; }
        catch(PDOException $e){ $error = $e->getMessage(); }
    }
}

// Filter
$fDate  = $_GET['date']  ?? '';
$fRoute = $_GET['route'] ?? '';
$fType  = $_GET['type']  ?? '';

$where  = ['1=1']; $params = [];
if($fDate)  { $where[] = 'DATE(r.date_collected)=?'; $params[] = $fDate; }
if($fRoute) { $where[] = 'r.route_id=?'; $params[] = $fRoute; }
if($fType)  { $where[] = 'r.waste_type=?'; $params[] = $fType; }

$sql = "SELECT r.*, t.plate_number, rt.route_name, rt.color_hex
        FROM waste_collection_records r
        LEFT JOIN waste_trucks t ON r.truck_id=t.id
        LEFT JOIN waste_routes rt ON r.route_id=rt.id
        WHERE ".implode(' AND ',$where)."
        ORDER BY r.date_collected DESC LIMIT 100";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$records = $stmt->fetchAll();

// Summary
$totalKg = array_sum(array_column($records,'volume_kg'));
$totalRec = count($records);

// Dropdowns
$trucks = $pdo->query("SELECT * FROM waste_trucks ORDER BY truck_id")->fetchAll();
$routes = $pdo->query("SELECT * FROM waste_routes WHERE is_active=1 ORDER BY id")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>(function(){const t=localStorage.getItem('theme')||'light';if(t==='dark')document.documentElement.classList.add('dark-theme')})();</script>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Waste Collection Records — UMAN_</title>
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
        .page-header h1 i{color:#16a34a}
        /* Alert */
        .alert{padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:13px;font-weight:500}
        .alert-success{background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d}
        .alert-error{background:#fef2f2;border:1px solid #fecaca;color:#dc2626}
        /* Summary bar */
        .summary-bar{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:16px}
        .sum-card{background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1px solid #bbf7d0;border-radius:12px;padding:14px 20px;display:flex;align-items:center;gap:12px}
        .sum-card.blue{background:linear-gradient(135deg,#eff6ff,#dbeafe);border-color:#bfdbfe}
        .sum-icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;background:#16a34a;font-size:15px}
        .sum-card.blue .sum-icon{background:#2563eb}
        .sum-value{font-size:20px;font-weight:700;color:#1e293b}
        .sum-label{font-size:11px;color:#64748b}
        /* Filter bar */
        .filter-bar{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;align-items:center}
        .filter-bar select,.filter-bar input{padding:9px 12px;border-radius:9px;border:1px solid #e2e8f0;font-size:13px;font-family:'Poppins',sans-serif;outline:none;background:#fff}
        .filter-bar select:focus,.filter-bar input:focus{border-color:#16a34a}
        /* Buttons */
        .btn{padding:9px 18px;border-radius:9px;font-weight:600;font-size:13px;border:none;cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;gap:7px;text-decoration:none}
        .btn-green{background:linear-gradient(135deg,#16a34a,#15803d);color:#fff;box-shadow:0 3px 10px rgba(22,163,74,.3)}
        .btn-green:hover{transform:translateY(-1px)}
        .btn-outline{background:#fff;border:1px solid #e2e8f0;color:#64748b}
        .btn-outline:hover{background:#f8fafc}
        .btn-sm{padding:6px 14px;font-size:12px}
        .btn-red{background:#fef2f2;border:1px solid #fecaca;color:#dc2626}
        .btn-red:hover{background:#fee2e2}
        /* Table */
        .table-wrap{overflow-x:auto}
        table{width:100%;border-collapse:collapse;font-size:13px}
        th{background:#f8fafc;padding:12px 14px;text-align:left;font-weight:600;color:#475569;font-size:12px;border-bottom:2px solid #e2e8f0;white-space:nowrap}
        td{padding:12px 14px;border-bottom:1px solid #f1f5f9;color:#374151;vertical-align:middle}
        tr:hover td{background:#f8fafc}
        .badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600}
        .badge-completed{background:#f0fdf4;color:#16a34a}
        .badge-missed{background:#fef2f2;color:#dc2626}
        .badge-partial{background:#fff7ed;color:#ea580c}
        .badge-rescheduled{background:#eff6ff;color:#2563eb}
        .route-pill{display:inline-flex;align-items:center;gap:5px;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600;color:#fff}
        .empty-state{text-align:center;padding:40px;color:#94a3b8}
        .empty-state i{font-size:36px;display:block;margin-bottom:10px;opacity:.4}
        /* Modal */
        .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:2000;align-items:center;justify-content:center;padding:20px}
        .modal-overlay.show{display:flex}
        .modal{background:#fff;border-radius:20px;width:100%;max-width:560px;box-shadow:0 20px 60px rgba(0,0,0,.25);overflow:hidden;animation:mIn .25s ease}
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
        .dark-theme th{background:#0f172a;color:#94a3b8}
        .dark-theme td{color:#cbd5e1;border-color:#334155}
        .dark-theme tr:hover td{background:#1e293b}
        .dark-theme .form-control,.dark-theme .filter-bar select,.dark-theme .filter-bar input{background:#1e293b;border-color:#475569;color:#f8fafc}
        @media(max-width:768px){.main-content{margin-left:0;padding:16px}.form-grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
<?php include 'includes/utilities_sidebar.php'; ?>
<main class="main-content" id="mainContent">
<div class="card">

    <div class="page-header">
        <div>
            <h1><i class="fas fa-clipboard-list"></i> Waste Collection Records</h1>
            <p>Log and manage actual garbage collection data per route and truck.</p>
        </div>
        <div style="display:flex;gap:10px">
            <a href="waste_dashboard.php" class="btn btn-outline btn-sm"><i class="fas fa-chevron-left"></i> Dashboard</a>
            <button class="btn btn-green btn-sm" onclick="document.getElementById('addModal').classList.add('show')">
                <i class="fas fa-plus"></i> Add Record
            </button>
        </div>
    </div>

    <?php if($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>

    <!-- Summary -->
    <div class="summary-bar">
        <div class="sum-card">
            <div class="sum-icon"><i class="fas fa-list"></i></div>
            <div><div class="sum-value"><?= $totalRec ?></div><div class="sum-label">Records Shown</div></div>
        </div>
        <div class="sum-card blue">
            <div class="sum-icon"><i class="fas fa-weight"></i></div>
            <div><div class="sum-value"><?= number_format($totalKg,0) ?> kg</div><div class="sum-label">Total Volume</div></div>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" class="filter-bar">
        <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($fDate) ?>" style="width:160px">
        <select name="route" style="min-width:180px">
            <option value="">All Routes</option>
            <?php foreach($routes as $r): ?>
            <option value="<?= $r['id'] ?>" <?= $fRoute==$r['id']?'selected':'' ?>><?= htmlspecialchars($r['route_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="type" style="min-width:150px">
            <option value="">All Types</option>
            <?php foreach(['Biodegradable','Non-biodegradable','Recyclable','Hazardous','Mixed'] as $t): ?>
            <option value="<?= $t ?>" <?= $fType===$t?'selected':'' ?>><?= $t ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-green btn-sm"><i class="fas fa-search"></i> Filter</button>
        <a href="waste_records.php" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Clear</a>
    </form>

    <!-- Table -->
    <div class="table-wrap">
        <?php if(empty($records)): ?>
        <div class="empty-state"><i class="fas fa-clipboard"></i>No records found. Add the first collection record!</div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Record ID</th><th>Date</th><th>Route</th><th>Truck</th>
                    <th>Volume (kg)</th><th>Type</th><th>Crew</th><th>Status</th><th>Notes</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($records as $rec):
                $badgeClass = match($rec['collection_status']){
                    'Completed'=>'badge-completed','Missed'=>'badge-missed',
                    'Partial'=>'badge-partial','Rescheduled'=>'badge-rescheduled',default=>''
                };
            ?>
            <tr>
                <td><strong style="font-family:monospace;color:#16a34a"><?= htmlspecialchars($rec['record_id']) ?></strong></td>
                <td><?= date('M j, Y',strtotime($rec['date_collected'])) ?></td>
                <td>
                    <?php if($rec['route_name']): ?>
                    <span class="route-pill" style="background:<?= htmlspecialchars($rec['color_hex']??'#16a34a') ?>"><?= htmlspecialchars($rec['route_name']) ?></span>
                    <?php else: ?><span style="color:#94a3b8">—</span><?php endif; ?>
                </td>
                <td><?= htmlspecialchars($rec['plate_number']??'—') ?></td>
                <td><?= number_format($rec['volume_kg'],1) ?></td>
                <td><?= htmlspecialchars($rec['waste_type']) ?></td>
                <td><?= $rec['crew_count'] ?></td>
                <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($rec['collection_status']) ?></span></td>
                <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($rec['notes']) ?>"><?= htmlspecialchars($rec['notes']??'—') ?></td>
                <td>
                    <form method="POST" onsubmit="return confirm('Delete this record?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $rec['id'] ?>">
                        <button type="submit" class="btn btn-red btn-sm"><i class="fas fa-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</div>
</main>

<!-- Add Record Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header">
            <h2><i class="fas fa-plus-circle" style="color:#16a34a"></i> New Collection Record</h2>
            <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('show')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Date Collected *</label>
                        <input type="date" name="date_collected" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label>Volume (kg)</label>
                        <input type="number" name="volume_kg" class="form-control" placeholder="0.00" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label>Assigned Truck</label>
                        <select name="truck_id" class="form-control">
                            <option value="">— Select Truck —</option>
                            <?php foreach($trucks as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['truck_id'].' - '.$t['plate_number']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Route</label>
                        <select name="route_id" class="form-control">
                            <option value="">— Select Route —</option>
                            <?php foreach($routes as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['route_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Waste Type</label>
                        <select name="waste_type" class="form-control">
                            <?php foreach(['Biodegradable','Non-biodegradable','Recyclable','Hazardous','Mixed'] as $t): ?>
                            <option><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Collection Status</label>
                        <select name="collection_status" class="form-control">
                            <option>Completed</option><option>Partial</option><option>Missed</option><option>Rescheduled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Crew Count</label>
                        <input type="number" name="crew_count" class="form-control" value="3" min="1">
                    </div>
                    <div class="form-group full">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('addModal').classList.remove('show')">Cancel</button>
                <button type="submit" class="btn btn-green btn-sm"><i class="fas fa-save"></i> Save Record</button>
            </div>
        </form>
    </div>
</div>
<script>
const sb=document.getElementById('sidebar-nav'),mc=document.getElementById('mainContent');
if(sb){new MutationObserver(()=>mc.classList.toggle('collapsed',sb.classList.contains('collapsed'))).observe(sb,{attributes:true,attributeFilter:['class']})}
</script>
</body></html>
