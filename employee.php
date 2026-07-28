<?php
/**
 * Backward-compatible entrypoint.
 * Employee home is the utilities dashboard (employee.php never existed as a page).
 */
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

if (isEmployee()) {
    header('Location: utilities_dashboard.php');
} else {
    header('Location: citizen.php');
}
exit();
