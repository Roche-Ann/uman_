<?php
/**
 * Messages & Notifications
 */

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helper.php';
require_once __DIR__ . '/../modules/ApplicantSelfService/ApplicantController.php';

$auth = new Auth();
$auth->requireRole('applicant');

$applicantController = new ApplicantController();
$applicationId = $_GET['application_id'] ?? null;
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');
// When a search is submitted always start from page 1
$page = (!empty($search) && !isset($_GET['page'])) ? 1 : (isset($_GET['page']) ? (int)$_GET['page'] : 1);
$limit = 5;
$offset = ($page - 1) * $limit;

$messagesData = $applicantController->getMessagesPaginated($applicationId, $filter, $limit, $offset, $search);
$messages = $messagesData['items'];
$totalMessages = $messagesData['total'];
$totalPages = ceil($totalMessages / $limit);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $receiverId = $_POST['receiver_id'] ?? 0;
    $message = $_POST['message'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $appId = $_POST['application_id'] ?? null;
    
    if (empty($message)) {
        $error = _mgt('err_msg_required');
    } else {
        $applicantController->sendMessage($receiverId, $message, $subject, $appId);
        header("Location: messages.php?filter=sent&success=1");
        exit;
    }
}

if (isset($_GET['mark_read'])) {
    $applicantController->markMessageAsRead($_GET['mark_read']);
    $qs = http_build_query(array_filter(['filter' => $filter, 'page' => $page, 'search' => $search]));
    header('Location: messages.php?' . $qs);
    exit;
}

// ── i18n — reads language saved by settings.php ──────────────────────────────
$_mgLang = $_SESSION['locale_language'] ?? 'en_PH';

$_mgT = [
    'en_PH' => [
        'page_title'        => 'Messages',
        'heading'           => 'Messages & Notifications',
        'msg_sent_ok'       => 'Message sent successfully!',
        'err_msg_required'  => 'Message is required',
        // Inbox header labels
        'inbox_sent'        => 'Sent Messages',
        'inbox_unread'      => 'Unread Inbox',
        'inbox_read'        => 'Read Inbox',
        'inbox_all'         => 'Inbox (All)',
        // Filter buttons
        'filter_all'        => 'All',
        'filter_unread'     => 'Unread',
        'filter_read'       => 'Read',
        'filter_sent'       => 'Sent',
        // Message list
        'no_messages'       => 'No messages found here.',
        'lbl_to'            => 'To: ',
        'lbl_from'          => 'From: ',
        'no_subject'        => '(No Subject)',
        'read_more'         => 'Read more',
        'btn_mark_read'     => 'Mark as Read',
        'btn_close'         => 'Close',
        'btn_download_permit' => 'Download Permit',
        // Compose card
        'compose_title'     => 'New Message',
        'lbl_to_officer'    => 'To (Officer)',
        'opt_select_officer'=> 'Select Officer',
        'lbl_subject'       => 'Subject',
        'ph_subject'        => 'Application Inquiry',
        'lbl_message'       => 'Message',
        'ph_message'        => 'Type your message...',
        'btn_send'          => 'Send Message',
    ],
    'fil' => [
        'page_title'        => 'Mga Mensahe',
        'heading'           => 'Mga Mensahe at Abiso',
        'msg_sent_ok'       => 'Matagumpay na naipadala ang mensahe!',
        'err_msg_required'  => 'Kinakailangan ang mensahe',
        // Inbox header labels
        'inbox_sent'        => 'Mga Naipadalang Mensahe',
        'inbox_unread'      => 'Hindi pa Nababasa',
        'inbox_read'        => 'Nabasa na',
        'inbox_all'         => 'Lahat ng Mensahe',
        // Filter buttons
        'filter_all'        => 'Lahat',
        'filter_unread'     => 'Hindi Nabasa',
        'filter_read'       => 'Nabasa',
        'filter_sent'       => 'Naipadala',
        // Message list
        'no_messages'       => 'Walang mga mensaheng natagpuan.',
        'lbl_to'            => 'Para kay: ',
        'lbl_from'          => 'Mula kay: ',
        'no_subject'        => '(Walang Paksa)',
        'read_more'         => 'Magbasa pa',
        'btn_mark_read'     => 'Markahan bilang Nabasa',
        'btn_close'         => 'Isara',
        'btn_download_permit' => 'I-download ang Permit',
        // Compose card
        'compose_title'     => 'Bagong Mensahe',
        'lbl_to_officer'    => 'Para sa (Opisyal)',
        'opt_select_officer'=> 'Pumili ng Opisyal',
        'lbl_subject'       => 'Paksa',
        'ph_subject'        => 'Katanungan sa Aplikasyon',
        'lbl_message'       => 'Mensahe',
        'ph_message'        => 'I-type ang iyong mensahe...',
        'btn_send'          => 'Ipadala ang Mensahe',
    ],
];

function _mgt(string $key): string {
    global $_mgT, $_mgLang;
    return $_mgT[$_mgLang][$key] ?? $_mgT['en_PH'][$key] ?? $key;
}

