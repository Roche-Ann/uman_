<?php
// ai_weights_dashboard.php - Admin AI Weights Configuration
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn() || !isEmployee()) {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';

// Handle weight update submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_weights'])) {
    $wCoverage  = (float)($_POST['weight_coverage'] ?? 0);
    $wAssets    = (float)($_POST['weight_assets'] ?? 0);
    $wCapacity  = (float)($_POST['weight_capacity'] ?? 0);
    $wIncidents = (float)($_POST['weight_incidents'] ?? 0);

    $total = $wCoverage + $wAssets + $wCapacity + $wIncidents;

    if (abs($total - 100.0) > 0.01) {
        $error = "Total weights must equal exactly 100%. Current sum is {$total}%.";
    } else {
        $updateWeights = [
            'coverage'  => $wCoverage,
            'assets'    => $wAssets,
            'capacity'  => $wCapacity,
            'incidents' => $wIncidents
        ];
        $stmt = $pdo->prepare("UPDATE ai_weights SET weight_percent = ?, updated_by = ? WHERE factor_key = ?");
        $username = $_SESSION['user_name'] ?? 'Admin';
        foreach ($updateWeights as $key => $val) {
            $stmt->execute([$val, $username, $key]);
        }
        $message = "AI Scoring Weights updated successfully!";
    }
}

