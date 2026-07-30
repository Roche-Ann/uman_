<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helper.php';
require_once __DIR__ . '/../core/Auth.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$userId = $_SESSION['user_id'];
// ── i18n — reads language saved by settings.php ──────────────────────────────
$_prLang = $_SESSION['locale_language'] ?? 'en_PH';

$_prT = [
    'en_PH' => [
        'page_title'            => 'My Profile',
        // Breadcrumb
        'breadcrumb_dashboard'  => 'Dashboard',
        'breadcrumb_profile'    => 'My Profile',
        // Alerts
        'alert_fix'             => 'Please fix the following:',
        // Sidebar
        'lbl_joined'            => 'Joined',
        'stat_total'            => 'Total',
        'stat_approved'         => 'Approved',
        'stat_pending'          => 'Pending',
        'stat_rejected'         => 'Rejected',
        'link_my_apps'          => 'My Applications',
        'link_messages'         => 'Messages',
        // Avatar overlay
        'avatar_change'         => 'Change',
        // Tabs
        'tab_personal_info'     => 'Personal Info',
        'tab_change_password'   => 'Change Password',
        // Personal info card
        'card_personal_title'   => 'Personal Information',
        'card_personal_sub'     => 'Your account details as stored in the system.',
        'btn_edit'              => 'Edit',
        // Sections
        'sec_contact'           => 'Contact Details',
        'sec_address'           => 'Address',
        'sec_account'           => 'Account Info',
        // Field labels
        'lbl_full_name'         => 'Full Name',
        'lbl_email'             => 'Email Address',
        'lbl_phone'             => 'Phone Number',
        'lbl_street'            => 'Street / House No.',
        'lbl_barangay'          => 'Barangay',
        'lbl_district'          => 'District',
        'lbl_city'              => 'City / Municipality',
        'lbl_role'              => 'Account Role',
        'lbl_status'            => 'Account Status',
        'lbl_member_since'      => 'Member Since',
        'lbl_last_updated'      => 'Last Updated',
        // Placeholders
        'ph_phone'              => 'e.g. 09XXXXXXXXX',
        'ph_street'             => 'Street / House No.',
        'ph_barangay'           => 'Barangay',
        'ph_district'           => 'District',
        'ph_city'               => 'City / Municipality',
        // Not set / Cannot change
        'not_set'               => 'Not set',
        'cannot_change'         => 'Cannot be changed',
        // Action buttons
        'btn_save'              => 'Save Changes',
        'btn_cancel'            => 'Cancel',
        // Password card
        'card_pw_title'         => 'Change Password',
        'card_pw_sub'           => 'Choose a strong password of at least 8 characters.',
        'lbl_current_pw'        => 'Current Password',
        'lbl_new_pw'            => 'New Password',
        'lbl_confirm_pw'        => 'Confirm New Password',
        'pw_no_match'           => 'Passwords do not match',
        'btn_update_pw'         => 'Update Password',
        // Password side panel
        'pw_last_changed'       => 'Last password change',
        'pw_tips_title'         => 'Security Tips',
        'pw_tip_1'              => "Don't reuse passwords from other sites",
        'pw_tip_2'              => 'Never share your password with anyone',
        'pw_tip_3'              => 'Use a password manager to stay safe',
        'pw_tip_4'              => 'Change your password regularly',
        // JS alert
        'js_pw_min_chars'       => 'Password must be at least 8 characters.',
        // PHP error messages
        'err_full_name'         => 'Full name is required.',
        'err_email_invalid'     => 'A valid email address is required.',
        'err_email_taken'       => 'That email is already in use by another account.',
        'err_wrong_pw'          => 'Current password is incorrect.',
        'err_pw_too_short'      => 'New password must be at least 8 characters.',
        'err_pw_complexity'     => 'Password must combine uppercase, lowercase, and numbers.',
        'err_pw_no_match'       => 'New passwords do not match.',
        'err_no_file'           => 'No file uploaded or upload error.',
        'err_invalid_img'       => 'Only JPG, PNG, WEBP, or GIF images are allowed.',
        'err_img_too_big'       => 'Image must be under 2 MB.',
        // Success messages
        'ok_profile_updated'    => 'Profile updated successfully.',
        'ok_password_changed'   => 'Password changed successfully.',
        'ok_avatar_updated'     => 'Profile photo updated.',
    ],
    'fil' => [
        'page_title'            => 'Aking Profile',
        // Breadcrumb
        'breadcrumb_dashboard'  => 'Dashboard',
        'breadcrumb_profile'    => 'Aking Profile',
        // Alerts
        'alert_fix'             => 'Pakiayos ang mga sumusunod:',
        // Sidebar
        'lbl_joined'            => 'Sumali',
        'stat_total'            => 'Kabuuan',
        'stat_approved'         => 'Naaprubahan',
        'stat_pending'          => 'Nakabinbin',
        'stat_rejected'         => 'Tinanggihan',
        'link_my_apps'          => 'Aking mga Aplikasyon',
        'link_messages'         => 'Mga Mensahe',
        // Avatar overlay
        'avatar_change'         => 'Baguhin',
        // Tabs
        'tab_personal_info'     => 'Personal na Impormasyon',
        'tab_change_password'   => 'Baguhin ang Password',
        // Personal info card
        'card_personal_title'   => 'Personal na Impormasyon',
        'card_personal_sub'     => 'Ang iyong mga detalye ng account na nakaimbak sa sistema.',
        'btn_edit'              => 'I-edit',
        // Sections
        'sec_contact'           => 'Mga Detalye ng Pakikipag-ugnayan',
        'sec_address'           => 'Tirahan',
        'sec_account'           => 'Impormasyon ng Account',
        // Field labels
        'lbl_full_name'         => 'Buong Pangalan',
        'lbl_email'             => 'Email Address',
        'lbl_phone'             => 'Numero ng Telepono',
        'lbl_street'            => 'Kalye / Blg. ng Bahay',
        'lbl_barangay'          => 'Barangay',
        'lbl_district'          => 'Distrito',
        'lbl_city'              => 'Lungsod / Munisipalidad',
        'lbl_role'              => 'Papel ng Account',
        'lbl_status'            => 'Katayuan ng Account',
        'lbl_member_since'      => 'Miyembro Mula',
        'lbl_last_updated'      => 'Huling Na-update',
        // Placeholders
        'ph_phone'              => 'hal. 09XXXXXXXXX',
        'ph_street'             => 'Kalye / Blg. ng Bahay',
        'ph_barangay'           => 'Barangay',
        'ph_district'           => 'Distrito',
        'ph_city'               => 'Lungsod / Munisipalidad',
        // Not set / Cannot change
        'not_set'               => 'Hindi nakatakda',
        'cannot_change'         => 'Hindi maaaring baguhin',
        // Action buttons
        'btn_save'              => 'I-save ang mga Pagbabago',
        'btn_cancel'            => 'Kanselahin',
        // Password card
        'card_pw_title'         => 'Baguhin ang Password',
        'card_pw_sub'           => 'Pumili ng matibay na password na may hindi bababa sa 8 na karakter.',
        'lbl_current_pw'        => 'Kasalukuyang Password',
        'lbl_new_pw'            => 'Bagong Password',
        'lbl_confirm_pw'        => 'Kumpirmahin ang Bagong Password',
        'pw_no_match'           => 'Hindi magkatugma ang mga password',
        'btn_update_pw'         => 'I-update ang Password',
        // Password side panel
        'pw_last_changed'       => 'Huling pagbabago ng password',
        'pw_tips_title'         => 'Mga Tip sa Seguridad',
        'pw_tip_1'              => 'Huwag gumamit ulit ng mga password mula sa ibang site',
        'pw_tip_2'              => 'Huwag ibahagi ang iyong password sa sinuman',
        'pw_tip_3'              => 'Gumamit ng password manager para manatiling ligtas',
        'pw_tip_4'              => 'Regular na baguhin ang iyong password',
        // JS alert
        'js_pw_min_chars'       => 'Ang password ay dapat may hindi bababa sa 8 na karakter.',
        // PHP error messages
        'err_full_name'         => 'Kinakailangan ang buong pangalan.',
        'err_email_invalid'     => 'Kinakailangan ang wastong email address.',
        'err_email_taken'       => 'Ginagamit na ng ibang account ang email na iyon.',
        'err_wrong_pw'          => 'Mali ang kasalukuyang password.',
        'err_pw_too_short'      => 'Ang bagong password ay dapat may hindi bababa sa 8 na karakter.',
        'err_pw_complexity'     => 'Ang password ay dapat may uppercase, lowercase, at numero.',
        'err_pw_no_match'       => 'Hindi magkatugma ang mga bagong password.',
        'err_no_file'           => 'Walang na-upload na file o may error sa pag-upload.',
        'err_invalid_img'       => 'Mga JPG, PNG, WEBP, o GIF na larawan lamang ang pinapayagan.',
        'err_img_too_big'       => 'Ang larawan ay dapat wala pang 2 MB.',
        // Success messages
        'ok_profile_updated'    => 'Matagumpay na na-update ang profile.',
        'ok_password_changed'   => 'Matagumpay na nabago ang password.',
        'ok_avatar_updated'     => 'Na-update ang larawan ng profile.',
    ],
];

