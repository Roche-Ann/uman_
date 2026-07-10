<?php
// api/get_request_details.php
// Returns a single service request's details + attached documents (if any)
header('Content-Type: application/json');

require_once '../includes/auth.php';
require_once '../includes/db.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$id     = isset($_GET['id']) ? intval($_GET['id']) : 0;
$userId = $_SESSION['user_id'];
$isEmp  = isEmployee();

error_log("get_request_details: ID=$id, UserID=$userId, IsEmployee=$isEmp");

if (!$id || $id <= 0) {
    error_log("get_request_details: Invalid ID provided: $id");
    echo json_encode(['success' => false, 'message' => 'Invalid request ID: ' . $id]);
    exit();
}

try {
    if ($isEmp) {
        $stmt = $pdo->prepare("SELECT * FROM service_requests WHERE id = ?");
        $stmt->execute([$id]);
    } else {
        // Citizens can only view their own requests
        $stmt = $pdo->prepare("SELECT * FROM service_requests WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
    }

    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        error_log("get_request_details: Request not found - ID=$id, UserID=$userId");
        echo json_encode(['success' => false, 'message' => 'Request not found or access denied']);
        exit();
    }

    // Try fetching documents with permission check
    $documents = [];
    try {
        if ($isEmp) {
            // Employees can see all documents for this request
            $docStmt = $pdo->prepare("SELECT * FROM uploaded_documents WHERE request_id = ? ORDER BY uploaded_at DESC");
            $docStmt->execute([$id]);
        } else {
            // Citizens can only see documents from their own requests
            $docStmt = $pdo->prepare("
                SELECT ud.* FROM uploaded_documents ud
                JOIN service_requests sr ON ud.request_id = sr.id
                WHERE ud.request_id = ? AND sr.user_id = ?
                ORDER BY ud.uploaded_at DESC
            ");
            $docStmt->execute([$id, $userId]);
        }
        $documents = $docStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Documents table may not exist yet — silently ignore
        error_log("get_request_details: Documents table error: " . $e->getMessage());
        $documents = [];
    }

    // Filter out documents with invalid IDs
    $documents = array_filter($documents, function($doc) {
        return isset($doc['id']) && $doc['id'] > 0;
    });

    echo json_encode([
        'success'   => true,
        'data'      => [
            'request'   => $request,
            'documents' => $documents,
        ]
    ]);
} catch (Exception $e) {
    error_log("get_request_details: Exception - " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
