<?php
// utilities_dashboard.php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$userType = $_SESSION['user_type'] ?? 'employee';
$userName = $_SESSION['user_name'] ?? $_SESSION['full_name'] ?? 'LGU Coordinator';

// -------------------------------------------------------------
// 1. Asset Inventory Direct Metrics
// -------------------------------------------------------------
$assetStats = [
    'total_assets' => 0,
    'total_units' => 0,
    'operational' => 0,
    'damaged' => 0,
    'needs_inspection' => 0,
    'under_maintenance' => 0,
    'decommissioned' => 0
];
try {
    $row = $pdo->query("SELECT 
        COUNT(*) as total_assets,
        COALESCE(SUM(quantity), 0) as total_units,
        SUM(CASE WHEN condition_status = 'Operational' THEN 1 ELSE 0 END) as operational,
        SUM(CASE WHEN condition_status = 'Damaged' THEN 1 ELSE 0 END) as damaged,
        SUM(CASE WHEN condition_status = 'Needs Inspection' THEN 1 ELSE 0 END) as needs_inspection,
        SUM(CASE WHEN condition_status = 'Under Maintenance' THEN 1 ELSE 0 END) as under_maintenance,
        SUM(CASE WHEN condition_status = 'Decommissioned' THEN 1 ELSE 0 END) as decommissioned
    FROM utility_assets")->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $assetStats = array_merge($assetStats, array_map('intval', $row));
    }
} catch (Throwable $e) {}

// -------------------------------------------------------------
// 2. Maintenance Pipeline Direct Metrics
// -------------------------------------------------------------
$maintenanceStats = [
    'total_requests' => 0,
    'active_requests' => 0,
    'emergency_requests' => 0,
    'reported' => 0,
    'scheduled' => 0,
    'in_progress' => 0,
    'testing' => 0,
    'completed' => 0
];
try {
    $row = $pdo->query("SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN status NOT IN ('Completed', 'Cancelled', 'Unrepairable') THEN 1 ELSE 0 END) as active_requests,
        SUM(CASE WHEN priority = 'Emergency' AND status NOT IN ('Completed', 'Cancelled') THEN 1 ELSE 0 END) as emergency_requests,
        SUM(CASE WHEN status = 'Reported' THEN 1 ELSE 0 END) as reported,
        SUM(CASE WHEN status = 'Scheduled' THEN 1 ELSE 0 END) as scheduled,
        SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'Testing' THEN 1 ELSE 0 END) as testing,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed
    FROM maintenance_requests")->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $maintenanceStats = array_merge($maintenanceStats, array_map('intval', $row));
    }
} catch (Throwable $e) {}

// -------------------------------------------------------------
// 3. Energy Consumption Direct Metrics
// -------------------------------------------------------------
$energyStats = [
    'total_consumption' => 0.0,
    'total_cost' => 0.0,
    'records_count' => 0
];
try {
    $row = $pdo->query("SELECT 
        COALESCE(SUM(consumption_kwh), 0) as total_consumption,
        COALESCE(SUM(cost), 0) as total_cost,
        COUNT(*) as records_count
    FROM energy_consumption_records")->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $energyStats['total_consumption'] = (float)$row['total_consumption'];
        $energyStats['total_cost'] = (float)$row['total_cost'];
        $energyStats['records_count'] = (int)$row['records_count'];
    }
} catch (Throwable $e) {}

$energySyncs = 0;
try {
    $energySyncs = (int)$pdo->query("SELECT COUNT(*) FROM energy_sync_logs WHERE status = 'Successful'")->fetchColumn();
} catch (Throwable $e) {}

// -------------------------------------------------------------
// 4. Water Consumption Direct Metrics
// -------------------------------------------------------------
$waterStats = [
    'total_consumption' => 0.0,
    'total_cost' => 0.0,
    'records_count' => 0,
    'pending_advisories' => 0
];
try {
    $row = $pdo->query("SELECT 
        COALESCE(SUM(consumption_m3), 0) as total_consumption,
        COALESCE(SUM(cost), 0) as total_cost,
        COUNT(*) as records_count
    FROM water_consumption_records")->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $waterStats['total_consumption'] = (float)$row['total_consumption'];
        $waterStats['total_cost'] = (float)$row['total_cost'];
        $waterStats['records_count'] = (int)$row['records_count'];
    }
} catch (Throwable $e) {}

try {
    $waterStats['pending_advisories'] = (int)$pdo->query("SELECT COUNT(*) FROM water_recommendations WHERE status = 'Pending'")->fetchColumn();
} catch (Throwable $e) {}

// -------------------------------------------------------------
// 5. UPAD Grid Inspections Direct Metrics
// -------------------------------------------------------------
$upadStats = [
    'total_requests' => 0,
    'pending_requests' => 0,
    'approved_requests' => 0,
    'urgent_requests' => 0
];
try {
    $row = $pdo->query("SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
        SUM(CASE WHEN status IN ('approved', 'auto_approved', 'completed') THEN 1 ELSE 0 END) as approved_requests,
        SUM(CASE WHEN priority = 'Urgent' THEN 1 ELSE 0 END) as urgent_requests
    FROM upad_inspection_requests")->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $upadStats = array_merge($upadStats, array_map('intval', $row));
    }
} catch (Throwable $e) {}

