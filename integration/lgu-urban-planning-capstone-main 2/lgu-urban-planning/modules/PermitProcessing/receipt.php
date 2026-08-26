<?php
/**
 * receipt.php
 */

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/receipt_helper.php';

$auth = new Auth();
$auth->requireRole(['applicant']);

$db = Database::getInstance();

$applicationId = (int) ($_GET['id'] ?? 0);
$applicantId   = (int) ($_SESSION['user_id'] ?? 0);

if (!$applicationId) {
    http_response_code(400);
    die('Missing application ID.');
}

// Scoped to the logged-in applicant — mirrors the ownership check in pay.php.
$row = $db->fetchOne(
    "SELECT p.*, a.application_number, a.project_name, a.barangay, a.district, a.applicant_id,
            u.first_name AS applicant_first_name, u.last_name AS applicant_last_name, u.email AS applicant_email
     FROM payments p
     JOIN applications a ON a.id = p.application_id
     JOIN users u ON u.id = a.applicant_id
     WHERE p.application_id = ? AND a.applicant_id = ?
     ORDER BY p.id DESC LIMIT 1",
    [$applicationId, $applicantId]
);

if (!$row || $row['status'] !== 'paid') {
    http_response_code(403);
    die('Receipt not available. This application does not have a completed payment.');
}


$payment     = $row;
$application = $row;

try {
    $receiptInfo = buildReceiptPdf($payment, $application);
} catch (\Throwable $e) {
    http_response_code(500);
    die('Could not generate receipt: ' . htmlspecialchars($e->getMessage()));
}

$savePath = $receiptInfo['path'];
$filename = $receiptInfo['filename'];

if (!file_exists($savePath)) {
    http_response_code(500);
    die('Receipt file could not be created.');
}

header('Content-Type: application/pdf');
if (!empty($_GET['download'])) {
    header('Content-Disposition: attachment; filename="' . $filename . '"');
} else {
    header('Content-Disposition: inline; filename="' . $filename . '"');
}
header('Cache-Control: private, max-age=0, must-revalidate');
header('Content-Length: ' . filesize($savePath));
readfile($savePath);
exit;
