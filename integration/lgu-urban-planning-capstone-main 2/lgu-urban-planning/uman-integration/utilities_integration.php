<?php
/**
 * Config for the UPAD <-> UMAN (Energy & Utilities) integration.
 *
 * Real values come from environment variables — see .env.example in this
 * folder. Copy it to `.env` (untracked, gitignored) and override values
 * there for a different environment; nothing secret belongs in this file.
 *
 * The defaults below point at the live production domains (the callback
 * path is this project's real physical file path — there is no
 * /api/webhooks/ route, so don't point it there). For local XAMPP testing
 * with both projects under htdocs, override both in `.env` instead (see
 * .env.example's local-dev block, or this folder's own untracked .env).
 */

require_once __DIR__ . '/env.php';

// Base URL of the deployed UMAN system. Override via .env for local XAMPP
// testing (…/htdocs/uman_) or any other non-production environment.
define('UMAN_API_URL', uman_env('UMAN_API_URL', 'https://uman.infragovservices.com'));

// API key/token UMAN expects on inbound requests (Authorization: Bearer).
// Must match UPAD_API_KEY in UMAN's api/integration_config.php.
define('UMAN_API_KEY', uman_env('UMAN_API_KEY', 'UPAD_UMAN_INTEGRATION_KEY_2026'));

// Shared secret used to verify that inbound webhook calls really came from
// UMAN (HMAC signature check). Must match UPAD_WEBHOOK_SECRET in UMAN's
// api/integration_config.php.
define('UMAN_WEBHOOK_SECRET', uman_env('UMAN_WEBHOOK_SECRET', 'UPAD_UMAN_WEBHOOK_SECRET_2026'));

// The URL we give UMAN so it knows where to POST results back to us —
// this project's own webhook receiver, at its real deployed path.
define('UMAN_WEBHOOK_CALLBACK_URL', uman_env(
    'UMAN_WEBHOOK_CALLBACK_URL',
    'https://upad.infragovservices.com/uman-integration/uman_inspection_result.php'
));
