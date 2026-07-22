<?php
/**
 * UMAN staff: review CPRF external asset/equipment requests.
 */
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn() || !isEmployee()) {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';

$pdo->exec("
    CREATE TABLE IF NOT EXISTS `external_asset_requests` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `request_ref` VARCHAR(50) NOT NULL UNIQUE,
      `source_system` VARCHAR(50) NOT NULL DEFAULT 'CPRF',
      `cprf_facility_id` INT NOT NULL,
      `facility_name` VARCHAR(150) NOT NULL,
      `asset_type` VARCHAR(100) NOT NULL,
      `quantity` INT NOT NULL DEFAULT 1,
      `notes` TEXT NULL,
      `status` ENUM('pending', 'approved', 'fulfilled', 'rejected') NOT NULL DEFAULT 'pending',
      `fulfilled_asset_id` INT NULL,
      `review_notes` TEXT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        $error = 'Invalid request.';
    } elseif ($action === 'approve') {
        $notes = trim($_POST['review_notes'] ?? '');
        $pdo->prepare("UPDATE external_asset_requests SET status = 'approved', review_notes = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$notes ?: null, $id]);
        $success = 'Request approved.';
    } elseif ($action === 'reject') {
        $notes = trim($_POST['review_notes'] ?? '');
        $pdo->prepare("UPDATE external_asset_requests SET status = 'rejected', review_notes = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$notes ?: null, $id]);
        $success = 'Request rejected.';
    } elseif ($action === 'fulfill') {
        $assetId = (int)($_POST['fulfilled_asset_id'] ?? 0);
        $notes = trim($_POST['review_notes'] ?? '');
        if ($assetId <= 0) {
            $error = 'Select a utility asset to fulfill this request.';
        } else {
            $pdo->prepare("UPDATE external_asset_requests SET status = 'fulfilled', fulfilled_asset_id = ?, review_notes = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$assetId, $notes ?: null, $id]);
            $success = 'Request marked fulfilled and linked to asset.';
        }
    }
}

$filter = trim($_GET['status'] ?? '');
$sql = 'SELECT r.*, a.name AS fulfilled_asset_name, a.asset_id AS fulfilled_asset_code FROM external_asset_requests r LEFT JOIN utility_assets a ON a.id = r.fulfilled_asset_id WHERE 1=1';
$params = [];
if ($filter !== '' && in_array($filter, ['pending', 'approved', 'fulfilled', 'rejected'], true)) {
    $sql .= ' AND r.status = ?';
    $params[] = $filter;
}
$sql .= ' ORDER BY r.created_at DESC LIMIT 100';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$assets = $pdo->query("
    SELECT a.id, a.asset_id, a.name, t.name AS asset_type
    FROM utility_assets a
    JOIN asset_types t ON t.id = a.asset_type_id
    WHERE a.condition_status IN ('Operational', 'Needs Inspection')
    ORDER BY a.name ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CPRF Asset Requests | UMAN</title>
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

        /* Dashboard Header */
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
            font-size: 28px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .dashboard-header h1 i {
            color: #3762c8;
            font-size: 30px;
        }

        .dashboard-header .subtitle {
            color: #64748b;
            font-size: 14px;
            margin-top: 5px;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            border-left: 5px solid #cbd5e1;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        .stat-card.stat-pending { border-left-color: #f59e0b; }
        .stat-card.stat-approved { border-left-color: #3762c8; }
        .stat-card.stat-fulfilled { border-left-color: #10b981; }
        .stat-card.stat-rejected { border-left-color: #ef4444; }

        .stat-card h3 {
            font-size: 28px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 4px;
        }

        .stat-card p {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        /* Alerts */
        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-ok {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
            border: 1px solid #6ee7b7;
        }

        .alert-err {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        /* Filter Bar */
        .filter-bar {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .filter-bar label {
            font-size: 14px;
            font-weight: 600;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-bar select {
            padding: 10px 16px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            background: white;
            color: #2c3e50;
            cursor: pointer;
            transition: all 0.2s;
            min-width: 200px;
        }

        .filter-bar select:focus {
            outline: none;
            border-color: #3762c8;
            box-shadow: 0 0 0 3px rgba(55, 98, 200, 0.15);
        }

        /* Table Section */
        .table-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
            overflow-x: auto;
        }

        .table-section h3 {
            font-size: 16px;
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 2px solid #f1f2f6;
            padding-bottom: 10px;
        }

        .table-section h3 i {
            color: #3762c8;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        th {
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

        td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
            color: #334155;
        }

        tr {
            transition: background 0.15s ease;
        }

        tbody tr:hover {
            background: rgba(55, 98, 200, 0.03);
        }

        td strong {
            color: #1e293b;
            font-weight: 600;
        }

        td small {
            color: #94a3b8;
            font-size: 11px;
        }

        td em {
            color: #64748b;
            font-size: 12px;
            font-style: italic;
        }

        /* Status Badges */
        .badge {
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }

        .pending {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #92400e;
            border: 1px solid #fbbf24;
        }

        .approved {
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            color: #1e40af;
            border: 1px solid #60a5fa;
        }

        .fulfilled {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
            border: 1px solid #34d399;
        }

        .rejected {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
            border: 1px solid #f87171;
        }

        /* Action Forms */
        .action-form {
            background: #f8fafc;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 8px;
            border: 1px solid #e2e8f0;
            transition: box-shadow 0.2s ease;
        }

        .action-form:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }

        .action-form:last-child {
            margin-bottom: 0;
        }

        .action-form textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 13px;
            font-family: 'Poppins', sans-serif;
            resize: vertical;
            transition: border-color 0.2s;
            margin-bottom: 8px;
            background: white;
        }

        .action-form textarea:focus {
            outline: none;
            border-color: #3762c8;
            box-shadow: 0 0 0 3px rgba(55, 98, 200, 0.1);
        }

        .action-form select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 13px;
            font-family: 'Poppins', sans-serif;
            background: white;
            color: #334155;
            cursor: pointer;
            margin-bottom: 8px;
            transition: border-color 0.2s;
        }

        .action-form select:focus {
            outline: none;
            border-color: #3762c8;
            box-shadow: 0 0 0 3px rgba(55, 98, 200, 0.1);
        }

        /* Buttons */
        .btn {
            padding: 8px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            border: none;
            cursor: pointer;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: 'Poppins', sans-serif;
        }

        .btn-primary {
            background: #3762c8;
            color: white;
        }

        .btn-primary:hover {
            background: #2851b0;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(55, 98, 200, 0.35);
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-state p {
            font-size: 15px;
            font-weight: 500;
        }

        /* Fulfilled asset link */
        .fulfilled-link {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            color: #10b981;
            font-weight: 500;
            margin-top: 4px;
        }

        /* No-action text */
        .no-action {
            color: #94a3b8;
            font-size: 13px;
            font-style: italic;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .main-content {
                margin-left: 0 !important;
                padding: 20px;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>

<?php include 'includes/utilities_sidebar.php'; ?>

<main class="main-content" id="mainContent">
    <div class="card">

        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div>
                <h1><i class="fas fa-exchange-alt"></i> CPRF Asset Requests</h1>
                <p class="subtitle">Equipment/utility asset requests from the Community Public Reservation Facilities System.</p>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-ok"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-err"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Stats Summary -->
        <?php
            $countPending = 0; $countApproved = 0; $countFulfilled = 0; $countRejected = 0;
            foreach ($requests as $r) {
                switch ($r['status']) {
                    case 'pending':   $countPending++;   break;
                    case 'approved':  $countApproved++;  break;
                    case 'fulfilled': $countFulfilled++; break;
                    case 'rejected':  $countRejected++;  break;
                }
            }
        ?>
        <div class="stats-grid">
            <div class="stat-card stat-pending">
                <h3><?= $countPending; ?></h3>
                <p><i class="fas fa-clock"></i> Pending</p>
            </div>
            <div class="stat-card stat-approved">
                <h3><?= $countApproved; ?></h3>
                <p><i class="fas fa-thumbs-up"></i> Approved</p>
            </div>
            <div class="stat-card stat-fulfilled">
                <h3><?= $countFulfilled; ?></h3>
                <p><i class="fas fa-check-double"></i> Fulfilled</p>
            </div>
            <div class="stat-card stat-rejected">
                <h3><?= $countRejected; ?></h3>
                <p><i class="fas fa-times-circle"></i> Rejected</p>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <label><i class="fas fa-filter"></i> Filter by Status:</label>
            <form method="GET" style="margin:0;">
                <select name="status" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <?php foreach (['pending', 'approved', 'fulfilled', 'rejected'] as $s): ?>
                        <option value="<?= $s; ?>" <?= $filter === $s ? 'selected' : ''; ?>><?= ucfirst($s); ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <!-- Requests Table -->
        <div class="table-section">
            <h3><i class="fas fa-list-alt"></i> External Asset Requests</h3>

            <?php if (empty($requests)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No external requests found.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Facility (CPRF)</th>
                            <th>Asset Type</th>
                            <th>Qty</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $req): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($req['request_ref']); ?></strong><br>
                                    <small><?= htmlspecialchars($req['created_at']); ?></small>
                                </td>
                                <td>
                                    <?= htmlspecialchars($req['facility_name']); ?><br>
                                    <small>CPRF ID: <?= (int)$req['cprf_facility_id']; ?></small>
                                    <?php if ($req['notes']): ?><br><em><?= htmlspecialchars($req['notes']); ?></em><?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($req['asset_type']); ?></td>
                                <td><strong><?= (int)$req['quantity']; ?></strong></td>
                                <td>
                                    <span class="badge <?= htmlspecialchars($req['status']); ?>"><?= ucfirst($req['status']); ?></span>
                                    <?php if ($req['fulfilled_asset_name']): ?>
                                        <div class="fulfilled-link"><i class="fas fa-link"></i> <?= htmlspecialchars($req['fulfilled_asset_name']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($req['status'] === 'pending'): ?>
                                        <div class="action-form">
                                            <form method="POST">
                                                <input type="hidden" name="id" value="<?= (int)$req['id']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <textarea name="review_notes" rows="2" placeholder="Review notes (optional)"></textarea>
                                                <div class="btn-actions">
                                                    <button class="btn btn-primary" type="submit"><i class="fas fa-check"></i> Approve</button>
                                                </div>
                                            </form>
                                        </div>
                                        <div class="action-form">
                                            <form method="POST">
                                                <input type="hidden" name="id" value="<?= (int)$req['id']; ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button class="btn btn-danger" type="submit"><i class="fas fa-times"></i> Reject</button>
                                            </form>
                                        </div>
                                    <?php elseif (in_array($req['status'], ['pending', 'approved'], true)): ?>
                                        <div class="action-form">
                                            <form method="POST">
                                                <input type="hidden" name="id" value="<?= (int)$req['id']; ?>">
                                                <input type="hidden" name="action" value="fulfill">
                                                <select name="fulfilled_asset_id" required>
                                                    <option value="">Link asset…</option>
                                                    <?php foreach ($assets as $a): ?>
                                                        <option value="<?= (int)$a['id']; ?>"><?= htmlspecialchars($a['asset_id'] . ' — ' . $a['name']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <textarea name="review_notes" rows="2" placeholder="Fulfillment notes"></textarea>
                                                <button class="btn btn-success" type="submit"><i class="fas fa-check-double"></i> Mark Fulfilled</button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span class="no-action">— No actions available</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </div>
</main>

</body>
</html>

