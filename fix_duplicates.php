<?php
require 'includes/db.php';

echo "<h1>Merging duplicate utility assets...</h1>";
echo "<pre>";

// Find duplicates based on asset_id, facility_id, condition_status, custody_status
$stmt = $pdo->query("
    SELECT asset_id, cprf_facility_id, condition_status, cprf_custody_status, COUNT(*) as cnt
    FROM utility_assets
    GROUP BY asset_id, cprf_facility_id, condition_status, cprf_custody_status
    HAVING cnt > 1
");

$duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($duplicates)) {
    echo "No duplicates found.\n";
} else {
    foreach ($duplicates as $dup) {
        $q = "SELECT id, quantity FROM utility_assets WHERE asset_id = ? AND condition_status = ? AND cprf_custody_status = ?";
        $params = [$dup['asset_id'], $dup['condition_status'], $dup['cprf_custody_status']];
        
        if ($dup['cprf_facility_id'] === null) {
            $q .= " AND cprf_facility_id IS NULL";
        } else {
            $q .= " AND cprf_facility_id = ?";
            $params[] = $dup['cprf_facility_id'];
        }
        
        $rows = $pdo->prepare($q);
        $rows->execute($params);
        $records = $rows->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($records) > 1) {
            $keepId = $records[0]['id'];
            $totalQty = 0;
            $deleteIds = [];
            
            foreach ($records as $i => $rec) {
                $totalQty += (int)$rec['quantity'];
                if ($i > 0) {
                    $deleteIds[] = $rec['id'];
                }
            }
            
            echo "Merging {$dup['asset_id']} - keeping ID {$keepId}, total qty {$totalQty}. Deleting IDs: " . implode(',', $deleteIds) . "\n";
            
            $pdo->prepare("UPDATE utility_assets SET quantity = ? WHERE id = ?")->execute([$totalQty, $keepId]);
            
            $in = str_repeat('?,', count($deleteIds) - 1) . '?';
            $pdo->prepare("DELETE FROM utility_assets WHERE id IN ($in)")->execute($deleteIds);
        }
    }
}
echo "Done.</pre>";