function _prt(string $key): string {
    global $_prT, $_prLang;
    return $_prT[$_prLang][$key] ?? $_prT['en_PH'][$key] ?? $key;
}

$pageTitle = _prt('page_title');
$isAuthPage = true;

// Fetch full user record
$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);

// Combine first and last name
if ($user) {
    $user['full_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
}

// Read the dedicated pw_last_changed timestamp (set only when password actually changes)
$pwLastChangedRow = $db->fetchOne(
    "SELECT pref_value FROM user_preferences WHERE user_id = ? AND pref_key = 'pw_last_changed' LIMIT 1",
    [$userId]
);
$pwLastChanged = $pwLastChangedRow['pref_value'] ?? $user['created_at'] ?? null;

// Fetch application stats for this applicant
try {
    $appStats = $db->fetchOne(
        "SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
            SUM(CASE WHEN status = 'pending'  THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected
         FROM applications WHERE applicant_id = ?",
        [$userId]
    );
} catch (Exception $e) {
    $appStats = ['total' => 0, 'approved' => 0, 'pending' => 0, 'rejected' => 0];
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
        $district      = trim($_POST['district']  ?? '');
        $city          = trim($_POST['city']      ?? '');

        if (empty($fullNameInput)) {
            $errors[] = _prt('err_full_name');
        } else {
            $nameParts = explode(' ', $fullNameInput, 2);
            $firstName = $nameParts[0];
            $lastName  = $nameParts[1] ?? '';
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
            $errors[] = _prt('err_email_invalid');

        $existing = $db->fetchOne(
            "SELECT id FROM users WHERE email = ? AND id != ?",
            [$email, $userId]
        );
        if ($existing) $errors[] = _prt('err_email_taken');

        if (empty($errors)) {
            $oldFullName = $user['full_name'];
            $oldEmail    = $user['email'];
            $oldPhone    = $user['phone']    ?? '';
            $oldStreet   = $user['street']   ?? '';
            $oldBarangay = $user['barangay'] ?? '';
            $oldDistrict = $user['district'] ?? '';
            $oldCity     = $user['city']     ?? '';

            $stmt = $pdo->prepare(
                "UPDATE users
                 SET first_name = ?, last_name = ?, email = ?, phone = ?,
                     street = ?, barangay = ?, district = ?, city = ?
                 WHERE id = ?"
            );
            $stmt->execute([$firstName, $lastName, $email, $phone, $street, $barangay, $district, $city, $userId]);

            $_SESSION['full_name'] = $fullNameInput;

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
            if ($oldDistrict !== $district)
                $changes[] = "District: \"" . ($oldDistrict ?: 'Not set') . "\" → \"" . ($district ?: 'Not set') . "\"";
            if ($oldCity !== $city)
                $changes[] = "City: \"" . ($oldCity ?: 'Not set') . "\" → \"" . ($city ?: 'Not set') . "\"";

            $details = empty($changes)
                ? 'Profile saved with no field changes.'
                : implode('; ', $changes);

            logAudit($pdo, $userId, 'Profile Update', 'user', $userId, $details);

            $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
            $user['full_name'] = trim($user['first_name'] . ' ' . $user['last_name']);

            $success = _prt('ok_profile_updated');
        }
    }

    // -- Change password -----------------------------------------------------
    if ($_POST['action'] === 'change_password') {
        $currentPw = $_POST['current_password'] ?? '';
        $newPw     = $_POST['new_password']      ?? '';
        $confirmPw = $_POST['confirm_password']  ?? '';

        if (!password_verify($currentPw, $user['password_hash']))
            $errors[] = _prt('err_wrong_pw');
        if (strlen($newPw) < 8)
            $errors[] = _prt('err_pw_too_short');
        if (strlen($newPw) >= 8 && (!preg_match('@[A-Z]@', $newPw) || !preg_match('@[a-z]@', $newPw) || !preg_match('@[0-9]@', $newPw)))
            $errors[] = _prt('err_pw_complexity');
        if ($newPw !== $confirmPw)
            $errors[] = _prt('err_pw_no_match');

        if (empty($errors)) {
            $hashed = password_hash($newPw, PASSWORD_DEFAULT);
            $stmt   = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hashed, $userId]);
            // Store exact timestamp of this password change
            $stmt2 = $pdo->prepare(
                "INSERT INTO user_preferences (user_id, pref_key, pref_value)
                 VALUES (?, 'pw_last_changed', ?)
                 ON DUPLICATE KEY UPDATE pref_value = VALUES(pref_value)"
            );
            $stmt2->execute([$userId, date('Y-m-d H:i:s')]);
            logAudit($pdo, $userId, 'Password Change', 'user', $userId, 'Applicant changed their own account password.');
            $success = _prt('ok_password_changed');
        }
    }

    // -- Upload avatar -------------------------------------------------------
    if ($_POST['action'] === 'upload_avatar') {
        $uploadDir = __DIR__ . '/../assets/avatars/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

        $file = $_FILES['avatar'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = _prt('err_no_file');
        } else {
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $mime    = mime_content_type($file['tmp_name']);
            if (!in_array($mime, $allowed)) {
                $errors[] = _prt('err_invalid_img');
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $errors[] = _prt('err_img_too_big');
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
                $success = _prt('ok_avatar_updated');
            }
        }
    }
}

