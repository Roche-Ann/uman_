<?php
// Ensure auth.php is loaded so we have database connection and session handling
require_once 'includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';

// Assuming your auth.php provides a global $pdo or equivalent database connection variable.
global $pdo;
ensureAuthSchema();

// Handle cancel/back to login - clear pending session
if (isset($_GET['cancel']) || isset($_GET['back'])) {
    unset($_SESSION['pending_login']);
    header('Location: login.php');
    exit();
}

// Check if there's a pending login
if (!isset($_SESSION['pending_login'])) {
    header('Location: login.php');
    exit();
}

$pendingUser = $_SESSION['pending_login'];
$error = '';
$resendMessage = '';

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['otp'])) {
    $otp = trim($_POST['otp'] ?? '');
    
    if (empty($otp) || !ctype_digit($otp) || strlen($otp) !== 6) {
        $error = 'Please enter a valid 6-digit OTP code.';
    } else {
        // Find valid OTP for this user in the new `otps` table
        $stmt = $pdo->prepare('SELECT * FROM otps WHERE user_id = :uid AND used = 0 AND expires_at > UTC_TIMESTAMP() ORDER BY id DESC LIMIT 1');
        $stmt->execute([':uid' => $pendingUser['id']]);
        $otpRow = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$otpRow) {
            $error = 'OTP has expired or is invalid. Please request a new one.';
        } elseif (!password_verify($otp, $otpRow['otp_hash'])) {
            $error = 'Invalid OTP code. Please try again.';
        } else {
            // Mark OTP as used
            $pdo->prepare('UPDATE otps SET used = 1 WHERE id = :id')->execute([':id' => $otpRow['id']]);
            
            // Log the user in officially
            $_SESSION['user_id']   = $pendingUser['id'];
            $_SESSION['user_type'] = $pendingUser['role'];
            $_SESSION['user_name'] = $pendingUser['name'];
            $_SESSION['full_name'] = $pendingUser['name'];
            $_SESSION['user_email']= $pendingUser['email'];
            $_SESSION['logged_in'] = true;

            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$pendingUser['id']]);
            
            unset($_SESSION['pending_login']);
            
            // Redirect based on user_type (role)
            if ($pendingUser['role'] === 'employee') {
                header('Location: utilities_dashboard.php');
            } else {
                header('Location: citizen.php');
            }
            exit();
        }
    }
}

