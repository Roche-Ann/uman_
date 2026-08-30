<?php
// citizen_profile.php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$userId = (int)($_SESSION['user_id'] ?? 3);

$error = '';
$success = '';
$showPostLogoutPrompt = false;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        if (empty($fullname) || empty($email)) {
            $error = "Full Name and Email cannot be empty.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            try {
                // Check if email taken by another user
                $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
                $chk->execute([$email, $userId]);
                if ($chk->fetch()) {
                    $error = "This email address is already in use by another account.";
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
                    $stmt->execute([$fullname, $email, $userId]);
                    
                    $_SESSION['user_name'] = $fullname;
                    $_SESSION['full_name'] = $fullname;
                    $_SESSION['user_email'] = $email;
                    
                    $success = "Profile details updated successfully!";
                }
            } catch (PDOException $e) {
                $error = "Failed to update profile: " . $e->getMessage();
            }
        }
    } elseif ($action === 'update_password') {
        $curr = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $logoutAllDevices = isset($_POST['logout_all_devices']) && $_POST['logout_all_devices'] === '1';
        
        if (empty($curr) || empty($new) || empty($confirm)) {
            $error = "All password fields are required.";
        } elseif (strlen($new) < 8) {
            $error = "New password must be at least 8 characters long.";
        } elseif ($new !== $confirm) {
            $error = "New password and confirmation password do not match.";
        } else {
            try {
                // Verify current password
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $dbPass = $stmt->fetchColumn();
                
                if (!password_verify($curr, (string)$dbPass)) {
                    $error = "Incorrect current password. Please try again.";
                } else {
                    $hashed = password_hash($new, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed, $userId]);
                    
                    if ($logoutAllDevices) {
                        $revokedCount = revokeAllUserSessionsExceptCurrent($userId);
                        $success = "Password successfully updated! All other active devices and sessions have been logged out.";
                    } else {
                        $success = "Password successfully changed!";
                        $showPostLogoutPrompt = true;
                    }
                }
            } catch (PDOException $e) {
                $error = "Failed to update password: " . $e->getMessage();
            }
        }
    } elseif ($action === 'revoke_session') {
        $targetSessionId = (int)($_POST['session_id'] ?? 0);
        if ($targetSessionId > 0) {
            $revoked = revokeUserSession($userId, $targetSessionId);
            if ($revoked) {
                $success = "Selected device session has been logged out successfully.";
            } else {
                $error = "Unable to revoke session or session already expired.";
            }
        }
    } elseif ($action === 'revoke_all_other_sessions') {
        $revokedCount = revokeAllUserSessionsExceptCurrent($userId);
        $success = "All other active device sessions (" . $revokedCount . ") have been logged out.";
    }
}

// Retrieve user profile
$stmt = $pdo->prepare("SELECT full_name, email, created_at, user_type FROM users WHERE id = ?");
$stmt->execute([$userId]);
$profile = $stmt->fetch() ?: ['full_name' => 'Citizen', 'email' => '', 'created_at' => null, 'user_type' => 'citizen'];

// Retrieve active sessions
$activeSessions = getUserActiveSessions($userId);
$otherSessionsCount = 0;
foreach ($activeSessions as $s) {
    if (!$s['is_current']) {
        $otherSessionsCount++;
    }
}

// Format relative time helper
function timeAgo(string $datetime): string {
    $time = strtotime($datetime);
    if (!$time) return 'Recently';
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', $time);
}

