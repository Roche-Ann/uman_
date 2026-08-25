-- sql/utility_water.sql

-- 1. Create water consumption records table
CREATE TABLE IF NOT EXISTS `water_consumption_records` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `record_id` VARCHAR(50) NOT NULL UNIQUE,
  `source_system` VARCHAR(50) NOT NULL DEFAULT 'UMAN',
  `cprf_facility_id` INT NULL,
  `external_ref` VARCHAR(60) NULL,
  `utility_asset_id` INT NULL,
  `facility_name` VARCHAR(150) NULL,
  `asset_type` VARCHAR(100) NOT NULL DEFAULT 'Water Infrastructure',
  `location` TEXT NOT NULL,
  `month_year` VARCHAR(20) NOT NULL, -- Format: 'YYYY-MM'
  `previous_reading` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `current_reading` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `consumption_m3` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `rate_per_m3` DECIMAL(10, 2) NOT NULL DEFAULT 68.02,
  `cost` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `data_source` ENUM('Manual Input', 'Imported', 'CPRF Integration') NOT NULL DEFAULT 'Manual Input',
  `notes` TEXT NULL,
  `date_recorded` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`utility_asset_id`) REFERENCES `utility_assets`(`id`) ON DELETE SET NULL,
  UNIQUE KEY `uk_wcr_external_ref` (`external_ref`),
  INDEX `idx_wcr_cprf_facility` (`cprf_facility_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create water sync logs table
CREATE TABLE IF NOT EXISTS `water_sync_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sync_type` ENUM('Outbound Data Send', 'Inbound Recommendation Pull') NOT NULL,
  `payload_exported` LONGTEXT NULL,
  `records_count` INT DEFAULT 0,
  `status` ENUM('Pending', 'Sent', 'Successful', 'Failed') NOT NULL DEFAULT 'Pending',
  `error_details` TEXT NULL,
  `transferred_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create water recommendations table
CREATE TABLE IF NOT EXISTS `water_recommendations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `recommendation_title` VARCHAR(200) NOT NULL,
  `description` TEXT NOT NULL,
  `target_facility_asset` VARCHAR(150) NOT NULL,
  `priority_level` ENUM('Low', 'Medium', 'High', 'Emergency') NOT NULL DEFAULT 'Medium',
  `status` ENUM('Pending', 'Acknowledged', 'Implemented', 'Archived') NOT NULL DEFAULT 'Pending',
  `remarks` TEXT NULL,
  `date_received` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `date_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Create water notifications table
CREATE TABLE IF NOT EXISTS `water_notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `message` TEXT NOT NULL,
  `read_status` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
