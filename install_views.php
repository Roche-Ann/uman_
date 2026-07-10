<?php
// install_views.php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$pdo = $pdo ?? db();

try {
    $sqlFile = 'sql/utility_views.sql';
    if (!file_exists($sqlFile)) {
        die("Error: SQL file '{$sqlFile}' not found.\n");
    }

    $sql = file_get_contents($sqlFile);
    $pdo->exec($sql);
    echo "Successfully initialized database aggregated views!\n";
} catch (PDOException $e) {
    die("Setup failed: " . $e->getMessage() . "\n");
}
