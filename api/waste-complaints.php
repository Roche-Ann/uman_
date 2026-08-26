<?php
// api/waste-complaints.php
require_once '../includes/auth.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Fetch complaints (optionally filtered)
    $status = $_GET['status'] ?? 'all';
    $type   = $_GET['type']   ?? 'all';
    $date   = $_GET['date']   ?? '';

    $where = ['1=1'];
    $params = [];
    if ($status !== 'all') { $where[] = 'c.status = ?'; $params[] = $status; }
    if ($type   !== 'all') { $where[] = 'c.complaint_type = ?'; $params[] = $type; }
    if ($date)             { $where[] = 'DATE(c.created_at) = ?'; $params[] = $date; }

    $sql = "SELECT c.*, u.full_name as reporter_name
            FROM waste_complaints c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY c.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    exit;
}

if ($method === 'POST') {
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: $_POST;

    $action = $data['action'] ?? '';

    // ── Resolve complaint ────────────────────────────────────
    if ($action === 'resolve') {
        $id = intval($data['id'] ?? 0);
        if (!$id) { echo json_encode(['success'=>false,'message'=>'Invalid ID']); exit; }
        $stmt = $pdo->prepare("UPDATE waste_complaints SET status='Resolved', resolved_at=NOW() WHERE id=?");
        $stmt->execute([$id]);
        // Notification
        $pdo->prepare("INSERT INTO waste_notifications (message, type) VALUES (?, 'General')")
            ->execute(["Complaint #WC-{$id} has been marked as Resolved."]);
        echo json_encode(['success' => true]);
        exit;
    }

    // ── Dismiss complaint ────────────────────────────────────
    if ($action === 'dismiss') {
        $id = intval($data['id'] ?? 0);
        if (!$id) { echo json_encode(['success'=>false,'message'=>'Invalid ID']); exit; }
        $pdo->prepare("UPDATE waste_complaints SET status='Dismissed' WHERE id=?")->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // ── Submit new complaint (from citizen form) ─────────────
    if ($action === 'submit') {
        $userId        = $_SESSION['user_id'] ?? null;
        $type          = $data['complaint_type'] ?? '';
        $description   = trim($data['description'] ?? '');
        $barangay      = trim($data['barangay'] ?? '');
        $addressDetail = trim($data['address_detail'] ?? '');
        $lat           = $data['latitude']  ?? null;
        $lng           = $data['longitude'] ?? null;

        if (empty($type) || !in_array($type, ['Missed Collection', 'Illegal Dumping'])) {
            echo json_encode(['success'=>false,'message'=>'Invalid complaint type']); exit;
        }

        // Handle photo upload
        $photoPath = null;
        if (!empty($_FILES['photo']['name'])) {
            $uploadDir = '../uploads/waste_complaints/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $ext      = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $allowed  = ['jpg','jpeg','png','webp'];
            if (!in_array($ext, $allowed)) {
                echo json_encode(['success'=>false,'message'=>'Invalid file type']); exit;
            }
            if ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
                echo json_encode(['success'=>false,'message'=>'File too large (max 5MB)']); exit;
            }
            $filename  = 'wc_' . time() . '_' . rand(1000,9999) . '.' . $ext;
            $photoPath = 'uploads/waste_complaints/' . $filename;
            move_uploaded_file($_FILES['photo']['tmp_name'], '../' . $photoPath);
        }

        // Generate ID
        $prefix = 'WC-' . date('Ym') . '-';
        $count  = $pdo->query("SELECT COUNT(*) FROM waste_complaints WHERE complaint_id LIKE '{$prefix}%'")->fetchColumn() + 1;
        $complaintId = $prefix . str_pad($count, 4, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("
            INSERT INTO waste_complaints
                (complaint_id, user_id, complaint_type, description, barangay, address_detail, latitude, longitude, photo_path)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$complaintId, $userId, $type, $description, $barangay, $addressDetail, $lat, $lng, $photoPath]);
        $insertedId = $pdo->lastInsertId();

        // Notification
        $pdo->prepare("INSERT INTO waste_notifications (message, type) VALUES (?, 'New Complaint')")
            ->execute(["New {$type} complaint {$complaintId} filed at {$barangay}."]);

        echo json_encode(['success' => true, 'complaint_id' => $complaintId, 'id' => $insertedId]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Method not allowed']);
