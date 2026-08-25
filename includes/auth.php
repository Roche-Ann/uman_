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
            if (
                (str_starts_with($v, '"') && str_ends_with($v, '"')) ||
                (str_starts_with($v, "'") && str_ends_with($v, "'"))
            ) {
                $v = substr($v, 1, -1);
            }
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

/**
 * Ensure users/otps schema supports registration + OTP login.
 * Repairs the incomplete `users` definition from uman_utility_system.sql
 * (missing PRIMARY KEY / AUTO_INCREMENT), which blocks Create Account.
 */
function ensureAuthSchema(): void
{
    global $pdo;
    static $done = false;
    if ($done || !($pdo instanceof PDO)) {
        return;
    }

    // Create users table if missing (matches uman_utility_system + working keys)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT NOT NULL AUTO_INCREMENT,
            email VARCHAR(100) NOT NULL,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            user_type ENUM('citizen','employee') NOT NULL DEFAULT 'citizen',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            login_attempts INT NOT NULL DEFAULT 0,
            blocked_until DATETIME DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Repair existing users table if id is not AUTO_INCREMENT (common dump issue)
    try {
        $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'id'")->fetch(PDO::FETCH_ASSOC);
        if ($col && stripos((string)($col['Extra'] ?? ''), 'auto_increment') === false) {
            // Primary key required before AUTO_INCREMENT in MySQL
            try {
                $pdo->exec('ALTER TABLE users ADD PRIMARY KEY (id)');
            } catch (Throwable $e) {
                // already has PK or incompatible — continue
            }
            $pdo->exec('ALTER TABLE users MODIFY id INT NOT NULL AUTO_INCREMENT');
        }
    } catch (Throwable $e) {
        error_log('ensureAuthSchema users AI repair: ' . $e->getMessage());
    }

    // Unique email if missing
    try {
        $idx = $pdo->query("SHOW INDEX FROM users WHERE Key_name = 'email' OR (Non_unique = 0 AND Column_name = 'email')")->fetch(PDO::FETCH_ASSOC);
        if (!$idx) {
            $pdo->exec('ALTER TABLE users ADD UNIQUE KEY email (email)');
        }
    } catch (Throwable $e) {
        error_log('ensureAuthSchema users email unique: ' . $e->getMessage());
    }

    // OTP table for login verification
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS otps (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            otp_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            used TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_otps_user (user_id),
            INDEX idx_otps_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    // Trusted devices table for login bypass
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS trusted_devices (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            device_token VARCHAR(255) NOT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_trusted_devices_user (user_id),
            INDEX idx_trusted_devices_token (device_token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $done = true;
}

/**
 * Register a new citizen account.
 *
 * @return array{success: bool, message: string, user_id?: int}
 */
function registerUser($full_name, $email, $password) {
    global $pdo;

    ensureAuthSchema();

    $full_name = trim((string)$full_name);
    $email = strtolower(trim((string)$email));

    if ($full_name === '' || $email === '' || $password === '') {
        return ['success' => false, 'message' => 'All fields are required.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Please enter a valid email address.'];
    }
    if (strlen($email) > 100 || strlen($full_name) > 100) {
        return ['success' => false, 'message' => 'Name or email is too long.'];
    }

    // Check if email already exists
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Email address is already registered.'];
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Columns match sql/uman_utility_system.sql `users` table.
    // created_at / is_active / login_attempts have defaults, but set explicitly.
    $stmt = $pdo->prepare("
        INSERT INTO users (full_name, email, password, user_type, is_active, login_attempts)
        VALUES (:full_name, :email, :password, 'citizen', 1, 0)
    ");

    try {
        $stmt->execute([
            ':full_name' => $full_name,
            ':email'     => $email,
            ':password'  => $hashedPassword,
        ]);

        $newId = (int)$pdo->lastInsertId();
        if ($newId <= 0) {
            // Table still missing AUTO_INCREMENT — surface a clear message
            error_log('Registration error: lastInsertId was 0 (users.id likely missing AUTO_INCREMENT)');
            return [
                'success' => false,
                'message' => 'Registration failed: users table is missing AUTO_INCREMENT. Run sql/fix_users_table.sql in phpMyAdmin.',
            ];
        }

        return [
            'success' => true,
            'message' => 'Registration successful.',
            'user_id' => $newId,
        ];
    } catch (PDOException $e) {
        error_log('Registration error: ' . $e->getMessage());

        if ((int)$e->getCode() === 23000 || str_contains($e->getMessage(), 'Duplicate')) {
            return ['success' => false, 'message' => 'Email address is already registered.'];
        }

        // Common dump issue: id has no default / no AUTO_INCREMENT
        if (
            str_contains($e->getMessage(), "Field 'id' doesn't have a default value") ||
            str_contains($e->getMessage(), 'auto_increment')
        ) {
            // One more repair attempt, then retry once
            try {
                $pdo->exec('ALTER TABLE users ADD PRIMARY KEY (id)');
            } catch (Throwable $ignored) {
            }
            try {
                $pdo->exec('ALTER TABLE users MODIFY id INT NOT NULL AUTO_INCREMENT');
                $stmt->execute([
                    ':full_name' => $full_name,
                    ':email'     => $email,
                    ':password'  => $hashedPassword,
                ]);
                $newId = (int)$pdo->lastInsertId();
                if ($newId > 0) {
                    return [
                        'success' => true,
                        'message' => 'Registration successful.',
                        'user_id' => $newId,
                    ];
                }
            } catch (Throwable $retryError) {
                error_log('Registration retry error: ' . $retryError->getMessage());
            }

            return [
                'success' => false,
                'message' => 'Registration failed: users.id needs AUTO_INCREMENT. Run sql/fix_users_table.sql in phpMyAdmin.',
            ];
        }

        return ['success' => false, 'message' => 'Registration failed. Please try again.'];
    }
}

function logoutUser() {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit();
}