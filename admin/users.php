<?php
// admin/users.php - User Management Page
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Redirect if not logged in or not employee
if (!isLoggedIn() || !isEmployee()) {
    header('Location: ../login.php');
    exit();
}

$error = '';
$success = '';
$search = trim($_GET['search'] ?? '');
$role_filter = trim($_GET['role'] ?? '');

// Handle POST actions (Add, Edit, Toggle, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ============================================================
    // ADD NEW USER
    // ============================================================
    if ($action === 'add') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';
        $user_type = $_POST['user_type'] ?? 'citizen';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($full_name) || empty($email) || empty($password)) {
            $_SESSION['flash_error'] = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $_SESSION['flash_error'] = 'Password must be at least 6 characters.';
        } else {
            try {
                // Check if email exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $_SESSION['flash_error'] = 'Email already registered.';
                } else {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        INSERT INTO users (full_name, email, password, user_type, is_active)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$full_name, $email, $hashed, $user_type, $is_active]);
                    $_SESSION['flash_success'] = "User '$full_name' added successfully.";
                }
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = 'Failed to add user: ' . $e->getMessage();
            }
        }
        header('Location: users.php' . ($search ? '?search=' . urlencode($search) : ''));
        exit();
    }
    
    // ============================================================
    // EDIT USER
    // ============================================================
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
                // Check if email exists for other users
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $id]);
                if ($stmt->fetch()) {
                    $_SESSION['flash_error'] = 'Email already used by another user.';
                } else {
                    // Build update query
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
                    $_SESSION['flash_success'] = "User updated successfully.";
                }
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = 'Failed to update user: ' . $e->getMessage();
            }
        }
        header('Location: users.php' . ($search ? '?search=' . urlencode($search) : ''));
        exit();
    }
    
    // ============================================================
    // TOGGLE USER STATUS (Active/Inactive)
    // ============================================================
    if ($action === 'toggle') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                // Don't allow disabling yourself
                if ($id == $_SESSION['user_id']) {
                    $_SESSION['flash_error'] = 'You cannot change your own status.';
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
                    $stmt->execute([$id]);
                    $_SESSION['flash_success'] = "User status toggled successfully.";
                }
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = 'Failed to toggle user status.';
            }
        }
        header('Location: users.php' . ($search ? '?search=' . urlencode($search) : ''));
        exit();
    }
    
    // ============================================================
    // DELETE USER
    // ============================================================
    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                // Don't allow deleting yourself
                if ($id == $_SESSION['user_id']) {
                    $_SESSION['flash_error'] = 'You cannot delete your own account.';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$id]);
                    $_SESSION['flash_success'] = "User deleted successfully.";
                }
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = 'Failed to delete user.';
            }
        }
        header('Location: users.php' . ($search ? '?search=' . urlencode($search) : ''));
        exit();
    }
}

// Get flash messages
$error = $_SESSION['flash_error'] ?? '';
$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

// ============================================================
// BUILD QUERY WITH FILTERS
// ============================================================
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(full_name LIKE ? OR email LIKE ?)";
    $searchWildcard = '%' . $search . '%';
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
}

