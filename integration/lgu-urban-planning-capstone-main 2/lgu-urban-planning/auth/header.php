<?php

// Header

require_once __DIR__ . '/../core/Database.php';
$dbHeader = Database::getInstance()->getConnection();

$stmt = $dbHeader->query(
    "SELECT setting_key, setting_value, is_active
     FROM system_settings
     WHERE setting_key IN ('system_announcement', 'system_announcement_type')"
);
$_annRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$_annMap  = [];
foreach ($_annRows as $_r) { $_annMap[$_r['setting_key']] = $_r; }

$announcement     = ($_annMap['system_announcement']['is_active'] ?? 0)
                    ? ($_annMap['system_announcement']['setting_value'] ?? '')
                    : '';
$_rawType         = $_annMap['system_announcement_type']['setting_value'] ?? 'warning';
$announcementType = in_array($_rawType, ['info', 'warning', 'success', 'danger'], true)
                    ? $_rawType : 'warning';
unset($_annRows, $_annMap, $_r, $_rawType);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'LGU Urban Planning System'; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="icon" type="image/x-icon" href="assets/upad-logo.png" />
        
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap');
        
        * { font-family: 'Poppins', sans-serif; }
        
html, body { height: 100%; margin: 0; padding: 0; }
body { background-color: #000; min-height: 100vh; display: flex; flex-direction: column; background: url("assets/img/cityhall.webp") no-repeat center center fixed; background-size: cover; position: relative; }
body::before { content: ""; position: fixed; top: 0; left: 0; width: 100%; height: 100%; backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); background: rgba(0, 0, 0, 0.4); z-index: 0; pointer-events: none; }

/* ANNOUNCEMENT */
.announcement-banner { position: absolute; top: 0; left: 0; width: 100%; z-index: 1500; box-shadow: 0 4px 15px rgba(0,0,0,0.3); animation: slideDown 0.5s ease; display: flex; align-items: center; justify-content: center; min-height: 45px; }
.announcement-banner .btn-close { position: absolute; top: 50%; right: 15px; transform: translateY(-50%); padding: 0.5rem; margin: 0; }

@keyframes slideDown { from { transform: translateY(-100%); } to { transform: translateY(0); } }

.main-header { width: 100%; padding: 15px 60px; display: flex; justify-content: space-between; align-items: center; background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border-bottom: 1px solid rgba(255, 255, 255, 0.2); box-shadow: 0 4px 25px rgba(0,0,0,0.2); position: sticky; top: 0; z-index: 1100; }
.main-header, main, .login-container, .register-container, footer { position: relative; z-index: 1; }

