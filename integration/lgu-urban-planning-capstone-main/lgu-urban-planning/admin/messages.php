<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helper.php';

$auth = new Auth();
$auth->requireRole(['admin', 'super_admin', 'zoning_officer', 'building_official', 'assessor', 'inspector']);

$db  = Database::getInstance();
$pdo = $db->getConnection();

// ── AJAX: Deletion approve/reject (called by fetch() from JS) ─────────────────
if (isset($_POST['ajax_deletion_action'])) {
    header('Content-Type: application/json');
    $action       = $_POST['ajax_deletion_action'];
    $targetUserId = (int)($_POST['target_user_id'] ?? 0);
    $msgId        = (int)($_POST['msg_id']         ?? 0);
    $rejectReason = trim($_POST['reject_reason']   ?? '');

    if (!in_array($action, ['approve', 'reject'], true) || $targetUserId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Invalid request.']);
        exit;
    }

    try {
        if ($action === 'approve') {
            $pdo->prepare("UPDATE users SET status = 'deleted', deleted_at = NOW() WHERE id = ?")
                ->execute([$targetUserId]);
            $pdo->prepare("UPDATE user_preferences SET pref_value = '2'
                           WHERE user_id = ? AND pref_key = 'account_deletion_requested'")
                ->execute([$targetUserId]);
            $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address, created_at)
                           VALUES (?, 'Account Deletion Approved', ?, ?, NOW())")
                ->execute([$targetUserId, 'Approved by admin ID: ' . $_SESSION['user_id'], $_SERVER['REMOTE_ADDR'] ?? '']);
            $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, subject, message, is_read, created_at)
                           VALUES (?, ?, 'Account Deletion \xe2\x80\x93 Approved',
                           'Your account deletion request has been approved. Your account has been deactivated. If this was a mistake, please contact the office.', 0, NOW())")
                ->execute([$_SESSION['user_id'], $targetUserId]);
        } else {
            // Build the rejection message body, embedding admin's reason if provided
            $msgBody  = "Dear Applicant,\n\n";
            $msgBody .= "We appreciate you reaching out to the LGU Urban Planning and Development Office. ";
            $msgBody .= "After careful review, we regret to inform you that your account deletion request has not been approved at this time.\n\n";
            if ($rejectReason !== '') {
                $msgBody .= "Reason for non-approval:\n" . $rejectReason . "\n\n";
            }
            $msgBody .= "If you believe this decision was made in error, or if you have any concerns regarding your account, ";
            $msgBody .= "we encourage you to visit our office during business hours or reach out through the official contact channels for further assistance.\n\n";
            $msgBody .= "We value your continued engagement with our portal and remain committed to serving you.\n\n";
            $msgBody .= "Respectfully,\n";
            $msgBody .= "LGU Urban Planning and Development Office";

            $pdo->prepare("INSERT INTO user_preferences (user_id, pref_key, pref_value)
                           VALUES (?, 'account_deletion_requested', '0')
                           ON DUPLICATE KEY UPDATE pref_value = '0'")
                ->execute([$targetUserId]);
            $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address, created_at)
                           VALUES (?, 'Account Deletion Rejected', ?, ?, NOW())")
                ->execute([$targetUserId,
                           'Rejected by admin ID: ' . $_SESSION['user_id'] . ($rejectReason ? ' | Reason: ' . $rejectReason : ''),
                           $_SERVER['REMOTE_ADDR'] ?? '']);
            $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, subject, message, is_read, created_at)
                           VALUES (?, ?, 'Account Deletion \xe2\x80\x93 Rejected', ?, 0, NOW())")
                ->execute([$_SESSION['user_id'], $targetUserId, $msgBody]);
        }

        // Mark original message as read
        if ($msgId) {
            $db->query("UPDATE messages SET is_read = 1 WHERE id = ? AND receiver_id = ?",
                       [$msgId, $_SESSION['user_id']]);
        }

        echo json_encode(['ok' => true, 'action' => $action]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => 'Database error. Please try again.']);
    }
    exit;
}

// ── AJAX: Live search (returns JSON of all matching messages) ─────────────────
if (isset($_GET['ajax_search'])) {
    header('Content-Type: application/json');
    $q      = trim($_GET['q'] ?? '');
    $filter = $_GET['filter'] ?? 'all';
    $isSA   = ($_SESSION['role'] ?? '') === 'super_admin';

    if ($isSA) {
        $wc = ['1=1'];
        $pr = [];
    } else {
        $wc = ['m.receiver_id = ?'];
        $pr = [$_SESSION['user_id']];
    }

    if ($filter === 'unread')       { $wc[] = 'm.is_read = 0'; }
    elseif ($filter === 'read')     { $wc[] = 'm.is_read = 1'; }
    elseif ($filter === 'deletion') { $wc[] = "m.subject LIKE '%Account Deletion Request%'"; }
    elseif ($filter === 'sent')     { $wc = ['m.sender_id = ?']; $pr = [$_SESSION['user_id']]; }

    if ($q !== '') {
        $like = "%{$q}%";
        $wc[] = "(m.subject LIKE ? OR m.message LIKE ? OR us.first_name LIKE ? OR us.last_name LIKE ?)";
        array_push($pr, $like, $like, $like, $like);
    }

    $sql = "SELECT m.id, m.subject, m.message, m.is_read, m.created_at,
                   us.first_name AS sender_first_name, us.last_name AS sender_last_name,
                   us.id AS sender_user_id,
                   ur.first_name AS receiver_first_name, ur.last_name AS receiver_last_name,
                   a.application_number
            FROM messages m
            LEFT JOIN users us ON m.sender_id = us.id
            LEFT JOIN users ur ON m.receiver_id = ur.id
            LEFT JOIN applications a ON m.application_id = a.id
            WHERE " . implode(' AND ', $wc) . "
            ORDER BY m.created_at DESC
            LIMIT 200";

    $rows = $db->fetchAll($sql, $pr);
    $results = [];
    foreach ($rows as $row) {
        $senderName = trim(($row['sender_first_name'] ?? '') . ' ' . ($row['sender_last_name'] ?? '')) ?: 'System';
        $isDeletion = stripos($row['subject'] ?? '', 'Account Deletion Request') !== false;
        $prefVal    = null;
        if ($isDeletion && !empty($row['sender_user_id'])) {
            $pref    = $db->fetchOne("SELECT pref_value FROM user_preferences WHERE user_id = ? AND pref_key = 'account_deletion_requested'", [(int)$row['sender_user_id']]);
            $prefVal = $pref['pref_value'] ?? '1';
        }
        $results[] = [
            'id'         => $row['id'],
            'sender'     => $senderName,
            'subject'    => $row['subject'] ?? '',
            'message'    => $row['message'],
            'preview'    => mb_strimwidth(strip_tags($row['message']), 0, 120, '…'),
            'date'       => Helper::formatDateTime($row['created_at']),
            'appNumber'  => $row['application_number'] ?? '',
            'isRead'     => (bool)$row['is_read'],
            'isDeletion' => $isDeletion,
            'targetUid'  => (int)($row['sender_user_id'] ?? 0),
            'prefVal'    => $prefVal,
            'isAdmin'    => in_array($_SESSION['role'] ?? '', ['admin', 'super_admin']),
            'filter'     => $filter,
            'q'          => $q,
            'page'       => 1,
        ];
    }
    echo json_encode(['ok' => true, 'results' => $results, 'total' => count($results)]);
    exit;
}

