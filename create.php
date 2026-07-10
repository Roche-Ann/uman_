<?php
// create.php - REMOVE session_start() from here too
require_once 'includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . ($_SESSION['user_type'] == 'employee' ? 'employee.php' : 'citizen.php'));
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
    
    // Validation
    if (empty($full_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        $result = registerUser($full_name, $email, $password);
        
        if ($result['success']) {
            $success = 'Registration successful! Redirecting...';
            echo "<script>
                setTimeout(function() {
                    window.location.href = 'citizen.php';
                }, 1500);
            </script>";
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Account | LGU Portal</title>
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

.nav, .wrapper {
    position: relative;
    z-index: 1;
}

.card {
    background: rgba(255, 255, 255, 0.795) !important;
    backdrop-filter: none !important;
    animation: slideDown 0.7s cubic-bezier(0.34, 1.56, 0.64, 1);
    opacity: 0;
    animation-fill-mode: forwards;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-60px) scale(0.95); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.success-message {
    background-color: rgba(40, 167, 69, 0.1);
    color: #28a745;
    padding: 10px 15px;
    border-radius: 8px;
    margin-bottom: 15px;
    font-size: 14px;
    text-align: center;
    border: 1px solid rgba(40, 167, 69, 0.3);
}
</style>
</head>

<body>

<header class="nav">
    <div class="nav-logo">🏛️ LGU Portal</div>
    <div class="nav-links">
        <a href="login.php">Login</a>
        <a class="active">Create Account</a>
    </div>
</header>

<div class="wrapper">
    <div class="card">
        <img src="assets/images/logocityhall.png" class="icon-top">
        <h2 class="title">Create Account</h2>
        <p class="subtitle">Register to access the LGU maintenance system.</p>

        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="input-box">
                <label>Full Name</label>
                <input type="text" name="full_name" placeholder="Juan Dela Cruz" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                <span class="icon">👤</span>
            </div>

            <div class="input-box">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="name@lgu.gov.ph" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                <span class="icon">📧</span>
            </div>

            <div class="input-box">
                <label>Password (min. 6 characters)</label>
                <input type="password" name="password" placeholder="••••••••" required>
                <span class="icon">🔒</span>
            </div>

            <div class="input-box">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" placeholder="••••••••" required>
                <span class="icon">🔒</span>
            </div>

            <button type="submit" class="btn-primary">Create Account</button>

            <p class="small-text">
                Already registered?
                <a href="login.php" class="link">Sign In</a>
            </p>
        </form>
    </div>
</div>

</body>
</html>