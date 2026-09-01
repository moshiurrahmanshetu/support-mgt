<?php
/**
 * Application Configuration
 * Customer Support Management System (support-mgt)
 */

// Application Info
define('APP_NAME', 'SupportDesk');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/support-mgt');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'support_mgt_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Session Configuration
define('SESSION_NAME', 'support_mgt_session');
define('SESSION_LIFETIME', 86400); // 1 day

// Upload Path
define('UPLOAD_DIR', __DIR__ . '/../uploads');
define('AVATAR_UPLOAD_DIR', __DIR__ . '/../uploads/avatars');
define('AVATAR_URL_PATH', APP_URL . '/uploads/avatars');

// Error Reporting (Turn off display_errors in production)
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Timezone
date_default_timezone_set('Asia/Dhaka');
