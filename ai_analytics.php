<?php
// ai_analytics.php — AI Analytics Dashboard (Employee Only)
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn() || !isEmployee()) {
    header('Location: login.php');
    exit();
}

$userType = $_SESSION['user_type'] ?? 'employee';
$userName = $_SESSION['user_name'] ?? $_SESSION['full_name'] ?? 'LGU Coordinator';

// ============================================================
// 1. ASSETS MODULE DATA
// ============================================================
$assets = ['total_assets' => 0, 'operational_assets' => 0, 'damaged_assets' => 0, 'inspection_assets' => 0];
try {
    $assets = $pdo->query("SELECT * FROM aggregated_assets_view")->fetch() ?: $assets;
} catch (Throwable $e) {}

$underMaintenance = 0;
try {
    $underMaintenance = (int)$pdo->query("SELECT COUNT(*) FROM utility_assets WHERE condition_status = 'Under Maintenance'")->fetchColumn();
} catch (Throwable $e) {}

// Assets by category
$assetCategories = [];
try {
    $assetCategories = $pdo->query("
        SELECT t.name, COUNT(a.id) as count 
        FROM asset_types t 
        LEFT JOIN utility_assets a ON t.id = a.asset_type_id 
        GROUP BY t.id, t.name
    ")->fetchAll() ?: [];
} catch (Throwable $e) {}

// Assets by condition
$assetConditions = [];
try {
    $assetConditions = $pdo->query("
        SELECT condition_status, COUNT(*) as count 
        FROM utility_assets 
        GROUP BY condition_status
    ")->fetchAll() ?: [];
} catch (Throwable $e) {}

// Damaged assets without linked maintenance
$damagedNoMaintenance = 0;
try {
    $damagedNoMaintenance = (int)$pdo->query("
        SELECT COUNT(*) FROM utility_assets a 
        WHERE a.condition_status = 'Damaged' 
        AND a.id NOT IN (
            SELECT DISTINCT COALESCE(asset_id, 0) FROM maintenance_requests WHERE asset_id IS NOT NULL
        )
    ")->fetchColumn();
} catch (Throwable $e) {}

// Recently damaged assets
$recentDamaged = [];
try {
    $recentDamaged = $pdo->query("
        SELECT a.asset_name, a.location, a.condition_status, t.name as type_name, a.updated_at
        FROM utility_assets a
        JOIN asset_types t ON a.asset_type_id = t.id
        WHERE a.condition_status IN ('Damaged', 'Needs Inspection')
        ORDER BY a.updated_at DESC
        LIMIT 5
    ")->fetchAll() ?: [];
} catch (Throwable $e) {}

// ============================================================
// 2. INCIDENTS MODULE DATA
// ============================================================
$incidents = ['total_incidents' => 0, 'submitted_incidents' => 0, 'review_incidents' => 0, 'forwarded_incidents' => 0, 'resolved_incidents' => 0];
try {
    $incidents = $pdo->query("SELECT * FROM aggregated_incidents_view")->fetch() ?: $incidents;
} catch (Throwable $e) {}

// Incidents by category
$incidentCategories = [];
try {
    $incidentCategories = $pdo->query("
        SELECT c.name, COUNT(i.id) as count 
        FROM incident_categories c 
        LEFT JOIN utility_incidents i ON c.id = i.category_id 
        GROUP BY c.id, c.name
    ")->fetchAll() ?: [];
} catch (Throwable $e) {}

// Incidents by priority
$incidentPriorities = [];
try {
    $incidentPriorities = $pdo->query("
        SELECT priority, COUNT(*) as count 
        FROM utility_incidents 
        GROUP BY priority
    ")->fetchAll() ?: [];
} catch (Throwable $e) {}

// Emergency incidents count
$emergencyIncidents = 0;
try {
    $emergencyIncidents = (int)$pdo->query("SELECT COUNT(*) FROM utility_incidents WHERE priority = 'Emergency'")->fetchColumn();
} catch (Throwable $e) {}

// Monthly incident trend (last 6 months)
$incidentTrend = [];
try {
    $incidentTrend = $pdo->query("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count
        FROM utility_incidents
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ")->fetchAll() ?: [];
} catch (Throwable $e) {}

// ============================================================
// 3. MAINTENANCE MODULE DATA
// ============================================================
$maintenance = ['total_requests' => 0, 'pending_requests' => 0, 'progress_requests' => 0, 'completed_requests' => 0, 'emergency_requests' => 0];
try {
    $maintenance = $pdo->query("SELECT * FROM aggregated_maintenance_view")->fetch() ?: $maintenance;
} catch (Throwable $e) {}

// Maintenance by priority
$maintPriorities = [];
try {
    $maintPriorities = $pdo->query("
        SELECT COALESCE(priority, 'Unset') as priority, COUNT(*) as count 
        FROM maintenance_requests 
        GROUP BY priority
    ")->fetchAll() ?: [];
} catch (Throwable $e) {}

// Maintenance by source
$maintSources = [];
try {
    $maintSources = $pdo->query("
        SELECT COALESCE(source, 'Unknown') as source, COUNT(*) as count 
        FROM maintenance_requests 
        GROUP BY source
    ")->fetchAll() ?: [];
} catch (Throwable $e) {}

// Forwarded maintenance
$forwardedMaint = 0;
try {
    $forwardedMaint = (int)$pdo->query("SELECT COUNT(*) FROM maintenance_requests WHERE status = 'Forwarded'")->fetchColumn();
} catch (Throwable $e) {}

// ============================================================
// 4. ENERGY MODULE DATA
// ============================================================
$energy = ['total_consumption' => 0, 'total_cost' => 0, 'total_records' => 0];
try {
    $energy = $pdo->query("SELECT * FROM aggregated_energy_view")->fetch() ?: $energy;
} catch (Throwable $e) {}

$successfulSyncs = 0;
try {
    $successfulSyncs = (int)$pdo->query("SELECT COUNT(*) FROM energy_sync_logs WHERE status = 'Successful'")->fetchColumn();
} catch (Throwable $e) {}

$pendingAdvisories = 0;
try {
    $pendingAdvisories = (int)$pdo->query("SELECT COUNT(*) FROM energy_recommendations WHERE status = 'Pending'")->fetchColumn();
} catch (Throwable $e) {}

// Energy monthly trend
$energyTrend = [];
try {
    $energyTrend = $pdo->query("
        SELECT month_year, SUM(consumption_kwh) as kwh, SUM(cost) as cost
        FROM energy_consumption_records
        GROUP BY month_year
        ORDER BY month_year ASC
        LIMIT 12
    ")->fetchAll() ?: [];
} catch (Throwable $e) {}

// Top consuming facilities
$topFacilities = [];
try {
    $topFacilities = $pdo->query("
        SELECT COALESCE(facility_name, CONCAT(asset_type, ' - ', location)) as facility,
               SUM(consumption_kwh) as total_kwh, SUM(cost) as total_cost
        FROM energy_consumption_records
        GROUP BY facility
        ORDER BY total_kwh DESC
        LIMIT 5
    ")->fetchAll() ?: [];
} catch (Throwable $e) {}

// Energy by asset type
$energyByType = [];
try {
    $energyByType = $pdo->query("
        SELECT asset_type, SUM(consumption_kwh) as total_kwh, SUM(cost) as total_cost
        FROM energy_consumption_records
        GROUP BY asset_type
        ORDER BY total_kwh DESC
    ")->fetchAll() ?: [];
} catch (Throwable $e) {}

// ============================================================
// 5. COMPUTE AI HEALTH SCORE (0-100)
// ============================================================
$totalAssets = max((int)($assets['total_assets'] ?? 0), 1);
$operationalAssets = (int)($assets['operational_assets'] ?? 0);
$damagedAssets = (int)($assets['damaged_assets'] ?? 0);
$inspectionAssets = (int)($assets['inspection_assets'] ?? 0);

$totalIncidents = max((int)($incidents['total_incidents'] ?? 0), 1);
$resolvedIncidents = (int)($incidents['resolved_incidents'] ?? 0);
$submittedIncidents = (int)($incidents['submitted_incidents'] ?? 0);

$totalMaint = max((int)($maintenance['total_requests'] ?? 0), 1);
$completedMaint = (int)($maintenance['completed_requests'] ?? 0);
$emergencyMaint = (int)($maintenance['emergency_requests'] ?? 0);

// Sub-scores (each 0-100)
$assetHealthScore = ($totalAssets > 0) ? round(($operationalAssets / $totalAssets) * 100) : 100;
$incidentResolutionRate = ($totalIncidents > 0) ? round(($resolvedIncidents / $totalIncidents) * 100) : 100;
$maintCompletionRate = ($totalMaint > 0) ? round(($completedMaint / $totalMaint) * 100) : 100;

// Energy efficiency score: lower pending advisories = better
$energyScore = max(0, 100 - ($pendingAdvisories * 10));
$energyScore = min(100, $energyScore);

// Weighted overall AI score
$aiScore = round(
    ($assetHealthScore * 0.30) +
    ($incidentResolutionRate * 0.25) +
    ($maintCompletionRate * 0.25) +
    ($energyScore * 0.20)
);

// Risk level
if ($aiScore >= 80) {
    $riskLevel = 'Low';
    $riskColor = '#27ae60';
    $riskIcon = 'fa-shield-alt';
} elseif ($aiScore >= 60) {
    $riskLevel = 'Moderate';
    $riskColor = '#f39c12';
    $riskIcon = 'fa-exclamation-triangle';
} elseif ($aiScore >= 40) {
    $riskLevel = 'Elevated';
    $riskColor = '#e67e22';
    $riskIcon = 'fa-radiation-alt';
} else {
    $riskLevel = 'Critical';
    $riskColor = '#e74c3c';
    $riskIcon = 'fa-skull-crossbones';
}

// Count active risk alerts
$riskAlerts = 0;
if ($damagedAssets > 0) $riskAlerts++;
if ($emergencyMaint > 0) $riskAlerts++;
if ($emergencyIncidents > 0) $riskAlerts++;
if ($submittedIncidents > 3) $riskAlerts++;
if ($damagedNoMaintenance > 0) $riskAlerts++;
if ($pendingAdvisories > 2) $riskAlerts++;

// ============================================================
// 6. GENERATE AI RECOMMENDATIONS
// ============================================================
$recommendations = [];

if ($damagedNoMaintenance > 0) {
    $recommendations[] = [
        'priority' => 'High',
        'icon' => 'fa-tools',
        'color' => '#e74c3c',
        'title' => 'Unlinked Damaged Assets',
        'text' => "{$damagedNoMaintenance} damaged asset(s) have no corresponding maintenance request. Consider dispatching repair teams immediately."
    ];
}

if ($emergencyIncidents > 0) {
    $recommendations[] = [
        'priority' => 'High',
        'icon' => 'fa-exclamation-circle',
        'color' => '#e74c3c',
        'title' => 'Emergency Incidents Active',
        'text' => "{$emergencyIncidents} emergency incident(s) require immediate attention and should be escalated to department heads."
    ];
}

if ($submittedIncidents > 0) {
    $recommendations[] = [
        'priority' => 'Medium',
        'icon' => 'fa-clipboard-list',
        'color' => '#f39c12',
        'title' => 'Pending Incident Reviews',
        'text' => "{$submittedIncidents} incident report(s) are awaiting initial review. Timely processing improves citizen satisfaction."
    ];
}

if ($emergencyMaint > 0) {
    $recommendations[] = [
        'priority' => 'High',
        'icon' => 'fa-hard-hat',
        'color' => '#e74c3c',
        'title' => 'Emergency Maintenance Dispatches',
        'text' => "{$emergencyMaint} emergency maintenance request(s) flagged. Verify that external dispatch systems have been notified."
    ];
}

if ($inspectionAssets > 0) {
    $recommendations[] = [
        'priority' => 'Medium',
        'icon' => 'fa-search',
        'color' => '#f39c12',
        'title' => 'Assets Requiring Inspection',
        'text' => "{$inspectionAssets} asset(s) are marked as 'Needs Inspection'. Schedule field inspections to prevent further deterioration."
    ];
}

if ($pendingAdvisories > 0) {
    $recommendations[] = [
        'priority' => 'Medium',
        'icon' => 'fa-lightbulb',
        'color' => '#f39c12',
        'title' => 'Energy Advisories Pending',
        'text' => "{$pendingAdvisories} energy efficiency recommendation(s) are pending action. Implementing these could reduce operational costs."
    ];
}

if ($incidentResolutionRate < 50 && $totalIncidents > 1) {
    $recommendations[] = [
        'priority' => 'Medium',
        'icon' => 'fa-chart-line',
        'color' => '#f39c12',
        'title' => 'Low Incident Resolution Rate',
        'text' => "Resolution rate is at {$incidentResolutionRate}%. Consider allocating additional staff or resources to accelerate case processing."
    ];
}

if ($maintCompletionRate < 50 && $totalMaint > 1) {
    $recommendations[] = [
        'priority' => 'Medium',
        'icon' => 'fa-wrench',
        'color' => '#f39c12',
        'title' => 'Low Maintenance Completion',
        'text' => "Maintenance completion rate is at {$maintCompletionRate}%. Review bottlenecks in the maintenance pipeline."
    ];
}

if (empty($recommendations)) {
    $recommendations[] = [
        'priority' => 'Info',
        'icon' => 'fa-check-circle',
        'color' => '#27ae60',
        'title' => 'All Systems Nominal',
        'text' => 'No critical issues detected. All utility monitoring and maintenance pipelines are operating within normal parameters.'
    ];
}

// ============================================================
// 7. GENERATE AI NARRATIVE SUMMARY
// ============================================================
$aiNarrative = "<strong>LGU AI Analytics Report — " . date('F d, Y') . "</strong><br><br>";

$aiNarrative .= "🏢 <strong>System Health Overview:</strong><br>";
$aiNarrative .= "The overall LGU Utility System AI Health Score is <strong>{$aiScore}/100</strong> ({$riskLevel} Risk). ";
$aiNarrative .= "This score is computed from asset health ({$assetHealthScore}%), incident resolution ({$incidentResolutionRate}%), maintenance completion ({$maintCompletionRate}%), and energy efficiency ({$energyScore}%).<br><br>";

$aiNarrative .= "📊 <strong>Module Breakdown:</strong><br>";
$aiNarrative .= "• <strong>Assets:</strong> {$totalAssets} total assets tracked — {$operationalAssets} operational, {$damagedAssets} damaged, {$inspectionAssets} needing inspection.<br>";
$aiNarrative .= "• <strong>Incidents:</strong> {$totalIncidents} total reports — {$resolvedIncidents} resolved (" . $incidentResolutionRate . "% resolution rate), {$submittedIncidents} awaiting review.<br>";
$aiNarrative .= "• <strong>Maintenance:</strong> " . ($maintenance['total_requests'] ?? 0) . " total requests — {$completedMaint} completed, {$emergencyMaint} emergency dispatches.<br>";
$aiNarrative .= "• <strong>Energy:</strong> " . number_format($energy['total_consumption'] ?? 0, 1) . " kWh total consumption (₱" . number_format($energy['total_cost'] ?? 0, 2) . " est. cost).<br><br>";

$aiNarrative .= "⚠️ <strong>AI Advisory:</strong><br>";
if ($riskAlerts > 0) {
    $aiNarrative .= "{$riskAlerts} risk alert(s) detected across modules. Review the Recommendations panel for prioritized action items.";
} else {
    $aiNarrative .= "All active utility monitoring and maintenance pipelines are operating within nominal queue limits. No immediate action required.";
}

// ============================================================
// JSON DATA FOR CHARTS
// ============================================================
$assetCondLabels = json_encode(array_column($assetConditions, 'condition_status'));
$assetCondData = json_encode(array_map('intval', array_column($assetConditions, 'count')));

$assetCatLabels = json_encode(array_column($assetCategories, 'name'));
$assetCatData = json_encode(array_map('intval', array_column($assetCategories, 'count')));

$incCatLabels = json_encode(array_column($incidentCategories, 'name'));
$incCatData = json_encode(array_map('intval', array_column($incidentCategories, 'count')));

$incPriorLabels = json_encode(array_column($incidentPriorities, 'priority'));
$incPriorData = json_encode(array_map('intval', array_column($incidentPriorities, 'count')));

$incTrendLabels = json_encode(array_column($incidentTrend, 'month'));
$incTrendData = json_encode(array_map('intval', array_column($incidentTrend, 'count')));

$maintPriorLabels = json_encode(array_column($maintPriorities, 'priority'));
$maintPriorData = json_encode(array_map('intval', array_column($maintPriorities, 'count')));

$maintSourceLabels = json_encode(array_column($maintSources, 'source'));
$maintSourceData = json_encode(array_map('intval', array_column($maintSources, 'count')));

$energyTrendLabels = json_encode(array_column($energyTrend, 'month_year'));
$energyTrendKwh = json_encode(array_map('floatval', array_column($energyTrend, 'kwh')));
$energyTrendCost = json_encode(array_map('floatval', array_column($energyTrend, 'cost')));

$energyTypeLabels = json_encode(array_column($energyByType, 'asset_type'));
$energyTypeData = json_encode(array_map('floatval', array_column($energyByType, 'total_kwh')));

$topFacilityLabels = json_encode(array_column($topFacilities, 'facility'));
$topFacilityData = json_encode(array_map('floatval', array_column($topFacilities, 'total_kwh')));

// Radar chart data
$radarData = json_encode([$assetHealthScore, $incidentResolutionRate, $maintCompletionRate, $energyScore]);

// Pipeline data for incidents
$pipelineData = json_encode([
    (int)($incidents['submitted_incidents'] ?? 0),
    (int)($incidents['review_incidents'] ?? 0),
    (int)($incidents['forwarded_incidents'] ?? 0),
    (int)($incidents['resolved_incidents'] ?? 0)
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
    <title>AI Analytics | LGU Utilities Management</title>
    <meta name="description" content="AI-powered analytics dashboard for LGU utility management system">
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            margin-left: 78px;
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

        .dashboard-header h1 i { color: #3762c8; }

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
        .btn-primary:hover { background: #2851b0; }

        /* ===== AI SCORE HERO ===== */
        .ai-score-hero {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        @media (max-width: 900px) {
            .ai-score-hero { grid-template-columns: 1fr; }
        }

        .score-gauge {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            border-radius: 16px;
            padding: 30px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(30, 60, 114, 0.3);
        }

        .score-gauge::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, transparent 70%);
            animation: gaugeGlow 4s ease-in-out infinite;
        }

        @keyframes gaugeGlow {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 1; }
        }

        .score-circle {
            width: 160px;
            height: 160px;
            border-radius: 50%;
            background: conic-gradient(
                <?php echo $riskColor; ?> <?php echo $aiScore * 3.6; ?>deg,
                rgba(255,255,255,0.1) <?php echo $aiScore * 3.6; ?>deg
            );
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 1;
            animation: scoreIn 1s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
        }

        @keyframes scoreIn {
            from { transform: scale(0.5); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .score-inner {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .score-number {
            font-size: 42px;
            font-weight: 700;
            line-height: 1;
        }

        .score-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            opacity: 0.8;
            margin-top: 4px;
        }

        .score-risk {
            margin-top: 14px;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            color: white;
            position: relative;
            z-index: 1;
        }

        /* ===== STATS GRID ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
        }

        .stat-card {
            border-radius: 16px;
            padding: 22px 20px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: -30px; right: -30px;
            width: 100px; height: 100px;
            border-radius: 50%;
            background: rgba(255,255,255,0.08);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 14px 32px rgba(0,0,0,0.22);
        }

        .stat-card.assets      { background: linear-gradient(135deg, #3762c8 0%, #6490f5 100%); }
        .stat-card.incidents   { background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%); }
        .stat-card.maintenance { background: linear-gradient(135deg, #ef4444 0%, #f87171 100%); }
        .stat-card.energy      { background: linear-gradient(135deg, #8b5cf6 0%, #a78bfa 100%); }
        .stat-card.risk        { background: linear-gradient(135deg, #f97316 0%, #fb923c 100%); }
        .stat-card.score       { background: linear-gradient(135deg, #10b981 0%, #34d399 100%); }

        .stat-card-icon {
            background: rgba(255,255,255,0.18);
            border-radius: 12px;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .stat-card-icon i {
            font-size: 20px;
            color: #fff;
        }

        .stat-info h3 {
            font-size: 26px;
            font-weight: 700;
            color: #fff !important;
            margin: 0;
        }

        .stat-info p {
            font-size: 11px;
            color: rgba(255,255,255,0.85) !important;
            text-transform: uppercase;
            font-weight: 600;
            margin-top: 3px;
        }

        .stat-info .stat-sub {
            font-size: 11px;
            color: rgba(255,255,255,0.7) !important;
            margin-top: 2px;
            text-transform: none;
            font-weight: 400;
        }

        /* ===== TABS ===== */
        .tab-buttons {
            display: flex;
            gap: 10px;
            border-bottom: 2px solid #edf2f7;
            padding-bottom: 10px;
            margin-bottom: 25px;
            overflow-x: auto;
            margin-top: 30px;
        }

        .tab-btn {
            background: transparent;
            border: none;
            padding: 10px 18px;
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .tab-btn:hover { background: #f8fafc; color: #2c3e50; }
        .tab-btn.active { background: #3762c8; color: white; }

        .tab-pane { display: none; }
        .tab-pane.active { display: block; }

        /* ===== DASHBOARD LAYOUT ===== */
        .dashboard-layout {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 25px;
            margin-bottom: 25px;
        }

        .dashboard-layout.triple {
            grid-template-columns: 1fr 1fr 1fr;
        }

        .dashboard-layout.full {
            grid-template-columns: 1fr;
        }

        @media (max-width: 1000px) {
            .dashboard-layout, .dashboard-layout.triple { grid-template-columns: 1fr; }
        }

        .box {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .box h3 {
            font-size: 16px;
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 2px solid #f1f2f6;
            padding-bottom: 10px;
        }

        /* ===== AI BOX ===== */
        .ai-box {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            border: none;
        }
        .ai-box h3 { color: white; border-bottom-color: rgba(255,255,255,0.15); }
        .ai-box h3 i { color: #45aaf2; animation: pulse 2s infinite; }
        .ai-content { font-size: 13px; line-height: 1.7; background: rgba(0, 0, 0, 0.2); padding: 20px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.15); }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.15); }
        }

        /* ===== RECOMMENDATIONS ===== */
        .rec-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .rec-item {
            padding: 14px 16px;
            border-radius: 10px;
            background: #f8fafc;
            border-left: 4px solid #cbd5e1;
            transition: all 0.3s ease;
        }

        .rec-item:hover {
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .rec-item .rec-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 6px;
        }

        .rec-item .rec-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 14px;
            flex-shrink: 0;
        }

        .rec-item .rec-title {
            font-weight: 600;
            font-size: 14px;
            color: #2c3e50;
        }

        .rec-item .rec-badge {
            font-size: 9px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 99px;
            text-transform: uppercase;
            margin-left: auto;
            flex-shrink: 0;
        }

        .rec-item .rec-text {
            font-size: 12px;
            color: #64748b;
            line-height: 1.5;
            padding-left: 42px;
        }

        /* ===== RISK TABLE ===== */
        .risk-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .risk-table th {
            background: #f8fafc;
            padding: 10px 14px;
            text-align: left;
            font-weight: 600;
            color: #475569;
            border-bottom: 2px solid #e2e8f0;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .risk-table td {
            padding: 10px 14px;
            border-bottom: 1px solid #f1f5f9;
            color: #2c3e50;
        }

        .risk-table tr:hover td {
            background: #f8fafc;
        }

        .status-badge {
            font-size: 10px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 99px;
            display: inline-block;
        }

        .status-badge.damaged { background: #fee2e2; color: #dc2626; }
        .status-badge.inspection { background: #fef3c7; color: #d97706; }

        /* ===== SCORE BARS ===== */
        .score-bars {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .score-bar-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .score-bar-label {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            font-weight: 500;
            color: #2c3e50;
        }

        .score-bar-track {
            height: 10px;
            background: #e2e8f0;
            border-radius: 99px;
            overflow: hidden;
        }

        .score-bar-fill {
            height: 100%;
            border-radius: 99px;
            transition: width 1.2s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        /* ===== PIPELINE FUNNEL ===== */
        .pipeline-steps {
            display: flex;
            align-items: center;
            gap: 0;
            margin: 20px 0;
        }

        .pipeline-step {
            flex: 1;
            text-align: center;
            padding: 14px 8px;
            position: relative;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .pipeline-step:first-child { border-radius: 10px 0 0 10px; }
        .pipeline-step:last-child { border-radius: 0 10px 10px 0; }

        .pipeline-step:hover {
            background: #eef2ff;
            transform: translateY(-2px);
        }

        .pipeline-step .step-count {
            font-size: 22px;
            font-weight: 700;
            color: #2c3e50;
        }

        .pipeline-step .step-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            font-weight: 600;
            margin-top: 4px;
        }

        .pipeline-arrow {
            width: 0;
            height: 0;
            border-top: 12px solid transparent;
            border-bottom: 12px solid transparent;
            border-left: 12px solid #e2e8f0;
            flex-shrink: 0;
        }

        /* ===== CHART CONTAINERS ===== */
        .chart-container {
            position: relative;
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .chart-container.small { height: 250px; }
        .chart-container.medium { height: 300px; }
        .chart-container.large { height: 350px; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .main-content { padding: 15px; }
            .card { padding: 20px; }
            .dashboard-header h1 { font-size: 22px; }
            .ai-score-hero { grid-template-columns: 1fr; }
            .pipeline-steps { flex-direction: column; }
            .pipeline-step { border-radius: 8px !important; }
            .pipeline-arrow { 
                border-left: 12px solid transparent;
                border-right: 12px solid transparent;
                border-top: 12px solid #e2e8f0;
                border-bottom: none;
            }
        }

        /* ===== DARK THEME OVERRIDES ===== */
        .dark-theme .card {
            background: rgba(30, 41, 59, 0.9) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: #f8fafc !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5) !important;
        }
        .dark-theme .dashboard-header h1 {
            color: #f8fafc !important;
        }

        .dark-theme .box {
            background: #1e293b !important;
            border: 1px solid #334155 !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3) !important;
            color: #f8fafc !important;
        }
        .dark-theme .box h3 {
            color: #f8fafc !important;
            border-bottom-color: #334155 !important;
        }
        .dark-theme .tab-buttons {
            border-bottom-color: #334155 !important;
        }
        .dark-theme .tab-btn {
            color: #94a3b8 !important;
        }
        .dark-theme .tab-btn:hover {
            background: #151f32 !important;
            color: #f8fafc !important;
        }
        .dark-theme .tab-btn.active {
            background: #3762c8 !important;
            color: #ffffff !important;
        }
        .dark-theme .rec-item {
            background: #0f172a !important;
            border: 1px solid #334155 !important;
        }
        .dark-theme .rec-item .rec-title {
            color: #f8fafc !important;
        }
        .dark-theme .rec-item .rec-text {
            color: #cbd5e1 !important;
        }
        .dark-theme .risk-table th {
            background: #0f172a !important;
            color: #94a3b8 !important;
            border-bottom-color: #334155 !important;
        }
        .dark-theme .risk-table td {
            color: #cbd5e1 !important;
            border-bottom-color: #334155 !important;
        }
        .dark-theme .pipeline-step {
            background: #0f172a !important;
            border: 1px solid #334155 !important;
        }
        .dark-theme .pipeline-step .step-num {
            color: #f8fafc !important;
        }
        .dark-theme .pipeline-step .step-label {
            color: #94a3b8 !important;
        }
        .dark-theme .pipeline-arrow {
            border-left-color: #334155 !important;
        }
        .dark-theme .prog-bar {
            background: #0f172a !important;
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
                <h1><i class="fas fa-brain"></i> AI Analytics Center</h1>
                <p style="color: #64748b; font-size: 14px; margin-top: 5px;">AI-powered insights across all LGU utility modules — generated <?php echo date('F d, Y h:i A'); ?></p>
            </div>
            <div>
                <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Export Report</button>
            </div>
        </div>

        <!-- AI Score Hero + Stats -->
        <div class="ai-score-hero">
            <div class="score-gauge">
                <div class="score-circle">
                    <div class="score-inner">
                        <span class="score-number" id="aiScoreCounter">0</span>
                        <span class="score-label">AI Score</span>
                    </div>
                </div>
                <div class="score-risk" style="background: <?php echo $riskColor; ?>;">
                    <i class="fas <?php echo $riskIcon; ?>"></i> <?php echo $riskLevel; ?> Risk
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card assets">
                    <div class="stat-card-icon"><i class="fas fa-warehouse"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $assetHealthScore; ?>%</h3>
                        <p>Asset Health</p>
                        <div class="stat-sub"><?php echo $operationalAssets; ?>/<?php echo $totalAssets; ?> operational</div>
                    </div>
                </div>
                <div class="stat-card incidents">
                    <div class="stat-card-icon"><i class="fas fa-bullhorn"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $incidentResolutionRate; ?>%</h3>
                        <p>Resolution Rate</p>
                        <div class="stat-sub"><?php echo $resolvedIncidents; ?>/<?php echo $totalIncidents; ?> resolved</div>
                    </div>
                </div>
                <div class="stat-card maintenance">
                    <div class="stat-card-icon"><i class="fas fa-tools"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $maintCompletionRate; ?>%</h3>
                        <p>Maint. Completion</p>
                        <div class="stat-sub"><?php echo $completedMaint; ?>/<?php echo (int)($maintenance['total_requests'] ?? 0); ?> completed</div>
                    </div>
                </div>
                <div class="stat-card energy">
                    <div class="stat-card-icon"><i class="fas fa-bolt"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $energyScore; ?>%</h3>
                        <p>Energy Efficiency</p>
                        <div class="stat-sub"><?php echo $pendingAdvisories; ?> pending advisories</div>
                    </div>
                </div>
                <div class="stat-card risk">
                    <div class="stat-card-icon"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $riskAlerts; ?></h3>
                        <p>Risk Alerts</p>
                        <div class="stat-sub"><?php echo $riskLevel; ?> threat level</div>
                    </div>
                </div>
                <div class="stat-card score">
                    <div class="stat-card-icon"><i class="fas fa-sync-alt"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $successfulSyncs; ?></h3>
                        <p>Data Syncs</p>
                        <div class="stat-sub">Energy system exports</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tab-buttons">
            <button class="tab-btn active" onclick="switchTab(event, 'overview-pane')"><i class="fas fa-th-large"></i> Overview</button>
            <button class="tab-btn" onclick="switchTab(event, 'assets-pane')"><i class="fas fa-warehouse"></i> Assets Intelligence</button>
            <button class="tab-btn" onclick="switchTab(event, 'incidents-pane')"><i class="fas fa-bullhorn"></i> Incidents & Maintenance</button>
            <button class="tab-btn" onclick="switchTab(event, 'energy-pane')"><i class="fas fa-bolt"></i> Energy Intelligence</button>
        </div>

        <!-- =============================== -->
        <!-- TAB 1: OVERVIEW                 -->
        <!-- =============================== -->
        <div id="overview-pane" class="tab-pane active">
            <div class="dashboard-layout" style="grid-template-columns: 1.5fr 1fr;">
                <!-- AI Summary -->
                <div class="box ai-box">
                    <h3><i class="fas fa-robot"></i> AI Analytics Narrative</h3>
                    <div class="ai-content">
                        <?php echo $aiNarrative; ?>
                    </div>
                </div>

                <!-- Radar Chart -->
                <div class="box">
                    <h3><i class="fas fa-chart-radar"></i> Module Health Radar</h3>
                    <div class="chart-container medium">
                        <canvas id="radarChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Score Bars + Recommendations -->
            <div class="dashboard-layout" style="grid-template-columns: 1fr 1.3fr;">
                <div class="box">
                    <h3><i class="fas fa-tachometer-alt"></i> Module Scores Breakdown</h3>
                    <div class="score-bars">
                        <div class="score-bar-item">
                            <div class="score-bar-label">
                                <span><i class="fas fa-warehouse" style="color:#3762c8;margin-right:6px;"></i>Asset Health</span>
                                <span><?php echo $assetHealthScore; ?>%</span>
                            </div>
                            <div class="score-bar-track">
                                <div class="score-bar-fill" style="width:0%;background:linear-gradient(90deg,#3762c8,#6384d2);" data-width="<?php echo $assetHealthScore; ?>"></div>
                            </div>
                        </div>
                        <div class="score-bar-item">
                            <div class="score-bar-label">
                                <span><i class="fas fa-bullhorn" style="color:#f1c40f;margin-right:6px;"></i>Incident Resolution</span>
                                <span><?php echo $incidentResolutionRate; ?>%</span>
                            </div>
                            <div class="score-bar-track">
                                <div class="score-bar-fill" style="width:0%;background:linear-gradient(90deg,#f1c40f,#f39c12);" data-width="<?php echo $incidentResolutionRate; ?>"></div>
                            </div>
                        </div>
                        <div class="score-bar-item">
                            <div class="score-bar-label">
                                <span><i class="fas fa-tools" style="color:#e74c3c;margin-right:6px;"></i>Maintenance Completion</span>
                                <span><?php echo $maintCompletionRate; ?>%</span>
                            </div>
                            <div class="score-bar-track">
                                <div class="score-bar-fill" style="width:0%;background:linear-gradient(90deg,#e74c3c,#f87171);" data-width="<?php echo $maintCompletionRate; ?>"></div>
                            </div>
                        </div>
                        <div class="score-bar-item">
                            <div class="score-bar-label">
                                <span><i class="fas fa-bolt" style="color:#a55eea;margin-right:6px;"></i>Energy Efficiency</span>
                                <span><?php echo $energyScore; ?>%</span>
                            </div>
                            <div class="score-bar-track">
                                <div class="score-bar-fill" style="width:0%;background:linear-gradient(90deg,#a55eea,#c084fc);" data-width="<?php echo $energyScore; ?>"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="box">
                    <h3><i class="fas fa-lightbulb"></i> AI Recommendations <span style="background:#3762c8;color:#fff;font-size:10px;padding:2px 8px;border-radius:99px;margin-left:8px;"><?php echo count($recommendations); ?></span></h3>
                    <div class="rec-list" style="max-height: 320px; overflow-y: auto;">
                        <?php foreach ($recommendations as $rec): ?>
                        <div class="rec-item" style="border-left-color: <?php echo $rec['color']; ?>;">
                            <div class="rec-header">
                                <div class="rec-icon" style="background: <?php echo $rec['color']; ?>;">
                                    <i class="fas <?php echo $rec['icon']; ?>"></i>
                                </div>
                                <span class="rec-title"><?php echo $rec['title']; ?></span>
                                <span class="rec-badge" style="background: <?php echo $rec['color']; ?>20; color: <?php echo $rec['color']; ?>;"><?php echo $rec['priority']; ?></span>
                            </div>
                            <div class="rec-text"><?php echo $rec['text']; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- =============================== -->
        <!-- TAB 2: ASSETS INTELLIGENCE      -->
        <!-- =============================== -->
        <div id="assets-pane" class="tab-pane">
            <div class="dashboard-layout">
                <div class="box">
                    <h3><i class="fas fa-chart-pie"></i> Asset Condition Distribution</h3>
                    <div class="chart-container medium">
                        <canvas id="assetCondChart"></canvas>
                    </div>
                </div>
                <div class="box">
                    <h3><i class="fas fa-chart-bar"></i> Assets by Category</h3>
                    <div class="chart-container medium">
                        <canvas id="assetCatChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="dashboard-layout" style="grid-template-columns: 1fr;">
                <div class="box">
                    <h3><i class="fas fa-exclamation-triangle"></i> At-Risk Assets <span style="background:#fee2e2;color:#dc2626;font-size:10px;padding:2px 8px;border-radius:99px;margin-left:8px;"><?php echo count($recentDamaged); ?> flagged</span></h3>
                    <?php if (empty($recentDamaged)): ?>
                        <p style="color: #64748b; font-size: 13px;">No at-risk assets currently flagged. All assets are in good condition.</p>
                    <?php else: ?>
                    <table class="risk-table">
                        <thead>
                            <tr>
                                <th>Asset Name</th>
                                <th>Type</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Last Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentDamaged as $ra): ?>
                            <tr>
                                <td style="font-weight:600;"><?php echo htmlspecialchars($ra['asset_name']); ?></td>
                                <td><?php echo htmlspecialchars($ra['type_name']); ?></td>
                                <td><?php echo htmlspecialchars($ra['location']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $ra['condition_status'] === 'Damaged' ? 'damaged' : 'inspection'; ?>">
                                        <?php echo $ra['condition_status']; ?>
                                    </span>
                                </td>
                                <td style="color:#94a3b8;font-size:12px;"><?php echo date('M d, Y', strtotime($ra['updated_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- =============================== -->
        <!-- TAB 3: INCIDENTS & MAINTENANCE  -->
        <!-- =============================== -->
        <div id="incidents-pane" class="tab-pane">
            <!-- Pipeline -->
            <div class="box" style="margin-bottom: 25px;">
                <h3><i class="fas fa-stream"></i> Incident Resolution Pipeline</h3>
                <div class="pipeline-steps">
                    <div class="pipeline-step" style="background: linear-gradient(135deg, #eff6ff, #dbeafe);">
                        <div class="step-count"><?php echo $incidents['submitted_incidents'] ?? 0; ?></div>
                        <div class="step-label">Submitted</div>
                    </div>
                    <div class="pipeline-arrow"></div>
                    <div class="pipeline-step" style="background: linear-gradient(135deg, #fef9c3, #fef3c7);">
                        <div class="step-count"><?php echo $incidents['review_incidents'] ?? 0; ?></div>
                        <div class="step-label">Under Review</div>
                    </div>
                    <div class="pipeline-arrow"></div>
                    <div class="pipeline-step" style="background: linear-gradient(135deg, #fce7f3, #fecdd3);">
                        <div class="step-count"><?php echo $incidents['forwarded_incidents'] ?? 0; ?></div>
                        <div class="step-label">Forwarded</div>
                    </div>
                    <div class="pipeline-arrow"></div>
                    <div class="pipeline-step" style="background: linear-gradient(135deg, #dcfce7, #bbf7d0);">
                        <div class="step-count"><?php echo $incidents['resolved_incidents'] ?? 0; ?></div>
                        <div class="step-label">Resolved</div>
                    </div>
                </div>
            </div>

            <div class="dashboard-layout">
                <div class="box">
                    <h3><i class="fas fa-chart-bar"></i> Incidents by Category</h3>
                    <div class="chart-container medium">
                        <canvas id="incCatChart"></canvas>
                    </div>
                </div>
                <div class="box">
                    <h3><i class="fas fa-chart-doughnut"></i> Incidents by Priority</h3>
                    <div class="chart-container medium">
                        <canvas id="incPriorChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="dashboard-layout">
                <div class="box">
                    <h3><i class="fas fa-chart-line"></i> Incident Trend (Last 6 Months)</h3>
                    <div class="chart-container medium">
                        <canvas id="incTrendChart"></canvas>
                    </div>
                </div>
                <div class="box">
                    <h3><i class="fas fa-tools"></i> Maintenance by Priority</h3>
                    <div class="chart-container medium">
                        <canvas id="maintPriorChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="dashboard-layout">
                <div class="box">
                    <h3><i class="fas fa-sitemap"></i> Maintenance by Source</h3>
                    <div class="chart-container medium">
                        <canvas id="maintSourceChart"></canvas>
                    </div>
                </div>
                <div class="box" style="display:flex;flex-direction:column;justify-content:center;">
                    <h3><i class="fas fa-info-circle"></i> Correlation Insights</h3>
                    <div style="font-size:13px;color:#64748b;line-height:1.7;">
                        <p style="margin-bottom:12px;"><strong style="color:#2c3e50;">Incident → Maintenance Link:</strong><br>
                        Of <?php echo $totalIncidents; ?> total incidents, <?php echo $incidents['forwarded_incidents'] ?? 0; ?> have been forwarded to the maintenance system. The maintenance pipeline currently has <?php echo $maintenance['total_requests'] ?? 0; ?> total requests with <?php echo $forwardedMaint; ?> in forwarded status.</p>
                        
                        <p style="margin-bottom:12px;"><strong style="color:#2c3e50;">Emergency Correlation:</strong><br>
                        <?php echo $emergencyIncidents; ?> emergency incident(s) vs. <?php echo $emergencyMaint; ?> emergency maintenance request(s). 
                        <?php if ($emergencyIncidents > $emergencyMaint): ?>
                            <span style="color:#e74c3c;">⚠ Gap detected — some emergency incidents may not have corresponding maintenance dispatches.</span>
                        <?php else: ?>
                            <span style="color:#27ae60;">✓ Emergency coverage appears adequate.</span>
                        <?php endif; ?>
                        </p>

                        <p><strong style="color:#2c3e50;">Completion Efficiency:</strong><br>
                        Maintenance completion rate: <strong><?php echo $maintCompletionRate; ?>%</strong> | Incident resolution rate: <strong><?php echo $incidentResolutionRate; ?>%</strong></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- =============================== -->
        <!-- TAB 4: ENERGY INTELLIGENCE      -->
        <!-- =============================== -->
        <div id="energy-pane" class="tab-pane">
            <div class="dashboard-layout">
                <div class="box">
                    <h3><i class="fas fa-chart-area"></i> Energy Consumption Trend</h3>
                    <div class="chart-container medium">
                        <canvas id="energyTrendChart"></canvas>
                    </div>
                </div>
                <div class="box">
                    <h3><i class="fas fa-chart-pie"></i> Consumption by Asset Type</h3>
                    <div class="chart-container medium">
                        <canvas id="energyTypeChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="dashboard-layout">
                <div class="box">
                    <h3><i class="fas fa-building"></i> Top Consuming Facilities</h3>
                    <div class="chart-container medium">
                        <canvas id="topFacilityChart"></canvas>
                    </div>
                </div>
                <div class="box" style="display:flex;flex-direction:column;justify-content:center;">
                    <h3><i class="fas fa-file-invoice-dollar"></i> Energy Cost Summary</h3>
                    <div style="font-size:13px;color:#64748b;line-height:1.7;">
                        <p style="margin-bottom:12px;">
                            <strong style="color:#2c3e50;font-size:15px;">Total Consumption:</strong><br>
                            <span style="font-size:28px;font-weight:700;color:#a55eea;"><?php echo number_format($energy['total_consumption'] ?? 0, 1); ?></span> <span style="font-size:14px;">kWh</span>
                        </p>
                        <p style="margin-bottom:12px;">
                            <strong style="color:#2c3e50;font-size:15px;">Estimated Cost:</strong><br>
                            <span style="font-size:28px;font-weight:700;color:#e74c3c;">₱<?php echo number_format($energy['total_cost'] ?? 0, 2); ?></span>
                        </p>
                        <p style="margin-bottom:12px;">
                            <strong style="color:#2c3e50;">Records Tracked:</strong> <?php echo number_format($energy['total_records'] ?? 0); ?>
                        </p>
                        <p>
                            <strong style="color:#2c3e50;">Successful Syncs:</strong> <?php echo $successfulSyncs; ?> exports to Energy Efficiency System
                        </p>
                        <?php if ($pendingAdvisories > 0): ?>
                        <p style="margin-top:12px;padding:10px;background:#fef3c7;border-radius:8px;color:#92400e;font-weight:500;">
                            <i class="fas fa-lightbulb" style="color:#d97706;"></i> <?php echo $pendingAdvisories; ?> energy efficiency recommendation(s) pending action.
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</main>

<script>
    // Tab Switching
    function switchTab(evt, tabId) {
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById(tabId).classList.add('active');
        evt.currentTarget.classList.add('active');
    }

    // Animated Score Counter
    (function animateScore() {
        const target = <?php echo $aiScore; ?>;
        const el = document.getElementById('aiScoreCounter');
        let current = 0;
        const step = Math.max(1, Math.ceil(target / 60));
        const interval = setInterval(() => {
            current += step;
            if (current >= target) {
                current = target;
                clearInterval(interval);
            }
            el.textContent = current;
        }, 20);
    })();

    // Animate Score Bars
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            document.querySelectorAll('.score-bar-fill').forEach(bar => {
                bar.style.width = bar.dataset.width + '%';
            });
        }, 300);
    });

    // ===== CHART CONFIG HELPERS =====
    const chartColors = {
        blue: '#3762c8',
        blueLight: '#6384d2',
        yellow: '#f1c40f',
        red: '#e74c3c',
        green: '#27ae60',
        purple: '#a55eea',
        orange: '#f39c12',
        teal: '#1abc9c',
        pink: '#e84393',
        gray: '#95a5a6'
    };

    const palette = ['#3762c8','#e74c3c','#f1c40f','#27ae60','#a55eea','#1abc9c','#f39c12','#e84393','#95a5a6','#2c3e50'];

    const isDark = document.documentElement.classList.contains('dark-theme');
    Chart.defaults.font.family = "'Poppins', sans-serif";
    Chart.defaults.font.size = 12;
    if (isDark) {
        Chart.defaults.color = '#94a3b8';
    }

    // ===== 1. RADAR CHART =====
    new Chart(document.getElementById('radarChart'), {
        type: 'radar',
        data: {
            labels: ['Asset Health', 'Incident Resolution', 'Maintenance Completion', 'Energy Efficiency'],
            datasets: [{
                label: 'Module Score',
                data: <?php echo $radarData; ?>,
                backgroundColor: isDark ? 'rgba(55, 98, 200, 0.25)' : 'rgba(55, 98, 200, 0.15)',
                borderColor: '#3762c8',
                borderWidth: 2,
                pointBackgroundColor: '#3762c8',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 6,
                pointHoverRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                r: {
                    beginAtZero: true,
                    max: 100,
                    ticks: { 
                        stepSize: 25, 
                        font: { size: 10 },
                        color: isDark ? '#94a3b8' : '#64748b',
                        backdropColor: 'transparent'
                    },
                    pointLabels: {
                        color: isDark ? '#f8fafc' : '#2c3e50',
                        font: { size: 12, weight: '600' }
                    },
                    grid: { color: isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.06)' },
                    angleLines: { color: isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.06)' }
                }
            },
            plugins: { legend: { display: false } }
        }
    });

    // ===== 2. ASSET CONDITION CHART =====
    new Chart(document.getElementById('assetCondChart'), {
        type: 'doughnut',
        data: {
            labels: <?php echo $assetCondLabels; ?>,
            datasets: [{
                data: <?php echo $assetCondData; ?>,
                backgroundColor: ['#27ae60', '#e74c3c', '#f1c40f', '#3762c8', '#a55eea', '#95a5a6'],
                borderWidth: 3,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '55%',
            plugins: {
                legend: { position: 'bottom', labels: { padding: 15, font: { size: 11 } } }
            }
        }
    });

    // ===== 3. ASSET CATEGORY CHART =====
    new Chart(document.getElementById('assetCatChart'), {
        type: 'bar',
        data: {
            labels: <?php echo $assetCatLabels; ?>,
            datasets: [{
                label: 'Assets',
                data: <?php echo $assetCatData; ?>,
                backgroundColor: palette,
                borderRadius: 6,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' } },
                x: { grid: { display: false } }
            }
        }
    });

    // ===== 4. INCIDENT CATEGORY CHART =====
    new Chart(document.getElementById('incCatChart'), {
        type: 'bar',
        data: {
            labels: <?php echo $incCatLabels; ?>,
            datasets: [{
                label: 'Reports',
                data: <?php echo $incCatData; ?>,
                backgroundColor: '#f1c40f',
                borderRadius: 6,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' } },
                y: { grid: { display: false } }
            }
        }
    });

    // ===== 5. INCIDENT PRIORITY CHART =====
    new Chart(document.getElementById('incPriorChart'), {
        type: 'doughnut',
        data: {
            labels: <?php echo $incPriorLabels; ?>,
            datasets: [{
                data: <?php echo $incPriorData; ?>,
                backgroundColor: ['#27ae60', '#f1c40f', '#e67e22', '#e74c3c', '#95a5a6'],
                borderWidth: 3,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '55%',
            plugins: {
                legend: { position: 'bottom', labels: { padding: 15, font: { size: 11 } } }
            }
        }
    });

    // ===== 6. INCIDENT TREND CHART =====
    new Chart(document.getElementById('incTrendChart'), {
        type: 'line',
        data: {
            labels: <?php echo $incTrendLabels; ?>,
            datasets: [{
                label: 'Incidents',
                data: <?php echo $incTrendData; ?>,
                borderColor: '#f1c40f',
                backgroundColor: 'rgba(241, 196, 15, 0.1)',
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#f1c40f',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' } },
                x: { grid: { display: false } }
            }
        }
    });

    // ===== 7. MAINTENANCE PRIORITY CHART =====
    new Chart(document.getElementById('maintPriorChart'), {
        type: 'bar',
        data: {
            labels: <?php echo $maintPriorLabels; ?>,
            datasets: [{
                label: 'Requests',
                data: <?php echo $maintPriorData; ?>,
                backgroundColor: ['#27ae60', '#f1c40f', '#e67e22', '#e74c3c', '#95a5a6'],
                borderRadius: 6,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' } },
                x: { grid: { display: false } }
            }
        }
    });

    // ===== 8. MAINTENANCE SOURCE CHART =====
    new Chart(document.getElementById('maintSourceChart'), {
        type: 'doughnut',
        data: {
            labels: <?php echo $maintSourceLabels; ?>,
            datasets: [{
                data: <?php echo $maintSourceData; ?>,
                backgroundColor: palette,
                borderWidth: 3,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '55%',
            plugins: {
                legend: { position: 'bottom', labels: { padding: 15, font: { size: 11 } } }
            }
        }
    });

    // ===== 9. ENERGY TREND CHART =====
    new Chart(document.getElementById('energyTrendChart'), {
        type: 'line',
        data: {
            labels: <?php echo $energyTrendLabels; ?>,
            datasets: [{
                label: 'Consumption (kWh)',
                data: <?php echo $energyTrendKwh; ?>,
                borderColor: '#a55eea',
                backgroundColor: 'rgba(165, 94, 234, 0.1)',
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#a55eea',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                yAxisID: 'y'
            },{
                label: 'Cost (₱)',
                data: <?php echo $energyTrendCost; ?>,
                borderColor: '#e74c3c',
                backgroundColor: 'rgba(231, 76, 60, 0.05)',
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#e74c3c',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'top', labels: { padding: 15, font: { size: 11 } } }
            },
            scales: {
                y: {
                    type: 'linear',
                    position: 'left',
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.04)' },
                    title: { display: true, text: 'kWh', font: { size: 11 } }
                },
                y1: {
                    type: 'linear',
                    position: 'right',
                    beginAtZero: true,
                    grid: { display: false },
                    title: { display: true, text: '₱ Cost', font: { size: 11 } }
                },
                x: { grid: { display: false } }
            }
        }
    });

    // ===== 10. ENERGY BY TYPE CHART =====
    new Chart(document.getElementById('energyTypeChart'), {
        type: 'doughnut',
        data: {
            labels: <?php echo $energyTypeLabels; ?>,
            datasets: [{
                data: <?php echo $energyTypeData; ?>,
                backgroundColor: palette,
                borderWidth: 3,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '55%',
            plugins: {
                legend: { position: 'bottom', labels: { padding: 15, font: { size: 11 } } }
            }
        }
    });

    // ===== 11. TOP FACILITIES CHART =====
    new Chart(document.getElementById('topFacilityChart'), {
        type: 'bar',
        data: {
            labels: <?php echo $topFacilityLabels; ?>,
            datasets: [{
                label: 'kWh',
                data: <?php echo $topFacilityData; ?>,
                backgroundColor: 'rgba(165, 94, 234, 0.7)',
                borderColor: '#a55eea',
                borderWidth: 1,
                borderRadius: 6,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' }, title: { display: true, text: 'kWh', font: { size: 11 } } },
                y: { grid: { display: false } }
            }
        }
    });
</script>

</body>
</html>
