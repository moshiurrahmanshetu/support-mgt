-- Customer Support Management System (support-mgt)
-- Phase 06: Knowledge Base, FAQs, Categories, and Articles Schema

USE `support_mgt_db`;

-- --------------------------------------------------------
-- Table structure for table `knowledge_base_categories`
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
-- Table structure for table `knowledge_base_articles`
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
-- Table structure for table `faqs`
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

-- --------------------------------------------------------
-- Seed Settings for Knowledge Base
-- --------------------------------------------------------

INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_type`, `updated_at`)
VALUES
('knowledge_base_enabled', '1', 'boolean', NOW()),
('faq_enabled', '1', 'boolean', NOW())
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- --------------------------------------------------------
-- Seed Default Categories
-- --------------------------------------------------------

INSERT INTO `knowledge_base_categories` (`id`, `name`, `slug`, `description`, `icon`, `sort_order`, `status`, `created_by`, `created_at`, `updated_at`)
VALUES
(1, 'Getting Started', 'getting-started', 'Essential onboarding guides, tutorials, and quick start references.', 'bi-rocket-takeoff', 1, 'active', 1, NOW(), NOW()),
(2, 'Account & Security', 'account-security', 'Manage credentials, 2-factor verification, and profile privacy settings.', 'bi-shield-check', 2, 'active', 1, NOW(), NOW()),
(3, 'Billing & Invoicing', 'billing-invoicing', 'Subscription plans, payment options, invoices, and refund policies.', 'bi-credit-card', 3, 'active', 1, NOW(), NOW()),
(4, 'Troubleshooting', 'troubleshooting', 'Solutions to common system errors, connection bugs, and connectivity issues.', 'bi-wrench-adjustable', 4, 'active', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- --------------------------------------------------------
-- Seed Default Articles
-- --------------------------------------------------------

INSERT INTO `knowledge_base_articles` (`id`, `category_id`, `title`, `slug`, `excerpt`, `content`, `status`, `is_featured`, `view_count`, `created_by`, `created_at`, `updated_at`, `published_at`)
VALUES
(1, 1, 'How to Submit and Track Support Tickets', 'how-to-submit-and-track-support-tickets', 'Learn how to create inquiries, attach diagnostic files, and check resolution updates.', 'Submitting a support ticket is the fastest way to get direct assistance from our customer success engineers.\n\n### Steps to Create a Ticket:\n1. Sign in to your customer account.\n2. Click on **Create Ticket** in the navigation sidebar or Support Center.\n3. Enter a clear and descriptive Subject summarizing your issue.\n4. Select the appropriate Department (e.g. Technical Support or Billing).\n5. Specify the Priority Level of your inquiry.\n6. Provide detailed steps to reproduce the issue in the message field.\n7. Optionally attach diagnostic logs, screenshots, or PDF invoices (up to 10MB).\n8. Click **Submit Ticket**.\n\nYou will receive automated in-app alerts and email notifications as our team responds to your inquiry.', 'published', 1, 45, 1, NOW(), NOW(), NOW()),

(2, 2, 'How to Reset Your Account Password', 'how-to-reset-your-account-password', 'Step-by-step instructions for resetting forgotten passwords or updating security credentials.', 'If you have forgotten your password or wish to update your login credentials, follow these instructions:\n\n### If You Forgot Your Password:\n1. Open the Login page at `/auth/login.php`.\n2. Click on the **Forgot Password?** link beneath the password input.\n3. Enter your registered email address and submit.\n4. A secure password reset link will be generated. Follow the instructions to choose a new strong password.\n\n### If You Are Currently Logged In:\n1. Go to **My Profile** from the sidebar or top right user menu.\n2. Select **Change Password**.\n3. Enter your current password and your new password (minimum 8 characters).\n4. Click **Update Password**.\n\nRemember to keep your credentials confidential at all times.', 'published', 1, 78, 1, NOW(), NOW(), NOW()),

(3, 3, 'Understanding Billing Cycles and Invoice History', 'understanding-billing-cycles-and-invoice-history', 'Overview of payment schedules, downloading invoice receipts, and currency support.', 'Our billing cycle operates on a recurring monthly or annual schedule depending on your selected tier.\n\n### Payment Methods Supported:\n- Major Credit & Debit Cards (Visa, MasterCard, American Express)\n- Corporate Bank Wire Transfer\n- Automated Clearing House (ACH)\n\n### Downloading Invoices:\nAll finalized invoices are generated on the 1st day of each billing cycle. If you require tax exemption documents or customized company invoice details, submit a ticket to the **Billing & Payment** department.', 'published', 0, 22, 1, NOW(), NOW(), NOW()),

(4, 4, 'Resolving Attachment Upload Errors', 'resolving-attachment-upload-errors', 'Troubleshooting guide for file upload limits, blocked extensions, and connection timeouts.', 'If you encounter an error when attaching files to support tickets, please verify the following common causes:\n\n1. **File Size Limit**: The maximum allowed attachment size is **10MB** per file. Compress large log files into a `.zip` archive before uploading.\n2. **Allowed File Extensions**: Supported formats include `.jpg`, `.jpeg`, `.png`, `.webp`, `.pdf`, `.doc`, `.docx`, `.txt`, `.zip`, `.log`, `.csv`, `.xlsx`.\n3. **Prohibited Executables**: Script and binary files (`.php`, `.exe`, `.sh`, `.bat`, `.js`) are strictly blocked for security protection.\n4. **Network Interruption**: If your connection drops during upload, try refreshing and uploading individual files.', 'published', 1, 34, 1, NOW(), NOW(), NOW())
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- --------------------------------------------------------
-- Seed Default FAQs
-- --------------------------------------------------------

INSERT INTO `faqs` (`id`, `question`, `answer`, `sort_order`, `status`, `created_by`, `created_at`, `updated_at`)
VALUES
(1, 'What are your standard support operating hours?', 'Our support team monitors urgent inquiries 24/7. Standard business support inquiries are addressed Monday through Friday, 8:00 AM – 6:00 PM EST.', 1, 'active', 1, NOW(), NOW()),
(2, 'How long does it usually take to receive a response?', 'First response times for urgent inquiries are typically under 1 hour. Standard technical inquiries are responded to within 4 business hours.', 2, 'active', 1, NOW(), NOW()),
(3, 'Can I reopen a ticket after it has been marked resolved?', 'Yes! If you reply to any ticket that was marked resolved or closed, the system will automatically reopen the ticket and notify your assigned support agent.', 3, 'active', 1, NOW(), NOW()),
(4, 'Are my uploaded attachments and internal notes secure?', 'All ticket attachments are stored in protected directories with randomized server names and access is strictly authenticated. Internal staff notes are private and never exposed to customers.', 4, 'active', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `question` = VALUES(`question`);
