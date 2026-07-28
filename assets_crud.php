<?php
// assets_crud.php
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Self-healing database repair for missing PRIMARY KEY or AUTO_INCREMENT on assets tables
function ensureAssetsSchema($pdo) {
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

    $needsTypeRepair = false;
    try {
        $col = $pdo->query("SHOW COLUMNS FROM asset_types LIKE 'id'")->fetch(PDO::FETCH_ASSOC);
        if ($col && stripos((string)($col['Extra'] ?? ''), 'auto_increment') === false) {
            $needsTypeRepair = true;
        }
    } catch (Throwable $e) {
        $needsTypeRepair = true;
    }

    $needsAssetRepair = false;
    try {
        $col = $pdo->query("SHOW COLUMNS FROM utility_assets LIKE 'id'")->fetch(PDO::FETCH_ASSOC);
        if ($col && stripos((string)($col['Extra'] ?? ''), 'auto_increment') === false) {
            $needsAssetRepair = true;
        }
    } catch (Throwable $e) {
        $needsAssetRepair = true;
    }

    // Repair secondary tables if they are missing auto_increment
    $repairTable($pdo, 'asset_status_logs');
    $repairTable($pdo, 'asset_notifications');
    $repairTable($pdo, 'asset_images');
    $repairTable($pdo, 'asset_locations');

    if (!$needsTypeRepair && !$needsAssetRepair) {
        return; // Schema is already correct
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("SET SESSION sql_mode = REPLACE(@@sql_mode, 'NO_AUTO_VALUE_ON_ZERO', '')");

    if ($needsTypeRepair) {
        $types = $pdo->query("SELECT * FROM asset_types")->fetchAll();
        $pdo->exec("TRUNCATE TABLE asset_types");
        
        $typeIdMap = [];
        $nextId = 1;
        foreach ($types as $t) {
            $name = trim($t['name']);
            if (isset($typeIdMap[$name])) {
                continue;
            }
            $pdo->prepare("INSERT INTO asset_types (id, name, description, created_at) VALUES (?, ?, ?, ?)")
                ->execute([$nextId, $name, $t['description'] ?? null, $t['created_at'] ?? date('Y-m-d H:i:s')]);
            $typeIdMap[$name] = $nextId;
            $nextId++;
        }
        
        try {
            $pdo->exec("ALTER TABLE asset_types ADD PRIMARY KEY (id)");
        } catch (Throwable $ignored) {}
        
        try {
            $pdo->exec("ALTER TABLE asset_types MODIFY id INT NOT NULL AUTO_INCREMENT");
        } catch (Throwable $ignored) {}
    } else {
        $typeIdMap = $pdo->query("SELECT name, id FROM asset_types")->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    if ($needsAssetRepair) {
        $assets = $pdo->query("SELECT * FROM utility_assets")->fetchAll();
        $pdo->exec("TRUNCATE TABLE utility_assets");
        
        try {
            $pdo->exec("ALTER TABLE utility_assets ADD PRIMARY KEY (id)");
        } catch (Throwable $ignored) {}
        
        try {
            $pdo->exec("ALTER TABLE utility_assets MODIFY id INT NOT NULL AUTO_INCREMENT");
        } catch (Throwable $ignored) {}
        
        $assetIdMap = [];
        $nextAssetId = 1;
        foreach ($assets as $idx => $a) {
            $matchedTypeId = 1;
            $assetName = $a['name'];
            foreach ($typeIdMap as $typeName => $typeId) {
                if (stripos($assetName, $typeName) !== false) {
                    $matchedTypeId = $typeId;
                    break;
                }
            }
            if ($matchedTypeId === 1) {
                if (stripos($assetName, 'Drainage') !== false) {
                    $matchedTypeId = $typeIdMap['Drainage System'] ?? 1;
                } elseif (stripos($assetName, 'Pipeline') !== false || stripos($assetName, 'Water') !== false) {
                    $matchedTypeId = $typeIdMap['Water Pipeline'] ?? 1;
                } elseif (stripos($assetName, 'Pole') !== false || stripos($assetName, 'Electrical') !== false) {
                    $matchedTypeId = $typeIdMap['Electrical Utility Pole'] ?? 1;
                } elseif (stripos($assetName, 'Pump') !== false || stripos($assetName, 'Reservoir') !== false) {
                    $matchedTypeId = $typeIdMap['Public Utility Infrastructure'] ?? 1;
                }
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO utility_assets (id, asset_id, name, asset_type_id, quantity, location, latitude, longitude, date_installed, condition_status, description, responsible_office, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $nextAssetId,
                $a['asset_id'],
                $a['name'],
                $matchedTypeId,
                $a['quantity'] ?? 1,
                $a['location'],
                $a['latitude'] ?? null,
                $a['longitude'] ?? null,
                $a['date_installed'],
                $a['condition_status'],
                $a['description'] ?? null,
                $a['responsible_office'] ?? null,
                $a['created_at'] ?? date('Y-m-d H:i:s'),
                $a['updated_at'] ?? date('Y-m-d H:i:s')
            ]);
            
            $assetIdMap[$idx] = $nextAssetId;
            $nextAssetId++;
        }
        
        try {
            $logs = $pdo->query("SELECT id, utility_asset_id FROM asset_status_logs ORDER BY id ASC")->fetchAll();
            if (count($logs) === count($assetIdMap)) {
                $i = 0;
                foreach ($assetIdMap as $idx => $newId) {
                    if (isset($logs[$i])) {
                        $pdo->prepare("UPDATE asset_status_logs SET utility_asset_id = ? WHERE id = ?")
                            ->execute([$newId, $logs[$i]['id']]);
                    }
                    $i++;
                }
            } else {
                $pdo->prepare("UPDATE asset_status_logs SET utility_asset_id = 1 WHERE utility_asset_id = 0")->execute();
            }
        } catch (Throwable $e) {}
        
        try { $pdo->exec("UPDATE asset_locations SET utility_asset_id = 1 WHERE utility_asset_id = 0"); } catch (Throwable $e) {}
        try { $pdo->exec("UPDATE asset_images SET utility_asset_id = 1 WHERE utility_asset_id = 0"); } catch (Throwable $e) {}
        try { $pdo->exec("UPDATE incident_asset_links SET utility_asset_id = 1 WHERE utility_asset_id = 0"); } catch (Throwable $e) {}
        try { $pdo->exec("UPDATE maintenance_asset_links SET utility_asset_id = 1 WHERE utility_asset_id = 0"); } catch (Throwable $e) {}
        try { $pdo->exec("UPDATE maintenance_requests SET utility_asset_id = 1 WHERE utility_asset_id = 0"); } catch (Throwable $e) {}
        try { $pdo->exec("UPDATE energy_consumption_records SET utility_asset_id = 1 WHERE utility_asset_id = 0"); } catch (Throwable $e) {}
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
}

ensureAssetsSchema($pdo);

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$userType = $_SESSION['user_type'] ?? '';
$userId = $_SESSION['user_id'] ?? 1;

$error = '';
$success = '';

// Handle CRUD Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Handle File Upload helper
    function handleImageUpload($file) {
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        $targetDir = 'uploads/assets/';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($fileExtension, $allowedExtensions)) {
            return null;
        }
        $fileName = uniqid('asset_', true) . '.' . $fileExtension;
        $targetFilePath = $targetDir . $fileName;
        if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {
            return $targetFilePath;
        }
        return null;
    }

    function generateCategoryPrefix($categoryName) {
        $name = strtoupper(trim($categoryName));
        $name = preg_replace('/[^A-Z0-9\s]/', '', $name);
        $words = preg_split('/\s+/', $name);
        if (count($words) > 1) {
            $prefix = '';
            foreach ($words as $word) {
                $prefix .= substr($word, 0, 1);
            }
        } else {
            $consonants = preg_replace('/[AEIOU]/', '', $name);
            if (strlen($consonants) >= 3) {
                $prefix = substr($consonants, 0, 3);
            } else {
                $prefix = substr($name, 0, 3);
            }
        }
        $prefix = preg_replace('/[^A-Z0-9]/', '', $prefix);
        if (strlen($prefix) < 2) {
            $prefix = substr(preg_replace('/[^A-Z0-9]/', '', $name), 0, 3);
        }
        return $prefix;
    }

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $asset_type_id = $_POST['asset_type_id'] ?? '';
        $new_category_name = trim($_POST['new_category_name'] ?? '');
        $quantity = max(1, intval($_POST['quantity'] ?? 1));
        $location = trim($_POST['location'] ?? '');
        $latitude = null;
        $longitude = null;
        $date_installed = $_POST['date_installed'] ?? date('Y-m-d');
        $condition_status = $_POST['condition_status'] ?? 'Operational';
        $description = trim($_POST['description'] ?? '');
        $responsible_office = trim($_POST['responsible_office'] ?? '');

        if ($asset_type_id === 'new' && !empty($new_category_name)) {
            try {
                $stmt = $pdo->prepare("SELECT id FROM asset_types WHERE name = ?");
                $stmt->execute([$new_category_name]);
                $existing = $stmt->fetch();
                if ($existing) {
                    $asset_type_id = intval($existing['id']);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO asset_types (name) VALUES (?)");
                    $stmt->execute([$new_category_name]);
                    $asset_type_id = intval($pdo->lastInsertId());
                }
            } catch (PDOException $e) {
                $error = "Failed to create category: " . $e->getMessage();
            }
        } else {
            $asset_type_id = intval($asset_type_id);
        }

        if (empty($name) || empty($location) || $asset_type_id <= 0) {
            $error = 'Please fill in all required fields (Name, Type, Location).';
        } else {
            try {
                // Fetch the category name to generate the unique prefix
                $categoryName = '';
                if ($new_category_name !== '' && $_POST['asset_type_id'] === 'new') {
                    $categoryName = $new_category_name;
                } else {
                    $cStmt = $pdo->prepare("SELECT name FROM asset_types WHERE id = ?");
                    $cStmt->execute([$asset_type_id]);
                    $categoryName = $cStmt->fetchColumn() ?: 'AST';
                }

                $prefix = generateCategoryPrefix($categoryName) . '-';
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM utility_assets WHERE asset_id LIKE ?");
                $stmt->execute([$prefix . '%']);
                $count = $stmt->fetchColumn() + 1;
                $asset_id = $prefix . str_pad($count, 4, '0', STR_PAD_LEFT);

                // Insert Asset
                $stmt = $pdo->prepare("
                    INSERT INTO utility_assets (asset_id, name, asset_type_id, quantity, location, latitude, longitude, date_installed, condition_status, description, responsible_office)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$asset_id, $name, $asset_type_id, $quantity, $location, $latitude, $longitude, $date_installed, $condition_status, $description, $responsible_office]);
                $id = $pdo->lastInsertId();

                // Handle Image Upload if any
                $imagePath = handleImageUpload($_FILES['image'] ?? null);
                if ($imagePath) {
                    $pdo->prepare("INSERT INTO asset_images (utility_asset_id, image_path) VALUES (?, ?)")->execute([$id, $imagePath]);
                }

                // Log Status Log
                $pdo->prepare("
                    INSERT INTO asset_status_logs (utility_asset_id, old_status, new_status, changed_by, notes) 
                    VALUES (?, NULL, ?, ?, 'Asset registered in system.')
                ")->execute([$id, $condition_status, $userId]);

                // Create alert notification
                $pdo->prepare("
                    INSERT INTO asset_notifications (type, message) 
                    VALUES ('asset_created', ?)
                ")->execute(["New asset registered: {$asset_id} ({$name}) in {$location}."]);

                $success = "Asset {$asset_id} successfully created!";
            } catch (PDOException $e) {
                $error = "Failed to create asset: " . $e->getMessage();
            }
        }
    } elseif ($action === 'update') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $asset_type_id = $_POST['asset_type_id'] ?? '';
        $new_category_name = trim($_POST['new_category_name'] ?? '');
        $quantity = max(1, intval($_POST['quantity'] ?? 1));
        $location = trim($_POST['location'] ?? '');
        $date_installed = $_POST['date_installed'] ?? date('Y-m-d');
        $condition_status = $_POST['condition_status'] ?? 'Operational';
        $description = trim($_POST['description'] ?? '');
        $responsible_office = trim($_POST['responsible_office'] ?? '');
        $status_notes = trim($_POST['status_notes'] ?? '');

        if ($asset_type_id === 'new' && !empty($new_category_name)) {
            try {
                $stmt = $pdo->prepare("SELECT id FROM asset_types WHERE name = ?");
                $stmt->execute([$new_category_name]);
                $existing = $stmt->fetch();
                if ($existing) {
                    $asset_type_id = intval($existing['id']);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO asset_types (name) VALUES (?)");
                    $stmt->execute([$new_category_name]);
                    $asset_type_id = intval($pdo->lastInsertId());
                }
            } catch (PDOException $e) {
                $error = "Failed to create category: " . $e->getMessage();
            }
        } else {
            $asset_type_id = intval($asset_type_id);
        }

        if ($id <= 0 || empty($name) || empty($location) || $asset_type_id <= 0) {
            $error = 'Please fill in all required fields (Name, Type, Location).';
        } else {
            try {
                // Get current status & location to log changes
                $curr = $pdo->prepare("SELECT asset_id, condition_status, location, latitude, longitude FROM utility_assets WHERE id = ?");
                $curr->execute([$id]);
                $oldAsset = $curr->fetch();

                if ($oldAsset) {
                    $asset_id = $oldAsset['asset_id'];
                    $latitude = $oldAsset['latitude'];
                    $longitude = $oldAsset['longitude'];

                    // Update core details
                    $stmt = $pdo->prepare("
                        UPDATE utility_assets 
                        SET name = ?, asset_type_id = ?, quantity = ?, location = ?, latitude = ?, longitude = ?, date_installed = ?, condition_status = ?, description = ?, responsible_office = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $asset_type_id, $quantity, $location, $latitude, $longitude, $date_installed, $condition_status, $description, $responsible_office, $id]);

                    // Check and log status changes
                    if ($oldAsset['condition_status'] !== $condition_status) {
                        $pdo->prepare("
                            INSERT INTO asset_status_logs (utility_asset_id, old_status, new_status, changed_by, notes) 
                            VALUES (?, ?, ?, ?, ?)
                        ")->execute([$id, $oldAsset['condition_status'], $condition_status, $userId, $status_notes ?: 'Status modified by administrator.']);

                        // Trigger notifications
                        $notifType = ($condition_status === 'Damaged') ? 'reported_damaged' : 'status_changed';
                        $pdo->prepare("
                            INSERT INTO asset_notifications (type, message) 
                            VALUES (?, ?)
                        ")->execute([$notifType, "Asset {$asset_id} status changed from {$oldAsset['condition_status']} to {$condition_status}."]);
                    }

                    // Check and log location changes
                    if ($oldAsset['location'] !== $location) {
                        $pdo->prepare("
                            INSERT INTO asset_locations (utility_asset_id, old_location, new_location, old_latitude, new_latitude, old_longitude, new_longitude, changed_by) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ")->execute([$id, $oldAsset['location'], $location, $oldAsset['latitude'], $latitude, $oldAsset['longitude'], $longitude, $userId]);
                    }

                    // Handle Image Upload if any
                    $imagePath = handleImageUpload($_FILES['image'] ?? null);
                    if ($imagePath) {
                        $pdo->prepare("INSERT INTO asset_images (utility_asset_id, image_path) VALUES (?, ?)")->execute([$id, $imagePath]);
                    }

                    $success = "Asset {$asset_id} updated successfully!";
                }
            } catch (PDOException $e) {
                $error = "Failed to update asset: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $curr = $pdo->prepare("SELECT asset_id, name FROM utility_assets WHERE id = ?");
                $curr->execute([$id]);
                $asset = $curr->fetch();
                
                if ($asset) {
                    $pdo->prepare("DELETE FROM utility_assets WHERE id = ?")->execute([$id]);
                    $success = "Asset {$asset['asset_id']} ({$asset['name']}) has been successfully deleted.";
                }
            } catch (PDOException $e) {
                $error = "Failed to delete asset: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete_category') {
        $category_id = intval($_POST['category_id'] ?? 0);
        if ($category_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM asset_types WHERE id = ?");
                $stmt->execute([$category_id]);
                $success = "Category successfully deleted.";
            } catch (PDOException $e) {
                $error = "Cannot delete category: it is currently assigned to one or more utility assets.";
            }
        }
    }
}

// ------------------------------------------------------------------------
// Get Search / Filter / Pagination parameters
// ------------------------------------------------------------------------
$search = trim($_GET['search'] ?? '');
$type_filter = !empty($_GET['type_id']) ? intval($_GET['type_id']) : null;
$status_filter = !empty($_GET['status']) ? trim($_GET['status']) : null;

// Pagination configuration
$limit = 10;
$page = !empty($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(a.name LIKE ? OR a.asset_id LIKE ? OR a.location LIKE ?)";
    $searchWildcard = '%' . $search . '%';
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
}

if ($type_filter) {
    $conditions[] = "a.asset_type_id = ?";
    $params[] = $type_filter;
}

if ($status_filter) {
    $conditions[] = "a.condition_status = ?";
    $params[] = $status_filter;
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Retrieve count for pagination
$countQuery = "SELECT COUNT(*) FROM utility_assets a $whereClause";
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Retrieve Assets List
$query = "
    SELECT a.*, t.name as type_name, img.image_path
    FROM utility_assets a 
    JOIN asset_types t ON a.asset_type_id = t.id 
    LEFT JOIN (
        SELECT utility_asset_id, MAX(image_path) as image_path 
        FROM asset_images 
        GROUP BY utility_asset_id
    ) img ON a.id = img.utility_asset_id
    $whereClause
    ORDER BY a.asset_id ASC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$assetsList = $stmt->fetchAll();

// Retrieve all asset types for form selectors
$assetTypes = $pdo->query("SELECT * FROM asset_types ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Utility Assets</title>
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        /* Review changes modal and comparison table CSS */
        .review-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .review-table th, .review-table td {
            padding: 12px 10px;
            font-size: 13px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        .review-field-name {
            font-weight: 600;
            color: #334155;
            width: 120px;
        }
        .review-old-val {
            color: #ef4444;
            text-decoration: line-through;
            background-color: #fef2f2;
            padding: 4px 8px;
            border-radius: 4px;
            word-break: break-word;
        }
        .review-new-val {
            color: #16a34a;
            background-color: #f0fdf4;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 500;
            word-break: break-word;
        }

        /* Custom Searchable Dropdown CSS */
        .custom-select-container {
            position: relative;
            width: 100%;
        }
        .custom-select-options {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            max-height: 160px; /* displays approximately 4 options */
            overflow-y: auto;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            z-index: 1000;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
            margin-top: 4px;
        }
        .custom-select-options.open {
            display: block;
        }
        .custom-option {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 14px;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
            color: #334155;
            transition: background-color 0.15s ease;
        }
        .custom-option:hover {
            background-color: #f1f5f9;
            color: #0f172a;
        }
        .custom-option:last-child {
            border-bottom: none;
        }
        .delete-category-btn {
            background: none;
            border: none;
            color: #ef4444;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            padding: 2px 6px;
            line-height: 1;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.15s ease, color 0.15s ease;
        }
        .delete-category-btn:hover {
            background-color: #fee2e2;
            color: #b91c1c;
        }



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
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            backdrop-filter: blur(6px);
            background: rgba(0, 0, 0, 0.35);
            z-index: 0;
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
            margin-left: 90px;
        }

        .card {
            width: 100%;
            max-width: 1700px;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(15px);
            border-radius: 18px;
            padding: 40px;
            color: #000;
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,0.25);
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .dashboard-header h1 {
            color: #2c3e50;
            font-size: 32px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .dashboard-header h1 i {
            color: #3762c8;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background-color: #fde8e8;
            color: #c0392b;
            border: 1px solid #f8b4b4;
        }

        .alert-success {
            background-color: #e2fbe8;
            color: #1e7e34;
            border: 1px solid #b8f0c5;
        }

        /* Filter Box */
        .filter-container {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex: 1;
            min-width: 180px;
        }

        .form-group label {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
        }

        .form-control {
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 14px;
            outline: none;
            transition: border 0.3s;
        }

        .form-control:focus {
            border-color: #3762c8;
        }

        .btn {
            padding: 11px 22px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary { background: #3762c8; color: white; }
        .btn-primary:hover { background: #2851b0; }

        .btn-danger { background: #e74c3c; color: white; }
        .btn-danger:hover { background: #c0392b; }

        .btn-outline { background: transparent; border: 1px solid #cbd5e1; color: #64748b; }
        .btn-outline:hover { background: #f8f9fa; color: #2c3e50; }

        /* Table & Lists */
        .table-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .table-container {
            overflow-x: auto;
            width: 100%;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th {
            background: #f8f9fa;
            color: #475569;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            padding: 12px 16px;
            border-bottom: 2px solid #e2e8f0;
        }

        td {
            padding: 14px 16px;
            border-bottom: 1px solid #edf2f7;
            font-size: 14px;
            color: #2c3e50;
        }

        tr:hover td {
            background: #fcfcfc;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 99px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-operational { background: #e2fbe8; color: #1e7e34; }
        .badge-inspection { background: #fef9e7; color: #d39e00; }
        .badge-damaged { background: #fde8e8; color: #bd2130; }
        .badge-maintenance { background: #f3e5f5; color: #7b1fa2; }

        .btn-icon {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-icon-view { background: #e0f2fe; color: #0284c7; }
        .btn-icon-view:hover { background: #bae6fd; }

        .btn-icon-edit { background: #fef9e7; color: #d39e00; }
        .btn-icon-edit:hover { background: #fef3c7; }

        .btn-icon-delete { background: #fde8e8; color: #bd2130; }
        .btn-icon-delete:hover { background: #fecaca; }

        /* Pagination */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
        }

        .pagination-info {
            font-size: 13px;
            color: #64748b;
        }

        .pagination-links {
            display: flex;
            gap: 6px;
        }

        .page-link {
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            text-decoration: none;
            color: #64748b;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .page-link:hover {
            border-color: #3762c8;
            color: #3762c8;
            background: #f8fafc;
        }

        .page-link.active {
            background: #3762c8;
            color: white;
            border-color: #3762c8;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(4px);
        }

        .modal.open {
            display: flex;
        }

        .modal-content {
            background: white;
            width: 90%;
            max-width: 650px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            animation: modalFadeIn 0.3s ease;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-header {
            padding: 20px 24px;
            background: #f8f9fa;
            border-bottom: 1px solid #edf2f7;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            font-size: 18px;
            color: #2c3e50;
        }

        .modal-close {
            background: transparent;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: #64748b;
        }

        .modal-body {
            padding: 24px;
            max-height: 70vh;
            overflow-y: auto;
        }

        .modal-footer {
            padding: 16px 24px;
            background: #f8f9fa;
            border-top: 1px solid #edf2f7;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        @media (max-width: 600px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }
    </style>
</head>
<body>

<?php include 'includes/utilities_sidebar.php'; ?>

<main class="main-content" id="mainContent">
    <div class="card">
        
        <!-- Header -->
        <div class="dashboard-header">
            <div>
                <h1><i class="fas fa-boxes"></i> Utility Asset CRUD Management</h1>
                <p style="color: #64748b; font-size: 14px; margin-top: 5px;">Register, edit, view, or remove LGU utility assets records.</p>
            </div>
            <div>
                <button class="btn btn-primary" onclick="openAddModal()"><i class="fas fa-plus"></i> Register Asset</button>
                <a href="assets_dashboard.php" class="btn btn-outline"><i class="fas fa-chevron-left"></i> Dashboard</a>
            </div>
        </div>

        <!-- Alert messages -->
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Filters Form -->
        <form method="GET" class="filter-container">
            <div class="form-group" style="flex: 2;">
                <label>Search Asset</label>
                <input type="text" name="search" class="form-control" placeholder="Search by name, ID, or location..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="form-group" style="position:relative;">
                <label>Category</label>
                <?php
                $selectedCategoryName = 'All Categories';
                if ($type_filter) {
                    foreach ($assetTypes as $type) {
                        if ($type['id'] == $type_filter) {
                            $selectedCategoryName = $type['name'];
                            break;
                        }
                    }
                }
                ?>
                <div class="custom-select-container">
                    <input type="text" id="category-search-input" class="form-control" placeholder="Search category..." autocomplete="off" value="<?php echo htmlspecialchars($selectedCategoryName); ?>">
                    <input type="hidden" name="type_id" id="category-type-id" value="<?php echo htmlspecialchars($type_filter ?? ''); ?>">
                    <div class="custom-select-options" id="category-options-list">
                        <div class="custom-option" data-value="" data-name="All Categories">
                            <span>All Categories</span>
                        </div>
                        <?php foreach ($assetTypes as $type): ?>
                            <div class="custom-option" data-value="<?php echo $type['id']; ?>" data-name="<?php echo htmlspecialchars($type['name']); ?>">
                                <span><?php echo htmlspecialchars($type['name']); ?></span>
                                <button type="button" class="delete-category-btn" title="Delete Category" onclick="deleteCategory(event, <?php echo $type['id']; ?>, '<?php echo htmlspecialchars(addslashes($type['name'])); ?>')">&times;</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="">All Statuses</option>
                    <option value="Operational" <?php echo $status_filter === 'Operational' ? 'selected' : ''; ?>>Operational</option>
                    <option value="Needs Inspection" <?php echo $status_filter === 'Needs Inspection' ? 'selected' : ''; ?>>Needs Inspection</option>
                    <option value="Damaged" <?php echo $status_filter === 'Damaged' ? 'selected' : ''; ?>>Damaged</option>
                    <option value="Under Maintenance" <?php echo $status_filter === 'Under Maintenance' ? 'selected' : ''; ?>>Under Maintenance</option>
                </select>
            </div>

            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
                <a href="assets_crud.php" class="btn btn-outline">Reset</a>
            </div>
        </form>

        <!-- Data Table Section -->
        <div class="table-section">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Asset ID</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Qty</th>
                            <th>Installed</th>
                            <th>Status</th>
                            <th>Location</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($assetsList)): ?>
                            <tr><td colspan="8" style="text-align: center; padding: 30px; color: #64748b;">No assets found matching filters.</td></tr>
                        <?php else: ?>
                            <?php foreach ($assetsList as $asset): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($asset['asset_id']); ?></strong></td>
                                <td><?php echo htmlspecialchars($asset['name']); ?></td>
                                <td><?php echo htmlspecialchars($asset['type_name']); ?></td>
                                <td><?php echo htmlspecialchars($asset['quantity']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($asset['date_installed'])); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo strtolower(str_replace(' ', '', $asset['condition_status'])); ?>">
                                        <?php echo htmlspecialchars($asset['condition_status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($asset['location']); ?></td>
                                <td style="text-align: right;">
                                    <button class="btn-icon btn-icon-view" onclick='viewAsset(<?php echo json_encode($asset); ?>)' title="View Details"><i class="fas fa-eye"></i></button>
                                    <button class="btn-icon btn-icon-edit" onclick='editAsset(<?php echo json_encode($asset); ?>)' title="Edit Asset"><i class="fas fa-edit"></i></button>
                                    <button class="btn-icon btn-icon-delete" onclick="confirmDelete(<?php echo $asset['id']; ?>, '<?php echo htmlspecialchars($asset['asset_id']); ?>')" title="Delete"><i class="fas fa-trash-alt"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Row -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination-container">
                <div class="pagination-info">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($totalRecords, $offset + $limit); ?> of <?php echo $totalRecords; ?> assets
                </div>
                <div class="pagination-links">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="assets_crud.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&type_id=<?php echo $type_filter; ?>&status=<?php echo urlencode($status_filter); ?>" class="page-link <?php echo $page == $i ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<!-- ADD ASSET MODAL -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Register New Utility Asset</h3>
            <button class="modal-close" onclick="closeModal('addModal')">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label>Asset Name *</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Taft Avenue solar streetlight 03">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Quantity *</label>
                        <input type="number" name="quantity" class="form-control" required min="1" value="1">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Asset Type / Category *</label>
                        <select name="asset_type_id" id="add-type-id" class="form-control" required onchange="toggleNewCategory(this, 'add-new-category-wrapper')">
                            <option value="">Select Category</option>
                            <?php foreach ($assetTypes as $type): ?>
                                <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['name']); ?></option>
                            <?php endforeach; ?>
                            <option value="new">+ Create New Category...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Condition Status *</label>
                        <select name="condition_status" class="form-control" required>
                            <option value="Operational">Operational</option>
                            <option value="Needs Inspection">Needs Inspection</option>
                            <option value="Damaged">Damaged</option>
                            <option value="Under Maintenance">Under Maintenance</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" id="add-new-category-wrapper" style="display:none; margin-bottom:15px;">
                    <label>New Category Name *</label>
                    <input type="text" name="new_category_name" class="form-control" placeholder="Type new category name...">
                </div>

                <div class="form-group" style="margin-bottom:15px;">
                    <label>Installation Date *</label>
                    <input type="date" name="date_installed" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="form-group" style="margin-bottom:15px;">
                    <label>Location (Full Landmark address) *</label>
                    <input type="text" name="location" class="form-control" required placeholder="e.g. Taft Ave near Vito Cruz station, Malate">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Responsible Office / Department</label>
                        <input type="text" name="responsible_office" class="form-control" placeholder="e.g. City Engineering Office">
                    </div>
                    <div class="form-group">
                        <label>Asset Representative Image</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                    </div>
                </div>

                <div class="form-group">
                    <label>Description & Notes</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Enter other relevant details..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Asset</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT ASSET MODAL -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Utility Asset Details</h3>
            <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
        </div>
        <form id="edit-form" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update">
            <input type="hidden" id="edit-id" name="id">
            <div class="modal-body">
                <div class="form-group" style="margin-bottom:15px;">
                    <label>Asset ID</label>
                    <input type="text" id="edit-asset-id" class="form-control" disabled>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label>Asset Name *</label>
                        <input type="text" id="edit-name" name="name" class="form-control" required>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Quantity *</label>
                        <input type="number" id="edit-quantity" name="quantity" class="form-control" required min="1">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Asset Type / Category *</label>
                        <select id="edit-type-id" name="asset_type_id" class="form-control" required onchange="toggleNewCategory(this, 'edit-new-category-wrapper')">
                            <?php foreach ($assetTypes as $type): ?>
                                <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['name']); ?></option>
                            <?php endforeach; ?>
                            <option value="new">+ Create New Category...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Condition Status *</label>
                        <select id="edit-status" name="condition_status" class="form-control" required>
                            <option value="Operational">Operational</option>
                            <option value="Needs Inspection">Needs Inspection</option>
                            <option value="Damaged">Damaged</option>
                            <option value="Under Maintenance">Under Maintenance</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" id="edit-new-category-wrapper" style="display:none; margin-bottom:15px;">
                    <label>New Category Name *</label>
                    <input type="text" id="edit-new-category-input" name="new_category_name" class="form-control" placeholder="Type new category name...">
                </div>

                <div class="form-group" style="margin-bottom:15px;">
                    <label>Installation Date *</label>
                    <input type="date" id="edit-date-installed" name="date_installed" class="form-control" required>
                </div>

                <div class="form-group" style="margin-bottom:15px;">
                    <label>Location (Full Landmark address) *</label>
                    <input type="text" id="edit-location" name="location" class="form-control" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Responsible Office</label>
                        <input type="text" id="edit-office" name="responsible_office" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Upload New Image (Replaces current)</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:15px;">
                    <label>Description & Notes</label>
                    <textarea id="edit-description" name="description" class="form-control" rows="2"></textarea>
                </div>

                <div class="form-group" style="background:#f8f9fa; padding:15px; border-radius:8px;">
                    <label style="color:#2c3e50; font-weight:700;">Status Change Notes</label>
                    <p style="font-size:11px; color:#64748b; margin-bottom:5px;">State the reason if you are changing the condition status of the asset.</p>
                    <input type="text" name="status_notes" class="form-control" placeholder="e.g. Storm damage reported / Normal inspection cycle completed.">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- VIEW ASSET DETAILS MODAL -->
<div id="viewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Asset Specification Details</h3>
            <button class="modal-close" onclick="closeModal('viewModal')">&times;</button>
        </div>
        <div class="modal-body" style="padding:0;">
            <div id="view-image-container" style="width:100%; height:200px; background:#f1f2f6; display:none; justify-content:center; align-items:center; overflow:hidden;">
                <img id="view-image" src="" style="width:100%; height:100%; object-fit:cover;">
            </div>
            <div style="padding:24px;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px;">
                    <div>
                        <h2 id="view-name" style="font-size:20px; color:#2c3e50;"></h2>
                        <p id="view-type" style="font-size:12px; color:#64748b; font-weight:600; text-transform:uppercase; margin-top:2px;"></p>
                    </div>
                    <span id="view-status" class="badge"></span>
                </div>

                <table style="width:100%;" id="view-specs-table">
                    <tr>
                        <td style="font-weight:600; width:150px; color:#64748b;">Asset ID</td>
                        <td id="view-asset-id-text"></td>
                    </tr>
                    <tr>
                        <td style="font-weight:600; color:#64748b;">Quantity</td>
                        <td id="view-quantity-text"></td>
                    </tr>
                    <tr>
                        <td style="font-weight:600; color:#64748b;">Location</td>
                        <td id="view-location-text"></td>
                    </tr>
                    <tr>
                        <td style="font-weight:600; color:#64748b;">GPS Coordinates</td>
                        <td id="view-gps-text"></td>
                    </tr>
                    <tr>
                        <td style="font-weight:600; color:#64748b;">Date Installed</td>
                        <td id="view-date-text"></td>
                    </tr>
                    <tr>
                        <td style="font-weight:600; color:#64748b;">Responsible Office</td>
                        <td id="view-office-text"></td>
                    </tr>
                    <tr>
                        <td style="font-weight:600; color:#64748b;">Description</td>
                        <td id="view-desc-text"></td>
                    </tr>
                </table>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" onclick="closeModal('viewModal')">Close</button>
        </div>
    </div>
</div>

<!-- DELETE CONFIRM MODAL -->
<div id="deleteModal" class="modal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header" style="background:#fde8e8;">
            <h3 style="color:#bd2130;"><i class="fas fa-exclamation-triangle"></i> Delete Asset Record</h3>
            <button class="modal-close" onclick="closeModal('deleteModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" id="delete-id" name="id">
            <div class="modal-body" style="padding: 20px 24px;">
                <p>Are you sure you want to delete asset <strong id="delete-asset-id-text"></strong>? This will permanently remove its inventory records and history logs from the database.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('deleteModal')">Cancel</button>
                <button type="submit" class="btn btn-danger">Confirm Delete</button>
            </div>
        </form>
    </div>
</div>

<!-- REVIEW CHANGES CONFIRM MODAL -->
<div id="reviewChangesModal" class="modal">
    <div class="modal-content" style="max-width: 550px;">
        <div class="modal-header" style="background:#e2ebf6;">
            <h3 style="color:#2c3e50;"><i class="fas fa-eye"></i> Review Changes</h3>
            <button type="button" class="modal-close" onclick="closeModal('reviewChangesModal')">&times;</button>
        </div>
        <div class="modal-body" style="padding: 20px 24px;">
            <p style="margin-bottom: 15px; font-size: 14px; color: #64748b;">Please review the changes you made to this asset record before saving:</p>
            <table class="review-table">
                <thead>
                    <tr style="border-bottom: 2px solid #e2e8f0; text-align: left;">
                        <th style="padding: 8px; color: #64748b; font-size: 12px; text-transform: uppercase;">Field</th>
                        <th style="padding: 8px; color: #64748b; font-size: 12px; text-transform: uppercase;">Original</th>
                        <th style="padding: 8px; color: #64748b; font-size: 12px; text-transform: uppercase;">Modified</th>
                    </tr>
                </thead>
                <tbody id="review-changes-list">
                    <!-- Dynamic review rows inserted by JavaScript -->
                </tbody>
            </table>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('reviewChangesModal')">Go Back</button>
            <button type="button" class="btn btn-primary" onclick="submitEditForm()">Confirm & Save</button>
        </div>
    </div>
</div>

<!-- HIDDEN FORM FOR CATEGORY DELETION -->
<form id="delete-category-form" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete_category">
    <input type="hidden" name="category_id" id="delete-category-id">
</form>

<script>
    let currentEditingAsset = null;

    // Searchable Category Dropdown Logic
    document.addEventListener('DOMContentLoaded', () => {
        const editForm = document.getElementById('edit-form');
        if (editForm) {
            editForm.addEventListener('submit', (e) => {
                e.preventDefault();
                showReviewChanges();
            });
        }
        const searchInput = document.getElementById('category-search-input');
        const optionsList = document.getElementById('category-options-list');
        const hiddenInput = document.getElementById('category-type-id');
        const options = optionsList.querySelectorAll('.custom-option');

        if (searchInput && optionsList) {
            // Toggle list visibility on input click/focus
            searchInput.addEventListener('click', (e) => {
                e.stopPropagation();
                optionsList.classList.add('open');
            });

            searchInput.addEventListener('focus', () => {
                optionsList.classList.add('open');
                if (searchInput.value === 'All Categories') {
                    searchInput.value = '';
                    filterOptions('');
                }
            });

            // Filter options on input
            searchInput.addEventListener('input', () => {
                filterOptions(searchInput.value);
            });

            // Click option
            options.forEach(opt => {
                opt.addEventListener('click', (e) => {
                    if (e.target.classList.contains('delete-category-btn')) {
                        return;
                    }
                    const val = opt.getAttribute('data-value');
                    const name = opt.getAttribute('data-name');
                    hiddenInput.value = val;
                    searchInput.value = name;
                    optionsList.classList.remove('open');
                });
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.custom-select-container')) {
                    optionsList.classList.remove('open');
                    if (searchInput.value.trim() === '') {
                        let foundName = 'All Categories';
                        options.forEach(opt => {
                            if (opt.getAttribute('data-value') === hiddenInput.value) {
                                foundName = opt.getAttribute('data-name');
                            }
                        });
                        searchInput.value = foundName;
                    }
                }
            });
        }

        function filterOptions(query) {
            const cleanQuery = query.toLowerCase().trim();
            options.forEach(opt => {
                const name = opt.getAttribute('data-name').toLowerCase();
                if (name.includes(cleanQuery) || opt.getAttribute('data-value') === '') {
                    opt.style.display = 'flex';
                } else {
                    opt.style.display = 'none';
                }
            });
        }
    });

    function deleteCategory(event, id, name) {
        event.stopPropagation(); // Stop propagation so it doesn't trigger selection
        if (confirm("Are you sure you want to delete the category '" + name + "'?")) {
            document.getElementById('delete-category-id').value = id;
            document.getElementById('delete-category-form').submit();
        }
    }
    function toggleNewCategory(selectElement, wrapperId) {
        const wrapper = document.getElementById(wrapperId);
        const input = wrapper.querySelector('input');
        if (selectElement.value === 'new') {
            wrapper.style.display = 'block';
            input.required = true;
        } else {
            wrapper.style.display = 'none';
            input.required = false;
            input.value = '';
        }
    }

    function openAddModal() {
        // Reset category toggle
        document.getElementById('add-type-id').value = '';
        toggleNewCategory(document.getElementById('add-type-id'), 'add-new-category-wrapper');
        document.getElementById('addModal').classList.add('open');
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
    }

    function editAsset(asset) {
        currentEditingAsset = asset;
        document.getElementById('edit-id').value = asset.id;
        document.getElementById('edit-asset-id').value = asset.asset_id;
        document.getElementById('edit-name').value = asset.name;
        document.getElementById('edit-quantity').value = asset.quantity || 1;
        document.getElementById('edit-type-id').value = asset.asset_type_id;
        toggleNewCategory(document.getElementById('edit-type-id'), 'edit-new-category-wrapper');
        document.getElementById('edit-status').value = asset.condition_status;
        document.getElementById('edit-date-installed').value = asset.date_installed;
        document.getElementById('edit-location').value = asset.location;
        document.getElementById('edit-office').value = asset.responsible_office || '';
        document.getElementById('edit-description').value = asset.description || '';
        document.getElementById('editModal').classList.add('open');
    }

    function showReviewChanges() {
        if (!currentEditingAsset) return;

        const reviewList = document.getElementById('review-changes-list');
        reviewList.innerHTML = '';

        const fieldsToCompare = [
            { id: 'edit-name', name: 'Asset Name', key: 'name' },
            { id: 'edit-quantity', name: 'Quantity', key: 'quantity' },
            { id: 'edit-status', name: 'Condition Status', key: 'condition_status' },
            { id: 'edit-date-installed', name: 'Installation Date', key: 'date_installed' },
            { id: 'edit-location', name: 'Location', key: 'location' },
            { id: 'edit-office', name: 'Responsible Office', key: 'responsible_office' },
            { id: 'edit-description', name: 'Description & Notes', key: 'description' }
        ];

        let changesCount = 0;

        fieldsToCompare.forEach(field => {
            const inputVal = document.getElementById(field.id).value.trim();
            const originalVal = (currentEditingAsset[field.key] || '').toString().trim();

            if (inputVal !== originalVal) {
                appendReviewRow(field.name, originalVal, inputVal);
                changesCount++;
            }
        });

        // Compare Category
        const typeSelect = document.getElementById('edit-type-id');
        const selectedVal = typeSelect.value;
        const originalTypeVal = (currentEditingAsset['asset_type_id'] || '').toString();

        if (selectedVal !== originalTypeVal) {
            let oldCatName = currentEditingAsset['type_name'] || 'N/A';
            let newCatName = '';
            if (selectedVal === 'new') {
                newCatName = document.getElementById('edit-new-category-input').value.trim() + ' (New Category)';
            } else {
                newCatName = typeSelect.options[typeSelect.selectedIndex].text;
            }
            appendReviewRow('Asset Type / Category', oldCatName, newCatName);
            changesCount++;
        }

        if (changesCount > 0) {
            document.getElementById('reviewChangesModal').classList.add('open');
        } else {
            alert('No changes detected in the form fields.');
        }
    }

    function appendReviewRow(fieldName, oldVal, newVal) {
        const reviewList = document.getElementById('review-changes-list');
        const tr = document.createElement('tr');
        
        const tdField = document.createElement('td');
        tdField.className = 'review-field-name';
        tdField.textContent = fieldName;

        const tdOld = document.createElement('td');
        const spanOld = document.createElement('span');
        spanOld.className = 'review-old-val';
        spanOld.textContent = oldVal || 'N/A';
        tdOld.appendChild(spanOld);

        const tdNew = document.createElement('td');
        const spanNew = document.createElement('span');
        spanNew.className = 'review-new-val';
        spanNew.textContent = newVal || 'N/A';
        tdNew.appendChild(spanNew);

        tr.appendChild(tdField);
        tr.appendChild(tdOld);
        tr.appendChild(tdNew);
        reviewList.appendChild(tr);
    }

    function submitEditForm() {
        document.getElementById('edit-form').submit();
    }

    function viewAsset(asset) {
        document.getElementById('view-name').textContent = asset.name;
        document.getElementById('view-type').textContent = asset.type_name;
        document.getElementById('view-asset-id-text').textContent = asset.asset_id;
        document.getElementById('view-quantity-text').textContent = asset.quantity || '1';
        document.getElementById('view-location-text').textContent = asset.location;
        
        let gpsText = 'Not Available';
        if (asset.latitude && asset.longitude) {
            gpsText = asset.latitude + ', ' + asset.longitude;
        }
        document.getElementById('view-gps-text').textContent = gpsText;
        document.getElementById('view-date-text').textContent = asset.date_installed;
        document.getElementById('view-office-text').textContent = asset.responsible_office || 'N/A';
        document.getElementById('view-desc-text').textContent = asset.description || 'No description.';

        // Status badge
        const badge = document.getElementById('view-status');
        badge.className = 'badge badge-' + asset.condition_status.toLowerCase().replace(' ', '');
        badge.textContent = asset.condition_status;

        // Image Handling
        const imgContainer = document.getElementById('view-image-container');
        const img = document.getElementById('view-image');
        if (asset.image_path) {
            img.src = asset.image_path;
            imgContainer.style.display = 'flex';
        } else {
            imgContainer.style.display = 'none';
        }

        document.getElementById('viewModal').classList.add('open');
    }

    function confirmDelete(id, assetId) {
        document.getElementById('delete-id').value = id;
        document.getElementById('delete-asset-id-text').textContent = assetId;
        document.getElementById('deleteModal').classList.add('open');
    }
</script>

</body>
</html>
