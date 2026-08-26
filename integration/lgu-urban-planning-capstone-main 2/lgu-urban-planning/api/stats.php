<?php
/**
 * Read-only headline metric for the Main LGU SSO hub dashboard.
 * Auth: Authorization: Bearer <MAIN_LGU_SSO_SECRET> (same secret used for SSO).
 */
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../sso_env.php';

header('Content-Type: application/json');

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? (function_exists('apache_request_headers') ? (apache_request_headers()['Authorization'] ?? '') : '');
$token = preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m) ? $m[1] : '';

$secret = upad_sso_env('MAIN_LGU_SSO_SECRET', '');
if ($secret === '' || !hash_equals($secret, $token)) {
    http_response_code(403);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$count = (int) Database::getInstance()->fetchOne('SELECT COUNT(*) AS c FROM applications')['c'];

echo json_encode(['count' => $count, 'label' => 'Permit Applications']);
