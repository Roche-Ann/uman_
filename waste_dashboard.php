<?php
// waste_dashboard.php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) { header('Location: login.php'); exit(); }

$userType = $_SESSION['user_type'] ?? 'employee';

// KPI Stats
$stats = ['monthly_collections'=>0,'monthly_volume_kg'=>0,'open_complaints'=>0,'active_trucks'=>0,'active_routes'=>0,'avg_compliance_rate'=>0];
try { $stats = $pdo->query("SELECT * FROM aggregated_waste_view")->fetch() ?: $stats; } catch(Throwable $e){}

// Monthly volume trend (last 6 months)
$trend = [];
try {
    $trend = $pdo->query("
        SELECT DATE_FORMAT(date_collected,'%b %Y') as month,
               SUM(volume_kg) as total_kg,
               waste_type
        FROM waste_collection_records
        WHERE date_collected >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(date_collected,'%b %Y'), waste_type
        ORDER BY MIN(date_collected)
    ")->fetchAll();
} catch(Throwable $e){}

// Waste type donut
$typeTotals = [];
try {
    $typeTotals = $pdo->query("
        SELECT waste_type, SUM(volume_kg) as total
        FROM waste_collection_records
        WHERE MONTH(date_collected)=MONTH(CURDATE()) AND YEAR(date_collected)=YEAR(CURDATE())
        GROUP BY waste_type
    ")->fetchAll();
} catch(Throwable $e){}

// Recent notifications
$notifs = [];
try {
    $notifs = $pdo->query("SELECT * FROM waste_notifications ORDER BY created_at DESC LIMIT 6")->fetchAll();
} catch(Throwable $e){}

// Recent complaints
$complaints = [];
try {
    $complaints = $pdo->query("
        SELECT c.*, u.full_name as reporter_name
        FROM waste_complaints c LEFT JOIN users u ON c.user_id=u.id
        WHERE c.status IN ('Pending','Under Review')
        ORDER BY c.created_at DESC LIMIT 5
    ")->fetchAll();
} catch(Throwable $e){}

// Route status
$routes = [];
try { $routes = $pdo->query("SELECT r.*, COUNT(s.id) as stop_count FROM waste_routes r LEFT JOIN waste_route_stops s ON r.id=s.route_id WHERE r.is_active=1 GROUP BY r.id ORDER BY r.id")->fetchAll(); } catch(Throwable $e){}

// Chart data
$months = []; $bioData=[]; $nonBioData=[]; $recycData=[]; $mixedData=[];
foreach($trend as $row){
    if(!in_array($row['month'],$months)) $months[]=$row['month'];
}
foreach($months as $m){
    $bioData[]   = array_sum(array_column(array_filter($trend,fn($r)=>$r['month']==$m&&$r['waste_type']==='Biodegradable'),'total_kg'));
    $nonBioData[] = array_sum(array_column(array_filter($trend,fn($r)=>$r['month']==$m&&$r['waste_type']==='Non-biodegradable'),'total_kg'));
    $recycData[] = array_sum(array_column(array_filter($trend,fn($r)=>$r['month']==$m&&$r['waste_type']==='Recyclable'),'total_kg'));
    $mixedData[] = array_sum(array_column(array_filter($trend,fn($r)=>$r['month']==$m&&$r['waste_type']==='Mixed'),'total_kg'));
}
$donutLabels = array_column($typeTotals,'waste_type');
$donutData   = array_column($typeTotals,'total');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>(function(){const t=localStorage.getItem('theme')||'light';if(t==='dark')document.documentElement.classList.add('dark-theme')})();</script>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Waste Management Dashboard — UMAN_</title>
    <meta name="description" content="Waste collection dashboard for Quezon City LGU.">
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Poppins',sans-serif}
        body{min-height:100vh;display:flex;background:url("assets/images/cityhall.jpeg") center/cover no-repeat fixed}
        body::before{content:"";position:fixed;inset:0;backdrop-filter:blur(6px);background:rgba(0,0,0,0.35);z-index:0}
        .main-content{flex:1;margin-left:280px;padding:28px 36px;z-index:1;position:relative}
        .main-content.collapsed{margin-left:90px}
        .card{background:rgba(255,255,255,0.92);backdrop-filter:blur(18px);border-radius:20px;padding:32px;color:#1e293b;box-shadow:0 8px 32px rgba(0,0,0,0.18);border:1px solid rgba(255,255,255,0.3)}

        /* Header */
        .page-header{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px;margin-bottom:24px}
        .page-header h1{font-size:26px;font-weight:700;color:#15803d;display:flex;align-items:center;gap:12px}
        .page-header h1 i{background:linear-gradient(135deg,#16a34a,#15803d);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
        .page-header p{color:#64748b;font-size:13px;margin-top:4px}

        /* KPI Grid */
        .kpi-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
        .kpi-card{border-radius:16px;padding:20px 22px;display:flex;align-items:center;gap:16px;position:relative;overflow:hidden;transition:transform .2s,box-shadow .2s}
        .kpi-card:hover{transform:translateY(-2px);box-shadow:0 10px 30px rgba(0,0,0,0.15)}
        .kpi-card::after{content:"";position:absolute;right:-10px;top:-10px;width:70px;height:70px;border-radius:50%;opacity:.1}
        .kpi-card.green{background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1px solid #bbf7d0}
        .kpi-card.green::after{background:#16a34a}
        .kpi-card.orange{background:linear-gradient(135deg,#fff7ed,#ffedd5);border:1px solid #fed7aa}
        .kpi-card.orange::after{background:#ea580c}
        .kpi-card.blue{background:linear-gradient(135deg,#eff6ff,#dbeafe);border:1px solid #bfdbfe}
        .kpi-card.blue::after{background:#2563eb}
        .kpi-card.purple{background:linear-gradient(135deg,#faf5ff,#ede9fe);border:1px solid #ddd6fe}
        .kpi-card.purple::after{background:#7c3aed}
        .kpi-icon{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff;flex-shrink:0}
        .kpi-card.green .kpi-icon{background:linear-gradient(135deg,#16a34a,#15803d)}
        .kpi-card.orange .kpi-icon{background:linear-gradient(135deg,#ea580c,#c2410c)}
        .kpi-card.blue .kpi-icon{background:linear-gradient(135deg,#2563eb,#1d4ed8)}
        .kpi-card.purple .kpi-icon{background:linear-gradient(135deg,#7c3aed,#6d28d9)}
        .kpi-value{font-size:28px;font-weight:800;color:#1e293b;line-height:1}
        .kpi-label{font-size:12px;color:#64748b;font-weight:500;margin-top:3px}
        .kpi-sub{font-size:11px;color:#94a3b8;margin-top:4px}

        /* Charts row */
        .charts-row{display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:24px}
        .chart-box{background:#fff;border-radius:16px;padding:22px;border:1px solid #f1f5f9;box-shadow:0 2px 10px rgba(0,0,0,0.05)}
        .chart-box h3{font-size:14px;font-weight:700;color:#1e293b;margin-bottom:16px;display:flex;align-items:center;gap:8px}
        .chart-box h3 i{color:#16a34a}

        /* Bottom row */
        .bottom-row{display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px}
        .panel{background:#fff;border-radius:16px;padding:20px;border:1px solid #f1f5f9;box-shadow:0 2px 10px rgba(0,0,0,0.05)}
        .panel h3{font-size:13px;font-weight:700;color:#1e293b;margin-bottom:14px;display:flex;align-items:center;gap:8px;border-bottom:1px solid #f1f5f9;padding-bottom:10px}
        .panel h3 i{color:#16a34a}

        /* Route cards */
        .route-card{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid #f8fafc}
        .route-card:last-child{border-bottom:none}
        .route-dot{width:12px;height:12px;border-radius:50%;flex-shrink:0}
        .route-name{font-size:12px;font-weight:600;color:#1e293b;flex:1}
        .route-stops{font-size:11px;color:#94a3b8}

        /* Complaint items */
        .complaint-item{display:flex;gap:10px;padding:10px 0;border-bottom:1px solid #f8fafc;align-items:flex-start}
        .complaint-item:last-child{border-bottom:none}
        .ci-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0}
        .ci-icon.missed{background:#fef2f2;color:#dc2626}
        .ci-icon.illegal{background:#fff7ed;color:#ea580c}
        .ci-title{font-size:12px;font-weight:600;color:#1e293b}
        .ci-meta{font-size:11px;color:#94a3b8;margin-top:2px}
        .ci-badge{font-size:9px;font-weight:700;padding:2px 7px;border-radius:10px;display:inline-block;margin-top:3px}
        .ci-badge.pending{background:#fef9e7;color:#d97706}
        .ci-badge.review{background:#eff6ff;color:#2563eb}

        /* Notification items */
        .notif-item{display:flex;gap:10px;padding:9px 0;border-bottom:1px solid #f8fafc;align-items:flex-start}
        .notif-item:last-child{border-bottom:none}
        .notif-dot{width:8px;height:8px;border-radius:50%;background:#16a34a;flex-shrink:0;margin-top:5px}
        .notif-msg{font-size:12px;color:#374151;flex:1;line-height:1.4}
        .notif-time{font-size:10px;color:#94a3b8;white-space:nowrap}

        /* Empty state */
        .empty-state{text-align:center;padding:24px;color:#94a3b8;font-size:13px}
        .empty-state i{font-size:28px;margin-bottom:8px;display:block;opacity:.4}

        /* Btn */
        .btn{padding:9px 18px;border-radius:9px;font-weight:600;font-size:13px;border:none;cursor:pointer;transition:all .25s;display:inline-flex;align-items:center;gap:7px;text-decoration:none}
        .btn-green{background:linear-gradient(135deg,#16a34a,#15803d);color:#fff;box-shadow:0 3px 10px rgba(22,163,74,.3)}
        .btn-green:hover{transform:translateY(-1px)}
        .btn-outline{background:#fff;border:1px solid #e2e8f0;color:#64748b}
        .btn-sm{padding:6px 14px;font-size:12px}
        .header-actions{display:flex;gap:10px;flex-wrap:wrap}

        .dark-theme .card{background:rgba(15,23,42,0.92);color:#e2e8f0}
        .dark-theme .chart-box,.dark-theme .panel{background:#1e293b;border-color:#334155}
        .dark-theme .kpi-value,.dark-theme .route-name,.dark-theme .ci-title,.dark-theme .notif-msg{color:#f1f5f9}
        .dark-theme .kpi-card.green{background:linear-gradient(135deg,#14532d,#166534);border-color:#16a34a}
        .dark-theme .kpi-card.orange{background:linear-gradient(135deg,#431407,#7c2d12);border-color:#c2410c}
        .dark-theme .kpi-card.blue{background:linear-gradient(135deg,#172554,#1e3a8a);border-color:#1d4ed8}
        .dark-theme .kpi-card.purple{background:linear-gradient(135deg,#2e1065,#4c1d95);border-color:#6d28d9}

        @media(max-width:1100px){.kpi-grid{grid-template-columns:repeat(2,1fr)}.charts-row{grid-template-columns:1fr}.bottom-row{grid-template-columns:1fr}}
        @media(max-width:768px){.main-content{margin-left:0;padding:16px}}
    </style>
</head>
<body>
<?php include 'includes/utilities_sidebar.php'; ?>
<main class="main-content" id="mainContent">

    <div class="page-header">
        <div>
            <h1><i class="fas fa-recycle"></i> Waste Management Dashboard</h1>
            <p>Quezon City waste collection monitoring — <?= date('F Y') ?></p>
        </div>
        <div class="header-actions">
            <a href="waste_truck_map.php" class="btn btn-green btn-sm"><i class="fas fa-map-marked-alt"></i> Route Map</a>
            <a href="waste_records.php" class="btn btn-outline btn-sm"><i class="fas fa-clipboard-list"></i> Records</a>
            <a href="waste_schedules.php" class="btn btn-outline btn-sm"><i class="fas fa-calendar-alt"></i> Schedules</a>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="kpi-grid">
        <div class="kpi-card green">
            <div class="kpi-icon"><i class="fas fa-truck"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($stats['monthly_collections']) ?></div>
                <div class="kpi-label">Collections This Month</div>
                <div class="kpi-sub"><?= number_format($stats['monthly_volume_kg'],0) ?> kg total volume</div>
            </div>
        </div>
        <div class="kpi-card orange">
            <div class="kpi-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div>
                <div class="kpi-value"><?= $stats['open_complaints'] ?></div>
                <div class="kpi-label">Open Complaints</div>
                <div class="kpi-sub">Pending + Under Review</div>
            </div>
        </div>
        <div class="kpi-card blue">
            <div class="kpi-icon"><i class="fas fa-route"></i></div>
            <div>
                <div class="kpi-value"><?= $stats['active_routes'] ?></div>
                <div class="kpi-label">Active Routes</div>
                <div class="kpi-sub"><?= $stats['active_trucks'] ?> trucks assigned</div>
            </div>
        </div>
        <div class="kpi-card purple">
            <div class="kpi-icon"><i class="fas fa-chart-pie"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($stats['avg_compliance_rate'],1) ?>%</div>
                <div class="kpi-label">Avg Compliance Rate</div>
                <div class="kpi-sub">Segregation audits this month</div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="charts-row">
        <div class="chart-box">
            <h3><i class="fas fa-chart-bar"></i> Monthly Waste Volume (kg) — Last 6 Months</h3>
            <canvas id="trendChart" height="120"></canvas>
        </div>
        <div class="chart-box">
            <h3><i class="fas fa-chart-pie"></i> Waste Type Distribution</h3>
            <canvas id="donutChart" height="180"></canvas>
            <?php if(empty($donutLabels)): ?>
            <div class="empty-state"><i class="fas fa-database"></i>No data this month yet</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bottom Row -->
    <div class="bottom-row">
        <!-- Active Routes -->
        <div class="panel">
            <h3><i class="fas fa-route"></i> Active Routes <a href="waste_truck_map.php" style="margin-left:auto;font-size:11px;color:#16a34a;text-decoration:none">View Map →</a></h3>
            <?php if(empty($routes)): ?>
            <div class="empty-state"><i class="fas fa-route"></i>No routes configured</div>
            <?php else: ?>
            <?php foreach($routes as $r): ?>
            <div class="route-card">
                <div class="route-dot" style="background:<?= htmlspecialchars($r['color_hex']) ?>"></div>
                <span class="route-name"><?= htmlspecialchars($r['route_name']) ?></span>
                <span class="route-stops"><?= $r['stop_count'] ?> stops</span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Open Complaints -->
        <div class="panel">
            <h3><i class="fas fa-map-pin"></i> Open Complaints <a href="waste_truck_map.php" style="margin-left:auto;font-size:11px;color:#dc2626;text-decoration:none">View Map →</a></h3>
            <?php if(empty($complaints)): ?>
            <div class="empty-state"><i class="fas fa-check-circle"></i>No open complaints!</div>
            <?php else: ?>
            <?php foreach($complaints as $c):
                $isMissed = $c['complaint_type']==='Missed Collection';
                $sClass   = $c['status']==='Under Review'?'review':'pending';
            ?>
            <div class="complaint-item">
                <div class="ci-icon <?= $isMissed?'missed':'illegal' ?>">
                    <i class="fas <?= $isMissed?'fa-truck':'fa-dumpster-fire' ?>"></i>
                </div>
                <div>
                    <div class="ci-title"><?= htmlspecialchars($c['complaint_type']) ?></div>
                    <div class="ci-meta"><?= htmlspecialchars($c['barangay']?:'Unknown') ?> · <?= date('M j',strtotime($c['created_at'])) ?></div>
                    <span class="ci-badge <?= $sClass ?>"><?= $c['status'] ?></span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Notifications -->
        <div class="panel">
            <h3><i class="fas fa-bell"></i> Recent Alerts <a href="waste_notifications.php" style="margin-left:auto;font-size:11px;color:#16a34a;text-decoration:none">All →</a></h3>
            <?php if(empty($notifs)): ?>
            <div class="empty-state"><i class="fas fa-bell-slash"></i>No notifications yet</div>
            <?php else: ?>
            <?php foreach($notifs as $n): ?>
            <div class="notif-item">
                <div class="notif-dot" style="background:<?= $n['is_read']?'#cbd5e1':'#16a34a' ?>"></div>
                <span class="notif-msg"><?= htmlspecialchars($n['message']) ?></span>
                <span class="notif-time"><?= date('M j',strtotime($n['created_at'])) ?></span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</main>

<script>
// Trend bar chart
const trendCtx = document.getElementById('trendChart').getContext('2d');
new Chart(trendCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($months ?: ['No data']) ?>,
        datasets: [
            { label:'Biodegradable',   data: <?= json_encode($bioData) ?>,    backgroundColor:'#16a34a',borderRadius:6 },
            { label:'Non-biodegradable',data: <?= json_encode($nonBioData) ?>, backgroundColor:'#2563eb',borderRadius:6 },
            { label:'Recyclable',      data: <?= json_encode($recycData) ?>,   backgroundColor:'#7c3aed',borderRadius:6 },
            { label:'Mixed',           data: <?= json_encode($mixedData) ?>,   backgroundColor:'#ea580c',borderRadius:6 }
        ]
    },
    options: {
        responsive:true, plugins:{legend:{position:'bottom',labels:{font:{family:'Poppins',size:11}}}},
        scales:{y:{beginAtZero:true,grid:{color:'rgba(0,0,0,0.05)'},ticks:{font:{family:'Poppins'}}},
                x:{grid:{display:false},ticks:{font:{family:'Poppins'}}}}
    }
});

// Donut chart
const donutCtx = document.getElementById('donutChart').getContext('2d');
const donutColors = ['#16a34a','#2563eb','#7c3aed','#ea580c','#ca8a04'];
new Chart(donutCtx,{
    type:'doughnut',
    data:{
        labels: <?= json_encode($donutLabels ?: ['No data']) ?>,
        datasets:[{data: <?= json_encode($donutData ?: [1]) ?>, backgroundColor:donutColors,borderWidth:2,borderColor:'#fff'}]
    },
    options:{
        responsive:true,cutout:'65%',
        plugins:{legend:{position:'bottom',labels:{font:{family:'Poppins',size:11}}}}
    }
});

// Sidebar collapse sync
const sidebar = document.getElementById('sidebar-nav');
const mc = document.getElementById('mainContent');
if(sidebar){ const obs=new MutationObserver(()=>mc.classList.toggle('collapsed',sidebar.classList.contains('collapsed'))); obs.observe(sidebar,{attributes:true,attributeFilter:['class']}); }
</script>
</body></html>
