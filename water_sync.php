<?php
// water_sync.php — Outbound Integration & Sync Logs
require_once 'includes/auth.php';
require_once 'includes/db.php';
ensureWaterSchema();

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
        // Fetch all raw water records
        $records = $pdo->query("SELECT * FROM water_consumption_records")->fetchAll(PDO::FETCH_ASSOC);
        $count = count($records);
        
        // Export payload string
        $payload = json_encode($records, JSON_PRETTY_PRINT);
        
        if ($simulate_status === 'Successful') {
            // Log successful transmission
            $stmt = $pdo->prepare("
                INSERT INTO water_sync_logs (sync_type, records_count, status, payload_exported) 
                VALUES ('Outbound Data Send', ?, 'Successful', ?)
            ");
            $stmt->execute([$count, $payload]);

            // Save notification
            $pdo->prepare("INSERT INTO water_notifications (message) VALUES (?)")->execute(["Water consumption data successfully synchronized with external Water Board Utility. Transmitted {$count} records."]);
            $success = "Outbound synchronization completed successfully! Transmitted {$count} records.";
        } else {
            // Log failed transmission
            $stmt = $pdo->prepare("
                INSERT INTO water_sync_logs (sync_type, records_count, status, payload_exported, error_details) 
                VALUES ('Outbound Data Send', ?, 'Failed', ?, 'Simulated connection timeout: External API endpoint unreachable.')
            ");
            $stmt->execute([$count, $payload]);

            // Save notification
            $pdo->prepare("INSERT INTO water_notifications (message) VALUES (?)")->execute(["CRITICAL: Data synchronization with Water Board Utility failed."]);
            $error = "Synchronization failed: External API endpoint unreachable.";
        }
    } catch (PDOException $e) {
        $error = "Synchronization process failed: " . $e->getMessage();
    }
}

// Fetch sync logs
$syncLogs = $pdo->query("SELECT * FROM water_sync_logs ORDER BY transferred_at DESC")->fetchAll();

