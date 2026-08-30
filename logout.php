<?php
require_once __DIR__ . '/includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If this session originated from a Main LGU SSO launch, send the admin
// back to the SSO hub instead of this system's own login page.
$returnToMainLgu = !empty($_SESSION['sso_from_mainlgu']);

if (isset($_SESSION['user_id']) && isset($_SESSION['auth_session_token'])) {
    try {
        global $pdo;
        if ($pdo instanceof PDO) {
            $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ? AND session_token = ?");
            $stmt->execute([(int)$_SESSION['user_id'], $_SESSION['auth_session_token']]);
        }
    } catch (Throwable $e) {}
}

session_unset();
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