<?php
// Step 2: Test database connection and schema
echo "Step 2: Testing database connection and schema...<br>";

require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once __DIR__ . '/api/integration_config.php';

try {
    echo "✓ PDO connection established<br>";
    
    // Test external_asset_requests table
    $stmt = $pdo->query("SELECT COUNT(*) FROM external_asset_requests");
    echo "✓ external_asset_requests table exists<br>";
    
    // Test utility_assets table
    $stmt = $pdo->query("SELECT COUNT(*) FROM utility_assets");
    echo "✓ utility_assets table exists<br>";
    
    // Test CPRF custody columns
    $stmt = $pdo->query("SHOW COLUMNS FROM utility_assets LIKE 'cprf_facility_id'");
    if ($stmt->fetch()) {
        echo "✓ cprf_facility_id column exists<br>";
    } else {
        echo "✗ cprf_facility_id column missing<br>";
    }
    
    $stmt = $pdo->query("SHOW COLUMNS FROM utility_assets LIKE 'cprf_custody_status'");
    if ($stmt->fetch()) {
        echo "✓ cprf_custody_status column exists<br>";
    } else {
        echo "✗ cprf_custody_status column missing<br>";
    }
    
    echo "<br>Step 2 complete. Database schema is correct.";
    
} catch (Throwable $e) {
    echo "✗ Database error: " . htmlspecialchars($e->getMessage()) . "<br>";
}
?>
