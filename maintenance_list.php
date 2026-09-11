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

            $stmt = $pdo->query("
                SELECT a.id, a.asset_id, a.name, a.location, a.condition_status
                FROM utility_assets a
                WHERE a.condition_status IN ('Damaged', 'Under Maintenance')
                  AND a.id NOT IN (
                      SELECT DISTINCT utility_asset_id 
                      FROM maintenance_requests 
                      WHERE utility_asset_id IS NOT NULL 
                        AND status NOT IN ('Completed', 'Unrepairable', 'Cancelled')
                  )
            ");
            $orphans = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($orphans as $asset) {
                $year = date('Y');
                $mCheck = $pdo->query("SELECT MAX(id) FROM maintenance_requests");
                $nextNum = ((int)$mCheck->fetchColumn()) + 1;
                $reqId = sprintf("MNT-%s-%04d", $year, $nextNum);

                $pri = ($asset['condition_status'] === 'Damaged') ? 'High' : 'Medium';
                $initStatus = ($asset['condition_status'] === 'Damaged') ? 'Reported' : 'Scheduled';
                $mType = ($asset['condition_status'] === 'Damaged') ? 'Corrective' : 'Preventive';
                $title = ($asset['condition_status'] === 'Damaged')
                    ? "Repair Damaged Asset: " . $asset['name'] . " (" . $asset['asset_id'] . ")"
                    : "Maintenance Service: " . $asset['name'] . " (" . $asset['asset_id'] . ")";
                $desc = "Auto-synced from Asset Inventory. Asset {$asset['name']} ({$asset['asset_id']}) is currently {$asset['condition_status']}.";

                $ins = $pdo->prepare("
                    INSERT INTO maintenance_requests 
                    (request_id, utility_asset_id, title, maintenance_type, source, description, priority, location, status, progress_percent)
                    VALUES (?, ?, ?, ?, 'Asset Monitoring', ?, ?, ?, ?, 0)
                ");
                $ins->execute([
                    $reqId,
                    $asset['id'],
                    $title,
                    $mType,
                    $desc,
                    $pri,
                    $asset['location'] ?? 'Barangay Central',
                    $initStatus
                ]);
                $newTicketId = (int)$pdo->lastInsertId();

                if ($newTicketId > 0) {
                    $log = $pdo->prepare("
                        INSERT INTO maintenance_status_logs 
                        (maintenance_request_id, old_status, new_status, old_progress, new_progress, changed_by, notes)
                        VALUES (?, NULL, ?, 0, 0, ?, ?)
                    ");
                    $log->execute([$newTicketId, $initStatus, $validUserId, "Auto-created work order via Asset Inventory sync"]);
                }

                $synced[] = $reqId;
            }
        } catch (Throwable $e) {
            // Fail safe
        }
        return $synced;
    }
}

// Auto-run schema check
ensureMaintenanceSchema();

// Auto-sync orphaned damaged assets
$userId = $_SESSION['user_id'] ?? 1;
syncOrphanedDamagedAssets($pdo, $userId);

