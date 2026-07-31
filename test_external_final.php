<?php
// Final diagnostic: Test actual external_asset_requests.php with error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting external_asset_requests.php execution...<br><br>";

try {
    // Include the actual file
    include 'external_asset_requests.php';
    echo "<br><br>✓ File executed successfully";
} catch (Throwable $e) {
    echo "<br><br>✗ Error: " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "File: " . htmlspecialchars($e->getFile()) . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
    echo "Trace:<br><pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>