// ── Mark as read ──────────────────────────────────────────────────────────────
if (isset($_GET['mark_read'])) {
    $_saEarly = ($_SESSION['role'] ?? '') === 'super_admin';
    if ($_saEarly) {
        $db->query("UPDATE messages SET is_read = 1 WHERE id = ?", [(int)$_GET['mark_read']]);
    } else {
        $db->query("UPDATE messages SET is_read = 1 WHERE id = ? AND receiver_id = ?",
                   [(int)$_GET['mark_read'], $_SESSION['user_id']]);
    }
    $qs = http_build_query(array_filter(['filter' => $_GET['filter'] ?? 'all', 'q' => $_GET['q'] ?? '', 'page' => $_GET['page'] ?? 1]));
    header('Location: /lgu-urban-planning/admin/messages.php?' . $qs);
    exit;
}

// ── Delete message ────────────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $_saEarly = ($_SESSION['role'] ?? '') === 'super_admin';
    if ($_saEarly) {
        $db->query("DELETE FROM messages WHERE id = ?", [(int)$_GET['delete']]);
    } else {
        $db->query("DELETE FROM messages WHERE id = ? AND receiver_id = ?",
                   [(int)$_GET['delete'], $_SESSION['user_id']]);
    }
    $qs = http_build_query(array_filter(['filter' => $_GET['filter'] ?? 'all', 'q' => $_GET['q'] ?? '', 'page' => $_GET['page'] ?? 1, 'deleted' => 1]));
    header('Location: /lgu-urban-planning/admin/messages.php?' . $qs);
    exit;
}

// ── Send message ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $receiverId = (int)($_POST['receiver_id'] ?? 0);
    $subject    = trim($_POST['subject']    ?? '');
    $message    = trim($_POST['message']    ?? '');

    if ($receiverId > 0 && $message !== '') {
        $db->query(
            "INSERT INTO messages (sender_id, receiver_id, subject, message, message_type, is_read, created_at)
             VALUES (?, ?, ?, ?, 'message', 0, NOW())",
            [$_SESSION['user_id'], $receiverId, $subject ?: null, $message]
        );
    }
    header('Location: /lgu-urban-planning/admin/messages.php?filter=' . urlencode($filterType ?? 'all') . '&sent=1');
    exit;
}

$filterType  = $_GET['filter'] ?? 'all';
$searchQuery = trim($_GET['q'] ?? '');

// ── Super Admin sees ALL messages; other roles see only their own inbox ────────
$isSuperAdmin = ($_SESSION['role'] ?? '') === 'super_admin';

if ($isSuperAdmin) {
    $whereClauses = ['1=1'];
    $params       = [];
} else {
    $whereClauses = ['m.receiver_id = ?'];
    $params       = [$_SESSION['user_id']];
}

if ($filterType === 'unread') {
    $whereClauses[] = 'm.is_read = 0';
} elseif ($filterType === 'read') {
    $whereClauses[] = 'm.is_read = 1';
} elseif ($filterType === 'deletion') {
    $whereClauses[] = "m.subject LIKE '%Account Deletion Request%'";
}

if ($searchQuery !== '') {
    $whereClauses[] = "(m.subject LIKE ? OR m.message LIKE ? OR us.first_name LIKE ? OR us.last_name LIKE ?)";
    $like = "%{$searchQuery}%";
    array_push($params, $like, $like, $like, $like);
}

$whereSQL = implode(' AND ', $whereClauses);

// ── Pagination ────────────────────────────────────────────────────────────────
$limit      = 5;
$page       = max(1, (int)($_GET['page'] ?? 1));
$offset     = ($page - 1) * $limit;

// Total count (needs same JOIN as main query for search on sender name to work)
$totalCount = (int)($db->fetchOne(
    "SELECT COUNT(*) AS c
     FROM messages m
     LEFT JOIN users us ON m.sender_id = us.id
     WHERE {$whereSQL}",
    $params
)['c'] ?? 0);

$totalPages = max(1, (int)ceil($totalCount / $limit));
$page       = min($page, $totalPages); // clamp in case filter reduces pages

// Counts for badges
if ($isSuperAdmin) {
    $unreadCount = (int)($db->fetchOne(
        "SELECT COUNT(*) AS c FROM messages WHERE is_read = 0"
    )['c'] ?? 0);
    $deletionCount = (int)($db->fetchOne(
        "SELECT COUNT(*) AS c FROM messages WHERE is_read = 0 AND subject LIKE '%Account Deletion Request%'"
    )['c'] ?? 0);
} else {
    $unreadCount = (int)($db->fetchOne(
        "SELECT COUNT(*) AS c FROM messages WHERE receiver_id = ? AND is_read = 0",
        [$_SESSION['user_id']]
    )['c'] ?? 0);
    $deletionCount = (int)($db->fetchOne(
        "SELECT COUNT(*) AS c FROM messages WHERE receiver_id = ? AND is_read = 0 AND subject LIKE '%Account Deletion Request%'",
        [$_SESSION['user_id']]
    )['c'] ?? 0);
}

