<?php
/**
 * User Management - Delete User Handler (Phase 08)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/activity_log.php';

// Strict Authorization Guard
require_permission('users.delete');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('danger', 'Invalid request method.');
    redirect('modules/users/index.php');
}

if (!verify_csrf_token()) {
    flash('danger', 'Security token invalid or expired.');
    redirect('modules/users/index.php');
}

$db = get_db();
$currentUser = current_user();
$userId = (int)($_POST['id'] ?? 0);

if ($userId === (int)$currentUser['id']) {
    flash('danger', 'You cannot delete your own active account.');
    redirect('modules/users/index.php');
}

$stmt = $db->prepare("SELECT id, name, email, role, status FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    flash('danger', 'User account not found or already deleted.');
    redirect('modules/users/index.php');
}

// Last Admin Safety Protection
if (!can_delete_user($userId)) {
    flash('danger', 'You cannot delete the last active Administrator in the system.');
    redirect('modules/users/index.php');
}

// Soft Delete user to preserve historical tickets and activity records intact
$delStmt = $db->prepare("UPDATE users SET status = 'inactive', deleted_at = NOW(), updated_at = NOW() WHERE id = ?");
$delStmt->execute([$userId]);

log_activity($currentUser['id'], 'users', 'user_deleted', "Deleted user {$user['name']} ({$user['email']})", 'user', $userId);

flash('success', "User '{$user['name']}' has been removed successfully.");
redirect('modules/users/index.php');
