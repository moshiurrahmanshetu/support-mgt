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

### Phase 05: Notifications, Email, Activity Logs & System Settings
- **Robust Pagination Engine**: Mathematically guarded `get_pagination_params()` ensuring zero division is impossible, sanitizing `per_page` (whitelist: 20, 50, 100), clamping `page`, and preserving active filter queries across pagination.
- **In-App Notification Center**: User inbox for alerts (`ticket_created`, `ticket_assigned`, `ticket_reply`, `ticket_status_changed`, `ticket_reopened`), unread filtering, and single/bulk mark-as-read.
- **Live Topbar Notification Bell**: Real-time unread badge counter and quick-action dropdown menu showing the 5 most recent notifications.
- **Email Notification Infrastructure**: Reusable template engine supporting automated email dispatch on ticket lifecycle events, with safe fail-open error handling (email failure never halts ticket operations).
- **User Notification Preferences**: Fine-grained personal preferences allowing customers and staff to toggle email/in-app notification channels independently.
- **System Activity Logs**: Admin audit trail logging user authentication (login/logout), customer/agent management, department shifts, tag & canned response changes, and system settings updates.
- **Admin System Settings**: Tabbed configuration portal for General settings (App Name, URL, Company, Timezone with PHP validation, Date Format), Support Desk rules, Email/SMTP delivery, and Global notification toggles.

### Phase 06: Knowledge Base, FAQs & Public Support Center
- **Public Support Center Portal (`modules/knowledge_base/`)**: Customer-facing help center with prominent hero search, category grid, featured guides, popular articles, and interactive FAQ accordion.
- **Knowledge Base Categories**: Topic management with custom icons, sort orders, and unique slug generation. Deletion safety blocker prevents removing categories containing active articles.
- **Knowledge Base Articles**: Rich article documentation with excerpts, view counters (session-guarded to prevent refresh abuse), featured image uploads (JPEG, PNG, WebP), and publish/draft controls.
- **Article Search Engine**: Prepared-statement parameterized search across title, excerpt, and content, restricted strictly to published articles in active categories.
- **Related Articles**: Contextual recommendation widget rendering top articles from the same category on article pages.
- **Frequently Asked Questions (FAQ)**: Admin FAQ management with drag/sort ordering and collapsible Bootstrap accordion on the public portal.
- **Live Ticket Creation Auto-Suggestions**: Non-blocking Vanilla JavaScript auto-suggestion box beneath ticket subject input that dynamically queries published solutions.
- **System Settings Integration**: Global toggles for `knowledge_base_enabled` and `faq_enabled`.

### Phase 07: Reports & Analytics + Dashboard Enhancement
- **Reports Overview Dashboard (`modules/reports/index.php`)**: Executive KPI center with date range filtering (`Today`, `Yesterday`, `Last 7 Days`, `Last 30 Days`, `This Month`, `Last Month`, `Custom Range`), tickets breakdown, average first response latency, average resolution duration, and unassigned backlog.
- **Ticket Distribution Report (`modules/reports/tickets.php`)**: Status & Priority breakdown with volume metrics, percentage distribution bars, and zero-division mathematically safe calculations.
- **Department Performance Report (`modules/reports/departments.php`)**: Volume comparisons, status distribution, resolution speeds, and volume percentage shares across support teams.
- **Agent Workload & Performance (`modules/reports/agents.php`)**: Individual staff metrics tracking assigned tickets, open vs resolved workloads, average first response times, and average resolution turnaround times with active/inactive filtering.
- **Customer Ticket Analytics (`modules/reports/customers.php`)**: Customer inquiry volumes, resolution ratios, and last ticket activity dates with safe pagination (20, 50, 100).
- **First Response Time Analytics (`modules/reports/response_time.php`)**: Average, fastest, and slowest response metrics with speed distribution brackets (&lt;15m, 15m–1h, 1h–4h, 4h–24h, &gt;24h) and recent response audit logs.
- **Resolution Turnaround Analytics (`modules/reports/resolution_time.php`)**: Average, fastest, and slowest resolution turnaround times with duration brackets (&lt;1h, 1h–8h, 8h–24h, 1d–3d, &gt;3d) and recently resolved ticket details.
- **Secure CSV Export Engine (`modules/reports/export.php`)**: Native streaming export for Tickets, Agents, Customers, and Departments with CSV Formula Injection protection (`=`, `+`, `-`, `@`), UTF-8 BOM encoding, parameter filtering, and `report_exported` activity logging.
- **Dashboard Enhancements (`index.php`)**:
  - *Admin*: Added date range switchers (`Today`, `Last 7 Days`, `Last 30 Days`), live First Response & Resolution Speed KPI cards, and direct unassigned ticket links.
  - *Agent*: Scoped strictly to personal assigned tickets, personal First Response Speed, and personal Resolution Speed.
  - *Customer*: Clean personal dashboard scoped to own tickets and quick action links.

---

## 🗄️ Database Architecture & Migration Order

The database migrations must be imported in dependency order:

```
database/
├── 01_authentication.sql             # Phase 01: users, password_resets, default admin seed
├── 02_ticket_management.sql          # Phase 02: tickets, ticket_messages, ticket_attachments
├── 03_customer_agent_department.sql  # Phase 03: departments, department foreign keys
├── 04_advanced_ticket_workflow.sql   # Phase 04: tags, relations, canned responses, activity logs
├── 05_notifications_settings.sql     # Phase 05: notifications, preferences, system logs, settings
├── 06_knowledge_base.sql             # Phase 06: categories, articles, faqs, default KB seeds
├── 07_reports.sql                    # Phase 07: documentation & performance indexes
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

# 5. Phase 05: Notifications, Preferences, System Logs & Settings
cmd.exe /c "c:\xampp\mysql\bin\mysql.exe -u root < c:\xampp\htdocs\support-mgt\database\05_notifications_settings.sql"

# 6. Phase 06: Knowledge Base, Categories, Articles & FAQs
cmd.exe /c "c:\xampp\mysql\bin\mysql.exe -u root < c:\xampp\htdocs\support-mgt\database\06_knowledge_base.sql"

# 7. Phase 07: Reports & Analytics Performance Indexes
cmd.exe /c "c:\xampp\mysql\bin\mysql.exe -u root < c:\xampp\htdocs\support-mgt\database\07_reports.sql"
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
4. **Role Guards & Access Controls**: Server-side authorization checks (`require_role(ROLE_ADMIN)`) prevent unauthorized report and export viewing.
5. **CSV Formula Injection Mitigation**: All exported spreadsheet values beginning with `=`, `+`, `-`, `@` are safely sanitized.
6. **Credential Privacy**: Sensitive data (passwords, salts, session tokens, SMTP credentials) are strictly excluded from CSV exports and report interfaces.
7. **Division-by-Zero Safety**: All calculations are mathematically guarded against empty datasets (`safe_percentage()`).