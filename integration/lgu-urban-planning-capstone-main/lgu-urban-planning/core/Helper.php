<?php
/**
 * Helper Functions
 */

class Helper {
    public static function generateApplicationNumber() {
        return 'DP-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }
    
    public static function formatDate($date, $format = 'M d, Y') {
        if (empty($date)) return '';
        return date($format, strtotime($date));
    }
    
    public static function formatDateTime($date, $format = 'M d, Y h:i A') {
        if (empty($date)) return '';
        return date($format, strtotime($date));
    }
    
    public static function sanitize($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitize'], $data);
        }
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
    
    public static function uploadFile($file, $directory = 'documents') {
        $config = require __DIR__ . '/../config/app.php';
        $uploadPath = $config['upload_path'] . $directory . '/';
        
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }
        
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = uniqid() . '_' . time() . '.' . $extension;
        $filePath = $uploadPath . $fileName;
        
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            return [
                'success' => true,
                'file_name' => $file['name'],
                'file_path' => $directory . '/' . $fileName,
                'file_size' => $file['size'],
                'mime_type' => $file['type']
            ];
        }
        
        return ['success' => false, 'error' => 'File upload failed'];
    }
    
    public static function getStatusBadge($status) {
        $badges = [
            'draft' => 'secondary',
            'submitted' => 'primary',
            'under_review' => 'primary',
            'for_revision' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
            'cancelled' => 'dark'
        ];
        
        return $badges[$status] ?? 'secondary';
    }
    
    public static function getRoleName($role) {
        $roles = [
            'admin' => 'Administrator',
            'zoning_officer' => 'Zoning Officer',
            'building_official' => 'Building Official',
            'assessor' => 'Assessor',
            'applicant' => 'Applicant',
            'inspector' => 'Inspector'
        ];
        
        return $roles[$role] ?? ucfirst($role);
    }
}

