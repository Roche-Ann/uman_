<?php
/**
 * Reset Admin Password Script
 * Run this once to set/reset the admin password
 */

require_once __DIR__ . '/core/Database.php';

$db = Database::getInstance();

// Generate new password hash for "admin123"
$password = 'admin123';
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

// Check if admin user exists
$admin = $db->fetchOne("SELECT id FROM users WHERE username = 'admin'");

if ($admin) {
    // Update existing admin
    $db->query(
        "UPDATE users SET password_hash = ? WHERE username = 'admin'",
        [$passwordHash]
    );
    echo "Admin password updated successfully!<br>";
} else {
    // Create admin user if it doesn't exist
    $db->query(
        "INSERT INTO users (username, email, password_hash, first_name, last_name, role) 
         VALUES ('admin', 'admin@lgu.gov.ph', ?, 'System', 'Administrator', 'admin')",
        [$passwordHash]
    );
    echo "Admin user created successfully!<br>";
}

echo "<br><strong>Login Credentials:</strong><br>";
echo "Username: <strong>admin</strong><br>";
echo "Password: <strong>admin123</strong><br>";
echo "<br><a href='login.php'>Go to Login Page</a>";