$pageTitle = _mgt('page_title');
$isAuthPage = true;
include __DIR__ . '/../user/header.php';
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
/* =============================================
   MESSAGES PAGE — MODERN CIVIC THEME
   Breakpoints: 1024px | 768px | 480px | 320px
   Root fix: prevent horizontal overflow from
   sidebar + Bootstrap padding on narrow screens
   ============================================= */

.messages-page {
    --ap-navy:        #16324F;
    --ap-navy-deep:   #0F2438;
    --ap-navy-tint:   #EAF0F5;
    --ap-gold:        #A9812F;
    --ap-gold-tint:   #F6EFDE;
    --ap-bg:          #F6F7F9;
    --ap-surface:     #FFFFFF;
    --ap-border:      #E2E6EC;
    --ap-text:        #1C2733;
    --ap-text-muted:  #667085;

    padding: 0;
    width: 100%;
    box-sizing: border-box;
    overflow-x: hidden;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    color: var(--ap-text);
}

.messages-page h2 {
    font-size: 1.55rem;
    font-weight: 800;
    letter-spacing: -0.015em;
    color: var(--ap-navy-deep);
    margin-bottom: 1.25rem;
}

/* --- Dark mode ---
   Every rule below already reads colors from the --ap-* custom
   properties, so redefining them here re-themes the whole page. */
[data-bs-theme="dark"] .messages-page {
    --ap-navy:        #4d8eff;
    --ap-navy-deep:   #cfe2ff;
    --ap-navy-tint:   rgba(77, 142, 255, .15);
    --ap-gold:        #e8c568;
    --ap-gold-tint:   rgba(232, 197, 104, .12);
    --ap-bg:          var(--bs-body-bg);
    --ap-surface:     var(--bs-tertiary-bg);
    --ap-border:      var(--bs-border-color);
    --ap-text:        var(--bs-body-color);
    --ap-text-muted:  var(--bs-secondary-color);
}

/* This rule hardcoded a light-blue background over Bootstrap's own
   theme-aware .bg-body-tertiary, which otherwise re-themes itself
   automatically — so unread rows need an explicit dark variant. */
[data-bs-theme="dark"] .message-item.bg-body-tertiary {
    background: rgba(77, 142, 255, .15) !important;
}

/* Force all children to respect container width */
.messages-page *,
.messages-page *::before,
.messages-page *::after {
    box-sizing: border-box;
    max-width: 100%;
}

/* --- Layout row --- */
.messages-layout {
    width: 100%;
    margin: 0;
}

/* Bootstrap .row adds negative margins — neutralise on mobile */
@media (max-width: 768px) {
    .messages-layout {
        --bs-gutter-x: 0;
        margin-left: 0 !important;
        margin-right: 0 !important;
    }
    .messages-layout > [class*="col-"] {
        padding-left: 0 !important;
        padding-right: 0 !important;
    }
}

/* --- Cards --- */
.messages-page .card {
    background: var(--ap-surface);
    border: 1px solid var(--ap-border);
    border-radius: 12px;
    box-shadow: 0 1px 2px rgba(16,24,40,.04);
}

.messages-page .alert-success {
    background: var(--ap-navy-tint);
    border: 1px solid rgba(22,50,79,.15);
    color: var(--ap-navy-deep);
    border-radius: 10px;
}

/* --- Filter Bar (admin style) --- */
.messages-filter-bar {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--ap-border) !important;
}

/* Keep filter tabs vertically centered with the input-group height,
   not pulled down by the result count that appears below the search col */
.messages-filter-bar .col-auto {
    padding-top: 1px; /* optical alignment with input-group-sm */
}

.messages-filter-bar .btn-group .btn {
    font-size: 0.82rem;
    font-weight: 600;
    padding: 0.35rem 0.8rem;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 0.35rem;
    border-radius: 7px !important;
}

.messages-filter-bar .btn-group .btn + .btn {
    margin-left: 0.3rem;
}

.messages-filter-bar .btn-primary,
.messages-filter-bar .btn-outline-primary {
    background: var(--ap-navy);
    border-color: var(--ap-navy);
    color: #fff;
}

.messages-filter-bar .btn-primary:hover,
.messages-filter-bar .btn-outline-primary:hover {
    background: var(--ap-navy-deep);
    border-color: var(--ap-navy-deep);
}

.messages-filter-bar .btn-secondary {
    background: var(--ap-text-muted);
    border-color: var(--ap-text-muted);
    color: #fff;
}

.messages-filter-bar .btn-outline-secondary {
    color: var(--ap-text-muted);
    border-color: var(--ap-border);
    background: var(--ap-surface);
}

.messages-filter-bar .btn-outline-secondary:hover {
    background: var(--ap-bg);
    border-color: var(--ap-text-muted);
}

.messages-filter-bar .input-group-text {
    background: var(--ap-surface);
    border: 1px solid var(--ap-border);
    border-right: 0;
    border-radius: 7px 0 0 7px;
}

.messages-filter-bar .form-control {
    border: 1px solid var(--ap-border);
    border-left: 0;
    font-size: 0.875rem;
    border-radius: 0 7px 7px 0;
}

.messages-filter-bar .form-control:focus {
    box-shadow: none;
    border-color: var(--ap-border);
}

.messages-filter-bar #msgSearchBtn {
    background: var(--ap-navy);
    border-color: var(--ap-navy);
    border-radius: 7px;
    font-weight: 600;
}

