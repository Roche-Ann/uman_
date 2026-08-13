<?php
/**
 * UPAD → UMAN Inspection Request Receiver
 *
 * POST /api/v1/inspection-requests.php
 *   Receives an electrical/grid load inspection request from the Urban
 *   Planning (UPAD) system. Authenticates via Bearer token, persists the
 *   request, and immediately returns a generated reference_id.
 *
 * GET /api/v1/inspection-requests.php
 *   Lists stored inspection requests (admin use).
 *   Auth: same Bearer token.
 *
 * Auth (inbound from UPAD):
 *   Authorization: Bearer <UPAD_API_KEY>
 *   (UPAD sets this in their utilities_integration.php as UMAN_API_KEY)
 *
 * Success response (POST):
 *   HTTP 200 { "success": true, "reference_id": "EG-2026-001" }
 *
 * The result is sent back to UPAD asynchronously via:
 *   POST /api/v1/inspection-callback.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../integration_config.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ── Auth ─────────────────────────────────────────────────────────────────────
uman_require_bearer(UPAD_API_KEY);

$pdo = uman_integration_pdo();

// ════════════════════════════════════════════════════════════════════════════
// GET — list inspection requests (admin/debug)
// ════════════════════════════════════════════════════════════════════════════
if ($method === 'GET') {
    $status = $_GET['status'] ?? null;
    $limit  = min((int) ($_GET['limit'] ?? 50), 200);

    $sql    = 'SELECT id, reference_id, application_id, source_system, project_name,
                      barangay, district, category, priority, status,
                      requested_by, callback_sent_at, created_at
               FROM upad_inspection_requests';
    $params = [];

    if ($status) {
        $sql   .= ' WHERE status = ?';
        $params[] = $status;
    }

    $sql .= ' ORDER BY created_at DESC LIMIT ' . $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'count'   => count($rows),
        'data'    => $rows,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ════════════════════════════════════════════════════════════════════════════
// POST — receive new inspection request from UPAD
// ════════════════════════════════════════════════════════════════════════════
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$rawBody = file_get_contents('php://input');
$data    = json_decode($rawBody ?: '{}', true);

if (!is_array($data) || empty($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid or empty JSON payload']);
    exit;
}

// ── Validate required fields ──────────────────────────────────────────────
$required = ['application_id', 'source_system'];
$missing  = [];
foreach ($required as $f) {
    if (empty($data[$f])) {
        $missing[] = $f;
    }
}
if (!empty($missing)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error'   => 'Missing required fields: ' . implode(', ', $missing),
    ]);
    exit;
}

// ── Extract fields ────────────────────────────────────────────────────────
$applicationId    = (int) $data['application_id'];
$sourceSystem     = mb_substr(trim($data['source_system'] ?? 'UPAD'), 0, 20);
$projectName      = mb_substr(trim($data['project_name']  ?? ''), 0, 255) ?: null;
$barangay         = mb_substr(trim($data['barangay']       ?? ''), 0, 100) ?: null;
$district         = mb_substr(trim($data['district']       ?? ''), 0, 50)  ?: null;
$category         = mb_substr(trim($data['category']       ?? ''), 0, 80)  ?: null;
$estimatedLoadKva = isset($data['estimated_load_kva']) && $data['estimated_load_kva'] !== ''
                    ? (float) $data['estimated_load_kva'] : null;

// Normalise priority
$rawPriority = strtolower(trim($data['priority'] ?? 'medium'));
$priority = match(true) {
    str_starts_with($rawPriority, 'u') => 'Urgent',
    str_starts_with($rawPriority, 'l') => 'Low',
    default                            => 'Medium',
};

// Map location
$mapLocation  = is_array($data['map_location'] ?? null) ? $data['map_location'] : [];
$address      = mb_substr(trim($data['address'] ?? $mapLocation['address'] ?? ''), 0, 500) ?: null;
$latitude     = isset($mapLocation['latitude'])  && $mapLocation['latitude']  !== '' ? (float) $mapLocation['latitude']  : null;
$longitude    = isset($mapLocation['longitude']) && $mapLocation['longitude'] !== '' ? (float) $mapLocation['longitude'] : null;
$description  = mb_substr(trim($data['description']  ?? ''), 0, 2000) ?: null;
$requestedBy  = mb_substr(trim($data['requested_by'] ?? 'Urban Planning Office'), 0, 150);
$callbackUrl  = mb_substr(trim($data['callback_url'] ?? ''), 0, 500) ?: null;

if ($applicationId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'application_id must be a positive integer']);
    exit;
}

try {
    // ── Generate unique reference ID ─────────────────────────────────────
    // Format: EG-YYYY-NNN (EG = Electrical/Grid)
    $year   = date('Y');
    $seqRow = $pdo->query(
        "SELECT COUNT(*) AS c FROM upad_inspection_requests WHERE YEAR(created_at) = $year"
    )->fetch(PDO::FETCH_ASSOC);
    $seq         = ((int) ($seqRow['c'] ?? 0)) + 1;
    $referenceId = sprintf('EG-%s-%03d', $year, $seq);

    // ── Insert request record ─────────────────────────────────────────────
    $stmt = $pdo->prepare("
        INSERT INTO upad_inspection_requests
            (reference_id, application_id, source_system, project_name,
             barangay, district, category, estimated_load_kva, priority,
             address, latitude, longitude, description, requested_by,
             callback_url, status, raw_payload, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
    ");
    $stmt->execute([
        $referenceId,
        $applicationId,
        $sourceSystem,
        $projectName,
        $barangay,
        $district,
        $category,
        $estimatedLoadKva,
        $priority,
        $address,
        $latitude,
        $longitude,
        $description,
        $requestedBy,
        $callbackUrl,
        $rawBody,
    ]);

    // ── Run AI Auto-Verification and Auto-Approval ───────────────────────────
    $aiStatusMessage = 'AI auto-approval deferred.';
    try {
        require_once __DIR__ . '/inspection_ai.php';
        $aiResult = runInspectionAIValidation($referenceId, $pdo);
        if ($aiResult['success'] && $aiResult['approved']) {
            $aiStatusMessage = 'AI Auto-Approved: ' . $aiResult['message'];
        } else {
            $aiStatusMessage = 'AI Deferred: ' . ($aiResult['message'] ?? 'Pending manual survey.');
        }
    } catch (Throwable $e) {
        $aiStatusMessage = 'AI Engine offline: ' . $e->getMessage();
    }

    // ── Log the inbound request ───────────────────────────────────────────
    try {
        $pdo->prepare("
            INSERT INTO planning_coordination_logs (direction, log_type, details)
            VALUES ('Inbound', 'UPAD Inspection Request', ?)
        ")->execute([
            "Received inspection request from UPAD. App ID: $applicationId | Ref: $referenceId | Priority: $priority | Location: $address | $aiStatusMessage"
        ]);
    } catch (Throwable) {
        // planning_coordination_logs is optional — don't fail if it doesn't exist
    }

    // ── Respond to UPAD ───────────────────────────────────────────────────
    http_response_code(200);
    echo json_encode([
        'success'      => true,
        'reference_id' => $referenceId,
        'message'      => 'Inspection request received. UMAN AI Engine processed details: ' . $aiStatusMessage,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('[UPAD Integration] inspection-requests.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error processing inspection request']);
}
