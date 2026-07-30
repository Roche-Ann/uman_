<?php
/**
 * Run once: creates the upad_inspection_requests table.
 * Access via browser: http://localhost/uman_/api/v1/setup-upad-table.php
 * DELETE or restrict this file after running.
 */
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
require_once dirname(__DIR__) . '/../includes/db.php';

$sql = file_get_contents(dirname(__DIR__) . '/../sql/upad_integration.sql');
try {
    $pdo->exec($sql);
    echo "✅ upad_inspection_requests table created (or already exists).\n";
    echo "ℹ️  You can now delete this file.\n";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
