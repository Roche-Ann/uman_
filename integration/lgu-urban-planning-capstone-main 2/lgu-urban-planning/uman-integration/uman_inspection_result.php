<?php

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/utilities_integration.php';

header('Content-Type: application/json');

// ── 1. Verify the request really came from UMAN ──────────────────────────────
$signature = $_SERVER['HTTP_X_UMAN_SIGNATURE'] ?? '';
$rawBody   = file_get_contents('php://input');

$expectedSignature = hash_hmac('sha256', $rawBody, UMAN_WEBHOOK_SECRET);
if (!$signature || !hash_equals($expectedSignature, $signature)) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

$data = json_decode($rawBody, true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload']);
    exit;
}

$applicationId    = (int) ($data['application_id'] ?? 0);
$externalRefId    = $data['grid_id']              ?? null;
$overallCondition = $data['overall_condition']    ?? null;  // Excellent|Good|Fair|Poor|Critical
$severity         = $data['severity']             ?? null;  // ⚠️ scale not yet confirmed with UMAN
$recommendation   = $data['recommendation']        ?? null;  // ⚠️ dropdown options not yet confirmed
$remarks          = $data['remarks']              ?? '';
$inspectedAt      = $data['inspection_date']       ?? null;
$engineerAssigned = $data['engineer_assigned']     ?? null;

// ── Photos ───────────────────────────────────────────────────────────────
// ⚠️ Same caveat as the IPMS webhook: the exact key UMAN uses for photos
// hasn't been confirmed with the Energy/Utilities team yet. Checking a few
// plausible names so photos aren't silently dropped if it's a common
// alternate. Confirm the real key and trim this down once you have it.
$rawPhotos = $data['photos']
    ?? $data['photo_urls']
    ?? $data['images']
    ?? $data['attachments']
    ?? [];

$photoUrls = [];
if (is_array($rawPhotos)) {
    foreach ($rawPhotos as $photo) {
        if (is_string($photo)) {
            $photoUrls[] = $photo;
        } elseif (is_array($photo) && !empty($photo['url'])) {
            $photoUrls[] = $photo['url'];
        }
    }
}
$photosJson = $photoUrls ? json_encode($photoUrls) : null;

if (!$applicationId || !$overallCondition) {
    http_response_code(422);
    echo json_encode(['error' => 'Missing application_id or overall_condition']);
    exit;
}

$needsAction = in_array($overallCondition, ['Poor', 'Critical'], true)
    || in_array($recommendation, ['Immediate Upgrade', 'Escalate to District Engineer'], true);
$status = $needsAction ? 'rejected' : 'approved';

$notes = trim(sprintf(
    "Overall: %s | Severity: %s | Recommendation: %s\nEngineer: %s | Inspected: %s\nRemarks: %s",
    $overallCondition,
    $severity ?? 'N/A',
    $recommendation ?? 'N/A',
    $engineerAssigned ?? 'N/A',
    $inspectedAt ?? 'N/A',
    $remarks
));

$db = Database::getInstance()->getConnection();

try {
    $db->beginTransaction();

    // ── 2. Update our tracking record ───────────────────────────────────────
    $db->prepare(
        "UPDATE energy_inspection_requests
            SET status = 'completed', external_ref_id = ?, response_payload = ?, responded_at = NOW(),
                overall_condition = ?, severity = ?, recommendation = ?,
                engineer_assigned = ?, inspection_date = ?
          WHERE application_id = ? AND status IN ('sent', 'pending')
          ORDER BY id DESC LIMIT 1"
    )->execute([
        $externalRefId, $rawBody,
        $overallCondition, $severity, $recommendation,
        $engineerAssigned, $inspectedAt,
        $applicationId,
    ]);

    // ── 3. Update what shows up in the Technical Assessment tab ─────────────
    $flag = $status === 'approved' ? 'ok' : 'violation';
    $db->prepare(
        "INSERT INTO impact_assessments (application_id, energy_flag, energy_notes, energy_photos, checked_at)
         VALUES (?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
            energy_flag   = ?,
            energy_notes  = ?,
            energy_photos = ?,
            checked_at    = NOW()"
    )->execute([$applicationId, $flag, $notes, $photosJson, $flag, $notes, $photosJson]);

    // ── 4. Log it to the application's history / audit trail ────────────────
    $currentStatusStmt = $db->prepare("SELECT status FROM applications WHERE id = ?");
    $currentStatusStmt->execute([$applicationId]);
    $currentStatus = $currentStatusStmt->fetchColumn() ?: 'unknown';

    $db->prepare(
        "INSERT INTO application_status_history (application_id, status, remarks, changed_by)
         VALUES (?, ?, ?, 0)"
    )->execute([
        $applicationId,
        $currentStatus,
        'Utilities/grid inspection result received from UMAN: ' . strtoupper($status)
            . ($notes ? " — $notes" : ''),
    ]);

    $db->commit();

    http_response_code(200);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}