<?php
/**
 * Read-only monthly energy feed for the LGU1 Energy system.
 *
 * GET /api/monthly-energy-records.php?key=...&page=1&per_page=100
 * Optional filters: year, month, updated_since (ISO-8601/date-time)
 */
declare(strict_types=1);

require_once __DIR__ . '/integration_config.php';

uman_require_api_key($UMAN_INTEGRATION_API_KEY);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $pdo = uman_integration_pdo();

    $columns = $pdo->query('SHOW COLUMNS FROM energy_consumption_records')
        ->fetchAll(PDO::FETCH_COLUMN);
    $hasColumn = static fn(string $column): bool => in_array($column, $columns, true);

    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(200, max(1, (int)($_GET['per_page'] ?? 100)));
    $offset = ($page - 1) * $perPage;

    $conditions = [];
    $params = [];

    // This handoff intentionally exports only records received from CPRF.
    // UMAN keeps the analytics view; LGU1 Energy receives the same approved
    // monthly record under its matching CPRF-mirrored facility.
    if ($hasColumn('source_system')) {
        $conditions[] = "source_system = 'CPRF'";
    } else {
        // Legacy schemas cannot identify which rows came from CPRF safely.
        $conditions[] = '1 = 0';
    }

    $year = (int)($_GET['year'] ?? 0);
    if ($year > 0) {
        $conditions[] = 'LEFT(month_year, 4) = ?';
        $params[] = sprintf('%04d', $year);
    }

    $month = (int)($_GET['month'] ?? 0);
    if ($month >= 1 && $month <= 12) {
        $conditions[] = 'RIGHT(month_year, 2) = ?';
        $params[] = sprintf('%02d', $month);
    }

    $updatedSince = trim((string)($_GET['updated_since'] ?? ''));
    if ($updatedSince !== '') {
        $timestamp = strtotime($updatedSince);
        if ($timestamp === false) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'updated_since must be a valid date/time']);
            exit;
        }
        $conditions[] = 'date_recorded >= ?';
        $params[] = date('Y-m-d H:i:s', $timestamp);
    }

    $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

    $countStatement = $pdo->prepare('SELECT COUNT(*) FROM energy_consumption_records' . $where);
    $countStatement->execute($params);
    $total = (int)$countStatement->fetchColumn();

    $select = [
        'id', 'record_id', 'utility_asset_id', 'facility_name', 'asset_type',
        'location', 'month_year', 'consumption_kwh', 'cost', 'data_source',
        'notes', 'date_recorded',
    ];
    if ($hasColumn('rate_per_kwh')) {
        $select[] = 'rate_per_kwh';
    }
    if ($hasColumn('cprf_facility_id')) {
        $select[] = 'cprf_facility_id';
    }

    $statement = $pdo->prepare(
        'SELECT ' . implode(', ', $select)
        . ' FROM energy_consumption_records' . $where
        . ' ORDER BY date_recorded ASC, id ASC LIMIT ' . $perPage . ' OFFSET ' . $offset
    );
    $statement->execute($params);

    $data = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (!preg_match('/^(\d{4})-(\d{2})/', (string)$row['month_year'], $period)) {
            continue;
        }

        $cprfFacilityId = (int)($row['cprf_facility_id'] ?? 0);
        if ($cprfFacilityId <= 0) {
            continue;
        }

        $facilityName = trim((string)($row['facility_name'] ?? ''));
        $location = trim((string)($row['location'] ?? ''));
        $assetType = trim((string)($row['asset_type'] ?? '')) ?: 'Public Facility';
        $identity = 'cprf:' . $cprfFacilityId;

        $data[] = [
            'source_record_id' => (string)$row['record_id'],
            'facility_key' => $identity,
            'cprf_facility_id' => $cprfFacilityId,
            'facility_name' => $facilityName !== '' ? $facilityName : ($location !== '' ? $location : 'UMAN Energy Asset'),
            'facility_type' => $assetType,
            'location' => $location,
            'year' => (int)$period[1],
            'month' => (int)$period[2],
            'consumption_kwh' => (float)$row['consumption_kwh'],
            'cost' => is_numeric($row['cost'] ?? null) ? (float)$row['cost'] : null,
            'rate_per_kwh' => is_numeric($row['rate_per_kwh'] ?? null) ? (float)$row['rate_per_kwh'] : null,
            'recorded_at' => $row['date_recorded'],
            'notes' => $row['notes'],
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $data,
        'meta' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => max(1, (int)ceil($total / $perPage)),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('UMAN monthly energy feed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to load monthly energy records']);
}
