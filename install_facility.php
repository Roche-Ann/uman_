<?php
// install_facility.php
require_once 'includes/db.php';

header('Content-Type: text/plain');
echo "Starting LGU Public Facilities Utility Database Setup...\n";

try {
    // 1. Read SQL schema
    $sqlFile = 'sql/utility_facility.sql';
    if (!file_exists($sqlFile)) {
        die("Error: SQL file '{$sqlFile}' not found.\n");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // 2. Execute SQL schema queries
    $pdo->exec($sql);
    echo "Successfully created public facilities coordination tables!\n";
    
    // 3. Clear existing data
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("TRUNCATE TABLE facility_bookings;");
    $pdo->exec("TRUNCATE TABLE facility_notifications;");
    $pdo->exec("TRUNCATE TABLE facility_maintenance_requests;");
    $pdo->exec("TRUNCATE TABLE facility_incidents;");
    $pdo->exec("TRUNCATE TABLE facility_utility_assets;");
    $pdo->exec("TRUNCATE TABLE facility_utility_status;");
    $pdo->exec("TRUNCATE TABLE public_facilities;");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo "Cleaned up old facilities records.\n";
    
    // 4. Retrieve seeded asset IDs for linkage mapping
    $assets = $pdo->query("SELECT name, id FROM utility_assets")->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // 5. Seed sample public facilities
    $sampleFacilities = [
        [
            'facility_id' => 'FAC-PARK-0001',
            'name' => 'Barangay 386 Plaza and Park',
            'facility_type' => 'Park',
            'location' => 'San Rafael St, Quiapo, Manila',
            'latitude' => 14.600000,
            'longitude' => 120.992000,
            'utility_status' => 'Partially Ready',
            'description' => 'Public park featuring community playgrounds and gardens.',
            'water' => 1, 'elec' => 1, 'drainage' => 1, 'lighting' => 0
        ],
        [
            'facility_id' => 'FAC-GYM-0002',
            'name' => 'Sampaloc District Community Gymnasium',
            'facility_type' => 'Gymnasium',
            'location' => 'Sampaloc, Manila',
            'latitude' => 14.612000,
            'longitude' => 120.989000,
            'utility_status' => 'Fully Ready',
            'description' => 'Multi-purpose gymnasium hosting sports events and town halls.',
            'water' => 1, 'elec' => 1, 'drainage' => 1, 'lighting' => 1
        ],
        [
            'facility_id' => 'FAC-EVAC-0003',
            'name' => 'Quiapo Disaster Evacuation Center',
            'facility_type' => 'Evacuation Center',
            'location' => 'Quezon Blvd, Quiapo, Manila',
            'latitude' => 14.598000,
            'longitude' => 120.985000,
            'utility_status' => 'Not Ready',
            'description' => 'LGU disaster response center equipped to house up to 500 residents.',
            'water' => 0, 'elec' => 1, 'drainage' => 0, 'lighting' => 1
        ]
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO public_facilities (facility_id, name, facility_type, location, latitude, longitude, utility_status, description) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $statusStmt = $pdo->prepare("
        INSERT INTO facility_utility_status (public_facility_id, water_available, electricity_available, drainage_ok, lighting_ok) 
        VALUES (?, ?, ?, ?, ?)
    ");

    $linkStmt = $pdo->prepare("
        INSERT INTO facility_utility_assets (public_facility_id, utility_asset_id, association_notes) 
        VALUES (?, ?, ?)
    ");
    
    foreach ($sampleFacilities as $f) {
        $stmt->execute([$f['facility_id'], $f['name'], $f['facility_type'], $f['location'], $f['latitude'], $f['longitude'], $f['utility_status'], $f['description']]);
        $fid = $pdo->lastInsertId();
        
        // Insert checklist status values
        $statusStmt->execute([$fid, $f['water'], $f['elec'], $f['drainage'], $f['lighting']]);
        
        // Link related assets if seeded
        if ($f['facility_type'] === 'Park' && isset($assets['Rizal Avenue Solar Streetlight 01'])) {
            $linkStmt->execute([$fid, $assets['Rizal Avenue Solar Streetlight 01'], 'Powers park border walk paths.']);
        } elseif ($f['facility_type'] === 'Evacuation Center' && isset($assets['Barangay 386 Water Reservoir Pump 02'])) {
            $linkStmt->execute([$fid, $assets['Barangay 386 Water Reservoir Pump 02'], 'Direct supply pump for evacuation drinking water.']);
        }
    }
    
    // 6. Seed facility incidents
    $facilityMap = $pdo->query("SELECT name, id FROM public_facilities")->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $sampleIncidents = [
        [
            'public_facility_id' => $facilityMap['Barangay 386 Plaza and Park'],
            'incident_type' => 'Lighting Failure',
            'description' => 'Three path light fixtures damaged due to heavy storms. Park is dark at night.',
            'status' => 'Active'
        ],
        [
            'public_facility_id' => $facilityMap['Quiapo Disaster Evacuation Center'],
            'incident_type' => 'Water Interruption',
            'description' => 'Municipal water main leak has temporarily cut supply line pressure.',
            'status' => 'Active'
        ]
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO facility_incidents (public_facility_id, incident_type, description, status) 
        VALUES (?, ?, ?, ?)
    ");
    foreach ($sampleIncidents as $inc) {
        $stmt->execute([$inc['public_facility_id'], $inc['incident_type'], $inc['description'], $inc['status']]);
    }

    // 7. Seed bookings reservation schedules (read-only overlay)
    $sampleBookings = [
        [
            'public_facility_id' => $facilityMap['Sampaloc District Community Gymnasium'],
            'event_name' => 'Inter-Barangay Basketball Tournament Finals',
            'expected_attendance' => 450,
            'booking_date' => date('Y-m-d', strtotime('+3 days')),
            'start' => '13:00:00', 'end' => '18:00:00'
        ],
        [
            'public_facility_id' => $facilityMap['Barangay 386 Plaza and Park'],
            'event_name' => 'Community Nutrition Council Assembly',
            'expected_attendance' => 80,
            'booking_date' => date('Y-m-d', strtotime('+5 days')),
            'start' => '08:00:00', 'end' => '12:00:00'
        ]
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO facility_bookings (public_facility_id, event_name, expected_attendance, booking_date, start_time, end_time) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    foreach ($sampleBookings as $bk) {
        $stmt->execute([$bk['public_facility_id'], $bk['event_name'], $bk['expected_attendance'], $bk['booking_date'], $bk['start'], $bk['end']]);
    }

    // 8. Seed alerts
    $pdo->exec("
        INSERT INTO facility_notifications (message) 
        VALUES 
        ('Incident logged: Lighting Failure at Barangay 386 Plaza and Park.'),
        ('Evacuation Center utility status downgraded to Not Ready due to Water Interruption.')
    ");
    
    echo "Successfully seeded sample facilities, utility checklists, and bookings!\n";
    echo "Database setup completed successfully!\n";
    
} catch (PDOException $e) {
    die("Database setup failed: " . $e->getMessage() . "\n");
}
