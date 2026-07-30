<?php
/**
 * User & Access Management Module - User Controller
 */

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helper.php';

class UserController {
    private $db;
    private $auth;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->auth = new Auth();
    }

    /**
     * Fetch single user by ID
     */
    public function getUserById($userId) {
        return $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
    }


    public function getTotalUsersCount($filters = []) {
        $sql = "SELECT COUNT(*) as total FROM users WHERE 1=1";
        $params = [];

        if (!empty($filters['role'])) {
            $sql .= " AND role = ?";
            $params[] = $filters['role'];
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $sql .= " AND is_active = ?";
            $params[] = (int)$filters['is_active'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
            $s = "%{$filters['search']}%"; 
            $params = array_merge($params, [$s, $s, $s, $s]);
        }

        $result = $this->db->fetchOne($sql, $params);
        return $result['total'] ?? 0;
    }


    public function getAllUsersPaginated($filters = [], $limit = 10, $offset = 0) {
        $sql = "SELECT * FROM users WHERE 1=1";
        $params = [];

        if (!empty($filters['role'])) {
            $sql .= " AND role = ?";
            $params[] = $filters['role'];
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $sql .= " AND is_active = ?";
            $params[] = (int)$filters['is_active'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
            $s = "%{$filters['search']}%"; 
            $params = array_merge($params, [$s, $s, $s, $s]);
        }

        $sql .= " ORDER BY created_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Approve or Reject Identity Verification
     * MODIFIED: Added is_active = 1 to ensure user can still login after rejection
     */
    public function verifyIdentity($userId, $status, $reason = '') {
        $this->auth->requirePermission('manage_users');
        $isVerified = ($status === 'approve') ? 1 : 0;
        
        // Force is_active to 1 during verification/rejection process
        $this->db->query(
            "UPDATE users SET is_verified = ?, rejection_reason = ?, is_active = 1 WHERE id = ?", 
            [$isVerified, $reason, $userId]
        );
        
        $msg = ($isVerified) ? "Approved Identity" : "Rejected Identity: $reason";
        $adminId = $_SESSION['user_id'] ?? 0;
        $this->auth->logActivity($adminId, 'verify_identity', 'user', $userId, $msg);
        
        return true;
    }
    
    /**
     * Create new user account
     */
    public function createUser($data) {
        $this->auth->requirePermission('manage_users');
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        
        $this->db->query(
            "INSERT INTO users (username, email, password_hash, first_name, last_name, role, phone, is_active) 
             VALUES (?, ?, ?, ?, ?, ?, ?, 1)",
            [
                $data['username'], 
                $data['email'], 
                $passwordHash, 
                $data['first_name'], 
                $data['last_name'], 
                $data['role'], 
                $data['phone']
            ]
        );
        
        $userId = $this->db->lastInsertId();
        $adminId = $_SESSION['user_id'] ?? 0;
        $this->auth->logActivity($adminId, 'create_user', 'user', $userId, "Created user: {$data['username']}");
        
        return $userId;
    }
    
    /**
     * Update existing user details
     */
    public function updateUser($userId, $data) {
        $this->auth->requirePermission('manage_users');
        $updateFields = [];
        $params = [];
        
        foreach(['first_name', 'last_name', 'email', 'phone', 'role', 'username'] as $field) {
            if (isset($data[$field])) { 
                $updateFields[] = "$field = ?"; 
                $params[] = $data[$field]; 
            }
        }
        
        if (!empty($data['password'])) {
            $updateFields[] = "password_hash = ?";
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        $params[] = $userId;
        $this->db->query("UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?", $params);
        
        $adminId = $_SESSION['user_id'] ?? 0;
        $this->auth->logActivity($adminId, 'update_user', 'user', $userId, "Updated user ID: $userId");
        
        return true;
    }

    /**
     * Fetch user's activity history and applications
     */
    public function getUserHistory($userId) {
        $lastLogin = $this->db->fetchOne(
            "SELECT created_at FROM audit_logs WHERE user_id = ? AND action = 'login' ORDER BY created_at DESC LIMIT 1",
            [$userId]
        );
        
        $applications = $this->db->fetchAll(
            "SELECT application_number, project_name, status, created_at FROM applications WHERE applicant_id = ? ORDER BY created_at DESC",
            [$userId]
        );
        
        return [
            'last_login' => $lastLogin ? $lastLogin['created_at'] : 'No record',
            'applications' => $applications,
            'app_count' => count($applications)
        ];
    }

    public function deactivateUser($userId) {
        $this->auth->requirePermission('manage_users');
        $this->db->query("UPDATE users SET is_active = 0 WHERE id = ?", [$userId]);
        return true;
    }

    public function activateUser($userId) {
        $this->auth->requirePermission('manage_users');
        $this->db->query("UPDATE users SET is_active = 1 WHERE id = ?", [$userId]);
        return true;
    }

    /**
     * Get all users with optional filters (used for CSV Export)
     */
    public function getAllUsers($filters = []) {
        $sql = "SELECT * FROM users WHERE 1=1";
        $params = [];
        
        if (!empty($filters['role'])) { 
            $sql .= " AND role = ?"; 
            $params[] = $filters['role']; 
        }
        
        if (isset($filters['is_active']) && $filters['is_active'] !== '') { 
            $sql .= " AND is_active = ?"; 
            $params[] = $filters['is_active']; 
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
            $s = "%{$filters['search']}%"; 
            $params = array_merge($params, [$s, $s, $s, $s]);
        }
        
        return $this->db->fetchAll($sql . " ORDER BY created_at DESC", $params);
    }


    public function getTotalAuditLogsCount($filters = []) {
        $sql = "SELECT COUNT(*) as total FROM audit_logs WHERE 1=1";
        $params = [];

        if (!empty($filters['action'])) {
            $sql .= " AND action LIKE ?";
            $params[] = "%" . $filters['action'] . "%";
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['user_id'])) {
            $sql .= " AND user_id = ?";
            $params[] = $filters['user_id'];
        }

        $result = $this->db->fetchOne($sql, $params);
        return $result['total'] ?? 0;
    }

    /**
     * FETCH AUDIT LOGS WITH PAGINATION
     */
    public function getAuditLogs($filters = [], $limit = 15, $offset = 0) {
        $sql = "SELECT al.*, u.username 
                FROM audit_logs al 
                LEFT JOIN users u ON al.user_id = u.id 
                WHERE 1=1";
        $params = [];

        if (!empty($filters['action'])) {
            $sql .= " AND al.action LIKE ?";
            $params[] = "%" . $filters['action'] . "%";
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(al.created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(al.created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['user_id'])) {
            $sql .= " AND al.user_id = ?";
            $params[] = $filters['user_id'];
        }

        $sql .= " ORDER BY al.created_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        
        return $this->db->fetchAll($sql, $params);
    }
}