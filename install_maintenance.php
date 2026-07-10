<?php
// install_maintenance.php
require_once 'includes/db.php';

header('Content-Type: text/plain');
echo "Starting LGU Maintenance Coordination Database Setup...\n";

try {
    // 1. Read SQL schema
    $sqlFile = 'sql/utility_maintenance.sql';
    if (!file_exists($sqlFile)) {
        die("Error: SQL file '{$sqlFile}' not found.\n");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // 2. Execute SQL schema queries
    $pdo->exec($sql);
    echo "Successfully created maintenance coordination tables!\n";
    
    // 3. Clear existing data
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("TRUNCATE TABLE maintenance_history;");
    $pdo->exec("TRUNCATE TABLE maintenance_notifications;");
    $pdo->exec("TRUNCATE TABLE maintenance_asset_links;");
    $pdo->exec("TRUNCATE TABLE maintenance_status_logs;");
    $pdo->exec("TRUNCATE TABLE maintenance_forwarding_logs;");
    $pdo->exec("TRUNCATE TABLE maintenance_requests;");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo "Cleaned up old maintenance coordination records.\n";
    
    // 4. Retrieve seeded asset IDs for linkage mapping
    $assets = $pdo->query("SELECT name, id FROM utility_assets")->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // 5. Seed sample maintenance requests
    $sampleRequests = [
        [
            'request_id' => 'MNT-202606-0001',
            'utility_asset_id' => $assets['Espana Boulevard Water Pipeline Segment 4'] ?? null,
            'source' => 'Resident Report',
            'description' => 'Leakage repair request at España corner Lacson cast iron pipeline section.',
            'priority' => 'High',
            'location' => 'España Blvd corner Lacson, Manila',
            'status' => 'Accepted by Maintenance System',
            'external_ref_id' => 'EXT-WO-9081',
            'forward_status' => 'Accepted'
        ],
        [
            'request_id' => 'MNT-202606-0002',
            'utility_asset_id' => $assets['Rizal Avenue Solar Streetlight 01'] ?? null,
            'source' => 'Resident Report',
            'description' => 'Flickering and damaged twilight sensor bulb replacement.',
            'priority' => 'Medium',
            'location' => 'Rizal Avenue corner Recto, Manila',
            'status' => 'Forwarded',
            'external_ref_id' => 'EXT-WO-9082',
            'forward_status' => 'Sent'
        ],
        [
            'request_id' => 'MNT-202606-0003',
            'utility_asset_id' => $assets['Quezon Boulevard Drainage Gate A'] ?? null,
            'source' => 'Asset Monitoring',
            'description' => 'Silt build-up cleaning and metal grate realignment.',
            'priority' => 'High',
            'location' => 'Quezon Blvd, Quiapo, Manila',
            'status' => 'In Progress',
            'external_ref_id' => 'EXT-WO-9083',
            'forward_status' => 'Accepted'
        ],
        [
            'request_id' => 'MNT-202606-0004',
            'utility_asset_id' => $assets['Barangay 386 Water Reservoir Pump 02'] ?? null,
            'source' => 'Emergency Alert',
            'description' => 'Urgent submersible pump calibration request due to low pressure complaints.',
            'priority' => 'Emergency',
            'location' => 'San Rafael St, Quiapo, Manila',
            'status' => 'Created',
            'external_ref_id' => null,
            'forward_status' => 'Not Sent'
        ]
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO maintenance_requests (request_id, utility_asset_id, source, description, priority, location, status) 
        VALUES (:request_id, :utility_asset_id, :source, :description, :priority, :location, :status)
    ");
    
    $forwardStmt = $pdo->prepare("
        INSERT INTO maintenance_forwarding_logs (maintenance_request_id, target_system, external_ref_id, status) 
        VALUES (:mrid, 'Maintenance System', :ref, :fstatus)
    ");
    
    $logStmt = $pdo->prepare("
        INSERT INTO maintenance_status_logs (maintenance_request_id, old_status, new_status, changed_by, notes) 
        VALUES (:mrid, NULL, :status, 1, 'Initial request registration in coordinator.')
    ");
    
    $linkStmt = $pdo->prepare("
        INSERT INTO maintenance_asset_links (maintenance_request_id, utility_asset_id) 
        VALUES (:mrid, :uaid)
    ");

    $historyStmt = $pdo->prepare("
        INSERT INTO maintenance_history (maintenance_request_id, action, performed_by, details) 
        VALUES (:mrid, :action, 1, :details)
    ");
    
    foreach ($sampleRequests as $req) {
        $stmt->execute([
            ':request_id' => $req['request_id'],
            ':utility_asset_id' => $req['utility_asset_id'],
            ':source' => $req['source'],
            ':description' => $req['description'],
            ':priority' => $req['priority'],
            ':location' => $req['location'],
            ':status' => $req['status']
        ]);
        
        $mrid = $pdo->lastInsertId();
        
        // Log status log
        $logStmt->execute([
            ':mrid' => $mrid,
            ':status' => $req['status']
        ]);
        
        // Asset link
        if ($req['utility_asset_id']) {
            $linkStmt->execute([
                ':mrid' => $mrid,
                ':uaid' => $req['utility_asset_id']
            ]);
        }
        
        // Forwarding log
        if ($req['forward_status'] !== 'Not Sent') {
            $forwardStmt->execute([
                ':mrid' => $mrid,
                ':ref' => $req['external_ref_id'],
                ':fstatus' => $req['forward_status']
            ]);
        }
        
        // History log
        $historyStmt->execute([
            ':mrid' => $mrid,
            ':action' => 'Request Setup',
            ':details' => "Request logged from source: {$req['source']} with priority: {$req['priority']}."
        ]);

        // Notifications
        if ($req['priority'] === 'Emergency') {
            $pdo->prepare("
                INSERT INTO maintenance_notifications (user_id, message) 
                VALUES (1, :msg)
            ")->execute([
                ':msg' => "EMERGENCY: Urgent maintenance coordination needed for request {$req['request_id']}!"
            ]);
        } else {
            $pdo->prepare("
                INSERT INTO maintenance_notifications (user_id, message) 
                VALUES (1, :msg)
            ")->execute([
                ':msg' => "New maintenance request {$req['request_id']} registered in system."
            ]);
        }
    }
    
    echo "Successfully seeded sample maintenance requests, forwarding logs, and history!\n";
    echo "Database setup completed successfully!\n";
    
} catch (PDOException $e) {
    die("Database setup failed: " . $e->getMessage() . "\n");
}
