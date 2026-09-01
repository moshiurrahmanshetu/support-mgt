<?php
/**
 * Department Management - Status Toggle Handler (POST + CSRF)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_role(ROLE_ADMIN);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/departments/index.php');
}

if (!verify_csrf_token()) {
    flash('danger', 'Security validation failed. Please try again.');
    redirect('modules/departments/index.php');
}

$id = (int)($_POST['id'] ?? 0);
$status = trim($_POST['status'] ?? '');

if ($id <= 0 || !in_array($status, VALID_STATUSES, true)) {
    flash('danger', 'Invalid department status request parameters.');
    redirect('modules/departments/index.php');
}

$db = get_db();

// Fetch department
$stmt = $db->prepare("SELECT id, name FROM departments WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$dept = $stmt->fetch();

if (!$dept) {
    flash('danger', 'Department not found.');
    redirect('modules/departments/index.php');
}

// Update status
$updateStmt = $db->prepare("UPDATE departments SET status = ?, updated_at = NOW() WHERE id = ?");
$updateStmt->execute([$status, $id]);

$label = ($status === STATUS_ACTIVE) ? 'activated' : 'deactivated';
flash('success', "Department <strong>" . e($dept['name']) . "</strong> has been {$label}.");
redirect('modules/departments/index.php');
