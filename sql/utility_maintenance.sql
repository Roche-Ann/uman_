-- Database Schema for Maintenance Management Module
-- Tables: maintenance_requests, maintenance_status_logs

CREATE TABLE IF NOT EXISTS `maintenance_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` varchar(50) NOT NULL,
  `utility_asset_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `maintenance_type` enum('Corrective','Preventive','Emergency','Routine') NOT NULL DEFAULT 'Corrective',
  `source` varchar(100) NOT NULL DEFAULT 'Asset Monitoring',
  `description` text DEFAULT NULL,
  `priority` enum('Low','Medium','High','Emergency') NOT NULL DEFAULT 'Medium',
  `assigned_to` varchar(150) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `status` enum('Reported','Scheduled','In Progress','On Hold','Testing','Completed','Unrepairable','Cancelled') NOT NULL DEFAULT 'Reported',
  `progress_percent` int(11) NOT NULL DEFAULT 0,
  `scheduled_date` date DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_request_id` (`request_id`),
  KEY `idx_asset_id` (`utility_asset_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `maintenance_status_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `maintenance_request_id` int(11) NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `old_progress` int(11) NOT NULL DEFAULT 0,
  `new_progress` int(11) NOT NULL DEFAULT 0,
  `changed_by` int(11) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_req_log` (`maintenance_request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
