<?php
// api/get_all_requests.php
// Returns all service requests as JSON (employee use only)
header('Content-Type: application/json');

require_once '../includes/auth.php';
require_once '../includes/db.php';

if (!isLoggedIn() || !isEmployee()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $filter = isset($_GET['filter']) ? trim($_GET['filter']) : 'all';

    if ($filter !== 'all' && in_array($filter, ['pending','processing','approved','scheduled','completed','rejected'])) {
        $stmt = $pdo->prepare("SELECT * FROM service_requests WHERE id > 0 AND status = ? ORDER BY created_at DESC");
        $stmt->execute([$filter]);
    } else {
        $stmt = $pdo->query("SELECT * FROM service_requests WHERE id > 0 ORDER BY created_at DESC");
    }

    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("get_all_requests: Returned " . count($requests) . " requests");

    echo json_encode(['success' => true, 'data' => $requests]);
} catch (Exception $e) {
    error_log("get_all_requests: Error - " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