.messages-filter-bar #msgSearchBtn:hover {
    background: var(--ap-navy-deep);
    border-color: var(--ap-navy-deep);
}

/* Result count sits snugly under the search bar, doesn't affect row height */
.messages-filter-bar .col > small {
    display: block;
    margin-top: 0.3rem;
    font-size: 0.75rem;
    line-height: 1.2;
    color: var(--ap-text-muted);
}

/* --- Card inbox header label --- */
.messages-card-header {
    padding: 1rem 1.25rem;
}

.messages-card-header h5 {
    font-size: 1rem;
    font-weight: 700;
    color: var(--ap-navy-deep);
    margin-bottom: 0;
}

/* --- Filter Button Group (legacy, kept for mobile fallback) --- */
.filter-btn-group {
    display: flex;
    flex-wrap: nowrap;
    gap: 0.2rem;
    flex-shrink: 0;
}

.filter-btn-group .btn {
    font-size: 0.78rem;
    padding: 0.28rem 0.6rem;
    white-space: nowrap;
    flex: 1 1 auto;
}

/* --- Message Item (clickable preview) --- */
.message-item {
    margin-bottom: 0.875rem;
    padding: 0.9rem 1rem;
    border-radius: 10px;
    cursor: pointer;
    transition: box-shadow 0.15s ease, transform 0.1s ease;
    user-select: none;
    width: 100%;
    overflow: hidden;
    border: 1px solid var(--ap-border) !important;
}

.message-item:hover {
    box-shadow: 0 4px 14px rgba(22,50,79,0.1) !important;
    transform: translateY(-1px);
}

.message-item:active {
    transform: translateY(0);
}

.message-item.bg-body-tertiary {
    background: #eaf2ff !important;
}

.message-item .message-meta {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.5rem;
    flex-wrap: wrap;
    width: 100%;
}

.message-item .message-meta small {
    font-size: 0.78rem;
    white-space: nowrap;
    flex-shrink: 0;
    color: var(--ap-text-muted);
}

.message-item .message-meta strong {
    color: var(--ap-navy-deep);
}

.message-item .message-subject {
    font-size: 0.95rem;
    margin-top: 0.5rem;
    word-break: break-word;
    color: var(--ap-navy-deep);
}

/* Preview: single truncated line */
.message-preview {
    font-size: 0.875rem;
    margin-top: 0.3rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: var(--ap-text-muted);
    width: 100%;
    display: block;
}

.message-preview .click-hint {
    font-size: 0.75rem;
    color: #0d6efd;
    opacity: 0.85;
    margin-left: 0.35rem;
    font-weight: 600;
}

/* Unread dot — blue accent */
.unread-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    min-width: 8px;
    background: #0d6efd;
    border-radius: 50%;
    margin-right: 6px;
    flex-shrink: 0;
    margin-top: 3px;
}

/* Badges (application number tag) */
.messages-page .badge.bg-info {
    background: var(--ap-navy-tint) !important;
    color: var(--ap-navy-deep) !important;
    font-weight: 600;
    font-size: 0.7rem;
    border-radius: 999px;
    padding: 0.3em 0.7em;
}

/* --- Full Message Modal --- */
.msg-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(15, 36, 56, 0.45);
    z-index: 1055;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    backdrop-filter: blur(2px);
}

.msg-modal-overlay.active {
    display: flex;
}

.msg-modal {
    background: var(--ap-surface);
    color: var(--ap-text);
    border-radius: 14px;
    box-shadow: 0 20px 60px rgba(15,36,56,0.25);
    width: 100%;
    max-width: 620px;
    max-height: 88vh;
    display: flex;
    flex-direction: column;
    animation: modalIn 0.18s ease;
}

@keyframes modalIn {
    from { opacity: 0; transform: scale(0.96) translateY(10px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
}

.msg-modal-header {
    padding: 1.1rem 1.35rem 0.85rem;
    border-bottom: 1px solid var(--ap-border);
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.75rem;
    flex-shrink: 0;
}

.msg-modal-header .modal-title-group {
    flex: 1;
    min-width: 0;
}

.msg-modal-header .modal-sender {
    font-size: 0.82rem;
    color: var(--ap-text-muted);
    margin-bottom: 0.25rem;
    word-break: break-word;
}

.msg-modal-header .modal-subject {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--ap-navy-deep);
    word-break: break-word;
}

.msg-modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    line-height: 1;
    color: var(--ap-text-muted);
    cursor: pointer;
    padding: 0 0.25rem;
    flex-shrink: 0;
}

.msg-modal-close:hover { color: var(--ap-text); }

.msg-modal-body {
    padding: 1.1rem 1.35rem;
    overflow-y: auto;
    flex: 1;
    font-size: 0.9rem;
    line-height: 1.6;
    word-break: break-word;
}

.msg-modal-footer {
    padding: 0.85rem 1.35rem;
    border-top: 1px solid var(--ap-border);
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    flex-wrap: wrap;
    flex-shrink: 0;
}

.msg-modal-footer .btn {
    border-radius: 7px;
    font-weight: 600;
    font-size: 0.85rem;
}

