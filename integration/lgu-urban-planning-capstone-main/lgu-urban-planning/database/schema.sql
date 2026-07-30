-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 19, 2026 at 03:06 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `lgu_urban_planning`
--

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `id` int(11) NOT NULL,
  `application_number` varchar(50) NOT NULL,
  `applicant_id` int(11) NOT NULL,
  `parcel_id` varchar(50) DEFAULT NULL,
  `project_name` varchar(255) NOT NULL,
  `project_type` varchar(100) DEFAULT NULL,
  `project_description` text DEFAULT NULL,
  `lot_number` varchar(50) DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `street` varchar(255) DEFAULT NULL,
  `block` varchar(50) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `status` enum('draft','submitted','under_review','for_revision','approved','rejected','cancelled') DEFAULT 'draft',
  `assigned_officer_id` int(11) DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `record_type` enum('online','walk-in') DEFAULT 'online',
  `verified_latitude` decimal(10,8) DEFAULT NULL,
  `verified_longitude` decimal(11,8) DEFAULT NULL,
  `parcel_details` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `applications`
--

INSERT INTO `applications` (`id`, `application_number`, `applicant_id`, `parcel_id`, `project_name`, `project_type`, `project_description`, `lot_number`, `barangay`, `street`, `block`, `latitude`, `longitude`, `status`, `assigned_officer_id`, `submitted_at`, `created_at`, `updated_at`, `record_type`, `verified_latitude`, `verified_longitude`, `parcel_details`) VALUES
(38, 'DP-2026-2626', 21, '114-21-005-02-015', 'Proposed 2-Storey Residential Building', 'Residential', '2-Storey', '15', 'UP Village', 'Maginhawa Street', '22', 14.64650000, 121.05870000, 'submitted', NULL, '2026-04-09 11:48:20', '2026-04-09 11:48:20', '2026-04-09 11:48:20', 'walk-in', NULL, NULL, NULL),
(39, 'DP-2026-9416', 21, '114-13-002-01-001', 'Proposed 3-Storey Commercial Building (Retail)', 'Commercial', 'Business', '1', 'Socorro', 'Aurora Boulevard', '5', 14.62250000, 121.05330000, 'approved', NULL, '2026-04-09 11:49:14', '2026-04-09 11:49:14', '2026-05-11 02:57:04', 'walk-in', NULL, NULL, NULL),
(40, 'DP-2026-8081', 22, '114-05-002-01-001', 'Small Retail Convenience Store', 'Commercial', 'Commercial Store', '1', 'Bagong Pag-asa', 'North Avenue', '2', 14.65330000, 121.03330000, 'approved', NULL, '2026-05-07 16:46:48', '2026-05-07 16:46:48', '2026-05-13 21:01:38', 'online', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `application_documents`
--

CREATE TABLE `application_documents` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `document_type` varchar(100) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `version` int(11) DEFAULT 1,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `application_documents`
--

INSERT INTO `application_documents` (`id`, `application_id`, `document_type`, `file_name`, `file_path`, `file_size`, `mime_type`, `version`, `uploaded_by`, `created_at`) VALUES
(7, 40, 'site_plan', 'commercial-plan.jpg', 'documents/69fcc1f86a1ee_1778172408.jpg', 47767, 'image/jpeg', 1, 22, '2026-05-07 16:46:48'),
(8, 40, 'permit_certificate', 'Locational_Clearance_DP-2026-8081.pdf', 'uploads/permits/Locational_Clearance_DP-2026-8081.pdf', NULL, NULL, 1, 1, '2026-05-09 15:41:32'),
(9, 39, 'permit_certificate', 'Locational_Clearance_DP-2026-9416.pdf', 'uploads/permits/Locational_Clearance_DP-2026-9416.pdf', NULL, NULL, 1, 1, '2026-05-09 16:06:09');

-- --------------------------------------------------------

--
-- Table structure for table `application_status_history`
--

CREATE TABLE `application_status_history` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `status` varchar(50) NOT NULL,
  `remarks` text DEFAULT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `application_status_history`
--

INSERT INTO `application_status_history` (`id`, `application_id`, `status`, `remarks`, `changed_by`, `created_at`, `created_by`) VALUES
(141, 39, 'submitted', 'GIS Verification: COMPLIANT (Zone: Metropolitan Commercial)', 1, '2026-04-21 04:23:39', NULL),
(142, 39, 'approved', '.', 1, '2026-04-21 04:23:59', NULL),
(143, 40, 'submitted', 'Application submitted by applicant', 22, '2026-05-07 16:46:48', NULL),
(144, 40, 'approved', 'testing', 1, '2026-05-09 14:34:58', NULL),
(145, 40, 'approved', 'test again', 1, '2026-05-09 14:49:24', NULL),
(146, 40, 'approved', 'test again', 1, '2026-05-09 14:50:05', NULL),
(147, 40, 'approved', 'test 1', 1, '2026-05-09 15:36:18', NULL),
(148, 40, 'approved', 'Removed require_once \'PermitController.php\'', 1, '2026-05-09 15:38:56', NULL),
(149, 40, 'approved', 'test test', 1, '2026-05-09 15:41:32', NULL),
(150, 40, 'approved', 'dot', 1, '2026-05-09 15:52:58', NULL),
(151, 39, 'approved', 'test', 1, '2026-05-09 15:59:43', NULL),
(152, 39, 'submitted', 'd', 1, '2026-05-09 16:02:58', NULL),
(153, 39, 'approved', '123', 1, '2026-05-09 16:03:06', NULL),
(154, 39, 'approved', 'ror', 1, '2026-05-09 16:04:39', NULL),
(155, 39, 'approved', 'hmm', 1, '2026-05-09 16:06:09', NULL),
(156, 39, 'approved', '1', 1, '2026-05-09 16:09:02', NULL),
(157, 39, 'approved', '2', 1, '2026-05-09 16:11:02', NULL),
(158, 39, 'approved', 'last', 1, '2026-05-11 02:57:04', NULL),
(159, 40, 'approved', 'Parcel information updated by assessor.', 23, '2026-05-13 20:58:53', NULL),
(160, 40, 'approved', 'Parcel information updated by assessor.', 23, '2026-05-13 21:00:46', NULL),
(161, 40, 'approved', 'Parcel information updated by assessor.', 23, '2026-05-13 21:01:38', NULL),
(162, 40, 'approved', 'Parcel information updated by assessor.', 23, '2026-05-13 21:10:03', NULL),
(163, 40, 'approved', 'Parcel information updated by assessor.', 23, '2026-05-13 21:16:05', NULL),
(164, 40, 'approved', 'Parcel information updated by assessor.', 23, '2026-05-13 21:17:15', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-21 19:10:14'),
(2, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-21 19:31:02'),
(3, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-22 17:41:28'),
(4, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-22 17:45:30'),
(5, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-22 17:45:38'),
(6, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-22 17:45:42'),
(7, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-22 17:45:47'),
(8, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-22 17:49:37'),
(9, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-22 17:49:44'),
(10, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-22 17:49:48'),
(11, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-22 17:50:01'),
(12, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-22 17:51:07'),
(13, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-22 19:00:00'),
(14, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-22 19:00:21'),
(15, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-22 20:19:29'),
(16, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-22 20:19:35'),
(17, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-22 21:07:19'),
(18, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-22 21:07:31'),
(19, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-23 13:36:37'),
(20, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-23 13:37:06'),
(21, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-23 13:48:16'),
(22, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-23 13:48:31'),
(23, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-23 13:56:33'),
(24, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-23 14:09:11'),
(25, NULL, 'login', 'user', 2, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-23 14:09:22'),
(26, NULL, 'submit_application', 'application', 1, 'Submitted application: DP-2025-2969', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-23 14:11:17'),
(27, NULL, 'upload_document', 'application', 1, 'Uploaded document: ownership_proof', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-23 14:11:17'),
(28, NULL, 'logout', 'user', 2, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-23 14:12:35'),
(29, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-23 14:12:40'),
(30, 1, 'update_application_status', 'application', 1, 'Updated status to: rejected', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-23 14:16:52'),
(31, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-23 14:17:45'),
(32, NULL, 'login', 'user', 2, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-23 14:17:57'),
(33, NULL, 'logout', 'user', 2, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-23 14:18:31'),
(34, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-23 14:18:37'),
(35, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-26 16:08:09'),
(36, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-26 16:12:09'),
(37, NULL, 'login', 'user', 2, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-26 16:15:37'),
(38, NULL, 'logout', 'user', 2, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-26 16:16:47'),
(39, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-26 16:16:53'),
(40, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-26 16:22:03'),
(41, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-26 19:00:02'),
(42, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-26 19:00:40'),
(43, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-26 19:00:54'),
(44, 1, 'generate_report', 'report', 1, 'Generated report: applications_summary', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-26 19:11:21'),
(45, 1, 'generate_report', 'report', 2, 'Generated report: applications_summary', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-26 19:16:56'),
(46, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-26 19:32:33'),
(47, NULL, 'login', 'user', 2, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-26 19:32:42'),
(48, NULL, 'submit_application', 'application', 2, 'Submitted application: DP-2025-5500', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-26 19:33:57'),
(49, NULL, 'upload_document', 'application', 2, 'Uploaded document: ownership_proof', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-26 19:33:57'),
(50, NULL, 'logout', 'user', 2, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-26 19:34:14'),
(51, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-26 19:34:48'),
(52, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-27 13:25:01'),
(53, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-27 13:43:03'),
(54, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-27 13:43:12'),
(55, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-27 17:33:35'),
(56, 1, 'update_application_status', 'application', 1, 'Updated status to: rejected', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-27 17:50:24'),
(57, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-27 19:03:56'),
(58, NULL, 'login', 'user', 3, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-27 19:28:40'),
(59, NULL, 'logout', 'user', 3, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-27 19:28:45'),
(60, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-27 19:28:50'),
(61, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-28 06:41:03'),
(62, 1, 'deactivate_user', 'user', 3, 'Deactivated user ID: 3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-28 07:42:14'),
(63, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-28 07:42:23'),
(64, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-28 07:42:38'),
(65, 1, 'activate_user', 'user', 3, 'Activated user ID: 3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-28 07:42:41'),
(66, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-28 07:42:43'),
(67, NULL, 'login', 'user', 3, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-28 07:42:49'),
(68, NULL, 'logout', 'user', 3, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-28 07:42:51'),
(69, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-28 07:42:59'),
(70, 1, 'password_reset_triggered', 'user', 3, 'Reset link generated for: unknownfire01@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-28 08:10:39'),
(71, 1, 'password_reset_triggered', 'user', 3, 'Reset link generated for: unknownfire01@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-28 08:10:48'),
(72, 1, 'update_user', 'user', 3, 'Updated user ID: 3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-28 08:26:02'),
(73, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-28 08:26:05'),
(74, NULL, 'login', 'user', 3, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-28 08:26:20'),
(75, NULL, 'logout', 'user', 3, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-28 08:26:24'),
(76, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-28 08:26:31'),
(77, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-28 16:04:58'),
(78, 1, 'verify_identity', 'user', 3, 'Rejected Identity: Blurry or Unreadable ID', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-28 16:24:04'),
(79, 1, 'verify_identity', 'user', 3, 'Approved Identity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-28 16:27:00'),
(80, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-28 17:14:03'),
(81, NULL, 'login', 'user', 2, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-28 17:14:15'),
(82, NULL, 'logout', 'user', 2, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-28 17:14:28'),
(83, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-28 17:14:33'),
(84, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 15:08:41'),
(85, 1, 'generate_report', 'report', 3, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 15:31:21'),
(86, 1, 'generate_report', 'report', 4, 'Generated report: applications_summary', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 15:50:18'),
(87, 1, 'generate_report', 'report', 5, 'Generated report: applications_summary', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 15:50:39'),
(88, 1, 'generate_report', 'report', 6, 'Generated report: applications_summary', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 15:51:46'),
(89, 1, 'generate_report', 'report', 7, 'Generated report: applications_summary', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 15:52:00'),
(90, 1, 'generate_report', 'report', 8, 'Generated report: zoning_compliance', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 15:52:22'),
(91, 1, 'generate_report', 'report', 9, 'Generated report: applications_summary', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 15:57:11'),
(92, 1, 'generate_report', 'report', 10, 'Generated report: applications_summary', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:02:50'),
(93, 1, 'generate_report', 'report', 11, 'Generated report: applications_summary', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:03:07'),
(94, 1, 'generate_report', 'report', 12, 'Generated report: zoning_compliance', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:03:26'),
(95, 1, 'generate_report', 'report', 13, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:03:39'),
(96, 1, 'generate_report', 'report', 14, 'Generated report: monthly_analytics', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:03:51'),
(97, 1, 'generate_report', 'report', 15, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:04:11'),
(98, 1, 'generate_report', 'report', 16, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:06:44'),
(99, 1, 'generate_report', 'report', 17, 'Generated report: zoning_compliance', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:11:28'),
(100, 1, 'generate_report', 'report', 18, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:11:35'),
(101, 1, 'generate_report', 'report', 19, 'Generated report: zoning_compliance', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:11:39'),
(102, 1, 'update_application_status', 'application', 1, 'Updated status to: approved', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:14:31'),
(103, 1, 'generate_report', 'report', 20, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:14:47'),
(104, 1, 'generate_report', 'report', 21, 'Generated report: applications_summary', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:14:54'),
(105, 1, 'generate_report', 'report', 22, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:16:41'),
(106, 1, 'generate_report', 'report', 23, 'Generated report: applications_summary', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:17:39'),
(107, 1, 'generate_report', 'report', 24, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:18:19'),
(108, 1, 'generate_report', 'report', 25, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:18:46'),
(109, 1, 'generate_report', 'report', 26, 'Generated report: applications_summary', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:18:54'),
(110, 1, 'generate_report', 'report', 27, 'Generated report: zoning_compliance', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:19:03'),
(111, 1, 'generate_report', 'report', 28, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:19:14'),
(112, 1, 'generate_report', 'report', 29, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:22:40'),
(113, 1, 'generate_report', 'report', 30, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:22:55'),
(114, 1, 'generate_report', 'report', 31, 'Generated report: applications_summary', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:23:09'),
(115, 1, 'generate_report', 'report', 32, 'Generated report: applications_summary', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:23:14'),
(116, 1, 'generate_report', 'report', 33, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:23:20'),
(117, 1, 'generate_report', 'report', 34, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:23:38'),
(118, 1, 'generate_report', 'report', 35, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:25:15'),
(119, 1, 'generate_report', 'report', 36, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:28:53'),
(120, 1, 'generate_report', 'report', 37, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:29:03'),
(121, 1, 'generate_report', 'report', 38, 'Generated report: zoning_compliance', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:29:06'),
(122, 1, 'generate_report', 'report', 39, 'Generated report: zoning_compliance', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:29:17'),
(123, 1, 'generate_report', 'report', 40, 'Generated report: zoning_compliance', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:29:34'),
(124, 1, 'generate_report', 'report', 41, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:29:46'),
(125, 1, 'generate_report', 'report', 42, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:31:11'),
(126, 1, 'generate_report', 'report', 43, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:31:29'),
(127, 1, 'generate_report', 'report', 44, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:32:38'),
(128, 1, 'generate_report', 'report', 45, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:32:46'),
(129, 1, 'generate_report', 'report', 46, 'Generated report: applications_summary', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:32:50'),
(130, 1, 'generate_report', 'report', 47, 'Generated report: applications_summary', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:33:18'),
(131, 1, 'generate_report', 'report', 48, 'Generated report: applications_summary', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:33:26'),
(132, 1, 'generate_report', 'report', 49, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:33:48'),
(133, 1, 'generate_report', 'report', 50, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:36:34'),
(134, 1, 'generate_report', 'report', 51, 'Generated report: applications_summary', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:36:46'),
(135, 1, 'generate_report', 'report', 52, 'Generated report: applications_summary', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:36:52'),
(136, 1, 'generate_report', 'report', 53, 'Generated report: applications_summary', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:36:54'),
(137, 1, 'generate_report', 'report', 54, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:36:57'),
(138, 1, 'generate_report', 'report', 55, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:38:01'),
(139, 1, 'generate_report', 'report', 56, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:41:11'),
(140, 1, 'generate_report', 'report', 57, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:42:03'),
(141, 1, 'generate_report', 'report', 58, 'Generated report: applications_summary', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:42:13'),
(142, 1, 'generate_report', 'report', 59, 'Generated report: applications_summary', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:44:34'),
(143, 1, 'generate_report', 'report', 60, 'Generated report: zoning_compliance', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:44:45'),
(144, 1, 'generate_report', 'report', 61, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:44:48'),
(145, 1, 'generate_report', 'report', 62, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:45:36'),
(146, 1, 'generate_report', 'report', 63, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:45:41'),
(147, 1, 'generate_report', 'report', 64, 'Generated report: applications_summary', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:46:54'),
(148, 1, 'generate_report', 'report', 65, 'Generated report: zoning_compliance', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:47:10'),
(149, 1, 'generate_report', 'report', 66, 'Generated report: applications_summary', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:47:13'),
(150, 1, 'generate_report', 'report', 67, 'Generated report: applications_summary', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:47:20'),
(151, 1, 'generate_report', 'report', 68, 'Generated report: applications_summary', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:47:23'),
(152, 1, 'generate_report', 'report', 69, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:47:27'),
(153, 1, 'generate_report', 'report', 70, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:48:47'),
(154, 1, 'generate_report', 'report', 71, 'Generated report: zoning_compliance', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:50:08'),
(155, 1, 'generate_report', 'report', 72, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:50:11'),
(156, 1, 'generate_report', 'report', 73, 'Generated report: applications_summary', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:51:58'),
(157, 1, 'generate_report', 'report', 74, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:52:07'),
(158, 1, 'generate_report', 'report', 75, 'Generated report: applications_summary', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:53:43'),
(159, 1, 'generate_report', 'report', 76, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:53:48'),
(160, 1, 'generate_report', 'report', 77, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:55:50'),
(161, 1, 'generate_report', 'report', 78, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:58:14'),
(162, 1, 'generate_report', 'report', 79, 'Generated report: applications_summary', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:58:21'),
(163, 1, 'generate_report', 'report', 80, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:58:24'),
(164, 1, 'generate_report', 'report', 81, 'Generated report: applications_summary', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 16:59:13'),
(165, 1, 'generate_report', 'report', 82, 'Generated report: applications_summary', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 17:01:32'),
(166, 1, 'generate_report', 'report', 83, 'Generated report: applications_summary', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 17:01:48'),
(167, 1, 'generate_report', 'report', 84, 'Generated report: applications_summary', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 17:03:19'),
(168, 1, 'generate_report', 'report', 85, 'Generated report: applications_summary', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 17:04:08'),
(169, 1, 'generate_report', 'report', 86, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 17:06:53'),
(170, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 17:24:46'),
(171, 1, 'generate_report', 'report', 87, 'Generated report: zoning_compliance', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 18:02:53'),
(172, 1, 'generate_report', 'report', 88, 'Generated report: permits_issued', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 18:03:11'),
(173, 1, 'generate_report', 'report', 89, 'Generated report: applications_summary', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 18:03:21'),
(174, 1, 'generate_report', 'report', 90, 'Generated report: monthly_analytics', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 18:04:33'),
(175, 1, 'generate_report', 'report', 91, 'Generated report: zoning_compliance', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 18:05:50'),
(176, 1, 'generate_report', 'report', 92, 'Generated report: applications_summary', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 18:05:58'),
(177, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 18:57:13'),
(178, NULL, 'login', 'user', 2, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 18:57:20'),
(179, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-01 16:39:09'),
(180, NULL, 'login', 'user', 2, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-01 21:11:25'),
(181, NULL, 'login', 'user', 2, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 07:54:32'),
(182, NULL, 'logout', 'user', 2, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 08:12:25'),
(183, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 08:12:38'),
(184, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 08:12:47'),
(185, NULL, 'login', 'user', 2, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 08:12:53'),
(186, NULL, 'logout', 'user', 2, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 08:19:42'),
(187, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 08:19:47'),
(188, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 08:45:10'),
(189, NULL, 'login', 'user', 2, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 08:45:16'),
(190, NULL, 'logout', 'user', 2, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 08:48:25'),
(191, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 08:48:30'),
(192, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 08:48:56'),
(193, NULL, 'login', 'user', 2, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 08:49:02'),
(194, NULL, 'logout', 'user', 2, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 08:52:25'),
(195, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 08:52:31'),
(196, NULL, 'login', 'user', 2, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 08:54:19'),
(197, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 08:57:04'),
(198, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 10:15:00'),
(199, NULL, 'login', 'user', 2, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 10:15:08'),
(200, 1, 'verify_identity', 'user', 2, 'Rejected Identity: Missing back part of the ID', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 10:23:04'),
(201, NULL, 'logout', 'user', 2, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 10:34:58'),
(202, NULL, 'login', 'user', 2, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 10:42:10'),
(203, 1, 'verify_identity', 'user', 2, 'Rejected Identity: Missing back part of the ID', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 10:46:19'),
(204, NULL, 'logout', 'user', 2, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 10:46:24'),
(205, 1, 'verify_identity', 'user', 2, 'Approved Identity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 10:48:19'),
(206, NULL, 'login', 'user', 2, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 10:48:27'),
(207, 1, 'verify_identity', 'user', 2, 'Rejected Identity: Missing back part of the ID', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 10:52:30'),
(208, NULL, 'logout', 'user', 2, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 10:52:44'),
(209, 1, 'verify_identity', 'user', 2, 'Approved Identity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 10:54:52'),
(210, NULL, 'login', 'user', 2, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 10:55:03'),
(211, 1, 'verify_identity', 'user', 2, 'Rejected Identity: Blurry or Unreadable ID', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 10:55:34'),
(212, 1, 'verify_identity', 'user', 2, 'Approved Identity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 10:56:40'),
(213, NULL, 'logout', 'user', 2, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 11:06:33'),
(214, NULL, 'login', 'user', 2, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 11:06:41'),
(215, 1, 'verify_identity', 'user', 2, 'Rejected Identity: Missing back part of the ID', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 11:17:51'),
(216, NULL, 'logout', 'user', 2, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 11:18:27'),
(217, NULL, 'login', 'user', 2, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 11:23:23'),
(218, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 11:29:40'),
(219, NULL, 'login', 'user', 2, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 11:29:46'),
(220, NULL, 'logout', 'user', 2, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 11:32:06'),
(221, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 11:32:11'),
(222, 1, 'verify_identity', 'user', 2, 'Rejected Identity: Blurry or Unreadable ID', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 11:32:30'),
(223, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 11:32:39'),
(224, NULL, 'login', 'user', 2, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 11:32:46'),
(225, NULL, 'logout', 'user', 2, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 11:49:46'),
(226, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 11:50:00'),
(227, 1, 'verify_identity', 'user', 2, 'Rejected Identity: Blurry or Unreadable ID', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 11:52:59'),
(228, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 11:53:01'),
(229, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 11:53:05'),
(230, NULL, 'login', 'user', 2, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 11:53:24'),
(231, NULL, 'logout', 'user', 2, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 11:58:33'),
(232, NULL, 'login', 'user', 2, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 11:58:40'),
(233, 1, 'verify_identity', 'user', 2, 'Approved Identity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 12:07:28'),
(234, 1, 'verify_identity', 'user', 2, 'Rejected Identity: Expired Identification Card', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 12:12:12'),
(235, 1, 'verify_identity', 'user', 2, 'Rejected Identity: Blurry or Unreadable ID', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 12:12:19'),
(236, 1, 'verify_identity', 'user', 2, 'Rejected Identity: Blurry or Unreadable ID', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 12:12:26'),
(237, 1, 'verify_identity', 'user', 2, 'Approved Identity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 12:13:53'),
(238, 1, 'verify_identity', 'user', 2, 'Approved Identity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 12:19:11');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(239, 1, 'verify_identity', 'user', 2, 'Approved Identity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 12:19:53'),
(240, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 13:26:19'),
(241, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 13:26:23'),
(242, NULL, 'login', 'user', 2, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 13:26:29'),
(243, NULL, 'send_message', 'message', 17, 'Sent message to user ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 13:44:27'),
(244, NULL, 'logout', 'user', 2, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 14:15:17'),
(245, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 14:15:23'),
(246, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 14:22:33'),
(247, NULL, 'login', 'user', 2, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 14:22:40'),
(248, NULL, 'logout', 'user', 2, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 14:44:56'),
(249, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 14:45:01'),
(250, NULL, 'login', 'user', 2, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 14:53:29'),
(251, NULL, 'send_message', 'message', 22, 'Sent message to user ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 15:01:52'),
(252, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 15:17:48'),
(253, NULL, 'login', 'user', 2, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 15:17:55'),
(254, NULL, 'logout', 'user', 2, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-02 15:23:07'),
(255, 1, 'login', 'user', 1, 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-22 05:17:06'),
(256, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-22 05:23:04'),
(257, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-22 05:24:31'),
(258, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-22 05:30:36'),
(259, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-22 05:30:40'),
(260, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-22 05:32:41'),
(261, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-22 05:36:40'),
(262, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-22 05:36:48'),
(263, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-22 07:15:09'),
(264, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-22 07:18:59'),
(265, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-22 07:19:17'),
(266, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-22 07:19:32'),
(267, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-22 07:19:37'),
(268, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-22 07:20:05'),
(269, NULL, 'login', 'user', 7, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-27 14:31:25'),
(270, NULL, 'logout', 'user', 7, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-27 14:31:33'),
(271, NULL, 'login', 'user', 13, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-27 15:50:45'),
(272, NULL, 'logout', 'user', 13, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-27 15:50:48'),
(273, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-07 12:45:14'),
(274, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-07 12:45:44'),
(275, NULL, 'login', 'user', 15, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-07 12:53:04'),
(276, NULL, 'login', 'user', 15, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-08 12:53:07'),
(277, NULL, 'logout', 'user', 15, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-08 13:11:35'),
(278, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-08 13:11:40'),
(279, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-08 13:13:33'),
(280, NULL, 'login', 'user', 15, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-08 13:13:44'),
(281, NULL, 'submit_application', 'application', 3, 'Submitted application: DP-2026-2903', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-08 13:15:33'),
(282, NULL, 'upload_document', 'application', 3, 'Uploaded document: ownership_proof', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-08 13:15:33'),
(283, NULL, 'logout', 'user', 15, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-08 13:15:35'),
(284, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-08 13:15:42'),
(285, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-08 15:59:58'),
(286, NULL, 'login', 'user', 15, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 03:17:35'),
(287, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 03:26:06'),
(288, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 03:26:20'),
(289, NULL, 'logout', 'user', 15, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 03:32:58'),
(290, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 03:33:03'),
(291, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 03:34:59'),
(292, NULL, 'logout', 'user', 15, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 15:27:00'),
(293, NULL, 'login', 'user', 15, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 15:27:12'),
(294, NULL, 'logout', 'user', 15, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 15:27:22'),
(295, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 15:27:27'),
(296, 1, 'request_inspection', 'application', 3, 'Sent inspection request to Roads and Energy groups', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 16:20:35'),
(297, 1, 'request_inspection', 'application', 3, 'Sent inspection request to Roads and Energy groups', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 16:20:42'),
(298, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-11 14:32:41'),
(299, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-11 17:10:23'),
(300, NULL, 'login', 'user', 15, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-11 17:25:17'),
(301, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-11 17:44:35'),
(302, NULL, 'login', 'user', 15, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-11 17:44:41'),
(303, NULL, 'submit_application', 'application', 4, 'Submitted application: DP-2026-9253', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-11 17:45:26'),
(304, NULL, 'upload_document', 'application', 4, 'Uploaded document: lot_plan', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-11 17:45:26'),
(305, NULL, 'logout', 'user', 15, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-11 17:45:29'),
(306, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-11 17:45:38'),
(307, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-12 05:16:26'),
(308, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-12 14:00:42'),
(309, NULL, 'login', 'user', 15, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-12 14:32:28'),
(310, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-12 17:30:44'),
(311, 1, 'request_inspection', 'application', 6, 'Sent inspection request to Roads and Energy groups', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-12 18:46:30'),
(312, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-14 16:07:19'),
(313, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-14 17:25:56'),
(314, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-16 16:39:13'),
(315, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-16 19:12:57'),
(316, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-16 19:14:16'),
(317, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-17 12:05:56'),
(318, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-17 13:48:51'),
(319, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-18 15:16:08'),
(320, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-19 17:10:54'),
(321, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-19 20:21:18'),
(322, 1, 'update_application_status', 'application', 3, 'Updated status to: under_review', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-19 21:27:34'),
(323, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-20 01:24:27'),
(324, 1, 'update_application_status', 'application', 3, 'Updated status to: under_review', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-20 01:30:09'),
(325, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-20 11:03:57'),
(326, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-20 14:06:46'),
(327, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-20 20:35:59'),
(328, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-20 21:33:44'),
(329, NULL, 'login', 'user', 15, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-20 21:33:55'),
(330, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-20 21:35:07'),
(331, NULL, 'submit_application', 'application', 36, 'Submitted application: DP-2026-2687', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-20 22:05:13'),
(332, NULL, 'upload_document', 'application', 36, 'Uploaded document: lot_plan', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-20 22:05:13'),
(333, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 16:01:00'),
(334, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 16:04:08'),
(335, NULL, 'login', 'user', 15, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 16:06:25'),
(336, NULL, 'logout', 'user', 15, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 16:06:30'),
(337, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 16:06:42'),
(338, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 14:10:15'),
(339, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 18:48:45'),
(340, NULL, 'login', 'user', 16, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 19:55:11'),
(341, NULL, 'logout', 'user', 16, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 20:01:11'),
(342, NULL, 'login', 'user', 16, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 20:01:29'),
(343, NULL, 'logout', 'user', 16, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 20:24:53'),
(344, 17, 'login', 'user', 17, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 20:26:47'),
(345, 17, 'login', 'user', 17, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 21:26:52'),
(346, 17, 'logout', 'user', 17, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 21:28:23'),
(347, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 21:28:29'),
(348, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 21:28:53'),
(349, 17, 'login', 'user', 17, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 21:29:01'),
(350, 17, 'logout', 'user', 17, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 21:35:21'),
(351, 17, 'login', 'user', 17, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 21:35:31'),
(352, 17, 'logout', 'user', 17, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 21:37:23'),
(353, 17, 'login', 'user', 17, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 21:37:33'),
(354, 17, 'logout', 'user', 17, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 21:40:30'),
(355, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 21:40:37'),
(356, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 21:40:37'),
(357, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 21:40:40'),
(358, 17, 'login', 'user', 17, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 21:40:48'),
(359, 17, 'logout', 'user', 17, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 21:43:35'),
(360, 17, 'login', 'user', 17, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 21:43:47'),
(361, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 22:05:13'),
(362, 17, 'logout', 'user', 17, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 22:16:41'),
(363, 17, 'login', 'user', 17, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 22:16:57'),
(364, 17, 'logout', 'user', 17, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 22:26:07'),
(365, 17, 'login', 'user', 17, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 22:26:38'),
(366, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 02:33:57'),
(367, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 04:11:44'),
(368, 17, 'login', 'user', 17, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 04:12:35'),
(369, 17, 'logout', 'user', 17, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 04:12:43'),
(370, NULL, 'login', 'user', 18, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 04:16:10'),
(371, NULL, 'logout', 'user', 18, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 04:18:25'),
(372, NULL, 'login', 'user', 15, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 04:18:37'),
(373, NULL, 'logout', 'user', 15, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 04:43:37'),
(374, NULL, 'login', 'user', 15, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 05:10:56'),
(375, NULL, 'logout', 'user', 15, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 05:14:26'),
(376, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 05:14:30'),
(377, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 02:03:00'),
(378, NULL, 'login', 'user', 15, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 02:13:10'),
(379, NULL, 'submit_application', 'applications', 37, 'Submitted application #DP-2026-4364', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 02:42:40'),
(380, NULL, 'upload_document', 'application', 37, 'Uploaded document: site_plan', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 02:42:40'),
(381, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 13:25:18'),
(382, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 03:33:38'),
(383, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 03:34:02'),
(384, NULL, 'login', 'user', 15, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 03:34:11'),
(385, NULL, 'logout', 'user', 15, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 03:37:02'),
(386, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 03:37:07'),
(387, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 04:06:37'),
(388, NULL, 'login', 'user', 19, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 04:16:47'),
(389, NULL, 'logout', 'user', 19, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 04:24:28'),
(390, NULL, 'login', 'user', 15, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 04:24:39'),
(391, NULL, 'logout', 'user', 15, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 04:31:58'),
(392, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 04:32:29'),
(393, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 04:33:06'),
(394, NULL, 'login', 'user', 15, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 04:33:17'),
(395, NULL, 'logout', 'user', 15, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 04:38:27'),
(396, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 04:38:36'),
(397, NULL, 'login', 'user', 15, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 05:01:42'),
(398, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 10:13:42'),
(399, NULL, 'login', 'user', 15, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 10:44:30'),
(400, NULL, 'send_message', 'message', 34, 'Sent message to user ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 10:57:07'),
(401, NULL, 'send_message', 'message', 35, 'Sent message to user ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 11:03:36'),
(402, NULL, 'send_message', 'message', 37, 'Sent message to user ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 11:14:35'),
(403, NULL, 'send_message', 'message', 38, 'Sent message to user ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 11:14:55'),
(404, NULL, 'send_message', 'message', 39, 'Sent message to user ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 11:22:52'),
(405, NULL, 'send_message', 'message', 40, 'Sent message to user ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 11:24:07'),
(406, NULL, 'send_message', 'message', 41, 'Sent message to user ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 11:25:22'),
(407, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 18:04:08'),
(408, 17, 'login', 'user', 17, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 18:04:18'),
(409, 17, 'logout', 'user', 17, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 18:05:21'),
(410, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 19:07:38'),
(411, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 07:16:02'),
(412, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 12:34:37'),
(413, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 12:35:06'),
(414, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 12:40:38'),
(415, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 12:41:45'),
(416, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 12:45:31'),
(417, 21, 'logout', 'user', 21, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 12:45:38'),
(418, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 13:15:44'),
(419, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 16:21:41'),
(420, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 16:46:19'),
(421, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 17:23:50'),
(422, 17, 'login', 'user', 17, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 12:29:07'),
(423, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 12:30:03'),
(424, 17, 'logout', 'user', 17, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 12:32:25'),
(425, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 12:32:50'),
(426, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 12:36:37'),
(427, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 12:49:41'),
(428, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 12:59:20'),
(429, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 12:59:55'),
(430, 1, 'session_timeout', 'user', 1, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 13:04:49'),
(431, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 13:05:34'),
(432, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 13:13:07'),
(433, 1, 'session_timeout', 'user', 1, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 13:17:25'),
(434, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 13:17:50'),
(435, 1, 'session_timeout', 'user', 1, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-03 13:28:31'),
(436, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 13:29:04'),
(437, 1, 'session_timeout', 'user', 1, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 13:37:44'),
(438, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 13:49:48'),
(439, 1, 'session_timeout', 'user', 1, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 13:57:55'),
(440, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 13:58:26'),
(441, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 02:10:00'),
(442, 1, 'session_timeout', 'user', 1, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 02:12:12'),
(443, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 02:14:13'),
(444, 1, 'session_timeout', 'user', 1, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 02:16:23'),
(445, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 02:17:29'),
(446, 1, 'session_timeout', 'user', 1, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 02:20:11'),
(447, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 02:20:37'),
(448, 1, 'session_timeout', 'user', 1, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 02:42:36'),
(449, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 02:43:01'),
(450, 1, 'session_timeout', 'user', 1, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 02:58:18'),
(451, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 02:58:41'),
(452, 1, 'Export CSV - SUCCESS', 'users', NULL, 'Reason: Reporting | Token issued. Expires: 2026-04-04 11:00:11.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 02:59:11'),
(453, 1, 'session_timeout', 'user', 1, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 03:02:45'),
(454, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 03:05:03'),
(455, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 10:53:56'),
(456, 1, 'Export CSV - SUCCESS', 'users', NULL, 'Reason: Reporting | Token issued. Expires: 2026-04-09 18:59:52.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 10:58:52'),
(457, 1, 'session_timeout', 'user', 1, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 11:02:17'),
(458, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 11:18:21'),
(459, 1, 'Export CSV - SUCCESS', 'audit_logs', NULL, 'Reason: Reporting | Token issued. Expires: 2026-04-09 19:42:10.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 11:41:10'),
(460, 1, 'Export PURGE - SUCCESS', 'audit_logs', NULL, 'Reason: Purge audit_logs older than 1 year(s) | Token issued. Expires: 2026-04-09 19:42:21.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 11:41:21'),
(461, 1, 'session_timeout', 'user', 1, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 12:14:50'),
(462, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 12:15:35'),
(463, 1, 'session_timeout', 'user', 1, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 12:20:33'),
(464, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Mobile Safari/537.36', '2026-04-20 03:00:03'),
(465, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-21 03:59:56'),
(466, 1, 'session_timeout', 'user', 1, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Mobile Safari/537.36', '2026-04-21 04:16:54'),
(467, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Mobile Safari/537.36', '2026-04-21 04:17:23'),
(468, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-28 11:11:57'),
(469, 1, 'session_timeout', 'user', 1, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Mobile Safari/537.36', '2026-04-28 11:17:08'),
(470, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-01 16:00:09'),
(471, 1, 'session_timeout', 'user', 1, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Mobile Safari/537.36', '2026-05-01 16:03:22'),
(472, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Mobile Safari/537.36', '2026-05-01 16:03:51'),
(473, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-02 10:42:03'),
(474, 1, 'Profile Update', 'user', 1, 'City: \"Not set\" → \"Quezon City\"', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-02 11:05:54'),
(475, 1, 'Password Change', 'user', 1, 'User changed their own account password.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-02 11:06:32'),
(476, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-02 11:06:38'),
(477, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-02 11:07:24'),
(478, 1, 'Password Change', 'user', 1, 'User changed their own account password.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-02 11:09:53'),
(479, 1, 'session_timeout', 'user', 1, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-02 11:44:22'),
(480, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-02 11:45:02'),
(481, 1, 'Settings Update', 'settings', 0, 'Locale updated. Language: fil, Date: M/D/YYYY, Time: 12h, TZ: Asia/Manila.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-02 11:45:27'),
(482, 1, 'Settings Update', 'settings', 0, 'Locale updated. Language: en_PH, Date: M/D/YYYY, Time: 12h, TZ: Asia/Manila.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-02 11:45:37'),
(483, 1, 'session_timeout', 'user', 1, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-02 11:48:14'),
(484, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-02 11:50:56'),
(485, 1, 'Settings Update', 'settings', 0, 'Announcement banner updated. Enabled: 1, Type: info.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-02 11:52:38');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(486, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-02 11:59:31'),
(487, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-02 16:33:04'),
(488, 1, 'session_timeout', 'user', 1, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-02 16:40:11'),
(489, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-02 16:40:49'),
(490, 1, 'Profile Update', 'user', 1, 'Profile saved with no field changes.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-02 16:45:39'),
(491, 1, 'Settings Update', 'settings', 0, 'Announcement banner updated. Enabled: 0, Type: info.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-02 16:56:17'),
(492, 1, 'Settings Update', 'settings', 0, 'Announcement banner updated. Enabled: 0, Type: info.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-02 16:56:21'),
(493, 1, 'Settings Update', 'settings', 0, 'Announcement banner updated. Enabled: 0, Type: info.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-02 17:05:32'),
(494, 1, 'Settings Update', 'settings', 0, 'Announcement banner updated. Enabled: 0, Type: info.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-02 17:05:42'),
(495, 1, 'Settings Update', 'settings', 0, 'Announcement banner updated. Enabled: 0, Type: info.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-02 17:05:46'),
(496, 1, 'Settings Update', 'settings', 0, 'Announcement banner updated. Enabled: 1, Type: info.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-02 17:10:11'),
(497, 1, 'Settings Update', 'settings', 0, 'Announcement banner updated. Enabled: 1, Type: info.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-02 17:10:30'),
(498, 1, 'Settings Update', 'settings', 0, 'Announcement banner updated. Enabled: 1, Type: warning.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-02 17:19:22'),
(499, 1, 'Settings Update', 'settings', 0, 'Announcement banner updated. Enabled: 1, Type: info.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-02 17:20:00'),
(500, 1, 'Settings Update', 'settings', 0, 'Announcement banner updated. Enabled: 0, Type: info.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-02 17:21:47'),
(501, 1, 'session_timeout', 'user', 1, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-02 17:33:12'),
(502, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-02 17:33:52'),
(503, 1, 'Settings Update', 'settings', 0, 'Locale updated. Language: en_PH, Date: F j, Y, Time: 12h, TZ: Asia/Manila.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-02 17:34:14'),
(504, 1, 'Settings Update', 'settings', 0, 'Locale updated. Language: en_PH, Date: F j, Y, Time: 24h, TZ: Asia/Manila.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-02 17:37:49'),
(505, 1, 'Settings Update', 'settings', 0, 'Locale updated. Language: en_PH, Date: F j, Y, Time: 12h, TZ: Asia/Manila.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-02 17:37:57'),
(506, 1, 'Settings Update', 'settings', 0, 'Locale updated. Language: fil, Date: F j, Y, Time: 12h, TZ: Asia/Manila.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-02 17:51:23'),
(507, 1, 'Settings Update', 'settings', 0, 'Locale updated. Language: en_PH, Date: F j, Y, Time: 12h, TZ: Asia/Manila.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-02 17:52:07'),
(508, 1, 'session_timeout', 'user', 1, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-02 18:05:27'),
(509, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-03 07:42:06'),
(510, 1, 'Export CSV - SUCCESS', 'users', NULL, 'Reason: Reporting | Token issued. Expires: 2026-05-03 15:45:21.', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Mobile Safari/537.36', '2026-05-03 07:44:21'),
(511, 1, 'Settings Update', 'settings', 0, 'Locale updated. Language: en_PH, Date: F j, Y, Time: 24h, TZ: Asia/Manila.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-03 08:05:34'),
(512, 1, 'Settings Update', 'settings', 0, 'Locale updated. Language: en_PH, Date: F j, Y, Time: 12h, TZ: Asia/Manila.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-03 08:05:47'),
(513, 1, 'session_timeout', 'user', 1, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-03 08:34:34'),
(514, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-03 08:35:03'),
(515, 1, 'Settings Update', 'settings', 0, 'Admin triggered database backup download.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-03 08:41:47'),
(516, 1, 'Export SQL_BACKUP - SUCCESS', 'database_backup', NULL, 'Reason: Routine Backup | Token issued. Expires: 2026-05-03 16:52:33.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-03 08:51:33'),
(517, 1, 'Settings Update', 'settings', 0, 'Admin downloaded a full database SQL backup. Reason: Not specified', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-03 08:51:33'),
(518, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-03 08:59:47'),
(519, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-03 18:51:31'),
(520, 1, 'session_timeout', 'user', 1, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-03 19:05:59'),
(521, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-03 19:06:24'),
(522, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-03 19:09:02'),
(523, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-03 19:09:50'),
(524, 21, 'send_message', 'message', 91, 'Sent message to user ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-03 19:10:17'),
(525, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-03 19:17:09'),
(526, 21, 'session_timeout', 'user', 21, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-03 19:25:59'),
(527, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-03 19:27:22'),
(528, 21, 'session_timeout', 'user', 21, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-03 19:38:54'),
(529, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-03 19:40:24'),
(530, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-03 20:04:26'),
(531, 21, 'logout', 'user', 21, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-03 20:15:43'),
(532, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-04 13:02:24'),
(533, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-04 13:15:16'),
(534, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-04 20:16:12'),
(535, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-04 20:21:39'),
(536, 21, 'session_timeout', 'user', 21, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-04 20:41:19'),
(537, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-04 20:46:27'),
(538, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-04 20:52:02'),
(539, 21, 'Profile Photo Update', 'user', 21, 'Profile photo updated. New file: avatar_21_1777927933.jpg', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-04 20:52:13'),
(540, 21, 'session_timeout', 'user', 21, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Mobile Safari/537.36', '2026-05-04 21:16:42'),
(541, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-04 21:17:37'),
(542, 21, 'session_timeout', 'user', 21, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-04 21:27:42'),
(543, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-07 10:10:34'),
(544, 21, 'session_timeout', 'user', 21, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-07 10:39:06'),
(546, 21, 'session_timeout', 'user', 21, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-07 10:55:59'),
(547, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-07 10:56:47'),
(548, 21, 'session_timeout', 'user', 21, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-07 11:36:11'),
(549, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-07 13:35:42'),
(550, 21, 'logout', 'user', 21, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-07 13:43:49'),
(551, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-07 13:45:57'),
(552, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-07 16:37:52'),
(553, 21, 'logout', 'user', 21, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-07 16:41:48'),
(554, 22, 'login', 'user', 22, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-07 16:44:03'),
(555, 22, 'submit_application', 'applications', 40, 'Submitted application #DP-2026-8081', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-07 16:46:48'),
(556, 22, 'upload_document', 'application', 40, 'Uploaded document: site_plan', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-07 16:46:48'),
(557, 22, 'Data Export', NULL, NULL, 'Personal backup', '::1', NULL, '2026-05-07 16:51:46'),
(558, 22, 'Account Deletion Request', NULL, NULL, 'Duplicate account', '::1', NULL, '2026-05-07 16:52:11'),
(559, 22, 'Data Export', NULL, NULL, 'Personal backup', '::1', NULL, '2026-05-07 17:01:05'),
(560, 22, 'Data Export', NULL, NULL, 'Personal backup', '::1', NULL, '2026-05-07 17:03:57'),
(561, 22, 'Data Export Completed', NULL, NULL, 'Reason: Personal backup', '::1', NULL, '2026-05-07 17:03:57'),
(562, 22, 'session_timeout', 'user', 22, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-07 17:08:13'),
(563, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-07 17:08:49'),
(564, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-07 17:10:07'),
(565, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-07 17:10:45'),
(566, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-07 17:18:22'),
(567, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-08 23:00:45'),
(568, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-08 23:06:35'),
(569, 1, 'update_user', 'user', 22, 'Updated user ID: 22', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-08 23:07:25'),
(570, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-08 23:07:33'),
(571, 21, 'Profile Update', 'user', 21, 'Profile saved with no field changes.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-08 23:17:58'),
(572, 21, 'Profile Update', 'user', 21, 'Profile saved with no field changes.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-08 23:20:47'),
(573, 21, 'Password Change', 'user', 21, 'Applicant changed their own account password.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-08 23:21:14'),
(574, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-08 23:22:56'),
(575, 21, 'Password Change', 'user', 21, 'Applicant changed their own account password.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-08 23:24:14'),
(576, 21, 'Password Change', 'user', 21, 'Applicant changed their own account password.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-08 23:27:23'),
(577, 21, 'Password Change', 'user', 21, 'Applicant changed their own account password.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-08 23:28:16'),
(578, 21, 'Password Change', 'user', 21, 'Applicant changed their own account password.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-08 23:28:39'),
(579, 21, 'session_timeout', 'user', 21, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-08 23:37:33'),
(580, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-08 23:38:41'),
(581, 21, 'session_timeout', 'user', 21, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-08 23:47:55'),
(582, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-08 23:56:01'),
(583, 21, 'session_timeout', 'user', 21, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-08 23:59:09'),
(584, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 00:02:19'),
(585, 21, 'session_timeout', 'user', 21, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 00:04:42'),
(586, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 00:07:56'),
(587, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 01:10:45'),
(588, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 01:15:46'),
(589, 21, 'session_timeout', 'user', 21, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 01:18:34'),
(590, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 01:21:09'),
(591, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 01:28:16'),
(592, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 01:34:38'),
(593, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 01:47:02'),
(594, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 01:53:35'),
(595, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 01:58:21'),
(596, 21, 'Account Deletion Request', NULL, NULL, 'No longer need the account', '::1', NULL, '2026-05-09 01:58:38'),
(597, 21, 'logout', 'user', 21, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 01:59:15'),
(598, 1, 'session_timeout', 'user', 1, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 02:14:55'),
(599, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 02:15:29'),
(600, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 02:17:56'),
(601, 21, 'logout', 'user', 21, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 02:18:11'),
(602, 21, 'Account Deletion Rejected', NULL, NULL, 'Rejected by admin ID: 1', '::1', NULL, '2026-05-09 02:27:01'),
(603, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 02:27:35'),
(604, 21, 'logout', 'user', 21, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 02:29:26'),
(605, 1, 'session_timeout', 'user', 1, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 02:29:38'),
(606, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 02:30:12'),
(607, 1, 'Settings Update', 'settings', 0, 'Locale updated. Language: fil, Date: F j, Y, Time: 12h, TZ: Asia/Manila.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 02:31:17'),
(608, 1, 'Settings Update', 'settings', 0, 'Announcement banner updated. Enabled: 0, Type: info.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 02:31:28'),
(609, 1, 'Settings Update', 'settings', 0, 'Locale updated. Language: fil, Date: F j, Y, Time: 12h, TZ: Asia/Manila.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 02:31:32'),
(610, 1, 'Settings Update', 'settings', 0, 'Locale updated. Language: en_PH, Date: F j, Y, Time: 12h, TZ: Asia/Manila.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 02:32:07'),
(611, 1, 'Settings Update', 'settings', 0, 'Locale updated. Language: fil, Date: F j, Y, Time: 12h, TZ: Asia/Manila.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 02:35:25'),
(612, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 09:23:57'),
(613, 21, 'session_timeout', 'user', 21, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '2026-05-09 09:29:57'),
(614, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '2026-05-09 09:38:22'),
(615, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 09:46:45'),
(616, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 09:57:26'),
(617, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 10:01:39'),
(618, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 10:49:35'),
(619, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 10:53:48'),
(620, 21, 'logout', 'user', 21, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 11:00:55'),
(621, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 11:01:47'),
(622, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 14:20:01'),
(623, 1, 'session_timeout', 'user', 1, 'Session expired due to inactivity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 14:37:09'),
(624, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 14:37:37'),
(625, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 14:53:43'),
(626, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 14:54:34'),
(627, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 14:58:52'),
(628, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 14:59:48'),
(629, 21, 'logout', 'user', 21, 'User logged out', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '2026-05-09 15:02:01'),
(630, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '2026-05-09 15:02:30'),
(631, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 15:07:28'),
(632, 21, 'logout', 'user', 21, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 15:15:55'),
(633, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 15:29:04'),
(634, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 15:31:43'),
(635, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 15:32:34'),
(636, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 15:35:59'),
(637, 21, 'logout', 'user', 21, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 15:43:18'),
(638, 22, 'login', 'user', 22, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 15:43:59'),
(639, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 15:45:45'),
(640, 22, 'logout', 'user', 22, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 15:49:29'),
(641, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 15:52:44'),
(642, 22, 'login', 'user', 22, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 15:53:33'),
(643, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 16:01:26'),
(644, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 16:02:07'),
(645, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 16:02:28'),
(646, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 16:12:57'),
(647, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-09 16:13:47'),
(648, 21, 'logout', 'user', 21, 'User logged out', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '2026-05-09 16:22:23'),
(649, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-11 02:25:01'),
(650, 21, 'logout', 'user', 21, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-11 02:31:42'),
(651, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-11 02:32:30'),
(652, 21, 'logout', 'user', 21, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-11 02:45:09'),
(653, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-11 02:47:02'),
(654, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-11 02:56:48'),
(655, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-11 02:57:37'),
(656, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-11 03:05:15'),
(657, 21, 'logout', 'user', 21, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-11 03:34:58'),
(658, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-11 03:35:02'),
(659, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-11 03:35:57'),
(660, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-11 03:41:55'),
(661, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-11 03:45:24'),
(662, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-11 04:05:28'),
(663, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-11 04:08:18'),
(664, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-11 10:43:48'),
(665, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-11 11:14:40'),
(666, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-11 11:32:59'),
(667, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-11 11:38:05'),
(668, 21, 'logout', 'user', 21, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-11 11:38:26'),
(669, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-11 11:42:28'),
(670, 21, 'send_message', 'message', 113, 'Sent message to user ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-11 11:42:47'),
(671, 21, 'send_message', 'message', 114, 'Sent message to user ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-11 11:43:00'),
(672, 21, 'send_message', 'message', 115, 'Sent message to user ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-11 11:43:15'),
(673, 21, 'send_message', 'message', 116, 'Sent message to user ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-11 11:43:36'),
(674, 21, 'logout', 'user', 21, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-11 11:44:17'),
(675, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-12 23:32:42'),
(676, 1, 'Settings Update', 'role_permissions', 0, 'Role permissions matrix updated by administrator.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-12 23:34:23'),
(677, 1, 'Settings Update', 'settings', 0, 'Locale updated. Language: en_PH, Date: F j, Y, Time: 12h, TZ: Asia/Manila.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-12 23:46:12'),
(678, 1, 'Settings Update', 'settings', 0, 'Locale updated. Language: fil, Date: F j, Y, Time: 12h, TZ: Asia/Manila.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-12 23:55:25'),
(679, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-12 23:56:21'),
(680, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-13 00:05:10'),
(681, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-13 00:14:36'),
(682, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-13 00:17:11'),
(683, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-13 00:27:16'),
(684, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-13 12:26:36'),
(685, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-13 12:35:00'),
(686, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-13 20:23:54'),
(687, 1, 'Settings Update', 'settings', 0, 'Locale updated. Language: en_PH, Date: F j, Y, Time: 12h, TZ: Asia/Manila.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-13 20:25:38'),
(688, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-13 20:29:51'),
(689, 24, 'login', 'user', 24, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-13 20:31:10'),
(690, 24, 'logout', 'user', 24, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-13 20:33:30'),
(691, 23, 'login', 'user', 23, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-13 20:36:17'),
(692, 23, 'update_parcel_info', 'application', 40, 'Parcel info updated — Lot: 1, Block: 2, Street: North Avenue, Barangay: Bagong Pag-asa, Parcel ID: 114-05-002-01-001, Lat: 14.65330000, Lng: 121.03330000', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-13 21:10:03'),
(693, 23, 'logout', 'user', 23, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-13 21:14:08'),
(694, 23, 'login', 'user', 23, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-13 21:15:26'),
(695, 23, 'update_parcel_info', 'application', 40, 'Parcel info updated — Lot: 1, Block: 2, Street: North Avenue, Barangay: Bagong Pag-asa, Parcel ID: 114-05-002-01-001, Lat: 14.65330000, Lng: 121.03330000', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-13 21:16:05'),
(696, 23, 'update_parcel_info', 'application', 40, 'Parcel info updated — Lot: 1, Block: 2, Street: North Avenue, Barangay: Bagong Pag-asa, Parcel ID: 114-05-002-01-001, Lat: 14.65330000, Lng: 121.03330000', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-13 21:17:15'),
(697, 23, 'logout', 'user', 23, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-13 21:24:09'),
(698, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-14 05:27:44'),
(699, 21, 'login', 'user', 21, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-14 05:34:27'),
(700, 23, 'login', 'user', 23, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-14 06:09:13'),
(701, 23, 'logout', 'user', 23, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-14 06:33:59'),
(702, 24, 'login', 'user', 24, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-14 11:40:58'),
(703, 24, 'logout', 'user', 24, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-14 12:36:59'),
(704, 24, 'login', 'user', 24, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-15 05:28:22'),
(705, 24, 'logout', 'user', 24, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-15 05:41:45'),
(706, 25, 'login', 'user', 25, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-15 05:42:22'),
(707, 25, 'logout', 'user', 25, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-15 05:59:03'),
(708, 26, 'login', 'user', 26, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-15 05:59:40'),
(709, 26, 'logout', 'user', 26, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-15 06:06:26'),
(710, 17, 'login', 'user', 17, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-15 06:07:07'),
(711, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-15 06:12:07'),
(712, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-15 06:12:51'),
(713, 17, 'logout', 'user', 17, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-15 06:23:00'),
(714, 17, 'login', 'user', 17, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-15 06:24:45'),
(715, 17, 'logout', 'user', 17, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-15 06:29:24'),
(716, 1, 'login', 'user', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-19 12:48:59'),
(717, 1, 'logout', 'user', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-19 12:53:46');

-- --------------------------------------------------------

--
-- Table structure for table `gis_layers`
--

CREATE TABLE `gis_layers` (
  `id` int(11) NOT NULL,
  `layer_name` varchar(100) NOT NULL,
  `layer_type` enum('zoning','land_use','hazard','parcel','other') NOT NULL,
  `layer_data` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `impact_assessments`
--

CREATE TABLE `impact_assessments` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `traffic_score` decimal(10,2) DEFAULT NULL,
  `traffic_flag` enum('ok','high','awaiting','pending','violation') DEFAULT 'ok',
  `traffic_notes` text DEFAULT NULL,
  `energy_score` decimal(10,2) DEFAULT NULL,
  `energy_flag` enum('ok','high','awaiting','pending','violation') DEFAULT 'ok',
  `energy_notes` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `assessed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `checked_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inspections`
--

CREATE TABLE `inspections` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `inspector_id` int(11) DEFAULT NULL,
  `status` enum('scheduled','in_progress','completed','violation') DEFAULT 'scheduled',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inspections`
--

INSERT INTO `inspections` (`id`, `application_id`, `scheduled_at`, `inspector_id`, `status`, `notes`, `created_at`) VALUES
(62, 39, NULL, NULL, '', NULL, '2026-04-21 04:23:59'),
(63, 40, '2026-05-18 09:00:00', 17, 'scheduled', 'Store', '2026-05-09 14:34:58');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `application_id` int(11) DEFAULT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `message_type` enum('notification','message','system') DEFAULT 'message',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `application_id`, `sender_id`, `receiver_id`, `subject`, `message`, `is_read`, `message_type`, `created_at`) VALUES
(90, 39, 1, 21, 'CONGRATULATIONS: Approved Locational Clearance / Permit #DP-2026-9416', 'Dear Applicant,\n\nWe are pleased to inform you that your application for \'Proposed 3-Storey Commercial Building (Retail)\' has been officially APPROVED.\n\nYour Locational Clearance / Permit is now attached to this record. You may download and print the official document directly from the \'Documents\' section of your applicant portal.\n\nPermit Details:\n- Permit No: DP-2026-9416\n- Location: Barangay Socorro\n\nOffice Remarks:\n\".\"\n\nThank you for your cooperation.\n\nQuezon City Urban Planning Department', 0, '', '2026-04-21 04:23:59'),
(91, NULL, 21, 1, 'testing', 'test', 1, 'message', '2026-05-03 19:10:17'),
(92, NULL, 21, 1, 'Account Deletion Request – Aelous Nexus', 'User Aelous Nexus (ID: 21) has submitted an account deletion request.\n\nReason: No longer need the account\n\nPlease review this request and take the appropriate action in the user management panel.', 1, 'message', '2026-05-09 01:58:38'),
(93, NULL, 1, 21, 'Account Deletion – Rejected', 'Your account deletion request has been reviewed and was not approved at this time.\n\nIf you have further questions, please contact the office.', 0, 'message', '2026-05-09 02:27:01'),
(94, 40, 1, 22, 'CONGRATULATIONS: Approved Locational Clearance / Permit #DP-2026-8081', 'Dear Applicant,\n\nWe are pleased to inform you that your application for \'Small Retail Convenience Store\' has been officially APPROVED.\n\nYour Locational Clearance / Permit is now attached to this record. You may download and print the official document directly from the \'Documents\' section of your applicant portal.\n\nPermit Details:\n- Permit No: DP-2026-8081\n- Location: Barangay Bagong Pag-asa\n\nOffice Remarks:\n\"testing\"\n\nThank you for your cooperation.\n\nQuezon City Urban Planning Department', 0, '', '2026-05-09 14:34:58'),
(95, 40, 1, 22, 'CONGRATULATIONS: Approved Locational Clearance / Permit #DP-2026-8081', 'Dear Applicant,\n\nWe are pleased to inform you that your application for \'Small Retail Convenience Store\' has been officially APPROVED.\n\nYour Locational Clearance / Permit is now attached to this record. You may download and print the official document directly from the \'Documents\' section of your applicant portal.\n\nPermit Details:\n- Permit No: DP-2026-8081\n- Location: Barangay Bagong Pag-asa\n\nOffice Remarks:\n\"test again\"\n\nThank you for your cooperation.\n\nQuezon City Urban Planning Department', 0, '', '2026-05-09 14:49:24'),
(96, 40, 1, 22, 'CONGRATULATIONS: Approved Locational Clearance / Permit #DP-2026-8081', 'Dear Applicant,\n\nWe are pleased to inform you that your application for \'Small Retail Convenience Store\' has been officially APPROVED.\n\nYour Locational Clearance / Permit is now attached to this record. You may download and print the official document directly from the \'Documents\' section of your applicant portal.\n\nPermit Details:\n- Permit No: DP-2026-8081\n- Location: Barangay Bagong Pag-asa\n\nOffice Remarks:\n\"test again\"\n\nThank you for your cooperation.\n\nQuezon City Urban Planning Department', 0, '', '2026-05-09 14:50:05'),
(97, 40, 1, 22, 'CONGRATULATIONS: Approved Locational Clearance / Permit #DP-2026-8081', 'Dear Applicant,\n\nWe are pleased to inform you that your application for \'Small Retail Convenience Store\' has been officially APPROVED.\n\nYour Locational Clearance / Permit has been generated. You may download and print the official document from the \'Documents\' section of your portal. A copy has also been sent to your registered email address.\n\nPermit Details:\n- Permit No: DP-2026-8081\n- Location: Barangay Bagong Pag-asa\n\nOffice Remarks:\n\"test 1\"\n\nThank you for your cooperation.\n\nQuezon City Urban Planning Department', 0, '', '2026-05-09 15:36:18'),
(98, 40, 1, 22, 'CONGRATULATIONS: Approved Locational Clearance / Permit #DP-2026-8081', 'Dear Applicant,\n\nWe are pleased to inform you that your application for \'Small Retail Convenience Store\' has been officially APPROVED.\n\nYour Locational Clearance / Permit has been generated. You may download and print the official document from the \'Documents\' section of your portal. A copy has also been sent to your registered email address.\n\nPermit Details:\n- Permit No: DP-2026-8081\n- Location: Barangay Bagong Pag-asa\n\nOffice Remarks:\n\"Removed require_once \'PermitController.php\'\"\n\nThank you for your cooperation.\n\nQuezon City Urban Planning Department', 1, '', '2026-05-09 15:38:56'),
(99, 40, 1, 22, 'CONGRATULATIONS: Approved Locational Clearance / Permit #DP-2026-8081', 'Dear Applicant,\n\nWe are pleased to inform you that your application for \'Small Retail Convenience Store\' has been officially APPROVED.\n\nYour Locational Clearance / Permit has been generated. You may download and print the official document from the \'Documents\' section of your portal. A copy has also been sent to your registered email address.\n\nPermit Details:\n- Permit No: DP-2026-8081\n- Location: Barangay Bagong Pag-asa\n\nOffice Remarks:\n\"test test\"\n\nThank you for your cooperation.\n\nQuezon City Urban Planning Department', 1, '', '2026-05-09 15:41:32'),
(100, 40, 1, 22, 'CONGRATULATIONS: Approved Locational Clearance / Permit #DP-2026-8081', 'Dear Applicant,\n\nWe are pleased to inform you that your application for \'Small Retail Convenience Store\' has been officially APPROVED.\n\nYour Locational Clearance / Permit has been generated. You may download and print the official document from the \'Documents\' section of your portal. A copy has also been sent to your registered email address.\n\nPermit Details:\n- Permit No: DP-2026-8081\n- Location: Barangay Bagong Pag-asa\n\nOffice Remarks:\n\"dot\"\n\nThank you for your cooperation.\n\nQuezon City Urban Planning Department', 1, '', '2026-05-09 15:52:58'),
(101, 39, 1, 21, 'CONGRATULATIONS: Approved Locational Clearance / Permit #DP-2026-9416', 'Dear Applicant,\n\nWe are pleased to inform you that your application for \'Proposed 3-Storey Commercial Building (Retail)\' has been officially APPROVED.\n\nYour Locational Clearance / Permit has been generated. You may download and print the official document from the \'Documents\' section of your portal. A copy has also been sent to your registered email address.\n\nPermit Details:\n- Permit No: DP-2026-9416\n- Location: Barangay Socorro\n\nOffice Remarks:\n\"test\"\n\nThank you for your cooperation.\n\nQuezon City Urban Planning Department', 0, '', '2026-05-09 15:59:43'),
(102, 39, 1, 21, 'Official Update: Application #DP-2026-9416', 'Dear Applicant,\n\nThis is an official notification regarding your application: Proposed 3-Storey Commercial Building (Retail).\n\nThe status has been updated to: SUBMITTED.\nLocation: Barangay Socorro, Block 5, Street Aurora Boulevard\n\nRemarks from Office:\n\"d\"\n\nYou may monitor further progress through your portal.\n\nQuezon City Urban Planning Department', 0, '', '2026-05-09 16:02:58'),
(103, 39, 1, 21, 'CONGRATULATIONS: Approved Locational Clearance / Permit #DP-2026-9416', 'Dear Applicant,\n\nWe are pleased to inform you that your application for \'Proposed 3-Storey Commercial Building (Retail)\' has been officially APPROVED.\n\nYour Locational Clearance / Permit has been generated. You may download and print the official document from the \'Documents\' section of your portal. A copy has also been sent to your registered email address.\n\nPermit Details:\n- Permit No: DP-2026-9416\n- Location: Barangay Socorro\n\nOffice Remarks:\n\"123\"\n\nThank you for your cooperation.\n\nQuezon City Urban Planning Department', 0, '', '2026-05-09 16:03:06'),
(104, 39, 1, 21, 'CONGRATULATIONS: Approved Locational Clearance / Permit #DP-2026-9416', 'Dear Applicant,\n\nWe are pleased to inform you that your application for \'Proposed 3-Storey Commercial Building (Retail)\' has been officially APPROVED.\n\nYour Locational Clearance / Permit has been generated. You may download and print the official document from the \'Documents\' section of your portal. A copy has also been sent to your registered email address.\n\nPermit Details:\n- Permit No: DP-2026-9416\n- Location: Barangay Socorro\n\nOffice Remarks:\n\"ror\"\n\nThank you for your cooperation.\n\nQuezon City Urban Planning Department', 0, '', '2026-05-09 16:04:39'),
(105, 39, 1, 21, 'CONGRATULATIONS: Approved Locational Clearance / Permit #DP-2026-9416', 'Dear Applicant,\n\nWe are pleased to inform you that your application for \'Proposed 3-Storey Commercial Building (Retail)\' has been officially APPROVED.\n\nYour Locational Clearance / Permit has been generated. You may download and print the official document from the \'Documents\' section of your portal. A copy has also been sent to your registered email address.\n\nPermit Details:\n- Permit No: DP-2026-9416\n- Location: Barangay Socorro\n\nOffice Remarks:\n\"hmm\"\n\nThank you for your cooperation.\n\nQuezon City Urban Planning Department', 0, '', '2026-05-09 16:06:09'),
(106, 39, 1, 21, 'Permit Ready: DP-2026-9416', 'Congratulations! Your application has been approved.\n\nPermit No: DP-2026-9416\n\nYour Locational Clearance is now ready. Please open this message and click the Download Permit button, or visit your application details page to download your permit.\n\nThis is a system-generated notification.', 0, 'message', '2026-05-09 16:06:09'),
(107, 39, 1, 21, 'Permit Ready: DP-2026-9416', 'Congratulations! Your application has been approved.\n\nPermit No: DP-2026-9416\n\nYour Locational Clearance is now ready. Please open this message and click the Download Permit button, or visit your application details page to download your permit.\n\nThis is a system-generated notification.', 0, 'message', '2026-05-09 16:06:19'),
(108, 39, 1, 21, 'CONGRATULATIONS: Approved Locational Clearance / Permit #DP-2026-9416', 'Dear Applicant,\n\nWe are pleased to inform you that your application for \'Proposed 3-Storey Commercial Building (Retail)\' has been officially APPROVED.\n\nYour Locational Clearance / Permit has been generated. You may download and print the official document from the \'Documents\' section of your portal. A copy has also been sent to your registered email address.\n\nPermit Details:\n- Permit No: DP-2026-9416\n- Location: Barangay Socorro\n\nOffice Remarks:\n\"1\"\n\nThank you for your cooperation.\n\nQuezon City Urban Planning Department', 0, '', '2026-05-09 16:09:02'),
(109, 39, 1, 21, 'CONGRATULATIONS: Approved Locational Clearance / Permit #DP-2026-9416', 'Dear Applicant,\n\nWe are pleased to inform you that your application for \'Proposed 3-Storey Commercial Building (Retail)\' has been officially APPROVED.\n\nYour Locational Clearance / Permit has been generated. You may download and print the official document from the \'Documents\' section of your portal. A copy has also been sent to your registered email address.\n\nPermit Details:\n- Permit No: DP-2026-9416\n- Location: Barangay Socorro\n\nOffice Remarks:\n\"2\"\n\nThank you for your cooperation.\n\nQuezon City Urban Planning Department', 0, '', '2026-05-09 16:11:02'),
(110, 39, 1, 21, 'CONGRATULATIONS: Approved Locational Clearance / Permit #DP-2026-9416', 'Dear Applicant,\n\nWe are pleased to inform you that your application for \'Proposed 3-Storey Commercial Building (Retail)\' has been officially APPROVED.\n\nYour Locational Clearance / Permit has been generated. You may download and print the official document from the \'Documents\' section of your portal. A copy has also been sent to your registered email address.\n\nPermit Details:\n- Permit No: DP-2026-9416\n- Location: Barangay Socorro\n\nOffice Remarks:\n\"last\"\n\nThank you for your cooperation.\n\nQuezon City Urban Planning Department', 0, '', '2026-05-11 02:57:04'),
(111, 39, 1, 21, 'Permit Ready: DP-2026-9416', 'Congratulations! Your application has been approved.\n\nPermit No: DP-2026-9416\n\nYour Locational Clearance is now ready. Please open this message and click the Download Permit button, or visit your application details page to download your permit.\n\nThis is a system-generated notification.', 0, 'message', '2026-05-11 02:57:18'),
(112, NULL, 1, 21, 'Trial and Error', 'Test #1', 0, 'message', '2026-05-11 11:37:21'),
(113, NULL, 21, 1, 'testing', 'test #2', 0, 'message', '2026-05-11 11:42:47'),
(114, NULL, 21, 1, 'testing', 'test #3', 0, 'message', '2026-05-11 11:43:00'),
(115, NULL, 21, 1, 'testing', 'test #4', 0, 'message', '2026-05-11 11:43:15'),
(116, NULL, 21, 1, 'testing', 'test #5', 0, 'message', '2026-05-11 11:43:36'),
(117, 40, 1, 22, 'CONGRATULATIONS: Approved Locational Clearance / Permit #DP-2026-8081', 'Dear Applicant,\n\nWe are pleased to inform you that your application for \'Small Retail Convenience Store\' has been officially APPROVED.\n\nYour Locational Clearance / Permit has been generated. You may download and print the official document from the \'Documents\' section of your portal. A copy has also been sent to your registered email address.\n\nPermit Details:\n- Permit No: DP-2026-8081\n- Location: Barangay Bagong Pag-asa\n\nThank you for your cooperation.\n\nQuezon City Urban Planning Department', 0, 'message', '2026-05-13 21:17:51'),
(118, 39, 1, 21, 'CONGRATULATIONS: Approved Locational Clearance / Permit #DP-2026-9416', 'Dear Applicant,\n\nWe are pleased to inform you that your application for \'Proposed 3-Storey Commercial Building (Retail)\' has been officially APPROVED.\n\nYour Locational Clearance / Permit has been generated. You may download and print the official document from the \'Documents\' section of your portal. A copy has also been sent to your registered email address.\n\nPermit Details:\n- Permit No: DP-2026-9416\n- Location: Barangay Socorro\n\nThank you for your cooperation.\n\nQuezon City Urban Planning Department', 1, 'message', '2026-05-13 21:18:11'),
(119, 40, 1, 22, 'OFFICIAL NOTICE: Inspection Schedule for App #40', 'Dear Applicant,\n\nThis is an official notification from the Building Official\'s Office. An onsite inspection for your application (#40) has been scheduled on May 18, 2026, 9:00 AM.\n\nRemarks: Store\n\nPlease ensure that the project site is accessible and a representative is present during the visit. Thank you.', 0, 'message', '2026-05-15 06:12:45');

-- --------------------------------------------------------

--
-- Table structure for table `parcels`
--

CREATE TABLE `parcels` (
  `id` int(11) NOT NULL,
  `parcel_id` varchar(100) NOT NULL,
  `lot_number` varchar(50) DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `owner_name` varchar(255) DEFAULT NULL,
  `area_sqm` decimal(12,2) DEFAULT NULL,
  `zoning_classification_id` int(11) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `geom_json` longtext DEFAULT NULL,
  `is_master_data` tinyint(1) DEFAULT 0,
  `zoning_name` varchar(100) DEFAULT NULL,
  `boundary_coordinates` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parcels`
--

INSERT INTO `parcels` (`id`, `parcel_id`, `lot_number`, `barangay`, `owner_name`, `area_sqm`, `zoning_classification_id`, `latitude`, `longitude`, `geom_json`, `is_master_data`, `zoning_name`, `boundary_coordinates`, `created_at`, `updated_at`) VALUES
(3, 'QC-MASTER-NORTH', 'MASTER-N', 'Novaliches', NULL, NULL, 1, NULL, NULL, '{\"type\":\"Feature\",\"geometry\":{\"type\":\"Polygon\",\"coordinates\":[[[121.03,14.70],[121.06,14.70],[121.06,14.75],[121.03,14.75],[121.03,14.70]]]},\"properties\":{\"zone_code\":\"R1\"}}', 0, 'Low Density Residential (R-1)', NULL, '2026-02-20 12:09:58', '2026-02-20 12:09:58'),
(4, 'QC-MASTER-CENTRAL', 'MASTER-C', 'Diliman', NULL, NULL, 4, NULL, NULL, '{\"type\":\"Feature\",\"geometry\":{\"type\":\"Polygon\",\"coordinates\":[[[121.02,14.63],[121.05,14.63],[121.05,14.68],[121.02,14.68],[121.02,14.63]]]},\"properties\":{\"zone_code\":\"C2\"}}', 0, 'Major Commercial (C-2)', NULL, '2026-02-20 12:09:58', '2026-02-20 12:09:58'),
(5, 'QC-MASTER-INST', 'MASTER-I', 'Central', NULL, NULL, 9, NULL, NULL, '{\"type\":\"Feature\",\"geometry\":{\"type\":\"Polygon\",\"coordinates\":[[[121.045,14.645],[121.055,14.645],[121.055,14.655],[121.045,14.655],[121.045,14.645]]]},\"properties\":{\"zone_code\":\"INST\"}}', 0, 'Institutional Zone', NULL, '2026-02-20 12:09:58', '2026-02-20 12:09:58'),
(6, 'QC-ZONE-R1', 'R1-01', 'Novaliches', NULL, NULL, 1, NULL, NULL, '{\"type\":\"Feature\",\"geometry\":{\"type\":\"Polygon\",\"coordinates\":[[[121.03,14.70],[121.06,14.70],[121.06,14.75],[121.03,14.75],[121.03,14.70]]]},\"properties\":{\"zone_code\":\"R1\"}}', 0, 'Low Density Residential (R-1)', NULL, '2026-02-20 12:14:05', '2026-02-20 12:14:05'),
(7, 'QC-ZONE-R2', 'R2-01', 'Fairview', NULL, NULL, 2, NULL, NULL, '{\"type\":\"Feature\",\"geometry\":{\"type\":\"Polygon\",\"coordinates\":[[[121.05,14.68],[121.08,14.68],[121.08,14.72],[121.05,14.72],[121.05,14.68]]]},\"properties\":{\"zone_code\":\"R2\"}}', 0, 'Medium Density Residential (R-2)', NULL, '2026-02-20 12:14:05', '2026-02-20 12:14:05'),
(8, 'QC-ZONE-R3', 'R3-01', 'Batasan Hills', NULL, NULL, 6, NULL, NULL, '{\"type\":\"Feature\",\"geometry\":{\"type\":\"Polygon\",\"coordinates\":[[[121.09,14.67],[121.11,14.67],[121.11,14.70],[121.09,14.70],[121.09,14.67]]]},\"properties\":{\"zone_code\":\"R-3\"}}', 0, 'High-Density Residential', NULL, '2026-02-20 12:14:05', '2026-02-20 12:14:05'),
(9, 'QC-ZONE-C1', 'C1-01', 'Project 4', NULL, NULL, 3, NULL, NULL, '{\"type\":\"Feature\",\"geometry\":{\"type\":\"Polygon\",\"coordinates\":[[[121.06,14.62],[121.08,14.62],[121.08,14.65],[121.06,14.65],[121.06,14.62]]]},\"properties\":{\"zone_code\":\"C1\"}}', 0, 'Neighborhood Commercial (C-1)', NULL, '2026-02-20 12:14:05', '2026-02-20 12:14:05'),
(10, 'QC-ZONE-C2', 'C2-01', 'Diliman', NULL, NULL, 4, NULL, NULL, '{\"type\":\"Feature\",\"geometry\":{\"type\":\"Polygon\",\"coordinates\":[[[121.02,14.63],[121.05,14.63],[121.05,14.68],[121.02,14.68],[121.02,14.63]]]},\"properties\":{\"zone_code\":\"C2\"}}', 0, 'Major Commercial (C-2)', NULL, '2026-02-20 12:14:05', '2026-02-20 12:14:05'),
(11, 'QC-ZONE-C3', 'C3-01', 'Cubao', NULL, NULL, 7, NULL, NULL, '{\"type\":\"Feature\",\"geometry\":{\"type\":\"Polygon\",\"coordinates\":[[[121.04,14.61],[121.06,14.61],[121.06,14.63],[121.04,14.63],[121.04,14.61]]]},\"properties\":{\"zone_code\":\"C-3\"}}', 0, 'Metropolitan Commercial', NULL, '2026-02-20 12:14:05', '2026-02-20 12:14:05'),
(12, 'QC-ZONE-I1', 'I1-01', 'Balintawak', NULL, NULL, 5, NULL, NULL, '{\"type\":\"Feature\",\"geometry\":{\"type\":\"Polygon\",\"coordinates\":[[[121.00,14.65],[121.02,14.65],[121.02,14.67],[121.00,14.67],[121.00,14.65]]]},\"properties\":{\"zone_code\":\"I1\"}}', 0, 'Light Industrial (I-1)', NULL, '2026-02-20 12:14:05', '2026-02-20 12:14:05'),
(13, 'QC-ZONE-I2', 'I2-01', 'Talipapa', NULL, NULL, 8, NULL, NULL, '{\"type\":\"Feature\",\"geometry\":{\"type\":\"Polygon\",\"coordinates\":[[[121.01,14.68],[121.03,14.68],[121.03,14.70],[121.01,14.70],[121.01,14.68]]]},\"properties\":{\"zone_code\":\"I-2\"}}', 0, 'Medium Industrial', NULL, '2026-02-20 12:14:05', '2026-02-20 12:14:05'),
(14, 'QC-ZONE-INST', 'INST-01', 'Central', NULL, NULL, 9, NULL, NULL, '{\"type\":\"Feature\",\"geometry\":{\"type\":\"Polygon\",\"coordinates\":[[[121.045,14.645],[121.055,14.645],[121.055,14.655],[121.045,14.655],[121.045,14.645]]]},\"properties\":{\"zone_code\":\"INST\"}}', 0, 'Institutional Zone', NULL, '2026-02-20 12:14:05', '2026-02-20 12:14:05'),
(15, 'QC-ZONE-PRK', 'PRK-01', 'Vasra', NULL, NULL, 10, NULL, NULL, '{\"type\":\"Feature\",\"geometry\":{\"type\":\"Polygon\",\"coordinates\":[[[121.04,14.65],[121.05,14.65],[121.05,14.66],[121.04,14.66],[121.04,14.65]]]},\"properties\":{\"zone_code\":\"PRK\"}}', 0, 'Parks and Recreation', NULL, '2026-02-20 12:14:05', '2026-02-20 12:14:05'),
(16, 'QC-ZONE-SCZ', 'SCZ-01', 'Payatas', NULL, NULL, 11, NULL, NULL, '{\"type\":\"Feature\",\"geometry\":{\"type\":\"Polygon\",\"coordinates\":[[[121.10,14.71],[121.13,14.71],[121.13,14.74],[121.10,14.74],[121.10,14.71]]]},\"properties\":{\"zone_code\":\"S-CZ\"}}', 0, 'Special Control Zone', NULL, '2026-02-20 12:14:05', '2026-02-20 12:14:05');

-- --------------------------------------------------------

--
-- Table structure for table `permitted_uses`
--

CREATE TABLE `permitted_uses` (
  `id` int(11) NOT NULL,
  `zone_code` varchar(10) DEFAULT NULL,
  `project_type` varchar(100) DEFAULT NULL,
  `is_allowed` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `permitted_uses`
--

INSERT INTO `permitted_uses` (`id`, `zone_code`, `project_type`, `is_allowed`) VALUES
(6, 'R1', 'Single-family dwelling', 1),
(7, 'R1', 'Duplex', 1),
(8, 'C1', 'Sari-sari Store', 1),
(9, 'C2', 'Malls', 1),
(12, 'R2', 'Multi-family dwellings', 1),
(13, 'R2', 'Residential condominiums', 1),
(14, 'R-3', 'High-rise residential buildings', 1),
(16, 'C1', 'Bakeries', 1),
(17, 'C1', 'Barber shops', 1),
(19, 'C2', 'Commercial', 1),
(20, 'C2', 'BPO Offices', 1),
(21, 'C2', 'Hotels', 1),
(22, 'C-3', 'Regional shopping centers', 1),
(23, 'C-3', 'Metropolitan Commercial', 1),
(24, 'I1', 'Light Industrial', 1),
(25, 'I1', 'Warehouses', 1),
(26, 'I-2', 'Medium Industrial', 1),
(27, 'I-2', 'Factories', 1),
(28, 'INST', 'Schools', 1),
(29, 'INST', 'Hospitals', 1),
(30, 'INST', 'Government Offices', 1),
(31, 'PRK', 'Public parks', 1),
(32, 'PRK', 'Playgrounds', 1),
(33, 'S-CZ', 'Low-impact structures', 1);

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `report_type` varchar(50) NOT NULL,
  `report_name` varchar(255) NOT NULL,
  `generated_by` int(11) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `parameters` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reports`
--

INSERT INTO `reports` (`id`, `report_type`, `report_name`, `generated_by`, `file_path`, `parameters`, `created_at`) VALUES
(1, 'applications_summary', 'Applications Summary Report', 1, NULL, '{\"date_from\":\"\",\"date_to\":\"\",\"year\":\"2025\"}', '2025-12-26 19:11:21'),
(2, 'applications_summary', 'Applications Summary Report', 1, NULL, '{\"date_from\":\"2025-12-25\",\"date_to\":\"2025-12-27\",\"year\":\"2025\"}', '2025-12-26 19:16:56'),
(3, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"date_from\":\"2025-12-27\",\"date_to\":\"2025-12-29\",\"year\":\"2025\"}', '2025-12-29 15:31:21'),
(4, 'applications_summary', 'Applications Summary Report', 1, NULL, '{\"date_from\":\"\",\"date_to\":\"\",\"year\":\"2025\"}', '2025-12-29 15:50:18'),
(5, 'applications_summary', 'Applications Summary Report', 1, NULL, '{\"date_from\":\"2025-12-27\",\"date_to\":\"2025-12-29\",\"year\":\"2025\"}', '2025-12-29 15:50:39'),
(6, 'applications_summary', 'Applications Summary Report', 1, NULL, '{\"date_from\":\"2025-12-27\",\"date_to\":\"2025-12-29\",\"year\":\"2025\"}', '2025-12-29 15:51:46'),
(7, 'applications_summary', 'Applications Summary Report', 1, NULL, '{\"date_from\":\"2025-12-26\",\"date_to\":\"2025-12-29\",\"year\":\"2025\"}', '2025-12-29 15:52:00'),
(8, 'zoning_compliance', 'Zoning Compliance Report', 1, NULL, '{\"date_from\":\"2025-12-27\",\"date_to\":\"2025-12-29\",\"year\":\"2025\"}', '2025-12-29 15:52:22'),
(9, 'applications_summary', 'Applications Summary Report', 1, NULL, '{\"date_from\":\"\",\"date_to\":\"\",\"year\":\"2025\"}', '2025-12-29 15:57:11'),
(10, 'applications_summary', 'Applications Summary Report', 1, NULL, '{\"date_from\":\"\",\"date_to\":\"\",\"year\":\"2025\"}', '2025-12-29 16:02:50'),
(11, 'applications_summary', 'Applications Summary Report', 1, NULL, '{\"date_from\":\"2025-12-27\",\"date_to\":\"2025-12-30\",\"year\":\"2025\"}', '2025-12-29 16:03:07'),
(12, 'zoning_compliance', 'Zoning Compliance Report', 1, NULL, '{\"date_from\":\"2025-12-27\",\"date_to\":\"2025-12-30\",\"year\":\"2025\"}', '2025-12-29 16:03:26'),
(13, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"date_from\":\"2025-12-27\",\"date_to\":\"2025-12-30\",\"year\":\"2025\"}', '2025-12-29 16:03:39'),
(14, 'monthly_analytics', 'Monthly Analytics Report - 2025', 1, NULL, '{\"date_from\":\"2025-12-27\",\"date_to\":\"2025-12-30\",\"year\":\"2025\"}', '2025-12-29 16:03:51'),
(15, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"date_from\":\"\",\"date_to\":\"\",\"year\":\"2025\"}', '2025-12-29 16:04:11'),
(16, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"date_from\":\"\",\"date_to\":\"\",\"year\":\"2025\"}', '2025-12-29 16:06:44'),
(17, 'zoning_compliance', 'Zoning Compliance Report', 1, NULL, '{\"date_from\":\"\",\"date_to\":\"\",\"year\":\"2025\"}', '2025-12-29 16:11:28'),
(18, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"date_from\":\"\",\"date_to\":\"\",\"year\":\"2025\"}', '2025-12-29 16:11:35'),
(19, 'zoning_compliance', 'Zoning Compliance Report', 1, NULL, '{\"date_from\":\"\",\"date_to\":\"\",\"year\":\"2025\"}', '2025-12-29 16:11:39'),
(20, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"date_from\":\"\",\"date_to\":\"\",\"year\":\"2025\"}', '2025-12-29 16:14:47'),
(21, 'applications_summary', 'Applications Summary Report', 1, NULL, '{\"date_from\":\"2025-12-27\",\"date_to\":\"2025-12-30\",\"year\":\"2025\"}', '2025-12-29 16:14:54'),
(22, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"date_from\":\"2025-12-27\",\"date_to\":\"2025-12-30\",\"year\":\"2025\"}', '2025-12-29 16:16:41'),
(23, 'applications_summary', 'Applications Summary Report', 1, NULL, '{\"date_from\":\"\",\"date_to\":\"\",\"year\":\"2025\"}', '2025-12-29 16:17:39'),
(24, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"date_from\":\"2025-12-27\",\"date_to\":\"2025-12-30\",\"year\":\"2025\"}', '2025-12-29 16:18:19'),
(25, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"date_from\":\"2025-12-27\",\"date_to\":\"2025-12-30\",\"year\":\"2025\"}', '2025-12-29 16:18:46'),
(26, 'applications_summary', 'Applications Summary Report', 1, NULL, '{\"date_from\":\"2025-12-27\",\"date_to\":\"2025-12-30\",\"year\":\"2025\"}', '2025-12-29 16:18:54'),
(27, 'zoning_compliance', 'Zoning Compliance Report', 1, NULL, '{\"date_from\":\"2025-12-27\",\"date_to\":\"2025-12-30\",\"year\":\"2025\"}', '2025-12-29 16:19:03'),
(28, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"date_from\":\"2025-12-27\",\"date_to\":\"2025-12-30\",\"year\":\"2025\"}', '2025-12-29 16:19:14'),
(29, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"date_from\":\"2025-12-27\",\"date_to\":\"2025-12-30\",\"year\":\"2025\"}', '2025-12-29 16:22:40'),
(30, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"date_from\":\"2025-12-27\",\"date_to\":\"2025-12-30\",\"year\":\"2025\"}', '2025-12-29 16:22:55'),
(31, 'applications_summary', 'Applications Summary Report', 1, NULL, '{\"date_from\":\"2025-12-27\",\"date_to\":\"2025-12-30\",\"year\":\"2025\"}', '2025-12-29 16:23:09'),
(32, 'applications_summary', 'Applications Summary Report', 1, NULL, '{\"date_from\":\"2025-12-27\",\"date_to\":\"2025-12-30\",\"year\":\"2025\"}', '2025-12-29 16:23:14'),
(33, 'permits_issued', 'Permits Issued Report', 1, NULL, '[]', '2025-12-29 16:23:20'),
(34, 'permits_issued', 'Permits Issued Report', 1, NULL, '[]', '2025-12-29 16:23:38'),
(35, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:25:15'),
(36, 'permits_issued', 'Permits Issued Report', 1, NULL, '[]', '2025-12-29 16:28:53'),
(37, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:29:03'),
(38, 'zoning_compliance', 'Zoning Compliance Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:29:06'),
(39, 'zoning_compliance', 'Zoning Compliance Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:29:17'),
(40, 'zoning_compliance', 'Zoning Compliance Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:29:34'),
(41, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:29:46'),
(42, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:31:11'),
(43, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:31:29'),
(44, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:32:38'),
(45, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:32:46'),
(46, 'applications_summary', 'Applications Summary Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:32:50'),
(47, 'applications_summary', 'Applications Summary Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:33:18'),
(48, 'applications_summary', 'Applications Summary Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:33:26'),
(49, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:33:48'),
(50, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:36:34'),
(51, 'applications_summary', 'Applications Summary Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:36:46'),
(52, 'applications_summary', 'Applications Summary Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:36:52'),
(53, 'applications_summary', 'Applications Summary Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:36:54'),
(54, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:36:57'),
(55, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:38:01'),
(56, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:41:11'),
(57, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:42:03'),
(58, 'applications_summary', 'Applications Summary Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:42:13'),
(59, 'applications_summary', 'Applications Summary Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:44:34'),
(60, 'zoning_compliance', 'Zoning Compliance Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:44:45'),
(61, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:44:48'),
(62, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:45:36'),
(63, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:45:41'),
(64, 'applications_summary', 'Applications Summary Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:46:54'),
(65, 'zoning_compliance', 'Zoning Compliance Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:47:10'),
(66, 'applications_summary', 'Applications Summary Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:47:13'),
(67, 'applications_summary', 'Applications Summary Report', 1, NULL, '{\"date_from\":\"2025-12-27\",\"date_to\":\"2025-12-30\",\"year\":\"2025\"}', '2025-12-29 16:47:20'),
(68, 'applications_summary', 'Applications Summary Report', 1, NULL, '{\"date_from\":\"2025-12-27\",\"date_to\":\"2025-12-30\",\"year\":\"2025\"}', '2025-12-29 16:47:23'),
(69, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"date_from\":\"2025-12-27\",\"date_to\":\"2025-12-30\",\"year\":\"2025\"}', '2025-12-29 16:47:27'),
(70, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:48:47'),
(71, 'zoning_compliance', 'Zoning Compliance Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:50:08'),
(72, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:50:11'),
(73, 'applications_summary', 'Applications Summary Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:51:58'),
(74, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:52:07'),
(75, 'applications_summary', 'Applications Summary Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:53:43'),
(76, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:53:48'),
(77, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:55:50'),
(78, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:58:14'),
(79, 'applications_summary', 'Applications Summary Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:58:21'),
(80, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:58:24'),
(81, 'applications_summary', 'Applications Summary Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 16:59:13'),
(82, 'applications_summary', 'Applications Summary Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 17:01:32'),
(83, 'applications_summary', 'Applications Summary Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 17:01:48'),
(84, 'applications_summary', 'Applications Summary Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 17:03:19'),
(85, 'applications_summary', 'Applications Summary Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 17:04:08'),
(86, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-29 17:06:53'),
(87, 'zoning_compliance', 'Zoning Compliance Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-30 18:02:53'),
(88, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-30 18:03:11'),
(89, 'applications_summary', 'Applications Summary Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-30 18:03:21'),
(90, 'monthly_analytics', 'Monthly Analytics Report - 2025', 1, NULL, '{\"year\":\"2025\"}', '2025-12-30 18:04:33'),
(91, 'zoning_compliance', 'Zoning Compliance Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-30 18:05:50'),
(92, 'applications_summary', 'Applications Summary Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-30 18:05:58'),
(93, 'applications_summary', 'Applications Summary', 1, NULL, '{\"year\":\"2025\"}', '2025-12-30 18:07:10'),
(94, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-30 18:08:10'),
(95, 'zoning_compliance', 'Zoning Compliance List', 1, NULL, '{\"year\":\"2025\"}', '2025-12-30 18:08:16'),
(96, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-30 18:14:57'),
(97, 'zoning_compliance', 'Zoning Compliance List', 1, NULL, '{\"year\":\"2025\"}', '2025-12-30 18:16:42'),
(98, 'applications_summary', 'Applications Summary', 1, NULL, '{\"year\":\"2025\"}', '2025-12-30 18:16:57'),
(99, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-30 18:17:00'),
(100, 'zoning_compliance', 'Zoning Compliance List', 1, NULL, '{\"year\":\"2025\"}', '2025-12-30 18:17:02'),
(101, 'audit_summary', 'System Audit Summary', 1, NULL, '{\"year\":\"2025\"}', '2025-12-30 18:20:19'),
(102, 'audit_summary', 'System Audit Summary', 1, NULL, '{\"year\":\"2025\"}', '2025-12-30 18:22:47'),
(103, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-30 18:23:38'),
(104, 'audit_summary', 'System Audit Summary', 1, NULL, '{\"year\":\"2025\"}', '2025-12-30 18:24:18'),
(105, 'user_growth', 'User Growth Report (2025)', 1, NULL, '{\"year\":\"2025\"}', '2025-12-30 18:25:15'),
(106, 'monthly_analytics', 'Monthly Analytics (2025)', 1, NULL, '{\"year\":\"2025\"}', '2025-12-30 18:25:21'),
(107, 'audit_summary', 'System Audit Summary', 1, NULL, '{\"year\":\"2025\"}', '2025-12-30 18:25:43'),
(108, 'audit_summary', 'System Audit Summary', 1, NULL, '{\"year\":\"2025\"}', '2025-12-30 18:25:55'),
(109, 'audit_summary', 'System Audit Summary', 1, NULL, '{\"year\":\"2025\"}', '2025-12-30 18:26:07'),
(110, 'audit_summary', 'System Audit Summary', 1, NULL, '{\"year\":\"2025\"}', '2025-12-30 18:26:10'),
(111, 'audit_summary', 'System Audit Summary', 1, NULL, '{\"year\":\"2025\"}', '2025-12-30 18:26:15'),
(112, 'audit_summary', 'System Audit Summary', 1, NULL, '{\"year\":\"2025\"}', '2025-12-30 18:26:27'),
(113, 'audit_summary', 'System Audit Summary', 1, NULL, '{\"year\":\"2025\"}', '2025-12-30 18:26:31'),
(114, 'audit_summary', 'System Audit Summary', 1, NULL, '{\"year\":\"2025\"}', '2025-12-30 18:30:36'),
(115, 'audit_summary', 'System Audit Summary', 1, NULL, '{\"year\":\"2025\"}', '2025-12-30 18:30:53'),
(116, 'audit_summary', 'System Audit Summary', 1, NULL, '{\"year\":\"2025\"}', '2025-12-30 18:30:55'),
(117, 'user_growth', 'User Growth Report (2025)', 1, NULL, '{\"year\":\"2025\"}', '2025-12-30 18:31:26'),
(118, 'monthly_analytics', 'Monthly Analytics (2025)', 1, NULL, '{\"year\":\"2025\"}', '2025-12-30 18:31:36'),
(119, 'applications_summary', 'Applications Summary', 1, NULL, '{\"year\":\"2025\"}', '2025-12-30 18:32:58'),
(120, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"year\":\"2025\"}', '2025-12-30 18:34:01'),
(121, 'zoning_compliance', 'Zoning Compliance List', 1, NULL, '{\"year\":\"2025\"}', '2025-12-30 18:34:07'),
(122, 'audit_summary', 'System Audit Summary', 1, NULL, '{\"year\":\"2025\"}', '2025-12-30 18:34:18'),
(123, 'audit_summary', 'System Audit Summary', 1, NULL, '{\"year\":2025}', '2025-12-30 18:39:50'),
(124, 'audit_summary', 'System Audit Summary', 1, NULL, '{\"year\":2024}', '2025-12-30 18:39:50'),
(125, 'applications_summary', 'Applications Summary', 1, NULL, '{\"year\":2025}', '2025-12-30 18:40:08'),
(126, 'applications_summary', 'Applications Summary', 1, NULL, '{\"year\":2024}', '2025-12-30 18:40:08'),
(127, 'applications_summary', 'Applications Summary', 1, NULL, '{\"year\":2026}', '2026-01-01 18:19:39'),
(128, 'applications_summary', 'Applications Summary', 1, NULL, '{\"year\":2025}', '2026-01-01 18:19:40'),
(129, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"year\":2026}', '2026-01-01 18:19:45'),
(130, 'permits_issued', 'Permits Issued Report', 1, NULL, '{\"year\":2025}', '2026-01-01 18:19:45'),
(131, 'zoning_compliance', 'Zoning Compliance List', 1, NULL, '{\"year\":2026}', '2026-01-01 18:19:50'),
(132, 'zoning_compliance', 'Zoning Compliance List', 1, NULL, '{\"year\":2025}', '2026-01-01 18:19:50'),
(133, 'audit_summary', 'System Audit Summary', 1, NULL, '{\"year\":2026}', '2026-01-01 18:19:58'),
(134, 'audit_summary', 'System Audit Summary', 1, NULL, '{\"year\":2025}', '2026-01-01 18:19:58'),
(135, 'applications_summary', 'Applications Summary', 1, NULL, '{\"year\":2026}', '2026-02-20 21:33:05'),
(136, 'applications_summary', 'Applications Summary', 1, NULL, '{\"year\":2025}', '2026-02-20 21:33:05'),
(137, 'applications_summary', 'Applications Summary', 1, NULL, '{\"year\":2026}', '2026-02-23 05:25:14'),
(138, 'applications_summary', 'Applications Summary', 1, NULL, '{\"year\":2025}', '2026-02-23 05:25:14'),
(139, 'applications_summary', 'Applications Summary', 1, NULL, '{\"year\":2026}', '2026-02-25 05:28:39'),
(140, 'applications_summary', 'Applications Summary', 1, NULL, '{\"year\":2025}', '2026-02-25 05:28:39'),
(141, 'applications_summary', 'Applications Summary', 1, NULL, '{\"year\":2026}', '2026-02-25 05:30:49'),
(142, 'applications_summary', 'Applications Summary', 1, NULL, '{\"year\":2025}', '2026-02-25 05:30:49'),
(143, 'applications_summary', 'Applications Summary (2026)', 1, NULL, '{\"year\":2026}', '2026-02-25 17:50:14'),
(144, 'audit_summary', 'System Audit Summary (Latest 100)', 1, NULL, '{\"year\":2026}', '2026-02-25 17:53:43'),
(145, 'audit_summary', 'System Audit Summary (Latest 100)', 1, NULL, '{\"year\":2025}', '2026-02-25 17:53:43'),
(146, 'user_growth', 'User Growth Report (2026)', 1, NULL, '{\"year\":2026}', '2026-02-25 17:54:02'),
(147, 'user_growth', 'User Growth Report (2025)', 1, NULL, '{\"year\":2025}', '2026-02-25 17:54:02'),
(148, 'monthly_analytics', 'Monthly Analytics (2026)', 1, NULL, '{\"year\":2026}', '2026-02-25 17:54:10'),
(149, 'zoning_compliance', 'Zoning Compliance Report (2026)', 1, NULL, '{\"year\":2026}', '2026-02-25 18:02:00'),
(150, 'inspector_performance', 'Inspector Workload Summary (2026)', 1, NULL, '{\"year\":2026}', '2026-02-25 18:03:40'),
(151, 'applications_summary', 'Applications Summary (2026)', 1, NULL, '{\"year\":2026}', '2026-05-02 17:25:47'),
(152, 'applications_summary', 'Applications Summary (2026)', 1, NULL, '{\"year\":2026}', '2026-05-03 08:15:05');

-- --------------------------------------------------------

--
-- Table structure for table `road_inspection_requests`
--
-- Tracks outbound road inspection requests sent to IPMS
-- (lgu-urban-planning/ipms-integration/RoadsIntegrationService.php) and the
-- completed results pulled back by the poller
-- (lgu-urban-planning/ipms-integration/ipms_inspection_result.php).
--

CREATE TABLE `road_inspection_requests` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `status` enum('pending','sent','failed','completed') NOT NULL DEFAULT 'pending',
  `request_payload` text DEFAULT NULL,
  `requested_by` int(11) DEFAULT NULL,
  `requested_at` datetime DEFAULT NULL,
  `external_ref_id` varchar(64) DEFAULT NULL,
  `response_payload` text DEFAULT NULL,
  `responded_at` datetime DEFAULT NULL,
  `overall_condition` enum('Excellent','Good','Fair','Poor','Critical') DEFAULT NULL,
  `severity` enum('low','medium','high','critical') DEFAULT NULL,
  `recommendation` enum('Routine Maintenance','Repair','Rehabilitation','Road Reconstruction','Further Investigation','No Action Needed') DEFAULT NULL,
  `engineer_assigned` varchar(150) DEFAULT NULL,
  `inspection_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL,
  `role` varchar(50) NOT NULL,
  `permission` varchar(100) NOT NULL,
  `is_allowed` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`id`, `role`, `permission`, `is_allowed`) VALUES
(1, 'admin', 'manage_users', 1),
(2, 'admin', 'view_all_applications', 1),
(3, 'admin', 'manage_zoning', 1),
(4, 'admin', 'generate_reports', 1),
(5, 'admin', 'view_audit_logs', 1),
(6, 'zoning_officer', 'review_applications', 1),
(7, 'zoning_officer', 'check_zoning_compliance', 1),
(8, 'zoning_officer', 'update_application_status', 1),
(9, 'building_official', 'review_applications', 1),
(10, 'building_official', 'update_application_status', 1),
(11, 'assessor', 'view_applications', 1),
(12, 'assessor', 'update_parcel_info', 1),
(13, 'applicant', 'submit_application', 1),
(14, 'applicant', 'view_own_applications', 1),
(15, 'applicant', 'upload_documents', 1),
(17, 'super_admin', 'view_all_applications', 1),
(18, 'super_admin', 'manage_users', 1),
(19, 'super_admin', 'manage_zoning', 1),
(20, 'super_admin', 'generate_reports', 1),
(21, 'super_admin', 'review_applications', 1),
(22, 'super_admin', 'update_application_status', 1),
(23, 'super_admin', 'check_zoning_compliance', 1),
(24, 'super_admin', 'view_applications', 1),
(25, 'super_admin', 'update_parcel_info', 1),
(26, 'super_admin', 'view_audit_logs', 1),
(27, 'super_admin', 'purge_audit_logs', 1),
(28, 'super_admin', 'export_audit_logs', 1),
(29, 'super_admin', 'view_messages', 1),
(30, 'super_admin', 'manage_deletion_requests', 1),
(31, 'super_admin', 'add_remarks', 1),
(32, 'super_admin', 'send_inspection_request', 1),
(33, 'super_admin', 'save_impact_assessment', 1),
(34, 'super_admin', 'run_mock_impact_assessment', 1),
(35, 'super_admin', 'generate_permit', 1),
(36, 'super_admin', 'view_gis_map', 1),
(37, 'super_admin', 'check_spatial_compliance', 1),
(52, 'super_admin', 'view_reports', 1),
(53, 'super_admin', 'export_reports', 1),
(64, 'super_admin', 'submit_application', 0),
(65, 'super_admin', 'view_own_applications', 0),
(66, 'super_admin', 'upload_documents', 0),
(72, 'admin', 'review_applications', 1),
(73, 'admin', 'update_application_status', 1),
(74, 'admin', 'check_zoning_compliance', 1),
(75, 'admin', 'view_applications', 1),
(76, 'admin', 'update_parcel_info', 1),
(77, 'admin', 'submit_application', 0),
(78, 'admin', 'view_own_applications', 0),
(79, 'admin', 'upload_documents', 0),
(80, 'zoning_officer', 'view_all_applications', 1),
(81, 'zoning_officer', 'manage_users', 0),
(82, 'zoning_officer', 'manage_zoning', 1),
(83, 'zoning_officer', 'generate_reports', 1),
(84, 'zoning_officer', 'view_audit_logs', 0),
(88, 'zoning_officer', 'view_applications', 1),
(89, 'zoning_officer', 'update_parcel_info', 0),
(90, 'zoning_officer', 'submit_application', 0),
(91, 'zoning_officer', 'view_own_applications', 0),
(92, 'zoning_officer', 'upload_documents', 0),
(93, 'building_official', 'view_all_applications', 1),
(94, 'building_official', 'manage_users', 0),
(95, 'building_official', 'manage_zoning', 0),
(96, 'building_official', 'generate_reports', 0),
(97, 'building_official', 'view_audit_logs', 0),
(100, 'building_official', 'check_zoning_compliance', 0),
(101, 'building_official', 'view_applications', 1),
(102, 'building_official', 'update_parcel_info', 0),
(103, 'building_official', 'submit_application', 0),
(104, 'building_official', 'view_own_applications', 0),
(105, 'building_official', 'upload_documents', 0),
(106, 'assessor', 'view_all_applications', 1),
(107, 'assessor', 'manage_users', 0),
(108, 'assessor', 'manage_zoning', 0),
(109, 'assessor', 'generate_reports', 0),
(110, 'assessor', 'view_audit_logs', 0),
(111, 'assessor', 'review_applications', 0),
(112, 'assessor', 'update_application_status', 0),
(113, 'assessor', 'check_zoning_compliance', 0),
(116, 'assessor', 'submit_application', 0),
(117, 'assessor', 'view_own_applications', 0),
(118, 'assessor', 'upload_documents', 0),
(119, 'inspector', 'view_all_applications', 0),
(120, 'inspector', 'manage_users', 0),
(121, 'inspector', 'manage_zoning', 0),
(122, 'inspector', 'generate_reports', 0),
(123, 'inspector', 'view_audit_logs', 0),
(124, 'inspector', 'review_applications', 0),
(125, 'inspector', 'update_application_status', 0),
(126, 'inspector', 'check_zoning_compliance', 0),
(127, 'inspector', 'view_applications', 0),
(128, 'inspector', 'update_parcel_info', 0),
(129, 'inspector', 'submit_application', 0),
(130, 'inspector', 'view_own_applications', 0),
(131, 'inspector', 'upload_documents', 0),
(132, 'applicant', 'view_all_applications', 0),
(133, 'applicant', 'manage_users', 0),
(134, 'applicant', 'manage_zoning', 0),
(135, 'applicant', 'generate_reports', 0),
(136, 'applicant', 'view_audit_logs', 0),
(137, 'applicant', 'review_applications', 0),
(138, 'applicant', 'update_application_status', 0),
(139, 'applicant', 'check_zoning_compliance', 0),
(140, 'applicant', 'view_applications', 0),
(141, 'applicant', 'update_parcel_info', 0),
(145, 'super_admin', 'manage_settings', 1),
(146, 'super_admin', 'submit_inspection_report', 0),
(147, 'admin', 'manage_settings', 1),
(148, 'admin', 'export_audit_logs', 1),
(149, 'admin', 'purge_audit_logs', 1),
(150, 'admin', 'view_messages', 1),
(151, 'admin', 'manage_deletion_requests', 1),
(152, 'admin', 'add_remarks', 1),
(153, 'admin', 'generate_permit', 1),
(154, 'admin', 'send_inspection_request', 1),
(155, 'admin', 'submit_inspection_report', 0),
(156, 'admin', 'view_gis_map', 1),
(157, 'admin', 'check_spatial_compliance', 1),
(158, 'zoning_officer', 'manage_settings', 0),
(159, 'zoning_officer', 'export_audit_logs', 0),
(160, 'zoning_officer', 'purge_audit_logs', 0),
(161, 'zoning_officer', 'view_messages', 1),
(162, 'zoning_officer', 'manage_deletion_requests', 0),
(163, 'zoning_officer', 'add_remarks', 1),
(164, 'zoning_officer', 'generate_permit', 0),
(165, 'zoning_officer', 'send_inspection_request', 1),
(166, 'zoning_officer', 'submit_inspection_report', 0),
(167, 'zoning_officer', 'view_gis_map', 1),
(168, 'zoning_officer', 'check_spatial_compliance', 1),
(169, 'building_official', 'manage_settings', 0),
(170, 'building_official', 'export_audit_logs', 0),
(171, 'building_official', 'purge_audit_logs', 0),
(172, 'building_official', 'view_messages', 1),
(173, 'building_official', 'manage_deletion_requests', 0),
(174, 'building_official', 'add_remarks', 1),
(175, 'building_official', 'generate_permit', 1),
(176, 'zonning_officer', 'send_inspection_request', 1),
(177, 'building_official', 'submit_inspection_report', 0),
(178, 'building_official', 'view_gis_map', 1),
(179, 'building_official', 'check_spatial_compliance', 0),
(180, 'assessor', 'manage_settings', 0),
(181, 'assessor', 'export_audit_logs', 0),
(182, 'assessor', 'purge_audit_logs', 0),
(183, 'assessor', 'view_messages', 1),
(184, 'assessor', 'manage_deletion_requests', 0),
(185, 'assessor', 'add_remarks', 0),
(186, 'assessor', 'generate_permit', 0),
(187, 'assessor', 'send_inspection_request', 0),
(188, 'assessor', 'submit_inspection_report', 0),
(189, 'assessor', 'view_gis_map', 1),
(190, 'assessor', 'check_spatial_compliance', 0),
(191, 'inspector', 'manage_settings', 0),
(192, 'inspector', 'export_audit_logs', 0),
(193, 'inspector', 'purge_audit_logs', 0),
(194, 'inspector', 'view_messages', 1),
(195, 'inspector', 'manage_deletion_requests', 0),
(196, 'inspector', 'add_remarks', 0),
(197, 'inspector', 'generate_permit', 0),
(198, 'inspector', 'send_inspection_request', 0),
(199, 'inspector', 'submit_inspection_report', 1),
(200, 'inspector', 'view_gis_map', 0),
(201, 'inspector', 'check_spatial_compliance', 0),
(202, 'applicant', 'manage_settings', 0),
(203, 'applicant', 'export_audit_logs', 0),
(204, 'applicant', 'purge_audit_logs', 0),
(205, 'applicant', 'view_messages', 0),
(206, 'applicant', 'manage_deletion_requests', 0),
(207, 'applicant', 'add_remarks', 0),
(208, 'applicant', 'generate_permit', 0),
(209, 'applicant', 'send_inspection_request', 0),
(210, 'applicant', 'submit_inspection_report', 0),
(211, 'applicant', 'view_gis_map', 0),
(212, 'applicant', 'check_spatial_compliance', 0);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'announcement_enabled', '1', '2026-05-03 01:10:30'),
(2, 'announcement_type', 'info', '2026-05-03 01:10:30'),
(3, 'announcement_message', 'We are currently performing system updates. You may encounter issues with registration or submissions; please save your drafts and try again later.', '2026-05-03 01:10:30'),
(4, 'locale_language', 'en_PH', '2026-05-14 04:25:38'),
(5, 'locale_date_format', 'F j, Y', '2026-05-14 04:25:38'),
(6, 'locale_time_format', '12h', '2026-05-14 04:25:38'),
(7, 'locale_timezone', 'Asia/Manila', '2026-05-14 04:25:38');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `is_active`) VALUES
(1, 'system_announcement', 'We are currently performing system updates. You may encounter issues with registration or submissions; please save your drafts and try again later.', 0),
(3, 'system_announcement_type', 'info', 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `avatar` varchar(500) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `role` enum('super_admin','admin','zoning_officer','building_official','assessor','applicant','inspector') NOT NULL DEFAULT 'applicant',
  `id_front_path` varchar(255) DEFAULT NULL,
  `id_back_path` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `street` varchar(255) DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `verification_token` varchar(100) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `rejection_reason` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_activity` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `otp_code` varchar(6) DEFAULT NULL,
  `otp_expiry` datetime DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `token_expiry` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `avatar`, `password_hash`, `first_name`, `last_name`, `role`, `id_front_path`, `id_back_path`, `phone`, `birth_date`, `street`, `barangay`, `city`, `verification_token`, `is_verified`, `rejection_reason`, `is_active`, `created_at`, `updated_at`, `last_activity`, `otp_code`, `otp_expiry`, `reset_token`, `token_expiry`) VALUES
(1, 'admin', 'admin.upad@gmail.com', NULL, '$2y$10$DTBLtHe4jebtOlK5FYEuouqcTe1JA0Q3lhA6k4CRqyVzmLA2hWAey', 'System', 'Administrator', 'admin', NULL, NULL, '', NULL, '', '', 'Quezon City', NULL, 1, NULL, 1, '2025-12-21 19:07:31', '2026-05-19 12:53:46', '2026-05-19 20:53:46', NULL, NULL, NULL, NULL),
(17, 'inspector', 'inspector.upad@gmail.com', NULL, '$2y$10$y6S0D3FpaYF/bvns.DtE5uyG/qcEvUOLUiZzxKg5kvL4Mt3vwS8r2', 'Inspector', 'Juan', 'inspector', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, 1, '2026-02-22 20:26:07', '2026-05-15 06:29:24', '2026-05-15 14:29:24', NULL, NULL, NULL, NULL),
(21, 'aelousssn', 'unknownfire01@gmail.com', '/lgu-urban-planning/assets/avatars/avatar_21_1777927933.jpg', '$2y$10$njOjj0sn0lyEHNOqZobOAeTDjeGscjVKextwSiuDPq3DterP6FrsO', 'Aelous', 'Nexus', 'applicant', 'uploads/ids/aelousssn_FRONT_1775047484.jpg', 'uploads/ids/aelousssn_BACK_1775047484.jpg', '9207249702', '2003-11-07', '6835 Sto Nino St.', '177', 'Caloocan City', NULL, 1, NULL, 1, '2026-04-01 12:44:44', '2026-05-14 05:52:58', '2026-05-14 13:52:58', NULL, NULL, NULL, NULL),
(22, 'your.fallensky', 'yumiedalagan01@gmail.com', NULL, '$2y$10$6u2NT6suIInlpjVg8KjRfOKsNbsX4.8nbM4No1K728cpYPOwaFHU.', 'Yumie Margareth', 'Dalagan', 'applicant', 'uploads/ids/your.fallensky_FRONT_1778172186.jpg', 'uploads/ids/your.fallensky_BACK_1778172186.jpg', '9207249702', '2003-11-07', '7216 Sunflower St', '177', 'Caloocan City', NULL, 1, NULL, 1, '2026-05-07 16:43:06', '2026-05-09 15:57:35', '2026-05-09 23:57:35', NULL, NULL, NULL, NULL),
(23, 'superadmin', 'superadmin.upad@gmail.com', NULL, '$2y$10$lCin0hqJQ/0u.naqeuwodOz1Eytd1fvMp7X9Scdz/AEQVUkcTe.ta', 'Super', 'Admin', 'super_admin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, 1, '2026-05-11 10:54:24', '2026-05-14 06:33:59', '2026-05-14 14:33:59', NULL, NULL, NULL, NULL),
(24, 'zoningofficer', 'zoningofficer.upad@gmail.com', NULL, '$2y$10$0ZNRBNNF2XLRN.96OfG94ulr80RIMnM3lmhipDw2a1KPe0nIbsGaq', 'Zoning', 'Officer', 'zoning_officer', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, 1, '2026-05-11 10:54:24', '2026-05-15 05:41:45', '2026-05-15 13:41:45', NULL, NULL, NULL, NULL),
(25, 'buildingofficial', 'buildingofficial.upad@gmail.com', NULL, '$2y$10$rxxyfRRnEdg6xZ8g.nZ5MOQES7qGjvkOzbq3TUDQLBBmlZD27713O', 'Building', 'Official', 'building_official', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, 1, '2026-05-11 10:54:25', '2026-05-15 05:59:03', '2026-05-15 13:59:03', NULL, NULL, NULL, NULL),
(26, 'assessor', 'assessor.upad@gmail.com', NULL, '$2y$10$BAmmujvNOGCtWkOlO/dUleG.Rf4aYPvlBRthH7hgjzDn8ePmy5Ula', 'Assessor', 'Juan', 'assessor', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, 1, '2026-05-11 10:54:25', '2026-05-15 06:06:26', '2026-05-15 14:06:26', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_preferences`
--

CREATE TABLE `user_preferences` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `pref_key` varchar(64) NOT NULL,
  `pref_value` varchar(255) NOT NULL DEFAULT '',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_preferences`
--

INSERT INTO `user_preferences` (`id`, `user_id`, `pref_key`, `pref_value`, `updated_at`) VALUES
(1, 21, 'locale_language', 'en_PH', '2026-05-07 17:11:39'),
(2, 21, 'locale_date_format', 'F j, Y', '2026-05-04 21:25:08'),
(3, 21, 'locale_time_format', '12h', '2026-05-07 10:57:07'),
(25, 22, 'account_deletion_requested', '1', '2026-05-07 16:52:11'),
(26, 22, 'locale_language', 'en_PH', '2026-05-07 16:55:34'),
(27, 22, 'locale_date_format', 'F j, Y', '2026-05-07 16:55:34'),
(28, 22, 'locale_time_format', '12h', '2026-05-07 16:55:34'),
(38, 21, 'pw_last_changed', '2026-05-09 07:28:39', '2026-05-08 23:28:39'),
(41, 21, 'account_deletion_requested', '0', '2026-05-09 02:27:01');

-- --------------------------------------------------------

--
-- Table structure for table `violations`
--

CREATE TABLE `violations` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `inspection_id` int(11) DEFAULT NULL,
  `violation_type` varchar(100) DEFAULT NULL,
  `severity` enum('low','medium','high') DEFAULT 'low',
  `notes` text DEFAULT NULL,
  `resolved` tinyint(1) DEFAULT 0,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `violation_photo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `zoning_classifications`
--

CREATE TABLE `zoning_classifications` (
  `id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `allowed_uses` text DEFAULT NULL,
  `restrictions` text DEFAULT NULL,
  `max_height` decimal(10,2) DEFAULT NULL,
  `max_density` decimal(10,2) DEFAULT NULL,
  `max_far` decimal(5,2) DEFAULT NULL,
  `setback_front` decimal(10,2) DEFAULT NULL,
  `setback_rear` decimal(10,2) DEFAULT NULL,
  `setback_side` decimal(10,2) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `zoning_classifications`
--

INSERT INTO `zoning_classifications` (`id`, `code`, `name`, `description`, `allowed_uses`, `restrictions`, `max_height`, `max_density`, `max_far`, `setback_front`, `setback_rear`, `setback_side`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'R1', 'Low Density Residential (R-1)', 'Mainly for single-family, single-detached dwellings (Section 13)', 'Single-family dwellings, Duplex, Community facilities, Home laundries', NULL, 10.00, NULL, 0.60, 5.00, 3.00, 2.00, 1, '2025-12-21 19:07:31', '2026-02-20 01:55:12'),
(2, 'R2', 'Medium Density Residential (R-2)', 'Multi-family dwellings, residential condominiums (Section 14)', 'Apartments, Boarding houses, Townhouses, Clinics', NULL, 15.00, NULL, 1.00, 4.00, 3.00, 2.00, 1, '2025-12-21 19:07:31', '2026-02-20 01:55:12'),
(3, 'C1', 'Neighborhood Commercial (C-1)', 'Small-scale commercial for neighborhood needs (Section 16)', 'Sari-sari stores, Bakeries, Barber shops, Neighborhood clinics', NULL, 20.00, NULL, 2.00, 3.00, 3.00, 2.00, 1, '2025-12-21 19:07:31', '2026-02-20 01:55:12'),
(4, 'C2', 'Major Commercial (C-2)', 'Medium to high-intensity commercial developments (Section 17)', 'Malls, BPO Offices, Hotels, Banks, Major hospitals', NULL, 30.00, NULL, 3.00, 5.00, 5.00, 3.00, 1, '2025-12-21 19:07:31', '2026-02-20 01:55:12'),
(5, 'I1', 'Light Industrial (I-1)', 'Non-pollutive/non-hazardous manufacturing (Section 20)', 'Warehouses, Food processing, Garment factories', NULL, 25.00, NULL, 1.50, 10.00, 10.00, 5.00, 1, '2025-12-21 19:07:31', '2026-02-20 01:55:12'),
(6, 'R-3', 'High-Density Residential', 'High-rise residential buildings', 'Condominiums, high-rise apartments', NULL, 60.00, NULL, NULL, 5.00, 3.00, 3.00, 1, '2026-02-20 01:52:41', '2026-02-20 01:52:41'),
(7, 'C-3', 'Metropolitan Commercial', 'Heavy commercial developments', 'Regional shopping centers, skyscrapers, transport terminals', NULL, 100.00, NULL, NULL, 5.00, 5.00, 4.00, 1, '2026-02-20 01:52:41', '2026-02-20 01:52:41'),
(8, 'I-2', 'Medium Industrial', 'Medium-scale manufacturing', 'Factories, large assembly plants, food processing', NULL, 25.00, NULL, NULL, 10.00, 10.00, 10.00, 1, '2026-02-20 01:52:41', '2026-02-20 01:52:41'),
(9, 'INST', 'Institutional Zone', 'Community and government facilities', 'Schools, Hospitals, Government Offices, Churches', NULL, 20.00, NULL, NULL, 5.00, 4.00, 4.00, 1, '2026-02-20 01:52:41', '2026-02-20 01:52:41'),
(10, 'PRK', 'Parks and Recreation', 'Open spaces and leisure areas', 'Public parks, playgrounds, botanical gardens', NULL, 5.00, NULL, NULL, 5.00, 5.00, 5.00, 1, '2026-02-20 01:52:41', '2026-02-20 01:52:41'),
(11, 'S-CZ', 'Special Control Zone', 'Heritage or environmental protection areas', 'Regulated low-impact structures', NULL, 10.00, NULL, NULL, 6.00, 4.00, 4.00, 1, '2026-02-20 01:52:41', '2026-02-20 01:52:41');

-- --------------------------------------------------------

--
-- Table structure for table `zoning_compliance_checks`
--

CREATE TABLE `zoning_compliance_checks` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `parcel_id` varchar(50) DEFAULT NULL,
  `zoning_type` varchar(100) DEFAULT NULL,
  `compliance_status` enum('compliant','non_compliant') DEFAULT NULL,
  `technical_analysis` text DEFAULT NULL,
  `checked_by` int(11) DEFAULT NULL,
  `checked_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `zoning_compliance_checks`
--

INSERT INTO `zoning_compliance_checks` (`id`, `application_id`, `parcel_id`, `zoning_type`, `compliance_status`, `technical_analysis`, `checked_by`, `checked_at`) VALUES
(56, 35, '10', 'Major Commercial (C-2)', 'compliant', 'AUTOMATED WARNING: Project type \'Commercial\' is NOT listed as a permitted use in Major Commercial (C-2). Spatial verification performed on coordinates [14.653300, 121.033300]. The project site is verified to be within the Major Commercial (C-2) zone. Matched cadastral record Lot C2-01, Block undefined. Automated spatial check indicates the location is consistent with LGU land use mapping.', 1, '2026-02-25 19:55:38'),
(59, 37, '10', 'Major Commercial (C-2)', 'compliant', 'Coordinates: [14.639400, 121.034700]\r\nZoning Zone: Major Commercial (C-2)\r\nLand Record: Lot 5, Block 10\r\nStatus Check: Consistent with LGU Land Use Mapping.', 1, '2026-02-25 22:52:00'),
(77, 39, '11', 'Metropolitan Commercial', 'compliant', 'Coordinates: [14.622500, 121.053300]\r\nZoning Zone: Metropolitan Commercial\r\nLand Record: Lot 1, Block 5\r\nStatus Check: Consistent with LGU Land Use Mapping.', 1, '2026-04-21 12:23:39');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `application_number` (`application_number`),
  ADD KEY `assigned_officer_id` (`assigned_officer_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_applicant_id` (`applicant_id`),
  ADD KEY `idx_application_number` (`application_number`);

--
-- Indexes for table `application_documents`
--
ALTER TABLE `application_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uploaded_by` (`uploaded_by`),
  ADD KEY `idx_application_id` (`application_id`);

--
-- Indexes for table `application_status_history`
--
ALTER TABLE `application_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `changed_by` (`changed_by`),
  ADD KEY `idx_application_id` (`application_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_action` (`action`);

--
-- Indexes for table `gis_layers`
--
ALTER TABLE `gis_layers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `impact_assessments`
--
ALTER TABLE `impact_assessments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assessed_by` (`assessed_by`),
  ADD UNIQUE KEY `idx_app` (`application_id`);

--
-- Indexes for table `inspections`
--
ALTER TABLE `inspections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inspector_id` (`inspector_id`),
  ADD KEY `idx_app_inspection` (`application_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `idx_receiver_id` (`receiver_id`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_application_id` (`application_id`);

--
-- Indexes for table `parcels`
--
ALTER TABLE `parcels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `parcel_id` (`parcel_id`),
  ADD KEY `zoning_classification_id` (`zoning_classification_id`),
  ADD KEY `idx_parcel_id` (`parcel_id`),
  ADD KEY `idx_lot_number` (`lot_number`),
  ADD KEY `idx_barangay` (`barangay`);

--
-- Indexes for table `permitted_uses`
--
ALTER TABLE `permitted_uses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_zone_project` (`zone_code`,`project_type`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `generated_by` (`generated_by`),
  ADD KEY `idx_report_type` (`report_type`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `road_inspection_requests`
--
ALTER TABLE `road_inspection_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_application_id` (`application_id`),
  ADD KEY `requested_by` (`requested_by`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_role_permission` (`role`,`permission`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_setting_key` (`setting_key`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_email` (`email`);

--
-- Indexes for table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_pref` (`user_id`,`pref_key`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `violations`
--
ALTER TABLE `violations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inspection_id` (`inspection_id`),
  ADD KEY `idx_app_violation` (`application_id`);

--
-- Indexes for table `zoning_classifications`
--
ALTER TABLE `zoning_classifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `zoning_compliance_checks`
--
ALTER TABLE `zoning_compliance_checks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `application_id_2` (`application_id`),
  ADD KEY `application_id` (`application_id`),
  ADD KEY `zoning_classification_id` (`zoning_type`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `application_documents`
--
ALTER TABLE `application_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `application_status_history`
--
ALTER TABLE `application_status_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=165;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=718;

--
-- AUTO_INCREMENT for table `gis_layers`
--
ALTER TABLE `gis_layers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `impact_assessments`
--
ALTER TABLE `impact_assessments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `inspections`
--
ALTER TABLE `inspections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=120;

--
-- AUTO_INCREMENT for table `parcels`
--
ALTER TABLE `parcels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `permitted_uses`
--
ALTER TABLE `permitted_uses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=153;

--
-- AUTO_INCREMENT for table `road_inspection_requests`
--
ALTER TABLE `road_inspection_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=222;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `user_preferences`
--
ALTER TABLE `user_preferences`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `violations`
--
ALTER TABLE `violations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `zoning_classifications`
--
ALTER TABLE `zoning_classifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `zoning_compliance_checks`
--
ALTER TABLE `zoning_compliance_checks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=78;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `applications_ibfk_1` FOREIGN KEY (`applicant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `applications_ibfk_2` FOREIGN KEY (`assigned_officer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `application_documents`
--
ALTER TABLE `application_documents`
  ADD CONSTRAINT `application_documents_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `application_documents_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `application_status_history`
--
ALTER TABLE `application_status_history`
  ADD CONSTRAINT `application_status_history_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `application_status_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `impact_assessments`
--
ALTER TABLE `impact_assessments`
  ADD CONSTRAINT `impact_assessments_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `impact_assessments_ibfk_2` FOREIGN KEY (`assessed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inspections`
--
ALTER TABLE `inspections`
  ADD CONSTRAINT `inspections_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inspections_ibfk_2` FOREIGN KEY (`inspector_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `parcels`
--
ALTER TABLE `parcels`
  ADD CONSTRAINT `parcels_ibfk_1` FOREIGN KEY (`zoning_classification_id`) REFERENCES `zoning_classifications` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `permitted_uses`
--
ALTER TABLE `permitted_uses`
  ADD CONSTRAINT `permitted_uses_ibfk_1` FOREIGN KEY (`zone_code`) REFERENCES `zoning_classifications` (`code`);

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `road_inspection_requests`
--
ALTER TABLE `road_inspection_requests`
  ADD CONSTRAINT `road_inspection_requests_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `road_inspection_requests_ibfk_2` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `violations`
--
ALTER TABLE `violations`
  ADD CONSTRAINT `violations_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `violations_ibfk_2` FOREIGN KEY (`inspection_id`) REFERENCES `inspections` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
