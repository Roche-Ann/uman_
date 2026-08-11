<?php
// includes/emergency_banner.php
global $pdo;
if (!isset($pdo)) return;

try {
    $stmt = $pdo->prepare("
        SELECT id, title, content, severity, area_affected, published_date
        FROM utility_advisories
        WHERE severity = 'Emergency'
        AND published_date <= NOW()
        ORDER BY published_date DESC
        LIMIT 1
    ");
    $stmt->execute();
    $emergency = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($emergency): ?>
        <div id="emergency-banner" style="
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
            padding: 12px 20px;
            text-align: center;
            font-family: 'Poppins', sans-serif;
            position: sticky;
            top: 0;
            z-index: 9999;
            box-shadow: 0 4px 20px rgba(220, 53, 69, 0.4);
            animation: emergencyPulse 2s infinite;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        ">
            <div style="display: flex; align-items: center; gap: 12px; flex: 1; justify-content: center; flex-wrap: wrap;">
                <span style="background:white; color:#dc3545; padding:4px 12px; border-radius:20px; font-weight:700; font-size:12px; text-transform:uppercase; animation:emergencyPulse 1s infinite;">
                    🚨 EMERGENCY
                </span>
                <span style="font-weight:600; font-size:15px;">
                    <?php echo htmlspecialchars($emergency['title'] ?? 'Emergency Advisory'); ?>
                </span>
                <span style="opacity:0.9; font-size:13px;">
                    <?php echo htmlspecialchars(substr($emergency['content'] ?? '', 0, 100)) . (strlen($emergency['content'] ?? '') > 100 ? '...' : ''); ?>
                </span>
                <?php if (!empty($emergency['area_affected'])): ?>
                    <span style="background:rgba(255,255,255,0.2); padding:2px 12px; border-radius:20px; font-size:12px;">
                        📍 <?php echo htmlspecialchars($emergency['area_affected']); ?>
                    </span>
                <?php endif; ?>
                <span style="font-size:11px; opacity:0.7;">
                    <?php echo date('M d, Y h:i A', strtotime($emergency['published_date'])); ?>
                </span>
            </div>
            <button onclick="closeEmergencyBanner()" style="background:rgba(255,255,255,0.2); border:none; color:white; font-size:20px; cursor:pointer; padding:0 10px; border-radius:50%; transition:background 0.2s;">
                ✕
            </button>
        </div>
        <style>
            @keyframes emergencyPulse { 0%,100% { opacity:1; } 50% { opacity:0.85; } }
        </style>
        <script>
        function closeEmergencyBanner() {
            const banner = document.getElementById('emergency-banner');
            banner.style.display = 'none';
            sessionStorage.setItem('emergency_banner_closed', 'true');
        }
        document.addEventListener('DOMContentLoaded', function() {
            const banner = document.getElementById('emergency-banner');
            if (sessionStorage.getItem('emergency_banner_closed') === 'true') {
                banner.style.display = 'none';
            }
        });
        </script>
    <?php endif;
} catch (Exception $e) {}
?>