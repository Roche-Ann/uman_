-- Adds the outbound tracking table for the UPAD <-> UMAN (Energy/Utilities)
-- integration. Mirrors road_inspection_requests (Roads/IPMS integration).
--
-- Used by:
--   lgu-urban-planning/uman-integration/UtilitiesIntegrationService.php (insert/update)
--   lgu-urban-planning/uman-integration/uman_inspection_result.php (update on webhook)
--
-- Safe to import on a database that already has it — UtilitiesIntegrationService.php
-- also creates this table on first use if it's missing, so this file just lets
-- you provision it up front (e.g. via phpMyAdmin's Import tab).

CREATE TABLE IF NOT EXISTS `energy_inspection_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `status` enum('pending','sent','failed','completed') NOT NULL DEFAULT 'pending',
  `request_payload` text DEFAULT NULL,
  `requested_by` int(11) DEFAULT NULL,
  `requested_at` datetime DEFAULT NULL,
  `external_ref_id` varchar(64) DEFAULT NULL,
  `response_payload` text DEFAULT NULL,
  `responded_at` datetime DEFAULT NULL,
  `overall_condition` enum('Excellent','Good','Fair','Poor','Critical') DEFAULT NULL,
  `severity` enum('Low','Medium','High') DEFAULT NULL,
  `recommendation` varchar(255) DEFAULT NULL,
  `engineer_assigned` varchar(150) DEFAULT NULL,
  `inspection_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_application_id` (`application_id`),
  KEY `requested_by` (`requested_by`),
  CONSTRAINT `energy_inspection_requests_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `energy_inspection_requests_ibfk_2` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
