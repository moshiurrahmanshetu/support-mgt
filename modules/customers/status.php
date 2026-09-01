<?php
/**
 * Customer Management - Status Toggle Handler (POST + CSRF)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_role(ROLE_ADMIN);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/customers/index.php');
}

if (!verify_csrf_token()) {
    flash('danger', 'Security validation failed. Please try again.');
    redirect('modules/customers/index.php');
}

$id = (int)($_POST['id'] ?? 0);
$status = trim($_POST['status'] ?? '');

if ($id <= 0 || !in_array($status, VALID_STATUSES, true)) {
    flash('danger', 'Invalid customer status parameters.');
    redirect('modules/customers/index.php');
}

$db = get_db();

// Verify Customer exists
$stmt = $db->prepare("SELECT id, name FROM users WHERE id = ? AND role = 'customer' LIMIT 1");
$stmt->execute([$id]);
$customer = $stmt->fetch();

if (!$customer) {
    flash('danger', 'Customer account not found.');
    redirect('modules/customers/index.php');
}

require_once __DIR__ . '/../../includes/activity_log.php';

// Update status
$updateStmt = $db->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?");
$updateStmt->execute([$status, $id]);

$label = ($status === STATUS_ACTIVE) ? 'activated' : 'deactivated';
$user = current_user();
log_activity($user['id'], 'customer', 'customer_status_changed', "Customer {$customer['name']} was {$label}", 'user', $id);

flash('success', "Customer <strong>" . e($customer['name']) . "</strong> has been {$label}.");
redirect($_SERVER['HTTP_REFERER'] ?? 'modules/customers/index.php');
