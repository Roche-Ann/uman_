<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

date_default_timezone_set('Asia/Manila');
require 'vendor/autoload.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Auth.php';

$auth = new Auth();
$db = Database::getInstance();
$error = '';
$success_msg = '';
$otp_sent = false;
$email_for_otp = '';

if (isset($_GET['step']) && $_GET['step'] === 'otp' && isset($_GET['email'])) {
    $otp_sent = true;
    $email_for_otp = $_GET['email'];
}

if (isset($_GET['resend'])) {
        if ($_GET['resend'] === 'success') {
            $success_msg_resend = "A new OTP has been sent to your email!";
        } elseif ($_GET['resend'] === 'error') {
            $error = "Failed to resend OTP. Please try again.";
        }
    }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- LOGIC 1: SUBMITION OF REGISTRATION FORM ---
    if (isset($_POST['first_name'])) {
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $firstName = ucwords(strtolower(trim($_POST['first_name'] ?? '')));
        $lastName  = ucwords(strtolower(trim($_POST['last_name'] ?? '')));
        $phone = $_POST['phone'] ?? '';
        $birthDate = $_POST['birth_date'] ?? '';
        $street    = ucwords(strtolower(trim($_POST['street'] ?? '')));
        $barangay  = ucwords(strtolower(trim($_POST['barangay'] ?? '')));
        $district  = ucwords(strtolower(trim($_POST['district'] ?? '')));
        $city      = ucwords(strtolower(trim($_POST['city'] ?? '')));

        $today = new DateTime();
        $birth = new DateTime($birthDate);
        $age = $today->diff($birth)->y;

        $hasUppercase = preg_match('@[A-Z]@', $password);
        $hasLowercase = preg_match('@[a-z]@', $password);
        $hasNumber    = preg_match('@[0-9]@', $password);

        if (empty($username) || empty($email) || empty($password) || empty($birthDate)) {
            $error = 'All required fields must be filled';
        } elseif (empty($_POST['id_type'])) { 
            $error = 'Please select a Valid ID type.';
        } elseif (empty($_FILES['id_front']['name']) || empty($_FILES['id_back']['name'])) {
            $error = 'Please upload both Front and Back images of your ID.';
        } elseif ($age < 18) {
            $error = 'You must be at least 18 years old to register.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif (!$hasUppercase || !$hasLowercase || !$hasNumber) {
            $error = 'Password must combine uppercase, lowercase, and numbers.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match';
        } else {
            $existing = $db->fetchOne("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email]);
            
            if ($existing) {
                $error = 'Username or email already exists';
            } else {
            // OTP Generation
            $otp = rand(100000, 999999);
            $expiry = date("Y-m-d H:i:s", strtotime("+1 minute"));
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                // File Uploads
                $uploadDir = 'uploads/ids/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $front_path = '';
                $back_path = '';

                if (isset($_FILES['id_front']) && $_FILES['id_front']['error'] === 0) {
                    $ext = pathinfo($_FILES['id_front']['name'], PATHINFO_EXTENSION);
                    $front_path = $uploadDir . $username . '_FRONT_' . time() . '.' . $ext;
                    move_uploaded_file($_FILES['id_front']['tmp_name'], $front_path);
                }
                if (isset($_FILES['id_back']) && $_FILES['id_back']['error'] === 0) {
                    $ext = pathinfo($_FILES['id_back']['name'], PATHINFO_EXTENSION);
                    $back_path = $uploadDir . $username . '_BACK_' . time() . '.' . $ext;
                    move_uploaded_file($_FILES['id_back']['tmp_name'], $back_path);
                }

                $query = "INSERT INTO users (username, email, password_hash, first_name, last_name, role, phone, birth_date, street, barangay, district, city, id_front_path, id_back_path, otp_code, otp_expiry, is_verified) 
                          VALUES (?, ?, ?, ?, ?, 'applicant', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";

                $db->query($query, [$username, $email, $passwordHash, $firstName, $lastName, $phone, $birthDate, $street, $barangay, $district, $city, $front_path, $back_path, $otp, $expiry]);

                // PHPMailer
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
                    $mail->addAddress($email);
                    $mail->isHTML(true);
                    $mail->Subject = 'Verify Your Account - OTP Code';
                    $mail->Body = "
                    <div style='background-color: #f9f9f9; padding: 40px 0; font-family: Helvetica, Arial, sans-serif;'>
                        <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05);'>
                            <div style='padding: 30px; text-align: center;'>
                                <h2 style='color: #285ccd; font-size: 24px; margin-bottom: 20px; font-weight: bold;'>Verification Code</h2>
                                
                                <p style='color: #555555; font-size: 16px; margin-bottom: 30px;'>Your One-Time Password (OTP) for registration is:</p>
                                
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
                    $otp_sent = true;
                    $email_for_otp = $email;
                } catch (Exception $e) {
                    $error = "Email failed. Error: {$mail->ErrorInfo}";
                }
            }
        }
    }

    // --- LOGIC 2: OTP CODE ---
    if (isset($_POST['otp_code_input'])) {
        // Siguraduhin na 6 digits talaga ang nakuha bago mag-query
        $input_otp = trim((string)$_POST['otp_code_input']); 
        $email = $_POST['email_hidden'];
        
        if (strlen($input_otp) === 6) {
            $user = $db->fetchOne("SELECT id, otp_code, otp_expiry FROM users WHERE email = ? AND is_verified = 0", [$email]);

            if ($user) {
                $db_otp = trim((string)$user['otp_code']);
                date_default_timezone_set('Asia/Manila');
                $currentTime = date("Y-m-d H:i:s");

                // Gumamit ng (string) casting para sa parehong panig
                if ((string)$db_otp === (string)$input_otp) {
                    if ($currentTime <= $user['otp_expiry']) {
                        $db->query("UPDATE users SET is_verified = 1, otp_code = NULL, otp_expiry = NULL WHERE email = ?", [$email]);
                        $success_msg = "Account verified successfully!";
                        $otp_sent = false;
                    } else {
                        $error = "OTP code has expired. Please request a new one.";
                        $otp_sent = true;
                        $email_for_otp = $email;
                    }
                } else {
                    // DEBUG: Pwede mong i-uncomment ito para makita ang difference
                    // $error = "Input: $input_otp | DB: $db_otp"; 
                    $error = "Incorrect OTP code. Please try again.";
                    $otp_sent = true;
                    $email_for_otp = $email;
                }
            }
        } else {
            $error = "Please enter the complete 6-digit code.";
            $otp_sent = true;
            $email_for_otp = $email;
        }
    }
}

