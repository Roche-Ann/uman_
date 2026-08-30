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
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        return false;
    }
    return validateUserSession();
}

/**
 * Validate that the current session token has not been revoked.
 */
function validateUserSession(): bool {
    global $pdo;
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    $userId = (int)$_SESSION['user_id'];

    // Lazily register session token if missing
    if (empty($_SESSION['auth_session_token'])) {
        registerUserSession($userId);
        return true;
    }

    try {
        ensureAuthSchema();
        $token = $_SESSION['auth_session_token'];
        $stmt = $pdo->prepare("SELECT id FROM user_sessions WHERE user_id = ? AND session_token = ? LIMIT 1");
        $stmt->execute([$userId, $token]);
        $exists = $stmt->fetchColumn();

        if (!$exists) {
            // Session was revoked remotely (e.g. from password change or device logout)
            session_unset();
            session_destroy();
            return false;
        }

        // Periodically update last_activity timestamp (at most once every 60 seconds)
        $now = time();
        if (!isset($_SESSION['last_activity_sync']) || ($now - (int)$_SESSION['last_activity_sync']) > 60) {
            $_SESSION['last_activity_sync'] = $now;
            $upd = $pdo->prepare("UPDATE user_sessions SET last_activity = NOW() WHERE user_id = ? AND session_token = ?");
            $upd->execute([$userId, $token]);
        }

        return true;
    } catch (Throwable $e) {
        return true;
    }
}

/**
 * Extract clean client IP address.
 */
