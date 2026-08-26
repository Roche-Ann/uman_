<?php
require_once __DIR__ . '/../../core/Database.php';

if (!class_exists('MonitoringController')) {
    class MonitoringController {
        private $db;
        public function __construct() { $this->db = Database::getInstance(); }

public function getAllInspections() {
    $sql = "SELECT 
                i.id, 
                i.status, 
                i.scheduled_at, 
                a.application_number, 
                a.project_name 
            FROM inspections i 
            JOIN applications a ON i.application_id = a.id 
            -- Siguraduhin na may valid date talaga
            WHERE i.scheduled_at IS NOT NULL 
            AND i.scheduled_at != '0000-00-00 00:00:00' 
            AND i.scheduled_at != ''"; 
    return $this->db->fetchAll($sql);
}

public function getApplicationsForDropdown() {
    // Kukunin lang ang mga application na nasa inspection table na wala pang schedule
    $sql = "SELECT a.id, a.application_number, a.project_name 
            FROM applications a
            JOIN inspections i ON a.id = i.application_id
            WHERE (i.scheduled_at IS NULL OR i.scheduled_at = '0000-00-00 00:00:00' OR i.scheduled_at = '')
            AND i.status != 'completed'
            ORDER BY a.created_at DESC";
            
    return $this->db->fetchAll($sql);
}

        public function getStaffList() {
        return $this->db->fetchAll("SELECT id, first_name, last_name, role FROM users WHERE role IN ('admin', 'inspector', 'zoning_officer', 'building_official')");        }

        public function getRecentViolations() {
            return $this->db->fetchAll("SELECT v.*, a.application_number FROM violations v JOIN applications a ON v.application_id = a.id WHERE v.resolved = 0 ORDER BY v.created_at DESC LIMIT 5");
        }

public function scheduleInspection($data) {
    // Siguraduhin na ang date format ay tama para sa MySQL
    $date = str_replace('T', ' ', $data['scheduled_at']);
    $notes = $data['notes'] ?? ''; 
    $app_id = (int)$data['application_id'];
    $inspector_id = (int)$data['inspector_id'];
    
    // UPDATE: Tinanggal natin ang status check sa WHERE clause para siguradong mag-update
    $sql = "UPDATE inspections 
            SET scheduled_at = ?, 
                inspector_id = ?, 
                notes = ?, 
                status = 'scheduled' 
            WHERE application_id = ?";
            
    return $this->db->query($sql, [$date, $inspector_id, $notes, $app_id]);
}

// Sa loob ng MonitoringController.php, palitan ang function na ito:
public function getInspectionLogs() {
    $sql = "SELECT 
            i.*, 
            a.application_number, 
            a.project_name,
            -- DITO TAYO NAGKAKAMALI: Siguraduhing i.inspector_id ang ginagamit sa JOIN
            CONCAT(u.first_name, ' ', u.last_name) as inspector_name,
            CASE 
                WHEN (i.scheduled_at IS NULL OR i.scheduled_at = '0000-00-00 00:00:00' OR i.scheduled_at = '') THEN 'inspection'
                ELSE i.status 
            END as display_status
        FROM inspections i
        JOIN applications a ON i.application_id = a.id
        LEFT JOIN users u ON i.inspector_id = u.id -- JOIN base sa inassign na inspector
        ORDER BY i.created_at DESC";
            
    return $this->db->fetchAll($sql);
}

        public function approveOccupancy($id) {
            return $this->db->query("UPDATE applications SET status = 'completed' WHERE id = ?", [$id]);
        }
    }
}