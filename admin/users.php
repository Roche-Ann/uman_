<?php
// admin/users.php - User Management (FIXED EDIT MODAL)
require_once '../includes/auth.php';
require_once '../includes/db.php';

if (!isLoggedIn() || !isEmployee()) {
    header('Location: ../login.php');
    exit();
}

$error = $success = '';
$search = trim($_GET['search'] ?? '');
$role_filter = trim($_GET['role'] ?? '');

// ============================================================
// HANDLE POST ACTIONS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ----- ADD USER -----
    if ($action === 'add') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';
        $user_type = $_POST['user_type'] ?? 'citizen';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($full_name) || empty($email) || empty($password)) {
            $_SESSION['flash_error'] = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = 'Invalid email.';
        } elseif (strlen($password) < 6) {
            $_SESSION['flash_error'] = 'Password must be at least 6 characters.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $_SESSION['flash_error'] = 'Email already exists.';
                } else {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, user_type, is_active, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$full_name, $email, $hashed, $user_type, $is_active]);
                    $_SESSION['flash_success'] = "User '$full_name' added.";
                }
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = 'DB error: ' . $e->getMessage();
            }
        }
        header('Location: users.php' . ($search ? '?search=' . urlencode($search) : ''));
        exit();
    }
    
    // ----- EDIT USER (FIXED) -----
    if ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $full_name = trim($_POST['full_name'] ?? '');
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $user_type = $_POST['user_type'] ?? 'citizen';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $new_password = trim($_POST['new_password'] ?? '');
        
        // Debug: log the received data
        error_log("Edit User - ID: $id, Name: $full_name, Email: $email, Role: $user_type, Active: $is_active");
        
        if ($id <= 0 || empty($full_name) || empty($email)) {
            $_SESSION['flash_error'] = 'Name and email are required.';
        } else {
            try {
                // Check email not used by other users
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $id]);
                if ($stmt->fetch()) {
                    $_SESSION['flash_error'] = 'Email already used by another user.';
                } else {
                    $sql = "UPDATE users SET full_name = ?, email = ?, user_type = ?, is_active = ?";
                    $params = [$full_name, $email, $user_type, $is_active];
                    if (!empty($new_password)) {
                        $sql .= ", password = ?";
                        $params[] = password_hash($new_password, PASSWORD_DEFAULT);
                    }
                    $sql .= " WHERE id = ?";
                    $params[] = $id;
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    
                    if ($stmt->rowCount() > 0) {
                        $_SESSION['flash_success'] = "User updated successfully.";
                    } else {
                        $_SESSION['flash_success'] = "No changes made or user not found.";
                    }
                }
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = 'DB error: ' . $e->getMessage();
            }
        }
        header('Location: users.php' . ($search ? '?search=' . urlencode($search) : ''));
        exit();
    }
    
    // ----- TOGGLE -----
    if ($action === 'toggle') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0 && $id != $_SESSION['user_id']) {
            $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['flash_success'] = "User status toggled.";
        } else {
            $_SESSION['flash_error'] = "Cannot change your own status.";
        }
        header('Location: users.php' . ($search ? '?search=' . urlencode($search) : ''));
        exit();
    }
    
    // ----- DELETE -----
    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0 && $id != $_SESSION['user_id']) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['flash_success'] = "User deleted.";
        } else {
            $_SESSION['flash_error'] = "Cannot delete your own account.";
        }
        header('Location: users.php' . ($search ? '?search=' . urlencode($search) : ''));
        exit();
    }
}

// ============================================================
// FLASH MESSAGES
// ============================================================
$error = $_SESSION['flash_error'] ?? '';
$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

