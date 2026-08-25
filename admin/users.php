<?php
// admin/users.php - User Management
require_once '../includes/auth.php';
require_once '../includes/db.php';

if (!isLoggedIn() || !isEmployee()) {
    header('Location: ../login.php');
    exit();
}

$error = $success = '';
$search = trim($_GET['search'] ?? '');
$role_filter = trim($_GET['role'] ?? '');

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
        .btn { padding:10px 20px; border-radius:8px; font-weight:600; font-size:14px; border:none; cursor:pointer; display:inline-flex; align-items:center; gap:8px; text-decoration:none; }
        .btn-primary { background:#3762c8; color:#fff; }
        .btn-primary:hover { background:#2851b0; }
        .btn-outline { background:transparent; border:1px solid #cbd5e1; color:#64748b; }
        .btn-outline:hover { background:#f8f9fa; }
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
        .pagination-container { display:flex; justify-content:space-between; align-items:center; margin-top:20px; }
        .pagination-info { font-size:13px; color:#64748b; }
        .pagination-links { display:flex; gap:6px; }
        .page-link { padding:6px 12px; border-radius:6px; border:1px solid #cbd5e1; text-decoration:none; color:#64748b; font-size:13px; font-weight:500; }
        .page-link:hover { border-color:#3762c8; color:#3762c8; background:#f8fafc; }
        .page-link.active { background:#3762c8; color:#fff; border-color:#3762c8; }

        /* ===== DARK THEME OVERRIDES ===== */
        .dark-theme .card {
            background: rgba(30, 41, 59, 0.9) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: #f8fafc !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5) !important;
        }
        .dark-theme .dashboard-header h1 {
            color: #f8fafc !important;
        }
        .dark-theme .filter-panel {
            background: #1e293b !important;
            border: 1px solid #334155 !important;
            box-shadow: none !important;
        }
        .dark-theme .table-section {
            background: #1e293b !important;
            border: 1px solid #334155 !important;
            box-shadow: none !important;
        }
        .dark-theme .form-control {
            background: #0f172a !important;
            color: #f8fafc !important;
            border-color: #334155 !important;
        }
        .dark-theme .form-control:focus {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25) !important;
        }
        .dark-theme .form-group label {
            color: #94a3b8 !important;
        }
        .dark-theme th {
            background: #0f172a !important;
            color: #94a3b8 !important;
            border-bottom-color: #334155 !important;
        }
        .dark-theme td {
            border-bottom-color: #334155 !important;
            color: #cbd5e1 !important;
        }
        .dark-theme tr:hover td {
            background: rgba(255, 255, 255, 0.04) !important;
        }
        .dark-theme .badge-citizen {
            background: rgba(14, 165, 233, 0.2) !important;
            color: #38bdf8 !important;
            border: 1px solid rgba(14, 165, 233, 0.4) !important;
        }
        .dark-theme .badge-employee {
            background: rgba(99, 102, 241, 0.2) !important;
            color: #a5b4fc !important;
            border: 1px solid rgba(99, 102, 241, 0.4) !important;
        }
        .dark-theme .badge-active {
            background: rgba(16, 185, 129, 0.2) !important;
            color: #34d399 !important;
            border: 1px solid rgba(16, 185, 129, 0.4) !important;
        }
        .dark-theme .badge-inactive {
            background: rgba(239, 68, 68, 0.2) !important;
            color: #f87171 !important;
            border: 1px solid rgba(239, 68, 68, 0.4) !important;
        }
        .dark-theme .btn-outline {
            color: #cbd5e1 !important;
            border-color: #475569 !important;
            background: transparent !important;
        }
        .dark-theme .btn-outline:hover {
            background: #334155 !important;
            color: #ffffff !important;
        }
    </style>
</head>
<body>
<?php $sidebarBase = '../'; include '../includes/utilities_sidebar.php'; ?>
<main class="main-content">
    <div class="card">
        <div class="dashboard-header">
            <div>
                <h1><i class="fas fa-users-cog"></i> User Management</h1>
                <p style="color:#64748b; font-size:14px;">Monitor LGU user accounts and their activities on the website.</p>
            </div>
            <div class="header-action-group" style="display:flex; gap:10px;">
                <a href="activity_logs.php" class="btn btn-outline"><i class="fas fa-scroll"></i> Activity Log</a>
                <a href="../utilities_dashboard.php" class="btn btn-outline"><i class="fas fa-chevron-left"></i> Dashboard</a>
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr><td colspan="7" style="text-align:center; padding:30px; color:#94a3b8;">No users found.</td></tr>
                        <?php else: foreach ($users as $u): $isSelf = ($u['id'] == $_SESSION['user_id']); ?>
                            <tr>
                                <td><strong><?php echo $u['id']; ?></strong></td>
                                <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                <td><span class="badge badge-<?php echo $u['user_type']; ?>"><?php echo ucfirst($u['user_type']); ?></span></td>
                                <td><span class="badge badge-<?php echo $u['is_active'] ? 'active' : 'inactive'; ?>"><?php echo $u['is_active'] ? 'Active' : 'Inactive'; ?></span><?php if ($isSelf) echo ' <span style="font-size:10px; color:#94a3b8;">(You)</span>'; ?></td>
                                <td><?php echo date('M d, Y h:i:s A', strtotime($u['created_at'])); ?></td>
                                <td><?php echo $u['last_login'] ? date('M d, Y h:i:s A', strtotime($u['last_login'])) : '<span style="color:#94a3b8;">Never</span>'; ?></td>
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
</body>
</html>