<?php
/**
 * Config for the UPAD <-> UMAN (Energy & Utilities) integration.
 *
 * ⚠️ PLACEHOLDER VALUES — replace all of these once the UMAN team issues
 * real credentials and confirms their actual endpoint path. UMAN is the
 * Energy/Utilities counterpart to Roads' IPMS.
 *
 * Recommended: move these into environment variables (.env) instead of
 * hardcoding, especially UMAN_API_KEY and UMAN_WEBHOOK_SECRET.
 */

// Base URL of their system. Confirm the exact API path with the
// Energy/Utilities team (e.g. it might be /api/v1/inspection-requests,
// /api/v2/grid/requests, etc.)
define('UMAN_API_URL', 'https://uman.infragovservices.com');

// API key/token — must match UPAD_API_KEY in UMAN's .env (or the default
// 'UPAD_UMAN_INTEGRATION_KEY_2026' if no .env is set on the UMAN side).
define('UMAN_API_KEY', 'UPAD_UMAN_INTEGRATION_KEY_2026');

// Shared HMAC-SHA256 secret — must match UPAD_WEBHOOK_SECRET in UMAN's .env
// (or the default 'UPAD_UMAN_WEBHOOK_SECRET_2026' if no .env is set).
define('UMAN_WEBHOOK_SECRET', 'UPAD_UMAN_WEBHOOK_SECRET_2026');

// The URL we give to the Energy/Utilities team so they know where to POST
// results back to us.
define('UMAN_WEBHOOK_CALLBACK_URL', 'https://upad.infragovservices.com/api/webhooks/uman_inspection_result.php');