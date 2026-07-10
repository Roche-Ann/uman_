<?php
// api/test_requests.php - Debug endpoint to test service requests API
header('Content-Type: application/json');

require_once '../includes/auth.php';
require_once '../includes/db.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    // Test 1: Count all requests
    $countStmt = $pdo->query("SELECT COUNT(*) as count FROM service_requests");
    $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
    $totalRequests = $countResult['count'] ?? 0;

    // Test 2: Get first request
    $stmt = $pdo->query("SELECT * FROM service_requests LIMIT 1");
    $firstRequest = $stmt->fetch(PDO::FETCH_ASSOC);

    // Test 3: Get user's requests (if citizen)
    $userRequests = [];
    if (!isEmployee()) {
        $userId = $_SESSION['user_id'];
        $userStmt = $pdo->prepare("SELECT * FROM service_requests WHERE user_id = ?");
        $userStmt->execute([$userId]);
        $userRequests = $userStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Test 4: Check table structure
    $columnsStmt = $pdo->query("SHOW COLUMNS FROM service_requests");
    $columns = $columnsStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'debug' => [
            'total_requests' => $totalRequests,
            'first_request' => $firstRequest,
            'user_requests_count' => count($userRequests),
            'is_employee' => isEmployee(),
            'table_columns' => count($columns),
            'columns' => array_column($columns, 'Field'),
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
