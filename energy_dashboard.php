<?php
// energy_dashboard.php — Redesigned Energy Management Dashboard
require_once 'includes/auth.php';
require_once 'includes/db.php';

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
// SUMMARY CARD QUERIES (always all-time totals for header cards)
// ============================================================
$totalConsumption       = $pdo->query("SELECT COALESCE(SUM(consumption_kwh),0) FROM energy_consumption_records")->fetchColumn();
$totalCost              = $pdo->query("SELECT COALESCE(SUM(cost),0) FROM energy_consumption_records")->fetchColumn();
$pendingAdvisories      = $pdo->query("SELECT COUNT(*) FROM energy_recommendations WHERE status = 'Pending'")->fetchColumn();
$successfulSyncs        = $pdo->query("SELECT COUNT(*) FROM energy_sync_logs WHERE status = 'Successful'")->fetchColumn();
$lastSyncRow            = $pdo->query("SELECT transferred_at FROM energy_sync_logs ORDER BY transferred_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$lastSyncDate           = $lastSyncRow ? date('M d, Y', strtotime($lastSyncRow['transferred_at'])) : 'Never';

// Distinct facilities/assets count (all time)
$facilityCount = $pdo->query("SELECT COUNT(DISTINCT COALESCE(facility_name, CONCAT(asset_type,'-',location))) FROM energy_consumption_records")->fetchColumn();

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
// FILTERED TOTAL (for AI digest and % calculations)
// ============================================================
$stmtFTotal = $pdo->prepare("SELECT COALESCE(SUM(consumption_kwh),0) FROM energy_consumption_records $filterWhere");
$stmtFTotal->execute($filterParams);
$filteredTotal = (float)$stmtFTotal->fetchColumn();

$stmtFCost = $pdo->prepare("SELECT COALESCE(SUM(cost),0) FROM energy_consumption_records $filterWhere");
$stmtFCost->execute($filterParams);
$filteredCost = (float)$stmtFCost->fetchColumn();

// ============================================================
// MONTHLY TREND — single aggregate line for selected year
// ============================================================
// Build separate params for trend (year only, ignore month filter for trend)
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
    SELECT month_year, COALESCE(SUM(consumption_kwh),0) as total_kwh
    FROM energy_consumption_records
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
    $trendMap[$tr['month_year']] = (float)$tr['total_kwh'];
}
for ($m = 1; $m <= 12; $m++) {
    $key = sprintf('%d-%02d', $fYear, $m);
    $trendLabels[] = $monthNames[$m - 1];
    $trendData[]   = $trendMap[$key] ?? null; // null creates gap in chart
}
$latestTrendValue = !empty($trendRows) ? end($trendRows)['total_kwh'] : 0;
$latestTrendMonth = !empty($trendRows) ? $monthNames[intval(substr(end($trendRows)['month_year'], 5, 2)) - 1] . ' ' . $fYear : '—';

$trendLabelsJson = json_encode($trendLabels);
$trendDataJson   = json_encode($trendData);

