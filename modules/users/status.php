<?php
/**
 * User Management - Status Toggle Handler (Phase 08)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/activity_log.php';

// Strict Authorization Guard
require_permission('users.edit');

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

$stmt = $db->prepare("SELECT id, name, email, role, status FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    flash('danger', 'User account not found.');
    redirect('modules/users/index.php');
}

$newStatus = ($user['status'] === STATUS_ACTIVE) ? STATUS_INACTIVE : STATUS_ACTIVE;

// Last Admin Safety Protection
if (!can_modify_user_role_or_status($userId, $user['role'], $newStatus)) {
    flash('danger', 'You cannot deactivate the last active Administrator in the system.');
    redirect('modules/users/index.php');
}

$updateStmt = $db->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?");
$updateStmt->execute([$newStatus, $userId]);

log_activity($currentUser['id'], 'users', 'user_status_changed', "Changed status of {$user['name']} ({$user['email']}) to {$newStatus}", 'user', $userId);

flash('success', "User '{$user['name']}' has been " . ($newStatus === STATUS_ACTIVE ? 'activated' : 'deactivated') . " successfully.");
redirect('modules/users/index.php');
