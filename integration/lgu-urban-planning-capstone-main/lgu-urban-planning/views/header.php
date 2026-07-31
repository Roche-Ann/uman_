<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>LGU Urban Planning System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            background-attachment: fixed;
            min-height: 100vh;
        }
        
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #1e3a8a 0%, #1e40af 50%, #1e3a8a 100%);
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .sidebar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><defs><pattern id="grid" width="20" height="20" patternUnits="userSpaceOnUse"><path d="M 20 0 L 0 0 0 20" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.3;
        }
        
        .sidebar > * {
            position: relative;
            z-index: 1;
        }
        
        .sidebar h4 {
            font-weight: 700;
            font-size: 1.5rem;
            letter-spacing: -0.5px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.9);
            padding: 14px 20px;
            margin: 4px 12px;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .sidebar .nav-link i {
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }
        
        .sidebar .nav-link:hover {
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: #fff;
            box-shadow: 0 4px 16px rgba(59, 130, 246, 0.4);
            font-weight: 600;
        }
        
        .main-content {
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
            min-height: 100vh;
            padding: 30px;
        }
        
        .card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.12);
        }
        
        .card-header {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 20px 25px;
            font-weight: 600;
        }
        
        .card-body {
            padding: 25px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            border: none;
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
        }
        
        .btn-outline-light {
            border-radius: 12px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-outline-light:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
        }
        
        .badge {
            border-radius: 8px;
            padding: 6px 12px;
            font-weight: 600;
        }
        
        h2 {
            font-weight: 700;
            color: #1e293b;
            letter-spacing: -0.5px;
        }
        
        .table {
            border-radius: 12px;
            overflow: hidden;
        }
        
        .table thead {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        
        .table tbody tr {
            transition: all 0.2s ease;
        }
        
        .table tbody tr:hover {
            background-color: #f8f9fa;
            transform: scale(1.01);
        }
    </style>
</head>
<body>
    <?php if (isset($_SESSION['user_id'])): ?>
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-2 sidebar">
                <div class="p-3">
                    <h4 class="text-white mb-4">LGU Planning</h4>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <?php
                            $role = $_SESSION['role'] ?? 'applicant';
                            $dashboardUrl = ($role === 'applicant') ? '/lgu-urban-planning/user/index.php' : '/lgu-urban-planning/admin/index.php';
                            ?>
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="<?php echo $dashboardUrl; ?>">
                                <i class="bi bi-house-door"></i> Dashboard
                            </a>
                        </li>
                        
                        <?php if ($_SESSION['role'] === 'applicant'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="/lgu-urban-planning/applicant/apply.php">
                                    <i class="bi bi-file-earmark-plus"></i> Submit Application
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/lgu-urban-planning/applicant/applications.php">
                                    <i class="bi bi-list-ul"></i> My Applications
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/lgu-urban-planning/applicant/messages.php">
                                    <i class="bi bi-envelope"></i> Messages
                                    <?php
                                    require_once __DIR__ . '/../modules/ApplicantSelfService/ApplicantController.php';
                                    $appController = new ApplicantController();
                                    $unread = $appController->getUnreadMessageCount();
                                    if ($unread > 0): ?>
                                        <span class="badge bg-danger"><?php echo $unread; ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php if (in_array($_SESSION['role'], ['admin', 'zoning_officer', 'building_official'])): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="/lgu-urban-planning/permit/applications.php">
                                    <i class="bi bi-file-earmark-text"></i> Applications
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/lgu-urban-planning/gis/map.php">
                                    <i class="bi bi-map"></i> GIS Map
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="/lgu-urban-planning/admin/users.php">
                                    <i class="bi bi-people"></i> User Management
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/lgu-urban-planning/admin/audit-logs.php">
                                    <i class="bi bi-journal-text"></i> Audit Logs
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php if (in_array($_SESSION['role'], ['admin', 'zoning_officer'])): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="/lgu-urban-planning/reports/index.php">
                                    <i class="bi bi-graph-up"></i> Reports
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="p-3 border-top border-secondary">
                    <div class="text-white mb-2">
                        <small><?php echo htmlspecialchars($_SESSION['full_name']); ?></small><br>
                        <small class="text-muted"><?php echo Helper::getRoleName($_SESSION['role']); ?></small>
                    </div>
                    <a href="/lgu-urban-planning/logout.php" class="btn btn-sm btn-outline-light w-100">Logout</a>
                </div>
            </nav>
            <main class="col-md-10 main-content">
    <?php endif; ?>

