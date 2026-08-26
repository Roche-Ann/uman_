<?php
session_start();
require_once __DIR__ . '/core/Database.php';

$dbInstance   = Database::getInstance();
$alertType    = '';
$alertTitle   = '';
$alertMessage = '';
$redirectOnClose = ''; // redirect URL injected into JS after alert closes
$tokenValid   = false;
$token        = trim($_GET['token'] ?? '');

// Validate token on every load
if (empty($token)) {
    $alertType    = 'error';
    $alertTitle   = 'Invalid Link';
    $alertMessage = 'No reset token was provided. Please request a new password reset link.';
    $redirectOnClose = 'forgot_password.php';
} else {
    $user = $dbInstance->fetchOne(
        "SELECT id, email FROM users 
         WHERE reset_token = ? AND token_expiry > NOW() LIMIT 1",
        [$token]
    );

    if (!$user) {
        $alertType    = 'error';
        $alertTitle   = 'Invalid or Expired Link';
        $alertMessage = 'This password reset link is invalid or has already expired. Please request a new one.';
        $redirectOnClose = 'forgot_password.php';
    } else {
        $tokenValid = true;
    }
}

// -------------------------------------------------------
// Handle form submission
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    $newPassword     = $_POST['new_password']     ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validate password strength (matches registration rules)
    if (strlen($newPassword) < 8 || !preg_match('/[A-Z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
        $alertType    = 'warning';
        $alertTitle   = 'Weak Password';
        $alertMessage = 'Password must be at least 8 characters with at least one uppercase letter and one number.';
    } elseif ($newPassword !== $confirmPassword) {
        $alertType    = 'error';
        $alertTitle   = 'Password Mismatch';
        $alertMessage = 'Passwords do not match. Please try again.';
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

        $dbInstance->query(
            "UPDATE users 
             SET password_hash = ?, reset_token = NULL, token_expiry = NULL 
             WHERE id = ?",
            [$hashedPassword, $user['id']]
        );

        $alertType       = 'success';
        $alertTitle      = 'Password Updated';
        $alertMessage    = 'Your password has been changed successfully. You can now log in with your new password.';
        $redirectOnClose = 'login.php';
        $tokenValid      = false; // hide the form after success
    }
}

$pageTitle = "Reset Password - LGU Urban Planning System";
include __DIR__ . '/auth/header.php';
?>

