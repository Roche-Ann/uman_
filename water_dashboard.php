<?php
// water_dashboard.php — Redesigned Water Management Dashboard
require_once 'includes/auth.php';
require_once 'includes/db.php';
ensureWaterSchema();

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// ============================================================
// FILTER PARAMETERS
// ============================================================
$fYear     = !empty($_GET['year'])     ? intval($_GET['year'])          : intval(date('Y'));
$fMonth    = !empty($_GET['month'])    ? intval($_GET['month'])          : 0; // 0 = All months
$fType     = trim($_GET['asset_type'] ?? '');
$fLocation = trim($_GET['location']   ?? '');

// ============================================================
// SUMMARY CARD QUERIES
// ============================================================
$totalConsumption       = $pdo->query("SELECT COALESCE(SUM(consumption_m3),0) FROM water_consumption_records")->fetchColumn();
$totalCost              = $pdo->query("SELECT COALESCE(SUM(cost),0) FROM water_consumption_records")->fetchColumn();
$pendingAdvisories      = $pdo->query("SELECT COUNT(*) FROM water_recommendations WHERE status = 'Pending'")->fetchColumn();
$successfulSyncs        = $pdo->query("SELECT COUNT(*) FROM water_sync_logs WHERE status = 'Successful'")->fetchColumn();
$lastSyncRow            = $pdo->query("SELECT transferred_at FROM water_sync_logs ORDER BY transferred_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$lastSyncDate           = $lastSyncRow ? date('M d, Y', strtotime($lastSyncRow['transferred_at'])) : 'Never';

// Distinct facilities/assets count (all time)
$facilityCount = $pdo->query("SELECT COUNT(DISTINCT COALESCE(facility_name, CONCAT(asset_type,'-',location))) FROM water_consumption_records")->fetchColumn();

// ============================================================
// BUILD FILTER WHERE CLAUSE (for charts & table)
// ============================================================
$filterConds  = [];
$filterParams = [];

// Year filter (always applied)
$filterConds[]  = "YEAR(STR_TO_DATE(CONCAT(month_year,'-01'),'%Y-%m-%d')) = ?";
$filterParams[] = $fYear;

if ($fMonth > 0) {
    $filterConds[]  = "MONTH(STR_TO_DATE(CONCAT(month_year,'-01'),'%Y-%m-%d')) = ?";
    $filterParams[] = $fMonth;
}
if ($fType !== '') {
    $filterConds[]  = "asset_type = ?";
    $filterParams[] = $fType;
}
if ($fLocation !== '') {
    $filterConds[]  = "location LIKE ?";
    $filterParams[] = '%' . $fLocation . '%';
}
$filterWhere = 'WHERE ' . implode(' AND ', $filterConds);

// ============================================================
// FILTERED TOTAL
// ============================================================
$stmtFTotal = $pdo->prepare("SELECT COALESCE(SUM(consumption_m3),0) FROM water_consumption_records $filterWhere");
$stmtFTotal->execute($filterParams);
$filteredTotal = (float)$stmtFTotal->fetchColumn();

$stmtFCost = $pdo->prepare("SELECT COALESCE(SUM(cost),0) FROM water_consumption_records $filterWhere");
$stmtFCost->execute($filterParams);
$filteredCost = (float)$stmtFCost->fetchColumn();

// ============================================================
// MONTHLY TREND — single aggregate line for selected year
// ============================================================
$trendConds  = [];
$trendParams = [];
$trendConds[]  = "YEAR(STR_TO_DATE(CONCAT(month_year,'-01'),'%Y-%m-%d')) = ?";
$trendParams[] = $fYear;
if ($fType !== '') {
    $trendConds[]  = "asset_type = ?";
    $trendParams[] = $fType;
}
if ($fLocation !== '') {
    $trendConds[]  = "location LIKE ?";
    $trendParams[] = '%' . $fLocation . '%';
}
$trendWhere = 'WHERE ' . implode(' AND ', $trendConds);

$stmtTrend = $pdo->prepare("
    SELECT month_year, COALESCE(SUM(consumption_m3),0) as total_m3
    FROM water_consumption_records
    $trendWhere
    GROUP BY month_year
    ORDER BY month_year ASC
");
$stmtTrend->execute($trendParams);
$trendRows = $stmtTrend->fetchAll(PDO::FETCH_ASSOC);

// Build complete 12-month array for selected year
$trendLabels = [];
$trendData   = [];
$monthNames  = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$trendMap    = [];
foreach ($trendRows as $tr) {
    $trendMap[$tr['month_year']] = (float)$tr['total_m3'];
}
for ($m = 1; $m <= 12; $m++) {
    $key = sprintf('%d-%02d', $fYear, $m);
    $trendLabels[] = $monthNames[$m - 1];
    $trendData[]   = $trendMap[$key] ?? null;
}

$trendLabelsJson = json_encode($trendLabels);
$trendDataJson   = json_encode($trendData);

// ============================================================
// FACILITY / ASSET HORIZONTAL BAR CHART + TOP CONSUMERS
// ============================================================
$stmtFac = $pdo->prepare("
    SELECT COALESCE(facility_name, CONCAT(asset_type, ' — ', location)) as label,
           asset_type,
           COALESCE(SUM(consumption_m3),0) as m3
    FROM water_consumption_records
    $filterWhere
    GROUP BY label, asset_type
    ORDER BY m3 DESC
");
$stmtFac->execute($filterParams);
$facilityRows = $stmtFac->fetchAll(PDO::FETCH_ASSOC);

// Top 8 for chart
$chartFacilityRows = array_slice($facilityRows, 0, 8);
$facLabels = [];
$facData   = [];
foreach ($chartFacilityRows as $fr) {
    $facLabels[] = $fr['label'];
    $facData[]   = (float)$fr['m3'];
}
$facLabelsJson = json_encode($facLabels);
$facDataJson   = json_encode($facData);

// Top 5 consumers for ranking section
$topConsumers = array_slice($facilityRows, 0, 5);

// ============================================================
// AI DIGEST
// ============================================================
function generateAIDigest(array $facilityRows, float $filteredTotal, float $filteredCost, int $fYear, int $fMonth, string $fType): string {
    $monthNames = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
    $periodLabel = ($fMonth > 0) ? ($monthNames[$fMonth] . ' ' . $fYear) : 'Year ' . $fYear;
    if ($fType) $periodLabel .= " ({$fType})";

    if (empty($facilityRows) || $filteredTotal == 0) {
        return "No water consumption records found for the selected period (<strong>{$periodLabel}</strong>). "
             . "Please add consumption readings via the Water Readings page or adjust your filters.";
    }

    $count  = count($facilityRows);
    $top    = $facilityRows[0];
    $bottom = end($facilityRows);
    $topPct = $filteredTotal > 0 ? round(($top['m3'] / $filteredTotal) * 100, 1) : 0;

    $out  = "📊 <strong>Consumption Summary for {$periodLabel}</strong><br><br>";
    $out .= "Total recorded water consumption is <strong>" . number_format($filteredTotal, 2) . " m³</strong>";
    if ($filteredCost > 0) {
        $out .= " with an estimated cost of <strong>₱" . number_format($filteredCost, 2) . "</strong>";
    }
    $out .= ". A total of <strong>{$count} " . ($count === 1 ? 'facility/asset' : 'facilities/assets') . "</strong> have recorded consumption for this period.<br><br>";

    $out .= "💧 <strong>Highest Consumer:</strong> ";
    $out .= htmlspecialchars($top['label']) . " with <strong>" . number_format($top['m3'], 2) . " m³</strong>";
    $out .= " — accounting for <strong>{$topPct}%</strong> of total consumption.<br>";

    if ($count > 1) {
        $out .= "📉 <strong>Lowest Consumer:</strong> ";
        $out .= htmlspecialchars($bottom['label']) . " with <strong>" . number_format($bottom['m3'], 2) . " m³</strong>.<br>";
    }

    if ($count > 2) {
        $out .= "<br>📋 <strong>Consumption Distribution:</strong><br>";
        foreach (array_slice($facilityRows, 0, 5) as $idx => $fr) {
            $pct = $filteredTotal > 0 ? round(($fr['m3'] / $filteredTotal) * 100, 1) : 0;
            $out .= "• " . htmlspecialchars($fr['label']) . ": <strong>" . number_format($fr['m3'], 2) . " m³</strong> ({$pct}%)<br>";
        }
        if ($count > 5) {
            $out .= "• … and " . ($count - 5) . " more " . ($count - 5 == 1 ? 'facility/asset' : 'facilities/assets') . ".<br>";
        }
    }

    return $out;
}
$aiDigest = generateAIDigest($facilityRows, $filteredTotal, $filteredCost, $fYear, $fMonth, $fType);

// AI Analytics — Water Intelligence Score
$waterScore = max(0, 100 - ($pendingAdvisories * 10));
$waterScore = min(100, $waterScore);

$waterAiRecs = [];
if ($pendingAdvisories > 0) {
    $waterAiRecs[] = ['icon' => 'fa-lightbulb', 'color' => '#f39c12', 'priority' => 'High',
        'title' => 'Advisories Pending',
        'text' => "{$pendingAdvisories} water efficiency recommendation(s) require action. Resolving leaks/maintenance will save costs."];
}
if (!empty($facilityRows)) {
    $topConsumer = $facilityRows[0];
    if ($filteredTotal > 0 && ($topConsumer['m3'] / $filteredTotal) > 0.4) {
        $waterAiRecs[] = ['icon' => 'fa-tint-slash', 'color' => '#e74c3c', 'priority' => 'Medium',
            'title' => 'High Concentrated Consumption',
            'text' => "{$topConsumer['label']} accounts for a major portion of total water use. Investigate for underground leaks."];
    }
}
if ($waterScore >= 90) {
    $waterAiRecs[] = ['icon' => 'fa-seedling', 'color' => '#27ae60', 'priority' => 'Info',
        'title' => 'Optimal Efficiency Rating',
        'text' => "Water distribution score is {$waterScore}%. System loss parameters are nominal."];
} elseif ($waterScore < 60) {
    $waterAiRecs[] = ['icon' => 'fa-triangle-exclamation', 'color' => '#e74c3c', 'priority' => 'High',
        'title' => 'Low Efficiency Rating',
        'text' => "Efficiency score dropped to {$waterScore}%. Multiple pending repairs need immediate attention."];
}
if ($successfulSyncs > 0) {
    $waterAiRecs[] = ['icon' => 'fa-sync-alt', 'color' => '#3762c8', 'priority' => 'Info',
        'title' => 'Data Sync Complete',
        'text' => "{$successfulSyncs} successful data exports to external Water Utility integration."];
}
if (empty($waterAiRecs)) {
    $waterAiRecs[] = ['icon' => 'fa-check-circle', 'color' => '#27ae60', 'priority' => 'Info',
        'title' => 'All Clear', 'text' => 'Water consumption metrics are nominal. No outstanding system advisories.'];
}

// ============================================================
// ADVISORIES (recent 6)
// ============================================================
$recentRecs = $pdo->query("
    SELECT * FROM water_recommendations
    ORDER BY date_received DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// RECORDS TABLE (embedded, paginated)
// ============================================================
$recLimit  = 10;
$recPage   = max(1, intval($_GET['rec_page'] ?? 1));
$recOffset = ($recPage - 1) * $recLimit;
$recSearch = trim($_GET['rec_search'] ?? '');

$recConds  = array_values($filterConds);
$recParams = array_values($filterParams);

if ($recSearch !== '') {
    $recConds[]  = "(location LIKE ? OR COALESCE(facility_name,'') LIKE ? OR record_id LIKE ?)";
    $sw = '%' . $recSearch . '%';
    $recParams[] = $sw;
    $recParams[] = $sw;
    $recParams[] = $sw;
}
$recWhere = 'WHERE ' . implode(' AND ', $recConds);

$recCountStmt = $pdo->prepare("SELECT COUNT(*) FROM water_consumption_records $recWhere");
$recCountStmt->execute($recParams);
$recTotal      = (int)$recCountStmt->fetchColumn();
$recTotalPages = (int)ceil($recTotal / $recLimit);

$recStmt = $pdo->prepare("
    SELECT r.*, a.name as asset_name, a.asset_id as asset_code
    FROM water_consumption_records r
    LEFT JOIN utility_assets a ON r.utility_asset_id = a.id
    $recWhere
    ORDER BY r.month_year DESC, r.date_recorded DESC
    LIMIT $recLimit OFFSET $recOffset
");
$recStmt->execute($recParams);
$recordsList = $recStmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// AVAILABLE YEARS FOR FILTER DROPDOWN
// ============================================================
$yearsAvail = $pdo->query("
    SELECT DISTINCT YEAR(STR_TO_DATE(CONCAT(month_year,'-01'),'%Y-%m-%d')) as yr
    FROM water_consumption_records
    ORDER BY yr DESC
")->fetchAll(PDO::FETCH_COLUMN);
if (empty($yearsAvail)) {
    $yearsAvail = [intval(date('Y'))];
}

// ============================================================
// AVAILABLE LOCATIONS FOR FILTER DROPDOWN
// ============================================================
$locationsAvail = $pdo->query("
    SELECT DISTINCT location FROM water_consumption_records ORDER BY location ASC
")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Water Management Dashboard | LGU Utilities</title>
    <meta name="description" content="Monitor water consumption across all LGU facilities and utility assets.">
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        *::before, *::after { box-sizing: border-box; }

        body {
            min-height: 100vh;
            display: flex;
            background: url("assets/images/cityhall.jpeg") center/cover no-repeat fixed;
            position: relative;
        }
        body::before {
            content: "";
            position: fixed; inset: 0;
            backdrop-filter: blur(6px);
            background: rgba(0, 0, 0, 0.35);
            z-index: 0;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px 40px 60px;
            transition: margin-left 0.25s ease;
            z-index: 1;
            position: relative;
        }
        .main-content.collapsed { margin-left: 90px; }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 20px; } }

        .card {
            width: 100%;
            max-width: 1700px;
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(18px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.18);
            border: 1px solid rgba(255,255,255,0.3);
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 32px;
            flex-wrap: wrap;
            gap: 20px;
        }
        .dashboard-header h1 {
            color: #1e293b;
            font-size: 30px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .dashboard-header h1 i { color: #0284c7; }
        .dashboard-header p { color: #64748b; font-size: 13px; margin-top: 6px; }
        .header-actions { display: flex; gap: 10px; flex-wrap: wrap; }

        .btn {
            padding: 10px 18px;
            border-radius: 9px;
            font-weight: 600;
            font-size: 13px;
            border: none;
            cursor: pointer;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-primary   { background: #0284c7; color: #fff; }
        .btn-primary:hover { background: #0369a1; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(2,132,199,0.35); }
        .btn-outline   { background: transparent; border: 1.5px solid #cbd5e1; color: #475569; }
        .btn-outline:hover { background: #f8fafc; color: #1e293b; }
        .btn-amber     { background: #f59e0b; color: #fff; }
        .btn-amber:hover { background: #d97706; }
        .btn-sm        { padding: 7px 13px; font-size: 12px; }

        .section-divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 36px 0 22px;
        }
        .section-divider h2 { font-size: 16px; font-weight: 600; color: #475569; letter-spacing: 0.5px; text-transform: uppercase; white-space: nowrap; }
        .section-divider .line { height: 1.5px; background: #e2e8f0; width: 100%; }

        /* ── Filter Bar ── */
        .filter-bar {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 18px 24px;
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)) auto;
            gap: 20px;
            align-items: end;
        }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-group label { font-size: 12px; font-weight: 600; color: #64748b; }
        .filter-control {
            padding: 9px 12px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 13px;
            background: #fff;
            color: #1e293b;
            outline: none;
            transition: border-color 0.2s;
        }
        .filter-control:focus { border-color: #0284c7; }
        .filter-actions { display: flex; gap: 8px; }

        /* ── Grid Layouts ── */
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-bottom: 24px; }
        .grid-2-1 { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 24px; }
        .grid-1-2 { display: grid; grid-template-columns: 1fr 2fr; gap: 24px; margin-bottom: 24px; }
        @media (max-width: 1200px) { .grid-3, .grid-2-1, .grid-1-2 { grid-template-columns: 1fr; } }

        /* ── KPI Cards ── */
        .kpi-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            transition: transform 0.25s, box-shadow 0.25s;
        }
        .kpi-card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .kpi-info h3 { font-size: 13px; color: #64748b; font-weight: 500; }
        .kpi-info .value { font-size: 26px; font-weight: 700; color: #1e293b; margin: 6px 0 2px; }
        .kpi-info .subtext { font-size: 11px; color: #94a3b8; }
        .kpi-icon { width: 52px; height: 52px; border-radius: 12px; display: grid; place-items: center; font-size: 22px; }
        .kpi-icon.blue { background: #e0f2fe; color: #0284c7; }
        .kpi-icon.green { background: #dcfce7; color: #15803d; }
        .kpi-icon.orange { background: #ffedd5; color: #ea580c; }
        .kpi-icon.purple { background: #f3e8ff; color: #7c3aed; }

        /* ── Panel Cards ── */
        .panel {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            min-height: 380px;
        }
        .panel-title {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .panel-title i { color: #64748b; }
        .panel-body { flex: 1; position: relative; }

        /* ── AI Insights ── */
        .ai-panel { background: linear-gradient(135deg, #f8fafc, #eff6ff); border: 1px solid #dbeafe; }
        .ai-digest-content { font-size: 13.5px; line-height: 1.6; color: #334155; }
        .ai-digest-content strong { color: #0f172a; }

        .ai-rec-list { display: flex; flex-direction: column; gap: 12px; }
        .ai-rec-item {
            background: #fff;
            border-left: 4px solid #0284c7;
            border-radius: 0 8px 8px 0;
            padding: 14px 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            display: flex;
            gap: 14px;
            align-items: flex-start;
        }
        .ai-rec-item.priority-High { border-left-color: #ef4444; }
        .ai-rec-item.priority-Medium { border-left-color: #f59e0b; }
        .ai-rec-item.priority-Info { border-left-color: #3b82f6; }

        .ai-rec-icon { font-size: 18px; margin-top: 2px; }
        .ai-rec-text h4 { font-size: 13px; font-weight: 600; color: #1e293b; margin-bottom: 4px; }
        .ai-rec-text p { font-size: 12px; color: #475569; line-height: 1.4; }

        .metric-ring-container { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; gap: 15px; }
        .efficiency-score-label { font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; }

        /* SVG Circle progress */
        .score-svg { transform: rotate(-90deg); }
        .score-bg { fill: none; stroke: #f1f5f9; stroke-width: 10; }
        .score-fill {
            fill: none;
            stroke: #0284c7;
            stroke-width: 10;
            stroke-linecap: round;
            stroke-dasharray: 283;
            stroke-dashoffset: <?php echo (283 - (283 * $waterScore) / 100); ?>;
            transition: stroke-dashoffset 1s ease-out;
        }

        /* ── Recommendations Grid ── */
        .recs-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; }
        .rec-card {
            border: 1px solid #e2e8f0;
            background: #fff;
            border-radius: 12px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: border-color 0.2s;
        }
        .rec-card:hover { border-color: #cbd5e1; }
        .rec-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; }
        .rec-header h4 { font-size: 13.5px; font-weight: 600; color: #1e293b; }
        .priority-badge {
            font-size: 10px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .priority-badge.High      { background: #fee2e2; color: #ef4444; }
        .priority-badge.Medium    { background: #fef3c7; color: #d97706; }
        .priority-badge.Low       { background: #f0fdf4; color: #15803d; }
        .priority-badge.Emergency { background: #fef2f2; color: #dc2626; border: 1px solid #fca5a5; }

        .rec-body { font-size: 12px; color: #475569; line-height: 1.5; margin-bottom: 12px; flex: 1; }
        .rec-footer { display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 10px; }
        .rec-target { font-size: 11px; color: #94a3b8; font-weight: 500; display: flex; align-items: center; gap: 4px; }
        .status-badge {
            font-size: 10px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .status-badge.Pending      { background: #f1f5f9; color: #475569; }
        .status-badge.Acknowledged { background: #e0f2fe; color: #0284c7; }
        .status-badge.Implemented  { background: #dcfce7; color: #15803d; }
        .status-badge.Archived     { background: #f3e8ff; color: #7c3aed; }

        /* ── Records Table Panel ── */
        .table-panel { min-height: unset; margin-bottom: 24px; }
        .table-header-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 12px; }
        .table-search { position: relative; }
        .table-search input {
            padding: 8px 12px 8px 34px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 12.5px;
            outline: none;
            width: 250px;
            transition: all 0.2s;
        }
        .table-search input:focus { border-color: #0284c7; width: 280px; }
        .table-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 13px; }

        .table-wrapper { width: 100%; overflow-x: auto; margin-bottom: 16px; }
        .table { width: 100%; border-collapse: collapse; text-align: left; font-size: 12.5px; }
        .table th { padding: 12px 16px; border-bottom: 2px solid #e2e8f0; color: #475569; font-weight: 600; background: #f8fafc; }
        .table td { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; }
        .table tbody tr:hover td { background: #f8fafc; }

        .source-badge {
            font-size: 10.5px;
            font-weight: 500;
            padding: 2px 6px;
            border-radius: 6px;
            display: inline-block;
        }
        .source-badge.Manual      { background: #eff6ff; color: #2563eb; }
        .source-badge.Imported    { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }
        .source-badge.CPRF        { background: #f0fdf4; color: #16a34a; }

        .pagination-container { display: flex; justify-content: space-between; align-items: center; margin-top: 14px; flex-wrap: wrap; gap: 12px; }
        .pagination-info { font-size: 12px; color: #64748b; }
        .pagination-links { display: flex; gap: 5px; }
        .page-link {
            padding: 6px 11px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            text-decoration: none;
            color: #475569;
            font-size: 12px;
            transition: all 0.2s;
        }
        .page-link:hover { background: #f1f5f9; color: #1e293b; }
        .page-link.active { background: #0284c7; color: #fff; border-color: #0284c7; font-weight: 600; }

        /* ── Dark Theme Overrides ── */
        .dark-theme .card { background: rgba(15,23,42,0.92); border-color: rgba(255,255,255,0.08); }
        .dark-theme .dashboard-header h1 { color: #f8fafc; }
        .dark-theme .dashboard-header p { color: #94a3b8; }
        .dark-theme .btn-outline { border-color: #334155; color: #cbd5e1; }
        .dark-theme .btn-outline:hover { background: #1e293b; color: #f8fafc; }
        .dark-theme .section-divider h2 { color: #94a3b8; }
        .dark-theme .section-divider .line { background: #334155; }
        .dark-theme .filter-bar { background: #1e293b; border-color: #334155; }
        .dark-theme .filter-group label { color: #94a3b8; }
        .dark-theme .filter-control { background: #0f172a; border-color: #334155; color: #cbd5e1; }
        .dark-theme .filter-control:focus { border-color: #0284c7; }
        .dark-theme .kpi-card { background: #1e293b; border-color: #334155; }
        .dark-theme .kpi-info h3 { color: #94a3b8; }
        .dark-theme .kpi-info .value { color: #f8fafc; }
        .dark-theme .panel { background: #1e293b; border-color: #334155; }
        .dark-theme .panel-title { color: #f8fafc; }
        .dark-theme .panel-title i { color: #94a3b8; }
        .dark-theme .ai-panel { background: linear-gradient(135deg, #1e293b, #0f172a); border-color: #1e3a8a; }
        .dark-theme .ai-digest-content { color: #cbd5e1; }
        .dark-theme .ai-digest-content strong { color: #f8fafc; }
        .dark-theme .ai-rec-item { background: #0f172a; }
        .dark-theme .ai-rec-text h4 { color: #f8fafc; }
        .dark-theme .ai-rec-text p { color: #94a3b8; }
        .dark-theme .score-bg { stroke: #0f172a; }
        .dark-theme .rec-card { background: #0f172a; border-color: #1e293b; }
        .dark-theme .rec-card:hover { border-color: #334155; }
        .dark-theme .rec-header h4 { color: #f8fafc; }
        .dark-theme .rec-body { color: #cbd5e1; }
        .dark-theme .rec-footer { border-top-color: #1e293b; }
        .dark-theme .status-badge.Pending { background: #1e293b; color: #94a3b8; }
        .dark-theme .table th { background: #0f172a; border-bottom-color: #334155; color: #94a3b8; }
        .dark-theme .table td { border-bottom-color: #1e293b; color: #cbd5e1; }
        .dark-theme .table tbody tr:hover td { background: #0f172a; }
        .dark-theme .table-search input { background: #0f172a; border-color: #334155; color: #cbd5e1; }
        .dark-theme .table-search input:focus { border-color: #0284c7; }
        .dark-theme .page-link { border-color: #334155; color: #94a3b8; }
        .dark-theme .page-link:hover { background: #1e293b; }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<?php include_once 'includes/utilities_sidebar.php'; ?>

<main class="main-content <?php echo (isset($_COOKIE['sidebar_collapsed']) && $_COOKIE['sidebar_collapsed'] === 'true') ? 'collapsed' : ''; ?>">
    <div class="card">

        <!-- DASHBOARD HEADER -->
        <header class="dashboard-header">
            <div>
                <h1><i class="fas fa-tint"></i> Water Management Dashboard</h1>
                <p>Monitor water distribution parameters, costs, and optimization recommendations.</p>
            </div>
            <div class="header-actions">
                <a href="water_records.php" class="btn btn-primary"><i class="fas fa-list"></i> Water Readings</a>
                <a href="water_sync.php" class="btn btn-outline"><i class="fas fa-sync"></i> Sync Status</a>
            </div>
        </header>

        <!-- FILTER BAR -->
        <section class="filter-bar">
            <form action="" method="GET" style="display:contents;">
                <div class="filter-group">
                    <label for="year">Year</label>
                    <select name="year" id="year" class="filter-control">
                        <?php foreach ($yearsAvail as $yr): ?>
                            <option value="<?php echo $yr; ?>" <?php echo ($yr === $fYear) ? 'selected' : ''; ?>><?php echo $yr; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="month">Month</label>
                    <select name="month" id="month" class="filter-control">
                        <option value="0" <?php echo ($fMonth === 0) ? 'selected' : ''; ?>>All Months</option>
                        <?php 
                        $months = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
                        for ($m=1; $m<=12; $m++):
                        ?>
                            <option value="<?php echo $m; ?>" <?php echo ($m === $fMonth) ? 'selected' : ''; ?>><?php echo $months[$m]; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="asset_type">Asset Type</label>
                    <select name="asset_type" id="asset_type" class="filter-control">
                        <option value="" <?php echo ($fType === '') ? 'selected' : ''; ?>>All Types</option>
                        <option value="Water Pipeline" <?php echo ($fType === 'Water Pipeline') ? 'selected' : ''; ?>>Water Pipeline</option>
                        <option value="Public Utility Infrastructure" <?php echo ($fType === 'Public Utility Infrastructure') ? 'selected' : ''; ?>>Public Utility Infrastructure</option>
                        <option value="Water Infrastructure" <?php echo ($fType === 'Water Infrastructure') ? 'selected' : ''; ?>>Water Infrastructure</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="location">Location</label>
                    <select name="location" id="location" class="filter-control">
                        <option value="" <?php echo ($fLocation === '') ? 'selected' : ''; ?>>All Locations</option>
                        <?php foreach ($locationsAvail as $loc): ?>
                            <option value="<?php echo htmlspecialchars($loc); ?>" <?php echo ($loc === $fLocation) ? 'selected' : ''; ?>><?php echo htmlspecialchars($loc); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
                    <a href="water_dashboard.php" class="btn btn-outline btn-sm"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>
        </section>

        <!-- KPI SUMMARY CARDS -->
        <section class="grid-3" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 30px;">
            <div class="kpi-card">
                <div class="kpi-info">
                    <h3>Total Water Usage</h3>
                    <div class="value"><?php echo number_format($totalConsumption, 2); ?> m³</div>
                    <div class="subtext">All-time consumption</div>
                </div>
                <div class="kpi-icon blue"><i class="fas fa-tint"></i></div>
            </div>

            <div class="kpi-card">
                <div class="kpi-info">
                    <h3>Estimated Costs</h3>
                    <div class="value">₱<?php echo number_format($totalCost, 2); ?></div>
                    <div class="subtext">Estimated monetary cost</div>
                </div>
                <div class="kpi-icon green"><i class="fas fa-wallet"></i></div>
            </div>

            <div class="kpi-card">
                <div class="kpi-info">
                    <h3>System Loss Risks</h3>
                    <div class="value"><?php echo $pendingAdvisories; ?></div>
                    <div class="subtext">Pending recommendations</div>
                </div>
                <div class="kpi-icon orange"><i class="fas fa-triangle-exclamation"></i></div>
            </div>

            <div class="kpi-card">
                <div class="kpi-info">
                    <h3>External Integration</h3>
                    <div class="value"><?php echo $successfulSyncs; ?></div>
                    <div class="subtext">Last Sync: <?php echo $lastSyncDate; ?></div>
                </div>
                <div class="kpi-icon purple"><i class="fas fa-sync-alt"></i></div>
            </div>
        </section>

        <!-- CHARTS SECTION -->
        <section class="grid-2-1">
            <div class="panel">
                <div class="panel-title"><i class="fas fa-chart-line"></i> Monthly Water Consumption Trend (<?php echo $fYear; ?>)</div>
                <div class="panel-body">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>

            <div class="panel">
                <div class="panel-title"><i class="fas fa-gauge"></i> Distribution Efficiency Score</div>
                <div class="panel-body">
                    <div class="metric-ring-container">
                        <svg class="score-svg" width="130" height="130">
                            <circle class="score-bg" cx="65" cy="65" r="45"></circle>
                            <circle class="score-fill" cx="65" cy="65" r="45"></circle>
                        </svg>
                        <div style="position: absolute; font-size: 26px; font-weight: 700; color: #1e293b; top: 120px; transform: translateY(-50%);" class="kpi-info">
                            <span style="color: inherit; font-size: inherit; font-weight: inherit;" id="score-text"><?php echo $waterScore; ?>%</span>
                        </div>
                        <div class="efficiency-score-label">Efficiency Index</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ANALYTICS PANEL & CATEGORIES -->
        <section class="grid-1-2">
            <div class="panel ai-panel">
                <div class="panel-title" style="color:#0284c7;"><i class="fas fa-brain"></i> Water intelligence Analysis</div>
                <div class="panel-body">
                    <div class="ai-digest-content">
                        <?php echo $aiDigest; ?>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-title"><i class="fas fa-building-columns"></i> Top Facility Water Consumption Rankings</div>
                <div class="panel-body">
                    <canvas id="facilityChart"></canvas>
                </div>
            </div>
        </section>

        <!-- RECOMMENDATIONS PANEL -->
        <section class="panel" style="min-height:unset; margin-bottom:24px;">
            <div class="panel-title"><i class="fas fa-lightbulb"></i> Efficiency recommendations & System Loss Interventions</div>
            <div class="panel-body">
                <?php if (empty($recentRecs)): ?>
                    <p style="font-size:13px; color:#64748b;">No recommendations currently loaded.</p>
                <?php else: ?>
                    <div class="recs-grid">
                        <?php foreach ($recentRecs as $rec): ?>
                            <div class="rec-card">
                                <div class="rec-header">
                                    <h4><?php echo htmlspecialchars($rec['recommendation_title']); ?></h4>
                                    <span class="priority-badge <?php echo $rec['priority_level']; ?>"><?php echo $rec['priority_level']; ?></span>
                                </div>
                                <div class="rec-body">
                                    <p><?php echo htmlspecialchars($rec['description']); ?></p>
                                </div>
                                <div class="rec-footer">
                                    <span class="rec-target"><i class="fas fa-location-dot"></i> <?php echo htmlspecialchars($rec['target_facility_asset']); ?></span>
                                    <span class="status-badge <?php echo $rec['status']; ?>"><i class="fas fa-circle" style="font-size:6px; margin-right:4px;"></i> <?php echo $rec['status']; ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- CONSUMPTION RECORDS TABLE -->
        <section class="panel table-panel">
            <div class="table-header-row">
                <div class="panel-title" style="margin-bottom:0;"><i class="fas fa-list"></i> Water Readings (Filtered)</div>
                <div class="table-search">
                    <form action="" method="GET">
                        <input type="hidden" name="year" value="<?php echo $fYear; ?>">
                        <input type="hidden" name="month" value="<?php echo $fMonth; ?>">
                        <input type="hidden" name="asset_type" value="<?php echo $fType; ?>">
                        <input type="hidden" name="location" value="<?php echo htmlspecialchars($fLocation); ?>">
                        <i class="fas fa-search"></i>
                        <input type="text" name="rec_search" placeholder="Search record ID or location..." value="<?php echo htmlspecialchars($recSearch); ?>">
                    </form>
                </div>
            </div>

            <div class="panel-body">
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Record ID</th>
                                <th>Asset / Facility</th>
                                <th>Location</th>
                                <th>Month-Year</th>
                                <th>Prev Reading</th>
                                <th>Curr Reading</th>
                                <th>Consumption (m³)</th>
                                <th>Rate (₱)</th>
                                <th>Cost (₱)</th>
                                <th>Source</th>
                                <th>Date Recorded</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recordsList)): ?>
                                <tr>
                                    <td colspan="11" style="text-align:center; color:#64748b; padding:30px;">No water readings found matching filters.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recordsList as $row): ?>
                                    <?php 
                                    $displayName = $row['facility_name'] ?: ($row['asset_name'] ?: 'N/A');
                                    $sourceClass = 'Manual';
                                    if ($row['data_source'] === 'Imported') $sourceClass = 'Imported';
                                    if ($row['data_source'] === 'CPRF Integration') $sourceClass = 'CPRF';
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($row['record_id']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($displayName); ?></td>
                                        <td><?php echo htmlspecialchars($row['location']); ?></td>
                                        <td><span class="code"><?php echo htmlspecialchars($row['month_year']); ?></span></td>
                                        <td><?php echo number_format((float)$row['previous_reading'], 2); ?></td>
                                        <td><?php echo number_format((float)$row['current_reading'], 2); ?></td>
                                        <td><strong><?php echo number_format((float)$row['consumption_m3'], 2); ?></strong></td>
                                        <td>₱<?php echo number_format((float)$row['rate_per_m3'], 2); ?></td>
                                        <td><strong>₱<?php echo number_format((float)$row['cost'], 2); ?></strong></td>
                                        <td><span class="source-badge <?php echo $sourceClass; ?>"><?php echo htmlspecialchars($row['data_source']); ?></span></td>
                                        <td><span class="muted"><?php echo date('M d, Y', strtotime($row['date_recorded'])); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- PAGINATION -->
                <?php if ($recTotalPages > 1): ?>
                <div class="pagination-container">
                    <div class="pagination-info">
                        Showing <?php echo ($recOffset + 1); ?> – <?php echo min($recTotal, $recOffset + $recLimit); ?> of <?php echo $recTotal; ?> records
                    </div>
                    <div class="pagination-links">
                        <?php
                        $baseQ = http_build_query([
                            'year'       => $fYear,
                            'month'      => $fMonth,
                            'asset_type' => $fType,
                            'location'   => $fLocation,
                            'rec_search' => $recSearch,
                        ]);
                        for ($pg = 1; $pg <= $recTotalPages; $pg++):
                        ?>
                        <a href="water_dashboard.php?<?php echo $baseQ; ?>&rec_page=<?php echo $pg; ?>"
                           class="page-link <?php echo ($pg === $recPage) ? 'active' : ''; ?>">
                            <?php echo $pg; ?>
                        </a>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </section>

    </div>
</main>

<!-- CHART.JS SCRIPTS -->
<script>
/* ─── Monthly Trend Chart ─── */
(function() {
    const canvas = document.getElementById('trendChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');

    const grad = ctx.createLinearGradient(0, 0, 0, 300);
    grad.addColorStop(0, 'rgba(2,132,199,0.28)');
    grad.addColorStop(1, 'rgba(2,132,199,0.01)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo $trendLabelsJson; ?>,
            datasets: [{
                label: 'Total Water (m³)',
                data: <?php echo $trendDataJson; ?>,
                borderColor: '#0284c7',
                backgroundColor: grad,
                borderWidth: 2.5,
                pointBackgroundColor: '#0284c7',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7,
                fill: true,
                tension: 0.4,
                spanGaps: false,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1e293b',
                    titleColor: '#f8fafc',
                    bodyColor: '#94a3b8',
                    padding: 12,
                    callbacks: {
                        title: (items) => items[0].label + ' <?php echo $fYear; ?>',
                        label: (item) => ' ' + Number(item.raw).toLocaleString('en-PH', {minimumFractionDigits:2}) + ' m³'
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: {
                        font: { family: 'Poppins', size: 11 },
                        color: '#64748b',
                        callback: (v) => Number(v).toLocaleString() + ' m³'
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { family: 'Poppins', size: 11 }, color: '#64748b' }
                }
            }
        }
    });
})();

/* ─── Facility Horizontal Bar Chart ─── */
(function() {
    const canvas = document.getElementById('facilityChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');

    const labels = <?php echo $facLabelsJson; ?>;
    const data   = <?php echo $facDataJson; ?>;

    const colours = labels.map((_, i) => {
        const alpha = 1 - (i * 0.08);
        return `rgba(2,132,199,${Math.max(0.35, alpha)})`;
    });

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Water Consumption (m³)',
                data: data,
                backgroundColor: colours,
                borderRadius: 6,
                borderSkipped: false,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1e293b',
                    titleColor: '#f8fafc',
                    bodyColor: '#94a3b8',
                    padding: 12,
                    callbacks: {
                        label: (item) => ' ' + Number(item.raw).toLocaleString('en-PH', {minimumFractionDigits:2}) + ' m³'
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: {
                        font: { family: 'Poppins', size: 11 },
                        color: '#64748b',
                        callback: (v) => Number(v).toLocaleString() + ' m³'
                    }
                },
                y: {
                    grid: { display: false },
                    ticks: {
                        font: { family: 'Poppins', size: 11 },
                        color: '#334155',
                        callback: function(val) {
                            const lbl = this.getLabelForValue(val);
                            return lbl.length > 30 ? lbl.substring(0, 28) + '…' : lbl;
                        }
                    }
                }
            }
        }
    });
})();
</script>

</body>
</html>
