<?php
// maintenance_list.php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

if (!function_exists('ensureMaintenanceSchema')) {
    function ensureMaintenanceSchema(): void {
        global $pdo;
        static $done = false;
        if ($done || !($pdo instanceof PDO)) {
            return;
        }
        
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

            // Self-repair AUTO_INCREMENT if missing
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

            // Relax ENUM restrictions on legacy tables to prevent truncation warnings
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
        } catch (Throwable $e) {}
        $done = true;
    }
}

if (!function_exists('syncOrphanedDamagedAssets')) {
    function syncOrphanedDamagedAssets($pdo, $userId = 1): array {
        $synced = [];
        try {
            $validUserId = intval($userId);
            if ($validUserId <= 0) $validUserId = 1;
            try {
                $chkUser = $pdo->prepare("SELECT id FROM users WHERE id = ?");
                $chkUser->execute([$validUserId]);
                if (!$chkUser->fetch()) {
                    $firstU = $pdo->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetchColumn();
                    if ($firstU) $validUserId = intval($firstU);
                }
            } catch (Throwable $e) {}

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
            if (!$orphans) return $synced;

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
                $desc = !empty($asset['description']) ? $asset['description'] : "Asset flagged as {$asset['condition_status']} in Asset Inventory. Auto-generated work order.";
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
                        VALUES (?, NULL, ?, 0, 0, ?, 'Auto-generated from Asset Inventory condition')
                    ")->execute([$newId, $status, $validUserId]);
                } catch (Throwable $e) {}

                $synced[] = ['request_id' => $reqId, 'asset_code' => $asset['asset_id'], 'status' => $status];
            }
        } catch (Throwable $e) {
            $_SESSION['sync_error'] = $e->getMessage();
        }
        return $synced;
    }
}

$userType = $_SESSION['user_type'] ?? '';
$userId   = intval($_SESSION['user_id'] ?? 1);
if ($userId <= 0) $userId = 1;

ensureMaintenanceSchema();
syncOrphanedDamagedAssets($pdo, $userId);

// Manual sync trigger (?sync=1)
if (isset($_GET['sync'])) {
    $res = syncOrphanedDamagedAssets($pdo, $userId);
    if (!empty($res)) {
        $_SESSION['flash_success'] = "Successfully synchronized " . count($res) . " asset(s) from Asset Inventory into Maintenance Work Orders!";
    } elseif (!empty($_SESSION['sync_error'])) {
        $_SESSION['flash_error'] = "Sync issue: " . $_SESSION['sync_error'];
        unset($_SESSION['sync_error']);
    } else {
        $_SESSION['flash_success'] = "All damaged and under-maintenance assets are already tracked in the Maintenance module.";
    }
    header('Location: maintenance_list.php');
    exit();
}

// Flash messages
$flashError   = $_SESSION['flash_error'] ?? '';
$flashSuccess = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

