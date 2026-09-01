<?php
/**
 * Application Constants
 */

// User Roles
define('ROLE_ADMIN', 'admin');
define('ROLE_AGENT', 'agent');
define('ROLE_CUSTOMER', 'customer');

// Valid roles list
define('VALID_ROLES', [
    ROLE_ADMIN,
    ROLE_AGENT,
    ROLE_CUSTOMER
]);

// Account Statuses
define('STATUS_ACTIVE', 'active');
define('STATUS_INACTIVE', 'inactive');

// Valid statuses list
define('VALID_STATUSES', [
    STATUS_ACTIVE,
    STATUS_INACTIVE
]);

// Avatar Upload Limits
define('MAX_AVATAR_SIZE', 2 * 1024 * 1024); // 2 MB
define('ALLOWED_AVATAR_MIMES', [
    'image/jpeg',
    'image/png',
    'image/webp'
]);
define('ALLOWED_AVATAR_EXTENSIONS', [
    'jpg',
    'jpeg',
    'png',
    'webp'
]);

// Default Avatar Asset Path
define('DEFAULT_AVATAR_PATH', APP_URL . '/assets/images/default-avatar.svg');

// Password Reset Token Expiry (in seconds, e.g. 1 hour)
define('PASSWORD_RESET_EXPIRY', 3600);