$pageTitle = "Register - LGU Urban Planning System";
include __DIR__ . '/auth/header.php'; 
?>

<style>
    /* ── BASE STYLES ── */
    body { min-height: 100vh; display: flex; flex-direction: column; }

    .register-container {
        flex: 1 0 auto;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px 16px;
        margin-top: 10px;
    }

    /* Card: uses overflow:hidden + no padding trick so header fills top cleanly */
    .register-card {
        width: 100%;
        max-width: 500px;
        background: rgba(255, 255, 255, 0.85);
        border-radius: 18px;
        overflow: hidden;
        backdrop-filter: blur(15px);
        -webkit-backdrop-filter: blur(15px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.2);
    }

    /* Header sits flush at the top — no negative margin hack needed */
    .register-header {
        background: linear-gradient(135deg, #6384d2, #285ccd);
        padding: 20px 25px;
        text-align: center;
        color: white;
    }

    /* Body content inside card */
    .register-body {
        padding: 20px 25px;
    }

    .register-logo-wrap {
        width: 100px;
        height: 100px;
        margin: 0 auto 8px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .register-logo {
        width: 100%;
        height: 100%;
        object-fit: contain;
        margin-bottom: 0;
        filter:
            drop-shadow(0 0 6px rgba(255,255,255,0.9))
            drop-shadow(0 0 14px rgba(255,255,255,0.7))
            drop-shadow(0 0 24px rgba(255,255,255,0.5));
    }
    .form-label { font-size: 12px; font-weight: 600; color: #000; margin-bottom: 4px; }
    .form-control { background: rgba(255,255,255,0.7) !important; border: 1px solid rgba(0,0,0,0.1); border-radius: 10px; padding: 8px; font-size: 14px; }
    .btn-step { padding: 10px; background: linear-gradient(135deg, #6384d2, #285ccd); border: none; border-radius: 12px; color: #fff; font-weight: 600; }
    .btn-step:hover { background: linear-gradient(135deg, #4d76d6, #1651d0); color: #fff; }
    .form-step { display: none; }
    .form-step.active { display: block; animation: fadeIn 0.4s ease-in-out; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .step-indicator { display: flex; justify-content: center; gap: 10px; margin-bottom: 15px; }
    .dot { width: 8px; height: 8px; background: #cbd5e1; border-radius: 50%; }
    .dot.active { background: #285ccd; width: 22px; border-radius: 10px; transition: 0.3s; }
    .strength-meter { height: 5px; background-color: #e2e8f0; border-radius: 3px; margin-top: 6px; overflow: hidden; }
    .strength-bar { height: 100%; width: 0%; transition: all 0.3s ease; }
    .login-link { text-align: center; margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(0,0,0,0.1); font-size: 13px; }
    .login-link a { color: #285ccd; text-decoration: none; font-weight: 600; }
    .cursor-pointer { cursor: pointer; }

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
       768px (Tablet) | 480px (Large Mobile) | 320px (Small Mobile)
       ================================================ */

    /* --- 768px: Tablet --- */
    @media (max-width: 768px) {
        .register-container {
            padding: 20px 16px;
            margin-top: 8px;
            align-items: center;
        }
        .register-card {
            max-width: 480px;
            width: 100%;
            border-radius: 16px;
        }
        .register-header { padding: 18px 20px; }
        .register-body { padding: 14px 18px; }
        .register-logo-wrap { width: 70px; height: 70px; margin-bottom: 6px; }
        .register-header h5 { font-size: 1rem; }
        .form-label { font-size: 11.5px; margin-bottom: 2px; }
        .form-control { font-size: 13px; padding: 7px 10px; border-radius: 8px; }
        .btn-step { padding: 9px; font-size: 14px; border-radius: 10px; }
        /* Tighten Bootstrap row/col spacing */
        .register-body .row { --bs-gutter-y: 0.35rem; --bs-gutter-x: 0.5rem; }
        .register-body .mb-2 { margin-bottom: 0.35rem !important; }
        .register-body .mb-3 { margin-bottom: 0.5rem !important; }
        .register-body .mb-4 { margin-bottom: 0.6rem !important; }
        .register-body h6.fw-bold { margin-bottom: 0.5rem !important; }
        .login-link { margin-top: 10px; padding-top: 10px; font-size: 12px; }
        .otp-box { width: 44px !important; height: 48px !important; font-size: 1.2rem !important; padding: 4px !important; }
        #customAlertBox { max-width: 340px; padding: 28px 22px 20px; }
    }

    /* --- 480px: Large Mobile --- */
    @media (max-width: 480px) {
        .register-container { padding: 12px 10px; margin-top: 6px; align-items: center; }
        .register-card { max-width: 400px; width: 100%; border-radius: 14px; }
        .register-header { padding: 12px 16px; }
        .register-body { padding: 12px 14px; }
        .register-logo-wrap { width: 58px; height: 58px; margin-bottom: 4px; }
        .register-header h5 { font-size: 0.92rem; }
        .form-label { font-size: 11px; margin-bottom: 2px; }
        .form-control { font-size: 12px; padding: 6px 9px; border-radius: 8px; }
        h6.fw-bold { font-size: 0.85rem; }
        .btn-step { padding: 8px; font-size: 13px; border-radius: 9px; }
        .step-indicator { gap: 7px; margin-bottom: 8px; }
        /* Tighten Bootstrap spacing further */
        .register-body .row { --bs-gutter-y: 0.25rem; --bs-gutter-x: 0.4rem; }
        .register-body .mb-2 { margin-bottom: 0.25rem !important; }
        .register-body .mb-3 { margin-bottom: 0.4rem !important; }
        .register-body .mb-4 { margin-bottom: 0.5rem !important; }
        .register-body .mt-3 { margin-top: 0.4rem !important; }
        .register-body h6.fw-bold { margin-bottom: 0.4rem !important; }
        /* Stack address and ID upload cols */
        .register-card .col-4,
        .register-card .col-md-6 {
            width: 100% !important;
            flex: 0 0 100% !important;
            max-width: 100% !important;
        }
        .login-link { margin-top: 8px; padding-top: 8px; font-size: 11.5px; }
        .otp-box { width: 38px !important; height: 44px !important; font-size: 1.1rem !important; padding: 3px !important; }
        #customAlertBox { max-width: 300px; padding: 24px 18px 18px; border-radius: 16px; }
        #customAlertTitle { font-size: 0.95rem; }
        #customAlertMsg   { font-size: 0.8rem; }
        #customAlertBtn   { padding: 9px 28px; font-size: 0.88rem; }
    }

    /* --- 320px: Small Mobile --- */
    @media (max-width: 320px) {
        .register-container { padding: 8px 6px; margin-top: 4px; align-items: center; }
        .register-card { max-width: 300px; width: 100%; border-radius: 12px; }
        .register-header { padding: 10px 13px; }
        .register-body { padding: 10px 12px; }
        .register-logo-wrap { width: 48px; height: 48px; margin-bottom: 3px; }
        .register-header h5 { font-size: 0.83rem; }
        .form-label { font-size: 10.5px; margin-bottom: 1px; }
        .form-control { font-size: 11.5px; padding: 5px 8px; border-radius: 7px; }
        h6.fw-bold { font-size: 0.78rem; }
        .btn-step { padding: 7px; font-size: 12px; border-radius: 8px; }
        .step-indicator { gap: 5px; margin-bottom: 6px; }
        .dot { width: 7px; height: 7px; }
        .dot.active { width: 18px; }
        /* Minimum spacing — everything tight */
        .register-body .row { --bs-gutter-y: 0.2rem; --bs-gutter-x: 0.3rem; }
        .register-body .mb-2 { margin-bottom: 0.2rem !important; }
        .register-body .mb-3 { margin-bottom: 0.3rem !important; }
        .register-body .mb-4 { margin-bottom: 0.35rem !important; }
        .register-body .mt-3 { margin-top: 0.3rem !important; }
        .register-body .g-2 { --bs-gutter-y: 0.2rem !important; }
        .register-body h6.fw-bold { margin-bottom: 0.3rem !important; }
        .login-link { margin-top: 6px; padding-top: 6px; font-size: 11px; }
        .otp-box { width: 32px !important; height: 38px !important; font-size: 1rem !important; padding: 2px !important; border-radius: 6px !important; }
        #customAlertBox { max-width: 260px; padding: 20px 14px 16px; border-radius: 14px; }
        #customAlertTitle { font-size: 0.88rem; }
        #customAlertMsg   { font-size: 0.75rem; margin-bottom: 18px; }
        #customAlertBtn   { padding: 8px 22px; font-size: 0.82rem; border-radius: 10px; }
    }
</style>

<!-- ── MODERN CUSTOM ALERT OVERLAY ── -->
<div id="customAlertOverlay"
     data-register-error="<?php echo isset($error) ? addslashes($error) : ''; ?>"
     data-register-success="<?php echo isset($success_msg_resend) ? addslashes($success_msg_resend) : ''; ?>">
    <div id="customAlertBox">
        <div class="alert-icon-wrap" id="customAlertIconWrap">
            <span id="customAlertIcon"></span>
        </div>
        <div id="customAlertTitle"></div>
        <div id="customAlertMsg"></div>
        <button id="customAlertBtn" onclick="closeAlert()">Got it</button>
    </div>
</div>

<div class="register-container">
    <div class="register-card">
        <div class="register-header">
            <div class="register-logo-wrap">
                <img src="assets/upad-logo.png" alt="LGU Logo" class="register-logo">
            </div>
            <h5 class="fw-bold mb-1"><?php echo $success_msg ? 'Success!' : ($otp_sent ? 'Email Verification' : 'Create Your Account'); ?></h5>
            <?php if (!$success_msg && !$otp_sent): ?>
            <div class="step-indicator mt-3">
                <div class="dot active" id="dot1"></div>
                <div class="dot" id="dot2"></div>
                <div class="dot" id="dot3"></div>
            </div>
            <?php endif; ?>
        </div>

        <div class="register-body">
        <?php if ($success_msg): ?>
            <div class="text-center py-4">
                <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                <h4 class="fw-bold mt-3">Verified!</h4>
                <p class="text-muted"><?php echo $success_msg; ?></p>
                <a href="login.php" class="btn btn-step w-100">Login Now</a>
            </div>

        <?php elseif ($otp_sent): ?>
            <div class="py-3">
                <p class="text-center small">Please enter the 6-digit code sent to:<br><strong><?php echo htmlspecialchars($email_for_otp); ?></strong></p>
                    <form method="POST" id="otpForm">
                        <input type="hidden" name="email_hidden" value="<?php echo htmlspecialchars($email_for_otp); ?>">
                        <input type="hidden" name="otp_code_input" id="final_otp">
                        
                        <div class="d-flex justify-content-between gap-2 mb-4">
                            <input type="text" class="form-control otp-box" maxlength="1" pattern="\d*" inputmode="numeric">
                            <input type="text" class="form-control otp-box" maxlength="1" pattern="\d*" inputmode="numeric">
                            <input type="text" class="form-control otp-box" maxlength="1" pattern="\d*" inputmode="numeric">
                            <input type="text" class="form-control otp-box" maxlength="1" pattern="\d*" inputmode="numeric">
                            <input type="text" class="form-control otp-box" maxlength="1" pattern="\d*" inputmode="numeric">
                            <input type="text" class="form-control otp-box" maxlength="1" pattern="\d*" inputmode="numeric">
                        </div>
                        
                        <button type="submit" class="btn btn-step w-100 py-2">Verify My Account</button>
                    </form>
            <p class="text-center mt-3 small text-muted">
                Didn't get a code? 
                <a href="resend_otp.php?email=<?php echo urlencode($email_for_otp); ?>" class="text-primary fw-bold">Resend Code</a>
            </p>

    <?php else: ?>
        <form method="POST" enctype="multipart/form-data" id="regForm">
            <div class="form-step active" id="step1">
                <h6 class="fw-bold mb-3 text-dark"><i class="bi bi-person-circle me-2"></i>Personal Information</h6>
                <div class="row g-2"> 
                    <div class="col-md-6 mb-2">
                        <label class="form-label">First Name</label>
                        <input type="text" class="form-control" name="first_name" required>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Last Name</label>
                        <input type="text" class="form-control" name="last_name" required>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Birth Date</label>
                        <input type="date" class="form-control" name="birth_date" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone Number</label>
                        <div class="input-group">
                            <span class="input-group-text small" style="padding: 0 8px;">+63</span>
                            <input type="text" class="form-control" name="phone" maxlength="10" placeholder="9123456789" required>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn btn-step w-100" onclick="nextStep(2)">Next Step <i class="bi bi-arrow-right ms-2"></i></button>
            </div>

            <div class="form-step" id="step2">
                <h6 class="fw-bold mb-3 text-dark"><i class="bi bi-shield-lock-fill me-2"></i>Account Security</h6>
                <div class="row g-2 mb-2">
                    <div class="col-md-6">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" name="username" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-md-6">
                        <label class="form-label">Password</label>
                        <div class="position-relative">
                            <input type="password" class="form-control" name="password" id="reg_password" required>
                            <i class="bi bi-eye position-absolute top-50 end-0 translate-middle-y me-3 cursor-pointer" id="toggleIcon" onclick="togglePassword('reg_password', 'toggleIcon')"></i>
                        </div>
                        <div class="strength-meter">
                            <div id="strength-bar" class="strength-bar"></div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <small id="strength-text" style="font-size: 10px; font-weight: bold;"></small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Confirm Password</label>
                        <div class="position-relative">
                            <input type="password" class="form-control" name="confirm_password" id="confirm_password" required>
                            <i class="bi bi-eye position-absolute top-50 end-0 translate-middle-y me-3 cursor-pointer" id="toggleConfirmIcon" onclick="togglePassword('confirm_password', 'toggleConfirmIcon')"></i>
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button type="button" class="btn btn-light w-50 border" onclick="nextStep(1)">Back</button>
                    <button type="button" class="btn btn-step w-50" onclick="nextStep(3)">Next</button>
                </div>
            </div>

            <div class="form-step" id="step3">
                <h6 class="fw-bold mb-3 text-dark"><i class="bi bi-geo-alt-fill me-2"></i>Address & Verification</h6>
                <div class="mb-3">
                    <label class="form-label">Complete Address</label>
                    <div class="row g-2">
                        <div class="col-3"><input type="text" class="form-control" name="street" placeholder="Street" required></div>
                        <div class="col-3"><input type="text" class="form-control" name="barangay" placeholder="Barangay" required></div>
                        <div class="col-3"><input type="text" class="form-control" name="district" placeholder="District" required></div>
                        <div class="col-3"><input type="text" class="form-control" name="city" placeholder="City" required></div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Select Valid ID Type</label>
                    <select class="form-control" name="id_type" id="id_type" required onchange="toggleUploads()">
                        <option value="">-- Select ID --</option>
                        <option value="National ID">PhilSys (National ID)</option>
                        <option value="Drivers License">Driver's License</option>
                        <option value="Passport">Passport</option>
                    </select>
                </div>
                <div id="upload-section" style="display: none;">
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">ID Front</label>
                            <input type="file" class="form-control" name="id_front" id="id_front" accept="image/*">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ID Back</label>
                            <input type="file" class="form-control" name="id_back" id="id_back" accept="image/*">
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light w-50 border" onclick="nextStep(2)">Back</button>
                    <button type="submit" class="btn btn-step w-50">Create Account</button>
                </div>
            </div>
        </form>
        <?php endif; ?>

        <div class="login-link">
            <span>Already have an account?</span> 
            <a href="login.php">Login here</a>
        </div>

        </div><!-- /.register-body -->
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php include __DIR__ . '/auth/footer.php'; ?>