include __DIR__ . '/../user/header.php';
?>

<div class="page-container profile-page">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="/lgu-urban-planning/user/index.php" style="color: inherit; text-decoration: none;"><?php echo _prt('breadcrumb_dashboard'); ?></a>
            </li>
            <li class="breadcrumb-item active"><?php echo _prt('breadcrumb_profile'); ?></li>
        </ol>
    </nav>

    <!-- Toast container -->
    <div id="toastContainer" aria-live="polite" aria-atomic="true"
         style="position:fixed;bottom:1.25rem;right:1.25rem;z-index:9999;display:flex;flex-direction:column;gap:0.5rem;"></div>

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
                        <span><?php echo _prt('avatar_change'); ?></span>
                    </label>
                    <input type="file" id="avatarInput" name="avatar"
                           accept="image/jpeg,image/png,image/webp,image/gif"
                           class="d-none" onchange="submitAvatarForm()">
                </form>
            </div>

            <div class="profile-identity">
                <h5 class="profile-name"><?php echo htmlspecialchars($user['full_name']); ?></h5>
                <span class="profile-role-badge">
                    <i class="bi bi-person-badge"></i>
                    <?php echo Helper::getRoleName($user['role']); ?>
                </span>
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
                <?php if (!empty($user['street']) || !empty($user['barangay']) || !empty($user['district']) || !empty($user['city'])): ?>
                <li>
                    <i class="bi bi-geo-alt"></i>
                    <span><?php echo htmlspecialchars(implode(', ', array_filter([$user['street'] ?? '', $user['barangay'] ?? '', $user['district'] ?? '', $user['city'] ?? '']))); ?></span>
                </li>
                <?php endif; ?>
                <li>
                    <i class="bi bi-calendar3"></i>
                    <span><?php echo _prt('lbl_joined'); ?> <?php echo date('F j, Y', strtotime($user['created_at'])); ?></span>
                </li>
                <li>
                    <i class="bi bi-circle-fill <?php echo ($user['status'] ?? 'active') === 'active' ? 'text-success' : 'text-danger'; ?>" style="font-size: 0.55rem; margin-top: 2px;"></i>
                    <span class="<?php echo ($user['status'] ?? 'active') === 'active' ? 'status-active' : 'status-inactive'; ?>">
                        <?php echo ucfirst($user['status'] ?? 'active'); ?>
                    </span>
                </li>
            </ul>

            <!-- Application Stats -->
            <div class="app-stats-grid">
                <div class="app-stat-item">
                    <span class="app-stat-value"><?php echo (int)($appStats['total'] ?? 0); ?></span>
                    <span class="app-stat-label"><?php echo _prt('stat_total'); ?></span>
                </div>
                <div class="app-stat-item app-stat-approved">
                    <span class="app-stat-value"><?php echo (int)($appStats['approved'] ?? 0); ?></span>
                    <span class="app-stat-label"><?php echo _prt('stat_approved'); ?></span>
                </div>
                <div class="app-stat-item app-stat-pending">
                    <span class="app-stat-value"><?php echo (int)($appStats['pending'] ?? 0); ?></span>
                    <span class="app-stat-label"><?php echo _prt('stat_pending'); ?></span>
                </div>
                <div class="app-stat-item app-stat-rejected">
                    <span class="app-stat-value"><?php echo (int)($appStats['rejected'] ?? 0); ?></span>
                    <span class="app-stat-label"><?php echo _prt('stat_rejected'); ?></span>
                </div>
            </div>

            <!-- Quick links -->
            <div class="sidebar-quick-links">
                <a href="/lgu-urban-planning/applicant/applications.php" class="quick-link-btn">
                    <i class="bi bi-list-ul"></i> <?php echo _prt('link_my_apps'); ?>
                </a>
                <a href="/lgu-urban-planning/applicant/messages.php" class="quick-link-btn">
                    <i class="bi bi-envelope"></i> <?php echo _prt('link_messages'); ?>
                </a>
            </div>
        </aside>

        <!-- ── RIGHT PANEL: Tabs ───────────────────────────────────────── -->
        <div class="profile-main">

            <!-- Tab nav -->
            <ul class="nav profile-tabs" id="profileTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="profile-tab-btn active" id="info-tab"
                            data-bs-toggle="tab" data-bs-target="#tab-info"
                            type="button" role="tab">
                        <i class="bi bi-person"></i> <?php echo _prt('tab_personal_info'); ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="profile-tab-btn" id="password-tab"
                            data-bs-toggle="tab" data-bs-target="#tab-password"
                            type="button" role="tab">
                        <i class="bi bi-shield-lock"></i> <?php echo _prt('tab_change_password'); ?>
                    </button>
                </li>
            </ul>

            <div class="tab-content profile-tab-content">

                <!-- ── TAB: Personal Info ─────────────────────────────── -->
                <div class="tab-pane fade show active" id="tab-info" role="tabpanel">
                    <div class="profile-card">
                        <div class="profile-card-header">
                            <div>
                                <h6 class="profile-card-title"><?php echo _prt('card_personal_title'); ?></h6>
                                <p class="profile-card-subtitle"><?php echo _prt('card_personal_sub'); ?></p>
                            </div>
                            <button class="btn btn-edit" id="editBtn" onclick="enableEdit()">
                                <i class="bi bi-pencil"></i> <?php echo _prt('btn_edit'); ?>
                            </button>
                        </div>

                        <form method="POST" id="profileForm">
                            <input type="hidden" name="action" value="update_profile">

                            <div class="profile-sections">

                                <!-- ── Section: Contact ── -->
                                <div class="profile-section">
                                    <div class="profile-section-label">
                                        <i class="bi bi-person-lines-fill"></i> <?php echo _prt('sec_contact'); ?>
                                    </div>

                                    <div class="profile-row">
                                        <div class="profile-row-field">
                                            <span class="profile-field-label"><?php echo _prt('lbl_full_name'); ?></span>
                                            <div class="view-mode">
                                                <span class="field-view-value"><?php echo htmlspecialchars($user['full_name']); ?></span>
                                            </div>
                                            <div class="edit-mode d-none">
                                                <input type="text" name="full_name" class="form-control profile-input"
                                                       value="<?php echo htmlspecialchars($user['full_name']); ?>">
                                            </div>
                                        </div>
                                        <div class="profile-row-field">
                                            <span class="profile-field-label"><?php echo _prt('lbl_email'); ?></span>
                                            <div class="view-mode">
                                                <span class="field-view-value"><?php echo htmlspecialchars($user['email']); ?></span>
                                            </div>
                                            <div class="edit-mode d-none">
                                                <input type="email" name="email" class="form-control profile-input"
                                                       value="<?php echo htmlspecialchars($user['email']); ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="profile-row">
                                        <div class="profile-row-field">
                                            <span class="profile-field-label"><?php echo _prt('lbl_phone'); ?></span>
                                            <div class="view-mode">
                                                <?php if (!empty($user['phone'])): ?>
                                                    <span class="field-view-value"><?php echo htmlspecialchars($user['phone']); ?></span>
                                                <?php else: ?>
                                                    <span class="not-set"><?php echo _prt('not_set'); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="edit-mode d-none">
                                                <input type="text" name="phone" class="form-control profile-input"
                                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                                       placeholder="<?php echo _prt('ph_phone'); ?>">
                                            </div>
                                        </div>
                                        <div class="profile-row-field">
                                            <!-- spacer -->
                                        </div>
                                    </div>
                                </div>

                                <!-- ── Section: Address ── -->
                                <div class="profile-section">
                                    <div class="profile-section-label">
                                        <i class="bi bi-geo-alt"></i> <?php echo _prt('sec_address'); ?>
                                    </div>

                                    <div class="profile-row">
                                        <div class="profile-row-field">
                                            <span class="profile-field-label"><?php echo _prt('lbl_street'); ?></span>
                                            <div class="view-mode">
                                                <?php if (!empty($user['street'])): ?>
                                                    <span class="field-view-value"><?php echo htmlspecialchars($user['street']); ?></span>
                                                <?php else: ?>
                                                    <span class="not-set"><?php echo _prt('not_set'); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="edit-mode d-none">
                                                <input type="text" name="street" class="form-control profile-input"
                                                       value="<?php echo htmlspecialchars($user['street'] ?? ''); ?>"
                                                       placeholder="<?php echo _prt('ph_street'); ?>">
                                            </div>
                                        </div>
                                        <div class="profile-row-field">
                                            <span class="profile-field-label"><?php echo _prt('lbl_barangay'); ?></span>
                                            <div class="view-mode">
                                                <?php if (!empty($user['barangay'])): ?>
                                                    <span class="field-view-value"><?php echo htmlspecialchars($user['barangay']); ?></span>
                                                <?php else: ?>
                                                    <span class="not-set"><?php echo _prt('not_set'); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="edit-mode d-none">
                                                <input type="text" name="barangay" class="form-control profile-input"
                                                       value="<?php echo htmlspecialchars($user['barangay'] ?? ''); ?>"
                                                       placeholder="<?php echo _prt('ph_barangay'); ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="profile-row">
                                        <div class="profile-row-field">
                                            <span class="profile-field-label"><?php echo _prt('lbl_city'); ?></span>
                                            <div class="view-mode">
                                                <?php if (!empty($user['city'])): ?>
                                                    <span class="field-view-value"><?php echo htmlspecialchars($user['city']); ?></span>
                                                <?php else: ?>
                                                    <span class="not-set"><?php echo _prt('not_set'); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="edit-mode d-none">
                                                <input type="text" name="city" class="form-control profile-input"
                                                       value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>"
                                                       placeholder="<?php echo _prt('ph_city'); ?>">
                                            </div>
                                        </div>
                                        <div class="profile-row-field">
                                            <span class="profile-field-label"><?php echo _prt('lbl_district'); ?></span>
                                            <div class="view-mode">
                                                <?php if (!empty($user['district'])): ?>
                                                    <span class="field-view-value"><?php echo htmlspecialchars($user['district']); ?></span>
                                                <?php else: ?>
                                                    <span class="not-set"><?php echo _prt('not_set'); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="edit-mode d-none">
                                                <input type="text" name="district" class="form-control profile-input"
                                                       value="<?php echo htmlspecialchars($user['district'] ?? ''); ?>"
                                                       placeholder="<?php echo _prt('ph_district'); ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- ── Section: Account ── -->
                                <div class="profile-section" style="border-bottom: none;">
                                    <div class="profile-section-label">
                                        <i class="bi bi-person-badge"></i> <?php echo _prt('sec_account'); ?>
                                    </div>

                                    <div class="profile-row">
                                        <div class="profile-row-field">
                                            <span class="profile-field-label"><?php echo _prt('lbl_role'); ?></span>
                                            <div class="view-mode">
                                                <span class="field-view-value"><?php echo Helper::getRoleName($user['role']); ?></span>
                                            </div>
                                            <div class="edit-mode d-none">
                                                <span class="field-view-value text-muted fst-italic" style="font-size: 0.82rem;"><?php echo _prt('cannot_change'); ?></span>
                                            </div>
                                        </div>
                                        <div class="profile-row-field">
                                            <span class="profile-field-label"><?php echo _prt('lbl_status'); ?></span>
                                            <div class="view-mode">
                                                <span class="<?php echo ($user['status'] ?? 'active') === 'active' ? 'status-active' : 'status-inactive'; ?>">
                                                    <?php echo ucfirst($user['status'] ?? 'active'); ?>
                                                </span>
                                            </div>
                                            <div class="edit-mode d-none">
                                                <span class="field-view-value text-muted fst-italic" style="font-size: 0.82rem;"><?php echo _prt('cannot_change'); ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="profile-row" style="border-top: none;">
                                        <div class="profile-row-field">
                                            <span class="profile-field-label"><?php echo _prt('lbl_member_since'); ?></span>
                                            <span class="field-view-value"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></span>
                                        </div>
                                        <div class="profile-row-field">
                                            <span class="profile-field-label"><?php echo _prt('lbl_last_updated'); ?></span>
                                            <span class="field-view-value"><?php echo date('F j, Y', strtotime($user['updated_at'] ?? $user['created_at'])); ?></span>
                                        </div>
                                    </div>
                                </div>

                            </div><!-- /.profile-sections -->

                            <!-- Action buttons (edit mode only) -->
                            <div class="edit-mode d-none profile-form-actions">
                                <button type="submit" class="btn btn-save">
                                    <i class="bi bi-check-lg"></i> <?php echo _prt('btn_save'); ?>
                                </button>
                                <button type="button" class="btn btn-cancel" onclick="cancelEdit()">
                                    <?php echo _prt('btn_cancel'); ?>
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
                                <h6 class="profile-card-title"><?php echo _prt('card_pw_title'); ?></h6>
                                <p class="profile-card-subtitle"><?php echo _prt('card_pw_sub'); ?></p>
                            </div>
                        </div>

                        <div class="pw-two-col">

                            <!-- LEFT: Form -->
                            <form method="POST" id="passwordForm">
                                <input type="hidden" name="action" value="change_password">

                                <div class="profile-fields">
                                    <div class="profile-field-group profile-field-full">
                                        <label class="profile-field-label"><?php echo _prt('lbl_current_pw'); ?></label>
                                        <div class="input-group">
                                            <input type="password" name="current_password" id="currentPw"
                                                   class="form-control profile-input">
                                            <button class="btn btn-pw-toggle" type="button" onclick="togglePw('currentPw', this)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="profile-field-group profile-field-full">
                                        <label class="profile-field-label"><?php echo _prt('lbl_new_pw'); ?></label>
                                        <div class="input-group">
                                            <input type="password" name="new_password" id="newPw"
                                                   class="form-control profile-input"
                                                   oninput="checkStrength(this.value)">
                                            <button class="btn btn-pw-toggle" type="button" onclick="togglePw('newPw', this)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                        <div class="pw-strength-bar mt-2">
                                            <div class="pw-strength-fill" id="strengthFill"></div>
                                        </div>
                                        <small class="pw-strength-label" id="strengthLabel"></small>
                                    </div>

                                    <div class="profile-field-group profile-field-full">
                                        <label class="profile-field-label"><?php echo _prt('lbl_confirm_pw'); ?></label>
                                        <div class="input-group">
                                            <input type="password" name="confirm_password" id="confirmPw"
                                                   class="form-control profile-input">
                                            <button class="btn btn-pw-toggle" type="button" onclick="togglePw('confirmPw', this)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                        <small class="pw-match-hint d-none text-danger" id="matchHint">
                                            <i class="bi bi-x-circle"></i> <?php echo _prt('pw_no_match'); ?>
                                        </small>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <button type="submit" class="btn btn-save">
                                        <i class="bi bi-shield-check"></i> <?php echo _prt('btn_update_pw'); ?>
                                    </button>
                                </div>
                            </form>

                            <!-- RIGHT: Last changed + Security tips -->
                            <div class="pw-side-info">

                                <div class="pw-last-changed">
                                    <div class="pw-last-changed-icon"><i class="bi bi-clock-history"></i></div>
                                    <div>
                                        <div class="pw-last-changed-label"><?php echo _prt('pw_last_changed'); ?></div>
                                        <div class="pw-last-changed-date">
                                            <?php echo date('F j, Y \a\t g:i A', strtotime($pwLastChanged)); ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="pw-tips">
                                    <p class="pw-tips-title">
                                        <i class="bi bi-shield-exclamation"></i> <?php echo _prt('pw_tips_title'); ?>
                                    </p>
                                    <ul class="pw-tips-list">
                                        <li><i class="bi bi-check2-circle"></i> <?php echo _prt('pw_tip_1'); ?></li>
                                        <li><i class="bi bi-check2-circle"></i> <?php echo _prt('pw_tip_2'); ?></li>
                                        <li><i class="bi bi-check2-circle"></i> <?php echo _prt('pw_tip_3'); ?></li>
                                        <li><i class="bi bi-check2-circle"></i> <?php echo _prt('pw_tip_4'); ?></li>
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
    grid-template-columns: 280px 1fr;
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
    background: rgba(0,0,0,0.5);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 3px;
    opacity: 0;
    transition: opacity 0.2s;
    cursor: pointer;
    color: #fff;
    font-size: 0.65rem;
}
.avatar-overlay i { font-size: 1.1rem; }
.avatar-upload-wrap:hover .avatar-overlay { opacity: 1; }

