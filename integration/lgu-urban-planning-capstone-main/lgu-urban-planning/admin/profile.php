<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helper.php';
require_once __DIR__ . '/../core/Auth.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$pageTitle = 'My Profile';
$isAuthPage = true;

// Fetch full user record
$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);

// Logic to combine first and last name for the UI
if ($user) {
    $user['full_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
}

$success = '';
$errors  = [];

// ── Audit log helper ─────────────────────────────────────────────────────────
function logAudit($pdo, $userId, $action, $entityType, $entityId, $details) {
    $ip        = $_SERVER['REMOTE_ADDR']     ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $stmt = $pdo->prepare(
        "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    $stmt->execute([$userId, $action, $entityType, $entityId, $details, $ip, $userAgent]);
}

// ── Handle profile update ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    $pdo = $db->getConnection();

    // -- Save profile info ---------------------------------------------------
    if ($_POST['action'] === 'update_profile') {
        $fullNameInput = trim($_POST['full_name'] ?? '');
        $email         = trim($_POST['email']     ?? '');
        $phone         = trim($_POST['phone']     ?? '');
        $street        = trim($_POST['street']    ?? '');
        $barangay      = trim($_POST['barangay']  ?? '');
        $city          = trim($_POST['city']      ?? '');

        if (empty($fullNameInput)) {
            $errors[] = 'Full name is required.';
        } else {
            $nameParts = explode(' ', $fullNameInput, 2);
            $firstName = $nameParts[0];
            $lastName  = $nameParts[1] ?? '';
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
            $errors[] = 'A valid email address is required.';

        $existing = $db->fetchOne(
            "SELECT id FROM users WHERE email = ? AND id != ?",
            [$email, $userId]
        );
        if ($existing) $errors[] = 'That email is already in use by another account.';

        if (empty($errors)) {
            // Snapshot old values for audit diff
            $oldFullName = $user['full_name'];
            $oldEmail    = $user['email'];
            $oldPhone    = $user['phone']    ?? '';
            $oldStreet   = $user['street']   ?? '';
            $oldBarangay = $user['barangay'] ?? '';
            $oldCity     = $user['city']     ?? '';

            $stmt = $pdo->prepare(
                "UPDATE users
                 SET first_name = ?, last_name = ?, email = ?, phone = ?,
                     street = ?, barangay = ?, city = ?
                 WHERE id = ?"
            );
            $stmt->execute([$firstName, $lastName, $email, $phone, $street, $barangay, $city, $userId]);

            $_SESSION['full_name'] = $fullNameInput;

            // Build human-readable diff for audit log
            $changes = [];
            if ($oldFullName !== $fullNameInput)
                $changes[] = "Full Name: \"{$oldFullName}\" → \"{$fullNameInput}\"";
            if ($oldEmail !== $email)
                $changes[] = "Email: \"{$oldEmail}\" → \"{$email}\"";
            if ($oldPhone !== $phone)
                $changes[] = "Phone: \"" . ($oldPhone ?: 'Not set') . "\" → \"" . ($phone ?: 'Not set') . "\"";
            if ($oldStreet !== $street)
                $changes[] = "Street: \"" . ($oldStreet ?: 'Not set') . "\" → \"" . ($street ?: 'Not set') . "\"";
            if ($oldBarangay !== $barangay)
                $changes[] = "Barangay: \"" . ($oldBarangay ?: 'Not set') . "\" → \"" . ($barangay ?: 'Not set') . "\"";
            if ($oldCity !== $city)
                $changes[] = "City: \"" . ($oldCity ?: 'Not set') . "\" → \"" . ($city ?: 'Not set') . "\"";

            $details = empty($changes)
                ? 'Profile saved with no field changes.'
                : implode('; ', $changes);

            logAudit($pdo, $userId, 'Profile Update', 'user', $userId, $details);

            $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
            $user['full_name'] = trim($user['first_name'] . ' ' . $user['last_name']);

            $success = 'Profile updated successfully.';
        }
    }

    // -- Change password -----------------------------------------------------
    if ($_POST['action'] === 'change_password') {
        $currentPw = $_POST['current_password'] ?? '';
        $newPw     = $_POST['new_password']      ?? '';
        $confirmPw = $_POST['confirm_password']  ?? '';

        // password column is named password_hash in the DB
        if (!password_verify($currentPw, $user['password_hash']))
            $errors[] = 'Current password is incorrect.';
        if (strlen($newPw) < 8)
            $errors[] = 'New password must be at least 8 characters.';
        if ($newPw !== $confirmPw)
            $errors[] = 'New passwords do not match.';

        if (empty($errors)) {
            $hashed = password_hash($newPw, PASSWORD_DEFAULT);
            $stmt   = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hashed, $userId]);
            logAudit($pdo, $userId, 'Password Change', 'user', $userId, 'User changed their own account password.');
            $success = 'Password changed successfully.';
        }
    }

    // -- Upload avatar -------------------------------------------------------
    if ($_POST['action'] === 'upload_avatar') {
        $uploadDir = __DIR__ . '/../assets/avatars/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

        $file = $_FILES['avatar'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'No file uploaded or upload error.';
        } else {
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $mime    = mime_content_type($file['tmp_name']);
            if (!in_array($mime, $allowed)) {
                $errors[] = 'Only JPG, PNG, WEBP, or GIF images are allowed.';
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $errors[] = 'Image must be under 2 MB.';
            } else {
                $ext        = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename   = 'avatar_' . $userId . '_' . time() . '.' . strtolower($ext);
                move_uploaded_file($file['tmp_name'], $uploadDir . $filename);
                $avatarPath = '/lgu-urban-planning/assets/avatars/' . $filename;
                $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $stmt->execute([$avatarPath, $userId]);
                $_SESSION['avatar'] = $avatarPath;
                $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
                $user['full_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
                logAudit($pdo, $userId, 'Profile Photo Update', 'user', $userId, "Profile photo updated. New file: {$filename}");
                $success = 'Profile photo updated.';
            }
        }
    }
}

include __DIR__ . '/header.php';
?>

<div class="page-container profile-page">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/lgu-urban-planning/admin/index.php" style="color: inherit; text-decoration: none;">Dashboard</a></li>
            <li class="breadcrumb-item active">My Profile</li>
        </ol>
    </nav>

    <!-- Alerts -->
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert">
            <i class="bi bi-check-circle-fill"></i>
            <span><?php echo htmlspecialchars($success); ?></span>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <strong>Please fix the following:</strong>
            </div>
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $e): ?>
                    <li><?php echo htmlspecialchars($e); ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="profile-grid">

        <!-- ── LEFT PANEL: Avatar + identity summary ────────────────────── -->
        <aside class="profile-sidebar-card">
            <div class="avatar-upload-wrap">
                <?php if (!empty($user['avatar'])): ?>
                    <img src="<?php echo htmlspecialchars($user['avatar']); ?>"
                         alt="Profile photo" class="profile-avatar-img" id="avatarPreview">
                <?php else: ?>
                    <div class="profile-avatar-initials" id="avatarPreview">
                        <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                    </div>
                <?php endif; ?>

                <!-- Hover overlay for photo change -->
                <form method="POST" enctype="multipart/form-data" id="avatarForm">
                    <input type="hidden" name="action" value="upload_avatar">
                    <label class="avatar-overlay" for="avatarInput" title="Change photo">
                        <i class="bi bi-camera-fill"></i>
                        <span>Change</span>
                    </label>
                    <input type="file" id="avatarInput" name="avatar"
                           accept="image/jpeg,image/png,image/webp,image/gif"
                           class="d-none" onchange="submitAvatarForm()">
                </form>
            </div>

            <div class="profile-identity">
                <h5 class="profile-name"><?php echo htmlspecialchars($user['full_name']); ?></h5>
                <span class="profile-role-badge"><?php echo Helper::getRoleName($user['role']); ?></span>
            </div>

            <ul class="profile-meta-list">
                <li>
                    <i class="bi bi-envelope"></i>
                    <span><?php echo htmlspecialchars($user['email']); ?></span>
                </li>
                <?php if (!empty($user['phone'])): ?>
                <li>
                    <i class="bi bi-telephone"></i>
                    <span><?php echo htmlspecialchars($user['phone']); ?></span>
                </li>
                <?php endif; ?>
                <?php if (!empty($user['street']) || !empty($user['barangay']) || !empty($user['city'])): ?>
                <li>
                    <i class="bi bi-geo-alt"></i>
                    <span><?php echo htmlspecialchars(implode(', ', array_filter([$user['street'] ?? '', $user['barangay'] ?? '', $user['city'] ?? '']))); ?></span>
                </li>
                <?php endif; ?>
                <li>
                    <i class="bi bi-calendar3"></i>
                    <span>Joined <?php echo date('F j, Y', strtotime($user['created_at'])); ?></span>
                </li>
            </ul>
        </aside>

        <!-- ── RIGHT PANEL: Tabs ───────────────────────────────────────── -->
        <div class="profile-main">

            <!-- Tab nav -->
            <ul class="nav profile-tabs" id="profileTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="profile-tab-btn active" id="info-tab"
                            data-bs-toggle="tab" data-bs-target="#tab-info"
                            type="button" role="tab">
                        <i class="bi bi-person"></i> Personal Info
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="profile-tab-btn" id="password-tab"
                            data-bs-toggle="tab" data-bs-target="#tab-password"
                            type="button" role="tab">
                        <i class="bi bi-shield-lock"></i> Change Password
                    </button>
                </li>
            </ul>

            <div class="tab-content profile-tab-content">

                <!-- ── TAB: Personal Info ─────────────────────────────── -->
                <div class="tab-pane fade show active" id="tab-info" role="tabpanel">
                    <div class="profile-card">
                        <div class="profile-card-header">
                            <div>
                                <h6 class="profile-card-title">Personal Information</h6>
                                <p class="profile-card-subtitle">Your account details as stored in the system.</p>
                            </div>
                            <button class="btn btn-edit" id="editBtn" onclick="enableEdit()">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                        </div>

                        <form method="POST" id="profileForm">
                            <input type="hidden" name="action" value="update_profile">

                            <div class="profile-sections">

                                <!-- ── Section: Contact ── -->
                                <div class="profile-section">
                                    <div class="profile-section-label">
                                        <i class="bi bi-person-lines-fill"></i> Contact Details
                                    </div>

                                    <div class="profile-row">
                                        <div class="profile-row-field">
                                            <span class="profile-field-label">Full Name</span>
                                            <div class="view-mode">
                                                <span class="field-view-value"><?php echo htmlspecialchars($user['full_name']); ?></span>
                                            </div>
                                            <div class="edit-mode d-none">
                                                <input type="text" name="full_name" class="form-control profile-input"
                                                       value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="profile-row-field">
                                            <span class="profile-field-label">Email Address</span>
                                            <div class="view-mode">
                                                <span class="field-view-value"><?php echo htmlspecialchars($user['email']); ?></span>
                                            </div>
                                            <div class="edit-mode d-none">
                                                <input type="email" name="email" class="form-control profile-input"
                                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="profile-row">
                                        <div class="profile-row-field">
                                            <span class="profile-field-label">Phone Number</span>
                                            <div class="view-mode">
                                                <span class="field-view-value">
                                                    <?php echo !empty($user['phone']) ? htmlspecialchars($user['phone']) : '<span class="not-set">Not set</span>'; ?>
                                                </span>
                                            </div>
                                            <div class="edit-mode d-none">
                                                <input type="tel" name="phone" class="form-control profile-input"
                                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                                       placeholder="e.g. +63 912 345 6789">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- ── Section: Address ── -->
                                <div class="profile-section">
                                    <div class="profile-section-label">
                                        <i class="bi bi-geo-alt-fill"></i> Address
                                    </div>

                                    <div class="profile-row">
                                        <div class="profile-row-field profile-row-field--full">
                                            <span class="profile-field-label">Street</span>
                                            <div class="view-mode">
                                                <span class="field-view-value">
                                                    <?php echo !empty($user['street']) ? htmlspecialchars($user['street']) : '<span class="not-set">Not set</span>'; ?>
                                                </span>
                                            </div>
                                            <div class="edit-mode d-none">
                                                <input type="text" name="street" class="form-control profile-input"
                                                       value="<?php echo htmlspecialchars($user['street'] ?? ''); ?>"
                                                       placeholder="e.g. 123 Rizal St.">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="profile-row">
                                        <div class="profile-row-field">
                                            <span class="profile-field-label">Barangay</span>
                                            <div class="view-mode">
                                                <span class="field-view-value">
                                                    <?php echo !empty($user['barangay']) ? htmlspecialchars($user['barangay']) : '<span class="not-set">Not set</span>'; ?>
                                                </span>
                                            </div>
                                            <div class="edit-mode d-none">
                                                <input type="text" name="barangay" class="form-control profile-input"
                                                       value="<?php echo htmlspecialchars($user['barangay'] ?? ''); ?>"
                                                       placeholder="e.g. Barangay San Jose">
                                            </div>
                                        </div>
                                        <div class="profile-row-field">
                                            <span class="profile-field-label">City / Municipality</span>
                                            <div class="view-mode">
                                                <span class="field-view-value">
                                                    <?php echo !empty($user['city']) ? htmlspecialchars($user['city']) : '<span class="not-set">Not set</span>'; ?>
                                                </span>
                                            </div>
                                            <div class="edit-mode d-none">
                                                <input type="text" name="city" class="form-control profile-input"
                                                       value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>"
                                                       placeholder="e.g. Quezon City">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- ── Section: Account ── -->
                                <div class="profile-section profile-section--last">
                                    <div class="profile-section-label">
                                        <i class="bi bi-shield-check"></i> Account
                                    </div>

                                    <div class="profile-row">
                                        <div class="profile-row-field">
                                            <span class="profile-field-label">Role</span>
                                            <div class="view-mode">
                                                <span class="field-view-value"><?php echo Helper::getRoleName($user['role']); ?></span>
                                            </div>
                                            <div class="edit-mode d-none">
                                                <input type="text" class="form-control profile-input"
                                                       value="<?php echo Helper::getRoleName($user['role']); ?>" disabled>
                                                <small class="text-muted">Role can only be changed by an administrator.</small>
                                            </div>
                                        </div>
                                        <div class="profile-row-field">
                                            <span class="profile-field-label">Account Status</span>
                                            <div class="view-mode">
                                                <?php
                                                $status = $user['is_active'] ?? 1;
                                                $statusClass = $status ? 'status-active' : 'status-inactive';
                                                $statusLabel = $status ? 'Active' : 'Inactive';
                                                ?>
                                                <span class="<?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
                                            </div>
                                            <div class="edit-mode d-none">
                                                <span class="<?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
                                                <small class="text-muted d-block mt-1">Status is managed by an administrator.</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div><!-- /.profile-sections -->

                            <!-- Save / Cancel (hidden until edit mode) -->
                            <div class="edit-mode d-none mt-4 d-flex gap-2" id="formActions">
                                <button type="submit" class="btn btn-save">
                                    <i class="bi bi-check-lg"></i> Save Changes
                                </button>
                                <button type="button" class="btn btn-cancel" onclick="cancelEdit()">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div><!-- /#tab-info -->

                <!-- ── TAB: Change Password ───────────────────────────── -->
                <div class="tab-pane fade" id="tab-password" role="tabpanel">
                    <div class="profile-card">
                        <div class="profile-card-header">
                            <div>
                                <h6 class="profile-card-title">Change Password</h6>
                                <p class="profile-card-subtitle">Choose a strong password of at least 8 characters.</p>
                            </div>
                        </div>

                        <div class="pw-two-col">

                            <!-- LEFT: Form -->
                            <form method="POST" id="passwordForm" onsubmit="return validatePasswordForm()">
                                <input type="hidden" name="action" value="change_password">

                                <div class="profile-fields">
                                    <div class="profile-field-group profile-field-full">
                                        <label class="profile-field-label">Current Password</label>
                                        <div class="input-group">
                                            <input type="password" name="current_password" id="currentPw"
                                                   class="form-control profile-input" required>
                                            <button class="btn btn-pw-toggle" type="button" onclick="togglePw('currentPw', this)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="profile-field-group profile-field-full">
                                        <label class="profile-field-label">New Password</label>
                                        <div class="input-group">
                                            <input type="password" name="new_password" id="newPw"
                                                   class="form-control profile-input" required
                                                   oninput="checkStrength(this.value)">
                                            <button class="btn btn-pw-toggle" type="button" onclick="togglePw('newPw', this)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                        <!-- Strength meter -->
                                        <div class="pw-strength-bar mt-2">
                                            <div class="pw-strength-fill" id="strengthFill"></div>
                                        </div>
                                        <small class="pw-strength-label" id="strengthLabel"></small>
                                    </div>

                                    <div class="profile-field-group profile-field-full">
                                        <label class="profile-field-label">Confirm New Password</label>
                                        <div class="input-group">
                                            <input type="password" name="confirm_password" id="confirmPw"
                                                   class="form-control profile-input" required>
                                            <button class="btn btn-pw-toggle" type="button" onclick="togglePw('confirmPw', this)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                        <small class="pw-match-hint d-none text-danger" id="matchHint">
                                            <i class="bi bi-x-circle"></i> Passwords do not match
                                        </small>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <button type="submit" class="btn btn-save">
                                        <i class="bi bi-shield-check"></i> Update Password
                                    </button>
                                </div>
                            </form>

                            <!-- RIGHT: Last changed + Security tips -->
                            <div class="pw-side-info">

                                <div class="pw-last-changed">
                                    <div class="pw-last-changed-icon"><i class="bi bi-clock-history"></i></div>
                                    <div>
                                        <div class="pw-last-changed-label">Last password change</div>
                                        <div class="pw-last-changed-date">
                                            <?php echo date('F j, Y \\a\\t g:i A', strtotime($user['updated_at'] ?? $user['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="pw-tips">
                                    <p class="pw-tips-title">
                                        <i class="bi bi-shield-exclamation"></i> Security Tips
                                    </p>
                                    <ul class="pw-tips-list">
                                        <li><i class="bi bi-check2-circle"></i> Don't reuse passwords from other sites</li>
                                        <li><i class="bi bi-check2-circle"></i> Never share your password with anyone</li>
                                        <li><i class="bi bi-check2-circle"></i> Use a password manager to stay safe</li>
                                        <li><i class="bi bi-check2-circle"></i> Change your password regularly</li>
                                    </ul>
                                </div>

                            </div><!-- /.pw-side-info -->

                        </div><!-- /.pw-two-col -->
                    </div>
                </div><!-- /#tab-password -->

            </div><!-- /.tab-content -->
        </div><!-- /.profile-main -->
    </div><!-- /.profile-grid -->
</div><!-- /.profile-page -->

<!-- ── Styles ──────────────────────────────────────────────────────────────── -->
<style>

/* ════════════════════════════════════
   BASE — Desktop
════════════════════════════════════ */
.profile-page { padding: 24px; }

.profile-grid {
    display: grid;
    grid-template-columns: 260px 1fr;
    gap: 20px;
    align-items: start;
}

/* ── Sidebar ── */
.profile-sidebar-card {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.06);
    padding: 22px 18px 18px;
    text-align: center;
    position: sticky;
    top: 80px;
}
.avatar-upload-wrap {
    position: relative;
    width: 88px;
    height: 88px;
    margin: 0 auto 14px;
}
.profile-avatar-img,
.profile-avatar-initials {
    width: 88px;
    height: 88px;
    border-radius: 50%;
    object-fit: cover;
    display: flex;
    align-items: center;
    justify-content: center;
}
.profile-avatar-initials {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: #fff;
    font-size: 2rem;
    font-weight: 700;
}
.avatar-overlay {
    position: absolute;
    inset: 0;
    border-radius: 50%;
    background: rgba(0,0,0,0.45);
    color: #fff;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 3px;
    font-size: 0.68rem;
    font-weight: 600;
    cursor: pointer;
    opacity: 0;
    transition: opacity 0.2s;
}
.avatar-upload-wrap:hover .avatar-overlay { opacity: 1; }
.avatar-overlay i { font-size: 1.2rem; }

.profile-identity { margin-bottom: 14px; }
.profile-name {
    font-weight: 700;
    font-size: 0.95rem;
    color: #111827;
    margin-bottom: 5px;
}
.profile-role-badge {
    display: inline-block;
    background: #eff6ff;
    color: #2563eb;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 2px 9px;
    border-radius: 999px;
    border: 1px solid #bfdbfe;
}
.profile-meta-list {
    list-style: none;
    padding: 0;
    margin: 0;
    text-align: left;
    border-top: 1px solid #f1f5f9;
    padding-top: 12px;
}
.profile-meta-list li {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    padding: 5px 0;
    font-size: 0.79rem;
    color: #4b5563;
    border-bottom: 1px solid #f8fafc;
}
.profile-meta-list li i {
    color: #3b82f6;
    font-size: 0.9rem;
    margin-top: 1px;
    flex-shrink: 0;
}

/* ── Tabs ── */
.profile-tabs {
    display: flex;
    gap: 0;
    border-bottom: 2px solid #e5e7eb;
    margin-bottom: 16px;
    padding: 0;
}
.profile-tab-btn {
    background: none;
    border: none;
    padding: 9px 16px;
    font-size: 0.85rem;
    font-weight: 500;
    color: #6b7280;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: color 0.2s, border-color 0.2s;
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
}
.profile-tab-btn.active,
.profile-tab-btn:hover { color: #2563eb; border-bottom-color: #2563eb; }

/* ── Profile card ── */
.profile-card {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.06);
    padding: 22px 24px;
}
.profile-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 16px;
}
.profile-card-title {
    font-weight: 700;
    font-size: 0.92rem;
    color: #111827;
    margin-bottom: 2px;
}
.profile-card-subtitle {
    font-size: 0.77rem;
    color: #9ca3af;
    margin: 0;
}

/* ── Clean sections (Personal Info) ── */
.profile-sections { display: flex; flex-direction: column; }

.profile-section {
    border-bottom: 1px solid #f1f5f9;
    padding-bottom: 14px;
    margin-bottom: 14px;
}
.profile-section--last {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}
.profile-section-label {
    font-size: 0.67rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: #3b82f6;
    display: flex;
    align-items: center;
    gap: 5px;
    margin-bottom: 2px;
}
.profile-section-label i { font-size: 0.72rem; }

.profile-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    border-top: 1px solid #f1f5f9;
}
.profile-row-field {
    padding: 10px 4px;
    display: flex;
    flex-direction: column;
    gap: 3px;
}
.profile-row-field + .profile-row-field {
    border-left: 1px solid #f1f5f9;
    padding-left: 18px;
}
.profile-row-field--full { grid-column: 1 / -1; }

