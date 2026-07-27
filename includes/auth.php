<?php
// includes/auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load .env if not already loaded
if (!isset($GLOBALS['_ENV_LOADED'])) {
    $envPath = __DIR__ . '/../.env';
    if (is_readable($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) continue;
            if (!str_contains($line, '=')) continue;
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v);
            if ($k === '') continue;
            putenv("$k=$v");
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
        }
    }
    $GLOBALS['_ENV_LOADED'] = true;
}

// 1. Database Connection from .env
$host    = getenv('DB_HOST') ?: '127.0.0.1';
$db      = getenv('DB_NAME') ?: 'utility_system';
$user    = getenv('DB_USER') ?: 'root';
$pass    = getenv('DB_PASS') ?: '';
$charset = getenv('DB_CHARSET') ?: 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

function isLoggedIn() {
    // Check for logged_in flag to ensure OTP was passed
    return isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function isEmployee() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'employee';
}

function isCitizen() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'citizen';
}

function loginUser($email, $password) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return ['success' => false, 'message' => 'Invalid email or password'];
    }
    
    // Check if account is blocked
    if ($user['blocked_until'] && strtotime($user['blocked_until']) > time()) {
        $blockTime = date('Y-m-d H:i:s', strtotime($user['blocked_until']));
        return ['success' => false, 'message' => "Account blocked until $blockTime"];
    }
    
    // Check if account is active
    if (!$user['is_active']) {
        return ['success' => false, 'message' => 'Account is deactivated'];
    }
    
    // Verify password
    if (password_verify($password, $user['password'])) {
        // Reset login attempts
        $stmt = $pdo->prepare("UPDATE users SET login_attempts = 0, blocked_until = NULL WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        // IMPORTANT: We do NOT set the full session here yet.
        // We only return the user data so login.php can handle the OTP process.
        return [
            'success' => true, 
            'user_id' => $user['id'],
            'full_name' => $user['full_name'],
            'user_type' => $user['user_type']
        ];
    } else {
        // Increment login attempts
        $newAttempts = $user['login_attempts'] + 1;
        $stmt = $pdo->prepare("UPDATE users SET login_attempts = ? WHERE id = ?");
        $stmt->execute([$newAttempts, $user['id']]);
        
        if ($newAttempts >= 5) {
            $blockUntil = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            $stmt = $pdo->prepare("UPDATE users SET blocked_until = ? WHERE id = ?");
            $stmt->execute([$blockUntil, $user['id']]);
            return [
                'success' => false, 
                'message' => "Too many failed attempts. Blocked for 30 minutes.",
                'attempts_left' => 0
            ];
        }
        
        return [
            'success' => false, 
            'message' => 'Invalid email or password',
            'attempts_left' => 5 - $newAttempts
        ];
    }
}

// =============================================
// ✅ ADD THIS FUNCTION – registerUser()
// =============================================
/**
 * Register a new user account
 * 
 * @param string $full_name
 * @param string $email
 * @param string $password
 * @return array ['success' => bool, 'message' => string]
 */
function registerUser($full_name, $email, $password) {
    global $pdo;
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Email address is already registered.'];
    }
    
    // Hash the password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new user (default role: citizen)
    $stmt = $pdo->prepare("
        INSERT INTO users (full_name, email, password, user_type, created_at)
        VALUES (:full_name, :email, :password, 'citizen', NOW())
    ");
    
    try {
        $stmt->execute([
            ':full_name' => $full_name,
            ':email'     => $email,
            ':password'  => $hashedPassword,
        ]);
        return ['success' => true, 'message' => 'Registration successful.'];
    } catch (PDOException $e) {
        error_log("Registration error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Registration failed. Please try again.'];
    }
}
// =============================================
// END OF registerUser() FUNCTION
// =============================================

function logoutUser() {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit();
}