// Fetch current weights
$weights = $pdo->query("SELECT * FROM ai_weights")->fetchAll(PDO::FETCH_ASSOC);
$weightMap = [];
foreach ($weights as $w) {
    $weightMap[$w['factor_key']] = (float)$w['weight_percent'];
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
    <title>AI Scoring Weight Management</title>
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin:0; padding:0; font-family: 'Poppins', sans-serif; }
        body {
            min-height: 100vh;
            display: flex;
            background: url("assets/images/cityhall.jpeg") center/cover no-repeat fixed;
            color: #1e293b;
            position: relative;
        }
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            backdrop-filter: blur(6px);
            background: rgba(0, 0, 0, 0.30);
            z-index: 0;
        }
        .main-content { flex: 1; margin-left: 280px; padding: 30px; position: relative; z-index: 1; }
        .page-wrapper {
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(15px);
            border-radius: 18px;
            padding: 35px 40px;
            box-shadow: 0 6px 24px rgba(0,0,0,0.22);
            border: 1px solid rgba(255,255,255,0.3);
            max-width: 1400px;
        }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .header h1 { font-size: 24px; color: #0f172a; display: flex; gap: 10px; align-items: center; }
        .alert { padding: 14px 20px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; }
        .factor-card { background: #ffffff; border-radius: 12px; padding: 22px; border: 1px solid #e2e8f0; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .factor-title { font-weight: 600; font-size: 15px; margin-bottom: 8px; color: #0f172a; display: flex; justify-content: space-between; align-items: center; }
        .factor-desc { font-size: 12px; color: #64748b; margin-bottom: 15px; line-height: 1.5; }
        
        .slider-row { display: flex; align-items: center; gap: 15px; }
        .slider-row input[type="range"] { flex: 1; accent-color: #3b82f6; cursor: pointer; }
        .val-badge { background: #3b82f6; color: #fff; font-weight: 700; padding: 4px 10px; border-radius: 6px; min-width: 55px; text-align: center; font-size: 13px; }
        
        .total-box { margin-top: 25px; padding: 20px; border-radius: 12px; background: #ffffff; border: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .btn { padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; }
        .btn-primary { background: #3b82f6; color: #fff; }
        .btn-primary:hover { background: #2563eb; }
        .btn-outline { background: #f8fafc; color: #475569; border: 1px solid #cbd5e1; }

        /* Dark Mode */
        .dark-theme body::before { background: rgba(5, 10, 22, 0.80) !important; }
        .dark-theme body { color: #f1f5f9 !important; }
        .dark-theme .page-wrapper { background: rgba(10, 18, 35, 0.92) !important; border-color: rgba(255,255,255,0.08) !important; }
        .dark-theme .header h1 { color: #f8fafc !important; }
        .dark-theme .factor-card { background: rgba(30, 41, 59, 0.95) !important; border-color: #334155 !important; }
        .dark-theme .factor-title { color: #f8fafc !important; }
        .dark-theme .factor-desc { color: #94a3b8 !important; }
        .dark-theme .total-box { background: rgba(30, 41, 59, 0.95) !important; border-color: #334155 !important; }
    </style>
</head>
<body>
    <?php include 'includes/utilities_sidebar.php'; ?>
    <main class="main-content">
        <div class="page-wrapper">
            <div class="header">
                <div>
                    <h1><i class="fas fa-sliders-h" style="color: #3b82f6;"></i> AI Inspection Scoring Weights</h1>
                    <p style="color:#64748b; font-size:14px; margin-top:4px;">Customize dynamic scoring weights used when evaluating urban planning inspection requests.</p>
                </div>
                <div style="display:flex; gap:10px;">
                    <a href="upad_integration.php" class="btn btn-outline"><i class="fas fa-city"></i> Inspection Hub</a>
                    <a href="ai_feedback_loop.php" class="btn btn-outline"><i class="fas fa-history"></i> Decision Audit Logs</a>
                </div>
            </div>

            <?php if ($message): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <form method="POST" id="weightForm">
                <input type="hidden" name="save_weights" value="1">
                <div class="grid">
                    <div class="factor-card">
                        <div class="factor-title">
                            <span><i class="fas fa-map-marked-alt" style="color:#3b82f6;"></i> Coverage Status</span>
                            <span class="val-badge" id="badge_coverage"><?php echo $weightMap['coverage'] ?? 30; ?>%</span>
                        </div>
                        <p class="factor-desc">Weighs utility availability: Fully Covered (100 pts), Partially Covered (50 pts), or Not Covered (0 pts).</p>
                        <div class="slider-row">
                            <input type="range" name="weight_coverage" id="w_cov" min="0" max="100" step="1" value="<?php echo $weightMap['coverage'] ?? 30; ?>" oninput="updateSums()">
                        </div>
                    </div>

                    <div class="factor-card">
                        <div class="factor-title">
                            <span><i class="fas fa-tools" style="color:#10b981;"></i> Asset Health</span>
                            <span class="val-badge" id="badge_assets"><?php echo $weightMap['assets'] ?? 25; ?>%</span>
                        </div>
                        <p class="factor-desc">Weighs percentage of operational infrastructure assets vs damaged equipment in the project zone.</p>
                        <div class="slider-row">
                            <input type="range" name="weight_assets" id="w_ast" min="0" max="100" step="1" value="<?php echo $weightMap['assets'] ?? 25; ?>" oninput="updateSums()">
                        </div>
                    </div>

                    <div class="factor-card">
                        <div class="factor-title">
                            <span><i class="fas fa-bolt" style="color:#f59e0b;"></i> Capacity Status</span>
                            <span class="val-badge" id="badge_capacity"><?php echo $weightMap['capacity'] ?? 25; ?>%</span>
                        </div>
                        <p class="factor-desc">Weighs grid/transformer capacity: Normal (100 pts), Near Capacity (60 pts), Overloaded (20 pts).</p>
                        <div class="slider-row">
                            <input type="range" name="weight_capacity" id="w_cap" min="0" max="100" step="1" value="<?php echo $weightMap['capacity'] ?? 25; ?>" oninput="updateSums()">
                        </div>
                    </div>

                    <div class="factor-card">
                        <div class="factor-title">
                            <span><i class="fas fa-shield-alt" style="color:#ef4444;"></i> Incident Clearance</span>
                            <span class="val-badge" id="badge_incidents"><?php echo $weightMap['incidents'] ?? 20; ?>%</span>
                        </div>
                        <p class="factor-desc">Weighs active hazard incidents: 0 Active (100 pts), 1-2 Active (70 pts), &gt;2 Active (30 pts).</p>
                        <div class="slider-row">
                            <input type="range" name="weight_incidents" id="w_inc" min="0" max="100" step="1" value="<?php echo $weightMap['incidents'] ?? 20; ?>" oninput="updateSums()">
                        </div>
                    </div>
                </div>

                <div class="total-box">
                    <div>
                        <strong>Total Configured Weight: </strong>
                        <span id="totalText" style="font-size: 18px; font-weight: 700; color: #10b981;">100%</span>
                        <p id="totalWarning" style="font-size: 12px; color: #ef4444; display: none; margin-top: 4px;">Total weights must equal exactly 100% before saving.</p>
                    </div>
                    <button type="submit" id="saveBtn" class="btn btn-primary"><i class="fas fa-save"></i> Save Weights</button>
                </div>
            </form>
        </div>
    </main>

    <script>
        function updateSums() {
            const cov = parseFloat(document.getElementById('w_cov').value) || 0;
            const ast = parseFloat(document.getElementById('w_ast').value) || 0;
            const cap = parseFloat(document.getElementById('w_cap').value) || 0;
            const inc = parseFloat(document.getElementById('w_inc').value) || 0;

            document.getElementById('badge_coverage').innerText = cov + '%';
            document.getElementById('badge_assets').innerText = ast + '%';
            document.getElementById('badge_capacity').innerText = cap + '%';
            document.getElementById('badge_incidents').innerText = inc + '%';

            const total = cov + ast + cap + inc;
            const totalText = document.getElementById('totalText');
            const totalWarning = document.getElementById('totalWarning');
            const saveBtn = document.getElementById('saveBtn');

            totalText.innerText = total + '%';
            if (total === 100) {
                totalText.style.color = '#10b981';
                totalWarning.style.display = 'none';
                saveBtn.disabled = false;
                saveBtn.style.opacity = '1';
                saveBtn.style.cursor = 'pointer';
            } else {
                totalText.style.color = '#ef4444';
                totalWarning.style.display = 'block';
                saveBtn.disabled = true;
                saveBtn.style.opacity = '0.5';
                saveBtn.style.cursor = 'not-allowed';
            }
        }
        updateSums();
    </script>
</body>
</html>
