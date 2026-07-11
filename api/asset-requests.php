<?php
/**
 * GET  /api/asset-requests.php?key=...&status=pending
 * POST /api/asset-requests.php?key=...  (JSON or form body)
 *
 * CPRF submits equipment/asset requests; UMAN staff fulfill via external_asset_requests.php.
 */
declare(strict_types=1);

require_once __DIR__ . '/integration_config.php';

uman_require_api_key($UMAN_INTEGRATION_API_KEY);

try {
    $pdo = uman_integration_pdo();

    // Ensure table exists (lazy install on first call)
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
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

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
        $quantity = max(1, (int)($json['quantity'] ?? 1));
        $notes = trim((string)($json['notes'] ?? ''));

        if ($facilityId <= 0 || $facilityName === '' || $assetType === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'cprf_facility_id, facility_name, and asset_type are required']);
            exit;
        }

        $prefix = 'CPRF-REQ-' . date('Ym') . '-';
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM external_asset_requests WHERE request_ref LIKE ?');
        $countStmt->execute([$prefix . '%']);
        $seq = (int)$countStmt->fetchColumn() + 1;
        $requestRef = $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("
            INSERT INTO external_asset_requests
                (request_ref, source_system, cprf_facility_id, facility_name, asset_type, quantity, notes, status)
            VALUES (?, 'CPRF', ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$requestRef, $facilityId, $facilityName, $assetType, $quantity, $notes ?: null]);

        $id = (int)$pdo->lastInsertId();

        try {
            $pdo->prepare("INSERT INTO asset_notifications (type, message) VALUES ('external_request', ?)")
                ->execute(["New CPRF asset request {$requestRef} for {$facilityName}: {$quantity}x {$assetType}"]);
        } catch (Throwable $e) {
            // optional
        }

        echo json_encode([
            'success' => true,
            'message' => 'Asset request submitted',
            'request_ref' => $requestRef,
            'id' => $id,
            'status' => 'pending',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
} catch (Throwable $e) {
    error_log('UMAN asset-requests API: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error processing request']);
}