// Get device icon class
function getDeviceIcon(string $deviceType, string $platform): string {
    $platform = strtolower($platform);
    if (str_contains($platform, 'windows')) return 'fab fa-windows text-primary';
    if (str_contains($platform, 'apple') || str_contains($platform, 'ios') || str_contains($platform, 'macos')) return 'fab fa-apple text-dark';
    if (str_contains($platform, 'android')) return 'fab fa-android text-success';
    if (str_contains($platform, 'linux')) return 'fab fa-linux text-warning';
    
    if (strtolower($deviceType) === 'mobile') return 'fas fa-mobile-screen-button text-info';
    if (strtolower($deviceType) === 'tablet') return 'fas fa-tablet-screen-button text-info';
    return 'fas fa-laptop text-primary';
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
    <title>Resident Account & Security Settings</title>
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Public+Sans:wght@400;500;600;700&display=swap');

        :root {
            --civic-sapphire: #1E3A8A;
            --civic-sapphire-hover: #1e40af;
            --utility-teal: #0D9488;
            --municipal-slate: #334155;
            --bg-card: rgba(255, 255, 255, 0.92);
            --border-card: rgba(226, 232, 240, 0.9);
            --text-main: #1e293b;
            --text-muted: #64748b;
            --input-bg: #ffffff;
            --input-border: #cbd5e1;
            --shadow-card: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        .dark-theme {
            --bg-card: rgba(30, 41, 59, 0.94);
            --border-card: rgba(51, 65, 85, 0.8);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --input-bg: #0f172a;
            --input-border: #334155;
            --shadow-card: 0 10px 30px rgba(0, 0, 0, 0.35);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', 'Public Sans', sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            background: url("assets/images/cityhall.jpeg") center/cover no-repeat fixed;
            position: relative;
            color: var(--text-main);
        }

        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            background: rgba(15, 23, 42, 0.45);
            z-index: 0;
        }

        .main-content {
            flex: 1;
            padding: 40px 24px 100px;
            transition: all 0.25s ease;
            z-index: 1;
            position: relative;
            max-width: 1300px;
            margin: 0 auto;
            width: 100%;
        }

        /* Top Header Card */
        .profile-hero {
            background: var(--bg-card);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 20px;
            padding: 28px 36px;
            box-shadow: var(--shadow-card);
            border: 1px solid var(--border-card);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }

        .hero-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .hero-avatar {
            width: 68px;
            height: 68px;
            border-radius: 18px;
            background: linear-gradient(135deg, #3762c8, #0D9488);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
            box-shadow: 0 8px 16px rgba(55, 98, 200, 0.3);
        }

        .hero-text h1 {
            font-size: 26px;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .hero-text p {
            color: var(--text-muted);
            font-size: 14px;
            margin-top: 4px;
        }

        .badge-citizen-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(13, 148, 136, 0.12);
            color: #0D9488;
            font-size: 12px;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 20px;
            border: 1px solid rgba(13, 148, 136, 0.3);
        }

        /* Buttons */
        .btn {
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3762c8, #1e40af);
            color: white;
            box-shadow: 0 4px 12px rgba(55, 98, 200, 0.25);
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #2b4ea7, #173282);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(55, 98, 200, 0.35);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--input-border);
            color: var(--text-main);
        }
        .btn-outline:hover {
            background: rgba(0, 0, 0, 0.05);
        }

        .btn-danger-outline {
            background: rgba(239, 68, 68, 0.08);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ef4444;
        }
        .btn-danger-outline:hover {
            background: #ef4444;
            color: white;
            border-color: #ef4444;
        }

        .btn-sm {
            padding: 6px 14px;
            font-size: 12px;
            border-radius: 8px;
        }

        .btn-warning {
            background: #f59e0b;
            color: white;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }
        .btn-warning:hover {
            background: #d97706;
        }

        /* Alerts */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            animation: fadeIn 0.3s ease;
        }
        .alert-content {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .dark-theme .alert-error {
            background: rgba(153, 27, 27, 0.25);
            color: #fca5a5;
            border-color: rgba(239, 68, 68, 0.4);
        }
        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .dark-theme .alert-success {
            background: rgba(22, 101, 52, 0.25);
            color: #86efac;
            border-color: rgba(34, 197, 94, 0.4);
        }

        /* Post Password Change Warning Banner */
        .security-prompt-banner {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.12), rgba(217, 119, 6, 0.18));
            border: 1px solid rgba(245, 158, 11, 0.4);
            border-radius: 14px;
            padding: 20px 24px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }
        .security-prompt-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .security-prompt-icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background: #f59e0b;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        .security-prompt-text h4 {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-main);
        }
        .security-prompt-text p {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        /* Settings Grid Layout */
        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 28px;
            margin-bottom: 28px;
        }

        @media (max-width: 900px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
        }

        .settings-card {
            background: var(--bg-card);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 18px;
            padding: 30px;
            box-shadow: var(--shadow-card);
            border: 1px solid var(--border-card);
            display: flex;
            flex-direction: column;
        }

        .settings-card-header {
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border-card);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .settings-card-header h2 {
            font-size: 18px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-main);
        }

        .settings-card-header h2 i {
            color: #3762c8;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .form-control {
            width: 100%;
            padding: 11px 16px;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 10px;
            font-size: 14px;
            color: var(--text-main);
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .form-control:focus {
            border-color: #3762c8;
            box-shadow: 0 0 0 3px rgba(55, 98, 200, 0.15);
        }

        .password-toggle-btn {
            position: absolute;
            right: 14px;
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 14px;
            padding: 4px;
        }
        .password-toggle-btn:hover {
            color: var(--text-main);
        }

        /* Checkbox Box (Log out of all devices option) */
        .security-option-box {
            background: rgba(55, 98, 200, 0.05);
            border: 1px solid rgba(55, 98, 200, 0.18);
            border-radius: 12px;
            padding: 16px;
            margin: 18px 0 22px;
            transition: all 0.2s ease;
        }
        .dark-theme .security-option-box {
            background: rgba(55, 98, 200, 0.1);
            border-color: rgba(55, 98, 200, 0.25);
        }
        .security-option-box:hover {
            border-color: rgba(55, 98, 200, 0.4);
        }

        .custom-checkbox-container {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            cursor: pointer;
            user-select: none;
        }

        .custom-checkbox-container input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-top: 2px;
            accent-color: #3762c8;
            cursor: pointer;
            flex-shrink: 0;
        }

        .checkbox-content {
            flex: 1;
        }

        .checkbox-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .pill-recommended {
            background: #10b981;
            color: white;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 2px 8px;
            border-radius: 20px;
            letter-spacing: 0.5px;
        }

        .checkbox-desc {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 3px;
            line-height: 1.4;
        }

        /* Full Width Device Tracking Section */
        .devices-card {
            background: var(--bg-card);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 18px;
            padding: 30px;
            box-shadow: var(--shadow-card);
            border: 1px solid var(--border-card);
        }

        .devices-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
            padding-bottom: 18px;
            border-bottom: 1px solid var(--border-card);
            margin-bottom: 22px;
        }

        .devices-header-left h2 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .devices-header-left p {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .device-count-badge {
            background: rgba(55, 98, 200, 0.12);
            color: #3762c8;
            font-size: 12px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 12px;
            margin-left: 6px;
        }

        /* Device Session Item */
        .sessions-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .session-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 14px;
            gap: 16px;
            transition: all 0.2s ease;
        }

        .session-item:hover {
            border-color: #3762c8;
            transform: translateY(-1px);
        }

        .session-item.current-device {
            border-left: 4px solid #10b981;
            background: rgba(16, 185, 129, 0.03);
        }

        .session-left {
            display: flex;
            align-items: center;
            gap: 16px;
            min-width: 0;
            flex: 1;
        }

        .session-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: rgba(55, 98, 200, 0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }
        .dark-theme .session-icon {
            background: rgba(255, 255, 255, 0.06);
        }

        .session-details {
            min-width: 0;
        }

        .session-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .session-meta {
            display: flex;
            align-items: center;
            gap: 14px;
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 4px;
            flex-wrap: wrap;
        }

        .session-meta span {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge-current {
            background: rgba(16, 185, 129, 0.12);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge-active-session {
            background: rgba(100, 116, 139, 0.12);
            color: var(--text-muted);
            font-size: 11px;
            font-weight: 500;
            padding: 2px 8px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .session-right {
            flex-shrink: 0;
        }

        .security-tip-footer {
            margin-top: 20px;
            padding: 14px 18px;
            background: rgba(13, 148, 136, 0.08);
            border: 1px solid rgba(13, 148, 136, 0.2);
            border-radius: 12px;
            font-size: 12px;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .security-tip-footer i {
            color: #0D9488;
            font-size: 16px;
        }

        /* Confirmation Modals */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            z-index: 99999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s ease;
        }
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        .modal-box {
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            border-radius: 18px;
            width: 100%;
            max-width: 440px;
            padding: 28px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            text-align: center;
            transform: scale(0.95);
            transition: transform 0.2s ease;
        }
        .modal-overlay.active .modal-box {
            transform: scale(1);
        }
        .modal-icon-danger {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(239, 68, 68, 0.12);
            color: #ef4444;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            margin: 0 auto 16px;
        }
        .modal-box h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 8px;
        }
        .modal-box p {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.5;
            margin-bottom: 24px;
        }
        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-4px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

<?php include 'includes/utilities_sidebar.php'; ?>

<main class="main-content" id="mainContent">
    
    <!-- Hero / Header -->
    <div class="profile-hero">
        <div class="hero-info">
            <div class="hero-avatar">
                <i class="fas fa-shield-halved"></i>
            </div>
            <div class="hero-text">
                <h1>
                    Account & Security
                    <span class="badge-citizen-pill"><i class="fas fa-user-check"></i> Verified Resident</span>
                </h1>
                <p>Manage your profile, change login credentials, and monitor devices currently signed into your account.</p>
            </div>
        </div>
        <div>
            <a href="citizen.php" class="btn btn-outline"><i class="fas fa-chevron-left"></i> Back to Hub</a>
        </div>
    </div>

    <!-- Feedback Alerts -->
    <?php if ($error): ?>
        <div class="alert alert-error">
            <div class="alert-content">
                <i class="fas fa-exclamation-circle fa-lg"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
            <button type="button" class="btn-sm btn-outline" onclick="this.parentElement.remove()" style="border:none;">&times;</button>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <div class="alert-content">
                <i class="fas fa-check-circle fa-lg"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
            <button type="button" class="btn-sm btn-outline" onclick="this.parentElement.remove()" style="border:none;">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Post Password Change Prompt (if user didn't auto logout) -->
    <?php if ($showPostLogoutPrompt && $otherSessionsCount > 0): ?>
        <div class="security-prompt-banner">
            <div class="security-prompt-left">
                <div class="security-prompt-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="security-prompt-text">
                    <h4>Log out of other devices?</h4>
                    <p>Your password was just changed. To ensure maximum account security, you can sign out of all <?php echo $otherSessionsCount; ?> other active sessions now.</p>
                </div>
            </div>
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="action" value="revoke_all_other_sessions">
                <button type="submit" class="btn btn-warning">
                    <i class="fas fa-arrow-right-from-bracket"></i> Log Out All Other Devices Now
                </button>
            </form>
        </div>
    <?php endif; ?>

    <!-- 2 Column Settings Grid -->
    <div class="profile-grid">
        
        <!-- Left: Personal Information -->
        <div class="settings-card">
            <div class="settings-card-header">
                <h2><i class="fas fa-id-card"></i> Personal Information</h2>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="fullname" class="form-control" value="<?php echo htmlspecialchars($profile['full_name'] ?? ''); ?>" required placeholder="e.g. Juan Dela Cruz">
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>" required placeholder="name@domain.com">
                </div>

                <div class="form-group" style="margin-top: 8px;">
                    <label>Resident Account Status</label>
                    <div style="padding: 10px 14px; background: rgba(0,0,0,0.03); border-radius: 10px; font-size: 13px; color: var(--text-muted); display: flex; justify-content: space-between; align-items: center;">
                        <span><i class="fas fa-check-circle text-success"></i> Active Citizen Account</span>
                        <small>Registered <?php echo $profile['created_at'] ? date('M Y', strtotime($profile['created_at'])) : '2026'; ?></small>
                    </div>
                </div>

                <div style="margin-top: auto; padding-top: 15px;">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-save"></i> Save Profile Details
                    </button>
                </div>
            </form>
        </div>

        <!-- Right: Change Password with Logout All Option -->
        <div class="settings-card">
            <div class="settings-card-header">
                <h2><i class="fas fa-key"></i> Change Password</h2>
            </div>
            <form method="POST" id="passwordForm">
                <input type="hidden" name="action" value="update_password">

                <div class="form-group">
                    <label>Current Password</label>
                    <div class="input-wrapper">
                        <input type="password" name="current_password" id="currPass" class="form-control" required placeholder="Enter current password">
                        <button type="button" class="password-toggle-btn" onclick="togglePass('currPass', this)" aria-label="Toggle password visibility"><i class="fas fa-eye"></i></button>
                    </div>
                </div>

                <div class="form-group">
                    <label>New Password</label>
                    <div class="input-wrapper">
                        <input type="password" name="new_password" id="newPass" class="form-control" required minlength="8" placeholder="At least 8 characters">
                        <button type="button" class="password-toggle-btn" onclick="togglePass('newPass', this)" aria-label="Toggle password visibility"><i class="fas fa-eye"></i></button>
                    </div>
                </div>

                <div class="form-group">
                    <label>Confirm New Password</label>
                    <div class="input-wrapper">
                        <input type="password" name="confirm_password" id="confPass" class="form-control" required minlength="8" placeholder="Retype new password">
                        <button type="button" class="password-toggle-btn" onclick="togglePass('confPass', this)" aria-label="Toggle password visibility"><i class="fas fa-eye"></i></button>
                    </div>
                </div>

                <!-- Checkbox Option: Log out of all devices -->
                <div class="security-option-box">
                    <label class="custom-checkbox-container">
                        <input type="checkbox" name="logout_all_devices" id="logout_all_devices" value="1" checked>
                        <div class="checkbox-content">
                            <span class="checkbox-title">
                                <i class="fas fa-shield-halved text-primary"></i>
                                Log out of all other devices
                                <span class="pill-recommended">Recommended</span>
                            </span>
                            <p class="checkbox-desc">
                                Automatically sign out of all other phones, tablets, or computers where your account is currently logged in.
                            </p>
                        </div>
                    </label>
                </div>

                <div style="margin-top: auto;">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-lock"></i> Change Password & Update Security
                    </button>
                </div>
            </form>
        </div>

    </div>

    <!-- Active Sessions & Logged-in Devices Tracker -->
    <div class="devices-card" id="devicesSection">
        <div class="devices-header">
            <div class="devices-header-left">
                <h2>
                    <i class="fas fa-laptop-house" style="color: #3762c8;"></i>
                    Where You're Logged In
                    <span class="device-count-badge"><?php echo count($activeSessions); ?> Device<?php echo count($activeSessions) === 1 ? '' : 's'; ?></span>
                </h2>
                <p>Track all active browsers and devices currently signed into your resident account. Log out of any unfamiliar sessions.</p>
            </div>
            <?php if ($otherSessionsCount > 0): ?>
                <div>
                    <button type="button" class="btn btn-danger-outline btn-sm" onclick="openLogoutAllModal()">
                        <i class="fas fa-arrow-right-from-bracket"></i> Log Out All Other Devices
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <div class="sessions-list">
            <?php if (empty($activeSessions)): ?>
                <div style="text-align:center; padding: 30px; color: var(--text-muted);">
                    <i class="fas fa-circle-notch fa-spin fa-2x"></i>
                    <p style="margin-top: 10px;">No active session records found.</p>
                </div>
            <?php else: ?>
                <?php foreach ($activeSessions as $session): ?>
                    <div class="session-item <?php echo $session['is_current'] ? 'current-device' : ''; ?>">
                        <div class="session-left">
                            <div class="session-icon">
                                <i class="<?php echo getDeviceIcon($session['device_type'] ?? 'Desktop', $session['platform'] ?? 'Windows'); ?>"></i>
                            </div>
                            <div class="session-details">
                                <div class="session-title">
                                    <span><?php echo htmlspecialchars(($session['platform'] ?? 'Device') . ' · ' . ($session['browser'] ?? 'Web Browser')); ?></span>
                                    <?php if ($session['is_current']): ?>
                                        <span class="badge-current"><i class="fas fa-circle-check"></i> This Device</span>
                                    <?php else: ?>
                                        <span class="badge-active-session"><i class="fas fa-circle-dot" style="font-size:8px; color:#10b981;"></i> Active</span>
                                    <?php endif; ?>
                                </div>
                                <div class="session-meta">
                                    <span><i class="fas fa-location-dot"></i> <?php echo htmlspecialchars($session['location'] ?? 'Quezon City, PH'); ?></span>
                                    <span><i class="fas fa-network-wired"></i> IP: <?php echo htmlspecialchars($session['ip_address'] ?? '127.0.0.1'); ?></span>
                                    <span><i class="fas fa-clock"></i> <?php echo $session['is_current'] ? 'Active now' : 'Last active ' . timeAgo($session['last_activity']); ?></span>
                                    <span><i class="fas fa-calendar-alt"></i> Signed in <?php echo date('M j, Y', strtotime($session['created_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="session-right">
                            <?php if ($session['is_current']): ?>
                                <span style="font-size: 12px; font-weight: 600; color: #10b981; padding: 6px 12px;">
                                    <i class="fas fa-shield-check"></i> Current Session
                                </span>
                            <?php else: ?>
                                <button type="button" class="btn btn-danger-outline btn-sm" onclick="openRevokeSingleModal(<?php echo (int)$session['id']; ?>, '<?php echo htmlspecialchars(addslashes($session['platform'] . ' · ' . $session['browser'])); ?>')">
                                    <i class="fas fa-sign-out-alt"></i> Log Out
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="security-tip-footer">
            <i class="fas fa-circle-info"></i>
            <span><strong>Security Tip:</strong> If you notice a device, IP address, or location that you don't recognize, change your password above immediately and choose <strong>"Log out of all other devices"</strong> to secure your account.</span>
        </div>
    </div>

</main>

<!-- Modal: Confirm Single Device Revocation -->
<div class="modal-overlay" id="revokeSingleModal">
    <div class="modal-box">
        <div class="modal-icon-danger">
            <i class="fas fa-sign-out-alt"></i>
        </div>
        <h3>Log Out Device?</h3>
        <p id="revokeDeviceText">Are you sure you want to log out of this device? The user on that device will be signed out immediately.</p>
        <form method="POST" id="revokeSingleForm">
            <input type="hidden" name="action" value="revoke_session">
            <input type="hidden" name="session_id" id="revokeSessionId" value="0">
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('revokeSingleModal')">Cancel</button>
                <button type="submit" class="btn btn-danger-outline" style="background:#ef4444; color:white;">Yes, Log Out Device</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Confirm Log Out of All Other Devices -->
<div class="modal-overlay" id="logoutAllModal">
    <div class="modal-box">
        <div class="modal-icon-danger">
            <i class="fas fa-arrow-right-from-bracket"></i>
        </div>
        <h3>Log Out All Other Devices?</h3>
        <p>This will sign your account out of all other phones, tablets, and computers. Only this current browser session will remain logged in.</p>
        <form method="POST">
            <input type="hidden" name="action" value="revoke_all_other_sessions">
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('logoutAllModal')">Cancel</button>
                <button type="submit" class="btn btn-danger-outline" style="background:#ef4444; color:white;">Yes, Log Out All Others</button>
            </div>
        </form>
    </div>
</div>

<script>
function togglePass(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

function openRevokeSingleModal(sessionId, deviceName) {
    document.getElementById('revokeSessionId').value = sessionId;
    document.getElementById('revokeDeviceText').textContent = 'Are you sure you want to log out ' + deviceName + '? That session will be disconnected immediately.';
    document.getElementById('revokeSingleModal').classList.add('active');
}

function openLogoutAllModal() {
    document.getElementById('logoutAllModal').classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

// Close modals on escape key or clicking backdrop
window.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal('revokeSingleModal');
        closeModal('logoutAllModal');
    }
});

document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});
</script>

</body>
</html>
