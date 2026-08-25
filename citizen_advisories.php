<?php
// citizen_advisories.php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$search = trim($_GET['search'] ?? '');
$area_filter = trim($_GET['area'] ?? '');

$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(title LIKE ? OR content LIKE ?)";
    $searchWildcard = '%' . $search . '%';
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
}

if (!empty($area_filter)) {
    $conditions[] = "area_affected LIKE ?";
    $params[] = '%' . $area_filter . '%';
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Retrieve advisories
$advisories = [];
try {
    $query = "SELECT * FROM utility_advisories $whereClause ORDER BY published_date DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $advisories = $stmt->fetchAll();
} catch (Exception $e) {
    // Fallback
}

// Generate Resident AI advisory summary
function generateResidentAISummary($advisories) {
    if (empty($advisories)) {
        return "No active utility interruptions or maintenance alerts reported for your area.";
    }

    $summary = "<strong>Resident AI Advisory Assistant:</strong><br>";
    $outagesCount = 0;
    $maintenanceCount = 0;
    
    foreach ($advisories as $a) {
        if (stripos($a['title'], 'interruption') !== false || stripos($a['title'], 'outage') !== false) {
            $outagesCount++;
        } else {
            $maintenanceCount++;
        }
    }
    
    $summary .= "Currently tracking {$outagesCount} active outages/service interruptions and {$maintenanceCount} LGU maintenance actions. ";
    
    // Highlight emergency items
    $emergencies = [];
    foreach ($advisories as $a) {
        if (isset($a['severity']) && $a['severity'] === 'Emergency') {
            $emergencies[] = $a['title'];
        }
    }
    
    if (!empty($emergencies)) {
        $summary .= "<span style='color:#e74c3c; font-weight:700;'>⚠️ Critical Alert:</span> " . implode(', ', $emergencies) . ". Residents in affected zones are advised to prepare alternative reserves.";
    } else {
        $summary .= "Grid load remains stable. No critical emergency alerts active.";
    }
    
    return $summary;
}

$aiSummary = generateResidentAISummary($advisories);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LGU Utility Advisories</title>
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            margin-left: 90px;
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

        /* Filter Panel */
        .filter-panel {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex: 1;
            min-width: 160px;
        }

        .form-group label { font-size: 12px; font-weight: 600; color: #64748b; }
        .form-control { padding: 10px 14px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 14px; outline: none; }

        .btn {
            padding: 11px 22px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary { background: #3762c8; color: white; }
        .btn-primary:hover { background: #2851b0; }

        .btn-outline { background: transparent; border: 1px solid #cbd5e1; color: #64748b; }
        .btn-outline:hover { background: #f8f9fa; color: #2c3e50; }

        /* Advisories Cards */
        .advisory-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
            gap: 25px;
        }

        .advisory-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .advisory-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .advisory-title { font-size: 16px; font-weight: 700; color: #2c3e50; }
        .advisory-date { font-size: 11px; color: #94a3b8; }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 99px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-low { background: #e2fbe8; color: #1e7e34; }
        .badge-medium { background: #e0f2fe; color: #0284c7; }
        .badge-high { background: #fff4e5; color: #b45309; }
        .badge-emergency { background: #fde8e8; color: #bd2130; }

        /* AI Box */
        .ai-box {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .ai-box h3 { font-size: 16px; color: white; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid rgba(255,255,255,0.15); padding-bottom: 10px; margin-bottom: 15px; }
        .ai-content { font-size: 13px; line-height: 1.6; background: rgba(0, 0, 0, 0.2); padding: 15px; border-radius: 8px; }
    </style>
</head>
<body>

<?php include 'includes/utilities_sidebar.php'; ?>

<main class="main-content" id="mainContent">
    <div class="card">
        
        <!-- Header -->
        <div class="dashboard-header">
            <div>
                <h1><i class="fas fa-bullhorn"></i> Utility Advisories & Announcements</h1>
                <p style="color: #64748b; font-size: 14px; margin-top: 5px;">Track scheduled maintenance, water interruptions, and emergency LGU bulletins.</p>
            </div>
            <div>
                <a href="citizen.php" class="btn btn-outline"><i class="fas fa-chevron-left"></i> Home</a>
            </div>
        </div>

        <!-- AI Assistant Box -->
        <div class="ai-box">
            <h3><i class="fas fa-robot"></i> LGU AI Assistant</h3>
            <div class="ai-content">
                <?php echo $aiSummary; ?>
            </div>
        </div>

        <!-- Filters Form -->
        <form method="GET" class="filter-panel">
            <div class="form-group" style="flex:2;">
                <label>Keyword Search</label>
                <input type="text" name="search" class="form-control" placeholder="Search headline, text, or warnings..." value="<?php echo htmlspecialchars($search); ?>">
            </div>

            <div class="form-group">
                <label>Filter by Area / Zone</label>
                <input type="text" name="area" class="form-control" placeholder="e.g. Quiapo, Zone 3..." value="<?php echo htmlspecialchars($area_filter); ?>">
            </div>

            <div style="display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply Filters</button>
                <a href="citizen_advisories.php" class="btn btn-outline">Clear</a>
            </div>
        </form>

        <!-- Advisories Cards Grid -->
        <div class="advisory-grid">
            <?php if (empty($advisories)): ?>
                <div style="grid-column:1/-1; text-align:center; color:#64748b; padding:40px;">No public announcements published.</div>
            <?php else: ?>
                <?php foreach ($advisories as $adv): 
                    $severity = $adv['severity'] ?? 'Medium';
                    $badgeClass = strtolower($severity);
                ?>
                    <div class="advisory-card">
                        <div>
                            <div class="advisory-header">
                                <div>
                                    <div class="advisory-title"><?php echo htmlspecialchars($adv['title']); ?></div>
                                    <div class="advisory-date"><?php echo date('M d, Y', strtotime($adv['published_date'])); ?></div>
                                </div>
                                <span class="badge badge-<?php echo $badgeClass; ?>">
                                    <?php echo htmlspecialchars($severity); ?>
                                </span>
                            </div>

                            <p style="font-size:12px; color:#64748b; margin-bottom:15px; line-height:1.6;"><?php echo htmlspecialchars($adv['content']); ?></p>
                        </div>

                        <div style="border-top:1px solid #edf2f7; padding-top:15px; display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-size:12px; color:#3762c8; font-weight:600;"><i class="fas fa-map-marker-alt"></i> Area: <?php echo htmlspecialchars($adv['area_affected']); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</main>

</body>
</html>
