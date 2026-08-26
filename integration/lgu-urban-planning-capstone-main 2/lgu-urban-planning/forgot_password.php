<?php
session_start();
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$dbInstance   = Database::getInstance();
$alertType    = '';
$alertTitle   = '';
$alertMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $alertType    = 'error';
        $alertTitle   = 'Invalid Email';
        $alertMessage = 'Please enter a valid email address.';
    } else {
        $user = $dbInstance->fetchOne(
            "SELECT id, email, first_name FROM users WHERE email = ? LIMIT 1",
            [$email]
        );

        // Always show the same message to prevent user enumeration
        $alertType    = 'success';
        $alertTitle   = 'Email Sent';
        $alertMessage = 'If that email is registered, a password reset link has been sent. Please check your inbox.';

        if ($user) {
            $token  = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            $dbInstance->query(
                "UPDATE users SET reset_token = ?, token_expiry = ? WHERE id = ?",
                [$token, $expiry, $user['id']]
            );

            $resetLink = (isset($_SERVER['HTTPS']) ? 'https' : 'http')
                       . '://' . $_SERVER['HTTP_HOST']
                       . dirname($_SERVER['PHP_SELF'])
                       . '/reset_password.php?token=' . $token;

            $firstName = htmlspecialchars($user['first_name'] ?? 'User');

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
                $mail->Subject = 'Password Reset Request - LGU Urban Planning';
                $mail->isHTML(true);
                $mail->Body = "
                <div style='background-color:#f9f9f9;padding:40px 0;font-family:Helvetica,Arial,sans-serif;'>
                    <div style='max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #e0e0e0;
                                border-radius:8px;overflow:hidden;box-shadow:0 4px 10px rgba(0,0,0,0.05);'>
                        <div style='background:linear-gradient(135deg,#6384d2,#285ccd);padding:28px;text-align:center;'>
                            <h2 style='color:#ffffff;margin:0;font-size:22px;font-weight:bold;'>Password Reset Request</h2>
                        </div>
                        <div style='padding:32px;'>
                            <p style='color:#333333;font-size:15px;margin-bottom:12px;'>Hi <strong>{$firstName}</strong>,</p>
                            <p style='color:#555555;font-size:14px;line-height:1.6;margin-bottom:24px;'>
                                We received a request to reset your password for your LGU Urban Planning account.
                                Click the button below to create a new password.
                                This link is valid for <strong style='color:#285ccd;'>15 minutes</strong>.
                            </p>
                            <div style='text-align:center;margin:28px 0;'>
                                <a href='{$resetLink}'
                                   style='background:linear-gradient(135deg,#6384d2,#285ccd);color:#ffffff;
                                          text-decoration:none;padding:14px 36px;border-radius:10px;
                                          font-weight:600;font-size:15px;display:inline-block;'>
                                    Reset My Password
                                </a>
                            </div>
                            <p style='color:#777777;font-size:13px;margin-bottom:4px;'>
                                If the button doesn't work, copy and paste this link into your browser:
                            </p>
                            <p style='word-break:break-all;color:#285ccd;font-size:12px;'>{$resetLink}</p>
                            <hr style='border:none;border-top:1px solid #eeeeee;margin:24px 0;'>
                            <p style='color:#999999;font-size:12px;'>
                                If you did not request a password reset, please ignore this email — your password will remain unchanged.
                            </p>
                        </div>
                    </div>
                </div>";

                $mail->send();
            } catch (Exception $e) {
                error_log("Password reset mail failed: {$mail->ErrorInfo}");
            }
        }
    }
}

$pageTitle = "Forgot Password - LGU Urban Planning System";
include __DIR__ . '/auth/header.php';
?>

