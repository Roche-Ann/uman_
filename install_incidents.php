<?php
// install_incidents.php
require_once 'includes/db.php';

header('Content-Type: text/plain');
echo "Starting LGU Resident Reports and Incidents Database Setup...\n";

try {
    // 1. Read SQL schema
    $sqlFile = 'sql/utility_incidents.sql';
    if (!file_exists($sqlFile)) {
        die("Error: SQL file '{$sqlFile}' not found.\n");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // 2. Execute SQL schema queries
    $pdo->exec($sql);
    echo "Successfully created incident tables and seeded categories!\n";
    
    // 3. Clear existing data
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("TRUNCATE TABLE incident_notifications;");
    $pdo->exec("TRUNCATE TABLE incident_images;");
    $pdo->exec("TRUNCATE TABLE incident_forwarding_logs;");
    $pdo->exec("TRUNCATE TABLE incident_status_logs;");
    $pdo->exec("TRUNCATE TABLE incident_asset_links;");
    $pdo->exec("TRUNCATE TABLE utility_incidents;");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo "Cleaned up old incident records.\n";
    
    // 4. Retrieve category IDs
    $categories = $pdo->query("SELECT name, id FROM incident_categories")->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // 5. Retrieve seeded asset IDs for linkage mapping
    $assets = $pdo->query("SELECT name, id FROM utility_assets")->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // 6. Seed sample incidents (simulating resident reports)
    $sampleIncidents = [
        [
            'incident_id' => 'INC-202606-0001',
            'category_id' => $categories['Water Leak'],
            'description' => 'Water spraying out of the pipe junction under the street corner. Flow is strong enough to flood the gutter.',
            'location' => 'España Blvd corner Lacson, Manila',
            'latitude' => 14.611111,
            'longitude' => 120.993889,
            'status' => 'Forwarded to Maintenance System',
            'priority' => 'High',
            'resident_id' => 3, // Assuming user ID 3 (citizen)
            'asset_name' => 'Espana Boulevard Water Pipeline Segment 4'
        ],
        [
            'incident_id' => 'INC-202606-0002',
            'category_id' => $categories['Broken Streetlight'],
            'description' => 'Solar streetlight bulb has been flickering for three days, and now it has completely gone dark. Area is unsafe at night.',
            'location' => 'Rizal Avenue corner Recto, Manila',
            'latitude' => 14.604167,
            'longitude' => 120.982222,
            'status' => 'Under Review',
            'priority' => 'Medium',
            'resident_id' => 3,
            'asset_name' => 'Rizal Avenue Solar Streetlight 01'
        ],
        [
            'incident_id' => 'INC-202606-0003',
            'category_id' => $categories['Drainage Blockage'],
            'description' => 'Accumulated plastic trash and mud block the drainage grating, preventing rainwater flow, leading to flooding.',
            'location' => 'Quezon Blvd, Quiapo, Manila',
            'latitude' => 14.598333,
            'longitude' => 120.985000,
            'status' => 'Verified',
            'priority' => 'High',
            'resident_id' => 3,
            'asset_name' => 'Quezon Boulevard Drainage Gate A'
        ],
        [
            'incident_id' => 'INC-202606-0004',
            'category_id' => $categories['Electrical Issue'],
            'description' => 'Sparking electrical connections and low hanging telecom cables near tree branches.',
            'location' => 'P. Noval St, Sampaloc, Manila',
            'latitude' => 14.608333,
            'longitude' => 120.989444,
            'status' => 'Submitted',
            'priority' => 'Emergency',
            'resident_id' => 3,
            'asset_name' => null // Unlinked asset (unidentified location report)
        ]
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO utility_incidents (incident_id, category_id, description, location, latitude, longitude, status, priority, resident_id) 
        VALUES (:incident_id, :category_id, :description, :location, :latitude, :longitude, :status, :priority, :resident_id)
    ");
    
    $logStmt = $pdo->prepare("
        INSERT INTO incident_status_logs (utility_incident_id, old_status, new_status, changed_by, notes) 
        VALUES (:uiid, NULL, :status, 3, 'Resident report submitted via portal.')
    ");
    
    $linkStmt = $pdo->prepare("
        INSERT INTO incident_asset_links (utility_incident_id, utility_asset_id) 
        VALUES (:uiid, :uaid)
    ");

    $forwardStmt = $pdo->prepare("
        INSERT INTO incident_forwarding_logs (utility_incident_id, target_system, forwarded_by, status) 
        VALUES (:uiid, 'Maintenance Management System', 1, 'Dispatched')
    ");
    
    foreach ($sampleIncidents as $inc) {
        $stmt->execute([
            ':incident_id' => $inc['incident_id'],
            ':category_id' => $inc['category_id'],
            ':description' => $inc['description'],
            ':location' => $inc['location'],
            ':latitude' => $inc['latitude'],
            ':longitude' => $inc['longitude'],
            ':status' => $inc['status'],
            ':priority' => $inc['priority'],
            ':resident_id' => $inc['resident_id']
        ]);
        
        $uiid = $pdo->lastInsertId();
        
        // Log status log
        $logStmt->execute([
            ':uiid' => $uiid,
            ':status' => $inc['status']
        ]);
        
        // Link to asset if specified
        if ($inc['asset_name'] && isset($assets[$inc['asset_name']])) {
            $linkStmt->execute([
                ':uiid' => $uiid,
                ':uaid' => $assets[$inc['asset_name']]
            ]);
        }
        
        // Log forwarding log if forwarded
        if ($inc['status'] === 'Forwarded to Maintenance System') {
            $forwardStmt->execute([':uiid' => $uiid]);
        }
        
        // Trigger notifications
        if ($inc['priority'] === 'Emergency') {
            $pdo->prepare("
                INSERT INTO incident_notifications (user_id, message) 
                VALUES (1, :msg)
            ")->execute([
                ':msg' => "EMERGENCY: Incident {$inc['incident_id']} reported at {$inc['location']}. Immediate action needed!"
            ]);
        } else {
            $pdo->prepare("
                INSERT INTO incident_notifications (user_id, message) 
                VALUES (1, :msg)
            ")->execute([
                ':msg' => "New incident {$inc['incident_id']} reported: {$inc['description']}"
            ]);
        }
    }
    
    echo "Successfully seeded sample incidents, logs, and notifications!\n";
    echo "Database setup completed successfully!\n";
    
} catch (PDOException $e) {
    die("Database setup failed: " . $e->getMessage() . "\n");
}
