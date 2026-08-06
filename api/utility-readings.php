<?php
/**
 * GET  /api/utility-readings.php?key=...&cprf_facility_id=...
 * POST /api/utility-readings.php?key=...  (JSON body)
 *
 * CPRF submits monthly electric (+ optional water) meter readings per
 * facility. Stored into the existing energy_consumption_records table
 * (extended with a few CPRF-linkage columns) so they flow through the same
 * dashboard/Sync Now pipeline as UMAN's own manually-entered records —
 * see energy_records.php / energy_sync.php / energy_dashboard.php.
 *
 * Idempotent by external_ref: CPRF resubmits the same reading (e.g. after
 * an edit/correction) with the same "CPRF-{reading_id}" ref, which upserts
 * the existing row instead of creating a duplicate.
 *
 * Expected POST payload (see CPRF's frs_uman_build_utility_reading_payload()):
 *   facility_id*   (int)    — CPRF's facilities.id
 *   year*          (int)
 *   month*         (int)    — 1-12
 *   reading_date*  (date)   — YYYY-MM-DD
 *   electric*      (object) — {previous_reading_kwh, current_reading_kwh, consumption_kwh, rate_per_kwh, cost}
 *   water          (object, optional) — {previous_reading_cbm, current_reading_cbm, consumption_cbm, rate_per_cbm, cost}
 *   external_ref*  (str)    — stable idempotency key, e.g. "CPRF-123"
 *   notes          (str, optional)
 *   recorded_by_name (str, optional)
 *
 * High-consumption monitoring: after storing, compares the new reading
 * against the facility's own trailing average (last 3 CPRF-sourced records)
 * and drops an energy_notifications entry when it's notably higher —
 * this is the "UMAN monitors for high usage" half of the CPRF integration.
 */
declare(strict_types=1);

require_once __DIR__ . '/integration_config.php';

uman_require_api_key($UMAN_INTEGRATION_API_KEY);

/** How much higher than trailing average counts as "high" (50% over). */
const CPRF_HIGH_CONSUMPTION_MULTIPLIER = 1.5;
/** Minimum prior CPRF records needed before a baseline comparison is meaningful. */
const CPRF_HIGH_CONSUMPTION_MIN_HISTORY = 1;

/**
 * Idempotently extend energy_consumption_records with CPRF-linkage + water
 * columns, and widen data_source to admit a 'CPRF Integration' value.
 */
