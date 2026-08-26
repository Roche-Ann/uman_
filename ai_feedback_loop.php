<?php
// ai_feedback_loop.php - Manual Overrides and Continuous Model Training Audit
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn() || !isEmployee()) {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';

// Handle Manual Override
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'manual_override') {
    $logId    = (int)($_POST['log_id'] ?? 0);
    $override = trim($_POST['override_decision'] ?? '');
    $reason   = trim($_POST['override_reason'] ?? '');
    $user     = $_SESSION['user_name'] ?? 'Staff Engineer';

    if ($logId > 0 && in_array($override, ['Approved', 'Conditional', 'Rejected'], true) && !empty($reason)) {
        $stmt = $pdo->prepare("
            UPDATE inspection_ai_logs 
            SET is_overridden = 1, override_decision = ?, override_reason = ?, overridden_by = ?, overridden_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$override, $reason, $user, $logId]);
        $message = "Manual override logged for Request log #$logId. Feedback recorded for AI model retraining.";
    } else {
        $error = "Please choose a valid override decision and provide justification.";
    }
}

// Analytics
$totalEvaluations = (int)$pdo->query("SELECT COUNT(*) FROM inspection_ai_logs")->fetchColumn();
$overriddenCount  = (int)$pdo->query("SELECT COUNT(*) FROM inspection_ai_logs WHERE is_overridden = 1")->fetchColumn();
$agreementRate    = $totalEvaluations > 0 ? round((($totalEvaluations - $overriddenCount) / $totalEvaluations) * 100, 1) : 100;

