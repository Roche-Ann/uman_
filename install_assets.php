<?php
// install_assets.php
require_once 'includes/db.php';

header('Content-Type: text/plain');
echo "Starting Utility Assets Database Setup...\n";

try {
    // 1. Read SQL schema
    $sqlFile = 'sql/utility_assets.sql';
    if (!file_exists($sqlFile)) {
        die("Error: SQL file '{$sqlFile}' not found.\n");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Relax strict mode for installation
    $pdo->exec("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");
    
    // 2. Execute SQL schema queries
    $pdo->exec($sql);
    echo "Successfully created tables and seeded asset types!\n";
    
    // 3. Clear existing seed assets to avoid duplicates
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("TRUNCATE TABLE asset_notifications;");
    $pdo->exec("TRUNCATE TABLE asset_images;");
    $pdo->exec("TRUNCATE TABLE asset_locations;");
    $pdo->exec("TRUNCATE TABLE asset_status_logs;");
    $pdo->exec("TRUNCATE TABLE asset_audit_logs;");
    $pdo->exec("TRUNCATE TABLE asset_inspections;");
    $pdo->exec("TRUNCATE TABLE utility_assets;");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo "Cleaned up old asset tables.\n";
    
    // 4. Retrieve asset type IDs
    $types = $pdo->query("SELECT name, id FROM asset_types")->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // 5. Seed sample assets
    $sampleAssets = [
        [
            'asset_id' => 'AST-202601-0001',
            'name' => 'Rizal Avenue Solar Streetlight 01',
            'asset_type_id' => $types['Streetlight'],
            'location' => 'Rizal Avenue corner Recto, Manila',
            'latitude' => 14.604167,
            'longitude' => 120.982222,
            'date_installed' => '2026-01-15',
            'status' => 'Operational',
            'condition' => 'Good',
            'description' => 'Solar streetlight with 100W LED bulb. Automatic twilight sensor.',
            'responsible_office' => 'City General Services Office'
        ],
        [
            'asset_id' => 'AST-202601-0002',
            'name' => 'Quezon Boulevard Drainage Gate A',
            'asset_type_id' => $types['Drainage System'],
            'location' => 'Quezon Blvd, Quiapo, Manila',
            'latitude' => 14.598333,
            'longitude' => 120.985000,
            'date_installed' => '2025-10-10',
            'status' => 'Operational',
            'condition' => 'Fair',
            'description' => 'Main storm drainage outflow gate. Reported silt build-up.',
            'responsible_office' => 'City Engineering Office'
        ],
        [
            'asset_id' => 'AST-202602-0003',
            'name' => 'Espana Boulevard Water Pipeline Segment 4',
            'asset_type_id' => $types['Water Pipeline'],
            'location' => 'España Blvd corner Lacson, Manila',
            'latitude' => 14.611111,
            'longitude' => 120.993889,
            'date_installed' => '2024-05-20',
            'status' => 'Non-Operational',
            'condition' => 'Critical',
            'description' => '12-inch main cast iron distribution pipe. Minor pressure leak detected.',
            'responsible_office' => 'LGU Water District'
        ],
        [
            'asset_id' => 'AST-202602-0004',
            'name' => 'Magsaysay Boulevard Electrical Pole E-45',
            'asset_type_id' => $types['Electrical Utility Pole'],
            'location' => 'Magsaysay Blvd, Santa Mesa, Manila',
            'latitude' => 14.601944,
            'longitude' => 121.008333,
            'date_installed' => '2023-11-12',
            'status' => 'Operational',
            'condition' => 'Good',
            'description' => 'Concrete pole supporting streetlights and LGU surveillance cameras.',
            'responsible_office' => 'City Information Technology Office'
        ],
        [
            'asset_id' => 'AST-202603-0005',
            'name' => 'Barangay 386 Water Reservoir Pump 02',
            'asset_type_id' => $types['Public Utility Infrastructure'],
            'location' => 'San Rafael St, Quiapo, Manila',
            'latitude' => 14.595556,
            'longitude' => 120.990278,
            'date_installed' => '2025-02-28',
            'status' => 'Under Maintenance',
            'condition' => 'Poor',
            'description' => 'Submersible pump motor. Scheduled periodic cleaning.',
            'responsible_office' => 'LGU Water District'
        ],
        [
            'asset_id' => 'AST-202603-0006',
            'name' => 'Taft Avenue Solar Streetlight 12',
            'asset_type_id' => $types['Streetlight'],
            'location' => 'Taft Avenue corner Vito Cruz, Manila',
            'latitude' => 14.563889,
            'longitude' => 120.994722,
            'date_installed' => '2026-03-05',
            'status' => 'Operational',
            'condition' => 'Excellent',
            'description' => 'Solar-powered pole with battery storage box secured at 3m height.',
            'responsible_office' => 'City General Services Office'
        ]
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO utility_assets (asset_id, name, asset_type_id, quantity, location, latitude, longitude, date_installed, status, `condition`, description, responsible_office) 
        VALUES (:asset_id, :name, :asset_type_id, :quantity, :location, :latitude, :longitude, :date_installed, :status, :condition, :description, :responsible_office)
    ");
    
    $logStmt = $pdo->prepare("
        INSERT INTO asset_status_logs (utility_asset_id, old_status, new_status, changed_by, notes) 
        VALUES (:uaid, NULL, :new_status, 1, 'Initial seeding during system installation.')
    ");
    
    foreach ($sampleAssets as $asset) {
        $asset['quantity'] = $asset['quantity'] ?? 1;
        $stmt->execute($asset);
        $assetId = $pdo->lastInsertId();
        
        // Log status
        $logStmt->execute([
            ':uaid' => $assetId,
            ':new_status' => $asset['status'] . ' / ' . $asset['condition']
        ]);
        
        // Trigger initial notification for damaged/needs inspection
        if ($asset['condition'] === 'Critical') {
            $pdo->prepare("
                INSERT INTO asset_notifications (type, message) 
                VALUES ('reported_damaged', :msg)
            ")->execute([
                ':msg' => "ALERT: Asset {$asset['asset_id']} ({$asset['name']}) is reported as Critical condition."
            ]);
        } elseif ($asset['condition'] === 'Fair' || $asset['condition'] === 'Poor') {
            $pdo->prepare("
                INSERT INTO asset_notifications (type, message) 
                VALUES ('status_changed', :msg)
            ")->execute([
                ':msg' => "Warning: Asset {$asset['asset_id']} condition changed to Needs Inspection."
            ]);
        }
    }
    
    echo "Successfully seeded sample utility assets and status logs!\n";
    echo "Database setup completed successfully!\n";
    
} catch (PDOException $e) {
    die("Database setup failed: " . $e->getMessage() . "\n");
}
