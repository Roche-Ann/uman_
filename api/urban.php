<?php
/**
 * Urban Planning Integration API
 * 
 * GET  /api/planning.php?key=...&type=coverage|expansions|projects
 * POST /api/planning.php?key=... (JSON body)
 *
 * External systems can fetch and submit urban planning data.
 */
declare(strict_types=1);

require_once __DIR__ . '/integration_config.php';

// Only allow GET and POST
$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET', 'POST'], true)) {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Authenticate
uman_require_api_key($UMAN_INTEGRATION_API_KEY);

try {
    $pdo = uman_integration_pdo();

    // ============================================================
    // GET: Retrieve planning data
    // ============================================================
    if ($method === 'GET') {
        $type = trim($_GET['type'] ?? '');
        $limit = (int)($_GET['limit'] ?? 100);
        $offset = (int)($_GET['offset'] ?? 0);
        $filter = trim($_GET['filter'] ?? '');

        $response = ['success' => true, 'data' => []];

        switch ($type) {
            case 'coverage':
                // Fetch utility coverage records
                $sql = "SELECT * FROM utility_coverage_records";
                $params = [];
                if ($filter) {
                    $sql .= " WHERE area_name LIKE ? OR coverage_type LIKE ?";
                    $params = ['%' . $filter . '%', '%' . $filter . '%'];
                }
                $sql .= " ORDER BY area_name ASC LIMIT ? OFFSET ?";
                $params[] = $limit;
                $params[] = $offset;
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $response['data'] = $rows;
                $response['count'] = count($rows);
                break;

            case 'expansions':
                // Fetch expansion requests
                $sql = "SELECT * FROM utility_expansion_requests";
                $params = [];
                if ($filter) {
                    $sql .= " WHERE area_location LIKE ? OR utility_type LIKE ? OR status LIKE ?";
                    $params = ['%' . $filter . '%', '%' . $filter . '%', '%' . $filter . '%'];
                }
                $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
                $params[] = $limit;
                $params[] = $offset;
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $response['data'] = $rows;
                $response['count'] = count($rows);
                break;

            case 'projects':
                // Fetch development projects
                $sql = "SELECT * FROM development_projects";
                $params = [];
                if ($filter) {
                    $sql .= " WHERE project_name LIKE ? OR location LIKE ? OR development_type LIKE ?";
                    $params = ['%' . $filter . '%', '%' . $filter . '%', '%' . $filter . '%'];
                }
                $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
                $params[] = $limit;
                $params[] = $offset;
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $response['data'] = $rows;
                $response['count'] = count($rows);
                break;

            default:
                // If no type, return summary
                $coverageCount = $pdo->query("SELECT COUNT(*) FROM utility_coverage_records")->fetchColumn();
                $expansionsCount = $pdo->query("SELECT COUNT(*) FROM utility_expansion_requests")->fetchColumn();
                $projectsCount = $pdo->query("SELECT COUNT(*) FROM development_projects")->fetchColumn();
                $response['summary'] = [
                    'coverage_areas' => (int)$coverageCount,
                    'expansion_requests' => (int)$expansionsCount,
                    'development_projects' => (int)$projectsCount,
                ];
                $response['message'] = 'Specify ?type=coverage, expansions, or projects to retrieve data.';
                break;
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ============================================================
    // POST: Receive urban planning data (create/update)
    // ============================================================
    if ($method === 'POST') {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw ?: '{}', true);
        if (!is_array($json)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid JSON payload']);
            exit;
        }

        $action = $json['action'] ?? '';
        $data = $json['data'] ?? [];

        if (empty($action) || empty($data)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Missing action or data']);
            exit;
        }

        $result = [];

        switch ($action) {
            // ---------- Create Development Project ----------
            case 'create_project':
                $required = ['project_name', 'location', 'development_type', 'utility_requirements'];
                foreach ($required as $field) {
                    if (empty($data[$field])) {
                        http_response_code(422);
                        echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
                        exit;
                    }
                }

                $stmt = $pdo->prepare("
                    INSERT INTO development_projects 
                        (project_name, location, latitude, longitude, development_type, expected_timeline, 
                         utility_requirements, status, readiness_status, planning_notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $data['project_name'],
                    $data['location'],
                    $data['latitude'] ?? null,
                    $data['longitude'] ?? null,
                    $data['development_type'],
                    $data['expected_timeline'] ?? null,
                    $data['utility_requirements'],
                    $data['status'] ?? 'Approved Construction',
                    $data['readiness_status'] ?? 'Ready',
                    $data['planning_notes'] ?? null,
                ]);
                $newId = (int)$pdo->lastInsertId();

                // Log coordination
                $pdo->prepare("
                    INSERT INTO planning_coordination_logs (direction, log_type, details)
                    VALUES ('Inbound', 'Project Import', ?)
                ")->execute(["Imported development project '{$data['project_name']}' from Urban Planning System."]);

                $result = ['success' => true, 'message' => 'Project created', 'id' => $newId];
                break;

            // ---------- Create Expansion Request ----------
            case 'create_expansion':
                $required = ['area_location', 'utility_type', 'reason'];
                foreach ($required as $field) {
                    if (empty($data[$field])) {
                        http_response_code(422);
                        echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
                        exit;
                    }
                }

                // Generate request ID
                $prefix = 'PLN-EXP-' . date('Ym') . '-';
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM utility_expansion_requests WHERE request_id LIKE ?");
                $countStmt->execute([$prefix . '%']);
                $seq = (int)$countStmt->fetchColumn() + 1;
                $requestId = $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);

                $stmt = $pdo->prepare("
                    INSERT INTO utility_expansion_requests 
                        (request_id, area_location, utility_type, reason, priority, estimated_scope, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $requestId,
                    $data['area_location'],
                    $data['utility_type'],
                    $data['reason'],
                    $data['priority'] ?? 'Medium',
                    $data['estimated_scope'] ?? null,
                    $data['status'] ?? 'Pending',
                ]);
                $newId = (int)$pdo->lastInsertId();

                // Log
                $pdo->prepare("
                    INSERT INTO planning_coordination_logs (direction, log_type, details)
                    VALUES ('Inbound', 'Expansion Request', ?)
                ")->execute(["Received expansion request for '{$data['area_location']}' from Urban Planning System."]);

                $result = ['success' => true, 'message' => 'Expansion request created', 'request_id' => $requestId, 'id' => $newId];
                break;

            // ---------- Update Project Readiness ----------
            case 'update_project_readiness':
                if (empty($data['project_id']) || empty($data['readiness_status'])) {
                    http_response_code(422);
                    echo json_encode(['success' => false, 'error' => 'Missing project_id or readiness_status']);
                    exit;
                }
                $stmt = $pdo->prepare("UPDATE development_projects SET readiness_status = ?, planning_notes = ? WHERE id = ?");
                $stmt->execute([
                    $data['readiness_status'],
                    $data['planning_notes'] ?? null,
                    (int)$data['project_id']
                ]);
                $rowsAffected = $stmt->rowCount();
                if ($rowsAffected === 0) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Project not found or no change']);
                    exit;
                }
                $result = ['success' => true, 'message' => 'Project readiness updated'];
                break;

            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => "Unknown action: $action"]);
                exit;
        }

        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

} catch (Throwable $e) {
    error_log('Urban Planning API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error processing request']);
}