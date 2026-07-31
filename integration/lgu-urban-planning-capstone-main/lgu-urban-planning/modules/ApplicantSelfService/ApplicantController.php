<?php

// Applicant Controller

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helper.php';

class ApplicantController {
    private $db;
    private $auth;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->auth = new Auth();
    }
    
    public function submitApplication($data) {
        $this->auth->requireRole('applicant');
        
        $applicationNumber = Helper::generateApplicationNumber();
        
        // 1. INSERT SA APPLICATIONS TABLE
        $this->db->query(
            "INSERT INTO applications (
                application_number, applicant_id, project_name, project_type, project_description,
                lot_number, block, street, barangay, parcel_id, latitude, longitude, status, submitted_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted', NOW())",
            [
                $applicationNumber,
                $_SESSION['user_id'],
                $data['project_name'],
                $data['project_type'] ?? null,
                $data['project_description'] ?? null,
                $data['lot_number'] ?? null,
                $data['block'] ?? null,
                $data['street'] ?? null,
                $data['barangay'] ?? null,
                $data['parcel_id'] ?? null,
                $data['latitude'] ?? null,
                $data['longitude'] ?? null
            ]
        );
        
        $applicationId = $this->db->lastInsertId();
        
        // 2. INSERT SA STATUS HISTORY (Mahalaga para sa Audit Trail)
        $this->db->query(
            "INSERT INTO application_status_history (application_id, status, remarks, changed_by) 
             VALUES (?, 'submitted', 'Application submitted by applicant', ?)",
            [$applicationId, $_SESSION['user_id']]
        );
        
        // 3. LOG ACTIVITY
        $this->auth->logActivity(
            $_SESSION['user_id'], 
            'submit_application', 
            'applications', 
            $applicationId, 
            "Submitted application #$applicationNumber"
        );
        
        return $applicationId;
    }

    public function uploadDocument($applicationId, $file, $documentType) {
        $this->auth->requireRole('applicant');
        
        $application = $this->db->fetchOne(
            "SELECT id FROM applications WHERE id = ? AND applicant_id = ?",
            [$applicationId, $_SESSION['user_id']]
        );
        
        if (!$application) {
            return ['success' => false, 'error' => 'Application not found'];
        }
        
        $uploadResult = Helper::uploadFile($file, 'documents');
        
        if ($uploadResult['success']) {
            $this->db->query(
                "INSERT INTO application_documents (application_id, document_type, file_name, file_path, file_size, mime_type, uploaded_by) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $applicationId,
                    $documentType,
                    $uploadResult['file_name'],
                    $uploadResult['file_path'],
                    $uploadResult['file_size'],
                    $uploadResult['mime_type'],
                    $_SESSION['user_id']
                ]
            );
            
            $this->auth->logActivity($_SESSION['user_id'], 'upload_document', 'application', $applicationId, "Uploaded document: {$documentType}");
            
            return ['success' => true, 'document_id' => $this->db->lastInsertId()];
        }
        
        return $uploadResult;
    }
    
    public function getMyApplications() {
        $this->auth->requireRole('applicant');
        
        return $this->db->fetchAll(
            "SELECT a.*, 
                    (SELECT COUNT(*) FROM application_documents WHERE application_id = a.id) as document_count
             FROM applications a 
             WHERE a.applicant_id = ? 
             ORDER BY a.created_at DESC",
            [$_SESSION['user_id']]
        );
    }
    
    public function getApplicationDetails($applicationId) {
        $this->auth->requireRole('applicant');
        
        $application = $this->db->fetchOne(
            "SELECT * FROM applications WHERE id = ? AND applicant_id = ?",
            [$applicationId, $_SESSION['user_id']]
        );
        
        if (!$application) {
            return null;
        }
        
        $application['documents'] = $this->db->fetchAll(
            "SELECT * FROM application_documents WHERE application_id = ? ORDER BY created_at DESC",
            [$applicationId]
        );
        
        $application['status_history'] = $this->db->fetchAll(
            "SELECT ash.*, u.first_name, u.last_name 
             FROM application_status_history ash 
             LEFT JOIN users u ON ash.changed_by = u.id 
             WHERE ash.application_id = ? 
             ORDER BY ash.created_at ASC",
            [$applicationId]
        );
        
        return $application;
    }

    public function getMessagesPaginated($applicationId = null, $filter = 'all', $limit = 5, $offset = 0, $search = '') {
        $this->auth->requireRole('applicant');
        $userId = $_SESSION['user_id'];

        if ($filter === 'sent') {
            $where = "WHERE m.sender_id = ?";
            $params = [$userId];
        } else {
            $where = "WHERE m.receiver_id = ?";
            $params = [$userId];

            if ($filter === 'unread') {
                $where .= " AND m.is_read = 0";
            } elseif ($filter === 'read') {
                $where .= " AND m.is_read = 1";
            }
        }

        if ($applicationId) {
            $where .= " AND m.application_id = ?";
            $params[] = $applicationId;
        }

        // Search: only match subject, message body, and sender full name
        if (!empty($search)) {
            $like = '%' . $search . '%';
            $where .= " AND (
                m.subject LIKE ?
                OR m.message LIKE ?
                OR CONCAT(sender.first_name, ' ', sender.last_name) LIKE ?
            )";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        // Count needs the same JOIN as main query so the sender LIKE works
        $countSql = "SELECT COUNT(*) as count
                     FROM messages m
                     LEFT JOIN users sender ON m.sender_id = sender.id
                     $where";
        $totalResult = $this->db->fetchOne($countSql, $params);
        $total = $totalResult['count'] ?? 0;

        $sql = "SELECT m.*,
                       sender.first_name as sender_first_name, sender.last_name as sender_last_name,
                       CONCAT(receiver.first_name, ' ', receiver.last_name) as receiver_name,
                       a.application_number
                FROM messages m
                LEFT JOIN users sender   ON m.sender_id   = sender.id
                LEFT JOIN users receiver ON m.receiver_id = receiver.id
                LEFT JOIN applications a ON m.application_id = a.id
                $where
                ORDER BY m.created_at DESC
                LIMIT $limit OFFSET $offset";

        $items = $this->db->fetchAll($sql, $params);

        return [
            'items' => $items,
            'total' => $total,
        ];
    }
    
    public function getMessages($applicationId = null) {
        $this->auth->requireRole('applicant');
        
        $sql = "SELECT m.*, 
                       sender.first_name as sender_first_name, sender.last_name as sender_last_name,
                       receiver.first_name as receiver_first_name, receiver.last_name as receiver_last_name,
                       a.application_number
                FROM messages m
                LEFT JOIN users sender ON m.sender_id = sender.id
                LEFT JOIN users receiver ON m.receiver_id = receiver.id
                LEFT JOIN applications a ON m.application_id = a.id
                WHERE m.receiver_id = ?";
        $params = [$_SESSION['user_id']];
        
        if ($applicationId) {
            $sql .= " AND m.application_id = ?";
            $params[] = $applicationId;
        }
        
        $sql .= " ORDER BY m.created_at DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function sendMessage($receiverId, $message, $subject = null, $applicationId = null) {
        $this->auth->requireRole('applicant');
        
        $this->db->query(
            "INSERT INTO messages (application_id, sender_id, receiver_id, subject, message, message_type) 
             VALUES (?, ?, ?, ?, ?, 'message')",
            [$applicationId, $_SESSION['user_id'], $receiverId, $subject, $message]
        );
        
        $this->auth->logActivity($_SESSION['user_id'], 'send_message', 'message', $this->db->lastInsertId(), "Sent message to user ID: $receiverId");
        
        return $this->db->lastInsertId();
    }
    
    public function markMessageAsRead($messageId) {
        $this->auth->requireRole('applicant');
        
        $this->db->query(
            "UPDATE messages SET is_read = 1 WHERE id = ? AND receiver_id = ?",
            [$messageId, $_SESSION['user_id']]
        );
    }
    
    public function getUnreadMessageCount() {
        $this->auth->requireRole('applicant');
        
        $result = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0",
            [$_SESSION['user_id']]
        );
        
        return $result['count'] ?? 0;
    }
}