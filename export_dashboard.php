<?php
// export_dashboard.php - Export Management Dashboard
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn() || !isEmployee()) {
    header('Location: login.php');
    exit();
}

// Get counts for each module to show on the dashboard
$counts = [];
try {
    $counts['assets'] = $pdo->query("SELECT COUNT(*) FROM utility_assets")->fetchColumn();
} catch (Throwable $e) { $counts['assets'] = 0; }

try {
    $counts['incidents'] = $pdo->query("SELECT COUNT(*) FROM utility_incidents")->fetchColumn();
} catch (Throwable $e) { $counts['incidents'] = 0; }

try {
    $counts['maintenance'] = $pdo->query("SELECT COUNT(*) FROM maintenance_requests")->fetchColumn();
} catch (Throwable $e) { $counts['maintenance'] = 0; }

try {
    $counts['energy'] = $pdo->query("SELECT COUNT(*) FROM energy_consumption_records")->fetchColumn();
} catch (Throwable $e) { $counts['energy'] = 0; }

try {
    $counts['facilities'] = $pdo->query("SELECT COUNT(*) FROM public_facilities")->fetchColumn();
} catch (Throwable $e) { $counts['facilities'] = 0; }

try {
    $counts['users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
} catch (Throwable $e) { $counts['users'] = 0; }

$userName = $_SESSION['user_name'] ?? $_SESSION['full_name'] ?? 'LGU Coordinator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Data | LGU Portal</title>
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            background: url("assets/images/cityhall.jpeg") center/cover no-repeat fixed;
            position: relative;
        }

        body::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            backdrop-filter: blur(6px);
            background: rgba(0, 0, 0, 0.35);
            z-index: 0;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px 40px;
            transition: margin-left 0.25s ease;
            z-index: 1;
            position: relative;
        }

        .main-content.collapsed {
            margin-left: 90px;
        }

        .card {
            width: 100%;
            max-width: 1700px;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(15px);
            border-radius: 18px;
            padding: 40px;
            color: #000;
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,0.25);
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .dashboard-header h1 {
            color: #2c3e50;
            font-size: 32px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .dashboard-header h1 i {
            color: #3762c8;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: #3762c8;
            color: white;
        }
        .btn-primary:hover {
            background: #2851b0;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-success:hover {
            background: #218838;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #cbd5e1;
            color: #64748b;
        }
        .btn-outline:hover {
            background: #f8f9fa;
            color: #2c3e50;
        }

        .export-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
        }

        .export-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .export-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        .export-card .icon {
            font-size: 32px;
            margin-bottom: 12px;
        }

        .export-card h3 {
            font-size: 18px;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .export-card p {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 15px;
        }

        .export-card .count {
            display: inline-block;
            background: #f1f5f9;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 12px;
            color: #475569;
            margin-bottom: 15px;
        }

        .export-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .export-actions .btn {
            flex: 1;
            justify-content: center;
            padding: 8px 12px;
            font-size: 12px;
            min-width: 70px;
        }

        .icon-assets { color: #4b7bec; }
        .icon-incidents { color: #f1c40f; }
        .icon-maintenance { color: #e74c3c; }
        .icon-energy { color: #a55eea; }
        .icon-facilities { color: #45aaf2; }
        .icon-users { color: #2ecc71; }
    </style>
</head>
<body>

<?php include 'includes/utilities_sidebar.php'; ?>

<main class="main-content" id="mainContent">
    <div class="card">
        
        <!-- Header -->
        <div class="dashboard-header">
            <div>
                <h1><i class="fas fa-file-export"></i> Data Export Center</h1>
                <p style="color: #64748b; font-size: 14px; margin-top: 5px;">Export data from all modules in CSV or PDF format.</p>
            </div>
            <div>
                <a href="utilities_dashboard.php" class="btn btn-outline"><i class="fas fa-chevron-left"></i> Dashboard</a>
            </div>
        </div>

        <!-- Export Grid -->
        <div class="export-grid">
            
            <!-- Assets -->
            <div class="export-card">
                <div class="icon icon-assets"><i class="fas fa-boxes"></i></div>
                <h3>Assets</h3>
                <p>Export all utility assets with their status, location, and category.</p>
                <span class="count"><?php echo number_format($counts['assets']); ?> records</span>
                <div class="export-actions">
                    <a href="export.php?type=assets&format=csv" class="btn btn-success"><i class="fas fa-file-csv"></i> CSV</a>
                    <a href="export.php?type=assets&format=pdf" class="btn btn-danger"><i class="fas fa-file-pdf"></i> PDF</a>
                </div>
            </div>

            <!-- Incidents -->
            <div class="export-card">
                <div class="icon icon-incidents"><i class="fas fa-exclamation-triangle"></i></div>
                <h3>Incidents</h3>
                <p>Export all incident reports with status, priority, and category.</p>
                <span class="count"><?php echo number_format($counts['incidents']); ?> records</span>
                <div class="export-actions">
                    <a href="export.php?type=incidents&format=csv" class="btn btn-success"><i class="fas fa-file-csv"></i> CSV</a>
                    <a href="export.php?type=incidents&format=pdf" class="btn btn-danger"><i class="fas fa-file-pdf"></i> PDF</a>
                </div>
            </div>

            <!-- Maintenance -->
            <div class="export-card">
                <div class="icon icon-maintenance"><i class="fas fa-tools"></i></div>
                <h3>Maintenance</h3>
                <p>Export all maintenance requests with status, priority, and source.</p>
                <span class="count"><?php echo number_format($counts['maintenance']); ?> records</span>
                <div class="export-actions">
                    <a href="export.php?type=maintenance&format=csv" class="btn btn-success"><i class="fas fa-file-csv"></i> CSV</a>
                    <a href="export.php?type=maintenance&format=pdf" class="btn btn-danger"><i class="fas fa-file-pdf"></i> PDF</a>
                </div>
            </div>

            <!-- Energy -->
            <div class="export-card">
                <div class="icon icon-energy"><i class="fas fa-bolt"></i></div>
                <h3>Energy</h3>
                <p>Export energy consumption records with cost and asset type.</p>
                <span class="count"><?php echo number_format($counts['energy']); ?> records</span>
                <div class="export-actions">
                    <a href="export.php?type=energy&format=csv" class="btn btn-success"><i class="fas fa-file-csv"></i> CSV</a>
                    <a href="export.php?type=energy&format=pdf" class="btn btn-danger"><i class="fas fa-file-pdf"></i> PDF</a>
                </div>
            </div>

            <!-- Facilities -->
            <div class="export-card">
                <div class="icon icon-facilities"><i class="fas fa-warehouse"></i></div>
                <h3>Facilities</h3>
                <p>Export public facilities with utility readiness status.</p>
                <span class="count"><?php echo number_format($counts['facilities']); ?> records</span>
                <div class="export-actions">
                    <a href="export.php?type=facilities&format=csv" class="btn btn-success"><i class="fas fa-file-csv"></i> CSV</a>
                    <a href="export.php?type=facilities&format=pdf" class="btn btn-danger"><i class="fas fa-file-pdf"></i> PDF</a>
                </div>
            </div>

            <!-- Users -->
            <div class="export-card">
                <div class="icon icon-users"><i class="fas fa-users"></i></div>
                <h3>Users</h3>
                <p>Export all user accounts with roles and status.</p>
                <span class="count"><?php echo number_format($counts['users']); ?> records</span>
                <div class="export-actions">
                    <a href="export.php?type=users&format=csv" class="btn btn-success"><i class="fas fa-file-csv"></i> CSV</a>
                    <a href="export.php?type=users&format=pdf" class="btn btn-danger"><i class="fas fa-file-pdf"></i> PDF</a>
                </div>
            </div>

        </div>

        <!-- Instructions -->
        <div style="margin-top: 30px; padding: 20px; background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0;">
            <h4 style="color: #2c3e50; margin-bottom: 10px;"><i class="fas fa-info-circle"></i> Export Instructions</h4>
            <ul style="color: #64748b; font-size: 13px; line-height: 2; padding-left: 20px;">
                <li><strong>CSV</strong> – Opens in Excel, Google Sheets, or any spreadsheet software.</li>
                <li><strong>PDF</strong> – Printable report format with table layout.</li>
                <li><strong>All data</strong> is exported based on your current filters. Use the search/filter pages to narrow down results before exporting.</li>
                <li><strong>Large datasets</strong> may take a few seconds to generate.</li>
            </ul>
        </div>

    </div>
</main>

</body>
</html>