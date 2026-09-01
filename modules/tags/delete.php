<?php
/**
 * Tag Management - Delete Tag Handler (Admin Only, POST + CSRF)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_role(ROLE_ADMIN);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/tags/index.php');
}

if (!verify_csrf_token()) {
    flash('danger', 'Security validation failed. Please try again.');
    redirect('modules/tags/index.php');
}

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    flash('danger', 'Invalid tag reference.');
    redirect('modules/tags/index.php');
}

$db = get_db();

// Verify Tag exists
$stmt = $db->prepare("SELECT id, name FROM ticket_tags WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$tag = $stmt->fetch();

if (!$tag) {
    flash('danger', 'Tag not found.');
    redirect('modules/tags/index.php');
}

require_once __DIR__ . '/../../includes/activity_log.php';

// Delete tag (Foreign key CASCADE removes pivot records only; tickets remain completely intact)
$delStmt = $db->prepare("DELETE FROM ticket_tags WHERE id = ?");
$delStmt->execute([$id]);

$user = current_user();
log_activity($user['id'], 'tag', 'tag_deleted', "Deleted ticket tag: {$tag['name']} (ID: {$id})", 'tag', $id);

flash('success', "Tag <strong>" . e($tag['name']) . "</strong> has been deleted.");
redirect('modules/tags/index.php');