// ── Fetch messages ────────────────────────────────────────────────────────────
$messages = $db->fetchAll(
    "SELECT m.*,
            us.first_name AS sender_first_name, us.last_name AS sender_last_name,
            us.role AS sender_role, us.id AS sender_user_id,
            ur.first_name AS receiver_first_name, ur.last_name AS receiver_last_name,
            a.application_number
     FROM messages m
     LEFT JOIN users us ON m.sender_id = us.id
     LEFT JOIN users ur ON m.receiver_id = ur.id
     LEFT JOIN applications a ON m.application_id = a.id
     WHERE {$whereSQL}
     ORDER BY m.created_at DESC
     LIMIT {$limit} OFFSET {$offset}",
    $params
);

// Pre-fetch deletion pref statuses
$deletionStatuses = [];
foreach ($messages as $msg) {
    if (stripos($msg['subject'] ?? '', 'Account Deletion Request') !== false && !empty($msg['sender_user_id'])) {
        $uid = (int)$msg['sender_user_id'];
        if (!isset($deletionStatuses[$uid])) {
            $row = $db->fetchOne(
                "SELECT pref_value FROM user_preferences WHERE user_id = ? AND pref_key = 'account_deletion_requested'",
                [$uid]
            );
            $deletionStatuses[$uid] = $row['pref_value'] ?? '1';
        }
    }
}

$pageTitle = 'Messages';
$isAuthPage = true;
include __DIR__ . '/../admin/header.php';
?>

<style>
/* ── Message cards (user-style) ── */
.msg-row {
    margin-bottom: 0.875rem;
    padding: 0.875rem;
    border-radius: 0.5rem;
    cursor: pointer;
    transition: box-shadow 0.15s ease, transform 0.1s ease;
    user-select: none;
    overflow: hidden;
}
.msg-row:hover {
    box-shadow: 0 4px 14px rgba(13, 110, 253, 0.12) !important;
    transform: translateY(-1px);
}
.msg-row:active { transform: translateY(0); }

/* Unread = blue left border + light tinted bg; read = grey border */
.msg-row.unread {
    background: #f8f9ff;
    border: 1px solid #dee2e6;
    border-left: 5px solid #0d6efd !important;
}
.msg-row.read {
    background: #fff;
    border: 1px solid #dee2e6;
    border-left: 5px solid #dee2e6 !important;
}
.msg-row.deletion-unread {
    background: #fff8f8;
    border: 1px solid #fecaca;
    border-left: 5px solid #dc3545 !important;
}

/* Meta row: sender + badge on left, date on right */
.msg-meta {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.5rem;
    flex-wrap: wrap;
    width: 100%;
}
.msg-meta small {
    font-size: 0.78rem;
    white-space: nowrap;
    flex-shrink: 0;
}

