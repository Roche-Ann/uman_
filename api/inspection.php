<?php
/**
 * Urban Planning Inspection Request API
 * 
 * POST /api/inspection.php?key=...
 * 
 * External urban planning systems submit inspection requests.
 * This API evaluates utility conditions and returns approval/rejection with details.
 */
declare(strict_types=1);

require_once __DIR__ . '/integration_config.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Authenticate
uman_require_api_key($UMAN_INTEGRATION_API_KEY);

try {
    $pdo = uman_integration_pdo();

    // Parse JSON input
    $raw = file_get_contents('php://input');
    $input = json_decode($raw ?: '{}', true);
    if (!is_array($input) || empty($input)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON payload']);
        exit;
    }

    // Validate required fields
    $required = ['request_id', 'location', 'utility_type'];
    $missing = [];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            $missing[] = $field;
        }
    }
    if (!empty($missing)) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error' => 'Missing required fields: ' . implode(', ', $missing)
        ]);
        exit;
    }

    $requestId = trim($input['request_id']);
    $location = trim($input['location']);
    $utilityType = trim($input['utility_type']);
    $projectId = isset($input['project_id']) ? (int)$input['project_id'] : null;
    $requestedDate = $input['requested_date'] ?? date('Y-m-d');
    $details = trim($input['details'] ?? '');

    // ================================================================
    // 1. Check utility coverage for the location
    // ================================================================
    $coverageStatus = 'Not Covered';
    $coverageDetails = [];
    $coverageQuery = $pdo->prepare("
        SELECT coverage_type, coverage_status, remarks
        FROM utility_coverage_records
        WHERE coverage_type = :type
        ORDER BY radius_meters ASC
        LIMIT 1
    ");
    $coverageQuery->execute([':type' => $utilityType]);
    $coverage = $coverageQuery->fetch(PDO::FETCH_ASSOC);
    if ($coverage) {
        $coverageStatus = $coverage['coverage_status'];
        $coverageDetails[] = "Coverage status: {$coverage['coverage_status']}";
        if (!empty($coverage['remarks'])) {
            $coverageDetails[] = "Remarks: {$coverage['remarks']}";
        }
    } else {
        // Fallback: check for any coverage for location by area name (if we have coordinates)
        // For simplicity, we'll just note no specific coverage
        $coverageDetails[] = "No specific coverage record for '$utilityType' at this location.";
    }

    // ================================================================
    // 2. Check relevant assets in the area
    // ================================================================
    $assetStatus = [];
    $assetQuery = $pdo->prepare("
        SELECT asset_id, name, condition_status, location, description
        FROM utility_assets
        WHERE location LIKE :location
           OR asset_type_id IN (
               SELECT id FROM asset_types WHERE name LIKE :type
           )
        ORDER BY condition_status ASC
        LIMIT 10
    ");
    $assetQuery->execute([
        ':location' => '%' . $location . '%',
        ':type' => '%' . $utilityType . '%'
    ]);
    $assets = $assetQuery->fetchAll(PDO::FETCH_ASSOC);

    $operationalAssets = 0;
    $damagedAssets = 0;
    $totalAssets = count($assets);
    foreach ($assets as $asset) {
        if ($asset['condition_status'] === 'Operational') {
            $operationalAssets++;
        } elseif ($asset['condition_status'] === 'Damaged') {
            $damagedAssets++;
        }
    }

    if ($totalAssets > 0) {
        $assetStatus[] = "Found $totalAssets assets; $operationalAssets operational, $damagedAssets damaged.";
    } else {
        $assetStatus[] = "No existing assets found for this utility type at the location.";
    }

    // ================================================================
    // 3. Check capacity status
    // ================================================================
    $capacityStatus = [];
    $capacityQuery = $pdo->prepare("
        SELECT location_zone, max_capacity, current_load, status
        FROM utility_capacity_records
        WHERE capacity_type LIKE :type
           OR location_zone LIKE :location
        ORDER BY status DESC
        LIMIT 5
    ");
    $capacityQuery->execute([
        ':type' => '%' . $utilityType . '%',
        ':location' => '%' . $location . '%'
    ]);
    $capacities = $capacityQuery->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($capacities)) {
        foreach ($capacities as $cap) {
            $capacityStatus[] = "Zone: {$cap['location_zone']} - Status: {$cap['status']} (Load: {$cap['current_load']}/{$cap['max_capacity']})";
        }
    } else {
        $capacityStatus[] = "No capacity records found for this area or utility type.";
    }

    // ================================================================
    // 4. Check active incidents / maintenance affecting area
    // ================================================================
    $incidentStatus = [];
    $incidentQuery = $pdo->prepare("
        SELECT incident_type, description, status
        FROM utility_incidents
        WHERE location LIKE :location
          AND status NOT IN ('Resolved', 'Closed')
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $incidentQuery->execute([':location' => '%' . $location . '%']);
    $incidents = $incidentQuery->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($incidents)) {
        $incidentStatus[] = "Active incidents found in the area:";
        foreach ($incidents as $inc) {
            $incidentStatus[] = " - {$inc['incident_type']} ({$inc['status']})";
        }
    } else {
        $incidentStatus[] = "No active incidents reported in this area.";
    }

    // ================================================================
    // 5. Decision logic
    // ================================================================
    $decision = 'Approved';
    $issues = [];

    // Check coverage
    if ($coverageStatus === 'Not Covered') {
        $decision = 'Rejected';
        $issues[] = 'Area not covered by this utility type.';
    } elseif ($coverageStatus === 'Partially Covered') {
        $decision = 'Conditional';
        $issues[] = 'Partial coverage – additional infrastructure may be needed.';
    }

    // Check damaged assets
    if ($damagedAssets > 0) {
        if ($decision === 'Approved') {
            $decision = 'Conditional';
        }
        $issues[] = "$damagedAssets damaged assets found – repair required before inspection.";
    }

    // Check capacity overload
    $overloaded = false;
    foreach ($capacities as $cap) {
        if ($cap['status'] === 'Overloaded') {
            $overloaded = true;
            $issues[] = "Capacity overloaded in zone {$cap['location_zone']}.";
        }
    }
    if ($overloaded && $decision === 'Approved') {
        $decision = 'Conditional';
    }

    // Emergency issues
    if (!empty($incidents) && $decision === 'Approved') {
        $decision = 'Conditional';
        $issues[] = 'Active incidents in area – inspection may be delayed.';
    }

    // If still approved, may have minor suggestions
    if ($decision === 'Approved') {
        if ($coverageStatus === 'Fully Covered' && $operationalAssets > 0 && !$overloaded) {
            // All good
            $issues[] = 'All utility conditions are satisfactory.';
        } else {
            $issues[] = 'General approval, but check minor notes.';
        }
    }

    // ================================================================
    // 6. Prepare detailed result
    // ================================================================
    $detailedResult = [
        'coverage' => $coverageStatus,
        'assets' => $assetStatus,
        'capacities' => $capacityStatus,
        'incidents' => $incidentStatus,
        'decision_summary' => $issues,
    ];

    // ================================================================
    // 7. Log the request and response
    // ================================================================
    $logStmt = $pdo->prepare("
        INSERT INTO planning_coordination_logs (direction, log_type, details)
        VALUES ('Inbound', 'Inspection Request', ?)
    ");
    $logStmt->execute([
        "Inspection request $requestId: Decision $decision. Issues: " . implode(' | ', $issues)
    ]);

    // ================================================================
    // 8. Return response
    // ================================================================
    $response = [
        'success' => true,
        'request_id' => $requestId,
        'decision' => $decision,
        'message' => $decision === 'Approved' ? 'Inspection approved.' : 'Inspection not fully cleared.',
        'detailed_result' => $detailedResult,
        'issues' => $issues,
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;

} catch (Throwable $e) {
    error_log('Inspection API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error processing inspection request']);
}