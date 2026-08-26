<?php

// Document Controller

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helper.php';

class DocumentController {
    private $db;
    private $auth;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->auth = new Auth();
    }
    
    public function getDocuments($applicationId) {
        $this->auth->requireLogin();
        
        $application = $this->db->fetchOne(
            "SELECT applicant_id, assigned_officer_id FROM applications WHERE id = ?",
            [$applicationId]
        );
        
        if (!$application) return [];
        
        $hasAccess = (in_array($_SESSION['role'], ['admin', 'super_admin', 'zoning_officer', 'building_official', 'assessor']) ||
                      $application['applicant_id'] == $_SESSION['user_id'] ||
                      $application['assigned_officer_id'] == $_SESSION['user_id']);
        
        if (!$hasAccess) return [];
        
        return $this->db->fetchAll(
            "SELECT ad.*, u.first_name, u.last_name 
             FROM application_documents ad
             LEFT JOIN users u ON ad.uploaded_by = u.id
             WHERE ad.application_id = ? 
             ORDER BY ad.created_at DESC",
            [$applicationId]
        );
    }
    
    // --- Inspector Workload Snapshot (for dashboard chart, independent of selected report/year) ---
    public function getInspectorWorkloadSnapshot() {
        $this->auth->requirePermission('generate_reports');

        $sql = "SELECT 
                    CONCAT(u.first_name, ' ', u.last_name) AS inspector_name,
                    COUNT(i.id) AS total_inspections
                FROM users u
                INNER JOIN inspections i ON u.id = i.inspector_id
                GROUP BY u.id, u.first_name, u.last_name
                ORDER BY COUNT(i.id) DESC
                LIMIT 8";

        return $this->db->fetchAll($sql) ?: [];
    }

    // --- Export Report to file (CSV) ---
    public function exportReport($report, $format = 'csv') {
        $this->auth->requirePermission('generate_reports');

        if (empty($report) || !is_array($report)) {
            return ['success' => false, 'error' => 'No report data to export.'];
        }

        if ($format !== 'csv') {
            return ['success' => false, 'error' => 'Unsupported export format: ' . htmlspecialchars($format)];
        }

        $exportDir = realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'reports';
        if (!is_dir($exportDir) && !mkdir($exportDir, 0755, true) && !is_dir($exportDir)) {
            return ['success' => false, 'error' => 'Unable to create export directory.'];
        }

        $fileName = 'report_' . date('Ymd_His') . '_' . substr(uniqid(), -6) . '.csv';
        $fullPath = $exportDir . DIRECTORY_SEPARATOR . $fileName;

        $fp = fopen($fullPath, 'w');
        if ($fp === false) {
            return ['success' => false, 'error' => 'Unable to create export file.'];
        }

        // Header row from the first record's column names
        fputcsv($fp, array_keys(reset($report)));

        foreach ($report as $row) {
            fputcsv($fp, $row);
        }

        fclose($fp);

        return [
            'success'   => true,
            'file_path' => $fullPath, // absolute path — no need to combine with config upload_path
            'file_name' => $fileName
        ];
    }

    public function generateReport($reportType, $filters = []) {
        $this->auth->requirePermission('generate_reports');

        // Restrict sensitive report types to admin/super_admin only
        $adminOnlyReports = ['audit_summary', 'user_growth', 'inspector_performance'];
        if (in_array($reportType, $adminOnlyReports) && !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
            return ['success' => false, 'error' => 'You do not have permission to generate this report.'];
        }

        $report = null;
        
        switch ($reportType) {
            case 'applications_summary':
                $report = $this->generateApplicationsSummary($filters);
                break;
            case 'zoning_compliance':
                $report = $this->generateZoningComplianceReport($filters);
                break;
            case 'inspector_performance':
                $report = $this->generateInspectorPerformance($filters);
                break;
            case 'audit_summary':
                $report = $this->generateAuditSummary($filters);
                break;
            case 'user_growth':
                $report = $this->generateUserGrowth($filters);
                break;
            case 'permits_issued':
                $report = $this->generatePermitsIssuedReport($filters);
                break;
            case 'monthly_analytics':
                $report = $this->generateMonthlyAnalytics($filters);
                break;
            default:
                return ['success' => false, 'error' => 'Invalid report type selected'];
        }
        
        if ($report && !empty($report['data'])) {
            $this->db->query(
                "INSERT INTO reports (report_type, report_name, generated_by, parameters) VALUES (?, ?, ?, ?)",
                [$reportType, $report['name'], $_SESSION['user_id'], json_encode($filters)]
            );
        }
        
        return $report;
    }

    // --- Zoning Compliance List ---
private function generateZoningComplianceReport($filters) {
    $year = $filters['year'] ?? date('Y');
    
    // Gagamitin natin ang mga columns na binigay mo: 
    // zoning_type, compliance_status, technical_analysis
    $sql = "SELECT 
                a.application_number AS 'App #', 
                a.project_name AS 'Project Name', 
                a.barangay AS 'Barangay', 
                zcc.zoning_type AS 'Zoning Classification', 
                zcc.compliance_status AS 'Compliance Result',
                zcc.checked_at AS 'Date Checked'
            FROM applications a
            INNER JOIN zoning_compliance_checks zcc ON a.id = zcc.application_id
            WHERE YEAR(zcc.checked_at) = ?
            ORDER BY zcc.checked_at DESC";
            
    $data = $this->db->fetchAll($sql, [$year]);
    
    return [
        'name' => "Zoning Compliance Report ($year)", 
        'data' => $data ?: []
    ];
}

    // --- Inspector Performance ---