.profile-identity { margin-bottom: 14px; }
.profile-name {
    font-size: 1rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 6px;
    line-height: 1.3;
}
.profile-role-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 20px;
}

/* Meta list */
.profile-meta-list {
    list-style: none;
    padding: 0;
    margin: 0 0 16px;
    text-align: left;
    border-top: 1px solid #f1f5f9;
    padding-top: 14px;
}
.profile-meta-list li {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    font-size: 0.75rem;
    color: #64748b;
    padding: 5px 0;
    border-bottom: 1px solid #f8fafc;
    word-break: break-word;
}
.profile-meta-list li i {
    color: #3b82f6;
    font-size: 0.82rem;
    flex-shrink: 0;
    margin-top: 1px;
}

/* Status badges */
.status-active {
    display: inline-block;
    background: #f0fdf4;
    color: #15803d;
    border: 1px solid #bbf7d0;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 20px;
}
.status-inactive {
    display: inline-block;
    background: #fff1f2;
    color: #be123c;
    border: 1px solid #fecdd3;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 20px;
}

/* Application Stats Grid */
.app-stats-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-bottom: 16px;
    border-top: 1px solid #f1f5f9;
    padding-top: 14px;
}
.app-stat-item {
    background: #f8fafc;
    border-radius: 10px;
    padding: 10px 8px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
}
.app-stat-value {
    font-size: 1.35rem;
    font-weight: 800;
    color: #1e293b;
    line-height: 1;
}
.app-stat-label {
    font-size: 0.62rem;
    font-weight: 600;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.app-stat-approved .app-stat-value { color: #16a34a; }
.app-stat-pending  .app-stat-value { color: #d97706; }
.app-stat-rejected .app-stat-value { color: #dc2626; }

/* Quick links */
.sidebar-quick-links {
    display: flex;
    flex-direction: column;
    gap: 6px;
    border-top: 1px solid #f1f5f9;
    padding-top: 14px;
}
.quick-link-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.78rem;
    font-weight: 500;
    color: #334155;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 8px 12px;
    text-decoration: none;
    transition: all 0.2s;
}
.quick-link-btn:hover {
    background: #eff6ff;
    color: #1d4ed8;
    border-color: #bfdbfe;
}
.quick-link-btn i { font-size: 0.9rem; }

/* ── Main content ── */
.profile-main { min-width: 0; }

/* ── Tabs ── */
.profile-tabs {
    list-style: none;
    padding: 0;
    margin: 0 0 0;
    display: flex;
    gap: 0;
    border-bottom: 2px solid #e2e8f0;
}
.profile-tab-btn {
    border: none;
    background: transparent;
    padding: 10px 18px;
    font-size: 0.82rem;
    font-weight: 600;
    color: #94a3b8;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: color 0.2s, border-color 0.2s;
}
.profile-tab-btn.active,
.profile-tab-btn:hover {
    color: #2563eb;
    border-bottom-color: #2563eb;
}
.profile-tab-content { padding-top: 2px; }

/* ── Profile card ── */
.profile-card {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.06);
    padding: 22px;
    margin-top: 16px;
}
.profile-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 18px;
}
.profile-card-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 2px;
}
.profile-card-subtitle {
    font-size: 0.75rem;
    color: #94a3b8;
    margin: 0;
}

