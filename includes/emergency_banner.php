<?php
// includes/emergency_banner.php
// Modal-style emergency banner (like logout modal)

global $pdo;
if (!isset($pdo)) return;

try {
    // Check for active emergency (last 7 days)
    $stmt = $pdo->prepare("
        SELECT id, title, content, severity, area_affected, published_date
        FROM utility_advisories
        WHERE severity = 'Emergency'
          AND published_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY published_date DESC
        LIMIT 1
    ");
    $stmt->execute();
    $emergency = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$emergency) return;

    // If user already dismissed this emergency, don't show
    if (isset($_SESSION['hide_emergency_banner']) && $_SESSION['hide_emergency_banner'] == $emergency['id']) {
        return;
    }

    ?>
    <style>
        /* Emergency Modal Overlay – matches logout modal */
        .emergency-modal {
            display: flex;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.6);
            z-index: 99999;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            animation: logoutFadeIn 0.25s ease-out;
        }
        .emergency-modal-content {
            background: #ffffff;
            border-radius: 16px;
            width: 500px;
            max-width: 90%;
            padding: 30px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 10px 10px -5px rgba(0, 0, 0, 0.05);
            text-align: center;
            animation: logoutScaleUp 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            border: 1px solid rgba(220, 53, 69, 0.3);
        }
        .emergency-modal-header i {
            font-size: 48px;
            color: #dc3545;
            margin-bottom: 15px;
        }
        .emergency-modal-header h2 {
            font-size: 22px;
            color: #0f172a;
            font-weight: 600;
            margin-bottom: 10px;
            margin-top: 0;
        }
        .emergency-modal-text {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 15px;
            line-height: 1.6;
        }
        .emergency-modal-footer {
            margin-top: 20px;
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .emergency-modal-btn {
            padding: 10px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            display: inline-block;
        }
        .emergency-modal-btn.dismiss-btn {
            background: #f1f5f9;
            color: #475569;
        }
        .emergency-modal-btn.dismiss-btn:hover {
            background: #e2e8f0;
            color: #334155;
        }
        .emergency-modal-btn.acknowledge-btn {
            background: #dc3545;
            color: #ffffff;
        }
        .emergency-modal-btn.acknowledge-btn:hover {
            background: #b02a37;
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }
        .emergency-modal .badge-area {
            display: inline-block;
            background: #dc3545;
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
    </style>

    <div class="emergency-modal" id="emergencyModal">
        <div class="emergency-modal-content">
            <div class="emergency-modal-header">
                <i class="fas fa-exclamation-triangle"></i>
                <h2>🚨 Emergency Advisory</h2>
                <span class="badge-area">Active</span>
            </div>
            <div class="emergency-modal-text">
                <strong style="font-size:16px; color:#0f172a;"><?php echo htmlspecialchars($emergency['title']); ?></strong><br>
                <span style="display:block; margin:10px 0;"><?php echo htmlspecialchars($emergency['content']); ?></span>
                <?php if (!empty($emergency['area_affected'])): ?>
                    <span style="display:inline-block; background:#f1f5f9; padding:2px 12px; border-radius:20px; font-size:13px; color:#475569;">
                        📍 <?php echo htmlspecialchars($emergency['area_affected']); ?>
                    </span>
                <?php endif; ?>
                <div style="font-size:12px; color:#94a3b8; margin-top:8px;">
                    <?php echo date('M d, Y h:i A', strtotime($emergency['published_date'])); ?>
                </div>
            </div>
            <div class="emergency-modal-footer">
                <button class="emergency-modal-btn dismiss-btn" onclick="hideEmergencyBanner(<?php echo $emergency['id']; ?>)">
                    I Understand
                </button>
                <a href="incidents_dashboard.php" class="emergency-modal-btn acknowledge-btn">
                    Report Issue
                </a>
            </div>
        </div>
    </div>

    <script>
    function hideEmergencyBanner(id) {
        // Hide the modal
        document.getElementById('emergencyModal').style.display = 'none';
        // Send request to hide in session
        fetch('hide_banner.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + id
        }).catch(() => {});
    }
    </script>
    <?php
} catch (Exception $e) {
    // Silent fail – don't break the page
    error_log('Emergency banner error: ' . $e->getMessage());
}
?>