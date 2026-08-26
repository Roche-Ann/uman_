<?php
// core/keep_alive.php
// Called via POST by main.js when the user dismisses the session-expiry warning.
// Refreshes $_SESSION['last_activity'] so the server-side timeout is reset
// in sync with the client-side timer reset.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only honour the ping for authenticated users.
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

$_SESSION['last_activity'] = time();

http_response_code(204); // No Content — nothing to return