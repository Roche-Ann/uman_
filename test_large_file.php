<?php
// Test if file size is the issue
echo "Testing large file execution...<br>";
echo "File size: " . filesize(__FILE__) . " bytes<br>";

require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once __DIR__ . '/api/integration_config.php';

// Add some content to increase file size
$content = str_repeat("Test content line\n", 10000);
echo "Content length: " . strlen($content) . " characters<br>";

echo "✓ Large file executed successfully<br>";
?>
