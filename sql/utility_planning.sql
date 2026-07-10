-- sql/utility_planning.sql

-- 1. Create utility coverage records table
CREATE TABLE IF NOT EXISTS `utility_coverage_records` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `area_name` VARCHAR(100) NOT NULL,
  `latitude` DECIMAL(10, 8) NOT NULL,
  `longitude` DECIMAL(11, 8) NOT NULL,
  `radius_meters` INT DEFAULT 500,
  `coverage_type` ENUM('Water Supply', 'Streetlight', 'Drainage', 'Electrical') NOT NULL DEFAULT 'Water Supply',
  `coverage_status` ENUM('Fully Covered', 'Partially Covered', 'Not Covered') NOT NULL DEFAULT 'Fully Covered',
  `remarks` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create utility expansion requests table
CREATE TABLE IF NOT EXISTS `utility_expansion_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `request_id` VARCHAR(50) NOT NULL UNIQUE,
  `area_location` VARCHAR(100) NOT NULL,
  `utility_type` ENUM('Water Supply', 'Streetlight', 'Drainage', 'Electrical') NOT NULL DEFAULT 'Water Supply',
  `reason` TEXT NOT NULL,
  `priority` ENUM('Low', 'Medium', 'High', 'Emergency') NOT NULL DEFAULT 'Medium',
  `estimated_scope` TEXT NULL,
  `status` ENUM('Pending', 'Under Review', 'Approved', 'Deferred', 'Rejected') NOT NULL DEFAULT 'Pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create development projects table (imported projects)
CREATE TABLE IF NOT EXISTS `development_projects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_name` VARCHAR(150) NOT NULL,
  `location` TEXT NOT NULL,
  `latitude` DECIMAL(10, 8) NULL,
  `longitude` DECIMAL(11, 8) NULL,
  `development_type` ENUM('Residential', 'Commercial', 'Industrial', 'Mixed-Use') NOT NULL DEFAULT 'Residential',
  `expected_timeline` VARCHAR(100) NULL,
  `utility_requirements` TEXT NOT NULL,
  `status` VARCHAR(50) DEFAULT 'Approved Construction',
  `readiness_status` ENUM('Ready', 'Needs Upgrade', 'Insufficient Capacity') NOT NULL DEFAULT 'Ready',
  `planning_notes` TEXT NULL,
  `reviewed_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Create utility capacity records table
CREATE TABLE IF NOT EXISTS `utility_capacity_records` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `location_zone` VARCHAR(100) NOT NULL,
  `capacity_type` ENUM('Water Supply Volume', 'Drainage Flow Rate', 'Electrical Grid Load') NOT NULL DEFAULT 'Water Supply Volume',
  `max_capacity` DECIMAL(12, 2) NOT NULL,
  `current_load` DECIMAL(12, 2) NOT NULL,
  `unit` VARCHAR(20) NOT NULL,
  `status` ENUM('Normal', 'Near Capacity', 'Overloaded') NOT NULL DEFAULT 'Normal',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Create planning coordination logs table
CREATE TABLE IF NOT EXISTS `planning_coordination_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `direction` ENUM('Outbound', 'Inbound') NOT NULL,
  `log_type` VARCHAR(100) NOT NULL, -- e.g. 'Project Review', 'Coverage Sync', 'Capacity Update'
  `details` TEXT NOT NULL,
  `logged_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Create planning notifications table
CREATE TABLE IF NOT EXISTS `planning_notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `message` TEXT NOT NULL,
  `read_status` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
