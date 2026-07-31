<?php
/**
 * Config for the UPAD <-> IPMS (Roads & Bridges) integration.
 *
 * Real values must come from environment variables — see .env.example in
 * this folder. Copy it to `.env` (untracked, see .gitignore) and fill in
 * the real values there; nothing secret belongs in this file.
 *
 *   IPMS_BASE_URL          Deployed IPMS URL, e.g. https://ipms.example.gov.
 *                           Coordinate the real value with the IPMS teammate —
 *                           as of this writing IPMS is still localhost-only in
 *                           dev, so this integration cannot reach them for
 *                           real until they deploy somewhere both sides can
 *                           reach.
 *   URBAN_PLANNING_API_KEY Shared API key sent as the X-API-Key header on
 *                           every request. Must be the exact same string as
 *                           URBAN_PLANNING_API_KEY in IPMS's own .env — get
 *                           it from the IPMS teammate, don't invent one.
 *
 * There is no HMAC/webhook secret in this integration — IPMS has no
 * outbound webhook sender, everything inbound is us polling them.
 */

require_once __DIR__ . '/env.php';

define('IPMS_BASE_URL', ipms_env('IPMS_BASE_URL', 'http://localhost/ipms'));
define('URBAN_PLANNING_API_KEY', ipms_env('URBAN_PLANNING_API_KEY', ''));
