# RAW PHP Customer Support Management System (`support-mgt`)

A lightweight, enterprise-ready Customer Support Management System built with **Raw PHP**, **MySQL (PDO)**, **Bootstrap 5**, **Bootstrap Icons**, and **Vanilla JavaScript**.

---

## 🚀 Key Features by Phase

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
- **Department Management**: Support teams setup (`Technical Support`, `Billing & Payment`, `Sales & Account Inquiry`, `General Support`) with active status toggles.
- **Customer Directory**: Filterable customer directory with live ticket analytics and profile drilldowns.
- **Agent Provisioning**: Agent accounts with department assignments, workload tracking, and safe deactivation.
- **Ticket-Department Integration**: Department categorization and department-aware agent assignment.
- **Admin Safety Guard**: Server-side protection preventing deactivation of the last active administrator account.

### Phase 04: Advanced Ticket Workflow, History & Tools
- **Ticket Tags**: Custom tag creation with HEX color badges (`Technical`, `Billing`, `Bug Report`, `Feature Request`, `Urgent Assistance`). Interactive tag assignment on tickets.
- **Canned Responses**: Standardized response templates for support agents to quickly answer frequent inquiries with one-click insertion and pre-send editing.
- **Activity History & Timeline**: Audit trail tracking ticket creation, assignments, unassignments, status changes, priority shifts, department transfers, tags added/removed, and reopens.
- **Ticket Reopen Workflow**: Customer replies to `resolved` or `closed` tickets automatically reopen the ticket, clear resolution timestamps, and log activity events.
- **Response & Resolution Metrics**: First response time tracking (`first_response_at`) on initial staff reply and resolution duration tracking (`resolved_at`).
- **Advanced Search, Filters & Sorting**: Search by ticket #, subject, customer name/email; filter by Status, Priority, Department, Agent, and Tag; sorting by Newest, Oldest, Updated, and Priority; pagination (20, 50, 100).

---

## 🗄️ Database Architecture & Migration Order

The database migrations must be imported in dependency order:

```
database/
├── 01_authentication.sql             # Phase 01: users, password_resets, default admin seed
├── 02_ticket_management.sql          # Phase 02: tickets, ticket_messages, ticket_attachments
├── 03_customer_agent_department.sql  # Phase 03: departments, department foreign keys
├── 04_advanced_ticket_workflow.sql   # Phase 04: tags, relations, canned responses, activity logs
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

# 4. Phase 04: Advanced Workflows, Tags, Canned Responses & Logs
cmd.exe /c "c:\xampp\mysql\bin\mysql.exe -u root < c:\xampp\htdocs\support-mgt\database\04_advanced_ticket_workflow.sql"
```

---

## 👥 Default Administrator Credentials

| Role | Email | Password |
| :--- | :--- | :--- |
| **Administrator** | `admin@supportmgt.local` | `Admin@123456` |

---

## 🔒 Security Measures

1. **SQL Injection Prevention**: All database queries strictly use PDO prepared statements with parameterized inputs.
2. **Cross-Site Scripting (XSS)**: User-supplied output is escaped via `htmlspecialchars()` using `e()`.
3. **Cross-Site Request Forgery (CSRF)**: All POST requests require a verified CSRF session token.
4. **IDOR & Role Guards**: Server-side authorization checks ensure users can only access resources permitted by their role.
5. **Upload Protection**: Strict whitelist validation, randomized filenames, and `.htaccess` execution prevention in upload folders.
6. **Admin Deactivation Protection**: Server-side checks guarantee at least one active administrator remains at all times.
7. **Zero Destructive Cascades**: Deleting tags removes pivot relations only, never deleting tickets or conversation logs.