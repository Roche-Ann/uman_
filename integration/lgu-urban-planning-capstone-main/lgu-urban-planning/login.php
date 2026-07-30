<?php
session_start();
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$auth = new Auth();
$error = '';
$dbInstance = Database::getInstance();

$showOtpForm = false;

// --- RESEND OTP: handles ?action=resend, no separate file needed ---
if (isset($_GET['action']) && $_GET['action'] === 'resend') {
    if (!isset($_SESSION['temp_login_user'])) {
        header("Location: login.php");
        exit();
    }

    $tempUser = $_SESSION['temp_login_user'];
    $user = $dbInstance->fetchOne(
        "SELECT id, email FROM users WHERE (username = ? OR email = ?) AND is_verified = 1 LIMIT 1",
        [$tempUser, $tempUser]
    );

    if (!$user) {
        header("Location: login.php");
        exit();
    }

    $new_otp    = rand(100000, 999999);
    $new_expiry = date("Y-m-d H:i:s", strtotime("+1 minute"));
    $dbInstance->query(
        "UPDATE users SET otp_code = ?, otp_expiry = ? WHERE id = ?",
        [$new_otp, $new_expiry, $user['id']]
    );

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'aelousssnexus@gmail.com';
        $mail->Password   = 'zuey mjni sbzz gvsm';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('aelousssnexus@gmail.com', 'LGU Urban Planning');
        $mail->addAddress($user['email']);
        $mail->isHTML(true);
        $mail->Subject = 'Your NEW Login OTP Code';
        $mail->Body = "
        <div style='background-color: #f9f9f9; padding: 40px 0; font-family: Helvetica, Arial, sans-serif;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05);'>
                <div style='padding: 30px; text-align: center;'>
                    <h2 style='color: #285ccd; font-size: 24px; margin-bottom: 20px; font-weight: bold;'>Verification Code</h2>
                    <p style='color: #555555; font-size: 16px; margin-bottom: 30px;'>Your New One-Time Password (OTP) for login is:</p>
                    <div style='background-color: #f4f7ff; border-radius: 10px; padding: 20px; margin: 0 auto 30px auto; width: fit-content;'>
                        <h1 style='letter-spacing: 12px; color: #333333; font-size: 42px; margin: 0; padding-left: 12px;'>$new_otp</h1>
                    </div>
                    <p style='color: #777777; font-size: 14px; margin-bottom: 10px;'>This code is valid for <strong style='color: #333;'>1 minute</strong> only.</p>
                    <p style='color: #999999; font-size: 12px; border-top: 1px solid #eeeeee; padding-top: 20px; margin-top: 20px;'>
                        If you did not request this, please ignore this email.
                    </p>
                </div>
            </div>
        </div>";

        $mail->send();
        $_SESSION['otp_expiry_ts'] = strtotime($new_expiry);
        header("Location: login.php?otp=1&resend=success");
    } catch (Exception $e) {
        header("Location: login.php?otp=1&resend=error");
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- STEP 2: ITO ANG DAGDAG (PAG-CHECK NG OTP) ---
    if (isset($_POST['final_otp'])) {
        $userOtp = $_POST['final_otp'];
        $tempUser = $_SESSION['temp_login_user'] ?? '';
        $tempPass = $_SESSION['temp_login_pass'] ?? '';

        $user = $dbInstance->fetchOne("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1", [$tempUser, $tempUser]);

        if ($user) {
            $now = date("Y-m-d H:i:s");
            if ($user['otp_code'] === $userOtp && $now <= $user['otp_expiry']) {
                
                if ($auth->login($tempUser, $tempPass)) {
                    $dbInstance->query("UPDATE users SET otp_code = NULL, otp_expiry = NULL WHERE id = ?", [$user['id']]);
                    unset($_SESSION['temp_login_user'], $_SESSION['temp_login_pass'], $_SESSION['otp_expiry_ts']);
                    session_regenerate_id(true);
                    $_SESSION['show_preloader'] = true;
                    $auth->redirectToDashboard();
                    exit;
                }
            } else {
                $error = "Incorrect code or session expired (1-minute limit).";
                $showOtpForm = true; 
            }
        }
    } 
    
    // --- STEP 1: ANG ORIGINAL MONG LOGIC (PAG-CHECK NG PASSWORD) ---
    else {
        $usernameInput = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        $user = $dbInstance->fetchOne(
            "SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1", 
            [$usernameInput, $usernameInput]
        );

        if ($user && password_verify($password, $user['password_hash'])) {

            if ((int)$user['is_verified'] === 0) {
                header("Location: register.php?step=otp&email=" . urlencode($user['email']));
                exit;
            }

            // --- DITO NATIN ISININGIT ANG PAG-GENERATE NG OTP ---
            $otp = rand(100000, 999999);
            $expiry = date("Y-m-d H:i:s", strtotime('+1 minute'));

            // Gumamit tayo ng query() imbes na execute() para hindi mag-error
            $dbInstance->query(
                "UPDATE users SET otp_code = ?, otp_expiry = ? WHERE id = ?",
                [$otp, $expiry, $user['id']]
            );

            // Itabi muna ang credentials sa session para magamit sa Step 2
            $_SESSION['temp_login_user'] = $usernameInput;
            $_SESSION['temp_login_pass'] = $password;

            // Send OTP via PHPMailer

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';      
                $mail->SMTPAuth   = true;
                $mail->Username   = 'aelousssnexus@gmail.com';
                $mail->Password   = 'zuey mjni sbzz gvsm';   
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('aelousssnexus@gmail.com', 'LGU Urban Planning');
                $mail->addAddress($user['email']);
                $mail->Subject = 'Your OTP Code';
                $mail->isHTML(true);
                $mail->Body = "
                <div style='background-color: #f9f9f9; padding: 40px 0; font-family: Helvetica, Arial, sans-serif;'>
                    <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05);'>
                        <div style='padding: 30px; text-align: center;'>
                            <h2 style='color: #285ccd; font-size: 24px; margin-bottom: 20px; font-weight: bold;'>Verification Code</h2>
                            
                            <p style='color: #555555; font-size: 16px; margin-bottom: 30px;'>Your One-Time Password (OTP) for login is:</p>
                            
                            <div style='background-color: #f4f7ff; border-radius: 10px; padding: 20px; margin: 0 auto 30px auto; width: fit-content;'>
                                <h1 style='letter-spacing: 12px; color: #333333; font-size: 42px; margin: 0; padding-left: 12px;'>$otp</h1>
                            </div>
                            
                            <p style='color: #777777; font-size: 14px; margin-bottom: 10px;'>This code is valid for <strong style='color: #333;'>1 minute</strong> only.</p>

                            <p style='color: #999999; font-size: 12px; border-top: 1px solid #eeeeee; padding-top: 20px; margin-top: 20px;'>
                                If you did not request this, please ignore this email.
                            </p>
                        </div>
                    </div>
                </div>";

                $mail->send();
                $_SESSION['otp_expiry_ts'] = strtotime($expiry);
            } catch (Exception $e) {
                $error = "Could not send OTP. Mailer Error: {$mail->ErrorInfo}";
                $showOtpForm = false;
            }
            
            $showOtpForm = true;

        } else {
            $error = 'Invalid username or password';
        }
    }
}

