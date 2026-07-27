<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If this session originated from a Main LGU SSO launch, send the admin
// back to the SSO hub instead of this system's own login page.
$returnToMainLgu = !empty($_SESSION['sso_from_mainlgu']);

session_destroy();

if ($returnToMainLgu) {
    $mainLguUrl = ($_SERVER['SERVER_NAME'] ?? '') === 'localhost'
        ? 'http://localhost/Main%20LGU/admin/dashboard.php'
        : 'https://infragovservices.com/admin/dashboard.php';
    header('Location: ' . $mainLguUrl);
    exit();
}

header('Location: login.php');
exit();
?>