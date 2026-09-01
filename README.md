# Customer Support Management System (`support-mgt`)

A lightweight, high-performance, and secure **RAW PHP Customer Support Management System** built with **PHP 8**, **MySQL (PDO)**, and **Bootstrap 5 / Bootstrap Icons**.

> **Phase 01 Deliverable**: Project Foundation, Authentication System, Role Foundation, User Profile System, and Responsive SaaS Admin Dashboard UI.

---

## 1. System Requirements

- **PHP**: 8.0 or higher (`pdo_mysql`, `fileinfo`, `mbstring`, `session` extensions enabled)
- **Database**: MySQL 5.7+ or MariaDB 10.4+
- **Web Server**: Apache 2.4+ (e.g. XAMPP, WampServer, or LAMP)
- **Browser**: Modern web browser (Chrome, Firefox, Edge, Safari)

---

## 2. Installation & XAMPP Setup

### Step 1: Clone / Place the Project
Place the `support-mgt` directory inside your web server's document root:
```
C:\xampp\htdocs\support-mgt\
```

### Step 2: Database Setup & Migration
1. Start **Apache** and **MySQL** in your XAMPP Control Panel.
2. Import the Phase 01 schema into MySQL:

#### Option A: Using PowerShell / Terminal
```powershell
cmd.exe /c "c:\xampp\mysql\bin\mysql.exe -u root < c:\xampp\htdocs\support-mgt\database\01_authentication.sql"
```

#### Option B: Using phpMyAdmin
- Navigate to [http://localhost/phpmyadmin](http://localhost/phpmyadmin).
- Create a new database named `support_mgt_db` (Collation: `utf8mb4_unicode_ci`).
- Go to the **Import** tab, choose `database/01_authentication.sql`, and click **Import**.

### Step 3: Application Configuration
Review `config/config.php` to verify your local database settings and application URL:
```php
define('APP_NAME', 'SupportDesk');
define('APP_URL', 'http://localhost/support-mgt');

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'support_mgt_db');
define('DB_USER', 'root');
define('DB_PASS', '');
```

---

## 3. Access & Default Credentials

Open your web browser and navigate to:
```
http://localhost/support-mgt/
```

### Seed Administrator Account (Development)
- **Email**: `admin@supportmgt.local`
- **Password**: `Admin@123456`
- **Role**: `admin`

---

## 4. Architecture & File Structure

```
support-mgt/
├── assets/
│   ├── css/
│   │   └── style.css            # Clean, modern SaaS admin styles (solid colors, collapsible sidebar)
│   ├── js/
│   │   └── main.js             # Collapsible sidebar, tooltips, password toggle, alerts
│   ├── images/
│   │   ├── logo.svg            # Crisp application vector logo
│   │   └── default-avatar.svg  # Vector default user avatar
│   └── vendor/                 # Offline Bootstrap 5 & Bootstrap Icons bundles
│
├── auth/
│   ├── login.php               # Login form & authentication POST handler
│   ├── logout.php              # Secure logout handler (clears and destroys session)
│   ├── register.php           # Customer registration (strictly auto-assigns 'customer' role)
│   ├── forgot_password.php     # Token-based password reset request workflow
│   ├── reset_password.php      # Secure token validation and password reset handler
│   └── verify_account.php      # Account email verification endpoint
│
├── config/
│   ├── config.php              # Centralized environment, URL, and database settings
│   ├── constants.php           # Roles, statuses, upload constraints, token expiry
│   └── database.php            # PDO singleton database connection provider
│
├── database/
│   ├── 01_authentication.sql   # Phase 01 database schema and initial admin seed
│   └── README.md               # Database setup and migration guide
│
├── includes/
│   ├── functions.php           # Global helper functions (e, url, redirect, flash, old, formatting)
│   ├── csrf.php                # CSRF protection (generation, form fields, verification)
│   ├── auth_check.php          # require_login(), require_role(), is_logged_in(), current_user()
│   ├── guest_check.php         # require_guest() guard for authentication pages
│   ├── flash_messages.php      # Dismissible Bootstrap 5 alert renderer
│   ├── header.php              # HTML head, styles, and top wrapper
│   ├── topbar.php              # Sticky top header, hamburger toggle, profile dropdown
│   ├── sidebar.php             # Collapsible, role-aware navigation sidebar
│   └── footer.php              # Layout closing tags, scripts, and footer copyright
│
├── modules/
│   └── profile/
│       ├── index.php           # User profile overview (avatar, details, roles, last login)
│       ├── edit.php            # Edit profile personal details (name, phone)
│       ├── change_password.php # Update password with current password verification
│       └── change_avatar.php   # Secure avatar image upload, validation, and old file cleanup
│
├── uploads/
│   └── avatars/
│       └── .htaccess           # Security guard preventing script execution in uploads
│
├── .htaccess                   # Root security rules & directory listing prevention
├── index.php                   # Authenticated dashboard overview
└── README.md                   # Project documentation
```

---

## 5. Security Features

1. **Password Security**: Passwords hashed with `PASSWORD_DEFAULT` (`bcrypt`). Plain-text passwords are never stored.
2. **CSRF Protection**: Cryptographically secure CSRF tokens on all POST requests (`login`, `register`, `forgot_password`, `reset_password`, `profile_edit`, `change_password`, `change_avatar`).
3. **Session Management**:
   - `session_regenerate_id(true)` upon successful login and password modification to defeat session fixation.
   - `HttpOnly` and `SameSite=Lax` session cookie settings.
4. **SQL Injection Prevention**: 100% parameterized queries with PDO prepared statements and emulation disabled (`PDO::ATTR_EMULATE_PREPARES => false`).
5. **XSS Protection**: Context-aware output escaping helper `e()` using `htmlspecialchars()` with `ENT_QUOTES | ENT_SUBSTITUTE`.
6. **Avatar Upload Hardening**:
   - Strict MIME validation via `finfo` (`image/jpeg`, `image/png`, `image/webp`).
   - Extension whitelist (`jpg`, `jpeg`, `png`, `webp`).
   - Binary image integrity verification with `getimagesize()`.
   - File size cap (2MB).
   - Cryptographically random filenames (`avatar_` + 32-hex characters).
   - Execution prevention via `.htaccess` in `uploads/avatars/`.
7. **Strict Role Isolation**:
   - Public registration strictly assigns the `customer` role.
   - Reusable `require_role()` authorization gates.

---

## 6. UI & Design System

- **Clean SaaS Aesthetics**: Spacious, modern layout with subtle borders and solid colors.
- **Strict No-Gradient Rule**: Pure solid colors used throughout all components.
- **Collapsible Sidebar**:
  - Desktop: Expands with icon + text; collapses to icons only with instant hover tooltips. State is automatically remembered via `localStorage`.
  - Mobile: Smooth offcanvas drawer with backdrop and hamburger button.