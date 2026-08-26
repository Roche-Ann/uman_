<?php
session_start();
require_once __DIR__ . '/core/Database.php';

$db = Database::getInstance();
$userId = $_SESSION['user_id'] ?? 0;

if ($userId > 0) {
    // Keep the server-side session alive on every poll.
    $_SESSION['last_activity'] = time();
    $notifData = $db->fetchOne("SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0", [$userId]);
    
    $messages = $db->fetchAll("SELECT * FROM messages WHERE receiver_id = ? ORDER BY created_at DESC LIMIT 5", [$userId]);

    foreach ($messages as &$m) {
        $m['formatted_date'] = date('M d, h:i A', strtotime($m['created_at']));
    }

    header('Content-Type: application/json');
    echo json_encode([
        'count' => (int)$notifData['count'],
        'messages' => $messages
    ]);
} else {
    echo json_encode(['count' => 0, 'messages' => []]);
}