/* Sender name */
.msg-sender {
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}
.msg-row.unread       .msg-sender { font-weight: 700; color: #1a1a2e; }
.msg-row.read         .msg-sender { font-weight: 500; color: #6b7280; }
.msg-row.deletion-unread .msg-sender { font-weight: 700; color: #991b1b; }

/* Subject */
.msg-subject {
    font-size: 0.95rem;
    margin-top: 0.5rem;
    word-break: break-word;
    display: block;
}
.msg-row.unread       .msg-subject { font-weight: 600; color: #1a1a2e; }
.msg-row.read         .msg-subject { font-weight: 400; color: #6b7280; }
.msg-row.deletion-unread .msg-subject { font-weight: 600; color: #7f1d1d; }

/* Preview */
.msg-preview {
    font-size: 0.875rem;
    margin-top: 0.3rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #6b7280;
    width: 100%;
    display: block;
}
.msg-preview .click-hint {
    font-size: 0.75rem;
    color: #0d6efd;
    opacity: 0.8;
    margin-left: 0.35rem;
}

/* Unread dot */
.unread-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    min-width: 8px;
    border-radius: 50%;
    flex-shrink: 0;
    margin-top: 1px;
}

/* ── Message modal ── */
#messageModal .modal-header { border-bottom:1px solid #e9ecef; padding:1rem 1.5rem; }
#messageModal .modal-body   { padding:1.5rem; }
#messageModal .modal-footer { border-top:1px solid #e9ecef; padding:0.75rem 1.5rem; background:#f9fafb; }
.msg-body-text { font-size:0.9rem;line-height:1.8;white-space:pre-wrap;color:#374151; }
.deletion-panel { background:#fff5f5;border:1px solid #fecaca;border-radius:10px;padding:1rem 1.25rem;margin-top:1.25rem; }

/* ── Action toast (approve/reject confirmation) ── */
#actionToastBackdrop {
    display: none;
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.45);
    z-index: 10;
    border-radius: 14px;
    animation: fadeIn 0.2s ease;
}
#actionToastBackdrop.show { display: block; }
@keyframes fadeIn {
    from { opacity: 0; }
    to   { opacity: 1; }
}
#actionToast {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -60%);
    width: 400px;
    max-width: calc(100% - 2rem);
    z-index: 11;
    border-radius: 16px;
    box-shadow: 0 16px 48px rgba(0,0,0,0.30);
    display: none;
    animation: popIn 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}
#actionToast.show {
    display: block;
    transform: translate(-50%, -50%);
}
@keyframes popIn {
    from { opacity:0; transform: translate(-50%, -44%) scale(0.92); }
    to   { opacity:1; transform: translate(-50%, -50%) scale(1); }
}
#actionToast .toast-body { padding: 1.5rem; }

/* ── Result toasts ── */
.result-toast {
    position: fixed;
    bottom: 1.5rem;
    right: 1.5rem;
    min-width: 300px;
    z-index: 1200;
    border-radius: 10px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}
/* ── Compose card (mirrors user side) ── */
.compose-card .card-header h5 { font-size: 1rem; }
.compose-card .card-body { padding: 1rem; }
.compose-card .form-label { font-size: 0.8rem; margin-bottom: 0.3rem; }
.compose-card .form-control,
.compose-card .form-select { font-size: 0.875rem; padding: 0.45rem 0.65rem; }
.compose-card textarea { resize: vertical; min-height: 100px; }
.compose-card .btn-send { font-size: 0.9rem; padding: 0.55rem; }

/* ── Searchable Select ── */
.searchable-select { position: relative; }
.searchable-select-trigger {
    display: flex; align-items: center; justify-content: space-between;
    padding: 0.45rem 0.65rem; font-size: 0.875rem;
    border: 1px solid var(--bs-border-color); border-radius: 0.375rem;
    background: var(--bs-body-bg); color: var(--bs-body-color);
    cursor: pointer; user-select: none;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.searchable-select-trigger:focus,
.searchable-select.open .searchable-select-trigger {
    border-color: #86b7fe; outline: 0;
    box-shadow: 0 0 0 0.25rem rgba(13,110,253,.25);
}
.searchable-select-arrow { font-size: 0.75rem; transition: transform 0.2s; }
.searchable-select.open .searchable-select-arrow { transform: rotate(180deg); }
.searchable-select-menu {
    display: none; position: absolute; z-index: 1055;
    width: 100%; top: calc(100% + 4px);
    border: 1px solid var(--bs-border-color); border-radius: 0.375rem;
    background: var(--bs-body-bg);
    box-shadow: 0 4px 16px rgba(0,0,0,.12);
    overflow: hidden;
}
.searchable-select.open .searchable-select-menu { display: block; }
.searchable-select-search-wrap {
    display: flex; align-items: center; gap: 0.4rem;
    padding: 0.5rem 0.65rem;
    border-bottom: 1px solid var(--bs-border-color);
}
.searchable-select-search-icon { font-size: 0.8rem; color: var(--bs-secondary-color); }
.searchable-select-search {
    border: none; outline: none; background: transparent;
    font-size: 0.85rem; color: var(--bs-body-color); width: 100%;
}
.searchable-select-options { max-height: 200px; overflow-y: auto; }
.searchable-select-option {
    padding: 0.45rem 0.75rem; font-size: 0.875rem;
    cursor: pointer; transition: background 0.1s;
}
.searchable-select-option:hover,
.searchable-select-option.active { background: rgba(13,110,253,.1); color: #0d6efd; }
.searchable-select-empty {
    padding: 0.6rem 0.75rem; font-size: 0.82rem;
    color: var(--bs-secondary-color); text-align: center;
}
</style>

<div class="p-4">

    <!-- Page header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-1">Messages</h2>
            <p class="text-muted mb-0">Inbox from applicants and system notifications</p>
        </div>
    </div>

    <!-- Flash alerts -->
    <?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle me-2"></i>Message deleted successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if (isset($_GET['sent'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle me-2"></i>Message sent successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row g-3">

        <!-- ── Left: filter bar + message list ── -->
        <div class="col-md-8">

            <!-- Filter bar -->
            <div class="card mb-3 border-0 shadow-sm">
                <div class="card-body py-2 px-3">
                    <form method="GET" action="" class="row g-2 align-items-center">
                        <div class="col-auto">
                            <div class="btn-group btn-group-sm" role="group">
                                <?php
                                $tabs = [
                                    'all'      => ['All',               'bi-inbox',         'btn-primary',   'btn-outline-secondary'],
                                    'unread'   => ['Unread',            'bi-envelope',      'btn-primary',   'btn-outline-secondary'],
                                    'read'     => ['Read',              'bi-envelope-open', 'btn-secondary', 'btn-outline-secondary'],
                                    'deletion' => ['Deletion Requests', 'bi-person-x',      'btn-danger',    'btn-outline-danger'],
                                ];
                                foreach ($tabs as $key => [$label, $icon, $activeCls, $outlineCls]):
                                    $active = ($filterType === $key);
                                    $href   = '?filter=' . $key . ($searchQuery ? '&q=' . urlencode($searchQuery) : '') . '&page=1';
                                ?>
                                <a href="<?php echo $href; ?>" class="btn <?php echo $active ? $activeCls : $outlineCls; ?>">
                                    <i class="bi <?php echo $icon; ?> me-1"></i><?php echo $label; ?>
                                    <?php if ($key === 'unread' && $unreadCount > 0): ?>
                                        <span class="badge bg-white text-primary ms-1"><?php echo $unreadCount; ?></span>
                                    <?php elseif ($key === 'deletion' && $deletionCount > 0): ?>
                                        <span class="badge bg-white text-danger ms-1"><?php echo $deletionCount; ?></span>
                                    <?php endif; ?>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="bi bi-search text-muted"></i>
                                </span>
                                <input type="text" id="liveSearchInput" class="form-control border-start-0 border-end-0"
                                       placeholder="Search sender, subject or message…"
                                       autocomplete="off">
                                <button type="button" id="clearSearchBtn" class="btn btn-outline-secondary" style="display:none;">
                                    <i class="bi bi-x"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Message list -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3" id="msgListBody">
                    <?php if (empty($messages)): ?>
                        <div class="p-5 text-center text-muted">
                            <i class="bi bi-inbox fs-2 d-block mb-2 opacity-50"></i>
                            No messages found.
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $msg):
                            $isDeletion  = stripos($msg['subject'] ?? '', 'Account Deletion Request') !== false;
                            $isUnread    = !$msg['is_read'];
                            $senderName  = trim(($msg['sender_first_name'] ?? '') . ' ' . ($msg['sender_last_name'] ?? '')) ?: 'System';
                            $previewText = mb_strimwidth(strip_tags($msg['message']), 0, 120, '…');
                            $targetUid   = (int)($msg['sender_user_id'] ?? 0);
                            $prefVal     = $isDeletion ? ($deletionStatuses[$targetUid] ?? '1') : null;
                            $appNum      = htmlspecialchars($msg['application_number'] ?? '');

                            if ($isDeletion && $isUnread) $rowClass = 'deletion-unread';
                            elseif ($isUnread)             $rowClass = 'unread';
                            else                           $rowClass = 'read';

                            $borderColor = $isDeletion ? '#dc3545' : ($isUnread ? '#0d6efd' : '#dee2e6');

                            $modalData = json_encode([
                                'id'         => $msg['id'],
                                'sender'     => $senderName,
                                'subject'    => $msg['subject'] ?? '',
                                'message'    => $msg['message'],
                                'date'       => Helper::formatDateTime($msg['created_at']),
                                'appNumber'  => $msg['application_number'] ?? '',
                                'isRead'     => (bool)$msg['is_read'],
                                'isDeletion' => $isDeletion,
                                'targetUid'  => $targetUid,
                                'prefVal'    => $prefVal,
                                'filter'     => $filterType,
                                'q'          => $searchQuery,
                                'page'       => $page,
                                'isAdmin' => (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin'])),
                            ]);
                        ?>
                        <div class="msg-row <?php echo $rowClass; ?>"
                             style="border-left: 5px solid <?php echo $borderColor; ?> !important;"
                             data-msg='<?php echo htmlspecialchars($modalData, ENT_QUOTES); ?>'
                             data-search="<?php echo htmlspecialchars(strtolower($senderName . ' ' . ($msg['subject'] ?? '') . ' ' . $msg['message']), ENT_QUOTES); ?>"
                             onclick="openMessage(JSON.parse(this.dataset.msg))"
                             role="button" tabindex="0"
                             onkeydown="if(event.key==='Enter')openMessage(JSON.parse(this.dataset.msg))">

                            <div class="msg-meta">
                                <div class="msg-sender">
                                    <?php if ($isUnread): ?>
                                        <span class="unread-dot" style="background:<?php echo $isDeletion ? '#dc3545' : '#0d6efd'; ?>;"></span>
                                    <?php endif; ?>
                                    <strong><?php echo htmlspecialchars($senderName); ?></strong>
                                    <?php if ($appNum): ?>
                                        <span class="badge bg-info text-dark ms-1"><?php echo $appNum; ?></span>
                                    <?php endif; ?>
                                    <?php if ($isDeletion): ?>
                                        <span class="badge bg-danger ms-1"><i class="bi bi-person-x me-1"></i>Deletion</span>
                                    <?php endif; ?>
                                    <?php if ($isUnread && !$isDeletion): ?>
                                        <span class="badge bg-primary ms-1">New</span>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted"><?php echo Helper::formatDateTime($msg['created_at']); ?></small>
                            </div>

                            <div class="msg-subject"><?php echo htmlspecialchars($msg['subject'] ?? '(no subject)'); ?></div>

                            <div class="msg-preview">
                                <?php echo htmlspecialchars($previewText); ?>
                                <span class="click-hint"><i class="bi bi-eye me-1"></i>Read more</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($totalPages > 1):
                $paginationBase = '?filter=' . urlencode($filterType)
                    . ($searchQuery ? '&q=' . urlencode($searchQuery) : '');
            ?>
            <nav class="mt-3" aria-label="Message pages" id="msgPagination">
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo $paginationBase; ?>&page=<?php echo $page - 1; ?>" aria-label="Previous">
                            <i class="bi bi-chevron-left"></i> Prev
                        </a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo $paginationBase; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo $paginationBase; ?>&page=<?php echo $page + 1; ?>" aria-label="Next">
                            Next <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
                <p class="text-center text-muted mt-2 mb-0" style="font-size:0.78rem;">
                    Showing <?php echo $totalCount === 0 ? 0 : $offset + 1; ?>–<?php echo min($offset + $limit, $totalCount); ?> of <?php echo $totalCount; ?> messages
                </p>
            </nav>
            <?php endif; ?>

        </div><!-- /.col-md-8 -->

        <!-- ── Right: Compose card ── -->
        <div class="col-md-4">
            <div class="card shadow-sm border-0 compose-card">
                <div class="card-header bg-primary text-white py-3 border-0">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2"></i>New Message</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">To (User)</label>
                            <!-- Hidden input carries the actual value on submit -->
                            <input type="hidden" name="receiver_id" id="receiverId" required>
                            <div class="searchable-select" id="userDropdown">
                                <div class="searchable-select-trigger" id="userDropdownTrigger" tabindex="0">
                                    <span id="userDropdownLabel" class="text-muted">Select User</span>
                                    <i class="bi bi-chevron-down searchable-select-arrow"></i>
                                </div>
                                <div class="searchable-select-menu" id="userDropdownMenu">
                                    <div class="searchable-select-search-wrap">
                                        <i class="bi bi-search searchable-select-search-icon"></i>
                                        <input type="text" class="searchable-select-search" id="userSearch"
                                               placeholder="Search user..." autocomplete="off">
                                    </div>
                                    <div class="searchable-select-options" id="userOptions">
                                        <?php
                                        $applicants = $db->fetchAll(
                                            "SELECT id, first_name, last_name FROM users WHERE role = 'applicant' AND is_active = 1 ORDER BY first_name, last_name"
                                        );
                                        foreach ($applicants as $ap):
                                            $fullName = htmlspecialchars($ap['first_name'] . ' ' . $ap['last_name']);
                                        ?>
                                            <div class="searchable-select-option" data-value="<?php echo $ap['id']; ?>" data-label="<?php echo $fullName; ?>">
                                                <i class="bi bi-person me-2 text-muted"></i><?php echo $fullName; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="searchable-select-empty" id="userNoResults" style="display:none;">
                                        <i class="bi bi-search me-1"></i>No users found
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Subject</label>
                            <input type="text" class="form-control" name="subject" placeholder="e.g. Application Update">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Message</label>
                            <textarea class="form-control" name="message" rows="5" required placeholder="Type your message..."></textarea>
                        </div>
                        <button type="submit" name="send_message" class="btn btn-primary w-100 fw-bold btn-send">
                            <i class="bi bi-send me-2"></i>Send Message
                        </button>
                    </form>
                </div>
            </div>
        </div><!-- /.col-md-4 -->

    </div><!-- /.row -->

</div><!-- /.p-4 -->


<!-- ══════════════════════════════════════════════════════════════
     Message Modal
═══════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="border-radius:14px;overflow:hidden;position:relative;">

            <div class="modal-header">
                <div class="flex-grow-1 me-3">
                    <h5 class="modal-title mb-0 fw-semibold" id="messageModalLabel" style="font-size:1rem;"></h5>
                    <div id="modalMeta" class="text-muted mt-1" style="font-size:0.8rem;"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div id="modalBody" class="msg-body-text"></div>

                <!-- Deletion admin panel -->
                <div id="deletionPanel" class="deletion-panel d-none">
                    <p class="mb-1 fw-semibold text-danger" style="font-size:0.85rem;">
                        <i class="bi bi-shield-exclamation me-1"></i>Admin Action Required
                    </p>
                    <p class="text-muted mb-3" id="deletionPanelHint" style="font-size:0.82rem;">
                        Review and take action on this account deletion request.
                    </p>
                    <div id="deletionActions"></div>
                </div>
            </div>

            <div class="modal-footer justify-content-between">
                <div class="d-flex gap-2" id="modalFooterLeft"></div>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>

            <!-- ── Confirmation overlay (lives inside modal to stay in focus trap) ── -->
            <div id="actionToastBackdrop"></div>
            <div id="actionToast">
    <div class="toast-body bg-white rounded-3">

        <!-- APPROVE view -->
        <div id="toastApproveView">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span style="width:36px;height:36px;background:#dcfce7;border-radius:8px;
                             display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="bi bi-check-circle-fill text-success fs-5"></i>
                </span>
                <div>
                    <div class="fw-semibold" style="font-size:0.9rem;">Approve Account Deletion?</div>
                    <div class="text-muted" style="font-size:0.78rem;">The user account will be deactivated immediately and the user will be notified.</div>
                </div>
            </div>
            <div class="d-flex gap-2 justify-content-end mt-3">
                <button class="btn btn-sm btn-outline-secondary px-3" onclick="closeActionToast()">Cancel</button>
                <button class="btn btn-sm btn-success px-3" id="toastApproveConfirm" onclick="submitDeletionAction('approve')">
                    <i class="bi bi-check-circle me-1"></i>Yes, Approve
                </button>
            </div>
        </div>

        <!-- REJECT view -->
        <div id="toastRejectView" class="d-none">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span style="width:36px;height:36px;background:#fee2e2;border-radius:8px;
                             display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="bi bi-x-circle-fill text-danger fs-5"></i>
                </span>
                <div>
                    <div class="fw-semibold" style="font-size:0.9rem;">Reject Deletion Request?</div>
                    <div class="text-muted" style="font-size:0.78rem;">A message will be sent back to the user explaining the rejection.</div>
                </div>
            </div>
            <div class="mb-2">
                <label class="form-label fw-semibold mb-1" style="font-size:0.8rem;">
                    Reason for rejection <span class="text-muted fw-normal">(optional — sent to user)</span>
                </label>
                <textarea id="rejectReasonInput" class="form-control form-control-sm" rows="3"
                          placeholder="e.g. Your account has pending applications that must be resolved first…"
                          style="font-size:0.82rem;resize:none;"></textarea>
            </div>
            <div class="d-flex gap-2 justify-content-end mt-2">
                <button class="btn btn-sm btn-outline-secondary px-3" onclick="closeActionToast()">Cancel</button>
                <button class="btn btn-sm btn-danger px-3" onclick="submitDeletionAction('reject')">
                    <i class="bi bi-x-circle me-1"></i>Yes, Reject
                </button>
            </div>
        </div>

    </div>
</div>

        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /#messageModal -->


<!-- ══════════════════════════════════════════════════════════════
     Result Toast (success / error)
═══════════════════════════════════════════════════════════════ -->
<div id="resultToast" class="toast result-toast align-items-center border-0" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
        <div class="toast-body d-flex align-items-center gap-2" id="resultToastBody"></div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
</div>


<script>
var _pendingData   = null;   // the modal data object for the pending action
var _pendingAction = null;   // 'approve' or 'reject'

// ── Open message modal ────────────────────────────────────────────────────────
function openMessage(data) {
    _pendingData = data;

    document.getElementById('messageModalLabel').textContent = data.subject || '(no subject)';

    var metaParts = ['From: ' + data.sender];
    if (data.appNumber) metaParts.push('Application: ' + data.appNumber);
    metaParts.push(data.date);
    document.getElementById('modalMeta').textContent = metaParts.join('  ·  ');

    document.getElementById('modalBody').textContent = data.message;

    // Deletion panel
    var panel   = document.getElementById('deletionPanel');
    var actions = document.getElementById('deletionActions');
    var hint    = document.getElementById('deletionPanelHint');

    if (data.isDeletion && data.isAdmin) {
        panel.classList.remove('d-none');
        actions.innerHTML = '';
        hint.style.display = '';

        if (data.prefVal === '2') {
            actions.innerHTML = '<span class="badge bg-success px-3 py-2" style="font-size:0.85rem;"><i class="bi bi-check-circle me-1"></i>Already Approved</span>';
            hint.style.display = 'none';
        } else if (data.prefVal === '0') {
            actions.innerHTML = '<span class="badge bg-secondary px-3 py-2" style="font-size:0.85rem;"><i class="bi bi-x-circle me-1"></i>Already Rejected</span>';
            hint.style.display = 'none';
        } else {
            actions.innerHTML =
                '<button class="btn btn-sm btn-success px-3 me-2" onclick="showActionToast(\'approve\')">'
                + '<i class="bi bi-check-circle me-1"></i>Approve Deletion</button>'
                + '<button class="btn btn-sm btn-outline-danger px-3" onclick="showActionToast(\'reject\')">'
                + '<i class="bi bi-x-circle me-1"></i>Reject Request</button>';
        }
    } else {
        panel.classList.add('d-none');
    }

    // Footer buttons
    var qStr = '&filter=' + encodeURIComponent(data.filter)
             + (data.q    ? '&q='    + encodeURIComponent(data.q)    : '')
             + (data.page ? '&page=' + encodeURIComponent(data.page) : '');
    var btns = '';
    if (!data.isRead) {
        btns += '<a href="?mark_read=' + data.id + qStr + '" class="btn btn-sm btn-outline-primary">'
              + '<i class="bi bi-envelope-open me-1"></i>Mark as Read</a>';
    }
    btns += '<a href="?delete=' + data.id + qStr + '" class="btn btn-sm btn-outline-danger"'
          + ' onclick="return confirm(\'Delete this message? This cannot be undone.\')">'
          + '<i class="bi bi-trash me-1"></i>Delete</a>';
    document.getElementById('modalFooterLeft').innerHTML = btns;

    // Silently mark as read
    if (!data.isRead) {
        fetch('?mark_read=' + data.id + '&filter=' + encodeURIComponent(data.filter), { redirect: 'manual' })
            .then(function () {
                var row = document.querySelector('.msg-row[data-id="' + data.id + '"]');
                if (row) {
                    row.classList.remove('unread');
                    row.classList.add('read');
                    var dot = row.querySelector('.unread-dot');
                    if (dot) dot.remove();
                    var nb = row.querySelector('.badge.bg-primary');
                    if (nb) nb.remove();
                }
            });
    }

    bootstrap.Modal.getOrCreateInstance(document.getElementById('messageModal')).show();
}

// ── Action toast ──────────────────────────────────────────────────────────────
function showActionToast(action) {
    _pendingAction = action;
    var toast = document.getElementById('actionToast');

    document.getElementById('toastApproveView').classList.toggle('d-none', action !== 'approve');
    document.getElementById('toastRejectView').classList.toggle('d-none',  action !== 'reject');

    if (action === 'reject') {
        document.getElementById('rejectReasonInput').value = '';
    }

    toast.classList.add('show');
    document.getElementById('actionToastBackdrop').classList.add('show');
    // Focus textarea for reject
    if (action === 'reject') {
        setTimeout(function () {
            document.getElementById('rejectReasonInput').focus();
        }, 50);
    }
}

function closeActionToast() {
    document.getElementById('actionToast').classList.remove('show');
    document.getElementById('actionToastBackdrop').classList.remove('show');
    _pendingAction = null;
}

// ── Submit deletion action via fetch ──────────────────────────────────────────
function submitDeletionAction(action) {
    if (!_pendingData) return;

    var approveBtn = document.getElementById('toastApproveConfirm');
    if (approveBtn && action === 'approve') {
        approveBtn.disabled = true;
        approveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing…';
    }

    var formData = new FormData();
    formData.append('ajax_deletion_action', action);
    formData.append('target_user_id',       _pendingData.targetUid);
    formData.append('msg_id',               _pendingData.id);
    if (action === 'reject') {
        formData.append('reject_reason', document.getElementById('rejectReasonInput').value.trim());
    }

    fetch(window.location.pathname + window.location.search, {
        method: 'POST',
        body:   formData
    })
    .then(function (r) { return r.json(); })
    .then(function (res) {
        closeActionToast();

        if (res.ok) {
            // Update the deletion panel inside the modal to show done state
            var actions = document.getElementById('deletionActions');
            var hint    = document.getElementById('deletionPanelHint');
            if (action === 'approve') {
                actions.innerHTML = '<span class="badge bg-success px-3 py-2" style="font-size:0.85rem;"><i class="bi bi-check-circle me-1"></i>Approved</span>';
            } else {
                actions.innerHTML = '<span class="badge bg-secondary px-3 py-2" style="font-size:0.85rem;"><i class="bi bi-x-circle me-1"></i>Rejected</span>';
            }
            hint.style.display = 'none';

            // Remove deletion badge from the list row
            var row = document.querySelector('.msg-row[data-id="' + _pendingData.id + '"]');
            if (row) {
                var delBadge = row.querySelector('.badge.bg-danger');
                if (delBadge) delBadge.remove();
            }

            showResultToast(
                action === 'approve'
                    ? 'Account deletion approved. The user has been deactivated and notified.'
                    : 'Deletion request rejected. A message has been sent to the user.',
                action === 'approve' ? 'success' : 'warning'
            );
        } else {
            showResultToast(res.error || 'Something went wrong. Please try again.', 'danger');
            // Re-enable approve button if failed
            if (approveBtn) {
                approveBtn.disabled = false;
                approveBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Yes, Approve';
            }
        }
    })
    .catch(function () {
        closeActionToast();
        showResultToast('Network error. Please check your connection and try again.', 'danger');
    });
}

// ── Result toast ──────────────────────────────────────────────────────────────
function showResultToast(message, type) {
    var el   = document.getElementById('resultToast');
    var body = document.getElementById('resultToastBody');

    var icons = { success: 'bi-check-circle-fill', warning: 'bi-exclamation-triangle-fill', danger: 'bi-x-circle-fill' };
    var icon  = icons[type] || 'bi-info-circle-fill';

    el.className = 'toast result-toast align-items-center border-0 text-white bg-' + type;
    body.innerHTML = '<i class="bi ' + icon + ' me-2 fs-5"></i>' + message;

    var bsToast = bootstrap.Toast.getOrCreateInstance(el, { delay: 5000 });
    bsToast.show();
}

// ── Set data-id on rows (for mark-as-read update) ─────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.msg-row').forEach(function (row) {
        try {
            var d = JSON.parse(row.dataset.msg);
            if (d && d.id) row.setAttribute('data-id', d.id);
        } catch(e) {}
    });

    // Close action toast on backdrop click
    document.getElementById('actionToastBackdrop').addEventListener('click', function () {
        closeActionToast();
    });

    // Close action toast on outside click
    document.addEventListener('click', function (e) {
        var toast = document.getElementById('actionToast');
        if (toast.classList.contains('show') && !toast.contains(e.target)) {
            // Only close if the click isn't on one of the modal action buttons
            var btns = document.getElementById('deletionActions');
            if (btns && btns.contains(e.target)) return;
            closeActionToast();
        }
    });
});

// ── Searchable User Dropdown ──────────────────────────────────────────────────
(function () {
    const dropdown  = document.getElementById('userDropdown');
    const trigger   = document.getElementById('userDropdownTrigger');
    const menu      = document.getElementById('userDropdownMenu');
    const search    = document.getElementById('userSearch');
    const options   = document.querySelectorAll('.searchable-select-option');
    const noResults = document.getElementById('userNoResults');
    const label     = document.getElementById('userDropdownLabel');
    const hidden    = document.getElementById('receiverId');

    if (!dropdown) return;

    // Open / close
    trigger.addEventListener('click', function () {
        dropdown.classList.toggle('open');
        if (dropdown.classList.contains('open')) {
            search.value = '';
            filterOptions('');
            setTimeout(() => search.focus(), 50);
        }
    });

    // Close on outside click
    document.addEventListener('click', function (e) {
        if (!dropdown.contains(e.target)) dropdown.classList.remove('open');
    });

    // Close on Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') dropdown.classList.remove('open');
    });

    // Live filter
    search.addEventListener('input', function () {
        filterOptions(this.value.trim().toLowerCase());
    });

    function filterOptions(q) {
        let visible = 0;
        options.forEach(function (opt) {
            const match = opt.dataset.label.toLowerCase().includes(q);
            opt.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        noResults.style.display = visible === 0 ? 'block' : 'none';
    }

    // Select option
    options.forEach(function (opt) {
        opt.addEventListener('click', function () {
            options.forEach(o => o.classList.remove('active'));
            this.classList.add('active');
            hidden.value        = this.dataset.value;
            label.textContent   = this.dataset.label;
            label.classList.remove('text-muted');
            dropdown.classList.remove('open');
        });
    });
})();

// ── Live search (AJAX — searches ALL messages across all pages) ───────────────
(function () {
    const input      = document.getElementById('liveSearchInput');
    const clearBtn   = document.getElementById('clearSearchBtn');
    const listBody   = document.getElementById('msgListBody');
    const pagination = document.getElementById('msgPagination');
    if (!input || !listBody) return;

    // Grab the active filter from the URL
    function getFilter() {
        return new URLSearchParams(window.location.search).get('filter') || 'all';
    }

    let debounceTimer = null;
    let currentQ      = '';

    // ── Render a single message row from JSON data ────────────────────────────
    function renderRow(d) {
        const isDeletion = d.isDeletion;
        const isUnread   = !d.isRead;
        let rowClass     = isUnread ? (isDeletion ? 'deletion-unread' : 'unread') : 'read';
        let borderColor  = isDeletion ? '#dc3545' : (isUnread ? '#0d6efd' : '#dee2e6');

        const dot    = isUnread ? `<span class="unread-dot" style="background:${isDeletion ? '#dc3545' : '#0d6efd'};"></span>` : '';
        const appBadge = d.appNumber ? `<span class="badge bg-info text-dark ms-1">${escHtml(d.appNumber)}</span>` : '';
        const delBadge = isDeletion  ? `<span class="badge bg-danger ms-1"><i class="bi bi-person-x me-1"></i>Deletion</span>` : '';
        const newBadge = (isUnread && !isDeletion) ? `<span class="badge bg-primary ms-1">New</span>` : '';

        const safeData = JSON.stringify(d).replace(/'/g, "&#39;");

        return `<div class="msg-row ${rowClass}"
                     style="border-left:5px solid ${borderColor} !important;"
                     data-msg='${safeData}'
                     data-id="${d.id}"
                     onclick="openMessage(JSON.parse(this.dataset.msg))"
                     role="button" tabindex="0"
                     onkeydown="if(event.key==='Enter')openMessage(JSON.parse(this.dataset.msg))">
            <div class="msg-meta">
                <div class="msg-sender">
                    ${dot}<strong>${escHtml(d.sender)}</strong>${appBadge}${delBadge}${newBadge}
                </div>
                <small class="text-muted">${escHtml(d.date)}</small>
            </div>
            <div class="msg-subject">${escHtml(d.subject || '(no subject)')}</div>
            <div class="msg-preview">${escHtml(d.preview)}<span class="click-hint"><i class="bi bi-eye me-1"></i>Read more</span></div>
        </div>`;
    }

    function escHtml(str) {
        return String(str ?? '')
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function showLoading() {
        listBody.innerHTML = '<div class="p-4 text-center text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Searching…</div>';
    }

    function showResults(results, q) {
        if (results.length === 0) {
            listBody.innerHTML = '<div class="p-5 text-center text-muted"><i class="bi bi-search fs-2 d-block mb-2 opacity-50"></i>No messages found for <strong>' + escHtml(q) + '</strong>.</div>';
            return;
        }
        listBody.innerHTML = results.map(renderRow).join('');
        // Show result count
        const info = document.createElement('p');
        info.className = 'text-center text-muted mt-2 mb-0';
        info.style.fontSize = '0.78rem';
        info.textContent = results.length + ' result' + (results.length !== 1 ? 's' : '') + ' found across all pages';
        listBody.appendChild(info);
    }

    function restorePage() {
        window.location.reload();
    }

    function doSearch(q) {
        currentQ = q;
        if (!q) {
            // Clear — reload to restore paginated view
            restorePage();
            return;
        }
        showLoading();
        if (pagination) pagination.style.display = 'none';

        const url = `?ajax_search=1&q=${encodeURIComponent(q)}&filter=${encodeURIComponent(getFilter())}`;
        fetch(url)
            .then(r => r.json())
            .then(data => {
                if (data.ok) showResults(data.results, q);
                else listBody.innerHTML = '<div class="p-4 text-center text-danger">Search failed. Please try again.</div>';
            })
            .catch(() => {
                listBody.innerHTML = '<div class="p-4 text-center text-danger">Network error. Please try again.</div>';
            });
    }

    input.addEventListener('input', function () {
        const q = this.value.trim();
        if (clearBtn) clearBtn.style.display = q ? '' : 'none';
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => doSearch(q), 350);
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            input.value = '';
            clearBtn.style.display = 'none';
            restorePage();
        });
    }
})();
</script>

<?php include __DIR__ . '/../admin/footer.php'; ?>