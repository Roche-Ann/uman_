<?php
/**
 * GET  /api/water-readings.php?key=...&cprf_facility_id=...
 * POST /api/water-readings.php?key=...  (JSON body)
 *
 * Integration-ready water readings feed. Supports both query operations and
 * idempotent post submission of readings from other groups' systems.
 */
declare(strict_types=1);

require_once __DIR__ . '/integration_config.php';

uman_require_api_key($UMAN_INTEGRATION_API_KEY);

try {
    $pdo = uman_integration_pdo();
    ensureWaterSchema();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $facilityId = isset($_GET['cprf_facility_id']) ? (int)$_GET['cprf_facility_id'] : 0;

        $sql = "SELECT * FROM water_consumption_records WHERE source_system = 'CPRF'";
        $params = [];
        if ($facilityId > 0) {
            $sql .= ' AND cprf_facility_id = ?';
            $params[] = $facilityId;
        }
        $sql .= ' ORDER BY date_recorded DESC LIMIT 200';

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

        $facilityId = (int)($json['facility_id'] ?? 0);
        $facilityName = trim((string)($json['facility_name'] ?? ''));
        $facilityLocation = trim((string)($json['location'] ?? ''));
        $year = (int)($json['year'] ?? 0);
        $month = (int)($json['month'] ?? 0);
        $previousReading = isset($json['previous_reading_cbm']) ? (float)$json['previous_reading_cbm'] : 0.0;
        $currentReading = isset($json['current_reading_cbm']) ? (float)$json['current_reading_cbm'] : 0.0;
        $consumptionWater = isset($json['consumption_cbm']) ? (float)$json['consumption_cbm'] : ($currentReading - $previousReading);
        $ratePerWater = isset($json['rate_per_cbm']) ? (float)$json['rate_per_cbm'] : 68.02;
        $waterCost = isset($json['cost']) ? (float)$json['cost'] : ($consumptionWater * $ratePerWater);
        $externalRef = trim((string)($json['external_ref'] ?? ''));
        $notes = trim((string)($json['notes'] ?? ''));
        $recordedByName = trim((string)($json['recorded_by_name'] ?? ''));

        if ($facilityId <= 0 || $year <= 0 || $month < 1 || $month > 12 || $externalRef === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'facility_id, year, month, and external_ref are required'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $monthYear = sprintf('%04d-%02d', $year, $month);
        $noteParts = [];
        if ($notes !== '') $noteParts[] = $notes;
        if ($recordedByName !== '') $noteParts[] = "Recorded by {$recordedByName} (CPRF)";
        $combinedNotes = $noteParts === [] ? null : implode(' — ', $noteParts);

        $displayName = $facilityName !== '' ? $facilityName : "CPRF Facility #{$facilityId}";
        $displayLocation = $facilityLocation !== '' ? $facilityLocation : $displayName;

        // Idempotent upsert on external_ref
        $existing = $pdo->prepare('SELECT id, record_id FROM water_consumption_records WHERE external_ref = ? LIMIT 1');
        $existing->execute([$externalRef]);
        $existingRow = $existing->fetch(PDO::FETCH_ASSOC);

        if ($existingRow) {
            $recordId = (string)$existingRow['record_id'];
            $update = $pdo->prepare('
                UPDATE water_consumption_records
                SET facility_name = ?, location = ?, month_year = ?, previous_reading = ?, current_reading = ?,
                    consumption_m3 = ?, rate_per_m3 = ?, cost = ?, data_source = ?, notes = ?
                WHERE id = ?
            ');
            $update->execute([
                $displayName, $displayLocation, $monthYear, $previousReading, $currentReading,
                $consumptionWater, $ratePerWater, $waterCost, 'CPRF Integration', $combinedNotes,
                (int)$existingRow['id'],
            ]);
        } else {
            $recordId = 'CPRF-WTR-' . date('Ym') . '-' . (int)$facilityId . '-' . substr(md5($externalRef), 0, 6);
            $insert = $pdo->prepare('
                INSERT INTO water_consumption_records
                    (record_id, source_system, cprf_facility_id, external_ref, facility_name, location, month_year,
                     previous_reading, current_reading, consumption_m3, rate_per_m3, cost, data_source, notes)
                VALUES (?, \'CPRF\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'CPRF Integration\', ?)
            ');
            $insert->execute([
                $recordId, $facilityId, $externalRef, $displayName, $displayLocation, $monthYear,
                $previousReading, $currentReading, $consumptionWater, $ratePerWater, $waterCost, $combinedNotes
            ]);
        }

        // Monitoring for high consumption
        $insertedId = $existingRow ? (int)$existingRow['id'] : (int)$pdo->lastInsertId();
        $hist = $pdo->prepare("SELECT consumption_m3 FROM water_consumption_records WHERE cprf_facility_id = ? AND id != ? ORDER BY date_recorded DESC LIMIT 3");
        $hist->execute([$facilityId, $insertedId]);
        $priorRows = $hist->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($priorRows) >= 1) {
            $avg = array_sum($priorRows) / count($priorRows);
            if ($avg > 0 && $consumptionWater > ($avg * 1.5)) {
                $pct = round((($consumptionWater - $avg) / $avg) * 100);
                $message = "WARNING: High water usage detected at {$displayName} for {$monthYear}. Consumption ({$consumptionWater} m³) is {$pct}% higher than the facility's average ({$avg} m³).";
                $pdo->prepare("INSERT INTO water_notifications (message) VALUES (?)")->execute([$message]);
            }
        }

        echo json_encode(['success' => true, 'record_id' => $recordId]);
        exit;
    }

    http_response_code(405);
    header('Allow: GET, POST');
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal Server Error: ' . $e->getMessage()]);
}
