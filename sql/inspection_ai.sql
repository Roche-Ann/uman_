-- ============================================================
-- AI Scoring Weights & Audit Logs Schema
-- ============================================================

CREATE TABLE IF NOT EXISTS `ai_weights` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `factor_key` VARCHAR(50) NOT NULL UNIQUE,
  `factor_name` VARCHAR(100) NOT NULL,
  `weight_percent` DECIMAL(5,2) NOT NULL DEFAULT 25.00,
  `description` TEXT NULL,
  `updated_by` VARCHAR(100) DEFAULT 'System',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `ai_weights` (`factor_key`, `factor_name`, `weight_percent`, `description`) VALUES
('coverage',  'Utility Coverage Status',   30.00, 'Weighs Full (100), Partial (50), or Not Covered (0)'),
('assets',    'Asset Operational Health',  25.00, 'Weighs percentage of operational vs damaged assets in zone'),
('capacity',  'Grid & Substation Capacity', 25.00, 'Weighs Normal (100), Near Capacity (60), Overloaded (20)'),
('incidents', 'Active Incident Clearance', 20.00, 'Weighs active incidents: 0 (100), 1-2 (70), >2 (30)')
ON DUPLICATE KEY UPDATE `factor_name` = VALUES(`factor_name`), `description` = VALUES(`description`);

CREATE TABLE IF NOT EXISTS `inspection_ai_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `request_id` VARCHAR(50) NOT NULL,
  `location` VARCHAR(255) NOT NULL,
  `utility_type` VARCHAR(80) NOT NULL DEFAULT 'Electrical',
  `project_id` INT NULL,
  `coverage_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `asset_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `capacity_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `incident_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `weights_applied` JSON NOT NULL,
  `final_ai_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `ai_decision` ENUM('Approved', 'Conditional', 'Rejected') NOT NULL,
  `factors_breakdown` JSON NULL,
  `is_overridden` TINYINT(1) NOT NULL DEFAULT 0,
  `override_decision` ENUM('Approved', 'Conditional', 'Rejected') NULL,
  `override_reason` TEXT NULL,
  `overridden_by` VARCHAR(100) NULL,
  `overridden_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_request_id` (`request_id`),
  INDEX `idx_ai_decision` (`ai_decision`),
  INDEX `idx_is_overridden` (`is_overridden`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
