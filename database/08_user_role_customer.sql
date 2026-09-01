-- Customer Support Management System (support-mgt)
-- Phase 08: User Management + Role Management + Permissions + Customer Registration

USE `support_mgt_db`;

-- --------------------------------------------------------
-- 1. Roles Table
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
    `id` BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `is_system` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_roles_slug` (`slug`),
    INDEX `idx_roles_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 2. User Roles Pivot Table
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_roles` (
    `id` BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT(20) UNSIGNED NOT NULL,
    `role_id` BIGINT(20) UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_user_role` (`user_id`, `role_id`),
    INDEX `idx_user_roles_user` (`user_id`),
    INDEX `idx_user_roles_role` (`role_id`),
    CONSTRAINT `fk_user_roles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_user_roles_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3. Permissions Table
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `permissions` (
    `id` BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `module` VARCHAR(60) NOT NULL,
    `description` VARCHAR(255) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_permissions_slug` (`slug`),
    INDEX `idx_permissions_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 4. Role Permissions Pivot Table
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `role_permissions` (
    `id` BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `role_id` BIGINT(20) UNSIGNED NOT NULL,
    `permission_id` BIGINT(20) UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_role_permission` (`role_id`, `permission_id`),
    INDEX `idx_role_permissions_role` (`role_id`),
    INDEX `idx_role_permissions_perm` (`permission_id`),
    CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_role_permissions_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 5. Safe Users Table Alterations
-- --------------------------------------------------------

-- Expand role column to VARCHAR to accommodate custom and normalized role slugs
ALTER TABLE `users` MODIFY COLUMN `role` VARCHAR(50) NOT NULL DEFAULT 'customer';

