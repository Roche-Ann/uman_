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
    uman_ensure_cprf_custody_schema($pdo);

    $status = trim((string)($_GET['status'] ?? ''));
    $availableOnly = isset($_GET['available']) && $_GET['available'] !== '0' && $_GET['available'] !== 'false';
    $assetType = trim((string)($_GET['asset_type'] ?? ''));

    // Filter controls for the new Assignments page (cprf_custody_status + cprf_facility_id)
    $custodyStatus = trim((string)($_GET['custody'] ?? ''));
    $cprfFacilityId = !empty($_GET['cprf_facility_id']) ? (int)$_GET['cprf_facility_id'] : 0;
    // `warehoused_only` = convenience flag: return only assets currently in UMAN
    // custody (not on-loan at any facility). Used by Assignable Assets tab.
    $warehousedOnly = isset($_GET['warehoused_only']) && $_GET['warehoused_only'] !== '0' && $_GET['warehoused_only'] !== 'false';

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
            a.cprf_facility_id,
            a.cprf_custody_status,
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

    if ($cprfFacilityId > 0) {
        $sql .= ' AND a.cprf_facility_id = ?';
        $params[] = $cprfFacilityId;
    }
    if ($custodyStatus !== '') {
        $sql .= ' AND a.cprf_custody_status = ?';
        $params[] = $custodyStatus;
    } elseif ($warehousedOnly) {
        $sql .= " AND a.cprf_custody_status = 'WAREHOUSED'";
    }

    $sql .= ' ORDER BY a.name ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($assets as &$row) {
        $custody = (string)($row['cprf_custody_status'] ?? 'WAREHOUSED');
        $row['custody_status'] = $custody;
        $row['custody_status_label'] = match ($custody) {
            'ON_LOAN_AT_FACILITY' => 'On-loan at CPRF Facility',
            'LOAN_RETURN_PENDING' => 'Return Pending (CPRF notified UMAN)',
            'LOAN_RETURNED'       => 'Returned to UMAN Warehouse',
            'CONDEMNED'           => 'Condemned / Decommissioned',
            default               => 'At UMAN Warehouse',
        };
        $row['cprf_facility_id'] = !empty($row['cprf_facility_id']) ? (int)$row['cprf_facility_id'] : null;
    }
    unset($row);

    echo json_encode([
        'success' => true,
        'count' => count($assets),
        'data' => $assets,
        'filters' => [
            'warehoused_only'  => $warehousedOnly,
            'cprf_facility_id' => $cprfFacilityId > 0 ? $cprfFacilityId : null,
            'custody_status'   => $custodyStatus !== '' ? $custodyStatus : null,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('UMAN assets API: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error loading assets']);
}
