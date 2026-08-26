<?php
// export_dashboard.php - Export Management Page
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn() || !isEmployee()) {
    header('Location: login.php');
    exit();
}

$counts = [];
$tables = ['assets'=>'utility_assets','incidents'=>'utility_incidents','maintenance'=>'maintenance_requests','energy'=>'energy_consumption_records','facilities'=>'public_facilities','users'=>'users'];
foreach ($tables as $key => $table) {
    try {
        $counts[$key] = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
    } catch (Throwable $e) { $counts[$key] = 0; }
}
$userName = $_SESSION['user_name'] ?? $_SESSION['full_name'] ?? 'LGU Coordinator';
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
    <title>Export Data | LGU Portal</title>
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family:'Poppins',sans-serif; margin:0; padding:0; box-sizing:border-box; }
        body { min-height:100vh; display:flex; background:url("assets/images/cityhall.jpeg") center/cover no-repeat fixed; position:relative; }
        body::before { content:""; position: fixed; top:0; left:0; width:100%; height:100%; backdrop-filter:blur(6px); background:rgba(0,0,0,0.35); z-index:0; }
        .main-content { flex:1; margin-left:280px; padding:30px 40px; z-index:1; position:relative; }
        .card { max-width:1700px; background:rgba(255,255,255,0.85); backdrop-filter:blur(15px); border-radius:18px; padding:40px; box-shadow:0 6px 20px rgba(0,0,0,0.2); }
        .dashboard-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:30px; flex-wrap:wrap; gap:20px; }
        .dashboard-header h1 { color:#2c3e50; font-size:32px; font-weight:700; display:flex; align-items:center; gap:15px; }
        .dashboard-header h1 i { color:#3762c8; }
        .btn { padding:10px 20px; border-radius:8px; font-weight:600; font-size:14px; border:none; cursor:pointer; display:inline-flex; align-items:center; gap:8px; text-decoration:none; }
        .btn-outline { background:transparent; border:1px solid #cbd5e1; color:#64748b; }
        .btn-outline:hover { background:#f8f9fa; }
        .btn-success { background:#28a745; color:#fff; }
        .btn-danger { background:#dc3545; color:#fff; }
        .export-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:25px; }
        .export-card { background:white; border-radius:12px; padding:25px; box-shadow:0 4px 12px rgba(0,0,0,0.05); border:1px solid rgba(0,0,0,0.05); transition:0.2s; }
        .export-card:hover { transform:translateY(-4px); box-shadow:0 8px 20px rgba(0,0,0,0.1); }
        .export-card .icon { font-size:32px; margin-bottom:12px; }
        .export-card h3 { font-size:18px; color:#2c3e50; margin-bottom:8px; }
        .export-card p { font-size:13px; color:#64748b; margin-bottom:15px; }
        .export-card .count { background:#f1f5f9; padding:2px 12px; border-radius:20px; font-size:12px; color:#475569; display:inline-block; margin-bottom:15px; }
        .export-actions { display:flex; gap:10px; flex-wrap:wrap; }
        .export-actions .btn { flex:1; justify-content:center; padding:8px 12px; font-size:12px; min-width:70px; }
        .icon-assets { color:#4b7bec; } .icon-incidents { color:#f1c40f; } .icon-maintenance { color:#e74c3c; } .icon-energy { color:#a55eea; } .icon-facilities { color:#45aaf2; } .icon-users { color:#2ecc71; }

        .instructions-card {
            margin-top: 30px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        /* ===== DARK THEME OVERRIDES ===== */
        .dark-theme .card {
            background: rgba(30, 41, 59, 0.9) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: #f8fafc !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5) !important;
        }
        .dark-theme .dashboard-header h1 {
            color: #f8fafc !important;
        }
        .dark-theme .export-card {
            background: #1e293b !important;
            border: 1px solid #334155 !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3) !important;
        }
        .dark-theme .export-card:hover {
            border-color: #3b82f6 !important;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5) !important;
        }
        .dark-theme .export-card h3 {
            color: #f8fafc !important;
        }
        .dark-theme .export-card p {
            color: #94a3b8 !important;
        }
        .dark-theme .export-card .count {
            background: #0f172a !important;
            color: #93c5fd !important;
            border: 1px solid #334155 !important;
        }
        .dark-theme .instructions-card {
            background: #0f172a !important;
            border: 1px solid #334155 !important;
        }
        .dark-theme .instructions-card h4 {
            color: #f8fafc !important;
        }
        .dark-theme .instructions-card ul {
            color: #94a3b8 !important;
        }
        .dark-theme .instructions-card strong {
            color: #f8fafc !important;
        }
    </style>
</head>
<body>
<?php include 'includes/utilities_sidebar.php'; ?>
<main class="main-content">
    <div class="card">
        <div class="dashboard-header">
            <div><h1><i class="fas fa-file-export"></i> Data Export Center</h1><p style="color:#64748b;">Export all modules in CSV or PDF.</p></div>
            <a href="utilities_dashboard.php" class="btn btn-outline"><i class="fas fa-chevron-left"></i> Dashboard</a>
        </div>
        <div class="export-grid">
            <div class="export-card"><div class="icon icon-assets"><i class="fas fa-boxes"></i></div><h3>Assets</h3><p>Export utility assets</p><span class="count"><?php echo number_format($counts['assets']); ?> records</span><div class="export-actions"><a href="export.php?type=assets&format=csv" class="btn btn-success"><i class="fas fa-file-csv"></i> CSV</a><a href="export.php?type=assets&format=pdf" class="btn btn-danger"><i class="fas fa-file-pdf"></i> PDF</a></div></div>
            <div class="export-card"><div class="icon icon-incidents"><i class="fas fa-exclamation-triangle"></i></div><h3>Incidents</h3><p>Export incident reports</p><span class="count"><?php echo number_format($counts['incidents']); ?> records</span><div class="export-actions"><a href="export.php?type=incidents&format=csv" class="btn btn-success"><i class="fas fa-file-csv"></i> CSV</a><a href="export.php?type=incidents&format=pdf" class="btn btn-danger"><i class="fas fa-file-pdf"></i> PDF</a></div></div>
            <div class="export-card"><div class="icon icon-maintenance"><i class="fas fa-tools"></i></div><h3>Maintenance</h3><p>Export maintenance requests</p><span class="count"><?php echo number_format($counts['maintenance']); ?> records</span><div class="export-actions"><a href="export.php?type=maintenance&format=csv" class="btn btn-success"><i class="fas fa-file-csv"></i> CSV</a><a href="export.php?type=maintenance&format=pdf" class="btn btn-danger"><i class="fas fa-file-pdf"></i> PDF</a></div></div>
            <div class="export-card"><div class="icon icon-energy"><i class="fas fa-bolt"></i></div><h3>Energy</h3><p>Export consumption records</p><span class="count"><?php echo number_format($counts['energy']); ?> records</span><div class="export-actions"><a href="export.php?type=energy&format=csv" class="btn btn-success"><i class="fas fa-file-csv"></i> CSV</a><a href="export.php?type=energy&format=pdf" class="btn btn-danger"><i class="fas fa-file-pdf"></i> PDF</a></div></div>
            <div class="export-card"><div class="icon icon-facilities"><i class="fas fa-warehouse"></i></div><h3>Facilities</h3><p>Export public facilities</p><span class="count"><?php echo number_format($counts['facilities']); ?> records</span><div class="export-actions"><a href="export.php?type=facilities&format=csv" class="btn btn-success"><i class="fas fa-file-csv"></i> CSV</a><a href="export.php?type=facilities&format=pdf" class="btn btn-danger"><i class="fas fa-file-pdf"></i> PDF</a></div></div>
            <div class="export-card"><div class="icon icon-users"><i class="fas fa-users"></i></div><h3>Users</h3><p>Export user accounts</p><span class="count"><?php echo number_format($counts['users']); ?> records</span><div class="export-actions"><a href="export.php?type=users&format=csv" class="btn btn-success"><i class="fas fa-file-csv"></i> CSV</a><a href="export.php?type=users&format=pdf" class="btn btn-danger"><i class="fas fa-file-pdf"></i> PDF</a></div></div>
        </div>
        <div class="instructions-card">
            <h4><i class="fas fa-info-circle"></i> Instructions</h4>
            <ul style="color:#64748b; font-size:13px; line-height:2; padding-left:20px;">
                <li><strong>CSV</strong> – Opens in Excel / Google Sheets</li>
                <li><strong>PDF</strong> – Printable report (requires TCPDF library)</li>
                <li>All data is exported without filters</li>
            </ul>
        </div>
    </div>
</main>
</body>
</html>