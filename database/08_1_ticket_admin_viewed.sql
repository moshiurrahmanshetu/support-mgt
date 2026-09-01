-- Customer Support Management System (support-mgt)
-- Phase 08.1: New Support Ticket Sidebar Counter & Admin Viewed Tracking

USE `support_mgt_db`;

-- 1. Add admin_viewed_at to tickets table if not exists
SET @exist := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'support_mgt_db' AND table_name = 'tickets' AND column_name = 'admin_viewed_at');
SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE `tickets` ADD COLUMN `admin_viewed_at` DATETIME NULL AFTER `first_response_at`, ADD INDEX `idx_tkt_admin_viewed` (`admin_viewed_at`)', 'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Backfill existing tickets as already seen so historical tickets do not falsely inflate the counter
UPDATE `tickets` SET `admin_viewed_at` = `created_at` WHERE `admin_viewed_at` IS NULL;
