<?php

/**
 * Permit Controller
 * Updated with Overdue Filtering Logic and Manual Admin Submission Logic
 * Fixed: GIS Data fetching explicit columns
 */

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helper.php';

class PermitController {
    private $db;
    private $auth;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->auth = new Auth();
    }
    
    public function getApplications($filters = []) {
        $this->auth->requireRole(['admin', 'super_admin', 'zoning_officer', 'building_official', 'assessor', 'inspector']);
        
        $sql = "SELECT a.*, 
                       u.first_name as applicant_first_name, u.last_name as applicant_last_name,
                       u.email as applicant_email,
                       ao.first_name as officer_first_name, ao.last_name as officer_last_name,
                       (SELECT COUNT(*) FROM application_documents WHERE application_id = a.id) as document_count
                FROM applications a
                LEFT JOIN users u ON a.applicant_id = u.id
                LEFT JOIN users ao ON a.assigned_officer_id = ao.id
                WHERE 1=1";
        $params = [];
        
        // --- START OVERDUE FILTER LOGIC ---
        if (isset($filters['filter']) && $filters['filter'] === 'overdue') {
            // Applications older than 3 days and NOT yet final (approved/rejected/cancelled)
            $sql .= " AND a.created_at <= DATE_SUB(NOW(), INTERVAL 3 DAY) 
                      AND a.status NOT IN ('approved', 'rejected', 'cancelled')";
        } 
        // Normal Status Filter (if not overdue)
        elseif (isset($filters['status']) && !empty($filters['status'])) {
            $sql .= " AND a.status = ?";
            $params[] = $filters['status'];
        }
        // --- END OVERDUE FILTER LOGIC ---
        
        if (isset($filters['assigned_officer_id']) && !empty($filters['assigned_officer_id'])) {
            $sql .= " AND a.assigned_officer_id = ?";
            $params[] = $filters['assigned_officer_id'];
        }
        
        if (isset($filters['search']) && !empty($filters['search'])) {
            $sql .= " AND (a.application_number LIKE ? OR a.project_name LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
            $search = "%{$filters['search']}%";
            $params = array_merge($params, [$search, $search, $search, $search]);
        }
        
        $sql .= " ORDER BY a.created_at DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getApplicationDetails($applicationId) {
        $this->auth->requireRole(['admin', 'super_admin', 'zoning_officer', 'building_official', 'assessor', 'inspector']);
        
        // FIXED: Removed explicitly named missing columns to prevent PDOException
        $application = $this->db->fetchOne(
            "SELECT a.*, 
                    u.first_name as applicant_first_name, u.last_name as applicant_last_name,
                    u.email as applicant_email, u.phone as applicant_phone,
                    ao.first_name as officer_first_name, ao.last_name as officer_last_name
             FROM applications a
             LEFT JOIN users u ON a.applicant_id = u.id
             LEFT JOIN users ao ON a.assigned_officer_id = ao.id
             WHERE a.id = ?",
            [$applicationId]
        );
        
        if (!$application) {
            return null;
        }
        
        // Attach impact assessment summary if exists
        $assessment = $this->getImpactAssessment($applicationId);
        if ($assessment) {
            $application['impact_traffic_score'] = $assessment['traffic_score'];
            $application['impact_traffic_flag'] = $assessment['traffic_flag'];
            $application['impact_energy_score'] = $assessment['energy_score'];
            $application['impact_energy_flag'] = $assessment['energy_flag'];
            $application['impact_notes'] = $assessment['notes'];
        }
        
        $application['documents'] = $this->db->fetchAll(
            "SELECT ad.*, u.first_name, u.last_name 
             FROM application_documents ad
             LEFT JOIN users u ON ad.uploaded_by = u.id
             WHERE ad.application_id = ? 
             ORDER BY ad.created_at DESC",
            [$applicationId]
        );
        
        $application['status_history'] = $this->db->fetchAll(
            "SELECT ash.*, u.first_name, u.last_name, u.role 
             FROM application_status_history ash 
             LEFT JOIN users u ON ash.changed_by = u.id 
             WHERE ash.application_id = ? 
             ORDER BY ash.created_at ASC",
            [$applicationId]
        );
        
        return $application;
    }
    
    public function updateApplicationStatus($applicationId, $status, $remarks = null, $assignOfficerId = null) {
        $this->auth->requireRole(['admin', 'super_admin', 'zoning_officer', 'building_official', 'assessor']);
        
        // Guard: if approving, ensure impact assessment not flagged high
        if ($status === 'approved') {
            $assessment = $this->getImpactAssessment($applicationId);
            if ($assessment && (($assessment['traffic_flag'] ?? 'ok') === 'high' || ($assessment['energy_flag'] ?? 'ok') === 'high')) {
                return false;
            }
        }
        
        $updateFields = ["status = ?"];
        $params = [$status];
        
        if ($assignOfficerId) {
            $updateFields[] = "assigned_officer_id = ?";
            $params[] = $assignOfficerId;
        }
        
        // Get current application to check for manual/admin submission
        $currentApp = $this->db->fetchOne("SELECT applicant_id, record_type FROM applications WHERE id = ?", [$applicationId]);
        
        // Custom logic for manual admin submission history
        if ($status === 'submitted' && ($currentApp['record_type'] ?? '') === 'walk-in') {
            $remarks = "Application submitted by admin for user #" . $currentApp['applicant_id'];
        }
        
        $params[] = $applicationId;
        
        $this->db->query(
            "UPDATE applications SET " . implode(', ', $updateFields) . " WHERE id = ?",
            $params
        );
        
        // Add status history
        $this->db->query(
            "INSERT INTO application_status_history (application_id, status, remarks, changed_by) 
             VALUES (?, ?, ?, ?)",
            [$applicationId, $status, $remarks, $_SESSION['user_id']]
        );
        
        if ($currentApp) {
            $this->sendNotification(
                $currentApp['applicant_id'],
                "Application Status Updated",
                "Your application status has been updated to: " . ucfirst(str_replace('_', ' ', $status)),
                $applicationId
            );
        }
        
        $this->auth->logActivity($_SESSION['user_id'], 'update_application_status', 'application', $applicationId, "Updated status to: $status");
        
        return true;
    }
    
    public function addRemarks($applicationId, $remarks) {
        $this->auth->requireRole(['admin', 'super_admin', 'zoning_officer', 'building_official', 'assessor', 'inspector']);
        
        $this->db->query(
            "INSERT INTO application_status_history (application_id, status, remarks, changed_by) 
             VALUES (?, (SELECT status FROM applications WHERE id = ?), ?, ?)",
            [$applicationId, $applicationId, $remarks, $_SESSION['user_id']]
        );
        
        $this->auth->logActivity($_SESSION['user_id'], 'add_remarks', 'application', $applicationId, "Added remarks");
        
        return true;
    }
    
    public function generatePermit($applicationId) {
        $this->auth->requireRole(['admin', 'super_admin', 'building_official']);
        
        $application = $this->getApplicationDetails($applicationId);
        
        if (!$application || $application['status'] !== 'approved') {
            return ['success' => false, 'error' => 'Application must be approved to generate permit'];
        }
        
        $permitContent = $this->generatePermitContent($application);
        $permitPath = $this->savePermitPDF($applicationId, $permitContent);
        
        $this->auth->logActivity($_SESSION['user_id'], 'generate_permit', 'application', $applicationId, "Generated development permit");
        
        return ['success' => true, 'file_path' => $permitPath];
    }
    
    private function generatePermitContent($application) {
        $content = "DEVELOPMENT PERMIT\n";
        $content .= "==================\n\n";
        $content .= "Permit Number: {$application['application_number']}\n";
        $content .= "Date Issued: " . date('F d, Y') . "\n\n";
        $content .= "APPLICANT INFORMATION:\n";
        $content .= "Name: {$application['applicant_first_name']} {$application['applicant_last_name']}\n";
        $content .= "Email: {$application['applicant_email']}\n\n";
        $content .= "PROJECT INFORMATION:\n";
        $content .= "Project Name: {$application['project_name']}\n";
        $content .= "Project Type: {$application['project_type']}\n";
        $content .= "Location: {$application['barangay']}, Lot {$application['lot_number']}\n\n";
        $content .= "This permit authorizes the development project as described above, subject to compliance with all applicable zoning regulations and building codes.\n\n";
        $content .= "Issued by: " . ($_SESSION['full_name'] ?? 'System Admin') . "\n";
        $content .= "Position: " . Helper::getRoleName($_SESSION['role'] ?? 'admin') . "\n";
        
        return $content;
    }
    
    private function savePermitPDF($applicationId, $content) {
        $config = require __DIR__ . '/../../config/app.php';
        $permitPath = $config['upload_path'] . 'permits/';
        
        if (!is_dir($permitPath)) {
            mkdir($permitPath, 0755, true);
        }
        
        $fileName = "permit_{$applicationId}_" . time() . ".txt";
        $filePath = $permitPath . $fileName;
        
        file_put_contents($filePath, $content);
        
        return 'permits/' . $fileName;
    }
    
    public function sendNotification($userId, $subject, $message, $applicationId = null) {
        $this->db->query(
            "INSERT INTO messages (application_id, sender_id, receiver_id, subject, message, message_type) 
             VALUES (?, ?, ?, ?, ?, 'notification')",
            [$applicationId, $_SESSION['user_id'] ?? 0, $userId, $subject, $message]
        );
        
        return $this->db->lastInsertId();
    }
    
    public function getDashboardStats() {
        $this->auth->requireRole(['admin', 'super_admin', 'zoning_officer', 'building_official', 'assessor', 'inspector']);

        $role   = $_SESSION['role']    ?? null;
        $userId = $_SESSION['user_id'] ?? null;
        $stats  = [];

        // Inspectors and assessors only see applications assigned to them
        if (in_array($role, ['inspector', 'assessor'])) {
            $base = "SELECT COUNT(*) as count FROM applications WHERE assigned_officer_id = ?";
            $stats['total_applications'] = $this->db->fetchOne($base, [$userId])['count'];
            $stats['pending_review']     = $this->db->fetchOne($base . " AND status IN ('submitted', 'under_review')", [$userId])['count'];
            $stats['approved']           = $this->db->fetchOne($base . " AND status = 'approved'",     [$userId])['count'];
            $stats['rejected']           = $this->db->fetchOne($base . " AND status = 'rejected'",     [$userId])['count'];
            $stats['for_revision']       = $this->db->fetchOne($base . " AND status = 'for_revision'", [$userId])['count'];
            $stats['overdue_count']      = $this->db->fetchOne(
                $base . " AND created_at <= DATE_SUB(NOW(), INTERVAL 3 DAY) AND status NOT IN ('approved', 'rejected', 'cancelled')",
                [$userId]
            )['count'];

        // Zoning officers see only zoning-related types
        } elseif ($role === 'zoning_officer') {
            $base = "SELECT COUNT(*) as count FROM applications WHERE project_type IN ('zoning', 'subdivision', 'rezoning')";
            $stats['total_applications'] = $this->db->fetchOne($base)['count'];
            $stats['pending_review']     = $this->db->fetchOne($base . " AND status IN ('submitted', 'under_review')")['count'];
            $stats['approved']           = $this->db->fetchOne($base . " AND status = 'approved'")['count'];
            $stats['rejected']           = $this->db->fetchOne($base . " AND status = 'rejected'")['count'];
            $stats['for_revision']       = $this->db->fetchOne($base . " AND status = 'for_revision'")['count'];
            $stats['overdue_count']      = $this->db->fetchOne(
                $base . " AND created_at <= DATE_SUB(NOW(), INTERVAL 3 DAY) AND status NOT IN ('approved', 'rejected', 'cancelled')"
            )['count'];

        // Admin and building_official see everything
        } else {
            $stats['total_applications'] = $this->db->fetchOne("SELECT COUNT(*) as count FROM applications")['count'];
            $stats['pending_review']     = $this->db->fetchOne("SELECT COUNT(*) as count FROM applications WHERE status IN ('submitted', 'under_review')")['count'];
            $stats['approved']           = $this->db->fetchOne("SELECT COUNT(*) as count FROM applications WHERE status = 'approved'")['count'];
            $stats['rejected']           = $this->db->fetchOne("SELECT COUNT(*) as count FROM applications WHERE status = 'rejected'")['count'];
            $stats['for_revision']       = $this->db->fetchOne("SELECT COUNT(*) as count FROM applications WHERE status = 'for_revision'")['count'];
            $stats['overdue_count']      = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM applications WHERE created_at <= DATE_SUB(NOW(), INTERVAL 3 DAY) AND status NOT IN ('approved', 'rejected', 'cancelled')"
            )['count'];
        }

        return $stats;
    }

    public function getImpactAssessment($applicationId) {
        return $this->db->fetchOne(
            "SELECT * FROM impact_assessments WHERE application_id = ? ORDER BY id DESC LIMIT 1",
            [$applicationId]
        );
    }

    public function sendInspectionRequest($applicationId) {
        $this->auth->requireRole(['admin', 'super_admin', 'zoning_officer', 'building_official', 'inspector']);
        
        $sql = "INSERT INTO impact_assessments (application_id, traffic_flag, energy_flag, notes, assessed_by)
                VALUES (?, 'awaiting', 'awaiting', 'Inspection requested. Waiting for departmental reports.', ?)
                ON DUPLICATE KEY UPDATE 
                    traffic_flag = 'awaiting', 
                    energy_flag = 'awaiting', 
                    notes = 'Inspection re-requested.',
                    assessed_by = VALUES(assessed_by)";
        
        $this->auth->logActivity($_SESSION['user_id'], 'request_inspection', 'application', $applicationId, "Sent inspection request to Roads and Energy groups");
        
        return $this->db->query($sql, [$applicationId, $_SESSION['user_id']]);
    }
    
    public function saveImpactAssessment($applicationId, $trafficScore, $trafficFlag, $energyScore, $energyFlag, $notes = null) {
        $this->auth->requireRole(['admin', 'super_admin', 'zoning_officer', 'building_official', 'assessor']);
        
        $this->db->query(
            "INSERT INTO impact_assessments (application_id, traffic_score, traffic_flag, energy_score, energy_flag, notes, assessed_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE 
                traffic_score = VALUES(traffic_score),
                traffic_flag = VALUES(traffic_flag),
                energy_score = VALUES(energy_score),
                energy_flag = VALUES(energy_flag),
                notes = VALUES(notes),
                assessed_by = VALUES(assessed_by)",
            [
                $applicationId, $trafficScore, $trafficFlag, 
                $energyScore, $energyFlag, $notes, $_SESSION['user_id']
            ]
        );
        return $this->db->lastInsertId();
    }
    
    public function runMockImpactAssessment($application) {
        $this->auth->requireRole(['admin', 'super_admin', 'zoning_officer', 'building_official']);
        
        $projectType = strtolower($application['project_type'] ?? '');
        $trafficScore = rand(40, 95);
        $energyScore = rand(40, 95);
        
        if (strpos($projectType, 'mall') !== false || strpos($projectType, 'commercial') !== false) {
            $trafficScore = max($trafficScore, 80);
        }
        
        $trafficFlag = $trafficScore >= 75 ? 'high' : 'ok';
        $energyFlag = $energyScore >= 75 ? 'high' : 'ok';
        
        $notes = "Auto assessment (mock): Traffic {$trafficScore}, Energy {$energyScore}";
        $this->saveImpactAssessment($application['id'], $trafficScore, $trafficFlag, $energyScore, $energyFlag, $notes);
        
        return [
            'traffic_score' => $trafficScore,
            'traffic_flag' => $trafficFlag,
            'energy_score' => $energyScore,
            'energy_flag' => $energyFlag,
            'notes' => $notes
        ];
    }
}