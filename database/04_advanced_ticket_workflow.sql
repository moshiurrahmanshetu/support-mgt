-- Customer Support Management System (support-mgt)
-- Phase 04: Advanced Ticket Workflow, Tags, Canned Responses & Activity History Schema

USE `support_mgt_db`;

-- --------------------------------------------------------
-- Add `first_response_at` to `tickets` table
-- --------------------------------------------------------

SET @dbname = DATABASE();
SET @tablename = "tickets";
SET @columnname = "first_response_at";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 1",
  "ALTER TABLE tickets ADD COLUMN first_response_at DATETIME DEFAULT NULL AFTER assigned_to;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- --------------------------------------------------------
-- Table structure for table `ticket_tags`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `ticket_tags` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `color` VARCHAR(10) NOT NULL DEFAULT '#6c757d',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ticket_tags_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for pivot table `ticket_tag_relations`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `ticket_tag_relations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` BIGINT UNSIGNED NOT NULL,
  `tag_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ticket_tag` (`ticket_id`, `tag_id`),
  KEY `idx_ttr_tag_id` (`tag_id`),
  CONSTRAINT `fk_ttr_ticket_id` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ttr_tag_id` FOREIGN KEY (`tag_id`) REFERENCES `ticket_tags` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `canned_responses`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `canned_responses` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(150) NOT NULL,
  `content` TEXT NOT NULL,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_canned_created_by` (`created_by`),
  CONSTRAINT `fk_canned_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `ticket_activity_logs`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `ticket_activity_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(50) NOT NULL,
  `old_value` VARCHAR(255) DEFAULT NULL,
  `new_value` VARCHAR(255) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tal_ticket_id` (`ticket_id`),
  KEY `idx_tal_user_id` (`user_id`),
  CONSTRAINT `fk_tal_ticket_id` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tal_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Seed Default Ticket Tags
-- --------------------------------------------------------

INSERT INTO `ticket_tags` (`id`, `name`, `color`, `created_at`, `updated_at`)
VALUES
(1, 'Technical', '#0d6efd', NOW(), NOW()),
(2, 'Billing', '#198754', NOW(), NOW()),
(3, 'Payment', '#0dcaf0', NOW(), NOW()),
(4, 'Login & Security', '#6f42c1', NOW(), NOW()),
(5, 'Account', '#6c757d', NOW(), NOW()),
(6, 'Bug Report', '#dc3545', NOW(), NOW()),
(7, 'Feature Request', '#fd7e14', NOW(), NOW()),
(8, 'Urgent Assistance', '#d63384', NOW(), NOW())
ON DUPLICATE KEY UPDATE `color` = VALUES(`color`);

-- --------------------------------------------------------
-- Seed Sample Canned Responses
-- --------------------------------------------------------

INSERT INTO `canned_responses` (`id`, `title`, `content`, `created_by`, `created_at`, `updated_at`)
VALUES
(1, 'Greeting & Acknowledgment', 'Hello,\n\nThank you for contacting our support team. We have received your request and our engineers are actively looking into it.\n\nWe will get back to you with an update shortly.\n\nBest regards,\nCustomer Support Team', 1, NOW(), NOW()),
(2, 'Request for Additional Details', 'Hello,\n\nCould you please provide a few additional details to help us investigate further?\n1. Exact steps to reproduce the issue.\n2. Any error messages displayed on screen.\n3. A screenshot or error log if available.\n\nThank you for your cooperation.\n\nBest regards,\nSupport Team', 1, NOW(), NOW()),
(3, 'Issue Resolved Confirmation', 'Hello,\n\nWe are pleased to inform you that the issue you reported has been resolved.\n\nPlease verify on your end and let us know if everything is working as expected. If you need any further assistance, feel free to reply to this ticket.\n\nBest regards,\nCustomer Support Team', 1, NOW(), NOW()),
(4, 'Billing & Invoicing Clarification', 'Hello,\n\nThank you for reaching out regarding your account billing. We have reviewed your invoice records and updated your account statement accordingly.\n\nPlease check your billing portal to view the updated details.\n\nBest regards,\nBilling Support Department', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);
