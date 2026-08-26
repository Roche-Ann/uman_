<?php
/**
 * UMAN Staff: Urban Planning (UPAD) Integration Hub
 *
 * Real Inbound Electrical / Grid Inspection Monitoring,
 * AI Auto-Approval Engine, and Callback Delivery to UPAD.
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

// Self-healing database schema check (ensures columns exist without throwing warnings)
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `upad_inspection_requests` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `reference_id` VARCHAR(30) NOT NULL UNIQUE,
          `application_id` INT NOT NULL,
          `source_system` VARCHAR(20) NOT NULL DEFAULT 'UPAD',
          `project_name` VARCHAR(255) NULL,
          `barangay` VARCHAR(100) NULL,
          `district` VARCHAR(50) NULL,
          `category` VARCHAR(80) NULL,
          `estimated_load_kva` DECIMAL(10,2) NULL,
          `priority` ENUM('Urgent','Medium','Low') NOT NULL DEFAULT 'Medium',
          `address` TEXT NULL,
          `latitude` DECIMAL(10,7) NULL,
          `longitude` DECIMAL(10,7) NULL,
          `description` TEXT NULL,
          `requested_by` VARCHAR(150) NULL,
          `callback_url` TEXT NULL,
          `status` ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
          `ai_score` DECIMAL(5,2) NULL,
          `ai_decision` VARCHAR(50) NULL,
          `raw_payload` JSON NULL,
          `result_payload` JSON NULL,
          `callback_sent_at` DATETIME NULL,
          `callback_http_code` SMALLINT NULL,
          `callback_error` TEXT NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX `idx_application_id` (`application_id`),
          INDEX `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Add missing columns if table already existed without them
    $existingCols = $pdo->query("SHOW COLUMNS FROM `upad_inspection_requests`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('ai_score', $existingCols, true)) {
        $pdo->exec("ALTER TABLE `upad_inspection_requests` ADD COLUMN `ai_score` DECIMAL(5,2) NULL AFTER `status`");
    }
    if (!in_array('ai_decision', $existingCols, true)) {
        $pdo->exec("ALTER TABLE `upad_inspection_requests` ADD COLUMN `ai_decision` VARCHAR(50) NULL AFTER `ai_score`");
    }
    if (!in_array('corrective_recommendation', $existingCols, true)) {
        $pdo->exec("ALTER TABLE `upad_inspection_requests` ADD COLUMN `corrective_recommendation` TEXT NULL AFTER `ai_decision`");
    }
    if (!in_array('correction_requested_by', $existingCols, true)) {
        $pdo->exec("ALTER TABLE `upad_inspection_requests` ADD COLUMN `correction_requested_by` VARCHAR(100) NULL AFTER `corrective_recommendation`");
    }
    if (!in_array('correction_requested_at', $existingCols, true)) {
        $pdo->exec("ALTER TABLE `upad_inspection_requests` ADD COLUMN `correction_requested_at` DATETIME NULL AFTER `correction_requested_by`");
    }
} catch (Throwable $e) {
    // Fail-safe
}

// ── Handle Manual Callback Submission from UI ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_callback') {
    $refId            = trim((string)($_POST['reference_id'] ?? ''));
    $inspectionDate   = trim((string)($_POST['inspection_date'] ?? date('Y-m-d')));
    $engineerAssigned = trim((string)($_POST['engineer_assigned'] ?? 'Engr. Juan Dela Cruz'));
    $overallCondition = trim((string)($_POST['overall_condition'] ?? 'Good'));
    $gridCondition    = trim((string)($_POST['grid_capacity_condition'] ?? 'Good'));
    $transformerCond  = trim((string)($_POST['transformer_condition'] ?? 'Good'));
    $lineCond         = trim((string)($_POST['line_condition'] ?? 'Good'));
    $loadForecastCond = trim((string)($_POST['load_forecast_condition'] ?? 'Good'));
    $severity         = trim((string)($_POST['severity'] ?? 'Low'));
    $recommendation   = trim((string)($_POST['recommendation'] ?? 'Approved for grid connection'));
    $remarks          = trim((string)($_POST['remarks'] ?? ''));

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
                'gps_latitude'             => !empty($req['latitude']) ? (float)$req['latitude'] : null,
                'gps_longitude'            => !empty($req['longitude']) ? (float)$req['longitude'] : null,
                'remarks'                  => $remarks,
                'photo_urls'               => [],
            ];

            $callbackJson = json_encode($callbackPayload, JSON_UNESCAPED_UNICODE);
            
            // Normalize callback URL (fix legacy /api/webhooks/ or placeholder URLs)
            $rawCallback = trim((string)($req['callback_url'] ?? ''));
            if (empty($rawCallback) || str_contains($rawCallback, 'example.com') || str_contains($rawCallback, '/api/webhooks/')) {
                $callbackUrl = 'https://upad.infragovservices.com/uman-integration/uman_inspection_result.php';
            } else {
                $callbackUrl = $rawCallback;
            }
            
            $signature = hash_hmac('sha256', $callbackJson, UPAD_WEBHOOK_SECRET);

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

// ── Handle AI Validation & Auto-Approval Trigger ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'run_ai_validation') {
    $refId = trim((string)($_POST['reference_id'] ?? ''));
    if (empty($refId)) {
        $errors[] = "Reference ID is required.";
    } else {
        require_once __DIR__ . '/api/v1/inspection_ai.php';
        $aiResult = runInspectionAIValidation($refId, $pdo);
        if ($aiResult['success']) {
            if ($aiResult['approved']) {
                $statusMsg = $aiResult['callback_success'] 
                    ? "Callback successfully dispatched to UPAD (HTTP {$aiResult['callback_http_code']})."
                    : "Callback attempt recorded ({$aiResult['callback_error']}).";
                $successes[] = "AI Evaluation Complete ({$refId}): Decision {$aiResult['decision']} (Score: {$aiResult['score']}%). {$statusMsg}";
            } else {
                $errors[] = "AI Evaluation ({$refId}): Score {$aiResult['score']}% ({$aiResult['decision']}). " . $aiResult['message'];
            }
        } else {
            $errors[] = "AI Engine Error: " . $aiResult['error'];
        }
    }
}

// ── Handle Request Corrective Action ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_correction') {
    $refId = trim((string)($_POST['reference_id'] ?? ''));
    $recomText = trim((string)($_POST['recommendation'] ?? 'Corrective action required based on inspection findings.'));
    
    if (!empty($refId)) {
        $pdo->prepare("
            UPDATE upad_inspection_requests 
            SET status = 'sent_for_correction', 
                corrective_recommendation = ?, 
                correction_requested_by = 'System Administrator', 
                correction_requested_at = NOW()
            WHERE reference_id = ?
        ")->execute([$recomText, $refId]);
        
        $successes[] = "Inspection request $refId sent back for corrective action with AI-generated recommendations.";
    }
}

// ── Handle Reinspect Trigger ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reinspect') {
    $refId = trim((string)($_POST['reference_id'] ?? ''));
    if (!empty($refId)) {
        $pdo->prepare("
            UPDATE upad_inspection_requests 
            SET status = 'under_reinspection' 
            WHERE reference_id = ?
        ")->execute([$refId]);

        require_once __DIR__ . '/api/v1/inspection_ai.php';
        $aiResult = runInspectionAIValidation($refId, $pdo);
        if ($aiResult['success']) {
            $successes[] = "Inspection request $refId returned to inspection process and re-evaluated by AI Engine. Score: {$aiResult['score']}% ({$aiResult['decision']}).";
        } else {
            $errors[] = "Reinspection triggered for $refId: " . ($aiResult['error'] ?? 'Re-evaluation pending.');
        }
    }
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
            flex-wrap: wrap;
            gap: 15px;
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
            cursor: pointer;
            transition: all 0.2s ease;
            user-select: none;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        .stat-card.active-filter {
            box-shadow: 0 0 0 2px #3b82f6, 0 8px 20px rgba(59, 130, 246, 0.2);
        }
        .stat-card.pending { border-left-color: #f59e0b; }
        .stat-card.pending.active-filter { box-shadow: 0 0 0 2px #f59e0b, 0 8px 20px rgba(245, 158, 11, 0.2); }
        .stat-card.completed { border-left-color: #10b981; }
        .stat-card.completed.active-filter { box-shadow: 0 0 0 2px #10b981, 0 8px 20px rgba(16, 185, 129, 0.2); }
        .stat-card.failed { border-left-color: #ef4444; }
        .stat-card.failed.active-filter { box-shadow: 0 0 0 2px #ef4444, 0 8px 20px rgba(239, 68, 68, 0.2); }
        
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
            flex-wrap: wrap;
            gap: 15px;
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
            margin: 0;
        }

        /* Filter Tabs */
        .filter-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }
        .filter-tab {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            color: #475569;
            padding: 7px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            user-select: none;
        }
        .filter-tab:hover {
            background: #e2e8f0;
            color: #0f172a;
        }
        .filter-tab.active {
            background: #3b82f6;
            color: #ffffff;
            border-color: #3b82f6;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
        }
        .filter-tab .tab-badge {
            background: rgba(0, 0, 0, 0.08);
            padding: 2px 7px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 700;
        }
        .filter-tab.active .tab-badge {
            background: rgba(255, 255, 255, 0.25);
            color: #ffffff;
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
            vertical-align: middle;
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
        .badge-processing { background: #dbeafe; color: #1e40af; }
        .badge-completed { background: #d1fae5; color: #065f46; }
        .badge-failed { background: #fee2e2; color: #991b1b; }
        .badge-urgent { background: #fee2e2; color: #b91c1c; }
        .badge-medium { background: #e0f2fe; color: #0369a1; }
        .badge-low { background: #f1f5f9; color: #475569; }
        
        .score-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 12px;
        }
        .score-approved { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .score-conditional { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .score-rejected { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

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
                    <p>Inbound electrical grid inspection requests from Urban Planning with automated AI assessment and callback delivery.</p>
                </div>
                <div>
                    <a href="ai_analytics.php" class="btn btn-outline"><i class="fas fa-brain"></i> AI Analytics</a>
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
                <div class="stat-card" data-filter="all" onclick="filterRequests('all', document.querySelector('.filter-tab[data-filter=\'all\']'))" title="Click to view all requests">
                    <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $totalCount; ?></h3>
                        <p>Total UPAD Requests</p>
                    </div>
                </div>
                <div class="stat-card pending" data-filter="pending" onclick="filterRequests('pending', document.querySelector('.filter-tab[data-filter=\'pending\']'))" title="Click to view awaiting inspection">
                    <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $pendingCount; ?></h3>
                        <p>Awaiting Inspection</p>
                    </div>
                </div>
                <div class="stat-card completed" data-filter="completed" onclick="filterRequests('completed', document.querySelector('.filter-tab[data-filter=\'completed\']'))" title="Click to view delivered callbacks">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $completedCount; ?></h3>
                        <p>Callback Delivered</p>
                    </div>
                </div>
                <div class="stat-card failed" data-filter="failed" onclick="filterRequests('failed', document.querySelector('.filter-tab[data-filter=\'failed\']'))" title="Click to view failed deliveries">
                    <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $failedCount; ?></h3>
                        <p>Delivery Failed</p>
                    </div>
                </div>
            </div>

            <!-- Requests List -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-list" style="color: #3b82f6;"></i> UPAD Inspection Requests</h2>
                    
                    <!-- Clickable Filter Tabs -->
                    <div class="filter-tabs">
                        <button type="button" class="filter-tab active" data-filter="all" onclick="filterRequests('all', this)">
                            <i class="fas fa-layer-group"></i> All <span class="tab-badge"><?php echo $totalCount; ?></span>
                        </button>
                        <button type="button" class="filter-tab" data-filter="pending" onclick="filterRequests('pending', this)">
                            <i class="fas fa-hourglass-half"></i> Awaiting Inspection <span class="tab-badge"><?php echo $pendingCount; ?></span>
                        </button>
                        <button type="button" class="filter-tab" data-filter="completed" onclick="filterRequests('completed', this)">
                            <i class="fas fa-check-circle"></i> Delivered <span class="tab-badge"><?php echo $completedCount; ?></span>
                        </button>
                        <button type="button" class="filter-tab" data-filter="failed" onclick="filterRequests('failed', this)">
                            <i class="fas fa-times-circle"></i> Failed <span class="tab-badge"><?php echo $failedCount; ?></span>
                        </button>
                    </div>
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
                                <th>Callback Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($requests)): ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; color: #94a3b8; padding: 40px;">
                                        <i class="fas fa-inbox" style="font-size: 28px; margin-bottom: 8px; display: block;"></i>
                                        No inspection requests received from Urban Planning yet.<br>
                                        When Urban Planning staff clicks <strong>Request New Inspection</strong> in UPAD, it will appear here instantly.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <tr id="noMatchingFilterRow" style="display: none;">
                                    <td colspan="9" style="text-align: center; color: #94a3b8; padding: 40px;">
                                        <i class="fas fa-filter" style="font-size: 28px; margin-bottom: 8px; display: block; color: #64748b;"></i>
                                        No inspection requests match the selected filter tab.
                                    </td>
                                </tr>
                                <?php foreach ($requests as $r): ?>
                                    <?php 
                                        $aiScore = isset($r['ai_score']) && $r['ai_score'] !== null ? (float)$r['ai_score'] : null;
                                        $aiDecision = $r['ai_decision'] ?? null;
                                        $scoreClass = 'score-approved';
                                        if ($aiScore !== null) {
                                            if ($aiScore < 50.0) {
                                                $scoreClass = 'score-rejected';
                                            } elseif ($aiScore < 80.0) {
                                                $scoreClass = 'score-conditional';
                                            }
                                        }

                                        $rawStatus = strtolower(trim((string)($r['status'] ?? 'pending')));
                                        if ($rawStatus === 'completed' || $rawStatus === 'delivered') {
                                            $rowCategory = 'completed';
                                        } elseif ($rawStatus === 'failed') {
                                            $rowCategory = 'failed';
                                        } else {
                                            $rowCategory = 'pending';
                                        }
                                    ?>
                                    <tr class="request-row" data-status="<?php echo $rowCategory; ?>">
                                        <td><strong><?php echo htmlspecialchars($r['reference_id'] ?? ''); ?></strong></td>
                                        <td>#<?php echo (int) ($r['application_id'] ?? 0); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($r['project_name'] ?? 'N/A'); ?></strong><br>
                                            <small style="color: #64748b;"><?php echo htmlspecialchars($r['barangay'] ?? 'No Barangay'); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($r['category'] ?? 'Commercial'); ?></td>
                                        <td><?php echo !empty($r['estimated_load_kva']) ? number_format((float)$r['estimated_load_kva'], 1) . ' kVA' : 'N/A'; ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo strtolower($r['priority'] ?? 'medium'); ?>">
                                                <?php echo htmlspecialchars($r['priority'] ?? 'Medium'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($aiScore !== null): ?>
                                                <div class="score-pill <?php echo $scoreClass; ?>">
                                                    <i class="fas fa-robot"></i> <?php echo (int)round($aiScore); ?>% (<?php echo htmlspecialchars($aiDecision ?? ''); ?>)
                                                </div>
                                                <?php if ($aiDecision === 'Conditional'): ?>
                                                    <div style="margin-top: 4px;">
                                                        <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.3); font-size: 10px; font-weight: 700;">
                                                            <i class="fas fa-user-clock"></i> Manual Review
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <small style="color:#94a3b8;"><i class="fas fa-hourglass-start"></i> Pending AI</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                                $statusDisplay = match ($rawStatus) {
                                                    'completed', 'delivered' => '<span class="badge" style="background:rgba(16,185,129,0.15); color:#059669; border:1px solid rgba(16,185,129,0.3);"><i class="fas fa-check-circle"></i> Inspected</span>',
                                                    'sent_for_correction'    => '<span class="badge" style="background:rgba(239,68,68,0.15); color:#dc2626; border:1px solid rgba(239,68,68,0.3);"><i class="fas fa-reply"></i> Sent for Correction</span>',
                                                    'under_reinspection'     => '<span class="badge" style="background:rgba(147,51,234,0.15); color:#7e22ce; border:1px solid rgba(147,51,234,0.3);"><i class="fas fa-sync fa-spin"></i> Under Reinspection</span>',
                                                    'failed'                 => '<span class="badge" style="background:rgba(239,68,68,0.15); color:#dc2626; border:1px solid rgba(239,68,68,0.3);"><i class="fas fa-exclamation-triangle"></i> Delivery Failed</span>',
                                                    default                  => '<span class="badge" style="background:rgba(245,158,11,0.15); color:#d97706; border:1px solid rgba(245,158,11,0.3);"><i class="fas fa-hourglass-start"></i> Awaiting Inspection</span>',
                                                };
                                                echo $statusDisplay;
                                            ?>
                                            <?php if (!empty($r['callback_http_code'])): ?>
                                                <br><small style="color: #64748b; font-size: 11px;">HTTP <?php echo (int)$r['callback_http_code']; ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                                <button type="button" class="btn btn-outline" style="padding: 5px 10px; font-size: 11px;" 
                                                        onclick="openDetailsModal(
                                                            '<?php echo htmlspecialchars($r['reference_id'] ?? ''); ?>',
                                                            '<?php echo htmlspecialchars($r['project_name'] ?? ''); ?>',
                                                            '<?php echo htmlspecialchars($r['barangay'] ?? ''); ?>',
                                                            '<?php echo $aiScore !== null ? (int)round($aiScore) : 'N/A'; ?>',
                                                            '<?php echo htmlspecialchars($aiDecision ?? 'Pending'); ?>',
                                                            '<?php echo htmlspecialchars(addslashes($r['remarks'] ?? 'AI evaluation completed. Inspection result generated.')); ?>'
                                                        )">
                                                    <i class="fas fa-file-alt"></i> Details
                                                </button>

                                                <?php if ($aiDecision === 'Rejected' || $rawStatus === 'sent_for_correction'): ?>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="action" value="request_correction">
                                                        <input type="hidden" name="reference_id" value="<?php echo htmlspecialchars($r['reference_id'] ?? ''); ?>">
                                                        <input type="hidden" name="recommendation" value="<?php echo htmlspecialchars($r['remarks'] ?? 'Corrective action required based on inspection findings.'); ?>">
                                                        <button type="submit" class="btn btn-warning" style="padding: 5px 10px; font-size: 11px; background: #f59e0b; color: white;" title="Send inspection back to team with AI corrective recommendations">
                                                            <i class="fas fa-reply"></i> Send for Correction
                                                        </button>
                                                    </form>

                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="action" value="reinspect">
                                                        <input type="hidden" name="reference_id" value="<?php echo htmlspecialchars($r['reference_id'] ?? ''); ?>">
                                                        <button type="submit" class="btn btn-ai" style="padding: 5px 10px; font-size: 11px; background: #8b5cf6; color: white;" title="Re-evaluate request after corrective work completed">
                                                            <i class="fas fa-sync"></i> Reinspect
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="action" value="run_ai_validation">
                                                        <input type="hidden" name="reference_id" value="<?php echo htmlspecialchars($r['reference_id'] ?? ''); ?>">
                                                        <button type="submit" class="btn btn-ai" style="padding: 5px 10px; font-size: 11px;" title="Re-evaluate grid data">
                                                            <i class="fas fa-sync"></i> Re-Evaluate
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <button type="button" class="btn btn-success" style="padding: 5px 10px; font-size: 11px;" onclick="openCallbackModal('<?php echo htmlspecialchars($r['reference_id'] ?? ''); ?>')">
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

    <!-- Inspection Details Modal -->
    <div class="modal" id="detailsModal">
        <div class="modal-content" style="max-width: 620px;">
            <div class="modal-header">
                <h3><i class="fas fa-microchip" style="color: #3b82f6;"></i> Inspection Result & Recommended Action</h3>
                <button type="button" onclick="closeDetailsModal()" style="border:none; background:none; font-size:18px; cursor:pointer;">&times;</button>
            </div>
            <div style="padding: 10px 0;">
                <div style="display: flex; justify-content: space-between; align-items: center; background: #f8fafc; padding: 15px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #e2e8f0;">
                    <div>
                        <small style="color: #64748b; font-weight: 600; text-transform: uppercase; font-size: 11px;">Reference ID</small>
                        <h4 id="det_ref_id" style="margin: 2px 0 0 0; font-size: 18px; font-weight: 700; color: #0f172a;">-</h4>
                    </div>
                    <div style="text-align: right;">
                        <small style="color: #64748b; font-weight: 600; text-transform: uppercase; font-size: 11px;">AI Inspection Score</small>
                        <div id="det_score_badge" style="font-size: 22px; font-weight: 800; color: #3b82f6; font-family: monospace;">-%</div>
                    </div>
                </div>

                <div style="margin-bottom: 18px;">
                    <label style="font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; display: block; margin-bottom: 6px;">Inspection Result</label>
                    <div id="det_decision_box" style="padding: 14px 16px; border-radius: 10px; font-weight: 700; font-size: 15px; display: flex; align-items: center; justify-content: space-between;">
                        <span id="det_decision_text">-</span>
                        <span id="det_review_tag" style="display: none; font-size: 11px; padding: 4px 10px; border-radius: 6px; background: rgba(245, 158, 11, 0.2); color: #b45309; font-weight: 700;">
                            <i class="fas fa-user-clock"></i> Flagged for Manual Review
                        </span>
                    </div>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; display: block; margin-bottom: 6px;">AI-Generated Recommended Action</label>
                    <div style="background: rgba(59, 130, 246, 0.06); border-left: 4px solid #3b82f6; padding: 16px; border-radius: 8px; color: #1e293b; font-size: 13px; line-height: 1.6;">
                        <i class="fas fa-lightbulb" style="color: #3b82f6; margin-right: 6px; font-size: 15px;"></i>
                        <span id="det_recommendation_text">Evaluating recommendation...</span>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 25px;">
                    <button type="button" class="btn btn-outline" onclick="closeDetailsModal()">Close</button>
                    <button type="button" class="btn btn-success" onclick="closeDetailsModal(); openCallbackModal(document.getElementById('det_ref_id').innerText);">
                        <i class="fas fa-paper-plane"></i> Proceed to Callback
                    </button>
                </div>
            </div>
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

        function openDetailsModal(refId, project, barangay, score, decision, recommendation) {
            document.getElementById('det_ref_id').innerText = refId;
            document.getElementById('det_score_badge').innerText = (score !== 'N/A') ? score + '%' : 'N/A';

            const decisionBox = document.getElementById('det_decision_box');
            const decisionText = document.getElementById('det_decision_text');
            const reviewTag = document.getElementById('det_review_tag');

            decisionText.innerText = decision;

            if (decision === 'Approved') {
                decisionBox.style.background = 'rgba(16, 185, 129, 0.12)';
                decisionBox.style.color = '#065f46';
                decisionBox.style.border = '1px solid rgba(16, 185, 129, 0.3)';
                reviewTag.style.display = 'none';
            } else if (decision === 'Conditional') {
                decisionBox.style.background = 'rgba(245, 158, 11, 0.12)';
                decisionBox.style.color = '#92400e';
                decisionBox.style.border = '1px solid rgba(245, 158, 11, 0.3)';
                reviewTag.style.display = 'inline-block';
            } else if (decision === 'Rejected') {
                decisionBox.style.background = 'rgba(239, 68, 68, 0.12)';
                decisionBox.style.color = '#991b1b';
                decisionBox.style.border = '1px solid rgba(239, 68, 68, 0.3)';
                reviewTag.style.display = 'none';
            } else {
                decisionBox.style.background = '#f1f5f9';
                decisionBox.style.color = '#64748b';
                decisionBox.style.border = '1px solid #cbd5e1';
                reviewTag.style.display = 'none';
            }

            document.getElementById('det_recommendation_text').innerText = recommendation;
            document.getElementById('detailsModal').classList.add('show');
        }

        function closeDetailsModal() {
            document.getElementById('detailsModal').classList.remove('show');
        }

        function filterRequests(status, element) {
            // Update active filter tab style
            document.querySelectorAll('.filter-tab').forEach(tab => tab.classList.remove('active'));
            if (element) {
                element.classList.add('active');
            } else {
                const targetTab = document.querySelector(`.filter-tab[data-filter="${status}"]`);
                if (targetTab) targetTab.classList.add('active');
            }

            // Update stat card active outline
            document.querySelectorAll('.stat-card').forEach(card => card.classList.remove('active-filter'));
            const targetCard = document.querySelector(`.stat-card[data-filter="${status}"]`);
            if (targetCard) targetCard.classList.add('active-filter');

            // Filter table rows
            const rows = document.querySelectorAll('.request-row');
            let visibleCount = 0;

            rows.forEach(row => {
                const rowStatus = row.getAttribute('data-status');
                if (status === 'all' || rowStatus === status) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            // Toggle empty filter row visibility
            const noMatchRow = document.getElementById('noMatchingFilterRow');
            if (noMatchRow) {
                noMatchRow.style.display = (visibleCount === 0 && rows.length > 0) ? '' : 'none';
            }
        }
    </script>
</body>
</html>