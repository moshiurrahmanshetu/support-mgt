# RAW PHP Customer Support Management System (`support-mgt`)

A lightweight, enterprise-ready Customer Support Management System built with **Raw PHP**, **MySQL (PDO)**, **Bootstrap 5**, **Bootstrap Icons**, and **Vanilla JavaScript**.

---

## 🚀 Key Features

### Phase 01: Foundation & Authentication
- **Role-Based Access Control (RBAC)**: Distinct permissions for `admin`, `agent`, and `customer`.
- **Authentication**: Secure registration, login with "Remember Me", session management, and password reset via token.
- **Profile Management**: Profile info update, avatar upload with file validation, and secure password changes.
- **Responsive Layout**: Modern dashboard with a collapsible sidebar (Icon + Label when expanded; Icon-only with tooltips when collapsed; mobile offcanvas support).
- **Strict UI Aesthetic**: 100% Solid colors — clean, professional SaaS interface with no CSS gradients.

### Phase 02: Ticket Management Core
- **Ticket Creation**: Sequential ticket numbering (e.g. `TKT-100001`), priority levels (`low`, `medium`, `high`, `urgent`), and file attachments.
- **Conversation Timeline**: Visual separation of customer responses, staff responses, and internal staff notes.
- **Internal Staff Notes**: Dedicated private notes (`internal_note`) with amber solid styling, strictly hidden from customers.
- **Ticket Lifecycle**: Dynamic status transitions (`open`, `in_progress`, `pending`, `resolved`, `closed`) with automated timestamp auditing (`resolved_at`, `closed_at`).
- **Secure File Streaming**: Dedicated attachment download handler with IDOR checks and `.htaccess` upload script execution blocking.

### Phase 03: Customer, Agent & Department Management
- **Department Management**:
  - Full department CRUD (`Technical Support`, `Billing & Payment`, `Sales & Account Inquiry`, `General Support`).
  - Active/Inactive status toggle with zero data loss to existing tickets and agents.
  - Agent and ticket counts per department.
- **Customer Management**:
  - Filterable customer directory with live ticket statistics (Total, Open).
  - Customer profile overview with complete ticket history and direct ticket navigation.
  - Read-only email protection and account activation/deactivation.
- **Agent Management**:
  - Dedicated agent provisioning (role immutable and strictly forced to `agent`).
  - Department team assignment with active department verification.
  - Workload metrics: Assigned Tickets, Resolved Tickets, and detailed ticket timeline.
  - Safe agent deactivation preventing new ticket assignments while preserving historical records.
- **Ticket-Department Integration**:
  - Support ticket creation with active department categorization.
  - Department-aware agent assignment: Admin ticket view intelligently filters agents matching the ticket's department.
  - Advanced ticket directory filtering by Department, Priority, and Status.
- **Admin Safety Guard**: Server-side protection preventing deactivation of the last active administrator account.

---

## 🗄️ Database Architecture & Migration Order

The database migrations must be imported in dependency order:

```
database/
├── 01_authentication.sql             # Phase 01: users, password_resets, default admin seed
├── 02_ticket_management.sql          # Phase 02: tickets, ticket_messages, ticket_attachments
├── 03_customer_agent_department.sql  # Phase 03: departments, department_id foreign keys
└── README.md                         # Migration documentation
```

### Import Commands in XAMPP (MySQL)

```powershell
# 1. Phase 01: Core Users & Authentication
cmd.exe /c "c:\xampp\mysql\bin\mysql.exe -u root < c:\xampp\htdocs\support-mgt\database\01_authentication.sql"

# 2. Phase 02: Tickets & Attachments
cmd.exe /c "c:\xampp\mysql\bin\mysql.exe -u root < c:\xampp\htdocs\support-mgt\database\02_ticket_management.sql"

# 3. Phase 03: Departments & Management
cmd.exe /c "c:\xampp\mysql\bin\mysql.exe -u root < c:\xampp\htdocs\support-mgt\database\03_customer_agent_department.sql"
```

---

## 👥 Default Administrator Credentials

| Role | Email | Password |
| :--- | :--- | :--- |
| **Administrator** | `admin@supportmgt.local` | `Admin@123456` |

---

## 📁 Directory Structure

```
support-mgt/
├── assets/
│   ├── css/style.css                # Custom solid-color SaaS design system
│   ├── js/main.js                   # Sidebar toggle, password toggle, tooltips
│   ├── images/                      # Default avatars and logo
│   └── vendor/                      # Bootstrap 5 & Bootstrap Icons
├── auth/
│   ├── login.php                    # Sign In
│   ├── register.php                 # Customer Registration
│   ├── forgot_password.php          # Password Reset Request
│   ├── reset_password.php           # Token-based Password Reset
│   └── logout.php                   # Secure Session Termination
├── config/
│   ├── config.php                   # APP_URL, upload directories, database credentials
│   ├── constants.php                # Roles, statuses, priorities, upload rules
│   └── database.php                 # PDO database connector with UTF-8
├── database/
│   ├── 01_authentication.sql        # Phase 01 SQL
│   ├── 02_ticket_management.sql     # Phase 02 SQL
│   ├── 03_customer_agent_department.sql # Phase 03 SQL
│   └── README.md
├── includes/
│   ├── auth_check.php               # Login/role guards and admin safety checks
│   ├── csrf.php                     # CSRF token generator & validator
│   ├── functions.php                # Centralized helpers (url, e, flash, formatters)
│   ├── header.php                   # HTML head, topbar navbar
│   ├── sidebar.php                  # Collapsible role-based sidebar
│   ├── footer.php                   # Footer scripts & initialization
│   └── flash_messages.php           # UI alert messages
├── modules/
│   ├── profile/                     # Profile, avatar, password management
│   ├── tickets/                     # Ticket CRUD, conversation, replies, assignment
│   ├── customers/                   # Admin customer directory, profile, edit, status
│   ├── agents/                      # Admin agent provisioning, workload, edit, status
│   └── departments/                 # Admin department management, active status toggle
├── uploads/
│   ├── avatars/                     # User profile photos
│   └── tickets/                     # Encrypted ticket attachments (.htaccess protected)
├── index.php                        # Live analytics dashboard
└── README.md
```

---

## 🔒 Security Measures

1. **SQL Injection Prevention**: All database queries strictly use PDO prepared statements with parameterized inputs.
2. **Cross-Site Scripting (XSS)**: All user-supplied output is escaped via `htmlspecialchars()` using `e()`.
3. **Cross-Site Request Forgery (CSRF)**: All POST requests require a verified CSRF session token.
4. **IDOR & Role Guards**: Server-side authorization checks ensure users can only access resources permitted by their role.
5. **Upload Protection**: Strict whitelist validation, randomized filenames, and `.htaccess` execution prevention in upload folders.
6. **Admin Deactivation Protection**: Server-side checks guarantee at least one active administrator remains at all times.