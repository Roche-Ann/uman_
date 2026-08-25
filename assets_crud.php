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

// Auto-migrate schema updates for utility_assets and asset_status_logs
try {
    $col = $pdo->query("SHOW COLUMNS FROM utility_assets LIKE 'parent_asset_id'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE `utility_assets` ADD COLUMN `parent_asset_id` INT NULL DEFAULT NULL AFTER `asset_id`");
        try {
            $pdo->exec("ALTER TABLE `utility_assets` ADD CONSTRAINT `fk_parent_asset` FOREIGN KEY (`parent_asset_id`) REFERENCES `utility_assets`(`id`) ON DELETE SET NULL");
        } catch (Throwable $ignored) {}
    }
} catch (Throwable $e) {}

try {
    $pdo->exec("ALTER TABLE `utility_assets` MODIFY COLUMN `condition_status` VARCHAR(50) NOT NULL DEFAULT 'Operational'");
} catch (Throwable $e) {}

try {
    $col = $pdo->query("SHOW COLUMNS FROM asset_status_logs LIKE 'action_type'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE `asset_status_logs` ADD COLUMN `action_type` VARCHAR(50) NOT NULL DEFAULT 'status_changed' AFTER `utility_asset_id`");
    }
} catch (Throwable $e) {}

try {
    $col = $pdo->query("SHOW COLUMNS FROM asset_status_logs LIKE 'changed_fields'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE `asset_status_logs` ADD COLUMN `changed_fields` LONGTEXT NULL AFTER `notes`");
    }
} catch (Throwable $e) {}

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$userType = $_SESSION['user_type'] ?? '';
$userId = intval($_SESSION['user_id'] ?? 1);
if ($userId <= 0) $userId = 1;
try {
    $chkUser = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $chkUser->execute([$userId]);
    if (!$chkUser->fetch()) {
        $firstU = $pdo->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetchColumn();
        if ($firstU) $userId = intval($firstU);
    }
} catch (Throwable $e) {}

