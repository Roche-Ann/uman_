<?php
// export.php - Central Export Handler
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn() || !isEmployee()) {
    header('Location: login.php');
    exit();
}

$type = $_GET['type'] ?? '';
$format = $_GET['format'] ?? 'csv';
$allowed = ['assets','incidents','maintenance','energy','water','facilities','users'];
if (!in_array($type, $allowed)) die('Invalid type.');

$data = [];
$headers = [];
$filename = '';

switch ($type) {
    case 'assets':
        $sql = "SELECT a.asset_id AS 'Asset ID', a.name AS 'Asset Name', t.name AS 'Category', a.quantity AS 'Quantity', a.location AS 'Location', a.condition_status AS 'Status', a.date_installed AS 'Date Installed', a.responsible_office AS 'Responsible Office', a.description AS 'Description' FROM utility_assets a JOIN asset_types t ON a.asset_type_id = t.id ORDER BY a.asset_id";
        $stmt = $pdo->query($sql);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $headers = ['Asset ID','Asset Name','Category','Quantity','Location','Status','Date Installed','Responsible Office','Description'];
        $filename = 'assets_export_' . date('Y-m-d');
        break;
    case 'incidents':
        $sql = "SELECT i.incident_id AS 'Report ID', c.name AS 'Category', i.description AS 'Description', i.location AS 'Location', i.status AS 'Status', i.priority AS 'Priority', i.created_at AS 'Date Reported', i.updated_at AS 'Last Updated', u.full_name AS 'Reported By' FROM utility_incidents i JOIN incident_categories c ON i.category_id = c.id LEFT JOIN users u ON i.resident_id = u.id ORDER BY i.created_at DESC";
        $stmt = $pdo->query($sql);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $headers = ['Report ID','Category','Description','Location','Status','Priority','Date Reported','Last Updated','Reported By'];
        $filename = 'incidents_export_' . date('Y-m-d');
        break;
    case 'maintenance':
        $sql = "SELECT r.request_id AS 'Request ID', r.source AS 'Source', r.description AS 'Description', r.priority AS 'Priority', r.status AS 'Status', r.location AS 'Location', r.created_at AS 'Date Created', r.updated_at AS 'Last Updated', a.asset_id AS 'Linked Asset' FROM maintenance_requests r LEFT JOIN utility_assets a ON r.utility_asset_id = a.id ORDER BY r.created_at DESC";
        $stmt = $pdo->query($sql);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $headers = ['Request ID','Source','Description','Priority','Status','Location','Date Created','Last Updated','Linked Asset'];
        $filename = 'maintenance_export_' . date('Y-m-d');
        break;
    case 'energy':
        $sql = "SELECT e.record_id AS 'Record ID', e.asset_type AS 'Asset Type', e.facility_name AS 'Facility Name', e.location AS 'Location', e.month_year AS 'Month/Year', e.consumption_kwh AS 'Consumption (kWh)', e.cost AS 'Cost (PHP)', e.data_source AS 'Data Source', e.notes AS 'Notes' FROM energy_consumption_records e ORDER BY e.month_year DESC";
        $stmt = $pdo->query($sql);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $headers = ['Record ID','Asset Type','Facility Name','Location','Month/Year','Consumption (kWh)','Cost (PHP)','Data Source','Notes'];
        $filename = 'energy_export_' . date('Y-m-d');
        break;
    case 'water':
        if (function_exists('ensureWaterSchema')) {
            ensureWaterSchema();
        }
        $sql = "SELECT w.record_id AS 'Record ID', w.asset_type AS 'Asset Type', w.facility_name AS 'Facility Name', w.location AS 'Location', w.month_year AS 'Month/Year', w.consumption_m3 AS 'Consumption (m³)', w.cost AS 'Cost (PHP)', w.data_source AS 'Data Source', w.notes AS 'Notes' FROM water_consumption_records w ORDER BY w.month_year DESC, w.date_recorded DESC";
        $stmt = $pdo->query($sql);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $headers = ['Record ID','Asset Type','Facility Name','Location','Month/Year','Consumption (m³)','Cost (PHP)','Data Source','Notes'];
        $filename = 'water_export_' . date('Y-m-d');
        break;
    case 'facilities':
        $sql = "SELECT f.facility_id AS 'Facility ID', f.name AS 'Facility Name', f.facility_type AS 'Facility Type', f.location AS 'Location', f.utility_status AS 'Utility Status', f.description AS 'Description', s.water_available AS 'Water Available', s.electricity_available AS 'Electricity Available', s.drainage_ok AS 'Drainage OK', s.lighting_ok AS 'Lighting OK' FROM public_facilities f JOIN facility_utility_status s ON f.id = s.public_facility_id ORDER BY f.name";
        $stmt = $pdo->query($sql);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $headers = ['Facility ID','Facility Name','Facility Type','Location','Utility Status','Description','Water','Electricity','Drainage','Lighting'];
        $filename = 'facilities_export_' . date('Y-m-d');
        break;
    case 'users':
        $sql = "SELECT id AS 'User ID', full_name AS 'Full Name', email AS 'Email', user_type AS 'Role', CASE WHEN is_active=1 THEN 'Active' ELSE 'Inactive' END AS 'Status', created_at AS 'Registered Date' FROM users ORDER BY id DESC";
        $stmt = $pdo->query($sql);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $headers = ['User ID','Full Name','Email','Role','Status','Registered Date'];
        $filename = 'users_export_' . date('Y-m-d');
        break;
}

