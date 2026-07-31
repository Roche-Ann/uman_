<?php
require_once __DIR__ . '/../core/Database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = Database::getInstance()->getConnection();
    $val = $_POST['setting_value'] ?? '';
    $active = $_POST['is_active'] ?? 0;

    $stmt = $db->prepare("UPDATE system_settings SET setting_value = ?, is_active = ? WHERE setting_key = 'system_announcement'");
    $success = $stmt->execute([$val, $active]);

    echo json_encode(['success' => $success]);
    exit;
}