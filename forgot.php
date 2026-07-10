<?php
// Removed session_start() because it is handled by auth.php
require_once 'includes/auth.php';
require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

// Grab the database connection from auth.php
// Connect directly to the new utility_system database
$host = '127.0.0.1';
$db   = 'utility_system';
$user = 'root'; // Laragon default
$pass = '';     // Laragon default
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Minimal .env loader (key=value) for local use
$envPath = __DIR__ . '/.env';
if (is_readable($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if ($k === '') continue;
        putenv("$k=$v");
        $_ENV[$k] = $v;
        $_SERVER[$k] = $v;
    }
}

$APP_SECRET = getenv('APP_SECRET') ?: 'default_secret_key_change_me';
if ($APP_SECRET === '') {
    http_response_code(500);
    exit('APP_SECRET is not configured.');
}

// Ensure password_resets table exists in the new database
$pdo->exec("
    CREATE TABLE IF NOT EXISTS password_resets (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash VARCHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_pr_user (user_id),
        INDEX idx_pr_expires (expires_at),
        CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

function buildResetLink(int $userId, string $token): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $https = !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off';
    $scheme = $https ? 'https' : 'http';
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['REQUEST_URI'] ?? '/')), '/');
    return "{$scheme}://{$host}{$base}/reset-password.php?uid={$userId}&token={$token}";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if ($email === '') {
        header('Location: forgot.php?status=missing');
        exit;
    }

    $userStmt = $pdo->prepare('SELECT id, email, full_name FROM users WHERE email = :email LIMIT 1');
    $userStmt->execute([':email' => $email]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash_hmac('sha256', $token, $APP_SECRET);
        // Use UTC timezone for consistency with database comparison
        $expiresAt = (new DateTime('+30 minutes', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        // Invalidate old tokens for this user when creating a new one
        $pdo->prepare('UPDATE password_resets SET used = 1 WHERE user_id = :uid AND used = 0')->execute([
            ':uid' => $user['id'],
        ]);

        $insert = $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at, used) VALUES (:uid, :hash, :exp, 0)');
        $insert->execute([
            ':uid' => $user['id'],
            ':hash' => $tokenHash,
            ':exp' => $expiresAt,
        ]);

        $resetLink = buildResetLink((int)$user['id'], $token);
        $subject = 'Password Reset Instructions';
        $html = "
            <p>Hello {$user['full_name']},</p>
            <p>We received a request to reset your password. This link expires in 30 minutes.</p>
            <p><a href=\"{$resetLink}\">Reset your password</a></p>
            <p>If you didn't request this, you can ignore this email.</p>
        ";
        $text = "Hello {$user['full_name']},\n\nWe received a request to reset your password. This link expires in 30 minutes.\n\nReset your password: {$resetLink}\n\nIf you didn't request this, you can ignore this email.";

        // Send immediately
        $dsnRaw = getenv('MAILER_DSN') ?: 'smtp://localhost';
        $dsn = is_string($dsnRaw) ? trim($dsnRaw) : 'smtp://localhost';
        $transport = null;
        try {
            $transport = Transport::fromDsn($dsn);
        } catch (Throwable $e) {
            error_log('Password reset mail DSN error: ' . $e->getMessage());
        }

        if ($transport) {
            $mailer = new Mailer($transport);
            $from = getenv('MAILER_FROM') ?: 'no-reply@localhost';
            $mail = (new Email())
                ->from($from)
                ->to($user['email'])
                ->subject($subject)
                ->text($text)
                ->html($html);

            try {
                $mailer->send($mail);
            } catch (Throwable $e) {
                error_log('Password reset mail error: ' . $e->getMessage());
            }
        }
    }

    header('Location: forgot.php?status=sent');
    exit;
}

$status = $_GET['status'] ?? '';
$msg = '';
if ($status === 'sent') {
    $msg = 'If that email exists, reset instructions have been sent.';
} elseif ($status === 'missing') {
    $msg = 'Please enter your email address.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password | LGU Portal</title>
<link rel="stylesheet" href="assets/css/style.css">
<link rel="icon" type="image/png" href="assets/images/logocityhall.png">
<style>

body {
    min-height: 100vh;
    height: auto;
    display: flex;
    flex-direction: column;

    /* Updated to match new system paths */
    background: url("assets/images/cityhall.jpeg") center/cover no-repeat fixed;
    position: relative;
    overflow-x: hidden;
    overflow-y: auto;
}

/* NEW — Blur overlay */
body::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;

    backdrop-filter: blur(6px); /* actual blur */
    background: rgba(0, 0, 0, 0.35); /* dark overlay */
    z-index: 0; /* keeps blur behind content */
}

/* Make content appear ABOVE blur */
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
.card .input-box { color: #fff !important; margin-bottom: 15px;}
.card .input-box label { color: #fff !important; }
.card .small-text { color: #ffffffcc !important; }
</style>
</head>

<body>

<header class="nav">
    <div class="nav-logo">🏛️ LGU Portal</div>
    <div class="nav-links">
        <a href="login.php">Login</a>
        <a class="active">Forgot Password</a>
    </div>
</header>

<div class="wrapper">
    <div class="card">

        <img src="assets/images/logocityhall.png" class="icon-top">

        <h2 class="title">Forgot Password</h2>
        <p class="subtitle">Enter your email to receive reset instructions.</p>

        <?php if ($msg): ?>
        <div style="background:#eef2ff;color:#1e3a8a;padding:10px 12px;border-radius:10px;margin-bottom:12px;border:1px solid #cbd5ff;text-align:center;font-size:14px;">
            <?php echo htmlspecialchars($msg); ?>
        </div>
        <?php endif; ?>

        <form action="forgot.php" method="POST">

            <div class="input-box">
                <label>Email Address</label>
                <input name="email" type="email" placeholder="name@lgu.gov.ph" required maxlength="190">
                <span class="icon">📧</span>
            </div>

            <button class="btn-primary" type="submit" style="margin-top: 15px;">Send Reset Link</button>

            <p class="small-text" style="margin-top: 15px; text-align: center;">
                Remembered your password?
                <a href="login.php" class="link">Back to Login</a>
            </p>

        </form>
    </div>
</div>

</body>
</html>