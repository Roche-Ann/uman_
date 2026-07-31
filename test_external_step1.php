<?php
// Step 1: Test basic includes
echo "Step 1: Testing basic includes...<br>";

try {
    require_once 'includes/auth.php';
    echo "✓ auth.php loaded<br>";
} catch (Throwable $e) {
    echo "✗ auth.php failed: " . htmlspecialchars($e->getMessage()) . "<br>";
}

try {
    require_once 'includes/db.php';
    echo "✓ db.php loaded<br>";
} catch (Throwable $e) {
    echo "✗ db.php failed: " . htmlspecialchars($e->getMessage()) . "<br>";
}

try {
    require_once __DIR__ . '/api/integration_config.php';
    echo "✓ integration_config.php loaded<br>";
} catch (Throwable $e) {
    echo "✗ integration_config.php failed: " . htmlspecialchars($e->getMessage()) . "<br>";
}

echo "<br>Step 1 complete. All includes loaded successfully.";
?>
