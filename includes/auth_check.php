<?php
/**
 * Authentication & Authorization Check Helpers
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/permissions.php';

/**
 * Check if a user is currently authenticated
 */
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']) && !empty($_SESSION['user']);
}

/**
 * Retrieve the current authenticated user record
 */
function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    return $_SESSION['user'] ?? null;
}

/**
 * Sync and refresh session user data from database
 */
function refresh_user_session(int $userId): bool {
    $db = get_db();
    $stmt = $db->prepare("SELECT id, role, name, email, phone, avatar, department_id, status, email_verified_at, last_login_at, created_at, updated_at, deleted_at FROM users WHERE id = ? AND status = 'active' AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user'] = $user;
        return true;
    }

    // User is deactivated or deleted
    unset($_SESSION['user_id'], $_SESSION['user']);
    return false;
}

/**
 * Guard page: Require user to be logged in
 */
function require_login(): void {
    if (!is_logged_in()) {
        // Remember intended URL if GET request
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? '';
        }
        flash('warning', 'Please sign in to access this page.');
        redirect('auth/login.php');
    }

    // Verify account is still active in database periodically or on request
    if (!refresh_user_session((int)$_SESSION['user_id'])) {
        flash('danger', 'Your account has been deactivated or no longer exists.');
        redirect('auth/login.php');
    }
}

/**
 * Check if the logged in user has one of the required roles
 *
 * @param string|array $roles Single role or array of allowed roles
 */
function has_role($roles): bool {
    if (!is_logged_in()) {
        return false;
    }
    $user = current_user();
    if (!$user) {
        return false;
    }

    $currentRole = strtolower($user['role'] ?? '');
    
    // Normalization mapping for flexible role aliases
    $equivalentRoles = [$currentRole];
    if ($currentRole === 'admin' || $currentRole === 'administrator') {
        $equivalentRoles = ['admin', 'administrator'];
    } elseif ($currentRole === 'agent' || $currentRole === 'support_agent') {
        $equivalentRoles = ['agent', 'support_agent'];
    } elseif ($currentRole === 'manager' || $currentRole === 'support_manager') {
        $equivalentRoles = ['manager', 'support_manager'];
    }

    $targetRoles = is_array($roles) ? array_map('strtolower', $roles) : [strtolower($roles)];
    
    foreach ($equivalentRoles as $r) {
        if (in_array($r, $targetRoles, true)) {
            return true;
        }
    }

    return false;
}

/**
 * Guard page: Require user to have specific role(s)
 *
 * @param string|array $roles
 */
function require_role($roles): void {
    require_login();

    if (!has_role($roles)) {
        flash('danger', 'You do not have permission to access that resource.');
        redirect('index.php');
    }
}

/**
 * Admin Safety Guard: Verify if a user can safely be deactivated
 * Prevents deactivating the last active administrator.
 */
function can_deactivate_user(int $userId): bool {
    return can_modify_user_role_or_status($userId, 'customer', STATUS_INACTIVE);
}