.msg-modal-footer .btn-primary {
    background: var(--ap-navy);
    border-color: var(--ap-navy);
}

.msg-modal-footer .btn-primary:hover {
    background: var(--ap-navy-deep);
    border-color: var(--ap-navy-deep);
}

.msg-modal-footer .btn-secondary {
    background: var(--ap-surface);
    border-color: var(--ap-border);
    color: var(--ap-text);
}

.msg-modal-footer .btn-secondary:hover {
    background: var(--ap-bg);
}

.msg-modal-footer .btn-success {
    background: #2E7D4F;
    border-color: #2E7D4F;
}

/* --- Empty State --- */
.empty-state {
    padding: 3rem 1rem;
    text-align: center;
}

.empty-state i { font-size: 2.5rem; color: var(--ap-text-muted); }
.empty-state p { color: var(--ap-text-muted); }

/* --- Pagination --- */
.messages-pagination { margin-top: 1.25rem; }

.messages-pagination .pagination {
    flex-wrap: wrap;
    justify-content: center;
    gap: 0.15rem;
}

.messages-pagination .page-link {
    font-size: 0.8rem;
    padding: 0.4rem 0.7rem;
    min-width: 2.1rem;
    text-align: center;
    color: var(--ap-navy);
    border: 1px solid var(--ap-border);
    border-radius: 7px;
}

.messages-pagination .page-item.active .page-link {
    background: var(--ap-navy);
    border-color: var(--ap-navy);
    color: #fff;
}

.messages-pagination .page-item.disabled .page-link {
    color: var(--ap-text-muted);
}

/* --- Compose Card --- */
.compose-card { margin-top: 1.25rem; }

.compose-card .card-header {
    background: var(--ap-navy) !important;
    color: #fff !important;
    border-radius: 12px 12px 0 0;
    padding: 1rem 1.25rem !important;
}

.compose-card .card-header h5 {
    font-size: 1rem;
    font-weight: 700;
}

.compose-card .card-body { padding: 1.25rem; }

.compose-card .form-label {
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--ap-text);
    margin-bottom: 0.4rem;
}

.compose-card .form-control,
.compose-card .form-select {
    font-size: 0.875rem;
    padding: 0.55rem 0.75rem;
    width: 100%;
    border: 1px solid var(--ap-border);
    border-radius: 8px;
    background: var(--ap-surface);
    color: var(--ap-text);
}

.compose-card .form-control:focus,
.compose-card .form-select:focus {
    border-color: var(--ap-navy);
    box-shadow: 0 0 0 3px rgba(22,50,79,.14);
}

.compose-card textarea {
    resize: vertical;
    min-height: 100px;
    width: 100%;
}

.compose-card .btn-send {
    font-size: 0.9rem;
    padding: 0.65rem;
    width: 100%;
    background: var(--ap-navy);
    border-color: var(--ap-navy);
    border-radius: 8px;
    font-weight: 700;
}

.compose-card .btn-send:hover {
    background: var(--ap-navy-deep);
    border-color: var(--ap-navy-deep);
}

/* Who to contact guide */
.compose-card .bg-body-tertiary {
    background: var(--ap-bg) !important;
    border: 1px solid var(--ap-border) !important;
}

.compose-card .bg-body-tertiary p {
    color: var(--ap-gold) !important;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    font-size: 0.7rem !important;
}

.compose-card .bg-body-tertiary ul li {
    color: var(--ap-text-muted);
}

.compose-card .bg-body-tertiary ul li .text-body {
    color: var(--ap-text) !important;
}

/* =============================================
   1024px — Laptop
   ============================================= */
@media (max-width: 1024px) {
    .messages-page h2 {
        font-size: 1.4rem;
    }

    .messages-card-header { padding: 0.85rem 1.1rem; }
    .messages-card-header h5 { font-size: 1rem; }

    .filter-btn-group .btn {
        font-size: 0.78rem;
        padding: 0.32rem 0.65rem;
    }

    .message-item { padding: 0.9rem; }

    .compose-card .card-body { padding: 1.1rem; }

    /* --- Filter bar: the 4 filter buttons + search input-group no
       longer fit side-by-side once the sidebar + compose column eat
       into the available width. Stack search below the buttons and
       stop the input-group itself from wrapping (Bootstrap's
       .input-group wraps by default, which is what was crushing the
       text input and dropping the Search button to its own line). */
    .messages-filter-bar .row {
        flex-wrap: wrap;
    }

    .messages-filter-bar .col-auto {
        width: 100%;
        margin-bottom: 0.5rem;
    }

    .messages-filter-bar .col {
        width: 100%;
        max-width: 100%;
        flex: 0 0 100%;
    }

    .messages-filter-bar .input-group {
        flex-wrap: nowrap;
    }

    .messages-filter-bar #msgSearchInput {
        min-width: 0;
    }
}

/* =============================================
   768px — Tablet
   ============================================= */