<style>
    .login-container { flex: 1; display: flex; align-items: center; justify-content: center; padding: 10px; margin-top: 5px; }
    .login-card { width: 100%; max-width: 400px; background: rgba(255,255,255,0.85); padding: 15px 32px 28px; border-radius: 18px; backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); box-shadow: 0 8px 25px rgba(0,0,0,0.2); position: relative; overflow: hidden; }
    .login-header { background: linear-gradient(135deg, #6384d2, #285ccd); margin: -28px -32px 25px -32px; padding: 20px; text-align: center; color: white; }
    .login-logo { width: 80px; margin-bottom: 10px; }
    .form-label { font-size: 13px; font-weight: 600; color: #000; margin-bottom: 5px; }
    .form-control { background: rgba(255,255,255,0.7) !important; border: 1px solid rgba(0,0,0,0.1); border-radius: 10px; padding: 10px 12px; color: #000; font-size: 13px; }
    .btn-login { width: 100%; padding: 10px; background: linear-gradient(135deg, #6384d2, #285ccd); border: none; border-radius: 12px; color: #fff; font-size: 15px; font-weight: 600; transition: 0.25s ease; }
    .btn-login:hover { background: linear-gradient(135deg, #4d76d6, #1651d0); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(40,92,205,0.4); }

    /* Password wrapper (same as login) */
    .password-wrapper { position: relative; }
    .password-wrapper .password-toggle { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #6b7280; font-size: 1rem; }

    /* Strength meter */
    .strength-bar-track { height: 5px; background: #e2e8f0; border-radius: 4px; margin-top: 6px; overflow: hidden; }
    #strength-bar { height: 100%; width: 0; border-radius: 4px; transition: width .3s, background-color .3s; }
    #strength-text { font-size: 11px; font-weight: 600; margin-top: 3px; }

    /* Alert modal */
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
        .login-card { width: 290px; padding: 0 0 18px; border-radius: 12px; overflow: hidden; }
        .login-header { padding: 12px; margin: 0; }
        .login-logo { width: 35px; margin-bottom: 5px; }
    }
</style>

<!-- Alert Overlay -->
<div id="customAlertOverlay"
     data-alert-type="<?php echo $alertType; ?>"
     data-alert-title="<?php echo addslashes($alertTitle); ?>"
     data-alert-msg="<?php echo addslashes($alertMessage); ?>"
     data-redirect="<?php echo $redirectOnClose; ?>">
    <div id="customAlertBox">
        <div class="alert-icon-wrap" id="customAlertIconWrap">
            <span id="customAlertIcon"></span>
        </div>
        <div id="customAlertTitle"></div>
        <div id="customAlertMsg"></div>
        <button id="customAlertBtn" onclick="closeAlertAndRedirect()">Got it</button>
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
            <?php if ($tokenValid): ?>
                <h5 class="text-center mb-2 fw-bold text-dark">Set New Password</h5>
                <p class="text-center text-muted mb-4" style="font-size:12px; line-height:1.5;">
                    Choose a strong password with at least 8 characters, one uppercase letter, and one number.
                </p>

                <form method="POST" action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>">

                    <!-- New Password -->
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="bi bi-lock me-1"></i> New Password
                        </label>
                        <div class="password-wrapper">
                            <input type="password" id="new_password" name="new_password"
                                   class="form-control shadow-sm"
                                   placeholder="Enter new password" required>
                            <i class="bi bi-eye password-toggle"
                               onclick="togglePassword('new_password', 'toggleNew')" id="toggleNew"></i>
                        </div>
                        <!-- Strength meter -->
                        <div class="strength-bar-track">
                            <div id="strength-bar"></div>
                        </div>
                        <div id="strength-text" class="text-muted"></div>
                    </div>

                    <!-- Confirm Password -->
                    <div class="mb-4">
                        <label class="form-label">
                            <i class="bi bi-lock-fill me-1"></i> Confirm New Password
                        </label>
                        <div class="password-wrapper">
                            <input type="password" id="confirm_password" name="confirm_password"
                                   class="form-control shadow-sm"
                                   placeholder="Re-enter new password" required>
                            <i class="bi bi-eye password-toggle"
                               onclick="togglePassword('confirm_password', 'toggleConfirm')" id="toggleConfirm"></i>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-login shadow-sm">
                        <i class="bi bi-check2-circle me-2"></i> Update Password
                    </button>
                </form>

            <?php else: ?>
                <!-- Token invalid or already used — show friendly message -->
                <div class="text-center py-3">
                    <i class="bi bi-shield-x" style="font-size:3rem; color:#ef4444;"></i>
                    <h6 class="mt-3 fw-bold text-dark">Link Unavailable</h6>
                    <p class="text-muted" style="font-size:12px;">
                        This reset link is invalid or has already been used.
                        Please request a new one.
                    </p>
                    <a href="forgot_password.php" class="btn btn-login shadow-sm mt-2" style="font-size:13px;">
                        <i class="bi bi-arrow-clockwise me-1"></i> Request New Link
                    </a>
                </div>
            <?php endif; ?>

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

    // ---- Alert display + optional redirect on close ----
    if (overlay) {
        const type     = overlay.dataset.alertType;
        const title    = overlay.dataset.alertTitle;
        const msg      = overlay.dataset.alertMsg;
        if (type && title && msg) showAlert(type, title, msg);
    }

    // ---- Password strength meter ----
    const newPassInput = document.getElementById('new_password');
    if (newPassInput) {
        newPassInput.addEventListener('input', function () {
            const password = this.value;
            const bar  = document.getElementById('strength-bar');
            const text = document.getElementById('strength-text');
            if (!bar || !text) return;

            let strength = 0;
            if (password.length >= 8)            strength++;
            if (/[A-Z]/.test(password))          strength++;
            if (/[a-z]/.test(password))          strength++;
            if (/[0-9]/.test(password))          strength++;
            if (/[^A-Za-z0-9]/.test(password))   strength++;

            const cfg = [
                { w: '0%',   c: '#ef4444', t: 'Too Short'   },
                { w: '20%',  c: '#ef4444', t: 'Very Weak'   },
                { w: '40%',  c: '#f97316', t: 'Weak'        },
                { w: '60%',  c: '#eab308', t: 'Good'        },
                { w: '80%',  c: '#2563eb', t: 'Strong'      },
                { w: '100%', c: '#22c55e', t: 'Very Strong' },
            ];

            bar.style.width           = cfg[strength].w;
            bar.style.backgroundColor = cfg[strength].c;
            text.textContent          = cfg[strength].t;
            text.style.color          = cfg[strength].c;
        });
    }
});

// Close alert and optionally redirect
function closeAlertAndRedirect() {
    const overlay  = document.getElementById('customAlertOverlay');
    const redirect = overlay ? overlay.dataset.redirect : '';
    closeAlert();
    if (redirect) window.location.href = redirect;
}
</script>

<?php include __DIR__ . '/auth/footer.php'; ?>