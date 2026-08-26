<?php
require_once __DIR__ . '/GISController.php';
$controller = new GISController();

$action = $_GET['action'] ?? '';

header('Content-Type: application/json');

if ($action === 'get_layer') {
    $id = $_GET['id'] ?? 0;
    $layer = $controller->getLayerData($id);
    if ($layer) {
        echo $layer['layer_data']; 
    } else {
        echo json_encode(['error' => 'Layer not found']);
    }
    exit;
}