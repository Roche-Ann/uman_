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

// ── CPRF (Barangay Culiat Facilities Reservation) integration constants ──────────
// Base URL where CPRF integrations webhook receiver lives.
// Default matches typical LGU XAMPP vhost "lgu.test"; override in your env for prod.
define('CPRF_BASE_URL',       rtrim((string)(getenv('CPRF_BASE_URL')       ?: 'http://lgu.test'), '/\\'));
// Inbound API key CPRF expects on X-API-Key header — send on callbacks to CPRF.
// Should match INTEGRATIONS_INBOUND_KEY / FACILITIES_API_KEY in CPRF .env.
define('CPRF_INBOUND_API_KEY', trim((string)(getenv('CPRF_INBOUND_API_KEY') ?: trim((string)(getenv('FACILITIES_API_KEY')          ?: 'CPRF_INTEGRATIONS_SECURE_KEY_2025')))));
// CPRF route prefix for integration routes on the CPRF side.
define('CPRF_INTEGRATIONS_PREFIX', '/api/integrations');

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

/**
 * Idempotently add the 2 CPRF custody columns to utility_assets so we can
 * track which assets are currently on-loan at a Barangay Culiat facility
 * (vs. sitting in the UMAN warehouse).
 */
function uman_ensure_cprf_custody_schema(PDO $pdo): void
{
    $addCol = static function (string $definition) use ($pdo): void {
        try {
            $pdo->exec('ALTER TABLE utility_assets ADD COLUMN ' . $definition);
        } catch (Throwable $e) {
            // duplicate column: noop
        }
    };
    $addCol("`cprf_facility_id` INT NULL AFTER `responsible_office`");
    $addCol("`cprf_custody_status` ENUM('WAREHOUSED','ON_LOAN_AT_FACILITY','LOAN_RETURN_PENDING','LOAN_RETURNED','CONDEMNED')
                NOT NULL DEFAULT 'WAREHOUSED' AFTER `cprf_facility_id`");
    try {
        $pdo->exec('ALTER TABLE utility_assets
            ADD INDEX idx_ua_cprf_facility (cprf_facility_id),
            ADD INDEX idx_ua_cprf_custody (cprf_custody_status)');
    } catch (Throwable $e) {
        // indexes already exist
    }
}

/**
 * POST a custody-change webhook to the CPRF integration endpoint.
 *
 * @param string $route  e.g. 'utilities/equipment/assigned'
 * @param array  $payload JSON body
 * @return array{ok:bool, http_code:int, error:?string, body:string}
 */
function uman_post_to_cprf(string $route, array $payload): array
{
    $url = CPRF_BASE_URL . CPRF_INTEGRATIONS_PREFIX . '/' . ltrim($route, '/');
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'http_code' => 0, 'error' => 'curl_init() failed', 'body' => ''];
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($body),
            'X-API-Key: ' . CPRF_INBOUND_API_KEY,
            'X-UMAN-Callback-Source: UMAN-Assignments-v1',
        ],
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_FAILONERROR    => false,
    ]);
    $respBody = (string)curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_errno($ch) !== 0 ? curl_error($ch) : null;
    curl_close($ch);
    if ($err === null && $respBody !== '') {
        $decoded = json_decode($respBody, true);
        $ok = is_array($decoded) && !empty($decoded['success']);
    } else {
        $ok = $httpCode >= 200 && $httpCode < 300;
    }
    return ['ok' => $ok, 'http_code' => $httpCode, 'error' => $err, 'body' => $respBody];
}