@media (max-width: 768px) {
    .messages-page h2 {
        font-size: 1.3rem;
        margin-bottom: 0.875rem;
    }

    /* Stack inbox + compose vertically */
    .messages-layout {
        display: flex !important;
        flex-direction: column;
        gap: 1rem;
    }

    .messages-layout > div {
        width: 100% !important;
        max-width: 100% !important;
        flex: none !important;
    }

    .messages-card-header {
        padding: 0.75rem 1rem;
        gap: 0.5rem;
    }

    .messages-card-header h5 { font-size: 0.95rem; }

    .filter-btn-group .btn {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }

    .message-item { padding: 0.8rem; }

    .compose-card { margin-top: 0; }

    .msg-modal { max-width: 100%; }
}

/* =============================================
   480px — Large Mobile
   ============================================= */
@media (max-width: 480px) {
    .messages-page h2 {
        font-size: 1.15rem;
        margin-bottom: 0.75rem;
    }

    /* Filter bar: stack tabs above search on small screens */
    .messages-filter-bar .row {
        flex-direction: column;
        gap: 0.4rem;
    }

    .messages-filter-bar .col-auto,
    .messages-filter-bar .col {
        width: 100%;
        max-width: 100%;
    }

    .messages-filter-bar .btn-group {
        display: grid !important;
        grid-template-columns: repeat(4, 1fr);
        gap: 0.25rem;
        width: 100%;
    }

    .messages-filter-bar .btn-group .btn {
        font-size: 0.68rem;
        padding: 0.32rem 0.1rem;
        justify-content: center;
        gap: 0.2rem;
        margin-left: 0 !important;
    }

    .messages-filter-bar .btn-group .btn i {
        font-size: 0.75rem;
    }

    /* Message items */
    .message-item {
        padding: 0.7rem;
        margin-bottom: 0.65rem;
    }

    .message-item .message-meta {
        flex-direction: column;
        gap: 0.2rem;
    }

    .message-item .message-meta small {
        font-size: 0.7rem;
    }

    .message-item .message-subject { font-size: 0.85rem; }

    .message-preview { font-size: 0.8rem; }

    .badge { font-size: 0.65rem !important; }

    /* Pagination */
    .messages-pagination .page-link {
        font-size: 0.72rem;
        padding: 0.3rem 0.5rem;
        min-width: 1.9rem;
    }

    /* Compose */
    .compose-card .card-body { padding: 0.9rem; }

    .compose-card .form-control,
    .compose-card .form-select { font-size: 0.825rem; }

    .compose-card textarea { min-height: 80px; }

    /* Modal: bottom-sheet */
    .msg-modal-overlay {
        padding: 0;
        align-items: flex-end;
    }

    .msg-modal {
        border-radius: 14px 14px 0 0;
        max-height: 85vh;
        max-width: 100%;
    }

    .msg-modal-header { padding: 0.9rem 1rem 0.65rem; }
    .msg-modal-body {
        padding: 0.8rem 1rem;
        font-size: 0.85rem;
    }
    .msg-modal-footer { padding: 0.65rem 1rem; }
}

/* =============================================
   320px — Small Mobile
   ============================================= */
@media (max-width: 320px) {
    .messages-page h2 {
        font-size: 1rem;
        margin-bottom: 0.625rem;
    }

    /* Force page to never exceed viewport width */
    .messages-layout,
    .messages-layout > div,
    .card,
    .card-body,
    .card-header {
        max-width: 100% !important;
        width: 100% !important;
    }

    /* Card header: keep title + filter stacked */
    .messages-card-header {
        flex-direction: column;
        align-items: stretch;
        padding: 0.6rem 0.7rem;
        gap: 0.4rem;
    }

    .messages-card-header h5 {
        font-size: 0.8rem;
    }

    /* Hide envelope icon — too cramped */
    .messages-card-header h5 .bi { display: none; }

    /* Filters: 2×2 grid on 320px */
    .filter-btn-group {
        display: grid !important;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.2rem;
        width: 100%;
    }

    .filter-btn-group .btn {
        font-size: 0.68rem;
        padding: 0.28rem 0.2rem;
        text-align: center;
    }

    /* Message items */
    .message-item {
        padding: 0.55rem;
        margin-bottom: 0.55rem;
        border-radius: 8px;
    }

    .message-item .message-meta strong {
        font-size: 0.75rem;
    }

    .message-item .message-meta small {
        font-size: 0.65rem;
    }

    .message-item .message-subject {
        font-size: 0.78rem;
        margin-top: 0.3rem;
    }

    .message-preview {
        font-size: 0.72rem;
        margin-top: 0.2rem;
    }

    .message-preview .click-hint { display: none; }

    .unread-dot {
        width: 6px;
        height: 6px;
        min-width: 6px;
        margin-right: 4px;
    }

    /* Pagination */
    .messages-pagination .page-link {
        font-size: 0.65rem;
        padding: 0.25rem 0.4rem;
        min-width: 1.6rem;
    }

    /* Compose form */
    .compose-card .card-header {
        padding: 0.55rem 0.7rem !important;
    }

    .compose-card .card-header h5 {
        font-size: 0.82rem;
    }

    .compose-card .card-body {
        padding: 0.65rem;
    }

    .compose-card .form-label {
        font-size: 0.72rem;
        margin-bottom: 0.25rem;
    }

    .compose-card .form-control,
    .compose-card .form-select {
        font-size: 0.75rem;
        padding: 0.4rem 0.55rem;
    }

    .compose-card textarea { min-height: 70px; }

    .compose-card .btn-send {
        font-size: 0.78rem;
        padding: 0.5rem;
    }

    .mb-3 { margin-bottom: 0.55rem !important; }

    /* Empty state */
    .empty-state { padding: 1.75rem 0.5rem; }
    .empty-state i { font-size: 1.75rem; }
    .empty-state p { font-size: 0.78rem; }

    /* Modal: full-height sheet on 320px */
    .msg-modal-overlay { padding: 0; align-items: flex-end; }

    .msg-modal {
        border-radius: 12px 12px 0 0;
        max-height: 92vh;
        max-width: 100%;
    }

    .msg-modal-header {
        padding: 0.7rem 0.8rem 0.55rem;
    }

    .msg-modal-header .modal-sender { font-size: 0.72rem; }

    .msg-modal-header .modal-subject { font-size: 0.88rem; }

    .msg-modal-close { font-size: 1.25rem; }

    .msg-modal-body {
        padding: 0.7rem 0.8rem;
        font-size: 0.78rem;
    }

    .msg-modal-footer {
        padding: 0.55rem 0.8rem;
        gap: 0.35rem;
    }

    .msg-modal-footer .btn {
        font-size: 0.72rem;
        padding: 0.3rem 0.65rem;
    }
}

