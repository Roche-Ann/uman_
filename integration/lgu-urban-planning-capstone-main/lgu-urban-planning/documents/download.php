<?php

/* Download */

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../modules/DocumentReportManagement/DocumentController.php';

$auth = new Auth();
$auth->requireLogin();

$documentController = new DocumentController();
$documentId = $_GET['id'] ?? 0;
$isViewMode = isset($_GET['view']) && $_GET['view'] == '1';

$result = $documentController->downloadDocument($documentId);

if ($result['success']) {
    $filePath = $result['file_path'];

    if (file_exists($filePath)) {
        header('Content-Type: ' . $result['mime_type']);
        
        if ($isViewMode) {

            header('Content-Disposition: inline; filename="' . $result['file_name'] . '"');
        } else {
            header('Content-Disposition: attachment; filename="' . $result['file_name'] . '"');
        }

        header('Content-Length: ' . filesize($filePath));
        header('Pragma: public');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        
        readfile($filePath);
        exit;
    } else {
        $errorMsg = "File not found on server.";
    }
} else {
    $errorMsg = $result['error'] ?? "Unable to access document.";
}

// Error Handling
$role = $_SESSION['role'] ?? 'applicant';
$dashboardUrl = ($role === 'applicant') ? '/lgu-urban-planning/user/index.php' : '/lgu-urban-planning/admin/index.php';
header('Location: ' . $dashboardUrl . '?error=' . urlencode($errorMsg));
exit;