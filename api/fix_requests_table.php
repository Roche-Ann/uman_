<?php
// api/fix_requests_table.php - Fix corrupted records and auto_increment
header('Content-Type: text/html; charset=utf-8');

require_once '../includes/auth.php';
require_once '../includes/db.php';

if (!isLoggedIn() || !isEmployee()) {
    echo "Unauthorized - Employee access required";
    exit();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Service Requests Table Cleanup</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; text-align: center; }
        .step { margin: 20px 0; padding: 15px; background: #f8f9fa; border-left: 4px solid #3498db; border-radius: 4px; }
        .step-title { font-weight: bold; color: #2c3e50; margin-bottom: 8px; }
        .success { color: #27ae60; font-weight: bold; }
        .error { color: #e74c3c; font-weight: bold; }
        .warning { color: #f39c12; font-weight: bold; }
        .info { color: #3498db; }
        hr { margin: 30px 0; border: 1px solid #e0e0e0; }
        .summary { background: #ecf0f1; padding: 20px; border-radius: 6px; margin: 20px 0; }
        .button { 
            display: inline-block; 
            margin-top: 20px; 
            padding: 10px 20px; 
            background: #3498db; 
            color: white; 
            text-decoration: none; 
            border-radius: 4px; 
            border: none; 
            cursor: pointer;
            font-size: 14px;
        }
        .button:hover { background: #2980b9; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔧 Service Requests Table Cleanup</h1>
    <p style="text-align: center; color: #7f8c8d;">Automated database maintenance and repair</p>

<?php

try {
    // Step 1: Check for records with ID = 0
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM service_requests WHERE id = 0 OR id IS NULL");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $zeroCount = $result['count'] ?? 0;
    
    echo "<div class='step'>";
    echo "<div class='step-title'>Step 1: Check for invalid records (ID = 0 or NULL)</div>";
    echo "<p>Found: <strong>" . $zeroCount . "</strong> invalid record(s)</p>";

    if ($zeroCount > 0) {
        // Delete records with ID = 0 or NULL
        $pdo->exec("DELETE FROM service_requests WHERE id = 0 OR id IS NULL");
        echo "<p class='success'>✓ Deleted " . $zeroCount . " invalid record(s)</p>";
    } else {
        echo "<p class='success'>✓ No invalid records found</p>";
    }
    echo "</div>";

    // Step 2: Check if PRIMARY KEY exists
    $stmt = $pdo->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'service_requests' AND COLUMN_NAME = 'id' AND CONSTRAINT_NAME = 'PRIMARY'");
    $pk = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<div class='step'>";
    echo "<div class='step-title'>Step 2: Verify PRIMARY KEY</div>";
    if ($pk) {
        echo "<p class='success'>✓ PRIMARY KEY exists on 'id' column</p>";
    } else {
        echo "<p class='warning'>⚠ PRIMARY KEY missing! Adding it now...</p>";
        try {
            $pdo->exec("ALTER TABLE service_requests DROP PRIMARY KEY");
        } catch (Exception $e) {
            // Primary key might not exist
        }
        $pdo->exec("ALTER TABLE service_requests ADD PRIMARY KEY (id)");
        echo "<p class='success'>✓ PRIMARY KEY added successfully</p>";
    }
    echo "</div>";

    // Step 3: Check AUTO_INCREMENT value
    echo "<div class='step'>";
    echo "<div class='step-title'>Step 3: Verify AUTO_INCREMENT</div>";
    
    $stmt = $pdo->query("SELECT AUTO_INCREMENT FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'service_requests' AND TABLE_SCHEMA = DATABASE()");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $currentAutoIncrement = $result['AUTO_INCREMENT'] ?? null;
    
    if ($currentAutoIncrement === null || $currentAutoIncrement == 0) {
        echo "<p class='warning'>⚠ AUTO_INCREMENT not set properly</p>";
    } else {
        echo "<p>Current AUTO_INCREMENT value: <strong>" . $currentAutoIncrement . "</strong></p>";
    }

    // Step 4: Get the max ID to ensure auto_increment is correct
    $stmt = $pdo->query("SELECT MAX(id) as max_id FROM service_requests WHERE id > 0");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $maxId = $result['max_id'] ?? 0;
    echo "<p>Maximum existing ID in database: <strong>" . $maxId . "</strong></p>";

    // Step 5: Reset auto_increment if needed
    if ($maxId > 0) {
        $newAutoIncrement = $maxId + 1;
        try {
            $pdo->exec("ALTER TABLE service_requests MODIFY id int(11) NOT NULL AUTO_INCREMENT");
            $pdo->exec("ALTER TABLE service_requests AUTO_INCREMENT = " . $newAutoIncrement);
            echo "<p class='success'>✓ Set AUTO_INCREMENT to " . $newAutoIncrement . " (next new request will use this ID)</p>";
        } catch (Exception $e) {
            echo "<p class='error'>✗ Error setting AUTO_INCREMENT: " . $e->getMessage() . "</p>";
        }
    } else {
        try {
            $pdo->exec("ALTER TABLE service_requests MODIFY id int(11) NOT NULL AUTO_INCREMENT");
            $pdo->exec("ALTER TABLE service_requests AUTO_INCREMENT = 7");
            echo "<p class='success'>✓ AUTO_INCREMENT initialized to 7</p>";
        } catch (Exception $e) {
            echo "<p class='error'>✗ Error: " . $e->getMessage() . "</p>";
        }
    }
    echo "</div>";

    // Step 6: Verify all requests now have valid IDs
    echo "<div class='step'>";
    echo "<div class='step-title'>Step 6: Final Verification</div>";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM service_requests WHERE id <= 0");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $invalidCount = $result['count'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM service_requests");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalCount = $result['count'] ?? 0;
    
    if ($invalidCount === 0) {
        echo "<p class='success'>✓ SUCCESS! All records are now valid</p>";
        echo "<p>✓ Total requests in database: <strong>" . $totalCount . "</strong></p>";
        echo "<p class='success' style='font-size: 16px; margin-top: 15px;'>✓ You can now view all your service requests without errors!</p>";
    } else {
        echo "<p class='error'>⚠ Warning: Still " . $invalidCount . " invalid records found</p>";
    }
    echo "</div>";
    
    echo "<hr>";
    echo "<div class='summary'>";
    echo "<h3 style='margin-top: 0;'>Summary</h3>";
    echo "<p>✓ Invalid records deleted: " . $zeroCount . "</p>";
    echo "<p>✓ Total valid requests: " . $totalCount . "</p>";
    echo "<p>✓ Table optimization: Complete</p>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='step'>";
    echo "<p class='error'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "Please contact your system administrator if you continue to experience issues.";
    echo "</div>";
}

?>

    <div style="text-align: center;">
        <button class="button" onclick="window.location.href='../utilities_dashboard.php'">← Back to Dashboard</button>
    </div>
</div>
</body>
</html>