<style>
    .login-container { flex: 1; display: flex; align-items: center; justify-content: center; padding: 10px; margin-top: 5px; }
    .login-card { width: 100%; max-width: 380px; background: rgba(255,255,255,0.85); padding: 15px 32px 28px; border-radius: 18px; backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); box-shadow: 0 8px 25px rgba(0,0,0,0.2); position: relative; overflow: hidden; }
    .login-header { background: linear-gradient(135deg, #6384d2, #285ccd); margin: -28px -32px 25px -32px; padding: 20px; text-align: center; color: white; }
    .login-logo { width: 80px; margin-bottom: 10px; }
    .form-label { font-size: 13px; font-weight: 600; color: #000; margin-bottom: 5px; }
    .form-control { background: rgba(255,255,255,0.7) !important; border: 1px solid rgba(0,0,0,0.1); border-radius: 10px; padding: 10px 12px; color: #000; font-size: 13px; }
    .btn-login { width: 100%; padding: 10px; background: linear-gradient(135deg, #6384d2, #285ccd); border: none; border-radius: 12px; color: #fff; font-size: 15px; font-weight: 600; transition: 0.25s ease; }
    .btn-login:hover { background: linear-gradient(135deg, #4d76d6, #1651d0); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(40,92,205,0.4); }

    #customAlertOverlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 9999; align-items: center; justify-content: center; animation: alertFadeIn .2s ease; }
    #customAlertOverlay.show { display: flex; }
    #customAlertBox { background: #fff; border-radius: 20px; padding: 36px 28px; max-width: 360px; width: 90%; text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,.15); animation: alertSlideUp .25s ease; }
    @keyframes alertFadeIn  { from { opacity: 0; } to { opacity: 1; } }
    @keyframes alertSlideUp { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    .alert-icon-wrap { width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; font-size: 1.8rem; }
    .alert-icon-wrap.error   { background: #fef2f2; }
    .alert-icon-wrap.warning { background: #fffbeb; }
    .alert-icon-wrap.success { background: #f0fdf4; }
    .alert-icon-wrap.info    { background: #eff6ff; }
    #customAlertTitle { font-size: 1.05rem; font-weight: 700; margin-bottom: 8px; color: #1e293b; }
    #customAlertMsg   { font-size: 0.875rem; color: #64748b; margin-bottom: 24px; line-height: 1.5; }
    #customAlertBtn { padding: 10px 36px; border: none; border-radius: 12px; background: linear-gradient(135deg, #6384d2, #285ccd); color: #fff; font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: 0.2s; }
    #customAlertBtn:hover { background: linear-gradient(135deg, #4d76d6, #1651d0); transform: translateY(-1px); }

    @media (max-width: 768px) {
        .login-container { padding: 15px; }
        .login-card { width: 280px; padding: 0 0 18px; border-radius: 12px; overflow: hidden; }
        .login-header { padding: 12px; margin: 0; }
        .login-logo { width: 35px; margin-bottom: 5px; }
    }
</style>

<div id="customAlertOverlay"
     data-alert-type="<?php echo $alertType; ?>"
     data-alert-title="<?php echo addslashes($alertTitle); ?>"
     data-alert-msg="<?php echo addslashes($alertMessage); ?>">
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
            <img src="assets/img/lgu-logo.png" alt="LGU Logo" class="login-logo">
            <h5 class="fw-bold mb-1">LGU Urban Planning</h5>
            <div class="small opacity-75" style="font-size:0.65rem;">Development Permit Management System</div>
        </div>

        <div style="padding: 0 6px;">
            <h5 class="text-center mb-2 fw-bold text-dark">Forgot Password?</h5>
            <p class="text-center text-muted mb-4" style="font-size:12px; line-height:1.5;">
                Enter your registered email and we'll send you a link to reset your password.
            </p>

            <form method="POST">
                <div class="mb-4">
                    <label class="form-label">
                        <i class="bi bi-envelope me-1"></i> Email Address
                    </label>
                    <input type="email" class="form-control shadow-sm" name="email"
                           placeholder="Enter your registered email" required>
                </div>

                <button type="submit" class="btn btn-login shadow-sm">
                    <i class="bi bi-send me-2"></i> Send Reset Link
                </button>
            </form>

            <div class="text-center mt-4">
                <a href="login.php" class="small text-decoration-none fw-bold" style="color:#2864ef;">
                    <i class="bi bi-arrow-left me-1"></i> Back to Login
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const overlay = document.getElementById('customAlertOverlay');
    if (overlay) {
        overlay.addEventListener('click', e => { if (e.target === overlay) closeAlert(); });
        const type  = overlay.dataset.alertType;
        const title = overlay.dataset.alertTitle;
        const msg   = overlay.dataset.alertMsg;
        if (type && title && msg) showAlert(type, title, msg);
    }
});
</script>

<?php include __DIR__ . '/auth/footer.php'; ?>