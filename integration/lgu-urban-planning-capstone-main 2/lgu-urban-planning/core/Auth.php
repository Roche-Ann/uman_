<?php
/**
 * Authentication
 */

class Auth {
    private $db;

    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->startSession();
        $this->updateLastActivity();
    }
    
    private function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    // Online Status Indicator

    private function updateLastActivity() {
        if ($this->isLoggedIn()) {
            try {
                $this->db->query(
                    "UPDATE users SET last_activity = NOW() WHERE id = ?",
                    [$_SESSION['user_id']]
                );
            } catch (Exception $e) {
            }
        }
    }
    
    public function login($username, $password) {
        $user = $this->db->fetchOne(
            "SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1",
            [$username, $username]
        );
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
            
            $this->updateLastActivity();

            $this->logActivity($user['id'], 'login', 'user', $user['id'], 'User logged in');
            
            return true;
        }
        
        return false;
    }
    
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $this->logActivity($_SESSION['user_id'], 'logout', 'user', $_SESSION['user_id'], 'User logged out');
        }
        
        session_unset();
        session_destroy();
        return true;
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function getUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return $this->db->fetchOne(
            "SELECT * FROM users WHERE id = ?",
            [$_SESSION['user_id']]
        );
    }
    
    public function hasPermission($permission) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        $role = $_SESSION['role'];
        $hasPermission = $this->db->fetchOne(
            "SELECT id FROM role_permissions WHERE role = ? AND permission = ?",
            [$role, $permission]
        );
        
        return $hasPermission !== false;
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: /lgu-urban-planning/login.php');
            exit;
        }
    }
    
public function redirectToDashboard() {
    if (!$this->isLoggedIn()) {
        header('Location: /lgu-urban-planning/login.php');
        exit;
    }
    
    $user = $this->getUser();
    $role = $user['role'] ?? $_SESSION['role'];

    if ($role === 'applicant') {
        header('Location: /lgu-urban-planning/user/index.php');
    } else {
        header('Location: /lgu-urban-planning/admin/index.php');
    }
    exit;
}
    
    public function requirePermission($permission) {
        $this->requireLogin();
        if (!$this->hasPermission($permission)) {
            header('Location: /lgu-urban-planning/access-denied.php');
            exit;
        }
    }
    
public function requireRole($roles) {
    $this->requireLogin();
    
    // Fetch fresh user data from DB to ensure the role is current
    $user = $this->getUser(); 
    $currentRole = $user['role'] ?? $_SESSION['role'];

    if (!is_array($roles)) {
        $roles = [$roles];
    }

    if (!in_array($currentRole, $roles)) {
        header('Location: /lgu-urban-planning/access-denied.php');
        exit;
    }
}
    
    public function logActivity($userId, $action, $entityType = null, $entityId = null, $details = null) {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $this->db->query(
            "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) 
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$userId, $action, $entityType, $entityId, $details, $ipAddress, $userAgent]
        );
    }
}