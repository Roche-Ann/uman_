<?php
/**
 * Install UMAN ↔ CPRF integration tables and equipment asset types.
 * Run once on UMAN server: php install_integration.php
 */
require_once 'includes/db.php';

header('Content-Type: text/plain');
echo "Installing UMAN integration schema...\n";

$sql = file_get_contents(__DIR__ . '/sql/utility_integration.sql');
$pdo->exec($sql);

echo "Done. Deploy api/assets.php and api/asset-requests.php for CPRF.\n";
echo "Set UMAN_INTEGRATION_API_KEY on server (default in integration_config.php: UMAN_SECURE_KEY_2025)\n";
