# Database Documentation — Phases 01 through 07

This directory contains the database migration scripts and seed data for **support-mgt**.

## Database: `support_mgt_db`

### 1. `01_authentication.sql` (Phase 01)
Contains:
- `users`: Core table storing user accounts, role (`admin`, `agent`, `customer`), password hash, avatar, status (`active`, `inactive`), and timestamps.
- `password_resets`: Table storing secure password reset tokens, expiration times, and usage flags.
- **Default Administrator Seed**:
  - **Email**: `admin@supportmgt.local`
  - **Password**: `Admin@123456`
  - **Role**: `admin`

### 2. `02_ticket_management.sql` (Phase 02)
Contains:
- `tickets`: Core ticket tracking table (`ticket_number` e.g. `TKT-100001`, `subject`, `description`, `priority`, `status`, `assigned_to`, `resolved_at`, `closed_at`).
- `ticket_messages`: Message history table storing replies and internal staff notes (`message_type`: `reply` | `internal_note`).
- `ticket_attachments`: Secure attachment records (`original_name`, `stored_name`, `mime_type`, `file_size`).

### 3. `03_customer_agent_department.sql` (Phase 03)
Contains:
- `departments`: Table storing support departments (`id`, `name`, `description`, `status`).
- Adds `department_id` to `users` for agent department assignment.
- Adds `department_id` to `tickets` for ticket department integration.

### 4. `04_advanced_ticket_workflow.sql` (Phase 04)
Contains:
- Adds `first_response_at` to `tickets`.
- `ticket_tags`: Custom ticket tags (`id`, `name`, `color`).
- `ticket_tag_relations`: Pivot table for many-to-many ticket tag association (`ticket_id`, `tag_id`).
- `canned_responses`: Pre-written response templates for staff (`id`, `title`, `content`, `created_by`).
- `ticket_activity_logs`: Audit trail for ticket lifecycle events.

### 5. `05_notifications_settings.sql` (Phase 05)
Contains:
- `notifications`: In-app notification inbox records (`id`, `user_id`, `title`, `message`, `type`, `reference_type`, `reference_id`, `is_read`, `created_at`).
- `user_notification_preferences`: Fine-grained personal notification preferences.
- `activity_logs`: System-level activity audit trail.
- `settings`: Application configuration repository.

### 6. `06_knowledge_base.sql` (Phase 06)
Contains:
- `knowledge_base_categories`: Table for topic categories (`id`, `name`, `slug` UNIQUE, `description`, `icon`, `sort_order`, `status`, `created_by` FK).
- `knowledge_base_articles`: Table for self-service documentation (`id`, `category_id` FK, `title`, `slug` UNIQUE, `excerpt`, `content`, `featured_image`, `status`, `is_featured`, `view_count`, `created_by` FK, `published_at`).
- `faqs`: Table for frequently asked questions (`id`, `question`, `answer`, `sort_order`, `status`, `created_by` FK).

### 7. `07_reports.sql` (Phase 07)
Contains:
- Performance optimization indexes for `tickets` table (`idx_tkt_created_at`, `idx_tkt_first_response`, `idx_tkt_resolved_at`).
- Explanatory architectural documentation noting that all reports are computed live from existing application data with no static/duplicate report tables.

## Database Import Order in XAMPP

Execute the SQL files in dependency order:

```powershell
# Phase 01: Authentication
cmd.exe /c "c:\xampp\mysql\bin\mysql.exe -u root < c:\xampp\htdocs\support-mgt\database\01_authentication.sql"

# Phase 02: Ticket Management Core
cmd.exe /c "c:\xampp\mysql\bin\mysql.exe -u root < c:\xampp\htdocs\support-mgt\database\02_ticket_management.sql"

# Phase 03: Customer, Agent & Department Management
cmd.exe /c "c:\xampp\mysql\bin\mysql.exe -u root < c:\xampp\htdocs\support-mgt\database\03_customer_agent_department.sql"

# Phase 04: Advanced Workflows, Tags, Canned Responses & Activity Logs
cmd.exe /c "c:\xampp\mysql\bin\mysql.exe -u root < c:\xampp\htdocs\support-mgt\database\04_advanced_ticket_workflow.sql"

# Phase 05: Notifications, Preferences, System Logs & Settings
cmd.exe /c "c:\xampp\mysql\bin\mysql.exe -u root < c:\xampp\htdocs\support-mgt\database\05_notifications_settings.sql"

# Phase 06: Knowledge Base, Categories, Articles & FAQs
cmd.exe /c "c:\xampp\mysql\bin\mysql.exe -u root < c:\xampp\htdocs\support-mgt\database\06_knowledge_base.sql"

# Phase 07: Reports & Analytics Performance Indexes
cmd.exe /c "c:\xampp\mysql\bin\mysql.exe -u root < c:\xampp\htdocs\support-mgt\database\07_reports.sql"
```
