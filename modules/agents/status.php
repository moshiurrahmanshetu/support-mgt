<?php
/**
 * Agent Management - Status Toggle Handler (POST + CSRF)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_role(ROLE_ADMIN);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/agents/index.php');
}

if (!verify_csrf_token()) {
    flash('danger', 'Security validation failed. Please try again.');
    redirect('modules/agents/index.php');
}

$id = (int)($_POST['id'] ?? 0);
$status = trim($_POST['status'] ?? '');

if ($id <= 0 || !in_array($status, VALID_STATUSES, true)) {
    flash('danger', 'Invalid agent status parameters.');
    redirect('modules/agents/index.php');
}

$db = get_db();

// Verify Agent exists
$stmt = $db->prepare("SELECT id, name FROM users WHERE id = ? AND role = 'agent' LIMIT 1");
$stmt->execute([$id]);
$agent = $stmt->fetch();

if (!$agent) {
    flash('danger', 'Support agent account not found.');
    redirect('modules/agents/index.php');
}

// Update status
$updateStmt = $db->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?");
$updateStmt->execute([$status, $id]);

$label = ($status === STATUS_ACTIVE) ? 'activated' : 'deactivated';
flash('success', "Agent <strong>" . e($agent['name']) . "</strong> has been {$label}.");
redirect($_SERVER['HTTP_REFERER'] ?? 'modules/agents/index.php');