// Fetch logs
$logs = $pdo->query("SELECT * FROM inspection_ai_logs ORDER BY created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
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
    <title>AI Feedback Loop & Decision Audit</title>
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
            max-width: 1500px;
        }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .header h1 { font-size: 24px; color: #0f172a; display: flex; gap: 10px; align-items: center; }
        
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: #ffffff; padding: 20px; border-radius: 14px; border-left: 5px solid #3b82f6; box-shadow: 0 2px 10px rgba(0,0,0,0.07); }
        .stat-card.green { border-left-color: #10b981; }
        .stat-card.yellow { border-left-color: #f59e0b; }
        .stat-card h3 { font-size: 26px; color: #0f172a; font-weight: 700; }
        .stat-card p { font-size: 12px; color: #64748b; text-transform: uppercase; font-weight: 600; margin-top: 4px; }
        
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px; }
        th { background: #f8fafc; padding: 12px 16px; text-align: left; color: #475569; border-bottom: 2px solid #e2e8f0; font-weight: 600; }
        td { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; color: #334155; }
        tr:hover td { background: #f8fafc; }
        
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; display: inline-block; }
        .badge-approved { background: #d1fae5; color: #065f46; }
        .badge-conditional { background: #fef3c7; color: #92400e; }
        .badge-rejected { background: #fee2e2; color: #991b1b; }
        
        .btn { padding: 8px 14px; border-radius: 8px; font-size: 12px; font-weight: 600; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; }
        .btn-override { background: #3b82f6; color: #fff; }
        .btn-override:hover { background: #2563eb; }
        .btn-outline { background: #f8fafc; color: #475569; border: 1px solid #cbd5e1; }
        
        .alert { padding: 14px 20px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        /* Modal */
        .modal { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(6px); justify-content: center; align-items: center; z-index: 1000; }
        .modal.show { display: flex; }
        .modal-content { background: #ffffff; border-radius: 16px; padding: 25px; width: 480px; max-width: 95%; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 6px; }
        .form-control { width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 13px; font-family: inherit; }

        /* Dark Theme */
        .dark-theme body::before { background: rgba(5, 10, 22, 0.80) !important; }
        .dark-theme body { color: #f1f5f9 !important; }
        .dark-theme .page-wrapper { background: rgba(10, 18, 35, 0.92) !important; border-color: rgba(255,255,255,0.08) !important; }
        .dark-theme .header h1 { color: #f8fafc !important; }
        .dark-theme .stat-card { background: rgba(30, 41, 59, 0.95) !important; }
        .dark-theme .stat-card h3 { color: #f8fafc !important; }
        .dark-theme th { background: #151f32 !important; color: #94a3b8 !important; border-bottom-color: #334155 !important; }
        .dark-theme td { color: #cbd5e1 !important; border-bottom-color: #334155 !important; }
        .dark-theme tr:hover td { background: rgba(255,255,255,0.04) !important; }
        .dark-theme .modal-content { background: #1e293b !important; color: #f8fafc !important; border: 1px solid #475569 !important; }
        .dark-theme .form-control { background: #0f172a !important; border-color: #475569 !important; color: #f8fafc !important; }
    </style>
</head>
<body>
    <?php include 'includes/utilities_sidebar.php'; ?>
    <main class="main-content">
        <div class="page-wrapper">
            <div class="header">
                <div>
                    <h1><i class="fas fa-brain" style="color: #3b82f6;"></i> AI Decision Logs & Continuous Feedback Loop</h1>
                    <p style="color:#64748b; font-size:14px; margin-top:4px;">Audit automated inspection scoring results, record manual overrides, and review training feedback metrics.</p>
                </div>
                <div style="display:flex; gap:10px;">
                    <a href="upad_integration.php" class="btn btn-outline"><i class="fas fa-city"></i> Inspection Hub</a>
                    <a href="ai_weights_dashboard.php" class="btn btn-outline"><i class="fas fa-sliders-h"></i> Configure Weights</a>
                </div>
            </div>

            <?php if ($message): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <div class="stats-row">
                <div class="stat-card">
                    <h3><?php echo $totalEvaluations; ?></h3>
                    <p>Total AI Evaluations</p>
                </div>
                <div class="stat-card green">
                    <h3 style="color:#10b981;"><?php echo $agreementRate; ?>%</h3>
                    <p>AI Agreement Rate</p>
                </div>
                <div class="stat-card yellow">
                    <h3 style="color:#f59e0b;"><?php echo $overriddenCount; ?></h3>
                    <p>Manual Overrides</p>
                </div>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Ref ID</th>
                            <th>Location</th>
                            <th>Factors Breakdown</th>
                            <th>AI Score</th>
                            <th>AI Decision</th>
                            <th>Audit / Override Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: #94a3b8; padding: 30px;">
                                    No AI evaluations logged yet. Run AI validation from the Inspection Hub!
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $l): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($l['request_id']); ?></strong></td>
                                <td><?php echo htmlspecialchars($l['location']); ?></td>
                                <td style="font-size: 11px; color:#64748b;">
                                    Cov: <strong><?php echo (int)$l['coverage_score']; ?></strong> |
                                    Ast: <strong><?php echo (int)$l['asset_score']; ?></strong> |
                                    Cap: <strong><?php echo (int)$l['capacity_score']; ?></strong> |
                                    Inc: <strong><?php echo (int)$l['incident_score']; ?></strong>
                                </td>
                                <td><strong><?php echo $l['final_ai_score']; ?>%</strong></td>
                                <td>
                                    <span class="badge badge-<?php echo strtolower($l['ai_decision']); ?>">
                                        <?php echo $l['ai_decision']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($l['is_overridden']): ?>
                                        <span style="color:#f59e0b; font-size:12px; font-weight:600;">
                                            <i class="fas fa-user-edit"></i> Overridden to <?php echo $l['override_decision']; ?>
                                        </span>
                                        <br><small style="color:#64748b; font-style:italic;">"<?php echo htmlspecialchars($l['override_reason']); ?>"</small>
                                    <?php else: ?>
                                        <span style="color:#10b981; font-size:12px;"><i class="fas fa-check-circle"></i> AI Confirmed</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-override" onclick="openOverrideModal(<?php echo $l['id']; ?>, '<?php echo htmlspecialchars($l['request_id']); ?>', '<?php echo htmlspecialchars($l['ai_decision']); ?>')">
                                        <i class="fas fa-edit"></i> Override
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Override Modal -->
    <div class="modal" id="overrideModal">
        <div class="modal-content">
            <h3 style="margin-bottom:15px; color:#0f172a; font-size:16px;"><i class="fas fa-user-shield" style="color:#3b82f6;"></i> Manual Decision Override</h3>
            <form method="POST">
                <input type="hidden" name="action" value="manual_override">
                <input type="hidden" name="log_id" id="modal_log_id">
                
                <div class="form-group">
                    <label>Request ID</label>
                    <input type="text" id="modal_req_id" class="form-control" readonly style="background:#f1f5f9;">
                </div>
                <div class="form-group">
                    <label>New Manual Decision</label>
                    <select name="override_decision" class="form-control">
                        <option value="Approved">Approved</option>
                        <option value="Conditional">Conditional</option>
                        <option value="Rejected">Rejected</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Justification / Reason (For Model Retraining)</label>
                    <textarea name="override_reason" class="form-control" rows="3" placeholder="e.g. Field engineer verified dedicated sub-feeder backup..." required></textarea>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                    <button type="button" class="btn btn-outline" onclick="closeOverrideModal()">Cancel</button>
                    <button type="submit" class="btn btn-override">Save Override</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openOverrideModal(id, reqId, currentDecision) {
            document.getElementById('modal_log_id').value = id;
            document.getElementById('modal_req_id').value = reqId + ' (Current: ' + currentDecision + ')';
            document.getElementById('overrideModal').classList.add('show');
        }
        function closeOverrideModal() {
            document.getElementById('overrideModal').classList.remove('show');
        }
    </script>
</body>
</html>
