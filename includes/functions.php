<?php
/**
 * Core Helper Functions
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';

// Safe Session Initialization
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'domain'   => '',
        'secure'   => false, // Set to true if on HTTPS
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

/**
 * Escape string for safe HTML output
 */
function e(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Generate absolute URL for the application
 */
function url(string $path = ''): string {
    $baseUrl = rtrim(APP_URL, '/');
    $cleanPath = ltrim($path, '/');
    return empty($cleanPath) ? $baseUrl : $baseUrl . '/' . $cleanPath;
}

/**
 * Redirect to a given URL or internal path
 */
function redirect(string $path): void {
    if (!filter_var($path, FILTER_VALIDATE_URL)) {
        $path = url($path);
    }
    header('Location: ' . $path);
    exit;
}

/**
 * Add a flash message to session
 */
function flash(string $type, string $message): void {
    if (!isset($_SESSION['flash_messages']) || !is_array($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][] = [
        'type'    => $type, // success, danger, warning, info
        'message' => $message
    ];
}

/**
 * Retrieve and clear flash messages
 */
function get_flashes(): array {
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

/**
 * Set old form inputs in session
 */
function set_old_input(array $data): void {
    // Avoid storing passwords in old input
    unset($data['password'], $data['confirm_password'], $data['current_password'], $data['new_password'], $data['csrf_token']);
    $_SESSION['old_input'] = $data;
}

/**
 * Retrieve old input value
 */
function old(string $key, $default = ''): string {
    $value = $_SESSION['old_input'][$key] ?? $default;
    return (string)$value;
}

/**
 * Clear old form inputs from session
 */
function clear_old_input(): void {
    unset($_SESSION['old_input']);
}

/**
 * Generate cryptographically secure random token
 */
function generate_token(int $length = 32): string {
    return bin2hex(random_bytes($length));
}

/**
 * Format datetime string into human readable format
 */
function format_datetime(?string $datetime, string $format = 'M d, Y h:i A'): string {
    if (empty($datetime)) {
        return 'Never';
    }
    try {
        $dt = new DateTime($datetime);
        return $dt->format($format);
    } catch (Exception $e) {
        return (string)$datetime;
    }
}

/**
 * Get public avatar URL for a given avatar filename
 */
function get_avatar_url(?string $avatarFilename): string {
    if (!empty($avatarFilename)) {
        $filePath = AVATAR_UPLOAD_DIR . '/' . $avatarFilename;
        if (file_exists($filePath)) {
            return AVATAR_URL_PATH . '/' . $avatarFilename;
        }
    }
    return DEFAULT_AVATAR_PATH;
}

/**
 * Sanitize basic string input
 */
function sanitize_input(string $data): string {
    return trim(strip_tags($data));
}
