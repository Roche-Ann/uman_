<?php
// upad_integration.php - Urban Planning Integration Hub (Real Data)
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn() || !isEmployee()) {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';

// ================================================================
// HANDLE ACTIONS
// ================================================================

// AI Validate Request
if (isset($_GET['ai_validate']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // Fetch the request
    $stmt = $pdo->prepare("SELECT * FROM inspection_requests WHERE id = ?");
    $stmt->execute([$id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($request) {
        // Call the AI inspection API
        $apiKey = getenv('UMAN_INTEGRATION_API_KEY') ?: 'UMAN_SECURE_KEY_2025';
        $url = 'https://' . $_SERVER['HTTP_HOST'] . '/api/inspection.php?key=' . $apiKey;
        
        $payload = [
            'request_id' => $request['ref_id'],
            'location' => $request['barangay'] ?: $request['project_name'],
            'utility_type' => 'Electrical',
            'project_id' => $request['id'],
            'details' => $request['project_name'] . ' - Load: ' . $request['load_kva'] . ' kVA'
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            $decision = $result['decision'] ?? 'Conditional';
            $score = $result['ai_score'] ?? 0;
            
            // Map decision to status
            $statusMap = [
                'Approved' => 'APPROVED',
                'Conditional' => 'CONDITIONAL',
                'Rejected' => 'REJECTED'
            ];
            $newStatus = $statusMap[$decision] ?? 'PROCESSING';
            
            // Update the request
            $stmt = $pdo->prepare("
                UPDATE inspection_requests 
                SET status = ?, ai_score = ?, ai_decision = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$newStatus, $score, $decision, $id]);
            
            $success = "AI validation completed for {$request['ref_id']}. Decision: $decision (Score: $score/100)";
        } else {
            $error = "AI API call failed. HTTP Code: $httpCode";
        }
    } else {
        $error = "Request not found.";
    }
    header('Location: upad_integration.php' . ($success ? '?success=' . urlencode($success) : '?error=' . urlencode($error)));
    exit();
}

// Send Result (Deliver callback)
if (isset($_GET['send_result']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    $stmt = $pdo->prepare("SELECT * FROM inspection_requests WHERE id = ?");
    $stmt->execute([$id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($request && $request['callback_url']) {
        // Send result back to UPAD
        $payload = [
            'ref_id' => $request['ref_id'],
            'status' => $request['status'],
            'ai_score' => $request['ai_score'],
            'ai_decision' => $request['ai_decision'],
            'delivered_at' => date('Y-m-d H:i:s')
        ];
        
        $ch = curl_init($request['callback_url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            // Mark as delivered
            $stmt = $pdo->prepare("UPDATE inspection_requests SET status = 'DELIVERED', delivered_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            $success = "Result delivered to UPAD successfully.";
        } else {
            $error = "Failed to deliver result. HTTP Code: $httpCode";
        }
    } else {
        $error = "No callback URL available for this request.";
    }
    header('Location: upad_integration.php' . ($success ? '?success=' . urlencode($success) : '?error=' . urlencode($error)));
    exit();
}

// Create Test Request (for testing)
if (isset($_POST['create_test'])) {
    $refId = 'EG-2026-' . str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);
    $projects = [
        'Tondo North Extension Substation',
        'Quiapo Commercial Grid Upgrade',
        'Sampaloc Microgrid Expansion',
        'Rizal Avenue Smart Lighting',
        'Ermita Power Distribution Center'
    ];
    $barangays = ['Tondo', 'Quiapo', 'Sampaloc', 'Ermita', 'Malate'];
    $categories = ['Commercial', 'Residential', 'Infrastructure', 'Industrial'];
    $priorities = ['LOW', 'MEDIUM', 'HIGH', 'URGENT'];
    
    $stmt = $pdo->prepare("
        INSERT INTO inspection_requests (ref_id, project_name, barangay, category, load_kva, priority, callback_url)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $refId,
        $projects[array_rand($projects)],
        $barangays[array_rand($barangays)],
        $categories[array_rand($categories)],
        rand(100, 800),
        $priorities[array_rand($priorities)],
        'https://upad.example.com/callback/' . $refId
    ]);
    $success = "Test request $refId created successfully.";
    header('Location: upad_integration.php?success=' . urlencode($success));
    exit();
}

// Handle success/error messages
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// ================================================================
// FETCH REAL DATA
// ================================================================

// Get statistics
$total = $pdo->query("SELECT COUNT(*) FROM inspection_requests")->fetchColumn();
$pending = $pdo->query("SELECT COUNT(*) FROM inspection_requests WHERE status = 'PENDING' OR status = 'PROCESSING'")->fetchColumn();
$delivered = $pdo->query("SELECT COUNT(*) FROM inspection_requests WHERE status = 'DELIVERED'")->fetchColumn();

// Fetch all requests
$requests = $pdo->query("
    SELECT * FROM inspection_requests 
    ORDER BY requested_at DESC 
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

// Get API key for display
$apiKey = getenv('UMAN_INTEGRATION_API_KEY') ?: 'UMAN_SECURE_KEY_2025';
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
    <title>Urban Planning (UPAD) Integration Hub</title>
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif; }
        body { min-height:100vh; display:flex; background:url("assets/images/cityhall.jpeg") center/cover no-repeat fixed; position:relative; }
        body::before { content:""; position:absolute; top:0; left:0; width:100%; height:100%; backdrop-filter:blur(6px); background:rgba(0,0,0,0.35); z-index:0; }
        .main-content { flex:1; margin-left:280px; padding:30px 40px; z-index:1; position:relative; }
        .card { width:100%; max-width:1700px; background:rgba(255,255,255,0.85); backdrop-filter:blur(15px); border-radius:18px; padding:40px; box-shadow:0 6px 20px rgba(0,0,0,0.2); border:1px solid rgba(255,255,255,0.25); }
        .dashboard-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:30px; flex-wrap:wrap; gap:20px; }
        .dashboard-header h1 { color:#2c3e50; font-size:32px; font-weight:700; display:flex; align-items:center; gap:15px; }
        .dashboard-header h1 i { color:#3762c8; }
        .btn { padding:10px 20px; border-radius:8px; font-weight:600; font-size:14px; border:none; cursor:pointer; display:inline-flex; align-items:center; gap:8px; text-decoration:none; }
        .btn-primary { background:#3762c8; color:#fff; }
        .btn-primary:hover { background:#2851b0; }
        .btn-success { background:#28a745; color:#fff; }
        .btn-danger { background:#dc3545; color:#fff; }
        .btn-warning { background:#ffc107; color:#212529; }
        .btn-outline { background:transparent; border:1px solid #cbd5e1; color:#64748b; }
        .btn-outline:hover { background:#f8f9fa; }
        .btn-sm { padding:6px 12px; font-size:12px; }
        
        .stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:20px; margin-bottom:30px; }
        .stat-card { background:white; border-radius:12px; padding:20px; border-left:5px solid #3762c8; }
        .stat-card h3 { font-size:28px; font-weight:700; color:#2c3e50; }
        .stat-card p { font-size:12px; color:#64748b; text-transform:uppercase; font-weight:600; }
        .stat-card.pending { border-left-color:#f1c40f; }
        .stat-card.delivered { border-left-color:#2ecc71; }

        .alert { padding:15px 20px; border-radius:8px; margin-bottom:25px; font-weight:500; }
        .alert-success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
        .alert-error { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }

        .table-container { overflow-x:auto; background:white; border-radius:12px; padding:20px; }
        table { width:100%; border-collapse:collapse; }
        th { background:#f8f9fa; color:#475569; font-size:12px; text-transform:uppercase; padding:12px 16px; border-bottom:2px solid #e2e8f0; text-align:left; }
        td { padding:12px 16px; border-bottom:1px solid #edf2f7; font-size:13px; }
        tr:hover td { background:#fcfcfc; }
        
        .badge { padding:3px 10px; border-radius:99px; font-size:10px; font-weight:700; text-transform:uppercase; }
        .badge-pending { background:#fef3c7; color:#92400e; }
        .badge-processing { background:#dbeafe; color:#1e40af; }
        .badge-approved { background:#d1fae5; color:#065f46; }
        .badge-conditional { background:#fef3c7; color:#92400e; }
        .badge-rejected { background:#fee2e2; color:#991b1b; }
        .badge-failed { background:#fee2e2; color:#991b1b; }
        .badge-delivered { background:#d1fae5; color:#065f46; }
        .badge-low { background:#d1fae5; color:#065f46; }
        .badge-medium { background:#dbeafe; color:#1e40af; }
        .badge-high { background:#fef3c7; color:#92400e; }
        .badge-urgent { background:#fee2e2; color:#991b1b; }

        .api-box { background:#f8fafc; padding:15px; border-radius:8px; margin:15px 0; border:1px solid #e2e8f0; font-size:13px; }
        .api-box code { background:#e2e8f0; padding:2px 6px; border-radius:4px; font-size:12px; }
        .actions { display:flex; gap:6px; flex-wrap:wrap; }
    </style>
</head>
<body>
<?php include 'includes/utilities_sidebar.php'; ?>
<main class="main-content">
    <div class="card">
        <div class="dashboard-header">
            <div>
                <h1><i class="fas fa-city"></i> Urban Planning (UPAD) Integration Hub</h1>
                <p style="color:#64748b; font-size:14px;">Monitor and manage electrical/grid load inspection requests from Urban Planning.</p>
            </div>
            <div>
                <form method="POST" style="display:inline;">
                    <button type="submit" name="create_test" class="btn btn-outline btn-sm">
                        <i class="fas fa-plus"></i> Create Test Request
                    </button>
                </form>
                <a href="utilities_dashboard.php" class="btn btn-outline btn-sm"><i class="fas fa-chevron-left"></i> Dashboard</a>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo number_format($total); ?></h3>
                <p>Total Requests</p>
            </div>
            <div class="stat-card pending">
                <h3><?php echo number_format($pending); ?></h3>
                <p>Awaiting Inspection</p>
            </div>
            <div class="stat-card delivered">
                <h3><?php echo number_format($delivered); ?></h3>
                <p>Delivered</p>
            </div>
        </div>

        <div class="api-box">
            <strong><i class="fas fa-link"></i> Live System Integration Endpoint</strong><br>
            Urban Planning system POSTs inspection requests directly to:
            <code>https://uman.infragovservices.com/api/inspection.php?key=YOUR_API_KEY</code>
            <br><br>
            <strong>Bearer Key:</strong> <code><?php echo htmlspecialchars($apiKey); ?></code>
            <br>
            <span style="color:#94a3b8; font-size:12px;">Share this key with the Urban Planning team for authentication.</span>
        </div>

        <div style="margin-top:20px;">
            <h3 style="color:#2c3e50; margin-bottom:15px;"><i class="fas fa-list"></i> Inbound UPAD Inspection Requests</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Ref ID</th>
                            <th>UPAD App ID</th>
                            <th>Project / Barangay</th>
                            <th>Category</th>
                            <th>Load (kVA)</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Requested At</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($requests)): ?>
                            <tr><td colspan="9" style="text-align:center; padding:30px; color:#94a3b8;">No inspection requests found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($requests as $req): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($req['ref_id']); ?></strong></td>
                                <td><?php echo htmlspecialchars($req['upad_app_id'] ?: 'N/A'); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($req['project_name']); ?></strong>
                                    <?php if ($req['barangay']): ?>
                                        <br><small style="color:#64748b;"><?php echo htmlspecialchars($req['barangay']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($req['category']); ?></td>
                                <td><strong><?php echo number_format($req['load_kva'], 1); ?></strong> kVA</td>
                                <td><span class="badge badge-<?php echo strtolower($req['priority']); ?>"><?php echo $req['priority']; ?></span></td>
                                <td>
                                    <span class="badge badge-<?php echo strtolower($req['status']); ?>">
                                        <?php echo $req['status']; ?>
                                    </span>
                                    <?php if ($req['ai_score'] !== null): ?>
                                        <br><small style="font-size:10px;">Score: <?php echo $req['ai_score']; ?>%</small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y H:i', strtotime($req['requested_at'])); ?></td>
                                <td style="text-align:center;">
                                    <div class="actions" style="justify-content:center;">
                                        <?php if ($req['status'] === 'PENDING' || $req['status'] === 'PROCESSING'): ?>
                                            <a href="upad_integration.php?ai_validate=1&id=<?php echo $req['id']; ?>" class="btn btn-primary btn-sm" onclick="return confirm('Run AI validation on this request?');">
                                                <i class="fas fa-robot"></i> AI Validate
                                            </a>
                                            <a href="upad_integration.php?send_result=1&id=<?php echo $req['id']; ?>" class="btn btn-success btn-sm" onclick="return confirm('Send result back to UPAD?');">
                                                <i class="fas fa-paper-plane"></i> Send Result
                                            </a>
                                        <?php elseif ($req['status'] === 'DELIVERED'): ?>
                                            <span style="color:#2ecc71; font-size:12px;"><i class="fas fa-check-circle"></i> Delivered</span>
                                        <?php else: ?>
                                            <span style="color:#64748b; font-size:12px;"><?php echo $req['status']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
</body>
</html>