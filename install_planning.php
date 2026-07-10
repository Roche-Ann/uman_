<?php
// install_planning.php
require_once 'includes/db.php';

header('Content-Type: text/plain');
echo "Starting LGU Utility Planning and Readiness Database Setup...\n";

try {
    // 1. Read SQL schema
    $sqlFile = 'sql/utility_planning.sql';
    if (!file_exists($sqlFile)) {
        die("Error: SQL file '{$sqlFile}' not found.\n");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // 2. Execute SQL schema queries
    $pdo->exec($sql);
    echo "Successfully created planning coordination tables!\n";
    
    // 3. Clear existing data
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("TRUNCATE TABLE planning_notifications;");
    $pdo->exec("TRUNCATE TABLE planning_coordination_logs;");
    $pdo->exec("TRUNCATE TABLE utility_capacity_records;");
    $pdo->exec("TRUNCATE TABLE development_projects;");
    $pdo->exec("TRUNCATE TABLE utility_expansion_requests;");
    $pdo->exec("TRUNCATE TABLE utility_coverage_records;");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo "Cleaned up old planning records.\n";
    
    // 4. Seed utility coverage records
    $sampleCoverage = [
        ['area_name' => 'Sampaloc District Zone A', 'latitude' => 14.612500, 'longitude' => 120.990000, 'radius_meters' => 600, 'coverage_type' => 'Water Supply', 'coverage_status' => 'Fully Covered', 'remarks' => 'Direct connection to Manila main pipelines. Clean water pressure is optimal.'],
        ['area_name' => 'Quiapo Commercial Hub', 'latitude' => 14.598000, 'longitude' => 120.985000, 'radius_meters' => 500, 'coverage_type' => 'Drainage', 'coverage_status' => 'Partially Covered', 'remarks' => 'Old underground culverts need dredging during typhoon season. Silt risk.'],
        ['area_name' => 'Tondo North Extension', 'latitude' => 14.625000, 'longitude' => 120.965000, 'radius_meters' => 700, 'coverage_type' => 'Streetlight', 'coverage_status' => 'Not Covered', 'remarks' => 'Public safety concerns reported. Road sections lack functional municipal poles.'],
        ['area_name' => 'Barangay 386 Residential Block', 'latitude' => 14.600000, 'longitude' => 120.992000, 'radius_meters' => 400, 'coverage_type' => 'Water Supply', 'coverage_status' => 'Partially Covered', 'remarks' => 'Upper floor apartments report water pressure drops during peak hours.'],
        ['area_name' => 'Ermita Tourist Strip', 'latitude' => 14.578000, 'longitude' => 120.980000, 'radius_meters' => 600, 'coverage_type' => 'Electrical', 'coverage_status' => 'Fully Covered', 'remarks' => 'Redundant grids installed. Safety monitoring operational.']
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO utility_coverage_records (area_name, latitude, longitude, radius_meters, coverage_type, coverage_status, remarks) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($sampleCoverage as $c) {
        $stmt->execute([$c['area_name'], $c['latitude'], $c['longitude'], $c['radius_meters'], $c['coverage_type'], $c['coverage_status'], $c['remarks']]);
    }
    
    // 5. Seed utility expansion requests
    $sampleExpansions = [
        ['request_id' => 'PLN-EXP-202606-0001', 'area_location' => 'Tondo North Extension', 'utility_type' => 'Streetlight', 'reason' => 'Upgrade streetlights to solar LED due to rising citizen incident reports at night.', 'priority' => 'High', 'estimated_scope' => 'Install 45 solar poles and LED heads.', 'status' => 'Under Review'],
        ['request_id' => 'PLN-EXP-202606-0002', 'area_location' => 'Quiapo Commercial Hub', 'utility_type' => 'Drainage', 'reason' => 'Upgrade old brick drainage system to larger concrete pipe box culverts.', 'priority' => 'High', 'estimated_scope' => 'Lay 150m concrete box culverts.', 'status' => 'Pending'],
        ['request_id' => 'PLN-EXP-202606-0003', 'area_location' => 'Sampaloc District Zone A', 'utility_type' => 'Water Supply', 'reason' => 'Extend existing water main pipelines into newly zoned subdivision phase.', 'priority' => 'Medium', 'estimated_scope' => 'Extend 100m pipeline sections.', 'status' => 'Approved']
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO utility_expansion_requests (request_id, area_location, utility_type, reason, priority, estimated_scope, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($sampleExpansions as $e) {
        $stmt->execute([$e['request_id'], $e['area_location'], $e['utility_type'], $e['reason'], $e['priority'], $e['estimated_scope'], $e['status']]);
    }

    // 6. Seed development projects (imported from Urban Planning)
    $sampleProjects = [
        ['project_name' => 'Manila Bay Reclamation Residential Phase 1', 'location' => 'Roxas Blvd, Manila', 'latitude' => 14.565000, 'longitude' => 120.982000, 'development_type' => 'Residential', 'expected_timeline' => '2026-2028', 'utility_requirements' => 'Requires high volume water connection (5000 L/min) and high-load power grid.', 'status' => 'Approved Construction', 'readiness_status' => 'Needs Upgrade', 'planning_notes' => 'Existing water mains in Roxas Blvd require upgrade before project integration.'],
        ['project_name' => 'Quiapo Mall Redevelopment Project', 'location' => 'Carriedo St, Quiapo, Manila', 'latitude' => 14.599000, 'longitude' => 120.983000, 'development_type' => 'Commercial', 'expected_timeline' => '2026-2027', 'utility_requirements' => 'Requires expanded drainage flow capacity to handle shopping center storm runoffs.', 'status' => 'Under Review', 'readiness_status' => 'Ready', 'planning_notes' => 'Existing Quiapo commercial drainage grid is adequate for predicted output load.']
    ];

    $stmt = $pdo->prepare("
        INSERT INTO development_projects (project_name, location, latitude, longitude, development_type, expected_timeline, utility_requirements, status, readiness_status, planning_notes) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($sampleProjects as $p) {
        $stmt->execute([$p['project_name'], $p['location'], $p['latitude'], $p['longitude'], $p['development_type'], $p['expected_timeline'], $p['utility_requirements'], $p['status'], $p['readiness_status'], $p['planning_notes']]);
    }

    // 7. Seed capacity records
    $sampleCapacities = [
        ['location_zone' => 'Sampaloc District Zone A', 'capacity_type' => 'Water Supply Volume', 'max_capacity' => 10000.00, 'current_load' => 4500.00, 'unit' => 'L/min', 'status' => 'Normal'],
        ['location_zone' => 'Quiapo Commercial Hub', 'capacity_type' => 'Drainage Flow Rate', 'max_capacity' => 12000.00, 'current_load' => 10800.00, 'unit' => 'm3/hr', 'status' => 'Near Capacity'],
        ['location_zone' => 'Tondo North Extension', 'capacity_type' => 'Electrical Grid Load', 'max_capacity' => 8000.00, 'current_load' => 8200.00, 'unit' => 'kVA', 'status' => 'Overloaded']
    ];

    $stmt = $pdo->prepare("
        INSERT INTO utility_capacity_records (location_zone, capacity_type, max_capacity, current_load, unit, status) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    foreach ($sampleCapacities as $cap) {
        $stmt->execute([$cap['location_zone'], $cap['capacity_type'], $cap['max_capacity'], $cap['current_load'], $cap['unit'], $cap['status']]);
    }

    // 8. Seed logs
    $pdo->exec("
        INSERT INTO planning_coordination_logs (direction, log_type, details) 
        VALUES 
        ('Inbound', 'Project Import', 'Imported 2 new approved development plans from the Urban Planning System.'),
        ('Outbound', 'Coverage Sync', 'Dispatched utility coverage GIS files to the Urban Planning System.')
    ");
    
    echo "Successfully seeded sample planning database records!\n";
    echo "Database setup completed successfully!\n";
    
} catch (PDOException $e) {
    die("Database setup failed: " . $e->getMessage() . "\n");
}
