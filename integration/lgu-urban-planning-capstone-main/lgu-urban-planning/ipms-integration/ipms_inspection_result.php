<?php
/**
 * Road inspection results poller.
 *
 * IPMS has no outbound webhook sender — this is a pull model. WE poll IPMS
 * on a schedule (cron, every 15-30 min) instead of them pushing to us.
 * Run this either:
 *   - via a system cron calling the PHP CLI:
 *       php /path/to/ipms-integration/ipms_inspection_result.php
 *   - or via a cron entry that curls the URL, if the host only supports that:
 *       curl -s https://.../ipms-integration/ipms_inspection_result.php
 *
 * GET {IPMS_BASE_URL}/integrations/urban-planning/inspection-results.php
 * Header: X-API-Key: {URBAN_PLANNING_API_KEY}   (same key as the outbound
 * request in RoadsIntegrationService — no HMAC/signature anywhere)
 *
 * Query params (pass as ?all=1 / ?peek=1 over HTTP, or --all / --peek on
 * the CLI):
 *   (none)  Only results not yet pulled. This is what the recurring cron
 *           run should use — a normal pull marks each row as synced
 *           server-side, so we never get it twice.
 *   all=1   Re-list every completed result ever. Use once for a first
 *           backfill, not on every cron tick.
 *   peek=1  Inspect without marking as consumed. Testing only.
 *
 * Each result is correlated back to our own road_inspection_requests row
 * via external_reference (falling back to road_id) — both of which are
 * just our own application id, echoed back by IPMS. IPMS has no concept of
 * "application_id" and no approve/reject decision for inspections; we
 * derive a needs-review flag ourselves from overall_condition / severity /
 * recommendation (the real enum has no "Immediate Repair" or "Escalate to
 * District Engineer" — those never existed on IPMS's side).
 */

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/roads_integration.php';

if (PHP_SAPI !== 'cli') {
    header('Content-Type: application/json');
}

function ipms_result_flag(string $name): bool
{
    if (isset($_GET[$name])) {
        return $_GET[$name] == '1' || $_GET[$name] === 'true';
    }

    foreach ($GLOBALS['argv'] ?? [] as $arg) {
        if ($arg === "--$name" || $arg === "--$name=1") {
            return true;
        }
    }

    return false;
}

$mode = ipms_result_flag('peek') ? 'peek' : (ipms_result_flag('all') ? 'all' : 'default');

if (URBAN_PLANNING_API_KEY === '' || URBAN_PLANNING_API_KEY === null) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'URBAN_PLANNING_API_KEY is not configured (see ipms-integration/.env.example)']);
    exit(1);
}

$query = [];
if ($mode === 'all') {
    $query['all'] = 1;
} elseif ($mode === 'peek') {
    $query['peek'] = 1;
}

$url = rtrim(IPMS_BASE_URL, '/') . '/integrations/urban-planning/inspection-results.php';
if ($query) {
    $url .= '?' . http_build_query($query);
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPGET        => true,
    CURLOPT_HTTPHEADER     => ['X-API-Key: ' . URBAN_PLANNING_API_KEY],
    CURLOPT_TIMEOUT        => 30,
]);

$responseBody = curl_exec($ch);
$httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError    = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => "cURL error: $curlError"]);
    exit(1);
}

$decoded = json_decode($responseBody ?: '', true);

if ($httpCode !== 200 || empty($decoded['success'])) {
    http_response_code($httpCode ?: 502);
    echo json_encode(['success' => false, 'error' => "IPMS responded with HTTP $httpCode", 'body' => $decoded ?? $responseBody]);
    exit(1);
}

$results = $decoded['results'] ?? [];

$db = Database::getInstance()->getConnection();

$processed = 0;
$skipped   = 0;
$errors    = [];

