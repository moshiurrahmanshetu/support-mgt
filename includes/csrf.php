<?php
/**
 * CSRF Protection Helpers
 */

require_once __DIR__ . '/functions.php';

/**
 * Get or generate CSRF token
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Generate hidden CSRF input field for HTML forms
 */
function csrf_field(): string {
    $token = csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

/**
 * Verify submitted CSRF token
 */
function verify_csrf_token(?string $token = null): bool {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }

    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Require valid CSRF token or abort with HTTP 419 / error message
 */
function require_csrf_token(): void {
    if (!verify_csrf_token()) {
        flash('danger', 'Security validation failed (invalid or expired CSRF token). Please try again.');
        // If referer is present, redirect back; otherwise, redirect to home/login
        $referer = $_SERVER['HTTP_REFERER'] ?? url();
        redirect($referer);
    }
}
