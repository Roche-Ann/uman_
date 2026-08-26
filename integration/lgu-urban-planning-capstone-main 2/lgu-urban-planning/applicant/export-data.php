<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';

$auth = new Auth();
$auth->requireLogin();

if (!isset($_SESSION['export_initiated']) || (time() - $_SESSION['export_initiated']) > 60) {
    header('Location: settings.php');
    exit();
}

$exportReason = $_SESSION['export_reason'] ?? 'N/A';
$userId       = $_SESSION['user_id'];

unset($_SESSION['export_reason'], $_SESSION['export_initiated']);

$db  = Database::getInstance();
$pdo = $db->getConnection();

// Fetch user profile (safe columns only)
$userStmt = $pdo->prepare("SELECT id, username, email, first_name, last_name, phone, birth_date, street, barangay, city, created_at FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

// Fetch applications
$appStmt = $pdo->prepare("SELECT application_number, project_name, project_type, project_description, lot_number, barangay, street, block, latitude, longitude, status, submitted_at, created_at FROM applications WHERE applicant_id = ?");
$appStmt->execute([$userId]);
$applications = $appStmt->fetchAll(PDO::FETCH_ASSOC);

// Log the export
$log = $pdo->prepare(
    "INSERT INTO audit_logs (user_id, action, details, ip_address, created_at)
     VALUES (?, 'Data Export Completed', ?, ?, NOW())"
);
$log->execute([$userId, "Reason: $exportReason", $_SERVER['REMOTE_ADDR'] ?? '']);

// Send download headers — no output before this!
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="my-data-' . date('Y-m-d') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// Section 1: User Profile
fputcsv($out, ['=== ACCOUNT INFORMATION ===']);
fputcsv($out, array_keys($user));
fputcsv($out, array_values($user));

fputcsv($out, []); // blank row

// Section 2: Applications
fputcsv($out, ['=== APPLICATIONS ===']);
if (!empty($applications)) {
    fputcsv($out, array_keys($applications[0]));
    foreach ($applications as $row) {
        fputcsv($out, $row);
    }
} else {
    fputcsv($out, ['No applications found.']);
}

fclose($out);
exit();