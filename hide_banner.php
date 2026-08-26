<?php
// hide_banner.php
session_start();
if (isset($_POST['id'])) {
    $_SESSION['hide_emergency_banner'] = (int)$_POST['id'];
}
echo 'ok';