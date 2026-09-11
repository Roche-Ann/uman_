<?php
// maintenance_list.php - Maintenance Tracking & Management Module
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'] ?? 1;

// ===========================================
// 1. SELF-HEALING DATABASE MIGRATION
// ===========================================
try {
    // Ensure table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `maintenance_requests` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `request_id` VARCHAR(50) NOT NULL UNIQUE,
            `utility_asset_id` INT NULL,
            `title` VARCHAR(255) NULL,
            `maintenance_type` ENUM('Corrective', 'Preventive', 'Routine', 'Emergency', 'Inspection Followup') NOT NULL DEFAULT 'Corrective',
            `source` VARCHAR(100) NOT NULL DEFAULT 'Asset Monitoring',
            `description` TEXT NOT NULL,
            `priority` ENUM('Low', 'Medium', 'High', 'Emergency') NOT NULL DEFAULT 'Medium',
            `location` TEXT NOT NULL,
            `status` ENUM('Reported', 'Under Review', 'Scheduled', 'In Progress', 'On Hold', 'Completed', 'Cancelled', 'Created', 'Forwarded', 'Accepted by Maintenance System', 'Closed') NOT NULL DEFAULT 'Reported',
            `progress_percent` INT NOT NULL DEFAULT 0,
            `assigned_personnel` VARCHAR(150) NULL,
            `assigned_team` VARCHAR(100) NULL,
            `scheduled_start_date` DATETIME NULL,
            `target_completion_date` DATETIME NULL,
            `actual_completion_date` DATETIME NULL,
            `findings` TEXT NULL,
            `root_cause` TEXT NULL,
            `action_taken` TEXT NULL,
            `parts_replaced` TEXT NULL,
            `maintenance_notes` TEXT NULL,
            `follow_up_needed` TINYINT(1) DEFAULT 0,
            `follow_up_reason` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX(`utility_asset_id`),
            INDEX(`status`),
            INDEX(`priority`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `maintenance_status_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `maintenance_request_id` INT NOT NULL,
            `old_status` VARCHAR(50) NULL,
            `new_status` VARCHAR(50) NOT NULL,
            `changed_by` INT NOT NULL,
            `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `notes` TEXT NULL,
            INDEX(`maintenance_request_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $existingCols = $pdo->query("SHOW COLUMNS FROM maintenance_requests")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('title', $existingCols)) {
        $pdo->exec("ALTER TABLE maintenance_requests ADD COLUMN title VARCHAR(255) NULL AFTER utility_asset_id");
    }
    if (!in_array('maintenance_type', $existingCols)) {
        $pdo->exec("ALTER TABLE maintenance_requests ADD COLUMN maintenance_type ENUM('Corrective', 'Preventive', 'Routine', 'Emergency', 'Inspection Followup') NOT NULL DEFAULT 'Corrective' AFTER title");
    }
    if (!in_array('progress_percent', $existingCols)) {
        $pdo->exec("ALTER TABLE maintenance_requests ADD COLUMN progress_percent INT NOT NULL DEFAULT 0 AFTER status");
    }
    if (!in_array('assigned_personnel', $existingCols)) {
        $pdo->exec("ALTER TABLE maintenance_requests ADD COLUMN assigned_personnel VARCHAR(150) NULL AFTER progress_percent");
    }
    if (!in_array('assigned_team', $existingCols)) {
        $pdo->exec("ALTER TABLE maintenance_requests ADD COLUMN assigned_team VARCHAR(100) NULL AFTER assigned_personnel");
    }
    if (!in_array('scheduled_start_date', $existingCols)) {
        $pdo->exec("ALTER TABLE maintenance_requests ADD COLUMN scheduled_start_date DATETIME NULL AFTER assigned_team");
    }
    if (!in_array('target_completion_date', $existingCols)) {
        $pdo->exec("ALTER TABLE maintenance_requests ADD COLUMN target_completion_date DATETIME NULL AFTER scheduled_start_date");
    }
    if (!in_array('actual_completion_date', $existingCols)) {
        $pdo->exec("ALTER TABLE maintenance_requests ADD COLUMN actual_completion_date DATETIME NULL AFTER target_completion_date");
    }
    if (!in_array('findings', $existingCols)) {
        $pdo->exec("ALTER TABLE maintenance_requests ADD COLUMN findings TEXT NULL AFTER actual_completion_date");
    }
    if (!in_array('root_cause', $existingCols)) {
        $pdo->exec("ALTER TABLE maintenance_requests ADD COLUMN root_cause TEXT NULL AFTER findings");
    }
    if (!in_array('action_taken', $existingCols)) {
        $pdo->exec("ALTER TABLE maintenance_requests ADD COLUMN action_taken TEXT NULL AFTER root_cause");
    }
    if (!in_array('parts_replaced', $existingCols)) {
        $pdo->exec("ALTER TABLE maintenance_requests ADD COLUMN parts_replaced TEXT NULL AFTER action_taken");
    }
    if (!in_array('maintenance_notes', $existingCols)) {
        $pdo->exec("ALTER TABLE maintenance_requests ADD COLUMN maintenance_notes TEXT NULL AFTER parts_replaced");
    }
} catch (Throwable $e) {
    // Silently continue
}

// ===========================================
// 2. AJAX ENDPOINTS
// ===========================================
if (isset($_GET['fetch_logs_id'])) {
    header('Content-Type: application/json');
    $id = intval($_GET['fetch_logs_id']);
    $stmt = $pdo->prepare("
        SELECT l.*, COALESCE(u.full_name, u.username, 'System') as user_name 
        FROM maintenance_status_logs l 
        LEFT JOIN users u ON l.changed_by = u.id 
        WHERE l.maintenance_request_id = ? 
        ORDER BY l.changed_at DESC
    ");
    $stmt->execute([$id]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($logs);
    exit();
}

if (isset($_GET['fetch_record_id'])) {
    header('Content-Type: application/json');
    $id = intval($_GET['fetch_record_id']);
    $stmt = $pdo->prepare("
        SELECT 
            r.*,
            a.name as asset_name,
            a.asset_id as asset_code,
            a.location as asset_location_default,
            a.condition_status as asset_condition,
            t.name as asset_category
        FROM maintenance_requests r
        LEFT JOIN utility_assets a ON r.utility_asset_id = a.id
        LEFT JOIN asset_types t ON a.asset_type_id = t.id
        WHERE r.id = ?
    ");
    $stmt->execute([$id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode($record ?: ['error' => 'Record not found']);
    exit();
}

// ===========================================
// 3. POST ACTION HANDLERS
// ===========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- CREATE NEW WORK ORDER ---
    if ($action === 'create') {
        $asset_id = !empty($_POST['utility_asset_id']) ? intval($_POST['utility_asset_id']) : null;
        $title = trim($_POST['title'] ?? '');
        $maintenance_type = $_POST['maintenance_type'] ?? 'Corrective';
        $priority = $_POST['priority'] ?? 'Medium';
        $source = $_POST['source'] ?? 'Asset Monitoring';
        $status = $_POST['status'] ?? 'Reported';
        $location = trim($_POST['location'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $scheduled_start = !empty($_POST['scheduled_start_date']) ? $_POST['scheduled_start_date'] : null;
        $target_comp = !empty($_POST['target_completion_date']) ? $_POST['target_completion_date'] : null;
        $assigned_lead = trim($_POST['assigned_personnel'] ?? '');
        $assigned_team = trim($_POST['assigned_team'] ?? '');

        if (!empty($title) && !empty($location)) {
            try {
                $year = date('Y');
                $seqRow = $pdo->query("SELECT COUNT(*) FROM maintenance_requests WHERE request_id LIKE 'MNT-$year-%'")->fetchColumn();
                $seq = intval($seqRow) + 1;
                $reqId = sprintf("MNT-%s-%04d", $year, $seq);

                $stmt = $pdo->prepare("
                    INSERT INTO maintenance_requests 
                        (request_id, utility_asset_id, title, maintenance_type, source, description, priority, location, status, progress_percent, assigned_personnel, assigned_team, scheduled_start_date, target_completion_date, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$reqId, $asset_id, $title, $maintenance_type, $source, $description, $priority, $location, $status, $assigned_lead, $assigned_team, $scheduled_start, $target_comp]);
                $newId = $pdo->lastInsertId();

                // Status log
                $pdo->prepare("
                    INSERT INTO maintenance_status_logs (maintenance_request_id, old_status, new_status, changed_by, notes)
                    VALUES (?, NULL, ?, ?, 'Work order created')
                ")->execute([$newId, $status, $userId]);

                // Update asset condition to Under Maintenance if linked
                if ($asset_id && $status !== 'Completed') {
                    $pdo->prepare("UPDATE utility_assets SET condition_status = 'Under Maintenance' WHERE id = ?")->execute([$asset_id]);
                    try {
                        $pdo->prepare("INSERT INTO asset_status_logs (utility_asset_id, action_type, old_status, new_status, changed_by, notes) VALUES (?, 'status_changed', 'Operational', 'Under Maintenance', ?, ?)")
                            ->execute([$asset_id, $userId, "Work order {$reqId} created."]);
                    } catch (Throwable $e) {}
                }

                $_SESSION['flash_success'] = "Maintenance task <strong>{$reqId}</strong> created successfully.";
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = "Failed to create task: " . $e->getMessage();
            }
        } else {
            $_SESSION['flash_error'] = "Please provide both a Title and Location for the maintenance task.";
        }
        header('Location: maintenance_list.php');
        exit();
    }

    // --- UPDATE WORK ORDER & PROGRESS ---
    if ($action === 'update_status') {
        $id = intval($_POST['id'] ?? 0);
        $new_status = $_POST['status'] ?? 'Reported';
        $progress_percent = max(0, min(100, intval($_POST['progress_percent'] ?? 0)));
        $scheduled_start = !empty($_POST['scheduled_start_date']) ? $_POST['scheduled_start_date'] : null;
        $target_comp = !empty($_POST['target_completion_date']) ? $_POST['target_completion_date'] : null;
        $actual_comp = !empty($_POST['actual_completion_date']) ? $_POST['actual_completion_date'] : null;
        $assigned_lead = trim($_POST['assigned_personnel'] ?? '');
        $assigned_team = trim($_POST['assigned_team'] ?? '');
        $findings = trim($_POST['findings'] ?? '');
        $root_cause = trim($_POST['root_cause'] ?? '');
        $action_taken = trim($_POST['action_taken'] ?? '');
        $parts_replaced = trim($_POST['parts_replaced'] ?? '');
        $status_note = trim($_POST['status_note'] ?? '');

        if ($new_status === 'Completed' && $progress_percent < 100) {
            $progress_percent = 100;
        }
        if ($progress_percent === 100 && $new_status !== 'Cancelled') {
            $new_status = 'Completed';
        }
        if ($new_status === 'Completed' && empty($actual_comp)) {
            $actual_comp = date('Y-m-d H:i:s');
        }

        if ($id > 0) {
            try {
                $currStmt = $pdo->prepare("
                    SELECT r.*, a.id as asset_db_id, a.parent_asset_id, a.asset_id as asset_code, a.quantity as asset_qty 
                    FROM maintenance_requests r 
                    LEFT JOIN utility_assets a ON r.utility_asset_id = a.id 
                    WHERE r.id = ?
                ");
                $currStmt->execute([$id]);
                $curr = $currStmt->fetch(PDO::FETCH_ASSOC);

                if ($curr) {
                    $old_status = $curr['status'];

                    $upd = $pdo->prepare("
                        UPDATE maintenance_requests 
                        SET status = ?, progress_percent = ?, scheduled_start_date = ?, target_completion_date = ?, actual_completion_date = ?, assigned_personnel = ?, assigned_team = ?, findings = ?, root_cause = ?, action_taken = ?, parts_replaced = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $upd->execute([
                        $new_status, $progress_percent, $scheduled_start, $target_comp, $actual_comp,
                        $assigned_lead, $assigned_team, $findings, $root_cause, $action_taken, $parts_replaced, $id
                    ]);

                    // Log to maintenance_status_logs
                    $logNote = $status_note ?: "Progress: {$progress_percent}%. Status: {$new_status}.";
                    $pdo->prepare("
                        INSERT INTO maintenance_status_logs (maintenance_request_id, old_status, new_status, changed_by, notes)
                        VALUES (?, ?, ?, ?, ?)
                    ")->execute([$id, $old_status, $new_status, $userId, $logNote]);

                    // BIDIRECTIONAL AUTO-SYNC TO ASSET INVENTORY
                    if (!empty($curr['utility_asset_id'])) {
                        $assetDbId = intval($curr['utility_asset_id']);
                        $assetCode = $curr['asset_code'] ?: "Asset #{$assetDbId}";

                        if ($new_status === 'Completed') {
                            // If child offshoot, merge back into parent asset
                            if (!empty($curr['parent_asset_id'])) {
                                $parentId = intval($curr['parent_asset_id']);
                                $offshootQty = intval($curr['asset_qty'] ?? 1);

                                $pdo->prepare("UPDATE utility_assets SET quantity = quantity + ? WHERE id = ?")->execute([$offshootQty, $parentId]);
                                try {
                                    $pdo->prepare("INSERT INTO asset_status_logs (utility_asset_id, action_type, old_status, new_status, changed_by, notes) VALUES (?, 'split_merged', 'Under Maintenance', 'Operational (Merged)', ?, ?)")
                                        ->execute([$assetDbId, $userId, "Maintenance completed: {$offshootQty} unit(s) restored and merged back into parent asset."]);
                                    $pdo->prepare("INSERT INTO asset_notifications (type, message) VALUES ('status_changed', ?)")
                                        ->execute(["Maintenance completed: {$offshootQty} unit(s) of {$assetCode} restored and merged back."]);
                                } catch (Throwable $e) {}
                                $pdo->prepare("DELETE FROM utility_assets WHERE id = ?")->execute([$assetDbId]);
                            } else {
                                // Regular asset restored to Operational
                                $pdo->prepare("UPDATE utility_assets SET condition_status = 'Operational' WHERE id = ?")->execute([$assetDbId]);
                                try {
                                    $pdo->prepare("INSERT INTO asset_status_logs (utility_asset_id, action_type, old_status, new_status, changed_by, notes) VALUES (?, 'status_changed', 'Under Maintenance', 'Operational', ?, ?)")
                                        ->execute([$assetDbId, $userId, "Maintenance task {$curr['request_id']} completed. Asset restored to Operational."]);
                                    $pdo->prepare("INSERT INTO asset_notifications (type, message) VALUES ('status_changed', ?)")
                                        ->execute(["Asset {$assetCode} maintenance completed and marked as Operational."]);
                                } catch (Throwable $e) {}
                            }
                            $_SESSION['flash_success'] = "Work order <strong>{$curr['request_id']}</strong> marked as <strong>Completed</strong>! Linked asset automatically restored to <strong>Operational</strong>.";
                        } else {
                            $_SESSION['flash_success'] = "Work order <strong>{$curr['request_id']}</strong> updated successfully (Progress: {$progress_percent}%).";
                        }
                    } else {
                        $_SESSION['flash_success'] = "Work order <strong>{$curr['request_id']}</strong> updated successfully (Progress: {$progress_percent}%).";
                    }
                }
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = "Update failed: " . $e->getMessage();
            }
        }
        header('Location: maintenance_list.php');
        exit();
    }

    // --- DELETE WORK ORDER ---
    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $pdo->prepare("DELETE FROM maintenance_requests WHERE id = ?")->execute([$id]);
                $_SESSION['flash_success'] = "Maintenance task deleted.";
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = "Failed to delete task: " . $e->getMessage();
            }
        }
        header('Location: maintenance_list.php');
        exit();
    }
}

// Flash Messages
$error = $_SESSION['flash_error'] ?? '';
$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

// ===========================================
// 4. STATS SUMMARY AGGREGATION
// ===========================================
function getCount($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

$statTotal = getCount($pdo, "SELECT COUNT(*) FROM maintenance_requests");
$statReported = getCount($pdo, "SELECT COUNT(*) FROM maintenance_requests WHERE status IN ('Reported', 'Under Review', 'Created')");
$statActive = getCount($pdo, "SELECT COUNT(*) FROM maintenance_requests WHERE status IN ('Scheduled', 'In Progress', 'On Hold')");
$statCompleted = getCount($pdo, "SELECT COUNT(*) FROM maintenance_requests WHERE status IN ('Completed', 'Closed')");
$statOverdue = getCount($pdo, "SELECT COUNT(*) FROM maintenance_requests WHERE status NOT IN ('Completed', 'Cancelled', 'Closed') AND target_completion_date IS NOT NULL AND target_completion_date < NOW()");

// ===========================================
// 5. QUERY FILTERS & PAGINATION
// ===========================================
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$source_filter = $_GET['source'] ?? '';
$priority_filter = $_GET['priority'] ?? '';
$type_filter = $_GET['maintenance_type'] ?? '';
$asset_filter = intval($_GET['utility_asset_id'] ?? 0);

$page = max(1, intval($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(r.title LIKE ? OR r.request_id LIKE ? OR r.description LIKE ? OR r.location LIKE ? OR r.assigned_personnel LIKE ? OR a.name LIKE ? OR a.asset_id LIKE ?)";
    $sw = '%' . $search . '%';
    $params = array_merge($params, [$sw, $sw, $sw, $sw, $sw, $sw, $sw]);
}

if (!empty($status_filter)) {
    if ($status_filter === 'Overdue') {
        $conditions[] = "r.status NOT IN ('Completed', 'Cancelled', 'Closed') AND r.target_completion_date IS NOT NULL AND r.target_completion_date < NOW()";
    } elseif ($status_filter === 'Active') {
        $conditions[] = "r.status IN ('Scheduled', 'In Progress', 'On Hold')";
    } else {
        $conditions[] = "r.status = ?";
        $params[] = $status_filter;
    }
}

if (!empty($priority_filter)) {
    $conditions[] = "r.priority = ?";
    $params[] = $priority_filter;
}

if (!empty($type_filter)) {
    $conditions[] = "r.maintenance_type = ?";
    $params[] = $type_filter;
}

if (!empty($source_filter)) {
    $conditions[] = "r.source = ?";
    $params[] = $source_filter;
}

if ($asset_filter > 0) {
    $conditions[] = "r.utility_asset_id = ?";
    $params[] = $asset_filter;
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Count for pagination
$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM maintenance_requests r
    LEFT JOIN utility_assets a ON r.utility_asset_id = a.id 
    $whereClause
");
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRecords / $limit));

// Fetch Records
$dataStmt = $pdo->prepare("
    SELECT 
        r.*,
        a.name as asset_name,
        a.asset_id as asset_code,
        a.location as asset_location_default,
        a.condition_status as asset_condition,
        t.name as asset_category
    FROM maintenance_requests r
    LEFT JOIN utility_assets a ON r.utility_asset_id = a.id
    LEFT JOIN asset_types t ON a.asset_type_id = t.id
    $whereClause
    ORDER BY 
        CASE 
            WHEN r.priority = 'Emergency' AND r.status NOT IN ('Completed', 'Cancelled') THEN 1
            WHEN r.priority = 'High' AND r.status NOT IN ('Completed', 'Cancelled') THEN 2
            WHEN r.status = 'In Progress' THEN 3
            WHEN r.status = 'Scheduled' THEN 4
            WHEN r.status = 'Reported' THEN 5
            ELSE 6
        END,
        r.created_at DESC
    LIMIT $limit OFFSET $offset
");
$dataStmt->execute($params);
$requests = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch assets list for the Create Modal dropdown
$assetsList = $pdo->query("
    SELECT a.id, a.asset_id, a.name, a.location, a.condition_status, t.name as category_name
    FROM utility_assets a
    LEFT JOIN asset_types t ON a.asset_type_id = t.id
    ORDER BY a.name ASC
")->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Maintenance Management & Tracking | Utilities System</title>
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap");

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            background: url("assets/images/cityhall.jpeg") center/cover no-repeat fixed;
            position: relative;
        }

        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            backdrop-filter: blur(8px);
            background: rgba(15, 23, 42, 0.45);
            z-index: 0;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px 40px;
            transition: margin-left 0.25s ease;
            z-index: 1;
            position: relative;
            max-width: 100%;
        }

        .main-content.collapsed {
            margin-left: 78px;
        }

        .glass-card {
            width: 100%;
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(16px);
            border-radius: 20px;
            padding: 35px 40px;
            color: #1e293b;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.4);
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }

        .page-title {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .page-title-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.35);
        }

        .page-title h1 {
            font-size: 26px;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -0.5px;
        }

        .page-title p {
            font-size: 13.5px;
            color: #64748b;
            margin-top: 2px;
        }

        .header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
        }

        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
        }

        .btn-outline {
            background: white;
            color: #475569;
            border: 1px solid #cbd5e1;
        }

        .btn-outline:hover {
            background: #f8fafc;
            color: #0f172a;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: white;
            border-radius: 14px;
            padding: 18px 20px;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
            transition: transform 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .stat-icon.total { background: #eff6ff; color: #2563eb; }
        .stat-icon.reported { background: #fef3c7; color: #d97706; }
        .stat-icon.active { background: #e0e7ff; color: #4f46e5; }
        .stat-icon.completed { background: #ecfdf5; color: #059669; }
        .stat-icon.overdue { background: #fee2e2; color: #dc2626; }

        .stat-info h3 {
            font-size: 22px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
        }

        .stat-info p {
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Filter Toolbar */
        .filter-toolbar {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 16px 20px;
            margin-bottom: 24px;
        }

        .filter-form {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            min-width: 240px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 9px 14px 9px 38px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 13.5px;
            outline: none;
            background: white;
        }

        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 14px;
        }

        .filter-select {
            padding: 9px 14px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 13.5px;
            background: white;
            color: #334155;
            outline: none;
            min-width: 130px;
        }

        /* Table */
        .table-container {
            width: 100%;
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background: white;
        }

        .maintenance-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
            text-align: left;
        }

        .maintenance-table th {
            background: #f8fafc;
            padding: 14px 18px;
            font-weight: 600;
            color: #475569;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
        }

        .maintenance-table td {
            padding: 14px 18px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            vertical-align: middle;
        }

        .maintenance-table tr:hover {
            background: #f8fafc;
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 600;
            white-space: nowrap;
        }

        .badge-reported { background: #fef3c7; color: #92400e; }
        .badge-review { background: #e0e7ff; color: #3730a3; }
        .badge-scheduled { background: #eff6ff; color: #1d4ed8; }
        .badge-progress { background: #f3e8ff; color: #6b21a8; }
        .badge-hold { background: #ffedd5; color: #9a3412; }
        .badge-completed { background: #dcfce7; color: #15803d; }
        .badge-cancelled { background: #f1f5f9; color: #64748b; }

        .badge-pLow { background: #f1f5f9; color: #475569; }
        .badge-pMed { background: #e0f2fe; color: #0369a1; }
        .badge-pHigh { background: #fed7aa; color: #c2410c; }
        .badge-pEmg { background: #fee2e2; color: #b91c1c; }

        .badge-type {
            background: #f1f5f9;
            color: #475569;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
        }

        /* Progress Bar */
        .progress-bar-container {
            width: 100px;
            height: 7px;
            background: #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 5px;
        }

        .progress-bar-fill {
            height: 100%;
            background: #2563eb;
            border-radius: 10px;
            transition: width 0.3s ease;
        }

        /* Action buttons group */
        .action-btn-group {
            display: flex;
            gap: 6px;
            align-items: center;
        }

        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #cbd5e1;
            background: white;
            color: #475569;
            cursor: pointer;
            transition: all 0.15s ease;
            text-decoration: none;
            font-size: 13px;
        }

        .action-btn:hover {
            background: #f8fafc;
            color: #0f172a;
            border-color: #94a3b8;
        }

        .action-btn.btn-update {
            color: #2563eb;
            border-color: #bfdbfe;
            background: #eff6ff;
        }

        .action-btn.btn-update:hover {
            background: #2563eb;
            color: white;
        }

        .action-btn.btn-del {
            color: #ef4444;
            border-color: #fecaca;
            background: #fef2f2;
        }

        .action-btn.btn-del:hover {
            background: #ef4444;
            color: white;
        }

        .overdue-tag {
            color: #dc2626;
            font-size: 11px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            margin-top: 2px;
        }

        /* Modals */
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-backdrop.active {
            display: flex;
        }

        .modal-box {
            background: white;
            border-radius: 18px;
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            border: 1px solid #e2e8f0;
            animation: modalPop 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .modal-box.modal-box-lg {
            max-width: 750px;
        }

        @keyframes modalPop {
            0% { transform: scale(0.95); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-close-btn {
            background: transparent;
            border: none;
            font-size: 22px;
            color: #94a3b8;
            cursor: pointer;
            line-height: 1;
        }

        .modal-close-btn:hover {
            color: #0f172a;
        }

        .modal-body {
            padding: 24px;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            background: #f8fafc;
            border-radius: 0 0 18px 18px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        .form-grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 12.5px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 6px;
        }

        .form-group label .req {
            color: #ef4444;
        }

        .form-control, .form-select {
            width: 100%;
            padding: 9px 12px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 13.5px;
            outline: none;
            color: #1e293b;
            background: white;
        }

        .form-control:focus, .form-select:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .timeline-list {
            position: relative;
            padding-left: 20px;
        }

        .timeline-list::before {
            content: "";
            position: absolute;
            left: 7px;
            top: 6px;
            bottom: 6px;
            width: 2px;
            background: #e2e8f0;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 18px;
        }

        .timeline-bullet {
            position: absolute;
            left: -20px;
            top: 4px;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #2563eb;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #2563eb;
        }

        .timeline-content {
            background: #f8fafc;
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            font-size: 13px;
        }

        .timeline-meta {
            font-size: 11px;
            color: #64748b;
            margin-bottom: 4px;
            display: flex;
            justify-content: space-between;
        }

        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 13.5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 24px;
            font-size: 13px;
            color: #64748b;
        }

        .page-links {
            display: flex;
            gap: 4px;
        }

        .page-link {
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            background: white;
            color: #334155;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
        }

        .page-link.active {
            background: #2563eb;
            color: white;
            border-color: #2563eb;
        }

        .page-link:hover:not(.active) {
            background: #f8fafc;
        }

        /* ===== DARK THEME OVERRIDES ===== */
        .dark-theme .glass-card {
            background: rgba(30, 41, 59, 0.95);
            border-color: rgba(255, 255, 255, 0.1);
            color: #f8fafc;
        }
        .dark-theme .page-title h1 { color: #f8fafc; }
        .dark-theme .stat-card { background: #1e293b; border-color: #334155; }
        .dark-theme .stat-info h3 { color: #f8fafc; }
        .dark-theme .filter-toolbar { background: #0f172a; border-color: #334155; }
        .dark-theme .search-box input, .dark-theme .filter-select { background: #1e293b; border-color: #334155; color: #f8fafc; }
        .dark-theme .table-container { background: #1e293b; border-color: #334155; }
        .dark-theme .maintenance-table th { background: #0f172a; border-color: #334155; color: #94a3b8; }
        .dark-theme .maintenance-table td { border-color: #334155; color: #cbd5e1; }
        .dark-theme .maintenance-table tr:hover { background: #0f172a; }
        .dark-theme .modal-box { background: #1e293b; border-color: #334155; color: #f8fafc; }
        .dark-theme .modal-header { border-color: #334155; }
        .dark-theme .modal-header h3 { color: #f8fafc; }
        .dark-theme .modal-footer { background: #0f172a; border-color: #334155; }
        .dark-theme .form-control, .dark-theme .form-select { background: #0f172a; border-color: #334155; color: #f8fafc; }
        .dark-theme .timeline-content { background: #0f172a; border-color: #334155; color: #cbd5e1; }
        .dark-theme .action-btn { background: #0f172a; border-color: #334155; color: #cbd5e1; }
    </style>
</head>
<body>

<?php include 'includes/utilities_sidebar.php'; ?>

<main class="main-content">
    <div class="glass-card">
        
        <!-- Flash Alert -->
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title">
                <div class="page-title-icon">
                    <i class="fas fa-tools"></i>
                </div>
                <div>
                    <h1>Maintenance Operations & Tracking</h1>
                    <p>Track, assign, and update equipment repairs and work order progress</p>
                </div>
            </div>

            <div class="header-actions">
                <a href="maintenance_dashboard.php" class="btn btn-outline">
                    <i class="fas fa-chart-pie"></i> Dashboard
                </a>
                <button type="button" class="btn btn-primary" onclick="openCreateModal()">
                    <i class="fas fa-plus"></i> New Work Order
                </button>
            </div>
        </div>

        <!-- Stats Overview Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon total"><i class="fas fa-tasks"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($statTotal); ?></h3>
                    <p>Total Tasks</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon reported"><i class="fas fa-clipboard-list"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($statReported); ?></h3>
                    <p>Reported / Review</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon active"><i class="fas fa-wrench"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($statActive); ?></h3>
                    <p>In Progress / Active</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon completed"><i class="fas fa-check-double"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($statCompleted); ?></h3>
                    <p>Completed Tasks</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon overdue"><i class="fas fa-clock"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($statOverdue); ?></h3>
                    <p>Overdue Tasks</p>
                </div>
            </div>
        </div>

        <!-- Filter Toolbar -->
        <div class="filter-toolbar">
            <form method="GET" class="filter-form">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search reference, title, technician, location, asset..." value="<?php echo htmlspecialchars($search); ?>">
                </div>

                <select name="status" class="filter-select">
                    <option value="">All Statuses</option>
                    <option value="Active" <?php echo $status_filter === 'Active' ? 'selected' : ''; ?>>Active (Scheduled/In Progress)</option>
                    <option value="Reported" <?php echo $status_filter === 'Reported' ? 'selected' : ''; ?>>Reported</option>
                    <option value="Under Review" <?php echo $status_filter === 'Under Review' ? 'selected' : ''; ?>>Under Review</option>
                    <option value="Scheduled" <?php echo $status_filter === 'Scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                    <option value="In Progress" <?php echo $status_filter === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                    <option value="On Hold" <?php echo $status_filter === 'On Hold' ? 'selected' : ''; ?>>On Hold</option>
                    <option value="Completed" <?php echo $status_filter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="Overdue" <?php echo $status_filter === 'Overdue' ? 'selected' : ''; ?>>Overdue Tasks</option>
                </select>

                <select name="priority" class="filter-select">
                    <option value="">All Priorities</option>
                    <option value="Low" <?php echo $priority_filter === 'Low' ? 'selected' : ''; ?>>Low</option>
                    <option value="Medium" <?php echo $priority_filter === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                    <option value="High" <?php echo $priority_filter === 'High' ? 'selected' : ''; ?>>High</option>
                    <option value="Emergency" <?php echo $priority_filter === 'Emergency' ? 'selected' : ''; ?>>Emergency</option>
                </select>

                <select name="maintenance_type" class="filter-select">
                    <option value="">All Types</option>
                    <option value="Corrective" <?php echo $type_filter === 'Corrective' ? 'selected' : ''; ?>>Corrective</option>
                    <option value="Preventive" <?php echo $type_filter === 'Preventive' ? 'selected' : ''; ?>>Preventive</option>
                    <option value="Routine" <?php echo $type_filter === 'Routine' ? 'selected' : ''; ?>>Routine</option>
                    <option value="Emergency" <?php echo $type_filter === 'Emergency' ? 'selected' : ''; ?>>Emergency</option>
                </select>

                <button type="submit" class="btn btn-outline" style="padding: 9px 16px;"><i class="fas fa-filter"></i> Filter</button>
                <?php if (!empty($search) || !empty($status_filter) || !empty($priority_filter) || !empty($type_filter) || $asset_filter > 0): ?>
                    <a href="maintenance_list.php" class="btn btn-outline" style="padding: 9px 16px; color:#ef4444;"><i class="fas fa-times"></i> Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Maintenance Table -->
        <div class="table-container">
            <table class="maintenance-table">
                <thead>
                    <tr>
                        <th>Work Order</th>
                        <th>Target Asset</th>
                        <th>Type & Source</th>
                        <th>Priority</th>
                        <th>Timeline</th>
                        <th>Assigned Crew</th>
                        <th>Status & Progress</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px; color: #94a3b8;">
                                <i class="fas fa-inbox" style="font-size: 36px; margin-bottom: 12px; display: block;"></i>
                                No maintenance work orders found matching your criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requests as $req): 
                            $progress = intval($req['progress_percent'] ?? 0);
                            $isOverdue = (!in_array($req['status'], ['Completed', 'Cancelled', 'Closed']) && !empty($req['target_completion_date']) && strtotime($req['target_completion_date']) < time());
                            
                            // Status badge mapping
                            $statusClass = 'badge-reported';
                            if ($req['status'] === 'Under Review') $statusClass = 'badge-review';
                            elseif ($req['status'] === 'Scheduled') $statusClass = 'badge-scheduled';
                            elseif ($req['status'] === 'In Progress') $statusClass = 'badge-progress';
                            elseif ($req['status'] === 'On Hold') $statusClass = 'badge-hold';
                            elseif (in_array($req['status'], ['Completed', 'Closed'])) $statusClass = 'badge-completed';
                            elseif ($req['status'] === 'Cancelled') $statusClass = 'badge-cancelled';

                            // Priority badge mapping
                            $priClass = 'badge-pMed';
                            if ($req['priority'] === 'Low') $priClass = 'badge-pLow';
                            elseif ($req['priority'] === 'High') $priClass = 'badge-pHigh';
                            elseif ($req['priority'] === 'Emergency') $priClass = 'badge-pEmg';
                        ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: #0f172a; font-size: 13.5px;">
                                        <a href="javascript:void(0)" onclick="openUpdateModal(<?php echo $req['id']; ?>)" style="color: #2563eb; text-decoration: none;">
                                            <?php echo htmlspecialchars($req['request_id']); ?>
                                        </a>
                                    </div>
                                    <div style="font-size: 12.5px; color: #475569; margin-top: 2px; font-weight: 500;">
                                        <?php echo htmlspecialchars($req['title'] ?: (substr($req['description'], 0, 45) . '...')); ?>
                                    </div>
                                    <div style="font-size: 11px; color: #94a3b8; margin-top: 2px;">
                                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($req['location']); ?>
                                    </div>
                                </td>

                                <td>
                                    <?php if (!empty($req['asset_name'])): ?>
                                        <div style="font-weight: 600; color: #1e293b; font-size: 13px;">
                                            <a href="assets_crud.php?search=<?php echo urlencode($req['asset_code']); ?>" style="color:inherit; text-decoration:none;" title="View Asset in Inventory">
                                                <?php echo htmlspecialchars($req['asset_name']); ?>
                                            </a>
                                        </div>
                                        <div style="font-size: 11.5px; color: #64748b;">
                                            <code><?php echo htmlspecialchars($req['asset_code']); ?></code> &bull; <span style="font-size:11px; color:#2563eb;">[<?php echo htmlspecialchars($req['asset_condition'] ?? 'Good'); ?>]</span>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-style: italic; font-size: 12px;">Unlinked / General</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span class="badge-type"><?php echo htmlspecialchars($req['maintenance_type'] ?? 'Corrective'); ?></span>
                                    <div style="font-size: 11px; color: #64748b; margin-top: 4px;">
                                        Src: <?php echo htmlspecialchars($req['source']); ?>
                                    </div>
                                </td>

                                <td>
                                    <span class="badge <?php echo $priClass; ?>">
                                        <?php if ($req['priority'] === 'Emergency'): ?><i class="fas fa-bolt"></i><?php endif; ?>
                                        <?php echo htmlspecialchars($req['priority']); ?>
                                    </span>
                                </td>

                                <td>
                                    <div style="font-size: 12.5px; font-weight: 500;">
                                        Target: <?php echo !empty($req['target_completion_date']) ? date('M d, Y', strtotime($req['target_completion_date'])) : '<span style="color:#94a3b8;">Not set</span>'; ?>
                                    </div>
                                    <?php if ($isOverdue): ?>
                                        <div class="overdue-tag">
                                            <i class="fas fa-exclamation-circle"></i> OVERDUE
                                        </div>
                                    <?php elseif (!empty($req['scheduled_start_date'])): ?>
                                        <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                                            Start: <?php echo date('M d, Y', strtotime($req['scheduled_start_date'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div style="font-size: 12.5px; font-weight: 600; color: #334155;">
                                        <?php echo htmlspecialchars($req['assigned_personnel'] ?: 'Unassigned'); ?>
                                    </div>
                                    <?php if (!empty($req['assigned_team'])): ?>
                                        <div style="font-size: 11px; color: #64748b;">
                                            Team: <?php echo htmlspecialchars($req['assigned_team']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span class="badge <?php echo $statusClass; ?>">
                                        <?php echo htmlspecialchars($req['status']); ?>
                                    </span>
                                    <div class="progress-bar-container" title="<?php echo $progress; ?>% Completed">
                                        <div class="progress-bar-fill" style="width: <?php echo $progress; ?>%;"></div>
                                    </div>
                                    <div style="font-size: 10.5px; color: #64748b; margin-top: 2px;">
                                        <?php echo $progress; ?>% complete
                                    </div>
                                </td>

                                <td style="text-align: right;">
                                    <div class="action-btn-group" style="justify-content: flex-end;">
                                        <button type="button" class="action-btn btn-update" onclick="openUpdateModal(<?php echo $req['id']; ?>)" title="Update Work Order & Progress">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="action-btn" onclick="openLogsModal(<?php echo $req['id']; ?>, '<?php echo htmlspecialchars($req['request_id']); ?>')" title="View Timeline / Audit Log">
                                            <i class="fas fa-history"></i>
                                        </button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete maintenance record <?php echo $req['request_id']; ?>?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $req['id']; ?>">
                                            <button type="submit" class="action-btn btn-del" title="Delete Task">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <div>Showing <?php echo min($totalRecords, $offset + 1); ?> - <?php echo min($totalRecords, $offset + $limit); ?> of <?php echo $totalRecords; ?> records</div>
                <div class="page-links">
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <a href="?page=<?php echo $p; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&priority=<?php echo urlencode($priority_filter); ?>&maintenance_type=<?php echo urlencode($type_filter); ?>&utility_asset_id=<?php echo $asset_filter; ?>" class="page-link <?php echo $page == $p ? 'active' : ''; ?>">
                            <?php echo $p; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
</main>

<!-- ===========================================
     CREATE TASK MODAL
=========================================== -->
<div class="modal-backdrop" id="createModal">
    <div class="modal-box modal-box-lg">
        <form method="POST">
            <input type="hidden" name="action" value="create">
            
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle" style="color: #2563eb;"></i> Register New Maintenance Task</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('createModal')">&times;</button>
            </div>

            <div class="modal-body">
                
                <div class="form-group">
                    <label>Target Utility Asset <small style="font-weight: normal; color: #64748b;">(Select registered asset)</small></label>
                    <select name="utility_asset_id" id="create_asset_id" class="form-select" onchange="autoFillAssetDetails(this)">
                        <option value="">-- No Specific Asset / General Infrastructure --</option>
                        <?php foreach ($assetsList as $ast): ?>
                            <option value="<?php echo $ast['id']; ?>" 
                                    data-location="<?php echo htmlspecialchars($ast['location']); ?>"
                                    data-condition="<?php echo htmlspecialchars($ast['condition_status']); ?>">
                                <?php echo htmlspecialchars($ast['name'] . ' (' . $ast['asset_id'] . ') - ' . $ast['location']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Work Order Title <span class="req">*</span></label>
                        <input type="text" name="title" id="create_title" class="form-control" placeholder="e.g. Pump Valve Replacement, Streetlight Rewiring" required>
                    </div>

                    <div class="form-group">
                        <label>Maintenance Type <span class="req">*</span></label>
                        <select name="maintenance_type" class="form-select" required>
                            <option value="Corrective">Corrective (Repair Broken Issue)</option>
                            <option value="Preventive">Preventive (Scheduled Servicing)</option>
                            <option value="Routine">Routine (Regular Check/Tune-up)</option>
                            <option value="Emergency">Emergency (Immediate Intervention)</option>
                            <option value="Inspection Followup">Inspection Followup</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid-3">
                    <div class="form-group">
                        <label>Priority Level <span class="req">*</span></label>
                        <select name="priority" class="form-select" required>
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                            <option value="Emergency">Emergency</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Source of Request <span class="req">*</span></label>
                        <select name="source" class="form-select" required>
                            <option value="Asset Monitoring" selected>Asset Monitoring</option>
                            <option value="Scheduled Routine">Scheduled Routine</option>
                            <option value="Inspection">Inspection</option>
                            <option value="Emergency Alert">Emergency Alert</option>
                            <option value="Resident Report">Resident Report</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Initial Status</label>
                        <select name="status" class="form-select">
                            <option value="Reported" selected>Reported</option>
                            <option value="Under Review">Under Review</option>
                            <option value="Scheduled">Scheduled</option>
                            <option value="In Progress">In Progress</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Location / Facility Address <span class="req">*</span></label>
                    <input type="text" name="location" id="create_location" class="form-control" placeholder="e.g. Brgy Hall Pump Station #2" required>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Scheduled Start Date</label>
                        <input type="datetime-local" name="scheduled_start_date" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Target Completion Date</label>
                        <input type="datetime-local" name="target_completion_date" class="form-control">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Assigned Lead Technician</label>
                        <input type="text" name="assigned_personnel" class="form-control" placeholder="Lead engineer / technician">
                    </div>

                    <div class="form-group">
                        <label>Assigned Team / Department</label>
                        <input type="text" name="assigned_team" class="form-control" placeholder="e.g. Electrical Crew 1">
                    </div>
                </div>

                <div class="form-group">
                    <label>Description & Defect Details</label>
                    <textarea name="description" id="create_desc" class="form-control" rows="3" placeholder="Provide diagnostic details, symptoms, or observed issues..."></textarea>
                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Create Work Order</button>
            </div>
        </form>
    </div>
</div>

<!-- ===========================================
     UPDATE STATUS & PROGRESS MODAL
=========================================== -->
<div class="modal-backdrop" id="updateModal">
    <div class="modal-box modal-box-lg">
        <form method="POST">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="id" id="update_id">
            
            <div class="modal-header">
                <h3><i class="fas fa-wrench" style="color: #2563eb;"></i> Update Work Order Progress</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('updateModal')">&times;</button>
            </div>

            <div class="modal-body">
                
                <div style="background: #f1f5f9; padding: 12px 16px; border-radius: 10px; margin-bottom: 18px;" id="update_task_box">
                    <div style="font-weight: 700; color: #0f172a;" id="update_task_ref">MNT-XXXX</div>
                    <div style="font-size: 13px; color: #475569;" id="update_task_title">Task Title</div>
                </div>

                <div class="form-grid-3">
                    <div class="form-group">
                        <label>Current Status <span class="req">*</span></label>
                        <select name="status" id="update_status" class="form-select" onchange="syncProgressWithStatus(this.value)">
                            <option value="Reported">Reported</option>
                            <option value="Under Review">Under Review</option>
                            <option value="Scheduled">Scheduled</option>
                            <option value="In Progress">In Progress</option>
                            <option value="On Hold">On Hold</option>
                            <option value="Completed">Completed (Restores Asset to Operational)</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Progress Completion (%) <span class="req">*</span></label>
                        <input type="number" name="progress_percent" id="update_progress_percent" class="form-control" min="0" max="100" value="0">
                    </div>

                    <div class="form-group">
                        <label>Actual Completion Date</label>
                        <input type="datetime-local" name="actual_completion_date" id="update_actual_completion_date" class="form-control">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Scheduled Start Date</label>
                        <input type="datetime-local" name="scheduled_start_date" id="update_scheduled_start_date" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Target Completion Date</label>
                        <input type="datetime-local" name="target_completion_date" id="update_target_completion_date" class="form-control">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Assigned Lead Technician</label>
                        <input type="text" name="assigned_personnel" id="update_assigned_personnel" class="form-control" placeholder="Lead technician">
                    </div>

                    <div class="form-group">
                        <label>Assigned Crew / Team</label>
                        <input type="text" name="assigned_team" id="update_assigned_team" class="form-control" placeholder="e.g. Electrical Crew 2">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Technical Findings / Diagnostics</label>
                        <textarea name="findings" id="update_findings" class="form-control" rows="2" placeholder="Describe inspection findings or physical measurements..."></textarea>
                    </div>

                    <div class="form-group">
                        <label>Root Cause Analysis</label>
                        <textarea name="root_cause" id="update_root_cause" class="form-control" rows="2" placeholder="Wear and tear, voltage surge, pipe corrosion, clogging, etc."></textarea>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Corrective Action Taken</label>
                        <textarea name="action_taken" id="update_action_taken" class="form-control" rows="2" placeholder="Specific repairs, adjustments, or re-alignments performed..."></textarea>
                    </div>

                    <div class="form-group">
                        <label>Parts / Materials Replaced</label>
                        <textarea name="parts_replaced" id="update_parts_replaced" class="form-control" rows="2" placeholder="List valves, cables, breakers, fittings, or seals installed..."></textarea>
                    </div>
                </div>

                <div class="form-group">
                    <label>Status Update Note / Timeline Log <small>(Recorded in audit trail)</small></label>
                    <input type="text" name="status_note" class="form-control" placeholder="Brief note on what was accomplished in this update...">
                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('updateModal')">Cancel</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Save Work Order Updates</button>
            </div>
        </form>
    </div>
</div>

<!-- ===========================================
     AUDIT LOG / TIMELINE MODAL
=========================================== -->
<div class="modal-backdrop" id="logsModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fas fa-history" style="color: #2563eb;"></i> Timeline & Audit Trail</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('logsModal')">&times;</button>
        </div>

        <div class="modal-body">
            <div style="font-size: 13px; color: #64748b; margin-bottom: 16px;">
                Task Ref: <strong id="logs_task_ref" style="color: #0f172a;"></strong>
            </div>

            <div id="logsLoading" style="text-align: center; padding: 20px; color: #94a3b8;">
                <i class="fas fa-spinner fa-spin" style="font-size: 24px;"></i>
                <p style="margin-top: 8px;">Loading audit logs...</p>
            </div>

            <div class="timeline-list" id="timelineList" style="display: none;"></div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('logsModal')">Close</button>
        </div>
    </div>
</div>

<script>
    function openModal(id) {
        document.getElementById(id).classList.add('active');
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
    }

    function openCreateModal() {
        openModal('createModal');
    }

    function autoFillAssetDetails(selectElem) {
        const selectedOption = selectElem.options[selectElem.selectedIndex];
        if (selectedOption && selectedOption.value) {
            const loc = selectedOption.getAttribute('data-location');
            if (loc) {
                document.getElementById('create_location').value = loc;
            }
        }
    }

    function syncProgressWithStatus(status) {
        const progInput = document.getElementById('update_progress_percent');
        const compDateInput = document.getElementById('update_actual_completion_date');
        
        if (status === 'Completed') {
            progInput.value = 100;
            if (!compDateInput.value) {
                const now = new Date();
                const pad = n => String(n).padStart(2, '0');
                compDateInput.value = now.getFullYear() + '-' + pad(now.getMonth()+1) + '-' + pad(now.getDate()) + 'T' + pad(now.getHours()) + ':' + pad(now.getMinutes());
            }
        } else if (status === 'In Progress' && parseInt(progInput.value) === 0) {
            progInput.value = 25;
        } else if (status === 'Reported' || status === 'Under Review') {
            if (parseInt(progInput.value) > 20) {
                progInput.value = 0;
            }
        }
    }

    // Open Update Modal and pre-populate via AJAX
    function openUpdateModal(id) {
        fetch(`maintenance_list.php?fetch_record_id=${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    alert(data.error);
                    return;
                }

                document.getElementById('update_id').value = data.id;
                document.getElementById('update_task_ref').innerHTML = `${data.request_id} &bull; ${data.priority} Priority`;
                document.getElementById('update_task_title').innerText = data.title || data.description;
                document.getElementById('update_status').value = data.status || 'Reported';
                document.getElementById('update_progress_percent').value = data.progress_percent || 0;
                document.getElementById('update_assigned_personnel').value = data.assigned_personnel || '';
                document.getElementById('update_assigned_team').value = data.assigned_team || '';
                document.getElementById('update_findings').value = data.findings || '';
                document.getElementById('update_root_cause').value = data.root_cause || '';
                document.getElementById('update_action_taken').value = data.action_taken || '';
                document.getElementById('update_parts_replaced').value = data.parts_replaced || '';

                if (data.scheduled_start_date) {
                    document.getElementById('update_scheduled_start_date').value = data.scheduled_start_date.replace(' ', 'T').slice(0, 16);
                } else {
                    document.getElementById('update_scheduled_start_date').value = '';
                }

                if (data.target_completion_date) {
                    document.getElementById('update_target_completion_date').value = data.target_completion_date.replace(' ', 'T').slice(0, 16);
                } else {
                    document.getElementById('update_target_completion_date').value = '';
                }

                if (data.actual_completion_date) {
                    document.getElementById('update_actual_completion_date').value = data.actual_completion_date.replace(' ', 'T').slice(0, 16);
                } else {
                    document.getElementById('update_actual_completion_date').value = '';
                }

                openModal('updateModal');
            })
            .catch(err => {
                console.error(err);
                alert('Failed to load record details.');
            });
    }

    // Open Logs Timeline Modal via AJAX
    function openLogsModal(id, requestRef) {
        document.getElementById('logs_task_ref').innerText = requestRef;
        document.getElementById('logsLoading').style.display = 'block';
        document.getElementById('timelineList').style.display = 'none';
        document.getElementById('timelineList').innerHTML = '';

        openModal('logsModal');

        fetch(`maintenance_list.php?fetch_logs_id=${id}`)
            .then(res => res.json())
            .then(data => {
                document.getElementById('logsLoading').style.display = 'none';
                const list = document.getElementById('timelineList');
                list.style.display = 'block';

                if (!data || data.length === 0) {
                    list.innerHTML = '<p style="color: #94a3b8; font-style: italic;">No audit log events recorded yet.</p>';
                    return;
                }

                let html = '';
                data.forEach(item => {
                    const dateStr = new Date(item.changed_at).toLocaleString();
                    const statusText = item.old_status ? `${item.old_status} &rarr; <strong>${item.new_status}</strong>` : `<strong>${item.new_status}</strong>`;
                    html += `
                        <div class="timeline-item">
                            <div class="timeline-bullet"></div>
                            <div class="timeline-content">
                                <div class="timeline-meta">
                                    <span><i class="fas fa-user"></i> ${item.user_name}</span>
                                    <span>${dateStr}</span>
                                </div>
                                <div style="font-weight: 600; color: #1e293b; margin-bottom: 2px;">
                                    ${statusText}
                                </div>
                                ${item.notes ? `<div style="color: #64748b; font-size: 12px; margin-top: 4px;">${item.notes}</div>` : ''}
                            </div>
                        </div>
                    `;
                });
                list.innerHTML = html;
            })
            .catch(err => {
                console.error(err);
                document.getElementById('logsLoading').innerHTML = '<p style="color: #ef4444;">Failed to load timeline.</p>';
            });
    }

    // Auto-open update modal if passed in URL
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const autoOpenId = urlParams.get('open_modal_id');
        if (autoOpenId) {
            openUpdateModal(parseInt(autoOpenId));
        }
    });
</script>

</body>
</html>
