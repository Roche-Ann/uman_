<?php
// install_waste.php — Direct PHP installer (no SQL file parsing issues)
require_once 'includes/db.php';

header('Content-Type: text/plain; charset=utf-8');
echo "=======================================================\n";
echo " UMAN_ Waste Management Module — Database Installer\n";
echo "=======================================================\n\n";

$errors = 0;

function run(PDO $pdo, string $label, string $sql): void {
    global $errors;
    try {
        $pdo->exec($sql);
        echo "[OK] $label\n";
    } catch (PDOException $e) {
        // Duplicate/already-exists errors are fine
        if (in_array($e->getCode(), ['42S01','42000']) || str_contains($e->getMessage(), 'already exists')) {
            echo "[SKIP] $label (already exists)\n";
        } else {
            echo "[WARN] $label — " . $e->getMessage() . "\n";
            $errors++;
        }
    }
}

$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

// ── 1. CREATE TABLES ─────────────────────────────────────────
run($pdo, "Table: waste_routes", "
CREATE TABLE IF NOT EXISTS waste_routes (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    route_name   VARCHAR(100) NOT NULL,
    color_hex    VARCHAR(10)  NOT NULL DEFAULT '#22c55e',
    district     VARCHAR(100),
    coverage     VARCHAR(255),
    start_time   TIME         NOT NULL DEFAULT '06:00:00',
    is_active    TINYINT(1)   NOT NULL DEFAULT 1,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
)");

run($pdo, "Table: waste_route_stops", "
CREATE TABLE IF NOT EXISTS waste_route_stops (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    route_id       INT          NOT NULL,
    stop_order     INT          NOT NULL,
    barangay_name  VARCHAR(100) NOT NULL,
    latitude       DECIMAL(10,7) NOT NULL,
    longitude      DECIMAL(10,7) NOT NULL,
    travel_min     INT          NOT NULL DEFAULT 15,
    service_min    INT          NOT NULL DEFAULT 10,
    waste_types    VARCHAR(100) DEFAULT 'Biodegradable, Non-biodegradable',
    FOREIGN KEY (route_id) REFERENCES waste_routes(id) ON DELETE CASCADE
)");

run($pdo, "Table: waste_trucks", "
CREATE TABLE IF NOT EXISTS waste_trucks (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    truck_id     VARCHAR(20)  NOT NULL UNIQUE,
    plate_number VARCHAR(20)  NOT NULL,
    driver_name  VARCHAR(100),
    route_id     INT,
    capacity_kg  INT          DEFAULT 5000,
    status       ENUM('Active','On Break','Breakdown','Off Duty') DEFAULT 'Active',
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (route_id) REFERENCES waste_routes(id) ON DELETE SET NULL
)");

run($pdo, "Table: waste_collection_records", "
CREATE TABLE IF NOT EXISTS waste_collection_records (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    record_id         VARCHAR(30)  NOT NULL UNIQUE,
    truck_id          INT,
    route_id          INT,
    date_collected    DATE         NOT NULL,
    volume_kg         DECIMAL(10,2) DEFAULT 0,
    waste_type        ENUM('Biodegradable','Non-biodegradable','Recyclable','Hazardous','Mixed') DEFAULT 'Mixed',
    crew_count        INT          DEFAULT 3,
    collection_status ENUM('Completed','Partial','Missed','Rescheduled') DEFAULT 'Completed',
    notes             TEXT,
    created_at        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (truck_id)  REFERENCES waste_trucks(id)  ON DELETE SET NULL,
    FOREIGN KEY (route_id)  REFERENCES waste_routes(id)  ON DELETE SET NULL
)");

run($pdo, "Table: waste_complaints", "
CREATE TABLE IF NOT EXISTS waste_complaints (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id    VARCHAR(30)  NOT NULL UNIQUE,
    user_id         INT,
    complaint_type  ENUM('Missed Collection','Illegal Dumping') NOT NULL,
    description     TEXT,
    barangay        VARCHAR(100),
    address_detail  VARCHAR(255),
    latitude        DECIMAL(10,7),
    longitude       DECIMAL(10,7),
    photo_path      VARCHAR(500),
    status          ENUM('Pending','Under Review','Resolved','Dismissed') DEFAULT 'Pending',
    admin_notes     TEXT,
    resolved_at     TIMESTAMP    NULL,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
)");

run($pdo, "Table: waste_schedules", "
CREATE TABLE IF NOT EXISTS waste_schedules (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    zone_name    VARCHAR(100) NOT NULL,
    barangay     VARCHAR(100),
    day_of_week  ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
    time_slot    VARCHAR(20)  DEFAULT '6:00 AM',
    truck_id     INT,
    route_id     INT,
    waste_type   VARCHAR(100) DEFAULT 'Mixed',
    status       ENUM('Active','Suspended','Completed','Missed','Rescheduled') DEFAULT 'Active',
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (truck_id)  REFERENCES waste_trucks(id)  ON DELETE SET NULL,
    FOREIGN KEY (route_id)  REFERENCES waste_routes(id)  ON DELETE SET NULL
)");

run($pdo, "Table: waste_facilities", "
CREATE TABLE IF NOT EXISTS waste_facilities (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    facility_name    VARCHAR(150) NOT NULL,
    facility_type    ENUM('MRF','Dumpsite','Composting','Transfer Station') NOT NULL,
    location         VARCHAR(255),
    latitude         DECIMAL(10,7),
    longitude        DECIMAL(10,7),
    capacity_tons    DECIMAL(10,2) DEFAULT 0,
    current_load_tons DECIMAL(10,2) DEFAULT 0,
    status           ENUM('Operational','Full','Closed','Maintenance') DEFAULT 'Operational',
    last_updated     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
)");

run($pdo, "Table: waste_compliance", "
CREATE TABLE IF NOT EXISTS waste_compliance (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    zone_name        VARCHAR(100) NOT NULL,
    barangay         VARCHAR(100),
    audit_date       DATE         NOT NULL,
    compliance_rate  DECIMAL(5,2) DEFAULT 0,
    audited_by       VARCHAR(100),
    notes            TEXT,
    created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
)");

run($pdo, "Table: waste_notifications", "
CREATE TABLE IF NOT EXISTS waste_notifications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    message    TEXT         NOT NULL,
    type       ENUM('Missed Collection','New Complaint','Facility Alert','Compliance Alert','General') DEFAULT 'General',
    is_read    TINYINT(1)   DEFAULT 0,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
)");

// ── 2. VIEW ───────────────────────────────────────────────────
run($pdo, "View: aggregated_waste_view", "
CREATE OR REPLACE VIEW aggregated_waste_view AS
SELECT
    (SELECT COUNT(*) FROM waste_collection_records
     WHERE MONTH(date_collected)=MONTH(CURDATE()) AND YEAR(date_collected)=YEAR(CURDATE())) AS monthly_collections,
    (SELECT COALESCE(SUM(volume_kg),0) FROM waste_collection_records
     WHERE MONTH(date_collected)=MONTH(CURDATE())) AS monthly_volume_kg,
    (SELECT COUNT(*) FROM waste_complaints WHERE status IN ('Pending','Under Review')) AS open_complaints,
    (SELECT COUNT(*) FROM waste_trucks WHERE status='Active') AS active_trucks,
    (SELECT COUNT(*) FROM waste_routes WHERE is_active=1) AS active_routes,
    (SELECT COALESCE(AVG(compliance_rate),0) FROM waste_compliance
     WHERE MONTH(audit_date)=MONTH(CURDATE())) AS avg_compliance_rate
");

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

// ── 3. SEED ROUTES ────────────────────────────────────────────
echo "\nSeeding routes...\n";

// Clear existing seed data to re-insert cleanly
try { $pdo->exec("DELETE FROM waste_route_stops WHERE route_id <= 6"); } catch(Throwable $e){}
try { $pdo->exec("DELETE FROM waste_trucks WHERE route_id <= 6 OR route_id IS NULL"); } catch(Throwable $e){}
try { $pdo->exec("DELETE FROM waste_routes WHERE id <= 6"); } catch(Throwable $e){}

$routeStmt = $pdo->prepare("INSERT INTO waste_routes (id,route_name,color_hex,district,coverage,start_time) VALUES (?,?,?,?,?,?)");
$routes = [
    [1, 'Commonwealth–Batasan Corridor', '#16a34a', 'Batasan Hills District',      'Commonwealth Ave, UP Diliman, Batasan Rd, Holy Spirit', '06:00:00'],
    [2, 'Quezon Ave–Timog Circuit',      '#2563eb', 'Apolonio Samson District',    'Quezon Ave, Timog Ave, Scout Area, EDSA',               '06:30:00'],
    [3, 'Balintawak–Fairview North',     '#ea580c', 'Novaliches District',         'Mindanao Ave, Quirino Hwy, Fairview, Regalado Ave',      '06:00:00'],
    [4, 'Cubao–Aurora Boulevard East',   '#dc2626', 'Matandang Balara District',   'EDSA Cubao, Aurora Blvd, Eastwood, Libis',              '06:30:00'],
    [5, 'Novaliches–Sauyo Loop',         '#7c3aed', 'San Bartolome District',      'Novaliches Proper, Sauyo Rd, Lagro, Nova Market',       '06:00:00'],
    [6, 'Kamuning–Project 6 Circuit',    '#ca8a04', 'Commonwealth District',       'EDSA Kamuning, Tomas Morato, Project 4, Project 6',     '07:00:00'],
];
foreach ($routes as $r) { $routeStmt->execute($r); }
echo "[OK] 6 routes inserted\n";

// ── 4. SEED STOPS ─────────────────────────────────────────────
$stopStmt = $pdo->prepare("INSERT INTO waste_route_stops (route_id,stop_order,barangay_name,latitude,longitude,travel_min,service_min) VALUES (?,?,?,?,?,?,?)");
$stops = [
    // Route 1 — Commonwealth–Batasan
    [1,1,'Batasan Hills',   14.6957, 121.1050,  0, 10],
    [1,2,'Holy Spirit',     14.6889, 121.0894, 18, 12],
    [1,3,'Commonwealth',    14.6803, 121.0756, 15, 10],
    [1,4,'UP Campus Area',  14.6547, 121.0644, 20, 10],
    [1,5,'Diliman',         14.6507, 121.0695, 10,  8],
    [1,6,'Loyola Heights',  14.6432, 121.0784, 12,  8],
    // Route 2 — Quezon Ave–Timog
    [2,1,'Quezon Avenue',   14.6411, 121.0153,  0, 10],
    [2,2,'Sacred Heart',    14.6387, 121.0203, 10,  8],
    [2,3,'Timog Avenue',    14.6363, 121.0308, 12,  8],
    [2,4,'South Triangle',  14.6339, 121.0358,  8,  8],
    [2,5,'Scout Area',      14.6306, 121.0408, 10, 10],
    [2,6,'EDSA-Quezon',     14.6284, 121.0506, 15,  8],
    // Route 3 — Balintawak–Fairview
    [3,1,'Balintawak',      14.6567, 120.9831,  0, 10],
    [3,2,'Tandang Sora',    14.6695, 121.0305, 20, 12],
    [3,3,'Fairview',        14.7211, 121.0578, 25, 15],
    [3,4,'Greater Lagro',   14.7358, 121.0506, 12, 10],
    [3,5,'Regalado',        14.7472, 121.0428, 12,  8],
    [3,6,'North Fairview',  14.7556, 121.0436,  8,  8],
    // Route 4 — Cubao–Aurora East
    [4,1,'Cubao',           14.6195, 121.0528,  0, 10],
    [4,2,'New Manila',      14.6211, 121.0389, 10,  8],
    [4,3,'Aurora Blvd',     14.6183, 121.0567,  8, 10],
    [4,4,'Anonas',          14.6142, 121.0628, 10,  8],
    [4,5,'Libis',           14.5989, 121.0700, 15, 10],
    [4,6,'Eastwood',        14.6092, 121.0789, 12,  8],
    // Route 5 — Novaliches–Sauyo
    [5,1,'Novaliches Proper',14.7272,121.0167,  0, 12],
    [5,2,'Sauyo',            14.7028,121.0122, 18, 10],
    [5,3,'Lagro',            14.7172,121.0344, 15, 10],
    [5,4,'San Agustin',      14.7089,121.0278, 10,  8],
    [5,5,'Sta. Lucia',       14.6983,121.0211, 12,  8],
    [5,6,'Novaliches Market',14.7233,121.0128, 15, 10],
    // Route 6 — Kamuning–Project 6
    [6,1,'Kamuning',        14.6331, 121.0286,  0, 10],
    [6,2,'Tomas Morato',    14.6378, 121.0347,  8,  8],
    [6,3,'Paligsahan',      14.6353, 121.0197, 10,  8],
    [6,4,'Project 4',       14.6283, 121.0614, 20, 10],
    [6,5,'Project 6',       14.6453, 121.0131, 18, 10],
    [6,6,'Sto. Domingo',    14.6406, 121.0214, 10,  8],
];
foreach ($stops as $s) { $stopStmt->execute($s); }
echo "[OK] 36 stops inserted\n";

// ── 5. SEED TRUCKS ────────────────────────────────────────────
$truckStmt = $pdo->prepare("INSERT INTO waste_trucks (truck_id,plate_number,driver_name,route_id,capacity_kg,status) VALUES (?,?,?,?,?,?)");
$trucks = [
    ['QC-GT-001','ABC 1234','Pedro Santos',    1, 5000, 'Active'],
    ['QC-GT-002','DEF 5678','Maria Reyes',     2, 5000, 'Active'],
    ['QC-GT-003','GHI 9012','Juan dela Cruz',  3, 6000, 'Active'],
    ['QC-GT-004','JKL 3456','Ana Gonzales',    4, 5000, 'Active'],
    ['QC-GT-005','MNO 7890','Carlos Bautista', 5, 5000, 'Active'],
    ['QC-GT-006','PQR 2345','Rosa Mendoza',    6, 4500, 'Active'],
];
foreach ($trucks as $t) {
    try { $truckStmt->execute($t); } catch(PDOException $e){ echo "[SKIP] Truck {$t[0]} (already exists)\n"; }
}
echo "[OK] 6 trucks inserted\n";

// ── 6. SEED FACILITIES ────────────────────────────────────────
$facStmt = $pdo->prepare("INSERT IGNORE INTO waste_facilities (facility_name,facility_type,location,latitude,longitude,capacity_tons,current_load_tons,status) VALUES (?,?,?,?,?,?,?,?)");
$facilities = [
    ['QC Material Recovery Facility - Batasan','MRF',              'Batasan Hills, QC', 14.6940,121.1100,500,310,'Operational'],
    ['Fairview Composting Site',               'Composting',       'Fairview, QC',      14.7300,121.0550,200, 80,'Operational'],
    ['Novaliches Transfer Station',            'Transfer Station', 'Novaliches, QC',    14.7250,121.0200,800,620,'Operational'],
];
foreach ($facilities as $f) { $facStmt->execute($f); }
echo "[OK] 3 facilities inserted\n";

// ── 7. CREATE UPLOADS DIRECTORY ───────────────────────────────
$uploadDir = __DIR__ . '/uploads/waste_complaints/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
    echo "[OK] Created: uploads/waste_complaints/\n";
} else {
    echo "[OK] Upload directory exists\n";
}

// ── 8. VERIFY ─────────────────────────────────────────────────
echo "\nVerifying tables:\n";
$tables = ['waste_routes','waste_route_stops','waste_trucks','waste_collection_records',
           'waste_complaints','waste_schedules','waste_facilities','waste_compliance','waste_notifications'];
foreach ($tables as $table) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        echo "  ✓ {$table} — {$count} row(s)\n";
    } catch(Throwable $e){ echo "  ✗ {$table} — MISSING\n"; $errors++; }
}

echo "\n=======================================================\n";
if ($errors === 0) {
    echo " ✅ Waste Management Module installed successfully!\n";
} else {
    echo " ⚠  Installed with {$errors} warning(s). See above.\n";
}
echo "=======================================================\n\n";
echo "  → Route Map:  waste_truck_map.php\n";
echo "  → Dashboard:  waste_dashboard.php\n";
echo "  → Records:    waste_records.php\n";
echo "  → Schedules:  waste_schedules.php\n";
?>
