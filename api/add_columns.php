<?php
// Database migration for service_requests table
header('Content-Type: text/html; charset=utf-8');

// Database connection
$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "utility_system";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h3>Database Migration - Adding missing columns to service_requests</h3>";

    // Check if disconnection_reason column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM service_requests LIKE 'disconnection_reason'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE service_requests ADD COLUMN disconnection_reason text DEFAULT NULL AFTER email");
        echo "✓ Added disconnection_reason column<br>";
    } else {
        echo "✓ disconnection_reason column already exists<br>";
    }

    // Check if previous_account column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM service_requests LIKE 'previous_account'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE service_requests ADD COLUMN previous_account varchar(100) DEFAULT NULL AFTER disconnection_reason");
        echo "✓ Added previous_account column<br>";
    } else {
        echo "✓ previous_account column already exists<br>";
    }

    echo "<br><strong>Database migration completed successfully!</strong>";
} catch (PDOException $e) {
    echo "<span style='color: red;'><strong>Error:</strong> " . $e->getMessage() . "</span>";
}
?>
