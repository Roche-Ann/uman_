<?php
// Removed session_start() as it's handled in includes/auth.php
require_once 'includes/auth.php';

// DB connection is handled by auth.php above

// Minimal .env loader (key=value) for local use
$envPath = __DIR__ . '/.env';
if (is_readable($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        putenv(trim($k) . '=' . trim($v));
    }
}

$APP_SECRET = getenv('APP_SECRET') ?: 'default_secret_key_change_me';
if ($APP_SECRET === '') {
    http_response_code(500);
    exit('APP_SECRET is not configured.');
}

$token = trim($_REQUEST['token'] ?? '');
$uid = (int)($_REQUEST['uid'] ?? 0);
$error = '';
$success = '';

function fetchReset(PDO $pdo, int $uid, string $token, string $secret)
{
    if (!$uid || !$token || !ctype_xdigit($token) || strlen($token) !== 64) {
        return null;
    }
    $hash = hash_hmac('sha256', $token, $secret);
    // Use UTC_TIMESTAMP() for consistent timezone comparison
    $stmt = $pdo->prepare('SELECT * FROM password_resets WHERE user_id = :uid AND token_hash = :hash AND used = 0 AND expires_at > UTC_TIMESTAMP() ORDER BY id DESC LIMIT 1');
    $stmt->execute([':uid' => $uid, ':hash' => $hash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    $resetRow = fetchReset($pdo, $uid, $token, $APP_SECRET);
    if (!$resetRow) {
        $error = 'This reset link is invalid or has expired. Please request a new one.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $pdo->beginTransaction();
            // IMPORTANT: Updated column name to 'password' as per your utility_system.sql
            $updUser = $pdo->prepare('UPDATE users SET password = :hash WHERE id = :uid');
            $updUser->execute([':hash' => $hash, ':uid' => $uid]);

            $markUsed = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE id = :id');
            $markUsed->execute([':id' => $resetRow['id']]);

            // Invalidate other pending tokens for this user
            $pdo->prepare('UPDATE password_resets SET used = 1 WHERE user_id = :uid AND id <> :id')->execute([
                ':uid' => $uid,
                ':id' => $resetRow['id'],
            ]);

            $pdo->commit();
            $success = 'Your password has been updated. You can now log in.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Something went wrong while updating your password.';
            error_log('Reset password error: ' . $e->getMessage());
        }
    }
} else {
    $resetRow = fetchReset($pdo, $uid, $token, $APP_SECRET);
    if (!$resetRow) {
        $error = 'This reset link is invalid or has expired. Please request a new one.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password | LGU Portal</title>
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/responsive.css">
<link rel="icon" type="image/png" href="assets/images/logocityhall.png">
<style>
body {
    min-height: 100vh;
    height: auto;
    display: flex;
    flex-direction: column;
    background: url("assets/images/cityhall.jpeg") center/cover no-repeat fixed;
    position: relative;
    overflow-x: hidden;
}
body::before {
    content: "";
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    backdrop-filter: blur(6px);
    background: rgba(0, 0, 0, 0.35);
    z-index: 0;
}
.nav, .wrapper {
    position: relative;
    z-index: 1;
}
.card {
    background: rgba(255, 255, 255, 0.25) !important;
    backdrop-filter: blur(15px) !important;
    -webkit-backdrop-filter: blur(15px) !important;
}
.card .title { color: #fff !important; }
.card .subtitle { color: #f0f0f0 !important; }
.card .input-box { color: #fff !important; text-align: left; }
.card .input-box label { color: #fff !important; display: block; margin-bottom: 5px; }
.card .small-text { color: #ffffffcc !important; }

.status-box {
    padding: 10px 12px;
    border-radius: 10px;
    margin-bottom: 12px;
    border: 1px solid;
    font-size: 14px;
    text-align: center;
}
.error-box { background:#fee2e2; color:#991b1b; border-color:#fca5a5; }
.success-box { background:#ecfdf3; color:#166534; border-color:#bbf7d0; }
.info-box { background:#eef2ff; color:#1e3a8a; border-color:#cbd5ff; }
</style>
</head>
<body>

<header class="nav">
    <div class="nav-logo">🏛️ LGU Portal</div>
    <div class="nav-links">
        <a href="login.php">Login</a>
        <a class="active">Reset Password</a>
    </div>
</header>

<div class="wrapper">
    <div class="card">
        <img src="assets/images/logocityhall.png" class="icon-top">
        <h2 class="title">Reset Password</h2>
        <p class="subtitle">Enter your new password below.</p>

        <?php if ($error): ?>
            <div class="status-box error-box"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif ($success): ?>
            <div class="status-box success-box"><?php echo htmlspecialchars($success); ?></div>
        <?php else: ?>
            <div class="status-box info-box">Choose a strong password (at least 8 characters).</div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form action="" method="POST">
            <input type="hidden" name="uid" value="<?php echo htmlspecialchars((string)$uid); ?>">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

            <div class="input-box">
                <label>New Password</label>
                <input name="password" type="password" placeholder="Enter new password" required minlength="8" style="width:100%; padding:10px; border-radius:8px; border:none;">
            </div>

            <div class="input-box" style="margin-top:15px;">
                <label>Retype Password</label>
                <input name="confirm" type="password" placeholder="Retype new password" required minlength="8" style="width:100%; padding:10px; border-radius:8px; border:none;">
            </div>

            <button class="btn-primary" type="submit" style="margin-top:20px;" <?php echo $error && !($resetRow ?? null) ? 'disabled' : ''; ?>>Update Password</button>

            <p class="small-text" style="margin-top: 15px; text-align:center;">
                <a href="login.php" class="link">Back to Login</a>
            </p>
        </form>
        <?php else: ?>
            <div style="text-align:center; margin-top:10px;">
                <a class="btn-primary" href="login.php" style="display:inline-block; text-decoration:none;">Go to Login</a>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>