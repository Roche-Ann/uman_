-- sql/utility_assets.sql

-- 1. Create asset types table if not exists
CREATE TABLE IF NOT EXISTS `asset_types` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE,
  `description` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create utility assets table
CREATE TABLE IF NOT EXISTS `utility_assets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `asset_id` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `asset_type_id` INT NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `location` TEXT NOT NULL,
  `latitude` DECIMAL(10, 8) NULL,
  `longitude` DECIMAL(11, 8) NULL,
  `date_installed` DATE NOT NULL,
  `condition_status` ENUM('Operational', 'Needs Inspection', 'Damaged', 'Under Maintenance') NOT NULL DEFAULT 'Operational',
  `description` TEXT NULL,
  `responsible_office` VARCHAR(100) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`asset_type_id`) REFERENCES `asset_types`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create asset status logs table
CREATE TABLE IF NOT EXISTS `asset_status_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `utility_asset_id` INT NOT NULL,
  `old_status` VARCHAR(50) NULL,
  `new_status` VARCHAR(50) NOT NULL,
  `changed_by` INT NOT NULL,
  `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `notes` TEXT NULL,
  `report_ref` VARCHAR(50) NULL,
  FOREIGN KEY (`utility_asset_id`) REFERENCES `utility_assets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Create asset locations table
CREATE TABLE IF NOT EXISTS `asset_locations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `utility_asset_id` INT NOT NULL,
  `old_location` TEXT NULL,
  `new_location` TEXT NOT NULL,
  `old_latitude` DECIMAL(10, 8) NULL,
  `new_latitude` DECIMAL(10, 8) NULL,
  `old_longitude` DECIMAL(11, 8) NULL,
  `new_longitude` DECIMAL(11, 8) NULL,
  `changed_by` INT NOT NULL,
  `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`utility_asset_id`) REFERENCES `utility_assets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Create asset images table
CREATE TABLE IF NOT EXISTS `asset_images` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `utility_asset_id` INT NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`utility_asset_id`) REFERENCES `utility_assets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Create asset notifications table
CREATE TABLE IF NOT EXISTS `asset_notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `type` VARCHAR(50) NOT NULL,
  `message` TEXT NOT NULL,
  `read_status` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Seed asset types
INSERT INTO `asset_types` (`name`, `description`) VALUES
('Streetlight', 'Public street illumination lights and solar-powered posts'),
('Drainage System', 'Storm drainage networks, manholes, culverts, and gratings'),
('Water Pipeline', 'LGU-managed main water distribution lines and valves'),
('Electrical Utility Pole', 'LGU-managed power distribution poles and public safety lines'),
('Public Utility Infrastructure', 'Other community structures, water pumps, reservoirs, and public facilities')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);
