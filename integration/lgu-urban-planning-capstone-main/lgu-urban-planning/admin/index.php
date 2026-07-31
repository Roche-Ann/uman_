<?php

// Admin Dashboard - Staff/Admin Side

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helper.php';

$auth = new Auth();

$auth->requireLogin();
$auth->requireRole(['admin', 'super_admin', 'zoning_officer', 'building_official', 'assessor', 'inspector']);

$db = Database::getInstance();

// Get dashboard data based on role
require_once __DIR__ . '/../modules/PermitProcessing/PermitController.php';
$permitController = new PermitController();
$dashboardData['stats']               = $permitController->getDashboardStats();
$dashboardData['recent_applications'] = $permitController->getApplications(['status' => 'submitted']);

$isAuthPage = true;

include __DIR__ . '/header.php';
include __DIR__ . '/dashboard.php';
include __DIR__ . '/footer.php';