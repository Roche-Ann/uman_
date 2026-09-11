<?php
// install_maintenance.php
require_once __DIR__ . '/includes/db.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `maintenance_requests` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `request_id` varchar(50) NOT NULL,
          `utility_asset_id` int(11) DEFAULT NULL,
          `title` varchar(255) NOT NULL,
          `maintenance_type` enum('Corrective','Preventive','Emergency','Routine') NOT NULL DEFAULT 'Corrective',
          `source` varchar(100) NOT NULL DEFAULT 'Asset Monitoring',
          `description` text DEFAULT NULL,
          `priority` enum('Low','Medium','High','Emergency') NOT NULL DEFAULT 'Medium',
          `assigned_to` varchar(150) DEFAULT NULL,
          `location` varchar(255) DEFAULT NULL,
          `status` enum('Reported','Scheduled','In Progress','On Hold','Testing','Completed','Unrepairable','Cancelled') NOT NULL DEFAULT 'Reported',
          `progress_percent` int(11) NOT NULL DEFAULT 0,
          `scheduled_date` date DEFAULT NULL,
          `started_at` datetime DEFAULT NULL,
          `completed_at` datetime DEFAULT NULL,
          `notes` text DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`),
          UNIQUE KEY `idx_request_id` (`request_id`),
          KEY `idx_asset_id` (`utility_asset_id`),
          KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `maintenance_status_logs` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `maintenance_request_id` int(11) NOT NULL,
          `old_status` varchar(50) DEFAULT NULL,
          `new_status` varchar(50) NOT NULL,
          `old_progress` int(11) NOT NULL DEFAULT 0,
          `new_progress` int(11) NOT NULL DEFAULT 0,
          `changed_by` int(11) NOT NULL DEFAULT 1,
          `notes` text DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`),
          KEY `idx_req_log` (`maintenance_request_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Self-heal: ensure auto_increment and primary key
    $repairTable = function($pdo, $tableName) {
        try {
            $col = $pdo->query("SHOW COLUMNS FROM `$tableName` LIKE 'id'")->fetch(PDO::FETCH_ASSOC);
            if ($col && stripos((string)($col['Extra'] ?? ''), 'auto_increment') === false) {
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                $pdo->exec("SET SESSION sql_mode = REPLACE(@@sql_mode, 'NO_AUTO_VALUE_ON_ZERO', '')");
                try {
                    $pdo->exec("ALTER TABLE `$tableName` ADD PRIMARY KEY (id)");
                } catch (Throwable $ignored) {}
                $pdo->exec("ALTER TABLE `$tableName` MODIFY id INT NOT NULL AUTO_INCREMENT");
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            }
        } catch (Throwable $e) {}
    };

    $repairTable($pdo, 'maintenance_requests');
    $repairTable($pdo, 'maintenance_status_logs');

    echo "Maintenance tables verified/created successfully.\n";
} catch (PDOException $e) {
    echo "Error initializing maintenance tables: " . $e->getMessage() . "\n";
}
