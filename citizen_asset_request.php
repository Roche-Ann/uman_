<?php
/**
 * LGU Citizen Portal: Asset / Equipment Request
 * Allows citizens to request utility assets & equipment from the LGU employee side,
 * track request statuses, and view review notes/fulfillment details.
 */
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'] ?? 0;
$userName = trim((string)($_SESSION['user_name'] ?? $_SESSION['full_name'] ?? 'Resident'));
$userEmail = trim((string)($_SESSION['email'] ?? ''));

// Identity Verification Check
try {
    $stmt = $pdo->prepare("SELECT verification_status FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userVerifStatus = $stmt->fetchColumn() ?: 'unverified';
    if ($userVerifStatus !== 'verified') {
        header('Location: citizen_verification.php');
        exit();
    }
} catch (Exception $e) {
    // If column doesn't exist yet, ignore
}

// Self-healing DB Schema for external_asset_requests
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `external_asset_requests` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `request_ref` VARCHAR(50) NOT NULL UNIQUE,
          `source_system` VARCHAR(50) NOT NULL DEFAULT 'CPRF',
          `cprf_facility_id` INT NOT NULL DEFAULT 0,
          `citizen_user_id` INT NULL,
          `requester_name` VARCHAR(150) NULL,
          `requester_contact` VARCHAR(100) NULL,
          `facility_name` VARCHAR(150) NOT NULL,
          `asset_type` VARCHAR(100) NOT NULL,
          `asset_type_id` INT NULL,
          `requested_asset_code` VARCHAR(50) NULL,
          `exact_match` TINYINT(1) NOT NULL DEFAULT 0,
          `quantity` INT NOT NULL DEFAULT 1,
          `urgency` ENUM('Routine','Priority','Emergency') NOT NULL DEFAULT 'Routine',
          `date_needed` DATE NULL,
          `booking_ref` VARCHAR(80) NULL,
          `event_purpose` VARCHAR(200) NULL,
          `responsible_office` VARCHAR(100) NULL,
          `notes` TEXT NULL,
          `status` ENUM('pending', 'approved', 'fulfilled', 'rejected') NOT NULL DEFAULT 'pending',
          `fulfilled_asset_id` INT NULL,
          `review_notes` TEXT NULL,
          `is_archived` TINYINT(1) NOT NULL DEFAULT 0,
          `archived_at` TIMESTAMP NULL,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_ear_status (status),
          INDEX idx_ear_citizen (citizen_user_id),
          INDEX idx_ear_source (source_system)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $addCol = function (string $colDef) use ($pdo): void {
        try { $pdo->exec("ALTER TABLE `external_asset_requests` ADD COLUMN $colDef"); }
        catch (Throwable $e) {}
    };
    $addCol("`citizen_user_id` INT NULL AFTER `cprf_facility_id`");
    $addCol("`requester_name` VARCHAR(150) NULL AFTER `citizen_user_id`");
    $addCol("`requester_contact` VARCHAR(100) NULL AFTER `requester_name`");
} catch (Throwable $e) {
    // Continue if tables/columns already exist
}

