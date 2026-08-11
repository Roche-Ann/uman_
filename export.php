<?php
// export.php - Export data from all modules
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Redirect if not logged in
if (!isLoggedIn() || !isEmployee()) {
    header('Location: login.php');
    exit();
}

// Get parameters
$type = $_GET['type'] ?? '';
$format = $_GET['format'] ?? 'csv';
$filter = $_GET['filter'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Define allowed export types
$allowedTypes = ['assets', 'incidents', 'maintenance', 'energy', 'facilities', 'users'];

if (!in_array($type, $allowedTypes)) {
    die('Invalid export type.');
}

// ================================================================
// BUILD DATA BASED ON TYPE
// ================================================================
$data = [];
$headers = [];
$filename = date('Y-m-d');

switch ($type) {
    case 'assets':
        // Export utility assets
        $sql = "
            SELECT 
                a.asset_id AS 'Asset ID',
                a.name AS 'Asset Name',
                t.name AS 'Category',
                a.quantity AS 'Quantity',
                a.location AS 'Location',
                a.condition_status AS 'Status',
                a.date_installed AS 'Date Installed',
                a.responsible_office AS 'Responsible Office',
                a.description AS 'Description'
            FROM utility_assets a
            JOIN asset_types t ON a.asset_type_id = t.id
            ORDER BY a.asset_id ASC
        ";
        $stmt = $pdo->query($sql);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $headers = ['Asset ID', 'Asset Name', 'Category', 'Quantity', 'Location', 'Status', 'Date Installed', 'Responsible Office', 'Description'];
        $filename = 'assets_export_' . date('Y-m-d');
        break;

    case 'incidents':
        // Export incident reports
        $sql = "
            SELECT 
                i.incident_id AS 'Report ID',
                c.name AS 'Category',
                i.description AS 'Description',
                i.location AS 'Location',
                i.status AS 'Status',
                i.priority AS 'Priority',
                i.created_at AS 'Date Reported',
                i.updated_at AS 'Last Updated',
                u.full_name AS 'Reported By'
            FROM utility_incidents i
            JOIN incident_categories c ON i.category_id = c.id
            LEFT JOIN users u ON i.resident_id = u.id
            ORDER BY i.created_at DESC
        ";
        $stmt = $pdo->query($sql);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $headers = ['Report ID', 'Category', 'Description', 'Location', 'Status', 'Priority', 'Date Reported', 'Last Updated', 'Reported By'];
        $filename = 'incidents_export_' . date('Y-m-d');
        break;

    case 'maintenance':
        // Export maintenance requests
        $sql = "
            SELECT 
                r.request_id AS 'Request ID',
                r.source AS 'Source',
                r.description AS 'Description',
                r.priority AS 'Priority',
                r.status AS 'Status',
                r.location AS 'Location',
                r.created_at AS 'Date Created',
                r.updated_at AS 'Last Updated',
                a.asset_id AS 'Linked Asset'
            FROM maintenance_requests r
            LEFT JOIN utility_assets a ON r.utility_asset_id = a.id
            ORDER BY r.created_at DESC
        ";
        $stmt = $pdo->query($sql);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $headers = ['Request ID', 'Source', 'Description', 'Priority', 'Status', 'Location', 'Date Created', 'Last Updated', 'Linked Asset'];
        $filename = 'maintenance_export_' . date('Y-m-d');
        break;

    case 'energy':
        // Export energy consumption records
        $sql = "
            SELECT 
                e.record_id AS 'Record ID',
                e.asset_type AS 'Asset Type',
                e.facility_name AS 'Facility Name',
                e.location AS 'Location',
                e.month_year AS 'Month/Year',
                e.consumption_kwh AS 'Consumption (kWh)',
                e.cost AS 'Cost (PHP)',
                e.data_source AS 'Data Source',
                e.notes AS 'Notes'
            FROM energy_consumption_records e
            ORDER BY e.month_year DESC, e.date_recorded DESC
        ";
        $stmt = $pdo->query($sql);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $headers = ['Record ID', 'Asset Type', 'Facility Name', 'Location', 'Month/Year', 'Consumption (kWh)', 'Cost (PHP)', 'Data Source', 'Notes'];
        $filename = 'energy_export_' . date('Y-m-d');
        break;

    case 'facilities':
        // Export public facilities
        $sql = "
            SELECT 
                f.facility_id AS 'Facility ID',
                f.name AS 'Facility Name',
                f.facility_type AS 'Facility Type',
                f.location AS 'Location',
                f.utility_status AS 'Utility Status',
                f.description AS 'Description',
                s.water_available AS 'Water Available',
                s.electricity_available AS 'Electricity Available',
                s.drainage_ok AS 'Drainage OK',
                s.lighting_ok AS 'Lighting OK'
            FROM public_facilities f
            JOIN facility_utility_status s ON f.id = s.public_facility_id
            ORDER BY f.name ASC
        ";
        $stmt = $pdo->query($sql);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $headers = ['Facility ID', 'Facility Name', 'Facility Type', 'Location', 'Utility Status', 'Description', 'Water Available', 'Electricity Available', 'Drainage OK', 'Lighting OK'];
        $filename = 'facilities_export_' . date('Y-m-d');
        break;

    case 'users':
        // Export users
        $sql = "
            SELECT 
                id AS 'User ID',
                full_name AS 'Full Name',
                email AS 'Email',
                user_type AS 'Role',
                CASE WHEN is_active = 1 THEN 'Active' ELSE 'Inactive' END AS 'Status',
                created_at AS 'Registered Date'
            FROM users
            ORDER BY id DESC
        ";
        $stmt = $pdo->query($sql);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $headers = ['User ID', 'Full Name', 'Email', 'Role', 'Status', 'Registered Date'];
        $filename = 'users_export_' . date('Y-m-d');
        break;
}

// ================================================================
// IF NO DATA
// ================================================================
if (empty($data)) {
    die('No data to export.');
}

// ================================================================
// FORMAT: CSV
// ================================================================
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    // Add UTF-8 BOM for Excel compatibility
    fwrite($output, "\xEF\xBB\xBF");
    
    // Headers
    fputcsv($output, $headers);
    
    // Data rows
    foreach ($data as $row) {
        fputcsv($output, array_values($row));
    }
    
    fclose($output);
    exit;
}

