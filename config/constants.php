<?php
/**
 * Application Constants
 */

// User Roles
define('ROLE_ADMIN', 'admin');
define('ROLE_ADMINISTRATOR', 'administrator');
define('ROLE_SUPPORT_MANAGER', 'support_manager');
define('ROLE_SUPPORT_AGENT', 'support_agent');
define('ROLE_AGENT', 'agent');
define('ROLE_CUSTOMER', 'customer');

// Valid roles list
define('VALID_ROLES', [
    ROLE_ADMIN,
    ROLE_ADMINISTRATOR,
    ROLE_SUPPORT_MANAGER,
    ROLE_SUPPORT_AGENT,
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

// Ticket Priorities
define('PRIORITY_LOW', 'low');
define('PRIORITY_MEDIUM', 'medium');
define('PRIORITY_HIGH', 'high');
define('PRIORITY_URGENT', 'urgent');

define('VALID_PRIORITIES', [
    PRIORITY_LOW,
    PRIORITY_MEDIUM,
    PRIORITY_HIGH,
    PRIORITY_URGENT
]);

// Ticket Statuses
define('STATUS_OPEN', 'open');
define('STATUS_IN_PROGRESS', 'in_progress');
define('STATUS_PENDING', 'pending');
define('STATUS_RESOLVED', 'resolved');
define('STATUS_CLOSED', 'closed');

define('VALID_TICKET_STATUSES', [
    STATUS_OPEN,
    STATUS_IN_PROGRESS,
    STATUS_PENDING,
    STATUS_RESOLVED,
    STATUS_CLOSED
]);

// Ticket Message Types
define('MESSAGE_TYPE_REPLY', 'reply');
define('MESSAGE_TYPE_NOTE', 'internal_note');

// Ticket Attachment Limits
define('MAX_TICKET_ATTACHMENT_SIZE', 10 * 1024 * 1024); // 10 MB

define('ALLOWED_ATTACHMENT_EXTENSIONS', [
    'jpg',
    'jpeg',
    'png',
    'webp',
    'pdf',
    'doc',
    'docx',
    'txt',
    'zip',
    'log',
    'csv',
    'xlsx'
]);

define('BLOCKED_ATTACHMENT_EXTENSIONS', [
    'php',
    'php3',
    'php4',
    'php5',
    'php7',
    'php8',
    'phtml',
    'phar',
    'cgi',
    'pl',
    'exe',
    'sh',
    'py',
    'asp',
    'aspx',
    'js',
    'bat',
    'cmd',
    'vbs',
    'jar',
    'scr'
]);
