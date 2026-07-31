<?php
/**
 * GET  /api/asset-requests.php?key=...&status=pending
 * POST /api/asset-requests.php?key=...  (JSON or form body)
 *
 * CPRF submits equipment/asset requests; UMAN staff fulfill via external_asset_requests.php.
 *
 * Accepted POST payload fields (recommended for unambiguous fulfillment):
 *   cprf_facility_id* (int)  — target facility the request is for
 *   facility_name*    (str)
 *   asset_type*       (str)  — UMAN asset_types.name category (e.g. "Sound System")
 *   asset_type_id     (int)  — UMAN asset_types.id (optional, links to exact category row)
 *   requested_asset_code (str)— specific asset_code (utility_assets.asset_id) if known;
 *                               use this + request "exact_match:true" to reserve that exact unit
 *   exact_match       (bool) — if true + requested_asset_code set, UMAN will not substitute
 *   quantity          (int)  — defaults to 1
 *   urgency           (str)  — 'Routine' | 'Priority' | 'Emergency' (default Routine)
 *   date_needed       (date) — YYYY-MM-DD when the asset must be available at the facility
 *   booking_ref       (str)  — CPRF booking reference / reservation ID (links request to event)
 *   event_purpose     (str)  — e.g. "Barangay assembly" / "Graduation ceremony"
 *   responsible_office (str) — UMAN responsible_office (from asset catalog), for routing
 *   notes             (str)  — free text
 */
declare(strict_types=1);

require_once __DIR__ . '/integration_config.php';

uman_require_api_key($UMAN_INTEGRATION_API_KEY);

