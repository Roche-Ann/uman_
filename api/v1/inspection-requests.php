<?php
/**
 * UMAN – Urban Planning Inspection Requests API
 * 
 * POST /api/v1/inspection-requests.php
 * 
 * Production-ready server-to-server integration endpoint for Urban Planning (UPAD)
 * to evaluate inspection requests using AI scoring, determine decisions, 
 * prevent duplicate processing, and audit transactions.
 * 
 * Authentication: Authorization: Bearer <integration-key>
 * Content-Type: application/json
 */

declare(strict_types=1);

$startTime = microtime(true);

require_once __DIR__ . '/../integration_config.php';

header('Content-Type: application/json; charset=utf-8');

// ── 1. HTTP Method Validation ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Allow GET only for health/admin verification
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        uman_require_bearer(UPAD_API_KEY);
        $pdo = uman_integration_pdo();
        $stmt = $pdo->query("SELECT * FROM inspection_ai_logs ORDER BY created_at DESC LIMIT 20");
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Inspection Requests API active',
            'count'   => count($logs),
            'data'    => $logs
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

// ── 2. Bearer Token Authentication ───────────────────────────────────────────
// Expected key configured via UPAD_INTEGRATION_KEY or UPAD_API_KEY
$expectedToken = trim((string)(getenv('UPAD_INTEGRATION_KEY') ?: getenv('UPAD_API_KEY') ?: UPAD_API_KEY));
uman_require_bearer($expectedToken);

// ── 3. Parse JSON Body ───────────────────────────────────────────────────────
$rawBody = file_get_contents('php://input');
$input   = json_decode($rawBody ?: '{}', true);