-- Add deleted_at for soft delete support if not exists
SET @exist := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'support_mgt_db' AND table_name = 'users' AND column_name = 'deleted_at');
SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE `users` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL AFTER `updated_at`, ADD INDEX `idx_users_deleted` (`deleted_at`)', 'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- --------------------------------------------------------
-- 6. Seed Default System Roles
-- --------------------------------------------------------
INSERT INTO `roles` (`name`, `slug`, `description`, `status`, `is_system`, `created_at`, `updated_at`)
VALUES 
    ('Administrator', 'administrator', 'Superuser with unrestricted access across all system modules, configurations, and administrative tools.', 'active', 1, NOW(), NOW()),
    ('Support Manager', 'support_manager', 'Team lead with access to ticket operations, staff workload, department oversight, and analytical reports.', 'active', 1, NOW(), NOW()),
    ('Support Agent', 'support_agent', 'Support representative responsible for handling, replying to, and resolving assigned customer tickets.', 'active', 1, NOW(), NOW()),
    ('Customer', 'customer', 'End-user client who can submit, track, and manage their personal support inquiries.', 'active', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE 
    `name` = VALUES(`name`),
    `description` = VALUES(`description`),
    `is_system` = 1;

-- --------------------------------------------------------
-- 7. Seed System Permissions
-- --------------------------------------------------------
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`)
VALUES
    -- Dashboard
    ('View Dashboard', 'dashboard.view', 'Dashboard', 'Access personal dashboard and operational metrics', NOW()),

    -- Tickets
    ('View Tickets', 'tickets.view', 'Tickets', 'Browse and view ticket lists and conversations', NOW()),
    ('Create Tickets', 'tickets.create', 'Tickets', 'Submit new support inquiries', NOW()),
    ('Edit Tickets', 'tickets.edit', 'Tickets', 'Modify ticket subjects and metadata', NOW()),
    ('Delete Tickets', 'tickets.delete', 'Tickets', 'Delete support tickets permanently', NOW()),
    ('Assign Tickets', 'tickets.assign', 'Tickets', 'Assign or transfer tickets to agents', NOW()),
    ('Reply to Tickets', 'tickets.reply', 'Tickets', 'Post public replies and internal notes', NOW()),
    ('Change Ticket Status', 'tickets.change_status', 'Tickets', 'Update ticket lifecycle status', NOW()),
    ('Change Ticket Priority', 'tickets.change_priority', 'Tickets', 'Update ticket priority level', NOW()),

    -- Customers
    ('View Customers', 'customers.view', 'Customers', 'View customer list and profiles', NOW()),
    ('Create Customers', 'customers.create', 'Customers', 'Manually register new customer accounts', NOW()),
    ('Edit Customers', 'customers.edit', 'Customers', 'Edit customer profile and status', NOW()),
    ('Delete Customers', 'customers.delete', 'Customers', 'Delete customer accounts', NOW()),

    -- Agents
    ('View Agents', 'agents.view', 'Agents', 'View support staff list and workloads', NOW()),
    ('Create Agents', 'agents.create', 'Agents', 'Provision new support staff accounts', NOW()),
    ('Edit Agents', 'agents.edit', 'Agents', 'Update agent details and departments', NOW()),
    ('Delete Agents', 'agents.delete', 'Agents', 'Deactivate or remove agent accounts', NOW()),

    -- Departments
    ('View Departments', 'departments.view', 'Departments', 'View support departments list', NOW()),
    ('Create Departments', 'departments.create', 'Departments', 'Create new support departments', NOW()),
    ('Edit Departments', 'departments.edit', 'Departments', 'Update department details and status', NOW()),
    ('Delete Departments', 'departments.delete', 'Departments', 'Delete support departments', NOW()),

    -- Tags
    ('View Tags', 'tags.view', 'Tags', 'View available ticket tags', NOW()),
    ('Create Tags', 'tags.create', 'Tags', 'Create new custom ticket tags', NOW()),
    ('Edit Tags', 'tags.edit', 'Tags', 'Edit ticket tag names and badge colors', NOW()),
    ('Delete Tags', 'tags.delete', 'Tags', 'Delete ticket tags', NOW()),

    -- Canned Responses
    ('View Canned Responses', 'canned_responses.view', 'Canned Responses', 'View pre-written response templates', NOW()),
    ('Create Canned Responses', 'canned_responses.create', 'Canned Responses', 'Create response templates', NOW()),
    ('Edit Canned Responses', 'canned_responses.edit', 'Canned Responses', 'Edit response templates', NOW()),
    ('Delete Canned Responses', 'canned_responses.delete', 'Canned Responses', 'Delete response templates', NOW()),

    -- Knowledge Base
    ('View Knowledge Base', 'knowledge_base.view', 'Knowledge Base', 'Access knowledge base articles and FAQs', NOW()),
    ('Create Knowledge Base', 'knowledge_base.create', 'Knowledge Base', 'Create articles, categories, and FAQs', NOW()),
    ('Edit Knowledge Base', 'knowledge_base.edit', 'Knowledge Base', 'Edit articles, categories, and FAQs', NOW()),
    ('Delete Knowledge Base', 'knowledge_base.delete', 'Knowledge Base', 'Delete articles, categories, and FAQs', NOW()),
    ('Publish Knowledge Base', 'knowledge_base.publish', 'Knowledge Base', 'Publish or unpublish articles and FAQs', NOW()),

    -- Notifications
    ('View Notifications', 'notifications.view', 'Notifications', 'Access notifications inbox', NOW()),

    -- Activity Logs
    ('View Activity Logs', 'activity_logs.view', 'Activity Logs', 'Access system audit and security logs', NOW()),

    -- Reports
    ('View Reports', 'reports.view', 'Reports', 'Access analytics and performance reports', NOW()),
    ('Export Reports', 'reports.export', 'Reports', 'Download CSV analytics and exports', NOW()),

    -- Settings
    ('View Settings', 'settings.view', 'Settings', 'View system configuration settings', NOW()),
    ('Edit Settings', 'settings.edit', 'Settings', 'Modify system configuration settings', NOW()),

    -- User Management
    ('View Users', 'users.view', 'User Management', 'View all registered user accounts', NOW()),
    ('Create Users', 'users.create', 'User Management', 'Create new user accounts', NOW()),
    ('Edit Users', 'users.edit', 'User Management', 'Edit user accounts and change status', NOW()),
    ('Delete Users', 'users.delete', 'User Management', 'Soft delete user accounts', NOW()),

    -- Role Management
    ('View Roles', 'roles.view', 'Role Management', 'View system and custom roles', NOW()),
    ('Create Roles', 'roles.create', 'Role Management', 'Create new custom roles', NOW()),
    ('Edit Roles', 'roles.edit', 'Role Management', 'Edit role metadata and status', NOW()),
    ('Delete Roles', 'roles.delete', 'Role Management', 'Delete custom roles', NOW()),

    -- Permission Management
    ('View Permissions', 'permissions.view', 'Role Management', 'View module permissions', NOW()),
    ('Assign Permissions', 'permissions.assign', 'Role Management', 'Assign permissions to roles', NOW()),

    -- Profile
    ('View Profile', 'profile.view', 'Profile', 'View own profile information', NOW()),
    ('Edit Profile', 'profile.edit', 'Profile', 'Update own profile and password', NOW())
ON DUPLICATE KEY UPDATE 
    `name` = VALUES(`name`),
    `module` = VALUES(`module`),
    `description` = VALUES(`description`);

-- --------------------------------------------------------
-- 8. Seed Role-Permission Associations
-- --------------------------------------------------------

-- Administrator: Assign ALL permissions
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

-- --------------------------------------------------------
-- 9. Backfill user_roles for existing users
-- --------------------------------------------------------

-- Admin users
INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`, `created_at`)
SELECT u.id, r.id, NOW()
FROM `users` u
JOIN `roles` r ON r.slug = 'administrator'
WHERE u.role IN ('admin', 'administrator');

-- Agent users
INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`, `created_at`)
SELECT u.id, r.id, NOW()
FROM `users` u
JOIN `roles` r ON r.slug = 'support_agent'
WHERE u.role IN ('agent', 'support_agent');

-- Manager users
INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`, `created_at`)
SELECT u.id, r.id, NOW()
FROM `users` u
JOIN `roles` r ON r.slug = 'support_manager'
WHERE u.role IN ('manager', 'support_manager');

-- Customer users
INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`, `created_at`)
SELECT u.id, r.id, NOW()
FROM `users` u
JOIN `roles` r ON r.slug = 'customer'
WHERE u.role = 'customer';
