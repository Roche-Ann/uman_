<?php
/**
 * Application Configuration
 */

return [
    'app_name' => 'LGU Urban Planning and Development System',
    'app_version' => '1.0.0',
    'base_url' => 'http://localhost/lgu-urban-planning',
    'timezone' => 'Asia/Manila',
    'session_lifetime' => 7200, // 2 hours
    'upload_path' => __DIR__ . '/../uploads/',
    'max_file_size' => 10485760, // 10MB
    'allowed_file_types' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'dwg', 'dxf'],
    'email' => [
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => 587,
        'smtp_username' => '',
        'smtp_password' => '',
        'from_email' => 'noreply@lgu.gov.ph',
        'from_name' => 'LGU Urban Planning System'
    ]
];

