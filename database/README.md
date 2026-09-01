# Database Documentation — Phase 01

This directory contains the database migration scripts and seed data for **support-mgt**.

## Database: `support_mgt_db`

### 1. `01_authentication.sql`
Contains:
- `users`: Core table storing user accounts, role (`admin`, `agent`, `customer`), password hash, avatar, status (`active`, `inactive`), and timestamps.
- `password_resets`: Table storing secure password reset tokens, expiration times, and usage flags.
- **Default Administrator Seed**:
  - **Email**: `admin@supportmgt.local`
  - **Password**: `Admin@123456`
  - **Role**: `admin`

## Importing the Database in XAMPP

### Option A: Using MySQL Command Line
```powershell
c:\xampp\mysql\bin\mysql.exe -u root < c:\xampp\htdocs\support-mgt\database\01_authentication.sql
```

### Option B: Using phpMyAdmin
1. Open [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
2. Create database `support_mgt_db` (Collation: `utf8mb4_unicode_ci`) or select Import directly.
3. Choose file `database/01_authentication.sql` and click **Import**.
