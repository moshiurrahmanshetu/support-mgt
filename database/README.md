# Database Documentation — Phases 01, 02, 03 & 04

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
- `tickets`: Core ticket tracking table (public `ticket_number` e.g. `TKT-100001`, `subject`, `description`, `priority`, `status`, `assigned_to`, `resolved_at`, `closed_at`).
- `ticket_messages`: Message history table storing replies and internal staff notes (`message_type`: `reply` | `internal_note`).
- `ticket_attachments`: Secure attachment records (`original_name`, `stored_name`, `mime_type`, `file_size`).

### 3. `03_customer_agent_department.sql` (Phase 03)
Contains:
- `departments`: Table storing support departments (`id`, `name`, `description`, `status`).
- Adds `department_id` to `users` for agent department assignment.
- Adds `department_id` to `tickets` for ticket department integration.
- **Initial Department Seeds**:
  - Technical Support
  - Billing & Payment
  - Sales & Account Inquiry
  - General Support

### 4. `04_advanced_ticket_workflow.sql` (Phase 04)
Contains:
- Adds `first_response_at` to `tickets`.
- `ticket_tags`: Custom ticket tags (`id`, `name`, `color`).
- `ticket_tag_relations`: Pivot table for many-to-many ticket tag association (`ticket_id`, `tag_id`).
- `canned_responses`: Pre-written response templates for staff (`id`, `title`, `content`, `created_by`).
- `ticket_activity_logs`: Audit trail for ticket lifecycle events (`ticket_id`, `user_id`, `action`, `old_value`, `new_value`, `description`).
- **Initial Seeds**:
  - 8 Default Ticket Tags (Technical, Billing, Payment, Login & Security, Account, Bug Report, Feature Request, Urgent Assistance).
  - 4 Default Canned Responses.

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
```
