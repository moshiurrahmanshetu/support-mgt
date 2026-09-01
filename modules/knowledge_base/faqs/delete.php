<?php
/**
 * FAQ Management - Delete FAQ Handler (Admin Only - Phase 06)
 */

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../../includes/activity_log.php';

// Strict Admin Gate
require_role(ROLE_ADMIN);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/knowledge_base/faqs/index.php');
}

if (!verify_csrf_token()) {
    flash('danger', 'Security validation failed. Please try again.');
    redirect('modules/knowledge_base/faqs/index.php');
}

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    flash('danger', 'Invalid FAQ reference.');
    redirect('modules/knowledge_base/faqs/index.php');
}

$db = get_db();

// Verify FAQ exists
$stmt = $db->prepare("SELECT id, question FROM faqs WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$faq = $stmt->fetch();

if (!$faq) {
    flash('danger', 'FAQ not found.');
    redirect('modules/knowledge_base/faqs/index.php');
}

// Delete FAQ
$delStmt = $db->prepare("DELETE FROM faqs WHERE id = ?");
$delStmt->execute([$id]);

$user = current_user();
log_activity($user['id'], 'knowledge_base', 'knowledge_base_faq_deleted', "Deleted FAQ '{$faq['question']}' (ID: {$id})", 'faq', $id);

flash('success', "FAQ has been deleted.");
redirect('modules/knowledge_base/faqs/index.php');
