-- Customer Support Management System (support-mgt)
-- Complete Marketplace Installation Schema & Default System Seeds
-- Version: 1.1.0

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- 1. Table structure for table `departments`
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
-- 2. Table structure for table `users`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role` VARCHAR(50) NOT NULL DEFAULT 'customer',
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `avatar` VARCHAR(255) DEFAULT NULL,
  `department_id` BIGINT UNSIGNED DEFAULT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
  `last_login_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_role_index` (`role`),
  KEY `users_status_index` (`status`),
  KEY `users_department_id_index` (`department_id`),
  KEY `idx_users_deleted` (`deleted_at`),
  CONSTRAINT `fk_users_department_id` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3. Table structure for table `password_resets`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(191) NOT NULL,
  `token` VARCHAR(100) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME NOT NULL,
  `used` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `password_resets_token_unique` (`token`),
  KEY `password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 4. Table structure for table `roles`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `is_system` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_slug_unique` (`slug`),
  KEY `idx_roles_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 5. Table structure for table `user_roles`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_roles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `role_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_role` (`user_id`, `role_id`),
  KEY `idx_user_roles_user` (`user_id`),
  KEY `idx_user_roles_role` (`role_id`),
  CONSTRAINT `fk_user_roles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_user_roles_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 6. Table structure for table `permissions`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `module` VARCHAR(60) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_slug_unique` (`slug`),
  KEY `idx_permissions_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 7. Table structure for table `role_permissions`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` BIGINT UNSIGNED NOT NULL,
  `permission_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_role_permission` (`role_id`, `permission_id`),
  KEY `idx_role_permissions_role` (`role_id`),
  KEY `idx_role_permissions_perm` (`permission_id`),
  CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_role_permissions_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 8. Table structure for table `tickets`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tickets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_number` VARCHAR(30) NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `department_id` BIGINT UNSIGNED DEFAULT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `priority` ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium',
  `status` ENUM('open', 'in_progress', 'pending', 'resolved', 'closed') NOT NULL DEFAULT 'open',
  `assigned_to` BIGINT UNSIGNED DEFAULT NULL,
  `first_response_at` DATETIME DEFAULT NULL,
  `admin_viewed_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `resolved_at` DATETIME DEFAULT NULL,
  `closed_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tickets_ticket_number_unique` (`ticket_number`),
  KEY `tickets_user_id_index` (`user_id`),
  KEY `tickets_department_id_index` (`department_id`),
  KEY `tickets_assigned_to_index` (`assigned_to`),
  KEY `tickets_status_index` (`status`),
  KEY `tickets_priority_index` (`priority`),
  KEY `tickets_created_at_index` (`created_at`),
  KEY `idx_tkt_admin_viewed` (`admin_viewed_at`),
  CONSTRAINT `fk_tickets_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_tickets_department_id` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_tickets_assigned_to` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 9. Table structure for table `ticket_messages`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ticket_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `message` TEXT NOT NULL,
  `message_type` ENUM('reply', 'internal_note') NOT NULL DEFAULT 'reply',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ticket_messages_ticket_id_index` (`ticket_id`),
  KEY `ticket_messages_user_id_index` (`user_id`),
  KEY `ticket_messages_type_index` (`message_type`),
  KEY `ticket_messages_created_at_index` (`created_at`),
  CONSTRAINT `fk_ticket_messages_ticket_id` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_ticket_messages_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 10. Table structure for table `ticket_attachments`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ticket_attachments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` BIGINT UNSIGNED NOT NULL,
  `message_id` BIGINT UNSIGNED DEFAULT NULL,
  `uploaded_by` BIGINT UNSIGNED NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `stored_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `mime_type` VARCHAR(100) NOT NULL,
  `file_size` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ticket_attachments_ticket_id_index` (`ticket_id`),
  KEY `ticket_attachments_message_id_index` (`message_id`),
  KEY `ticket_attachments_uploaded_by_index` (`uploaded_by`),
  CONSTRAINT `fk_ticket_attachments_ticket_id` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_ticket_attachments_message_id` FOREIGN KEY (`message_id`) REFERENCES `ticket_messages` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ticket_attachments_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 11. Table structure for table `ticket_tags`
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
-- 12. Table structure for table `ticket_tag_relations`
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
-- 13. Table structure for table `canned_responses`
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
-- 14. Table structure for table `ticket_activity_logs`
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
-- 15. Table structure for table `notifications`
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
-- 16. Table structure for table `user_notification_preferences`
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
-- 17. Table structure for table `activity_logs`
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
-- 18. Table structure for table `settings`
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
-- 19. Table structure for table `knowledge_base_categories`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `knowledge_base_categories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(120) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `icon` VARCHAR(50) NOT NULL DEFAULT 'bi-folder',
  `sort_order` INT NOT NULL DEFAULT 0,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_kb_cat_slug` (`slug`),
  KEY `idx_kb_cat_status` (`status`),
  CONSTRAINT `fk_kb_cat_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 20. Table structure for table `knowledge_base_articles`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `knowledge_base_articles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` BIGINT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `excerpt` TEXT DEFAULT NULL,
  `content` MEDIUMTEXT NOT NULL,
  `featured_image` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('draft', 'published') NOT NULL DEFAULT 'published',
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `view_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `published_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_kb_art_slug` (`slug`),
  KEY `idx_kb_art_cat` (`category_id`),
  KEY `idx_kb_art_status` (`status`),
  KEY `idx_kb_art_featured` (`is_featured`),
  CONSTRAINT `fk_kb_art_cat` FOREIGN KEY (`category_id`) REFERENCES `knowledge_base_categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_kb_art_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 21. Table structure for table `faqs`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `faqs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `question` VARCHAR(255) NOT NULL,
  `answer` TEXT NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_faq_status` (`status`),
  CONSTRAINT `fk_faq_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ========================================================