$error = $_SESSION['flash_error'] ?? '';
$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

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
                $_SESSION['flash_error'] = "Failed to create category: " . $e->getMessage();
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit();
            }
        } else {
            $asset_type_id = intval($asset_type_id);
        }

        if (empty($name) || empty($location) || $asset_type_id <= 0) {
            $_SESSION['flash_error'] = 'Please fill in all required fields (Name, Type, Location).';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
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

                // Handle Image Upload if any (non-critical)
                try {
                    $imagePath = handleImageUpload($_FILES['image'] ?? null);
                    if ($imagePath) {
                        $pdo->prepare("INSERT INTO asset_images (utility_asset_id, image_path) VALUES (?, ?)")->execute([$id, $imagePath]);
                    }
                } catch (Throwable $ignored) {}

                // Log creation with full snapshot (non-critical)
                try {
                    $snapshot = json_encode([
                        'asset_id'           => $asset_id,
                        'name'               => $name,
                        'quantity'           => $quantity,
                        'location'           => $location,
                        'condition_status'   => $condition_status,
                        'date_installed'     => $date_installed,
                        'responsible_office' => $responsible_office,
                        'description'        => $description,
                    ], JSON_UNESCAPED_UNICODE);
                    $pdo->prepare("
                        INSERT INTO asset_status_logs (utility_asset_id, action_type, old_status, new_status, changed_by, notes, changed_fields) 
                        VALUES (?, 'asset_created', NULL, ?, ?, 'Asset registered in system.', ?)
                    ")->execute([$id, $condition_status, $userId, $snapshot]);
                } catch (Throwable $ignored) {}

                // Create alert notification (non-critical)
                try {
                    $pdo->prepare("
                        INSERT INTO asset_notifications (type, message) 
                        VALUES ('asset_created', ?)
                    ")->execute(["New asset registered: {$asset_id} ({$name}) in {$location}."]);
                } catch (Throwable $ignored) {}

                $_SESSION['flash_success'] = "Asset {$asset_id} successfully created!";
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit();
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = "Failed to create asset: " . $e->getMessage();
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit();
            }
        }
    } elseif ($action === 'update') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $asset_type_id = $_POST['asset_type_id'] ?? '';
        $new_category_name = trim($_POST['new_category_name'] ?? '');
        $quantity = max(1, intval($_POST['quantity'] ?? 1));
        $affected_quantity = max(0, intval($_POST['affected_quantity'] ?? 0));
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
                $_SESSION['flash_error'] = "Failed to create category: " . $e->getMessage();
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit();
            }
        } else {
            $asset_type_id = intval($asset_type_id);
        }

        if ($id <= 0 || empty($name) || empty($location) || $asset_type_id <= 0) {
            $_SESSION['flash_error'] = 'Please fill in all required fields (Name, Type, Location).';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        } else {
            try {
                // Get current status & location to log changes
                $curr = $pdo->prepare("
                    SELECT asset_id, condition_status, location, latitude, longitude,
                           quantity, parent_asset_id, name, asset_type_id,
                           date_installed, description, responsible_office
                    FROM utility_assets WHERE id = ?
                ");
                $curr->execute([$id]);
                $oldAsset = $curr->fetch();

                if ($oldAsset) {
                    $asset_id = $oldAsset['asset_id'];
                    $latitude = (isset($oldAsset['latitude']) && is_numeric($oldAsset['latitude'])) ? (float)$oldAsset['latitude'] : null;
                    $longitude = (isset($oldAsset['longitude']) && is_numeric($oldAsset['longitude'])) ? (float)$oldAsset['longitude'] : null;
                    $total_qty = intval($oldAsset['quantity']);
                    $is_non_operational = in_array($condition_status, ['Damaged', 'Needs Inspection', 'Under Maintenance']);
                    $is_child = !empty($oldAsset['parent_asset_id']);

                    // ---- AUTO-MERGE: child offshoot restored to Operational ----
                    if ($is_child && $condition_status === 'Operational') {
                        try {
                            $parentId = intval($oldAsset['parent_asset_id']);
                            $pdo->prepare("UPDATE utility_assets SET quantity = quantity + ? WHERE id = ?")
                                ->execute([$total_qty, $parentId]);
                            $parentCodeStmt = $pdo->prepare("SELECT asset_id FROM utility_assets WHERE id = ?");
                            $parentCodeStmt->execute([$parentId]);
                            $parentCode = $parentCodeStmt->fetchColumn() ?: 'parent';
                            try {
                                $pdo->prepare("INSERT INTO asset_status_logs (utility_asset_id, action_type, old_status, new_status, changed_by, notes) VALUES (?, 'split_merged', ?, ?, ?, ?)")
                                    ->execute([$id, $oldAsset['condition_status'], 'Operational (Merged)', $userId,
                                        "{$total_qty} unit(s) restored to Operational and merged back into {$parentCode}. " . ($status_notes ?: '')]);
                            } catch (Throwable $ignored) {}
                            try {
                                $pdo->prepare("INSERT INTO asset_notifications (type, message) VALUES (?, ?)")
                                    ->execute(['status_changed', "Asset {$asset_id}: {$total_qty} unit(s) restored and merged back into {$parentCode}."]);
                            } catch (Throwable $ignored) {}
                            $pdo->prepare("DELETE FROM utility_assets WHERE id = ?")->execute([$id]);
                            $_SESSION['flash_success'] = "{$total_qty} unit(s) of {$asset_id} restored to Operational and merged back into {$parentCode}.";
                        } catch (PDOException $e) {
                            $_SESSION['flash_error'] = "Auto-merge failed: " . $e->getMessage();
                        }
                        header('Location: ' . $_SERVER['PHP_SELF']);
                        exit();
                    }

                    // ---- SPLIT: partial quantity changing to a non-operational status ----
                    $doSplit = ($is_non_operational && !$is_child && $affected_quantity > 0 && $affected_quantity < $total_qty);
                    if ($doSplit) {
                        try {
                            $newParentQty = $total_qty - $affected_quantity;
                            $pdo->prepare("UPDATE utility_assets SET quantity = ? WHERE id = ?")
                                ->execute([$newParentQty, $id]);

                            // Generate unique child asset_id: e.g. ELEC-0012-M1, -M2, etc.
                            $baseAssetId = $oldAsset['asset_id'];
                            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM utility_assets WHERE asset_id LIKE ?");
                            $countStmt->execute([$baseAssetId . '-M%']);
                            $offshotCount = intval($countStmt->fetchColumn());
                            $childAssetId = $baseAssetId . '-M' . ($offshotCount + 1);

                            $pdo->prepare("
                                INSERT INTO utility_assets
                                    (asset_id, parent_asset_id, name, asset_type_id, quantity, location, latitude, longitude, date_installed, condition_status, description, responsible_office)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ")->execute([
                                $childAssetId, $id, $name, $asset_type_id, $affected_quantity,
                                $location, $latitude, $longitude, $date_installed,
                                $condition_status, $description, $responsible_office
                            ]);
                            $childId = $pdo->lastInsertId();

                            try {
                                $pdo->prepare("INSERT INTO asset_status_logs (utility_asset_id, action_type, old_status, new_status, changed_by, notes) VALUES (?, 'split_created', NULL, ?, ?, ?)")
                                    ->execute([$childId, $condition_status, $userId,
                                        "Offshoot created from {$baseAssetId}: {$affected_quantity} unit(s) marked as {$condition_status}. " . ($status_notes ?: '')]);
                            } catch (Throwable $ignored) {}
                            try {
                                $pdo->prepare("INSERT INTO asset_notifications (type, message) VALUES (?, ?)")
                                    ->execute(['status_changed', "Asset {$baseAssetId}: {$affected_quantity} unit(s) split off as {$childAssetId} with status {$condition_status}."]);
                            } catch (Throwable $ignored) {}
                            $_SESSION['flash_success'] = "{$affected_quantity} unit(s) split from {$baseAssetId} as {$childAssetId} (Status: {$condition_status}). {$newParentQty} unit(s) remain Operational.";
                        } catch (PDOException $e) {
                            $_SESSION['flash_error'] = "Split failed: " . $e->getMessage();
                        }
                        header('Location: ' . $_SERVER['PHP_SELF']);
                        exit();
                    }

                    // Update core details (normal full-batch update)
                    $stmt = $pdo->prepare("
                        UPDATE utility_assets 
                        SET name = ?, asset_type_id = ?, quantity = ?, location = ?, latitude = ?, longitude = ?, date_installed = ?, condition_status = ?, description = ?, responsible_office = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $asset_type_id, $quantity, $location, $latitude, $longitude, $date_installed, $condition_status, $description, $responsible_office, $id]);

                    // --- Unified field-diff audit log (non-critical) ---
                    try {
                        // Fetch type name for comparison
                        $oldTypeName = '';
                        $newTypeName = '';
                        try {
                            $tStmt = $pdo->prepare("SELECT id, name FROM asset_types WHERE id IN (?, ?)");
                            $tStmt->execute([$oldAsset['asset_type_id'] ?? 0, $asset_type_id]);
                            foreach ($tStmt->fetchAll() as $t) {
                                if ($t['id'] == ($oldAsset['asset_type_id'] ?? 0)) $oldTypeName = $t['name'];
                                if ($t['id'] == $asset_type_id) $newTypeName = $t['name'];
                            }
                        } catch (Throwable $ignored) {}

                        $fieldLabels = [
                            'name'               => ['Name',               $oldAsset['name'] ?? '',                $name],
                            'quantity'           => ['Quantity',            $total_qty,                             $quantity],
                            'location'           => ['Location',            $oldAsset['location'] ?? '',            $location],
                            'condition_status'   => ['Status',              $oldAsset['condition_status'] ?? '',    $condition_status],
                            'asset_type'         => ['Category',            $oldTypeName,                          $newTypeName],
                            'date_installed'     => ['Date Installed',      $oldAsset['date_installed'] ?? '',      $date_installed],
                            'responsible_office' => ['Responsible Office',  $oldAsset['responsible_office'] ?? '', $responsible_office],
                            'description'        => ['Description',         $oldAsset['description'] ?? '',        $description],
                        ];

                        $diff = [];
                        foreach ($fieldLabels as $key => [$label, $old, $new]) {
                            if (trim(strval($old)) !== trim(strval($new))) {
                                $diff[$label] = ['old' => strval($old), 'new' => strval($new)];
                            }
                        }

                        $isStatusChanged = (trim(strval($oldAsset['condition_status'] ?? '')) !== trim(strval($condition_status)));
                        $actionType = $isStatusChanged ? 'status_changed' : 'asset_edited';
                        $finalNotes = $status_notes ?: ($isStatusChanged ? "Status changed from {$oldAsset['condition_status']} to {$condition_status}." : (empty($diff) ? "Asset record updated." : "Asset details modified."));
                        $diffJson = !empty($diff) ? json_encode($diff, JSON_UNESCAPED_UNICODE) : null;

                        // Insert audit log (guaranteed execution)
                        $logged = false;
                        try {
                            $pdo->prepare("
                                INSERT INTO asset_status_logs (utility_asset_id, action_type, old_status, new_status, changed_by, notes, changed_fields)
                                VALUES (?, ?, ?, ?, ?, ?, ?)
                            ")->execute([
                                $id,
                                $actionType,
                                $oldAsset['condition_status'] ?? 'Operational',
                                $condition_status,
                                $userId,
                                $finalNotes,
                                $diffJson
                            ]);
                            $logged = true;
                        } catch (Throwable $e) {}

                        if (!$logged) {
                            try {
                                $pdo->prepare("
                                    INSERT INTO asset_status_logs (utility_asset_id, old_status, new_status, changed_by, notes)
                                    VALUES (?, ?, ?, ?, ?)
                                ")->execute([
                                    $id,
                                    $oldAsset['condition_status'] ?? 'Operational',
                                    $condition_status,
                                    $userId,
                                    $finalNotes
                                ]);
                            } catch (Throwable $ignored) {}
                        }

                        // Trigger notification only for status changes (non-critical)
                        if ($oldAsset['condition_status'] !== $condition_status) {
                            try {
                                $notifType = ($condition_status === 'Damaged') ? 'reported_damaged' : 'status_changed';
                                $pdo->prepare("INSERT INTO asset_notifications (type, message) VALUES (?, ?)")
                                    ->execute([$notifType, "Asset {$asset_id} status changed from {$oldAsset['condition_status']} to {$condition_status}."]);
                            } catch (Throwable $ignored) {}
                        }

                        // Log location change in asset_locations table too (non-critical)
                        if ($oldAsset['location'] !== $location) {
                            try {
                                $pdo->prepare("
                                    INSERT INTO asset_locations (utility_asset_id, old_location, new_location, old_latitude, new_latitude, old_longitude, new_longitude, changed_by)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                                ")->execute([$id, $oldAsset['location'], $location, $oldAsset['latitude'], $latitude, $oldAsset['longitude'], $longitude, $userId]);
                            } catch (Throwable $ignored) {}
                        }
                    } catch (Throwable $ignored) {}

                    // Handle Image Upload (non-critical)
                    try {
                        $imagePath = handleImageUpload($_FILES['image'] ?? null);
                        if ($imagePath) {
                            $pdo->prepare("INSERT INTO asset_images (utility_asset_id, image_path) VALUES (?, ?)")->execute([$id, $imagePath]);
                        }
                    } catch (Throwable $ignored) {}

                    $_SESSION['flash_success'] = "Asset {$asset_id} updated successfully!";
                    header('Location: ' . $_SERVER['PHP_SELF'] . ($condition_status === 'Retired' ? '?tab=retired' : ''));
                    exit();
                }
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = "Failed to update asset: " . $e->getMessage();
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit();
            }
        }
    } elseif ($action === 'reactivate') {
        $reactivateId = intval($_POST['id'] ?? 0);
        $reactivateNote = trim($_POST['reactivate_notes'] ?? 'Asset restored from Retired status to Operational.');
        if ($reactivateId > 0) {
            try {
                $aStmt = $pdo->prepare("SELECT asset_id, condition_status FROM utility_assets WHERE id = ?");
                $aStmt->execute([$reactivateId]);
                $targetAsset = $aStmt->fetch();
                if ($targetAsset) {
                    $pdo->prepare("UPDATE utility_assets SET condition_status = 'Operational' WHERE id = ?")->execute([$reactivateId]);
                    
                    // Log status change
                    try {
                        $diff = ['Status' => ['old' => $targetAsset['condition_status'], 'new' => 'Operational']];
                        $pdo->prepare("
                            INSERT INTO asset_status_logs (utility_asset_id, action_type, old_status, new_status, changed_by, notes, changed_fields)
                            VALUES (?, 'status_changed', ?, 'Operational', ?, ?, ?)
                        ")->execute([
                            $reactivateId,
                            $targetAsset['condition_status'],
                            $userId,
                            $reactivateNote,
                            json_encode($diff, JSON_UNESCAPED_UNICODE)
                        ]);
                    } catch (Throwable $e) {}
                    
                    $_SESSION['flash_success'] = "Asset {$targetAsset['asset_id']} has been restored to Operational status.";
                }
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = "Failed to reactivate asset: " . $e->getMessage();
            }
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        }
    } elseif ($action === 'delete_category') {
        $category_id = intval($_POST['category_id'] ?? 0);
        if ($category_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM asset_types WHERE id = ?");
                $stmt->execute([$category_id]);
                $_SESSION['flash_success'] = "Category successfully deleted.";
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit();
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = "Cannot delete category: it is currently assigned to one or more utility assets.";
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit();
            }
        }
    }
}

