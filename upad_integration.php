<?php
/**
 * UMAN Staff: Urban Planning (UPAD) Integration Hub
 *
 * View inbound electrical/grid inspection requests from UPAD,
 * test integration, run real AI scoring, and send inspection callbacks back to UPAD.
 */
require_once 'includes/auth.php';
require_once 'includes/db.php';
define('UMAN_HTML_PAGE', true);
require_once __DIR__ . '/api/integration_config.php';

if (!isLoggedIn() || !isEmployee()) {
    header('Location: login.php');
    exit();
}

$errors = [];
$successes = [];

// Ensure tables exist
try {
    $pdo->exec(file_get_contents(__DIR__ . '/sql/inspection_ai.sql'));
} catch (Throwable $e) {
    // fail-safe
}

// ── Handle Manual Callback Submission from UI ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_callback') {
    $refId            = trim($_POST['reference_id'] ?? '');
    $inspectionDate   = trim($_POST['inspection_date'] ?? date('Y-m-d'));
    $engineerAssigned = trim($_POST['engineer_assigned'] ?? 'Engr. Juan Dela Cruz');
    $overallCondition = trim($_POST['overall_condition'] ?? 'Good');
    $gridCondition    = trim($_POST['grid_capacity_condition'] ?? 'Good');
    $transformerCond  = trim($_POST['transformer_condition'] ?? 'Good');
    $lineCond         = trim($_POST['line_condition'] ?? 'Good');
    $loadForecastCond = trim($_POST['load_forecast_condition'] ?? 'Good');
    $severity         = trim($_POST['severity'] ?? 'Low');
    $recommendation   = trim($_POST['recommendation'] ?? 'Approved for grid connection');
    $remarks          = trim($_POST['remarks'] ?? '');

    if (empty($refId)) {
        $errors[] = "Reference ID is required.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM upad_inspection_requests WHERE reference_id = ?");
        $stmt->execute([$refId]);
        $req = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$req) {
            $errors[] = "Request ref $refId not found.";
        } else {
            $callbackPayload = [
                'application_id'           => (int) $req['application_id'],
                'grid_id'                  => $refId,
                'inspection_date'          => $inspectionDate,
                'engineer_assigned'        => $engineerAssigned,
                'grid_capacity_condition'  => $gridCondition,
                'transformer_condition'    => $transformerCond,
                'line_condition'           => $lineCond,
                'load_forecast_condition'  => $loadForecastCond,
                'overall_condition'        => $overallCondition,
                'severity'                 => $severity,
                'recommendation'           => $recommendation,
                'gps_latitude'             => $req['latitude'] ? (float)$req['latitude'] : null,
                'gps_longitude'            => $req['longitude'] ? (float)$req['longitude'] : null,
                'remarks'                  => $remarks,
                'photo_urls'               => [],
            ];

            $callbackJson = json_encode($callbackPayload, JSON_UNESCAPED_UNICODE);
            $callbackUrl  = !empty($req['callback_url']) ? $req['callback_url'] : UPAD_DEFAULT_CALLBACK_URL;
            $signature    = hash_hmac('sha256', $callbackJson, UPAD_WEBHOOK_SECRET);

            $sendCurl = function($targetUrl) use ($callbackJson, $signature) {
                $ch = curl_init($targetUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => $callbackJson,
                    CURLOPT_HTTPHEADER     => [
                        'Content-Type: application/json',
                        'X-UMAN-Signature: ' . $signature,
                    ],
                    CURLOPT_TIMEOUT        => 10,
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);
                $responseBody = curl_exec($ch);
                $httpCode     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlErr      = curl_error($ch);

                return [$httpCode, $curlErr, $responseBody];
            };

            [$httpCode, $curlErr, $responseBody] = $sendCurl($callbackUrl);
            $success = !$curlErr && $httpCode >= 200 && $httpCode < 300;

            if (!$success && UPAD_DEFAULT_CALLBACK_URL !== $callbackUrl) {
                [$httpCode, $curlErr, $responseBody] = $sendCurl(UPAD_DEFAULT_CALLBACK_URL);
                $success = !$curlErr && $httpCode >= 200 && $httpCode < 300;
            }

            $errText = $curlErr ?: ($success ? null : "HTTP $httpCode: " . mb_substr(strip_tags($responseBody ?: ''), 0, 150));

            // Update status in DB
            $pdo->prepare("
                UPDATE upad_inspection_requests
                SET status = ?, result_payload = ?, callback_sent_at = NOW(), callback_http_code = ?, callback_error = ?
                WHERE reference_id = ?
            ")->execute([
                $success ? 'completed' : 'failed',
                json_encode(['sent' => $callbackPayload, 'http_code' => $httpCode, 'response' => mb_substr($responseBody ?: '', 0, 1000)]),
                $httpCode ?: null,
                $errText,
                $refId
            ]);

            if ($success) {
                $successes[] = "Inspection callback for $refId successfully delivered to UPAD! (HTTP $httpCode)";
            } else {
                $errors[] = "Callback attempt saved, but delivery failed ($errText). Status updated.";
            }
        }
    }
}

