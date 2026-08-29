<?php
/**
 * UMAN staff: CPRF Integration Hub (tabs).
 *
 *   Tab 1 — 📥 Asset Requests     : review/approve/fulfill/reject CPRF requests
 *   Tab 2 — 🏢 Facility Assignments: left facility list + 3 sub-tabs
 *            (Assignable Assets / At This Facility / Activity Log)
 *
 * Form handlers at the top cover actions from BOTH tabs.
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
$webhookNotice = '';

try {
    uman_ensure_cprf_custody_schema($pdo);
    // Auto-migrate schema to support 'returned' status for citizen requests
    $pdo->exec("ALTER TABLE external_asset_requests MODIFY COLUMN status ENUM('pending','approved','fulfilled','rejected','returned') NOT NULL DEFAULT 'pending'");

    // Self-healing: Merge any existing duplicate utility_assets that were created before the merge logic
    $dupStmt = $pdo->query("SELECT asset_id, cprf_facility_id, condition_status, cprf_custody_status, COUNT(*) as cnt FROM utility_assets GROUP BY asset_id, cprf_facility_id, condition_status, cprf_custody_status HAVING cnt > 1");
    $duplicates = $dupStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($duplicates)) {
        foreach ($duplicates as $dup) {
            $q = "SELECT id, quantity FROM utility_assets WHERE asset_id = ? AND condition_status = ? AND cprf_custody_status = ?";
            $params = [$dup['asset_id'], $dup['condition_status'], $dup['cprf_custody_status']];
            if ($dup['cprf_facility_id'] === null) {
                $q .= " AND cprf_facility_id IS NULL";
            } else {
                $q .= " AND cprf_facility_id = ?";
                $params[] = $dup['cprf_facility_id'];
            }
            $rows = $pdo->prepare($q);
            $rows->execute($params);
            $records = $rows->fetchAll(PDO::FETCH_ASSOC);
            if (count($records) > 1) {
                $keepId = $records[0]['id'];
                $totalQty = 0;
                $deleteIds = [];
                foreach ($records as $i => $rec) {
                    $totalQty += (int)$rec['quantity'];
                    if ($i > 0) $deleteIds[] = $rec['id'];
                }
                $pdo->prepare("UPDATE utility_assets SET quantity = ? WHERE id = ?")->execute([$totalQty, $keepId]);
                $in = str_repeat('?,', count($deleteIds) - 1) . '?';
                $pdo->prepare("DELETE FROM utility_assets WHERE id IN ($in)")->execute($deleteIds);
            }
        }
    }
} catch (Throwable $e) {
    $errors[] = 'Schema/Data warning: ' . htmlspecialchars($e->getMessage());
}

// ─────────────────────────────────────────────────────────────────────────────
// Phase-2 external_asset_requests schema ensure (idempotent)
// ─────────────────────────────────────────────────────────────────────────────
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `external_asset_requests` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `request_ref` VARCHAR(50) NOT NULL UNIQUE,
      `source_system` VARCHAR(50) NOT NULL DEFAULT 'CPRF',
      `cprf_facility_id` INT NOT NULL,
      `facility_name` VARCHAR(150) NOT NULL,
      `asset_type` VARCHAR(100) NOT NULL,
      `quantity` INT NOT NULL DEFAULT 1,
      `notes` TEXT NULL,
      `status` ENUM('pending', 'approved', 'fulfilled', 'rejected') NOT NULL DEFAULT 'pending',
      `fulfilled_asset_id` INT NULL,
      `review_notes` TEXT NULL,
      `is_archived` TINYINT(1) NOT NULL DEFAULT 0,
      `archived_at` TIMESTAMP NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
// Self-healing: add is_archived and citizen columns if missing
try { $pdo->query("SELECT `is_archived` FROM `external_asset_requests` LIMIT 1"); }
catch (Throwable $e) {
    try {
        $pdo->exec("ALTER TABLE `external_asset_requests` ADD COLUMN `is_archived` TINYINT(1) NOT NULL DEFAULT 0 AFTER `review_notes`");
        $pdo->exec("ALTER TABLE `external_asset_requests` ADD COLUMN `archived_at` TIMESTAMP NULL AFTER `is_archived`");
    } catch (Throwable $ignored) {}
}
try { $pdo->exec("ALTER TABLE `external_asset_requests` ADD COLUMN `citizen_user_id` INT NULL AFTER `cprf_facility_id`"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE `external_asset_requests` ADD COLUMN `requester_name` VARCHAR(150) NULL AFTER `citizen_user_id`"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE `external_asset_requests` ADD COLUMN `requester_contact` VARCHAR(100) NULL AFTER `requester_name`"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE `external_asset_requests` ADD COLUMN `requested_asset_code` VARCHAR(50) NULL AFTER `asset_type`"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE `external_asset_requests` ADD COLUMN `return_date` DATE NULL AFTER `requested_asset_code`"); } catch (Throwable $e) {}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers (shared between tabs)
// ─────────────────────────────────────────────────────────────────────────────
function current_actor_label(): string
{
    $name = trim((string)($_SESSION['first_name'] ?? ''));
    if ($name !== '' && !empty($_SESSION['last_name'])) {
        return trim($name . ' ' . $_SESSION['last_name']);
    }
    $user = trim((string)($_SESSION['username'] ?? ''));
    return $user !== '' ? $user : 'UMAN staff';
}

function build_asset_meta(PDO $pdo, int $assetId): array
{
    $stmt = $pdo->prepare("
        SELECT a.id, a.asset_id AS asset_code, a.name, t.name AS asset_type,
               a.condition_status, a.responsible_office
        FROM utility_assets a JOIN asset_types t ON t.id = a.asset_type_id
        WHERE a.id = ? LIMIT 1
    ");
    $stmt->execute([$assetId]);
    return (array)($stmt->fetch(PDO::FETCH_ASSOC) ?: []);
}

function h(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES); }

/**
 * Checks utility asset inventory for available stock matching an external request.
 */
