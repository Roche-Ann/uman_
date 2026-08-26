<?php
// install_waste.php
require_once 'includes/db.php';

header('Content-Type: text/plain');
echo "=======================================================\n";
echo " UMAN_ Waste Management Module — Database Installer\n";
echo "=======================================================\n\n";

try {
    // 1. Read and execute SQL schema
    $sqlFile = 'sql/utility_waste.sql';
    if (!file_exists($sqlFile)) {
        die("Error: SQL file '{$sqlFile}' not found.\n");
    }

    $sql = file_get_contents($sqlFile);
    // Split on semicolons, skip empties
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($statements as $stmt) {
        if (!empty($stmt)) {
            $pdo->exec($stmt);
        }
    }
    echo "[OK] Database tables and seed data created successfully.\n";

    // 2. Create uploads directory
    $uploadDir = 'uploads/waste_complaints/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
        echo "[OK] Created upload directory: {$uploadDir}\n";
    } else {
        echo "[OK] Upload directory already exists: {$uploadDir}\n";
    }

    // 3. Verify tables
    $tables = ['waste_routes','waste_route_stops','waste_trucks','waste_collection_records',
               'waste_complaints','waste_schedules','waste_facilities','waste_compliance','waste_notifications'];

    echo "\nVerifying tables:\n";
    foreach ($tables as $table) {
        $count = $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        echo "  ✓ {$table} — {$count} row(s)\n";
    }

    echo "\n=======================================================\n";
    echo " ✅ Waste Management Module installed successfully!\n";
    echo "=======================================================\n\n";
    echo "Next steps:\n";
    echo "  → Admin Map:    waste_truck_map.php\n";
    echo "  → Dashboard:    waste_dashboard.php\n";
    echo "  → Records:      waste_records.php\n";
    echo "  → Schedules:    waste_schedules.php\n";

} catch (PDOException $e) {
    echo "\n[ERROR] " . $e->getMessage() . "\n";
    echo "Please check your database connection and try again.\n";
} catch (Throwable $e) {
    echo "\n[ERROR] " . $e->getMessage() . "\n";
}
?>
