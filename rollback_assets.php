<?php
/**
 * rollback_assets.php
 * Reverses the asset inventory schema migration.
 * Restores the original `condition_status` column from the new `status` + `condition` columns.
 *
 * Run ONCE on the live server by visiting: https://uman.infragovservices.com/rollback_assets.php
 * Delete this file from the server after running it!
 */
require_once 'includes/db.php';

header('Content-Type: text/plain');
echo "Starting Asset Inventory Schema Rollback...\n\n";

try {
    // 1. Check if rollback is needed
    $colRows = $pdo->query("SHOW COLUMNS FROM `utility_assets`")->fetchAll(PDO::FETCH_ASSOC);
    $cols = array_column($colRows, 'Field');

    $hasCsCol  = in_array('condition_status', $cols);
    $hasStatus = in_array('status', $cols);

    if ($hasCsCol && !$hasStatus) {
        die("Rollback already completed — condition_status exists and status is gone.\n");
    }

    if (!$hasStatus) {
        die("Error: status column not found. Cannot continue rollback.\n");
    }

    // Step 1 — only add condition_status if it isn't already there
    if (!$hasCsCol) {
        echo "Step 1: Adding back condition_status column...\n";
        $pdo->exec("
            ALTER TABLE utility_assets
            ADD COLUMN condition_status ENUM('Operational','Needs Inspection','Damaged','Under Maintenance')
            NOT NULL DEFAULT 'Operational'
            AFTER date_installed
        ");
        echo "  OK\n";
    } else {
        echo "Step 1: condition_status already exists — skipping ADD COLUMN.\n";
    }

    echo "Step 2: Populating condition_status from status + condition...\n";
    $sql = 'UPDATE utility_assets SET condition_status =
        CASE
            WHEN status = \'Under Maintenance\' THEN \'Under Maintenance\'
            WHEN status IN (\'Non-Operational\',\'Retired\',\'Disposed\') THEN \'Damaged\'
            WHEN status = \'Operational\' AND `condition` IN (\'Poor\',\'Critical\') THEN \'Needs Inspection\'
            ELSE \'Operational\'
        END';
    $pdo->exec($sql);
    echo "  OK\n";

    echo "Step 3: Dropping new status and condition columns...\n";
    $pdo->exec("ALTER TABLE utility_assets DROP COLUMN status");
    $pdo->exec('ALTER TABLE utility_assets DROP COLUMN `condition`');
    echo "  OK\n";



    echo "Step 4: Dropping added columns (if they exist)...\n";
    $extraCols = ['serial_number','model_brand','primary_photo','warranty_doc','purchase_doc','inspection_doc'];
    foreach ($extraCols as $col) {
        try {
            $pdo->exec("ALTER TABLE utility_assets DROP COLUMN $col");
            echo "  $col dropped\n";
        } catch (Exception $e) {
            echo "  $col not found - skipping\n";
        }
    }

    echo "Step 5: Dropping asset_inspections and asset_audit_logs tables...\n";
    $pdo->exec("DROP TABLE IF EXISTS asset_audit_logs");
    $pdo->exec("DROP TABLE IF EXISTS asset_inspections");
    echo "  OK\n";

    echo "\nRollback completed successfully!\n";
    echo "Please delete this file from your server now.\n";

} catch (PDOException $e) {
    die("\nRollback failed: " . $e->getMessage() . "\n");
}
