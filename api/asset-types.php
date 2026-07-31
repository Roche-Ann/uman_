<?php
/**
 * GET /api/asset-types.php?key=...
 *
 * Lists all asset types (category catalog) available from UMAN for
 * the CPRF Facilities Reservation dropdown + description tooltips.
 * Returns: [{id, name, description, asset_count, operational_count,
 *            sample_assets:[{asset_code, name, location, condition_status}]}]
 */

declare(strict_types=1);

require_once __DIR__ . '/integration_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

uman_require_api_key($UMAN_INTEGRATION_API_KEY);

try {
    $pdo = uman_integration_pdo();

    $rows = $pdo->query("
        SELECT
            t.id,
            t.name,
            t.description,
            COUNT(a.id) AS asset_count,
            SUM(CASE WHEN a.condition_status = 'Operational' THEN 1 ELSE 0 END) AS operational_count
        FROM asset_types t
        LEFT JOIN utility_assets a ON a.asset_type_id = t.id
        GROUP BY t.id, t.name, t.description
        ORDER BY t.name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $enriched = [];
    $sampleStmt = $pdo->prepare("
        SELECT asset_id AS asset_code, name, location, condition_status
        FROM utility_assets
        WHERE asset_type_id = ?
        ORDER BY condition_status = 'Operational' DESC, id DESC
        LIMIT 3
    ");
    foreach ($rows as $row) {
        $sampleStmt->execute([(int)$row['id']]);
        $row['sample_assets'] = $sampleStmt->fetchAll(PDO::FETCH_ASSOC);
        $enriched[] = $row;
    }

    echo json_encode([
        'success' => true,
        'count' => count($enriched),
        'data' => $enriched,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('UMAN asset-types API: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error listing asset types'], JSON_UNESCAPED_UNICODE);
}