private function generateInspectorPerformance($filters) {
    $year = $filters['year'] ?? date('Y');
    
    // Gagamitin ang 'inspections' table at i-join sa 'users' para sa pangalan
    $sql = "SELECT 
                CONCAT(u.first_name, ' ', u.last_name) AS 'Inspector Name',
                COUNT(i.id) AS 'Total Tasks',
                SUM(CASE WHEN i.status = 'completed' THEN 1 ELSE 0 END) AS 'Completed',
                SUM(CASE WHEN i.status = 'scheduled' THEN 1 ELSE 0 END) AS 'Pending/Scheduled',
                MAX(i.scheduled_at) AS 'Last Inspection'
            FROM users u
            INNER JOIN inspections i ON u.id = i.inspector_id
            WHERE YEAR(i.scheduled_at) = ?
            GROUP BY u.id, u.first_name, u.last_name
            ORDER BY COUNT(i.id) DESC";
            
    $data = $this->db->fetchAll($sql, [$year]);
    
    return [
        'name' => "Inspector Workload Summary ($year)", 
        'data' => $data ?: []
    ];
}

    // --- Audit Summary ---
private function generateAuditSummary($filters) {
    $sql = "SELECT u.username, a.action, a.entity_type, a.created_at 
            FROM audit_logs a
            LEFT JOIN users u ON a.user_id = u.id 
            ORDER BY a.created_at DESC LIMIT 100";
    
    return [
        'name' => 'System Audit Summary (Latest 100)', 
        'data' => $this->db->fetchAll($sql)
    ];
}
    
    // --- User Growth ---
    private function generateUserGrowth($filters) {
        $year = $filters['year'] ?? date('Y');
        $sql = "SELECT MONTHNAME(created_at) as month, COUNT(id) as registrations 
                FROM users WHERE YEAR(created_at) = ? 
                GROUP BY MONTH(created_at)";
        return ['name' => "User Growth Report ($year)", 'data' => $this->db->fetchAll($sql, [$year])];
    }

private function generateApplicationsSummary($filters) {
    $year = $filters['year'] ?? date('Y');
    $sql = "SELECT application_number, project_name, status, barangay, created_at 
            FROM applications WHERE YEAR(created_at) = ?";
    return [
        'name' => "Applications Summary ($year)", 
        'data' => $this->db->fetchAll($sql, [$year])
    ];
}

private function generatePermitsIssuedReport($filters) {
    $year = $filters['year'] ?? date('Y');
    
    // Ginawa nating dynamic ang YEAR para sumunod sa filter sa sidebar
    $sql = "SELECT 
                application_number as permit_id, 
                project_name, 
                barangay, 
                updated_at as date_issued,
                status
            FROM applications 
            WHERE status = 'approved' 
            AND YEAR(updated_at) = ?";
            
    $data = $this->db->fetchAll($sql, [$year]);
    
    return [
        'name' => "Permits Issued Report ($year)", 
        'data' => $data ?: [] 
    ];
}

    private function generateMonthlyAnalytics($filters) {
        $year = $filters['year'] ?? date('Y');
        $sql = "SELECT MONTHNAME(created_at) as month, COUNT(*) as total_count 
                FROM applications WHERE YEAR(created_at) = ? 
                GROUP BY MONTH(created_at)";
        return ['name' => "Monthly Analytics ($year)", 'data' => $this->db->fetchAll($sql, [$year])];
    }

    // Download Document
    public function downloadDocument($documentId) {
        $this->auth->requireLogin();

        $document = $this->db->fetchOne(
            "SELECT ad.*, a.applicant_id, a.assigned_officer_id 
             FROM application_documents ad
             JOIN applications a ON ad.application_id = a.id
             WHERE ad.id = ?",
            [$documentId]
        );

        if (!$document) {
            die("Error: Document record not found.");
        }

        // Security Check
        $hasAccess = (in_array($_SESSION['role'], ['admin', 'super_admin', 'zoning_officer', 'building_official', 'assessor']) ||
                      $document['applicant_id'] == $_SESSION['user_id'] ||
                      $document['assigned_officer_id'] == $_SESSION['user_id']);

        if (!$hasAccess) {
            die("Error: Unauthorized access.");
        }

        // --- DYNAMIC PATH REPAIR ---
        $basePath = realpath(__DIR__ . '/../../');
        

        $fileNameOnly = basename($document['file_path']); 
        $filePath = $basePath . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'documents' . DIRECTORY_SEPARATOR . $fileNameOnly;

    if (file_exists($filePath)) {
            if (ob_get_level()) ob_end_clean();

            $isView = isset($_GET['view']) && $_GET['view'] == 1;
            $disposition = $isView ? 'inline' : 'attachment';
            
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $filePath);
            finfo_close($finfo);

            header('Content-Description: File Transfer');
            header('Content-Type: ' . $mimeType); 
            header('Content-Disposition: ' . $disposition . '; filename="' . basename($document['file_name']) . '"');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filePath));
            
            readfile($filePath);
            exit;
        } else {
            die("Error: File not found on server.");
        }
    }
}