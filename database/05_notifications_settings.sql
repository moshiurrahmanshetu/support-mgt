-- Customer Support Management System (support-mgt)
-- Phase 05: Notifications, Email Infrastructure, System Activity Logs & Settings Schema

USE `support_mgt_db`;

-- --------------------------------------------------------
-- Table structure for table `notifications`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `reference_type` VARCHAR(50) DEFAULT NULL,
  `reference_id` BIGINT UNSIGNED DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user_read` (`user_id`, `is_read`),
  CONSTRAINT `fk_notif_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `user_notification_preferences`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `user_notification_preferences` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `email_ticket_created` TINYINT(1) NOT NULL DEFAULT 1,
  `email_ticket_assigned` TINYINT(1) NOT NULL DEFAULT 1,
  `email_ticket_reply` TINYINT(1) NOT NULL DEFAULT 1,
  `email_ticket_status` TINYINT(1) NOT NULL DEFAULT 1,
  `email_ticket_reopened` TINYINT(1) NOT NULL DEFAULT 1,
  `in_app_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_prefs` (`user_id`),
  CONSTRAINT `fk_unp_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `activity_logs` (System Activity)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED DEFAULT NULL,
  `module` VARCHAR(50) NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `description` TEXT NOT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `reference_type` VARCHAR(50) DEFAULT NULL,
  `reference_id` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_act_user` (`user_id`),
  KEY `idx_act_module` (`module`),
  KEY `idx_act_created_at` (`created_at`),
  CONSTRAINT `fk_act_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `settings`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT DEFAULT NULL,
  `setting_type` VARCHAR(30) NOT NULL DEFAULT 'string',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Seed Default System Settings
-- --------------------------------------------------------

INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_type`, `updated_at`)
VALUES
('app_name', 'Support Management System', 'string', NOW()),
('app_url', 'http://localhost/support-mgt', 'string', NOW()),
('company_name', 'SupportMgt Corp', 'string', NOW()),
('company_email', 'support@supportmgt.local', 'string', NOW()),
('company_phone', '+1 (555) 123-4567', 'string', NOW()),
('timezone', 'UTC', 'string', NOW()),
('date_format', 'M d, Y h:i A', 'string', NOW()),
('default_priority', 'medium', 'string', NOW()),
('default_status', 'open', 'string', NOW()),
('allow_customer_attachments', '1', 'boolean', NOW()),
('max_attachment_size_mb', '10', 'integer', NOW()),
('mail_enabled', '0', 'boolean', NOW()),
('smtp_host', 'localhost', 'string', NOW()),
('smtp_port', '587', 'integer', NOW()),
('smtp_user', '', 'string', NOW()),
('smtp_pass', '', 'string', NOW()),
('smtp_encryption', 'tls', 'string', NOW()),
('mail_from_name', 'Support Desk', 'string', NOW()),
('mail_from_email', 'no-reply@supportmgt.local', 'string', NOW()),
('enable_in_app_notifications', '1', 'boolean', NOW()),
('enable_email_notifications', '0', 'boolean', NOW())
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);