// ------------------------------------------------------------------------
// Get Search / Filter / Tab / Pagination parameters
// ------------------------------------------------------------------------
$currentTab    = ($_GET['tab'] ?? 'active') === 'retired' ? 'retired' : 'active';
$search        = trim($_GET['search'] ?? '');
$type_filter   = !empty($_GET['type_id']) ? intval($_GET['type_id']) : null;
$status_filter = !empty($_GET['status']) ? trim($_GET['status']) : null;

// Tab counts
$activeCount  = 0;
$retiredCount = 0;
try {
    $activeCount  = intval($pdo->query("SELECT COUNT(*) FROM utility_assets WHERE condition_status != 'Retired'")->fetchColumn() ?: 0);
    $retiredCount = intval($pdo->query("SELECT COUNT(*) FROM utility_assets WHERE condition_status = 'Retired'")->fetchColumn() ?: 0);
} catch (Throwable $e) {}

// Build asset WHERE conditions
$conditions = [];
$params = [];

if ($currentTab === 'retired') {
    $conditions[] = "a.condition_status = 'Retired'";
} else {
    $conditions[] = "a.condition_status != 'Retired'";
    if ($status_filter) {
        $conditions[] = "a.condition_status = ?";
        $params[] = $status_filter;
    }
}

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

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Fetch all matching assets (no pagination at row level yet)
$allAssetsQuery = "
    SELECT a.*, t.name as type_name, t.id as type_id_val
    FROM utility_assets a
    LEFT JOIN asset_types t ON a.asset_type_id = t.id
    $whereClause
    ORDER BY COALESCE(t.name, 'Unknown') ASC, a.asset_id ASC
