<?php
/**
 * Create Test Applicant Account
 * Run this once to create a test applicant user
 */

require_once __DIR__ . '/core/Database.php';

$db = Database::getInstance();

// Test applicant credentials
$username = 'testuser';
$email = 'testuser@example.com';
$password = 'test123';
$firstName = 'Test';
$lastName = 'User';
$phone = '1234567890';

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

// Check if user already exists
$existing = $db->fetchOne(
    "SELECT id FROM users WHERE username = ? OR email = ?",
    [$username, $email]
);

if ($existing) {
    // Update existing user
    $db->query(
        "UPDATE users SET password_hash = ?, first_name = ?, last_name = ?, phone = ?, role = 'applicant', is_active = 1 
         WHERE username = ?",
        [$passwordHash, $firstName, $lastName, $phone, $username]
    );
    echo "Test applicant account updated successfully!<br>";
} else {
    // Create new user
    $db->query(
        "INSERT INTO users (username, email, password_hash, first_name, last_name, role, phone) 
         VALUES (?, ?, ?, ?, ?, 'applicant', ?)",
        [$username, $email, $passwordHash, $firstName, $lastName, $phone]
    );
    echo "Test applicant account created successfully!<br>";
}

echo "<br><strong>Test Applicant Login Credentials:</strong><br>";
echo "Username: <strong>$username</strong><br>";
echo "Password: <strong>$password</strong><br>";
echo "Email: <strong>$email</strong><br>";
echo "<br><a href='login.php'>Go to Login Page</a>";

