<?php
/**
 * Role Management - Delete Custom Role Handler (Phase 08)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/activity_log.php';

// Strict Authorization Guard
require_permission('roles.delete');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('danger', 'Invalid request method.');
    redirect('modules/roles/index.php');
}

if (!verify_csrf_token()) {
    flash('danger', 'Security token invalid or expired.');
    redirect('modules/roles/index.php');
}

$db = get_db();
$currentUser = current_user();
$roleId = (int)($_POST['id'] ?? 0);

// Fetch role record
$stmt = $db->prepare("SELECT * FROM roles WHERE id = ? LIMIT 1");
$stmt->execute([$roleId]);
$role = $stmt->fetch();

if (!$role) {
    flash('danger', 'Role not found.');
    redirect('modules/roles/index.php');
}

// 1. Prevent deleting built-in system roles
if ((int)$role['is_system'] === 1) {
    flash('danger', 'Built-in system roles cannot be deleted.');
    redirect('modules/roles/index.php');
}

// 2. Prevent deleting roles currently assigned to users
$userCheckStmt = $db->prepare("SELECT COUNT(*) FROM user_roles WHERE role_id = ?");
$userCheckStmt->execute([$roleId]);
$assignedCount = (int)$userCheckStmt->fetchColumn();

if ($assignedCount > 0) {
    flash('warning', "This role is currently assigned to {$assignedCount} user(s). Please reassign those users before deleting the role.");
    redirect('modules/roles/view.php?id=' . $roleId);
}

// 3. Delete custom role (Foreign key cascades to role_permissions)
$delStmt = $db->prepare("DELETE FROM roles WHERE id = ?");
$delStmt->execute([$roleId]);

log_activity($currentUser['id'], 'roles', 'role_deleted', "Deleted custom role '{$role['name']}' ({$role['slug']})", 'role', $roleId);

flash('success', "Custom role '{$role['name']}' has been deleted successfully.");
redirect('modules/roles/index.php');
