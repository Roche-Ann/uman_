-- External integration tables (CPRF ↔ UMAN)

CREATE TABLE IF NOT EXISTS `external_asset_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `request_ref` VARCHAR(50) NOT NULL UNIQUE,
  `source_system` VARCHAR(50) NOT NULL DEFAULT 'CPRF',
  `cprf_facility_id` INT NOT NULL DEFAULT 0,
  `citizen_user_id` INT NULL,
  `requester_name` VARCHAR(150) NULL,
  `requester_contact` VARCHAR(100) NULL,
  `facility_name` VARCHAR(150) NOT NULL,
  `asset_type` VARCHAR(100) NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `notes` TEXT NULL,
  `status` ENUM('pending', 'approved', 'fulfilled', 'rejected') NOT NULL DEFAULT 'pending',
  `fulfilled_asset_id` INT NULL,
  `review_notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Facility equipment asset types (for CPRF venue requests)
INSERT INTO `asset_types` (`name`, `description`) VALUES
('Sound System', 'PA system, speakers, microphones for events'),
('Projector & AV', 'Projectors, screens, and AV equipment'),
('Air Conditioning', 'HVAC units for indoor facilities'),
('Lighting Equipment', 'Event lighting and fixtures'),
('Furniture Set', 'Chairs, tables, and movable furnishings')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);
