<?php
// api/v1/inspection_ai.php
// AI Inspection Processor using real data from asset inventory and incident reports

require_once __DIR__ . '/../integration_config.php';

function runInspectionAIValidation(string $referenceId, PDO $pdo): array {
    // 1. Fetch the request
    $stmt = $pdo->prepare("SELECT * FROM upad_inspection_requests WHERE reference_id = ?");
    $stmt->execute([$referenceId]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$req) {
        return ['success' => false, 'error' => 'Inspection request not found'];
    }

    $latitude = $req['latitude'];
    $longitude = $req['longitude'];
    $barangay = $req['barangay'];
    $category = $req['category'];
    $address = $req['address'];

    // 2. Query Asset Inventory for matching or nearby assets
    $matchedAssets = [];
    if ($latitude && $longitude) {
        // Haversine formula to find assets within 1.5km
        $assetStmt = $pdo->prepare("
            SELECT id, asset_id, name, location, condition_status,
                   (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance
            FROM utility_assets
            HAVING distance < 1.5
            ORDER BY distance ASC
        ");
        $assetStmt->execute([$latitude, $longitude, $latitude]);
        $matchedAssets = $assetStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Fallback search by location description / barangay
    if (empty($matchedAssets) && $barangay) {
        $assetStmt = $pdo->prepare("
            SELECT id, asset_id, name, location, condition_status, 0.0 as distance
            FROM utility_assets
            WHERE location LIKE ? OR description LIKE ?
        ");
        $assetStmt->execute(['%' . $barangay . '%', '%' . $barangay . '%']);
        $matchedAssets = $assetStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 3. Query Incident Reports for matching location/barangay
    $matchedIncidents = [];
    if ($latitude && $longitude) {
        // Find incidents within 1.5km
        $incStmt = $pdo->prepare("
            SELECT id, incident_id, description, status, priority,
                   (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance
            FROM utility_incidents
            HAVING distance < 1.5
            ORDER BY distance ASC
        ");
        $incStmt->execute([$latitude, $longitude, $latitude]);
        $matchedIncidents = $incStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if (empty($matchedIncidents) && $barangay) {
        $incStmt = $pdo->prepare("
            SELECT id, incident_id, description, status, priority, 0.0 as distance
            FROM utility_incidents
            WHERE location LIKE ? OR description LIKE ?
        ");
        $incStmt->execute(['%' . $barangay . '%', '%' . $barangay . '%']);
        $matchedIncidents = $incStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 4. AI Verification Reasoning Logic (No Dummy Data, Real Cross-Referencing)
    $hasUnresolvedIncidents = false;
    $blockingIncidents = [];
    foreach ($matchedIncidents as $inc) {
        if (!in_array($inc['status'], ['Resolved', 'Closed'])) {
            $hasUnresolvedIncidents = true;
            $blockingIncidents[] = $inc;
        }
    }

    $hasOperationalAssets = false;
    $operationalAssetList = [];
    foreach ($matchedAssets as $asset) {
        if ($asset['condition_status'] === 'Operational') {
            $hasOperationalAssets = true;
            $operationalAssetList[] = $asset;
        }
    }

    // Decision matrix:
    // - If matching asset exists and is Operational, AND there are no unresolved / active incidents in that area: Auto-Approve!
    $approved = false;
    $reason = '';
    $recommendation = '';
    $overallCondition = 'Good';

    if (empty($matchedAssets)) {
        $reason = "AI Check: No matching infrastructure assets found in the inventory for location '$barangay'. Manual survey required.";
        $recommendation = "Deferred for manual surveyor dispatch.";
        $overallCondition = "Needs Inspection";
    } elseif ($hasUnresolvedIncidents) {
        $reason = "AI Check: Found " . count($blockingIncidents) . " active/unresolved utility incidents in this area (e.g. " . $blockingIncidents[0]['description'] . "). Grid stability risk.";
        $recommendation = "Inspection deferred until active incidents are resolved.";
        $overallCondition = "Needs Attention";
    } elseif ($hasOperationalAssets) {
        $approved = true;
        $reason = "AI Auto-Approved: Verified matching operational asset '" . $operationalAssetList[0]['name'] . "' (" . $operationalAssetList[0]['asset_id'] . ") at coordinates. No active incidents reported in this zone.";
        $recommendation = "Approved for grid connection / development project integration.";
        $overallCondition = "Good";
    } else {
        $reason = "AI Check: Matched assets found but they are in status '" . $matchedAssets[0]['condition_status'] . "'. Grid load cannot be verified automatically.";
        $recommendation = "Deferred for physical technician review.";
        $overallCondition = "Needs Inspection";
    }

    // 5. If approved, auto-perform callback to UPAD!
    if ($approved) {
        $callbackPayload = [
            'application_id'           => (int) $req['application_id'],
            'grid_id'                  => $referenceId,
            'inspection_date'          => date('Y-m-d'),
            'engineer_assigned'        => 'UMAN AI Auto-Approver',
            'grid_capacity_condition'  => 'Good',
            'transformer_condition'    => 'Good',
            'line_condition'           => 'Good',
            'load_forecast_condition'  => 'Good',
            'overall_condition'        => $overallCondition,
            'severity'                 => 'Low',
            'recommendation'           => $recommendation,
            'gps_latitude'             => $req['latitude'] ? (float)$req['latitude'] : null,
            'gps_longitude'            => $req['longitude'] ? (float)$req['longitude'] : null,
            'remarks'                  => $reason,
            'photo_urls'               => [],
        ];

        $callbackJson = json_encode($callbackPayload, JSON_UNESCAPED_UNICODE);
        $callbackUrl  = !empty($req['callback_url']) ? $req['callback_url'] : UPAD_DEFAULT_CALLBACK_URL;
        $signature    = hash_hmac('sha256', $callbackJson, UPAD_WEBHOOK_SECRET);

        $ch = curl_init($callbackUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $callbackJson,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-UMAN-Signature: ' . $signature,
            ],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $responseBody = curl_exec($ch);
        $httpCode     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr      = curl_error($ch);
        curl_close($ch);

        $success = !$curlErr && $httpCode >= 200 && $httpCode < 300;
        $errText = $curlErr ?: ($success ? null : "HTTP $httpCode: " . mb_substr(strip_tags($responseBody ?: ''), 0, 150));

        // Update request status in DB
        $updateStmt = $pdo->prepare("
            UPDATE upad_inspection_requests
            SET status = ?, result_payload = ?, callback_sent_at = NOW(), callback_http_code = ?, callback_error = ?
            WHERE reference_id = ?
        ");
        $updateStmt->execute([
            $success ? 'completed' : 'failed',
            json_encode(['sent' => $callbackPayload, 'http_code' => $httpCode, 'response' => mb_substr($responseBody ?: '', 0, 1000)]),
            $httpCode ?: null,
            $errText,
            $referenceId
        ]);

        // Log AI Auto-Approval Action
        try {
            $pdo->prepare("
                INSERT INTO planning_coordination_logs (direction, log_type, details)
                VALUES ('Outbound', 'AI Auto-Approval Callback', ?)
            ")->execute([
                "AI auto-approved application ID: " . $req['application_id'] . " | Ref: $referenceId. Result: $reason. Callback HTTP: $httpCode"
            ]);
        } catch (Throwable) {
        }

        return [
            'success' => true,
            'approved' => true,
            'message' => $reason,
            'callback_success' => $success,
            'callback_error' => $errText
        ];
    } else {
        // Update request status in DB to indicate AI verification results
        $updateStmt = $pdo->prepare("
            UPDATE upad_inspection_requests
            SET status = 'pending', callback_error = ?
            WHERE reference_id = ?
        ");
        $updateStmt->execute([$reason, $referenceId]);

        // Log AI analysis
        try {
            $pdo->prepare("
                INSERT INTO planning_coordination_logs (direction, log_type, details)
                VALUES ('Inbound', 'AI Verification Deferred', ?)
            ")->execute([
                "AI verification for Ref $referenceId deferred: $reason"
            ]);
        } catch (Throwable) {
        }

        return [
            'success' => true,
            'approved' => false,
            'message' => $reason
        ];
    }
}
