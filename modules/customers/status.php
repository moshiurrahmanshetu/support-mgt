<?php
/**
 * Customer Management - Status Toggle Handler (Phase 08 Completion)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/activity_log.php';

// Strict Authorization Guard
require_permission('customers.edit');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('danger', 'Invalid request method. Status update must be submitted via POST.');
    redirect('modules/customers/index.php');
}

if (!verify_csrf_token()) {
    flash('danger', 'Security token invalid or expired. Please try again.');
    redirect('modules/customers/index.php');
}

$id = (int)($_POST['id'] ?? 0);
$status = trim($_POST['status'] ?? '');

if ($id <= 0 || !in_array($status, VALID_STATUSES, true)) {
    flash('danger', 'Invalid customer status parameters.');
    redirect('modules/customers/index.php');
}

$db = get_db();
$currentUser = current_user();

// Verify Customer exists and is not deleted
$stmt = $db->prepare("SELECT id, name, email FROM users WHERE id = ? AND role = 'customer' AND deleted_at IS NULL LIMIT 1");
$stmt->execute([$id]);
$customer = $stmt->fetch();

if (!$customer) {
    flash('danger', 'Customer account not found or is deleted.');
    redirect('modules/customers/index.php');
}

// Update status
$updateStmt = $db->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ? AND role = 'customer'");
$updateStmt->execute([$status, $id]);

$label = ($status === STATUS_ACTIVE) ? 'activated' : 'deactivated';
log_activity(
    $currentUser['id'],
    'customer',
    'customer_status_changed',
    "Customer {$customer['name']} ({$customer['email']}) was {$label}",
    'user',
    $id
);

flash('success', "Customer <strong>" . e($customer['name']) . "</strong> has been {$label}.");
redirect('modules/customers/view.php?id=' . $id);
