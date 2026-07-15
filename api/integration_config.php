<?php
/**
 * Shared config for UMAN external integration APIs (CPRF, etc.)
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// Load DB connection at file scope so $pdo is global
require_once dirname(__DIR__) . '/includes/db.php';

$UMAN_INTEGRATION_API_KEY = trim((string)(getenv('UMAN_INTEGRATION_API_KEY') ?: 'UMAN_SECURE_KEY_2025'));

function uman_require_api_key(string $expectedKey): void
{
    $provided = trim((string)($_GET['key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? ''));
    if ($provided === '' || !hash_equals($expectedKey, $provided)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized — invalid or missing API key']);
        exit;
    }
}

function uman_integration_pdo(): PDO
{
    global $pdo;
    return $pdo;
}
