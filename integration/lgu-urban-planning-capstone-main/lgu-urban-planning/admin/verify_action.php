<?php

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';

// --- SECURITY: Must be an active admin or super_admin session ---
$auth = new Auth();
$auth->requireLogin();
$auth->requireRole(['admin', 'super_admin']);

// --- Only accept AJAX POST requests ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Method not allowed.']));
}

while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json');

$db  = Database::getInstance();
$pdo = $db->getConnection();

$userId       = $_SESSION['user_id']     ?? 0;
$password     = $_POST['password']       ?? '';
$exportReason = trim($_POST['reason']    ?? 'Not specified');
$exportType   = trim($_POST['export_type'] ?? 'CSV');
$tableName    = trim($_POST['table_name']  ?? 'unknown');
$ipAddress    = $_SERVER['REMOTE_ADDR']  ?? '0.0.0.0';
$userAgent    = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

// --- Helper: write to audit_logs ---
function writeAuditLog(PDO $pdo, int $userId, string $action, string $tableName, string $status, string $details, string $ip, string $agent): void {
    $stmt = $pdo->prepare(
        "INSERT INTO audit_logs (user_id, action, entity_type, details, ip_address, user_agent, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())"
    );
    $stmt->execute([$userId, $action, $tableName, $details, $ip, $agent]);
}

// --- Validate inputs ---
if (empty($password)) {
    try { writeAuditLog($pdo, $userId, "Export {$exportType} - FAILED", $tableName,
        'Failed', "Reason: {$exportReason} | Error: Password field was empty.", $ipAddress, $userAgent); } catch(Exception $e) {}
    echo json_encode(['success' => false, 'message' => 'Password is required.']);
    exit;
}

// --- Fetch the current admin's password hash ---
try {
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$userId]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

if (!$admin) {
    echo json_encode(['success' => false, 'message' => 'Session invalid. Please log in again.']);
    exit;
}

// --- Verify password ---
if (!password_verify($password, $admin['password_hash'])) {
    try { writeAuditLog($pdo, $userId, "Export {$exportType} - FAILED", $tableName,
        'Failed', "Reason: {$exportReason} | Error: Incorrect password entered.", $ipAddress, $userAgent); } catch(Exception $e) {}
    echo json_encode(['success' => false, 'message' => 'Incorrect password. Export denied.']);
    exit;
}

// --- Password correct: generate a one-time export token ---
$token     = bin2hex(random_bytes(32));          // 64-char hex token
$expiresAt = date('Y-m-d H:i:s', time() + 60);  // Valid for 60 seconds only

// Store token in session (no extra DB table needed)
$_SESSION['export_token']            = $token;
$_SESSION['export_token_expires']    = $expiresAt;
$_SESSION['export_token_table']      = $tableName;
$_SESSION['export_token_type']       = $exportType;

// Release the session lock so the download request can read the token immediately.
session_write_close();

// --- Log the successful verification ---
try { writeAuditLog($pdo, $userId, "Export {$exportType} - SUCCESS", $tableName,
    'Success', "Reason: {$exportReason} | Token issued. Expires: {$expiresAt}.", $ipAddress, $userAgent); } catch(Exception $e) {}

echo json_encode([
    'success' => true,
    'token'   => $token,
    'message' => 'Verification successful. Starting download...'
]);
exit;