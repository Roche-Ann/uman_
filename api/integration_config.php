<?php
/**
 * Shared config for UMAN external integration APIs (CPRF, UPAD, etc.)
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// Load DB connection at file scope so $pdo is global
require_once dirname(__DIR__) . '/includes/db.php';

// ── General integration API key (used by CPRF and other inbound callers) ──────
$UMAN_INTEGRATION_API_KEY = trim((string)(getenv('UMAN_INTEGRATION_API_KEY') ?: 'UMAN_SECURE_KEY_2025'));

// ── Urban Planning (UPAD) integration constants ───────────────────────────────
// API key UPAD must send as: Authorization: Bearer <key>
// Set UPAD_API_KEY in your .env, or share the default below with the UPAD team.
define('UPAD_API_KEY',         trim((string)(getenv('UPAD_API_KEY')         ?: 'UPAD_UMAN_INTEGRATION_KEY_2026')));

// Shared HMAC-SHA256 secret — used to sign X-UMAN-Signature on callbacks to UPAD.
// Must match UMAN_WEBHOOK_SECRET in the UPAD system's utilities_integration.php.
define('UPAD_WEBHOOK_SECRET',  trim((string)(getenv('UPAD_WEBHOOK_SECRET')  ?: 'UPAD_UMAN_WEBHOOK_SECRET_2026')));

// ── Auth helpers ──────────────────────────────────────────────────────────────

function uman_require_api_key(string $expectedKey): void
{
    $provided = trim((string)($_GET['key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? ''));
    if ($provided === '' || !hash_equals($expectedKey, $provided)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized — invalid or missing API key']);
        exit;
    }
}

/**
 * Validate an inbound Bearer token.
 * Accepts: Authorization: Bearer <token>
 */
function uman_require_bearer(string $expectedKey): void
{
    $header   = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $provided = '';
    if (preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        $provided = trim($m[1]);
    }
    if ($provided === '' || !hash_equals($expectedKey, $provided)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized — invalid or missing Bearer token']);
        exit;
    }
}

function uman_integration_pdo(): PDO
{
    global $pdo;
    return $pdo;
}
