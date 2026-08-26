<?php
/**
 * SSO consumer: accepts a signed token from Main LGU (infragovservices.com
 * hub) and establishes a native session, mirroring the same $_SESSION keys
 * Auth::login() sets (user_id, username, role, full_name).
 */
session_start();
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/sso_env.php';

$auth = new Auth();
$db = Database::getInstance();

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

$secret = upad_sso_env('MAIN_LGU_SSO_SECRET', '');
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
if (($payload['target'] ?? '') !== 'urbanplanning') {
    sso_reject('token not issued for this system');
}
if (!isset($payload['exp']) || time() > $payload['exp']) {
    sso_reject('token expired');
}

$db->query("CREATE TABLE IF NOT EXISTS sso_used_tokens (
    nonce VARCHAR(64) PRIMARY KEY,
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$nonce = $payload['nonce'] ?? '';
try {
    $db->query('INSERT INTO sso_used_tokens (nonce) VALUES (?)', [$nonce]);
} catch (PDOException $e) {
    sso_reject('token already used');
}

$email = (string) ($payload['email'] ?? '');
$fullName = trim((string) ($payload['full_name'] ?? 'Super Admin'));

$user = $db->fetchOne('SELECT * FROM users WHERE email = ? LIMIT 1', [$email]);

if (!$user) {
    $nameParts = explode(' ', $fullName, 2);
    $firstName = $nameParts[0] !== '' ? $nameParts[0] : 'Super';
    $lastName = $nameParts[1] ?? 'Admin';
    $username = 'sso_' . substr(md5($email), 0, 10);
    $randomPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

    $db->query(
        "INSERT INTO users (username, email, password_hash, first_name, last_name, role, is_active, is_verified)
         VALUES (?, ?, ?, ?, ?, 'super_admin', 1, 1)",
        [$username, $email, $randomPassword, $firstName, $lastName]
    );
    $newId = $db->lastInsertId();
    $user = $db->fetchOne('SELECT * FROM users WHERE id = ?', [$newId]);
}

if (!$user['is_active']) {
    sso_reject('this account has been deactivated');
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['role'] = $user['role'];
$_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
$_SESSION['sso_from_mainlgu'] = true;

$db->query('UPDATE users SET last_activity = NOW() WHERE id = ?', [$user['id']]);
$auth->logActivity($user['id'], 'login', 'user', $user['id'], 'Logged in via Main LGU SSO');

$auth->redirectToDashboard();
