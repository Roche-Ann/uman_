-- -----------------------------------------------------------------------------
-- UMAN (Utilities Asset Management) — CPRF Property Custodian Lifecycle Phase 3
-- Manual migration for clean deploys / COA audits.
--
-- NOTE: EVERY ALTER TABLE in this file is a SUBSET of the idempotent auto-migrate
-- logic that fires inside `uman_ensure_cprf_custody_schema()` in
-- /api/integration_config.php on first load of /api/facility-equipment.php.
-- You do NOT need to run this file manually — the PHP helper wraps identical
-- DDL in try/catch so legacy databases are upgraded on first API call.
-- Running it explicitly is recommended for:
--   (a) brand-new UMAN deployments (faster first API call, no per-request ALTERs)
--   (b) before COA/DILG spot-audits so all columns exist and are discoverable
--       via `SHOW COLUMNS` queries in database-diff audit tools.
--
-- Engine:          MySQL 8.0+ InnoDB, utf8mb4_unicode_ci
-- Run order:       1. sql/utility_assets.sql (base schema)   ALREADY EXISTS
--                  2. THIS FILE — migration_cprf_property_lifecycle_phase3.sql
-- Target user:     UMAN database owner (must have ALTER, INDEX privs)
-- -----------------------------------------------------------------------------

-- -----------------------------------------------------------------------------
-- 1.  CPRF custody tracking columns on utility_assets.
--     Tracks whether an asset is warehoused, on-loan at a CPRF facility,
--     pending return pickup, returned to warehouse, or condemned (decommissioned).
-- -----------------------------------------------------------------------------
ALTER TABLE utility_assets
    ADD COLUMN IF NOT EXISTS `cprf_facility_id` INT NULL
        COMMENT 'CPRF facilities.id when asset is on-loan at a Barangay Culiat facility'
        AFTER `responsible_office`,

    ADD COLUMN IF NOT EXISTS `cprf_custody_status`
        ENUM('WAREHOUSED','ON_LOAN_AT_FACILITY','LOAN_RETURN_PENDING','LOAN_RETURNED','CONDEMNED')
        NOT NULL DEFAULT 'WAREHOUSED'
        COMMENT 'CPRF chain-of-custody state for COA Circular 2023-004 §3–§6'
        AFTER `cprf_facility_id`,

    ADD INDEX IF NOT EXISTS idx_ua_cprf_facility (cprf_facility_id),
    ADD INDEX IF NOT EXISTS idx_ua_cprf_custody (cprf_custody_status);

-- For databases where cprf_custody_status already EXISTS with a narrower ENUM
-- (missing CONDEMNED), widen it explicitly:
ALTER TABLE utility_assets
    MODIFY COLUMN `cprf_custody_status`
        ENUM('WAREHOUSED','ON_LOAN_AT_FACILITY','LOAN_RETURN_PENDING','LOAN_RETURNED','CONDEMNED')
        NOT NULL DEFAULT 'WAREHOUSED';

-- -----------------------------------------------------------------------------
-- 2.  Verify / backfill safe defaults for existing rows.
--     Assets with no CPRF facility link should remain WAREHOUSED.
-- -----------------------------------------------------------------------------
UPDATE utility_assets
   SET cprf_custody_status = 'WAREHOUSED'
 WHERE cprf_facility_id IS NULL
   AND cprf_custody_status NOT IN ('WAREHOUSED','LOAN_RETURNED','CONDEMNED');

UPDATE utility_assets
   SET cprf_custody_status = 'ON_LOAN_AT_FACILITY'
 WHERE cprf_facility_id IS NOT NULL
   AND cprf_custody_status = 'WAREHOUSED';