foreach ($results as $result) {
    $correlationId = $result['external_reference'] ?? $result['road_id'] ?? null;
    $applicationId = is_numeric($correlationId) ? (int) $correlationId : 0;

    $overallCondition = $result['overall_condition'] ?? null; // Excellent|Good|Fair|Poor|Critical

    if ($applicationId <= 0 || !$overallCondition) {
        $skipped++;
        continue;
    }

    $severity             = $result['severity']              ?? null; // low|medium|high|critical
    $recommendation       = $result['recommendation']        ?? null; // one of 6 fixed values
    $remarks              = $result['remarks']                ?? '';
    $inspectionDate       = $result['inspection_date']        ?? null;
    $engineerName         = $result['engineer_name']          ?? null;
    $submittedAt          = $result['submitted_at']           ?? null;
    $roadCondition        = $result['road_condition']         ?? null;
    $surfaceCondition     = $result['surface_condition']      ?? null;
    $drainageCondition    = $result['drainage_condition']     ?? null;
    $sidewalkCondition    = $result['sidewalk_condition']     ?? null;
    $streetlightCondition = $result['streetlight_condition']  ?? null;
    $trafficSignCondition = $result['traffic_sign_condition'] ?? null;
    $photoUrls            = $result['photo_urls']             ?? [];
    $resultId             = $result['id']                     ?? null;

    // Decision is ours to make — IPMS has no approve/reject concept.
    // Poor/Critical condition, high/critical severity, or a recommendation
    // of Road Reconstruction / Further Investigation all flag for review.
    $needsReview = in_array(strtolower((string) $overallCondition), ['poor', 'critical'], true)
        || in_array(strtolower((string) $severity), ['high', 'critical'], true)
        || in_array(strtolower((string) $recommendation), ['road reconstruction', 'further investigation'], true);

    $flag = $needsReview ? 'violation' : 'ok';

    $notes = trim(sprintf(
        "Overall: %s | Severity: %s | Recommendation: %s\n" .
        "Road: %s | Surface: %s | Drainage: %s | Sidewalk: %s | Streetlight: %s | Traffic Signs: %s\n" .
        "Engineer: %s | Inspected: %s | Submitted: %s\n" .
        "Remarks: %s%s",
        $overallCondition, $severity ?? 'N/A', $recommendation ?? 'N/A',
        $roadCondition ?? 'N/A', $surfaceCondition ?? 'N/A', $drainageCondition ?? 'N/A',
        $sidewalkCondition ?? 'N/A', $streetlightCondition ?? 'N/A', $trafficSignCondition ?? 'N/A',
        $engineerName ?? 'N/A', $inspectionDate ?? 'N/A', $submittedAt ?? 'N/A',
        $remarks ?: 'None',
        $photoUrls ? ("\nPhotos: " . count($photoUrls) . ' attached') : ''
    ));

    try {
        $db->beginTransaction();

        // ── Update our tracking record ──────────────────────────────────
        $db->prepare(
            "UPDATE road_inspection_requests
                SET status = 'completed', external_ref_id = ?, response_payload = ?, responded_at = NOW(),
                    overall_condition = ?, severity = ?, recommendation = ?,
                    engineer_assigned = ?, inspection_date = ?
              WHERE application_id = ?
              ORDER BY id DESC LIMIT 1"
        )->execute([
            $resultId !== null ? (string) $resultId : null,
            json_encode($result),
            $overallCondition, $severity, $recommendation,
            $engineerName, $inspectionDate,
            $applicationId,
        ]);

        // ── Update what shows up in the Technical Assessment tab ────────
        $db->prepare(
            "INSERT INTO impact_assessments (application_id, traffic_flag, traffic_notes, checked_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                traffic_flag  = ?,
                traffic_notes = ?,
                checked_at    = NOW()"
        )->execute([$applicationId, $flag, $notes, $flag, $notes]);

        // ── Log it to the application's history / audit trail ───────────
        $currentStatusStmt = $db->prepare("SELECT status FROM applications WHERE id = ?");
        $currentStatusStmt->execute([$applicationId]);
        $currentStatus = $currentStatusStmt->fetchColumn() ?: 'unknown';

        $db->prepare(
            "INSERT INTO application_status_history (application_id, status, remarks, changed_by)
             VALUES (?, ?, ?, NULL)"
        )->execute([
            $applicationId,
            $currentStatus,
            'Road inspection result received from IPMS: ' . ($needsReview ? 'FLAGGED FOR REVIEW' : 'NO ISSUES FLAGGED')
                . " — $notes",
        ]);

        $db->commit();
        $processed++;
    } catch (Exception $e) {
        $db->rollBack();
        $errors[] = "application_id $applicationId: " . $e->getMessage();
    }
}

http_response_code(200);
echo json_encode([
    'success'   => true,
    'mode'      => $mode,
    'fetched'   => count($results),
    'processed' => $processed,
    'skipped'   => $skipped,
    'errors'    => $errors,
]);
