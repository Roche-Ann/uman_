<?php
// login.php - REMOVED session_start() from here
require_once 'includes/auth.php';

// Add the Symfony Mailer classes at the top
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

// Ensure composer autoload is included for the Mailer (adjust path if needed)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Redirect if already logged in
if (isLoggedIn()) {
    if ($_SESSION['user_type'] === 'employee') {
        header('Location: utilities_dashboard.php');
    } else {
        header('Location: citizen.php');
    }
    exit();
}

$error = '';
$warning = '';

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
                // Setup Session directly and redirect
                $_SESSION['user_id']   = $userId;
                $_SESSION['user_type'] = $userType;
                $_SESSION['user_name'] = $fullName;
                $_SESSION['full_name'] = $fullName;
                $_SESSION['user_email']= $email;
                $_SESSION['logged_in'] = true;
                $_SESSION['show_welcome_modal'] = true;

                if ($userType === 'employee') {
                    header('Location: utilities_dashboard.php');
                } else {
                    header('Location: citizen.php');
                }
                exit();
            } else {
                $error = "System Error: Could not retrieve User ID.";
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LGU | Login</title>
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
    body {
        height: 100vh;
        display: flex;
        flex-direction: column;
        background: url("assets/images/cityhall.jpeg") center/cover no-repeat fixed;
        position: relative;
        overflow: hidden;
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
    
    .nav, .wrapper, .footer {
        position: relative;
        z-index: 1;
    }
    
    .nav-left {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
    }
    
    .date-time {
        font-size: 12px;
        color: #fff;
        opacity: 0.9;
        margin-bottom: 1px;
        font-weight: 1000;
    }
    
    .error-message {
        background-color: rgba(220, 53, 69, 0.1);
        color: #dc3545;
        padding: 10px 15px;
        border-radius: 8px;
        margin-bottom: 15px;
        font-size: 14px;
        text-align: center;
        border: 1px solid rgba(220, 53, 69, 0.3);
    }
    
    .attempt-warning {
        background-color: rgba(255, 193, 7, 0.1);
        color: #856404;
        padding: 8px 15px;
        border-radius: 8px;
        margin-bottom: 10px;
        font-size: 13px;
        text-align: center;
        border: 1px solid rgba(255, 193, 7, 0.3);
    }
    
    .card {
        background: rgba(255, 255, 255, 0.25) !important;
        backdrop-filter: blur(15px) !important;
        -webkit-backdrop-filter: blur(15px) !important;
    }
    
    .card .title {
        color: #fff !important;
    }
    
    .card .subtitle {
        color: #f0f0f0 !important;
    }
    
    .card .input-box {
        color: #fff !important;
        margin-bottom: 15px;
    }
    
    .card .input-box label {
        color: #fff !important;
    }
    
    .card .small-text {
        color: #ffffffcc !important;
    }

    /* Checkbox Styling */
    .show-password-container {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: -5px;
        margin-bottom: 25px;
        padding-left: 5px;
    }

    .show-password-container input[type="checkbox"] {
        width: 15px;
        height: 15px;
        cursor: pointer;
        margin: 0;
        accent-color: #0d6efd;
    }

    .show-password-container label {
        color: #ffffffcc;
        font-size: 13px;
        cursor: pointer;
        margin: 0;
        font-weight: 500;
        user-select: none;
    }

    /* Forgot Password Link Styling */
    .forgot-link {
        color: #ffffffcc;
        font-size: 13px;
        text-decoration: none;
        font-weight: 500;
        transition: color 0.3s;
    }

    .forgot-link:hover {
        color: #fff;
        text-decoration: underline;
    }
    
    .bottom-links {
        margin-top: 15px;
        text-align: center;
    }
    </style>
</head>
<body>

<header class="nav">
    <div class="nav-left">
        <div class="date-time" id="dateTime"></div>
        <div class="nav-logo">🏛️ Local Government Unit Portal</div>
    </div>
    <div class="nav-links">
        <a href="home.php">Home</a>
    </div>
</header>

<div class="wrapper">
    <div class="card">
        <img src="assets/images/logocityhall.png" class="icon-top">
        <h2 class="title">LGU Login</h2>
        <p class="subtitle">Secure access to community maintenance services.</p>

        <form method="POST" action="">
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($warning): ?>
                <div class="attempt-warning"><?php echo htmlspecialchars($warning); ?></div>
            <?php endif; ?>

            <div class="input-box">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="name@lgu.gov.ph" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                <span class="icon">📧</span>
            </div>

            <div class="input-box" style="margin-bottom: 10px;">
                <label>Password</label>
                <input type="password" id="login-password" name="password" placeholder="••••••••" required>
                <span class="icon">🔒</span>
            </div>

            <div class="show-password-container">
                <input type="checkbox" id="toggle-password">
                <label for="toggle-password">Show password</label>
            </div>

            <button type="submit" class="btn-primary">Sign In</button>

            <div class="bottom-links">
                <p class="small-text" style="margin-bottom: 8px;">
                    <a href="forgot.php" class="forgot-link">Forgot Password?</a>
                </p>
                <p class="small-text">Don't have an account?
                    <a href="create.php" class="link">Create one</a>
                </p>
            </div>
        </form>
    </div>
</div>

<footer class="footer">
    <div class="footer-links">
        <a href="#">Privacy Policy</a>
        <a href="#">About</a>
        <a href="#">Help</a>
    </div>
    <div class="footer-logo">
        © 2025 LGU Citizen Portal · All Rights Reserved
    </div>
</footer>

<script>
// Date Time Update Function
function updateDateTime() {
    const now = new Date();
    const options = { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    };
    const dateTimeString = now.toLocaleString('en-US', options);
    document.getElementById('dateTime').textContent = dateTimeString;
}
updateDateTime();
setInterval(updateDateTime, 1000);

// Show/Hide Password Toggle Logic
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

</body>
</html>