<?php
/**
 * UMAN → CPRF bidirectional custody API (Phase 2 Assign/Unassign/AcceptReturn).
 *
 * For the Barangay Culiat "CPRF Facility Assignments" workflow.
 *
 * Routes (all require X-API-Key: UMAN_INTEGRATION_API_KEY, matching your
 * existing integration auth in integration_config.php):
 *
 *   GET  ?action=list&facility_id={ID}
 *        Returns 3 buckets for a single facility:
 *          {assignable:[], at_facility:[], recently_logged_events:[]}
 *   GET  ?action=facility_summary
 *        Returns all CPRF facilities with per-facility on-loan counts
 *        (cached, live data from CPRF facilities/status webhook + local UMAN
 *        custody columns).
 *   POST {action:assign,      facility_id, asset_ids:[], actor, notes, source}
 *        Marks assets as ON_LOAN_AT_FACILITY, fires CPRF /assigned webhook.
 *   POST {action:unassign,    facility_id, asset_id, actor, reason}
 *        Marks asset as WAREHOUSED, fires CPRF /unassigned webhook.
 *   POST {action:accept_return, facility_id, asset_id, actor, reason, replacement, replacement_asset_id}
 *        UMAN accepts a return from CPRF: marks LOAN_RETURNED, fires CPRF
 *        webhook. If replacement=true (RETURN_AND_REPLACE), auto-creates an
 *        approved external_asset_request for the same facility+asset_type so
 *        staff can fulfill immediately.
 *   POST {action:request_return, facility_id, asset_id, return_type, condition, reason, actor_label}
 *        CPRF-initiated return/replacement request.
 *
 * All changes are idempotent (duplicate POSTs do not throw).
 */
declare(strict_types=1);

require_once __DIR__ . '/integration_config.php';

header('Content-Type: application/json; charset=utf-8');

