<?php
// install_energy.php
require_once 'includes/db.php';

header('Content-Type: text/plain');
echo "Starting LGU Energy Consumption Database Setup...\n";

try {
    // 1. Read SQL schema
    $sqlFile = 'sql/utility_energy.sql';
    if (!file_exists($sqlFile)) {
        die("Error: SQL file '{$sqlFile}' not found.\n");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // 2. Execute SQL schema queries
    $pdo->exec($sql);
    echo "Successfully created energy coordination tables!\n";
    
    // 3. Clear existing data
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("TRUNCATE TABLE energy_notifications;");
    $pdo->exec("TRUNCATE TABLE energy_recommendations;");
    $pdo->exec("TRUNCATE TABLE energy_sync_logs;");
    $pdo->exec("TRUNCATE TABLE energy_consumption_records;");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo "Cleaned up old energy records.\n";
    
    // 4. Retrieve seeded asset IDs for linkage mapping
    $assets = $pdo->query("SELECT name, id FROM utility_assets")->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // 5. Seed sample energy consumption records
    $sampleRecords = [
        [
            'record_id' => 'ENG-202606-0001',
            'utility_asset_id' => $assets['Rizal Avenue Solar Streetlight 01'] ?? null,
            'facility_name' => null,
            'asset_type' => 'Streetlight',
            'location' => 'Rizal Avenue corner Recto, Manila',
            'month_year' => '2026-06',
            'consumption_kwh' => 120.50,
            'cost' => 1350.00,
            'data_source' => 'Manual Input',
            'notes' => 'Solar streetlight battery backup load recorded manually.'
        ],
        [
            'record_id' => 'ENG-202606-0002',
            'utility_asset_id' => null,
            'facility_name' => 'Quiapo Municipal Hall Annex',
            'asset_type' => 'Public Facility',
            'location' => 'Quezon Blvd, Quiapo, Manila',
            'month_year' => '2026-06',
            'consumption_kwh' => 4500.00,
            'cost' => 52000.00,
            'data_source' => 'Imported',
            'notes' => 'Imported monthly building meter readings.'
        ],
        [
            'record_id' => 'ENG-202606-0003',
            'utility_asset_id' => $assets['Barangay 386 Water Reservoir Pump 02'] ?? null,
            'facility_name' => null,
            'asset_type' => 'Water Infrastructure',
            'location' => 'San Rafael St, Quiapo, Manila',
            'month_year' => '2026-06',
            'consumption_kwh' => 1850.20,
            'cost' => 21200.00,
            'data_source' => 'Manual Input',
            'notes' => 'Submersible pump motor consumption.'
        ]
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO energy_consumption_records (record_id, utility_asset_id, facility_name, asset_type, location, month_year, consumption_kwh, cost, data_source, notes) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($sampleRecords as $r) {
        $stmt->execute([$r['record_id'], $r['utility_asset_id'], $r['facility_name'], $r['asset_type'], $r['location'], $r['month_year'], $r['consumption_kwh'], $r['cost'], $r['data_source'], $r['notes']]);
    }
    
    // 6. Seed sample recommendations from Energy Efficiency System
    $sampleRecs = [
        [
            'recommendation_title' => 'Retrofit LED heads on non-solar poles',
            'description' => 'Replace remaining 150W high pressure sodium lamps with 50W LED heads to reduce sector usage by 60%.',
            'target_facility_asset' => 'Tondo North Extension',
            'priority_level' => 'High',
            'status' => 'Pending'
        ],
        [
            'recommendation_title' => 'Install solar roof panels on Municipal Annex',
            'description' => 'Install 10kW rooftop solar arrays to shave peak electricity demands during daylight working hours.',
            'target_facility_asset' => 'Quiapo Municipal Hall Annex',
            'priority_level' => 'Medium',
            'status' => 'Acknowledged'
        ]
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO energy_recommendations (recommendation_title, description, target_facility_asset, priority_level, status) 
        VALUES (?, ?, ?, ?, ?)
    ");
    foreach ($sampleRecs as $rec) {
        $stmt->execute([$rec['recommendation_title'], $rec['description'], $rec['target_facility_asset'], $rec['priority_level'], $rec['status']]);
    }

    // 7. Seed sync log
    $pdo->exec("
        INSERT INTO energy_sync_logs (sync_type, records_count, status, payload_exported) 
        VALUES ('Outbound Data Send', 3, 'Successful', '[Simulated CSV payload transmission data]')
    ");
    
    echo "Successfully seeded sample energy records, sync logs, and recommendations!\n";
    echo "Database setup completed successfully!\n";
    
} catch (PDOException $e) {
    die("Database setup failed: " . $e->getMessage() . "\n");
}
