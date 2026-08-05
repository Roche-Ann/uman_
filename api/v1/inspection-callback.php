<?php
/**
 * UMAN → UPAD Inspection Result Callback
 *
 * POST /api/v1/inspection-callback.php
 *   Called by UMAN admin/staff once an inspection has been completed.
 *   Sends the inspection result back to the UPAD system's callback URL,
 *   signed with HMAC-SHA256 (X-UMAN-Signature header).
 *
 * Auth (this endpoint — admin only):
 *   Authorization: Bearer <UPAD_API_KEY>
 *
 * Request body (JSON):
 * {
 *   "reference_id":             "EG-2026-001",    // required — our reference
 *   "inspection_date":          "2026-07-30",      // required
 *   "engineer_assigned":        "Juan Dela Cruz",  // required
 *   "grid_capacity_condition":  "Good",            // required  Excellent|Good|Fair|Poor|Critical
 *   "transformer_condition":    "Good",            // required
 *   "line_condition":           "Good",            // required
 *   "load_forecast_condition":  "Good",            // required
 *   "overall_condition":        "Good",            // required — drives UPAD approve/reject
 *   "severity":                 "Low",             // required  Low|Medium|High
 *   "recommendation":           "No action needed",// required
 *   "gps_latitude":             14.6760,           // optional
 *   "gps_longitude":            121.0437,          // optional
 *   "remarks":                  "All clear.",      // optional
 *   "photo_urls":               []                 // optional array of URL strings
 * }
 *
 * UPAD webhook expects the payload to be signed via:
 *   X-UMAN-Signature: hmac_sha256(raw_body, UPAD_WEBHOOK_SECRET)
 */

declare(strict_types=1);

require_once __DIR__ . '/../integration_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// ── Auth ─────────────────────────────────────────────────────────────────────
uman_require_bearer(UPAD_API_KEY);

$pdo     = uman_integration_pdo();
$rawBody = file_get_contents('php://input');
$input   = json_decode($rawBody ?: '{}', true);

if (!is_array($input) || empty($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid or empty JSON payload']);
    exit;
}

// ── Validate required fields ──────────────────────────────────────────────────
$requiredFields = [
    'reference_id', 'inspection_date', 'engineer_assigned',
    'grid_capacity_condition', 'transformer_condition',
    'line_condition', 'load_forecast_condition',
    'overall_condition', 'severity', 'recommendation',
];
$missing = [];
foreach ($requiredFields as $f) {
    if (empty($input[$f])) {
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

// ── Allowed values validation ─────────────────────────────────────────────────
$conditionScale = ['Excellent', 'Good', 'Fair', 'Poor', 'Critical'];
$conditionFields = [
    'grid_capacity_condition', 'transformer_condition',
    'line_condition', 'load_forecast_condition', 'overall_condition',
];
foreach ($conditionFields as $cf) {
    if (!in_array($input[$cf], $conditionScale, true)) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error'   => "$cf must be one of: " . implode(', ', $conditionScale),
        ]);
        exit;
    }
}

$validSeverities = ['Low', 'Medium', 'High'];
if (!in_array($input['severity'], $validSeverities, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'severity must be: Low, Medium, or High']);
    exit;
}

// ── Load the stored request record ───────────────────────────────────────────
$referenceId = trim($input['reference_id']);
$stmt        = $pdo->prepare('SELECT * FROM upad_inspection_requests WHERE reference_id = ?');
$stmt->execute([$referenceId]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => "No request found with reference_id: $referenceId"]);
    exit;
}

if ($request['status'] === 'completed') {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'Callback already sent for this inspection request']);
    exit;
}

