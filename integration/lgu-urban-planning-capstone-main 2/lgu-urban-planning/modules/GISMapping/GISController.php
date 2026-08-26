<?php
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';

class GISController {
    private $db;
    private $auth;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->auth = new Auth();
    }

    /**
     * Get Layer configurations
     */
    public function getGISLayers() {
        $this->auth->requireLogin();
        return $this->db->fetchAll(
            "SELECT id, layer_name, layer_type, display_order 
             FROM gis_layers WHERE is_active = 1 
             ORDER BY display_order, layer_name"
        );
    }

    /**
     * Fetch GeoJSON data for specific layers
     */
    public function getLayerData($layerId) {
        $this->auth->requireLogin();
        return $this->db->fetchOne(
            "SELECT layer_name, layer_type, layer_data FROM gis_layers WHERE id = ?",
            [$layerId]
        );
    }

    /**
     * FETCH ALL PARCELS
     * Aliasing 'zoning_classification_id' as 'zoning_id' so it works 
     * with the map.php JavaScript filter.
     */
public function getAllParcels() {
    $this->auth->requireLogin();
    return $this->db->fetchAll(
        "SELECT p.*, 
                p.zoning_classification_id as zoning_id, 
                zc.code as zoning_code, 
                zc.name as zoning_name
        FROM parcels p 
        LEFT JOIN zoning_classifications zc ON p.zoning_classification_id = zc.id"
    );
}

    /**
     * SEARCH PARCELS
     * Used by the Location Locator sidebar.
     */
    public function searchParcel($criteria) {
        $this->auth->requireLogin();
        $sql = "SELECT p.*, p.zoning_classification_id as zoning_id, zc.name as zoning_name 
                FROM parcels p 
                LEFT JOIN zoning_classifications zc ON p.zoning_classification_id = zc.id 
                WHERE 1=1";
        $params = [];
        
        if (!empty($criteria['lot_number'])) { $sql .= " AND p.lot_number = ?"; $params[] = $criteria['lot_number']; }
        if (!empty($criteria['block_no'])) { $sql .= " AND p.block_no = ?"; $params[] = $criteria['block_no']; }
        if (!empty($criteria['barangay'])) { $sql .= " AND p.barangay LIKE ?"; $params[] = "%".$criteria['barangay']."%"; }
        if (!empty($criteria['parcel_id'])) { $sql .= " AND p.parcel_id = ?"; $params[] = $criteria['parcel_id']; }
        
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * ZONING DROPDOWN DATA
     */
    public function getZoningClassifications() {
        return $this->db->fetchAll("SELECT id, code, name FROM zoning_classifications WHERE is_active = 1 ORDER BY code ASC");
    }

    /**
     * BOUNDARY VALIDATOR
     * Ensures coordinates are within Quezon City limits.
     */
    private function isWithinQC($lat, $lng) {
        $minLat = 14.5800; $maxLat = 14.7700;
        $minLng = 120.9800; $maxLng = 121.1500;
        return ($lat >= $minLat && $lat <= $maxLat && $lng >= $minLng && $lng <= $maxLng);
    }

    /**
     * GET SINGLE PARCEL BY ID
     */
    public function getParcelById($parcelId) {
        return $this->db->fetchOne(
            "SELECT p.*, zc.id as zoning_classification_id, zc.code as zoning_code, zc.name as zoning_name, zc.allowed_uses, zc.description as zoning_description
             FROM parcels p 
             LEFT JOIN zoning_classifications zc ON p.zoning_classification_id = zc.id 
             WHERE p.parcel_id = ? OR p.id = ?",
            [$parcelId, $parcelId]
        );
    }

    /**
     * TERM MAPPING
     * Maps user input (e.g. 'sari-sari') to legal zoning terms.
     */
    private function mapQCTerms($projectType) {
        $projectType = strtolower(trim($projectType));
        $dictionary = [
            'sari-sari store' => 'neighborhood retail, convenience store',
            'apartment'       => 'multi-family dwelling, residential condominium, rowhouse',
            'bahay'           => 'single-detached dwelling, residential',
            'opisina'         => 'office',
            'tindahan'        => 'retail, commercial center',
            'talyer'          => 'automotive repair shop, service station',
            'bodega'          => 'warehouse, storage facility',
            'eskwelahan'      => 'educational institution, school',
            'klinika'         => 'medical clinic, health facility'
        ];
        return $dictionary[$projectType] ?? $projectType;
    }

    /**
     * CHECK ZONING COMPLIANCE
     * Main logic for technical analysis.
     */
    public function checkZoningCompliance($applicationId, $parcelId = null) {
        $this->auth->requireLogin();
        try {
            $app = $this->db->fetchOne(
                "SELECT latitude, longitude, project_type, parcel_id FROM applications WHERE id = ?", 
                [$applicationId]
            );

            if (!$this->isWithinQC($app['latitude'], $app['longitude'])) {
                return ['success' => false, 'error' => "OUTSIDE JURISDICTION: Coordinates are outside Quezon City limits."];
            }

            $effectiveParcelId = $parcelId ?: ($app['parcel_id'] ?? null);

            if ($parcelId) {
                $this->db->query("UPDATE applications SET parcel_id = ?, updated_at = NOW() WHERE id = ?", [$parcelId, $applicationId]);
            }

            if (!$effectiveParcelId) {
                return ['success' => false, 'error' => 'Missing Parcel ID. Please select a lot on the map.'];
            }

            $parcel = $this->getParcelById($effectiveParcelId);
            if (!$parcel) {
                return ['success' => false, 'error' => 'Parcel data not found.'];
            }

            $mappedType = $this->mapQCTerms($app['project_type']);
            $allowedUses = strtolower($parcel['allowed_uses'] ?? '');
            
            $isCompliant = (strpos($allowedUses, strtolower($app['project_type'])) !== false || 
                            strpos($allowedUses, strtolower($mappedType)) !== false);

            $status = $isCompliant ? 'compliant' : 'non_compliant';
            $report = $isCompliant 
                ? "Project is COMPLIANT with {$parcel['zoning_name']} regulations." 
                : "Project is RESTRICTED in {$parcel['zoning_code']} zone. Manual review required.";

            $this->db->query(
                "INSERT INTO zoning_compliance_checks (application_id, parcel_id, zoning_type, compliance_result, technical_analysis, checked_by, checked_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE 
                compliance_result = VALUES(compliance_result),
                technical_analysis = VALUES(technical_analysis)",
                [$applicationId, $effectiveParcelId, $parcel['zoning_name'], $status, $report, $_SESSION['user_id']]
            );

            $this->db->query("UPDATE applications SET zoning_compliance_status = ?, updated_at = NOW() WHERE id = ?", [$status, $applicationId]);
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

/**
 * GET COMPLIANCE CHECK
 * Fixed: Using 'zoning_type' in the JOIN instead of 'zoning_classification_id'
 */
public function getComplianceCheck($applicationId) {
    $this->auth->requireLogin();
    return $this->db->fetchOne(
        "SELECT zcc.*, zc.name as zoning_name, zc.code as zoning_code
         FROM zoning_compliance_checks zcc
         JOIN zoning_classifications zc ON zcc.zoning_type = zc.name
         WHERE zcc.application_id = ? 
         ORDER BY zcc.checked_at DESC LIMIT 1",
        [$applicationId]
    );
}
}