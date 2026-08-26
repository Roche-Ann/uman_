<?php
require_once 'includes/db.php';

header('Content-Type: text/plain');
echo "Starting Asset Inventory Database Migration...\n";

try {
    $pdo->beginTransaction();

    // 1. Add new columns to utility_assets
    echo "Adding new columns to utility_assets...\n";
    $pdo->exec("
        ALTER TABLE `utility_assets` 
        ADD COLUMN `status` ENUM('Operational', 'Under Maintenance', 'Non-Operational', 'Retired', 'Disposed') NOT NULL DEFAULT 'Operational' AFTER `date_installed`,
        ADD COLUMN `condition` ENUM('Excellent', 'Good', 'Fair', 'Poor', 'Critical') NOT NULL DEFAULT 'Good' AFTER `status`,
        ADD COLUMN `serial_number` VARCHAR(100) NULL AFTER `quantity`,
        ADD COLUMN `model_brand` VARCHAR(100) NULL AFTER `serial_number`,
        ADD COLUMN `primary_photo` VARCHAR(255) NULL AFTER `responsible_office`,
        ADD COLUMN `warranty_doc` VARCHAR(255) NULL AFTER `primary_photo`,
        ADD COLUMN `purchase_doc` VARCHAR(255) NULL AFTER `warranty_doc`,
        ADD COLUMN `inspection_doc` VARCHAR(255) NULL AFTER `purchase_doc`
    ");

    // 2. Migrate existing data
    echo "Migrating condition_status data to status and condition...\n";
    $pdo->exec("
        UPDATE `utility_assets` SET
        `status` = CASE 
            WHEN `condition_status` = 'Operational' THEN 'Operational'
            WHEN `condition_status` = 'Under Maintenance' THEN 'Under Maintenance'
            WHEN `condition_status` = 'Damaged' THEN 'Non-Operational'
            WHEN `condition_status` = 'Needs Inspection' THEN 'Operational'
            ELSE 'Operational'
        END,
        `condition` = CASE
            WHEN `condition_status` = 'Operational' THEN 'Good'
            WHEN `condition_status` = 'Needs Inspection' THEN 'Fair'
            WHEN `condition_status` = 'Damaged' THEN 'Critical'
            WHEN `condition_status` = 'Under Maintenance' THEN 'Poor'
            ELSE 'Good'
        END
    ");

    // 3. Drop old column
    echo "Dropping old condition_status column...\n";
    $pdo->exec("ALTER TABLE `utility_assets` DROP COLUMN `condition_status`");

    // 4. Create asset_inspections table
    echo "Creating asset_inspections table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `asset_inspections` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `utility_asset_id` INT NOT NULL,
          `inspection_date` DATE NOT NULL,
          `next_inspection_date` DATE NULL,
          `inspector_name` VARCHAR(100) NOT NULL,
          `findings` TEXT NULL,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (`utility_asset_id`) REFERENCES `utility_assets`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 5. Create general audit logs table
    echo "Creating asset_audit_logs table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `asset_audit_logs` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `utility_asset_id` INT NOT NULL,
          `user_id` INT NOT NULL,
          `action_type` VARCHAR(50) NOT NULL,
          `old_value` TEXT NULL,
          `new_value` TEXT NULL,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (`utility_asset_id`) REFERENCES `utility_assets`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Migrate old status logs to audit logs
    echo "Migrating old status logs to audit logs...\n";
    $pdo->exec("
        INSERT INTO `asset_audit_logs` (`utility_asset_id`, `user_id`, `action_type`, `old_value`, `new_value`, `created_at`)
        SELECT `utility_asset_id`, `changed_by`, 'Status Changed', `old_status`, `new_status`, `changed_at`
        FROM `asset_status_logs`
    ");
    
    $pdo->commit();
    echo "Migration completed successfully!\n";

} catch (PDOException $e) {
    $pdo->rollBack();
    die("Migration failed: " . $e->getMessage() . "\n");
}
