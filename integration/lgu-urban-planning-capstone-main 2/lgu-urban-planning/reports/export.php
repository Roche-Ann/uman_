<?php
/**
 * Export Report
 */

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../modules/DocumentReportManagement/DocumentController.php';

$auth = new Auth();
$auth->requirePermission('generate_reports');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_data'])) {
    $documentController = new DocumentController();
    $report = json_decode($_POST['report_data'], true);
    $format = $_POST['export_format'] ?? 'csv';
    
    $result = $documentController->exportReport($report, $format);
    
    if ($result['success']) {
        $filePath = $result['file_path'];
        
        if (file_exists($filePath)) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $result['file_name'] . '"');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            @unlink($filePath); // clean up the temp export file after sending
            exit;
        }
    }
}

header('Location: /lgu-urban-planning/reports/index.php');
exit;