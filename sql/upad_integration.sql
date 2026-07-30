-- ============================================================
-- Urban Planning (UPAD) ↔ UMAN Integration Tables
-- Run once to set up inbound inspection request tracking.
-- ============================================================

CREATE TABLE IF NOT EXISTS `upad_inspection_requests` (
  `id`                     INT AUTO_INCREMENT PRIMARY KEY,
  `reference_id`           VARCHAR(30)  NOT NULL UNIQUE COMMENT 'Our generated ref e.g. EG-2026-001',
  `application_id`         INT          NOT NULL        COMMENT 'UPAD correlation ID — echoed back in callback',
  `source_system`          VARCHAR(20)  NOT NULL DEFAULT 'UPAD',
  `project_name`           VARCHAR(255) NULL,
  `barangay`               VARCHAR(100) NULL,
  `district`               VARCHAR(50)  NULL,
  `category`               VARCHAR(80)  NULL            COMMENT 'Residential / Commercial / Industrial',
  `estimated_load_kva`     DECIMAL(10,2) NULL,
  `priority`               ENUM('Urgent','Medium','Low') NOT NULL DEFAULT 'Medium',
  `address`                TEXT         NULL,
  `latitude`               DECIMAL(10,7) NULL,
  `longitude`              DECIMAL(10,7) NULL,
  `description`            TEXT         NULL,
  `requested_by`           VARCHAR(150) NULL,
  `callback_url`           TEXT         NULL            COMMENT 'Where to POST the result back',
  `status`                 ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `raw_payload`            JSON         NULL            COMMENT 'Full inbound request payload',
  `result_payload`         JSON         NULL            COMMENT 'Full outbound callback payload',
  `callback_sent_at`       DATETIME     NULL,
  `callback_http_code`     SMALLINT     NULL,
  `callback_error`         TEXT         NULL,
  `created_at`             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX `idx_application_id` (`application_id`),
  INDEX `idx_status`         (`status`),
  INDEX `idx_created_at`     (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Inbound inspection requests from the Urban Planning (UPAD) system';