-- SYSTEM SEED DATA
-- ========================================================

-- 1. Default Roles
INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `status`, `is_system`, `created_at`, `updated_at`)
VALUES 
    (1, 'Administrator', 'administrator', 'Superuser with unrestricted access across all system modules, configurations, and administrative tools.', 'active', 1, NOW(), NOW()),
    (2, 'Support Manager', 'support_manager', 'Team lead with access to ticket operations, staff workload, department oversight, and analytical reports.', 'active', 1, NOW(), NOW()),
    (3, 'Support Agent', 'support_agent', 'Support representative responsible for handling, replying to, and resolving assigned customer tickets.', 'active', 1, NOW(), NOW()),
    (4, 'Customer', 'customer', 'End-user client who can submit, track, and manage their personal support inquiries.', 'active', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE 
    `name` = VALUES(`name`),
    `description` = VALUES(`description`),
    `is_system` = 1;

-- 2. System Permissions
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `description`, `created_at`)
VALUES
    -- Dashboard
    (1, 'View Dashboard', 'dashboard.view', 'Dashboard', 'Access personal dashboard and operational metrics', NOW()),

    -- Tickets
    (2, 'View Tickets', 'tickets.view', 'Tickets', 'Browse and view ticket lists and conversations', NOW()),
    (3, 'Create Tickets', 'tickets.create', 'Tickets', 'Submit new support inquiries', NOW()),
    (4, 'Edit Tickets', 'tickets.edit', 'Tickets', 'Modify ticket subjects and metadata', NOW()),
    (5, 'Delete Tickets', 'tickets.delete', 'Tickets', 'Delete support tickets permanently', NOW()),
    (6, 'Assign Tickets', 'tickets.assign', 'Tickets', 'Assign or transfer tickets to agents', NOW()),
    (7, 'Reply to Tickets', 'tickets.reply', 'Tickets', 'Post public replies and internal notes', NOW()),
    (8, 'Change Ticket Status', 'tickets.change_status', 'Tickets', 'Update ticket lifecycle status', NOW()),
    (9, 'Change Ticket Priority', 'tickets.change_priority', 'Tickets', 'Update ticket priority level', NOW()),

    -- Customers
    (10, 'View Customers', 'customers.view', 'Customers', 'View customer list and profiles', NOW()),
    (11, 'Create Customers', 'customers.create', 'Customers', 'Manually register new customer accounts', NOW()),
    (12, 'Edit Customers', 'customers.edit', 'Customers', 'Edit customer profile and status', NOW()),
    (13, 'Delete Customers', 'customers.delete', 'Customers', 'Delete customer accounts', NOW()),

    -- Agents
    (14, 'View Agents', 'agents.view', 'Agents', 'View support staff list and workloads', NOW()),
    (15, 'Create Agents', 'agents.create', 'Agents', 'Provision new support staff accounts', NOW()),
    (16, 'Edit Agents', 'agents.edit', 'Agents', 'Update agent details and departments', NOW()),
    (17, 'Delete Agents', 'agents.delete', 'Agents', 'Deactivate or remove agent accounts', NOW()),

    -- Departments
    (18, 'View Departments', 'departments.view', 'Departments', 'View support departments list', NOW()),
    (19, 'Create Departments', 'departments.create', 'Departments', 'Create new support departments', NOW()),
    (20, 'Edit Departments', 'departments.edit', 'Departments', 'Update department details and status', NOW()),
    (21, 'Delete Departments', 'departments.delete', 'Departments', 'Delete support departments', NOW()),

    -- Tags
    (22, 'View Tags', 'tags.view', 'Tags', 'View available ticket tags', NOW()),
    (23, 'Create Tags', 'tags.create', 'Tags', 'Create new custom ticket tags', NOW()),
    (24, 'Edit Tags', 'tags.edit', 'Tags', 'Edit ticket tag names and badge colors', NOW()),
    (25, 'Delete Tags', 'tags.delete', 'Tags', 'Delete ticket tags', NOW()),

    -- Canned Responses
    (26, 'View Canned Responses', 'canned_responses.view', 'Canned Responses', 'View pre-written response templates', NOW()),
    (27, 'Create Canned Responses', 'canned_responses.create', 'Canned Responses', 'Create response templates', NOW()),
    (28, 'Edit Canned Responses', 'canned_responses.edit', 'Canned Responses', 'Edit response templates', NOW()),
    (29, 'Delete Canned Responses', 'canned_responses.delete', 'Canned Responses', 'Delete response templates', NOW()),

    -- Knowledge Base
    (30, 'View Knowledge Base', 'knowledge_base.view', 'Knowledge Base', 'Access knowledge base articles and FAQs', NOW()),
    (31, 'Create Knowledge Base', 'knowledge_base.create', 'Knowledge Base', 'Create articles, categories, and FAQs', NOW()),
    (32, 'Edit Knowledge Base', 'knowledge_base.edit', 'Knowledge Base', 'Edit articles, categories, and FAQs', NOW()),
    (33, 'Delete Knowledge Base', 'knowledge_base.delete', 'Knowledge Base', 'Delete articles, categories, and FAQs', NOW()),
    (34, 'Publish Knowledge Base', 'knowledge_base.publish', 'Knowledge Base', 'Publish or unpublish articles and FAQs', NOW()),

    -- Notifications
    (35, 'View Notifications', 'notifications.view', 'Notifications', 'Access notifications inbox', NOW()),

    -- Activity Logs
    (36, 'View Activity Logs', 'activity_logs.view', 'Activity Logs', 'Access system audit and security logs', NOW()),

    -- Reports
    (37, 'View Reports', 'reports.view', 'Reports', 'Access analytics and performance reports', NOW()),
    (38, 'Export Reports', 'reports.export', 'Reports', 'Download CSV analytics and exports', NOW()),

    -- Settings
    (39, 'View Settings', 'settings.view', 'Settings', 'View system configuration settings', NOW()),
    (40, 'Edit Settings', 'settings.edit', 'Settings', 'Modify system configuration settings', NOW()),

    -- User Management
    (41, 'View Users', 'users.view', 'User Management', 'View all registered user accounts', NOW()),
    (42, 'Create Users', 'users.create', 'User Management', 'Create new user accounts', NOW()),
    (43, 'Edit Users', 'users.edit', 'User Management', 'Edit user accounts and change status', NOW()),
    (44, 'Delete Users', 'users.delete', 'User Management', 'Soft delete user accounts', NOW()),

    -- Role Management
    (45, 'View Roles', 'roles.view', 'Role Management', 'View system and custom roles', NOW()),
    (46, 'Create Roles', 'roles.create', 'Role Management', 'Create new custom roles', NOW()),
    (47, 'Edit Roles', 'roles.edit', 'Role Management', 'Edit role metadata and status', NOW()),
    (48, 'Delete Roles', 'roles.delete', 'Role Management', 'Delete custom roles', NOW()),

    -- Permission Management
    (49, 'View Permissions', 'permissions.view', 'Role Management', 'View module permissions', NOW()),
    (50, 'Assign Permissions', 'permissions.assign', 'Role Management', 'Assign permissions to roles', NOW()),

    -- Profile
    (51, 'View Profile', 'profile.view', 'Profile', 'View own profile information', NOW()),
    (52, 'Edit Profile', 'profile.edit', 'Profile', 'Update own profile and password', NOW())