$errors = [];
$successes = [];

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    
    if ($action === 'submit_request') {
        $assetTypeSelect = trim((string)($_POST['asset_type_select'] ?? ''));
        $assetTypeCustom = trim((string)($_POST['asset_type_custom'] ?? ''));
        $assetType = ($assetTypeSelect === 'Other' && $assetTypeCustom !== '') ? $assetTypeCustom : $assetTypeSelect;
        
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));
        $urgency = in_array($_POST['urgency'] ?? '', ['Routine', 'Priority', 'Emergency'], true) ? $_POST['urgency'] : 'Routine';
        $dateNeeded = trim((string)($_POST['date_needed'] ?? '')) ?: null;
        $returnDate = trim((string)($_POST['return_date'] ?? '')) ?: null;
        $eventPurpose = trim((string)($_POST['event_purpose'] ?? ''));
        $locationName = trim((string)($_POST['facility_name'] ?? ''));
        $contactNumber = trim((string)($_POST['contact_number'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));
        $requestedAssetCode = trim((string)($_POST['requested_asset_code'] ?? '')) ?: null;

        if ($assetType === '') {
            $errors[] = 'Please select or specify the equipment / asset type requested.';
        }
        if ($locationName === '') {
            $errors[] = 'Please enter the delivery location or venue address.';
        }
        if ($eventPurpose === '') {
            $errors[] = 'Please provide the purpose or event description.';
        }
        if (!$dateNeeded) {
            $errors[] = 'Date needed is required.';
        }
        if (!$returnDate) {
            $errors[] = 'Return date is required.';
        }
        if ($dateNeeded && $returnDate && strtotime($returnDate) < strtotime($dateNeeded)) {
            $errors[] = 'Return date cannot be earlier than the date needed.';
        }

        if (empty($errors)) {
            try {
                // Generate Citizen Request Reference
                $prefix = 'CITIZEN-REQ-' . date('Ym') . '-';
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM external_asset_requests WHERE request_ref LIKE ?");
                $countStmt->execute([$prefix . '%']);
                $seq = (int)$countStmt->fetchColumn() + 1;
                $requestRef = $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);

                $contactStr = $userEmail;
                if ($contactNumber !== '') {
                    $contactStr .= ($contactStr !== '' ? ' | Tel: ' : '') . $contactNumber;
                }

                $stmt = $pdo->prepare("
                    INSERT INTO external_asset_requests
                        (request_ref, source_system, cprf_facility_id, citizen_user_id, requester_name, requester_contact,
                         facility_name, asset_type, requested_asset_code, quantity, urgency, date_needed, return_date, event_purpose, notes, status)
                    VALUES (?, 'Citizen Portal', 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                ");
                $stmt->execute([
                    $requestRef,
                    $userId,
                    $userName,
                    $contactStr,
                    $locationName,
                    $assetType,
                    $requestedAssetCode,
                    $quantity,
                    $urgency,
                    $dateNeeded,
                    $returnDate,
                    $eventPurpose,
                    $notes
                ]);

                // Create alert notification for employees
                try {
                    $urgencyTag = $urgency === 'Routine' ? '' : "[{$urgency}] ";
                    $whenTag = $dateNeeded ? " · needed by {$dateNeeded}" : '';
                    $notifMsg = "{$urgencyTag}New Citizen Asset Request {$requestRef} by {$userName}: {$quantity}x {$assetType}{$whenTag} for {$eventPurpose} at {$locationName}";
                    $pdo->prepare("INSERT INTO asset_notifications (type, message) VALUES ('citizen_request', ?)")
                        ->execute([$notifMsg]);
                } catch (Throwable $e) {}

                $successes[] = "Asset request <strong>{$requestRef}</strong> submitted successfully! It has been forwarded to the LGU Asset Management team for review.";
            } catch (Throwable $e) {
                $errors[] = "Failed to submit asset request: " . htmlspecialchars($e->getMessage());
            }
        }
    }
}

