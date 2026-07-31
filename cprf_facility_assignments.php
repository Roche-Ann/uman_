<?php
/**
 * Standalone Facility Assignments page — DEPRECATED.
 *
 * This workflow now lives inside the CPRF Integration Hub as Tab #2 of
 * external_asset_requests.php. Redirect anyone who bookmarked the old URL
 * or is coming from an old nav link.
 */
require_once 'includes/auth.php';
if (!isLoggedIn() || !isEmployee()) {
    header('Location: login.php');
    exit;
}
header('Location: external_asset_requests.php#hub-assignments');
exit;
