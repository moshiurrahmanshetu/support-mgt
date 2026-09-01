<?php
/**
 * Customer Management - Delete Customer Handler (Phase 08 Completion)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/activity_log.php';

// Strict Authorization Guard
require_permission('customers.delete');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('danger', 'Invalid request method. Deletion must be submitted via POST.');
    redirect('modules/customers/index.php');
}

if (!verify_csrf_token()) {
    flash('danger', 'Security token invalid or expired. Please try again.');
    redirect('modules/customers/index.php');
}

$db = get_db();
$currentUser = current_user();
$customerId = (int)($_POST['id'] ?? 0);

if ($customerId <= 0) {
    flash('danger', 'Invalid customer identifier.');
    redirect('modules/customers/index.php');
}

// 1. Fetch Customer Record
$stmt = $db->prepare("SELECT id, name, email, role, status, deleted_at FROM users WHERE id = ? AND role = 'customer' LIMIT 1");
$stmt->execute([$customerId]);
$customer = $stmt->fetch();

if (!$customer) {
    flash('danger', 'Customer account not found.');
    redirect('modules/customers/index.php');
}

if (!empty($customer['deleted_at'])) {
    flash('warning', 'This customer account has already been deleted.');
    redirect('modules/customers/index.php');
}

// 2. Perform Soft Delete (Preserves historical tickets, messages, and activity audit trails)
$delStmt = $db->prepare("UPDATE users SET status = 'inactive', deleted_at = NOW(), updated_at = NOW() WHERE id = ? AND role = 'customer'");
$delStmt->execute([$customerId]);

// 3. Log Activity
log_activity(
    $currentUser['id'],
    'customer',
    'customer_deleted',
    "Deleted customer account {$customer['name']} ({$customer['email']})",
    'user',
    $customerId
);

flash('success', "Customer <strong>" . e($customer['name']) . "</strong> has been deleted successfully. Historical tickets remain preserved.");
redirect('modules/customers/index.php');