// Show OTP form when redirected back after resend (?otp=1)
if (!$showOtpForm && isset($_GET['otp']) && $_GET['otp'] === '1' && isset($_SESSION['temp_login_user'])) {
    $showOtpForm = true;
}

if ($auth->isLoggedIn()) {
    $auth->redirectToDashboard();
    exit;
}

$pageTitle = "Login - LGU Urban Planning System";
include __DIR__ . '/auth/header.php'; 
?>

<style>
    /* LOGIN */
    .login-container { flex: 1; display: flex; align-items: center; justify-content: center; padding: 10px; margin-top: 5px; }
    .login-card { width: 100%; max-width: 380px; background: rgba(255, 255, 255, 0.85); padding: 15px 32px; border-radius: 18px; backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); box-shadow: 0 8px 25px rgba(0,0,0,0.2); position: relative; overflow: hidden; }
    .login-header { background: linear-gradient(135deg, #6384d2, #285ccd); margin: -28px -32px 25px -32px; padding: 20px; text-align: center; color: white; }
    .login-header h5 { font-size: 1.1rem; white-space: nowrap; }
    .login-logo-wrap {
        width: 100px;
        height: 100px;
        margin: 0 auto 10px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .login-logo {
        width: 100%;
        height: 100%;
        object-fit: contain;
        margin-bottom: 0;
        filter:
            drop-shadow(0 0 6px rgba(255,255,255,0.9))
            drop-shadow(0 0 14px rgba(255,255,255,0.7))
            drop-shadow(0 0 24px rgba(255,255,255,0.5));
    }

    /* FORM */
    .form-label { font-size: 13px; font-weight: 600; color: #000; margin-bottom: 5px; }
    .form-control { background: rgba(255,255,255,0.7) !important; border: 1px solid rgba(0,0,0,0.1); border-radius: 10px; padding: 10px 12px; color: #000; font-size: 13px; }

    .form-control.is-invalid { border: 1px solid #dc3545 !important; box-shadow: 0 0 0 3px rgba(220,53,69,0.12); animation: field-shake 0.3s ease; }
    .field-error { display: none; color: #dc3545; font-size: 0.75rem; margin-top: 5px; }
    .field-error.show { display: block; }
    @keyframes field-shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-4px); }
        75% { transform: translateX(4px); }
    }

    /* BUTTON */
    .btn-login { width: 100%; padding: 10px; background: linear-gradient(135deg, #6384d2, #285ccd); border: none; border-radius: 12px; color: #fff; font-size: 16px; font-weight: 600; transition: 0.25s ease; }
    .btn-login:hover { background: linear-gradient(135deg, #4d76d6, #1651d0); transform: translateY(-2px); box-shadow: 0 6px 15px rgba(43, 91, 222, 0.45); color: #fff; }

    /* FOOTER LINKS */
    .login-footer-link { color: #2864ef; font-weight: 500; transition: opacity 0.2s; }
    .login-footer-link:hover { opacity: 0.75; color: #2864ef; }
    .login-footer-text { color: #000; font-weight: 400; }
    .login-footer-register { color: #2864ef; font-weight: 600; transition: opacity 0.2s; }
    .login-footer-register:hover { opacity: 0.75; color: #2864ef; }

    /* PASSWORD */
    .password-wrapper { position: relative; }
    .password-toggle { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; opacity: 0.7; }

    /* ── MODERN ALERT ── */
    #customAlertOverlay {
        display: none; position: fixed; inset: 0;
        background: rgba(0,0,0,0.45); backdrop-filter: blur(4px);
        z-index: 9999; align-items: center; justify-content: center;
    }
    #customAlertOverlay.show { display: flex; animation: alertFadeIn 0.2s ease; }
    #customAlertBox {
        background: #fff; border-radius: 20px; padding: 36px 30px 26px;
        max-width: 360px; width: 90%; text-align: center;
        box-shadow: 0 24px 70px rgba(0,0,0,0.18);
        animation: alertSlideUp 0.25s ease;
    }
    @keyframes alertFadeIn  { from { opacity: 0; } to { opacity: 1; } }
    @keyframes alertSlideUp { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    .alert-icon-wrap {
        width: 64px; height: 64px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 16px; font-size: 1.8rem;
    }
    .alert-icon-wrap.error   { background: #fef2f2; }
    .alert-icon-wrap.warning { background: #fffbeb; }
    .alert-icon-wrap.success { background: #f0fdf4; }
    .alert-icon-wrap.info    { background: #eff6ff; }
    #customAlertTitle { font-size: 1.05rem; font-weight: 700; margin-bottom: 8px; color: #1e293b; }
    #customAlertMsg   { font-size: 0.875rem; color: #64748b; margin-bottom: 24px; line-height: 1.5; }
    #customAlertBtn {
        padding: 10px 36px; border: none; border-radius: 12px;
        background: linear-gradient(135deg, #6384d2, #285ccd);
        color: #fff; font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: 0.2s;
    }
    #customAlertBtn:hover { background: linear-gradient(135deg, #4d76d6, #1651d0); transform: translateY(-1px); box-shadow: 0 6px 18px rgba(40,92,205,0.35); }

/* ================================================
   MOBILE RESPONSIVE
   Breakpoints: 768px (Tablet), 425px (Large Mobile), 320px (Small Mobile)
   ================================================ */

/* --- 768px: Tablet --- */
@media (max-width: 768px) {
    .login-container {
        align-items: center;
        padding: 20px 15px;
    }

    .login-card {
        width: 100%;
        max-width: 420px;
        padding: 0;
        border-radius: 14px;
        overflow: hidden;
    }

    .login-header {
        padding: 18px 20px;
        margin: 0;
    }

    .login-logo-wrap {
        width: 70px;
        height: 70px;
        margin-bottom: 8px;
    }

    .login-header h5 {
        font-size: 1rem;
    }

    .login-header .small {
        font-size: 0.7rem !important;
    }

    .login-card > div:last-child {
        padding: 20px 24px;
    }

    h5.text-center {
        font-size: 1rem;
        margin-bottom: 14px !important;
    }

    .form-label {
        font-size: 12px;
        margin-bottom: 4px;
    }

    .form-control {
        font-size: 13px;
        padding: 8px 11px;
    }

    .btn-login {
        padding: 9px 12px;
        font-size: 15px;
    }

    .text-center.mt-3 a,
    .text-center.mt-2 a {
        font-size: 0.8rem;
    }

    /* OTP boxes */
    .otp-box {
        width: 44px !important;
        height: 48px !important;
        font-size: 1.2rem !important;
    }

    /* Alert box */
    #customAlertBox {
        max-width: 320px;
        padding: 28px 22px 20px;
    }
}

/* --- 425px: Large Mobile --- */
@media (max-width: 425px) {
    .login-container {
        padding: 12px 10px;
    }

    .login-card {
        max-width: 100%;
        border-radius: 12px;
    }

    .login-header {
        padding: 14px 16px;
    }

    .login-logo-wrap {
        width: 58px;
        height: 58px;
        margin-bottom: 6px;
    }

    .login-header h5 {
        font-size: 0.9rem;
    }

    .login-header .small {
        font-size: 0.63rem !important;
    }

    .login-card > div:last-child {
        padding: 16px 18px;
    }

    h5.text-center {
        font-size: 0.92rem;
        margin-bottom: 10px !important;
    }

    .form-label {
        font-size: 11.5px;
        margin-bottom: 3px;
    }

    .form-control {
        font-size: 12.5px;
        padding: 7px 10px;
        border-radius: 8px;
    }

    .btn-login {
        padding: 8px 10px;
        font-size: 14px;
        border-radius: 10px;
    }

    .text-center.mt-3,
    .text-center.mt-2 {
        margin-top: 8px !important;
    }

    .text-center.mt-3 a,
    .text-center.mt-2 a {
        font-size: 0.75rem;
    }

    /* OTP boxes */
    .otp-box {
        width: 38px !important;
        height: 44px !important;
        font-size: 1.1rem !important;
    }

    /* Alert box */
    #customAlertBox {
        max-width: 290px;
        padding: 24px 18px 18px;
        border-radius: 16px;
    }

    #customAlertTitle {
        font-size: 0.95rem;
    }

    #customAlertMsg {
        font-size: 0.8rem;
    }

    #customAlertBtn {
        padding: 9px 28px;
        font-size: 0.88rem;
    }
}

/* --- 320px: Small Mobile --- */
@media (max-width: 320px) {
    .login-container {
        padding: 8px 6px;
    }

    .login-card {
        border-radius: 10px;
    }

    .login-header {
        padding: 12px 14px;
    }

    .login-logo-wrap {
        width: 48px;
        height: 48px;
        margin-bottom: 5px;
    }

    .login-header h5 {
        font-size: 0.8rem;
    }

    .login-header .small {
        font-size: 0.58rem !important;
    }

    .login-card > div:last-child {
        padding: 12px 14px;
    }

    h5.text-center {
        font-size: 0.85rem;
        margin-bottom: 8px !important;
    }

    p.text-center.small {
        font-size: 0.7rem !important;
    }

    .form-label {
        font-size: 11px;
        margin-bottom: 2px;
    }

    .form-control {
        font-size: 12px;
        padding: 6px 9px;
        border-radius: 7px;
    }

    .btn-login {
        padding: 7px 8px;
        font-size: 13px;
        border-radius: 9px;
    }

    .text-center.mt-3,
    .text-center.mt-2 {
        margin-top: 6px !important;
    }

    .text-center.mt-3 a,
    .text-center.mt-2 a {
        font-size: 0.7rem;
        display: block;
        line-height: 1.4;
    }

    /* OTP boxes — tight fit for 6 digits */
    .otp-box {
        width: 32px !important;
        height: 38px !important;
        font-size: 1rem !important;
        padding: 2px !important;
    }

    /* Alert box */
    #customAlertBox {
        max-width: 260px;
        padding: 20px 14px 16px;
        border-radius: 14px;
    }

    #customAlertTitle {
        font-size: 0.88rem;
    }

    #customAlertMsg {
        font-size: 0.75rem;
        margin-bottom: 18px;
    }

    #customAlertBtn {
        padding: 8px 22px;
        font-size: 0.82rem;
        border-radius: 10px;
    }
}

</style>

<div id="customAlertOverlay" data-login-error="<?php echo addslashes($error); ?>">
    <div id="customAlertBox">
        <div class="alert-icon-wrap" id="customAlertIconWrap">
            <span id="customAlertIcon"></span>
        </div>
        <div id="customAlertTitle"></div>
        <div id="customAlertMsg"></div>
        <button id="customAlertBtn" onclick="closeAlert()">Got it</button>
    </div>
</div>

<div class="login-container">
    <div class="login-card">
        <div class="login-header">
            <div class="login-logo-wrap">
                <img src="/lgu-urban-planning/assets/upad-logo.png" alt="LGU Logo" class="login-logo">
            </div>
            <h5 class="fw-bold mb-1" data-en="Urban Planning and Development" data-tl="Pagpaplano ng Lungsod">Urban Planning and Development</h5>
            <div class="small opacity-75" style="font-size: 0.65rem;" 
                 data-en="Development Permit Management System" 
                 data-tl="Sistema ng Pamamahala ng Permit">Development Permit Management System</div>
        </div>

        <div>
            <?php if (!$showOtpForm): ?>
                <h5 class="text-center mb-4 fw-bold text-dark" data-en="Login to Your Account" data-tl="Mag-login">Login to Your Account</h5>
                
                <form method="POST" id="loginForm" novalidate>
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="bi bi-person me-1"></i> 
                            <span data-en="Username or Email" data-tl="Username o Email">Username or Email</span>
                        </label>
                        <input type="text" class="form-control shadow-sm" name="username" 
                            data-en-placeholder="Enter your username or email" 
                            data-tl-placeholder="Ilagay ang iyong username o email" 
                            placeholder="Enter your username or email" required>
                        <div class="field-error" data-en="Please fill out this field." data-tl="Pakipunan ang patlang na ito."></div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">
                            <i class="bi bi-lock me-1"></i> 
                            <span data-en="Password" data-tl="Password">Password</span>
                        </label>
                        <div class="password-wrapper">
                            <input type="password" id="password" class="form-control shadow-sm" name="password" 
                                data-en-placeholder="Enter your password" 
                                data-tl-placeholder="Ilagay ang iyong password" 
                                placeholder="Enter your password" required>
                            <i class="bi bi-eye password-toggle" onclick="togglePassword('password', 'toggleIcon')" id="toggleIcon"></i>
                        </div>
                        <div class="field-error" data-en="Please fill out this field." data-tl="Pakipunan ang patlang na ito."></div>
                    </div>
                    
                    <button type="submit" class="btn btn-login shadow-sm">
                        <i class="bi bi-box-arrow-in-right me-2"></i> 
                        <span data-en="Login" data-tl="Mag-login">Login</span>
                    </button>
                </form>
                
                <div class="text-center mt-3 mb-1">
                    <a href="forgot_password.php" class="small text-decoration-none login-footer-link" data-en="Forgot Password" data-tl="Nakalimutan ang Password">Forgot Password</a>
                </div>

                <div class="text-center mt-2">
                    <span class="small login-footer-text" data-en="Don't have an account?" data-tl="Wala pang account?">Don't have an account?</span>
                    <a href="register.php" class="small text-decoration-none login-footer-register ms-1" data-en="Register" data-tl="Mag-rehistro">Register</a>
                </div>
                <?php else: ?>
                <h5 class="text-center mb-2 fw-bold text-dark">Enter OTP Code</h5>
                <p class="text-center small text-muted mb-3">A 6-digit code was sent to your email. (Expires in 1 min)</p>

                <?php if (isset($_GET['resend']) && $_GET['resend'] === 'success'): ?>
                    <div class="alert alert-success py-2 text-center small mb-3" role="alert" style="border-radius:10px; font-size:0.78rem;">
                        <i class="bi bi-check-circle me-1"></i> New OTP sent! Check your email.
                    </div>
                <?php elseif (isset($_GET['resend']) && $_GET['resend'] === 'error'): ?>
                    <div class="alert alert-danger py-2 text-center small mb-3" role="alert" style="border-radius:10px; font-size:0.78rem;">
                        <i class="bi bi-exclamation-circle me-1"></i> Failed to send OTP. Please try again.
                    </div>
                <?php endif; ?>

                <form id="otpForm" method="POST">
                    <div class="d-flex gap-2 justify-content-center mb-3">
                        <input type="text" class="form-control otp-box text-center fw-bold fs-4" maxlength="1" style="width: 40px; height: 50px;">
                        <input type="text" class="form-control otp-box text-center fw-bold fs-4" maxlength="1" style="width: 40px; height: 50px;">
                        <input type="text" class="form-control otp-box text-center fw-bold fs-4" maxlength="1" style="width: 40px; height: 50px;">
                        <input type="text" class="form-control otp-box text-center fw-bold fs-4" maxlength="1" style="width: 40px; height: 50px;">
                        <input type="text" class="form-control otp-box text-center fw-bold fs-4" maxlength="1" style="width: 40px; height: 50px;">
                        <input type="text" class="form-control otp-box text-center fw-bold fs-4" maxlength="1" style="width: 40px; height: 50px;">
                    </div>

                    <input type="hidden" name="final_otp" id="final_otp">

                    <button type="submit" class="btn btn-login shadow-sm w-100">
                        Verify & Login
                    </button>

                    <!-- Resend OTP row -->
                    <div class="text-center mt-3">
                        <span class="small text-muted">Didn't receive the code?</span>
                        <a id="resendBtn" href="login.php?action=resend"
                           class="small text-decoration-none ms-1 fw-semibold"
                           style="color:#285ccd; pointer-events:none; opacity:0.45;">
                            Resend OTP <span id="resendTimer">(60s)</span>
                        </a>
                    </div>

                    <div class="text-center mt-2">
                        <a href="login.php" class="small text-decoration-none text-muted">Cancel Login</a>
                    </div>
                </form>

                <script>
                (function () {
                    const btn   = document.getElementById('resendBtn');
                    const timer = document.getElementById('resendTimer');

                    // Server tells us exactly when the OTP that's currently
                    // active actually expires (set the moment it was mailed,
                    // whether that was the initial send or a resend). This
                    // means the countdown is always in sync with reality and
                    // resets correctly on every fresh OTP, not just once.
                    const expiryTs = <?php echo isset($_SESSION['otp_expiry_ts']) ? (int)$_SESSION['otp_expiry_ts'] * 1000 : 'Date.now()'; ?>;

                    function tick() {
                        const remaining = Math.ceil((expiryTs - Date.now()) / 1000);

                        if (remaining <= 0) {
                            timer.textContent = '';
                            btn.style.pointerEvents = 'auto';
                            btn.style.opacity       = '1';
                        } else {
                            timer.textContent       = '(' + remaining + 's)';
                            btn.style.pointerEvents = 'none';
                            btn.style.opacity       = '0.45';
                            setTimeout(tick, 1000);
                        }
                    }

                    tick();
                })();
                </script>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
(function () {
    var form = document.getElementById('loginForm');
    if (!form) return;

    var lang = document.documentElement.getAttribute('lang') === 'tl' ? 'tl' : 'en';

    function markField(field, invalid) {
        var wrapper = field.closest('.mb-3, .mb-4');
        var error = wrapper ? wrapper.querySelector('.field-error') : null;
        field.classList.toggle('is-invalid', invalid);
        if (error) {
            error.textContent = error.getAttribute('data-' + lang) || error.getAttribute('data-en');
            error.classList.toggle('show', invalid);
        }
    }

    form.addEventListener('submit', function (e) {
        var fields = form.querySelectorAll('[required]');
        var firstInvalid = null;
        var hasInvalid = false;

        fields.forEach(function (field) {
            var invalid = field.value.trim() === '';
            markField(field, invalid);
            if (invalid) {
                hasInvalid = true;
                if (!firstInvalid) firstInvalid = field;
            }
        });

        if (hasInvalid) {
            e.preventDefault();
            firstInvalid.focus();
        }
    });

    // Clear the red state as soon as the user starts typing in that field
    form.querySelectorAll('[required]').forEach(function (field) {
        field.addEventListener('input', function () {
            if (field.value.trim() !== '') markField(field, false);
        });
    });
})();
</script>

<?php include __DIR__ . '/auth/footer.php'; ?>