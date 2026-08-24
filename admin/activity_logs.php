<?php
// admin/activity_logs.php - User Activity Logs
require_once '../includes/auth.php';
require_once '../includes/db.php';

if (!isLoggedIn() || !isEmployee()) {
    header('Location: ../login.php');
    exit();
}

$role_filter = trim($_GET['role'] ?? '');
$search = trim($_GET['search'] ?? '');

$params = [];
$searchCondition = "";

if ($search) {
    $searchCondition = " AND (u.full_name LIKE ? OR log_details LIKE ?)";
    $searchWildcard = '%' . $search . '%';
    // Parameters will be added to each part of the UNION query below
}

// Build the mega UNION query for all activities
// We map columns to: log_date, user_id, full_name, user_type, activity_type, log_details

$queries = [];

// 1. Service Requests (Citizens)
$q1 = "SELECT sr.created_at as log_date, u.id as user_id, CAST(u.full_name AS CHAR) as full_name, CAST(u.user_type AS CHAR) as user_type, CAST('Service Request Submitted' AS CHAR) as activity_type, CAST(CONCAT('Submitted a ', sr.utility_type, ' request (Status: ', sr.status, ')') AS CHAR) as log_details
FROM service_requests sr
JOIN users u ON sr.user_id = u.id
WHERE 1=1";
if ($role_filter) { $q1 .= " AND u.user_type = '$role_filter'"; }
if ($search) { $q1 .= " AND (u.full_name LIKE ? OR CONCAT('Submitted a ', sr.utility_type, ' request (Status: ', sr.status, ')') LIKE ?)"; $params[] = $searchWildcard; $params[] = $searchWildcard; }
$queries[] = $q1;

// 2. OTP Verifications (Citizens)
$q2 = "SELECT o.created_at as log_date, u.id as user_id, CAST(u.full_name AS CHAR) as full_name, CAST(u.user_type AS CHAR) as user_type, CAST('OTP Generated' AS CHAR) as activity_type, CAST(CONCAT('Generated OTP (Used: ', IF(o.used=1, 'Yes', 'No'), ')') AS CHAR) as log_details
FROM otps o
JOIN users u ON o.user_id = u.id
WHERE 1=1";
if ($role_filter) { $q2 .= " AND u.user_type = '$role_filter'"; }
if ($search) { $q2 .= " AND (u.full_name LIKE ? OR CONCAT('Generated OTP (Used: ', IF(o.used=1, 'Yes', 'No'), ')') LIKE ?)"; $params[] = $searchWildcard; $params[] = $searchWildcard; }
$queries[] = $q2;

// 3. User Registrations (Both)
$q3 = "SELECT u.created_at as log_date, u.id as user_id, CAST(u.full_name AS CHAR) as full_name, CAST(u.user_type AS CHAR) as user_type, CAST('Account Registered' AS CHAR) as activity_type, CAST('User account was created in the system.' AS CHAR) as log_details
FROM users u
WHERE 1=1";
if ($role_filter) { $q3 .= " AND u.user_type = '$role_filter'"; }
if ($search) { $q3 .= " AND (u.full_name LIKE ? OR 'User account was created in the system.' LIKE ?)"; $params[] = $searchWildcard; $params[] = $searchWildcard; }
$queries[] = $q3;

// 4. Asset Status Changes (Employees)
$q4 = "SELECT asl.changed_at as log_date, u.id as user_id, CAST(u.full_name AS CHAR) as full_name, CAST(u.user_type AS CHAR) as user_type, CAST('Asset Status Changed' AS CHAR) as activity_type, CAST(CONCAT('Changed asset ', asl.utility_asset_id, ' from ', COALESCE(asl.old_status, 'None'), ' to ', asl.new_status) AS CHAR) as log_details
FROM asset_status_logs asl
JOIN users u ON asl.changed_by = u.id
WHERE 1=1";
if ($role_filter) { $q4 .= " AND u.user_type = '$role_filter'"; }
if ($search) { $q4 .= " AND (u.full_name LIKE ? OR CONCAT('Changed asset ', asl.utility_asset_id, ' from ', COALESCE(asl.old_status, 'None'), ' to ', asl.new_status) LIKE ?)"; $params[] = $searchWildcard; $params[] = $searchWildcard; }
$queries[] = $q4;

// Combine queries
$unionQuery = implode(" UNION ALL ", $queries);

// Pagination
$limit = 20;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// Total Count
$countSql = "SELECT COUNT(*) FROM ($unionQuery) as t";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / $limit);

// Fetch Data
$dataSql = "SELECT * FROM ($unionQuery) as t ORDER BY log_date DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($dataSql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs</title>
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
        .pagination-container { display:flex; justify-content:space-between; align-items:center; margin-top:20px; }
        .pagination-info { font-size:13px; color:#64748b; }
        .pagination-links { display:flex; gap:6px; }
        .page-link { padding:6px 12px; border-radius:6px; border:1px solid #cbd5e1; text-decoration:none; color:#64748b; font-size:13px; font-weight:500; }
        .page-link:hover { border-color:#3762c8; color:#3762c8; background:#f8fafc; }
        .page-link.active { background:#3762c8; color:#fff; border-color:#3762c8; }
    </style>
</head>
<body>
<?php $sidebarBase = '../'; include '../includes/utilities_sidebar.php'; ?>
<main class="main-content">
    <div class="card">
        <div class="dashboard-header">
            <div>
                <h1><i class="fas fa-scroll"></i> Activity Logs</h1>
                <p style="color:#64748b; font-size:14px;">Detailed history of all actions performed by users in the system.</p>
            </div>
            <div>
                <a href="users.php" class="btn btn-outline"><i class="fas fa-chevron-left"></i> Back to Users</a>
            </div>
        </div>

        <form method="GET" class="filter-panel">
            <div class="form-group" style="flex:2;">
                <label>Search Details or Name</label>
                <input type="text" name="search" class="form-control" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
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
                <a href="activity_logs.php" class="btn btn-outline">Reset</a>
            </div>
        </form>

        <div class="table-section">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>User Name</th>
                            <th>Role</th>
                            <th>Activity Type</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="5" style="text-align:center; padding:30px; color:#94a3b8;">No activities found.</td></tr>
                        <?php else: foreach ($logs as $log): ?>
                            <tr>
                                <td><span style="font-size:13px; font-family:monospace; color:#475569;"><?php echo date('M d, Y h:i A', strtotime($log['log_date'])); ?></span></td>
                                <td><strong><?php echo htmlspecialchars($log['full_name']); ?></strong></td>
                                <td><span class="badge badge-<?php echo $log['user_type']; ?>"><?php echo ucfirst($log['user_type']); ?></span></td>
                                <td>
                                    <span style="font-weight:600; color:#0369a1;"><?php echo htmlspecialchars($log['activity_type']); ?></span>
                                </td>
                                <td><span style="color:#334155; font-size:13px;"><?php echo htmlspecialchars($log['log_details']); ?></span></td>
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
                            <a href="activity_logs.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>" class="page-link <?php echo $page==$i?'active':''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>
</body>
</html>
