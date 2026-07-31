<?php
require_once __DIR__ . '/UserController.php';
session_start();

$controller = new UserController();

if (isset($_GET['action']) && $_GET['action'] === 'get_history') {
    $userId = $_GET['user_id'] ?? 0;
    
    try {
        $history = $controller->getUserHistory($userId);
        echo json_encode(['success' => true, 'data' => $history]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}