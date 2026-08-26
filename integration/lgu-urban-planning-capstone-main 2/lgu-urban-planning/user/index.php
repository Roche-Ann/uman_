<?php

// User Dashboard - Applicant Side

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helper.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requireRole('applicant');

$db = Database::getInstance();

// Get dashboard data for applicant
require_once __DIR__ . '/../modules/ApplicantSelfService/ApplicantController.php';
$applicantController = new ApplicantController();
$dashboardData['my_applications'] = $applicantController->getMyApplications();
$dashboardData['unread_messages'] = $applicantController->getUnreadMessageCount();

$isAuthPage = true; // Enables session timeout timer in user.js
include __DIR__ . '/header.php';
include __DIR__ . '/dashboard.php';
include __DIR__ . '/footer.php';