-- sql/utility_maintenance.sql

-- 1. Create maintenance requests table
CREATE TABLE IF NOT EXISTS `maintenance_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `request_id` VARCHAR(50) NOT NULL UNIQUE,
  `utility_asset_id` INT NULL,
  `source` ENUM('Resident Report', 'Asset Monitoring', 'Emergency Alert') NOT NULL DEFAULT 'Asset Monitoring',
  `description` TEXT NOT NULL,
  `priority` ENUM('Low', 'Medium', 'High', 'Emergency') NOT NULL DEFAULT 'Medium',
  `location` TEXT NOT NULL,
  `status` ENUM('Created', 'Forwarded', 'Accepted by Maintenance System', 'In Progress', 'Completed', 'Closed') NOT NULL DEFAULT 'Created',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`utility_asset_id`) REFERENCES `utility_assets`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create maintenance forwarding logs table
CREATE TABLE IF NOT EXISTS `maintenance_forwarding_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `maintenance_request_id` INT NOT NULL,
  `target_system` VARCHAR(100) NOT NULL DEFAULT 'Maintenance System',
  `forwarded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `external_ref_id` VARCHAR(50) NULL,
  `status` ENUM('Not Sent', 'Sent', 'Accepted', 'Rejected') NOT NULL DEFAULT 'Not Sent',
  FOREIGN KEY (`maintenance_request_id`) REFERENCES `maintenance_requests`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create maintenance status logs table
CREATE TABLE IF NOT EXISTS `maintenance_status_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `maintenance_request_id` INT NOT NULL,
  `old_status` VARCHAR(50) NULL,
  `new_status` VARCHAR(50) NOT NULL,
  `changed_by` INT NOT NULL,
  `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `notes` TEXT NULL,
  FOREIGN KEY (`maintenance_request_id`) REFERENCES `maintenance_requests`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Create maintenance asset links table
CREATE TABLE IF NOT EXISTS `maintenance_asset_links` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `maintenance_request_id` INT NOT NULL,
  `utility_asset_id` INT NOT NULL,
  `linked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`maintenance_request_id`) REFERENCES `maintenance_requests`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`utility_asset_id`) REFERENCES `utility_assets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Create maintenance notifications table
CREATE TABLE IF NOT EXISTS `maintenance_notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL, -- Target user ID (admin or resident)
  `message` TEXT NOT NULL,
  `read_status` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Create maintenance history table
CREATE TABLE IF NOT EXISTS `maintenance_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `maintenance_request_id` INT NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `performed_by` INT NOT NULL,
  `performed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `details` TEXT NULL,
  FOREIGN KEY (`maintenance_request_id`) REFERENCES `maintenance_requests`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
