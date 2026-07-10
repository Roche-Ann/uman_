-- sql/utility_incidents.sql

-- 1. Create incident categories table
CREATE TABLE IF NOT EXISTS `incident_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE,
  `description` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create incidents table
CREATE TABLE IF NOT EXISTS `utility_incidents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `incident_id` VARCHAR(50) NOT NULL UNIQUE,
  `category_id` INT NOT NULL,
  `description` TEXT NOT NULL,
  `location` TEXT NOT NULL,
  `latitude` DECIMAL(10, 8) NULL,
  `longitude` DECIMAL(11, 8) NULL,
  `status` ENUM('Submitted', 'Under Review', 'Verified', 'Forwarded to Maintenance System', 'In Progress', 'Resolved', 'Closed') NOT NULL DEFAULT 'Submitted',
  `priority` ENUM('Low', 'Medium', 'High', 'Emergency') NOT NULL DEFAULT 'Medium',
  `resident_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `incident_categories`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create incident asset links table
CREATE TABLE IF NOT EXISTS `incident_asset_links` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `utility_incident_id` INT NOT NULL,
  `utility_asset_id` INT NOT NULL,
  `linked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`utility_incident_id`) REFERENCES `utility_incidents`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`utility_asset_id`) REFERENCES `utility_assets`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_incident_asset` (`utility_incident_id`, `utility_asset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Create incident status logs table
CREATE TABLE IF NOT EXISTS `incident_status_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `utility_incident_id` INT NOT NULL,
  `old_status` VARCHAR(50) NULL,
  `new_status` VARCHAR(50) NOT NULL,
  `changed_by` INT NOT NULL,
  `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `notes` TEXT NULL,
  FOREIGN KEY (`utility_incident_id`) REFERENCES `utility_incidents`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Create incident forwarding logs table
CREATE TABLE IF NOT EXISTS `incident_forwarding_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `utility_incident_id` INT NOT NULL,
  `target_system` VARCHAR(100) NOT NULL,
  `forwarded_by` INT NOT NULL,
  `forwarded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `status` VARCHAR(50) DEFAULT 'Sent',
  FOREIGN KEY (`utility_incident_id`) REFERENCES `utility_incidents`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Create incident images table
CREATE TABLE IF NOT EXISTS `incident_images` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `utility_incident_id` INT NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`utility_incident_id`) REFERENCES `utility_incidents`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Create incident notifications table
CREATE TABLE IF NOT EXISTS `incident_notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL, -- Target user ID (admin or resident)
  `message` TEXT NOT NULL,
  `read_status` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed incident categories
INSERT INTO `incident_categories` (`name`, `description`) VALUES
('Broken Streetlight', 'Inoperative street illumination lamps or broken lampposts'),
('Water Leak', 'Main pipeline water leakages, ruptured valves, or pipeline bursts'),
('Drainage Blockage', 'Clogged storm canals, overflow drainages, or blocked gratings'),
('Electrical Issue', 'Sparking wires, dangling lines, or LGU electrical safety concerns'),
('Damaged Utility Pole', 'Tilted, cracked, or structurally compromised utility posts'),
('Other Utility Concern', 'Other municipal utilities issues reported by residents')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);