// ── Build the result payload to send to UPAD ────────────────────────────────
$callbackPayload = [
    'application_id'           => (int) $request['application_id'],
    'grid_id'                  => $referenceId,
    'inspection_date'          => trim($input['inspection_date']),
    'engineer_assigned'        => mb_substr(trim($input['engineer_assigned']), 0, 150),
    'grid_capacity_condition'  => $input['grid_capacity_condition'],
    'transformer_condition'    => $input['transformer_condition'],
    'line_condition'           => $input['line_condition'],
    'load_forecast_condition'  => $input['load_forecast_condition'],
    'overall_condition'        => $input['overall_condition'],
    'severity'                 => $input['severity'],
    'recommendation'           => mb_substr(trim($input['recommendation']), 0, 300),
    'gps_latitude'             => isset($input['gps_latitude'])  ? (float) $input['gps_latitude']  : null,
    'gps_longitude'            => isset($input['gps_longitude']) ? (float) $input['gps_longitude'] : null,
    'remarks'                  => mb_substr(trim($input['remarks'] ?? ''), 0, 2000),
    'photo_urls'               => is_array($input['photo_urls'] ?? null) ? $input['photo_urls'] : [],
];

$callbackJson = json_encode($callbackPayload, JSON_UNESCAPED_UNICODE);

// ── Determine callback URL ────────────────────────────────────────────────────
// Use stored URL from the original request, fall back to the known UPAD endpoint.
$callbackUrl = !empty($request['callback_url'])
    ? $request['callback_url']
    : UPAD_DEFAULT_CALLBACK_URL;

// ── Sign the payload ──────────────────────────────────────────────────────────
$signature = hash_hmac('sha256', $callbackJson, UPAD_WEBHOOK_SECRET);

// ── POST result to UPAD ───────────────────────────────────────────────────────
$ch = curl_init($callbackUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $callbackJson,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'X-UMAN-Signature: ' . $signature,
    ],
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$responseBody = curl_exec($ch);
$httpCode     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError    = curl_error($ch);
curl_close($ch);

$callbackSucceeded = !$curlError && $httpCode >= 200 && $httpCode < 300;
$callbackError     = $curlError ?: ($callbackSucceeded ? null : "UPAD responded HTTP $httpCode: " . mb_substr(strip_tags($responseBody ?: ''), 0, 200));

// ── Persist result & update status ───────────────────────────────────────────
$pdo->prepare("
    UPDATE upad_inspection_requests
    SET status             = ?,
        result_payload     = ?,
        callback_sent_at   = NOW(),
        callback_http_code = ?,
        callback_error     = ?
    WHERE reference_id = ?
")->execute([
    $callbackSucceeded ? 'completed' : 'failed',
    json_encode([
        'sent_payload'  => $callbackPayload,
        'http_code'     => $httpCode,
        'response_body' => mb_substr($responseBody ?: '', 0, 2000),
    ]),
    $httpCode ?: null,
    $callbackError,
    $referenceId,
]);

// ── Log outbound call ─────────────────────────────────────────────────────────
try {
    $pdo->prepare("
        INSERT INTO planning_coordination_logs (direction, log_type, details)
        VALUES ('Outbound', 'UPAD Inspection Callback', ?)
    ")->execute([
        sprintf(
            'Sent inspection result to UPAD. Ref: %s | App ID: %d | Overall: %s | HTTP: %d | %s',
            $referenceId,
            $request['application_id'],
            $callbackPayload['overall_condition'],
            $httpCode,
            $callbackSucceeded ? 'SUCCESS' : ('FAILED: ' . $callbackError)
        )
    ]);
} catch (Throwable) {
    // non-critical
}

// ── Respond ───────────────────────────────────────────────────────────────────
if ($callbackSucceeded) {
    http_response_code(200);
    echo json_encode([
        'success'        => true,
        'reference_id'   => $referenceId,
        'application_id' => (int) $request['application_id'],
        'callback_url'   => $callbackUrl,
        'http_code'      => $httpCode,
        'message'        => 'Inspection result delivered to Urban Planning system.',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    http_response_code(502);
    echo json_encode([
        'success'        => false,
        'reference_id'   => $referenceId,
        'callback_url'   => $callbackUrl,
        'http_code'      => $httpCode,
        'error'          => $callbackError,
        'message'        => 'Result stored locally but delivery to UPAD failed. Retry via this endpoint.',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
