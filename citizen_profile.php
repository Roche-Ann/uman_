<?php
// citizen_profile.php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'] ?? 3;

$error = '';
$success = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        if (empty($fullname) || empty($email)) {
            $error = "Full Name and Email cannot be empty.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
                $stmt->execute([$fullname, $email, $userId]);
                
                $_SESSION['user_name'] = $fullname;
                $_SESSION['full_name'] = $fullname;
                
                $success = "Profile details updated successfully!";
            } catch (PDOException $e) {
                $error = "Failed to update profile: " . $e->getMessage();
            }
        }
    } elseif ($action === 'update_password') {
        $curr = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        
        if (empty($curr) || empty($new) || empty($confirm)) {
            $error = "All password fields are required.";
        } elseif ($new !== $confirm) {
            $error = "New password and confirmation password do not match.";
        } else {
            try {
                // Verify current password
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $dbPass = $stmt->fetchColumn();
                
                if (!password_verify($curr, $dbPass)) {
                    $error = "Incorrect current password.";
                } else {
                    $hashed = password_hash($new, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed, $userId]);
                    $success = "Password successfully changed!";
                }
            } catch (PDOException $e) {
                $error = "Failed to update password: " . $e->getMessage();
            }
        }
    }
}

// Retrieve profile
$stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
$stmt->execute([$userId]);
$profile = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Account Settings</title>
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            background: url("assets/images/cityhall.jpeg") center/cover no-repeat fixed;
            position: relative;
        }

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

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px 40px;
            transition: margin-left 0.25s ease;
            z-index: 1;
            position: relative;
        }

        .main-content.collapsed {
            margin-left: 90px;
        }

        .card {
            width: 100%;
            max-width: 1700px;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(15px);
            border-radius: 18px;
            padding: 40px;
            color: #000;
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,0.25);
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .dashboard-header h1 {
            color: #2c3e50;
            font-size: 32px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .dashboard-header h1 i { color: #3762c8; }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error { background-color: #fde8e8; color: #c0392b; border: 1px solid #f8b4b4; }
        .alert-success { background-color: #e2fbe8; color: #1e7e34; border: 1px solid #b8f0c5; }

        .btn {
            padding: 11px 22px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary { background: #3762c8; color: white; }
        .btn-primary:hover { background: #2851b0; }

        .btn-outline { background: transparent; border: 1px solid #cbd5e1; color: #64748b; }
        .btn-outline:hover { background: #f8f9fa; color: #2c3e50; }

        /* Two Column Layout */
        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        @media (max-width: 850px) {
            .settings-grid { grid-template-columns: 1fr; }
        }

        .box {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .box h3 {
            font-size: 16px;
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 2px solid #f1f2f6;
            padding-bottom: 10px;
        }

        .form-control { padding: 10px 14px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 14px; outline: none; width: 100%; margin-top: 4px; }
        .form-group { display: flex; flex-direction: column; margin-bottom: 15px; }
        .form-group label { font-size: 12px; font-weight: 600; color: #64748b; }
    </style>
</head>
<body>

<?php include 'includes/utilities_sidebar.php'; ?>

<main class="main-content" id="mainContent">
    <div class="card">
        
        <!-- Header -->
        <div class="dashboard-header">
            <div>
                <h1><i class="fas fa-user-cog"></i> Profile Settings</h1>
                <p style="color: #64748b; font-size: 14px; margin-top: 5px;">Update your contact information or change your LGU portal password.</p>
            </div>
            <div>
                <a href="citizen.php" class="btn btn-outline"><i class="fas fa-chevron-left"></i> Home</a>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="settings-grid">
            
            <!-- Left: Personal Information -->
            <div class="box">
                <h3><i class="fas fa-user"></i> Personal Details</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="fullname" class="form-control" value="<?php echo htmlspecialchars($profile['full_name'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>" required>
                    </div>

                    <button type="submit" class="btn btn-primary" style="margin-top:10px;">Save Profile Details</button>
                </form>
            </div>

            <!-- Right: Change Password -->
            <div class="box">
                <h3><i class="fas fa-key"></i> Security Settings</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update_password">

                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" class="form-control" required placeholder="Enter current password">
                    </div>

                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" class="form-control" required placeholder="Enter new password">
                    </div>

                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required placeholder="Retype new password">
                    </div>

                    <button type="submit" class="btn btn-primary" style="margin-top:10px;">Change Password</button>
                </form>
            </div>

        </div>

    </div>
</main>

</body>
</html>
