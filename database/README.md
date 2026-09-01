# Database Documentation — Phase 01 & Phase 02

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

## Import Order in XAMPP

```powershell
# Phase 01: Authentication
cmd.exe /c "c:\xampp\mysql\bin\mysql.exe -u root < c:\xampp\htdocs\support-mgt\database\01_authentication.sql"

# Phase 02: Ticket Management Core
cmd.exe /c "c:\xampp\mysql\bin\mysql.exe -u root < c:\xampp\htdocs\support-mgt\database\02_ticket_management.sql"
```