// -------------------------------------------------------------
// 6. Facility Equipment & CPRF Custody Direct Metrics
// -------------------------------------------------------------
$facilityStats = [
    'total_requests' => 0,
    'active_loans' => 0,
    'pending_approval' => 0,
    'returned' => 0
];
try {
    $row = $pdo->query("SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN status IN ('approved', 'fulfilled') THEN 1 ELSE 0 END) as active_loans,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_approval,
        SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) as returned
    FROM external_asset_requests")->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $facilityStats = array_merge($facilityStats, array_map('intval', $row));
    }
} catch (Throwable $e) {}

// -------------------------------------------------------------
// 7. Live Unified Coordination Activity Feed
// -------------------------------------------------------------
$allNotifications = [];

// A. Maintenance requests recent updates
try {
    $mntEvents = $pdo->query("SELECT 
        CONCAT('Work Order #', request_id, ' (', title, ') is ', status) as message,
        updated_at as created_at,
        'Maintenance' as type,
        'fa-tools' as icon,
        '#3b82f6' as color
    FROM maintenance_requests 
    ORDER BY updated_at DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { $mntEvents = []; }

// B. Asset recent condition or update
try {
    $assetEvents = $pdo->query("SELECT 
        CONCAT('Asset \"', name, '\" updated to ', condition_status) as message,
        updated_at as created_at,
        'Asset' as type,
        'fa-boxes' as icon,
        '#10b981' as color
    FROM utility_assets 
    ORDER BY updated_at DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { $assetEvents = []; }

// C. UPAD Inspection updates
try {
    $upadEvents = $pdo->query("SELECT 
        CONCAT('UPAD Grid: ', COALESCE(project_name, reference_id), ' [', priority, '] · ', status) as message,
        created_at,
        'UPAD Grid' as type,
        'fa-bolt' as icon,
        '#f59e0b' as color
    FROM upad_inspection_requests 
    ORDER BY created_at DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { $upadEvents = []; }

// D. Facility loans
try {
    $cprfEvents = $pdo->query("SELECT 
        CONCAT('Facility Loan: ', COALESCE(requested_by_name, 'Citizen'), ' requested ', COALESCE(asset_name, 'Item'), ' (', status, ')') as message,
        created_at,
        'Facility' as type,
        'fa-building' as icon,
        '#8b5cf6' as color
    FROM external_asset_requests 
    ORDER BY created_at DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { $cprfEvents = []; }

$allNotifications = array_merge($mntEvents, $assetEvents, $upadEvents, $cprfEvents);
usort($allNotifications, function ($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});
$allNotifications = array_slice($allNotifications, 0, 8);

// -------------------------------------------------------------
// 8. AI Command Center Dynamic Summarizer
// -------------------------------------------------------------
function generateAICentralSummary($assetStats, $maintenanceStats, $energyStats, $waterStats, $upadStats, $facilityStats) {
    $summary = "<strong>LGU Central AI Assistant Coordination Report (" . date('F Y') . ")</strong><br><br>";
    
    $summary .= "🏢 <strong>System-Wide Multi-Sector Overview:</strong><br>";
    $summary .= "• <strong>Asset Inventory:</strong> " . number_format($assetStats['total_assets']) . " registered assets with " . number_format($assetStats['total_units']) . " total units. ";
    if ($assetStats['damaged'] > 0 || $assetStats['under_maintenance'] > 0) {
        $summary .= "Current tracking flags <strong>" . $assetStats['damaged'] . " damaged</strong> and <strong>" . $assetStats['under_maintenance'] . " in maintenance</strong>.<br>";
    } else {
        $summary .= "All assets reporting nominal operational status.<br>";
    }
    
    $summary .= "• <strong>Maintenance Pipeline:</strong> " . number_format($maintenanceStats['active_requests']) . " active work orders (" . number_format($maintenanceStats['total_requests']) . " total logged). ";
    if ($maintenanceStats['emergency_requests'] > 0) {
        $summary .= "<span style='color:#ef4444; font-weight:700;'>🚨 " . $maintenanceStats['emergency_requests'] . " emergency dispatches flagged!</span><br>";
    } else {
        $summary .= "0 emergency escalations.<br>";
    }
    
    $summary .= "• <strong>Resource Utilities:</strong> Electricity recorded at " . number_format($energyStats['total_consumption'], 1) . " kWh (₱" . number_format($energyStats['total_cost'], 2) . ") and Water at " . number_format($waterStats['total_consumption'], 1) . " m³ (₱" . number_format($waterStats['total_cost'], 2) . ").<br>";
    $summary .= "• <strong>External Hubs:</strong> UPAD has " . number_format($upadStats['pending_requests']) . " pending electrical inspections; " . number_format($facilityStats['active_loans']) . " equipment units currently deployed across city facilities.<br>";
    
    $summary .= "<br>⚠️ <strong>Actionable Coordination Advisories:</strong><br>";
    $advisories = [];
    if ($maintenanceStats['emergency_requests'] > 0) {
        $advisories[] = "<span style='color:#ef4444;'><strong>Urgent:</strong> " . $maintenanceStats['emergency_requests'] . " emergency work orders require rapid field dispatch.</span>";
    }
    if ($assetStats['damaged'] > 0) {
        $advisories[] = "<strong>Asset Alert:</strong> " . $assetStats['damaged'] . " damaged utility assets require diagnostic inspection.";
    }
    if ($upadStats['pending_requests'] > 0) {
        $advisories[] = "<strong>Grid Clearance:</strong> " . $upadStats['pending_requests'] . " inbound UPAD electrical connection requests await review.";
    }
    if ($waterStats['pending_advisories'] > 0) {
        $advisories[] = "<strong>Water Advisory:</strong> " . $waterStats['pending_advisories'] . " water efficiency recommendations flagged for review.";
    }
    
    if (empty($advisories)) {
        $summary .= "• All active utility monitoring, maintenance, and grid integration pipelines are operating within nominal limits.";
    } else {
        foreach ($advisories as $adv) {
            $summary .= "• " . $adv . "<br>";
        }
    }
    
    return $summary;
}

$aiSummaryText = generateAICentralSummary($assetStats, $maintenanceStats, $energyStats, $waterStats, $upadStats, $facilityStats);

// Chart Data Encoders
$assetConditionLabels = json_encode(['Operational', 'Damaged', 'Needs Inspection', 'Under Maintenance']);
$assetConditionData = json_encode([
    $assetStats['operational'],
    $assetStats['damaged'],
    $assetStats['needs_inspection'],
    $assetStats['under_maintenance']
]);

$maintenanceStatusLabels = json_encode(['Reported', 'Scheduled', 'In Progress', 'Testing', 'Completed']);
$maintenanceStatusData = json_encode([
    $maintenanceStats['reported'],
    $maintenanceStats['scheduled'],
    $maintenanceStats['in_progress'],
    $maintenanceStats['testing'],
    $maintenanceStats['completed']
]);

$upadStatusLabels = json_encode(['Pending Review', 'Approved / Clear', 'Urgent Priority']);
$upadStatusData = json_encode([
    $upadStats['pending_requests'],
    $upadStats['approved_requests'],
    $upadStats['urgent_requests']
]);
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
    <title>LGU Central Command Center</title>
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

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
            backdrop-filter: blur(6px);
            background: rgba(0, 0, 0, 0.45);
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
            margin-left: 78px;
        }

        .card {
            width: 100%;
            max-width: 1700px;
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(16px);
            border-radius: 18px;
            padding: 35px 40px;
            color: #0f172a;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
            border: 1px solid rgba(255,255,255,0.3);
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .dashboard-header h1 {
            color: #1e293b;
            font-size: 28px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .dashboard-header h1 i { color: #3b82f6; }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary { background: #2563eb; color: white; }
        .btn-primary:hover { background: #1d4ed8; transform: translateY(-1px); }

        /* 6-Pillar Stats Cards Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 18px;
            margin-bottom: 30px;
        }

        .stat-card {
            border-radius: 16px;
            padding: 20px 22px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.16);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            color: #fff;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: -20px; right: -20px;
            width: 85px; height: 85px;
            border-radius: 50%;
            background: rgba(255,255,255,0.12);
            pointer-events: none;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 14px 30px rgba(0,0,0,0.22);
        }

        .stat-card.assets       { background: linear-gradient(135deg, #059669, #10b981); }
        .stat-card.maintenance  { background: linear-gradient(135deg, #1d4ed8, #3b82f6); }
        .stat-card.energy       { background: linear-gradient(135deg, #6d28d9, #8b5cf6); }
        .stat-card.water        { background: linear-gradient(135deg, #0284c7, #06b6d4); }
        .stat-card.upad         { background: linear-gradient(135deg, #d97706, #f59e0b); }
        .stat-card.facility     { background: linear-gradient(135deg, #4338ca, #6366f1); }

        .stat-card-icon {
            width: 50px; height: 50px;
            border-radius: 14px;
            background: rgba(255,255,255,0.2);
            display: grid;
            place-items: center;
            font-size: 22px;
            flex-shrink: 0;
        }

        .stat-info {
            flex: 1;
            min-width: 0;
        }

        .stat-info h3 {
            font-size: 26px;
            font-weight: 700;
            color: #fff;
            line-height: 1.1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .stat-info .stat-label {
            font-size: 11px;
            color: rgba(255,255,255,0.85);
            text-transform: uppercase;
            font-weight: 600;
            margin-top: 4px;
            letter-spacing: 0.6px;
        }

        .stat-info .stat-sub {
            font-size: 12px;
            color: rgba(255,255,255,0.92);
            margin-top: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .stat-badge-pill {
            display: inline-block;
            font-size: 10px;
            padding: 2px 7px;
            border-radius: 99px;
            background: rgba(239, 68, 68, 0.9);
            color: #fff;
            font-weight: 700;
            margin-left: 6px;
        }

        /* Tab Layout */
        .tab-buttons {
            display: flex;
            gap: 8px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 8px;
            margin-bottom: 24px;
            overflow-x: auto;
        }

        .tab-btn {
            background: transparent;
            border: none;
            padding: 10px 18px;
            font-size: 13.5px;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.25s;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .tab-btn:hover { background: #f1f5f9; color: #1e293b; }
        .tab-btn.active { background: #2563eb; color: white; }

        .tab-pane { display: none; }
        .tab-pane.active { display: block; }

        .dashboard-layout {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 24px;
            margin-bottom: 25px;
        }

        @media (max-width: 1100px) {
            .dashboard-layout { grid-template-columns: 1fr; }
        }

        .box {
            background: #ffffff;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
            border: 1px solid rgba(0,0,0,0.06);
        }

        .box h3 {
            font-size: 16px;
            color: #1e293b;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 9px;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 10px;
        }

        /* AI Analytics Card */
        .ai-box {
            background: linear-gradient(135deg, #1e3a8a, #1e40af);
            color: white;
            border: none;
        }
        .ai-box h3 { color: white; border-bottom-color: rgba(255,255,255,0.2); }
        .ai-box h3 i { color: #60a5fa; }
        .ai-content { 
            font-size: 13.5px; 
            line-height: 1.65; 
            background: rgba(0, 0, 0, 0.25); 
            padding: 20px; 
            border-radius: 10px; 
            border: 1px solid rgba(255,255,255,0.15); 
        }

        .log-item {
            padding: 10px 14px;
            border-radius: 8px;
            background: #f8fafc;
            border-left: 3px solid #3b82f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            transition: background 0.2s;
        }
        .log-item:hover {
            background: #f1f5f9;
        }

        .badge-type {
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 6px;
            text-transform: uppercase;
            display: inline-block;
            margin-right: 6px;
        }

        /* Dark Theme Support */
        .dark-theme body::before {
            background: rgba(5, 10, 22, 0.85);
        }
        .dark-theme .card {
            background: rgba(15, 23, 42, 0.92);
            border-color: rgba(255, 255, 255, 0.08);
            color: #f8fafc;
        }
        .dark-theme .dashboard-header h1 {
            color: #f8fafc;
        }
        .dark-theme .tab-buttons {
            border-bottom-color: #334155;
        }
        .dark-theme .tab-btn {
            color: #94a3b8;
        }
        .dark-theme .tab-btn:hover {
            background: #1e293b;
            color: #f8fafc;
        }
        .dark-theme .tab-btn.active {
            background: #2563eb;
            color: #fff;
        }
        .dark-theme .box {
            background: #1e293b;
            border-color: #334155;
            color: #f8fafc;
        }
        .dark-theme .box h3 {
            color: #f8fafc;
            border-bottom-color: #334155;
        }
        .dark-theme .log-item {
            background: #0f172a;
            border-left-color: #3b82f6;
            color: #e2e8f0;
        }
        .dark-theme .log-item:hover {
            background: #1e293b;
        }
        .dark-theme .log-item .msg-text {
            color: #f1f5f9 !important;
        }

        /* Welcome Modal CSS */
        .welcome-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 10000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(4px);
        }
        .welcome-modal.open {
            display: flex;
        }
        .welcome-modal-content {
            background: #ffffff;
            border-radius: 16px;
            width: 520px;
            max-width: 90%;
            padding: 30px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);
            position: relative;
        }
        .dark-theme .welcome-modal-content {
            background: #1e293b;
            color: #f8fafc;
            border: 1px solid #334155;
        }
        .welcome-header {
            text-align: center;
            margin-bottom: 20px;
        }
        .welcome-header i {
            font-size: 48px;
            color: #2563eb;
            margin-bottom: 10px;
        }
        .welcome-header h2 {
            font-size: 22px;
            color: #1e293b;
            font-weight: 600;
        }
        .dark-theme .welcome-header h2 { color: #f8fafc; }
        .welcome-body {
            font-size: 13.5px;
            color: #475569;
            line-height: 1.6;
        }
        .dark-theme .welcome-body { color: #cbd5e1; }
        .welcome-body h4 {
            color: #1e293b;
            margin-top: 15px;
            margin-bottom: 8px;
            font-weight: 600;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
        }
        .dark-theme .welcome-body h4 { color: #f8fafc; border-bottom-color: #334155; }
        .welcome-updates-list {
            list-style: none;
            padding: 0;
        }
        .welcome-updates-list li {
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .dark-theme .welcome-updates-list li { border-bottom-color: #334155; }
        .welcome-updates-list li:last-child {
            border-bottom: none;
        }
        .welcome-updates-list li i {
            color: #2563eb;
            margin-top: 4px;
            font-size: 16px;
        }
        .welcome-footer {
            margin-top: 25px;
            display: flex;
            justify-content: center;
        }
        .welcome-btn {
            background: #2563eb;
            color: #fff;
            padding: 10px 30px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .welcome-btn:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
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
                <h1><i class="fas fa-satellite-dish"></i> LGU Central Command Dashboard</h1>
                <p style="color: #64748b; font-size: 14px; margin-top: 5px;">Unified monitoring center for inventory, maintenance dispatches, resource grids, and urban planning.</p>
            </div>
            <div>
                <button class="btn btn-primary" onclick="generateReport()"><i class="fas fa-file-download"></i> Generate System Report</button>
            </div>
        </div>

        <!-- 6-Pillar Command Center Metric Grid -->
        <div class="stats-grid">
            
            <!-- 1. Monitored Assets -->
            <div class="stat-card assets">
                <div class="stat-card-icon"><i class="fas fa-boxes"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($assetStats['total_assets']); ?></h3>
                    <div class="stat-label">Total Assets</div>
                    <div class="stat-sub"><?php echo number_format($assetStats['total_units']); ?> units · <?php echo number_format($assetStats['operational']); ?> operational</div>
                </div>
            </div>

            <!-- 2. Active Maintenance -->
            <div class="stat-card maintenance">
                <div class="stat-card-icon"><i class="fas fa-tools"></i></div>
                <div class="stat-info">
                    <h3>
                        <?php echo number_format($maintenanceStats['active_requests']); ?>
                        <?php if ($maintenanceStats['emergency_requests'] > 0): ?>
                            <span class="stat-badge-pill"><?php echo $maintenanceStats['emergency_requests']; ?> Emerg</span>
                        <?php endif; ?>
                    </h3>
                    <div class="stat-label">Active Work Orders</div>
                    <div class="stat-sub"><?php echo number_format($maintenanceStats['total_requests']); ?> total · <?php echo number_format($maintenanceStats['in_progress']); ?> in progress</div>
                </div>
            </div>

            <!-- 3. Energy Consumption -->
            <div class="stat-card energy">
                <div class="stat-card-icon"><i class="fas fa-bolt"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($energyStats['total_consumption'], 1); ?> <span style="font-size:12px; font-weight:500;">kWh</span></h3>
                    <div class="stat-label">Energy Grid</div>
                    <div class="stat-sub">Est. ₱<?php echo number_format($energyStats['total_cost'], 2); ?></div>
                </div>
            </div>

            <!-- 4. Water Consumption -->
            <div class="stat-card water">
                <div class="stat-card-icon"><i class="fas fa-tint"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($waterStats['total_consumption'], 1); ?> <span style="font-size:12px; font-weight:500;">m³</span></h3>
                    <div class="stat-label">Water Grid</div>
                    <div class="stat-sub">Est. ₱<?php echo number_format($waterStats['total_cost'], 2); ?></div>
                </div>
            </div>

            <!-- 5. UPAD Electrical Inspections -->
            <div class="stat-card upad">
                <div class="stat-card-icon"><i class="fas fa-network-wired"></i></div>
                <div class="stat-info">
                    <h3>
                        <?php echo number_format($upadStats['pending_requests']); ?>
                        <?php if ($upadStats['urgent_requests'] > 0): ?>
                            <span class="stat-badge-pill"><?php echo $upadStats['urgent_requests']; ?> Urgent</span>
                        <?php endif; ?>
                    </h3>
                    <div class="stat-label">UPAD Grid Review</div>
                    <div class="stat-sub"><?php echo number_format($upadStats['total_requests']); ?> total clearances</div>
                </div>
            </div>

            <!-- 6. Facility Equipment Custody -->
            <div class="stat-card facility">
                <div class="stat-card-icon"><i class="fas fa-building"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($facilityStats['active_loans']); ?></h3>
                    <div class="stat-label">Facility On-Loan</div>
                    <div class="stat-sub"><?php echo number_format($facilityStats['pending_approval']); ?> pending · <?php echo number_format($facilityStats['returned']); ?> returned</div>
                </div>
            </div>

        </div>

        <!-- AI Digest & Live Coordination Feed Row -->
        <div class="dashboard-layout" style="margin-bottom: 25px;">
            
            <!-- AI Command Briefing -->
            <div class="box ai-box">
                <h3><i class="fas fa-robot"></i> Central AI Operational Briefing</h3>
                <div class="ai-content">
                    <?php echo $aiSummaryText; ?>
                </div>
            </div>
            
            <!-- Global Real-Time Alert Feed -->
            <div class="box">
                <h3><i class="fas fa-bell"></i> Multi-Module Live Coordination Feed</h3>
                <div style="display:flex; flex-direction:column; gap:10px; max-height: 250px; overflow-y:auto; padding-right: 4px;">
                    <?php if (empty($allNotifications)): ?>
                        <div style="color: #94a3b8; font-size: 13px; text-align: center; padding: 25px 0;">
                            <i class="fas fa-check-circle" style="font-size: 28px; margin-bottom: 8px; color: #10b981; display:block;"></i>
                            No recent coordination events recorded.
                        </div>
                    <?php else: ?>
                        <?php foreach ($allNotifications as $n): ?>
                            <div class="log-item" style="border-left-color: <?php echo htmlspecialchars($n['color']); ?>;">
                                <div style="display: flex; align-items: flex-start; gap: 10px; width: 100%;">
                                    <div style="color: <?php echo htmlspecialchars($n['color']); ?>; font-size: 14px; margin-top: 2px;">
                                        <i class="fas <?php echo htmlspecialchars($n['icon']); ?>"></i>
                                    </div>
                                    <div style="flex: 1; min-width: 0;">
                                        <div class="msg-text" style="font-weight:600; color:#1e293b; font-size: 12.5px; line-height: 1.4;">
                                            <span class="badge-type" style="background: <?php echo htmlspecialchars($n['color']); ?>22; color: <?php echo htmlspecialchars($n['color']); ?>;">
                                                <?php echo htmlspecialchars($n['type']); ?>
                                            </span>
                                            <?php echo htmlspecialchars($n['message']); ?>
                                        </div>
                                        <div style="font-size:10px; color:#94a3b8; margin-top:3px;">
                                            <?php echo date('M d, Y · h:i A', strtotime($n['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Section Tabs -->
        <div class="tab-buttons">
            <button class="tab-btn active" onclick="switchTab(event, 'assets-pane')"><i class="fas fa-warehouse"></i> Asset Analytics</button>
            <button class="tab-btn" onclick="switchTab(event, 'maintenance-pane')"><i class="fas fa-tools"></i> Maintenance Pipeline</button>
            <button class="tab-btn" onclick="switchTab(event, 'resource-pane')"><i class="fas fa-bolt"></i> Resource Grids (Energy & Water)</button>
            <button class="tab-btn" onclick="switchTab(event, 'upad-pane')"><i class="fas fa-network-wired"></i> UPAD Grid Review</button>
            <button class="tab-btn" onclick="switchTab(event, 'facility-pane')"><i class="fas fa-building"></i> Facility Deployments</button>
        </div>

        <!-- TAB PANES -->
        
        <!-- 1. Assets Pane -->
        <div id="assets-pane" class="tab-pane active">
            <div class="dashboard-layout">
                <div class="box">
                    <h3><i class="fas fa-chart-pie"></i> Asset Operational Condition Breakdown</h3>
                    <div style="position:relative; height:280px; width:100%; display:flex; justify-content:center; align-items:center;">
                        <canvas id="assetChart"></canvas>
                    </div>
                </div>
                <div class="box" style="display:flex; flex-direction:column; justify-content:center;">
                    <h4 style="color:#1e293b; font-size:15px; margin-bottom:12px;" class="msg-text">Asset Inventory Status Summary:</h4>
                    <p style="font-size:13px; color:#64748b; line-height:1.6;" class="msg-text">
                        Municipal assets include Solar Streetlights, Drainage Gates, Pipeline Sections, and Emergency Equipment.
                        Currently tracking <strong><?php echo number_format($assetStats['total_assets']); ?></strong> unique assets across 
                        <strong><?php echo number_format($assetStats['total_units']); ?></strong> total deployed units.
                    </p>
                    <div style="margin-top: 15px; padding: 12px; background: rgba(0,0,0,0.03); border-radius: 8px;">
                        <div style="font-size: 12px; color: #64748b; line-height: 1.8;" class="msg-text">
                            • 🟢 <strong>Operational:</strong> <?php echo number_format($assetStats['operational']); ?> assets<br>
                            • 🔴 <strong>Damaged:</strong> <?php echo number_format($assetStats['damaged']); ?> assets<br>
                            • 🟡 <strong>Needs Inspection:</strong> <?php echo number_format($assetStats['needs_inspection']); ?> assets<br>
                            • 🔵 <strong>Under Maintenance:</strong> <?php echo number_format($assetStats['under_maintenance']); ?> assets
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Maintenance Pane -->
        <div id="maintenance-pane" class="tab-pane">
            <div class="dashboard-layout">
                <div class="box">
                    <h3><i class="fas fa-chart-bar"></i> Maintenance Work Order Pipeline</h3>
                    <div style="position:relative; height:280px; width:100%; display:flex; justify-content:center; align-items:center;">
                        <canvas id="maintenanceChart"></canvas>
                    </div>
                </div>
                <div class="box" style="display:flex; flex-direction:column; justify-content:center;">
                    <h4 style="color:#1e293b; font-size:15px; margin-bottom:12px;" class="msg-text">Work Order Dispatches:</h4>
                    <p style="font-size:13px; color:#64748b; line-height:1.6;" class="msg-text">
                        Maintenance orders handle repairs for damaged and routine-scheduled municipal assets.
                        There are currently <strong><?php echo number_format($maintenanceStats['active_requests']); ?></strong> active repair orders in the queue.
                    </p>
                    <div style="margin-top: 15px; padding: 12px; background: rgba(0,0,0,0.03); border-radius: 8px;">
                        <div style="font-size: 12px; color: #64748b; line-height: 1.8;" class="msg-text">
                            • 📋 <strong>Reported (Queue):</strong> <?php echo number_format($maintenanceStats['reported']); ?> orders<br>
                            • 📅 <strong>Scheduled:</strong> <?php echo number_format($maintenanceStats['scheduled']); ?> orders<br>
                            • ⚙️ <strong>In Progress:</strong> <?php echo number_format($maintenanceStats['in_progress']); ?> orders<br>
                            • 🧪 <strong>Testing:</strong> <?php echo number_format($maintenanceStats['testing']); ?> orders<br>
                            • ✅ <strong>Completed (All-Time):</strong> <?php echo number_format($maintenanceStats['completed']); ?> orders
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Resource Grid (Energy & Water) -->
        <div id="resource-pane" class="tab-pane">
            <div class="dashboard-layout" style="grid-template-columns: 1fr 1fr;">
                <div class="box">
                    <h3><i class="fas fa-bolt" style="color:#8b5cf6;"></i> Electricity Grid Records</h3>
                    <p style="font-size:13.5px; color:#1e293b; margin-bottom:12px;" class="msg-text">
                        Total Recorded Consumption: <strong><?php echo number_format($energyStats['total_consumption'], 1); ?> kWh</strong><br>
                        Estimated Grid Expenditure: <strong>₱<?php echo number_format($energyStats['total_cost'], 2); ?></strong>
                    </p>
                    <p style="font-size:12.5px; color:#64748b; line-height:1.6;" class="msg-text">
                        Energy data syncs with the external Energy Efficiency System. Successful sync actions logged: <strong><?php echo $energySyncs; ?></strong>. Total logged audit records: <strong><?php echo number_format($energyStats['records_count']); ?></strong>.
                    </p>
                </div>
                <div class="box">
                    <h3><i class="fas fa-tint" style="color:#06b6d4;"></i> Water Distribution Records</h3>
                    <p style="font-size:13.5px; color:#1e293b; margin-bottom:12px;" class="msg-text">
                        Total Recorded Consumption: <strong><?php echo number_format($waterStats['total_consumption'], 1); ?> m³</strong><br>
                        Estimated Water Expenditure: <strong>₱<?php echo number_format($waterStats['total_cost'], 2); ?></strong>
                    </p>
                    <p style="font-size:12.5px; color:#64748b; line-height:1.6;" class="msg-text">
                        Pending water efficiency advisories: <strong><?php echo number_format($waterStats['pending_advisories']); ?></strong>. Total logged audit records: <strong><?php echo number_format($waterStats['records_count']); ?></strong>.
                    </p>
                </div>
            </div>
        </div>

        <!-- 4. UPAD Grid Review Pane -->
        <div id="upad-pane" class="tab-pane">
            <div class="dashboard-layout">
                <div class="box">
                    <h3><i class="fas fa-network-wired"></i> UPAD Electrical Clearances Status</h3>
                    <div style="position:relative; height:280px; width:100%; display:flex; justify-content:center; align-items:center;">
                        <canvas id="upadChart"></canvas>
                    </div>
                </div>
                <div class="box" style="display:flex; flex-direction:column; justify-content:center;">
                    <h4 style="color:#1e293b; font-size:15px; margin-bottom:12px;" class="msg-text">Urban Planning & Architectural Hub:</h4>
                    <p style="font-size:13px; color:#64748b; line-height:1.6;" class="msg-text">
                        Receives live inbound electrical and grid inspection requests for building clearances.
                        The integrated AI compliance engine auto-evaluates capacity risks and dispatches verification callbacks.
                    </p>
                    <div style="margin-top: 15px; padding: 12px; background: rgba(0,0,0,0.03); border-radius: 8px;">
                        <div style="font-size: 12px; color: #64748b; line-height: 1.8;" class="msg-text">
                            • ⏳ <strong>Pending Review:</strong> <?php echo number_format($upadStats['pending_requests']); ?> requests<br>
                            • ⚡ <strong>Urgent Priority:</strong> <?php echo number_format($upadStats['urgent_requests']); ?> requests<br>
                            • 🟢 <strong>Approved / Cleared:</strong> <?php echo number_format($upadStats['approved_requests']); ?> requests<br>
                            • 📊 <strong>Total Inbound Requests:</strong> <?php echo number_format($upadStats['total_requests']); ?> records
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. Facility Deployments Pane -->
        <div id="facility-pane" class="tab-pane">
            <div class="dashboard-layout" style="grid-template-columns: 1fr;">
                <div class="box">
                    <h3><i class="fas fa-building"></i> Community Facility & Citizen Equipment Custody</h3>
                    <p style="font-size:13.5px; color:#1e293b; margin-bottom:12px;" class="msg-text">
                        Currently deployed equipment on-loan at evacuation centers, barangay halls, and citizen requests: 
                        <strong><?php echo number_format($facilityStats['active_loans']); ?> units</strong>.
                    </p>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
                        <div style="padding: 15px; background: rgba(59, 130, 246, 0.08); border-radius: 10px; border-left: 4px solid #3b82f6;">
                            <div style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600;">Active On-Loan</div>
                            <div style="font-size: 22px; font-weight: 700; color: #2563eb; margin-top: 4px;"><?php echo number_format($facilityStats['active_loans']); ?></div>
                        </div>
                        <div style="padding: 15px; background: rgba(245, 158, 11, 0.08); border-radius: 10px; border-left: 4px solid #f59e0b;">
                            <div style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600;">Pending Approval</div>
                            <div style="font-size: 22px; font-weight: 700; color: #d97706; margin-top: 4px;"><?php echo number_format($facilityStats['pending_approval']); ?></div>
                        </div>
                        <div style="padding: 15px; background: rgba(16, 185, 129, 0.08); border-radius: 10px; border-left: 4px solid #10b981;">
                            <div style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600;">Returned Custody</div>
                            <div style="font-size: 22px; font-weight: 700; color: #059669; margin-top: 4px;"><?php echo number_format($facilityStats['returned']); ?></div>
                        </div>
                        <div style="padding: 15px; background: rgba(99, 102, 241, 0.08); border-radius: 10px; border-left: 4px solid #6366f1;">
                            <div style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600;">Total Loan Requests</div>
                            <div style="font-size: 22px; font-weight: 700; color: #4338ca; margin-top: 4px;"><?php echo number_format($facilityStats['total_requests']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</main>

<script>
    function switchTab(evt, tabId) {
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        
        document.getElementById(tabId).classList.add('active');
        evt.currentTarget.classList.add('active');
    }

    function generateReport() {
        alert("Generating LGU System-Wide Multi-Sector Status Report (PDF/Excel)...\nSimulated file download started in background.");
    }

    // Chart 1: Assets Operational Status
    const assetCtx = document.getElementById('assetChart').getContext('2d');
    new Chart(assetCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo $assetConditionLabels; ?>,
            datasets: [{
                data: <?php echo $assetConditionData; ?>,
                backgroundColor: ['#10b981', '#ef4444', '#f59e0b', '#3b82f6'],
                borderWidth: 2
            }]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    // Chart 2: Maintenance Request Status
    const maintenanceCtx = document.getElementById('maintenanceChart').getContext('2d');
    new Chart(maintenanceCtx, {
        type: 'bar',
        data: {
            labels: <?php echo $maintenanceStatusLabels; ?>,
            datasets: [{
                label: 'Work Orders',
                data: <?php echo $maintenanceStatusData; ?>,
                backgroundColor: '#3b82f6',
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            }
        }
    });

    // Chart 3: UPAD Electrical Clearances Status
    const upadCtx = document.getElementById('upadChart').getContext('2d');
    new Chart(upadCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo $upadStatusLabels; ?>,
            datasets: [{
                data: <?php echo $upadStatusData; ?>,
                backgroundColor: ['#f59e0b', '#10b981', '#ef4444'],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
</script>

<?php if (isset($_SESSION['show_welcome_modal']) && $_SESSION['show_welcome_modal'] === true): ?>
<!-- WELCOME BACK POPUP MODAL -->
<div id="welcomeBackModal" class="welcome-modal open">
    <div class="welcome-modal-content">
        <div class="welcome-header">
            <i class="fas fa-hand-sparkles"></i>
            <h2>Welcome Back, <?php echo htmlspecialchars($userName); ?>!</h2>
            <p style="color: #64748b; font-size: 14px; margin-top: 5px;">You have successfully logged in to LGU Central Command.</p>
        </div>
        <div class="welcome-body">
            <h4><i class="fas fa-bullhorn" style="color: #2563eb; margin-right: 6px;"></i> Live Municipal Status:</h4>
            <ul class="welcome-updates-list">
                <li>
                    <i class="fas fa-boxes"></i>
                    <div>
                        <strong>Monitored Assets:</strong> Tracking <?php echo number_format($assetStats['total_assets']); ?> assets (<?php echo number_format($assetStats['total_units']); ?> units), with <?php echo number_format($assetStats['damaged']); ?> currently marked Damaged.
                    </div>
                </li>
                <li>
                    <i class="fas fa-tools"></i>
                    <div>
                        <strong>Maintenance Pipeline:</strong> <?php echo number_format($maintenanceStats['active_requests']); ?> active work orders in repair queue (<?php echo number_format($maintenanceStats['emergency_requests']); ?> emergency).
                    </div>
                </li>
                <li>
                    <i class="fas fa-bolt"></i>
                    <div>
                        <strong>Resource Grids:</strong> Recorded electricity stands at <?php echo number_format($energyStats['total_consumption'], 1); ?> kWh and water at <?php echo number_format($waterStats['total_consumption'], 1); ?> m³.
                    </div>
                </li>
            </ul>
        </div>
        <div class="welcome-footer">
            <button type="button" class="welcome-btn" onclick="closeWelcomeModal()">Dismiss Updates</button>
        </div>
    </div>
</div>
<script>
function closeWelcomeModal() {
    document.getElementById('welcomeBackModal').classList.remove('open');
}
</script>
<?php 
    $_SESSION['show_welcome_modal'] = false;
endif; 
?>

</body>
</html>