// Fetch available asset categories for dropdown
$assetTypes = [];
$availableAssetsList = [];
try {
    $assetTypes = $pdo->query("SELECT id, name, description FROM asset_types ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $availableAssetsList = $pdo->query("SELECT a.asset_id, a.name, t.name AS type_name FROM utility_assets a JOIN asset_types t ON a.asset_type_id = t.id WHERE a.condition_status IN ('Operational', 'Needs Inspection') ORDER BY a.name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {}

// Fetch citizen's existing requests
$myRequests = [];
try {
    $stmt = $pdo->prepare("
        SELECT r.*, a.name AS fulfilled_asset_name, a.asset_id AS fulfilled_asset_code
        FROM external_asset_requests r
        LEFT JOIN utility_assets a ON a.id = r.fulfilled_asset_id
        WHERE r.citizen_user_id = ? OR (r.source_system = 'Citizen Portal' AND r.requester_name = ?)
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$userId, $userName]);
    $myRequests = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {}
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
    <title>Request Assets & Equipment — LGU Citizen Portal</title>
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }

        body {
            min-height: 100vh;
            display: flex;
            background: url("assets/images/cityhall.jpeg") center/cover no-repeat fixed;
            position: relative;
        }

        body::before {
            content: "";
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
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

        .main-content.collapsed { margin-left: 90px; }

        .card {
            width: 100%;
            max-width: 1700px;
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(15px);
            border-radius: 18px;
            padding: 35px;
            color: #000;
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,0.25);
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .dashboard-header h1 {
            color: #2c3e50;
            font-size: 28px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .dashboard-header h1 i { color: #3762c8; }

        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-error { background-color: #fde8e8; color: #c0392b; border: 1px solid #f8b4b4; }
        .alert-success { background-color: #e2fbe8; color: #1e7e34; border: 1px solid #b8f0c5; }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-primary { background: #3762c8; color: white; }
        .btn-primary:hover { background: #2851b0; transform: translateY(-1px); }
        .btn-outline { background: transparent; border: 1px solid #cbd5e1; color: #64748b; }
        .btn-outline:hover { background: #f8f9fa; color: #2c3e50; }

        /* Form styling */
        .form-section {
            background: white;
            border-radius: 14px;
            padding: 28px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.06);
            margin-bottom: 35px;
        }
        .form-section h3 {
            font-size: 17px;
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 2px solid #f1f2f6;
            padding-bottom: 10px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        .form-group label {
            font-size: 13px;
            font-weight: 600;
            color: #334155;
        }
        .form-group label span.req { color: #e11d48; }
        .form-control {
            padding: 11px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            color: #1e293b;
            background: #f8fafc;
            transition: all 0.2s ease;
        }
        .form-control:focus {
            outline: none;
            border-color: #3762c8;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(55, 98, 200, 0.15);
        }
        textarea.form-control { resize: vertical; min-height: 80px; }

        /* Table styling */
        .table-section {
            background: white;
            border-radius: 14px;
            padding: 28px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.06);
            overflow-x: auto;
        }
        .table-section h3 {
            font-size: 17px;
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 2px solid #f1f2f6;
            padding-bottom: 10px;
        }
        .req-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .req-table th {
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #64748b;
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
        }
        .req-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
            color: #334155;
        }
        .req-table tr:hover td { background: rgba(55, 98, 200, 0.03); }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge.pending   { background: linear-gradient(135deg,#fef3c7,#fde68a); color: #92400e; border: 1px solid #fbbf24; }
        .badge.approved  { background: linear-gradient(135deg,#dbeafe,#bfdbfe); color: #1e40af; border: 1px solid #60a5fa; }
        .badge.fulfilled { background: linear-gradient(135deg,#d1fae5,#a7f3d0); color: #065f46; border: 1px solid #34d399; }
        .badge.rejected  { background: linear-gradient(135deg,#fee2e2,#fecaca); color: #991b1b; border: 1px solid #f87171; }
        
        .badge.urgency-routine { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
        .badge.urgency-priority { background: #fef3c7; color: #b45309; border: 1px solid #fcd34d; }
        .badge.urgency-emergency { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        .empty-state {
            text-align: center;
            padding: 45px 20px;
            color: #64748b;
        }
        .empty-state i { font-size: 44px; color: #cbd5e1; margin-bottom: 12px; }

        @media (max-width: 900px) {
            /* Wrap table in a scrollable div is NOT needed — we convert to cards */
            .req-table, 
            .req-table thead, 
            .req-table tbody, 
            .req-table th, 
            .req-table td, 
            .req-table tr {
                display: block;
                width: 100%;
                box-sizing: border-box;
            }
            .req-table thead {
                display: none;
            }
            /* Each row becomes a card */
            .req-table tr {
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 14px;
                margin-bottom: 14px;
                padding: 14px 16px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
                width: 100%;
                overflow: hidden;
            }
            .dark-theme .req-table tr {
                background: #0f172a;
                border-color: #1e293b;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            }
            /* Each cell: label stacked above value */
            .req-table td {
                display: block;
                padding: 8px 0;
                border-bottom: 1px solid #e2e8f0;
                text-align: left;
                width: 100%;
            }
            .dark-theme .req-table td {
                border-bottom-color: #1e293b;
            }
            .req-table td:last-child {
                border-bottom: none;
                padding-bottom: 0;
            }
            .req-table td:first-child {
                padding-top: 0;
            }
            /* Label shown as tiny uppercase header above the value */
            .req-table td::before {
                content: attr(data-label);
                display: block;
                font-weight: 700;
                color: #94a3b8;
                text-transform: uppercase;
                font-size: 10px;
                letter-spacing: 0.6px;
                margin-bottom: 4px;
            }
            .req-table td .td-val {
                display: block;
                text-align: left;
                width: 100%;
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
                <h1><i class="fas fa-boxes-stacked"></i> LGU Asset & Equipment Requests</h1>
                <p style="color: #64748b; font-size: 14px; margin-top: 4px;">Request utility equipment, sound systems, generators, or community assets from the LGU.</p>
            </div>
            <div>
                <a href="citizen.php" class="btn btn-outline"><i class="fas fa-home"></i> Home Portal</a>
            </div>
        </div>

        <!-- Alerts -->
        <?php foreach ($errors as $err): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo $err; ?></div>
        <?php endforeach; ?>
        <?php foreach ($successes as $succ): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $succ; ?></div>
        <?php endforeach; ?>

        <!-- New Asset Request Form -->
        <div class="form-section">
            <h3><i class="fas fa-paper-plane" style="color: #3762c8;"></i> Submit New Asset / Equipment Request</h3>
            <form method="POST">
                <input type="hidden" name="action" value="submit_request">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="asset_type_select">Equipment / Asset Category <span class="req">*</span></label>
                        <select name="asset_type_select" id="asset_type_select" class="form-control" required onchange="toggleCustomAssetType(this.value)">
                            <option value="">Select equipment type...</option>
                            <?php foreach ($assetTypes as $t): ?>
                                <option value="<?php echo htmlspecialchars($t['name']); ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                            <?php endforeach; ?>
                            <option value="Water Pump & Hose">Water Pump & Hose</option>
                            <option value="Portable Generator">Portable Generator</option>
                            <option value="Tents & Canopies">Tents & Canopies</option>
                            <option value="Maintenance Tools">Maintenance Tools</option>
                            <option value="Other">Other (Specify below...)</option>
                        </select>
                    </div>

                    <div class="form-group" id="specific_asset_group" style="display:none;">
                        <label for="requested_asset_code" id="specific_asset_label">Specific Asset (Optional)</label>
                        <select name="requested_asset_code" id="requested_asset_code" class="form-control" style="display:none;">
                            <option value="">Any available</option>
                        </select>
                        <input type="text" name="asset_type_custom" id="asset_type_custom" class="form-control" placeholder="Specify custom asset name..." style="display:none;">
                    </div>

                    <div class="form-group">
                        <label for="quantity">Quantity Required <span class="req">*</span></label>
                        <input type="number" name="quantity" id="quantity" class="form-control" value="1" min="1" max="100" required>
                    </div>

                    <div class="form-group">
                        <label for="urgency">Urgency Level <span class="req">*</span></label>
                        <select name="urgency" id="urgency" class="form-control" required>
                            <option value="Routine">Routine (Standard Scheduling)</option>
                            <option value="Priority">Priority (High Priority Event)</option>
                            <option value="Emergency">Emergency (Immediate Calamity / Utility Breakdown)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="date_needed">Date Needed <span class="req">*</span></label>
                        <input type="date" name="date_needed" id="date_needed" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-group">
                        <label for="return_date">Return Date <span class="req">*</span></label>
                        <input type="date" name="return_date" id="return_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-group">
                        <label for="contact_number">Contact Number for Coordination</label>
                        <input type="text" name="contact_number" id="contact_number" class="form-control" placeholder="e.g. 0917-123-4567">
                    </div>

                    <div class="form-group full-width">
                        <label for="facility_name">Delivery / Setup Venue & Address <span class="req">*</span></label>
                        <input type="text" name="facility_name" id="facility_name" class="form-control" placeholder="e.g. Barangay 4 Multipurpose Hall, Plaza St., Poblacion" required>
                    </div>

                    <div class="form-group full-width">
                        <label for="event_purpose">Event / Request Purpose <span class="req">*</span></label>
                        <input type="text" name="event_purpose" id="event_purpose" class="form-control" placeholder="e.g. Community Clean-up Drive / Barangay General Assembly / Calamity Response" required>
                    </div>

                    <div class="form-group full-width">
                        <label for="notes">Additional Remarks or Setup Instructions</label>
                        <textarea name="notes" id="notes" class="form-control" placeholder="Specify any specific instructions, power requirements, or contact person details for delivery..."></textarea>
                    </div>
                </div>

                <div style="margin-top: 20px; text-align: right;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Request to LGU</button>
                </div>
            </form>
        </div>

        <!-- My Asset Requests List -->
        <div class="table-section">
            <h3><i class="fas fa-list-check" style="color: #3762c8;"></i> My Submitted Asset Requests</h3>
            
            <?php if (empty($myRequests)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p style="font-size: 15px; font-weight: 500;">You haven't submitted any asset requests yet.</p>
                    <p style="font-size: 13px; margin-top: 4px;">Fill out the form above to request equipment from the LGU Asset Management team.</p>
                </div>
            <?php else: ?>
                <table class="req-table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Equipment / Asset</th>
                            <th>Qty & Urgency</th>
                            <th>Venue & Purpose</th>
                            <th>Date Needed</th>
                            <th>Status</th>
                            <th>LGU Review Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($myRequests as $req): ?>
                            <tr>
                                <td data-label="Reference">
                                    <div class="td-val">
                                        <strong style="color:#1e293b;"><?php echo htmlspecialchars($req['request_ref']); ?></strong><br>
                                        <small style="color:#94a3b8; font-size:11px;"><?php echo date('M d, Y h:i A', strtotime($req['created_at'])); ?></small>
                                    </div>
                                </td>
                                <td data-label="Equipment / Asset">
                                    <div class="td-val">
                                        <strong style="font-size:14px; color:#2c3e50;"><?php echo htmlspecialchars($req['asset_type']); ?></strong>
                                    </div>
                                </td>
                                <td data-label="Qty & Urgency">
                                    <div class="td-val">
                                        <strong style="font-size:14px;"><?php echo (int)$req['quantity']; ?></strong> <span style="font-size:12px; color:#64748b;">unit<?php echo ((int)$req['quantity'] !== 1) ? 's' : ''; ?></span><br>
                                        <span class="badge urgency-<?php echo strtolower($req['urgency']); ?>" style="margin-top:4px;">
                                            <?php echo htmlspecialchars($req['urgency']); ?>
                                        </span>
                                    </div>
                                </td>
                                <td data-label="Venue & Purpose">
                                    <div class="td-val">
                                        <strong><?php echo htmlspecialchars($req['facility_name']); ?></strong><br>
                                        <small style="color:#475569; font-style:italic;">Purpose: <?php echo htmlspecialchars($req['event_purpose']); ?></small>
                                        <?php if (!empty($req['notes'])): ?>
                                            <br><small style="color:#64748b;">Note: <?php echo htmlspecialchars($req['notes']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td data-label="Date Needed">
                                    <div class="td-val">
                                        <?php if ($req['date_needed']): ?>
                                            <span style="font-weight:500; font-size:12px;"><i class="far fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($dateNeededStr = $req['date_needed'])); ?></span>
                                        <?php else: ?>
                                            <span style="color:#94a3b8; font-size:12px;">As soon as available</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td data-label="Status">
                                    <div class="td-val">
                                        <span class="badge <?php echo htmlspecialchars($req['status']); ?>">
                                            <?php if ($req['status'] === 'pending'): ?><i class="fas fa-clock"></i> Pending Review
                                            <?php elseif ($req['status'] === 'approved'): ?><i class="fas fa-thumbs-up"></i> Approved
                                            <?php elseif ($req['status'] === 'fulfilled'): ?><i class="fas fa-check-circle"></i> Fulfilled
                                            <?php elseif ($req['status'] === 'rejected'): ?><i class="fas fa-times-circle"></i> Rejected
                                            <?php endif; ?>
                                        </span>
                                        <?php if (!empty($req['fulfilled_asset_name'])): ?>
                                            <div style="font-size:11px; color:#059669; font-weight:600; margin-top:4px;">
                                                <i class="fas fa-link"></i> Assigned: <?php echo htmlspecialchars($req['fulfilled_asset_name'] . ' (' . $req['fulfilled_asset_code'] . ')'); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td data-label="LGU Review Remarks">
                                    <div class="td-val">
                                        <?php if (!empty($req['review_notes'])): ?>
                                            <span style="font-size:12px; color:#334155; font-style:italic;">"<?php echo htmlspecialchars($req['review_notes']); ?>"</span>
                                        <?php else: ?>
                                            <span style="color:#94a3b8; font-size:12px;">Awaiting staff feedback</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </div>
</main>

<script>
const availableAssetsJs = <?php echo json_encode($availableAssetsList, JSON_UNESCAPED_UNICODE); ?>;

function toggleCustomAssetType(val) {
    const specificGroup = document.getElementById('specific_asset_group');
    const customInput = document.getElementById('asset_type_custom');
    const specificSelect = document.getElementById('requested_asset_code');
    const specificLabel = document.getElementById('specific_asset_label');

    if (val === 'Other') {
        specificGroup.style.display = 'flex';
        specificSelect.style.display = 'none';
        specificSelect.innerHTML = '<option value="">Any available</option>';
        customInput.style.display = 'block';
        customInput.setAttribute('required', 'required');
        specificLabel.innerHTML = 'Specify Custom Asset Name <span class="req">*</span>';
    } else if (val) {
        const assets = availableAssetsJs.filter(a => a.type_name === val);
        if (assets.length > 0) {
            specificGroup.style.display = 'flex';
            customInput.style.display = 'none';
            customInput.removeAttribute('required');
            customInput.value = '';
            
            specificSelect.style.display = 'block';
            specificLabel.innerHTML = 'Specific Asset Preference (Optional)';
            
            let html = '<option value="">Any available ' + val + '</option>';
            assets.forEach(a => {
                html += '<option value="' + a.asset_id + '">' + a.name + ' (' + a.asset_id + ')</option>';
            });
            specificSelect.innerHTML = html;
        } else {
            specificGroup.style.display = 'none';
            customInput.removeAttribute('required');
            customInput.value = '';
            specificSelect.innerHTML = '<option value="">Any available</option>';
        }
    } else {
        specificGroup.style.display = 'none';
        customInput.removeAttribute('required');
        customInput.value = '';
    }
}
</script>

</body>
</html>
