-- ============================================================
-- 1. AI Scoring Weights Configuration
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

-- ============================================================
-- 2. AI Inspection Audit & Training Logs
-- ============================================================
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

-- ============================================================
-- 3. Ensure UPAD inspection requests table has AI columns
-- ============================================================
CREATE TABLE IF NOT EXISTS `upad_inspection_requests` (
  `id`                     INT AUTO_INCREMENT PRIMARY KEY,
  `reference_id`           VARCHAR(30)  NOT NULL UNIQUE,
  `application_id`         INT          NOT NULL,
  `source_system`          VARCHAR(20)  NOT NULL DEFAULT 'UPAD',
  `project_name`           VARCHAR(255) NULL,
  `barangay`               VARCHAR(100) NULL,
  `district`               VARCHAR(50)  NULL,
  `category`               VARCHAR(80)  NULL,
  `estimated_load_kva`     DECIMAL(10,2) NULL,
  `priority`               ENUM('Urgent','Medium','Low') NOT NULL DEFAULT 'Medium',
  `address`                TEXT         NULL,
  `latitude`               DECIMAL(10,7) NULL,
  `longitude`              DECIMAL(10,7) NULL,
  `description`            TEXT         NULL,
  `requested_by`           VARCHAR(150) NULL,
  `callback_url`           TEXT         NULL,
  `status`                 ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `ai_score`               DECIMAL(5,2) NULL,
  `ai_decision`            VARCHAR(50)  NULL,
  `raw_payload`            JSON         NULL,
  `result_payload`         JSON         NULL,
  `callback_sent_at`       DATETIME     NULL,
  `callback_http_code`     SMALLINT     NULL,
  `callback_error`         TEXT         NULL,
  `created_at`             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_application_id` (`application_id`),
  INDEX `idx_status`         (`status`),
  INDEX `idx_created_at`     (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. Seed Real Coverage, Capacity & Incident Data for Testing
-- ============================================================
INSERT INTO `utility_coverage_records` (`area_name`, `latitude`, `longitude`, `radius_meters`, `coverage_type`, `coverage_status`, `remarks`) VALUES
('Quiapo', 14.59833300, 120.98500000, 1500, 'Electrical', 'Fully Covered', 'Primary grid substation operational with 3-phase high voltage line.'),
('Santa Mesa', 14.60194400, 121.00833300, 1200, 'Electrical', 'Fully Covered', 'Commercial and residential distribution lines active.'),
('Malate', 14.56388900, 120.99472200, 1000, 'Electrical', 'Fully Covered', 'Microgrid and smart lighting corridor integrated.'),
('Sampaloc', 14.60833300, 120.99444400, 1200, 'Electrical', 'Partially Covered', 'Upgrade in progress for secondary transformer bank.'),
('Tondo', 14.61527800, 120.96944400, 1500, 'Electrical', 'Fully Covered', 'North extension line active with backup industrial breaker.')
ON DUPLICATE KEY UPDATE `coverage_status` = VALUES(`coverage_status`);

INSERT INTO `utility_capacity_records` (`location_zone`, `capacity_type`, `max_capacity`, `current_load`, `unit`, `status`) VALUES
('Quiapo Commercial Zone', 'Electrical Grid Load', 5000.00, 3200.00, 'kVA', 'Normal'),
('Santa Mesa District 6', 'Electrical Grid Load', 4000.00, 2450.00, 'kVA', 'Normal'),
('Malate Smart Corridor', 'Electrical Grid Load', 3500.00, 2800.00, 'kVA', 'Normal'),
('Sampaloc Zone 4', 'Electrical Grid Load', 3000.00, 2650.00, 'kVA', 'Near Capacity'),
('Tondo Industrial Port Area', 'Electrical Grid Load', 8000.00, 4100.00, 'kVA', 'Normal')
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`), `current_load` = VALUES(`current_load`);
