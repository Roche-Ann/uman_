<?php
/**
 * Generate Password Hash
 */

$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Password: admin123\n";
echo "Hash: " . $hash . "\n";
echo "\nVerification: " . (password_verify($password, $hash) ? 'SUCCESS' : 'FAILED') . "\n";

