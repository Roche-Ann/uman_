<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isEmployee()) {
        header('Location: utilities_dashboard.php');
    } else {
        header('Location: citizen.php');
    }
    exit();
}

$error = '';
$warning = '';
$success = '';

if (isset($_GET['registered']) && $_GET['registered'] == '1') {
    $success = 'Account created successfully. Please log in to continue.';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $result = loginUser($email, $password);
        
        if ($result['success']) {
            // ==========================================
            // START OTP INTEGRATION
            // ==========================================
            
            // 1. Get the User ID and Name. 
            $userId = $result['user_id'] ?? $_SESSION['user_id'] ?? null;
            $fullName = $result['full_name'] ?? 'User';
            $userType = $result['user_type'];

            if ($userId) {
                ensureAuthSchema();

                // Check if device is trusted
                $deviceToken = $_COOKIE['remember_device_token'] ?? '';
                $deviceTrusted = false;
                if (!empty($deviceToken)) {
                    $tokenHash = hash('sha256', $deviceToken);
                    global $pdo;
                    $stmt = $pdo->prepare('SELECT id FROM trusted_devices WHERE user_id = :uid AND device_token = :token AND expires_at > UTC_TIMESTAMP()');
                    $stmt->execute([
                        ':uid' => $userId,
                        ':token' => $tokenHash
                    ]);
                    if ($stmt->fetch()) {
                        $deviceTrusted = true;
                    }
                }

                if ($deviceTrusted) {
                    // Log the user in officially, bypassing OTP
                    $_SESSION['user_id']   = $userId;
                    $_SESSION['user_type'] = $userType;
                    $_SESSION['user_name'] = $fullName;
                    $_SESSION['full_name'] = $fullName;
                    $_SESSION['user_email']= $email;
                    $_SESSION['logged_in'] = true;
                    $_SESSION['just_logged_in'] = true;

                    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $stmt->execute([$userId]);

                    // Redirect based on user_type (role)
                    if ($userType === 'employee') {
                        header('Location: utilities_dashboard.php');
                    } else {
                        header('Location: citizen.php');
                    }
                    exit();
                }

                // 2. Generate OTP and Expiry
                $otp = str_pad((string)mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
                $otpHash = password_hash($otp, PASSWORD_DEFAULT);
                $expiresAt = (new DateTime('+10 minutes', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

                // 3. Database Operations
                global $pdo; 
                
                // Invalidate old OTPs
                $stmt = $pdo->prepare('UPDATE otps SET used = 1 WHERE user_id = :uid AND used = 0');
                $stmt->execute([':uid' => $userId]);

                // Store new OTP
                $stmt = $pdo->prepare('INSERT INTO otps (user_id, otp_hash, expires_at, used) VALUES (:uid, :hash, :exp, 0)');
                $stmt->execute([
                    ':uid' => $userId,
                    ':hash' => $otpHash,
                    ':exp' => $expiresAt,
                ]);

                // 4. Send the OTP email via Gmail SMTP
                $mailResult = sendOtpEmail($email, $fullName, $otp, 10);
                if (!$mailResult['success']) {
                    error_log('OTP mail error: ' . ($mailResult['error'] ?? 'unknown'));
                }

                // 5. Setup Pending Session (includes dev_otp & mail_failed flag for fallback UI)
                $_SESSION['pending_login'] = [
                    'id'          => $userId,
                    'name'        => $fullName,
                    'email'       => $email,
                    'role'        => $userType,
                    'dev_otp'     => $otp,
                    'mail_failed' => !$mailResult['success'],
                    'mail_error'  => $mailResult['error'] ?? null,
                ];

                unset($_SESSION['logged_in']);
                unset($_SESSION['user_id']);

                // 6. Redirect to OTP verification page
                header('Location: verify-otp.php');
                exit();
            } else {
                $error = "System Error: Could not retrieve User ID for OTP.";
            }
            // ==========================================
            // END OTP INTEGRATION
            // ==========================================
        } else {
            $error = $result['message'];
            if (isset($result['attempts_left']) && $result['attempts_left'] > 0) {
                $warning = "Warning: {$result['attempts_left']} attempt(s) remaining.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>
        document.documentElement.classList.remove('dark-theme');
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LGU | Login</title>
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&family=Urbanist:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/responsive.css">

    <style>
        /* ---------- DESIGN TOKENS (from the new UI) ---------- */
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
            scroll-padding-top: 80px;
        }

        body {
            font-family: var(--font-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: url("assets/images/cityhall.jpeg") center/cover no-repeat fixed;
            position: relative;
        }

        /* Background overlay with blur */
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

        /* Ensure content sits above the overlay */
        .civic-navigation,
        .login-section,
        .civic-footer {
            position: relative;
            z-index: 1;
        }

        /* ---------- GLASS NAVIGATION ---------- */
        .civic-navigation {
            position: fixed;
            top: 0;
            width: 100%;
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(11, 61, 145, 0.08);
            z-index: 1000;
            padding: 1rem 0;
            transition: var(--transition-smooth);
        }

        .civic-navigation.scrolled {
            padding: 0.75rem 0;
            box-shadow: var(--shadow-persistent);
        }

        .nav-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo-entity {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
        }

        .logo-marker {
            background: none !important;
            border-radius: 0 !important;
        }

        .logo-only {
            width: 58px;
            height: 58px;
            object-fit: contain;
        }

        .logo-text {
            display: flex;
            flex-direction: column;
        }

        .logo-primary {
            font-family: var(--font-heading);
            font-weight: 700;
            font-size: 1.5rem;
            background: linear-gradient(135deg, var(--civic-sapphire), var(--utility-teal));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
        }

        .logo-secondary {
            font-size: 0.75rem;
            color: var(--municipal-slate);
            opacity: 0.7;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 2.5rem;
            list-style: none;
        }

        .nav-link-item {
            position: relative;
        }

        .nav-link {
            font-family: var(--font-heading);
            font-weight: 600;
            color: var(--municipal-slate);
            text-decoration: none;
            font-size: 0.95rem;
            padding: 0.5rem 0;
            position: relative;
            transition: var(--transition-smooth);
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--civic-sapphire), var(--utility-teal));
            transition: var(--transition-smooth);
            border-radius: var(--radius-pill);
        }

        .nav-link:hover {
            color: var(--civic-sapphire);
        }

        .nav-link:hover::after {
            width: 100%;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        /* Buttons */
        .civic-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.75rem;
            border-radius: var(--radius-pill);
            font-family: var(--font-heading);
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: var(--transition-smooth);
            position: relative;
            overflow: hidden;
        }

        .civic-button::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s;
        }

        .civic-button:hover::after {
            left: 100%;
        }

        .button-primary {
            background: linear-gradient(135deg, var(--civic-sapphire), var(--utility-teal));
            color: white;
            box-shadow: 0 8px 24px rgba(11, 61, 145, 0.2);
        }

        .button-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(11, 61, 145, 0.3);
        }

        .button-secondary {
            background: white;
            color: var(--civic-sapphire);
            border: 2px solid rgba(11, 61, 145, 0.1);
        }

        .button-secondary:hover {
            border-color: var(--civic-sapphire);
            background: rgba(11, 61, 145, 0.02);
            transform: translateY(-2px);
        }

        /* Mobile menu toggle */
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--civic-sapphire);
            cursor: pointer;
        }

        /* ---------- LOGIN CARD (glassmorphism) ---------- */
        .login-section {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 120px 1.5rem 60px; /* top padding for fixed nav */
            min-height: 100vh;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.795); /* glass effect */
            border-radius: var(--radius-modern);
            box-shadow: var(--shadow-elevated);
            padding: 2.5rem;
            width: 100%;
            max-width: 460px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            animation: slideDown 0.7s cubic-bezier(0.34, 1.56, 0.64, 1);
            opacity: 0;  /* start invisible, animation fills forwards */
            animation-fill-mode: forwards;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-60px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header .logo-only {
            width: 80px;
            height: 80px;
            margin-bottom: 1rem;
        }

        .login-header h2 {
            font-family: var(--font-heading);
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--civic-sapphire), var(--utility-teal));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .login-header p {
            color: rgba(47, 72, 88, 0.7);
            font-size: 0.95rem;
        }

        /* Form elements */
        .input-box {
            margin-bottom: 1.5rem;
        }

        .input-box label {
            display: block;
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--municipal-slate);
            margin-bottom: 0.3rem;
        }

        .input-box input {
            width: 100%;
            padding: 0.85rem 1rem;
            border: 2px solid rgba(11, 61, 145, 0.1);
            border-radius: var(--radius-soft);
            font-family: var(--font-primary);
            font-size: 1rem;
            transition: var(--transition-smooth);
            background: white;
        }

        .input-box input:focus {
            outline: none;
            border-color: var(--civic-sapphire);
            box-shadow: 0 0 0 4px rgba(11, 61, 145, 0.1);
        }

        .show-password-container {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: -0.5rem 0 1.5rem 0;
        }

        .show-password-container input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--civic-sapphire);
            cursor: pointer;
        }

        .show-password-container label {
            color: var(--municipal-slate);
            font-size: 0.9rem;
            cursor: pointer;
            user-select: none;
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
            margin-bottom: 1.5rem;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(11, 61, 145, 0.3);
        }

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
            background: rgba(42, 157, 143, 0.12);
            border-left: 4px solid var(--progress-emerald);
            color: #1b6b60;
            padding: 0.75rem 1rem;
            border-radius: var(--radius-soft);
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .attempt-warning {
            background: rgba(255, 158, 0, 0.1);
            border-left: 4px solid var(--insight-amber);
            color: #b45b0a;
            padding: 0.75rem 1rem;
            border-radius: var(--radius-soft);
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .bottom-links {
            text-align: center;
            margin-top: 1.5rem;
        }

        .bottom-links p {
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .forgot-link, .link {
            color: var(--civic-sapphire);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition-smooth);
        }

        .forgot-link:hover, .link:hover {
            color: var(--utility-teal);
            text-decoration: underline;
        }

        .small-text {
            color: rgba(47, 72, 88, 0.7);
        }

        /* ---------- FOOTER ---------- */
        .civic-footer {
            background: var(--municipal-slate);
            color: white;
            padding: 4rem 0 2rem;
            margin-top: auto;
        }

        .footer-grid {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 2rem;
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .footer-brand .footer-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .footer-logo-icon {
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--civic-sapphire);
            font-size: 1.25rem;
        }

        .footer-logo-text {
            font-family: var(--font-heading);
            font-size: 1.25rem;
            font-weight: 700;
        }

        .footer-description {
            opacity: 0.8;
            font-size: 0.9rem;
            line-height: 1.6;
        }

        .footer-heading {
            font-family: var(--font-heading);
            font-size: 1.125rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }

        .footer-links {
            list-style: none;
            padding: 0;
        }

        .footer-grid > div:not(:first-child) {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .footer-links li {
            margin-bottom: 0.75rem;
        }

        .footer-links a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: var(--transition-smooth);
            font-size: 0.9rem;
        }

        .footer-links a:hover {
            color: white;
            padding-left: 4px;
        }

        .footer-bottom {
            max-width: 1280px;
            margin: 0 auto;
            padding: 2rem 2rem 0;
            text-align: center;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .footer-copyright {
            opacity: 0.7;
            font-size: 0.875rem;
        }

        /* ---------- RESPONSIVE ---------- */
        @media (max-width: 992px) {
            .menu-toggle {
                display: block;
            }

            .nav-actions {
                display: none;
            }

            .nav-links {
                position: fixed;
                top: 80px;
                right: -100%;
                width: 80%;
                height: calc(100vh - 80px);
                background: white;
                flex-direction: column;
                justify-content: flex-start;
                padding: 2rem;
                box-shadow: -10px 0 30px rgba(0,0,0,0.1);
                transition: 0.4s ease-in-out;
                z-index: 1000;
            }

            .nav-links.active {
                right: 0;
            }

            .nav-links .mobile-nav-actions {
                display: flex;
                flex-direction: column;
                gap: 1rem;
                width: 100%;
                margin-top: 2rem;
            }
        }

        @media (max-width: 768px) {
            body, .dark-theme body {
                background: url("assets/images/cityhall.jpeg") center/cover no-repeat !important;
            }
            body::before {
                content: "" !important;
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                width: 100% !important;
                height: 100% !important;
                backdrop-filter: blur(6px) !important;
                background: rgba(255, 255, 255, 0.5) !important; /* white overlay */
                z-index: -1 !important;
                display: block !important;
            }

            .show-password-container input[type="checkbox"] {
                width: 18px !important;
                height: 18px !important;
                min-height: 0 !important;
                padding: 0 !important;
                margin: 0 !important;
                display: inline-block !important;
                -webkit-appearance: checkbox !important;
                appearance: checkbox !important;
            }

            .nav-container {
                padding: 0 1rem;
            }

            .login-card {
                padding: 2rem 1.5rem;
            }

            .login-header h2 {
                font-size: 1.75rem;
            }

            .footer-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
        }

        /* micro-interactions */
        .hover-lift {
            transition: var(--transition-smooth);
        }
        .hover-lift:hover {
            transform: translateY(-4px);
        }
    </style>
</head>
<body>
    <!--  NAVIGATION  -->
    <nav class="civic-navigation" id="mainNav">
        <div class="nav-container">
            <a href="home.php" class="logo-entity">
                <div class="logo-marker">
                    <img src="assets/images/logocityhall.png" alt="LGU Logo" class="logo-only">
                </div>
                <div class="logo-text">
                    <span class="logo-primary">Utilities Management System</span>
                    <span class="logo-secondary"><span class="title-gradient">uMAN</span></span>
                </div>
            </a>

            <button class="menu-toggle" id="mobileMenuBtn">
                <i class="fas fa-bars"></i>
            </button>

            <ul class="nav-links" id="navLinksList">
                <li class="nav-link-item"><a href="home.php" class="nav-link">Home</a></li>
                <li class="nav-link-item"><a href="#hub" class="nav-link">Citizen Hub</a></li>
                <li class="nav-link-item"><a href="#modules" class="nav-link">Utilities</a></li>
                <li class="nav-link-item"><a href="#methodology" class="nav-link">History</a></li>
                <li class="nav-link-item"><a href="#analytics" class="nav-link">Contacts</a></li>
                <!-- Mobile actions (shown only in mobile menu) -->
                <li class="mobile-nav-actions" style="display: none;">
                    <a href="create.php" class="civic-button button-secondary" style="width:100%; justify-content:center;">Register</a>
                    <a href="login.php" class="civic-button button-primary" style="width:100%; justify-content:center;">Login</a>
                </li>
            </ul>

            <div class="nav-actions">
                <a href="create.php" class="civic-button button-secondary">Register</a>
                <a href="login.php" class="civic-button button-primary">Login</a>
            </div>
        </div>
    </nav>

    <!-- LOGIN SECTION with card -->
    <section class="login-section">
        <div class="login-card">
            <div class="login-header">
                <img src="assets/images/logocityhall.png" alt="LGU Logo" class="logo-only">
                <h2>Welcome Back</h2>
                <p>Secure access to community maintenance services.</p>
            </div>

            <form method="POST" action="">
                <?php if ($error): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <?php if ($warning): ?>
                    <div class="attempt-warning"><?php echo htmlspecialchars($warning); ?></div>
                <?php endif; ?>

                <div class="input-box">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="name@lgu.gov.ph" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                </div>

                <div class="input-box">
                    <label>Password</label>
                    <input type="password" id="login-password" name="password" placeholder="•••••" required>
                </div>

                <div class="show-password-container">
                    <input type="checkbox" id="toggle-password">
                    <label for="toggle-password">Show password</label>
                </div>

                <button type="submit" class="btn-primary">Sign In</button>

                <div class="bottom-links">
                    <p class="small-text">
                        <a href="forgot.php" class="forgot-link">Forgot Password?</a>
                    </p>
                    <p class="small-text">Don't have an account?
                        <a href="create.php" class="link">Create one</a>
                    </p>
                </div>
            </form>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="civic-footer">
        <div class="footer-grid">
            <div class="footer-brand">
                <div class="footer-logo">
                    <div class="footer-logo-icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <span class="footer-logo-text">Quezon City<br><small>Web-Based Utilities Management</small></span>
                </div>
                <p class="footer-description">
                    We streamline utility operations and provide easy access to monitor your consumption and service requests.
                </p>
            </div>
            <div>
                <h4 class="footer-heading">Quick Links</h4>
                <ul class="footer-links">
                    <li><a href="home.php">Home</a></li>
                    <li><a href="#modules">Utilities</a></li>
                    <li><a href="#methodology">History</a></li>
                    <li><a href="#analytics">Contacts</a></li>
                </ul>
            </div>
            <div>
                <h4 class="footer-heading">Core Services</h4>
                <ul class="footer-links">
                    <li><a href="#modules">Asset Inventory</a></li>
                    <li><a href="#modules">Incident Reporting</a></li>
                    <li><a href="#modules">Maintenance Dispatch</a></li>
                    <li><a href="#modules">Energy Monitoring</a></li>
                </ul>
            </div>
            <div>
                <h4 class="footer-heading">Contact Us</h4>
                <ul class="footer-links">
                    <li>Email: <a href="mailto:contactus@quezoncity.gov.ph">contactus@quezoncity.gov.ph</a></li>
                    <li>Phone: <a href="tel:+63212345678">+63 2 1234 5678</a></li>
                    <li><a href="https://maps.google.com/?cid=15606250877574773486" target="_blank">Location: Quezon City Hall</a></li>
                </ul>
            </div>
            <div>
                <h4 class="footer-heading">Legal & Compliance</h4>
                <ul class="footer-links">
                    <li><a href="privacy.php">Privacy Policy</a></li>
                    <li><a href="terms.php">Terms & Conditions</a></li>
                    <li><a href="dataprivacy.php">Data Privacy Notice</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="footer-copyright">
                © 2026 Utilities Management System · All Rights Reserved
            </div>
        </div>
    </footer>

    <!-- JavaScript for mobile menu and show/hide password -->
    <script>
        // Mobile menu toggle
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const navLinksList = document.getElementById('navLinksList');
        const mobileActionsGroup = document.querySelector('.mobile-nav-actions');

        if (mobileMenuBtn && navLinksList) {
            mobileMenuBtn.addEventListener('click', () => {
                navLinksList.classList.toggle('active');
                const icon = mobileMenuBtn.querySelector('i');
                if (navLinksList.classList.contains('active')) {
                    icon.className = 'fas fa-times';
                    if (window.innerWidth <= 992 && mobileActionsGroup) {
                        mobileActionsGroup.style.display = 'flex';
                    }
                } else {
                    icon.className = 'fas fa-bars';
                    if (mobileActionsGroup) mobileActionsGroup.style.display = 'none';
                }
            });

            // Close menu when a nav link is clicked
            document.querySelectorAll('.nav-link').forEach(link => {
                link.addEventListener('click', () => {
                    navLinksList.classList.remove('active');
                    mobileMenuBtn.querySelector('i').className = 'fas fa-bars';
                    if (mobileActionsGroup) mobileActionsGroup.style.display = 'none';
                });
            });

            window.addEventListener('resize', () => {
                if (window.innerWidth > 992) {
                    navLinksList.classList.remove('active');
                    mobileMenuBtn.querySelector('i').className = 'fas fa-bars';
                    if (mobileActionsGroup) mobileActionsGroup.style.display = 'none';
                }
            });
        }

        // Show/Hide Password
        (function() {
            const checkbox = document.getElementById('toggle-password');
            const passwordInput = document.getElementById('login-password');
            if (checkbox && passwordInput) {
                checkbox.addEventListener('change', function() {
                    passwordInput.type = this.checked ? 'text' : 'password';
                });
            }
        })();
    </script>
    <!-- GLOBAL SPINNER -->
    <div id="global-spinner" class="global-spinner-overlay">
        <div class="spinner"></div>
    </div>
    <style>
    .global-spinner-overlay {
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        z-index: 999999;
        display: flex;
        justify-content: center;
        align-items: center;
        opacity: 1;
        visibility: visible;
        transition: opacity 0.25s ease, visibility 0.25s ease;
    }
    .dark-theme .global-spinner-overlay {
        background: rgba(15, 23, 42, 0.8);
    }
    .global-spinner-overlay.hidden {
        opacity: 0;
        visibility: hidden;
    }
    .global-spinner-overlay .spinner {
        width: 48px;
        height: 48px;
        border: 4px solid rgba(55, 98, 200, 0.2);
        border-top-color: #3762c8;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }
    .dark-theme .global-spinner-overlay .spinner {
        border: 4px solid rgba(99, 132, 210, 0.2);
        border-top-color: #6384d2;
    }
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    </style>
    <script>
    window.addEventListener('load', function() {
        const spinner = document.getElementById('global-spinner');
        if (spinner) {
            setTimeout(() => spinner.classList.add('hidden'), 100);
        }
    });
    window.addEventListener('beforeunload', function() {
        const spinner = document.getElementById('global-spinner');
        if (spinner) spinner.classList.remove('hidden');
    });
    </script>
</body>
</html>