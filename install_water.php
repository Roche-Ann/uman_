<?php
// install_water.php
require_once 'includes/db.php';

header('Content-Type: text/plain');
echo "Starting LGU Water Consumption Database Setup...\n";

try {
    // 1. Read SQL schema
    $sqlFile = 'sql/utility_water.sql';
    if (!file_exists($sqlFile)) {
        die("Error: SQL file '{$sqlFile}' not found.\n");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // 2. Execute SQL schema queries
    $pdo->exec($sql);
    echo "Successfully created water coordination tables!\n";
    
    // 3. Clear existing data
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("TRUNCATE TABLE water_notifications;");
    $pdo->exec("TRUNCATE TABLE water_recommendations;");
    $pdo->exec("TRUNCATE TABLE water_sync_logs;");
    $pdo->exec("TRUNCATE TABLE water_consumption_records;");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo "Cleaned up old water records.\n";
    
    // 4. Retrieve seeded asset IDs for linkage mapping
    $assets = $pdo->query("SELECT name, id FROM utility_assets")->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // 5. Seed sample water consumption records
    $sampleRecords = [
        [
            'record_id' => 'WTR-202606-0001',
            'utility_asset_id' => $assets['Espana Boulevard Water Pipeline Segment 4'] ?? null,
            'facility_name' => null,
            'location' => 'España Blvd corner Lacson, Manila',
            'month_year' => '2026-06',
            'previous_reading' => 1200.00,
            'current_reading' => 1250.50,
            'consumption_m3' => 50.50,
            'rate_per_m3' => 68.02,
            'cost' => 3435.01,
            'data_source' => 'Manual Input',
            'notes' => ' España Blvd sector manual meter reading.'
        ],
        [
            'record_id' => 'WTR-202606-0002',
            'utility_asset_id' => null,
            'facility_name' => 'Quiapo Municipal Hall Annex',
            'location' => 'Quezon Blvd, Quiapo, Manila',
            'month_year' => '2026-06',
            'previous_reading' => 4500.00,
            'current_reading' => 4820.00,
            'consumption_m3' => 320.00,
            'rate_per_m3' => 68.02,
            'cost' => 21766.40,
            'data_source' => 'Imported',
            'notes' => 'Imported monthly building water meter readings.'
        ],
        [
            'record_id' => 'WTR-202606-0003',
            'utility_asset_id' => $assets['Barangay 386 Water Reservoir Pump 02'] ?? null,
            'facility_name' => null,
            'location' => 'San Rafael St, Quiapo, Manila',
            'month_year' => '2026-06',
            'previous_reading' => 8900.00,
            'current_reading' => 9150.20,
            'consumption_m3' => 250.20,
            'rate_per_m3' => 68.02,
            'cost' => 17018.60,
            'data_source' => 'Manual Input',
            'notes' => 'Submersible pump flow rate tracking.'
        ]
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO water_consumption_records (record_id, utility_asset_id, facility_name, location, month_year, previous_reading, current_reading, consumption_m3, rate_per_m3, cost, data_source, notes) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($sampleRecords as $r) {
        $stmt->execute([
            $r['record_id'], 
            $r['utility_asset_id'], 
            $r['facility_name'], 
            $r['location'], 
            $r['month_year'], 
            $r['previous_reading'],
            $r['current_reading'],
            $r['consumption_m3'],
            $r['rate_per_m3'],
            $r['cost'],
            $r['data_source'], 
            $r['notes']
        ]);
    }
    
    // 6. Seed sample recommendations from Water Efficiency System
    $sampleRecs = [
        [
            'recommendation_title' => 'Fix valve leak at España Segment 4',
            'description' => 'Inspect and replace degraded rubber seals on España Boulevard segment to stop pressure drop and resource loss.',
            'target_facility_asset' => 'España Blvd corner Lacson, Manila',
            'priority_level' => 'High',
            'status' => 'Pending'
        ],
        [
            'recommendation_title' => 'Optimize pump runtime at Barangay 386 Pump 02',
            'description' => 'Adjust timer intervals to avoid run dry situations during peak low-pressure hours.',
            'target_facility_asset' => 'Barangay 386 Water Reservoir Pump 02',
            'priority_level' => 'Medium',
            'status' => 'Acknowledged'
        ]
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO water_recommendations (recommendation_title, description, target_facility_asset, priority_level, status) 
        VALUES (?, ?, ?, ?, ?)
    ");
    foreach ($sampleRecs as $rec) {
        $stmt->execute([$rec['recommendation_title'], $rec['description'], $rec['target_facility_asset'], $rec['priority_level'], $rec['status']]);
    }

    // 7. Seed sync log
    $pdo->exec("
        INSERT INTO water_sync_logs (sync_type, records_count, status, payload_exported) 
        VALUES ('Outbound Data Send', 3, 'Successful', '[Simulated CSV payload transmission data for water records]')
    ");
    
    echo "Successfully seeded sample water records, sync logs, and recommendations!\n";
    echo "Database setup completed successfully!\n";
    
} catch (PDOException $e) {
    die("Database setup failed: " . $e->getMessage() . "\n");
}