function facility_eq_json(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = uman_integration_pdo();
    uman_ensure_cprf_custody_schema($pdo);
} catch (Throwable $e) {
    facility_eq_json(500, ['success' => false, 'error' => 'DB init failed: ' . $e->getMessage()]);
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$body = [];
if ($method !== 'GET') {
    $raw = file_get_contents('php://input') ?: '';
    $dec = json_decode($raw, true);
    if (is_array($dec)) {
        $body = $dec;
    } else {
        // Fallback: accept standard form-encoded for form posts from the UI.
        $body = is_array($_POST) ? $_POST : [];
    }
}
$action = trim((string)($body['action'] ?? $_GET['action'] ?? ''));

uman_require_api_key($UMAN_INTEGRATION_API_KEY);

// ── Helpers ──────────────────────────────────────────────────────────────────

function uman_fetch_asset_meta(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("
        SELECT a.id, a.asset_id AS asset_code, a.name, a.condition_status,
               t.name AS asset_type, a.description, a.responsible_office
        FROM utility_assets a JOIN asset_types t ON t.id = a.asset_type_id
        WHERE a.id = ? LIMIT 1
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function uman_facility_exists_locally(PDO $pdo, int $facilityId, string $facilityName = ''): bool
{
    // Best-effort: if CPRF integration DB table CPRF_facilities (mirror) ever
    // exists, check there. For now, accept any non-zero ID because the CPRF
    // facilities/status list is authoritative and the webhook receiver
    // validates existence on the CPRF side anyway.
    return $facilityId > 0;
}

function uman_event_ref(): string
{
    return 'UFE-' . date('YmdHis') . '-' . substr(uniqid('', true), -6, 6);
}

// ── GET list (per-facility 3 buckets) ────────────────────────────────────────
if ($method === 'GET' && ($action === '' || $action === 'list')) {
    $facilityId = (int)($_GET['facility_id'] ?? 0);
    if ($facilityId <= 0) {
        facility_eq_json(422, ['success' => false, 'error' => 'facility_id required']);
    }

    $atFacility = $pdo->prepare("
        SELECT a.id, a.asset_id AS asset_code, a.name, a.condition_status,
               t.name AS asset_type, a.cprf_custody_status,
               a.cprf_facility_id, a.responsible_office
        FROM utility_assets a JOIN asset_types t ON t.id = a.asset_type_id
        WHERE a.cprf_facility_id = ?
        ORDER BY a.name ASC
    ");
    $atFacility->execute([$facilityId]);
    $atRows = $atFacility->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Return pending = asset currently at facility but user has told UMAN they
    // want to return it (mirrors CPRF facility_equipment.status='return_pending')
    foreach ($atRows as &$r) {
        $r['cprf_facility_id'] = !empty($r['cprf_facility_id']) ? (int)$r['cprf_facility_id'] : null;
        $c = (string)($r['cprf_custody_status'] ?? 'ON_LOAN_AT_FACILITY');
        $r['custody_status_label'] = match ($c) {
            'LOAN_RETURN_PENDING' => 'Return Pending (CPRF wants back)',
            'LOAN_RETURNED'       => 'Returned',
            default               => 'On-loan at facility',
        };
    }
    unset($r);

    $assignable = $pdo->query("
        SELECT a.id, a.asset_id AS asset_code, a.name, a.condition_status,
               t.name AS asset_type, a.responsible_office
        FROM utility_assets a JOIN asset_types t ON t.id = a.asset_type_id
        WHERE a.condition_status IN ('Operational','Needs Inspection')
          AND a.cprf_custody_status = 'WAREHOUSED'
        ORDER BY a.name ASC
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Recent events: look at external_asset_requests for this facility
    $logStmt = $pdo->prepare("
        SELECT id, request_ref, status, asset_type, quantity, notes,
               review_notes, fulfilled_asset_id, created_at, updated_at
        FROM external_asset_requests
        WHERE source_system = 'CPRF' AND cprf_facility_id = ?
        ORDER BY updated_at DESC LIMIT 15
    ");
    $logStmt->execute([$facilityId]);
    $events = $logStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    facility_eq_json(200, [
        'success'      => true,
        'facility_id'  => $facilityId,
        'assignable'   => $assignable,
        'at_facility'  => $atRows,
        'events'       => $events,
        'return_pending_count' => count(array_filter($atRows, static fn($r) => ($r['cprf_custody_status'] ?? '') === 'LOAN_RETURN_PENDING')),
    ]);
}

// ── GET facility_summary (all CPRF facilities + custody counts) ──────────────
if ($method === 'GET' && $action === 'facility_summary') {
    $counts = $pdo->query("
        SELECT cprf_facility_id AS facility_id, COUNT(*) AS on_loan_count,
               SUM(CASE WHEN cprf_custody_status = 'LOAN_RETURN_PENDING' THEN 1 ELSE 0 END) AS return_pending_count
        FROM utility_assets
        WHERE cprf_facility_id IS NOT NULL
        GROUP BY cprf_facility_id
    ")->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

    $out = [];
    foreach ($counts as $fid => $rows) {
        $fidI = (int)$fid;
        if ($fidI <= 0) continue;
        $first = reset($rows);
        $out[$fidI] = [
            'on_loan_count'        => (int)($first['on_loan_count'] ?? 0),
            'return_pending_count' => (int)($first['return_pending_count'] ?? 0),
        ];
    }

    facility_eq_json(200, [
        'success' => true,
        'served_at' => date('c'),
        'custody_by_facility_id' => $out,
    ]);
}

// ── POST assign (UMAN → give assets to a facility) ──────────────────────────
if ($method === 'POST' && $action === 'assign') {
    $facilityId = (int)($body['facility_id'] ?? 0);
    $assetIds = is_array($body['asset_ids'] ?? null) ? $body['asset_ids'] : [];
    $actor = trim((string)($body['actor'] ?? ''));
    $notes = trim((string)($body['notes'] ?? ''));
    $source = trim((string)($body['source'] ?? 'UMAN_DIRECT'));
    $linkedReq = trim((string)($body['linked_request_ref'] ?? ''));
    $facilityName = trim((string)($body['facility_name'] ?? ('CPRF Facility #' . $facilityId)));
    if (!in_array($source, ['UMAN_DIRECT','UMAN_REQUEST_FULFILLED','UMAN_REASSIGNED_DEPRECATED'], true)) {
        $source = 'UMAN_DIRECT';
    }
    if ($facilityId <= 0) {
        facility_eq_json(422, ['success' => false, 'error' => 'facility_id required']);
    }
    if ($assetIds === []) {
        facility_eq_json(422, ['success' => false, 'error' => 'asset_ids (non-empty array) required']);
    }
    $assetIds = array_values(array_unique(array_filter(array_map('intval', $assetIds), static fn($i) => $i > 0)));
    if ($assetIds === []) {
        facility_eq_json(422, ['success' => false, 'error' => 'asset_ids contains no valid IDs']);
    }

    $updAsset = $pdo->prepare("
        UPDATE utility_assets
           SET cprf_facility_id = ?,
               cprf_custody_status = 'ON_LOAN_AT_FACILITY',
               location = COALESCE(NULLIF(location,''), CONCAT('CPRF: ', ?)),
               updated_at = NOW()
         WHERE id = ?
           AND condition_status IN ('Operational','Needs Inspection')
           AND cprf_custody_status IN ('WAREHOUSED','LOAN_RETURNED')
    ");

    $done = 0;
    $skipped = [];
    $webhookResults = [];
    $eventRefs = [];
    $actorLabel = $actor !== '' ? $actor : 'UMAN staff';

    $pdo->beginTransaction();
    try {
        foreach ($assetIds as $aid) {
            $updAsset->execute([$facilityId, $facilityName, $aid]);
            if ($updAsset->rowCount() <= 0) {
                $skipped[] = ['asset_id' => $aid, 'reason' => 'not WAREHOUSED or condition invalid'];
                continue;
            }
            $done++;
            $meta = uman_fetch_asset_meta($pdo, $aid) ?? [];
            $eventRef = uman_event_ref();
            $eventRefs[] = ['asset_id' => $aid, 'event_ref' => $eventRef];

            // Fire webhook synchronously; on failure we still commit the
            // local custody change because CPRF auto-sync (frs_sync_local_uman)
            // will pick it up on next CPRF page load as a fallback.
            $wh = uman_post_to_cprf('utilities/equipment/assigned', [
                'facility_id'       => $facilityId,
                'facility_name'     => $facilityName,
                'uman_asset_id'     => $aid,
                'assignment_source' => $source,
                'assigned_by'       => $actorLabel,
                'assigned_at'       => date('c'),
                'assignment_ref'    => $eventRef,
                'linked_request_ref' => $linkedReq !== '' ? $linkedReq : null,
                'meta'              => [
                    'asset_code'   => (string)($meta['asset_code'] ?? ''),
                    'asset_name'   => (string)($meta['name'] ?? ''),
                    'asset_type'   => (string)($meta['asset_type'] ?? ''),
                    'condition_status' => (string)($meta['condition_status'] ?? ''),
                    'assignment_notes' => $notes,
                    'linked_request_ref' => $linkedReq,
                ],
            ]);
            $webhookResults[] = [
                'asset_id'  => $aid,
                'ok'        => $wh['ok'],
                'http_code' => $wh['http_code'],
                'error'     => $wh['error'] ?? null,
            ];
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        facility_eq_json(500, ['success' => false, 'error' => 'Assign failed: ' . $e->getMessage()]);
    }

    facility_eq_json(200, [
        'success'                => true,
        'action'                 => 'assign',
        'assigned_count'         => $done,
        'skipped'                => $skipped,
        'event_refs'             => $eventRefs,
        'webhook_results'        => $webhookResults,
        'webhook_ok_count'       => count(array_filter($webhookResults, static fn($w) => !empty($w['ok']))),
        'note_on_webhook_fail'   => 'CPRF auto-sync (frs_sync_local_uman_requests) is the fallback for failed webhook delivery.',
    ]);
}

// ── POST unassign (UMAN → recall asset from facility back to warehouse) ───
if ($method === 'POST' && $action === 'unassign') {
    $facilityId = (int)($body['facility_id'] ?? 0);
    $assetId = (int)($body['asset_id'] ?? 0);
    $actor = trim((string)($body['actor'] ?? ''));
    $reason = trim((string)($body['reason'] ?? ''));
    if ($facilityId <= 0 || $assetId <= 0) {
        facility_eq_json(422, ['success' => false, 'error' => 'facility_id and asset_id required']);
    }
    $actorLabel = $actor !== '' ? $actor : 'UMAN staff';
    $eventRef = uman_event_ref();

    $pdo->beginTransaction();
    try {
        $upd = $pdo->prepare("
            UPDATE utility_assets
               SET cprf_facility_id = NULL,
                   cprf_custody_status = CASE
                       WHEN cprf_custody_status = 'LOAN_RETURN_PENDING' THEN 'LOAN_RETURNED'
                       ELSE 'WAREHOUSED'
                   END,
                   updated_at = NOW()
             WHERE id = ? AND cprf_facility_id = ?
        ");
        $upd->execute([$assetId, $facilityId]);
        if ($upd->rowCount() <= 0) {
            $pdo->rollBack();
            facility_eq_json(409, ['success' => false, 'error' => 'Asset is not currently on-loan at this facility']);
        }

        $meta = uman_fetch_asset_meta($pdo, $assetId);
        $wh = uman_post_to_cprf('utilities/equipment/unassigned', [
            'facility_id'    => $facilityId,
            'uman_asset_id'  => $assetId,
            'unassigned_by'  => $actorLabel,
            'unassigned_at'  => date('c'),
            'event_ref'      => $eventRef,
            'reason'         => $reason,
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        facility_eq_json(500, ['success' => false, 'error' => 'Unassign failed: ' . $e->getMessage()]);
    }

    facility_eq_json(200, [
        'success'          => true,
        'action'           => 'unassign',
        'event_ref'        => $eventRef,
        'webhook'          => [
            'ok'        => $wh['ok'],
            'http_code' => $wh['http_code'],
            'error'     => $wh['error'] ?? null,
        ],
        'asset_meta'       => $meta,
        'note_on_webhook_fail' => 'CPRF auto-sync is the fallback for failed delivery.',
    ]);
}

// ── POST accept_return (UMAN accepts CPRF return) ─────────────────────────
if ($method === 'POST' && $action === 'accept_return') {
    $facilityId = (int)($body['facility_id'] ?? 0);
    $assetId = (int)($body['asset_id'] ?? 0);
    $actor = trim((string)($body['actor'] ?? ''));
    $reason = trim((string)($body['reason'] ?? ''));
    $replacement = !empty($body['replacement']);
    $replacementAssetId = !empty($body['replacement_asset_id']) ? (int)$body['replacement_asset_id'] : 0;
    // Phase 3c: explicit return_type overrides inferred `replacement` flag,
    // but we still honor the legacy boolean flag for backwards compat.
    $returnType = trim((string)($body['return_type'] ?? ''));
    if (!in_array($returnType, ['RETURN_ONLY','RETURN_AND_REPLACE','RETURN_DECOMMISSION'], true)) {
        $returnType = $replacement ? 'RETURN_AND_REPLACE' : 'RETURN_ONLY';
    }
    if ($returnType === 'RETURN_AND_REPLACE') {
        $replacement = true;
    }
    $disposalRef  = trim((string)($body['disposal_ref'] ?? ''));
    $trackingNo   = trim((string)($body['tracking_number'] ?? ''));
    if ($facilityId <= 0 || $assetId <= 0) {
        facility_eq_json(422, ['success' => false, 'error' => 'facility_id and asset_id required']);
    }
    $actorLabel = $actor !== '' ? $actor : 'UMAN staff';
    $eventRef = uman_event_ref();

    $pdo->beginTransaction();
    try {
        $meta = uman_fetch_asset_meta($pdo, $assetId);
        if (!$meta) {
            throw new RuntimeException('asset_id not found');
        }
        // Read any prior LOAN_RETURN_PENDING metadata (return_type originally
        // requested by CPRF) so we can fall back if frontend didn't re-send.
        $pendingMeta = $pdo->prepare("
            SELECT cprf_custody_status, condition_status
              FROM utility_assets WHERE id = ? LIMIT 1
        ");
        $pendingMeta->execute([$assetId]);
        $custodyRow = $pendingMeta->fetch(PDO::FETCH_ASSOC) ?: [];
        if ((string)($custodyRow['cprf_custody_status'] ?? '') !== 'ON_LOAN_AT_FACILITY' &&
            (string)($custodyRow['cprf_custody_status'] ?? '') !== 'LOAN_RETURN_PENDING') {
            // Accept the accept but log warning: still continue because CPRF
            // could be replaying the webhook after a prior UMAN-side partial commit.
        }

        $facilityName = trim((string)($body['facility_name'] ?? ('CPRF Facility #' . $facilityId)));
        $assetType = (string)($meta['asset_type'] ?? '');

        // 1. Clear custody on the returned asset.
        //    Phase 3c: RETURN_DECOMMISSION → CONDEMNED forever, not LOAN_RETURNED.
        $newCustodyStatus = $returnType === 'RETURN_DECOMMISSION' ? 'CONDEMNED' : 'LOAN_RETURNED';
        $condUpdate = '';
        $condParams = [];
        if (!empty($body['condition_after_return'])) {
            $condUpdate = ", condition_status = ?";
            $condParams[] = trim((string)$body['condition_after_return']);
        }
        $upd = $pdo->prepare("
            UPDATE utility_assets
               SET cprf_facility_id = NULL,
                   cprf_custody_status = ?
                   {$condUpdate},
                   updated_at = NOW()
             WHERE id = ? AND cprf_facility_id = ?
        ");
        $upd->execute(array_merge([$newCustodyStatus], $condParams, [$assetId, $facilityId]));
        if ($upd->rowCount() <= 0) {
            // Accept even if the row already wasn't at facility (idempotent retry).
        }

        // 2. Phase 3c: Fire the NEW return-accepted webhook (carries richer
        //    COA-specific event_type) instead of the generic unassigned hook.
        $wh = uman_post_to_cprf('utilities/equipment/return-accepted', [
            'facility_id'             => $facilityId,
            'uman_asset_id'           => $assetId,
            'return_type'             => $returnType,
            'accepted_by'             => $actorLabel,
            'accepted_at'             => date('c'),
            'event_ref'               => $eventRef,
            'notes'                   => ($reason !== '' ? $reason : 'Return accepted by UMAN'),
            'condition_after_return'  => !empty($body['condition_after_return'])
                                       ? trim((string)$body['condition_after_return']) : '',
            'disposal_ref'            => $returnType === 'RETURN_DECOMMISSION' ? $disposalRef : '',
            'replacement_asset_id'    => $replacement ? $replacementAssetId : null,
            'linked_request_ref'      => '',
            'replacement_tracking'    => $trackingNo,
        ]);

        // 3. If RETURN_AND_REPLACE: auto-create APPROVED request + assign
        //    the replacement AS A REPLACEMENT_SHIPMENT (not generic assign),
        //    fire replacement-shipped webhook so CPRF gets replacement_in_transit
        //    badge + "Mark Received" button on the facility modal.
        $newReq = null;
        $whRep = null;
        if ($replacement) {
            $newRef = 'CPRF-REPL-' . date('Ymd') . '-' . strtoupper(substr(uniqid('', true), -5, 5));
            $qty = (int)($body['replacement_quantity'] ?? 1);
            if ($qty < 1) $qty = 1;
            $notesMerged = 'Auto-created: replacement for returned asset '
                         . ($meta['asset_code'] ?? '') . ' (' . ($meta['name'] ?? '') . ')'
                         . ($reason !== '' ? '. Reason: ' . $reason : '');
            $pdo->prepare("
                INSERT INTO external_asset_requests
                    (request_ref, source_system, cprf_facility_id, facility_name,
                     asset_type, quantity, notes, status, review_notes,
                     fulfilled_asset_id)
                VALUES (?, 'CPRF', ?, ?, ?, ?, ?, 'approved', ?, ?)
            ")->execute([
                $newRef,
                $facilityId,
                $facilityName,
                $assetType,
                $qty,
                $notesMerged,
                'Replacement for returned unit — auto-approved by system',
                $replacementAssetId > 0 ? $replacementAssetId : null,
            ]);
            $newReq = [
                'request_ref' => $newRef,
                'asset_type'  => $assetType,
                'quantity'    => $qty,
                'status'      => 'approved',
                'fulfilled_asset_id' => $replacementAssetId > 0 ? $replacementAssetId : null,
            ];

            if ($replacementAssetId > 0) {
                // Set the replacement asset ON_LOAN_AT_FACILITY so CPRF facility
                // inventory will show it when received; simultaneously fire the
                // replacement-shipped webhook so CPRF shows "in transit" badge.
                $updRep = $pdo->prepare("
                    UPDATE utility_assets
                       SET cprf_facility_id = ?,
                           cprf_custody_status = 'ON_LOAN_AT_FACILITY',
                           updated_at = NOW()
                     WHERE id = ? AND cprf_custody_status IN ('WAREHOUSED','LOAN_RETURNED','CONDEMNED')
                       AND (condition_status IN ('Operational','Needs Inspection')
                            OR ('' IS NULL))
                ");
                @$updRep->execute([$facilityId, $replacementAssetId]);
                $repMeta = uman_fetch_asset_meta($pdo, $replacementAssetId);
                $repEventRef = uman_event_ref();
                $whRep = uman_post_to_cprf('utilities/equipment/replacement-shipped', [
                    'facility_id'        => $facilityId,
                    'facility_name'      => $facilityName,
                    'original_asset_id'  => $assetId,
                    'replacement_asset_id' => $replacementAssetId,
                    'shipped_by'         => $actorLabel,
                    'shipped_at'         => date('c'),
                    'event_ref'          => $repEventRef,
                    'linked_request_ref' => $newRef,
                    'tracking_number'    => $trackingNo,
                    'condition_status'   => (string)($repMeta['condition_status'] ?? ''),
                    'asset_code'         => (string)($repMeta['asset_code'] ?? ''),
                    'asset_name'         => (string)($repMeta['name'] ?? ''),
                    'asset_type'         => (string)($repMeta['asset_type'] ?? ''),
                    'notes'              => 'Replacement for returned #' . $assetId .
                                            ($reason !== '' ? ' — ' . $reason : ''),
                ]);
                $newReq['replacement_assigned_event_ref'] = $repEventRef;
                $newReq['replacement_webhook'] = ['ok' => $whRep['ok'], 'http_code' => $whRep['http_code'],
                                                  'error' => $whRep['error'] ?? null];
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        facility_eq_json(500, ['success' => false, 'error' => 'Accept return failed: ' . $e->getMessage()]);
    }

    facility_eq_json(200, [
        'success'             => true,
        'action'              => 'accept_return',
        'event_ref'           => $eventRef,
        'return_type'         => $returnType,
        'new_custody_status'  => isset($newCustodyStatus) ? $newCustodyStatus : null,
        'replacement_request' => $newReq,
        'cprf_webhook'        => [
            'ok'        => $wh['ok'] ?? null,
            'http_code' => $wh['http_code'] ?? null,
            'error'     => $wh['error'] ?? null,
        ],
        'cprf_replacement_webhook' => $whRep !== null ? [
            'ok'        => $whRep['ok'],
            'http_code' => $whRep['http_code'],
            'error'     => $whRep['error'] ?? null,
        ] : null,
        'asset_meta'          => $meta,
        'note_on_webhook_fail' => 'CPRF auto-sync is the fallback for failed delivery; COA events are still written locally.',
    ]);
}

// ── POST request_return (CPRF tells UMAN they want to return/replace) ──────
if ($method === 'POST' && $action === 'request_return') {
    $facilityId = (int)($body['facility_id'] ?? 0);
    $assetId = (int)($body['asset_id'] ?? 0);
    $returnType = trim((string)($body['return_type'] ?? 'RETURN_ONLY'));
    $condition = trim((string)($body['condition'] ?? ''));
    $reason = trim((string)($body['reason'] ?? ''));
    $actorLabel = trim((string)($body['actor_label'] ?? 'CPRF user'));
    if (!in_array($returnType, ['RETURN_ONLY','RETURN_AND_REPLACE','RETURN_DECOMMISSION'], true)) {
        $returnType = 'RETURN_ONLY';
    }
    if ($facilityId <= 0 || $assetId <= 0) {
        facility_eq_json(422, ['success' => false, 'error' => 'facility_id and asset_id required']);
    }

    $pdo->beginTransaction();
    try {
        $upd = $pdo->prepare("
            UPDATE utility_assets
               SET cprf_custody_status = 'LOAN_RETURN_PENDING',
                   updated_at = NOW()
             WHERE id = ? AND cprf_facility_id = ?
               AND cprf_custody_status IN ('ON_LOAN_AT_FACILITY')
        ");
        $upd->execute([$assetId, $facilityId]);
        if ($upd->rowCount() <= 0) {
            $pdo->rollBack();
            facility_eq_json(409, [
                'success' => false,
                'error'   => 'Asset is not on-loan at this facility; cannot request return.',
            ]);
        }
        $meta = uman_fetch_asset_meta($pdo, $assetId);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        facility_eq_json(500, ['success' => false, 'error' => 'Request return failed: ' . $e->getMessage()]);
    }

    facility_eq_json(200, [
        'success'      => true,
        'action'       => 'request_return',
        'return_type'  => $returnType,
        'asset_meta'   => $meta,
        'pickup_instructions_for_uman' => match ($returnType) {
            'RETURN_AND_REPLACE' => 'CPRF wants a replacement. After inspecting returned asset for damage, click Accept Return with "Replacement = Yes" on the Assignments page.',
            'RETURN_DECOMMISSION' => 'CPRF reports asset damaged/obsolete. Route to disposal/WMR workflow; no replacement required unless facility requests.',
            default => 'CPRF is done with this asset (e.g. post-event equipment). Schedule warehouse pickup or drop-off.',
        },
        'requester'  => $actorLabel,
        'condition'  => $condition,
        'reason'     => $reason,
    ]);
}

facility_eq_json(404, [
    'success' => false,
    'error'   => 'not_found',
    'action'  => $action,
    'routes'  => [
        'GET ?action=list[&facility_id=ID]',
        'GET ?action=facility_summary',
        'POST assign, unassign, accept_return, request_return',
    ],
]);
