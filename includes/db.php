<?php
// includes/db.php
// Load .env if not already loaded
if (!isset($GLOBALS['_ENV_LOADED'])) {
    $envPath = __DIR__ . '/../.env';
    if (is_readable($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) continue;
            if (!str_contains($line, '=')) continue;
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v);
            if ($k === '') continue;
            putenv("$k=$v");
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
        }
    }
    $GLOBALS['_ENV_LOADED'] = true;
}

$host     = getenv('DB_HOST') ?: '127.0.0.1';
$dbname   = getenv('DB_NAME') ?: 'utility_system';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Function to generate unique IDs
function generateAccountNumber() {
    return 'ACC-' . date('Ym') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

function generateBillNumber($utilityType = 'water') {
    $datePart = date('Ymd');
    $randomPart = strtoupper(substr(uniqid(), -6));
    $suffix = ($utilityType === 'electricity') ? '-ELEC' : '';
    return 'BILL-' . $datePart . '-' . $randomPart . $suffix;
}

function generatePaymentRef() {
    return 'PAY-' . date('YmdHis') . '-' . rand(1000, 9999);
}

function getConsumerByUserId($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM consumers WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

// Check if consumer exists for citizen
function checkConsumerExists($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM consumers WHERE user_id = ?");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    return $result['count'] > 0;
}

// Get water rate based on consumption
function getWaterRate($consumption) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT rate_per_unit, basic_charge 
        FROM water_rates 
        WHERE (? BETWEEN min_consumption AND max_consumption) 
           OR (? >= min_consumption AND max_consumption IS NULL)
        AND status = 'active' 
        ORDER BY min_consumption DESC 
        LIMIT 1
    ");
    $stmt->execute([$consumption, $consumption]);
    $result = $stmt->fetch();
    
    if (!$result) {
        // Default rates if no rate found
        if ($consumption <= 20) {
            return ['rate_per_unit' => 15.75, 'basic_charge' => 150.00];
        } elseif ($consumption <= 30) {
            return ['rate_per_unit' => 18.25, 'basic_charge' => 150.00];
        } else {
            return ['rate_per_unit' => 22.00, 'basic_charge' => 150.00];
        }
    }
    
    return $result;
}

// Get electricity rate based on consumption
function getElectricityRate($consumption) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT rate_per_unit, basic_charge 
        FROM electricity_rates 
        WHERE (? BETWEEN min_consumption AND max_consumption) 
           OR (? >= min_consumption AND max_consumption IS NULL)
        AND status = 'active' 
        ORDER BY min_consumption DESC 
        LIMIT 1
    ");
    $stmt->execute([$consumption, $consumption]);
    $result = $stmt->fetch();
    
    if (!$result) {
        // Default rates if no rate found
        if ($consumption <= 100) {
            return ['rate_per_unit' => 10.75, 'basic_charge' => 150.00];
        } elseif ($consumption <= 200) {
            return ['rate_per_unit' => 12.25, 'basic_charge' => 150.00];
        } else {
            return ['rate_per_unit' => 15.00, 'basic_charge' => 150.00];
        }
    }
    
    return $result;
}

// Get all consumers with their utility types
function getAllConsumers() {
    global $pdo;
    $stmt = $pdo->query("
        SELECT c.*, u.email, u.user_type 
        FROM consumers c 
        LEFT JOIN users u ON c.user_id = u.id 
        ORDER BY c.last_name, c.first_name
    ");
    return $stmt->fetchAll();
}

// Get bills by consumer ID
function getBillsByConsumerId($consumerId, $status = null) {
    global $pdo;
    
    if ($status) {
        $stmt = $pdo->prepare("
            SELECT * FROM billing 
            WHERE consumer_id = ? AND status = ?
            ORDER BY billing_month DESC
        ");
        $stmt->execute([$consumerId, $status]);
    } else {
        $stmt = $pdo->prepare("
            SELECT * FROM billing 
            WHERE consumer_id = ?
            ORDER BY billing_month DESC
        ");
        $stmt->execute([$consumerId]);
    }
    
    return $stmt->fetchAll();
}

// Get bill details by ID
function getBillById($billId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT b.*, c.first_name, c.last_name, c.account_number, 
               c.address, c.barangay, c.meter_number, c.electric_meter_number
        FROM billing b 
        JOIN consumers c ON b.consumer_id = c.id 
        WHERE b.id = ?
    ");
    $stmt->execute([$billId]);
    return $stmt->fetch();
}

// Update bill status
function updateBillStatus($billId, $status, $paidAt = null) {
    global $pdo;
    
    if ($paidAt) {
        $stmt = $pdo->prepare("
            UPDATE billing 
            SET status = ?, paid_at = ? 
            WHERE id = ?
        ");
        return $stmt->execute([$status, $paidAt, $billId]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE billing 
            SET status = ? 
            WHERE id = ?
        ");
        return $stmt->execute([$status, $billId]);
    }
}

// Get total amount by status
function getTotalAmountByStatus($status, $consumerId = null) {
    global $pdo;
    
    if ($consumerId) {
        $stmt = $pdo->prepare("
            SELECT SUM(total_amount) as total 
            FROM billing 
            WHERE status = ? AND consumer_id = ?
        ");
        $stmt->execute([$status, $consumerId]);
    } else {
        $stmt = $pdo->prepare("
            SELECT SUM(total_amount) as total 
            FROM billing 
            WHERE status = ?
        ");
        $stmt->execute([$status]);
    }
    
    $result = $stmt->fetch();
    return $result['total'] ?: 0;
}

// Check for overdue bills
function checkOverdueBills() {
    global $pdo;
    $stmt = $pdo->query("
        UPDATE billing 
        SET status = 'overdue' 
        WHERE status = 'pending' 
        AND due_date < CURDATE()
    ");
    return $stmt->rowCount();
}
?>