if (empty($data)) die('No data to export.');

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers);
    foreach ($data as $row) fputcsv($out, array_values($row));
    fclose($out);
    exit;
}

if ($format === 'pdf') {
    if (!class_exists('TCPDF')) {
        if (file_exists(__DIR__ . '/vendor/autoload.php')) {
            require_once __DIR__ . '/vendor/autoload.php';
        } elseif (file_exists(__DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php')) {
            require_once __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php';
        }
    }

    if (!class_exists('TCPDF')) {
        // Fallback: Printable report view that opens print-to-PDF dialog
        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title><?php echo strtoupper($type); ?> Export Report</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; padding: 25px; color: #1e293b; background: #fff; }
                h1 { font-size: 20px; color: #1e293b; text-align: center; margin-bottom: 4px; text-transform: uppercase; }
                .meta { text-align: center; font-size: 12px; color: #64748b; margin-bottom: 20px; }
                table { width: 100%; border-collapse: collapse; font-size: 10px; }
                th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
                th { background-color: #3762c8; color: #fff; font-weight: 600; }
                tr:nth-child(even) { background-color: #f8fafc; }
                .actions { margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; }
                .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 13px; text-decoration: none; cursor: pointer; border: 1px solid #cbd5e1; background: #f8fafc; color: #1e293b; }
                .btn-print { background: #3762c8; color: #fff; border-color: #3762c8; }
                @media print {
                    .actions { display: none !important; }
                    body { padding: 0; }
                }
            </style>
        </head>
        <body>
            <div class="actions">
                <a href="export_dashboard.php" class="btn">&larr; Back to Export Center</a>
                <button onclick="window.print()" class="btn btn-print">&#128424; Print / Save as PDF</button>
            </div>
            <h1><?php echo htmlspecialchars(strtoupper($type)); ?> Export Report</h1>
            <div class="meta">Generated: <?php echo date('Y-m-d H:i:s'); ?> &bull; Total Records: <?php echo count($data); ?></div>
            <table>
                <thead>
                    <tr>
                        <?php foreach ($headers as $h): ?>
                            <th><?php echo htmlspecialchars($h); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $row): ?>
                        <tr>
                            <?php foreach (array_values($row) as $val): ?>
                                <td><?php echo htmlspecialchars($val === null || $val === '' ? '—' : $val); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <script>
                window.onload = function() { window.print(); };
            </script>
        </body>
        </html>
        <?php
        exit;
    }

    // Clean output buffer to ensure binary PDF stream is not corrupted
    while (ob_get_level()) {
        ob_end_clean();
    }

    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(true, 12);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, strtoupper($type) . ' EXPORT', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, 'Generated: ' . date('Y-m-d H:i:s') . ' | Total Records: ' . count($data), 0, 1, 'C');
    $pdf->Ln(4);
    $html = '<table border="1" cellpadding="4" style="font-size:8px; border-collapse:collapse; width:100%;">';
    $html .= '<thead><tr style="background-color:#3762c8; color:white; font-weight:bold;">';
    foreach ($headers as $h) $html .= '<th style="padding:6px 6px; text-align:center;">' . htmlspecialchars($h) . '</th>';
    $html .= '</tr></thead><tbody>';
    $i = 0;
    foreach ($data as $row) {
        $bg = ($i % 2 === 0) ? '#f8f9fa' : '#ffffff';
        $html .= '<tr style="background-color:' . $bg . ';">';
        foreach (array_values($row) as $val) {
            $html .= '<td style="padding:4px 5px;">' . htmlspecialchars($val === null || $val === '' ? '—' : $val) . '</td>';
        }
        $html .= '</tr>';
        $i++;
    }
    $html .= '</tbody></table>';
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output($filename . '.pdf', 'D');
    exit;
}
?>