.header-brand-link { text-decoration: none; color: #fff; opacity: .9; transition: .2s; }
.header-brand-link:hover { opacity: 1; }

/* Brand Icon Styling */
.brand-icon-wrap { width: 32px; height: 32px; background: #ffffff; border-radius: 8px; display: flex; align-items: center; justify-content: center; padding: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.2); flex-shrink: 0; }
.brand-icon { width: 100%; height: 100%; object-fit: contain; }

.password-wrapper { position: relative; }
.password-toggle { position: absolute; top: 50%; right: 15px; transform: translateY(-50%); cursor: pointer; color: #6b7280; z-index: 10; }

/* DARK MODE OVERRIDES */
[data-bs-theme="dark"] body::before { background: rgba(0, 0, 0, 0.85) !important; }
[data-bs-theme="dark"] .main-header { background: rgba(15, 23, 42, 0.9) !important; border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important; }
[data-bs-theme="dark"] body .login-card, [data-bs-theme="dark"] body .register-card { background: rgba(30, 41, 59, 0.95) !important; color: #ffffff !important; border: 1px solid rgba(255, 255, 255, 0.15) !important; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5) !important; }
[data-bs-theme="dark"] body .form-label, [data-bs-theme="dark"] body .text-dark, [data-bs-theme="dark"] body h4, [data-bs-theme="dark"] body h5, [data-bs-theme="dark"] body h6, [data-bs-theme="dark"] body .fw-bold { color: #f8fafc !important; }
[data-bs-theme="dark"] body .form-control { background-color: rgba(15, 23, 42, 0.8) !important; border-color: rgba(255, 255, 255, 0.1) !important; color: #ffffff !important; }
[data-bs-theme="dark"] body .form-control::placeholder { color: #94a3b8 !important; }
[data-bs-theme="dark"] body select.form-control option { background-color: #1e293b; color: white; }
[data-bs-theme="dark"] body .input-group-text { background-color: #334155 !important; border-color: rgba(255, 255, 255, 0.1) !important; color: #ffffff !important; }
[data-bs-theme="dark"] body .text-muted, [data-bs-theme="dark"] body .login-link, [data-bs-theme="dark"] body .small { color: #94a3b8 !important; }
[data-bs-theme="dark"] body .login-link { border-top-color: rgba(255, 255, 255, 0.1) !important; }
[data-bs-theme="dark"] body a:not(.btn) { color: #60a5fa !important; }
[data-bs-theme="dark"] body .password-toggle, [data-bs-theme="dark"] body .cursor-pointer { color: #cbd5e1 !important; }
[data-bs-theme="dark"] body .strength-meter { background-color: #334155 !important; }

/* MOBILE RESPONSIVE */
@media (max-width: 768px) {
    .main-header { padding: 10px 20px; }
    .header-brand h6 { font-size: 0.85rem; white-space: nowrap; }
    .header-accessibility { gap: 8px !important; }
    .brand-icon-wrap { width: 28px; height: 28px; border-radius: 7px; padding: 3px; }
}

@media (max-width: 425px) {
    .main-header { padding: 8px 12px; }
    .header-brand h6 { font-size: 0.78rem; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .header-accessibility { gap: 6px !important; }
    .header-accessibility .btn-outline-light { padding: 2px 8px !important; font-size: 11px !important; }
    .header-accessibility .bi-house-door,
    .header-accessibility .bi-moon-stars { font-size: 1rem !important; }
    .announcement-banner { font-size: 0.72rem; padding: 8px 30px; }
    .brand-icon-wrap { width: 24px; height: 24px; border-radius: 6px; padding: 3px; }
}

@media (max-width: 320px) {
    .main-header { padding: 7px 10px; }
    .header-brand h6 { display: none; }
    .header-accessibility { gap: 5px !important; }
    .header-accessibility .btn-outline-light { padding: 2px 6px !important; font-size: 10px !important; }
    .header-accessibility .bi-house-door,
    .header-accessibility .bi-moon-stars { font-size: 0.95rem !important; }
}

</style>
</head>
<body>

<?php if (!empty($announcement)): ?>
    <div id="announcementAlert" class="alert alert-<?php echo $announcementType; ?> alert-dismissible fade show border-0 rounded-0 m-0 text-center announcement-banner" role="alert">
        <div class="d-flex align-items-center justify-content-center w-100">
            <i class="bi bi-megaphone-fill me-2"></i>
            <strong>Notice:</strong>&nbsp;<?php echo htmlspecialchars($announcement); ?>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<header class="main-header">
<div class="header-brand">
        <a href="index.php" class="header-brand-link d-flex align-items-center">
            <div class="brand-icon-wrap me-2">
                <img src="assets/upad-logo.png" alt="Logo" class="brand-icon">
            </div>
            <h6 class="mb-0 fw-normal">Urban Planning and Development</h6>
        </a>
    </div>
    <div class="header-accessibility d-flex align-items-center gap-2">
        <a href="landingpage.php" class="text-white opacity-75 d-flex align-items-center" title="Home"><i class="bi bi-house-door" style="font-size:1.1rem;"></i></a>
        <div class="btn-group btn-group-sm">
            <button type="button" id="btn-en" class="btn btn-outline-light active" style="padding:3px 10px;font-size:12px;">EN</button>
            <button type="button" id="btn-tl" class="btn btn-outline-light" style="padding:3px 10px;font-size:12px;">TL</button>
        </div>
        <button class="btn btn-link text-white p-0 d-flex align-items-center" id="darkModeBtn" type="button"><i class="bi bi-moon-stars" style="font-size:1.1rem;"></i></button>
    </div>
</header>