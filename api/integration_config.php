<?php
/**
 * Shared config for UMAN external integration APIs (CPRF, etc.)
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// Load DB connection at file scope so $pdo is global (upstream loading pattern)
require_once dirname(__DIR__) . '/includes/db.php';

/**
 * Load the shared CPRF ↔ UMAN API key with wide backward-compatibility:
 *   - UMAN_INTEGRATION_API_KEY  (preferred on the UMAN server)
 *   - UMAN_API_KEY              (same env name CPRF uses — avoids split-name drift)
 *   - literal 'UMAN_SECURE_KEY_2025'  (dev / no-env fallback)
 *
 * Both sides now accept BOTH env var names so a deploy that sets only one
 * still keeps the pair synchronized.
 */
$UMAN_INTEGRATION_API_KEY = trim((string)(
    getenv('UMAN_INTEGRATION_API_KEY')
    ?: getenv('UMAN_API_KEY')
    ?: (isset($_ENV['UMAN_INTEGRATION_API_KEY']) ? $_ENV['UMAN_INTEGRATION_API_KEY'] : '')
    ?: (isset($_ENV['UMAN_API_KEY']) ? $_ENV['UMAN_API_KEY'] : '')
    ?: 'UMAN_SECURE_KEY_2025'
));

function uman_require_api_key(string $expectedKey): void
{
    $provided = trim((string)($_GET['key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? $_SERVER['HTTP_X_API_KEY_LOWER'] ?? ''));
    if ($provided === '' || !hash_equals($expectedKey, $provided)) {
        http_response_code(401);
        $envHint = '';
        if ($provided === '') {
            $envHint = ' (no key received — call GET/POST with ?key=… or set the X-API-Key header)';
        }
        echo json_encode(['success' => false, 'error' => 'Unauthorized — invalid or missing API key' . $envHint], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function uman_integration_pdo(): PDO
{
    global $pdo;
    return $pdo;
}