try {
    $pdo = uman_integration_pdo();

    // ── Schema ensurance: core table + newer specific-fields extension.
    //    Idempotent: CREATE TABLE IF NOT EXISTS then targeted ALTER ADD so
    //    legacy installations auto-upgrade without downtime.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `external_asset_requests` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `request_ref` VARCHAR(50) NOT NULL UNIQUE,
          `source_system` VARCHAR(50) NOT NULL DEFAULT 'CPRF',
          `cprf_facility_id` INT NOT NULL,
          `facility_name` VARCHAR(150) NOT NULL,
          `asset_type` VARCHAR(100) NOT NULL,
          `asset_type_id` INT NULL,
          `requested_asset_code` VARCHAR(50) NULL,
          `exact_match` TINYINT(1) NOT NULL DEFAULT 0,
          `quantity` INT NOT NULL DEFAULT 1,
          `urgency` ENUM('Routine','Priority','Emergency') NOT NULL DEFAULT 'Routine',
          `date_needed` DATE NULL,
          `booking_ref` VARCHAR(80) NULL,
          `event_purpose` VARCHAR(200) NULL,
          `responsible_office` VARCHAR(100) NULL,
          `notes` TEXT NULL,
          `status` ENUM('pending', 'approved', 'fulfilled', 'rejected') NOT NULL DEFAULT 'pending',
          `fulfilled_asset_id` INT NULL,
          `review_notes` TEXT NULL,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_ear_status (status),
          INDEX idx_ear_facility (cprf_facility_id),
          INDEX idx_ear_date_needed (date_needed),
          INDEX idx_ear_urgency (urgency),
          INDEX idx_ear_requested_asset (requested_asset_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Additive column patching for legacy tables that predate these fields.
    $addCol = function (string $definition) use ($pdo): void {
        try { $pdo->exec("ALTER TABLE external_asset_requests ADD COLUMN $definition"); }
        catch (Throwable $e) { /* duplicate column is fine */ }
    };
    $addCol("`asset_type_id` INT NULL AFTER `asset_type`");
    $addCol("`requested_asset_code` VARCHAR(50) NULL AFTER `asset_type_id`");
    $addCol("`exact_match` TINYINT(1) NOT NULL DEFAULT 0 AFTER `requested_asset_code`");
    $addCol("`urgency` ENUM('Routine','Priority','Emergency') NOT NULL DEFAULT 'Routine' AFTER `quantity`");
    $addCol("`date_needed` DATE NULL AFTER `urgency`");
    $addCol("`booking_ref` VARCHAR(80) NULL AFTER `date_needed`");
    $addCol("`event_purpose` VARCHAR(200) NULL AFTER `booking_ref`");
    $addCol("`responsible_office` VARCHAR(100) NULL AFTER `event_purpose`");
    try { $pdo->exec("ALTER TABLE external_asset_requests
        ADD INDEX idx_ear_status (status),
        ADD INDEX idx_ear_facility (cprf_facility_id),
        ADD INDEX idx_ear_date_needed (date_needed),
        ADD INDEX idx_ear_urgency (urgency),
        ADD INDEX idx_ear_requested_asset (requested_asset_code)"); }
    catch (Throwable $e) { /* indexes already exist — noop */ }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $status = trim((string)($_GET['status'] ?? ''));
        $facilityId = isset($_GET['cprf_facility_id']) ? (int)$_GET['cprf_facility_id'] : 0;

        $sql = 'SELECT * FROM external_asset_requests WHERE source_system = ?';
        $params = ['CPRF'];

        if ($status !== '' && in_array($status, ['pending', 'approved', 'fulfilled', 'rejected'], true)) {
            $sql .= ' AND status = ?';
            $params[] = $status;
        }
        if ($facilityId > 0) {
            $sql .= ' AND cprf_facility_id = ?';
            $params[] = $facilityId;
        }

        $sql .= ' ORDER BY created_at DESC LIMIT 200';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'count' => count($rows), 'data' => $rows], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw ?: '{}', true);
        if (!is_array($json)) {
            $json = $_POST;
        }

        $facilityId = (int)($json['cprf_facility_id'] ?? 0);
        $facilityName = trim((string)($json['facility_name'] ?? ''));
        $assetType = trim((string)($json['asset_type'] ?? ''));
        $assetTypeId = !empty($json['asset_type_id']) ? (int)$json['asset_type_id'] : null;
        $requestedAssetCode = trim((string)($json['requested_asset_code'] ?? '')) ?: null;
        $exactMatch = !empty($json['exact_match']) ? 1 : 0;
        $quantity = max(1, (int)($json['quantity'] ?? 1));
        $urgency = in_array(($json['urgency'] ?? ''), ['Routine','Priority','Emergency'], true)
            ? (string)$json['urgency'] : 'Routine';
        $dateNeeded = trim((string)($json['date_needed'] ?? '')) ?: null;
        if ($dateNeeded !== null) {
            $parsed = date_parse($dateNeeded);
            $dateNeeded = ($parsed !== false && empty($parsed['errors']))
                ? sprintf('%04d-%02d-%02d', $parsed['year'], $parsed['month'], $parsed['day'])
                : null;
        }
        $bookingRef = trim((string)($json['booking_ref'] ?? '')) ?: null;
        $eventPurpose = trim((string)($json['event_purpose'] ?? '')) ?: null;
        $responsibleOffice = trim((string)($json['responsible_office'] ?? '')) ?: null;
        $notes = trim((string)($json['notes'] ?? '')) ?: null;

        if ($facilityId <= 0 || $facilityName === '' || $assetType === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'cprf_facility_id, facility_name, and asset_type are required']);
            exit;
        }

        // If a specific asset_code is pinned with exact_match=1 and it is already allocated
        // (Damaged / Under Maintenance), fail fast 422 with a clear message so CPRF staff
        // can pick an alternative instead of waiting weeks on an impossible request.
        if ($exactMatch === 1 && $requestedAssetCode !== null) {
            $availStmt = $pdo->prepare("
                SELECT condition_status, name, location, responsible_office
                FROM utility_assets WHERE asset_id = ? LIMIT 1
            ");
            $availStmt->execute([$requestedAssetCode]);
            $asset = $availStmt->fetch(PDO::FETCH_ASSOC);
            if (!$asset) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'error' => "Exact match requested, but asset_code {$requestedAssetCode} does not exist in UMAN catalog.",
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if (in_array($asset['condition_status'], ['Damaged','Under Maintenance'], true)) {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'error' => "Exact-match asset {$requestedAssetCode} is currently {$asset['condition_status']}. Cancel exact_match to allow an alternate unit of the same type.",
                    'asset' => $asset,
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        $prefix = 'CPRF-REQ-' . date('Ym') . '-';
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM external_asset_requests WHERE request_ref LIKE ?');
        $countStmt->execute([$prefix . '%']);
        $seq = (int)$countStmt->fetchColumn() + 1;
        $requestRef = $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("
            INSERT INTO external_asset_requests
                (request_ref, source_system, cprf_facility_id, facility_name,
                 asset_type, asset_type_id, requested_asset_code, exact_match,
                 quantity, urgency, date_needed, booking_ref, event_purpose,
                 responsible_office, notes, status)
            VALUES (?, 'CPRF', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $requestRef,
            $facilityId, $facilityName,
            $assetType, $assetTypeId, $requestedAssetCode, $exactMatch,
            $quantity, $urgency, $dateNeeded, $bookingRef, $eventPurpose,
            $responsibleOffice, $notes,
        ]);

        $id = (int)$pdo->lastInsertId();

        try {
            $urgencyTag = $urgency === 'Routine' ? '' : "[{$urgency}] ";
            $eventTag = $eventPurpose ? " · {$eventPurpose}" : '';
            $whenTag = $dateNeeded ? " · need by {$dateNeeded}" : '';
            $exactTag = $exactMatch ? " · exact {$requestedAssetCode}" : ($requestedAssetCode ? " · prefers {$requestedAssetCode}" : '');
            $notificationMsg = "{$urgencyTag}New CPRF asset request {$requestRef} for {$facilityName}: {$quantity}x {$assetType}{$exactTag}{$whenTag}{$eventTag}";
            if ($responsibleOffice) $notificationMsg .= " · route to {$responsibleOffice}";
            if ($bookingRef) $notificationMsg .= " (booking {$bookingRef})";
            if ($notes) $notificationMsg .= " — " . mb_substr($notes, 0, 140);
            $pdo->prepare("INSERT INTO asset_notifications (type, message) VALUES ('external_request', ?)")
                ->execute([$notificationMsg]);
        } catch (Throwable $e) {
            // optional notification table — do not fail request on this
        }

        echo json_encode([
            'success' => true,
            'message' => 'Asset request submitted',
            'request_ref' => $requestRef,
            'id' => $id,
            'status' => 'pending',
            'recorded' => [
                'urgency' => $urgency,
                'date_needed' => $dateNeeded,
                'booking_ref' => $bookingRef,
                'event_purpose' => $eventPurpose,
                'responsible_office' => $responsibleOffice,
                'requested_asset_code' => $requestedAssetCode,
                'exact_match' => (bool)$exactMatch,
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
} catch (Throwable $e) {
    error_log('UMAN asset-requests API: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error processing request: ' . $e->getMessage()]);
}
