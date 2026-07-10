-- sql/utility_energy.sql

-- 1. Create energy consumption records table
CREATE TABLE IF NOT EXISTS `energy_consumption_records` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `record_id` VARCHAR(50) NOT NULL UNIQUE,
  `utility_asset_id` INT NULL,
  `facility_name` VARCHAR(150) NULL,
  `asset_type` VARCHAR(100) NOT NULL DEFAULT 'Streetlight',
  `location` TEXT NOT NULL,
  `month_year` VARCHAR(20) NOT NULL, -- Format: 'YYYY-MM'
  `consumption_kwh` DECIMAL(12, 2) NOT NULL,
  `cost` DECIMAL(12, 2) NULL,
  `data_source` ENUM('Manual Input', 'Imported') NOT NULL DEFAULT 'Manual Input',
  `notes` TEXT NULL,
  `date_recorded` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`utility_asset_id`) REFERENCES `utility_assets`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create energy sync logs table
CREATE TABLE IF NOT EXISTS `energy_sync_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sync_type` ENUM('Outbound Data Send', 'Inbound Recommendation Pull') NOT NULL,
  `payload_exported` LONGTEXT NULL,
  `records_count` INT DEFAULT 0,
  `status` ENUM('Pending', 'Sent', 'Successful', 'Failed') NOT NULL DEFAULT 'Pending',
  `error_details` TEXT NULL,
  `transferred_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create energy recommendations table
CREATE TABLE IF NOT EXISTS `energy_recommendations` (
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

-- 4. Create energy notifications table
CREATE TABLE IF NOT EXISTS `energy_notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `message` TEXT NOT NULL,
  `read_status` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