function getClientIpAddress(): string {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return trim($_SERVER['HTTP_CF_CONNECTING_IP']);
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($parts[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

/**
 * Parse user agent into device type, browser name, and operating system platform.
 */
function parseUserAgent(?string $ua): array {
    $deviceType = 'Desktop';
    $browser = 'Web Browser';
    $platform = 'Unknown OS';

    if (!$ua) {
        return ['device_type' => $deviceType, 'browser' => $browser, 'platform' => $platform];
    }

    // Platform detection
    if (stripos($ua, 'windows nt 10') !== false) {
        $platform = 'Windows 10/11';
    } elseif (stripos($ua, 'windows nt 6.3') !== false) {
        $platform = 'Windows 8.1';
    } elseif (stripos($ua, 'windows nt 6.1') !== false) {
        $platform = 'Windows 7';
    } elseif (stripos($ua, 'windows') !== false) {
        $platform = 'Windows';
    } elseif (stripos($ua, 'android') !== false) {
        $platform = 'Android';
        $deviceType = 'Mobile';
    } elseif (stripos($ua, 'iphone') !== false) {
        $platform = 'iOS (iPhone)';
        $deviceType = 'Mobile';
    } elseif (stripos($ua, 'ipad') !== false) {
        $platform = 'iPadOS (iPad)';
        $deviceType = 'Tablet';
    } elseif (stripos($ua, 'macintosh') !== false || stripos($ua, 'mac os x') !== false) {
        $platform = 'macOS';
    } elseif (stripos($ua, 'linux') !== false) {
        $platform = 'Linux';
    }

    // Device Type refinement
    if (stripos($ua, 'tablet') !== false || stripos($ua, 'ipad') !== false) {
        $deviceType = 'Tablet';
    } elseif ((stripos($ua, 'mobile') !== false || stripos($ua, 'phone') !== false) && $deviceType !== 'Tablet') {
        $deviceType = 'Mobile';
    }

    // Browser detection
    if (preg_match('/edg\/([\d\.]+)/i', $ua)) {
        $browser = 'Microsoft Edge';
    } elseif (preg_match('/opr\/([\d\.]+)/i', $ua) || stripos($ua, 'opera') !== false) {
        $browser = 'Opera';
    } elseif (preg_match('/chrome\/([\d\.]+)/i', $ua)) {
        $browser = 'Google Chrome';
    } elseif (preg_match('/firefox\/([\d\.]+)/i', $ua)) {
        $browser = 'Mozilla Firefox';
    } elseif (preg_match('/safari\/([\d\.]+)/i', $ua) && stripos($ua, 'chrome') === false) {
        $browser = 'Apple Safari';
    }

    return [
        'device_type' => $deviceType,
        'browser' => $browser,
        'platform' => $platform
    ];
}

/**
 * Register a new active device session for a user.
 */
function registerUserSession(int $userId): string {
    global $pdo;
    ensureAuthSchema();

    try {
        $token = bin2hex(random_bytes(32));
    } catch (Throwable $e) {
        $token = bin2hex(openssl_random_pseudo_bytes(32));
    }

    $_SESSION['auth_session_token'] = $token;
    $_SESSION['last_activity_sync'] = time();

    $sessId = session_id() ?: $token;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $ip = getClientIpAddress();
    $deviceInfo = parseUserAgent($ua);

    $location = 'Quezon City, Philippines';
    if ($ip === '127.0.0.1' || $ip === '::1' || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')) {
        $location = 'Local Network · Quezon City';
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_sessions (user_id, session_token, session_id, ip_address, user_agent, device_type, browser, platform, location, last_activity, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $userId,
            $token,
            substr($sessId, 0, 128),
            substr($ip, 0, 45),
            $ua ? substr($ua, 0, 500) : null,
            $deviceInfo['device_type'],
            $deviceInfo['browser'],
            $deviceInfo['platform'],
            $location
        ]);
    } catch (Throwable $e) {
        error_log('registerUserSession error: ' . $e->getMessage());
    }

    return $token;
}

/**
 * Fetch all active logged-in device sessions for a user.
 */
function getUserActiveSessions(int $userId): array {
    global $pdo;
    ensureAuthSchema();

    $currentToken = $_SESSION['auth_session_token'] ?? '';
    try {
        $stmt = $pdo->prepare("SELECT * FROM user_sessions WHERE user_id = ? ORDER BY last_activity DESC");
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sessions = [];
        foreach ($rows as $row) {
            $isCurrent = ($currentToken !== '' && $row['session_token'] === $currentToken);
            $row['is_current'] = $isCurrent;
            $sessions[] = $row;
        }

        // Sort with current session pinned first
        usort($sessions, function($a, $b) {
            if ($a['is_current'] && !$b['is_current']) return -1;
            if (!$a['is_current'] && $b['is_current']) return 1;
            return strtotime($b['last_activity']) <=> strtotime($a['last_activity']);
        });

        return $sessions;
    } catch (Throwable $e) {
        error_log('getUserActiveSessions error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Revoke a single active device session by session ID.
 */
function revokeUserSession(int $userId, int $sessionId): bool {
    global $pdo;
    ensureAuthSchema();
    try {
        $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE id = ? AND user_id = ?");
        $stmt->execute([$sessionId, $userId]);
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        error_log('revokeUserSession error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Revoke all other active device sessions for a user except the current one.
 * Also invalidates trusted devices tokens.
 */
function revokeAllUserSessionsExceptCurrent(int $userId, ?string $currentToken = null): int {
    global $pdo;
    ensureAuthSchema();
    if ($currentToken === null) {
        $currentToken = $_SESSION['auth_session_token'] ?? '';
    }

    try {
        if ($currentToken !== '') {
            $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ? AND session_token != ?");
            $stmt->execute([$userId, $currentToken]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?");
            $stmt->execute([$userId]);
        }
        $count = $stmt->rowCount();

        // Invalidate trusted devices so old devices must verify OTP again
        try {
            $pdo->prepare("DELETE FROM trusted_devices WHERE user_id = ?")->execute([$userId]);
        } catch (Throwable $e) {}

        return $count;
    } catch (Throwable $e) {
        error_log('revokeAllUserSessionsExceptCurrent error: ' . $e->getMessage());
        return 0;
    }
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

    // Active User Sessions & Device Tracker table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_sessions (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_token VARCHAR(64) NOT NULL,
            session_id VARCHAR(128) NOT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(500) DEFAULT NULL,
            device_type VARCHAR(50) DEFAULT 'Desktop',
            browser VARCHAR(100) DEFAULT NULL,
            platform VARCHAR(100) DEFAULT NULL,
            location VARCHAR(150) DEFAULT NULL,
            last_activity DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_sessions_user (user_id),
            INDEX idx_user_sessions_token (session_token),
            INDEX idx_user_sessions_activity (last_activity)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
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