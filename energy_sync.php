<?php
// energy_sync.php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';

// Handle POST Trigger Sync Outbound
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sync') {
    $simulate_status = $_POST['simulate_status'] ?? 'Successful';
    
    try {
        // Fetch all raw energy records
        $records = $pdo->query("SELECT * FROM energy_consumption_records")->fetchAll(PDO::FETCH_ASSOC);
        $count = count($records);
        
        // Export payload string
        $payload = json_encode($records, JSON_PRETTY_PRINT);
        
        if ($simulate_status === 'Successful') {
            // Log successful transmission
            $stmt = $pdo->prepare("
                INSERT INTO energy_sync_logs (sync_type, records_count, status, payload_exported) 
                VALUES ('Outbound Data Send', ?, 'Successful', ?)
            ");
            $stmt->execute([$count, $payload]);

            // Save notification
            $pdo->prepare("INSERT INTO energy_notifications (message) VALUES (?)")->execute(["Energy consumption data successfully synchronized with Energy Efficiency System. Transmitted {$count} records."]);
            $success = "Outbound synchronization completed successfully! Transmitted {$count} records.";
        } else {
            // Log failed transmission
            $stmt = $pdo->prepare("
                INSERT INTO energy_sync_logs (sync_type, records_count, status, payload_exported, error_details) 
                VALUES ('Outbound Data Send', ?, 'Failed', ?, 'Simulated connection timeout: External API endpoint unreachable.')
            ");
            $stmt->execute([$count, $payload]);

            // Save notification
            $pdo->prepare("INSERT INTO energy_notifications (message) VALUES (?)")->execute(["CRITICAL: Data synchronization with Energy Efficiency System failed."]);
            $error = "Synchronization failed: External API endpoint unreachable.";
        }
    } catch (PDOException $e) {
        $error = "Synchronization process failed: " . $e->getMessage();
    }
}

// Fetch sync logs
$syncLogs = $pdo->query("SELECT * FROM energy_sync_logs ORDER BY transferred_at DESC")->fetchAll();

// Dynamic API to view payload
if (isset($_GET['fetch_payload_id'])) {
    header('Content-Type: application/json');
    $stmt = $pdo->prepare("SELECT payload_exported FROM energy_sync_logs WHERE id = ?");
    $stmt->execute([intval($_GET['fetch_payload_id'])]);
    echo json_encode(['payload' => $stmt->fetchColumn()]);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Energy Efficiency Integration Synchronization</title>
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
            text-decoration: none;
        }

        .btn-primary { background: #3762c8; color: white; }
        .btn-primary:hover { background: #2851b0; }

        .btn-outline { background: transparent; border: 1px solid #cbd5e1; color: #64748b; }
        .btn-outline:hover { background: #f8f9fa; color: #2c3e50; }

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

        .layout-grid {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 35px;
        }

        @media (max-width: 1000px) {
            .layout-grid { grid-template-columns: 1fr; }
        }

        .section-box {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .section-box h3 {
            font-size: 16px;
            color: #2c3e50;
            margin-bottom: 20px;
            border-bottom: 2px solid #f1f2f6;
            padding-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 15px;
        }

        .form-group label { font-size: 13px; font-weight: 600; color: #64748b; }
        .form-control { padding: 10px 14px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 14px; outline: none; }

        /* Table section */
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th {
            background: #f8f9fa;
            color: #475569;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            padding: 12px 16px;
            border-bottom: 2px solid #e2e8f0;
        }
        td { padding: 14px 16px; border-bottom: 1px solid #edf2f7; font-size: 13px; color: #2c3e50; }
        tr:hover td { background: #fcfcfc; }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 99px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-successful { background: #e2fbe8; color: #1e7e34; }
        .badge-failed { background: #fde8e8; color: #bd2130; }

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
            max-width: 650px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            overflow: hidden;
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
                <h1><i class="fas fa-sync"></i> External System Integration</h1>
                <p style="color: #64748b; font-size: 14px; margin-top: 5px;">Synchronize electricity records and payload datasets with the external Energy Efficiency System.</p>
            </div>
            <div>
                <a href="energy_dashboard.php" class="btn btn-outline"><i class="fas fa-chevron-left"></i> Dashboard</a>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="layout-grid">
            <!-- Left Panel: Trigger Sync -->
            <div class="section-box">
                <h3><i class="fas fa-cloud-upload-alt"></i> Dispatch Outbound Sync</h3>
                <p style="font-size: 13px; color: #64748b; margin-bottom: 20px; line-height: 1.5;">This aggregates all logged electricity consumption records and dispatches them via API to the external Energy Efficiency System queue.</p>
                
                <form method="POST">
                    <input type="hidden" name="action" value="sync">
                    
                    <div class="form-group">
                        <label>Simulate Synchronization Result</label>
                        <select name="simulate_status" class="form-control">
                            <option value="Successful">Simulate API Success (Returns 200 OK)</option>
                            <option value="Failed">Simulate API Connection Error (Returns Timeout)</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;"><i class="fas fa-sync-alt"></i> Trigger Outbound Sync</button>
                </form>
            </div>

            <!-- Right Panel: Logs list -->
            <div class="section-box">
                <h3><i class="fas fa-history"></i> Transmission History</h3>
                
                <div style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Sync Action</th>
                                <th>Count</th>
                                <th>Status</th>
                                <th>Transferred At</th>
                                <th style="text-align:right;">Payload</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($syncLogs)): ?>
                                <tr><td colspan="5" style="text-align:center; padding:20px; color:#64748b;">No transmission logs found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($syncLogs as $log): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($log['sync_type']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($log['records_count']); ?> records</td>
                                        <td><span class="badge badge-<?php echo strtolower($log['status']); ?>"><?php echo htmlspecialchars($log['status']); ?></span></td>
                                        <td><?php echo date('M d, Y h:i A', strtotime($log['transferred_at'])); ?></td>
                                        <td style="text-align:right;">
                                            <button class="btn-outline" style="padding:4px 8px; font-size:11px; border-radius:4px;" onclick="viewPayload(<?php echo $log['id']; ?>)">View Payload</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</main>

<!-- PAYLOAD VIEW MODAL -->
<div id="payloadModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Transmitted Dataset Payload</h3>
            <button class="modal-close" onclick="closeModal('payloadModal')">&times;</button>
        </div>
        <div class="modal-body">
            <pre id="payload-content" style="background:#f8f9fa; padding:15px; border-radius:8px; font-family:monospace; font-size:11px; max-height:400px; overflow:auto; white-space:pre-wrap;"></pre>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" onclick="closeModal('payloadModal')">Close</button>
        </div>
    </div>
</div>

<script>
    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
    }

    function viewPayload(id) {
        document.getElementById('payload-content').textContent = "Loading payload data...";
        document.getElementById('payloadModal').classList.add('open');

        // Fetch payload details using AJAX
        fetch(`energy_sync.php?fetch_payload_id=${id}`)
            .then(res => res.json())
            .then(data => {
                document.getElementById('payload-content').textContent = data.payload || 'No payload logged.';
            });
    }
</script>

</body>
</html>
