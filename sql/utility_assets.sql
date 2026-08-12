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
  `status` ENUM('Operational', 'Under Maintenance', 'Non-Operational', 'Retired', 'Disposed') NOT NULL DEFAULT 'Operational',
  `condition` ENUM('Excellent', 'Good', 'Fair', 'Poor', 'Critical') NOT NULL DEFAULT 'Good',
  `serial_number` VARCHAR(100) NULL,
  `model_brand` VARCHAR(100) NULL,
  `primary_photo` VARCHAR(255) NULL,
  `warranty_doc` VARCHAR(255) NULL,
  `purchase_doc` VARCHAR(255) NULL,
  `inspection_doc` VARCHAR(255) NULL,
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

-- 7. Create asset inspections table
CREATE TABLE IF NOT EXISTS `asset_inspections` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `utility_asset_id` INT NOT NULL,
  `inspection_date` DATE NOT NULL,
  `next_inspection_date` DATE NULL,
  `inspector_name` VARCHAR(100) NOT NULL,
  `findings` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`utility_asset_id`) REFERENCES `utility_assets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Create asset audit logs table
CREATE TABLE IF NOT EXISTS `asset_audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `utility_asset_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `action_type` VARCHAR(50) NOT NULL,
  `old_value` TEXT NULL,
  `new_value` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`utility_asset_id`) REFERENCES `utility_assets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Seed asset types
INSERT INTO `asset_types` (`name`, `description`) VALUES
('Streetlight', 'Public street illumination lights and solar-powered posts'),
('Drainage System', 'Storm drainage networks, manholes, culverts, and gratings'),
('Water Pipeline', 'LGU-managed main water distribution lines and valves'),
('Electrical Utility Pole', 'LGU-managed power distribution poles and public safety lines'),
('Public Utility Infrastructure', 'Other community structures, water pumps, reservoirs, and public facilities')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);