function cprf_ensure_energy_schema(PDO $pdo): void
{
    $addCol = static function (string $definition) use ($pdo): void {
        try {
            $pdo->exec("ALTER TABLE energy_consumption_records ADD COLUMN $definition");
        } catch (Throwable $e) {
            // duplicate column: noop
        }
    };
    $addCol("`source_system` VARCHAR(50) NOT NULL DEFAULT 'UMAN' AFTER `record_id`");
    $addCol("`cprf_facility_id` INT NULL AFTER `source_system`");
    $addCol("`external_ref` VARCHAR(60) NULL AFTER `cprf_facility_id`");
    $addCol("`rate_per_kwh` DECIMAL(10,2) NULL AFTER `cost`");
    $addCol("`consumption_water_cbm` DECIMAL(12,2) NULL AFTER `rate_per_kwh`");
    $addCol("`rate_per_water` DECIMAL(10,2) NULL AFTER `consumption_water_cbm`");
    $addCol("`water_cost` DECIMAL(12,2) NULL AFTER `rate_per_water`");

    try {
        $pdo->exec('ALTER TABLE energy_consumption_records
            ADD UNIQUE KEY uk_ecr_external_ref (external_ref),
            ADD INDEX idx_ecr_cprf_facility (cprf_facility_id)');
    } catch (Throwable $e) {
        // index/unique key already exists — noop
    }

    try {
        $pdo->exec("ALTER TABLE energy_consumption_records
            MODIFY COLUMN data_source ENUM('Manual Input','Imported','CPRF Integration') NOT NULL DEFAULT 'Manual Input'");
    } catch (Throwable $e) {
        // widening failed (older MySQL / insufficient privilege) — POST handler
        // falls back to the 'Imported' value in that case, see below.
    }
}

try {
    $pdo = uman_integration_pdo();
    cprf_ensure_energy_schema($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $facilityId = isset($_GET['cprf_facility_id']) ? (int)$_GET['cprf_facility_id'] : 0;

        $sql = "SELECT * FROM energy_consumption_records WHERE source_system = 'CPRF'";
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
        $readingDate = trim((string)($json['reading_date'] ?? ''));
        $electric = is_array($json['electric'] ?? null) ? $json['electric'] : [];
        $water = is_array($json['water'] ?? null) ? $json['water'] : null;
        $externalRef = trim((string)($json['external_ref'] ?? ''));
        $notes = trim((string)($json['notes'] ?? ''));
        $recordedByName = trim((string)($json['recorded_by_name'] ?? ''));

        if ($facilityId <= 0 || $year <= 0 || $month < 1 || $month > 12 || $externalRef === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'facility_id, year, month, and external_ref are required'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (!isset($electric['consumption_kwh']) || !is_numeric($electric['consumption_kwh'])) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'electric.consumption_kwh is required'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $consumptionKwh = (float)$electric['consumption_kwh'];
        $electricCost = isset($electric['cost']) && is_numeric($electric['cost']) ? (float)$electric['cost'] : null;
        $ratePerKwh = isset($electric['rate_per_kwh']) && is_numeric($electric['rate_per_kwh']) ? (float)$electric['rate_per_kwh'] : null;

        $consumptionWater = null;
        $waterCost = null;
        $ratePerWater = null;
        if ($water !== null && isset($water['consumption_cbm']) && is_numeric($water['consumption_cbm'])) {
            $consumptionWater = (float)$water['consumption_cbm'];
            $waterCost = isset($water['cost']) && is_numeric($water['cost']) ? (float)$water['cost'] : null;
            $ratePerWater = isset($water['rate_per_cbm']) && is_numeric($water['rate_per_cbm']) ? (float)$water['rate_per_cbm'] : null;
        }

        $monthYear = sprintf('%04d-%02d', $year, $month);
        $noteParts = [];
        if ($notes !== '') $noteParts[] = $notes;
        if ($recordedByName !== '') $noteParts[] = "Recorded by {$recordedByName} (CPRF)";
        $combinedNotes = $noteParts === [] ? null : implode(' — ', $noteParts);

        // Prefer the widened enum value; fall back to 'Imported' if the
        // MODIFY COLUMN in cprf_ensure_energy_schema() couldn't run (older
        // MySQL / no ALTER privilege) and the enum was never widened.
        $dataSource = 'CPRF Integration';
        try {
            $colCheck = $pdo->query("SHOW COLUMNS FROM energy_consumption_records LIKE 'data_source'")->fetch(PDO::FETCH_ASSOC);
            if ($colCheck && strpos((string)$colCheck['Type'], 'CPRF Integration') === false) {
                $dataSource = 'Imported';
            }
        } catch (Throwable $e) {
            $dataSource = 'Imported';
        }

        // Prefer the real facility name/location CPRF sends; only fall back
        // to a generic label for older CPRF builds that don't send them yet.
        $displayName = $facilityName !== '' ? $facilityName : "CPRF Facility #{$facilityId}";
        $displayLocation = $facilityLocation !== '' ? $facilityLocation : $displayName;

        // Idempotent upsert on external_ref: same reading resubmitted (e.g.
        // after a CPRF-side correction) updates the existing row instead of
        // creating a duplicate.
        $existing = $pdo->prepare('SELECT id, record_id FROM energy_consumption_records WHERE external_ref = ? LIMIT 1');
        $existing->execute([$externalRef]);
        $existingRow = $existing->fetch(PDO::FETCH_ASSOC);

        if ($existingRow) {
            $recordId = (string)$existingRow['record_id'];
            $update = $pdo->prepare('
                UPDATE energy_consumption_records
                SET facility_name = ?, location = ?, month_year = ?, consumption_kwh = ?, cost = ?,
                    rate_per_kwh = ?, consumption_water_cbm = ?, rate_per_water = ?, water_cost = ?,
                    data_source = ?, notes = ?
                WHERE id = ?
            ');
            $update->execute([
                $displayName, $displayLocation, $monthYear,
                $consumptionKwh, $electricCost, $ratePerKwh,
                $consumptionWater, $ratePerWater, $waterCost,
                $dataSource, $combinedNotes,
                (int)$existingRow['id'],
            ]);
        } else {
            $recordId = 'CPRF-ENG-' . date('Ym') . '-' . (int)$facilityId . '-' . substr(md5($externalRef), 0, 6);
            $insert = $pdo->prepare('
                INSERT INTO energy_consumption_records
                    (record_id, source_system, cprf_facility_id, external_ref,
                     utility_asset_id, facility_name, asset_type, location, month_year,
                     consumption_kwh, cost, rate_per_kwh,
                     consumption_water_cbm, rate_per_water, water_cost,
                     data_source, notes)
                VALUES
                    (?, \'CPRF\', ?, ?,
                     NULL, ?, \'CPRF Facility\', ?, ?,
                     ?, ?, ?,
                     ?, ?, ?,
                     ?, ?)
            ');
            $insert->execute([
                $recordId, $facilityId, $externalRef,
                $displayName, $displayLocation, $monthYear,
                $consumptionKwh, $electricCost, $ratePerKwh,
                $consumptionWater, $ratePerWater, $waterCost,
                $dataSource, $combinedNotes,
            ]);
        }

        // ── High-consumption monitoring ──────────────────────────────────
        // Compare against this facility's own trailing average (prior CPRF
        // records only, excluding the one just written) — a simple, self-
        // contained heuristic rather than a fixed global threshold, since
        // "normal" usage varies a lot per facility.
        $flaggedHigh = false;
        try {
            $historyStmt = $pdo->prepare("
                SELECT consumption_kwh, consumption_water_cbm
                FROM energy_consumption_records
                WHERE source_system = 'CPRF' AND cprf_facility_id = ? AND external_ref != ?
                ORDER BY date_recorded DESC
                LIMIT 3
            ");
            $historyStmt->execute([$facilityId, $externalRef]);
            $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($history) >= CPRF_HIGH_CONSUMPTION_MIN_HISTORY) {
                $avgKwh = array_sum(array_map(static fn($r) => (float)$r['consumption_kwh'], $history)) / count($history);
                $highElectric = $avgKwh > 0 && $consumptionKwh > $avgKwh * CPRF_HIGH_CONSUMPTION_MULTIPLIER;

                $waterHistory = array_values(array_filter($history, static fn($r) => $r['consumption_water_cbm'] !== null));
                $highWater = false;
                if ($consumptionWater !== null && count($waterHistory) > 0) {
                    $avgWater = array_sum(array_map(static fn($r) => (float)$r['consumption_water_cbm'], $waterHistory)) / count($waterHistory);
                    $highWater = $avgWater > 0 && $consumptionWater > $avgWater * CPRF_HIGH_CONSUMPTION_MULTIPLIER;
                }

                if ($highElectric || $highWater) {
                    $flaggedHigh = true;
                    $bits = [];
                    if ($highElectric) $bits[] = sprintf('%.2f kWh (avg %.2f)', $consumptionKwh, $avgKwh);
                    if ($highWater) $bits[] = sprintf('%.2f m³ (avg %.2f)', $consumptionWater, $avgWater ?? 0);
                    $msg = "High consumption — CPRF Facility #{$facilityId}, {$monthYear}: " . implode(' · ', $bits);
                    $pdo->prepare('INSERT INTO energy_notifications (message) VALUES (?)')->execute([$msg]);
                }
            }
        } catch (Throwable $e) {
            // Monitoring is best-effort — never fail the submission over it.
        }

        echo json_encode([
            'success' => true,
            'message' => $existingRow ? 'Reading updated' : 'Reading recorded',
            'record_id' => $recordId,
            'flagged_high' => $flaggedHigh,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('UMAN utility-readings API: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error processing request: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
