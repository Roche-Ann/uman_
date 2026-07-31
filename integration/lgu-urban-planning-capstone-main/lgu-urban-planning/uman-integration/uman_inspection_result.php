<?php
/**
 * Inbound webhook — UMAN posts the grid/electrical load inspection result
 * here once their inspector has been out to the site.
 *
 * Give this URL to the UMAN team:
 *   https://upad.infragovservices.com/api/webhooks/uman_inspection_result.php
 *
 * ⚠️ Expected JSON payload below is a PLACEHOLDER, mirrored off the shape
 * confirmed with IPMS for Roads. None of this has been confirmed with the
 * Energy/Utilities team yet — get their actual modal field names and
 * approve/reject convention before relying on it.
 * {
 *   "application_id": 789,              // echoed back from our request
 *   "grid_id": "EG-2026-014",           // their own generated id
 *   "inspection_date": "2026-07-19",
 *   "engineer_assigned": "Maria Santos",
 *   "grid_capacity_condition": "Good",  // Excellent | Good | Fair | Poor | Critical
 *   "transformer_condition": "Fair",
 *   "line_condition": "Good",
 *   "load_forecast_condition": "Good",
 *   "overall_condition": "Good",        // Excellent | Good | Fair | Poor | Critical
 *   "severity": "Low",                  // placeholder scale — confirm values with UMAN
 *   "recommendation": "No action needed", // placeholder — confirm their dropdown options
 *   "gps_latitude": 14.6760,
 *   "gps_longitude": 121.0437,
 *   "remarks": "Observations for the record...",
 *   "photo_urls": ["https://uman.infragovservices.com/uploads/xyz.jpg"]
 * }
 *
 * Auth: expects an X-UMAN-Signature header containing an HMAC-SHA256 of the
 * raw request body, signed with the shared UMAN_WEBHOOK_SECRET.
 */

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

if (!$applicationId || !$overallCondition) {
    http_response_code(422);
    echo json_encode(['error' => 'Missing application_id or overall_condition']);
    exit;
}

// ⚠️ PLACEHOLDER mapping — mirrored off the Roads/IPMS decision table.
// Confirm this logic with the Energy/Utilities team, or ask them to just
// send an explicit "status" field instead, which would be more reliable.
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
        "INSERT INTO impact_assessments (application_id, energy_flag, energy_notes, checked_at)
         VALUES (?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
            energy_flag  = ?,
            energy_notes = ?,
            checked_at   = NOW()"
    )->execute([$applicationId, $flag, $notes, $flag, $notes]);

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