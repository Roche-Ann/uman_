<?php
/**
 * Installer script for UPAD (Urban Planning) integration tables in UMAN.
 * Can be run via browser or CLI: php install_upad_integration.php
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/plain; charset=utf-8');
echo "Installing UMAN ↔ UPAD (Urban Planning) integration schema...\n";

$sqlFile = __DIR__ . '/sql/upad_integration.sql';
if (!file_exists($sqlFile)) {
    echo "❌ Error: SQL file not found at $sqlFile\n";
    exit(1);
}

$sql = file_get_contents($sqlFile);
try {
    $pdo->exec("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");
    $pdo->exec($sql);
    echo "✅ upad_inspection_requests table created or updated successfully.\n";
    echo "Done.\n";
} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
    exit(1);
}
