<?php
/**
 * Config for the UPAD <-> IPMS (Roads & Bridges) integration.
 *
 * Real values must come from environment variables — see .env.example in
 * this folder. Copy it to `.env` (untracked, see .gitignore) and fill in
 * the real values there; nothing secret belongs in this file.
 *
 *   IPMS_BASE_URL          Deployed IPMS URL. Defaults to the live production
 *                           domain below, so a deployed UPAD works without a
 *                           .env (which is gitignored and therefore absent on
 *                           the server). Override in .env for local XAMPP.
 *   URBAN_PLANNING_API_KEY Shared API key sent as the X-API-Key header on
 *                           every request. Must be the exact same string as
 *                           URBAN_PLANNING_API_KEY in IPMS's own .env — get
 *                           it from the IPMS teammate, don't invent one.
 *
 * There is no HMAC/webhook secret in this integration — IPMS has no
 * outbound webhook sender, everything inbound is us polling them.
 */

require_once __DIR__ . '/env.php';

// Base URL of the deployed IPMS system. The default must be the real
// production domain: `.env` is gitignored, so a deployed UPAD has none and
// falls back to this value. (A localhost default made the production server
// POST to its *own* web root and get a LiteSpeed 404 back.)
define('IPMS_BASE_URL', ipms_env('IPMS_BASE_URL', 'https://ipms.infragovservices.com'));
define('URBAN_PLANNING_API_KEY', ipms_env('URBAN_PLANNING_API_KEY', ''));