// ── Handle AI Validation Trigger ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'run_ai_validation') {
    $refId = trim($_POST['reference_id'] ?? '');
    if (empty($refId)) {
        $errors[] = "Reference ID is required.";
    } else {
        require_once __DIR__ . '/api/v1/inspection_ai.php';
        $aiResult = runInspectionAIValidation($refId, $pdo);
        if ($aiResult['success']) {
            if ($aiResult['approved']) {
                $successes[] = "AI Validation Success ($refId): Score {$aiResult['score']}/100 ({$aiResult['decision']}). Callback dispatched to UPAD.";
            } else {
                $errors[] = "AI Validation ($refId): Score {$aiResult['score']}/100 ({$aiResult['decision']}). " . $aiResult['message'];
            }
        } else {
            $errors[] = "AI Engine Error: " . $aiResult['error'];
        }
    }
}

// ── Handle Seed Real Urban Planning Requests ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'seed_test_request') {
    $realProjects = [
        [
            'project' => 'Quezon Boulevard Urban Drainage & Power Outflow Inspection',
            'barangay' => 'Quiapo',
            'district' => 'District 3',
            'category' => 'Infrastructure',
            'load' => 350.00,
            'priority' => 'Urgent',
            'address' => 'Quezon Blvd, Quiapo, Manila',
            'lat' => 14.59833300,
            'lng' => 120.98500000,
            'desc' => 'Urban planning grid inspection for high-volume commercial drainage and lighting project.'
        ],
        [
            'project' => 'Magsaysay Boulevard Electrical Substation & Pole Connection',
            'barangay' => 'Santa Mesa',
            'district' => 'District 6',
            'category' => 'Commercial',
            'load' => 600.00,
            'priority' => 'Medium',
            'address' => 'Magsaysay Blvd, Santa Mesa, Manila',
            'lat' => 14.60194400,
            'lng' => 121.00833300,
            'desc' => 'Grid capacity verification for new civic infrastructure development.'
        ],
        [
            'project' => 'Taft Avenue Smart Streetlight & Microgrid Expansion',
            'barangay' => 'Malate',
            'district' => 'District 5',
            'category' => 'Infrastructure',
            'load' => 450.00,
            'priority' => 'Low',
            'address' => 'Taft Avenue corner Vito Cruz, Manila',
            'lat' => 14.56388900,
            'lng' => 120.99472200,
            'desc' => 'Load analysis and solar grid sync check for smart street lighting corridor.'
        ]
    ];

    $selected = $realProjects[array_rand($realProjects)];
    $testAppId = rand(2000, 9999);
    $year      = date('Y');
    $seqRow    = $pdo->query("SELECT COUNT(*) AS c FROM upad_inspection_requests WHERE YEAR(created_at) = $year")->fetch(PDO::FETCH_ASSOC);
    $seq       = ((int) ($seqRow['c'] ?? 0)) + 1;
    $refId     = sprintf('EG-%s-%03d', $year, $seq);

    $stmt = $pdo->prepare("
        INSERT INTO upad_inspection_requests
            (reference_id, application_id, source_system, project_name, barangay, district, category,
             estimated_load_kva, priority, address, latitude, longitude, description, requested_by,
             callback_url, status, created_at)
        VALUES (?, ?, 'UPAD', ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                'Urban Planning Office',
                ?,
                'pending', NOW())
    ");
    $stmt->execute([
        $refId,
        $testAppId,
        $selected['project'],
        $selected['barangay'],
        $selected['district'],
        $selected['category'],
        $selected['load'],
        $selected['priority'],
        $selected['address'],
        $selected['lat'],
        $selected['lng'],
        $selected['desc'],
        UPAD_DEFAULT_CALLBACK_URL
    ]);
    $successes[] = "Real Urban Planning Inspection Request created ($refId - {$selected['project']}).";
}