";
$allStmt = $pdo->prepare($allAssetsQuery);
$allStmt->execute($params);
$allAssets = $allStmt->fetchAll();

// Group assets by category
$groupedAssets = [];
foreach ($allAssets as $asset) {
    $catKey = $asset['type_id_val'] ?? 0;
    $catName = $asset['type_name'] ?? 'Unknown';
    if (!isset($groupedAssets[$catKey])) {
        $groupedAssets[$catKey] = [
            'category_name' => $catName,
            'category_id'   => $catKey,
            'total_qty'     => 0,
            'statuses'      => [],
            'assets'        => [],
        ];
    }
    $groupedAssets[$catKey]['total_qty'] += intval($asset['quantity']);
    $groupedAssets[$catKey]['statuses'][] = $asset['condition_status'];
    $groupedAssets[$catKey]['assets'][] = $asset;
}

// Pagination based on categories
$limit = 10;
$page = !empty($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;
$totalCategories = count($groupedAssets);
$totalPages = ceil($totalCategories / $limit);
$pagedGroups = array_slice($groupedAssets, $offset, $limit, true);

// Retrieve all asset types for form selectors
$assetTypes = $pdo->query("SELECT * FROM asset_types ORDER BY name ASC")->fetchAll();

// Determine which categories to auto-expand (when search is active)
$autoExpandCats = [];
if (!empty($search) || $status_filter) {
    foreach ($pagedGroups as $catId => $group) {
        $autoExpandCats[] = $catId;
    }
}
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

        /* =========== ACCORDION TABLE STYLES =========== */
        .group-header-row {
            cursor: pointer;
            background: #ffffff;
            border-left: 3px solid transparent;
            transition: background 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease;
        }
        .group-header-row:hover {
            background: #f5f7ff;
            border-left-color: #3762c8;
        }
        .group-header-row.expanded {
            background: #f0f4ff;
            border-left-color: #3762c8;
            box-shadow: inset 0 -1px 0 #dbeafe;
        }
        .accordion-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 26px;
            height: 26px;
            border-radius: 8px;
            background: #e8eeff;
            color: #3762c8;
            font-size: 10px;
            transition: transform 0.22s ease, background 0.18s ease;
            border: 1px solid #c7d7fb;
        }
        .group-header-row:hover .accordion-icon {
            background: #dbeafe;
        }
        .group-header-row.expanded .accordion-icon {
            transform: rotate(90deg);
            background: #3762c8;
            color: #ffffff;
            border-color: #3762c8;
        }
        .category-label {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            letter-spacing: 0.01em;
        }
        .asset-count-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #eff6ff;
            color: #3762c8;
            border: 1px solid #bfdbfe;
            border-radius: 20px;
            padding: 3px 11px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.03em;
        }
        /* expand hint */
        .expand-hint {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            color: #94a3b8;
            font-weight: 500;
            transition: color 0.15s;
        }
        .group-header-row:hover .expand-hint,
        .group-header-row.expanded .expand-hint {
            color: #3762c8;
        }
        /* Child row container */
        .group-child-row {
            display: none;
        }
        .group-child-row.open {
            display: table-row;
        }
        /* Child table inside expanded row */
        .child-table-wrapper {
            padding: 0 12px 12px 52px;
            background: #fafbff;
            border-top: 1px solid #e2e8f0;
            border-bottom: 2px solid #dbeafe;
        }
        .child-table {
            width: 100%;
            border-collapse: collapse;
            border-radius: 8px;
            overflow: hidden;
        }
        .child-table thead tr {
            background: #f1f5f9;
        }
        .child-table thead th {
            padding: 10px 14px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
        }
        .child-table tbody tr {
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.1s;
        }
        .child-table tbody tr:hover {
            background: #eff6ff;
        }
        .child-table td {
            padding: 11px 14px;
            font-size: 13px;
            color: #334155;
        }
        .child-asset-row.search-highlight {
            background: #fefce8 !important;
            border-left: 3px solid #eab308;
        }
        /* ---- Offshoot / split child rows ---- */
        .child-asset-row.is-offshoot {
            background: #fffbeb !important;
            border-left: 3px solid #f59e0b !important;
        }
        .child-asset-row.is-offshoot:hover {
            background: #fef3c7 !important;
        }
        .offshoot-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 10px;
            color: #92400e;
            background: #fef3c7;
            border: 1px solid #fde68a;
            border-radius: 4px;
            padding: 2px 6px;
            margin-top: 3px;
        }
        .offshoot-merge-notice {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 14px;
            font-size: 13px;
            color: #78350f;
            display: none;
        }
        .split-panel {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            display: none;
        }
        .split-panel label.panel-title {
            font-weight: 700;
            color: #166534;
            font-size: 13px;
        }
        .split-panel p.panel-hint {
            font-size: 11px;
            color: #166534;
            margin: 4px 0 10px;
        }
        /* Inventory Tabs CSS */
        .inventory-tabs {
            display: flex;
            gap: 12px;
            margin-bottom: 22px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 12px;
            flex-wrap: wrap;
        }
        .inventory-tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            color: #64748b;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
        }
        .inventory-tab:hover {
            color: #3762c8;
            background: #eff6ff;
            border-color: #bfdbfe;
        }
        .inventory-tab.active {
            background: #3762c8;
            color: #ffffff;
            border-color: #3762c8;
            box-shadow: 0 4px 12px rgba(55, 98, 200, 0.25);
        }
        .inventory-tab .tab-badge {
            background: #e2e8f0;
            color: #475569;
            padding: 2px 8px;
            border-radius: 99px;
            font-size: 11px;
            font-weight: 700;
        }
        .inventory-tab.active .tab-badge {
            background: rgba(255, 255, 255, 0.25);
            color: #ffffff;
        }
        .inventory-tab.tab-retired.active {
            background: #475569;
            border-color: #475569;
            box-shadow: 0 4px 12px rgba(71, 85, 105, 0.25);
        }
        .dark-theme .inventory-tabs {
            border-bottom-color: #334155 !important;
        }
        .dark-theme .inventory-tab {
            background: #1e293b !important;
            border-color: #334155 !important;
            color: #94a3b8 !important;
        }
        .dark-theme .inventory-tab:hover {
            color: #93c5fd !important;
            background: #151f32 !important;
        }
        .dark-theme .inventory-tab.active {
            background: #3762c8 !important;
            color: #ffffff !important;
            border-color: #3762c8 !important;
        }
        .dark-theme .inventory-tab.tab-retired.active {
            background: #334155 !important;
            border-color: #475569 !important;
        }
        .dark-theme .inventory-tab .tab-badge {
            background: #334155 !important;
            color: #94a3b8 !important;
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
            <div class="header-action-group" style="display:flex; gap:10px;">
                <button class="btn btn-primary" onclick="openAddModal()"><i class="fas fa-plus"></i> Register Asset</button>
                <a href="assets_history.php" class="btn btn-outline"><i class="fas fa-scroll"></i> Activity Log</a>
                <a href="assets_dashboard.php" class="btn btn-outline"><i class="fas fa-chevron-left"></i> Dashboard</a>
            </div>
        </div>

        <!-- Alert messages -->
        <?php if ($error): ?>
            <div class="alert alert-error" id="flash-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success" id="flash-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Inventory Navigation Tabs -->
        <div class="inventory-tabs">
            <a href="assets_crud.php?tab=active<?php echo $search ? '&search='.urlencode($search) : ''; ?><?php echo $type_filter ? '&type_id='.$type_filter : ''; ?>" class="inventory-tab <?php echo $currentTab === 'active' ? 'active' : ''; ?>">
                <i class="fas fa-boxes"></i> Active Inventory
                <span class="tab-badge"><?php echo number_format($activeCount); ?></span>
            </a>
            <a href="assets_crud.php?tab=retired<?php echo $search ? '&search='.urlencode($search) : ''; ?><?php echo $type_filter ? '&type_id='.$type_filter : ''; ?>" class="inventory-tab tab-retired <?php echo $currentTab === 'retired' ? 'active' : ''; ?>">
                <i class="fas fa-archive"></i> Retired &amp; Decommissioned
                <span class="tab-badge"><?php echo number_format($retiredCount); ?></span>
            </a>
        </div>

        <!-- Filters Form -->
        <form method="GET" class="filter-container">
            <input type="hidden" name="tab" value="<?php echo htmlspecialchars($currentTab); ?>">
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

            <?php if ($currentTab === 'active'): ?>
            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="">All Active Statuses</option>
                    <option value="Operational" <?php echo $status_filter === 'Operational' ? 'selected' : ''; ?>>Operational</option>
                    <option value="Needs Inspection" <?php echo $status_filter === 'Needs Inspection' ? 'selected' : ''; ?>>Needs Inspection</option>
                    <option value="Damaged" <?php echo $status_filter === 'Damaged' ? 'selected' : ''; ?>>Damaged</option>
                    <option value="Under Maintenance" <?php echo $status_filter === 'Under Maintenance' ? 'selected' : ''; ?>>Under Maintenance</option>
                </select>
            </div>
            <?php endif; ?>

            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
                <a href="assets_crud.php?tab=<?php echo $currentTab; ?>" class="btn btn-outline">Reset</a>
            </div>
        </form>

        <!-- Data Table Section -->
        <div class="table-section">
            <div class="table-container">
                <table id="assets-accordion-table">
                    <thead>
                        <tr>
                            <th style="width:36px;"></th>
                            <th>Category</th>
                            <th>Total Assets</th>
                            <th>Total Qty</th>
                            <th>Status Summary</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pagedGroups)): ?>
                            <tr><td colspan="6" style="text-align:center; padding:30px; color:#64748b;">
                                <?php echo $currentTab === 'retired' ? 'No retired or decommissioned assets found.' : 'No active assets found matching filters.'; ?>
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($pagedGroups as $catId => $group): ?>
                            <?php
                                $isExpanded = in_array($catId, $autoExpandCats);
                                $statusCounts = array_count_values($group['statuses']);
                                $dominantStatus = array_search(max($statusCounts), $statusCounts);
                                $badgeClass = strtolower(str_replace([' ','_'], '', $dominantStatus));
                                $totalAssets = count($group['assets']);
                            ?>
                            <!-- Category Header Row -->
                            <tr class="group-header-row <?php echo $isExpanded ? 'expanded' : ''; ?>" 
                                data-cat-id="<?php echo $catId; ?>"
                                onclick="toggleGroup(<?php echo $catId; ?>)">
                                <td>
                                    <span class="accordion-icon">
                                        <i class="fas fa-chevron-right"></i>
                                    </span>
                                </td>
                                <td>
                                    <strong class="category-label">
                                        <i class="fas fa-layer-group" style="color:#6366f1; margin-right:6px;"></i>
                                        <?php echo htmlspecialchars($group['category_name']); ?>
                                    </strong>
                                </td>
                                <td>
                                    <span class="asset-count-badge"><i class="fas fa-layer-group" style="font-size:9px;"></i> <?php echo $totalAssets; ?> Asset<?php echo $totalAssets !== 1 ? 's' : ''; ?></span>
                                </td>
                                <td><strong style="color:#1e293b;"><?php echo $group['total_qty']; ?></strong></td>
                                <td>
                                    <?php foreach ($statusCounts as $status => $count): ?>
                                    <span class="badge badge-<?php echo strtolower(str_replace([' ','_'],'', $status)); ?>" style="margin-right:4px; font-size:10px;">
                                        <?php echo htmlspecialchars($status); ?> (<?php echo $count; ?>)
                                    </span>
                                    <?php endforeach; ?>
                                </td>
                                <td style="text-align:right;">
                                    <span class="expand-hint"><i class="fas fa-chevron-down" style="font-size:10px;"></i> Expand</span>
                                </td>
                            </tr>
                            <!-- Expanded Child Rows -->
                            <tr class="group-child-row <?php echo $isExpanded ? 'open' : ''; ?>" data-parent-cat="<?php echo $catId; ?>">
                                <td colspan="6" style="padding:0;">
                                    <div class="child-table-wrapper">
                                        <table class="child-table">
                                            <thead>
                                                <tr>
                                                    <th>Asset ID</th>
                                                    <th>Name</th>
                                                    <th>Qty</th>
                                                    <th>Status</th>
                                                    <th style="text-align:right;">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($group['assets'] as $asset): ?>
                                                <?php $isOffshoot = !empty($asset['parent_asset_id']); ?>
                                                <tr class="child-asset-row <?php echo $isOffshoot ? 'is-offshoot' : ''; ?> <?php echo (!empty($search) && (stripos($asset['name'], $search) !== false || stripos($asset['asset_id'], $search) !== false)) ? 'search-highlight' : ''; ?>">
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($asset['asset_id']); ?></strong>
                                                        <?php if ($isOffshoot): ?>
                                                        <br><span class="offshoot-badge"><i class="fas fa-link"></i> Offshoot</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($asset['name']); ?></td>
                                                    <td><?php echo intval($asset['quantity']); ?></td>
                                                    <td>
                                                        <span class="badge badge-<?php echo strtolower(str_replace([' ','_'],'', $asset['condition_status'])); ?>">
                                                            <?php echo htmlspecialchars($asset['condition_status']); ?>
                                                        </span>
                                                    </td>
                                                    <td style="text-align:right;">
                                                        <button class="btn-icon btn-icon-view" onclick='viewAsset(<?php echo json_encode($asset); ?>)' title="View Details"><i class="fas fa-eye"></i></button>
                                                        <?php if ($currentTab === 'retired'): ?>
                                                            <button class="btn-icon" style="color:#10b981;border-color:#a7f3d0;background:#ecfdf5;" onclick='openReactivateModal(<?php echo json_encode($asset); ?>)' title="Restore to Operational"><i class="fas fa-undo"></i></button>
                                                        <?php endif; ?>
                                                        <button class="btn-icon btn-icon-edit" onclick='editAsset(<?php echo json_encode($asset); ?>)' title="Edit Asset"><i class="fas fa-edit"></i></button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
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
                    Showing categories <?php echo $offset + 1; ?> to <?php echo min($totalCategories, $offset + $limit); ?> of <?php echo $totalCategories; ?>
                </div>
                <div class="pagination-links">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="assets_crud.php?page=<?php echo $i; ?>&tab=<?php echo $currentTab; ?>&search=<?php echo urlencode($search); ?>&type_id=<?php echo $type_filter; ?>&status=<?php echo urlencode($status_filter); ?>" class="page-link <?php echo $page == $i ? 'active' : ''; ?>">
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
                            <option value="Retired">Retired</option>
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
                        <select id="edit-status" name="condition_status" class="form-control" required
                            onchange="toggleSplitPanel(this.value, parseInt(document.getElementById('edit-quantity').value)||1, !!(currentEditingAsset && currentEditingAsset.parent_asset_id))">
                            <option value="Operational">Operational</option>
                            <option value="Needs Inspection">Needs Inspection</option>
                            <option value="Damaged">Damaged</option>
                            <option value="Under Maintenance">Under Maintenance</option>
                            <option value="Retired">Retired</option>
                        </select>
                    </div>
                </div>

                <!-- Merge notice (shown only for child offshoots) -->
                <div id="edit-merge-notice" class="offshoot-merge-notice">
                    <i class="fas fa-link"></i>
                    <strong>This is an offshoot record.</strong>
                    Setting the status back to <strong>Operational</strong> will automatically merge it back into its parent asset and restore the quantity.
                </div>

                <!-- Partial split panel (shown only when qty > 1 and non-operational status is selected) -->
                <div id="edit-split-wrapper" class="split-panel">
                    <label class="panel-title"><i class="fas fa-scissors"></i> Partial Status Change — How many items are affected?</label>
                    <p class="panel-hint">Only some items in this batch need this status. The rest will remain Operational as a separate record.</p>
                    <div style="display:flex; align-items:center; gap:12px;">
                        <div style="flex:1;">
                            <label style="font-size:12px; color:#166534; font-weight:600;">Affected Items <span style="font-weight:400;">(out of <strong id="edit-split-max">1</strong> total)</span></label>
                            <input type="number" id="edit-affected-quantity" name="affected_quantity" class="form-control" min="1" value="1" style="margin-top:4px;">
                        </div>
                        <div style="padding-top:22px; font-size:12px; color:#166534;">
                            <i class="fas fa-info-circle"></i> Set equal to total to update all without splitting
                        </div>
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

