-- Customer Support Management System (support-mgt)
-- Phase 07: Reports & Analytics Database Documentation & Performance Indexes

USE `support_mgt_db`;

-- --------------------------------------------------------
-- Note on Phase 07 Reporting Architecture:
-- 
-- All reports and analytics in Phase 07 are dynamically calculated
-- in real-time from existing application tables:
--   - `tickets`
--   - `users`
--   - `departments`
--   - `ticket_messages`
--   - `ticket_activity_logs`
--   - `ticket_tags`
-- 
-- No separate static analytics or summary reporting tables are required,
-- guaranteeing 100% real-time data accuracy with zero data duplication.
-- --------------------------------------------------------

-- --------------------------------------------------------
-- Performance Index Optimization for Report Queries
-- --------------------------------------------------------

-- Add indexes on tickets created_at, first_response_at, and resolved_at for fast date-range aggregation if not present
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = 'support_mgt_db' AND table_name = 'tickets' AND index_name = 'idx_tkt_created_at');
SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE `tickets` ADD INDEX `idx_tkt_created_at` (`created_at`)', 'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = 'support_mgt_db' AND table_name = 'tickets' AND index_name = 'idx_tkt_first_response');
SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE `tickets` ADD INDEX `idx_tkt_first_response` (`first_response_at`)', 'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = 'support_mgt_db' AND table_name = 'tickets' AND index_name = 'idx_tkt_resolved_at');
SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE `tickets` ADD INDEX `idx_tkt_resolved_at` (`resolved_at`)', 'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
