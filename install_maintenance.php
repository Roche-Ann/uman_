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

    // Ensure all required columns exist in maintenance_requests
    try {
        $reqCols = $pdo->query("SHOW COLUMNS FROM `maintenance_requests`")->fetchAll(PDO::FETCH_COLUMN);
        $reqColsLower = array_map('strtolower', $reqCols);

        $colsToAdd = [
            'title'            => 'VARCHAR(255) NOT NULL DEFAULT "" AFTER `utility_asset_id`',
            'maintenance_type' => 'VARCHAR(50) NOT NULL DEFAULT "Corrective" AFTER `title`',
            'assigned_to'      => 'VARCHAR(150) NULL AFTER `priority`',
            'progress_percent' => 'INT(11) NOT NULL DEFAULT 0 AFTER `status`',
            'scheduled_date'   => 'DATE NULL AFTER `progress_percent`',
            'started_at'       => 'DATETIME NULL AFTER `scheduled_date`',
            'completed_at'     => 'DATETIME NULL AFTER `started_at`',
            'notes'            => 'TEXT NULL AFTER `completed_at`',
        ];

        foreach ($colsToAdd as $col => $def) {
            if (!in_array(strtolower($col), $reqColsLower)) {
                try {
                    $pdo->exec("ALTER TABLE `maintenance_requests` ADD COLUMN `$col` $def");
                } catch (Throwable $e) {}
            }
        }
    } catch (Throwable $e) {}

    // Ensure all required columns exist in maintenance_status_logs
    try {
        $logCols = $pdo->query("SHOW COLUMNS FROM `maintenance_status_logs`")->fetchAll(PDO::FETCH_COLUMN);
        $logColsLower = array_map('strtolower', $logCols);

        $logColsToAdd = [
            'old_progress' => 'INT(11) NOT NULL DEFAULT 0 AFTER `new_status`',
            'new_progress' => 'INT(11) NOT NULL DEFAULT 0 AFTER `old_progress`',
            'notes'        => 'TEXT NULL AFTER `changed_by`',
            'created_at'   => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
        ];

        foreach ($logColsToAdd as $col => $def) {
            if (!in_array(strtolower($col), $logColsLower)) {
                try {
                    $pdo->exec("ALTER TABLE `maintenance_status_logs` ADD COLUMN `$col` $def");
                } catch (Throwable $e) {}
            }
        }
    } catch (Throwable $e) {}

    // Relax ENUM restrictions on legacy tables
    try {
        $pdo->exec("ALTER TABLE `maintenance_requests` MODIFY COLUMN `source` VARCHAR(100) NOT NULL DEFAULT 'Asset Monitoring'");
    } catch (Throwable $e) {}
    try {
        $pdo->exec("ALTER TABLE `maintenance_requests` MODIFY COLUMN `status` VARCHAR(100) NOT NULL DEFAULT 'Reported'");
    } catch (Throwable $e) {}
    try {
        $pdo->exec("ALTER TABLE `maintenance_requests` MODIFY COLUMN `priority` VARCHAR(50) NOT NULL DEFAULT 'Medium'");
    } catch (Throwable $e) {}
    try {
        $pdo->exec("ALTER TABLE `maintenance_requests` MODIFY COLUMN `location` VARCHAR(255) NULL");
    } catch (Throwable $e) {}

    echo "<h3>Maintenance tables verified/created successfully.</h3>\n";

    // Auto-sync inventory assets
    $stmt = $pdo->query("
        SELECT a.id, a.asset_id, a.name, a.location, a.condition_status, a.description, a.quantity
        FROM utility_assets a
        WHERE LOWER(TRIM(REPLACE(a.condition_status, '_', ' '))) IN ('damaged', 'under maintenance')
          AND NOT EXISTS (
              SELECT 1 FROM maintenance_requests m 
              WHERE m.utility_asset_id = a.id 
                AND m.status NOT IN ('Completed', 'Unrepairable', 'Cancelled')
          )
    ");
    $orphans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p>Found " . count($orphans) . " asset(s) with Damaged or Under Maintenance condition needing work orders.</p>\n<ul>\n";

    $year = date('Y');
    foreach ($orphans as $asset) {
        $seqRow = $pdo->query("SELECT COUNT(*) FROM maintenance_requests WHERE request_id LIKE 'MNT-$year-%'")->fetchColumn();
        $seq = intval($seqRow) + 1;
        $reqId = sprintf("MNT-%s-%04d", $year, $seq);

        $normStatus = strtolower(trim(str_replace('_', ' ', $asset['condition_status'])));
        $isDamaged = ($normStatus === 'damaged');
        $status = $isDamaged ? 'Reported' : 'Scheduled';
        $priority = $isDamaged ? 'High' : 'Medium';
        $type = $isDamaged ? 'Emergency' : 'Corrective';
        $title = ($isDamaged ? "Repair Damaged Asset: " : "Maintenance Service: ") . $asset['name'] . " (" . $asset['asset_id'] . ")";
        $desc = !empty($asset['description']) ? $asset['description'] : "Asset flagged as {$asset['condition_status']} in Asset Inventory.";
        $loc = !empty($asset['location']) ? $asset['location'] : 'Main Facility';

        $ins = $pdo->prepare("
            INSERT INTO maintenance_requests 
                (request_id, utility_asset_id, title, maintenance_type, source, description, priority, location, status, progress_percent, created_at, updated_at)
            VALUES 
                (?, ?, ?, ?, 'Asset Monitoring', ?, ?, ?, ?, 0, NOW(), NOW())
        ");
        $ins->execute([$reqId, $asset['id'], $title, $type, $desc, $priority, $loc, $status]);
        $newId = (int)$pdo->lastInsertId();

        try {
            $pdo->prepare("
                INSERT INTO maintenance_status_logs (maintenance_request_id, old_status, new_status, old_progress, new_progress, changed_by, notes)
                VALUES (?, NULL, ?, 0, 0, 1, 'Auto-generated from Asset Inventory condition')
            ")->execute([$newId, $status]);
        } catch (Throwable $e) {}

        echo "<li>Created Work Order <strong>{$reqId}</strong> for <strong>{$asset['asset_id']}</strong> ({$asset['name']}) &rarr; Status: <strong>{$status}</strong></li>\n";
    }
    echo "</ul>\n";
    echo "<p><a href='maintenance_list.php' style='display:inline-block;padding:10px 18px;background:#3b82f6;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;'>Go to Maintenance Dashboard &rarr;</a></p>\n";
} catch (PDOException $e) {
    echo "<p style='color:red;'>Error initializing maintenance tables: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}