// Handle resend OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend'])) {
    // Generate new OTP
    $otp = str_pad((string)mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $otpHash = password_hash($otp, PASSWORD_DEFAULT);
    $expiresAt = (new DateTime('+10 minutes', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

    // Invalidate old OTPs for this user
    $pdo->prepare('UPDATE otps SET used = 1 WHERE user_id = :uid AND used = 0')->execute([
        ':uid' => $pendingUser['id'],
    ]);

    // Store new OTP in database
    $insert = $pdo->prepare('INSERT INTO otps (user_id, otp_hash, expires_at, used) VALUES (:uid, :hash, :exp, 0)');
    $insert->execute([
        ':uid' => $pendingUser['id'],
        ':hash' => $otpHash,
        ':exp' => $expiresAt,
    ]);

    // Send OTP via Gmail SMTP
    $mailResult = sendOtpEmail($pendingUser['email'], $pendingUser['name'], $otp, 10);
    if ($mailResult['success']) {
        $resendMessage = 'A new verification code has been sent to your email.';
    } else {
        error_log('OTP resend mail error: ' . ($mailResult['error'] ?? 'unknown'));
        $error = 'Failed to send email. Please try again later.';
    }
}

// =============================================
// Fetch the latest valid OTP expiry for countdown
// (Moved here so it picks up new OTP after resend)
// =============================================
$stmt = $pdo->prepare('SELECT expires_at FROM otps WHERE user_id = :uid AND used = 0 AND expires_at > UTC_TIMESTAMP() ORDER BY id DESC LIMIT 1');
$stmt->execute([':uid' => $pendingUser['id']]);
$otpData = $stmt->fetch(PDO::FETCH_ASSOC);
$expiryTimestamp = $otpData ? strtotime($otpData['expires_at']) : 0; // 0 means no valid OTP
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP | LGU Portal</title>
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&family=Urbanist:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/responsive.css">

    <style>
        /* DESIGN TOKENS (identical to login/register) */
        :root {
            --civic-sapphire: #0B3D91;
            --utility-teal: #00A896;
            --insight-amber: #FF9E00;
            --municipal-slate: #2F4858;
            --resident-sand: #F4F1DE;
            --infrastructure-gray: #E0E0E2;
            --progress-emerald: #2A9D8F;
            --alert-coral: #E76F51;

            --font-primary: 'Public Sans', system-ui, -apple-system, sans-serif;
            --font-heading: 'Urbanist', 'Segoe UI', sans-serif;
            --font-mono: 'Fira Code', 'Cascadia Code', monospace;

            --shadow-gentle: 0 8px 32px rgba(11, 61, 145, 0.08);
            --shadow-elevated: 0 16px 48px rgba(11, 61, 145, 0.12);
            --shadow-persistent: 0 4px 24px rgba(11, 61, 145, 0.06);

            --radius-modern: 20px;
            --radius-pill: 9999px;
            --radius-soft: 12px;

            --transition-smooth: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            --transition-bounce: all 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: var(--font-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
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

        .verify-card {
            position: relative;
            z-index: 1;
            background: rgba(255, 255, 255, 0.795);
            border-radius: var(--radius-modern);
            box-shadow: var(--shadow-elevated);
            padding: 2.5rem;
            width: 100%;
            max-width: 480px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            animation: slideDown 0.7s cubic-bezier(0.34, 1.56, 0.64, 1);
            opacity: 0;
            animation-fill-mode: forwards;
            margin: 1.5rem;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-60px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .verify-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .verify-header .logo-only {
            width: 80px;
            height: 80px;
            margin-bottom: 1rem;
        }

        .verify-header h2 {
            font-family: var(--font-heading);
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--civic-sapphire), var(--utility-teal));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .verify-header p {
            color: rgba(47, 72, 88, 0.7);
            font-size: 0.95rem;
        }

        /* OTP input styling */
        .otp-input {
            width: 100%;
            padding: 0.85rem 1rem;
            border: 2px solid rgba(11, 61, 145, 0.1);
            border-radius: var(--radius-soft);
            font-family: var(--font-mono);
            font-size: 1.5rem;
            text-align: center;
            letter-spacing: 0.5rem;
            transition: var(--transition-smooth);
            background: white;
        }

        .otp-input:focus {
            outline: none;
            border-color: var(--civic-sapphire);
            box-shadow: 0 0 0 4px rgba(11, 61, 145, 0.1);
        }

        .otp-input::placeholder {
            letter-spacing: 0.25rem;
            font-size: 1rem;
            color: rgba(47, 72, 88, 0.4);
        }

        /* Message styling */
        .error-message {
            background: rgba(231, 111, 81, 0.1);
            border-left: 4px solid var(--alert-coral);
            color: var(--alert-coral);
            padding: 0.75rem 1rem;
            border-radius: var(--radius-soft);
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .success-message {
            background: rgba(40, 167, 69, 0.1);
            border-left: 4px solid #28a745;
            color: #28a745;
            padding: 0.75rem 1rem;
            border-radius: var(--radius-soft);
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .info-message {
            background: rgba(23, 162, 184, 0.1);
            border-left: 4px solid #17a2b8;
            color: #17a2b8;
            padding: 0.75rem 1rem;
            border-radius: var(--radius-soft);
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .countdown-timer {
            text-align: center;
            margin-top: -0.5rem;
            margin-bottom: 1rem;
            font-size: 1rem;
            font-weight: 600;
            color: var(--civic-sapphire);
        }
        .countdown-timer.expired {
            color: var(--alert-coral);
        }

        .btn-primary {
            width: 100%;
            padding: 0.9rem;
            background: linear-gradient(135deg, var(--civic-sapphire), var(--utility-teal));
            color: white;
            border: none;
            border-radius: var(--radius-pill);
            font-family: var(--font-heading);
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition-smooth);
            box-shadow: 0 8px 24px rgba(11, 61, 145, 0.2);
            margin-top: 1.5rem;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(11, 61, 145, 0.3);
        }

        .resend-form {
            margin-top: 1rem;
            text-align: center;
        }

        .resend-btn {
            background: transparent;
            border: none;
            color: var(--civic-sapphire);
            text-decoration: underline;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: var(--transition-smooth);
        }

        .resend-btn:hover {
            color: var(--utility-teal);
        }

        .bottom-links {
            text-align: center;
            margin-top: 1rem;
        }

        .link {
            color: var(--civic-sapphire);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition-smooth);
        }

        .link:hover {
            color: var(--utility-teal);
            text-decoration: underline;
        }

        .small-text {
            color: rgba(47, 72, 88, 0.7);
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <!-- VERIFY OTP CARD -->
    <div class="verify-card">
        <div class="verify-header">
            <img src="assets/images/logocityhall.png" alt="LGU Logo" class="logo-only">
            <h2>Verify Your Email</h2>
            <p>Enter the 6‑digit code sent to <strong><?php echo htmlspecialchars($pendingUser['email']); ?></strong></p>
        </div>

        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif ($resendMessage): ?>
            <div class="success-message"><?php echo htmlspecialchars($resendMessage); ?></div>
        <?php else: ?>
            <div class="info-message">Check your email for the verification code.</div>
        <?php endif; ?>

        <!-- Countdown Timer Display -->
        <div class="countdown-timer" id="countdownTimer"></div>

        <form action="" method="POST">
            <input 
                name="otp" 
                type="text" 
                class="otp-input" 
                placeholder="000000" 
                required 
                maxlength="6" 
                pattern="[0-9]{6}"
                inputmode="numeric"
                autocomplete="one-time-code"
                autofocus>

            <button class="btn-primary" type="submit" id="verifyBtn">Verify Code</button>
        </form>

        <form action="" method="POST" class="resend-form">
            <input type="hidden" name="resend" value="1">
            <button type="submit" class="resend-btn" id="resendBtn">Resend Code</button>
        </form>

        <div class="bottom-links">
            <p class="small-text">
                <a href="verify-otp.php?cancel=1" class="link">Back to Login</a>
            </p>
        </div>
    </div>

    <script>
        // Pass the expiry timestamp from PHP to JavaScript
        const expiryTimestamp = <?php echo $expiryTimestamp; ?>; // Unix timestamp (seconds)
        const countdownElement = document.getElementById('countdownTimer');
        const verifyButton = document.getElementById('verifyBtn');
        const resendButton = document.getElementById('resendBtn');

        function updateCountdown() {
            const now = Math.floor(Date.now() / 1000); // current UTC seconds
            let remainingSeconds = expiryTimestamp - now;

            if (remainingSeconds <= 0) {
                countdownElement.textContent = 'Code expired';
                countdownElement.classList.add('expired');
                verifyButton.disabled = true;
                verifyButton.style.opacity = 0.5;
                verifyButton.style.cursor = 'not-allowed';
                // Optionally disable resend? No, resend should remain active.
                return;
            }

            // Remove expired class if previously added
            countdownElement.classList.remove('expired');
            verifyButton.disabled = false;
            verifyButton.style.opacity = 1;
            verifyButton.style.cursor = 'pointer';

            const minutes = Math.floor(remainingSeconds / 60);
            const seconds = remainingSeconds % 60;
            const formatted = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            countdownElement.textContent = `Code expires in ${formatted}`;
        }

        // Update immediately and then every second
        updateCountdown();
        setInterval(updateCountdown, 1000);
    </script>

    <!-- Auto-format OTP input (numbers only) -->
    <script>
        document.querySelector('.otp-input')?.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    </script>
</body>
</html>