// Dynamic API to view payload
if (isset($_GET['fetch_payload_id'])) {
    header('Content-Type: application/json');
    $stmt = $pdo->prepare("SELECT payload_exported FROM water_sync_logs WHERE id = ?");
    $stmt->execute([intval($_GET['fetch_payload_id'])]);
    echo json_encode(['payload' => $stmt->fetchColumn()]);
    exit();
}
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
    <title>Water Board Integration Sync | LGU Utilities</title>
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        .main-content.collapsed { margin-left: 78px; }
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
        .btn-primary:hover { background: #0369a1; transform: translateY(-1px); }
        .btn-outline   { background: transparent; border: 1.5px solid #cbd5e1; color: #475569; }
        .btn-outline:hover { background: #f8fafc; color: #1e293b; }
        .btn-danger    { background: #ef4444; color: #fff; }
        .btn-danger:hover { background: #dc2626; }

        /* -- Grid Layouts -- */
        .layout-grid { display: grid; grid-template-columns: 1fr 400px; gap: 30px; align-items: start; }
        @media (max-width: 1200px) { .layout-grid { grid-template-columns: 1fr; } }

        /* -- Panel Cards -- */
        .panel {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
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

        .flash { padding: 12px 18px; border-radius: 8px; font-size: 13.5px; font-weight: 500; margin-bottom: 24px; border-left: 4px solid; }
        .flash.success { background: #ecfdf5; color: #065f46; border-left-color: #10b981; }
        .flash.error { background: #fef2f2; color: #991b1b; border-left-color: #ef4444; }

        /* -- Sync Control -- */
        .sync-control-panel { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; display: flex; flex-direction: column; gap: 16px; }
        .sync-control-title { font-size: 14px; font-weight: 600; color: #334155; }
        .sync-control-desc { font-size: 12px; color: #64748b; line-height: 1.5; }
        .sync-select {
            padding: 9px 12px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 13px;
            background: #fff;
            color: #1e293b;
            outline: none;
        }

        /* -- Tables -- */
        .table-wrapper { width: 100%; overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; text-align: left; font-size: 12.5px; }
        .table th { padding: 12px 16px; border-bottom: 2px solid #e2e8f0; color: #475569; font-weight: 600; background: #f8fafc; }
        .table td { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; }
        .table tbody tr:hover td { background: #f8fafc; }

        .status-badge {
            font-size: 10px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .status-badge.Successful { background: #dcfce7; color: #15803d; }
        .status-badge.Failed     { background: #fee2e2; color: #ef4444; }

        .btn-view-payload {
            background: #e0f2fe;
            color: #0284c7;
            border: none;
            padding: 6px 10px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 11px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-view-payload:hover { background: #bae6fd; }

        /* -- Payload Modal -- */
        .modal {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            display: flex; align-items: center; justify-content: center;
            z-index: 2000;
            opacity: 0; pointer-events: none;
            transition: opacity 0.25s ease;
        }
        .modal.active { opacity: 1; pointer-events: all; }
        .modal-content {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            width: 90%;
            max-width: 800px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.25);
            display: flex; flex-direction: column;
            max-height: 80vh;
        }
        .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 16px; }
        .modal-header h3 { font-size: 15px; font-weight: 600; color: #1e293b; }
        .btn-close { background: none; border: none; font-size: 18px; cursor: pointer; color: #94a3b8; }
        .btn-close:hover { color: #64748b; }
        .modal-body { flex: 1; overflow-y: auto; background: #0f172a; color: #38bdf8; padding: 16px; border-radius: 8px; font-family: monospace; font-size: 12px; white-space: pre-wrap; word-break: break-all; }

        /* -- Dark Mode -- */
        .dark-theme .card { background: rgba(15,23,42,0.92); border-color: rgba(255,255,255,0.08); }
        .dark-theme .dashboard-header h1 { color: #f8fafc; }
        .dark-theme .dashboard-header p { color: #94a3b8; }
        .dark-theme .panel { background: #1e293b; border-color: #334155; }
        .dark-theme .panel-title { color: #f8fafc; }
        .dark-theme .panel-title i { color: #94a3b8; }
        .dark-theme .sync-control-panel { background: #0f172a; border-color: #334155; }
        .dark-theme .sync-control-title { color: #f8fafc; }
        .dark-theme .sync-control-desc { color: #94a3b8; }
        .dark-theme .sync-select { background: #0f172a; border-color: #334155; color: #cbd5e1; }
        .dark-theme .table th { background: #0f172a; border-bottom-color: #334155; color: #94a3b8; }
        .dark-theme .table td { border-bottom-color: #1e293b; color: #cbd5e1; }
        .dark-theme .table tbody tr:hover td { background: #0f172a; }
        .dark-theme .modal-content { background: #1e293b; color: #cbd5e1; border: 1px solid #334155; }
        .dark-theme .modal-header h3 { color: #f8fafc; }
        .dark-theme .modal-header { border-bottom-color: #334155; }
        .dark-theme .btn-view-payload { background: #0c4a6e; color: #38bdf8; }
        .dark-theme .btn-view-payload:hover { background: #075985; }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<?php include_once 'includes/utilities_sidebar.php'; ?>

<main class="main-content <?php echo (isset($_COOKIE['sidebar_collapsed']) && $_COOKIE['sidebar_collapsed'] === 'true') ? 'collapsed' : ''; ?>">
    <div class="card">

        <!-- HEADER -->
        <header class="dashboard-header">
            <div>
                <h1><i class="fas fa-sync-alt"></i> Water Board Integration Sync</h1>
                <p>Synchronize monthly water consumption records and export telemetry with external Water Utility systems.</p>
            </div>
            <div>
                <a href="water_dashboard.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            </div>
        </header>

        <!-- FLASH NOTIFICATIONS -->
        <?php if ($success): ?>
            <div class="flash success"><?php echo htmlspecialchars($success); ?></div>
        <?php elseif ($error): ?>
            <div class="flash error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="layout-grid">
            
            <!-- LEFT PANEL: TRANSFERRED LOGS TABLE -->
            <div class="panel">
                <div class="panel-title"><i class="fas fa-list-check"></i> Transfer logs</div>
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Sync ID</th>
                                <th>Sync Type</th>
                                <th>Records Count</th>
                                <th>Status</th>
                                <th>Transferred At</th>
                                <th>Payload</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($syncLogs)): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; color:#64748b; padding:30px;">No synchronization logs recorded.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($syncLogs as $row): ?>
                                    <tr>
                                        <td><strong>SYNC-<?php echo str_pad($row['id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['sync_type']); ?></td>
                                        <td><?php echo $row['records_count']; ?> records</td>
                                        <td>
                                            <span class="status-badge <?php echo $row['status']; ?>">
                                                <i class="fas <?php echo ($row['status'] === 'Successful') ? 'fa-check-circle' : 'fa-circle-xmark'; ?>"></i>
                                                <?php echo $row['status']; ?>
                                            </span>
                                            <?php if ($row['error_details']): ?>
                                                <p style="font-size:10px; color:#ef4444; margin-top:2px;"><?php echo htmlspecialchars($row['error_details']); ?></p>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="muted"><?php echo date('M d, Y H:i:s', strtotime($row['transferred_at'])); ?></span></td>
                                        <td>
                                            <button class="btn-view-payload" onclick="viewPayload(<?php echo $row['id']; ?>)">
                                                <i class="fas fa-eye"></i> View JSON
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- RIGHT PANEL: SYNC CONTROLS -->
            <div class="sync-control-panel">
                <div class="sync-control-title"><i class="fas fa-plug-circle-bolt"></i> Telemetry Sync Tools</div>
                <p class="sync-control-desc">Transmit the current database of water consumption readings to the external Water Board Integration REST endpoint.</p>
                
                <form action="" method="POST">
                    <input type="hidden" name="action" value="sync">
                    
                    <div style="display:flex; flex-direction:column; gap:6px; margin-bottom:14px;">
                        <label class="action-label" for="simulate_status">Connection Simulation</label>
                        <select name="simulate_status" id="simulate_status" class="sync-select">
                            <option value="Successful">Successful Connection (Nominal)</option>
                            <option value="Failed">Force Connection Timeout (Fail)</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                        <i class="fas fa-paper-plane"></i> Synchronize Now
                    </button>
                </form>
            </div>

        </div>

    </div>
</main>

<!-- PAYLOAD DETAILS MODAL -->
<div class="modal" id="payloadModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Exported Payload Metadata</h3>
            <button class="btn-close" onclick="closeModal()">&times;</button>
        </div>
        <pre class="modal-body" id="modalPayloadBody">Loading payload...</pre>
    </div>
</div>

<!-- JAVASCRIPT FOR DYNAMIC MODAL FETCH -->
<script>
const modal = document.getElementById('payloadModal');
const modalBody = document.getElementById('modalPayloadBody');

function viewPayload(id) {
    modalBody.textContent = 'Loading payload...';
    modal.classList.add('active');
    
    fetch(`water_sync.php?fetch_payload_id=${id}`)
        .then(res => res.json())
        .then(data => {
            try {
                // If it is encoded JSON string, format it pretty
                const parsed = JSON.parse(data.payload);
                modalBody.textContent = JSON.stringify(parsed, null, 4);
            } catch(e) {
                modalBody.textContent = data.payload || 'No payload recorded.';
            }
        })
        .catch(err => {
            modalBody.textContent = 'Error loading payload: ' + err.message;
        });
}

function closeModal() {
    modal.classList.remove('active');
}

// Close when clicking overlay
window.addEventListener('click', (e) => {
    if (e.target === modal) closeModal();
});
</script>

</body>
</html>
