<?php
// includes/auth.php

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php'; // Ensure database connection is available

// Function to check if a user is logged in
function isLoggedIn(): bool
{
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

// Function to check if the logged-in user is an employee
function isEmployee(): bool
{
    return isLoggedIn() && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'employee';
}

// Function to log in a user
function loginUser(string $email, string $password): array
{
    global $pdo;

    // Check for brute-force attempts
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempt_time > (NOW() - INTERVAL 15 MINUTE)");
    $stmt->execute([$ipAddress]);
    $attempts = $stmt->fetchColumn();

    if ($attempts >= 5) { // 5 attempts in 15 minutes
        return ['success' => false, 'message' => 'Too many failed login attempts. Please try again after 15 minutes.'];
    }

    $stmt = $pdo->prepare("SELECT id, full_name, email, password, user_type, status FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        if ($user['status'] === 'active') {
            // Clear login attempts on successful login
            $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ?")->execute([$ipAddress]);

            // Do NOT set session variables here directly for OTP flow
            // Instead, return user data for OTP processing
            return [
                'success' => true,
                'user_id' => $user['id'],
                'full_name' => $user['full_name'],
                'email' => $user['email'],
                'user_type' => $user['user_type']
            ];
        } else {
            // Log failed attempt
            $pdo->prepare("INSERT INTO login_attempts (ip_address, attempt_time) VALUES (?, NOW())")->execute([$ipAddress]);
            return ['success' => false, 'message' => 'Your account is not active. Please contact support.'];
        }
    } else {
        // Log failed attempt
        $pdo->prepare("INSERT INTO login_attempts (ip_address, attempt_time) VALUES (?, NOW())")->execute([$ipAddress]);
        $remainingAttempts = 5 - ($attempts + 1);
        $message = 'Invalid email or password.';
        if ($remainingAttempts > 0) {
            $message .= " You have {$remainingAttempts} attempt(s) remaining.";
        } else {
            $message .= " Your account will be locked for 15 minutes.";
        }
        return ['success' => false, 'message' => $message, 'attempts_left' => $remainingAttempts];
    }
}

// Function to log out a user
function logoutUser(): void
{
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit();
}

/**
 * Ensures that the necessary authentication schema tables (login_attempts, password_resets, otps) exist in the database.
 * This function should be called once during application initialization or setup.
 */
function ensureAuthSchema(): void
{
    global $pdo;

    // Create login_attempts table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `login_attempts` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `ip_address` VARCHAR(45) NOT NULL,
            `attempt_time` DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Create password_resets table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `password_resets` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `token_hash` VARCHAR(255) NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `used` BOOLEAN DEFAULT FALSE,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `user_id_idx` (`user_id`),
            INDEX `token_hash_idx` (`token_hash`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Create otps table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `otps` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `otp_hash` VARCHAR(255) NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `used` BOOLEAN DEFAULT FALSE,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `user_id_idx` (`user_id`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}

// Call ensureAuthSchema once to make sure tables exist
ensureAuthSchema();
