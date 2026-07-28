-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jul 28, 2026 at 04:04 PM
-- Server version: 10.11.14-MariaDB-ubu2204
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `uman_utility_system`
--

-- --------------------------------------------------------



--
-- Table structure for table `aggregated_incidents_view`
--

CREATE TABLE `aggregated_incidents_view` (
  `total_incidents` bigint(21) DEFAULT NULL,
  `submitted_incidents` decimal(22,0) DEFAULT NULL,
  `review_incidents` decimal(22,0) DEFAULT NULL,
  `forwarded_incidents` decimal(22,0) DEFAULT NULL,
  `resolved_incidents` decimal(22,0) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `aggregated_maintenance_view`
--

CREATE TABLE `aggregated_maintenance_view` (
  `total_requests` bigint(21) DEFAULT NULL,
  `pending_requests` decimal(22,0) DEFAULT NULL,
  `progress_requests` decimal(22,0) DEFAULT NULL,
  `completed_requests` decimal(22,0) DEFAULT NULL,
  `emergency_requests` decimal(22,0) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `asset_images`
--

CREATE TABLE `asset_images` (
  `id` int(11) NOT NULL,
  `utility_asset_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `asset_locations`
--

CREATE TABLE `asset_locations` (
  `id` int(11) NOT NULL,
  `utility_asset_id` int(11) NOT NULL,
  `old_location` text DEFAULT NULL,
  `new_location` text NOT NULL,
  `old_latitude` decimal(10,8) DEFAULT NULL,
  `new_latitude` decimal(10,8) DEFAULT NULL,
  `old_longitude` decimal(11,8) DEFAULT NULL,
  `new_longitude` decimal(11,8) DEFAULT NULL,
  `changed_by` int(11) NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `asset_notifications`
--

CREATE TABLE `asset_notifications` (
  `id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `read_status` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `asset_notifications`
--

INSERT INTO `asset_notifications` (`id`, `type`, `message`, `read_status`, `created_at`) VALUES
(0, 'status_changed', 'Warning: Asset AST-202601-0002 condition changed to Needs Inspection.', 0, '2026-07-15 08:35:37'),
(0, 'reported_damaged', 'ALERT: Asset AST-202602-0003 (Espana Boulevard Water Pipeline Segment 4) is reported as Damaged.', 0, '2026-07-15 08:35:37');

-- --------------------------------------------------------

--
-- Table structure for table `asset_status_logs`
--

CREATE TABLE `asset_status_logs` (
  `id` int(11) NOT NULL,
  `utility_asset_id` int(11) NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `changed_by` int(11) NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  `report_ref` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `asset_status_logs`
--

INSERT INTO `asset_status_logs` (`id`, `utility_asset_id`, `old_status`, `new_status`, `changed_by`, `changed_at`, `notes`, `report_ref`) VALUES
(0, 0, NULL, 'Operational', 1, '2026-07-15 08:35:37', 'Initial seeding during system installation.', NULL),
(0, 0, NULL, 'Needs Inspection', 1, '2026-07-15 08:35:37', 'Initial seeding during system installation.', NULL),
(0, 0, NULL, 'Damaged', 1, '2026-07-15 08:35:37', 'Initial seeding during system installation.', NULL),
(0, 0, NULL, 'Operational', 1, '2026-07-15 08:35:37', 'Initial seeding during system installation.', NULL),
(0, 0, NULL, 'Under Maintenance', 1, '2026-07-15 08:35:37', 'Initial seeding during system installation.', NULL),
(0, 0, NULL, 'Operational', 1, '2026-07-15 08:35:37', 'Initial seeding during system installation.', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `asset_types`
--

CREATE TABLE `asset_types` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `asset_types`
--

INSERT INTO `asset_types` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'Streetlight', 'Public street illumination lights and solar-powered posts', '2026-06-30 19:06:41'),
(2, 'Drainage System', 'Storm drainage networks, manholes, culverts, and gratings', '2026-06-30 19:06:41'),
(3, 'Water Pipeline', 'LGU-managed main water distribution lines and valves', '2026-06-30 19:06:41'),
(4, 'Electrical Utility Pole', 'LGU-managed power distribution poles and public safety lines', '2026-06-30 19:06:41'),
(5, 'Public Utility Infrastructure', 'Other community structures, water pumps, reservoirs, and public facilities', '2026-06-30 19:06:41'),
(7, 'Smart Traffic Sensor', NULL, '2026-07-07 20:55:59'),
(8, 'adsad', NULL, '2026-07-07 21:00:37'),
(0, 'Streetlight', 'Public street illumination lights and solar-powered posts', '2026-07-15 08:35:37'),
(0, 'Drainage System', 'Storm drainage networks, manholes, culverts, and gratings', '2026-07-15 08:35:37'),
(0, 'Water Pipeline', 'LGU-managed main water distribution lines and valves', '2026-07-15 08:35:37'),
(0, 'Electrical Utility Pole', 'LGU-managed power distribution poles and public safety lines', '2026-07-15 08:35:37'),
(0, 'Public Utility Infrastructure', 'Other community structures, water pumps, reservoirs, and public facilities', '2026-07-15 08:35:37'),
(0, 'Sound System', 'PA system, speakers, microphones for events', '2026-07-15 08:37:07'),
(0, 'Projector & AV', 'Projectors, screens, and AV equipment', '2026-07-15 08:37:07'),
(0, 'Air Conditioning', 'HVAC units for indoor facilities', '2026-07-15 08:37:07'),
(0, 'Lighting Equipment', 'Event lighting and fixtures', '2026-07-15 08:37:07'),
(0, 'Furniture Set', 'Chairs, tables, and movable furnishings', '2026-07-15 08:37:07');

-- --------------------------------------------------------

--
-- Table structure for table `billing`
--

CREATE TABLE `billing` (
  `id` int(11) NOT NULL,
  `consumer_id` int(11) NOT NULL,
  `bill_number` varchar(30) NOT NULL,
  `utility_type` enum('water','electricity') DEFAULT 'water',
  `billing_month` date NOT NULL,
  `due_date` date NOT NULL,
  `water_consumption` decimal(10,2) DEFAULT NULL,
  `electric_consumption` decimal(10,2) DEFAULT NULL,
  `water_rate_per_unit` decimal(10,2) DEFAULT NULL,
  `electric_rate_per_unit` decimal(10,2) DEFAULT NULL,
  `water_basic_charge` decimal(10,2) DEFAULT NULL,
  `electric_basic_charge` decimal(10,2) DEFAULT NULL,
  `environmental_charge` decimal(10,2) DEFAULT 20.00,
  `penalty` decimal(10,2) DEFAULT 0.00,
  `discount` decimal(10,2) DEFAULT 0.00,
  `water_subtotal` decimal(10,2) DEFAULT NULL,
  `electric_subtotal` decimal(10,2) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','paid','overdue','cancelled','partially_paid') DEFAULT 'pending',
  `payment_due` decimal(10,2) DEFAULT NULL,
  `generated_by` int(11) DEFAULT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `paid_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `consumers`
--

CREATE TABLE `consumers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `account_number` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text NOT NULL,
  `barangay` varchar(50) NOT NULL,
  `meter_number` varchar(30) NOT NULL,
  `electric_meter_number` varchar(30) DEFAULT NULL,
  `utility_type` enum('water','electricity','both') DEFAULT 'water',
  `connection_date` date NOT NULL,
  `electric_connection_date` date DEFAULT NULL,
  `disconnection_date` date DEFAULT NULL,
  `status` enum('active','disconnected','pending','suspended') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `consumer_bills_summary`
--

CREATE TABLE `consumer_bills_summary` (
  `consumer_id` int(11) DEFAULT NULL,
  `account_number` varchar(20) DEFAULT NULL,
  `consumer_name` varchar(101) DEFAULT NULL,
  `barangay` varchar(50) DEFAULT NULL,
  `total_bills` bigint(21) DEFAULT NULL,
  `pending_bills` decimal(22,0) DEFAULT NULL,
  `paid_bills` decimal(22,0) DEFAULT NULL,
  `overdue_bills` decimal(22,0) DEFAULT NULL,
  `pending_amount` decimal(32,2) DEFAULT NULL,
  `paid_amount` decimal(32,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `consumer_consumption_history`
--

CREATE TABLE `consumer_consumption_history` (
  `consumer_id` int(11) DEFAULT NULL,
  `consumer_name` varchar(101) DEFAULT NULL,
  `billing_month` varchar(7) DEFAULT NULL,
  `consumption` decimal(10,2) DEFAULT NULL,
  `current_reading` decimal(10,2) DEFAULT NULL,
  `previous_reading` decimal(10,2) DEFAULT NULL,
  `bill_amount` decimal(10,2) DEFAULT NULL,
  `bill_status` enum('pending','paid','overdue','cancelled','partially_paid') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `development_projects`
--

CREATE TABLE `development_projects` (
  `id` int(11) NOT NULL,
  `project_name` varchar(150) NOT NULL,
  `location` text NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `development_type` enum('Residential','Commercial','Industrial','Mixed-Use') NOT NULL DEFAULT 'Residential',
  `expected_timeline` varchar(100) DEFAULT NULL,
  `utility_requirements` text NOT NULL,
  `status` varchar(50) DEFAULT 'Approved Construction',
  `readiness_status` enum('Ready','Needs Upgrade','Insufficient Capacity') NOT NULL DEFAULT 'Ready',
  `planning_notes` text DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `electricity_rates`
--

CREATE TABLE `electricity_rates` (
  `id` int(11) NOT NULL,
  `category` varchar(50) NOT NULL,
  `min_consumption` int(11) DEFAULT 0,
  `max_consumption` int(11) DEFAULT NULL,
  `rate_per_unit` decimal(10,2) NOT NULL,
  `basic_charge` decimal(10,2) NOT NULL,
  `effective_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `electricity_rates`
--

INSERT INTO `electricity_rates` (`id`, `category`, `min_consumption`, `max_consumption`, `rate_per_unit`, `basic_charge`, `effective_date`, `expiry_date`, `status`, `created_by`, `created_at`) VALUES
(1, 'Residential - Low', 0, 50, 8.50, 100.00, '2024-01-01', NULL, 'active', NULL, '2026-01-05 10:54:03'),
(2, 'Residential - Medium', 51, 100, 10.75, 150.00, '2024-01-01', NULL, 'active', NULL, '2026-01-05 10:54:03'),
(3, 'Residential - High', 101, 200, 12.25, 200.00, '2024-01-01', NULL, 'active', NULL, '2026-01-05 10:54:03'),
(4, 'Residential - Excessive', 201, NULL, 15.00, 250.00, '2024-01-01', NULL, 'active', NULL, '2026-01-05 10:54:03'),
(5, 'Commercial', 0, NULL, 18.50, 350.00, '2024-01-01', NULL, 'active', NULL, '2026-01-05 10:54:03');

-- --------------------------------------------------------

--
-- Table structure for table `electric_meter_readings`
--

CREATE TABLE `electric_meter_readings` (
  `id` int(11) NOT NULL,
  `consumer_id` int(11) NOT NULL,
  `meter_number` varchar(30) NOT NULL,
  `reading_date` date NOT NULL,
  `current_reading` decimal(10,2) NOT NULL,
  `previous_reading` decimal(10,2) DEFAULT 0.00,
  `consumption` decimal(10,2) NOT NULL,
  `reader_id` int(11) DEFAULT NULL,
  `reading_type` enum('manual','digital','estimated') DEFAULT 'manual',
  `status` enum('pending','verified','billed') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `energy_consumption_records`
--

CREATE TABLE `energy_consumption_records` (
  `id` int(11) NOT NULL,
  `record_id` varchar(50) NOT NULL,
  `utility_asset_id` int(11) DEFAULT NULL,
  `facility_name` varchar(150) DEFAULT NULL,
  `asset_type` varchar(100) NOT NULL DEFAULT 'Streetlight',
  `location` text NOT NULL,
  `month_year` varchar(20) NOT NULL,
  `consumption_kwh` decimal(12,2) NOT NULL,
  `cost` decimal(12,2) DEFAULT NULL,
  `data_source` enum('Manual Input','Imported') NOT NULL DEFAULT 'Manual Input',
  `notes` text DEFAULT NULL,
  `date_recorded` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `energy_notifications`
--

CREATE TABLE `energy_notifications` (
  `id` int(11) NOT NULL,
  `message` text NOT NULL,
  `read_status` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `energy_recommendations`
--

CREATE TABLE `energy_recommendations` (
  `id` int(11) NOT NULL,
  `recommendation_title` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `target_facility_asset` varchar(150) NOT NULL,
  `priority_level` enum('Low','Medium','High','Emergency') NOT NULL DEFAULT 'Medium',
  `status` enum('Pending','Acknowledged','Implemented','Archived') NOT NULL DEFAULT 'Pending',
  `remarks` text DEFAULT NULL,
  `date_received` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `energy_sync_logs`
--

CREATE TABLE `energy_sync_logs` (
  `id` int(11) NOT NULL,
  `sync_type` enum('Outbound Data Send','Inbound Recommendation Pull') NOT NULL,
  `payload_exported` longtext DEFAULT NULL,
  `records_count` int(11) DEFAULT 0,
  `status` enum('Pending','Sent','Successful','Failed') NOT NULL DEFAULT 'Pending',
  `error_details` text DEFAULT NULL,
  `transferred_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `external_asset_requests`
--

CREATE TABLE `external_asset_requests` (
  `id` int(11) NOT NULL,
  `request_ref` varchar(50) NOT NULL,
  `source_system` varchar(50) NOT NULL DEFAULT 'CPRF',
  `cprf_facility_id` int(11) NOT NULL,
  `facility_name` varchar(150) NOT NULL,
  `asset_type` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `status` enum('pending','approved','fulfilled','rejected') NOT NULL DEFAULT 'pending',
  `fulfilled_asset_id` int(11) DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `external_asset_requests`
--

INSERT INTO `external_asset_requests` (`id`, `request_ref`, `source_system`, `cprf_facility_id`, `facility_name`, `asset_type`, `quantity`, `notes`, `status`, `fulfilled_asset_id`, `review_notes`, `created_at`, `updated_at`) VALUES
(1, 'CPRF-REQ-202607-0001', 'CPRF', 5, 'Cassanova Multipurpose Building', 'Projector & AV', 10, 'For convention hall', 'pending', NULL, NULL, '2026-07-15 09:49:34', '2026-07-15 09:49:34'),
(2, 'CPRF-REQ-202607-0002', 'CPRF', 4, 'Bernardo Court', 'Sound System', 30, 'For Convention Halls', 'approved', NULL, NULL, '2026-07-22 08:40:34', '2026-07-28 08:12:06');

-- --------------------------------------------------------



--
-- Table structure for table `incident_asset_links`
--

CREATE TABLE `incident_asset_links` (
  `id` int(11) NOT NULL,
  `utility_incident_id` int(11) NOT NULL,
  `utility_asset_id` int(11) NOT NULL,
  `linked_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `incident_categories`
--

CREATE TABLE `incident_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `incident_categories`
--

INSERT INTO `incident_categories` (`id`, `name`, `description`) VALUES
(1, 'Broken Streetlight', 'Inoperative street illumination lamps or broken lampposts'),
(2, 'Water Leak', 'Main pipeline water leakages, ruptured valves, or pipeline bursts'),
(3, 'Drainage Blockage', 'Clogged storm canals, overflow drainages, or blocked gratings'),
(4, 'Electrical Issue', 'Sparking wires, dangling lines, or LGU electrical safety concerns'),
(5, 'Damaged Utility Pole', 'Tilted, cracked, or structurally compromised utility posts'),
(6, 'Other Utility Concern', 'Other municipal utilities issues reported by residents');

-- --------------------------------------------------------

--
-- Table structure for table `incident_forwarding_logs`
--

CREATE TABLE `incident_forwarding_logs` (
  `id` int(11) NOT NULL,
  `utility_incident_id` int(11) NOT NULL,
  `target_system` varchar(100) NOT NULL,
  `forwarded_by` int(11) NOT NULL,
  `forwarded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(50) DEFAULT 'Sent'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `incident_forwarding_logs`
--

INSERT INTO `incident_forwarding_logs` (`id`, `utility_incident_id`, `target_system`, `forwarded_by`, `forwarded_at`, `status`) VALUES
(1, 1, 'Maintenance Management System', 1, '2026-06-30 19:14:27', 'Dispatched');

-- --------------------------------------------------------

--
-- Table structure for table `incident_images`
--

CREATE TABLE `incident_images` (
  `id` int(11) NOT NULL,
  `utility_incident_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `incident_notifications`
--

CREATE TABLE `incident_notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `read_status` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `incident_status_logs`
--

CREATE TABLE `incident_status_logs` (
  `id` int(11) NOT NULL,
  `utility_incident_id` int(11) NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `changed_by` int(11) NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `incident_status_logs`
--

INSERT INTO `incident_status_logs` (`id`, `utility_incident_id`, `old_status`, `new_status`, `changed_by`, `changed_at`, `notes`) VALUES
(1, 1, NULL, 'Forwarded to Maintenance System', 3, '2026-06-30 19:14:27', 'Resident report submitted via portal.'),
(2, 2, NULL, 'Under Review', 3, '2026-06-30 19:14:27', 'Resident report submitted via portal.'),
(3, 3, NULL, 'Verified', 3, '2026-06-30 19:14:27', 'Resident report submitted via portal.'),
(4, 4, NULL, 'Submitted', 3, '2026-06-30 19:14:27', 'Resident report submitted via portal.');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_asset_links`
--

CREATE TABLE `maintenance_asset_links` (
  `id` int(11) NOT NULL,
  `maintenance_request_id` int(11) NOT NULL,
  `utility_asset_id` int(11) NOT NULL,
  `linked_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_forwarding_logs`
--

CREATE TABLE `maintenance_forwarding_logs` (
  `id` int(11) NOT NULL,
  `maintenance_request_id` int(11) NOT NULL,
  `target_system` varchar(100) NOT NULL DEFAULT 'Maintenance System',
  `forwarded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `external_ref_id` varchar(50) DEFAULT NULL,
  `status` enum('Not Sent','Sent','Accepted','Rejected') NOT NULL DEFAULT 'Not Sent'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `maintenance_forwarding_logs`
--

INSERT INTO `maintenance_forwarding_logs` (`id`, `maintenance_request_id`, `target_system`, `forwarded_at`, `external_ref_id`, `status`) VALUES
(1, 1, 'Maintenance System', '2026-06-30 19:22:11', 'EXT-WO-9081', 'Accepted'),
(2, 2, 'Maintenance System', '2026-06-30 19:22:11', 'EXT-WO-9082', 'Sent'),
(3, 3, 'Maintenance System', '2026-06-30 19:22:11', 'EXT-WO-9083', 'Accepted');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_history`
--

CREATE TABLE `maintenance_history` (
  `id` int(11) NOT NULL,
  `maintenance_request_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `performed_by` int(11) NOT NULL,
  `performed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `details` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `maintenance_history`
--

INSERT INTO `maintenance_history` (`id`, `maintenance_request_id`, `action`, `performed_by`, `performed_at`, `details`) VALUES
(1, 1, 'Request Setup', 1, '2026-06-30 19:22:11', 'Request logged from source: Resident Report with priority: High.'),
(2, 2, 'Request Setup', 1, '2026-06-30 19:22:11', 'Request logged from source: Resident Report with priority: Medium.'),
(3, 3, 'Request Setup', 1, '2026-06-30 19:22:11', 'Request logged from source: Asset Monitoring with priority: High.'),
(4, 4, 'Request Setup', 1, '2026-06-30 19:22:11', 'Request logged from source: Emergency Alert with priority: Emergency.');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_notifications`
--

CREATE TABLE `maintenance_notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `read_status` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_requests`
--

CREATE TABLE `maintenance_requests` (
  `id` int(11) NOT NULL,
  `request_id` varchar(50) NOT NULL,
  `utility_asset_id` int(11) DEFAULT NULL,
  `source` enum('Resident Report','Asset Monitoring','Emergency Alert') NOT NULL DEFAULT 'Asset Monitoring',
  `description` text NOT NULL,
  `priority` enum('Low','Medium','High','Emergency') NOT NULL DEFAULT 'Medium',
  `location` text NOT NULL,
  `status` enum('Created','Forwarded','Accepted by Maintenance System','In Progress','Completed','Closed') NOT NULL DEFAULT 'Created',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_status_logs`
--

CREATE TABLE `maintenance_status_logs` (
  `id` int(11) NOT NULL,
  `maintenance_request_id` int(11) NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `changed_by` int(11) NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `maintenance_status_logs`
--

INSERT INTO `maintenance_status_logs` (`id`, `maintenance_request_id`, `old_status`, `new_status`, `changed_by`, `changed_at`, `notes`) VALUES
(1, 1, NULL, 'Accepted by Maintenance System', 1, '2026-06-30 19:22:11', 'Initial request registration in coordinator.'),
(2, 2, NULL, 'Forwarded', 1, '2026-06-30 19:22:11', 'Initial request registration in coordinator.'),
(3, 3, NULL, 'In Progress', 1, '2026-06-30 19:22:11', 'Initial request registration in coordinator.'),
(4, 4, NULL, 'Created', 1, '2026-06-30 19:22:11', 'Initial request registration in coordinator.');

-- --------------------------------------------------------

--
-- Table structure for table `meter_readings`
--

CREATE TABLE `meter_readings` (
  `id` int(11) NOT NULL,
  `consumer_id` int(11) NOT NULL,
  `meter_number` varchar(30) NOT NULL,
  `utility_type` enum('water','electricity') DEFAULT 'water',
  `reading_date` date NOT NULL,
  `current_reading` decimal(10,2) NOT NULL,
  `previous_reading` decimal(10,2) DEFAULT 0.00,
  `consumption` decimal(10,2) NOT NULL,
  `reader_id` int(11) DEFAULT NULL,
  `reading_type` enum('manual','digital','estimated') DEFAULT 'manual',
  `status` enum('pending','verified','billed') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `monthly_collection_report`
--

CREATE TABLE `monthly_collection_report` (
  `month_year` varchar(7) DEFAULT NULL,
  `total_payments` bigint(21) DEFAULT NULL,
  `total_collected` decimal(32,2) DEFAULT NULL,
  `average_payment` decimal(14,6) DEFAULT NULL,
  `payment_method` enum('gcash','grab_pay','credit_card','bank_transfer','cash','check','online_banking') DEFAULT NULL,
  `unique_consumers` bigint(21) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `otps`
--

CREATE TABLE `otps` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `otps`
--

INSERT INTO `otps` (`id`, `user_id`, `otp_hash`, `expires_at`, `used`, `created_at`) VALUES
(1, 1, '$2y$10$HTNTfPzGZhKQF0gEnde39uRqhQzMhylVeI2umJzF1DvfWPwVzilNC', '2026-07-27 09:53:03', 1, '2026-07-27 09:43:03'),
(2, 1, '$2y$10$fvmdE8juPTjo50JdrIni2uXp6lbbZiaIubu0BN4C4mftUOdJPidFC', '2026-07-27 10:02:20', 1, '2026-07-27 09:52:20'),
(3, 1, '$2y$10$QlQ9MM/dVhI39sO9reGDt.4U6V5HIr6TwEreCE8V3xB8ZnYexN/Fm', '2026-07-27 10:10:26', 1, '2026-07-27 10:00:26'),
(4, 1, '$2y$10$j8nyA6IFtToogYa5F9JxLutdfALyYXWRXnokIo7qb1812o0/GDYmi', '2026-07-27 10:22:42', 1, '2026-07-27 10:12:42'),
(5, 1, '$2y$10$OCOL4AAaZ03NWwjgFWgDXuX4lvP8Ep/GWF6GfknPyM2/XN7YdqlTG', '2026-07-27 10:22:42', 1, '2026-07-27 10:12:42'),
(6, 1, '$2y$10$sQ9MAt7rxCZ3RTl7N741q.hVaqLmGYw6WQzpYlqgP4TU0hKzxpqFq', '2026-07-27 10:26:03', 1, '2026-07-27 10:16:03'),
(7, 1, '$2y$10$uZqDL0YtrotpKIzNSweVf.griFT8Ui1MjqL436lSGrGcUu9Rtw38a', '2026-07-27 12:54:18', 1, '2026-07-27 12:44:18'),
(8, 1, '$2y$10$rrSDW4Ja6Wu9YBl5pZdTpOmfah1ZOLQQ/1nKIw40QwKGaNve1S.UC', '2026-07-27 13:01:07', 1, '2026-07-27 12:51:07'),
(9, 1, '$2y$10$Qia50khx0oEG7ze6KQq7oOtl/3c95yTqGa/mfGuEqrocBzvLd6lB6', '2026-07-27 13:01:16', 1, '2026-07-27 12:51:16'),
(10, 1, '$2y$10$ly8AGJAI7wJgD3L47nO8XuEiwLjqeWHcPYxf4sVrQNi8/Pj/8hvTW', '2026-07-27 13:01:27', 1, '2026-07-27 12:51:27'),
(11, 1, '$2y$10$9maXMcM6GEUAYoal1UNxC.M2p7zvFia8QOl8Mstmx45IWGMrUFc0G', '2026-07-27 13:04:11', 1, '2026-07-27 12:54:11'),
(12, 1, '$2y$10$093Z.RUQntoyQ2SQdgLoDu5jeJhbe2wvGiKDnMPwwPnJERBUIizia', '2026-07-27 13:04:38', 1, '2026-07-27 12:54:38'),
(13, 1, '$2y$10$mgfGL8Pyd3jt6UHuhvAQQuN//MRmb1kE95my3t2EvmqsQgl7XJwaO', '2026-07-27 13:06:02', 1, '2026-07-27 12:56:02'),
(14, 1, '$2y$10$CUMcUBrMMNcp5ccCWi0Fleyouvrc3lSf3GBUhKl2B72bvw7NWRZum', '2026-07-27 13:18:32', 1, '2026-07-27 13:08:32'),
(15, 1, '$2y$10$4iACZt/dg8Elf5NQQ7pc0OfMthxFMB6DnCnOUYtPgauf4MR2fdHFe', '2026-07-27 13:20:43', 1, '2026-07-27 13:10:43'),
(16, 1, '$2y$10$.0LC0Ohex/7WsRx7N8MVmOpKHsE2I5Vq5U.8zSJJ607lYXJERNzIO', '2026-07-27 15:31:48', 1, '2026-07-27 15:21:48'),
(17, 2, '$2y$10$JDM/bBKIURZfMyMCroY/cusBXqjPQc1GXE90V8Ox8T1SkO9zNlkMu', '2026-07-27 15:34:58', 0, '2026-07-27 15:24:58'),
(18, 1, '$2y$10$1GnyyndUTlFMjk4Xn81/AeGniqzTlqHd6yWXY4tWRaoTTCm7SYHmi', '2026-07-27 15:37:59', 1, '2026-07-27 15:27:59'),
(19, 1, '$2y$10$kGPAJxeFoZB2S5ONga73RufwybmjXE7fn5tU7pVv6qQ4Ei/krc3p6', '2026-07-27 15:49:14', 1, '2026-07-27 15:39:14'),
(20, 1, '$2y$10$SRrVDGM5elomMJuzFL7S9erjfqOpkkHmTQQsj2df571NejrqpQbu2', '2026-07-27 15:50:54', 1, '2026-07-27 15:40:54'),
(21, 1, '$2y$10$DVuDv8Da0IPfY0FHJzbk7eRK9UASgVAeoHUx5fG8p4rBOsLzqAkAW', '2026-07-27 15:55:47', 1, '2026-07-27 15:45:47'),
(22, 1, '$2y$10$MAAKb0JEekUAQFFdJ5nVOeViuoBpxhHHvmZctpNmcXfbOMb2BTLhu', '2026-07-27 16:05:16', 1, '2026-07-27 15:55:16'),
(23, 1, '$2y$10$ucupWi7YSx4rUl9TturE6.RSK./NcRSn09RTFusErbBHiO9K/QnI6', '2026-07-28 12:21:33', 1, '2026-07-28 12:11:33'),
(24, 1, '$2y$10$Xc3c.Y43bCivdM/hNkyiX.Nkjzp84AVW49S4X4coP8.GZrhRUihkK', '2026-07-28 15:38:53', 0, '2026-07-28 15:28:53');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `billing_id` int(11) NOT NULL,
  `consumer_id` int(11) NOT NULL,
  `payment_ref` varchar(50) NOT NULL,
  `paymongo_pi_id` varchar(100) DEFAULT NULL,
  `paymongo_source_id` varchar(100) DEFAULT NULL,
  `payment_method` enum('gcash','grab_pay','credit_card','bank_transfer','cash','check','online_banking') NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `service_fee` decimal(10,2) DEFAULT 0.00,
  `payment_date` datetime NOT NULL,
  `status` enum('pending','paid','failed','refunded','cancelled') DEFAULT 'pending',
  `receipt_url` text DEFAULT NULL,
  `receipt_number` varchar(50) DEFAULT NULL,
  `paid_by` varchar(100) DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_configs`
--

CREATE TABLE `payment_configs` (
  `id` int(11) NOT NULL,
  `config_key` varchar(100) NOT NULL,
  `config_value` text NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_configs`
--

INSERT INTO `payment_configs` (`id`, `config_key`, `config_value`, `description`, `is_active`, `updated_at`, `updated_by`) VALUES
(1, 'paymongo_secret_key', 'sk_test_otZq5vJfcjMSrH2RLBiqsCao', 'PayMongo Secret Key for test mode', 1, '2025-12-07 19:22:29', NULL),
(2, 'paymongo_public_key', 'pk_test_fUxTt8BBdwmaR2y4YPiddpoC', 'PayMongo Public Key for test mode', 1, '2025-12-07 19:22:40', NULL),
(3, 'paymongo_mode', 'test', 'Payment mode: test or live', 1, '2025-12-07 18:25:10', NULL),
(4, 'service_fee_percentage', '1.5', 'Service fee percentage for online payments', 1, '2025-12-07 18:25:10', NULL),
(5, 'penalty_rate', '0.02', 'Monthly penalty rate for overdue bills', 1, '2025-12-07 18:25:10', NULL),
(6, 'due_date_days', '15', 'Number of days after billing for due date', 1, '2025-12-07 18:25:10', NULL),
(7, 'paymongo_webhook_secret', 'whsk_PZyeuT16yLstpkRL7WkLrdVi', 'Webhook secret for PayMongo verification', 1, '2025-12-07 19:30:11', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `planning_coordination_logs`
--

CREATE TABLE `planning_coordination_logs` (
  `id` int(11) NOT NULL,
  `direction` enum('Outbound','Inbound') NOT NULL,
  `log_type` varchar(100) NOT NULL,
  `details` text NOT NULL,
  `logged_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `planning_coordination_logs`
--

INSERT INTO `planning_coordination_logs` (`id`, `direction`, `log_type`, `details`, `logged_at`) VALUES
(1, 'Inbound', 'Project Import', 'Imported 2 new approved development plans from the Urban Planning System.', '2026-06-30 19:26:24'),
(2, 'Outbound', 'Coverage Sync', 'Dispatched utility coverage GIS files to the Urban Planning System.', '2026-06-30 19:26:24');

-- --------------------------------------------------------

--
-- Table structure for table `planning_notifications`
--

CREATE TABLE `planning_notifications` (
  `id` int(11) NOT NULL,
  `message` text NOT NULL,
  `read_status` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------



--
-- Table structure for table `requests`
--

CREATE TABLE `requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `request_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `location` varchar(200) DEFAULT NULL,
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `request_status_history`
--

CREATE TABLE `request_status_history` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `changed_by` varchar(50) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `servicefee`
--

CREATE TABLE `servicefee` (
  `id` int(11) NOT NULL,
  `gcash` decimal(11,0) NOT NULL,
  `credit_card` decimal(11,0) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `servicefee`
--

INSERT INTO `servicefee` (`id`, `gcash`, `credit_card`) VALUES
(1, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `service_requests`
--

CREATE TABLE `service_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `request_type` enum('connection','disconnection','reconnection') NOT NULL,
  `utility_type` enum('water','electricity') NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `address` text NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `disconnection_reason` text DEFAULT NULL,
  `previous_account` varchar(100) DEFAULT NULL,
  `status` enum('pending','processing','approved','scheduled','completed','rejected') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `technician_name` varchar(255) DEFAULT NULL,
  `scheduled_date` date DEFAULT NULL,
  `scheduled_time` time DEFAULT NULL,
  `completion_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transaction_logs`
--

CREATE TABLE `transaction_logs` (
  `id` int(11) NOT NULL,
  `payment_id` int(11) DEFAULT NULL,
  `paymongo_event` varchar(100) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `request_data` text DEFAULT NULL,
  `response_data` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `uploaded_documents`
--

CREATE TABLE `uploaded_documents` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `document_type` varchar(100) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `validation_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `validation_notes` text DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `user_type` enum('citizen','employee') DEFAULT 'citizen',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `login_attempts` int(11) DEFAULT 0,
  `blocked_until` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `user_type`, `created_at`, `login_attempts`, `blocked_until`, `is_active`) VALUES
(1, 'roche.mapait@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'employee', '2025-12-07 18:25:10', 0, NULL, 1),
(2, 'juan.dela.cruz@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Juan Dela Cruz', 'citizen', '2025-12-07 18:25:10', 0, NULL, 1),
(3, 'maria.santos@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Maria Santos', 'citizen', '2025-12-07 18:25:10', 0, NULL, 1),
(4, 'pedro.gonzales@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Pedro Gonzales', 'citizen', '2025-12-07 18:25:10', 0, NULL, 1),
(5, 'ana.reyes@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ana Reyes', 'citizen', '2025-12-07 18:25:10', 0, NULL, 1),
(6, 'luis.torres@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Luis Torres', 'citizen', '2025-12-07 18:25:10', 0, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `utility_assets`
--

CREATE TABLE `utility_assets` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `asset_id` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `asset_type_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `location` text NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `date_installed` date NOT NULL,
  `condition_status` enum('Operational','Needs Inspection','Damaged','Under Maintenance') NOT NULL DEFAULT 'Operational',
  `description` text DEFAULT NULL,
  `responsible_office` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `utility_assets`
--

INSERT INTO `utility_assets` (`id`, `asset_id`, `name`, `asset_type_id`, `quantity`, `location`, `latitude`, `longitude`, `date_installed`, `condition_status`, `description`, `responsible_office`, `created_at`, `updated_at`) VALUES
(0, 'AST-202601-0001', 'Rizal Avenue Solar Streetlight 01', 0, 1, 'Rizal Avenue corner Recto, Manila', 14.60416700, 120.98222200, '2026-01-15', 'Operational', 'Solar streetlight with 100W LED bulb. Automatic twilight sensor.', 'City General Services Office', '2026-07-15 08:35:37', '2026-07-15 08:35:37'),
(0, 'AST-202601-0002', 'Quezon Boulevard Drainage Gate A', 0, 1, 'Quezon Blvd, Quiapo, Manila', 14.59833300, 120.98500000, '2025-10-10', 'Needs Inspection', 'Main storm drainage outflow gate. Reported silt build-up.', 'City Engineering Office', '2026-07-15 08:35:37', '2026-07-15 08:35:37'),
(0, 'AST-202602-0003', 'Espana Boulevard Water Pipeline Segment 4', 0, 1, 'España Blvd corner Lacson, Manila', 14.61111100, 120.99388900, '2024-05-20', 'Damaged', '12-inch main cast iron distribution pipe. Minor pressure leak detected.', 'LGU Water District', '2026-07-15 08:35:37', '2026-07-15 08:35:37'),
(0, 'AST-202602-0004', 'Magsaysay Boulevard Electrical Pole E-45', 0, 1, 'Magsaysay Blvd, Santa Mesa, Manila', 14.60194400, 121.00833300, '2023-11-12', 'Operational', 'Concrete pole supporting streetlights and LGU surveillance cameras.', 'City Information Technology Office', '2026-07-15 08:35:37', '2026-07-15 08:35:37'),
(0, 'AST-202603-0005', 'Barangay 386 Water Reservoir Pump 02', 0, 1, 'San Rafael St, Quiapo, Manila', 14.59555600, 120.99027800, '2025-02-28', 'Under Maintenance', 'Submersible pump motor. Scheduled periodic cleaning.', 'LGU Water District', '2026-07-15 08:35:37', '2026-07-15 08:35:37'),
(0, 'AST-202603-0006', 'Taft Avenue Solar Streetlight 12', 0, 1, 'Taft Avenue corner Vito Cruz, Manila', 14.56388900, 120.99472200, '2026-03-05', 'Operational', 'Solar-powered pole with battery storage box secured at 3m height.', 'City General Services Office', '2026-07-15 08:35:37', '2026-07-15 08:35:37');

-- --------------------------------------------------------

--
-- Table structure for table `utility_capacity_records`
--

CREATE TABLE `utility_capacity_records` (
  `id` int(11) NOT NULL,
  `location_zone` varchar(100) NOT NULL,
  `capacity_type` enum('Water Supply Volume','Drainage Flow Rate','Electrical Grid Load') NOT NULL DEFAULT 'Water Supply Volume',
  `max_capacity` decimal(12,2) NOT NULL,
  `current_load` decimal(12,2) NOT NULL,
  `unit` varchar(20) NOT NULL,
  `status` enum('Normal','Near Capacity','Overloaded') NOT NULL DEFAULT 'Normal',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `utility_capacity_records`
--

INSERT INTO `utility_capacity_records` (`id`, `location_zone`, `capacity_type`, `max_capacity`, `current_load`, `unit`, `status`, `updated_at`) VALUES
(1, 'Sampaloc District Zone A', 'Water Supply Volume', 10000.00, 4500.00, 'L/min', 'Normal', '2026-06-30 19:26:24'),
(2, 'Quiapo Commercial Hub', 'Drainage Flow Rate', 12000.00, 10800.00, 'm3/hr', 'Near Capacity', '2026-06-30 19:26:24'),
(3, 'Tondo North Extension', 'Electrical Grid Load', 8000.00, 8200.00, 'kVA', 'Overloaded', '2026-06-30 19:26:24');

-- --------------------------------------------------------

--
-- Table structure for table `utility_coverage_records`
--

CREATE TABLE `utility_coverage_records` (
  `id` int(11) NOT NULL,
  `area_name` varchar(100) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `radius_meters` int(11) DEFAULT 500,
  `coverage_type` enum('Water Supply','Streetlight','Drainage','Electrical') NOT NULL DEFAULT 'Water Supply',
  `coverage_status` enum('Fully Covered','Partially Covered','Not Covered') NOT NULL DEFAULT 'Fully Covered',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `utility_expansion_requests`
--

CREATE TABLE `utility_expansion_requests` (
  `id` int(11) NOT NULL,
  `request_id` varchar(50) NOT NULL,
  `area_location` varchar(100) NOT NULL,
  `utility_type` enum('Water Supply','Streetlight','Drainage','Electrical') NOT NULL DEFAULT 'Water Supply',
  `reason` text NOT NULL,
  `priority` enum('Low','Medium','High','Emergency') NOT NULL DEFAULT 'Medium',
  `estimated_scope` text DEFAULT NULL,
  `status` enum('Pending','Under Review','Approved','Deferred','Rejected') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `utility_incidents`
--

CREATE TABLE `utility_incidents` (
  `id` int(11) NOT NULL,
  `incident_id` varchar(50) NOT NULL,
  `category_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `location` text NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `status` enum('Submitted','Under Review','Verified','Forwarded to Maintenance System','In Progress','Resolved','Closed') NOT NULL DEFAULT 'Submitted',
  `priority` enum('Low','Medium','High','Emergency') NOT NULL DEFAULT 'Medium',
  `resident_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `rating` int(11) DEFAULT NULL,
  `feedback_comments` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vw_request_timeline`
--

CREATE TABLE `vw_request_timeline` (
  `request_id` int(11) DEFAULT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `request_type` enum('connection','disconnection','reconnection') DEFAULT NULL,
  `utility_type` enum('water','electricity') DEFAULT NULL,
  `history_id` int(11) DEFAULT NULL,
  `previous_status` varchar(50) DEFAULT NULL,
  `current_status` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `changed_by` varchar(50) DEFAULT NULL,
  `status_changed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vw_service_requests_with_docs`
--

CREATE TABLE `vw_service_requests_with_docs` (
  `id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `request_type` enum('connection','disconnection','reconnection') DEFAULT NULL,
  `utility_type` enum('water','electricity') DEFAULT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `status` enum('pending','processing','approved','scheduled','completed','rejected') DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `technician_name` varchar(255) DEFAULT NULL,
  `scheduled_date` date DEFAULT NULL,
  `scheduled_time` time DEFAULT NULL,
  `completion_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `document_count` bigint(21) DEFAULT NULL,
  `document_types` mediumtext DEFAULT NULL,
  `approved_docs` decimal(22,0) DEFAULT NULL,
  `pending_docs` decimal(22,0) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `water_rates`
--

CREATE TABLE `water_rates` (
  `id` int(11) NOT NULL,
  `category` varchar(50) NOT NULL,
  `min_consumption` int(11) DEFAULT 0,
  `max_consumption` int(11) DEFAULT NULL,
  `rate_per_unit` decimal(10,2) NOT NULL,
  `basic_charge` decimal(10,2) NOT NULL,
  `effective_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `water_rates`
--

INSERT INTO `water_rates` (`id`, `category`, `min_consumption`, `max_consumption`, `rate_per_unit`, `basic_charge`, `effective_date`, `expiry_date`, `status`, `created_by`, `created_at`) VALUES
(1, 'Residential - Low', 0, 10, 12.50, 100.00, '2024-01-01', NULL, 'active', 1, '2025-12-07 18:25:10'),
(2, 'Residential - Medium', 11, 20, 15.75, 150.00, '2024-01-01', NULL, 'active', 1, '2025-12-07 18:25:10'),
(3, 'Residential - High', 21, 30, 18.25, 200.00, '2024-01-01', NULL, 'active', 1, '2025-12-07 18:25:10'),
(4, 'Residential - Excessive', 31, NULL, 22.00, 250.00, '2024-01-01', NULL, 'active', 1, '2025-12-07 18:25:10'),
(5, 'Commercial', 0, NULL, 25.50, 350.00, '2024-01-01', NULL, 'active', 1, '2025-12-07 18:25:10');

-- --------------------------------------------------------

--
-- Table structure for table `work_orders`
--

CREATE TABLE `work_orders` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `technician_name` varchar(255) DEFAULT NULL,
  `scheduled_date` date DEFAULT NULL,
  `scheduled_time` time DEFAULT NULL,
  `work_description` text DEFAULT NULL,
  `special_instructions` text DEFAULT NULL,
  `status` enum('pending','assigned','in_progress','completed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `external_asset_requests`
--
ALTER TABLE `external_asset_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_ref` (`request_ref`);

--
-- Indexes for table `otps`
--
ALTER TABLE `otps`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `external_asset_requests`
--
ALTER TABLE `external_asset_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `otps`
--
ALTER TABLE `otps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