// -------------------------------------------------------------
// POST Request Handlers: Create, Update Status/Progress, Delete
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Create New Maintenance Request
    if ($action === 'create_ticket') {
        $assetId     = !empty($_POST['utility_asset_id']) ? intval($_POST['utility_asset_id']) : null;
        $title       = trim($_POST['title'] ?? '');
        $mType       = $_POST['maintenance_type'] ?? 'Corrective';
        $priority    = $_POST['priority'] ?? 'Medium';
        $assignedTo  = trim($_POST['assigned_to'] ?? '');
        $schedDate   = !empty($_POST['scheduled_date']) ? $_POST['scheduled_date'] : null;
        $location    = trim($_POST['location'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $notes       = trim($_POST['notes'] ?? '');

        if (empty($title)) {
            $_SESSION['flash_error'] = 'Please provide a title for the maintenance request.';
            header('Location: maintenance_list.php');
            exit();
        }

        try {
            // Generate sequence Request ID
            $year = date('Y');
            $seqRow = $pdo->query("SELECT COUNT(*) FROM maintenance_requests WHERE request_id LIKE 'MNT-$year-%'")->fetchColumn();
            $seq = intval($seqRow) + 1;
            $reqId = sprintf("MNT-%s-%04d", $year, $seq);

            // If location is blank and asset is chosen, fetch asset's location
            if (empty($location) && $assetId) {
                $aStmt = $pdo->prepare("SELECT location FROM utility_assets WHERE id = ?");
                $aStmt->execute([$assetId]);
                $location = (string)($aStmt->fetchColumn() ?: '');
            }

            $stmt = $pdo->prepare("
                INSERT INTO maintenance_requests 
                    (request_id, utility_asset_id, title, maintenance_type, source, description, priority, assigned_to, location, status, progress_percent, scheduled_date, notes, created_at, updated_at)
                VALUES 
                    (?, ?, ?, ?, 'Asset Monitoring', ?, ?, ?, ?, 'Reported', 0, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$reqId, $assetId, $title, $mType, $description, $priority, $assignedTo, $location, $schedDate, $notes]);
            $newMntId = (int)$pdo->lastInsertId();

            // Status log
            $pdo->prepare("
                INSERT INTO maintenance_status_logs (maintenance_request_id, old_status, new_status, old_progress, new_progress, changed_by, notes)
                VALUES (?, NULL, 'Reported', 0, 0, ?, 'Maintenance ticket created')
            ")->execute([$newMntId, $userId]);

            // Sync Asset condition to Under Maintenance if linked
            if ($assetId) {
                $pdo->prepare("UPDATE utility_assets SET condition_status = 'Under Maintenance' WHERE id = ? AND condition_status != 'Under Maintenance'")->execute([$assetId]);
                try {
                    $pdo->prepare("
                        INSERT INTO asset_status_logs (utility_asset_id, old_status, new_status, changed_by, notes)
                        VALUES (?, 'Operational', 'Under Maintenance', ?, ?)
                    ")->execute([$assetId, $userId, "Placed Under Maintenance via Work Order $reqId"]);
                } catch (Throwable $e) {}
            }

            $_SESSION['flash_success'] = "Maintenance ticket <strong>{$reqId}</strong> created successfully.";
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Failed to create ticket: " . $e->getMessage();
        }
        header('Location: maintenance_list.php');
        exit();
    }

    // 2. Update Status, Progress & Assignment
    if ($action === 'update_progress') {
        $id          = intval($_POST['ticket_id'] ?? 0);
        $newStatus   = $_POST['status'] ?? '';
        $progress    = min(100, max(0, intval($_POST['progress_percent'] ?? 0)));
        $assignedTo  = trim($_POST['assigned_to'] ?? '');
        $schedDate   = !empty($_POST['scheduled_date']) ? $_POST['scheduled_date'] : null;
        $statusNotes = trim($_POST['status_notes'] ?? '');

        if ($id <= 0 || empty($newStatus)) {
            $_SESSION['flash_error'] = 'Invalid update request.';
            header('Location: maintenance_list.php');
            exit();
        }

        try {
            // Fetch current ticket
            $tStmt = $pdo->prepare("SELECT * FROM maintenance_requests WHERE id = ?");
            $tStmt->execute([$id]);
            $ticket = $tStmt->fetch();

            if (!$ticket) {
                $_SESSION['flash_error'] = 'Ticket not found.';
                header('Location: maintenance_list.php');
                exit();
            }

            $oldStatus   = $ticket['status'];
            $oldProgress = (int)$ticket['progress_percent'];
            $assetId     = $ticket['utility_asset_id'];

            // Auto set progress based on status if not explicitly moved
            if ($newStatus === 'Completed' && $progress < 100) {
                $progress = 100;
            } elseif ($newStatus === 'Reported' && $oldStatus !== 'Reported' && $progress === $oldProgress) {
                $progress = 0;
            } elseif ($newStatus === 'In Progress' && $progress === 0) {
                $progress = 50;
            }

            $startedAt = $ticket['started_at'];
            if ($newStatus === 'In Progress' && empty($startedAt)) {
                $startedAt = date('Y-m-d H:i:s');
            }

            $completedAt = $ticket['completed_at'];
            if ($newStatus === 'Completed' && empty($completedAt)) {
                $completedAt = date('Y-m-d H:i:s');
            }

            // Update ticket
            $uStmt = $pdo->prepare("
                UPDATE maintenance_requests 
                SET status = ?, progress_percent = ?, assigned_to = ?, scheduled_date = ?, started_at = ?, completed_at = ?, notes = CONCAT(IFNULL(notes,''), '\n', ?), updated_at = NOW()
                WHERE id = ?
            ");
            $uStmt->execute([
                $newStatus,
                $progress,
                $assignedTo,
                $schedDate,
                $startedAt,
                $completedAt,
                !empty($statusNotes) ? "[" . date('M d, Y H:i') . "] " . $statusNotes : '',
                $id
            ]);

            // Log status change
            if ($oldStatus !== $newStatus || $oldProgress !== $progress || !empty($statusNotes)) {
                $pdo->prepare("
                    INSERT INTO maintenance_status_logs (maintenance_request_id, old_status, new_status, old_progress, new_progress, changed_by, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ")->execute([$id, $oldStatus, $newStatus, $oldProgress, $progress, $userId, $statusNotes ?: "Progress updated to {$progress}%"]);
            }

            // 2-Way Asset Synchronization
            if ($assetId) {
                if ($newStatus === 'Completed') {
                    // Check if asset is a child offshoot (e.g. SS-0001-M1)
                    $aCheck = $pdo->prepare("SELECT id, asset_id, parent_asset_id, quantity, name FROM utility_assets WHERE id = ?");
                    $aCheck->execute([$assetId]);
                    $assetRow = $aCheck->fetch(PDO::FETCH_ASSOC);

                    if ($assetRow && !empty($assetRow['parent_asset_id'])) {
                        $childQty = intval($assetRow['quantity']);
                        $parentId = intval($assetRow['parent_asset_id']);
                        $childCode = $assetRow['asset_id'];

                        // Merge quantity back into parent asset
                        $pdo->prepare("UPDATE utility_assets SET quantity = quantity + ? WHERE id = ?")
                            ->execute([$childQty, $parentId]);

                        $parentCodeStmt = $pdo->prepare("SELECT asset_id FROM utility_assets WHERE id = ?");
                        $parentCodeStmt->execute([$parentId]);
                        $parentCode = $parentCodeStmt->fetchColumn() ?: 'Parent';

                        try {
                            $pdo->prepare("
                                INSERT INTO asset_status_logs (utility_asset_id, action_type, old_status, new_status, changed_by, notes)
                                VALUES (?, 'split_merged', 'Under Maintenance', 'Operational (Merged)', ?, ?)
                            ")->execute([$parentId, $userId, "{$childQty} unit(s) of {$childCode} repaired via Work Order {$ticket['request_id']} and merged back into {$parentCode}."]);
                        } catch (Throwable $e) {}

                        try {
                            $pdo->prepare("INSERT INTO asset_notifications (type, message) VALUES (?, ?)")
                                ->execute(['status_changed', "Work Order {$ticket['request_id']}: {$childQty} unit(s) of {$childCode} repaired and merged back into {$parentCode}."]);
                        } catch (Throwable $e) {}

                        // Clean up child offshoot row
                        $pdo->prepare("DELETE FROM utility_assets WHERE id = ?")->execute([$assetId]);
                    } else {
                        // Regular asset restored to Operational
                        $pdo->prepare("UPDATE utility_assets SET condition_status = 'Operational' WHERE id = ?")->execute([$assetId]);
                        try {
                            $pdo->prepare("
                                INSERT INTO asset_status_logs (utility_asset_id, old_status, new_status, changed_by, notes)
                                VALUES (?, 'Under Maintenance', 'Operational', ?, ?)
                            ")->execute([$assetId, $userId, "Restored to Operational via Work Order {$ticket['request_id']}."]);
                        } catch (Throwable $e) {}
                        try {
                            $pdo->prepare("INSERT INTO asset_notifications (type, message) VALUES (?, ?)")
                                ->execute(['status_changed', "Work Order {$ticket['request_id']}: Asset restored to Operational status."]);
                        } catch (Throwable $e) {}
                    }
                } elseif ($newStatus === 'Unrepairable') {
                    $pdo->prepare("UPDATE utility_assets SET condition_status = 'Retired' WHERE id = ?")->execute([$assetId]);
                    try {
                        $pdo->prepare("
                            INSERT INTO asset_status_logs (utility_asset_id, old_status, new_status, changed_by, notes)
                            VALUES (?, 'Under Maintenance', 'Retired', ?, ?)
                        ")->execute([$assetId, $userId, "Decommissioned via Work Order {$ticket['request_id']} (Unrepairable)."]);
                    } catch (Throwable $e) {}
                } elseif (in_array($newStatus, ['Scheduled', 'In Progress', 'On Hold', 'Testing'])) {
                    $pdo->prepare("UPDATE utility_assets SET condition_status = 'Under Maintenance' WHERE id = ?")->execute([$assetId]);
                } elseif ($newStatus === 'Reported') {
                    $aCheck = $pdo->prepare("SELECT condition_status FROM utility_assets WHERE id = ?");
                    $aCheck->execute([$assetId]);
                    $currCond = $aCheck->fetchColumn();
                    if ($currCond !== 'Damaged') {
                        $pdo->prepare("UPDATE utility_assets SET condition_status = 'Under Maintenance' WHERE id = ?")->execute([$assetId]);
                    }
                }
            }

            $_SESSION['flash_success'] = "Work Order <strong>{$ticket['request_id']}</strong> updated to <strong>{$newStatus}</strong> ({$progress}%).";
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Failed to update ticket: " . $e->getMessage();
        }
        header('Location: maintenance_list.php');
        exit();
    }

    // 3. Delete / Archive Ticket
    if ($action === 'delete_ticket') {
        $id = intval($_POST['ticket_id'] ?? 0);
        if ($id > 0) {
            try {
                $pdo->prepare("DELETE FROM maintenance_status_logs WHERE maintenance_request_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM maintenance_requests WHERE id = ?")->execute([$id]);
                $_SESSION['flash_success'] = "Maintenance ticket deleted successfully.";
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = "Could not delete ticket: " . $e->getMessage();
            }
        }
        header('Location: maintenance_list.php');
        exit();
    }
}

// -------------------------------------------------------------
// Data Fetching & Aggregation
// -------------------------------------------------------------
$search       = trim($_GET['search'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$filterPri    = trim($_GET['priority'] ?? '');
$filterType   = trim($_GET['type'] ?? '');
$viewMode     = trim($_GET['view'] ?? 'kanban'); // 'kanban' or 'table'

// Base Query
$where = ["1=1"];
$params = [];

if ($search !== '') {
    $where[] = "(m.request_id LIKE ? OR m.title LIKE ? OR m.assigned_to LIKE ? OR m.location LIKE ? OR a.name LIKE ? OR a.asset_id LIKE ?)";
    $term = "%$search%";
    $params = array_merge($params, [$term, $term, $term, $term, $term, $term]);
}
if ($filterStatus !== '') {
    $where[] = "m.status = ?";
    $params[] = $filterStatus;
}
if ($filterPri !== '') {
    $where[] = "m.priority = ?";
    $params[] = $filterPri;
}
if ($filterType !== '') {
    $where[] = "m.maintenance_type = ?";
    $params[] = $filterType;
}

$whereClause = implode(" AND ", $where);

// Tickets list
$sql = "
    SELECT m.*, 
           a.asset_id as asset_code, 
           a.name as asset_name, 
           a.condition_status as asset_condition,
           t.name as asset_type_name
    FROM maintenance_requests m
    LEFT JOIN utility_assets a ON m.utility_asset_id = a.id
    LEFT JOIN asset_types t ON a.asset_type_id = t.id
    WHERE $whereClause
    ORDER BY 
        CASE m.priority 
            WHEN 'Emergency' THEN 1 
            WHEN 'High' THEN 2 
            WHEN 'Medium' THEN 3 
            WHEN 'Low' THEN 4 
            ELSE 5 
        END,
        m.created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Metrics Counts
$totalCount      = (int)$pdo->query("SELECT COUNT(*) FROM maintenance_requests")->fetchColumn();
$reportedCount   = (int)$pdo->query("SELECT COUNT(*) FROM maintenance_requests WHERE status = 'Reported'")->fetchColumn();
$scheduledCount  = (int)$pdo->query("SELECT COUNT(*) FROM maintenance_requests WHERE status = 'Scheduled'")->fetchColumn();
$inProgressCount = (int)$pdo->query("SELECT COUNT(*) FROM maintenance_requests WHERE status = 'In Progress'")->fetchColumn();
$testingCount    = (int)$pdo->query("SELECT COUNT(*) FROM maintenance_requests WHERE status = 'Testing'")->fetchColumn();
$completedCount  = (int)$pdo->query("SELECT COUNT(*) FROM maintenance_requests WHERE status = 'Completed'")->fetchColumn();
$onHoldCount     = (int)$pdo->query("SELECT COUNT(*) FROM maintenance_requests WHERE status = 'On Hold'")->fetchColumn();
$activeCount     = (int)$pdo->query("SELECT COUNT(*) FROM maintenance_requests WHERE status NOT IN ('Completed', 'Unrepairable', 'Cancelled')")->fetchColumn();

// Fetch assets for dropdown in New Ticket modal
$assetsList = $pdo->query("
    SELECT a.id, a.asset_id, a.name, a.location, a.condition_status, t.name as type_name
    FROM utility_assets a
    LEFT JOIN asset_types t ON a.asset_type_id = t.id
    ORDER BY a.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Kanban Columns Mapping
$kanbanColumns = [
    'Reported'    => ['title' => 'Reported', 'icon' => 'fa-exclamation-circle', 'color' => '#eab308', 'bg' => 'rgba(234, 179, 8, 0.12)'],
    'Scheduled'   => ['title' => 'Scheduled', 'icon' => 'fa-calendar-alt', 'color' => '#0284c7', 'bg' => 'rgba(2, 132, 199, 0.12)'],
    'In Progress' => ['title' => 'In Progress', 'icon' => 'fa-tools', 'color' => '#3b82f6', 'bg' => 'rgba(59, 130, 246, 0.12)'],
    'On Hold'     => ['title' => 'On Hold', 'icon' => 'fa-pause-circle', 'color' => '#f97316', 'bg' => 'rgba(249, 115, 22, 0.12)'],
    'Testing'     => ['title' => 'Testing', 'icon' => 'fa-vial', 'color' => '#8b5cf6', 'bg' => 'rgba(139, 92, 246, 0.12)'],
    'Completed'   => ['title' => 'Completed', 'icon' => 'fa-check-circle', 'color' => '#10b981', 'bg' => 'rgba(16, 185, 129, 0.12)']
];

// Group tickets by status for kanban
$kanbanTickets = [];
foreach (array_keys($kanbanColumns) as $kStatus) {
    $kanbanTickets[$kStatus] = [];
}
foreach ($tickets as $t) {
    if (isset($kanbanTickets[$t['status']])) {
        $kanbanTickets[$t['status']][] = $t;
    }
}

// Helpers
function getPriorityBadgeClass($priority) {
    switch ($priority) {
        case 'Emergency': return 'badge-priority-emergency';
        case 'High': return 'badge-priority-high';
        case 'Medium': return 'badge-priority-medium';
        case 'Low': return 'badge-priority-low';
        default: return 'badge-priority-medium';
    }
}

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'Reported': return 'status-reported';
        case 'Scheduled': return 'status-scheduled';
        case 'In Progress': return 'status-inprogress';
        case 'On Hold': return 'status-onhold';
        case 'Testing': return 'status-testing';
        case 'Completed': return 'status-completed';
        case 'Unrepairable': return 'status-unrepairable';
        case 'Cancelled': return 'status-cancelled';
        default: return 'status-reported';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Maintenance Management | UMAN</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-page: #f8fafc;
            --bg-card: rgba(255, 255, 255, 0.9);
            --bg-card-hover: #ffffff;
            --border-color: rgba(226, 232, 240, 0.8);
            --text-primary: #0f172a;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --primary: #3762c8;
            --primary-hover: #2851b0;
            --primary-light: rgba(55, 98, 200, 0.1);
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 14px rgba(0,0,0,0.07);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
            --radius-md: 12px;
            --radius-lg: 16px;
        }

        .dark-theme {
            --bg-page: #0b0f19;
            --bg-card: rgba(18, 24, 38, 0.85);
            --bg-card-hover: rgba(26, 34, 52, 0.95);
            --border-color: rgba(255, 255, 255, 0.08);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --primary: #4d7bf3;
            --primary-hover: #3762c8;
            --primary-light: rgba(77, 123, 243, 0.15);
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 14px rgba(0,0,0,0.4);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.5);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background-color: var(--bg-page);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .main-wrapper {
            flex: 1;
            margin-left: 280px;
            padding: 90px 28px 40px 28px;
            min-height: 100vh;
            transition: margin-left 0.25s ease;
        }

        .sidebar-nav.collapsed ~ .main-wrapper,
        .main-wrapper.expanded {
            margin-left: 78px;
        }

        @media (max-width: 1024px) {
            .main-wrapper {
                margin-left: 0 !important;
                padding: 85px 16px 30px 16px;
            }
        }

        /* Top Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .page-title-group h1 {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-title-group h1 i {
            color: var(--primary);
            background: var(--primary-light);
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .page-title-group p {
            font-size: 13.5px;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 4px 12px rgba(55, 98, 200, 0.3);
        }

        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(55, 98, 200, 0.4);
        }

        .btn-outline {
            background: var(--bg-card);
            color: var(--text-primary);
            border-color: var(--border-color);
        }

        .btn-outline:hover {
            background: var(--bg-card-hover);
            border-color: var(--primary);
            color: var(--primary);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 8px;
        }

        /* Metrics Grid */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .metric-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: var(--shadow-sm);
            backdrop-filter: blur(12px);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .metric-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .metric-info .metric-value {
            font-size: 22px;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.1;
        }

        .metric-info .metric-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-top: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Filter and View Controls Bar */
        .controls-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            box-shadow: var(--shadow-sm);
        }

        .filters-form {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            flex: 1;
        }

        .search-box {
            position: relative;
            min-width: 220px;
            flex: 1;
            max-width: 320px;
        }

        .search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 14px;
        }

        .search-box input {
            width: 100%;
            padding: 9px 14px 9px 38px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background: var(--bg-page);
            color: var(--text-primary);
            font-size: 13px;
            outline: none;
            transition: border-color 0.2s;
        }

        .search-box input:focus {
            border-color: var(--primary);
        }

        .filter-select {
            padding: 9px 14px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background: var(--bg-page);
            color: var(--text-primary);
            font-size: 13px;
            outline: none;
            cursor: pointer;
        }

        .filter-select:focus {
            border-color: var(--primary);
        }

        .view-switcher {
            display: flex;
            background: var(--bg-page);
            padding: 4px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            gap: 4px;
        }

        .view-btn {
            padding: 7px 14px;
            border-radius: 8px;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            font-size: 12.5px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }

        .view-btn.active {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 2px 8px rgba(55, 98, 200, 0.3);
        }

        /* Kanban Board Styles */
        .kanban-board {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 18px;
            align-items: start;
            overflow-x: auto;
            padding-bottom: 20px;
        }

        @media (min-width: 1600px) {
            .kanban-board {
                grid-template-columns: repeat(6, 1fr);
            }
        }

        .kanban-col {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 16px;
            min-height: 480px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            box-shadow: var(--shadow-sm);
        }

        .kanban-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
        }

        .kanban-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .kanban-count {
            font-size: 11px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 99px;
            background: var(--bg-page);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }

        .kanban-cards-wrapper {
            display: flex;
            flex-direction: column;
            gap: 12px;
            flex: 1;
        }

        .kanban-card {
            background: var(--bg-card-hover);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 14px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            box-shadow: var(--shadow-sm);
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
            cursor: pointer;
            position: relative;
        }

        .kanban-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary);
        }

        .kanban-card-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11.5px;
        }

        .card-req-id {
            font-weight: 800;
            color: var(--primary);
            letter-spacing: 0.3px;
        }

        .card-priority {
            font-size: 10.5px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 6px;
            text-transform: uppercase;
        }

        .badge-priority-emergency { background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
        .badge-priority-high { background: rgba(249, 115, 22, 0.15); color: #f97316; border: 1px solid rgba(249, 115, 22, 0.3); }
        .badge-priority-medium { background: rgba(234, 179, 8, 0.15); color: #eab308; border: 1px solid rgba(234, 179, 8, 0.3); }
        .badge-priority-low { background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); }

        .card-title {
            font-size: 13.5px;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.3;
        }

        .card-asset-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11.5px;
            color: var(--text-secondary);
            background: var(--bg-page);
            padding: 4px 8px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            width: fit-content;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .card-progress-box {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .card-progress-labels {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: var(--text-secondary);
            font-weight: 600;
        }

        .progress-bar-bg {
            width: 100%;
            height: 6px;
            background: var(--bg-page);
            border-radius: 99px;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6, #10b981);
            border-radius: 99px;
            transition: width 0.3s ease;
        }

        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11.5px;
            color: var(--text-muted);
            padding-top: 6px;
            border-top: 1px dashed var(--border-color);
        }

        .card-assignee {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--text-secondary);
            font-weight: 600;
        }

        .card-empty-placeholder {
            text-align: center;
            padding: 30px 10px;
            color: var(--text-muted);
            font-size: 12.5px;
            border: 2px dashed var(--border-color);
            border-radius: var(--radius-md);
        }

        /* Table View */
        .table-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        .custom-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 13px;
        }

        .custom-table th {
            background: var(--bg-page);
            color: var(--text-secondary);
            font-weight: 700;
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-color);
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.6px;
        }

        .custom-table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }

        .custom-table tr:hover td {
            background: var(--bg-card-hover);
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 99px;
            font-size: 11px;
            font-weight: 700;
        }

        .status-reported { background: rgba(234, 179, 8, 0.15); color: #d97706; }
        .status-scheduled { background: rgba(2, 132, 199, 0.15); color: #0284c7; }
        .status-inprogress { background: rgba(59, 130, 246, 0.15); color: #2563eb; }
        .status-onhold { background: rgba(249, 115, 22, 0.15); color: #ea580c; }
        .status-testing { background: rgba(139, 92, 246, 0.15); color: #7c3aed; }
        .status-completed { background: rgba(16, 185, 129, 0.15); color: #059669; }
        .status-unrepairable { background: rgba(239, 68, 68, 0.15); color: #dc2626; }
        .status-cancelled { background: rgba(148, 163, 184, 0.15); color: #64748b; }

        /* Modal Styles */
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(6px);
            z-index: 10000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-backdrop.open {
            display: flex;
        }

        .modal-dialog {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 620px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
            animation: modalPop 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes modalPop {
            0% { transform: scale(0.92); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        .modal-header {
            padding: 18px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            font-size: 17px;
            font-weight: 800;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-close-btn {
            background: transparent;
            border: none;
            font-size: 18px;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 4px;
        }

        .modal-body {
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group label {
            font-size: 12.5px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .form-control {
            padding: 10px 14px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background: var(--bg-page);
            color: var(--text-primary);
            font-size: 13.5px;
            outline: none;
            transition: border-color 0.2s;
            width: 100%;
        }

        .form-control:focus {
            border-color: var(--primary);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        @media (max-width: 540px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        /* Range Slider */
        .range-slider-wrapper {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .range-slider {
            flex: 1;
            accent-color: var(--primary);
            cursor: pointer;
        }

        .range-slider-value {
            font-size: 14px;
            font-weight: 800;
            color: var(--primary);
            min-width: 45px;
            text-align: right;
        }

        /* Timeline list inside detail modal */
        .activity-timeline {
            display: flex;
            flex-direction: column;
            gap: 12px;
            position: relative;
            padding-left: 18px;
            border-left: 2px solid var(--border-color);
            margin-top: 10px;
        }

        .timeline-item {
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .timeline-dot {
            position: absolute;
            left: -24px;
            top: 4px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--primary);
            border: 2px solid var(--bg-card);
        }

        .timeline-title {
            font-size: 12.5px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .timeline-meta {
            font-size: 11px;
            color: var(--text-muted);
        }

        .timeline-notes {
            font-size: 12px;
            color: var(--text-secondary);
            background: var(--bg-page);
            padding: 6px 10px;
            border-radius: 8px;
            margin-top: 2px;
        }

        /* Alert notifications */
        .alert-box {
            padding: 12px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13.5px;
            font-weight: 600;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #059669;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #dc2626;
        }
    </style>
</head>
<body>

    <!-- Sidebar Navigation -->
    <?php include_once 'includes/utilities_sidebar.php'; ?>

    <main class="main-wrapper" id="mainWrapper">
        
        <!-- Flash Alerts -->
        <?php if ($flashSuccess): ?>
            <div class="alert-box alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $flashSuccess; ?></span>
            </div>
        <?php endif; ?>

        <?php if ($flashError): ?>
            <div class="alert-box alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <span><?php echo $flashError; ?></span>
            </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title-group">
                <h1>
                    <i class="fas fa-wrench"></i>
                    Asset Maintenance Management
                </h1>
                <p>Track, manage, and synchronize maintenance lifecycles directly with the Asset Inventory.</p>
            </div>
            <div class="header-actions">
                <a href="maintenance_list.php?sync=1" class="btn btn-secondary" style="display:inline-flex;align-items:center;gap:8px;background:rgba(59,130,246,0.12);color:#3b82f6;border:1px solid rgba(59,130,246,0.3);padding:10px 18px;border-radius:10px;text-decoration:none;font-weight:600;font-size:13.5px;" title="Scan and pull any Damaged or Under Maintenance items from Asset Inventory">
                    <i class="fas fa-sync-alt"></i> Sync from Inventory
                </a>
                <button type="button" class="btn btn-primary" onclick="openNewTicketModal()">
                    <i class="fas fa-plus"></i>
                    New Maintenance Ticket
                </button>
            </div>
        </div>

        <!-- Metrics Overview Cards -->
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-icon" style="background: rgba(59, 130, 246, 0.12); color: #3b82f6;">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="metric-info">
                    <div class="metric-value"><?php echo $totalCount; ?></div>
                    <div class="metric-label">Total Work Orders</div>
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-icon" style="background: rgba(234, 179, 8, 0.12); color: #eab308;">
                    <i class="fas fa-bell"></i>
                </div>
                <div class="metric-info">
                    <div class="metric-value"><?php echo $reportedCount; ?></div>
                    <div class="metric-label">Reported / Pending</div>
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-icon" style="background: rgba(59, 130, 246, 0.12); color: #3b82f6;">
                    <i class="fas fa-spinner fa-spin"></i>
                </div>
                <div class="metric-info">
                    <div class="metric-value"><?php echo $inProgressCount; ?></div>
                    <div class="metric-label">In Progress</div>
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-icon" style="background: rgba(139, 92, 246, 0.12); color: #8b5cf6;">
                    <i class="fas fa-vial"></i>
                </div>
                <div class="metric-info">
                    <div class="metric-value"><?php echo $testingCount; ?></div>
                    <div class="metric-label">Testing / QA</div>
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-icon" style="background: rgba(16, 185, 129, 0.12); color: #10b981;">
                    <i class="fas fa-check-double"></i>
                </div>
                <div class="metric-info">
                    <div class="metric-value"><?php echo $completedCount; ?></div>
                    <div class="metric-label">Completed & Restored</div>
                </div>
            </div>
        </div>

        <!-- Controls: Search, Filter, View Switcher -->
        <div class="controls-card">
            <form method="GET" action="maintenance_list.php" class="filters-form" id="filtersForm">
                <input type="hidden" name="view" value="<?php echo htmlspecialchars($viewMode); ?>">
                
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search ticket, asset, crew..." value="<?php echo htmlspecialchars($search); ?>">
                </div>

                <select name="priority" class="filter-select" onchange="this.form.submit()">
                    <option value="">All Priorities</option>
                    <option value="Emergency" <?php echo $filterPri === 'Emergency' ? 'selected' : ''; ?>>Emergency</option>
                    <option value="High" <?php echo $filterPri === 'High' ? 'selected' : ''; ?>>High</option>
                    <option value="Medium" <?php echo $filterPri === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                    <option value="Low" <?php echo $filterPri === 'Low' ? 'selected' : ''; ?>>Low</option>
                </select>

                <select name="type" class="filter-select" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <option value="Corrective" <?php echo $filterType === 'Corrective' ? 'selected' : ''; ?>>Corrective</option>
                    <option value="Preventive" <?php echo $filterType === 'Preventive' ? 'selected' : ''; ?>>Preventive</option>
                    <option value="Emergency" <?php echo $filterType === 'Emergency' ? 'selected' : ''; ?>>Emergency</option>
                    <option value="Routine" <?php echo $filterType === 'Routine' ? 'selected' : ''; ?>>Routine</option>
                </select>

                <?php if (!empty($search) || !empty($filterPri) || !empty($filterType)): ?>
                    <a href="maintenance_list.php?view=<?php echo htmlspecialchars($viewMode); ?>" class="btn btn-outline btn-sm">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                <?php endif; ?>
            </form>

            <div class="view-switcher">
                <a href="maintenance_list.php?view=kanban<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($filterPri) ? '&priority='.urlencode($filterPri) : ''; ?><?php echo !empty($filterType) ? '&type='.urlencode($filterType) : ''; ?>" 
                   class="view-btn <?php echo $viewMode === 'kanban' ? 'active' : ''; ?>">
                    <i class="fas fa-columns"></i> Kanban
                </a>
                <a href="maintenance_list.php?view=table<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($filterPri) ? '&priority='.urlencode($filterPri) : ''; ?><?php echo !empty($filterType) ? '&type='.urlencode($filterType) : ''; ?>" 
                   class="view-btn <?php echo $viewMode === 'table' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i> Table
                </a>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- VIEW MODE 1: KANBAN BOARD                     -->
        <!-- ============================================== -->
        <?php if ($viewMode === 'kanban'): ?>
            <div class="kanban-board">
                <?php foreach ($kanbanColumns as $statusKey => $colMeta): 
                    $colItems = $kanbanTickets[$statusKey] ?? [];
                ?>
                    <div class="kanban-col">
                        <div class="kanban-header">
                            <div class="kanban-title">
                                <i class="fas <?php echo $colMeta['icon']; ?>" style="color: <?php echo $colMeta['color']; ?>;"></i>
                                <?php echo $colMeta['title']; ?>
                            </div>
                            <span class="kanban-count"><?php echo count($colItems); ?></span>
                        </div>

                        <div class="kanban-cards-wrapper">
                            <?php if (empty($colItems)): ?>
                                <div class="card-empty-placeholder">No tickets in this stage</div>
                            <?php else: ?>
                                <?php foreach ($colItems as $item): ?>
                                    <div class="kanban-card" onclick="openUpdateModal(<?php echo htmlspecialchars(json_encode($item)); ?>)">
                                        <div class="kanban-card-top">
                                            <span class="card-req-id"><?php echo htmlspecialchars($item['request_id']); ?></span>
                                            <span class="card-priority <?php echo getPriorityBadgeClass($item['priority']); ?>">
                                                <?php echo htmlspecialchars($item['priority']); ?>
                                            </span>
                                        </div>

                                        <div class="card-title"><?php echo htmlspecialchars($item['title'] ?? 'Maintenance Work Order'); ?></div>

                                        <?php if (!empty($item['asset_name'])): ?>
                                            <div class="card-asset-tag" title="<?php echo htmlspecialchars($item['asset_name']); ?>">
                                                <i class="fas fa-cube"></i>
                                                <span><?php echo htmlspecialchars(($item['asset_code'] ?? '') ? $item['asset_code'] . ' - ' : '') . htmlspecialchars($item['asset_name']); ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <div class="card-progress-box">
                                            <div class="card-progress-labels">
                                                <span>Progress</span>
                                                <span><?php echo intval($item['progress_percent'] ?? 0); ?>%</span>
                                            </div>
                                            <div class="progress-bar-bg">
                                                <div class="progress-bar-fill" style="width: <?php echo intval($item['progress_percent'] ?? 0); ?>%;"></div>
                                            </div>
                                        </div>

                                        <div class="card-footer">
                                            <div class="card-assignee">
                                                <i class="fas fa-user-circle"></i>
                                                <span><?php echo htmlspecialchars(($item['assigned_to'] ?? '') ?: 'Unassigned'); ?></span>
                                            </div>
                                            <div>
                                                <i class="far fa-clock"></i>
                                                <span><?php echo date('M d', strtotime($item['created_at'] ?? 'now')); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <!-- ============================================== -->
        <!-- VIEW MODE 2: TABULAR LIST                      -->
        <!-- ============================================== -->
        <?php else: ?>
            <div class="table-card">
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Work Order</th>
                                <th>Asset Information</th>
                                <th>Title & Type</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Progress</th>
                                <th>Assigned To</th>
                                <th>Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($tickets)): ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                        <i class="fas fa-inbox" style="font-size: 32px; margin-bottom: 8px; display: block;"></i>
                                        No maintenance work orders found matching your criteria.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($tickets as $t): ?>
                                    <tr>
                                        <td>
                                            <strong style="color: var(--primary);"><?php echo htmlspecialchars($t['request_id']); ?></strong>
                                        </td>
                                        <td>
                                            <?php if (!empty($t['asset_name'])): ?>
                                                <div style="font-weight: 700; color: var(--text-primary);"><?php echo htmlspecialchars($t['asset_name']); ?></div>
                                                <div style="font-size: 11px; color: var(--text-muted);"><?php echo htmlspecialchars($t['asset_code']); ?> &bull; <?php echo htmlspecialchars($t['asset_type_name'] ?? 'Asset'); ?></div>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted);">Unlinked</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600;"><?php echo htmlspecialchars($t['title'] ?? 'Maintenance Work Order'); ?></div>
                                            <div style="font-size: 11px; color: var(--text-muted);"><?php echo htmlspecialchars($t['maintenance_type'] ?? 'Corrective'); ?></div>
                                        </td>
                                        <td>
                                            <span class="card-priority <?php echo getPriorityBadgeClass($t['priority'] ?? 'Medium'); ?>">
                                                <?php echo htmlspecialchars($t['priority'] ?? 'Medium'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-pill <?php echo getStatusBadgeClass($t['status'] ?? 'Reported'); ?>">
                                                <?php echo htmlspecialchars($t['status'] ?? 'Reported'); ?>
                                            </span>
                                        </td>
                                        <td style="min-width: 120px;">
                                            <div class="card-progress-labels" style="margin-bottom: 3px;">
                                                <span style="font-size: 11px;"><?php echo intval($t['progress_percent'] ?? 0); ?>%</span>
                                            </div>
                                            <div class="progress-bar-bg">
                                                <div class="progress-bar-fill" style="width: <?php echo intval($t['progress_percent'] ?? 0); ?>%;"></div>
                                            </div>
                                        </td>
                                        <td>
                                            <span style="font-weight: 500;"><?php echo htmlspecialchars(($t['assigned_to'] ?? '') ?: 'Unassigned'); ?></span>
                                        </td>
                                        <td style="font-size: 11.5px; color: var(--text-muted);">
                                            <?php echo date('M d, Y', strtotime($t['created_at'] ?? 'now')); ?>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-outline btn-sm" onclick="openUpdateModal(<?php echo htmlspecialchars(json_encode($t)); ?>)">
                                                <i class="fas fa-edit"></i> Update
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <!-- ============================================== -->
    <!-- MODAL 1: CREATE NEW MAINTENANCE TICKET         -->
    <!-- ============================================== -->
    <div class="modal-backdrop" id="modalNewTicket">
        <div class="modal-dialog">
            <form method="POST" action="maintenance_list.php">
                <input type="hidden" name="action" value="create_ticket">

                <div class="modal-header">
                    <h3><i class="fas fa-plus-circle" style="color: var(--primary);"></i> Create Maintenance Ticket</h3>
                    <button type="button" class="modal-close-btn" onclick="closeModals()">&times;</button>
                </div>

                <div class="modal-body">
                    <div class="form-group">
                        <label for="new_utility_asset_id">Select Asset from Inventory (Optional)</label>
                        <select name="utility_asset_id" id="new_utility_asset_id" class="form-control" onchange="autofillAssetDetails(this)">
                            <option value="">-- General / No specific asset --</option>
                            <?php foreach ($assetsList as $ast): ?>
                                <option value="<?php echo $ast['id']; ?>" data-location="<?php echo htmlspecialchars($ast['location']); ?>" data-name="<?php echo htmlspecialchars($ast['name']); ?>">
                                    <?php echo htmlspecialchars($ast['asset_id']); ?> - <?php echo htmlspecialchars($ast['name']); ?> (<?php echo htmlspecialchars($ast['condition_status']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="new_title">Work Order Title <span style="color: #ef4444;">*</span></label>
                        <input type="text" name="title" id="new_title" class="form-control" placeholder="e.g. Pump Valve Overhaul & Diagnostic" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_maintenance_type">Maintenance Type</label>
                            <select name="maintenance_type" id="new_maintenance_type" class="form-control">
                                <option value="Corrective">Corrective (Fix Damage)</option>
                                <option value="Preventive">Preventive</option>
                                <option value="Emergency">Emergency Response</option>
                                <option value="Routine">Routine Inspection</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="new_priority">Priority Level</label>
                            <select name="priority" id="new_priority" class="form-control">
                                <option value="Low">Low</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="High">High</option>
                                <option value="Emergency">Emergency</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_assigned_to">Assign Technician / Crew</label>
                            <input type="text" name="assigned_to" id="new_assigned_to" class="form-control" placeholder="e.g. Team Alpha / Engr. Santos">
                        </div>

                        <div class="form-group">
                            <label for="new_scheduled_date">Target Scheduled Date</label>
                            <input type="date" name="scheduled_date" id="new_scheduled_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="new_location">Location / Area</label>
                        <input type="text" name="location" id="new_location" class="form-control" placeholder="e.g. Substation 4, Zone 12">
                    </div>

                    <div class="form-group">
                        <label for="new_description">Work Description / Diagnostics</label>
                        <textarea name="description" id="new_description" class="form-control" rows="3" placeholder="Describe the fault, symptoms, or maintenance instructions..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModals()">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create Work Order</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================== -->
    <!-- MODAL 2: UPDATE STATUS, PROGRESS & TIMELINE    -->
    <!-- ============================================== -->
    <div class="modal-backdrop" id="modalUpdateTicket">
        <div class="modal-dialog">
            <form method="POST" action="maintenance_list.php">
                <input type="hidden" name="action" value="update_progress">
                <input type="hidden" name="ticket_id" id="upd_ticket_id">

                <div class="modal-header">
                    <h3>
                        <i class="fas fa-tasks" style="color: var(--primary);"></i>
                        <span id="upd_req_id_badge">MNT-XXXX</span>
                    </h3>
                    <button type="button" class="modal-close-btn" onclick="closeModals()">&times;</button>
                </div>

                <div class="modal-body">
                    <div style="background: var(--bg-page); padding: 12px 16px; border-radius: 10px; border: 1px solid var(--border-color);">
                        <h4 id="upd_title" style="font-size: 15px; font-weight: 700; color: var(--text-primary); margin-bottom: 4px;"></h4>
                        <div id="upd_asset_info" style="font-size: 12px; color: var(--text-secondary);"></div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="upd_status">Maintenance Status <span style="color: #ef4444;">*</span></label>
                            <select name="status" id="upd_status" class="form-control" onchange="onStatusSelectChange(this.value)">
                                <option value="Reported">Reported / Pending</option>
                                <option value="Scheduled">Scheduled</option>
                                <option value="In Progress">In Progress / Under Repair</option>
                                <option value="On Hold">On Hold / Paused</option>
                                <option value="Testing">Testing / Quality Inspection</option>
                                <option value="Completed">Completed & Restored (Auto-Operational)</option>
                                <option value="Unrepairable">Unrepairable (Auto-Decommission)</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="upd_assigned_to">Assigned Crew / Technician</label>
                            <input type="text" name="assigned_to" id="upd_assigned_to" class="form-control" placeholder="Technician name">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Completion Progress (<span id="progressValueDisplay">0%</span>)</label>
                        <div class="range-slider-wrapper">
                            <input type="range" name="progress_percent" id="upd_progress_slider" min="0" max="100" value="0" class="range-slider" oninput="updateSliderLabel(this.value)">
                            <span class="range-slider-value" id="sliderNumVal">0%</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="upd_scheduled_date">Target Completion Date</label>
                        <input type="date" name="scheduled_date" id="upd_scheduled_date" class="form-control">
                    </div>

                    <div class="form-group">
                        <label for="upd_status_notes">Work Log / Progress Notes</label>
                        <textarea name="status_notes" id="upd_status_notes" class="form-control" rows="2" placeholder="Detail actions performed during this update..."></textarea>
                    </div>

                    <!-- Delete Trigger -->
                    <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 10px; border-top: 1px dashed var(--border-color);">
                        <span style="font-size: 11.5px; color: var(--text-muted);">Need to cancel or remove this ticket?</span>
                        <button type="button" class="btn btn-sm" style="color: #ef4444; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2);" onclick="submitDeleteTicket()">
                            <i class="fas fa-trash"></i> Delete Ticket
                        </button>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModals()">Close</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save & Synchronize</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Hidden Delete Form -->
    <form id="deleteTicketForm" method="POST" action="maintenance_list.php" style="display: none;">
        <input type="hidden" name="action" value="delete_ticket">
        <input type="hidden" name="ticket_id" id="delete_ticket_id">
    </form>

    <script>
        function openNewTicketModal() {
            document.getElementById('modalNewTicket').classList.add('open');
        }

        function openUpdateModal(ticket) {
            document.getElementById('upd_ticket_id').value = ticket.id;
            document.getElementById('delete_ticket_id').value = ticket.id;
            document.getElementById('upd_req_id_badge').innerText = ticket.request_id;
            document.getElementById('upd_title').innerText = ticket.title;
            
            const assetInfo = ticket.asset_name ? `Asset: ${ticket.asset_name} (${ticket.asset_code || 'No Code'}) • Condition: ${ticket.asset_condition || 'N/A'}` : 'General Maintenance (Unlinked Asset)';
            document.getElementById('upd_asset_info').innerText = assetInfo;
            
            document.getElementById('upd_status').value = ticket.status;
            document.getElementById('upd_assigned_to').value = ticket.assigned_to || '';
            document.getElementById('upd_scheduled_date').value = ticket.scheduled_date || '';
            
            const prog = parseInt(ticket.progress_percent) || 0;
            document.getElementById('upd_progress_slider').value = prog;
            updateSliderLabel(prog);
            
            document.getElementById('upd_status_notes').value = '';
            document.getElementById('modalUpdateTicket').classList.add('open');
        }

        function updateSliderLabel(val) {
            document.getElementById('progressValueDisplay').innerText = val + '%';
            document.getElementById('sliderNumVal').innerText = val + '%';
        }

        function onStatusSelectChange(status) {
            const slider = document.getElementById('upd_progress_slider');
            if (status === 'Completed') {
                slider.value = 100;
            } else if (status === 'Reported' && parseInt(slider.value) > 20) {
                slider.value = 0;
            } else if (status === 'In Progress' && parseInt(slider.value) === 0) {
                slider.value = 50;
            } else if (status === 'Testing' && parseInt(slider.value) < 80) {
                slider.value = 85;
            }
            updateSliderLabel(slider.value);
        }

        function autofillAssetDetails(selectElem) {
            const selectedOpt = selectElem.options[selectElem.selectedIndex];
            const loc = selectedOpt.getAttribute('data-location');
            const name = selectedOpt.getAttribute('data-name');
            if (loc) {
                document.getElementById('new_location').value = loc;
            }
            if (name && !document.getElementById('new_title').value) {
                document.getElementById('new_title').value = `Maintenance for ${name}`;
            }
        }

        function submitDeleteTicket() {
            if (confirm('Are you sure you want to permanently delete this maintenance ticket?')) {
                document.getElementById('deleteTicketForm').submit();
            }
        }

        function closeModals() {
            document.querySelectorAll('.modal-backdrop').forEach(m => m.classList.remove('open'));
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal-backdrop')) {
                closeModals();
            }
        };
    </script>
</body>
</html>
