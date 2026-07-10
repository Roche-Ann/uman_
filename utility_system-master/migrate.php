<?php
require_once 'includes/db.php';

$sqlFile = __DIR__ . '/sql/maintenance_schema.sql';
if (!file_exists($sqlFile)) {
    die("SQL file not found.");
}

$sql = file_get_contents($sqlFile);
try {
    $pdo->exec($sql);
    echo "Migration successful.\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
