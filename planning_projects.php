<?php
// planning_projects.php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'review') {
        $id = intval($_POST['id'] ?? 0);
        $readiness = $_POST['readiness_status'] ?? 'Ready';
        $notes = trim($_POST['planning_notes'] ?? '');

        if ($id > 0) {
            try {
                // Get project details
                $stmt = $pdo->prepare("SELECT project_name FROM development_projects WHERE id = ?");
                $stmt->execute([$id]);
                $projName = $stmt->fetchColumn();

                // Update readiness status and notes
                $stmt = $pdo->prepare("
                    UPDATE development_projects 
                    SET readiness_status = ?, planning_notes = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$readiness, $notes, $id]);

                // Log planning coordination log
                $pdo->prepare("
                    INSERT INTO planning_coordination_logs (direction, log_type, details) 
                    VALUES ('Outbound', 'Project Review', ?)
                ")->execute(["Completed utility readiness review for project: {$projName} (Status: {$readiness})."]);

                $success = "Project review successfully updated for '{$projName}'!";
            } catch (PDOException $e) {
                $error = "Failed to update review: " . $e->getMessage();
            }
        }
    }
}

// Retrieve search / filters
$search = trim($_GET['search'] ?? '');
$type_filter = $_GET['development_type'] ?? '';
$readiness_filter = $_GET['readiness_status'] ?? '';

$conditions = [];
$params = [];

if ($search) {
    $conditions[] = "(project_name LIKE ? OR location LIKE ? OR utility_requirements LIKE ?)";
    $searchWildcard = '%' . $search . '%';
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
}

if ($type_filter) {
    $conditions[] = "development_type = ?";
    $params[] = $type_filter;
}

