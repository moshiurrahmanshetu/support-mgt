-- Customer Support Management System (support-mgt)
-- Phase 03: Customer Management, Agent Management & Department Management Schema

USE `support_mgt_db`;

-- --------------------------------------------------------
-- Table structure for table `departments`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `departments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `departments_name_unique` (`name`),
  KEY `departments_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Add `department_id` to `users` table (for Agent Department Assignment)
-- --------------------------------------------------------

SET @dbname = DATABASE();
SET @tablename = "users";
SET @columnname = "department_id";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 1",
  "ALTER TABLE users ADD COLUMN department_id BIGINT UNSIGNED DEFAULT NULL AFTER avatar, ADD KEY users_department_id_index (department_id), ADD CONSTRAINT fk_users_department_id FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL ON UPDATE CASCADE;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- --------------------------------------------------------
-- Add `department_id` to `tickets` table (for Ticket Department Integration)
-- --------------------------------------------------------

SET @tablename = "tickets";
SET @columnname = "department_id";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 1",
  "ALTER TABLE tickets ADD COLUMN department_id BIGINT UNSIGNED DEFAULT NULL AFTER user_id, ADD KEY tickets_department_id_index (department_id), ADD CONSTRAINT fk_tickets_department_id FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL ON UPDATE CASCADE;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- --------------------------------------------------------
-- Seed Initial Support Departments
-- --------------------------------------------------------

INSERT INTO `departments` (`id`, `name`, `description`, `status`, `created_at`, `updated_at`)
VALUES
(1, 'Technical Support', 'Hardware, software, server configuration, and technical troubleshooting inquiries.', 'active', NOW(), NOW()),
(2, 'Billing & Payment', 'Invoicing, subscriptions, refunds, payment processing, and billing disputes.', 'active', NOW(), NOW()),
(3, 'Sales & Account Inquiry', 'Pre-sales inquiries, enterprise plans, upgrades, and account onboarding.', 'active', NOW(), NOW()),
(4, 'General Support', 'General questions, feedback, service assistance, and other support requests.', 'active', NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);