// ============================================================
// FACILITY / ASSET HORIZONTAL BAR CHART + TOP CONSUMERS
// ============================================================
$stmtFac = $pdo->prepare("
    SELECT COALESCE(facility_name, CONCAT(asset_type, ' — ', location)) as label,
           asset_type,
           COALESCE(SUM(consumption_kwh),0) as kwh
    FROM energy_consumption_records
    $filterWhere
    GROUP BY label, asset_type
    ORDER BY kwh DESC
");
$stmtFac->execute($filterParams);
$facilityRows = $stmtFac->fetchAll(PDO::FETCH_ASSOC);

// Top 8 for chart
$chartFacilityRows = array_slice($facilityRows, 0, 8);
$facLabels = [];
$facData   = [];
foreach ($chartFacilityRows as $fr) {
    $facLabels[] = $fr['label'];
    $facData[]   = (float)$fr['kwh'];
}
$facLabelsJson = json_encode($facLabels);
$facDataJson   = json_encode($facData);

// Top 5 consumers for ranking section
$topConsumers = array_slice($facilityRows, 0, 5);

// ============================================================
// AI DIGEST (descriptive only — no recommendations)
// ============================================================
function generateAIDigest(array $facilityRows, float $filteredTotal, float $filteredCost, int $fYear, int $fMonth, string $fType): string {
    $monthNames = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
    $periodLabel = ($fMonth > 0) ? ($monthNames[$fMonth] . ' ' . $fYear) : 'Year ' . $fYear;
    if ($fType) $periodLabel .= " ({$fType})";

    if (empty($facilityRows) || $filteredTotal == 0) {
        return "No electricity consumption records found for the selected period (<strong>{$periodLabel}</strong>). "
             . "Please add consumption records via the Consumption Records page or adjust your filters.";
    }

    $count  = count($facilityRows);
    $top    = $facilityRows[0];
    $bottom = end($facilityRows);
    $topPct = $filteredTotal > 0 ? round(($top['kwh'] / $filteredTotal) * 100, 1) : 0;

    $out  = "📊 <strong>Consumption Summary for {$periodLabel}</strong><br><br>";
    $out .= "Total recorded electricity consumption is <strong>" . number_format($filteredTotal, 2) . " kWh</strong>";
    if ($filteredCost > 0) {
        $out .= " with an estimated cost of <strong>₱" . number_format($filteredCost, 2) . "</strong>";
    }
    $out .= ". A total of <strong>{$count} " . ($count === 1 ? 'facility/asset' : 'facilities/assets') . "</strong> have recorded consumption for this period.<br><br>";

    $out .= "⚡ <strong>Highest Consumer:</strong> ";
    $out .= htmlspecialchars($top['label']) . " with <strong>" . number_format($top['kwh'], 2) . " kWh</strong>";
    $out .= " — accounting for <strong>{$topPct}%</strong> of total consumption.<br>";

    if ($count > 1) {
        $out .= "📉 <strong>Lowest Consumer:</strong> ";
        $out .= htmlspecialchars($bottom['label']) . " with <strong>" . number_format($bottom['kwh'], 2) . " kWh</strong>.<br>";
    }

    if ($count > 2) {
        $out .= "<br>📋 <strong>Consumption Distribution:</strong><br>";
        foreach (array_slice($facilityRows, 0, 5) as $idx => $fr) {
            $pct = $filteredTotal > 0 ? round(($fr['kwh'] / $filteredTotal) * 100, 1) : 0;
            $out .= "• " . htmlspecialchars($fr['label']) . ": <strong>" . number_format($fr['kwh'], 2) . " kWh</strong> ({$pct}%)<br>";
        }
        if ($count > 5) {
            $out .= "• … and " . ($count - 5) . " more " . ($count - 5 == 1 ? 'facility/asset' : 'facilities/assets') . ".<br>";
        }
    }

    return $out;
}
$aiDigest = generateAIDigest($facilityRows, $filteredTotal, $filteredCost, $fYear, $fMonth, $fType);

// ============================================================
// ADVISORIES (recent 6)
// ============================================================
$recentRecs = $pdo->query("
    SELECT * FROM energy_recommendations
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

$recConds  = array_values($filterConds);  // clone filter
$recParams = array_values($filterParams);

if ($recSearch !== '') {
    $recConds[]  = "(location LIKE ? OR COALESCE(facility_name,'') LIKE ? OR record_id LIKE ?)";
    $sw = '%' . $recSearch . '%';
    $recParams[] = $sw;
    $recParams[] = $sw;
    $recParams[] = $sw;
}
$recWhere = 'WHERE ' . implode(' AND ', $recConds);

$recCountStmt = $pdo->prepare("SELECT COUNT(*) FROM energy_consumption_records $recWhere");
$recCountStmt->execute($recParams);
$recTotal      = (int)$recCountStmt->fetchColumn();
$recTotalPages = (int)ceil($recTotal / $recLimit);

$recStmt = $pdo->prepare("
    SELECT r.*, a.name as asset_name, a.asset_id as asset_code
    FROM energy_consumption_records r
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
    FROM energy_consumption_records
    ORDER BY yr DESC
")->fetchAll(PDO::FETCH_COLUMN);
if (empty($yearsAvail)) {
    $yearsAvail = [intval(date('Y'))];
}

// ============================================================
// AVAILABLE LOCATIONS FOR FILTER DROPDOWN
// ============================================================
$locationsAvail = $pdo->query("
    SELECT DISTINCT location FROM energy_consumption_records ORDER BY location ASC
")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Energy Management Dashboard | LGU Utilities</title>
    <meta name="description" content="Monitor electricity consumption across all LGU facilities and utility assets.">
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }

        /* ── Body & Background ── */
        body {
            min-height: 100vh;
            display: flex;
            background: url("assets/images/cityhall.jpeg") center/cover no-repeat fixed;
            position: relative;
        }
        body::before {
            content: "";
            position: absolute; inset: 0;
            backdrop-filter: blur(6px);
            background: rgba(0, 0, 0, 0.35);
            z-index: 0;
        }

        /* ── Main Layout ── */
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

        /* ── Card ── */
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

        /* ── Dashboard Header ── */
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
        .dashboard-header h1 i { color: #f59e0b; }
        .dashboard-header p { color: #64748b; font-size: 13px; margin-top: 6px; }
        .header-actions { display: flex; gap: 10px; flex-wrap: wrap; }

        /* ── Buttons ── */
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
        .btn-primary   { background: #3762c8; color: #fff; }
        .btn-primary:hover { background: #2851b0; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(55,98,200,0.35); }
        .btn-outline   { background: transparent; border: 1.5px solid #cbd5e1; color: #475569; }
        .btn-outline:hover { background: #f8fafc; color: #1e293b; }
        .btn-amber     { background: #f59e0b; color: #fff; }
        .btn-amber:hover { background: #d97706; }
        .btn-sm        { padding: 7px 13px; font-size: 12px; }

        /* ── Section Divider ── */
        .section-divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 36px 0 22px;
        }
        .section-divider h2 {
            font-size: 15px;
            font-weight: 700;
            color: #334155;
            white-space: nowrap;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }
        .section-divider::after {
            content: '';
            flex: 1;
            height: 2px;
            background: linear-gradient(90deg, #e2e8f0, transparent);
            border-radius: 2px;
        }

        /* ── Summary Cards ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 18px;
            margin-bottom: 30px;
        }
        @media (max-width: 1200px) { .stats-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 700px)  { .stats-grid { grid-template-columns: repeat(2, 1fr); } }

        .stat-card {
            background: #fff;
            border-radius: 14px;
            padding: 22px 20px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border-left: 5px solid #cbd5e1;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.1); }
        .stat-card.blue   { border-left-color: #3762c8; }
        .stat-card.green  { border-left-color: #22c55e; }
        .stat-card.amber  { border-left-color: #f59e0b; }
        .stat-card.purple { border-left-color: #a855f7; }
        .stat-card.teal   { border-left-color: #14b8a6; }

        .stat-icon-wrap {
            width: 50px; height: 50px;
            border-radius: 12px;
            display: grid; place-items: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        .stat-card.blue   .stat-icon-wrap { background: #eff4ff; color: #3762c8; }
        .stat-card.green  .stat-icon-wrap { background: #f0fdf4; color: #22c55e; }
        .stat-card.amber  .stat-icon-wrap { background: #fffbeb; color: #f59e0b; }
        .stat-card.purple .stat-icon-wrap { background: #faf5ff; color: #a855f7; }
        .stat-card.teal   .stat-icon-wrap { background: #f0fdfa; color: #14b8a6; }

        .stat-info h3 { font-size: 22px; font-weight: 700; color: #1e293b; line-height: 1.2; }
        .stat-info h3 .unit { font-size: 12px; font-weight: 500; color: #94a3b8; }
        .stat-info p { font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px; }

        /* ── Filter Bar ── */
        .filter-bar {
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            padding: 20px 24px;
            display: flex;
            gap: 14px;
            align-items: flex-end;
            flex-wrap: wrap;
            margin-bottom: 30px;
        }
        .filter-bar .fg { display: flex; flex-direction: column; gap: 5px; min-width: 130px; }
        .filter-bar label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .filter-bar select,
        .filter-bar input[type="text"] {
            padding: 9px 12px;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13px;
            color: #334155;
            background: #fff;
            outline: none;
            transition: border-color 0.2s;
            min-width: 130px;
        }
        .filter-bar select:focus,
        .filter-bar input:focus { border-color: #3762c8; }
        .filter-actions { display: flex; gap: 8px; align-items: flex-end; margin-left: auto; }

        /* ── Two Column Row ── */
        .row-2col {
            display: grid;
            grid-template-columns: 1.3fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }
        @media (max-width: 1050px) { .row-2col { grid-template-columns: 1fr; } }

        /* ── Box ── */
        .box {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 2px 14px rgba(0,0,0,0.06);
            border: 1px solid #f1f5f9;
        }
        .box-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 2px solid #f1f5f9;
        }
        .box-title {
            font-size: 14px;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 9px;
        }
        .box-title i { color: #3762c8; font-size: 16px; }
        .box-subtitle { font-size: 11px; color: #94a3b8; margin-top: 2px; font-weight: 500; }

        /* ── Chart Containers ── */
        .chart-wrap { position: relative; width: 100%; }
        .chart-wrap canvas { width: 100% !important; }

        /* ── Trend Stat Chip ── */
        .trend-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #eff4ff;
            color: #3762c8;
            border-radius: 99px;
            padding: 5px 14px;
            font-size: 12px;
            font-weight: 700;
        }

        /* ── AI Box ── */
        .ai-box {
            background: linear-gradient(145deg, #0f2044, #1a3a6e);
            border: none;
        }
        .ai-box .box-title { color: #e0eaff; }
        .ai-box .box-title i { color: #60a5fa; }
        .ai-box .box-header { border-bottom-color: rgba(255,255,255,0.1); }
        .ai-content {
            background: rgba(0,0,0,0.25);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 18px;
            font-size: 13px;
            line-height: 1.75;
            color: #cbd5e1;
            max-height: 320px;
            overflow-y: auto;
        }
        .ai-content strong { color: #93c5fd; }
        .ai-disclaimer {
            margin-top: 14px;
            font-size: 11px;
            color: rgba(255,255,255,0.35);
            border-top: 1px solid rgba(255,255,255,0.08);
            padding-top: 10px;
        }

        /* ── Top Consumers ── */
        .top-list { display: flex; flex-direction: column; gap: 12px; }
        .top-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
            border-radius: 10px;
            background: #f8fafc;
            border: 1px solid #f1f5f9;
            transition: background 0.2s ease;
        }
        .top-item:hover { background: #eff4ff; border-color: #c7d7f8; }
        .top-rank {
            width: 32px; height: 32px;
            border-radius: 50%;
            display: grid; place-items: center;
            font-size: 14px; font-weight: 800;
            flex-shrink: 0;
        }
        .top-rank.r1 { background: #fef9c3; color: #a16207; }
        .top-rank.r2 { background: #f1f5f9; color: #475569; }
        .top-rank.r3 { background: #fff7ed; color: #c2410c; }
        .top-rank.rn { background: #f0f9ff; color: #0284c7; }
        .top-info { flex: 1; min-width: 0; }
        .top-name { font-size: 13px; font-weight: 600; color: #1e293b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .top-meta { font-size: 11px; color: #64748b; margin-top: 2px; }
        .top-kwh { font-size: 14px; font-weight: 700; color: #3762c8; white-space: nowrap; }
        .top-pct-bar { margin-top: 6px; height: 4px; background: #e2e8f0; border-radius: 99px; overflow: hidden; }
        .top-pct-fill { height: 100%; background: linear-gradient(90deg, #3762c8, #60a5fa); border-radius: 99px; transition: width 1s ease; }

        /* ── Advisories ── */
        .advisory-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 16px;
        }
        .advisory-card {
            border-radius: 12px;
            padding: 18px;
            border: 1.5px solid #e2e8f0;
            background: #fff;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .advisory-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.08); }
        .advisory-card.high   { border-left: 4px solid #ef4444; }
        .advisory-card.medium { border-left: 4px solid #f59e0b; }
        .advisory-card.low    { border-left: 4px solid #22c55e; }
        .adv-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; margin-bottom: 10px; }
        .adv-title { font-size: 13px; font-weight: 700; color: #1e293b; line-height: 1.4; }
        .adv-badges { display: flex; flex-direction: column; gap: 4px; align-items: flex-end; flex-shrink: 0; }
        .adv-desc { font-size: 12px; color: #64748b; line-height: 1.6; margin-bottom: 12px; }
        .adv-meta { display: flex; flex-wrap: wrap; gap: 8px; font-size: 11px; color: #94a3b8; }
        .adv-meta span { display: flex; align-items: center; gap: 4px; }

        /* ── Badges ── */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 9px;
            border-radius: 99px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .badge-pending      { background: #fef3c7; color: #d97706; }
        .badge-acknowledged { background: #dbeafe; color: #2563eb; }
        .badge-in-progress  { background: #f3e8ff; color: #9333ea; }
        .badge-archived     { background: #f1f5f9; color: #475569; }
        .badge-implemented  { background: #dcfce7; color: #16a34a; }
        .badge-high         { background: #fee2e2; color: #dc2626; }
        .badge-medium       { background: #fef3c7; color: #d97706; }
        .badge-low          { background: #dcfce7; color: #16a34a; }
        .badge-manual       { background: #e0f2fe; color: #0284c7; }
        .badge-imported     { background: #dcfce7; color: #16a34a; }

        /* ── Records Table ── */
        .table-section {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 2px 14px rgba(0,0,0,0.06);
            border: 1px solid #f1f5f9;
        }
        .table-search-bar {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }
        .table-search-bar input {
            flex: 1;
            min-width: 200px;
            padding: 9px 14px;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13px;
            outline: none;
        }
        .table-search-bar input:focus { border-color: #3762c8; }
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        thead th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px 16px;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
        }
        tbody td { padding: 13px 16px; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: #334155; }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover td { background: #f8fafc; }
        .td-kwh { font-weight: 700; color: #1e293b; }
        .td-cost { color: #22c55e; font-weight: 600; }
        .td-id { font-family: monospace; font-size: 12px; color: #64748b; }

        /* ── Pagination ── */
        .pagination-wrap { display: flex; justify-content: space-between; align-items: center; margin-top: 18px; flex-wrap: wrap; gap: 12px; }
        .pagination-info { font-size: 12px; color: #64748b; }
        .pagination-links { display: flex; gap: 5px; }
        .page-link {
            padding: 6px 11px;
            border-radius: 7px;
            border: 1.5px solid #e2e8f0;
            text-decoration: none;
            color: #64748b;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .page-link:hover { border-color: #3762c8; color: #3762c8; background: #eff4ff; }
        .page-link.active { background: #3762c8; color: #fff; border-color: #3762c8; }

        /* ── Empty State ── */
        .empty-state {
            text-align: center;
            padding: 50px 30px;
            color: #94a3b8;
        }
        .empty-state i { font-size: 42px; opacity: 0.4; display: block; margin-bottom: 14px; }
        .empty-state p { font-size: 14px; }

        /* ── Pulse animation for AI icon ── */
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.6} }

        /* ── Dark theme overrides (inherited from sidebar) ── */
        .dark-theme .card { background: rgba(15,23,42,0.95) !important; }
        .dark-theme .box,
        .dark-theme .stat-card,
        .dark-theme .table-section,
        .dark-theme .advisory-card,
        .dark-theme .top-item,
        .dark-theme .filter-bar { background: #1e293b !important; border-color: #334155 !important; }
        .dark-theme .box-title,
        .dark-theme .dashboard-header h1,
        .dark-theme .stat-info h3 { color: #f8fafc !important; }
        .dark-theme .box-header { border-bottom-color: #334155 !important; }
        .dark-theme thead th { background: #0f172a !important; color: #94a3b8 !important; border-bottom-color: #334155 !important; }
        .dark-theme tbody td { color: #cbd5e1 !important; border-bottom-color: #334155 !important; }
        .dark-theme tbody tr:hover td { background: rgba(255,255,255,0.04) !important; }
        .dark-theme .filter-bar select,
        .dark-theme .filter-bar input,
        .dark-theme .table-search-bar input { background: #0f172a !important; border-color: #475569 !important; color: #f8fafc !important; }
        .dark-theme .section-divider h2 { color: #94a3b8 !important; }
        .dark-theme .top-name { color: #f8fafc !important; }
        .dark-theme .adv-title { color: #f8fafc !important; }
    </style>
</head>
<body>

<?php include 'includes/utilities_sidebar.php'; ?>

<main class="main-content" id="mainContent">
<div class="card">

    <!-- ═══════════════════════════════════════════════════════
         HEADER
    ═══════════════════════════════════════════════════════ -->
    <div class="dashboard-header">
        <div>
            <h1><i class="fas fa-bolt"></i> Energy Management Dashboard</h1>
            <p>Monitor electricity consumption across LGU facilities and utility assets. All values are from actual recorded data.</p>
        </div>
        <div class="header-actions">
            <a href="energy_records.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Record</a>
            <a href="energy_sync.php"    class="btn btn-outline"><i class="fas fa-sync-alt"></i> Transmission Logs</a>
            <a href="energy_recommendations.php" class="btn btn-outline"><i class="fas fa-lightbulb"></i> Advisories</a>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
         SUMMARY CARDS (all-time totals)
    ═══════════════════════════════════════════════════════ -->
    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="stat-icon-wrap"><i class="fas fa-bolt"></i></div>
            <div class="stat-info">
                <h3><?php echo number_format($totalConsumption, 2); ?> <span class="unit">kWh</span></h3>
                <p>Total Consumption</p>
            </div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon-wrap"><i class="fas fa-peso-sign"></i></div>
            <div class="stat-info">
                <h3>₱<?php echo number_format($totalCost, 2); ?></h3>
                <p>Estimated Cost</p>
            </div>
        </div>
        <div class="stat-card teal">
            <div class="stat-icon-wrap"><i class="fas fa-building"></i></div>
            <div class="stat-info">
                <h3><?php echo number_format($facilityCount); ?></h3>
                <p>Facilities / Assets Monitored</p>
            </div>
        </div>
        <div class="stat-card amber">
            <div class="stat-icon-wrap"><i class="fas fa-bell"></i></div>
            <div class="stat-info">
                <h3><?php echo number_format($pendingAdvisories); ?></h3>
                <p>Pending Advisories</p>
            </div>
        </div>
        <div class="stat-card purple">
            <div class="stat-icon-wrap"><i class="fas fa-exchange-alt"></i></div>
            <div class="stat-info">
                <h3><?php echo number_format($successfulSyncs); ?></h3>
                <p>Data Syncs — Last: <?php echo $lastSyncDate; ?></p>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
         FILTER BAR
    ═══════════════════════════════════════════════════════ -->
    <form method="GET" id="filterForm">
        <!-- preserve rec_search across filter submits -->
        <?php if ($recSearch): ?>
            <input type="hidden" name="rec_search" value="<?php echo htmlspecialchars($recSearch); ?>">
        <?php endif; ?>
        <div class="filter-bar">
            <div class="fg">
                <label>Year</label>
                <select name="year" id="fYear" onchange="this.form.submit()">
                    <?php foreach ($yearsAvail as $yr): ?>
                        <option value="<?php echo $yr; ?>" <?php echo ($yr == $fYear) ? 'selected' : ''; ?>>
                            <?php echo $yr; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="fg">
                <label>Month</label>
                <select name="month" id="fMonth" onchange="this.form.submit()">
                    <option value="0" <?php echo ($fMonth == 0) ? 'selected' : ''; ?>>All Months</option>
                    <?php
                    $mn = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                    foreach ($mn as $mi => $mname):
                    ?>
                    <option value="<?php echo $mi+1; ?>" <?php echo ($fMonth == $mi+1) ? 'selected' : ''; ?>>
                        <?php echo $mname; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="fg">
                <label>Type</label>
                <select name="asset_type" id="fType" onchange="this.form.submit()">
                    <option value="" <?php echo ($fType === '') ? 'selected' : ''; ?>>All Types</option>
                    <option value="Streetlight"         <?php echo ($fType === 'Streetlight')         ? 'selected' : ''; ?>>Streetlight</option>
                    <option value="Public Facility"     <?php echo ($fType === 'Public Facility')     ? 'selected' : ''; ?>>Public Facility</option>
                    <option value="Water Infrastructure"<?php echo ($fType === 'Water Infrastructure') ? 'selected' : ''; ?>>Water Infrastructure</option>
                </select>
            </div>
            <div class="fg">
                <label>Location</label>
                <select name="location" id="fLocation" onchange="this.form.submit()">
                    <option value="" <?php echo ($fLocation === '') ? 'selected' : ''; ?>>All Locations</option>
                    <?php foreach ($locationsAvail as $loc): ?>
                        <option value="<?php echo htmlspecialchars($loc); ?>"
                            <?php echo ($fLocation === $loc) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($loc); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Apply</button>
                <a href="energy_dashboard.php" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Reset</a>
            </div>
        </div>
        <!-- Showing filter context -->
        <?php if ($fMonth > 0 || $fType !== '' || $fLocation !== ''): ?>
        <div style="margin-bottom:16px; font-size:12px; color:#64748b; display:flex; align-items:center; gap:6px;">
            <i class="fas fa-info-circle" style="color:#3762c8;"></i>
            Showing data for:
            <strong style="color:#1e293b;">
                <?php
                $parts = [];
                if ($fMonth > 0) $parts[] = $mn[$fMonth-1] . ' ' . $fYear;
                else $parts[] = 'Year ' . $fYear;
                if ($fType)     $parts[] = $fType;
                if ($fLocation) $parts[] = $fLocation;
                echo htmlspecialchars(implode(' · ', $parts));
                ?>
            </strong>
            &nbsp;·&nbsp; Filtered total: <strong style="color:#3762c8;"><?php echo number_format($filteredTotal, 2); ?> kWh</strong>
        </div>
        <?php endif; ?>
    </form>

    <!-- ═══════════════════════════════════════════════════════
         ROW 1: Monthly Trend + AI Digest
    ═══════════════════════════════════════════════════════ -->
    <div class="section-divider"><h2><i class="fas fa-chart-line" style="color:#3762c8;margin-right:6px;"></i> Consumption Overview</h2></div>
    <div class="row-2col">

        <!-- LEFT: Monthly Trend Chart -->
        <div class="box">
            <div class="box-header">
                <div>
                    <div class="box-title"><i class="fas fa-chart-area"></i> Total Monthly Electricity Consumption</div>
                    <div class="box-subtitle">Aggregate kWh across all monitored facilities &amp; assets — <?php echo $fYear; ?></div>
                </div>
                <?php if ($latestTrendValue > 0): ?>
                <div class="trend-chip">
                    <i class="fas fa-bolt"></i>
                    <?php echo number_format($latestTrendValue, 2); ?> kWh
                    <span style="font-weight:500; color:#6384d2;"><?php echo $latestTrendMonth; ?></span>
                </div>
                <?php endif; ?>
            </div>

            <?php if (array_sum(array_filter($trendData, fn($v) => $v !== null)) == 0): ?>
            <div class="empty-state">
                <i class="fas fa-chart-area"></i>
                <p>No consumption records found for <?php echo $fYear; ?>.</p>
                <a href="energy_records.php" class="btn btn-primary btn-sm" style="margin-top:14px;"><i class="fas fa-plus"></i> Add Record</a>
            </div>
            <?php else: ?>
            <div class="chart-wrap" style="height:290px;">
                <canvas id="trendChart"></canvas>
            </div>
            <?php endif; ?>
        </div>

        <!-- RIGHT: AI Digest -->
        <div class="box ai-box">
            <div class="box-header">
                <div>
                    <div class="box-title"><i class="fas fa-robot" style="animation:pulse 2s infinite;"></i> LGU AI Energy Consumption Digest</div>
                    <div class="box-subtitle" style="color:rgba(255,255,255,0.4);">Based on actual recorded data — descriptive summary only</div>
                </div>
            </div>
            <div class="ai-content">
                <?php echo $aiDigest; ?>
            </div>
            <div class="ai-disclaimer">
                <i class="fas fa-info-circle"></i>
                This AI digest describes recorded consumption data only. Energy efficiency recommendations are received separately from the external Energy Efficiency System.
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
         ROW 2: Facility Bar Chart + Top Consumers
    ═══════════════════════════════════════════════════════ -->
    <div class="section-divider"><h2><i class="fas fa-chart-bar" style="color:#3762c8;margin-right:6px;"></i> Facility &amp; Asset Breakdown</h2></div>
    <div class="row-2col">

        <!-- LEFT: Horizontal Bar Chart -->
        <div class="box">
            <div class="box-header">
                <div>
                    <div class="box-title"><i class="fas fa-bars"></i> Electricity Consumption by Facility / Asset</div>
                    <div class="box-subtitle">Sorted highest to lowest — top 8 shown</div>
                </div>
                <?php if (count($facilityRows) > 8): ?>
                <a href="energy_records.php" class="btn btn-outline btn-sm"><i class="fas fa-list"></i> View All</a>
                <?php endif; ?>
            </div>

            <?php if (empty($facilityRows)): ?>
            <div class="empty-state">
                <i class="fas fa-chart-bar"></i>
                <p>No consumption records found for the selected filters.</p>
            </div>
            <?php else: ?>
            <div class="chart-wrap" style="height:<?php echo max(200, count($chartFacilityRows) * 48 + 40); ?>px;">
                <canvas id="facilityChart"></canvas>
            </div>
            <?php endif; ?>
        </div>

        <!-- RIGHT: Top Consumers -->
        <div class="box">
            <div class="box-header">
                <div>
                    <div class="box-title"><i class="fas fa-trophy"></i> Top Consumers</div>
                    <div class="box-subtitle">Highest electricity consumers for selected period</div>
                </div>
            </div>

            <?php if (empty($topConsumers)): ?>
            <div class="empty-state">
                <i class="fas fa-trophy"></i>
                <p>No data available for the selected filters.</p>
            </div>
            <?php else: ?>
            <div class="top-list">
                <?php foreach ($topConsumers as $idx => $tc):
                    $rank  = $idx + 1;
                    $pct   = $filteredTotal > 0 ? round(($tc['kwh'] / $filteredTotal) * 100, 1) : 0;
                    $rankClass = match($rank) { 1 => 'r1', 2 => 'r2', 3 => 'r3', default => 'rn' };
                ?>
                <div class="top-item">
                    <div class="top-rank <?php echo $rankClass; ?>"><?php echo $rank; ?></div>
                    <div class="top-info">
                        <div class="top-name" title="<?php echo htmlspecialchars($tc['label']); ?>">
                            <?php echo htmlspecialchars($tc['label']); ?>
                        </div>
                        <div class="top-meta"><?php echo htmlspecialchars($tc['asset_type']); ?></div>
                        <div class="top-pct-bar">
                            <div class="top-pct-fill" style="width:<?php echo $pct; ?>%"></div>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <div class="top-kwh"><?php echo number_format($tc['kwh'], 2); ?> <small style="font-size:10px;color:#94a3b8;">kWh</small></div>
                        <div style="font-size:11px; color:#64748b; margin-top:3px;"><?php echo $pct; ?>% of total</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
         ROW 3: Received Energy Efficiency Advisories
    ═══════════════════════════════════════════════════════ -->
    <div class="section-divider"><h2><i class="fas fa-lightbulb" style="color:#f59e0b;margin-right:6px;"></i> Received Energy Efficiency Advisories</h2></div>
    <div class="box" style="margin-bottom:24px;">
        <div class="box-header">
            <div>
                <div class="box-title"><i class="fas fa-bell"></i> Received Energy Efficiency Advisories</div>
                <div class="box-subtitle">Recommendations received from the external Energy Efficiency System — this module only displays and tracks them</div>
            </div>
            <a href="energy_recommendations.php" class="btn btn-outline btn-sm"><i class="fas fa-external-link-alt"></i> Manage All</a>
        </div>

        <?php if (empty($recentRecs)): ?>
        <div class="empty-state">
            <i class="fas fa-check-circle" style="color:#22c55e; opacity:0.6;"></i>
            <p>No energy efficiency advisories have been received yet.</p>
        </div>
        <?php else: ?>
        <div class="advisory-grid">
            <?php foreach ($recentRecs as $rec):
                $priority    = strtolower($rec['priority_level'] ?? 'medium');
                $statusRaw   = $rec['status'] ?? 'Pending';
                $statusClass = strtolower(str_replace(' ', '-', $statusRaw));
            ?>
            <div class="advisory-card <?php echo $priority; ?>">
                <div class="adv-header">
                    <div class="adv-title"><?php echo htmlspecialchars($rec['recommendation_title']); ?></div>
                    <div class="adv-badges">
                        <span class="badge badge-<?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusRaw); ?></span>
                        <span class="badge badge-<?php echo $priority; ?>"><?php echo htmlspecialchars($rec['priority_level'] ?? ''); ?></span>
                    </div>
                </div>
                <div class="adv-desc"><?php echo htmlspecialchars($rec['description'] ?? ''); ?></div>
                <div class="adv-meta">
                    <?php if (!empty($rec['target_facility_asset'])): ?>
                    <span><i class="fas fa-building"></i> <?php echo htmlspecialchars($rec['target_facility_asset']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($rec['date_received'])): ?>
                    <span><i class="fas fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($rec['date_received'])); ?></span>
                    <?php endif; ?>
                    <span><i class="fas fa-info-circle"></i> Source: External Energy Efficiency System</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ═══════════════════════════════════════════════════════
         ROW 4: Detailed Consumption Records Table
    ═══════════════════════════════════════════════════════ -->
    <div class="section-divider"><h2><i class="fas fa-table" style="color:#3762c8;margin-right:6px;"></i> Electricity Consumption Records</h2></div>
    <div class="table-section">
        <div class="box-header" style="border-bottom:2px solid #f1f5f9; margin-bottom:18px; padding-bottom:14px;">
            <div>
                <div class="box-title"><i class="fas fa-list-alt"></i> Electricity Consumption Records</div>
                <div class="box-subtitle">Filtered by selected year/month/type/location — <?php echo $recTotal; ?> record<?php echo $recTotal !== 1 ? 's' : ''; ?> found</div>
            </div>
            <a href="energy_records.php" class="btn btn-primary btn-sm"><i class="fas fa-external-link-alt"></i> Full Records Page</a>
        </div>

        <!-- Table search -->
        <form method="GET" class="table-search-bar">
            <input type="hidden" name="year"       value="<?php echo $fYear; ?>">
            <input type="hidden" name="month"      value="<?php echo $fMonth; ?>">
            <input type="hidden" name="asset_type" value="<?php echo htmlspecialchars($fType); ?>">
            <input type="hidden" name="location"   value="<?php echo htmlspecialchars($fLocation); ?>">
            <input type="text" name="rec_search" placeholder="Search by record ID, facility, or location…"
                   value="<?php echo htmlspecialchars($recSearch); ?>">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Search</button>
            <?php if ($recSearch): ?>
            <a href="energy_dashboard.php?year=<?php echo $fYear; ?>&month=<?php echo $fMonth; ?>&asset_type=<?php echo urlencode($fType); ?>&location=<?php echo urlencode($fLocation); ?>"
               class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Clear</a>
            <?php endif; ?>
        </form>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Record ID</th>
                        <th>Facility / Asset</th>
                        <th>Type</th>
                        <th>Location</th>
                        <th>Period</th>
                        <th>Consumption (kWh)</th>
                        <th>Estimated Cost</th>
                        <th>Date Recorded</th>
                        <th>Source</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recordsList)): ?>
                    <tr>
                        <td colspan="9">
                            <div class="empty-state">
                                <i class="fas fa-search"></i>
                                <p>No consumption records found for the selected filters.</p>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($recordsList as $rec):
                        $facName = '';
                        if (!empty($rec['facility_name'])) {
                            $facName = $rec['facility_name'];
                        } elseif (!empty($rec['asset_name'])) {
                            $facName = $rec['asset_name'] . ' (' . $rec['asset_code'] . ')';
                        } else {
                            $facName = '<em style="color:#94a3b8;">Unlinked Asset</em>';
                        }
                        $srcClass = ($rec['data_source'] === 'Imported') ? 'imported' : 'manual';
                    ?>
                    <tr>
                        <td class="td-id"><?php echo htmlspecialchars($rec['record_id']); ?></td>
                        <td><?php echo $facName; ?></td>
                        <td><?php echo htmlspecialchars($rec['asset_type']); ?></td>
                        <td style="max-width:160px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo htmlspecialchars($rec['location']); ?>">
                            <?php echo htmlspecialchars($rec['location']); ?>
                        </td>
                        <td><?php echo htmlspecialchars($rec['month_year']); ?></td>
                        <td class="td-kwh"><?php echo number_format($rec['consumption_kwh'], 2); ?> kWh</td>
                        <td class="td-cost"><?php echo $rec['cost'] ? '₱' . number_format($rec['cost'], 2) : '<span style="color:#94a3b8;">—</span>'; ?></td>
                        <td><?php echo !empty($rec['date_recorded']) ? date('M d, Y', strtotime($rec['date_recorded'])) : '—'; ?></td>
                        <td><span class="badge badge-<?php echo $srcClass; ?>"><?php echo htmlspecialchars($rec['data_source']); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($recTotalPages > 1): ?>
        <div class="pagination-wrap">
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
                <a href="energy_dashboard.php?<?php echo $baseQ; ?>&rec_page=<?php echo $pg; ?>"
                   class="page-link <?php echo ($pg === $recPage) ? 'active' : ''; ?>">
                    <?php echo $pg; ?>
                </a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /.card -->
</main>

<!-- ═══════════════════════════════════════════════════════
     CHART.JS SCRIPTS
═══════════════════════════════════════════════════════ -->
<script>
/* ─── Monthly Trend Chart ─── */
(function() {
    const canvas = document.getElementById('trendChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');

    // Gradient fill
    const grad = ctx.createLinearGradient(0, 0, 0, 300);
    grad.addColorStop(0, 'rgba(55,98,200,0.28)');
    grad.addColorStop(1, 'rgba(55,98,200,0.01)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo $trendLabelsJson; ?>,
            datasets: [{
                label: 'Total kWh',
                data: <?php echo $trendDataJson; ?>,
                borderColor: '#3762c8',
                backgroundColor: grad,
                borderWidth: 2.5,
                pointBackgroundColor: '#3762c8',
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
                        label: (item) => ' ' + Number(item.raw).toLocaleString('en-PH', {minimumFractionDigits:2}) + ' kWh'
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
                        callback: (v) => Number(v).toLocaleString() + ' kWh'
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

    // Colour gradient per bar based on rank
    const colours = labels.map((_, i) => {
        const alpha = 1 - (i * 0.08);
        return `rgba(55,98,200,${Math.max(0.35, alpha)})`;
    });

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'kWh',
                data: data,
                backgroundColor: colours,
                borderRadius: 6,
                borderSkipped: false,
            }]
        },
        options: {
            indexAxis: 'y',            // ← Horizontal bar
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
                        label: (item) => ' ' + Number(item.raw).toLocaleString('en-PH', {minimumFractionDigits:2}) + ' kWh'
                    }
                },
                // Show kWh value at end of each bar (Chart.js datalabels not loaded — handled via tooltip)
            },
            scales: {
                x: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: {
                        font: { family: 'Poppins', size: 11 },
                        color: '#64748b',
                        callback: (v) => Number(v).toLocaleString() + ' kWh'
                    }
                },
                y: {
                    grid: { display: false },
                    ticks: {
                        font: { family: 'Poppins', size: 11 },
                        color: '#334155',
                        // Truncate long names on y-axis
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