// ── Query statistics ─────────────────────────────────────────────────────────
$totalCount     = (int) $pdo->query("SELECT COUNT(*) FROM upad_inspection_requests")->fetchColumn();
$pendingCount   = (int) $pdo->query("SELECT COUNT(*) FROM upad_inspection_requests WHERE status = 'pending'")->fetchColumn();
$completedCount = (int) $pdo->query("SELECT COUNT(*) FROM upad_inspection_requests WHERE status = 'completed'")->fetchColumn();
$failedCount    = (int) $pdo->query("SELECT COUNT(*) FROM upad_inspection_requests WHERE status = 'failed'")->fetchColumn();

// Fetch inspection requests
$stmt = $pdo->query("SELECT * FROM upad_inspection_requests ORDER BY created_at DESC LIMIT 100");
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Poppins', sans-serif;
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
            -webkit-backdrop-filter: blur(6px);
            background: rgba(0, 0, 0, 0.30);
            z-index: 0;
            transition: background 0.3s ease;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
            transition: margin-left 0.25s ease;
            z-index: 1;
            position: relative;
        }

        .page-header {
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .page-header h1 {
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .page-header p {
            color: #64748b;
            font-size: 14px;
            margin-top: 4px;
        }

        .page-wrapper {
            width: 100%;
            max-width: 1700px;
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border-radius: 18px;
            padding: 35px 40px;
            box-shadow: 0 6px 24px rgba(0,0,0,0.22);
            border: 1px solid rgba(255,255,255,0.3);
            color: #1e293b;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.07);
            display: flex;
            align-items: center;
            gap: 15px;
            border-left: 5px solid #3b82f6;
        }
        .stat-card.pending { border-left-color: #f59e0b; }
        .stat-card.completed { border-left-color: #10b981; }
        .stat-card.failed { border-left-color: #ef4444; }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            display: grid;
            place-items: center;
            font-size: 20px;
        }
        .stat-card.pending .stat-icon { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .stat-card.completed .stat-icon { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .stat-card.failed .stat-icon { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

        .stat-info h3 { font-size: 24px; font-weight: 700; color: #0f172a; }
        .stat-info p { font-size: 12px; color: #64748b; font-weight: 500; }

        .card {
            background: #ffffff;
            border-radius: 14px;
            padding: 22px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.07);
            margin-bottom: 24px;
            border: 1px solid rgba(0,0,0,0.06);
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        .card-header h2 {
            font-size: 18px;
            font-weight: 600;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Buttons */
        .btn {
            padding: 8px 14px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 12px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        .btn-primary { background: #3b82f6; color: #fff; }
        .btn-primary:hover { background: #2563eb; }
        .btn-ai { background: #6366f1; color: #fff; }
        .btn-ai:hover { background: #4f46e5; }
        .btn-success { background: #10b981; color: #fff; }
        .btn-success:hover { background: #059669; }
        .btn-outline { background: #f8fafc; color: #475569; border: 1px solid #cbd5e1; }
        .btn-outline:hover { background: #f1f5f9; }

        /* Alerts */
        .alert {
            padding: 14px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        /* Table */
        .table-responsive { overflow-x: auto; }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        th {
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            text-align: left;
            padding: 12px 16px;
            border-bottom: 2px solid #e2e8f0;
        }
        td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
        }
        tr:hover td { background: #f8fafc; }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-completed { background: #d1fae5; color: #065f46; }
        .badge-failed { background: #fee2e2; color: #991b1b; }
        .badge-urgent { background: #fee2e2; color: #b91c1c; }
        .badge-medium { background: #e0f2fe; color: #0369a1; }
        .badge-low { background: #f1f5f9; color: #475569; }
        .badge-score { background: #e0e7ff; color: #3730a3; font-weight: 700; }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(6px);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        .modal.show { display: flex; }
        .modal-content {
            background: #ffffff;
            border-radius: 16px;
            width: 620px;
            max-width: 95%;
            padding: 25px;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
        }
        .modal-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #e2e8f0;
        }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 6px; }
        .form-control {
            width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid #cbd5e1;
            font-size: 13px; font-family: inherit;
        }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }

        /* Dark Theme Overrides */
        .dark-theme body::before { background: rgba(5, 10, 22, 0.80) !important; }
        .dark-theme body { color: #f1f5f9 !important; }
        .dark-theme .page-header h1 { color: #f8fafc !important; }
        .dark-theme .page-wrapper { background: rgba(10, 18, 35, 0.92) !important; border-color: rgba(255,255,255,0.08) !important; }
        .dark-theme .stat-card { background: rgba(30, 41, 59, 0.95) !important; }
        .dark-theme .stat-info h3 { color: #f8fafc !important; }
        .dark-theme .card { background: rgba(15, 23, 42, 0.85) !important; border-color: #334155 !important; }
        .dark-theme th { background: #151f32 !important; color: #94a3b8 !important; border-bottom-color: #334155 !important; }
        .dark-theme td { color: #cbd5e1 !important; border-bottom-color: #334155 !important; }
        .dark-theme tr:hover td { background: rgba(255,255,255,0.04) !important; }
        .dark-theme .modal-content { background: #1e293b !important; color: #f8fafc !important; border-color: #475569 !important; }
        .dark-theme .form-control { background: #0f172a !important; border-color: #475569 !important; color: #f8fafc !important; }
    </style>
</head>
<body>
    <?php include 'includes/utilities_sidebar.php'; ?>

    <main class="main-content">
        <div class="page-wrapper">
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-city" style="color: #3b82f6;"></i> Urban Planning (UPAD) Integration Hub</h1>
                    <p>Evaluate real infrastructure conditions, calculate AI inspection scores, and dispatch approvals to UPAD.</p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="ai_weights_dashboard.php" class="btn btn-outline"><i class="fas fa-sliders-h"></i> AI Weights</a>
                    <a href="ai_feedback_loop.php" class="btn btn-outline"><i class="fas fa-brain"></i> AI Feedback</a>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="seed_test_request">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i> Create Test Request
                        </button>
                    </form>
                </div>
            </div>

            <?php foreach ($successes as $s): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($s); ?></div>
            <?php endforeach; ?>

            <?php foreach ($errors as $e): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($e); ?></div>
            <?php endforeach; ?>

            <!-- Stats Overview -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $totalCount; ?></h3>
                        <p>Total Requests</p>
                    </div>
                </div>
                <div class="stat-card pending">
                    <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $pendingCount; ?></h3>
                        <p>Awaiting Inspection</p>
                    </div>
                </div>
                <div class="stat-card completed">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $completedCount; ?></h3>
                        <p>Callback Delivered</p>
                    </div>
                </div>
                <div class="stat-card failed">
                    <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $failedCount; ?></h3>
                        <p>Delivery Failed</p>
                    </div>
                </div>
            </div>

            <!-- Inbound API Endpoint Information -->
            <div class="card" style="background: linear-gradient(135deg, #1e293b, #0f172a); color: #fff;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <div>
                        <h3 style="font-size: 16px; color: #38bdf8; margin-bottom: 6px;"><i class="fas fa-network-wired"></i> Live UPAD Inbound Endpoint</h3>
                        <p style="font-size: 13px; color: #94a3b8;">Urban Planning system POSTs inspection requests directly to:</p>
                        <code style="display: block; background: rgba(255,255,255,0.1); padding: 8px 12px; border-radius: 6px; margin-top: 8px; font-family: monospace; color: #4ade80;">
                            https://uman.infragovservices.com/api/v1/inspection-requests.php
                        </code>
                    </div>
                    <div>
                        <span style="font-size: 12px; background: rgba(56, 189, 248, 0.2); color: #38bdf8; padding: 6px 12px; border-radius: 20px; font-weight: 600;">
                            Bearer Key: UPAD_UMAN_INTEGRATION_KEY_2026
                        </span>
                    </div>
                </div>
            </div>

            <!-- Requests List -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-list"></i> Real UPAD Inspection Requests & AI Decisions</h2>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Ref ID</th>
                                <th>UPAD App ID</th>
                                <th>Project / Barangay</th>
                                <th>Category</th>
                                <th>Load (kVA)</th>
                                <th>Priority</th>
                                <th>AI Evaluation</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($requests)): ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; color: #94a3b8; padding: 30px;">
                                        No inspection requests received yet. Click <strong>Create Test Request</strong> above to simulate one!
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($requests as $r): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($r['reference_id']); ?></strong></td>
                                        <td>#<?php echo (int) $r['application_id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($r['project_name'] ?: 'N/A'); ?></strong><br>
                                            <small style="color: #64748b;"><?php echo htmlspecialchars($r['barangay'] ?: 'No Barangay'); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($r['category'] ?: 'General'); ?></td>
                                        <td><?php echo $r['estimated_load_kva'] ? number_format((float)$r['estimated_load_kva'], 1) . ' kVA' : 'N/A'; ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo strtolower($r['priority']); ?>">
                                                <?php echo htmlspecialchars($r['priority']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($r['ai_score'] !== null): ?>
                                                <span class="badge badge-score"><?php echo $r['ai_score']; ?>%</span>
                                                <small style="color:#64748b; display:block;"><?php echo htmlspecialchars($r['ai_decision'] ?? ''); ?></small>
                                            <?php else: ?>
                                                <small style="color:#94a3b8;">Pending AI</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo strtolower($r['status']); ?>">
                                                <?php echo htmlspecialchars($r['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display:flex; gap:6px;">
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="action" value="run_ai_validation">
                                                    <input type="hidden" name="reference_id" value="<?php echo htmlspecialchars($r['reference_id']); ?>">
                                                    <button type="submit" class="btn btn-ai" title="Evaluate real grid data & auto-approve">
                                                        <i class="fas fa-robot"></i> AI Validate
                                                    </button>
                                                </form>

                                                <button type="button" class="btn btn-success" onclick="openCallbackModal('<?php echo htmlspecialchars($r['reference_id']); ?>')">
                                                    <i class="fas fa-paper-plane"></i> Callback
                                                </button>
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

    <!-- Callback Modal -->
    <div class="modal" id="callbackModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-paper-plane" style="color: #10b981;"></i> Send Inspection Callback to UPAD</h3>
                <button type="button" onclick="closeCallbackModal()" style="border:none; background:none; font-size:18px; cursor:pointer;">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="send_callback">
                <input type="hidden" name="reference_id" id="modal_ref_id">

                <div class="form-grid">
                    <div class="form-group">
                        <label>Inspection Date</label>
                        <input type="date" name="inspection_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Engineer Assigned</label>
                        <input type="text" name="engineer_assigned" class="form-control" value="Engr. Juan Dela Cruz" required>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Overall Condition</label>
                        <select name="overall_condition" class="form-control">
                            <option value="Excellent">Excellent</option>
                            <option value="Good" selected>Good</option>
                            <option value="Fair">Fair</option>
                            <option value="Poor">Poor</option>
                            <option value="Critical">Critical</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Grid Capacity Condition</label>
                        <select name="grid_capacity_condition" class="form-control">
                            <option value="Good" selected>Good (Within Load Limit)</option>
                            <option value="Fair">Fair (Near Capacity)</option>
                            <option value="Poor">Poor (Overloaded)</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Transformer Condition</label>
                        <select name="transformer_condition" class="form-control">
                            <option value="Good" selected>Good</option>
                            <option value="Fair">Fair</option>
                            <option value="Poor">Poor</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Severity Level</label>
                        <select name="severity" class="form-control">
                            <option value="Low" selected>Low</option>
                            <option value="Medium">Medium</option>
                            <option value="High">High</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Recommendation</label>
                    <input type="text" name="recommendation" class="form-control" value="Grid capacity verified. Approved for connection." required>
                </div>

                <div class="form-group">
                    <label>Engineer Remarks</label>
                    <textarea name="remarks" class="form-control" rows="3">Site inspection completed. Existing transformer capacity and grid stability are sufficient.</textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn btn-outline" onclick="closeCallbackModal()">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Send Callback to UPAD</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openCallbackModal(refId) {
            document.getElementById('modal_ref_id').value = refId;
            document.getElementById('callbackModal').classList.add('show');
        }
        function closeCallbackModal() {
            document.getElementById('callbackModal').classList.remove('show');
        }
    </script>
</body>
</html>