function get_request_asset_availability(string $reqAssetType, int $reqQty, array $allAvailableAssets, ?string $specificAssetId = null): array
{
    $reqTypeLower = mb_strtolower(trim($reqAssetType));
    $reqTokens = array_filter(preg_split('/[\s,\-\/&]+/', $reqTypeLower), function($t) { return mb_strlen($t) >= 3; });

    $matchingAssets = [];
    $totalAvailableQty = 0;
    $targetTypeId = null;

    // First pass: If a specific asset ID was requested, find its type_id so we can show ALL stock for that category!
    if (!empty($specificAssetId)) {
        // Find the actual asset ID by checking for known formats
        foreach ($allAvailableAssets as $a) {
            $aIdStr = trim((string)($a['asset_id'] ?? ''));
            if ($aIdStr !== '' && (
                $specificAssetId === $aIdStr ||
                str_starts_with($specificAssetId, $aIdStr . ' -') ||
                str_starts_with($specificAssetId, $aIdStr . '-') ||
                str_starts_with($specificAssetId, $aIdStr . ' ')
            )) {
                $targetTypeId = $a['type_id'] ?? null;
                break;
            }
        }
    }

    foreach ($allAvailableAssets as $asset) {
        $matches = false;
        $qty = intval($asset['quantity'] ?? 1);
        
        // If we found the target type ID based on the specific asset requested, just match by type ID!
        if ($targetTypeId !== null) {
            if (($asset['type_id'] ?? null) == $targetTypeId) {
                $matches = true;
            }
        } else {
            // Fallback to fuzzy text matching if no specific asset was found or provided
            $typeNameLower = mb_strtolower(trim((string)($asset['asset_type'] ?? '')));
            $assetNameLower = mb_strtolower(trim((string)($asset['name'] ?? '')));

            if ($typeNameLower !== '' && $reqTypeLower !== '' && ($typeNameLower === $reqTypeLower || stripos($typeNameLower, $reqTypeLower) !== false || stripos($reqTypeLower, $typeNameLower) !== false)) {
                $matches = true;
            } elseif ($reqTypeLower !== '' && (stripos($assetNameLower, $reqTypeLower) !== false || $assetNameLower === $reqTypeLower)) {
                $matches = true;
            } else {
                foreach ($reqTokens as $token) {
                    $pattern = '/\b' . preg_quote($token, '/') . '/i';
                    if (($typeNameLower !== '' && preg_match($pattern, $typeNameLower)) || preg_match($pattern, $assetNameLower)) {
                        $matches = true;
                        break;
                    }
                }
            }
        }

        if ($matches) {
            $matchingAssets[] = $asset;
            $totalAvailableQty += $qty;
        }
    }

    $isSufficient = ($totalAvailableQty >= $reqQty && $reqQty > 0);
    $isPartial = ($totalAvailableQty > 0 && $totalAvailableQty < $reqQty);
    $isUnavailable = ($totalAvailableQty === 0);

    return [
        'available_qty'   => $totalAvailableQty,
        'requested_qty'   => $reqQty,
        'is_sufficient'   => $isSufficient,
        'is_partial'      => $isPartial,
        'is_unavailable'  => $isUnavailable,
        'matching_assets' => $matchingAssets,
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// POST handlers (for BOTH tabs — action= determines which one)
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    // ═════════════════════════════════════════════════════════════════════════
    // Archive handler
    // ═════════════════════════════════════════════════════════════════════════
    if ($action === 'archive') {
        $archiveId = (int)($_POST['id'] ?? 0);
        if ($archiveId > 0) {
            try {
                $archRow = $pdo->prepare("SELECT status, request_ref FROM external_asset_requests WHERE id = ? LIMIT 1");
                $archRow->execute([$archiveId]);
                $archData = $archRow->fetch(PDO::FETCH_ASSOC);
                if ($archData && in_array($archData['status'], ['fulfilled', 'rejected'], true)) {
                    $pdo->prepare("UPDATE external_asset_requests SET is_archived = 1, archived_at = NOW() WHERE id = ?")
                        ->execute([$archiveId]);
                    $successes[] = 'Request ' . htmlspecialchars($archData['request_ref']) . ' has been archived.';
                } else {
                    $errors[] = 'Only fulfilled or rejected requests can be archived.';
                }
            } catch (Throwable $e) {
                $errors[] = 'Archive failed: ' . htmlspecialchars($e->getMessage());
            }
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Tab 1 — Asset Requests handlers
    // ═════════════════════════════════════════════════════════════════════════
    if (in_array($action, ['approve','reject','fulfill'], true)) {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $errors[] = 'Invalid request.';
        } elseif ($action === 'approve') {
            $notes = trim((string)($_POST['review_notes'] ?? ''));

            // Check real-time inventory availability before approving
            $reqStmt = $pdo->prepare("SELECT id, request_ref, asset_type, quantity, citizen_user_id, requested_asset_code FROM external_asset_requests WHERE id = ? LIMIT 1");
            $reqStmt->execute([$id]);
            $targetReq = $reqStmt->fetch(PDO::FETCH_ASSOC);

            if (!$targetReq) {
                $errors[] = 'Request not found.';
            } else {
                $currentStock = [];
                try {
                    $currentStock = $pdo->query("
                        SELECT a.id, a.asset_id, a.name, a.quantity, a.condition_status,
                               t.id AS type_id, t.name AS asset_type
                        FROM utility_assets a
                        JOIN asset_types t ON t.id = a.asset_type_id
                        WHERE a.condition_status IN ('Operational', 'Needs Inspection')
                    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } catch (Throwable $e) {
                    $currentStock = [];
                }
                $availCheck = get_request_asset_availability($targetReq['asset_type'], (int)$targetReq['quantity'], $currentStock, $targetReq['requested_asset_code'] ?? null);

                if (!$availCheck['is_sufficient']) {
                    $errors[] = "Cannot approve request {$targetReq['request_ref']}: Insufficient stock. Only {$availCheck['available_qty']} of {$targetReq['quantity']} units of '{$targetReq['asset_type']}' are available in inventory.";
                } else {
                    $pdo->prepare("UPDATE external_asset_requests SET status = 'approved', review_notes = ?, updated_at = NOW() WHERE id = ?")
                        ->execute([$notes ?: null, $id]);
                    $successes[] = "Request {$targetReq['request_ref']} approved successfully (Stock verified: {$availCheck['available_qty']} units available).";

                    // Citizen notification if applicable
                    if (!empty($targetReq['citizen_user_id'])) {
                        try {
                            $notifText = "Your asset request [{$targetReq['request_ref']}] for {$targetReq['quantity']}x {$targetReq['asset_type']} has been APPROVED by LGU staff.";
                            if ($notes !== '') $notifText .= " Note: " . $notes;
                            $pdo->prepare("INSERT INTO incident_notifications (user_id, message, read_status) VALUES (?, ?, 0)")
                                ->execute([(int)$targetReq['citizen_user_id'], $notifText]);
                        } catch (Throwable $e) {}
                    }
                }
            }
        } elseif ($action === 'reject') {
            $notes = trim((string)($_POST['review_notes'] ?? ''));
            $reqStmt = $pdo->prepare("SELECT id, request_ref, asset_type, quantity, citizen_user_id FROM external_asset_requests WHERE id = ? LIMIT 1");
            $reqStmt->execute([$id]);
            $targetReq = $reqStmt->fetch(PDO::FETCH_ASSOC);

            $pdo->prepare("UPDATE external_asset_requests SET status = 'rejected', is_archived = 1, archived_at = NOW(), review_notes = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$notes ?: null, $id]);
            $successes[] = 'Request rejected and archived.';

            if (!empty($targetReq['citizen_user_id'])) {
                try {
                    $notifText = "Your asset request [{$targetReq['request_ref']}] for {$targetReq['quantity']}x {$targetReq['asset_type']} has been REJECTED.";
                    if ($notes !== '') $notifText .= " Reason: " . $notes;
                    $pdo->prepare("INSERT INTO incident_notifications (user_id, message, read_status) VALUES (?, ?, 0)")
                        ->execute([(int)$targetReq['citizen_user_id'], $notifText]);
                } catch (Throwable $e) {}
            }
        } elseif ($action === 'fulfill') {
            $assetId = (int)($_POST['fulfilled_asset_id'] ?? 0);
            $notes = trim((string)($_POST['review_notes'] ?? ''));
            if ($assetId <= 0) {
                $errors[] = 'Select a utility asset to fulfill this request.';
            } else {
                $req = $pdo->prepare("SELECT id, request_ref, cprf_facility_id, citizen_user_id, asset_type, quantity, facility_name FROM external_asset_requests WHERE id = ? LIMIT 1");
                $req->execute([$id]);
                $reqRow = $req->fetch(PDO::FETCH_ASSOC);
                $facilityId = (int)($reqRow['cprf_facility_id'] ?? 0);
                $facilityName = (string)($reqRow['facility_name'] ?? ('Facility #' . $facilityId));
                $requestRef = (string)($reqRow['request_ref'] ?? '');
                $actor = current_actor_label();

                $pdo->beginTransaction();
                try {
                    $pdo->prepare("UPDATE external_asset_requests SET status = 'fulfilled', is_archived = 1, archived_at = NOW(), fulfilled_asset_id = ?, review_notes = ?, updated_at = NOW() WHERE id = ?")
                        ->execute([$assetId, $notes ?: null, $id]);

                    if (!empty($reqRow['citizen_user_id'])) {
                        try {
                            $metaAss = build_asset_meta($pdo, $assetId);
                            $assetCodeStr = !empty($metaAss['asset_code']) ? " ({$metaAss['asset_code']})" : "";
                            $notifText = "Your asset request [{$requestRef}] for {$reqRow['quantity']}x {$reqRow['asset_type']} has been FULFILLED! Assigned unit: " . ($metaAss['name'] ?? 'Asset') . $assetCodeStr . ".";
                            $pdo->prepare("INSERT INTO incident_notifications (user_id, message, read_status) VALUES (?, ?, 0)")
                                ->execute([(int)$reqRow['citizen_user_id'], $notifText]);
                        } catch (Throwable $e) {}
                    }

                    $webhookNotice = '';
                    if (!empty($reqRow['citizen_user_id'])) {
                        $upd = $pdo->prepare("
                            UPDATE utility_assets
                               SET quantity = GREATEST(0, quantity - ?),
                                   updated_at = NOW()
                             WHERE id = ?
                               AND condition_status IN ('Operational','Needs Inspection')
                        ");
                        $upd->execute([$reqRow['quantity'], $assetId]);
                    } else {
                        $upd = $pdo->prepare("
                            UPDATE utility_assets
                               SET cprf_facility_id = ?,
                                   cprf_custody_status = 'ON_LOAN_AT_FACILITY',
                                   location = COALESCE(NULLIF(location,''), CONCAT('CPRF: ', ?)),
                                   updated_at = NOW()
                             WHERE id = ?
                               AND condition_status IN ('Operational','Needs Inspection')
                               AND cprf_custody_status IN ('WAREHOUSED','LOAN_RETURNED')
                        ");
                        $upd->execute([$facilityId, $facilityName, $assetId]);
                        if ($upd->rowCount() <= 0) {
                            $webhookNotice = ' WARNING: the selected asset is not WAREHOUSED (it may already be on-loan elsewhere). Request still marked as fulfilled.';
                        }
                    }

                    $pdo->commit();
                    $successes[] = 'Request marked fulfilled and linked to asset.' . $webhookNotice;

                    if ($facilityId > 0) {
                        $meta = build_asset_meta($pdo, $assetId);
                        $eventRef = 'UMAN-FULFILL-' . date('YmdHis') . '-' . $id;
                        $wh = uman_post_to_cprf('utilities/equipment/assigned', [
                            'facility_id'        => $facilityId,
                            'facility_name'      => $facilityName,
                            'uman_asset_id'      => $assetId,
                            'assignment_source'  => 'UMAN_REQUEST_FULFILLED',
                            'assigned_by'        => $actor,
                            'assigned_at'        => date('c'),
                            'assignment_ref'     => $eventRef,
                            'linked_request_ref' => $requestRef,
                            'meta'               => [
                                'asset_code'         => (string)($meta['asset_code'] ?? ''),
                                'asset_name'         => (string)($meta['name'] ?? ''),
                                'asset_type'         => (string)($meta['asset_type'] ?? ''),
                                'condition_status'   => (string)($meta['condition_status'] ?? ''),
                                'linked_request_ref' => $requestRef,
                                'assignment_notes'   => $notes,
                            ],
                        ]);
                        if (empty($wh['ok'])) {
                            $whAppend = ' (CPRF webhook NOT delivered — CPRF will pick up custody via auto-sync on next page load: '
                                      . htmlspecialchars($wh['error'] ?? ('HTTP ' . ($wh['http_code'] ?? 0))) . ')';
                            $successes[count($successes) - 1] = rtrim(end($successes), '.') . $whAppend;
                        }
                    }
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    $errors[] = 'Fulfill failed: ' . htmlspecialchars($e->getMessage());
                }
            }
        }
    }
    // ═════════════════════════════════════════════════════════════════════════
    // Tab 2 — Facility Assignments handlers
    // ═════════════════════════════════════════════════════════════════════════
    else {
        $facilityId = (int)($_POST['facility_id'] ?? 0);
        $facilityName = trim((string)($_POST['facility_name'] ?? ('CPRF Facility #' . $facilityId)));

        if ($action === 'refresh_facilities') {
            $successes[] = 'Facility list refreshed from CPRF.';
        } elseif ($facilityId === 0 && $action !== 'accept_citizen_return') {
            $errors[] = 'Select a CPRF facility first (from the Assignments tab left list).';
        } elseif ($action === 'assign_selected') {
            $assetIds = is_array($_POST['asset_ids'] ?? null) ? $_POST['asset_ids'] : [];
            $assetIds = array_values(array_unique(array_filter(array_map('intval', $assetIds), static fn($i) => $i > 0)));
            if ($assetIds === []) {
                $errors[] = 'Select one or more assets to assign.';
            } else {
                $actor = current_actor_label();
                $notes = trim((string)($_POST['notes'] ?? ''));
                $source = 'UMAN_DIRECT';
                $assigned = 0;
                $skipped = [];
                $webhookFails = 0;

                $pdo->beginTransaction();
                try {
                    $upd = $pdo->prepare("
                        UPDATE utility_assets
                           SET cprf_facility_id = ?,
                               cprf_custody_status = 'ON_LOAN_AT_FACILITY',
                               location = COALESCE(NULLIF(location,''), CONCAT('CPRF: ', ?)),
                               updated_at = NOW()
                         WHERE id = ?
                    ");

                    foreach ($assetIds as $aid) {
                        // 1. Fetch current asset for quantity check (with row lock)
                        $assetStmt = $pdo->prepare("SELECT asset_id, name, asset_type_id, quantity, location, latitude, longitude, date_installed, condition_status, description, responsible_office FROM utility_assets WHERE id = ? AND condition_status IN ('Operational','Needs Inspection') AND cprf_custody_status IN ('WAREHOUSED','LOAN_RETURNED') FOR UPDATE");
                        $assetStmt->execute([$aid]);
                        $assetRow = $assetStmt->fetch(PDO::FETCH_ASSOC);

                        if (!$assetRow) {
                            $skipped[] = $aid;
                            continue;
                        }

                        // 2. Determine assignment quantity
                        $requestedQty = isset($_POST['assign_qty'][$aid]) ? (int)$_POST['assign_qty'][$aid] : 1;
                        $availQty = (int)$assetRow['quantity'];
                        if ($requestedQty < 1) $requestedQty = 1;
                        if ($requestedQty > $availQty) $requestedQty = $availQty; // clamp

                        $assignedAid = $aid;

                        // 3. Check if facility already has this exact asset (merge check)
                        $checkStmt = $pdo->prepare("SELECT id, quantity FROM utility_assets WHERE asset_id = ? AND cprf_facility_id = ? AND condition_status = ? AND cprf_custody_status = 'ON_LOAN_AT_FACILITY'");
                        $checkStmt->execute([$assetRow['asset_id'], $facilityId, $assetRow['condition_status']]);
                        $existingAsset = $checkStmt->fetch(PDO::FETCH_ASSOC);

                        if ($existingAsset) {
                            // Merge into existing asset
                            $mergeId = $existingAsset['id'];
                            
                            $pdo->prepare("UPDATE utility_assets SET quantity = quantity + ?, updated_at = NOW() WHERE id = ?")->execute([$requestedQty, $mergeId]);
                            
                            if ($requestedQty === $availQty) {
                                // Full assignment -> Delete original warehouse row since it's merged
                                $pdo->prepare("DELETE FROM utility_assets WHERE id = ?")->execute([$aid]);
                            } else {
                                // Partial assignment -> Decrease original warehouse row
                                $pdo->prepare("UPDATE utility_assets SET quantity = quantity - ?, updated_at = NOW() WHERE id = ?")->execute([$requestedQty, $aid]);
                            }
                            
                            $assignedAid = $mergeId;
                        } else {
                            // No existing asset to merge into. Process as normal full/partial
                            if ($requestedQty === $availQty) {
                                // Full assignment
                                $upd->execute([$facilityId, $facilityName, $aid]);
                                $assignedAid = $aid;
                            } else {
                                // Partial assignment: split the asset
                                // 3a. Decrease original asset quantity
                                $decStmt = $pdo->prepare("UPDATE utility_assets SET quantity = quantity - ?, updated_at = NOW() WHERE id = ?");
                                $decStmt->execute([$requestedQty, $aid]);
                                
                                // 3b. Insert new split asset
                                $newAssetId = $assetRow['asset_id'];
                                $insStmt = $pdo->prepare("
                                    INSERT INTO utility_assets (
                                        asset_id, name, asset_type_id, quantity, location, 
                                        latitude, longitude, date_installed, condition_status, 
                                        description, responsible_office, cprf_facility_id, 
                                        cprf_custody_status, created_at, updated_at
                                    ) VALUES (?, ?, ?, ?, COALESCE(NULLIF(?,''), CONCAT('CPRF: ', ?)), ?, ?, ?, ?, ?, ?, ?, 'ON_LOAN_AT_FACILITY', NOW(), NOW())
                                ");
                                $insStmt->execute([
                                    $newAssetId,
                                    $assetRow['name'],
                                    $assetRow['asset_type_id'],
                                    $requestedQty,
                                    $assetRow['location'],
                                    $facilityName,
                                    $assetRow['latitude'],
                                    $assetRow['longitude'],
                                    $assetRow['date_installed'],
                                    $assetRow['condition_status'],
                                    $assetRow['description'],
                                    $assetRow['responsible_office'],
                                    $facilityId
                                ]);
                                $assignedAid = $pdo->lastInsertId();
                            }
                        }

                        $assigned++;
                        $meta = build_asset_meta($pdo, $assignedAid);
                        $eventRef = 'UFE-ASG-' . date('YmdHis') . '-' . $assignedAid;

                        // Log to Activity tab
                        $pdo->prepare("
                            INSERT INTO external_asset_requests
                                (request_ref, source_system, cprf_facility_id, facility_name,
                                 asset_type, quantity, notes, status, fulfilled_asset_id, review_notes)
                            VALUES (?, 'UMAN_DIRECT', ?, ?, ?, ?, ?, 'fulfilled', ?, ?)
                        ")->execute([
                            $eventRef, $facilityId, $facilityName,
                            $meta['asset_type'] ?? 'Asset', $requestedQty, $notes,
                            $assignedAid, 'Direct assignment by ' . $actor
                        ]);

                        $wh = uman_post_to_cprf('utilities/equipment/assigned', [
                            'facility_id'       => $facilityId,
                            'facility_name'     => $facilityName,
                            'uman_asset_id'     => $assignedAid,
                            'assignment_source' => $source,
                            'assigned_by'       => $actor,
                            'assigned_at'       => date('c'),
                            'assignment_ref'    => $eventRef,
                            'meta'              => [
                                'asset_code'         => (string)($meta['asset_code'] ?? ''),
                                'asset_name'         => (string)($meta['name'] ?? ''),
                                'asset_type'         => (string)($meta['asset_type'] ?? ''),
                                'condition_status'   => (string)($meta['condition_status'] ?? ''),
                                'assignment_notes'   => $notes,
                            ],
                        ]);
                        if (empty($wh['ok'])) {
                            $webhookFails++;
                        }
                    }
                    $pdo->commit();
                    $successes[] = sprintf(
                        'Assigned %d asset(s) to %s.%s%s',
                        $assigned,
                        htmlspecialchars($facilityName),
                        $skipped !== [] ? ' Skipped ' . count($skipped) . ' (already on-loan elsewhere or invalid condition).' : '',
                        $webhookFails > 0 ? " {$webhookFails} CPRF webhook(s) not delivered yet — auto-sync will pick them up." : ''
                    );
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    $errors[] = 'Assign failed: ' . htmlspecialchars($e->getMessage());
                }
            }
        } elseif ($action === 'unassign') {
            $assetId = (int)($_POST['asset_id'] ?? 0);
            if ($assetId <= 0) {
                $errors[] = 'asset_id required.';
            } else {
                $actor = current_actor_label();
                $reason = trim((string)($_POST['reason'] ?? ''));
                $eventRef = 'UFE-UN-' . date('YmdHis') . '-' . $assetId;

                $pdo->beginTransaction();
                try {
                    $unassignQtyStmt = $pdo->prepare("SELECT quantity FROM utility_assets WHERE id = ?");
                    $unassignQtyStmt->execute([$assetId]);
                    $unassignQty = $unassignQtyStmt->fetchColumn() ?: 1;

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
                        throw new RuntimeException('Asset is not currently on-loan at this facility.');
                    }
                    $meta = build_asset_meta($pdo, $assetId);

                    // Log to Activity tab
                    $pdo->prepare("
                        INSERT INTO external_asset_requests
                            (request_ref, source_system, cprf_facility_id, facility_name,
                             asset_type, quantity, status, fulfilled_asset_id, review_notes)
                        VALUES (?, 'UMAN_DIRECT', ?, ?, ?, ?, 'returned', ?, ?)
                    ")->execute([
                        $eventRef, $facilityId, $facilityName,
                        $meta['asset_type'] ?? 'Asset', $unassignQty, $assetId, 'Recalled by ' . $actor
                    ]);

                    $pdo->commit();

                    $wh = uman_post_to_cprf('utilities/equipment/unassigned', [
                        'facility_id'   => $facilityId,
                        'uman_asset_id' => $assetId,
                        'unassigned_by' => $actor,
                        'unassigned_at' => date('c'),
                        'event_ref'     => $eventRef,
                        'reason'        => $reason !== '' ? $reason : 'Recalled by UMAN staff',
                    ]);
                    $whMsg = empty($wh['ok'])
                        ? ' CPRF webhook not delivered — auto-sync on next CPRF page load will apply the change.'
                        : '';
                    $successes[] = sprintf(
                        'Unassigned %s (%s) from %s.%s',
                        htmlspecialchars($meta['name'] ?? ('Asset #' . $assetId)),
                        htmlspecialchars($meta['asset_code'] ?? ''),
                        htmlspecialchars($facilityName),
                        $whMsg
                    );
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    $errors[] = 'Unassign failed: ' . htmlspecialchars($e->getMessage());
                }
            }
        } elseif ($action === 'accept_return') {
            $assetId = (int)($_POST['asset_id'] ?? 0);
            $replacement = !empty($_POST['replacement']);
            $replacementAssetId = !empty($_POST['replacement_asset_id']) ? (int)$_POST['replacement_asset_id'] : 0;
            $reason = trim((string)($_POST['reason'] ?? ''));
            $conditionAfter = trim((string)($_POST['condition_after_return'] ?? ''));

            if ($assetId <= 0) {
                $errors[] = 'asset_id required.';
            } else {
                $actor = current_actor_label();
                $eventRef = 'UFE-RET-' . date('YmdHis') . '-' . $assetId;
                $newReq = null;

                $pdo->beginTransaction();
                try {
                    $meta = build_asset_meta($pdo, $assetId);
                    $assetType = (string)($meta['asset_type'] ?? '');

                    // Fetch the asset being returned
                    $retStmt = $pdo->prepare("SELECT asset_id, quantity, condition_status FROM utility_assets WHERE id = ?");
                    $retStmt->execute([$assetId]);
                    $retAsset = $retStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$retAsset) {
                        throw new RuntimeException('Asset not found.');
                    }

                    $finalCondition = $conditionAfter !== '' ? $conditionAfter : $retAsset['condition_status'];

                    // Check if we can merge it into an existing WAREHOUSED asset
                    $checkWhStmt = $pdo->prepare("SELECT id FROM utility_assets WHERE asset_id = ? AND cprf_custody_status IN ('WAREHOUSED', 'LOAN_RETURNED') AND condition_status = ? AND cprf_facility_id IS NULL AND id != ?");
                    $checkWhStmt->execute([$retAsset['asset_id'], $finalCondition, $assetId]);
                    $whAsset = $checkWhStmt->fetch(PDO::FETCH_ASSOC);

                    if ($whAsset) {
                        // Merge into existing warehouse asset
                        $pdo->prepare("UPDATE utility_assets SET quantity = quantity + ?, updated_at = NOW() WHERE id = ?")->execute([$retAsset['quantity'], $whAsset['id']]);
                        
                        // Delete the returned row
                        $pdo->prepare("DELETE FROM utility_assets WHERE id = ?")->execute([$assetId]);
                    } else {
                        // No existing warehouse asset, just update this row
                        $condParams = [];
                        $condSql = '';
                        if ($conditionAfter !== '') {
                            $condSql = ', condition_status = ?';
                            $condParams[] = $conditionAfter;
                        }
                        $upd = $pdo->prepare("
                            UPDATE utility_assets
                               SET cprf_facility_id = NULL,
                                   cprf_custody_status = 'WAREHOUSED'
                                   {$condSql},
                                   updated_at = NOW()
                             WHERE id = ? AND cprf_facility_id = ?
                        ");
                        $upd->execute(array_merge($condParams, [$assetId, $facilityId]));
                        if ($upd->rowCount() <= 0) {
                            throw new RuntimeException('Asset is not currently on-loan at this facility.');
                        }
                    }

                    $wh = uman_post_to_cprf('utilities/equipment/unassigned', [
                        'facility_id'   => $facilityId,
                        'uman_asset_id' => $assetId,
                        'unassigned_by' => $actor,
                        'unassigned_at' => date('c'),
                        'event_ref'     => $eventRef,
                        'reason'        => ($reason !== '' ? $reason : 'Return accepted by UMAN')
                                         . ($replacement ? ' (replacement pending)' : ''),
                    ]);

                    // Log to Activity tab
                    $pdo->prepare("
                        INSERT INTO external_asset_requests
                            (request_ref, source_system, cprf_facility_id, facility_name,
                             asset_type, quantity, notes, status, fulfilled_asset_id, review_notes)
                        VALUES (?, 'UMAN_DIRECT', ?, ?, ?, ?, ?, 'returned', ?, ?)
                    ")->execute([
                        $eventRef, $facilityId, $facilityName,
                        $assetType, $retAsset['quantity'], $reason,
                        $assetId, 'Return accepted by ' . $actor
                    ]);

                    if ($replacement) {
                        $newRef = 'CPRF-REPL-' . date('Ymd') . '-' . strtoupper(substr(uniqid('', true), -5, 5));
                        $qty = 1;
                        $notesMerged = 'Auto-created: replacement for returned asset '
                                     . ($meta['asset_code'] ?? '') . ' (' . ($meta['name'] ?? '') . ')'
                                     . ($reason !== '' ? '. Reason: ' . $reason : '');
                        $pdo->prepare("
                            INSERT INTO external_asset_requests
                                (request_ref, source_system, cprf_facility_id, facility_name,
                                 asset_type, quantity, notes, status, review_notes, fulfilled_asset_id)
                            VALUES (?, 'CPRF', ?, ?, ?, ?, ?, 'approved', ?, ?)
                        ")->execute([
                            $newRef, $facilityId, $facilityName,
                            $assetType, $qty, $notesMerged,
                            'Replacement for returned unit — auto-approved by system',
                            $replacementAssetId > 0 ? $replacementAssetId : null,
                        ]);
                        $newReq = ['request_ref' => $newRef, 'status' => 'approved'];

                        if ($replacementAssetId > 0) {
                            $updRep = $pdo->prepare("
                                UPDATE utility_assets
                                   SET cprf_facility_id = ?,
                                       cprf_custody_status = 'ON_LOAN_AT_FACILITY',
                                       updated_at = NOW()
                                 WHERE id = ? AND cprf_custody_status IN ('WAREHOUSED','LOAN_RETURNED')
                                   AND condition_status IN ('Operational','Needs Inspection')
                            ");
                            $updRep->execute([$facilityId, $replacementAssetId]);
                            $repMeta = build_asset_meta($pdo, $replacementAssetId);
                            $repEvent = 'UFE-REP-' . date('YmdHis') . '-' . $replacementAssetId;
                            uman_post_to_cprf('utilities/equipment/assigned', [
                                'facility_id'        => $facilityId,
                                'facility_name'      => $facilityName,
                                'uman_asset_id'      => $replacementAssetId,
                                'assignment_source'  => 'UMAN_REASSIGNED_DEPRECATED',
                                'assigned_by'        => $actor,
                                'assigned_at'        => date('c'),
                                'assignment_ref'     => $repEvent,
                                'linked_request_ref' => $newRef,
                                'meta'               => [
                                    'asset_code'         => (string)($repMeta['asset_code'] ?? ''),
                                    'asset_name'         => (string)($repMeta['name'] ?? ''),
                                    'asset_type'         => (string)($repMeta['asset_type'] ?? ''),
                                    'condition_status'   => (string)($repMeta['condition_status'] ?? ''),
                                    'linked_request_ref' => $newRef,
                                    'assignment_notes'   => 'Replacement for returned #' . $assetId,
                                ],
                            ]);
                        }
                    }
                    $pdo->commit();
                    $whMsg = empty($wh['ok'])
                        ? ' CPRF webhook not delivered for the returned asset — auto-sync will catch it on next load.'
                        : '';
                    $successes[] = sprintf(
                        'Accepted return of %s (%s).%s%s',
                        htmlspecialchars($meta['name'] ?? ('Asset #' . $assetId)),
                        htmlspecialchars($meta['asset_code'] ?? ''),
                        $newReq !== null ? " Replacement request <strong>{$newReq['request_ref']}</strong> created (approved)." : '',
                        $whMsg
                    );
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    $errors[] = 'Accept return failed: ' . htmlspecialchars($e->getMessage());
                }
            }
        } elseif ($action === 'accept_citizen_return') {
            $reqId = (int)($_POST['id'] ?? 0);
            $assetId = (int)($_POST['fulfilled_asset_id'] ?? 0);
            $qty = (int)($_POST['quantity'] ?? 0);
            if ($reqId <= 0 || $assetId <= 0) {
                $errors[] = 'Invalid return request.';
            } else {
                $pdo->beginTransaction();
                try {
                    $pdo->prepare("UPDATE external_asset_requests SET status = 'returned', updated_at = NOW() WHERE id = ?")->execute([$reqId]);
                    $pdo->prepare("UPDATE utility_assets SET quantity = quantity + ?, updated_at = NOW() WHERE id = ?")->execute([$qty, $assetId]);
                    $pdo->commit();
                    $successes[] = 'Citizen asset returned to inventory successfully.';
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    $errors[] = 'Return failed: ' . htmlspecialchars($e->getMessage());
                }
            }
        }
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// Tab 1 — Asset Requests: data preload
// ═════════════════════════════════════════════════════════════════════════════
$filter = trim($_GET['status'] ?? '');
$sourceFilter = trim($_GET['source'] ?? '');
$showArchived = (($_GET['archived'] ?? '') === '1');

// If filtering by rejected/returned, implicitly switch to archived view
if (in_array($filter, ['rejected', 'returned'], true)) {
    $showArchived = true;
}

$sql = 'SELECT r.*, a.name AS fulfilled_asset_name, a.asset_id AS fulfilled_asset_code, req_a.name AS requested_asset_name FROM external_asset_requests r LEFT JOIN utility_assets a ON a.id = r.fulfilled_asset_id LEFT JOIN utility_assets req_a ON req_a.asset_id = r.requested_asset_code WHERE 1=1';
$params = [];
if ($filter !== '' && in_array($filter, ['pending', 'approved', 'fulfilled', 'rejected', 'returned'], true)) {
    $sql .= ' AND r.status = ?';
    $params[] = $filter;
}
if ($sourceFilter === 'citizen') {
    $sql .= " AND (r.source_system = 'Citizen Portal' OR r.citizen_user_id IS NOT NULL)";
} elseif ($sourceFilter === 'cprf') {
    $sql .= " AND (r.source_system = 'CPRF' OR (r.source_system IS NULL AND r.citizen_user_id IS NULL))";
}

$isCitizenSql = "(r.source_system = 'Citizen Portal' OR r.citizen_user_id IS NOT NULL)";

if ($showArchived) {
    $sql .= " AND (r.is_archived = 1 OR r.status IN ('rejected', 'returned') OR (r.status = 'fulfilled' AND NOT $isCitizenSql))";
} else {
    $sql .= " AND (r.is_archived = 0 OR r.is_archived IS NULL) AND r.status NOT IN ('rejected', 'returned') AND NOT (r.status = 'fulfilled' AND NOT $isCitizenSql)";
}
$sql .= ' ORDER BY r.created_at DESC LIMIT 100';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
// Count archived for badge
$countArchived = 0;
try { 
    $countArchived = (int)$pdo->query("SELECT COUNT(*) FROM external_asset_requests WHERE is_archived = 1 OR status IN ('rejected', 'returned') OR (status = 'fulfilled' AND (source_system IS NULL OR source_system != 'Citizen Portal') AND citizen_user_id IS NULL)")->fetchColumn(); 
}
catch (Throwable $e) { $countArchived = 0; }

$allAvailableAssets = [];
try {
    $allAvailableAssets = $pdo->query("
        SELECT a.id, a.asset_id, a.name, a.quantity, a.condition_status,
               t.id AS type_id, t.name AS asset_type
        FROM utility_assets a
        JOIN asset_types t ON t.id = a.asset_type_id
        WHERE a.condition_status IN ('Operational', 'Needs Inspection')
        ORDER BY t.name ASC, a.name ASC
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $allAvailableAssets = [];
}
$assetsForFulfill = $allAvailableAssets;

// ═════════════════════════════════════════════════════════════════════════════
// Tab 2 — Facility Assignments: data preload (facilities + assignment panels)
// ═════════════════════════════════════════════════════════════════════════════
$cprfFacilities = [];
$cprfFetchError = null;

$cprfApiUrl = CPRF_BASE_URL . CPRF_INTEGRATIONS_PREFIX . '/facilities/status';
$ch = curl_init($cprfApiUrl);
if ($ch !== false) {
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['X-API-Key: ' . CPRF_INBOUND_API_KEY],
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);
    $respBody = (string)curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_errno($ch) !== 0 ? curl_error($ch) : null;
    curl_close($ch);
    if ($curlErr !== null) {
        $cprfFetchError = $curlErr;
    } elseif ($httpCode < 200 || $httpCode >= 300) {
        $cprfFetchError = 'HTTP ' . $httpCode;
    } else {
        $decoded = json_decode($respBody, true);
        if (is_array($decoded) && !empty($decoded['success'])) {
            $cprfFacilities = array_values((array)($decoded['facilities'] ?? []));
        } else {
            $cprfFetchError = 'CPRF returned non-success response';
        }
    }
} else {
    $cprfFetchError = 'curl_init() unavailable';
}

$cprfFacilities = array_map(static function ($row) {
    return [
        'id'                       => (int)($row['id'] ?? 0),
        'name'                     => (string)($row['name'] ?? 'Unknown Facility'),
        'status'                   => (string)($row['status'] ?? 'available'),
        'status_label'             => (string)($row['status_label'] ?? 'Available'),
        'is_assignable'            => !empty($row['is_assignable']) || ($row['status'] ?? '') !== 'offline',
        'location'                 => (string)($row['location'] ?? ''),
        'capacity'                 => (string)($row['capacity'] ?? ''),
        'amenities'                => is_array($row['amenities'] ?? null) ? $row['amenities'] : [],
        'description'              => (string)($row['description'] ?? ''),
        'updated_at'               => (string)($row['updated_at'] ?? ''),
        'assigned_equipment_count' => (int)($row['assigned_equipment_count'] ?? 0),
    ];
}, $cprfFacilities);

$localCounts = [];
try {
    $localCounts = $pdo->query("
        SELECT cprf_facility_id AS fid,
               COUNT(*) AS on_loan,
               SUM(CASE WHEN cprf_custody_status = 'LOAN_RETURN_PENDING' THEN 1 ELSE 0 END) AS ret_pending
        FROM utility_assets
        WHERE cprf_facility_id IS NOT NULL
        GROUP BY cprf_facility_id
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $localCounts = [];
}
$countsById = [];
foreach ($localCounts as $r) {
    $countsById[(int)($r['fid'] ?? 0)] = [
        'on_loan'     => (int)($r['on_loan'] ?? 0),
        'ret_pending' => (int)($r['ret_pending'] ?? 0),
    ];
}
foreach ($cprfFacilities as &$f) {
    $c = $countsById[$f['id']] ?? ['on_loan' => 0, 'ret_pending' => 0];
    $f['local_on_loan'] = $c['on_loan'];
    $f['local_return_pending'] = $c['ret_pending'];
    $f['display_equipment_count'] = max($c['on_loan'], $f['assigned_equipment_count']);
    $f['is_citizen'] = false;
}
unset($f);

try {
    $citizensWithLoans = $pdo->query("
        SELECT citizen_user_id, requester_name, facility_name, COUNT(id) as active_loans, SUM(quantity) as total_items
        FROM external_asset_requests
        WHERE status = 'fulfilled' AND (source_system = 'Citizen Portal' OR citizen_user_id IS NOT NULL)
        GROUP BY citizen_user_id, requester_name, facility_name
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($citizensWithLoans as $cit) {
        $citId = (int)$cit['citizen_user_id'];
        $cprfFacilities[] = [
            'id' => -abs($citId > 0 ? $citId : crc32((string)$cit['requester_name'])),
            'name' => 'Citizen: ' . ($cit['requester_name'] ?: 'Unknown'),
            'status' => 'active',
            'status_label' => 'Active Loans',
            'is_assignable' => false,
            'location' => (string)$cit['facility_name'],
            'capacity' => '',
            'amenities' => [],
            'description' => 'Citizen borrower',
            'updated_at' => '',
            'assigned_equipment_count' => (int)$cit['total_items'],
            'local_on_loan' => (int)$cit['active_loans'],
            'local_return_pending' => 0,
            'display_equipment_count' => (int)$cit['active_loans'],
            'is_citizen' => true,
            'citizen_user_id' => $citId
        ];
    }
} catch (Throwable $e) {}

$assignableAssets = [];
$atFacility = [];
$events = [];
$selectedFacilityId = (int)($_GET['facility_id'] ?? ($cprfFacilities[0]['id'] ?? 0));
$selectedFacilityName = null;
foreach ($cprfFacilities as $f) {
    if ($f['id'] === $selectedFacilityId) {
        $selectedFacilityName = $f['name'];
        break;
    }
}

$selectedFacilityIsCitizen = false;
$selectedCitizenUserId = 0;
foreach ($cprfFacilities as $f) {
    if ($f['id'] === $selectedFacilityId) {
        if (!empty($f['is_citizen'])) {
            $selectedFacilityIsCitizen = true;
            $selectedCitizenUserId = $f['citizen_user_id'];
        }
        break;
    }
}

if ($selectedFacilityIsCitizen) {
    try {
        $atStmt = $pdo->prepare("
            SELECT r.id as req_id, r.quantity as loaned_qty, r.return_date,
                   a.id, a.asset_id AS asset_code, a.name, a.condition_status, t.name AS asset_type
            FROM external_asset_requests r
            JOIN utility_assets a ON a.id = r.fulfilled_asset_id
            LEFT JOIN asset_types t ON t.id = a.asset_type_id
            WHERE r.citizen_user_id = ? AND r.status = 'fulfilled'
        ");
        $atStmt->execute([$selectedCitizenUserId]);
        $atFacility = $atStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        $evtStmt = $pdo->prepare("
            SELECT id, request_ref, status, asset_type, quantity, notes,
                   review_notes, fulfilled_asset_id, created_at, updated_at
            FROM external_asset_requests
            WHERE citizen_user_id = ?
            ORDER BY updated_at DESC LIMIT 15
        ");
        $evtStmt->execute([$selectedCitizenUserId]);
        $events = $evtStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {}
} elseif ($selectedFacilityId > 0) {
    try {
        $atStmt = $pdo->prepare("
            SELECT a.id, a.asset_id AS asset_code, a.name, a.condition_status,
                   t.name AS asset_type, a.cprf_custody_status, a.responsible_office
            FROM utility_assets a JOIN asset_types t ON t.id = a.asset_type_id
            WHERE a.cprf_facility_id = ?
            ORDER BY a.name ASC
        ");
        $atStmt->execute([$selectedFacilityId]);
        $atFacility = $atStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $assignableStmt = $pdo->query("
            SELECT a.id, a.asset_id AS asset_code, a.name, a.condition_status,
                   t.name AS asset_type, a.responsible_office, a.quantity
            FROM utility_assets a JOIN asset_types t ON t.id = a.asset_type_id
            WHERE a.condition_status IN ('Operational','Needs Inspection')
              AND a.cprf_custody_status = 'WAREHOUSED'
            ORDER BY a.name ASC
        ");
        $assignableAssets = $assignableStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $evtStmt = $pdo->prepare("
            SELECT id, request_ref, status, asset_type, quantity, notes,
                   review_notes, fulfilled_asset_id, created_at, updated_at
            FROM external_asset_requests
            WHERE source_system = 'CPRF' AND cprf_facility_id = ?
            ORDER BY updated_at DESC LIMIT 15
        ");
        $evtStmt->execute([$selectedFacilityId]);
        $events = $evtStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $errors[] = 'Failed to load assignments panel: ' . htmlspecialchars($e->getMessage());
    }
}

$replacementCandidates = [];
try {
    $replacementCandidates = $pdo->query("
        SELECT a.id, a.asset_id AS asset_code, a.name, t.name AS asset_type, a.condition_status
        FROM utility_assets a JOIN asset_types t ON t.id = a.asset_type_id
        WHERE a.condition_status IN ('Operational','Needs Inspection')
          AND a.cprf_custody_status = 'WAREHOUSED'
        ORDER BY t.name ASC, a.name ASC
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    // ignore
}

// ── Count stats (for Tab-1 cards) ───────────────────────────────────────────
$countPending = 0; $countApproved = 0; $countFulfilled = 0; $countRejected = 0;
try {
    $counts = $pdo->query("SELECT status, COUNT(*) FROM external_asset_requests GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
    $countPending   = (int)($counts['pending'] ?? 0);
    $countApproved  = (int)($counts['approved'] ?? 0);
    $countFulfilled = (int)($counts['fulfilled'] ?? 0);
    $countRejected  = (int)($counts['rejected'] ?? 0);
} catch (Throwable $e) {}
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
    <title>CPRF Integration Hub | UMAN</title>
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif; }
        body {
            min-height: 100vh;
            display: flex;
            background: url("assets/images/cityhall.jpeg") center/cover no-repeat fixed;
            position: relative;
        }
        body::before {
            content:""; position: fixed; inset:0;
            backdrop-filter: blur(6px);
            background: rgba(0,0,0,0.35); z-index:0;
        }
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 24px 32px;
            transition: margin-left .25s ease;
            z-index: 1; position: relative;
        }
        .main-content.collapsed { margin-left: 90px; }
        .card {
            width: 100%; max-width: 1700px;
            background: rgba(255,255,255,0.88); backdrop-filter: blur(15px);
            border-radius: 18px; padding: 28px 30px;
            color: #000; box-shadow: 0 6px 20px rgba(0,0,0,.2);
            border: 1px solid rgba(255,255,255,.25);
        }

        /* ── Header ────────────────────────────────────────────────────── */
        .dashboard-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 18px; flex-wrap: wrap; gap: 14px;
        }
        .dashboard-header h1 {
            color:#2c3e50; font-size:24px; font-weight:700;
            display:flex; align-items:center; gap:12px;
        }
        .dashboard-header h1 i { color:#3762c8; font-size:26px; }
        .subtitle { color:#64748b; font-size:13px; margin-top:4px; }

        /* ── Flash messages ────────────────────────────────────────────── */
        .flash { border-radius: 10px; padding: 10px 14px; margin-bottom: 14px; font-size: 13px; }
        .flash.success { background:#ecfdf5; color:#047857; border:1px solid #a7f3d0; }
        .flash.error   { background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; }
        .flash.warning { background:#fffbeb; color:#92400e; border:1px solid #fde68a; }

        /* ── TOP-LEVEL TABS (Hub level) ────────────────────────────────── */
        .hub-tabs {
            display:flex; gap:2px; border-bottom: 2px solid #e2e8f0;
            margin-bottom: 20px; flex-wrap: wrap;
        }
        .hub-tab {
            padding: 11px 20px; border-radius: 10px 10px 0 0; cursor: pointer;
            font-size: 14px; font-weight: 600; color: #475569;
            border: 1px solid transparent; border-bottom: 2px solid transparent;
            margin-bottom: -2px;
        }
        .hub-tab:hover { background:#f8fafc; }
        .hub-tab.active {
            background:#fff; border-color:#e2e8f0; border-bottom-color:#fff;
            color:#059669; font-weight:700;
        }
        .hub-tab .icon { margin-right: 8px; }
        .hub-tab .count-chip {
            display:inline-block; padding:1px 9px; margin-left:8px; border-radius:999px;
            background:#e0e7ff; color:#3730a3; font-size:11px; font-weight:600;
        }
        .hub-panel { display:none; }
        .hub-panel.active { display: block; }
        /* Archived Requests button (beside Facility Assignments tab) */
        .hub-tab-archive {
            padding: 8px 16px; border-radius: 8px; cursor: pointer;
            font-size: 13px; font-weight: 600; color: #64748b;
            border: 1px solid #e2e8f0; background: #f8fafc;
            margin-bottom: 4px; margin-left: auto;
            display: inline-flex; align-items: center; gap: 6px;
            text-decoration: none; transition: all 0.2s ease;
        }
        .hub-tab-archive:hover { background:#fff7ed; border-color:#fed7aa; color:#9a3412; }
        .hub-tab-archive.arch-active {
            background: linear-gradient(135deg,#fff7ed,#ffedd5);
            border-color:#fb923c; color:#9a3412;
        }
        .hub-tab-archive .arch-chip {
            display:inline-block; padding:1px 7px; border-radius:999px;
            background:#fee2e2; color:#991b1b; font-size:11px; font-weight:700;
        }
        .btn-archive {
            background: linear-gradient(135deg, #fff7ed, #ffedd5);
            color: #9a3412; border: 1px solid #fdba74;
            padding: 6px 14px; border-radius: 8px; font-size: 12px;
            font-weight: 600; cursor: pointer; display: inline-flex;
            align-items: center; gap: 5px; transition: all 0.2s ease;
        }
        .btn-archive:hover { background: linear-gradient(135deg,#ffedd5,#fed7aa); border-color:#f97316; color:#7c2d12; transform: translateY(-1px); box-shadow: 0 3px 8px rgba(249,115,22,0.2); }
        .archived-banner {
            background: linear-gradient(135deg, #fff7ed, #ffedd5);
            border: 1px solid #fed7aa; border-radius: 10px;
            padding: 10px 16px; margin-bottom: 18px; font-size: 13px;
            color: #9a3412; display: flex; align-items: center; gap: 8px;
        }

        /* ── Tab 1: Asset Requests styles ──────────────────────────────── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white; border-radius: 12px; padding: 20px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            border-left: 5px solid #cbd5e1;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        .stat-card.stat-pending   { border-left-color: #f59e0b; }
        .stat-card.stat-approved  { border-left-color: #3762c8; }
        .stat-card.stat-fulfilled { border-left-color: #10b981; }
        .stat-card.stat-rejected  { border-left-color: #ef4444; }
        .stat-card h3 { font-size: 28px; font-weight: 700; color: #2c3e50; margin-bottom: 4px; }
        .stat-card p {
            font-size: 11px; color: #64748b; text-transform: uppercase;
            font-weight: 600; letter-spacing: 0.5px;
        }
        .filter-bar {
            display: flex; align-items: center; gap: 15px;
            margin-bottom: 25px; flex-wrap: wrap;
        }
        .filter-bar label {
            font-size: 14px; font-weight: 600; color: #2c3e50;
            display: flex; align-items: center; gap: 8px;
        }
        .filter-bar select {
            padding: 10px 16px; border: 1px solid #cbd5e1; border-radius: 8px;
            font-size: 14px; background: white; color: #2c3e50; cursor: pointer;
            transition: all 0.2s; min-width: 200px;
        }
        .filter-bar select:focus {
            outline: none; border-color: #3762c8;
            box-shadow: 0 0 0 3px rgba(55, 98, 200, 0.15);
        }
        .table-section {
            background: white; border-radius: 12px; padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05); overflow-x: auto;
        }
        .table-section h3 {
            font-size: 16px; color: #2c3e50; margin-bottom: 20px;
            display: flex; align-items: center; gap: 8px;
            border-bottom: 2px solid #f1f2f6; padding-bottom: 10px;
        }
        .table-section h3 i { color: #3762c8; }
        .req-table {
            width: 100%; border-collapse: collapse; font-size: 13px;
        }
        .req-table th {
            padding: 12px 16px; text-align: left; font-weight: 600;
            font-size: 11px; text-transform: uppercase; letter-spacing: 0.8px;
            color: #64748b; background: #f8fafc; border-bottom: 2px solid #e2e8f0;
        }
        .req-table td {
            padding: 14px 16px; border-bottom: 1px solid #f1f5f9;
            vertical-align: top; color: #334155;
        }
        .req-table tr:hover td { background: rgba(55, 98, 200, 0.03); }
        .req-table td strong { color: #1e293b; font-weight: 600; }
        .req-table td small { color: #94a3b8; font-size: 11px; }
        .req-table td em { color: #64748b; font-size: 12px; font-style: italic; }
        .badge {
            display:inline-block; padding:4px 12px; border-radius:999px;
            font-size:11px; font-weight:600; text-transform:uppercase;
            letter-spacing:.5px;
        }
        .badge.pending   { background:linear-gradient(135deg,#fef3c7,#fde68a); color:#92400e; border:1px solid #fbbf24; }
        .badge.approved  { background:linear-gradient(135deg,#dbeafe,#bfdbfe); color:#1e40af; border:1px solid #60a5fa; }
        .badge.fulfilled { background:linear-gradient(135deg,#d1fae5,#a7f3d0); color:#065f46; border:1px solid #34d399; }
        .badge.rejected  { background:linear-gradient(135deg,#fee2e2,#fecaca); color:#991b1b; border:1px solid #f87171; }
        .badge.available { background:#dcfce7; color:#166534; }
        .badge.maintenance { background:#fef3c7; color:#92400e; }
        .badge.offline   { background:#fee2e2; color:#991b1b; }
        .badge.count     { background:#e0e7ff; color:#3730a3; margin-left:6px; }
        .badge.ret-pending { background:#fecaca; color:#991b1b; margin-left:4px; }
        .badge.cant-assign { background:#e5e7eb; color:#475569; margin-left:6px; }
        .badge.avail-sufficient { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #166534; border: 1px solid #86efac; }
        .badge.avail-partial    { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e; border: 1px solid #fcd34d; }
        .badge.avail-none       { background: linear-gradient(135deg, #fee2e2, #fecaca); color: #991b1b; border: 1px solid #fca5a5; }
        .avail-qty-sub { font-size: 11px; margin-top: 3px; }
        .avail-match-chips { display: flex; gap: 4px; flex-wrap: wrap; margin-top: 5px; }
        .avail-chip { font-size: 10px; background: #f1f5f9; border: 1px solid #e2e8f0; padding: 2px 6px; border-radius: 4px; color: #475569; }
        .dark-theme .avail-chip { background: #1e293b; border-color: #334155; color: #94a3b8; }
        .action-form {
            background: #f8fafc; border-radius: 10px; padding: 12px;
            margin-bottom: 8px; border: 1px solid #e2e8f0;
            transition: box-shadow 0.2s ease;
        }
        .action-form:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .action-form:last-child { margin-bottom: 0; }
        .action-form textarea {
            width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px;
            font-size: 13px; resize: vertical; transition: border-color 0.2s;
            margin-bottom: 8px; background: white;
        }
        .action-form textarea:focus {
            outline: none; border-color: #3762c8;
            box-shadow: 0 0 0 3px rgba(55, 98, 200, 0.1);
        }
        .action-form select {
            width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px;
            font-size: 13px; background: white; color: #334155; cursor: pointer;
            margin-bottom: 8px; transition: border-color 0.2s;
        }
        .action-form select:focus {
            outline: none; border-color: #3762c8;
            box-shadow: 0 0 0 3px rgba(55, 98, 200, 0.1);
        }
        .btn {
            padding: 8px 18px; border-radius: 8px; font-weight: 600; font-size: 13px;
            border: 1px solid #cbd5e1; cursor: pointer; transition: all 0.25s ease;
            display: inline-flex; align-items: center; gap: 6px;
            background: #fff; color: #334155;
        }
        .btn-primary { background: #3762c8; color: white; border-color: #2851b0; }
        .btn-primary:hover { background: #2851b0; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(55,98,200,.35); }
        .btn-primary:disabled { opacity:.55; cursor:not-allowed; background:#93b0ea; border-color:#93b0ea; transform:none; box-shadow:none; }
        .btn-danger  { background: #ef4444; color: white; border-color: #dc2626; }
        .btn-danger:hover { background: #dc2626; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(239,68,68,.3); }
        .btn-success { background: #10b981; color: white; border-color: #059669; }
        .btn-success:hover { background: #059669; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(16,185,129,.3); }
        .btn-warning { background: #f59e0b; color: white; border-color: #b45309; }
        .btn-warning:hover { background: #b45309; transform: translateY(-1px); }
        .btn-sm { padding:5px 10px; font-size:12px; border-radius:6px; }
        .btn-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
        .empty-state i { font-size: 48px; margin-bottom: 16px; opacity: 0.5; }
        .empty-state p { font-size: 15px; font-weight: 500; }
        .fulfilled-link {
            display: inline-flex; align-items: center; gap: 4px;
            font-size: 11px; color: #10b981; font-weight: 500; margin-top: 4px;
        }
        .no-action { color: #94a3b8; font-size: 13px; font-style: italic; }

        /* ── Tab 2: Facility Assignments styles ────────────────────────── */
        .layout {
            display: grid; grid-template-columns: 340px 1fr;
            gap: 16px;
        }
        .facility-list {
            background: #fff; border-radius: 12px; border: 1px solid #e2e8f0;
            padding: 10px; max-height: calc(100vh - 200px); overflow-y: auto;
        }
        .facility-list .search {
            position: sticky; top: 0; background: #fff; padding-bottom: 8px; margin-bottom: 8px;
            border-bottom: 1px solid #e2e8f0;
        }
        .facility-list input[type=search] {
            width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1;
            border-radius: 8px; font-size: 13px;
        }
        .facility-item {
            display: block; border-radius: 10px; padding: 10px 12px;
            margin-bottom: 6px; border: 1px solid #e2e8f0; background: #f8fafc;
            cursor: pointer; transition: all .15s ease; color: #0f172a;
            text-decoration: none;
        }
        .facility-item:hover { border-color:#10b981; background:#ecfdf5; }
        .facility-item.active {
            border-color: #059669; background: #d1fae5;
            box-shadow: inset 3px 0 0 #059669;
        }
        .facility-item .name { font-weight:600; font-size:14px; color:#1e293b; }
        .facility-item .meta {
            font-size:11px; color:#64748b; margin-top:3px;
            display:flex; justify-content:space-between; gap:8px;
        }
        .right-panel { min-width: 0; }
        .tabs {
            display:flex; gap:2px; border-bottom: 2px solid #e2e8f0; margin-bottom: 14px; flex-wrap: wrap;
        }
        .tab {
            padding: 9px 14px; border-radius: 8px 8px 0 0; cursor: pointer;
            font-size: 13px; font-weight: 500; color: #475569;
            border: 1px solid transparent; border-bottom: 2px solid transparent;
            margin-bottom: -2px;
        }
        .tab:hover { background:#f8fafc; }
        .tab.active {
            background:#fff; border-color:#e2e8f0; border-bottom-color:#fff;
            color:#059669; font-weight:600;
        }
        .tab .count-chip {
            display:inline-block; padding:1px 7px; margin-left:6px; border-radius:999px;
            background:#e0f2fe; color:#0369a1; font-size:11px;
        }
        .tab-panel { display:none; }
        .tab-panel.active { display: block; }

        .panel-toolbar {
            display:flex; gap:8px; align-items:center; justify-content:space-between;
            margin-bottom: 10px; flex-wrap: wrap;
        }
        .panel-toolbar input[type=search] {
            flex: 1 1 240px; padding: 8px 10px; border: 1px solid #cbd5e1;
            border-radius: 8px; font-size: 13px;
        }
        .table {
            width: 100%; border-collapse: collapse; font-size: 13px;
            background: #fff; border-radius: 10px; overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        .table th, .table td {
            padding: 9px 12px; text-align: left; border-bottom: 1px solid #eef2f7;
        }
        .table th {
            background: #f1f5f9; font-weight: 600; color: #475569;
            font-size: 12px; text-transform: uppercase; letter-spacing: .03em;
        }
        .table tr:last-child td { border-bottom: none; }
        .table tr:hover td { background: #f8fafc; }
        .code { font-family: ui-monospace, Menlo, monospace; font-size:12px; color:#334155; }
        .muted { color:#64748b; }
        .row-actions { display:flex; gap:6px; flex-wrap:wrap; }
        .inline-form { display:inline; }
        .form-grid {
            display:grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;
        }
        .form-grid label { font-size:12px; color:#475569; display:block; font-weight:500; margin-bottom:4px; }
        .form-grid input, .form-grid select, .form-grid textarea {
            width:100%; padding: 6px 8px; font-size:13px;
            border:1px solid #cbd5e1; border-radius: 6px;
        }
        .form-grid .full { grid-column: 1 / -1; }

        .event-card {
            padding: 10px 12px; border-radius: 10px; border: 1px solid #e2e8f0;
            background: #fff; margin-bottom: 8px; font-size: 13px;
        }
        .event-card .head {
            display:flex; justify-content:space-between; align-items:center;
            gap:8px; margin-bottom: 4px;
        }
        .event-card .ref { font-weight: 600; color:#0f172a; }

        /* ── Responsive ────────────────────────────────────────────────── */
        @media (max-width: 1100px) {
            .layout { grid-template-columns: 1fr; }
            .facility-list { max-height: 380px; }
        }
        @media (max-width: 992px) {
            .main-content { margin-left: 0 !important; padding: 16px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .form-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 600px) {
            .stats-grid { grid-template-columns: 1fr; }
            .dashboard-header { flex-direction: column; align-items: flex-start; }
        }

        /* Custom Modal Styles */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 2000; justify-content: center; align-items: center; backdrop-filter: blur(4px); }
        .modal.open { display: flex; }
        .modal-content { background: white; width: 90%; max-width: 450px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15); overflow: hidden; animation: modalFadeIn 0.3s ease; }
        @keyframes modalFadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        .modal-header { padding: 20px 24px; background: #f8f9fa; border-bottom: 1px solid #edf2f7; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { font-size: 18px; color: #2c3e50; margin: 0; }
        .modal-close { background: transparent; border: none; font-size: 18px; cursor: pointer; color: #64748b; }
        .modal-body { padding: 24px; font-size: 15px; color: #334155; }
        .modal-footer { padding: 16px 24px; background: #f8f9fa; border-top: 1px solid #edf2f7; display: flex; justify-content: flex-end; gap: 12px; }
    </style>
</head>
<body>

<?php include 'includes/utilities_sidebar.php'; ?>

<main class="main-content" id="mainContent">
    <div class="card">

        <!-- Header -->
        <div class="dashboard-header">
            <div>
                <h1><i class="fas fa-sitemap"></i> CPRF Integration Hub</h1>
                <div class="subtitle">
                    Two-way sync with Barangay Culiat Facilities Reservation System:
                    review asset requests &amp; directly assign equipment to facilities.
                </div>
            </div>
            <div style="display:flex; gap:8px; align-items:center;">
                <span class="badge <?= $cprfFetchError ? 'offline' : 'available'; ?>">
                    <i class="fas fa-plug"></i>
                    <?= $cprfFetchError ? ('CPRF offline: ' . h($cprfFetchError)) : 'CPRF live sync'; ?>
                </span>
                <form method="POST" style="margin:0; display:inline;">
                    <input type="hidden" name="action" value="refresh_facilities">
                    <button class="btn btn-sm" type="submit"><i class="fas fa-sync-alt"></i> Refresh list</button>
                </form>
            </div>
        </div>

        <?php if ($cprfFetchError): ?>
            <div class="flash warning">
                <i class="fas fa-triangle-exclamation"></i>
                Could not fetch live facility list from CPRF:
                <strong><?= h($cprfFetchError); ?></strong>.
                Check <code>CPRF_BASE_URL</code> + <code>CPRF_INBOUND_API_KEY</code> in your env.
            </div>
        <?php endif; ?>

        <?php foreach ($successes as $s): ?>
            <div class="flash success"><i class="fas fa-circle-check"></i> <?= $s; ?></div>
        <?php endforeach; ?>
        <?php foreach ($errors as $e): ?>
            <div class="flash error"><i class="fas fa-circle-xmark"></i> <?= $e; ?></div>
        <?php endforeach; ?>

        <!-- ── TOP-LEVEL HUB TABS ────────────────────────────────────── -->
        <div class="hub-tabs" role="tablist">
            <div class="hub-tab active" data-hub-tab="hub-requests" role="tab">
                <i class="fas fa-inbox icon"></i> Asset Requests
                <span class="count-chip"><?= $countPending + $countApproved; ?> open</span>
            </div>
            <div class="hub-tab" data-hub-tab="hub-assignments" role="tab">
                <i class="fas fa-users-cog icon"></i> Citizen & Facility Assignments
                <span class="count-chip"><?= count($cprfFacilities); ?> entities</span>
            </div>
            <a href="?archived=1<?= $filter !== '' ? '&status='.urlencode($filter) : ''; ?>"
               class="hub-tab-archive<?= $showArchived ? ' arch-active' : ''; ?>"
               title="View archived (completed) requests">
                <i class="fas fa-archive"></i> Archived Requests
                <?php if ($countArchived > 0): ?>
                    <span class="arch-chip"><?= $countArchived; ?></span>
                <?php endif; ?>
            </a>
        </div>

        <!-- ══════════════════════════════════════════════════════════════
             HUB PANEL 1 — Asset Requests
             ══════════════════════════════════════════════════════════ -->
        <div id="hub-requests" class="hub-panel active">

            <?php if ($showArchived): ?>
            <div class="archived-banner">
                <i class="fas fa-archive"></i>
                <span>Viewing <strong>archived requests</strong>. <a href="?" style="color:#b45309; font-weight:600;">&#8592; Back to active requests</a></span>
            </div>
            <?php endif; ?>

            <?php if (!$showArchived): ?>
            <div class="stats-grid">
                <div class="stat-card stat-pending">
                    <h3><?= $countPending; ?></h3>
                    <p><i class="fas fa-clock"></i> Pending</p>
                </div>
                <div class="stat-card stat-approved">
                    <h3><?= $countApproved; ?></h3>
                    <p><i class="fas fa-thumbs-up"></i> Approved</p>
                </div>
                <div class="stat-card stat-fulfilled">
                    <h3><?= $countFulfilled; ?></h3>
                    <p><i class="fas fa-check-double"></i> Fulfilled</p>
                </div>
                <div class="stat-card stat-rejected">
                    <h3><?= $countRejected; ?></h3>
                    <p><i class="fas fa-times-circle"></i> Rejected</p>
                </div>
            </div>
            <?php endif; ?>

            <div class="filter-bar">
                <form method="GET" style="margin:0; width:100%; display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                    <?php if ($showArchived): ?>
                        <input type="hidden" name="archived" value="1">
                    <?php endif; ?>
                    <label style="margin:0; white-space:nowrap;"><i class="fas fa-filter"></i> Filter Source:</label>
                    <select name="source" onchange="this.form.submit()" class="form-control" style="flex:1; min-width:170px;">
                        <option value="">All Sources (CPRF & Citizen)</option>
                        <option value="citizen" <?= $sourceFilter === 'citizen' ? 'selected' : ''; ?>>Citizen Requests</option>
                        <option value="cprf" <?= $sourceFilter === 'cprf' ? 'selected' : ''; ?>>CPRF Facility Requests</option>
                    </select>

                    <label style="margin:0; white-space:nowrap; margin-left:10px;"><i class="fas fa-tasks"></i> Status:</label>
                    <select name="status" onchange="this.form.submit()" class="form-control" style="flex:1; min-width:170px;">
                        <option value="">All Statuses</option>
                        <?php 
                        $statusOptions = $showArchived ? ['fulfilled', 'rejected', 'returned'] : ['pending', 'approved', 'fulfilled'];
                        foreach ($statusOptions as $s): 
                        ?>
                            <option value="<?= $s; ?>" <?= $filter === $s ? 'selected' : ''; ?>><?= ucfirst($s); ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <div class="table-section">
                <h3><i class="fas fa-list-alt"></i> <?= $showArchived ? 'Archived Asset Requests' : 'Asset Requests Hub'; ?></h3>

                <?php if (empty($requests)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No requests found matching your filter criteria.</p>
                    </div>
                <?php else: ?>
                    <?php if (!$showArchived): ?>
                    <table class="req-table">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Source & Requester / Location</th>
                                <th>Asset Type</th>
                                <th>Requested Qty</th>
                                <th>Available in Stock</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $req): ?>
                                <?php $avail = get_request_asset_availability($req['asset_type'], (int)$req['quantity'], $allAvailableAssets, $req['requested_asset_code'] ?? null); ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($req['request_ref']); ?></strong><br>
                                        <small><?= htmlspecialchars($req['created_at']); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($req['source_system'] === 'Citizen Portal' || !empty($req['citizen_user_id'])): ?>
                                            <span class="badge" style="background:linear-gradient(135deg,#e0f2fe,#bae6fd); color:#0369a1; border:1px solid #7dd3fc; font-weight:700; margin-bottom:4px;">
                                                <i class="fas fa-user-circle"></i> Citizen Request
                                            </span><br>
                                            <strong style="color:#1e293b;"><i class="fas fa-user"></i> <?= htmlspecialchars($req['requester_name'] ?: 'Citizen Resident'); ?></strong><br>
                                            <?php if (!empty($req['requester_contact'])): ?>
                                                <small style="color:#64748b;"><i class="fas fa-address-card"></i> <?= htmlspecialchars($req['requester_contact']); ?></small><br>
                                            <?php endif; ?>
                                            <small style="color:#334155;">📍 Location: <strong><?= htmlspecialchars($req['facility_name']); ?></strong></small>
                                            <?php if (!empty($req['return_date'])): ?>
                                                <br><small style="color:#b45309;"><i class="fas fa-calendar-check"></i> Return By: <strong><?= htmlspecialchars($req['return_date']); ?></strong></small>
                                            <?php endif; ?>
                                            <?php if (!empty($req['event_purpose'])): ?>
                                                <br><small style="color:#475569;">🎯 Purpose: <em><?= htmlspecialchars($req['event_purpose']); ?></em></small>
                                            <?php endif; ?>
                                            <?php if (!empty($req['notes'])): ?>
                                                <br><em style="color:#64748b; font-size:11px;">"<?= htmlspecialchars($req['notes']); ?>"</em>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge" style="background:linear-gradient(135deg,#f3e8ff,#e9d5ff); color:#6b21a8; border:1px solid #c084fc; font-weight:700; margin-bottom:4px;">
                                                <i class="fas fa-building"></i> CPRF Facility
                                            </span><br>
                                            <strong><?= htmlspecialchars($req['facility_name']); ?></strong><br>
                                            <small>CPRF ID: <?= (int)$req['cprf_facility_id']; ?></small>
                                            <?php if (!empty($req['notes'])): ?><br><em><?= htmlspecialchars($req['notes']); ?></em><?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($req['asset_type']); ?></strong>
                                        <?php if (!empty($req['requested_asset_code'])): ?>
                                            <div style="margin-top: 6px; font-size: 11px; color: #0f766e; background: #ccfbf1; padding: 4px 8px; border-radius: 4px; display: inline-block;">
                                                <i class="fas fa-tag"></i> <strong><?= htmlspecialchars($req['requested_asset_code']); ?></strong>
                                                <?php if (!empty($req['requested_asset_name'])): ?>
                                                    &mdash; <?= htmlspecialchars($req['requested_asset_name']); ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong style="font-size:14px; color:#1e293b;"><?= (int)$req['quantity']; ?></strong> <span class="muted" style="font-size:12px;">unit<?= ((int)$req['quantity'] !== 1) ? 's' : ''; ?></span>
                                    </td>
                                    <td>
                                        <?php if ($req['status'] === 'fulfilled'): ?>
                                            <span class="badge fulfilled"><i class="fas fa-check-circle"></i> Fulfilled</span>
                                            <?php if (!empty($req['fulfilled_asset_name'])): ?>
                                                <div class="fulfilled-link"><i class="fas fa-link"></i> <?= htmlspecialchars($req['fulfilled_asset_name']); ?></div>
                                            <?php endif; ?>
                                        <?php elseif ($req['status'] === 'rejected'): ?>
                                            <span class="badge rejected"><i class="fas fa-ban"></i> Closed</span>
                                        <?php else: ?>
                                            <?php if ($avail['is_sufficient']): ?>
                                                <span class="badge avail-sufficient">
                                                    <i class="fas fa-check-circle"></i> <?= $avail['available_qty']; ?> In Stock
                                                </span>
                                                <div class="avail-qty-sub" style="color:#16a34a; font-weight:500;">
                                                    <i class="fas fa-cubes"></i> Sufficient stock (<?= $avail['available_qty']; ?> of <?= $avail['requested_qty']; ?>)
                                                </div>
                                            <?php elseif ($avail['is_partial']): ?>
                                                <span class="badge avail-partial">
                                                    <i class="fas fa-exclamation-triangle"></i> <?= $avail['available_qty']; ?> In Stock
                                                </span>
                                                <div class="avail-qty-sub" style="color:#d97706; font-weight:500;">
                                                    <i class="fas fa-cubes"></i> Partial shortage (<?= $avail['available_qty']; ?> of <?= $avail['requested_qty']; ?>)
                                                </div>
                                            <?php else: ?>
                                                <span class="badge avail-none">
                                                    <i class="fas fa-times-circle"></i> 0 In Stock
                                                </span>
                                                <div class="avail-qty-sub" style="color:#dc2626; font-weight:500;">
                                                    <i class="fas fa-times"></i> No matching units available
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($avail['matching_assets'])): ?>
                                                <div class="avail-match-chips" title="Matching available warehouse units">
                                                    <?php foreach (array_slice($avail['matching_assets'], 0, 3) as $m): ?>
                                                        <span class="avail-chip" title="<?= htmlspecialchars($m['name'] . ' (' . $m['condition_status'] . ')'); ?>">
                                                            <?= htmlspecialchars($m['asset_id']); ?>: <strong><?= intval($m['quantity']); ?> qty</strong>
                                                        </span>
                                                    <?php endforeach; ?>
                                                    <?php if (count($avail['matching_assets']) > 3): ?>
                                                        <span class="avail-chip">+<?= count($avail['matching_assets']) - 3; ?> more</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= htmlspecialchars($req['status']); ?>"><?= ucfirst($req['status']); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($req['status'] === 'pending'): ?>
                                            <div class="action-form">
                                                <form method="POST" onsubmit="event.preventDefault(); openConfirmModal(this, 'Approve Request', 'Are you sure you want to approve this request?', 'btn-primary');">
                                                    <input type="hidden" name="id" value="<?= (int)$req['id']; ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <?php if ($avail['is_sufficient']): ?>
                                                        <textarea name="review_notes" rows="2" placeholder="Approval notes (optional)"></textarea>
                                                        <div class="btn-actions">
                                                            <button class="btn btn-primary" type="submit"><i class="fas fa-check"></i> Approve</button>
                                                        </div>
                                                    <?php else: ?>
                                                        <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:8px 10px; margin-bottom:8px;">
                                                            <div style="font-size:11px; font-weight:600; color:#991b1b; display:flex; align-items:center; gap:5px;">
                                                                <i class="fas fa-ban"></i> Cannot Approve (Stock Shortage)
                                                            </div>
                                                            <div style="font-size:11px; color:#b91c1c; margin-top:2px; line-height:1.35;">
                                                                <?php if ($avail['available_qty'] > 0): ?>
                                                                    Only <strong><?= $avail['available_qty']; ?></strong> of <strong><?= (int)$req['quantity']; ?></strong> units available in inventory.
                                                                <?php else: ?>
                                                                    <strong>0</strong> units available in inventory.
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <div class="btn-actions">
                                                            <button class="btn btn-primary" type="button" disabled style="opacity:0.5; cursor:not-allowed; background:#94a3b8; border-color:#94a3b8;"><i class="fas fa-lock"></i> Approval Blocked</button>
                                                        </div>
                                                    <?php endif; ?>
                                                </form>
                                            </div>
                                            <div class="action-form">
                                                <form method="POST" onsubmit="event.preventDefault(); openConfirmModal(this, 'Reject Request', 'Are you sure you want to reject this request?', 'btn-danger');">
                                                    <input type="hidden" name="id" value="<?= (int)$req['id']; ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <textarea name="review_notes" rows="1" placeholder="Rejection reason (e.g. stock unavailable)"></textarea>
                                                    <div class="btn-actions">
                                                        <button class="btn btn-danger" type="submit"><i class="fas fa-times"></i> Reject</button>
                                                    </div>
                                                </form>
                                            </div>
                                        <?php elseif (in_array($req['status'], ['pending', 'approved'], true)): ?>
                                            <?php
                                                $reqSpecificCode = trim((string)($req['requested_asset_code'] ?? ''));
                                                $autoSelectedAssetId = 0;
                                                $autoSelectedAssetObj = null;
                                                if (!empty($avail['matching_assets']) && $reqSpecificCode !== '') {
                                                    foreach ($avail['matching_assets'] as $a) {
                                                        $aIdStr = trim((string)($a['asset_id'] ?? ''));
                                                        if ($aIdStr !== '' && (
                                                            strcasecmp($reqSpecificCode, $aIdStr) === 0 ||
                                                            stripos($reqSpecificCode, $aIdStr) === 0 ||
                                                            stripos($aIdStr, $reqSpecificCode) === 0
                                                        )) {
                                                            $autoSelectedAssetId = (int)$a['id'];
                                                            $autoSelectedAssetObj = $a;
                                                            break;
                                                        }
                                                    }
                                                }
                                            ?>
                                            <div class="action-form">
                                                <form method="POST" onsubmit="event.preventDefault(); openConfirmModal(this, 'Fulfill Request', 'Are you sure you want to mark this request as fulfilled?', 'btn-success');">
                                                    <input type="hidden" name="id" value="<?= (int)$req['id']; ?>">
                                                    <input type="hidden" name="action" value="fulfill">
                                                    
                                                    <?php if ($reqSpecificCode !== '' && $autoSelectedAssetId > 0): ?>
                                                        <input type="hidden" name="fulfilled_asset_id" value="<?= $autoSelectedAssetId; ?>">
                                                        <div style="padding:8px 10px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; margin-bottom:8px;">
                                                            <div style="font-size:11px; color:#166534; font-weight:700; display:flex; align-items:center; gap:5px;">
                                                                <i class="fas fa-lock"></i> Fixed Specific Asset (Automatic)
                                                            </div>
                                                            <div style="font-size:12px; font-weight:700; color:#065f46; margin-top:3px;">
                                                                <?= htmlspecialchars($autoSelectedAssetObj['asset_id'] . ' — ' . $autoSelectedAssetObj['name']); ?>
                                                            </div>
                                                            <div style="font-size:11px; color:#047857; margin-top:2px;">
                                                                <?= (int)$autoSelectedAssetObj['quantity']; ?> unit<?= ((int)$autoSelectedAssetObj['quantity'] !== 1) ? 's' : ''; ?> available in stock
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <?php if ($reqSpecificCode !== ''): ?>
                                                            <div style="padding:6px 8px; background:#fffbe6; border:1px solid #ffe58f; border-radius:6px; margin-bottom:6px; font-size:11px; color:#873800;">
                                                                <i class="fas fa-exclamation-triangle"></i> Requested unit (<strong><?= htmlspecialchars($reqSpecificCode); ?></strong>) unavailable. Select from stock:
                                                            </div>
                                                        <?php endif; ?>
                                                        <select name="fulfilled_asset_id" required>
                                                            <option value="">Select asset to fulfill…</option>
                                                            <?php if (!empty($avail['matching_assets'])): ?>
                                                                <?php foreach ($avail['matching_assets'] as $a): ?>
                                                                    <option value="<?= (int)$a['id']; ?>">
                                                                        <?= htmlspecialchars($a['asset_id'] . ' — ' . $a['name'] . ' (' . $a['quantity'] . ' available)'); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            <?php else: ?>
                                                                <option value="" disabled>No matching assets available in stock</option>
                                                            <?php endif; ?>
                                                        </select>
                                                    <?php endif; ?>
                                                    
                                                    <textarea name="review_notes" rows="2" placeholder="Fulfillment notes"></textarea>
                                                    <button class="btn btn-success" type="submit"><i class="fas fa-check-double"></i> Mark Fulfilled</button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <div style="display:flex; flex-direction:column; gap:6px; align-items:flex-start;">
                                                <?php if (empty($req['is_archived'])): ?>
                                                <span class="no-action" style="font-size:12px;">— No actions available</span>
                                                <?php else: ?>
                                                <span class="no-action" style="font-size:12px; color:#fb923c;"><i class="fas fa-archive"></i> Archived</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <table class="req-table archived-table" style="opacity: 0.8;">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Source & Requester / Location</th>
                                <th>Asset Type</th>
                                <th>Qty</th>
                                <th>Resolution</th>
                                <th>Archived At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $req): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($req['request_ref']); ?></strong><br>
                                        <small><?= htmlspecialchars($req['created_at']); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($req['source_system'] === 'Citizen Portal' || !empty($req['citizen_user_id'])): ?>
                                            <span class="badge" style="background:linear-gradient(135deg,#e0f2fe,#bae6fd); color:#0369a1; border:1px solid #7dd3fc; font-weight:700; margin-bottom:2px; font-size:10px;">
                                                <i class="fas fa-user-circle"></i> Citizen Request
                                            </span><br>
                                            <strong><i class="fas fa-user"></i> <?= htmlspecialchars($req['requester_name'] ?: 'Citizen'); ?></strong><br>
                                            <small>📍 <?= htmlspecialchars($req['facility_name']); ?></small>
                                            <?php if (!empty($req['return_date'])): ?>
                                                <br><small style="color:#b45309;"><i class="fas fa-calendar-check"></i> Return By: <strong><?= htmlspecialchars($req['return_date']); ?></strong></small>
                                            <?php endif; ?>
                                            <?php if (!empty($req['notes'])): ?><br><em style="font-size:11px;">"<?= htmlspecialchars($req['notes']); ?>"</em><?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge" style="background:linear-gradient(135deg,#f3e8ff,#e9d5ff); color:#6b21a8; border:1px solid #c084fc; font-weight:700; margin-bottom:2px; font-size:10px;">
                                                <i class="fas fa-building"></i> CPRF Facility
                                            </span><br>
                                            <span style="font-weight: 500;"><?= htmlspecialchars($req['facility_name']); ?></span><br>
                                            <small>CPRF ID: <?= (int)$req['cprf_facility_id']; ?></small>
                                            <?php if (!empty($req['notes'])): ?><br><em style="font-size:11px;"><?= htmlspecialchars($req['notes']); ?></em><?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($req['asset_type']); ?></strong>
                                        <?php if (!empty($req['requested_asset_code'])): ?>
                                            <div style="margin-top: 6px; font-size: 11px; color: #0f766e; background: #ccfbf1; padding: 4px 8px; border-radius: 4px; display: inline-block;">
                                                <i class="fas fa-tag"></i> <strong><?= htmlspecialchars($req['requested_asset_code']); ?></strong>
                                                <?php if (!empty($req['requested_asset_name'])): ?>
                                                    &mdash; <?= htmlspecialchars($req['requested_asset_name']); ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong style="font-size:14px;"><?= (int)$req['quantity']; ?></strong> <span style="font-size:12px;">unit<?= ((int)$req['quantity'] !== 1) ? 's' : ''; ?></span>
                                    </td>
                                    <td>
                                        <?php if ($req['status'] === 'fulfilled'): ?>
                                            <span class="badge fulfilled"><i class="fas fa-check-circle"></i> Fulfilled</span>
                                            <?php if (!empty($req['fulfilled_asset_name'])): ?>
                                                <div class="fulfilled-link" style="font-size:11px; margin-top:3px;"><i class="fas fa-link"></i> <?= htmlspecialchars($req['fulfilled_asset_name']); ?></div>
                                            <?php endif; ?>
                                        <?php elseif ($req['status'] === 'returned'): ?>
                                            <span class="badge fulfilled" style="background:#064e3b; color:#ecfdf5;"><i class="fas fa-undo"></i> Returned</span>
                                        <?php elseif ($req['status'] === 'rejected'): ?>
                                            <span class="badge rejected"><i class="fas fa-ban"></i> Rejected</span>
                                        <?php else: ?>
                                            <span class="badge archived"><i class="fas fa-archive"></i> Archived</span>
                                        <?php endif; ?>
                                        <?php if (!empty($req['review_notes'])): ?>
                                            <div style="font-size:11px; margin-top:6px; font-style:italic; opacity:0.8;">"<?= htmlspecialchars($req['review_notes']); ?>"</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="font-size:12px; white-space:nowrap;"><i class="far fa-clock"></i> <?= htmlspecialchars($req['archived_at'] ?? $req['updated_at']); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════
             HUB PANEL 2 — Facility Assignments (split layout)
             ══════════════════════════════════════════════════════════ -->
        <div id="hub-assignments" class="hub-panel">

            <div class="layout">

                <aside class="facility-list" id="facilityList">
                    <div class="search">
                        <input type="search" id="facilitySearch" placeholder="Search facilities…" aria-label="Search facilities">
                    </div>
                    <?php if ($cprfFacilities === []): ?>
                        <div class="muted" style="padding:12px; text-align:center; font-size:13px;">
                            No facilities returned from CPRF.
                        </div>
                    <?php else: ?>
                        <?php foreach ($cprfFacilities as $f):
                            $active = $f['id'] === $selectedFacilityId ? ' active' : '';
                            $statusBadge = match ($f['status']) {
                                'maintenance' => 'maintenance',
                                'offline'     => 'offline',
                                default       => 'available',
                            };
                            $assignBadge = $f['is_assignable'] ? '' : '<span class="badge cant-assign" title="Offline — not assignable">Closed</span>';
                        ?>
                            <a class="facility-item<?= $active; ?>"
                               href="?<?= $filter !== '' ? 'status=' . urlencode($filter) . '&' : ''; ?>facility_id=<?= (int)$f['id']; ?>#hub-assignments"
                               data-id="<?= (int)$f['id']; ?>"
                               data-name="<?= h($f['name']); ?>">
                                <div class="name">
                                    <?= h($f['name']); ?>
                                    <?= $assignBadge; ?>
                                </div>
                                <div class="meta">
                                    <span>
                                        <span class="badge <?= $statusBadge; ?>"><?= h($f['status_label']); ?></span>
                                        <?php if ($f['display_equipment_count'] > 0): ?>
                                            <span class="badge count"><?= (int)$f['display_equipment_count']; ?> items</span>
                                        <?php endif; ?>
                                        <?php if (!empty($f['local_return_pending'])): ?>
                                            <span class="badge ret-pending" title="Return requested"><?= (int)$f['local_return_pending']; ?> return</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php if ($f['location'] !== ''): ?>
                                    <div class="meta"><span>📍 <?= h(mb_strimwidth($f['location'], 0, 52, '…')); ?></span></div>
                                <?php endif; ?>
                                <?php if ($f['amenities'] !== []): ?>
                                    <div class="meta">
                                        <span class="muted">🛠 <?= h(implode(', ', array_slice($f['amenities'], 0, 3))); ?><?= count($f['amenities']) > 3 ? ' …+' . (count($f['amenities']) - 3) : ''; ?></span>
                                    </div>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </aside>

                <section class="right-panel">
                    <?php if ($selectedFacilityId === 0): ?>
                        <div class="muted" style="padding:24px 12px; text-align:center;">
                            Select a facility from the left list to start managing equipment.
                        </div>
                    <?php else: ?>
                        <div style="margin-bottom: 12px;">
                            <h3 style="color:#0f172a; font-size:17px; font-weight:600;">
                                <?= h($selectedFacilityName ?? ('Facility #' . $selectedFacilityId)); ?>
                            </h3>
                            <div class="muted" style="font-size:12px;">
                                Facility ID #<?= (int)$selectedFacilityId; ?>
                            </div>
                        </div>

                        <div class="tabs" role="tablist">
                            <?php if (empty($selectedFacilityIsCitizen)): ?>
                                <div class="tab active" data-tab="tab-assignable" role="tab">
                                    📦 Assignable Assets <span class="count-chip"><?= count($assignableAssets); ?></span>
                                </div>
                                <div class="tab" data-tab="tab-at-facility" role="tab">
                                    ✅ At This Facility <span class="count-chip"><?= count($atFacility); ?></span>
                                </div>
                            <?php else: ?>
                                <div class="tab active" data-tab="tab-at-facility" role="tab">
                                    ✅ Active Loans <span class="count-chip"><?= count($atFacility); ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="tab" data-tab="tab-events" role="tab">
                                📋 Activity <span class="count-chip"><?= count($events); ?></span>
                            </div>
                        </div>

                        <div class="assignment-content">
                        <!-- SUB-TAB 1: Assignable Assets -->
                        <?php if (empty($selectedFacilityIsCitizen)): ?>
                        <div id="tab-assignable" class="tab-panel active">
                            <form method="POST" id="form-assign">
                                <input type="hidden" name="action" value="assign_selected">
                                <input type="hidden" name="facility_id" value="<?= (int)$selectedFacilityId; ?>">
                                <input type="hidden" name="facility_name" value="<?= h($selectedFacilityName); ?>">

                                <div class="panel-toolbar">
                                    <input type="search" id="searchAssignable" placeholder="Search assets in UMAN warehouse…" aria-label="Search assignable assets">
                                    <div style="display:flex; gap:6px; align-items:center;">
                                        <span id="assignSelectedCount" class="muted" style="font-size:12px;">0 selected</span>
                                        <button type="button" onclick="openAssignModal()" class="btn btn-success btn-sm" <?= count($assignableAssets) ? '' : 'disabled'; ?> id="assignSubmitBtn">
                                            <i class="fas fa-arrow-down"></i> Assign checked
                                        </button>
                                    </div>
                                </div>
                                <div class="form-grid">
                                    <div class="full">
                                        <label>Assignment notes (optional)</label>
                                        <textarea name="notes" rows="2" placeholder="e.g. 3 tables + 40 chairs for Dec barangay assemblies; pickup Tue 9am from Warehouse A"></textarea>
                                    </div>
                                </div>

                                <?php if ($assignableAssets === []): ?>
                                    <div class="muted" style="padding:24px 12px; text-align:center; background:#fff; border:1px dashed #cbd5e1; border-radius:10px;">
                                        <i class="fas fa-box-open"></i> No assets currently in UMAN warehouse
                                        (all operational items are already on-loan elsewhere).
                                    </div>
                                <?php else: ?>
                                    <table class="table" id="assignableTable">
                                        <thead>
                                            <tr>
                                                <th style="width:32px;"><input type="checkbox" id="assignCheckAll"></th>
                                                <th>Code</th>
                                                <th>Name</th>
                                                <th>Type</th>
                                                <th>Condition</th>
                                                <th style="width:100px;">Qty to Assign</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($assignableAssets as $a):
                                                $id = (int)$a['id'];
                                            ?>
                                                <tr class="asset-row">
                                                    <td><input type="checkbox" name="asset_ids[]" value="<?= $id; ?>" class="asset-chk"></td>
                                                    <td class="code"><?= h($a['asset_code'] ?? ''); ?></td>
                                                    <td><?= h($a['name'] ?? ''); ?></td>
                                                    <td><?= h($a['asset_type'] ?? ''); ?></td>
                                                    <td><span class="badge available"><?= h($a['condition_status'] ?? ''); ?></span></td>
                                                    <td>
                                                        <?php $availQty = (int)($a['quantity'] ?? 1); ?>
                                                        <input type="number" name="assign_qty[<?= $id; ?>]" class="form-control" style="width:80px; padding:4px 8px; font-size:13px;" value="<?= $availQty ?>" min="1" max="<?= $availQty ?>">
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </form>
                        </div>
                        <?php endif; ?>

                        <!-- SUB-TAB 2: At This Facility / Active Loans -->
                        <div id="tab-at-facility" class="tab-panel <?= !empty($selectedFacilityIsCitizen) ? 'active' : '' ?>">
                            <div class="panel-toolbar">
                                <input type="search" id="searchAtFacility" placeholder="Search items at facility…" aria-label="Search items at facility">
                                <span class="muted" style="font-size:12px;">
                                    <?= count($atFacility); ?> item<?= count($atFacility) === 1 ? '' : 's'; ?> on-loan
                                </span>
                            </div>
                            <?php if ($atFacility === []): ?>
                                <div class="muted" style="padding:24px 12px; text-align:center; background:#fff; border:1px dashed #cbd5e1; border-radius:10px;">
                                    <?php if (!empty($selectedFacilityIsCitizen)): ?>
                                        This citizen currently has no unreturned equipment on loan.
                                    <?php else: ?>
                                        No equipment currently assigned to this facility.
                                        Use the <strong>Assignable Assets</strong> tab (or fulfill a CPRF request) to assign.
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <?php if (!empty($selectedFacilityIsCitizen)): ?>
                                    <table class="table" id="atFacilityTable">
                                        <thead>
                                            <tr>
                                                <th>Code</th>
                                                <th>Name</th>
                                                <th>Type</th>
                                                <th>Loaned Qty</th>
                                                <th>Return Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($atFacility as $a): ?>
                                                <tr class="asset-row">
                                                    <td class="code"><?= h($a['asset_code'] ?? ''); ?></td>
                                                    <td><?= h($a['name'] ?? ''); ?></td>
                                                    <td><?= h($a['asset_type'] ?? ''); ?></td>
                                                    <td><strong><?= (int)$a['loaned_qty']; ?></strong> units</td>
                                                    <td>
                                                        <?php if (!empty($a['return_date'])): ?>
                                                            <span style="color:#b45309;"><i class="fas fa-calendar-check"></i> <?= h($a['return_date']); ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <form method="POST" onsubmit="event.preventDefault(); openConfirmModal(this, 'Return Asset', 'Are you sure you want to mark this citizen request as returned? This will restock the inventory.', 'btn-success');">
                                                            <input type="hidden" name="id" value="<?= (int)$a['req_id']; ?>">
                                                            <input type="hidden" name="fulfilled_asset_id" value="<?= (int)$a['id']; ?>">
                                                            <input type="hidden" name="quantity" value="<?= (int)$a['loaned_qty']; ?>">
                                                            <input type="hidden" name="action" value="accept_citizen_return">
                                                            <button class="btn btn-sm btn-success" type="submit"><i class="fas fa-undo"></i> Return</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <table class="table" id="atFacilityTable">
                                        <thead>
                                            <tr>
                                                <th>Code</th>
                                                <th>Name</th>
                                                <th>Type</th>
                                                <th>Condition</th>
                                                <th>Custody</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($atFacility as $a):
                                                $id = (int)$a['id'];
                                                $custody = (string)($a['cprf_custody_status'] ?? 'ON_LOAN_AT_FACILITY');
                                                $custodyBadge = $custody === 'LOAN_RETURN_PENDING'
                                                    ? ['class' => 'ret-pending', 'text' => 'Return Pending (CPRF)']
                                                    : ['class' => 'available', 'text' => 'On-loan'];
                                            ?>
                                                <tr class="asset-row">
                                                    <td class="code"><?= h($a['asset_code'] ?? ''); ?></td>
                                                    <td><?= h($a['name'] ?? ''); ?></td>
                                                    <td><?= h($a['asset_type'] ?? ''); ?></td>
                                                    <td><?= h($a['condition_status'] ?? ''); ?></td>
                                                    <td><span class="badge <?= $custodyBadge['class']; ?>"><?= h($custodyBadge['text']); ?></span></td>
                                                    <td>
                                                        <div class="row-actions">
                                                            <form method="POST" class="inline-form" onsubmit="return confirm('Unassign this asset from the facility? CPRF will be notified to remove it from the facility locker.');">
                                                                <input type="hidden" name="action" value="unassign">
                                                                <input type="hidden" name="facility_id" value="<?= (int)$selectedFacilityId; ?>">
                                                                <input type="hidden" name="facility_name" value="<?= h($selectedFacilityName); ?>">
                                                                <input type="hidden" name="asset_id" value="<?= $id; ?>">
                                                                <input type="hidden" name="reason" value="UMAN recall / reassigned on <?= date('Y-m-d'); ?>">
                                                                <button type="submit" class="btn btn-sm btn-danger" title="Recall asset to UMAN warehouse"><i class="fas fa-rotate-left"></i> Unassign</button>
                                                            </form>
                                                            <button type="button" class="btn btn-sm btn-warning accept-return-btn"
                                                                    data-asset-id="<?= $id; ?>"
                                                                    data-asset-code="<?= h($a['asset_code'] ?? ''); ?>"
                                                                    data-asset-name="<?= h($a['name'] ?? ''); ?>"
                                                                    data-asset-type="<?= h($a['asset_type'] ?? ''); ?>">
                                                                <i class="fas fa-hand-holding"></i> Accept return
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>

                        <!-- SUB-TAB 3: Activity log -->
                        <div id="tab-events" class="tab-panel">
                            <?php if ($events === []): ?>
                                <div class="muted" style="padding:24px 12px; text-align:center; background:#fff; border:1px dashed #cbd5e1; border-radius:10px;">
                                    No CPRF-related activity for this facility yet.
                                    Fulfilling a request or assigning an asset will create entries here.
                                </div>
                            <?php else: ?>
                                <?php foreach ($events as $ev):
                                    $statusClass = match ((string)($ev['status'] ?? '')) {
                                        'fulfilled' => 'available',
                                        'approved'  => 'count',
                                        'rejected'  => 'offline',
                                        default     => 'ret-pending',
                                    };
                                ?>
                                    <div class="event-card">
                                        <div class="head">
                                            <span>
                                                <span class="ref"><?= h($ev['request_ref'] ?? ''); ?></span>
                                                <span class="badge <?= $statusClass; ?>"><?= h(ucfirst((string)($ev['status'] ?? 'pending'))); ?></span>
                                            </span>
                                            <span class="muted" style="font-size:12px;">
                                                <?= h(date('M d, Y g:i A', strtotime((string)($ev['updated_at'] ?? '')))); ?>
                                            </span>
                                        </div>
                                        <div class="muted" style="font-size:12px; margin-bottom: 3px;">
                                            <strong><?= h($ev['asset_type'] ?? ''); ?></strong>
                                            · Qty <?= (int)($ev['quantity'] ?? 1); ?>
                                            <?php if (!empty($ev['fulfilled_asset_id'])): ?>
                                                · Fulfilled with asset #<span class="code"><?= (int)$ev['fulfilled_asset_id']; ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($ev['notes'])): ?>
                                            <div style="font-size:12px;">📝 <?= h(mb_strimwidth((string)$ev['notes'], 0, 180, '…')); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($ev['review_notes'])): ?>
                                            <div style="font-size:12px; color:#059669;">✅ UMAN: <?= h(mb_strimwidth((string)$ev['review_notes'], 0, 180, '…')); ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <?php if ($selectedFacilityId > 0): ?>
            <form method="POST" id="acceptReturnForm" style="display:none;">
                <input type="hidden" name="action" value="accept_return">
                <input type="hidden" name="facility_id" value="<?= (int)$selectedFacilityId; ?>">
                <input type="hidden" name="facility_name" value="<?= h($selectedFacilityName); ?>">
                <input type="hidden" name="asset_id" id="ret-asset-id" value="">
            </form>
            <?php endif; ?>

        </div><!-- /hub-assignments -->

    </div>
</main>

<!-- Custom Confirm Modal -->
<div id="confirmActionModal" class="modal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header">
            <h3 id="confirmActionTitle">Confirm Action</h3>
            <button class="modal-close" type="button" onclick="closeConfirmModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <p id="confirmActionMessage" style="font-size: 15px; color: #334155;">Are you sure?</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeConfirmModal()">Cancel</button>
            <button type="button" class="btn btn-primary" id="confirmActionBtn" onclick="submitConfirmForm()">Confirm</button>
        </div>
    </div>
</div>

<!-- Accept Return Modal -->
<div id="acceptReturnModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 id="acceptReturnTitle">Accept Return</h3>
            <button class="modal-close" type="button" onclick="closeAcceptReturnModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <p id="acceptReturnSubtitle" style="font-size: 14px; color: #475569; margin-bottom: 15px;"></p>
            
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display:block; margin-bottom:5px; font-weight:bold; font-size: 14px;">Reason (shown in CPRF audit log):</label>
                <input type="text" id="acceptReturnReason" class="form-control" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px;" value="Inspected and returned to UMAN warehouse">
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display:block; margin-bottom:5px; font-weight:bold; font-size: 14px;">Condition after return:</label>
                <select id="acceptReturnCondition" class="form-control" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px;">
                    <option value="Operational">Operational</option>
                    <option value="Needs Inspection">Needs Inspection</option>
                    <option value="Damaged">Damaged</option>
                    <option value="Condemned">Condemned</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display:flex; align-items:center; gap:8px; font-weight:bold; font-size: 14px; cursor: pointer;">
                    <input type="checkbox" id="acceptReturnReplace" value="yes" style="width:16px; height:16px; accent-color: #3b82f6;" onchange="toggleReplacementOptions()">
                    Send a REPLACEMENT to this facility?
                </label>
                <p style="font-size: 12px; color: #64748b; margin-top: 4px; margin-left: 24px;">Check this to create an approved replacement request.</p>
            </div>

            <div class="form-group" id="replacementAssetGroup" style="display:none; margin-left: 24px;">
                <label style="display:block; margin-bottom:5px; font-size: 14px;">Pick a replacement asset (optional):</label>
                <select id="acceptReturnReplacementAsset" class="form-control" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px;">
                    <option value="0">-- Fulfill later --</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeAcceptReturnModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="submitAcceptReturn()">Accept Return</button>
        </div>
    </div>
</div>

<!-- Custom Assign Modal -->
<div id="assignModal" class="modal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header">
            <h3>Confirm Assignment</h3>
            <button class="modal-close" type="button" onclick="closeAssignModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <p id="assignModalSubtitle" style="font-size: 15px; color: #334155; margin-bottom: 10px;"></p>
            <p style="font-size: 14px; color: #475569;">Are you sure you want to assign these assets to <strong><?= h($selectedFacilityName ?? '') ?></strong>?</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeAssignModal()">Cancel</button>
            <button type="button" class="btn btn-success" onclick="submitAssign()"><i class="fas fa-check"></i> Confirm Assignment</button>
        </div>
    </div>
</div>

<script>
(function () {
    "use strict";

    // Custom Modal Logic
    window.currentActionForm = null;

    window.openConfirmModal = function(form, title, message, btnClass) {
        window.currentActionForm = form;
        document.getElementById('confirmActionTitle').innerText = title;
        document.getElementById('confirmActionMessage').innerHTML = message;
        
        const btn = document.getElementById('confirmActionBtn');
        btn.className = 'btn ' + btnClass;
        
        document.getElementById('confirmActionModal').classList.add('open');
    };

    window.closeConfirmModal = function() {
        document.getElementById('confirmActionModal').classList.remove('open');
        window.currentActionForm = null;
    };

    window.submitConfirmForm = function() {
        if (window.currentActionForm) {
            window.currentActionForm.submit();
        }
    };

    // ── Hub-level tabs ─────────────────────────────────────────────────
    const hubTabs = document.querySelectorAll('.hub-tab');
    const hubPanels = document.querySelectorAll('.hub-panel');
    function activateHubTab(tabId) {
        hubTabs.forEach(function (t) {
            t.classList.toggle('active', t.getAttribute('data-hub-tab') === tabId);
        });
        hubPanels.forEach(function (p) {
            p.classList.toggle('active', p.getAttribute('id') === tabId);
        });
        if (window.location.hash === '#hub-assignments' || window.location.hash === '#hub-requests') {
            // anchor already handled via the matching logic above
        }
    }
    hubTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            const id = tab.getAttribute('data-hub-tab');
            activateHubTab(id);
            if (history.replaceState) {
                history.replaceState(null, '', '#' + id);
            }
        });
    });
    if (window.location.hash === '#hub-assignments') {
        activateHubTab('hub-assignments');
    } else {
        activateHubTab('hub-requests');
    }

    // ── Sub-tabs (Assignments panel only) ──────────────────────────────
    document.querySelectorAll('.tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            const target = tab.getAttribute('data-tab');
            document.querySelectorAll('.tab').forEach(function (t) { t.classList.remove('active'); });
            document.querySelectorAll('.tab-panel').forEach(function (p) { p.classList.remove('active'); });
            tab.classList.add('active');
            const panel = document.getElementById(target);
            if (panel) panel.classList.add('active');
        });
    });


    // ── Facility list search ────────────────────────────────────────────
    const facilitySearch = document.getElementById('facilitySearch');
    if (facilitySearch) {
        facilitySearch.addEventListener('input', function () {
            const q = facilitySearch.value.trim().toLowerCase();
            document.querySelectorAll('#facilityList .facility-item').forEach(function (el) {
                const text = (el.textContent || '').toLowerCase();
                el.style.display = q === '' || text.includes(q) ? '' : 'none';
            });
        });
    }

    // ── Assignable assets table search + select-all + counter ──────────
    const assignSearch = document.getElementById('searchAssignable');
    const assignTable  = document.getElementById('assignableTable');
    const assignCheckAll = document.getElementById('assignCheckAll');
    const assetChk = document.querySelectorAll('#assignableTable .asset-chk');
    const assignCount = document.getElementById('assignSelectedCount');
    const assignBtn = document.getElementById('assignSubmitBtn');

    function recountAssign() {
        const n = document.querySelectorAll('#assignableTable .asset-chk:checked').length;
        if (assignCount) assignCount.textContent = n + ' selected';
        if (assignBtn)   assignBtn.disabled = n === 0;
    }
    if (assignCheckAll) {
        assignCheckAll.addEventListener('change', function () {
            const visibleRows = assignTable.querySelectorAll('tbody tr.asset-row');
            visibleRows.forEach(function (tr) {
                const cb = tr.querySelector('.asset-chk');
                if (cb && tr.style.display !== 'none') cb.checked = assignCheckAll.checked;
            });
            recountAssign();
        });
    }
    assetChk.forEach(function (cb) { cb.addEventListener('change', recountAssign); });
    if (assignSearch && assignTable) {
        assignSearch.addEventListener('input', function () {
            const q = assignSearch.value.trim().toLowerCase();
            assignTable.querySelectorAll('tbody tr.asset-row').forEach(function (tr) {
                const txt = (tr.textContent || '').toLowerCase();
                tr.style.display = q === '' || txt.includes(q) ? '' : 'none';
            });
        });
    }
    recountAssign();

    // ── At-facility table search ────────────────────────────────────────
    const atSearch = document.getElementById('searchAtFacility');
    const atTable  = document.getElementById('atFacilityTable');
    if (atSearch && atTable) {
        atSearch.addEventListener('input', function () {
            const q = atSearch.value.trim().toLowerCase();
            atTable.querySelectorAll('tbody tr.asset-row').forEach(function (tr) {
                const txt = (tr.textContent || '').toLowerCase();
                tr.style.display = q === '' || txt.includes(q) ? '' : 'none';
            });
        });
    }

    // ── Accept return (modal) ────────────────────────────────────────
    let currentReturnAssetType = '';
    const replacementCandidates = <?= json_encode(
        array_map(static fn($r) => [
            'id'        => (int)($r['id'] ?? 0),
            'code'      => (string)($r['asset_code'] ?? ''),
            'name'      => (string)($r['name'] ?? ''),
            'type'      => (string)($r['asset_type'] ?? ''),
            'condition' => (string)($r['condition_status'] ?? ''),
        ], $replacementCandidates ?? []),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ); ?>;

    window.closeAcceptReturnModal = function() {
        document.getElementById('acceptReturnModal').classList.remove('open');
    };

    window.openAssignModal = function() {
        const n = document.querySelectorAll('#assignableTable .asset-chk:checked').length;
        if (n === 0) return;
        document.getElementById('assignModalSubtitle').innerHTML = `You have selected <strong>${n}</strong> asset(s) to assign.`;
        document.getElementById('assignModal').classList.add('open');
    };

    window.closeAssignModal = function() {
        document.getElementById('assignModal').classList.remove('open');
    };

    window.submitAssign = function() {
        document.getElementById('form-assign').submit();
    };

    window.toggleReplacementOptions = function() {
        const checked = document.getElementById('acceptReturnReplace').checked;
        document.getElementById('replacementAssetGroup').style.display = checked ? 'block' : 'none';
    };

    window.submitAcceptReturn = function() {
        const form = document.getElementById('acceptReturnForm');
        if (!form) return;
        
        form.querySelectorAll('.dynamic-input').forEach(el => el.remove());

        const reason = document.getElementById('acceptReturnReason').value.trim();
        const cond = document.getElementById('acceptReturnCondition').value;
        const wantReplace = document.getElementById('acceptReturnReplace').checked;
        const replaceId = document.getElementById('acceptReturnReplacementAsset').value;

        if (!reason) {
            alert("Reason is required.");
            return;
        }

        const add = function (name, value) {
            const inp = document.createElement('input');
            inp.type = 'hidden'; inp.className = 'dynamic-input';
            inp.name = name; inp.value = value;
            form.appendChild(inp);
        };

        add('reason', reason);
        add('condition_after_return', cond);
        
        if (wantReplace) {
            add('replacement', '1');
            if (Number(replaceId) > 0) {
                add('replacement_asset_id', replaceId);
            }
        }

        form.submit();
    };

    document.querySelectorAll('.accept-return-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const ds = btn.dataset || {};
            const id = Number(ds.assetId) || 0;
            const code = ds.assetCode || '';
            const name = ds.assetName || '';
            const type = ds.assetType || '';
            if (id <= 0) return;

            document.getElementById('ret-asset-id').value = String(id);
            document.getElementById('acceptReturnSubtitle').textContent = `Accept return of ${name} (#${code})`;
            document.getElementById('acceptReturnReason').value = 'Inspected and returned to UMAN warehouse';
            document.getElementById('acceptReturnCondition').value = 'Operational';
            document.getElementById('acceptReturnReplace').checked = false;
            toggleReplacementOptions();
            
            currentReturnAssetType = type;

            const sameType = replacementCandidates.filter(c => (c.type || '').toLowerCase() === String(type || '').toLowerCase());
            const pool = sameType.length > 0 ? sameType : replacementCandidates;

            const select = document.getElementById('acceptReturnReplacementAsset');
            select.innerHTML = '<option value="0">-- Fulfill later --</option>';
            pool.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = `#${c.code} — ${c.name} [${c.condition}]`;
                select.appendChild(opt);
            });

            document.getElementById('acceptReturnModal').classList.add('open');
        });
    });
})();
</script>
</body>
</html>