/* Search input active glow when a query is set */
#msgSearchInput.border-primary {
    box-shadow: 0 0 0 0.15rem rgba(13, 110, 253, 0.15);
    border-color: #0d6efd !important;
}
</style>

<div class="messages-page">
    <h2 class="mb-4 fw-bold text-body"><?php echo _mgt('heading'); ?></h2>
    
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?php echo _mgt('msg_sent_ok'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Two-column layout on desktop; stacks on mobile via .messages-layout -->
    <div class="row messages-layout">

        <!-- ── Inbox / Sent Column ── -->
        <div class="col-md-8">
            <div class="card">

                <!-- ── Filter bar (admin style) ── -->
                <div class="card-body border-bottom py-2 px-3 messages-filter-bar">
                    <div class="row g-2 align-items-start">
                        <div class="col-auto">
                            <div class="btn-group btn-group-sm" role="group" aria-label="Message filters">
                                <?php
                                $tabs = [
                                    'all'    => [_mgt('filter_all'),    'bi-inbox',         'btn-primary',          'btn-outline-secondary'],
                                    'unread' => [_mgt('filter_unread'), 'bi-envelope',      'btn-primary',          'btn-outline-secondary'],
                                    'read'   => [_mgt('filter_read'),   'bi-envelope-open', 'btn-secondary',        'btn-outline-secondary'],
                                    'sent'   => [_mgt('filter_sent'),   'bi-send',          'btn-outline-primary',  'btn-outline-secondary'],
                                ];
                                foreach ($tabs as $key => [$label, $icon, $activeCls, $outlineCls]):
                                    $isActive = ($filter === $key);
                                    $href = '?filter=' . $key
                                        . ($search        ? '&search='         . urlencode($search)        : '')
                                        . ($applicationId ? '&application_id=' . urlencode($applicationId) : '');
                                ?>
                                <a href="<?php echo $href; ?>"
                                   class="btn <?php echo $isActive ? $activeCls : $outlineCls; ?>">
                                    <i class="bi <?php echo $icon; ?>"></i>
                                    <?php echo $label; ?>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col">
                            <form method="GET" action="messages.php" role="search">
                                <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                                <?php if ($applicationId): ?>
                                    <input type="hidden" name="application_id" value="<?php echo htmlspecialchars($applicationId); ?>">
                                <?php endif; ?>
                                <!-- page intentionally omitted — search always resets to page 1 -->
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text border-end-0">
                                        <i class="bi bi-search text-muted"></i>
                                    </span>
                                    <input type="text"
                                           name="search"
                                           id="msgSearchInput"
                                           class="form-control border-start-0 bg-body text-body<?php echo $search ? ' border-primary' : ''; ?>"
                                           placeholder="Search sender, subject or message..."
                                           value="<?php echo htmlspecialchars($search); ?>"
                                           autocomplete="off"
                                           aria-label="Search messages">
                                    <?php if ($search): ?>
                                        <a href="?filter=<?php echo urlencode($filter); ?><?php echo $applicationId ? '&application_id='.urlencode($applicationId) : ''; ?>"
                                           class="btn btn-outline-secondary" aria-label="Clear search">
                                            <i class="bi bi-x-lg"></i>
                                        </a>
                                    <?php endif; ?>
                                    <button class="btn btn-primary" type="submit" id="msgSearchBtn">
                                        <i class="bi bi-search"></i> Search
                                    </button>
                                </div>
                            </form>
                            <?php if ($search): ?>
                                <small class="text-muted ps-1">
                                    <?php echo $totalMessages; ?> result<?php echo $totalMessages !== 1 ? 's' : ''; ?> for &ldquo;<?php echo htmlspecialchars($search); ?>&rdquo;
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <?php if (empty($messages)): ?>
                        <div class="empty-state">
                            <?php if ($search): ?>
                                <i class="bi bi-search text-muted opacity-50"></i>
                                <p class="text-muted mt-2 mb-0">No messages match your search.</p>
                                <a href="?filter=<?php echo urlencode($filter); ?><?php echo $applicationId ? '&application_id='.urlencode($applicationId) : ''; ?>"
                                   class="btn btn-sm btn-outline-secondary mt-3">
                                    <i class="bi bi-x me-1"></i>Clear search
                                </a>
                            <?php else: ?>
                                <i class="bi bi-chat-left-dots text-muted opacity-50"></i>
                                <p class="text-muted mt-2 mb-0"><?php echo _mgt('no_messages'); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>

                        <?php foreach ($messages as $msg): ?>
                            <?php
                                $isUnread   = (!$msg['is_read'] && $filter !== 'sent');
                                $msgId      = $msg['id'];
                                $fullText   = htmlspecialchars($msg['message']);
                                $subject    = htmlspecialchars($msg['subject'] ?: _mgt('no_subject'));
                                $appNum     = htmlspecialchars($msg['application_number'] ?? '');
                                $datetime   = Helper::formatDateTime($msg['created_at']);
                                $markReadUrl = '?' . http_build_query(array_filter(['mark_read' => $msgId, 'filter' => $filter, 'page' => $page, 'search' => $search]));

                                // ── Permit PDF detection ──────────────────────────────────────────
                                $appId    = $msg['application_id'] ?? null;
                                $safeNo   = preg_replace('/[^A-Za-z0-9\-_]/', '_', $msg['application_number'] ?? '');
                                $pdfFile  = __DIR__ . '/../uploads/permits/Locational_Clearance_' . $safeNo . '.pdf';
                                $hasPermit = $appId && !empty($safeNo) && file_exists($pdfFile);
                                $permitUrl = $hasPermit
                                    ? '/lgu-urban-planning/modules/PermitProcessing/generate_permit_pdf.php?id=' . (int)$appId
                                    : '';

                                if ($filter === 'sent') {
                                    $senderLabel = _mgt('lbl_to') . htmlspecialchars($msg['receiver_name'] ?? 'Officer');
                                } else {
                                    $senderLabel = _mgt('lbl_from') . htmlspecialchars($msg['sender_first_name'] . ' ' . $msg['sender_last_name']);
                                }
                            ?>

                            <!-- Clickable preview row -->
                            <div class="message-item rounded <?php echo $isUnread ? 'bg-body-tertiary' : 'bg-body'; ?>"
                                 data-search-sender="<?php echo strtolower(htmlspecialchars(strip_tags($senderLabel))); ?>"
                                 data-search-subject="<?php echo strtolower(htmlspecialchars($msg['subject'] ?: '')); ?>"
                                 data-search-body="<?php echo strtolower(htmlspecialchars(mb_substr(strip_tags($msg['message']), 0, 300))); ?>"
                                 style="border-left: 5px solid <?php echo $isUnread ? '#0d6efd' : 'var(--bs-border-color)'; ?> !important;"
                                 onclick="openMsgModal(<?php echo $msgId; ?>)"
                                 role="button"
                                 tabindex="0"
                                 onkeydown="if(event.key==='Enter'||event.key===' ')openMsgModal(<?php echo $msgId; ?>)"
                                 aria-label="Open message: <?php echo $subject; ?>">

                                <div class="message-meta">
                                    <div class="d-flex align-items-center">
                                        <?php if ($isUnread): ?><span class="unread-dot"></span><?php endif; ?>
                                        <strong class="text-body"><?php echo $senderLabel; ?></strong>
                                        <?php if ($appNum): ?>
                                            <span class="badge bg-info text-dark ms-1"><?php echo $appNum; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted"><?php echo $datetime; ?></small>
                                </div>

                                <div class="message-subject fw-bold text-body"><?php echo $subject; ?></div>

                                <!-- Truncated preview only -->
                                <div class="message-preview">
                                    <?php echo htmlspecialchars(mb_substr(strip_tags($msg['message']), 0, 120)); ?>
                                    <span class="click-hint"><i class="bi bi-eye me-1"></i><?php echo _mgt('read_more'); ?></span>
                                </div>
                            </div>

                            <!-- Full message modal (hidden) -->
                            <div class="msg-modal-overlay" id="modal-<?php echo $msgId; ?>" onclick="closeMsgModal(event, <?php echo $msgId; ?>)" role="dialog" aria-modal="true" aria-label="Full message">
                                <div class="msg-modal">
                                    <div class="msg-modal-header">
                                        <div class="modal-title-group">
                                            <div class="modal-sender text-muted">
                                                <?php echo $senderLabel; ?>
                                                <?php if ($appNum): ?>
                                                    <span class="badge bg-info text-dark ms-1"><?php echo $appNum; ?></span>
                                                <?php endif; ?>
                                                <span class="ms-2">&bull; <?php echo $datetime; ?></span>
                                            </div>
                                            <div class="modal-subject"><?php echo $subject; ?></div>
                                        </div>
                                        <button class="msg-modal-close" onclick="closeById(<?php echo $msgId; ?>)" aria-label="Close">&times;</button>
                                    </div>

                                    <div class="msg-modal-body"><?php echo nl2br(htmlspecialchars(preg_replace("/(\r?\n){3,}/", "\n\n", $msg['message']))); ?></div>

                                    <div class="msg-modal-footer">
                                        <?php if ($hasPermit): ?>
                                            <a href="<?php echo $permitUrl; ?>" target="_blank"
                                               class="btn btn-sm btn-success">
                                                <i class="bi bi-file-earmark-pdf me-1"></i>
                                                <?php echo _mgt('btn_download_permit'); ?>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($isUnread): ?>
                                            <a href="<?php echo $markReadUrl; ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-check2-all me-1"></i><?php echo _mgt('btn_mark_read'); ?>
                                            </a>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-secondary" onclick="closeById(<?php echo $msgId; ?>)">
                                            <i class="bi bi-x me-1"></i><?php echo _mgt('btn_close'); ?>
                                        </button>
                                    </div>
                                </div>
                            </div>

                        <?php endforeach; ?>

                        <?php if ($totalPages > 1):
                            $paginationBase = 'filter=' . urlencode($filter)
                                . ($search        ? '&search='         . urlencode($search)        : '')
                                . ($applicationId ? '&application_id=' . urlencode($applicationId) : '');
                        ?>
                        <nav class="messages-pagination mt-4" aria-label="Message pages">
                            <ul class="pagination pagination-sm justify-content-center">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link shadow-none" href="?<?php echo $paginationBase; ?>&page=<?php echo $page - 1; ?>" aria-label="Previous">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                </li>
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                        <a class="page-link shadow-none" href="?<?php echo $paginationBase; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link shadow-none" href="?<?php echo $paginationBase; ?>&page=<?php echo $page + 1; ?>" aria-label="Next">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>

                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ── Compose Column ── -->
        <div class="col-md-4 mt-4 mt-md-0">
            <div class="card compose-card">
                <div class="card-header bg-primary text-white py-3 border-0">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2"></i><?php echo _mgt('compose_title'); ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-body"><?php echo _mgt('lbl_to_officer'); ?></label>
                            <select class="form-select bg-body text-body border-secondary-subtle" name="receiver_id" required>
                                <option value=""><?php echo _mgt('opt_select_officer'); ?></option>
                                <?php
                                $db = Database::getInstance();
                                $officers = $db->fetchAll("SELECT id, first_name, last_name, role FROM users WHERE role IN ('super_admin', 'admin', 'zoning_officer', 'building_official', 'assessor') AND is_active = 1 ORDER BY role, first_name, last_name");
                                foreach ($officers as $off): ?>
                                    <option value="<?php echo $off['id']; ?>"><?php echo htmlspecialchars($off['first_name'].' '.$off['last_name'].' ('.Helper::getRoleName($off['role']).')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-body"><?php echo _mgt('lbl_subject'); ?></label>
                            <input type="text" class="form-control bg-body text-body border-secondary-subtle" name="subject" placeholder="<?php echo _mgt('ph_subject'); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-body"><?php echo _mgt('lbl_message'); ?></label>
                            <textarea class="form-control bg-body text-body border-secondary-subtle" name="message" rows="5" required placeholder="<?php echo _mgt('ph_message'); ?>"></textarea>
                        </div>
                        <button type="submit" name="send_message" class="btn btn-primary w-100 fw-bold btn-send">
                            <i class="bi bi-send me-2"></i><?php echo _mgt('btn_send'); ?>
                        </button>
                    </form>

                    <!-- Who to contact guide -->
                    <div class="mt-3 p-3 rounded-3 border border-secondary-subtle bg-body-tertiary">
                        <p class="small fw-semibold text-muted mb-2"><i class="bi bi-info-circle me-1"></i>Who should I message?</p>
                        <ul class="list-unstyled mb-0 small text-muted" style="line-height:1.8;">
                            <li><span class="fw-semibold text-body">🏙️ Zoning Officer</span> — Zoning compliance, land use</li>
                            <li><span class="fw-semibold text-body">🏗️ Building Official</span> — Permits, inspections</li>
                            <li><span class="fw-semibold text-body">📋 Assessor</span> — Property &amp; tax assessment</li>
                            <li><span class="fw-semibold text-body">📁 Admin</span> — General inquiries, document follow-ups</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /.messages-layout -->
</div><!-- /.messages-page -->

<script>
function openMsgModal(id) {
    const overlay = document.getElementById('modal-' + id);
    if (!overlay) return;
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
    // Focus the close button for accessibility
    overlay.querySelector('.msg-modal-close')?.focus();
}

function closeById(id) {
    const overlay = document.getElementById('modal-' + id);
    if (!overlay) return;
    overlay.classList.remove('active');
    document.body.style.overflow = '';
}

function closeMsgModal(event, id) {
    // Only close if clicking the backdrop, not the modal itself
    if (event.target === event.currentTarget) {
        closeById(id);
    }
}

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.msg-modal-overlay.active').forEach(function(overlay) {
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        });
    }
});
// ── Search: Escape clears, auto-focus cursor to end ──────────────────────
(function () {
    const input = document.getElementById('msgSearchInput');
    if (!input) return;
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            input.value = '';
            input.closest('form').submit();
        }
    });
    if (input.value) {
        input.focus();
        const len = input.value.length;
        input.setSelectionRange(len, len);
    }
})();
</script>

<?php include __DIR__ . '/../user/footer.php'; ?>