<?php
/**
 * Canned Responses - Delete Template Handler (Admin Only, POST + CSRF)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_role(ROLE_ADMIN);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/canned_responses/index.php');
}

if (!verify_csrf_token()) {
    flash('danger', 'Security validation failed. Please try again.');
    redirect('modules/canned_responses/index.php');
}

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    flash('danger', 'Invalid template reference.');
    redirect('modules/canned_responses/index.php');
}

$db = get_db();

// Verify Template exists
$stmt = $db->prepare("SELECT id, title FROM canned_responses WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$template = $stmt->fetch();

if (!$template) {
    flash('danger', 'Template not found.');
    redirect('modules/canned_responses/index.php');
}

// Delete template
$delStmt = $db->prepare("DELETE FROM canned_responses WHERE id = ?");
$delStmt->execute([$id]);

flash('success', "Template <strong>" . e($template['title']) . "</strong> has been deleted.");
redirect('modules/canned_responses/index.php');