// ============================================================
// BUILD QUERY
// ============================================================
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
$stmt = $pdo->prepare("SELECT *, last_login, created_at FROM users $where ORDER BY id DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$users = $stmt->fetchAll();

// Fetch user activity details for each user
foreach ($users as &$u) {
    $userId = $u['id'];
    $u['service_requests'] = [];
    $u['service_requests_count'] = 0;
    $u['otps'] = [];
    $u['otp_count'] = 0;
    
    try {
        $srStmt = $pdo->prepare("SELECT id, request_type, utility_type, status, created_at FROM service_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
        $srStmt->execute([$userId]);
        $u['service_requests'] = $srStmt->fetchAll(PDO::FETCH_ASSOC);
        $u['service_requests_count'] = count($u['service_requests']);
    } catch (Exception $e) {
        // Fallback if table/schema differs
    }
    
    try {
        $otpStmt = $pdo->prepare("SELECT id, created_at, used FROM otps WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
        $otpStmt->execute([$userId]);
        $u['otps'] = $otpStmt->fetchAll(PDO::FETCH_ASSOC);
        $u['otp_count'] = count($u['otps']);
    } catch (Exception $e) {
        // Fallback
    }
}
unset($u);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
    <link rel="icon" type="image/png" href="../assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif; }
        body { min-height:100vh; display:flex; background:url("../assets/images/cityhall.jpeg") center/cover no-repeat fixed; position:relative; }
        body::before { content:""; position:absolute; top:0; left:0; width:100%; height:100%; backdrop-filter:blur(6px); background:rgba(0,0,0,0.35); z-index:0; }
        .main-content { flex:1; margin-left:280px; padding:30px 40px; z-index:1; position:relative; }
        .card { width:100%; max-width:1700px; background:rgba(255,255,255,0.85); backdrop-filter:blur(15px); border-radius:18px; padding:40px; box-shadow:0 6px 20px rgba(0,0,0,0.2); border:1px solid rgba(255,255,255,0.25); }
        .dashboard-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:30px; flex-wrap:wrap; gap:20px; }
        .dashboard-header h1 { color:#2c3e50; font-size:32px; font-weight:700; display:flex; align-items:center; gap:15px; }
        .dashboard-header h1 i { color:#3762c8; }
        .btn { padding:10px 20px; border-radius:8px; font-weight:600; font-size:14px; border:none; cursor:pointer; display:inline-flex; align-items:center; gap:8px; text-decoration:none; }
        .btn-primary { background:#3762c8; color:#fff; }
        .btn-primary:hover { background:#2851b0; }
        .btn-outline { background:transparent; border:1px solid #cbd5e1; color:#64748b; }
        .btn-outline:hover { background:#f8f9fa; }
        .btn-danger { background:#dc3545; color:#fff; }
        .btn-warning { background:#ffc107; color:#212529; }
        .btn-sm { padding:5px 10px; font-size:12px; }
        .alert { padding:15px 20px; border-radius:8px; margin-bottom:25px; font-size:14px; font-weight:500; display:flex; align-items:center; gap:10px; }
        .alert-error { background:#fde8e8; color:#c0392b; border:1px solid #f8b4b4; }
        .alert-success { background:#e2fbe8; color:#1e7e34; border:1px solid #b8f0c5; }
        .filter-panel { background:white; padding:20px; border-radius:12px; margin-bottom:25px; display:flex; gap:15px; align-items:flex-end; flex-wrap:wrap; box-shadow:0 4px 10px rgba(0,0,0,0.05); }
        .form-group { display:flex; flex-direction:column; gap:6px; flex:1; min-width:160px; }
        .form-group label { font-size:12px; font-weight:600; color:#64748b; }
        .form-control { padding:10px 14px; border-radius:8px; border:1px solid #cbd5e1; font-size:14px; outline:none; }
        .form-control:focus { border-color:#3762c8; }
        .table-section { background:white; border-radius:12px; padding:25px; box-shadow:0 4px 10px rgba(0,0,0,0.05); }
        .table-container { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; text-align:left; }
        th { background:#f8f9fa; color:#475569; font-weight:600; font-size:12px; text-transform:uppercase; padding:12px 16px; border-bottom:2px solid #e2e8f0; }
        td { padding:14px 16px; border-bottom:1px solid #edf2f7; font-size:14px; color:#2c3e50; }
        tr:hover td { background:#fcfcfc; }
        .badge { display:inline-block; padding:4px 10px; border-radius:99px; font-size:10px; font-weight:700; text-transform:uppercase; }
        .badge-citizen { background:#e0f2fe; color:#0284c7; }
        .badge-employee { background:#dbeafe; color:#1e40af; }
        .badge-active { background:#e2fbe8; color:#1e7e34; }
        .badge-inactive { background:#fde8e8; color:#bd2130; }
        .btn-icon { width:32px; height:32px; border-radius:6px; display:inline-flex; align-items:center; justify-content:center; border:none; cursor:pointer; font-size:13px; transition:all 0.2s; }
        .btn-icon-activity { background:#e0e7ff; color:#4338ca; }
        .btn-icon-activity:hover { background:#c7d2fe; }
        .btn-icon-edit { background:#fef9e7; color:#d39e00; }
        .btn-icon-edit:hover { background:#fce8b2; }
        .btn-icon-toggle { background:#e0f2fe; color:#0284c7; }
        .btn-icon-toggle:hover { background:#bae6fd; }
        .btn-icon-delete { background:#fde8e8; color:#bd2130; }
        .btn-icon-delete:hover { background:#f8b4b4; }
        .pagination-container { display:flex; justify-content:space-between; align-items:center; margin-top:20px; }
        .pagination-info { font-size:13px; color:#64748b; }
        .pagination-links { display:flex; gap:6px; }
        .page-link { padding:6px 12px; border-radius:6px; border:1px solid #cbd5e1; text-decoration:none; color:#64748b; font-size:13px; font-weight:500; }
        .page-link:hover { border-color:#3762c8; color:#3762c8; background:#f8fafc; }
        .page-link.active { background:#3762c8; color:#fff; border-color:#3762c8; }
        .modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2000; justify-content:center; align-items:center; backdrop-filter:blur(4px); }
        .modal.open { display:flex; }
        .modal-content { background:white; width:90%; max-width:550px; border-radius:16px; box-shadow:0 10px 30px rgba(0,0,0,0.15); overflow:hidden; animation:modalFadeIn 0.3s ease; }
        @keyframes modalFadeIn { from { opacity:0; transform:translateY(-20px); } to { opacity:1; transform:translateY(0); } }
        .modal-header { padding:20px 24px; background:#f8f9fa; border-bottom:1px solid #edf2f7; display:flex; justify-content:space-between; align-items:center; }
        .modal-header h3 { font-size:18px; color:#2c3e50; }
        .modal-close { background:transparent; border:none; font-size:18px; cursor:pointer; color:#64748b; }
        .modal-body { padding:24px; max-height:70vh; overflow-y:auto; }
        .modal-footer { padding:16px 24px; background:#f8f9fa; border-top:1px solid #edf2f7; display:flex; justify-content:flex-end; gap:12px; }
        .form-row { display:flex; gap:15px; margin-bottom:15px; }
        .form-row .form-group { flex:1; }
        .checkbox-group { display:flex; align-items:center; gap:10px; margin-top:5px; }
        .checkbox-group input[type="checkbox"] { width:18px; height:18px; accent-color:#3762c8; }
    </style>
</head>
<body>
<?php $sidebarBase = '../'; include '../includes/utilities_sidebar.php'; ?>
<main class="main-content">
    <div class="card">
        <div class="dashboard-header">
            <div>
                <h1><i class="fas fa-users-cog"></i> User Management</h1>
                <p style="color:#64748b; font-size:14px;">Manage all LGU user accounts and monitor website activities.</p>
            </div>
            <div>
                <button class="btn btn-primary" onclick="openAddModal()"><i class="fas fa-user-plus"></i> Add User</button>
                <a href="../utilities_dashboard.php" class="btn btn-outline"><i class="fas fa-chevron-left"></i> Dashboard</a>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
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
                            <th>Registered</th>
                            <th>Last Login</th>
                            <th>Activity Summary</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr><td colspan="9" style="text-align:center; padding:30px; color:#94a3b8;">No users found.</td></tr>
                        <?php else: foreach ($users as $u): $isSelf = ($u['id'] == $_SESSION['user_id']); ?>
                            <tr>
                                <td><strong><?php echo $u['id']; ?></strong></td>
                                <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                <td><span class="badge badge-<?php echo $u['user_type']; ?>"><?php echo ucfirst($u['user_type']); ?></span></td>
                                <td><span class="badge badge-<?php echo $u['is_active'] ? 'active' : 'inactive'; ?>"><?php echo $u['is_active'] ? 'Active' : 'Inactive'; ?></span><?php if ($isSelf) echo ' <span style="font-size:10px; color:#94a3b8;">(You)</span>'; ?></td>
                                <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                                <td><?php echo $u['last_login'] ? date('M d, Y h:i A', strtotime($u['last_login'])) : '<span style="color:#94a3b8;">Never</span>'; ?></td>
                                <td>
                                    <span class="badge" style="background:#e0e7ff; color:#4338ca;">
                                        <i class="fas fa-file-invoice"></i> <?php echo $u['service_requests_count']; ?> Request<?php echo $u['service_requests_count'] == 1 ? '' : 's'; ?>
                                    </span>
                                </td>
                                <td style="text-align:right; white-space:nowrap;">
                                    <button class="btn-icon btn-icon-activity" title="View User Activity & Actions" onclick="viewActivity(<?php echo htmlspecialchars(json_encode($u, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)); ?>)"><i class="fas fa-chart-line"></i></button>
                                    <button class="btn-icon btn-icon-edit" title="Edit User" onclick="editUser(<?php echo htmlspecialchars(json_encode($u, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)); ?>)"><i class="fas fa-edit"></i></button>
                                    <?php if (!$isSelf): ?>
                                        <button class="btn-icon btn-icon-toggle" title="Toggle Status" onclick="toggleUser(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['full_name'], ENT_QUOTES); ?>', <?php echo $u['is_active']; ?>)"><i class="fas <?php echo $u['is_active'] ? 'fa-pause' : 'fa-play'; ?>"></i></button>
                                        <button class="btn-icon btn-icon-delete" title="Delete User" onclick="deleteUser(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['full_name'], ENT_QUOTES); ?>')"><i class="fas fa-trash-alt"></i></button>
                                    <?php else: ?>
                                        <span style="font-size:11px; color:#94a3b8; margin-left:4px;">(Self)</span>
                                    <?php endif; ?>
                                </td>
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
                            <a href="users.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>" class="page-link <?php echo $page==$i?'active':''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- ADD MODAL -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3>Add User</h3><button class="modal-close" onclick="closeModal('addModal')">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group"><label>Full Name *</label><input type="text" name="full_name" class="form-control" required></div>
                <div class="form-group"><label>Email *</label><input type="email" name="email" class="form-control" required></div>
                <div class="form-group"><label>Password * (min 6)</label><input type="password" name="password" class="form-control" required minlength="6"></div>
                <div class="form-row">
                    <div class="form-group"><label>Role</label><select name="user_type" class="form-control"><option value="citizen">Citizen</option><option value="employee">Employee</option></select></div>
                    <div class="form-group"><label>&nbsp;</label><div class="checkbox-group"><input type="checkbox" name="is_active" checked><label>Active</label></div></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('addModal')">Cancel</button><button type="submit" class="btn btn-primary">Add</button></div>
        </form>
    </div>
</div>

<!-- ===== EDIT MODAL ===== -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit User</h3>
            <button type="button" class="modal-close" onclick="closeModal('editModal')">&times;</button>
        </div>
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <!-- Hidden action and ID -->
            <input type="hidden" name="action" value="edit">
            <input type="hidden" id="edit-id" name="id">
            <div class="modal-body">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" id="edit-full-name" name="full_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" id="edit-email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>New Password (optional)</label>
                    <input type="password" id="edit-password" name="new_password" class="form-control" placeholder="Leave blank to keep current">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Role</label>
                        <select id="edit-role" name="user_type" class="form-control">
                            <option value="citizen">Citizen</option>
                            <option value="employee">Employee</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <div class="checkbox-group">
                            <input type="checkbox" id="edit-is-active" name="is_active" value="1">
                            <label for="edit-is-active">Active</label>
                        </div>
                    </div>
                </div>

                <!-- USER WEBSITE ACTIVITY PREVIEW IN EDIT MODAL -->
                <div class="form-group" style="margin-top:10px;">
                    <label style="font-weight:600; color:#475569;"><i class="fas fa-history"></i> Website Activity Preview</label>
                    <div id="edit-activity-info" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px 14px; font-size:12px; color:#475569;">
                        Loading activity summary...
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ===== USER ACTIVITY MODAL ===== -->
<div id="activityModal" class="modal">
    <div class="modal-content" style="max-width:650px;">
        <div class="modal-header" style="background:#e0e7ff;">
            <h3 style="color:#3730a3; display:flex; align-items:center; gap:8px;">
                <i class="fas fa-user-clock"></i> User Website Actions & Activity
            </h3>
            <button type="button" class="modal-close" onclick="closeModal('activityModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:16px; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                <div>
                    <h4 id="act-name" style="font-size:16px; color:#1e293b; margin-bottom:4px;">User Name</h4>
                    <p id="act-email" style="font-size:13px; color:#64748b;">user@example.com</p>
                </div>
                <div style="display:flex; gap:8px;">
                    <span id="act-role-badge" class="badge">Citizen</span>
                    <span id="act-status-badge" class="badge">Active</span>
                </div>
            </div>

            <!-- STATS GRID -->
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap:12px; margin-bottom:20px;">
                <div style="background:#f1f5f9; padding:12px; border-radius:10px; text-align:center;">
                    <div style="font-size:20px; font-weight:700; color:#3762c8;" id="act-stat-requests">0</div>
                    <div style="font-size:11px; color:#64748b; font-weight:500;">Service Requests</div>
                </div>
                <div style="background:#f1f5f9; padding:12px; border-radius:10px; text-align:center;">
                    <div style="font-size:20px; font-weight:700; color:#0284c7;" id="act-stat-otps">0</div>
                    <div style="font-size:11px; color:#64748b; font-weight:500;">OTP Verifications</div>
                </div>
                <div style="background:#f1f5f9; padding:12px; border-radius:10px; text-align:center;">
                    <div style="font-size:12px; font-weight:600; color:#334155; margin-top:4px;" id="act-stat-login">Never</div>
                    <div style="font-size:11px; color:#64748b; font-weight:500;">Last Login</div>
                </div>
            </div>

            <!-- ACTIVITY TIMELINE / ACTION LIST -->
            <h4 style="font-size:14px; font-weight:600; color:#334155; margin-bottom:12px; display:flex; align-items:center; gap:8px;">
                <i class="fas fa-stream"></i> Recent Website Actions Log
            </h4>
            <div id="act-list-container" style="max-height:280px; overflow-y:auto; display:flex; flex-direction:column; gap:10px;">
                <!-- Dynamically filled -->
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('activityModal')">Close</button>
        </div>
    </div>
</div>

<!-- DELETE MODAL -->
<div id="deleteModal" class="modal">
    <div class="modal-content" style="max-width:450px;">
        <div class="modal-header" style="background:#fde8e8;"><h3 style="color:#bd2130;">Delete User</h3><button class="modal-close" onclick="closeModal('deleteModal')">&times;</button></div>
        <form method="POST"><input type="hidden" name="action" value="delete"><input type="hidden" id="delete-id" name="id">
            <div class="modal-body"><p>Delete user <strong id="delete-name"></strong>? This cannot be undone.</p></div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('deleteModal')">Cancel</button><button type="submit" class="btn btn-danger">Delete</button></div>
        </form>
    </div>
</div>

<!-- TOGGLE MODAL -->
<div id="toggleModal" class="modal">
    <div class="modal-content" style="max-width:450px;">
        <div class="modal-header" style="background:#e0f2fe;"><h3 style="color:#0284c7;">Toggle Status</h3><button class="modal-close" onclick="closeModal('toggleModal')">&times;</button></div>
        <form method="POST"><input type="hidden" name="action" value="toggle"><input type="hidden" id="toggle-id" name="id">
            <div class="modal-body"><p>Are you sure you want to <strong id="toggle-action-text"></strong> user <strong id="toggle-name"></strong>?</p></div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('toggleModal')">Cancel</button><button type="submit" class="btn btn-warning">Confirm</button></div>
        </form>
    </div>
</div>

<script>
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
}
function openAddModal() {
    document.getElementById('addModal').classList.add('open');
}

function editUser(u) {
    document.getElementById('edit-id').value = u.id;
    document.getElementById('edit-full-name').value = u.full_name;
    document.getElementById('edit-email').value = u.email;
    document.getElementById('edit-role').value = u.user_type;
    document.getElementById('edit-is-active').checked = (u.is_active == 1);
    document.getElementById('edit-password').value = '';
    
    // Update preview of user activity inside Edit Modal
    document.getElementById('edit-activity-info').innerHTML = `
        <strong>Role:</strong> ${u.user_type.toUpperCase()} &nbsp;|&nbsp; 
        <strong>Registered:</strong> ${u.created_at ? u.created_at.split(' ')[0] : 'N/A'} &nbsp;|&nbsp; 
        <strong>Last Login:</strong> ${u.last_login ? u.last_login : 'Never'}<br>
        <strong>Submitted Service Requests:</strong> ${u.service_requests_count || 0}
    `;

    document.getElementById('editModal').classList.add('open');
}

function viewActivity(u) {
    document.getElementById('act-name').textContent = u.full_name;
    document.getElementById('act-email').textContent = u.email;
    
    // Badges
    const roleBadge = document.getElementById('act-role-badge');
    roleBadge.textContent = u.user_type.charAt(0).toUpperCase() + u.user_type.slice(1);
    roleBadge.className = 'badge badge-' + u.user_type;
    
    const statusBadge = document.getElementById('act-status-badge');
    statusBadge.textContent = u.is_active == 1 ? 'Active' : 'Inactive';
    statusBadge.className = 'badge badge-' + (u.is_active == 1 ? 'active' : 'inactive');

    // Stats
    document.getElementById('act-stat-requests').textContent = u.service_requests_count || 0;
    document.getElementById('act-stat-otps').textContent = u.otp_count || 0;
    document.getElementById('act-stat-login').textContent = u.last_login ? u.last_login : 'Never';

    // List container
    const container = document.getElementById('act-list-container');
    container.innerHTML = '';

    let items = [];

    // Service requests
    if (u.service_requests && u.service_requests.length > 0) {
        u.service_requests.forEach(sr => {
            items.push({
                type: 'Service Request',
                icon: 'fa-file-invoice',
                color: '#3762c8',
                title: (sr.request_type ? sr.request_type.toUpperCase() : 'SERVICE') + ' - ' + (sr.utility_type ? sr.utility_type.toUpperCase() : 'UTILITY'),
                status: sr.status || 'pending',
                date: sr.created_at
            });
        });
    }

    // OTP activity
    if (u.otps && u.otps.length > 0) {
        u.otps.forEach(otp => {
            items.push({
                type: 'Security',
                icon: 'fa-key',
                color: '#eab308',
                title: 'OTP Verification Requested ' + (otp.used == 1 ? '(Verified)' : '(Pending/Expired)'),
                status: otp.used == 1 ? 'Verified' : 'Requested',
                date: otp.created_at
            });
        });
    }

    // Account creation
    if (u.created_at) {
        items.push({
            type: 'Account',
            icon: 'fa-user-plus',
            color: '#16a34a',
            title: 'Account Registered on Website',
            status: 'Created',
            date: u.created_at
        });
    }

    // Last login
    if (u.last_login) {
        items.push({
            type: 'Authentication',
            icon: 'fa-sign-in-alt',
            color: '#0284c7',
            title: 'User Logged In to Website',
            status: 'Success',
            date: u.last_login
        });
    }

    // Sort items by date descending
    items.sort((a, b) => new Date(b.date) - new Date(a.date));

    if (items.length === 0) {
        container.innerHTML = '<div style="text-align:center; padding:20px; color:#94a3b8; font-size:13px;">No actions logged for this user yet.</div>';
    } else {
        items.forEach(item => {
            const div = document.createElement('div');
            div.style.cssText = 'background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px 14px; display:flex; justify-content:space-between; align-items:center; gap:10px;';
            
            const left = document.createElement('div');
            left.style.cssText = 'display:flex; align-items:center; gap:10px;';
            left.innerHTML = `<div style="width:32px; height:32px; border-radius:50%; background:${item.color}15; color:${item.color}; display:flex; align-items:center; justify-content:center; font-size:13px;"><i class="fas ${item.icon}"></i></div>
                              <div>
                                <div style="font-weight:600; font-size:13px; color:#1e293b;">${item.title}</div>
                                <div style="font-size:11px; color:#64748b;">${new Date(item.date).toLocaleString()}</div>
                              </div>`;
            
            const right = document.createElement('div');
            right.innerHTML = `<span class="badge" style="background:#e2e8f0; color:#475569; font-size:10px;">${item.status}</span>`;
            
            div.appendChild(left);
            div.appendChild(right);
            container.appendChild(div);
        });
    }

    document.getElementById('activityModal').classList.add('open');
}

function deleteUser(id, name) {
    document.getElementById('delete-id').value = id;
    document.getElementById('delete-name').textContent = name;
    document.getElementById('deleteModal').classList.add('open');
}

function toggleUser(id, name, current) {
    document.getElementById('toggle-id').value = id;
    document.getElementById('toggle-name').textContent = name;
    document.getElementById('toggle-action-text').textContent = current ? 'deactivate' : 'activate';
    document.getElementById('toggleModal').classList.add('open');
}

// Close modals when clicking outside
document.querySelectorAll('.modal').forEach(m => {
    m.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('open');
        }
    });
});
</script>
</body>
</html>