if ($readiness_filter) {
    $conditions[] = "readiness_status = ?";
    $params[] = $readiness_filter;
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

$projects = $pdo->prepare("
    SELECT * FROM development_projects 
    $whereClause 
    ORDER BY created_at DESC
");
$projects->execute($params);
$projectsList = $projects->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Development Project Utility Readiness</title>
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
            position: absolute;
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

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error { background-color: #fde8e8; color: #c0392b; border: 1px solid #f8b4b4; }
        .alert-success { background-color: #e2fbe8; color: #1e7e34; border: 1px solid #b8f0c5; }

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

        /* Project Cards Grid */
        .project-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
            gap: 25px;
        }

        .project-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.2s;
        }

        .project-card:hover {
            transform: translateY(-2px);
            border-color: #3762c8;
        }

        .project-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .project-title { font-size: 16px; font-weight: 700; color: #2c3e50; }
        .project-location { font-size: 12px; color: #64748b; margin-top: 2px; }

        .badge {
            font-size: 9px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 99px;
            text-transform: uppercase;
        }

        .badge-ready { background: #e2fbe8; color: #1e7e34; }
        .badge-needsupgrade { background: #fff4e5; color: #b45309; }
        .badge-insufficientcapacity { background: #fde8e8; color: #bd2130; }

        .detail-row {
            font-size: 13px;
            color: #475569;
            margin-bottom: 8px;
            display: flex;
            align-items: flex-start;
            gap: 8px;
            line-height: 1.4;
        }

        .detail-row i { color: #94a3b8; width: 16px; margin-top: 3px; }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(4px);
        }
        .modal.open { display: flex; }
        .modal-content {
            background: white;
            width: 90%;
            max-width: 550px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            animation: modalFadeIn 0.3s ease;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .modal-header { padding: 20px 24px; background: #f8f9fa; border-bottom: 1px solid #edf2f7; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { font-size: 18px; color: #2c3e50; }
        .modal-close { background: transparent; border: none; font-size: 18px; cursor: pointer; color: #64748b; }
        .modal-body { padding: 24px; }
        .modal-footer { padding: 16px 24px; background: #f8f9fa; border-top: 1px solid #edf2f7; display: flex; justify-content: flex-end; gap: 12px; }
    </style>
</head>
<body>

<?php include 'includes/utilities_sidebar.php'; ?>

<main class="main-content" id="mainContent">
    <div class="card">
        
        <!-- Header -->
        <div class="dashboard-header">
            <div>
                <h1><i class="fas fa-building"></i> Imported Development Projects</h1>
                <p style="color: #64748b; font-size: 14px; margin-top: 5px;">Review construction projects from the Urban Planning System and assess utility availability readiness.</p>
            </div>
            <div>
                <a href="planning_dashboard.php" class="btn btn-outline"><i class="fas fa-chevron-left"></i> Dashboard</a>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Filters Form -->
        <form method="GET" class="filter-panel">
            <div class="form-group" style="flex:2;">
                <label>Keyword Search</label>
                <input type="text" name="search" class="form-control" placeholder="Search name, location, requirements..." value="<?php echo htmlspecialchars($search); ?>">
            </div>

            <div class="form-group">
                <label>Development Type</label>
                <select name="development_type" class="form-control">
                    <option value="">All Types</option>
                    <option value="Residential" <?php echo $type_filter === 'Residential' ? 'selected' : ''; ?>>Residential</option>
                    <option value="Commercial" <?php echo $type_filter === 'Commercial' ? 'selected' : ''; ?>>Commercial</option>
                    <option value="Industrial" <?php echo $type_filter === 'Industrial' ? 'selected' : ''; ?>>Industrial</option>
                    <option value="Mixed-Use" <?php echo $type_filter === 'Mixed-Use' ? 'selected' : ''; ?>>Mixed-Use</option>
                </select>
            </div>

            <div class="form-group">
                <label>Utility Readiness</label>
                <select name="readiness_status" class="form-control">
                    <option value="">All Readiness</option>
                    <option value="Ready" <?php echo $readiness_filter === 'Ready' ? 'selected' : ''; ?>>Ready</option>
                    <option value="Needs Upgrade" <?php echo $readiness_filter === 'Needs Upgrade' ? 'selected' : ''; ?>>Needs Upgrade</option>
                    <option value="Insufficient Capacity" <?php echo $readiness_filter === 'Insufficient Capacity' ? 'selected' : ''; ?>>Insufficient Capacity</option>
                </select>
            </div>

            <div style="display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
                <a href="planning_projects.php" class="btn btn-outline">Reset</a>
            </div>
        </form>

        <!-- Project Cards Grid -->
        <div class="project-grid">
            <?php if (empty($projectsList)): ?>
                <div style="grid-column: 1/-1; text-align: center; color: #64748b; padding: 40px;">No projects found matching criteria.</div>
            <?php else: ?>
                <?php foreach ($projectsList as $proj): 
                    $badgeClass = strtolower(str_replace(' ', '', $proj['readiness_status']));
                ?>
                    <div class="project-card">
                        <div>
                            <div class="project-header">
                                <div>
                                    <div class="project-title"><?php echo htmlspecialchars($proj['project_name']); ?></div>
                                    <div class="project-location"><?php echo htmlspecialchars($proj['location']); ?></div>
                                </div>
                                <span class="badge badge-<?php echo $badgeClass; ?>">
                                    <?php echo htmlspecialchars($proj['readiness_status']); ?>
                                </span>
                            </div>

                            <div class="detail-row">
                                <i class="fas fa-tags"></i>
                                <span>Type: <strong><?php echo htmlspecialchars($proj['development_type']); ?></strong></span>
                            </div>
                            <div class="detail-row">
                                <i class="fas fa-hourglass-half"></i>
                                <span>Timeline: <?php echo htmlspecialchars($proj['expected_timeline']); ?></span>
                            </div>
                            <div class="detail-row">
                                <i class="fas fa-network-wired"></i>
                                <span>Requirements: <em><?php echo htmlspecialchars($proj['utility_requirements']); ?></em></span>
                            </div>
                            
                            <?php if ($proj['planning_notes']): ?>
                                <div style="margin-top:15px; padding:10px 15px; border-radius:8px; background:#f8fafc; border-left:3px solid #cbd5e1; font-size:12px; color:#475569;">
                                    <strong>Planning Notes:</strong>
                                    <p style="margin-top:2px;"><?php echo htmlspecialchars($proj['planning_notes']); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div style="border-top:1px solid #edf2f7; margin-top:20px; padding-top:15px; display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-size:10px; color:#94a3b8; font-style:italic;">Imported: <?php echo date('M d, Y', strtotime($proj['created_at'])); ?></span>
                            <button class="btn btn-outline" style="padding: 6px 12px; font-size:12px;" onclick="openReviewModal(<?php echo json_encode($proj); ?>)"><i class="fas fa-check-double"></i> Review Utility Readiness</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</main>

<!-- REVIEW PROJECT MODAL -->
<div id="reviewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Review Project Utility Readiness</h3>
            <button class="modal-close" onclick="closeModal('reviewModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="review">
            <input type="hidden" id="review-id" name="id">
            <div class="modal-body">
                <div class="form-group">
                    <label>Utility Readiness Evaluation Status</label>
                    <select id="review-readiness" name="readiness_status" class="form-control" required>
                        <option value="Ready">Ready (Utility grids can absorb development load)</option>
                        <option value="Needs Upgrade">Needs Upgrade (Requires capacity extensions/upgrades)</option>
                        <option value="Insufficient Capacity">Insufficient Capacity (Overload hazard detected)</option>
                    </select>
                </div>

                <div class="form-group" style="margin-top:15px;">
                    <label>Planning Coordination Notes</label>
                    <textarea id="review-notes" name="planning_notes" class="form-control" rows="4" placeholder="Enter evaluation results, recommended pipe sizes, substation requirements, or constraints details..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('reviewModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Evaluation</button>
            </div>
        </form>
    </div>
</div>

<script>
    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
    }

    function openReviewModal(proj) {
        document.getElementById('review-id').value = proj.id;
        document.getElementById('review-readiness').value = proj.readiness_status;
        document.getElementById('review-notes').value = proj.planning_notes || '';
        document.getElementById('reviewModal').classList.add('open');
    }
</script>

</body>
</html>