ON DUPLICATE KEY UPDATE 
    `name` = VALUES(`name`),
    `module` = VALUES(`module`),
    `description` = VALUES(`description`);

-- 3. Role Permissions Associations
-- Administrator: All Permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`, `created_at`)
SELECT r.id, p.id, NOW()
FROM `roles` r
CROSS JOIN `permissions` p
WHERE r.slug = 'administrator';

-- Support Manager Permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`, `created_at`)
SELECT r.id, p.id, NOW()
FROM `roles` r
JOIN `permissions` p ON p.slug IN (
    'dashboard.view',
    'tickets.view', 'tickets.create', 'tickets.edit', 'tickets.assign', 'tickets.reply', 'tickets.change_status', 'tickets.change_priority',
    'customers.view', 'customers.create', 'customers.edit',
    'agents.view', 'agents.create', 'agents.edit',
    'departments.view', 'departments.create', 'departments.edit',
    'tags.view', 'tags.create', 'tags.edit',
    'canned_responses.view', 'canned_responses.create', 'canned_responses.edit',
    'knowledge_base.view', 'knowledge_base.create', 'knowledge_base.edit', 'knowledge_base.publish',
    'notifications.view',
    'activity_logs.view',
    'reports.view', 'reports.export',
    'profile.view', 'profile.edit'
)
WHERE r.slug = 'support_manager';

