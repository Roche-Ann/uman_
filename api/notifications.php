<?php
// api/notifications.php
// Standalone AJAX endpoint to handle topbar notifications and mark-as-read operations

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$userId   = $_SESSION['user_id'] ?? 0;
$userType = $_SESSION['user_type'] ?? 'employee';
$action   = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'mark_all_read') {
        try {
            if ($userType === 'employee') {
                $pdo->exec("UPDATE asset_notifications SET read_status = 1 WHERE read_status = 0");
            } else {
                $stmt = $pdo->prepare("UPDATE incident_notifications SET read_status = 1 WHERE user_id = ? AND read_status = 0");
                $stmt->execute([$userId]);
            }
            echo json_encode(['success' => true, 'unread_count' => 0]);
            exit();
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
    }

    if ($action === 'mark_read') {
        $id = intval($_POST['id'] ?? 0);
        $source = $_POST['source'] ?? 'asset_notifications';

        try {
            if ($source === 'asset_notifications' && $id > 0) {
                $stmt = $pdo->prepare("UPDATE asset_notifications SET read_status = 1 WHERE id = ?");
                $stmt->execute([$id]);
            } elseif ($source === 'incident_notifications' && $id > 0) {
                $stmt = $pdo->prepare("UPDATE incident_notifications SET read_status = 1 WHERE id = ? AND user_id = ?");
                $stmt->execute([$id, $userId]);
            }
            echo json_encode(['success' => true]);
            exit();
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
exit();
