-- sql/utility_facility.sql

-- 1. Create public facilities table
CREATE TABLE IF NOT EXISTS `public_facilities` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `facility_id` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(150) NOT NULL,
  `facility_type` ENUM('Park', 'Gymnasium', 'Barangay Hall', 'Evacuation Center', 'Community Center', 'Other LGU facility') NOT NULL DEFAULT 'Other LGU facility',
  `location` TEXT NOT NULL,
  `latitude` DECIMAL(10, 8) NULL,
  `longitude` DECIMAL(11, 8) NULL,
  `utility_status` ENUM('Fully Ready', 'Partially Ready', 'Not Ready') NOT NULL DEFAULT 'Fully Ready',
  `description` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create facility utility status checklist table
CREATE TABLE IF NOT EXISTS `facility_utility_status` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `public_facility_id` INT NOT NULL,
  `water_available` TINYINT(1) DEFAULT 1,
  `electricity_available` TINYINT(1) DEFAULT 1,
  `drainage_ok` TINYINT(1) DEFAULT 1,
  `lighting_ok` TINYINT(1) DEFAULT 1,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`public_facility_id`) REFERENCES `public_facilities`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create facility utility assets link table
CREATE TABLE IF NOT EXISTS `facility_utility_assets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `public_facility_id` INT NOT NULL,
  `utility_asset_id` INT NOT NULL,
  `association_notes` TEXT NULL,
  FOREIGN KEY (`public_facility_id`) REFERENCES `public_facilities`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`utility_asset_id`) REFERENCES `utility_assets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Create facility utility incidents table
CREATE TABLE IF NOT EXISTS `facility_incidents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `public_facility_id` INT NOT NULL,
  `utility_asset_id` INT NULL,
  `incident_type` VARCHAR(100) NOT NULL, -- e.g. 'Power Outage', 'Water Interruption', 'Lighting Failure'
  `description` TEXT NOT NULL,
  `status` ENUM('Active', 'Investigating', 'Resolved') NOT NULL DEFAULT 'Active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`public_facility_id`) REFERENCES `public_facilities`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`utility_asset_id`) REFERENCES `utility_assets`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Create facility maintenance coordination requests table
CREATE TABLE IF NOT EXISTS `facility_maintenance_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `public_facility_id` INT NOT NULL,
  `maintenance_request_id` INT NOT NULL,
  `linked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`public_facility_id`) REFERENCES `public_facilities`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Create facility notifications table
CREATE TABLE IF NOT EXISTS `facility_notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `message` TEXT NOT NULL,
  `read_status` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Create booking reservation overlay schedule table (read-only simulated inbound reservation data)
CREATE TABLE IF NOT EXISTS `facility_bookings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `public_facility_id` INT NOT NULL,
  `event_name` VARCHAR(200) NOT NULL,
  `expected_attendance` INT NOT NULL,
  `booking_date` DATE NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  FOREIGN KEY (`public_facility_id`) REFERENCES `public_facilities`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
