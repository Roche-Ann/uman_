<?php
/**
 * GET /api/assets.php?key=...&status=Operational&available=1
 * Lists utility assets for external systems (CPRF facility equipment assignment).
 */
declare(strict_types=1);

require_once __DIR__ . '/integration_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

uman_require_api_key($UMAN_INTEGRATION_API_KEY);

try {
    $pdo = uman_integration_pdo();

    $status = trim((string)($_GET['status'] ?? ''));
    $availableOnly = isset($_GET['available']) && $_GET['available'] !== '0' && $_GET['available'] !== 'false';
    $assetType = trim((string)($_GET['asset_type'] ?? ''));

    $sql = "
        SELECT
            a.id,
            a.asset_id AS asset_code,
            a.name,
            a.quantity,
            a.location,
            a.latitude,
            a.longitude,
            a.date_installed,
            a.condition_status,
            a.description,
            a.responsible_office,
            t.name AS asset_type,
            a.created_at,
            a.updated_at
        FROM utility_assets a
        JOIN asset_types t ON t.id = a.asset_type_id
        WHERE 1=1
    ";
    $params = [];

    if ($status !== '') {
        $sql .= ' AND a.condition_status = ?';
        $params[] = $status;
    } elseif ($availableOnly) {
        $sql .= " AND a.condition_status IN ('Operational', 'Needs Inspection')";
    }

    if ($assetType !== '') {
        $sql .= ' AND t.name LIKE ?';
        $params[] = '%' . $assetType . '%';
    }

    $sql .= ' ORDER BY a.name ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'count' => count($assets),
        'data' => $assets,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('UMAN assets API: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error loading assets']);
}
