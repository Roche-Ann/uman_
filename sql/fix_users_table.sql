-- =============================================================================
-- UMAN: Fix / create `users` (+ `otps`) so Create Account works
-- Run this in phpMyAdmin → SQL tab (select database first)
-- Safe to re-run: uses checks / IF NOT EXISTS where possible
-- =============================================================================

-- 1) Create `users` if it does not exist (complete, working definition)
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `user_type` enum('citizen','employee') NOT NULL DEFAULT 'citizen',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `login_attempts` int(11) NOT NULL DEFAULT 0,
  `blocked_until` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Repair an EXISTING broken `users` table from uman_utility_system.sql
--    (that dump created `users` WITHOUT primary key / AUTO_INCREMENT)
--    If a statement errors because it already exists, skip to the next one.

-- Add primary key if missing
ALTER TABLE `users` ADD PRIMARY KEY (`id`);

-- Enable auto-increment so INSERT works without supplying id
ALTER TABLE `users` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- Unique email (required for registration duplicate checks)
ALTER TABLE `users` ADD UNIQUE KEY `email` (`email`);

-- Make sure defaults exist for registration inserts
ALTER TABLE `users`
  MODIFY `user_type` enum('citizen','employee') NOT NULL DEFAULT 'citizen',
  MODIFY `login_attempts` int(11) NOT NULL DEFAULT 0,
  MODIFY `is_active` tinyint(1) NOT NULL DEFAULT 1,
  MODIFY `created_at` timestamp NOT NULL DEFAULT current_timestamp();

-- 3) OTP table used by login verification
CREATE TABLE IF NOT EXISTS `otps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_otps_user` (`user_id`),
  KEY `idx_otps_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Ensure otps.id auto-increments (dump had this, but make sure)
ALTER TABLE `otps` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- 4) Optional seed admin (password = "password") — skip if email exists
INSERT INTO `users` (`email`, `password`, `full_name`, `user_type`, `is_active`, `login_attempts`)
SELECT 'roche.mapait@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'employee', 1, 0
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `email` = 'roche.mapait@gmail.com');
