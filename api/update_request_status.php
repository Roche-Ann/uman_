<?php
// api/update_request_status.php
// Updates status, schedule info, or rejection of a service request (employee only)
header('Content-Type: application/json');

require_once '../includes/auth.php';
require_once '../includes/db.php';

if (!isLoggedIn() || !isEmployee()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$requestId   = isset($_POST['request_id'])   ? intval($_POST['request_id'])       : 0;
$newStatus   = isset($_POST['new_status'])   ? trim($_POST['new_status'])          : '';
$status      = isset($_POST['status'])       ? trim($_POST['status'])              : $newStatus;

// Accept either 'status' or 'new_status'
if (empty($status)) {
    echo json_encode(['success' => false, 'message' => 'Status is required']);
    exit();
}

if (!$requestId) {
    echo json_encode(['success' => false, 'message' => 'Invalid request ID']);
    exit();
}

$allowed = ['pending','processing','approved','scheduled','completed','rejected'];
if (!in_array($status, $allowed)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status value']);
    exit();
}

try {
    if ($status === 'scheduled') {
        // Scheduling: also save date, time, technician, notes
        $scheduledDate   = isset($_POST['scheduled_date'])  ? trim($_POST['scheduled_date'])  : null;
        $scheduledTime   = isset($_POST['scheduled_time'])  ? trim($_POST['scheduled_time'])  : null;
        $technicianName  = isset($_POST['technician_name']) ? trim($_POST['technician_name']) : null;
        $notes           = isset($_POST['notes'])           ? trim($_POST['notes'])           : null;

        $stmt = $pdo->prepare("
            UPDATE service_requests
            SET status = 'scheduled',
                scheduled_date  = ?,
                scheduled_time  = ?,
                technician_name = ?,
                notes           = ?,
                updated_at      = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$scheduledDate, $scheduledTime, $technicianName, $notes, $requestId]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE service_requests
            SET status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$status, $requestId]);
    }

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No record updated. Request ID may not exist.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
