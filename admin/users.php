<?php
// admin/users.php - User Management
require_once '../includes/auth.php';
require_once '../includes/db.php';

if (!isLoggedIn() || !isEmployee()) {
    header('Location: ../login.php');
    exit();
}

// Self-healing schema for identity verification and archiving
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'verification_status'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `verification_status` ENUM('unverified', 'pending', 'verified', 'rejected') DEFAULT 'unverified'");
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `id_image_path` VARCHAR(255) DEFAULT NULL");
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `selfie_image_path` VARCHAR(255) DEFAULT NULL");
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `is_archived` TINYINT(1) DEFAULT 0");
    }
} catch (PDOException $e) {}

// Handle Form Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['user_id'])) {
    $action = $_POST['action'];
    $uid = (int)$_POST['user_id'];
    
    // Ensure we don't modify the currently logged-in admin unless allowed
    if ($uid !== (int)$_SESSION['user_id'] || in_array($action, ['verify_identity'])) {
        try {
            if ($action === 'verify_identity') {
                $pdo->prepare("UPDATE users SET verification_status = 'verified' WHERE id = ?")->execute([$uid]);
                $_SESSION['flash_success'] = "User identity successfully verified.";
            } elseif ($action === 'reject_identity') {
                $pdo->prepare("UPDATE users SET verification_status = 'rejected' WHERE id = ?")->execute([$uid]);
                $_SESSION['flash_success'] = "User identity rejected.";
            } elseif ($action === 'disable_user') {
                $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?")->execute([$uid]);
                $_SESSION['flash_success'] = "User account disabled.";
            } elseif ($action === 'enable_user') {
                $pdo->prepare("UPDATE users SET is_active = 1 WHERE id = ?")->execute([$uid]);
                $_SESSION['flash_success'] = "User account enabled.";
            } elseif ($action === 'archive_user') {
                $pdo->prepare("UPDATE users SET is_archived = 1 WHERE id = ?")->execute([$uid]);
                $_SESSION['flash_success'] = "User archived successfully.";
            } elseif ($action === 'restore_user') {
                $pdo->prepare("UPDATE users SET is_archived = 0 WHERE id = ?")->execute([$uid]);
                $_SESSION['flash_success'] = "User restored from archive.";
            }
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Database error updating user.";
        }
    } else {
        $_SESSION['flash_error'] = "You cannot disable or archive your own active session account.";
    }
    header('Location: users.php');
    exit();
}

$error = $success = '';
$search = trim($_GET['search'] ?? '');
$role_filter = trim($_GET['role'] ?? '');
$status_filter = trim($_GET['status'] ?? 'active');

// Flash messages
$error = $_SESSION['flash_error'] ?? '';
$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