/* ── Field labels & values ── */
.profile-field-label {
    display: block;
    font-size: 0.7rem;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 2px;
}
.field-view-value {
    font-size: 0.875rem;
    color: #111827;
    font-weight: 500;
}
.not-set {
    color: #9ca3af;
    font-style: italic;
    font-size: 0.85rem;
}

/* ── Fields grid (password tab) ── */
.profile-fields {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
.profile-field-full { grid-column: 1 / -1; }
.profile-field-group {}

/* ── Status badges ── */
.status-active {
    background: #dcfce7;
    color: #15803d;
    border: 1px solid #bbf7d0;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 3px 9px;
    border-radius: 999px;
    display: inline-block;
}
.status-inactive {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fecaca;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 3px 9px;
    border-radius: 999px;
    display: inline-block;
}

/* ── Inputs ── */
.profile-input {
    font-size: 0.85rem;
    border-radius: 8px;
    border: 1.5px solid #e5e7eb;
    transition: border-color 0.2s;
}
.profile-input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.12);
}

/* ── Buttons ── */
.btn-edit {
    background: #eff6ff;
    color: #2563eb;
    border: 1px solid #bfdbfe;
    font-size: 0.8rem;
    font-weight: 600;
    padding: 5px 12px;
    border-radius: 8px;
    white-space: nowrap;
    flex-shrink: 0;
    transition: background 0.2s;
}
.btn-edit:hover { background: #dbeafe; }
.btn-save {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: #fff;
    border: none;
    font-size: 0.85rem;
    font-weight: 600;
    padding: 8px 18px;
    border-radius: 9px;
    transition: opacity 0.2s;
}
.btn-save:hover { opacity: 0.9; color: #fff; }
.btn-cancel {
    background: #f3f4f6;
    color: #374151;
    border: none;
    font-size: 0.85rem;
    font-weight: 500;
    padding: 8px 18px;
    border-radius: 9px;
    transition: background 0.2s;
}
.btn-cancel:hover { background: #e5e7eb; }
.btn-pw-toggle {
    border: 1.5px solid #e5e7eb;
    border-left: none;
    background: #f9fafb;
    color: #6b7280;
    border-radius: 0 8px 8px 0 !important;
    padding: 0 11px;
    transition: color 0.2s;
}
.btn-pw-toggle:hover { color: #2563eb; }

/* ── Password strength ── */
.pw-strength-bar {
    height: 4px;
    background: #e5e7eb;
    border-radius: 999px;
    overflow: hidden;
}
.pw-strength-fill {
    height: 100%;
    width: 0%;
    border-radius: 999px;
    transition: width 0.3s ease, background 0.3s ease;
}
.pw-strength-label {
    font-size: 0.72rem;
    font-weight: 600;
    margin-top: 3px;
    display: block;
}

/* ── Password two-col ── */
.pw-two-col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    align-items: start;
}

/* ── Last password changed ── */
.pw-last-changed {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #f0f7ff;
    border: 1px solid #bfdbfe;
    border-radius: 10px;
    padding: 12px 14px;
    margin-bottom: 12px;
}
.pw-last-changed-icon {
    width: 32px;
    height: 32px;
    background: #dbeafe;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #2563eb;
    font-size: 0.9rem;
    flex-shrink: 0;
}
.pw-last-changed-label {
    font-size: 0.68rem;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    margin-bottom: 1px;
}
.pw-last-changed-date {
    font-size: 0.82rem;
    font-weight: 600;
    color: #1d4ed8;
}

/* ── Security tips ── */
.pw-tips {
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 10px;
    padding: 12px 14px;
}
.pw-tips-title {
    font-size: 0.68rem;
    font-weight: 700;
    color: #92400e;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 5px;
}
.pw-tips-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.pw-tips-list li {
    font-size: 0.8rem;
    color: #78350f;
    display: flex;
    align-items: center;
    gap: 7px;
}
.pw-tips-list li i { color: #d97706; font-size: 0.9rem; flex-shrink: 0; }


/* ════════════════════════════════════
   768px — Tablets
════════════════════════════════════ */
@media (max-width: 768px) {
    .profile-page { padding: 14px; }

    /* Stack grid */
    .profile-grid { grid-template-columns: 1fr; gap: 14px; }

    /* Sidebar: horizontal row */
    .profile-sidebar-card {
        position: static;
        display: flex;
        flex-direction: row;
        align-items: center;
        text-align: left;
        gap: 16px;
        padding: 16px;
        flex-wrap: wrap;
        border-radius: 12px;
    }
    .avatar-upload-wrap { margin: 0; flex-shrink: 0; }
    .profile-identity { margin-bottom: 0; flex: 1; min-width: 120px; }
    .profile-meta-list {
        width: 100%;
        border-top: 1px solid #f1f5f9;
        padding-top: 10px;
        margin-top: 2px;
    }

    /* Card */
    .profile-card { padding: 16px; border-radius: 12px; }
    .profile-card-header { margin-bottom: 12px; }

    /* Password two-col → single */
    .pw-two-col { grid-template-columns: 1fr; gap: 14px; }

    /* Tabs */
    .profile-tab-btn { padding: 8px 13px; font-size: 0.8rem; }
}


/* ════════════════════════════════════
   480px — Large Mobile
════════════════════════════════════ */
@media (max-width: 480px) {
    .profile-page { padding: 10px; }

    /* Sidebar: back to vertical */
    .profile-sidebar-card {
        flex-direction: column;
        text-align: center;
        padding: 16px 14px;
        gap: 0;
    }
    .avatar-upload-wrap { margin: 0 auto 12px; }
    .profile-identity { margin-bottom: 12px; }
    .profile-meta-list { text-align: left; }

    /* Card */
    .profile-card { padding: 14px 12px; border-radius: 10px; }
    .profile-card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
        margin-bottom: 10px;
    }

    /* Tabs fill width */
    .profile-tabs { gap: 0; }
    .profile-tab-btn {
        flex: 1;
        justify-content: center;
        padding: 8px 8px;
        font-size: 0.75rem;
        gap: 4px;
    }

    /* Sections: rows go single col */
    .profile-section { padding-bottom: 10px; margin-bottom: 10px; }
    .profile-row { grid-template-columns: 1fr; }
    .profile-row-field--full { grid-column: 1; }
    .profile-row-field + .profile-row-field {
        border-left: none;
        border-top: 1px solid #f1f5f9;
        padding-left: 4px;
    }
    [data-bs-theme="dark"] .profile-row-field + .profile-row-field {
        border-top-color: rgba(255,255,255,0.07);
    }

    /* Password fields single col */
    .profile-fields { grid-template-columns: 1fr; gap: 12px; }
    .profile-field-full { grid-column: 1; }

    /* Buttons full-width stacked */
    .btn-save, .btn-cancel { width: 100%; justify-content: center; }
    #formActions { flex-direction: column !important; gap: 8px !important; }

    /* Password info */
    .pw-last-changed { padding: 10px 12px; gap: 9px; }
    .pw-last-changed-date { font-size: 0.77rem; }
    .pw-tips { padding: 10px 12px; }
}


/* ════════════════════════════════════
   320px — Small Mobile
════════════════════════════════════ */
@media (max-width: 320px) {
    .profile-page { padding: 8px; }

    /* Smaller avatar */
    .avatar-upload-wrap,
    .profile-avatar-img,
    .profile-avatar-initials { width: 68px; height: 68px; }
    .profile-avatar-initials { font-size: 1.6rem; }

    .profile-name { font-size: 0.85rem; }
    .profile-role-badge { font-size: 0.63rem; padding: 2px 7px; }
    .profile-meta-list li { font-size: 0.72rem; gap: 7px; }

    /* Card */
    .profile-card { padding: 10px; border-radius: 8px; }
    .profile-card-title { font-size: 0.82rem; }
    .profile-card-subtitle { font-size: 0.7rem; }

    /* Tabs */
    .profile-tab-btn {
        font-size: 0.68rem;
        padding: 7px 5px;
        gap: 3px;
    }
    .profile-tab-btn i { font-size: 0.75rem; }

    /* Field text */
    .profile-field-label { font-size: 0.63rem; }
    .field-view-value { font-size: 0.8rem; }
    .profile-input { font-size: 0.8rem; }
    .not-set { font-size: 0.78rem; }
    .profile-row-field { padding: 8px 3px; }

    /* Buttons */
    .btn-save, .btn-cancel { font-size: 0.78rem; padding: 7px 12px; }
    .btn-edit { font-size: 0.72rem; padding: 4px 9px; }

    /* Password section */
    .pw-last-changed { padding: 8px 10px; gap: 7px; }
    .pw-last-changed-icon { width: 28px; height: 28px; font-size: 0.8rem; }
    .pw-last-changed-label { font-size: 0.62rem; }
    .pw-last-changed-date { font-size: 0.72rem; }
    .pw-tips { padding: 8px 10px; }
    .pw-tips-title { font-size: 0.62rem; }
    .pw-tips-list li { font-size: 0.72rem; }
}


/* ════════════════════════════════════
   DARK MODE
════════════════════════════════════ */
[data-bs-theme="dark"] .profile-sidebar-card,
[data-bs-theme="dark"] .profile-card {
    background: #1e293b !important;
    border: 1px solid rgba(255,255,255,0.07);
}
[data-bs-theme="dark"] .profile-name,
[data-bs-theme="dark"] .profile-card-title,
[data-bs-theme="dark"] .field-view-value { color: #f1f5f9 !important; }
[data-bs-theme="dark"] .profile-card-subtitle,
[data-bs-theme="dark"] .profile-meta-list li { color: #94a3b8; }
[data-bs-theme="dark"] .profile-meta-list { border-top-color: rgba(255,255,255,0.08); }
[data-bs-theme="dark"] .profile-meta-list li { border-bottom-color: rgba(255,255,255,0.04); }
[data-bs-theme="dark"] .profile-field-label { color: #94a3b8; }
[data-bs-theme="dark"] .profile-tabs { border-bottom-color: rgba(255,255,255,0.1); }
[data-bs-theme="dark"] .profile-tab-btn { color: #94a3b8; }
[data-bs-theme="dark"] .profile-tab-btn.active,
[data-bs-theme="dark"] .profile-tab-btn:hover { color: #60a5fa; border-bottom-color: #60a5fa; }
[data-bs-theme="dark"] .profile-role-badge { background: #1e3a5f; color: #93c5fd; border-color: #1e40af; }
[data-bs-theme="dark"] .profile-input { background: #0f172a; border-color: #334155; color: #f1f5f9; }
[data-bs-theme="dark"] .btn-edit { background: #1e3a5f; color: #93c5fd; border-color: #1e40af; }
[data-bs-theme="dark"] .btn-edit:hover { background: #1e40af; }
[data-bs-theme="dark"] .btn-cancel { background: #334155; color: #e2e8f0; }
[data-bs-theme="dark"] .btn-cancel:hover { background: #475569; }
[data-bs-theme="dark"] .btn-pw-toggle { background: #1e293b; border-color: #334155; color: #94a3b8; }
[data-bs-theme="dark"] .pw-strength-bar { background: #334155; }
[data-bs-theme="dark"] .status-active { background: #14532d; color: #86efac; border-color: #166634; }
[data-bs-theme="dark"] .status-inactive { background: #450a0a; color: #fca5a5; border-color: #7f1d1d; }
[data-bs-theme="dark"] .profile-section { border-bottom-color: rgba(255,255,255,0.07); }
[data-bs-theme="dark"] .profile-section-label { color: #60a5fa; }
[data-bs-theme="dark"] .profile-row { border-top-color: rgba(255,255,255,0.07); }
[data-bs-theme="dark"] .profile-row-field + .profile-row-field { border-left-color: rgba(255,255,255,0.07); }
[data-bs-theme="dark"] .not-set { color: #475569; }
[data-bs-theme="dark"] .pw-last-changed { background: #1e3a5f; border-color: #1d4ed8; }
[data-bs-theme="dark"] .pw-last-changed-icon { background: #1e40af; color: #93c5fd; }
[data-bs-theme="dark"] .pw-last-changed-label { color: #94a3b8; }
[data-bs-theme="dark"] .pw-last-changed-date { color: #93c5fd; }
[data-bs-theme="dark"] .pw-tips { background: #1c1a0f; border-color: #854d0e; }
[data-bs-theme="dark"] .pw-tips-title { color: #fbbf24; }
[data-bs-theme="dark"] .pw-tips-list li { color: #fcd34d; }
[data-bs-theme="dark"] .pw-tips-list li i { color: #f59e0b; }
</style>

<!-- ── Scripts ─────────────────────────────────────────────────────────────── -->
<script>
// Toggle between view and edit modes
function enableEdit() {
    document.querySelectorAll('#profileForm .view-mode').forEach(el => el.classList.add('d-none'));
    document.querySelectorAll('#profileForm .edit-mode').forEach(el => el.classList.remove('d-none'));
    document.getElementById('editBtn').classList.add('d-none');
}

function cancelEdit() {
    document.querySelectorAll('#profileForm .view-mode').forEach(el => el.classList.remove('d-none'));
    document.querySelectorAll('#profileForm .edit-mode').forEach(el => el.classList.add('d-none'));
    document.getElementById('editBtn').classList.remove('d-none');
}

// Auto-submit avatar form on file select
function submitAvatarForm() {
    document.getElementById('avatarForm').submit();
}

// Show/hide password
function togglePw(id, btn) {
    const input = document.getElementById(id);
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
}

// Password strength meter
function checkStrength(val) {
    const fill  = document.getElementById('strengthFill');
    const label = document.getElementById('strengthLabel');
    let score = 0;
    if (val.length >= 8)              score++;
    if (/[A-Z]/.test(val))            score++;
    if (/[0-9]/.test(val))            score++;
    if (/[^A-Za-z0-9]/.test(val))     score++;

    const levels = [
        { w: '25%',  bg: '#ef4444', text: 'Weak',      color: '#ef4444' },
        { w: '50%',  bg: '#f97316', text: 'Fair',       color: '#f97316' },
        { w: '75%',  bg: '#eab308', text: 'Good',       color: '#eab308' },
        { w: '100%', bg: '#22c55e', text: 'Strong',     color: '#22c55e' },
    ];
    const lvl = levels[Math.max(0, score - 1)];
    fill.style.width      = val.length ? lvl.w  : '0%';
    fill.style.background = val.length ? lvl.bg : 'transparent';
    label.textContent     = val.length ? lvl.text : '';
    label.style.color     = val.length ? lvl.color : '';
}

// Confirm password match hint
document.getElementById('confirmPw').addEventListener('input', function () {
    const hint = document.getElementById('matchHint');
    if (this.value && this.value !== document.getElementById('newPw').value) {
        hint.classList.remove('d-none');
    } else {
        hint.classList.add('d-none');
    }
});

// Client-side password validation before submit
function validatePasswordForm() {
    const newPw     = document.getElementById('newPw').value;
    const confirmPw = document.getElementById('confirmPw').value;
    if (newPw !== confirmPw) {
        document.getElementById('matchHint').classList.remove('d-none');
        return false;
    }
    if (newPw.length < 8) {
        alert('Password must be at least 8 characters.');
        return false;
    }
    return true;
}

// If returning from a password error, switch to password tab automatically
<?php if (!empty($errors) && isset($_POST['action']) && $_POST['action'] === 'change_password'): ?>
    document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('password-tab').click();
    });
<?php endif; ?>

// If returning from a profile error, re-enable edit mode
<?php if (!empty($errors) && isset($_POST['action']) && $_POST['action'] === 'update_profile'): ?>
    document.addEventListener('DOMContentLoaded', enableEdit);
<?php endif; ?>
</script>

<?php include __DIR__ . '/footer.php'; ?>