// Explicit manual sync trigger (?sync=1)
if (isset($_GET['sync']) && $_GET['sync'] == '1') {
    $syncedList = syncOrphanedDamagedAssets($pdo, $userId);
    if (!empty($syncedList)) {
        $_SESSION['flash_success'] = "Successfully synchronized " . count($syncedList) . " damaged/maintenance asset(s) into active work orders: " . implode(', ', $syncedList);
    } else {
        $_SESSION['flash_success'] = "All damaged and under-maintenance assets are already synchronized with Maintenance Work Orders.";
    }
    header('Location: maintenance_list.php');
    exit();
}

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// -------------------------------------------------------------
// POST Request Handling
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Create Maintenance Ticket
    if ($action === 'create_ticket') {
        $assetId     = !empty($_POST['utility_asset_id']) ? intval($_POST['utility_asset_id']) : null;
        $title       = trim($_POST['title'] ?? '');
        $mType       = trim($_POST['maintenance_type'] ?? 'Corrective');
        $source      = 'Asset Monitoring';
        $priority    = trim($_POST['priority'] ?? 'Medium');
        $assignedTo  = trim($_POST['assigned_to'] ?? '');
        $schedDate   = !empty($_POST['scheduled_date']) ? $_POST['scheduled_date'] : null;
        $location    = trim($_POST['location'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($title)) {
            $_SESSION['flash_error'] = "Work Order Title is required.";
            header('Location: maintenance_list.php');
            exit();
        }

        try {
            $year = date('Y');
            $stmt = $pdo->query("SELECT MAX(id) FROM maintenance_requests");
            $nextNum = ((int)$stmt->fetchColumn()) + 1;
            $requestId = sprintf("MNT-%s-%04d", $year, $nextNum);

            $stmt = $pdo->prepare("
                INSERT INTO maintenance_requests 
                (request_id, utility_asset_id, title, maintenance_type, source, description, priority, assigned_to, location, status, progress_percent, scheduled_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Reported', 0, ?)
            ");
            $stmt->execute([
                $requestId,
                $assetId,
                $title,
                $mType,
                $source,
                $description,
                $priority,
                $assignedTo ?: null,
                $location ?: null,
                $schedDate
            ]);
            $ticketId = (int)$pdo->lastInsertId();

            if ($ticketId > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO maintenance_status_logs 
                    (maintenance_request_id, old_status, new_status, old_progress, new_progress, changed_by, notes)
                    VALUES (?, NULL, 'Reported', 0, 0, ?, ?)
                ");
                $stmt->execute([$ticketId, $userId, "Work order created manually"]);
            }

            if ($assetId) {
                $pdo->prepare("UPDATE utility_assets SET condition_status = 'Under Maintenance' WHERE id = ?")->execute([$assetId]);
                try {
                    $pdo->prepare("
                        INSERT INTO asset_status_logs (utility_asset_id, old_status, new_status, changed_by, notes)
                        VALUES (?, 'Operational', 'Under Maintenance', ?, ?)
                    ")->execute([$assetId, $userId, "Maintenance ticket $requestId created: $title"]);
                } catch (Throwable $e) {}
            }

            $_SESSION['flash_success'] = "Maintenance Work Order <strong>$requestId</strong> created successfully.";
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Error creating ticket: " . $e->getMessage();
        }
        header('Location: maintenance_list.php');
        exit();
    }

    // 2. Update Status & Progress
    if ($action === 'update_progress') {
        $ticketId   = intval($_POST['ticket_id'] ?? 0);
        $newStatus  = trim($_POST['status'] ?? 'Reported');
        $progress   = max(0, min(100, intval($_POST['progress_percent'] ?? 0)));
        $assignedTo = trim($_POST['assigned_to'] ?? '');
        $schedDate  = !empty($_POST['scheduled_date']) ? $_POST['scheduled_date'] : null;
        $notes      = trim($_POST['status_notes'] ?? '');

        if ($newStatus === 'Completed' && $progress < 100) {
            $progress = 100;
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM maintenance_requests WHERE id = ?");
            $stmt->execute([$ticketId]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$ticket) {
                throw new PDOException("Ticket not found.");
            }

            $oldStatus   = $ticket['status'];
            $oldProgress = intval($ticket['progress_percent']);

            $startedAt   = $ticket['started_at'];
            $completedAt = $ticket['completed_at'];

            if ($newStatus === 'In Progress' && empty($startedAt)) {
                $startedAt = date('Y-m-d H:i:s');
            }
            if ($newStatus === 'Completed' && empty($completedAt)) {
                $completedAt = date('Y-m-d H:i:s');
            } elseif ($newStatus !== 'Completed') {
                $completedAt = null;
            }

            $upd = $pdo->prepare("
                UPDATE maintenance_requests 
                SET status = ?, 
                    progress_percent = ?, 
                    assigned_to = ?, 
                    scheduled_date = ?, 
                    started_at = ?, 
                    completed_at = ?,
                    notes = ?
                WHERE id = ?
            ");
            $upd->execute([
                $newStatus,
                $progress,
                $assignedTo ?: null,
                $schedDate,
                $startedAt,
                $completedAt,
                $notes ?: null,
                $ticketId
            ]);

            $logStmt = $pdo->prepare("
                INSERT INTO maintenance_status_logs 
                (maintenance_request_id, old_status, new_status, old_progress, new_progress, changed_by, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $logStmt->execute([
                $ticketId,
                $oldStatus,
                $newStatus,
                $oldProgress,
                $progress,
                $userId,
                $notes ?: "Status updated to $newStatus ($progress%)"
            ]);

            $assetId = $ticket['utility_asset_id'];
            if ($assetId) {
                if ($newStatus === 'Completed') {
                    $astStmt = $pdo->prepare("SELECT * FROM utility_assets WHERE id = ?");
                    $astStmt->execute([$assetId]);
                    $ast = $astStmt->fetch(PDO::FETCH_ASSOC);

                    if ($ast) {
                        $parentId = !empty($ast['parent_asset_id']) ? intval($ast['parent_asset_id']) : 0;
                        $offshootQty = max(1, intval($ast['quantity'] ?? 1));

                        if ($parentId > 0) {
                            $parentStmt = $pdo->prepare("SELECT * FROM utility_assets WHERE id = ?");
                            $parentStmt->execute([$parentId]);
                            $parentAst = $parentStmt->fetch(PDO::FETCH_ASSOC);

                            if ($parentAst) {
                                $pdo->prepare("
                                    UPDATE utility_assets 
                                    SET quantity = quantity + ? 
                                    WHERE id = ?
                                ")->execute([$offshootQty, $parentId]);

                                try {
                                    $pdo->prepare("
                                        INSERT INTO asset_status_logs (utility_asset_id, old_status, new_status, changed_by, notes)
                                        VALUES (?, ?, ?, ?, ?)
                                    ")->execute([
                                        $parentId,
                                        $parentAst['condition_status'],
                                        $parentAst['condition_status'],
                                        $userId,
                                        "Merged back {$offshootQty} repaired unit(s) from work order {$ticket['request_id']} ({$ast['asset_id']})."
                                    ]);
                                } catch (Throwable $e) {}

                                $pdo->prepare("DELETE FROM utility_assets WHERE id = ?")->execute([$assetId]);
                            } else {
                                $pdo->prepare("UPDATE utility_assets SET condition_status = 'Operational' WHERE id = ?")->execute([$assetId]);
                            }
                        } else {
                            $pdo->prepare("UPDATE utility_assets SET condition_status = 'Operational' WHERE id = ?")->execute([$assetId]);
                        }

                        try {
                            $pdo->prepare("
                                INSERT INTO asset_status_logs (utility_asset_id, old_status, new_status, changed_by, notes)
                                VALUES (?, 'Under Maintenance', 'Operational', ?, ?)
                            ")->execute([$assetId, $userId, "Restored to Operational condition via completed Maintenance Work Order {$ticket['request_id']}."]);
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
}

// -------------------------------------------------------------
// Data Fetching & Aggregation
// -------------------------------------------------------------
$search       = trim($_GET['search'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$filterPri    = trim($_GET['priority'] ?? '');
$filterType   = trim($_GET['type'] ?? '');
$viewMode     = trim($_GET['view'] ?? 'table'); // Default to 'table' matching UPAD / other modules

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

// Fetch assets for dropdown in New Ticket modal
$assetsList = $pdo->query("
    SELECT a.id, a.asset_id, a.name, a.location, a.condition_status, t.name as type_name
    FROM utility_assets a
    LEFT JOIN asset_types t ON a.asset_type_id = t.id
    ORDER BY a.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Kanban Columns Mapping
$kanbanColumns = [
    'Reported'    => ['title' => 'Reported', 'icon' => 'fa-exclamation-circle', 'color' => '#eab308'],
    'Scheduled'   => ['title' => 'Scheduled', 'icon' => 'fa-calendar-alt', 'color' => '#0284c7'],
    'In Progress' => ['title' => 'In Progress', 'icon' => 'fa-tools', 'color' => '#3b82f6'],
    'On Hold'     => ['title' => 'On Hold', 'icon' => 'fa-pause-circle', 'color' => '#f97316'],
    'Testing'     => ['title' => 'Testing', 'icon' => 'fa-vial', 'color' => '#8b5cf6'],
    'Completed'   => ['title' => 'Completed', 'icon' => 'fa-check-circle', 'color' => '#10b981']
];

$kanbanTickets = [];
foreach (array_keys($kanbanColumns) as $kStatus) {
    $kanbanTickets[$kStatus] = [];
}
foreach ($tickets as $t) {
    if (isset($kanbanTickets[$t['status']])) {
        $kanbanTickets[$t['status']][] = $t;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            if (savedTheme === 'dark') {
                document.documentElement.classList.add('dark-theme');
            }
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Maintenance Management | UMAN</title>
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            display: flex;
            background: url("assets/images/cityhall.jpeg") center/cover no-repeat fixed;
            color: #1e293b;
            position: relative;
        }
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            backdrop-filter: blur(6px);
            background: rgba(0, 0, 0, 0.30);
            z-index: 0;
            transition: background 0.3s ease;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px 40px;
            transition: margin-left 0.25s ease;
            z-index: 1;
            position: relative;
        }

        .main-content.collapsed {
            margin-left: 78px;
        }

        @media (max-width: 992px) {
            .main-content {
                margin-left: 0 !important;
                padding: 20px 16px;
            }
        }

        .page-wrapper {
            width: 100%;
            max-width: 1700px;
            margin-left: auto;
            margin-right: auto;
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(15px);
            border-radius: 18px;
            padding: 35px 40px;
            box-shadow: 0 6px 24px rgba(0,0,0,0.22);
            border: 1px solid rgba(255,255,255,0.3);
            color: #1e293b;
        }

        .page-header {
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .page-header h1 {
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-header p {
            color: #64748b;
            font-size: 14px;
            margin-top: 4px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            border-radius: 16px;
            padding: 20px 18px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.18);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            color: #fff;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #1a3e7a, #2a5fc2);
            cursor: pointer;
            user-select: none;
            text-decoration: none;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: -20px; right: -20px;
            width: 90px; height: 90px;
            border-radius: 50%;
            background: rgba(255,255,255,0.08);
            pointer-events: none;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 14px 32px rgba(0,0,0,0.25);
        }
        .stat-card.active-filter {
            box-shadow: 0 0 0 3px #ffffff, 0 14px 32px rgba(0,0,0,0.35);
            transform: translateY(-4px);
        }

        .stat-card.total      { background: linear-gradient(135deg, #1a3e7a, #2a5fc2); }
        .stat-card.pending    { background: linear-gradient(135deg, #7a5c0d, #c4920e); }
        .stat-card.inprogress { background: linear-gradient(135deg, #1a6b38, #25a259); }
        .stat-card.completed  { background: linear-gradient(135deg, #0d5c7a, #0284c7); }

        .stat-card-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: rgba(255,255,255,0.18) !important;
            display: grid;
            place-items: center;
            font-size: 22px;
            flex-shrink: 0;
            color: #fff !important;
        }

        .stat-info {
            display: flex;
            flex-direction: column;
        }

        .stat-info h3 {
            font-size: 28px;
            font-weight: 700;
            color: #fff;
            line-height: 1;
            margin: 0;
        }

        .stat-info p {
            font-size: 11px;
            color: rgba(255,255,255,0.85);
            text-transform: uppercase;
            font-weight: 600;
            margin-top: 4px;
            letter-spacing: 0.6px;
            margin-bottom: 0;
        }

        /* Card Container */
        .card {
            background: #ffffff;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.07);
            margin-bottom: 24px;
            border: 1px solid rgba(0,0,0,0.06);
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 1px solid #e2e8f0;
        }
        .card-header h2 {
            font-size: 18px;
            font-weight: 600;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
        }

        /* Filter Tabs */
        .filter-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }
        .filter-tab {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            color: #475569;
            padding: 7px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            user-select: none;
            text-decoration: none;
        }
        .filter-tab:hover {
            background: #e2e8f0;
            color: #0f172a;
        }
        .filter-tab.active {
            background: #3b82f6;
            color: #ffffff;
            border-color: #3b82f6;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
        }
        .filter-tab .tab-badge {
            background: rgba(0, 0, 0, 0.08);
            padding: 2px 7px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 700;
        }
        .filter-tab.active .tab-badge {
            background: rgba(255, 255, 255, 0.25);
            color: #ffffff;
        }

        /* Buttons */
        .btn {
            padding: 8px 16px;
            border-radius: 9px;
            font-weight: 600;
            font-size: 13px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: all 0.2s ease;
            text-decoration: none;
            font-family: inherit;
        }
        .btn-primary { background: #3b82f6; color: #fff; }
        .btn-primary:hover { background: #2563eb; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(59,130,246,0.3); }
        .btn-outline { background: #f8fafc; color: #475569; border: 1px solid #cbd5e1; }
        .btn-outline:hover { background: #f1f5f9; color: #0f172a; }
        .btn-sm { padding: 6px 12px; font-size: 12px; border-radius: 7px; }

        /* Alerts */
        .alert {
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        /* Table */
        .table-responsive { overflow-x: auto; }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        th {
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            text-align: left;
            padding: 12px 16px;
            border-bottom: 2px solid #e2e8f0;
            font-size: 12.5px;
        }
        td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            vertical-align: middle;
        }
        tr:hover td { background: #f8fafc; }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .badge-pending    { background: #fef3c7; color: #92400e; }
        .badge-processing { background: #dbeafe; color: #1e40af; }
        .badge-completed  { background: #d1fae5; color: #065f46; }
        .badge-failed     { background: #fee2e2; color: #991b1b; }
        .badge-urgent     { background: #fee2e2; color: #b91c1c; font-weight: 700; }
        .badge-medium     { background: #e0f2fe; color: #0369a1; }
        .badge-low        { background: #f1f5f9; color: #475569; }

        .score-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 12px;
        }
        .score-approved    { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .score-conditional { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .score-rejected    { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        /* Form Controls */
        .form-control {
            width: 100%;
            padding: 9px 13px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 13px;
            font-family: inherit;
            background: #fff;
            color: #1e293b;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }

        /* Modal */
        .modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(6px);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        .modal-backdrop.open { display: flex; }
        .modal-dialog {
            background: #ffffff;
            border-radius: 16px;
            width: 620px;
            max-width: 95%;
            padding: 25px;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2);
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        .modal-header h3 {
            font-size: 17px;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
        }
        .modal-close-btn {
            background: transparent;
            border: none;
            font-size: 24px;
            line-height: 1;
            color: #64748b;
            cursor: pointer;
            padding: 0 4px;
        }
        .modal-close-btn:hover { color: #0f172a; }
        .modal-body { display: flex; flex-direction: column; gap: 15px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group label {
            font-size: 12px;
            font-weight: 600;
            color: #475569;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        @media (max-width: 600px) { .form-row { grid-template-columns: 1fr; } }
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
            padding-top: 14px;
            border-top: 1px solid #e2e8f0;
        }

        /* Range slider styling */
        .range-slider-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .range-slider {
            flex: 1;
            accent-color: #3b82f6;
            height: 6px;
            border-radius: 3px;
        }
        .range-slider-value {
            font-size: 13px;
            font-weight: 700;
            color: #3b82f6;
            min-width: 40px;
            text-align: right;
        }

        /* Kanban View (Matches UPAD sleek dark/light theme) */
        .kanban-board {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
            overflow-x: auto;
            padding-bottom: 12px;
        }
        .kanban-col {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 16px 14px;
            display: flex;
            flex-direction: column;
            min-width: 230px;
        }
        .kanban-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        .kanban-title {
            font-size: 13.5px;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .kanban-count {
            background: #e2e8f0;
            color: #475569;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 10px;
        }
        .kanban-cards-wrapper {
            display: flex;
            flex-direction: column;
            gap: 12px;
            flex: 1;
        }
        .kanban-card {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: 14px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.04);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .kanban-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.08);
            border-color: #3b82f6;
        }
        .kanban-card-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        .card-req-id {
            font-size: 12px;
            font-weight: 700;
            color: #3b82f6;
        }
        .card-title {
            font-size: 13px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 8px;
            line-height: 1.4;
        }
        .card-asset-tag {
            font-size: 11.5px;
            color: #64748b;
            background: #f1f5f9;
            padding: 4px 8px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 10px;
            width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .card-progress-box {
            margin-top: 6px;
        }
        .card-progress-labels {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 4px;
        }
        .progress-bar-bg {
            background: #e2e8f0;
            border-radius: 10px;
            height: 6px;
            overflow: hidden;
        }
        .progress-bar-fill {
            height: 100%;
            background: #3b82f6;
            border-radius: 10px;
            transition: width 0.3s;
        }
        .card-footer {
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: #94a3b8;
        }
        .card-empty-placeholder {
            text-align: center;
            padding: 30px 10px;
            color: #94a3b8;
            font-size: 12px;
            border: 2px dashed #e2e8f0;
            border-radius: 10px;
        }

        /* Dark Theme Overrides (Matches UPAD Module in photo 3) */
        .dark-theme body::before { background: rgba(5, 10, 22, 0.80) !important; }
        .dark-theme body { color: #f1f5f9 !important; }
        .dark-theme .page-header h1 { color: #f8fafc !important; }
        .dark-theme .page-header p { color: #94a3b8 !important; }
        .dark-theme .page-wrapper {
            background: rgba(10, 18, 35, 0.92) !important;
            border-color: rgba(255,255,255,0.08) !important;
            box-shadow: 0 6px 24px rgba(0,0,0,0.6) !important;
        }
        .dark-theme .card {
            background: rgba(15, 23, 42, 0.85) !important;
            border-color: #334155 !important;
        }
        .dark-theme .card-header { border-bottom-color: #334155 !important; }
        .dark-theme .card-header h2 { color: #f8fafc !important; }
        .dark-theme .filter-tab {
            background: #1e293b !important;
            border-color: #334155 !important;
            color: #cbd5e1 !important;
        }
        .dark-theme .filter-tab:hover {
            background: #334155 !important;
            color: #ffffff !important;
        }
        .dark-theme .filter-tab.active {
            background: #3b82f6 !important;
            color: #ffffff !important;
            border-color: #3b82f6 !important;
        }
        .dark-theme .filter-tab .tab-badge { background: rgba(255, 255, 255, 0.15) !important; color: #f8fafc !important; }
        .dark-theme th {
            background: #151f32 !important;
            color: #94a3b8 !important;
            border-bottom-color: #334155 !important;
        }
        .dark-theme td {
            color: #cbd5e1 !important;
            border-bottom-color: #334155 !important;
        }
        .dark-theme tr:hover td { background: rgba(255,255,255,0.04) !important; }
        .dark-theme .form-control {
            background: #0f172a !important;
            border-color: #475569 !important;
            color: #f8fafc !important;
        }
        .dark-theme .form-group label { color: #94a3b8 !important; }
        .dark-theme .modal-dialog {
            background: #1e293b !important;
            color: #f8fafc !important;
            border: 1px solid #475569 !important;
        }
        .dark-theme .modal-header { border-bottom-color: #334155 !important; }
        .dark-theme .modal-header h3 { color: #f8fafc !important; }
        .dark-theme .modal-close-btn { color: #94a3b8 !important; }
        .dark-theme .modal-footer { border-top-color: #334155 !important; }
        .dark-theme .kanban-col {
            background: #131d2e !important;
            border-color: #27354a !important;
        }
        .dark-theme .kanban-header { border-bottom-color: #27354a !important; }
        .dark-theme .kanban-title { color: #f8fafc !important; }
        .dark-theme .kanban-count { background: #1e293b !important; color: #94a3b8 !important; }
        .dark-theme .kanban-card {
            background: #1e293b !important;
            border-color: #334155 !important;
        }
        .dark-theme .kanban-card:hover { border-color: #3b82f6 !important; }
        .dark-theme .card-title { color: #f8fafc !important; }
        .dark-theme .card-asset-tag { background: #0f172a !important; color: #94a3b8 !important; }
        .dark-theme .progress-bar-bg { background: #334155 !important; }
        .dark-theme .card-footer { border-top-color: #334155 !important; }
        .dark-theme .card-empty-placeholder { border-color: #334155 !important; color: #64748b !important; }
        .dark-theme .btn-outline { background: #1e293b !important; border-color: #475569 !important; color: #cbd5e1 !important; }
        .dark-theme .btn-outline:hover { background: #334155 !important; color: #fff !important; }
    </style>
</head>
<body>
    <?php include 'includes/utilities_sidebar.php'; ?>

    <main class="main-content">
        <div class="page-wrapper">
            <!-- Header -->
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-tools" style="color: #3b82f6;"></i> Asset Maintenance Management</h1>
                    <p>Track, manage, and synchronize maintenance lifecycles directly with the Asset Inventory.</p>
                </div>
                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <a href="maintenance_list.php?sync=1" class="btn btn-outline" title="Rescan asset inventory for damaged or repair-needed assets">
                        <i class="fas fa-sync-alt"></i> Sync from Inventory
                    </a>
                    <button type="button" class="btn btn-primary" onclick="openNewTicketModal()">
                        <i class="fas fa-plus"></i> New Maintenance Ticket
                    </button>
                </div>
            </div>

            <!-- Flash Alerts -->
            <?php if (!empty($flashSuccess)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div><?php echo $flashSuccess; ?></div>
                </div>
            <?php endif; ?>
            <?php if (!empty($flashError)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div><?php echo $flashError; ?></div>
                </div>
            <?php endif; ?>

            <!-- Stats Grid Matching UPAD / Other Modules in Photo 3 -->
            <div class="stats-grid">
                <a href="maintenance_list.php?status=&view=<?php echo urlencode($viewMode); ?>" class="stat-card total <?php echo empty($filterStatus) ? 'active-filter' : ''; ?>" title="View All Tickets">
                    <div class="stat-card-icon"><i class="fas fa-clipboard-list"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $totalCount; ?></h3>
                        <p>Total Work Orders</p>
                    </div>
                </a>

                <a href="maintenance_list.php?status=Reported&view=<?php echo urlencode($viewMode); ?>" class="stat-card pending <?php echo $filterStatus === 'Reported' ? 'active-filter' : ''; ?>" title="View Reported / Pending">
                    <div class="stat-card-icon"><i class="fas fa-hourglass-half"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $reportedCount; ?></h3>
                        <p>Reported / Pending</p>
                    </div>
                </a>

                <a href="maintenance_list.php?status=In+Progress&view=<?php echo urlencode($viewMode); ?>" class="stat-card inprogress <?php echo $filterStatus === 'In Progress' ? 'active-filter' : ''; ?>" title="View In Progress">
                    <div class="stat-card-icon"><i class="fas fa-tools"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $inProgressCount; ?></h3>
                        <p>In Progress</p>
                    </div>
                </a>

                <a href="maintenance_list.php?status=Completed&view=<?php echo urlencode($viewMode); ?>" class="stat-card completed <?php echo $filterStatus === 'Completed' ? 'active-filter' : ''; ?>" title="View Completed & Restored">
                    <div class="stat-card-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $completedCount; ?></h3>
                        <p>Completed & Restored</p>
                    </div>
                </a>
            </div>

            <!-- Card Container -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-list-check" style="color: #3b82f6;"></i> Maintenance Work Orders</h2>

                    <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                        <!-- Filter Tabs Matching UPAD in Photo 3 -->
                        <div class="filter-tabs">
                            <a href="maintenance_list.php?status=&view=<?php echo urlencode($viewMode); ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" class="filter-tab <?php echo empty($filterStatus) ? 'active' : ''; ?>">
                                All <span class="tab-badge"><?php echo $totalCount; ?></span>
                            </a>
                            <a href="maintenance_list.php?status=Reported&view=<?php echo urlencode($viewMode); ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" class="filter-tab <?php echo $filterStatus === 'Reported' ? 'active' : ''; ?>">
                                Reported <span class="tab-badge"><?php echo $reportedCount; ?></span>
                            </a>
                            <a href="maintenance_list.php?status=Scheduled&view=<?php echo urlencode($viewMode); ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" class="filter-tab <?php echo $filterStatus === 'Scheduled' ? 'active' : ''; ?>">
                                Scheduled <span class="tab-badge"><?php echo $scheduledCount; ?></span>
                            </a>
                            <a href="maintenance_list.php?status=In+Progress&view=<?php echo urlencode($viewMode); ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" class="filter-tab <?php echo $filterStatus === 'In Progress' ? 'active' : ''; ?>">
                                In Progress <span class="tab-badge"><?php echo $inProgressCount; ?></span>
                            </a>
                            <a href="maintenance_list.php?status=Completed&view=<?php echo urlencode($viewMode); ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" class="filter-tab <?php echo $filterStatus === 'Completed' ? 'active' : ''; ?>">
                                Completed <span class="tab-badge"><?php echo $completedCount; ?></span>
                            </a>
                        </div>

                        <!-- View Switcher Toggle -->
                        <div style="display: flex; gap: 4px; background: rgba(0,0,0,0.06); padding: 3px; border-radius: 10px;">
                            <a href="maintenance_list.php?view=table<?php echo !empty($filterStatus) ? '&status='.urlencode($filterStatus) : ''; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" 
                               class="btn btn-sm <?php echo $viewMode !== 'kanban' ? 'btn-primary' : 'btn-outline'; ?>" style="padding: 5px 10px;">
                                <i class="fas fa-table"></i> Table
                            </a>
                            <a href="maintenance_list.php?view=kanban<?php echo !empty($filterStatus) ? '&status='.urlencode($filterStatus) : ''; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" 
                               class="btn btn-sm <?php echo $viewMode === 'kanban' ? 'btn-primary' : 'btn-outline'; ?>" style="padding: 5px 10px;">
                                <i class="fas fa-columns"></i> Kanban
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Secondary Filters: Search, Priority, Type -->
                <div style="margin-bottom: 20px;">
                    <form method="GET" action="maintenance_list.php" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                        <input type="hidden" name="view" value="<?php echo htmlspecialchars($viewMode); ?>">
                        <?php if (!empty($filterStatus)): ?>
                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($filterStatus); ?>">
                        <?php endif; ?>

                        <div style="position: relative; flex: 1; min-width: 240px; max-width: 360px;">
                            <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 13px;"></i>
                            <input type="text" name="search" class="form-control" style="padding-left: 36px; height: 38px;" placeholder="Search ticket, asset, technician..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>

                        <select name="priority" class="form-control" style="width: auto; height: 38px;" onchange="this.form.submit()">
                            <option value="">All Priorities</option>
                            <option value="Emergency" <?php echo $filterPri === 'Emergency' ? 'selected' : ''; ?>>Emergency</option>
                            <option value="High" <?php echo $filterPri === 'High' ? 'selected' : ''; ?>>High</option>
                            <option value="Medium" <?php echo $filterPri === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="Low" <?php echo $filterPri === 'Low' ? 'selected' : ''; ?>>Low</option>
                        </select>

                        <select name="type" class="form-control" style="width: auto; height: 38px;" onchange="this.form.submit()">
                            <option value="">All Types</option>
                            <option value="Corrective" <?php echo $filterType === 'Corrective' ? 'selected' : ''; ?>>Corrective</option>
                            <option value="Preventive" <?php echo $filterType === 'Preventive' ? 'selected' : ''; ?>>Preventive</option>
                            <option value="Emergency" <?php echo $filterType === 'Emergency' ? 'selected' : ''; ?>>Emergency</option>
                            <option value="Routine" <?php echo $filterType === 'Routine' ? 'selected' : ''; ?>>Routine</option>
                        </select>

                        <?php if (!empty($search) || !empty($filterPri) || !empty($filterType)): ?>
                            <a href="maintenance_list.php?view=<?php echo urlencode($viewMode); ?><?php echo !empty($filterStatus) ? '&status='.urlencode($filterStatus) : ''; ?>" class="btn btn-outline btn-sm" style="height: 38px;">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- VIEW MODE: TABLE (Matches Photo 3) -->
                <?php if ($viewMode !== 'kanban'): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Ticket ID</th>
                                    <th>Asset / Equipment</th>
                                    <th>Work Order Title</th>
                                    <th>Priority</th>
                                    <th>Assigned Crew</th>
                                    <th>Progress</th>
                                    <th>Status</th>
                                    <th style="text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tickets)): ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; padding: 45px 20px; color: #94a3b8;">
                                            <i class="fas fa-inbox" style="font-size: 36px; margin-bottom: 10px; display: block; opacity: 0.6;"></i>
                                            No maintenance work orders found matching your criteria.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($tickets as $t): 
                                        $pri = $t['priority'] ?? 'Medium';
                                        $priBadge = ($pri === 'Emergency' || $pri === 'High') ? 'badge-urgent' : (($pri === 'Medium') ? 'badge-medium' : 'badge-low');
                                        
                                        $st = $t['status'] ?? 'Reported';
                                        if ($st === 'Completed') {
                                            $stBadge = 'badge-completed';
                                        } elseif ($st === 'In Progress' || $st === 'Testing') {
                                            $stBadge = 'badge-processing';
                                        } elseif ($st === 'Reported') {
                                            $stBadge = 'badge-pending';
                                        } elseif ($st === 'Scheduled') {
                                            $stBadge = 'badge-medium';
                                        } else {
                                            $stBadge = 'badge-failed';
                                        }

                                        $prog = intval($t['progress_percent'] ?? 0);
                                        $scoreClass = ($prog >= 80) ? 'score-approved' : (($prog >= 30) ? 'score-conditional' : 'score-rejected');
                                    ?>
                                        <tr>
                                            <td>
                                                <strong style="color: #3b82f6; font-size: 13.5px;"><?php echo htmlspecialchars($t['request_id']); ?></strong>
                                            </td>
                                            <td>
                                                <?php if (!empty($t['asset_name'])): ?>
                                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($t['asset_name']); ?></div>
                                                    <div style="font-size: 11.5px; color: #64748b;">
                                                        <?php echo htmlspecialchars($t['asset_code'] ?? 'No Code'); ?>
                                                        <?php if (!empty($t['asset_condition'])): ?>
                                                            &bull; <span style="color: #eab308;"><?php echo htmlspecialchars($t['asset_condition']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color: #94a3b8; font-style: italic;">Unlinked Asset</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="font-weight: 500;"><?php echo htmlspecialchars($t['title'] ?? 'Maintenance Work Order'); ?></div>
                                                <div style="font-size: 11px; color: #94a3b8;"><?php echo htmlspecialchars($t['maintenance_type'] ?? 'Corrective'); ?></div>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $priBadge; ?>">
                                                    <?php echo htmlspecialchars($pri); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 6px; font-weight: 500;">
                                                    <i class="fas fa-user-circle" style="color: #94a3b8;"></i>
                                                    <span><?php echo htmlspecialchars(($t['assigned_to'] ?? '') ?: 'Unassigned'); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="score-pill <?php echo $scoreClass; ?>">
                                                    <?php echo $prog; ?>%
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $stBadge; ?>">
                                                    <?php if ($st === 'In Progress'): ?>
                                                        <i class="fas fa-spinner fa-spin"></i>
                                                    <?php elseif ($st === 'Completed'): ?>
                                                        <i class="fas fa-check"></i>
                                                    <?php endif; ?>
                                                    <?php echo htmlspecialchars($st); ?>
                                                </span>
                                            </td>
                                            <td style="text-align: right;">
                                                <button type="button" class="btn btn-primary btn-sm" onclick='openUpdateModal(<?php echo htmlspecialchars(json_encode($t), ENT_QUOTES, "UTF-8"); ?>)'>
                                                    <i class="fas fa-edit"></i> Update Status
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                <!-- VIEW MODE: KANBAN BOARD -->
                <?php else: ?>
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
                                        <?php foreach ($colItems as $item): 
                                            $pri = $item['priority'] ?? 'Medium';
                                            $priBadge = ($pri === 'Emergency' || $pri === 'High') ? 'badge-urgent' : (($pri === 'Medium') ? 'badge-medium' : 'badge-low');
                                            $prog = intval($item['progress_percent'] ?? 0);
                                        ?>
                                            <div class="kanban-card" onclick='openUpdateModal(<?php echo htmlspecialchars(json_encode($item), ENT_QUOTES, "UTF-8"); ?>)'>
                                                <div class="kanban-card-top">
                                                    <span class="card-req-id"><?php echo htmlspecialchars($item['request_id']); ?></span>
                                                    <span class="badge <?php echo $priBadge; ?>" style="font-size: 10px; padding: 2px 7px;">
                                                        <?php echo htmlspecialchars($pri); ?>
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
                                                        <span><?php echo $prog; ?>%</span>
                                                    </div>
                                                    <div class="progress-bar-bg">
                                                        <div class="progress-bar-fill" style="width: <?php echo $prog; ?>%;"></div>
                                                    </div>
                                                </div>

                                                <div class="card-footer">
                                                    <div style="display: flex; align-items: center; gap: 4px;">
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
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- ============================================== -->
    <!-- MODAL 1: CREATE NEW MAINTENANCE TICKET         -->
    <!-- ============================================== -->
    <div class="modal-backdrop" id="modalNewTicket">
        <div class="modal-dialog">
            <form method="POST" action="maintenance_list.php">
                <input type="hidden" name="action" value="create_ticket">

                <div class="modal-header">
                    <h3><i class="fas fa-plus-circle" style="color: #3b82f6;"></i> Create Maintenance Ticket</h3>
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
                                <option value="Corrective">Corrective (Repair)</option>
                                <option value="Preventive">Preventive (Scheduled Servicing)</option>
                                <option value="Emergency">Emergency (Immediate Hazard)</option>
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
                            <label for="new_assigned_to">Assign Technician / Team</label>
                            <input type="text" name="assigned_to" id="new_assigned_to" class="form-control" placeholder="e.g. Lead Technician Reyes">
                        </div>

                        <div class="form-group">
                            <label for="new_scheduled_date">Target Completion Date</label>
                            <input type="date" name="scheduled_date" id="new_scheduled_date" class="form-control">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="new_location">Site Location</label>
                        <input type="text" name="location" id="new_location" class="form-control" placeholder="e.g. Barangay Central Water Plant">
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
    <!-- (Delete Ticket removed as requested)           -->
    <!-- ============================================== -->
    <div class="modal-backdrop" id="modalUpdateTicket">
        <div class="modal-dialog">
            <form method="POST" action="maintenance_list.php">
                <input type="hidden" name="action" value="update_progress">
                <input type="hidden" name="ticket_id" id="upd_ticket_id">

                <div class="modal-header">
                    <h3>
                        <i class="fas fa-tasks" style="color: #3b82f6;"></i>
                        <span id="upd_req_id_badge">MNT-XXXX</span>
                    </h3>
                    <button type="button" class="modal-close-btn" onclick="closeModals()">&times;</button>
                </div>

                <div class="modal-body">
                    <div style="background: rgba(0,0,0,0.04); padding: 12px 16px; border-radius: 10px; border: 1px solid rgba(0,0,0,0.08);">
                        <h4 id="upd_title" style="font-size: 15px; font-weight: 700; margin-bottom: 4px;"></h4>
                        <div id="upd_asset_info" style="font-size: 12px; color: #64748b;"></div>
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
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModals()">Close</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save & Synchronize</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openNewTicketModal() {
            document.getElementById('modalNewTicket').classList.add('open');
        }

        function openUpdateModal(ticket) {
            document.getElementById('upd_ticket_id').value = ticket.id;
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
