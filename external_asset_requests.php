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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #f1f5f9; }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 2rem; }
        .card { background: #fff; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { padding: 10px 12px; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
        th { background: #f8fafc; }
        .badge { padding: 3px 8px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .pending { background: #fef3c7; color: #92400e; }
        .approved { background: #dbeafe; color: #1e40af; }
        .fulfilled { background: #d1fae5; color: #065f46; }
        .rejected { background: #fee2e2; color: #991b1b; }
        .btn { border: none; border-radius: 6px; padding: 6px 12px; cursor: pointer; font-size: 13px; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-danger { background: #dc2626; color: #fff; }
        .btn-success { background: #059669; color: #fff; }
        select, textarea { width: 100%; padding: 6px; border: 1px solid #cbd5e1; border-radius: 6px; }
        .alert { padding: 10px 12px; border-radius: 8px; margin-bottom: 1rem; }
        .alert-ok { background: #d1fae5; color: #065f46; }
        .alert-err { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
<div class="wrap">
    <p><a href="utilities_dashboard.php">&larr; Back to UMAN Dashboard</a></p>
    <div class="card">
        <h1><i class="fas fa-exchange-alt"></i> CPRF Asset Requests</h1>
        <p style="color:#64748b;">Equipment/utility asset requests from the Facilities Reservation System.</p>

        <?php if ($success): ?><div class="alert alert-ok"><?= htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-err"><?= htmlspecialchars($error); ?></div><?php endif; ?>

        <form method="GET" style="margin:1rem 0;">
            <select name="status" onchange="this.form.submit()">
                <option value="">All statuses</option>
                <?php foreach (['pending', 'approved', 'fulfilled', 'rejected'] as $s): ?>
                    <option value="<?= $s; ?>" <?= $filter === $s ? 'selected' : ''; ?>><?= ucfirst($s); ?></option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if (empty($requests)): ?>
            <p style="color:#64748b;">No external requests yet.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Ref</th>
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
                            <td><strong><?= htmlspecialchars($req['request_ref']); ?></strong><br><small><?= htmlspecialchars($req['created_at']); ?></small></td>
                            <td><?= htmlspecialchars($req['facility_name']); ?><br><small>CPRF ID: <?= (int)$req['cprf_facility_id']; ?></small><?php if ($req['notes']): ?><br><em><?= htmlspecialchars($req['notes']); ?></em><?php endif; ?></td>
                            <td><?= htmlspecialchars($req['asset_type']); ?></td>
                            <td><?= (int)$req['quantity']; ?></td>
                            <td><span class="badge <?= htmlspecialchars($req['status']); ?>"><?= ucfirst($req['status']); ?></span>
                                <?php if ($req['fulfilled_asset_name']): ?><br><small>Fulfilled: <?= htmlspecialchars($req['fulfilled_asset_name']); ?></small><?php endif; ?>
                            </td>
                            <td>
                                <?php if ($req['status'] === 'pending'): ?>
                                    <form method="POST" style="margin-bottom:6px;">
                                        <input type="hidden" name="id" value="<?= (int)$req['id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <textarea name="review_notes" rows="2" placeholder="Review notes"></textarea>
                                        <button class="btn btn-primary" type="submit">Approve</button>
                                    </form>
                                    <form method="POST">
                                        <input type="hidden" name="id" value="<?= (int)$req['id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button class="btn btn-danger" type="submit">Reject</button>
                                    </form>
                                <?php elseif (in_array($req['status'], ['pending', 'approved'], true)): ?>
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
                                        <button class="btn btn-success" type="submit">Mark Fulfilled</button>
                                    </form>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