// ================================================================
// FORMAT: PDF (using TCPDF)
// ================================================================
if ($format === 'pdf') {
    // Check if TCPDF is installed
    if (!class_exists('TCPDF')) {
        // Try to load TCPDF from vendor
        $tcpdfPath = __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php';
        if (file_exists($tcpdfPath)) {
            require_once $tcpdfPath;
        } else {
            // Fallback: provide download link for CSV instead
            die('PDF library not installed. Please install TCPDF via Composer: <code>composer require tecnickcom/tcpdf</code><br><br>
                 <a href="?type=' . $type . '&format=csv" class="btn btn-primary">Download as CSV instead</a>');
        }
    }

    // Create new PDF document
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('LGU Utilities Management System');
    $pdf->SetAuthor('uMAN');
    $pdf->SetTitle('Export - ' . ucfirst($type));
    $pdf->SetSubject('Data Export');
    
    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', '', 9);
    
    // Title
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, strtoupper($type) . ' EXPORT', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, 'Generated: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
    $pdf->Ln(5);
    
    // Build HTML table
    $html = '<table border="1" cellpadding="4" style="font-size: 9px; border-collapse: collapse;">';
    
    // Header row
    $html .= '<thead><tr style="background-color: #3762c8; color: white;">';
    foreach ($headers as $header) {
        $html .= '<th style="padding: 6px 8px; text-align: left;">' . htmlspecialchars($header) . '</th>';
    }
    $html .= '</tr></thead>';
    
    // Data rows
    $html .= '<tbody>';
    $rowCount = 0;
    foreach ($data as $row) {
        $rowCount++;
        $bgColor = ($rowCount % 2 == 0) ? '#f8f9fa' : '#ffffff';
        $html .= '<tr style="background-color: ' . $bgColor . ';">';
        foreach (array_values($row) as $value) {
            $displayValue = $value === null || $value === '' ? '—' : htmlspecialchars((string)$value);
            $html .= '<td style="padding: 4px 6px;">' . $displayValue . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody>';
    $html .= '</table>';
    
    // Add table to PDF
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Output PDF
    $pdf->Output($filename . '.pdf', 'D');
    exit;
}
?>