-- Support Agent Permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`, `created_at`)
SELECT r.id, p.id, NOW()
FROM `roles` r
JOIN `permissions` p ON p.slug IN (
    'dashboard.view',
    'tickets.view', 'tickets.reply', 'tickets.change_status',
    'canned_responses.view',
    'knowledge_base.view',
    'notifications.view',
    'profile.view', 'profile.edit'
)
WHERE r.slug = 'support_agent';

-- Customer Permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`, `created_at`)
SELECT r.id, p.id, NOW()
FROM `roles` r
JOIN `permissions` p ON p.slug IN (
    'dashboard.view',
    'tickets.view', 'tickets.create', 'tickets.reply',
    'knowledge_base.view',
    'notifications.view',
    'profile.view', 'profile.edit'
)
WHERE r.slug = 'customer';

-- 4. Default Departments
INSERT INTO `departments` (`id`, `name`, `description`, `status`, `created_at`, `updated_at`)
VALUES
(1, 'Technical Support', 'Hardware, software, server configuration, and technical troubleshooting inquiries.', 'active', NOW(), NOW()),
(2, 'Billing & Payment', 'Invoicing, subscriptions, refunds, payment processing, and billing disputes.', 'active', NOW(), NOW()),
(3, 'Sales & Account Inquiry', 'Pre-sales inquiries, enterprise plans, upgrades, and account onboarding.', 'active', NOW(), NOW()),
(4, 'General Support', 'General questions, feedback, service assistance, and other support requests.', 'active', NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 5. Default Ticket Tags
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

-- 6. Default Canned Responses
INSERT INTO `canned_responses` (`id`, `title`, `content`, `created_by`, `created_at`, `updated_at`)
VALUES
(1, 'Greeting & Acknowledgment', 'Hello,\n\nThank you for contacting our support team. We have received your request and our engineers are actively looking into it.\n\nWe will get back to you with an update shortly.\n\nBest regards,\nCustomer Support Team', NULL, NOW(), NOW()),
(2, 'Request for Additional Details', 'Hello,\n\nCould you please provide a few additional details to help us investigate further?\n1. Exact steps to reproduce the issue.\n2. Any error messages displayed on screen.\n3. A screenshot or error log if available.\n\nThank you for your cooperation.\n\nBest regards,\nSupport Team', NULL, NOW(), NOW()),
(3, 'Issue Resolved Confirmation', 'Hello,\n\nWe are pleased to inform you that the issue you reported has been resolved.\n\nPlease verify on your end and let us know if everything is working as expected. If you need any further assistance, feel free to reply to this ticket.\n\nBest regards,\nCustomer Support Team', NULL, NOW(), NOW()),
(4, 'Billing & Invoicing Clarification', 'Hello,\n\nThank you for reaching out regarding your account billing. We have reviewed your invoice records and updated your account statement accordingly.\n\nPlease check your billing portal to view the updated details.\n\nBest regards,\nBilling Support Department', NULL, NOW(), NOW())
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- 7. Default System Settings
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
('enable_email_notifications', '0', 'boolean', NOW()),
('knowledge_base_enabled', '1', 'boolean', NOW()),
('faq_enabled', '1', 'boolean', NOW())
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- 8. Default Knowledge Base Categories
INSERT INTO `knowledge_base_categories` (`id`, `name`, `slug`, `description`, `icon`, `sort_order`, `status`, `created_by`, `created_at`, `updated_at`)
VALUES
(1, 'Getting Started', 'getting-started', 'Essential onboarding guides, tutorials, and quick start references.', 'bi-rocket-takeoff', 1, 'active', NULL, NOW(), NOW()),
(2, 'Account & Security', 'account-security', 'Manage credentials, 2-factor verification, and profile privacy settings.', 'bi-shield-check', 2, 'active', NULL, NOW(), NOW()),
(3, 'Billing & Invoicing', 'billing-invoicing', 'Subscription plans, payment options, invoices, and refund policies.', 'bi-credit-card', 3, 'active', NULL, NOW(), NOW()),
(4, 'Troubleshooting', 'troubleshooting', 'Solutions to common system errors, connection bugs, and connectivity issues.', 'bi-wrench-adjustable', 4, 'active', NULL, NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 9. Default Knowledge Base Articles
INSERT INTO `knowledge_base_articles` (`id`, `category_id`, `title`, `slug`, `excerpt`, `content`, `status`, `is_featured`, `view_count`, `created_by`, `created_at`, `updated_at`, `published_at`)
VALUES
(1, 1, 'How to Submit and Track Support Tickets', 'how-to-submit-and-track-support-tickets', 'Learn how to create inquiries, attach diagnostic files, and check resolution updates.', 'Submitting a support ticket is the fastest way to get direct assistance from our customer success engineers.\n\n### Steps to Create a Ticket:\n1. Sign in to your customer account.\n2. Click on **Create Ticket** in the navigation sidebar or Support Center.\n3. Enter a clear and descriptive Subject summarizing your issue.\n4. Select the appropriate Department (e.g. Technical Support or Billing).\n5. Specify the Priority Level of your inquiry.\n6. Provide detailed steps to reproduce the issue in the message field.\n7. Optionally attach diagnostic logs, screenshots, or PDF invoices (up to 10MB).\n8. Click **Submit Ticket**.\n\nYou will receive automated in-app alerts and email notifications as our team responds to your inquiry.', 'published', 1, 45, NULL, NOW(), NOW(), NOW()),
(2, 2, 'How to Reset Your Account Password', 'how-to-reset-your-account-password', 'Step-by-step instructions for resetting forgotten passwords or updating security credentials.', 'If you have forgotten your password or wish to update your login credentials, follow these instructions:\n\n### If You Forgot Your Password:\n1. Open the Login page at `/auth/login.php`.\n2. Click on the **Forgot Password?** link beneath the password input.\n3. Enter your registered email address and submit.\n4. A secure password reset link will be generated. Follow the instructions to choose a new strong password.\n\n### If You Are Currently Logged In:\n1. Go to **My Profile** from the sidebar or top right user menu.\n2. Select **Change Password**.\n3. Enter your current password and your new password (minimum 8 characters).\n4. Click **Update Password**.\n\nRemember to keep your credentials confidential at all times.', 'published', 1, 78, NULL, NOW(), NOW(), NOW()),
(3, 3, 'Understanding Billing Cycles and Invoice History', 'understanding-billing-cycles-and-invoice-history', 'Overview of payment schedules, downloading invoice receipts, and currency support.', 'Our billing cycle operates on a recurring monthly or annual schedule depending on your selected tier.\n\n### Payment Methods Supported:\n- Major Credit & Debit Cards (Visa, MasterCard, American Express)\n- Corporate Bank Wire Transfer\n- Automated Clearing House (ACH)\n\n### Downloading Invoices:\nAll finalized invoices are generated on the 1st day of each billing cycle. If you require tax exemption documents or customized company invoice details, submit a ticket to the **Billing & Payment** department.', 'published', 0, 22, NULL, NOW(), NOW(), NOW()),
(4, 4, 'Resolving Attachment Upload Errors', 'resolving-attachment-upload-errors', 'Troubleshooting guide for file upload limits, blocked extensions, and connection timeouts.', 'If you encounter an error when attaching files to support tickets, please verify the following common causes:\n\n1. **File Size Limit**: The maximum allowed attachment size is **10MB** per file. Compress large log files into a `.zip` archive before uploading.\n2. **Allowed File Extensions**: Supported formats include `.jpg`, `.jpeg`, `.png`, `.webp`, `.pdf`, `.doc`, `.docx`, `.txt`, `.zip`, `.log`, `.csv`, `.xlsx`.\n3. **Prohibited Executables**: Script and binary files (`.php`, `.exe`, `.sh`, `.bat`, `.js`) are strictly blocked for security protection.\n4. **Network Interruption**: If your connection drops during upload, try refreshing and uploading individual files.', 'published', 1, 34, NULL, NOW(), NOW(), NOW())
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- 10. Default FAQs
INSERT INTO `faqs` (`id`, `question`, `answer`, `sort_order`, `status`, `created_by`, `created_at`, `updated_at`)
VALUES
(1, 'What are your standard support operating hours?', 'Our support team monitors urgent inquiries 24/7. Standard business support inquiries are addressed Monday through Friday, 8:00 AM – 6:00 PM EST.', 1, 'active', NULL, NOW(), NOW()),
(2, 'How long does it usually take to receive a response?', 'First response times for urgent inquiries are typically under 1 hour. Standard technical inquiries are responded to within 4 business hours.', 2, 'active', NULL, NOW(), NOW()),
(3, 'Can I reopen a ticket after it has been marked resolved?', 'Yes! If you reply to any ticket that was marked resolved or closed, the system will automatically reopen the ticket and notify your assigned support agent.', 3, 'active', NULL, NOW(), NOW()),
(4, 'Are my uploaded attachments and internal notes secure?', 'All ticket attachments are stored in protected directories with randomized server names and access is strictly authenticated. Internal staff notes are private and never exposed to customers.', 4, 'active', NULL, NOW(), NOW())
ON DUPLICATE KEY UPDATE `question` = VALUES(`question`);

SET FOREIGN_KEY_CHECKS = 1;
