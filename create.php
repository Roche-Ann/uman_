<?php
// create.php - REMOVE session_start() from here too
require_once 'includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . ($_SESSION['user_type'] == 'employee' ? 'utilities_dashboard.php' : 'citizen.php'));
    exit();
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $terms_agreed = isset($_POST['terms_agreed']) ? true : false; // Added Terms Check
    
    // Validation
    if (empty($full_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (!$terms_agreed) {
        $error = 'You must read and agree to the Terms & Conditions and Data Privacy Notice.';
    } else {
        $result = registerUser($full_name, $email, $password);
        
        if ($result['success']) {
            // Account created — send them to login (OTP is required there).
            header('Location: login.php?registered=1');
            exit();
        } else {
            $error = $result['message'];
        }
    }
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
    <title>Create Account | LGU Portal</title>
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&family=Urbanist:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/responsive.css">

    <style>
        /* ---------- DESIGN TOKENS ---------- */
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

        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
        html { scroll-behavior: smooth; scroll-padding-top: 80px; }
        body { font-family: var(--font-primary); min-height: 100vh; display: flex; flex-direction: column; background: url("assets/images/cityhall.jpeg") center/cover no-repeat fixed; position: relative; }
        body::before { content: ""; position: fixed; top: 0; left: 0; width: 100%; height: 100%; backdrop-filter: blur(6px); background: rgba(0, 0, 0, 0.35); z-index: 0; }
        .civic-navigation, .register-section, .civic-footer { position: relative; z-index: 1; }

        /* ---------- GLASS NAVIGATION ---------- */
        .civic-navigation { position: fixed; top: 0; width: 100%; background: rgba(255, 255, 255, 0.94); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border-bottom: 1px solid rgba(11, 61, 145, 0.08); z-index: 1000; padding: 1rem 0; transition: var(--transition-smooth); }
        .nav-container { max-width: 1280px; margin: 0 auto; padding: 0 2rem; display: flex; align-items: center; justify-content: space-between; }
        .logo-entity { display: flex; align-items: center; gap: 0.75rem; text-decoration: none; }
        .logo-only { width: 58px; height: 58px; object-fit: contain; }
        .logo-text { display: flex; flex-direction: column; }
        .logo-primary { font-family: var(--font-heading); font-weight: 700; font-size: 1.5rem; background: linear-gradient(135deg, var(--civic-sapphire), var(--utility-teal)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; line-height: 1; }
        .logo-secondary { font-size: 0.75rem; color: var(--municipal-slate); opacity: 0.7; letter-spacing: 0.5px; margin-top: 2px; }
        .nav-links { display: flex; align-items: center; gap: 2.5rem; list-style: none; }
        .nav-link { font-family: var(--font-heading); font-weight: 600; color: var(--municipal-slate); text-decoration: none; font-size: 0.95rem; padding: 0.5rem 0; position: relative; transition: var(--transition-smooth); }
        .nav-link::after { content: ''; position: absolute; bottom: -2px; left: 0; width: 0; height: 2px; background: linear-gradient(90deg, var(--civic-sapphire), var(--utility-teal)); transition: var(--transition-smooth); border-radius: var(--radius-pill); }
        .nav-link:hover { color: var(--civic-sapphire); }
        .nav-link:hover::after { width: 100%; }
        .nav-actions { display: flex; align-items: center; gap: 1rem; }

        /* Buttons */
        .civic-button { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.75rem; border-radius: var(--radius-pill); font-family: var(--font-heading); font-weight: 600; text-decoration: none; border: none; cursor: pointer; transition: var(--transition-smooth); position: relative; overflow: hidden; }
        .civic-button::after { content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent); transition: left 0.6s; }
        .civic-button:hover::after { left: 100%; }
        .button-primary { background: linear-gradient(135deg, var(--civic-sapphire), var(--utility-teal)); color: white; box-shadow: 0 8px 24px rgba(11, 61, 145, 0.2); }
        .button-primary:hover { transform: translateY(-2px); box-shadow: 0 12px 32px rgba(11, 61, 145, 0.3); }
        .button-secondary { background: white; color: var(--civic-sapphire); border: 2px solid rgba(11, 61, 145, 0.1); }
        .button-secondary:hover { border-color: var(--civic-sapphire); background: rgba(11, 61, 145, 0.02); transform: translateY(-2px); }
        .menu-toggle { display: none; background: none; border: none; font-size: 1.5rem; color: var(--civic-sapphire); cursor: pointer; }

        /* ---------- REGISTER CARD ---------- */
        .register-section { flex: 1; display: flex; align-items: center; justify-content: center; padding: 120px 1.5rem 60px; min-height: 100vh; }
        .register-card { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(15px); border-radius: var(--radius-modern); box-shadow: var(--shadow-elevated); padding: 2.5rem; width: 100%; max-width: 480px; border: 1px solid rgba(255, 255, 255, 0.3); animation: slideDown 0.7s cubic-bezier(0.34, 1.56, 0.64, 1); opacity: 0; animation-fill-mode: forwards; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-60px) scale(0.95); } to { opacity: 1; transform: translateY(0) scale(1); } }
        .register-header { text-align: center; margin-bottom: 2rem; }
        .register-header .logo-only { width: 80px; height: 80px; margin-bottom: 1rem; }
        .register-header h2 { font-family: var(--font-heading); font-size: 2rem; font-weight: 700; background: linear-gradient(135deg, var(--civic-sapphire), var(--utility-teal)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin-bottom: 0.5rem; }
        .register-header p { color: rgba(47, 72, 88, 0.7); font-size: 0.95rem; }

        /* Form elements */
        .input-box { margin-bottom: 1.5rem; }
        .input-box label { display: block; font-weight: 600; font-size: 0.9rem; color: var(--municipal-slate); margin-bottom: 0.3rem; }
        .input-box input[type="text"], .input-box input[type="email"], .input-box input[type="password"] { width: 100%; padding: 0.85rem 1rem; border: 2px solid rgba(11, 61, 145, 0.1); border-radius: var(--radius-soft); font-family: var(--font-primary); font-size: 1rem; transition: var(--transition-smooth); background: white; }
        .input-box input:focus { outline: none; border-color: var(--civic-sapphire); box-shadow: 0 0 0 4px rgba(11, 61, 145, 0.1); }
        
        .password-field .password-wrapper { position: relative; display: flex; align-items: center; }
        .password-field .password-wrapper input { padding-right: 2.5rem; }
        .password-field .password-wrapper .toggle-password { position: absolute; right: 1rem; cursor: pointer; color: var(--municipal-slate); opacity: 0.6; transition: var(--transition-smooth); font-size: 1.1rem; }
        .password-field .password-wrapper .toggle-password:hover { opacity: 1; color: var(--civic-sapphire); }
        .password-strength { margin-top: 0.5rem; height: 6px; width: 100%; background-color: #e0e0e2; border-radius: 10px; overflow: hidden; }
        .strength-bar { height: 100%; width: 0%; border-radius: 10px; transition: width 0.3s ease, background-color 0.3s ease; }
        .strength-text { font-size: 0.8rem; margin-top: 0.2rem; text-align: right; color: var(--municipal-slate); }
        .strength-weak { background-color: var(--alert-coral); }
        .strength-medium { background-color: var(--insight-amber); }
        .strength-strong { background-color: var(--progress-emerald); }

        /* TERMS CHECKBOX UI */
        .terms-checkbox-wrapper { display: flex; align-items: flex-start; gap: 10px; background: rgba(255, 255, 255, 0.5); padding: 15px; border-radius: var(--radius-soft); border: 1px solid rgba(11, 61, 145, 0.1); }
        .terms-checkbox-wrapper input[type="checkbox"] { width: 18px; height: 18px; margin-top: 2px; cursor: pointer; accent-color: var(--civic-sapphire); }
        .terms-checkbox-wrapper label { margin-bottom: 0; font-weight: 500; font-size: 0.85rem; line-height: 1.4; color: var(--municipal-slate); cursor: pointer; }

        .btn-primary { width: 100%; padding: 0.9rem; background: linear-gradient(135deg, var(--civic-sapphire), var(--utility-teal)); color: white; border: none; border-radius: var(--radius-pill); font-family: var(--font-heading); font-weight: 600; font-size: 1rem; cursor: pointer; transition: var(--transition-smooth); box-shadow: 0 8px 24px rgba(11, 61, 145, 0.2); margin-bottom: 1.5rem; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 12px 32px rgba(11, 61, 145, 0.3); }

        .error-message { background: rgba(231, 111, 81, 0.1); border-left: 4px solid var(--alert-coral); color: var(--alert-coral); padding: 0.75rem 1rem; border-radius: var(--radius-soft); margin-bottom: 1.5rem; font-weight: 500; }
        .success-message { background: rgba(40, 167, 69, 0.1); border-left: 4px solid #28a745; color: #28a745; padding: 0.75rem 1rem; border-radius: var(--radius-soft); margin-bottom: 1.5rem; font-weight: 500; }

        .bottom-links { text-align: center; margin-top: 1.5rem; }
        .bottom-links p { margin-bottom: 0.5rem; font-size: 0.95rem; }
        .link { color: var(--civic-sapphire); text-decoration: none; font-weight: 600; transition: var(--transition-smooth); cursor: pointer; }
        .link:hover { color: var(--utility-teal); text-decoration: underline; }
        .small-text { color: rgba(47, 72, 88, 0.7); }

        /* ---------- TERMS MODAL UI ---------- */
        .tc-modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(8px);
            z-index: 9999; display: flex; align-items: center; justify-content: center;
            opacity: 0; visibility: hidden; transition: all 0.3s ease; padding: 20px;
        }
        .tc-modal-overlay.active { opacity: 1; visibility: visible; }
        .tc-modal-box {
            background: #fff; width: 100%; max-width: 600px;
            border-radius: var(--radius-modern); box-shadow: var(--shadow-elevated);
            transform: translateY(30px) scale(0.95); transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: flex; flex-direction: column; max-height: 85vh; overflow: hidden;
        }
        .tc-modal-overlay.active .tc-modal-box { transform: translateY(0) scale(1); }
        .tc-modal-header {
            padding: 20px 25px; border-bottom: 1px solid rgba(0,0,0,0.1);
            display: flex; justify-content: space-between; align-items: center;
            background: rgba(244, 241, 222, 0.3);
        }
        .tc-modal-header h3 { font-family: var(--font-heading); color: var(--civic-sapphire); margin: 0; font-size: 1.3rem; }
        .tc-modal-header button { background: none; border: none; font-size: 1.5rem; color: var(--municipal-slate); cursor: pointer; transition: color 0.2s; }
        .tc-modal-header button:hover { color: var(--alert-coral); }
        .tc-modal-content {
            padding: 25px; overflow-y: auto; color: var(--municipal-slate);
            font-size: 0.95rem; line-height: 1.6; flex-grow: 1;
        }
        .tc-modal-content h4 { color: var(--civic-sapphire); margin: 15px 0 8px; font-size: 1.1rem; }
        .tc-modal-content p { margin-bottom: 15px; }
        .tc-modal-actions {
            padding: 20px 25px; border-top: 1px solid rgba(0,0,0,0.1);
            display: flex; justify-content: flex-end; gap: 15px; background: rgba(244, 241, 222, 0.3);
        }
        .tc-modal-actions .btn-primary { margin-bottom: 0; width: auto; padding: 0.75rem 2rem; }
        .tc-modal-actions .btn-secondary { padding: 0.75rem 1.5rem; border-radius: var(--radius-pill); font-weight: 600; cursor: pointer; transition: 0.3s; }

        /* ---------- FOOTER ---------- */
        .civic-footer { background: var(--municipal-slate); color: white; padding: 4rem 0 2rem; margin-top: auto; }
        .footer-grid { max-width: 1280px; margin: 0 auto; padding: 0 2rem; display: grid; grid-template-columns: repeat(5, 1fr); gap: 2rem; margin-bottom: 3rem; }
        .footer-brand .footer-logo { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem; }
        .footer-logo-icon { width: 40px; height: 40px; background: white; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--civic-sapphire); font-size: 1.25rem; }
        .footer-logo-text { font-family: var(--font-heading); font-size: 1.25rem; font-weight: 700; }
        .footer-description { opacity: 0.8; font-size: 0.9rem; line-height: 1.6; }
        .footer-heading { font-family: var(--font-heading); font-size: 1.125rem; font-weight: 700; margin-bottom: 1.5rem; }
        .footer-links { list-style: none; padding: 0; }
        .footer-grid > div:nth-child(2) .footer-links { text-align: center; }
        .footer-grid > div:nth-child(2) { display: flex; flex-direction: column; align-items: center; }
        .footer-grid > div:not(:first-child) { display: flex; flex-direction: column; align-items: center; text-align: center; }
        .footer-links li { margin-bottom: 0.75rem; }
        .footer-links a { color: rgba(255, 255, 255, 0.8); text-decoration: none; transition: var(--transition-smooth); font-size: 0.9rem; }
        .footer-links a:hover { color: white; padding-left: 4px; }
        .footer-bottom { max-width: 1280px; margin: 0 auto; padding: 2rem 2rem 0; text-align: center; border-top: 1px solid rgba(255, 255, 255, 0.1); }
        .footer-copyright { opacity: 0.7; font-size: 0.875rem; }

        @media (max-width: 992px) {
            .menu-toggle { display: block; }
            .nav-actions { display: none; }
            .nav-links { position: fixed; top: 80px; right: -100%; width: 80%; height: calc(100vh - 80px); background: white; flex-direction: column; justify-content: flex-start; padding: 2rem; box-shadow: -10px 0 30px rgba(0,0,0,0.1); transition: 0.4s ease-in-out; z-index: 1000; }
            .nav-links.active { right: 0; }
            .nav-links .mobile-nav-actions { display: flex; flex-direction: column; gap: 1rem; width: 100%; margin-top: 2rem; }
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
            .input-box input[type="text"], 
            .input-box input[type="email"], 
            .input-box input[type="password"] {
                background: #ffffff !important;
                color: var(--municipal-slate) !important;
                border-color: rgba(11, 61, 145, 0.1) !important;
            }
            .nav-container { padding: 0 1rem; }
            .register-card { padding: 2rem 1.5rem; }
            .register-header h2 { font-size: 1.75rem; }
            .footer-grid { grid-template-columns: 1fr; gap: 2rem; }
            .tc-modal-actions { flex-direction: column; }
            .tc-modal-actions button { width: 100%; text-align: center; }
        }
    </style>
</head>
<body>
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

    <section class="register-section">
        <div class="register-card">
            <div class="register-header">
                <img src="assets/images/logocityhall.png" alt="LGU Logo" class="logo-only">
                <h2>Create Account</h2>
                <p>Register to access the LGU maintenance system.</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-message"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="registerForm">
                <div class="input-box">
                    <label>Full Name</label>
                    <input type="text" name="full_name" placeholder="Juan Dela Cruz" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                </div>

                <div class="input-box">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="name@lgu.gov.ph" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                </div>

                <div class="input-box password-field">
                    <label>Password (min. 6 characters)</label>
                    <div class="password-wrapper">
                        <input type="password" id="register-password" name="password" placeholder="•••" required>
                        <i class="fas fa-eye toggle-password" id="toggle-register-password"></i>
                    </div>
                    <div class="password-strength">
                        <div class="strength-bar" id="strengthBar"></div>
                    </div>
                    <div class="strength-text" id="strengthText"></div>
                </div>

                <div class="input-box password-field">
                    <label>Confirm Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="register-confirm-password" name="confirm_password" placeholder="••••" required>
                        <i class="fas fa-eye toggle-password" id="toggle-register-confirm"></i>
                    </div>
                </div>

                <div class="input-box terms-checkbox-wrapper">
                    <input type="checkbox" id="terms_agreed" name="terms_agreed" <?php echo isset($_POST['terms_agreed']) ? 'checked' : ''; ?> required>
                    <label for="terms_agreed">
                        I have read and agree to the 
                        <a href="#" id="openTcModal" class="link">Terms & Conditions and Data Privacy Notice</a>.
                    </label>
                </div>

                <button type="submit" class="btn-primary">Create Account</button>

                <div class="bottom-links">
                    <p class="small-text">
                        Already registered?
                        <a href="login.php" class="link">Sign In</a>
                    </p>
                </div>
            </form>
        </div>
    </section>

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
                    <li><a href="#">Privacy Policy</a></li>
                    <li><a href="#">Terms & Conditions</a></li>
                    <li><a href="#">Data Privacy Notice</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="footer-copyright">
                2026 Utilities Management System · All Rights Reserved
            </div>
        </div>
    </footer>

    <div class="tc-modal-overlay" id="tcModalOverlay">
        <div class="tc-modal-box">
            <div class="tc-modal-header">
                <h3>Terms & Data Privacy</h3>
                <button type="button" id="closeTcIcon"><i class="fas fa-times"></i></button>
            </div>
            <div class="tc-modal-content">
                <h4>1. Introduction</h4>
                <p>Welcome to the Quezon City Web-Based Utilities Management System. By registering an account and using this portal, you agree to comply with the terms and conditions outlined below.</p>
                
                <h4>2. Data Privacy Act of 2012</h4>
                <p>In accordance with Republic Act No. 10173 (Data Privacy Act of 2012), the Local Government Unit (LGU) is committed to protecting your personal information. We collect your Name, Email, Phone Number, and Barangay Address strictly for the purpose of validating your residency and processing your requests (e.g., Asset Borrowing, Service Requests, Complaints).</p>
                
                <h4>3. Use of Personal Data</h4>
                <p>Your data will not be shared with third parties without your explicit consent, except when required by law enforcement or necessary for public safety. It will be securely stored in our databases and accessed only by authorized LGU personnel.</p>
                
                <h4>4. User Responsibilities</h4>
                <p>You are responsible for maintaining the confidentiality of your account credentials. Any malicious activity, submission of false information, or abuse of the borrowing and ticketing system may result in account suspension and restricted access to barangay services.</p>
                
                <h4>5. Acceptance</h4>
                <p>By clicking "I Agree", you consent to the collection and processing of your data as stated above and acknowledge your responsibilities as a user of this portal.</p>
            </div>
            <div class="tc-modal-actions">
                <button type="button" class="btn-secondary" id="closeTcBtn">Cancel</button>
                <button type="button" class="btn-primary" id="acceptTcBtn">I Agree</button>
            </div>
        </div>
    </div>

    <script>
        // Modal Logic
        const tcModalOverlay = document.getElementById('tcModalOverlay');
        const openTcModal = document.getElementById('openTcModal');
        const closeTcIcon = document.getElementById('closeTcIcon');
        const closeTcBtn = document.getElementById('closeTcBtn');
        const acceptTcBtn = document.getElementById('acceptTcBtn');
        const termsCheckbox = document.getElementById('terms_agreed');

        function openModal(e) {
            e.preventDefault();
            tcModalOverlay.classList.add('active');
        }

        function closeModal() {
            tcModalOverlay.classList.remove('active');
        }

        openTcModal.addEventListener('click', openModal);
        closeTcIcon.addEventListener('click', closeModal);
        closeTcBtn.addEventListener('click', closeModal);
        
        // Auto-check box when clicking "I Agree"
        acceptTcBtn.addEventListener('click', function() {
            termsCheckbox.checked = true;
            closeModal();
        });

        // Close modal when clicking outside
        tcModalOverlay.addEventListener('click', function(e) {
            if (e.target === tcModalOverlay) closeModal();
        });

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
        }

        // Show/Hide Passwords with icons
        (function() {
            const toggleMain = document.getElementById('toggle-register-password');
            const passwordMain = document.getElementById('register-password');
            if (toggleMain && passwordMain) {
                toggleMain.addEventListener('click', function() {
                    const type = passwordMain.type === 'password' ? 'text' : 'password';
                    passwordMain.type = type;
                    this.classList.toggle('fa-eye');
                    this.classList.toggle('fa-eye-slash');
                });
            }

            const toggleConfirm = document.getElementById('toggle-register-confirm');
            const passwordConfirm = document.getElementById('register-confirm-password');
            if (toggleConfirm && passwordConfirm) {
                toggleConfirm.addEventListener('click', function() {
                    const type = passwordConfirm.type === 'password' ? 'text' : 'password';
                    passwordConfirm.type = type;
                    this.classList.toggle('fa-eye');
                    this.classList.toggle('fa-eye-slash');
                });
            }
        })();

        // Password strength meter
        (function() {
            const passwordInput = document.getElementById('register-password');
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');

            if (!passwordInput || !strengthBar || !strengthText) return;

            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;

                if (password.length >= 8) strength += 1;
                if (password.length >= 12) strength += 1;
                if (/[a-z]/.test(password)) strength += 1;
                if (/[A-Z]/.test(password)) strength += 1;
                if (/\d/.test(password)) strength += 1;
                if (/[^a-zA-Z0-9]/.test(password)) strength += 1;

                if (strength > 5) strength = 5;

                let barWidth = (strength / 5) * 100;
                strengthBar.style.width = barWidth + '%';

                if (strength <= 2) {
                    strengthBar.className = 'strength-bar strength-weak';
                    strengthText.textContent = 'Weak';
                    strengthText.style.color = 'var(--alert-coral)';
                } else if (strength <= 4) {
                    strengthBar.className = 'strength-bar strength-medium';
                    strengthText.textContent = 'Medium';
                    strengthText.style.color = 'var(--insight-amber)';
                } else {
                    strengthBar.className = 'strength-bar strength-strong';
                    strengthText.textContent = 'Strong';
                    strengthText.style.color = 'var(--progress-emerald)';
                }

                if (password.length === 0) {
                    strengthBar.style.width = '0%';
                    strengthText.textContent = '';
                }
            });
        })();
    </script>
</body>
</html>