if (!empty($role_filter) && in_array($role_filter, ['citizen', 'employee'])) {
    $conditions[] = "user_type = ?";
    $params[] = $role_filter;
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Pagination
$limit = 15;
$page = !empty($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Count total
$countQuery = "SELECT COUNT(*) FROM users $whereClause";
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Fetch users
$query = "
    SELECT id, full_name, email, user_type, is_active, created_at
    FROM users
    $whereClause
    ORDER BY id DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | LGU Portal</title>
    <link rel="icon" type="image/png" href="../assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Same styles as other admin pages -->
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
            background: url("../assets/images/cityhall.jpeg") center/cover no-repeat fixed;
            position: relative;
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
            flex-wrap: wrap;
            gap: 20px;
        }

        .dashboard-header h1 {
            color: #2c3e50;
            font-size: 32px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .dashboard-header h1 i {
            color: #3762c8;
        }

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

        .alert-error {
            background-color: #fde8e8;
            color: #c0392b;
            border: 1px solid #f8b4b4;
        }

        .alert-success {
            background-color: #e2fbe8;
            color: #1e7e34;
            border: 1px solid #b8f0c5;
        }

        .btn {
            padding: 10px 20px;
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

        .btn-primary {
            background: #3762c8;
            color: white;
        }
        .btn-primary:hover {
            background: #2851b0;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-success:hover {
            background: #218838;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        .btn-warning:hover {
            background: #e0a800;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #cbd5e1;
            color: #64748b;
        }
        .btn-outline:hover {
            background: #f8f9fa;
            color: #2c3e50;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        /* Filter Panel */
        .filter-panel {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex: 1;
            min-width: 160px;
        }

        .form-group label {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
        }

        .form-control {
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 14px;
            outline: none;
        }

        .form-control:focus {
            border-color: #3762c8;
        }

        /* Table Section */
        .table-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .table-container {
            overflow-x: auto;
            width: 100%;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th {
            background: #f8f9fa;
            color: #475569;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            padding: 12px 16px;
            border-bottom: 2px solid #e2e8f0;
        }

        td {
            padding: 14px 16px;
            border-bottom: 1px solid #edf2f7;
            font-size: 14px;
            color: #2c3e50;
        }

        tr:hover td {
            background: #fcfcfc;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 99px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-citizen {
            background: #e0f2fe;
            color: #0284c7;
        }
        .badge-employee {
            background: #dbeafe;
            color: #1e40af;
        }
        .badge-active {
            background: #e2fbe8;
            color: #1e7e34;
        }
        .badge-inactive {
            background: #fde8e8;
            color: #bd2130;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-icon-edit {
            background: #fef9e7;
            color: #d39e00;
        }
        .btn-icon-edit:hover {
            background: #fef3c7;
        }

        .btn-icon-toggle {
            background: #e0f2fe;
            color: #0284c7;
        }
        .btn-icon-toggle:hover {
            background: #bae6fd;
        }

        .btn-icon-delete {
            background: #fde8e8;
            color: #bd2130;
        }
        .btn-icon-delete:hover {
            background: #fecaca;
        }

        /* Pagination */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
        }

        .pagination-info {
            font-size: 13px;
            color: #64748b;
        }

        .pagination-links {
            display: flex;
            gap: 6px;
        }

        .page-link {
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            text-decoration: none;
            color: #64748b;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .page-link:hover {
            border-color: #3762c8;
            color: #3762c8;
            background: #f8fafc;
        }

        .page-link.active {
            background: #3762c8;
            color: white;
            border-color: #3762c8;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(4px);
        }

        .modal.open {
            display: flex;
        }

        .modal-content {
            background: white;
            width: 90%;
            max-width: 550px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            animation: modalFadeIn 0.3s ease;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-header {
            padding: 20px 24px;
            background: #f8f9fa;
            border-bottom: 1px solid #edf2f7;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            font-size: 18px;
            color: #2c3e50;
        }

        .modal-close {
            background: transparent;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: #64748b;
        }

        .modal-body {
            padding: 24px;
            max-height: 70vh;
            overflow-y: auto;
        }

        .modal-footer {
            padding: 16px 24px;
            background: #f8f9fa;
            border-top: 1px solid #edf2f7;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        @media (max-width: 600px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 5px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #3762c8;
        }
    </style>
</head>
<body>

<?php include '../includes/utilities_sidebar.php'; ?>

<main class="main-content" id="mainContent">
    <div class="card">
        
        <!-- Header -->
        <div class="dashboard-header">
            <div>
                <h1><i class="fas fa-users-cog"></i> User Management</h1>
                <p style="color: #64748b; font-size: 14px; margin-top: 5px;">Manage all LGU user accounts – employees and citizens.</p>
            </div>
            <div>
                <button class="btn btn-primary" onclick="openAddModal()"><i class="fas fa-user-plus"></i> Add User</button>
                <a href="../utilities_dashboard.php" class="btn btn-outline"><i class="fas fa-chevron-left"></i> Dashboard</a>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Filter Panel -->
        <form method="GET" class="filter-panel">
            <div class="form-group" style="flex: 2;">
                <label>Search Users</label>
                <input type="text" name="search" class="form-control" placeholder="Search by name or email..." value="<?php echo htmlspecialchars($search); ?>">
            </div>

            <div class="form-group">
                <label>Role</label>
                <select name="role" class="form-control">
                    <option value="">All Roles</option>
                    <option value="citizen" <?php echo $role_filter === 'citizen' ? 'selected' : ''; ?>>Citizen</option>
                    <option value="employee" <?php echo $role_filter === 'employee' ? 'selected' : ''; ?>>Employee</option>
                </select>
            </div>

            <div style="display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
                <a href="users.php" class="btn btn-outline">Reset</a>
            </div>
        </form>

        <!-- Users Table -->
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
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr><td colspan="7" style="text-align:center; padding:30px; color:#64748b;">No users found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <?php $isSelf = ($user['id'] == $_SESSION['user_id']); ?>
                                <tr>
                                    <td><strong><?php echo $user['id']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $user['user_type']; ?>">
                                            <?php echo ucfirst($user['user_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                        <?php if ($isSelf): ?>
                                            <span style="font-size:10px; color:#94a3b8; margin-left:5px;">(You)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td style="text-align:right; white-space:nowrap;">
                                        <button class="btn-icon btn-icon-edit" onclick="editUser(<?php echo json_encode($user); ?>)" title="Edit User"><i class="fas fa-edit"></i></button>
                                        <?php if (!$isSelf): ?>
                                            <button class="btn-icon btn-icon-toggle" onclick="toggleUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['full_name']); ?>', <?php echo $user['is_active']; ?>)" title="Toggle Status">
                                                <i class="fas <?php echo $user['is_active'] ? 'fa-pause' : 'fa-play'; ?>"></i>
                                            </button>
                                            <button class="btn-icon btn-icon-delete" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['full_name']); ?>')" title="Delete User"><i class="fas fa-trash-alt"></i></button>
                                        <?php else: ?>
                                            <span style="font-size:11px; color:#94a3b8;">Self</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination-container">
                <div class="pagination-info">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($totalRecords, $offset + $limit); ?> of <?php echo $totalRecords; ?> users
                </div>
                <div class="pagination-links">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="users.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>" class="page-link <?php echo $page == $i ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<!-- ============================================================ -->
<!-- ADD USER MODAL -->
<!-- ============================================================ -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus"></i> Add New User</h3>
            <button class="modal-close" onclick="closeModal('addModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" class="form-control" placeholder="Juan Dela Cruz" required>
                </div>

                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" class="form-control" placeholder="name@lgu.gov.ph" required>
                </div>

                <div class="form-group">
                    <label>Password * (min. 6 characters)</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••" required minlength="6">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Role *</label>
                        <select name="user_type" class="form-control" required>
                            <option value="citizen">Citizen</option>
                            <option value="employee">Employee</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <div class="checkbox-group">
                            <input type="checkbox" name="is_active" id="add-is-active" checked>
                            <label for="add-is-active" style="margin:0;">Active</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add User</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================ -->
<!-- EDIT USER MODAL -->
<!-- ============================================================ -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-user-edit"></i> Edit User</h3>
            <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" id="edit-id" name="id">
            <div class="modal-body">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" id="edit-full-name" name="full_name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" id="edit-email" name="email" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>New Password (leave blank to keep current)</label>
                    <input type="password" id="edit-password" name="new_password" class="form-control" placeholder="•••••• (optional)">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Role *</label>
                        <select id="edit-role" name="user_type" class="form-control" required>
                            <option value="citizen">Citizen</option>
                            <option value="employee">Employee</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <div class="checkbox-group">
                            <input type="checkbox" id="edit-is-active" name="is_active" value="1">
                            <label for="edit-is-active" style="margin:0;">Active</label>
                        </div>
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

<!-- ============================================================ -->
<!-- CONFIRM DELETE MODAL -->
<!-- ============================================================ -->
<div id="deleteModal" class="modal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header" style="background:#fde8e8;">
            <h3 style="color:#bd2130;"><i class="fas fa-exclamation-triangle"></i> Delete User</h3>
            <button class="modal-close" onclick="closeModal('deleteModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" id="delete-id" name="id">
            <div class="modal-body" style="padding:20px 24px;">
                <p>Are you sure you want to delete user <strong id="delete-name"></strong>?</p>
                <p style="font-size:13px; color:#64748b; margin-top:10px;">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('deleteModal')">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete User</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================ -->
<!-- CONFIRM TOGGLE MODAL -->
<!-- ============================================================ -->
<div id="toggleModal" class="modal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header" style="background:#e0f2fe;">
            <h3 style="color:#0284c7;"><i class="fas fa-exchange-alt"></i> Toggle User Status</h3>
            <button class="modal-close" onclick="closeModal('toggleModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" id="toggle-id" name="id">
            <div class="modal-body" style="padding:20px 24px;">
                <p>Are you sure you want to <strong id="toggle-action-text"></strong> user <strong id="toggle-name"></strong>?</p>
                <p style="font-size:13px; color:#64748b; margin-top:10px;">This will change their access to the system.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('toggleModal')">Cancel</button>
                <button type="submit" class="btn btn-warning">Confirm</button>
            </div>
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

    function editUser(user) {
        document.getElementById('edit-id').value = user.id;
        document.getElementById('edit-full-name').value = user.full_name;
        document.getElementById('edit-email').value = user.email;
        document.getElementById('edit-role').value = user.user_type;
        document.getElementById('edit-is-active').checked = user.is_active == 1;
        document.getElementById('edit-password').value = '';
        document.getElementById('editModal').classList.add('open');
    }

    function deleteUser(id, name) {
        document.getElementById('delete-id').value = id;
        document.getElementById('delete-name').textContent = name;
        document.getElementById('deleteModal').classList.add('open');
    }

    function toggleUser(id, name, currentStatus) {
        document.getElementById('toggle-id').value = id;
        document.getElementById('toggle-name').textContent = name;
        const action = currentStatus ? 'deactivate' : 'activate';
        document.getElementById('toggle-action-text').textContent = action;
        document.getElementById('toggleModal').classList.add('open');
    }

    // Close modals when clicking outside
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('open');
            }
        });
    });
</script>

</body>
</html>