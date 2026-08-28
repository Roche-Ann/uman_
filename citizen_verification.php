<?php
// citizen_verification.php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'] ?? 0;
$error = '';
$success = '';

// Self-healing check (just in case they land here before an admin views users)
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'verification_status'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `verification_status` ENUM('unverified', 'pending', 'verified', 'rejected') DEFAULT 'unverified'");
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `id_image_path` VARCHAR(255) DEFAULT NULL");
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `selfie_image_path` VARCHAR(255) DEFAULT NULL");
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `is_archived` TINYINT(1) DEFAULT 0");
    }
} catch (PDOException $e) {}

// Fetch Current Status
$stmt = $pdo->prepare("SELECT verification_status, id_image_path, selfie_image_path FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
$status = $user['verification_status'] ?? 'unverified';

// Handle Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['id_image'], $_FILES['selfie_image'])) {
    if ($status === 'verified') {
        $error = "Your account is already verified.";
    } elseif ($status === 'pending') {
        $error = "Your verification is currently under review.";
    } else {
        $uploadDir = 'uploads/verifications/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $idFile = $_FILES['id_image'];
        $selfieFile = $_FILES['selfie_image'];

        // Basic validation
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($idFile['type'], $allowedTypes) || !in_array($selfieFile['type'], $allowedTypes)) {
            $error = "Only JPG, PNG, and WebP images are allowed.";
        } elseif ($idFile['size'] > 5000000 || $selfieFile['size'] > 5000000) {
            $error = "Images must be smaller than 5MB each.";
        } else {
            $idExt = pathinfo($idFile['name'], PATHINFO_EXTENSION);
            $selfieExt = pathinfo($selfieFile['name'], PATHINFO_EXTENSION);
            
            $idPath = $uploadDir . 'id_' . $userId . '_' . time() . '.' . $idExt;
            $selfiePath = $uploadDir . 'selfie_' . $userId . '_' . time() . '.' . $selfieExt;

            if (move_uploaded_file($idFile['tmp_name'], $idPath) && move_uploaded_file($selfieFile['tmp_name'], $selfiePath)) {
                try {
                    $updateStmt = $pdo->prepare("UPDATE users SET verification_status = 'pending', id_image_path = ?, selfie_image_path = ? WHERE id = ?");
                    $updateStmt->execute([$idPath, $selfiePath, $userId]);
                    $status = 'pending';
                    $success = "Verification documents submitted successfully. Please wait for an administrator to review them.";
                } catch (PDOException $e) {
                    $error = "Database error while saving verification data.";
                }
            } else {
                $error = "Failed to upload images. Please try again.";
            }
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
    <title>Identity Verification | UMAN</title>
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif; }
        body { min-height:100vh; display:flex; background:url("assets/images/cityhall.jpeg") center/cover no-repeat fixed; position:relative; justify-content:center; align-items:center; }
        body::before { content:""; position: fixed; top:0; left:0; width:100%; height:100%; backdrop-filter:blur(6px); background:rgba(0,0,0,0.35); z-index:0; }
        .main-content { z-index:1; position:relative; width:100%; max-width:600px; padding:20px; }
        
        .card { background:rgba(255,255,255,0.95); backdrop-filter:blur(15px); border-radius:18px; padding:40px; box-shadow:0 10px 30px rgba(0,0,0,0.2); border:1px solid rgba(255,255,255,0.25); text-align:center; }
        .card h1 { color:#1e293b; font-size:24px; margin-bottom:10px; }
        .card p { color:#475569; font-size:14px; margin-bottom:25px; line-height:1.5; }
        
        .status-box { padding:20px; border-radius:12px; margin-bottom:25px; display:flex; flex-direction:column; align-items:center; gap:10px; }
        .status-box i { font-size:40px; }
        .status-box h2 { font-size:18px; margin:0; }
        
        .status-verified { background:#ecfdf5; color:#059669; border:1px solid #a7f3d0; }
        .status-pending { background:#fefce8; color:#ca8a04; border:1px solid #fef08a; }
        .status-rejected { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; }
        .status-unverified { background:#f8fafc; color:#475569; border:1px solid #e2e8f0; }
        
        .upload-form { text-align:left; background:#f8fafc; padding:25px; border-radius:12px; border:1px solid #e2e8f0; }
        .form-group { margin-bottom:20px; }
        .form-group label { display:block; font-size:13px; font-weight:600; color:#1e293b; margin-bottom:8px; }
        .form-group input[type="file"] { display:block; width:100%; padding:10px; font-size:14px; color:#475569; border:2px dashed #cbd5e1; border-radius:8px; background:white; cursor:pointer; }
        
        .btn { width:100%; padding:14px; border-radius:8px; font-weight:600; font-size:15px; border:none; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; gap:8px; text-decoration:none; transition:all 0.2s; }
        .btn-primary { background:#3762c8; color:#fff; }
        .btn-primary:hover { background:#2851b0; }
        .btn-outline { background:transparent; border:1px solid #cbd5e1; color:#64748b; margin-top:15px; }
        .btn-outline:hover { background:#f1f5f9; }
        
        .alert { padding:15px; border-radius:8px; margin-bottom:20px; font-size:13px; text-align:left; }
        .alert-error { background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; }
        .alert-success { background:#ecfdf5; color:#047857; border:1px solid #a7f3d0; }
        
        /* Dark Theme */
        .dark-theme .card { background:rgba(30,41,59,0.95); border-color:rgba(255,255,255,0.1); color:#f8fafc; }
        .dark-theme .card h1 { color:#f8fafc; }
        .dark-theme .card p { color:#cbd5e1; }
        .dark-theme .upload-form { background:#0f172a; border-color:#334155; }
        .dark-theme .form-group label { color:#e2e8f0; }
        .dark-theme .form-group input[type="file"] { background:#1e293b; border-color:#475569; color:#f8fafc; }
        .dark-theme .status-unverified { background:#0f172a; border-color:#334155; color:#cbd5e1; }
        .dark-theme .status-verified { background:rgba(6, 78, 59, 0.4); color:#6ee7b7; border-color:#065f46; }
        .dark-theme .status-pending { background:rgba(113, 63, 18, 0.4); color:#fde047; border-color:#854d0e; }
        .dark-theme .status-rejected { background:rgba(127, 29, 29, 0.4); color:#fca5a5; border-color:#991b1b; }
    </style>
</head>
<body>
<main class="main-content">
    <div class="card">
        <h1>Identity Verification</h1>
        <p>To ensure the security and integrity of our LGU services, all citizens must verify their identity before requesting assets or equipment.</p>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($status === 'verified'): ?>
            <div class="status-box status-verified">
                <i class="fas fa-check-circle"></i>
                <h2>Identity Verified</h2>
                <span style="font-size:13px;">Your account is fully verified. You have full access to LGU services.</span>
            </div>
            <a href="citizen_asset_request.php" class="btn btn-primary"><i class="fas fa-arrow-right"></i> Continue to Asset Requests</a>
            <a href="citizen.php" class="btn btn-outline"><i class="fas fa-home"></i> Back to Dashboard</a>
            
        <?php elseif ($status === 'pending'): ?>
            <div class="status-box status-pending">
                <i class="fas fa-clock"></i>
                <h2>Verification Pending</h2>
                <span style="font-size:13px;">Your documents are currently under review by our staff. Please check back later.</span>
            </div>
            <a href="citizen.php" class="btn btn-outline"><i class="fas fa-home"></i> Back to Dashboard</a>
            
        <?php else: ?>
            <?php if ($status === 'rejected'): ?>
                <div class="status-box status-rejected">
                    <i class="fas fa-times-circle"></i>
                    <h2>Verification Rejected</h2>
                    <span style="font-size:13px;">Your previous submission was rejected. Please upload clear, readable images and try again.</span>
                </div>
            <?php else: ?>
                <div class="status-box status-unverified">
                    <i class="fas fa-shield-alt"></i>
                    <h2>Verification Required</h2>
                    <span style="font-size:13px;">Please upload your documents below.</span>
                </div>
            <?php endif; ?>
            
            <form class="upload-form" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="id_image"><i class="fas fa-id-card"></i> Upload Valid ID</label>
                    <input type="file" name="id_image" id="id_image" accept="image/jpeg, image/png, image/webp" required>
                    <span style="font-size:11px; color:#64748b; margin-top:4px; display:block;">Gov-issued ID (e.g. Passport, Driver's License, UMID)</span>
                </div>
                <div class="form-group">
                    <label for="selfie_image"><i class="fas fa-camera"></i> Upload Selfie with ID</label>
                    <input type="file" name="selfie_image" id="selfie_image" accept="image/jpeg, image/png, image/webp" required>
                    <span style="font-size:11px; color:#64748b; margin-top:4px; display:block;">Hold your ID next to your face. Ensure both are clear.</span>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Submit Documents</button>
            </form>
            
            <a href="citizen.php" class="btn btn-outline" style="background:transparent; border:none; box-shadow:none;"><i class="fas fa-arrow-left"></i> Skip for now (Limited Access)</a>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
