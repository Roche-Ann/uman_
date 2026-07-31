<?php
// Step 3: Test main file structure with minimal logic
echo "Step 3: Testing main file structure...<br>";

require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once __DIR__ . '/api/integration_config.php';

try {
    // Test session check
    if (!isLoggedIn() || !isEmployee()) {
        echo "✗ User not logged in or not employee<br>";
        exit;
    }
    echo "✓ User authenticated<br>";
    
    // Test schema ensure function
    uman_ensure_cprf_custody_schema($pdo);
    echo "✓ Schema ensure function works<br>";
    
    // Test table creation (idempotent)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `external_asset_requests` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `request_ref` VARCHAR(50) NOT NULL UNIQUE,
          `source_system` VARCHAR(50) NOT NULL DEFAULT 'CPRF',
          `cprf_facility_id` INT NOT NULL,
          `facility_name` VARCHAR(150) NOT NULL,
          `asset_type` VARCHAR(100) NOT NULL,
          `quantity` INT NOT NULL DEFAULT 1,
          `notes` TEXT NULL,
          `status` ENUM('pending', 'approved', 'fulfilled', 'rejected') NOT NULL DEFAULT 'pending',
          `fulfilled_asset_id` INT NULL,
          `review_notes` TEXT NULL,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ Table creation/verification works<br>";
    
    // Test helper function
    function current_actor_label(): string
    {
        $name = trim((string)($_SESSION['first_name'] ?? ''));
        if ($name !== '' && !empty($_SESSION['last_name'])) {
            return trim($name . ' ' . $_SESSION['last_name']);
        }
        $user = trim((string)($_SESSION['username'] ?? ''));
        return $user !== '' ? $user : 'UMAN staff';
    }
    $actor = current_actor_label();
    echo "✓ Helper function works (actor: " . htmlspecialchars($actor) . ")<br>";
    
    echo "<br>Step 3 complete. Main file structure works.";
    
} catch (Throwable $e) {
    echo "✗ Error: " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "File: " . htmlspecialchars($e->getFile()) . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
}
?>
