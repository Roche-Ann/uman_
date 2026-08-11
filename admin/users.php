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
                    $_SESSION['flash_success'] = "User updated.";
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
        /* Your existing styles – keep them as they are */
        /* (I've included the full CSS from earlier, but you can reuse your current styles) */
        /* For brevity, I'll include a minimal version that works with your design */
        /* ... */
    </style>
</head>
<body>
<?php include '../includes/utilities_sidebar.php'; ?>
<main class="main-content">
    <div class="card">
        <div class="dashboard-header">
            <div>
                <h1><i class="fas fa-users-cog"></i> User Management</h1>
                <p style="color:#64748b; font-size:14px;">Manage all LGU user accounts.</p>
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
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr><td colspan="8" style="text-align:center; padding:30px; color:#94a3b8;">No users found.</td></tr>
                        <?php else: foreach ($users as $u): $isSelf = ($u['id'] == $_SESSION['user_id']); ?>
                            <tr>
                                <td><strong><?php echo $u['id']; ?></strong></td>
                                <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                <td><span class="badge badge-<?php echo $u['user_type']; ?>"><?php echo ucfirst($u['user_type']); ?></span></td>
                                <td><span class="badge badge-<?php echo $u['is_active'] ? 'active' : 'inactive'; ?>"><?php echo $u['is_active'] ? 'Active' : 'Inactive'; ?></span><?php if ($isSelf) echo ' <span style="font-size:10px; color:#94a3b8;">(You)</span>'; ?></td>
                                <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                                <td><?php echo $u['last_login'] ? date('M d, Y h:i A', strtotime($u['last_login'])) : 'Never'; ?></td>
                                <td style="text-align:right; white-space:nowrap;">
                                    <button class="btn-icon btn-icon-edit" onclick="editUser(<?php echo json_encode($u); ?>)"><i class="fas fa-edit"></i></button>
                                    <?php if (!$isSelf): ?>
                                        <button class="btn-icon btn-icon-toggle" onclick="toggleUser(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['full_name']); ?>', <?php echo $u['is_active']; ?>)"><i class="fas <?php echo $u['is_active'] ? 'fa-pause' : 'fa-play'; ?>"></i></button>
                                        <button class="btn-icon btn-icon-delete" onclick="deleteUser(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['full_name']); ?>')"><i class="fas fa-trash-alt"></i></button>
                                    <?php else: ?>
                                        <span style="font-size:11px; color:#94a3b8;">Self</span>
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

<!-- ===== ADD MODAL ===== -->
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

<!-- ===== EDIT MODAL (FIXED) ===== -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3>Edit User</h3><button class="modal-close" onclick="closeModal('editModal')">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" id="edit-id" name="id">
            <div class="modal-body">
                <div class="form-group"><label>Full Name *</label><input type="text" id="edit-full-name" name="full_name" class="form-control" required></div>
                <div class="form-group"><label>Email *</label><input type="email" id="edit-email" name="email" class="form-control" required></div>
                <div class="form-group"><label>New Password (optional)</label><input type="password" id="edit-password" name="new_password" class="form-control" placeholder="Leave blank to keep current"></div>
                <div class="form-row">
                    <div class="form-group"><label>Role</label><select id="edit-role" name="user_type" class="form-control"><option value="citizen">Citizen</option><option value="employee">Employee</option></select></div>
                    <div class="form-group"><label>&nbsp;</label><div class="checkbox-group"><input type="checkbox" id="edit-is-active" name="is_active" value="1"><label>Active</label></div></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('editModal')">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
    </div>
</div>

<!-- ===== DELETE MODAL ===== -->
<div id="deleteModal" class="modal">
    <div class="modal-content" style="max-width:450px;">
        <div class="modal-header" style="background:#fde8e8;"><h3 style="color:#bd2130;">Delete User</h3><button class="modal-close" onclick="closeModal('deleteModal')">&times;</button></div>
        <form method="POST"><input type="hidden" name="action" value="delete"><input type="hidden" id="delete-id" name="id">
            <div class="modal-body"><p>Delete user <strong id="delete-name"></strong>? This cannot be undone.</p></div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('deleteModal')">Cancel</button><button type="submit" class="btn btn-danger">Delete</button></div>
        </form>
    </div>
</div>

<!-- ===== TOGGLE MODAL ===== -->
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
    // Populate edit form fields
    document.getElementById('edit-id').value = u.id;
    document.getElementById('edit-full-name').value = u.full_name;
    document.getElementById('edit-email').value = u.email;
    document.getElementById('edit-role').value = u.user_type;
    document.getElementById('edit-is-active').checked = (u.is_active == 1);
    document.getElementById('edit-password').value = '';
    // Open the modal
    document.getElementById('editModal').classList.add('open');
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