<!-- REACTIVATE ASSET MODAL -->
<div id="reactivateModal" class="modal">
    <div class="modal-content" style="max-width: 480px;">
        <div class="modal-header" style="background:#ecfdf5;">
            <h3 style="color:#065f46;"><i class="fas fa-undo"></i> Reactivate Asset</h3>
            <button type="button" class="modal-close" onclick="closeModal('reactivateModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="reactivate">
            <input type="hidden" id="reactivate-id" name="id">
            <div class="modal-body" style="padding:20px 24px;">
                <p style="font-size:13px; color:#475569; margin-bottom:14px;">
                    You are restoring <strong id="reactivate-asset-title"></strong> back to <strong>Operational</strong> status. It will move to the Active Inventory tab.
                </p>
                <div class="form-group">
                    <label>Recommissioning Reason / Notes</label>
                    <input type="text" name="reactivate_notes" class="form-control" placeholder="e.g. Asset repaired / recommissioned into service" required value="Asset repaired and recommissioned into service.">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('reactivateModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="background:#10b981;border-color:#10b981;"><i class="fas fa-check"></i> Confirm Reactivation</button>
            </div>
        </form>
    </div>
</div>

<!-- HIDDEN FORM FOR CATEGORY DELETION -->
<form id="delete-category-form" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete_category">
    <input type="hidden" name="category_id" id="delete-category-id">
</form>

<script>
    let currentEditingAsset = null;

    function openReactivateModal(asset) {
        document.getElementById('reactivate-id').value = asset.id;
        document.getElementById('reactivate-asset-title').textContent = asset.name + ' (' + asset.asset_id + ')';
        document.getElementById('reactivateModal').classList.add('open');
    }

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

        // --- Partial-split / merge-notice wiring ---
        const qty   = parseInt(asset.quantity) || 1;
        const isChild = !!asset.parent_asset_id;

        // Merge notice: visible only for child offshoots
        const mergeNotice = document.getElementById('edit-merge-notice');
        if (mergeNotice) mergeNotice.style.display = isChild ? 'block' : 'none';

        // Split panel: set max and default, then show/hide
        const splitMax = document.getElementById('edit-split-max');
        const affectedInput = document.getElementById('edit-affected-quantity');
        if (splitMax) splitMax.textContent = qty;
        if (affectedInput) { affectedInput.max = qty; affectedInput.value = qty; }

        toggleSplitPanel(asset.condition_status, qty, isChild);
        document.getElementById('editModal').classList.add('open');
    }

    function toggleSplitPanel(status, qty, isChild) {
        const nonOp = ['Damaged', 'Needs Inspection', 'Under Maintenance'].includes(status);
        const wrapper = document.getElementById('edit-split-wrapper');
        // Show split panel only for parent assets (not children) with qty > 1 and a non-operational status
        if (wrapper) wrapper.style.display = (nonOp && qty > 1 && !isChild) ? 'block' : 'none';
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

        // Include affected qty row in review if split panel is active
        const splitWrapper = document.getElementById('edit-split-wrapper');
        if (splitWrapper && splitWrapper.style.display !== 'none') {
            const affectedQty = parseInt(document.getElementById('edit-affected-quantity').value) || 0;
            const totalQty    = parseInt(document.getElementById('edit-quantity').value) || 1;
            if (affectedQty > 0 && affectedQty < totalQty) {
                appendReviewRow('Affected Items (Split Off)', 'All ' + totalQty + ' items', affectedQty + ' item(s) — rest remain Operational');
                changesCount++;
            }
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


    function toggleGroup(catId) {
        const headerRow = document.querySelector('.group-header-row[data-cat-id="' + catId + '"]');
        const childRow  = document.querySelector('.group-child-row[data-parent-cat="' + catId + '"]');
        if (!headerRow || !childRow) return;

        const isOpen = childRow.classList.contains('open');

        if (isOpen) {
            childRow.classList.remove('open');
            headerRow.classList.remove('expanded');
        } else {
            childRow.classList.add('open');
            headerRow.classList.add('expanded');
        }
    }
</script>

</body>
</html>