/* ── Sections ── */
.profile-section {
    border-bottom: 1px solid #f1f5f9;
    padding-bottom: 12px;
    margin-bottom: 12px;
}
.profile-section-label {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #3b82f6;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.profile-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    border-top: 1px solid #f8fafc;
}
.profile-row-field {
    padding: 10px 12px 10px 0;
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.profile-row-field + .profile-row-field {
    border-left: 1px solid #f1f5f9;
    padding-left: 12px;
}

.profile-field-label {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #94a3b8;
}
.field-view-value {
    font-size: 0.88rem;
    font-weight: 500;
    color: #1e293b;
}
.not-set {
    font-size: 0.82rem;
    color: #cbd5e1;
    font-style: italic;
}

/* ── Inputs ── */
.profile-input {
    font-size: 0.85rem;
    border-radius: 7px;
    border-color: #e2e8f0;
    padding: 7px 10px;
}
.profile-input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.12);
}

/* ── Buttons ── */
.btn-edit {
    font-size: 0.75rem;
    font-weight: 600;
    padding: 5px 12px;
    border-radius: 7px;
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
    display: flex;
    align-items: center;
    gap: 5px;
    transition: background 0.2s;
}
.btn-edit:hover { background: #dbeafe; }

.profile-form-actions {
    display: flex;
    gap: 10px;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid #f1f5f9;
}
.btn-save {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: #fff;
    border: none;
    font-size: 0.85rem;
    font-weight: 600;
    padding: 8px 20px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: opacity 0.2s;
}
.btn-save:hover { opacity: 0.9; color: #fff; }
.btn-cancel {
    background: #f1f5f9;
    color: #64748b;
    border: 1px solid #e2e8f0;
    font-size: 0.85rem;
    font-weight: 600;
    padding: 8px 16px;
    border-radius: 8px;
    transition: background 0.2s;
}
.btn-cancel:hover { background: #e2e8f0; }

/* ── Password tab ── */
.pw-two-col {
    display: grid;
    grid-template-columns: 1fr 280px;
    gap: 24px;
    align-items: start;
}
.profile-fields { display: flex; flex-direction: column; gap: 16px; }
.profile-field-group { display: flex; flex-direction: column; gap: 6px; }
.profile-field-full { grid-column: 1 / -1; }

.btn-pw-toggle {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-left: none;
    color: #94a3b8;
    padding: 0 10px;
    font-size: 0.85rem;
    transition: color 0.2s;
}
.btn-pw-toggle:hover { color: #3b82f6; }

.pw-strength-bar {
    height: 4px;
    background: #e2e8f0;
    border-radius: 4px;
    overflow: hidden;
}
.pw-strength-fill {
    height: 100%;
    width: 0;
    border-radius: 4px;
    transition: width 0.3s, background 0.3s;
}
.pw-strength-label {
    font-size: 0.72rem;
    font-weight: 600;
}
.pw-match-hint { font-size: 0.72rem; }

.pw-side-info { display: flex; flex-direction: column; gap: 12px; }
.pw-last-changed {
    display: flex;
    align-items: center;
    gap: 12px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 10px;
    padding: 12px 14px;
}
.pw-last-changed-icon {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #2563eb;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    flex-shrink: 0;
}
.pw-last-changed-label { font-size: 0.65rem; color: #64748b; font-weight: 600; }
.pw-last-changed-date  { font-size: 0.76rem; color: #1d4ed8; font-weight: 700; }
.pw-tips {
    background: #fffbeb;
    border: 1px solid #fef08a;
    border-radius: 10px;
    padding: 12px 14px;
}
.pw-tips-title {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #d97706;
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
    font-size: 0.76rem;
    color: #92400e;
    display: flex;
    align-items: flex-start;
    gap: 6px;
}
.pw-tips-list li i { color: #d97706; font-size: 0.8rem; flex-shrink: 0; margin-top: 1px; }

/* ════════════════════════════════════
   1024px — LAPTOP
════════════════════════════════════ */
@media (max-width: 1024px) {
    .profile-grid { grid-template-columns: 250px 1fr; gap: 16px; }
    .profile-card { padding: 18px; }
    /* The nested 1fr/280px password grid runs out of room once the
       outer sidebar column and header's own docked sidebar both eat
       into the width — stack it before that happens. */
    .pw-two-col { grid-template-columns: 1fr; gap: 18px; }
}

/* ════════════════════════════════════
   768px — TABLETS
════════════════════════════════════ */
@media (max-width: 768px) {
    .profile-grid { grid-template-columns: 1fr; }
    .profile-sidebar-card { position: static; }
    .pw-two-col { grid-template-columns: 1fr; }
    .profile-row { grid-template-columns: 1fr; }
    .profile-row-field + .profile-row-field { border-left: none; padding-left: 0; }
    .app-stats-grid { grid-template-columns: repeat(4, 1fr); }
}

/* ════════════════════════════════════
   480px — MOBILE
════════════════════════════════════ */
@media (max-width: 480px) {
    .profile-page { padding: 14px; }
    .profile-meta-list li { font-size: 0.72rem; gap: 7px; }
    .profile-card { padding: 14px; border-radius: 10px; }
    .profile-card-title { font-size: 0.85rem; }
    .profile-tab-btn { font-size: 0.7rem; padding: 8px 10px; gap: 4px; }
    .profile-field-label { font-size: 0.63rem; }
    .field-view-value { font-size: 0.83rem; }
    .profile-input { font-size: 0.82rem; }
    .not-set { font-size: 0.78rem; }
    .profile-row-field { padding: 8px 4px; }
    .btn-save, .btn-cancel { font-size: 0.8rem; padding: 7px 14px; }
    .btn-edit { font-size: 0.72rem; padding: 4px 10px; }
    .pw-last-changed { padding: 10px 12px; gap: 8px; }
    .pw-last-changed-icon { width: 30px; height: 30px; font-size: 0.82rem; }
    .pw-last-changed-label { font-size: 0.62rem; }
    .pw-last-changed-date { font-size: 0.73rem; }
    .pw-tips { padding: 10px 12px; }
    .pw-tips-title { font-size: 0.64rem; }
    .pw-tips-list li { font-size: 0.73rem; }
    .app-stats-grid { grid-template-columns: repeat(2, 1fr); }
}

/* ════════════════════════════════════
   320px — SMALL MOBILE
════════════════════════════════════ */
@media (max-width: 320px) {
    .profile-page { padding: 10px; }
    .avatar-upload-wrap { width: 72px; height: 72px; }
    .profile-avatar-img, .profile-avatar-initials { width: 72px; height: 72px; }
    .profile-avatar-initials { font-size: 1.5rem; }
    .profile-name { font-size: 0.9rem; }
    .profile-role-badge { font-size: 0.6rem; padding: 2px 8px; }
    .profile-meta-list li { font-size: 0.66rem; gap: 6px; }
    .app-stats-grid { gap: 6px; }
    .app-stat-item { padding: 8px 6px; }
    .app-stat-value { font-size: 1.1rem; }
    .app-stat-label { font-size: 0.55rem; }
    .quick-link-btn { font-size: 0.72rem; padding: 7px 10px; }
    .profile-card { padding: 12px; border-radius: 10px; }
    .profile-card-title { font-size: 0.8rem; }
    .profile-card-subtitle { font-size: 0.68rem; }
    .profile-tab-btn { font-size: 0.64rem; padding: 7px 8px; gap: 3px; }
    .profile-section-label { font-size: 0.62rem; }
    .profile-field-label { font-size: 0.6rem; }
    .field-view-value { font-size: 0.78rem; }
    .profile-input { font-size: 0.78rem; padding: 6px 8px; }
    .not-set { font-size: 0.72rem; }
    .profile-row-field { padding: 7px 4px; }
    .profile-form-actions { flex-direction: column; }
    .btn-save, .btn-cancel {
        font-size: 0.75rem;
        padding: 7px 12px;
        width: 100%;
        justify-content: center;
    }
    .btn-edit { font-size: 0.68rem; padding: 4px 8px; }
    .pw-last-changed {
        flex-direction: column;
        align-items: flex-start;
        gap: 6px;
        padding: 8px 10px;
    }
    .pw-last-changed-icon { width: 26px; height: 26px; font-size: 0.75rem; }
    .pw-last-changed-label { font-size: 0.58rem; }
    .pw-last-changed-date { font-size: 0.68rem; }
    .pw-tips { padding: 8px 10px; }
    .pw-tips-title { font-size: 0.6rem; }
    .pw-tips-list li { font-size: 0.68rem; }
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
[data-bs-theme="dark"] .btn-cancel { background: #334155; color: #e2e8f0; border-color: #475569; }
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
[data-bs-theme="dark"] .app-stat-item { background: #0f172a; }
[data-bs-theme="dark"] .app-stat-value { color: #f1f5f9; }
[data-bs-theme="dark"] .app-stat-label { color: #64748b; }
[data-bs-theme="dark"] .app-stats-grid { border-top-color: rgba(255,255,255,0.08); }
[data-bs-theme="dark"] .sidebar-quick-links { border-top-color: rgba(255,255,255,0.08); }
[data-bs-theme="dark"] .quick-link-btn { background: #0f172a; border-color: #334155; color: #cbd5e1; }
[data-bs-theme="dark"] .quick-link-btn:hover { background: #1e3a5f; color: #93c5fd; border-color: #1e40af; }
/* ── Toast notifications ── */
.profile-toast {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 260px;
    max-width: 360px;
    padding: 0.7rem 1rem;
    border-radius: 10px;
    background: #1e293b;
    color: #f1f5f9;
    font-size: 0.85rem;
    font-weight: 500;
    box-shadow: 0 4px 16px rgba(0,0,0,0.18);
    opacity: 0;
    transform: translateY(12px);
    transition: opacity 0.22s ease, transform 0.22s ease;
    pointer-events: none;
}
.profile-toast.toast-show   { opacity: 1; transform: translateY(0); pointer-events: auto; }
.profile-toast.toast-warning { border-left: 4px solid #f59e0b; }
.profile-toast.toast-error   { border-left: 4px solid #ef4444; }
.profile-toast.toast-success { border-left: 4px solid #22c55e; }
.profile-toast .toast-icon   { font-size: 1rem; flex-shrink: 0; }
.profile-toast .toast-close  {
    margin-left: auto; background: none; border: none;
    color: #94a3b8; cursor: pointer; padding: 0; font-size: 0.8rem; line-height: 1;
}
.profile-toast .toast-close:hover { color: #f1f5f9; }
[data-bs-theme="dark"] .profile-toast { background: #0f172a; }
</style>

<!-- ── Scripts ─────────────────────────────────────────────────────────────── -->
<script>
// ── Toast helper ──────────────────────────────────────────────────────────────
function showToast(message, type, duration) {
    type     = type     || 'warning';
    duration = duration || 3500;
    var icons = {
        warning: 'bi bi-exclamation-circle-fill text-warning',
        error:   'bi bi-x-circle-fill text-danger',
        success: 'bi bi-check-circle-fill text-success'
    };
    var container = document.getElementById('toastContainer');
    var toast = document.createElement('div');
    toast.className = 'profile-toast toast-' + type;
    toast.innerHTML =
        '<i class="' + (icons[type] || icons.warning) + ' toast-icon"></i>' +
        '<span>' + message + '</span>' +
        '<button class="toast-close" aria-label="Dismiss">&times;</button>';
    toast.querySelector('.toast-close').addEventListener('click', function () { dismissToast(toast); });
    container.appendChild(toast);
    requestAnimationFrame(function () {
        requestAnimationFrame(function () { toast.classList.add('toast-show'); });
    });
    toast._timer = setTimeout(function () { dismissToast(toast); }, duration);
}
function dismissToast(toast) {
    clearTimeout(toast._timer);
    toast.classList.remove('toast-show');
    toast.addEventListener('transitionend', function () { toast.remove(); }, { once: true });
}

// ── Toggle between view and edit modes ────────────────────────────────────────
function enableEdit() {
    document.querySelectorAll('#profileForm .view-mode').forEach(function (el) { el.classList.add('d-none'); });
    document.querySelectorAll('#profileForm .edit-mode').forEach(function (el) { el.classList.remove('d-none'); });
    document.getElementById('editBtn').classList.add('d-none');
}
function cancelEdit() {
    document.querySelectorAll('#profileForm .view-mode').forEach(function (el) { el.classList.remove('d-none'); });
    document.querySelectorAll('#profileForm .edit-mode').forEach(function (el) { el.classList.add('d-none'); });
    document.getElementById('editBtn').classList.remove('d-none');
}

// ── Auto-submit avatar form on file select ────────────────────────────────────
function submitAvatarForm() {
    document.getElementById('avatarForm').submit();
}

// ── Show/hide password ────────────────────────────────────────────────────────
function togglePw(id, btn) {
    var input = document.getElementById(id);
    var icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
}

// ── Password strength meter ───────────────────────────────────────────────────
function checkStrength(val) {
    var fill  = document.getElementById('strengthFill');
    var label = document.getElementById('strengthLabel');
    var score = 0;
    if (val.length >= 8)            score++;
    if (/[A-Z]/.test(val))          score++;
    if (/[0-9]/.test(val))          score++;
    if (/[^A-Za-z0-9]/.test(val))   score++;
    var levels = [
        { w: '25%',  bg: '#ef4444', text: 'Weak',   color: '#ef4444' },
        { w: '50%',  bg: '#f97316', text: 'Fair',   color: '#f97316' },
        { w: '75%',  bg: '#eab308', text: 'Good',   color: '#eab308' },
        { w: '100%', bg: '#22c55e', text: 'Strong', color: '#22c55e' },
    ];
    var lvl = levels[Math.max(0, score - 1)];
    fill.style.width      = val.length ? lvl.w   : '0%';
    fill.style.background = val.length ? lvl.bg  : 'transparent';
    label.textContent     = val.length ? lvl.text : '';
    label.style.color     = val.length ? lvl.color : '';
}

document.addEventListener('DOMContentLoaded', function () {

    // ── Confirm password match hint ───────────────────────────────────────────
    document.getElementById('confirmPw').addEventListener('input', function () {
        var hint = document.getElementById('matchHint');
        if (this.value && this.value !== document.getElementById('newPw').value) {
            hint.classList.remove('d-none');
        } else {
            hint.classList.add('d-none');
        }
    });

    // ── Profile form: JS validation before submit ─────────────────────────────
    var profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', function (e) {
            var fullName = profileForm.querySelector('[name="full_name"]').value.trim();
            var email    = profileForm.querySelector('[name="email"]').value.trim();
            if (!fullName) {
                e.preventDefault();
                showToast('<?php echo _prt('err_full_name'); ?>', 'warning');
                profileForm.querySelector('[name="full_name"]').focus();
                return;
            }
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                e.preventDefault();
                showToast('<?php echo _prt('err_email_invalid'); ?>', 'warning');
                profileForm.querySelector('[name="email"]').focus();
                return;
            }
        });
    }

    // ── Password form: JS validation before submit ────────────────────────────
    var passwordForm = document.getElementById('passwordForm');
    if (passwordForm) {
        passwordForm.addEventListener('submit', function (e) {
            var currentPw = document.getElementById('currentPw').value;
            var newPw     = document.getElementById('newPw').value;
            var confirmPw = document.getElementById('confirmPw').value;

            if (!currentPw) {
                e.preventDefault();
                showToast('Please enter your current password.', 'warning');
                document.getElementById('currentPw').focus();
                return;
            }
            if (!newPw) {
                e.preventDefault();
                showToast('Please enter a new password.', 'warning');
                document.getElementById('newPw').focus();
                return;
            }
            if (newPw.length < 8) {
                e.preventDefault();
                showToast('<?php echo _prt('err_pw_too_short'); ?>', 'warning');
                document.getElementById('newPw').focus();
                return;
            }
            if (!/[A-Z]/.test(newPw) || !/[a-z]/.test(newPw) || !/[0-9]/.test(newPw)) {
                e.preventDefault();
                showToast('<?php echo _prt('err_pw_complexity'); ?>', 'warning');
                document.getElementById('newPw').focus();
                return;
            }
            if (newPw !== confirmPw) {
                e.preventDefault();
                document.getElementById('matchHint').classList.remove('d-none');
                showToast('<?php echo _prt('pw_no_match'); ?>', 'warning');
                document.getElementById('confirmPw').focus();
                return;
            }
        });
    }

    // ── Show server-side feedback as toasts on page load ──────────────────────
    <?php if (!empty($success)): ?>
    showToast(<?php echo json_encode(htmlspecialchars($success)); ?>, 'success', 5000);
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
    <?php foreach ($errors as $e): ?>
    showToast(<?php echo json_encode(htmlspecialchars($e)); ?>, 'error');
    <?php endforeach; ?>
    <?php endif; ?>

    // ── If returning from a password error, switch to password tab ────────────
    <?php if (!empty($errors) && isset($_POST['action']) && $_POST['action'] === 'change_password'): ?>
    document.getElementById('password-tab').click();
    <?php endif; ?>

    // ── If returning from a profile error, re-enable edit mode ───────────────
    <?php if (!empty($errors) && isset($_POST['action']) && $_POST['action'] === 'update_profile'): ?>
    enableEdit();
    <?php endif; ?>

});
</script>

<?php include __DIR__ . '/../user/footer.php'; ?>