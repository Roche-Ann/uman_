<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/auth.php';

// Set default page
if (isLoggedIn()) {
    if ($_SESSION['user_type'] == 'employee') {
        header('Location: utilities_dashboard.php');
    } else {
        header('Location: citizen.php');
    }
    exit();
} else {
    header('Location: home.php');
    exit();
}
?>