if (!is_array($input) || empty($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON payload']);
    exit;
}

try {
    $pdo = uman_integration_pdo();

    // Self-healing: Ensure inspection_ai_logs schema exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `inspection_ai_logs` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `request_id` VARCHAR(100) NOT NULL,
          `inspection_id` VARCHAR(100) NOT NULL,
          `location` VARCHAR(255) NULL,
          `utility_type` VARCHAR(80) NOT NULL DEFAULT 'Electrical',
          `source_system` VARCHAR(100) NOT NULL DEFAULT 'Urban Planning',
          `coverage_value` VARCHAR(50) NOT NULL,
          `coverage_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
          `asset_health` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
          `asset_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
          `capacity_value` VARCHAR(50) NOT NULL,
          `capacity_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
          `incident_count` INT NOT NULL DEFAULT 0,
          `incident_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
          `weights_applied` JSON NOT NULL,
          `final_ai_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
          `ai_decision` ENUM('Approved', 'Conditional', 'Rejected') NOT NULL,
          `factors_breakdown` JSON NULL,
          `processing_time_ms` DECIMAL(8,2) NULL,
          `response_status` SMALLINT NOT NULL DEFAULT 200,
          `callback_attempted` TINYINT(1) NOT NULL DEFAULT 0,
          `callback_url` TEXT NULL,
          `callback_http_code` SMALLINT NULL,
          `callback_error` TEXT NULL,
          `is_overridden` TINYINT(1) NOT NULL DEFAULT 0,
          `override_decision` ENUM('Approved', 'Conditional', 'Rejected') NULL,
          `override_reason` TEXT NULL,
          `overridden_by` VARCHAR(100) NULL,
          `overridden_at` DATETIME NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          INDEX `idx_inspection_id` (`inspection_id`),
          INDEX `idx_request_id` (`request_id`),
          INDEX `idx_ai_decision` (`ai_decision`),
          INDEX `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // ── 4. Input Validation ─────────────────────────────────────────────────
    
    // Support either inspection_id or legacy application_id/request_id
    $inspectionId = trim((string)($input['inspection_id'] ?? $input['request_id'] ?? ($input['application_id'] ?? '')));
    if ($inspectionId === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required field: inspection_id']);
        exit;
    }

    // 4.1 Check Idempotency / Duplicate Inspection ID
    $dupStmt = $pdo->prepare("
        SELECT inspection_id, final_ai_score, ai_decision, factors_breakdown, created_at 
        FROM inspection_ai_logs 
        WHERE inspection_id = ? 
        ORDER BY id DESC LIMIT 1
    ");
    $dupStmt->execute([$inspectionId]);
    $existing = $dupStmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $factors = json_decode($existing['factors_breakdown'] ?: '{}', true);
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Inspection request already processed',
            'data'    => [
                'inspection_id' => $existing['inspection_id'],
                'score'         => (int)round((float)$existing['final_ai_score']),
                'decision'      => $existing['ai_decision'],
                'factors'       => [
                    'coverage'     => $factors['coverage']['score'] ?? 100,
                    'asset_health' => $factors['asset_health']['score'] ?? 90,
                    'capacity'     => $factors['capacity']['score'] ?? 100,
                    'incidents'    => $factors['incidents']['score'] ?? 100,
                ],
                'processed_at'  => date('c', strtotime($existing['created_at']))
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // 4.2 Validate coverage
    if (!isset($input['coverage'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required field: coverage']);
        exit;
    }
    $coverageRaw = trim((string)$input['coverage']);
    $validCoverages = ['Fully Covered', 'Partially Covered', 'Not Covered'];
    if (!in_array($coverageRaw, $validCoverages, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid coverage. Allowed values: Fully Covered, Partially Covered, Not Covered']);
        exit;
    }
    $coverageScore = match ($coverageRaw) {
        'Fully Covered'     => 100.0,
        'Partially Covered' => 50.0,
        'Not Covered'       => 0.0,
    };

    // 4.3 Validate asset_health
    if (!isset($input['asset_health'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required field: asset_health']);
        exit;
    }
    if (!is_numeric($input['asset_health'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid asset_health. Value must be numeric.']);
        exit;
    }
    $assetHealth = (float)$input['asset_health'];
    if ($assetHealth < 0.0 || $assetHealth > 100.0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid asset_health. Value must be between 0 and 100.']);
        exit;
    }
    $assetHealthScore = $assetHealth;

    // 4.4 Validate capacity
    if (!isset($input['capacity'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required field: capacity']);
        exit;
    }
    $capacityRaw = trim((string)$input['capacity']);
    $validCapacities = ['Normal', 'Near Capacity', 'Overloaded'];
    if (!in_array($capacityRaw, $validCapacities, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid capacity. Allowed values: Normal, Near Capacity, Overloaded']);
        exit;
    }
    $capacityScore = match ($capacityRaw) {
        'Normal'        => 100.0,
        'Near Capacity' => 60.0,
        'Overloaded'    => 20.0,
    };

    // 4.5 Validate incident_count
    if (!isset($input['incident_count'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required field: incident_count']);
        exit;
    }
    if (!is_numeric($input['incident_count']) || (int)$input['incident_count'] < 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid incident_count. Must be an integer >= 0.']);
        exit;
    }
    $incidentCount = (int)$input['incident_count'];
    if ($incidentCount === 0) {
        $incidentScore = 100.0;
    } elseif ($incidentCount <= 2) {
        $incidentScore = 70.0;
    } else {
        $incidentScore = 30.0;
    }

    // ── 5. Weighted Score Calculation ───────────────────────────────────────
    // Weights: Coverage = 30%, Asset Health = 30%, Capacity = 20%, Incidents = 20%
    $rawScore = (
        ($coverageScore    * 0.30) +
        ($assetHealthScore * 0.30) +
        ($capacityScore    * 0.20) +
        ($incidentScore    * 0.20)
    );
    $finalScore = max(0, min(100, (int)round($rawScore)));

    // ── 6. Decision Classification ──────────────────────────────────────────
    if ($finalScore >= 80) {
        $decision = 'Approved';
    } elseif ($finalScore >= 50) {
        $decision = 'Conditional';
    } else {
        $decision = 'Rejected';
    }

    $processedAt = date('c');
    $factorsBreakdown = [
        'coverage' => [
            'value'  => $coverageRaw,
            'score'  => (int)$coverageScore,
            'weight' => 0.30
        ],
        'asset_health' => [
            'value'  => $assetHealth,
            'score'  => (int)$assetHealthScore,
            'weight' => 0.30
        ],
        'capacity' => [
            'value'  => $capacityRaw,
            'score'  => (int)$capacityScore,
            'weight' => 0.20
        ],
        'incidents' => [
            'value'  => $incidentCount,
            'score'  => (int)$incidentScore,
            'weight' => 0.20
        ]
    ];

    $weightsSnapshot = [
        'coverage'     => 30.0,
        'asset_health' => 30.0,
        'capacity'     => 20.0,
        'incidents'    => 20.0
    ];

    $processingTimeMs = round((microtime(true) - $startTime) * 1000, 2);

    // ── 7. Audit & Database Logging ─────────────────────────────────────────
    $logStmt = $pdo->prepare("
        INSERT INTO inspection_ai_logs 
            (request_id, inspection_id, location, utility_type, source_system,
             coverage_value, coverage_score, asset_health, asset_score,
             capacity_value, capacity_score, incident_count, incident_score,
             weights_applied, final_ai_score, ai_decision, factors_breakdown,
             processing_time_ms, response_status, created_at)
        VALUES (?, ?, ?, 'Electrical', 'Urban Planning',
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, 200, NOW())
    ");

    $locationInfo = trim((string)($input['location'] ?? $input['project_name'] ?? $input['barangay'] ?? 'Urban Planning Zone'));
    $logStmt->execute([
        $inspectionId,
        $inspectionId,
        $locationInfo,
        $coverageRaw,
        $coverageScore,
        $assetHealth,
        $assetHealthScore,
        $capacityRaw,
        $capacityScore,
        $incidentCount,
        $incidentScore,
        json_encode($weightsSnapshot),
        $finalScore,
        $decision,
        json_encode($factorsBreakdown),
        $processingTimeMs
    ]);

    // Optional mirror in upad_inspection_requests
    try {
        $year   = date('Y');
        $seqRow = $pdo->query("SELECT COUNT(*) AS c FROM upad_inspection_requests WHERE YEAR(created_at) = $year")->fetch(PDO::FETCH_ASSOC);
        $seq    = ((int)($seqRow['c'] ?? 0)) + 1;
        $refId  = sprintf('EG-%s-%03d', $year, $seq);

        $pdo->prepare("
            INSERT INTO upad_inspection_requests 
                (reference_id, application_id, source_system, project_name, barangay, status, ai_score, ai_decision, raw_payload, created_at)
            VALUES (?, ?, 'UPAD', ?, ?, 'completed', ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                status = 'completed', ai_score = VALUES(ai_score), ai_decision = VALUES(ai_decision), updated_at = NOW()
        ")->execute([
            $refId,
            (int)($input['application_id'] ?? preg_replace('/\D/', '', $inspectionId) ?: $seq),
            $locationInfo,
            $input['barangay'] ?? $locationInfo,
            $finalScore,
            $decision,
            $rawBody
        ]);
    } catch (Throwable) {}

    // ── 8. Asynchronous / Non-blocking Callback Attempt ─────────────────────
    $callbackUrl = trim((string)($input['callback_url'] ?? ''));
    if (!empty($callbackUrl)) {
        try {
            $callbackPayload = [
                'inspection_id' => $inspectionId,
                'score'         => $finalScore,
                'decision'      => $decision,
                'factors'       => [
                    'coverage'     => (int)$coverageScore,
                    'asset_health' => (int)$assetHealthScore,
                    'capacity'     => (int)$capacityScore,
                    'incidents'    => (int)$incidentScore,
                ],
                'processed_at'  => $processedAt
            ];
            $cbJson = json_encode($callbackPayload, JSON_UNESCAPED_UNICODE);
            $sig    = hash_hmac('sha256', $cbJson, UPAD_WEBHOOK_SECRET);

            $ch = curl_init($callbackUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $cbJson,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'X-UMAN-Signature: ' . $sig,
                ],
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $cbResponse = curl_exec($ch);
            $cbHttpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $cbErr      = curl_error($ch);

            $pdo->prepare("
                UPDATE inspection_ai_logs 
                SET callback_attempted = 1, callback_url = ?, callback_http_code = ?, callback_error = ?
                WHERE inspection_id = ?
            ")->execute([
                $callbackUrl,
                $cbHttpCode ?: null,
                $cbErr ?: ($cbHttpCode >= 200 && $cbHttpCode < 300 ? null : "HTTP $cbHttpCode"),
                $inspectionId
            ]);
        } catch (Throwable) {}
    }

    // ── 9. Return JSON Response ─────────────────────────────────────────────
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Inspection request processed successfully',
        'data'    => [
            'inspection_id' => $inspectionId,
            'score'         => $finalScore,
            'decision'      => $decision,
            'factors'       => [
                'coverage'     => (int)$coverageScore,
                'asset_health' => (int)$assetHealthScore,
                'capacity'     => (int)$capacityScore,
                'incidents'    => (int)$incidentScore,
            ],
            'processed_at'  => $processedAt
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;

} catch (Throwable $e) {
    error_log('[UMAN API Error] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Unable to process inspection request'
    ]);
    exit;
}