// Build query
$conditions = [];
$params = [];
if ($search) {
    $conditions[] = "(full_name LIKE ? OR email LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($role_filter && in_array($role_filter, ['citizen','employee'])) {
    $conditions[] = "user_type = ?";
    $params[] = $role_filter;
}
if ($status_filter === 'archived') {
    $conditions[] = "is_archived = 1";
} else {
    $conditions[] = "is_archived = 0";
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Pagination
$limit = 15;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users $where");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / $limit);

// Fetch users
$stmt = $pdo->prepare("SELECT id, email, full_name, user_type, created_at, last_login, is_active, is_archived, verification_status, id_image_path, selfie_image_path FROM users $where ORDER BY id DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$users = $stmt->fetchAll();
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
    <title>User Management</title>
    <link rel="icon" type="image/png" href="../assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif; }
        body { min-height:100vh; display:flex; background:url("../assets/images/cityhall.jpeg") center/cover no-repeat fixed; position:relative; }
        body::before { content:""; position: fixed; top:0; left:0; width:100%; height:100%; backdrop-filter:blur(6px); background:rgba(0,0,0,0.35); z-index:0; }
        .main-content { flex:1; margin-left:280px; padding:30px 40px; z-index:1; position:relative; }
        .card { width:100%; max-width:1700px; background:rgba(255,255,255,0.85); backdrop-filter:blur(15px); border-radius:18px; padding:40px; box-shadow:0 6px 20px rgba(0,0,0,0.2); border:1px solid rgba(255,255,255,0.25); }
        .dashboard-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:30px; flex-wrap:wrap; gap:20px; }
        .dashboard-header h1 { color:#2c3e50; font-size:32px; font-weight:700; display:flex; align-items:center; gap:15px; }
        .dashboard-header h1 i { color:#3762c8; }
        .btn { padding:10px 20px; border-radius:8px; font-weight:600; font-size:14px; border:none; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; gap:8px; text-decoration:none; transition:all 0.2s; }
        .btn-primary { background:#3762c8; color:#fff; }
        .btn-primary:hover { background:#2851b0; }
        .btn-success { background:#16a34a; color:#fff; }
        .btn-success:hover { background:#15803d; }
        .btn-danger { background:#dc2626; color:#fff; }
        .btn-danger:hover { background:#b91c1c; }
        .btn-warning { background:#f59e0b; color:#fff; }
        .btn-warning:hover { background:#d97706; }
        .btn-outline { background:transparent; border:1px solid #cbd5e1; color:#64748b; }
        .btn-outline:hover { background:#f8f9fa; }
        
        .alert { padding:15px 20px; border-radius:8px; margin-bottom:25px; font-size:14px; font-weight:500; display:flex; align-items:center; gap:10px; }
        .alert-error { background:#fde8e8; color:#c0392b; border:1px solid #f8b4b4; }
        .alert-success { background:#e2fbe8; color:#1e7e34; border:1px solid #b8f0c5; }
        .filter-panel { background:white; padding:20px; border-radius:12px; margin-bottom:25px; display:flex; gap:15px; align-items:flex-end; flex-wrap:wrap; box-shadow:0 4px 10px rgba(0,0,0,0.05); }
        .form-group { display:flex; flex-direction:column; gap:6px; flex:1; min-width:160px; }
        .form-group label { font-size:12px; font-weight:600; color:#64748b; }
        .form-control { padding:10px 14px; border-radius:8px; border:1px solid #cbd5e1; font-size:14px; outline:none; width:100%; }
        .form-control:focus { border-color:#3762c8; }
        .table-section { background:white; border-radius:12px; padding:25px; box-shadow:0 4px 10px rgba(0,0,0,0.05); }
        .table-container { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; text-align:left; }
        th { background:#f8f9fa; color:#475569; font-weight:600; font-size:12px; text-transform:uppercase; padding:12px 16px; border-bottom:2px solid #e2e8f0; }
        td { padding:14px 16px; border-bottom:1px solid #edf2f7; font-size:14px; color:#2c3e50; }
        tr.clickable-row { cursor:pointer; transition: background 0.15s ease; }
        tr.clickable-row:hover td { background:#f1f5f9; }
        .badge { display:inline-block; padding:4px 10px; border-radius:99px; font-size:10px; font-weight:700; text-transform:uppercase; }
        
        .badge-citizen { background:#e0f2fe; color:#0284c7; }
        .badge-employee { background:#dbeafe; color:#1e40af; }
        
        .badge-active { background:#d1fae5; color:#059669; }
        .badge-inactive { background:#fee2e2; color:#b91c1c; }
        .badge-archived { background:#f1f5f9; color:#475569; }
        
        .verif-unverified { background:#fef3c7; color:#d97706; }
        .verif-pending { background:#fef08a; color:#ca8a04; }
        .verif-verified { background:#dcfce7; color:#16a34a; }
        .verif-rejected { background:#fee2e2; color:#dc2626; }

        .pagination-container { display:flex; justify-content:space-between; align-items:center; margin-top:20px; }
        .pagination-info { font-size:13px; color:#64748b; }
        .pagination-links { display:flex; gap:6px; }
        .page-link { padding:6px 12px; border-radius:6px; border:1px solid #cbd5e1; text-decoration:none; color:#64748b; font-size:13px; font-weight:500; }
        .page-link:hover { border-color:#3762c8; color:#3762c8; background:#f8fafc; }
        .page-link.active { background:#3762c8; color:#fff; border-color:#3762c8; }

        /* MODAL CSS */
        .modal-overlay { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:9999; display:none; justify-content:center; align-items:center; backdrop-filter:blur(4px); }
        .modal-overlay.active { display:flex; }
        .modal-box { background:white; border-radius:12px; width:90%; max-width:700px; max-height:90vh; overflow-y:auto; box-shadow:0 10px 30px rgba(0,0,0,0.3); position:relative; }
        .modal-header { padding:20px 25px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; background:white; z-index:10; border-radius:12px 12px 0 0; }
        .modal-header h2 { font-size:20px; color:#1e293b; }
        .modal-close { background:none; border:none; font-size:24px; color:#94a3b8; cursor:pointer; }
        .modal-close:hover { color:#1e293b; }
        .modal-body { padding:25px; }
        
        .user-detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:25px; background:#f8fafc; padding:20px; border-radius:8px; border:1px solid #e2e8f0; }
        .detail-item label { display:block; font-size:12px; color:#64748b; font-weight:600; margin-bottom:4px; text-transform:uppercase; }
        .detail-item span { font-size:15px; color:#0f172a; font-weight:500; }
        
        .verification-docs { margin-top:20px; }
        .verification-docs h3 { font-size:16px; color:#1e293b; margin-bottom:15px; border-bottom:1px solid #e2e8f0; padding-bottom:10px; }
        .images-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
        .img-container { text-align:center; background:#f1f5f9; padding:10px; border-radius:8px; border:1px solid #cbd5e1; }
        .img-container p { font-size:13px; font-weight:600; margin-bottom:10px; color:#475569; }
        .img-container img { max-width:100%; max-height:250px; object-fit:contain; border-radius:4px; cursor:pointer; transition:transform 0.2s; }
        .img-container img:hover { transform:scale(1.02); }
        .no-image { padding:40px 0; color:#94a3b8; font-size:13px; font-style:italic; }
        
        .modal-actions { padding:20px 25px; border-top:1px solid #e2e8f0; background:#f8fafc; border-radius:0 0 12px 12px; display:flex; justify-content:space-between; flex-wrap:wrap; gap:10px; }
        .action-group { display:flex; gap:10px; }

        /* ===== DARK THEME OVERRIDES ===== */
        .dark-theme .card { background: rgba(30, 41, 59, 0.9) !important; border: 1px solid rgba(255, 255, 255, 0.1) !important; color: #f8fafc !important; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5) !important; }
        .dark-theme .dashboard-header h1 { color: #f8fafc !important; }
        .dark-theme .filter-panel, .dark-theme .table-section { background: #1e293b !important; border: 1px solid #334155 !important; box-shadow: none !important; }
        .dark-theme .form-control { background: #0f172a !important; color: #f8fafc !important; border-color: #334155 !important; }
        .dark-theme .form-control:focus { border-color: #3b82f6 !important; box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25) !important; }
        .dark-theme .form-group label { color: #94a3b8 !important; }
        .dark-theme th { background: #0f172a !important; color: #94a3b8 !important; border-bottom-color: #334155 !important; }
        .dark-theme td { border-bottom-color: #334155 !important; color: #cbd5e1 !important; }
        .dark-theme tr.clickable-row:hover td { background: rgba(255, 255, 255, 0.04) !important; }
        
        .dark-theme .modal-box { background:#1e293b; color:#f8fafc; }
        .dark-theme .modal-header { background:#1e293b; border-bottom-color:#334155; }
        .dark-theme .modal-header h2 { color:#f8fafc; }
        .dark-theme .user-detail-grid { background:#0f172a; border-color:#334155; }
        .dark-theme .detail-item span { color:#f8fafc; }
        .dark-theme .verification-docs h3 { color:#f8fafc; border-bottom-color:#334155; }
        .dark-theme .img-container { background:#0f172a; border-color:#334155; }
        .dark-theme .img-container p { color:#cbd5e1; }
        .dark-theme .modal-actions { background:#0f172a; border-top-color:#334155; }
    </style>
</head>
<body>
<?php $sidebarBase = '../'; include '../includes/utilities_sidebar.php'; ?>
<main class="main-content">
    <div class="card">
        <div class="dashboard-header">
            <div>
                <h1><i class="fas fa-users-cog"></i> User Management</h1>
                <p style="color:#64748b; font-size:14px;">Monitor LGU user accounts, verify identities, and manage access.</p>
            </div>
            <div class="header-action-group" style="display:flex; gap:10px;">
                <a href="activity_logs.php" class="btn btn-outline"><i class="fas fa-scroll"></i> Activity Log</a>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="GET" class="filter-panel">
            <div class="form-group" style="flex:2;">
                <label>Search</label>
                <input type="text" name="search" class="form-control" placeholder="Search by name or email..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="role" class="form-control">
                    <option value="">All</option>
                    <option value="citizen" <?php echo $role_filter=='citizen'?'selected':''; ?>>Citizen</option>
                    <option value="employee" <?php echo $role_filter=='employee'?'selected':''; ?>>Employee</option>
                </select>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="active" <?php echo $status_filter=='active'?'selected':''; ?>>Active & Disabled</option>
                    <option value="archived" <?php echo $status_filter=='archived'?'selected':''; ?>>Archived</option>
                </select>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
                <a href="users.php" class="btn btn-outline">Reset</a>
            </div>
        </form>

        <div class="table-section">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Verification</th>
                            <th>Registered</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr><td colspan="7" style="text-align:center; padding:30px; color:#94a3b8;">No users found.</td></tr>
                        <?php else: foreach ($users as $u): 
                            $isSelf = ($u['id'] == $_SESSION['user_id']); 
                            $statusClass = $u['is_archived'] ? 'archived' : ($u['is_active'] ? 'active' : 'inactive');
                            $statusText = $u['is_archived'] ? 'Archived' : ($u['is_active'] ? 'Active' : 'Disabled');
                            $verifStatus = $u['verification_status'] ?? 'unverified';
                            
                            // Safe JSON for modal
                            $userJson = htmlspecialchars(json_encode([
                                'id' => $u['id'],
                                'full_name' => $u['full_name'],
                                'email' => $u['email'],
                                'user_type' => ucfirst($u['user_type']),
                                'is_active' => $u['is_active'],
                                'is_archived' => $u['is_archived'],
                                'verification_status' => $verifStatus,
                                'id_image_path' => $u['id_image_path'],
                                'selfie_image_path' => $u['selfie_image_path'],
                                'created_at' => date('M d, Y h:i A', strtotime($u['created_at'])),
                                'last_login' => $u['last_login'] ? date('M d, Y h:i A', strtotime($u['last_login'])) : 'Never',
                                'is_self' => $isSelf
                            ]), ENT_QUOTES, 'UTF-8');
                        ?>
                            <tr class="clickable-row" onclick="openUserModal(<?= $userJson ?>)">
                                <td><strong><?php echo $u['id']; ?></strong></td>
                                <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                <td><span class="badge badge-<?php echo $u['user_type']; ?>"><?php echo ucfirst($u['user_type']); ?></span></td>
                                <td><span class="badge badge-<?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
                                <td><span class="badge verif-<?php echo $verifStatus; ?>"><?php echo ucfirst($verifStatus); ?></span></td>
                                <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($totalPages > 1): ?>
                <div class="pagination-container">
                    <div class="pagination-info">Showing <?php echo $offset+1; ?> to <?php echo min($total, $offset+$limit); ?> of <?php echo $total; ?></div>
                    <div class="pagination-links">
                        <?php for ($i=1; $i<=$totalPages; $i++): ?>
                            <a href="users.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>" class="page-link <?php echo $page==$i?'active':''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- USER MANAGEMENT MODAL -->
<div class="modal-overlay" id="userModalOverlay" onclick="closeModalOnBg(event)">
    <div class="modal-box" id="userModalContent">
        <div class="modal-header">
            <h2><i class="fas fa-user-circle"></i> User Profile</h2>
            <button class="modal-close" onclick="closeUserModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="user-detail-grid">
                <div class="detail-item">
                    <label>Full Name</label>
                    <span id="m_fullname"></span>
                </div>
                <div class="detail-item">
                    <label>Email Address</label>
                    <span id="m_email"></span>
                </div>
                <div class="detail-item">
                    <label>Role</label>
                    <span id="m_role"></span>
                </div>
                <div class="detail-item">
                    <label>Status</label>
                    <span id="m_status"></span>
                </div>
                <div class="detail-item">
                    <label>Registered On</label>
                    <span id="m_registered"></span>
                </div>
                <div class="detail-item">
                    <label>Last Login</label>
                    <span id="m_lastlogin"></span>
                </div>
            </div>

            <!-- Identity Verification Section (for Citizens) -->
            <div id="m_verification_section" class="verification-docs" style="display:none;">
                <h3><i class="fas fa-id-card"></i> Identity Verification <span id="m_verif_badge" class="badge"></span></h3>
                <div class="images-grid">
                    <div class="img-container">
                        <p>Valid ID</p>
                        <div id="m_id_img"></div>
                    </div>
                    <div class="img-container">
                        <p>Selfie with ID</p>
                        <div id="m_selfie_img"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="modal-actions">
            <!-- Verification Actions -->
            <div class="action-group" id="m_action_verif" style="display:none;">
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="user_id" id="f_verif_uid1">
                    <input type="hidden" name="action" value="verify_identity">
                    <button type="submit" class="btn btn-success" title="Approve Identity"><i class="fas fa-check-circle"></i> Verify</button>
                </form>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="user_id" id="f_verif_uid2">
                    <input type="hidden" name="action" value="reject_identity">
                    <button type="submit" class="btn btn-danger" title="Reject Identity"><i class="fas fa-times-circle"></i> Reject</button>
                </form>
            </div>
            
            <div style="flex:1;"></div>
            
            <!-- Account Control Actions -->
            <div class="action-group" id="m_action_control">
                <form method="POST" id="form_toggle_active" style="display:inline;">
                    <input type="hidden" name="user_id" id="f_act_uid">
                    <input type="hidden" name="action" id="f_act_action">
                    <button type="submit" class="btn btn-warning" id="btn_toggle_active"></button>
                </form>
                <form method="POST" id="form_toggle_archive" style="display:inline;">
                    <input type="hidden" name="user_id" id="f_arc_uid">
                    <input type="hidden" name="action" id="f_arc_action">
                    <button type="submit" class="btn btn-outline" id="btn_toggle_archive" style="border-color:#ef4444; color:#ef4444;"><i class="fas fa-archive"></i> Archive</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function openUserModal(user) {
        document.getElementById('userModalOverlay').classList.add('active');
        
        document.getElementById('m_fullname').innerText = user.full_name;
        document.getElementById('m_email').innerText = user.email;
        document.getElementById('m_role').innerText = user.user_type;
        document.getElementById('m_registered').innerText = user.created_at;
        document.getElementById('m_lastlogin').innerText = user.last_login;
        
        let statusText = user.is_archived ? 'Archived' : (user.is_active ? 'Active' : 'Disabled');
        document.getElementById('m_status').innerText = statusText;
        
        // Verification section
        const verifSec = document.getElementById('m_verification_section');
        const verifAction = document.getElementById('m_action_verif');
        const actControl = document.getElementById('m_action_control');
        
        if (user.user_type === 'Citizen') {
            verifSec.style.display = 'block';
            
            let badgeClass = 'verif-' + user.verification_status;
            let verifBadge = document.getElementById('m_verif_badge');
            verifBadge.className = 'badge ' + badgeClass;
            verifBadge.innerText = user.verification_status.toUpperCase();
            
            // Render images
            document.getElementById('m_id_img').innerHTML = user.id_image_path 
                ? `<a href="../${user.id_image_path}" target="_blank"><img src="../${user.id_image_path}" alt="ID Image"></a>` 
                : `<div class="no-image">No ID uploaded</div>`;
                
            document.getElementById('m_selfie_img').innerHTML = user.selfie_image_path 
                ? `<a href="../${user.selfie_image_path}" target="_blank"><img src="../${user.selfie_image_path}" alt="Selfie Image"></a>` 
                : `<div class="no-image">No Selfie uploaded</div>`;
                
            // Show Verify/Reject buttons if pending
            if (user.verification_status === 'pending') {
                verifAction.style.display = 'flex';
                document.getElementById('f_verif_uid1').value = user.id;
                document.getElementById('f_verif_uid2').value = user.id;
            } else {
                verifAction.style.display = 'none';
            }
        } else {
            verifSec.style.display = 'none';
            verifAction.style.display = 'none';
        }
        
        // Control section logic
        if (user.is_self) {
            actControl.style.display = 'none'; // Cannot disable/archive self
        } else {
            actControl.style.display = 'flex';
            
            document.getElementById('f_act_uid').value = user.id;
            document.getElementById('f_arc_uid').value = user.id;
            
            let btnAct = document.getElementById('btn_toggle_active');
            if (user.is_active) {
                document.getElementById('f_act_action').value = 'disable_user';
                btnAct.innerHTML = '<i class="fas fa-ban"></i> Disable User';
                btnAct.className = 'btn btn-warning';
            } else {
                document.getElementById('f_act_action').value = 'enable_user';
                btnAct.innerHTML = '<i class="fas fa-check"></i> Enable User';
                btnAct.className = 'btn btn-success';
            }
            
            let btnArc = document.getElementById('btn_toggle_archive');
            if (user.is_archived) {
                document.getElementById('f_arc_action').value = 'restore_user';
                btnArc.innerHTML = '<i class="fas fa-box-open"></i> Restore from Archive';
                btnArc.style = "border-color:#10b981; color:#10b981;";
            } else {
                document.getElementById('f_arc_action').value = 'archive_user';
                btnArc.innerHTML = '<i class="fas fa-archive"></i> Archive User';
                btnArc.style = "border-color:#ef4444; color:#ef4444;";
            }
        }
    }

    function closeUserModal() {
        document.getElementById('userModalOverlay').classList.remove('active');
    }

    function closeModalOnBg(e) {
        if (e.target.id === 'userModalOverlay') {
            closeUserModal();
        }
    }
</script>
</body>
</html>