<?php
/**
 * SSO consumer: accepts a signed token from Main LGU (infragovservices.com
 * hub) and establishes a native session, mirroring the same $_SESSION keys
 * login.php sets after a successful password login.
 */
require_once __DIR__ . '/includes/auth.php'; // starts session, loads .env, connects $pdo

function sso_reject(string $message): void
{
    http_response_code(403);
    exit('SSO error: ' . $message);
}

$token = $_GET['sso_token'] ?? '';
$parts = explode('.', $token, 2);
if (count($parts) !== 2) {
    sso_reject('malformed token');
}
[$payloadPart, $signaturePart] = $parts;

$secret = trim((string) (getenv('MAIN_LGU_SSO_SECRET') ?: ''));
if ($secret === '') {
    sso_reject('SSO is not configured on this system');
}
$expectedSig = rtrim(strtr(base64_encode(hash_hmac('sha256', $payloadPart, $secret, true)), '+/', '-_'), '=');
if (!hash_equals($expectedSig, $signaturePart)) {
    sso_reject('invalid signature');
}

$payload = json_decode(base64_decode(strtr($payloadPart, '-_', '+/')), true);
if (!is_array($payload)) {
    sso_reject('invalid payload');
}
if (($payload['target'] ?? '') !== 'uman') {
    sso_reject('token not issued for this system');
}
if (!isset($payload['exp']) || time() > $payload['exp']) {
    sso_reject('token expired');
}

$pdo->exec("CREATE TABLE IF NOT EXISTS sso_used_tokens (
    nonce VARCHAR(64) PRIMARY KEY,
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$nonce = $payload['nonce'] ?? '';
try {
    $pdo->prepare('INSERT INTO sso_used_tokens (nonce) VALUES (?)')->execute([$nonce]);
} catch (\PDOException $e) {
    sso_reject('token already used');
}

$email = (string) ($payload['email'] ?? '');
$fullName = trim((string) ($payload['full_name'] ?? 'Super Admin'));

$stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    $randomPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $insert = $pdo->prepare(
        "INSERT INTO users (full_name, email, password, user_type, is_active, login_attempts) VALUES (?, ?, ?, 'employee', 1, 0)"
    );
    $insert->execute([$fullName, $email, $randomPassword]);
    $newId = $pdo->lastInsertId();

    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$newId]);
    $user = $stmt->fetch();
}

if (!$user['is_active']) {
    sso_reject('this account has been deactivated');
}
if (!empty($user['blocked_until']) && strtotime($user['blocked_until']) > time()) {
    sso_reject('this account is temporarily blocked');
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['user_type'] = $user['user_type'];
$_SESSION['user_name'] = $user['full_name'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['logged_in'] = true;
$_SESSION['just_logged_in'] = true;
$_SESSION['sso_from_mainlgu'] = true;

$pdo->prepare('UPDATE users SET login_attempts = 0, blocked_until = NULL WHERE id = ?')->execute([$user['id']]);

header('Location: utilities_dashboard.php');
exit;
